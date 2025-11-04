<?php
/**
 * HTTP Request Class
 *
 * Represents an incoming HTTP request
 *
 * @package CIS\Base\Http
 */

declare(strict_types=1);

namespace CIS\Base\Http;

class Request
{
    private string $id;
    private string $method;
    private string $uri;
    private array $query;
    private array $post;
    private array $files;
    private array $server;
    private array $headers;
    private ?string $body;
    private float $startTime;

    public function __construct(
        string $method,
        string $uri,
        array $query = [],
        array $post = [],
        array $files = [],
        array $server = [],
        ?string $body = null
    ) {
        $this->id = $this->generateRequestId();
        $this->method = strtoupper($method);
        $this->uri = $uri;
        $this->query = $query;
        $this->post = $post;
        $this->files = $files;
        $this->server = $server;
        $this->body = $body;
        $this->startTime = microtime(true);
        $this->headers = $this->extractHeaders();
    }

    /**
     * Create request from globals
     */
    public static function capture(): self
    {
        return new self(
            $_SERVER['REQUEST_METHOD'] ?? 'GET',
            $_SERVER['REQUEST_URI'] ?? '/',
            $_GET,
            $_POST,
            $_FILES,
            $_SERVER,
            file_get_contents('php://input') ?: null
        );
    }

    /**
     * Get unique request ID
     */
    public function id(): string
    {
        return $this->id;
    }

    /**
     * Get request method
     */
    public function method(): string
    {
        return $this->method;
    }

    /**
     * Get request URI
     */
    public function uri(): string
    {
        return $this->uri;
    }

    /**
     * Get query parameter
     */
    public function query(string $key, $default = null)
    {
        return $this->query[$key] ?? $default;
    }

    /**
     * Get all query parameters
     */
    public function queryAll(): array
    {
        return $this->query;
    }

    /**
     * Get POST parameter
     */
    public function post(string $key, $default = null)
    {
        return $this->post[$key] ?? $default;
    }

    /**
     * Get all POST parameters
     */
    public function postAll(): array
    {
        return $this->post;
    }

    /**
     * Get input (POST or query)
     */
    public function input(string $key, $default = null)
    {
        return $this->post[$key] ?? $this->query[$key] ?? $default;
    }

    /**
     * Get all input
     */
    public function all(): array
    {
        return array_merge($this->query, $this->post);
    }

    /**
     * Get only specified keys
     */
    public function only(array $keys): array
    {
        return array_intersect_key($this->all(), array_flip($keys));
    }

    /**
     * Get all except specified keys
     */
    public function except(array $keys): array
    {
        return array_diff_key($this->all(), array_flip($keys));
    }

    /**
     * Check if input exists
     */
    public function has(string $key): bool
    {
        return isset($this->post[$key]) || isset($this->query[$key]);
    }

    /**
     * Get uploaded file
     */
    public function file(string $key): ?array
    {
        return $this->files[$key] ?? null;
    }

    /**
     * Check if file exists
     */
    public function hasFile(string $key): bool
    {
        return isset($this->files[$key]) && $this->files[$key]['error'] === UPLOAD_ERR_OK;
    }

    /**
     * Get header
     */
    public function header(string $key, $default = null)
    {
        $key = strtolower($key);
        return $this->headers[$key] ?? $default;
    }

    /**
     * Get all headers
     */
    public function headers(): array
    {
        return $this->headers;
    }

    /**
     * Check if header exists
     */
    public function hasHeader(string $key): bool
    {
        return isset($this->headers[strtolower($key)]);
    }

    /**
     * Get bearer token
     */
    public function bearerToken(): ?string
    {
        $authorization = $this->header('authorization', '');

        if (preg_match('/Bearer\s+(.*)$/i', $authorization, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Check if request is GET
     */
    public function isGet(): bool
    {
        return $this->method === 'GET';
    }

    /**
     * Check if request is POST
     */
    public function isPost(): bool
    {
        return $this->method === 'POST';
    }

    /**
     * Check if request is PUT
     */
    public function isPut(): bool
    {
        return $this->method === 'PUT';
    }

    /**
     * Check if request is DELETE
     */
    public function isDelete(): bool
    {
        return $this->method === 'DELETE';
    }

    /**
     * Check if request is AJAX
     */
    public function isAjax(): bool
    {
        return $this->header('x-requested-with') === 'XMLHttpRequest';
    }

    /**
     * Check if request is JSON
     */
    public function isJson(): bool
    {
        return str_contains($this->header('content-type', ''), 'application/json');
    }

    /**
     * Get request body as JSON
     */
    public function json(): ?array
    {
        if ($this->body === null) {
            return null;
        }

        return json_decode($this->body, true);
    }

    /**
     * Get raw body
     */
    public function body(): ?string
    {
        return $this->body;
    }

    /**
     * Get client IP address
     */
    public function ip(): string
    {
        // Check for proxy headers
        if (!empty($this->server['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $this->server['HTTP_X_FORWARDED_FOR']);
            return trim($ips[0]);
        }

        if (!empty($this->server['HTTP_X_REAL_IP'])) {
            return $this->server['HTTP_X_REAL_IP'];
        }

        return $this->server['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    /**
     * Get user agent
     */
    public function userAgent(): string
    {
        return $this->server['HTTP_USER_AGENT'] ?? '';
    }

    /**
     * Get referer
     */
    public function referer(): ?string
    {
        return $this->server['HTTP_REFERER'] ?? null;
    }

    /**
     * Get request URL
     */
    public function url(): string
    {
        $scheme = $this->isSecure() ? 'https' : 'http';
        $host = $this->server['HTTP_HOST'] ?? 'localhost';
        $uri = parse_url($this->uri, PHP_URL_PATH);

        return "{$scheme}://{$host}{$uri}";
    }

    /**
     * Get full URL with query string
     */
    public function fullUrl(): string
    {
        return $this->url() . (empty($this->query) ? '' : '?' . http_build_query($this->query));
    }

    /**
     * Get path only
     */
    public function path(): string
    {
        return parse_url($this->uri, PHP_URL_PATH) ?? '/';
    }

    /**
     * Check if request is secure (HTTPS)
     */
    public function isSecure(): bool
    {
        return !empty($this->server['HTTPS']) && $this->server['HTTPS'] !== 'off';
    }

    /**
     * Get request start time
     */
    public function startTime(): float
    {
        return $this->startTime;
    }

    /**
     * Get request duration (milliseconds)
     */
    public function duration(): float
    {
        return (microtime(true) - $this->startTime) * 1000;
    }

    /**
     * Extract headers from $_SERVER
     */
    private function extractHeaders(): array
    {
        $headers = [];

        foreach ($this->server as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $headerKey = strtolower(str_replace('_', '-', substr($key, 5)));
                $headers[$headerKey] = $value;
            }
        }

        // Add content-type and content-length if present
        if (isset($this->server['CONTENT_TYPE'])) {
            $headers['content-type'] = $this->server['CONTENT_TYPE'];
        }

        if (isset($this->server['CONTENT_LENGTH'])) {
            $headers['content-length'] = $this->server['CONTENT_LENGTH'];
        }

        return $headers;
    }

    /**
     * Generate unique request ID
     */
    private function generateRequestId(): string
    {
        return uniqid('req_', true);
    }
}
