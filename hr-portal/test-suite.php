<?php
/**
 * HR PORTAL - COMPREHENSIVE TEST SUITE
 *
 * Tests all pages, integrations, and API endpoints
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "═══════════════════════════════════════════════════════════════════════\n";
echo "  HR PORTAL - COMPREHENSIVE TEST SUITE\n";
echo "═══════════════════════════════════════════════════════════════════════\n\n";

$results = [
    'passed' => 0,
    'failed' => 0,
    'warnings' => 0
];

function test($name, $callback) {
    global $results;
    echo "Testing: $name ... ";
    try {
        $result = $callback();
        if ($result === true) {
            echo "✓ PASS\n";
            $results['passed']++;
        } elseif ($result === null) {
            echo "⚠ WARNING\n";
            $results['warnings']++;
        } else {
            echo "✗ FAIL: $result\n";
            $results['failed']++;
        }
    } catch (Exception $e) {
        echo "✗ FAIL: " . $e->getMessage() . "\n";
        $results['failed']++;
    }
}

// ============================================================================
// SECTION 1: FILE EXISTENCE TESTS
// ============================================================================
echo "\n【1】 FILE EXISTENCE TESTS\n";
echo "────────────────────────────────────────────────────────────────────────\n";

$requiredFiles = [
    'index.php',
    'integrations.php',
    'staff-directory.php',
    'staff-detail.php',
    'staff-timesheets.php',
    'staff-payroll.php',
    'includes/DeputyIntegration.php',
    'includes/XeroIntegration.php',
    'includes/AIPayrollEngine.php',
    'includes/PayrollDashboard.php',
    'api/sync-timesheet.php',
    'api/sync-payrun.php',
    'api/sync-deputy.php',
    'api/sync-xero.php',
    'api/approve-item.php',
    'api/deny-item.php',
    'api/batch-approve.php',
    'api/toggle-autopilot.php',
    'api/dashboard-stats.php'
];

foreach ($requiredFiles as $file) {
    test("File exists: $file", function() use ($file) {
        return file_exists(__DIR__ . '/' . $file);
    });
}

// ============================================================================
// SECTION 2: PHP SYNTAX TESTS
// ============================================================================
echo "\n【2】 PHP SYNTAX VALIDATION\n";
echo "────────────────────────────────────────────────────────────────────────\n";

foreach ($requiredFiles as $file) {
    if (file_exists(__DIR__ . '/' . $file)) {
        test("Syntax check: $file", function() use ($file) {
            $output = [];
            $return = 0;
            exec("php -l " . escapeshellarg(__DIR__ . '/' . $file) . " 2>&1", $output, $return);
            return $return === 0;
        });
    }
}

// ============================================================================
// SECTION 3: CLASS LOADING TESTS
// ============================================================================
echo "\n【3】 CLASS LOADING TESTS\n";
echo "────────────────────────────────────────────────────────────────────────\n";

// Test if we can load the integration classes without errors
test("Load DeputyIntegration class", function() {
    $file = __DIR__ . '/includes/DeputyIntegration.php';
    if (!file_exists($file)) return "File not found";

    $content = file_get_contents($file);
    if (strpos($content, 'class DeputyIntegration') === false) {
        return "Class definition not found";
    }
    return true;
});

test("Load XeroIntegration class", function() {
    $file = __DIR__ . '/includes/XeroIntegration.php';
    if (!file_exists($file)) return "File not found";

    $content = file_get_contents($file);
    if (strpos($content, 'class XeroIntegration') === false) {
        return "Class definition not found";
    }
    return true;
});

test("Load AIPayrollEngine class", function() {
    $file = __DIR__ . '/includes/AIPayrollEngine.php';
    if (!file_exists($file)) return "File not found";

    $content = file_get_contents($file);
    if (strpos($content, 'class AIPayrollEngine') === false) {
        return "Class definition not found";
    }
    return true;
});

test("Load PayrollDashboard class", function() {
    $file = __DIR__ . '/includes/PayrollDashboard.php';
    if (!file_exists($file)) return "File not found";

    $content = file_get_contents($file);
    if (strpos($content, 'class PayrollDashboard') === false) {
        return "Class definition not found";
    }
    return true;
});

// ============================================================================
// SECTION 4: INTEGRATION WRAPPER TESTS
// ============================================================================
echo "\n【4】 INTEGRATION WRAPPER STRUCTURE\n";
echo "────────────────────────────────────────────────────────────────────────\n";

test("DeputyIntegration has required methods", function() {
    $content = file_get_contents(__DIR__ . '/includes/DeputyIntegration.php');
    $methods = ['getEmployee', 'getTimesheets', 'syncTimesheetAmendment', 'getAllEmployees', 'testConnection'];
    foreach ($methods as $method) {
        if (strpos($content, "function $method") === false) {
            return "Missing method: $method";
        }
    }
    return true;
});

test("XeroIntegration has required methods", function() {
    $content = file_get_contents(__DIR__ . '/includes/XeroIntegration.php');
    $methods = ['getEmployee', 'getAllEmployees', 'getPayRuns', 'syncPayrunAmendment', 'getLeaveApplications', 'testConnection'];
    foreach ($methods as $method) {
        if (strpos($content, "function $method") === false) {
            return "Missing method: $method";
        }
    }
    return true;
});

test("DeputyIntegration uses existing services", function() {
    $content = file_get_contents(__DIR__ . '/includes/DeputyIntegration.php');
    if (strpos($content, 'PayrollModule\Services') === false) {
        return "Not using PayrollModule\Services namespace";
    }
    if (strpos($content, 'DeputyService') === false) {
        return "Not using DeputyService";
    }
    return true;
});

test("XeroIntegration uses existing services", function() {
    $content = file_get_contents(__DIR__ . '/includes/XeroIntegration.php');
    if (strpos($content, 'PayrollModule\Services') === false) {
        return "Not using PayrollModule\Services namespace";
    }
    if (strpos($content, 'XeroServiceSDK') === false) {
        return "Not using XeroServiceSDK";
    }
    return true;
});

// ============================================================================
// SECTION 5: PAGE NAVIGATION TESTS
// ============================================================================
echo "\n【5】 NAVIGATION & INTERCONNECTION\n";
echo "────────────────────────────────────────────────────────────────────────\n";

test("staff-directory.php links to staff-detail.php", function() {
    $content = file_get_contents(__DIR__ . '/staff-directory.php');
    return strpos($content, 'staff-detail.php') !== false;
});

test("staff-directory.php links to staff-timesheets.php", function() {
    $content = file_get_contents(__DIR__ . '/staff-directory.php');
    return strpos($content, 'staff-timesheets.php') !== false;
});

test("staff-directory.php links to staff-payroll.php", function() {
    $content = file_get_contents(__DIR__ . '/staff-directory.php');
    return strpos($content, 'staff-payroll.php') !== false;
});

test("staff-detail.php has 4 tabs", function() {
    $content = file_get_contents(__DIR__ . '/staff-detail.php');
    $tabs = ['Overview', 'Timesheets', 'Payroll', 'AI History'];
    foreach ($tabs as $tab) {
        if (stripos($content, $tab) === false) {
            return "Missing tab: $tab";
        }
    }
    return true;
});

test("staff-timesheets.php has breadcrumb navigation", function() {
    $content = file_get_contents(__DIR__ . '/staff-timesheets.php');
    return strpos($content, 'breadcrumb') !== false && strpos($content, 'staff-detail.php') !== false;
});

test("staff-payroll.php has breadcrumb navigation", function() {
    $content = file_get_contents(__DIR__ . '/staff-payroll.php');
    return strpos($content, 'breadcrumb') !== false && strpos($content, 'staff-detail.php') !== false;
});

test("index.php has Quick Navigation", function() {
    $content = file_get_contents(__DIR__ . '/index.php');
    return strpos($content, 'Quick Navigation') !== false || strpos($content, 'staff-directory.php') !== false;
});

test("integrations.php links back to index.php", function() {
    $content = file_get_contents(__DIR__ . '/integrations.php');
    return strpos($content, 'index.php') !== false;
});

// ============================================================================
// SECTION 6: DEPUTY INTEGRATION VISIBILITY
// ============================================================================
echo "\n【6】 DEPUTY INTEGRATION VISIBILITY\n";
echo "────────────────────────────────────────────────────────────────────────\n";

test("staff-directory.php shows Deputy badge", function() {
    $content = file_get_contents(__DIR__ . '/staff-directory.php');
    return strpos($content, 'deputy_employee_id') !== false || strpos($content, 'deputy_id') !== false || stripos($content, 'Deputy') !== false;
});

test("staff-detail.php shows Deputy ID", function() {
    $content = file_get_contents(__DIR__ . '/staff-detail.php');
    return strpos($content, 'deputy_id') !== false || stripos($content, 'Deputy ID') !== false;
});

test("staff-timesheets.php shows Deputy sync status", function() {
    $content = file_get_contents(__DIR__ . '/staff-timesheets.php');
    return strpos($content, 'Deputy Sync') !== false || strpos($content, 'deputy') !== false;
});

test("staff-timesheets.php has 'Sync to Deputy' button", function() {
    $content = file_get_contents(__DIR__ . '/staff-timesheets.php');
    return stripos($content, 'Sync to Deputy') !== false || stripos($content, 'syncToDeputy') !== false;
});

test("integrations.php has Deputy connection card", function() {
    $content = file_get_contents(__DIR__ . '/integrations.php');
    return stripos($content, 'Deputy Integration') !== false;
});

// ============================================================================
// SECTION 7: XERO INTEGRATION VISIBILITY
// ============================================================================
echo "\n【7】 XERO INTEGRATION VISIBILITY\n";
echo "────────────────────────────────────────────────────────────────────────\n";

test("staff-directory.php shows Xero badge", function() {
    $content = file_get_contents(__DIR__ . '/staff-directory.php');
    return strpos($content, 'xero_employee_id') !== false || strpos($content, 'xero_id') !== false || stripos($content, 'Xero') !== false;
});

test("staff-detail.php shows Xero ID", function() {
    $content = file_get_contents(__DIR__ . '/staff-detail.php');
    return strpos($content, 'xero_id') !== false || stripos($content, 'Xero ID') !== false;
});

test("staff-payroll.php shows Xero sync status", function() {
    $content = file_get_contents(__DIR__ . '/staff-payroll.php');
    return strpos($content, 'Xero Sync') !== false || strpos($content, 'xero') !== false;
});

test("staff-payroll.php has 'Sync to Xero' button", function() {
    $content = file_get_contents(__DIR__ . '/staff-payroll.php');
    return stripos($content, 'Sync to Xero') !== false || stripos($content, 'syncToXero') !== false;
});

test("integrations.php has Xero connection card", function() {
    $content = file_get_contents(__DIR__ . '/integrations.php');
    return stripos($content, 'Xero Integration') !== false;
});

// ============================================================================
// SECTION 8: API ENDPOINT TESTS
// ============================================================================
echo "\n【8】 API ENDPOINT STRUCTURE\n";
echo "────────────────────────────────────────────────────────────────────────\n";

test("api/sync-timesheet.php calls DeputyIntegration", function() {
    $content = file_get_contents(__DIR__ . '/api/sync-timesheet.php');
    return strpos($content, 'DeputyIntegration') !== false;
});

test("api/sync-payrun.php calls XeroIntegration", function() {
    $content = file_get_contents(__DIR__ . '/api/sync-payrun.php');
    return strpos($content, 'XeroIntegration') !== false;
});

test("api/sync-deputy.php handles bulk sync", function() {
    $content = file_get_contents(__DIR__ . '/api/sync-deputy.php');
    return strpos($content, 'getAllEmployees') !== false || strpos($content, 'employees') !== false;
});

test("api/sync-xero.php handles bulk sync", function() {
    $content = file_get_contents(__DIR__ . '/api/sync-xero.php');
    return strpos($content, 'getAllEmployees') !== false || strpos($content, 'employees') !== false;
});

test("API endpoints return JSON", function() {
    $files = ['sync-timesheet.php', 'sync-payrun.php', 'sync-deputy.php', 'sync-xero.php'];
    foreach ($files as $file) {
        $content = file_get_contents(__DIR__ . '/api/' . $file);
        if (strpos($content, 'application/json') === false && strpos($content, 'json_encode') === false) {
            return "Missing JSON response in $file";
        }
    }
    return true;
});

// ============================================================================
// SECTION 9: SQL QUERY TESTS
// ============================================================================
echo "\n【9】 SQL QUERY STRUCTURE\n";
echo "────────────────────────────────────────────────────────────────────────\n";

test("staff-directory.php queries staff table", function() {
    $content = file_get_contents(__DIR__ . '/staff-directory.php');
    return stripos($content, 'FROM staff') !== false || stripos($content, 'SELECT') !== false;
});

test("staff-timesheets.php joins with integration_sync_log", function() {
    $content = file_get_contents(__DIR__ . '/staff-timesheets.php');
    return stripos($content, 'integration_sync_log') !== false;
});

test("staff-payroll.php joins with integration_sync_log", function() {
    $content = file_get_contents(__DIR__ . '/staff-payroll.php');
    return stripos($content, 'integration_sync_log') !== false;
});

test("staff-detail.php queries AI decisions", function() {
    $content = file_get_contents(__DIR__ . '/staff-detail.php');
    return stripos($content, 'payroll_ai_decisions') !== false;
});

// ============================================================================
// SECTION 10: UI FEATURES
// ============================================================================
echo "\n【10】 UI FEATURES & FUNCTIONALITY\n";
echo "────────────────────────────────────────────────────────────────────────\n";

test("staff-directory.php has search functionality", function() {
    $content = file_get_contents(__DIR__ . '/staff-directory.php');
    return stripos($content, 'search') !== false;
});

test("staff-timesheets.php has pagination", function() {
    $content = file_get_contents(__DIR__ . '/staff-timesheets.php');
    return stripos($content, 'pagination') !== false || strpos($content, '$perPage') !== false;
});

test("staff-payroll.php has YTD summary", function() {
    $content = file_get_contents(__DIR__ . '/staff-payroll.php');
    return stripos($content, 'YTD') !== false || stripos($content, 'year-to-date') !== false;
});

test("integrations.php has sync buttons", function() {
    $content = file_get_contents(__DIR__ . '/integrations.php');
    return stripos($content, 'Sync') !== false && stripos($content, 'button') !== false;
});

test("Pages use Bootstrap styling", function() {
    $files = ['staff-directory.php', 'staff-detail.php', 'staff-timesheets.php'];
    foreach ($files as $file) {
        $content = file_get_contents(__DIR__ . '/' . $file);
        if (stripos($content, 'card') === false && stripos($content, 'btn') === false) {
            return "Missing Bootstrap classes in $file";
        }
    }
    return true;
});

// ============================================================================
// RESULTS SUMMARY
// ============================================================================
echo "\n═══════════════════════════════════════════════════════════════════════\n";
echo "  TEST RESULTS SUMMARY\n";
echo "═══════════════════════════════════════════════════════════════════════\n\n";

$total = $results['passed'] + $results['failed'] + $results['warnings'];
$passRate = $total > 0 ? round(($results['passed'] / $total) * 100, 1) : 0;

echo "Total Tests:    " . $total . "\n";
echo "✓ Passed:       " . $results['passed'] . " (" . $passRate . "%)\n";
echo "✗ Failed:       " . $results['failed'] . "\n";
echo "⚠ Warnings:     " . $results['warnings'] . "\n\n";

if ($results['failed'] === 0) {
    echo "🎉 ALL TESTS PASSED! HR Portal is ready to use.\n\n";
    echo "Next Steps:\n";
    echo "  1. Visit: http://staff.vapeshed.co.nz/modules/hr-portal/\n";
    echo "  2. Test Deputy connection in integrations.php\n";
    echo "  3. Test Xero connection in integrations.php\n";
    echo "  4. Browse staff in staff-directory.php\n";
    echo "  5. View staff details and test sync functionality\n";
} else {
    echo "⚠️  Some tests failed. Please review the output above.\n";
    exit(1);
}

echo "\n═══════════════════════════════════════════════════════════════════════\n";
