<?php
/**
 * Smart-Cron Task: Generate Daily Flagged Products
 * 
 * Intelligently selects 20 products per outlet per day that need verification:
 * - Low stock items (critical/low)
 * - High-value products (price > $100)
 * - Fast-moving items (high sales velocity)
 * - Recently changed prices
 * - Products flagged by managers
 * - Random selection from remaining inventory
 * 
 * Runs daily at 1:00 AM
 * 
 * @package CIS\FlaggedProducts\Cron
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/app.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/assets/services/CISLogger.php';
require_once __DIR__ . '/../lib/Logger.php';
require_once __DIR__ . '/../models/FlaggedProductsRepository.php';

use FlaggedProducts\Lib\Logger;

// Track execution start time
$executionStart = microtime(true);

try {
    // Log task start
    Logger::cronTaskStarted('generate_daily_products', [
        'scheduled_time' => date('Y-m-d H:i:s'),
        'target_per_outlet' => 20
    ]);
    
    CISLogger::info('flagged_products_cron', 'Starting daily product generation');
    
    $totalGenerated = 0;
    $totalOutlets = 0;
    $totalSkipped = 0;
    $strategyBreakdown = [];
    
    // Get all active outlets with flagged products settings
    $outlets = sql_query_collection_safe(
        "SELECT id, name, 
                COALESCE(flags_enabled, 1) as flags_enabled,
                COALESCE(flags_per_day, 20) as flags_per_day
         FROM vend_outlets 
         WHERE (deleted_at IS NULL OR deleted_at = '0000-00-00 00:00:00' OR deleted_at = '')
         ORDER BY name",
        []
    );
    
    if (empty($outlets)) {
        CISLogger::warning('flagged_products_cron', 'No active outlets found');
        echo json_encode(['success' => false, 'message' => 'No outlets found']);
        exit;
    }
    
    foreach ($outlets as $outlet) {
        // Check if flags are enabled for this outlet
        if (!$outlet->flags_enabled) {
            CISLogger::info('flagged_products_cron', "Skipping outlet {$outlet->name} - flags disabled");
            $totalSkipped++;
            continue;
        }
        
        // Check if outlet has > 10 sales in the last 7 days
        $salesCount = sql_query_single_value_safe(
            "SELECT COUNT(*) 
             FROM vend_sales 
             WHERE outlet_id = ? 
             AND sale_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
            [$outlet->id]
        );
        
        if ($salesCount <= 10) {
            CISLogger::info('flagged_products_cron', "Skipping outlet {$outlet->name} - only {$salesCount} sales in last 7 days (need >10)");
            $totalSkipped++;
            continue;
        }
        
        $flagsPerDay = (int)$outlet->flags_per_day;
        
        CISLogger::info('flagged_products_cron', "Generating {$flagsPerDay} products for outlet: {$outlet->name} ({$salesCount} sales)");
        
        $selectedProducts = [];
        $productIds = [];
        
        // Calculate distribution based on flags_per_day
        $quotas = [
            'critical_zero' => max(1, round($flagsPerDay * 0.10)), // 10% - Stock showing 0 (verify if truly 0)
            'critical_low' => max(2, round($flagsPerDay * 0.15)),  // 15% - 1-4 units (almost out)
            'teetering' => max(1, round($flagsPerDay * 0.10)),     // 10% - 5-7 units (teetering on low)
            'fast_moving' => max(2, round($flagsPerDay * 0.20)),   // 20% - High sales velocity
            'slow_moving' => max(1, round($flagsPerDay * 0.10)),   // 10% - Low sales, old stock
            'high_value' => max(1, round($flagsPerDay * 0.10)),    // 10% - Expensive items
            'recently_sold_out' => max(1, round($flagsPerDay * 0.05)), // 5% - Recently went to 0
            'random_variety' => max(1, round($flagsPerDay * 0.20)) // 20% - Pure variety
        ];
        
        // 1. STOCK SHOWING 0 (verify if truly zero or data issue)
        $zeroStock = sql_query_collection_safe(
            "SELECT p.id, p.name, p.sku, p.price_including_tax, i.count as stock_level
             FROM vend_products p
             INNER JOIN vend_inventory i ON p.id = i.product_id
             WHERE i.outlet_id = ?
             AND i.count = 0
             AND p.active = 1
             AND (p.deleted_at IS NULL OR p.deleted_at = '0000-00-00 00:00:00' OR p.deleted_at = '')
             ORDER BY RAND()
             LIMIT ?",
            [$outlet->id, $quotas['critical_zero']]
        );
        
        foreach ($zeroStock as $product) {
            $selectedProducts[] = [
                'product_id' => $product->id,
                'outlet_id' => $outlet->id,
                'reason' => 'verify_zero_stock',
                'priority' => 1,
                'auto_flagged' => 1
            ];
            $productIds[] = $product->id;
        }
        
        // 2. CRITICAL LOW STOCK (1-4 units)
        if (!empty($productIds)) {
            $criticalLow = sql_query_collection_safe(
                "SELECT p.id, p.name, p.sku, p.price_including_tax, i.count as stock_level
                 FROM vend_products p
                 INNER JOIN vend_inventory i ON p.id = i.product_id
                 WHERE i.outlet_id = ?
                 AND i.count BETWEEN 1 AND 4
                 AND p.active = 1
                 AND (p.deleted_at IS NULL OR p.deleted_at = '0000-00-00 00:00:00' OR p.deleted_at = '')
                 AND p.id NOT IN (" . implode(',', array_fill(0, count($productIds), '?')) . ")
                 ORDER BY i.count ASC, p.price_including_tax DESC
                 LIMIT ?",
                array_merge([$outlet->id], $productIds, [$quotas['critical_low']])
            );
        } else {
            $criticalLow = sql_query_collection_safe(
                "SELECT p.id, p.name, p.sku, p.price_including_tax, i.count as stock_level
                 FROM vend_products p
                 INNER JOIN vend_inventory i ON p.id = i.product_id
                 WHERE i.outlet_id = ?
                 AND i.count BETWEEN 1 AND 4
                 AND p.active = 1
                 AND (p.deleted_at IS NULL OR p.deleted_at = '0000-00-00 00:00:00' OR p.deleted_at = '')
                 ORDER BY i.count ASC, p.price_including_tax DESC
                 LIMIT ?",
                [$outlet->id, $quotas['critical_low']]
            );
        }
        
        foreach ($criticalLow as $product) {
            $selectedProducts[] = [
                'product_id' => $product->id,
                'outlet_id' => $outlet->id,
                'reason' => 'critical_low_stock',
                'priority' => 1,
                'auto_flagged' => 1
            ];
            $productIds[] = $product->id;
        }
        
        // 3. TEETERING STOCK (5-7 units - on the edge)
        $teetering = sql_query_collection_safe(
            "SELECT p.id, p.name, p.sku, p.price_including_tax, i.count as stock_level
             FROM vend_products p
             INNER JOIN vend_inventory i ON p.id = i.product_id
             WHERE i.outlet_id = ?
             AND i.count BETWEEN 5 AND 7
             AND p.active = 1
             AND (p.deleted_at IS NULL OR p.deleted_at = '0000-00-00 00:00:00' OR p.deleted_at = '')
             " . (!empty($productIds) ? "AND p.id NOT IN (" . implode(',', array_fill(0, count($productIds), '?')) . ")" : "") . "
             ORDER BY RAND()
             LIMIT ?",
            !empty($productIds) ? array_merge([$outlet->id], $productIds, [$quotas['teetering']]) : [$outlet->id, $quotas['teetering']]
        );
        
        foreach ($teetering as $product) {
            $selectedProducts[] = [
                'product_id' => $product->id,
                'outlet_id' => $outlet->id,
                'reason' => 'teetering_stock',
                'priority' => 2,
                'auto_flagged' => 1
            ];
            $productIds[] = $product->id;
        }
        
        // 4. FAST-MOVING PRODUCTS (5+ sales in last 7 days - high velocity)
        $fastMoving = sql_query_collection_safe(
            "SELECT p.id, p.name, p.sku, p.price_including_tax, 
                    COUNT(sl.id) as sales_count,
                    i.count as stock_level
             FROM vend_products p
             INNER JOIN vend_inventory i ON p.id = i.product_id
             INNER JOIN vend_sale_lines sl ON p.id = sl.product_id
             INNER JOIN vend_sales s ON sl.sale_id = s.id
             WHERE i.outlet_id = ?
             AND s.outlet_id = ?
             AND s.sale_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)
             AND p.active = 1
             AND (p.deleted_at IS NULL OR p.deleted_at = '0000-00-00 00:00:00' OR p.deleted_at = '')
             " . (!empty($productIds) ? "AND p.id NOT IN (" . implode(',', array_fill(0, count($productIds), '?')) . ")" : "") . "
             GROUP BY p.id
             HAVING sales_count >= 5
             ORDER BY sales_count DESC, i.count ASC
             LIMIT ?",
            !empty($productIds) ? array_merge([$outlet->id, $outlet->id], $productIds, [$quotas['fast_moving']]) : [$outlet->id, $outlet->id, $quotas['fast_moving']]
        );
        
        foreach ($fastMoving as $product) {
            $selectedProducts[] = [
                'product_id' => $product->id,
                'outlet_id' => $outlet->id,
                'reason' => 'fast_moving',
                'priority' => 3,
                'auto_flagged' => 1
            ];
            $productIds[] = $product->id;
        }
        
        // 5. SLOW-MOVING / OLD STOCK (no sales in 30 days, but has stock)
        $slowMoving = sql_query_collection_safe(
            "SELECT p.id, p.name, p.sku, p.price_including_tax, i.count as stock_level,
                    DATEDIFF(NOW(), p.updated_at) as days_old
             FROM vend_products p
             INNER JOIN vend_inventory i ON p.id = i.product_id
             LEFT JOIN vend_sale_lines sl ON p.id = sl.product_id 
                AND sl.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
             WHERE i.outlet_id = ?
             AND i.count > 5
             AND p.active = 1
             AND (p.deleted_at IS NULL OR p.deleted_at = '0000-00-00 00:00:00' OR p.deleted_at = '')
             " . (!empty($productIds) ? "AND p.id NOT IN (" . implode(',', array_fill(0, count($productIds), '?')) . ")" : "") . "
             GROUP BY p.id
             HAVING COUNT(sl.id) = 0
             ORDER BY days_old DESC, i.count DESC
             LIMIT ?",
            !empty($productIds) ? array_merge([$outlet->id], $productIds, [$quotas['slow_moving']]) : [$outlet->id, $quotas['slow_moving']]
        );
        
        foreach ($slowMoving as $product) {
            $selectedProducts[] = [
                'product_id' => $product->id,
                'outlet_id' => $outlet->id,
                'reason' => 'slow_moving_old_stock',
                'priority' => 4,
                'auto_flagged' => 1
            ];
            $productIds[] = $product->id;
        }
        
        // 6. HIGH-VALUE PRODUCTS (>$100 - expensive items need accuracy)
        $highValue = sql_query_collection_safe(
            "SELECT p.id, p.name, p.sku, p.price_including_tax, i.count as stock_level
             FROM vend_products p
             INNER JOIN vend_inventory i ON p.id = i.product_id
             WHERE i.outlet_id = ?
             AND p.price_including_tax > 100
             AND p.active = 1
             AND (p.deleted_at IS NULL OR p.deleted_at = '0000-00-00 00:00:00' OR p.deleted_at = '')
             " . (!empty($productIds) ? "AND p.id NOT IN (" . implode(',', array_fill(0, count($productIds), '?')) . ")" : "") . "
             ORDER BY p.price_including_tax DESC, i.count ASC
             LIMIT ?",
            !empty($productIds) ? array_merge([$outlet->id], $productIds, [$quotas['high_value']]) : [$outlet->id, $quotas['high_value']]
        );
        
        foreach ($highValue as $product) {
            $selectedProducts[] = [
                'product_id' => $product->id,
                'outlet_id' => $outlet->id,
                'reason' => 'high_value',
                'priority' => 3,
                'auto_flagged' => 1
            ];
            $productIds[] = $product->id;
        }
        
        // 7. RECENTLY SOLD OUT (was >0, now 0 in last 7 days - verify true stockout)
        $recentlySoldOut = sql_query_collection_safe(
            "SELECT p.id, p.name, p.sku, p.price_including_tax, i.count as stock_level,
                    (SELECT COUNT(*) FROM vend_sale_lines sl 
                     INNER JOIN vend_sales s ON sl.sale_id = s.id
                     WHERE sl.product_id = p.id 
                     AND s.outlet_id = ?
                     AND s.sale_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)) as recent_sales
             FROM vend_products p
             INNER JOIN vend_inventory i ON p.id = i.product_id
             WHERE i.outlet_id = ?
             AND i.count = 0
             AND p.active = 1
             AND (p.deleted_at IS NULL OR p.deleted_at = '0000-00-00 00:00:00' OR p.deleted_at = '')
             " . (!empty($productIds) ? "AND p.id NOT IN (" . implode(',', array_fill(0, count($productIds), '?')) . ")" : "") . "
             HAVING recent_sales > 0
             ORDER BY recent_sales DESC
             LIMIT ?",
            !empty($productIds) ? array_merge([$outlet->id, $outlet->id], $productIds, [$quotas['recently_sold_out']]) : [$outlet->id, $outlet->id, $quotas['recently_sold_out']]
        );
        
        foreach ($recentlySoldOut as $product) {
            $selectedProducts[] = [
                'product_id' => $product->id,
                'outlet_id' => $outlet->id,
                'reason' => 'recently_sold_out',
                'priority' => 2,
                'auto_flagged' => 1
            ];
            $productIds[] = $product->id;
        }
        
        // 8. SMART RANDOM VARIETY - Mix of strategies to prevent pattern recognition
        $remaining = $flagsPerDay - count($selectedProducts);
        
        if ($remaining > 0) {
            // Use day of week to rotate through different selection strategies
            $dayOfWeek = (int)date('w'); // 0 (Sunday) to 6 (Saturday)
            $strategy = $dayOfWeek % 4; // 0-3 rotation
            
            $reason = 'random_variety';
            
            switch ($strategy) {
                case 0: // Random from popular brands (Monday/Friday)
                    $randomVariety = sql_query_collection_safe(
                        "SELECT p.id, p.name, p.sku, p.price_including_tax, i.count as stock_level
                         FROM vend_products p
                         INNER JOIN vend_inventory i ON p.id = i.product_id
                         WHERE i.outlet_id = ?
                         AND p.active = 1
                         AND (p.deleted_at IS NULL OR p.deleted_at = '0000-00-00 00:00:00' OR p.deleted_at = '')
                         AND i.count >= 0
                         AND p.brand IS NOT NULL
                         AND p.brand LIKE '%SMOK%' 
                            OR p.brand LIKE '%Geek Vape%'
                            OR p.brand LIKE '%Vaporesso%'
                            OR p.brand LIKE '%IGET%'
                            OR p.brand LIKE '%VooPoo%'
                         " . (!empty($productIds) ? "AND p.id NOT IN (" . implode(',', array_fill(0, count($productIds), '?')) . ")" : "") . "
                         ORDER BY RAND()
                         LIMIT ?",
                        !empty($productIds) ? array_merge([$outlet->id], $productIds, [$remaining]) : [$outlet->id, $remaining]
                    );
                    $reason = 'popular_brands_check';
                    break;
                    
                case 1: // Low stock across all price ranges (Tuesday/Saturday)
                    $randomVariety = sql_query_collection_safe(
                        "SELECT p.id, p.name, p.sku, p.price_including_tax, i.count as stock_level
                         FROM vend_products p
                         INNER JOIN vend_inventory i ON p.id = i.product_id
                         WHERE i.outlet_id = ?
                         AND p.active = 1
                         AND (p.deleted_at IS NULL OR p.deleted_at = '0000-00-00 00:00:00' OR p.deleted_at = '')
                         AND i.count BETWEEN 8 AND 15
                         " . (!empty($productIds) ? "AND p.id NOT IN (" . implode(',', array_fill(0, count($productIds), '?')) . ")" : "") . "
                         ORDER BY RAND()
                         LIMIT ?",
                        !empty($productIds) ? array_merge([$outlet->id], $productIds, [$remaining]) : [$outlet->id, $remaining]
                    );
                    $reason = 'moderate_stock_check';
                    break;
                    
                case 2: // Mid-range price products (Wednesday/Sunday)
                    $randomVariety = sql_query_collection_safe(
                        "SELECT p.id, p.name, p.sku, p.price_including_tax, i.count as stock_level
                         FROM vend_products p
                         INNER JOIN vend_inventory i ON p.id = i.product_id
                         WHERE i.outlet_id = ?
                         AND p.active = 1
                         AND (p.deleted_at IS NULL OR p.deleted_at = '0000-00-00 00:00:00' OR p.deleted_at = '')
                         AND i.count >= 0
                         AND p.price_including_tax BETWEEN 20 AND 60
                         " . (!empty($productIds) ? "AND p.id NOT IN (" . implode(',', array_fill(0, count($productIds), '?')) . ")" : "") . "
                         ORDER BY RAND()
                         LIMIT ?",
                        !empty($productIds) ? array_merge([$outlet->id], $productIds, [$remaining]) : [$outlet->id, $remaining]
                    );
                    $reason = 'mid_price_range_check';
                    break;
                    
                case 3: // Pure random across everything (Thursday)
                default:
                    $randomVariety = sql_query_collection_safe(
                        "SELECT p.id, p.name, p.sku, p.price_including_tax, i.count as stock_level
                         FROM vend_products p
                         INNER JOIN vend_inventory i ON p.id = i.product_id
                         WHERE i.outlet_id = ?
                         AND p.active = 1
                         AND (p.deleted_at IS NULL OR p.deleted_at = '0000-00-00 00:00:00' OR p.deleted_at = '')
                         AND i.count >= 0
                         " . (!empty($productIds) ? "AND p.id NOT IN (" . implode(',', array_fill(0, count($productIds), '?')) . ")" : "") . "
                         ORDER BY RAND()
                         LIMIT ?",
                        !empty($productIds) ? array_merge([$outlet->id], $productIds, [$remaining]) : [$outlet->id, $remaining]
                    );
                    $reason = 'random_variety';
                    break;
            }
            
            foreach ($randomVariety as $product) {
                $selectedProducts[] = [
                    'product_id' => $product->id,
                    'outlet_id' => $outlet->id,
                    'reason' => $reason, // Dynamic reason based on strategy
                    'priority' => 6,
                    'auto_flagged' => 1
                ];
                $productIds[] = $product->id;
            }
        }
        
        // Clear existing flagged products for this outlet (from previous day)
        sql_query_update_or_insert_safe(
            "DELETE FROM flagged_products WHERE outlet_id = ? AND completed = 0 AND auto_flagged = 1",
            [$outlet->id]
        );
        
        // Insert new flagged products
        $inserted = 0;
        foreach ($selectedProducts as $flagged) {
            $sql = "INSERT INTO flagged_products 
                    (product_id, outlet_id, reason, priority, auto_flagged, flagged_at, expires_at)
                    VALUES (?, ?, ?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 24 HOUR))";
            
            sql_query_update_or_insert_safe($sql, [
                $flagged['product_id'],
                $flagged['outlet_id'],
                $flagged['reason'],
                $flagged['priority'],
                $flagged['auto_flagged']
            ]);
            
            $inserted++;
        }
        
        $totalGenerated += $inserted;
        $totalOutlets++;
        
        // Log breakdown of reasons
        $reasonBreakdown = [];
        foreach ($selectedProducts as $sp) {
            if (!isset($reasonBreakdown[$sp['reason']])) {
                $reasonBreakdown[$sp['reason']] = 0;
            }
            $reasonBreakdown[$sp['reason']]++;
            
            // Track global strategy breakdown
            if (!isset($strategyBreakdown[$sp['reason']])) {
                $strategyBreakdown[$sp['reason']] = 0;
            }
            $strategyBreakdown[$sp['reason']]++;
        }
        $breakdownStr = implode(', ', array_map(function($reason, $count) {
            return "{$reason}: {$count}";
        }, array_keys($reasonBreakdown), $reasonBreakdown));
        
        CISLogger::info('flagged_products_cron', "Generated {$inserted} products for {$outlet->name} ({$breakdownStr})");
    }
    
    // Calculate execution time
    $executionTime = microtime(true) - $executionStart;
    
    $message = "Generated {$totalGenerated} products across {$totalOutlets} outlets";
    if ($totalSkipped > 0) {
        $message .= " (skipped {$totalSkipped} outlets - disabled or <10 sales)";
    }
    CISLogger::info('flagged_products_cron', $message);
    
    // Log task completion with metrics
    Logger::cronTaskCompleted('generate_daily_products', true, [
        'products_generated' => $totalGenerated,
        'outlets_processed' => $totalOutlets,
        'outlets_skipped' => $totalSkipped,
        'strategy_breakdown' => $strategyBreakdown,
        'execution_time_seconds' => round($executionTime, 2),
        'avg_products_per_outlet' => $totalOutlets > 0 ? round($totalGenerated / $totalOutlets, 1) : 0
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => $message,
        'total_products' => $totalGenerated,
        'total_outlets' => $totalOutlets,
        'skipped_outlets' => $totalSkipped,
        'execution_time' => round($executionTime, 2)
    ]);
    
} catch (Exception $e) {
    // Calculate execution time for failure case
    $executionTime = microtime(true) - $executionStart;
    
    CISLogger::error('flagged_products_cron', 'Error generating daily products: ' . $e->getMessage());
    
    // Log task failure
    Logger::cronTaskCompleted('generate_daily_products', false, [
        'products_generated' => $totalGenerated ?? 0,
        'outlets_processed' => $totalOutlets ?? 0,
        'execution_time_seconds' => round($executionTime, 2)
    ], $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
    exit(1);
}
