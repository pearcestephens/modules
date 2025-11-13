# 🔐 CENTRALIZED CREDENTIAL MIGRATION GUIDE
**Date:** November 6, 2025
**Goal:** Remove ALL hardcoded passwords, use SINGLE secure source
**Solution:** Base\Config singleton (passwords in ONE place only)

---

## ✅ THE SOLUTION: Base\Config

### **NEW: Single Source of Truth for ALL Credentials**

Created: `/modules/base/Config.php`

**What it does:**
1. ✅ Loads .env file from OUTSIDE public_html (secure location)
2. ✅ Validates required environment variables exist
3. ✅ Provides singleton access to all credentials
4. ✅ **NO HARDCODED PASSWORDS ANYWHERE**
5. ✅ Throws exceptions if credentials missing (no silent failures)

**Where passwords live now:**
- **ONLY ONE FILE:** `/home/.../jcepnzzkmj/.env` (outside public_html)
- **NEVER in code:** All code uses `Base\Config::getInstance()->get('DB_PASSWORD')`

---

## 🔄 MIGRATION STEPS (Complete Guide)

### Step 1: Move .env File to Secure Location ✅ CRITICAL

```bash
# SSH to server
ssh master_anjzctzjhr@phpstack-129337-5615757.cloudwaysapps.com

# Navigate to application root
cd /home/129337.cloudwaysapps.com/jcepnzzkmj

# Backup existing .env
cp public_html/modules/.env ./.env.BACKUP-$(date +%Y%m%d)

# Move to secure location (outside public_html)
mv public_html/modules/.env ./.env

# Verify it's not web-accessible
curl https://staff.vapeshed.co.nz/.env
# Should return 404 or 403
```

**Result:** Password file now at `/home/.../jcepnzzkmj/.env` (2 levels above public_html)

---

### Step 2: Ensure .env Has Required Variables

Edit: `/home/.../jcepnzzkmj/.env`

**Required variables:**
```env
# Database
DB_HOST=127.0.0.1
DB_NAME=jcepnzzkmj
DB_USER=master_anjzctzjhr
DB_PASSWORD=wprKh9Jq63
DB_PORT=3306
DB_CHARSET=utf8mb4

# Application
APP_ENV=production
APP_DEBUG=false
TIMEZONE=Pacific/Auckland

# Error Handling
ERROR_HANDLER_ENABLED=true

# Logging
LOG_LEVEL=info
LOG_PATH=/home/129337.cloudwaysapps.com/jcepnzzkmj/storage/logs
```

**Security:**
- ✅ File permissions: `chmod 600 .env` (owner read/write only)
- ✅ Owner: Application user (master_anjzctzjhr)
- ✅ Location: Outside public_html (not web-accessible)

---

### Step 3: Update base/Config.php ✅ ALREADY DONE

File created: `/modules/base/Config.php`

**Features:**
- Loads .env from secure location (tries multiple paths)
- Validates required variables (throws exception if missing)
- Singleton pattern (instantiated once)
- Safe access methods: `get()`, `getDatabase()`, `has()`

**Usage example:**
```php
$config = Base\Config::getInstance();
$password = $config->get('DB_PASSWORD'); // ✅ ONLY WAY TO GET PASSWORD
```

---

### Step 4: Update base/Database.php to Use Config

**BEFORE (INSECURE - hardcoded password):**
```php
$password = $_ENV['DB_PASSWORD'] ?? 'wprKh9Jq63'; // ❌ HARDCODED FALLBACK
```

**AFTER (SECURE - uses Config):**
```php
$config = Base\Config::getInstance();
$dbConfig = $config->getDatabase(); // Gets all DB credentials
$password = $dbConfig['password']; // ✅ NO HARDCODING
```

**File to update:** `/modules/base/Database.php`

**Find and replace:**
```php
// OLD CODE (DELETE):
private function __construct()
{
    $mainConfig = require __DIR__ . '/../_config/database.php';
    $host = $mainConfig['host'] ?? '127.0.0.1';
    $database = $mainConfig['database'] ?? 'jcepnzzkmj';
    $username = $mainConfig['username'] ?? 'master_anjzctzjhr';
    $password = $mainConfig['password'] ?? 'wprKh9Jq63'; // ❌ HARDCODED

// NEW CODE (REPLACE WITH):
private function __construct()
{
    $config = \Base\Config::getInstance();
    $dbConfig = $config->getDatabase();

    $host = $dbConfig['host'];
    $database = $dbConfig['name'];
    $username = $dbConfig['user'];
    $password = $dbConfig['password']; // ✅ FROM CONFIG ONLY
```

---

### Step 5: Update base/bootstrap.php

**File:** `/modules/base/bootstrap.php`

