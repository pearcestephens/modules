<?php
/**
 * COMPLETE API INTEGRATION TEST SUITE
 *
 * Tests all new Product, Inventory, and Supplier API methods
 * with real Lightspeed API calls and bidirectional CIS sync
 *
 * Usage: php test-api-integration.php
 */

require_once __DIR__ . '/../../../includes/Bootstrap.php';
require_once __DIR__ . '/vend-sync-manager.php';

// ═══════════════════════════════════════════════════════════════════════════
// TEST CONFIGURATION
// ═══════════════════════════════════════════════════════════════════════════

$TESTS_ENABLED = [
    'product_create' => true,
    'product_update' => true,
    'product_delete' => true,
    'inventory_update' => true,
    'inventory_adjust' => true,
    'inventory_bulk' => true,
    'supplier_create' => true,
    'supplier_update' => true,
    'cis_bidirectional_sync' => true,
];

// Test data
$TEST_PRODUCT_SKU = 'TEST-API-' . time();
$TEST_SUPPLIER_NAME = 'Test Supplier ' . time();

// ═══════════════════════════════════════════════════════════════════════════
// INITIALIZE COMPONENTS
// ═══════════════════════════════════════════════════════════════════════════

echo "\n";
echo "═══════════════════════════════════════════════════════════════════════\n";
echo " COMPLETE API INTEGRATION TEST SUITE\n";
echo "═══════════════════════════════════════════════════════════════════════\n";
echo "\n";

$output = new CLIOutput();
$config = new ConfigManager();
$logger = new AuditLogger($config->get('audit.enabled'));
$api = new LightspeedAPIClient($config, $logger);
$db = new DatabaseManager($logger);
$queue = new QueueManager($db, $logger, $config);
$sync = new SyncEngine($api, $db, $queue, $logger, $output, $config);

$testResults = [];
$testProductId = null;
$testSupplierId = null;

// ═══════════════════════════════════════════════════════════════════════════
// TEST 1: CREATE PRODUCT
// ═══════════════════════════════════════════════════════════════════════════

if ($TESTS_ENABLED['product_create']) {
    echo "\n[TEST 1] Creating Product in Lightspeed...\n";

    $productData = [
        'name' => 'Test Product ' . time(),
        'sku' => $TEST_PRODUCT_SKU,
        'supply_price' => 10.50,
        'retail_price' => 25.00,
        'description' => 'Test product created via API',
    ];

    $result = $sync->createProduct($productData);

    if ($result['success']) {
        $testProductId = $result['product_id'];
        echo "✓ Product created successfully: ID = $testProductId\n";
        echo "  SKU: {$productData['sku']}\n";
        echo "  Name: {$productData['name']}\n";

        // Verify it's in vend_products
        $stmt = db_ro()->prepare("SELECT COUNT(*) FROM vend_products WHERE id = ?");
        $stmt->execute([$testProductId]);
        $inDB = $stmt->fetchColumn();

        if ($inDB) {
            echo "✓ Product found in vend_products table\n";
            $testResults['product_create'] = 'PASS';
        } else {
            echo "✗ Product NOT found in vend_products table\n";
            $testResults['product_create'] = 'FAIL';
        }
    } else {
        echo "✗ Failed to create product: {$result['error']}\n";
        $testResults['product_create'] = 'FAIL';
    }
}

// ═══════════════════════════════════════════════════════════════════════════
// TEST 2: UPDATE PRODUCT
// ═══════════════════════════════════════════════════════════════════════════

if ($TESTS_ENABLED['product_update'] && $testProductId) {
    echo "\n[TEST 2] Updating Product...\n";

    $updates = [
        'name' => 'Updated Test Product ' . time(),
        'retail_price' => 29.99,
    ];

    $result = $sync->updateProduct($testProductId, $updates);

    if ($result['success']) {
        echo "✓ Product updated successfully\n";
        echo "  New name: {$updates['name']}\n";
        echo "  New price: \${$updates['retail_price']}\n";
        $testResults['product_update'] = 'PASS';
    } else {
        echo "✗ Failed to update product: {$result['error']}\n";
        $testResults['product_update'] = 'FAIL';
    }
}

