<?php
/**
 * Test Database Connectivity and Schema
 *
 * Verifies all required tables exist and are accessible
 *
 * @package CIS\Modules\StaffAccounts\Tests
 * @version 1.0.0
 */

require_once __DIR__ . '/../bootstrap.php';

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "  DATABASE CONNECTIVITY TEST\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

try {
    $pdo = cis_resolve_pdo();
    echo "✅ PDO connection successful\n\n";
} catch (Exception $e) {
    echo "❌ CRITICAL: Cannot connect to database!\n";
    echo "Error: " . $e->getMessage() . "\n\n";
    exit(1);
}

// Required tables
$requiredTables = [
    'users',
    'vend_customers',
    'vend_users',
    'staff_payment_transactions',
    'staff_account_reconciliation',
    'staff_saved_cards',
    'staff_payment_plans',
    'xero_payroll_deductions'
];

echo "1️⃣  Checking required tables exist...\n\n";

$allPresent = true;
$totalRows = 0;

foreach ($requiredTables as $table) {
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE '{$table}'");
        $exists = $stmt->rowCount() > 0;

        if ($exists) {
            $countStmt = $pdo->query("SELECT COUNT(*) as cnt FROM {$table}");
            $count = $countStmt->fetch(PDO::FETCH_ASSOC)['cnt'];
            $totalRows += $count;
            printf("   ✅ %-40s %10s rows\n", $table, number_format($count));
        } else {
            printf("   ❌ %-40s MISSING!\n", $table);
            $allPresent = false;
        }
    } catch (Exception $e) {
        printf("   ❌ %-40s ERROR: %s\n", $table, $e->getMessage());
        $allPresent = false;
    }
}

echo "\n   Total rows across all tables: " . number_format($totalRows) . "\n\n";

if (!$allPresent) {
    echo "❌ CRITICAL: Some required tables missing!\n\n";
    exit(1);
}

// Test key queries
echo "2️⃣  Testing key queries...\n\n";

// Test 1: Staff with Vend accounts
try {
    $stmt = $pdo->query("
        SELECT COUNT(*) as cnt
        FROM users u
        WHERE u.staff_active = 1
        AND u.vend_customer_account IS NOT NULL
        AND u.vend_customer_account != ''
    ");
    $staffCount = $stmt->fetch(PDO::FETCH_ASSOC)['cnt'];
    echo "   ✅ Staff with Vend accounts: {$staffCount}\n";
} catch (Exception $e) {
    echo "   ❌ Staff query failed: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 2: Recent transactions
try {
    $stmt = $pdo->query("
        SELECT COUNT(*) as cnt
        FROM staff_payment_transactions
        WHERE created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");
    $txnCount = $stmt->fetch(PDO::FETCH_ASSOC)['cnt'];
    echo "   ✅ Recent transactions (30 days): {$txnCount}\n";
} catch (Exception $e) {
    echo "   ⚠️  Transaction query warning: " . $e->getMessage() . "\n";
}

// Test 3: Current balances
try {
    $stmt = $pdo->query("
        SELECT
            COUNT(*) as cnt,
            SUM(CASE WHEN vend_balance < 0 THEN 1 ELSE 0 END) as negative_count,
            SUM(vend_balance) as total_balance
        FROM staff_account_reconciliation
    ");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "   ✅ Total accounts: {$result['cnt']}\n";
    echo "   ✅ Accounts with balance: {$result['negative_count']}\n";
    echo "   ✅ Total outstanding: $" . number_format($result['total_balance'], 2) . "\n";
} catch (Exception $e) {
    echo "   ⚠️  Balance query warning: " . $e->getMessage() . "\n";
}

// Test 4: Vend customer linkage
try {
    $stmt = $pdo->query("
        SELECT COUNT(*) as cnt
        FROM users u
        INNER JOIN vend_customers vc ON u.vend_customer_account = vc.id
        WHERE u.staff_active = 1
    ");
    $linkedCount = $stmt->fetch(PDO::FETCH_ASSOC)['cnt'];
    echo "   ✅ Staff linked to Vend customers: {$linkedCount}\n";
} catch (Exception $e) {
    echo "   ❌ Vend linkage query failed: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n";

// Test 5: Database write permissions
echo "3️⃣  Testing write permissions...\n\n";

try {
    // Try to create and delete a test record
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        INSERT INTO staff_payment_transactions
        (user_id, amount, transaction_type, request_id, response_data, created_at)
        VALUES (1, 0.01, 'payment_approved', 'TEST_WRITE_PERMISSION', '{}', NOW())
    ");
    $stmt->execute();

    $testId = $pdo->lastInsertId();

    $stmt = $pdo->prepare("DELETE FROM staff_payment_transactions WHERE id = ?");
    $stmt->execute([$testId]);

    $pdo->rollBack();

    echo "   ✅ Database write permissions OK\n";
} catch (Exception $e) {
    $pdo->rollBack();
    echo "   ❌ CRITICAL: Cannot write to database!\n";
    echo "   Error: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "  ✅ DATABASE TEST COMPLETE\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

exit(0);
