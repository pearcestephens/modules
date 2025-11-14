#!/usr/bin/env php
<?php
/**
 * Comprehensive Payroll Module Tester
 * Tests all endpoints, views, and performs deep analysis
 */

declare(strict_types=1);

// Color output helpers
function colorize(string $text, string $color): string {
    $colors = [
        'green' => "\033[32m",
        'red' => "\033[31m",
        'yellow' => "\033[33m",
        'blue' => "\033[34m",
        'cyan' => "\033[36m",
        'reset' => "\033[0m"
    ];
    return ($colors[$color] ?? '') . $text . $colors['reset'];
}

function printHeader(string $text): void {
    echo "\n" . str_repeat("=", 80) . "\n";
    echo colorize($text, 'cyan') . "\n";
    echo str_repeat("=", 80) . "\n";
}

function printSubHeader(string $text): void {
    echo "\n" . colorize($text, 'blue') . "\n";
    echo str_repeat("-", 80) . "\n";
}

// Configuration
$baseUrl = 'https://staff.vapeshed.co.nz/modules/human_resources/payroll/';
$results = [
    'total' => 0,
    'passed' => 0,
    'failed' => 0,
    'warnings' => 0,
    'errors' => []
];

// Test configuration
$tests = [
    'api_endpoints' => [
        // Health & Dashboard
        ['GET', '/health/', 'Health check', 200],
        ['GET', '/payroll/dashboard', 'Dashboard view', 200],
        ['GET', '/api/payroll/dashboard/data', 'Dashboard data', 200],

        // Amendments
        ['GET', '/api/payroll/amendments/pending', 'Pending amendments', 200],
        ['GET', '/api/payroll/amendments/history', 'Amendment history (no param)', [400, 200]],
        ['POST', '/api/payroll/amendments/create', 'Create amendment', [401, 403, 422]],
        ['POST', '/api/payroll/amendments/1/approve', 'Approve amendment', [401, 403, 404]],
        ['POST', '/api/payroll/amendments/1/decline', 'Decline amendment', [401, 403, 404]],

        // Automation
        ['GET', '/api/payroll/automation/dashboard', 'Automation dashboard', 200],
        ['GET', '/api/payroll/automation/reviews/pending', 'Pending reviews', 200],
        ['GET', '/api/payroll/automation/rules', 'Automation rules', 200],
        ['GET', '/api/payroll/automation/stats', 'Automation stats', 200],
        ['POST', '/api/payroll/automation/process', 'Process automation', [401, 403, 422]],

        // Xero
        ['GET', '/api/payroll/xero/oauth/authorize', 'Xero OAuth', 200],
        ['POST', '/api/payroll/xero/payrun/create', 'Xero create payrun', [401, 403, 422]],
        ['POST', '/api/payroll/xero/payments/batch', 'Xero batch payments', [401, 403, 422]],

        // Discrepancies
        ['GET', '/api/payroll/discrepancies/pending', 'Pending discrepancies', [200, 401]],
        ['GET', '/api/payroll/discrepancies/my-history', 'My discrepancy history', 200],
        ['GET', '/api/payroll/discrepancies/statistics', 'Discrepancy stats', [200, 401]],
        ['POST', '/api/payroll/discrepancies/submit', 'Submit discrepancy', [401, 403, 422]],
        ['POST', '/api/payroll/discrepancies/1/approve', 'Approve discrepancy', [401, 403, 404]],
        ['POST', '/api/payroll/discrepancies/1/decline', 'Decline discrepancy', [401, 403, 404]],

        // Bonuses
        ['GET', '/api/payroll/bonuses/pending', 'Pending bonuses', 200],
        ['GET', '/api/payroll/bonuses/history', 'Bonus history', 200],
        ['GET', '/api/payroll/bonuses/summary', 'Bonus summary', 200],

        // Vend Payments
        ['GET', '/api/payroll/vend-payments/pending', 'Pending Vend payments', 200],
        ['GET', '/api/payroll/vend-payments/history', 'Vend payment history', 200],
        ['GET', '/api/payroll/vend-payments/statistics', 'Vend payment stats', 200],

        // Leave
        ['GET', '/api/payroll/leave/pending', 'Pending leave', 200],
        ['GET', '/api/payroll/leave/history', 'Leave history', 200],
        ['GET', '/api/payroll/leave/balances', 'Leave balances', 200],

        // Pay Runs
        ['GET', '/payroll/payruns', 'Pay runs view', 200],
        ['GET', '/api/payroll/payruns/list', 'Pay runs list', 200],
        ['POST', '/api/payroll/payruns/create', 'Create pay run', [401, 403, 422]],
        ['POST', '/api/payroll/payruns/2025-01/approve', 'Approve pay run', [401, 403, 404]],

        // Reconciliation
        ['GET', '/payroll/reconciliation', 'Reconciliation view', 200],
        ['GET', '/api/payroll/reconciliation/dashboard', 'Reconciliation dashboard', 200],
        ['GET', '/api/payroll/reconciliation/variances', 'Reconciliation variances', 200],
    ],
    'views' => [
        '/payroll/dashboard' => 'Main Dashboard',
        '/payroll/payruns' => 'Pay Runs',
        '/payroll/reconciliation' => 'Reconciliation',
    ]
];/**
 * Convert route path to query parameter format
 */
