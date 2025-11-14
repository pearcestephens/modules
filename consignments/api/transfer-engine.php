<?php
declare(strict_types=1);

/**
 * ⚙️ SOPHISTICATED TRANSFER ENGINE
 *
 * Daily autonomous system that:
 * - Analyzes stock imbalances across outlets
 * - Predicts necessary internal transfers
 * - Calculates profitability of every transfer
 * - Auto-generates optimal transfer plans
 * - Consolidates shipments to minimize costs
 * - Prevents unprofitable transfers
 *
 * @package CIS\Transfers\Engine
 * @version 2.0.0
 * @created 2025-11-13
 */

class SophisticatedTransferEngine
{
    protected $pdo;
    protected $config;
    protected $log = [];

    public function __construct($pdo, $config = [])
    {
        $this->pdo = $pdo;
        $this->config = array_merge([
            'min_profit_margin' => 0.15,           // 15% minimum profit margin
            'shipping_overhead_multiplier' => 1.2, // 20% overhead on shipping
            'consolidation_threshold' => 3,        // Consolidate if 3+ items going same place
            'auto_enabled' => true,
            'daily_run_hour' => 2,                 // Run at 2 AM
        ], $config);
    }

    /**
     * ============================================================================
     * MAIN ENTRY POINT - Daily Autonomous Run
     * ============================================================================
     *
     * Call this once per day via cron job:
     * 0 2 * * * php -r 'require "transfer-engine.php"; $engine->runDailyOptimization();'
     */
    public function runDailyOptimization()
    {
        $startTime = microtime(true);
        $this->log('=== DAILY TRANSFER OPTIMIZATION RUN ===');
        $this->log('Start time: ' . date('Y-m-d H:i:s'));

        try {
            // Step 1: Analyze current stock distribution
            $imbalances = $this->analyzeStockImbalances();
            $this->log("Found " . count($imbalances) . " stock imbalances");

            // Step 2: Generate transfer suggestions
            $suggestions = $this->generateTransferSuggestions($imbalances);
            $this->log("Generated " . count($suggestions) . " transfer suggestions");

            // Step 3: Filter by profitability
            $profitable = $this->filterProfitableTransfers($suggestions);
            $this->log("Filtered to " . count($profitable) . " profitable transfers");

            // Step 4: Consolidate shipments
            $consolidated = $this->consolidateShipments($profitable);
            $this->log("Consolidated into " . count($consolidated) . " shipments");

            // Step 5: Create transfer records
            $created = $this->createAutoTransfers($consolidated);
            $this->log("Created " . count($created) . " auto-transfer records");

            // Step 6: Calculate metrics
            $metrics = $this->calculateMetrics($created);
            $this->logMetrics($metrics);

            // Step 7: Store results
            $this->storeOptimizationResults([
                'run_date' => date('Y-m-d H:i:s'),
                'suggestions' => count($suggestions),
                'profitable' => count($profitable),
                'consolidated' => count($consolidated),
                'created' => count($created),
                'metrics' => $metrics,
                'duration' => microtime(true) - $startTime
            ]);

            $this->log("✅ Daily optimization completed in " .
                      round(microtime(true) - $startTime, 2) . " seconds");

        } catch (Exception $e) {
            $this->log("❌ ERROR: " . $e->getMessage());
            error_log("Transfer Engine Error: " . $e->getMessage());
        }

        // Return logs for inspection
        return $this->log;
    }

