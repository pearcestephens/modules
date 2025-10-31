<?php
/**
 * Debug Router - Shows what route is being detected
 *
 * Access this to see what the router is interpreting:
 * https://staff.vapeshed.co.nz/modules/human_resources/payroll/debug_router.php
 */

// Simulate the routing logic
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$isApi = isset($_GET['api']);
$isView = isset($_GET['view']);

$requestUri = $_SERVER['REQUEST_URI'] ?? '';
$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';

echo "<h1>Payroll Router Debug</h1>";
echo "<pre>";

echo "=== SERVER VARS ===\n";
echo "REQUEST_METHOD: " . $method . "\n";
echo "REQUEST_URI: " . $requestUri . "\n";
echo "SCRIPT_NAME: " . $scriptName . "\n";
echo "QUERY_STRING: " . ($_SERVER['QUERY_STRING'] ?? 'none') . "\n";
echo "\n";

echo "=== PARSED ===\n";
echo "isApi: " . ($isApi ? 'YES' : 'NO') . "\n";
echo "isView: " . ($isView ? 'YES' : 'NO') . "\n";
echo "\$_GET['api']: " . ($_GET['api'] ?? 'not set') . "\n";
echo "\$_GET['view']: " . ($_GET['view'] ?? 'not set') . "\n";
echo "\n";

// Build route using same logic as index.php
if ($isApi) {
    $route = $method . ' /api/payroll/' . trim($_GET['api'], '/');
} elseif ($isView) {
    $route = 'GET /payroll/' . trim($_GET['view'], '/');
} elseif (isset($_GET['route'])) {
    $route = $method . ' ' . trim($_GET['route'], '/');
} else {
    // Remove query string
    $cleanUri = preg_replace('/\?.*$/', '', $requestUri);

    echo "=== CLEAN URL DETECTION ===\n";
    echo "cleanUri: " . $cleanUri . "\n";
    echo "scriptName: " . $scriptName . "\n";
    echo "dirname(scriptName): " . dirname($scriptName) . "\n";
    echo "contains 'index.php': " . (strpos($cleanUri, 'index.php') !== false ? 'YES' : 'NO') . "\n";
    echo "equals dirname+/: " . ($cleanUri === dirname($scriptName) . '/' ? 'YES' : 'NO') . "\n";
    echo "\n";

    // If accessing index.php directly without params, show dashboard
    if (strpos($cleanUri, 'index.php') !== false || $cleanUri === dirname($scriptName) . '/') {
        $route = 'GET /payroll/dashboard';
    } else {
        // Try to extract route from URI
        $path = str_replace(dirname($scriptName), '', $cleanUri);
        $route = $method . ' ' . $path;
    }
}

echo "=== FINAL ROUTE ===\n";
echo "Route: " . $route . "\n";
echo "\n";

echo "=== AVAILABLE ROUTES ===\n";
$routes = require __DIR__ . '/routes.php';
foreach ($routes as $pattern => $config) {
    if (strpos($pattern, 'payroll') !== false) {
        echo $pattern . "\n";
    }
}

echo "</pre>";

echo "<h2>Test URLs</h2>";
echo "<ul>";
echo "<li><a href='/modules/human_resources/payroll/'>Base URL with /</a></li>";
echo "<li><a href='/modules/human_resources/payroll/index.php'>index.php direct</a></li>";
echo "<li><a href='/modules/human_resources/payroll/?view=dashboard'>?view=dashboard</a></li>";
echo "<li><a href='/modules/human_resources/payroll/?view=payruns'>?view=payruns</a></li>";
echo "</ul>";