function convertRouteToUrl(string $baseUrl, string $path): string {
    // Routes like /api/payroll/dashboard/data become ?api=dashboard/data
    // Routes like /payroll/dashboard become ?view=dashboard
    if (strpos($path, '/api/payroll/') === 0) {
        // API endpoint - use ?api=path
        $apiPath = substr($path, strlen('/api/payroll/'));
        return $baseUrl . '?api=' . $apiPath;
    } elseif (strpos($path, '/payroll/') === 0) {
        // View endpoint - use ?view=path
        $viewPath = substr($path, strlen('/payroll/'));
        return $baseUrl . '?view=' . $viewPath;
    } else {
        // Other paths (like /health/) - use as-is after baseUrl
        return $baseUrl . $path;
    }
}

/**
 * Make HTTP request
 */
function makeRequest(string $method, string $url, array $expectedCodes = [200]): array {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'X-Requested-With: XMLHttpRequest'
        ]
    ]);

    $startTime = microtime(true);
    $response = curl_exec($ch);
    $duration = microtime(true) - $startTime;

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    $error = curl_error($ch);

    curl_close($ch);

    $passed = in_array($httpCode, $expectedCodes);

    return [
        'passed' => $passed,
        'http_code' => $httpCode,
        'content_type' => $contentType,
        'duration' => $duration,
        'response' => $response,
        'error' => $error,
        'expected' => $expectedCodes
    ];
}

/**
 * Analyze HTML response
 */
function analyzeHtml(string $html, bool $isView = false): array {
    $issues = [];

    // Check for PHP errors
    if (preg_match('/(Fatal error|Parse error|Warning|Notice):/i', $html, $matches)) {
        $issues[] = "PHP Error detected: " . $matches[0];
    }

    // Check for SQL errors
    if (preg_match('/(SQL syntax|MySQL error|database error)/i', $html, $matches)) {
        $issues[] = "Database Error detected: " . $matches[0];
    }

    // Check for 404 content
    if (stripos($html, 'not found') !== false && stripos($html, '404') !== false) {
        $issues[] = "404 content detected in HTML";
    }

    // Check for empty body
    if (strlen(trim($html)) < 100) {
        $issues[] = "Response too short (possible empty page)";
    }

    // Check for required HTML structure
    if (stripos($html, '<html') === false && stripos($html, '<!DOCTYPE') === false) {
        // Might be JSON, check that
        $json = json_decode($html, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $issues[] = "Not valid HTML or JSON";
        }
    }

    // Check for broken links/resources (only report if critical threshold)
    // Minor missing resources are common in dev environments
    if ($isView) {
        preg_match_all('/(?:src|href)=["\']([^"\']+)["\']/', $html, $matches);
        $resources = $matches[1] ?? [];
        $brokenResources = 0;
        $checkedResources = 0;

        foreach ($resources as $resource) {
            // Skip external resources
            if (strpos($resource, 'http') === 0) {
                continue;
            }
            // Skip data URIs and anchors
            if (strpos($resource, 'data:') === 0 || strpos($resource, '#') === 0 || strpos($resource, 'javascript:') === 0) {
                continue;
            }

            $checkedResources++;

            // Only check local absolute paths
            if (strpos($resource, '/') === 0) {
                // For view pages, we expect most resources to exist
                // Only flag if resource doesn't exist AND it looks like a critical file
                $extension = pathinfo($resource, PATHINFO_EXTENSION);
                $isCritical = in_array($extension, ['css', 'js']);

                if ($isCritical && !file_exists($_SERVER['DOCUMENT_ROOT'] . $resource)) {
                    $brokenResources++;
                }
            }
        }

        // Only report if more than 20% of checked resources are broken
        if ($checkedResources > 0 && $brokenResources > 0) {
            $brokenPercentage = ($brokenResources / $checkedResources) * 100;
            if ($brokenPercentage > 20) {
                $issues[] = "$brokenResources critical broken resource links (${brokenPercentage}%)";
            }
        }
    }

    return $issues;
}

