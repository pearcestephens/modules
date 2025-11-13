# 🚀 COMPREHENSIVE CODING AGENT BRIEF
## Complete Consignments Module Implementation

**Date:** October 31, 2025
**Status:** Ready for Autonomous Coding Agent
**Scope:** ENTIRE Consignments Module - All Features, All Transfer Types
**Priority:** CRITICAL

---

## 📋 EXECUTIVE SUMMARY

Build a **complete, production-ready Consignments Module** that handles:
- ✅ All purchase order workflows (DRAFT → ACTIVE → RECEIVED → COMPLETED)
- ✅ All 4 transfer types (Consignments, Stock Transfers, Staff Transfers, Inter-Outlet)
- ✅ Complete receiving workflow with signature capture, barcode scanning, photo uploads
- ✅ Multi-tier approval system ($2k/$2k-$5k/$5k+ tiers)
- ✅ Lightspeed Retail integration at receive time
- ✅ AI Logging via CISLogger for all actions
- ✅ Real-time inventory synchronization
- ✅ Supplier communication system
- ✅ Complete audit trail

**Result:** Fully functional, tested, documented, and deployed module ready for production use.

---

## 🎯 CORE REQUIREMENTS

### A. TRANSFER TYPES (All 4 Must Be Supported)

#### 1. **Consignments** (Supplier Consignment)
- Supplier sends goods on consignment (not yet paid)
- Outlet receives goods into "Consignment Stock" bin
- Stock remains supplier property until sold/confirmed
- When sold: Convert to owned stock + trigger payment
- Database: `vend_consignments`, `consignment_items`, `consignment_status_log`

#### 2. **Stock Transfers** (Inter-Outlet or HQ→Outlet)
- Transfer stock between outlets or from HQ to outlet
- Source outlet: Reduces inventory
- Destination outlet: Increases inventory
- Real-time sync across outlets
- Database: `stock_transfers`, `stock_transfer_items`, `transfer_status_log`

#### 3. **Staff Transfers** (Staff Personal Stock)
- Individual staff member transfers personal vape gear
- Tracked at staff_id level (not outlet level)
- Audit trail: Who transferred what to whom and when
- Database: `staff_transfers`, `staff_transfer_items`, `staff_transfer_log`

#### 4. **Purchase Orders (PO)** (Supplier Orders)
- Create PO for supplier
- Status progression: DRAFT → ACTIVE → AWAITING_RECEIPT → RECEIVED → COMPLETED
- Multi-tier approval based on amount
- Receive goods against PO
- Database: `purchase_orders`, `po_items`, `po_status_log`

---

### B. BUSINESS LOGIC (From Q1-Q15 Answers)

#### User Roles & Permissions (5 Total)
1. **Director** - Can approve all POs, override any approval
2. **Comms Manager** - Can approve POs $2k-$5k
3. **Retail Ops Manager** - Can approve POs $2k-$5k
4. **Store Manager** - Can approve POs $0-$2k, receive goods
5. **Store Assistant** - Can perform receiving, assist with POs

#### Multi-Tier Approval System
```
PO Amount Range          Approval Required By
$0 - $2,000             Store Manager
$2,000 - $5,000         Retail Ops Manager OR Comms Manager
$5,000+                 Director ONLY
```

#### DRAFT Status Architecture ⭐ CRITICAL
- **POs created as DRAFT initially** (not ACTIVE)
- Must be explicitly confirmed/activated by authorized user
- DRAFT → ACTIVE transition requires approval
- Workflow:
  1. User creates PO (status = DRAFT)
  2. System calculates required approval tier
  3. Notifies appropriate approver
  4. Approver reviews & confirms
  5. Status becomes ACTIVE
  6. Supplier notified
  7. Can receive against ACTIVE PO only

#### Lightspeed Sync Timing
- **Sync happens AT RECEIVE TIME** (not at PO creation)
- Real product/quantity data available only when goods physically received
- Sync includes: Product list, quantities, pricing updates
- Database event: `po_status_log` with 'lightspeed_synced' action
- Idempotent: Can sync multiple times, same result

#### Signature Capture
- **Method:** Checkbox + Staff ID authentication
- **Who signs:** Receiving staff member only
- **Storage:** Audit trail (DB) + PNG file system
- **Required:** Depends on outlet/supplier + configurable
- **Config flags:** `outlets.signature_required`, `suppliers.signature_required`
- **Audit table:** `signature_audit_log` (receipt_id, staff_id, timestamp, file_path)

