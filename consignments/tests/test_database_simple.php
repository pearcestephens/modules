#!/usr/bin/env php
<?php
/**
 * Simple Database Test - Direct Connection
 * Tests database structure without bootstrap dependencies
 */

declare(strict_types=1);

// Direct PDO connection
try {
    $db = new PDO(
        'mysql:host=127.0.0.1;port=3306;dbname=jcepnzzkmj;charset=utf8mb4',
        'jcepnzzkmj',
        'wprKh9Jq63',
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
} catch (PDOException $e) {
    die("❌ Database connection failed: " . $e->getMessage() . "\n");
}

echo "🔍 CONSIGNMENTS DATABASE TEST\n";
echo str_repeat("=", 80) . "\n\n";

$passed = 0;
$failed = 0;

function testTable($db, $table, $requiredCols = []) {
    global $passed, $failed;

    echo "Testing: {$table}... ";

    try {
        // Check if table exists
        $stmt = $db->query("SHOW TABLES LIKE '{$table}'");
        if ($stmt->rowCount() === 0) {
            echo "❌ NOT FOUND\n";
            $failed++;
            return;
        }

        // Get row count
        $stmt = $db->query("SELECT COUNT(*) as count FROM {$table}");
        $count = $stmt->fetch()['count'];

        // Check required columns if specified
        if (!empty($requiredCols)) {
            $stmt = $db->query("DESCRIBE {$table}");
            $actualCols = $stmt->fetchAll(PDO::FETCH_COLUMN);
            $missing = array_diff($requiredCols, $actualCols);

            if (!empty($missing)) {
                echo "❌ Missing columns: " . implode(', ', $missing) . "\n";
                $failed++;
                return;
            }
        }

        echo "✅ ({$count} rows)\n";
        $passed++;

    } catch (PDOException $e) {
        echo "❌ ERROR: " . $e->getMessage() . "\n";
        $failed++;
    }
}

echo "📋 PRIMARY TABLES\n";
echo str_repeat("-", 80) . "\n";

testTable($db, 'vend_consignments', [
    'id', 'vend_transfer_id', 'outlet_from', 'outlet_to',
    'state', 'transfer_category', 'created_at', 'deleted_at'
]);

testTable($db, 'vend_consignment_line_items', [
    'id', 'transfer_id', 'product_id', 'quantity',
    'quantity_sent', 'quantity_received', 'status'
]);

testTable($db, 'vend_consignment_queue', [
    'id', 'transfer_id', 'action', 'status'
]);

echo "\n📋 SUPPORTING TABLES\n";
echo str_repeat("-", 80) . "\n";

testTable($db, 'consignment_shipments');
testTable($db, 'consignment_parcels');
testTable($db, 'consignment_notes');
testTable($db, 'consignment_audit_log');
testTable($db, 'consignment_logs');

echo "\n📋 VEND INTEGRATION\n";
echo str_repeat("-", 80) . "\n";

testTable($db, 'vend_outlets', ['outletID', 'outletName']);
testTable($db, 'vend_suppliers', ['supplierID', 'supplierName']);
testTable($db, 'vend_products', ['id', 'sku', 'name']);

echo "\n📊 DATA COUNTS\n";
echo str_repeat("-", 80) . "\n";

$counts = [
    'Total Consignments' => "SELECT COUNT(*) FROM vend_consignments WHERE deleted_at IS NULL",
    'Stock Transfers' => "SELECT COUNT(*) FROM vend_consignments WHERE transfer_category = 'STOCK_TRANSFER' AND deleted_at IS NULL",
    'Purchase Orders' => "SELECT COUNT(*) FROM vend_consignments WHERE transfer_category = 'PURCHASE_ORDER' AND deleted_at IS NULL",
    'Open State' => "SELECT COUNT(*) FROM vend_consignments WHERE state = 'OPEN' AND deleted_at IS NULL",
    'Sent State' => "SELECT COUNT(*) FROM vend_consignments WHERE state = 'SENT' AND deleted_at IS NULL",
    'Line Items' => "SELECT COUNT(*) FROM vend_consignment_line_items",
    'Queue Jobs' => "SELECT COUNT(*) FROM vend_consignment_queue",
    'Outlets' => "SELECT COUNT(*) FROM vend_outlets",
    'Suppliers' => "SELECT COUNT(*) FROM vend_suppliers"
];

foreach ($counts as $label => $query) {
    try {
        $stmt = $db->query($query);
        $count = $stmt->fetch()['COUNT(*)'];
        echo sprintf("  %-25s %10d\n", $label . ':', $count);
    } catch (PDOException $e) {
        echo sprintf("  %-25s ERROR\n", $label . ':');
    }
}

echo "\n📋 SAMPLE DATA\n";
echo str_repeat("-", 80) . "\n";

try {
    $stmt = $db->query("
        SELECT id, vend_transfer_id, outlet_from, outlet_to, state, transfer_category, created_at
        FROM vend_consignments
        WHERE deleted_at IS NULL
        ORDER BY created_at DESC
        LIMIT 5
    ");

    $transfers = $stmt->fetchAll();

    if (count($transfers) > 0) {
        echo "Latest 5 transfers:\n";
        foreach ($transfers as $t) {
            echo sprintf("  ID: %-5d  Type: %-20s  State: %-10s  From: %s → To: %s\n",
                $t['id'],
                $t['transfer_category'],
                $t['state'],
                $t['outlet_from'],
                $t['outlet_to']
            );
        }
    } else {
        echo "  No transfers found in database.\n";
    }
} catch (PDOException $e) {
    echo "  ERROR: " . $e->getMessage() . "\n";
}

echo "\n" . str_repeat("=", 80) . "\n";
echo "SUMMARY: ✅ {$passed} passed, ❌ {$failed} failed\n";
echo str_repeat("=", 80) . "\n";

if ($failed === 0) {
    echo "🎉 ALL TESTS PASSED - DATABASE READY\n";
    exit(0);
} else {
    echo "🚨 SOME TESTS FAILED - CHECK ERRORS ABOVE\n";
    exit(1);
}
