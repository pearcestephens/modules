#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * Payslip Snapshot CLI Tool
 *
 * Downloads and syncs payslip data from Xero/Deputy/CIS and inserts into database
 * as immutable "final truth" snapshot. Allows edits with automatic diff calculation.
 *
 * Usage:
 *   php snapshot_payslip.php --payslip-id=123 [--force]
 *   php snapshot_payslip.php --pay-run-id=456 --all [--force]
 *   php snapshot_payslip.php --list-recent
 *
 * Options:
 *   --payslip-id=ID     Snapshot specific payslip by Xero payslip ID
 *   --pay-run-id=ID     Snapshot all payslips in pay run
 *   --all               Process all payslips in pay run
 *   --force             Overwrite existing snapshot
 *   --list-recent       List recent pay runs
 *   --help              Show this help
 *
 * @package PayrollModule\CLI
 * @version 1.0.0
 */

// Bootstrap
require_once dirname(__DIR__, 4) . '/app.php';
require_once dirname(__DIR__) . '/lib/xero_config.php';

use XeroAPI\XeroPHP\Api\PayrollNzApi;
use XeroAPI\XeroPHP\Configuration;

// ============================================================================
// CONFIGURATION
// ============================================================================

$config = [
    'db_host' => '127.0.0.1',
    'db_name' => 'jcepnzzkmj',
    'db_user' => 'jcepnzzkmj',
    'db_pass' => 'wprKh9Jq63',
    'xero_tenant_id' => $GLOBALS['xeroTenantId'] ?? null,
];

// ============================================================================
// PARSE ARGUMENTS
// ============================================================================

$options = getopt('', [
    'payslip-id:',
    'pay-run-id:',
    'all',
    'force',
    'list-recent',
    'help'
]);

if (isset($options['help']) || empty($options)) {
    showHelp();
    exit(0);
}

// ============================================================================
// DATABASE CONNECTION
// ============================================================================

try {
    $pdo = new PDO(
        "mysql:host={$config['db_host']};dbname={$config['db_name']};charset=utf8mb4",
        $config['db_user'],
        $config['db_pass'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
        ]
    );

    log_message("✓ Database connected");

} catch (PDOException $e) {
    log_error("✗ Database connection failed: " . $e->getMessage());
    exit(1);
}

// ============================================================================
// MAIN EXECUTION
// ============================================================================

try {

    if (isset($options['list-recent'])) {
        listRecentPayRuns($pdo);
        exit(0);
    }

    if (isset($options['payslip-id'])) {
        // Single payslip snapshot
        $payslipId = $options['payslip-id'];
        $force = isset($options['force']);

        log_message("Processing payslip: {$payslipId}");
        $result = snapshotPayslip($pdo, $payslipId, $force);

        if ($result['success']) {
            log_success("✓ Payslip snapshot created successfully");
            log_message("  Snapshot ID: {$result['snapshot_id']}");
            log_message("  Staff: {$result['staff_name']}");
            log_message("  Period: {$result['period_start']} to {$result['period_end']}");
            log_message("  Gross Pay: \${$result['gross_pay']}");
            log_message("  Net Pay: \${$result['net_pay']}");
            exit(0);
        } else {
            log_error("✗ Failed: {$result['error']}");
            exit(1);
        }
    }

    if (isset($options['pay-run-id'])) {
        // All payslips in pay run
        $payRunId = $options['pay-run-id'];
        $force = isset($options['force']);

        log_message("Processing pay run: {$payRunId}");
        $results = snapshotPayRun($pdo, $payRunId, $force);

        log_success("✓ Pay run snapshot completed");
        log_message("  Total processed: {$results['total']}");
        log_message("  Successful: {$results['success']}");
        log_message("  Failed: {$results['failed']}");
        log_message("  Skipped (already exists): {$results['skipped']}");

        if (!empty($results['errors'])) {
            log_message("\nErrors:");
            foreach ($results['errors'] as $error) {
                log_error("  - {$error}");
            }
        }

        exit($results['failed'] > 0 ? 1 : 0);
    }

    log_error("✗ No action specified. Use --help for usage.");
    exit(1);

} catch (Exception $e) {
    log_error("✗ Fatal error: " . $e->getMessage());
    log_error("  Stack trace: " . $e->getTraceAsString());
    exit(1);
}

// ============================================================================
// FUNCTIONS
// ============================================================================

