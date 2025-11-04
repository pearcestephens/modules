<?php
/**
 * Vend Allocation Service Test Script
 *
 * Tests the VendAllocationService with the 248 pending deductions
 */

require_once __DIR__ . '/vendor/autoload.php';

// Database connection
$pdo = new PDO(
    'mysql:host=127.0.0.1;dbname=jcepnzzkmj;charset=utf8mb4',
    'jcepnzzkmj',
    'wprKh9Jq63',
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]
);

use PayrollModule\Services\VendAllocationService;

$service = new VendAllocationService($pdo);

echo "=" . str_repeat("=", 70) . "\n";
echo "VEND ALLOCATION SERVICE TEST\n";
echo "=" . str_repeat("=", 70) . "\n\n";

// Test 1: Get current stats
echo "1. Getting current statistics...\n";
$stats = $service->getStats();
echo "   Pending deductions: " . ($stats['total_pending'] ?? 0) . "\n";
echo "   Total amount: $" . number_format($stats['total_amount'] ?? 0, 2) . "\n";
echo "   Oldest transaction: " . ($stats['oldest_transaction'] ?? 'N/A') . "\n";
echo "   Affected staff: " . ($stats['affected_staff'] ?? 0) . "\n\n";

// Test 2: Dry run allocation (pay run ID 1 for testing)
$testPayRunId = 1;
echo "2. Running DRY RUN allocation for pay run #{$testPayRunId}...\n";
$dryRunResult = $service->dryRun($testPayRunId, ['max_amount' => 5000]);

if ($dryRunResult['success']) {
    echo "   ✅ Dry run successful!\n";
    echo "   Would allocate: " . $dryRunResult['summary']['allocated_count'] . " deductions\n";
    echo "   Total amount: $" . number_format($dryRunResult['summary']['total_amount'], 2) . "\n";
    echo "   Failed: " . $dryRunResult['summary']['failed_count'] . "\n";
    echo "   Remaining: " . $dryRunResult['summary']['pending_remaining'] . "\n";
} else {
    echo "   ❌ Dry run failed: " . $dryRunResult['error'] . "\n";
}
echo "\n";

// Test 3: Real allocation (commented out for safety - uncomment to run)
/*
echo "3. Running REAL allocation...\n";
$realResult = $service->allocateToPayRun($testPayRunId, ['max_amount' => 1000]);

if ($realResult['success']) {
    echo "   ✅ Allocation successful!\n";
    echo "   Allocated: " . $realResult['summary']['allocated_count'] . " deductions\n";
    echo "   Total amount: $" . number_format($realResult['summary']['total_amount'], 2) . "\n";
} else {
    echo "   ❌ Allocation failed: " . $realResult['error'] . "\n";
}
echo "\n";
*/

// Test 4: Generate reconciliation report
echo "3. Generating reconciliation report for pay run #{$testPayRunId}...\n";
$report = $service->generateReconciliationReport($testPayRunId);
echo "   Total allocated: " . $report['total_allocated'] . "\n";
echo "   Total amount: $" . number_format($report['total_amount'], 2) . "\n";
echo "   Staff members: " . count($report['by_staff']) . "\n";
echo "   Generated at: " . $report['generated_at'] . "\n\n";

echo "=" . str_repeat("=", 70) . "\n";
echo "TEST COMPLETE\n";
echo "=" . str_repeat("=", 70) . "\n";
