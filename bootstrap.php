<?php
/**
 * Modules Bootstrap - Lightweight Module Initialization
 * 
 * This file loads ONLY what modules need, without pulling in the entire CIS application.
 * Include this ONCE at your module entry point (e.g., modules/consignments/index.php)
 * 
 * What it loads:
 * - Essential constants (HTTPS_URL, paths, etc.)
 * - Session management
 * - Database connection
 * - Core utility functions (minimal set)
 * - Error handling
 * - Authentication check
 * 
 * What it does NOT load:
 * - Main CIS routing
 * - Heavy CIS functions
 * - Legacy template system
 * - Unnecessary global state
 * 
 * Usage:
 *   require_once __DIR__ . '/bootstrap.php';
 * 
 * @package Modules
 * @version 1.0.0
 */

declare(strict_types=1);

// Prevent direct access
if (basename($_SERVER['SCRIPT_FILENAME']) === 'bootstrap.php') {
    http_response_code(403);
    exit('Direct access to bootstrap file is forbidden');
}

// ============================================================================
// 1. DEFINE CONSTANTS
// ============================================================================

if (!defined('MODULES_ROOT')) {
    define('MODULES_ROOT', __DIR__);
}

if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}

// Load essential constants from main CIS (if available)
$constantsFile = APP_ROOT . '/assets/functions/constants.php';
if (file_exists($constantsFile)) {
    require_once $constantsFile;
}

// Fallback constants if main constants.php not loaded
if (!defined('HTTPS_URL')) {
    // Auto-detect or use environment variable
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'staff.vapeshed.co.nz';
    define('HTTPS_URL', $protocol . '://' . $host . '/');
}

if (!defined('SITE_URL')) {
    define('SITE_URL', HTTPS_URL);
}

// ============================================================================
// 2. ERROR HANDLING
// ============================================================================

// Set error reporting (production vs development)
$isProduction = ($_SERVER['SERVER_NAME'] ?? '') === 'staff.vapeshed.co.nz';

if ($isProduction) {
    error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
}

// Set error log location
ini_set('error_log', APP_ROOT . '/logs/modules-error.log');

// Custom error handler (optional - can be expanded)
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    if (!(error_reporting() & $errno)) {
        return false;
    }
    
    $errorType = match($errno) {
        E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR => 'ERROR',
        E_WARNING, E_CORE_WARNING, E_COMPILE_WARNING, E_USER_WARNING => 'WARNING',
        E_NOTICE, E_USER_NOTICE => 'NOTICE',
        E_DEPRECATED, E_USER_DEPRECATED => 'DEPRECATED',
        default => 'UNKNOWN'
    };
    
    error_log("[{$errorType}] {$errstr} in {$errfile} on line {$errline}");
    
    return true;
});

// ============================================================================
// 3. SESSION MANAGEMENT
// ============================================================================

if (session_status() === PHP_SESSION_NONE) {
    // Session configuration
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_secure', $isProduction ? '1' : '0');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_samesite', 'Lax');
    
    session_name('CIS_SESSION');
    session_start();
}

// Regenerate session ID periodically (security)
if (!isset($_SESSION['last_regeneration'])) {
    $_SESSION['last_regeneration'] = time();
} elseif (time() - $_SESSION['last_regeneration'] > 1800) { // 30 minutes
    session_regenerate_id(true);
    $_SESSION['last_regeneration'] = time();
}

// Generate CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ============================================================================
// 4. DATABASE CONNECTION
// ============================================================================

// Load database configuration
$dbConfigFile = APP_ROOT . '/assets/functions/db_config.php';
if (file_exists($dbConfigFile)) {
    require_once $dbConfigFile;
}

// Establish database connection (if not already connected)
if (!isset($GLOBALS['db_connection']) && defined('DB_HOST')) {
    try {
        $GLOBALS['db_connection'] = new mysqli(
            DB_HOST,
            DB_USER,
            DB_PASS,
            DB_NAME
        );
        
        if ($GLOBALS['db_connection']->connect_error) {
            throw new Exception('Database connection failed: ' . $GLOBALS['db_connection']->connect_error);
        }
        
        $GLOBALS['db_connection']->set_charset('utf8mb4');
        
    } catch (Exception $e) {
        error_log('Database connection error: ' . $e->getMessage());
        
        if (!$isProduction) {
            die('Database connection failed. Check error logs.');
        } else {
            http_response_code(503);
            die('Service temporarily unavailable. Please try again later.');
        }
    }
}

// ============================================================================
// 5. LOAD ESSENTIAL FUNCTIONS
// ============================================================================

/**
 * Load a function file from assets/functions/ if it exists
 * 
 * @param string $filename Filename without path (e.g., 'user.php')
 * @return bool True if loaded, false if not found
 */
function load_function_file(string $filename): bool
{
    static $loaded = [];
    
    if (isset($loaded[$filename])) {
        return true;
    }
    
    $filePath = APP_ROOT . '/assets/functions/' . $filename;
    
    if (file_exists($filePath)) {
        require_once $filePath;
        $loaded[$filename] = true;
        return true;
    }
    
    return false;
}

// Load essential function files (in order of dependency)
$essentialFunctions = [
    'db.php',           // Database query wrappers
    'security.php',     // Security functions (if exists)
    'user.php',         // User functions (getUserInformation, etc.)
    'permissions.php',  // Permission checking
    'navigation.php',   // Navigation menu functions
    'notifications.php', // User notifications
];

