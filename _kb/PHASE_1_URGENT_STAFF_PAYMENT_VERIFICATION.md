# 🚨 PHASE 1: URGENT STAFF PAYMENT VERIFICATION
## Ensuring Staff Can Be Paid This Week

**Created:** December 19, 2024
**Priority:** CRITICAL
**Status:** IN PROGRESS

---

## ✅ VERIFICATION CHECKLIST

### 1. STAFF ACCOUNTS PAYMENT FLOW

#### 1.1 Payment Gateway (Nuvei) ✅
- [ ] Verify Nuvei API credentials configured
- [ ] Test connection to Nuvei gateway
- [ ] Check API rate limits
- [ ] Verify merchant account active

**Test Command:**
```php
<?php
require_once '/modules/staff-accounts/bootstrap.php';
require_once '/modules/staff-accounts/lib/NuveiPayment.php';

$nuvei = new NuveiPayment();
$testResult = $nuvei->testConnection();

echo "Nuvei Status: " . ($testResult['success'] ? "✅ ONLINE" : "❌ OFFLINE") . "\n";
echo "Details: " . json_encode($testResult, JSON_PRETTY_PRINT) . "\n";
```

#### 1.2 Vend Balance System ✅
- [ ] Verify vend_customers table accessible
- [ ] Test balance queries
- [ ] Check balance update mechanism
- [ ] Verify staff customer IDs mapped

**Test Query:**
```sql
-- Check staff with Vend customer accounts
SELECT
    u.id,
    u.first_name,
    u.last_name,
    u.vend_customer_account,
    vc.customer_code,
    vc.balance,
    vc.updated_at
FROM users u
LEFT JOIN vend_customers vc ON u.vend_customer_account = vc.id
WHERE u.staff_active = 1
AND u.vend_customer_account IS NOT NULL
LIMIT 10;
```

#### 1.3 Payment Processing API ✅
- [ ] Test `/modules/staff-accounts/api/process-payment.php`
- [ ] Verify CSRF token validation
- [ ] Test rate limiting
- [ ] Check transaction logging

**Test Request:**
```bash
curl -X POST https://staff.vapeshed.co.nz/modules/staff-accounts/api/process-payment.php \
  -H "Content-Type: application/json" \
  -d '{
    "user_id": 1,
    "amount": 50.00,
    "payment_method": "test_card",
    "test_mode": true
  }'
```

#### 1.4 Transaction Recording ✅
- [ ] Verify staff_payment_transactions table
- [ ] Test INSERT functionality
- [ ] Check audit logging
- [ ] Verify idempotency

**Test Query:**
```sql
-- Check recent transactions
SELECT
    id,
    user_id,
    amount,
    transaction_type,
    created_at
FROM staff_payment_transactions
ORDER BY created_at DESC
LIMIT 10;
```

---

### 2. HR PAYROLL SYSTEM

#### 2.1 Database Schema ✅
- [ ] Verify all 23 payroll tables exist
- [ ] Check table row counts
- [ ] Verify indexes present
- [ ] Test foreign key constraints

**Verification Query:**
```sql
-- Check payroll tables exist
SELECT
    TABLE_NAME,
    TABLE_ROWS,
    ROUND(DATA_LENGTH / 1024 / 1024, 2) as SIZE_MB
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = 'jcepnzzkmj'
AND TABLE_NAME LIKE 'payroll%'
ORDER BY TABLE_NAME;
```

#### 2.2 Xero Integration ✅
- [ ] Verify Xero API credentials
- [ ] Test Xero OAuth token
- [ ] Check tenant ID configured
- [ ] Test payroll push

**Test Command:**
```php
<?php
require_once '/modules/staff-accounts/lib/XeroApiService.php';

$xero = new XeroApiService();
$connectionTest = $xero->testConnection();

echo "Xero Status: " . ($connectionTest['success'] ? "✅ CONNECTED" : "❌ DISCONNECTED") . "\n";
echo "Tenant ID: " . ($connectionTest['tenant_id'] ?? 'NOT FOUND') . "\n";
```

#### 2.3 Deputy Integration ✅
- [ ] Verify Deputy API credentials
- [ ] Test timesheet retrieval
- [ ] Check data format compatibility
- [ ] Verify user ID mapping

**Test Command:**
```bash
# Test Deputy API connection
curl -X GET "https://api.deputy.com/api/v1/supervise/timesheet" \
  -H "Authorization: Bearer YOUR_DEPUTY_TOKEN" \
  -H "Content-Type: application/json"
```

#### 2.4 Pay Run Workflow ✅
- [ ] Test pay run creation
- [ ] Verify calculations (regular + overtime)
- [ ] Check deductions applied
- [ ] Test Xero push
- [ ] Verify bank file generation

