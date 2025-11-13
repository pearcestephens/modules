<?php
declare(strict_types=1);

namespace CIS\Base\Support;

/**
 * Response - unified JSON output helper.
 */
class Response
{
    /**
     * Send JSON with status code and optional extra headers.
     */
    public static function json(array $data, int $status = 200, array $headers = []): void
    {
        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: application/json');
            // Ensure no caching for dynamic / debug endpoints
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            header('Pragma: no-cache');
            foreach ($headers as $k => $v) {
                header($k . ': ' . $v);
            }
        }
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
    }

    /**
     * Error envelope.
     */
    public static function error(string $code, string $message = '', int $status = 400, array $extra = []): void
    {
        $payload = array_merge([
            'error' => $code,
            'message' => $message,
            'trace_id' => substr(sha1($code . $message . microtime()), 0, 12)
        ], $extra);
        self::json($payload, $status);
    }
}
