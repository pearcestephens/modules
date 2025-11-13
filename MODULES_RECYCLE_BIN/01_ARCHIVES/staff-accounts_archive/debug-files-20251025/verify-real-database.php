<?php
/**
 * Verify Real Database Setup
 * Checks that employee_mappings table exists and has data
 */

echo "=== REAL DATABASE VERIFICATION ===\n\n";

// Database connection
$host = '127.0.0.1';
$dbname = 'jcepnzzkmj';
$username = 'jcepnzzkmj';
$password = 'wprKh9Jq63';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✅ Database connection: SUCCESS\n\n";
} catch (PDOException $e) {
    echo "❌ Database connection: FAILED\n";
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}

// Check if employee_mappings table exists
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'employee_mappings'");
    $exists = $stmt->fetch();
    
    if ($exists) {
        echo "✅ employee_mappings table: EXISTS\n\n";
    } else {
        echo "❌ employee_mappings table: DOES NOT EXIST\n";
        exit(1);
    }
} catch (PDOException $e) {
    echo "❌ Table check failed: " . $e->getMessage() . "\n";
    exit(1);
}

// Get table record counts
echo "--- Record Counts ---\n";
try {
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as total_mappings,
            SUM(CASE WHEN mapping_status = 'approved' THEN 1 ELSE 0 END) as approved_count,
            SUM(CASE WHEN mapping_status = 'pending' THEN 1 ELSE 0 END) as pending_count,
            SUM(CASE WHEN vend_customer_id IS NOT NULL THEN 1 ELSE 0 END) as mapped_count
        FROM employee_mappings
    ");
    $counts = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "Total mappings: " . $counts['total_mappings'] . "\n";
    echo "Approved: " . $counts['approved_count'] . "\n";
    echo "Pending: " . $counts['pending_count'] . "\n";
    echo "With Vend customer: " . $counts['mapped_count'] . "\n\n";
    
    if ($counts['total_mappings'] == 0) {
        echo "⚠️  WARNING: Table exists but has no records\n\n";
    } else {
        echo "✅ Table has " . $counts['total_mappings'] . " records\n\n";
    }
} catch (PDOException $e) {
    echo "❌ Count query failed: " . $e->getMessage() . "\n";
    exit(1);
}

// Show sample records
echo "--- Sample Records (First 5) ---\n";
try {
    $stmt = $pdo->query("
        SELECT 
            id,
            xero_employee_id,
            vend_customer_id,
            employee_name,
            mapping_status,
            created_at
        FROM employee_mappings
        ORDER BY id
        LIMIT 5
    ");
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($records)) {
        echo "No records found\n";
    } else {
        foreach ($records as $record) {
            echo sprintf(
                "ID %d: %s (Xero: %s) → Vend: %s [%s]\n",
                $record['id'],
                $record['employee_name'],
                $record['xero_employee_id'],
                $record['vend_customer_id'] ?: 'NOT MAPPED',
                $record['mapping_status']
            );
        }
    }
} catch (PDOException $e) {
    echo "❌ Sample query failed: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n--- Testing Bypass Status ---\n";
$bypassFile = __DIR__ . '/testing-bot-bypass.php';
if (file_exists($bypassFile)) {
    $content = file_get_contents($bypassFile);
    if (preg_match('/private\s+\$bypassEnabled\s*=\s*(true|false);/', $content, $matches)) {
        $enabled = $matches[1];
        if ($enabled === 'false') {
            echo "✅ Testing bypass: DISABLED (real database active)\n";
        } else {
            echo "⚠️  Testing bypass: ENABLED (mock data active)\n";
        }
    }
} else {
    echo "✅ Testing bypass file not found (real database by default)\n";
}

echo "\n--- Other Required Tables ---\n";
$requiredTables = [
    'cis_staff_vend_map',
    'xero_payroll_deductions',
    'vend_customers',
    'users'
];

foreach ($requiredTables as $table) {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM $table");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "✅ $table: " . number_format($result['count']) . " records\n";
    } catch (PDOException $e) {
        echo "❌ $table: ERROR - " . $e->getMessage() . "\n";
    }
}

echo "\n=== VERIFICATION COMPLETE ===\n";
echo "\nNext step: Run API tests with real database\n";
echo "Command: php test-api-endpoints.php\n";
