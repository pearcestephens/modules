<?php
/**
 * CIS Modules Integration Test
 * Tests all migrated modules for connectivity and basic functionality
 */

echo "════════════════════════════════════════════════════════════\n";
echo "  🧪 CIS MODULES INTEGRATION TEST\n";
echo "════════════════════════════════════════════════════════════\n\n";

// Load configuration
$config = require 'stock_transfer_engine/config/database.php';

echo "🔐 Configuration:\n";
echo "   Host: {$config['host']}\n";
echo "   Database: {$config['database']}\n";
echo "   Username: {$config['username']}\n\n";

// Test 1: Database Connection
echo "TEST 1: Database Connection\n";
echo "─────────────────────────────\n";
try {
    $pdo = new PDO(
        "mysql:host={$config['host']};dbname={$config['database']}",
        $config['username'],
        $config['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    echo "✅ Connection successful\n\n";
} catch (Exception $e) {
    echo "❌ Connection failed: " . $e->getMessage() . "\n\n";
    exit(1);
}

// Test 2: Check Tables
echo "TEST 2: Table Existence\n";
echo "─────────────────────────────\n";
$tables = [
    'stock_transfers' => 'Stock Transfer (base)',
    'stock_transfer_items' => 'Stock Transfer Items',
    'excess_stock_alerts' => 'AI Excess Detection',
    'stock_velocity_tracking' => 'Velocity Tracking',
    'freight_costs' => 'Freight Calculator',
    'outlet_freight_zones' => 'Freight Zones',
    'transfer_routes' => 'Route Optimization',
    'transfer_boxes' => 'Box Tracking',
    'transfer_rejections' => 'Rejection Tracking',
    'transfer_tracking_events' => 'Event Tracking',
    'crawler_logs' => 'Crawler Logs',
    'crawler_metrics' => 'Crawler Metrics',
    'crawler_sessions' => 'Crawler Sessions',
    'dynamic_pricing_recommendations' => 'Dynamic Pricing'
];

$existing = 0;
$missing = 0;

foreach ($tables as $table => $description) {
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        if ($stmt->rowCount() > 0) {
            echo "   ✅ $description ($table)\n";
            $existing++;
        } else {
            echo "   ❌ $description ($table) - NOT FOUND\n";
            $missing++;
        }
    } catch (Exception $e) {
        echo "   ⚠️  Error checking $table: " . $e->getMessage() . "\n";
    }
}

echo "\n📊 Summary: $existing tables exist, $missing missing\n\n";

// Test 3: PHP Module Loading
echo "TEST 3: PHP Module Loading\n";
echo "─────────────────────────────\n";

$modules = [
    'VendTransferAPI' => 'stock_transfer_engine/services/VendTransferAPI.php',
    'WarehouseManager' => 'stock_transfer_engine/services/WarehouseManager.php',
    'ExcessDetectionEngine' => 'stock_transfer_engine/services/ExcessDetectionEngine.php',
    'HumanBehaviorEngine' => 'human_behavior_engine/HumanBehaviorEngine.php',
    'CompetitiveIntelCrawler' => 'crawlers/CompetitiveIntelCrawler.php',
    'DynamicPricingEngine' => 'dynamic_pricing/DynamicPricingEngine.php',
    'AdvancedIntelligenceEngine' => 'ai_intelligence/AdvancedIntelligenceEngine.php'
];

$loaded = 0;
$errors = 0;

foreach ($modules as $name => $path) {
    if (file_exists($path)) {
        $syntax = exec("php -l $path 2>&1", $output, $return);
        if ($return === 0) {
            echo "   ✅ $name\n";
            $loaded++;
        } else {
            echo "   ❌ $name - Syntax Error\n";
            $errors++;
        }
    } else {
        echo "   ❌ $name - File Not Found\n";
        $errors++;
    }
}

echo "\n📊 Summary: $loaded modules valid, $errors with errors\n\n";

// Test 4: Configuration Files
echo "TEST 4: Configuration Files\n";
echo "─────────────────────────────\n";

$configs = [
    'warehouses.php' => 'stock_transfer_engine/config/warehouses.php',
    'database.php' => 'stock_transfer_engine/config/database.php'
];

foreach ($configs as $name => $path) {
    if (file_exists($path)) {
        echo "   ✅ $name\n";
    } else {
        echo "   ❌ $name - NOT FOUND\n";
    }
}

echo "\n";

// Test 5: Documentation
echo "TEST 5: Documentation\n";
echo "─────────────────────────────\n";

$docs = [
    'README.md',
    'INDEX.md',
    'MIGRATION_GUIDE.md',
    'SUMMARY.md',
    'INTEGRATION_ANALYSIS.md',
    'VERIFICATION.sh'
];

$docCount = 0;
foreach ($docs as $doc) {
    if (file_exists($doc)) {
        echo "   ✅ $doc\n";
        $docCount++;
    }
}

echo "\n📊 $docCount documentation files present\n\n";

// Final Summary
echo "════════════════════════════════════════════════════════════\n";
echo "  📊 FINAL SUMMARY\n";
echo "════════════════════════════════════════════════════════════\n\n";

$totalTests = 5;
$passed = 0;

if ($existing > 0) $passed++;
if ($loaded >= 6) $passed++;
if ($missing == 0) $passed++;

$status = ($passed >= 3) ? "✅ READY FOR USE" : "⚠️  NEEDS ATTENTION";

echo "Tests Completed: $totalTests\n";
echo "Database Tables: $existing / " . count($tables) . "\n";
echo "PHP Modules: $loaded / " . count($modules) . "\n";
echo "Documentation: $docCount / " . count($docs) . "\n";
echo "\n$status\n\n";

if ($missing > 0) {
    echo "⚠️  Next Step: Import missing database schemas\n";
    echo "   Run: php import_database_schemas.php\n\n";
}

echo "════════════════════════════════════════════════════════════\n";
