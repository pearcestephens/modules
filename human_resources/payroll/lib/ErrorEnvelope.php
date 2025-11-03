<?php
/**
 * Payroll Module - Error Envelope
 *
 * Normalizes exceptions into consistent error structure for DLQ/logging
 *
 * @package Payroll\Lib
 * @version 1.0.0
 * @created 2025-11-02
 */

declare(strict_types=1);

namespace Payroll\Lib;

use Throwable;
use PDOException;

final class ErrorEnvelope
{
    /**
     * Convert exception to normalized error envelope
     *
     * @param Throwable $e The exception
     * @param array $meta Additional context metadata
     * @return array Error envelope with structure:
     *   - ok: false
     *   - request_id: unique ID
     *   - category: DB|API|VALIDATION|INTERNAL
     *   - code: error code string
     *   - message: error message (truncated to 240 chars)
     *   - meta: additional context
     *   - timestamp: ISO8601 timestamp
     */
    public static function from(Throwable $e, array $meta = []): array
    {
        // Categorize error
        $category = self::categorize($e);
        $code = self::codeFrom($e);

        return [
            'ok' => false,
            'request_id' => bin2hex(random_bytes(8)),
            'category' => $category,
            'code' => $code,
            'message' => substr($e->getMessage(), 0, 240),
            'meta' => array_merge($meta, [
                'exception_class' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]),
            'timestamp' => date('Y-m-d\TH:i:s\Z')
        ];
    }

    /**
     * Categorize exception type
     */
    private static function categorize(Throwable $e): string
    {
        if ($e instanceof PDOException) {
            return 'DB';
        }

        if ($e instanceof \InvalidArgumentException) {
            return 'VALIDATION';
        }

        $class = get_class($e);
        if (str_contains($class, 'RateLimitException')) {
            return 'API';
        }

        return 'INTERNAL';
    }

    /**
     * Generate error code from exception
     */
    private static function codeFrom(Throwable $e): string
    {
        if ($e instanceof PDOException) {
            return 'DB_ERROR';
        }

        if ($e instanceof \InvalidArgumentException) {
            return 'VALIDATION_ERROR';
        }

        $class = get_class($e);

        if (str_contains($class, 'RateLimitException')) {
            return 'RATE_LIMIT';
        }

        if (str_contains($class, 'TimeoutException')) {
            return 'TIMEOUT';
        }

        return 'UNEXPECTED';
    }

    /**
     * Check if error is retryable
     *
     * @param array $envelope Error envelope
     * @return bool True if operation can be retried
     */
    public static function isRetryable(array $envelope): bool
    {
        $retryableCodes = [
            'RATE_LIMIT',
            'TIMEOUT',
            'DB_ERROR', // Connection issues, not constraint violations
        ];

        $code = $envelope['code'] ?? '';

        return in_array($code, $retryableCodes, true);
    }

    /**
     * Get suggested retry delay in seconds
     *
     * @param array $envelope Error envelope
     * @return int Seconds to wait before retry
     */
    public static function retryDelay(array $envelope): int
    {
        $code = $envelope['code'] ?? '';

        return match($code) {
            'RATE_LIMIT' => $envelope['meta']['retry_after'] ?? 60,
            'TIMEOUT' => 30,
            'DB_ERROR' => 10,
            default => 5
        };
    }
}
