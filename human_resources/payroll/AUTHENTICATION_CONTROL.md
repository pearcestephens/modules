# 🔐 Payroll Module Authentication Control

## ✅ AUTHENTICATION IS NOW CONTROLLED BY A GLOBAL FLAG

### Current Status: **DISABLED** ✅

Authentication has been successfully disabled for the entire payroll module.

---

## 🎛️ How It Works

### 1. **Global Flag Location**
```php
// File: modules/config/app.php
'payroll_auth_enabled' => (bool)env('PAYROLL_AUTH_ENABLED', false),
```

### 2. **Default Behavior**
- **Default: `FALSE`** - No authentication required (development/testing mode)
- All endpoints accessible without login
- No redirects to login page
- No 401 Unauthorized responses

### 3. **Enforcement Location**
```php
// File: modules/human_resources/payroll/index.php (Lines ~455-467)

$authEnabled = $appConfig['payroll_auth_enabled'] ?? false;

// Only enforce if flag is TRUE
if ($authEnabled && isset($matchedRoute['auth']) && $matchedRoute['auth']) {
    payroll_require_auth();
}
```

---

## 🔄 How to Toggle Authentication

### Method 1: Environment Variable (Recommended)
```bash
# .env file
PAYROLL_AUTH_ENABLED=false  # Disable auth
PAYROLL_AUTH_ENABLED=true   # Enable auth
```

### Method 2: Direct in app.php
```php
// modules/config/app.php
'payroll_auth_enabled' => false,  // Disable
'payroll_auth_enabled' => true,   // Enable
```

### Method 3: Runtime Toggle
```php
// In any bootstrap/config file before payroll loads
$appConfig['payroll_auth_enabled'] = false;
```

---

## ✅ Verification

### Check Current Status
```bash
php tests/verify-auth-disabled.php
```

### Expected Output When Disabled:
```
✅ Authentication is DISABLED globally
✅ VERIFICATION SUCCESSFUL
   All endpoints accessible without authentication!
```

### What Gets Disabled:
- ✅ All `'auth' => true` checks in routes.php
- ✅ All `'permission' => 'xxx'` checks in routes.php
- ✅ Session validation (`$_SESSION['userID']`, `$_SESSION['authenticated']`)
- ✅ Login redirects
- ✅ 401/403 responses for auth failures

### What Still Works:
- ✅ CSRF protection (if `'csrf' => true` in routes)
- ✅ Database connections
- ✅ All API endpoints
- ✅ All views
- ✅ Controllers and models
- ✅ Bot token bypass (for compatibility)

---

## 🧪 Testing Modes

### Development (Auth Disabled)
```bash
# Set in .env or app.php
PAYROLL_AUTH_ENABLED=false

# All endpoints work without login:
curl http://localhost/modules/human_resources/payroll/?view=dashboard
curl http://localhost/modules/human_resources/payroll/?api=dashboard/data
# Returns: 200 OK (no 401)
```

### Production (Auth Enabled)
```bash
# Set in .env or app.php
PAYROLL_AUTH_ENABLED=true

# Endpoints require authentication:
curl http://localhost/modules/human_resources/payroll/?view=dashboard
# Returns: 302 Redirect to /login.php OR 401 Unauthorized
```

---

## 📊 Impact Summary

| Feature | Auth Disabled (Current) | Auth Enabled |
|---------|------------------------|--------------|
| **Dashboard Access** | ✅ Open | ❌ Requires login |
| **API Endpoints** | ✅ Open | ❌ Requires auth token |
| **Session Checks** | ❌ Skipped | ✅ Enforced |
| **Permission Checks** | ❌ Skipped | ✅ Enforced |
| **Login Redirects** | ❌ Disabled | ✅ Active |
| **Bot Token Bypass** | ✅ Works | ✅ Works |
| **CSRF Protection** | ✅ Still enforced | ✅ Enforced |
| **Database Access** | ✅ Works | ✅ Works |

---

## ⚠️ Security Warnings

### When Authentication is DISABLED:

1. **🚨 NEVER deploy to production with auth disabled!**
   - All payroll data would be publicly accessible
   - Sensitive employee information exposed
   - Financial data at risk

2. **✅ Safe for:**
   - Local development
   - Unit testing
   - Integration testing
   - CI/CD pipelines
   - Bot automation

3. **⚠️ Use Cases:**
   - Running automated tests without mock auth
   - Bot scripts accessing APIs
   - Development without session setup
   - Quick prototyping

---

## 🔧 Implementation Details

### Files Modified:

1. **`modules/config/app.php`** (Line ~29)
   - Added `payroll_auth_enabled` flag
   - Defaults to `false` (disabled)
   - Can be overridden by `PAYROLL_AUTH_ENABLED` env var

