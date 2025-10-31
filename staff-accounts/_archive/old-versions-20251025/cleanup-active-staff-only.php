<?php
require_once 'bootstrap.php';

echo "=== CLEANING UP DEDUCTIONS - ACTIVE STAFF ONLY ===\n\n";

// Step 1: Remove mappings for inactive staff
echo "Step 1: Removing mappings for inactive staff...\n";
$stmt = $pdo->prepare("
    UPDATE xero_payroll_deductions d
    LEFT JOIN users u ON d.vend_customer_id = u.vend_customer_account
    SET d.vend_customer_id = NULL, d.user_id = NULL
    WHERE u.staff_active != 1 OR u.staff_active IS NULL
");
$stmt->execute();
$removedCount = $stmt->rowCount();
echo "✅ Removed $removedCount mappings for inactive staff\n\n";

// Step 2: Re-map only active staff
echo "Step 2: Re-mapping deductions for ACTIVE staff only...\n";

$stmt = $pdo->query("
    SELECT DISTINCT employee_name, xero_employee_id
    FROM xero_payroll_deductions 
    WHERE vend_customer_id IS NULL
    AND employee_name NOT LIKE 'Unknown%'
    ORDER BY employee_name
");

$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
$matched = 0;
$notMatched = 0;

echo "Found " . count($employees) . " employees without mappings\n\n";

foreach ($employees as $employee) {
    $employeeName = $employee['employee_name'];
    $xeroEmployeeId = $employee['xero_employee_id'];
    
    echo "Processing: $employeeName\n";
    
    // Try to match by full name in ACTIVE users only
    $stmt = $pdo->prepare("
        SELECT 
            id as user_id,
            vend_customer_account as vend_customer_id,
            CONCAT(first_name, ' ', last_name) as full_name,
            staff_active
        FROM users 
        WHERE CONCAT(first_name, ' ', last_name) = ?
        AND vend_customer_account IS NOT NULL
        AND staff_active = 1
        LIMIT 1
    ");
    $stmt->execute([$employeeName]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result) {
        echo "  ✅ Matched to ACTIVE user: {$result['full_name']} -> Vend: {$result['vend_customer_id']}\n";
        
        // Update all deductions for this employee
        $updateStmt = $pdo->prepare("
            UPDATE xero_payroll_deductions 
            SET user_id = ?, vend_customer_id = ? 
            WHERE xero_employee_id = ? AND vend_customer_id IS NULL
        ");
        $updateStmt->execute([
            $result['user_id'],
            $result['vend_customer_id'],
            $xeroEmployeeId
        ]);
        
        $updated = $updateStmt->rowCount();
        echo "  → Updated $updated deduction records\n";
        $matched++;
    } else {
        // Check if user exists but is inactive
        $stmt = $pdo->prepare("
            SELECT 
                CONCAT(first_name, ' ', last_name) as full_name,
                staff_active,
                vend_customer_account
            FROM users 
            WHERE CONCAT(first_name, ' ', last_name) = ?
            LIMIT 1
        ");
        $stmt->execute([$employeeName]);
        $inactiveUser = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($inactiveUser) {
            if ($inactiveUser['staff_active'] != 1) {
                echo "  ⚠️  User exists but is INACTIVE (staff_active = {$inactiveUser['staff_active']})\n";
            } elseif (!$inactiveUser['vend_customer_account']) {
                echo "  ⚠️  User is active but has no vend_customer_account\n";
            }
        } else {
            echo "  ❌ No user found with this name\n";
        }
        
        $notMatched++;
    }
    
    echo "\n";
}

echo "=== STEP 2 SUMMARY ===\n";
echo "✅ Matched: $matched active employees\n";
echo "❌ Not matched: $notMatched employees\n\n";

// Step 3: Final status
echo "Step 3: Final status check...\n";
$stmt = $pdo->query("
    SELECT 
        COUNT(*) as total,
        COUNT(d.vend_customer_id) as mapped_total,
        COUNT(CASE WHEN u.staff_active = 1 THEN 1 END) as mapped_active
    FROM xero_payroll_deductions d
    LEFT JOIN users u ON d.vend_customer_id = u.vend_customer_account
    WHERE d.allocation_status = 'pending'
");
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

echo "\n=== FINAL STATUS ===\n";
echo "Total pending deductions: {$stats['total']}\n";
echo "Mapped to Vend customers: {$stats['mapped_total']}\n";
echo "Mapped to ACTIVE staff: {$stats['mapped_active']}\n";
echo "Still unmapped: " . ($stats['total'] - $stats['mapped_total']) . "\n";

if ($stats['mapped_active'] > 0) {
    echo "\n🎉 SUCCESS! {$stats['mapped_active']} deductions are mapped to ACTIVE staff!\n";
    echo "Inactive staff deductions have been excluded from allocation.\n";
}

// Step 4: Show breakdown by staff status
echo "\nStep 4: Breakdown by staff status...\n";
$stmt = $pdo->query("
    SELECT 
        CASE 
            WHEN u.staff_active = 1 THEN 'Active Staff'
            WHEN u.staff_active = 0 THEN 'Inactive Staff'
            WHEN u.staff_active IS NULL AND d.vend_customer_id IS NOT NULL THEN 'Unknown Status'
            ELSE 'Not Mapped'
        END as staff_status,
        COUNT(*) as deduction_count,
        COUNT(DISTINCT d.employee_name) as employee_count
    FROM xero_payroll_deductions d
    LEFT JOIN users u ON d.vend_customer_id = u.vend_customer_account
    WHERE d.allocation_status = 'pending'
    GROUP BY staff_status
    ORDER BY deduction_count DESC
");

echo "\nDEDUCTIONS BY STAFF STATUS:\n";
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "- {$row['staff_status']}: {$row['deduction_count']} deductions from {$row['employee_count']} employees\n";
}
?>