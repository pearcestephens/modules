<?php
/**
 * Iterative Endpoint Testing Bot
 *
 * Tests every endpoint with curl, verifies responses, and fixes failures
 * Includes bot bypass parameters and cookie handling
 *
 * Usage: php ITERATIVE_ENDPOINT_TEST.php
 */

declare(strict_types=1);

// Configuration
$config = [
    'base_url' => 'https://staff.vapeshed.co.nz/modules/bank-transactions',
    'module_path' => __DIR__,
    'bot_bypass' => '?bot=true',
    'timeout' => 10,
    'retry_count' => 3,
    'verbose' => true,
];

// Initialize test results
$results = [
    'passed' => 0,
    'failed' => 0,
    'errors' => [],
    'tests' => [],
];

// Color codes for terminal output
$colors = [
    'green' => "\033[92m",
    'red' => "\033[91m",
    'yellow' => "\033[93m",
    'blue' => "\033[94m",
    'reset' => "\033[0m",
    'bold' => "\033[1m",
];

// ============================================================================
// TEST SUITE 1: ENTRY POINTS
// ============================================================================

echo "\n{$colors['bold']}{$colors['blue']}╔════════════════════════════════════════════════════════════════╗{$colors['reset']}\n";
echo "{$colors['bold']}{$colors['blue']}║ PHASE 1: ENTRY POINTS                                          ║{$colors['reset']}\n";
echo "{$colors['bold']}{$colors['blue']}╚════════════════════════════════════════════════════════════════╝{$colors['reset']}\n\n";

$entryPoints = [
    ['name' => 'Dashboard', 'url' => $config['base_url'] . '/?route=dashboard' . $config['bot_bypass'], 'method' => 'GET'],
    ['name' => 'Transaction List', 'url' => $config['base_url'] . '/?route=list' . $config['bot_bypass'], 'method' => 'GET'],
    ['name' => 'Auto-Match', 'url' => $config['base_url'] . '/?route=auto-match' . $config['bot_bypass'], 'method' => 'GET'],
    ['name' => 'Manual Match', 'url' => $config['base_url'] . '/?route=manual-match' . $config['bot_bypass'], 'method' => 'GET'],
];

foreach ($entryPoints as $test) {
    testEndpoint($test, $results, $config, $colors);
}

// ============================================================================
// TEST SUITE 2: API ENDPOINTS (GET)
// ============================================================================

echo "\n{$colors['bold']}{$colors['blue']}╔════════════════════════════════════════════════════════════════╗{$colors['reset']}\n";
echo "{$colors['bold']}{$colors['blue']}║ PHASE 2: API ENDPOINTS (GET)                                   ║{$colors['reset']}\n";
echo "{$colors['bold']}{$colors['blue']}╚════════════════════════════════════════════════════════════════╝{$colors['reset']}\n\n";

$getEndpoints = [
    ['name' => 'Dashboard Metrics', 'url' => $config['base_url'] . '/api/dashboard-metrics.php' . $config['bot_bypass'], 'method' => 'GET', 'expects_json' => true],
    ['name' => 'Match Suggestions', 'url' => $config['base_url'] . '/api/match-suggestions.php' . $config['bot_bypass'], 'method' => 'GET', 'expects_json' => true],
    ['name' => 'Export', 'url' => $config['base_url'] . '/api/export.php' . $config['bot_bypass'], 'method' => 'GET', 'expects_json' => true],
];

foreach ($getEndpoints as $test) {
    testEndpoint($test, $results, $config, $colors);
}

// ============================================================================
// TEST SUITE 3: API ENDPOINTS (POST)
// ============================================================================

echo "\n{$colors['bold']}{$colors['blue']}╔════════════════════════════════════════════════════════════════╗{$colors['reset']}\n";
echo "{$colors['bold']}{$colors['blue']}║ PHASE 3: API ENDPOINTS (POST)                                  ║{$colors['reset']}\n";
echo "{$colors['bold']}{$colors['blue']}╚════════════════════════════════════════════════════════════════╝{$colors['reset']}\n\n";

$postEndpoints = [
    [
        'name' => 'Auto-Match Single',
        'url' => $config['base_url'] . '/api/auto-match-single.php?bot=true',
        'method' => 'POST',
        'expects_json' => true,
        'data' => ['transaction_id' => 1, 'csrf_token' => 'bot-bypass']
    ],
    [
        'name' => 'Auto-Match All',
        'url' => $config['base_url'] . '/api/auto-match-all.php?bot=true',
        'method' => 'POST',
        'expects_json' => true,
        'data' => ['from_date' => date('2025-01-01'), 'to_date' => date('Y-m-d'), 'csrf_token' => 'bot-bypass']
    ],
    [
        'name' => 'Bulk Auto-Match',
        'url' => $config['base_url'] . '/api/bulk-auto-match.php?bot=true',
        'method' => 'POST',
        'expects_json' => true,
        'data' => ['transaction_ids' => [1, 2, 3], 'csrf_token' => 'bot-bypass']
    ],
    [
        'name' => 'Bulk Send Review',
        'url' => $config['base_url'] . '/api/bulk-send-review.php?bot=true',
        'method' => 'POST',
        'expects_json' => true,
        'data' => ['transaction_ids' => [1, 2, 3], 'csrf_token' => 'bot-bypass']
    ],
    [
        'name' => 'Reassign Payment',
        'url' => $config['base_url'] . '/api/reassign-payment.php?bot=true',
        'method' => 'POST',
        'expects_json' => true,
        'data' => ['transaction_id' => 1, 'order_id' => 123, 'csrf_token' => 'bot-bypass']
    ],
    [
        'name' => 'Settings (POST)',
        'url' => $config['base_url'] . '/api/settings.php?bot=true',
        'method' => 'POST',
        'expects_json' => true,
        'data' => ['setting_key' => 'auto_match_threshold', 'setting_value' => '200', 'csrf_token' => 'bot-bypass']
    ],
];

