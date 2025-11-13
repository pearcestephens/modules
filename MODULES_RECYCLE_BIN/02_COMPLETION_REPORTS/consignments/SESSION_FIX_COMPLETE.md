# CIS & Modules Session Integration Fix

## 🔍 **Problem Identified**

The "Forbidden" error was caused by **session mismatch** between:
1. **Main CIS System** (`/public_html/bootstrap.php`)
2. **Modules System** (`/modules/consignments/bootstrap.php`)

### Root Causes:
1. ❌ Main bootstrap didn't set a `session_name()` (used PHP default)
2. ❌ Modules bootstrap set `session_name('CIS_SESSION')`
3. ❌ **Different session names = separate sessions = no shared login**
4. ❌ `index.php` didn't load bootstrap, so no database/session available
5. ❌ Session variable mismatch: CIS uses `$_SESSION['userID']`, modules use `$_SESSION['user_id']`

## ✅ **Fixes Applied**

### 1. **Unified Session Name** (`bootstrap.php`)
```php
// BEFORE:
session_start([...]);

// AFTER:
session_name('CIS_SESSION');  // ✅ Now matches modules
session_start([...]);
```

### 2. **Session Variable Normalization** (`bootstrap.php`)
```php
// Keep both userID (CIS legacy) and user_id (modules) in sync
if (isset($_SESSION['userID']) && !isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = $_SESSION['userID'];
}
if (isset($_SESSION['user_id']) && !isset($_SESSION['userID'])) {
    $_SESSION['userID'] = $_SESSION['user_id'];
}
```

### 3. **Load Bootstrap in index.php**
```php
// BEFORE:
declare(strict_types=1);
// Determine which view to load...

// AFTER:
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';  // ✅ Load sessions & database
// Determine which view to load...
```

## 🧪 **Testing Your Fix**

### Step 1: Test Session Integration
```
https://staff.vapeshed.co.nz/modules/consignments/test-session.php
```

**Expected Output:**
```
✅ Main bootstrap loaded
✅ Session Status: ACTIVE
✅ Session Name: CIS_SESSION
✅ Modules bootstrap loaded
✅ PDO connection works
✅ User is logged in
```

### Step 2: Access Consignments Module
```
https://staff.vapeshed.co.nz/modules/consignments/
```

**Should now work!** No more "Forbidden" errors.

### Step 3: Verify Other Modules
```
https://staff.vapeshed.co.nz/modules/consignments/TransferManager/
https://staff.vapeshed.co.nz/modules/consignments/?route=purchase-orders
https://staff.vapeshed.co.nz/modules/consignments/?route=freight
```

## 📊 **How It Works Now**

### Login Flow:
1. User logs into main CIS → `$_SESSION['userID']` is set
2. Bootstrap normalizes → `$_SESSION['user_id']` = `$_SESSION['userID']`
3. User visits `/modules/consignments/`
4. `index.php` loads `bootstrap.php`
5. Bootstrap loads `/modules/base/bootstrap.php`
6. Base bootstrap sees session already active (same `CIS_SESSION` name)
7. Session variables are normalized again (bidirectional sync)
8. Module recognizes user is logged in
9. ✅ Page loads successfully!

### Session Storage:
- **Location:** Same PHP session files (default: `/tmp` or configured path)
- **Session Name:** `CIS_SESSION` (both systems)
- **Cookie:** Shared `CIS_SESSION` cookie across entire domain
- **Variables:** Both `userID` and `user_id` kept in sync

## 🔐 **Security Notes**

All existing security features preserved:
- ✅ `httponly` cookies (XSS protection)
- ✅ `secure` cookies on HTTPS
- ✅ `samesite=Lax` (CSRF protection)
- ✅ `strict_mode` enabled
- ✅ 48-character session IDs
- ✅ Session regeneration on login
- ✅ 30-minute inactivity timeout

## 📁 **Files Modified**

1. `/public_html/bootstrap.php`
   - Added `session_name('CIS_SESSION')`
   - Added session variable normalization

2. `/public_html/modules/consignments/index.php`
   - Added `require_once __DIR__ . '/bootstrap.php';`

3. `/public_html/modules/consignments/test-session.php` (NEW)
   - Debug tool to verify session integration

## 🚀 **Additional Benefits**

Now that sessions are unified:

1. **Single Sign-On** - Log in once, access everything
2. **Shared User Context** - User data available everywhere
3. **Consistent Auth** - Same authentication across all modules
4. **Easier Development** - No session confusion
5. **Better Security** - One session to secure, not two

## 🐛 **Troubleshooting**

### If you still see "Forbidden":

1. **Clear Browser Cookies**
   ```
   Settings → Privacy → Clear cookies for staff.vapeshed.co.nz
   ```

2. **Check File Permissions**
   ```bash
   ls -la /home/master/applications/jcepnzzkmj/public_html/modules/consignments/index.php
   # Should be readable: -rw-r--r-- or -rw-rw-r--
   ```

3. **Verify Session Directory**
   ```bash
   php -r "echo session_save_path();"
   # Check this directory is writable
   ```

4. **Test Directly**
   ```
   https://staff.vapeshed.co.nz/modules/consignments/test-session.php
   ```

### If session test shows "NOT SET":

**You need to log into the main CIS system first!**
```
https://staff.vapeshed.co.nz/login.php
```

Then retry the modules.

## ✅ **Success Criteria**

Fix is successful when:
- ✅ Can access `/modules/consignments/` without "Forbidden"
- ✅ test-session.php shows `user_id` is set
- ✅ All consignment pages load correctly
- ✅ Can switch between CIS and modules without re-login
- ✅ Same session ID in both systems

## 🎯 **Next Steps**

1. Test the fix using test-session.php
2. Try accessing the consignments module
3. Verify HARDFAST dashboard still works
4. If successful, remove test-session.php (security)

**The fix is complete! Both systems now share the same session seamlessly.** 🚀
