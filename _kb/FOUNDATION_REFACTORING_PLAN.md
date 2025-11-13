# Consignments Module - Foundation Refactoring Plan

**Status**: READY FOR APPROVAL
**Created**: 2025-01-21
**Priority**: 🔴 **CRITICAL** - Must complete before UI improvements

---

## 🎯 Executive Summary

**Current State**: Consignments module operates as standalone system with inline authentication, no middleware pipeline, hardcoded DB connections, and no base template inheritance.

**Target State**: Professional enterprise architecture inheriting from base module with middleware pipeline, centralized authentication, database abstraction, session management, and theme inheritance.

**Impact**:
- ✅ Security: Centralized auth/CSRF via SecurityMiddleware
- ✅ Maintainability: DRY principle - no duplicate session/auth code
- ✅ Performance: Connection pooling via base Database class
- ✅ UI/UX: Consistent theme across all pages via base templates
- ✅ Standards: PSR-12 compliant, follows CIS best practices

---

## 📊 Current State Analysis

### Base Module Architecture (Available)

**Core Services** (`/modules/base/`):
```
✅ Database.php          - PDO/MySQLi abstraction with connection pooling
✅ Session.php           - Secure session management (integrates with app.php)
✅ SecurityMiddleware.php - CSRF tokens, rate limiting stubs
✅ Router.php            - GET routing via ?endpoint=...
✅ Response.php          - JSON/redirect helpers
✅ Validator.php         - Input validation
✅ Logger.php            - Logging interface
✅ ErrorHandler.php      - Error/exception management
✅ bootstrap.php         - Auto-initialization pipeline
```

**Template System** (`/modules/base/_templates/`):
```
✅ layouts/
   - dashboard.php   - Full admin dashboard with sidebar
   - table.php       - Data table view
   - card.php        - Card-based layout
   - blank.php       - Minimal layout
   - split.php       - Two-column split

✅ components/
   - header.php      - Top navigation bar
   - footer.php      - Footer with system info
   - sidebar.php     - Left navigation menu
   - breadcrumbs.php - Breadcrumb navigation

✅ themes/
   - cis-classic/    - Default CIS theme with variables
```

**API Extension Pattern**:
```php
// BaseAPI provides:
- Request lifecycle management (CSRF, auth, rate limit, routing)
- Standard JSON envelope (success/error responses)
- Logging with correlation IDs
- Input validation helpers
- Error handling

// Usage:
class ConsignmentsAPI extends \CIS\Base\Lib\BaseAPI {
    protected function handleGetTransfer($data) {
        $this->validateRequired($data, ['id']);
        // Business logic here
        return $this->success($result);
    }
}
```

### Consignments Module Current State (Problems)

**backend.php** (2,219 lines) - MAJOR ISSUES:
```php
❌ Inline authentication:
   if (!function_exists('isLoggedIn') || !isLoggedIn()) {
       // Manual 401 response
   }

❌ Direct DB connections:
   function db(): mysqli {
       $conn = new mysqli($host, $user, $pass, $name);
       // No pooling, no abstraction
   }

❌ Manual session start:
   session_start();

❌ Custom CSRF (non-standard):
   $_SESSION['tt_csrf']

❌ Hardcoded error handling:
   function json_error_handler() { ... }

❌ No middleware pipeline

❌ No template inheritance

❌ 2,219 lines of mixed concerns
```

**All Frontend Pages** - DUPLICATE ISSUES:
```php
❌ Each page loads app.php separately
❌ Each page checks isLoggedIn() manually
❌ Each page has hardcoded HTML structure
❌ No theme inheritance
❌ Inconsistent styling
❌ Duplicate session/auth code across 30+ files
```

**bootstrap.php** - PARTIALLY CORRECT:
```php
✅ Loads base/bootstrap.php (good!)
✅ PSR-4 autoloader setup
❌ Loads old shared functions (deprecated)
❌ Doesn't enforce base template usage
❌ No middleware registration
```

---

## 🏗️ Refactoring Architecture

### Phase 1: Foundation Classes (Base Inheritance)

