#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * Phase 1 Test Suite Runner
 *
 * Runs comprehensive tests for the email notification system:
 * - Database connectivity
 * - EmailService functionality
 * - NotificationService queue processing
 * - Template rendering
 * - Worker operation
 *
 * Usage:
 *   php bin/test-phase1.php [--verbose] [--unit-only] [--integration-only]
 *
 * @package CIS\Consignments
 * @version 1.0.0
 */

// Ensure running from CLI
if (php_sapi_name() !== 'cli') {
    die("This script can only be run from the command line.\n");
}

// Parse options
$options = getopt('', ['verbose', 'unit-only', 'integration-only', 'help']);

if (isset($options['help'])) {
    showHelp();
    exit(0);
}

$verbose = isset($options['verbose']);
$unitOnly = isset($options['unit-only']);
$integrationOnly = isset($options['integration-only']);

echo "\n";
echo "═══════════════════════════════════════════════════════════════════\n";
echo "  PHASE 1: EMAIL NOTIFICATION SYSTEM - TEST SUITE\n";
echo "═══════════════════════════════════════════════════════════════════\n";
echo "\n";

// Bootstrap
require_once __DIR__ . '/../bootstrap.php';

use CIS\Consignments\Services\EmailService;
use CIS\Consignments\Services\NotificationService;

$testResults = [];
$startTime = microtime(true);

try {
    // ========================================================================
    // 1. ENVIRONMENT CHECKS
    // ========================================================================

    if (!$integrationOnly) {
        echo "🔍 Running Environment Checks...\n";
        echo str_repeat("─", 67) . "\n";

        $testResults['env'] = runEnvironmentChecks($verbose);

        echo "\n";
    }

    // ========================================================================
    // 2. DATABASE VERIFICATION
    // ========================================================================

    if (!$integrationOnly) {
        echo "🗄️  Running Database Verification...\n";
        echo str_repeat("─", 67) . "\n";

        $testResults['db'] = runDatabaseVerification($verbose);

        echo "\n";
    }

    // ========================================================================
    // 3. UNIT TESTS (via PHPUnit)
    // ========================================================================

    if (!$integrationOnly) {
        echo "🧪 Running Unit Tests (PHPUnit)...\n";
        echo str_repeat("─", 67) . "\n";

        $testResults['unit'] = runUnitTests($verbose);

        echo "\n";
    }

    // ========================================================================
    // 4. INTEGRATION TESTS
    // ========================================================================

    if (!$unitOnly) {
        echo "🔗 Running Integration Tests...\n";
        echo str_repeat("─", 67) . "\n";

        $testResults['integration'] = runIntegrationTests($verbose);

        echo "\n";
    }

    // ========================================================================
    // 5. WORKER TESTS
    // ========================================================================

    if (!$unitOnly) {
        echo "⚙️  Running Worker Tests...\n";
        echo str_repeat("─", 67) . "\n";

        $testResults['worker'] = runWorkerTests($verbose);

        echo "\n";
    }

    // ========================================================================
    // SUMMARY
    // ========================================================================

    $duration = round(microtime(true) - $startTime, 2);

    echo "═══════════════════════════════════════════════════════════════════\n";
    echo "  TEST SUMMARY\n";
    echo "═══════════════════════════════════════════════════════════════════\n";
    echo "\n";

    $totalTests = 0;
    $totalPassed = 0;
    $totalFailed = 0;

    foreach ($testResults as $category => $results) {
        $passed = count(array_filter($results, fn($r) => $r['success']));
        $failed = count(array_filter($results, fn($r) => !$r['success']));
        $total = count($results);

        $totalTests += $total;
        $totalPassed += $passed;
        $totalFailed += $failed;

        $icon = $failed === 0 ? '✅' : '⚠️';
        echo sprintf("%s %-20s: %2d/%2d passed\n", $icon, strtoupper($category), $passed, $total);
    }

    echo str_repeat("─", 67) . "\n";
    echo sprintf("   %-20s: %2d/%2d passed\n", 'TOTAL', $totalPassed, $totalTests);
    echo sprintf("   %-20s: %.2fs\n", 'Duration', $duration);
    echo "\n";

    if ($totalFailed === 0) {
        echo "🎉 ALL TESTS PASSED!\n";
        echo "\n";
        echo "Phase 1 (Email Notification System) is fully operational.\n";
        echo "\n";
        echo "Next steps:\n";
        echo "  1. Setup cron jobs: php bin/setup-cron.php\n";
        echo "  2. Monitor queue: php bin/notification-worker.php --stats\n";
        echo "  3. Begin Phase 2: Approval Workflow Integration\n";
        echo "\n";
        exit(0);
    } else {
        echo "⚠️  {$totalFailed} TEST(S) FAILED\n";
        echo "\n";
        echo "Please review the failures above before proceeding.\n";
        echo "\n";
        exit(1);
    }

} catch (Exception $e) {
    echo "\n";
    echo "❌ FATAL ERROR: " . $e->getMessage() . "\n";
    echo "\n";
    exit(1);
}

