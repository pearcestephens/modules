<?php

declare(strict_types=1);
/**
 * MultiStrategyExtractor - Orchestrator for Multi-Strategy Product Extraction.
 *
 * Extraction strategies in priority order:
 * 1. DOM/Source Parsing (primary) - XPath, CSS selectors, regex
 * 2. API Interception (high value) - Fetch/XHR requests via CDP
 * 3. Dropdown Extraction - <select> elements, data-* attributes
 * 4. Hidden Elements - Hidden inputs, JSON in <script> tags
 * 5. Network Traffic Analysis - Full HAR capture
 * 6. Schema.org Structured Data - LD+JSON, Microdata
 *
 * @version 3.0.0
 */

namespace CIS\SharedServices\ProductIntelligence\Extraction;

use DOMDocument;
use DOMXPath;
use Exception;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

use function count;

use const CURLINFO_HTTP_CODE;
use const CURLOPT_FOLLOWLOCATION;
use const CURLOPT_RETURNTRANSFER;
use const CURLOPT_TIMEOUT;
use const CURLOPT_USERAGENT;

class MultiStrategyExtractor
{
    // Strategy priority order
    private const STRATEGY_PRIORITY = [
        'api'        => 10,        // Highest - cleanest data
        'schema'     => 9,      // Structured data
        'dom'        => 8,         // Primary fallback
        'dropdown'   => 6,    // Good for variants
        'hidden'     => 5,      // Often contains IDs
        'network'    => 4,     // Traffic analysis
        'screenshot' => 2,  // GPT Vision fallback
    ];

    private LoggerInterface $logger;

    private array $config;

    private array $extractors = [];

    private array $extractionLog = [];

    public function __construct(?LoggerInterface $logger = null, array $config = [])
    {
        $this->logger = $logger ?? new NullLogger();
        $this->config = array_merge([
            'use_all_strategies'    => true,
            'stop_on_first_success' => false,
            'min_confidence'        => 0.70,
            'max_extraction_time'   => 30, // seconds
        ], $config);
    }

    /**
     * Extract product data using all available strategies.
     *
     * @param string $url           Target URL
     * @param array  $chromeSession Chrome session data
     *
     * @return array Extracted product data with confidence scores
     */
    public function extract(string $url, array $chromeSession = []): array
    {
        $this->extractionLog = [];
        $startTime           = microtime(true);

        $results = [
            'url'             => $url,
            'timestamp'       => date('Y-m-d H:i:s'),
            'strategies_used' => [],
            'extraction_time' => 0,
            'data'            => [],
            'confidence'      => 0.0,
        ];

        // Try each strategy in priority order
        $strategies = $this->getStrategiesInOrder();

        foreach ($strategies as $strategyName => $priority) {
            if (microtime(true) - $startTime > $this->config['max_extraction_time']) {
                $this->log('Extraction timeout reached');

                break;
            }

            $strategyResult = $this->executeStrategy($strategyName, $url, $chromeSession);

            if ($strategyResult['success']) {
                $results['strategies_used'][] = [
                    'name'        => $strategyName,
                    'confidence'  => $strategyResult['confidence'],
                    'data_points' => count($strategyResult['data']),
                ];

                // Merge data with confidence-based prioritization
                $results['data'] = $this->mergeData($results['data'], $strategyResult['data']);

                $this->log("Strategy '{$strategyName}' extracted " . count($strategyResult['data']) . ' data points');

                if ($this->config['stop_on_first_success']
                    && $strategyResult['confidence'] >= $this->config['min_confidence']) {
                    break;
                }
            }
        }

        $results['extraction_time'] = round(microtime(true) - $startTime, 3);
        $results['confidence']      = $this->calculateOverallConfidence($results);
        $results['extraction_log']  = $this->extractionLog;

        return $results;
    }

    /**
     * Execute a specific extraction strategy.
     */
    private function executeStrategy(string $strategy, string $url, array $chromeSession): array
    {
        try {
            $this->logger->debug("Executing strategy: {$strategy}", ['url' => $url]);

            switch ($strategy) {
                case 'api':
                    return $this->extractFromAPI($url, $chromeSession);
                case 'schema':
                    return $this->extractFromSchema($url);
                case 'dom':
                    return $this->extractFromDOM($url);
                case 'dropdown':
                    return $this->extractFromDropdowns($url, $chromeSession);
                case 'hidden':
                    return $this->extractFromHiddenElements($url);
                case 'network':
                    return $this->extractFromNetworkTraffic($url, $chromeSession);
                case 'screenshot':
                    return $this->extractFromScreenshot($url, $chromeSession);
                default:
                    return ['success' => false, 'data' => [], 'confidence' => 0.0];
            }
        } catch (Exception $e) {
            $this->logger->warning("Strategy '{$strategy}' failed: " . $e->getMessage(), [
                'url' => $url,
                'exception' => get_class($e)
            ]);
            $this->log("Strategy '{$strategy}' failed: " . $e->getMessage());

            return ['success' => false, 'data' => [], 'confidence' => 0.0];
        }
    }

