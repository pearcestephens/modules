<?php
/**
 * Phase D: Compute Deductions → Plan Vend Apply
 *
 * Extracts latest payrun deductions, maps to Vend customers, generates idempotency keys,
 * and prepares Vend payment application plan.
 *
 * Usage:
 *   php phase-d-compute-deductions.php --dry=1  (preview only)
 *   php phase-d-compute-deductions.php --dry=0  (update database)
 *
 * Features:
 * - Maps Xero employees to Vend customers via cis_staff_vend_map
 * - Generates SHA256 idempotency keys
 * - Creates hold list CSV for unmapped employees
 * - Prepares Vend API POST payloads
 * - Updates vend_customer_id in xero_payroll_deductions
 */

declare(strict_types=1);

// Bootstrap
$_SERVER['DOCUMENT_ROOT'] = '/home/master/applications/jcepnzzkmj/public_html';
require_once $_SERVER['DOCUMENT_ROOT'] . '/modules/base/bootstrap.php';

use CIS\Base\Database;

// Parse CLI arguments
$options = getopt('', ['dry::', 'payrun:', 'help']);

if (isset($options['help'])) {
    echo "Usage: php phase-d-compute-deductions.php [OPTIONS]\n";
    echo "  --dry      Dry run mode (1=preview, 0=commit, default=1)\n";
    echo "  --payrun   Specific payrun date (YYYY-MM-DD, default=latest)\n";
    echo "  --help     Show this help message\n";
    exit(0);
}

$dry_run = isset($options['dry']) ? (int)$options['dry'] : 1;
$payrun_date = $options['payrun'] ?? null;

// Initialize
$pdo = Database::pdo();

echo "\n=== Phase D: Compute Deductions → Plan Vend Apply ===\n";
echo "Mode: " . ($dry_run ? "DRY RUN (preview only)" : "LIVE (updates database)") . "\n\n";

// Step 1: Get latest payrun (or specified date)
echo "[1/7] Finding target payrun...\n";