foreach ($essentialFunctions as $functionFile) {
    load_function_file($functionFile);
}

// ============================================================================
// 6. AUTHENTICATION CHECK
// ============================================================================

/**
 * Check if user is authenticated
 * 
 * @return bool True if authenticated
 */
function is_authenticated(): bool
{
    return isset($_SESSION['userID']) && is_numeric($_SESSION['userID']) && $_SESSION['userID'] > 0;
}

/**
 * Require authentication - redirect to login if not authenticated
 * 
 * @param string|null $redirectTo URL to redirect to after login
 * @return void
 */
function require_authentication(?string $redirectTo = null): void
{
    if (!is_authenticated()) {
        $redirectTo = $redirectTo ?? $_SERVER['REQUEST_URI'] ?? '/';
        $loginUrl = HTTPS_URL . 'login.php?redirect=' . urlencode($redirectTo);
        
        header('Location: ' . $loginUrl);
        exit;
    }
}

// Check authentication by default (can be overridden by setting SKIP_AUTH_CHECK before including bootstrap)
if (!defined('SKIP_AUTH_CHECK') || SKIP_AUTH_CHECK !== true) {
    require_authentication();
}

// ============================================================================
// 7. HELPER FUNCTIONS
// ============================================================================

/**
 * Get authenticated user ID
 * 
 * @return int|null User ID or null if not authenticated
 */
function get_user_id(): ?int
{
    return isset($_SESSION['userID']) && is_numeric($_SESSION['userID']) 
        ? (int)$_SESSION['userID'] 
        : null;
}

/**
 * Get authenticated user details
 * 
 * @return array|null User details array or null if not authenticated
 */
function get_user_details(): ?array
{
    $userId = get_user_id();
    
    if (!$userId) {
        return null;
    }
    
    // Try to get from function if available
    if (function_exists('getUserInformation')) {
        $details = getUserInformation($userId);
        
        if (is_array($details)) {
            return $details;
        } elseif (is_object($details)) {
            return (array)$details;
        }
    }
    
    // Fallback: return minimal details
    return [
        'id' => $userId,
        'userID' => $userId,
        'first_name' => $_SESSION['first_name'] ?? 'User',
        'last_name' => $_SESSION['last_name'] ?? '',
        'email' => $_SESSION['email'] ?? '',
    ];
}

/**
 * Check if user has permission
 * 
 * @param string $permission Permission name or ID
 * @return bool True if user has permission
 */
function has_permission(string $permission): bool
{
    if (!is_authenticated()) {
        return false;
    }
    
    if (function_exists('userHasPermission')) {
        return userHasPermission(get_user_id(), $permission);
    }
    
    // Fallback: check session permissions array
    return isset($_SESSION['permissions']) && in_array($permission, $_SESSION['permissions']);
}

/**
 * Verify CSRF token
 * 
 * @param string|null $token Token to verify (from $_POST or $_GET)
 * @return bool True if valid
 */
function verify_csrf_token(?string $token): bool
{
    if (!isset($_SESSION['csrf_token']) || !$token) {
        return false;
    }
    
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Get CSRF token HTML input
 * 
 * @return string HTML input field with CSRF token
 */
function csrf_token_input(): string
{
    $token = $_SESSION['csrf_token'] ?? '';
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * Safe redirect
 * 
 * @param string $url URL to redirect to
 * @return void
 */
function safe_redirect(string $url): void
{
    // Ensure URL is relative or from same domain
    if (!preg_match('#^https?://#i', $url)) {
        header('Location: ' . $url);
    } elseif (parse_url($url, PHP_URL_HOST) === $_SERVER['HTTP_HOST']) {
        header('Location: ' . $url);
    } else {
        // External URL - redirect to home instead
        header('Location: /');
    }
    exit;
}

// ============================================================================
// 8. MODULE CONTEXT FLAG
// ============================================================================

// Set flag that modules can check to ensure bootstrap was loaded
if (!defined('CIS_MODULE_CONTEXT')) {
    define('CIS_MODULE_CONTEXT', true);
}

// Set flag indicating bootstrap is complete
define('MODULES_BOOTSTRAP_LOADED', true);

// ============================================================================
// 9. OPTIONAL: LOAD MODULE-SPECIFIC BOOTSTRAP
// ============================================================================

// If a module has its own bootstrap file, load it
// Example: modules/consignments/module_bootstrap.php
$callingScript = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0]['file'] ?? '';
$moduleDir = dirname($callingScript);

if ($moduleDir !== MODULES_ROOT) {
    $moduleBootstrap = $moduleDir . '/module_bootstrap.php';
    
    if (file_exists($moduleBootstrap)) {
        require_once $moduleBootstrap;
    }
}

// ============================================================================
// BOOTSTRAP COMPLETE
// ============================================================================

// Log bootstrap completion (development only)
if (!$isProduction && function_exists('error_log')) {
    $loadTime = microtime(true) - ($_SERVER['REQUEST_TIME_FLOAT'] ?? 0);
    error_log(sprintf(
        '[BOOTSTRAP] Modules bootstrap loaded in %.3fms | User: %s | Session: %s',
        $loadTime * 1000,
        get_user_id() ?? 'guest',
        session_id()
    ));
}
