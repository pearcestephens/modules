#!/usr/bin/env php
<?php
/**
 * Phase E: Apply Pending Payroll Deductions to Vend (PROPER METHOD)
 *
 * Uses PaymentAllocationService which calls $vend->registerSalePayments()->post()
 * This is the ACTUAL working method you've built!
 *
 * Usage:
 *   php phase-e-proper.php [--limit=N] [--dry=1]
 *
 * @package CIS\CLI\StaffAccounts
 */

declare(strict_types=1);

// Load CIS application
define('CLI_SCRIPT', true);
require_once __DIR__ . '/../../../app.php';

// Load PaymentAllocationService directly
require_once __DIR__ . '/../lib/PaymentAllocationService.php';

use CIS\Modules\StaffAccounts\PaymentAllocationService;

// Setup PDO if not already available from app.php
if (!isset($pdo)) {
    $pdo = new PDO(
        'mysql:host=127.0.0.1;dbname=jcepnzzkmj;charset=utf8mb4',
        'jcepnzzkmj',
        'wprKh9Jq63',
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
}

// Parse arguments
$limit = null;
$dryRun = false;

foreach ($argv as $arg) {
    if (strpos($arg, '--limit=') === 0) {
        $limit = (int)substr($arg, 8);
    }
    if (strpos($arg, '--dry=') === 0) {
        $dryRun = (bool)(int)substr($arg, 6);
    }
}

// Banner
echo "\n";
echo "╔══════════════════════════════════════════════════╗\n";
echo "║  PHASE E: APPLY PAYROLL DEDUCTIONS (PROPER)     ║\n";
echo "╚══════════════════════════════════════════════════╝\n\n";

if ($dryRun) {
    echo "Mode: 🟡 DRY RUN (no changes will be made)\n";
} else {
    echo "Mode: 🔴 LIVE (payments will be applied to Vend)\n";
}

if ($limit) {
    echo "Limit: Processing first {$limit} payment(s)\n";
}
echo "\n";

// Initialize service
try {
    $service = new PaymentAllocationService($pdo);

    // Get pending deductions
    $pending = $service->getPendingDeductions();

    if ($limit) {
        $pending = array_slice($pending, 0, $limit);
    }

    $total = count($pending);

    if ($total === 0) {
        echo "✅ No pending deductions found\n\n";
        exit(0);
    }

    echo "Found {$total} pending payment" . ($total !== 1 ? 's' : '') . "\n\n";

    // Process each deduction
    $startTime = microtime(true);
    $successful = 0;
    $failed = 0;
    $errors = [];

    foreach ($pending as $idx => $deduction) {
        $num = $idx + 1;
        $name = $deduction['employee_name'];
        $amount = number_format($deduction['amount'], 2);

        echo "[{$num}/{$total}] {$name} (\${$amount})... ";

        if ($dryRun) {
            echo "🟡 SKIPPED (dry run)\n";
            continue;
        }

        try {
            // Use PaymentAllocationService to allocate
            $result = $service->allocateDeduction(
                (int)$deduction['id'],
                null, // performed_by (system)
                false // not dry run
            );

            if ($result['success']) {
                echo "✅ SUCCESS\n";
                if (!empty($result['vend_payment_id'])) {
                    echo "   Payment ID: {$result['vend_payment_id']}\n";
                }
                $successful++;
            } else {
                echo "❌ FAILED\n";
                $error = $result['error'] ?? 'Unknown error';
                echo "   Error: {$error}\n";
                $errors[] = "{$name}: {$error}";
                $failed++;
            }

        } catch (Exception $e) {
            echo "❌ EXCEPTION\n";
            echo "   Error: " . $e->getMessage() . "\n";
            $errors[] = "{$name}: " . $e->getMessage();
            $failed++;
        }

        // Rate limit: 300ms between requests
        if ($num < $total) {
            usleep(300000); // 300ms
        }
    }

    // Summary
    $duration = round(microtime(true) - $startTime, 2);

    echo "\n";
    echo "Summary:\n";
    echo "Processed: {$total}\n";
    echo "  ✅ Success: {$successful}\n";
    echo "  ❌ Failed: {$failed}\n";

    if (!empty($errors)) {
        echo "\nErrors:\n";
        foreach ($errors as $error) {
            echo "  • {$error}\n";
        }
    }

    echo "\nDuration: {$duration}s\n\n";

    exit($failed > 0 ? 1 : 0);

} catch (Exception $e) {
    echo "\n❌ FATAL ERROR: " . $e->getMessage() . "\n\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n\n";
    exit(1);
}
