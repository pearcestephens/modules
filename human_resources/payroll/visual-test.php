#!/usr/bin/env php
<?php
/**
 * Visual Page Analyzer
 * Tests actual page rendering and checks for visual/functional issues
 */

declare(strict_types=1);

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

function testPageRendering(string $url, string $name): array {
    echo "\n" . colorize("Testing: $name", 'cyan') . "\n";
    echo str_repeat('-', 80) . "\n";

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_TIMEOUT => 10,
    ]);

    $html = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $issues = [];
    $checks = [];

    // Check 1: HTTP Status
    if ($httpCode === 200) {
        $checks[] = [true, "HTTP Status: 200 OK"];
    } else {
        $checks[] = [false, "HTTP Status: $httpCode"];
        $issues[] = "Unexpected HTTP status: $httpCode";
    }

    // Check 2: HTML Structure
    if (stripos($html, '<!DOCTYPE') !== false || stripos($html, '<html') !== false) {
        $checks[] = [true, "Valid HTML structure"];
    } else {
        $checks[] = [false, "Invalid HTML structure"];
        $issues[] = "Missing DOCTYPE or HTML tag";
    }

    // Check 3: Title
    if (preg_match('/<title>([^<]+)<\/title>/', $html, $matches)) {
        $checks[] = [true, "Page title: " . $matches[1]];
    } else {
        $checks[] = [false, "Missing page title"];
        $issues[] = "No title tag found";
    }

    // Check 4: Bootstrap
    if (stripos($html, 'bootstrap') !== false) {
        $checks[] = [true, "Bootstrap framework loaded"];
    } else {
        $checks[] = [false, "Bootstrap not detected"];
        $issues[] = "Bootstrap CSS/JS not found";
    }

    // Check 5: No PHP Errors
    if (!preg_match('/(Fatal error|Parse error|Warning|Notice):/i', $html)) {
        $checks[] = [true, "No PHP errors"];
    } else {
        preg_match('/(Fatal error|Parse error|Warning|Notice):[^\n]+/', $html, $errorMatch);
        $checks[] = [false, "PHP errors detected"];
        $issues[] = "PHP Error: " . ($errorMatch[0] ?? 'Unknown error');
    }

    // Check 6: No SQL Errors
    if (!preg_match('/(SQL syntax|MySQL error|database error)/i', $html)) {
        $checks[] = [true, "No SQL errors"];
    } else {
        $checks[] = [false, "SQL errors detected"];
        $issues[] = "Database error found in HTML";
    }

    // Check 7: Content Length
    $contentLength = strlen($html);
    if ($contentLength > 1000) {
        $checks[] = [true, "Content length: " . number_format($contentLength) . " bytes"];
    } else {
        $checks[] = [false, "Content too short: " . $contentLength . " bytes"];
        $issues[] = "Page content suspiciously short";
    }

    // Check 8: Navigation
    if (stripos($html, 'nav') !== false || stripos($html, 'navbar') !== false) {
        $checks[] = [true, "Navigation present"];
    } else {
        $checks[] = [false, "No navigation found"];
        $issues[] = "Missing navigation elements";
    }

    // Check 9: JavaScript
    if (stripos($html, '<script') !== false) {
        preg_match_all('/<script[^>]*src=["\']([^"\']+)/', $html, $scripts);
        $scriptCount = count($scripts[1]);
        $checks[] = [true, "JavaScript: $scriptCount external scripts"];
    } else {
        $checks[] = [false, "No JavaScript found"];
    }

    // Check 10: CSS
    if (stripos($html, '<link') !== false) {
        preg_match_all('/<link[^>]*href=["\']([^"\']+\.css)/', $html, $styles);
        $styleCount = count($styles[1]);
        $checks[] = [true, "Stylesheets: $styleCount external CSS files"];
    } else {
        $checks[] = [false, "No external stylesheets"];
    }

    // Print results
    foreach ($checks as [$passed, $message]) {
        $icon = $passed ? colorize('✓', 'green') : colorize('✗', 'red');
        echo "$icon $message\n";
    }

    if (!empty($issues)) {
        echo "\n" . colorize("⚠ Issues Found:", 'yellow') . "\n";
        foreach ($issues as $issue) {
            echo "  • $issue\n";
        }
    }

    $passedCount = count(array_filter($checks, fn($c) => $c[0]));
    $totalCount = count($checks);
    $passRate = ($passedCount / $totalCount) * 100;

    echo "\n" . colorize("Score: $passedCount/$totalCount (" . number_format($passRate, 1) . "%)",
        $passRate >= 90 ? 'green' : ($passRate >= 70 ? 'yellow' : 'red')) . "\n";

    return [
        'passed' => $passedCount,
        'total' => $totalCount,
        'pass_rate' => $passRate,
        'issues' => $issues,
        'http_code' => $httpCode
    ];
}

// Test pages
$baseUrl = 'https://staff.vapeshed.co.nz/modules/human_resources/payroll/';
$pages = [
    '?view=dashboard' => 'Payroll Dashboard',
    '?view=payruns' => 'Pay Runs',
    '?view=reconciliation' => 'Reconciliation',
    '?api=dashboard/data' => 'Dashboard API',
    '?api=amendments/pending' => 'Amendments API',
    '?api=automation/dashboard' => 'Automation API',
];

echo colorize("
================================================================================
VISUAL PAGE ANALYZER
================================================================================
", 'cyan');

$results = [];
foreach ($pages as $path => $name) {
    $url = $baseUrl . $path;
    $result = testPageRendering($url, $name);
    $results[] = [
        'name' => $name,
        'url' => $url,
        'result' => $result
    ];
}

// Summary
echo "\n";
echo colorize("
================================================================================
SUMMARY
================================================================================
", 'cyan');

$totalPages = count($results);
$fullyPassing = count(array_filter($results, fn($r) => $r['result']['pass_rate'] >= 90));
$partiallyPassing = count(array_filter($results, fn($r) => $r['result']['pass_rate'] >= 70 && $r['result']['pass_rate'] < 90));
$failing = count(array_filter($results, fn($r) => $r['result']['pass_rate'] < 70));

echo "Total Pages Tested: $totalPages\n";
echo colorize("Fully Passing (≥90%): $fullyPassing", 'green') . "\n";
echo colorize("Partially Passing (70-89%): $partiallyPassing", 'yellow') . "\n";
echo colorize("Failing (<70%): $failing", $failing > 0 ? 'red' : 'green') . "\n";

if ($fullyPassing === $totalPages) {
    echo "\n" . colorize("🎉 ALL PAGES RENDERING PERFECTLY!", 'green') . "\n";
} elseif ($failing === 0) {
    echo "\n" . colorize("✓ All pages rendering well with minor issues", 'yellow') . "\n";
} else {
    echo "\n" . colorize("⚠ Some pages need attention", 'red') . "\n";
}

echo "\n";
exit($failing > 0 ? 1 : 0);
