<?php
/**
 * Lightweight response helper utilities.
 *
 * @package App\\Support
 */

declare(strict_types=1);

namespace App\Support;

final class Response
{
    private function __construct()
    {
    }

    /**
     * Send a JSON response and terminate execution.
     *
     * @param array<string,mixed> $payload
     * @param int $statusCode
     * @return void
     */
    public static function json(array $payload, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /**
     * Send a simple HTML response.
     *
     * @param string $html
     * @param int $statusCode
     * @return void
     */
    public static function html(string $html, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: text/html; charset=utf-8');
        echo $html;
        exit;
    }

    /**
     * Shortcut for error JSON responses.
     *
     * @param string $message
     * @param int $statusCode
     * @param array<string,mixed> $context
     * @return void
     */
    public static function error(string $message, int $statusCode = 500, array $context = []): void
    {
        $payload = array_merge([
            'success' => false,
            'status' => $statusCode,
            'message' => $message,
        ], $context);

        self::json($payload, $statusCode);
    }
}