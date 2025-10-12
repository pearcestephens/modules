# CIS Modules System - Master Documentation

**Version:** 3.0.0  
**Last Updated:** October 12, 2025  
**Status:** ✅ Production Ready

> **📌 This is the ONLY documentation file you need.** Everything else auto-generates or is outdated noise.

---

## 📚 Quick Navigation

1. [**Quick Start**](#-quick-start) - Get running in 30 seconds
2. [**Project Structure**](#-project-structure) - Where everything lives
3. [**Database & Sessions**](#%EF%B8%8F-database--sessions) - How data works
4. [**Routing**](#%EF%B8%8F-routing-system) - URL → Controller mapping
5. [**Templates**](#-template-architecture) - ONE template approach
6. [**Error Handling**](#%EF%B8%8F-error-handling) - Debug like a pro
7. [**Building Modules**](#%EF%B8%8F-building-new-modules) - Step-by-step guide
8. [**Knowledge Base**](#-knowledge-base-refresh) - Auto-docs system
9. [**External Dependencies**](#-external-dependencies) - CIS, DB, CSS/JS

---

## 🚀 Quick Start

### **Access Modules**
```
https://staff.vapeshed.co.nz/modules/consignments/
```

### **Available Routes**
```
GET  /modules/consignments/                    → Home
GET  /modules/consignments/transfers/pack      → Pack Transfer  
GET  /modules/consignments/transfers/receive   → Receive Transfer
GET  /modules/consignments/transfers/hub       → Consignment Hub
POST /modules/consignments/transfers/api/...   → API Endpoints
```

### **Health Check**
```bash
curl https://staff.vapeshed.co.nz/modules/consignments/health.php
# Returns: {"status":"ok","module":"consignments","timestamp":...}
```

---

## 📁 Project Structure

```
modules/
├── README.md                ← THIS FILE (master docs - ONLY ONE YOU NEED)
├── .vscode/
│   ├── tasks.json          ← Auto-runs refresh-kb on folder open
│   └── refresh-kb.js       ← Knowledge base generator (168 files scanned)
├── _copilot/                ← AUTO-GENERATED (don't edit manually!)
│   ├── MODULES/            ← Per-module docs (README, routes, controllers)
│   ├── SEARCH/
│   │   └── index.json      ← Searchable index (57 entries, 28.6KB)
│   ├── STATUS.md           ← Lint results + scan stats
│   └── logs/               ← Refresh logs
├── base/                    ← Module infrastructure (inherited by all)
│   ├── lib/
│   │   ├── Controller/
│   │   │   ├── BaseController.php   ← All controllers extend this
│   │   │   ├── PageController.php   ← HTML pages (uses master.php)
│   │   │   └── ApiController.php    ← JSON APIs
│   │   ├── ErrorHandler.php         ← Basic error handler
│   │   ├── Kernel.php               ← Bootstrap (sessions, DB, autoload)
│   │   ├── Router.php               ← URL routing
│   │   ├── View.php                 ← Template rendering
│   │   ├── Validation.php           ← Input validation
│   │   ├── Security.php             ← CSRF, auth helpers
│   │   └── Helpers.php              ← URL helpers
│   ├── views/
│   │   └── layouts/
│   │       └── master.php           ← ONE TEMPLATE (includes CIS components)
│   └── tests/
│       └── smoke.php                ← Basic health test
├── consignments/            ← Example module
│   ├── index.php           ← Entry point (defines routes)
│   ├── health.php          ← Health check endpoint
│   ├── module_bootstrap.php ← Module-specific config
│   ├── controllers/        ← MVC controllers
│   ├── views/              ← HTML templates
│   ├── lib/                ← Module utilities
│   └── assets/             ← CSS/JS (module-specific)
├── core/                    ← Legacy utilities
│   ├── Bootstrap.php
│   ├── ErrorHandler.php    ← UPGRADED v2.0 (enterprise-grade)
│   └── ModuleUtilities.php
├── docs/                    ← Minimal high-level docs only
│   ├── CODING_STANDARDS.md ← PSR-12, security, patterns
│   ├── ERROR_HANDLER_GUIDE.md ← Full error handler documentation
│   └── TEMPLATE_ARCHITECTURE.md ← Template usage guide
└── archived/                ← Old/deprecated (380KB archived)
```

---

## 🗄️ Database & Sessions

### **Database Connection**

Uses **global CIS PDO factory** from `/app.php`:

```php
<?php
// Defined in /app.php (outside modules)
function cis_pdo(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]
        );
    }
    return $pdo;
}

// Module wrapper: consignments/lib/Db.php
namespace Transfers\Lib;

final class Db {
    public static function pdo(): PDO {
        return \cis_pdo();  // Uses global factory
    }
}

// Usage in controllers
$pdo = Db::pdo();
$stmt = $pdo->prepare('SELECT * FROM transfers WHERE id = ?');
$stmt->execute([$id]);
```

**Config:** `/app.php` or `/private_html/app.php`
```php
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'cis_database');
define('DB_USER', getenv('DB_USER') ?: 'cis_user');
define('DB_PASS', getenv('DB_PASS') ?: 'password');
```

**Timezone:**
```bash
DB_TZ="+13:00"  # NZ timezone (set in environment)
```

### **Sessions**

**Auto-started by `Kernel::boot()`:**

```php
<?php
// Automatic in Kernel::boot()
if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
}

// CSRF token auto-generated
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
```

**Session Variables:**
```php
$_SESSION['userID']     // Current user (set by CIS auth)
$_SESSION['csrf_token'] // CSRF protection
$_SESSION['outletID']   // User's outlet (if applicable)
```

**Auth Check:**
```php
if (empty($_SESSION['userID'])) {
    header('Location: /login.php');
    exit;
}
```

**Bot Bypass (testing):**
```
?bot=true  → Sets $_ENV['BOT_BYPASS_AUTH'] = '1'
```

---

## 🛣️ Routing System

**Entry Point:** `/modules/consignments/index.php`

```php
<?php
use Modules\Base\Router;
use Modules\Consignments\controllers\PackController;

require __DIR__ . '/module_bootstrap.php';
require_once dirname(__DIR__) . '/base/lib/ErrorHandler.php';
require_once dirname(__DIR__) . '/base/lib/Kernel.php';

ErrorHandler::register($_ENV['APP_DEBUG'] === '1');
Kernel::boot();

$router = new Router();
$router->add('GET', '/', HomeController::class, 'index');
$router->add('GET', '/transfers/pack', PackController::class, 'index');
$router->add('POST', '/transfers/api/pack/add-line', PackApiController::class, 'addLine');
$router->dispatch('/modules/consignments');  // Base path
```

**URL Parameters:**
```php
// URL: /modules/consignments/transfers/pack?transfer=123
$transferId = (int)($_GET['transfer'] ?? 0);
```

---

## 🎨 Template Architecture

### **The ONE Template Approach**

**File:** `/modules/base/views/layouts/master.php`

**Key Rule:** Modules **INCLUDE** CIS components, don't recreate them!

```php
<?php
$templateRoot = $_SERVER['DOCUMENT_ROOT'] . '/assets/template';

include $templateRoot . '/html-header.php';  // <head> + CSS
?>
<body>
    <?php include $templateRoot . '/header.php'; ?>      <!-- Navbar -->
    <div class="app-body">
        <?php include $templateRoot . '/sidemenu.php'; ?> <!-- Sidebar -->
        <main class="main">
            <ol class="breadcrumb">...</ol>               <!-- Breadcrumbs -->
            <div class="container-fluid">
                <?= $content ?>                           <!-- YOUR MODULE HTML -->
            </div>
        </main>
    </div>
    <?php include $templateRoot . '/html-footer.php'; ?>  <!-- Scripts -->
    <?php include $templateRoot . '/footer.php'; ?>       <!-- Footer -->
</body>
```

### **CIS Template Components (External)**

**Location:** `/assets/template/` (OUTSIDE `/modules/`)

```
/assets/template/
├── html-header.php       # <head>, CSS, jQuery, meta
├── header.php            # Top navbar (logo, user menu)
├── sidemenu.php          # Left sidebar (dynamic navigation from DB)
├── html-footer.php       # Bootstrap, CoreUI, scripts
├── footer.php            # Copyright footer
└── (other widgets)
```

### **CSS/JS (Stay Outside Modules)**

```
/assets/css/              # CoreUI v2 + Bootstrap 4
/assets/js/               # jQuery, Moment, CoreUI
```

### **Using Templates**

**Method 1: PageController (Auto)**
```php
<?php
class PackController extends PageController {
    public function index(): string {
        return $this->view(__DIR__ . '/../views/pack/full.php', [
            'pageTitle' => 'Pack Transfer',
            'data' => $data
        ]);
    }
}
```

**Method 2: Manual**
```php
<?php
define('CIS_MODULE_CONTEXT', true);

$pageTitle = 'My Page';
ob_start();
?>
<div class="card">Your HTML</div>
<?php
$content = ob_get_clean();
include __DIR__ . '/../base/views/layouts/master.php';
```

**Variables:**
```php
$pageTitle    // Browser <title>
$content      // Your HTML
$breadcrumbs  // [['label' => 'Home', 'href' => '/'], ...]
$moduleCSS    // ['/modules/example/assets/css/style.css']
$moduleJS     // ['/modules/example/assets/js/app.js']
```

---

## ⚠️ Error Handling

**Upgraded Error Handler v2.0:** `/modules/core/ErrorHandler.php`

```php
<?php
use Modules\Core\ErrorHandler;

// Development (show full errors + stack trace)
ErrorHandler::register(debugMode: true);

// Production (hide details from users)
ErrorHandler::register(debugMode: false);

// Add debugging context
ErrorHandler::addContext('sql_query', $query);
ErrorHandler::addContext('user_id', $_SESSION['userID']);
```

**Features:**
- ✅ Full stack trace with code preview (5 lines context)
- ✅ Syntax-highlighted PHP code
- ✅ Request/environment info
- ✅ Context system (SQL, API responses, user data)
- ✅ Unique error IDs for log correlation
- ✅ Beautiful dark theme (debug mode)
- ✅ Clean user screen (production)

**See:** `docs/ERROR_HANDLER_GUIDE.md`

---

## 🏗️ Building New Modules

### **1. Create Structure**

```bash
modules/mymodule/
├── index.php             # Routes
├── health.php            # Health check
├── module_bootstrap.php  # Config
├── controllers/
├── views/
├── lib/
└── assets/
```

### **2. Entry Point**

```php
<?php
// mymodule/index.php
declare(strict_types=1);

use Modules\Base\Router;
use Modules\MyModule\controllers\HomeController;

require __DIR__ . '/module_bootstrap.php';
require_once dirname(__DIR__) . '/base/lib/ErrorHandler.php';
require_once dirname(__DIR__) . '/base/lib/Kernel.php';

ErrorHandler::register($_ENV['APP_DEBUG'] === '1');
Kernel::boot();

$router = new Router();
$router->add('GET', '/', HomeController::class, 'index');
$router->dispatch('/modules/mymodule');
```

### **3. Controller**

```php
<?php
namespace Modules\MyModule\controllers;

use Modules\Base\Controller\PageController;

final class HomeController extends PageController {
    public function index(): string {
        return $this->view(__DIR__ . '/../views/home/index.php', [
            'pageTitle' => 'My Module'
        ]);
    }
}
```

### **4. Register Autoloader**

Edit `base/lib/Kernel.php`:
```php
$prefixes = [
    'Modules\\Base\\' => __DIR__ . '/',
    'Modules\\MyModule\\' => dirname(__DIR__, 2) . '/mymodule/', // ADD THIS
];
```

### **5. Test**

```bash
https://staff.vapeshed.co.nz/modules/mymodule/
```

---

## 🔄 Knowledge Base Refresh

### **What Is It?**

Auto-generates documentation by scanning your code:
- ✅ Per-module docs (README, routes, controllers, views)
- ✅ Searchable JSON index
- ✅ Lint reports (Bootstrap mixing, oversized files, etc.)

### **When to Run**

**Automatic:** On workspace open (`.vscode/tasks.json`)

**Manual:**
```bash
node .vscode/refresh-kb.js
```

### **What Gets Generated**

```
_copilot/
├── MODULES/module_name/
│   ├── README.md
│   ├── routes.md
│   ├── controllers.md
│   └── views.md
├── SEARCH/index.json      # Searchable (57 entries)
└── STATUS.md              # Lint results
```

### **Lint Checks**

- ❌ Bootstrap 4/5 mixing (`data-dismiss` vs `data-bs-dismiss`)
- ❌ Duplicate `<body>` tags
- ❌ Raw includes in views
- ❌ Files > 25KB

### **Last Refresh Stats**

```
Modules:  6
Files:    161
Docs:     24
Entries:  57
Size:     28.6KB
Updated:  2025-10-12 12:51
```

### **Best Practices**

1. Run after major changes
2. Fix lint warnings before commit
3. Delete outdated docs immediately
4. Let auto-refresh handle module docs

---

## 🌐 External Dependencies

### **Main CIS App** (Outside `/modules/`)

```
/app.php                  # Bootstrap (sessions, DB, auth)
/assets/template/         # UI components
/assets/css/              # Styles
/assets/js/               # Scripts
```

**Key Functions:**
```php
cis_pdo()                 # PDO factory
getUserInformation($uid)  # User data
getNavigationMenus()      # Sidebar
getCurrentUserPermissions($uid) # Permissions
```

### **Database Tables**

```sql
transfers         # Transfer records
transfer_items    # Line items
vend_products     # Product catalog
vend_outlets      # Outlet list
```

### **Environment Variables**

```bash
DB_HOST=localhost
DB_NAME=cis_database
DB_USER=cis_user
DB_PASS=password
DB_TZ="+13:00"
APP_DEBUG=0           # 0=prod, 1=debug
APP_TZ="Pacific/Auckland"
```

### **CSS/JS Stack**

- CoreUI v2.0 + Bootstrap 4.2
- jQuery 3.7.1
- Font Awesome 5.15
- Moment.js 2.29

---

## ✅ Quick Reference

### **Key Files**
```
modules/README.md                         ← THIS FILE (ONLY ONE YOU NEED)
modules/base/lib/Kernel.php               ← Bootstrap
modules/base/lib/Router.php               ← Routing
modules/base/views/layouts/master.php     ← ONE template
modules/core/ErrorHandler.php             ← Upgraded error handler v2.0
.vscode/refresh-kb.js                     ← Auto-docs
_copilot/SEARCH/index.json                ← Searchable index
_copilot/STATUS.md                        ← Lint results
```

### **Common Commands**
```bash
# Refresh knowledge base
node .vscode/refresh-kb.js

# Check health
curl MODULE_URL/health.php

# View lint status
cat _copilot/STATUS.md

# Enable debug
export APP_DEBUG=1

# Test with bot bypass
curl "URL?bot=true"
```

### **Auth Check**
```php
if (empty($_SESSION['userID'])) {
    header('Location: /login.php');
    exit;
}
```

### **Database Query**
```php
$pdo = \Transfers\Lib\Db::pdo();
$stmt = $pdo->prepare('SELECT * FROM table WHERE id = ?');
$stmt->execute([$id]);
```

---

## 🎯 Core Principles

1. **DRY** - Don't recreate CIS components
2. **ONE Template** - `master.php` only
3. **Auto-Docs** - Let refresh-kb handle it
4. **Delete Noise** - Remove outdated docs
5. **Security First** - CSRF, prepared statements, auth checks

---

## 🚨 Red Flags (DON'T DO THIS)

❌ Recreate header/footer/sidebar  
❌ Keep outdated docs  
❌ Mix Bootstrap 4/5 syntax  
❌ SQL concatenation  
❌ Skip CSRF validation  
❌ Expose debug in production  

---

## 📚 More Docs (Only If Needed)

- `docs/CODING_STANDARDS.md` - PSR-12, security
- `docs/ERROR_HANDLER_GUIDE.md` - Full error handler guide
- `docs/TEMPLATE_ARCHITECTURE.md` - Detailed template info

---

## 🆘 Troubleshooting

**"Class not found"**  
→ Check `Kernel.php` autoloader, run `composer dump-autoload`

**Routes not working**  
→ Check `$router->dispatch()` base path

**Template not loading**  
→ Define `CIS_MODULE_CONTEXT`, set `$content`

**DB connection fails**  
→ Check `/app.php` loaded, verify credentials

---

## ✅ Production Checklist

- [ ] Knowledge base refreshed
- [ ] No lint warnings
- [ ] All routes tested
- [ ] Health check returns 200
- [ ] Error handler tested
- [ ] No debug code left
- [ ] Outdated docs deleted
- [ ] This README up-to-date

---

**Last Updated:** October 12, 2025  
**Status:** ✅ **CURRENT & ACCURATE**  
**Next Refresh:** Run `node .vscode/refresh-kb.js` after changes

---

🎉 **That's it! You now have everything you need in ONE file.**
