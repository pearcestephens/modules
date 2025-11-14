# 🚀 INVENTORY SYNC - PRODUCTION DEPLOYMENT CHECKLIST

**Target:** Production Ready in 12-16 hours
**Current Score:** 8.5/10
**Target Score:** 9.5/10

---

## ✅ CRITICAL PATH (Must Do Before Production)

### [ ] 1. Implement Real Vend API (4 hours) 🔴
**File:** `classes/InventorySyncEngine.php`

**Tasks:**
- [ ] Replace `getVendInventory()` mock with real API
- [ ] Replace `updateVendInventory()` mock with real API
- [ ] Add retry logic (3 attempts with exponential backoff)
- [ ] Add timeout handling (30s)
- [ ] Test with production Vend account
- [ ] Handle rate limiting (429 responses)

**Test:**
```bash
# Verify real API connection
php scripts/test.php
# Should show actual inventory counts from Vend
```

**Code Location:**
```
Line 378: protected function getVendInventory()
Line 471: protected function updateVendInventory()
```

---

### [ ] 2. Add Authentication (2 hours) 🔴
**File:** `controllers/InventorySyncController.php`

**Tasks:**
- [ ] Add `requireAuth()` method
- [ ] Check `$_SESSION['user_id']` exists
- [ ] Protect sensitive endpoints (all except 'status')
- [ ] Return 401 for unauthorized
- [ ] Add IP whitelist option

**Code to Add:**
```php
protected function requireAuth() {
    if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
        return $this->error('Unauthorized', 401);
    }

    // Optional: Check user role
    if ($_SESSION['role'] !== 'admin') {
        return $this->error('Insufficient permissions', 403);
    }
}

public function handle() {
    // Start session if not started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $action = $_GET['action'] ?? 'status';
    $public = ['status']; // Only status is public

    if (!in_array($action, $public)) {
        $this->requireAuth();
    }
    // ... rest of code
}
```

**Test:**
```bash
# Should fail without auth
curl "http://localhost/api/inventory-sync?action=force_to_vend"
# Expected: {"error": "Unauthorized", "code": 401}
```

---

### [ ] 3. Add CSRF Protection (2 hours) 🔴
**File:** `controllers/InventorySyncController.php`

**Tasks:**
- [ ] Generate CSRF token on login
- [ ] Validate token on POST requests
- [ ] Reject invalid tokens
- [ ] Add token to response headers

**Code to Add:**
```php
protected function validateCSRFToken() {
    $token = $_SERVER['HTTP_X_CSRF_TOKEN']
          ?? $_POST['csrf_token']
          ?? null;

    if (!$token) {
        return false;
    }

    $session_token = $_SESSION['csrf_token'] ?? '';

    return hash_equals($session_token, $token);
}

public function handle() {
    // ... after requireAuth()

    // Protect POST/PUT/DELETE
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        if (!$this->validateCSRFToken()) {
            return $this->error('Invalid CSRF token', 403);
        }
    }
    // ... rest of code
}
```

**Test:**
```bash
# Should fail without CSRF token
curl -X POST "http://localhost/api/inventory-sync?action=force_to_vend" \
  -H "Content-Type: application/json" \
  -d '{"product_id": 123, "outlet_id": 1}'
# Expected: {"error": "Invalid CSRF token", "code": 403}
```

---

### [ ] 4. Add Unit Tests (8 hours) 🔴
**New File:** `tests/InventorySyncEngineTest.php`

**Tasks:**
- [ ] Install PHPUnit: `composer require --dev phpunit/phpunit`
- [ ] Create test suite
- [ ] Mock PDO and Vend API
- [ ] Test checkSync() - all scenarios
- [ ] Test recordTransfer() - success and failure
- [ ] Test auto-fix logic
- [ ] Test alert triggering
- [ ] Achieve 80%+ coverage

**Tests to Write:**
```php
<?php
use PHPUnit\Framework\TestCase;

class InventorySyncEngineTest extends TestCase {
    // 1. Test perfect match
    public function testCheckSyncPerfectMatch() {}

    // 2. Test minor drift (auto-fix)
    public function testCheckSyncMinorDrift() {}

    // 3. Test major drift (alert)
    public function testCheckSyncMajorDrift() {}

    // 4. Test critical drift
    public function testCheckSyncCriticalDrift() {}

    // 5. Test missing data
    public function testCheckSyncMissingData() {}

    // 6. Test transfer success
    public function testRecordTransferSuccess() {}

    // 7. Test transfer insufficient inventory
    public function testRecordTransferInsufficientInventory() {}

    // 8. Test force sync to Vend
    public function testForceSyncToVend() {}

    // 9. Test force sync from Vend
    public function testForceSyncFromVend() {}

    // 10. Test auto-fix conditions
    public function testCanAutoFix() {}
}
```

**Run Tests:**
```bash
vendor/bin/phpunit tests/
# Target: 80%+ coverage
```

---

### [ ] 5. Sanitize Logs (1 hour) 🔴
**File:** `classes/InventorySyncEngine.php`

