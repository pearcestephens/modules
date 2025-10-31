#!/usr/bin/env php
<?php
/**
 * Deputy Timesheet Sync - Cron Job
 *
 * Syncs approved amendments back to Deputy every hour
 *
 * Crontab entry:
 * 0 * * * * /usr/bin/php /home/master/applications/jcepnzzkmj/public_html/modules/human_resources/payroll/cron/sync_deputy.php >> /home/master/applications/jcepnzzkmj/logs/deputy_sync.log 2>&1
 *
 * @package PayrollModule\Cron
 * @version 1.0.0
 */

declare(strict_types=1);

// Bootstrap application
require_once __DIR__ . '/../../../../private_html/app.php';

use PayrollModule\Services\AmendmentService;
use PayrollModule\Services\DeputyService;
use PayrollModule\Lib\PayrollLogger;

// Start timing
$startTime = microtime(true);
$timestamp = date('Y-m-d H:i:s');

echo "\n=== Deputy Timesheet Sync ===\n";
echo "Started: {$timestamp}\n";
echo "PID: " . getmypid() . "\n\n";

try {
    // Initialize services
    $amendmentService = new AmendmentService();
    $pdo = $amendmentService->getConnection();
    $deputyService = new DeputyService($pdo);

    // Get amendments that need Deputy sync (approved but not synced)
    $sql = "SELECT
                ta.*,
                ps.deputy_user_id,
                ps.first_name,
                ps.last_name
            FROM payroll_timesheet_amendments ta
            JOIN payroll_staff ps ON ta.staff_id = ps.id
            WHERE ta.status = 'approved'
            AND ta.deputy_synced = 0
            AND ta.deputy_timesheet_id IS NOT NULL
            ORDER BY ta.approved_at ASC
            LIMIT 50";

    $stmt = $pdo->query($sql);
    $amendments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "Found " . count($amendments) . " amendments to sync\n\n";

    $synced = 0;
    $failed = 0;

    foreach ($amendments as $amendment) {
        echo "Syncing amendment #{$amendment['id']} for {$amendment['first_name']} {$amendment['last_name']}...\n";

        try {
            // Parse timestamps
            $newStartTs = strtotime($amendment['new_start_time']);
            $newEndTs = strtotime($amendment['new_end_time']);

            // Update Deputy timesheet
            $result = $deputyService->updateTimesheet(
                (int)$amendment['deputy_timesheet_id'],
                $newStartTs,
                $newEndTs,
                (int)$amendment['new_break_minutes']
            );

            if ($result['success']) {
                // Mark as synced
                $updateSql = "UPDATE payroll_timesheet_amendments
                              SET deputy_synced = 1, deputy_synced_at = NOW()
                              WHERE id = ?";
                $stmt = $pdo->prepare($updateSql);
                $stmt->execute([$amendment['id']]);

                echo "  ✓ Synced successfully\n";
                $synced++;
            } else {
                echo "  ✗ Failed: {$result['error']}\n";
                $failed++;
            }

        } catch (\Exception $e) {
            echo "  ✗ Error: {$e->getMessage()}\n";
            $failed++;
        }

        // Small delay to avoid rate limiting
        usleep(250000); // 250ms
    }

    echo "\n=== Summary ===\n";
    echo "Synced: {$synced}\n";
    echo "Failed: {$failed}\n";

    $duration = round(microtime(true) - $startTime, 2);
    echo "Completed in {$duration}s\n";

    exit($failed > 0 ? 1 : 0);

} catch (\Throwable $e) {
    echo "\n*** ERROR ***\n";
    echo "Message: {$e->getMessage()}\n";
    echo "File: {$e->getFile()}:{$e->getLine()}\n";
    echo "\nStack Trace:\n{$e->getTraceAsString()}\n";

    exit(1);
}
