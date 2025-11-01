#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * End-to-End Payroll Integration Test
 *
 * Tests all 4 completed features:
 * 1. Rate Limit Telemetry
 * 2. Snapshot Integrity
 * 3. Reconciliation Dashboard
 * 4. Auth & PII Redaction
 *
 * Usage: php tests/test_complete_integration.php
 */

require_once __DIR__ . '/../../../base/bootstrap.php';

// Color output helpers
function green(string $msg): string { return "\033[32m{$msg}\033[0m"; }
function red(string $msg): string { return "\033[31m{$msg}\033[0m"; }
function yellow(string $msg): string { return "\033[33m{$msg}\033[0m"; }
function bold(string $msg): string { return "\033[1m{$msg}\033[0m"; }

echo "\n";
echo "╔═══════════════════════════════════════════════════════════════╗\n";
echo "║  " . bold("PAYROLL MODULE - COMPLETE INTEGRATION TEST") . "              ║\n";
echo "╚═══════════════════════════════════════════════════════════════╝\n\n";

$passed = 0;
$failed = 0;

// ============================================================================
// TEST 1: RATE LIMIT TELEMETRY
// ============================================================================
echo bold("TEST 1: Rate Limit Telemetry\n");
echo str_repeat("─", 65) . "\n";

try {
    require_once __DIR__ . '/../services/HttpRateLimitReporter.php';

    // Use centralized database config (no hardcoded credentials)
    $dbConfig = require __DIR__ . '/../../../config/database.php';
    $cisConfig = $dbConfig['cis'];

    $pdo = new PDO(
        sprintf(
            "mysql:host=%s;dbname=%s;charset=%s",
            $cisConfig['host'],
            $cisConfig['database'],
            $cisConfig['charset']
        ),
        $cisConfig['username'],
        $cisConfig['password'],
        $cisConfig['options']
    );

    $reporter = new \HumanResources\Payroll\Services\HttpRateLimitReporter($pdo);

    // Test single log
    echo "  1a. Logging single API call... ";
    $reporter->logSingle([
        'service' => 'xero',
        'endpoint' => '/api/test',
        'status_code' => 200,
        'response_time_ms' => 150
    ]);
    echo green("✅ PASS\n");
    $passed++;

    // Test batch log
    echo "  1b. Logging batch API calls... ";
    $reporter->logBatch([
        [
            'service' => 'deputy',
            'endpoint' => '/api/timesheets',
            'status_code' => 200,
            'response_time_ms' => 250
        ],
        [
            'service' => 'xero',
            'endpoint' => '/api/payruns',
            'status_code' => 429,
            'response_time_ms' => 100,
            'retry_after' => 60
        ]
    ]);
    echo green("✅ PASS\n");
    $passed++;

    // Verify data
    echo "  1c. Verifying logged data... ";
    $stmt = $pdo->query("SELECT COUNT(*) FROM payroll_rate_limits WHERE logged_at >= NOW() - INTERVAL 1 MINUTE");
    $count = $stmt->fetchColumn();
    if ($count >= 3) {
        echo green("✅ PASS") . " ($count records)\n";
        $passed++;
    } else {
        echo red("❌ FAIL") . " (expected >= 3, got $count)\n";
        $failed++;
    }

} catch (\Exception $e) {
    echo red("❌ FAIL") . " - " . $e->getMessage() . "\n";
    $failed += 3;
}

echo "\n";

// ============================================================================
// TEST 2: SNAPSHOT INTEGRITY
// ============================================================================
echo bold("TEST 2: Snapshot Integrity\n");
echo str_repeat("─", 65) . "\n";

try {
    require_once __DIR__ . '/../lib/PayrollSnapshotManager.php';

    $snapshotManager = new \PayrollSnapshotManager($pdo, 'test-tenant', 1);

    // Create test snapshot
    echo "  2a. Creating test snapshot... ";
    $snapshotId = $snapshotManager->captureSnapshot(
        1, // run_id
        null, // revision_id
        [['id' => 1, 'name' => 'Test User']], // userObjects
        null, null, null, null, null, null, null,
        'test'
    );
    echo green("✅ PASS") . " (ID: $snapshotId)\n";
    $passed++;

    // Verify integrity
    echo "  2b. Verifying snapshot integrity... ";
    $result = $snapshotManager->verifySnapshotIntegrity($snapshotId);
    if ($result['valid']) {
        echo green("✅ PASS\n");
        $passed++;
    } else {
        echo red("❌ FAIL") . " - Hash mismatch\n";
        $failed++;
    }

    // Test tampering detection
    echo "  2c. Testing tampering detection... ";
    $pdo->prepare("UPDATE payroll_snapshots SET user_objects_json = ? WHERE id = ?")
        ->execute(['[{"tampered": true}]', $snapshotId]);

    $result = $snapshotManager->verifySnapshotIntegrity($snapshotId);
    if (!$result['valid']) {
        echo green("✅ PASS") . " (tampering detected)\n";
        $passed++;
    } else {
        echo red("❌ FAIL") . " - Should detect tampering\n";
        $failed++;
    }

} catch (\Exception $e) {
    echo red("❌ FAIL") . " - " . $e->getMessage() . "\n";
    $failed += 3;
}

