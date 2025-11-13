<?php
/**
 * Proper Vend Customer ID Mapping
 * Use actual Vend customer codes instead of name matching
 */

require_once '../shared/bootstrap.php';

// Authentication - use CIS standard function
require_once ROOT_PATH . '/assets/functions/config.php';
cis_require_login();

echo "=== VEND CUSTOMER ID BASED MAPPING ===\n\n";

try {
    // 1. Show all unmapped Xero employees with their deduction details
    echo "1. UNMAPPED XERO EMPLOYEES:\n";
    echo str_repeat("-", 70) . "\n";
    
    $stmt = $pdo->query("
        SELECT 
            employee_name,
            pay_period_start,
            pay_period_end,
            deduction_type,
            amount,
            id
        FROM xero_payroll_deductions 
        WHERE vend_customer_id IS NULL
        ORDER BY employee_name, pay_period_start
        LIMIT 20
    ");
    
    $unmappedEmployees = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if (!isset($unmappedEmployees[$row['employee_name']])) {
            $unmappedEmployees[$row['employee_name']] = [];
        }
        $unmappedEmployees[$row['employee_name']][] = $row;
    }
    
    foreach ($unmappedEmployees as $employeeName => $deductions) {
        echo "\n🔍 EMPLOYEE: $employeeName\n";
        echo "   Deductions: " . count($deductions) . "\n";
        echo "   Sample deduction details:\n";
        $sample = $deductions[0];
        echo "   - Period: {$sample['pay_period_start']} to {$sample['pay_period_end']}\n";
        echo "   - Type: {$sample['deduction_type']}\n";
        echo "   - Amount: \${$sample['amount']}\n";
        echo "   - ID: {$sample['id']}\n";
    }
    
    // 2. Show available Vend customers with their codes
    echo "\n\n2. AVAILABLE VEND CUSTOMERS:\n";
    echo str_repeat("-", 70) . "\n";
    printf("%-30s %-40s %s\n", "Full Name", "Customer Code", "Active");
    echo str_repeat("-", 70) . "\n";
    
    $stmt = $pdo->query("
        SELECT 
            CONCAT(first_name, ' ', last_name) as full_name,
            vend_customer_account,
            staff_active
        FROM users 
        WHERE vend_customer_account IS NOT NULL
        ORDER BY staff_active DESC, full_name
    ");
    
    $vendCustomers = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $vendCustomers[] = $row;
        $status = $row['staff_active'] ? 'ACTIVE' : 'INACTIVE';
        printf("%-30s %-40s %s\n", 
            $row['full_name'], 
            $row['vend_customer_account'], 
            $status
        );
    }
    
    echo "\n\n3. MANUAL MAPPING INSTRUCTIONS:\n";
    echo str_repeat("-", 70) . "\n";
    echo "To manually map an employee, you can use this SQL:\n\n";
    echo "UPDATE xero_payroll_deductions \n";
    echo "SET vend_customer_id = 'CUSTOMER_CODE_HERE' \n";
    echo "WHERE employee_name = 'EMPLOYEE_NAME_HERE';\n\n";
    
    echo "For example, if Billy/Brydan should be mapped to customer code 'XYZ123':\n";
    echo "UPDATE xero_payroll_deductions \n";
    echo "SET vend_customer_id = 'XYZ123' \n";
    echo "WHERE employee_name = 'Brydan Downs';\n\n";
    
    // 4. Current mapping statistics
    echo "4. CURRENT MAPPING STATISTICS:\n";
    echo str_repeat("-", 70) . "\n";
    
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as total_deductions,
            SUM(CASE WHEN vend_customer_id IS NOT NULL THEN 1 ELSE 0 END) as mapped_deductions,
            COUNT(DISTINCT employee_name) as total_employees,
            COUNT(DISTINCT CASE WHEN vend_customer_id IS NOT NULL THEN employee_name END) as mapped_employees
        FROM xero_payroll_deductions
    ");
    
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    $unmapped_deductions = $stats['total_deductions'] - $stats['mapped_deductions'];
    $unmapped_employees = $stats['total_employees'] - $stats['mapped_employees'];
    $mapping_rate = round($stats['mapped_deductions'] / $stats['total_deductions'] * 100, 1);
    
    echo "Total Deductions: {$stats['total_deductions']}\n";
    echo "Mapped Deductions: {$stats['mapped_deductions']}\n";
    echo "Unmapped Deductions: $unmapped_deductions\n";
    echo "Mapping Rate: {$mapping_rate}%\n\n";
    
    echo "Total Employees: {$stats['total_employees']}\n";
    echo "Mapped Employees: {$stats['mapped_employees']}\n";
    echo "Unmapped Employees: $unmapped_employees\n\n";
    
    if ($unmapped_deductions > 0) {
        echo "🔧 PLEASE PROVIDE THE CORRECT VEND CUSTOMER CODES FOR:\n";
        
        $stmt = $pdo->query("
            SELECT DISTINCT employee_name, COUNT(*) as deduction_count
            FROM xero_payroll_deductions 
            WHERE vend_customer_id IS NULL
            GROUP BY employee_name
            ORDER BY deduction_count DESC
        ");
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo "   - {$row['employee_name']} ({$row['deduction_count']} deductions)\n";
        }
    }

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

echo "\n=== READY FOR MANUAL CUSTOMER CODE MAPPING ===\n";
?>