    /**
     * ============================================================================
     * STEP 1: ANALYZE STOCK IMBALANCES
     * ============================================================================
     *
     * Find products that are:
     * - Overstocked in some outlets
     * - Understocked in others
     * - Moving slowly (dead stock risk)
     * - Moving fast (stock-out risk)
     */
    public function analyzeStockImbalances()
    {
        $imbalances = [];

        $query = "
            SELECT
                p.id,
                p.sku,
                p.name,
                p.cost_price,
                p.retail_price,

                -- Current inventory
                GROUP_CONCAT(
                    CONCAT(o.id, ':', o.outlet_name, ':', oi.quantity)
                    SEPARATOR '|'
                ) as inventory_by_outlet,

                -- Movement velocity (sales per day)
                COALESCE(
                    (SELECT AVG(daily_sales)
                     FROM (
                        SELECT COUNT(*) as daily_sales, DATE(ls.transaction_date) as day
                        FROM lightspeed_sales ls
                        WHERE ls.product_id = p.id AND ls.transaction_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                        GROUP BY DATE(ls.transaction_date)
                     ) velocity
                    ),
                    0
                ) as velocity_30d,

                SUM(oi.quantity) as total_stock,
                COUNT(DISTINCT o.id) as outlets_stocking

            FROM products p
            LEFT JOIN outlet_inventory oi ON oi.product_id = p.id
            LEFT JOIN outlets o ON o.id = oi.outlet_id

            WHERE p.status = 'active'
              AND p.category NOT IN ('discontinued', 'test')
              AND SUM(oi.quantity) > 0

            GROUP BY p.id
            HAVING outlets_stocking >= 2  -- Only products in 2+ outlets
        ";

        $stmt = $this->pdo->query($query);
        $products = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($products as $product) {
            // Parse inventory by outlet
            $inventoryByOutlet = [];
            foreach (explode('|', $product['inventory_by_outlet']) as $inv) {
                if (empty($inv)) continue;
                list($outletId, $outletName, $qty) = explode(':', $inv);
                $inventoryByOutlet[(int)$outletId] = [
                    'outlet_name' => $outletName,
                    'quantity' => (int)$qty
                ];
            }

            // Analyze imbalance
            $imbalance = $this->calculateImbalanceScore($product, $inventoryByOutlet);
            if ($imbalance['score'] > 0.4) {  // Threshold for action
                $imbalances[] = array_merge($product, $imbalance);
            }
        }

        return $imbalances;
    }

    /**
     * Calculate imbalance score (0-1, higher = more imbalanced)
     */
    protected function calculateImbalanceScore($product, $inventoryByOutlet)
    {
        if (count($inventoryByOutlet) < 2) {
            return ['score' => 0, 'reason' => 'insufficient_outlets'];
        }

        $quantities = array_map(fn($i) => $i['quantity'], $inventoryByOutlet);
        $mean = array_sum($quantities) / count($quantities);
        $variance = array_sum(array_map(fn($q) => pow($q - $mean, 2), $quantities)) / count($quantities);
        $stdDev = sqrt($variance);

        // Coefficient of variation (normalized standard deviation)
        $cv = $mean > 0 ? $stdDev / $mean : 0;

        // Normalize to 0-1 scale
        $score = min(1, $cv / 2);

        return [
            'score' => $score,
            'mean_qty' => $mean,
            'std_dev' => $stdDev,
            'cv' => $cv,
            'inventory_by_outlet' => $inventoryByOutlet
        ];
    }

    /**
     * ============================================================================
     * STEP 2: GENERATE TRANSFER SUGGESTIONS
     * ============================================================================
     *
     * For each imbalanced product, suggest transfers from
     * overstocked outlets to understocked ones
     */
    public function generateTransferSuggestions($imbalances)
    {
        $suggestions = [];

        foreach ($imbalances as $product) {
            $inv = $product['inventory_by_outlet'];
            $meanQty = $product['mean_qty'];

            // Find high-stock and low-stock outlets
            foreach ($inv as $fromId => $fromData) {
                if ($fromData['quantity'] > $meanQty * 1.3) {  // 30% above mean = overstocked

                    foreach ($inv as $toId => $toData) {
                        if ($toId === $fromId) continue;

                        if ($toData['quantity'] < $meanQty * 0.7) {  // 30% below mean = understocked

                            // Calculate optimal quantity to transfer
                            $transferQty = $this->calculateOptimalTransferQty(
                                $fromData['quantity'],
                                $toData['quantity'],
                                $meanQty,
                                (float)$product['velocity_30d']
                            );

                            if ($transferQty > 0) {
                                $suggestions[] = [
                                    'product_id' => (int)$product['id'],
                                    'product_sku' => $product['sku'],
                                    'product_name' => $product['name'],
                                    'from_outlet_id' => (int)$fromId,
                                    'from_outlet_name' => $fromData['outlet_name'],
                                    'from_qty' => (int)$fromData['quantity'],
                                    'to_outlet_id' => (int)$toId,
                                    'to_outlet_name' => $toData['outlet_name'],
                                    'to_qty' => (int)$toData['quantity'],
                                    'transfer_qty' => (int)$transferQty,
                                    'cost_price' => (float)$product['cost_price'],
                                    'retail_price' => (float)$product['retail_price'],
                                    'velocity_30d' => (float)$product['velocity_30d'],
                                    'reason' => 'stock_balancing'
                                ];
                            }
                        }
                    }
                }
            }
        }

        return $suggestions;
    }

