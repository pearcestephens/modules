<?php
/**
 * Central Crawler Logger
 *
 * Universal logging system for ALL crawlers in the CIS ecosystem
 * - News Aggregator
 * - Competitive Intelligence Crawler
 * - Transfer Engine
 * - Any future crawlers
 *
 * @package CIS\Crawlers
 * @version 1.0.0
 */

namespace CIS\Crawlers;

class CentralLogger {

    private $db;
    private $config;
    private $sessionId;
    private $crawlerType;

    // Log levels
    const DEBUG = 'debug';
    const INFO = 'info';
    const WARNING = 'warning';
    const ERROR = 'error';
    const CRITICAL = 'critical';

    // Crawler types
    const TYPE_NEWS = 'news_aggregator';
    const TYPE_COMPETITIVE = 'competitive_intel';
    const TYPE_TRANSFER = 'transfer_engine';
    const TYPE_STEALTH = 'stealth_crawler';

    public function __construct($db, $crawlerType, $config = []) {
        $this->db = $db;
        $this->crawlerType = $crawlerType;
        $this->sessionId = $this->generateSessionId();

        $this->config = array_merge([
            'enable_db_logging' => true,
            'enable_file_logging' => true,
            'log_file_path' => '/var/log/cis-crawlers/',
            'max_log_size' => 10485760, // 10MB
            'retention_days' => 30,
            'alert_on_error' => true,
            'alert_email' => 'pearce.stephens@ecigdis.co.nz',
        ], $config);

        $this->ensureLogDirectory();
        $this->initializeSession();
    }

    /**
     * Log a message with context
     */
    public function log($level, $message, $context = []) {
        $timestamp = date('Y-m-d H:i:s');

        $logEntry = [
            'session_id' => $this->sessionId,
            'crawler_type' => $this->crawlerType,
            'level' => $level,
            'message' => $message,
            'context' => json_encode($context),
            'timestamp' => $timestamp,
            'memory_usage' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true),
        ];

        // Database logging
        if ($this->config['enable_db_logging']) {
            $this->logToDatabase($logEntry);
        }

        // File logging (fallback + redundancy)
        if ($this->config['enable_file_logging']) {
            $this->logToFile($logEntry);
        }

        // Alert on errors
        if (in_array($level, [self::ERROR, self::CRITICAL]) && $this->config['alert_on_error']) {
            $this->sendAlert($logEntry);
        }