echo "\n";

// ============================================================================
// TEST 3: RECONCILIATION SERVICE
// ============================================================================
echo bold("TEST 3: Reconciliation Service\n");
echo str_repeat("─", 65) . "\n";

try {
    require_once __DIR__ . '/../services/ReconciliationService.php';

    $reconService = new \HumanResources\Payroll\Services\ReconciliationService($pdo);

    // Test dashboard data
    echo "  3a. Getting dashboard data... ";
    $dashboardData = $reconService->getDashboardData();
    if (isset($dashboardData['total_employees'])) {
        echo green("✅ PASS\n");
        $passed++;
    } else {
        echo red("❌ FAIL") . " - Missing required fields\n";
        $failed++;
    }

    // Test variance detection
    echo "  3b. Detecting variances... ";
    $variances = $reconService->getVariances('current', 0.01);
    echo green("✅ PASS") . " (" . count($variances) . " variances)\n";
    $passed++;

} catch (\Exception $e) {
    echo red("❌ FAIL") . " - " . $e->getMessage() . "\n";
    $failed += 2;
}

echo "\n";

// ============================================================================
// TEST 4: AUTH & PII REDACTION
// ============================================================================
echo bold("TEST 4: Auth & PII Redaction\n");
echo str_repeat("─", 65) . "\n";

try {
    require_once __DIR__ . '/../middleware/PayrollAuthMiddleware.php';
    require_once __DIR__ . '/../lib/PiiRedactor.php';

    // Test auth middleware
    echo "  4a. Testing auth middleware... ";
    $adminUser = ['id' => 1, 'role' => 'payroll_admin'];
    $staffUser = ['id' => 2, 'role' => 'staff'];

    $adminAuth = new \HumanResources\Payroll\Middleware\PayrollAuthMiddleware($adminUser);
    $staffAuth = new \HumanResources\Payroll\Middleware\PayrollAuthMiddleware($staffUser);

    if ($adminAuth->can('view_all') && !$staffAuth->can('view_all')) {
        echo green("✅ PASS\n");
        $passed++;
    } else {
        echo red("❌ FAIL") . " - Permission logic incorrect\n";
        $failed++;
    }

    // Test PII redaction
    echo "  4b. Testing PII redaction... ";
    $redactor = new \HumanResources\Payroll\Lib\PiiRedactor();

    $testData = [
        'name' => 'John Doe',
        'email' => 'john.doe@example.com',
        'bank_account' => '12-3456-7890123-00',
        'hourly_rate' => 25.50
    ];

    $redacted = $redactor->redact($testData, 'full');

    if ($redacted['email'] !== $testData['email'] &&
        $redacted['bank_account'] !== $testData['bank_account']) {
        echo green("✅ PASS\n");
        $passed++;
    } else {
        echo red("❌ FAIL") . " - PII not redacted\n";
        $failed++;
    }

    // Test log message redaction
    echo "  4c. Testing log redaction... ";
    $logMsg = "User john.doe@example.com accessed account 12-3456-7890123-00";
    $redactedMsg = $redactor->redactLogMessage($logMsg);

    if (strpos($redactedMsg, 'john.doe@example.com') === false) {
        echo green("✅ PASS\n");
        $passed++;
    } else {
        echo red("❌ FAIL") . " - Email not redacted from log\n";
        $failed++;
    }

} catch (\Exception $e) {
    echo red("❌ FAIL") . " - " . $e->getMessage() . "\n";
    $failed += 3;
}

echo "\n";

// ============================================================================
// SUMMARY
// ============================================================================
echo str_repeat("═", 65) . "\n";
echo bold("TEST SUMMARY\n");
echo str_repeat("═", 65) . "\n";
echo "Total Tests: " . ($passed + $failed) . "\n";
echo green("Passed: $passed\n");
echo red("Failed: $failed\n");
echo "\n";

if ($failed === 0) {
    echo green("╔═══════════════════════════════════════════════════════════════╗\n");
    echo green("║  ✅ ALL TESTS PASSED! PAYROLL MODULE 100% COMPLETE!           ║\n");
    echo green("╚═══════════════════════════════════════════════════════════════╝\n");
    exit(0);
} else {
    echo yellow("╔═══════════════════════════════════════════════════════════════╗\n");
    echo yellow("║  ⚠️  SOME TESTS FAILED - REVIEW ERRORS ABOVE                  ║\n");
    echo yellow("╚═══════════════════════════════════════════════════════════════╝\n");
    exit(1);
}
