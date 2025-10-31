<?php
/**
 * Cron Monitoring & Alerting System
 * 
 * Provides comprehensive monitoring, metrics tracking, and alerting
 * for flagged products cron tasks
 * 
 * Features:
 * - Execution time tracking
 * - Performance metrics
 * - Error rate monitoring
 * - Automatic alerting on failures
 * - Health check dashboard data
 * 
 * @package CIS\FlaggedProducts\Cron
 */

class CronMonitor {
    
    private $taskName;
    private $startTime;
    private $endTime;
    private $metrics = [];
    private $errors = [];
    private $warnings = [];
    
    // Alert thresholds
    private const EXECUTION_TIME_WARNING = 300; // 5 minutes
    private const EXECUTION_TIME_CRITICAL = 600; // 10 minutes
    private const ERROR_RATE_WARNING = 0.05; // 5%
    private const ERROR_RATE_CRITICAL = 0.10; // 10%
    
    // Alerting configuration
    private const ALERT_EMAIL = 'alerts@vapeshed.co.nz';
    private const ALERT_SLACK_WEBHOOK = null; // Set if using Slack
    
    /**
     * Start monitoring a cron task
     */
    public function __construct(string $taskName) {
        $this->taskName = $taskName;
        $this->startTime = microtime(true);
        
        CISLogger::info('cron_monitor', "Task '{$taskName}' started", [
            'task' => $taskName,
            'start_time' => date('Y-m-d H:i:s'),
            'pid' => getmypid()
        ]);
    }
    
    /**
     * Add a metric to track
     */
    public function addMetric(string $key, $value): void {
        $this->metrics[$key] = $value;
    }
    
    /**
     * Log an error
     */
    public function logError(string $message, array $context = []): void {
        $this->errors[] = [
            'message' => $message,
            'context' => $context,
            'timestamp' => microtime(true)
        ];
        
        CISLogger::error('cron_monitor', "Task '{$this->taskName}' error: {$message}", $context);
    }
    
    /**
     * Log a warning
     */
    public function logWarning(string $message, array $context = []): void {
        $this->warnings[] = [
            'message' => $message,
            'context' => $context,
            'timestamp' => microtime(true)
        ];
        
        CISLogger::warning('cron_monitor', "Task '{$this->taskName}' warning: {$message}", $context);
    }
    
    /**
     * Complete monitoring and save metrics
     */
    public function complete(bool $success = true, string $message = ''): void {
        $this->endTime = microtime(true);
        $executionTime = round($this->endTime - $this->startTime, 2);
        
        // Store execution record
        $this->saveExecutionRecord($success, $executionTime, $message);
        
        // Check for alerts
        $this->checkAlerts($success, $executionTime);
        
        // Log completion
        CISLogger::info('cron_monitor', "Task '{$this->taskName}' completed", [
            'task' => $this->taskName,
            'success' => $success,
            'execution_time' => $executionTime,
            'metrics' => $this->metrics,
            'errors' => count($this->errors),
            'warnings' => count($this->warnings)
        ]);
        
        // Update health dashboard
        $this->updateHealthDashboard($success, $executionTime);
    }
    
    /**
     * Save execution record to database
     */
    private function saveExecutionRecord(bool $success, float $executionTime, string $message): void {
        try {
            $sql = "INSERT INTO flagged_products_cron_executions 
                    (task_name, started_at, completed_at, execution_time, success, 
                     error_count, warning_count, metrics, message)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            sql_query_update_or_insert_safe($sql, [
                $this->taskName,
                date('Y-m-d H:i:s', (int)$this->startTime),
                date('Y-m-d H:i:s', (int)$this->endTime),
                $executionTime,
                $success ? 1 : 0,
                count($this->errors),
                count($this->warnings),
                json_encode($this->metrics),
                $message
            ]);
        } catch (Exception $e) {
            CISLogger::error('cron_monitor', 'Failed to save execution record: ' . $e->getMessage());
        }
    }
    
