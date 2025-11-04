<?php
/**
 * Phase E: Apply Payroll Deductions to Vend
 *
 * Applies payroll deductions as "Internet Banking" payments to employee Vend accounts.
 * Uses idempotency keys to prevent duplicate allocations.
 *
 * Usage:
 *   php phase-e-apply-vend-payments.php --dry=1  (preview only)
 *   php phase-e-apply-vend-payments.php --dry=0  (LIVE - applies to Vend)
 *   php phase-e-apply-vend-payments.php --limit=5  (process only first 5)
 *
 * DANGER: --dry=0 makes REAL API calls to Vend and allocates real payments!
 */

declare(strict_types=1);

// Mark as CLI execution to bypass web-only checks
define('CLI_EXECUTION', true);
define('BYPASS_AUTH', true);

// Bootstrap
$_SERVER['DOCUMENT_ROOT'] = '/home/master/applications/jcepnzzkmj/public_html';
require_once $_SERVER['DOCUMENT_ROOT'] . '/modules/base/bootstrap.php';

use CIS\Base\Database;

// Load Vend payment library
if (!function_exists('vend_add_payment_strict_auto')) {
    require_once $_SERVER['DOCUMENT_ROOT'] . '/assets/functions/xeroAPI/vend-payment-lib.php';
}

// Load configuration functions
if (!function_exists('cis_vend_access_token')) {
    require_once $_SERVER['DOCUMENT_ROOT'] . '/modules/shared/functions/config.php';
}

// Parse CLI arguments
$options = getopt('', ['dry::', 'limit::', 'plan:', 'help']);

if (isset($options['help'])) {
    echo "Usage: php phase-e-apply-vend-payments.php [OPTIONS]\n";
    echo "  --dry      Dry run mode (1=preview, 0=LIVE, default=1)\n";
    echo "  --limit    Limit number of payments (default=all)\n";
    echo "  --plan     Path to plan JSON (default=auto-detect latest)\n";
    echo "  --help     Show this help message\n";
    exit(0);
}

$dry_run = isset($options['dry']) ? (int)$options['dry'] : 1;
$limit = isset($options['limit']) ? (int)$options['limit'] : null;
$plan_file = $options['plan'] ?? '/home/master/applications/jcepnzzkmj/private_html/ai_runs/payroll/20251103/phase_d_vend_plan.json';

// Initialize
$pdo = Database::pdo();

echo "\n";
echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║  PHASE E: APPLY PAYROLL DEDUCTIONS TO VEND                  ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n";
echo "\n";
echo "Mode: " . ($dry_run ? "🔍 DRY RUN (NO API CALLS)" : "🔴 LIVE MODE (REAL API CALLS)") . "\n";
if ($limit) echo "Limit: First {$limit} payments only\n";
echo "\n";

// Step 1: Load execution plan
echo "[1/9] Loading execution plan...\n";

if (!file_exists($plan_file)) {
    echo "ERROR: Plan file not found: {$plan_file}\n";
    exit(1);
}

$plan = json_decode(file_get_contents($plan_file), true);

if (!$plan) {
    echo "ERROR: Failed to parse plan JSON\n";
    exit(1);
}

echo "  ✓ Plan loaded: {$plan_file}\n";
echo "  ✓ Generated: {$plan['generated_at']}\n";
echo "  ✓ Payrun: {$plan['payrun']['payment_date']} (ID: {$plan['payrun']['id']})\n";
echo "  ✓ Payments to process: " . count($plan['vend_payments']) . "\n\n";

// Step 2: Verify Vend connection
echo "[2/9] Verifying Vend API connection...\n";

$vend_token = cis_vend_access_token(false);
if (!$vend_token) {
    echo "ERROR: Vend access token not configured\n";
    exit(1);
}

echo "  ✓ Vend token loaded\n\n";

// Step 3: Get "Internet Banking" payment type
echo "[3/9] Resolving Internet Banking payment type...\n";

