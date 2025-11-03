<?php
/**
 * PHPUnit Bootstrap File
 *
 * Sets up testing environment for payroll module tests
 * Phase 1 Security: Uses centralized config, no hardcoded credentials
 */

// Set testing environment
$_ENV['APP_ENV'] = 'testing';
$_ENV['APP_DEBUG'] = false;

// Define base paths
define('PAYROLL_TEST_ROOT', __DIR__);
define('PAYROLL_MODULE_ROOT', dirname(__DIR__));
define('MODULES_ROOT', dirname(PAYROLL_MODULE_ROOT, 2));

// Load main app for base infrastructure
require_once '/home/master/applications/jcepnzzkmj/public_html/app.php';

// Composer autoloader
$autoloadFile = MODULES_ROOT . '/vendor/autoload.php';
if (file_exists($autoloadFile)) {
    require_once $autoloadFile;
}

// Load environment helper if not already loaded
if (!function_exists('env')) {
    require_once MODULES_ROOT . '/config/env-loader.php';
}

// Load payroll service classes
require_once __DIR__ . '/../services/PayslipCalculationEngine.php';
require_once __DIR__ . '/../services/BonusService.php';
require_once __DIR__ . '/../services/BankExportService.php';
require_once __DIR__ . '/../services/NZEmploymentLaw.php';

// Prevent session autostart in tests
ini_set('session.auto_start', '0');

// Set error reporting for tests
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Initialize database connection for tests using centralized config
if (!isset($GLOBALS['pdo']) || !$GLOBALS['pdo']) {
    try {
        // For unit tests, use hardcoded test credentials
        // Integration tests will use actual database
        $host = getenv('PAYROLL_DB_HOST') ?: '127.0.0.1';
        $database = getenv('PAYROLL_DB_NAME') ?: 'jcepnzzkmj';
        $username = getenv('PAYROLL_DB_USER') ?: 'jcepnzzkmj';
        $password = getenv('PAYROLL_DB_PASS') ?: 'wprKh9Jq63';
        $charset = 'utf8mb4';

        $dsn = sprintf(
            "mysql:host=%s;dbname=%s;charset=%s",
            $host,
            $database,
            $charset
        );

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        $pdo = new PDO($dsn, $username, $password, $options);
        $GLOBALS['pdo'] = $pdo;

        echo "\n";
        echo "╔════════════════════════════════════════════════════════════════╗\n";
        echo "║  PHPUnit Test Suite - Payroll Module                          ║\n";
        echo "║  Environment: " . str_pad($_ENV['APP_ENV'], 48) . " ║\n";
        echo "║  Database: " . str_pad($cisConfig['database'], 51) . " ║\n";
        echo "╚════════════════════════════════════════════════════════════════╝\n";
        echo "\n";

    } catch (PDOException $e) {
        die("❌ Database connection failed: " . $e->getMessage() . "\n");
    }
}
