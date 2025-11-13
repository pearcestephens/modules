<?php
require_once 'bootstrap.php';

echo "=== UPDATING EXISTING DEDUCTIONS WITH VEND CUSTOMER MAPPING ===\n\n";

// Get all deductions that don't have vend_customer_id mapped
$stmt = $pdo->query("
    SELECT DISTINCT employee_name, xero_employee_id
    FROM xero_payroll_deductions 
    WHERE vend_customer_id IS NULL
    ORDER BY employee_name
");

$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
$matched = 0;
$notMatched = 0;

echo "Found " . count($employees) . " employees without Vend customer mapping\n\n";

foreach ($employees as $employee) {
    $employeeName = $employee['employee_name'];
    $xeroEmployeeId = $employee['xero_employee_id'];
    
    echo "Processing: $employeeName\n";
    
    // Try to match by full name in users table
    $stmt = $pdo->prepare("
        SELECT 
            id as user_id,
            vend_customer_account as vend_customer_id,
            CONCAT(first_name, ' ', last_name) as full_name
        FROM users 
        WHERE CONCAT(first_name, ' ', last_name) = ?
        AND vend_customer_account IS NOT NULL
        LIMIT 1
    ");
    $stmt->execute([$employeeName]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result) {
        echo "  ✅ Matched to user: {$result['full_name']} -> Vend Customer: {$result['vend_customer_id']}\n";
        
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
        echo "  ❌ No matching user found with vend_customer_account\n";
        
        // Show similar names for debugging
        $stmt = $pdo->prepare("
            SELECT CONCAT(first_name, ' ', last_name) as full_name, vend_customer_account
            FROM users 
            ORDER BY full_name
        ");
        $stmt->execute();
        $allUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "  📋 Available users:\n";
        foreach (array_slice($allUsers, 0, 5) as $user) {
            $vendStatus = $user['vend_customer_account'] ? "✅ {$user['vend_customer_account']}" : "❌ No Vend Account";
            echo "    - {$user['full_name']} ($vendStatus)\n";
        }
        echo "    ... and " . (count($allUsers) - 5) . " more\n";
        
        $notMatched++;
    }
    
    echo "\n";
}

echo "=== SUMMARY ===\n";
echo "✅ Matched: $matched employees\n";
echo "❌ Not matched: $notMatched employees\n";

// Check final status
$stmt = $pdo->query("
    SELECT 
        COUNT(*) as total,
        COUNT(vend_customer_id) as mapped
    FROM xero_payroll_deductions 
    WHERE allocation_status = 'pending'
");
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

echo "\n=== FINAL STATUS ===\n";
echo "Total pending deductions: {$stats['total']}\n";
echo "Mapped to Vend customers: {$stats['mapped']}\n";
echo "Still unmapped: " . ($stats['total'] - $stats['mapped']) . "\n";

if ($stats['mapped'] > 0) {
    echo "\n🎉 SUCCESS! Some deductions are now mapped and should show Customer IDs in the UI!\n";
}
?>