#### Barcode Scanning
- **Requirement:** Optional (not mandatory)
- **Formats:** Support ANY barcode type (EAN-13, UPC, Code128, custom)
- **Verification Logic:** Accept ANY quantity/value (no blocking)
- **Audio Feedback:**
  - Tone 1: When reaching expected QTY (success)
  - Tone 2: Unexpected product (alert)
  - Tone 3: Warnings/info (gentle)
- **UI:** Dual-mode (manual entry + barcode scanning)
- **Config:** `outlets.barcode_scanning_enabled`

#### Email Notifications
- **Suppliers:** Receive email when PO becomes ACTIVE
- **Management:** Weekly automated overview report (Monday 6 AM or on-demand)
- **Store Manager:** Included in management report
- **System:** Leverage existing cron system (has many options)
- **Content:** PO status, receipts, exceptions, metrics
- **Flexibility:** Template customization per outlet

#### Over-Receipt Handling
- **Default:** Auto-accept any over-receipt (no blocking)
- **Behavior:** Flag for review but allow continuation
- **Notification:** Alert (not warning) sent to manager
- **No approval needed:** Auto-proceed with flagged status

#### Partial Deliveries
- **Allowed:** Unlimited partial deliveries per PO
- **Workflow:** Can receive partial qty, repeat multiple times
- **Status:** PO stays AWAITING_RECEIPT until 100% received
- **Flexibility:** Supplier can split shipments, outlet can receive incrementally

#### Freight Handling
- **Optional:** Not required
- **Per Outlet:** Can enable/disable per outlet
- **Source:** From invoice (not user-entered)
- **Format:** Flexible (text field, supports various formats)
- **Storage:** In PO receiving record, part of audit trail

#### Invoice Management
- **Storage:** File system (not database blob)
- **Retention:** 1 year retention policy
- **Format:** Accepted formats (PDF, images, etc.)
- **Matching:** Flag any PO ↔ Invoice mismatch
- **Approval:** Staff must approve/reject mismatches manually
- **Path:** `/invoices/[year]/[outlet_id]/[po_number]/`

#### Supplier Claims
- **Auto-generation:** Automatically create as DRAFT claim when issues found
- **Trigger:** Over-receipt, missing items, damaged goods, pricing discrepancies
- **Status:** DRAFT requires management approval before sending
- **Approval:** Management team must validate before sending to supplier
- **Tracking:** Full audit log of claim lifecycle

#### Product Photos
- **Quantity:** 5 photos per product (during receiving)
- **Auto-resize:** To 1080p max dimension
- **Format:** JPEG or PNG
- **Storage:** File system `/photos/products/[outlet_id]/[po_id]/[product_id]/`
- **UI:** Gallery view, can rotate/reorder
- **Optional:** Not required, but captured when available

---

### C. DATABASE SCHEMA REQUIREMENTS

#### Core Tables (Must Exist & Be Properly Related)

**purchase_orders**
```sql
id (PK)
po_number (unique per outlet)
outlet_id (FK → vend_outlets)
supplier_id (FK → vend_suppliers)
status (DRAFT|ACTIVE|AWAITING_RECEIPT|RECEIVED|COMPLETED|CANCELLED)
total_amount (decimal)
approval_required_tier (0|1|2) -- 0=$2k, 1=$2k-$5k, 2=$5k+
approved_by (FK → users, nullable until ACTIVE)
created_by (FK → users)
created_at
draft_confirmed_at (when DRAFT → ACTIVE)
lightspeed_synced_at (when synced to Lightspeed)
completed_at
notes
config_flags (JSON: signature_required, barcode_enabled, etc.)
```

**po_items**
```sql
id (PK)
po_id (FK → purchase_orders)
product_id (FK → vend_products)
sku
ordered_quantity
received_quantity
over_receipt_quantity
unit_price
line_total
notes
received_at (when fully received)
```

**po_status_log** (Audit Trail)
```sql
id (PK)
po_id (FK → purchase_orders)
status_from
status_to
changed_by (FK → users)
changed_at
action (created|drafted|activated|received|completed|cancelled|lightspeed_synced)
notes
```

**consignments**
```sql
id (PK)
consignment_number
supplier_id (FK → vend_suppliers)
outlet_id (FK → vend_outlets)
status (AWAITING_RECEIPT|RECEIVED|CONFIRMED|COMPLETED|CANCELLED)
total_value
created_by (FK → users)
created_at
received_at
confirmed_at (when converted to owned stock)
completed_at
```