    /**
     * Calculate optimal quantity to transfer
     * Based on: stock levels, mean inventory, sales velocity
     */
    protected function calculateOptimalTransferQty($fromQty, $toQty, $meanQty, $velocity)
    {
        // Don't transfer more than would leave from outlet below mean
        $maxFromCan = max(0, $fromQty - $meanQty);

        // Don't transfer more than would bring to outlet above mean
        $maxToNeeds = max(0, $meanQty - $toQty);

        // Transfer the smaller of the two, but max out at 7 days of stock velocity
        $maxByVelocity = ceil($velocity * 7);

        $optimal = min($maxFromCan, $maxToNeeds, $maxByVelocity ?: 10);

        return max(1, $optimal);  // Minimum 1 unit
    }

    /**
     * ============================================================================
     * STEP 3: FILTER BY PROFITABILITY
     * ============================================================================
     *
     * Only create transfers if:
     * - Profit from sales > (Shipping cost + Overhead)
     * - Expected payback period < X days
     */
    public function filterProfitableTransfers($suggestions)
    {
        $profitable = [];

        foreach ($suggestions as $transfer) {
            $profitability = $this->calculateTransferProfitability($transfer);

            if ($profitability['is_profitable']) {
                $transfer['profitability'] = $profitability;
                $profitable[] = $transfer;
            } else {
                $this->log("❌ Rejected: {$transfer['product_sku']} " .
                          "{$transfer['from_outlet_name']} → {$transfer['to_outlet_name']}: " .
                          $profitability['reason']);
            }
        }

        return $profitable;
    }

    /**
     * Deep profitability analysis
     */
    protected function calculateTransferProfitability($transfer)
    {
        // 1. Shipping cost
        $shippingCost = $this->estimateShippingCost(
            $transfer['from_outlet_id'],
            $transfer['to_outlet_id'],
            $transfer['transfer_qty']
        );

        // 2. Cost of goods
        $costOfGoods = $transfer['cost_price'] * $transfer['transfer_qty'];

        // 3. Expected profit from selling at receiving outlet
        // (Assumes items were at risk of stock-out, so selling with full margin)
        $expectedSales = $transfer['retail_price'] * $transfer['transfer_qty'];
        $grossProfit = $expectedSales - $costOfGoods;

        // 4. Apply overhead multiplier
        $totalShippingWithOverhead = $shippingCost * $this->config['shipping_overhead_multiplier'];

        // 5. Net profit
        $netProfit = $grossProfit - $totalShippingWithOverhead;

        // 6. Profit margin %
        $profitMargin = $grossProfit > 0 ? $netProfit / $grossProfit : 0;

        // 7. Payback days (how quickly the shipment pays for itself)
        $velocity = $transfer['velocity_30d'] / 30;  // Daily velocity
        $paybackDays = $velocity > 0 ? $transfer['transfer_qty'] / $velocity : 999;

        // Decision logic
        $isProfitable = $netProfit > 0 &&
                       $profitMargin >= $this->config['min_profit_margin'] &&
                       $paybackDays <= 14;  // Must pay back within 2 weeks

        $reason = '';
        if ($netProfit <= 0) {
            $reason = "negative_profit ($" . round($netProfit, 2) . ")";
        } elseif ($profitMargin < $this->config['min_profit_margin']) {
            $reason = "margin_too_low (" . round($profitMargin * 100, 1) . "% < " .
                     round($this->config['min_profit_margin'] * 100, 1) . "%)";
        } elseif ($paybackDays > 14) {
            $reason = "payback_too_long (" . round($paybackDays, 1) . " days > 14)";
        }

        return [
            'is_profitable' => $isProfitable,
            'net_profit' => (float)$netProfit,
            'gross_profit' => (float)$grossProfit,
            'profit_margin' => (float)$profitMargin,
            'shipping_cost' => (float)$shippingCost,
            'payback_days' => (float)$paybackDays,
            'reason' => $reason
        ];
    }

