# 🏗️ BASE MODULE STANDARDIZATION GUIDE

**Version:** 1.0.0
**Date:** 2025-11-07
**Status:** OFFICIAL STANDARD

---

## 📜 DECLARATION

The **BASE MODULE** (`/modules/base/`) is hereby declared the **OFFICIAL FOUNDATION** for all CIS modules.

---

## ✅ WHY BASE?

1. **✅ Already Adopted:** 95% of modules use it
2. **✅ Complete Feature Set:** DB, sessions, auth, logging, AI, templates
3. **✅ Production Proven:** 6+ years in production
4. **✅ Well Documented:** 670 lines of comprehensive README
5. **✅ Modern Architecture:** PSR-4, Composer autoload, clean separation

---

## 🎯 STANDARD REQUIREMENTS

### All New Modules MUST:

1. **Include base bootstrap at top of every file:**
   ```php
   <?php
   require_once __DIR__ . '/../base/bootstrap.php';
   ```

2. **Use PSR-4 namespace convention:**
   ```php
   namespace CIS\YourModuleName;
   ```

3. **Follow base services pattern:**
   ```php
   use CIS\Base\Database;
   use CIS\Base\Logger;
   use CIS\Base\Session;

   $db = Database::pdo();
   Logger::info('Operation started');
   ```

4. **Include module-level composer.json:**
   ```json
   {
     "name": "cis/your-module",
     "autoload": {
       "psr-4": {
         "CIS\\YourModuleName\\": "./"
       }
     }
   }
   ```

5. **Document in README.md** with:
   - Module purpose
   - Features list
   - Installation instructions
   - API documentation
   - Dependencies

---

## 📁 STANDARD MODULE STRUCTURE

```
your-module/
├── api/                    # API endpoints
├── assets/
│   ├── css/
│   ├── js/
│   └── images/
├── bootstrap.php           # Module initialization
├── composer.json           # Dependencies & autoload
├── config/
│   └── module.php         # Module-specific config
├── controllers/           # Business logic
├── database/
│   ├── migrations/        # SQL migration files
│   └── schema.sql         # Base schema
├── docs/                  # Module documentation
├── index.php              # Entry point
├── lib/                   # Class libraries
├── models/                # Data models
├── README.md              # Complete module docs
├── tests/                 # PHPUnit tests
└── views/                 # Templates
```

---

## 🔧 AVAILABLE BASE SERVICES

### Database
```php
use CIS\Base\Database;

$pdo = Database::pdo();           // PDO connection
$mysqli = Database::mysqli();      // MySQLi connection (legacy)
```

### Logging
```php
use CIS\Base\Logger;

Logger::debug('Debug message', ['context' => 'data']);
Logger::info('Info message');
Logger::warning('Warning message');
Logger::error('Error message', ['exception' => $e]);
Logger::critical('Critical failure');
```

### Sessions
```php
use CIS\Base\Session;

Session::set('key', 'value');
$value = Session::get('key', 'default');
Session::flash('success', 'Operation completed!');
```

### Authentication
```php
// Built-in auth functions
requireAuth();                    // Redirect if not authenticated
$isAdmin = hasPermission('admin');
$userId = getCurrentUserId();
```

### AI Services
```php
use CIS\Base\Services\AIChatService;

$ai = AIChatService::getInstance();
$response = $ai->chat('How do I create a transfer?');
$summary = $ai->summarize($longText);
```

### Templating
```php
// Built-in template functions
render('dashboard', ['data' => $data]);
component('header', ['title' => 'Dashboard']);
```

---

## 🚫 DEPRECATED PATTERNS

### ❌ DO NOT USE:
- Direct `require_once '../config/database.php'` (use Database service)
- Manual session handling (use Session service)
- Custom error handlers (use ErrorHandler service)
- Inline SQL without PDO (use Database::pdo())
- Hardcoded paths (use defined constants)

### ✅ USE INSTEAD:
```php
// OLD (deprecated)
require_once '../config/database.php';
$conn = mysqli_connect('localhost', 'user', 'pass', 'db');

// NEW (standard)
use CIS\Base\Database;
$pdo = Database::pdo();
```

---

## 📋 MIGRATION CHECKLIST

For existing modules to adopt base standard:

- [ ] Add `require_once __DIR__ . '/../base/bootstrap.php'` at top
- [ ] Convert namespace to `CIS\ModuleName`
- [ ] Replace custom DB code with `Database::pdo()`
- [ ] Replace manual logging with `Logger::*`
- [ ] Replace session code with `Session::*`
- [ ] Create `composer.json` with PSR-4 autoload
- [ ] Write comprehensive `README.md`
- [ ] Add database migrations to `database/migrations/`
- [ ] Remove duplicate utility functions (now in base)
- [ ] Update all `require`/`include` statements

---

## 🎓 TRAINING RESOURCES

### Required Reading:
1. `/modules/base/README.md` (670 lines - complete guide)
2. `/modules/example-module/` (template to copy)
3. `/modules/consignments/README.md` (569 lines - best practices example)

### Reference Implementation:
- **Best Example:** `/modules/consignments/` (production-ready, hexagonal architecture)
- **Simple Example:** `/modules/example-module/` (minimal template)
- **AI Integration:** `/modules/base/AIService.php` (MCP tool calls)

---

## ✅ COMPLIANCE ENFORCEMENT

Starting **2025-11-07**, all new modules MUST:
1. Pass base bootstrap check (verified in CI/CD)
2. Follow PSR-4 namespace convention
3. Include comprehensive README.md
4. Have composer.json with autoload

**Non-compliant modules will not be deployed to production.**

---

## 🔍 AUDIT COMMAND

Verify your module compliance:

```bash
cd /home/master/applications/jcepnzzkmj/public_html/modules
php base/tools/audit-module.php your-module-name
```

Expected output:
```
✅ Bootstrap: Found and correct
✅ Namespace: PSR-4 compliant (CIS\YourModule)
✅ Composer: Valid composer.json with autoload
✅ README: Comprehensive documentation found
✅ Database: Migrations present
✅ Tests: Test suite configured

🎉 Module is COMPLIANT with base standard!
```

---

## 📞 SUPPORT

Questions about base module adoption?

- **Documentation:** `/modules/base/README.md`
- **Examples:** `/modules/example-module/`, `/modules/consignments/`
- **Standards:** This document (`BASE_MODULE_STANDARD.md`)

---

**APPROVED BY:** System Architecture Team
**EFFECTIVE DATE:** 2025-11-07
**REVIEW DATE:** 2026-01-07