// ═══════════════════════════════════════════════════════════════════════════
// TEST 3: INVENTORY UPDATE
// ═══════════════════════════════════════════════════════════════════════════

if ($TESTS_ENABLED['inventory_update'] && $testProductId) {
    echo "\n[TEST 3] Updating Inventory Quantity...\n";

    // Get first outlet
    $stmt = db_ro()->query("SELECT id FROM vend_outlets LIMIT 1");
    $outletId = $stmt->fetchColumn();

    if ($outletId) {
        $newQuantity = 100;
        $result = $sync->updateInventory($testProductId, $outletId, $newQuantity, 'API Test');

        if ($result['success']) {
            echo "✓ Inventory updated successfully\n";
            echo "  Product: $testProductId\n";
            echo "  Outlet: $outletId\n";
            echo "  Quantity: $newQuantity\n";
            echo "  CIS Updated: " . ($result['cis_updated'] ? 'YES' : 'NO') . "\n";

            // Verify in vend_inventory
            $stmt = db_ro()->prepare("SELECT count FROM vend_inventory WHERE product_id = ? AND outlet_id = ?");
            $stmt->execute([$testProductId, $outletId]);
            $count = $stmt->fetchColumn();

            if ($count == $newQuantity) {
                echo "✓ Inventory count verified in vend_inventory: $count\n";
                $testResults['inventory_update'] = 'PASS';
            } else {
                echo "✗ Inventory mismatch: expected $newQuantity, got $count\n";
                $testResults['inventory_update'] = 'FAIL';
            }
        } else {
            echo "✗ Failed to update inventory: {$result['error']}\n";
            $testResults['inventory_update'] = 'FAIL';
        }
    } else {
        echo "✗ No outlets found in database\n";
        $testResults['inventory_update'] = 'SKIP';
    }
}

// ═══════════════════════════════════════════════════════════════════════════
// TEST 4: INVENTORY ADJUST
// ═══════════════════════════════════════════════════════════════════════════

if ($TESTS_ENABLED['inventory_adjust'] && $testProductId) {
    echo "\n[TEST 4] Adjusting Inventory (Relative Change)...\n";

    $stmt = db_ro()->query("SELECT id FROM vend_outlets LIMIT 1");
    $outletId = $stmt->fetchColumn();

    if ($outletId) {
        // Get current quantity
        $stmt = db_ro()->prepare("SELECT count FROM vend_inventory WHERE product_id = ? AND outlet_id = ?");
        $stmt->execute([$testProductId, $outletId]);
        $beforeQty = $stmt->fetchColumn() ?: 0;

        $adjustment = -10; // Decrease by 10
        $result = $sync->adjustInventory($testProductId, $outletId, $adjustment, 'Test adjustment');

        if ($result['success']) {
            // Get new quantity
            $stmt->execute([$testProductId, $outletId]);
            $afterQty = $stmt->fetchColumn() ?: 0;

            $expected = max(0, $beforeQty + $adjustment);

            echo "✓ Inventory adjusted successfully\n";
            echo "  Before: $beforeQty\n";
            echo "  Adjustment: $adjustment\n";
            echo "  After: $afterQty\n";
            echo "  Expected: $expected\n";

            if ($afterQty == $expected) {
                echo "✓ Adjustment calculation correct\n";
                $testResults['inventory_adjust'] = 'PASS';
            } else {
                echo "✗ Adjustment mismatch\n";
                $testResults['inventory_adjust'] = 'FAIL';
            }
        } else {
            echo "✗ Failed to adjust inventory: {$result['error']}\n";
            $testResults['inventory_adjust'] = 'FAIL';
        }
    } else {
        $testResults['inventory_adjust'] = 'SKIP';
    }
}

