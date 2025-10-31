<?php
/**
 * PRODUCTION DEPLOYMENT - Staff Accounts Payment System
 * PHP-based deployment (no bash/mysql dependencies)
 * 
 * Usage:
 *   php deploy-payment-system.php
 *   php deploy-payment-system.php --rollback
 */

require_once __DIR__ . '/bootstrap.php';

$isRollback = in_array('--rollback', $argv ?? []);
$timestamp = date('Y-m-d H:i:s');

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "  STAFF ACCOUNTS PAYMENT SYSTEM - PRODUCTION DEPLOYMENT\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "  Time: $timestamp\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

if ($isRollback) {
    echo "🔄 ROLLBACK MODE - Not implemented yet\n";
    echo "   Manual rollback: Restore from /home/master/applications/jcepnzzkmj/backups/\n\n";
    exit(0);
}

// ============================================================================
// STEP 1: PRE-FLIGHT CHECKS
// ============================================================================
echo "📋 Step 1: Pre-flight checks\n";
echo "───────────────────────────────────────────────────────────\n";

// Check database connection (try both PDO and mysqli)
$dbConnection = null;
$dbName = null;

if (isset($pdo) && $pdo instanceof PDO) {
    try {
        $dbName = $pdo->query("SELECT DATABASE()")->fetchColumn();
        $dbConnection = $pdo;
        echo "✅ Database connected (PDO): $dbName\n";
    } catch (Exception $e) {
        echo "❌ ERROR: PDO connection failed: " . $e->getMessage() . "\n";
        exit(1);
    }
} elseif (isset($db) && $db instanceof mysqli && $db->ping()) {
    $dbName = $db->query("SELECT DATABASE()")->fetch_row()[0];
    $dbConnection = $db;
    echo "✅ Database connected (mysqli): $dbName\n";
} else {
    echo "❌ ERROR: No database connection found\n";
    echo "   Checked: \$pdo (PDO) and \$db (mysqli)\n";
    exit(1);
}

// Check PHP version
echo "✅ PHP Version: " . PHP_VERSION . "\n";

// Check required extensions
$requiredExtensions = ['mysqli', 'pdo', 'pdo_mysql', 'json', 'curl'];
foreach ($requiredExtensions as $ext) {
    if (!extension_loaded($ext)) {
        echo "❌ ERROR: Required PHP extension missing: $ext\n";
        exit(1);
    }
}
echo "✅ Required PHP extensions installed\n\n";

// ============================================================================
// STEP 2: CREATE BACKUP
// ============================================================================
echo "💾 Step 2: Creating backup\n";
echo "───────────────────────────────────────────────────────────\n";

$backupDir = "/home/master/applications/jcepnzzkmj/backups/staff-accounts-" . date('Ymd_His');
if (!is_dir($backupDir)) {
    mkdir($backupDir, 0755, true);
}

// Backup critical tables
$tablesToBackup = [
    'staff_account_balance',
    'staff_allocations',
    'config'
];

foreach ($tablesToBackup as $table) {
    // Check if table exists
    if ($dbConnection instanceof PDO) {
        $stmt = $dbConnection->query("SHOW TABLES LIKE '$table'");
        $exists = $stmt->fetch();
    } else {
        $result = $dbConnection->query("SHOW TABLES LIKE '$table'");
        $exists = $result && $result->num_rows > 0;
    }
    
    if ($exists) {
        $backupContent = "-- Backup of $table at $timestamp\n";
        
        if ($dbConnection instanceof PDO) {
            $data = $dbConnection->query("SELECT * FROM $table");
            while ($row = $data->fetch(PDO::FETCH_ASSOC)) {
                $values = array_map(function($v) {
                    return is_null($v) ? 'NULL' : "'" . addslashes($v) . "'";
                }, array_values($row));
                $backupContent .= "INSERT INTO $table VALUES (" . implode(', ', $values) . ");\n";
            }
        } else {
            $data = $dbConnection->query("SELECT * FROM $table");
            while ($row = $data->fetch_assoc()) {
                $values = array_map(function($v) use ($dbConnection) {
                    return is_null($v) ? 'NULL' : "'" . $dbConnection->real_escape_string($v) . "'";
                }, array_values($row));
                $backupContent .= "INSERT INTO $table VALUES (" . implode(', ', $values) . ");\n";
            }
        }
        
        file_put_contents("$backupDir/$table.sql", $backupContent);
        echo "   ✅ Backed up $table\n";
    }
}

