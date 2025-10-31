<?php
/**
 * Smart-Cron Task: Daily Leaderboard Refresh
 * 
 * Recalculates leaderboard rankings and updates cache
 * Runs daily at 2:00 AM
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
    Logger::cronTaskStarted('refresh_leaderboard', [
        'scheduled_time' => date('Y-m-d H:i:s'),
        'periods' => ['daily', 'weekly', 'monthly', 'all_time']
    ]);
    
    CISLogger::info('flagged_products_cron', 'Starting daily leaderboard refresh');
    
    $periods = ['daily', 'weekly', 'monthly', 'all_time'];
    $stats = [];
    
    foreach ($periods as $period) {
        // Get leaderboard data
        $leaderboard = FlaggedProductsRepository::getLeaderboard($period, 100);
        
        // Cache it (using Smart-Cron's caching system)
        $cacheKey = "flagged_products_leaderboard_{$period}";
        $cacheData = json_encode($leaderboard);
        
        // Store in database cache table
        $sql = "INSERT INTO smart_cron_cache (cache_key, cache_data, expires_at)
                VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 24 HOUR))
                ON DUPLICATE KEY UPDATE 
                    cache_data = VALUES(cache_data),
                    expires_at = VALUES(expires_at),
                    updated_at = NOW()";
        
        sql_query_update_or_insert_safe($sql, [$cacheKey, $cacheData]);
        
        $stats[$period] = count($leaderboard);
    }
    
    // Get all outlets for per-store leaderboards
    $outlets = sql_query_collection_safe("SELECT id FROM vend_outlets WHERE (deleted_at IS NULL OR deleted_at = '0000-00-00 00:00:00' OR deleted_at = '')", []);
    
    foreach ($outlets as $outlet) {
        foreach ($periods as $period) {
            $leaderboard = FlaggedProductsRepository::getLeaderboard($period, 50, $outlet->id);
            
            $cacheKey = "flagged_products_leaderboard_{$period}_{$outlet->id}";
            $cacheData = json_encode($leaderboard);
            
            $sql = "INSERT INTO smart_cron_cache (cache_key, cache_data, expires_at)
                    VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 24 HOUR))
                    ON DUPLICATE KEY UPDATE 
                        cache_data = VALUES(cache_data),
                        expires_at = VALUES(expires_at),
                        updated_at = NOW()";
            
            sql_query_update_or_insert_safe($sql, [$cacheKey, $cacheData]);
        }
    }
    
    // Calculate execution time
    $executionTime = microtime(true) - $executionStart;
    
    CISLogger::info('flagged_products_cron', 'Daily leaderboard refresh completed', $stats);
    
    // Log task completion with metrics
    Logger::cronTaskCompleted('refresh_leaderboard', true, [
        'periods_refreshed' => count($periods),
        'outlets_processed' => count($outlets),
        'total_cache_entries' => count($periods) + (count($outlets) * count($periods)),
        'stats_per_period' => $stats,
        'execution_time_seconds' => round($executionTime, 2)
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Leaderboard refresh completed',
        'stats' => $stats,
        'outlets_processed' => count($outlets),
        'execution_time' => round($executionTime, 2)
    ]);
    
} catch (Exception $e) {
    // Calculate execution time for failure case
    $executionTime = microtime(true) - $executionStart;
    
    CISLogger::error('flagged_products_cron', 'Leaderboard refresh failed: ' . $e->getMessage());
    
    // Log task failure
    Logger::cronTaskCompleted('refresh_leaderboard', false, [
        'execution_time_seconds' => round($executionTime, 2)
    ], $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