**1.1 Create ConsignmentsAPI (extends BaseAPI)**

File: `/modules/consignments/lib/ConsignmentsAPI.php`

```php
<?php
declare(strict_types=1);

namespace Consignments\Lib;

use CIS\Base\Lib\BaseAPI;
use CIS\Base\Database;
use Consignments\Services\TransferService;
use Consignments\Services\ConsignmentService;

/**
 * Consignments API Controller
 *
 * All transfer/consignment API endpoints extend this.
 * Provides pre-configured database, services, and business logic helpers.
 */
class ConsignmentsAPI extends BaseAPI
{
    protected TransferService $transferService;
    protected ConsignmentService $consignmentService;

    public function __construct(array $config = [])
    {
        // Configure API requirements
        $config = array_merge([
            'require_auth' => true,
            'allowed_methods' => ['POST', 'GET'],
            'rate_limit' => 120, // 120 requests/minute
        ], $config);

        parent::__construct($config);

        // Initialize services with base Database
        $pdo = Database::pdo();
        $this->transferService = new TransferService($pdo);
        $this->consignmentService = new ConsignmentService($pdo);
    }

    /**
     * Helper: Get current user ID from session
     */
    protected function getUserId(): int
    {
        return (int)($_SESSION['user_id'] ?? $_SESSION['userID'] ?? 0);
    }

    /**
     * Helper: Validate transfer ownership/access
     */
    protected function validateTransferAccess(int $transferId): void
    {
        $transfer = $this->transferService->getById($transferId);
        if (!$transfer) {
            $this->error('Transfer not found', 'NOT_FOUND', 404);
        }
        // Add permission checks here if needed
    }
}
```

**1.2 Create ConsignmentsController (Page Controller Base)**

File: `/modules/consignments/lib/ConsignmentsController.php`

```php
<?php
declare(strict_types=1);

namespace Consignments\Lib;

use CIS\Base\Database;
use CIS\Base\Session;
use CIS\Base\SecurityMiddleware;
use CIS\Base\Response;

/**
 * Consignments Page Controller Base
 *
 * All frontend pages extend this for consistent auth, session, templates.
 */
abstract class ConsignmentsController
{
    protected \PDO $db;
    protected array $viewData = [];

    public function __construct()
    {
        // Ensure base is initialized
        Session::init();
        SecurityMiddleware::init();

        // Check authentication
        $this->requireAuth();

        // Setup database
        $this->db = Database::pdo();

        // Set default view data
        $this->viewData = [
            'pageTitle' => 'Consignments',
            'moduleName' => 'consignments',
            'currentUser' => $this->getCurrentUser(),
            'csrfToken' => SecurityMiddleware::generateToken(),
        ];
    }

    /**
     * Require authentication or redirect
     */
    protected function requireAuth(): void
    {
        if (!$this->isAuthenticated()) {
            Response::redirect('/login.php');
        }
    }

    /**
     * Check if user is authenticated
     */
    protected function isAuthenticated(): bool
    {
        return !empty($_SESSION['user_id']) || !empty($_SESSION['userID']);
    }

    /**
     * Get current user info
     */
    protected function getCurrentUser(): array
    {
        $userId = $_SESSION['user_id'] ?? $_SESSION['userID'] ?? 0;
        if (!$userId) return [];

        $stmt = $this->db->prepare('SELECT user_id, username, email FROM users WHERE user_id = ?');
        $stmt->execute([$userId]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Render page using base template
     */
    protected function render(string $layout, string $contentView, array $data = []): void
    {
        // Merge data with defaults
        $this->viewData = array_merge($this->viewData, $data);

        // Extract variables for template
        extract($this->viewData);

        // Load content view first (capture output)
        $contentFile = __DIR__ . '/../views/' . $contentView . '.php';
        if (!file_exists($contentFile)) {
            throw new \RuntimeException("View not found: {$contentView}");
        }

        ob_start();
        require $contentFile;
        $pageContent = ob_get_clean();

        // Load layout template
        $layoutFile = $_SERVER['DOCUMENT_ROOT'] . '/modules/base/_templates/layouts/' . $layout . '.php';
        if (!file_exists($layoutFile)) {
            throw new \RuntimeException("Layout not found: {$layout}");
        }

        require $layoutFile;
    }
}
```

