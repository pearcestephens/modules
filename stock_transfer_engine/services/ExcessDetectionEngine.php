<?php
/**
 * AI Excess Detection Engine
 *
 * SOLVES THE CORE PROBLEM: "Stock not having anywhere to go once it reaches a store"
 *
 * This is the brain that:
 * - Detects overstock EARLY (before it becomes dead stock)
 * - Calculates weeks of stock based on sales velocity
 * - Suggests peer redistribution (Store A excess → Store B gap)
 * - Recommends returns to warehouse when no stores need it
 * - Flags slow movers before they become dead stock
 *
 * Severity Levels:
 * - CAUTION: 8-12 weeks of stock (monitor)
 * - WARNING: 12-16 weeks of stock (redistribute soon)
 * - CRITICAL: 16+ weeks of stock (immediate action needed)
 *
 * @package CIS\Services\StockTransfers
 * @version 1.0.0
 */

namespace CIS\Services\StockTransfers;

use PDO;
use Exception;

class ExcessDetectionEngine
{
    private $db;
    private $logger;
    private $vendAPI;
    private $warehouseManager;

    // Severity thresholds (weeks of stock)
    const THRESHOLD_CAUTION = 8;
    const THRESHOLD_WARNING = 12;
    const THRESHOLD_CRITICAL = 16;

    // Velocity classifications
    const VELOCITY_FAST = 'fast';       // >10 units/week
    const VELOCITY_MEDIUM = 'medium';   // 3-10 units/week
    const VELOCITY_SLOW = 'slow';       // 0.5-3 units/week
    const VELOCITY_DEAD = 'dead';       // <0.5 units/week

    public function __construct(PDO $db, VendTransferAPI $vendAPI, WarehouseManager $warehouseManager, $logger = null)
    {
        $this->db = $db;
        $this->logger = $logger;
        $this->vendAPI = $vendAPI;
        $this->warehouseManager = $warehouseManager;
    }

