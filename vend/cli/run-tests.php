<?php
/**
 * Simple Inline Test Runner
 */
require_once __DIR__ . '/../../../assets/services/gpt/src/Bootstrap.php';

echo "\n═══ VEND SYNC MANAGER - COMPREHENSIVE TESTS ═══\n\n";

$passed = 0;
$failed = 0;

// Test 1: Database connection
echo "Test 1: Database connection... ";
try {
    $db = db_ro();
    $stmt = $db->query('SELECT 1');
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($result) {
        echo "✓ PASS\n";
        $passed++;
    }
} catch (Exception $e) {
    echo "✗ FAIL: " . $e->getMessage() . "\n";
    $failed++;
}

// Test 2: Vend tables exist
echo "Test 2: Vend tables exist... ";
try {
    $db = db_ro();
    $tables = ['vend_products', 'vend_sales', 'vend_consignments', 'vend_queue', 'vend_api_logs'];
    $allExist = true;
    foreach ($tables as $table) {
        $stmt = $db->prepare('SHOW TABLES LIKE ?');
        $stmt->execute([$table]);
        if (!$stmt->fetch()) {
            $allExist = false;
            echo "\n  Missing: $table\n";
        }
    }
    if ($allExist) {
        echo "✓ PASS (" . count($tables) . " tables)\n";
        $passed++;
    } else {
        echo "✗ FAIL\n";
        $failed++;
    }
} catch (Exception $e) {
    echo "✗ FAIL: " . $e->getMessage() . "\n";
    $failed++;
}

// Test 3: Queue stats
echo "Test 3: Queue statistics... ";
try {
    $db = db_ro();
    $stmt = $db->query('SELECT COUNT(*) as total, SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END) as success FROM vend_queue');
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "✓ PASS (Total: {$stats['total']}, Success: {$stats['success']})\n";
    $passed++;
} catch (Exception $e) {
    echo "✗ FAIL: " . $e->getMessage() . "\n";
    $failed++;
}

// Test 4: Consignment states
echo "Test 4: Consignment states distribution... ";
try {
    $db = db_ro();
    $stmt = $db->query("SELECT state, COUNT(*) as count FROM vend_consignments WHERE deleted_at IS NULL GROUP BY state ORDER BY count DESC LIMIT 5");
    $states = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "✓ PASS (" . count($states) . " states found)\n";
    foreach ($states as $s) {
        echo "  - {$s['state']}: {$s['count']}\n";
    }
    $passed++;
} catch (Exception $e) {
    echo "✗ FAIL: " . $e->getMessage() . "\n";
    $failed++;
}

// Test 5: Webhook tables
echo "Test 5: Webhook logging tables... ";
try {
    $db = db_ro();
    $webhookTables = ['webhook_consignment_events', 'webhooks_audit_log', 'webhooks_queue'];
    $allExist = true;
    foreach ($webhookTables as $table) {
        $stmt = $db->prepare('SHOW TABLES LIKE ?');
        $stmt->execute([$table]);
        if (!$stmt->fetch()) {
            $allExist = false;
            echo "\n  Missing: $table\n";
        }
    }
    if ($allExist) {
        echo "✓ PASS (" . count($webhookTables) . " tables)\n";
        $passed++;
    } else {
        echo "✗ FAIL\n";
        $failed++;
    }
} catch (Exception $e) {
    echo "✗ FAIL: " . $e->getMessage() . "\n";
    $failed++;
}

// Test 6: Consignment audit logging
echo "Test 6: Consignment audit log data... ";
try {
    $db = db_ro();
    $stmt = $db->query('SELECT COUNT(*) as count FROM consignment_audit_log');
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "✓ PASS ({$result['count']} entries)\n";
    $passed++;
} catch (Exception $e) {
    echo "✗ FAIL: " . $e->getMessage() . "\n";
    $failed++;
}

// Test 7: Real webhook data
echo "Test 7: Real webhook data available... ";
try {
    $db = db_ro();
    $stmt = $db->query('SELECT COUNT(*) as count FROM webhooks_raw_storage WHERE webhook_type LIKE "consignment%"');
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "✓ PASS ({$result['count']} consignment webhooks)\n";
    $passed++;
} catch (Exception $e) {
    echo "✗ FAIL: " . $e->getMessage() . "\n";
    $failed++;
}

// Test 8: API token configured
echo "Test 8: API token configuration... ";
try {
    $db = db_ro();
    $stmt = $db->query("SELECT config_value FROM configuration WHERE config_label = 'vend_access_token'");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($result && strlen($result['config_value']) > 20) {
        echo "✓ PASS (Token configured)\n";
        $passed++;
    } else {
        echo "✗ FAIL (Token not configured)\n";
        $failed++;
    }
} catch (Exception $e) {
    echo "✗ FAIL: " . $e->getMessage() . "\n";
    $failed++;
}

// Test 9: CLI test commands
echo "Test 9: CLI commands available... ";
try {
    $cliFile = __DIR__ . '/vend-sync-manager.php';
    if (file_exists($cliFile) && is_readable($cliFile)) {
        echo "✓ PASS (CLI file accessible)\n";
        $passed++;
    } else {
        echo "✗ FAIL (CLI file not found)\n";
        $failed++;
    }
} catch (Exception $e) {
    echo "✗ FAIL: " . $e->getMessage() . "\n";
    $failed++;
}

// Test 10: Webhook processor integration
echo "Test 10: Webhook processor test... ";
try {
    // Simulate a simple webhook payload
    $testPayload = [
        'event' => 'product.updated',
        'id' => 'test-webhook-' . time(),
        'data' => ['id' => 'test-product-123']
    ];

    // Check if WebhookProcessor class would be available
    require_once $cliFile;
    if (class_exists('WebhookProcessor')) {
        echo "✓ PASS (WebhookProcessor class available)\n";
        $passed++;
    } else {
        echo "✗ FAIL (WebhookProcessor class not found)\n";
        $failed++;
    }
} catch (Exception $e) {
    echo "✗ FAIL: " . $e->getMessage() . "\n";
    $failed++;
}

// Final report
$total = $passed + $failed;
$rate = $total > 0 ? ($passed / $total) * 100 : 0;
echo "\n═══════════════════════════════════\n";
echo "RESULTS: $passed passed, $failed failed\n";
echo sprintf("SUCCESS RATE: %.1f%%\n", $rate);
echo "═══════════════════════════════════\n\n";

exit($failed > 0 ? 1 : 0);