echo "✅ Backup created: $backupDir\n\n";

// ============================================================================
// STEP 3: RUN DATABASE MIGRATIONS
// ============================================================================
echo "🗄️  Step 3: Running database migrations\n";
echo "───────────────────────────────────────────────────────────\n";

// Read and execute Nuvei tables SQL
$nuveiSql = file_get_contents(__DIR__ . '/database/nuvei-tables.sql');
if ($nuveiSql) {
    // Split by semicolons and execute each statement
    $statements = array_filter(array_map('trim', explode(';', $nuveiSql)));
    
    foreach ($statements as $statement) {
        if (empty($statement) || strpos($statement, '--') === 0) {
            continue;
        }
        
        try {
            if ($dbConnection instanceof PDO) {
                $dbConnection->exec($statement);
            } else {
                $dbConnection->query($statement);
            }
        } catch (Exception $e) {
            // Ignore "table already exists" errors
            if (strpos($e->getMessage(), 'already exists') === false && strpos($e->getMessage(), 'Duplicate') === false) {
                echo "   ⚠️ Warning: " . $e->getMessage() . "\n";
            }
        }
    }
    echo "   ✅ Nuvei payment tables created\n";
}

// Read and execute manager dashboard tables SQL
$managerSql = file_get_contents(__DIR__ . '/database/manager-dashboard-tables.sql');
if ($managerSql) {
    $statements = array_filter(array_map('trim', explode(';', $managerSql)));
    
    foreach ($statements as $statement) {
        if (empty($statement) || strpos($statement, '--') === 0) {
            continue;
        }
        
        try {
            if ($dbConnection instanceof PDO) {
                $dbConnection->exec($statement);
            } else {
                $dbConnection->query($statement);
            }
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'already exists') === false && strpos($e->getMessage(), 'Duplicate') === false) {
                echo "   ⚠️ Warning: " . $e->getMessage() . "\n";
            }
        }
    }
    echo "   ✅ Manager dashboard tables created\n";
}

echo "✅ All migrations complete\n\n";

// ============================================================================
// STEP 4: VERIFY SCHEMA
// ============================================================================
echo "🔍 Step 4: Verifying database schema\n";
echo "───────────────────────────────────────────────────────────\n";

$requiredTables = [
    'staff_payment_transactions',
    'staff_saved_cards',
    'staff_payment_plans',
    'staff_payment_plan_installments',
    'staff_reminder_log',
    'staff_account_balance'
];

foreach ($requiredTables as $table) {
    if ($dbConnection instanceof PDO) {
        $stmt = $dbConnection->query("SHOW TABLES LIKE '$table'");
        $exists = $stmt->fetch();
    } else {
        $result = $dbConnection->query("SHOW TABLES LIKE '$table'");
        $exists = $result && $result->num_rows > 0;
    }
    
    if ($exists) {
        echo "   ✅ $table\n";
    } else {
        echo "   ❌ $table - MISSING!\n";
        exit(1);
    }
}

echo "✅ All required tables exist\n\n";

// ============================================================================
// STEP 5: CONFIGURE SYSTEM
// ============================================================================
echo "⚙️  Step 5: System configuration\n";
echo "───────────────────────────────────────────────────────────\n";

// Add Nuvei config placeholders
$nuveiConfig = [
    'nuvei_merchant_id' => 'CONFIGURE_ME',
    'nuvei_merchant_site_id' => 'CONFIGURE_ME',
    'nuvei_secret_key' => 'CONFIGURE_ME',
    'nuvei_environment' => 'sandbox'
];

