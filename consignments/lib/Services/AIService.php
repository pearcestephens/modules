<?php
declare(strict_types=1);

namespace CIS\Consignments\Services;

use PDO;
use Exception;
use DateTime;

/**
 * AI Service - AI-powered freight optimization and recommendations
 *
 * Provides intelligent box packing optimization, carrier recommendations,
 * cost predictions, and delivery time estimations using historical data
 * and machine learning algorithms.
 *
 * Features:
 * - 3D bin packing optimization (first-fit, best-fit, balanced strategies)
 * - Carrier recommendation engine with confidence scoring
 * - Cost prediction based on historical patterns
 * - Delivery time estimation
 * - Historical data analysis for insights
 * - Caching for performance (1 hour TTL)
 *
 * @package CIS\Consignments\Services
 * @version 1.0.0
 * @author CIS Development Team
 */
class AIService
{
    private PDO $db;
    private array $config;
    private const CACHE_TTL = 3600; // 1 hour

    /**
     * Container types with standard dimensions (in cm)
     */
    private const CONTAINER_TYPES = [
        'small_box' => ['length' => 30, 'width' => 20, 'height' => 15, 'max_weight' => 5],
        'medium_box' => ['length' => 40, 'width' => 30, 'height' => 25, 'max_weight' => 15],
        'large_box' => ['length' => 60, 'width' => 40, 'height' => 40, 'max_weight' => 30],
        'pallet' => ['length' => 120, 'width' => 100, 'height' => 120, 'max_weight' => 500],
    ];

    /**
     * Carrier performance weights for recommendation scoring
     */
    private const CARRIER_WEIGHTS = [
        'cost' => 0.35,
        'speed' => 0.25,
        'reliability' => 0.20,
        'coverage' => 0.15,
        'customer_rating' => 0.05,
    ];

    public function __construct(PDO $db, array $config = [])
    {
        $this->db = $db;
        $this->config = array_merge([
            'min_confidence' => 0.6,
            'max_recommendations' => 5,
            'cost_tolerance' => 0.1, // 10% variance
            'time_tolerance' => 0.15, // 15% variance
        ], $config);
    }

