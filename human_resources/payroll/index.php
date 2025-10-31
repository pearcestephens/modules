<?php
declare(strict_types=1);

/**
 * Payroll Module - Single Entry Point
 *
 * Handles ALL requests to the payroll module:
 * - Views: /modules/human_resources/payroll/?view=dashboard
 * - API: /modules/human_resources/payroll/?api=dashboard/data
 *
 * Clean URL structure with single index.php dispatcher
 *
 * @package HumanResources\Payroll
 * @version 2.0.0
 */

// Enable error display for development
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

// ============================================================================
// BOOTSTRAP - DEDICATED (NO APP.PHP)
// ============================================================================

// Use PHPSESSID (default session name - same as main CIS)
if (session_status() === PHP_SESSION_NONE) {
    session_name('PHPSESSID');
}

// Session configuration
ini_set('session.use_only_cookies', '1');
ini_set('session.use_strict_mode', '1');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.cookie_secure', '1');
ini_set('session.cookie_lifetime', '0');
ini_set('session.gc_maxlifetime', '79200');

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Regenerate CSRF token periodically (every 30 minutes)
if (empty($_SESSION['csrf_token_time']) || (time() - $_SESSION['csrf_token_time']) > 1800) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    $_SESSION['csrf_token_time'] = time();
}

// Module base path
define('PAYROLL_MODULE_PATH', __DIR__);

// Autoloader for payroll classes
spl_autoload_register(function ($class) {
    $namespaces = [
        'HumanResources\\Payroll\\' => PAYROLL_MODULE_PATH . '/',
        'PayrollModule\\' => PAYROLL_MODULE_PATH . '/'
    ];

    foreach ($namespaces as $prefix => $baseDir) {
        if (strpos($class, $prefix) === 0) {
            $relativeClass = substr($class, strlen($prefix));
            $parts = explode('\\', $relativeClass);
            $fileName = array_pop($parts);
            $path = strtolower(implode('/', $parts));

            $file = $baseDir . ($path ? $path . '/' : '') . $fileName . '.php';

            if (file_exists($file)) {
                require_once $file;
                return true;
            }
        }
    }

    return false;
});

// ============================================================================
// DATABASE CONNECTION
// ============================================================================

function getPayrollDb(): PDO {
    static $db = null;

    if ($db === null) {
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
            error_log("Payroll DB Error: " . $e->getMessage());
            http_response_code(500);

            if (payroll_is_api_request()) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Database connection failed']);
            } else {
                echo "Database connection failed. Please try again later.";
            }
            exit;
        }
    }

    return $db;
}

// ============================================================================
// HELPER FUNCTIONS (Namespaced to avoid conflicts)
// ============================================================================

/**
 * Check if current request is an API request
 */
function payroll_is_api_request(): bool {
    return isset($_GET['api']) || strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false;
}

/**
 * Get current logged-in user (with bot bypass support)
 * ALIGNED WITH MAIN CIS SESSION STRUCTURE
 */
function payroll_get_current_user(): ?array {
    // Check for bot bypass token in headers or query string
    $botToken = $_SERVER['HTTP_X_BOT_TOKEN'] ?? $_GET['bot_token'] ?? null;

    if ($botToken && payroll_validate_bot_token($botToken)) {
        // Return synthetic bot user with full permissions
        return [
            'id' => 0,
            'email' => 'bot@system.internal',
            'name' => 'System Bot',
            'role' => 'admin',
            'permissions' => ['*'], // All permissions
            'is_bot' => true
        ];
    }

    // Check main CIS session structure (CORRECTED)
    // Main CIS uses: $_SESSION['userID'], $_SESSION['username'], $_SESSION['authenticated']
    if (!empty($_SESSION['userID']) && !empty($_SESSION['authenticated'])) {
        return [
            'id' => (int)$_SESSION['userID'],
            'email' => $_SESSION['username'] ?? '',
            'name' => $_SESSION['username'] ?? 'User',
            'role' => $_SESSION['role'] ?? 'staff',
            'permissions' => $_SESSION['permissions'] ?? [],
            'is_bot' => false
        ];
    }

    return null;
}

/**
 * Validate bot authentication token
 */
function payroll_validate_bot_token(string $token): bool {
    // Define valid bot tokens (should be in environment variables in production)
    $validTokens = [
        'test_bot_token_12345',
        'ci_automation_token',
        hash('sha256', 'payroll_bot_' . date('Y-m-d')) // Daily rotating token
    ];

    return in_array($token, $validTokens, true);
}

/**
 * Check if user has specific permission
 */
