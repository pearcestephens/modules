#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * Verify Payroll Snapshot Integrity
 *
 * CLI tool to verify SHA256 hashes of payroll snapshots
 *
 * Usage:
 *   php verify_snapshots.php --snapshot-id=123
 *   php verify_snapshots.php --run-id=45
 *   php verify_snapshots.php --all
 *   php verify_snapshots.php --recent=10
 *
 * @package CIS\HumanResources\Payroll\CLI
 */

require_once __DIR__ . '/../../../base/bootstrap.php';
require_once __DIR__ . '/../lib/PayrollSnapshotManager.php';

use CIS\Base\Database;

// Parse CLI arguments
$options = getopt('', ['snapshot-id:', 'run-id:', 'all', 'recent:', 'help']);

if (isset($options['help'])) {
    showHelp();
    exit(0);
}

try {
    // Get database connection
    $pdo = Database::pdo();
    $tenantId = getenv('XERO_TENANT_ID') ?: 'default';
    $manager = new PayrollSnapshotManager($pdo, $tenantId);

    echo "╔═══════════════════════════════════════════════════════════════╗\n";
    echo "║  PAYROLL SNAPSHOT INTEGRITY VERIFICATION                      ║\n";
    echo "╚═══════════════════════════════════════════════════════════════╝\n\n";

    // Verify single snapshot
    if (isset($options['snapshot-id'])) {
        $snapshotId = (int)$options['snapshot-id'];
        echo "Verifying snapshot ID: {$snapshotId}\n";
        echo "─────────────────────────────────────────────────────────────\n\n";

        $result = $manager->verifySnapshotIntegrity($snapshotId);
        displaySnapshotResult($result);

        exit($result['valid'] ? 0 : 1);
    }

    // Verify all snapshots in a run
    if (isset($options['run-id'])) {
        $runId = (int)$options['run-id'];
        echo "Verifying all snapshots for run ID: {$runId}\n";
        echo "─────────────────────────────────────────────────────────────\n\n";

        $result = $manager->verifyRunSnapshots($runId);
        displayRunResult($result);

        exit($result['all_valid'] ? 0 : 1);
    }

    // Verify recent snapshots
    if (isset($options['recent'])) {
        $limit = (int)$options['recent'];
        echo "Verifying {$limit} most recent snapshots\n";
        echo "─────────────────────────────────────────────────────────────\n\n";

        $stmt = $pdo->prepare("
            SELECT id FROM payroll_snapshots
            ORDER BY snapshot_at DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        $snapshotIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

        verifyMultipleSnapshots($manager, $snapshotIds);
        exit(0);
    }

    // Verify all snapshots
    if (isset($options['all'])) {
        echo "Verifying ALL snapshots in database\n";
        echo "─────────────────────────────────────────────────────────────\n\n";

        $stmt = $pdo->query("SELECT id FROM payroll_snapshots ORDER BY id");
        $snapshotIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

        echo "Found " . count($snapshotIds) . " snapshots\n\n";
        verifyMultipleSnapshots($manager, $snapshotIds);
        exit(0);
    }

    // No options provided
    echo "❌ No verification target specified.\n\n";
    showHelp();
    exit(1);

} catch (\Throwable $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    echo "\nStack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}

/**
 * Display single snapshot verification result
 */
function displaySnapshotResult(array $result): void
{
    if ($result['valid']) {
        echo "✅ VALID - Snapshot integrity verified\n\n";
        echo "  Snapshot ID:    {$result['snapshot_id']}\n";
        echo "  Snapshot Time:  {$result['snapshot_at']}\n";
        echo "  Hash:           " . substr($result['stored_hash'], 0, 16) . "...\n";
        echo "  Status:         ✅ Hash matches\n\n";
    } else {
        echo "❌ INVALID - Snapshot integrity compromised!\n\n";
        echo "  Snapshot ID:    {$result['snapshot_id']}\n";
        echo "  Snapshot Time:  {$result['snapshot_at']}\n";
        echo "  Stored Hash:    " . substr($result['stored_hash'], 0, 16) . "...\n";
        echo "  Computed Hash:  " . substr($result['computed_hash'], 0, 16) . "...\n";
        echo "  Status:         ❌ HASH MISMATCH\n\n";

        if (isset($result['error'])) {
            echo "  Error:          {$result['error']}\n\n";
        }
    }
}

/**
 * Display run verification result
 */
function displayRunResult(array $result): void
{
    echo "Run ID: {$result['run_id']}\n";
    echo "Total Snapshots: {$result['total_snapshots']}\n";
    echo "Valid: {$result['valid']}\n";
    echo "Invalid: {$result['invalid']}\n\n";

    if ($result['all_valid']) {
        echo "✅ ALL SNAPSHOTS VALID\n\n";
    } else {
        echo "❌ INTEGRITY ISSUES DETECTED\n\n";
    }

    echo "Snapshot Details:\n";
    echo "─────────────────────────────────────────────────────────────\n";

    foreach ($result['snapshots'] as $snapshot) {
        $icon = $snapshot['valid'] ? '✅' : '❌';
        $status = $snapshot['valid'] ? 'VALID' : 'INVALID';
        echo "{$icon} Snapshot {$snapshot['snapshot_id']}: {$status}\n";
    }

    echo "\n";
}

/**
 * Verify multiple snapshots with progress indicator
 */
function verifyMultipleSnapshots(PayrollSnapshotManager $manager, array $snapshotIds): void
{
    $total = count($snapshotIds);
    $validCount = 0;
    $invalidCount = 0;
    $errors = [];

    foreach ($snapshotIds as $index => $snapshotId) {
        $progress = $index + 1;
        echo "\rVerifying {$progress}/{$total}...";

        $result = $manager->verifySnapshotIntegrity($snapshotId);

        if ($result['valid']) {
            $validCount++;
        } else {
            $invalidCount++;
            $errors[] = [
                'snapshot_id' => $snapshotId,
                'error' => $result['error'] ?? 'Hash mismatch'
            ];
        }
    }

    echo "\n\n";
    echo "╔═══════════════════════════════════════════════════════════════╗\n";
    echo "║  VERIFICATION COMPLETE                                         ║\n";
    echo "╚═══════════════════════════════════════════════════════════════╝\n\n";

    echo "Total Snapshots: {$total}\n";
    echo "✅ Valid:        {$validCount}\n";
    echo "❌ Invalid:      {$invalidCount}\n\n";

    if ($invalidCount > 0) {
        echo "❌ INTEGRITY ISSUES FOUND:\n";
        echo "─────────────────────────────────────────────────────────────\n";
        foreach ($errors as $error) {
            echo "  Snapshot {$error['snapshot_id']}: {$error['error']}\n";
        }
        echo "\n";
    } else {
        echo "✅ ALL SNAPSHOTS VERIFIED SUCCESSFULLY!\n\n";
    }
}

/**
 * Show help message
 */
function showHelp(): void
{
    echo <<<HELP
Payroll Snapshot Integrity Verification Tool

Usage:
  php verify_snapshots.php [OPTIONS]

Options:
  --snapshot-id=ID    Verify a single snapshot
  --run-id=ID         Verify all snapshots in a pay run
  --recent=N          Verify N most recent snapshots
  --all               Verify ALL snapshots in database
  --help              Show this help message

Examples:
  php verify_snapshots.php --snapshot-id=123
  php verify_snapshots.php --run-id=45
  php verify_snapshots.php --recent=10
  php verify_snapshots.php --all

Exit Codes:
  0 - All snapshots valid
  1 - One or more snapshots invalid or error occurred

HELP;
}
