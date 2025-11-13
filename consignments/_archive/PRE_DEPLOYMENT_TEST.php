<?php
/**
 * Pre-Deployment Test Suite
 * Tests TransferManagerAPI refactor is production-ready
 */

echo "╔═══════════════════════════════════════════════════════════════╗\n";
echo "║  PRE-DEPLOYMENT TEST SUITE - TransferManagerAPI v2.0         ║\n";
echo "╚═══════════════════════════════════════════════════════════════╝\n\n";

$tests_passed = 0;
$tests_failed = 0;
$errors = [];

// Test 1: File existence
echo "[1] Checking required files...\n";
$required_files = [
    'lib/TransferManagerAPI.php',
    'lib/Services/TransferService.php',
    'lib/Services/ProductService.php',
    'lib/Services/ConfigService.php',
    'lib/Services/SyncService.php',
    'TransferManager/backend-v2.php'
];

foreach ($required_files as $file) {
    if (file_exists($file)) {
        echo "    ✓ $file exists\n";
        $tests_passed++;
    } else {
        echo "    ✗ $file MISSING\n";
        $tests_failed++;
        $errors[] = "Missing file: $file";
    }
}

// Test 2: PHP syntax validation
echo "\n[2] Validating PHP syntax...\n";
foreach ($required_files as $file) {
    if (file_exists($file) && pathinfo($file, PATHINFO_EXTENSION) === 'php') {
        exec("php -l $file 2>&1", $output, $return);
        if ($return === 0) {
            echo "    ✓ $file syntax OK\n";
            $tests_passed++;
        } else {
            echo "    ✗ $file syntax ERROR\n";
            $tests_failed++;
            $errors[] = "Syntax error in $file: " . implode("\n", $output);
        }
    }
}

// Test 3: Service class structure
echo "\n[3] Checking service class structure...\n";
require_once 'lib/Services/TransferService.php';
require_once 'lib/Services/ProductService.php';
require_once 'lib/Services/ConfigService.php';
require_once 'lib/Services/SyncService.php';

$services = [
    'CIS\Consignments\Lib\Services\TransferService',
    'CIS\Consignments\Lib\Services\ProductService',
    'CIS\Consignments\Lib\Services\ConfigService',
    'CIS\Consignments\Lib\Services\SyncService'
];

foreach ($services as $service_class) {
    if (class_exists($service_class)) {
        echo "    ✓ $service_class loaded\n";
        $tests_passed++;
        
        // Check for make() factory method
        if (method_exists($service_class, 'make')) {
            echo "    ✓ $service_class::make() exists\n";
            $tests_passed++;
        } else {
            echo "    ✗ $service_class::make() MISSING\n";
            $tests_failed++;
            $errors[] = "Missing factory method: $service_class::make()";
        }
    } else {
        echo "    ✗ $service_class NOT FOUND\n";
        $tests_failed++;
        $errors[] = "Class not found: $service_class";
    }
}

// Test 4: TransferManagerAPI structure
echo "\n[4] Checking TransferManagerAPI structure...\n";
$api_file = file_get_contents('lib/TransferManagerAPI.php');

$required_methods = [
    'handleInit',
    'handleToggleSync',
    'handleVerifySync',
    'handleListTransfers',
    'handleGetTransferDetail',
    'handleSearchProducts',
    'handleCreateTransfer',
    'handleAddTransferItem',
    'handleUpdateTransferItem',
    'handleRemoveTransferItem',
    'handleMarkSent',
    'handleAddNote'
];

foreach ($required_methods as $method) {
    if (strpos($api_file, "function $method(") !== false) {
        echo "    ✓ $method() exists\n";
        $tests_passed++;
    } else {
        echo "    ✗ $method() MISSING\n";
        $tests_failed++;
        $errors[] = "Missing handler method: $method()";
    }
}

// Test 5: Service injection in API
echo "\n[5] Checking service injection...\n";
$service_properties = [
    'transferService',
    'productService',
    'configService',
    'syncService'
];

foreach ($service_properties as $prop) {
    if (strpos($api_file, "private $prop") !== false || strpos($api_file, "private \$$prop") !== false) {
        echo "    ✓ \$$prop property declared\n";
        $tests_passed++;
    } else {
        echo "    ✗ \$$prop property MISSING\n";
        $tests_failed++;
        $errors[] = "Missing service property: \$$prop";
    }
    
    if (strpos($api_file, "\$this->$prop = ") !== false) {
        echo "    ✓ \$$prop initialized\n";
        $tests_passed++;
    } else {
        echo "    ✗ \$$prop NOT initialized\n";
        $tests_failed++;
        $errors[] = "Service not initialized: \$$prop";
    }
}

// Test 6: Check for direct database queries (should be 0)
echo "\n[6] Checking for direct database queries in API...\n";
$direct_queries = preg_match_all('/\$this->db->(query|prepare|execute)/', $api_file, $matches);
if ($direct_queries === 0) {
    echo "    ✓ No direct database queries found (using services)\n";
    $tests_passed++;
} else {
    echo "    ✗ Found $direct_queries direct database queries (should use services)\n";
    $tests_failed++;
    $errors[] = "API contains direct database queries - should use services";
}

// Test 7: Code metrics
echo "\n[7] Code metrics...\n";
$api_lines = count(file('lib/TransferManagerAPI.php'));
echo "    ℹ TransferManagerAPI: $api_lines lines\n";
if ($api_lines < 700) {
    echo "    ✓ Code reduction achieved (target: <700 lines)\n";
    $tests_passed++;
} else {
    echo "    ⚠ Code still large (target: <700 lines)\n";
}

$service_lines = 0;
foreach (['TransferService', 'ProductService', 'ConfigService', 'SyncService'] as $service) {
    $lines = count(file("lib/Services/$service.php"));
    $service_lines += $lines;
    echo "    ℹ $service: $lines lines\n";
}
echo "    ℹ Total service lines: $service_lines\n";

// Summary
echo "\n";
echo "═══════════════════════════════════════════════════════════════\n";
echo "SUMMARY\n";
echo "═══════════════════════════════════════════════════════════════\n";
echo "✓ Passed: $tests_passed\n";
echo "✗ Failed: $tests_failed\n";
echo "  Total:  " . ($tests_passed + $tests_failed) . "\n";
echo "═══════════════════════════════════════════════════════════════\n";

if ($tests_failed > 0) {
    echo "\n❌ DEPLOYMENT BLOCKED - FIX ERRORS:\n";
    foreach ($errors as $i => $error) {
        echo "  " . ($i + 1) . ". $error\n";
    }
    exit(1);
} else {
    echo "\n🎉 ALL PRE-DEPLOYMENT TESTS PASSED!\n";
    echo "✅ READY FOR PRODUCTION DEPLOYMENT\n";
    exit(0);
}
