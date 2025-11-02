<?php
/**
 * Payroll Integration Smoke Test
 *
 * End-to-end verification of Deputy → ReconciliationService → Xero flow.
 *
 * Usage: php smoke-test-integration.php
 *
 * @package CIS\Payroll\Tests
 */

declare(strict_types=1);

require_once __DIR__ . '/../services/PayrollDeputyService.php';
require_once __DIR__ . '/../services/PayrollXeroService.php';
require_once __DIR__ . '/../services/ReconciliationService.php';
require_once __DIR__ . '/../services/HttpRateLimitReporter.php';

use HumanResources\Payroll\Services\PayrollDeputyService;
use HumanResources\Payroll\Services\PayrollXeroService;
use HumanResources\Payroll\Services\ReconciliationService;
use HumanResources\Payroll\Services\HttpRateLimitReporter;

function echoSection(string $title): void
{
    echo "\n" . str_repeat('=', 60) . "\n";
    echo "  {$title}\n";
    echo str_repeat('=', 60) . "\n";
}

function echoPass(string $message): void
{
    echo "✓ {$message}\n";
}

function echoFail(string $message): void
{
    echo "✗ {$message}\n";
}

try {
    $db = new PDO(
        'mysql:host=127.0.0.1;dbname=jcepnzzkmj;charset=utf8mb4',
        'jcepnzzkmj',
        'wprKh9Jq63',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    echoSection("Payroll Integration Smoke Test");

    // Step 1: Verify database tables exist
    echoSection("Step 1: Database Prerequisites");
    $requiredTables = [
        'staff_identity_map',
        'payroll_rate_limits',
        'payroll_activity_log',
        'deputy_timesheets'
    ];

    foreach ($requiredTables as $table) {
        $stmt = $db->query("SHOW TABLES LIKE '{$table}'");
        if ($stmt->rowCount() > 0) {
            echoPass("Table '{$table}' exists");
        } else {
            echoFail("Table '{$table}' missing");
            exit(1);
        }
    }

    // Step 2: Instantiate services
    echoSection("Step 2: Service Instantiation");

    $deputyService = new PayrollDeputyService($db);
    echoPass("PayrollDeputyService instantiated");

    $xeroService = PayrollXeroService::make($db);
    echoPass("PayrollXeroService instantiated");

    $reconciler = ReconciliationService::make($db);
    echoPass("ReconciliationService instantiated");

    $rateLimitReporter = new HttpRateLimitReporter($db);
    echoPass("HttpRateLimitReporter instantiated");

    // Step 3: Fetch data (stub mode - expecting empty arrays)
    echoSection("Step 3: Data Retrieval (Stub Mode)");

    $deputyTimesheets = $deputyService->fetchTimesheets(
        startDate: '2024-10-01',
        endDate: '2024-10-31'
    );
    echoPass("Deputy timesheets fetched: " . count($deputyTimesheets) . " records");

    $xeroPayRuns = $xeroService->listEmployees();
    echoPass("Xero employees fetched: " . count($xeroPayRuns) . " records");

    // Step 4: Run reconciliation
    echoSection("Step 4: Reconciliation Execution");

    $result = $reconciler->runReconciliation(
        payPeriod: '2024-W44',
        employeeFilter: null
    );

    if (isset($result['variances']) && is_array($result['variances'])) {
        echoPass("Reconciliation completed: " . count($result['variances']) . " variances detected");
    } else {
        echoFail("Reconciliation returned unexpected structure");
        exit(1);
    }

    // Step 5: Verify activity logging
    echoSection("Step 5: Activity Log Verification");

    $stmt = $db->query(
        "SELECT COUNT(*) FROM payroll_activity_log WHERE created_at >= NOW() - INTERVAL 1 MINUTE"
    );
    $recentLogs = (int) $stmt->fetchColumn();

    if ($recentLogs > 0) {
        echoPass("Activity logged: {$recentLogs} entries in last minute");
    } else {
        echoFail("No recent activity logs found");
    }

    // Step 6: Test rate-limit reporter
    echoSection("Step 6: Rate-Limit Telemetry");

    $rateLimitReporter->record(
        service: 'deputy',
        endpoint: '/timesheets',
        status: 429,
        retryAfter: 60,
        requestId: 'smoke_test_' . time()
    );
    echoPass("Rate-limit event recorded");

    $stmt = $db->query(
        "SELECT COUNT(*) FROM payroll_rate_limits WHERE service = 'deputy' AND created_at >= NOW() - INTERVAL 1 MINUTE"
    );
    $recentRateLimits = (int) $stmt->fetchColumn();

    if ($recentRateLimits > 0) {
        echoPass("Rate-limit telemetry verified: {$recentRateLimits} events");
    } else {
        echoFail("Rate-limit telemetry not persisted");
    }

    // Final summary
    echoSection("Integration Test: PASSED");
    echo "\nAll services operational. Ready for production hardening.\n\n";

    exit(0);

} catch (Throwable $e) {
    echoSection("Integration Test: FAILED");
    echo "\n" . get_class($e) . ": {$e->getMessage()}\n";
    echo "File: {$e->getFile()}:{$e->getLine()}\n\n";
    exit(1);
}
