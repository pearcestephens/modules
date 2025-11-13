<?php
/**
 * COMPREHENSIVE ENDPOINT TESTING SCRIPT
 *
 * Tests all Bank Transactions module endpoints with:
 * - Authentication bypass for testing
 * - Actual JSON payloads
 * - Response validation
 * - Error handling
 *
 * Usage: php TEST_ALL_ENDPOINTS.php
 * Or via browser: https://staff.vapeshed.co.nz/modules/bank-transactions/TEST_ALL_ENDPOINTS.php?bot=true
 */

declare(strict_types=1);

// Start output buffering
ob_start();

// Set testing environment
$_GET['bot'] = 'true';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['DOCUMENT_ROOT'] = '/home/master/applications/jcepnzzkmj/public_html';

// Include application bootstrap
require_once $_SERVER['DOCUMENT_ROOT'] . '/app.php';

// Mock user session for testing
$_SESSION['user_id'] = 1;
$_SESSION['staff_id'] = 1;
$_SESSION['logged_in'] = true;

// ANSI colors for CLI output
$colors = [
    'reset' => "\033[0m",
    'bold' => "\033[1m",
    'red' => "\033[31m",
    'green' => "\033[32m",
    'yellow' => "\033[33m",
    'blue' => "\033[34m",
    'magenta' => "\033[35m",
    'cyan' => "\033[36m",
];

// Detect if running in CLI
$isCLI = PHP_SAPI === 'cli';

function c($color, $text) {
    global $colors, $isCLI;
    if ($isCLI) {
        return $colors[$color] . $text . $colors['reset'];
    }
    return $text;
}

function section($title) {
    echo "\n" . c('bold', c('cyan', str_repeat('═', 80))) . "\n";
    echo c('bold', c('cyan', "  " . $title)) . "\n";
    echo c('bold', c('cyan', str_repeat('═', 80))) . "\n";
}

function test($name, $passed, $details = '') {
    $status = $passed ? c('green', '✓ PASS') : c('red', '✗ FAIL');
    echo sprintf("  %-60s %s\n", $name, $status);
    if (!$passed && $details) {
        echo c('yellow', "    → " . $details) . "\n";
    }
}

function testEndpoint($name, $path, $method = 'GET', $postData = null, $headers = []) {
    global $isCLI;

    $url = 'https://staff.vapeshed.co.nz' . $path;

    echo "\n" . c('bold', "Testing: $name") . "\n";
    echo "  URL: " . c('blue', $url) . "\n";
    echo "  Method: " . c('magenta', $method) . "\n";

    $ch = curl_init($url);

    // Set common options
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false, // For local testing
        CURLOPT_TIMEOUT => 30,
        CURLOPT_COOKIEFILE => '/tmp/curl_cookies.txt',
        CURLOPT_COOKIEJAR => '/tmp/curl_cookies.txt',
    ]);

    // Set method and data
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($postData) {
            $jsonData = json_encode($postData);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
            $headers[] = 'Content-Type: application/json';
            $headers[] = 'Content-Length: ' . strlen($jsonData);
            echo "  Payload: " . c('yellow', $jsonData) . "\n";
        }
    }

    // Add headers
    $headers[] = 'X-Requested-With: XMLHttpRequest';
    $headers[] = 'Accept: application/json';
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    // Execute request
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    // Parse response
    $isJson = false;
    $data = null;
    $contentType = '';

    if ($response) {
        $data = json_decode($response, true);
        $isJson = json_last_error() === JSON_ERROR_NONE;

        // Check if HTML response (bot bypass might have failed)
        if (!$isJson && stripos($response, '<!DOCTYPE') !== false) {
            $contentType = 'HTML';
        }
    }

    // Display results
    echo "  Response Code: " . ($httpCode === 200 ? c('green', $httpCode) : c('red', $httpCode)) . "\n";

    if ($error) {
        echo "  " . c('red', "cURL Error: $error") . "\n";
        return false;
    }

    if ($contentType === 'HTML') {
        echo "  " . c('yellow', "⚠ WARNING: Received HTML response (bot bypass may have failed)") . "\n";
        echo "  " . c('yellow', "Response preview: " . substr($response, 0, 200) . "...") . "\n";
        return false;
    }

    if (!$isJson) {
        echo "  " . c('red', "✗ Response is not valid JSON") . "\n";
        echo "  " . c('yellow', "Response: " . substr($response, 0, 500)) . "\n";
        return false;
    }

    // Check success field
    if (isset($data['success'])) {
        if ($data['success']) {
            echo "  " . c('green', "✓ Success: true") . "\n";
        } else {
            echo "  " . c('red', "✗ Success: false") . "\n";
            if (isset($data['error'])) {
                echo "  " . c('yellow', "Error: " . json_encode($data['error'])) . "\n";
            }
        }
    }

    // Display data summary
    if (isset($data['data'])) {
        echo "  " . c('green', "✓ Has data field") . "\n";
        if (is_array($data['data'])) {
            echo "  " . c('cyan', "Data keys: " . implode(', ', array_keys($data['data']))) . "\n";
        }
    }

    // Pretty print response (first 1000 chars)
    $jsonPretty = json_encode($data, JSON_PRETTY_PRINT);
    if (strlen($jsonPretty) > 1000) {
        $jsonPretty = substr($jsonPretty, 0, 1000) . "\n  ... (truncated)";
    }
    echo "  " . c('cyan', "Response:") . "\n";
    echo "  " . str_replace("\n", "\n  ", $jsonPretty) . "\n";

    return $httpCode === 200 && ($data['success'] ?? false);
}