function payroll_has_permission(string $permission): bool {
    $user = payroll_get_current_user();

    if (!$user) {
        return false;
    }

    // Admin has all permissions
    if (($user['role'] ?? '') === 'admin') {
        return true;
    }

    // Check specific permission
    return in_array($permission, $user['permissions'] ?? []);
}

/**
 * Require authentication - redirect/error if not logged in
 */
function payroll_require_auth(): void {
    if (!payroll_get_current_user()) {
        if (payroll_is_api_request()) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Authentication required']);
        } else {
            header('Location: /login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
        }
        exit;
    }
}

/**
 * Require specific permission - 403 if denied
 */
function payroll_require_permission(string $permission): void {
    payroll_require_auth();

    if (!payroll_has_permission($permission)) {
        if (payroll_is_api_request()) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Permission denied', 'required' => $permission]);
        } else {
            header('Location: /403.php');
        }
        exit;
    }
}

/**
 * Validate CSRF token for POST requests
 */
function payroll_validate_csrf(): bool {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        $sessionToken = $_SESSION['csrf_token'] ?? '';

        return hash_equals($sessionToken, $token);
    }

    return true;
}

// ============================================================================
// STATIC ASSET HANDLING - SERVE DIRECTLY, DO NOT ROUTE
// ============================================================================

$requestUri = $_SERVER['REQUEST_URI'] ?? '';
$cleanUri = preg_replace('/\?.*$/', '', $requestUri);

// Check if this is a static asset request (CSS, JS, images, fonts, etc.)
if (preg_match('/\.(css|js|png|jpg|jpeg|gif|svg|ico|woff|woff2|ttf|eot|map)$/i', $cleanUri)) {
    // Extract the file path from the URI
    // Remove the base path (/modules/human_resources/payroll) from the URI
    $moduleBasePath = '/modules/human_resources/payroll';

    // If URI starts with module base path, remove it
    if (strpos($cleanUri, $moduleBasePath) === 0) {
        $relativeFilePath = substr($cleanUri, strlen($moduleBasePath));
    } else {
        // Fallback: just use the clean URI as-is
        $relativeFilePath = $cleanUri;
    }

    $filePath = __DIR__ . $relativeFilePath;

    if (file_exists($filePath) && is_file($filePath)) {
        // Determine MIME type
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $mimeTypes = [
            'css' => 'text/css',
            'js' => 'application/javascript',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            'ico' => 'image/x-icon',
            'woff' => 'font/woff',
            'woff2' => 'font/woff2',
            'ttf' => 'font/ttf',
            'eot' => 'application/vnd.ms-fontobject',
            'map' => 'application/json'
        ];

        $mimeType = $mimeTypes[$extension] ?? 'application/octet-stream';

        // Set appropriate headers
        header('Content-Type: ' . $mimeType);
        header('Content-Length: ' . filesize($filePath));
        header('Cache-Control: public, max-age=31536000'); // 1 year cache
        header('X-Content-Type-Options: nosniff');

        // Output file
        readfile($filePath);
        exit;
    } else {
        // Asset not found
        http_response_code(404);
        header('Content-Type: text/plain');
        echo "Asset not found: " . htmlspecialchars($relativeFilePath);
        exit;
    }
}

// ============================================================================
// ROUTING LOGIC
// ============================================================================

// Load routes configuration
$routes = require __DIR__ . '/routes.php';

// Determine request type and path from multiple sources
// Supports: ?api=path, ?view=path, or clean URLs via htaccess
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$isApi = isset($_GET['api']);
$isView = isset($_GET['view']);

// Build route string - supports both query params and clean URLs
if ($isApi) {
    // API request via ?api=dashboard/data
    $route = $method . ' /api/payroll/' . trim($_GET['api'], '/');
} elseif ($isView) {
    // View request via ?view=dashboard
    $route = 'GET /payroll/' . trim($_GET['view'], '/');
} elseif (isset($_GET['route'])) {
    // Direct route param (for testing or explicit routing)
    $route = $method . ' ' . trim($_GET['route'], '/');
} else {
    // Check if this is a direct file access or clean URL
    $requestUri = $_SERVER['REQUEST_URI'] ?? '';
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';

    // Remove query string
    $cleanUri = preg_replace('/\?.*$/', '', $requestUri);

    // Get the module path
    $modulePath = dirname($scriptName);

    // If accessing index.php directly or the directory, show dashboard
    if (strpos($cleanUri, 'index.php') !== false ||
        $cleanUri === $modulePath . '/' ||
        $cleanUri === $modulePath ||
        $cleanUri === rtrim($modulePath, '/')) {
        $route = 'GET /payroll/dashboard';
    } else {
        // Try to extract route from URI (for future htaccess support)
        $path = str_replace($modulePath, '', $cleanUri);
        $route = $method . ' ' . $path;
    }
}

