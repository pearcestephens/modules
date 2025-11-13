<?php
/**
 * Phase E V3: SIMPLE - Just call the payment function directly
 * Based on your confirmation that vend_add_payment works for paying staff accounts
 */

declare(strict_types=1);

// Minimal setup
$_SERVER['DOCUMENT_ROOT'] = '/home/master/applications/jcepnzzkmj/public_html';
define('BASE_PATH', $_SERVER['DOCUMENT_ROOT']);

// Set Vend domain (just prefix, library adds .vendhq.com)
putenv('VEND_DOMAIN=vapeshed');

// Database - both PDO and mysqli for compatibility
$pdo = new PDO(
    "mysql:host=127.0.0.1;dbname=jcepnzzkmj;charset=utf8mb4",
    'jcepnzzkmj',
    'wprKh9Jq63',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

// MySQLi connection for vend library
$con = mysqli_connect('127.0.0.1', 'jcepnzzkmj', 'wprKh9Jq63', 'jcepnzzkmj');
if (!$con) {
    die("MySQL connection failed: " . mysqli_connect_error());
}

// Make connections globally available
$GLOBALS['pdo'] = $pdo;
$GLOBALS['con'] = $con;

// Load vend library
require_once BASE_PATH . '/assets/functions/xeroAPI/vend-payment-lib.php';

$options = getopt('', ['dry::', 'limit::', 'help']);

if (isset($options['help'])) {
    echo "Usage: php phase-e-v3-simple.php [--dry=0] [--limit=N]\n";
    exit(0);
}

$dry_run = isset($options['dry']) ? (int)$options['dry'] : 0;
$limit = isset($options['limit']) ? (int)$options['limit'] : null;

echo "\n╔══════════════════════════════════════════════════╗\n";
echo "║  PHASE E V3: SIMPLE PAYMENT APPLICATION          ║\n";
echo "╚══════════════════════════════════════════════════╝\n\n";
echo "Mode: " . ($dry_run ? "🔍 DRY RUN" : "🔴 LIVE") . "\n\n";

// Get pending payments
$stmt = $pdo->prepare("
    SELECT
        id,
        employee_name,
        vend_customer_id,
        amount,
        description
    FROM xero_payroll_deductions
    WHERE payroll_id = 69
      AND vend_customer_id IS NOT NULL
      AND allocation_status = 'pending'
    ORDER BY amount DESC
");
$stmt->execute();
$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($limit) {
    $payments = array_slice($payments, 0, $limit);
}

echo "Found " . count($payments) . " pending payments\n\n";

if (empty($payments)) {
    echo "Nothing to process.\n";
    exit(0);
}

$results = ['success' => [], 'failed' => []];
$start = microtime(true);

foreach ($payments as $idx => $payment) {
    $num = $idx + 1;
    $name = $payment['employee_name'];
    $amount = (float)$payment['amount'];
    $customer_id = $payment['vend_customer_id'];
    $note = "Payroll deduction - Oct 28, 2025 - {$name}";

    echo "[{$num}/" . count($payments) . "] {$name} (\${$amount})... ";

    if ($dry_run) {
        echo "SKIPPED (dry run)\n";
        continue;
    }

    try {
        // Call the working function you've used before
        $result = vend_add_payment_strict_auto(
            $customer_id,
            $amount,
            $note,
            '2025-10-28'
        );

        if ($result['success'] && !empty($result['payment_id'])) {
            // Update database
            $stmt = $pdo->prepare("
                UPDATE xero_payroll_deductions
                SET vend_payment_id = ?,
                    allocated_amount = ?,
                    allocation_status = 'allocated',
                    allocated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$result['payment_id'], $amount, $payment['id']]);

            echo "✅ OK (ID: " . substr($result['payment_id'], 0, 12) . "...)\n";
            $results['success'][] = $payment;
        } else {
            throw new Exception($result['error'] ?? 'Unknown error');
        }

        usleep(300000); // 300ms delay

    } catch (Exception $e) {
        echo "❌ FAILED: " . $e->getMessage() . "\n";
        $results['failed'][] = $payment;

        $stmt = $pdo->prepare("
            UPDATE xero_payroll_deductions
            SET allocation_status = 'failed'
            WHERE id = ?
        ");
        $stmt->execute([$payment['id']]);
    }
}

$elapsed = round(microtime(true) - $start, 2);

echo "\n╔══════════════════════════════════════════════════╗\n";
echo "║  COMPLETE                                         ║\n";
echo "╚══════════════════════════════════════════════════╝\n\n";
echo "Processed: " . count($payments) . "\n";
echo "  ✅ Success: " . count($results['success']) . "\n";
echo "  ❌ Failed: " . count($results['failed']) . "\n";
echo "Duration: {$elapsed}s\n\n";

// ✅ CRITICAL FIX: Cleanup database connections before exit
if (isset($con) && $con instanceof mysqli && !empty($con->thread_id)) {
    @mysqli_close($con);
}
if (isset($pdo)) {
    $pdo = null;
}

if (count($results['success']) === count($payments)) {
    echo "🎉 100% SUCCESS!\n\n";
    exit(0);
} else {
    echo "⚠️  Some failures - check logs\n\n";
    exit(1);
}
