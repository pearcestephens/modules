#!/usr/bin/env php
<?php
/**
 * Module Directory Web Page Crawler & Health Checker
 *
 * Scans all PHP files in /modules/ directory and tests HTTP accessibility
 * Reports: 200 OK, 404 Not Found, 500 Server Error, and other issues
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
set_time_limit(0);

$baseDir = '/home/master/applications/jcepnzzkmj/public_html/modules';
$baseUrl = 'https://staff.vapeshed.co.nz/modules';

// Patterns to exclude (libraries, services, config files that aren't meant to be accessed directly)
$excludePatterns = [
    '/lib/',
    '/services/',
    '/includes/',
    '/middleware/',
    '/dao/',
    '/models/',
    '/Contracts/',
    '/bootstrap.php',
    '/config.php',
    '/autoload.php',
    '/routes.php',
    '/composer',
    '/vendor/',
    '/_archive/',
    '/_templates/',
    '/migrations/',
    '/tests/',
    '/Test.php',
    'test-',
    'test_',
    '/cli/',
    '/cron/',
    '/node_modules/',
    '/.git/',
];

// Find all PHP files
echo "🔍 Scanning for PHP files...\n";
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($baseDir, RecursiveDirectoryIterator::SKIP_DOTS)
);

$phpFiles = [];
foreach ($iterator as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        $phpFiles[] = $file->getPathname();
    }
}

echo "✅ Found " . count($phpFiles) . " PHP files\n\n";

// Filter out excluded patterns
$accessiblePages = [];
foreach ($phpFiles as $file) {
    $skip = false;
    foreach ($excludePatterns as $pattern) {
        if (stripos($file, $pattern) !== false) {
            $skip = true;
            break;
        }
    }

    if (!$skip) {
        $accessiblePages[] = $file;
    }
}

echo "📄 Testing " . count($accessiblePages) . " accessible pages...\n";
echo str_repeat('=', 80) . "\n\n";

$results = [
    'success' => [],
    '404' => [],
    '500' => [],
    'redirect' => [],
    'error' => [],
    'timeout' => []
];

foreach ($accessiblePages as $file) {
    // Convert file path to URL
    $relativePath = str_replace($baseDir, '', $file);
    $url = $baseUrl . $relativePath;

    echo "Testing: $url\n";

    // Initialize cURL
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HEADER => true,
        CURLOPT_NOBODY => true, // HEAD request for speed
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    // Categorize response
    if ($error) {
        if (stripos($error, 'timeout') !== false) {
            $results['timeout'][] = ['url' => $url, 'error' => $error];
            echo "  ⏱️  TIMEOUT: $error\n";
        } else {
            $results['error'][] = ['url' => $url, 'error' => $error];
            echo "  ❌ ERROR: $error\n";
        }
    } elseif ($httpCode >= 200 && $httpCode < 300) {
        $results['success'][] = $url;
        echo "  ✅ $httpCode OK\n";
    } elseif ($httpCode >= 300 && $httpCode < 400) {
        $results['redirect'][] = ['url' => $url, 'code' => $httpCode];
        echo "  ↗️  $httpCode REDIRECT\n";
    } elseif ($httpCode == 404) {
        $results['404'][] = $url;
        echo "  🔴 404 NOT FOUND\n";
    } elseif ($httpCode >= 500) {
        $results['500'][] = $url;
        echo "  💥 $httpCode SERVER ERROR\n";
    } else {
        $results['error'][] = ['url' => $url, 'code' => $httpCode];
        echo "  ⚠️  $httpCode\n";
    }

    echo "\n";
    usleep(100000); // 100ms delay to avoid overwhelming server
}

// Summary Report
echo "\n";
echo str_repeat('=', 80) . "\n";
echo "📊 SUMMARY REPORT\n";
echo str_repeat('=', 80) . "\n\n";

echo "✅ SUCCESS (200-299): " . count($results['success']) . " pages\n";
echo "↗️  REDIRECTS (300-399): " . count($results['redirect']) . " pages\n";
echo "🔴 NOT FOUND (404): " . count($results['404']) . " pages\n";
echo "💥 SERVER ERRORS (500+): " . count($results['500']) . " pages\n";
echo "⚠️  OTHER ERRORS: " . count($results['error']) . " pages\n";
echo "⏱️  TIMEOUTS: " . count($results['timeout']) . " pages\n\n";

// Detailed Reports
if (!empty($results['404'])) {
    echo "\n🔴 404 NOT FOUND PAGES:\n";
    echo str_repeat('-', 80) . "\n";
    foreach ($results['404'] as $url) {
        echo "  - $url\n";
    }
}

if (!empty($results['500'])) {
    echo "\n💥 500 SERVER ERROR PAGES:\n";
    echo str_repeat('-', 80) . "\n";
    foreach ($results['500'] as $url) {
        echo "  - $url\n";
    }
}

if (!empty($results['error'])) {
    echo "\n⚠️  OTHER ERRORS:\n";
    echo str_repeat('-', 80) . "\n";
    foreach ($results['error'] as $item) {
        $url = is_array($item) ? $item['url'] : $item;
        $detail = is_array($item) && isset($item['code']) ? " (HTTP {$item['code']})" : '';
        $detail .= is_array($item) && isset($item['error']) ? " - {$item['error']}" : '';
        echo "  - $url$detail\n";
    }
}

if (!empty($results['redirect'])) {
    echo "\n↗️  REDIRECTS (may need authentication):\n";
    echo str_repeat('-', 80) . "\n";
    foreach ($results['redirect'] as $item) {
        echo "  - {$item['url']} (HTTP {$item['code']})\n";
    }
}

// Export results to JSON
$jsonFile = $baseDir . '/health-check-report.json';
file_put_contents($jsonFile, json_encode($results, JSON_PRETTY_PRINT));
echo "\n📄 Full report saved to: $jsonFile\n";

// Exit with error code if broken pages found
$brokenCount = count($results['404']) + count($results['500']);
if ($brokenCount > 0) {
    echo "\n⚠️  FOUND $brokenCount BROKEN PAGES!\n";
    exit(1);
} else {
    echo "\n✅ ALL PAGES HEALTHY!\n";
    exit(0);
}
