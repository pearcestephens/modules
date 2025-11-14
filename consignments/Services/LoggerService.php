<?php
/**
 * Consignments Module - Logger Service
 *
 * Provides PSR-3 compatible logging with environment-aware output.
 * All debug logging is conditional on APP_DEBUG setting.
 *
 * Features:
 * - Environment-aware logging (debug disabled in production)
 * - Structured logging with context
 * - File-based error logging
 * - Performance metrics
 * - Audit trail support
 *
 * @package ConsignmentsModule\Services
 */

namespace ConsignmentsModule\Services;

class LoggerService
{
    const LEVEL_DEBUG   = 'DEBUG';
    const LEVEL_INFO    = 'INFO';
    const LEVEL_WARNING = 'WARNING';
    const LEVEL_ERROR   = 'ERROR';
    const LEVEL_CRITICAL = 'CRITICAL';

    private $config;
    private $logPath;
    private $debugEnabled;
    private $logLevels = [
        'DEBUG'    => 100,
        'INFO'     => 200,
        'WARNING'  => 300,
        'ERROR'    => 400,
        'CRITICAL' => 500,
    ];

    public function __construct($config = [])
    {
        $this->config = $config;
        $this->logPath = $config['log_path'] ?? '/home/master/applications/jcepnzzkmj/public_html/modules/consignments/_logs';
        $this->debugEnabled = ($config['debug'] ?? getenv('APP_DEBUG')) === true || getenv('APP_DEBUG') === 'true';

        // Ensure log directory exists
        if (!is_dir($this->logPath)) {
            @mkdir($this->logPath, 0755, true);
        }
    }

    /**
     * Log a debug message (only in debug mode)
     */
    public function debug($message, $context = [])
    {
        if ($this->debugEnabled) {
            $this->log(self::LEVEL_DEBUG, $message, $context);
        }
    }

    /**
     * Log info message
     */
    public function info($message, $context = [])
    {
        $this->log(self::LEVEL_INFO, $message, $context);
    }

    /**
     * Log warning message
     */
    public function warning($message, $context = [])
    {
        $this->log(self::LEVEL_WARNING, $message, $context);
    }

    /**
     * Log error message
     */
    public function error($message, $context = [])
    {
        $this->log(self::LEVEL_ERROR, $message, $context);
    }

    /**
     * Log critical error
     */
    public function critical($message, $context = [])
    {
        $this->log(self::LEVEL_CRITICAL, $message, $context);
    }

    /**
     * Main logging method
     */
    private function log($level, $message, $context = [])
    {
        // Check if we should log at this level
        $currentMinLevel = $this->debugEnabled ? 100 : 300; // DEBUG=100, WARNING=300
        if ($this->logLevels[$level] < $currentMinLevel) {
            return;
        }

        // Build log entry
        $timestamp = date('Y-m-d H:i:s');
        $contextStr = !empty($context) ? ' | ' . json_encode($context) : '';
        $logEntry = "[$timestamp] [$level] $message$contextStr" . PHP_EOL;

        // Write to appropriate log file
        $logFile = $this->getLogFile($level);
        @file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);

        // Also write errors/warnings to PHP error log
        if (in_array($level, [self::LEVEL_ERROR, self::LEVEL_CRITICAL, self::LEVEL_WARNING])) {
            error_log("[$level] $message" . ($context ? ' | ' . json_encode($context) : ''));
        }
    }

    /**
     * Get log file path by level
     */
    private function getLogFile($level)
    {
        $date = date('Y-m-d');

        if ($level === self::LEVEL_DEBUG) {
            return "{$this->logPath}/debug-{$date}.log";
        } elseif ($level === self::LEVEL_INFO) {
            return "{$this->logPath}/info-{$date}.log";
        } else {
            return "{$this->logPath}/errors-{$date}.log";
        }
    }

    /**
     * Log API call with timing
     */
    public function logApiCall($method, $path, $status, $duration, $payload = null, $response = null)
    {
        $context = [
            'method' => $method,
            'path' => $path,
            'status' => $status,
            'duration_ms' => $duration,
        ];

        if ($payload !== null && $this->debugEnabled) {
            $context['payload'] = $this->redactSensitive($payload);
        }

        if ($response !== null && $this->debugEnabled) {
            $context['response'] = $this->redactSensitive($response);
        }

        if ($status >= 400) {
            $this->error("API Call Failed: $method $path", $context);
        } elseif ($status >= 300) {
            $this->warning("API Call Redirect: $method $path", $context);
        } elseif ($this->debugEnabled) {
            $this->debug("API Call Success: $method $path", $context);
        }
    }

    /**
     * Log consignment operation
     */
    public function logConsignmentOp($operation, $consignmentId, $details = [])
    {
        $context = array_merge(['consignment_id' => $consignmentId], $details);
        $this->info("Consignment: $operation", $context);
    }

    /**
     * Log product operation
     */
    public function logProductOp($operation, $productId, $details = [])
    {
        $context = array_merge(['product_id' => $productId], $details);
        if ($this->debugEnabled) {
            $this->debug("Product: $operation", $context);
        }
    }

    /**
     * Remove sensitive data from logging
     */
    private function redactSensitive($data)
    {
        if (is_array($data)) {
            $redacted = [];
            foreach ($data as $key => $value) {
                if (in_array(strtolower($key), ['password', 'token', 'secret', 'key', 'auth'])) {
                    $redacted[$key] = '***REDACTED***';
                } elseif (is_array($value) || is_object($value)) {
                    $redacted[$key] = $this->redactSensitive($value);
                } else {
                    $redacted[$key] = $value;
                }
            }
            return $redacted;
        } elseif (is_object($data)) {
            return $this->redactSensitive((array)$data);
        } else {
            return $data;
        }
    }

    /**
     * Get recent log entries (for admin dashboard)
     */
    public function getRecentErrors($limit = 50)
    {
        $logFile = "{$this->logPath}/errors-" . date('Y-m-d') . ".log";

        if (!file_exists($logFile)) {
            return [];
        }

        $lines = array_slice(explode(PHP_EOL, file_get_contents($logFile)), -$limit);
        return array_filter($lines);
    }

    /**
     * Check if debug mode is enabled
     */
    public function isDebugEnabled()
    {
        return $this->debugEnabled;
    }

    /**
     * Get log statistics for admin dashboard
     */
    public function getLogStats()
    {
        $today = date('Y-m-d');
        $stats = [
            'errors' => 0,
            'warnings' => 0,
            'info' => 0,
            'debug' => 0,
            'last_error' => null,
        ];

        foreach (['error', 'warning', 'info', 'debug'] as $type) {
            $logFile = "{$this->logPath}/{$type}-{$today}.log";
            if (file_exists($logFile)) {
                $count = count(array_filter(explode(PHP_EOL, file_get_contents($logFile))));
                $stats["{$type}s"] = $count;
            }
        }

        // Get last error
        $errorFile = "{$this->logPath}/errors-{$today}.log";
        if (file_exists($errorFile)) {
            $lines = array_filter(explode(PHP_EOL, file_get_contents($errorFile)));
            if (!empty($lines)) {
                $stats['last_error'] = end($lines);
            }
        }

        return $stats;
    }
}