// ============================================================================
// TEST FUNCTIONS
// ============================================================================

function runEnvironmentChecks(bool $verbose): array
{
    $results = [];

    // PHP version
    $phpVersion = PHP_VERSION;
    $results[] = testCheck(
        'PHP Version >= 8.0',
        version_compare($phpVersion, '8.0.0', '>='),
        "Current: {$phpVersion}",
        $verbose
    );

    // PDO extension
    $results[] = testCheck(
        'PDO Extension',
        extension_loaded('pdo'),
        extension_loaded('pdo') ? 'Installed' : 'NOT FOUND',
        $verbose
    );

    // PDO MySQL driver
    $results[] = testCheck(
        'PDO MySQL Driver',
        extension_loaded('pdo_mysql'),
        extension_loaded('pdo_mysql') ? 'Installed' : 'NOT FOUND',
        $verbose
    );

    // Composer autoload
    $autoloadPath = __DIR__ . '/../vendor/autoload.php';
    $results[] = testCheck(
        'Composer Autoload',
        file_exists($autoloadPath),
        file_exists($autoloadPath) ? 'Found' : 'NOT FOUND',
        $verbose
    );

    return $results;
}

function runDatabaseVerification(bool $verbose): array
{
    $results = [];

    try {
        $pdo = db();

        // Connection
        $results[] = testCheck(
            'Database Connection',
            true,
            'Connected',
            $verbose
        );

        // Tables
        $tables = [
            'consignment_notification_queue',
            'consignment_email_templates',
            'consignment_email_template_config',
            'consignment_email_log'
        ];

        foreach ($tables as $table) {
            $stmt = $pdo->query("SHOW TABLES LIKE '{$table}'");
            $exists = ($stmt && $stmt->fetch());

            $results[] = testCheck(
                "Table: {$table}",
                $exists,
                $exists ? 'Exists' : 'NOT FOUND',
                $verbose
            );
        }

        // Template count
        $stmt = $pdo->query("SELECT COUNT(*) FROM consignment_email_templates");
        $count = (int)$stmt->fetchColumn();

        $results[] = testCheck(
            'Email Templates',
            $count >= 9,
            "{$count} templates",
            $verbose
        );

        // Config count
        $stmt = $pdo->query("SELECT COUNT(*) FROM consignment_email_template_config");
        $count = (int)$stmt->fetchColumn();

        $results[] = testCheck(
            'Config Entries',
            $count >= 8,
            "{$count} entries",
            $verbose
        );

    } catch (Exception $e) {
        $results[] = testCheck(
            'Database Connection',
            false,
            $e->getMessage(),
            $verbose
        );
    }

    return $results;
}

function runUnitTests(bool $verbose): array
{
    $results = [];

    // Check if PHPUnit is available
    $phpunitPath = __DIR__ . '/../vendor/bin/phpunit';

    if (!file_exists($phpunitPath)) {
        $results[] = testCheck(
            'PHPUnit',
            false,
            'Not installed (run: composer install)',
            $verbose
        );
        return $results;
    }

    // Run PHPUnit tests
    $testDir = __DIR__ . '/../tests/Unit';

    if (!is_dir($testDir)) {
        $results[] = testCheck(
            'Test Directory',
            false,
            'tests/Unit directory not found',
            $verbose
        );
        return $results;
    }

    $output = [];
    $returnCode = 0;

    exec("cd " . __DIR__ . '/.. && vendor/bin/phpunit tests/Unit 2>&1', $output, $returnCode);

    $results[] = testCheck(
        'PHPUnit Test Suite',
        $returnCode === 0,
        $returnCode === 0 ? 'All tests passed' : 'Some tests failed',
        $verbose
    );

    if ($verbose && $returnCode !== 0) {
        echo "\nPHPUnit Output:\n";
        echo implode("\n", $output) . "\n\n";
    }

    return $results;
}

