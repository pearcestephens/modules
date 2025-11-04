<?php
/**
 * Phase E V2: Apply Payroll Deductions to Vend (Using Existing Library)
 *
 * Uses proven vend_add_payment_strict_auto() function from vend-payment-lib.php
 * This is the CORRECT approach using Vend's register_sales API.
 */

declare(strict_types=1);

// Bootstrap the application to get all dependencies
$_SERVER['DOCUMENT_ROOT'] = '/home/master/applications/jcepnzzkmj/public_html';
define('CLI_EXECUTION', true);
define('BYPASS_AUTH', true);
chdir($_SERVER['DOCUMENT_ROOT']);

// Load the app
require_once $_SERVER['DOCUMENT_ROOT'] . '/app.php';

// Load the working Vend payment library
require_once $_SERVER['DOCUMENT_ROOT'] . '/assets/functions/xeroAPI/vend-payment-lib.php';

// Get database connection from bootstrap
$pdo = \CIS\Base\Database::getInstance()->getConnection();

// Parse arguments
$options = getopt('', ['dry::', 'limit::', 'help']);

if (isset($options['help'])) {
    echo "Usage: php phase-e-v2.php [OPTIONS]\n";
    echo "  --dry      Dry run mode (1=preview, 0=LIVE, default=0)\n";
    echo "  --limit    Limit number of payments\n";
    echo "  --help     Show this help\n";
    exit(0);
}

$dry_run = isset($options['dry']) ? (int)$options['dry'] : 0; // Default to LIVE!
$limit = isset($options['limit']) ? (int)$options['limit'] : null;

echo "\n";
echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║  PHASE E V2: APPLY TO VEND (USING WORKING LIBRARY)          ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n";
echo "\n";
echo "Mode: " . ($dry_run ? "🔍 DRY RUN" : "🔴 LIVE MODE - APPLYING REAL PAYMENTS") . "\n";
echo "Library: vend_add_payment_strict_auto() from vend-payment-lib.php\n\n";

// Load plan
$plan_file = '/home/master/applications/jcepnzzkmj/private_html/ai_runs/payroll/20251103/phase_d_vend_plan.json';
$plan = json_decode(file_get_contents($plan_file), true);

$payrun_id = $plan['payrun']['id'];
$payrun_date = $plan['payrun']['payment_date'];

echo "[1/5] Plan loaded\n";
echo "  • Payrun: {$payrun_date} (ID: {$payrun_id})\n";
echo "  • Planned payments: " . count($plan['vend_payments']) . "\n\n";

