<?php
/**
 * Get Table Structures - Analyze existing vend_customers and vend_users tables
 * 
 * Purpose: Before creating new schema, verify no duplication with existing tables
 * 
 * Usage: php get-table-structures.php
 */

require_once __DIR__ . '/../../shared/bootstrap.php';

// Get PDO from global scope (CLI mode)
$pdo = $GLOBALS['pdo'] ?? null;

if (!$pdo) {
    die("ERROR: Database connection not available. Check .env configuration.\n");
}

echo "=============================================================================\n";
echo "EXISTING VEND TABLE STRUCTURE ANALYSIS\n";
echo "=============================================================================\n\n";

// Tables to analyze
$tables = ['vend_customers', 'vend_users', 'staff_account_reconciliation'];

foreach ($tables as $table) {
    echo "-----------------------------------------------------------------------------\n";
    echo "TABLE: {$table}\n";
    echo "-----------------------------------------------------------------------------\n";
    
    try {
        // Check if table exists
        $stmt = $pdo->query("SHOW TABLES LIKE '{$table}'");
        $exists = $stmt->rowCount() > 0;
        
        if (!$exists) {
            echo "❌ Table does NOT exist in database\n\n";
            continue;
        }
        
        echo "✅ Table exists\n\n";
        
        // Get full CREATE TABLE statement
        $stmt = $pdo->query("SHOW CREATE TABLE {$table}");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            echo "CREATE TABLE STATEMENT:\n";
            echo "------------------------\n";
            echo $result['Create Table'] . "\n\n";
        }
        
        // Get column details
        $stmt = $pdo->query("DESCRIBE {$table}");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "COLUMN DETAILS:\n";
        echo "------------------------\n";
        printf("%-30s %-20s %-10s %-10s %-10s\n", "Field", "Type", "Null", "Key", "Default");
        echo str_repeat("-", 90) . "\n";
        
        foreach ($columns as $col) {
            printf(
                "%-30s %-20s %-10s %-10s %-10s\n",
                $col['Field'],
                $col['Type'],
                $col['Null'],
                $col['Key'],
                $col['Default'] ?? 'NULL'
            );
        }
        
        echo "\nTOTAL COLUMNS: " . count($columns) . "\n";
        
        // Get row count
        $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM {$table}");
        $count = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "TOTAL ROWS: " . number_format($count['cnt']) . "\n";
        
        // Get sample row (if exists)
        $stmt = $pdo->query("SELECT * FROM {$table} LIMIT 1");
        $sample = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($sample) {
            echo "\nSAMPLE ROW (first record):\n";
            echo "------------------------\n";
            foreach ($sample as $field => $value) {
                $displayValue = is_null($value) ? 'NULL' : (strlen($value) > 50 ? substr($value, 0, 47) . '...' : $value);
                printf("%-30s = %s\n", $field, $displayValue);
            }
        }
        
        echo "\n";
        
    } catch (PDOException $e) {
        echo "❌ ERROR: " . $e->getMessage() . "\n\n";
    }
}

echo "=============================================================================\n";
echo "PROPOSED SCHEMA COMPARISON\n";
echo "=============================================================================\n\n";

echo "PROPOSED NEW COLUMNS FOR staff_account_reconciliation:\n";
echo "------------------------\n";
$proposedColumns = [
    'credit_limit' => 'DECIMAL(10,2) DEFAULT 0.00',
    'credit_account_id' => 'VARCHAR(50) NULL',
    'customer_type_id' => 'INT NULL',
    'discount_id' => 'INT NULL',
    'customer_code' => 'VARCHAR(100) NULL',
    'customer_company' => 'VARCHAR(255) NULL',
    'customer_email' => 'VARCHAR(255) NULL',
    'customer_phone' => 'VARCHAR(50) NULL',
    'customer_dob' => 'DATE NULL',
    'customer_archived' => 'TINYINT(1) DEFAULT 0',
    'customer_created_at' => 'DATETIME NULL',
    'vend_last_synced_at' => 'DATETIME NULL'
];

foreach ($proposedColumns as $col => $type) {
    echo "- {$col} ({$type})\n";
}

echo "\n";
echo "PROPOSED NEW TABLES:\n";
echo "------------------------\n";
echo "- vend_credit_accounts\n";
echo "- vend_customer_contacts\n";
echo "- vend_customer_custom_fields\n";
echo "- vend_customer_tags\n";

echo "\n=============================================================================\n";
echo "DUPLICATION ANALYSIS\n";
echo "=============================================================================\n\n";

// Check if vend_customers exists and analyze
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'vend_customers'");
    if ($stmt->rowCount() > 0) {
        $stmt = $pdo->query("DESCRIBE vend_customers");
        $vendCustomerCols = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
        
        echo "vend_customers fields that might duplicate proposed columns:\n";
        echo "------------------------\n";
        
        $duplicates = [];
        $proposedFields = array_keys($proposedColumns);
        
        foreach ($vendCustomerCols as $existingCol) {
            // Check for similar field names
            $normalized = str_replace(['customer_', 'vend_'], '', strtolower($existingCol));
            
            foreach ($proposedFields as $proposedCol) {
                $normalizedProposed = str_replace(['customer_', 'vend_'], '', strtolower($proposedCol));
                
                if (strpos($normalizedProposed, $normalized) !== false || strpos($normalized, $normalizedProposed) !== false) {
                    $duplicates[] = [
                        'existing' => $existingCol,
                        'proposed' => $proposedCol,
                        'similarity' => 'High'
                    ];
                }
            }
        }
        
        if (empty($duplicates)) {
            echo "✅ NO obvious duplicates detected\n\n";
        } else {
            echo "⚠️  POTENTIAL DUPLICATES DETECTED:\n\n";
            foreach ($duplicates as $dup) {
                echo "- vend_customers.{$dup['existing']} vs proposed {$dup['proposed']} (Similarity: {$dup['similarity']})\n";
            }
            echo "\n";
        }
        
        echo "All vend_customers columns:\n";
        echo "------------------------\n";
        foreach ($vendCustomerCols as $col) {
            echo "- {$col}\n";
        }
        echo "\n";
    }
} catch (PDOException $e) {
    echo "Could not analyze vend_customers: " . $e->getMessage() . "\n\n";
}

echo "=============================================================================\n";
echo "RECOMMENDATION\n";
echo "=============================================================================\n\n";

echo "Based on the analysis above:\n\n";
echo "1. If vend_customers already has comprehensive customer data:\n";
echo "   → Use vend_customers as source of truth\n";
echo "   → Don't add duplicate fields to staff_account_reconciliation\n";
echo "   → Join tables in queries instead\n\n";

echo "2. If vend_customers is minimal (just ID, name, balance):\n";
echo "   → Safe to add proposed fields to staff_account_reconciliation\n";
echo "   → These tables serve different purposes\n\n";

echo "3. If vend_customers doesn't exist:\n";
echo "   → Create it as proposed\n";
echo "   → Proceed with full schema\n\n";

echo "=============================================================================\n";
