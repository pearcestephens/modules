<?php
/**
 * Comprehensive API Test Suite
 * Tests refactored TransferManagerAPI with service layer
 */

declare(strict_types=1);

echo "╔══════════════════════════════════════════════════════════════════╗\n";
echo "║  TransferManagerAPI - Comprehensive Pre-Production Test Suite   ║\n";
echo "╚══════════════════════════════════════════════════════════════════╝\n\n";

// Test counter
$passed = 0;
$failed = 0;
$tests = [];

function test(string $name, callable $fn): void {
    global $passed, $failed, $tests;
    try {
        $result = $fn();
        if ($result === true || $result === null) {
            echo "✓ {$name}\n";
            $passed++;
            $tests[] = ['name' => $name, 'status' => 'passed'];
        } else {
            echo "✗ {$name}: " . ($result ?: 'Failed') . "\n";
            $failed++;
            $tests[] = ['name' => $name, 'status' => 'failed', 'error' => $result];
        }
    } catch (Exception $e) {
        echo "✗ {$name}: " . $e->getMessage() . "\n";
        $failed++;
        $tests[] = ['name' => $name, 'status' => 'failed', 'error' => $e->getMessage()];
    }
}

echo "[1] File Existence & Permissions\n";
test("backend-v2.php exists", fn() => file_exists('TransferManager/backend-v2.php'));
test("backend-v2.php readable", fn() => is_readable('TransferManager/backend-v2.php'));
test("TransferManagerAPI.php exists", fn() => file_exists('lib/TransferManagerAPI.php'));
test("TransferManagerAPI.php readable", fn() => is_readable('lib/TransferManagerAPI.php'));
test("All service files exist", function() {
    $services = ['TransferService', 'ProductService', 'ConfigService', 'SyncService'];
    foreach ($services as $service) {
        if (!file_exists("lib/Services/{$service}.php")) {
            return "Missing {$service}.php";
        }
    }
    return true;
});
echo "\n";

echo "[2] PHP Syntax Validation\n";
test("backend-v2.php syntax valid", function() {
    exec("php -l TransferManager/backend-v2.php 2>&1", $output, $code);
    return $code === 0 ? true : implode("\n", $output);
});
test("TransferManagerAPI.php syntax valid", function() {
    exec("php -l lib/TransferManagerAPI.php 2>&1", $output, $code);
    return $code === 0 ? true : implode("\n", $output);
});
test("TransferService.php syntax valid", function() {
    exec("php -l lib/Services/TransferService.php 2>&1", $output, $code);
    return $code === 0 ? true : implode("\n", $output);
});
test("ProductService.php syntax valid", function() {
    exec("php -l lib/Services/ProductService.php 2>&1", $output, $code);
    return $code === 0 ? true : implode("\n", $output);
});
test("ConfigService.php syntax valid", function() {
    exec("php -l lib/Services/ConfigService.php 2>&1", $output, $code);
    return $code === 0 ? true : implode("\n", $output);
});
test("SyncService.php syntax valid", function() {
    exec("php -l lib/Services/SyncService.php 2>&1", $output, $code);
    return $code === 0 ? true : implode("\n", $output);
});
echo "\n";

echo "[3] Class Loading & Instantiation\n";
test("Bootstrap loads without errors", function() {
    ob_start();
    try {
        require_once 'bootstrap.php';
        ob_end_clean();
        return true;
    } catch (Exception $e) {
        ob_end_clean();
        return $e->getMessage();
    }
});

test("TransferManagerAPI class exists", function() {
    return class_exists('CIS\Consignments\Lib\TransferManagerAPI') ? true : "Class not found";
});

test("TransferService class exists", function() {
    return class_exists('CIS\Consignments\Lib\Services\TransferService') ? true : "Class not found";
});

test("ProductService class exists", function() {
    return class_exists('CIS\Consignments\Lib\Services\ProductService') ? true : "Class not found";
});

test("ConfigService class exists", function() {
    return class_exists('CIS\Consignments\Lib\Services\ConfigService') ? true : "Class not found";
});

test("SyncService class exists", function() {
    return class_exists('CIS\Consignments\Lib\Services\SyncService') ? true : "Class not found";
});

echo "\n";

echo "[4] Service Layer Tests (Real Database)\n";
test("TransferService::make() works", function() {
    $service = \CIS\Consignments\Lib\Services\TransferService::make();
    return $service instanceof \CIS\Consignments\Lib\Services\TransferService ? true : "Failed to instantiate";
});

test("ProductService::make() works", function() {
    $service = \CIS\Consignments\Lib\Services\ProductService::make();
    return $service instanceof \CIS\Consignments\Lib\Services\ProductService ? true : "Failed to instantiate";
});

test("ConfigService::make() works", function() {
    $service = \CIS\Consignments\Lib\Services\ConfigService::make();
    return $service instanceof \CIS\Consignments\Lib\Services\ConfigService ? true : "Failed to instantiate";
});