// Find matching route
$matchedRoute = null;
$routeParams = [];

// Extract method and path from built route
list($routeMethod, $routePath) = explode(' ', $route, 2);

foreach ($routes as $pattern => $config) {
    list($patternMethod, $patternPath) = explode(' ', $pattern, 2);

    // Check method matches
    if ($patternMethod !== $routeMethod) {
        continue;
    }

    // Convert route pattern to regex (handle :id parameters)
    $regex = preg_replace('/\/:([^\/]+)/', '/(?P<$1>[^/]+)', $patternPath);
    $regex = '#^' . $regex . '$#';

    if (preg_match($regex, $routePath, $matches)) {
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

    // Log for debugging
    error_log("Payroll 404: Route '$route' not found");
    error_log("REQUEST_URI: " . ($_SERVER['REQUEST_URI'] ?? 'none'));
    error_log("SCRIPT_NAME: " . ($_SERVER['SCRIPT_NAME'] ?? 'none'));

    if (payroll_is_api_request()) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => 'Route not found',
            'route' => $route,
            'request_uri' => $_SERVER['REQUEST_URI'] ?? '',
            'script_name' => $_SERVER['SCRIPT_NAME'] ?? '',
            'available' => array_keys($routes)
        ]);
    } else {
        // Check if 404.php exists, otherwise show inline error
        $errorPage = __DIR__ . '/views/errors/404.php';
        if (file_exists($errorPage)) {
            require $errorPage;
        } else {
            echo "<!DOCTYPE html><html><head><title>404 Not Found</title></head><body>";
            echo "<h1>404 - Route Not Found</h1>";
            echo "<p>The requested route could not be found.</p>";
            echo "<p><strong>Route:</strong> " . htmlspecialchars($route) . "</p>";
            echo "<p><strong>Request URI:</strong> " . htmlspecialchars($_SERVER['REQUEST_URI'] ?? '') . "</p>";
            echo "<p><a href='/modules/human_resources/payroll/?view=dashboard'>Go to Dashboard</a></p>";
            if (isset($_SESSION['user']) && ($_SESSION['user']['role'] ?? '') === 'admin') {
                echo "<details><summary>Debug Info (Admin Only)</summary><pre>";
                echo "Available Routes:\n";
                foreach (array_keys($routes) as $r) {
                    echo "  " . htmlspecialchars($r) . "\n";
                }
                echo "</pre></details>";
            }
            echo "</body></html>";
        }
    }
    exit;
}

// ============================================================================
// SECURITY CHECKS
// ============================================================================

// Check authentication
if (isset($matchedRoute['auth']) && $matchedRoute['auth']) {
    payroll_require_auth();
}

// Check permissions
if (isset($matchedRoute['permission']) && $matchedRoute['permission']) {
    payroll_require_permission($matchedRoute['permission']);
}

// Check CSRF for POST/PUT/DELETE
if (isset($matchedRoute['csrf']) && $matchedRoute['csrf']) {
    if (!payroll_validate_csrf()) {
        http_response_code(403);

        if (payroll_is_api_request()) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'CSRF validation failed']);
        } else {
            echo "CSRF validation failed";
        }
        exit;
    }
}

// ============================================================================
// CONTROLLER DISPATCH
// ============================================================================

try {
    $controllerClass = 'HumanResources\\Payroll\\Controllers\\' . $matchedRoute['controller'];

    if (!class_exists($controllerClass)) {
        throw new Exception("Controller not found: {$controllerClass}");
    }

    // Instantiate controller (no constructor parameters needed)
    $controller = new $controllerClass();
    $action = $matchedRoute['action'];

    if (!method_exists($controller, $action)) {
        throw new Exception("Action not found: {$action}");
    }

    // Call controller action with route parameters
    if (!empty($routeParams)) {
        $controller->$action(...array_values($routeParams));
    } else {
        $controller->$action();
    }

} catch (Exception $e) {
    error_log("Payroll Module Error: " . $e->getMessage());
    error_log($e->getTraceAsString());

    http_response_code(500);

    if (payroll_is_api_request()) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => 'Internal server error',
            'message' => $_ENV['APP_ENV'] === 'development' ? $e->getMessage() : null
        ]);
    } else {
        require __DIR__ . '/views/errors/500.php';
    }
}
