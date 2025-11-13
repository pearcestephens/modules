<?php
declare(strict_types=1);

/**
 * Bank Transactions Module - Standalone Bootstrap
 *
 * COMPLETELY SELF-CONTAINED - NO CIS DEPENDENCIES
 * ================================================
 *
 * This bootstrap initializes the bank-transactions module with zero dependencies
 * on the legacy CIS system. It includes:
 *
 * - PDO-only database connection (no MySQLi)
 * - Composer autoloader (PSR-4)
 * - Configuration management
 * - Session handling
 * - Error/Exception handling
 * - Logging
 * - Authentication helpers
 *
 * @package BankTransactions
 * @version 2.0.0 (Self-Contained)
 * @date 2025-11-13
 */

// ============================================================================
// PREVENT REDUNDANT INCLUSION
// ============================================================================

if (defined('BANK_TRANSACTIONS_BOOTSTRAP_LOADED')) {
    return;
}
define('BANK_TRANSACTIONS_BOOTSTRAP_LOADED', true);

// ============================================================================
// SECURITY HARDENING - IMMEDIATE
// ============================================================================

// Set secure session cookie parameters BEFORE session_start()
ini_set('session.cookie_httponly', '1');      // No JavaScript access
ini_set('session.cookie_secure', '1');         // HTTPS only
ini_set('session.cookie_samesite', 'Strict');  // CSRF protection
ini_set('session.use_only_cookies', '1');      // No URL-based sessions
ini_set('session.use_strict_mode', '1');       // Strict mode enabled

// Disable error output to browsers (log instead)
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// Set DOCUMENT_ROOT for CLI compatibility
if (!isset($_SERVER['DOCUMENT_ROOT']) || empty($_SERVER['DOCUMENT_ROOT'])) {
    $_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__, 2);
}

// ============================================================================
// 1. ENVIRONMENT & CONFIGURATION
// ============================================================================

// Detect environment
$environment = $_ENV['ENVIRONMENT'] ?? $_SERVER['APP_ENV'] ?? 'production';
define('APP_ENV', $environment);
define('APP_DEBUG', $environment !== 'production');

// Load .env file if exists
$envFile = dirname(__DIR__, 2) . '/.env';
if (file_exists($envFile)) {
    $envLines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($envLines as $line) {
        // Skip comments
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        // Parse KEY=VALUE
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value, '\'"');
            if (!isset($_ENV[$key])) {
                $_ENV[$key] = $value;
            }
        }
    }
}

// Database configuration
define('DB_HOST', $_ENV['DB_HOST'] ?? '127.0.0.1');
define('DB_PORT', (int)($_ENV['DB_PORT'] ?? 3306));
define('DB_NAME', $_ENV['DB_NAME'] ?? 'jcepnzzkmj');
define('DB_USER', $_ENV['DB_USER'] ?? 'jcepnzzkmj');
define('DB_PASS', $_ENV['DB_PASSWORD'] ?? '');
define('DB_CHARSET', 'utf8mb4');

// Module configuration
define('BANK_TRANSACTIONS_VERSION', '2.0.0');
define('BANK_TRANSACTIONS_PATH', __DIR__);
define('BANK_TRANSACTIONS_MODULE_PATH', __DIR__);
define('BANK_TRANSACTIONS_MODULE_URL', '/modules/bank-transactions');
define('BANK_TRANSACTIONS_NAMESPACE', 'BankTransactions');
define('BANK_TRANSACTIONS_CONFIDENCE_THRESHOLD', 200);
define('BANK_TRANSACTIONS_CONFIDENCE_MARGIN', 60);

// ============================================================================
// 2. COMPOSER AUTOLOADER (PSR-4)
// ============================================================================

$vendorAutoload = dirname(__DIR__, 2) . '/vendor/autoload.php';
if (file_exists($vendorAutoload)) {
    require_once $vendorAutoload;
} else {
    throw new RuntimeException('Composer autoloader not found at: ' . $vendorAutoload);
}

// ============================================================================
// 3. NAMESPACE SETUP & CUSTOM AUTOLOADER
// ============================================================================

/**
 * Simple PSR-4 autoloader for BankTransactions namespace
 * Maps: BankTransactions\Lib\* to lib/*
 *       BankTransactions\Models\* to models/*
 *       BankTransactions\Controllers\* to controllers/*
 */
spl_autoload_register(function ($class) {
    $prefix = 'BankTransactions\\';

    if (strpos($class, $prefix) !== 0) {
        return; // Not our class
    }

    $relative = substr($class, strlen($prefix));
    $path = __DIR__ . '/' . str_replace('\\', '/', $relative) . '.php';

    if (file_exists($path)) {
        require_once $path;
    }
});

// ============================================================================
// 4. PDO DATABASE CONNECTION (SINGLETON)
// ============================================================================

class DatabaseConnection {
    private static ?PDO $connection = null;

    public static function getInstance(): PDO {
        if (self::$connection === null) {
            try {
                $dsn = sprintf(
                    'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                    DB_HOST,
                    DB_PORT,
                    DB_NAME,
                    DB_CHARSET
                );

                self::$connection = new PDO(
                    $dsn,
                    DB_USER,
                    DB_PASS,
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES => false,
                        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
                    ]
                );

                error_log('[Bank Transactions] PDO connection established successfully');
            } catch (PDOException $e) {
                error_log('[Bank Transactions] PDO connection failed: ' . $e->getMessage());
                throw new RuntimeException('Database connection failed: ' . $e->getMessage());
            }
        }

