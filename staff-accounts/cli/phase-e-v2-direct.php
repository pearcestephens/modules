<?php
/**
 * Phase E V2: Apply Payroll Deductions to Vend (Direct API Implementation)
 *
 * Uses correct Vend API endpoint: PUT /api/2.0/register_sales
 * Based on patterns discovered in vend-payment-lib.php
 *
 * Flow:
 * 1. Create register sale with customer association
 * 2. Add "Account" payment to the sale
 * 3. Close the sale
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
    echo "Usage: php phase-e-v2-direct.php [OPTIONS]\n";
    echo "  --dry      Dry run mode (1=preview, 0=LIVE, default=0)\n";
    echo "  --limit    Limit number of payments\n";
    echo "  --help     Show this help\n";
    exit(0);
}

$dry_run = isset($options['dry']) ? (int)$options['dry'] : 0;
$limit = isset($options['limit']) ? (int)$options['limit'] : null;

echo "\n";
echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║  PHASE E V2: APPLY TO VEND (DIRECT API)                     ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n";
echo "\n";
echo "Mode: " . ($dry_run ? "🔍 DRY RUN" : "🔴 LIVE MODE - APPLYING REAL PAYMENTS") . "\n";
echo "API: PUT /api/2.0/register_sales (CORRECT ENDPOINT)\n\n";

// Get Vend configuration
$stmt = $pdo->query("SELECT config_value FROM configuration WHERE config_label = 'vend_access_token' LIMIT 1");
$token_row = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$token_row) {
    die("ERROR: Vend API token not found in configuration table\n");
}
$vend_token = $token_row['config_value'];

$stmt = $pdo->query("SELECT config_value FROM configuration WHERE config_label = 'vend_domain' LIMIT 1");
$domain_row = $stmt->fetch(PDO::FETCH_ASSOC);
$vend_domain = $domain_row ? $domain_row['config_value'] : 'vapeshed.vendhq.com';

echo "[1/6] Configuration loaded\n";
echo "  • Domain: {$vend_domain}\n";
echo "  • Token: " . substr($vend_token, 0, 12) . "...\n\n";

// Use hardcoded IDs for Hamilton East register and Store Credit payment type
// These were fetched from Vend API and are stable values
$register_id = '02dcd191-ae2b-11e6-f485-8eceed6ff0d6'; // Hamilton East
$payment_type_id = '02dcd191-ae14-11e6-f485-8eceefa0014c'; // Store Credit

echo "[2/6] Using Hamilton East register\n";
echo "  • Register ID: {$register_id}\n\n";

echo "[3/6] Using Store Credit payment type\n";
echo "  • Payment Type ID: {$payment_type_id}\n\n";// Load plan
$plan_file = '/home/master/applications/jcepnzzkmj/private_html/ai_runs/payroll/20251103/phase_d_vend_plan.json';
$plan = json_decode(file_get_contents($plan_file), true);

$payrun_id = $plan['payrun']['id'];
$payrun_date = $plan['payrun']['payment_date'];

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

echo "[4/6] Found " . count($payments) . " pending payments\n";
echo "  • Payrun: {$payrun_date} (ID: {$payrun_id})\n\n";

if (empty($payments)) {
    echo "No pending payments to process. Exiting.\n";
    exit(0);
}

// Process payments
echo "[5/6] Applying payments via register_sales...\n\n";

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
        // Build sale payload
        $sale_date = date('Y-m-d H:i:s'); // ISO 8601 format
        $note = "Payroll deduction - {$payrun_date} - {$employee}";

        $sale_payload = [
            'register_id' => $register_id,
            'customer_id' => $customer_id,
            'status' => 'CLOSED',
            'sale_date' => $sale_date,
            'note' => $note,
            'line_items' => [], // No products, just payment
            'payments' => [
                [
                    'payment_type_id' => $payment_type_id,
                    'amount' => $amount,
                    'payment_date' => $sale_date
                ]
            ]
        ];

        // Call Vend API
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => "https://{$vend_domain}/api/2.0/register_sales",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($sale_payload),
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $vend_token,
                'Content-Type: application/json',
                'Accept: application/json'
            ],
            CURLOPT_TIMEOUT => 30
        ]);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code >= 200 && $http_code < 300) {
            $result = json_decode($response, true);

            // Extract sale ID from response
            $vend_sale_id = null;
            if (isset($result['data']['id'])) {
                $vend_sale_id = $result['data']['id'];
            } else if (isset($result['register_sale']['id'])) {
                $vend_sale_id = $result['register_sale']['id'];
            } else if (isset($result['id'])) {
                $vend_sale_id = $result['id'];
            }

            if ($vend_sale_id) {
                // Update database
                $stmt = $pdo->prepare("
                    UPDATE xero_payroll_deductions
                    SET vend_payment_id = ?,
                        allocated_amount = ?,
                        allocation_status = 'allocated',
                        allocated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$vend_sale_id, $amount, $deduction_id]);

                echo "✅ SUCCESS (Sale: " . substr($vend_sale_id, 0, 12) . "...)\n";

                $results['success'][] = array_merge($payment, [
                    'vend_payment_id' => $vend_sale_id
                ]);
            } else {
                throw new Exception("No sale ID in response");
            }

        } else {
            $error_data = json_decode($response, true);
            $error_msg = is_array($error_data) ?
                json_encode($error_data) :
                "HTTP {$http_code}: " . substr($response, 0, 100);

            throw new Exception($error_msg);
        }

        // Rate limit: 300ms between calls
        usleep(300000);

    } catch (Exception $e) {
        $error = substr($e->getMessage(), 0, 80);
        echo "❌ FAILED ({$error}...)\n";

        $results['failed'][] = array_merge($payment, [
            'error' => $e->getMessage()
        ]);

        // Mark as failed
        $stmt = $pdo->prepare("
            UPDATE xero_payroll_deductions
            SET allocation_status = 'failed'
            WHERE id = ?
        ");
        $stmt->execute([$deduction_id]);
    }
}

$elapsed = round(microtime(true) - $start_time, 2);

echo "\n[6/6] Processing complete in {$elapsed}s\n\n";

// Verify database state
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

echo "Database state:\n";
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
    echo "\n⚠️  FAILURES:\n";
    foreach (array_slice($results['failed'], 0, 5) as $f) {
        $err = substr($f['error'], 0, 60);
        echo "  • {$f['employee_name']}: {$err}...\n";
    }

    if (count($results['failed']) > 5) {
        $more = count($results['failed']) - 5;
        echo "  ... and {$more} more\n";
    }
}

// Save report
$report = [
    'executed_at' => date('Y-m-d H:i:s'),
    'version' => 'v2_direct_api',
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

echo "\n✅ Report: {$report_file}\n";

if ($dry_run) {
    echo "🔍 DRY RUN - run with --dry=0 to apply for real\n";
} else {
    if (count($results['success']) === count($payments)) {
        echo "🎉 100% SUCCESS! Ready for Phase F\n";
    } else if (count($results['success']) > 0) {
        echo "⚠️  Partial success - review failures\n";
    } else {
        echo "❌ All failed - check configuration\n";
    }
}

echo "\n";

exit(count($results['failed']) === 0 ? 0 : 1);