**1.3 Refactor bootstrap.php**

File: `/modules/consignments/bootstrap.php`

```php
<?php
declare(strict_types=1);

/**
 * Consignments Module Bootstrap v2.0
 *
 * Professional bootstrap with base inheritance.
 * All pages get: Auth, Session, DB, Middleware, Templates.
 */

// Prevent direct access
if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', $_SERVER['DOCUMENT_ROOT']);
}

// ============================================================================
// 1. LOAD BASE MODULE (Core Services + Middleware)
// ============================================================================
require_once __DIR__ . '/../base/bootstrap.php';

// Base bootstrap now provides:
// ✅ CIS\Base\Database (PDO + MySQLi)
// ✅ CIS\Base\Session (secure session management)
// ✅ CIS\Base\SecurityMiddleware (CSRF, rate limiting)
// ✅ CIS\Base\Router (routing helpers)
// ✅ CIS\Base\Response (JSON/redirect helpers)
// ✅ CIS\Base\Validator (input validation)
// ✅ CIS\Base\ErrorHandler (error management)
// ✅ CISLogger (universal logging)
// ✅ Auth, Cache, RateLimiter, Encryption, etc.

// ============================================================================
// 2. DEFINE MODULE CONSTANTS
// ============================================================================
if (!defined('CONSIGNMENTS_MODULE_PATH')) {
    define('CONSIGNMENTS_MODULE_PATH', ROOT_PATH . '/modules/consignments');
    define('CONSIGNMENTS_API_PATH', CONSIGNMENTS_MODULE_PATH . '/api');
    define('CONSIGNMENTS_VIEWS_PATH', CONSIGNMENTS_MODULE_PATH . '/views');
    define('CONSIGNMENTS_LIB_PATH', CONSIGNMENTS_MODULE_PATH . '/lib');
}

// ============================================================================
// 3. LOAD PSR-4 AUTOLOADER (Consignments\* namespace)
// ============================================================================
require_once __DIR__ . '/autoload.php';

// ============================================================================
// 4. LOAD CONSIGNMENTS BASE CLASSES
// ============================================================================
require_once __DIR__ . '/lib/ConsignmentsAPI.php';
require_once __DIR__ . '/lib/ConsignmentsController.php';

// ============================================================================
// 5. SHARED API RESPONSE (Standardized)
// ============================================================================
if (file_exists(ROOT_PATH . '/modules/shared/api/StandardResponse.php')) {
    require_once ROOT_PATH . '/modules/shared/api/StandardResponse.php';
}

// ============================================================================
// ✅ BOOTSTRAP COMPLETE
// ============================================================================
// Available to all module pages:
// - ConsignmentsController (extend for pages)
// - ConsignmentsAPI (extend for API endpoints)
// - All base services (Database, Session, SecurityMiddleware, etc.)
// - Base templates (dashboard.php, table.php, etc.)
```

---

### Phase 2: Refactor backend.php (API Endpoints)

**Current**: 2,219 lines with inline everything
**Target**: ~300 lines of clean routing + extracted API classes

**2.1 Create TransferAPI (extends ConsignmentsAPI)**

File: `/modules/consignments/api/TransferAPI.php`

