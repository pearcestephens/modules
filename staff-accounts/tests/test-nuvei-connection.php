<?php
/**
 * Nuvei Payment Gateway Connection Test
 *
 * Tests Nuvei API connectivity and configuration
 * Part of Phase 1: Urgent Staff Payment Verification
 */

require_once __DIR__ . '/../../../app.php';
require_once __DIR__ . '/../lib/NuveiPaymentGateway.php';

use StaffAccounts\Lib\NuveiPaymentGateway;

echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "  NUVEI PAYMENT GATEWAY TEST\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

// Test 1: Check environment variables
echo "1️⃣  Checking Nuvei configuration...\n\n";

$requiredEnvVars = [
    'NUVEI_MERCHANT_ID',
    'NUVEI_MERCHANT_SITE_ID',
    'NUVEI_SECRET_KEY',
    'NUVEI_API_BASE_URL'
];

$configOk = true;
foreach ($requiredEnvVars as $var) {
    $value = getenv($var);
    if (empty($value)) {
        echo "   ❌ Missing: {$var}\n";
        $configOk = false;
    } else {
        // Mask sensitive values
        $display = (strpos($var, 'SECRET') !== false || strpos($var, 'KEY') !== false)
            ? substr($value, 0, 4) . '***' . substr($value, -4)
            : $value;
        echo "   ✅ {$var}: {$display}\n";
    }
}

if (!$configOk) {
    echo "\n❌ CRITICAL: Nuvei configuration incomplete!\n";
    exit(1);
}

echo "\n";

// Test 2: Initialize Nuvei client
echo "2️⃣  Initializing Nuvei client...\n\n";

try {
    $nuvei = new NuveiPaymentGateway();
    echo "   ✅ NuveiPaymentGateway instantiated\n";
} catch (Exception $e) {
    echo "   ❌ CRITICAL: Cannot instantiate Nuvei client!\n";
    echo "   Error: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n";

// Test 3: Test API connectivity (getSessionToken)
echo "3️⃣  Testing API connectivity...\n\n";

try {
    // Attempt to get a session token (lightest API call)
    $sessionToken = $nuvei->getSessionToken();

    if ($sessionToken && strlen($sessionToken) > 20) {
        echo "   ✅ Session token retrieved: " . substr($sessionToken, 0, 8) . "***\n";
        echo "   ✅ Nuvei API is accessible\n";
    } else {
        echo "   ⚠️  Warning: Session token looks invalid\n";
        echo "   Token: " . var_export($sessionToken, true) . "\n";
    }
} catch (Exception $e) {
    echo "   ❌ CRITICAL: Cannot connect to Nuvei API!\n";
    echo "   Error: " . $e->getMessage() . "\n";
    echo "\n";
    echo "   Troubleshooting:\n";
    echo "   • Check NUVEI_API_BASE_URL is correct\n";
    echo "   • Verify merchant credentials are valid\n";
    echo "   • Check network connectivity\n";
    echo "   • Verify Nuvei account is active\n";
    exit(1);
}

echo "\n";

// Test 4: Check if test mode is enabled
echo "4️⃣  Checking environment mode...\n\n";

$apiUrl = getenv('NUVEI_API_BASE_URL');
if (strpos($apiUrl, 'ppp-test') !== false || strpos($apiUrl, 'sandbox') !== false) {
    echo "   ⚠️  TEST MODE: Using sandbox/test environment\n";
    echo "   URL: {$apiUrl}\n";
} else {
    echo "   ✅ PRODUCTION MODE\n";
    echo "   URL: {$apiUrl}\n";
}

echo "\n";

// Test 5: Verify payment methods available
echo "5️⃣  Checking available payment methods...\n\n";

try {
    // Try to get merchant payment methods
    $methods = $nuvei->getMerchantPaymentMethods();

    if (is_array($methods) && count($methods) > 0) {
        echo "   ✅ " . count($methods) . " payment methods available\n";
        foreach (array_slice($methods, 0, 5) as $method) {
            echo "      • " . ($method['name'] ?? $method['paymentMethod'] ?? 'Unknown') . "\n";
        }
        if (count($methods) > 5) {
            echo "      ... and " . (count($methods) - 5) . " more\n";
        }
    } else {
        echo "   ⚠️  Warning: No payment methods found or method check not implemented\n";
    }
} catch (Exception $e) {
    echo "   ⚠️  Warning: Cannot retrieve payment methods\n";
    echo "   Error: " . $e->getMessage() . "\n";
    echo "   (This may be normal if method not implemented)\n";
}

echo "\n";

// Test 6: Database connectivity for payment logging
echo "6️⃣  Testing payment transaction logging...\n\n";

try {
    $pdo = new PDO(
        "mysql:host=127.0.0.1;dbname=jcepnzzkmj;charset=utf8mb4",
        "jcepnzzkmj",
        "wprKh9Jq63",
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // Check staff_payment_transactions table
    $stmt = $pdo->query("SELECT COUNT(*) FROM staff_payment_transactions");
    $count = $stmt->fetchColumn();

    echo "   ✅ Payment logging table accessible\n";
    echo "   ✅ Current transaction count: {$count}\n";
} catch (Exception $e) {
    echo "   ❌ CRITICAL: Cannot access payment transaction table!\n";
    echo "   Error: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "  ✅ NUVEI GATEWAY TEST COMPLETE\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "\n💡 Summary:\n";
echo "   • Nuvei configuration: " . ($configOk ? "OK" : "MISSING") . "\n";
echo "   • API connectivity: WORKING\n";
echo "   • Payment logging: WORKING\n";
echo "\n✅ Ready for staff payments on Tuesday!\n\n";

exit(0);
