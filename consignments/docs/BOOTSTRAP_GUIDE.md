# Consignments Module Bootstrap Guide

## Quick Start

### Basic Usage

At the top of **any** Consignments module file, just add:

```php
<?php
require_once __DIR__ . '/../bootstrap.php';
// or
require_once __DIR__ . '/../../bootstrap.php'; // if deeper in folders
```

That's it! You now have access to:
- ✅ Database connection
- ✅ Session handling
- ✅ ApiResponse class
- ✅ All shared functions
- ✅ Your subfolder's functions (auto-loaded)

---

## What Gets Loaded Automatically

### 1. Base Application
```
✅ /app.php (database, sessions, core)
✅ /bootstrap/app.php (if exists)
✅ /modules/base/lib/Session.php
```

### 2. Shared API Components
```
✅ /modules/shared/api/ApiResponse.php
```

### 3. Consignments Shared Files
```
✅ /modules/consignments/shared/lib/*.php (all files)
✅ /modules/consignments/shared/functions/*.php (all files)
```

### 4. Your Subfolder's Functions
```
✅ Auto-detects your current folder
✅ Loads ../functions/*.php automatically
```

**Example:** If you're in:
- `/modules/consignments/stock-transfers/pack.php`

It automatically loads:
- `/modules/consignments/stock-transfers/functions/*.php`

---

## Examples

### Example 1: API Endpoint

**Before:**
```php
<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/app.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/modules/shared/api/ApiResponse.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/modules/base/lib/Session.php';

// Now write your code...
```

**After:**
```php
<?php
require_once __DIR__ . '/../bootstrap.php';

// Everything is already loaded! Write your code...
```

---

### Example 2: Stock Transfer Page

**Before:**
```php
<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/app.php';
require_once __DIR__ . '/functions/pack.php';
require_once __DIR__ . '/functions/validation.php';

// Now write your code...
```

**After:**
```php
<?php
require_once __DIR__ . '/../bootstrap.php';

// Your functions/*.php files are auto-loaded!
```

---

### Example 3: Purchase Order Controller

```php
<?php
require_once __DIR__ . '/../../bootstrap.php';

// Now you have:
// - Database via $db or Db class
// - ApiResponse class
// - Session handling
// - All shared functions
// - All purchase-orders/functions/*.php files

class PurchaseOrderController {
    public function receive() {
        // Use ApiResponse without manual include
        echo ApiResponse::success([
            'order_id' => 123
        ]);
    }
}
```

---

## Helper Functions

The bootstrap provides helper functions for loading specific files:

### Load a specific subfolder's files

```php
// Load all files from a subfolder
consignments_load_subfolder('stock-transfers/lib');
// This loads: /modules/consignments/stock-transfers/lib/*.php
```

### Load a specific file

```php
// Load a specific file
if (consignments_load_file('purchase-orders/functions/receiving.php')) {
    echo "File loaded!";
}
```

---

## Constants Defined

The bootstrap defines these constants for you:

```php
CONSIGNMENTS_MODULE_PATH   // /path/to/modules/consignments
CONSIGNMENTS_API_PATH      // /path/to/modules/consignments/api
CONSIGNMENTS_SHARED_PATH   // /path/to/modules/consignments/shared
```

Use them for cleaner paths:

```php
// Instead of:
require_once $_SERVER['DOCUMENT_ROOT'] . '/modules/consignments/shared/lib/Helper.php';

// Just use:
require_once CONSIGNMENTS_SHARED_PATH . '/lib/Helper.php';
```

---

## File Structure

