<?php
/**
 * Phase E: Apply Payroll Deductions to Vend (STANDALONE VERSION)
 *
 * Applies payroll deductions as customer account payments in Vend.
 * Minimal dependencies - direct database access only.
 */

declare(strict_types=1);

// Database connection
$host = '127.0.0.1';
$dbname = 'jcepnzzkmj';
$username = 'jcepnzzkmj';
$password = 'wprKh9Jq63';

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage() . "\n");
}

// Parse arguments
$options = getopt('', ['dry::', 'limit::', 'help']);

if (isset($options['help'])) {
    echo "Usage: php phase-e-standalone.php [OPTIONS]\n";
    echo "  --dry      Dry run mode (1=preview, 0=LIVE, default=1)\n";
    echo "  --limit    Limit number of payments\n";
    echo "  --help     Show this help\n";
    exit(0);
}

$dry_run = isset($options['dry']) ? (int)$options['dry'] : 1;
$limit = isset($options['limit']) ? (int)$options['limit'] : null;

echo "\n╔══════════════════════════════════════════════════════════════╗\n";
echo "║  PHASE E: APPLY PAYROLL DEDUCTIONS TO VEND (STANDALONE)     ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n\n";
echo "Mode: " . ($dry_run ? "🔍 DRY RUN" : "🔴 LIVE MODE") . "\n\n";

// Load plan
$plan_file = '/home/master/applications/jcepnzzkmj/private_html/ai_runs/payroll/20251103/phase_d_vend_plan.json';
$plan = json_decode(file_get_contents($plan_file), true);

echo "[1/6] Plan loaded - {$plan['summary']['mapped']} payments ready\n\n";

// Get payments to process
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
$stmt->execute([$plan['payrun']['id']]);
$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($limit) {
    $payments = array_slice($payments, 0, $limit);
}

echo "[2/6] Found " . count($payments) . " payments to process\n\n";

// Load Vend token
$stmt = $pdo->query("SELECT config_value FROM configuration WHERE config_label = 'vend_access_token'");
$vend_token = $stmt->fetchColumn();

if (!$vend_token) {
    die("ERROR: Vend access token not found\n");
}

echo "[3/6] Vend API token loaded\n\n";

// Get Vend domain
$stmt = $pdo->query("SELECT config_value FROM configuration WHERE config_label = 'vend_domain_prefix'");
$vend_domain = $stmt->fetchColumn() ?: 'vapeshed';

echo "[4/6] Vend domain: {$vend_domain}.vendhq.com\n\n";

// Process payments
echo "[5/6] Processing payments...\n\n";

$results = ['success' => [], 'failed' => [], 'skipped' => []];

foreach ($payments as $idx => $payment) {
    $num = $idx + 1;
    echo "  [{$num}/" . count($payments) . "] {$payment['employee_name']} - \${$payment['amount']}... ";

    if ($dry_run) {
        echo "DRY RUN (skipped)\n";
        $results['skipped'][] = $payment;
        continue;
    }

    // Make Vend API call
    try {
        $api_url = "https://{$vend_domain}.vendhq.com/api/2.0/customer_balance_adjustments";

        $payload = [
            'customer_id' => $payment['vend_customer_id'],
            'amount' => (float)$payment['amount'],
            'note' => "Payroll deduction - " . $plan['payrun']['payment_date'],
            'created_at' => date('c')
        ];

        $ch = curl_init($api_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $vend_token,
            'Content-Type: application/json',
            'Accept: application/json'
        ]);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new Exception("cURL error: $error");
        }

        if ($http_code !== 200 && $http_code !== 201) {
            throw new Exception("HTTP $http_code: " . substr($response, 0, 100));
        }

        $result = json_decode($response, true);

        if (!isset($result['data']['id'])) {
            throw new Exception("No payment ID in response");
        }

        $vend_payment_id = $result['data']['id'];

        // Update database
        $stmt = $pdo->prepare("
            UPDATE xero_payroll_deductions
            SET vend_payment_id = ?,
                allocated_amount = ?,
                allocation_status = 'allocated',
                allocated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$vend_payment_id, $payment['amount'], $payment['id']]);

        echo "✅ SUCCESS (ID: " . substr($vend_payment_id, 0, 8) . "...)\n";

        $results['success'][] = array_merge($payment, ['vend_payment_id' => $vend_payment_id]);

        // Rate limit: 300ms between calls
        usleep(300000);

    } catch (Exception $e) {
        echo "❌ FAILED (" . $e->getMessage() . ")\n";

        $results['failed'][] = array_merge($payment, ['error' => $e->getMessage()]);

        // Mark as failed in database
        $stmt = $pdo->prepare("UPDATE xero_payroll_deductions SET allocation_status = 'failed' WHERE id = ?");
        $stmt->execute([$payment['id']]);
    }
}

echo "\n[6/6] Complete\n\n";

// Summary
echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║  PHASE E SUMMARY                                             ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n\n";

echo "Payrun: {$plan['payrun']['payment_date']}\n";
echo "Processed: " . count($payments) . "\n";
echo "  ✅ Success: " . count($results['success']) . "\n";
echo "  ❌ Failed: " . count($results['failed']) . "\n";
echo "  🔍 Skipped: " . count($results['skipped']) . "\n\n";

if (!empty($results['success'])) {
    $total = array_sum(array_column($results['success'], 'amount'));
    echo "💰 Total allocated: $" . number_format($total, 2) . "\n";
}

if (!empty($results['failed'])) {
    echo "\n⚠️  FAILURES:\n";
    foreach ($results['failed'] as $f) {
        echo "  • {$f['employee_name']}: {$f['error']}\n";
    }
}

// Save report
$report = [
    'executed_at' => date('Y-m-d H:i:s'),
    'dry_run' => (bool)$dry_run,
    'payrun' => $plan['payrun'],
    'summary' => [
        'processed' => count($payments),
        'successful' => count($results['success']),
        'failed' => count($results['failed']),
        'skipped' => count($results['skipped'])
    ],
    'results' => $results
];

$report_file = '/home/master/applications/jcepnzzkmj/private_html/ai_runs/payroll/20251103/phase_e_execution_report.json';
file_put_contents($report_file, json_encode($report, JSON_PRETTY_PRINT));

echo "\n✅ Report saved: {$report_file}\n\n";

if ($dry_run) {
    echo "🔍 DRY RUN complete - run with --dry=0 to apply for real\n";
} else {
    echo "✅ LIVE RUN complete - payments applied to Vend\n";
    echo "Next: Reply 'RUN PHASE F' for reconciliation\n";
}

echo "\n";

exit(empty($results['failed']) ? 0 : 1);
