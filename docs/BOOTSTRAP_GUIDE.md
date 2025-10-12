# 🚀 CIS Module Bootstrap Guide
## How to Properly Load app.php and Use Templates

**Last Updated:** October 12, 2025  
**Purpose:** Prevent bootstrap errors and ensure proper initialization order  

---

## ⚠️ Critical Rule: Load app.php ONCE

**app.php should be loaded ONCE per request, at the entry point only.**

### ✅ CORRECT Pattern

```php
// FILE: modules/consignments/index.php (ENTRY POINT)
<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/app.php';  // ← Load ONCE here
define('CIS_MODULE_CONTEXT', true);

// Route to appropriate page
$action = $_GET['action'] ?? 'list';

switch ($action) {
    case 'pack':
        require __DIR__ . '/pack.php';  // ← Does NOT load app.php
        break;
    case 'receive':
        require __DIR__ . '/receive.php';  // ← Does NOT load app.php
        break;
    default:
        require __DIR__ . '/pages/list.php';  // ← Does NOT load app.php
}
```

```php
// FILE: modules/consignments/pack.php (PAGE FILE)
<?php
// NO app.php here! It was already loaded by index.php

$pageTitle = 'Pack Transfer';
$breadcrumbs = [
    ['label' => 'Home', 'href' => '/index.php'],
    ['label' => 'Transfers', 'href' => '/modules/consignments/'],
    ['label' => 'Pack', 'active' => true]
];

ob_start();
?>
<h1>Pack Transfer Content</h1>
<p>Your page content here...</p>
<?php
$content = ob_get_clean();

// Use the template (which assumes app.php is already loaded)
require __DIR__ . '/../base/views/layouts/master.php';
```

---

### ❌ WRONG Patterns

#### Don't load app.php in every file:
```php
// FILE: modules/consignments/pack.php
<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/app.php';  // ❌ WRONG!
// This will cause session issues, duplicate constants, etc.
```

#### Don't load app.php in templates:
```php
// FILE: base/views/layouts/master.php
<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/app.php';  // ❌ WRONG!
// Templates should assume bootstrap is already done
```

---

## 📋 What app.php Should Contain

Your `app.php` bootstrap file should load (in this order):

```php
<?php
/**
 * CIS Application Bootstrap
 * 
 * Load this ONCE per request at the entry point.
 * Never load this in page files or templates.
 */

// 1. Constants (HTTPS_URL, DB credentials, etc.)
require_once __DIR__ . '/assets/functions/constants.php';

// 2. Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 3. Database connection
require_once __DIR__ . '/assets/functions/db.php';

// 4. Core functions
require_once __DIR__ . '/assets/functions/core.php';
require_once __DIR__ . '/assets/functions/users.php';
require_once __DIR__ . '/assets/functions/permissions.php';
require_once __DIR__ . '/assets/functions/navigation.php';

// 5. Error handler (optional but recommended)
require_once __DIR__ . '/core/ErrorHandler.php';

// 6. Autoloader (if using namespaces/classes)
require_once __DIR__ . '/vendor/autoload.php';  // Composer
// OR
spl_autoload_register(function($class) {
    // Your PSR-4 autoloader
});
```

---

## 🗺️ Request Flow Diagram

```
User Request: /modules/consignments/?action=pack
    ↓
┌─────────────────────────────────────────────┐
│ modules/consignments/index.php              │
│ ↓                                           │
│ require app.php (BOOTSTRAP - ONCE)          │
│   ├─ constants.php (HTTPS_URL, etc.)        │
│   ├─ session_start()                        │
│   ├─ db connection                          │
│   └─ core functions                         │
│ ↓                                           │
│ Route to pack.php                           │
└─────────────────────────────────────────────┘
    ↓
┌─────────────────────────────────────────────┐
│ modules/consignments/pack.php               │
│ (NO app.php - already loaded!)              │
│ ↓                                           │
│ Build page content                          │
│ ↓                                           │
│ require master.php                          │
└─────────────────────────────────────────────┘
    ↓
┌─────────────────────────────────────────────┐
│ base/views/layouts/master.php               │
│ (NO app.php - already loaded!)              │
│ ↓                                           │
│ Check: HTTPS_URL defined? ✓                 │
│ Check: Session started? ✓                   │
│ ↓                                           │
│ Include partials:                           │
│   ├─ head.php                               │
│   ├─ topbar.php                             │
│   ├─ sidebar.php                            │
│   ├─ footer.php                             │
│   └─ scripts.php                            │
└─────────────────────────────────────────────┘
    ↓
HTML Output to Browser
```

