<?php
declare(strict_types=1);

namespace Modules\Base;

final class Router
{
    /** @var array<string,array<string,array{0:string,1:string}>> */
    private array $routes = [];

    public function add(string $method, string $path, string $class, string $action): void
    {
        $method = strtoupper($method);
        $this->routes[$method][$path] = [$class, $action];
    }

    public function dispatch(string $basePath = '/modules/consignments'): void
    {
        $requestUri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($requestUri, PHP_URL_PATH) ?: '/';
        $path = (string)substr($path, strlen($basePath));
        if ($path === '' || $path === false) {
            $path = '/';
        }
        // Normalize by trimming trailing slash (but keep root '/')
        if ($path !== '/' && str_ends_with($path, '/')) {
            $path = rtrim($path, '/');
        }
        $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));

        if (!isset($this->routes[$method])) {
            http_response_code(405);
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'error' => 'Method Not Allowed']);
            return;
        }
        if (!isset($this->routes[$method][$path])) {
            http_response_code(404);
            header('Content-Type: text/html; charset=UTF-8');
            echo '<h1>404 Not Found</h1>';
            return;
        }

        [$class, $action] = $this->routes[$method][$path];
        $controller = new $class();
        $response = $controller->$action();

        if (is_array($response)) {
            header('Content-Type: application/json');
            echo json_encode($response);
        } elseif (is_string($response)) {
            echo $response;
        }
    }
}
