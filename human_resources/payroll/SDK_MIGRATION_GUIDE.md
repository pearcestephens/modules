# SDK Migration Guide

## Overview

This document outlines the migration from legacy custom API implementations to **official SDK-based services** with enterprise-grade features:

- ✅ **XeroServiceSDK** - Official Xero PHP SDK with OAuth 2.0
- ✅ **DeputyServiceSDK** - REST API wrapper with rate limiting
- ✅ **VendServiceSDK** - Lightspeed Retail API wrapper with pagination

All SDK services include:
- **Comprehensive logging** (every operation timed and logged)
- **Rate limiting** (API compliance)
- **Retry logic** (automatic failure recovery)
- **Error handling** (graceful degradation)
- **Test connection methods** (validation)

---

## 1. Environment Configuration

### Required Environment Variables

Add to `.env`:

```bash
# Xero SDK Configuration
XERO_CLIENT_ID=your_client_id_here
XERO_CLIENT_SECRET=your_client_secret_here
XERO_REDIRECT_URI=https://staff.vapeshed.co.nz/modules/human_resources/payroll/api/payroll/xero/oauth/callback
XERO_REGION=NZ  # or AU

# Deputy API Configuration
DEPUTY_API_ENDPOINT=https://api.deputy.com/api
DEPUTY_API_TOKEN=your_deputy_token_here

# Vend/Lightspeed Retail Configuration
VEND_DOMAIN_PREFIX=your_domain_prefix
VEND_ACCESS_TOKEN=your_vend_token_here
```

### Database Schema

Ensure `xero_tokens` table exists for OAuth token storage:

```sql
CREATE TABLE IF NOT EXISTS xero_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    access_token TEXT NOT NULL,
    refresh_token TEXT NOT NULL,
    expires_at INT NOT NULL,
    tenant_id VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

---

## 2. Service Comparison

### XeroService → XeroServiceSDK

| Feature | Legacy XeroService | New XeroServiceSDK |
|---------|-------------------|-------------------|
| OAuth | Custom implementation | Official SDK (XeroAPI\XeroPHP) |
| Token Refresh | Manual | Automatic with 5min buffer |
| API Calls | cURL-based | SDK methods (PayrollNzApi, PayrollAuApi) |
| Error Handling | Basic | ApiException catching, full logging |
| Logging | Minimal | Comprehensive (all operations timed) |
| Rate Limiting | None | SDK-handled |
| Type Safety | None | Full (SDK models) |

**Migration Benefits:**
- Automatic token refresh (no expired token errors)
- Type-safe API calls (IDE autocomplete)
- Better error messages (SDK exceptions)
- Maintained by Xero (automatic updates)

### DeputyService → DeputyServiceSDK

| Feature | Legacy DeputyService | New DeputyServiceSDK |
|---------|---------------------|---------------------|
| HTTP Client | cURL | GuzzleHttp\Client |
| Rate Limiting | None | 10 requests/second enforced |
| Retry Logic | None | 3 attempts with exponential backoff |
| Logging | Basic | Comprehensive (timing + parameters) |
| Bulk Operations | Manual loops | Optimized bulk methods |
| Timeout Handling | Fixed | Configurable (30s request, 10s connect) |

**Migration Benefits:**
- API compliance (rate limiting prevents bans)
- Automatic retry on transient failures
- Better performance monitoring (timing logs)
- Bulk update operations

### VendService → VendServiceSDK

| Feature | Legacy VendService | New VendServiceSDK |
|---------|-------------------|-------------------|
| Approach | Snapshot-based | Live API-based |
| HTTP Client | cURL | GuzzleHttp\Client |
| Pagination | Manual | Automatic with version tracking |
| Rate Limiting | None | 7 requests/minute enforced |
| Logging | Minimal | Comprehensive (all operations) |
| Filtering | Post-fetch | Query parameters |

**Migration Benefits:**
- Real-time data (no snapshot delays)
- Better filtering (server-side)
- API compliance (rate limiting)
- Comprehensive error handling

---

## 3. Migration Steps

### Step 1: Test SDK Services

Create test scripts to validate SDK connections:

**Test Xero SDK:**
```php
<?php
require_once __DIR__ . '/services/XeroServiceSDK.php';

