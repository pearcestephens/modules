<?php
/**
 * Database Connection Test - PDO Configuration
 * 
 * Tests that PDO is configured correctly and MySQLi is available but not auto-initialized
 */

declare(strict_types=1);

echo "\n";
echo "═══════════════════════════════════════════════════════════\n";
echo " CIS Base Module - Database Configuration Test\n";
echo "═══════════════════════════════════════════════════════════\n\n";

// Load Database classes
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/DatabasePDO.php';
require_once __DIR__ . '/DatabaseMySQLi.php';

use CIS\Base\Database;

$passed = 0;
$failed = 0;

function test(string $name, callable $test): void {
    global $passed, $failed;
    try {
        $result = $test();
        if ($result === true) {
            $passed++;
            echo "✅ PASS: $name\n";
        } else {
            $failed++;
            echo "❌ FAIL: $name\n";
            if (is_string($result)) {
                echo "   → $result\n";
            }
        }
    } catch (Exception $e) {
        $failed++;
        echo "❌ FAIL: $name\n";
        echo "   → Exception: " . $e->getMessage() . "\n";
    }
}

echo "Testing Database Configuration...\n\n";

// Test 1: PDO Auto-initialization
test("PDO auto-initializes on first call", function() {
    try {
        $pdo = Database::pdo();
        return $pdo instanceof PDO;
    } catch (Exception $e) {
        return $e->getMessage();
    }
});

// Test 2: Database driver returns PDO
test("getDriver() returns 'PDO'", function() {
    return Database::getDriver() === 'PDO';
});

// Test 3: Simple query works
test("Simple PDO query executes", function() {
    $result = Database::query("SELECT 1 as test");
    return is_array($result) && isset($result[0]['test']) && $result[0]['test'] == 1;
});

// Test 4: Prepared statement with parameters
test("Prepared statement with parameters works", function() {
    $result = Database::query("SELECT ? as value, ? as name", [123, 'test']);
    return isset($result[0]['value']) && $result[0]['value'] == 123;
});

// Test 5: Query one works
test("queryOne() returns single row", function() {
    $result = Database::queryOne("SELECT 'test' as value");
    return is_array($result) && $result['value'] === 'test';
});

// Test 6: MySQLi NOT auto-initialized
test("MySQLi NOT auto-initialized", function() {
    try {
        Database::mysqli();
        return "MySQLi should NOT be auto-initialized";
    } catch (RuntimeException $e) {
        // Expected exception
        return strpos($e->getMessage(), 'not initialized') !== false;
    }
});

// Test 7: MySQLi can be manually initialized
test("MySQLi can be manually initialized", function() {
    Database::initMySQLi();
    $mysqli = Database::mysqli();
    return $mysqli instanceof mysqli;
});

// Test 8: MySQLi works after initialization
test("MySQLi query works after initMySQLi()", function() {
    $mysqli = Database::mysqli();
    $result = $mysqli->query("SELECT 'test' as value");
    $row = $result->fetch_assoc();
    return $row['value'] === 'test';
});

// Test 9: Both drivers work simultaneously
test("Both PDO and MySQLi work simultaneously", function() {
    $pdoResult = Database::query("SELECT 'pdo' as driver");
    $mysqli = Database::mysqli();
    $mysqliResult = $mysqli->query("SELECT 'mysqli' as driver");
    $mysqliRow = $mysqliResult->fetch_assoc();
    
    return $pdoResult[0]['driver'] === 'pdo' && 
           $mysqliRow['driver'] === 'mysqli';
});

// Test 10: Transaction support
test("PDO transactions work", function() {
    Database::beginTransaction();
    Database::commit();
    return true;
});

// Test 11: Direct PDO access
test("Direct PDO access returns PDO instance", function() {
    $pdo = Database::pdo();
    return get_class($pdo) === 'PDO';
});

// Test 12: Last insert ID
test("lastInsertId() accessible", function() {
    $id = Database::lastInsertId();
    return is_int($id);
});

echo "\n═══════════════════════════════════════════════════════════\n";
echo "Test Summary\n";
echo "═══════════════════════════════════════════════════════════\n\n";

$total = $passed + $failed;
$percentage = $total > 0 ? round(($passed / $total) * 100, 1) : 0;

echo "Passed:  $passed\n";
echo "Failed:  $failed\n";
echo "Total:   $total\n";
echo "Success: $percentage%\n\n";

if ($failed === 0) {
    echo "✅ ALL TESTS PASSED\n\n";
    echo "Configuration Summary:\n";
    echo "  • PDO: Auto-initialized on first use ✅\n";
    echo "  • MySQLi: Available but manual init required ✅\n";
    echo "  • Driver: " . Database::getDriver() . " ✅\n";
    echo "  • Both drivers can coexist ✅\n\n";
    echo "Status: PRODUCTION READY 🚀\n\n";
    exit(0);
} else {
    echo "❌ SOME TESTS FAILED\n";
    echo "Please review the errors above.\n\n";
    exit(1);
}
