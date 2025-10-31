#!/usr/bin/env php
<?php
/**
 * Update Dashboard Stats - Cron Job
 *
 * Updates dashboard statistics and analytics daily
 *
 * Crontab entry:
 * 0 2 * * * /usr/bin/php /home/master/applications/jcepnzzkmj/public_html/modules/human_resources/payroll/cron/update_dashboard.php >> /home/master/applications/jcepnzzkmj/logs/dashboard_stats.log 2>&1
 *
 * @package PayrollModule\Cron
 * @version 1.0.0
 */

declare(strict_types=1);

// Bootstrap application
require_once __DIR__ . '/../../../../private_html/app.php';

use PayrollModule\Services\PayrollAutomationService;

// Start timing
$startTime = microtime(true);
$timestamp = date('Y-m-d H:i:s');

echo "\n=== Dashboard Stats Update ===\n";
echo "Started: {$timestamp}\n";
echo "PID: " . getmypid() . "\n\n";

try {
    // Initialize service
    $service = new PayrollAutomationService();
    $pdo = $service->getConnection();

    // Calculate and store daily statistics
    echo "Calculating daily statistics...\n";

    // 1. Overall automation stats
    $sql = "INSERT INTO payroll_automation_daily_stats (
                stat_date,
                total_amendments,
                ai_reviewed,
                auto_approved,
                manual_review,
                declined,
                avg_confidence,
                avg_processing_seconds,
                created_at
            )
            SELECT
                CURDATE() as stat_date,
                COUNT(*) as total_amendments,
                SUM(CASE WHEN ad.id IS NOT NULL THEN 1 ELSE 0 END) as ai_reviewed,
                SUM(CASE WHEN ad.decision = 'approved' THEN 1 ELSE 0 END) as auto_approved,
                SUM(CASE WHEN ad.decision = 'manual_review' THEN 1 ELSE 0 END) as manual_review,
                SUM(CASE WHEN ad.decision = 'declined' THEN 1 ELSE 0 END) as declined,
                AVG(ad.confidence_score) as avg_confidence,
                AVG(TIMESTAMPDIFF(SECOND, ad.created_at, ad.updated_at)) as avg_processing_seconds,
                NOW() as created_at
            FROM payroll_timesheet_amendments ta
            LEFT JOIN payroll_ai_decisions ad ON ad.entity_type = 'timesheet_amendment' AND ad.entity_id = ta.id
            WHERE DATE(ta.created_at) = CURDATE()
            ON DUPLICATE KEY UPDATE
                total_amendments = VALUES(total_amendments),
                ai_reviewed = VALUES(ai_reviewed),
                auto_approved = VALUES(auto_approved),
                manual_review = VALUES(manual_review),
                declined = VALUES(declined),
                avg_confidence = VALUES(avg_confidence),
                avg_processing_seconds = VALUES(avg_processing_seconds),
                updated_at = NOW()";

    $pdo->exec($sql);
    echo "  ✓ Daily stats updated\n";

    // 2. Rule performance stats
    echo "Calculating rule performance...\n";

    $sql = "INSERT INTO payroll_rule_performance_daily (
                stat_date,
                rule_id,
                execution_count,
                passed_count,
                failed_count,
                avg_confidence_adjustment,
                created_at
            )
            SELECT
                CURDATE() as stat_date,
                re.rule_id,
                COUNT(*) as execution_count,
                SUM(CASE WHEN re.passed THEN 1 ELSE 0 END) as passed_count,
                SUM(CASE WHEN NOT re.passed THEN 1 ELSE 0 END) as failed_count,
                AVG(re.confidence_adjustment) as avg_confidence_adjustment,
                NOW() as created_at
            FROM payroll_ai_rule_executions re
            WHERE DATE(re.created_at) = CURDATE()
            GROUP BY re.rule_id
            ON DUPLICATE KEY UPDATE
                execution_count = VALUES(execution_count),
                passed_count = VALUES(passed_count),
                failed_count = VALUES(failed_count),
                avg_confidence_adjustment = VALUES(avg_confidence_adjustment),
                updated_at = NOW()";

    $pdo->exec($sql);
    echo "  ✓ Rule performance updated\n";

    // 3. Staff amendment patterns
    echo "Analyzing staff patterns...\n";

    $sql = "INSERT INTO payroll_staff_amendment_patterns (
                staff_id,
                total_amendments,
                approved_count,
                declined_count,
                avg_hours_change,
                last_amendment_date,
                updated_at
            )
            SELECT
                staff_id,
                COUNT(*) as total_amendments,
                SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_count,
                SUM(CASE WHEN status = 'declined' THEN 1 ELSE 0 END) as declined_count,
                AVG(new_hours - original_hours) as avg_hours_change,
                MAX(created_at) as last_amendment_date,
                NOW() as updated_at
            FROM payroll_timesheet_amendments
            GROUP BY staff_id
            ON DUPLICATE KEY UPDATE
                total_amendments = VALUES(total_amendments),
                approved_count = VALUES(approved_count),
                declined_count = VALUES(declined_count),
                avg_hours_change = VALUES(avg_hours_change),
                last_amendment_date = VALUES(last_amendment_date),
                updated_at = NOW()";

    $pdo->exec($sql);
    echo "  ✓ Staff patterns analyzed\n";

    // 4. Cleanup old data (keep 90 days of detailed logs)
    echo "Cleaning up old data...\n";

    $sql = "DELETE FROM payroll_ai_rule_executions
            WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)";
    $deleted = $pdo->exec($sql);
    echo "  ✓ Cleaned {$deleted} old rule executions\n";

    $sql = "DELETE FROM payroll_context_snapshots
            WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)";
    $deleted = $pdo->exec($sql);
    echo "  ✓ Cleaned {$deleted} old context snapshots\n";

    // 5. Archive old notifications
    $sql = "UPDATE payroll_notifications
            SET archived = 1
            WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)
            AND read_at IS NOT NULL";
    $archived = $pdo->exec($sql);
    echo "  ✓ Archived {$archived} old notifications\n";

    echo "\n=== Summary ===\n";
    echo "✓ All dashboard stats updated successfully\n";

    $duration = round(microtime(true) - $startTime, 2);
    echo "Completed in {$duration}s\n";

    exit(0);

} catch (\Throwable $e) {
    echo "\n*** ERROR ***\n";
    echo "Message: {$e->getMessage()}\n";
    echo "File: {$e->getFile()}:{$e->getLine()}\n";
    echo "\nStack Trace:\n{$e->getTraceAsString()}\n";

    exit(1);
}
