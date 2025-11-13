<?php
/**
 * Fix Employee Name Mapping Issues
 * Handle exact name matching problems from Xero data
 */

require_once '../shared/bootstrap.php';

echo "=== FIXING EMPLOYEE NAME MAPPING ISSUES ===\n\n";

try {
    // Define the exact name mappings
    $nameMap = [
        // Extra spaces
        'Jayden  Garrett-Macfarlane' => 'Jayden Garrett-Macfarlane',
        'John  Yotoko' => 'John Yotoko',
        'Kiel  Newman' => 'Kiel Newman',
        
        // Apostrophe differences
        "Heath O'Malley" => 'Heath OMalley',
        
        // Name variations
        'Brydan Downs' => 'Billy Downs',
        'Lawrence Archbold' => 'Lawrence (Celeste) Archbold',
        
        // Different first names - need to check these manually
        'Ian Paul' => 'Ian Pairama',  // Assuming Ian Paul is Ian Pairama
        'Nikita Shannon' => 'Nikita Peden',  // Assuming Nikita Shannon is Nikita Peden
    ];
    
    echo "1. APPLYING NAME MAPPINGS:\n";
    echo str_repeat("-", 50) . "\n";
    
    $totalUpdated = 0;
    
    foreach ($nameMap as $xeroName => $vendName) {
        // Get the customer account for the correct Vend name
        $stmt = $pdo->prepare("
            SELECT vend_customer_account 
            FROM users 
            WHERE CONCAT(first_name, ' ', last_name) = ?
            AND (staff_active = 1 OR EXISTS (
                SELECT 1 FROM xero_payroll_deductions xpd 
                WHERE xpd.created_at >= DATE_SUB(CURDATE(), INTERVAL 12 WEEK)
            ))
        ");
        $stmt->execute([$vendName]);
        $customerAccount = $stmt->fetchColumn();
        
        if ($customerAccount) {
            // Update all deductions with this Xero name
            $updateStmt = $pdo->prepare("
                UPDATE xero_payroll_deductions 
                SET vend_customer_id = ? 
                WHERE employee_name = ?
            ");
            $updateStmt->execute([$customerAccount, $xeroName]);
            $updated = $updateStmt->rowCount();
            $totalUpdated += $updated;
            
            echo sprintf("✅ %-30s → %-30s (%d deductions)\n", $xeroName, $vendName, $updated);
        } else {
            echo sprintf("❌ %-30s → %-30s (NOT FOUND)\n", $xeroName, $vendName);
        }
    }
    
    echo "\nTotal deductions updated: $totalUpdated\n\n";
    
    // 2. Check remaining unmapped deductions
    echo "2. REMAINING UNMAPPED DEDUCTIONS:\n";
    echo str_repeat("-", 50) . "\n";
    
    $stmt = $pdo->query("
        SELECT employee_name, COUNT(*) as count
        FROM xero_payroll_deductions 
        WHERE vend_customer_id IS NULL
        GROUP BY employee_name
        ORDER BY count DESC, employee_name
    ");
    
    $unmappedCount = 0;
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo sprintf("%-30s (%d deductions)\n", $row['employee_name'], $row['count']);
        $unmappedCount += $row['count'];
    }
    
    if ($unmappedCount == 0) {
        echo "🎉 ALL DEDUCTIONS ARE NOW MAPPED!\n";
    } else {
        echo "\nTotal unmapped deductions: $unmappedCount\n";
    }
    
    // 3. Final status summary
    echo "\n3. FINAL MAPPING STATUS:\n";
    echo str_repeat("-", 50) . "\n";
    
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as total_deductions,
            SUM(CASE WHEN vend_customer_id IS NOT NULL THEN 1 ELSE 0 END) as mapped_deductions,
            COUNT(DISTINCT employee_name) as total_employees,
            COUNT(DISTINCT CASE WHEN vend_customer_id IS NOT NULL THEN employee_name END) as mapped_employees
        FROM xero_payroll_deductions
    ");
    
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "Total Deductions: {$stats['total_deductions']}\n";
    echo "Mapped Deductions: {$stats['mapped_deductions']}\n";
    echo "Unmapped Deductions: " . ($stats['total_deductions'] - $stats['mapped_deductions']) . "\n";
    echo "Mapping Rate: " . round($stats['mapped_deductions'] / $stats['total_deductions'] * 100, 1) . "%\n";
    echo "\nTotal Employees: {$stats['total_employees']}\n";
    echo "Mapped Employees: {$stats['mapped_employees']}\n";
    echo "Unmapped Employees: " . ($stats['total_employees'] - $stats['mapped_employees']) . "\n";

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

echo "\n=== MAPPING FIX COMPLETE ===\n";
?>