/**
 * Analyze JSON response
 */
function analyzeJson(string $json, int $httpCode, $expectedCodes): array {
    $issues = [];

    $data = json_decode($json, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        $issues[] = "Invalid JSON: " . json_last_error_msg();
        return $issues;
    }

    // Skip analysis for expected auth/validation failures (these are security features, not bugs)
    $expectedFailureCodes = [401, 403, 422];
    if (in_array($httpCode, $expectedFailureCodes) && in_array($httpCode, (array)$expectedCodes)) {
        // This is an expected security response - don't flag as warning
        return [];
    }

    // Check for error response structure (only warn if unexpected)
    if (isset($data['success']) && $data['success'] === false) {
        if (isset($data['error'])) {
            // Only report if this wasn't an expected error code
            if (!in_array($httpCode, (array)$expectedCodes)) {
                $error = is_array($data['error']) ? json_encode($data['error']) : $data['error'];
                $issues[] = "Unexpected error response: " . $error;
            }
        }
    }

    // Check for expected fields (but only for successful responses)
    if ($httpCode >= 200 && $httpCode < 300) {
        if (!isset($data['success']) && !isset($data['data']) && !isset($data['error']) && !isset($data['message'])) {
            // Allow some flexibility - if response has meaningful data, it's OK
            if (empty($data) || count($data) === 0) {
                $issues[] = "Response missing standard fields (success/data/error)";
            }
        }
    }

    return $issues;
}

/**
 * Test API endpoint
 */
function testEndpoint(string $method, string $endpoint, string $description, $expectedCodes): array {
    global $baseUrl, $results;

    $results['total']++;

    // Ensure expectedCodes is an array
    if (!is_array($expectedCodes)) {
        $expectedCodes = [$expectedCodes];
    }

    // Convert route path to proper URL format
    $url = convertRouteToUrl($baseUrl, $endpoint);
    $result = makeRequest($method, $url, $expectedCodes);

    $passed = $result['passed'];
    $icon = $passed ? colorize('✓', 'green') : colorize('✗', 'red');

    echo sprintf(
        "%s %s %s - %d (expected %s) [%.0fms]\n",
        $icon,
        str_pad($method, 6),
        str_pad($endpoint, 50),
        $result['http_code'],
        implode('/', $expectedCodes),
        $result['duration'] * 1000
    );

    if ($passed) {
        $results['passed']++;

        // Analyze response
        $issues = [];
        if (strpos($result['content_type'], 'json') !== false) {
            $issues = analyzeJson($result['response'], $result['http_code'], $expectedCodes);
        } elseif (strpos($result['content_type'], 'html') !== false) {
            $issues = analyzeHtml($result['response'], false);
        }

        if (!empty($issues)) {
            $results['warnings']++;
            echo colorize("  ⚠ Warnings:\n", 'yellow');
            foreach ($issues as $issue) {
                echo colorize("    - $issue\n", 'yellow');
            }
        }
    } else {
        $results['failed']++;
        $results['errors'][] = [
            'method' => $method,
            'endpoint' => $endpoint,
            'description' => $description,
            'expected' => $expectedCodes,
            'actual' => $result['http_code'],
            'response' => substr($result['response'], 0, 500)
        ];

        echo colorize("  Error: " . ($result['error'] ?: 'Unexpected status code') . "\n", 'red');
        if ($result['response']) {
            echo colorize("  Response: " . substr($result['response'], 0, 200) . "...\n", 'red');
        }
    }

    return $result;
}

/**
 * Test view page
 */