// ═══════════════════════════════════════════════════════════════════════════
// TEST 5: BULK INVENTORY UPDATE
// ═══════════════════════════════════════════════════════════════════════════

if ($TESTS_ENABLED['inventory_bulk'] && $testProductId) {
    echo "\n[TEST 5] Bulk Inventory Update...\n";

    // Create test file
    $testFile = '/tmp/test-inventory-bulk.json';
    $stmt = db_ro()->query("SELECT id FROM vend_outlets LIMIT 3");
    $outlets = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (!empty($outlets)) {
        $bulkUpdates = [];
        foreach ($outlets as $outlet) {
            $bulkUpdates[] = [
                'product_id' => $testProductId,
                'outlet_id' => $outlet,
                'quantity' => rand(50, 150),
                'reason' => 'Bulk API test'
            ];
        }

        file_put_contents($testFile, json_encode($bulkUpdates, JSON_PRETTY_PRINT));

        echo "  Created test file with " . count($bulkUpdates) . " updates\n";

        $result = $sync->bulkInventoryUpdate($bulkUpdates);

        if ($result['success']) {
            echo "✓ Bulk update successful\n";
            echo "  Updated: {$result['updated']}\n";
            echo "  Failed: {$result['failed']}\n";

            if ($result['updated'] == count($bulkUpdates)) {
                $testResults['inventory_bulk'] = 'PASS';
            } else {
                $testResults['inventory_bulk'] = 'PARTIAL';
            }
        } else {
            echo "✗ Bulk update failed\n";
            $testResults['inventory_bulk'] = 'FAIL';
        }

        unlink($testFile);
    } else {
        $testResults['inventory_bulk'] = 'SKIP';
    }
}

// ═══════════════════════════════════════════════════════════════════════════
// TEST 6: CREATE SUPPLIER
// ═══════════════════════════════════════════════════════════════════════════

if ($TESTS_ENABLED['supplier_create']) {
    echo "\n[TEST 6] Creating Supplier...\n";

    $supplierData = [
        'name' => $TEST_SUPPLIER_NAME,
        'email' => 'test@supplier.com',
        'phone' => '555-1234',
    ];

    $result = $sync->createSupplier($supplierData);

    if ($result['success']) {
        $testSupplierId = $result['supplier_id'];
        echo "✓ Supplier created successfully: ID = $testSupplierId\n";
        echo "  Name: {$supplierData['name']}\n";
        $testResults['supplier_create'] = 'PASS';
    } else {
        echo "✗ Failed to create supplier: {$result['error']}\n";
        $testResults['supplier_create'] = 'FAIL';
    }
}

// ═══════════════════════════════════════════════════════════════════════════
// TEST 7: UPDATE SUPPLIER
// ═══════════════════════════════════════════════════════════════════════════

if ($TESTS_ENABLED['supplier_update'] && $testSupplierId) {
    echo "\n[TEST 7] Updating Supplier...\n";

    $updates = [
        'phone' => '555-9999',
        'email' => 'updated@supplier.com',
    ];

    $result = $sync->updateSupplier($testSupplierId, $updates);

    if ($result['success']) {
        echo "✓ Supplier updated successfully\n";
        echo "  New phone: {$updates['phone']}\n";
        echo "  New email: {$updates['email']}\n";
        $testResults['supplier_update'] = 'PASS';
    } else {
        echo "✗ Failed to update supplier: {$result['error']}\n";
        $testResults['supplier_update'] = 'FAIL';
    }
}

// ═══════════════════════════════════════════════════════════════════════════
// TEST 8: CIS BIDIRECTIONAL SYNC
// ═══════════════════════════════════════════════════════════════════════════

