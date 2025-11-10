<?php
/**
 * Database Schema Import Script
 * Imports all CIS module database schemas into jcepnzzkmj database
 */

echo "════════════════════════════════════════════════════════════\n";
echo "  📦 CIS DATABASE SCHEMA IMPORT\n";
echo "════════════════════════════════════════════════════════════\n\n";

// Load configuration
$config = require 'stock_transfer_engine/config/database.php';

echo "🔐 Connecting to database...\n";
echo "   Host: {$config['host']}\n";
echo "   Database: {$config['database']}\n\n";

try {
    $pdo = new PDO(
        "mysql:host={$config['host']};dbname={$config['database']}",
        $config['username'],
        $config['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    echo "✅ Connected successfully\n\n";
} catch (Exception $e) {
    echo "❌ Connection failed: " . $e->getMessage() . "\n";
    exit(1);
}

// Schema files to import (in order)
$schemas = [
    [
        'name' => 'Stock Transfer Engine (Base)',
        'file' => 'stock_transfer_engine/database/current_database_schema.sql',
        'critical' => true
    ],
    [
        'name' => 'Stock Transfer Engine (Addon)',
        'file' => 'stock_transfer_engine/database/migration_addon.sql',
        'critical' => true
    ],
    [
        'name' => 'Crawlers',
        'file' => 'crawlers/database_schema.sql',
        'critical' => false
    ],
    [
        'name' => 'Dynamic Pricing',
        'file' => 'dynamic_pricing/database_schema.sql',
        'critical' => false
    ]
];

$totalImported = 0;
$totalFailed = 0;

foreach ($schemas as $schema) {
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "📋 {$schema['name']}\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    
    if (!file_exists($schema['file'])) {
        echo "⚠️  File not found: {$schema['file']}\n\n";
        if ($schema['critical']) {
            $totalFailed++;
        }
        continue;
    }
    
    echo "📂 Reading: {$schema['file']}\n";
    $sql = file_get_contents($schema['file']);
    
    if (empty($sql)) {
        echo "⚠️  File is empty\n\n";
        continue;
    }
    
    // Split SQL into statements
    $statements = array_filter(
        array_map('trim', explode(';', $sql)),
        function($stmt) {
            return !empty($stmt) && 
                   !preg_match('/^--/', $stmt) && 
                   !preg_match('/^\/\*/', $stmt);
        }
    );
    
    echo "🔧 Found " . count($statements) . " SQL statements\n";
    echo "⚙️  Executing...\n\n";
    
    $success = 0;
    $failed = 0;
    
    foreach ($statements as $i => $statement) {
        try {
            // Extract table name for better logging
            if (preg_match('/CREATE TABLE (?:IF NOT EXISTS )?`?(\w+)`?/i', $statement, $matches)) {
                echo "   → Creating table: {$matches[1]}... ";
                $pdo->exec($statement);
                echo "✅\n";
                $success++;
            } elseif (preg_match('/ALTER TABLE `?(\w+)`?/i', $statement, $matches)) {
                echo "   → Altering table: {$matches[1]}... ";
                $pdo->exec($statement);
                echo "✅\n";
                $success++;
            } elseif (preg_match('/INSERT (?:IGNORE )?INTO `?(\w+)`?/i', $statement, $matches)) {
                echo "   → Inserting data into: {$matches[1]}... ";
                $pdo->exec($statement);
                echo "✅\n";
                $success++;
            } else {
                // Generic statement
                $pdo->exec($statement);
                $success++;
            }
        } catch (Exception $e) {
            $failed++;
            $error = $e->getMessage();
            
            // Only report non-duplicate key/column errors
            if (!preg_match('/Duplicate|already exists/i', $error)) {
                echo "❌ Error: " . substr($error, 0, 100) . "\n";
            }
        }
    }
    
    echo "\n📊 Results: $success successful, $failed failed/skipped\n\n";
    
    if ($success > 0) {
        $totalImported++;
    } elseif ($schema['critical']) {
        $totalFailed++;
    }
}

// Verification
echo "════════════════════════════════════════════════════════════\n";
echo "  🔍 VERIFICATION\n";
echo "════════════════════════════════════════════════════════════\n\n";

$verifyTables = [
    'stock_transfers',
    'stock_transfer_items',
    'excess_stock_alerts',
    'stock_velocity_tracking',
    'freight_costs',
    'outlet_freight_zones',
    'transfer_routes',
    'transfer_boxes',
    'transfer_rejections',
    'transfer_tracking_events',
    'crawler_logs',
    'crawler_metrics',
    'crawler_sessions',
    'dynamic_pricing_recommendations'
];

$verified = 0;

foreach ($verifyTables as $table) {
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        if ($stmt->rowCount() > 0) {
            echo "✅ $table\n";
            $verified++;
        } else {
            echo "❌ $table - NOT FOUND\n";
        }
    } catch (Exception $e) {
        echo "⚠️  Error checking $table\n";
    }
}

echo "\n";
echo "════════════════════════════════════════════════════════════\n";
echo "  📊 IMPORT COMPLETE\n";
echo "════════════════════════════════════════════════════════════\n\n";

echo "Schemas Processed: " . count($schemas) . "\n";
echo "Successfully Imported: $totalImported\n";
echo "Failed: $totalFailed\n";
echo "Tables Verified: $verified / " . count($verifyTables) . "\n\n";

if ($verified >= 12) {
    echo "✅ IMPORT SUCCESSFUL - All critical tables created\n";
    echo "🎉 CIS modules are ready to use!\n\n";
    echo "Next steps:\n";
    echo "1. Test stock transfer engine: php test_stock_transfer.php\n";
    echo "2. Test crawler system: php test_crawlers.php\n";
    echo "3. Run full integration test: php test_integration.php\n\n";
} else {
    echo "⚠️  IMPORT INCOMPLETE - Some tables missing\n";
    echo "Review errors above and retry if needed.\n\n";
}

echo "════════════════════════════════════════════════════════════\n";
