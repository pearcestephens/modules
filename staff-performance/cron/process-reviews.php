#!/usr/bin/env php
<?php
/**
 * Cron Job: Process Google Reviews
 *
 * Run every 6 hours to award bonuses for new reviews
 *
 * @package CIS\Modules\StaffPerformance\Cron
 * @version 1.0.0
 */

// Load bootstrap
require_once dirname(__DIR__) . '/bootstrap.php';

// Disable notification system temporarily
$config['gamification']['notification_enabled'] = false;

try {
    $service = new StaffPerformance\Services\GoogleReviewsGamification($db, $config['gamification']);
    $result = $service->processReviews();

    echo "[" . date('Y-m-d H:i:s') . "] Process Reviews Completed\n";
    echo "Processed: " . ($result['processed'] ?? 0) . " reviews\n";
    echo "Bonuses: $" . number_format($result['bonuses'] ?? 0, 2) . "\n";

    // Log to file
    $logFile = __DIR__ . '/../logs/process-reviews.log';
    file_put_contents(
        $logFile,
        date('Y-m-d H:i:s') . " | Processed: " . ($result['processed'] ?? 0) . " | Bonuses: $" . number_format($result['bonuses'] ?? 0, 2) . "\n",
        FILE_APPEND
    );

} catch (Exception $e) {
    echo "[ERROR] " . $e->getMessage() . "\n";
    error_log("Process Reviews Cron Error: " . $e->getMessage());
    exit(1);
}

exit(0);