**consignment_items**
```sql
id (PK)
consignment_id (FK → consignments)
product_id (FK → vend_products)
quantity_sent
quantity_received
quantity_sold (when converted to owned)
status
```

**stock_transfers**
```sql
id (PK)
transfer_number
source_outlet_id (FK → vend_outlets)
destination_outlet_id (FK → vend_outlets)
status (DRAFT|ACTIVE|IN_TRANSIT|RECEIVED|COMPLETED|CANCELLED)
created_by (FK → users)
approved_by (FK → users)
created_at
sent_at
received_at
completed_at
```

**stock_transfer_items**
```sql
id (PK)
transfer_id (FK → stock_transfers)
product_id (FK → vend_products)
quantity_sent
quantity_received
unit_price
```

**staff_transfers**
```sql
id (PK)
transfer_number
from_staff_id (FK → users)
to_staff_id (FK → users)
from_outlet_id (FK → vend_outlets)
to_outlet_id (FK → vend_outlets)
status (DRAFT|CONFIRMED|COMPLETED)
created_by (FK → users)
created_at
confirmed_at
completed_at
notes
```

**staff_transfer_items**
```sql
id (PK)
transfer_id (FK → staff_transfers)
product_id (FK → vend_products)
quantity
serial_number (if applicable)
condition_notes
```

**signature_audit_log**
```sql
id (PK)
receipt_id (FK → po_id, consignment_id, or transfer_id)
receipt_type (purchase_order|consignment|stock_transfer|staff_transfer)
staff_id (FK → users)
outlet_id (FK → vend_outlets)
action (signed|verified|rejected)
signature_file_path
created_at
notes
```

**po_receiving** (Receiving Session)
```sql
id (PK)
po_id (FK → purchase_orders)
receiving_staff_id (FK → users)
outlet_id (FK → vend_outlets)
started_at
completed_at
signature_required
signature_captured
signature_file
barcode_scanning_enabled
freight_captured
freight_text
invoice_file_path
invoice_status (pending_match|matched|mismatched|approved|rejected)
notes
status (in_progress|completed|cancelled)
```

**po_photos** (Photos Captured During Receiving)
```sql
id (PK)
po_id (FK → purchase_orders)
po_item_id (FK → po_items, nullable)
receipt_id (FK → po_receiving)
product_id (FK → vend_products)
photo_filename
photo_path
photo_size_original
photo_size_optimized (1080p)
captured_at
captured_by (FK → users)
notes
```

**Related Existing Tables (Must Be Connected)**
- `vend_products` (products in inventory)
- `vend_outlets` (store locations)
- `vend_suppliers` (supplier master)
- `vend_inventory` (stock levels)
- `vend_sales` (sales transactions)
- `users` (staff members)

---

### D. CRISLOGGER INTEGRATION (AI Logging)

**Every significant action must be logged via CISLogger:**

```php
// Example: When PO is created
CISLogger::log([
    'entity_type' => 'purchase_order',
    'entity_id' => $po->id,
    'action' => 'created',
    'actor_id' => auth()->user()->id,
    'actor_role' => auth()->user()->role,
    'details' => [
        'po_number' => $po->po_number,
        'supplier_id' => $po->supplier_id,
        'outlet_id' => $po->outlet_id,
        'total_amount' => $po->total_amount,
        'approval_tier' => $po->approval_required_tier,
        'status' => $po->status,
    ],
    'timestamp' => now(),
    'ip_address' => request()->ip(),
]);

// Example: When PO status changes to ACTIVE
CISLogger::log([
    'entity_type' => 'purchase_order',
    'entity_id' => $po->id,
    'action' => 'status_changed',
    'status_from' => 'DRAFT',
    'status_to' => 'ACTIVE',
    'actor_id' => $approver->id,
    'reason' => 'PO Approved',
    'details' => [
        'approval_tier' => $po->approval_required_tier,
        'approver_role' => $approver->role,
    ],
]);

// Example: When goods are received
CISLogger::log([
    'entity_type' => 'po_receiving',
    'entity_id' => $receiving->id,
    'action' => 'goods_received',
    'actor_id' => $receiving_staff->id,
    'details' => [
        'po_id' => $po->id,
        'items_received' => count($receiving_items),
        'over_receipt_count' => count($over_receipts),
        'photos_captured' => $photo_count,
        'signature_captured' => $receiving->signature_captured,
        'barcode_scans' => $barcode_scan_count,
    ],
]);

// Example: When Lightspeed is synced
CISLogger::log([
    'entity_type' => 'purchase_order',
    'entity_id' => $po->id,
    'action' => 'lightspeed_synced',
    'system' => 'lightspeed_retail',
    'details' => [
        'items_synced' => count($po_items),
        'sync_duration_ms' => $duration,
        'sync_status' => 'success',
    ],
]);
```

