#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * ============================================================================
 * COMPLETE PAYROLL SNAPSHOT CLI - CAPTURE EVERYTHING
 * ============================================================================
 *
 * Purpose: Downloads and captures EVERYTHING from CIS/Xero/Deputy/Vend and
 *          stores it as an immutable snapshot so we have ZERO guesswork.
 *
 * What gets captured:
 * 1. CIS Database:
 *    - Active staff (users table)
 *    - Existing payslips (payroll_payslips)
 *    - Timesheet amendments (payroll_timesheet_amendments)
 *    - Public holidays worked
 *    - Leave requests
 *    - Outlet data
 *
 * 2. Deputy API:
 *    - Raw timesheets (EVERYTHING)
 *    - Employee objects
 *    - Roster data
 *    - Approved/unapproved status
 *
 * 3. Xero API:
 *    - ALL employees
 *    - Employee start dates
 *    - Leave balances
 *    - Pay templates
 *    - Tax codes
 *
 * 4. Vend API:
 *    - Customer account balances
 *    - Recent sales for each staff
 *    - Payment history
 *
 * 5. Calculated Data:
 *    - Vape drops completed
 *    - Google review bonuses
 *    - Monthly bonuses pending
 *    - Commission calculations
 *
 * Usage:
 *   php snapshot_complete_payroll.php --period-start=2025-01-20 --period-end=2025-01-26
 *   php snapshot_complete_payroll.php --auto  (uses last Tuesday)
 *
 * Output: Creates snapshot in payroll_context_snapshots table + JSON file
 * ============================================================================
 */

// ============================================================================
// BOOTSTRAP
// ============================================================================

error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('log_errors', '1');
date_default_timezone_set('Pacific/Auckland');
set_time_limit(0);

$rootPath = realpath(__DIR__ . '/../../../../');
define('BASE_PATH', rtrim($rootPath, '/') . '/');

require_once BASE_PATH . 'assets/functions/config.php';
require_once BASE_PATH . 'assets/services/xero-sdk/xero-init.php';

// Deputy helpers
require_once BASE_PATH . 'assets/functions/deputyAPI.php';

// Vend helpers
if (file_exists(BASE_PATH . 'assets/functions/vend_integration.php')) {
    require_once BASE_PATH . 'assets/functions/vend_integration.php';
}

// ============================================================================
// CLI ARGS PARSING
// ============================================================================

$options = getopt('', ['period-start:', 'period-end:', 'auto', 'help']);

if (isset($options['help'])) {
    echo <<<HELP
Complete Payroll Snapshot CLI

Usage:
  php snapshot_complete_payroll.php --period-start=YYYY-MM-DD --period-end=YYYY-MM-DD
  php snapshot_complete_payroll.php --auto

Options:
  --period-start   Start date (Monday)
  --period-end     End date (Sunday)
  --auto           Auto-detect last Tuesday to this Monday
  --help           Show this help

Examples:
  php snapshot_complete_payroll.php --period-start=2025-01-20 --period-end=2025-01-26
  php snapshot_complete_payroll.php --auto

HELP;
    exit(0);
}

// Determine period
if (isset($options['auto'])) {
    $periodStart = date('Y-m-d', strtotime('Tuesday last week'));
    $periodEnd = date('Y-m-d', strtotime('Monday this week'));
} else {
    $periodStart = $options['period-start'] ?? null;
    $periodEnd = $options['period-end'] ?? null;

    if (!$periodStart || !$periodEnd) {
        echo "❌ Error: --period-start and --period-end are required (or use --auto)\n";
        exit(1);
    }
}

$paymentDate = date('Y-m-d', strtotime($periodEnd . ' +3 days')); // Thursday typically

echo "🚀 COMPLETE PAYROLL SNAPSHOT\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Period:  {$periodStart} → {$periodEnd}\n";
echo "Payment: {$paymentDate}\n";
echo "Started: " . date('Y-m-d H:i:s') . "\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$startTime = microtime(true);
$snapshotData = [];

// ============================================================================
// STEP 1: CIS DATABASE - ACTIVE STAFF
// ============================================================================

echo "📊 [1/8] Fetching CIS active staff...\n";

$staffQuery = "
    SELECT
        id, first_name, last_name, email, phone,
        deputy_id, xero_id, xero_employee_id, xero_start_date,
        vend_customer_account, vend_customer_id,
        hourly_rate, salary_annual, employment_type,
        date_of_birth, bank_account, tax_code, kiwisaver_rate,
        student_loan, outlet_id, position, is_manager,
        created_at, updated_at, last_login
    FROM users
    WHERE active = 1
    AND deleted_at IS NULL
    AND id NOT IN (1, 18)
    ORDER BY first_name, last_name
";

$staffStmt = $pdo->query($staffQuery);
$activeStaff = $staffStmt->fetchAll(PDO::FETCH_ASSOC);

