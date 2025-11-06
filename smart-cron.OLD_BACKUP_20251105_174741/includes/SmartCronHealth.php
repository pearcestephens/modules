<?php
/**
 * Smart Cron Health Monitor
 *
 * Monitors system health and detects issues.
 *
 * @version 2.0
 */

class SmartCronHealth
{
    private mysqli $db;
    private SmartCronLogger $logger;
    private array $issues = [];

    public function __construct(mysqli $db, SmartCronLogger $logger)
    {
        $this->db = $db;
        $this->logger = $logger;
    }

    /**
     * Perform comprehensive health check
     */
    public function isSystemHealthy(): bool
    {
        $this->issues = [];
        $healthy = true;

        // Check database connectivity
        if (!$this->checkDatabase()) {
            $healthy = false;
        }

        // Check filesystem
        if (!$this->checkFilesystem()) {
            $healthy = false;
        }

        // Check disk space
        if (!$this->checkDiskSpace()) {
            $healthy = false;
        }

        // Check memory
        if (!$this->checkMemory()) {
            $healthy = false;
        }

        // Check stuck tasks
        if (!$this->checkStuckTasks()) {
            $healthy = false;
        }

        // Check critical failures
        if (!$this->checkCriticalFailures()) {
            $healthy = false;
        }

        // Log health check
        $this->logHealthCheck($healthy);

        return $healthy;
    }

    /**
     * Check database connectivity and performance
     */
    private function checkDatabase(): bool
    {
        try {
            // Test query
            $result = $this->db->query("SELECT 1");

            if (!$result) {
                $this->issues[] = [
                    'type' => 'database',
                    'severity' => 'critical',
                    'message' => 'Database query failed: ' . $this->db->error
                ];
                return false;
            }

            // Check table count
            $result = $this->db->query("
                SELECT COUNT(*) as count
                FROM information_schema.tables
                WHERE table_schema = DATABASE()
                  AND table_name LIKE 'smart_cron_%'
            ");

            $row = $result->fetch_assoc();

            if ($row['count'] < 8) {
                $this->issues[] = [
                    'type' => 'database',
                    'severity' => 'critical',
                    'message' => 'Missing Smart Cron database tables'
                ];
                return false;
            }

            return true;

        } catch (Throwable $e) {
            $this->issues[] = [
                'type' => 'database',
                'severity' => 'critical',
                'message' => 'Database error: ' . $e->getMessage()
            ];
            return false;
        }
    }

    /**
     * Check filesystem permissions and directories
     */
    private function checkFilesystem(): bool
    {
        $directories = [
            SMART_CRON_LOG_DIR => 'Log directory',
            SMART_CRON_LOCK_DIR => 'Lock directory',
            SMART_CRON_BACKUP_DIR => 'Backup directory'
        ];

        $healthy = true;

        foreach ($directories as $dir => $name) {
            if (!is_dir($dir)) {
                $this->issues[] = [
                    'type' => 'filesystem',
                    'severity' => 'error',
                    'message' => sprintf('%s does not exist: %s', $name, $dir)
                ];
                $healthy = false;
                continue;
            }

            if (!is_writable($dir)) {
                $this->issues[] = [
                    'type' => 'filesystem',
                    'severity' => 'error',
                    'message' => sprintf('%s is not writable: %s', $name, $dir)
                ];
                $healthy = false;
            }
        }

        return $healthy;
    }

    /**
     * Check disk space
     */
    private function checkDiskSpace(): bool
    {
        $path = SMART_CRON_LOG_DIR;
        $freeSpace = disk_free_space($path);
        $totalSpace = disk_total_space($path);

        if ($freeSpace === false || $totalSpace === false) {
            $this->issues[] = [
                'type' => 'disk_space',
                'severity' => 'warning',
                'message' => 'Could not determine disk space'
            ];
            return true; // Don't fail on this
        }

        $percentFree = ($freeSpace / $totalSpace) * 100;

        if ($percentFree < 5) {
            $this->issues[] = [
                'type' => 'disk_space',
                'severity' => 'critical',
                'message' => sprintf('Critically low disk space: %.2f%% free', $percentFree)
            ];
            return false;
        }

        if ($percentFree < 10) {
            $this->issues[] = [
                'type' => 'disk_space',
                'severity' => 'warning',
                'message' => sprintf('Low disk space: %.2f%% free', $percentFree)
            ];
        }

        return true;
    }

    /**
     * Check memory usage
     */
    private function checkMemory(): bool
    {
        $memoryLimit = ini_get('memory_limit');
        $memoryLimitBytes = $this->parseMemoryLimit($memoryLimit);
        $memoryUsage = memory_get_usage(true);

        if ($memoryLimitBytes > 0) {
            $percentUsed = ($memoryUsage / $memoryLimitBytes) * 100;

            if ($percentUsed > 90) {
                $this->issues[] = [
                    'type' => 'memory',
                    'severity' => 'critical',
                    'message' => sprintf('High memory usage: %.2f%%', $percentUsed)
                ];
                return false;
            }

            if ($percentUsed > 75) {
                $this->issues[] = [
                    'type' => 'memory',
                    'severity' => 'warning',
                    'message' => sprintf('Elevated memory usage: %.2f%%', $percentUsed)
                ];
            }
        }

        return true;
    }

    /**
     * Check for stuck tasks
     */
    private function checkStuckTasks(): bool
    {
        $sql = "
            SELECT id, task_name, last_run_at
            FROM smart_cron_tasks_config
            WHERE is_running = 1
              AND last_run_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)
        ";

        $result = $this->db->query($sql);

        if ($result && $result->num_rows > 0) {
            $tasks = [];
            while ($row = $result->fetch_assoc()) {
                $tasks[] = $row['task_name'];
            }

            $this->issues[] = [
                'type' => 'stuck_tasks',
                'severity' => 'error',
                'message' => sprintf('Found %d stuck task(s): %s', count($tasks), implode(', ', $tasks))
            ];

            return false;
        }

        return true;
    }

