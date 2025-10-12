<?php
declare(strict_types=1);

// Minimal module bootstrap for Consignments module.
// Keep side-effects small; Kernel::boot() will load app.php and register autoloaders.

// Define module root for downstream includes if needed
if (!defined('MODULE_CONSIGNMENTS_ROOT')) {
    define('MODULE_CONSIGNMENTS_ROOT', __DIR__);
}

// Timezone from env if provided
$__tz = $_ENV['APP_TZ'] ?? '';
if (is_string($__tz) && $__tz !== '') {
    @date_default_timezone_set($__tz);
}

// Error reporting based on APP_DEBUG env
$__debug = $_ENV['APP_DEBUG'] ?? '';
$__isDebug = ($__debug === '1' || strtolower((string)$__debug) === 'true');
if ($__isDebug) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(E_ALL & ~E_NOTICE & ~E_STRICT & ~E_DEPRECATED);
}

// Allow CIS bots/tests to bypass auth by appending ?bot=true or POSTing bot=true
$__botParam = $_GET['bot'] ?? $_POST['bot'] ?? null;
if ($__botParam !== null) {
    $botFlag = false;
    if (is_bool($__botParam)) {
        $botFlag = $__botParam;
    } else {
        $value = strtolower(trim((string)$__botParam));
        $botFlag = in_array($value, ['1', 'true', 'yes', 'on'], true);
    }

    if ($botFlag) {
        $_ENV['BOT_BYPASS_AUTH'] = '1';
        $_SERVER['BOT_BYPASS_AUTH'] = '1';
        if (!defined('IS_BOT_REQUEST')) {
            define('IS_BOT_REQUEST', true);
        }
    }
}

unset($__tz, $__debug, $__isDebug, $__botParam, $botFlag, $value);

// CRITICAL: Initialize CIS database connection FIRST using existing connectToSQL()
$mysql_paths = [
    $_SERVER['DOCUMENT_ROOT'] . '/assets/functions/mysql.php',
    '/home/master/applications/jcepnzzkmj/public_html/assets/functions/mysql.php',
    __DIR__ . '/../../assets/functions/mysql.php'
];

$mysql_included = false;
foreach ($mysql_paths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $mysql_included = true;
        break;
    }
}

// If we can't find mysql.php, create a simple connection
if (!$mysql_included) {
    function connectToSQL() {
        global $con;
        
        $host = $_ENV['DB_HOST'] ?? 'localhost';
        $username = $_ENV['DB_USER'] ?? 'jcepnzzkmj';
        $password = $_ENV['DB_PASS'] ?? '';
        $database = $_ENV['DB_NAME'] ?? 'jcepnzzkmj';
        
        $con = new mysqli($host, $username, $password, $database);
        
        if ($con->connect_error) {
            error_log("CRITICAL: Connection failed: " . $con->connect_error);
            return false;
        }
        
        return $con;
    }
}

if (!connectToSQL()) {
    error_log("CRITICAL: Failed to connect to CIS database via connectToSQL()");
    // Continue but log the failure
}

// Load database configuration from .env in public_html
require_once __DIR__ . '/lib/Db.php';
Transfers\Lib\Db::loadEnv();

// Initialize shared database connection (creates singleton for all modules)
try {
    $__testDb = Transfers\Lib\Db::mysqli();
    if (!Transfers\Lib\Db::ping()) {
        throw new Exception('Database ping failed');
    }
    // MySQLi connection successful - available to all modules via Transfers\Lib\Db::mysqli()
} catch (Exception $e) {
    error_log("Module bootstrap database initialization failed: " . $e->getMessage());
    // Continue execution but log the issue
}
unset($__testDb);

// Initialize PHPSESSID session handling (inherit from main CIS application)
// Session data stored in database Session table as per CIS architecture
if (session_status() === PHP_SESSION_NONE) {
    session_start(); // This will use existing PHPSESSID cookie
}

// Simple autoloader for module classes
spl_autoload_register(function ($class) {
    // Handle Transfers\Lib namespace
    if (strpos($class, 'Transfers\\Lib\\') === 0) {
        $className = substr($class, strlen('Transfers\\Lib\\'));
        $file = __DIR__ . '/lib/' . $className . '.php';
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
    
    // Handle Transfers\Controllers namespace  
    if (strpos($class, 'Transfers\\Controllers\\') === 0) {
        $className = substr($class, strlen('Transfers\\Controllers\\'));
        $file = __DIR__ . '/controllers/' . $className . '.php';
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});
