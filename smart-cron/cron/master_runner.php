#!/usr/bin/env php
<?php
/**
 * Smart Cron Master Runner
 *
 * Ultra-robust cron execution engine with failsafes, monitoring, and recovery.
 * Add to crontab: * * * * * /usr/bin/php /path/to/master_runner.php >> /var/log/smart-cron/master.log 2>&1
 *
 * Features:
 * - Process isolation and timeout enforcement
 * - Automatic retry on failure
 * - Complete execution logging
 * - Resource monitoring
 * - Deadlock detection
 * - Alert generation
 * - Health checks
 *
 * @version 2.0
 * @author Ecigdis Limited
 */

// Strict error handling
declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// Constants
define('SMART_CRON_START_TIME', microtime(true));
define('SMART_CRON_ROOT', dirname(__DIR__));
define('SMART_CRON_LOG_DIR', '/var/log/smart-cron');
define('SMART_CRON_LOCK_DIR', '/var/run/smart-cron');
define('SMART_CRON_BACKUP_DIR', '/var/backups/smart-cron');
define('SMART_CRON_MAX_EXECUTION_TIME', 55); // Seconds (leave buffer for 1-min cron)
define('SMART_CRON_MAX_CONCURRENT', 10);
define('SMART_CRON_DEADLOCK_TIMEOUT', 600); // 10 minutes

// Bootstrap
require_once SMART_CRON_ROOT . '/../config/database.php';
require_once SMART_CRON_ROOT . '/includes/SmartCronRunner.php';
require_once SMART_CRON_ROOT . '/includes/SmartCronLogger.php';
require_once SMART_CRON_ROOT . '/includes/SmartCronAlert.php';
require_once SMART_CRON_ROOT . '/includes/SmartCronHealth.php';

// Ensure directories exist
foreach ([SMART_CRON_LOG_DIR, SMART_CRON_LOCK_DIR, SMART_CRON_BACKUP_DIR] as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0750, true);
    }
}

// Set log file
$logFile = SMART_CRON_LOG_DIR . '/master-' . date('Y-m-d') . '.log';
ini_set('error_log', $logFile);

/**
 * Main execution function
 */
