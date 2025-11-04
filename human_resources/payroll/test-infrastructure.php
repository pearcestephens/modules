#!/usr/bin/env php
<?php
/**
 * PAYROLL MODULE INFRASTRUCTURE AUDIT
 * Tests all core systems before bot integration
 */

echo "==========================================\n";
echo "PAYROLL INFRASTRUCTURE AUDIT\n";
echo "==========================================\n\n";

$baseUrl = 'https://staff.vapeshed.co.nz/modules/human_resources/payroll';
$failures = [];
$warnings = [];

// ============================================================================
// TEST 1: WEB UI ROUTES
// ============================================================================
echo "TEST 1: Web UI Routes\n";
echo "-------------------------------------------\n";

$webRoutes = [
    '/' => 'Main Dashboard',
    '/dashboard' => 'Dashboard (explicit)',
    '/payruns' => 'Pay Runs List',
];

foreach ($webRoutes as $path => $name) {
    $url = $baseUrl . $path;
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $status = match(true) {
        $httpCode === 200 => '✓ 200 OK',
        $httpCode === 302 => '⚠ 302 REDIRECT',
        $httpCode === 404 => '✗ 404 NOT FOUND',
        default => "✗ {$httpCode}"
    };

    echo "  {$status}: {$name} ({$path})\n";

    if ($httpCode !== 200) {
        $failures[] = "{$name} returned {$httpCode}";
    }
}
echo "\n";

// ============================================================================
// TEST 2: API ENDPOINTS (No Auth)
// ============================================================================
echo "TEST 2: API Endpoints (Public)\n";
echo "-------------------------------------------\n";

$apiEndpoints = [
    '/api/bot_events.php?action=health_check' => 'Bot Health Check',
];

foreach ($apiEndpoints as $path => $name) {
    $url = $baseUrl . $path;
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['X-Bot-Token: ci_automation_token']);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $status = $httpCode === 200 ? '✓ 200 OK' : "✗ {$httpCode}";
    echo "  {$status}: {$name}\n";

    if ($httpCode !== 200) {
        $failures[] = "{$name} returned {$httpCode}";
    }
}
echo "\n";

// ============================================================================
// TEST 3: DATABASE CONNECTIVITY
// ============================================================================
echo "TEST 3: Database Infrastructure\n";
echo "-------------------------------------------\n";