// ============================================================================
// START TESTS
// ============================================================================

if (!$isCLI) {
    header('Content-Type: text/plain; charset=utf-8');
}

section("BANK TRANSACTIONS MODULE - COMPREHENSIVE ENDPOINT TEST");

echo "\n" . c('bold', "Environment:") . "\n";
echo "  PHP Version: " . PHP_VERSION . "\n";
echo "  Document Root: " . $_SERVER['DOCUMENT_ROOT'] . "\n";
echo "  Bot Bypass: " . (BOT_BYPASS_AUTH ? c('green', 'ENABLED') : c('red', 'DISABLED')) . "\n";
echo "  User ID: " . ($_SESSION['user_id'] ?? 'NOT SET') . "\n";
echo "\n";

$results = [];

// ============================================================================
section("1. DASHBOARD METRICS API");
// ============================================================================

$results['dashboard-metrics'] = testEndpoint(
    'Dashboard Metrics (Today)',
    '/modules/bank-transactions/api/dashboard-metrics.php?bot=true',
    'GET'
);

$results['dashboard-metrics-date'] = testEndpoint(
    'Dashboard Metrics (Specific Date)',
    '/modules/bank-transactions/api/dashboard-metrics.php?bot=true&date=2025-10-01',
    'GET'
);

// ============================================================================
section("2. AUTO-MATCH SINGLE TRANSACTION");
// ============================================================================

// First, get a transaction to test with
echo "\n" . c('yellow', "Note: Testing with transaction_id=1 (may not exist)") . "\n";

$results['auto-match-single'] = testEndpoint(
    'Auto-Match Single Transaction',
    '/modules/bank-transactions/api/auto-match-single.php?bot=true',
    'POST',
    [
        'transaction_id' => 1,
        'csrf_token' => 'test_token' // In real scenario, this would be validated
    ]
);

// ============================================================================
section("3. AUTO-MATCH ALL TRANSACTIONS");
// ============================================================================

$results['auto-match-all'] = testEndpoint(
    'Auto-Match All Unmatched Transactions',
    '/modules/bank-transactions/api/auto-match-all.php?bot=true',
    'POST',
    [
        'date' => date('Y-m-d'),
        'csrf_token' => 'test_token'
    ]
);

// ============================================================================
section("4. BULK AUTO-MATCH");
// ============================================================================

$results['bulk-auto-match'] = testEndpoint(
    'Bulk Auto-Match Multiple Transactions',
    '/modules/bank-transactions/api/bulk-auto-match.php?bot=true',
    'POST',
    [
        'transaction_ids' => [1, 2, 3],
        'csrf_token' => 'test_token'
    ]
);

