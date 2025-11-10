<?php
/**
 * Payroll Module - Bootstrap
 *
 * Initializes core environment settings for payroll processing
 * - Sets NZ timezone
 * - Configures error reporting
 * - Loads environment variables
 * - Includes autoloader
 * - Supports both web UI (session-based) and API (token-based) modes
 *
 * @package Payroll
 * @version 2.0.0
 * @created 2025-11-02
 * @updated 2025-11-04
 */

declare(strict_types=1);

// Set New Zealand timezone for all date/time operations
date_default_timezone_set('Pacific/Auckland');

// Enable all error reporting but don't display (log only)
error_reporting(E_ALL);
ini_set('display_errors', '1');  // 🔥 FORCE DISPLAY ERRORS ON
ini_set('display_startup_errors', '1');
ini_set('log_errors', '1');

// Detect if this is an API call (bot or other token-based access)
define('IS_API_REQUEST', (
    isset($_SERVER['HTTP_X_BOT_TOKEN']) ||
    isset($_GET['bot_token']) ||
    isset($_SERVER['HTTP_X_API_TOKEN']) ||
    isset($_GET['api_token']) ||
    (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/api/') !== false)
));

// Load environment variables
$envLoaderPath = $_SERVER['DOCUMENT_ROOT'] . '/config/env-loader.php';
if (!file_exists($envLoaderPath)) {
    // Fallback to relative path
    $envLoaderPath = dirname(__DIR__, 3) . '/config/env-loader.php';
}

if (file_exists($envLoaderPath)) {
    require_once $envLoaderPath;
} else {
    error_log('WARNING: env-loader.php not found. Environment variables may not be loaded.');
}

// Include autoloader
require_once __DIR__ . '/autoload.php';

// Define base path
if (!defined('BASE_PATH')) {
    define('BASE_PATH', realpath(__DIR__ . '/../../..') . '/');
}

// Load CIS core ONLY for web UI requests (not API)
if (!IS_API_REQUEST) {
    if (file_exists(BASE_PATH . 'app.php')) {
        require_once BASE_PATH . 'app.php';
    }
}

// Constants for payment allocation
if (!defined('STRICT_REGISTER_NAME')) {
    define('STRICT_REGISTER_NAME', 'Main Register');
}
if (!defined('STRICT_PAYMENT_TYPE_NAME')) {
    define('STRICT_PAYMENT_TYPE_NAME', 'Account Payment');
}

// Load Xero clients helper
if (!function_exists('xero_get_api_clients')) {
    /**
     * Get Xero API clients (Accounting and Payroll NZ)
     */
    function xero_get_api_clients(): array {
        require_once BASE_PATH . 'assets/functions/xeroAPI/xero-authentication.php';

        $storage = new XeroOAuth2StorageSession();
        $accountingApi = new XeroAPI\XeroPHP\Api\AccountingApi(
            new GuzzleHttp\Client(),
            xero_authentication_configuration($storage)
        );

        $payrollNzApi = new XeroAPI\XeroPHP\Api\PayrollNzApi(
            new GuzzleHttp\Client(),
            xero_authentication_configuration($storage)
        );

        $xeroTenantId = $storage->getXeroTenantId();

        return [
            'accountingApi' => $accountingApi,
            'payrollNzApi' => $payrollNzApi,
            'xeroTenantId' => $xeroTenantId,
            'storage' => $storage
        ];
    }
}

// Database connection for payroll tables
if (!function_exists('getPayrollDb')) {
    /**
     * Get PDO connection for payroll operations
     */
    function getPayrollDb(): PDO {
        // Use existing CIS database connection config
        $host = $_ENV['DB_HOST'] ?? '127.0.0.1';
        $dbname = $_ENV['DB_NAME'] ?? 'jcepnzzkmj';
        $username = $_ENV['DB_USER'] ?? 'jcepnzzkmj';
        $password = $_ENV['DB_PASS'] ?? 'wprKh9Jq63';

        $dsn = "mysql:host={$host};dbname={$dbname};charset=utf8mb4";
        $pdo = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        return $pdo;
    }
}

// Load CIS user helper
if (!function_exists('getCISUserObjectByXeroEmployeeID')) {
    /**
     * Get CIS user by Xero Employee ID
     */
    function getCISUserObjectByXeroEmployeeID(string $xeroEmployeeId): ?object {
        $db = getPayrollDb();
        $stmt = $db->prepare("
            SELECT u.*,
                   u.user_id as id,
                   u.vend_customer_account_number as vend_customer_account
            FROM users u
            WHERE u.xero_employee_id = ?
            LIMIT 1
        ");
        $stmt->execute([$xeroEmployeeId]);
        $row = $stmt->fetch();

        return $row ? (object)$row : null;
    }
}

// ============================================================================
// BOT & API AUTHENTICATION FUNCTIONS
// ============================================================================

/**
 * Validate bot token
 *
 * Supports multiple token types:
 * - Static tokens for testing/CI
 * - Daily rotating tokens for production bots
 * - Environment-based tokens
 */
if (!function_exists('payroll_validate_bot_token')) {
    function payroll_validate_bot_token(string $token): bool {
        $validTokens = [
            // Static tokens for development/testing
            'test_bot_token_12345',
            'ci_automation_token',

            // Daily rotating token (recommended for production)
            hash('sha256', 'payroll_bot_' . date('Y-m-d')),

            // Environment-based tokens (if configured)
            $_ENV['BOT_TOKEN'] ?? null,
            $_ENV['PAYROLL_BOT_TOKEN'] ?? null,
        ];

        // Remove null values
        $validTokens = array_filter($validTokens);

        return in_array($token, $validTokens, true);
    }
}

/**
 * Validate API token (for non-bot API access)
 */
if (!function_exists('payroll_validate_api_token')) {
    function payroll_validate_api_token(string $token): bool {
        $validTokens = [
            $_ENV['API_TOKEN'] ?? null,
            $_ENV['PAYROLL_API_TOKEN'] ?? null,
            hash('sha256', 'api_key_' . date('Y-m-d')),
        ];

        $validTokens = array_filter($validTokens);

        return in_array($token, $validTokens, true);
    }
}

/**
 * Require bot authentication (for bot endpoints)
 *
 * Checks for bot token in header or query string
 * Exits with 401/403 if not valid
 */
if (!function_exists('payroll_require_bot_auth')) {
    function payroll_require_bot_auth(): void {
        $botToken = $_SERVER['HTTP_X_BOT_TOKEN'] ?? $_GET['bot_token'] ?? null;

        if (!$botToken) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => 'Bot token required',
                'hint' => 'Send X-Bot-Token header or bot_token query parameter'
            ]);
            exit;
        }

        if (!payroll_validate_bot_token($botToken)) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => 'Invalid bot token'
            ]);
            exit;
        }
    }
}

