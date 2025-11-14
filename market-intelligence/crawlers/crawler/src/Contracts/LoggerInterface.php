<?php

declare(strict_types=1);
/**
 * Logger Interface.
 *
 * Contract for centralized logging with structured data and correlation
 * Defines methods for multi-level logging, context tracking, and analytics
 *
 * @version 2.0.0 - Ultra-Sophisticated ML/AI Enhanced
 */

namespace CIS\SharedServices\Crawler\Contracts;

interface LoggerInterface
{
    /**
     * Log a message with level and context
     * Enhanced with: Correlation IDs, structured logging, distributed tracing.
     *
     * @param string $level   Log level (debug, info, warning, error, critical)
     * @param string $message Log message
     * @param array  $context Additional context data
     */
    public function log(string $level, string $message, array $context = []): void;

    /**
     * Log debug message.
     *
     * @param string $message Debug message
     * @param array  $context Context data
     */
    public function debug(string $message, array $context = []): void;

    /**
     * Log info message.
     *
     * @param string $message Info message
     * @param array  $context Context data
     */
    public function info(string $message, array $context = []): void;

    /**
     * Log warning message.
     *
     * @param string $message Warning message
     * @param array  $context Context data
     */
    public function warning(string $message, array $context = []): void;

    /**
     * Log error message.
     *
     * @param string $message Error message
     * @param array  $context Context data
     */
    public function error(string $message, array $context = []): void;

    /**
     * Log critical message.
     *
     * @param string $message Critical message
     * @param array  $context Context data
     */
    public function critical(string $message, array $context = []): void;

    /**
     * Set correlation ID for request tracking
     * NEW: Distributed tracing support (Jaeger, Zipkin compatible).
     *
     * @param string $correlationId Unique request identifier
     */
    public function setCorrelationId(string $correlationId): void;

    /**
     * Get current correlation ID.
     *
     * @return string|null Correlation ID or null if not set
     */
    public function getCorrelationId(): ?string;

    /**
     * Add persistent context (applies to all subsequent logs)
     * NEW: Thread-local context storage.
     *
     * @param array $context Persistent context data
     */
    public function addContext(array $context): void;

    /**
     * Clear persistent context.
     */
    public function clearContext(): void;

    /**
     * Create child logger with inherited context
     * NEW: Hierarchical logging for nested operations.
     *
     * @param array $childContext Additional context for child logger
     *
     * @return self Child logger instance
     */
    public function createChild(array $childContext = []): self;

    /**
     * Log performance metrics (timing, memory, resources)
     * NEW: InfluxDB time-series integration.
     *
     * @param string $operation Operation name
     * @param float  $duration  Duration in seconds
     * @param array  $metrics   Additional metrics (memory, cpu, network)
     */
    public function logPerformance(string $operation, float $duration, array $metrics = []): void;

    /**
     * Log security event (detection, ban, suspicious activity)
     * NEW: Security event enrichment with threat intelligence.
     *
     * @param string $eventType Security event type
     * @param array  $details   Event details (ip, user_agent, detection_reason)
     */
    public function logSecurityEvent(string $eventType, array $details = []): void;

    /**
     * Get log statistics for analysis
     * NEW: Real-time log analytics.
     *
     * @param array $filters Filters (level, time_range, correlation_id)
     *
     * @return array Statistics (count_by_level, error_rate, top_errors)
     */
    public function getStatistics(array $filters = []): array;

    /**
     * Flush logs to persistent storage
     * Enhanced with: Batch writing, async flushing.
     */
    public function flush(): void;
}
