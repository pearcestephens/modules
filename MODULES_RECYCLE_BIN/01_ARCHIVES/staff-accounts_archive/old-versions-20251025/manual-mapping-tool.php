<?php
/**
 * Manual Vend Customer Code Mapping Tool
 * Map employees using actual Vend customer codes
 */

require_once '../shared/bootstrap.php';

// Authentication - use CIS standard function
require_once ROOT_PATH . '/assets/functions/config.php';
cis_require_login();

echo "=== MANUAL VEND CUSTOMER CODE MAPPING ===\n\n";

// 1. Show all unmapped employees
echo "1. UNMAPPED EMPLOYEES NEEDING CUSTOMER CODES:\n";
echo str_repeat("-", 80) . "\n";

$stmt = $pdo->query("
    SELECT 
        employee_name,
        COUNT(*) as deduction_count,
        SUM(amount) as total_amount,
        MIN(created_at) as first_deduction,
        MAX(created_at) as last_deduction
    FROM xero_payroll_deductions 
    WHERE vend_customer_id IS NULL
    GROUP BY employee_name
    ORDER BY deduction_count DESC
");

$unmappedEmployees = [];
printf("%-25s %-8s %-10s %-12s %s\n", "Employee", "Count", "Amount", "First", "Last");
echo str_repeat("-", 80) . "\n";

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $unmappedEmployees[] = $row['employee_name'];
    printf("%-25s %-8d $%-9.2f %-12s %s\n", 
        $row['employee_name'], 
        $row['deduction_count'],
        $row['total_amount'],
        substr($row['first_deduction'], 0, 10),
        substr($row['last_deduction'], 0, 10)
    );
}

echo "\n2. AVAILABLE VEND CUSTOMER CODES:\n";
echo str_repeat("-", 80) . "\n";
printf("%-30s %-40s %s\n", "Staff Name", "Vend Customer Code", "Status");
echo str_repeat("-", 80) . "\n";

$stmt = $pdo->query("
    SELECT 
        CONCAT(first_name, ' ', last_name) as full_name,
        vend_customer_account,
        staff_active
    FROM users 
    WHERE vend_customer_account IS NOT NULL
    ORDER BY full_name
");

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $status = $row['staff_active'] ? 'ACTIVE' : 'INACTIVE';
    printf("%-30s %-40s %s\n", 
        $row['full_name'], 
        $row['vend_customer_account'], 
        $status
    );
}

echo "\n3. MAPPING COMMANDS:\n";
echo str_repeat("-", 80) . "\n";
echo "Copy and paste these commands with the correct customer codes:\n\n";

foreach ($unmappedEmployees as $employeeName) {
    if ($employeeName === 'Unknown Employee') {
        echo "-- Skipping 'Unknown Employee' (system entries)\n";
        continue;
    }
    
    echo "-- Map $employeeName:\n";
    echo "UPDATE xero_payroll_deductions SET vend_customer_id = 'CUSTOMER_CODE_HERE' WHERE employee_name = '$employeeName';\n\n";
}

echo "\n4. EXAMPLE FOR BILLY/BRYDAN:\n";
echo str_repeat("-", 80) . "\n";
echo "You mentioned you identified Billy/Brydan via his customer code.\n";
echo "If his Vend customer code is (for example) '02dcd191-ae14-11e7-edd8-06d28d4a9880':\n\n";
echo "UPDATE xero_payroll_deductions \n";
echo "SET vend_customer_id = '02dcd191-ae14-11e7-edd8-06d28d4a9880' \n";
echo "WHERE employee_name = 'Brydan Downs';\n\n";

echo "PLEASE PROVIDE THE CORRECT CUSTOMER CODES FOR EACH UNMAPPED EMPLOYEE!\n";
?>