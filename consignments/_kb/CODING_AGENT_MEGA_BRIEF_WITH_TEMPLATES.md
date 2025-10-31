# 🚀 MEGA COMPREHENSIVE CODING AGENT BRIEF
## Complete Consignments Module + Module Pattern Documentation

**Date:** October 31, 2025
**Status:** FINAL - Ready for Autonomous Coding Agent
**Scope:** COMPLETE Module Build + All Templates + All Patterns
**Priority:** CRITICAL - PRODUCTION DELIVERY

---

## 📋 EXECUTIVE SUMMARY

### PRIMARY OBJECTIVE
Build a **complete, production-ready Consignments Module** that handles ALL workflows:
- ✅ All 4 transfer types (Consignments, Stock Transfers, Staff Transfers, Purchase Orders)
- ✅ DRAFT status architecture for POs
- ✅ Multi-tier approval system
- ✅ Signature capture + barcode scanning + photo uploads
- ✅ Lightspeed integration (at receive time)
- ✅ AI logging via CISLogger
- ✅ Complete audit trails
- ✅ Supplier communication
- ✅ Real-time inventory sync

### SECONDARY OBJECTIVE
**Create Module Pattern Documentation** that outlines:
- Standard module folder structure
- How to inherit from CIS template properly
- Using new base module architecture
- Best practices for extending CIS

---

## 🗂️ PART 1: DATABASE SCHEMA (All Table Prefixes)

### TABLE PREFIXES & NAMING CONVENTION

```
VEND_*              = Lightspeed/Vend integration tables (master data)
CONSIGNMENT_*       = Consignment-specific tables
QUEUE_*             = Queue/async processing tables
TRANSFER_*          = Stock transfer tables
STAFF_*             = Staff transfer tables
PO_*                = Purchase order tables
*_STATUS_LOG        = Immutable status change logs
*_AUDIT_LOG         = Detailed audit trails
```

---

### A. VEND_* TABLES (Lightspeed Master Data)

```sql
-- VEND_CONSIGNMENTS (Lightspeed consignment records)
VEND_CONSIGNMENTS (
  id (UUID),
  outlet_from_id (UUID),
  outlet_to_id (UUID),
  status (OPEN|IN_TRANSIT|RECEIVED),
  vend_version (optimistic lock),
  created_at,
  updated_at
)

-- VEND_CONSIGNMENT_ITEMS (Line items)
VEND_CONSIGNMENT_ITEMS (
  id (UUID),
  vend_consignment_id (FK),
  vend_product_id (UUID),
  expected_count,
  received_count,
  synced_at,
  created_at
)

-- VEND_PRODUCTS (Cached from Lightspeed)
VEND_PRODUCTS (
  id (UUID),
  sku,
  name,
  category,
  price,
  synced_at,
  created_at
)

-- VEND_OUTLETS (Cached from Lightspeed)
VEND_OUTLETS (
  id (UUID),
  name,
  location,
  region,
  manager_id,
  active,
  synced_at,
  created_at
)

-- VEND_SUPPLIERS (Cached from Lightspeed)
VEND_SUPPLIERS (
  id (UUID),
  name,
  contact_email,
  contact_phone,
  payment_terms,
  active,
  synced_at,
  created_at
)
```

---

### B. CONSIGNMENT_* TABLES (Consignment Workflow)

