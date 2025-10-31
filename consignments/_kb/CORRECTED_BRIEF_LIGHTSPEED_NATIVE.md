# 🚀 CONSIGNMENTS MODULE - FINAL CORRECTED BRIEF
## Complete Module Build (Using Lightspeed Consignment Model)

**Date:** October 31, 2025
**Status:** FINAL CORRECTED - Ready for Development
**Key Change:** Uses Lightspeed native consignment model (NOT separate PO tables)

---

## ⚡ CRITICAL CLARIFICATION

**You are NOT creating separate PO_* tables.**

Instead, you're using **Lightspeed's native CONSIGNMENT MODEL**:
- ✅ `QUEUE_CONSIGNMENTS` = Shadow table of Lightspeed consignments
- ✅ `QUEUE_CONSIGNMENT_PRODUCTS` = Line items synced from Lightspeed
- ✅ Consignments handle ALL transfer types (PO, Stock Transfer, Consignment, Staff)
- ✅ Single unified model through Lightspeed

---

## 🗂️ DATABASE SCHEMA (CORRECTED)

### TABLE PREFIXES (CORRECTED)

```
VEND_*                  = Lightspeed master data (read from Lightspeed)
QUEUE_*                 = CIS shadow/cache of Lightspeed data
TRANSFER_*              = CIS transfer records (links to consignments)
STAFF_*                 = Staff transfer records
SIGNATURE_*             = Signature capture
BARCODE_*               = Barcode scanning
*_AUDIT_LOG             = CIS audit trails
*_STATUS_LOG            = CIS status transitions
```

### A. VEND_* TABLES (Read from Lightspeed)

```sql
-- VEND_CONSIGNMENTS (Lightspeed consignments - READ ONLY from Vend)
VEND_CONSIGNMENTS (
  id (UUID - from Lightspeed),
  outlet_id (UUID),
  source_outlet_id (UUID),
  type (SUPPLIER|OUTLET|RETURN|STOCKTAKE),
  status (OPEN|SENT|DISPATCHED|RECEIVED|CANCELLED|STOCKTAKE),
  reference (VARCHAR - CIS reference),
  vend_version (INT - for optimistic locking),
  created_at,
  updated_at
)

-- VEND_CONSIGNMENT_PRODUCTS (Line items - READ from Lightspeed)
VEND_CONSIGNMENT_PRODUCTS (
  id (UUID),
  consignment_id (FK → VEND_CONSIGNMENTS),
  product_id (UUID),
  sku,
  count (quantity),
  synced_at
)

-- VEND_PRODUCTS (Cached product master)
VEND_PRODUCTS (
  id (UUID),
  sku,
  name,
  category,
  price,
  synced_at
)

-- VEND_OUTLETS (Cached outlet master)
VEND_OUTLETS (
  id (UUID),
  name,
  location,
  synced_at
)
```

### B. QUEUE_* TABLES (CIS Cache/Shadow of Lightspeed)

```sql
-- QUEUE_CONSIGNMENTS (Shadow of VEND_CONSIGNMENTS for fast lookup)
QUEUE_CONSIGNMENTS (
  id (PK),
  vend_consignment_id (FK → VEND_CONSIGNMENTS, unique),
  transfer_id (FK → transfers, for linking),
  type (SUPPLIER|OUTLET|RETURN|STOCKTAKE),
  status (OPEN|SENT|DISPATCHED|RECEIVED|CANCELLED|STOCKTAKE),
  outlet_from_id (UUID),
  outlet_to_id (UUID),
  vend_version (INT - optimistic lock),
  sync_status (pending|synced|error),
  last_sync_at,
  sync_error,
  created_at,
  updated_at
)

-- QUEUE_CONSIGNMENT_PRODUCTS (Shadow of VEND_CONSIGNMENT_PRODUCTS)
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

-- QUEUE_JOBS (Background job queue)
QUEUE_JOBS (
  id (PK),
  job_type (transfer.create_consignment, transfer.sync_to_lightspeed),
  payload (JSON),
  status (pending|processing|completed|failed),
  priority (1-10),
  attempts,
  max_attempts,
  last_error,
  created_at
)

-- QUEUE_WEBHOOK_EVENTS (Inbound Lightspeed webhooks)
QUEUE_WEBHOOK_EVENTS (
  id (PK),
  webhook_type (consignment.updated, consignment.received),
  payload (JSON),
  status (pending|processing|completed|failed),
  processed_at,
  error_message,
  created_at
)
```

### C. TRANSFER_* TABLES (CIS Transfer Records)

