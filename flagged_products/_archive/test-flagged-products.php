<?php
/**
 * Test script for Flagged Products v2
 * Tests all components and APIs
 */

echo "=== FLAGGED PRODUCTS V2 TEST SUITE ===\n\n";

// Test 1: Page Load with Bot Bypass
echo "Test 1: Page Load with Bot Bypass\n";
$url = "https://staff.vapeshed.co.nz/flagged-products-v2.php?outlet_id=02dcd191-ae2b-11e6-f485-8eceed6eeafb&bot=1";
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
curl_setopt($ch, CURLOPT_HEADER, true);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode == 200) {
    echo "✅ PASS: Page loads successfully (HTTP 200)\n";
    if (strpos($response, 'X-Bot-Bypass: 1') !== false) {
        echo "✅ PASS: Bot bypass header present\n";
    } else {
        echo "⚠️  WARNING: Bot bypass header not found\n";
    }
} elseif ($httpCode == 302) {
    echo "❌ FAIL: Page redirects (HTTP 302) - auth not bypassed\n";
} else {
    echo "❌ FAIL: HTTP $httpCode\n";
}

// Test 2: Check for JavaScript Errors
echo "\nTest 2: JavaScript Files\n";
$jsFiles = [
    '/modules/flagged_products/assets/js/anti-cheat.js',
    '/modules/flagged_products/assets/js/flagged-products.js'
];

foreach ($jsFiles as $file) {
    $url = "https://staff.vapeshed.co.nz" . $file;
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode == 200) {
        echo "✅ PASS: $file exists\n";
    } else {
        echo "❌ FAIL: $file not found (HTTP $httpCode)\n";
    }
}

// Test 3: API Endpoint Tests
echo "\nTest 3: API Endpoints\n";
$apis = [
    '/modules/flagged_products/api/report-violation.php' => 'POST',
    '/modules/flagged_products/functions/api.php?action=complete' => 'POST'
];

foreach ($apis as $endpoint => $method) {
    $url = "https://staff.vapeshed.co.nz" . $endpoint;
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    if ($method == 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
    }
    curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    // 401 is expected without auth, we just want to check it's not 404/500
    if ($httpCode == 401 || $httpCode == 400 || $httpCode == 200) {
        echo "✅ PASS: $endpoint responds (HTTP $httpCode)\n";
    } elseif ($httpCode == 500) {
        echo "❌ FAIL: $endpoint has server error (HTTP 500)\n";
    } elseif ($httpCode == 404) {
        echo "❌ FAIL: $endpoint not found (HTTP 404)\n";
    } else {
        echo "⚠️  WARNING: $endpoint returns HTTP $httpCode\n";
    }
}

// Test 4: CSS File
echo "\nTest 4: CSS File\n";
$cssUrl = "https://staff.vapeshed.co.nz/modules/flagged_products/assets/css/flagged-products.css";
$ch = curl_init($cssUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_NOBODY, true);
curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode == 200) {
    echo "✅ PASS: CSS file exists\n";
} else {
    echo "❌ FAIL: CSS file not found (HTTP $httpCode)\n";
}

echo "\n=== TEST SUITE COMPLETE ===\n";
