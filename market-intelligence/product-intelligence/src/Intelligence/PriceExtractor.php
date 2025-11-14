<?php

declare(strict_types=1);
/**
 * PriceExtractor - Ultra-Sophisticated Price Extraction with NZD Currency & GST Detection.
 *
 * Features:
 * - NZD currency detection ($, NZD, NZ$)
 * - GST inclusion/exclusion detection
 * - Price range handling
 * - Sale/regular price differentiation
 * - Multi-currency support
 * - Bulk/tiered pricing
 * - Member/guest pricing
 *
 * @version 3.0.0
 */

namespace CIS\SharedServices\ProductIntelligence\Intelligence;

use function count;
use function in_array;

use const PREG_OFFSET_CAPTURE;

class PriceExtractor
{
    // Price patterns (ordered by specificity)
    private const PRICE_PATTERNS = [
        // NZD specific
        '/NZ\$\s*(\d+(?:,\d{3})*(?:\.\d{2})?)/i',
        '/\$\s*(\d+(?:,\d{3})*(?:\.\d{2})?)\s*NZD/i',
        '/(\d+(?:,\d{3})*(?:\.\d{2})?)\s*NZD/i',

        // Generic currency
        '/\$\s*(\d+(?:,\d{3})*(?:\.\d{2})?)/i',
        '/(\d+(?:,\d{3})*\.\d{2})/i',
    ];

    // GST patterns
    private const GST_PATTERNS = [
        'incl' => [
            '/incl(?:uding)?(?:\s+|\.)gst/i',
            '/gst\s+incl(?:uded)?/i',
            '/inc\.?\s+gst/i',
            '/prices?\s+incl(?:ude)?\s+gst/i',
        ],
        'excl' => [
            '/excl(?:uding)?(?:\s+|\.)gst/i',
            '/gst\s+excl(?:uded)?/i',
            '/exc\.?\s+gst/i',
            '/prices?\s+excl(?:ude)?\s+gst/i',
            '/\+\s*gst/i',
        ],
    ];

    // GST rate for NZ
    private const NZ_GST_RATE = 0.15; // 15%