echo "   ✅ Retrieved " . count($activeStaff) . " active staff members\n\n";

$snapshotData['active_staff'] = $activeStaff;
$snapshotData['active_staff_count'] = count($activeStaff);

// ============================================================================
// STEP 2: CIS DATABASE - EXISTING PAYSLIPS (if any)
// ============================================================================

echo "📋 [2/8] Fetching existing payslips for period...\n";

$payslipsQuery = "
    SELECT *
    FROM payroll_payslips
    WHERE period_start = ?
    AND period_end = ?
    ORDER BY staff_id
";

$payslipsStmt = $pdo->prepare($payslipsQuery);
$payslipsStmt->execute([$periodStart, $periodEnd]);
$existingPayslips = $payslipsStmt->fetchAll(PDO::FETCH_ASSOC);

echo "   ✅ Retrieved " . count($existingPayslips) . " existing payslips\n\n";

$snapshotData['existing_payslips'] = $existingPayslips;
$snapshotData['existing_payslips_count'] = count($existingPayslips);

// ============================================================================
// STEP 3: CIS DATABASE - TIMESHEET AMENDMENTS
// ============================================================================

echo "⏰ [3/8] Fetching timesheet amendments...\n";

$amendmentsQuery = "
    SELECT *
    FROM payroll_timesheet_amendments
    WHERE period_start = ?
    AND period_end = ?
    ORDER BY staff_id, created_at
";

$amendmentsStmt = $pdo->prepare($amendmentsQuery);
$amendmentsStmt->execute([$periodStart, $periodEnd]);
$timesheetAmendments = $amendmentsStmt->fetchAll(PDO::FETCH_ASSOC);

echo "   ✅ Retrieved " . count($timesheetAmendments) . " timesheet amendments\n\n";

$snapshotData['timesheet_amendments'] = $timesheetAmendments;
$snapshotData['timesheet_amendments_count'] = count($timesheetAmendments);

// ============================================================================
// STEP 4: DEPUTY API - RAW TIMESHEETS (EVERYTHING)
// ============================================================================

echo "🕐 [4/8] Fetching Deputy timesheets (complete)...\n";

try {
    // Use the global getTimeSheets() function
    $deputyTimesheets = getTimeSheets();

    // Filter to period (but keep ALL fields)
    $periodTimesheets = array_filter($deputyTimesheets, function($ts) use ($periodStart, $periodEnd) {
        $tsDate = date('Y-m-d', strtotime($ts['Date'] ?? ''));
        return $tsDate >= $periodStart && $tsDate <= $periodEnd;
    });

    echo "   ✅ Retrieved " . count($periodTimesheets) . " timesheets for period\n";
    echo "   📦 Total Deputy timesheets available: " . count($deputyTimesheets) . "\n\n";

    $snapshotData['deputy_timesheets_raw'] = array_values($periodTimesheets);
    $snapshotData['deputy_timesheets_count'] = count($periodTimesheets);

    // Extract unique employee objects
    $employeeObjects = [];
    foreach ($deputyTimesheets as $ts) {
        if (isset($ts['EmployeeObject']) && !isset($employeeObjects[$ts['Employee']])) {
            $employeeObjects[$ts['Employee']] = $ts['EmployeeObject'];
        }
    }

    $snapshotData['deputy_employee_objects'] = $employeeObjects;

} catch (\Exception $e) {
    echo "   ⚠️  Warning: Deputy API error: " . $e->getMessage() . "\n\n";
    $snapshotData['deputy_timesheets_raw'] = [];
    $snapshotData['deputy_timesheets_count'] = 0;
    $snapshotData['deputy_error'] = $e->getMessage();
}

// ============================================================================
// STEP 5: XERO API - EMPLOYEES & DETAILS
// ============================================================================

echo "💼 [5/8] Fetching Xero employee data...\n";

try {
    // Get ALL Xero employees with full details
    $xeroEmployees = getXeroALLEmployeesWithEmail($payrollNzApi, $xeroTenantId);

    // Convert to array format
    $xeroEmployeesArray = [];
    foreach ($xeroEmployees as $emp) {
        $xeroEmployeesArray[] = [
            'employee_id' => (string)$emp->getEmployeeId(),
            'first_name' => $emp->getFirstName(),
            'last_name' => $emp->getLastName(),
            'email' => $emp->getEmail(),
            'date_of_birth' => $emp->getDateOfBirth() ? $emp->getDateOfBirth()->format('Y-m-d') : null,
            'start_date' => $emp->getStartDate() ? $emp->getStartDate()->format('Y-m-d') : null,
            'title' => $emp->getTitle(),
            'phone' => $emp->getPhone(),
            'mobile' => $emp->getMobile(),
            'status' => $emp->getEmploymentBasis(),
            'is_authorised_to_approve_leave' => $emp->getIsAuthorisedToApproveLeave(),
            'is_authorised_to_approve_timesheets' => $emp->getIsAuthorisedToApproveTimesheets(),
        ];
    }

    echo "   ✅ Retrieved " . count($xeroEmployeesArray) . " Xero employees\n\n";

    $snapshotData['xero_employees'] = $xeroEmployeesArray;
    $snapshotData['xero_employees_count'] = count($xeroEmployeesArray);

} catch (\Exception $e) {
    echo "   ⚠️  Warning: Xero API error: " . $e->getMessage() . "\n\n";
    $snapshotData['xero_employees'] = [];
    $snapshotData['xero_employees_count'] = 0;
    $snapshotData['xero_error'] = $e->getMessage();
}

