<?php
/**
 * Test Controller Instantiation
 *
 * Tests if AmendmentController can be instantiated successfully
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

// Mock the required environment
$_SESSION = [];
$_SESSION['csrf_token'] = 'test_token';
$_SESSION['csrf_token_time'] = time();

// Set base path
define('PAYROLL_MODULE_PATH', __DIR__);

// Register the autoloader
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
});

// Mock the payroll_get_current_user function (controllers need this)
if (!function_exists('payroll_get_current_user')) {
    function payroll_get_current_user() {
        return [
            'id' => 1,
            'username' => 'test_user',
            'email' => 'test@example.com',
            'role' => 'admin'
        ];
    }
}

echo "=== CONTROLLER INSTANTIATION TEST ===\n\n";

// Test 1: AmendmentController
echo "TEST 1: Instantiating AmendmentController\n";
echo "--------------------------------------------\n";
try {
    $controller = new HumanResources\Payroll\Controllers\AmendmentController();
    echo "✅ AmendmentController instantiated successfully!\n";
    echo "Controller class: " . get_class($controller) . "\n";

    // Try to call an endpoint
    echo "\nTesting getPending() method...\n";
    ob_start();
    $controller->getPending();
    $output = ob_get_clean();

    if (!empty($output)) {
        echo "✅ Method executed and returned output\n";
        echo "Output preview: " . substr($output, 0, 200) . "...\n";
    } else {
        echo "⚠️  Method executed but returned empty output\n";
    }

} catch (Throwable $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "Type: " . get_class($e) . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    echo "\nStack trace:\n";
    echo $e->getTraceAsString() . "\n";
}

echo "\n";

// Test 2: BonusController
echo "TEST 2: Instantiating BonusController\n";
echo "--------------------------------------------\n";
try {
    $controller2 = new HumanResources\Payroll\Controllers\BonusController();
    echo "✅ BonusController instantiated successfully!\n";
} catch (Throwable $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "Type: " . get_class($e) . "\n";
}

echo "\n=== TEST COMPLETE ===\n";