    /**
     * Check for critical failures
     */
    private function checkCriticalFailures(): bool
    {
        $sql = "
            SELECT id, task_name, consecutive_failures, failure_threshold
            FROM smart_cron_tasks_config
            WHERE enabled = 1
              AND consecutive_failures >= failure_threshold
        ";

        $result = $this->db->query($sql);

        if ($result && $result->num_rows > 0) {
            $tasks = [];
            while ($row = $result->fetch_assoc()) {
                $tasks[] = sprintf('%s (%d failures)', $row['task_name'], $row['consecutive_failures']);
            }

            $this->issues[] = [
                'type' => 'critical_failures',
                'severity' => 'error',
                'message' => sprintf('Tasks exceeding failure threshold: %s', implode(', ', $tasks))
            ];

            return false;
        }

        return true;
    }

    /**
     * Log health check to database
     */
    private function logHealthCheck(bool $healthy): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO smart_cron_health_checks (
                check_type, check_status, check_message, check_data
            ) VALUES ('system_health', ?, ?, ?)
        ");

        $status = $healthy ? 'healthy' : 'critical';
        $message = $healthy ? 'All systems operational' : sprintf('Found %d issue(s)', count($this->issues));
        $data = json_encode(['issues' => $this->issues]);

        $stmt->bind_param('sss', $status, $message, $data);
        $stmt->execute();
        $stmt->close();
    }

    /**
     * Parse memory limit string to bytes
     */
    private function parseMemoryLimit(string $limit): int
    {
        $limit = trim($limit);

        if ($limit === '-1') {
            return 0; // Unlimited
        }

        $last = strtolower($limit[strlen($limit) - 1]);
        $value = (int) $limit;

        switch ($last) {
            case 'g':
                $value *= 1024;
            case 'm':
                $value *= 1024;
            case 'k':
                $value *= 1024;
        }

        return $value;
    }

    /**
     * Get issues
     */
    public function getIssues(): array
    {
        return $this->issues;
    }

    /**
     * Get system status summary
     */
    public function getSystemStatus(): array
    {
        $sql = "SELECT * FROM smart_cron_system_status";
        $result = $this->db->query($sql);

        if (!$result) {
            return [];
        }

        return $result->fetch_assoc() ?: [];
    }
}
