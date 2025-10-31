# 🔄 Consignments Rebuild - Extract the Good Ideas

**Purpose:** Catalog all the excellent patterns, business logic, and features from your current consignments work that should be preserved in the rebuild using the proper `/modules/base/_templates/layouts/` system.

**Status:** Extraction phase before clean rebuild
**Date:** October 31, 2025

---

## 📋 Table of Contents

1. [What to Keep](#what-to-keep)
2. [What to Rebuild Cleanly](#what-to-rebuild-cleanly)
3. [Business Logic Worth Preserving](#business-logic-worth-preserving)
4. [UI/UX Patterns to Reuse](#uiux-patterns-to-reuse)
5. [Technical Patterns to Keep](#technical-patterns-to-keep)
6. [Security Features to Preserve](#security-features-to-preserve)
7. [Rebuild Strategy](#rebuild-strategy)

---

## ✅ What to Keep (Already Good)

### 1. **Business Logic & Validation**

**Location:** Scattered across various files
**Status:** ✅ **KEEP** - Extract and centralize

**Good patterns found:**
- Transfer state validation (`OPEN`, `PACKING`, etc.)
- Transfer category checking (`STOCK` vs others)
- Outlet permissions (can user access this transfer?)
- Product validation logic
- Quantity/inventory checks

**Action:** Extract into clean service classes

---

### 2. **Auto-Save System**

**Location:**
- `/stock-transfers/js/` (auto-save logic)
- Related API endpoints
- Auto-save indicator UI

**Status:** ✅ **KEEP CONCEPT** - Rebuild with cleaner architecture

**What's good:**
- Real-time save indicator (badge in corner)
- Save status feedback ("IDLE", "SAVING", "SAVED", "ERROR")
- Timestamp tracking
- Visual feedback to users

**What to improve:**
- Simplify API structure
- Use consistent endpoint patterns
- Better error recovery
- Cleaner state management

**Action:** Rebuild with same UX, cleaner code

---

### 3. **Asset Auto-Loading System**

**Location:** `/shared/functions/auto-load-assets.php`
**Status:** ✅ **EXCELLENT** - Keep as-is

**Found in `pack.php`:**
```php
require_once __DIR__ . '/../../shared/functions/auto-load-assets.php';
$autoCSS = autoLoadModuleCSS(__FILE__, [
    'additional' => [
        '/modules/consignments/stock-transfers/css/pack-print.css' => ['media' => 'print']
    ]
]);
$autoJS = autoLoadModuleJS(__FILE__, [
    'additional' => [
        '/modules/consignments/stock-transfers/js/pipeline.js',
        '/modules/consignments/stock-transfers/js/pack-fix.js',
    ],
    'defer' => false
]);
```

**Why it's good:**
- Convention over configuration
- Automatic discovery of module CSS/JS
- Optional overrides for specific needs
- Supports media queries, defer, etc.

**Action:** Use this pattern in all rebuilt pages

---

### 4. **CSRF Protection Pattern**

**Location:** Found in multiple files
**Status:** ✅ **KEEP**

```php
$csrf = htmlspecialchars(
    function_exists('cis_csrf_token')
        ? (string)cis_csrf_token()
        : (string)($_SESSION['csrf'] ?? ''),
    ENT_QUOTES
);
```

**Why it's good:**
- Fallback logic if helper not available
- Proper escaping
- Session-based tokens

**Action:** Standardize this in all forms

---

### 5. **Error Page Helper**

**Location:** `/shared/blocks/error.php` (referenced in pack.php)
**Status:** ✅ **EXCELLENT**

**Usage in pack.php:**
```php
showErrorPage("Transfer #$transferId not found or you don't have access to it.", [
    'title' => 'Unable to Load Transfer',
    'backUrl' => 'index.php',
    'backLabel' => 'Back to Transfer List'
]);
```

**Why it's good:**
- User-friendly error messages
- Contextual back navigation
- Consistent error UX
- Proper HTTP status codes

**Action:** Use in all rebuilt pages

---

### 6. **Universal Transfer Getter**

**Location:** Mentioned in pack.php
**Function:** `getUniversalTransfer($transferId)`
**Status:** ✅ **KEEP**

**What it does:**
- Single source of truth for transfer data
- Handles permissions automatically
- Returns standardized object
- Throws exceptions on error

**Action:** Build PO equivalent: `getUniversalPurchaseOrder($poId)`

---

### 7. **Admin Controls Structure**

**Location:** `/views/admin-controls.php`
**Status:** ✅ **EXCELLENT CONCEPT** - Rebuild with proper layout

**Features found:**
- User permissions management
- Role-based access control
- System validation
- Tabbed interface for different settings

**Action:** Rebuild using `dashboard.php` layout, keep feature set

---

### 8. **Breadcrumb Pattern**

**Found in pack.php:**
```php
<ol class="breadcrumb">
    <li class="breadcrumb-item">Home</li>
    <li class="breadcrumb-item"><a href="#">Transfers</a></li>
    <li class="breadcrumb-item active">
        OUTGOING Stock Transfer #<?= (int)$transferData->id; ?>
        To <?= htmlspecialchars($transferData->outlet_to->name ?? ''); ?>
    </li>
</ol>
```

**Status:** ✅ **KEEP PATTERN** - But use layout system

**Action:** Define `$breadcrumb_items` array, let layout render it

---

### 9. **Module Bootstrap Pattern**

**Location:** `/bootstrap.php`
**Status:** ✅ **EXCELLENT**

**What it does:**
- Loads CIS core
- Defines module constants
- Sets up autoloading
- Initializes shared resources

**Action:** Keep this pattern for PO module

---

### 10. **Documentation Standards**

**Location:** Multiple files in `/docs/`, `/_kb/`
**Status:** ✅ **EXCELLENT DISCIPLINE**

**Found:**
- Comprehensive documentation
- API playbooks
- Implementation roadmaps
- Test results
- Architecture decisions

**Action:** Continue this discipline in rebuild

---

## 🔄 What to Rebuild Cleanly

### 1. **Page Structure → Use Base Layouts**

**Current:** Mixed approaches, some use old templates
**Rebuild:** Use `/modules/base/_templates/layouts/`

**Map old pages to new layouts:**

| Page Type | Current | New Layout |
|-----------|---------|------------|
| List view | Custom HTML | `table.php` |
| Create/Edit form | Mixed template | `dashboard.php` |
| Detail view | Custom cards | `card.php` |
| Minimal overlays | Inline HTML | `blank.php` |

---

### 2. **API Endpoints → Standardize Structure**

**Current:** Mixed locations, inconsistent patterns
**Rebuild:** Clean API structure

**New structure:**
```
/modules/consignments/api/
├── purchase-orders/
│   ├── create.php
│   ├── update.php
│   ├── delete.php
│   ├── get.php
│   ├── list.php
│   ├── approve.php
│   └── cancel.php
├── suppliers/
│   ├── list.php
│   └── get.php
└── shared/
    ├── autosave.php
    └── validate.php
```

**Standardize response format:**
```php
// Success
['success' => true, 'data' => [...], 'message' => '...']

// Error
['success' => false, 'error' => '...', 'code' => 'ERROR_CODE']
```

---

### 3. **JavaScript Organization**

**Current:** Some files in page directories, some in module root
**Rebuild:** Clear hierarchy

**New structure:**
```
/modules/consignments/js/
├── core/
│   ├── api-client.js        (AJAX wrapper)
│   ├── validation.js        (Form validation)
│   └── utils.js             (Shared utilities)
├── purchase-orders/
│   ├── list.js
│   ├── create.js
│   ├── edit.js
│   └── approve.js
└── shared/
    ├── autosave.js          (Reusable auto-save)
    └── notifications.js     (Toast/alert system)
```

---

### 4. **CSS Organization**

**Current:** Mixed locations
**Rebuild:** Module-scoped with auto-loading

**New structure:**
```
/modules/consignments/css/
├── core/
│   ├── variables.css        (Module-specific CSS vars)
│   └── base.css             (Module base styles)
├── purchase-orders/
│   ├── list.css
│   ├── form.css
│   └── print.css            (Print-specific)
└── components/
    ├── status-badges.css
    └── approval-buttons.css
```

---

### 5. **Database Access → Service Classes**

**Current:** Mixed queries in page files
**Rebuild:** Clean service layer

**Create service classes:**
```php
// /lib/Services/PurchaseOrderService.php
class PurchaseOrderService {
    public function create(array $data): PurchaseOrder
    public function get(int $id): ?PurchaseOrder
    public function update(int $id, array $data): bool
    public function delete(int $id): bool
    public function approve(int $id, int $userId): bool
    public function cancel(int $id, string $reason): bool
}

// /lib/Services/SupplierService.php
class SupplierService {
    public function list(array $filters = []): array
    public function get(int $id): ?Supplier
    public function getByVendorCode(string $code): ?Supplier
}
```

---

## 💡 Business Logic Worth Preserving

### 1. **Transfer State Machine**

**Current states found:** `OPEN`, `PACKING`, `SENT`, `RECEIVED`, etc.
**Status:** ✅ **EXCELLENT PATTERN**

**For Purchase Orders, adapt to:**
- `DRAFT` → Creating/editing PO
- `PENDING_APPROVAL` → Waiting for approval
- `APPROVED` → Ready to send to supplier
- `SENT` → Sent to supplier
- `CONFIRMED` → Supplier confirmed
- `PARTIAL_RECEIVED` → Some items received
- `RECEIVED` → All items received
- `CANCELLED` → Order cancelled
- `AMENDED` → Order amended after sending

**Action:** Create state transition rules and validation

---

### 2. **Permission Checks**

**Pattern found:**
- Check user can access outlet
- Check user can edit transfer
- Check transfer state allows operation

**For PO, check:**
- User can create PO for outlet
- User can approve PO (approval matrix from Q21-Q26)
- User can receive PO items
- User can amend sent PO (if supplier allows)

**Action:** Centralize in permission service

---

### 3. **Validation Rules**

**Found patterns:**
- Transfer must be in valid state for operation
- Transfer category must match page type
- User must have access to source/destination outlets
- Products must exist and be valid
- Quantities must be positive

**For PO, add:**
- Supplier must be active
- Products must be orderable from supplier
- Minimum order quantities
- Maximum order quantities
- Budget approval thresholds
- Freight requirements

**Action:** Centralize in validation service

---

### 4. **Product Search/Selection**

**Found:** "Add Products" modal pattern
**Status:** ✅ **GOOD UX** - Rebuild cleanly

**Features:**
- Search products
- Filter by category/brand
- Show availability
- Add multiple at once
- Quantity input

**Action:** Rebuild with cleaner modal using `blank.php` layout

---

### 5. **Auto-Save Logic**

**Found:** Save draft periodically
**Status:** ✅ **EXCELLENT FEATURE**

**Business rules to preserve:**
- Save every N seconds if changed
- Show save status to user
- Recover draft on page reload
- Clear draft when submitted
- Handle conflicts (if edited elsewhere)

**Action:** Rebuild with same UX, cleaner implementation

---

## 🎨 UI/UX Patterns to Reuse

### 1. **Fixed Auto-Save Badge**

**Current:**
```html
<div class="auto-save-container">
    <div id="autosave-indicator" class="auto-save-badge">
        <div class="save-status-icon"></div>
        <div class="save-status-text">
            <span class="save-status">IDLE</span>
            <span class="save-timestamp">Never</span>
        </div>
    </div>
</div>
```

**Status:** ✅ **EXCELLENT UX**
**Action:** Reuse this exact pattern

---

### 2. **Card Header with Actions**

**Found pattern:**
```html
<div class="card-header d-flex justify-content-between align-items-center">
    <div>
        <h4 class="card-title mb-0">Title</h4>
        <div class="small text-muted">Subtitle</div>
    </div>
    <div class="btn-group">
        <button class="btn btn-outline-primary">Action</button>
    </div>
</div>
```

**Status:** ✅ **CLEAN PATTERN**
**Action:** Use in rebuilt pages

---

### 3. **Status Badges**

**Likely found in CSS:**
- Color-coded status indicators
- State-specific styling
- Responsive badges

**Action:** Create reusable status badge component

---

### 4. **Breadcrumb Navigation**

**Pattern:** Home → Section → Subsection → Current
**Status:** ✅ **GOOD UX**
**Action:** Use in all main pages

---

### 5. **Print Styles**

**Found:** `/css/pack-print.css`
**Status:** ✅ **IMPORTANT FEATURE**

**Print requirements:**
- Clean layout for printing
- Hide navigation/sidebars
- Show essential info only
- Optimize for A4 paper

**Action:** Create print stylesheets for PO pages

---

## 🔒 Security Features to Preserve

### 1. **CSRF Protection**

**Status:** ✅ **REQUIRED**
**Pattern:** Token in form + meta tag
**Action:** Use in all forms

---

### 2. **Input Validation**

**Status:** ✅ **REQUIRED**
**Pattern:** Server-side validation with detailed errors
**Action:** Implement in all API endpoints

---

### 3. **Authorization Checks**

**Status:** ✅ **REQUIRED**
**Pattern:** Check permissions before operations
**Action:** Implement approval matrix from Q21-Q26

---

### 4. **SQL Injection Prevention**

**Status:** ✅ **REQUIRED**
**Pattern:** Prepared statements only
**Action:** Enforce in all DB queries

---

### 5. **XSS Prevention**

**Status:** ✅ **REQUIRED**
**Pattern:** `htmlspecialchars()` on all output
**Action:** Enforce in all templates

---

## 🚀 Rebuild Strategy

### Phase 1: Foundation (Week 1)

**Goal:** Set up clean structure

1. ✅ **Create module structure**
   ```
   /modules/consignments/
   ├── purchase-orders/        (NEW - main pages)
   ├── api/                    (NEW - standardized)
   ├── lib/                    (NEW - services/models)
   ├── css/                    (reorganize)
   ├── js/                     (reorganize)
   └── config/                 (keep)
   ```

2. ✅ **Create base service classes**
   - `PurchaseOrderService.php`
   - `SupplierService.php`
   - `ApprovalService.php`
   - `ValidationService.php`
   - `PermissionService.php`

3. ✅ **Set up database migrations**
   - Purchase order tables (from Q1-Q35)
   - Approval workflow tables (from Q21-Q26)
   - Audit log tables

---

### Phase 2: Core Pages (Week 2)

**Goal:** Basic CRUD functionality

1. ✅ **List page** (using `table.php` layout)
   - View all POs
   - Filter/search
   - Quick actions
   - Pagination

2. ✅ **Create page** (using `dashboard.php` layout)
   - Supplier selection
   - Product selection modal
   - Line item table
   - Auto-save
   - Submit workflow

3. ✅ **Detail/View page** (using `card.php` layout)
   - Show PO details
   - Edit button (if allowed)
   - Approval actions (if applicable)
   - Status timeline

4. ✅ **Edit page** (using `dashboard.php` layout)
   - Load existing PO
   - Same form as create
   - Auto-save
   - Submit workflow

---

### Phase 3: Approvals (Week 3)

**Goal:** Implement approval workflow from Q21-Q26

1. ✅ **Approval dashboard**
   - Pending approvals
   - Approval history
   - Delegated approvals

2. ✅ **Approval actions**
   - Approve
   - Reject
   - Request amendment
   - Escalate

3. ✅ **Email notifications** (from Q27)
   - Internal notifications
   - Supplier notifications
   - Approval reminders

---

### Phase 4: Integrations (Week 4)

**Goal:** External system integrations

1. ✅ **Lightspeed/Vend integration**
   - Create consignment when PO approved
   - Update stock on receipt
   - Sync status

2. ✅ **Freight integration** (from Q27-Q35)
   - GSS API
   - NZ Post API
   - FreightEngine
   - Rate comparison

3. ✅ **Accounting integration**
   - Xero invoice creation
   - Cost allocation
   - Budget tracking

---

### Phase 5: Advanced Features (Week 5)

**Goal:** Polish and optimization

1. ✅ **Bulk operations**
   - Bulk create from CSV
   - Bulk approve
   - Bulk send

2. ✅ **Reporting**
   - PO volume reports
   - Supplier performance
   - Budget tracking
   - Approval metrics

3. ✅ **Performance optimization**
   - Caching
   - Query optimization
   - Asset minification

---

### Phase 6: Testing & Deployment (Week 6-7)

**Goal:** Production-ready system

1. ✅ **Testing**
   - Unit tests for services
   - Integration tests for APIs
   - UI tests for key workflows
   - Load testing

2. ✅ **Documentation**
   - User guide
   - Admin guide
   - API documentation
   - Development guide

3. ✅ **Deployment**
   - Staging deployment
   - User acceptance testing
   - Production deployment
   - Post-deployment monitoring

---

## 📝 Extraction Checklist

Before rebuilding each feature, extract:

- [ ] Business logic (validation rules, state transitions)
- [ ] UI patterns (layouts, components, interactions)
- [ ] Security measures (CSRF, validation, authorization)
- [ ] Database queries (optimize and refactor)
- [ ] JavaScript functionality (events, AJAX, validation)
- [ ] CSS styling (responsive, print, components)
- [ ] Error handling (user messages, logging)
- [ ] Documentation (requirements, decisions)

---

## 🎯 Success Criteria

The rebuild is successful when:

✅ All features from Q1-Q35 are implemented
✅ All pages use `/modules/base/_templates/layouts/` properly
✅ All APIs follow standardized structure
✅ All security measures are implemented
✅ All good UX patterns are preserved
✅ Code is cleaner and more maintainable
✅ Documentation is comprehensive
✅ Tests are passing
✅ Users can complete all workflows

---

## 📚 Reference Documents

- **Business requirements:** `PEARCE_ANSWERS_SESSION_3.md` (Q1-Q35)
- **Approval workflow:** Q21-Q26 answers
- **Email templates:** Q27 answer
- **Freight integration:** `FREIGHT_GSS_NZPOST_DISCOVERY_COMPLETE.md`
- **Quick references:** `Q27-Q35_QUICK_REFERENCE.md`

---

**Next Step:** Start Phase 1 - Foundation setup using proper template system! 🚀
