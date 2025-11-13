<?php
declare(strict_types=1);

/**
 * CIS Core Module Routes
 * 
 * Simple routing system for authentication and dashboard
 */

use CIS\Core\Controllers\AuthController;
use CIS\Core\Controllers\DashboardController;

// Get request URI and method
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$requestMethod = $_SERVER['REQUEST_METHOD'];

// Start session
session_start();

// Route definitions
$routes = [
    'GET' => [
        '/login' => [AuthController::class, 'showLoginForm'],
        '/logout' => [AuthController::class, 'logout'],
        '/dashboard' => [DashboardController::class, 'index'],
        '/' => [DashboardController::class, 'index']
    ],
    'POST' => [
        '/api/auth/login' => [AuthController::class, 'login']
    ]
];

// Match route
if (isset($routes[$requestMethod][$requestUri])) {
    [$controller, $method] = $routes[$requestMethod][$requestUri];
    
    $instance = new $controller();
    $instance->$method();
} else {
    // 404 Not Found
    http_response_code(404);
    echo "404 - Page not found";
}