    /**
     * Extract from intercepted API calls.
     */
    private function extractFromAPI(string $url, array $chromeSession): array
    {
        // Placeholder - requires Chrome DevTools Protocol integration
        if (empty($chromeSession['intercepted_requests'])) {
            return ['success' => false, 'data' => [], 'confidence' => 0.0];
        }

        $data       = [];
        $confidence = 0.95; // API data is highly reliable

        foreach ($chromeSession['intercepted_requests'] as $request) {
            if ($this->isProductAPI($request['url'])) {
                $jsonData = json_decode($request['response'], true);

                if ($jsonData) {
                    $data = array_merge($data, $this->parseAPIResponse($jsonData));
                }
            }
        }

        return [
            'success'    => !empty($data),
            'data'       => $data,
            'confidence' => $confidence,
        ];
    }

    /**
     * Extract from Schema.org structured data.
     */
    private function extractFromSchema(string $url): array
    {
        $html = $this->fetchHTML($url);
        if (!$html) {
            return ['success' => false, 'data' => [], 'confidence' => 0.0];
        }

        $data       = [];
        $confidence = 0.90; // Structured data is very reliable

        // LD+JSON
        if (preg_match_all('/<script[^>]*type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/is', $html, $matches)) {
            foreach ($matches[1] as $json) {
                $schemaData = json_decode($json, true);

                if ($schemaData && isset($schemaData['@type']) && $schemaData['@type'] === 'Product') {
                    $data['name']      = $schemaData['name'] ?? null;
                    $data['brand']     = $schemaData['brand']['name'] ?? null;
                    $data['sku']       = $schemaData['sku'] ?? null;
                    $data['image_url'] = $schemaData['image'] ?? null;

                    if (isset($schemaData['offers'])) {
                        $data['price']        = $schemaData['offers']['price'] ?? null;
                        $data['currency']     = $schemaData['offers']['priceCurrency'] ?? null;
                        $data['availability'] = $schemaData['offers']['availability'] ?? null;
                    }
                }
            }
        }

        return [
            'success'    => !empty($data),
            'data'       => $data,
            'confidence' => $confidence,
        ];
    }

    /**
     * Extract from DOM using XPath and CSS selectors.
     */
    private function extractFromDOM(string $url): array
    {
        $html = $this->fetchHTML($url);
        if (!$html) {
            return ['success' => false, 'data' => [], 'confidence' => 0.0];
        }

        $data       = [];
        $confidence = 0.75;

        // Use DOMDocument for parsing
        $dom = new DOMDocument();
        @$dom->loadHTML($html);
        $xpath = new DOMXPath($dom);

        // Common product selectors
        $selectors = [
            'name' => [
                "//h1[contains(@class, 'product')]",
                "//h1[contains(@class, 'title')]",
                "//*[@itemprop='name']",
            ],
            'price' => [
                "//*[contains(@class, 'price')]",
                "//*[@itemprop='price']",
            ],
            'brand' => [
                "//*[contains(@class, 'brand')]",
                "//*[@itemprop='brand']",
            ],
            'sku' => [
                "//*[contains(@class, 'sku')]",
                "//*[@itemprop='sku']",
            ],
        ];

        foreach ($selectors as $field => $xpaths) {
            foreach ($xpaths as $xpathQuery) {
                $nodes = $xpath->query($xpathQuery);
                if ($nodes->length > 0) {
                    $data[$field] = trim($nodes[0]->textContent);

                    break;
                }
            }
        }

        // Extract images
        $images = $xpath->query("//img[contains(@class, 'product') or contains(@alt, 'product')]");
        if ($images->length > 0) {
            $data['image_url'] = $images[0]->getAttribute('src');
        }

        return [
            'success'    => !empty($data),
            'data'       => $data,
            'confidence' => $confidence,
        ];
    }

    /**
     * Extract from dropdown menus.
     */
    private function extractFromDropdowns(string $url, array $chromeSession): array
    {
        $html = $this->fetchHTML($url);
        if (!$html) {
            return ['success' => false, 'data' => [], 'confidence' => 0.0];
        }

        $data       = [];
        $confidence = 0.70;

        $dom = new DOMDocument();
        @$dom->loadHTML($html);
        $xpath = new DOMXPath($dom);

        // Find all select elements
        $selects = $xpath->query('//select');

        foreach ($selects as $select) {
            $selectName = $select->getAttribute('name');
            $options    = [];

            $optionNodes = $xpath->query('.//option', $select);
            foreach ($optionNodes as $option) {
                $options[] = [
                    'value' => $option->getAttribute('value'),
                    'text'  => trim($option->textContent),
                ];
            }

            if (!empty($options)) {
                $data['variants'][$selectName] = $options;
            }
        }

        return [
            'success'    => !empty($data),
            'data'       => $data,
            'confidence' => $confidence,
        ];
    }

