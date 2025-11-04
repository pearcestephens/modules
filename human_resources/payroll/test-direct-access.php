#!/usr/bin/env php
<?php
/**
 * Test direct controller access to verify controllers work
 */

echo "==========================================\n";
echo "DIRECT CONTROLLER ACCESS TEST\n";
echo "==========================================\n\n";

// Load bootstrap
require_once __DIR__ . '/bootstrap.php';

echo "TEST 1: Load DashboardController\n";
try {
    $controllerClass = 'HumanResources\\Payroll\\Controllers\\DashboardController';

    if (class_exists($controllerClass)) {
        echo "✓ Class exists: {$controllerClass}\n";

        $controller = new $controllerClass();
        echo "✓ Controller instantiated\n";

        if (method_exists($controller, 'index')) {
            echo "✓ index() method exists\n";

            // Capture output
            ob_start();
            $controller->index();
            $output = ob_get_clean();

            echo "✓ index() executed without error\n";
            echo "Output length: " . strlen($output) . " bytes\n";

            if (strlen($output) > 0) {
                echo "First 200 chars:\n";
                echo substr($output, 0, 200) . "...\n";
            } else {
                echo "⚠ WARNING: Output is empty!\n";
            }
        } else {
            echo "✗ index() method not found\n";
        }
    } else {
        echo "✗ Class not found: {$controllerClass}\n";
    }
} catch (Exception $e) {
    echo "✗ ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n";
    echo $e->getTraceAsString() . "\n";
}

echo "\n";
echo "TEST 2: Check view file\n";
$viewFile = __DIR__ . '/views/dashboard.php';
if (file_exists($viewFile)) {
    echo "✓ View file exists: {$viewFile}\n";
    echo "File size: " . filesize($viewFile) . " bytes\n";
} else {
    echo "✗ View file NOT found: {$viewFile}\n";
}

echo "\n";
echo "==========================================\n";
echo "TEST COMPLETE\n";
echo "==========================================\n";
