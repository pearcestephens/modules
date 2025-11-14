<?php

/**
 * System Access Logging Middleware Integration
 *
 * Easy integration with existing applications.
 * Can be used as Apache prepend, framework middleware, or manual calls.
 *
 * Usage:
 * 1. Apache auto_prepend_file: php_value auto_prepend_file "/path/to/access_logging_middleware.php"
 * 2. Framework: $middleware->add(SystemAccessLoggingMiddleware::class);
 * 3. Manual: SystemAccessLoggingMiddleware::track();
 *
 * @package FraudDetection\Middleware
 * @version 1.0.0
 */

namespace FraudDetection\Middleware;

require_once __DIR__ . '/SystemAccessLogger.php';

class SystemAccessLoggingMiddleware
{
    private static ?SystemAccessLogger $logger = null;
    private static ?float $startTime = null;

    /**
     * Initialize and start tracking (call at request start)
     */
    public static function start(): void
    {
        self::$startTime = microtime(true);

        // Register shutdown function to capture response
        register_shutdown_function([self::class, 'end']);
    }

    /**
     * End tracking and log access (call at request end)
     */
    public static function end(): void
    {
        if (!self::$startTime) {
            return;
        }

        $responseTime = round((microtime(true) - self::$startTime) * 1000, 2);

        try {
            $logger = self::getLogger();
            $request = self::captureRequest();
            $request['response_time'] = $responseTime;
            $request['response_code'] = http_response_code();

            $logger->logAccess($request);
        } catch (\Exception $e) {
            error_log("Access logging failed: " . $e->getMessage());
        }
    }

    /**
     * Track a single access (convenience method)
     */
    public static function track(array $customData = []): array
    {
        try {
            $logger = self::getLogger();
            $request = self::captureRequest();
            $request = array_merge($request, $customData);

            return $logger->logAccess($request);
        } catch (\Exception $e) {
            error_log("Access tracking failed: " . $e->getMessage());
            return ['logged' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get or create logger instance
     */
    private static function getLogger(): SystemAccessLogger
    {
        if (self::$logger === null) {
            $pdo = self::getDatabaseConnection();
            $config = [
                'enabled' => getenv('ACCESS_LOGGING_ENABLED') !== 'false',
                'excluded_paths' => self::getExcludedPaths()
            ];
            self::$logger = new SystemAccessLogger($pdo, $config);
        }
        return self::$logger;
    }

    /**
     * Get database connection
     */
    private static function getDatabaseConnection(): \PDO
    {
        static $pdo = null;

        if ($pdo === null) {
            $pdo = new \PDO(
                sprintf(
                    "mysql:host=%s;dbname=%s;charset=utf8mb4",
                    getenv('DB_HOST') ?: 'localhost',
                    getenv('DB_NAME') ?: 'cis'
                ),
                getenv('DB_USER') ?: 'root',
                getenv('DB_PASS') ?: '',
                [
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                    \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                    \PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
        }

        return $pdo;
    }

    /**
     * Capture current request
     */
    private static function captureRequest(): array
    {
        return [
            'path' => $_SERVER['REQUEST_URI'] ?? '/',
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
            'query_params' => $_GET ?? [],
            'post_params' => $_POST ?? [],
            'ip_address' => self::getClientIp(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'referer' => $_SERVER['HTTP_REFERER'] ?? null
        ];
    }

    /**
     * Get client IP (handles proxies)
     */
    private static function getClientIp(): string
    {
        $headers = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'REMOTE_ADDR'
        ];

        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return '0.0.0.0';
    }

    /**
     * Get excluded paths from config
     */
    private static function getExcludedPaths(): array
    {
        $default = [
            '/assets/',
            '/css/',
            '/js/',
            '/images/',
            '/favicon.ico',
            '/health',
            '/ping',
            '/robots.txt'
        ];

        $custom = getenv('ACCESS_LOGGING_EXCLUDED_PATHS');
        if ($custom) {
            return array_merge($default, explode(',', $custom));
        }

        return $default;
    }
}

// Auto-start if included via auto_prepend
if (!defined('ACCESS_LOGGING_MANUAL')) {
    SystemAccessLoggingMiddleware::start();
}
