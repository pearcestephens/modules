# CIS MODULE ARCHITECTURE ANALYSIS & REFACTORING PROPOSAL
**Deep Dive Analysis - October 27, 2025**

---

## 📊 CURRENT STATE ANALYSIS

### Modules Inventory
1. **shared/** - Base infrastructure (207-line bootstrap)
2. **consignments/** - Stock transfers & purchase orders
3. **staff-accounts/** - Financial tracking with Xero/Vend
4. **staff-performance/** - Performance tracking & gamification
5. **flagged-products/** - Inventory accuracy tracking
6. **flagged_products/** - ⚠️ DUPLICATE with underscore
7. **fraud-prevention/** - Minimal (docs only)

---

## 🚨 IDENTIFIED PROBLEMS

### 1. NAMING INCONSISTENCIES ⚠️⚠️⚠️
**Problem:**
- `shared/` should be `base/` - unclear that this is the foundation
- `flagged-products/` AND `flagged_products/` - TWO versions of same module!
- Hyphenated names (staff-accounts) vs underscores (flagged_products)
- "shared" implies peer-level, not parent-level inheritance

**Impact:** Confusion about hierarchy, accidental duplication, no clear naming convention

---

### 2. INCONSISTENT BOOTSTRAP PATTERNS 🔴🔴
**Problem:** 3 different bootstrap patterns in use:

#### Pattern A: Modern (staff-accounts, staff-performance)
```php
require_once __DIR__ . '/../shared/bootstrap.php';  // ✅ Good
define('MODULE_PATH', __DIR__);
require_once MODULE_PATH . '/lib/Service.php';
```

#### Pattern B: Legacy (flagged-products)
```php
require_once ROOT_PATH . '/app.php';  // ❌ Doesn't use shared
require_once ROOT_PATH . '/assets/functions/config.php';
// Manually loads everything
```

#### Pattern C: Over-engineered (consignments - 207 lines!)
```php
require_once ROOT_PATH . '/app.php';
require_once ROOT_PATH . '/assets/functions/config.php';
require_once ROOT_PATH . '/bootstrap/app.php';  // Multiple bootstraps??
foreach (glob(ROOT_PATH . '/modules/shared/functions/*.php') as $sharedFunc) {
    require_once $sharedFunc;  // Indiscriminate loading
}
if (file_exists(ROOT_PATH . '/modules/base/lib/Session.php')) {
    require_once ROOT_PATH . '/modules/base/lib/Session.php';  // References non-existent base/
}
```

**Impact:** No consistency, impossible to onboard, impossible to maintain

---

### 3. TEMPLATE CONFUSION 🎨❌
**Problem:**
- Two templates: `shared/templates/base-layout.php` AND `/assets/template/base-layout.php`
- Different modules use different templates
- Template path finding with fallback loops (unnecessary complexity)
- Template requires OUTPUT BUFFERING (ob_start/ob_get_clean) - awkward pattern

**Current usage pattern (staff-accounts):**
```php
// Page starts here (line 1-100: logic)
$page_title = 'My Account';
ob_start();  // Start buffering
?>
<div>HTML content here</div>
<?php
$page_content = ob_get_clean();  // Capture buffer
require_once ROOT_PATH . '/assets/template/base-layout.php';  // Render at end
```

**Why this is bad:**
- Mixes logic and presentation
- Output buffering adds complexity
- Template path inconsistent (`/assets/` vs `/modules/shared/`)
- No clear separation of concerns

---

### 4. UNCLEAR INHERITANCE MODEL 🏗️❌
**Problem:**
- No documentation showing `base` → `module` hierarchy
- Some modules inherit from `shared/`, some don't
- `shared/bootstrap.php` tries to handle both CLI and web (overcomplicated)
- References to non-existent `/modules/base/` in code

**What developers see:**
```
modules/
├── shared/          ← Is this a module or infrastructure?
├── consignments/    ← Does this inherit? How?
├── staff-accounts/  ← Uses shared
└── flagged-products/ ← Doesn't use shared!
```

**What they should see:**
```
modules/
├── base/              ← CLEARLY the foundation (everyone inherits this)
│   ├── bootstrap.php  ← Database, sessions, auth
│   ├── templates/     ← CIS layout wrapper
│   └── lib/           ← Shared utilities
│
├── example-module/    ← TEMPLATE showing perfect pattern
│   ├── bootstrap.php  ← require base, define constants, load libs
│   ├── views/         ← Pages that use base template
│   ├── api/           ← JSON endpoints
│   └── lib/           ← Module business logic
│
└── staff-performance/ ← Real module following pattern
    └── (same structure)
```

---

### 5. OVER-ENGINEERING IN SHARED BOOTSTRAP 🤯
**Problem:** `shared/bootstrap.php` is 207 lines doing too much

**Current bloat:**
- CLI detection and separate initialization (should be separate file)
- .env file parsing (should use library)
- Multiple ROOT_PATH detection attempts with fallbacks
- Error handling setup mixed with basic setup
- Session initialization mixed with database setup
- Comment noise (good docs, wrong place)

**What it should be:**
```php
<?php
// base/bootstrap.php - The ONE file every module loads
// Provides: Database ($pdo), Session ($_SESSION), Auth (requireLogin())

require_once __DIR__ . '/init/database.php';  // Just $pdo
require_once __DIR__ . '/init/session.php';   // Just session_start()
require_once __DIR__ . '/init/auth.php';      // Just requireLogin()
```

**30 lines, not 207.**

---

### 6. DUPLICATE CODE EVERYWHERE 📋📋
**Problem:** Every module re-implements common patterns

**Examples:**
- Authentication checks in every view file
- StandardResponse JSON wrapper duplicated
- Database query patterns repeated
- Error handling inconsistent
- API response formats different per module

**Should be:** Base provides all common patterns, modules just use them

---

### 7. NO CLEAR SEPARATION OF CONCERNS 🎯❌
**Problem:** Mixed responsibilities

**Current mess:**
- Business logic in view files
- SQL queries in controllers
- Bootstrap files loading service classes (should be autoloaded)
- API endpoints mixing validation, business logic, and responses
- No clear MVC or layered architecture

**Example from staff-accounts/views/my-account.php:**
```php
// Line 1-30: Database queries (should be in Repository)
$stmt = $pdo->prepare("SELECT ...");

// Line 31-80: Business logic calculations (should be in Service)
$balance = calculate_complex_logic($data);

// Line 81-250: HTML presentation (should be in Template)
<div class="card">...</div>

// No separation!
```

---

### 8. TIGHT COUPLING TO LEGACY SYSTEM 🔗❌
**Problem:** Modules still dependent on old `/assets/` structure

**Dependencies found:**
- `ROOT_PATH . '/assets/template/html-header.php'` - Old header
- `ROOT_PATH . '/assets/functions/config.php'` - Old config
- `ROOT_PATH . '/assets/functions/VendAPI.php'` - Legacy APIs
- `ROOT_PATH . '/assets/services/xero-sdk/'` - External SDKs mixed in

**Impact:** Can't truly modularize, can't test in isolation, can't reuse elsewhere

---

### 9. MISSING DOCUMENTATION & EXAMPLES 📚❌
**Problem:**
- No "how to create a module" guide
- No example module showing perfect pattern
- bootstrap.php comments are good but buried in implementation
- No architecture diagram
- No conventions document

**Result:** Every developer invents their own pattern

---

### 10. INCONSISTENT DIRECTORY STRUCTURE 📁
**Current chaos:**

```
consignments/
├── bootstrap.php
├── config/
├── database/
├── functions/      ← Helpers
├── lib/            ← Classes
├── migrations/
├── purchase-orders/  ← Submodule?
├── shared/         ← Module-specific shared (confusing!)
├── stock-transfers/  ← Another submodule?
└── views/

staff-accounts/
├── bootstrap.php
├── _archive/       ← Good! Old code preserved
├── api/
├── cli/            ← CLI scripts
├── css/
├── database/
├── js/
├── _kb/            ← Knowledge base?
└── lib/

flagged-products/
├── api/
├── config/
├── cron/           ← Cron jobs
├── lib/
└── views/

flagged_products/   ← DUPLICATE MODULE!
├── _archive/
├── assets/         ← Own assets folder?
├── controllers/    ← MVC pattern?
├── functions/      ← vs lib/?
└── (massive)
```

**No consistency!**

---

## ✅ PROPOSED SOLUTION: THE BASE/MODULE PATTERN

### Core Principles
1. **Single Source of Truth** - `base/` provides everything common
2. **Clear Inheritance** - Every module extends `base/`
3. **Separation of Concerns** - Views, Logic, Data separate
4. **Lightweight** - Minimal boilerplate, maximum clarity
5. **Convention over Configuration** - Standard structure, no decisions
6. **Progressive Enhancement** - Start simple, add complexity only when needed

---

### NEW DIRECTORY STRUCTURE

```
modules/
│
├── base/                          ← THE FOUNDATION (renamed from shared)
│   ├── README.md                  ← "This is the foundation. All modules inherit this."
│   │
│   ├── bootstrap.php              ← 30 lines: require database, session, auth
│   │
│   ├── init/                      ← Initialization (clean separation)
│   │   ├── database.php           ← Creates $pdo (and nothing else)
│   │   ├── session.php            ← Starts session (and nothing else)
│   │   ├── auth.php               ← requireLogin() function
│   │   └── cli.php                ← CLI-specific initialization (separate!)
│   │
│   ├── templates/                 ← CIS HTML wrapper
│   │   ├── layout.php             ← Main template (simplified)
│   │   ├── header.php             ← Top nav
│   │   ├── sidebar.php            ← Side menu
│   │   └── footer.php             ← Footer
│   │
│   ├── lib/                       ← Shared utilities
│   │   ├── Database.php           ← Query builder (optional)
│   │   ├── Response.php           ← JSON API responses
│   │   ├── Validator.php          ← Input validation
│   │   └── ErrorHandler.php       ← Error logging
│   │
│   ├── api/                       ← Shared API utilities
│   │   └── StandardResponse.php   ← JSON envelope
│   │
│   └── docs/                      ← Architecture documentation
│       ├── ARCHITECTURE.md        ← This document
│       ├── CREATING_MODULES.md    ← Step-by-step guide
│       └── CONVENTIONS.md         ← Naming, structure rules
│
├── _example-module/               ← TEMPLATE FOR NEW MODULES
│   ├── README.md                  ← "Copy this folder to create new module"
│   │
│   ├── bootstrap.php              ← 10 lines: inherit base + define constants
│   │
│   ├── views/                     ← User-facing pages
│   │   ├── index.php              ← Example: list view
│   │   ├── detail.php             ← Example: detail view
│   │   └── form.php               ← Example: create/edit form
│   │
│   ├── api/                       ← JSON endpoints
│   │   ├── list.php               ← Example: GET /api/list
│   │   ├── get.php                ← Example: GET /api/get?id=123
│   │   ├── create.php             ← Example: POST /api/create
│   │   ├── update.php             ← Example: PUT /api/update
│   │   └── delete.php             ← Example: DELETE /api/delete
│   │
│   ├── lib/                       ← Business logic
│   │   ├── ExampleService.php     ← Business operations
│   │   └── ExampleRepository.php  ← Database operations
│   │
│   ├── database/                  ← Database files
│   │   ├── schema.sql             ← Table definitions
│   │   └── migrations/            ← Schema changes
│   │
│   ├── assets/                    ← Module-specific assets
│   │   ├── css/
│   │   │   └── module.css
│   │   └── js/
│   │       └── module.js
│   │
│   └── tests/                     ← Unit/integration tests
│       └── ExampleServiceTest.php
│
└── staff-performance/             ← REAL MODULE (follows example)
    ├── bootstrap.php              ← Inherits base
    ├── views/
    │   ├── google-reviews.php     ← Uses base template
    │   ├── dashboard.php
    │   └── leaderboard.php
    ├── api/
    │   ├── get-reviews.php
    │   └── award-bonus.php
    ├── lib/
    │   ├── GoogleReviewsService.php
    │   └── GamificationEngine.php
    └── database/
        └── schema.sql
```

---

## 📝 THE PERFECT MODULE PATTERN

### 1. base/bootstrap.php (THE FOUNDATION)

```php
<?php
/**
 * CIS Base Module Bootstrap
 * 
 * This file provides the foundation for ALL CIS modules.
 * Every module MUST load this first.
 * 
 * Provides:
 * - Database connection ($pdo)
 * - Session handling ($_SESSION)
 * - Authentication (requireLogin())
 * - Standard API responses
 * 
 * Usage in any module:
 *   require_once __DIR__ . '/../base/bootstrap.php';
 */

