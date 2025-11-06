#!/usr/bin/env php
<?php
/**
 * Cron Job: Update Monthly Performance Stats
 *
 * Run daily at 1am to recalculate stats and rankings
 *
 * @package CIS\Modules\StaffPerformance\Cron
 * @version 1.0.0
 */

// Load bootstrap
require_once dirname(__DIR__) . '/bootstrap.php';

try {
    $service = new StaffPerformance\Services\StaffPerformanceTracker($db, $config['performance']);
    $result = $service->updateMonthlyStats();

    echo "[" . date('Y-m-d H:i:s') . "] Update Stats Completed\n";
    echo "Month: " . ($result['month'] ?? 'N/A') . "\n";
    echo "Updated: " . ($result['updated'] ?? 0) . " staff members\n";

    // Log to file
    $logFile = __DIR__ . '/../logs/update-stats.log';
    file_put_contents(
        $logFile,
        date('Y-m-d H:i:s') . " | Month: " . ($result['month'] ?? 'N/A') . " | Updated: " . ($result['updated'] ?? 0) . "\n",
        FILE_APPEND
    );

} catch (Exception $e) {
    echo "[ERROR] " . $e->getMessage() . "\n";
    error_log("Update Stats Cron Error: " . $e->getMessage());
    exit(1);
}

exit(0);
