<?php
/**
 * Quick DB Connection Test - URGENT DEADLINE 10AM NZ
 * Tests if our independent Db class can connect to the database
 */

declare(strict_types=1);

// Start with bootstrap
require_once __DIR__ . '/module_bootstrap.php';

use Transfers\Lib\Db;

try {
    echo "🔍 TESTING DATABASE CONNECTION - URGENT DEADLINE 10AM NZ\n";
    echo "============================================================\n\n";
    
    // Load environment
    echo "1. Loading .env file...\n";
    Db::loadEnv();
    
    // Check environment variables
    echo "2. Environment variables:\n";
    echo "   DB_HOST: " . ($_ENV['DB_HOST'] ?? 'NOT SET') . "\n";
    echo "   DB_NAME: " . ($_ENV['DB_NAME'] ?? 'NOT SET') . "\n";
    echo "   DB_USER: " . ($_ENV['DB_USER'] ?? 'NOT SET') . "\n";
    echo "   DB_PASS: " . (isset($_ENV['DB_PASS']) ? '[SET]' : 'NOT SET') . "\n";
    echo "   DB_PORT: " . ($_ENV['DB_PORT'] ?? 'NOT SET') . "\n\n";
    
    // Test database connection
    echo "3. Testing PDO connection...\n";
    $pdo = Db::pdo();
    echo "   ✅ PDO connection successful!\n\n";
    
    // Test basic query
    echo "4. Testing basic query...\n";
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM information_schema.tables WHERE table_schema = ?");
    $stmt->execute([$_ENV['DB_NAME'] ?? 'jcepnzzkmj']);
    $result = $stmt->fetch();
    echo "   ✅ Found {$result['count']} tables in database\n\n";
    
    // Test vend_outlets table (critical for transfers)
    echo "5. Testing critical tables...\n";
    $criticalTables = ['vend_outlets', 'stock_transfers', 'stock_transfer_items'];
    
    foreach ($criticalTables as $table) {
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM `{$table}`");
            $stmt->execute();
            $result = $stmt->fetch();
            echo "   ✅ Table '{$table}': {$result['count']} records\n";
        } catch (Exception $e) {
            echo "   ❌ Table '{$table}': ERROR - " . $e->getMessage() . "\n";
        }
    }
    
    echo "\n🎉 DATABASE CONNECTION TEST PASSED!\n";
    echo "Ready for API testing and full module deployment.\n";
    
} catch (Exception $e) {
    echo "\n❌ DATABASE CONNECTION FAILED!\n";
    echo "Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    
    if ($e instanceof PDOException) {
        echo "PDO Error Code: " . $e->getCode() . "\n";
    }
    
    echo "\nDebugging info:\n";
    echo "- Check .env file exists: " . (file_exists($_SERVER['DOCUMENT_ROOT'] . '/.env') ? 'YES' : 'NO') . "\n";
    echo "- Document root: " . $_SERVER['DOCUMENT_ROOT'] . "\n";
    echo "- Current working directory: " . getcwd() . "\n";
    
    exit(1);
}
?>