```sql
-- CONSIGNMENT_RECORDS (Main consignment records)
CONSIGNMENT_RECORDS (
  id (PK),
  vend_consignment_id (FK → VEND_CONSIGNMENTS),
  outlet_from_id (FK → VEND_OUTLETS),
  outlet_to_id (FK → VEND_OUTLETS),
  supplier_id (FK → VEND_SUPPLIERS),
  status (DRAFT|ACTIVE|IN_TRANSIT|RECEIVED|COMPLETED|CANCELLED),
  created_by (FK → users),
  approved_by (FK → users),
  received_by (FK → users),
  approval_tier_required (STORE_MANAGER|RETAIL_OPS|DIRECTOR),
  created_at,
  approved_at,
  received_at,
  completed_at,
  updated_at
)

-- CONSIGNMENT_ITEMS (Line items)
CONSIGNMENT_ITEMS (
  id (PK),
  consignment_id (FK),
  vend_product_id (FK → VEND_PRODUCTS),
  sku,
  expected_count,
  received_count,
  variance_reason,
  created_at,
  updated_at
)

-- CONSIGNMENT_STATUS_LOG (Immutable log)
CONSIGNMENT_STATUS_LOG (
  id (PK),
  consignment_id (FK),
  from_status,
  to_status,
  reason,
  user_id (FK),
  metadata (JSON),
  created_at
)

-- CONSIGNMENT_AUDIT_LOG (Detailed audit)
CONSIGNMENT_AUDIT_LOG (
  id (PK),
  consignment_id (FK),
  action (created|approved|received|completed|modified),
  user_id (FK),
  old_values (JSON),
  new_values (JSON),
  metadata (JSON),
  ip_address,
  created_at
)
```

---

### C. QUEUE_* TABLES (Async Processing & Webhooks)

```sql
-- QUEUE_CONSIGNMENTS (Shadow table for sync)
QUEUE_CONSIGNMENTS (
  id (PK),
  transfer_id (FK),
  vend_consignment_id (UUID),
  status (OPEN|IN_TRANSIT|RECEIVED),
  sync_status (pending|synced|error),
  last_sync_at,
  sync_error,
  created_at,
  updated_at
)

-- QUEUE_CONSIGNMENT_PRODUCTS (Product-level sync)
QUEUE_CONSIGNMENT_PRODUCTS (
  id (PK),
  queue_consignment_id (FK),
  vend_product_id (UUID),
  sku,
  expected_count,
  received_count,
  sync_status (pending|synced|error),
  created_at
)

-- QUEUE_JOBS (Job queue for background processing)
QUEUE_JOBS (
  id (PK),
  job_type (VARCHAR: transfer.create_consignment, transfer.sync_to_lightspeed, etc.),
  payload (JSON),
  status (pending|processing|completed|failed|cancelled),
  priority (1-10),
  attempts,
  max_attempts,
  last_error,
  worker_id,
  started_at,
  completed_at,
  created_at
)

-- QUEUE_WEBHOOK_EVENTS (Inbound Lightspeed webhooks)
QUEUE_WEBHOOK_EVENTS (
  id (PK),
  webhook_type (consignment.updated|consignment.received|etc.),
  payload (JSON),
  status (pending|processing|completed|failed|ignored),
  processed_at,
  error_message,
  created_at
)
```

---

### D. TRANSFER_* TABLES (Stock Transfers)

```sql
-- TRANSFER_RECORDS (Stock transfer header)
TRANSFER_RECORDS (
  id (PK),
  source_outlet_id (FK → VEND_OUTLETS),
  dest_outlet_id (FK → VEND_OUTLETS),
  transfer_type (CONSIGNMENT|STOCK_TRANSFER|STAFF_TRANSFER|PO),
  status (DRAFT|ACTIVE|SHIPPED|RECEIVED|COMPLETED),
  created_by,
  approved_by,
  received_by,
  created_at,
  shipped_at,
  received_at,
  completed_at,
  updated_at
)

-- TRANSFER_ITEMS (Line items)
TRANSFER_ITEMS (
  id (PK),
  transfer_id (FK),
  vend_product_id (FK),
  sku,
  sent_qty,
  received_qty,
  variance_reason,
  created_at
)

-- TRANSFER_STATUS_LOG (Status changes)
TRANSFER_STATUS_LOG (
  id (PK),
  transfer_id (FK),
  from_status,
  to_status,
  reason,
  user_id,
  created_at
)
```

---

### E. STAFF_* TABLES (Staff Transfers)