declare(strict_types=1);

// Define base paths
if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__, 2));  // modules/base -> public_html
}

if (!defined('BASE_MODULE_PATH')) {
    define('BASE_MODULE_PATH', __DIR__);
}

// Load core initialization
require_once __DIR__ . '/init/database.php';   // Provides: $pdo
require_once __DIR__ . '/init/session.php';    // Provides: $_SESSION
require_once __DIR__ . '/init/auth.php';       // Provides: requireLogin()
require_once __DIR__ . '/lib/Response.php';    // Provides: Response::json()

// Mark as loaded
define('CIS_BASE_LOADED', true);
```

**30 lines. That's it. No CLI detection, no .env parsing, no complexity.**

---

### 2. base/init/database.php (SINGLE RESPONSIBILITY)

```php
<?php
/**
 * Database Initialization
 * Creates $pdo connection and nothing else.
 */

if (!isset($GLOBALS['pdo'])) {
    require_once ROOT_PATH . '/config/database.php';  // Gets credentials
    
    $dsn = "mysql:host={$db_host};dbname={$db_name};charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ];
    
    $pdo = new PDO($dsn, $db_user, $db_pass, $options);
    $GLOBALS['pdo'] = $pdo;
}
```

**18 lines. One job: create $pdo.**

---

### 3. base/templates/layout.php (SIMPLE TEMPLATE)

```php
<?php
/**
 * Base CIS Layout Template
 * 
 * Usage:
 *   $page_title = 'My Page';
 *   $page_content = '<div>Content</div>';
 *   require_once __DIR__ . '/../../base/templates/layout.php';
 */