---

## 🔍 Debugging Bootstrap Issues

### Problem: "HTTPS_URL constant not defined"

**Cause:** app.php not loaded, or constants.php not included in app.php

**Fix:**
```php
// In your entry point (index.php):
require_once $_SERVER['DOCUMENT_ROOT'] . '/app.php';

// In app.php:
require_once __DIR__ . '/assets/functions/constants.php';
```

### Problem: "Session not started"

**Cause:** app.php doesn't call session_start()

**Fix:**
```php
// In app.php:
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
```

### Problem: "Call to undefined function getUserInformation()"

**Cause:** Core functions not loaded in app.php

**Fix:**
```php
// In app.php:
require_once __DIR__ . '/assets/functions/users.php';
```

### Problem: "Headers already sent" (session issues)

**Cause:** app.php loaded multiple times (once in index.php, again in page file)

**Fix:** Remove duplicate `require app.php` from page files. Only load at entry point.

---

## 📝 Template Prerequisites Checklist

Before using `master.php`, ensure these are loaded:

- [ ] **constants.php** → Defines `HTTPS_URL`
- [ ] **session_start()** → For `$_SESSION["userID"]`
- [ ] **Database connection** → For `getUserInformation()`, etc.
- [ ] **Core functions** → `getUserInformation()`, `getNavigationMenus()`, etc.
- [ ] **Permission functions** → `getCurrentUserPermissions()`
- [ ] **Notification functions** → `userNotifications_getAllUnreadNotifications()`

**All of these should be in app.php, loaded ONCE at entry point.**

---

## 🎯 Module Entry Point Template

Use this pattern for every module's `index.php`:

```php
<?php
/**
 * [Module Name] - Entry Point
 * 
 * This is the ONLY file in this module that loads app.php
 */

// 1. Bootstrap application (ONCE)
require_once $_SERVER['DOCUMENT_ROOT'] . '/app.php';

// 2. Define module context
define('CIS_MODULE_CONTEXT', true);

// 3. Load module-specific bootstrap (optional)
require_once __DIR__ . '/module_bootstrap.php';

// 4. Route to appropriate page
$action = $_GET['action'] ?? 'default';

switch ($action) {
    case 'pack':
        require __DIR__ . '/pages/pack.php';
        break;
    case 'receive':
        require __DIR__ . '/pages/receive.php';
        break;
    default:
        require __DIR__ . '/pages/list.php';
}
```

---

## ✅ Quick Verification

To verify your bootstrap is correct, add this at the top of `master.php`:

```php
// Debug: Check prerequisites (remove in production)
$checks = [
    'HTTPS_URL defined' => defined('HTTPS_URL'),
    'Session started' => session_status() === PHP_SESSION_ACTIVE,
    'DB functions available' => function_exists('getUserInformation'),
    'CIS_MODULE_CONTEXT defined' => defined('CIS_MODULE_CONTEXT'),
];

foreach ($checks as $check => $passed) {
    if (!$passed) {
        trigger_error("Bootstrap check failed: {$check}", E_USER_WARNING);
    }
}
```

---

## 🚨 Common Mistakes to Avoid

1. ❌ **Loading app.php in multiple files per request**
   - Causes: Duplicate sessions, constant redefinition, performance issues

2. ❌ **Loading app.php in template files**
   - Templates should be "dumb" - they just render, not bootstrap

3. ❌ **Hardcoding constants in templates**
   - Never do: `define('HTTPS_URL', '...')` in templates
   - Always define in constants.php loaded by app.php

4. ❌ **Skipping session_start() in app.php**
   - Templates/partials use `$_SESSION` - it must be started first

5. ❌ **Not checking prerequisites**
   - Add safety checks in templates to catch bootstrap issues early

---

## 📚 Related Documentation

- [Module Architecture](../docs/architecture/modules.md)
- [Template System](../docs/guides/templates.md)
- [Constants Reference](../docs/api/constants.md)

---

**Remember:** app.php = Entry point ONLY, ONCE per request. Everything else flows from there! 🚀
