<?php
/**
 * Smart Cron Logger - Structured Logging System
 *
 * Provides comprehensive logging with levels, context, and rotation.
 *
 * @version 2.0
 */

class SmartCronLogger
{
    private string $logFile;
    private string $logLevel;
    private array $context = [];

    const LEVEL_DEBUG = 'DEBUG';
    const LEVEL_INFO = 'INFO';
    const LEVEL_WARNING = 'WARNING';
    const LEVEL_ERROR = 'ERROR';
    const LEVEL_CRITICAL = 'CRITICAL';

    private const LEVELS = [
        self::LEVEL_DEBUG => 0,
        self::LEVEL_INFO => 1,
        self::LEVEL_WARNING => 2,
        self::LEVEL_ERROR => 3,
        self::LEVEL_CRITICAL => 4
    ];

    public function __construct(string $logFile, string $logLevel = self::LEVEL_INFO)
    {
        $this->logFile = $logFile;
        $this->logLevel = $logLevel;

        // Ensure log directory exists
        $logDir = dirname($logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0750, true);
        }

        // Rotate log if too large (> 10MB)
        if (file_exists($logFile) && filesize($logFile) > 10 * 1024 * 1024) {
            $this->rotateLog();
        }
    }

    /**
     * Set global context (appears in all log entries)
     */
    public function setContext(array $context): void
    {
        $this->context = array_merge($this->context, $context);
    }

    /**
     * Log debug message
     */
    public function debug(string $message, array $context = []): void
    {
        $this->log(self::LEVEL_DEBUG, $message, $context);
    }

    /**
     * Log info message
     */
    public function info(string $message, array $context = []): void
    {
        $this->log(self::LEVEL_INFO, $message, $context);
    }

    /**
     * Log warning message
     */
    public function warning(string $message, array $context = []): void
    {
        $this->log(self::LEVEL_WARNING, $message, $context);
    }

    /**
     * Log error message
     */
    public function error(string $message, array $context = []): void
    {
        $this->log(self::LEVEL_ERROR, $message, $context);
    }

    /**
     * Log critical message
     */
    public function critical(string $message, array $context = []): void
    {
        $this->log(self::LEVEL_CRITICAL, $message, $context);
    }

    /**
     * Core log method
     */
    private function log(string $level, string $message, array $context = []): void
    {
        // Check if this level should be logged
        if (self::LEVELS[$level] < self::LEVELS[$this->logLevel]) {
            return;
        }

        // Merge contexts
        $fullContext = array_merge($this->context, $context);

        // Build log entry
        $entry = [
            'timestamp' => date('Y-m-d H:i:s.u'),
            'level' => $level,
            'message' => $message,
            'context' => $fullContext,
            'pid' => getmypid(),
            'memory' => round(memory_get_usage(true) / 1024 / 1024, 2) . 'MB'
        ];

        // Format as JSON for easy parsing
        $logLine = json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;

        // Write to file
        $written = @file_put_contents($this->logFile, $logLine, FILE_APPEND | LOCK_EX);

        if ($written === false) {
            error_log("Failed to write to Smart Cron log: " . $this->logFile);
        }

        // Also write critical errors to PHP error log
        if ($level === self::LEVEL_CRITICAL || $level === self::LEVEL_ERROR) {
            error_log(sprintf('[Smart Cron][%s] %s', $level, $message));
        }
    }

    /**
     * Rotate log file
     */
    private function rotateLog(): void
    {
        $rotatedFile = $this->logFile . '.' . date('YmdHis') . '.gz';

        // Compress and move
        if ($fp = gzopen($rotatedFile, 'w9')) {
            $content = file_get_contents($this->logFile);
            gzwrite($fp, $content);
            gzclose($fp);

            // Clear original file
            file_put_contents($this->logFile, '');

            $this->info('Log file rotated', ['rotated_file' => $rotatedFile]);
        }
    }

    /**
     * Get recent log entries (for dashboard)
     */
    public static function getTail(string $logFile, int $lines = 100): array
    {
        if (!file_exists($logFile)) {
            return [];
        }

        $entries = [];
        $handle = @fopen($logFile, 'r');

        if (!$handle) {
            return [];
        }

        // Read from end
        fseek($handle, -1, SEEK_END);
        $position = ftell($handle);
        $lineCount = 0;
        $buffer = '';

        while ($position > 0 && $lineCount < $lines) {
            $char = fgetc($handle);

            if ($char === "\n" && $buffer !== '') {
                $entries[] = json_decode(strrev($buffer), true);
                $buffer = '';
                $lineCount++;
            } else {
                $buffer .= $char;
            }

            $position--;
            fseek($handle, $position);
        }

        // Last line
        if ($buffer !== '') {
            $entries[] = json_decode(strrev($buffer), true);
        }

        fclose($handle);

        return array_reverse(array_filter($entries));
    }

    /**
     * Search logs by criteria
     */
    public static function search(string $logFile, array $criteria, int $limit = 100): array
    {
        $entries = self::getTail($logFile, 10000); // Get large sample
        $results = [];

        foreach ($entries as $entry) {
            $matches = true;

            // Check each criterion
            foreach ($criteria as $key => $value) {
                if ($key === 'level' && $entry['level'] !== $value) {
                    $matches = false;
                    break;
                }

                if ($key === 'message' && stripos($entry['message'], $value) === false) {
                    $matches = false;
                    break;
                }

                if ($key === 'date' && !str_starts_with($entry['timestamp'], $value)) {
                    $matches = false;
                    break;
                }
            }

            if ($matches) {
                $results[] = $entry;

                if (count($results) >= $limit) {
                    break;
                }
            }
        }

        return $results;
    }
}
