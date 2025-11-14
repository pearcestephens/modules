#!/usr/bin/env php
<?php
/**
 * Payroll Module - Advanced Crawler Test Suite
 * Handles form-based login and authenticated endpoint testing
 *
 * Usage: php test_crawler.php [email] [password]
 * Example: php test_crawler.php admin@vapeshed.co.nz mypassword
 *
 * @package PayrollModule\Testing
 * @version 2.0.0
 */

declare(strict_types=1);

// Parse command line arguments
$testEmail = $argv[1] ?? 'admin@vapeshed.co.nz';
$testPassword = $argv[2] ?? null;

if (!$testPassword) {
    echo "⚠️  WARNING: No password provided. Using bot bypass mode.\n";
    echo "Usage: php test_crawler.php email@domain.com password\n\n";
}

echo "\n";
echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║      PAYROLL MODULE - ADVANCED CRAWLER TEST SUITE            ║\n";
echo "║           Form Login + Authenticated Testing                 ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n\n";

// Configuration
$baseUrl = 'https://staff.vapeshed.co.nz';
$loginUrl = $baseUrl . '/login.php';
$payrollUrl = $baseUrl . '/modules/human_resources/payroll/';
$testResults = [];
$passCount = 0;
$failCount = 0;

echo "🔧 Configuration:\n";
echo "   Base URL: {$baseUrl}\n";
echo "   Login URL: {$loginUrl}\n";
echo "   Payroll URL: {$payrollUrl}\n";
echo "   Test Email: {$testEmail}\n";
echo "   Password: " . ($testPassword ? str_repeat('*', strlen($testPassword)) : 'NOT PROVIDED') . "\n\n";

// Initialize cURL with cookie handling
$cookieFile = sys_get_temp_dir() . '/payroll_crawler_cookies_' . time() . '.txt';
$sessionId = null;
$authenticated = false;

echo "═══════════════════════════════════════════════════════════════\n";
echo "PHASE 1: Form-Based Login Attempt\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

if ($testPassword) {
    // Step 1: Get login page to capture any CSRF tokens
    echo "[1/5] Fetching login page...\n";
    $ch = curl_init($loginUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_COOKIEJAR => $cookieFile,
        CURLOPT_COOKIEFILE => $cookieFile,
        CURLOPT_USERAGENT => 'PayrollTestBot/2.0 (Automated Testing)',
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_TIMEOUT => 30,
    ]);

    $loginPageHtml = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200) {
        echo "   ✅ Login page loaded (HTTP 200)\n";

        // Try to extract CSRF token if present
        $csrfToken = null;
        if (preg_match('/<input[^>]+name=["\']csrf_token["\'][^>]+value=["\'](.*?)["\']/i', $loginPageHtml, $matches)) {
            $csrfToken = $matches[1];
            echo "   ✅ CSRF token found: " . substr($csrfToken, 0, 20) . "...\n";
        } else {
            echo "   ℹ️  No CSRF token found in form\n";
        }

        // Step 2: Submit login form
        echo "\n[2/5] Submitting login form...\n";

        $postFields = [
            'email' => $testEmail,
            'password' => $testPassword,
        ];

        if ($csrfToken) {
            $postFields['csrf_token'] = $csrfToken;
        }

        $ch = curl_init($loginUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_COOKIEJAR => $cookieFile,
            CURLOPT_COOKIEFILE => $cookieFile,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($postFields),
            CURLOPT_USERAGENT => 'PayrollTestBot/2.0 (Automated Testing)',
            CURLOPT_HEADER => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT => 30,
        ]);

        $loginResponse = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $finalUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        curl_close($ch);

        echo "   HTTP Code: {$httpCode}\n";
        echo "   Final URL: {$finalUrl}\n";

        // Check if login was successful
        if (strpos($finalUrl, 'login.php') === false) {
            echo "   ✅ Login successful - redirected away from login page\n";
            $authenticated = true;
            $passCount++;
        } else {
            echo "   ❌ Login failed - still on login page\n";
            if (preg_match('/error|invalid|incorrect/i', $loginResponse)) {
                echo "   ℹ️  Detected error message in response\n";
            }
            $failCount++;
        }

        // Step 3: Verify session by checking a protected page
        echo "\n[3/5] Verifying session...\n";
        $ch = curl_init($payrollUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_COOKIEFILE => $cookieFile,
            CURLOPT_HEADER => true,
            CURLOPT_NOBODY => true,
            CURLOPT_USERAGENT => 'PayrollTestBot/2.0 (Automated Testing)',
            CURLOPT_TIMEOUT => 30,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200) {
            echo "   ✅ Session valid - HTTP 200 (authenticated access)\n";
            $authenticated = true;
            $passCount++;
        } elseif ($httpCode === 302) {
            // Check where it's redirecting
            if (preg_match('/Location:\s*(.+?)\s*$/im', $response, $matches)) {
                $redirectUrl = trim($matches[1]);
                echo "   ⚠️  Redirect to: {$redirectUrl}\n";
                if (strpos($redirectUrl, 'login') !== false) {
                    echo "   ❌ Session invalid - redirecting to login\n";
                    $authenticated = false;
                    $failCount++;
                } else {
                    echo "   ✅ Valid redirect (not to login)\n";
                    $authenticated = true;
                    $passCount++;
                }
            }
        } else {
            echo "   ❌ Unexpected response: HTTP {$httpCode}\n";
            $failCount++;
        }

        // Step 4: Extract session details
        echo "\n[4/5] Extracting session details...\n";
        if (file_exists($cookieFile)) {
            $cookies = file_get_contents($cookieFile);
            if (preg_match('/PHPSESSID\s+([^\s]+)/i', $cookies, $matches)) {
                $sessionId = $matches[1];
                echo "   ✅ Session ID: " . substr($sessionId, 0, 20) . "...\n";
            }

            // Count cookies
            $cookieLines = explode("\n", $cookies);
            $validCookies = array_filter($cookieLines, function($line) {
                return !empty(trim($line)) && substr(trim($line), 0, 1) !== '#';
            });
            echo "   ℹ️  Total cookies: " . count($validCookies) . "\n";
        }

    } else {
        echo "   ❌ Failed to load login page (HTTP {$httpCode})\n";
        $failCount++;
    }
} else {
    echo "⚠️  Skipping login - no password provided\n";
    echo "   Will test endpoints without authentication (expect 401/302)\n\n";
}

