# AI Memory System - Session Context

**Last Updated:** October 12, 2025  
**Purpose:** Track decisions, changes, and context across coding sessions

---

## 🧠 Recent Decisions (Last 30 Days)

### October 12, 2025
- ✅ **Template Architecture Fixed**: Changed from recreating CIS UI to wrapper approach (includes `/assets/template/` components)
- ✅ **Directory Renamed**: `_base/` → `base/` (PSR-12 compliant, industry standard)
- ✅ **Template Consolidation**: Merged 2 confusing templates into 1 master.php (100 lines)
- ✅ **Knowledge Base Enhanced**: Added auto-cleanup (orphaned docs, old files, empty dirs)
- ✅ **Module Detection Fixed**: Only count directories with `index.php` or `module_bootstrap.php` as modules

**Rationale:** Template was recreating entire CIS UI instead of including it. This broke maintenance and caused duplication.

**Impact:** Reduced template from 418 lines → 100 lines. Now includes real CIS components instead of copying them.

---

## 📚 Key Patterns Established

### 1. Template Pattern (FINAL)
```php
// ONE template: base/views/layouts/master.php
// Wrapper only - includes external CIS components
$templateRoot = $_SERVER['DOCUMENT_ROOT'] . '/assets/template';
include $templateRoot . '/html-header.php';  // <head> + CSS
include $templateRoot . '/header.php';        // Top navbar
include $templateRoot . '/sidemenu.php';      // Sidebar
echo $content;                                 // Module content
include $templateRoot . '/html-footer.php';   // Scripts
include $templateRoot . '/footer.php';        // Footer
```

**Rule:** Never recreate CIS UI components. Always include from `/assets/template/`.

### 2. Database Pattern
```php
// Global factory in /app.php
function cis_pdo(): PDO { ... }

// Module wrapper
namespace Transfers\Lib;
class Db {
    public static function pdo(): PDO {
        return \cis_pdo();
    }
}

// Usage
$pdo = Db::pdo();
$stmt = $pdo->prepare('SELECT * FROM table WHERE id = ?');
$stmt->execute([$id]);
```

**Rule:** Always use `Db::pdo()` wrapper, never direct PDO instantiation.

### 3. Session Pattern
```php
// Auto-started by Kernel::boot()
// Available variables:
$_SESSION['userID']     // Current user
$_SESSION['csrf_token'] // CSRF protection
$_SESSION['outletID']   // User's outlet

// Auth check
if (empty($_SESSION['userID'])) {
    header('Location: /login.php');
    exit;
}
```

**Rule:** Sessions are auto-started. Never call `session_start()` in modules.

### 4. Routing Pattern
```php
// Define routes in module/index.php
$router = new Router();
$router->add('GET', '/', HomeController::class, 'index');
$router->add('POST', '/api/save', ApiController::class, 'save');
$router->dispatch('/modules/modulename');  // Base path
```

**Rule:** All routes in `index.php`. Use Router class, never manual switch/case.

---

## 🚨 Anti-Patterns (DON'T DO THIS)

### ❌ Recreating CIS Templates
```php
// WRONG - Don't recreate header/sidebar/footer
<header>...</header>
<div class="sidebar">...</div>

// RIGHT - Include from CIS
include $_SERVER['DOCUMENT_ROOT'] . '/assets/template/header.php';
```

### ❌ Direct PDO Instantiation
```php
// WRONG
$pdo = new PDO('mysql:host=...', $user, $pass);

// RIGHT
$pdo = \Transfers\Lib\Db::pdo();
```

### ❌ Manual Session Management
```php
// WRONG
session_start();
$_SESSION['custom'] = 'value';

// RIGHT
// Sessions already started by Kernel::boot()
$_SESSION['custom'] = 'value';
```

### ❌ SQL Concatenation
```php
// WRONG
$sql = "SELECT * FROM users WHERE id = " . $id;

// RIGHT
$stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$id]);
```

---

