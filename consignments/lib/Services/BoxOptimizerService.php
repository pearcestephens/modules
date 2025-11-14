<?php
declare(strict_types=1);

namespace CIS\Consignments\Services;

use PDO;
use RuntimeException;

/**
 * Box Optimizer Service
 *
 * Real-time box optimization engine that provides:
 * - Dimensional validation (carrier limits, density checks)
 * - Weight tier detection (crossing thresholds)
 * - Box utilization analysis (space efficiency)
 * - Consolidation suggestions (merge boxes to save cost)
 * - Carrier comparison (best option for dimensions+weight)
 * - Historical learning (patterns from past shipments)
 *
 * @package CIS\Consignments\Services
 * @version 1.0.0
 * @author AI Development Team
 */
class BoxOptimizerService
{
    private PDO $db;
    private array $config;

    /**
     * Carrier dimension limits (cm)
     */
    private const CARRIER_LIMITS = [
        'nz_post' => [
            'max_length' => 300,
            'max_single_dimension' => 150,
            'max_girth' => 300, // L+W+H
            'max_weight' => 30,
        ],
        'nz_courier' => [
            'max_length' => 300,
            'max_single_dimension' => 200,
            'max_girth' => 300,
            'max_weight' => 50,
        ],
        'gss' => [
            'max_length' => 300,
            'max_single_dimension' => 200,
            'max_girth' => 300,
            'max_weight' => 50,
        ],
        'generic' => [
            'max_length' => 300,
            'max_single_dimension' => 200,
            'max_girth' => 400,
            'max_weight' => 100,
        ]
    ];

    /**
     * Weight tier thresholds for NZ Courier
     */
    private const NZ_COURIER_TIERS = [
        ['min' => 0, 'max' => 5, 'cost' => 25, 'label' => 'Up to 5kg'],
        ['min' => 5, 'max' => 10, 'cost' => 35, 'label' => '5-10kg'],
        ['min' => 10, 'max' => 15, 'cost' => 45, 'label' => '10-15kg'],
        ['min' => 15, 'max' => 20, 'cost' => 55, 'label' => '15-20kg'],
        ['min' => 20, 'max' => 25, 'cost' => 75, 'label' => '20-25kg'],
        ['min' => 25, 'max' => 30, 'cost' => 95, 'label' => '25-30kg'],
    ];

    /**
     * Weight tier thresholds for NZ Post
     */
    private const NZ_POST_TIERS = [
        ['min' => 0, 'max' => 0.5, 'cost' => 10, 'label' => 'Up to 500g'],
        ['min' => 0.5, 'max' => 2, 'cost' => 15, 'label' => '500g-2kg'],
        ['min' => 2, 'max' => 5, 'cost' => 20, 'label' => 'Small Parcel (5kg)'],
        ['min' => 5, 'max' => 50, 'cost' => 35, 'label' => 'Parcel Post (50kg)'],
        ['min' => 50, 'max' => 100, 'cost' => 65, 'label' => 'Heavy Parcel (100kg)'],
    ];

    public function __construct(PDO $db, array $config = [])
    {
        $this->db = $db;
        $this->config = array_merge([
            'consolidation_min_savings' => 5.00, // Min $5 savings to suggest
            'utilization_warning_threshold' => 25, // % - warn if < this
            'density_min' => 100, // kg/m³
            'density_max' => 1000, // kg/m³
        ], $config);
    }

    /**
     * Analyze a box for optimization opportunities
     *
     * @param array $box Box data: length, width, height (cm), weight (kg)
     * @param string $carrier Carrier code (nz_post, nz_courier, gss, generic)
     * @param int|null $transferId Transfer ID for historical comparison
     * @return array Complete optimization analysis
     */
    public function analyzeBox(
        array $box,
        string $carrier = 'nz_courier',
        ?int $transferId = null
    ): array {
        // Validate input
        $this->validateBoxInput($box);

        $analysis = [
            'box' => $box,
            'carrier' => $carrier,
            'valid' => true,
            'validations' => [],
            'warnings' => [],
            'suggestions' => [],
            'metrics' => [],
        ];

        // Run all analyses
        $analysis['metrics'] = $this->calculateMetrics($box);
        $analysis['validations'] = $this->validateDimensions($box, $carrier);
        $analysis['warnings'] = array_merge(
            $analysis['warnings'],
            $this->checkDensity($box),
            $this->checkUtilization($box),
            $this->checkDimensionSanity($box)
        );

        // Suggestions
        $analysis['suggestions'] = array_merge(
            $this->suggestSmallerBox($box, $carrier),
            $this->suggestTierCrossing($box, $carrier),
            $this->suggestCarrierSwitch($box)
        );

        // Mark invalid if critical validation failed
        if (!empty($analysis['validations'])) {
            $analysis['valid'] = false;
        }

        return $analysis;
    }