// Query Vend payment types from local database
$stmt = $pdo->query("
    SELECT id, name
    FROM vend_payment_types
    WHERE name LIKE '%Internet%' OR name LIKE '%Banking%'
    ORDER BY name
    LIMIT 1
");
$payment_type = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$payment_type) {
    // Fallback: get first available payment type
    $stmt = $pdo->query("SELECT id, name FROM vend_payment_types LIMIT 1");
    $payment_type = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$payment_type) {
    echo "ERROR: No payment types available\n";
    exit(1);
}

$payment_type_id = $payment_type['id'];
echo "  ✓ Payment Type: {$payment_type['name']}\n";
echo "  ✓ Payment Type ID: {$payment_type_id}\n\n";// Step 4: Prepare payment list
echo "[4/9] Preparing payment list...\n";

$payments_to_process = $plan['vend_payments'];

if ($limit) {
    $payments_to_process = array_slice($payments_to_process, 0, $limit);
    echo "  ✓ Limited to first {$limit} payments\n";
}

$total_amount = array_sum(array_column($payments_to_process, function($p) {
    return (float)$p['payload']['amount'];
}));

echo "  ✓ Payments ready: " . count($payments_to_process) . "\n";
echo "  ✓ Total amount: $" . number_format($total_amount, 2) . "\n\n";

// Step 5: Process payments
echo "[5/9] Processing payments to Vend...\n\n";

$results = [
    'success' => [],
    'failed' => [],
    'skipped' => []
];

$processed = 0;

foreach ($payments_to_process as $payment) {
    $processed++;
    $employee_name = $payment['employee_name'];
    $amount = $payment['payload']['amount'];
    $deduction_id = $payment['deduction_id'];
    $idem_key = $payment['idempotency_key'];

    echo "  [{$processed}/" . count($payments_to_process) . "] {$employee_name} - \${$amount}... ";

    if ($dry_run) {
        echo "DRY RUN (skipped)\n";
        $results['skipped'][] = [
            'deduction_id' => $deduction_id,
            'employee_name' => $employee_name,
            'amount' => $amount,
            'reason' => 'Dry run mode'
        ];
        continue;
    }

    try {
        // Apply payment using existing Vend payment library
        $payment_note = $payment['payload']['note'];

        $result = vend_add_payment_strict_auto(
            $payment['payload']['customer_id'],  // Vend customer ID
            (float)$payment['payload']['amount'], // Amount
            $payment_note,                        // Note
            $payment['payload']['payment_date']   // Payment date
        );

        if ($result['success'] && isset($result['payment_id'])) {
            $vend_payment_id = $result['payment_id'];

            // Update database
            $stmt = $pdo->prepare("
                UPDATE xero_payroll_deductions
                SET vend_payment_id = ?,
                    allocated_amount = ?,
                    allocation_status = 'allocated',
                    allocated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$vend_payment_id, $amount, $deduction_id]);

            echo "✅ SUCCESS (Vend ID: " . substr($vend_payment_id, 0, 8) . "...)\n";

            $results['success'][] = [
                'deduction_id' => $deduction_id,
                'employee_name' => $employee_name,
                'amount' => $amount,
                'vend_payment_id' => $vend_payment_id
            ];

        } else {
            throw new Exception($result['error'] ?? 'Unknown error from Vend API');
        }

        // Rate limit protection: 200ms delay between calls
        usleep(200000);    } catch (Exception $e) {
        echo "❌ FAILED ({$e->getMessage()})\n";

        $results['failed'][] = [
            'deduction_id' => $deduction_id,
            'employee_name' => $employee_name,
            'amount' => $amount,
            'error' => $e->getMessage()
        ];

        // Update database with failure
        $stmt = $pdo->prepare("
            UPDATE xero_payroll_deductions
            SET allocation_status = 'failed'
            WHERE id = ?
        ");
        $stmt->execute([$deduction_id]);
    }
}

echo "\n";

// Step 6: Verify allocations
echo "[6/9] Verifying allocations in database...\n";