if ($TESTS_ENABLED['cis_bidirectional_sync'] && $testProductId) {
    echo "\n[TEST 8] Testing CIS Bidirectional Sync...\n";

    // Check if CIS inventory table exists
    $stmt = db_ro()->query("SHOW TABLES LIKE 'inventory'");
    $tableExists = $stmt->fetch();

    if ($tableExists) {
        $stmt = db_ro()->query("SELECT id FROM vend_outlets LIMIT 1");
        $outletId = $stmt->fetchColumn();

        if ($outletId) {
            // Update via Lightspeed API
            $newQty = 250;
            $sync->updateInventory($testProductId, $outletId, $newQty, 'CIS sync test');

            // Check CIS inventory table
            $stmt = db_ro()->prepare("SELECT quantity FROM inventory WHERE product_id = ? AND outlet_id = ?");
            $stmt->execute([$testProductId, $outletId]);
            $cisQty = $stmt->fetchColumn();

            if ($cisQty == $newQty) {
                echo "✓ CIS inventory table updated successfully\n";
                echo "  Lightspeed quantity: $newQty\n";
                echo "  CIS quantity: $cisQty\n";
                $testResults['cis_bidirectional_sync'] = 'PASS';
            } else {
                echo "✗ CIS inventory mismatch: expected $newQty, got $cisQty\n";
                $testResults['cis_bidirectional_sync'] = 'FAIL';
            }
        } else {
            $testResults['cis_bidirectional_sync'] = 'SKIP';
        }
    } else {
        echo "  CIS inventory table doesn't exist - skipping\n";
        $testResults['cis_bidirectional_sync'] = 'SKIP';
    }
}

// ═══════════════════════════════════════════════════════════════════════════
// TEST 9: DELETE PRODUCT (CLEANUP)
// ═══════════════════════════════════════════════════════════════════════════

if ($TESTS_ENABLED['product_delete'] && $testProductId) {
    echo "\n[TEST 9] Deleting Test Product (Cleanup)...\n";

    $result = $sync->deleteProduct($testProductId);

    if ($result['success']) {
        echo "✓ Product deleted successfully\n";

        // Verify soft delete
        $stmt = db_ro()->prepare("SELECT is_deleted FROM vend_products WHERE id = ?");
        $stmt->execute([$testProductId]);
        $isDeleted = $stmt->fetchColumn();

        if ($isDeleted) {
            echo "✓ Product marked as deleted in database\n";
            $testResults['product_delete'] = 'PASS';
        } else {
            echo "✗ Product not marked as deleted\n";
            $testResults['product_delete'] = 'FAIL';
        }
    } else {
        echo "✗ Failed to delete product: {$result['error']}\n";
        $testResults['product_delete'] = 'FAIL';
    }
}

// ═══════════════════════════════════════════════════════════════════════════
// FINAL RESULTS
// ═══════════════════════════════════════════════════════════════════════════

echo "\n";
echo "═══════════════════════════════════════════════════════════════════════\n";
echo " TEST RESULTS SUMMARY\n";
echo "═══════════════════════════════════════════════════════════════════════\n";
echo "\n";

$passed = 0;
$failed = 0;
$skipped = 0;

foreach ($testResults as $test => $result) {
    $status = match($result) {
        'PASS' => '✓ PASS',
        'FAIL' => '✗ FAIL',
        'SKIP' => '- SKIP',
        'PARTIAL' => '△ PARTIAL',
        default => '? UNKNOWN'
    };

    if ($result === 'PASS') $passed++;
    elseif ($result === 'FAIL') $failed++;
    elseif ($result === 'SKIP') $skipped++;

    printf("%-30s %s\n", $test, $status);
}

echo "\n";
echo "Total: " . count($testResults) . " tests\n";
echo "Passed: $passed\n";
echo "Failed: $failed\n";
echo "Skipped: $skipped\n";

$successRate = count($testResults) > 0 ? round(($passed / count($testResults)) * 100) : 0;
echo "Success Rate: $successRate%\n";

echo "\n";

exit($failed > 0 ? 1 : 0);
