<?php
/**
 * Payroll Module - Response Helper
 *
 * Standardized JSON response envelope for API endpoints
 *
 * @package Payroll\Lib
 * @version 1.0.0
 * @created 2025-11-02
 */

declare(strict_types=1);

namespace Payroll\Lib;

final class Respond
{
    /**
     * Generate unique request ID for tracing
     */
    public static function rid(): string
    {
        return bin2hex(random_bytes(8));
    }

    /**
     * Send successful JSON response
     *
     * @param array $data Response payload
     * @param int $code HTTP status code
     */
    public static function ok(array $data = [], int $code = 200): void
    {
        http_response_code($code);
        header('Content-Type: application/json');

        echo json_encode([
            'ok' => true,
            'request_id' => self::rid(),
            'data' => $data,
            'timestamp' => date('Y-m-d\TH:i:s\Z')
        ], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

        exit;
    }

    /**
     * Send error JSON response
     *
     * @param string $code Error code (e.g., 'INVALID_INPUT', 'DB_ERROR')
     * @param string $message Human-readable error message
     * @param array $meta Additional context/metadata
     * @param int $http HTTP status code
     */
    public static function fail(string $code, string $message, array $meta = [], int $http = 400): void
    {
        http_response_code($http);
        header('Content-Type: application/json');

        echo json_encode([
            'ok' => false,
            'request_id' => self::rid(),
            'code' => $code,
            'message' => $message,
            'meta' => $meta,
            'timestamp' => date('Y-m-d\TH:i:s\Z')
        ], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

        exit;
    }
}