```
modules/consignments/
├── bootstrap.php              ← Include this file
│
├── shared/                    ← Auto-loaded by bootstrap
│   ├── lib/*.php             ← All loaded
│   ├── functions/*.php       ← All loaded
│   └── js/ajax-manager.js
│
├── api/
│   ├── api.php               ← Uses bootstrap
│   ├── autosave_transfer.php ← Included by api.php
│   └── log_error.php         ← Included by api.php
│
├── stock-transfers/
│   ├── pack.php              ← Uses bootstrap
│   ├── functions/            ← Auto-loaded when pack.php runs
│   │   ├── pack.php
│   │   └── validation.php
│   └── js/pack.js
│
└── purchase-orders/
    ├── receive.php           ← Uses bootstrap
    └── functions/            ← Auto-loaded when receive.php runs
        └── receiving.php
```

---

## Migration Guide

### Converting Existing Files

**Step 1:** Replace all includes with bootstrap

```php
// Replace these lines:
require_once $_SERVER['DOCUMENT_ROOT'] . '/app.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/modules/shared/api/ApiResponse.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/modules/base/lib/Session.php';
require_once __DIR__ . '/functions/pack.php';

// With just this:
require_once __DIR__ . '/../bootstrap.php';
```

**Step 2:** Test the file

```php
// Add at the top to verify bootstrap loaded:
if (!defined('CONSIGNMENTS_BOOTSTRAP_LOADED')) {
    die('Bootstrap not loaded!');
}
```

**Step 3:** Remove manual function includes

If your functions are in the standard `functions/` folder, they're auto-loaded!

---

## Best Practices

### ✅ DO

1. **Use bootstrap in every Consignments file**
   ```php
   require_once __DIR__ . '/../bootstrap.php';
   ```

2. **Put shared code in shared/ folders**
   ```
   /modules/consignments/shared/lib/Helper.php  ← Auto-loaded
   ```

3. **Put subfolder code in functions/ folders**
   ```
   /modules/consignments/stock-transfers/functions/pack.php  ← Auto-loaded
   ```

4. **Use constants for paths**
   ```php
   CONSIGNMENTS_MODULE_PATH . '/templates/email.php'
   ```

### ❌ DON'T

1. **Don't mix manual includes with bootstrap**
   ```php
   // Bad - redundant
   require_once __DIR__ . '/../bootstrap.php';
   require_once $_SERVER['DOCUMENT_ROOT'] . '/app.php'; // Already loaded!
   ```

2. **Don't include bootstrap multiple times**
   - It's safe (uses require_once), but wasteful

3. **Don't put unrelated code in shared/**
   - Only put truly shared Consignments code there

---

## Troubleshooting

### "Class ApiResponse not found"

**Solution:** Bootstrap not included. Add:
```php
require_once __DIR__ . '/../bootstrap.php';
```

### "Function xyz() not defined"

**Solution:** Check if function file is in correct location:
- Shared functions: `/modules/consignments/shared/functions/`
- Subfolder functions: `/modules/consignments/[subfolder]/functions/`

### "Bootstrap loads too many files"

**Solution:** The bootstrap only loads:
1. Essential files (app.php, ApiResponse)
2. Shared files (consignments/shared/)
3. Your current subfolder's functions

It does NOT load other subfolders' files.

---

## Performance

### Load Time

The bootstrap adds approximately:
- **5-10ms** on first load (file system checks)
- **<1ms** on subsequent loads (opcache)

### What's NOT Loaded

The bootstrap is smart and doesn't load:
- ❌ Other modules (inventory, hr, etc.)
- ❌ Other subfolders (if you're in stock-transfers, it won't load purchase-orders)
- ❌ View files (HTML templates)
- ❌ JavaScript/CSS files

### Optimization

Files are loaded with `require_once`, so multiple includes are safe and fast.

---

## Summary

**One line loads everything you need:**

```php
<?php
require_once __DIR__ . '/../bootstrap.php';

// Now code with confidence!
// - Database: ✅
// - Sessions: ✅  
// - API Response: ✅
// - Your functions: ✅
```

**Clean. Simple. Fast.**

---

**Version:** 1.0.0  
**Last Updated:** October 2025  
**Module:** Consignments