test("SyncService::make() works", function() {
    $service = \CIS\Consignments\Lib\Services\SyncService::make();
    return $service instanceof \CIS\Consignments\Lib\Services\SyncService ? true : "Failed to instantiate";
});

test("TransferService::recent() returns data", function() {
    $service = \CIS\Consignments\Lib\Services\TransferService::make();
    $transfers = $service->recent(5);
    return is_array($transfers) ? true : "Expected array";
});

test("ConfigService::getOutlets() returns data", function() {
    $service = \CIS\Consignments\Lib\Services\ConfigService::make();
    $outlets = $service->getOutlets();
    return is_array($outlets) && count($outlets) > 0 ? true : "No outlets found";
});

test("ConfigService::getSuppliers() returns data", function() {
    $service = \CIS\Consignments\Lib\Services\ConfigService::make();
    $suppliers = $service->getSuppliers();
    return is_array($suppliers) && count($suppliers) > 0 ? true : "No suppliers found";
});

test("SyncService::isEnabled() returns bool", function() {
    $service = \CIS\Consignments\Lib\Services\SyncService::make();
    $enabled = $service->isEnabled();
    return is_bool($enabled) ? true : "Expected boolean";
});

echo "\n";

echo "[5] Database Connectivity\n";
test("Read-only connection works", function() {
    $ro = db_ro();
    $stmt = $ro->query("SELECT 1 as test");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['test'] === 1 ? true : "Query failed";
});

test("Read-write connection works", function() {
    $rw = db_rw_or_null();
    if (!$rw) return "RW connection not available";
    $stmt = $rw->query("SELECT 1 as test");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['test'] === 1 ? true : "Query failed";
});

test("queue_consignments table accessible", function() {
    $ro = db_ro();
    $stmt = $ro->query("SELECT COUNT(*) as cnt FROM queue_consignments LIMIT 1");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return isset($result['cnt']) ? true : "Table query failed";
});

test("vend_products table accessible", function() {
    $ro = db_ro();
    $stmt = $ro->query("SELECT COUNT(*) as cnt FROM vend_products LIMIT 1");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return isset($result['cnt']) ? true : "Table query failed";
});

echo "\n";

echo "[6] API Method Availability\n";
$reflection = new ReflectionClass('CIS\Consignments\Lib\TransferManagerAPI');
$expectedMethods = [
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

foreach ($expectedMethods as $method) {
    test("API has {$method}()", function() use ($reflection, $method) {
        return $reflection->hasMethod($method) ? true : "Method not found";
    });
}

echo "\n";

echo "[7] Service Method Availability\n";
$transferReflection = new ReflectionClass('CIS\Consignments\Lib\Services\TransferService');
$expectedTransferMethods = [
    'list', 'getById', 'create', 'addItem', 'updateItem', 'deleteItem', 
    'addNote', 'updateStatus', 'getStats'
];

foreach ($expectedTransferMethods as $method) {
    test("TransferService has {$method}()", function() use ($transferReflection, $method) {
        return $transferReflection->hasMethod($method) ? true : "Method not found";
    });
}

echo "\n";

echo "[8] Code Quality Checks\n";
test("TransferManagerAPI reduced from 834 lines", function() {
    $lines = count(file('lib/TransferManagerAPI.php'));
    return $lines < 834 ? true : "Still {$lines} lines (target < 834)";
});

test("No direct DB queries in handleListTransfers", function() {
    $content = file_get_contents('lib/TransferManagerAPI.php');
    preg_match('/function handleListTransfers.*?(?=function\s+\w+|$)/s', $content, $matches);
    if (empty($matches)) return "Method not found";
    $method = $matches[0];
    $hasDirectQuery = preg_match('/\$this->db->(prepare|query)/', $method);
    return !$hasDirectQuery ? true : "Found direct DB query";
});

test("Services injected in constructor", function() {
    $content = file_get_contents('lib/TransferManagerAPI.php');
    preg_match('/function __construct.*?\{.*?\}/s', $content, $matches);
    if (empty($matches)) return "Constructor not found";
    $constructor = $matches[0];
    $hasInjection = preg_match('/TransferService::make\(\)/', $constructor);
    return $hasInjection ? true : "Service injection not found";
});

echo "\n";

echo "======================================================================\n";
echo "SUMMARY\n";
echo "======================================================================\n";
echo "✓ Passed: {$passed}\n";
echo "✗ Failed: {$failed}\n";
echo "  Total:  " . ($passed + $failed) . "\n";
echo "======================================================================\n";

if ($failed === 0) {
    echo "🎉 ALL TESTS PASSED - READY FOR PRODUCTION!\n";
    exit(0);
} else {
    echo "⚠️  SOME TESTS FAILED - REVIEW BEFORE DEPLOYMENT\n";
    exit(1);
}