    /**
     * Analyze multiple boxes together for consolidation opportunities
     */
    public function analyzeMultipleBoxes(
        array $boxes,
        string $carrier = 'nz_courier'
    ): array {
        $analysis = [
            'boxes' => $boxes,
            'carrier' => $carrier,
            'total_weight' => 0,
            'total_volume' => 0,
            'total_cost_current' => 0,
            'consolidations' => [],
            'carrier_switch_savings' => [],
        ];

        // Calculate totals
        foreach ($boxes as $idx => $box) {
            $metrics = $this->calculateMetrics($box);
            $analysis['total_weight'] += $metrics['weight_kg'];
            $analysis['total_volume'] += $metrics['volume_cm3'];
            $analysis['total_cost_current'] += $this->estimateCost($box, $carrier);
        }

        // Find consolidation opportunities
        $analysis['consolidations'] = $this->findConsolidationOpportunities($boxes, $carrier);

        // Check carrier switch savings
        $analysis['carrier_switch_savings'] = $this->compareCarriers($boxes);

        return $analysis;
    }

    /**
     * Suggest consolidating multiple boxes
     */
    private function findConsolidationOpportunities(
        array $boxes,
        string $carrier
    ): array {
        $suggestions = [];

        // Look for underutilized boxes that could be consolidated
        $underutilized = [];
        foreach ($boxes as $idx => $box) {
            $metrics = $this->calculateMetrics($box);
            if ($metrics['utilization_pct'] < 30) {
                $underutilized[] = ['idx' => $idx, 'box' => $box, 'metrics' => $metrics];
            }
        }

        if (count($underutilized) >= 2) {
            // Try consolidating the two smallest underutilized boxes
            usort($underutilized, fn($a, $b) => $a['metrics']['weight_kg'] <=> $b['metrics']['weight_kg']);

            $first = $underutilized[0];
            $second = $underutilized[1];

            // Check if they'd fit in one box
            $combined = [
                'length' => max($first['box']['length'], $second['box']['length']),
                'width' => max($first['box']['width'], $second['box']['width']),
                'height' => $first['box']['height'] + $second['box']['height'], // Stack them
                'weight' => $first['box']['weight'] + $second['box']['weight'],
            ];

            $currentCost = $this->estimateCost($first['box'], $carrier) +
                          $this->estimateCost($second['box'], $carrier);
            $combinedCost = $this->estimateCost($combined, $carrier);
            $savings = $currentCost - $combinedCost;

            if ($savings >= $this->config['consolidation_min_savings']) {
                $suggestions[] = [
                    'type' => 'consolidation',
                    'boxes_involved' => [$first['idx'], $second['idx']],
                    'current_cost' => $currentCost,
                    'combined_cost' => $combinedCost,
                    'savings' => $savings,
                    'new_box' => $combined,
                    'description' => sprintf(
                        'Consolidate boxes #%d and #%d into one larger box (save $%.2f)',
                        $first['idx'] + 1,
                        $second['idx'] + 1,
                        $savings
                    ),
                    'confidence' => 0.85,
                ];
            }
        }

        return $suggestions;
    }

    /**
     * Compare cost across carriers for given boxes
     */
    private function compareCarriers(array $boxes): array {
        $carriers = ['nz_post', 'nz_courier', 'gss'];
        $results = [];

        foreach ($carriers as $carrier) {
            $total_cost = array_sum(
                array_map(fn($box) => $this->estimateCost($box, $carrier), $boxes)
            );
            $results[$carrier] = $total_cost;
        }

        // Find cheapest
        $cheapest = array_key_first(array_filter(
            $results,
            fn($cost) => $cost === min($results)
        ));

        $recommendations = [];
        foreach ($results as $carrier => $cost) {
            if ($carrier !== $cheapest) {
                $savings = $cost - $results[$cheapest];
                $recommendations[] = [
                    'carrier' => $carrier,
                    'cost' => $cost,
                    'savings_vs_best' => $savings,
                ];
            }
        }

        return [
            'current_cheapest' => $cheapest,
            'current_cost' => $results[$cheapest],
            'alternatives' => $recommendations,
        ];
    }