**Test Steps:**
1. Navigate to: `https://staff.vapeshed.co.nz/modules/human_resources/payroll/`
2. Click "Create New Pay Run"
3. Set period: Last week
4. Click "Load Deputy Timesheets"
5. Verify hours loaded correctly
6. Click "Calculate Payroll"
7. Review calculations
8. Click "Push to Xero"
9. Verify success message
10. Generate bank file
11. Verify ABA file downloaded

---

### 3. INTEGRATION TESTING

#### 3.1 End-to-End Payment Flow ✅
- [ ] Staff makes purchase in Vend
- [ ] Balance updates in vend_customers
- [ ] Staff logs into CIS
- [ ] Views updated balance
- [ ] Makes payment via Nuvei
- [ ] Payment recorded
- [ ] Balance updated
- [ ] Xero notified

#### 3.2 End-to-End Payroll Flow ✅
- [ ] Pay period ends
- [ ] Deputy timesheets sync
- [ ] Pay run created
- [ ] Calculations performed
- [ ] Pushed to Xero
- [ ] Bank file generated
- [ ] Staff accounts updated
- [ ] Payments processed

---

## 🔍 DETAILED TEST PROCEDURES

### Test 1: Payment Gateway Connection

**File:** `/modules/staff-accounts/tests/test-nuvei-connection.php`

```php
<?php
/**
 * Test Nuvei Payment Gateway Connection
 *
 * Verifies we can communicate with Nuvei API
 */

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../lib/NuveiPayment.php';

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "  NUVEI PAYMENT GATEWAY CONNECTION TEST\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$nuvei = new NuveiPayment();

// Test 1: Credentials loaded
echo "1️⃣  Checking credentials...\n";
if ($nuvei->hasCredentials()) {
    echo "   ✅ Merchant ID: " . $nuvei->getMerchantId() . "\n";
    echo "   ✅ Site ID: " . $nuvei->getSiteId() . "\n";
    echo "   ✅ API endpoint configured\n\n";
} else {
    echo "   ❌ CRITICAL: Nuvei credentials missing!\n\n";
    exit(1);
}

// Test 2: API connectivity
echo "2️⃣  Testing API connection...\n";
$connectionTest = $nuvei->testConnection();

if ($connectionTest['success']) {
    echo "   ✅ API responding: " . $connectionTest['response_time'] . "ms\n";
    echo "   ✅ Session token: " . substr($connectionTest['session_token'], 0, 20) . "...\n\n";
} else {
    echo "   ❌ CRITICAL: Cannot connect to Nuvei API!\n";
    echo "   Error: " . $connectionTest['error'] . "\n\n";
    exit(1);
}

// Test 3: Test charge (if test mode enabled)
if (getenv('NUVEI_TEST_MODE') === 'true') {
    echo "3️⃣  Testing payment processing (TEST MODE)...\n";

    $testCharge = $nuvei->charge(10.00, [
        'card_number' => '4111111111111111',
        'expiry_month' => '12',
        'expiry_year' => '2025',
        'cvv' => '123',
        'cardholder_name' => 'Test User'
    ]);

    if ($testCharge['success']) {
        echo "   ✅ Test charge successful\n";
        echo "   ✅ Transaction ID: " . $testCharge['transaction_id'] . "\n\n";
    } else {
        echo "   ⚠️  Test charge failed (may be expected in prod)\n";
        echo "   Reason: " . $testCharge['error'] . "\n\n";
    }
}

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "  ✅ NUVEI GATEWAY TEST COMPLETE\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
```

---

### Test 2: Database Connectivity

**File:** `/modules/staff-accounts/tests/test-database.php`

