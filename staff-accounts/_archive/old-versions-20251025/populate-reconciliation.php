#!/usr/bin/env php
<?php
/**
 * Populate Staff Account Reconciliation Table
 * 
 * Creates reconciliation records for all staff members by:
 * 1. Getting all users with vend_customer_account
 * 2. Calculating total Xero deductions per employee
 * 3. Creating reconciliation records (Vend balance will be synced separately)
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

echo "═══════════════════════════════════════════════════════\n";
echo "Staff Account Reconciliation - Initial Population\n";
echo "═══════════════════════════════════════════════════════\n\n";

try {
    $pdo->beginTransaction();
    
    // Step 1: Get all users with Vend customer accounts
    echo "Step 1: Loading staff with Vend customer accounts...\n";
    $stmt = $pdo->query("
        SELECT 
            id as user_id,
            CONCAT(first_name, ' ', last_name) as full_name,
            xero_id,
            vend_customer_account as vend_customer_id
        FROM users
        WHERE vend_customer_account IS NOT NULL
        ORDER BY last_name, first_name
    ");
    $staffMembers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "✓ Found " . count($staffMembers) . " staff members\n\n";
    
    // Step 2: Get Xero deduction totals per employee
    echo "Step 2: Calculating Xero deduction totals...\n";
    $stmt = $pdo->query("
        SELECT 
            xero_employee_id,
            SUM(amount) as total_deductions
        FROM xero_payroll_deductions
        GROUP BY xero_employee_id
    ");
    $xeroDeductions = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $xeroDeductions[$row['xero_employee_id']] = (float)$row['total_deductions'];
    }
    echo "✓ Calculated deductions for " . count($xeroDeductions) . " employees\n\n";
    
    // Step 3: Insert reconciliation records
    echo "Step 3: Creating reconciliation records...\n";
    
    $insertStmt = $pdo->prepare("
        INSERT INTO staff_account_reconciliation (
            user_id,
            vend_customer_id,
            employee_name,
            total_xero_deductions,
            total_allocated,
            pending_allocation,
            vend_balance,
            credit_limit,
            outstanding_amount,
            status,
            created_at,
            updated_at
        ) VALUES (
            :user_id,
            :vend_customer_id,
            :employee_name,
            :total_xero_deductions,
            0.00,
            :pending_allocation,
            0.00,
            0.00,
            0.00,
            'pending',
            NOW(),
            NOW()
        )
    ");
    
    $insertedCount = 0;
    $totalDeductions = 0;
    
    foreach ($staffMembers as $staff) {
        $xeroId = $staff['xero_id'];
        $deductions = $xeroDeductions[$xeroId] ?? 0.00;
        
        $insertStmt->execute([
            'user_id' => $staff['user_id'],
            'vend_customer_id' => $staff['vend_customer_id'],
            'employee_name' => $staff['full_name'],
            'total_xero_deductions' => $deductions,
            'pending_allocation' => $deductions // Initially all deductions are pending
        ]);
        
        $insertedCount++;
        $totalDeductions += $deductions;
        
        if ($insertedCount % 20 == 0) {
            echo "  Processed $insertedCount staff members...\n";
        }
    }
    
    $pdo->commit();
    
    echo "\n";
    echo "═══════════════════════════════════════════════════════\n";
    echo "✓ COMPLETE!\n";
    echo "═══════════════════════════════════════════════════════\n\n";
    echo "Records created: $insertedCount\n";
    echo "Total deductions: \$" . number_format($totalDeductions, 2) . "\n\n";
    
    // Show summary
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as total_staff,
            SUM(total_xero_deductions) as total_deductions,
            SUM(pending_allocation) as total_pending
        FROM staff_account_reconciliation
    ");
    $summary = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "Summary:\n";
    echo "  Total staff accounts: " . $summary['total_staff'] . "\n";
    echo "  Total Xero deductions: \$" . number_format($summary['total_deductions'], 2) . "\n";
    echo "  Pending allocation: \$" . number_format($summary['total_pending'], 2) . "\n\n";
    
    echo "Next steps:\n";
    echo "  1. Sync Vend customer balances (vend_balance column)\n";
    echo "  2. Calculate outstanding amounts (vend_balance - total_xero_deductions)\n";
    echo "  3. Update status based on outstanding amounts\n\n";
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
