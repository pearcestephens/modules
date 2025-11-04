<?php
/**
 * Test Autoloader
 *
 * Directly tests if the autoloader can load PayrollModule\Services classes
 */

// Suppress all output initially
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Set base path
define('PAYROLL_MODULE_PATH', __DIR__);

// Register the exact same autoloader from index.php
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

            echo "Attempting to load: $class\n";
            echo "  Prefix: $prefix\n";
            echo "  Relative: $relativeClass\n";
            echo "  Path: $path\n";
            echo "  File: $file\n";
            echo "  Exists: " . (file_exists($file) ? 'YES' : 'NO') . "\n";

            if (file_exists($file)) {
                require_once $file;
                echo "  ✅ Loaded successfully\n\n";
                return true;
            } else {
                echo "  ❌ File not found\n\n";
            }
        }
    }

    echo "No matching namespace for: $class\n\n";
    return false;
});

echo "=== AUTOLOADER TEST ===\n\n";

// Test 1: Load AmendmentService
echo "TEST 1: Loading PayrollModule\\Services\\AmendmentService\n";
echo "---------------------------------------------------\n";
try {
    $test1 = new PayrollModule\Services\AmendmentService();
    echo "✅ Successfully instantiated AmendmentService\n";
    echo "Class exists: " . (class_exists('PayrollModule\\Services\\AmendmentService', false) ? 'YES' : 'NO') . "\n";
} catch (Throwable $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
}

echo "\n";

// Test 2: Load PayrollLogger
echo "TEST 2: Loading PayrollModule\\Lib\\PayrollLogger\n";
echo "---------------------------------------------------\n";
try {
    // Check if class exists without instantiating (Logger might need parameters)
    class_exists('PayrollModule\\Lib\\PayrollLogger');
    echo "✅ PayrollLogger class loaded\n";
} catch (Throwable $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
}

echo "\n";

// Test 3: Load BonusService
echo "TEST 3: Loading PayrollModule\\Services\\BonusService\n";
echo "---------------------------------------------------\n";
try {
    $test3 = new PayrollModule\Services\BonusService();
    echo "✅ Successfully instantiated BonusService\n";
} catch (Throwable $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
}

echo "\n=== TEST COMPLETE ===\n";
