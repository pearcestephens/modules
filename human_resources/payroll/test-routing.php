<?php
/**
 * Test Payroll Routing (bypasses auth for testing)
 */

echo "<h1>Payroll Routing Test</h1>";
echo "<p>Testing what route is being generated...</p>";

// Simulate accessing ?view=payruns
$_GET['view'] = 'payruns';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/modules/human_resources/payroll/?view=payruns';
$_SERVER['SCRIPT_NAME'] = '/modules/human_resources/payroll/index.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$isApi = isset($_GET['api']);
$isView = isset($_GET['view']);

echo "<h2>Request Analysis</h2>";
echo "<pre>";
echo "METHOD: $method\n";
echo "isApi: " . ($isApi ? 'YES' : 'NO') . "\n";
echo "isView: " . ($isView ? 'YES' : 'NO') . "\n";
echo "\$_GET['view']: " . ($_GET['view'] ?? 'not set') . "\n";
echo "</pre>";

// Build route using index.php logic
if ($isApi) {
    $route = $method . ' /api/payroll/' . trim($_GET['api'], '/');
} elseif ($isView) {
    $route = 'GET /payroll/' . trim($_GET['view'], '/');
} else {
    $route = 'GET /payroll/dashboard';
}

echo "<h2>Generated Route</h2>";
echo "<p><strong>$route</strong></p>";

// Load routes
$routesFile = __DIR__ . '/routes.php';
if (!file_exists($routesFile)) {
    echo "<p style='color:red'>ERROR: routes.php not found at: $routesFile</p>";
    exit;
}

$routes = require $routesFile;

echo "<h2>Route Matching</h2>";

// Check if route exists
if (isset($routes[$route])) {
    echo "<p style='color:green'>✅ ROUTE FOUND!</p>";
    echo "<pre>" . print_r($routes[$route], true) . "</pre>";
} else {
    echo "<p style='color:red'>❌ ROUTE NOT FOUND</p>";
    echo "<p>Looking for pattern match...</p>";

    $found = false;
    foreach ($routes as $pattern => $config) {
        if (strpos($pattern, 'payroll') !== false && strpos($pattern, 'payrun') !== false) {
            echo "<div style='border:1px solid #ccc; padding:10px; margin:5px'>";
            echo "<strong>Pattern:</strong> $pattern<br>";
            echo "<strong>Config:</strong> <pre>" . print_r($config, true) . "</pre>";
            echo "</div>";
        }
    }
}

echo "<h2>Available Payroll Routes</h2>";
echo "<ul>";
foreach ($routes as $pattern => $config) {
    if (strpos($pattern, 'payroll') !== false) {
        echo "<li><code>$pattern</code></li>";
    }
}
echo "</ul>";

echo "<hr>";
echo "<p><a href='?view=payruns'>Try ?view=payruns</a></p>";
echo "<p><a href='?view=dashboard'>Try ?view=dashboard</a></p>";