$page_title = $page_title ?? 'CIS';
$page_content = $page_content ?? '<div class="alert alert-warning">No content</div>';
?>
<!DOCTYPE html>
<html>
<head>
    <title><?php echo htmlspecialchars($page_title); ?> - CIS</title>
    <?php include ROOT_PATH . '/assets/template/html-header.php'; ?>
    <?php echo $page_head_extra ?? ''; ?>
</head>
<body class="app header-fixed sidebar-fixed sidebar-lg-show">
    <?php include ROOT_PATH . '/assets/template/header.php'; ?>
    
    <div class="app-body">
        <?php include ROOT_PATH . '/assets/template/sidemenu.php'; ?>
        
        <main class="main">
            <?php echo $page_content; ?>
        </main>
    </div>
    
    <?php include ROOT_PATH . '/assets/template/html-footer.php'; ?>
    <?php echo $page_scripts ?? ''; ?>
</body>
</html>
```

**No complex logic. Just a wrapper. 35 lines.**

---

### 4. Module Bootstrap (EVERY MODULE THE SAME)

```php
<?php
/**
 * Staff Performance Module Bootstrap
 * Inherits base and adds module-specific setup.
 */

// 1. Load base (provides database, session, auth)
require_once __DIR__ . '/../base/bootstrap.php';

// 2. Define module constants
define('STAFF_PERFORMANCE_PATH', __DIR__);
define('STAFF_PERFORMANCE_API', __DIR__ . '/api');
define('STAFF_PERFORMANCE_LIB', __DIR__ . '/lib');

// 3. Autoload module classes (only if needed)
spl_autoload_register(function($class) {
    $file = STAFF_PERFORMANCE_LIB . "/{$class}.php";
    if (file_exists($file)) require_once $file;
});

// Done. Module ready.
```

**15 lines. Every module identical pattern.**

---

### 5. View File (CLEAN SEPARATION)

```php
<?php
/**
 * Google Reviews Dashboard
 */

// Load module bootstrap (which loads base)
require_once __DIR__ . '/../bootstrap.php';

// Require authentication
requireLogin();

// Get data using service layer (no SQL here!)
$service = new GoogleReviewsService($pdo);
$reviews = $service->getRecentReviews(limit: 50);
$stats = $service->getMonthlyStats();

// Set template variables
$page_title = 'Google Reviews Dashboard';

// Build content (or use separate template file)
$page_content = "
<div class='container-fluid'>
    <div class='row'>
        <div class='col-md-3'>
            <div class='card'>
                <div class='card-body'>
                    <h4>{$stats['total_reviews']}</h4>
                    <p>Total Reviews</p>
                </div>
            </div>
        </div>
        <!-- More cards -->
    </div>
    
    <div class='card'>
        <div class='card-header'>Recent Reviews</div>
        <div class='card-body'>
            <table class='table'>
                <!-- Review data from \$reviews -->
            </table>
        </div>
    </div>
</div>
";

// Render using base template
require_once BASE_MODULE_PATH . '/templates/layout.php';
```

**Clean, simple, obvious. No output buffering needed!**

---

### 6. API Endpoint (STANDARD PATTERN)

```php
<?php
/**
 * API: Get Recent Reviews
 * GET /modules/staff-performance/api/get-reviews.php
 */

require_once __DIR__ . '/../bootstrap.php';

// Require authentication
requireLogin();