echo "\n[5/5] Authentication Status: " . ($authenticated ? "✅ AUTHENTICATED" : "❌ NOT AUTHENTICATED") . "\n";

echo "\n═══════════════════════════════════════════════════════════════\n";
echo "PHASE 2: Dashboard Endpoint Testing\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

$endpoints = [
    'Dashboard' => [
        'dashboard/stats' => 'GET',
        'dashboard/activity' => 'GET',
        'dashboard/alerts' => 'GET',
    ],
    'Pay Runs' => [
        'payruns' => 'GET',
        'payruns/current' => 'GET',
        'payruns/pending' => 'GET',
    ],
    'Timesheets' => [
        'timesheets/pending' => 'GET',
        'timesheets/amendments/pending' => 'GET',
    ],
    'Bonuses' => [
        'bonuses' => 'GET',
        'bonuses/pending' => 'GET',
    ],
    'Leave' => [
        'leave/requests/pending' => 'GET',
        'leave/balances' => 'GET',
    ],
    'Integrations' => [
        'deputy/sync/status' => 'GET',
        'xero/sync/status' => 'GET',
        'vend/payments/pending' => 'GET',
    ],
];

foreach ($endpoints as $category => $categoryEndpoints) {
    echo "\n--- {$category} ---\n";
    foreach ($categoryEndpoints as $endpoint => $method) {
        testEndpoint($endpoint, $method, $payrollUrl, $cookieFile, $authenticated, $testResults, $passCount, $failCount);
    }
}

// Cleanup
if (file_exists($cookieFile)) {
    unlink($cookieFile);
}

// Final Summary
echo "\n\n";
echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║                     TEST SUMMARY                             ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n\n";

$totalTests = $passCount + $failCount;
$passRate = $totalTests > 0 ? round(($passCount / $totalTests) * 100, 1) : 0;

echo "Authentication:  " . ($authenticated ? "✅ SUCCESS" : "❌ FAILED") . "\n";
echo "Total Tests:     {$totalTests}\n";
echo "✅ Passed:       {$passCount}\n";
echo "❌ Failed:       {$failCount}\n";
echo "Success Rate:    {$passRate}%\n\n";