**Required Logging Events:**
- PO created (DRAFT)
- PO activated (DRAFT → ACTIVE)
- PO approved (by approver)
- Receiving started
- Items received (per item)
- Over-receipts flagged
- Photos captured
- Barcode scans
- Signatures captured
- Invoices matched/mismatched
- Lightspeed sync triggered/completed
- Supplier claims created
- Status changes
- Deletions/cancellations
- All approval actions
- All exceptions/errors

---

### E. API ENDPOINTS (Must Be RESTful)

#### Purchase Orders
- `POST /api/v1/purchase-orders` - Create PO (DRAFT)
- `GET /api/v1/purchase-orders` - List POs
- `GET /api/v1/purchase-orders/{id}` - Get single PO
- `PUT /api/v1/purchase-orders/{id}` - Update PO (only DRAFT)
- `POST /api/v1/purchase-orders/{id}/activate` - Activate PO (DRAFT→ACTIVE)
- `POST /api/v1/purchase-orders/{id}/receive` - Start receiving
- `POST /api/v1/purchase-orders/{id}/complete` - Mark complete
- `DELETE /api/v1/purchase-orders/{id}` - Cancel PO

#### Receiving
- `POST /api/v1/po/{id}/receiving/start` - Start receiving session
- `POST /api/v1/po/{id}/receiving/items` - Record received items
- `POST /api/v1/po/{id}/receiving/signature` - Capture signature
- `POST /api/v1/po/{id}/receiving/photos` - Upload photos
- `POST /api/v1/po/{id}/receiving/invoice` - Upload invoice
- `POST /api/v1/po/{id}/receiving/complete` - Complete receiving

#### Lightspeed Integration
- `POST /api/v1/lightspeed/sync/{po_id}` - Sync to Lightspeed
- `GET /api/v1/lightspeed/sync-status/{po_id}` - Check sync status

#### Stock Transfers
- `POST /api/v1/stock-transfers` - Create transfer
- `POST /api/v1/stock-transfers/{id}/send` - Send transfer
- `POST /api/v1/stock-transfers/{id}/receive` - Receive transfer
- `GET /api/v1/stock-transfers` - List transfers

#### Staff Transfers
- `POST /api/v1/staff-transfers` - Create transfer
- `POST /api/v1/staff-transfers/{id}/confirm` - Confirm transfer
- `GET /api/v1/staff-transfers` - List transfers

#### Consignments
- `POST /api/v1/consignments` - Create consignment
- `POST /api/v1/consignments/{id}/receive` - Receive consignment
- `POST /api/v1/consignments/{id}/confirm` - Confirm sale
- `GET /api/v1/consignments` - List consignments

---

### F. UI COMPONENTS (Minimum)

#### Dashboard
- Overview cards: POs pending, receipts pending, transfers in progress
- Quick actions: Create PO, Start Receiving, View Pending Approvals
- Recent activity feed (powered by CISLogger)

#### PO Management
- Create PO form (products, quantities, supplier, outlet)
- PO list with filtering (status, outlet, supplier, date range)
- PO detail page with full history
- Approval workflow interface (for managers)
- Print PO template

#### Receiving Interface
- Product entry (manual + barcode scanning)
- Quantity verification (visual feedback, audio tones)
- Signature capture UI
- Photo gallery (upload, rotate, preview)
- Invoice upload & matching UI
- Real-time progress indicator

#### Transfers Management
- Create transfer interface (source, destination, items)
- Transfer tracking/status display
- Staff transfer interface (from/to staff selection)
- Bulk receive interface

#### Approvals
- Pending approvals list (filtered by user role)
- Approval detail page with approval history
- One-click approve/reject interface
- Comments/notes field for approvals

#### Reports
- Weekly management overview (same as email report)
- PO aging report
- Delivery performance by supplier
- Photo/signature audit trail
- CISLogger activity report

---

### G. INTEGRATION POINTS

