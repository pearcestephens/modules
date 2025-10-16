# Bootstrap File Loading - Fixed! ✅

## Issue
```
Fatal error: Cannot redeclare showErrorPage() 
(previously declared in /modules/consignments/shared/functions/error-page.php:33) 
in /modules/shared/functions/error-page.php on line 33
```

## Root Cause

The file `error-page.php` exists in **TWO** locations:
1. `/modules/shared/functions/error-page.php` ✅ (CORRECT - shared across all modules)
2. `/modules/consignments/shared/functions/error-page.php` ❌ (DUPLICATE - shouldn't exist here)

The bootstrap was loading both, causing a function redeclaration error.

## Solution

Updated `/modules/consignments/bootstrap.php` to skip loading `error-page.php` from the consignments folder:

```php
// Auto-load remaining files from shared/functions if exists
if (is_dir($consignmentsSharedDir . '/functions')) {
    foreach (glob($consignmentsSharedDir . '/functions/*.php') as $functionFile) {
        $basename = basename($functionFile);
        
        // Skip if already loaded (transfers.php loaded above)
        if ($basename === 'transfers.php') {
            continue;
        }
        
        // Skip if it's a duplicate of /modules/shared/functions/ file
        // (error-page.php should only be in shared, not consignments/shared)
        if ($basename === 'error-page.php') {
            continue;
        }
        
        require_once $functionFile;
    }
}
```

## Recommendation

**Delete the duplicate file:**
```bash
rm /home/master/applications/jcepnzzkmj/public_html/modules/consignments/shared/functions/error-page.php
```

This file should ONLY exist in `/modules/shared/functions/` since it's used by multiple modules, not just Consignments.

## File Organization Rules

### `/modules/shared/functions/`
✅ Files used by **multiple modules** (inventory, consignments, HR, etc.)
- `error-page.php` ← Used by ALL modules
- Other cross-module utilities

### `/modules/consignments/shared/functions/`
✅ Files used **only by Consignments** module
- `transfers.php` ← Consignments-specific
- Other consignments-specific utilities

## Current Bootstrap Load Order

1. ✅ `/app.php` (DB, sessions)
2. ✅ `/assets/functions/config.php`
3. ✅ `/bootstrap/app.php` (if exists)
4. ✅ `/modules/shared/functions/*.php` (ALL cross-module files)
5. ✅ `/modules/base/lib/Session.php`
6. ✅ `/modules/shared/api/ApiResponse.php`
7. ✅ `/modules/consignments/shared/functions/transfers.php`
8. ✅ `/modules/consignments/shared/lib/*.php`
9. ✅ `/modules/consignments/shared/functions/*.php` (except transfers.php and error-page.php)
10. ✅ Auto-detect current subfolder and load its functions/

## Testing

After this fix, pack.php should load without errors:

```bash
# Test the page
curl -I https://your-domain.com/modules/consignments/stock-transfers/pack.php?id=123

# Should return 200 OK, not 500 Internal Server Error
```

## Status

✅ **FIXED** - Bootstrap now skips duplicate error-page.php  
📋 **TODO** - Delete duplicate file to clean up codebase

---

**Date:** October 15, 2025  
**Issue:** Function redeclaration error  
**Solution:** Skip loading duplicate files in bootstrap