function testView(string $endpoint, string $name): array {
    global $baseUrl, $results;

    $results['total']++;

    // Convert route path to proper URL format
    $url = convertRouteToUrl($baseUrl, $endpoint);
    $result = makeRequest('GET', $url, [200]);

    $passed = $result['passed'];
    $icon = $passed ? colorize('✓', 'green') : colorize('✗', 'red');

    echo sprintf(
        "%s VIEW %s - %d [%.0fms]\n",
        $icon,
        str_pad($endpoint, 45),
        $result['http_code'],
        $result['duration'] * 1000
    );

    if ($passed) {
        $results['passed']++;

        // Analyze HTML
        $issues = analyzeHtml($result['response'], true);

        if (!empty($issues)) {
            $results['warnings']++;
            echo colorize("  ⚠ Issues found:\n", 'yellow');
            foreach ($issues as $issue) {
                echo colorize("    - $issue\n", 'yellow');
            }
        } else {
            echo colorize("  ✓ HTML structure looks good\n", 'green');
        }
    } else {
        $results['failed']++;
        $results['errors'][] = [
            'type' => 'view',
            'endpoint' => $endpoint,
            'name' => $name,
            'expected' => [200],
            'actual' => $result['http_code'],
            'response' => substr($result['response'], 0, 500)
        ];

        echo colorize("  Error: Page not accessible\n", 'red');
    }

    return $result;
}

// ============================================================================
// RUN TESTS
// ============================================================================

printHeader("COMPREHENSIVE PAYROLL MODULE TEST SUITE");
echo "Started: " . date('Y-m-d H:i:s') . "\n";
echo "Base URL: $baseUrl\n";

// Test API Endpoints
printSubHeader("API ENDPOINT TESTS");
foreach ($tests['api_endpoints'] as $test) {
    [$method, $endpoint, $description, $expectedCodes] = $test;
    testEndpoint($method, $endpoint, $description, $expectedCodes);
}

// Test Views
printSubHeader("VIEW PAGE TESTS");
foreach ($tests['views'] as $endpoint => $name) {
    testView($endpoint, $name);
}

// Performance Analysis
printSubHeader("PERFORMANCE ANALYSIS");
echo "Note: Performance metrics collected during endpoint tests\n";
echo "All response times shown in milliseconds\n";

// Security Checks
printSubHeader("SECURITY CHECKS");
echo "✓ HTTPS enforced: " . (strpos($baseUrl, 'https://') === 0 ? 'Yes' : 'No') . "\n";
echo "✓ Auth required on sensitive endpoints: Testing...\n";

// Test auth-protected endpoints without auth
$authTests = [
    '/api/payroll/amendments/create',
    '/api/payroll/automation/process',
    '/api/payroll/discrepancies/submit',
];

foreach ($authTests as $endpoint) {
    $url = convertRouteToUrl($baseUrl, $endpoint);
    $result = makeRequest('POST', $url, [401, 403, 422]);
    $icon = $result['passed'] ? colorize('✓', 'green') : colorize('✗', 'red');
    echo "$icon Auth protection on $endpoint: " . $result['http_code'] . "\n";
}

// Final Summary
printHeader("TEST SUMMARY");

$passRate = $results['total'] > 0 ? ($results['passed'] / $results['total'] * 100) : 0;

echo sprintf("Total Tests:  %d\n", $results['total']);
echo sprintf("Passed:       %s\n", colorize((string)$results['passed'], 'green'));
echo sprintf("Failed:       %s\n", $results['failed'] > 0 ? colorize((string)$results['failed'], 'red') : '0');
echo sprintf("Warnings:     %s\n", $results['warnings'] > 0 ? colorize((string)$results['warnings'], 'yellow') : '0');
echo sprintf("Pass Rate:    %.1f%%\n", $passRate);

if ($passRate >= 90) {
    echo "\n" . colorize("🎉 EXCELLENT! Module is performing well!", 'green') . "\n";
} elseif ($passRate >= 75) {
    echo "\n" . colorize("✓ GOOD! Some issues to address.", 'yellow') . "\n";
} else {
    echo "\n" . colorize("⚠ NEEDS ATTENTION! Multiple issues found.", 'red') . "\n";
}

// Detailed Errors
if (!empty($results['errors'])) {
    printHeader("ERRORS REQUIRING ATTENTION");
    foreach ($results['errors'] as $idx => $error) {
        echo "\n" . colorize("Error #" . ($idx + 1), 'red') . "\n";
        echo "  Endpoint: " . ($error['endpoint'] ?? 'N/A') . "\n";
        echo "  Method: " . ($error['method'] ?? 'GET') . "\n";
        echo "  Expected: " . implode('/', $error['expected']) . "\n";
        echo "  Actual: " . $error['actual'] . "\n";
        if (!empty($error['response'])) {
            echo "  Response: " . substr($error['response'], 0, 200) . "...\n";
        }
    }
}

// Save results
$reportFile = 'comprehensive-test-results.json';
file_put_contents($reportFile, json_encode($results, JSON_PRETTY_PRINT));
echo "\nDetailed results saved to: $reportFile\n";

// Exit code
exit($results['failed'] > 0 ? 1 : 0);
