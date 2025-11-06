#!/usr/bin/env php
<?php
/**
 * Comprehensive Page Scanner for Staff Accounts Module
 * Tests all pages and APIs, reports status codes and errors
 */

// ANSI Color codes for terminal output
define('GREEN', "\033[0;32m");
define('RED', "\033[0;31m");
define('YELLOW', "\033[1;33m");
define('BLUE', "\033[0;34m");
define('NC', "\033[0m"); // No Color

$baseUrl = 'https://staff.vapeshed.co.nz/modules/staff-accounts';
$errors = [];
$warnings = [];
$success = [];

// Pages to test
$pages = [
    // Main pages
    'index.php' => ['method' => 'GET', 'auth' => true],

    // View pages
    'views/apply-payments.php' => ['method' => 'GET', 'auth' => true],
    'views/make-payment.php' => ['method' => 'GET', 'auth' => true],
    'views/my-account.php' => ['method' => 'GET', 'auth' => true],
    'views/payment-success.php' => ['method' => 'GET', 'auth' => true],
    'views/staff-list.php' => ['method' => 'GET', 'auth' => true],

    // API endpoints (GET)
    'api/customer-search.php?q=test' => ['method' => 'GET', 'auth' => true],
    'api/auto-match-suggestions.php' => ['method' => 'GET', 'auth' => true],
    'api/manager-dashboard.php' => ['method' => 'GET', 'auth' => true],
    'api/employee-mapping.php' => ['method' => 'GET', 'auth' => true],
    'api/staff-reconciliation.php' => ['method' => 'GET', 'auth' => true],
];

echo "\n";
echo BLUE . "==========================================\n" . NC;
echo BLUE . "  Staff Accounts Module - Page Scanner\n" . NC;
echo BLUE . "==========================================\n" . NC;
echo "\n";

foreach ($pages as $page => $config) {
    $url = $baseUrl . '/' . $page;
    echo "Testing: " . YELLOW . $page . NC . "\n";
    echo "  URL: $url\n";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_NOBODY, false); // Get full response to check for errors
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    // Add cookies for authentication (if we have a session)
    $cookieFile = '/tmp/staff_accounts_cookies.txt';
    if (file_exists($cookieFile)) {
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $headers = substr($response, 0, $headerSize);
    $body = substr($response, $headerSize);

    curl_close($ch);

    // Check for PHP errors in response
    $hasPhpError = false;
    $errorMessage = '';

    if (preg_match('/Fatal error:(.*?)in/is', $body, $matches)) {
        $hasPhpError = true;
        $errorMessage = trim($matches[1]);
    } elseif (preg_match('/Parse error:(.*?)in/is', $body, $matches)) {
        $hasPhpError = true;
        $errorMessage = trim($matches[1]);
    } elseif (preg_match('/Warning:(.*?)in/is', $body, $matches)) {
        $hasPhpError = true;
        $errorMessage = trim($matches[1]);
    } elseif (preg_match('/SQLSTATE\[.*?\]:(.*?)(\n|$)/is', $body, $matches)) {
        $hasPhpError = true;
        $errorMessage = 'SQL Error: ' . trim($matches[1]);
    }

    // Determine status
    if ($httpCode == 200 && !$hasPhpError) {
        echo "  Status: " . GREEN . "✓ 200 OK" . NC . "\n";
        $success[] = $page;
    } elseif ($httpCode == 200 && $hasPhpError) {
        echo "  Status: " . RED . "✗ 200 but has PHP error" . NC . "\n";
        echo "  Error: " . RED . $errorMessage . NC . "\n";
        $errors[] = [
            'page' => $page,
            'code' => $httpCode,
            'error' => $errorMessage,
            'type' => 'PHP_ERROR'
        ];
    } elseif ($httpCode == 302 || $httpCode == 301) {
        // Check if redirect is to login
        if (preg_match('/Location:\s*(.+?)(login|signin)/i', $headers)) {
            echo "  Status: " . YELLOW . "⚠ $httpCode Redirect (Auth required)" . NC . "\n";
            $warnings[] = [
                'page' => $page,
                'code' => $httpCode,
                'message' => 'Requires authentication'
            ];
        } else {
            echo "  Status: " . YELLOW . "⚠ $httpCode Redirect" . NC . "\n";
            $warnings[] = [
                'page' => $page,
                'code' => $httpCode,
                'message' => 'Redirects to another page'
            ];
        }
    } else {
        echo "  Status: " . RED . "✗ $httpCode Error" . NC . "\n";
        $errors[] = [
            'page' => $page,
            'code' => $httpCode,
            'error' => $hasPhpError ? $errorMessage : 'HTTP Error',
            'type' => 'HTTP_ERROR'
        ];
    }

    echo "\n";
}

// Summary
echo BLUE . "==========================================\n" . NC;
echo BLUE . "  Summary\n" . NC;
echo BLUE . "==========================================\n" . NC;
echo "\n";

echo GREEN . "✓ Success: " . count($success) . " pages" . NC . "\n";
if (count($success) > 0) {
    foreach ($success as $page) {
        echo "  - $page\n";
    }
}
echo "\n";

if (count($warnings) > 0) {
    echo YELLOW . "⚠ Warnings: " . count($warnings) . " pages" . NC . "\n";
    foreach ($warnings as $warning) {
        echo "  - {$warning['page']} ({$warning['code']}) - {$warning['message']}\n";
    }
    echo "\n";
}

if (count($errors) > 0) {
    echo RED . "✗ Errors: " . count($errors) . " pages" . NC . "\n";
    foreach ($errors as $error) {
        echo "  - {$error['page']} ({$error['code']})\n";
        echo "    {$error['error']}\n";
    }
    echo "\n";

    echo RED . "==========================================\n" . NC;
    echo RED . "  ERRORS DETECTED - MANUAL FIX NEEDED\n" . NC;
    echo RED . "==========================================\n" . NC;
    exit(1);
} else {
    echo GREEN . "==========================================\n" . NC;
    echo GREEN . "  ALL PAGES OK!\n" . NC;
    echo GREEN . "==========================================\n" . NC;
    exit(0);
}
