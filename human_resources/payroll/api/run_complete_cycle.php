<?php
/**
 * Payroll API - Run Complete Payroll Cycle
 *
 * POST /modules/human_resources/payroll/?api=run_complete_cycle
 *
 * Request body (JSON):
 * {
 *   "dry_run": false,
 *   "xero_payrun_id": "optional-payrun-id"
 * }
 *
 * Response (JSON):
 * {
 *   "success": true,
 *   "payroll_run_id": 123,
 *   "summary": {
 *     "total_staff": 10,
 *     "successful": 9,
 *     "failed": 1,
 *     "total_allocated": 1234.56
 *   },
 *   "errors": ["Staff A: No Vend account"],
 *   "period": {
 *     "start": "2025-11-01",
 *     "end": "2025-11-07",
 *     "payment_date": "2025-11-14"
 *   }
 * }
 *
 * @package HumanResources\Payroll\API
 */

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once BASE_PATH . 'assets/functions/xeroAPI/xero-payruns.php';
require_once BASE_PATH . 'assets/functions/xeroAPI/vend-accounts.php';

// Only POST allowed
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Parse request
$input = json_decode(file_get_contents('php://input'), true) ?? [];
$dryRun = (bool)($input['dry_run'] ?? false);
$xeroPayrunId = $input['xero_payrun_id'] ?? null;

try {
    $db = getPayrollDb();

    // Get Xero API clients
    $xeroClients = xero_get_api_clients();
    $payrollNzApi = $xeroClients['payrollNzApi'];
    $xeroTenantId = $xeroClients['xeroTenantId'];

    // Fetch deductions from Xero
    $deductions = getStaffLatestAccountPaymentsSummaryFromLatestPostedPayRun(
        $payrollNzApi,
        $xeroTenantId
    );

    // Create payroll run record
    $payrun = getMostRecentPostedPayRun($payrollNzApi, $xeroTenantId);
    $paymentDate = $payrun->getPaymentDate()->format('Y-m-d');
    $periodStart = $payrun->getPeriodStartDate()->format('Y-m-d');
    $periodEnd = $payrun->getPeriodEndDate()->format('Y-m-d');

    $payrollRunId = null;

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
    }

    // Apply payments to Vend accounts
    $successful = 0;
    $failed = 0;
    $totalAllocated = 0.0;
    $errors = [];

    foreach ($deductions as $deduction) {
        $name = "{$deduction->firstName} {$deduction->lastName}";
        $amount = (float)$deduction->deductionAmount;

        // Get Vend customer ID
        $user = getCISUserObjectByXeroEmployeeID($deduction->employeeID);

        if (!$user || empty($user->vend_customer_account)) {
            $errors[] = "{$name}: No Vend customer account mapped";
            $failed++;
            continue;
        }

        $vendCustomerId = $user->vend_customer_account;

        if ($dryRun) {
            continue; // Skip actual allocation in dry run
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
                $errors[] = "{$name}: {$result['reason']}";
                $failed++;
            }

        } catch (Throwable $e) {
            $errors[] = "{$name}: " . $e->getMessage();
            $failed++;
        }

        // Rate limit: 300ms between requests
        usleep(300000);
    }

    // Update payroll run status
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

    // Return success response
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'dry_run' => $dryRun,
        'payroll_run_id' => $payrollRunId,
        'summary' => [
            'total_staff' => count($deductions),
            'successful' => $successful,
            'failed' => $failed,
            'total_allocated' => $totalAllocated
        ],
        'errors' => $errors,
        'period' => [
            'start' => $periodStart,
            'end' => $periodEnd,
            'payment_date' => $paymentDate
        ],
        'xero_payrun_id' => $payrun->getPayRunId(),
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_PRETTY_PRINT);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ], JSON_PRETTY_PRINT);
}
