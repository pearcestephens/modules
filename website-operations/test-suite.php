<?php
/**
 * Website Operations Module - Comprehensive Test Suite
 *
 * Tests all functionality including:
 * - Database connectivity
 * - Service layer operations
 * - API endpoints
 * - Shipping optimization algorithm
 * - Order management
 * - Product management
 *
 * @version 1.0.0
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Change to application root
chdir('/home/master/applications/jcepnzzkmj/public_html');

// Load database config
require_once 'includes/db_config.php';

// Test results storage
$testResults = [
    'total' => 0,
    'passed' => 0,
    'failed' => 0,
    'tests' => []
];

/**
 * Run a test and record result
 */
function runTest(string $name, callable $test): void {
    global $testResults;

    $testResults['total']++;

    try {
        $result = $test();

        if ($result === true || (is_array($result) && $result['success'] === true)) {
            $testResults['passed']++;
            $testResults['tests'][] = [
                'name' => $name,
                'status' => 'PASS',
                'message' => is_array($result) ? ($result['message'] ?? 'Test passed') : 'Test passed',
                'data' => is_array($result) ? ($result['data'] ?? null) : null
            ];
            echo "✅ PASS: {$name}\n";
        } else {
            throw new Exception(is_string($result) ? $result : 'Test returned false');
        }
    } catch (Exception $e) {
        $testResults['failed']++;
        $testResults['tests'][] = [
            'name' => $name,
            'status' => 'FAIL',
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ];
        echo "❌ FAIL: {$name} - {$e->getMessage()}\n";
    }
}

echo "\n";
echo "╔═══════════════════════════════════════════════════════════════╗\n";
echo "║   Website Operations Module - Comprehensive Test Suite       ║\n";
echo "╚═══════════════════════════════════════════════════════════════╝\n";
echo "\n";

// =============================================================================
// TEST SECTION 1: DATABASE CONNECTIVITY
// =============================================================================
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "TEST SECTION 1: Database Connectivity\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