```sql
-- STAFF_TRANSFERS (Staff personal item transfers)
STAFF_TRANSFERS (
  id (PK),
  from_staff_id (FK → users),
  to_staff_id (FK → users),
  from_outlet_id (FK → VEND_OUTLETS),
  to_outlet_id (FK → VEND_OUTLETS),
  status (INITIATED|APPROVED|COMPLETED|CANCELLED),
  transfer_reason,
  created_at,
  approved_at,
  completed_at
)

-- STAFF_TRANSFER_ITEMS (Items being transferred)
STAFF_TRANSFER_ITEMS (
  id (PK),
  staff_transfer_id (FK),
  vend_product_id (FK),
  sku,
  quantity,
  condition (new|used|damaged),
  created_at
)

-- STAFF_TRANSFER_LOG (Audit trail)
STAFF_TRANSFER_LOG (
  id (PK),
  staff_transfer_id (FK),
  action,
  user_id,
  notes,
  created_at
)
```

---

### F. PO_* TABLES (Purchase Orders)

```sql
-- PURCHASE_ORDERS (PO header - DRAFT status critical)
PURCHASE_ORDERS (
  id (PK),
  po_number (UNIQUE, auto-generated),
  supplier_id (FK → VEND_SUPPLIERS),
  receiving_outlet_id (FK → VEND_OUTLETS),
  status (DRAFT|ACTIVE|AWAITING_RECEIPT|RECEIVED|COMPLETED|CANCELLED),
  total_amount (DECIMAL),
  approval_tier_required (STORE_MANAGER|RETAIL_OPS|DIRECTOR),
  created_by (FK → users),
  approved_by (FK → users),
  received_by (FK → users),
  payment_terms,
  expected_delivery_date,
  created_at,
  activated_at,
  received_at,
  completed_at,
  updated_at
)

-- PO_ITEMS (Line items)
PO_ITEMS (
  id (PK),
  po_id (FK),
  vend_product_id (FK),
  sku,
  quantity_ordered,
  quantity_received,
  unit_price,
  line_total,
  received_at,
  created_at
)

-- PO_STATUS_LOG (Status progression - immutable)
PO_STATUS_LOG (
  id (PK),
  po_id (FK),
  from_status,
  to_status,
  reason,
  user_id (FK),
  metadata (JSON),
  created_at
)

-- PO_AUDIT_LOG (Detailed changes)
PO_AUDIT_LOG (
  id (PK),
  po_id (FK),
  action (created|activated|modified|received|completed),
  user_id,
  old_values (JSON),
  new_values (JSON),
  created_at
)
```

---

### G. SIGNATURE & RECEIPT TABLES

```sql
-- RECEIPT_SIGNATURES (Signature capture)
RECEIPT_SIGNATURES (
  id (PK),
  transfer_id (FK),
  po_id (FK, nullable),
  consignment_id (FK, nullable),
  staff_id (FK → users),
  signature_type (checkbox|digital|biometric),
  signature_file_path,
  signature_timestamp,
  required (BOOLEAN),
  outlet_configured (BOOLEAN),
  supplier_configured (BOOLEAN),
  created_at
)

-- RECEIPT_AUDIT_LOG (Signature audit)
RECEIPT_AUDIT_LOG (
  id (PK),
  signature_id (FK),
  action (created|viewed|verified|rejected),
  user_id,
  metadata (JSON),
  created_at
)
```

---

### H. BARCODE & PRODUCT SCANNING

```sql
-- BARCODE_SCANS (Barcode scan history)
BARCODE_SCANS (
  id (PK),
  transfer_id (FK),
  barcode_value,
  barcode_format (EAN13|UPC|Code128|CUSTOM),
  vend_product_id (FK),
  sku,
  scan_timestamp,
  qty_scanned,
  audio_feedback (tone1|tone2|tone3|none),
  created_at
)

-- BARCODE_CONFIGURATION (Per-outlet config)
BARCODE_CONFIGURATION (
  id (PK),
  outlet_id (FK → VEND_OUTLETS),
  enabled (BOOLEAN),
  format_preference,
  audio_enabled (BOOLEAN),
  created_at,
  updated_at
)
```