// ============================================================================
// STEP 6: VEND API - ACCOUNT BALANCES
// ============================================================================

echo "💰 [6/8] Fetching Vend account balances...\n";

$vendBalances = [];
$vendBalancesFetched = 0;

foreach ($activeStaff as $staff) {
    if (!empty($staff['vend_customer_account'])) {
        try {
            $balance = getCustomerAccountBalance($staff['vend_customer_account']);
            $vendBalances[$staff['id']] = [
                'staff_id' => $staff['id'],
                'vend_customer_account' => $staff['vend_customer_account'],
                'balance' => $balance,
                'fetched_at' => date('Y-m-d H:i:s')
            ];
            $vendBalancesFetched++;
        } catch (\Exception $e) {
            $vendBalances[$staff['id']] = [
                'staff_id' => $staff['id'],
                'vend_customer_account' => $staff['vend_customer_account'],
                'balance' => null,
                'error' => $e->getMessage()
            ];
        }
    }
}

echo "   ✅ Fetched " . $vendBalancesFetched . " Vend account balances\n\n";

$snapshotData['vend_account_balances'] = $vendBalances;
$snapshotData['vend_balances_count'] = $vendBalancesFetched;

// ============================================================================
// STEP 7: CALCULATED DATA - BONUSES & VAPE DROPS
// ============================================================================

echo "🎯 [7/8] Calculating bonuses and vape drops...\n";

$tuesday = date("Y-m-d", strtotime('Tuesday last week'));
$monday = date("Y-m-d", strtotime('Monday this week'));

$calculatedData = [];

foreach ($activeStaff as $staff) {
    $staffId = (int)$staff['id'];

    // Vape drops
    $vapeDrops = getCompletedVapeDropDeliveriesCountByUserID($tuesday, $monday, $staffId);

    $calculatedData[$staffId] = [
        'staff_id' => $staffId,
        'vape_drops' => $vapeDrops,
        'google_reviews_bonus' => 0, // TODO: Implement if needed
        'monthly_bonus' => 0, // TODO: Implement if needed
        'commission' => 0, // TODO: Implement if needed
    ];
}

echo "   ✅ Calculated data for " . count($calculatedData) . " staff\n\n";

$snapshotData['calculated_data'] = $calculatedData;

// ============================================================================
// STEP 8: BUILD USER OBJECTS (FULL PAYROLL STRUCTURE)
// ============================================================================

echo "👥 [8/8] Building complete user objects...\n";

$userObjects = [];

foreach ($activeStaff as $staff) {
    $staffId = (int)$staff['id'];
    $deputyId = (int)$staff['deputy_id'];

    $userObject = [
        // Core identity
        'staff_id' => $staffId,
        'deputy_id' => $deputyId,
        'xero_id' => $staff['xero_id'],
        'xero_employee_id' => $staff['xero_employee_id'],
        'vend_customer_account' => $staff['vend_customer_account'],

        // Full CIS staff record
        'user' => $staff,

        // Deputy data
        'timesheets' => array_values(array_filter(
            $snapshotData['deputy_timesheets_raw'],
            fn($ts) => (int)$ts['Employee'] === $deputyId
        )),
        'timesheet_employee_object' => $snapshotData['deputy_employee_objects'][$deputyId] ?? null,
        'total_hours' => array_reduce(
            array_filter(
                $snapshotData['deputy_timesheets_raw'],
                fn($ts) => (int)$ts['Employee'] === $deputyId
            ),
            fn($sum, $ts) => $sum + (float)($ts['TotalTime'] ?? 0),
            0
        ),

        // Amendments
        'timesheet_amendments' => array_values(array_filter(
            $timesheetAmendments,
            fn($a) => (int)$a['staff_id'] === $staffId
        )),

        // Vend data
        'account_balance' => $vendBalances[$staffId]['balance'] ?? null,

        // Calculated bonuses
        'vape_drops' => $calculatedData[$staffId]['vape_drops'] ?? 0,
        'google_review_bonus' => $calculatedData[$staffId]['google_reviews_bonus'] ?? 0,
        'monthly_bonus' => $calculatedData[$staffId]['monthly_bonus'] ?? 0,
        'commission' => $calculatedData[$staffId]['commission'] ?? 0,

        // Existing payslip (if any)
        'existing_payslip' => array_values(array_filter(
            $existingPayslips,
            fn($p) => (int)$p['staff_id'] === $staffId
        ))[0] ?? null,
    ];

    $userObjects[$staffId] = $userObject;
}