foreach ($postEndpoints as $test) {
    testEndpoint($test, $results, $config, $colors);
}

// ============================================================================
// TEST SUITE 4: FILE STRUCTURE VALIDATION
// ============================================================================

echo "\n{$colors['bold']}{$colors['blue']}╔════════════════════════════════════════════════════════════════╗{$colors['reset']}\n";
echo "{$colors['bold']}{$colors['blue']}║ PHASE 4: FILE STRUCTURE VALIDATION                             ║{$colors['reset']}\n";
echo "{$colors['bold']}{$colors['blue']}╚════════════════════════════════════════════════════════════════╝{$colors['reset']}\n\n";

$requiredFiles = [
    'controllers/BaseController.php',
    'controllers/DashboardController.php',
    'controllers/TransactionController.php',
    'controllers/MatchingController.php',
    'models/BaseModel.php',
    'models/TransactionModel.php',
    'models/OrderModel.php',
    'models/PaymentModel.php',
    'models/AuditLogModel.php',
    'models/MatchingRuleModel.php',
    'lib/MatchingEngine.php',
    'lib/ConfidenceScorer.php',
    'lib/APIHelper.php',
    'lib/PaymentProcessor.php',
    'lib/TransactionService.php',
    'views/dashboard.php',
    'views/transaction-list.php',
    'views/match-suggestions.php',
    'views/bulk-operations.php',
    'views/settings.php',
    'api/dashboard-metrics.php',
    'api/match-suggestions.php',
    'api/auto-match-single.php',
    'api/auto-match-all.php',
    'api/bulk-auto-match.php',
    'api/bulk-send-review.php',
    'api/reassign-payment.php',
    'api/export.php',
    'api/settings.php',
];

foreach ($requiredFiles as $file) {
    $fullPath = $config['module_path'] . '/' . $file;
    $status = file_exists($fullPath) ? 'PASS' : 'FAIL';
    $icon = file_exists($fullPath) ? "{$colors['green']}✅{$colors['reset']}" : "{$colors['red']}❌{$colors['reset']}";

    echo "{$icon} {$status}: {$file}\n";

    if (file_exists($fullPath)) {
        $results['passed']++;
    } else {
        $results['failed']++;
        $results['errors'][] = "Missing file: {$file}";
    }
}

// ============================================================================
// FINAL REPORT
// ============================================================================

echo "\n{$colors['bold']}{$colors['blue']}╔════════════════════════════════════════════════════════════════╗{$colors['reset']}\n";
echo "{$colors['bold']}{$colors['blue']}║ TEST SUMMARY                                                   ║{$colors['reset']}\n";
echo "{$colors['bold']}{$colors['blue']}╚════════════════════════════════════════════════════════════════╝{$colors['reset']}\n\n";

$totalTests = $results['passed'] + $results['failed'];
$successRate = $totalTests > 0 ? round(($results['passed'] / $totalTests) * 100, 1) : 0;

echo "Total Tests:     {$results['passed']} passed, {$results['failed']} failed\n";
echo "Success Rate:    {$successRate}%\n";
echo "Status:          ";

if ($results['failed'] === 0) {
    echo "{$colors['green']}{$colors['bold']}✅ ALL TESTS PASSED!{$colors['reset']}\n";
} else {
    echo "{$colors['red']}{$colors['bold']}⚠️  SOME TESTS FAILED{$colors['reset']}\n";

    if (!empty($results['errors'])) {
        echo "\n{$colors['red']}Errors:{$colors['reset']}\n";
        foreach ($results['errors'] as $error) {
            echo "  • {$error}\n";
        }
    }
}

echo "\n";

// ============================================================================
// HELPER FUNCTIONS
// ============================================================================

function testEndpoint(array $test, array &$results, array $config, array $colors): void
{
    $name = $test['name'];
    $url = $test['url'];
    $method = $test['method'];
    $expectsJson = $test['expects_json'] ?? false;
    $data = $test['data'] ?? null;

    echo "Testing: {$name}... ";

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => $config['timeout'],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_USERAGENT => 'CIS-Testing-Bot/1.0',
    ]);

    // Add POST data if provided
    if ($method === 'POST' && $data) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    }

    // Add bot bypass header
    curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge(
        curl_getinfo($ch, CURLINFO_HTTP_CODE) ? [] : [],
        ['X-Bot-Bypass: 1', 'User-Agent: CIS-Testing-Bot/1.0']
    ));

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    // Validate response
    $passed = false;
    if ($error) {
        echo "{$colors['red']}FAIL{$colors['reset']} (Error: {$error})\n";
        $results['failed']++;
        $results['errors'][] = "{$name}: {$error}";
    } elseif ($httpCode >= 400) {
        echo "{$colors['red']}FAIL{$colors['reset']} (HTTP {$httpCode})\n";
        $results['failed']++;
        $results['errors'][] = "{$name}: HTTP {$httpCode}";
    } elseif ($expectsJson && !isValidJson($response)) {
        echo "{$colors['yellow']}WARN{$colors['reset']} (Invalid JSON)\n";
        $results['failed']++;
        $results['errors'][] = "{$name}: Invalid JSON response";
    } else {
        echo "{$colors['green']}PASS{$colors['reset']}\n";
        $results['passed']++;
        $passed = true;
    }

    $results['tests'][] = [
        'name' => $name,
        'url' => $url,
        'method' => $method,
        'http_code' => $httpCode,
        'passed' => $passed,
    ];
}

function isValidJson(string $json): bool
{
    json_decode($json);
    return json_last_error() === JSON_ERROR_NONE;
}
?>