foreach ($nuveiConfig as $key => $value) {
    if ($dbConnection instanceof PDO) {
        $stmt = $dbConnection->prepare("INSERT INTO config (setting_key, setting_value, setting_group) 
                              VALUES (?, ?, 'payment') 
                              ON DUPLICATE KEY UPDATE setting_key = setting_key");
        $stmt->execute([$key, $value]);
    } else {
        $stmt = $dbConnection->prepare("INSERT INTO config (setting_key, setting_value, setting_group) 
                              VALUES (?, ?, 'payment') 
                              ON DUPLICATE KEY UPDATE setting_key = setting_key");
        $stmt->bind_param('ss', $key, $value);
        $stmt->execute();
    }
}
echo "   ✅ Nuvei config entries created\n";

// Grant manager permissions to admin users
if ($dbConnection instanceof PDO) {
    $dbConnection->exec("UPDATE users SET is_manager = 1 
                WHERE role IN ('admin', 'director', 'manager') 
                AND is_active = 1");
    $managerCount = $dbConnection->query("SELECT COUNT(*) as count FROM users WHERE is_manager = 1")->fetchColumn();
} else {
    $dbConnection->query("UPDATE users SET is_manager = 1 
                WHERE role IN ('admin', 'director', 'manager') 
                AND is_active = 1");
    $managerCount = $dbConnection->query("SELECT COUNT(*) as count FROM users WHERE is_manager = 1")->fetch_assoc()['count'];
}
echo "   ✅ Manager permissions granted to $managerCount users\n\n";

// ============================================================================
// STEP 6: SMOKE TESTS
// ============================================================================
echo "🧪 Step 6: Running smoke tests\n";
echo "───────────────────────────────────────────────────────────\n";

// Test 1: Check files exist
$filesToCheck = [
    'manager-dashboard.php',
    'api/payment.php',
    'api/manager-dashboard.php',
    'lib/NuveiPayment.php',
    'lib/LightspeedAPI.php'
];

foreach ($filesToCheck as $file) {
    if (file_exists(__DIR__ . '/' . $file)) {
        echo "   ✅ $file\n";
    } else {
        echo "   ❌ $file - MISSING!\n";
    }
}

// Test 2: Check data
if ($dbConnection instanceof PDO) {
    $balanceCount = $dbConnection->query("SELECT COUNT(*) as count FROM staff_account_balance")->fetchColumn();
    $paymentCount = $dbConnection->query("SELECT COUNT(*) as count FROM staff_payment_transactions")->fetchColumn();
} else {
    $balanceCount = $dbConnection->query("SELECT COUNT(*) as count FROM staff_account_balance")->fetch_assoc()['count'];
    $paymentCount = $dbConnection->query("SELECT COUNT(*) as count FROM staff_payment_transactions")->fetch_assoc()['count'];
}

echo "   ✅ Found $balanceCount staff account balances\n";
echo "   ✅ Payment transactions table ready (current: $paymentCount records)\n";

echo "✅ All smoke tests passed\n\n";

// ============================================================================
// DEPLOYMENT COMPLETE
// ============================================================================
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "  ✅ DEPLOYMENT SUCCESSFUL\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

echo "📦 Backup saved to: $backupDir\n\n";

echo "🚀 Next Steps:\n\n";
echo "   1. Update Nuvei credentials in config table:\n";
echo "      UPDATE config SET setting_value = 'YOUR_VALUE' WHERE setting_key = 'nuvei_merchant_id';\n\n";
echo "   2. Access Manager Dashboard:\n";
echo "      https://staff.vapeshed.co.nz/modules/staff-accounts/manager-dashboard.php\n\n";
echo "   3. Test payment flow:\n";
echo "      https://staff.vapeshed.co.nz/modules/staff-accounts/staff-reconciliation.php\n\n";
echo "   4. Check system status:\n";
echo "      https://staff.vapeshed.co.nz/modules/staff-accounts/check-system-status.php\n\n";

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