// Use standard response wrapper (from base)
try {
    // Get parameters
    $limit = (int)($_GET['limit'] ?? 50);
    
    // Use service layer
    $service = new GoogleReviewsService($pdo);
    $reviews = $service->getRecentReviews($limit);
    
    // Return success
    Response::success($reviews);
    
} catch (Exception $e) {
    Response::error($e->getMessage(), 500);
}
```

**20 lines. Standard pattern. Easy to understand.**

---

## 🎯 IMPLEMENTATION PLAN

### Phase 1: Rename & Clean Base (1 hour)
1. `mv modules/shared modules/base`
2. Update `base/bootstrap.php` - simplify to 30 lines
3. Create `base/init/` directory with separate files
4. Update `base/templates/layout.php` - simplify
5. Add `base/README.md` explaining it's the foundation
6. Add `base/docs/` with architecture documentation

### Phase 2: Create Example Module (30 min)
1. Create `modules/_example-module/` directory
2. Add fully documented example files
3. Include comments explaining every line
4. Add `README.md`: "Copy this folder to create new module"

### Phase 3: Fix Duplicates (15 min)
1. Delete `modules/flagged_products/` (keep `flagged-products/`)
2. Update any references

### Phase 4: Migrate Existing Modules (2-3 hours)
1. Update all `require_once __DIR__ . '/../shared/bootstrap.php';` 
   → `require_once __DIR__ . '/../base/bootstrap.php';`
2. Simplify each module's bootstrap.php to match pattern
3. Update template paths
4. Remove duplicate code

### Phase 5: Documentation (1 hour)
1. Create `base/docs/CREATING_MODULES.md`
2. Create `base/docs/CONVENTIONS.md`
3. Update each module's README
4. Add architecture diagram

---

## 📋 CONVENTIONS DOCUMENT (DRAFT)

### Module Naming
- **Format:** `kebab-case` (e.g., `staff-performance`, `google-reviews`)
- **NO underscores:** `staff_performance` ❌
- **NO camelCase:** `staffPerformance` ❌

### Directory Structure (REQUIRED)
```
module-name/
├── bootstrap.php       ← REQUIRED: loads base + module setup
├── README.md           ← REQUIRED: what this module does
├── views/              ← User-facing pages
├── api/                ← JSON endpoints
├── lib/                ← Business logic classes
├── database/           ← Schema and migrations
└── assets/             ← Module-specific CSS/JS
```

### File Naming
- **Views:** `dashboard.php`, `detail-view.php` (descriptive)
- **APIs:** `get-data.php`, `create-item.php` (verb-noun)
- **Classes:** `PascalCase.php` (e.g., `GoogleReviewsService.php`)

### Bootstrap Pattern (EXACT)
```php
<?php
require_once __DIR__ . '/../base/bootstrap.php';
define('MODULE_NAME_PATH', __DIR__);
// Optional: autoloader
```

### View Pattern (EXACT)
```php
<?php
require_once __DIR__ . '/../bootstrap.php';
requireLogin();

// Get data using services
// Set $page_title and $page_content
require_once BASE_MODULE_PATH . '/templates/layout.php';
```

### API Pattern (EXACT)
```php
<?php
require_once __DIR__ . '/../bootstrap.php';
requireLogin();

try {
    // Process request
    // Use services
    Response::success($data);
} catch (Exception $e) {
    Response::error($e->getMessage());
}
```

---

## ✅ BENEFITS OF NEW ARCHITECTURE

### For Developers
1. **10-minute onboarding** - Copy example module, understand instantly
2. **Zero boilerplate decisions** - Every module identical structure
3. **No tribal knowledge** - Everything documented and obvious
4. **Easy testing** - Clear separation, mockable services
5. **No duplicate code** - Base provides all common patterns

### For System
1. **Consistent** - All modules work the same way
2. **Maintainable** - Change base, all modules benefit
3. **Lightweight** - 30-line bootstrap vs 207 lines
4. **Portable** - Modules can be extracted and reused
5. **Testable** - Clear boundaries, no tight coupling

### For Codebase
1. **Clean inheritance** - base → module (obvious)
2. **Separation of concerns** - Views, logic, data separate
3. **No magic** - Every line explainable
4. **Progressive** - Start simple, add complexity only when needed
5. **Self-documenting** - Structure tells the story

---

## 🎬 READY TO IMPLEMENT?

This refactoring will:
- ✅ Make module creation take 10 minutes instead of hours
- ✅ Eliminate confusion about inheritance
- ✅ Remove 70% of boilerplate code
- ✅ Make onboarding instant
- ✅ Enable true separation of concerns
- ✅ Create reusable, testable modules

---

## 🎨 COMPREHENSIVE BASE MODEL STANDARDS

### THEMES & UI STANDARDS

#### Color Palette (CIS Brand)
```css
/* base/assets/css/variables.css */
:root {
    /* Primary Colors */
    --cis-primary: #4dbd74;        /* Success green */
    --cis-secondary: #20a8d8;      /* Info blue */
    --cis-danger: #f86c6b;         /* Error red */
    --cis-warning: #ffc107;        /* Warning yellow */
    --cis-info: #63c2de;           /* Info cyan */
    
    /* Neutral Colors */
    --cis-dark: #23282c;           /* Dark grey */
    --cis-light: #f0f3f5;          /* Light grey */
    --cis-white: #ffffff;
    --cis-black: #000000;
    
    /* Text Colors */
    --cis-text-primary: #23282c;
    --cis-text-secondary: #73818f;
    --cis-text-muted: #c8ced3;
    
    /* Background Colors */
    --cis-bg-body: #e4e5e6;
    --cis-bg-card: #ffffff;
    --cis-bg-sidebar: #2f353a;
    
    /* Border & Shadow */
    --cis-border: #c8ced3;
    --cis-shadow: 0 0 10px rgba(0,0,0,0.1);
    
    /* Spacing Scale (8px base) */
    --spacing-xs: 0.25rem;  /* 4px */
    --spacing-sm: 0.5rem;   /* 8px */
    --spacing-md: 1rem;     /* 16px */
    --spacing-lg: 1.5rem;   /* 24px */
    --spacing-xl: 2rem;     /* 32px */
    --spacing-xxl: 3rem;    /* 48px */
    
    /* Typography */
    --font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
    --font-size-base: 0.875rem;  /* 14px */
    --font-size-sm: 0.75rem;     /* 12px */
    --font-size-lg: 1rem;        /* 16px */
    --font-size-xl: 1.25rem;     /* 20px */
    --line-height: 1.5;
    
    /* Border Radius */
    --border-radius-sm: 0.2rem;
    --border-radius: 0.25rem;
    --border-radius-lg: 0.3rem;
    
    /* Transitions */
    --transition-fast: 150ms ease;
    --transition-base: 300ms ease;
    --transition-slow: 500ms ease;
}
```

#### Component Standards
```css
/* base/assets/css/components.css */