function main(): int
{
    $runner = null;
    $logger = null;

    try {
        // Initialize
        $logger = new SmartCronLogger($logFile);
        $logger->info('=== Smart Cron Master Runner Started ===', [
            'pid' => getmypid(),
            'user' => get_current_user(),
            'memory_limit' => ini_get('memory_limit'),
            'hostname' => gethostname()
        ]);

        // Check if already running (prevent overlaps)
        $lockFile = SMART_CRON_LOCK_DIR . '/master.lock';
        if (!acquireLock($lockFile, $logger)) {
            $logger->warning('Another master runner is already executing, skipping...');
            return 0;
        }

        // Database connection
        $db = getDatabaseConnection();
        if (!$db) {
            $logger->error('Failed to connect to database');
            return 1;
        }

        // Check system health
        $health = new SmartCronHealth($db, $logger);
        if (!$health->isSystemHealthy()) {
            $logger->error('System health check failed', $health->getIssues());
            sendHealthAlert($db, $health->getIssues());
            return 1;
        }

        // Initialize runner
        $runner = new SmartCronRunner($db, $logger);

        // Clean stale locks (tasks stuck in "running" state)
        $runner->cleanStaleLocks();

        // Get due tasks
        $tasks = $runner->getDueTasks();
        $logger->info(sprintf('Found %d task(s) due for execution', count($tasks)));

        if (empty($tasks)) {
            $logger->debug('No tasks due, exiting gracefully');
            releaseLock($lockFile);
            return 0;
        }

        // Execute tasks (respecting priority and concurrency limits)
        $results = $runner->executeTasks($tasks);

        // Log summary
        $summary = [
            'total' => count($results),
            'success' => count(array_filter($results, fn($r) => $r['success'])),
            'failed' => count(array_filter($results, fn($r) => !$r['success'])),
            'execution_time' => round(microtime(true) - SMART_CRON_START_TIME, 3)
        ];

        $logger->info('Execution summary', $summary);

        // Check for failures and generate alerts
        foreach ($results as $result) {
            if (!$result['success']) {
                handleFailure($db, $result, $logger);
            }
        }

        // Update system metrics
        updateMetrics($db, $summary);

        // Release lock
        releaseLock($lockFile);

        $logger->info('=== Smart Cron Master Runner Completed Successfully ===');
        return 0;

    } catch (Throwable $e) {
        if ($logger) {
            $logger->error('Fatal error in master runner', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
        } else {
            error_log('CRITICAL: ' . $e->getMessage());
        }

        // Try to send critical alert
        try {
            $db = getDatabaseConnection();
            if ($db) {
                $alert = new SmartCronAlert($db);
                $alert->createAlert([
                    'type' => 'system_critical',
                    'severity' => 'critical',
                    'title' => 'Smart Cron Master Runner Crashed',
                    'message' => $e->getMessage(),
                    'data' => [
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                        'trace' => $e->getTraceAsString()
                    ]
                ]);
            }
        } catch (Throwable $alertError) {
            error_log('Failed to send critical alert: ' . $alertError->getMessage());
        }

        return 1;
    } finally {
        // Cleanup
        if ($runner) {
            $runner->cleanup();
        }
    }
}

/**
 * Acquire lock file with timeout
 */
function acquireLock(string $lockFile, SmartCronLogger $logger): bool
{
    if (file_exists($lockFile)) {
        $lockAge = time() - filemtime($lockFile);

        // If lock is older than max execution time, it's stale
        if ($lockAge > SMART_CRON_MAX_EXECUTION_TIME) {
            $logger->warning('Stale lock detected, removing...', ['age' => $lockAge]);
            unlink($lockFile);
        } else {
            return false;
        }
    }

    $lockData = [
        'pid' => getmypid(),
        'started_at' => date('Y-m-d H:i:s'),
        'hostname' => gethostname()
    ];

    return file_put_contents($lockFile, json_encode($lockData)) !== false;
}

/**
 * Release lock file
 */
function releaseLock(string $lockFile): void
{
    if (file_exists($lockFile)) {
        unlink($lockFile);
    }
}

/**
 * Get database connection with retry
 */
function getDatabaseConnection(): ?mysqli
{
    $maxRetries = 3;
    $retryDelay = 1;

    for ($i = 0; $i < $maxRetries; $i++) {
        try {
            $db = new mysqli(
                DB_HOST,
                DB_USER,
                DB_PASS,
                DB_NAME,
                DB_PORT ?? 3306
            );

            if ($db->connect_error) {
                throw new Exception($db->connect_error);
            }

            $db->set_charset('utf8mb4');
            return $db;

        } catch (Throwable $e) {
            error_log(sprintf('Database connection attempt %d failed: %s', $i + 1, $e->getMessage()));

            if ($i < $maxRetries - 1) {
                sleep($retryDelay);
                $retryDelay *= 2; // Exponential backoff
            }
        }
    }

    return null;
}

/**
 * Handle task failure
 */
function handleFailure(mysqli $db, array $result, SmartCronLogger $logger): void
{
    $taskId = $result['task_id'];
    $taskName = $result['task_name'];

    // Get task config
    $stmt = $db->prepare("SELECT consecutive_failures, failure_threshold, alert_on_failure, alert_email FROM smart_cron_tasks_config WHERE id = ?");
    $stmt->bind_param('i', $taskId);
    $stmt->execute();
    $task = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$task) {
        return;
    }

    // Check if alert should be sent
    if ($task['alert_on_failure'] && $task['consecutive_failures'] >= $task['failure_threshold']) {
        $alert = new SmartCronAlert($db);
        $alert->createAlert([
            'type' => 'task_failure',
            'severity' => 'error',
            'task_id' => $taskId,
            'task_name' => $taskName,
            'execution_id' => $result['execution_id'] ?? null,
            'title' => sprintf('Task "%s" has failed %d consecutive times', $taskName, $task['consecutive_failures']),
            'message' => $result['error_message'] ?? 'Unknown error',
            'data' => [
                'exit_code' => $result['exit_code'] ?? null,
                'error_type' => $result['error_type'] ?? null,
                'stderr' => $result['stderr'] ?? null
            ]
        ]);

        // Send notification if email configured
        if (!empty($task['alert_email'])) {
            $alert->sendEmailNotification($task['alert_email']);
        }

        $logger->warning('Alert created for failed task', [
            'task_name' => $taskName,
            'consecutive_failures' => $task['consecutive_failures']
        ]);
    }
}

/**
 * Send system health alert
 */
function sendHealthAlert(mysqli $db, array $issues): void
{
    $alert = new SmartCronAlert($db);
    $alert->createAlert([
        'type' => 'system_health',
        'severity' => 'critical',
        'title' => 'Smart Cron System Health Check Failed',
        'message' => sprintf('Detected %d critical issue(s)', count($issues)),
        'data' => ['issues' => $issues]
    ]);
}

/**
 * Update system metrics
 */
function updateMetrics(mysqli $db, array $summary): void
{
    $metrics = [
        ['name' => 'tasks_executed', 'value' => $summary['total'], 'unit' => 'count'],
        ['name' => 'tasks_succeeded', 'value' => $summary['success'], 'unit' => 'count'],
        ['name' => 'tasks_failed', 'value' => $summary['failed'], 'unit' => 'count'],
        ['name' => 'runner_execution_time', 'value' => $summary['execution_time'], 'unit' => 'seconds'],
        ['name' => 'memory_peak', 'value' => memory_get_peak_usage(true) / 1024 / 1024, 'unit' => 'MB']
    ];

    $stmt = $db->prepare("INSERT INTO smart_cron_metrics (metric_name, metric_value, metric_unit, recorded_at) VALUES (?, ?, ?, NOW())");

    foreach ($metrics as $metric) {
        $stmt->bind_param('sds', $metric['name'], $metric['value'], $metric['unit']);
        $stmt->execute();
    }

    $stmt->close();
}

// ============================================================================
// EXECUTE
// ============================================================================

$exitCode = main();
exit($exitCode);
