<?php
/**
 * CIS Modules - Shared Base Bootstrap
 * 
 * This file provides common initialization for ALL CIS modules.
 * Every module should load this FIRST before doing module-specific setup.
 * 
 * What this provides:
 * - Root path and base URL constants
 * - Session handling
 * - Database connection (via app.php → config.php)
 * - Error handling (ErrorHub)
 * - StandardResponse API envelope
 * - Core utility functions
 * 
 * Usage (in any module's bootstrap.php):
 *   require_once __DIR__ . '/../shared/bootstrap.php';
 * 
 * @package CIS\Shared
 * @version 2.0.0
 */

declare(strict_types=1);

// ============================================================================
// DETECT CLI MODE FIRST
// ============================================================================

$IS_CLI = (php_sapi_name() === 'cli' || !isset($_SERVER['HTTP_HOST']));

// ============================================================================
// CLI-ONLY ENVIRONMENT (bypass all web-specific initialization)
// ============================================================================

if ($IS_CLI) {
    // Define minimal constants for CLI
    if (!defined('ROOT_PATH')) {
        define('ROOT_PATH', dirname(__DIR__, 2)); // modules/shared → public_html
    }
    
    if (!defined('BASE_URL')) {
        define('BASE_URL', getenv('BASE_URL') ?: 'https://staff.vapeshed.co.nz');
    }
    
    // Load .env file for database credentials (CLI needs this!)
    $envFile = ROOT_PATH . '/.env';
    if (file_exists($envFile)) {
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            // Skip comments
            if (strpos(trim($line), '#') === 0) {
                continue;
            }
            // Parse KEY=VALUE
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);
                // Remove quotes if present
                $value = trim($value, '"\'');
                // Set as environment variable
                putenv("{$key}={$value}");
                $_ENV[$key] = $value;
            }
        }
    }
    
    // Load ONLY PDO connection (skip app.php and all web dependencies)
    if (!isset($GLOBALS['pdo'])) {
        try {
            $host = getenv('DB_HOST') ?: 'localhost';
            $dbname = getenv('DB_NAME') ?: '';
            $user = getenv('DB_USER') ?: '';
            $pass = getenv('DB_PASS') ?: '';
            
            if ($dbname && $user) {
                $dsn = "mysql:host={$host};dbname={$dbname};charset=utf8mb4";
                $options = [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
                ];
                $GLOBALS['pdo'] = new PDO($dsn, $user, $pass, $options);
            }
        } catch (Exception $e) {
            error_log('[CLI Bootstrap] PDO creation failed: ' . $e->getMessage());
        }
    }
    
    // Load minimal helpers for CLI
    if (file_exists(__DIR__ . '/functions/config.php')) {
        require_once __DIR__ . '/functions/config.php';
    }
    
    define('CIS_SHARED_BOOTSTRAP_LOADED', true);
    define('CIS_CLI_MODE', true);
    
    // CLI bootstrap complete - skip all web initialization below
    return;
}

// ============================================================================
// WEB-ONLY ENVIRONMENT (full initialization)
// ============================================================================

// 1. CORE CONSTANTS & PATHS
if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', $_SERVER['DOCUMENT_ROOT']);
}

if (!defined('BASE_URL')) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    define('BASE_URL', $protocol . '://' . $_SERVER['HTTP_HOST']);
}

// 2. LOAD BASE APPLICATION (sessions, database, core functions)
// Load app.php which loads config.php (sessions, DB, etc.)
if (file_exists(ROOT_PATH . '/app.php')) {
    require_once ROOT_PATH . '/app.php';
}

// ============================================================================
// 2.5. ENSURE PDO CONNECTION IS AVAILABLE GLOBALLY
// ============================================================================

// Create PDO connection if not already available
if (!isset($GLOBALS['pdo'])) {
    try {
        // Try to get credentials from environment variables first
        $host = getenv('DB_HOST') ?: 'localhost';
        $dbname = getenv('DB_NAME') ?: '';
        $user = getenv('DB_USER') ?: '';
        $pass = getenv('DB_PASS') ?: '';
        
        // Fallback to constants if available
        if (defined('DB_HOST')) $host = DB_HOST;
        if (defined('DB_NAME')) $dbname = DB_NAME;
        if (defined('DB_USER')) $user = DB_USER;
        if (defined('DB_PASS')) $pass = DB_PASS;
        
        // If MySQLi connection exists, extract database name from it
        if (!$dbname && isset($db) && $db instanceof mysqli) {
            $result = $db->query("SELECT DATABASE()");
            if ($result) {
                $row = $result->fetch_row();
                $dbname = $row[0];
                $result->free();
            }
        }
        
        // Create PDO connection if we have the required info
        if ($dbname && $user) {
            $dsn = "mysql:host={$host};dbname={$dbname};charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
            ];
            $GLOBALS['pdo'] = new PDO($dsn, $user, $pass, $options);
        }
    } catch (Exception $e) {
        error_log('[CIS Bootstrap] PDO creation failed: ' . $e->getMessage());
        // Continue without PDO - some modules may not need it
    }
}

// ============================================================================
// 3. LOAD SHARED ERROR HANDLING
// ============================================================================

// Load and initialize global error handler (production-safe by default)
if (file_exists(__DIR__ . '/lib/ErrorHub.php')) {
    require_once __DIR__ . '/lib/ErrorHub.php';
    CIS\Shared\ErrorHub::register();
}

// ============================================================================
// 4. LOAD SHARED API RESPONSE HANDLER
// ============================================================================

// StandardResponse envelope for all API calls
if (file_exists(__DIR__ . '/api/StandardResponse.php')) {
    require_once __DIR__ . '/api/StandardResponse.php';
}

// ============================================================================
// 5. SHARED UTILITIES & HELPERS
// ============================================================================

// Configuration helpers (cis_config_get, etc.)
if (file_exists(__DIR__ . '/functions/config.php')) {
    require_once __DIR__ . '/functions/config.php';
}

// Navigation menu helpers (getNavigationMenus, renderNavigationMenu, etc.)
if (file_exists(__DIR__ . '/functions/navigation.php')) {
    require_once __DIR__ . '/functions/navigation.php';
}

// ============================================================================
// BOOTSTRAP COMPLETE
// ============================================================================

define('CIS_SHARED_BOOTSTRAP_LOADED', true);