**Tasks:**
- [ ] Add `sanitizeForLog()` method
- [ ] Remove API tokens from error messages
- [ ] Remove sensitive data (passwords, keys)
- [ ] Update all error_log() calls

**Code to Add:**
```php
protected function sanitizeForLog($message) {
    // Remove Bearer tokens
    $message = preg_replace(
        '/Bearer [A-Za-z0-9_-]+/',
        'Bearer [REDACTED]',
        $message
    );

    // Remove token query params
    $message = preg_replace(
        '/[?&]token=[A-Za-z0-9_-]+/',
        '?token=[REDACTED]',
        $message
    );

    // Remove passwords
    $message = preg_replace(
        '/(password[\'"]?\s*[:=]\s*[\'"]?)[^\'"]+/',
        '$1[REDACTED]',
        $message
    );

    return $message;
}

// Then update all error_log calls:
error_log("Error: " . $this->sanitizeForLog($e->getMessage()));
```

**Test:**
```bash
# Trigger an error and check logs
tail -f /var/log/php-errors.log
# Should NOT see actual API tokens
```

---

## 🟡 HIGH-VALUE ENHANCEMENTS (Recommended)

### [ ] 6. Add Input Validation (3 hours)
**File:** `classes/InventorySyncEngine.php`

**Tasks:**
- [ ] Validate product_id exists
- [ ] Validate outlet_id exists
- [ ] Validate quantity > 0 and < 10000
- [ ] Validate from_outlet != to_outlet
- [ ] Return clear error messages

---

### [ ] 7. Add Redis Caching (4 hours)
**File:** `classes/InventorySyncEngine.php`

**Tasks:**
- [ ] Install Redis: `composer require predis/predis`
- [ ] Cache inventory reads (60s TTL)
- [ ] Invalidate cache on updates
- [ ] Fallback to database if Redis unavailable
- [ ] 5x performance improvement

---

### [ ] 8. Add Rate Limiting (2 hours)
**File:** `controllers/InventorySyncController.php`

**Tasks:**
- [ ] Limit to 60 requests/minute per user
- [ ] Return 429 Too Many Requests
- [ ] Add X-RateLimit headers
- [ ] Use Redis for counter

---

### [ ] 9. Create Config File (2 hours)
**New File:** `config/inventory-sync.php`

**Tasks:**
- [ ] Move hardcoded values to config
- [ ] Environment variable validation
- [ ] Per-environment configs (dev/staging/prod)

---

### [ ] 10. Add Monitoring Dashboard (4 hours)
**New File:** `views/inventory-sync-dashboard.php`

**Tasks:**
- [ ] Real-time sync health widget
- [ ] Unresolved alerts table
- [ ] Sync accuracy chart (last 7 days)
- [ ] Quick action buttons (force sync)

---

## 🔍 VERIFICATION CHECKLIST

### After Completing Critical Path:

- [ ] All syntax errors fixed
- [ ] Real Vend API working
- [ ] Authentication prevents unauthorized access
- [ ] CSRF protection blocks invalid requests
- [ ] Unit tests pass (80%+ coverage)
- [ ] No API tokens in logs
- [ ] Database schema installed
- [ ] Cron job configured
- [ ] API endpoints return expected responses
- [ ] Error handling works correctly

---

## 📋 DEPLOYMENT STEPS

### Pre-Deployment:
```bash
# 1. Run syntax check
php -l classes/InventorySyncEngine.php
php -l controllers/InventorySyncController.php

# 2. Run unit tests
vendor/bin/phpunit tests/

# 3. Install database schema
mysql -u user -p vend < schema.sql

# 4. Test API endpoints
curl "http://staging.vapeshed.co.nz/api/inventory-sync?action=status"

# 5. Run test script
php scripts/test.php
```

### Deployment:
```bash
# 1. Push to staging
git push staging master

# 2. Test on staging for 24 hours

# 3. Push to production
git push production master

# 4. Configure cron job
crontab -e
# Add: 0,5,10,15,20,25,30,35,40,45,50,55 * * * * php /path/to/scripts/scheduled_sync.php

# 5. Monitor logs
tail -f /var/log/inventory_sync.log
```

---

## 🎯 SUCCESS CRITERIA

**Module is production-ready when:**
- [x] All 🔴 CRITICAL tasks complete
- [x] Unit tests pass (80%+ coverage)
- [x] Staging tests successful (24 hours)
- [x] No security vulnerabilities
- [x] Documentation updated
- [x] Team trained on usage

**Expected Results:**
- ✅ Sync accuracy >99.5%
- ✅ Perfect match rate >95%
- ✅ Auto-fix success >90%
- ✅ Response time <2 seconds
- ✅ Zero security incidents

---

## 📞 SUPPORT

**If Issues:**
1. Check logs: `tail -f /var/log/inventory_sync.log`
2. Review audit report: `AUDIT_REPORT.md`
3. Run test suite: `php scripts/test.php`
4. Check Vend API status: `curl -I https://api.vendhq.com`

**Contact:**
- Developer: [Your Name]
- Email: [Your Email]
- Slack: #inventory-sync

---

**Last Updated:** June 1, 2025
**Target Completion:** June 2, 2025
**Status:** 🟡 In Progress
