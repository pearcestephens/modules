#!/usr/bin/env php
<?php
/**
 * Quick Migration: Create Employee Mappings Table
 * 
 * This script creates the employee_mappings table needed for Phase 1
 * 
 * Usage: php create-employee-mappings-table.php
 */

declare(strict_types=1);

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "EMPLOYEE MAPPINGS TABLE CREATION\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

// Load bootstrap
echo "Loading bootstrap...\n";
require_once __DIR__ . '/../bootstrap.php';

// Get PDO connection
echo "Connecting to database...\n";
try {
    $pdo = cis_resolve_pdo();
    
    // Get database name
    $dbName = $pdo->query("SELECT DATABASE()")->fetchColumn();
    echo "✓ Connected to database: {$dbName}\n\n";
    
} catch (Exception $e) {
    echo "✗ Database connection failed: " . $e->getMessage() . "\n";
    echo "\nTrying direct connection...\n";
    
    // Try direct connection using environment variables
    $host = getenv('DB_HOST') ?: 'localhost';
    $dbname = getenv('DB_NAME') ?: 'jcepnzzkmj';
    $user = getenv('DB_USER') ?: '';
    $pass = getenv('DB_PASS') ?: '';
    
    try {
        $dsn = "mysql:host={$host};dbname={$dbname};charset=utf8mb4";
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
        echo "✓ Connected to database: {$dbname}\n\n";
    } catch (PDOException $e) {
        echo "✗ Direct connection also failed: " . $e->getMessage() . "\n";
        echo "\nPlease check your database credentials in .env file or environment variables.\n";
        exit(1);
    }
}

// Create table
echo "Creating employee_mappings table...\n";

$sql = "
CREATE TABLE IF NOT EXISTS employee_mappings (
  id INT PRIMARY KEY AUTO_INCREMENT,
  
  -- Xero employee data
  xero_employee_id VARCHAR(100) NOT NULL COMMENT 'Xero employee UUID',
  employee_name VARCHAR(255) NOT NULL COMMENT 'Full name from Xero',
  employee_email VARCHAR(255) COMMENT 'Email from Xero payroll',
  
  -- Vend customer data
  vend_customer_id VARCHAR(100) NOT NULL COMMENT 'Vend customer ID',
  vend_customer_name VARCHAR(255) COMMENT 'Name from Vend',
  
  -- Mapping metadata
  mapping_confidence DECIMAL(3,2) DEFAULT 1.00 COMMENT '0.00-1.00 confidence score',
  mapped_by ENUM('manual', 'auto_email', 'auto_name', 'auto_fuzzy') DEFAULT 'manual',
  mapped_by_user_id INT COMMENT 'User who created mapping',
  
  -- Audit trail
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  -- Constraints
  UNIQUE KEY uk_xero_employee (xero_employee_id),
  INDEX idx_vend_customer (vend_customer_id),
  INDEX idx_email (employee_email),
  INDEX idx_confidence (mapping_confidence),
  INDEX idx_mapped_by (mapped_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Maps Xero employees to Vend customers for payment allocation'
";

try {
    $pdo->exec($sql);
    echo "✓ Table created successfully!\n\n";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'already exists') !== false) {
        echo "⚠ Table already exists\n\n";
    } else {
        echo "✗ Error creating table: " . $e->getMessage() . "\n";
        exit(1);
    }
}

// Verify table structure
echo "Verifying table structure...\n";
try {
    $stmt = $pdo->query("DESCRIBE employee_mappings");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "\nColumns:\n";
    foreach ($columns as $col) {
        echo "  • {$col['Field']} ({$col['Type']}) {$col['Key']}\n";
    }
    
    echo "\nIndexes:\n";
    $stmt = $pdo->query("SHOW INDEX FROM employee_mappings");
    $indexes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $indexNames = array_unique(array_column($indexes, 'Key_name'));
    foreach ($indexNames as $indexName) {
        echo "  • {$indexName}\n";
    }
    
    // Check current count
    $count = $pdo->query("SELECT COUNT(*) FROM employee_mappings")->fetchColumn();
    echo "\nCurrent mappings: {$count}\n";
    
} catch (PDOException $e) {
    echo "✗ Verification failed: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "✓ EMPLOYEE MAPPINGS TABLE READY!\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "\nNext steps:\n";
echo "1. Verify API endpoints work\n";
echo "2. Build employee mapping UI\n";
echo "3. Start mapping 54 unmapped employees\n\n";
