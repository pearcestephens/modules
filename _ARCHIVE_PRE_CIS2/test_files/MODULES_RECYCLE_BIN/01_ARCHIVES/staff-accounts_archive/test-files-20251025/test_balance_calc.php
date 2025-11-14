<?php
/**
 * Test Staff Balance Calculation
 * 
 * Calculates staff account balances on-the-fly from:
 * - vend_sales (Internet Banking payments at Hamilton East)
 * - xero_payroll_deductions (deductions from paychecks)
 */

require_once 'bootstrap.php';

$userId = 120; // Baillie Ohia - test user

echo "Testing Staff Balance Calculation\n";
echo str_repeat('=', 80) . PHP_EOL;

// Since JSON_TABLE might not be available, we'll process in PHP
// First get the user info
$query = "SELECT id, first_name, last_name, vend_customer_account, xero_id FROM users WHERE id = ?";

$stmt = $pdo->prepare($query);
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    throw new Exception("User not found");
}

// Get all payments for this customer from Hamilton East
$paymentsQuery = "
SELECT payments 
FROM vend_sales 
WHERE customer_id = ? 
  AND outlet_id = '02dcd191-ae2b-11e6-f485-8eceed6eeafb'
  AND payments IS NOT NULL 
  AND payments != '[]'
  AND payments != ''
";

$stmt = $pdo->prepare($paymentsQuery);
$stmt->execute([$user['vend_customer_account']]);
$sales = $stmt->fetchAll();

// Calculate total Internet Banking payments
$totalPayments = 0;
foreach ($sales as $sale) {
    $paymentsJson = json_decode($sale['payments'], true);
    if (is_array($paymentsJson)) {
        foreach ($paymentsJson as $payment) {
            if (isset($payment['name']) && stripos($payment['name'], 'Internet Banking') !== false) {
                $totalPayments += floatval($payment['amount']);
            }
        }
    }
}

// Get Xero deductions
$xeroQuery = "SELECT SUM(amount) as total FROM xero_payroll_deductions WHERE xero_employee_id = ?";
$stmt = $pdo->prepare($xeroQuery);
$stmt->execute([$user['xero_id']]);
$xeroRow = $stmt->fetch();
$xeroDeductions = $xeroRow ? floatval($xeroRow['total']) : 0;

// Build result row
$row = [
    'id' => $user['id'],
    'first_name' => $user['first_name'],
    'last_name' => $user['last_name'],
    'vend_customer_account' => $user['vend_customer_account'],
    'xero_id' => $user['xero_id'],
    'vend_balance' => $totalPayments,
    'xero_deductions' => $xeroDeductions
];

try {
    // Since JSON_TABLE might not be available, we'll process in PHP
    // First get the user info
    $query = "SELECT id, first_name, last_name, vend_customer_account, xero_id FROM users WHERE id = ?";

    $stmt = $pdo->prepare($query);
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if (!$user) {
        throw new Exception("User not found");
    }

    // Get all payments for this customer from Hamilton East
    $paymentsQuery = "
    SELECT payments 
    FROM vend_sales 
    WHERE customer_id = ? 
      AND outlet_id = '02dcd191-ae2b-11e6-f485-8eceed6eeafb'
      AND payments IS NOT NULL 
      AND payments != '[]'
      AND payments != ''
    ";

    $stmt = $pdo->prepare($paymentsQuery);
    $stmt->execute([$user['vend_customer_account']]);
    $sales = $stmt->fetchAll();

    // Calculate total Internet Banking payments
    $totalPayments = 0;
    foreach ($sales as $sale) {
        $paymentsJson = json_decode($sale['payments'], true);
        if (is_array($paymentsJson)) {
            foreach ($paymentsJson as $payment) {
                if (isset($payment['name']) && stripos($payment['name'], 'Internet Banking') !== false) {
                    $totalPayments += floatval($payment['amount']);
                }
            }
        }
    }

    // Get Xero deductions
    $xeroQuery = "SELECT SUM(amount) as total FROM xero_payroll_deductions WHERE xero_employee_id = ?";
    $stmt = $pdo->prepare($xeroQuery);
    $stmt->execute([$user['xero_id']]);
    $xeroRow = $stmt->fetch();
    $xeroDeductions = $xeroRow ? floatval($xeroRow['total']) : 0;

    // Build result row
    $row = [
        'id' => $user['id'],
        'first_name' => $user['first_name'],
        'last_name' => $user['last_name'],
        'vend_customer_account' => $user['vend_customer_account'],
        'xero_id' => $user['xero_id'],
        'vend_balance' => $totalPayments,
        'xero_deductions' => $xeroDeductions
    ];

    if ($row) {
        echo "User: {$row['first_name']} {$row['last_name']}\n";
        echo "Vend Customer ID: {$row['vend_customer_account']}\n";
        echo "Xero Employee ID: " . ($row['xero_id'] ?? 'NULL') . "\n";
        echo "Internet Banking Payments (Hamilton East): $" . number_format($row['vend_balance'], 2) . "\n";
        echo "Xero Deductions: $" . number_format($row['xero_deductions'], 2) . "\n";
        echo "Outstanding Balance: $" . number_format($row['vend_balance'] - $row['xero_deductions'], 2) . "\n";
    } else {
        echo "User not found\n";
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo str_repeat('=', 80) . PHP_EOL;
