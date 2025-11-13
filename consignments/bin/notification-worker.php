#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * Email Notification Queue Worker
 *
 * Background process that continuously processes email queue.
 * Designed to run via cron every 1-5 minutes depending on workload.
 *
 * Usage:
 *   php bin/notification-worker.php [options]
 *
 * Options:
 *   --priority=N    Process specific priority (1=urgent, 2=high, 3=normal, 4=low)
 *   --limit=N       Maximum emails to process (default: auto based on priority)
 *   --retry         Process retry queue instead of normal queue
 *   --stats         Display queue statistics and exit
 *   --dlq           Display dead letter queue and exit
 *   --verbose       Show detailed processing output
 *   --help          Show help message
 *
 * See --help for cron setup examples
 *
 * @package CIS\Consignments
 * @version 1.0.0
 * @author CIS Development Team
 * @created 2025-11-08
 */

// Ensure running from CLI
if (php_sapi_name() !== 'cli') {
    die("This script can only be run from the command line.\n");
}

// Bootstrap
require_once __DIR__ . '/../bootstrap.php';

use CIS\Services\Consignments\Support\NotificationService;

// Parse command line options
$options = getopt('', [
    'priority:',
    'limit:',
    'retry',
    'stats',
    'dlq',
    'verbose',
    'help'
]);

// Show help
if (isset($options['help'])) {
    showHelp();
    exit(0);
}

// Initialize
$startTime = microtime(true);
$verbose = isset($options['verbose']);

try {
    $service = NotificationService::make();

    // Show statistics
    if (isset($options['stats'])) {
        showStatistics($service);
        exit(0);
    }

    // Show dead letter queue
    if (isset($options['dlq'])) {
        showDeadLetterQueue($service);
        exit(0);
    }

    // Process retry queue
    if (isset($options['retry'])) {
        log_message("Processing retry queue...", $verbose);
        $stats = $service->processRetries();
        logResults($stats, $verbose);
        exit(0);
    }

    // Process specific priority
    if (isset($options['priority'])) {
        $priority = (int)$options['priority'];

        if ($priority < 1 || $priority > 4) {
            error_log("ERROR: Invalid priority. Must be 1-4.");
            exit(1);
        }

        $limit = isset($options['limit']) ? (int)$options['limit'] : null;

        log_message("Processing priority {$priority} queue...", $verbose);
        $stats = $service->processQueue($priority, $limit ?? getBatchSizeForPriority($priority));
        logResults($stats, $verbose);
        exit(0);
    }

    // Default: process urgent and high priority
    log_message("Processing urgent and high priority queues...", $verbose);

    // Process urgent first
    log_message("→ Processing URGENT (priority 1)...", $verbose);
    $urgentStats = $service->processUrgent();
    logResults($urgentStats, $verbose);

    // Then high priority
    log_message("→ Processing HIGH (priority 2)...", $verbose);
    $highStats = $service->processHigh();
    logResults($highStats, $verbose);

    // Combined stats
    $totalStats = [
        'processed' => $urgentStats['processed'] + $highStats['processed'],
        'sent' => $urgentStats['sent'] + $highStats['sent'],
        'failed' => $urgentStats['failed'] + $highStats['failed'],
        'retried' => $urgentStats['retried'] + $highStats['retried'],
        'dlq' => $urgentStats['dlq'] + $highStats['dlq'],
        'duration' => round(microtime(true) - $startTime, 3)
    ];

    log_message("\n=== TOTAL STATS ===", $verbose);
    logResults($totalStats, $verbose);

    exit(0);

} catch (Exception $e) {
    error_log("FATAL ERROR: " . $e->getMessage());
    error_log($e->getTraceAsString());
    exit(1);
}

// ============================================================================
// HELPER FUNCTIONS
// ============================================================================

/**
 * Get batch size for priority level
 *
 * @param int $priority Priority level
 * @return int Batch size
 */
function getBatchSizeForPriority(int $priority): int
{
    switch ($priority) {
        case 1:
            return 50;
        case 2:
            return 100;
        case 3:
            return 200;
        case 4:
            return 500;
        default:
            return 100;
    }
}

/**
 * Log message to stdout and syslog
 *
 * @param string $message Message to log
 * @param bool $verbose Show on stdout
 */
function log_message(string $message, bool $verbose = false): void
{
    $timestamp = date('Y-m-d H:i:s');
    $logLine = "[{$timestamp}] {$message}";

    // Always log to syslog/error_log
    error_log($logLine);

    // Show on stdout if verbose
    if ($verbose) {
        echo $logLine . "\n";
    }
}

