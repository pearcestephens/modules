# Lessons Learned - Development Notes

**Purpose:** Capture mistakes, gotchas, and "I wish I knew that earlier" moments

---

## Template & UI

### ❌ Lesson: Don't Recreate External Components
**What Happened:** Original template recreated entire CIS UI (header, sidebar, footer) - 418 lines of duplication

**Problem:**
- Every CIS UI change required updating module template
- Two sources of truth (CIS template + module template)
- Styling conflicts
- Maintenance nightmare

**Solution:** Wrapper approach - include from `/assets/template/`

**Code:**
```php
// WRONG (418 lines of duplication)
<header class="app-header">
  <div class="logo">...</div>
  <nav>...</nav>
</header>

// RIGHT (100 lines, includes CIS)
<?php include $_SERVER['DOCUMENT_ROOT'] . '/assets/template/header.php'; ?>
```

**Takeaway:** Never duplicate external components. Always include or extend.

---

### ❌ Lesson: Underscore Prefix Not Standard for Directories
**What Happened:** Named base directory `_base/` thinking underscore means "internal"

**Problem:**
- Not PSR-12 compliant
- Looks like legacy/deprecated code
- Confusing for new developers

**Solution:** Renamed to `base/` (industry standard)

**Takeaway:** Underscore prefix is for PHP magic methods (`__construct`) and private vars (`$_internal`), NOT directories.

---

## Knowledge Base & Documentation

### ❌ Lesson: Multiple Docs Cause Confusion
**What Happened:** Had 7+ outdated docs (REFACTORING_COMPLETE.md, TEMPLATE_FIXED.md, etc.)

**Problem:**
- Which one is current?
- Contradictory information
- Wasted time reading wrong doc
- AI gets confused by conflicting info

**Solution:** Delete aggressively, maintain ONE master doc

**Takeaway:** One accurate file > ten outdated ones. Delete immediately, don't "keep for reference."

---

### ❌ Lesson: Auto-Scanners Need Strict Rules
**What Happened:** KB refresh counted `docs/`, `tools/` as "modules" (reported 5 modules instead of 1)

**Problem:**
- Inflated stats
- Generated useless module docs
- Polluted search index

**Solution:** Strict validation - must have `index.php` or `module_bootstrap.php`

**Code:**
```javascript
// WRONG (too permissive)
return !skipDirs.includes(name) && !name.startsWith('.');

// RIGHT (strict validation)
return fs.existsSync(path.join(modulePath, 'index.php')) ||
       fs.existsSync(path.join(modulePath, 'module_bootstrap.php'));
```

**Takeaway:** Be strict about what qualifies as a "thing." Don't assume.

---

## Database & Sessions

### ❌ Lesson: Don't Assume Sessions Are Started
**What Almost Happened:** Could have called `session_start()` in module, causing "headers already sent" error

**Problem:**
- Sessions started in `/app.php` (external CIS core)
- Calling again would throw error
- Hard to debug

**Solution:** Document that sessions are auto-started by `Kernel::boot()`

**Takeaway:** Document external bootstrapping clearly. Don't duplicate setup logic.

---

### ❌ Lesson: Global Functions Can Be Hard to Track
**What Happened:** Database factory `cis_pdo()` defined in `/app.php` (external)

**Problem:**
- Not obvious where it's defined
- IDE doesn't autocomplete
- New devs confused

**Solution:** Create module wrapper class `Db::pdo()` that calls `cis_pdo()`

**Code:**
```php
// Wrapper makes it discoverable
namespace Transfers\Lib;

class Db {
    public static function pdo(): PDO {
        return \cis_pdo();  // Call global factory
    }
}
```

**Takeaway:** Wrap external globals in module classes for discoverability.

---

## Routing & Controllers

### ❌ Lesson: Base Path Must Match URL Structure
**What Could Go Wrong:** If `$router->dispatch('/wrong/path')`, routes won't match

**Problem:**
- Routes defined relative to base path
- Wrong base = 404 errors
- Hard to debug

**Solution:** Base path must match actual URL structure

**Code:**
```php
// URL: https://staff.vapeshed.co.nz/modules/consignments/transfers/pack

// WRONG
$router->dispatch('/');  // Would look for /transfers/pack

// RIGHT
$router->dispatch('/modules/consignments');  // Matches URL structure
```

**Takeaway:** Router base path = everything before the route pattern in URL.

---

## Auto-Cleanup & Maintenance

### ✅ Lesson: Auto-Cleanup Needs Protection
**What Could Go Wrong:** Delete critical files (README.md, STATUS.md)