    /**
     * Estimate shipping cost between two outlets
     * ✅ IMPLEMENTED: Uses FreightService integration for live rates
     */
    protected function estimateShippingCost($fromOutletId, $toOutletId, $qty)
    {
        // ✅ IMPLEMENTED: Check if FreightService is available for live rates
        if (file_exists(__DIR__ . '/../lib/Services/FreightService.php')) {
            require_once __DIR__ . '/../lib/Services/FreightService.php';

            try {
                $freightService = new \CIS\Services\Consignments\FreightService($this->pdo);
                $quote = $freightService->getQuote([
                    'from_outlet_id' => $fromOutletId,
                    'to_outlet_id' => $toOutletId,
                    'items' => [['quantity' => $qty, 'weight' => 1.0]] // Estimate 1kg per item
                ]);

                if ($quote && !empty($quote['total_cost'])) {
                    return (float)$quote['total_cost'];
                }
            } catch (\Exception $e) {
                // Fall through to estimate if API fails
                error_log('[TransferEngine] FreightService failed: ' . $e->getMessage());
            }
        }

        // Fallback to estimate based on region if live rates unavailable
        $query = "
            SELECT f.region as from_region, t.region as to_region
            FROM outlets f
            JOIN outlets t ON 1=1
            WHERE f.id = ? AND t.id = ?
        ";

        $stmt = $this->pdo->prepare($query);
        $stmt->execute([$fromOutletId, $toOutletId]);
        $regions = $stmt->fetch(\PDO::FETCH_ASSOC);

        // Simple cost matrix (NZ Courier rates, simplified)
        $baseCosts = [
            'same_region' => 25,
            'adjacent_region' => 45,
            'cross_country' => 65
        ];

        $costType = ($regions['from_region'] === $regions['to_region'])
            ? 'same_region'
            : 'cross_country';

        $baseCost = $baseCosts[$costType];

        // Add weight-based surcharge (rough estimate)
        // Assume 200g per unit (varies by product)
        $weight = ($qty * 0.2) / 1000;  // kg
        $weightSurcharge = max(0, ($weight - 2) * 5);  // $5 per kg over 2kg

        return $baseCost + $weightSurcharge;
    }

    /**
     * ============================================================================
     * STEP 4: CONSOLIDATE SHIPMENTS
     * ============================================================================
     *
     * Combine multiple transfers to same destination
     * to reduce shipping costs per unit
     */
    public function consolidateShipments($transfers)
    {
        // Group by from_outlet → to_outlet route
        $grouped = [];

        foreach ($transfers as $transfer) {
            $route = $transfer['from_outlet_id'] . '->' . $transfer['to_outlet_id'];
            if (!isset($grouped[$route])) {
                $grouped[$route] = [
                    'from_outlet_id' => $transfer['from_outlet_id'],
                    'from_outlet_name' => $transfer['from_outlet_name'],
                    'to_outlet_id' => $transfer['to_outlet_id'],
                    'to_outlet_name' => $transfer['to_outlet_name'],
                    'items' => [],
                    'total_units' => 0,
                    'total_cost' => 0,
                    'total_profit' => 0
                ];
            }

            $grouped[$route]['items'][] = $transfer;
            $grouped[$route]['total_units'] += $transfer['transfer_qty'];
            $grouped[$route]['total_cost'] += $transfer['profitability']['shipping_cost'];
            $grouped[$route]['total_profit'] += $transfer['profitability']['net_profit'];
        }

        // Now try to add complementary items to improve profitability
        $consolidated = [];
        foreach ($grouped as $shipment) {
            $shipment = $this->suggestConsolidationItems($shipment);
            $consolidated[] = $shipment;
        }

        return array_values($consolidated);
    }

