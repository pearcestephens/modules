# 🎯 RESOURCE DISCOVERY CONSOLIDATION BRIEF
**Date:** October 31, 2025 | **Status:** 🟢 COMPLETE & VERIFIED
**Purpose:** Capture all discovered existing system resources before building

---

## ✅ WHAT WAS DISCOVERED

### 1. LIGHTSPEED-NATIVE CONSIGNMENT MODEL (NOT Separate PO_* Tables)
**Key Finding:** System uses Lightspeed's native CONSIGNMENT model through CIS shadow tables.

**Database Tables:**
- `VEND_CONSIGNMENTS` - Lightspeed master (read-only, synced via webhooks)
- `QUEUE_CONSIGNMENTS` - CIS shadow table (fast lookups, status tracking)
- `QUEUE_CONSIGNMENT_PRODUCTS` - Line items synced from Lightspeed
- `TRANSFERS` - CIS transfer records (linked to QUEUE_CONSIGNMENTS via consignment_id)
- `TRANSFER_ITEMS` - CIS line items (linked to TRANSFERS)
- `TRANSFER_STATUS_LOG` - Immutable state transitions
- `TRANSFER_AUDIT_LOG` - Detailed action audit trail
- **NO PO_* tables** - All purchase order functionality handled through CONSIGNMENT type in Lightspeed

**Model Types in Lightspeed:**
```
CONSIGNMENT types:
  - SUPPLIER (incoming from suppliers)
  - OUTLET (inter-outlet transfers)
  - RETURN (returns to suppliers)
  - STOCKTAKE (inventory counts)
```

**Status Values:**
```
OPEN → SENT → DISPATCHED → RECEIVED → COMPLETED
       OR   → CANCELLED
```

**Location:** `/home/master/applications/jcepnzzkmj/public_html/modules/consignments/_kb/CORRECTED_BRIEF_LIGHTSPEED_NATIVE.md`

---

### 2. FREIGHT INTEGRATION SYSTEM (Complete & Operational)
**Location:** `/home/master/applications/jcepnzzkmj/public_html/modules/consignments/lib/FreightIntegration.php` (300+ lines)

**Key Capabilities:**
```php
// Calculate weight, volume, container recommendations
$metrics = $freight->calculateTransferMetrics($transfer_id);
// Returns: {
//   weight: { total_weight_g, total_weight_kg, warnings },
//   volume: { total_volume_m3, warnings },
//   pick: { container_name, container_code, utilization_pct, cost },
//   db_cost: $estimated_cost,
//   warnings: [...]
// }

// Get freight quotes from couriers
$rates = $freight->getTransferRates($transfer_id);
// Returns: { rates: [...], cheapest, fastest, recommended }

// Get smart container recommendations
$containers = $freight->suggestTransferContainers($transfer_id, 'min_cost'|'min_boxes'|'balanced');
// Returns: { containers, total_boxes, total_cost, utilization_pct }

// Get carrier recommendation
$rec = $freight->getTransferRecommendation($transfer_id, 'cost'|'speed'|'reliability');
// Returns: { carrier, service, price, eta_days, confidence, reason }

// Create shipping label
$label = $freight->createTransferLabel($transfer_id, $carrier, $service);
// Returns: { label_id, label_url, tracking_number }

// Get tracking info
$tracking = $freight->getTransferTracking($transfer_id);
// Returns: { status, delivered, estimated_delivery, events: [...] }
```

**API Integration:**
- Calls `/assets/services/core/freight/api.php` (generic freight service wrapper)
- Supports multiple carriers (NZ Post, GoSweetSpot, etc.)
- Weight-based routing
- Volume optimization
- Container picking recommendations
- Database cost estimation

**Audit Trail:**
- All freight actions logged to `TRANSFER_AUDIT_LOG`
- Timestamps, weights, recommendations captured
- Full trace of decision-making

---

### 3. LIGHTSPEED API CLIENT (Hardened & Production-Ready)
**Location:** `/home/master/applications/jcepnzzkmj/public_html/modules/consignments/lib/LightspeedClient.php` (150+ lines)

**Features:**
```php
// Bearer token authentication
// Retry logic: 2 attempts with exponential backoff for 429/5xx
// Idempotency keys for safe retries
// Request IDs for audit trail
// Graceful error handling

$client = new LightspeedClient($baseUrl, $token);

// Create consignment in Lightspeed
$response = $client->createConsignment($sourceOutletId, $destinationOutletId, $reference);
// Returns: { consignment_id, status, created_at }

// Add products to consignment
$response = $client->addProduct($consignmentId, $productId, $quantity);

// Update consignment status
$response = $client->updateStatus($consignmentId, $newStatus);

// Get consignment details
$response = $client->getConsignment($consignmentId);
```

