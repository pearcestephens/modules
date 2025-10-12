# 🚀 Module Bootstrap Usage Guide

## What is `bootstrap.php`?

A **lightweight initialization file** that loads ONLY what your modules need, without pulling in the entire CIS application.

---

## ✅ What It Loads

1. **Constants** - HTTPS_URL, paths, etc.
2. **Session** - Secure session with CSRF tokens
3. **Database** - MySQLi connection
4. **Essential Functions** - User, permissions, navigation
5. **Authentication** - Auto-checks if user is logged in
6. **Error Handling** - Production-safe error logging
7. **Helper Functions** - Common utilities

---

## ❌ What It Does NOT Load

1. ❌ Main CIS routing system
2. ❌ Heavy legacy functions
3. ❌ Old template system
4. ❌ Unnecessary global state
5. ❌ Non-module dependencies

**Result:** Fast, clean, module-focused initialization!

---

## 📖 Usage Pattern

### ✅ CORRECT - Load Once at Entry Point

```php
<?php
/**
 * Module Entry Point - index.php
 * 
 * This is the ONLY place you load bootstrap.php
 */

// Load bootstrap ONCE
require_once __DIR__ . '/../bootstrap.php';

// Now route to your module pages
$page = $_GET['page'] ?? 'home';

switch ($page) {
    case 'pack':
        require __DIR__ . '/pages/pack.php';
        break;
    case 'receive':
        require __DIR__ . '/pages/receive.php';
        break;
    default:
        require __DIR__ . '/pages/home.php';
}
```

### ✅ CORRECT - Module Page (No Bootstrap)

```php
<?php
/**
 * Module Page - pages/pack.php
 * 
 * Bootstrap is ALREADY LOADED by index.php
 * Just check the flag and use what's available
 */

// Safety check - ensure bootstrap was loaded
if (!defined('CIS_MODULE_CONTEXT')) {
    die('This file must be accessed through the module entry point');
}

// Now you have access to:
// - $_SESSION (already started)
// - Database connection ($GLOBALS['db_connection'])
// - HTTPS_URL constant
// - get_user_id(), get_user_details(), has_permission(), etc.

$userId = get_user_id();
$userDetails = get_user_details();

// Build your page content
ob_start();
?>
<h1>Pack Transfer</h1>
<p>Welcome, <?= htmlspecialchars($userDetails['first_name'] ?? 'User') ?>!</p>

<!-- CSRF token available -->
<form method="POST">
    <?= csrf_token_input() ?>
    <!-- form fields -->
</form>
<?php
$content = ob_get_clean();

// Use the template (also doesn't need bootstrap)
require __DIR__ . '/../views/layouts/master.php';
```

### ✅ CORRECT - Template (No Bootstrap)

```php
<?php
/**
 * Master Template - views/layouts/master.php
 * 
 * Bootstrap is ALREADY LOADED
 * Just check the flag
 */

if (!defined('CIS_MODULE_CONTEXT')) {
    http_response_code(403);
    exit('Direct access forbidden');
}

// Now use what's available from bootstrap
$userId = get_user_id();
$userDetails = get_user_details();

// Include partials (they also don't need bootstrap)
require __DIR__ . '/../partials/head.php';
require __DIR__ . '/../partials/topbar.php';
// etc.
```

---

## ❌ WRONG - Loading Bootstrap Everywhere

```php
<?php
// ❌ WRONG - Don't do this in pages/pack.php
require_once __DIR__ . '/../../bootstrap.php'; // NO!

// ❌ WRONG - Don't do this in master.php
require_once __DIR__ . '/../../../bootstrap.php'; // NO!

// ❌ WRONG - Don't do this in partials/head.php
require_once __DIR__ . '/../../../../bootstrap.php'; // HELL NO!
```

**Why?** Because bootstrap is already loaded! Loading it multiple times causes:
- ❌ Duplicate session starts
- ❌ Duplicate database connections
- ❌ Redefined constants/functions
- ❌ Performance hit

---

## 🔒 Security Features

### 1. Automatic Authentication Check
```php
// By default, bootstrap requires authentication
// User will be redirected to login if not authenticated
```

### 2. Skip Authentication (Public Pages)
```php
<?php
// For public pages, skip auth check
define('SKIP_AUTH_CHECK', true);
require_once __DIR__ . '/../bootstrap.php';
```

### 3. CSRF Protection
```php
// In forms
<form method="POST">
    <?= csrf_token_input() ?>
    <!-- fields -->
</form>

// In POST handlers
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        die('Invalid CSRF token');
    }
    // Process form
}
```

### 4. Permission Checks
```php
// Check if user has permission
if (!has_permission('consignments.pack')) {
    http_response_code(403);
    die('Access denied');
}
```

---

## 🛠️ Helper Functions Available

After bootstrap loads, you have access to:

### Authentication
```php
is_authenticated()              // Returns bool
require_authentication()        // Redirects if not authenticated
get_user_id()                  // Returns int|null
get_user_details()             // Returns array|null
has_permission('permission')   // Returns bool
```

