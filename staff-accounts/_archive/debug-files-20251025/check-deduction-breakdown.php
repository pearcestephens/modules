<?php
/**
 * Check Deduction Breakdown - Find where all deductions are
 */

require_once '../shared/bootstrap.php';

echo "=== DEDUCTION BREAKDOWN ANALYSIS ===\n\n";

try {
    // Total deductions
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM xero_payroll_deductions WHERE amount > 0');
    $stmt->execute();
    $total = $stmt->fetchColumn();
    echo "Total deductions: $total\n\n";

    // Mapped vs unmapped breakdown
    echo "1. MAPPING STATUS BREAKDOWN:\n";
    echo str_repeat("-", 50) . "\n";
    
    $stmt = $pdo->prepare('
        SELECT 
            CASE WHEN vend_customer_id IS NOT NULL THEN "Mapped" ELSE "Unmapped" END as status,
            COUNT(*) as count,
            ROUND((COUNT(*) * 100.0 / ?), 1) as percentage
        FROM xero_payroll_deductions 
        WHERE amount > 0
        GROUP BY CASE WHEN vend_customer_id IS NOT NULL THEN 1 ELSE 0 END
        ORDER BY count DESC
    ');
    $stmt->execute([$total]);
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo sprintf("%-10s: %5d deductions (%s%%)\n", $row['status'], $row['count'], $row['percentage']);
    }
    
    echo "\n2. TOP 15 UNMAPPED EMPLOYEES:\n";
    echo str_repeat("-", 80) . "\n";
    
    $stmt = $pdo->prepare('
        SELECT 
            d.employee_name, 
            COUNT(*) as count, 
            MIN(p.pay_period_start) as first_period,
            MAX(p.pay_period_end) as last_period,
            CASE 
                WHEN MAX(p.pay_period_end) >= DATE_SUB(NOW(), INTERVAL 3 MONTH) THEN "ACTIVE"
                WHEN MAX(p.pay_period_end) >= DATE_SUB(NOW(), INTERVAL 6 MONTH) THEN "RECENT"
                ELSE "INACTIVE"
            END as activity_status
        FROM xero_payroll_deductions d
        LEFT JOIN xero_payrolls p ON d.payroll_id = p.id
        WHERE d.amount > 0 AND d.vend_customer_id IS NULL
        GROUP BY d.employee_name
        ORDER BY count DESC
        LIMIT 15
    ');
    $stmt->execute();
    
    printf("%-25s %6s %-10s %-12s %s\n", "Employee Name", "Count", "Activity", "Last Period", "First Period");
    echo str_repeat("-", 80) . "\n";
    
    $active_count = 0;
    $recent_count = 0;
    $inactive_count = 0;
    $active_deductions = 0;
    $recent_deductions = 0;
    $inactive_deductions = 0;
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $last_period = $row['last_period'] ? date('M j, Y', strtotime($row['last_period'])) : 'Unknown';
        $first_period = $row['first_period'] ? date('M j, Y', strtotime($row['first_period'])) : 'Unknown';
        
        printf("%-25s %6d %-10s %-12s %s\n", 
            substr($row['employee_name'], 0, 24), 
            $row['count'], 
            $row['activity_status'],
            $last_period,
            $first_period
        );
        
        if ($row['activity_status'] === 'ACTIVE') {
            $active_count++;
            $active_deductions += $row['count'];
        } elseif ($row['activity_status'] === 'RECENT') {
            $recent_count++;
            $recent_deductions += $row['count'];
        } else {
            $inactive_count++;
            $inactive_deductions += $row['count'];
        }
    }
    
    echo "\n3. ACTIVITY SUMMARY FOR UNMAPPED:\n";
    echo str_repeat("-", 40) . "\n";
    echo "Active employees (last 3 months): $active_count employees, $active_deductions deductions\n";
    echo "Recent employees (3-6 months): $recent_count employees, $recent_deductions deductions\n";
    echo "Inactive employees (6+ months): $inactive_count employees, $inactive_deductions deductions\n";
    
    echo "\n4. UNKNOWN/SYSTEM ENTRIES:\n";
    echo str_repeat("-", 40) . "\n";
    
    $stmt = $pdo->prepare('
        SELECT employee_name, COUNT(*) as count
        FROM xero_payroll_deductions 
        WHERE amount > 0 AND vend_customer_id IS NULL
        AND (
            employee_name LIKE "%Unknown%" OR 
            employee_name = "" OR 
            employee_name IS NULL OR
            employee_name LIKE "%test%" OR
            employee_name LIKE "%system%"
        )
        GROUP BY employee_name
        ORDER BY count DESC
    ');
    $stmt->execute();
    
    $system_total = 0;
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "'{$row['employee_name']}': {$row['count']} deductions\n";
        $system_total += $row['count'];
    }
    
    if ($system_total === 0) {
        echo "No obvious system/unknown entries found.\n";
    } else {
        echo "Total system entries: $system_total deductions\n";
    }
    
    echo "\n=== SUMMARY ===\n";
    echo "Total deductions: $total\n";
    echo "Unmapped active employees: $active_count employees, $active_deductions deductions\n";
    echo "Unmapped recent employees: $recent_count employees, $recent_deductions deductions\n";
    echo "Unmapped inactive employees: $inactive_count employees, $inactive_deductions deductions\n";
    echo "System/unknown entries: $system_total deductions\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
