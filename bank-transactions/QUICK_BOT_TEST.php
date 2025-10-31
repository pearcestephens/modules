<?php
/**
 * Quick Bot Bypass Endpoint Tester
 * Tests all 9 APIs for HTTP 200 + valid JSON
 */

$baseUrl = 'https://staff.vapeshed.co.nz/modules/bank-transactions';
$botBypass = '?bot=true';

$apis = [
    'GET' => [
        '/api/dashboard-metrics.php',
        '/api/match-suggestions.php',
        '/api/export.php',
    ],
    'POST' => [
        '/api/auto-match-single.php',
        '/api/auto-match-all.php',
        '/api/bulk-auto-match.php',
        '/api/bulk-send-review.php',
        '/api/reassign-payment.php',
        '/api/settings.php',
    ]
];

echo "╔════════════════════════════════════════════════════════╗\n";
echo "║ BOT BYPASS + HTTP 200 + JSON VALIDATION TEST           ║\n";
echo "╚════════════════════════════════════════════════════════╝\n\n";

$passed = 0;
$failed = 0;
$errors = [];

// Test GET endpoints
foreach ($apis['GET'] as $endpoint) {
    $url = $baseUrl . $endpoint . $botBypass;
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_CUSTOMREQUEST => 'GET',
        CURLOPT_HTTPHEADER => ['X-Bot-Bypass: 1'],
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $isJson = @json_decode($response, true) !== null;
    $status = ($httpCode === 200 && $isJson) ? '✅ PASS' : '❌ FAIL';

    echo "{$status} | GET $endpoint | HTTP {$httpCode} | JSON: " . ($isJson ? 'YES' : 'NO') . "\n";

    if ($httpCode === 200 && $isJson) {
        $passed++;
    } else {
        $failed++;
        $errors[] = "GET $endpoint: HTTP $httpCode, JSON: " . ($isJson ? 'yes' : 'no');
    }
}

echo "\n";

// Test POST endpoints
foreach ($apis['POST'] as $endpoint) {
    $url = $baseUrl . $endpoint . $botBypass;
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => json_encode(['csrf_token' => 'bot-bypass']),
        CURLOPT_HTTPHEADER => ['X-Bot-Bypass: 1', 'Content-Type: application/json'],
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $isJson = @json_decode($response, true) !== null;
    $status = ($httpCode === 200 && $isJson) ? '✅ PASS' : '❌ FAIL';

    echo "{$status} | POST $endpoint | HTTP {$httpCode} | JSON: " . ($isJson ? 'YES' : 'NO') . "\n";

    if ($httpCode === 200 && $isJson) {
        $passed++;
    } else {
        $failed++;
        $errors[] = "POST $endpoint: HTTP $httpCode, JSON: " . ($isJson ? 'yes' : 'no');
    }
}

echo "\n╔════════════════════════════════════════════════════════╗\n";
echo "║ RESULTS                                                ║\n";
echo "╚════════════════════════════════════════════════════════╝\n\n";
echo "✅ Passed: $passed/9\n";
echo "❌ Failed: $failed/9\n";

if (!empty($errors)) {
    echo "\nErrors:\n";
    foreach ($errors as $error) {
        echo "  • $error\n";
    }
}

echo "\n";
exit($failed > 0 ? 1 : 0);