---

### I. AI LOGGING INTEGRATION (CISLogger)

```sql
-- All tables must log to CISLogger with:

LOG_EVENT (
  id (PK),
  entity_type (CONSIGNMENT|TRANSFER|STAFF_TRANSFER|PO),
  entity_id,
  action (CREATE|READ|UPDATE|DELETE|APPROVE|RECEIVE),
  user_id,
  user_email,
  outlet_id,
  old_data (JSON),
  new_data (JSON),
  ip_address,
  user_agent,
  correlation_id (UUID),
  metadata (JSON),
  created_at
)

-- Integration Point:
// Every action must call:
CISLogger::log([
  'entity_type' => 'CONSIGNMENT',
  'entity_id' => $consignment_id,
  'action' => 'APPROVED',
  'old_data' => $old_status,
  'new_data' => $new_status,
  'metadata' => ['approval_tier' => 'DIRECTOR']
]);
```

---

## 🏗️ PART 2: MODULE PATTERN & INHERITANCE

### NEW STANDARD MODULE FOLDER STRUCTURE

```
modules/
├── base/                          # New base module (inheritance template)
│   ├── app/
│   │   ├── Http/
│   │   │   ├── Controllers/
│   │   │   ├── Requests/
│   │   │   ├── Middleware/
│   │   │   └── Kernel.php
│   │   ├── Models/
│   │   ├── Services/
│   │   ├── Traits/
│   │   └── Exceptions/
│   ├── database/
│   │   ├── migrations/
│   │   ├── seeders/
│   │   └── factories/
│   ├── routes/
│   │   ├── api.php
│   │   ├── web.php
│   │   └── cli.php
│   ├── resources/
│   │   ├── views/
│   │   ├── css/
│   │   └── js/
│   ├── config/
│   │   └── module.php
│   ├── tests/
│   │   ├── Unit/
│   │   ├── Feature/
│   │   └── Integration/
│   ├── _kb/
│   │   ├── README.md
│   │   ├── ARCHITECTURE.md
│   │   ├── API.md
│   │   └── EXAMPLES.md
│   └── bootstrap.php              # Module initialization
│
├── consignments/                  # Inherits from base
│   ├── app/
│   │   ├── Http/
│   │   │   ├── Controllers/
│   │   │   │   ├── BaseController extends Base\Http\Controllers\BaseController
│   │   │   │   ├── ConsignmentController
│   │   │   │   ├── ReceivingController
│   │   │   │   └── ApprovalController
│   │   │   ├── Requests/
│   │   │   └── Middleware/
│   │   ├── Models/
│   │   │   ├── Consignment extends Base\Models\BaseModel
│   │   │   ├── ConsignmentItem
│   │   │   ├── Transfer
│   │   │   └── PurchaseOrder
│   │   ├── Services/
│   │   │   ├── ConsignmentService
│   │   │   ├── ApprovalService
│   │   │   ├── LightspeedSyncService
│   │   │   └── AuditService
│   │   ├── Jobs/
│   │   │   ├── SyncToLightspeedJob
│   │   │   ├── ProcessWebhookJob
│   │   │   └── GenerateReportJob
│   │   └── Traits/
│   │       ├── HasApprovalWorkflow
│   │       ├── HasAuditLog
│   │       ├── HasStatusTransitions
│   │       └── HasDraftStatus
│   ├── database/
│   │   ├── migrations/
│   │   │   ├── 2025_10_31_create_consignments_table.php
│   │   │   ├── 2025_10_31_create_purchase_orders_table.php
│   │   │   ├── 2025_10_31_create_transfers_table.php
│   │   │   └── 2025_10_31_create_approval_workflows_table.php
│   │   └── seeders/
│   │       └── ConsignmentSeeder.php
│   ├── routes/
│   │   ├── api.php                # API endpoints
│   │   ├── web.php                # Web UI routes
│   │   └── webhook.php            # Lightspeed webhooks
│   ├── resources/
│   │   ├── views/
│   │   │   ├── consignments/
│   │   │   ├── transfers/
│   │   │   ├── purchase-orders/
│   │   │   ├── receiving/
│   │   │   └── approvals/
│   │   ├── css/
│   │   │   ├── consignments.css
│   │   │   └── receiving.css
│   │   └── js/
│   │       ├── consignments.js
│   │       ├── barcode-scanner.js
│   │       ├── signature-capture.js
│   │       └── real-time-updates.js
│   ├── config/
│   │   ├── module.php             # Module configuration
│   │   ├── approval.php           # Approval tier config
│   │   ├── lightspeed.php         # Lightspeed integration config
│   │   └── barcode.php            # Barcode scanner config
│   ├── tests/
│   │   ├── Unit/
│   │   │   ├── ApprovalTest.php
│   │   │   ├── ConsignmentTest.php
│   │   │   └── LightspeedSyncTest.php
│   │   ├── Feature/
│   │   │   ├── ConsignmentWorkflowTest.php
│   │   │   ├── ReceivingTest.php
│   │   │   └── ApprovalTest.php
│   │   └── Integration/
│   │       ├── LightspeedIntegrationTest.php
│   │       ├── WebhookHandlingTest.php
│   │       └── InventorySyncTest.php
│   ├── _kb/
│   │   ├── README.md
│   │   ├── ARCHITECTURE.md
│   │   ├── API.md
│   │   ├── DATABASE.md
│   │   ├── WORKFLOW.md
│   │   ├── LIGHTSPEED_INTEGRATION.md
│   │   ├── PEARCE_ANSWERS_SESSION_1.md
│   │   ├── PEARCE_ANSWERS_SESSION_2.md
│   │   └── CODING_AGENT_MEGA_BRIEF_WITH_TEMPLATES.md
│   └── bootstrap.php              # Module initialization (extends base)
│
└── [other-modules]/              # Follow same pattern
```

