<?php
/**
 * Logger Service - Application Logging
 *
 * @package CIS\Base\Core
 * @version 2.0.0
 */

declare(strict_types=1);

namespace CIS\Base\Core;

class Logger
{
    private Application $app;
    private array $config;

    // Log levels (PSR-3 compatible)
    public const EMERGENCY = 'emergency';
    public const ALERT = 'alert';
    public const CRITICAL = 'critical';
    public const ERROR = 'error';
    public const WARNING = 'warning';
    public const NOTICE = 'notice';
    public const INFO = 'info';
    public const DEBUG = 'debug';

    /**
     * Create logger instance
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
        $this->config = $app->config('logging', []);
    }

    /**
     * Log emergency message
     */
    public function emergency(string $message, array $context = []): void
    {
        $this->log(self::EMERGENCY, $message, $context);
    }

    /**
     * Log alert message
     */
    public function alert(string $message, array $context = []): void
    {
        $this->log(self::ALERT, $message, $context);
    }

    /**
     * Log critical message
     */
    public function critical(string $message, array $context = []): void
    {
        $this->log(self::CRITICAL, $message, $context);
    }

    /**
     * Log error message
     */
    public function error(string $message, array $context = []): void
    {
        $this->log(self::ERROR, $message, $context);
    }

    /**
     * Log warning message
     */
    public function warning(string $message, array $context = []): void
    {
        $this->log(self::WARNING, $message, $context);
    }

    /**
     * Log notice message
     */
    public function notice(string $message, array $context = []): void
    {
        $this->log(self::NOTICE, $message, $context);
    }

    /**
     * Log info message
     */
    public function info(string $message, array $context = []): void
    {
        $this->log(self::INFO, $message, $context);
    }

    /**
     * Log debug message
     */
    public function debug(string $message, array $context = []): void
    {
        $this->log(self::DEBUG, $message, $context);
    }

    /**
     * Log message with level
     */
    public function log(string $level, string $message, array $context = []): void
    {
        // Check if logging is enabled for this level
        $minLevel = $this->config['level'] ?? self::DEBUG;
        if (!$this->shouldLog($level, $minLevel)) {
            return;
        }

        // Format message
        $timestamp = date('Y-m-d H:i:s');
        $formattedMessage = $this->interpolate($message, $context);
        $logLine = "[{$timestamp}] [{$level}] {$formattedMessage}";

        // Add context if present
        if (!empty($context)) {
            $logLine .= ' ' . json_encode($context);
        }

        $logLine .= PHP_EOL;

        // Write to file
        $this->writeToFile($level, $logLine);

        // Write to database if configured
        if ($this->config['database'] ?? false) {
            $this->writeToDatabase($level, $message, $context);
        }
    }

    /**
     * Check if should log based on level
     */
    private function shouldLog(string $level, string $minLevel): bool
    {
        $levels = [
            self::DEBUG => 0,
            self::INFO => 1,
            self::NOTICE => 2,
            self::WARNING => 3,
            self::ERROR => 4,
            self::CRITICAL => 5,
            self::ALERT => 6,
            self::EMERGENCY => 7,
        ];

        return ($levels[$level] ?? 0) >= ($levels[$minLevel] ?? 0);
    }

    /**
     * Interpolate context values into message
     */
    private function interpolate(string $message, array $context): string
    {
        $replace = [];

        foreach ($context as $key => $val) {
            if (is_string($val) || is_numeric($val)) {
                $replace["{{$key}}"] = $val;
            }
        }

        return strtr($message, $replace);
    }

    /**
     * Write log to file
     */
    private function writeToFile(string $level, string $logLine): void
    {
        $channel = $this->config['channel'] ?? 'daily';
        $path = $this->config['path'] ?? $this->app->storagePath('logs');

        // Create logs directory if not exists
        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }

        // Determine log file name
        if ($channel === 'daily') {
            $filename = 'cis-' . date('Y-m-d') . '.log';
        } elseif ($channel === 'single') {
            $filename = 'cis.log';
        } else {
            $filename = "{$channel}.log";
        }

        $filepath = $path . '/' . $filename;

        // Write to file
        file_put_contents($filepath, $logLine, FILE_APPEND | LOCK_EX);
    }

    /**
     * Write log to database
     */
    private function writeToDatabase(string $level, string $message, array $context): void
    {
        try {
            $db = $this->app->make(Database::class);

            $db->insert('logs', [
                'level' => $level,
                'message' => $message,
                'context' => json_encode($context),
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Exception $e) {
            // Fail silently - don't break app if logging fails
            error_log("Failed to write log to database: " . $e->getMessage());
        }
    }

    /**
     * Get recent logs from file
     */
    public function recent(int $lines = 100): array
    {
        $path = $this->config['path'] ?? $this->app->storagePath('logs');
        $filename = 'cis-' . date('Y-m-d') . '.log';
        $filepath = $path . '/' . $filename;

        if (!file_exists($filepath)) {
            return [];
        }

        $file = new \SplFileObject($filepath);
        $file->seek(PHP_INT_MAX);
        $totalLines = $file->key();

        $startLine = max(0, $totalLines - $lines);
        $file->seek($startLine);

        $logs = [];
        while (!$file->eof()) {
            $line = trim($file->current());
            if ($line !== '') {
                $logs[] = $line;
            }
            $file->next();
        }

        return $logs;
    }
}