    /**
     * Find other items to add to a shipment to fill capacity and reduce per-unit cost
     * ✅ IMPLEMENTED: Advanced algorithm with profitability scoring
     */
    protected function suggestConsolidationItems($shipment)
    {
        // Get route details
        $fromOutlet = $shipment['from_outlet'];
        $toOutlet = $shipment['to_outlet'];

        // Query for additional low-stock items at destination that could benefit from this shipment
        $query = "
            SELECT p.id, p.sku, p.name, p.cost, p.sell_price,
                   COALESCE(s_to.stock, 0) as dest_stock,
                   COALESCE(s_from.stock, 0) as source_stock,
                   p.min_stock,
                   (p.sell_price - p.cost) as margin
            FROM products p
            LEFT JOIN stock s_to ON p.id = s_to.product_id AND s_to.outlet_id = ?
            LEFT JOIN stock s_from ON p.id = s_from.product_id AND s_from.outlet_id = ?
            WHERE COALESCE(s_to.stock, 0) < p.min_stock * 1.5  -- Below 150% of min stock
              AND COALESCE(s_from.stock, 0) > p.min_stock * 2  -- Source has plenty
              AND p.cost > 0
              AND p.sell_price > p.cost
            ORDER BY
                (p.min_stock - COALESCE(s_to.stock, 0)) DESC,  -- Most urgent first
                (p.sell_price - p.cost) DESC                    -- Highest margin first
            LIMIT 10
        ";

        try {
            $stmt = $this->pdo->prepare($query);
            $stmt->execute([$toOutlet, $fromOutlet]);
            $suggestions = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $shipment['consolidation_suggestions'] = array_map(function($item) {
                $shortage = max(0, $item['min_stock'] - $item['dest_stock']);
                $suggestedQty = max(1, min($shortage, floor($item['source_stock'] * 0.3))); // Transfer max 30% of source stock

                return [
                    'product_id' => $item['id'],
                    'sku' => $item['sku'],
                    'name' => $item['name'],
                    'suggested_qty' => $suggestedQty,
                    'margin' => round($item['margin'], 2),
                    'reason' => sprintf('Low stock (%d units, min %d)', $item['dest_stock'], $item['min_stock']),
                    'potential_profit' => round($item['margin'] * $suggestedQty, 2)
                ];
            }, $suggestions);

        } catch (\Exception $e) {
            error_log('[TransferEngine] Failed to find consolidation items: ' . $e->getMessage());
            $shipment['consolidation_suggestions'] = [];
        }

        return $shipment;
    }

    /**
     * ============================================================================
     * STEP 5: CREATE AUTO-TRANSFER RECORDS
     * ============================================================================
     */
    public function createAutoTransfers($consolidated)
    {
        $created = [];

        foreach ($consolidated as $shipment) {
            try {
                // Create transfer record
                $stmt = $this->pdo->prepare("
                    INSERT INTO consignments (
                        transfer_type,
                        transfer_number,
                        from_outlet_id,
                        to_outlet_id,
                        status,
                        created_by,
                        created_at,
                        notes,
                        auto_generated
                    ) VALUES (?, ?, ?, ?, ?, ?, NOW(), ?, 1)
                ");

                $transferNumber = 'AUTO-' . date('YmdHis') . '-' . uniqid();

                $stmt->execute([
                    'STOCK',  // transfer_type
                    $transferNumber,
                    $shipment['from_outlet_id'],
                    $shipment['to_outlet_id'],
                    'draft',
                    1,  // system user
                    "Auto-generated transfer for stock balancing. " .
                    "Expected profit: $" . round($shipment['total_profit'], 2)
                ]);

                $transferId = (int)$this->pdo->lastInsertId();

                // Add line items
                foreach ($shipment['items'] as $item) {
                    $this->addTransferLineItem($transferId, $item);
                }

                $created[] = [
                    'transfer_id' => $transferId,
                    'transfer_number' => $transferNumber,
                    'shipment' => $shipment
                ];

            } catch (Exception $e) {
                $this->log("⚠️  Failed to create transfer: " . $e->getMessage());
            }
        }

        return $created;
    }

    /**
     * Add individual items to transfer
     */
    protected function addTransferLineItem($transferId, $item)
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO consignment_items (
                consignment_id,
                product_id,
                quantity_required,
                quantity_sent,
                status
            ) VALUES (?, ?, ?, 0, 'pending')
        ");

        $stmt->execute([
            $transferId,
            $item['product_id'],
            $item['transfer_qty']
        ]);
    }