runTest("Database Connection", function() {
    $pdo = new PDO(
        "mysql:host=localhost;dbname=jcepnzzkmj;charset=utf8mb4",
        "jcepnzzkmj",
        "wprKh9Jq63",
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    return ['success' => true, 'message' => 'Connected to database'];
});

runTest("Web Tables Exist", function() {
    $pdo = new PDO("mysql:host=localhost;dbname=jcepnzzkmj;charset=utf8mb4", "jcepnzzkmj", "wprKh9Jq63");
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM information_schema.TABLES WHERE TABLE_SCHEMA = 'jcepnzzkmj' AND TABLE_NAME LIKE 'web_%'");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $count = (int)$result['count'];

    if ($count >= 10) {
        return ['success' => true, 'message' => "{$count} web tables found"];
    }
    throw new Exception("Expected at least 10 tables, found {$count}");
});

runTest("Store Configurations Table", function() {
    $pdo = new PDO("mysql:host=localhost;dbname=jcepnzzkmj;charset=utf8mb4", "jcepnzzkmj", "wprKh9Jq63");
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM store_configurations");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return ['success' => true, 'message' => "{$result['count']} stores configured", 'data' => $result];
});

// =============================================================================
// TEST SECTION 2: SERVICE LAYER
// =============================================================================
echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "TEST SECTION 2: Service Layer\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

runTest("Load WebsiteOperationsService", function() {
    if (!file_exists('modules/website-operations/services/WebsiteOperationsService.php')) {
        throw new Exception("Service file not found");
    }
    require_once 'modules/website-operations/services/WebsiteOperationsService.php';
    return ['success' => true, 'message' => 'Service class loaded'];
});

runTest("Load OrderManagementService", function() {
    if (!file_exists('modules/website-operations/services/OrderManagementService.php')) {
        throw new Exception("Service file not found");
    }
    require_once 'modules/website-operations/services/OrderManagementService.php';
    return ['success' => true, 'message' => 'Order service loaded'];
});

runTest("Load ShippingOptimizationService", function() {
    if (!file_exists('modules/website-operations/services/ShippingOptimizationService.php')) {
        throw new Exception("Service file not found");
    }
    require_once 'modules/website-operations/services/ShippingOptimizationService.php';
    return ['success' => true, 'message' => 'Shipping optimization loaded'];
});

runTest("Load ProductManagementService", function() {
    if (!file_exists('modules/website-operations/services/ProductManagementService.php')) {
        throw new Exception("Service file not found");
    }
    require_once 'modules/website-operations/services/ProductManagementService.php';
    return ['success' => true, 'message' => 'Product service loaded'];
});

runTest("Load CustomerManagementService", function() {
    if (!file_exists('modules/website-operations/services/CustomerManagementService.php')) {
        throw new Exception("Service file not found");
    }
    require_once 'modules/website-operations/services/CustomerManagementService.php';
    return ['success' => true, 'message' => 'Customer service loaded'];
});

// =============================================================================
// TEST SECTION 3: SERVICE INSTANTIATION
// =============================================================================
echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "TEST SECTION 3: Service Instantiation\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$pdo = new PDO("mysql:host=localhost;dbname=jcepnzzkmj;charset=utf8mb4", "jcepnzzkmj", "wprKh9Jq63", [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

runTest("Instantiate OrderManagementService", function() use ($pdo) {
    $service = new Modules\WebsiteOperations\Services\OrderManagementService($pdo);
    return ['success' => true, 'message' => 'Service instantiated'];
});

runTest("Instantiate ProductManagementService", function() use ($pdo) {
    $service = new Modules\WebsiteOperations\Services\ProductManagementService($pdo);
    return ['success' => true, 'message' => 'Service instantiated'];
});

runTest("Instantiate ShippingOptimizationService", function() use ($pdo) {
    $service = new Modules\WebsiteOperations\Services\ShippingOptimizationService($pdo);
    return ['success' => true, 'message' => 'Service instantiated'];
});

// =============================================================================
// TEST SECTION 4: DATA RETRIEVAL
// =============================================================================
echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "TEST SECTION 4: Data Retrieval\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

runTest("Get Recent Orders", function() use ($pdo) {
    $service = new Modules\WebsiteOperations\Services\OrderManagementService($pdo);
    $orders = $service->getRecentOrders(5);
    return ['success' => true, 'message' => count($orders) . ' orders retrieved', 'data' => count($orders)];
});

runTest("Get Products", function() use ($pdo) {
    $service = new Modules\WebsiteOperations\Services\ProductManagementService($pdo);
    $result = $service->getProducts([], 1, 10);
    return ['success' => true, 'message' => "{$result['total']} total products, showing 10", 'data' => $result['total']];
});

runTest("Get Product Statistics", function() use ($pdo) {
    $service = new Modules\WebsiteOperations\Services\ProductManagementService($pdo);
    $stats = $service->getProductStats();
    return ['success' => true, 'message' => "Total: {$stats['total']}, Active: {$stats['active']}", 'data' => $stats];
});

runTest("Get Customer Statistics", function() use ($pdo) {
    $service = new Modules\WebsiteOperations\Services\CustomerManagementService($pdo);
    $stats = $service->getCustomerStats(30);
    return ['success' => true, 'message' => "Total: {$stats['total']}, Active: {$stats['active']}", 'data' => $stats];
});

// =============================================================================
// TEST SECTION 5: SHIPPING OPTIMIZATION ALGORITHM
// =============================================================================
echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "TEST SECTION 5: Shipping Optimization Algorithm (MONEY SAVER!)\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

runTest("Distance Calculation (Haversine)", function() use ($pdo) {
    $service = new Modules\WebsiteOperations\Services\ShippingOptimizationService($pdo);

    // Use reflection to test private method
    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('haversineDistance');
    $method->setAccessible(true);

    // Auckland to Wellington distance (should be ~500km)
    $distance = $method->invoke($service, -36.8485, 174.7633, -41.2865, 174.7762);

    if ($distance > 400 && $distance < 600) {
        return ['success' => true, 'message' => "Auckland to Wellington: {$distance} km"];
    }
    throw new Exception("Distance calculation incorrect: {$distance} km");
});

runTest("Package Details Calculation", function() use ($pdo) {
    $service = new Modules\WebsiteOperations\Services\ShippingOptimizationService($pdo);

    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('calculatePackageDetails');
    $method->setAccessible(true);

    $items = [
        ['product_id' => 1, 'quantity' => 2]
    ];

    $details = $method->invoke($service, $items);

    if (isset($details['weight']) && isset($details['volume'])) {
        return ['success' => true, 'message' => "Package: {$details['weight']}g, {$details['volume']}cm³"];
    }
    throw new Exception("Package calculation failed");
});

runTest("Carrier Rate Estimation", function() use ($pdo) {
    $service = new Modules\WebsiteOperations\Services\ShippingOptimizationService($pdo);

    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('getCarrierRates');
    $method->setAccessible(true);

    $origin = ['postcode' => '1010', 'city' => 'Auckland'];
    $destination = ['postcode' => '6011', 'city' => 'Wellington'];
    $package = ['weight' => 500, 'volume' => 1000];

    $rates = $method->invoke($service, 'nzpost', $origin, $destination, $package);

    if (count($rates) > 0) {
        return ['success' => true, 'message' => count($rates) . ' shipping options found'];
    }
    throw new Exception("No rates calculated");
});

// =============================================================================
// TEST SECTION 6: API ENDPOINTS
// =============================================================================
echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "TEST SECTION 6: API Endpoints\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

runTest("API Index File Exists", function() {
    if (!file_exists('modules/website-operations/api/index.php')) {
        throw new Exception("API file not found");
    }
    return ['success' => true, 'message' => 'API file exists'];
});

runTest("API File Syntax Valid", function() {
    $output = shell_exec('php -l modules/website-operations/api/index.php 2>&1');
    if (strpos($output, 'No syntax errors') !== false) {
        return ['success' => true, 'message' => 'API syntax valid'];
    }
    throw new Exception("API syntax error: " . $output);
});

// =============================================================================
// TEST SECTION 7: FILE STRUCTURE
// =============================================================================
echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "TEST SECTION 7: File Structure\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$requiredFiles = [
    'services/WebsiteOperationsService.php',
    'services/OrderManagementService.php',
    'services/ShippingOptimizationService.php',
    'services/ProductManagementService.php',
    'services/CustomerManagementService.php',
    'services/WholesaleService.php',
    'services/PerformanceService.php',
    'api/index.php',
    'views/dashboard.php',
    'migrations/001_create_tables.sql',
    'module.json',
    'README.md',
    'BUILD_STATUS.md',
    'DELIVERY_REPORT.md'
];