```sql
-- TRANSFERS (CIS transfer header - NOW THE PRIMARY)
TRANSFERS (
  id (PK),
  consignment_id (FK → QUEUE_CONSIGNMENTS, nullable - links to Lightspeed),
  vend_consignment_id (VARCHAR - direct link to Lightspeed),
  vend_transfer_id (VARCHAR - old, for backwards compat),
  outlet_from (VARCHAR - source outlet UUID),
  outlet_to (VARCHAR - dest outlet UUID),
  transfer_type (CONSIGNMENT|STOCK_TRANSFER|RETURN|STOCKTAKE),
  state (OPEN|PACKING|SENT|RECEIVED|COMPLETED|CANCELLED),
  reference (VARCHAR - user reference),
  public_id (VARCHAR - public facing ID),
  created_by (INT - user_id),
  approved_by (INT - user_id),
  received_by (INT - user_id),
  approval_tier_required (STORE_MANAGER|RETAIL_OPS|DIRECTOR),
  created_at,
  approved_at,
  received_at,
  completed_at,
  updated_at
)

-- TRANSFER_ITEMS (Line items)
TRANSFER_ITEMS (
  id (PK),
  transfer_id (FK),
  vend_product_id (UUID),
  sku,
  qty_sent_total,
  qty_received_total,
  variance_reason,
  created_at
)

-- TRANSFER_STATUS_LOG (Immutable log)
TRANSFER_STATUS_LOG (
  id (PK),
  transfer_id (FK),
  from_state,
  to_state,
  reason,
  user_id,
  created_at
)

-- TRANSFER_AUDIT_LOG (Detailed audit)
TRANSFER_AUDIT_LOG (
  id (PK),
  transfer_id (FK),
  action (created|approved|received|completed),
  user_id,
  old_values (JSON),
  new_values (JSON),
  created_at
)
```

### D. STAFF_* TABLES (Staff Transfers)

