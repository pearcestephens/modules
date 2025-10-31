<?php
require_once 'bootstrap.php';

// Check what payroll data we have including pay periods
$stmt = $pdo->query("
    SELECT 
        d.id,
        d.employee_name,
        d.amount,
        d.vend_customer_id,
        p.payment_date,
        p.pay_period_start,
        p.pay_period_end
    FROM xero_payroll_deductions d
    JOIN xero_payrolls p ON d.payroll_id = p.id
    WHERE d.allocation_status = 'pending'
    AND d.amount > 0
    LIMIT 5
");

echo "=== PAY PERIOD DATA CHECK ===\n";
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "Employee: {$row['employee_name']}\n";
    echo "Amount: $" . number_format($row['amount'], 2) . "\n";
    echo "Payment Date: " . ($row['payment_date'] ?: 'NULL') . "\n";
    echo "Pay Period Start: " . ($row['pay_period_start'] ?: 'NULL') . "\n";
    echo "Pay Period End: " . ($row['pay_period_end'] ?: 'NULL') . "\n";
    echo "Vend Customer ID: " . ($row['vend_customer_id'] ?: 'NULL') . "\n";
    echo "---\n";
}
?>