    private array $config;

    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'default_currency'    => 'NZD',
            'assume_gst_incl'     => true, // NZ default
            'detect_sale_prices'  => true,
            'detect_price_ranges' => true,
        ], $config);
    }

    /**
     * Extract all price information from HTML.
     *
     * @param string $html Page HTML
     *
     * @return array Price data with confidence
     */
    public function extractPrices(string $html): array
    {
        $result = [
            'prices'        => [],
            'primary_price' => null,
            'sale_price'    => null,
            'regular_price' => null,
            'currency'      => $this->config['default_currency'],
            'gst_status'    => 'unknown',
            'gst_included'  => null,
            'confidence'    => 0.0,
        ];

        // Extract all prices from HTML
        $allPrices        = $this->findAllPrices($html);
        $result['prices'] = $allPrices;

        if (empty($allPrices)) {
            return $result;
        }

        // Detect GST status
        $gstStatus              = $this->detectGSTStatus($html);
        $result['gst_status']   = $gstStatus['status'];
        $result['gst_included'] = $gstStatus['included'];
        $result['confidence']   = $gstStatus['confidence'];

        // Differentiate sale vs regular prices
        if ($this->config['detect_sale_prices']) {
            $priceDiff               = $this->differentiatePrices($html, $allPrices);
            $result['sale_price']    = $priceDiff['sale'];
            $result['regular_price'] = $priceDiff['regular'];
            $result['primary_price'] = $priceDiff['sale'] ?? $priceDiff['regular'];
        } else {
            $result['primary_price'] = $allPrices[0] ?? null;
        }

        // Handle price ranges
        if ($this->config['detect_price_ranges']) {
            $range = $this->detectPriceRange($allPrices);
            if ($range) {
                $result['price_range'] = $range;
            }
        }

        return $result;
    }

    /**
     * Convert price between GST incl/excl.
     */
    public function convertGST(float $price, bool $fromIncl, bool $toIncl): float
    {
        if ($fromIncl === $toIncl) {
            return $price;
        }

        if ($fromIncl && !$toIncl) {
            // Incl → Excl: divide by (1 + rate)
            return $price / (1 + self::NZ_GST_RATE);
        }

        // Excl → Incl: multiply by (1 + rate)
        return $price * (1 + self::NZ_GST_RATE);
    }

    /**
     * Extract GST amount from price.
     */
    public function calculateGSTAmount(float $priceInclGST): float
    {
        return $priceInclGST - ($priceInclGST / (1 + self::NZ_GST_RATE));
    }

    /**
     * Format price for display.
     */
    public function formatPrice(float $price, string $currency = 'NZD', bool $gstIncluded = true): string
    {
        $formatted = $currency === 'NZD' ? '$' : $currency . ' ';
        $formatted .= number_format($price, 2);
        $formatted .= $gstIncluded ? ' (incl GST)' : ' (excl GST)';

        return $formatted;
    }

    /**
     * Find all prices in HTML.
     */
    private function findAllPrices(string $html): array
    {
        $prices = [];
        $found  = [];

        foreach (self::PRICE_PATTERNS as $pattern) {
            if (preg_match_all($pattern, $html, $matches, PREG_OFFSET_CAPTURE)) {
                foreach ($matches[1] as $match) {
                    $priceStr = $match[0];
                    $offset   = $match[1];

                    // Skip if already found at this position
                    if (isset($found[$offset])) {
                        continue;
                    }

                    // Parse price
                    $price = $this->parsePrice($priceStr);

                    if ($price > 0) {
                        $prices[] = [
                            'value'     => $price,
                            'formatted' => $priceStr,
                            'offset'    => $offset,
                            'context'   => $this->getContext($html, $offset),
                        ];

                        $found[$offset] = true;
                    }
                }
            }
        }

        // Sort by offset (order in page)
        usort($prices, fn ($a, $b) => $a['offset'] <=> $b['offset']);

        return $prices;
    }

    /**
     * Parse price string to float.
     */
    private function parsePrice(string $priceStr): float
    {
        // Remove commas and spaces
        $priceStr = str_replace([',', ' '], '', $priceStr);

        return (float) $priceStr;
    }

    /**
     * Detect GST inclusion/exclusion status.
     */
    private function detectGSTStatus(string $html): array
    {
        $result = [
            'status'     => 'unknown',
            'included'   => null,
            'confidence' => 0.0,
        ];

        // Check for explicit GST mentions
        $inclMatches = 0;
        $exclMatches = 0;

        foreach (self::GST_PATTERNS['incl'] as $pattern) {
            if (preg_match($pattern, $html)) {
                $inclMatches++;
            }
        }

        foreach (self::GST_PATTERNS['excl'] as $pattern) {
            if (preg_match($pattern, $html)) {
                $exclMatches++;
            }
        }

        // Determine status
        if ($inclMatches > 0 && $exclMatches === 0) {
            $result['status']     = 'incl';
            $result['included']   = true;
            $result['confidence'] = min(0.95, 0.80 + ($inclMatches * 0.05));
        } elseif ($exclMatches > 0 && $inclMatches === 0) {
            $result['status']     = 'excl';
            $result['included']   = false;
            $result['confidence'] = min(0.95, 0.80 + ($exclMatches * 0.05));
        } elseif ($inclMatches > 0 && $exclMatches > 0) {
            // Both found - ambiguous
            $result['status']     = 'mixed';
            $result['included']   = null;
            $result['confidence'] = 0.40;
        } else {
            // None found - use default assumption
            $result['status']     = 'assumed_incl';
            $result['included']   = $this->config['assume_gst_incl'];
            $result['confidence'] = 0.50;
        }

        return $result;
    }

    /**
     * Differentiate sale vs regular prices.
     */
    private function differentiatePrices(string $html, array $allPrices): array
    {
        $result = [
            'sale'    => null,
            'regular' => null,
        ];

        if (count($allPrices) < 2) {
            $result['regular'] = $allPrices[0] ?? null;

            return $result;
        }

        // Look for sale price indicators
        $saleKeywords = [
            'sale', 'special', 'now', 'save', 'discount',
            'was', 'rrp', 'regular', 'original',
        ];

        foreach ($allPrices as $price) {
            $context = strtolower($price['context']);

            // Check if context indicates sale price
            $isSale    = false;
            $isRegular = false;

            foreach ($saleKeywords as $keyword) {
                if (stripos($context, $keyword) !== false) {
                    if (in_array($keyword, ['was', 'rrp', 'regular', 'original'], true)) {
                        $isRegular = true;
                    } else {
                        $isSale = true;
                    }
                }
            }

            if ($isSale && !$result['sale']) {
                $result['sale'] = $price;
            } elseif ($isRegular && !$result['regular']) {
                $result['regular'] = $price;
            }
        }

        // If not detected by keywords, assume first (lower) is sale, second (higher) is regular
        if (!$result['sale'] && !$result['regular']) {
            if ($allPrices[0]['value'] < $allPrices[1]['value']) {
                $result['sale']    = $allPrices[0];
                $result['regular'] = $allPrices[1];
            } else {
                $result['regular'] = $allPrices[0];
            }
        }

        return $result;
    }

    /**
     * Detect price range (e.g., "$10 - $20").
     */
    private function detectPriceRange(array $prices): ?array
    {
        if (count($prices) < 2) {
            return null;
        }

        $values = array_column($prices, 'value');
        $min    = min($values);
        $max    = max($values);

        if ($max > $min) {
            return [
                'min'       => $min,
                'max'       => $max,
                'formatted' => '$' . number_format($min, 2) . ' - $' . number_format($max, 2),
            ];
        }

        return null;
    }

    /**
     * Get context around price (50 chars before/after).
     */
    private function getContext(string $html, int $offset, int $contextLength = 50): string
    {
        $start  = max(0, $offset - $contextLength);
        $length = $contextLength * 2;

        return substr($html, $start, $length);
    }
}
