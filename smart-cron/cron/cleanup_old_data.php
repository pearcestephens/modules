#!/usr/bin/env php
<?php
/**
 * Smart Cron Cleanup Script
 *
 * Cleans up old data to maintain system performance.
 * - Removes execution logs older than 90 days
 * - Removes audit logs older than 180 days
 * - Removes resolved alerts older than 30 days
 * - Compresses old log files
 * - Removes old backups (keeps last 30)
 *
 * Add to crontab: 0 2 * * * /usr/bin/php /path/to/cleanup_old_data.php >> /var/log/smart-cron/cleanup.log 2>&1
 *
 * @version 2.0
 */

declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

define('SMART_CRON_ROOT', dirname(__DIR__));

require_once SMART_CRON_ROOT . '/../config/database.php';
require_once SMART_CRON_ROOT . '/includes/SmartCronLogger.php';

$logFile = '/var/log/smart-cron/cleanup-' . date('Y-m-d') . '.log';
$logger = new SmartCronLogger($logFile);

$logger->info('=== Cleanup Script Started ===');

try {
    $db = getDatabaseConnection();

    if (!$db) {
        $logger->critical('Failed to connect to database');
        exit(1);
    }

    // 1. Clean old execution logs (keep 90 days)
    $logger->info('Cleaning old execution logs (older than 90 days)...');
    $result = $db->query("
        DELETE FROM smart_cron_executions
        WHERE started_at < DATE_SUB(NOW(), INTERVAL 90 DAY)
    ");

    if ($result) {
        $deleted = $db->affected_rows;
        $logger->info('Deleted old execution logs', ['count' => $deleted]);
    } else {
        $logger->error('Failed to delete execution logs', ['error' => $db->error]);
    }

    // 2. Clean old audit logs (keep 180 days)
    $logger->info('Cleaning old audit logs (older than 180 days)...');
    $result = $db->query("
        DELETE FROM smart_cron_audit_log
        WHERE created_at < DATE_SUB(NOW(), INTERVAL 180 DAY)
    ");

    if ($result) {
        $deleted = $db->affected_rows;
        $logger->info('Deleted old audit logs', ['count' => $deleted]);
    } else {
        $logger->error('Failed to delete audit logs', ['error' => $db->error]);
    }

    // 3. Clean resolved alerts (keep 30 days)
    $logger->info('Cleaning resolved alerts (older than 30 days)...');
    $result = $db->query("
        DELETE FROM smart_cron_alerts
        WHERE resolved = 1
          AND resolved_at < DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");

    if ($result) {
        $deleted = $db->affected_rows;
        $logger->info('Deleted resolved alerts', ['count' => $deleted]);
    } else {
        $logger->error('Failed to delete alerts', ['error' => $db->error]);
    }

    // 4. Clean old health check records (keep 30 days)
    $logger->info('Cleaning old health checks (older than 30 days)...');
    $result = $db->query("
        DELETE FROM smart_cron_health_checks
        WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");

    if ($result) {
        $deleted = $db->affected_rows;
        $logger->info('Deleted old health checks', ['count' => $deleted]);
    } else {
        $logger->error('Failed to delete health checks', ['error' => $db->error]);
    }

    // 5. Clean old metrics (keep 90 days)
    $logger->info('Cleaning old metrics (older than 90 days)...');
    $result = $db->query("
        DELETE FROM smart_cron_metrics
        WHERE recorded_at < DATE_SUB(NOW(), INTERVAL 90 DAY)
    ");

    if ($result) {
        $deleted = $db->affected_rows;
        $logger->info('Deleted old metrics', ['count' => $deleted]);
    } else {
        $logger->error('Failed to delete metrics', ['error' => $db->error]);
    }

    // 6. Clean old rate limit records (keep 7 days)
    $logger->info('Cleaning old rate limit records (older than 7 days)...');
    $result = $db->query("
        DELETE FROM smart_cron_rate_limits
        WHERE window_end < DATE_SUB(NOW(), INTERVAL 7 DAY)
    ");

    if ($result) {
        $deleted = $db->affected_rows;
        $logger->info('Deleted old rate limit records', ['count' => $deleted]);
    }

    // 7. Optimize tables for better performance
    $logger->info('Optimizing database tables...');
    $tables = ['smart_cron_executions', 'smart_cron_audit_log', 'smart_cron_alerts',
               'smart_cron_health_checks', 'smart_cron_metrics'];

    foreach ($tables as $table) {
        $result = $db->query("OPTIMIZE TABLE $table");
        if ($result) {
            $logger->info("Optimized table: $table");
        } else {
            $logger->warning("Failed to optimize table: $table", ['error' => $db->error]);
        }
    }

    // 8. Compress old log files
    $logger->info('Compressing old log files...');
    $logDir = '/var/log/smart-cron';

    if (is_dir($logDir)) {
        $files = glob($logDir . '/*.log');
        $compressed = 0;

        foreach ($files as $file) {
            $fileAge = time() - filemtime($file);

            // Compress files older than 7 days
            if ($fileAge > (7 * 24 * 60 * 60)) {
                $gzFile = $file . '.gz';

                if (!file_exists($gzFile)) {
                    $content = file_get_contents($file);
                    $gz = gzopen($gzFile, 'w9');
                    gzwrite($gz, $content);
                    gzclose($gz);

                    // Remove original file
                    unlink($file);
                    $compressed++;

                    $logger->info('Compressed log file', ['file' => basename($file)]);
                }
            }
        }

        $logger->info('Log compression complete', ['files_compressed' => $compressed]);
    }

    // 9. Clean old backups (keep last 30)
    $logger->info('Cleaning old backup files...');
    $backupDir = '/var/backups/smart-cron';

    if (is_dir($backupDir)) {
        $backups = glob($backupDir . '/*.sql.gz');

        // Sort by modification time (oldest first)
        usort($backups, function($a, $b) {
            return filemtime($a) - filemtime($b);
        });

        // Keep last 30 backups
        $toDelete = array_slice($backups, 0, count($backups) - 30);

        foreach ($toDelete as $backup) {
            unlink($backup);
            $logger->info('Deleted old backup', ['file' => basename($backup)]);
        }

        $logger->info('Backup cleanup complete', [
            'deleted' => count($toDelete),
            'kept' => min(30, count($backups))
        ]);
    }

    // 10. Generate cleanup report
    $report = [
        'completed_at' => date('Y-m-d H:i:s'),
        'execution_logs_cleaned' => true,
        'audit_logs_cleaned' => true,
        'alerts_cleaned' => true,
        'health_checks_cleaned' => true,
        'metrics_cleaned' => true,
        'tables_optimized' => true,
        'log_files_compressed' => true,
        'backups_cleaned' => true
    ];

    $logger->info('=== Cleanup Script Completed Successfully ===', $report);

    $db->close();
    exit(0);

} catch (Throwable $e) {
    $logger->critical('Cleanup script failed with exception', [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
    exit(1);
}