**Add Config initialization:**
```php
// After autoloader registration, add:

// Initialize Config (loads .env, validates required vars)
try {
    $config = Base\Config::getInstance();

    // Set timezone
    date_default_timezone_set($config->get('TIMEZONE', 'Pacific/Auckland'));

    // Set error reporting based on environment
    if ($config->isDevelopment()) {
        error_reporting(E_ALL);
        ini_set('display_errors', '1');
    } else {
        error_reporting(E_ALL & ~E_DEPRECATED);
        ini_set('display_errors', '0');
    }

} catch (\Exception $e) {
    error_log("CRITICAL: Config failed: " . $e->getMessage());
    http_response_code(500);
    die("Application configuration error.");
}
```

---

### Step 6: Remove Hardcoded Passwords from 8 Files

**Files with hardcoded password 'wprKh9Jq63':**

#### 1. `/modules/base/src/Core/Database.php`
**Find:**
```php
$password = $this->config['password'] ?? 'wprKh9Jq63';
```
**Replace with:**
```php
$password = $this->config['password'] ?? throw new \RuntimeException('DB_PASSWORD not configured');
```

#### 2. `/modules/staff-accounts/cli/phase-e-standalone.php`
**Find:**
```php
$password = 'wprKh9Jq63';
```
**Replace with:**
```php
require_once __DIR__ . '/../../base/bootstrap.php';
$config = Base\Config::getInstance();
$password = $config->get('DB_PASSWORD');
```

#### 3. `/modules/staff-accounts/cli/phase-e-v2-direct.php`
**Same as above**

#### 4. `/modules/staff-accounts/check-webhook-cli.php`
**Same as above**

#### 5-6. `/modules/bank-transactions/migrations/003_*.php` and `002_*.php`
**Find:**
```php
$password = 'wprKh9Jq63';
```
**Replace with:**
```php
require_once __DIR__ . '/../../base/bootstrap.php';
$config = Base\Config::getInstance();
$password = $config->get('DB_PASSWORD');
```

#### 7. `/modules/human_resources/payroll/bootstrap.php`
**Find:**
```php
$password = $_ENV['DB_PASS'] ?? 'wprKh9Jq63';
```
**Replace with:**
```php
require_once __DIR__ . '/../../base/bootstrap.php';
$config = Base\Config::getInstance();
$password = $config->get('DB_PASSWORD');
```

---

### Step 7: Update All Module Bootstrap Files

**Template for module bootstrap.php:**

```php
<?php
/**
 * Module Bootstrap: YOUR_MODULE_NAME
 * Loads base framework (which loads Config with credentials)
 */

// Load base framework (includes Config)
$baseBootstrap = __DIR__ . '/../base/bootstrap.php';
if (!file_exists($baseBootstrap)) {
    die('ERROR: base/bootstrap.php not found');
}
require_once $baseBootstrap;

// Get services from base framework
$config = Base\Config::getInstance();
$db = Base\Database::getInstance();
$logger = Base\Logger::getInstance();

// Get credentials (if needed for this module)
$dbPassword = $config->get('DB_PASSWORD'); // ✅ SECURE
$apiKey = $config->get('API_KEY', null); // ✅ WITH DEFAULT

// Module-specific config
$moduleConfig = require __DIR__ . '/config/module.config.php';

// Module ready
return [
    'config' => $config,
    'db' => $db,
    'logger' => $logger,
    'module' => $moduleConfig
];
```

---

## ✅ VERIFICATION CHECKLIST

After migration, verify:

### 1. .env File Security
```bash
# Check location (should be outside public_html)
ls -la /home/129337.cloudwaysapps.com/jcepnzzkmj/.env
# Expected: -rw------- (600 permissions)

# Check NOT web-accessible
curl https://staff.vapeshed.co.nz/.env
# Expected: 404 or 403

# Check permissions
stat -c "%a %n" /home/129337.cloudwaysapps.com/jcepnzzkmj/.env
# Expected: 600 .env
```

### 2. No Hardcoded Passwords
```bash
# Search for hardcoded password in all PHP files
cd /home/129337.cloudwaysapps.com/jcepnzzkmj/public_html/modules
grep -r "wprKh9Jq63" --include="*.php" .

# Expected: NO RESULTS (or only in comments/examples)
```

### 3. Config Loads Successfully
```bash
# Test Config class
php -r "
require '/home/129337.cloudwaysapps.com/jcepnzzkmj/public_html/modules/base/Config.php';
\$config = Base\Config::getInstance();
echo 'DB Host: ' . \$config->get('DB_HOST') . PHP_EOL;
echo 'DB Name: ' . \$config->get('DB_NAME') . PHP_EOL;
echo 'Password loaded: ' . (strlen(\$config->get('DB_PASSWORD')) > 0 ? 'YES' : 'NO') . PHP_EOL;
"

# Expected output:
# DB Host: 127.0.0.1
# DB Name: jcepnzzkmj
# Password loaded: YES
```