if ($payrun_date) {
    $stmt = $pdo->prepare("
        SELECT id, payment_date, employee_count, total_deductions, status
        FROM xero_payrolls
        WHERE payment_date = ?
        LIMIT 1
    ");
    $stmt->execute([$payrun_date]);
} else {
    $stmt = $pdo->query("
        SELECT id, payment_date, employee_count, total_deductions, status
        FROM xero_payrolls
        WHERE status = 'posted'
        ORDER BY payment_date DESC
        LIMIT 1
    ");
}

$payrun = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$payrun) {
    echo "ERROR: No payrun found\n";
    exit(1);
}

echo "  ✓ Payrun ID: {$payrun['id']}\n";
echo "  ✓ Payment Date: {$payrun['payment_date']}\n";
echo "  ✓ Employees: {$payrun['employee_count']}\n";
echo "  ✓ Total Deductions: \${$payrun['total_deductions']}\n\n";

// Step 2: Extract deductions for this payrun
echo "[2/7] Extracting deductions...\n";

$stmt = $pdo->prepare("
    SELECT
        id,
        xero_employee_id,
        employee_name,
        vend_customer_id,
        deduction_type,
        amount,
        description
    FROM xero_payroll_deductions
    WHERE payroll_id = ?
      AND deduction_type = 'Account Payment'
      AND amount > 0
    ORDER BY amount DESC
");
$stmt->execute([$payrun['id']]);
$deductions = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "  ✓ Found " . count($deductions) . " Account Payment deductions\n";
echo "  ✓ Total Amount: $" . number_format(array_sum(array_column($deductions, 'amount')), 2) . "\n\n";

if (empty($deductions)) {
    echo "No deductions to process. Exiting.\n";
    exit(0);
}

// Step 3: Map to Vend customers
echo "[3/7] Mapping Xero employees to Vend customers...\n";

$mapped = 0;
$unmapped = 0;
$unmapped_list = [];
$mapped_deductions = [];

foreach ($deductions as $deduction) {
    // Check if already mapped in deduction record
    if (!empty($deduction['vend_customer_id'])) {
        $mapped++;
        $mapped_deductions[] = $deduction;
        continue;
    }

    // Look up in staff mapping table
    $stmt = $pdo->prepare("
        SELECT vend_customer_id, email, first_name, last_name
        FROM cis_staff_vend_map
        WHERE xero_employee_id = ?
    ");
    $stmt->execute([$deduction['xero_employee_id']]);
    $mapping = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($mapping) {
        // Found mapping
        $deduction['vend_customer_id'] = $mapping['vend_customer_id'];
        $deduction['mapped_email'] = $mapping['email'];
        $mapped++;
        $mapped_deductions[] = $deduction;

        // Update deduction record with vend_customer_id if not dry run
        if (!$dry_run) {
            $update_stmt = $pdo->prepare("
                UPDATE xero_payroll_deductions
                SET vend_customer_id = ?
                WHERE id = ?
            ");
            $update_stmt->execute([$mapping['vend_customer_id'], $deduction['id']]);
        }
    } else {
        // No mapping found
        $unmapped++;
        $unmapped_list[] = [
            'xero_employee_id' => $deduction['xero_employee_id'],
            'employee_name' => $deduction['employee_name'],
            'amount' => $deduction['amount'],
            'deduction_id' => $deduction['id']
        ];
    }
}

echo "  ✓ Mapped: {$mapped} deductions\n";
echo "  ✓ Unmapped: {$unmapped} deductions\n\n";

// Step 4: Generate idempotency keys for mapped deductions
echo "[4/7] Generating idempotency keys...\n";

$vend_plan = [];

foreach ($mapped_deductions as $deduction) {
    // Generate idempotency key: sha256("payroll|payrun_id|vend_customer_id|amount_cents|payrun_date")
    $amount_cents = (int)round($deduction['amount'] * 100);
    $idem_string = sprintf(
        "payroll|%d|%s|%d|%s",
        $payrun['id'],
        $deduction['vend_customer_id'],
        $amount_cents,
        $payrun['payment_date']
    );
    $idem_key = hash('sha256', $idem_string);

    $vend_plan[] = [
        'deduction_id' => $deduction['id'],
        'xero_employee_id' => $deduction['xero_employee_id'],
        'employee_name' => $deduction['employee_name'],
        'vend_customer_id' => $deduction['vend_customer_id'],
        'amount' => $deduction['amount'],
        'amount_cents' => $amount_cents,
        'idempotency_key' => $idem_key,
        'description' => sprintf(
            "Payroll deduction - %s (%s)",
            $payrun['payment_date'],
            $deduction['employee_name']
        )
    ];
}

echo "  ✓ Generated " . count($vend_plan) . " idempotency keys\n";
echo "  ✓ Sample key: " . substr($vend_plan[0]['idempotency_key'] ?? 'N/A', 0, 16) . "...\n\n";

// Step 5: Create hold list CSV for unmapped employees
if (!empty($unmapped_list)) {
    echo "[5/7] Creating hold list for unmapped employees...\n";

    $hold_file = "/home/master/applications/jcepnzzkmj/private_html/ai_runs/payroll/20251103/phase_d_hold_list.csv";
    $hold_dir = dirname($hold_file);

    if (!is_dir($hold_dir)) {
        mkdir($hold_dir, 0755, true);
    }

    $fp = fopen($hold_file, 'w');
    fputcsv($fp, ['Xero Employee ID', 'Employee Name', 'Deduction Amount', 'Deduction ID', 'Action Required']);

    foreach ($unmapped_list as $item) {
        fputcsv($fp, [
            $item['xero_employee_id'],
            $item['employee_name'],
            '$' . number_format($item['amount'], 2),
            $item['deduction_id'],
            'Manual mapping required - add to cis_staff_vend_map'
        ]);
    }

    fclose($fp);

    echo "  ✓ Hold list saved: {$hold_file}\n";
    echo "  ✓ Manual action required for {$unmapped} employees\n\n";
} else {
    echo "[5/7] No unmapped employees - all deductions ready for processing\n\n";
}

// Step 6: Generate Vend API payloads
echo "[6/7] Generating Vend API payloads...\n";

$vend_payloads = [];
$total_to_apply = 0;

foreach ($vend_plan as $plan) {
    $payload = [
        'customer_id' => $plan['vend_customer_id'],
        'register_id' => null, // Set to specific register or leave null for "Internet Banking"
        'payment_type_id' => null, // Will be set to "Internet Banking" payment type
        'amount' => $plan['amount'],
        'payment_date' => $payrun['payment_date'],
        'note' => $plan['description']
    ];

    $vend_payloads[] = [
        'deduction_id' => $plan['deduction_id'],
        'employee_name' => $plan['employee_name'],
        'idempotency_key' => $plan['idempotency_key'],
        'payload' => $payload
    ];

    $total_to_apply += $plan['amount'];
}

echo "  ✓ Generated " . count($vend_payloads) . " Vend payment payloads\n";
echo "  ✓ Total to apply: $" . number_format($total_to_apply, 2) . "\n\n";

// Step 7: Save execution plan
echo "[7/7] Saving execution plan...\n";

$plan_file = "/home/master/applications/jcepnzzkmj/private_html/ai_runs/payroll/20251103/phase_d_vend_plan.json";
$plan_data = [
    'generated_at' => date('Y-m-d H:i:s'),
    'payrun' => [
        'id' => $payrun['id'],
        'payment_date' => $payrun['payment_date'],
        'employee_count' => $payrun['employee_count'],
        'total_deductions' => $payrun['total_deductions']
    ],
    'summary' => [
        'total_deductions' => count($deductions),
        'mapped' => $mapped,
        'unmapped' => $unmapped,
        'total_amount' => $total_to_apply,
        'dry_run' => (bool)$dry_run
    ],
    'vend_payments' => $vend_payloads,
    'unmapped_employees' => $unmapped_list
];

file_put_contents($plan_file, json_encode($plan_data, JSON_PRETTY_PRINT));

echo "  ✓ Plan saved: {$plan_file}\n\n";

// Summary
echo "=== Phase D Summary ===\n";
echo "Payrun: {$payrun['payment_date']} (ID: {$payrun['id']})\n";
echo "Deductions processed: " . count($deductions) . "\n";
echo "  • Mapped to Vend: {$mapped}\n";
echo "  • Unmapped (hold): {$unmapped}\n";
echo "Total amount ready: $" . number_format($total_to_apply, 2) . "\n";
echo "\n";

if ($unmapped > 0) {
    echo "⚠️  Action Required:\n";
    echo "  {$unmapped} employees need manual mapping in cis_staff_vend_map\n";
    echo "  See: {$hold_file}\n\n";
}

if ($dry_run) {
    echo "🔍 DRY RUN complete - no database changes made\n";
    echo "Run with --dry=0 to commit vend_customer_id updates\n";
} else {
    echo "✅ LIVE RUN complete - vend_customer_id values updated\n";
    echo "Ready for Phase E: Apply to Vend\n";
}

echo "\n=== Next Steps ===\n";
echo "• Review plan: cat {$plan_file}\n";
if ($unmapped > 0) {
    echo "• Fix mappings: Review and add missing staff to cis_staff_vend_map\n";
    echo "• Re-run Phase D: php phase-d-compute-deductions.php --dry=0\n";
}
echo "• Execute Phase E: Reply 'RUN PHASE E' to apply payments to Vend\n";
echo "\n";

exit(0);