```php
<?php
declare(strict_types=1);

namespace Consignments\API;

use Consignments\Lib\ConsignmentsAPI;

class TransferAPI extends ConsignmentsAPI
{
    // Auto-routes to methods like handleListTransfers, handleGetTransferDetail
    // Based on 'action' parameter in request

    protected function handleListTransfers($data)
    {
        $this->validateRequired($data, ['page', 'perPage']);

        $filters = [
            'type' => $data['type'] ?? null,
            'state' => $data['state'] ?? null,
            'outlet' => $data['outlet'] ?? null,
            'search' => $data['q'] ?? null,
        ];

        $result = $this->transferService->list(
            (int)$data['page'],
            (int)$data['perPage'],
            $filters
        );

        return $this->success($result, 'Transfers retrieved');
    }

    protected function handleGetTransferDetail($data)
    {
        $this->validateRequired($data, ['id']);
        $this->validateTransferAccess((int)$data['id']);

        $transfer = $this->transferService->getDetail((int)$data['id']);

        return $this->success($transfer, 'Transfer detail retrieved');
    }

    protected function handleCreateTransfer($data)
    {
        $this->validateRequired($data, [
            'consignment_category',
            'outlet_from',
            'outlet_to'
        ]);

        $transferId = $this->transferService->create([
            'category' => $data['consignment_category'],
            'outlet_from' => (int)$data['outlet_from'],
            'outlet_to' => (int)$data['outlet_to'],
            'supplier_id' => $data['supplier_id'] ?? null,
            'created_by' => $this->getUserId(),
        ]);

        return $this->success(['id' => $transferId], 'Transfer created', 201);
    }

    // ... other transfer actions (add_item, update_item, mark_sent, etc.)
}
```

**2.2 Slim backend.php Router**

File: `/modules/consignments/TransferManager/backend.php` (new version ~100 lines)

```php
<?php
declare(strict_types=1);

/**
 * Transfer Manager API Router
 *
 * Routes all transfer API requests to TransferAPI class.
 * Inherits auth, CSRF, logging from BaseAPI.
 */

require_once __DIR__ . '/../bootstrap.php';

use Consignments\API\TransferAPI;

// Initialize API handler
$api = new TransferAPI([
    'require_auth' => true,
    'allowed_methods' => ['POST', 'GET'],
    'log_requests' => true,
]);

// Handle request (BaseAPI manages entire lifecycle)
$api->handleRequest();
```

**Benefits**:
- ✅ 2,219 lines → ~100 line router + organized API classes
- ✅ Auto CSRF validation (BaseAPI)
- ✅ Auto auth check (BaseAPI)
- ✅ Standard JSON envelopes (BaseAPI)
- ✅ Logging with correlation IDs (BaseAPI)
- ✅ Rate limiting ready (BaseAPI)
- ✅ Testable API classes (no global state)

---

### Phase 3: Refactor Frontend Pages (Template Inheritance)

**Current**: Each page has hardcoded HTML, duplicate auth checks
**Target**: Clean controllers extending ConsignmentsController, using base templates

**Example: TransferManager/frontend.php Refactor**

**BEFORE** (Typical current state):
```php
<?php
require_once __DIR__ . '/../../app.php';

// Check authentication
if (!isLoggedIn()) {
    header('Location: /login.php');
    exit;
}

// DB query
$con = getDbConnection();
$transfers = mysqli_query($con, "SELECT * FROM transfers...");

?>
<!DOCTYPE html>
<html>
<head>
    <title>Transfer Manager</title>
    <link rel="stylesheet" href="/assets/css/bootstrap.min.css">
    <!-- duplicate CSS includes -->
</head>
<body>
    <!-- hardcoded header -->
    <!-- hardcoded sidebar -->
    <div class="content">
        <!-- page content -->
    </div>
    <!-- hardcoded footer -->
    <!-- duplicate JS includes -->
</body>
</html>
```

**AFTER** (New clean version):
```php
<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use Consignments\Lib\ConsignmentsController;
use Consignments\Services\TransferService;

class TransferManagerController extends ConsignmentsController
{
    private TransferService $transferService;

    public function __construct()
    {
        parent::__construct();
        $this->transferService = new TransferService($this->db);
    }

    public function index(): void
    {
        // Get transfers summary
        $stats = $this->transferService->getStats();

        // Render using base dashboard template
        $this->render('dashboard', 'transfers/manager', [
            'pageTitle' => 'Transfer Manager',
            'stats' => $stats,
            'pageCSS' => ['/modules/consignments/assets/css/transfers.css'],
            'pageJS' => ['/modules/consignments/assets/js/transfers.js'],
        ]);
    }
}

// Execute controller
$controller = new TransferManagerController();
$controller->index();
```

