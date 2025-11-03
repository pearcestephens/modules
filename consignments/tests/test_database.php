<?php
declare(strict_types=1);

/**
 * Database Connection Test
 *
 * Verifies database connectivity and required tables exist.
 *
 * @package CIS\Consignments\Tests
 * @version 1.0.0
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/app.php';

// Test results
$results = [
    'tests_run' => 0,
    'tests_passed' => 0,
    'tests_failed' => 0,
    'details' => []
];

/**
 * Run a test
 */
function test(string $name, callable $testFn): void
{
    global $results;
    $results['tests_run']++;

    try {
        $testFn();
        $results['tests_passed']++;
        $results['details'][] = ['test' => $name, 'status' => 'PASS', 'message' => 'OK'];
        echo "✅ PASS: $name\n";
    } catch (Exception $e) {
        $results['tests_failed']++;
        $results['details'][] = ['test' => $name, 'status' => 'FAIL', 'message' => $e->getMessage()];
        echo "❌ FAIL: $name - {$e->getMessage()}\n";
    }
}

echo "═══════════════════════════════════════════════════\n";
echo "  DATABASE CONNECTION TEST - Purchase Orders\n";
echo "═══════════════════════════════════════════════════\n\n";

// Get database connection
try {
    $pdo = getDbConnection();
    echo "✅ Database connection established\n\n";
} catch (Exception $e) {
    echo "❌ FATAL: Cannot connect to database: {$e->getMessage()}\n";
    exit(1);
}

// Test 1: vend_consignments table exists
test('vend_consignments table exists', function() use ($pdo) {
    $stmt = $pdo->query("SHOW TABLES LIKE 'vend_consignments'");
    if ($stmt->rowCount() === 0) {
        throw new Exception('Table does not exist');
    }
});

// Test 2: vend_consignment_line_items table exists
test('vend_consignment_line_items table exists', function() use ($pdo) {
    $stmt = $pdo->query("SHOW TABLES LIKE 'vend_consignment_line_items'");
    if ($stmt->rowCount() === 0) {
        throw new Exception('Table does not exist');
    }
});

// Test 3: transfer_category enum includes PURCHASE_ORDER
test('transfer_category includes PURCHASE_ORDER', function() use ($pdo) {
    $stmt = $pdo->query("SHOW COLUMNS FROM vend_consignments LIKE 'transfer_category'");
    $column = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$column) {
        throw new Exception('Column transfer_category not found');
    }

    if (stripos($column['Type'], 'PURCHASE_ORDER') === false) {
        throw new Exception('PURCHASE_ORDER not in enum values');
    }
});

// Test 4: consignment_audit_log table exists
test('consignment_audit_log table exists', function() use ($pdo) {
    $stmt = $pdo->query("SHOW TABLES LIKE 'consignment_audit_log'");
    if ($stmt->rowCount() === 0) {
        throw new Exception('Table does not exist');
    }
});

// Test 5: queue_consignments table exists
test('queue_consignments table exists', function() use ($pdo) {
    $stmt = $pdo->query("SHOW TABLES LIKE 'queue_consignments'");
    if ($stmt->rowCount() === 0) {
        throw new Exception('Table does not exist');
    }
});

// Test 6: vend_suppliers table exists
test('vend_suppliers table exists', function() use ($pdo) {
    $stmt = $pdo->query("SHOW TABLES LIKE 'vend_suppliers'");
    if ($stmt->rowCount() === 0) {
        throw new Exception('Table does not exist');
    }
});

// Test 7: vend_products table exists
test('vend_products table exists', function() use ($pdo) {
    $stmt = $pdo->query("SHOW TABLES LIKE 'vend_products'");
    if ($stmt->rowCount() === 0) {
        throw new Exception('Table does not exist');
    }
});

// Test 8: vend_outlets table exists
test('vend_outlets table exists', function() use ($pdo) {
    $stmt = $pdo->query("SHOW TABLES LIKE 'vend_outlets'");
    if ($stmt->rowCount() === 0) {
        throw new Exception('Table does not exist');
    }
});

// Test 9: users table exists
test('users table exists', function() use ($pdo) {
    $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
    if ($stmt->rowCount() === 0) {
        throw new Exception('Table does not exist');
    }
});

// Test 10: Can query vend_consignments
test('Can query vend_consignments', function() use ($pdo) {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM vend_consignments WHERE transfer_category = 'PURCHASE_ORDER'");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo " (Found {$result['count']} existing POs)";
});

// Test 11: Can query vend_suppliers
test('Can query vend_suppliers', function() use ($pdo) {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM vend_suppliers WHERE deleted_at IS NULL");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo " (Found {$result['count']} active suppliers)";
});

// Test 12: Can query vend_outlets
test('Can query vend_outlets', function() use ($pdo) {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM vend_outlets WHERE deleted_at IS NULL");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo " (Found {$result['count']} active outlets)";
});

// Test 13: Can instantiate PurchaseOrderService
test('Can load PurchaseOrderService', function() use ($pdo) {
    require_once __DIR__ . '/../lib/Services/PurchaseOrderService.php';
    $service = new \CIS\Consignments\Services\PurchaseOrderService($pdo);
    if (!$service) {
        throw new Exception('Failed to instantiate service');
    }
});

// Test 14: Can instantiate ApprovalService
test('Can load ApprovalService', function() use ($pdo) {
    require_once __DIR__ . '/../lib/Services/ApprovalService.php';
    $service = new \CIS\Consignments\Services\ApprovalService($pdo);
    if (!$service) {
        throw new Exception('Failed to instantiate service');
    }
});

// Test 15: Can instantiate ReceivingService
test('Can load ReceivingService', function() use ($pdo) {
    require_once __DIR__ . '/../lib/Services/ReceivingService.php';
    $service = new \CIS\Consignments\Services\ReceivingService($pdo);
    if (!$service) {
        throw new Exception('Failed to instantiate service');
    }
});

// Test 16: Can instantiate SupplierService
test('Can load SupplierService', function() use ($pdo) {
    require_once __DIR__ . '/../lib/Services/SupplierService.php';
    $service = new \CIS\Consignments\Services\SupplierService($pdo);
    if (!$service) {
        throw new Exception('Failed to instantiate service');
    }
});

// Test 17: Can load ValidationHelper
test('Can load ValidationHelper', function() {
    require_once __DIR__ . '/../lib/Helpers/ValidationHelper.php';

    // Test a simple validation
    $result = \CIS\Consignments\Helpers\ValidationHelper::validateEmail('test@example.com');
    if (!$result) {
        throw new Exception('Email validation failed');
    }
});

// Summary
echo "\n═══════════════════════════════════════════════════\n";
echo "  TEST SUMMARY\n";
echo "═══════════════════════════════════════════════════\n\n";
echo "Total Tests:   {$results['tests_run']}\n";
echo "✅ Passed:      {$results['tests_passed']}\n";
echo "❌ Failed:      {$results['tests_failed']}\n";
echo "\n";

if ($results['tests_failed'] === 0) {
    echo "🎉 ALL TESTS PASSED! Database is ready for Purchase Orders.\n\n";
    exit(0);
} else {
    echo "⚠️  SOME TESTS FAILED. Please review errors above.\n\n";
    exit(1);
}
