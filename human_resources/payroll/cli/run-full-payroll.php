#!/usr/bin/env php
<?php
/**
 * Complete Payroll Run - End-to-End Automation
 *
 * This script runs the COMPLETE payroll workflow:
 * 1. Fetch deductions from Xero (latest posted payrun)
 * 2. Apply payments to Vend accounts using proven method
 * 3. Record in new HR payroll tables
 * 4. Generate reports
 *
 * Usage:
 *   php run-full-payroll.php [--dry-run] [--xero-payrun-id=XXX]
 *
 * @package HumanResources\Payroll\CLI
 */

declare(strict_types=1);

// Bootstrap
require_once __DIR__ . '/../bootstrap.php';
require_once BASE_PATH . 'assets/functions/xeroAPI/xero-payruns.php';
require_once BASE_PATH . 'assets/functions/xeroAPI/vend-accounts.php';

// Parse arguments
$dryRun = in_array('--dry-run', $argv);
$xeroPayrunId = null;
foreach ($argv as $arg) {
    if (strpos($arg, '--xero-payrun-id=') === 0) {
        $xeroPayrunId = substr($arg, 17);
    }
}

echo "\n";
echo "╔══════════════════════════════════════════════════╗\n";
echo "║     COMPLETE PAYROLL RUN - FULL AUTOMATION       ║\n";
echo "╚══════════════════════════════════════════════════╝\n\n";

if ($dryRun) {
    echo "🟡 MODE: DRY RUN (no changes will be made)\n\n";
} else {
    echo "🔴 MODE: LIVE (payments will be applied)\n\n";
}