---

### HOW TO INHERIT FROM BASE MODULE PROPERLY

#### Step 1: Bootstrap (Module Initialization)

**File:** `modules/consignments/bootstrap.php`

```php
<?php
declare(strict_types=1);

namespace Consignments;

use Base\ModuleBootstrap;
use Base\ServiceProvider;

class Bootstrap extends ModuleBootstrap
{
    public string $moduleName = 'consignments';
    public string $version = '1.0.0';
    public string $description = 'Complete consignments, transfers, and PO system';

    /**
     * Service providers to register
     */
    public array $providers = [
        ConsignmentServiceProvider::class,
        ApprovalServiceProvider::class,
        LightspeedServiceProvider::class,
    ];

    /**
     * Register module configuration
     */
    public function registerConfig(): void
    {
        // Inherit from base config system
        $this->mergeConfigFrom(__DIR__ . '/config/module.php', 'consignments');
        $this->mergeConfigFrom(__DIR__ . '/config/approval.php', 'consignments.approval');
        $this->mergeConfigFrom(__DIR__ . '/config/lightspeed.php', 'consignments.lightspeed');
    }

    /**
     * Register routes
     */
    public function registerRoutes(): void
    {
        // Use base router
        $this->router->group(['prefix' => 'consignments', 'middleware' => ['auth', 'module:consignments']], function() {
            $this->loadRoutesFrom(__DIR__ . '/routes/api.php');
            $this->loadRoutesFrom(__DIR__ . '/routes/web.php');
            $this->loadRoutesFrom(__DIR__ . '/routes/webhook.php');
        });
    }

    /**
     * Publish assets
     */
    public function publishAssets(): void
    {
        $this->publishes([
            __DIR__ . '/resources/views' => resource_path('views/consignments'),
            __DIR__ . '/resources/css' => public_path('css/consignments'),
            __DIR__ . '/resources/js' => public_path('js/consignments'),
        ]);
    }

    /**
     * Load migrations
     */
    public function loadMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/database/migrations');
    }
}
```

#### Step 2: Models (Inherit from Base)

