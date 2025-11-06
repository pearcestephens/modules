<?php
declare(strict_types=1);

// Test script to diagnose WageDiscrepancyController instantiation

error_reporting(E_ALL);
ini_set('display_errors', '1');

echo "=== Testing WageDiscrepancyController Instantiation ===\n\n";

// Load composer autoloader
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
    echo "1. Autoloader loaded ✓\n";
} else {
    echo "1. No autoloader found (skipping)\n";
}

// Load database config
$host = '127.0.0.1';
$dbname = 'jcepnzzkmj';
$username = 'jcepnzzkmj';
$password = 'wprKh9Jq63';

try {
    $dsn = "mysql:host={$host};dbname={$dbname};charset=utf8mb4";
    $db = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
    echo "2. Database connection created ✓\n";
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage() . "\n");
}

// Load dependencies
echo "3. Loading dependencies...\n";

try {
    require_once __DIR__ . '/lib/PayrollLogger.php';
    echo "   PayrollLogger loaded ✓\n";

    require_once __DIR__ . '/controllers/BaseController.php';
    echo "   BaseController loaded ✓\n";

    require_once __DIR__ . '/services/BaseService.php';
    echo "   BaseService loaded ✓\n";

    require_once __DIR__ . '/services/AmendmentService.php';
    echo "   AmendmentService loaded ✓\n";

    require_once __DIR__ . '/services/PayslipService.php';
    echo "   PayslipService loaded ✓\n";

    require_once __DIR__ . '/services/WageDiscrepancyService.php';
    echo "   WageDiscrepancyService loaded ✓\n";
} catch (Throwable $e) {
    die("Failed to load dependencies: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n");
}

// Try to load the controller
echo "\n4. Loading WageDiscrepancyController class...\n";

try {
    $controllerFile = __DIR__ . '/controllers/WageDiscrepancyController.php';
    if (!file_exists($controllerFile)) {
        die("Controller file not found: {$controllerFile}\n");
    }
    require_once $controllerFile;
    echo "   Controller file loaded ✓\n";
} catch (Throwable $e) {
    die("Failed to load controller file: " . $e->getMessage() . "\n");
}

// Try to instantiate the controller
echo "\n5. Instantiating WageDiscrepancyController...\n";

try {
    $controller = new \HumanResources\Payroll\Controllers\WageDiscrepancyController($db);
    echo "   ✓ Controller instantiated successfully!\n";
    echo "   Controller class: " . get_class($controller) . "\n";
} catch (Throwable $e) {
    echo "   ✗ Failed to instantiate controller:\n";
    echo "   Error: " . $e->getMessage() . "\n";
    echo "   File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "   Stack trace:\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}

echo "\n6. Testing service instantiation directly...\n";

try {
    $service = new \PayrollModule\Services\WageDiscrepancyService();
    echo "   ✓ WageDiscrepancyService instantiated successfully!\n";
} catch (Throwable $e) {
    echo "   ✗ Failed to instantiate service:\n";
    echo "   Error: " . $e->getMessage() . "\n";
    echo "   File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "   Stack trace:\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}

echo "\n=== ALL TESTS PASSED ===\n";
