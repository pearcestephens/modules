<?php
/**
 * Smart-Cron Task: Store Stats Cache Refresh
 *
 * Caches store statistics for fast dashboard loading
 * Runs every hour
 *
 * @package CIS\FlaggedProducts\Cron
 */

require_once __DIR__ . '/bootstrap.php';

use FlaggedProducts\Lib\Logger;

// Track execution start time
$executionStart = microtime(true);

try {
    // Log task start
    Logger::cronTaskStarted('refresh_store_stats', [
        'scheduled_time' => date('Y-m-d H:i:s'),
        'cache_ttl' => '2 hours'
    ]);

    CISLogger::info('flagged_products_cron', 'Starting store stats cache refresh');

    // Get all outlets
    $outlets = sql_query_collection_safe("SELECT id, name FROM vend_outlets WHERE (deleted_at IS NULL OR deleted_at = '0000-00-00 00:00:00' OR deleted_at = '')", []);

    $cached = 0;

    foreach ($outlets as $outlet) {
        // Get stats for different time periods
        $periods = [
            'today' => date('Y-m-d'),
            '7days' => date('Y-m-d', strtotime('-7 days')),
            '30days' => date('Y-m-d', strtotime('-30 days')),
            '90days' => date('Y-m-d', strtotime('-90 days'))
        ];

        foreach ($periods as $periodKey => $startDate) {
            $stats = FlaggedProductsRepository::getStoreStats($outlet->id, $startDate, date('Y-m-d'));

            // Add outlet info
            $stats['outlet_id'] = $outlet->id;
            $stats['outlet_name'] = $outlet->name;
            $stats['period'] = $periodKey;
            $stats['cached_at'] = date('Y-m-d H:i:s');

            // Cache it
            $cacheKey = "flagged_products_store_stats_{$outlet->id}_{$periodKey}";
            $cacheData = json_encode($stats);

            $sql = "INSERT INTO smart_cron_cache (cache_key, cache_data, expires_at)
                    VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 2 HOUR))
                    ON DUPLICATE KEY UPDATE
                        cache_data = VALUES(cache_data),
                        expires_at = VALUES(expires_at),
                        updated_at = NOW()";

            sql_query_update_or_insert_safe($sql, [$cacheKey, $cacheData]);
            $cached++;
        }
    }

    // Also cache company-wide stats
    $companyStats = [
        'total_products_completed' => 0,
        'avg_accuracy' => 0,
        'total_active_users' => 0,
        'total_points_awarded' => 0
    ];

    $sql = "SELECT
                COUNT(DISTINCT fp.id) as total_completed,
                AVG(CASE WHEN fp.qty_before = fp.qty_after THEN 100 ELSE 0 END) as avg_accuracy,
                COUNT(DISTINCT fp.completed_by_staff) as active_users,
                SUM(us.points_earned) as total_points
            FROM flagged_products fp
            LEFT JOIN flagged_products_user_stats us ON us.user_id = fp.completed_by_staff
            WHERE fp.date_completed_stocktake >= DATE_SUB(NOW(), INTERVAL 30 DAY)";

    $result = sql_query_single_row_safe($sql, []);

    if ($result) {
        $companyStats = [
            'total_products_completed' => (int)$result->total_completed,
            'avg_accuracy' => round((float)$result->avg_accuracy, 2),
            'total_active_users' => (int)$result->active_users,
            'total_points_awarded' => (int)$result->total_points,
            'cached_at' => date('Y-m-d H:i:s')
        ];
    }

    $cacheKey = 'flagged_products_company_stats';
    $cacheData = json_encode($companyStats);

    $sql = "INSERT INTO smart_cron_cache (cache_key, cache_data, expires_at)
            VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 2 HOUR))
            ON DUPLICATE KEY UPDATE
                cache_data = VALUES(cache_data),
                expires_at = VALUES(expires_at),
                updated_at = NOW()";

    sql_query_update_or_insert_safe($sql, [$cacheKey, $cacheData]);
    $cached++;

    // Calculate execution time
    $executionTime = microtime(true) - $executionStart;

    CISLogger::info('flagged_products_cron', "Store stats cache refresh completed: {$cached} caches updated");

    // Log task completion with metrics
    Logger::cronTaskCompleted('refresh_store_stats', true, [
        'outlets_processed' => count($outlets),
        'caches_updated' => $cached,
        'periods_per_outlet' => 4,
        'company_stats' => $companyStats,
        'execution_time_seconds' => round($executionTime, 2)
    ]);

    echo json_encode([
        'success' => true,
        'caches_updated' => $cached,
        'outlets_processed' => count($outlets),
        'company_stats' => $companyStats,
        'execution_time' => round($executionTime, 2)
    ]);

} catch (Exception $e) {
    // Calculate execution time for failure case
    $executionTime = microtime(true) - $executionStart;

    CISLogger::error('flagged_products_cron', 'Store stats cache refresh failed: ' . $e->getMessage());

    // Log task failure
    Logger::cronTaskCompleted('refresh_store_stats', false, [
        'caches_updated' => $cached ?? 0,
        'execution_time_seconds' => round($executionTime, 2)
    ], $e->getMessage());

    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
