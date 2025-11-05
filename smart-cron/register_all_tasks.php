#!/usr/bin/env php
<?php
/**
 * Smart Cron - Register ALL System Tasks
 *
 * This script registers ALL cron tasks from across the entire CIS system
 * into the new Smart Cron Dashboard. Run once after deployment.
 *
 * Usage: php register_all_tasks.php
 *
 * @package SmartCron
 * @version 2.0.0
 */

declare(strict_types=1);

// Bootstrap
$baseDir = dirname(__DIR__, 2);
require_once $baseDir . '/private_html/app.php';

echo "\n╔══════════════════════════════════════════════════════════════╗\n";
echo "║          SMART CRON - COMPLETE TASK REGISTRATION            ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n\n";

$startTime = microtime(true);
$registered = 0;
$updated = 0;
$errors = [];

try {
    // Get database connection
    $db = get_db_connection();

    // ================================================================
    // DEFINE ALL SYSTEM CRON TASKS
    // ================================================================

    $allTasks = [

        // ============================================================
        // FLAGGED PRODUCTS MODULE (5 tasks)
        // ============================================================
        [
            'task_name' => 'flagged_products_generate_daily',
            'task_description' => 'Generate 20 smart-selected products per outlet per day',
            'task_script' => '/modules/flagged_products/cron/generate_daily_products.php',
            'schedule_pattern' => '5 7 * * *',  // Daily at 7:05 AM
            'priority' => 1,
            'timeout_seconds' => 600,
            'enabled' => 1,
            'category' => 'flagged_products',
            'notify_on_failure' => 1
        ],
        [
            'task_name' => 'flagged_products_refresh_leaderboard',
            'task_description' => 'Refresh leaderboard rankings and cache',
            'task_script' => '/modules/flagged_products/cron/refresh_leaderboard.php',
            'schedule_pattern' => '0 2 * * *',  // Daily at 2:00 AM
            'priority' => 3,
            'timeout_seconds' => 300,
            'enabled' => 1,
            'category' => 'flagged_products',
            'notify_on_failure' => 1
        ],
        [
            'task_name' => 'flagged_products_generate_ai_insights',
            'task_description' => 'Generate AI insights using ChatGPT',
            'task_script' => '/modules/flagged_products/cron/generate_ai_insights.php',
            'schedule_pattern' => '0 * * * *',  // Every hour
            'priority' => 4,
            'timeout_seconds' => 600,
            'enabled' => 1,
            'category' => 'flagged_products',
            'notify_on_failure' => 0
        ],
        [
            'task_name' => 'flagged_products_check_achievements',
            'task_description' => 'Check and award achievements/badges',
            'task_script' => '/modules/flagged_products/cron/check_achievements.php',
            'schedule_pattern' => '0 */6 * * *',  // Every 6 hours
            'priority' => 3,
            'timeout_seconds' => 300,
            'enabled' => 1,
            'category' => 'flagged_products',
            'notify_on_failure' => 0
        ],
        [
            'task_name' => 'flagged_products_refresh_store_stats',
            'task_description' => 'Cache store statistics for dashboards',
            'task_script' => '/modules/flagged_products/cron/refresh_store_stats.php',
            'schedule_pattern' => '*/30 * * * *',  // Every 30 minutes
            'priority' => 2,
            'timeout_seconds' => 180,
            'enabled' => 1,
            'category' => 'flagged_products',
            'notify_on_failure' => 0
        ],

        // ============================================================
        // PAYROLL MODULE (4 tasks)
        // ============================================================
        [
            'task_name' => 'payroll_sync_deputy',
            'task_description' => 'Sync approved amendments back to Deputy',
            'task_script' => '/modules/human_resources/payroll/cron/sync_deputy.php',
            'schedule_pattern' => '0 * * * *',  // Every hour
            'priority' => 2,
            'timeout_seconds' => 300,
            'enabled' => 1,
            'category' => 'payroll',
            'notify_on_failure' => 1
        ],
        [
            'task_name' => 'payroll_process_automated_reviews',
            'task_description' => 'Process pending AI reviews every 5 minutes',
            'task_script' => '/modules/human_resources/payroll/cron/process_automated_reviews.php',
            'schedule_pattern' => '*/5 * * * *',  // Every 5 minutes
            'priority' => 2,
            'timeout_seconds' => 240,
            'enabled' => 1,
            'category' => 'payroll',
            'notify_on_failure' => 1
        ],
        [
            'task_name' => 'payroll_update_dashboard',
            'task_description' => 'Update payroll dashboard statistics',
            'task_script' => '/modules/human_resources/payroll/cron/update_dashboard.php',
            'schedule_pattern' => '*/15 * * * *',  // Every 15 minutes
            'priority' => 3,
            'timeout_seconds' => 180,
            'enabled' => 1,
            'category' => 'payroll',
            'notify_on_failure' => 0
        ],
        [
            'task_name' => 'payroll_auto_start',
            'task_description' => 'Automatically start payroll periods on Monday',
            'task_script' => '/modules/human_resources/payroll/cron/payroll_auto_start.php',
            'schedule_pattern' => '0 6 * * 1',  // Monday at 6:00 AM
            'priority' => 1,
            'timeout_seconds' => 300,
            'enabled' => 1,
            'category' => 'payroll',
            'notify_on_failure' => 1
        ],

        // ============================================================
        // CONSIGNMENTS MODULE (2 tasks)
        // ============================================================
        [
            'task_name' => 'consignments_process_pending',
            'task_description' => 'Process pending consignments and transfers',
            'task_script' => '/modules/consignments/cron/process_pending.php',
            'schedule_pattern' => '*/10 * * * *',  // Every 10 minutes
            'priority' => 2,
            'timeout_seconds' => 300,
            'enabled' => 1,
            'category' => 'consignments',
            'notify_on_failure' => 1
        ],
        [
            'task_name' => 'consignments_update_analytics',
            'task_description' => 'Update consignment analytics and statistics',
            'task_script' => '/modules/consignments/cron/update_analytics.php',
            'schedule_pattern' => '0 3 * * *',  // Daily at 3:00 AM
            'priority' => 3,
            'timeout_seconds' => 600,
            'enabled' => 1,
            'category' => 'consignments',
            'notify_on_failure' => 0
        ],

        // ============================================================
        // BANK TRANSACTIONS MODULE (2 tasks)
        // ============================================================
        [
            'task_name' => 'bank_fetch_transactions',
            'task_description' => 'Fetch latest bank transactions from Xero',
            'task_script' => '/modules/bank-transactions/cron/fetch_transactions.php',
            'schedule_pattern' => '0 */4 * * *',  // Every 4 hours
            'priority' => 2,
            'timeout_seconds' => 300,
            'enabled' => 1,
            'category' => 'banking',
            'notify_on_failure' => 1
        ],
        [
            'task_name' => 'bank_auto_categorize',
            'task_description' => 'Auto-categorize transactions using AI',
            'task_script' => '/modules/bank-transactions/cron/auto_categorize.php',
            'schedule_pattern' => '30 */4 * * *',  // Every 4 hours (offset)
            'priority' => 3,
            'timeout_seconds' => 240,
            'enabled' => 1,
            'category' => 'banking',
            'notify_on_failure' => 0
        ],

        // ============================================================
        // STAFF ACCOUNTS MODULE (2 tasks)
        // ============================================================
        [
            'task_name' => 'staff_process_pending_payments',
            'task_description' => 'Process pending staff account payments',
            'task_script' => '/modules/staff-accounts/cron/process_payments.php',
            'schedule_pattern' => '0 8-18 * * *',  // Every hour from 8am-6pm
            'priority' => 1,
            'timeout_seconds' => 300,
            'enabled' => 1,
            'category' => 'staff_accounts',
            'notify_on_failure' => 1
        ],
        [
            'task_name' => 'staff_send_reminders',
            'task_description' => 'Send payment reminder notifications',
            'task_script' => '/modules/staff-accounts/cron/send_reminders.php',
            'schedule_pattern' => '0 9 * * *',  // Daily at 9:00 AM
            'priority' => 3,
            'timeout_seconds' => 180,
            'enabled' => 1,
            'category' => 'staff_accounts',
            'notify_on_failure' => 0
        ],

        // ============================================================
        // SYSTEM MAINTENANCE TASKS (4 tasks)
        // ============================================================
        [
            'task_name' => 'system_database_backup',
            'task_description' => 'Automated database backup',
            'task_script' => '/modules/db/cron/backup_database.php',
            'schedule_pattern' => '0 1 * * *',  // Daily at 1:00 AM
            'priority' => 1,
            'timeout_seconds' => 900,
            'enabled' => 1,
            'category' => 'system',
            'notify_on_failure' => 1
        ],
        [
            'task_name' => 'system_log_rotation',
            'task_description' => 'Rotate and compress old log files',
            'task_script' => '/modules/tools/cron/rotate_logs.php',
            'schedule_pattern' => '0 0 * * 0',  // Weekly on Sunday at midnight
            'priority' => 3,
            'timeout_seconds' => 300,
            'enabled' => 1,
            'category' => 'system',
            'notify_on_failure' => 0
        ],
        [
            'task_name' => 'system_cache_cleanup',
            'task_description' => 'Clean expired cache entries',
            'task_script' => '/modules/base/cron/cleanup_cache.php',
            'schedule_pattern' => '0 4 * * *',  // Daily at 4:00 AM
            'priority' => 3,
            'timeout_seconds' => 180,
            'enabled' => 1,
            'category' => 'system',
            'notify_on_failure' => 0
        ],
        [
            'task_name' => 'system_session_cleanup',
            'task_description' => 'Clean expired sessions',
            'task_script' => '/modules/base/cron/cleanup_sessions.php',
            'schedule_pattern' => '0 5 * * *',  // Daily at 5:00 AM
            'priority' => 3,
            'timeout_seconds' => 120,
            'enabled' => 1,
            'category' => 'system',
            'notify_on_failure' => 0
        ],

        // ============================================================
        // VEND/LIGHTSPEED SYNC TASKS (3 tasks)
        // ============================================================
        [
            'task_name' => 'vend_sync_products',
            'task_description' => 'Sync product data from Vend/Lightspeed',
            'task_script' => '/modules/consignments/cron/sync_vend_products.php',
            'schedule_pattern' => '0 */2 * * *',  // Every 2 hours
            'priority' => 2,
            'timeout_seconds' => 600,
            'enabled' => 1,
            'category' => 'vend_sync',
            'notify_on_failure' => 1
        ],
        [
            'task_name' => 'vend_sync_inventory',
            'task_description' => 'Sync inventory levels from Vend',
            'task_script' => '/modules/consignments/cron/sync_vend_inventory.php',
            'schedule_pattern' => '*/30 * * * *',  // Every 30 minutes
            'priority' => 2,
            'timeout_seconds' => 300,
            'enabled' => 1,
            'category' => 'vend_sync',
            'notify_on_failure' => 1
        ],
        [
            'task_name' => 'vend_sync_sales',
            'task_description' => 'Sync sales data from Vend for analytics',
            'task_script' => '/modules/consignments/cron/sync_vend_sales.php',
            'schedule_pattern' => '15 * * * *',  // Every hour at 15 past
            'priority' => 3,
            'timeout_seconds' => 300,
            'enabled' => 1,
            'category' => 'vend_sync',
            'notify_on_failure' => 0
        ],

        // ============================================================
        // MONITORING & REPORTING (3 tasks)
        // ============================================================
        [
            'task_name' => 'monitoring_daily_report',
            'task_description' => 'Generate and email daily system report',
            'task_script' => '/modules/tools/cron/daily_report.php',
            'schedule_pattern' => '0 7 * * *',  // Daily at 7:00 AM
            'priority' => 3,
            'timeout_seconds' => 300,
            'enabled' => 1,
            'category' => 'monitoring',
            'notify_on_failure' => 1
        ],
        [
            'task_name' => 'monitoring_check_disk_space',
            'task_description' => 'Check disk space and alert if low',
            'task_script' => '/modules/tools/cron/check_disk_space.php',
            'schedule_pattern' => '0 */6 * * *',  // Every 6 hours
            'priority' => 2,
            'timeout_seconds' => 60,
            'enabled' => 1,
            'category' => 'monitoring',
            'notify_on_failure' => 1
        ],
        [
            'task_name' => 'monitoring_error_summary',
            'task_description' => 'Compile error log summary and notify',
            'task_script' => '/modules/tools/cron/error_summary.php',
            'schedule_pattern' => '0 18 * * *',  // Daily at 6:00 PM
            'priority' => 3,
            'timeout_seconds' => 180,
            'enabled' => 1,
            'category' => 'monitoring',
            'notify_on_failure' => 0
        ],
    ];

    echo "Total tasks to register: " . count($allTasks) . "\n\n";

    // ================================================================
    // REGISTER EACH TASK
    // ================================================================

    foreach ($allTasks as $i => $task) {
        $num = $i + 1;
        echo "[$num/" . count($allTasks) . "] Processing: {$task['task_name']}...";

        try {
            // Check if task exists
            $stmt = $db->prepare("SELECT id, enabled FROM smart_cron_tasks_config WHERE task_name = ?");
            $stmt->execute([$task['task_name']]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($existing) {
                // Update existing task
                $stmt = $db->prepare("
                    UPDATE smart_cron_tasks_config
                    SET task_description = ?,
                        task_script = ?,
                        schedule_pattern = ?,
                        priority = ?,
                        timeout_seconds = ?,
                        enabled = ?,
                        category = ?,
                        notify_on_failure = ?,
                        updated_at = NOW()
                    WHERE task_name = ?
                ");

                $stmt->execute([
                    $task['task_description'],
                    $task['task_script'],
                    $task['schedule_pattern'],
                    $task['priority'],
                    $task['timeout_seconds'],
                    $task['enabled'],
                    $task['category'] ?? 'general',
                    $task['notify_on_failure'] ?? 0,
                    $task['task_name']
                ]);

                echo " ✓ UPDATED\n";
                $updated++;

            } else {
                // Insert new task
                $stmt = $db->prepare("
                    INSERT INTO smart_cron_tasks_config
                    (task_name, task_description, task_script, schedule_pattern,
                     priority, timeout_seconds, enabled, category, notify_on_failure, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");

                $stmt->execute([
                    $task['task_name'],
                    $task['task_description'],
                    $task['task_script'],
                    $task['schedule_pattern'],
                    $task['priority'],
                    $task['timeout_seconds'],
                    $task['enabled'],
                    $task['category'] ?? 'general',
                    $task['notify_on_failure'] ?? 0
                ]);

                echo " ✓ REGISTERED\n";
                $registered++;
            }

        } catch (Exception $e) {
            echo " ✗ ERROR: " . $e->getMessage() . "\n";
            $errors[] = [
                'task' => $task['task_name'],
                'error' => $e->getMessage()
            ];
        }
    }

    $duration = round(microtime(true) - $startTime, 2);

    // ================================================================
    // SUMMARY REPORT
    // ================================================================

    echo "\n" . str_repeat("=", 64) . "\n";
    echo "REGISTRATION COMPLETE\n";
    echo str_repeat("=", 64) . "\n\n";

    echo "✓ New Tasks Registered: {$registered}\n";
    echo "✓ Existing Tasks Updated: {$updated}\n";
    echo "✓ Total Tasks: " . ($registered + $updated) . "\n";

    if (count($errors) > 0) {
        echo "✗ Errors: " . count($errors) . "\n";
        echo "\nError Details:\n";
        foreach ($errors as $error) {
            echo "  • {$error['task']}: {$error['error']}\n";
        }
    }

    echo "\nCompleted in {$duration}s\n";

    // ================================================================
    // CATEGORY BREAKDOWN
    // ================================================================

    echo "\n" . str_repeat("-", 64) . "\n";
    echo "TASKS BY CATEGORY\n";
    echo str_repeat("-", 64) . "\n\n";

    $stmt = $db->query("
        SELECT
            COALESCE(category, 'general') as category,
            COUNT(*) as task_count,
            SUM(CASE WHEN enabled = 1 THEN 1 ELSE 0 END) as enabled_count
        FROM smart_cron_tasks_config
        GROUP BY category
        ORDER BY task_count DESC
    ");

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $cat = ucfirst($row['category']);
        echo sprintf("%-20s : %2d tasks (%d enabled)\n",
            $cat, $row['task_count'], $row['enabled_count']);
    }

    // ================================================================
    // NEXT STEPS
    // ================================================================

    echo "\n" . str_repeat("=", 64) . "\n";
    echo "NEXT STEPS\n";
    echo str_repeat("=", 64) . "\n\n";

    echo "1. ✓ Tasks registered in database\n";
    echo "2. → Ensure master_runner cron is active:\n";
    echo "     * * * * * php " . dirname(__DIR__) . "/smart-cron/cron/master_runner.php\n\n";
    echo "3. → View dashboard:\n";
    echo "     https://staff.vapeshed.co.nz/modules/smart-cron/dashboard/\n\n";
    echo "4. → Check logs:\n";
    echo "     tail -f /var/log/smart-cron/master.log\n\n";

    echo "═══════════════════════════════════════════════════════════════\n";
    echo "                    REGISTRATION SUCCESSFUL                     \n";
    echo "═══════════════════════════════════════════════════════════════\n\n";

    exit(0);

} catch (Exception $e) {
    echo "\n✗ FATAL ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n\n";
    exit(1);
}