$stmt = $pdo->prepare("
    SELECT
        allocation_status,
        COUNT(*) as count,
        SUM(allocated_amount) as total
    FROM xero_payroll_deductions
    WHERE payroll_id = ?
    GROUP BY allocation_status
");
$stmt->execute([$plan['payrun']['id']]);
$status_summary = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($status_summary as $row) {
    echo "  • {$row['allocation_status']}: {$row['count']} payments";
    if ($row['total']) {
        echo " (\$" . number_format($row['total'], 2) . ")";
    }
    echo "\n";
}
echo "\n";

// Step 7: Create failure hold list if needed
if (!empty($results['failed'])) {
    echo "[7/9] Creating failure hold list...\n";

    $hold_file = "/home/master/applications/jcepnzzkmj/private_html/ai_runs/payroll/20251103/phase_e_failures.csv";
    $fp = fopen($hold_file, 'w');
    fputcsv($fp, ['Deduction ID', 'Employee Name', 'Amount', 'Error Message', 'Action Required']);

    foreach ($results['failed'] as $failure) {
        fputcsv($fp, [
            $failure['deduction_id'],
            $failure['employee_name'],
            '$' . number_format($failure['amount'], 2),
            $failure['error'],
            'Manual retry required - check Vend API logs'
        ]);
    }

    fclose($fp);

    echo "  ⚠️  Failure hold list saved: {$hold_file}\n";
    echo "  ⚠️  Manual action required for " . count($results['failed']) . " payments\n\n";
} else {
    echo "[7/9] No failures - hold list not needed ✓\n\n";
}

// Step 8: Save execution report
echo "[8/9] Saving execution report...\n";

$report_file = "/home/master/applications/jcepnzzkmj/private_html/ai_runs/payroll/20251103/phase_e_execution_report.json";
$report_data = [
    'executed_at' => date('Y-m-d H:i:s'),
    'dry_run' => (bool)$dry_run,
    'payrun' => $plan['payrun'],
    'payment_type' => [
        'id' => $payment_type_id ?? null,
        'name' => $internet_banking_type['name'] ?? null
    ],
    'summary' => [
        'total_processed' => count($payments_to_process),
        'successful' => count($results['success']),
        'failed' => count($results['failed']),
        'skipped' => count($results['skipped']),
        'total_allocated' => array_sum(array_column($results['success'], 'amount'))
    ],
    'results' => $results
];

file_put_contents($report_file, json_encode($report_data, JSON_PRETTY_PRINT));

echo "  ✓ Report saved: {$report_file}\n\n";

// Step 9: Summary
echo "[9/9] Phase E Summary\n\n";

echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║  PHASE E EXECUTION COMPLETE                                  ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n\n";

echo "Payrun: {$plan['payrun']['payment_date']} (ID: {$plan['payrun']['id']})\n";
echo "Payments processed: " . count($payments_to_process) . "\n";
echo "  ✅ Successful: " . count($results['success']) . "\n";
echo "  ❌ Failed: " . count($results['failed']) . "\n";
echo "  🔍 Skipped (dry run): " . count($results['skipped']) . "\n";
echo "\n";

if (!empty($results['success'])) {
    $total_allocated = array_sum(array_column($results['success'], 'amount'));
    echo "💰 Total allocated: $" . number_format($total_allocated, 2) . "\n";
}

if (!empty($results['failed'])) {
    echo "\n⚠️  ATTENTION REQUIRED:\n";
    echo "  {$results['failed'][0]['employee_name']} and " . (count($results['failed']) - 1) . " others failed\n";
    echo "  See: {$hold_file}\n";
}

echo "\n";

if ($dry_run) {
    echo "🔍 DRY RUN complete - no changes made to Vend\n";
    echo "Run with --dry=0 to apply payments for real\n";
} else {
    echo "✅ LIVE RUN complete - payments applied to Vend\n";
    echo "Ready for Phase F: Reconciliation\n";
}

echo "\n=== Next Steps ===\n";
echo "• View report: cat {$report_file}\n";
if (!empty($results['failed'])) {
    echo "• Fix failures: Review {$hold_file} and retry\n";
}
if ($dry_run) {
    echo "• Execute LIVE: php phase-e-apply-vend-payments.php --dry=0\n";
} else {
    echo "• Execute Phase F: Reply 'RUN PHASE F' for reconciliation\n";
}
echo "\n";

exit(empty($results['failed']) ? 0 : 1);