/**
 * Require API authentication (for general API endpoints)
 */
if (!function_exists('payroll_require_api_auth')) {
    function payroll_require_api_auth(): void {
        $apiToken = $_SERVER['HTTP_X_API_TOKEN'] ?? $_GET['api_token'] ?? null;

        if (!$apiToken) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => 'API token required',
                'hint' => 'Send X-API-Token header or api_token query parameter'
            ]);
            exit;
        }

        if (!payroll_validate_api_token($apiToken)) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => 'Invalid API token'
            ]);
            exit;
        }
    }
}

/**
 * Require either bot OR API authentication (flexible endpoints)
 */
if (!function_exists('payroll_require_token_auth')) {
    function payroll_require_token_auth(): void {
        $botToken = $_SERVER['HTTP_X_BOT_TOKEN'] ?? $_GET['bot_token'] ?? null;
        $apiToken = $_SERVER['HTTP_X_API_TOKEN'] ?? $_GET['api_token'] ?? null;

        if (!$botToken && !$apiToken) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => 'Authentication token required',
                'hint' => 'Send X-Bot-Token, X-API-Token, or respective query parameters'
            ]);
            exit;
        }

        $valid = false;
        if ($botToken && payroll_validate_bot_token($botToken)) {
            $valid = true;
        }
        if ($apiToken && payroll_validate_api_token($apiToken)) {
            $valid = true;
        }

        if (!$valid) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => 'Invalid authentication token'
            ]);
            exit;
        }
    }
}

// ============================================================================
// JSON RESPONSE HELPERS
// ============================================================================

/**
 * Send JSON success response
 */
if (!function_exists('payroll_json_success')) {
    function payroll_json_success($data = null, string $message = null, int $code = 200): void {
        http_response_code($code);
        header('Content-Type: application/json');

        $response = ['success' => true];
        if ($data !== null) {
            $response['data'] = $data;
        }
        if ($message !== null) {
            $response['message'] = $message;
        }
        $response['timestamp'] = date('Y-m-d H:i:s');

        echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

/**
 * Send JSON error response
 */
if (!function_exists('payroll_json_error')) {
    function payroll_json_error(string $error, int $code = 400, array $details = []): void {
        http_response_code($code);
        header('Content-Type: application/json');

        $response = [
            'success' => false,
            'error' => $error,
            'timestamp' => date('Y-m-d H:i:s')
        ];

        if (!empty($details)) {
            $response['details'] = $details;
        }

        echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