// ============================================================================
section("5. BULK SEND TO REVIEW");
// ============================================================================

$results['bulk-send-review'] = testEndpoint(
    'Bulk Send Transactions to Manual Review',
    '/modules/bank-transactions/api/bulk-send-review.php?bot=true',
    'POST',
    [
        'transaction_ids' => [1, 2],
        'csrf_token' => 'test_token'
    ]
);

// ============================================================================
section("6. MATCH SUGGESTIONS");
// ============================================================================

$results['match-suggestions'] = testEndpoint(
    'Get Match Suggestions for Transaction',
    '/modules/bank-transactions/api/match-suggestions.php?bot=true&transaction_id=1',
    'GET'
);

// ============================================================================
section("7. REASSIGN PAYMENT");
// ============================================================================

$results['reassign-payment'] = testEndpoint(
    'Reassign Payment to Different Order',
    '/modules/bank-transactions/api/reassign-payment.php?bot=true',
    'POST',
    [
        'payment_id' => 1,
        'old_order_id' => 1000,
        'new_order_id' => 2000,
        'reason' => 'Test reassignment',
        'csrf_token' => 'test_token'
    ]
);

// ============================================================================
section("8. EXPORT TRANSACTIONS");
// ============================================================================

$results['export-csv'] = testEndpoint(
    'Export Transactions (CSV)',
    '/modules/bank-transactions/api/export.php?bot=true&format=csv&status=unmatched',
    'GET'
);

$results['export-json'] = testEndpoint(
    'Export Transactions (JSON)',
    '/modules/bank-transactions/api/export.php?bot=true&format=json&status=all',
    'GET'
);

// ============================================================================
section("SYNTAX CHECK ALL FILES");
// ============================================================================

$modulePath = $_SERVER['DOCUMENT_ROOT'] . '/modules/bank-transactions';
$files = [
    'bootstrap.php',
    'api/dashboard-metrics.php',
    'api/auto-match-single.php',
    'api/auto-match-all.php',
    'api/bulk-auto-match.php',
    'api/bulk-send-review.php',
    'api/match-suggestions.php',
    'api/reassign-payment.php',
    'api/export.php',
    'controllers/BaseController.php',
    'controllers/DashboardController.php',
    'controllers/TransactionController.php',
    'models/BaseModel.php',
    'models/TransactionModel.php',
    'models/OrderModel.php',
    'models/PaymentModel.php',
    'models/AuditLogModel.php',
    'lib/MatchingEngine.php',
    'lib/ConfidenceScorer.php',
    'views/dashboard.php',
    'views/transaction-list.php',
    'index.php',
];

echo "\n";
foreach ($files as $file) {
    $fullPath = $modulePath . '/' . $file;
    if (file_exists($fullPath)) {
        exec("php -l " . escapeshellarg($fullPath) . " 2>&1", $output, $returnCode);
        $passed = $returnCode === 0;
        test($file, $passed, $passed ? '' : implode(' ', $output));
    } else {
        test($file, false, 'File not found');
    }
}

// ============================================================================
section("TEST RESULTS SUMMARY");
// ============================================================================

$passed = array_filter($results);
$failed = array_diff_key($results, $passed);

echo "\n";
echo "  Total Tests: " . c('bold', count($results)) . "\n";
echo "  Passed: " . c('green', c('bold', count($passed))) . "\n";
echo "  Failed: " . c('red', c('bold', count($failed))) . "\n";
echo "  Success Rate: " . c('bold', round(count($passed) / count($results) * 100, 1) . '%') . "\n";

if (count($failed) > 0) {
    echo "\n" . c('red', "Failed Tests:") . "\n";
    foreach ($failed as $name => $result) {
        echo "  • " . c('red', $name) . "\n";
    }
}

echo "\n";
echo c('bold', c('green', "✓ Comprehensive endpoint testing complete!")) . "\n";
echo "\n";

// Clean output buffer
$output = ob_get_clean();
echo $output;

// Save to log file
$logFile = $modulePath . '/test-results-' . date('Y-m-d_H-i-s') . '.log';
file_put_contents($logFile, strip_tags($output));
echo c('cyan', "Results saved to: $logFile") . "\n";