**File:** `modules/consignments/app/Models/Consignment.php`

```php
<?php
declare(strict_types=1);

namespace Consignments\Models;

use Base\Models\BaseModel;
use Base\Traits\HasAuditLog;
use Base\Traits\HasStatusTransitions;
use Consignments\Traits\HasApprovalWorkflow;
use Consignments\Traits\HasDraftStatus;

class Consignment extends BaseModel
{
    use HasAuditLog;
    use HasStatusTransitions;
    use HasApprovalWorkflow;
    use HasDraftStatus;

    protected $table = 'consignments';

    protected $fillable = [
        'vend_consignment_id',
        'outlet_from_id',
        'outlet_to_id',
        'supplier_id',
        'status',
        'approval_tier_required',
        'created_by',
        'approved_by',
        'received_by',
    ];

    protected $casts = [
        'status' => 'string',
        'created_at' => 'datetime',
        'approved_at' => 'datetime',
        'received_at' => 'datetime',
    ];

    // Relationships
    public function items() { return $this->hasMany(ConsignmentItem::class); }
    public function statusLog() { return $this->hasMany(ConsignmentStatusLog::class); }
    public function auditLog() { return $this->hasMany(ConsignmentAuditLog::class); }

    // Scopes
    public function scopeDraft($query) { return $query->where('status', 'DRAFT'); }
    public function scopeActive($query) { return $query->where('status', 'ACTIVE'); }
    public function scopeAwaitingApproval($query) { return $query->where('status', 'AWAITING_APPROVAL'); }
}
```

#### Step 3: Controllers (Inherit from Base)

**File:** `modules/consignments/app/Http/Controllers/ConsignmentController.php`

```php
<?php
declare(strict_types=1);

namespace Consignments\Http\Controllers;

use Base\Http\Controllers\BaseController;
use Consignments\Models\Consignment;
use Consignments\Services\ConsignmentService;
use Illuminate\Http\Request;

class ConsignmentController extends BaseController
{
    protected ConsignmentService $service;

    public function __construct(ConsignmentService $service)
    {
        parent::__construct();
        $this->service = $service;

        // Inherit base authorization & middleware
        $this->middleware('auth');
        $this->middleware('permission:consignments.view', ['only' => ['index', 'show']]);
        $this->middleware('permission:consignments.create', ['only' => ['create', 'store']]);
        $this->middleware('permission:consignments.edit', ['only' => ['edit', 'update']]);
        $this->middleware('permission:consignments.approve', ['only' => ['approve']]);
    }

    /**
     * Get all consignments (with inheritance from base)
     */
    public function index(Request $request)
    {
        // Use base query builder
        $query = Consignment::query();

        // Apply scopes
        if ($request->get('status')) {
            $query->where('status', $request->get('status'));
        }

        // Pagination from base
        $consignments = $this->paginate($query);

        // Log action via CISLogger (inherited from base)
        $this->logAction('CONSIGNMENTS_LIST', ['filters' => $request->all()]);

        return response()->json(['data' => $consignments]);
    }

    /**
     * Approve consignment (uses inherited approval trait)
     */
    public function approve(Request $request, Consignment $consignment)
    {
        // Check approval tier
        $this->authorize('approve', $consignment);

        // Use service
        $consignment = $this->service->approve($consignment, auth()->user());

        // Log via inherited trait
        $consignment->logAudit('APPROVED', ['approver' => auth()->user()->id]);

        return response()->json(['data' => $consignment]);
    }
}
```

#### Step 4: Services (Use Dependency Injection from Base)

**File:** `modules/consignments/app/Services/ConsignmentService.php`