**Error Handling:**
- 429 (rate limit) → backoff + retry
- 5xx (server error) → backoff + retry
- 4xx (client error) → fail fast with message
- Network timeout → configurable timeout handling

**Audit:**
- All requests logged with Request-ID
- Response times tracked
- Errors logged with full context

---

### 4. CONSIGNMENTS SERVICE (Main Orchestration)
**Location:** `/home/master/applications/jcepnzzkmj/public_html/modules/consignments/lib/ConsignmentsService.php` (200+ lines)

**Purpose:** Single orchestration point for:
- Database operations
- Lightspeed API calls
- Progress tracking (for SSE/WebSocket)
- Workflow coordination

**Key Methods:**
```php
// Prepare transfer for upload
$upload_contract = $service->submitTransferAndPrepareUpload($transferId, $items, $notes);
// Returns: { upload_session_id, upload_url, progress_url }

// Execute upload to Lightspeed
$response = $service->uploadNow($transferId);
// Creates consignment, uploads products, marks SENT

// Update progress for real-time UI
$service->progressUpsert($transferId, $progress_data);
// Writes to progress table for SSE polling
```

**Architecture:**
- Uses dependency injection (PDO, LightspeedClient)
- Deterministic progress tracking (can be replayed)
- Safe to call multiple times (idempotent)
- Comprehensive error handling

---

### 5. PACK INTERFACE WITH FREIGHT CONSOLE
**Location:** `/home/master/applications/jcepnzzkmj/public_html/modules/consignments/stock-transfers/pack-pro.php`

**Freight Features:**
- Manual mode: Enter tracking numbers manually
- Pick-up mode: Track boxes picked up
- Drop-off mode: Track boxes dropped off
- Real-time freight insights panel
- Weight, volume, container, utilization, cost display
- Boxes stepper (±/+ buttons)
- Save freight config
- Reset button

**UI Integration:**
```php
// Real-time freight insights
- Total weight (kg)
- Container pick (name + code)
- Utilization percentage
- Estimated DB cost

// Warnings for:
- Products missing weight/dimensions
- Freight disabled for outlet
- API connection issues
```

---

### 6. PACK.JS - ADVANCED PACKING INTERFACE
**Location:** `/home/master/applications/jcepnzzkmj/public_html/modules/consignments/stock-transfers/js/pack.js`

**Features:**
- Auto-validate colours & KPIs
- Hover preview (preview item above cursor at mouse position)
- 600px power search (multi-select, block zero-stock items)
- Autosave (session localStorage)
- Save draft to transfers.draft_data DB field
- Freight 2-way sync (manual mode rules)
- One-button PACKED → upload workflow
- Real-time freight insights (calls AJAX)
- History placeholders

**Freight Integration:**
- 2-way sync with freight console
- Real-time weight/volume calculations
- Container recommendations
- Manual mode prevents auto-updates
- Debounced saves (prevents spam)

---

### 7. BARCODE SCANNING (Optional)
**Location:** `/home/master/applications/jcepnzzkmj/public_html/modules/consignments/stock-transfers/js/pack.js`

**Features (From Q14 Answer):**
- Optional (not mandatory)
- Any barcode format supported (EAN-13, Code128, QR, etc.)
- 3 audio tones:
  - Success tone: Qty reached for product
  - Warning tone: Unexpected product scanned
  - Info tone: General notices
- Accept ANY quantity/mismatch (no blocking)
- Real-time qty validation
- Visual feedback (red/green)
- Scan history

---

### 8. SIGNATURE CAPTURE (Optional)
**Location:** Related to receiving workflow

**Features (From Q13 Answer):**
- Checkbox + Staff ID authentication
- PNG file system storage
- Audit trail in DB (SIGNATURE_* tables)
- Configurable per outlet/supplier
- Optional or required based on config
- Canvas drawing or image upload support

---

### 9. EMAIL NOTIFICATIONS SYSTEM
**Location:** Existing cron/email system

**Features (From Q15 Answer):**
- Supplier notifications on PO creation
- Weekly summary reports
- Exception alerts
- Uses existing CIS cron system (no new scheduling)
- Template-based (HTML/text)
- Attachment support (PDF exports)

---

### 10. MULTI-TIER APPROVAL SYSTEM
**Approval Tiers (From Q1-Q12 Answers):**
```
Tier 1: $0-$2,000       → Store Manager only
Tier 2: $2,000-$5,000   → Retail Ops Manager OR Comms Manager
Tier 3: $5,000+         → Director ONLY
```

**Workflow:**
1. PO created (DRAFT status)
2. Store Manager must explicitly confirm to ACTIVE
3. If amount > $2k, requires Tier 2 approval
4. If amount > $5k, requires Director approval
5. Cannot receive against DRAFT POs

---