/* Cards - ALWAYS use this structure */
.cis-card {
    background: var(--cis-bg-card);
    border: 1px solid var(--cis-border);
    border-radius: var(--border-radius);
    box-shadow: var(--cis-shadow);
    margin-bottom: var(--spacing-md);
}

.cis-card-header {
    padding: var(--spacing-md);
    border-bottom: 1px solid var(--cis-border);
    font-weight: 600;
}

.cis-card-body {
    padding: var(--spacing-md);
}

/* Buttons - Standard sizes */
.cis-btn {
    padding: var(--spacing-sm) var(--spacing-md);
    border-radius: var(--border-radius);
    font-size: var(--font-size-base);
    transition: all var(--transition-fast);
}

.cis-btn-sm { padding: var(--spacing-xs) var(--spacing-sm); }
.cis-btn-lg { padding: var(--spacing-md) var(--spacing-lg); }

/* Tables - Consistent formatting */
.cis-table {
    width: 100%;
    border-collapse: collapse;
}

.cis-table th {
    background: var(--cis-light);
    padding: var(--spacing-sm);
    text-align: left;
    font-weight: 600;
}

.cis-table td {
    padding: var(--spacing-sm);
    border-top: 1px solid var(--cis-border);
}

/* Forms - Standard spacing */
.cis-form-group {
    margin-bottom: var(--spacing-md);
}

.cis-form-label {
    display: block;
    margin-bottom: var(--spacing-xs);
    font-weight: 500;
}

.cis-form-control {
    width: 100%;
    padding: var(--spacing-sm);
    border: 1px solid var(--cis-border);
    border-radius: var(--border-radius);
}
```

#### Layout Constraints (ENFORCED)
```php
// base/lib/Layout.php
class Layout {
    // ONLY these container types allowed
    const CONTAINER_FLUID = 'container-fluid';  // Full width
    const CONTAINER = 'container';              // Fixed 1200px max
    const CONTAINER_SM = 'container-sm';        // 540px max
    
    // Grid system (Bootstrap-compatible)
    const GRID_COLUMNS = 12;
    const BREAKPOINTS = [
        'xs' => 0,      // < 576px
        'sm' => 576,    // ≥ 576px
        'md' => 768,    // ≥ 768px
        'lg' => 992,    // ≥ 992px
        'xl' => 1200    // ≥ 1200px
    ];
    
    // Modules can ONLY inject content into <main class="main">
    // They CANNOT modify header, sidebar, or footer
}
```

---

### 📊 DATABASE STANDARDS

#### Connection Management
```php
// base/init/database.php
class Database {
    private static $instance = null;
    private $pdo;
    
    // Singleton pattern - ONE connection globally
    public static function getInstance(): PDO {
        if (self::$instance === null) {
            $host = getenv('DB_HOST') ?: '127.0.0.1';
            $name = getenv('DB_NAME');
            $user = getenv('DB_USER');
            $pass = getenv('DB_PASS');
            
            $dsn = "mysql:host={$host};dbname={$name};charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
            ];
            
            self::$instance = new PDO($dsn, $user, $pass, $options);
        }
        
        return self::$instance;
    }
}

// Global $pdo for backwards compatibility
$GLOBALS['pdo'] = Database::getInstance();
$pdo = $GLOBALS['pdo'];
```

#### Query Standards (ENFORCED)
```php
// base/lib/QueryValidator.php
class QueryValidator {
    // ❌ FORBIDDEN patterns
    private const FORBIDDEN = [
        '/SELECT \* FROM/',                    // Must specify columns
        '/WHERE .+ = [\'"][^\'"]+[\'"]/',    // Must use placeholders
        '/\$_GET|\$_POST|\$_REQUEST/',        // Must sanitize input
        '/DROP TABLE|TRUNCATE|DELETE FROM users/', // Dangerous operations
    ];
    
    // ✅ REQUIRED patterns
    private const REQUIRED = [
        'prepared_statement' => '/prepare\(/',
        'execute' => '/execute\(\[/',
        'parameter_binding' => '/\?|\:[a-z_]+/'
    ];
    
    public static function validate(string $query): bool {
        // Check for forbidden patterns
        foreach (self::FORBIDDEN as $pattern) {
            if (preg_match($pattern, $query)) {
                throw new SecurityException("Forbidden query pattern detected");
            }
        }
        
        // All queries MUST use prepared statements
        return true;
    }
}
```

#### Table Naming Convention
```sql
-- STANDARD: module_name_entity
-- Examples:
staff_performance_reviews          ✅
staff_performance_achievements     ✅
google_reviews_gamification        ✅

-- FORBIDDEN:
staffPerformanceReviews            ❌ (camelCase)
staff-performance-reviews          ❌ (hyphens)
reviews                            ❌ (too generic, no module prefix)
```

#### Column Standards
```sql
-- PRIMARY KEYS: Always `id` (INT UNSIGNED AUTO_INCREMENT)
CREATE TABLE example (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    -- ...
);

-- FOREIGN KEYS: Always singular_table_id
user_id INT UNSIGNED NOT NULL,        ✅
outlet_id VARCHAR(36) NOT NULL,       ✅ (UUID)
staff_member_id INT UNSIGNED,         ❌ (use staff_id)