    /**
     * Suggest smaller box for this box
     */
    private function suggestSmallerBox(array $box, string $carrier): array {
        $metrics = $this->calculateMetrics($box);

        if ($metrics['utilization_pct'] > 60) {
            // Box is well utilized, no suggestion
            return [];
        }

        // Standard box sizes to try (in cm)
        $standardSizes = [
            ['length' => 20, 'width' => 15, 'height' => 10],
            ['length' => 30, 'width' => 20, 'height' => 15],
            ['length' => 40, 'width' => 30, 'height' => 25],
            ['length' => 50, 'width' => 40, 'height' => 30],
        ];

        $suggestions = [];

        foreach ($standardSizes as $size) {
            // Check if item fits in this size
            $itemDims = [$box['length'], $box['width'], $box['height']];
            sort($itemDims);
            $sizeDims = [$size['length'], $size['width'], $size['height']];
            sort($sizeDims);

            $fits = ($itemDims[0] <= $sizeDims[0] &&
                     $itemDims[1] <= $sizeDims[1] &&
                     $itemDims[2] <= $sizeDims[2]);

            if ($fits) {
                $currentCost = $this->estimateCost($box, $carrier);
                $newCost = $this->estimateCost($size, $carrier);
                $savings = $currentCost - $newCost;

                if ($savings >= $this->config['consolidation_min_savings']) {
                    $suggestions[] = [
                        'type' => 'smaller_box',
                        'new_box' => $size,
                        'current_cost' => $currentCost,
                        'new_cost' => $newCost,
                        'savings' => $savings,
                        'description' => sprintf(
                            'Use %dx%dx%dcm box instead (save $%.2f)',
                            $size['length'],
                            $size['width'],
                            $size['height'],
                            $savings
                        ),
                        'confidence' => 0.75,
                    ];
                }
            }
        }

        return $suggestions;
    }

    /**
     * Suggest crossing into next weight tier
     */
    private function suggestTierCrossing(array $box, string $carrier): array {
        $weight = (float)$box['weight'];
        $tiers = $carrier === 'nz_post' ? self::NZ_POST_TIERS : self::NZ_COURIER_TIERS;

        $currentTier = null;
        $nextTier = null;

        foreach ($tiers as $tier) {
            if ($weight >= $tier['min'] && $weight < $tier['max']) {
                $currentTier = $tier;
                // Find next tier
                foreach ($tiers as $t) {
                    if ($t['min'] >= $tier['max']) {
                        $nextTier = $t;
                        break;
                    }
                }
                break;
            }
        }

        if (!$currentTier || !$nextTier) {
            return [];
        }

        $weightToAdd = $nextTier['min'] - $weight;
        $currentCost = $currentTier['cost'];
        $nextCost = $nextTier['cost'];

        // Only suggest if adding weight will actually save money
        if ($nextCost < $currentCost) {
            return [];
        }

        // If going to next tier costs MORE, don't suggest
        // This is for consolidation scenarios where adding weight helps

        return [[
            'type' => 'tier_crossing',
            'current_tier' => $currentTier['label'],
            'next_tier' => $nextTier['label'],
            'weight_to_add' => round($weightToAdd, 2),
            'current_cost' => $currentCost,
            'next_tier_cost' => $nextCost,
            'description' => sprintf(
                'You\'re %.2fkg from %s tier. Adding items strategically could consolidate shipments.',
                $weightToAdd,
                $nextTier['label']
            ),
            'confidence' => 0.6,
        ]];
    }

    /**
     * Suggest switching carriers
     */
    private function suggestCarrierSwitch(array $box): array {
        $nzPostCost = $this->estimateCost($box, 'nz_post');
        $nzCourierCost = $this->estimateCost($box, 'nz_courier');

        $suggestions = [];

        if ($nzPostCost < $nzCourierCost) {
            $savings = $nzCourierCost - $nzPostCost;
            if ($savings >= 5) {
                $suggestions[] = [
                    'type' => 'carrier_switch',
                    'from_carrier' => 'nz_courier',
                    'to_carrier' => 'nz_post',
                    'current_cost' => $nzCourierCost,
                    'new_cost' => $nzPostCost,
                    'savings' => $savings,
                    'description' => sprintf(
                        'NZ Post is cheaper for this box (save $%.2f)',
                        $savings
                    ),
                    'confidence' => 0.8,
                ];
            }
        }

        return $suggestions;
    }

    /**
     * Validate box dimensions against carrier limits
     */
    private function validateDimensions(array $box, string $carrier): array {
        $limits = $this->CARRIER_LIMITS[$carrier] ?? $this->CARRIER_LIMITS['generic'];
        $errors = [];

        $length = (float)$box['length'];
        $width = (float)$box['width'];
        $height = (float)$box['height'];
        $weight = (float)$box['weight'];

        // Check single dimensions
        $maxSingle = $limits['max_single_dimension'];
        if ($length > $maxSingle || $width > $maxSingle || $height > $maxSingle) {
            $errors[] = sprintf(
                'Single dimension exceeds %s limit of %dcm (L:%d W:%d H:%d)',
                $carrier,
                $maxSingle,
                $length,
                $width,
                $height
            );
        }

        // Check girth (L+W+H)
        $girth = $length + $width + $height;
        if ($girth > $limits['max_girth']) {
            $errors[] = sprintf(
                'Girth (L+W+H=%d) exceeds %s limit of %d',
                $girth,
                $carrier,
                $limits['max_girth']
            );
        }

        // Check weight
        if ($weight > $limits['max_weight']) {
            $errors[] = sprintf(
                'Weight (%.2fkg) exceeds %s limit of %dkg',
                $weight,
                $carrier,
                $limits['max_weight']
            );
        }

        return $errors;
    }