use PayrollModule\Services\XeroServiceSDK;

try {
    $xero = new XeroServiceSDK();
    $result = $xero->testConnection();

    if ($result['success']) {
        echo "✓ Xero SDK connected successfully\n";
        echo "Tenant ID: " . $result['tenant_id'] . "\n";
    } else {
        echo "✗ Xero SDK connection failed: " . $result['error'] . "\n";
    }
} catch (\Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}
```

**Test Deputy SDK:**
```php
<?php
require_once __DIR__ . '/services/DeputyServiceSDK.php';

use PayrollModule\Services\DeputyServiceSDK;

try {
    $deputy = new DeputyServiceSDK($pdo);
    $result = $deputy->testConnection();

    if ($result['success']) {
        echo "✓ Deputy SDK connected successfully\n";
        echo "User: " . $result['user']['DisplayName'] . "\n";
    } else {
        echo "✗ Deputy SDK connection failed: " . $result['error'] . "\n";
    }
} catch (\Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}
```

**Test Vend SDK:**
```php
<?php
require_once __DIR__ . '/services/VendServiceSDK.php';

use PayrollModule\Services\VendServiceSDK;

try {
    $vend = new VendServiceSDK();
    $result = $vend->testConnection();

    if ($result['success']) {
        echo "✓ Vend SDK connected successfully\n";
        echo "Outlets: " . count($result['outlets']) . "\n";
    } else {
        echo "✗ Vend SDK connection failed: " . $result['error'] . "\n";
    }
} catch (\Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}
```

### Step 2: Update Service Container

Modify `PayrollAutomationService` constructor to use SDK services:

**Before:**
```php
public function __construct()
{
    $this->xeroService = new XeroService($this->pdo);
    $this->deputyService = new DeputyService($this->pdo);
    $this->vendService = new VendService();
}
```

**After:**
```php
public function __construct()
{
    // Use SDK services
    $this->xeroService = new XeroServiceSDK();
    $this->deputyService = new DeputyServiceSDK($this->pdo);
    $this->vendService = new VendServiceSDK();
}
```

### Step 3: Update Controllers

#### XeroController Changes

**Before:**
```php
$payRun = $this->xeroService->createPayRun($data);
```

**After:**
```php
$payRun = $this->xeroService->createPayRunNZ($data);  // SDK method
```

#### DeputyController Changes

**Before:**
```php
$timesheets = $this->deputyService->fetchTimesheets($start, $end);
```

**After:**
```php
$timesheets = $this->deputyService->getTimesheets($start, $end);  // SDK method
```

#### VendController Changes

**Before:**
```php
$payments = $this->vendService->getAccountPaymentsFromSnapshot($start, $end);
```

**After:**
```php
$result = $this->vendService->getAccountPayments($start, $end);  // Live API
$payments = $result['payments'];
```

### Step 4: Test Integration

Run comprehensive test suite:

```bash
cd /home/master/applications/jcepnzzkmj/public_html/modules/human_resources/payroll
php comprehensive-test.php
```

**Expected Output:**
```
✓ Total Tests:    50
✓ Passed:         50
✓ Failed:         0
✓ Pass Rate:      100%
```

### Step 5: Monitor Logs

Check PayrollLogger output after deployment:

```bash
tail -f /path/to/payroll/logs/payroll.log | grep -E "(xero|deputy|vend)"
```

**Look for:**
- ✅ Successful API connections
- ✅ Token refresh operations (Xero)
- ✅ Rate limiting compliance
- ✅ Retry attempts and successes
- ⚠️ Any API errors or failures

---

## 4. API Method Mapping

### XeroServiceSDK Methods

| Operation | Method | Parameters | Returns |
|-----------|--------|------------|---------|
| Test connection | `testConnection()` | None | `array` (success, tenant_id, error) |
| Create NZ pay run | `createPayRunNZ($data)` | `array $data` | `PayRunNz` object |
| Get employees | `getEmployees()` | None | `array` of employees |
| Refresh token | `refreshAccessToken()` | None | `bool` |
| Get auth URL | `getAuthorizationUrl()` | None | `string` URL |
| Exchange code | `exchangeCodeForToken($code)` | `string $code` | `array` tokens |

### DeputyServiceSDK Methods

| Operation | Method | Parameters | Returns |
|-----------|--------|------------|---------|
| Test connection | `testConnection()` | None | `array` (success, user, error) |
| Get employee | `getEmployee($id)` | `int $id` | `array\|null` employee |
| Get timesheets | `getTimesheets($start, $end, $empId)` | `string $start`, `string $end`, `int\|null $empId` | `array` timesheets |
| Create timesheet | `createTimesheet($data)` | `array $data` | `array\|null` created |
| Update timesheet | `updateTimesheet($id, $data)` | `int $id`, `array $data` | `array\|null` updated |
| Delete timesheet | `deleteTimesheet($id)` | `int $id` | `bool` |
| Get leave requests | `getLeaveRequests($start, $end, $empId)` | `string $start`, `string $end`, `int\|null $empId` | `array` leaves |
| Bulk update | `bulkUpdateTimesheets($updates)` | `array $updates` | `array` results |

### VendServiceSDK Methods

| Operation | Method | Parameters | Returns |
|-----------|--------|------------|---------|
| Test connection | `testConnection()` | None | `array` (success, outlets, error) |
| Get products | `getProducts($page, $pageSize)` | `int $page`, `int $pageSize` | `array` (products, pagination) |
| Get product | `getProduct($id)` | `string $id` | `array\|null` product |
| Get sales | `getSales($start, $end, $registerId)` | `string $start`, `string $end`, `string\|null $registerId` | `array` sales |
| Get registers | `getRegisters()` | None | `array` registers |
| Get register | `getRegister($identifier)` | `string $identifier` | `array\|null` register |
| Get payment types | `getPaymentTypes()` | None | `array` payment types |
| Get payment type | `getPaymentType($identifier)` | `string $identifier` | `array\|null` payment type |
| Get account payments | `getAccountPayments($start, $end)` | `string $start`, `string $end` | `array` (payments, metadata) |

---

## 5. Error Handling

### XeroServiceSDK Errors

```php
try {
    $payRun = $xeroService->createPayRunNZ($data);
} catch (\XeroAPI\XeroPHP\ApiException $e) {
    // Xero API error
    $logger->error('Xero API error', [
        'code' => $e->getCode(),
        'message' => $e->getMessage(),
        'response_body' => $e->getResponseBody()
    ]);
} catch (\RuntimeException $e) {
    // Configuration or token error
    $logger->error('Xero service error', [
        'error' => $e->getMessage()
    ]);
}
```

### DeputyServiceSDK Errors

```php
try {
    $timesheets = $deputyService->getTimesheets($start, $end);
} catch (\RuntimeException $e) {
    // API request failed after retries
    $logger->error('Deputy API failed', [
        'error' => $e->getMessage()
    ]);
}
```

### VendServiceSDK Errors

```php
try {
    $result = $vendService->getAccountPayments($start, $end);
    $payments = $result['payments'];
} catch (\RuntimeException $e) {
    // API request failed after retries
    $logger->error('Vend API failed', [
        'error' => $e->getMessage()
    ]);
}
```

---

## 6. Performance Considerations

### Rate Limiting

All SDK services implement rate limiting to comply with API limits:

| Service | Limit | Window | Implementation |
|---------|-------|--------|----------------|
| Xero | SDK-handled | - | Automatic via official SDK |
| Deputy | 10 requests | 1 second | Timestamp queue in `rateLimit()` |
| Vend | 7 requests | 1 minute | Timestamp queue in `rateLimit()` |

**Impact:** API calls may be slightly slower due to rate limiting, but prevents API bans.

### Retry Logic

All services retry failed requests automatically:

- **Attempts:** 3 maximum
- **Backoff:** Exponential (500ms base × attempt number)
- **Logged:** Every attempt logged with timing

**Impact:** Increased resilience to transient network issues.

### Token Refresh (Xero)

XeroServiceSDK automatically refreshes tokens before expiry:

- **Buffer:** 5 minutes before actual expiry
- **Automatic:** Transparent to caller
- **Logged:** All token operations logged

**Impact:** No more expired token errors in production.

---

## 7. Rollback Plan

If issues arise, rollback to legacy services:

### Quick Rollback

**PayrollAutomationService constructor:**
```php
public function __construct()
{
    // Rollback to legacy services
    $this->xeroService = new XeroService($this->pdo);
    $this->deputyService = new DeputyService($this->pdo);
    $this->vendService = new VendService();
}
```

### Gradual Rollback

Switch services individually:

```php
// Keep SDK for Xero, rollback Deputy and Vend
$this->xeroService = new XeroServiceSDK();
$this->deputyService = new DeputyService($this->pdo);  // Legacy
$this->vendService = new VendService();  // Legacy
```

---

## 8. Testing Checklist

Before production deployment:

- [ ] All environment variables configured
- [ ] `xero_tokens` table exists
- [ ] Test connections pass for all 3 services
- [ ] Comprehensive test suite passes (50/50)
- [ ] Token refresh tested (Xero)
- [ ] Rate limiting verified (concurrent requests)
- [ ] Logs show proper timing and operation tracking
- [ ] Error handling tested (invalid credentials, network failure)
- [ ] Bulk operations tested (Deputy)
- [ ] Pagination tested (Vend)

---

## 9. Logging Guide

### What Gets Logged

**Every SDK operation logs:**
- ✅ Start time (timer)
- ✅ Parameters passed
- ✅ API endpoint called
- ✅ Response status/size
- ✅ End time (duration)
- ✅ Success/failure status
- ✅ Errors with full trace

### Log Levels

- `INFO`: Normal operations, successful API calls
- `DEBUG`: API request details, rate limiting waits
- `WARNING`: Retry attempts, missing optional data
- `ERROR`: API failures, configuration errors, exceptions

### Example Log Output

```
[2024-01-30 14:23:45] INFO: XeroServiceSDK initialized endpoint=https://api.xero.com
[2024-01-30 14:23:46] DEBUG: Xero API request method=GET endpoint=/payroll.xro/2.0/employees attempt=1
[2024-01-30 14:23:47] DEBUG: Xero API response status_code=200 response_size=15234
[2024-01-30 14:23:47] INFO: Employees fetched successfully count=42 duration=1.24s
```

---

## 10. Support

### Documentation

- **Xero SDK:** https://github.com/XeroAPI/xero-php-oauth2
- **Vend API:** https://developers.lightspeedhq.com/retail/
- **Deputy API:** https://www.deputy.com/api-doc/

### Troubleshooting

**Issue: "Xero credentials not configured"**
- Check `.env` has `XERO_CLIENT_ID`, `XERO_CLIENT_SECRET`, `XERO_REDIRECT_URI`
- Verify environment variables loaded (`var_dump($_ENV)`)

**Issue: "Deputy rate limit exceeded"**
- Increase `RATE_LIMIT_WINDOW_MS` in DeputyServiceSDK
- Reduce concurrent API calls

**Issue: "Vend connection failed"**
- Verify `VEND_DOMAIN_PREFIX` is correct (no https://, no .vendhq.com)
- Check `VEND_ACCESS_TOKEN` is valid (regenerate in Vend settings)

**Issue: "Token refresh failed"**
- Check `xero_tokens` table has valid refresh_token
- Re-authorize via OAuth flow (`getAuthorizationUrl()`)

---

## Summary

✅ **3 SDK services created** (Xero, Deputy, Vend)
✅ **Comprehensive logging** (every operation tracked)
✅ **Rate limiting** (API compliance)
✅ **Retry logic** (automatic failure recovery)
✅ **Error handling** (graceful degradation)
✅ **Test methods** (connection validation)
✅ **Migration guide** (complete with examples)

**Next Steps:**
1. Configure environment variables
2. Test SDK connections
3. Update PayrollAutomationService
4. Run comprehensive test suite
5. Monitor logs in production
6. Enjoy enterprise-grade API integrations! 🎉