try {
    $db = getPayrollDb();

    // ==================================================================
    // STEP 1: Get Xero API clients
    // ==================================================================
    echo "📡 STEP 1: Connecting to Xero API...\n";

    $xeroClients = xero_get_api_clients();
    $payrollNzApi = $xeroClients['payrollNzApi'];
    $xeroTenantId = $xeroClients['xeroTenantId'];

    echo "   ✅ Connected to Xero tenant: " . substr($xeroTenantId, 0, 8) . "...\n\n";

    // ==================================================================
    // STEP 2: Fetch deductions from Xero (latest posted payrun)
    // ==================================================================
    echo "📥 STEP 2: Fetching deductions from Xero payrun...\n";

    $deductions = getStaffLatestAccountPaymentsSummaryFromLatestPostedPayRun(
        $payrollNzApi,
        $xeroTenantId
    );

    echo "   ✅ Found " . count($deductions) . " staff with deductions\n\n";

    // ==================================================================
    // STEP 3: Create payroll run record
    // ==================================================================
    echo "📝 STEP 3: Creating payroll run record...\n";

    $payrun = getMostRecentPostedPayRun($payrollNzApi, $xeroTenantId);
    $paymentDate = $payrun->getPaymentDate()->format('Y-m-d');
    $periodStart = $payrun->getPeriodStartDate()->format('Y-m-d');
    $periodEnd = $payrun->getPeriodEndDate()->format('Y-m-d');

    if (!$dryRun) {
        $stmt = $db->prepare("
            INSERT INTO payroll_runs
            (run_uuid, period_start, period_end, payment_date, status, xero_payrun_id, created_by)
            VALUES (?, ?, ?, ?, 'in_progress', ?, 1)
        ");
        $runUuid = uniqid('payrun_', true);
        $stmt->execute([
            $runUuid,
            $periodStart,
            $periodEnd,
            $paymentDate,
            $payrun->getPayRunId()
        ]);
        $payrollRunId = (int)$db->lastInsertId();

        echo "   ✅ Created payroll run ID: {$payrollRunId}\n";
        echo "   📅 Period: {$periodStart} to {$periodEnd}\n";
        echo "   💰 Payment Date: {$paymentDate}\n\n";
    } else {
        $payrollRunId = null;
        echo "   🟡 [DRY RUN] Would create payroll run\n";
        echo "   📅 Period: {$periodStart} to {$periodEnd}\n";
        echo "   💰 Payment Date: {$paymentDate}\n\n";
    }

    // ==================================================================
    // STEP 4: Apply payments to Vend accounts
    // ==================================================================
    echo "💳 STEP 4: Applying payments to Vend accounts...\n\n";

    $successful = 0;
    $failed = 0;
    $totalAllocated = 0.0;
    $errors = [];

    foreach ($deductions as $idx => $deduction) {
        $num = $idx + 1;
        $total = count($deductions);
        $name = "{$deduction->firstName} {$deduction->lastName}";
        $amount = (float)$deduction->deductionAmount;

        echo "[{$num}/{$total}] {$name} (\${$amount})... ";

        // Get Vend customer ID from CIS users table
        $user = getCISUserObjectByXeroEmployeeID($deduction->employeeID);

        if (!$user || empty($user->vend_customer_account)) {
            echo "❌ NO VEND ACCOUNT\n";
            $errors[] = "{$name}: No Vend customer account mapped";
            $failed++;
            continue;
        }

        $vendCustomerId = $user->vend_customer_account;

        if ($dryRun) {
            echo "🟡 WOULD ALLOCATE\n";
            continue;
        }

        try {
            // Use the PROVEN working function!
            $result = vend_add_payment_strict_auto(
                $vendCustomerId,
                $amount,
                STRICT_REGISTER_NAME,
                STRICT_PAYMENT_TYPE_NAME
            );

            if ($result['ok'] && $result['allocated'] > 0) {
                echo "✅ SUCCESS (allocated: \${$result['allocated']})\n";

                // Record in payroll_deduction_lines
                $stmt = $db->prepare("
                    INSERT INTO payroll_deduction_lines
                    (payroll_run_id, staff_id, xero_employee_id, vend_customer_id,
                     deduction_type, amount, allocated_amount, status, payment_details)
                    VALUES (?, ?, ?, ?, 'account_payment', ?, ?, 'allocated', ?)
                ");
                $stmt->execute([
                    $payrollRunId,
                    $user->id ?? null,
                    $deduction->employeeID,
                    $vendCustomerId,
                    $amount,
                    $result['allocated'],
                    json_encode($result['details'] ?? [])
                ]);

                $successful++;
                $totalAllocated += $result['allocated'];
            } else {
                echo "❌ FAILED: {$result['reason']}\n";
                $errors[] = "{$name}: {$result['reason']}";
                $failed++;
            }

        } catch (Throwable $e) {
            echo "❌ EXCEPTION: " . $e->getMessage() . "\n";
            $errors[] = "{$name}: " . $e->getMessage();
            $failed++;
        }

        // Rate limit: 300ms between requests
        if ($num < $total) {
            usleep(300000);
        }
    }

    echo "\n";

    // ==================================================================
    // STEP 5: Update payroll run status
    // ==================================================================
    if (!$dryRun && $payrollRunId) {
        $status = ($failed === 0) ? 'completed' : 'completed_with_errors';

        $stmt = $db->prepare("
            UPDATE payroll_runs
            SET status = ?,
                vend_allocations_completed = 1,
                vend_allocations_count = ?,
                vend_allocations_amount = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$status, $successful, $totalAllocated, $payrollRunId]);
    }

    // ==================================================================
    // FINAL SUMMARY
    // ==================================================================
    echo "╔══════════════════════════════════════════════════╗\n";
    echo "║              PAYROLL RUN COMPLETE                ║\n";
    echo "╚══════════════════════════════════════════════════╝\n\n";

    echo "📊 Summary:\n";
    echo "   Total Staff: " . count($deductions) . "\n";
    echo "   ✅ Successful: {$successful}\n";
    echo "   ❌ Failed: {$failed}\n";
    echo "   💰 Total Allocated: \$" . number_format($totalAllocated, 2) . "\n";

    if (!empty($errors)) {
        echo "\n❌ Errors:\n";
        foreach ($errors as $error) {
            echo "   • {$error}\n";
        }
    }

    if (!$dryRun && $payrollRunId) {
        echo "\n📋 Payroll Run ID: {$payrollRunId}\n";
        echo "📅 Period: {$periodStart} to {$periodEnd}\n";
        echo "💰 Payment Date: {$paymentDate}\n";
    }

    echo "\n";

    exit($failed > 0 ? 1 : 0);

} catch (Throwable $e) {
    echo "\n";
    echo "❌ FATAL ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n";
    echo $e->getTraceAsString() . "\n\n";
    exit(1);
}
