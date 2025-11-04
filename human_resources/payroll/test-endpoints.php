<?php
/**
 * Payroll Module Endpoint Testing Script
 * Tests all 60+ API endpoints systematically
 *
 * Usage: php test-endpoints.php
 */

$baseUrl = "https://staff.vapeshed.co.nz/modules/human_resources/payroll";
$timestamp = date('Y-m-d H:i:s');
$results = [];

echo str_repeat('=', 80) . "\n";
echo "Payroll Module Endpoint Testing\n";
echo "Started: $timestamp\n";
echo str_repeat('=', 80) . "\n\n";

/**
 * Test an endpoint
 */
function testEndpoint($method, $path, $authRequired, $description) {
    global $baseUrl, $results;

    // Convert route path to query parameter format
    // Routes like /api/payroll/dashboard/data become ?api=dashboard/data
    // Routes like /payroll/dashboard become ?view=dashboard
    if (strpos($path, '/api/payroll/') === 0) {
        // API endpoint - use ?api=path
        $apiPath = substr($path, strlen('/api/payroll/'));
        $fullUrl = $baseUrl . '/?api=' . $apiPath;
    } elseif (strpos($path, '/payroll/') === 0) {
        // View endpoint - use ?view=path
        $viewPath = substr($path, strlen('/payroll/'));
        $fullUrl = $baseUrl . '/?view=' . $viewPath;
    } else {
        // Other paths - use as-is
        $fullUrl = $baseUrl . $path;
    }

    echo "Testing: $method $path ... ";

    // Initialize cURL
    $ch = curl_init($fullUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: application/json',
        'User-Agent: PayrollModuleTester/1.0'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    // Execute request
    $response = curl_exec($ch);
    $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    // Determine result
    $result = 'unknown';
    $color = "\033[1;33m"; // Yellow

    if ($statusCode === 200) {
        $result = 'success';
        $color = "\033[0;32m"; // Green
    } elseif ($statusCode === 401 || $statusCode === 403) {
        if ($authRequired) {
            $result = 'auth_required';
            $color = "\033[1;33m"; // Yellow
        } else {
            $result = 'unexpected_auth';
            $color = "\033[0;31m"; // Red
        }
    } elseif ($statusCode === 404) {
        $result = 'not_found';
        $color = "\033[0;31m"; // Red
    } elseif ($statusCode === 405) {
        $result = 'method_not_allowed';
        $color = "\033[0;31m"; // Red
    } elseif ($statusCode >= 500) {
        $result = 'server_error';
        $color = "\033[0;31m"; // Red
    } elseif ($statusCode >= 400) {
        $result = 'client_error';
        $color = "\033[0;31m"; // Red
    }

    $nc = "\033[0m"; // No color
    echo "{$color}{$statusCode} - {$result}{$nc}\n";

    // Store result
    $results[] = [
        'method' => $method,
        'path' => $path,
        'url' => $fullUrl,
        'description' => $description,
        'auth_required' => $authRequired,
        'status_code' => $statusCode,
        'result' => $result,
        'response_preview' => substr($response, 0, 500),
        'error' => $error ?: null
    ];

    return $statusCode;
}

// ============================================================================
// PHASE 1: Health & Info Endpoints
// ============================================================================

echo "\n" . str_repeat('=', 80) . "\n";
echo "PHASE 1: Health & Info Endpoints (No Auth Required)\n";
echo str_repeat('=', 80) . "\n";

testEndpoint('GET', '/health/', false, 'System health check');

// ============================================================================
// PHASE 2: Dashboard Endpoints
// ============================================================================

echo "\n" . str_repeat('=', 80) . "\n";
echo "PHASE 2: Dashboard Endpoints (Auth Required)\n";
echo str_repeat('=', 80) . "\n";

testEndpoint('GET', '/payroll/dashboard', true, 'Main payroll dashboard view');
testEndpoint('GET', '/api/payroll/dashboard/data', true, 'Dashboard data API');

// ============================================================================
// PHASE 3: Amendment Endpoints
// ============================================================================

echo "\n" . str_repeat('=', 80) . "\n";
echo "PHASE 3: Amendment Endpoints\n";
echo str_repeat('=', 80) . "\n";

testEndpoint('GET', '/api/payroll/amendments/pending', true, 'Get pending amendments');
testEndpoint('GET', '/api/payroll/amendments/history', true, 'Get amendment history');

// ============================================================================
// PHASE 4: Automation Endpoints
// ============================================================================

echo "\n" . str_repeat('=', 80) . "\n";
echo "PHASE 4: Automation Endpoints\n";
echo str_repeat('=', 80) . "\n";

testEndpoint('GET', '/api/payroll/automation/dashboard', true, 'Automation dashboard stats');
testEndpoint('GET', '/api/payroll/automation/reviews/pending', true, 'Pending AI reviews');
testEndpoint('GET', '/api/payroll/automation/rules', true, 'Active AI rules');
testEndpoint('GET', '/api/payroll/automation/stats', true, 'Automation statistics');

// ============================================================================
// PHASE 5: Xero Endpoints
// ============================================================================

echo "\n" . str_repeat('=', 80) . "\n";
echo "PHASE 5: Xero Endpoints\n";
echo str_repeat('=', 80) . "\n";

testEndpoint('GET', '/api/payroll/xero/oauth/authorize', true, 'Xero OAuth initiation');

// ============================================================================
// PHASE 6: Wage Discrepancy Endpoints
// ============================================================================

echo "\n" . str_repeat('=', 80) . "\n";
echo "PHASE 6: Wage Discrepancy Endpoints\n";
echo str_repeat('=', 80) . "\n";

testEndpoint('GET', '/api/payroll/discrepancies/pending', true, 'Get pending discrepancies');
testEndpoint('GET', '/api/payroll/discrepancies/my-history', true, 'Get my discrepancy history');
testEndpoint('GET', '/api/payroll/discrepancies/statistics', true, 'Discrepancy statistics');

// ============================================================================
// PHASE 7: Bonus Endpoints
// ============================================================================

echo "\n" . str_repeat('=', 80) . "\n";
echo "PHASE 7: Bonus Endpoints\n";
echo str_repeat('=', 80) . "\n";

testEndpoint('GET', '/api/payroll/bonuses/pending', true, 'Get pending bonuses');
testEndpoint('GET', '/api/payroll/bonuses/history', true, 'Get bonus history');
testEndpoint('GET', '/api/payroll/bonuses/summary', true, 'Get staff bonus summary');
testEndpoint('GET', '/api/payroll/bonuses/vape-drops', true, 'Get vape drops');
testEndpoint('GET', '/api/payroll/bonuses/google-reviews', true, 'Get Google review bonuses');

// ============================================================================
// PHASE 8: Vend Payment Endpoints
// ============================================================================

echo "\n" . str_repeat('=', 80) . "\n";
echo "PHASE 8: Vend Payment Endpoints\n";
echo str_repeat('=', 80) . "\n";

testEndpoint('GET', '/api/payroll/vend-payments/pending', true, 'Get pending Vend payments');
testEndpoint('GET', '/api/payroll/vend-payments/history', true, 'Get Vend payment history');
testEndpoint('GET', '/api/payroll/vend-payments/statistics', true, 'Vend payment statistics');

// ============================================================================
// PHASE 9: Leave Endpoints
// ============================================================================

echo "\n" . str_repeat('=', 80) . "\n";
echo "PHASE 9: Leave Endpoints\n";
echo str_repeat('=', 80) . "\n";

testEndpoint('GET', '/api/payroll/leave/pending', true, 'Get pending leave requests');
testEndpoint('GET', '/api/payroll/leave/history', true, 'Get leave history');
testEndpoint('GET', '/api/payroll/leave/balances', true, 'Get leave balances');

// ============================================================================
// PHASE 10: Pay Run Endpoints
// ============================================================================

echo "\n" . str_repeat('=', 80) . "\n";
echo "PHASE 10: Pay Run Endpoints\n";
echo str_repeat('=', 80) . "\n";

testEndpoint('GET', '/payroll/payruns', true, 'Pay run list view');
testEndpoint('GET', '/api/payroll/payruns/list', true, 'Get pay runs list (AJAX)');

// ============================================================================
// PHASE 11: Reconciliation Endpoints
// ============================================================================

echo "\n" . str_repeat('=', 80) . "\n";
echo "PHASE 11: Reconciliation Endpoints\n";
echo str_repeat('=', 80) . "\n";

testEndpoint('GET', '/payroll/reconciliation', true, 'Reconciliation dashboard view');
testEndpoint('GET', '/api/payroll/reconciliation/dashboard', true, 'Reconciliation dashboard data');
testEndpoint('GET', '/api/payroll/reconciliation/variances', true, 'Get current variances');

// ============================================================================
// TEST SUMMARY
// ============================================================================

echo "\n" . str_repeat('=', 80) . "\n";
echo "TEST SUMMARY\n";
echo str_repeat('=', 80) . "\n\n";

// Calculate summary statistics
$total = count($results);
$success = count(array_filter($results, fn($r) => $r['result'] === 'success'));
$authReq = count(array_filter($results, fn($r) => $r['result'] === 'auth_required'));
$notFound = count(array_filter($results, fn($r) => $r['result'] === 'not_found'));
$serverError = count(array_filter($results, fn($r) => $r['result'] === 'server_error'));
$clientError = count(array_filter($results, fn($r) => $r['result'] === 'client_error'));

echo "Total Endpoints Tested: $total\n";
echo "\033[0;32mSuccessful (200): $success\033[0m\n";
echo "\033[1;33mAuth Required (401/403): $authReq\033[0m\n";
echo "\033[0;31mNot Found (404): $notFound\033[0m\n";
echo "\033[0;31mServer Errors (5xx): $serverError\033[0m\n";
echo "\033[0;31mClient Errors (4xx): $clientError\033[0m\n";

// Save detailed results to JSON
$resultsFile = 'test-results.json';
file_put_contents($resultsFile, json_encode([
    'timestamp' => $timestamp,
    'base_url' => $baseUrl,
    'summary' => [
        'total' => $total,
        'success' => $success,
        'auth_required' => $authReq,
        'not_found' => $notFound,
        'server_error' => $serverError,
        'client_error' => $clientError
    ],
    'endpoints' => $results
], JSON_PRETTY_PRINT));

echo "\nDetailed results saved to: $resultsFile\n";

// ============================================================================
// ERROR ANALYSIS
// ============================================================================

$errors = array_filter($results, fn($r) => in_array($r['result'], ['not_found', 'server_error', 'client_error']));

if (!empty($errors)) {
    echo "\n" . str_repeat('=', 80) . "\n";
    echo "ERRORS REQUIRING ATTENTION\n";
    echo str_repeat('=', 80) . "\n\n";

    foreach ($errors as $error) {
        echo "❌ {$error['method']} {$error['path']}\n";
        echo "   Status: {$error['status_code']} - {$error['result']}\n";
        echo "   Description: {$error['description']}\n";
        if ($error['response_preview']) {
            echo "   Response: " . substr($error['response_preview'], 0, 200) . "...\n";
        }
        echo "\n";
    }
}

echo "\n" . str_repeat('=', 80) . "\n";
echo "Next Steps:\n";
echo "1. Review test-results.json for detailed error messages\n";
echo "2. Fix any 404 (routing issues) or 500 (server errors)\n";
echo "3. For auth-required endpoints, test with authenticated session\n";
echo str_repeat('=', 80) . "\n";