```php
<?php
/**
 * Test Database Connectivity and Schema
 *
 * Verifies all required tables exist and are accessible
 */

require_once __DIR__ . '/../bootstrap.php';

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "  DATABASE CONNECTIVITY TEST\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$pdo = cis_resolve_pdo();

// Required tables
$requiredTables = [
    'users',
    'vend_customers',
    'staff_payment_transactions',
    'staff_account_reconciliation',
    'staff_saved_cards',
    'xero_payroll_deductions',
    'payroll_runs',
    'payroll_employee_details'
];

echo "1️⃣  Checking required tables exist...\n\n";

$allPresent = true;
foreach ($requiredTables as $table) {
    $stmt = $pdo->query("SHOW TABLES LIKE '{$table}'");
    $exists = $stmt->rowCount() > 0;

    if ($exists) {
        $countStmt = $pdo->query("SELECT COUNT(*) as cnt FROM {$table}");
        $count = $countStmt->fetch(PDO::FETCH_ASSOC)['cnt'];
        echo "   ✅ {$table} ({$count} rows)\n";
    } else {
        echo "   ❌ {$table} MISSING!\n";
        $allPresent = false;
    }
}

echo "\n";

if (!$allPresent) {
    echo "❌ CRITICAL: Some required tables missing!\n\n";
    exit(1);
}

// Test key queries
echo "2️⃣  Testing key queries...\n\n";

// Test 1: Staff with Vend accounts
try {
    $stmt = $pdo->query("
        SELECT COUNT(*) as cnt
        FROM users u
        WHERE u.staff_active = 1
        AND u.vend_customer_account IS NOT NULL
    ");
    $staffCount = $stmt->fetch(PDO::FETCH_ASSOC)['cnt'];
    echo "   ✅ Staff with Vend accounts: {$staffCount}\n";
} catch (Exception $e) {
    echo "   ❌ Staff query failed: " . $e->getMessage() . "\n";
}

// Test 2: Recent transactions
try {
    $stmt = $pdo->query("
        SELECT COUNT(*) as cnt
        FROM staff_payment_transactions
        WHERE created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");
    $txnCount = $stmt->fetch(PDO::FETCH_ASSOC)['cnt'];
    echo "   ✅ Recent transactions (30 days): {$txnCount}\n";
} catch (Exception $e) {
    echo "   ❌ Transaction query failed: " . $e->getMessage() . "\n";
}

// Test 3: Current balances
try {
    $stmt = $pdo->query("
        SELECT COUNT(*) as cnt, SUM(vend_balance) as total
        FROM staff_account_reconciliation
        WHERE vend_balance < 0
    ");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "   ✅ Accounts with balance: {$result['cnt']} (Total: $" . number_format($result['total'], 2) . ")\n";
} catch (Exception $e) {
    echo "   ❌ Balance query failed: " . $e->getMessage() . "\n";
}

echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "  ✅ DATABASE TEST COMPLETE\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
```

---

### Test 3: API Endpoint Verification

**File:** `/modules/staff-accounts/tests/test-api-endpoints.php`

```php
<?php
/**
 * Test All Staff Accounts API Endpoints
 *
 * Verifies each API responds correctly
 */

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "  API ENDPOINT VERIFICATION TEST\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$baseUrl = 'https://staff.vapeshed.co.nz/modules/staff-accounts/api';

$endpoints = [
    'auto-match-suggestions.php',
    'customer-search.php',
    'employee-mapping.php',
    'manager-dashboard.php',
    'payment.php',
    'process-payment.php',
    'staff-reconciliation.php'
];

echo "Testing " . count($endpoints) . " API endpoints...\n\n";

foreach ($endpoints as $endpoint) {
    $url = "{$baseUrl}/{$endpoint}";

    // Use curl to test endpoint
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // For testing

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        echo "   ❌ {$endpoint}: Connection failed - {$error}\n";
    } elseif ($httpCode === 200) {
        echo "   ✅ {$endpoint}: Responding (HTTP {$httpCode})\n";
    } elseif ($httpCode === 401 || $httpCode === 403) {
        echo "   ⚠️  {$endpoint}: Auth required (HTTP {$httpCode}) - Expected\n";
    } else {
        echo "   ❌ {$endpoint}: Unexpected response (HTTP {$httpCode})\n";
    }
}

echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "  ✅ API ENDPOINT TEST COMPLETE\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
```

---

## 🚀 EXECUTION COMMANDS

### Run All Tests

```bash
cd /home/master/applications/jcepnzzkmj/public_html/modules/staff-accounts/

# Create tests directory if not exists
mkdir -p tests

# Run Nuvei test
php tests/test-nuvei-connection.php

# Run database test
php tests/test-database.php

# Run API test
php tests/test-api-endpoints.php
```

---

## 📊 TEST RESULTS SUMMARY

### Nuvei Gateway: ⏳ PENDING
- [ ] Credentials configured
- [ ] API responding
- [ ] Test charge successful

### Database: ⏳ PENDING
- [ ] All tables present
- [ ] Key queries working
- [ ] Balances retrievable

### API Endpoints: ⏳ PENDING
- [ ] All endpoints responding
- [ ] Auth working correctly
- [ ] No 500 errors

### Xero Integration: ⏳ PENDING
- [ ] Connection successful
- [ ] Tenant ID valid
- [ ] Payroll push working

### Deputy Integration: ⏳ PENDING
- [ ] Connection successful
- [ ] Timesheet retrieval working
- [ ] Data format correct

### Pay Run Workflow: ⏳ PENDING
- [ ] Can create pay run
- [ ] Calculations correct
- [ ] Xero push successful
- [ ] Bank file generated

---

## ✅ SIGN-OFF

**Phase Status:** ⏳ IN PROGRESS
**Started:** December 19, 2024
**Blockers:** None yet
**Next Steps:** Execute test suite

**Critical Question:** Can staff be paid this week?
**Answer:** ⏳ Testing in progress...

---

## 📞 IMMEDIATE ACTIONS REQUIRED

1. **Execute test suite** - Run all verification tests
2. **Document findings** - Record any issues found
3. **Fix critical issues** - Address any payment blockers immediately
4. **Verify workflows** - Test end-to-end payment scenarios
5. **Confirm readiness** - Get user confirmation before proceeding

**Ready to execute tests now! Should I proceed? 🚀**