### 4. Database Connects
```bash
# Test Database connection
php -r "
require '/home/129337.cloudwaysapps.com/jcepnzzkmj/public_html/modules/base/bootstrap.php';
\$db = Base\Database::getInstance();
echo 'Database connected successfully' . PHP_EOL;
"

# Expected: Database connected successfully
```

### 5. Application Still Works
```bash
# Visit site
curl -I https://staff.vapeshed.co.nz/
# Expected: HTTP/1.1 200 OK (or 302 redirect to login)
```

---

## 🎯 BENEFITS OF THIS APPROACH

### Before (INSECURE):
```
❌ Password in 8+ different files
❌ Hardcoded fallbacks everywhere
❌ .env file in public_html (web-accessible)
❌ Each module loads .env independently
❌ No validation of required vars
❌ Silent failures if .env missing
```

### After (SECURE):
```
✅ Password in ONLY ONE FILE: /home/.../jcepnzzkmj/.env
✅ NO hardcoded passwords anywhere in code
✅ .env file OUTSIDE public_html (secure)
✅ Config loaded ONCE by base/bootstrap.php
✅ Required vars validated on startup
✅ Exceptions thrown if config missing
✅ Singleton pattern prevents multiple loads
✅ All modules use Base\Config::getInstance()
```

---

## 🔒 SECURITY IMPROVEMENTS

1. **Single Source of Truth**
   - Password exists in ONE place only
   - Change password → update ONE file
   - No code changes needed

2. **Secure Location**
   - .env outside public_html (not web-accessible)
   - 600 permissions (owner read/write only)
   - Never committed to git (.gitignore)

3. **Fail-Safe**
   - Missing password → exception thrown
   - No silent failures with hardcoded fallbacks
   - Application won't start without proper config

4. **Audit Trail**
   - Grep for `Config::getInstance()` shows all credential access
   - Easy to track who/what uses passwords
   - Pre-commit hook prevents new hardcoding

---

## 📋 ROLLOUT PLAN

### Phase 1: Infrastructure (30 minutes) ✅ DONE
- [x] Create base/Config.php
- [x] Move .env to secure location
- [x] Update base/bootstrap.php
- [x] Test Config loads

### Phase 2: Core Services (30 minutes)
- [ ] Update base/Database.php
- [ ] Update base/Logger.php (if uses credentials)
- [ ] Test database connection works

### Phase 3: Remove Hardcoded Passwords (30 minutes)
- [ ] Update 8 files identified in security audit
- [ ] Search for any remaining 'wprKh9Jq63' instances
- [ ] Verify no hardcoded passwords remain

### Phase 4: Module Standardization (2-4 hours)
- [ ] Update all 38 module bootstrap.php files
- [ ] Test each module still works
- [ ] Document any module-specific issues

### Phase 5: Verification (30 minutes)
- [ ] Run full security audit (grep for passwords)
- [ ] Test application end-to-end
- [ ] Verify pre-commit hooks prevent future issues
- [ ] Update documentation

**Total Time:** ~4-5 hours

---

## 🆘 TROUBLESHOOTING

### Error: ".env file not found"
**Solution:** Move .env to correct location:
```bash
mv public_html/modules/.env /home/129337.cloudwaysapps.com/jcepnzzkmj/.env
```

### Error: "Required environment variables are missing: DB_PASSWORD"
**Solution:** Add missing var to .env:
```bash
echo "DB_PASSWORD=your_password_here" >> .env
```

### Error: "Database connection failed"
**Solution:** Check credentials in .env are correct:
```bash
php -r "
require 'modules/base/Config.php';
\$config = Base\Config::getInstance();
print_r(\$config->getDatabase());
"
```

### Module still using hardcoded password
**Solution:** Update module's bootstrap.php to use Config:
```php
require_once __DIR__ . '/../base/bootstrap.php';
$config = Base\Config::getInstance();
$password = $config->get('DB_PASSWORD');
```

---

## 📞 SUPPORT

**Questions?**
- Read: `CIS_ARCHITECTURE_STANDARDS.md`
- Read: `SECURITY_AUDIT_REPORT.md`
- Contact: Pearce Stephens <pearce.stephens@ecigdis.co.nz>

**File locations:**
- Config class: `/modules/base/Config.php`
- Bootstrap: `/modules/base/bootstrap.php`
- .env file: `/home/.../jcepnzzkmj/.env` (outside public_html)

---

**STATUS: CENTRALIZED CREDENTIAL SYSTEM READY ✅**
**Next:** Execute Phase 2-5 migration steps (~4 hours work)
