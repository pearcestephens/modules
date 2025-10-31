#!/usr/bin/env php
<?php
/**
 * Payroll Automation Worker - Cron Job
 *
 * Processes pending AI reviews every 5 minutes
 *
 * Crontab entry:
 * Run every 5 minutes: /usr/bin/php /home/master/applications/jcepnzzkmj/public_html/modules/human_resources/payroll/cron/process_automated_reviews.php >> /home/master/applications/jcepnzzkmj/logs/payroll_automation.log 2>&1
 *
 * @package PayrollModule\Cron
 * @version 1.0.0
 */

declare(strict_types=1);

// Bootstrap application
require_once __DIR__ . '/../../../../private_html/app.php';

use PayrollModule\Services\PayrollAutomationService;
use PayrollModule\Lib\PayrollLogger;

// Start timing
$startTime = microtime(true);
$timestamp = date('Y-m-d H:i:s');

echo "\n=== Payroll Automation Worker ===\n";
echo "Started: {$timestamp}\n";
echo "PID: " . getmypid() . "\n\n";

try {
    // Initialize service
    $automationService = new PayrollAutomationService();

    // Process pending reviews
    echo "Processing automated reviews...\n";
    $result = $automationService->processAutomatedReviews();

    // Display results
    echo "\nResults:\n";
    echo "  Total Reviewed: {$result['total_reviewed']}\n";
    echo "  Auto-Approved: {$result['auto_approved']}\n";
    echo "  Manual Review: {$result['manual_review']}\n";
    echo "  Declined: {$result['declined']}\n";
    echo "  Errors: {$result['errors']}\n";

    // Calculate duration
    $duration = round(microtime(true) - $startTime, 2);
    echo "\nCompleted in {$duration}s\n";

    // Exit with success code
    exit(0);

} catch (\Throwable $e) {
    echo "\n*** ERROR ***\n";
    echo "Message: {$e->getMessage()}\n";
    echo "File: {$e->getFile()}:{$e->getLine()}\n";
    echo "\nStack Trace:\n{$e->getTraceAsString()}\n";

    // Exit with error code
    exit(1);
}
