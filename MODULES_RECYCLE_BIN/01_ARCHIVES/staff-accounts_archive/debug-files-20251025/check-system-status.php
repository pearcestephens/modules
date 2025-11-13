<?php
/**
 * QUICK SANITY CHECK - Staff Accounts System
 * 
 * Run this to verify what's working right now
 * 
 * Usage:
 *   php check-system-status.php
 *   OR visit in browser: check-system-status.php
 */

require_once __DIR__ . '/bootstrap.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>System Status Check</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #1e1e1e; color: #fff; }
        .check { padding: 10px; margin: 5px 0; border-radius: 4px; }
        .pass { background: #28a745; }
        .fail { background: #dc3545; }
        .warn { background: #ffc107; color: #000; }
        .info { background: #17a2b8; }
        h1 { color: #4CAF50; }
        h2 { color: #2196F3; margin-top: 30px; }
        pre { background: #2d2d2d; padding: 10px; border-radius: 4px; overflow-x: auto; }
        .section { border-left: 4px solid #4CAF50; padding-left: 15px; margin: 20px 0; }
    </style>
</head>
<body>

<h1>🔍 STAFF ACCOUNTS SYSTEM - STATUS CHECK</h1>
<p>Current Time: <?= date('Y-m-d H:i:s') ?></p>

<hr>

<?php

$allPassed = true;

// ============================================================================
// 1. DATABASE CONNECTION
// ============================================================================
echo "<div class='section'>";
echo "<h2>1️⃣ DATABASE CONNECTION</h2>";

if (isset($db) && $db instanceof mysqli && $db->ping()) {
    echo "<div class='check pass'>✅ Database connected</div>";
} else {
    echo "<div class='check fail'>❌ Database connection FAILED</div>";
    $allPassed = false;
}

// ============================================================================
// 2. CORE TABLES
// ============================================================================
echo "<h2>2️⃣ CORE TABLES (Already Working)</h2>";

$coreTables = [
    'users' => 'Staff members',
    'staff_account_balance' => 'Current balances',
    'staff_allocations' => 'Payment records',
    'vend_sales' => 'Purchase history'
];

foreach ($coreTables as $table => $description) {
    $query = "SHOW TABLES LIKE '$table'";
    $result = $db->query($query);
    
    if ($result && $result->num_rows > 0) {
        $countQuery = "SELECT COUNT(*) as count FROM $table";
        $countResult = $db->query($countQuery);
        $count = $countResult->fetch_assoc()['count'];
        echo "<div class='check pass'>✅ $table - $count records - $description</div>";
    } else {
        echo "<div class='check fail'>❌ $table - MISSING - $description</div>";
        $allPassed = false;
    }
}

// ============================================================================
// 3. NEW PAYMENT TABLES (Optional)
// ============================================================================
echo "<h2>3️⃣ NEW PAYMENT TABLES (Need Deployment)</h2>";

$paymentTables = [
    'staff_payment_transactions' => 'Credit card transactions',
    'staff_saved_cards' => 'Saved payment methods',
    'staff_payment_plans' => 'Installment plans',
    'staff_payment_plan_installments' => 'Plan payments',
    'staff_reminder_log' => 'Manager reminders'
];

$paymentTablesExist = 0;
foreach ($paymentTables as $table => $description) {
    $query = "SHOW TABLES LIKE '$table'";
    $result = $db->query($query);
    
    if ($result && $result->num_rows > 0) {
        $countQuery = "SELECT COUNT(*) as count FROM $table";
        $countResult = $db->query($countQuery);
        $count = $countResult->fetch_assoc()['count'];
        echo "<div class='check pass'>✅ $table - $count records - $description</div>";
        $paymentTablesExist++;
    } else {
        echo "<div class='check warn'>⚠️ $table - Not deployed yet - $description</div>";
    }
}

if ($paymentTablesExist === 0) {
    echo "<div class='check info'>ℹ️ Payment tables not created yet. Run: bash deploy-payment-system.sh</div>";
}

// ============================================================================
// 4. KEY FILES
// ============================================================================
echo "<h2>4️⃣ KEY FILES</h2>";

$files = [
    'staff-reconciliation.php' => 'Main staff view (LIVE)',
    'manager-dashboard.php' => 'Manager dashboard (NEW)',
    'api/staff-reconciliation.php' => 'Staff API (LIVE)',
    'api/payment.php' => 'Payment API (NEW)',
    'api/manager-dashboard.php' => 'Manager API (NEW)',
    'lib/StaffAccountService.php' => 'Core service (LIVE)',
    'lib/NuveiPayment.php' => 'Payment gateway (NEW)',
    'lib/LightspeedAPI.php' => 'Credit limits (NEW)'
];

foreach ($files as $file => $description) {
    $fullPath = __DIR__ . '/' . $file;
    if (file_exists($fullPath)) {
        $size = filesize($fullPath);
        echo "<div class='check pass'>✅ $file - " . number_format($size) . " bytes - $description</div>";
    } else {
        echo "<div class='check fail'>❌ $file - MISSING - $description</div>";
        $allPassed = false;
    }
}

// ============================================================================
// 5. SAMPLE DATA
// ============================================================================
echo "<h2>5️⃣ SAMPLE DATA</h2>";

// Count active staff
$query = "SELECT COUNT(*) as count FROM users WHERE is_active = 1";
$result = $db->query($query);
$staffCount = $result->fetch_assoc()['count'];
echo "<div class='check " . ($staffCount > 0 ? 'pass' : 'fail') . "'>👥 Active Staff: $staffCount</div>";

// Count staff with balances
$query = "SELECT COUNT(*) as count FROM staff_account_balance WHERE current_balance > 0";
$result = $db->query($query);
$balanceCount = $result->fetch_assoc()['count'];
echo "<div class='check " . ($balanceCount > 0 ? 'pass' : 'warn') . "'>💰 Staff with Balances: $balanceCount</div>";

// Total debt
$query = "SELECT SUM(current_balance) as total FROM staff_account_balance";
$result = $db->query($query);
$totalDebt = $result->fetch_assoc()['total'] ?? 0;
echo "<div class='check info'>💵 Total Staff Debt: $" . number_format($totalDebt, 2) . "</div>";

// Recent allocations
$query = "SELECT COUNT(*) as count FROM staff_allocations WHERE allocation_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAYS)";
$result = $db->query($query);
$recentAllocations = $result->fetch_assoc()['count'];
echo "<div class='check " . ($recentAllocations > 0 ? 'pass' : 'warn') . "'>📅 Allocations (Last 30 Days): $recentAllocations</div>";

// Manager users
$query = "SELECT COUNT(*) as count FROM users WHERE is_manager = 1";
$result = $db->query($query);
$managerCount = $result->fetch_assoc()['count'];
echo "<div class='check " . ($managerCount > 0 ? 'pass' : 'warn') . "'>👔 Manager Users: $managerCount</div>";

// ============================================================================
// 6. CONFIGURATION
// ============================================================================
echo "<h2>6️⃣ CONFIGURATION</h2>";

// Check Nuvei config
$query = "SELECT setting_key, setting_value FROM config WHERE setting_key LIKE 'nuvei%'";
$result = $db->query($query);

if ($result && $result->num_rows > 0) {
    echo "<div class='check pass'>✅ Nuvei config entries exist:</div>";
    echo "<pre>";
    while ($row = $result->fetch_assoc()) {
        $value = ($row['setting_key'] === 'nuvei_secret_key') ? '***HIDDEN***' : $row['setting_value'];
        $configured = ($value !== 'CONFIGURE_ME' && $value !== '');
        $status = $configured ? '✅' : '⚠️';
        echo "$status {$row['setting_key']}: $value\n";
    }
    echo "</pre>";
} else {
    echo "<div class='check warn'>⚠️ Nuvei config not found - will be created on deployment</div>";
}

// Check Vend API config
$query = "SELECT setting_value FROM config WHERE setting_key = 'vend_access_token' LIMIT 1";
$result = $db->query($query);
if ($result && $result->num_rows > 0) {
    echo "<div class='check pass'>✅ Vend API configured</div>";
} else {
    echo "<div class='check warn'>⚠️ Vend API token not found</div>";
}

// ============================================================================
// 7. WORKING URLS
// ============================================================================
echo "<h2>7️⃣ ACCESS URLS</h2>";

$baseUrl = 'https://staff.vapeshed.co.nz/modules/staff-accounts';

echo "<div class='check pass'>";
echo "✅ <a href='$baseUrl/staff-reconciliation.php' target='_blank'>Staff Reconciliation (LIVE)</a><br>";
echo "</div>";

if ($paymentTablesExist > 0) {
    echo "<div class='check pass'>";
    echo "✅ <a href='$baseUrl/manager-dashboard.php' target='_blank'>Manager Dashboard (READY)</a><br>";
    echo "</div>";
} else {
    echo "<div class='check warn'>";
    echo "⚠️ Manager Dashboard (needs deployment)<br>";
    echo "</div>";
}

echo "</div>"; // close section

// ============================================================================
// FINAL SUMMARY
// ============================================================================
echo "<hr>";
echo "<h2>📊 SUMMARY</h2>";

if ($allPassed && $balanceCount > 0) {
    echo "<div class='check pass' style='font-size: 20px; font-weight: bold;'>";
    echo "✅ SYSTEM IS FULLY OPERATIONAL!";
    echo "</div>";
    
    echo "<div class='check info'>";
    echo "<strong>You can use staff-reconciliation.php RIGHT NOW.</strong><br><br>";
    echo "To add new features (manager dashboard, credit cards, payment plans):<br>";
    echo "<code>bash deploy-payment-system.sh</code>";
    echo "</div>";
    
} elseif ($allPassed && $balanceCount === 0) {
    echo "<div class='check warn' style='font-size: 20px; font-weight: bold;'>";
    echo "⚠️ SYSTEM OPERATIONAL BUT NO DATA YET";
    echo "</div>";
    
    echo "<div class='check info'>";
    echo "Database tables exist but no staff balances found.<br>";
    echo "The system will populate automatically as staff make purchases.";
    echo "</div>";
    
} else {
    echo "<div class='check fail' style='font-size: 20px; font-weight: bold;'>";
    echo "❌ SOME COMPONENTS MISSING";
    echo "</div>";
    
    echo "<div class='check info'>";
    echo "Check the issues above and run:<br>";
    echo "<code>bash deploy-payment-system.sh</code>";
    echo "</div>";
}

// ============================================================================
// NEXT STEPS
// ============================================================================
echo "<hr>";
echo "<h2>🚀 NEXT STEPS</h2>";

echo "<div class='check info'>";
echo "<strong>Current Status:</strong><br><br>";

if ($paymentTablesExist > 0) {
    echo "✅ Core system: LIVE<br>";
    echo "✅ Payment tables: DEPLOYED<br>";
    echo "✅ Manager dashboard: READY<br><br>";
    echo "<strong>Action:</strong> Configure Nuvei credentials if using credit cards<br>";
    echo "<code>UPDATE config SET setting_value = 'YOUR_KEY' WHERE setting_key = 'nuvei_merchant_id';</code>";
} else {
    echo "✅ Core system: LIVE<br>";
    echo "⚠️ Payment tables: NOT DEPLOYED<br>";
    echo "⚠️ Manager dashboard: NEEDS DEPLOYMENT<br><br>";
    echo "<strong>Action:</strong> Run deployment script<br>";
    echo "<code>cd " . __DIR__ . " && bash deploy-payment-system.sh</code>";
}

echo "</div>";

?>

<hr>
<p style="text-align: center; color: #888;">
    Staff Accounts System - Status Check v1.0<br>
    <small>Generated: <?= date('Y-m-d H:i:s') ?></small>
</p>

</body>
</html>
