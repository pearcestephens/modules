#!/usr/bin/env php
<?php
/**
 * COMPREHENSIVE PAYROLL MODULE AUDIT
 * Tests what's actually working vs what's broken
 */

echo "==========================================\n";
echo "PAYROLL MODULE REALITY CHECK\n";
echo "==========================================\n\n";

$baseUrl = 'https://staff.vapeshed.co.nz/modules/human_resources/payroll';
$botToken = 'ci_automation_token';

// Test 1: Main index page
echo "TEST 1: Main Index Page\n";
echo "URL: {$baseUrl}/\n";
$ch = curl_init($baseUrl . '/');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
curl_setopt($ch, CURLOPT_HEADER, true);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: {$httpCode}\n";
if (preg_match('/Location: (.+)/', $response, $matches)) {
    echo "Redirects to: " . trim($matches[1]) . "\n";
}
echo "\n";

// Test 2: Dashboard page
echo "TEST 2: Dashboard Page\n";
echo "URL: {$baseUrl}/dashboard\n";
$ch = curl_init($baseUrl . '/dashboard');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
curl_setopt($ch, CURLOPT_HEADER, true);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: {$httpCode}\n";
if (preg_match('/Location: (.+)/', $response, $matches)) {
    echo "Redirects to: " . trim($matches[1]) . "\n";
}
echo "\n";

// Test 3: Check if files exist
echo "TEST 3: Core Files Existence\n";
$files = [
    'index.php' => __DIR__ . '/index.php',
    'routes.php' => __DIR__ . '/routes.php',
    'bootstrap.php' => __DIR__ . '/bootstrap.php',
    'DashboardController' => __DIR__ . '/controllers/DashboardController.php',
    'dashboard view' => __DIR__ . '/views/dashboard.php',
    'bot_events.php' => __DIR__ . '/api/bot_events.php',
    'bot_actions.php' => __DIR__ . '/api/bot_actions.php',
    'bot_context.php' => __DIR__ . '/api/bot_context.php',
];

foreach ($files as $name => $path) {
    $exists = file_exists($path);
    $status = $exists ? '✓ EXISTS' : '✗ MISSING';
    echo "  {$status}: {$name}\n";
}
echo "\n";

// Test 4: Bot API health check
echo "TEST 4: Bot API - Health Check\n";
echo "URL: {$baseUrl}/api/bot_events.php?action=health_check\n";
$ch = curl_init($baseUrl . '/api/bot_events.php?action=health_check');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ["X-Bot-Token: {$botToken}"]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: {$httpCode}\n";
echo "Response: " . ($response ?: '(empty)') . "\n\n";

// Test 5: Bot API pending events
echo "TEST 5: Bot API - Pending Events\n";
echo "URL: {$baseUrl}/api/bot_events.php?action=pending_events\n";
$ch = curl_init($baseUrl . '/api/bot_events.php?action=pending_events');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ["X-Bot-Token: {$botToken}"]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: {$httpCode}\n";
if ($httpCode == 200) {
    $data = json_decode($response, true);
    if ($data) {
        echo "Success: " . ($data['success'] ? 'YES' : 'NO') . "\n";
        echo "Event Count: " . (is_array($data) ? count($data) : 'N/A') . "\n";
    }
}
echo "\n";

// Test 6: Database connectivity
echo "TEST 6: Database Connection\n";
try {
    $pdo = new PDO(
        'mysql:host=127.0.0.1;dbname=jcepnzzkmj;charset=utf8mb4',
        'jcepnzzkmj',
        'wprKh9Jq63',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    echo "✓ Database connection: SUCCESS\n";

    // Check bot tables
    $stmt = $pdo->query("SHOW TABLES LIKE 'payroll_bot%'");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "✓ Bot tables found: " . count($tables) . "\n";
    foreach ($tables as $table) {
        echo "  - {$table}\n";
    }
} catch (PDOException $e) {
    echo "✗ Database connection: FAILED\n";
    echo "  Error: " . $e->getMessage() . "\n";
}
echo "\n";

// Test 7: Check what routes are actually defined
echo "TEST 7: Defined Routes\n";
if (file_exists(__DIR__ . '/routes.php')) {
    $routes = require __DIR__ . '/routes.php';
    echo "Total routes defined: " . count($routes) . "\n";

    // Show first 10 routes
    $count = 0;
    foreach ($routes as $pattern => $config) {
        if ($count++ < 10) {
            echo "  {$pattern} -> {$config['controller']}::{$config['action']}\n";
        }
    }
    if (count($routes) > 10) {
        echo "  ... and " . (count($routes) - 10) . " more\n";
    }
} else {
    echo "✗ routes.php not found\n";
}
echo "\n";

// Test 8: Check controllers
echo "TEST 8: Available Controllers\n";
$controllerDir = __DIR__ . '/controllers';
if (is_dir($controllerDir)) {
    $controllers = scandir($controllerDir);
    $controllers = array_filter($controllers, function($f) {
        return substr($f, -4) === '.php' && $f !== '.' && $f !== '..';
    });
    echo "Controllers found: " . count($controllers) . "\n";
    foreach ($controllers as $controller) {
        echo "  - " . str_replace('.php', '', $controller) . "\n";
    }
} else {
    echo "✗ Controllers directory not found\n";
}
echo "\n";

// Test 9: Check views
echo "TEST 9: Available Views\n";
$viewsDir = __DIR__ . '/views';
if (is_dir($viewsDir)) {
    $views = scandir($viewsDir);
    $views = array_filter($views, function($f) {
        return substr($f, -4) === '.php' && $f !== '.' && $f !== '..';
    });
    echo "Views found: " . count($views) . "\n";
    foreach ($views as $view) {
        echo "  - " . str_replace('.php', '', $view) . "\n";
    }
} else {
    echo "✗ Views directory not found\n";
}
echo "\n";

// Test 10: Check bootstrap configuration
echo "TEST 10: Bootstrap Configuration\n";
if (file_exists(__DIR__ . '/bootstrap.php')) {
    $content = file_get_contents(__DIR__ . '/bootstrap.php');

    // Check for key functions
    $functions = [
        'payroll_require_bot_auth',
        'payroll_validate_bot_token',
        'getPayrollDb',
        'payroll_json_success',
        'payroll_json_error'
    ];

    foreach ($functions as $func) {
        $exists = strpos($content, "function {$func}") !== false;
        $status = $exists ? '✓' : '✗';
        echo "  {$status} {$func}()\n";
    }
} else {
    echo "✗ bootstrap.php not found\n";
}
echo "\n";

echo "==========================================\n";
echo "AUDIT COMPLETE\n";
echo "==========================================\n";
