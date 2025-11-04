<?php
/**
 * CIS Base Module - Front Controller
 *
 * GET query string router: ?endpoint=admin/traffic/monitor
 *
 * @package CIS\Base
 */

declare(strict_types=1);

// Bootstrap the application
require_once __DIR__ . '/../bootstrap/app.php';

use CIS\Base\Core\Application;
use CIS\Base\Http\Request;
use CIS\Base\Http\Response;
use CIS\Base\Http\Router;

try {
    // Initialize application
    $app = Application::getInstance();

    // Create request object
    $request = Request::capture();

    // Get endpoint from query string
    $endpoint = $request->query('endpoint', '');

    // Load URL configuration
    $urlConfig = require __DIR__ . '/../config/urls.php';

    // Handle empty endpoint (homepage)
    if (empty($endpoint)) {
        $endpoint = 'home';
    }

    // Check for static redirects
    if (isset($urlConfig['redirects'][$endpoint])) {
        [$target, $code] = $urlConfig['redirects'][$endpoint];
        Response::redirect($app->url($target), $code)->send();
        exit;
    }

    // Check for dynamic redirects from database
    $redirect = $app->database()->query(
        "SELECT to_path, status_code FROM web_traffic_redirects
         WHERE from_path = ? AND is_active = 1 LIMIT 1",
        [$endpoint]
    )->fetch();

    if ($redirect) {
        // Update hit count
        $app->database()->execute(
            "UPDATE web_traffic_redirects
             SET hit_count = hit_count + 1, last_hit_at = NOW()
             WHERE from_path = ?",
            [$endpoint]
        );

        Response::redirect($app->url($redirect['to_path']), (int)$redirect['status_code'])->send();
        exit;
    }

    // Find route in endpoints
    if (!isset($urlConfig['endpoints'][$endpoint])) {
        // Log 404
        $app->logger()->warning('404 Not Found', [
            'endpoint' => $endpoint,
            'method' => $request->method(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        // Record 404 error
        $app->database()->execute(
            "INSERT INTO web_traffic_errors
             (request_id, timestamp, error_code, error_type, error_message, endpoint, method, ip_address, user_agent)
             VALUES (?, NOW(3), 404, '404NotFound', 'Endpoint not found', ?, ?, ?, ?)",
            [
                $request->id(),
                $endpoint,
                $request->method(),
                $request->ip(),
                $request->userAgent(),
            ]
        );

        Response::notFound([
            'error' => 'Endpoint not found',
            'endpoint' => $endpoint,
            'message' => 'The requested endpoint does not exist.',
        ])->send();
        exit;
    }

    $route = $urlConfig['endpoints'][$endpoint];
    $method = $request->method();

    // Check if method is allowed
    if (!isset($route[$method])) {
        $allowed = implode(', ', array_keys($route));

        Response::methodNotAllowed([
            'error' => 'Method not allowed',
            'method' => $method,
            'endpoint' => $endpoint,
            'allowed_methods' => $allowed,
        ])->send();
        exit;
    }

    // Parse controller and method
    [$controllerName, $methodName] = explode('@', $route[$method]);
    $controllerClass = "CIS\\Base\\Http\\Controllers\\{$controllerName}";

    // Check if controller exists
    if (!class_exists($controllerClass)) {
        throw new \RuntimeException("Controller not found: {$controllerClass}");
    }

    // Instantiate controller
    $controller = new $controllerClass($app);

    // Check if method exists
    if (!method_exists($controller, $methodName)) {
        throw new \RuntimeException("Method not found: {$controllerClass}::{$methodName}");
    }

    // Apply middleware (from config/urls.php)
    $middlewareStack = $urlConfig['global_middleware'] ?? [];

    // Determine route group middleware
    foreach ($urlConfig['groups'] as $groupName => $groupConfig) {
        if (str_starts_with($endpoint, $groupConfig['prefix'])) {
            $middlewareStack = array_merge($middlewareStack, $groupConfig['middleware'] ?? []);
            break;
        }
    }

    // Execute middleware
    foreach ($middlewareStack as $middlewareName) {
        if (isset($urlConfig['middleware'][$middlewareName])) {
            $middlewareClass = $urlConfig['middleware'][$middlewareName];

            if (!class_exists($middlewareClass)) {
                continue;
            }

            $middleware = new $middlewareClass($app);
            $middlewareResult = $middleware->handle($request);

            // If middleware returns a response, send it and stop
            if ($middlewareResult instanceof Response) {
                $middlewareResult->send();
                exit;
            }
        }
    }

    // Check rate limiting
    if (isset($urlConfig['rate_limits'][$endpoint])) {
        [$limit, $minutes] = $urlConfig['rate_limits'][$endpoint];
    } else {
        // Use default admin rate limit
        $limit = $app->config('rate_limit.limits.admin.0', 120);
        $minutes = $app->config('rate_limit.limits.admin.1', 1);
    }

    // Simple rate limit check (IP-based)
    if ($app->config('rate_limit.enabled', true)) {
        $rateLimitKey = 'rate_limit:' . $request->ip() . ':' . $endpoint;
        $attempts = (int)($app->cache()->get($rateLimitKey) ?? 0);

        if ($attempts >= $limit) {
            Response::tooManyRequests([
                'error' => 'Too many requests',
                'limit' => $limit,
                'window' => $minutes . ' minute(s)',
                'retry_after' => 60,
            ])->send();
            exit;
        }

        $app->cache()->set($rateLimitKey, $attempts + 1, $minutes * 60);
    }

    // Execute controller method
    $response = $controller->$methodName($request);

    // Send response
    if ($response instanceof Response) {
        $response->send();
    } elseif (is_array($response)) {
        Response::json($response)->send();
    } elseif (is_string($response)) {
        Response::html($response)->send();
    } else {
        Response::json(['success' => true, 'data' => $response])->send();
    }

} catch (\Throwable $e) {
    // Log error
    $app->logger()->error('Unhandled exception', [
        'exception' => get_class($e),
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString(),
    ]);

    // Record 500 error
    try {
        $app->database()->execute(
            "INSERT INTO web_traffic_errors
             (request_id, timestamp, error_code, error_type, error_message, error_file, error_line, stack_trace, endpoint, method, ip_address)
             VALUES (?, NOW(3), 500, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $request->id() ?? uniqid('req_'),
                get_class($e),
                $e->getMessage(),
                $e->getFile(),
                $e->getLine(),
                $e->getTraceAsString(),
                $_GET['endpoint'] ?? 'unknown',
                $_SERVER['REQUEST_METHOD'] ?? 'GET',
                $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
            ]
        );
    } catch (\Throwable $dbError) {
        // Silently fail if DB insert fails
    }

    // Send error response
    $debug = $app->config('app.debug', false);

    Response::error([
        'error' => 'Internal server error',
        'message' => $debug ? $e->getMessage() : 'An unexpected error occurred.',
        'type' => $debug ? get_class($e) : null,
        'file' => $debug ? $e->getFile() : null,
        'line' => $debug ? $e->getLine() : null,
        'trace' => $debug ? explode("\n", $e->getTraceAsString()) : null,
    ], 500)->send();
}