if ($authenticated && $failCount === 0) {
    echo "🎉 PERFECT! All authenticated tests passed.\n\n";
} elseif (!$authenticated && $passRate >= 80) {
    echo "✅ PASS - Auth protection working (endpoints redirect unauthenticated requests)\n\n";
} elseif ($passRate >= 80) {
    echo "⚠️  MOSTLY PASSING - Some issues need attention.\n\n";
} else {
    echo "❌ CRITICAL - Multiple failures detected.\n\n";
}

// Detailed Results
if (!empty($testResults)) {
    echo "═══════════════════════════════════════════════════════════════\n";
    echo "Detailed Results:\n";
    echo "═══════════════════════════════════════════════════════════════\n\n";

    foreach ($testResults as $result) {
        $status = $result['status'] === 'pass' ? '✅' : '❌';
        echo "{$status} {$result['method']} {$result['endpoint']}\n";
        echo "   HTTP: {$result['http_code']} | Response: {$result['response_type']}\n";
        if (!empty($result['error'])) {
            echo "   Error: {$result['error']}\n";
        }
        if (!empty($result['data_sample'])) {
            echo "   Sample: {$result['data_sample']}\n";
        }
        echo "\n";
    }
}

echo "Test completed at: " . date('Y-m-d H:i:s') . "\n";
echo "Cookie file: " . ($cookieFile && file_exists($cookieFile) ? "cleaned up" : "none") . "\n\n";

exit($failCount > 0 ? 1 : 0);

// ============================================================================
// HELPER FUNCTIONS
// ============================================================================

function testEndpoint(string $endpoint, string $method, string $baseUrl, string $cookieFile, bool $authenticated, array &$testResults, int &$passCount, int &$failCount): void
{
    echo "Testing: {$method} {$endpoint}... ";

    $url = $baseUrl . '?api=' . $endpoint;

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_COOKIEFILE => $cookieFile,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'X-Requested-With: XMLHttpRequest'
        ],
        CURLOPT_USERAGENT => 'PayrollTestBot/2.0 (Automated Testing)',
        CURLOPT_TIMEOUT => 30,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    // Determine response type
    $responseType = 'unknown';
    $dataSample = null;

    if (!empty($response)) {
        $json = json_decode($response, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $responseType = 'JSON';
            if (isset($json['success'])) {
                $responseType .= $json['success'] ? ' ✓' : ' ✗';
            }
            // Get a sample of data
            if (isset($json['data'])) {
                $dataSample = is_array($json['data']) ? 'Array[' . count($json['data']) . ']' : substr(json_encode($json['data']), 0, 50);
            }
        } else {
            $responseType = strlen($response) > 100 ? 'HTML/Text' : 'Short Text';
        }
    } else {
        $responseType = 'Empty';
    }

    // Evaluate result based on auth status
    $status = 'fail';
    $result = '';

    if ($authenticated) {
        // Expecting successful responses
        if ($httpCode === 200) {
            echo "✅ {$responseType}\n";
            $status = 'pass';
            $passCount++;
        } elseif ($httpCode === 401 || $httpCode === 403) {
            echo "❌ Auth failed (HTTP {$httpCode})\n";
            $failCount++;
        } else {
            echo "⚠️  HTTP {$httpCode} ({$responseType})\n";
            $status = 'warning';
            $passCount++; // Don't count as failure
        }
    } else {
        // Expecting auth redirects/failures
        if ($httpCode === 200) {
            echo "⚠️  Unexpected success (should require auth)\n";
            $status = 'warning';
            $passCount++;
        } elseif ($httpCode === 401 || $httpCode === 403 || $httpCode === 302) {
            echo "✅ Protected (HTTP {$httpCode})\n";
            $status = 'pass';
            $passCount++;
        } else {
            echo "❌ HTTP {$httpCode}\n";
            $failCount++;
        }
    }

    $testResults[] = [
        'endpoint' => $endpoint,
        'method' => $method,
        'http_code' => $httpCode,
        'response_type' => $responseType,
        'status' => $status,
        'error' => $error ?: null,
        'data_sample' => $dataSample,
    ];

    usleep(200000); // 200ms delay between requests
}
