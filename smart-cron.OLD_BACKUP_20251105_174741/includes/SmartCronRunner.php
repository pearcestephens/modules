<?php
/**
 * Smart Cron Runner - Core Execution Engine
 *
 * Handles task execution with process isolation, timeout enforcement,
 * retry logic, and comprehensive logging.
 *
 * @version 2.0
 */

class SmartCronRunner
{
    private mysqli $db;
    private SmartCronLogger $logger;
    private array $runningProcesses = [];

    public function __construct(mysqli $db, SmartCronLogger $logger)
    {
        $this->db = $db;
        $this->logger = $logger;
    }

    /**
     * Get all tasks that are due for execution
     */
    public function getDueTasks(): array
    {
        $sql = "
            SELECT
                id, task_name, task_description, task_script,
                schedule_pattern, priority, timeout_seconds,
                max_retries, retry_delay_seconds,
                consecutive_failures, last_run_at
            FROM smart_cron_tasks_config
            WHERE enabled = 1
              AND is_running = 0
              AND (next_run_at IS NULL OR next_run_at <= NOW())
            ORDER BY priority ASC, last_run_at ASC
            LIMIT " . SMART_CRON_MAX_CONCURRENT;

        $result = $this->db->query($sql);

        if (!$result) {
            $this->logger->error('Failed to fetch due tasks', [
                'error' => $this->db->error
            ]);
            return [];
        }

        $tasks = [];
        while ($row = $result->fetch_assoc()) {
            // Verify script exists
            if (!file_exists($row['task_script'])) {
                $this->logger->error('Task script not found', [
                    'task_name' => $row['task_name'],
                    'script' => $row['task_script']
                ]);
                continue;
            }

            $tasks[] = $row;
        }

        return $tasks;
    }

    /**
     * Execute multiple tasks with concurrency control
     */
    public function executeTasks(array $tasks): array
    {
        $results = [];

        foreach ($tasks as $task) {
            // Check if we can run more tasks
            if (count($this->runningProcesses) >= SMART_CRON_MAX_CONCURRENT) {
                $this->logger->warning('Max concurrent tasks reached, waiting...', [
                    'running' => count($this->runningProcesses)
                ]);
                $this->waitForAnyProcess();
            }

            // Execute task
            $result = $this->executeTask($task);
            $results[] = $result;

            // If execution started in background, track it
            if ($result['started'] && !$result['completed']) {
                $this->runningProcesses[$task['id']] = $result;
            }
        }

        // Wait for all background processes to complete
        $this->waitForAllProcesses();

        return $results;
    }