-- TIMESTAMPS: Always these exact names
created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
deleted_at TIMESTAMP NULL,  -- Soft deletes

-- MONEY: Always DECIMAL(10,2)
amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
balance DECIMAL(10,2) NOT NULL DEFAULT 0.00,

-- STATUS: Always ENUM or TINYINT
status ENUM('pending','approved','rejected') DEFAULT 'pending',
is_active TINYINT(1) DEFAULT 1,  -- Boolean

-- JSON: Always validated before insert
metadata JSON NULL,
settings JSON NULL,
```

#### Migration Standards
```php
// base/lib/Migration.php
abstract class Migration {
    protected $pdo;
    
    // Every migration MUST implement these
    abstract public function up(): void;    // Apply changes
    abstract public function down(): void;  // Rollback changes
    
    // Automatic tracking
    private function recordMigration(string $name): void {
        $this->pdo->prepare("
            INSERT INTO migrations (name, executed_at) 
            VALUES (?, NOW())
        ")->execute([$name]);
    }
}

// Usage in module:
// database/migrations/001_create_reviews_table.php
class CreateReviewsTable extends Migration {
    public function up(): void {
        $this->pdo->exec("
            CREATE TABLE staff_performance_reviews (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                -- ...
            )
        ");
    }
    
    public function down(): void {
        $this->pdo->exec("DROP TABLE IF EXISTS staff_performance_reviews");
    }
}
```

---

### 🔐 SESSION & AUTHENTICATION STANDARDS

#### Session Configuration (ENFORCED)
```php
// base/init/session.php
class SessionManager {
    public static function initialize(): void {
        // Security settings (REQUIRED)
        ini_set('session.cookie_httponly', '1');  // Prevent XSS
        ini_set('session.cookie_secure', '1');    // HTTPS only
        ini_set('session.use_strict_mode', '1');  // Prevent session fixation
        ini_set('session.cookie_samesite', 'Lax'); // CSRF protection
        
        // Session lifetime
        ini_set('session.gc_maxlifetime', '3600'); // 1 hour
        ini_set('session.cookie_lifetime', '0');   // Browser session
        
        // Session name (custom, harder to fingerprint)
        session_name('CIS_SESSION');
        
        // Start session
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Regenerate ID periodically (prevent hijacking)
        if (!isset($_SESSION['last_regenerate'])) {
            $_SESSION['last_regenerate'] = time();
        }
        
        if (time() - $_SESSION['last_regenerate'] > 300) { // 5 minutes
            session_regenerate_id(true);
            $_SESSION['last_regenerate'] = time();
        }
    }
    
    public static function destroy(): void {
        $_SESSION = [];
        
        if (isset($_COOKIE[session_name()])) {
            setcookie(session_name(), '', time() - 3600, '/');
        }
        
        session_destroy();
    }
}
```

#### Authentication Requirements (STRICT)
```php
// base/lib/Auth.php
class Auth {
    // REQUIRED: Every protected page must call this
    public static function requireLogin(): void {
        if (!self::isLoggedIn()) {
            header('Location: /login.php?return=' . urlencode($_SERVER['REQUEST_URI']));
            exit;
        }
        
        // Log access
        self::logAccess();
    }
    
    // Check if user logged in
    public static function isLoggedIn(): bool {
        return isset($_SESSION['userID']) && 
               isset($_SESSION['auth_token']) &&
               self::validateAuthToken();
    }
    
    // Validate session hasn't been hijacked
    private static function validateAuthToken(): bool {
        $expected = hash('sha256', 
            $_SESSION['userID'] . 
            $_SERVER['HTTP_USER_AGENT'] . 
            getenv('APP_SECRET')
        );
        
        return isset($_SESSION['auth_token']) && 
               hash_equals($_SESSION['auth_token'], $expected);
    }
    
    // Permission checking (role-based)
    public static function requirePermission(string $permission): void {
        if (!self::hasPermission($permission)) {
            http_response_code(403);
            die('Access denied: Missing permission: ' . $permission);
        }
    }
    
    public static function hasPermission(string $permission): bool {
        if (!self::isLoggedIn()) return false;
        
        // Check user permissions from database
        global $pdo;
        $stmt = $pdo->prepare("
            SELECT 1 FROM user_permissions 
            WHERE user_id = ? AND permission = ? AND is_active = 1
        ");
        $stmt->execute([$_SESSION['userID'], $permission]);
        
        return $stmt->fetchColumn() !== false;
    }
}
```

#### Required Session Variables (STANDARD)
```php
// After successful login, MUST set these:
$_SESSION['userID']      = $user['id'];           // REQUIRED
$_SESSION['name']        = $user['name'];         // REQUIRED
$_SESSION['email']       = $user['email'];        // REQUIRED
$_SESSION['role']        = $user['role'];         // REQUIRED
$_SESSION['outlet_id']   = $user['outlet_id'];    // If applicable
$_SESSION['permissions'] = $user['permissions'];  // JSON array
$_SESSION['login_time']  = time();                // REQUIRED
$_SESSION['last_activity'] = time();              // Updated on each request
$_SESSION['auth_token']  = hash('sha256', ...);   // Security token

// ❌ NEVER store in session:
// - Passwords (even hashed)
// - API keys
// - Credit card numbers
// - Full customer records (store ID only)
```

---

### 🛡️ SECURITY STANDARDS (ENFORCED)

#### Input Validation (MANDATORY)
```php
// base/lib/Validator.php
class Validator {
    // All user input MUST pass through validation
    public static function validateRequest(array $rules): array {
        $validated = [];
        
        foreach ($rules as $field => $rule) {
            $value = $_POST[$field] ?? $_GET[$field] ?? null;
            
            // Required fields
            if (isset($rule['required']) && $rule['required'] && empty($value)) {
                throw new ValidationException("Field {$field} is required");
            }
            
            // Type validation
            if (isset($rule['type'])) {
                $value = self::validateType($value, $rule['type']);
            }
            
            // Custom validation
            if (isset($rule['validate'])) {
                $value = $rule['validate']($value);
            }
            
            $validated[$field] = $value;
        }
        
        return $validated;
    }
    