    /**
     * Check if alerts need to be sent
     */
    private function checkAlerts(bool $success, float $executionTime): void {
        $alerts = [];
        
        // Check for failure
        if (!$success) {
            $alerts[] = [
                'severity' => 'CRITICAL',
                'type' => 'task_failure',
                'message' => "Cron task '{$this->taskName}' FAILED",
                'details' => [
                    'errors' => $this->errors,
                    'execution_time' => $executionTime
                ]
            ];
        }
        
        // Check execution time
        if ($executionTime > self::EXECUTION_TIME_CRITICAL) {
            $alerts[] = [
                'severity' => 'CRITICAL',
                'type' => 'slow_execution',
                'message' => "Cron task '{$this->taskName}' execution time CRITICAL: {$executionTime}s (threshold: " . self::EXECUTION_TIME_CRITICAL . "s)",
                'details' => [
                    'execution_time' => $executionTime,
                    'threshold' => self::EXECUTION_TIME_CRITICAL
                ]
            ];
        } elseif ($executionTime > self::EXECUTION_TIME_WARNING) {
            $alerts[] = [
                'severity' => 'WARNING',
                'type' => 'slow_execution',
                'message' => "Cron task '{$this->taskName}' execution time WARNING: {$executionTime}s (threshold: " . self::EXECUTION_TIME_WARNING . "s)",
                'details' => [
                    'execution_time' => $executionTime,
                    'threshold' => self::EXECUTION_TIME_WARNING
                ]
            ];
        }
        
        // Check error rate over last 24 hours
        $errorRate = $this->getRecentErrorRate();
        if ($errorRate > self::ERROR_RATE_CRITICAL) {
            $alerts[] = [
                'severity' => 'CRITICAL',
                'type' => 'high_error_rate',
                'message' => "Cron task '{$this->taskName}' error rate CRITICAL: " . round($errorRate * 100, 1) . "% (threshold: " . (self::ERROR_RATE_CRITICAL * 100) . "%)",
                'details' => [
                    'error_rate' => $errorRate,
                    'threshold' => self::ERROR_RATE_CRITICAL
                ]
            ];
        } elseif ($errorRate > self::ERROR_RATE_WARNING) {
            $alerts[] = [
                'severity' => 'WARNING',
                'type' => 'high_error_rate',
                'message' => "Cron task '{$this->taskName}' error rate WARNING: " . round($errorRate * 100, 1) . "% (threshold: " . (self::ERROR_RATE_WARNING * 100) . "%)",
                'details' => [
                    'error_rate' => $errorRate,
                    'threshold' => self::ERROR_RATE_WARNING
                ]
            ];
        }
        
        // Send alerts
        foreach ($alerts as $alert) {
            $this->sendAlert($alert);
        }
    }
    
    /**
     * Get error rate for this task over last 24 hours
     */
    private function getRecentErrorRate(): float {
        try {
            $sql = "SELECT 
                        COUNT(*) as total,
                        SUM(CASE WHEN success = 0 THEN 1 ELSE 0 END) as failures
                    FROM flagged_products_cron_executions
                    WHERE task_name = ?
                    AND started_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)";
            
            $result = sql_query_single_safe($sql, [$this->taskName]);
            
            if ($result && $result->total > 0) {
                return $result->failures / $result->total;
            }
        } catch (Exception $e) {
            CISLogger::error('cron_monitor', 'Failed to calculate error rate: ' . $e->getMessage());
        }
        