    /**
     * Extract from hidden elements.
     */
    private function extractFromHiddenElements(string $url): array
    {
        $html = $this->fetchHTML($url);
        if (!$html) {
            return ['success' => false, 'data' => [], 'confidence' => 0.0];
        }

        $data       = [];
        $confidence = 0.65;

        // Hidden inputs
        if (preg_match_all('/<input[^>]*type=["\']hidden["\'][^>]*>/i', $html, $matches)) {
            foreach ($matches[0] as $input) {
                if (preg_match('/name=["\']([^"\']+)["\']/', $input, $nameMatch)
                    && preg_match('/value=["\']([^"\']+)["\']/', $input, $valueMatch)) {
                    $name  = $nameMatch[1];
                    $value = $valueMatch[1];

                    // Look for product-related fields
                    if (preg_match('/product|sku|item/i', $name)) {
                        $data[$name] = $value;
                    }
                }
            }
        }

        // JSON in script tags
        if (preg_match_all('/<script[^>]*>(.*?)<\/script>/is', $html, $matches)) {
            foreach ($matches[1] as $script) {
                if (preg_match('/\{.*?\}/s', $script, $jsonMatch)) {
                    $jsonData = json_decode($jsonMatch[0], true);
                    if ($jsonData && $this->containsProductData($jsonData)) {
                        $data = array_merge($data, $this->parseJSONForProductData($jsonData));
                    }
                }
            }
        }

        return [
            'success'    => !empty($data),
            'data'       => $data,
            'confidence' => $confidence,
        ];
    }

    /**
     * Extract from network traffic analysis.
     */
    private function extractFromNetworkTraffic(string $url, array $chromeSession): array
    {
        // Placeholder - requires full HAR capture
        return ['success' => false, 'data' => [], 'confidence' => 0.0];
    }

    /**
     * Extract from screenshot using GPT Vision.
     */
    private function extractFromScreenshot(string $url, array $chromeSession): array
    {
        // Placeholder - requires GPT Vision integration
        return ['success' => false, 'data' => [], 'confidence' => 0.0];
    }

    /**
     * Check if URL is a product API endpoint.
     */
    private function isProductAPI(string $url): bool
    {
        $patterns = [
            '/\/api\/.*product/i',
            '/\/api\/.*item/i',
            '/\/api\/.*inventory/i',
            '/\/products\.json/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $url)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Parse API response for product data.
     */
    private function parseAPIResponse(array $jsonData): array
    {
        $data = [];

        // Common API structures
        $mappings = [
            'name'  => ['name', 'title', 'product_name', 'productName'],
            'price' => ['price', 'amount', 'value', 'cost'],
            'sku'   => ['sku', 'code', 'item_code', 'product_code'],
            'brand' => ['brand', 'manufacturer', 'vendor'],
            'stock' => ['stock', 'quantity', 'available', 'in_stock'],
        ];

        foreach ($mappings as $field => $keys) {
            foreach ($keys as $key) {
                if (isset($jsonData[$key])) {
                    $data[$field] = $jsonData[$key];

                    break;
                }
            }
        }

        return $data;
    }

    /**
     * Check if JSON contains product data.
     */
    private function containsProductData(array $jsonData): bool
    {
        $productKeys = ['product', 'sku', 'price', 'item', 'productId'];

        foreach ($productKeys as $key) {
            if (isset($jsonData[$key])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Parse JSON for product data.
     */
    private function parseJSONForProductData(array $jsonData): array
    {
        return $this->parseAPIResponse($jsonData);
    }

    /**
     * Fetch HTML content.
     */
    private function fetchHTML(string $url): ?string
    {
        try {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0.0.0',
                CURLOPT_TIMEOUT        => 15,
            ]);

            $html     = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            return $httpCode === 200 ? $html : null;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Merge data from multiple strategies.
     */
    private function mergeData(array $existing, array $new): array
    {
        foreach ($new as $key => $value) {
            if (!isset($existing[$key]) || empty($existing[$key])) {
                $existing[$key] = $value;
            }
        }

        return $existing;
    }

    /**
     * Calculate overall confidence score.
     */
    private function calculateOverallConfidence(array $results): float
    {
        if (empty($results['strategies_used'])) {
            return 0.0;
        }

        $totalConfidence = 0;
        $totalWeight     = 0;

        foreach ($results['strategies_used'] as $strategy) {
            $weight = self::STRATEGY_PRIORITY[$strategy['name']] ?? 1;
            $totalConfidence += $strategy['confidence'] * $weight;
            $totalWeight += $weight;
        }

        return $totalWeight > 0 ? $totalConfidence / $totalWeight : 0.0;
    }

    /**
     * Get strategies sorted by priority.
     */
    private function getStrategiesInOrder(): array
    {
        $strategies = self::STRATEGY_PRIORITY;
        arsort($strategies);

        return $strategies;
    }

    /**
     * Get ordered strategies (for tests).
     * Returns array with strategy details including name and priority.
     */
    private function getOrderedStrategies(): array
    {
        $strategies = self::STRATEGY_PRIORITY;
        arsort($strategies);

        $ordered = [];
        foreach ($strategies as $name => $priority) {
            $ordered[] = [
                'name' => $name,
                'priority' => $priority,
                'enabled' => true
            ];
        }

        return $ordered;
    }

    /**
     * Log extraction event.
     */
    private function log(string $message): void
    {
        $this->extractionLog[] = [
            'timestamp' => microtime(true),
            'message'   => $message,
        ];
    }
}