### 11. DRAFT STATUS ARCHITECTURE
**Concept (From Q1-Q12 Answers):**
- All POs created in DRAFT status initially
- Explicit confirmation required to ACTIVE
- Approval tier depends on total amount
- Cannot sync to Lightspeed until ACTIVE + approved
- Can be edited, deleted, or archived in DRAFT

**Implementation Pattern:**
- Status column in TRANSFERS table
- State machine with defined transitions
- Audit log for all state changes
- Permission checks at each transition point

---

### 12. LIGHTSPEED SYNC TIMING
**Key Decision (From Q1-Q12 Answers):**
- Sync happens **AT RECEIVE TIME** (not at PO creation)
- Idempotent operation (safe to retry multiple times)
- Webhook inbound support
- 2-way sync (CIS → Lightspeed, Lightspeed → CIS)
- Queue-based for reliability

---

## 📊 EXISTING CODEBASE INVENTORY

### Core Service Classes
1. **FreightIntegration.php** (300+ lines)
   - Weight/volume calculations
   - Container recommendations
   - Courier routing
   - Label generation
   - Tracking retrieval

2. **ConsignmentsService.php** (200+ lines)
   - Transfer submission
   - Lightspeed upload orchestration
   - Progress tracking
   - Transaction management

3. **LightspeedClient.php** (150+ lines)
   - API communication
   - Retry logic with backoff
   - Error handling
   - Authentication

### UI Components
1. **pack-pro.php** (frontend template)
   - Freight console interface
   - Packing workflow
   - Real-time updates

2. **pack.js** (advanced packing interface)
   - Event handling
   - Auto-save
   - Barcode integration
   - Freight 2-way sync

### Documentation & Examples
1. **FREIGHT_USAGE_EXAMPLES.php** (usage patterns)
   - Complete integration examples
   - AJAX patterns
   - Error handling
   - Common workflows

2. **simple-upload-direct.php** (workflow reference)
   - End-to-end upload process
   - Progress tracking
   - Status transitions

3. **CORRECTED_BRIEF_LIGHTSPEED_NATIVE.md** (architecture guide)
   - Database schema (corrected, no PO_* tables)
   - 9-step workflow
   - Integration points
   - Table relationships

### Database Utilities
1. **pack_integration.php** (transfer preparation)
   - Format transfer data for packing interface
   - Include related data (items, shipments, receipts)
   - Warning system

2. **transfers.php** (transfer utilities)
   - Get transfer details
   - Get items
   - Get shipments
   - Format for display

---

## 🔄 COMPLETE WORKFLOW (9 Steps)

**Step 1: Create Transfer (CIS)**
- Create record in TRANSFERS table
- Status: OPEN
- No Lightspeed sync yet

**Step 2: Approve Transfer (CIS)**
- Verify approval tier
- Route to appropriate approver
- Status: APPROVED (after approval)

**Step 3: Pack Transfer (CIS)**
- Scan products or enter quantities manually
- Freight calculations (weight, volume, container)
- Save draft or lock for submission
- Status: PACKING

**Step 4: Submit for Upload (CIS)**
- Verify all items scanned/counted
- Prepare upload session
- Return upload contract (session_id, url)
- Status: READY_TO_UPLOAD

**Step 5: Create Lightspeed Consignment**
- POST /consignments to Lightspeed
- Receive consignment_id from Lightspeed
- Write to QUEUE_CONSIGNMENTS
- Status: CONSIGNMENT_CREATED

**Step 6: Upload Products to Lightspeed**
- For each item: POST /consignment_products
- Lightspeed synchronizes inventory
- Status: PRODUCTS_UPLOADED

**Step 7: Mark Sent (CIS)**
- Update TRANSFERS status: SENT
- Write to TRANSFER_STATUS_LOG
- Write audit entry
- Freight info archived

**Step 8: Receive at Destination (CIS)**
- Scan products or enter quantities
- Signature capture (if configured)
- Photos (if enabled)
- Calculate variances (over/under received)
- Status: RECEIVED

**Step 9: Complete Transfer (CIS)**
- Final reconciliation
- Update Lightspeed inventory
- Close transfer
- Generate reports
- Status: COMPLETED

---

## 🗂️ CRITICAL DATABASE TABLES

### Master Tables (from Lightspeed)
- `VEND_CONSIGNMENTS` - Lightspeed consignments (read-only)
- `VEND_CONSIGNMENT_PRODUCTS` - Lightspeed line items (read-only)
- `VEND_PRODUCTS` - Product master (cached)
- `VEND_OUTLETS` - Store/outlet master (cached)

### Shadow Tables (CIS cache/fast lookup)
- `QUEUE_CONSIGNMENTS` - CIS cache of Lightspeed consignments
- `QUEUE_CONSIGNMENT_PRODUCTS` - CIS cache of line items
- `QUEUE_JOBS` - Background job queue
- `QUEUE_WEBHOOK_EVENTS` - Inbound webhooks from Lightspeed