        return 0.0;
    }
    
    /**
     * Send alert via configured channels
     */
    private function sendAlert(array $alert): void {
        // Log alert
        CISLogger::log($alert['severity'] === 'CRITICAL' ? 'error' : 'warning', 
            'cron_alert', 
            $alert['message'], 
            $alert['details']
        );
        
        // Store alert in database
        try {
            $sql = "INSERT INTO flagged_products_cron_alerts 
                    (task_name, severity, type, message, details, created_at)
                    VALUES (?, ?, ?, ?, ?, NOW())";
            
            sql_query_update_or_insert_safe($sql, [
                $this->taskName,
                $alert['severity'],
                $alert['type'],
                $alert['message'],
                json_encode($alert['details'])
            ]);
        } catch (Exception $e) {
            CISLogger::error('cron_monitor', 'Failed to store alert: ' . $e->getMessage());
        }
        
        // Send email alert for CRITICAL issues
        if ($alert['severity'] === 'CRITICAL' && self::ALERT_EMAIL) {
            $this->sendEmailAlert($alert);
        }
        
        // Send Slack alert if configured
        if (self::ALERT_SLACK_WEBHOOK) {
            $this->sendSlackAlert($alert);
        }
    }
    
    /**
     * Send email alert
     */
    private function sendEmailAlert(array $alert): void {
        try {
            $subject = "[CIS ALERT] {$alert['severity']}: {$this->taskName}";
            $body = "Alert Details:\n\n";
            $body .= "Task: {$this->taskName}\n";
            $body .= "Severity: {$alert['severity']}\n";
            $body .= "Type: {$alert['type']}\n";
            $body .= "Message: {$alert['message']}\n\n";
            $body .= "Details:\n" . print_r($alert['details'], true);
            $body .= "\n\nTimestamp: " . date('Y-m-d H:i:s') . "\n";
            
            mail(self::ALERT_EMAIL, $subject, $body, "From: cron@vapeshed.co.nz");
        } catch (Exception $e) {
            CISLogger::error('cron_monitor', 'Failed to send email alert: ' . $e->getMessage());
        }
    }
    
    /**
     * Send Slack alert
     */
    private function sendSlackAlert(array $alert): void {
        try {
            $color = $alert['severity'] === 'CRITICAL' ? 'danger' : 'warning';
            $emoji = $alert['severity'] === 'CRITICAL' ? '🚨' : '⚠️';
            
            $payload = [
                'text' => "{$emoji} {$alert['message']}",
                'attachments' => [
                    [
                        'color' => $color,
                        'fields' => [
                            [
                                'title' => 'Task',
                                'value' => $this->taskName,
                                'short' => true
                            ],
                            [
                                'title' => 'Type',
                                'value' => $alert['type'],
                                'short' => true
                            ],
                            [
                                'title' => 'Details',
                                'value' => json_encode($alert['details']),
                                'short' => false
                            ]
                        ],
                        'footer' => 'CIS Cron Monitor',
                        'ts' => time()
                    ]
                ]
            ];
            
            $ch = curl_init(self::ALERT_SLACK_WEBHOOK);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_exec($ch);
            curl_close($ch);
        } catch (Exception $e) {
            CISLogger::error('cron_monitor', 'Failed to send Slack alert: ' . $e->getMessage());
        }
    }
    
    /**
     * Update health dashboard metrics
     */
    private function updateHealthDashboard(bool $success, float $executionTime): void {
        try {
            // Calculate average execution time over last 24 hours
            $avgExecution = sql_query_single_value_safe(
                "SELECT AVG(execution_time) 
                 FROM flagged_products_cron_executions
                 WHERE task_name = ?
                 AND started_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)",
                [$this->taskName]
            );
            
            // Calculate success rate over last 24 hours
            $successRate = sql_query_single_safe(
                "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN success = 1 THEN 1 ELSE 0 END) as successes
                 FROM flagged_products_cron_executions
                 WHERE task_name = ?
                 AND started_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)",
                [$this->taskName]
            );
            
            $successRatePercent = $successRate && $successRate->total > 0 
                ? round(($successRate->successes / $successRate->total) * 100, 2)
                : 100;
            
            // Update health dashboard cache
            $healthData = [
                'task_name' => $this->taskName,
                'last_run' => date('Y-m-d H:i:s'),
                'last_success' => $success,
                'last_execution_time' => $executionTime,
                'avg_execution_time_24h' => round($avgExecution ?: 0, 2),
                'success_rate_24h' => $successRatePercent,
                'metrics' => $this->metrics,
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            $sql = "INSERT INTO smart_cron_cache (cache_key, cache_data, expires_at)
                    VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))
                    ON DUPLICATE KEY UPDATE 
                        cache_data = VALUES(cache_data),
                        expires_at = VALUES(expires_at),
                        updated_at = NOW()";
            
            sql_query_update_or_insert_safe($sql, [
                "cron_health_{$this->taskName}",
                json_encode($healthData)
            ]);
        } catch (Exception $e) {
            CISLogger::error('cron_monitor', 'Failed to update health dashboard: ' . $e->getMessage());
        }
    }
    
    /**
     * Get health status for a task
     */
    public static function getHealthStatus(string $taskName): ?array {
        try {
            $cacheData = sql_query_single_value_safe(
                "SELECT cache_data 
                 FROM smart_cron_cache 
                 WHERE cache_key = ?
                 AND expires_at > NOW()",
                ["cron_health_{$taskName}"]
            );
            
            return $cacheData ? json_decode($cacheData, true) : null;
        } catch (Exception $e) {
            CISLogger::error('cron_monitor', 'Failed to get health status: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get recent alerts
     */
    public static function getRecentAlerts(int $limit = 50): array {
        try {
            $sql = "SELECT * 
                    FROM flagged_products_cron_alerts
                    ORDER BY created_at DESC
                    LIMIT ?";
            
            return sql_query_collection_safe($sql, [$limit]) ?: [];
        } catch (Exception $e) {
            CISLogger::error('cron_monitor', 'Failed to get recent alerts: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get execution history
     */
    public static function getExecutionHistory(string $taskName, int $limit = 100): array {
        try {
            $sql = "SELECT * 
                    FROM flagged_products_cron_executions
                    WHERE task_name = ?
                    ORDER BY started_at DESC
                    LIMIT ?";
            
            return sql_query_collection_safe($sql, [$taskName, $limit]) ?: [];
        } catch (Exception $e) {
            CISLogger::error('cron_monitor', 'Failed to get execution history: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get performance metrics
     */
    public static function getPerformanceMetrics(string $taskName): array {
        try {
            $sql = "SELECT 
                        COUNT(*) as total_runs,
                        SUM(CASE WHEN success = 1 THEN 1 ELSE 0 END) as successful_runs,
                        AVG(execution_time) as avg_execution_time,
                        MIN(execution_time) as min_execution_time,
                        MAX(execution_time) as max_execution_time,
                        SUM(error_count) as total_errors,
                        SUM(warning_count) as total_warnings
                    FROM flagged_products_cron_executions
                    WHERE task_name = ?
                    AND started_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
            
            $result = sql_query_single_safe($sql, [$taskName]);
            
            if ($result) {
                return [
                    'total_runs' => (int)$result->total_runs,
                    'successful_runs' => (int)$result->successful_runs,
                    'failed_runs' => (int)$result->total_runs - (int)$result->successful_runs,
                    'success_rate' => $result->total_runs > 0 
                        ? round(($result->successful_runs / $result->total_runs) * 100, 2) 
                        : 0,
                    'avg_execution_time' => round($result->avg_execution_time ?: 0, 2),
                    'min_execution_time' => round($result->min_execution_time ?: 0, 2),
                    'max_execution_time' => round($result->max_execution_time ?: 0, 2),
                    'total_errors' => (int)$result->total_errors,
                    'total_warnings' => (int)$result->total_warnings
                ];
            }
        } catch (Exception $e) {
            CISLogger::error('cron_monitor', 'Failed to get performance metrics: ' . $e->getMessage());
        }
        
        return [];
    }
}