**View File**: `/modules/consignments/views/transfers/manager.php`
```php
<?php
/**
 * Transfer Manager Content View
 *
 * This gets injected into base dashboard template.
 * No need for header/footer/sidebar - base template handles it.
 */
?>

<div class="container-fluid">
    <!-- Breadcrumbs (auto-rendered by base template) -->

    <div class="row mb-4">
        <div class="col-12">
            <h1><?= htmlspecialchars($pageTitle) ?></h1>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="cis-card">
                <div class="cis-card-body">
                    <h5>Active Transfers</h5>
                    <p class="h2"><?= $stats['active'] ?? 0 ?></p>
                </div>
            </div>
        </div>
        <!-- more cards -->
    </div>

    <!-- Transfers Table -->
    <div class="cis-card">
        <div class="cis-card-header">
            <h5>Recent Transfers</h5>
        </div>
        <div class="cis-card-body">
            <table id="transfers-table" class="table table-striped">
                <!-- DataTable initialized by JS -->
            </table>
        </div>
    </div>
</div>

<!-- Page-specific JS -->
<script>
// Initialize transfers table with AJAX data from backend.php
</script>
```

**Benefits**:
- ✅ Clean separation: Controller → View → Template
- ✅ No duplicate HTML structure
- ✅ Consistent theme across all pages
- ✅ Automatic header/sidebar/footer from base
- ✅ Auth handled by ConsignmentsController base class
- ✅ CSRF token auto-available via $csrfToken
- ✅ Easy to maintain and extend

---

### Phase 4: Database & Services Refactor

**4.1 Remove Direct DB Connections**

**BEFORE**:
```php
function db(): mysqli {
    $conn = new mysqli($host, $user, $pass, $name);
    return $conn;
}

$con = db();
$result = mysqli_query($con, "SELECT ...");
```

**AFTER**:
```php
use CIS\Base\Database;

// PDO (recommended)
$stmt = Database::query("SELECT * FROM transfers WHERE id = ?", [$id]);
$transfer = $stmt->fetch();

// Or get PDO directly
$pdo = Database::pdo();

// MySQLi (legacy support - must call initMySQLi first)
Database::initMySQLi();
$mysqli = Database::mysqli();
```

**4.2 Service Layer Pattern**

All business logic in service classes (already partially done):

```
/modules/consignments/lib/Services/
├── TransferService.php          ✅ Exists, refactor to use Database::pdo()
├── ConsignmentService.php       ✅ Exists, refactor to use Database::pdo()
├── ReceivingService.php         ✅ Exists, refactor to use Database::pdo()
├── PurchaseOrderService.php     ✅ Exists, refactor to use Database::pdo()
└── LightspeedSyncService.php    🆕 Create for sync logic
```

**Refactor Example**:
```php
// BEFORE (direct DB in service constructor)
public function __construct(PDO $pdo) {
    $this->pdo = $pdo; // Injected from somewhere
}

// AFTER (use base Database singleton)
use CIS\Base\Database;

public function __construct() {
    // Get PDO from base (auto-initialized, pooled)
}

public function getById(int $id): ?array {
    $stmt = Database::query(
        "SELECT * FROM transfers WHERE id = ?",
        [$id]
    );
    return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
}
```

---

## 📋 Implementation Checklist

### Phase 1: Foundation Classes ✅
- [ ] Create `/modules/consignments/lib/ConsignmentsAPI.php`
- [ ] Create `/modules/consignments/lib/ConsignmentsController.php`
- [ ] Refactor `/modules/consignments/bootstrap.php` (remove deprecated code)
- [ ] Create base view directory `/modules/consignments/views/`
- [ ] Test bootstrap loads without errors
- [ ] Verify all base services accessible

### Phase 2: API Refactor 🔄
- [ ] Create `/modules/consignments/api/TransferAPI.php`
- [ ] Extract all backend.php actions to TransferAPI methods
- [ ] Replace backend.php with slim router (~100 lines)
- [ ] Test all 24 API endpoints work with new structure
- [ ] Verify CSRF validation works
- [ ] Verify auth checks work
- [ ] Test error handling (404, 401, 500)
- [ ] Remove inline DB helper functions

