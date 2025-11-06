<?php
/**
 * E-Commerce Operations Module - Bootstrap
 *
 * Initializes the module, loads dependencies, and sets up autoloading.
 *
 * @package CIS\Modules\EcommerceOps
 * @version 1.0.0
 */

declare(strict_types=1);

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Define module constants
define('ECOMMERCE_OPS_ROOT', __DIR__);
define('ECOMMERCE_OPS_VERSION', '1.0.0');

// Load environment variables from .env file
if (file_exists(ECOMMERCE_OPS_ROOT . '/.env')) {
    $envFile = file_get_contents(ECOMMERCE_OPS_ROOT . '/.env');
    $lines = explode("\n", $envFile);

    foreach ($lines as $line) {
        $line = trim($line);

        // Skip comments and empty lines
        if (empty($line) || strpos($line, '#') === 0) {
            continue;
        }

        // Parse KEY=VALUE
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            // Set as environment variable if not already set
            if (!getenv($key)) {
                putenv("$key=$value");
            }
        }
    }
}

// Load existing CIS config
require_once $_SERVER['DOCUMENT_ROOT'] . '/assets/functions/config.php';

// Autoloader for service classes
spl_autoload_register(function ($class) {
    // Only autoload classes in our namespace
    if (strpos($class, 'CIS\\Modules\\EcommerceOps\\') !== 0) {
        return;
    }

    // Convert namespace to file path
    $classPath = str_replace('CIS\\Modules\\EcommerceOps\\', '', $class);
    $classPath = str_replace('\\', '/', $classPath);

    // Try to load from lib/ directory
    $file = ECOMMERCE_OPS_ROOT . '/lib/' . $classPath . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});

/**
 * Get environment variable with fallback
 *
 * @param string $key
 * @param mixed $default
 * @return mixed
 */
function ecomm_env(string $key, $default = null) {
    $value = getenv($key);
    return $value !== false ? $value : $default;
}

/**
 * Check if user is authenticated and has required permissions
 *
 * @return bool
 */
function ecomm_check_auth(): bool {
    if (!isset($_SESSION['userID']) || empty($_SESSION['userID'])) {
        return false;
    }

    // Additional permission checks can be added here
    return true;
}

/**
 * Require authentication - redirect if not logged in
 *
 * @return void
 */
function ecomm_require_auth(): void {
    if (!ecomm_check_auth()) {
        header('Location: /login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
        exit;
    }
}

/**
 * JSON response helper
 *
 * @param bool $success
 * @param mixed $data
 * @param string|null $message
 * @return void
 */
function ecomm_json_response(bool $success, $data = null, ?string $message = null): void {
    header('Content-Type: application/json');

    $response = [
        'success' => $success,
        'timestamp' => date('c'),
    ];

    if ($message !== null) {
        $response['message'] = $message;
    }

    if ($data !== null) {
        $response['data'] = $data;
    }

    echo json_encode($response, JSON_PRETTY_PRINT);
    exit;
}

/**
 * Log error to file
 *
 * @param string $message
 * @param array $context
 * @return void
 */
function ecomm_log_error(string $message, array $context = []): void {
    $logFile = ECOMMERCE_OPS_ROOT . '/logs/error.log';
    $logDir = dirname($logFile);

    // Create log directory if it doesn't exist
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }

    $timestamp = date('Y-m-d H:i:s');
    $userId = $_SESSION['userID'] ?? 'guest';
    $contextStr = !empty($context) ? json_encode($context) : '';

    $logEntry = "[$timestamp] [$userId] $message $contextStr\n";

    file_put_contents($logFile, $logEntry, FILE_APPEND);
}

// Module loaded successfully
if (ecomm_env('DEBUG_MODE', 'false') === 'true') {
    ecomm_log_error('E-Commerce Operations Module loaded', [
        'version' => ECOMMERCE_OPS_VERSION,
        'user' => $_SESSION['userID'] ?? null
    ]);
}