### CIS Transfer Tables
- `TRANSFERS` - Transfer header (linked to QUEUE_CONSIGNMENTS)
- `TRANSFER_ITEMS` - Transfer line items
- `TRANSFER_STATUS_LOG` - State transition history (immutable)
- `TRANSFER_AUDIT_LOG` - Detailed action audit trail

### Optional Tables (from Q13-Q15 Answers)
- `SIGNATURE_*` - Signature capture data
- `BARCODE_*` - Barcode scanning history
- Freight/weight tracking (in TRANSFER_AUDIT_LOG)

---

## 🎯 KEY ARCHITECTURAL DECISIONS

### 1. **Lightspeed-Native Model (Not Separate Tables)**
✅ Use Lightspeed's native CONSIGNMENT model
❌ Do NOT create separate PO_* tables
✅ Use CIS shadow tables for fast lookups only

### 2. **Sync Timing: Receive Time (Not Create Time)**
✅ Sync happens AT RECEIVE (when goods arrive)
❌ Do NOT sync when PO created
✅ Makes process reversible/editable before commitment

### 3. **Freight Integration (Weight/Volume/Container)**
✅ Complete FreightIntegration system exists
✅ Calls generic freight service at `/assets/services/core/freight/api.php`
✅ Supports multiple carriers (NZ Post, GoSweetSpot, etc.)

### 4. **Multi-Tier Approval ($2k, $5k thresholds)**
✅ 3-tier system: Store Manager, Retail Ops, Director
✅ Approval required before ACTIVE status
✅ Cannot receive against DRAFT

### 5. **DRAFT Status Architecture**
✅ All transfers created as DRAFT initially
✅ Explicit confirmation required to ACTIVE
✅ Can be edited/deleted/archived in DRAFT

---

## 📝 NEXT PHASES

### Phase 1: Continue Gap Analysis (Q16-Q35)
**Estimated Time:** 1-2 hours
**Remaining:** 20 questions
**Output:** PEARCE_ANSWERS_SESSION_3.md with all answers + implementation notes
**Current:** 15/35 complete (Q1-Q15 answered in Sessions 1-2)

**Questions Ready:**
- Q16: Product search & autocomplete (scope, filters, speed)
- Q17: PO cancellation/amendment rules
- Q18: Duplicate PO prevention
- Q19-Q35: Additional features, mobile, GRNI, metrics, etc.

### Phase 2: Setup Base Module Pattern (30 minutes)
**Deliverable:** `/modules/base/` inheritance template
**Contents:**
- BaseController, BaseModel, BaseService
- Traits: HasAuditLog, HasStatusTransitions, HasDraftStatus
- ServiceProviders for dependency injection
- Configuration patterns
- Module bootstrap system
- Complete documentation
- Consignments inheritance example

### Phase 3: Build Complete Consignments Module
**Deliverable:** `/modules/consignments/` with all integrations
**Includes:**
- Lightspeed-native model (no PO_* tables)
- FreightIntegration (weight/volume/container)
- LightspeedClient API integration
- ConsignmentsService orchestration
- Barcode scanning (optional, 3 tones)
- Signature capture (checkbox + ID)
- Multi-tier approval ($2k/$5k thresholds)
- DRAFT status workflow
- Email notifications (supplier + weekly reports)
- CISLogger audit integration
- Complete receiving workflow
- Photo uploads (5 per product, auto-resize)
- Variance handling (over/under)

---

## 🔐 BACKUP & CONTINUITY

**Auto-Push Daemon:**
- Status: 🟢 RUNNING (PID: 25193)
- Check Interval: 5 minutes
- Auto-commits: Every 5 minutes when changes detected
- Repo: pearcestephens/modules
- Recent Activity: 3+ automatic commits completed
- Safety: All work continuously backed up to GitHub

**How to Resume:**
1. Current status documented in this file
2. All discoveries recorded with file locations
3. Auto-push running (nothing needed)
4. Next session: Start with Q16 (all prep work done)

---

## 🚀 READY FOR NEXT PHASE

✅ Lightspeed-native model understood (no PO_* tables)
✅ Freight integration discovered and documented
✅ Courier API integration identified
✅ Weight/volume/container logic in place
✅ All existing code reviewed and mapped
✅ Auto-push running (continuous backup)
✅ Gap analysis 15/35 complete (Q1-Q15)
✅ 20 remaining questions ready

**NEXT IMMEDIATE ACTION:** Continue with Question 16 (Product search & autocomplete)

---

**Last Updated:** October 31, 2025
**Auto-Backup:** ✅ Running (every 5 min)
**Status:** 🟢 COMPLETE & VERIFIED