        return self::$connection;
    }

    public static function query(string $sql, array $params = []): PDOStatement {
        $conn = self::getInstance();
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public static function fetch(string $sql, array $params = []): ?array {
        return self::query($sql, $params)->fetch();
    }

    public static function fetchAll(string $sql, array $params = []): array {
        return self::query($sql, $params)->fetchAll();
    }
}

// ============================================================================
// 5. SESSION MANAGEMENT
// ============================================================================

class SessionManager {
    private static bool $started = false;

    public static function start(): void {
        if (self::$started || PHP_SAPI === 'cli') {
            return;
        }

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
            self::$started = true;
            error_log('[Bank Transactions] Session started');
        }
    }

    public static function get(string $key, $default = null) {
        self::start();
        return $_SESSION[$key] ?? $default;
    }

    public static function set(string $key, $value): void {
        self::start();
        $_SESSION[$key] = $value;
    }

    public static function has(string $key): bool {
        self::start();
        return isset($_SESSION[$key]);
    }

    public static function destroy(): void {
        self::start();
        session_destroy();
    }
}

// ============================================================================
// 6. AUTHENTICATION HELPERS
// ============================================================================

class AuthHelper {
    public static function isAuthenticated(): bool {
        return SessionManager::has('user_id') && SessionManager::has('user');
    }

    public static function requireAuth(): void {
        if (!self::isAuthenticated()) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => 'Authentication required',
                'code' => 'AUTH_REQUIRED'
            ]);
            exit(1);
        }
    }

    public static function getCurrentUser(): ?array {
        return SessionManager::get('user');
    }

    public static function getCurrentUserId(): ?int {
        return SessionManager::get('user_id');
    }

    public static function login(int $userId, array $userData): void {
        SessionManager::set('user_id', $userId);
        SessionManager::set('user', $userData);
        error_log("[Bank Transactions] User $userId logged in");
    }

    public static function logout(): void {
        $userId = self::getCurrentUserId();
        SessionManager::destroy();
        error_log("[Bank Transactions] User $userId logged out");
    }
}

// ============================================================================
// 7. ERROR & EXCEPTION HANDLING
// ============================================================================

class ErrorHandler {
    public static function handle(Throwable $throwable): void {
        $code = $throwable->getCode() ?: 500;
        $message = $throwable->getMessage();
        $trace = $throwable->getTraceAsString();

        // Log error
        error_log("[Bank Transactions Error] [$code] $message\n$trace");

        // Send JSON response for API requests
        if (self::isJsonRequest()) {
            http_response_code($code);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => $message,
                'code' => $code,
                'debug' => APP_DEBUG ? $trace : null
            ]);
        } else {
            // HTML response
            http_response_code($code);
            echo self::renderErrorPage($code, $message, $trace);
        }

        exit(1);
    }

    private static function isJsonRequest(): bool {
        return stripos($_SERVER['HTTP_ACCEPT'] ?? 'text/html', 'application/json') !== false
            || stripos($_SERVER['CONTENT_TYPE'] ?? 'text/html', 'application/json') !== false;
    }

    private static function renderErrorPage(int $code, string $message, string $trace): string {
        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <title>Error $code</title>
    <style>
        body { font-family: sans-serif; background: #f5f5f5; margin: 0; padding: 20px; }
        .error-container { max-width: 600px; margin: 50px auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1 { color: #d32f2f; margin-top: 0; }
        .error-code { color: #999; font-size: 0.9em; }
        .error-message { color: #333; margin: 20px 0; }
        .error-trace { background: #f5f5f5; padding: 15px; border-left: 3px solid #d32f2f; font-family: monospace; font-size: 0.85em; overflow-x: auto; }
    </style>
</head>
<body>
    <div class="error-container">
        <h1>Error <span class="error-code">$code</span></h1>
        <p class="error-message">$message</p>
        {debug_trace}
    </div>
</body>
</html>
HTML;
    }
}

// Register error handler
set_exception_handler([ErrorHandler::class, 'handle']);
set_error_handler(function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

// ============================================================================
// 8. LOGGING HELPER
// ============================================================================

class Logger {
    public static function info(string $message): void {
        error_log("[Bank Transactions] [INFO] $message");
    }

    public static function warning(string $message): void {
        error_log("[Bank Transactions] [WARNING] $message");
    }

    public static function error(string $message): void {
        error_log("[Bank Transactions] [ERROR] $message");
    }

    public static function debug(string $message): void {
        if (APP_DEBUG) {
            error_log("[Bank Transactions] [DEBUG] $message");
        }
    }
}

// ============================================================================
// 9. JSON RESPONSE HELPER
// ============================================================================

class JsonResponse {
    public static function success($data = null, string $message = 'Success', int $code = 200): void {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => $message,
            'data' => $data
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    public static function error(string $message, int $code = 400, $data = null): void {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => $message,
            'code' => $code,
            'data' => $data
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}

// ============================================================================
// 10. INITIALIZATION
// ============================================================================

try {
    // Start session
    SessionManager::start();

    // Test database connection
    DatabaseConnection::getInstance();

    // Log bootstrap completion
    Logger::info('Bank Transactions Module bootstrapped successfully (v' . BANK_TRANSACTIONS_VERSION . ')');

} catch (Throwable $e) {
    error_log('[Bank Transactions] Bootstrap failed: ' . $e->getMessage());
    throw $e;
}

// ============================================================================
// EXPORT PUBLIC API
// ============================================================================

// Make these available globally
$GLOBALS['bt_db'] = DatabaseConnection::class;
$GLOBALS['bt_auth'] = AuthHelper::class;
$GLOBALS['bt_session'] = SessionManager::class;
$GLOBALS['bt_logger'] = Logger::class;
$GLOBALS['bt_json'] = JsonResponse::class;