### Phase 3: Frontend Pages Refactor 🔄
**Priority Order** (start with most critical):

1. **TransferManager/frontend.php** (main interface)
   - [ ] Create TransferManagerController
   - [ ] Create view file `views/transfers/manager.php`
   - [ ] Test render with dashboard.php template
   - [ ] Verify sidebar navigation works
   - [ ] Verify AJAX calls to backend.php work

2. **stock-transfers/pack-pro.php** (packing interface)
   - [ ] Create PackProController
   - [ ] Create view file `views/transfers/pack-pro.php`
   - [ ] Use table.php layout (DataTables integration)
   - [ ] Test barcode scanning integration

3. **admin/dashboard.php** (monitoring)
   - [ ] Create ConsignmentDashboardController
   - [ ] Create view file `views/admin/dashboard.php`
   - [ ] Use dashboard.php layout with stat cards

4. **All remaining pages** (~20 files)
   - [ ] inventory-upload.php → controller pattern
   - [ ] product-transfer-sheet.php → controller pattern
   - [ ] consignment-export.php → controller pattern
   - [ ] etc.

### Phase 4: Services & Database 🔄
- [ ] Refactor TransferService to use `Database::pdo()`
- [ ] Refactor ConsignmentService to use `Database::pdo()`
- [ ] Refactor ReceivingService to use `Database::pdo()`
- [ ] Refactor PurchaseOrderService to use `Database::pdo()`
- [ ] Create LightspeedSyncService
- [ ] Remove all direct DB connections
- [ ] Remove all mysqli globals
- [ ] Verify connection pooling works (one DB connection per request)

### Phase 5: Testing & Validation ✅
- [ ] Test all 24 API endpoints (automated test suite)
- [ ] Test all frontend pages load correctly
- [ ] Test authentication redirects work
- [ ] Test CSRF validation works
- [ ] Test rate limiting works (if enabled)
- [ ] Test error pages (404, 500)
- [ ] Performance test (page load times, DB query counts)
- [ ] Security audit (no secrets exposed, HTTPS enforced)
- [ ] Load test with ab/hey (simulate 100 concurrent users)

### Phase 6: Documentation & Cleanup 📝
- [ ] Update README.md with new architecture
- [ ] Document ConsignmentsAPI usage for future endpoints
- [ ] Document ConsignmentsController usage for future pages
- [ ] Create API endpoint reference (Swagger/OpenAPI)
- [ ] Remove deprecated code (old shared functions)
- [ ] Update KNOWLEDGE_BASE.md with new patterns
- [ ] Archive old backend.php as `backend.php.v1.backup`

---

## 🔐 Security Improvements

### Before (Current)
❌ Custom CSRF implementation per file
❌ Inline auth checks (`if (!isLoggedIn())`)
❌ No rate limiting
❌ No request logging
❌ No input validation framework
❌ No XSS protection in templates

### After (New)
✅ Base SecurityMiddleware CSRF (standardized)
✅ ConsignmentsController auto-enforces auth
✅ BaseAPI rate limiting ready (120 req/min)
✅ BaseAPI correlation ID logging (trace requests)
✅ BaseAPI input validation (`validateRequired`)
✅ Base templates auto-escape output (`htmlspecialchars`)

---

## ⚡ Performance Improvements

### Before (Current)
❌ New DB connection per file (~30 connections/page)
❌ No query result caching
❌ No CDN for assets
❌ Inline CSS/JS in every page
❌ No gzip compression

### After (New)
✅ Single pooled connection per request (Database singleton)
✅ Cache service available via base (`Cache::get/set`)
✅ Base templates use CDN (Bootstrap, DataTables, FA)
✅ External CSS/JS files (cached by browser)
✅ Base bootstrap enables gzip (nginx config)

---

## 📈 Maintainability Improvements

### Before (Current)
❌ 2,219 line backend.php (god file)
❌ Duplicate code across 30+ files
❌ No consistent error handling
❌ No code style standard
❌ No autoloading (manual requires)