        return $logEntry;
    }

    /**
     * Convenience methods
     */
    public function debug($message, $context = []) {
        return $this->log(self::DEBUG, $message, $context);
    }

    public function info($message, $context = []) {
        return $this->log(self::INFO, $message, $context);
    }

    public function warning($message, $context = []) {
        return $this->log(self::WARNING, $message, $context);
    }

    public function error($message, $context = []) {
        return $this->log(self::ERROR, $message, $context);
    }

    public function critical($message, $context = []) {
        return $this->log(self::CRITICAL, $message, $context);
    }

    /**
     * Start a timed operation
     */
    public function startTimer($operationName) {
        return [
            'operation' => $operationName,
            'start_time' => microtime(true),
            'start_memory' => memory_get_usage(true),
        ];
    }

    /**
     * End a timed operation and log results
     */
    public function endTimer($timer, $success = true, $additionalContext = []) {
        $endTime = microtime(true);
        $endMemory = memory_get_usage(true);

        $duration = round(($endTime - $timer['start_time']) * 1000, 2); // ms
        $memoryDelta = $endMemory - $timer['start_memory'];

        $context = array_merge([
            'operation' => $timer['operation'],
            'duration_ms' => $duration,
            'memory_delta' => $memoryDelta,
            'memory_delta_mb' => round($memoryDelta / 1048576, 2),
            'success' => $success,
        ], $additionalContext);

        $level = $success ? self::INFO : self::ERROR;
        $status = $success ? 'completed' : 'failed';

        return $this->log($level, "Operation '{$timer['operation']}' $status in {$duration}ms", $context);
    }

    /**
     * Log crawler session summary
     */
    public function logSessionSummary($summary) {
        $this->info('Crawler session completed', array_merge([
            'session_id' => $this->sessionId,
            'crawler_type' => $this->crawlerType,
        ], $summary));

        // Update session record
        $this->updateSession($summary);
    }

    /**
     * Database logging
     */
    private function logToDatabase($entry) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO crawler_logs (
                    session_id, crawler_type, level, message, context,
                    timestamp, memory_usage, peak_memory
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $entry['session_id'],
                $entry['crawler_type'],
                $entry['level'],
                $entry['message'],
                $entry['context'],
                $entry['timestamp'],
                $entry['memory_usage'],
                $entry['peak_memory'],
            ]);

            return true;
        } catch (\PDOException $e) {
            // Fallback to file only
            error_log("CentralLogger DB error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * File logging (fallback + redundancy)
     */
    private function logToFile($entry) {
        $logFile = $this->config['log_file_path'] . $this->crawlerType . '_' . date('Y-m-d') . '.log';

        // Rotate if too large
        if (file_exists($logFile) && filesize($logFile) > $this->config['max_log_size']) {
            rename($logFile, $logFile . '.' . time() . '.old');
        }

        $logLine = sprintf(
            "[%s] [%s] %s: %s | Context: %s | Memory: %s MB\n",
            $entry['timestamp'],
            strtoupper($entry['level']),
            $entry['session_id'],
            $entry['message'],
            $entry['context'],
            round($entry['memory_usage'] / 1048576, 2)
        );

        file_put_contents($logFile, $logLine, FILE_APPEND | LOCK_EX);
    }

    /**
     * Initialize crawler session
     */
    private function initializeSession() {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO crawler_sessions (
                    session_id, crawler_type, started_at, status
                ) VALUES (?, ?, NOW(), 'running')
            ");

            $stmt->execute([$this->sessionId, $this->crawlerType]);
        } catch (\PDOException $e) {
            $this->logToFile(['timestamp' => date('Y-m-d H:i:s'), 'level' => 'error',
                'message' => 'Failed to initialize session', 'context' => json_encode(['error' => $e->getMessage()]),
                'session_id' => $this->sessionId, 'memory_usage' => 0, 'peak_memory' => 0]);
        }
    }

    /**
     * Update session with summary
     */
    private function updateSession($summary) {
        try {
            $stmt = $this->db->prepare("
                UPDATE crawler_sessions
                SET completed_at = NOW(),
                    status = ?,
                    summary = ?
                WHERE session_id = ?
            ");

            $status = isset($summary['success']) && $summary['success'] ? 'completed' : 'failed';
            $stmt->execute([$status, json_encode($summary), $this->sessionId]);
        } catch (\PDOException $e) {
            error_log("Failed to update session: " . $e->getMessage());
        }
    }

    /**
     * Send alert for critical errors
     */
    private function sendAlert($entry) {
        // TODO: Integrate with your alert system (email, Slack, SMS, etc.)
        // For now, just log to error_log
        error_log("CRAWLER ALERT [{$entry['level']}]: {$entry['message']}");
    }

    /**
     * Generate unique session ID
     */
    private function generateSessionId() {
        return date('Ymd_His') . '_' . substr(md5(uniqid(rand(), true)), 0, 8);
    }

    /**
     * Ensure log directory exists
     */
    private function ensureLogDirectory() {
        if (!is_dir($this->config['log_file_path'])) {
            mkdir($this->config['log_file_path'], 0755, true);
        }
    }

    /**
     * Get session ID
     */
    public function getSessionId() {
        return $this->sessionId;
    }

    /**
     * Get recent logs for this session
     */
    public function getSessionLogs($limit = 100) {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM crawler_logs
                WHERE session_id = ?
                ORDER BY timestamp DESC
                LIMIT ?
            ");

            $stmt->execute([$this->sessionId, $limit]);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            return [];
        }
    }

    /**
     * Static method to get logs by crawler type
     */
    public static function getLogsByCrawlerType($db, $crawlerType, $limit = 100) {
        try {
            $stmt = $db->prepare("
                SELECT * FROM crawler_logs
                WHERE crawler_type = ?
                ORDER BY timestamp DESC
                LIMIT ?
            ");

            $stmt->execute([$crawlerType, $limit]);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            return [];
        }
    }

    /**
     * Static method to get recent sessions
     */
    public static function getRecentSessions($db, $crawlerType = null, $limit = 50) {
        try {
            $sql = "SELECT * FROM crawler_sessions ";
            $params = [];

            if ($crawlerType) {
                $sql .= "WHERE crawler_type = ? ";
                $params[] = $crawlerType;
            }

            $sql .= "ORDER BY started_at DESC LIMIT ?";
            $params[] = $limit;

            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            return [];
        }
    }

    /**
     * Clean up old logs
     */
    public static function cleanup($db, $retentionDays = 30) {
        try {
            // Clean old database logs
            $stmt = $db->prepare("
                DELETE FROM crawler_logs
                WHERE timestamp < DATE_SUB(NOW(), INTERVAL ? DAY)
            ");
            $stmt->execute([$retentionDays]);

            // Clean old sessions
            $stmt = $db->prepare("
                DELETE FROM crawler_sessions
                WHERE started_at < DATE_SUB(NOW(), INTERVAL ? DAY)
            ");
            $stmt->execute([$retentionDays]);

            return true;
        } catch (\PDOException $e) {
            error_log("Cleanup failed: " . $e->getMessage());
            return false;
        }
    }
}