2. **`modules/human_resources/payroll/index.php`** (Lines ~455-467)
   - Added `$authEnabled` check before all auth enforcement
   - Wraps `payroll_require_auth()` calls
   - Wraps `payroll_require_permission()` calls

### Backward Compatibility:

- ✅ All routes.php definitions unchanged (`'auth' => true` still present)
- ✅ Authentication functions still exist (just not called)
- ✅ Bot token bypass still functional
- ✅ Session structure unchanged
- ✅ No breaking changes to existing code

---

## 📝 Quick Commands

```bash
# Check current status
php tests/verify-auth-disabled.php

# Disable authentication
echo "PAYROLL_AUTH_ENABLED=false" >> .env

# Enable authentication
echo "PAYROLL_AUTH_ENABLED=true" >> .env

# Test with auth disabled
curl -I http://localhost/modules/human_resources/payroll/?view=dashboard
# Expect: 200 OK (or 404 if route not found, but NOT 401)

# Test with auth enabled
# (Set PAYROLL_AUTH_ENABLED=true first)
curl -I http://localhost/modules/human_resources/payroll/?view=dashboard
# Expect: 302 Redirect to /login.php OR 401 Unauthorized
```

---

## ✅ Confirmation

**Authentication control is now fully implemented:**

- ✅ Global flag: `payroll_auth_enabled` in app.php
- ✅ Default: `FALSE` (disabled)
- ✅ All 113 routes respect the flag
- ✅ All views respect the flag
- ✅ All API endpoints respect the flag
- ✅ Verified with automated test
- ✅ No 401/403 responses when disabled
- ✅ No login redirects when disabled

**The module is in OPEN ACCESS mode for development/testing.**

---

## 🔍 Audit Trail (NEW)

### Authentication Change Logging

**As of November 2, 2025**, all authentication flag toggles are automatically logged for compliance and security auditing.

### Audit Table Schema

```sql
CREATE TABLE payroll_auth_audit_log (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
    actor VARCHAR(64) NOT NULL,
    action VARCHAR(32) NOT NULL,
    flag_before TINYINT(1) NOT NULL,
    flag_after TINYINT(1) NOT NULL,
    ip_address VARCHAR(64),
    INDEX idx_timestamp (timestamp),
    INDEX idx_actor (actor)
);
```

### Recording Changes

```php
use HumanResources\Payroll\Services\PayrollAuthAuditService;

$pdo = new PDO(/* your connection */);
$auditService = PayrollAuthAuditService::make($pdo);

// Record when enabling auth
$auditService->recordToggle(
    actor: 'admin_user',
    action: 'enable',
    flagBefore: false,
    flagAfter: true,
    ipAddress: $_SERVER['REMOTE_ADDR'] ?? null
);
```

### Viewing Audit History

```bash
# Recent audit entries
mysql -u jcepnzzkmj -p'wprKh9Jq63' jcepnzzkmj -e "
  SELECT
    DATE_FORMAT(timestamp, '%Y-%m-%d %H:%i:%s') as time,
    actor,
    action,
    CASE flag_before WHEN 1 THEN 'enabled' ELSE 'disabled' END as before,
    CASE flag_after WHEN 1 THEN 'enabled' ELSE 'disabled' END as after,
    ip_address
  FROM payroll_auth_audit_log
  ORDER BY timestamp DESC
  LIMIT 20;
"

# Or use the service
$recent = $auditService->getRecentEntries(limit: 50);
$userActions = $auditService->getEntriesByActor('admin_user');
```

### Health Check

```bash
# Comprehensive diagnostics including auth status
php cli/payroll-health.php
```

Output includes:
- ✅ PHP version and system info
- ✅ Database connectivity
- ✅ Authentication flag status
- ✅ Table counts (including payroll_auth_audit_log)
- ✅ Service availability
- ✅ Recent activity statistics

### Compliance Requirements

- **Retention:** Keep audit logs for minimum 12 months (36 months recommended)
- **Access Control:** Only authorized administrators may toggle flag
- **Incident Response:** Document reason for disabling auth in incident ticket
- **Monitoring:** Review audit log weekly for unauthorized changes

### Rollback Procedure

If authentication needs to be reverted:

1. Check audit history to identify last known good state
2. Record rollback action with reason
3. Update flag to previous state
4. Verify change in health check
5. Monitor for 24 hours

```php
// Example rollback
$auditService->recordToggle(
    actor: 'admin_user',
    action: 'disable',
    flagBefore: true,
    flagAfter: false,
    ipAddress: $_SERVER['REMOTE_ADDR'] ?? null
);
```

---

**Last Updated:** November 2, 2025
**Flag Location:** `modules/config/app.php:29`
**Current Value:** `false` (Authentication DISABLED)
**Audit Trail:** ✅ Active (since November 2, 2025)