function runIntegrationTests(bool $verbose): array
{
    $results = [];

    try {
        $pdo = db();
        $emailService = new EmailService($pdo);

        // Test 1: Queue a test email
        $testEmail = 'integration-test-' . time() . '@test.com';

        try {
            $queueId = $emailService->sendTemplate(
                'po_created_internal',
                $testEmail,
                'Integration Test User',
                [
                    'po_number' => 'TEST-' . time(),
                    'supplier_name' => 'Test Supplier',
                    'total_value' => '$99.99',
                    'created_by' => 'Test Runner',
                    'created_at' => date('Y-m-d H:i:s'),
                    'po_url' => 'https://test.com/po/123'
                ],
                1, // consignment_id
                EmailService::PRIORITY_NORMAL,
                999 // test user ID
            );

            $results[] = testCheck(
                'EmailService::sendTemplate()',
                is_int($queueId) && $queueId > 0,
                "Queued with ID: {$queueId}",
                $verbose
            );

            // Verify queue record
            $stmt = $pdo->prepare("SELECT * FROM consignment_notification_queue WHERE id = ?");
            $stmt->execute([$queueId]);
            $record = $stmt->fetch(PDO::FETCH_ASSOC);

            $results[] = testCheck(
                'Queue Record Created',
                !empty($record),
                $record ? 'Found' : 'NOT FOUND',
                $verbose
            );

            $results[] = testCheck(
                'Queue Status = pending',
                $record['status'] === 'pending',
                "Status: {$record['status']}",
                $verbose
            );

            // Clean up test record
            $pdo->prepare("DELETE FROM consignment_notification_queue WHERE id = ?")->execute([$queueId]);

        } catch (Exception $e) {
            $results[] = testCheck(
                'EmailService::sendTemplate()',
                false,
                $e->getMessage(),
                $verbose
            );
        }

        // Test 2: NotificationService
        try {
            $notificationService = new NotificationService($pdo);

            $stats = $notificationService->getQueueStats();

            $results[] = testCheck(
                'NotificationService::getQueueStats()',
                is_array($stats),
                'Returned array',
                $verbose
            );

        } catch (Exception $e) {
            $results[] = testCheck(
                'NotificationService::getQueueStats()',
                false,
                $e->getMessage(),
                $verbose
            );
        }

    } catch (Exception $e) {
        $results[] = testCheck(
            'Integration Tests',
            false,
            $e->getMessage(),
            $verbose
        );
    }

    return $results;
}

function runWorkerTests(bool $verbose): array
{
    $results = [];

    $workerPath = __DIR__ . '/notification-worker.php';

    // Check worker exists
    $results[] = testCheck(
        'Worker Script Exists',
        file_exists($workerPath),
        file_exists($workerPath) ? 'Found' : 'NOT FOUND',
        $verbose
    );

    if (!file_exists($workerPath)) {
        return $results;
    }

    // Check executable
    $isExecutable = is_executable($workerPath);
    $results[] = testCheck(
        'Worker Script Executable',
        $isExecutable,
        $isExecutable ? 'Yes' : 'No (run: chmod +x)',
        $verbose
    );

    // Test --help
    $output = [];
    $returnCode = 0;
    exec("php {$workerPath} --help 2>&1", $output, $returnCode);

    $results[] = testCheck(
        'Worker --help',
        $returnCode === 0,
        $returnCode === 0 ? 'Works' : 'Failed',
        $verbose
    );

    // Test --stats
    $output = [];
    $returnCode = 0;
    exec("php {$workerPath} --stats 2>&1", $output, $returnCode);

    $results[] = testCheck(
        'Worker --stats',
        $returnCode === 0,
        $returnCode === 0 ? 'Works' : 'Failed',
        $verbose
    );

    if ($verbose && $returnCode === 0) {
        echo "\nWorker Stats Output:\n";
        echo implode("\n", $output) . "\n\n";
    }

    return $results;
}

function testCheck(string $name, bool $success, string $message, bool $verbose): array
{
    $icon = $success ? '✓' : '❌';

    if ($verbose || !$success) {
        echo sprintf("  %s %-40s %s\n", $icon, $name, $message);
    }

    return [
        'name' => $name,
        'success' => $success,
        'message' => $message
    ];
}

function showHelp(): void
{
    echo <<<HELP

Phase 1 Test Suite Runner
==========================

Runs comprehensive tests for the email notification system.

USAGE:
  php bin/test-phase1.php [options]

OPTIONS:
  --verbose           Show all test results (not just failures)
  --unit-only         Run only unit tests (skip integration)
  --integration-only  Run only integration tests (skip unit)
  --help              Show this help message

TEST CATEGORIES:
  1. Environment Checks    - PHP version, extensions, autoload
  2. Database Verification - Tables, templates, config
  3. Unit Tests            - PHPUnit test suite
  4. Integration Tests     - EmailService, NotificationService
  5. Worker Tests          - notification-worker.php

EXAMPLES:
  # Run all tests
  php bin/test-phase1.php

  # Run with verbose output
  php bin/test-phase1.php --verbose

  # Run only unit tests
  php bin/test-phase1.php --unit-only

  # Run only integration tests
  php bin/test-phase1.php --integration-only


HELP;
}