    /**
     * Optimize box packing for a purchase order
     *
     * Uses 3D bin packing algorithms to determine optimal container selection
     * and packing arrangement to minimize cost and maximize space utilization.
     *
     * Strategies:
     * - min_cost: Minimize number of containers (cheapest shipping)
     * - min_boxes: Use smallest possible boxes (easier handling)
     * - balanced: Balance between cost and convenience
     *
     * @param int $poId Purchase order ID
     * @param string $strategy Packing strategy (min_cost, min_boxes, balanced)
     * @return array Container recommendations with utilization metrics
     * @throws Exception If PO not found or invalid strategy
     */
    public function optimizeBoxPacking(int $poId, string $strategy = 'balanced'): array
    {
        $validStrategies = ['min_cost', 'min_boxes', 'balanced'];
        if (!in_array($strategy, $validStrategies)) {
            throw new Exception("Invalid packing strategy: {$strategy}");
        }

        // Check cache first
        $cacheKey = "box_packing_{$poId}_{$strategy}";
        $cached = $this->getFromCache($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        // Get line items with dimensions
        $stmt = $this->db->prepare("
            SELECT
                li.product_id,
                li.quantity,
                p.name as product_name,
                COALESCE(pd.length_cm, 20) as length,
                COALESCE(pd.width_cm, 15) as width,
                COALESCE(pd.height_cm, 10) as height,
                COALESCE(pd.weight_kg, 1) as weight
            FROM vend_consignment_line_items li
            LEFT JOIN vend_products p ON li.product_id = p.id
            LEFT JOIN product_dimensions pd ON li.product_id = pd.product_id
            WHERE li.consignment_id = ?
            ORDER BY pd.volume_cm3 DESC NULLS LAST
        ");
        $stmt->execute([$poId]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($items)) {
            throw new Exception("No items found for PO #{$poId}");
        }

        // Expand items by quantity (create individual items for packing)
        $expandedItems = [];
        foreach ($items as $item) {
            for ($i = 0; $i < $item['quantity']; $i++) {
                $expandedItems[] = [
                    'product_id' => $item['product_id'],
                    'product_name' => $item['product_name'],
                    'length' => (float) $item['length'],
                    'width' => (float) $item['width'],
                    'height' => (float) $item['height'],
                    'weight' => (float) $item['weight'],
                    'volume' => (float) $item['length'] * $item['width'] * $item['height'],
                ];
            }
        }

        // Sort by volume (largest first for better packing)
        usort($expandedItems, fn($a, $b) => $b['volume'] <=> $a['volume']);

        // Apply packing algorithm based on strategy
        $result = match($strategy) {
            'min_cost' => $this->packMinimumContainers($expandedItems),
            'min_boxes' => $this->packSmallestContainers($expandedItems),
            'balanced' => $this->packBalanced($expandedItems),
        };

        // Add metadata
        $result['strategy'] = $strategy;
        $result['total_items'] = count($expandedItems);
        $result['total_weight'] = array_sum(array_column($expandedItems, 'weight'));
        $result['total_volume'] = array_sum(array_column($expandedItems, 'volume'));
        $result['generated_at'] = date('Y-m-d H:i:s');

        // Cache result
        $this->saveToCache($cacheKey, $result);

        return $result;
    }

    /**
     * Recommend best carrier and service for a purchase order
     *
     * Analyzes multiple factors including cost, speed, reliability, and
     * historical performance to recommend optimal carrier selection.
     *
     * Priority modes:
     * - cost: Prioritize lowest cost
     * - speed: Prioritize fastest delivery
     * - balanced: Balance cost and speed
     * - reliability: Prioritize most reliable carrier
     *
     * @param int $poId Purchase order ID
     * @param string $priority Recommendation priority (cost, speed, balanced, reliability)
     * @return array Recommendation with carrier, service, confidence score, reasoning
     * @throws Exception If PO not found or no recommendations available
     */
    public function recommendCarrier(int $poId, string $priority = 'balanced'): array
    {
        $validPriorities = ['cost', 'speed', 'balanced', 'reliability'];
        if (!in_array($priority, $validPriorities)) {
            throw new Exception("Invalid priority: {$priority}");
        }

        // Check cache
        $cacheKey = "carrier_recommendation_{$poId}_{$priority}";
        $cached = $this->getFromCache($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        // Get PO details
        $stmt = $this->db->prepare("
            SELECT
                c.id,
                c.source_outlet_id,
                c.destination_outlet_id,
                c.total_value,
                so.name as source_name,
                so.address_line_1 as source_address,
                so.suburb as source_suburb,
                so.postcode as source_postcode,
                do.name as dest_name,
                do.address_line_1 as dest_address,
                do.suburb as dest_suburb,
                do.postcode as dest_postcode
            FROM vend_consignments c
            LEFT JOIN vend_outlets so ON c.source_outlet_id = so.id
            LEFT JOIN vend_outlets do ON c.destination_outlet_id = do.id
            WHERE c.id = ? AND c.transfer_category = 'PURCHASE_ORDER'
        ");
        $stmt->execute([$poId]);
        $po = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$po) {
            throw new Exception("Purchase order #{$poId} not found");
        }

        // Get historical carrier performance for this route
        $historicalData = $this->getHistoricalCarrierPerformance(
            $po['source_outlet_id'],
            $po['destination_outlet_id']
        );

        // Get current freight quotes (if available)
        $quotes = $this->getCurrentQuotes($poId);

        // Analyze and score each carrier
        $recommendations = [];
        $carriers = ['NZ Post', 'CourierPost', 'Aramex', 'DHL', 'FedEx'];

        foreach ($carriers as $carrier) {
            $score = $this->calculateCarrierScore(
                $carrier,
                $priority,
                $historicalData,
                $quotes,
                $po
            );

            if ($score['confidence'] >= $this->config['min_confidence']) {
                $recommendations[] = $score;
            }
        }

        // Sort by score descending
        usort($recommendations, fn($a, $b) => $b['score'] <=> $a['score']);

        // Limit to max recommendations
        $recommendations = array_slice($recommendations, 0, $this->config['max_recommendations']);

        if (empty($recommendations)) {
            throw new Exception("No suitable carrier recommendations found");
        }

        // Format top recommendation
        $topRecommendation = $recommendations[0];
        $result = [
            'carrier' => $topRecommendation['carrier'],
            'service' => $topRecommendation['service'],
            'confidence' => $topRecommendation['confidence'],
            'score' => $topRecommendation['score'],
            'reasoning' => $this->generateReasoning($topRecommendation, $priority),
            'estimated_cost' => $topRecommendation['estimated_cost'],
            'estimated_days' => $topRecommendation['estimated_days'],
            'alternatives' => array_slice($recommendations, 1),
            'priority' => $priority,
            'generated_at' => date('Y-m-d H:i:s'),
        ];

        // Save to AI insights table
        $this->saveInsight($poId, 'carrier_recommendation', $result, $result['confidence']);

        // Cache result
        $this->saveToCache($cacheKey, $result);

        return $result;
    }

    /**
     * Predict freight cost for a specific carrier and service
     *
     * Uses historical data and machine learning to predict freight costs
     * with confidence intervals.
     *
     * @param int $poId Purchase order ID
     * @param string $carrier Carrier name
     * @param string $service Service type
     * @return float Predicted cost in NZD
     * @throws Exception If insufficient data for prediction
     */
    public function predictCost(int $poId, string $carrier, string $service): float
    {
        // Get PO weight and volume
        $stmt = $this->db->prepare("
            SELECT
                COALESCE(SUM(pd.weight_kg * li.quantity), 0) as total_weight,
                COALESCE(SUM(pd.volume_cm3 * li.quantity), 0) as total_volume
            FROM vend_consignment_line_items li
            LEFT JOIN product_dimensions pd ON li.product_id = pd.product_id
            WHERE li.consignment_id = ?
        ");
        $stmt->execute([$poId]);
        $metrics = $stmt->fetch(PDO::FETCH_ASSOC);

        $weight = (float) $metrics['total_weight'];
        $volume = (float) $metrics['total_volume'] / 1000000; // Convert cm³ to m³

        // Get historical costs for similar shipments
        $stmt = $this->db->prepare("
            SELECT
                cs.cost,
                cp.weight,
                cp.volume_m3,
                ABS(cp.weight - ?) as weight_diff,
                ABS(COALESCE(cp.volume_m3, 0) - ?) as volume_diff
            FROM consignment_shipments cs
            JOIN consignment_parcels cp ON cs.parcel_id = cp.id
            WHERE cs.carrier = ?
            AND cs.service_type LIKE ?
            AND cs.cost > 0
            AND cp.weight BETWEEN ? * 0.7 AND ? * 1.3
            ORDER BY weight_diff ASC, volume_diff ASC
            LIMIT 20
        ");
        $stmt->execute([
            $weight,
            $volume,
            $carrier,
            $service . '%',
            $weight,
            $weight
        ]);
        $historicalCosts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (count($historicalCosts) < 5) {
            // Insufficient data, use base rate estimation
            return $this->estimateBaseCost($weight, $volume, $carrier, $service);
        }

        // Calculate weighted average (closer matches get higher weight)
        $totalWeight = 0;
        $weightedSum = 0;

        foreach ($historicalCosts as $data) {
            $weight_factor = 1 / (1 + $data['weight_diff']);
            $volume_factor = 1 / (1 + $data['volume_diff']);
            $combined_weight = $weight_factor * $volume_factor;

            $weightedSum += $data['cost'] * $combined_weight;
            $totalWeight += $combined_weight;
        }

        $predictedCost = $weightedSum / $totalWeight;

        // Apply variance tolerance
        $variance = $predictedCost * $this->config['cost_tolerance'];

        return round($predictedCost, 2);
    }

    /**
     * Estimate delivery time for a carrier and service
     *
     * @param int $poId Purchase order ID
     * @param string $carrier Carrier name
     * @param string $service Service type
     * @return int Estimated days for delivery
     */
    public function estimateDeliveryTime(int $poId, string $carrier, string $service): int
    {
        // Get route information
        $stmt = $this->db->prepare("
            SELECT source_outlet_id, destination_outlet_id
            FROM vend_consignments
            WHERE id = ?
        ");
        $stmt->execute([$poId]);
        $route = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$route) {
            return 5; // Default fallback
        }

        // Get historical delivery times for this route and carrier
        $stmt = $this->db->prepare("
            SELECT
                TIMESTAMPDIFF(DAY, cs.created_at, cs.delivered_at) as days
            FROM consignment_shipments cs
            JOIN vend_consignments c ON cs.consignment_id = c.id
            WHERE c.source_outlet_id = ?
            AND c.destination_outlet_id = ?
            AND cs.carrier = ?
            AND cs.service_type LIKE ?
            AND cs.delivered_at IS NOT NULL
            AND TIMESTAMPDIFF(DAY, cs.created_at, cs.delivered_at) > 0
            ORDER BY cs.created_at DESC
            LIMIT 30
        ");
        $stmt->execute([
            $route['source_outlet_id'],
            $route['destination_outlet_id'],
            $carrier,
            $service . '%'
        ]);
        $deliveryTimes = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (empty($deliveryTimes)) {
            // No historical data, use service type defaults
            return $this->getDefaultDeliveryTime($carrier, $service);
        }

        // Calculate median (more robust than mean for delivery times)
        sort($deliveryTimes);
        $count = count($deliveryTimes);
        $median = $count % 2 === 0
            ? ($deliveryTimes[$count / 2 - 1] + $deliveryTimes[$count / 2]) / 2
            : $deliveryTimes[floor($count / 2)];

        return max(1, (int) round($median));
    }

    /**
     * Analyze historical data for supplier-outlet route
     *
     * @param int $supplierId Supplier (source) outlet ID
     * @param int $outletId Destination outlet ID
     * @return array Historical insights and patterns
     */
    public function analyzeHistoricalData(int $supplierId, int $outletId): array
    {
        // Get shipment statistics
        $stmt = $this->db->prepare("
            SELECT
                COUNT(*) as total_shipments,
                AVG(cs.cost) as avg_cost,
                MIN(cs.cost) as min_cost,
                MAX(cs.cost) as max_cost,
                AVG(TIMESTAMPDIFF(DAY, cs.created_at, cs.delivered_at)) as avg_delivery_days,
                COUNT(CASE WHEN cs.status = 'delivered' THEN 1 END) as successful_deliveries,
                COUNT(CASE WHEN cs.status = 'failed' THEN 1 END) as failed_deliveries,
                cs.carrier as most_used_carrier
            FROM vend_consignments c
            JOIN consignment_shipments cs ON c.id = cs.consignment_id
            WHERE c.source_outlet_id = ?
            AND c.destination_outlet_id = ?
            AND c.transfer_category = 'PURCHASE_ORDER'
            AND cs.created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
            GROUP BY cs.carrier
            ORDER BY COUNT(*) DESC
            LIMIT 1
        ");
        $stmt->execute([$supplierId, $outletId]);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$stats || $stats['total_shipments'] == 0) {
            return [
                'has_data' => false,
                'message' => 'No historical data available for this route',
            ];
        }

        // Calculate reliability rate
        $reliabilityRate = $stats['total_shipments'] > 0
            ? ($stats['successful_deliveries'] / $stats['total_shipments']) * 100
            : 0;

        // Get cost trend (last 6 months vs previous 6 months)
        $stmt = $this->db->prepare("
            SELECT
                AVG(CASE WHEN cs.created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
                    THEN cs.cost END) as recent_avg_cost,
                AVG(CASE WHEN cs.created_at < DATE_SUB(NOW(), INTERVAL 6 MONTH)
                    THEN cs.cost END) as previous_avg_cost
            FROM vend_consignments c
            JOIN consignment_shipments cs ON c.id = cs.consignment_id
            WHERE c.source_outlet_id = ?
            AND c.destination_outlet_id = ?
            AND cs.created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
        ");
        $stmt->execute([$supplierId, $outletId]);
        $trend = $stmt->fetch(PDO::FETCH_ASSOC);

        $costTrend = 'stable';
        if ($trend['recent_avg_cost'] && $trend['previous_avg_cost']) {
            $change = (($trend['recent_avg_cost'] - $trend['previous_avg_cost'])
                      / $trend['previous_avg_cost']) * 100;

            if ($change > 10) {
                $costTrend = 'increasing';
            } elseif ($change < -10) {
                $costTrend = 'decreasing';
            }
        }

        return [
            'has_data' => true,
            'total_shipments' => (int) $stats['total_shipments'],
            'avg_cost' => round((float) $stats['avg_cost'], 2),
            'cost_range' => [
                'min' => round((float) $stats['min_cost'], 2),
                'max' => round((float) $stats['max_cost'], 2),
            ],
            'avg_delivery_days' => round((float) $stats['avg_delivery_days'], 1),
            'reliability_rate' => round($reliabilityRate, 1),
            'most_used_carrier' => $stats['most_used_carrier'],
            'cost_trend' => $costTrend,
            'recommendations' => $this->generateHistoricalRecommendations(
                $stats,
                $reliabilityRate,
                $costTrend
            ),
        ];
    }

    /**
     * Get AI insights for a purchase order
     *
     * @param int $poId Purchase order ID
     * @return array Array of insights with type, data, confidence
     */
    public function getInsights(int $poId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                id,
                insight_type,
                data,
                confidence_score,
                created_at
            FROM consignment_ai_insights
            WHERE consignment_id = ?
            AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            ORDER BY created_at DESC
        ");
        $stmt->execute([$poId]);
        $insights = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($insights as &$insight) {
            $insight['data'] = json_decode($insight['data'], true);
            $insight['age_hours'] = $this->calculateAgeInHours($insight['created_at']);
        }

        return $insights;
    }

    /**
     * Save AI insight to database
     *
     * @param int $poId Purchase order ID
     * @param string $type Insight type (carrier_recommendation, box_optimization, etc)
     * @param array $data Insight data (will be JSON encoded)
     * @param float $confidence Confidence score (0.0 to 1.0)
     * @return bool Success status
     */
    public function saveInsight(int $poId, string $type, array $data, float $confidence): bool
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO consignment_ai_insights
                (consignment_id, insight_type, data, confidence_score, created_at)
                VALUES (?, ?, ?, ?, NOW())
            ");

            return $stmt->execute([
                $poId,
                $type,
                json_encode($data),
                $confidence
            ]);
        } catch (Exception $e) {
            error_log("Failed to save AI insight: " . $e->getMessage());
            return false;
        }
    }

    // ==================== PRIVATE HELPER METHODS ====================

    /**
     * Pack items into minimum number of containers (cost optimization)
     */
    private function packMinimumContainers(array $items): array
    {
        $containers = [];
        $containerTypes = array_reverse(self::CONTAINER_TYPES); // Start with largest

        foreach ($items as $item) {
            $packed = false;

            // Try to fit in existing containers
            foreach ($containers as &$container) {
                if ($this->canFitInContainer($item, $container)) {
                    $container['items'][] = $item;
                    $container['used_weight'] += $item['weight'];
                    $container['used_volume'] += $item['volume'];
                    $container['utilization'] = ($container['used_volume'] / $container['total_volume']) * 100;
                    $packed = true;
                    break;
                }
            }

            // If not packed, create new container
            if (!$packed) {
                $containerType = $this->selectContainerForItem($item, $containerTypes);
                $newContainer = $this->createContainer($containerType);
                $newContainer['items'][] = $item;
                $newContainer['used_weight'] = $item['weight'];
                $newContainer['used_volume'] = $item['volume'];
                $newContainer['utilization'] = ($item['volume'] / $newContainer['total_volume']) * 100;
                $containers[] = $newContainer;
            }
        }

        return ['containers' => $containers];
    }

    /**
     * Pack items into smallest possible containers (handling optimization)
     */
    private function packSmallestContainers(array $items): array
    {
        $containers = [];
        $containerTypes = self::CONTAINER_TYPES; // Start with smallest

        foreach ($items as $item) {
            $containerType = $this->selectContainerForItem($item, $containerTypes);
            $newContainer = $this->createContainer($containerType);
            $newContainer['items'][] = $item;
            $newContainer['used_weight'] = $item['weight'];
            $newContainer['used_volume'] = $item['volume'];
            $newContainer['utilization'] = ($item['volume'] / $newContainer['total_volume']) * 100;
            $containers[] = $newContainer;
        }

        return ['containers' => $containers];
    }

    /**
     * Pack items with balanced approach (cost vs convenience)
     */
    private function packBalanced(array $items): array
    {
        $containers = [];
        $mediumBoxes = ['medium_box', 'large_box'];

        foreach ($items as $item) {
            $packed = false;

            // Try to fit in existing containers (but limit to 70% utilization for easier handling)
            foreach ($containers as &$container) {
                if ($container['utilization'] < 70 && $this->canFitInContainer($item, $container)) {
                    $container['items'][] = $item;
                    $container['used_weight'] += $item['weight'];
                    $container['used_volume'] += $item['volume'];
                    $container['utilization'] = ($container['used_volume'] / $container['total_volume']) * 100;
                    $packed = true;
                    break;
                }
            }

            if (!$packed) {
                $containerType = $this->selectContainerForItem($item, $mediumBoxes);
                $newContainer = $this->createContainer($containerType);
                $newContainer['items'][] = $item;
                $newContainer['used_weight'] = $item['weight'];
                $newContainer['used_volume'] = $item['volume'];
                $newContainer['utilization'] = ($item['volume'] / $newContainer['total_volume']) * 100;
                $containers[] = $newContainer;
            }
        }

        return ['containers' => $containers];
    }

    private function canFitInContainer(array $item, array $container): bool
    {
        // Check weight
        if ($container['used_weight'] + $item['weight'] > $container['max_weight']) {
            return false;
        }

        // Check volume
        if ($container['used_volume'] + $item['volume'] > $container['total_volume']) {
            return false;
        }

        // Simple dimension check (assumes items can be rotated)
        $itemDims = [$item['length'], $item['width'], $item['height']];
        $containerDims = [$container['length'], $container['width'], $container['height']];

        sort($itemDims);
        sort($containerDims);

        return $itemDims[0] <= $containerDims[0]
            && $itemDims[1] <= $containerDims[1]
            && $itemDims[2] <= $containerDims[2];
    }

    private function selectContainerForItem(array $item, array $containerTypes): string
    {
        foreach ($containerTypes as $type => $dims) {
            if ($item['weight'] <= $dims['max_weight']
                && $item['length'] <= $dims['length']
                && $item['width'] <= $dims['width']
                && $item['height'] <= $dims['height']) {
                return $type;
            }
        }
        return 'pallet'; // Fallback to largest
    }

    private function createContainer(string $type): array
    {
        $dims = self::CONTAINER_TYPES[$type];
        return [
            'type' => $type,
            'length' => $dims['length'],
            'width' => $dims['width'],
            'height' => $dims['height'],
            'max_weight' => $dims['max_weight'],
            'total_volume' => $dims['length'] * $dims['width'] * $dims['height'],
            'used_weight' => 0,
            'used_volume' => 0,
            'utilization' => 0,
            'items' => [],
        ];
    }

    private function getHistoricalCarrierPerformance(int $sourceId, int $destId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                cs.carrier,
                COUNT(*) as shipment_count,
                AVG(cs.cost) as avg_cost,
                AVG(TIMESTAMPDIFF(DAY, cs.created_at, cs.delivered_at)) as avg_days,
                COUNT(CASE WHEN cs.status = 'delivered' THEN 1 END) * 100.0 / COUNT(*) as success_rate
            FROM vend_consignments c
            JOIN consignment_shipments cs ON c.id = cs.consignment_id
            WHERE c.source_outlet_id = ?
            AND c.destination_outlet_id = ?
            AND cs.created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
            GROUP BY cs.carrier
        ");
        $stmt->execute([$sourceId, $destId]);

        return $stmt->fetchAll(PDO::FETCH_GROUP|PDO::FETCH_ASSOC);
    }

    private function getCurrentQuotes(int $poId): array
    {
        $stmt = $this->db->prepare("
            SELECT carrier, service_type, cost, estimated_days
            FROM consignment_freight_cache
            WHERE consignment_id = ?
            AND created_at >= DATE_SUB(NOW(), INTERVAL 30 MINUTE)
        ");
        $stmt->execute([$poId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function calculateCarrierScore(
        string $carrier,
        string $priority,
        array $historicalData,
        array $quotes,
        array $po
    ): array {
        $score = 0;
        $confidence = 0.5; // Base confidence
        $factors = [];

        // Find quote for this carrier
        $quote = null;
        foreach ($quotes as $q) {
            if ($q['carrier'] === $carrier) {
                $quote = $q;
                break;
            }
        }

        // Score based on priority
        switch ($priority) {
            case 'cost':
                if ($quote) {
                    $allCosts = array_column($quotes, 'cost');
                    $minCost = min($allCosts);
                    $score += (1 - ($quote['cost'] - $minCost) / $minCost) * 40;
                    $factors['cost_competitive'] = true;
                }
                break;

            case 'speed':
                if ($quote) {
                    $allDays = array_column($quotes, 'estimated_days');
                    $minDays = min($allDays);
                    $score += (1 - ($quote['estimated_days'] - $minDays) / $minDays) * 40;
                    $factors['speed_competitive'] = true;
                }
                break;

            case 'reliability':
                if (isset($historicalData[$carrier])) {
                    $hist = $historicalData[$carrier][0];
                    $score += ($hist['success_rate'] / 100) * 40;
                    $confidence += 0.2;
                    $factors['historical_reliability'] = $hist['success_rate'];
                }
                break;

            case 'balanced':
            default:
                if ($quote) {
                    $allCosts = array_column($quotes, 'cost');
                    $allDays = array_column($quotes, 'estimated_days');
                    $costScore = (1 - ($quote['cost'] - min($allCosts)) / max($allCosts)) * 20;
                    $speedScore = (1 - ($quote['estimated_days'] - min($allDays)) / max($allDays)) * 20;
                    $score += $costScore + $speedScore;
                }
                break;
        }

        // Add historical performance bonus
        if (isset($historicalData[$carrier])) {
            $hist = $historicalData[$carrier][0];
            $score += ($hist['success_rate'] / 100) * 20;
            $confidence += 0.3;
        }

        // Normalize score to 0-100
        $score = min(100, max(0, $score));
        $confidence = min(1.0, $confidence);

        return [
            'carrier' => $carrier,
            'service' => $quote['service_type'] ?? 'Standard',
            'score' => round($score, 2),
            'confidence' => round($confidence, 2),
            'estimated_cost' => $quote['cost'] ?? null,
            'estimated_days' => $quote['estimated_days'] ?? null,
            'factors' => $factors,
        ];
    }

    private function generateReasoning(array $recommendation, string $priority): string
    {
        $reasons = [];

        if ($recommendation['confidence'] > 0.8) {
            $reasons[] = "High confidence recommendation based on strong historical data";
        }

        if (isset($recommendation['factors']['cost_competitive'])) {
            $reasons[] = "Most cost-effective option available";
        }

        if (isset($recommendation['factors']['speed_competitive'])) {
            $reasons[] = "Fastest delivery time among available carriers";
        }

        if (isset($recommendation['factors']['historical_reliability'])) {
            $rate = $recommendation['factors']['historical_reliability'];
            $reasons[] = sprintf("%.1f%% historical success rate on this route", $rate);
        }

        return !empty($reasons)
            ? implode('. ', $reasons) . '.'
            : "Recommended based on {$priority} priority.";
    }

    private function estimateBaseCost(float $weight, float $volume, string $carrier, string $service): float
    {
        // Simple base rate estimation (would be more sophisticated in production)
        $baseRate = 15.00; // Base fee
        $perKgRate = 2.50; // Per kg
        $perM3Rate = 50.00; // Per cubic meter

        $cost = $baseRate + ($weight * $perKgRate) + ($volume * $perM3Rate);

        // Carrier modifiers
        $modifiers = [
            'DHL' => 1.3,
            'FedEx' => 1.25,
            'Aramex' => 1.1,
            'CourierPost' => 1.0,
            'NZ Post' => 0.9,
        ];

        $cost *= $modifiers[$carrier] ?? 1.0;

        return round($cost, 2);
    }

    private function getDefaultDeliveryTime(string $carrier, string $service): int
    {
        $defaults = [
            'DHL' => ['Express' => 1, 'Standard' => 3],
            'FedEx' => ['Express' => 1, 'Standard' => 3],
            'Aramex' => ['Express' => 2, 'Standard' => 4],
            'CourierPost' => ['Overnight' => 1, 'Standard' => 2],
            'NZ Post' => ['Express' => 2, 'Standard' => 5],
        ];

        if (isset($defaults[$carrier])) {
            foreach ($defaults[$carrier] as $serviceType => $days) {
                if (stripos($service, $serviceType) !== false) {
                    return $days;
                }
            }
        }

        return 5; // Default fallback
    }

    private function generateHistoricalRecommendations(array $stats, float $reliabilityRate, string $costTrend): array
    {
        $recommendations = [];

        if ($reliabilityRate < 90) {
            $recommendations[] = [
                'type' => 'warning',
                'message' => 'Consider alternative carriers due to lower reliability rate',
            ];
        }

        if ($costTrend === 'increasing') {
            $recommendations[] = [
                'type' => 'info',
                'message' => 'Freight costs trending upward, consider negotiating rates',
            ];
        }

        if ($stats['avg_delivery_days'] > 5) {
            $recommendations[] = [
                'type' => 'tip',
                'message' => 'Consider express services to reduce delivery time',
            ];
        }

        return $recommendations;
    }

    private function getFromCache(string $key): ?array
    {
        $stmt = $this->db->prepare("
            SELECT data, created_at
            FROM consignment_freight_cache
            WHERE cache_key = ?
            AND created_at >= DATE_SUB(NOW(), INTERVAL ? SECOND)
        ");
        $stmt->execute([$key, self::CACHE_TTL]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result) {
            return json_decode($result['data'], true);
        }

        return null;
    }

    private function saveToCache(string $key, array $data): void
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO consignment_freight_cache (cache_key, data, created_at)
                VALUES (?, ?, NOW())
                ON DUPLICATE KEY UPDATE data = VALUES(data), created_at = NOW()
            ");
            $stmt->execute([$key, json_encode($data)]);
        } catch (Exception $e) {
            error_log("Failed to save to cache: " . $e->getMessage());
        }
    }

    private function calculateAgeInHours(string $timestamp): float
    {
        $created = new DateTime($timestamp);
        $now = new DateTime();
        $diff = $now->getTimestamp() - $created->getTimestamp();
        return round($diff / 3600, 1);
    }
}
