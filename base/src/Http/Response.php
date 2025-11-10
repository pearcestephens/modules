<?php

/**
 * HTTP Response Class.
 *
 * Represents an HTTP response
 */

declare(strict_types=1);

namespace CIS\Base\Http;

use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

class Response
{
    private int $statusCode = 200;

    private array $headers = [];

    private $content;

    private bool $sent = false;

    private static array $statusTexts = [
        200 => 'OK',
        201 => 'Created',
        204 => 'No Content',
        301 => 'Moved Permanently',
        302 => 'Found',
        304 => 'Not Modified',
        400 => 'Bad Request',
        401 => 'Unauthorized',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        419 => 'CSRF Token Mismatch',
        422 => 'Unprocessable Entity',
        429 => 'Too Many Requests',
        500 => 'Internal Server Error',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
    ];

    public function __construct($content = '', int $statusCode = 200, array $headers = [])
    {
        $this->content    = $content;
        $this->statusCode = $statusCode;
        $this->headers    = $headers;
    }

    /**
     * Create JSON response.
     */
    public static function json(array $data, int $statusCode = 200): self
    {
        $response = new self(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $statusCode);
        $response->header('Content-Type', 'application/json');

        // Add metadata
        if (!isset($data['_meta'])) {
            $response->content = json_encode(array_merge($data, [
                '_meta' => [
                    'timestamp' => date('c'),
                    'status'    => $statusCode,
                    'success'   => $statusCode < 400,
                ],
            ]), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return $response;
    }

    /**
     * Create HTML response.
     */
    public static function html(string $content, int $statusCode = 200): self
    {
        $response = new self($content, $statusCode);
        $response->header('Content-Type', 'text/html; charset=UTF-8');

        return $response;
    }

    /**
     * Create redirect response.
     */
    public static function redirect(string $url, int $statusCode = 302): self
    {
        $response = new self('', $statusCode);
        $response->header('Location', $url);

        return $response;
    }

    /**
     * Create 404 not found response.
     */
    public static function notFound(array $data = []): self
    {
        return self::json(array_merge([
            'error'   => 'Not Found',
            'message' => 'The requested resource was not found.',
        ], $data), 404);
    }

    /**
     * Create 401 unauthorized response.
     */
    public static function unauthorized(array $data = []): self
    {
        return self::json(array_merge([
            'error'   => 'Unauthorized',
            'message' => 'Authentication is required.',
        ], $data), 401);
    }

    /**
     * Create 403 forbidden response.
     */
    public static function forbidden(array $data = []): self
    {
        return self::json(array_merge([
            'error'   => 'Forbidden',
            'message' => 'You do not have permission to access this resource.',
        ], $data), 403);
    }

    /**
     * Create 405 method not allowed response.
     */
    public static function methodNotAllowed(array $data = []): self
    {
        return self::json(array_merge([
            'error'   => 'Method Not Allowed',
            'message' => 'The request method is not supported for this endpoint.',
        ], $data), 405);
    }

    /**
     * Create 422 validation error response.
     */
    public static function validationError(array $errors, string $message = 'Validation failed'): self
    {
        return self::json([
            'error'   => 'Validation Error',
            'message' => $message,
            'errors'  => $errors,
        ], 422);
    }

    /**
     * Create 429 too many requests response.
     */
    public static function tooManyRequests(array $data = []): self
    {
        $response = self::json(array_merge([
            'error'   => 'Too Many Requests',
            'message' => 'Rate limit exceeded.',
        ], $data), 429);

        if (isset($data['retry_after'])) {
            $response->header('Retry-After', (string) $data['retry_after']);
        }

        return $response;
    }

    /**
     * Create 500 error response.
     */
    public static function error(array $data = [], int $statusCode = 500): self
    {
        return self::json(array_merge([
            'error'   => 'Internal Server Error',
            'message' => 'An unexpected error occurred.',
        ], $data), $statusCode);
    }

    /**
     * Create success response.
     */
    public static function success(array $data = [], string $message = 'Success'): self
    {
        return self::json(array_merge([
            'success' => true,
            'message' => $message,
        ], $data), 200);
    }

    /**
     * Create created response.
     */
    public static function created(array $data = [], string $message = 'Resource created'): self
    {
        return self::json(array_merge([
            'success' => true,
            'message' => $message,
        ], $data), 201);
    }

    /**
     * Create no content response.
     */
    public static function noContent(): self
    {
        return new self('', 204);
    }

    /**
     * Set status code.
     */
    public function setStatusCode(int $statusCode): self
    {
        $this->statusCode = $statusCode;

        return $this;
    }

    /**
     * Get status code.
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Set header.
     */
    public function header(string $key, string $value): self
    {
        $this->headers[$key] = $value;

        return $this;
    }

    /**
     * Set multiple headers.
     */
    public function headers(array $headers): self
    {
        foreach ($headers as $key => $value) {
            $this->header($key, $value);
        }

        return $this;
    }

    /**
     * Get header.
     */
    public function getHeader(string $key): ?string
    {
        return $this->headers[$key] ?? null;
    }

    /**
     * Get all headers.
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Set cookie.
     */
    public function cookie(
        string $name,
        string $value,
        int $minutes = 0,
        string $path = '/',
        ?string $domain = null,
        bool $secure = true,
        bool $httpOnly = true,
    ): self {
        $expires = $minutes > 0 ? time() + ($minutes * 60) : 0;

        setcookie($name, $value, [
            'expires'  => $expires,
            'path'     => $path,
            'domain'   => $domain ?? '',
            'secure'   => $secure,
            'httponly' => $httpOnly,
            'samesite' => 'Lax',
        ]);

        return $this;
    }

    /**
     * Set content.
     */
    public function setContent($content): self
    {
        $this->content = $content;

        return $this;
    }

    /**
     * Get content.
     */
    public function getContent()
    {
        return $this->content;
    }

    /**
     * Send response.
     */
    public function send(): void
    {
        if ($this->sent) {
            return;
        }

        // Send status code
        http_response_code($this->statusCode);

        // Send headers
        foreach ($this->headers as $key => $value) {
            header("{$key}: {$value}");
        }

        // Send content
        if ($this->content !== null && $this->content !== '') {
            echo $this->content;
        }

        $this->sent = true;
    }

    /**
     * Get status text.
     */
    public static function getStatusText(int $code): string
    {
        return self::$statusTexts[$code] ?? 'Unknown';
    }
}
