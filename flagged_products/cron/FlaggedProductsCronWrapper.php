<?php
/**
 * Flagged Products Cron Wrapper
 *
 * Professional cron job wrapper with:
 * - Performance logging and metrics
 * - Smart Cron V2 integration
 * - Error handling and alerts
 * - Memory and execution time tracking
 * - Circuit breaker pattern
 * - Automatic retry logic
 *
 * @package CIS\FlaggedProducts\Cron
 * @version 2.0.0
 */

declare(strict_types=1);

class FlaggedProductsCronWrapper
{
    private string $taskName;
    private string $taskScript;
    private array $config;
    private ?PDO $db = null;
    private array $metrics = [];
    private float $startTime;
    private int $startMemory;

    // Smart Cron V2 Integration
    private ?object $minimalCron = null;
    private ?int $executionId = null;

    /**
     * Constructor
     */
    public function __construct(string $taskName, string $taskScript, array $config = [])
    {
        $this->taskName = $taskName;
        $this->taskScript = $taskScript;
        $this->config = array_merge([
            'timeout' => 600,
            'max_retries' => 3,
            'retry_delay' => 60,
            'memory_limit' => '512M',
            'enable_circuit_breaker' => true,
            'log_level' => 'INFO',
            'alert_on_failure' => true,
        ], $config);

        $this->startTime = microtime(true);
        $this->startMemory = memory_get_usage(true);

        // Set PHP limits
        ini_set('memory_limit', $this->config['memory_limit']);
        set_time_limit($this->config['timeout']);
    }