#### 1. **Lightspeed Retail**
- Sync products, inventory levels at receive time
- Update Lightspeed with received quantities
- Handle API errors gracefully
- Idempotent syncing

#### 2. **CISLogger** (AI Logging System)
- Log all actions with structured data
- Include actor, timestamp, IP, entity details
- Support for custom attributes

#### 3. **Vend Master Tables**
- `vend_products` - Product lookup, SKU matching
- `vend_outlets` - Outlet/store management
- `vend_suppliers` - Supplier master
- `vend_inventory` - Real-time stock levels

#### 4. **Email System**
- Supplier PO notifications
- Weekly management reports
- Exception alerts

#### 5. **File System**
- Signature PNGs: `/files/signatures/[outlet_id]/[receipt_id]/`
- Photos: `/files/photos/[outlet_id]/[po_id]/`
- Invoices: `/files/invoices/[year]/[outlet_id]/[po_id]/`

#### 6. **Authentication/Authorization**
- Multi-role support (5 roles)
- Approval tier enforcement
- Outlet-level permissions

---

### H. TESTING REQUIREMENTS

**Unit Tests:**
- PO creation (DRAFT status)
- Approval tier calculation
- Status transitions
- Lightspeed sync logic
- Over-receipt handling
- Partial delivery logic

**Integration Tests:**
- PO → Receiving → Lightspeed sync (full workflow)
- Multi-tier approvals
- CISLogger integration
- Photo upload & resize
- Invoice matching

**End-to-End Tests:**
- Complete PO lifecycle (create → approve → receive → complete)
- Stock transfer workflow
- Staff transfer workflow
- Consignment workflow
- Approval chain (multiple roles)

**Performance Tests:**
- 1000 PO list load time < 2s
- Barcode scan response < 200ms
- Photo upload/resize < 5s
- Lightspeed sync < 30s

---

### I. DOCUMENTATION REQUIREMENTS

**Code:**
- PHPDoc on all classes/methods
- Inline comments on complex logic
- README in each module folder

**User Guide:**
- How to create PO
- How to approve PO
- How to receive goods
- How to use barcode scanner
- How to upload photos/invoice
- Staff transfer process
- Consignment process

**API Documentation:**
- OpenAPI/Swagger spec
- Request/response examples
- Error codes
- Authentication details

**Database:**
- Entity Relationship Diagram (ERD)
- Table/field documentation
- Migration history

---

### J. DEPLOYMENT CHECKLIST

Before release:
- ✅ All unit tests pass
- ✅ All integration tests pass
- ✅ Zero PHP warnings/errors
- ✅ CISLogger working for all events
- ✅ Lightspeed integration tested
- ✅ Photo resize tested
- ✅ Signature capture tested
- ✅ Barcode scanning tested
- ✅ Email notifications tested
- ✅ All approval tiers tested
- ✅ Database migrations applied
- ✅ File permissions correct
- ✅ Performance benchmarks met
- ✅ Security review passed
- ✅ Documentation complete
- ✅ Backup created

---

## 🎯 SUCCESS CRITERIA

**MUST HAVE (Non-Negotiable):**
✅ All 4 transfer types fully functional
✅ DRAFT status architecture working
✅ Multi-tier approval system enforced
✅ Lightspeed sync at receive time
✅ All receiving features (signature, barcode, photos, invoice)
✅ CISLogger logging every action
✅ All database tables properly related
✅ All APIs working (tested)
✅ Email notifications working
✅ Zero data loss or corruption

**NICE TO HAVE (Can Be Added Later):**
- Advanced reporting dashboard
- Mobile app support
- Bulk import/export
- Supplier portal
- API webhooks

---

## 📝 FINAL NOTES

This is a **complete, production-ready specification**. The coding agent should:

1. **Create all database tables** with proper relationships
2. **Implement all APIs** with full validation
3. **Build all UI components** (clean, responsive Bootstrap 4.2)
4. **Integrate CISLogger** on every significant action
5. **Test everything** before opening PR
6. **Document thoroughly** (code + user guide)
7. **Handle errors gracefully** (no silent failures)
8. **Optimize performance** (indexed queries, caching where appropriate)
9. **Follow existing code patterns** in the project
10. **Deploy to main branch** when complete

**The result should be a fully functional, battle-tested Consignments Module that's ready for real-world use immediately.**

---

**Ready for autonomous coding agent to begin implementation.** 🚀