// Get pending deductions
$stmt = $pdo->prepare("
    SELECT
        id,
        employee_name,
        vend_customer_id,
        amount,
        description
    FROM xero_payroll_deductions
    WHERE payroll_id = ?
      AND vend_customer_id IS NOT NULL
      AND allocation_status = 'pending'
    ORDER BY amount DESC
");
$stmt->execute([$payrun_id]);
$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($limit) {
    $payments = array_slice($payments, 0, $limit);
}

echo "[2/5] Found " . count($payments) . " pending payments\n\n";

if (empty($payments)) {
    echo "No pending payments to process. Exiting.\n";
    exit(0);
}

// Process payments using the working library function
echo "[3/5] Processing payments with vend_add_payment_strict_auto()...\n\n";

$results = [
    'success' => [],
    'failed' => [],
    'skipped' => []
];

$start_time = microtime(true);

foreach ($payments as $idx => $payment) {
    $num = $idx + 1;
    $employee = $payment['employee_name'];
    $amount = (float)$payment['amount'];
    $customer_id = $payment['vend_customer_id'];
    $deduction_id = $payment['id'];

    echo "  [{$num}/" . count($payments) . "] {$employee} - \${$amount}... ";

    if ($dry_run) {
        echo "DRY RUN (skipped)\n";
        $results['skipped'][] = $payment;
        continue;
    }

    try {
        // Use the existing working function!
        $note = "Payroll deduction - {$payrun_date} - {$employee}";

        $result = vend_add_payment_strict_auto(
            $customer_id,
            $amount,
            $note,
            $payrun_date
        );

        if ($result['success'] && !empty($result['payment_id'])) {
            $vend_payment_id = $result['payment_id'];

            // Update database with success
            $stmt = $pdo->prepare("
                UPDATE xero_payroll_deductions
                SET vend_payment_id = ?,
                    allocated_amount = ?,
                    allocation_status = 'allocated',
                    allocated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$vend_payment_id, $amount, $deduction_id]);

            echo "✅ SUCCESS (Sale ID: " . substr($vend_payment_id, 0, 12) . "...)\n";

            $results['success'][] = array_merge($payment, [
                'vend_payment_id' => $vend_payment_id
            ]);

        } else {
            $error_msg = $result['error'] ?? 'Unknown error';
            throw new Exception($error_msg);
        }

        // Rate limit protection: 300ms between calls
        usleep(300000);

    } catch (Exception $e) {
        echo "❌ FAILED (" . substr($e->getMessage(), 0, 50) . "...)\n";

        $results['failed'][] = array_merge($payment, [
            'error' => $e->getMessage()
        ]);

        // Mark as failed in database
        $stmt = $pdo->prepare("
            UPDATE xero_payroll_deductions
            SET allocation_status = 'failed'
            WHERE id = ?
        ");
        $stmt->execute([$deduction_id]);
    }
}

$elapsed = round(microtime(true) - $start_time, 2);

echo "\n[4/5] Processing complete in {$elapsed}s\n\n";

// Verify database state
echo "[5/5] Verifying database state...\n";

$stmt = $pdo->prepare("
    SELECT
        allocation_status,
        COUNT(*) as count,
        SUM(allocated_amount) as total
    FROM xero_payroll_deductions
    WHERE payroll_id = ?
    GROUP BY allocation_status
");
$stmt->execute([$payrun_id]);
$status_summary = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($status_summary as $row) {
    echo "  • {$row['allocation_status']}: {$row['count']} payments";
    if ($row['total']) {
        echo " (\$" . number_format($row['total'], 2) . ")";
    }
    echo "\n";
}
echo "\n";

// Summary
echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║  PHASE E V2 COMPLETE                                         ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n\n";

echo "Payrun: {$payrun_date} (ID: {$payrun_id})\n";
echo "Processed: " . count($payments) . " payments\n";
echo "  ✅ Success: " . count($results['success']) . "\n";
echo "  ❌ Failed: " . count($results['failed']) . "\n";
echo "  🔍 Skipped: " . count($results['skipped']) . "\n";
echo "Duration: {$elapsed}s\n\n";

if (!empty($results['success'])) {
    $total = array_sum(array_column($results['success'], 'amount'));
    echo "💰 Total allocated to Vend: $" . number_format($total, 2) . "\n";
}

if (!empty($results['failed'])) {
    echo "\n⚠️  FAILURES DETECTED:\n";
    foreach ($results['failed'] as $f) {
        $err = substr($f['error'], 0, 60);
        echo "  • {$f['employee_name']}: {$err}...\n";
    }

    // Save failure report
    $fail_file = '/home/master/applications/jcepnzzkmj/private_html/ai_runs/payroll/20251103/phase_e_v2_failures.csv';
    $fp = fopen($fail_file, 'w');
    fputcsv($fp, ['Employee', 'Amount', 'Vend Customer ID', 'Error', 'Deduction ID']);
    foreach ($results['failed'] as $f) {
        fputcsv($fp, [
            $f['employee_name'],
            '$' . $f['amount'],
            $f['vend_customer_id'],
            $f['error'],
            $f['id']
        ]);
    }
    fclose($fp);
    echo "\nFailure report: {$fail_file}\n";
}

// Save execution report
$report = [
    'executed_at' => date('Y-m-d H:i:s'),
    'version' => 'v2_using_working_library',
    'dry_run' => (bool)$dry_run,
    'payrun' => $plan['payrun'],
    'duration_seconds' => $elapsed,
    'summary' => [
        'processed' => count($payments),
        'successful' => count($results['success']),
        'failed' => count($results['failed']),
        'skipped' => count($results['skipped']),
        'total_allocated' => array_sum(array_column($results['success'], 'amount'))
    ],
    'results' => $results
];

$report_file = '/home/master/applications/jcepnzzkmj/private_html/ai_runs/payroll/20251103/phase_e_v2_execution_report.json';
file_put_contents($report_file, json_encode($report, JSON_PRETTY_PRINT));

echo "\n✅ Execution report: {$report_file}\n\n";

if ($dry_run) {
    echo "🔍 DRY RUN complete - run with --dry=0 to apply for real\n";
} else {
    if (count($results['success']) === count($payments)) {
        echo "🎉 100% SUCCESS! All payments allocated to Vend!\n";
        echo "✅ Ready for Phase F: Reconciliation & Exports\n";
    } else if (count($results['success']) > 0) {
        echo "⚠️  Partial success - review failures and retry\n";
    } else {
        echo "❌ All payments failed - check logs and configuration\n";
    }
}

echo "\n";

exit(count($results['failed']) === 0 ? 0 : 1);