    /**
     * Detect overstock across all outlets
     *
     * This is the main method - runs daily to identify excess stock
     *
     * @param array $options Scan options (outlet_id, product_id, days_history)
     * @return array Detected excess stock alerts
     */
    public function detectOverstock(array $options = []): array
    {
        $defaults = [
            'outlet_id' => null,
            'product_id' => null,
            'days_history' => 90, // 90 days for velocity calculation
            'min_stock_level' => 1, // Ignore empty inventory
            'create_alerts' => true // Write to excess_stock_alerts table
        ];

        $options = array_merge($defaults, $options);

        $this->log('info', 'Starting overstock detection', $options);

        try {
            // Step 1: Get current stock levels
            $stockLevels = $this->vendAPI->pullStockLevels($options['outlet_id'], $options['product_id']);

            // Step 2: Get sales history for velocity calculation
            $salesHistory = $this->vendAPI->pullSalesHistory([
                'date_from' => date('Y-m-d', strtotime("-{$options['days_history']} days")),
                'date_to' => date('Y-m-d'),
                'outlet_id' => $options['outlet_id'],
                'product_id' => $options['product_id']
            ]);

            // Step 3: Calculate velocity for each product/outlet combination
            $velocityMap = $this->buildVelocityMap($salesHistory, $options['days_history']);

            // Step 4: Detect excess stock
            $excessItems = [];

            foreach ($stockLevels as $stock) {
                if ($stock['stock_level'] < $options['min_stock_level']) {
                    continue;
                }

                $key = "{$stock['product_id']}_{$stock['outlet_id']}";
                $velocity = $velocityMap[$key] ?? null;

                if (!$velocity) {
                    // No sales history - potential dead stock
                    $velocity = [
                        'weekly_sales' => 0,
                        'classification' => self::VELOCITY_DEAD
                    ];
                }

                $weeksOfStock = $this->calculateWeeksOfStock($stock['stock_level'], $velocity['weekly_sales']);
                $severity = $this->calculateSeverity($weeksOfStock, $velocity['classification']);

                // Only flag items with excessive stock
                if ($severity !== null) {
                    $suggestedAction = $this->suggestAction($stock, $velocity, $weeksOfStock);

                    $excessItem = [
                        'product_id' => $stock['product_id'],
                        'product_name' => $stock['product_name'],
                        'sku' => $stock['sku'],
                        'outlet_id' => $stock['outlet_id'],
                        'outlet_name' => $stock['outlet_name'],
                        'current_stock' => $stock['stock_level'],
                        'weekly_sales' => $velocity['weekly_sales'],
                        'weeks_of_stock' => $weeksOfStock,
                        'velocity_classification' => $velocity['classification'],
                        'severity' => $severity,
                        'suggested_action' => $suggestedAction['action'],
                        'action_reasoning' => $suggestedAction['reasoning'],
                        'target_outlet_id' => $suggestedAction['target_outlet_id'] ?? null,
                        'suggested_quantity' => $suggestedAction['quantity'] ?? null,
                        'detected_at' => date('Y-m-d H:i:s')
                    ];

                    $excessItems[] = $excessItem;

                    // Create alert in database
                    if ($options['create_alerts']) {
                        $this->createAlert($excessItem);
                    }
                }
            }

            $this->log('info', 'Overstock detection complete', [
                'excess_items_found' => count($excessItems),
                'outlets_scanned' => count(array_unique(array_column($stockLevels, 'outlet_id'))),
                'products_scanned' => count(array_unique(array_column($stockLevels, 'product_id')))
            ]);

            return $excessItems;

        } catch (Exception $e) {
            $this->log('error', 'Overstock detection failed', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Build velocity map from sales history
     */
    private function buildVelocityMap(array $salesHistory, int $daysHistory): array
    {
        $velocityMap = [];

        // Group sales by product/outlet
        foreach ($salesHistory as $sale) {
            $key = "{$sale['product_id']}_{$sale['outlet_id']}";

            if (!isset($velocityMap[$key])) {
                $velocityMap[$key] = [
                    'total_sold' => 0,
                    'transaction_count' => 0
                ];
            }

            $velocityMap[$key]['total_sold'] += $sale['quantity_sold'];
            $velocityMap[$key]['transaction_count'] += $sale['transaction_count'];
        }

        // Calculate weekly velocity and classification
        $weeks = $daysHistory / 7;

        foreach ($velocityMap as $key => &$data) {
            $data['weekly_sales'] = $data['total_sold'] / $weeks;
            $data['classification'] = $this->classifyVelocity($data['weekly_sales']);
        }

        return $velocityMap;
    }

    /**
     * Calculate weeks of stock
     */
    public function calculateWeeksOfStock(int $currentStock, float $weeklySales): float
    {
        if ($weeklySales <= 0) {
            return 999; // Infinite (no sales)
        }

        return round($currentStock / $weeklySales, 1);
    }

    /**
     * Classify velocity based on weekly sales
     */
    private function classifyVelocity(float $weeklySales): string
    {
        if ($weeklySales >= 10) {
            return self::VELOCITY_FAST;
        } elseif ($weeklySales >= 3) {
            return self::VELOCITY_MEDIUM;
        } elseif ($weeklySales >= 0.5) {
            return self::VELOCITY_SLOW;
        } else {
            return self::VELOCITY_DEAD;
        }
    }

    /**
     * Calculate severity based on weeks of stock and velocity
     */
    private function calculateSeverity(float $weeksOfStock, string $velocity): ?string
    {
        // Dead stock is always critical (even with low stock)
        if ($velocity === self::VELOCITY_DEAD && $weeksOfStock > 4) {
            return 'critical';
        }

        // Fast-moving items need more runway
        if ($velocity === self::VELOCITY_FAST) {
            if ($weeksOfStock >= 20) return 'critical';
            if ($weeksOfStock >= 16) return 'warning';
            if ($weeksOfStock >= 12) return 'caution';
        }

        // Standard thresholds for medium/slow
        if ($weeksOfStock >= self::THRESHOLD_CRITICAL) {
            return 'critical';
        } elseif ($weeksOfStock >= self::THRESHOLD_WARNING) {
            return 'warning';
        } elseif ($weeksOfStock >= self::THRESHOLD_CAUTION) {
            return 'caution';
        }

        return null; // No excess
    }

    /**
     * Suggest action for excess stock
     *
     * This is where the magic happens - deciding what to do with overstock
     */
    private function suggestAction(array $stock, array $velocity, float $weeksOfStock): array
    {
        $productId = $stock['product_id'];
        $outletId = $stock['outlet_id'];
        $currentStock = $stock['stock_level'];

        // Option 1: Find stores that need this product (peer redistribution)
        $gapOutlets = $this->findStockGaps($productId, $outletId);

        if (!empty($gapOutlets)) {
            // Peer redistribution is BEST option
            $targetOutlet = $gapOutlets[0]; // Highest priority gap
            $transferQty = min($currentStock, $targetOutlet['needed_quantity']);

            return [
                'action' => 'peer_transfer',
                'reasoning' => "Store {$targetOutlet['outlet_name']} needs {$targetOutlet['needed_quantity']} units. Transfer {$transferQty} units to fill gap.",
                'target_outlet_id' => $targetOutlet['outlet_id'],
                'quantity' => $transferQty,
                'estimated_savings' => $this->estimateFreightSavings('peer', $transferQty)
            ];
        }

        // Option 2: Return to warehouse (no stores need it)
        if ($velocity['classification'] === self::VELOCITY_DEAD || $weeksOfStock > 24) {
            $warehouse = $this->warehouseManager->getStockSource($productId, 1); // Get warehouse info

            return [
                'action' => 'return_warehouse',
                'reasoning' => "No stores need this product. Weekly sales: {$velocity['weekly_sales']}. Return to warehouse for redistribution or clearance.",
                'target_outlet_id' => $warehouse['outlet_id'] ?? null,
                'quantity' => $currentStock,
                'estimated_savings' => 0
            ];
        }

        // Option 3: Mark for clearance (dead stock, can't return)
        if ($velocity['classification'] === self::VELOCITY_DEAD) {
            return [
                'action' => 'mark_clearance',
                'reasoning' => "Dead stock ({$velocity['weekly_sales']} units/week). Mark for clearance sale.",
                'quantity' => $currentStock,
                'estimated_savings' => 0
            ];
        }

        // Option 4: Wait and monitor (excess but moving)
        return [
            'action' => 'wait_monitor',
            'reasoning' => "Stock is excessive but still selling ({$velocity['weekly_sales']} units/week). Monitor for 2 weeks before acting.",
            'quantity' => 0,
            'estimated_savings' => 0
        ];
    }

    /**
     * Find stores with stock gaps (need this product)
     */
    private function findStockGaps(string $productId, string $sourceOutletId): array
    {
        try {
            $sql = "
                SELECT
                    pi.outlet_id,
                    o.name as outlet_name,
                    pi.count as current_stock,
                    pi.reorder_point,
                    pi.restock_level,
                    (pi.restock_level - pi.count) as needed_quantity,
                    ofz.is_flagship,
                    ofz.is_hub_store,
                    CASE
                        WHEN ofz.is_flagship = 1 THEN 1
                        WHEN ofz.is_hub_store = 1 THEN 2
                        ELSE 3
                    END as priority
                FROM vend_product_inventory pi
                JOIN vend_outlets o ON pi.outlet_id = o.id
                LEFT JOIN outlet_freight_zones ofz ON o.id = ofz.outlet_id
                WHERE pi.product_id = ?
                AND pi.outlet_id != ? -- Not the source outlet
                AND pi.count < pi.reorder_point -- Below reorder point
                ORDER BY priority ASC, needed_quantity DESC
                LIMIT 5
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$productId, $sourceOutletId]);

            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            $this->log('error', 'Failed to find stock gaps', [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Estimate freight savings from peer transfers vs warehouse
     */
    private function estimateFreightSavings(string $routeType, int $quantity): float
    {
        // Rough estimates (will be refined by FreightCalculator later)
        $estimates = [
            'peer' => 6.00,      // Store to store
            'warehouse' => 12.00, // Warehouse to store
            'hub' => 8.00        // Hub store to store
        ];

        $peerCost = $estimates['peer'] * $quantity;
        $warehouseCost = $estimates['warehouse'] * $quantity;

        return round($warehouseCost - $peerCost, 2);
    }

    /**
     * Create alert in database
     */
    private function createAlert(array $excessItem): void
    {
        try {
            $sql = "
                INSERT INTO excess_stock_alerts (
                    product_id,
                    outlet_id,
                    current_stock,
                    weekly_sales,
                    weeks_of_stock,
                    velocity_classification,
                    severity,
                    suggested_action,
                    action_reasoning,
                    target_outlet_id,
                    suggested_quantity,
                    detected_at,
                    status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')
                ON DUPLICATE KEY UPDATE
                    current_stock = VALUES(current_stock),
                    weekly_sales = VALUES(weekly_sales),
                    weeks_of_stock = VALUES(weeks_of_stock),
                    velocity_classification = VALUES(velocity_classification),
                    severity = VALUES(severity),
                    suggested_action = VALUES(suggested_action),
                    action_reasoning = VALUES(action_reasoning),
                    target_outlet_id = VALUES(target_outlet_id),
                    suggested_quantity = VALUES(suggested_quantity),
                    detected_at = VALUES(detected_at),
                    updated_at = CURRENT_TIMESTAMP
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $excessItem['product_id'],
                $excessItem['outlet_id'],
                $excessItem['current_stock'],
                $excessItem['weekly_sales'],
                $excessItem['weeks_of_stock'],
                $excessItem['velocity_classification'],
                $excessItem['severity'],
                $excessItem['suggested_action'],
                $excessItem['action_reasoning'],
                $excessItem['target_outlet_id'],
                $excessItem['suggested_quantity'],
                $excessItem['detected_at']
            ]);

        } catch (Exception $e) {
            $this->log('error', 'Failed to create alert', [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Flag slow movers (before they become dead stock)
     */
    public function flagSlowMovers(array $options = []): array
    {
        $defaults = [
            'velocity_threshold' => 1.0, // < 1 unit per week
            'stock_threshold' => 5,      // At least 5 units in stock
            'days_history' => 60
        ];

        $options = array_merge($defaults, $options);

        $this->log('info', 'Flagging slow movers', $options);

        try {
            $sql = "
                SELECT
                    svt.product_id,
                    p.name as product_name,
                    p.sku,
                    svt.outlet_id,
                    o.name as outlet_name,
                    svt.avg_weekly_sales,
                    svt.velocity_classification,
                    pi.count as current_stock,
                    svt.weeks_of_stock
                FROM stock_velocity_tracking svt
                JOIN vend_products p ON svt.product_id = p.id
                JOIN vend_outlets o ON svt.outlet_id = o.id
                JOIN vend_product_inventory pi ON svt.product_id = pi.product_id AND svt.outlet_id = pi.outlet_id
                WHERE svt.velocity_classification IN ('slow', 'dead')
                AND svt.avg_weekly_sales < ?
                AND pi.count >= ?
                AND svt.updated_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                ORDER BY svt.weeks_of_stock DESC, svt.avg_weekly_sales ASC
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $options['velocity_threshold'],
                $options['stock_threshold'],
                $options['days_history']
            ]);

            $slowMovers = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $this->log('info', 'Slow movers flagged', [
                'count' => count($slowMovers)
            ]);

            return $slowMovers;

        } catch (Exception $e) {
            $this->log('error', 'Failed to flag slow movers', [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Suggest rebalancing opportunities
     * Returns list of potential peer transfers to balance stock
     */
    public function suggestRebalancing(): array
    {
        try {
            $this->log('info', 'Generating rebalancing suggestions');

            $sql = "
                SELECT
                    excess.product_id,
                    p.name as product_name,
                    p.sku,
                    excess.outlet_id as from_outlet_id,
                    o1.name as from_outlet_name,
                    excess.current_stock as excess_stock,
                    excess.suggested_quantity,
                    excess.target_outlet_id as to_outlet_id,
                    o2.name as to_outlet_name,
                    gaps.needed_quantity as gap_quantity,
                    excess.severity,
                    excess.action_reasoning
                FROM excess_stock_alerts excess
                JOIN vend_products p ON excess.product_id = p.id
                JOIN vend_outlets o1 ON excess.outlet_id = o1.id
                LEFT JOIN vend_outlets o2 ON excess.target_outlet_id = o2.id
                LEFT JOIN (
                    SELECT
                        pi.product_id,
                        pi.outlet_id,
                        (pi.restock_level - pi.count) as needed_quantity
                    FROM vend_product_inventory pi
                    WHERE pi.count < pi.reorder_point
                ) gaps ON excess.product_id = gaps.product_id AND excess.target_outlet_id = gaps.outlet_id
                WHERE excess.suggested_action = 'peer_transfer'
                AND excess.status = 'pending'
                ORDER BY excess.severity DESC, excess.weeks_of_stock DESC
                LIMIT 50
            ";

            $stmt = $this->db->query($sql);
            $suggestions = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $this->log('info', 'Rebalancing suggestions generated', [
                'count' => count($suggestions)
            ]);

            return $suggestions;

        } catch (Exception $e) {
            $this->log('error', 'Failed to generate rebalancing suggestions', [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Calculate return viability (should we return to warehouse?)
     */
    public function calculateReturnViability(string $productId, string $outletId): array
    {
        try {
            // Check if any other stores need this product
            $gaps = $this->findStockGaps($productId, $outletId);

            // Get warehouse stock
            $warehouseSource = $this->warehouseManager->getStockSource($productId, 1);

            return [
                'should_return' => empty($gaps),
                'reason' => empty($gaps) ? 'No stores need this product' : count($gaps) . ' stores have gaps',
                'alternative_outlets' => $gaps,
                'warehouse_stock' => $warehouseSource['available_quantity'] ?? 0,
                'recommendation' => empty($gaps) ? 'return_warehouse' : 'peer_transfer'
            ];

        } catch (Exception $e) {
            $this->log('error', 'Failed to calculate return viability', [
                'error' => $e->getMessage()
            ]);
            return ['should_return' => false, 'reason' => 'Error calculating'];
        }
    }

    /**
     * Logger helper
     */
    private function log(string $level, string $message, array $context = []): void
    {
        if ($this->logger && method_exists($this->logger, $level)) {
            $this->logger->$level("[ExcessDetectionEngine] {$message}", $context);
        }
    }
}