### After (New)
✅ Organized API classes (~100-200 lines each)
✅ DRY - base classes eliminate duplication
✅ Consistent error handling (BaseAPI, ErrorHandler)
✅ PSR-12 enforced (phpcs.xml)
✅ PSR-4 autoloading (composer.json)

---

## 🧪 Testing Strategy

### Unit Tests
```php
// Test TransferAPI methods in isolation
class TransferAPITest extends TestCase {
    public function testListTransfers() {
        $api = new TransferAPI(['require_auth' => false]);
        $result = $api->handleListTransfers(['page' => 1, 'perPage' => 20]);
        $this->assertTrue($result['success']);
    }
}
```

### Integration Tests
```bash
# Test full request lifecycle
curl -X POST http://localhost/modules/consignments/TransferManager/backend.php \
  -H "Content-Type: application/json" \
  -H "X-CSRF-Token: $TOKEN" \
  -d '{"action":"list_transfers","page":1,"perPage":20}'
```

### Load Tests
```bash
# Simulate 100 concurrent users
ab -n 1000 -c 100 -H "Cookie: PHPSESSID=$SESSION" \
  http://localhost/modules/consignments/TransferManager/frontend.php
```

---

## 🚀 Migration Path (Zero Downtime)

### Option A: Big Bang (Recommended for development)
1. Create new architecture in parallel
2. Test thoroughly in development
3. Deploy to production all at once
4. Keep old files as `.backup` for 1 week

### Option B: Gradual Migration (Lower risk)
1. Deploy Phase 1 (foundation classes) - no breaking changes
2. Deploy Phase 2 (API refactor) - test backend.php thoroughly
3. Deploy Phase 3 (frontend pages) - one page at a time
4. Deploy Phase 4 (services) - internal changes only

### Rollback Plan
```bash
# If issues detected, instant rollback:
cd /modules/consignments
mv backend.php backend.php.new
mv backend.php.v1.backup backend.php
# Restart PHP-FPM
sudo systemctl restart php-fpm
```

---

## 📊 Success Metrics

### Code Quality
- [ ] Lines of code reduced by 40% (eliminate duplication)
- [ ] Cyclomatic complexity < 10 per method
- [ ] PSR-12 compliance 100%
- [ ] No phpcs violations

### Performance
- [ ] Page load time < 500ms (p95)
- [ ] API response time < 200ms (p95)
- [ ] DB queries per page < 10
- [ ] Memory usage per request < 10MB

### Security
- [ ] All endpoints CSRF protected
- [ ] All pages auth-protected
- [ ] No secrets in code
- [ ] Rate limiting active

### Maintainability
- [ ] Code coverage > 70%
- [ ] PHPDoc comments 100%
- [ ] Onboarding time for new dev < 2 hours
- [ ] Bug fix time reduced by 50%

---

## 🎯 Next Steps

### Immediate (After Approval)
1. **Create foundation classes** (Phase 1)
   - ConsignmentsAPI.php
   - ConsignmentsController.php
   - Updated bootstrap.php

2. **Test foundation works**
   - Create simple test API endpoint
   - Create simple test page
   - Verify base services accessible

3. **Refactor backend.php** (Phase 2)
   - Extract TransferAPI
   - Test all 24 endpoints
   - Replace old backend.php

4. **Refactor first page** (Phase 3)
   - TransferManager/frontend.php
   - Verify template inheritance works
   - Test full workflow

### Then Continue Phases 3-6

---

## ❓ Questions for User

Before proceeding, please confirm:

1. **Approval**: Do you approve this refactoring plan?
2. **Priority**: Should we do Big Bang (all at once) or Gradual (phase by phase)?
3. **Testing**: Do you have a staging environment for testing?
4. **Timeline**: Any deadline constraints?
5. **Concerns**: Any specific concerns about this approach?

---

**Status**: ⏸️ AWAITING APPROVAL
**Next**: After approval → Implement Phase 1 foundation classes
**ETA**: Phase 1-2: 2-3 hours | Phase 3: 4-5 hours | Phase 4-6: 2-3 hours | Total: ~10-12 hours work