## 🎯 Module Structure Standard

```
module_name/
├── index.php              # Entry point (routes)
├── module_bootstrap.php   # Config
├── controllers/
│   ├── HomeController.php
│   └── Api/
│       └── ApiController.php
├── views/
│   ├── home/
│   │   └── index.php
│   └── layouts/           # Module-specific layouts (if any)
├── lib/
│   ├── Db.php            # Database wrapper
│   ├── Validation.php
│   └── Helpers.php
├── assets/
│   ├── css/
│   └── js/
└── components/
    └── partials/
```

**Rule:** Every module follows this structure. No exceptions.

---

## 🔍 Common Gotchas & Solutions

### Issue: "Class not found"
**Solution:** Check `base/lib/Kernel.php` autoloader. Add module namespace:
```php
$prefixes = [
    'Modules\\Base\\' => __DIR__ . '/',
    'Modules\\YourModule\\' => dirname(__DIR__, 2) . '/yourmodule/',
];
```

### Issue: Template not loading
**Solution:** 
1. Check `CIS_MODULE_CONTEXT` is defined
2. Verify `$content` variable is set
3. Ensure `master.php` path is correct in PageController

### Issue: Routes not working
**Solution:**
1. Check `$router->dispatch()` has correct base path
2. Verify route defined in `index.php`
3. Check controller class exists and method is public

### Issue: Database connection fails
**Solution:**
1. Verify `/app.php` is loaded (via `Kernel::boot()`)
2. Check DB_* environment variables
3. Use `Db::pdo()` wrapper, not direct PDO

---

## 📝 Documentation Philosophy

### What to Keep:
- ✅ Master README.md (comprehensive)
- ✅ FILE_RELATIONSHIPS.md (AI navigation)
- ✅ STATUS.md (auto-generated)
- ✅ VERIFICATION_REPORT.md (system health)
- ✅ This memory file

### What to Delete:
- ❌ Outdated implementation summaries
- ❌ Duplicate/conflicting docs
- ❌ Old refactoring plans
- ❌ Temporary notes

**Rule:** Delete inaccurate docs immediately. One accurate file beats ten outdated ones.

---

## 🔄 Auto-Maintenance Settings

Current auto-cleanup config:
```javascript
autoCleanup: {
    enabled: true,
    oldDocsAge: 30,           // Delete docs not updated in 30 days
    logRetention: 7,           // Keep logs for 7 days
    orphanedModuleDocs: true,  // Remove docs for deleted modules
    emptyDirs: true            // Remove empty directories
}
```

**Protected Files (Never Auto-Delete):**
- README.md
- STATUS.md
- VERIFICATION_REPORT.md
- FILE_RELATIONSHIPS.md
- AI_MEMORY.md (this file)

---

## 🎓 Lessons Learned

### Lesson 1: Template Architecture
**Problem:** Original template recreated entire CIS UI (418 lines of duplication)  
**Solution:** Wrapper approach - include from `/assets/template/` (100 lines)  
**Takeaway:** Never duplicate external components. Always include or extend.

### Lesson 2: Knowledge Base Accuracy
**Problem:** Multiple outdated docs caused confusion  
**Solution:** Delete aggressively, maintain ONE master doc  
**Takeaway:** One accurate file > ten confusing ones

### Lesson 3: Module Detection
**Problem:** Scanner counted `docs/`, `tools/` as modules  
**Solution:** Strict validation - must have `index.php` or `module_bootstrap.php`  
**Takeaway:** Be strict about what qualifies as a "module"

---

## 🚀 Future Improvements (Ideas)

- [ ] Add performance profiling to auto-scan (flag slow queries)
- [ ] Auto-detect missing indexes on hot tables
- [ ] Generate API documentation from route comments
- [ ] Auto-create test stubs for new controllers
- [ ] Detect unused views/components
- [ ] Track file size growth over time

---

**Last Session:** October 12, 2025  
**Next Review:** When major architectural changes are made