### Security
```php
verify_csrf_token($token)      // Returns bool
csrf_token_input()             // Returns HTML input string
safe_redirect($url)            // Redirects safely
```

### Utilities
```php
load_function_file('file.php') // Load additional function files
```

### Constants
```php
HTTPS_URL                      // https://staff.vapeshed.co.nz/
SITE_URL                       // Same as HTTPS_URL
APP_ROOT                       // /home/master/.../public_html
MODULES_ROOT                   // /home/master/.../public_html/modules
CIS_MODULE_CONTEXT             // True if bootstrap loaded
```

---

## 🏗️ Module Structure Example

```
modules/
├── bootstrap.php               # ← Load this ONCE at entry point
│
├── consignments/
│   ├── index.php               # ← Entry point - loads bootstrap
│   ├── module_bootstrap.php    # ← Optional module-specific init
│   │
│   ├── pages/
│   │   ├── pack.php            # ← No bootstrap needed
│   │   └── receive.php         # ← No bootstrap needed
│   │
│   ├── api/
│   │   ├── pack_submit.php     # ← Check CIS_MODULE_CONTEXT flag
│   │   └── receive_submit.php  # ← Check CIS_MODULE_CONTEXT flag
│   │
│   └── views/
│       └── layouts/
│           └── master.php      # ← No bootstrap needed
│
└── base/
    └── views/
        ├── layouts/
        │   └── master.php      # ← Shared template, no bootstrap
        └── partials/
            ├── head.php        # ← No bootstrap needed
            ├── topbar.php      # ← No bootstrap needed
            └── sidebar.php     # ← No bootstrap needed
```

---

## 🎯 Key Principles

### 1. **One Bootstrap Per Request**
Load `bootstrap.php` ONCE at your module's entry point (usually `index.php`)

### 2. **Check the Flag**
Other files should check `CIS_MODULE_CONTEXT` to ensure bootstrap was loaded:
```php
if (!defined('CIS_MODULE_CONTEXT')) {
    die('Bootstrap not loaded');
}
```

### 3. **Use What's Available**
After bootstrap:
- ✅ Session is started
- ✅ Database is connected
- ✅ Constants are defined
- ✅ Functions are loaded
- ✅ User is authenticated

### 4. **Module-Specific Init**
If your module needs extra setup, create `module_bootstrap.php`:
```php
<?php
// modules/consignments/module_bootstrap.php
// This runs automatically after main bootstrap

// Load module-specific libraries
require_once __DIR__ . '/lib/Db.php';
require_once __DIR__ . '/lib/Validation.php';

// Set module constants
define('CONSIGNMENTS_VERSION', '1.0.0');
```

---

## 🐛 Troubleshooting

### Problem: "Undefined constant HTTPS_URL"
**Solution:** Bootstrap not loaded. Check that your entry point includes it:
```php
require_once __DIR__ . '/../bootstrap.php';
```

### Problem: "Session already started"
**Solution:** You're loading bootstrap multiple times. Load it ONCE.

### Problem: "Cannot modify header information"
**Solution:** You're outputting content before bootstrap. Put bootstrap at the very top:
```php
<?php
require_once __DIR__ . '/../bootstrap.php'; // First thing!
```

### Problem: "User not authenticated" but they are logged in
**Solution:** Check that session cookies are being sent. Bootstrap uses `CIS_SESSION` as session name.

---

## 🚀 Migration from Old System

### Old Way (app.php everywhere):
```php
<?php
// OLD - pack.php
require_once $_SERVER['DOCUMENT_ROOT'] . '/app.php'; // ❌
```

### New Way (bootstrap once):
```php
<?php
// NEW - index.php (entry point)
require_once __DIR__ . '/../bootstrap.php'; // ✅ Once

// NEW - pack.php (routed to from index)
if (!defined('CIS_MODULE_CONTEXT')) {
    die('Use entry point');
}
// Now you have everything you need
```

---

## ✅ Checklist

When setting up a new module:

- [ ] Create `index.php` as entry point
- [ ] Load `bootstrap.php` ONCE in `index.php`
- [ ] Add routing logic in `index.php`
- [ ] Create page files that check `CIS_MODULE_CONTEXT`
- [ ] Use helper functions (get_user_id, has_permission, etc.)
- [ ] Add CSRF tokens to all forms
- [ ] Use `master.php` template (it doesn't need bootstrap)
- [ ] Test authentication/permissions
- [ ] Check error logs during development

---

## 📚 Related Files

- `bootstrap.php` - Main bootstrap file
- `module_bootstrap.php` - Module-specific init (optional)
- `base/views/layouts/master.php` - Shared template
- `base/views/partials/*.php` - Template partials

---

## 🎉 Benefits

✅ **Fast** - Only loads what you need  
✅ **Clean** - No legacy baggage  
✅ **Secure** - Built-in auth, CSRF, session hardening  
✅ **Consistent** - Same pattern for all modules  
✅ **Maintainable** - Easy to understand and debug  
✅ **Scalable** - Add middleware/features easily  

---

**Questions?** Check `bootstrap.php` source code - it's heavily documented!