    /**
     * Execute a single task with full error handling
     */
    public function executeTask(array $task): array
    {
        $taskId = $task['id'];
        $taskName = $task['task_name'];
        $startTime = microtime(true);

        $this->logger->info(sprintf('Executing task: %s', $taskName), [
            'task_id' => $taskId,
            'script' => $task['task_script'],
            'priority' => $task['priority'],
            'timeout' => $task['timeout_seconds']
        ]);

        // Mark task as running
        $this->markTaskRunning($taskId);

        $result = [
            'task_id' => $taskId,
            'task_name' => $taskName,
            'started' => true,
            'completed' => false,
            'success' => false,
            'exit_code' => null,
            'stdout' => '',
            'stderr' => '',
            'error_message' => null,
            'error_type' => null,
            'execution_time' => null,
            'memory_peak' => null,
            'execution_id' => null
        ];

        try {
            // Build command
            $command = $this->buildCommand($task['task_script'], $task['timeout_seconds']);

            // Execute with timeout and output capture
            $execResult = $this->executeCommand($command, $task['timeout_seconds']);

            $endTime = microtime(true);
            $executionTime = $endTime - $startTime;

            // Update result
            $result['completed'] = true;
            $result['success'] = $execResult['exit_code'] === 0;
            $result['exit_code'] = $execResult['exit_code'];
            $result['stdout'] = $execResult['stdout'];
            $result['stderr'] = $execResult['stderr'];
            $result['execution_time'] = $executionTime;
            $result['memory_peak'] = memory_get_peak_usage(true) / 1024 / 1024;

            // Determine error type if failed
            if (!$result['success']) {
                if ($execResult['timeout']) {
                    $result['error_type'] = 'timeout';
                    $result['error_message'] = sprintf('Task exceeded timeout of %d seconds', $task['timeout_seconds']);
                } elseif ($execResult['exit_code'] > 128) {
                    $result['error_type'] = 'signal';
                    $result['error_message'] = sprintf('Task killed by signal %d', $execResult['exit_code'] - 128);
                } else {
                    $result['error_type'] = 'execution_error';
                    $result['error_message'] = trim($execResult['stderr']) ?: 'Task returned non-zero exit code';
                }
            }

            // Log execution to database
            $executionId = $this->logExecution($task, $result, $startTime, $endTime);
            $result['execution_id'] = $executionId;

            // Update task statistics
            $this->updateTaskStats($task, $result);

            // Calculate next run time
            $this->scheduleNextRun($taskId, $task['schedule_pattern']);

            if ($result['success']) {
                $this->logger->info(sprintf('Task completed successfully: %s', $taskName), [
                    'execution_time' => round($executionTime, 3),
                    'exit_code' => $result['exit_code']
                ]);
            } else {
                $this->logger->error(sprintf('Task failed: %s', $taskName), [
                    'exit_code' => $result['exit_code'],
                    'error_type' => $result['error_type'],
                    'error_message' => $result['error_message']
                ]);
            }

        } catch (Throwable $e) {
            $result['completed'] = true;
            $result['success'] = false;
            $result['error_type'] = 'exception';
            $result['error_message'] = $e->getMessage();
            $result['stderr'] = $e->getTraceAsString();

            $this->logger->error(sprintf('Exception executing task: %s', $taskName), [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            // Still log the failure
            $this->logExecution($task, $result, $startTime, microtime(true));
            $this->updateTaskStats($task, $result);
        }

        // Mark task as no longer running
        $this->markTaskComplete($taskId);

        return $result;
    }

    /**
     * Build execution command with isolation and resource limits
     */
    private function buildCommand(string $script, int $timeout): string
    {
        // Use timeout command for hard limit
        // Use nice for lower priority
        // Capture both stdout and stderr

        $phpBin = '/usr/bin/php';

        // Add resource limits if available
        $ulimitCmd = '';
        if (function_exists('exec')) {
            // Limit memory to 512MB, CPU time to timeout + 5 seconds
            $ulimitCmd = sprintf('ulimit -v 524288 -t %d && ', $timeout + 5);
        }

        $command = sprintf(
            '%stimeout --kill-after=5s %ds nice -n 10 %s %s 2>&1',
            $ulimitCmd,
            $timeout,
            escapeshellarg($phpBin),
            escapeshellarg($script)
        );

        return $command;
    }

    /**
     * Execute command with output capture
     */
    private function executeCommand(string $command, int $timeout): array
    {
        $startTime = time();
        $descriptors = [
            0 => ['pipe', 'r'],  // stdin
            1 => ['pipe', 'w'],  // stdout
            2 => ['pipe', 'w'],  // stderr
        ];

        $process = proc_open($command, $descriptors, $pipes);

        if (!is_resource($process)) {
            return [
                'exit_code' => -1,
                'stdout' => '',
                'stderr' => 'Failed to start process',
                'timeout' => false
            ];
        }

        // Close stdin
        fclose($pipes[0]);

        // Set non-blocking mode for output streams
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $stdout = '';
        $stderr = '';
        $timedOut = false;

        // Read output with timeout monitoring
        while (true) {
            $status = proc_get_status($process);

            // Check timeout
            if (time() - $startTime > $timeout) {
                $timedOut = true;
                proc_terminate($process, SIGTERM);
                sleep(1);
                proc_terminate($process, SIGKILL);
                break;
            }

            // Read available output
            $stdout .= stream_get_contents($pipes[1]);
            $stderr .= stream_get_contents($pipes[2]);

            // Check if process finished
            if (!$status['running']) {
                // Read remaining output
                $stdout .= stream_get_contents($pipes[1]);
                $stderr .= stream_get_contents($pipes[2]);
                break;
            }

            usleep(100000); // 100ms
        }

        // Close pipes
        fclose($pipes[1]);
        fclose($pipes[2]);

        // Get exit code
        $exitCode = proc_close($process);

        if ($timedOut) {
            $exitCode = 124; // Standard timeout exit code
        }

        return [
            'exit_code' => $exitCode,
            'stdout' => $stdout,
            'stderr' => $stderr,
            'timeout' => $timedOut
        ];
    }

    /**
     * Log execution to database
     */
    private function logExecution(array $task, array $result, float $startTime, float $endTime): ?int
    {
        $stmt = $this->db->prepare("
            INSERT INTO smart_cron_executions (
                task_id, task_name, started_at, completed_at, execution_time,
                exit_code, success, stdout_output, stderr_output,
                output_size, error_message, error_type,
                memory_peak_mb, triggered_by, server_hostname, pid
            ) VALUES (?, ?, FROM_UNIXTIME(?), FROM_UNIXTIME(?), ?, ?, ?, ?, ?, ?, ?, ?, ?, 'cron', ?, ?)
        ");

        if (!$stmt) {
            $this->logger->error('Failed to prepare execution log statement', [
                'error' => $this->db->error
            ]);
            return null;
        }

        $outputSize = strlen($result['stdout']) + strlen($result['stderr']);
        $executionTime = $result['execution_time'];
        $hostname = gethostname();
        $pid = getmypid();

        $stmt->bind_param(
            'isdddiissisd ssi',
            $task['id'],
            $task['task_name'],
            $startTime,
            $endTime,
            $executionTime,
            $result['exit_code'],
            $result['success'],
            $result['stdout'],
            $result['stderr'],
            $outputSize,
            $result['error_message'],
            $result['error_type'],
            $result['memory_peak'],
            $hostname,
            $pid
        );

        $stmt->execute();
        $executionId = $stmt->insert_id;
        $stmt->close();

        return $executionId;
    }

    /**
     * Update task statistics
     */
    private function updateTaskStats(array $task, array $result): void
    {
        $stmt = $this->db->prepare("
            UPDATE smart_cron_tasks_config
            SET
                total_executions = total_executions + 1,
                total_successes = total_successes + IF(? = 1, 1, 0),
                total_failures = total_failures + IF(? = 0, 1, 0),
                consecutive_failures = IF(? = 1, 0, consecutive_failures + 1),
                last_run_at = NOW(),
                last_success_at = IF(? = 1, NOW(), last_success_at),
                last_failure_at = IF(? = 0, NOW(), last_failure_at),
                avg_execution_time = (
                    COALESCE(avg_execution_time, 0) * total_executions + ?
                ) / (total_executions + 1)
            WHERE id = ?
        ");

        $success = $result['success'] ? 1 : 0;
        $executionTime = $result['execution_time'] ?? 0;

        $stmt->bind_param('iiiiidi', $success, $success, $success, $success, $success, $executionTime, $task['id']);
        $stmt->execute();
        $stmt->close();
    }

    /**
     * Calculate and set next run time based on cron pattern
     */
    private function scheduleNextRun(int $taskId, string $pattern): void
    {
        // Parse cron pattern and calculate next run
        $nextRun = $this->calculateNextRun($pattern);

        if ($nextRun) {
            $stmt = $this->db->prepare("UPDATE smart_cron_tasks_config SET next_run_at = ? WHERE id = ?");
            $stmt->bind_param('si', $nextRun, $taskId);
            $stmt->execute();
            $stmt->close();
        }
    }

    /**
     * Calculate next run time from cron pattern
     */
    private function calculateNextRun(string $pattern): ?string
    {
        // Simple cron parser - can be enhanced with library like cron-expression
        // For now, just add 1 minute for simplicity
        return date('Y-m-d H:i:s', strtotime('+1 minute'));
    }

    /**
     * Mark task as running
     */
    private function markTaskRunning(int $taskId): void
    {
        $stmt = $this->db->prepare("UPDATE smart_cron_tasks_config SET is_running = 1 WHERE id = ?");
        $stmt->bind_param('i', $taskId);
        $stmt->execute();
        $stmt->close();
    }

    /**
     * Mark task as complete
     */
    private function markTaskComplete(int $taskId): void
    {
        $stmt = $this->db->prepare("UPDATE smart_cron_tasks_config SET is_running = 0 WHERE id = ?");
        $stmt->bind_param('i', $taskId);
        $stmt->execute();
        $stmt->close();
    }

    /**
     * Clean stale locks (tasks stuck in running state)
     */
    public function cleanStaleLocks(): void
    {
        // Find tasks marked as running but haven't updated in a while
        $sql = "
            UPDATE smart_cron_tasks_config
            SET is_running = 0, consecutive_failures = consecutive_failures + 1
            WHERE is_running = 1
              AND last_run_at < DATE_SUB(NOW(), INTERVAL " . SMART_CRON_DEADLOCK_TIMEOUT . " SECOND)
        ";

        $result = $this->db->query($sql);

        if ($result && $this->db->affected_rows > 0) {
            $this->logger->warning('Cleaned stale task locks', [
                'count' => $this->db->affected_rows
            ]);
        }
    }

    /**
     * Wait for any running process to complete
     */
    private function waitForAnyProcess(): void
    {
        // In this simplified version, we execute synchronously
        // In a production system, you'd track actual background processes
        sleep(1);
    }

    /**
     * Wait for all running processes to complete
     */
    private function waitForAllProcesses(): void
    {
        // All processes are synchronous in this implementation
        $this->runningProcesses = [];
    }

    /**
     * Cleanup resources
     */
    public function cleanup(): void
    {
        // Close database connection
        if ($this->db) {
            $this->db->close();
        }
    }
}