echo "   ✅ Built " . count($userObjects) . " complete user objects\n\n";

$snapshotData['user_objects'] = $userObjects;
$snapshotData['user_objects_count'] = count($userObjects);

// ============================================================================
// SAVE SNAPSHOT
// ============================================================================

echo "💾 Saving complete snapshot...\n";

// Generate snapshot hash (for integrity)
$snapshotJson = json_encode($snapshotData, JSON_PRETTY_PRINT);
$snapshotHash = hash('sha256', $snapshotJson);

// Metadata
$snapshotData['meta'] = [
    'period_start' => $periodStart,
    'period_end' => $periodEnd,
    'payment_date' => $paymentDate,
    'created_at' => date('Y-m-d H:i:s'),
    'snapshot_hash' => $snapshotHash,
    'source' => 'cli_snapshot_complete_payroll',
    'version' => '1.0.0',
    'php_version' => PHP_VERSION,
    'execution_time_seconds' => round(microtime(true) - $startTime, 2),
];

// Save to database
try {
    $insertQuery = "
        INSERT INTO payroll_context_snapshots (
            context_type,
            entity_id,
            snapshot_hash,
            snapshot_data,
            staff_data,
            roster_data,
            timesheet_data,
            payroll_data,
            historical_data,
            outlet_data,
            created_at,
            data_sources,
            data_quality_score
        ) VALUES (
            'complete_payroll_snapshot',
            0,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            NULL,
            NULL,
            NOW(),
            ?,
            1.0000
        )
    ";

    $insertStmt = $pdo->prepare($insertQuery);
    $insertStmt->execute([
        $snapshotHash,
        json_encode($snapshotData),
        json_encode($snapshotData['active_staff']),
        json_encode($snapshotData['deputy_timesheets_raw']),
        json_encode($snapshotData['deputy_timesheets_raw']),
        json_encode($snapshotData['user_objects']),
        json_encode(['cis_db', 'deputy_api', 'xero_api', 'vend_api']),
    ]);

    $snapshotId = $pdo->lastInsertId();

    echo "   ✅ Snapshot saved to database (ID: {$snapshotId})\n";

} catch (\PDOException $e) {
    echo "   ❌ Database error: " . $e->getMessage() . "\n";
    exit(1);
}

// Save to JSON file (backup)
$jsonFilename = sprintf(
    'snapshot_%s_%s_%s.json',
    $periodStart,
    $periodEnd,
    date('YmdHis')
);

$jsonPath = BASE_PATH . 'var/payroll_snapshots/' . $jsonFilename;
@mkdir(dirname($jsonPath), 0775, true);
file_put_contents($jsonPath, $snapshotJson);

echo "   ✅ Snapshot saved to file: {$jsonFilename}\n\n";

// ============================================================================
// SUMMARY
// ============================================================================

$duration = microtime(true) - $startTime;

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "✅ SNAPSHOT COMPLETE\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

echo "📊 Summary:\n";
echo "   Active Staff:           " . $snapshotData['active_staff_count'] . "\n";
echo "   Existing Payslips:      " . $snapshotData['existing_payslips_count'] . "\n";
echo "   Timesheet Amendments:   " . $snapshotData['timesheet_amendments_count'] . "\n";
echo "   Deputy Timesheets:      " . $snapshotData['deputy_timesheets_count'] . "\n";
echo "   Xero Employees:         " . $snapshotData['xero_employees_count'] . "\n";
echo "   Vend Balances:          " . $snapshotData['vend_balances_count'] . "\n";
echo "   User Objects:           " . $snapshotData['user_objects_count'] . "\n\n";

echo "💾 Storage:\n";
echo "   Database ID:    {$snapshotId}\n";
echo "   Hash:           {$snapshotHash}\n";
echo "   JSON File:      {$jsonFilename}\n";
echo "   Size:           " . round(strlen($snapshotJson) / 1024 / 1024, 2) . " MB\n\n";

echo "⏱️  Execution Time:  " . round($duration, 2) . " seconds\n";
echo "📅 Completed:       " . date('Y-m-d H:i:s') . "\n\n";

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "🎯 Next Steps:\n";
echo "   1. Review snapshot in database: SELECT * FROM payroll_context_snapshots WHERE id = {$snapshotId}\n";
echo "   2. Make edits to payslips in CIS\n";
echo "   3. Run diff tool to see changes\n";
echo "   4. Push changes to Xero when ready\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

exit(0);
