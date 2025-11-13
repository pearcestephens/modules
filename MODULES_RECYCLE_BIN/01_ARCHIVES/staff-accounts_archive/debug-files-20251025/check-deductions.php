#!/usr/bin/env php
<?php
require_once __DIR__ . '/bootstrap.php';

use CIS\Modules\StaffAccounts\XeroPayrollService;

global $payrollNzApi, $xeroTenantId;

try {
    // Get a recent payroll
    $stmt = $pdo->query('SELECT xero_payroll_id, payment_date FROM xero_payrolls ORDER BY payment_date DESC LIMIT 1');
    $payroll = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$payroll) {
        echo "❌ No payrolls found\n";
        exit;
    }
    
    echo "📋 Checking PaySlips for PayRun: {$payroll['xero_payroll_id']} ({$payroll['payment_date']})\n\n";
    
    // Fetch payslips
    $paySlipsResp = $payrollNzApi->getPaySlips($xeroTenantId, $payroll['xero_payroll_id'], 1);
    $paySlips = $paySlipsResp->getPaySlips();
    
    echo "Found " . count($paySlips) . " PaySlips\n\n";
    
    // Check first payslip for deductions
    if (count($paySlips) > 0) {
        $slip = $paySlips[0];
        $employeeId = $slip->getEmployeeID();
        
        echo "Employee ID: {$employeeId}\n";
        echo "Total Earnings: " . $slip->getTotalEarnings() . "\n";
        echo "Total Deductions: " . $slip->getTotalDeductions() . "\n\n";
        
        $deductionLines = $slip->getDeductionLines();
        echo "Deduction Lines: " . count($deductionLines) . "\n\n";
        
        foreach ($deductionLines as $line) {
            $typeId = $line->getDeductionTypeID();
            $amount = $line->getAmount();
            
            // Try to get deduction name
            try {
                $dedResp = $payrollNzApi->getDeduction($xeroTenantId, $typeId);
                $ded = $dedResp->getDeduction();
                $name = $ded->getDeductionName();
            } catch (Exception $e) {
                $name = "Unknown (error: " . $e->getMessage() . ")";
            }
            
            echo "  - Type: {$name}\n";
            echo "    TypeID: {$typeId}\n";
            echo "    Amount: \${$amount}\n\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