```php
<?php
declare(strict_types=1);

namespace Consignments\Services;

use Base\Services\BaseService;
use Consignments\Models\Consignment;
use CISLogger;

class ConsignmentService extends BaseService
{
    /**
     * Create consignment in DRAFT status
     */
    public function createDraft(array $data, $user): Consignment
    {
        $consignment = Consignment::create([
            'vend_consignment_id' => $data['vend_consignment_id'],
            'outlet_from_id' => $data['outlet_from_id'],
            'outlet_to_id' => $data['outlet_to_id'],
            'supplier_id' => $data['supplier_id'],
            'status' => 'DRAFT',  // Start as DRAFT
            'created_by' => $user->id,
            'approval_tier_required' => $this->calculateApprovalTier($data['total_amount']),
        ]);

        // Log to CISLogger
        CISLogger::log([
            'entity_type' => 'CONSIGNMENT',
            'entity_id' => $consignment->id,
            'action' => 'CREATED',
            'user_id' => $user->id,
            'metadata' => ['status' => 'DRAFT']
        ]);

        return $consignment;
    }

    /**
     * Activate DRAFT consignment (transition to ACTIVE)
     */
    public function activate(Consignment $consignment, $user): Consignment
    {
        $this->authorize('activate', $consignment, $user);

        $consignment->update(['status' => 'ACTIVE', 'approved_by' => $user->id, 'approved_at' => now()]);

        // Log state transition
        $consignment->logStatusTransition('DRAFT', 'ACTIVE', 'User approval');

        CISLogger::log([
            'entity_type' => 'CONSIGNMENT',
            'entity_id' => $consignment->id,
            'action' => 'ACTIVATED',
            'user_id' => $user->id,
            'old_data' => ['status' => 'DRAFT'],
            'new_data' => ['status' => 'ACTIVE']
        ]);

        return $consignment;
    }
}
```

#### Step 5: Traits (Module-Specific Extensions)

**File:** `modules/consignments/app/Traits/HasDraftStatus.php`

```php
<?php
declare(strict_types=1);

namespace Consignments\Traits;

trait HasDraftStatus
{
    /**
     * Check if record is in DRAFT status
     */
    public function isDraft(): bool
    {
        return $this->status === 'DRAFT';
    }

    /**
     * Check if record can transition from DRAFT
     */
    public function canTransitionFromDraft(): bool
    {
        return $this->isDraft() && $this->approved_by !== null;
    }

    /**
     * Get approval tier required for this draft
     */
    public function getApprovalTierRequired(): string
    {
        return $this->approval_tier_required;
    }

    /**
     * Check if user can approve this draft
     */
    public function canBeApprovedBy($user): bool
    {
        // Check user role against required tier
        $userTier = $this->getUserApprovalTier($user);
        return $this->tierCanApprove($userTier, $this->approval_tier_required);
    }
}
```

**File:** `modules/consignments/app/Traits/HasApprovalWorkflow.php`

```php
<?php
declare(strict_types=1);

namespace Consignments\Traits;

trait HasApprovalWorkflow
{
    /**
     * Get approval status
     */
    public function getApprovalStatus(): string
    {
        if ($this->approved_by === null && $this->status === 'DRAFT') {
            return 'PENDING_APPROVAL';
        }

        if ($this->approved_by !== null && $this->status === 'ACTIVE') {
            return 'APPROVED';
        }

        return $this->status;
    }

    /**
     * Get next approver (user or role)
     */
    public function getNextApprover()
    {
        // Based on approval tier, find next user in chain
        if ($this->approval_tier_required === 'STORE_MANAGER') {
            return $this->outlet->getStoreManager();
        }

        if ($this->approval_tier_required === 'RETAIL_OPS') {
            return User::whereHas('roles', fn($q) => $q->where('name', 'retail_ops_manager'))->first();
        }

        if ($this->approval_tier_required === 'DIRECTOR') {
            return User::whereHas('roles', fn($q) => $q->where('name', 'director'))->first();
        }
    }

    /**
     * Notify approver
     */
    public function notifyApprover(): void
    {
        $approver = $this->getNextApprover();
        if ($approver) {
            $approver->notify(new ConsignmentAwaitingApprovalNotification($this));
        }
    }
}
```

---

### CONFIGURATION FILES (Per Module)

**File:** `modules/consignments/config/module.php`

