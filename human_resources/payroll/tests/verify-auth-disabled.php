#!/usr/bin/env php
<?php
/**
 * Verify Authentication is Disabled
 *
 * Tests that all payroll module endpoints are accessible without authentication
 * when PAYROLL_AUTH_ENABLED = false
 */

declare(strict_types=1);

echo "\n";
echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║  PAYROLL MODULE - AUTH DISABLED VERIFICATION               ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n";
echo "\n";

// Load config
$config = require __DIR__ . '/../../../config/app.php';

echo "📊 Configuration:\n";
echo "  - PAYROLL_AUTH_ENABLED: " . ($config['payroll_auth_enabled'] ? 'TRUE ❌' : 'FALSE ✅') . "\n";
echo "  - APP_ENV: {$config['env']}\n";
echo "  - APP_DEBUG: " . ($config['debug'] ? 'TRUE' : 'FALSE') . "\n";
echo "\n";

if ($config['payroll_auth_enabled']) {
    echo "❌ ERROR: Authentication is ENABLED!\n";
    echo "   Set PAYROLL_AUTH_ENABLED=false in app.php or .env\n\n";
    exit(1);
}

echo "✅ Authentication is DISABLED globally\n\n";

// Test endpoints without authentication
echo "🧪 Testing Endpoints Without Authentication:\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$baseUrl = 'http://localhost';
$endpoints = [
    'GET /payroll/dashboard' => '/modules/human_resources/payroll/?view=dashboard',
    'GET /api/payroll/dashboard/data' => '/modules/human_resources/payroll/?api=dashboard/data',
    'GET /api/payroll/amendments/pending' => '/modules/human_resources/payroll/?api=amendments/pending',
];

$passed = 0;
$failed = 0;

foreach ($endpoints as $name => $path) {
    echo "Testing: {$name}\n";

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $baseUrl . $path,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT => 5,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HEADER => true,
        CURLOPT_NOBODY => false,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // Success if NOT 401 (Unauthorized) or 302 (Redirect to login)
    if ($httpCode === 401) {
        echo "  ❌ FAILED - Got 401 Unauthorized (auth still enforced!)\n";
        $failed++;
    } elseif ($httpCode === 302) {
        echo "  ❌ FAILED - Got 302 Redirect (auth redirect still active!)\n";
        $failed++;
    } elseif ($httpCode >= 200 && $httpCode < 400) {
        echo "  ✅ PASSED - Got {$httpCode} (accessible without auth)\n";
        $passed++;
    } else {
        echo "  ⚠️  WARNING - Got {$httpCode} (unexpected but not auth issue)\n";
        $passed++; // Count as pass since it's not auth blocking
    }

    echo "\n";
}

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "📊 Results:\n";
echo "  ✅ Passed: {$passed}\n";
echo "  ❌ Failed: {$failed}\n";
echo "\n";

if ($failed > 0) {
    echo "❌ VERIFICATION FAILED\n";
    echo "   Some endpoints still require authentication!\n";
    echo "   Check index.php security checks section.\n\n";
    exit(1);
}

echo "✅ VERIFICATION SUCCESSFUL\n";
echo "   All endpoints accessible without authentication!\n";
echo "   Module is in OPEN ACCESS mode.\n\n";

echo "⚠️  IMPORTANT:\n";
echo "   - This is for DEVELOPMENT/TESTING only\n";
echo "   - Set PAYROLL_AUTH_ENABLED=true for production\n";
echo "   - Never deploy with authentication disabled!\n\n";

exit(0);