**Solution:** Protected files list

**Code:**
```javascript
const protectedFiles = [
    'README.md', 
    'STATUS.md', 
    'VERIFICATION_REPORT.md', 
    'FILE_RELATIONSHIPS.md'
];
if (protectedFiles.includes(item.name)) continue;  // Skip
```

**Takeaway:** Always have safeguards. Don't trust "it'll be fine."

---

### ✅ Lesson: Empty Dirs Can Accumulate
**What Happened:** 12 empty directories in `docs/` subdirectories

**Problem:**
- Clutter
- Confusing (are these supposed to have content?)
- Git tracks them unnecessarily

**Solution:** Auto-remove empty directories on refresh

**Takeaway:** Small cleanup tasks add up. Automate them.

---

## Performance & Optimization

### 💡 Lesson: File Size Matters for AI Search
**Observation:** Search index 8.8KB (15 entries) is fast

**Why It Matters:**
- AI can load entire index in one go
- Fast searches
- Less token usage

**Best Practice:**
- Keep docs under 25KB
- Split large files into logical chunks
- Use headings for scanability

**Takeaway:** Small, focused files > one giant file (for search purposes).

---

## Testing & Debugging

### 💡 Lesson: Health Check Endpoints Are Essential
**Why:** `/health.php` returns JSON with module status

**Benefits:**
- Quick sanity check
- Monitoring/alerting
- CI/CD integration

**Code:**
```php
// health.php
header('Content-Type: application/json');
echo json_encode([
    'status' => 'ok',
    'module' => 'consignments',
    'timestamp' => time()
]);
```

**Takeaway:** Add health endpoints to all modules from day one.

---

### 💡 Lesson: Bot Bypass for Testing
**What:** `?bot=true` bypasses authentication

**Why Useful:**
- Automated tests
- Headless browser testing
- CI/CD pipelines

**Security:** Only works when `$_ENV['BOT_BYPASS_AUTH']` is set

**Takeaway:** Build test hooks from the start.

---

## Code Quality

### 💡 Lesson: Lint Early, Lint Often
**What:** Auto-lint checks on every KB refresh

**Checks:**
- Bootstrap 4/5 mixing
- Duplicate `<body>` tags
- Raw includes in views
- Files over 25KB

**Benefit:** Catch issues before they become problems

**Takeaway:** Automated quality checks = less tech debt.

---

## File Corruption

### ❌ Lesson: File Editors Can Corrupt Files
**What Happened:** `master.php` became corrupted with duplicate/merged lines (390 lines instead of 68)

**Problem:**
- Entire template unusable
- PHP parse errors
- All module pages broken

**How to Detect:**
- `php -l` shows syntax errors
- Unusual line count (10x normal)
- Duplicate `<?php` tags or docblocks

**How to Fix:**
```bash
# 1. Backup
cp file.php file.php.CORRUPTED

# 2. Remove
rm file.php

# 3. Recreate using heredoc (safer than editors)
cat > file.php << 'ENDOFFILE'
[clean content]
ENDOFFILE

# 4. Verify
php -l file.php
```

**Prevention:**
- Add `php -l` check to auto-refresh
- Always commit before major edits
- Check line count after saves

**Takeaway:** File corruption happens. Have recovery procedures ready.

---

## Gotchas to Remember

### 1. **Cloudways Paths**
- Apache logs: `/logs/apache_phpstack-129337-518184.cloudwaysapps.com.error.log`
- Not standard `/var/log/apache2/error.log`

### 2. **NZ Timezone**
- DB: `SET time_zone = "+13:00"`
- PHP: `date_default_timezone_set('Pacific/Auckland')`

### 3. **CoreUI v2 + Bootstrap 4.2**
- DON'T mix Bootstrap 5 syntax (`data-bs-dismiss`)
- Use Bootstrap 4 syntax (`data-dismiss`)

### 4. **Session Cookie Settings**
- `httponly`, `samesite=Strict`, `use_strict_mode`
- Already configured in `/app.php`

### 5. **PSR-4 Autoloading**
- Namespace must match directory structure
- `Modules\Consignments\controllers\PackController` → `consignments/controllers/PackController.php`

---

## Questions to Ask Before Coding

1. **Does this already exist in CIS?** (Don't duplicate)
2. **Is this the right place?** (Module vs base vs core)
3. **Will this break existing code?** (Backwards compatibility)
4. **Can I test this automatically?** (Health endpoint, lint check)
5. **Is this documented?** (Update README, memory files)

---

**Last Updated:** October 12, 2025  
**Add New Lessons As You Learn Them!**
