<?php
/**
 * Standardized JSON response helper with correlation id and timing.
 */
class SR_Response
{
    private static float $start;

    public static function boot(): void
    {
        if (!isset(self::$start)) { self::$start = microtime(true); }
    }

    public static function json(array $payload, int $status = 200, array $meta = []): void
    {
        global $SR_CORRELATION_ID;
        self::boot();
        $durationMs = (int)round((microtime(true) - self::$start) * 1000);
        $envelope = [
            'success' => $status >= 200 && $status < 300,
            'status' => $status,
            'correlation_id' => $SR_CORRELATION_ID ?? null,
            'duration_ms' => $durationMs,
            'data' => $payload,
            'meta' => $meta
        ];
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($envelope, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function error(string $message, int $status = 400, array $details = []): void
    {
        self::json(['error' => $message, 'details' => $details], $status);
    }
}