    private static function validateType($value, string $type) {
        return match($type) {
            'int' => filter_var($value, FILTER_VALIDATE_INT),
            'float' => filter_var($value, FILTER_VALIDATE_FLOAT),
            'email' => filter_var($value, FILTER_VALIDATE_EMAIL),
            'url' => filter_var($value, FILTER_VALIDATE_URL),
            'string' => htmlspecialchars(strip_tags($value), ENT_QUOTES, 'UTF-8'),
            'json' => json_decode($value, true),
            default => throw new ValidationException("Unknown type: {$type}")
        };
    }
}

// Usage (REQUIRED in all API endpoints):
try {
    $data = Validator::validateRequest([
        'user_id' => ['required' => true, 'type' => 'int'],
        'email' => ['required' => true, 'type' => 'email'],
        'amount' => ['required' => true, 'type' => 'float'],
    ]);
} catch (ValidationException $e) {
    Response::error($e->getMessage(), 400);
}
```

#### CSRF Protection (AUTOMATIC)
```php
// base/lib/CSRF.php
class CSRF {
    // Generate token (call at page render)
    public static function generateToken(): string {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
    
    // Validate token (automatic in POST requests)
    public static function validateToken(): bool {
        $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        
        if (!$token || !isset($_SESSION['csrf_token'])) {
            return false;
        }
        
        return hash_equals($_SESSION['csrf_token'], $token);
    }
    
    // HTML helper
    public static function field(): string {
        return '<input type="hidden" name="csrf_token" value="' . 
               htmlspecialchars(self::generateToken()) . '">';
    }
}

// AUTO-CHECK on all POST requests (in base/bootstrap.php)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !CSRF::validateToken()) {
    http_response_code(403);
    die('CSRF token validation failed');
}
```

#### Rate Limiting (AUTOMATIC)
```php
// base/lib/RateLimit.php
class RateLimit {
    // Automatic rate limiting on API endpoints
    public static function check(string $endpoint, int $limit = 60): void {
        $key = 'ratelimit:' . $endpoint . ':' . self::getClientIdentifier();
        
        global $pdo;
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM rate_limit_log 
            WHERE `key` = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 MINUTE)
        ");
        $stmt->execute([$key]);
        $count = $stmt->fetchColumn();
        
        if ($count >= $limit) {
            http_response_code(429);
            header('Retry-After: 60');
            die(json_encode([
                'success' => false,
                'error' => 'Rate limit exceeded. Try again in 60 seconds.'
            ]));
        }
        
        // Log this request
        $pdo->prepare("INSERT INTO rate_limit_log (`key`, created_at) VALUES (?, NOW())")
            ->execute([$key]);
    }
    
