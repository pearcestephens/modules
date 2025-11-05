#!/usr/bin/env php
<?php
/**
 * Smart Cron Health Monitor
 *
 * Runs every 5 minutes to check system health and generate alerts.
 * Add to crontab: */5 * * * * /usr/bin/php /path/to/health_monitor.php >> /var/log/smart-cron/health.log 2>&1
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
require_once SMART_CRON_ROOT . '/includes/SmartCronHealth.php';
require_once SMART_CRON_ROOT . '/includes/SmartCronAlert.php';

$logFile = '/var/log/smart-cron/health-' . date('Y-m-d') . '.log';
$logger = new SmartCronLogger($logFile);

$logger->info('=== Health Monitor Started ===');

try {
    // Connect to database
    $db = getDatabaseConnection();

    if (!$db) {
        $logger->critical('Failed to connect to database');
        exit(1);
    }

    // Run health checks
    $health = new SmartCronHealth($db, $logger);
    $isHealthy = $health->isSystemHealthy();
    $issues = $health->getIssues();

    if ($isHealthy) {
        $logger->info('System health check PASSED', [
            'status' => 'healthy',
            'issues_count' => 0
        ]);
    } else {
        $logger->error('System health check FAILED', [
            'status' => 'unhealthy',
            'issues_count' => count($issues),
            'issues' => $issues
        ]);

        // Create alert for critical issues
        $criticalIssues = array_filter($issues, fn($i) => $i['severity'] === 'critical');

        if (!empty($criticalIssues)) {
            $alert = new SmartCronAlert($db, $logger);
            $alert->createAlert([
                'type' => 'system_health',
                'severity' => 'critical',
                'task_id' => null,
                'task_name' => null,
                'execution_id' => null,
                'title' => 'Critical System Health Issues Detected',
                'message' => sprintf('Found %d critical issue(s) requiring immediate attention', count($criticalIssues)),
                'data' => ['issues' => $criticalIssues]
            ]);

            $logger->warning('Critical health alert created', [
                'critical_issues' => count($criticalIssues)
            ]);
        }
    }

    // Get system status summary
    $status = $health->getSystemStatus();
    $logger->info('System status', [
        'enabled_tasks' => $status['enabled_tasks'] ?? 0,
        'running_tasks' => $status['running_tasks'] ?? 0,
        'failing_tasks' => $status['failing_tasks'] ?? 0,
        'executions_last_hour' => $status['executions_last_hour'] ?? 0,
        'failures_last_hour' => $status['failures_last_hour'] ?? 0,
        'critical_alerts' => $status['critical_alerts'] ?? 0
    ]);

    // Check for long-running tasks
    $longRunning = $db->query("
        SELECT task_name, TIMESTAMPDIFF(MINUTE, last_run_at, NOW()) as minutes_running
        FROM smart_cron_tasks_config
        WHERE is_running = 1
          AND last_run_at < DATE_SUB(NOW(), INTERVAL 30 MINUTE)
    ");

    if ($longRunning && $longRunning->num_rows > 0) {
        while ($row = $longRunning->fetch_assoc()) {
            $logger->warning('Long-running task detected', [
                'task_name' => $row['task_name'],
                'minutes_running' => $row['minutes_running']
            ]);
        }
    }

    // Check disk space specifically for logs
    $logDir = '/var/log/smart-cron';
    if (is_dir($logDir)) {
        $totalSize = 0;
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($logDir));

        foreach ($files as $file) {
            if ($file->isFile()) {
                $totalSize += $file->getSize();
            }
        }

        $sizeMB = round($totalSize / 1024 / 1024, 2);
        $logger->info('Log directory size', ['size_mb' => $sizeMB]);

        if ($sizeMB > 1000) { // Warning if logs > 1GB
            $logger->warning('Log directory size exceeds 1GB', ['size_mb' => $sizeMB]);
        }
    }

    $logger->info('=== Health Monitor Completed ===');
    $db->close();
    exit(0);

} catch (Throwable $e) {
    $logger->critical('Health monitor failed with exception', [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
    exit(1);
}