    /**
     * Initialize database connection
     */
    private function initDatabase(): void
    {
        if ($this->db) {
            return;
        }

        try {
            $this->db = new PDO(
                'mysql:host=127.0.0.1;dbname=jcepnzzkmj;charset=utf8mb4',
                'jcepnzzkmj',
                'wprKh9Jq63',
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
        } catch (PDOException $e) {
            $this->log('CRITICAL', 'Database connection failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Initialize Smart Cron V2 integration
     */
    private function initSmartCron(): void
    {
        $wrapperPath = __DIR__ . '/../../../assets/services/cron/MinimalCronWrapper.php';

        if (file_exists($wrapperPath)) {
            require_once $wrapperPath;

            if (class_exists('MinimalCronWrapper')) {
                $this->minimalCron = new MinimalCronWrapper($this->taskName);
                $this->log('INFO', 'Smart Cron V2 integration enabled');
            }
        } else {
            $this->log('WARNING', 'MinimalCronWrapper not found, continuing without Smart Cron integration');
        }
    }

    /**
     * Execute the cron task
     */
    public function execute(): bool
    {
        try {
            $this->log('INFO', "Starting task: {$this->taskName}");

            // Initialize components
            $this->initDatabase();
            $this->initSmartCron();

            // Check circuit breaker
            if ($this->config['enable_circuit_breaker'] && $this->isCircuitOpen()) {
                $this->log('WARNING', 'Circuit breaker is OPEN, skipping execution');
                return false;
            }

            // Record start in Smart Cron V2
            if ($this->minimalCron) {
                $this->executionId = $this->logExecutionStart();
            }

            // Check if task script exists
            if (!file_exists($this->taskScript)) {
                throw new Exception("Task script not found: {$this->taskScript}");
            }

            // Execute with retry logic
            $attempt = 1;
            $success = false;
            $lastError = null;

            while ($attempt <= $this->config['max_retries'] && !$success) {
                try {
                    $this->log('INFO', "Execution attempt {$attempt}/{$this->config['max_retries']}");

                    // Execute the task script
                    $result = $this->executeTaskScript();

                    if ($result['success']) {
                        $success = true;
                        $this->log('INFO', 'Task completed successfully');
                    } else {
                        throw new Exception($result['error'] ?? 'Unknown error');
                    }

                } catch (Exception $e) {
                    $lastError = $e->getMessage();
                    $this->log('ERROR', "Attempt {$attempt} failed: {$lastError}");

                    if ($attempt < $this->config['max_retries']) {
                        $this->log('INFO', "Retrying in {$this->config['retry_delay']} seconds...");
                        sleep($this->config['retry_delay']);
                    }

                    $attempt++;
                }
            }

            // Calculate metrics
            $this->calculateMetrics($success);

            // Log execution end
            if ($this->executionId) {
                $this->logExecutionEnd($success, $lastError);
            }

            // Update circuit breaker
            if ($this->config['enable_circuit_breaker']) {
                $this->updateCircuitBreaker($success);
            }

            // Send alerts if failed
            if (!$success && $this->config['alert_on_failure']) {
                $this->sendAlert($lastError);
            }

            // Log final metrics
            $this->logMetrics();

            return $success;

        } catch (Exception $e) {
            $this->log('CRITICAL', 'Fatal error: ' . $e->getMessage());
            $this->log('DEBUG', $e->getTraceAsString());

            if ($this->executionId) {
                $this->logExecutionEnd(false, $e->getMessage());
            }

            if ($this->config['alert_on_failure']) {
                $this->sendAlert($e->getMessage());
            }

            return false;
        }
    }

    /**
     * Execute the task script in isolated environment
     */
    private function executeTaskScript(): array
    {
        $output = [];
        $returnCode = 0;

        // Build command
        $command = sprintf(
            'php %s 2>&1',
            escapeshellarg($this->taskScript)
        );

        // Execute
        exec($command, $output, $returnCode);

        // Parse output
        $outputString = implode("\n", $output);

        return [
            'success' => $returnCode === 0,
            'output' => $outputString,
            'return_code' => $returnCode,
            'error' => $returnCode !== 0 ? $outputString : null
        ];
    }

    /**
     * Calculate performance metrics
     */
    private function calculateMetrics(bool $success): void
    {
        $this->metrics = [
            'task_name' => $this->taskName,
            'success' => $success,
            'execution_time' => microtime(true) - $this->startTime,
            'memory_used' => memory_get_usage(true) - $this->startMemory,
            'peak_memory' => memory_get_peak_usage(true),
            'timestamp' => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * Log execution start in Smart Cron V2
     */
    private function logExecutionStart(): ?int
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO smart_cron_execution_history
                (job_name, started_at, status)
                VALUES (?, NOW(), 'running')
            ");

            $stmt->execute([$this->taskName]);

            return (int)$this->db->lastInsertId();

        } catch (PDOException $e) {
            $this->log('ERROR', 'Failed to log execution start: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Log execution end in Smart Cron V2
     */
    private function logExecutionEnd(bool $success, ?string $error = null): void
    {
        if (!$this->executionId) {
            return;
        }

        try {
            $stmt = $this->db->prepare("
                UPDATE smart_cron_execution_history
                SET
                    completed_at = NOW(),
                    status = ?,
                    execution_time = ?,
                    memory_used = ?,
                    peak_memory = ?,
                    error_message = ?
                WHERE id = ?
            ");

            $stmt->execute([
                $success ? 'success' : 'failed',
                $this->metrics['execution_time'],
                $this->metrics['memory_used'],
                $this->metrics['peak_memory'],
                $error,
                $this->executionId
            ]);

        } catch (PDOException $e) {
            $this->log('ERROR', 'Failed to log execution end: ' . $e->getMessage());
        }
    }

    /**
     * Check if circuit breaker is open
     */
    private function isCircuitOpen(): bool
    {
        try {
            $stmt = $this->db->prepare("
                SELECT
                    COUNT(*) as failure_count
                FROM smart_cron_execution_history
                WHERE job_name = ?
                AND status = 'failed'
                AND started_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
            ");

            $stmt->execute([$this->taskName]);
            $result = $stmt->fetch();

            // Open circuit if 5+ failures in last hour
            return ($result['failure_count'] ?? 0) >= 5;

        } catch (PDOException $e) {
            $this->log('ERROR', 'Failed to check circuit breaker: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Update circuit breaker state
     */
    private function updateCircuitBreaker(bool $success): void
    {
        // Circuit breaker logic is handled by failure counting
        // This method can be extended for more sophisticated patterns
        $this->log('DEBUG', 'Circuit breaker state updated');
    }

    /**
     * Send alert on failure
     */
    private function sendAlert(string $error): void
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO smart_cron_alerts
                (job_name, alert_type, message, created_at)
                VALUES (?, 'failure', ?, NOW())
            ");

            $message = sprintf(
                "Task '%s' failed: %s\nExecution Time: %.2fs\nMemory Used: %s",
                $this->taskName,
                $error,
                $this->metrics['execution_time'],
                $this->formatBytes($this->metrics['memory_used'])
            );

            $stmt->execute([$this->taskName, $message]);

            $this->log('INFO', 'Alert sent for task failure');

        } catch (PDOException $e) {
            $this->log('ERROR', 'Failed to send alert: ' . $e->getMessage());
        }
    }

    /**
     * Log metrics to file and database
     */
    private function logMetrics(): void
    {
        // Log to file
        $logDir = __DIR__ . '/../logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        $logFile = $logDir . '/cron-metrics-' . date('Y-m-d') . '.log';
        $logEntry = sprintf(
            "[%s] %s | Success: %s | Time: %.2fs | Memory: %s | Peak: %s\n",
            $this->metrics['timestamp'],
            $this->taskName,
            $this->metrics['success'] ? 'YES' : 'NO',
            $this->metrics['execution_time'],
            $this->formatBytes($this->metrics['memory_used']),
            $this->formatBytes($this->metrics['peak_memory'])
        );

        file_put_contents($logFile, $logEntry, FILE_APPEND);

        // Log to database
        try {
            $stmt = $this->db->prepare("
                INSERT INTO flagged_products_cron_metrics
                (task_name, success, execution_time, memory_used, peak_memory, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE
                    success = VALUES(success),
                    execution_time = VALUES(execution_time),
                    memory_used = VALUES(memory_used),
                    peak_memory = VALUES(peak_memory),
                    created_at = NOW()
            ");

            $stmt->execute([
                $this->metrics['task_name'],
                $this->metrics['success'] ? 1 : 0,
                $this->metrics['execution_time'],
                $this->metrics['memory_used'],
                $this->metrics['peak_memory']
            ]);

        } catch (PDOException $e) {
            $this->log('ERROR', 'Failed to log metrics to database: ' . $e->getMessage());
        }
    }

    /**
     * Log message
     */
    private function log(string $level, string $message): void
    {
        $logDir = __DIR__ . '/../logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        $logFile = $logDir . '/cron-' . date('Y-m-d') . '.log';
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[{$timestamp}] [{$level}] [{$this->taskName}] {$message}\n";

        file_put_contents($logFile, $logEntry, FILE_APPEND);

        // Also log to Smart Cron V2 if available
        if ($this->minimalCron && method_exists($this->minimalCron, 'log')) {
            $this->minimalCron->log($level, $message);
        }
    }

    /**
     * Format bytes to human readable
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));

        return round($bytes, 2) . ' ' . $units[$pow];
    }
}