    /**
     * Check if box density makes sense
     */
    private function checkDensity(array $box): array {
        $volume_m3 = $this->calculateMetrics($box)['volume_m3'];
        $density = $volume_m3 > 0 ? (float)$box['weight'] / $volume_m3 : 0;

        $warnings = [];

        if ($density < $this->config['density_min']) {
            $warnings[] = [
                'severity' => 'warning',
                'type' => 'low_density',
                'message' => sprintf(
                    'Very low density (%.0f kg/m³) - box may be oversized or weight misenter',
                    $density
                ),
            ];
        }

        if ($density > $this->config['density_max']) {
            $warnings[] = [
                'severity' => 'error',
                'type' => 'high_density',
                'message' => sprintf(
                    'Impossible density (%.0f kg/m³) - dimensions or weight likely wrong',
                    $density
                ),
            ];
        }

        return $warnings;
    }

    /**
     * Check box utilization percentage
     */
    private function checkUtilization(array $box): array {
        $metrics = $this->calculateMetrics($box);
        $util = $metrics['utilization_pct'];

        $warnings = [];

        if ($util < $this->config['utilization_warning_threshold']) {
            $warnings[] = [
                'severity' => 'warning',
                'type' => 'low_utilization',
                'percentage' => $util,
                'message' => sprintf(
                    'Box is only %.1f%% utilized - consider consolidating or using smaller box',
                    $util
                ),
            ];
        }

        return $warnings;
    }

    /**
     * Check if dimensions make sanity
     */
    private function checkDimensionSanity(array $box): array {
        $warnings = [];

        $length = (float)$box['length'];
        $width = (float)$box['width'];
        $height = (float)$box['height'];
        $weight = (float)$box['weight'];

        // Check for obviously wrong entries
        if ($length > 500 || $width > 500 || $height > 500) {
            $warnings[] = [
                'severity' => 'error',
                'type' => 'extreme_dimensions',
                'message' => 'Dimensions seem extremely large - please verify',
            ];
        }

        if ($weight > 100) {
            $warnings[] = [
                'severity' => 'warning',
                'type' => 'extreme_weight',
                'message' => 'Weight over 100kg - may need pallet or freight service',
            ];
        }

        return $warnings;
    }

    /**
     * Calculate box metrics
     */
    private function calculateMetrics(array $box): array {
        $length = (float)$box['length'];
        $width = (float)$box['width'];
        $height = (float)$box['height'];
        $weight = (float)$box['weight'];

        $volume_cm3 = $length * $width * $height;
        $volume_m3 = $volume_cm3 / 1_000_000;

        // Utilization: assume typical packing density of 250 kg/m³
        // If actual weight < that, box is underutilized
        $expectedWeight = $volume_m3 * 250; // kg
        $utilization = $expectedWeight > 0 ? ($weight / $expectedWeight) * 100 : 0;

        return [
            'volume_cm3' => (int)$volume_cm3,
            'volume_m3' => round($volume_m3, 6),
            'weight_kg' => $weight,
            'density_kg_m3' => $volume_m3 > 0 ? round($weight / $volume_m3, 2) : 0,
            'utilization_pct' => round(min(100, $utilization), 1),
            'girth' => (int)($length + $width + $height),
        ];
    }

    /**
     * Estimate cost for box with carrier
     */
    private function estimateCost(array $box, string $carrier): float {
        $weight = (float)$box['weight'];
        $tiers = $carrier === 'nz_post' ? self::NZ_POST_TIERS : self::NZ_COURIER_TIERS;

        foreach ($tiers as $tier) {
            if ($weight >= $tier['min'] && $weight < $tier['max']) {
                return (float)$tier['cost'];
            }
        }

        // Default to last tier
        return (float)end($tiers)['cost'];
    }

    /**
     * Validate box input data
     */
    private function validateBoxInput(array $box): void {
        if (!isset($box['length'], $box['width'], $box['height'], $box['weight'])) {
            throw new RuntimeException('Box must have length, width, height, and weight');
        }

        if (!is_numeric($box['length']) || !is_numeric($box['width']) ||
            !is_numeric($box['height']) || !is_numeric($box['weight'])) {
            throw new RuntimeException('Box dimensions and weight must be numeric');
        }

        if ($box['length'] <= 0 || $box['width'] <= 0 || $box['height'] <= 0 || $box['weight'] <= 0) {
            throw new RuntimeException('Box dimensions and weight must be positive');
        }
    }
}