```php
<?php

return [
    'name' => 'Consignments',
    'version' => '1.0.0',
    'enabled' => true,

    'tables' => [
        'consignments' => 'consignments',
        'consignment_items' => 'consignment_items',
        'purchase_orders' => 'purchase_orders',
        'po_items' => 'po_items',
        'transfers' => 'stock_transfers',
    ],

    'audit_logging_enabled' => true,
    'ai_logging_enabled' => true,

    'features' => [
        'draft_status' => true,
        'multi_tier_approval' => true,
        'barcode_scanning' => true,
        'signature_capture' => true,
        'lightspeed_integration' => true,
        'ai_logging' => true,
    ],
];
```

**File:** `modules/consignments/config/approval.php`

```php
<?php

return [
    'tiers' => [
        'STORE_MANAGER' => [
            'min_amount' => 0,
            'max_amount' => 2000,
            'roles' => ['store_manager'],
        ],
        'RETAIL_OPS' => [
            'min_amount' => 2000,
            'max_amount' => 5000,
            'roles' => ['retail_ops_manager', 'comms_manager'],
        ],
        'DIRECTOR' => [
            'min_amount' => 5000,
            'max_amount' => null,
            'roles' => ['director'],
        ],
    ],

    'draft_status_enabled' => true,
    'require_approval_before_activation' => true,
];
```

---

## 🎯 PART 3: IMPLEMENTATION REQUIREMENTS

### A. CORE FEATURES TO BUILD

1. **Consignment Module** ✅
   - Create/receive/complete consignments
   - Track supplier consignments
   - Handle stock bin management
   - Integration with Lightspeed

2. **Purchase Order System** ✅
   - Create POs in DRAFT status
   - Multi-tier approval workflow
   - Receive goods against PO
   - Lightspeed sync at receive time

3. **Stock Transfer System** ✅
   - Inter-outlet transfers
   - Real-time inventory sync
   - Transfer approval workflow
   - Variance handling (over/under receipt)

4. **Staff Transfer System** ✅
   - Staff-to-staff item transfers
   - Approval workflow
   - Audit trail

5. **Receiving Module** ✅
   - Barcode scanning (audio feedback: 3 tones)
   - Signature capture (checkbox + staff ID)
   - Photo uploads (5 per product, auto-resize to 1080p)
   - Real-time progress tracking
   - Accept any quantity (no blocking)

6. **Approval System** ✅
   - Multi-tier approvals ($2k/$2k-$5k/$5k+)
   - Draft → Active transition
   - Email notifications
   - Dashboard for approvers

7. **Lightspeed Integration** ✅
   - Sync at receive time
   - Webhook handling
   - Queue-based processing
   - Error recovery

8. **AI Logging (CISLogger)** ✅
   - Every action logged
   - Audit trails
   - Compliance tracking

9. **Reporting** ✅
   - Weekly management reports
   - Supplier performance metrics
   - Variance analysis
   - Audit reports

### B. TECHNICAL REQUIREMENTS

- ✅ PSR-12 code style
- ✅ Type hints on all functions
- ✅ Comprehensive error handling
- ✅ Unit + integration tests
- ✅ Database migrations
- ✅ API documentation
- ✅ Seed data
- ✅ Performance optimized
- ✅ Security hardened

---

## 🚀 DELIVERABLES

1. **Complete Consignments Module**
   - All 4 transfer types
   - Full receiving workflow
   - Approval system
   - Lightspeed integration

2. **Fully Tested** (95%+ coverage)
   - Unit tests
   - Integration tests
   - API tests
   - Workflow tests

3. **Production Ready**
   - Migrations
   - Seeders
   - Documentation
   - API docs

4. **Module Pattern Documentation**
   - Complete inheritance guide
   - Base module setup
   - CIS template usage
   - Best practices

5. **Ready to Deploy**
   - No breaking changes
   - Backward compatible
   - Zero downtime
   - Rollback ready

---

**END OF BRIEF**

Ready for autonomous coding agent to begin implementation. All context provided. All requirements specified. All patterns documented.