try {
    $pdo = new PDO(
        'mysql:host=127.0.0.1;dbname=jcepnzzkmj;charset=utf8mb4',
        'jcepnzzkmj',
        'wprKh9Jq63',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    echo "  ✓ Database connection successful\n";

    // Check critical tables
    $tables = [
        'users' => 'User accounts',
        'payroll_bot_config' => 'Bot configuration',
        'payroll_bot_events' => 'Bot event queue',
        'payroll_bot_decisions' => 'Bot decision log',
        'payroll_bot_heartbeat' => 'Bot health monitoring',
        'payroll_bot_metrics' => 'Bot performance metrics',
    ];

    foreach ($tables as $table => $desc) {
        $stmt = $pdo->query("SHOW TABLES LIKE '{$table}'");
        if ($stmt->rowCount() > 0) {
            echo "  ✓ Table exists: {$table}\n";
        } else {
            echo "  ✗ Table missing: {$table}\n";
            $failures[] = "Missing table: {$table}";
        }
    }

} catch (PDOException $e) {
    echo "  ✗ Database connection failed\n";
    echo "    Error: " . $e->getMessage() . "\n";
    $failures[] = "Database connection failed";
}
echo "\n";

// ============================================================================
// TEST 4: FILE STRUCTURE
// ============================================================================
echo "TEST 4: File Structure\n";
echo "-------------------------------------------\n";

$requiredFiles = [
    'index.php' => 'Main entry point',
    'routes.php' => 'Route configuration',
    'bootstrap.php' => 'Bootstrap loader',
    'autoload.php' => 'Autoloader',
    'controllers/DashboardController.php' => 'Dashboard controller',
    'controllers/BaseController.php' => 'Base controller',
    'controllers/PayRunController.php' => 'Pay run controller',
    'views/dashboard.php' => 'Dashboard view',
    'views/layouts/header.php' => 'Layout header',
    'views/layouts/footer.php' => 'Layout footer',
];

$basePath = __DIR__;
foreach ($requiredFiles as $file => $desc) {
    $path = $basePath . '/' . $file;
    if (file_exists($path)) {
        echo "  ✓ {$desc}\n";
    } else {
        echo "  ✗ MISSING: {$desc} ({$file})\n";
        $failures[] = "Missing file: {$file}";
    }
}
echo "\n";

// ============================================================================
// TEST 5: CONFIGURATION
// ============================================================================
echo "TEST 5: Configuration\n";
echo "-------------------------------------------\n";

$configFile = dirname(__DIR__, 2) . '/config/app.php';
if (file_exists($configFile)) {
    $config = require $configFile;
    echo "  ✓ Config file loaded\n";

    $settings = [
        'env' => $config['env'] ?? 'unknown',
        'debug' => $config['debug'] ?? false,
        'payroll_auth_enabled' => $config['payroll_auth_enabled'] ?? true,
    ];

    foreach ($settings as $key => $value) {
        $val = is_bool($value) ? ($value ? 'true' : 'false') : $value;
        echo "    {$key}: {$val}\n";
    }

    if ($config['payroll_auth_enabled'] ?? true) {
        $warnings[] = "Auth is ENABLED - pages require login";
    }
} else {
    echo "  ✗ Config file not found\n";
    $failures[] = "Config file missing";
}
echo "\n";

// ============================================================================
// TEST 6: CONTROLLERS
// ============================================================================
echo "TEST 6: Controllers\n";
echo "-------------------------------------------\n";

$controllerPath = $basePath . '/controllers';
if (is_dir($controllerPath)) {
    $controllers = array_filter(
        scandir($controllerPath),
        fn($f) => substr($f, -4) === '.php'
    );

    echo "  Found " . count($controllers) . " controllers:\n";
    foreach ($controllers as $controller) {
        echo "    - " . str_replace('.php', '', $controller) . "\n";
    }
} else {
    echo "  ✗ Controllers directory not found\n";
    $failures[] = "Controllers directory missing";
}
echo "\n";

// ============================================================================
// TEST 7: AUTOLOADER
// ============================================================================
echo "TEST 7: Autoloader\n";
echo "-------------------------------------------\n";

if (file_exists($basePath . '/autoload.php')) {
    require_once $basePath . '/autoload.php';
    echo "  ✓ Autoloader loaded\n";

    // Test autoloading a controller
    if (class_exists('HumanResources\\Payroll\\Controllers\\DashboardController')) {
        echo "  ✓ Autoloader working (DashboardController found)\n";
    } else {
        echo "  ⚠ Autoloader may not be working correctly\n";
        $warnings[] = "Autoloader test failed";
    }
} else {
    echo "  ✗ Autoloader file not found\n";
    $failures[] = "Autoloader missing";
}
echo "\n";

// ============================================================================
// TEST 8: ROUTES CONFIGURATION
// ============================================================================
echo "TEST 8: Routes Configuration\n";
echo "-------------------------------------------\n";

if (file_exists($basePath . '/routes.php')) {
    $routes = require $basePath . '/routes.php';
    echo "  ✓ Routes file loaded\n";
    echo "  Total routes: " . count($routes) . "\n";

    // Count by type
    $webRoutes = array_filter($routes, fn($r) => !str_contains($r['controller'] ?? '', 'Api'));
    $apiRoutes = array_filter($routes, fn($r) => str_contains($r['controller'] ?? '', 'Api') || isset($r['auth']) && $r['auth'] === false);

    echo "    Web UI routes: " . count($webRoutes) . "\n";
    echo "    API routes: " . count($apiRoutes) . "\n";

    // Check for missing controllers
    $uniqueControllers = array_unique(array_column($routes, 'controller'));
    echo "  Controllers referenced in routes:\n";
    foreach ($uniqueControllers as $ctrl) {
        $file = $basePath . '/controllers/' . $ctrl . '.php';
        $exists = file_exists($file);
        $status = $exists ? '✓' : '✗';
        echo "    {$status} {$ctrl}\n";
        if (!$exists) {
            $failures[] = "Missing controller: {$ctrl}";
        }
    }
} else {
    echo "  ✗ Routes file not found\n";
    $failures[] = "Routes file missing";
}
echo "\n";

// ============================================================================
// SUMMARY
// ============================================================================
echo "==========================================\n";
echo "SUMMARY\n";
echo "==========================================\n";

if (empty($failures) && empty($warnings)) {
    echo "✓ ALL TESTS PASSED\n";
    echo "Infrastructure is ready for bot integration!\n";
} else {
    if (!empty($failures)) {
        echo "✗ FAILURES: " . count($failures) . "\n";
        foreach ($failures as $failure) {
            echo "  - {$failure}\n";
        }
        echo "\n";
    }

    if (!empty($warnings)) {
        echo "⚠ WARNINGS: " . count($warnings) . "\n";
        foreach ($warnings as $warning) {
            echo "  - {$warning}\n";
        }
    }
}

echo "\n";
exit(empty($failures) ? 0 : 1);
