<?php
/**
 * Payroll Module - API Router
 *
 * Handles all payroll API requests and views
 *
 * Usage:
 *   /modules/human_resources/payroll/router.php?route=/payroll/dashboard
 *   /modules/human_resources/payroll/router.php?route=/api/payroll/dashboard/data
 *
 * @package HumanResources\Payroll
 */

declare(strict_types=1);

// Load main application
require_once $_SERVER['DOCUMENT_ROOT'] . '/app.php';

// Autoload payroll classes
spl_autoload_register(function ($class) {
    // PayrollModule namespace
    if (strpos($class, 'PayrollModule\\') === 0) {
        $classPath = str_replace('PayrollModule\\', '', $class);
        $classPath = str_replace('\\', '/', $classPath);
        $file = __DIR__ . '/' . strtolower($classPath) . '.php';

        // Try lowercase path first
        if (file_exists($file)) {
            require_once $file;
            return;
        }

        // Try preserving case for class file
        $parts = explode('/', $classPath);
        $fileName = array_pop($parts);
        $path = strtolower(implode('/', $parts));
        $file = __DIR__ . '/' . $path . '/' . $fileName . '.php';

        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }

    // HumanResources namespace
    if (strpos($class, 'HumanResources\\Payroll\\') === 0) {
        $classPath = str_replace('HumanResources\\Payroll\\', '', $class);
        $classPath = str_replace('\\', '/', $classPath);
        $parts = explode('/', $classPath);
        $fileName = array_pop($parts);
        $path = strtolower(implode('/', $parts));
        $file = __DIR__ . '/' . $path . '/' . $fileName . '.php';

        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});

// Load routes
$routes = require __DIR__ . '/routes.php';

// Get requested route
$route = $_GET['route'] ?? $_SERVER['PATH_INFO'] ?? '/';
$method = $_SERVER['REQUEST_METHOD'];

// Find matching route
$matchedRoute = null;
$routeParams = [];

foreach ($routes as $pattern => $config) {
    list($routeMethod, $routePath) = explode(' ', $pattern, 2);

    if ($routeMethod !== $method) {
        continue;
    }

    // Convert route pattern to regex (handle :id parameters)
    $regex = preg_replace('/\/:([^\/]+)/', '/(?P<$1>[^/]+)', $routePath);
    $regex = '#^' . $regex . '$#';

    if (preg_match($regex, $route, $matches)) {
        $matchedRoute = $config;

        // Extract named parameters
        foreach ($matches as $key => $value) {
            if (!is_numeric($key)) {
                $routeParams[$key] = $value;
            }
        }
        break;
    }
}

// Handle 404
if (!$matchedRoute) {
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'Route not found',
        'route' => $route,
        'method' => $method
    ]);
    exit;
}

// Check authentication
if (isset($matchedRoute['auth']) && $matchedRoute['auth']) {
    if (!isset($_SESSION['user']) || empty($_SESSION['user'])) {
        if (strpos($route, '/api/') === 0) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => 'Authentication required'
            ]);
        } else {
            header('Location: /login.php?redirect=' . urlencode($route));
        }
        exit;
    }
}

// Check permissions
if (isset($matchedRoute['permission']) && $matchedRoute['permission']) {
    $hasPermission = false;

    if (isset($_SESSION['user']['permissions'])) {
        $hasPermission = in_array($matchedRoute['permission'], $_SESSION['user']['permissions']);
    }

    // Check for admin role
    if (!$hasPermission && isset($_SESSION['user']['role']) && $_SESSION['user']['role'] === 'admin') {
        $hasPermission = true;
    }

    if (!$hasPermission) {
        if (strpos($route, '/api/') === 0) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => 'Permission denied',
                'required_permission' => $matchedRoute['permission']
            ]);
        } else {
            header('Location: /403.php');
        }
        exit;
    }
}

// Check CSRF for POST/PUT/DELETE requests
if (isset($matchedRoute['csrf']) && $matchedRoute['csrf']) {
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    $sessionToken = $_SESSION['csrf_token'] ?? '';

    if (!hash_equals($sessionToken, $token)) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => 'CSRF validation failed'
        ]);
        exit;
    }
}

// Instantiate controller
$controllerClass = 'HumanResources\\Payroll\\Controllers\\' . $matchedRoute['controller'];

if (!class_exists($controllerClass)) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'Controller not found',
        'controller' => $controllerClass
    ]);
    exit;
}

// Create database connection for controller
try {
    $db = new PDO(
        "mysql:host=127.0.0.1;dbname=jcepnzzkmj;charset=utf8mb4",
        "jcepnzzkmj",
        "wprKh9Jq63",
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'Database connection failed'
    ]);
    exit;
}

// Instantiate controller and call action
try {
    $controller = new $controllerClass($db);
    $action = $matchedRoute['action'];

    if (!method_exists($controller, $action)) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => 'Action not found',
            'action' => $action
        ]);
        exit;
    }

    // Call controller action with route parameters
    if (!empty($routeParams)) {
        $controller->$action(...array_values($routeParams));
    } else {
        $controller->$action();
    }

} catch (Exception $e) {
    error_log("Payroll Router Error: " . $e->getMessage());
    error_log($e->getTraceAsString());

    http_response_code(500);

    if (strpos($route, '/api/') === 0) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => 'Internal server error',
            'message' => $e->getMessage()
        ]);
    } else {
        echo "Internal Server Error: " . htmlspecialchars($e->getMessage());
    }
}