    private static function getClientIdentifier(): string {
        return hash('sha256', 
            ($_SESSION['userID'] ?? $_SERVER['REMOTE_ADDR']) . 
            $_SERVER['HTTP_USER_AGENT']
        );
    }
}
```

---

### 📦 API RESPONSE STANDARDS (STRICT)

#### Standard Envelope (REQUIRED)
```php
// base/lib/Response.php
class Response {
    // SUCCESS response (200 OK)
    public static function success($data = null, int $code = 200): void {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        
        echo json_encode([
            'success' => true,
            'data' => $data,
            'timestamp' => date('Y-m-d H:i:s'),
            'request_id' => self::getRequestId()
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        
        exit;
    }
    
    // ERROR response (4xx or 5xx)
    public static function error(string $message, int $code = 400, array $details = []): void {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        
        echo json_encode([
            'success' => false,
            'error' => [
                'message' => $message,
                'code' => $code,
                'details' => $details
            ],
            'timestamp' => date('Y-m-d H:i:s'),
            'request_id' => self::getRequestId()
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        
        // Log error
        error_log("[API Error {$code}] {$message} - Request ID: " . self::getRequestId());
        
        exit;
    }
    
    private static function getRequestId(): string {
        if (!isset($_SERVER['HTTP_X_REQUEST_ID'])) {
            $_SERVER['HTTP_X_REQUEST_ID'] = uniqid('req_', true);
        }
        return $_SERVER['HTTP_X_REQUEST_ID'];
    }
}

// ❌ FORBIDDEN: Direct echo/print in API files
// ✅ REQUIRED: Always use Response::success() or Response::error()
```

---

### 🔍 LOGGING STANDARDS

#### Standard Logger (REQUIRED)
```php
// base/lib/Logger.php
class Logger {
    const DEBUG = 'DEBUG';
    const INFO = 'INFO';
    const WARNING = 'WARNING';
    const ERROR = 'ERROR';
    const CRITICAL = 'CRITICAL';
    
    public static function log(string $level, string $message, array $context = []): void {
        global $pdo;
        
        // Format message with context
        $formatted = $message;
        if (!empty($context)) {
            $formatted .= ' | Context: ' . json_encode($context);
        }
        
        // Add request metadata
        $metadata = [
            'user_id' => $_SESSION['userID'] ?? null,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
            'url' => $_SERVER['REQUEST_URI'] ?? null,
            'method' => $_SERVER['REQUEST_METHOD'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
        ];
        
        // Write to database
        $stmt = $pdo->prepare("
            INSERT INTO system_logs (level, message, context, metadata, created_at)
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $level,
            $message,
            json_encode($context),
            json_encode($metadata)
        ]);
        
        // Also write to file for critical errors
        if (in_array($level, [self::ERROR, self::CRITICAL])) {
            error_log("[{$level}] {$formatted}");
        }
    }
    
    // Convenience methods
    public static function info(string $message, array $context = []): void {
        self::log(self::INFO, $message, $context);
    }
    
    public static function error(string $message, array $context = []): void {
        self::log(self::ERROR, $message, $context);
    }
}

// Usage (REQUIRED for significant actions):
Logger::info('Staff bonus awarded', [
    'staff_id' => 127,
    'amount' => 10.00,
    'reason' => 'Google review mention'
]);
```

---

### ⚙️ ENFORCEMENT MECHANISMS

#### Pre-commit Hooks (GIT)
```bash
# .git/hooks/pre-commit
#!/bin/bash

echo "🔍 Running CIS code standards validation..."

# Check for forbidden patterns
if git diff --cached --name-only | grep -E '\.php$' | xargs grep -n "SELECT \* FROM" 2>/dev/null; then
    echo "❌ ERROR: SELECT * is forbidden. Specify columns explicitly."
    exit 1
fi

if git diff --cached --name-only | grep -E '\.php$' | xargs grep -n "\$_GET\[" 2>/dev/null; then
    echo "❌ ERROR: Direct \$_GET access is forbidden. Use Validator::validateRequest()"
    exit 1
fi

# Check bootstrap is loaded in module files
if git diff --cached --name-only | grep -E 'modules/.*/views/.*\.php$' | xargs grep -L "require_once.*bootstrap\.php" 2>/dev/null; then
    echo "❌ ERROR: View files must load bootstrap.php"
    exit 1
fi

# Check for CSRF tokens in forms
if git diff --cached --name-only | grep -E '\.php$' | xargs grep -l "<form" | xargs grep -L "csrf_token" 2>/dev/null; then
    echo "⚠️  WARNING: Forms should include CSRF tokens"
fi

echo "✅ Code standards validation passed"
```

#### Runtime Validation (base/lib/Enforcer.php)
```php
class Enforcer {
    // Check if module follows base model
    public static function validateModuleStructure(string $modulePath): array {
        $errors = [];
        
        // REQUIRED files
        if (!file_exists("$modulePath/bootstrap.php")) {
            $errors[] = "Missing bootstrap.php";
        }
        
        if (!file_exists("$modulePath/README.md")) {
            $errors[] = "Missing README.md";
        }
        
        // Check bootstrap loads base
        $bootstrap = file_get_contents("$modulePath/bootstrap.php");
        if (!str_contains($bootstrap, '/../base/bootstrap.php')) {
            $errors[] = "bootstrap.php doesn't load base/bootstrap.php";
        }
        
        // Check for forbidden patterns
        $phpFiles = glob("$modulePath/**/*.php");
        foreach ($phpFiles as $file) {
            $content = file_get_contents($file);
            
            if (preg_match('/\$_GET\[|\$_POST\[|\$_REQUEST\[/', $content)) {
                $errors[] = basename($file) . ": Direct superglobal access (use Validator)";
            }
            
            if (preg_match('/SELECT \* FROM/', $content)) {
                $errors[] = basename($file) . ": SELECT * is forbidden";
            }
        }
        
        return $errors;
    }
}
```

#### Module Validation Command
```bash
# scripts/validate-module.php
<?php
require_once __DIR__ . '/../modules/base/bootstrap.php';
require_once __DIR__ . '/../modules/base/lib/Enforcer.php';

$modulePath = $argv[1] ?? null;

if (!$modulePath) {
    die("Usage: php validate-module.php <module-path>\n");
}

$errors = Enforcer::validateModuleStructure($modulePath);

if (empty($errors)) {
    echo "✅ Module validation passed!\n";
    exit(0);
} else {
    echo "❌ Module validation failed:\n";
    foreach ($errors as $error) {
        echo "  - $error\n";
    }
    exit(1);
}
```

---

## 📚 DOCUMENTATION REQUIREMENTS

Every module MUST include:

### 1. README.md (REQUIRED)
```markdown
# Module Name

## Purpose
Brief description of what this module does.

## Features
- Feature 1
- Feature 2

## Database Tables
- `module_table_1` - Description
- `module_table_2` - Description

## API Endpoints
- `GET /api/endpoint` - Description
- `POST /api/endpoint` - Description

## Views
- `/views/page.php` - Description

## Dependencies
- base/ (required)
- External library (if any)

## Setup
1. Run database migration: `php database/migrations/001_setup.php`
2. Configure settings in admin panel

## Testing
```bash
php tests/ModuleTest.php
```

## Permissions Required
- `module.view` - View module pages
- `module.edit` - Edit module data
```

### 2. API Documentation (REQUIRED for modules with APIs)
```markdown
# API Documentation

## Authentication
All endpoints require authentication via session.

## Rate Limiting
60 requests per minute per user.

## Endpoints

### GET /api/list
Retrieves list of items.

**Parameters:**
- `limit` (int, optional) - Max results (default: 50)
- `offset` (int, optional) - Pagination offset

**Response:**
```json
{
  "success": true,
  "data": [
    {"id": 1, "name": "Item"}
  ]
}
```

**Errors:**
- 401: Unauthorized
- 429: Rate limit exceeded
```

---

## 🎯 SUMMARY OF STANDARDS

### ✅ ENFORCED by Base:
1. **Database:** Singleton connection, prepared statements only, standard naming
2. **Sessions:** Secure config, regeneration, CSRF protection automatic
3. **Auth:** requireLogin() on all protected pages, permission checks
4. **APIs:** Standard JSON envelope, rate limiting, validation required
5. **Security:** Input validation, XSS prevention, SQL injection prevention
6. **Logging:** Structured logs with context and metadata
7. **Themes:** CSS variables, component library, layout constraints

### ✅ VALIDATED by Tools:
1. **Pre-commit hooks:** Check for forbidden patterns
2. **Runtime validation:** Module structure validation
3. **CI/CD checks:** Automated testing of standards compliance

### ✅ DOCUMENTED:
1. **Base README:** Architecture overview
2. **Example module:** Fully commented template
3. **Conventions doc:** All standards in one place
4. **API docs:** Endpoint specifications

---

**Do you want me to proceed with Phase 1: Rename & Clean Base?**
