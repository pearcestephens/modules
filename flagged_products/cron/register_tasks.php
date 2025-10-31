<?php
/**
 * Smart-Cron Task Registration
 * 
 * Registers all flagged products cron tasks with the Smart-Cron system
 * Run this once to set up automated tasks
 * 
 * @package CIS\FlaggedProducts\Cron
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/app.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/assets/services/CISLogger.php';

try {
    CISLogger::info('flagged_products_cron', 'Registering Smart-Cron tasks');
    
    // Define cron tasks
    $tasks = [
        [
            'task_name' => 'flagged_products_generate_daily_products',
            'task_description' => 'Generate 20 smart-selected products per outlet per day',
            'task_script' => '/modules/flagged_products/cron/generate_daily_products.php',
            'schedule_pattern' => '5 7 * * *', // Daily at 7:05 AM
            'priority' => 1,
            'timeout_seconds' => 600,
            'enabled' => 1
        ],
        [
            'task_name' => 'flagged_products_refresh_leaderboard',
            'task_description' => 'Refresh leaderboard rankings and cache',
            'task_script' => '/modules/flagged_products/cron/refresh_leaderboard.php',
            'schedule_pattern' => '0 2 * * *', // Daily at 2 AM
            'priority' => 3,
            'timeout_seconds' => 300,
            'enabled' => 1
        ],
        [
            'task_name' => 'flagged_products_generate_ai_insights',
            'task_description' => 'Generate AI insights using ChatGPT',
            'task_script' => '/modules/flagged_products/cron/generate_ai_insights.php',
            'schedule_pattern' => '0 * * * *', // Every hour
            'priority' => 4,
            'timeout_seconds' => 600,
            'enabled' => 1
        ],
        [
            'task_name' => 'flagged_products_check_achievements',
            'task_description' => 'Check and award achievements/badges',
            'task_script' => '/modules/flagged_products/cron/check_achievements.php',
            'schedule_pattern' => '0 */6 * * *', // Every 6 hours
            'priority' => 3,
            'timeout_seconds' => 300,
            'enabled' => 1
        ],
        [
            'task_name' => 'flagged_products_refresh_store_stats',
            'task_description' => 'Cache store statistics for dashboards',
            'task_script' => '/modules/flagged_products/cron/refresh_store_stats.php',
            'schedule_pattern' => '*/30 * * * *', // Every 30 minutes
            'priority' => 2,
            'timeout_seconds' => 180,
            'enabled' => 1
        ]
    ];
    
    $registered = 0;
    $updated = 0;
    
    foreach ($tasks as $task) {
        // Check if task already exists
        $checkSql = "SELECT id FROM smart_cron_tasks_config WHERE task_name = ?";
        $existing = sql_query_single_row_safe($checkSql, [$task['task_name']]);
        
        if ($existing) {
            // Update existing task
            $updateSql = "UPDATE smart_cron_tasks_config 
                          SET task_description = ?,
                              task_script = ?,
                              schedule_pattern = ?,
                              priority = ?,
                              timeout_seconds = ?,
                              enabled = ?,
                              updated_at = NOW()
                          WHERE task_name = ?";
            
            sql_query_update_or_insert_safe($updateSql, [
                $task['task_description'],
                $task['task_script'],
                $task['schedule_pattern'],
                $task['priority'],
                $task['timeout_seconds'],
                $task['enabled'],
                $task['task_name']
            ]);
            
            $updated++;
            CISLogger::info('flagged_products_cron', "Updated task: {$task['task_name']}");
        } else {
            // Insert new task
            $insertSql = "INSERT INTO smart_cron_tasks_config 
                          (task_name, task_description, task_script, schedule_pattern, priority, timeout_seconds, enabled, created_at)
                          VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
            
            sql_query_update_or_insert_safe($insertSql, [
                $task['task_name'],
                $task['task_description'],
                $task['task_script'],
                $task['schedule_pattern'],
                $task['priority'],
                $task['timeout_seconds'],
                $task['enabled']
            ]);
            
            $registered++;
            CISLogger::info('flagged_products_cron', "Registered new task: {$task['task_name']}");
        }
    }
    
    CISLogger::info('flagged_products_cron', "Smart-Cron task registration completed: {$registered} new, {$updated} updated");
    
    echo json_encode([
        'success' => true,
        'tasks_registered' => $registered,
        'tasks_updated' => $updated,
        'total_tasks' => count($tasks)
    ]);
    
    // Display summary
    echo "\n\n=== Smart-Cron Tasks Registered ===\n\n";
    foreach ($tasks as $task) {
        echo "✅ {$task['task_name']}\n";
        echo "   Schedule: {$task['schedule_pattern']}\n";
        echo "   Script: {$task['task_script']}\n";
        echo "   Priority: {$task['priority']}\n\n";
    }
    
} catch (Exception $e) {
    CISLogger::error('flagged_products_cron', 'Smart-Cron task registration failed: ' . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