/**
 * Log processing results
 *
 * @param array $stats Processing statistics
 * @param bool $verbose Show on stdout
 */
function logResults(array $stats, bool $verbose = false): void
{
    $message = sprintf(
        "Processed: %d | Sent: %d | Failed: %d | Retried: %d | DLQ: %d | Duration: %.3fs",
        $stats['processed'],
        $stats['sent'],
        $stats['failed'],
        $stats['retried'] ?? 0,
        $stats['dlq'] ?? 0,
        $stats['duration'] ?? 0
    );

    log_message($message, $verbose);
}

/**
 * Show queue statistics
 *
 * @param NotificationService $service Notification service
 */
function showStatistics(NotificationService $service): void
{
    echo "\n=== EMAIL QUEUE STATISTICS ===\n\n";

    $stats = $service->getQueueStats();

    if (empty($stats)) {
        echo "Queue is empty.\n\n";
        return;
    }

    // Group by status
    $byStatus = [];
    foreach ($stats as $row) {
        $status = $row['status'];
        if (!isset($byStatus[$status])) {
            $byStatus[$status] = [];
        }
        $byStatus[$status][] = $row;
    }

    // Display each status group
    foreach ($byStatus as $status => $rows) {
        echo strtoupper($status) . ":\n";
        echo str_repeat('-', 60) . "\n";

        foreach ($rows as $row) {
            $priority = match((int)$row['priority']) {
                1 => 'URGENT',
                2 => 'HIGH',
                3 => 'NORMAL',
                4 => 'LOW',
                default => 'UNKNOWN'
            };

            echo sprintf(
                "  Priority: %-8s | Count: %4d | Oldest: %s\n",
                $priority,
                $row['count'],
                $row['oldest']
            );
        }

        echo "\n";
    }
}

/**
 * Show dead letter queue
 *
 * @param NotificationService $service Notification service
 */
function showDeadLetterQueue(NotificationService $service): void
{
    echo "\n=== DEAD LETTER QUEUE (Failed after max retries) ===\n\n";

    $dlq = $service->getDeadLetterQueue(50);

    if (empty($dlq)) {
        echo "DLQ is empty.\n\n";
        return;
    }

    echo "Total items: " . count($dlq) . "\n\n";

    foreach ($dlq as $item) {
        echo "ID: {$item['id']} | PO: {$item['consignment_id']} | Template: {$item['template_key']}\n";
        echo "  To: {$item['recipient_email']}\n";
        echo "  Subject: {$item['subject']}\n";
        echo "  Retries: {$item['retry_count']}\n";
        echo "  Last Error: {$item['last_error']}\n";
        echo "  Failed: {$item['processed_at']}\n";
        echo str_repeat('-', 80) . "\n";
    }

    echo "\nTo retry an item: php bin/notification-worker.php --retry-dlq=<ID>\n\n";
}

/**
 * Show help message
 */
function showHelp(): void
{
    echo <<<HELP

Email Notification Queue Worker
================================

Processes queued email notifications with priority-based processing and retry logic.

USAGE:
  php bin/notification-worker.php [options]

OPTIONS:
  --priority=N    Process specific priority level
                  1 = Urgent (immediate)
                  2 = High (every 5 min)
                  3 = Normal (every 30 min)
                  4 = Low (daily)

  --limit=N       Maximum emails to process in this run
                  Default: auto-determined by priority

  --retry         Process retry queue (failed emails due for retry)

  --stats         Display queue statistics and exit

  --dlq           Display dead letter queue (max retries exceeded)

  --verbose       Show detailed processing output

  --help          Show this help message

EXAMPLES:
  # Process urgent emails
  php bin/notification-worker.php --priority=1 --verbose

  # Process high priority with limit
  php bin/notification-worker.php --priority=2 --limit=50

  # Process retries
  php bin/notification-worker.php --retry

  # Show queue stats
  php bin/notification-worker.php --stats

  # Default (urgent + high priority)
  php bin/notification-worker.php

CRON SETUP:
  # Urgent (every 1 min)
  * * * * * /usr/bin/php /path/to/bin/notification-worker.php --priority=1

  # High priority (every 5 min)
  */5 * * * * /usr/bin/php /path/to/bin/notification-worker.php --priority=2

  # Normal priority (every 30 min)
  */30 * * * * /usr/bin/php /path/to/bin/notification-worker.php --priority=3

  # Low priority (daily at 2am)
  0 2 * * * /usr/bin/php /path/to/bin/notification-worker.php --priority=4

  # Retry failed (every 15 min)
  */15 * * * * /usr/bin/php /path/to/bin/notification-worker.php --retry


HELP;
}
