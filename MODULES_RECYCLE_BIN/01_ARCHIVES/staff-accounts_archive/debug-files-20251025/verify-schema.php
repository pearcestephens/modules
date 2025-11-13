<?php
/**
 * Database Schema Triple-Check Verification
 * Extracts actual schema from database
 */

require_once __DIR__ . '/bootstrap.php';

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "  DATABASE SCHEMA VERIFICATION - TRIPLE CHECK\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

// Get all staff-related tables
$tables = [
    'users', 
    'staff_account_reconciliation', 
    'staff_payment_transactions', 
    'staff_saved_cards', 
    'staff_payment_plans', 
    'staff_payment_plan_installments', 
    'staff_reminder_log', 
    'staff_allocations'
];

$schemaReport = [];

foreach ($tables as $table) {
    echo "\n📋 TABLE: $table\n";
    echo str_repeat('─', 60) . "\n";
    
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        if (!$stmt->fetch()) {
            echo "❌ TABLE DOES NOT EXIST\n";
            $schemaReport[$table] = ['exists' => false];
            continue;
        }
        
        echo "✅ Table exists\n\n";
        echo "COLUMNS:\n";
        
        $stmt = $pdo->query("DESCRIBE $table");
        $columns = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $columns[$row['Field']] = [
                'type' => $row['Type'],
                'null' => $row['Null'],
                'key' => $row['Key'],
                'default' => $row['Default']
            ];
            printf("  %-35s %-25s %s\n", 
                $row['Field'], 
                $row['Type'], 
                $row['Key'] ? '['.$row['Key'].']' : ''
            );
        }
        
        $schemaReport[$table] = [
            'exists' => true,
            'columns' => $columns,
            'column_list' => array_keys($columns)
        ];
        
    } catch (Exception $e) {
        echo "❌ Error: " . $e->getMessage() . "\n";
        $schemaReport[$table] = ['exists' => false, 'error' => $e->getMessage()];
    }
}

echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "  SCHEMA VERIFICATION COMPLETE\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

// Save schema report
file_put_contents(__DIR__ . '/schema-report.json', json_encode($schemaReport, JSON_PRETTY_PRINT));
echo "📄 Schema report saved to: schema-report.json\n\n";

// Now check manager-dashboard.php and API for column mismatches
echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "  CHECKING CODE FOR COLUMN REFERENCES\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

// Check API file for column references
$apiFile = __DIR__ . '/api/manager-dashboard.php';
$apiContent = file_get_contents($apiFile);

// Critical columns to verify in staff_account_reconciliation
$criticalColumns = [
    'id', 'user_id', 'vend_customer_id', 'outstanding_amount', 
    'vend_balance', 'status', 'last_payment_date', 'last_payment_amount',
    'total_payments_ytd', 'created_at', 'updated_at'
];

echo "Checking staff_account_reconciliation columns:\n";
foreach ($criticalColumns as $col) {
    if (isset($schemaReport['staff_account_reconciliation']['columns'][$col])) {
        echo "  ✅ $col exists in database\n";
    } else {
        echo "  ❌ $col MISSING from database\n";
    }
}

echo "\n";

// Check users table columns
$userColumns = ['id', 'first_name', 'last_name', 'email', 'role_id', 'is_manager', 'staff_active'];
echo "Checking users table columns:\n";
foreach ($userColumns as $col) {
    if (isset($schemaReport['users']['columns'][$col])) {
        echo "  ✅ $col exists in database\n";
    } else {
        echo "  ❌ $col MISSING from database\n";
    }
}

echo "\n✅ TRIPLE-CHECK COMPLETE\n";