```sql
-- STAFF_TRANSFERS (Staff personal item transfers)
STAFF_TRANSFERS (
  id (PK),
  from_staff_id (FK → users),
  to_staff_id (FK → users),
  from_outlet_id (UUID),
  to_outlet_id (UUID),
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
  vend_product_id (UUID),
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

### E. SIGNATURE & RECEIVING TABLES

```sql
-- RECEIPT_SIGNATURES (Signature capture)
RECEIPT_SIGNATURES (
  id (PK),
  transfer_id (FK → transfers),
  consignment_id (FK → QUEUE_CONSIGNMENTS, nullable),
  staff_id (FK → users),
  signature_type (checkbox|digital|biometric),
  signature_file_path (VARCHAR - /var/receipts/signatures/[outlet_id]/[receipt_id]/),
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

-- BARCODE_SCANS (Barcode scan history)
BARCODE_SCANS (
  id (PK),
  transfer_id (FK),
  consignment_id (FK → QUEUE_CONSIGNMENTS),
  barcode_value (VARCHAR),
  barcode_format (EAN13|UPC|Code128|CUSTOM),
  vend_product_id (UUID),
  sku,
  scan_timestamp,
  qty_scanned,
  audio_feedback (tone1|tone2|tone3|none),
  created_at
)

-- BARCODE_CONFIGURATION (Per-outlet config)
BARCODE_CONFIGURATION (
  outlet_id (FK),
  enabled (BOOLEAN),
  format_preference,
  audio_enabled (BOOLEAN),
  created_at
)
```

### F. APPROVAL & WORKFLOW TABLES

```sql
-- APPROVAL_WORKFLOW (Multi-tier approval tracking)
APPROVAL_WORKFLOW (
  id (PK),
  transfer_id (FK → transfers),
  consignment_id (FK → QUEUE_CONSIGNMENTS),
  total_amount (DECIMAL),
  approval_tier_required (STORE_MANAGER|RETAIL_OPS|DIRECTOR),
  current_approver_id (INT → users),
  status (PENDING|APPROVED|REJECTED|CANCELLED),
  notes (TEXT),
  created_at,
  completed_at
)

-- APPROVAL_AUDIT_LOG (Approval history)
APPROVAL_AUDIT_LOG (
  id (PK),
  approval_workflow_id (FK),
  action (requested|approved|rejected),
  user_id,
  reason,
  created_at
)
```

---

## 🔄 WORKFLOW (How Consignments Work)

### 1. Create Transfer (CIS)
```
User creates transfer in CIS
↓
INSERT into transfers (state='OPEN')
↓
Ready for packing/counting
```

### 2. Pack & Count (CIS)
```
Staff count items in transfer
↓
INSERT into transfer_items (qty_sent_total)
↓
UPDATE transfers (state='PACKING')
```

### 3. Submit for Approval (CIS)
```
Manager submits transfer
↓
INSERT into approval_workflow (status='PENDING')
↓
Notify appropriate approver based on tier
```

### 4. Approve (CIS)
```
Approver reviews & approves
↓
UPDATE approval_workflow (status='APPROVED')
↓
UPDATE transfers (state='READY_FOR_LIGHTSPEED', approved_by=user_id)
```

### 5. Create Consignment in Lightspeed
```
Background job triggered
↓
POST /consignments to Lightspeed
  {
    "type": "OUTLET",
    "source_outlet_id": transfer.outlet_from,
    "outlet_id": transfer.outlet_to,
    "reference": "CIS-{transfer_id}"
  }
↓
Lightspeed returns consignment UUID
↓
INSERT into queue_consignments (vend_consignment_id, transfer_id)
↓
UPDATE transfers (vend_consignment_id, consignment_id)
```

### 6. Upload Products to Lightspeed
```
Background job loops through transfer_items
↓
For each item:
  POST /consignment_products to Lightspeed
    {
      "consignment_id": vend_consignment_id,
      "product_id": vend_product_id,
      "count": qty_sent_total
    }
↓
INSERT into queue_consignment_products
```

### 7. Mark Sent (CIS)
```
User marks transfer as shipped
↓
UPDATE transfers (state='SENT')
↓
UPDATE queue_consignments (status='SENT')
↓
(Optional) PATCH consignment status in Lightspeed
```

### 8. Receive at Destination (CIS)
```
Receiving staff access receiving UI
↓
View consignment details (loaded from queue_consignments)
↓
Barcode scan products (optional audio feedback)
↓
Capture signature (checkbox + staff ID)
↓
UPDATE transfer_items (qty_received_total)
↓
UPDATE transfers (state='RECEIVED')
↓
UPDATE queue_consignments (status='RECEIVED')
↓
Trigger inventory sync in CIS
↓
(Optional) PATCH consignment status in Lightspeed
```

### 9. Complete Transfer (CIS)
```
Final inventory reconciliation
↓
UPDATE transfers (state='COMPLETED')
↓
INSERT transfer_audit_log (action='COMPLETED')
↓
Notify stakeholders
```

---

## 🎯 KEY FEATURES (From Q1-Q15)

### User Roles (5 Total)
- Director (approve $5k+)
- Comms Manager (approve $2k-$5k)
- Retail Ops Manager (approve $2k-$5k)
- Store Manager (approve $0-$2k, receive goods)
- Store Assistant (perform receiving, assist)

### Multi-Tier Approval
```
$0 - $2,000         → Store Manager approval
$2,000 - $5,000     → Retail Ops Manager OR Comms Manager approval
$5,000+             → Director ONLY
```

### Signature Capture
- ✅ Method: Checkbox + Staff ID auth
- ✅ Who: Receiving staff member
- ✅ Storage: Audit trail DB + PNG file system
- ✅ Configurable: Per outlet/supplier

### Barcode Scanning
- ✅ Optional (not mandatory)
- ✅ Any barcode format supported
- ✅ 3 audio tones: success/warning/info
- ✅ Accept any quantity (no blocking)

### Lightspeed Sync
- ✅ At RECEIVE TIME (not creation)
- ✅ Idempotent (safe to retry)
- ✅ Queue-based processing
- ✅ Error recovery

### Photos
- ✅ 5 per product
- ✅ Auto-resize to 1080p
- ✅ File system storage

### Email Notifications
- ✅ Supplier notified when consignment created
- ✅ Weekly management reports
- ✅ Store manager overview

### AI Logging (CISLogger)
- ✅ Every action logged
- ✅ Audit trail for compliance
- ✅ Entity tracking (TRANSFER, CONSIGNMENT, etc.)

---

## 🏗️ MODULE STRUCTURE

```
modules/consignments/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── TransferController          (Create/list/manage transfers)
│   │   │   ├── ConsignmentController       (View consignments)
│   │   │   ├── ReceivingController         (Receive workflow)
│   │   │   ├── ApprovalController          (Approval workflow)
│   │   │   └── BarcodeController           (Barcode scanning)
│   │   ├── Requests/
│   │   └── Middleware/
│   ├── Models/
│   │   ├── Transfer                        (inherits from BaseModel)
│   │   ├── TransferItem
│   │   ├── Consignment                     (shadow of Vend)
│   │   ├── ConsignmentProduct
│   │   ├── ApprovalWorkflow
│   │   └── Signature
│   ├── Services/
│   │   ├── TransferService                 (Create/manage transfers)
│   │   ├── ConsignmentService              (Sync with Lightspeed)
│   │   ├── LightspeedSyncService           (API integration)
│   │   ├── ApprovalService                 (Multi-tier approval)
│   │   ├── ReceivingService                (Receiving workflow)
│   │   └── AuditService                    (CISLogger integration)
│   ├── Jobs/
│   │   ├── CreateConsignmentJob            (BG job)
│   │   ├── SyncToLightspeedJob             (BG job)
│   │   ├── ProcessWebhookJob               (BG job)
│   │   └── GenerateReportJob               (BG job)
│   ├── Traits/
│   │   ├── HasApprovalWorkflow
│   │   ├── HasAuditLog
│   │   ├── HasStatusTransitions
│   │   └── HasDraftStatus
│   └── Exceptions/
│       ├── ApprovalRequiredException
│       ├── LightspeedSyncException
│       └── ValidationException
├── database/
│   ├── migrations/
│   │   ├── create_transfers_table.php
│   │   ├── create_queue_consignments_table.php
│   │   ├── create_approval_workflow_table.php
│   │   └── create_signature_table.php
│   └── seeders/
│       └── TransferSeeder.php
├── routes/
│   ├── api.php                             (JSON API)
│   ├── web.php                             (Web UI)
│   └── webhook.php                         (Lightspeed webhooks)
├── resources/
│   ├── views/
│   │   ├── transfers/
│   │   │   ├── create.blade.php
│   │   │   ├── list.blade.php
│   │   │   └── detail.blade.php
│   │   ├── receiving/
│   │   │   ├── receiving-ui.blade.php
│   │   │   ├── barcode-scanner.blade.php
│   │   │   └── signature-capture.blade.php
│   │   └── approvals/
│   │       ├── pending.blade.php
│   │       └── history.blade.php
│   ├── css/
│   │   ├── consignments.css
│   │   ├── receiving.css
│   │   └── barcode.css
│   └── js/
│       ├── consignments.js
│       ├── barcode-scanner.js
│       ├── signature-capture.js
│       ├── real-time-updates.js
│       └── audio-feedback.js
├── config/
│   ├── module.php
│   ├── approval.php
│   ├── lightspeed.php
│   └── barcode.php
├── tests/
│   ├── Unit/
│   │   ├── ApprovalTest.php
│   │   ├── TransferTest.php
│   │   └── LightspeedSyncTest.php
│   ├── Feature/
│   │   ├── TransferWorkflowTest.php
│   │   ├── ReceivingTest.php
│   │   └── ApprovalTest.php
│   └── Integration/
│       ├── LightspeedIntegrationTest.php
│       ├── WebhookHandlingTest.php
│       └── InventorySyncTest.php
├── _kb/
│   ├── README.md
│   ├── ARCHITECTURE.md
│   ├── LIGHTSPEED_INTEGRATION.md
│   ├── WORKFLOW.md
│   └── PEARCE_ANSWERS_SESSION_1.md
└── bootstrap.php
```

---

## ✅ WHAT TO BUILD

### Phase 1: Core Models & Database
- [ ] Create QUEUE_CONSIGNMENTS table
- [ ] Create TRANSFERS table (linking to consignments)
- [ ] Create TRANSFER_ITEMS table
- [ ] Create APPROVAL_WORKFLOW table
- [ ] Create migrations (idempotent)

### Phase 2: Transfer Management
- [ ] TransferController (create, list, show)
- [ ] TransferService (business logic)
- [ ] Transfer models with relationships
- [ ] Tests for transfer lifecycle

### Phase 3: Approval System
- [ ] ApprovalController (approve/reject)
- [ ] ApprovalService (multi-tier logic)
- [ ] Approval models
- [ ] Tests for approval workflows

### Phase 4: Lightspeed Integration
- [ ] LightspeedSyncService (API calls)
- [ ] ConsignmentService (Lightspeed shadow)
- [ ] Background jobs (queue processing)
- [ ] Webhook handlers (inbound events)
- [ ] Tests for Lightspeed sync

### Phase 5: Receiving Workflow
- [ ] ReceivingController (UI & logic)
- [ ] ReceivingService (business logic)
- [ ] Barcode scanning integration
- [ ] Signature capture
- [ ] Photo upload
- [ ] Tests for receiving flow

### Phase 6: AI Logging
- [ ] CISLogger integration (all actions)
- [ ] Audit trail generation
- [ ] Compliance tracking

### Phase 7: Testing & Docs
- [ ] Unit tests (95%+ coverage)
- [ ] Integration tests
- [ ] API documentation
- [ ] Module documentation

---

## 🚀 NEXT STEPS

1. **Continue Gap Analysis** (Q16-Q35)
2. **Setup Base Module** (Inheritance template)
3. **Build Consignments Module** (With all features)

---

**END OF CORRECTED BRIEF**

Key Difference: **NO separate PO_* tables.** All managed through Lightspeed's native consignment model with CIS shadow tables.
