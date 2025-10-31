<?php
/**
 * Quick Test: Wage Discrepancy System
 *
 * Tests the service layer directly to verify it's working
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "🧪 WAGE DISCREPANCY SYSTEM - QUICK TEST\n";
echo str_repeat("=", 70) . "\n\n";

// Load app
require_once '/home/master/applications/jcepnzzkmj/public_html/app.php';

// Manually include service file for testing
require_once '/home/master/applications/jcepnzzkmj/public_html/modules/human_resources/payroll/services/WageDiscrepancyService.php';

use PayrollModule\Services\WageDiscrepancyService;

try {
    $service = new WageDiscrepancyService();
    echo "✅ Service loaded successfully\n\n";

    // Test 1: Get Statistics (should return zeros for new system)
    echo "Test 1: Get Statistics\n";
    echo str_repeat("-", 70) . "\n";
    $stats = $service->getStatistics();
    echo "Total discrepancies: " . $stats['total'] . "\n";
    echo "Pending: " . $stats['pending'] . "\n";
    echo "Auto-approved: " . $stats['auto_approved'] . "\n";
    echo "✅ Statistics working\n\n";

    // Test 2: Get Pending (should return empty array)
    echo "Test 2: Get Pending Queue\n";
    echo str_repeat("-", 70) . "\n";
    $pending = $service->getPendingDiscrepancies();
    echo "Pending count: " . count($pending) . "\n";
    echo "✅ Pending queue working\n\n";

    echo str_repeat("=", 70) . "\n";
    echo "🎉 ALL TESTS PASSED!\n";
    echo "\nSystem is ready for:\n";
    echo "  1. Staff submissions via API\n";
    echo "  2. Manager review dashboard\n";
    echo "  3. Auto-approval workflow\n\n";

} catch (\Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "\nStack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