/**
 * Snapshot a single payslip
 */
function snapshotPayslip(PDO $pdo, string $xeroPayslipId, bool $force = false): array
{
    global $config;

    try {
        // Check if already exists
        if (!$force) {
            $stmt = $pdo->prepare("
                SELECT id FROM payroll_payslips
                WHERE xero_payslip_id = ? AND snapshot_status = 'final'
            ");
            $stmt->execute([$xeroPayslipId]);

            if ($stmt->fetch()) {
                return [
                    'success' => false,
                    'error' => 'Snapshot already exists (use --force to overwrite)'
                ];
            }
        }

        // Fetch from Xero
        log_message("  Fetching from Xero...");
        $xeroData = fetchPayslipFromXero($xeroPayslipId);

        if (!$xeroData) {
            return ['success' => false, 'error' => 'Failed to fetch from Xero'];
        }

        // Fetch Deputy timesheet data
        log_message("  Fetching Deputy timesheet...");
        $deputyData = fetchDeputyTimesheet($pdo, $xeroData['employee_id'], $xeroData['period_start'], $xeroData['period_end']);

        // Fetch CIS context data
        log_message("  Fetching CIS context...");
        $cisData = fetchCISContext($pdo, $xeroData['employee_id']);

        // Begin transaction
        $pdo->beginTransaction();

        try {
            // Insert main payslip record
            log_message("  Inserting payslip record...");
            $payslipDbId = insertPayslipRecord($pdo, $xeroData, $deputyData, $cisData, $force);

            // Insert earnings lines
            log_message("  Inserting earnings lines...");
            insertEarningsLines($pdo, $payslipDbId, $xeroData['earnings_lines']);

            // Insert deduction lines
            log_message("  Inserting deduction lines...");
            insertDeductionLines($pdo, $payslipDbId, $xeroData['deduction_lines']);

            // Insert leave lines
            log_message("  Inserting leave lines...");
            insertLeaveLines($pdo, $payslipDbId, $xeroData['leave_lines']);

            // Insert reimbursement lines
            log_message("  Inserting reimbursement lines...");
            insertReimbursementLines($pdo, $payslipDbId, $xeroData['reimbursement_lines']);

            // Create snapshot JSON
            log_message("  Creating snapshot JSON...");
            $snapshot = createSnapshotJSON($xeroData, $deputyData, $cisData);

            // Update with snapshot and hash
            $snapshotHash = hash('sha256', $snapshot);
            $stmt = $pdo->prepare("
                UPDATE payroll_payslips
                SET snapshot_json = ?,
                    snapshot_hash = ?,
                    snapshot_status = 'final',
                    snapshot_created_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$snapshot, $snapshotHash, $payslipDbId]);

            $pdo->commit();

            return [
                'success' => true,
                'snapshot_id' => $payslipDbId,
                'staff_name' => $xeroData['employee_name'],
                'period_start' => $xeroData['period_start'],
                'period_end' => $xeroData['period_end'],
                'gross_pay' => $xeroData['gross_earnings'],
                'net_pay' => $xeroData['net_pay']
            ];

        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }

    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Snapshot all payslips in a pay run
 */
function snapshotPayRun(PDO $pdo, string $xeroPayRunId, bool $force = false): array
{
    $results = [
        'total' => 0,
        'success' => 0,
        'failed' => 0,
        'skipped' => 0,
        'errors' => []
    ];

    try {
        // Fetch pay run from Xero
        log_message("Fetching pay run from Xero...");
        $payslips = fetchPayRunFromXero($xeroPayRunId);

        if (empty($payslips)) {
            log_error("No payslips found in pay run");
            return $results;
        }

        $results['total'] = count($payslips);
        log_message("Found {$results['total']} payslips");

        // Process each payslip
        foreach ($payslips as $index => $payslipId) {
            $num = $index + 1;
            log_message("\n[{$num}/{$results['total']}] Processing payslip: {$payslipId}");

            $result = snapshotPayslip($pdo, $payslipId, $force);

            if ($result['success']) {
                $results['success']++;
                log_success("  ✓ Success: {$result['staff_name']}");
            } else {
                if (strpos($result['error'], 'already exists') !== false) {
                    $results['skipped']++;
                    log_message("  ⊘ Skipped (already exists)");
                } else {
                    $results['failed']++;
                    $results['errors'][] = "{$payslipId}: {$result['error']}";
                    log_error("  ✗ Failed: {$result['error']}");
                }
            }
        }

        return $results;

    } catch (Exception $e) {
        $results['errors'][] = "Pay run fetch failed: " . $e->getMessage();
        return $results;
    }
}

/**
 * Fetch payslip from Xero
 */
function fetchPayslipFromXero(string $payslipId): ?array
{
    global $config;

    try {
        // Initialize Xero API
        $xeroConfig = Configuration::getDefaultConfiguration()->setAccessToken(getXeroAccessToken());
        $payrollApi = new PayrollNzApi(new GuzzleHttp\Client(), $xeroConfig);

        // Fetch payslip
        $response = $payrollApi->getPaySlip($config['xero_tenant_id'], $payslipId);
        $payslip = $response->getPaySlip();

        if (!$payslip) {
            return null;
        }

        // Extract data
        return [
            'xero_payslip_id' => $payslip->getPaySlipId(),
            'xero_pay_run_id' => $payslip->getPayRunId(),
            'employee_id' => $payslip->getEmployeeId(),
            'employee_name' => $payslip->getFirstName() . ' ' . $payslip->getLastName(),
            'period_start' => $payslip->getPeriodStartDate()->format('Y-m-d'),
            'period_end' => $payslip->getPeriodEndDate()->format('Y-m-d'),
            'payment_date' => $payslip->getPaymentDate()->format('Y-m-d'),
            'gross_earnings' => (float)$payslip->getGrossEarnings(),
            'total_deductions' => (float)$payslip->getTotalDeductions(),
            'total_reimbursements' => (float)$payslip->getTotalReimbursements(),
            'net_pay' => (float)$payslip->getNetPay(),
            'tax' => (float)$payslip->getTax(),
            'earnings_lines' => extractEarningsLines($payslip),
            'deduction_lines' => extractDeductionLines($payslip),
            'leave_lines' => extractLeaveLines($payslip),
            'reimbursement_lines' => extractReimbursementLines($payslip)
        ];

    } catch (Exception $e) {
        log_error("Xero API error: " . $e->getMessage());
        return null;
    }
}

/**
 * Fetch pay run from Xero
 */
function fetchPayRunFromXero(string $payRunId): array
{
    global $config;

    try {
        $xeroConfig = Configuration::getDefaultConfiguration()->setAccessToken(getXeroAccessToken());
        $payrollApi = new PayrollNzApi(new GuzzleHttp\Client(), $xeroConfig);

        $response = $payrollApi->getPayRun($config['xero_tenant_id'], $payRunId);
        $payRun = $response->getPayRun();

        if (!$payRun) {
            return [];
        }

        $payslipIds = [];
        foreach ($payRun->getPaySlips() as $payslip) {
            $payslipIds[] = $payslip->getPaySlipId();
        }

        return $payslipIds;

    } catch (Exception $e) {
        log_error("Xero API error: " . $e->getMessage());
        return [];
    }
}

/**
 * Fetch Deputy timesheet data
 */
function fetchDeputyTimesheet(PDO $pdo, string $xeroEmployeeId, string $periodStart, string $periodEnd): array
{
    try {
        // Get staff_id from xero_employee_id
        $stmt = $pdo->prepare("
            SELECT id, deputy_id
            FROM users
            WHERE xero_employee_id = ?
        ");
        $stmt->execute([$xeroEmployeeId]);
        $staff = $stmt->fetch();

        if (!$staff || !$staff['deputy_id']) {
            return ['found' => false];
        }

        // Fetch Deputy timesheets
        $stmt = $pdo->prepare("
            SELECT
                date,
                start_time,
                end_time,
                total_hours,
                ordinary_hours,
                overtime_hours,
                break_duration,
                approved,
                approved_by,
                approved_at
            FROM deputy_timesheets
            WHERE deputy_user_id = ?
            AND date >= ?
            AND date <= ?
            ORDER BY date, start_time
        ");
        $stmt->execute([$staff['deputy_id'], $periodStart, $periodEnd]);
        $timesheets = $stmt->fetchAll();

        // Calculate totals
        $totals = [
            'total_hours' => 0,
            'ordinary_hours' => 0,
            'overtime_hours' => 0,
            'approved_count' => 0,
            'unapproved_count' => 0
        ];

        foreach ($timesheets as $ts) {
            $totals['total_hours'] += (float)$ts['total_hours'];
            $totals['ordinary_hours'] += (float)$ts['ordinary_hours'];
            $totals['overtime_hours'] += (float)$ts['overtime_hours'];

            if ($ts['approved']) {
                $totals['approved_count']++;
            } else {
                $totals['unapproved_count']++;
            }
        }

        return [
            'found' => true,
            'staff_id' => $staff['id'],
            'deputy_id' => $staff['deputy_id'],
            'timesheets' => $timesheets,
            'totals' => $totals
        ];

    } catch (Exception $e) {
        log_error("Deputy fetch error: " . $e->getMessage());
        return ['found' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Fetch CIS context data
 */
function fetchCISContext(PDO $pdo, string $xeroEmployeeId): array
{
    try {
        $stmt = $pdo->prepare("
            SELECT
                id,
                first_name,
                last_name,
                email,
                phone,
                deputy_id,
                xero_employee_id,
                employment_type,
                hourly_rate,
                salary,
                department,
                position,
                manager_id,
                start_date,
                end_date,
                status
            FROM users
            WHERE xero_employee_id = ?
        ");
        $stmt->execute([$xeroEmployeeId]);
        $user = $stmt->fetch();

        if (!$user) {
            return ['found' => false];
        }

        return [
            'found' => true,
            'staff' => $user
        ];

    } catch (Exception $e) {
        return ['found' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Insert payslip record
 */
function insertPayslipRecord(PDO $pdo, array $xeroData, array $deputyData, array $cisData, bool $force): int
{
    // Get staff_id
    $staffId = $cisData['staff']['id'] ?? null;

    if (!$staffId) {
        throw new Exception("Staff not found in CIS for Xero employee: {$xeroData['employee_id']}");
    }

    // Delete existing if force
    if ($force) {
        $stmt = $pdo->prepare("DELETE FROM payroll_payslips WHERE xero_payslip_id = ?");
        $stmt->execute([$xeroData['xero_payslip_id']]);
    }

    // Insert
    $stmt = $pdo->prepare("
        INSERT INTO payroll_payslips (
            xero_payslip_id,
            xero_pay_run_id,
            xero_employee_id,
            staff_id,
            period_start,
            period_end,
            payment_date,
            gross_earnings,
            total_deductions,
            total_reimbursements,
            net_pay,
            tax,
            deputy_total_hours,
            deputy_ordinary_hours,
            deputy_overtime_hours,
            snapshot_status,
            created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'final', NOW())
    ");

    $stmt->execute([
        $xeroData['xero_payslip_id'],
        $xeroData['xero_pay_run_id'],
        $xeroData['employee_id'],
        $staffId,
        $xeroData['period_start'],
        $xeroData['period_end'],
        $xeroData['payment_date'],
        $xeroData['gross_earnings'],
        $xeroData['total_deductions'],
        $xeroData['total_reimbursements'],
        $xeroData['net_pay'],
        $xeroData['tax'],
        $deputyData['totals']['total_hours'] ?? null,
        $deputyData['totals']['ordinary_hours'] ?? null,
        $deputyData['totals']['overtime_hours'] ?? null
    ]);

    return (int)$pdo->lastInsertId();
}

/**
 * Insert earnings lines
 */
function insertEarningsLines(PDO $pdo, int $payslipId, array $lines): void
{
    if (empty($lines)) {
        return;
    }

    $stmt = $pdo->prepare("
        INSERT INTO payroll_earnings_lines (
            payslip_id,
            xero_earnings_rate_id,
            earnings_type,
            description,
            rate_per_unit,
            number_of_units,
            fixed_amount,
            amount,
            line_order
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    foreach ($lines as $index => $line) {
        $stmt->execute([
            $payslipId,
            $line['earnings_rate_id'] ?? null,
            $line['earnings_type'],
            $line['description'],
            $line['rate_per_unit'],
            $line['number_of_units'],
            $line['fixed_amount'],
            $line['amount'],
            $index + 1
        ]);
    }
}

/**
 * Insert deduction lines
 */
function insertDeductionLines(PDO $pdo, int $payslipId, array $lines): void
{
    if (empty($lines)) {
        return;
    }

    $stmt = $pdo->prepare("
        INSERT INTO payroll_deduction_lines (
            payslip_id,
            xero_deduction_type_id,
            deduction_type,
            description,
            percentage,
            fixed_amount,
            amount,
            line_order
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");

    foreach ($lines as $index => $line) {
        $stmt->execute([
            $payslipId,
            $line['deduction_type_id'] ?? null,
            $line['deduction_type'],
            $line['description'],
            $line['percentage'],
            $line['fixed_amount'],
            $line['amount'],
            $index + 1
        ]);
    }
}

/**
 * Insert leave lines
 */
function insertLeaveLines(PDO $pdo, int $payslipId, array $lines): void
{
    if (empty($lines)) {
        return;
    }

    $stmt = $pdo->prepare("
        INSERT INTO payroll_leave_lines (
            payslip_id,
            xero_leave_type_id,
            leave_type,
            description,
            hours_taken,
            hours_accrued,
            balance,
            line_order
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");

    foreach ($lines as $index => $line) {
        $stmt->execute([
            $payslipId,
            $line['leave_type_id'] ?? null,
            $line['leave_type'],
            $line['description'],
            $line['hours_taken'],
            $line['hours_accrued'],
            $line['balance'],
            $index + 1
        ]);
    }
}

/**
 * Insert reimbursement lines
 */
function insertReimbursementLines(PDO $pdo, int $payslipId, array $lines): void
{
    if (empty($lines)) {
        return;
    }

    $stmt = $pdo->prepare("
        INSERT INTO payroll_reimbursement_lines (
            payslip_id,
            xero_reimbursement_type_id,
            reimbursement_type,
            description,
            amount,
            line_order
        ) VALUES (?, ?, ?, ?, ?, ?)
    ");

    foreach ($lines as $index => $line) {
        $stmt->execute([
            $payslipId,
            $line['reimbursement_type_id'] ?? null,
            $line['reimbursement_type'],
            $line['description'],
            $line['amount'],
            $index + 1
        ]);
    }
}

/**
 * Create snapshot JSON
 */
function createSnapshotJSON(array $xeroData, array $deputyData, array $cisData): string
{
    $snapshot = [
        'version' => '1.0.0',
        'created_at' => date('Y-m-d H:i:s'),
        'xero' => $xeroData,
        'deputy' => $deputyData,
        'cis' => $cisData,
        'metadata' => [
            'source' => 'snapshot_payslip_cli',
            'php_version' => PHP_VERSION,
            'hostname' => gethostname()
        ]
    ];

    return json_encode($snapshot, JSON_PRETTY_PRINT);
}

/**
 * Extract earnings lines from Xero payslip
 */
function extractEarningsLines($payslip): array
{
    $lines = [];

    foreach ($payslip->getEarningsLines() as $line) {
        $lines[] = [
            'earnings_rate_id' => $line->getEarningsRateId(),
            'earnings_type' => $line->getRateType() ?? 'Regular',
            'description' => $line->getDisplayName() ?? 'Earnings',
            'rate_per_unit' => (float)$line->getRatePerUnit(),
            'number_of_units' => (float)$line->getNumberOfUnits(),
            'fixed_amount' => (float)$line->getFixedAmount(),
            'amount' => (float)$line->getAmount()
        ];
    }

    return $lines;
}

/**
 * Extract deduction lines
 */
function extractDeductionLines($payslip): array
{
    $lines = [];

    foreach ($payslip->getDeductionLines() as $line) {
        $lines[] = [
            'deduction_type_id' => $line->getDeductionTypeId(),
            'deduction_type' => $line->getCalculationType() ?? 'Fixed',
            'description' => $line->getDisplayName() ?? 'Deduction',
            'percentage' => (float)$line->getPercentage(),
            'fixed_amount' => (float)$line->getAmount(),
            'amount' => (float)$line->getAmount()
        ];
    }

    return $lines;
}

/**
 * Extract leave lines
 */
function extractLeaveLines($payslip): array
{
    $lines = [];

    foreach ($payslip->getLeaveLines() as $line) {
        $lines[] = [
            'leave_type_id' => $line->getLeaveTypeId(),
            'leave_type' => $line->getEntitlementType() ?? 'Annual',
            'description' => $line->getDescription() ?? 'Leave',
            'hours_taken' => (float)$line->getNumberOfUnits(),
            'hours_accrued' => 0.0, // Not in payslip response
            'balance' => 0.0 // Not in payslip response
        ];
    }

    return $lines;
}

/**
 * Extract reimbursement lines
 */
function extractReimbursementLines($payslip): array
{
    $lines = [];

    foreach ($payslip->getReimbursementLines() as $line) {
        $lines[] = [
            'reimbursement_type_id' => $line->getReimbursementTypeId(),
            'reimbursement_type' => $line->getDescription() ?? 'Expense',
            'description' => $line->getDescription() ?? 'Reimbursement',
            'amount' => (float)$line->getAmount()
        ];
    }

    return $lines;
}

/**
 * List recent pay runs
 */
function listRecentPayRuns(PDO $pdo): void
{
    global $config;

    try {
        log_message("Fetching recent pay runs from Xero...\n");

        $xeroConfig = Configuration::getDefaultConfiguration()->setAccessToken(getXeroAccessToken());
        $payrollApi = new PayrollNzApi(new GuzzleHttp\Client(), $xeroConfig);

        $response = $payrollApi->getPayRuns($config['xero_tenant_id'], 1, 10);
        $payRuns = $response->getPayRuns();

        if (empty($payRuns)) {
            log_message("No pay runs found");
            return;
        }

        echo "Recent Pay Runs:\n";
        echo str_repeat("=", 80) . "\n";
        printf("%-40s %-15s %-15s %s\n", "Pay Run ID", "Period End", "Payment Date", "Status");
        echo str_repeat("-", 80) . "\n";

        foreach ($payRuns as $payRun) {
            printf(
                "%-40s %-15s %-15s %s\n",
                $payRun->getPayRunId(),
                $payRun->getPeriodEndDate()->format('Y-m-d'),
                $payRun->getPaymentDate()->format('Y-m-d'),
                $payRun->getPayRunStatus()
            );
        }

        echo str_repeat("=", 80) . "\n";

    } catch (Exception $e) {
        log_error("Failed to fetch pay runs: " . $e->getMessage());
    }
}

/**
 * Get Xero access token
 */
function getXeroAccessToken(): string
{
    // TODO: Implement token refresh logic
    // For now, read from environment or config
    return $_ENV['XERO_ACCESS_TOKEN'] ?? $GLOBALS['xeroAccessToken'] ?? '';
}

/**
 * Show help
 */
function showHelp(): void
{
    echo <<<HELP

Payslip Snapshot CLI Tool
==========================

USAGE:
  php snapshot_payslip.php --payslip-id=<id> [--force]
  php snapshot_payslip.php --pay-run-id=<id> --all [--force]
  php snapshot_payslip.php --list-recent
  php snapshot_payslip.php --help

OPTIONS:
  --payslip-id=ID     Snapshot specific payslip by Xero payslip ID
  --pay-run-id=ID     Snapshot all payslips in pay run
  --all               Process all payslips in pay run (with --pay-run-id)
  --force             Overwrite existing snapshot
  --list-recent       List recent pay runs from Xero
  --help              Show this help message

EXAMPLES:
  # List recent pay runs
  php snapshot_payslip.php --list-recent

  # Snapshot single payslip
  php snapshot_payslip.php --payslip-id=abc-123-xyz

  # Snapshot all payslips in pay run
  php snapshot_payslip.php --pay-run-id=xyz-789-abc --all

  # Force overwrite existing snapshot
  php snapshot_payslip.php --payslip-id=abc-123-xyz --force

WHAT IT DOES:
  1. Fetches payslip data from Xero API
  2. Fetches matching Deputy timesheet data
  3. Fetches CIS staff context
  4. Inserts as immutable "final truth" snapshot
  5. Creates JSON snapshot with SHA256 hash
  6. Records as baseline for future edits/diffs

TABLES POPULATED:
  - payroll_payslips (main record)
  - payroll_earnings_lines
  - payroll_deduction_lines
  - payroll_leave_lines
  - payroll_reimbursement_lines

AFTER SNAPSHOT:
  Use the payslip ID to make edits, which will automatically:
  - Calculate diffs from original snapshot
  - Track changes in amendments
  - Preserve original data integrity

HELP;
}

/**
 * Log message
 */
function log_message(string $message): void
{
    echo $message . "\n";
}

/**
 * Log success
 */
function log_success(string $message): void
{
    echo "\033[32m" . $message . "\033[0m\n";
}

/**
 * Log error
 */
function log_error(string $message): void
{
    echo "\033[31m" . $message . "\033[0m\n";
}
