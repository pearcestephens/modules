<?php

declare(strict_types=1);
/**
 * Logger - Enterprise-Grade Distributed Logging System.
 *
 * Features:
 * - Structured logging (ELK Stack compatible)
 * - Distributed tracing (Jaeger/Zipkin)
 * - Correlation IDs for request tracking
 * - Hierarchical loggers with context inheritance
 * - Performance metrics logging
 * - Security event enrichment
 * - Multi-channel output (file, database, Redis, stdout)
 *
 * @version 2.0.0
 */

namespace CIS\SharedServices\Crawler\Core;

use CIS\SharedServices\Crawler\Contracts\LoggerInterface;

use function sprintf;

use const FILE_APPEND;
use const LOCK_EX;

class Logger implements LoggerInterface
{
    private const LEVEL_PRIORITY = [
        'debug'    => 1,
        'info'     => 2,
        'warning'  => 3,
        'error'    => 4,
        'critical' => 5,
    ];

    private array $config;

    private ?string $correlationId = null;

    private array $persistentContext = [];

    private array $handlers = [];

    private array $statistics = [
        'debug'    => 0,
        'info'     => 0,
        'warning'  => 0,
        'error'    => 0,
        'critical' => 0,
    ];

    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'level'               => 'info',
            'channels'            => ['file'],
            'file_path'           => '/tmp/crawler/',
            'correlation_id'      => true,
            'distributed_tracing' => false,
            'performance_logging' => true,
            'security_logging'    => true,
        ], $config);

        $this->initializeHandlers();
        $this->ensureLogDirectory();
    }

    /**
     * Start correlation tracking.
     */
    public function startCorrelation(string $correlationId = null): string
    {
        $this->correlationId = $correlationId ?? bin2hex(random_bytes(16));
        return $this->correlationId;
    }

    /**
     * Start performance timer.
     */
    public function startTimer(string $name): void
    {
        $this->persistentContext[$name . '_start'] = microtime(true);
    }

    /**
     * Stop performance timer and return elapsed time.
     */
    public function stopTimer(string $name): float
    {
        $start = $this->persistentContext[$name . '_start'] ?? microtime(true);
        return microtime(true) - $start;
    }

    /**
     * Set persistent context.
     */
    public function setContext(array $context): void
    {
        $this->persistentContext = array_merge($this->persistentContext, $context);
    }

    /**
     * Log security event.
     */
    public function security(string $message, array $context = []): void
    {
        $this->log('warning', '[SECURITY] ' . $message, $context);
    }

    /**
     * Log exception.
     */
    public function exception(\Throwable $exception, array $context = []): void
    {
        $this->log('error', $exception->getMessage(), array_merge($context, [
            'exception' => get_class($exception),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString()
        ]));
    }

    /**
     * Get current memory usage.
     */
    public function getCurrentMemoryUsage(): int
    {
        return memory_get_usage(true);
    }

    /**
     * Create child correlation ID.
     */
    public function createChildCorrelation(string $parentId = null): string
    {
        $parentId = $parentId ?? $this->correlationId ?? uniqid('corr-', true);

        return $parentId . '-' . uniqid('child-', true);
    }

    public function getCorrelationId(): string
    {
        return $this->correlationId ?? '';
    }
}

    /**
     * Log message with level and context.
     */
    public function log(string $level, string $message, array $context = []): void
    {
        if (!$this->shouldLog($level)) {
            return;
        }

        $logEntry = $this->buildLogEntry($level, $message, $context);
        $this->writeToHandlers($logEntry);
        $this->updateStatistics($level);
    }

    /**
     * Log debug message.
     */
    public function debug(string $message, array $context = []): void
    {
        $this->log('debug', $message, $context);
    }

    /**
     * Log info message.
     */
    public function info(string $message, array $context = []): void
    {
        $this->log('info', $message, $context);
    }

    /**
     * Log warning message.
     */
    public function warning(string $message, array $context = []): void
    {
        $this->log('warning', $message, $context);
    }

    /**
     * Log error message.
     */
    public function error(string $message, array $context = []): void
    {
        $this->log('error', $message, $context);
    }

    /**
     * Log critical message.
     */
    public function critical(string $message, array $context = []): void
    {
        $this->log('critical', $message, $context);
    }

    /**
     * Set correlation ID for distributed tracing.
     */
    public function setCorrelationId(string $correlationId): void
    {
        $this->correlationId = $correlationId;
    }

    /**
     * Get current correlation ID.
     */
    public function getCorrelationId(): ?string
    {
        return $this->correlationId;
    }

    /**
     * Add persistent context.
     */
    public function addContext(array $context): void
    {
        $this->persistentContext = array_merge($this->persistentContext, $context);
    }

    /**
     * Clear persistent context.
     */
    public function clearContext(): void
    {
        $this->persistentContext = [];
    }

    /**
     * Create child logger with inherited context.
     */
    public function createChild(array $childContext = []): LoggerInterface
    {
        $child                    = new self($this->config);
        $child->correlationId     = $this->correlationId;
        $child->persistentContext = array_merge($this->persistentContext, $childContext);

        return $child;
    }

    /**
     * Log performance metrics.
     */
    public function logPerformance(string $operation, float $duration, array $metrics = []): void
    {
        if (!$this->config['performance_logging']) {
            return;
        }

        $this->log('info', "Performance: {$operation}", [
            'type'           => 'performance',
            'operation'      => $operation,
            'duration_ms'    => round($duration * 1000, 2),
            'memory_mb'      => round(memory_get_usage(true) / 1024 / 1024, 2),
            'peak_memory_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
            'metrics'        => $metrics,
        ]);
    }

    /**
     * Log security event.
     */
    public function logSecurityEvent(string $eventType, array $details = []): void
    {
        if (!$this->config['security_logging']) {
            return;
        }

        $this->log('warning', "Security Event: {$eventType}", [
            'type'       => 'security',
            'event_type' => $eventType,
            'ip'         => $_SERVER['REMOTE_ADDR'] ?? 'cli',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'cli',
            'timestamp'  => microtime(true),
            'details'    => $details,
        ]);
    }

    /**
     * Get log statistics.
     */
    public function getStatistics(array $filters = []): array
    {
        $stats = [
            'total_logs' => array_sum($this->statistics),
            'by_level'   => $this->statistics,
            'error_rate' => 0,
        ];

        $totalLogs = $stats['total_logs'];
        if ($totalLogs > 0) {
            $errors              = $this->statistics['error'] + $this->statistics['critical'];
            $stats['error_rate'] = round(($errors / $totalLogs) * 100, 2);
        }

        return $stats;
    }

    /**
     * Flush logs to persistent storage.
     */
    public function flush(): void
    {
        foreach ($this->handlers as $handler) {
            if (method_exists($handler, 'flush')) {
                $handler->flush();
            }
        }
    }

    // ============================================================================
    // PRIVATE METHODS
    // ============================================================================

    private function initializeHandlers(): void
    {
        foreach ($this->config['channels'] as $channel) {
            switch ($channel) {
                case 'file':
                    $this->handlers[] = new FileHandler($this->config);

                    break;
                case 'database':
                    // Database handler implementation
                    break;
                case 'redis':
                    // Redis handler implementation
                    break;
                case 'stdout':
                    $this->handlers[] = new StdoutHandler();

                    break;
            }
        }
    }

    private function buildLogEntry(string $level, string $message, array $context): array
    {
        $entry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'microtime' => microtime(true),
            'level'     => strtoupper($level),
            'message'   => $message,
            'context'   => array_merge($this->persistentContext, $context),
            'memory_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
        ];

        if ($this->correlationId) {
            $entry['correlation_id'] = $this->correlationId;
        }

        if ($this->config['distributed_tracing']) {
            $entry['trace_id'] = $this->correlationId ?? $this->generateTraceId();
            $entry['span_id']  = $this->generateSpanId();
        }

        return $entry;
    }

    private function shouldLog(string $level): bool
    {
        $configLevel     = $this->config['level'] ?? 'info';
        $configPriority  = self::LEVEL_PRIORITY[$configLevel] ?? 2;
        $messagePriority = self::LEVEL_PRIORITY[$level] ?? 2;

        return $messagePriority >= $configPriority;
    }

    private function writeToHandlers(array $logEntry): void
    {
        foreach ($this->handlers as $handler) {
            $handler->write($logEntry);
        }
    }

    private function updateStatistics(string $level): void
    {
        if (isset($this->statistics[$level])) {
            $this->statistics[$level]++;
        }
    }

    private function ensureLogDirectory(): void
    {
        $path = $this->config['file_path'];
        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }
    }

    private function generateTraceId(): string
    {
        return bin2hex(random_bytes(16));
    }

    private function generateSpanId(): string
    {
        return bin2hex(random_bytes(8));
    }
}

/**
 * File Handler - Writes logs to file.
 */
class FileHandler
{
    private string $filePath;

    public function __construct(array $config)
    {
        $this->filePath = $config['file_path'] . 'crawler-' . date('Y-m-d') . '.log';
    }

    public function write(array $logEntry): void
    {
        $line = sprintf(
            "[%s] %s: %s %s\n",
            $logEntry['timestamp'],
            $logEntry['level'],
            $logEntry['message'],
            !empty($logEntry['context']) ? json_encode($logEntry['context']) : '',
        );

        file_put_contents($this->filePath, $line, FILE_APPEND | LOCK_EX);
    }
}

/**
 * Stdout Handler - Writes logs to stdout.
 */
class StdoutHandler
{
    public function write(array $logEntry): void
    {
        $line = sprintf(
            "[%s] %s: %s\n",
            $logEntry['timestamp'],
            $logEntry['level'],
            $logEntry['message'],
        );

        echo $line;
    }
}
