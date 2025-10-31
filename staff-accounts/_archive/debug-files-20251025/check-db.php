#!/usr/bin/env php
<?php
require_once __DIR__ . '/bootstrap.php';

try {
    // Check payrolls
    $stmt = $pdo->query('SELECT COUNT(*) as total FROM xero_payrolls');
    $payrollCount = $stmt->fetchColumn();
    echo "✅ Total payrolls in DB: {$payrollCount}\n";
    
    // Check deductions
    $stmt = $pdo->query('SELECT COUNT(*) as total FROM xero_payroll_deductions');
    $deductionCount = $stmt->fetchColumn();
    echo "✅ Total deductions in DB: {$deductionCount}\n";
    
    // Show recent payrolls
    $stmt = $pdo->query('SELECT xero_payroll_id, payment_date, employee_count, total_gross_pay, status FROM xero_payrolls ORDER BY payment_date DESC LIMIT 5');
    $payrolls = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "\n📋 Recent Payrolls:\n";
    foreach ($payrolls as $pr) {
        echo sprintf("  - %s | %s | %d employees | $%.2f | %s\n", 
            substr($pr['xero_payroll_id'], 0, 8),
            $pr['payment_date'],
            $pr['employee_count'],
            $pr['total_gross_pay'],
            $pr['status']
        );
    }
    
    // Check pending deductions
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM xero_payroll_deductions WHERE allocation_status = 'pending'");
    $pendingCount = $stmt->fetchColumn();
    echo "\n⏳ Pending deductions: {$pendingCount}\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