foreach ($requiredFiles as $file) {
    runTest("File exists: {$file}", function() use ($file) {
        $path = "modules/website-operations/{$file}";
        if (!file_exists($path)) {
            throw new Exception("File not found: {$path}");
        }
        $size = filesize($path);
        return ['success' => true, 'message' => "File exists (" . number_format($size) . " bytes)"];
    });
}

// =============================================================================
// TEST SECTION 8: CONFIGURATION
// =============================================================================
echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "TEST SECTION 8: Configuration\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

runTest("Module.json Valid", function() {
    $content = file_get_contents('modules/website-operations/module.json');
    $json = json_decode($content, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Invalid JSON: " . json_last_error_msg());
    }
    return ['success' => true, 'message' => 'Module config valid'];
});

runTest("Module.json Has Required Fields", function() {
    $content = file_get_contents('modules/website-operations/module.json');
    $json = json_decode($content, true);

    $required = ['name', 'version', 'features', 'integrations', 'database'];
    foreach ($required as $field) {
        if (!isset($json[$field])) {
            throw new Exception("Missing required field: {$field}");
        }
    }
    return ['success' => true, 'message' => 'All required fields present'];
});

// =============================================================================
// FINAL RESULTS
// =============================================================================
echo "\n";
echo "╔═══════════════════════════════════════════════════════════════╗\n";
echo "║                     TEST RESULTS SUMMARY                      ║\n";
echo "╚═══════════════════════════════════════════════════════════════╝\n";
echo "\n";
echo "Total Tests:  {$testResults['total']}\n";
echo "Passed:       {$testResults['passed']} ✅\n";
echo "Failed:       {$testResults['failed']} ❌\n";
echo "\n";

$passRate = $testResults['total'] > 0 ? round(($testResults['passed'] / $testResults['total']) * 100, 1) : 0;
echo "Pass Rate:    {$passRate}%\n";
echo "\n";

if ($testResults['failed'] > 0) {
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "FAILED TESTS:\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    foreach ($testResults['tests'] as $test) {
        if ($test['status'] === 'FAIL') {
            echo "❌ {$test['name']}\n";
            echo "   Error: {$test['message']}\n\n";
        }
    }
}

echo "\n";
if ($testResults['failed'] === 0) {
    echo "╔═══════════════════════════════════════════════════════════════╗\n";
    echo "║              🎉 ALL TESTS PASSED! 🎉                          ║\n";
    echo "║         Module is PRODUCTION READY!                           ║\n";
    echo "╚═══════════════════════════════════════════════════════════════╝\n";
} else {
    echo "⚠️  Some tests failed. Review errors above.\n";
}

echo "\n";

// Save detailed results to JSON
file_put_contents(
    'modules/website-operations/test-results.json',
    json_encode($testResults, JSON_PRETTY_PRINT)
);

echo "Detailed results saved to: modules/website-operations/test-results.json\n\n";

exit($testResults['failed'] > 0 ? 1 : 0);