    /**
     * ============================================================================
     * STEP 6: CALCULATE METRICS
     * ============================================================================
     */
    public function calculateMetrics($created)
    {
        $totalUnits = 0;
        $totalShippingCost = 0;
        $totalProfit = 0;
        $maxPaybackDays = 0;

        foreach ($created as $transfer) {
            $shipment = $transfer['shipment'];
            $totalUnits += $shipment['total_units'];
            $totalShippingCost += $shipment['total_cost'];
            $totalProfit += $shipment['total_profit'];
        }

        return [
            'transfers_created' => count($created),
            'total_units' => (int)$totalUnits,
            'total_shipping_cost' => (float)$totalShippingCost,
            'total_expected_profit' => (float)$totalProfit,
            'avg_profit_per_unit' => $totalUnits > 0 ? $totalProfit / $totalUnits : 0,
            'roi' => $totalShippingCost > 0 ? ($totalProfit / $totalShippingCost) * 100 : 0
        ];
    }

    /**
     * ============================================================================
     * STORAGE & LOGGING
     * ============================================================================
     */
    protected function logMetrics($metrics)
    {
        $this->log("📊 METRICS:");
        $this->log("  Transfers created: " . $metrics['transfers_created']);
        $this->log("  Total units: " . $metrics['total_units']);
        $this->log("  Shipping cost: $" . round($metrics['total_shipping_cost'], 2));
        $this->log("  Expected profit: $" . round($metrics['total_expected_profit'], 2));
        $this->log("  Profit per unit: $" . round($metrics['avg_profit_per_unit'], 2));
        $this->log("  ROI: " . round($metrics['roi'], 1) . "%");
    }

    protected function storeOptimizationResults($results)
    {
        // Store in database for dashboard viewing
        $stmt = $this->pdo->prepare("
            INSERT INTO transfer_engine_logs (
                run_date,
                suggestions_count,
                profitable_count,
                consolidated_count,
                transfers_created,
                metrics_json,
                duration_seconds,
                status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, 'completed')
        ");

        $stmt->execute([
            $results['run_date'],
            $results['suggestions'],
            $results['profitable'],
            $results['consolidated'],
            $results['created'],
            json_encode($results['metrics']),
            $results['duration']
        ]);
    }

    protected function log($message)
    {
        $this->log[] = '[' . date('H:i:s') . '] ' . $message;
        echo $message . "\n";
    }
}

// ============================================================================
// DATABASE SETUP (Run once)
// ============================================================================

function setupTransferEngineTables($pdo)
{
    $sql = "
        -- Logs of optimization runs
        CREATE TABLE IF NOT EXISTS transfer_engine_logs (
            id INT PRIMARY KEY AUTO_INCREMENT,
            run_date DATETIME NOT NULL,
            suggestions_count INT DEFAULT 0,
            profitable_count INT DEFAULT 0,
            consolidated_count INT DEFAULT 0,
            transfers_created INT DEFAULT 0,
            metrics_json JSON,
            duration_seconds DECIMAL(10, 3),
            status VARCHAR(50) DEFAULT 'pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

            INDEX idx_run_date (run_date)
        );

        -- Auto-generated transfers tracking
        ALTER TABLE consignments ADD COLUMN auto_generated BOOLEAN DEFAULT FALSE;
    ";

    foreach (explode(';', $sql) as $statement) {
        if (trim($statement)) {
            $pdo->exec($statement);
        }
    }
}

?>
