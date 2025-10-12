# 🎯 TRANSFER SYSTEM MASTER GUIDE
**Complete Business Logic & Implementation Reference**  
**Last Updated:** October 12, 2025  
**Status:** Definitive Reference Document

---

## 📋 Table of Contents

1. [System Overview](#system-overview)
2. [Core Objects & Mapping](#core-objects--mapping)
3. [Lightspeed Integration Model](#lightspeed-integration-model)
4. [4 Transfer Modes](#4-transfer-modes)
5. [Complete Lifecycles](#complete-lifecycles)
6. [Page-by-Page Feature Checklists](#page-by-page-feature-checklists)
7. [Dashboard Specifications](#dashboard-specifications)
8. [Production Gotchas](#production-gotchas)
9. [API Playbook](#api-playbook)

---

## 🎯 System Overview

### **What This System Does**

The **Transfer System** manages stock movement between **17 retail outlets** across New Zealand through **4 distinct workflows**:

1. **Stock Transfers** (outlet→outlet) - Standard inventory movement
2. **Juice Transfers** (outlet→outlet) - E-liquid with compliance tracking
3. **Staff Transfers** (outlet→staff) - Employee personal orders
4. **Supplier Purchase Orders** (HQ→supplier) - External procurement

### **Architecture Principles**

- **Shadow/Cache Layer**: CIS maintains local state, syncs to Lightspeed
- **Partial Fulfillment**: Multi-shipment waves with box-level tracking
- **AI Integration**: FreightAI, NeuroLink, AutoPack optimization
- **Compliance Ready**: 7-year audit trails, regulatory tracking
- **Idempotent Operations**: Safe retries, duplicate prevention

---

## 📦 Core Objects & Mapping

### **1. Stock Transfer (outlet→outlet)**

**CIS Storage:**
- **Header:** `transfers` table
  - `type`: `stock|juice|staff` 
  - `status`: `draft|open|sent|partial|received|cancelled`
  - `vend_transfer_id`: Links to Lightspeed consignment
- **Lines:** `transfer_items` table
  - `qty_requested`: Original request
  - `qty_sent_total`: Cumulative sent (all shipments)
  - `qty_received_total`: Cumulative received

**Multi-Shipment Support:**
- `transfer_shipments`: Shipment waves
- `transfer_shipment_items`: Products per wave
- `transfer_parcels`: Box-level tracking
- `transfer_parcel_items`: Products per box

**Key Constraints:**
```sql
CHECK (qty_sent_total <= qty_requested)
CHECK (qty_received_total <= qty_sent_total)
CHECK (outlet_from <> outlet_to)
```

---

### **2. Supplier Purchase Order (HQ→supplier)**

**CIS Storage:**
- **Header:** `purchase_orders` table
- **Lines:** `purchase_order_line_items` table
  - **Discrepancy Flags:** `OK|MISSING|SENT_LOW|SENT_HIGH|SUBSTITUTED|DAMAGED|UNORDERED`

**Multi-Delivery Support:**
- `po_receipts`: Delivery waves  
- `po_receipt_items`: Products per delivery
- `po_evidence`: Packing slips, photos
- `po_events`: Complete event trail

**Validation & Control:**
- Device/session info for mobile receiving
- Packing slip capture (`packing_slip_no`)
- Invoice tracking (`invoice_no`)

---

### **3. Inventory Mirrors & Outlets**

**Lightspeed Cache Tables:**
- `vend_products`: Product catalog mirror
- `vend_inventory`: On-hand quantities
- `vend_outlets`: Store details (NZ Post keys, addresses)

**Sync Guardrails:**
- `vend_inventory.sync_status`: Eventual consistency tracking
- `vend_version_tracking`: Lost update prevention
- `idempotency_keys`: External call caching

---

### **4. Stock Corrections**

**Non-Transfer Adjustments:**
- `inventory_adjust_requests`: Manual corrections
- Idempotency protection
- Status tracking (`pending|approved|applied`)
- Audit trail linkage

---

## 🔌 Lightspeed (Vend) Consignment Model

### **What It Is**

**One unified Consignments API** covering multiple workflows via `type`:
- **OUTLET** (store↔store transfer)
- **SUPPLIER** (purchase order)  
- **RETURN** (supplier returns)
- **STOCKTAKE** (inventory audits)

**API Pattern:**
1. Create: `POST /api/2.0/consignments`
2. Update status and add/receive products
3. Finalize when complete

### **Status Workflows**

#### **OUTLET Workflow (transfers)**
```
OPEN → SENT → [DISPATCHED] → RECEIVED
```
- **DISPATCHED**: Optional intermediate stage
- **Cancellable** until fully received
- **Partial receiving** supported

#### **SUPPLIER Workflow (POs)**
```
OPEN → SENT → RECEIVED
```
- **RECEIVED is IMMUTABLE** - cannot change quantities after
- **Multiple deliveries** now first-class in Retail X UI

### **Where It's Great**

✅ **Unified Shape**: Same endpoints, shared vocabulary  
✅ **Clear State Machine**: Simple "finalize" semantics  
✅ **Multi-Delivery Support**: Aligns with `po_receipts` model  
✅ **First-Class Partial**: Built into Lightspeed UI now

### **Where It's Prickly**

⚠️ **Docs Variance**: SENT vs DISPATCHED - handle both  
⚠️ **Eventual Consistency**: No true two-phase commit  
⚠️ **Finality**: RECEIVED = immutable quantities  
⚠️ **No Consignment Tags**: Use dedicated outlet for stock segregation

---

## 🔄 4 Transfer Modes

### **Mode 1: GENERAL (stock)**
**Purpose:** Standard outlet→outlet inventory movement  
**Characteristics:**
- Basic workflow: Draft → Open → Pack → Send → Receive
- No special compliance requirements
- Standard approval workflow
- Multi-shipment waves supported

### **Mode 2: JUICE** 
**Purpose:** E-liquid transfers with regulatory compliance  
**Characteristics:**
- **Nicotine tracking**: `nicotine_in_shipment` flag
- **Regulatory compliance**: Enhanced audit trails
- **Special packaging**: Compliance-ready labeling
- **Age verification**: Enhanced receiving checks

### **Mode 3: STAFF**
**Purpose:** Employee personal orders  
**Characteristics:**
- **Special Pricing**: Employee discount rates
- **Approval Workflow**: Manager confirmation required
- **Deposit Logic**: Prepayment tracking
- **Return Requirements**: Mandatory return periods
- **Dual Confirmation**: `transfer_items.confirmation_status`
- **Customer Linkage**: `customer_id` points to staff's Vend customer

**Business Rules:**
- Requires second-party confirmation on send/receive
- Cannot self-transfer (outlet_from ≠ outlet_to enforced)
- Usually NOT synced to Lightspeed as OUTLET
- Treat as internal control + optional inventory adjust

### **Mode 4: SUPPLIER**
**Purpose:** Purchase orders from external suppliers  
**Characteristics:**
- **Multi-delivery**: `po_receipts` + `po_receipt_items`
- **Evidence capture**: Photos, packing slips
- **Discrepancy tracking**: Missing, damaged, substituted items
- **Mobile receiving**: Device/session capture
- **Invoice matching**: `invoice_no` tracking

---

## 📋 Complete Lifecycles

### **A) Stock Transfer (Pack → Receive)**

#### **1. Draft (Create Header & Lines)**
```sql
-- Create transfer
INSERT INTO transfers (type, status, outlet_from, outlet_to, created_by)
VALUES ('stock', 'draft', 'outlet_uuid_1', 'outlet_uuid_2', user_id);

-- Add items
INSERT INTO transfer_items (transfer_id, product_id, qty_requested)
VALUES (transfer_id, 'product_uuid', quantity);
```

**Preflight Checks:**
- ✅ Validate source on-hand quantities
- ✅ Ban zero/negative requests (table constraints)
- ✅ Verify outlet differences (CHECK constraint)

#### **2. Open (Lock & Pick)**
```sql
UPDATE transfers SET status = 'open' WHERE id = transfer_id;
```

**Features:**
- Show picklist by zone/SKU
- Allow tote assignment
- **Caveat:** Server time authoritative (avoid device clock drift)

#### **3. Pack & Dispatch**
```sql
-- Increment sent quantities
UPDATE transfer_items 
SET qty_sent_total = qty_sent_total + scanned_qty 
WHERE id = item_id;

-- Create shipment
INSERT INTO transfer_shipments (transfer_id, delivery_mode, status)
VALUES (transfer_id, delivery_mode, 'packed');
```

**Lightspeed Sync (if enabled):**
```bash
# Create OUTLET consignment
POST /api/2.0/consignments 
{
  "type": "OUTLET",
  "status": "OPEN",
  "source_outlet_id": "outlet_from",
  "destination_outlet_id": "outlet_to"
}

# Add products/quantities
POST /api/2.0/consignments/{id}/products
PUT /api/2.0/consignments/{id} {"status": "SENT"}  # or DISPATCHED
```

**State Change:**
- CIS flips to `status=sent` once first carton leaves
- Store label/manifest refs in shipment metadata

#### **4. Partial Receive(s)**
```sql
-- Increment received quantities
UPDATE transfer_items 
SET qty_received_total = qty_received_total + received_qty 
WHERE id = item_id;

-- Check for partial completion
UPDATE transfers SET status = 'partial' 
WHERE id = transfer_id 
AND EXISTS (
  SELECT 1 FROM transfer_items 
  WHERE transfer_id = transfers.id 
  AND qty_received_total < qty_sent_total
);
```

**Lightspeed Caveat:**
- Only finalize to RECEIVED when last delivery arrives
- Marking RECEIVED is **IMMUTABLE** - no changes after

#### **5. Complete**
```sql
-- Final completion check
UPDATE transfers SET status = 'received' 
WHERE id = transfer_id 
AND NOT EXISTS (
  SELECT 1 FROM transfer_items 
  WHERE transfer_id = transfers.id 
  AND qty_received_total < qty_sent_total
);
```

**Lightspeed Sync:**
```bash
PUT /api/2.0/consignments/{id} {"status": "RECEIVED"}
```

**Exception Handling:**
- If destination declines items → create return consignment
- For adjustments → use `inventory_adjust_requests` with idempotency

---

### **B) Staff Transfers (Special Workflow)**

Same rails as stock transfer BUT:

**Key Differences:**
- `transfers.type = 'staff'`
- Link via `staff_transfer_id`
- Requires `customer_id` (staff's Vend customer)
- Enhanced approval workflow
- Usually NOT synced to Lightspeed as OUTLET

**Extra Requirements:**
- ✅ Second-party confirmation required
- ✅ Manager approval for high-value items  
- ✅ Deposit tracking and return periods
- ✅ Enhanced audit for compliance

---

### **C) Supplier PO (Create → Multi-delivery → Finalize)**

#### **1. Create PO in CIS**
```sql
INSERT INTO purchase_orders (supplier_id, status, created_by)
VALUES (supplier_id, 'draft', user_id);

INSERT INTO purchase_order_line_items 
(po_id, product_id, qty_ordered, unit_cost)
VALUES (po_id, product_id, quantity, cost);
```

**Mobile Support:**
- Capture `packing_slip_no`
- Capture `invoice_no`  
- Store device/session info

#### **2. Send to Supplier / Lightspeed**
```bash
# If pushing to Lightspeed as SUPPLIER
POST /api/2.0/consignments
{
  "type": "SUPPLIER", 
  "status": "OPEN"
}

PUT /api/2.0/consignments/{id} {"status": "SENT"}
```

#### **3. Receive Deliveries (Partial Allowed)**
```sql
-- Create delivery wave
INSERT INTO po_receipts (po_id, receipt_date, delivery_note)
VALUES (po_id, NOW(), delivery_reference);

-- Add received items
INSERT INTO po_receipt_items 
(receipt_id, line_item_id, qty_received, discrepancy_type)
VALUES (receipt_id, line_id, received_qty, 'OK');

-- Update main lines
UPDATE purchase_order_line_items 
SET qty_arrived = qty_arrived + received_qty,
    discrepancy_type = CASE 
      WHEN qty_arrived + received_qty < qty_ordered THEN 'SENT_LOW'
      WHEN qty_arrived + received_qty > qty_ordered THEN 'SENT_HIGH'  
      ELSE 'OK'
    END
WHERE id = line_id;

-- Attach evidence
INSERT INTO po_evidence (po_id, evidence_type, file_path)
VALUES (po_id, 'packing_slip_photo', file_path);
```

**Lightspeed Alignment:**
- Aligns to new "multiple deliveries under one PO" pattern
- Each `po_receipts` = one delivery in Lightspeed UI

#### **4. Finalize**
```sql
-- Check completion
UPDATE purchase_orders SET status = 'completed'
WHERE id = po_id 
AND NOT EXISTS (
  SELECT 1 FROM purchase_order_line_items 
  WHERE po_id = purchase_orders.id 
  AND qty_arrived < qty_ordered
);
```

**Lightspeed Sync:**
```bash
# Only when all deliveries complete
PUT /api/2.0/consignments/{id} {"status": "RECEIVED"}  # IMMUTABLE!
```

---

## 📊 Page-by-Page Feature Checklists

### **A) Transfers › Pack**

#### **Pick & Pack Grid**
- ✅ Group by zone for efficient picking
- ✅ Real-time scanned qty vs requested  
- ✅ Running totals per carton
- ✅ Product images and descriptions

#### **Carton Builder**
- ✅ Per-carton line assignments
- ✅ Dimensions and weight tracking
- ✅ Running totals (items, weight, value)
- ✅ Barcode generation per carton

#### **Labeling/Manifest Hooks**
- ✅ NZ Post integration
- ✅ GSS courier integration
- ✅ Store label URLs/IDs per carton
- ✅ Audit trail for all labels

#### **Error Handling**
- ⚠️ On-hand shortfall warnings
- ⚠️ Over-pick prevention
- ⚠️ Product ban alerts
- ⚠️ Zone mismatch warnings

#### **Session Management**
- 🔒 Single-packer lock per transfer
- 🔒 Takeover after inactivity timeout
- 🔒 Session state auto-save

#### **State Controls**
- 🎛️ Draft→Open→Sent progression
- 🎛️ "Send Now" disabled until cartons sealed
- 🎛️ Bulk carton operations

---

### **B) Transfers › Receive**

#### **Fast Scan Mode**
- 📱 UPC or internal SKU scanning
- 📱 Auto-increment `qty_received_total`
- 📱 Audio/visual feedback
- 📱 Batch mode for speed

#### **Exceptions Panel**
- 📸 Missing item photography
- 📸 Damage documentation  
- 📸 Substitution tracking
- 📸 Evidence upload to `po_evidence`

#### **Partial Completion**
- ⏳ Leaves page in "Partial" until all closed
- ⏳ Outstanding item highlighting
- ⏳ ETA tracking for remaining shipments

#### **Quick Actions**
- ⚡ Return/adjust shortcut for rejects
- ⚡ Create adjustment requests
- ⚡ Manager escalation button

---

### **C) Transfers › Outlet Board (Store View)**

#### **Inbound Pane**
- 📥 ETA and sender info
- 📥 Carton count and outstanding
- 📥 High-priority flags
- 📥 Age-based sorting

#### **Outbound Pane**  
- 📤 Age since creation
- 📤 Last scan timestamp
- 📤 Destination and urgency
- 📤 Label/manifest status

#### **Quick Actions**
- ⚡ Open receive interface
- ⚡ Print/reprint labels  
- ⚡ Add tracking/comments
- ⚡ Nudge sender notifications

#### **Filtering**
- 🔍 By store/supplier
- 🔍 By age buckets
- 🔍 By status/priority
- 🔍 By value thresholds

---

### **D) Transfers › Management (HQ View)**

#### **Executive Rollups**
- 📊 In-transit value totals
- 📊 Oldest transfer age
- 📊 Per-store SLA compliance  
- 📊 Exception count trending

#### **Bulk Operations**
- 🔧 Cancel draft transfers
- 🔧 Resend failed webhooks
- 🔧 Rebuild labels/manifests
- 🔧 Bulk approval workflows

#### **Drill-Down Path**
```
Store → Transfer → Carton → Line → Evidence
```

---

### **E) POs › Receive**

#### **Slip Capture**
- 📄 Scan packing slip number
- 📄 PDF/photo attachment
- 📄 OCR text extraction
- 📄 Auto-match to PO

#### **Multi-Delivery Interface**
- 📦 Each `po_receipts` as subrows
- 📦 Per-line quantities per delivery
- 📦 Cumulative vs outstanding
- 📦 Delivery date tracking

#### **Discrepancy Tools**
- 🚨 Line-level flags (OK|MISSING|DAMAGED|etc)
- 🚨 Notes and resolution tracking
- 🚨 Barcode proof capture
- 🚨 Manager approval for variances

#### **Finalization**
- 🔒 Finalize button disables all edits
- 🔒 Accounts handoff banner
- 🔒 Immutable state enforcement

---

## 🎛️ Dashboard Specifications

### **Outlet-Only Dashboard**

#### **Today's Actions Panel**
- 🎯 Inbound transfers to receive
- 🎯 Outbound transfers to dispatch  
- 🎯 Low stock urgent requests
- 🎯 Overdue SLA warnings

#### **Scan & Go Panel**
- 📱 Single input field
- 📱 Smart mode detection (UPC/SKU/Transfer ID)
- 📱 Context-aware actions
- 📱 Instant feedback

#### **SLA Countdown**
- ⏰ Inbound aging timers
- ⏰ Outbound promised delivery
- ⏰ Color-coded urgency
- ⏰ Manager alert thresholds

#### **Device Optimization**
- 📱 Fully mobile-friendly design
- 📱 Offline cache for 30 scans
- 📱 Touch-optimized controls
- 📱 Barcode camera integration

---

### **Management-Only Dashboard**

#### **Network Heatmap**
- 🗺️ Aging buckets visualization
- 🗺️ In-transit value by lane
- 🗺️ Bottleneck identification
- 🗺️ Geographic flow patterns

#### **Exception Leaderboards**
- 🏆 By store performance
- 🏆 By supplier reliability
- 🏆 By SKU family issues
- 🏆 Trending problem areas

#### **Carrier Scorecard**
- 🚚 Late delivery percentages
- 🚚 Relabel requirements
- 🚚 Dimension mismatches
- 🚚 Cost per delivery analysis

#### **Sync Health Monitor**
- ⚡ Vend API rate limit usage
- ⚡ Conflict resolution count
- ⚡ Last success per entity type
- ⚡ Idempotency key effectiveness

**Data Sources:**
- `vend_inventory.sync_status` for consistency tracking
- `idempotency_keys` for duplicate detection
- `transfer_queue_log` for retry analysis

---

## ⚠️ Production Gotchas

### **1. Status Mapping Drift**
**Problem:** Some X-Series docs show `OPEN→SENT→RECEIVED`, others include `DISPATCHED`

**Solution:**
```php
class TransferStateMapper {
    public function toLightspeed($cisStatus) {
        switch($cisStatus) {
            case 'open': return 'OPEN';
            case 'sent': return 'SENT';  // or DISPATCHED
            case 'received': return 'RECEIVED';
            default: 
                Log::warning("Unknown CIS status: $cisStatus");
                return 'OPEN';  // Safe default
        }
    }
    
    public function fromLightspeed($lsStatus) {
        switch($lsStatus) {
            case 'OPEN': return 'open';
            case 'SENT':
            case 'DISPATCHED': return 'sent';  // Handle both
            case 'RECEIVED': return 'received';
            default:
                Log::warning("Unknown LS status: $lsStatus");
                return 'open';
        }
    }
}
```

### **2. Finality Rules**
**Problem:** Once RECEIVED in X-Series, quantities are IMMUTABLE

**Solution:**
- ✅ Only finalize when ALL deliveries complete
- ✅ Use fresh consignment for corrections
- ✅ Track finalization state in CIS
- ✅ Warn users before finalization

### **3. Partial Receipt Timing**
**Problem:** Not all accounts saw multi-delivery feature simultaneously

**Solution:**
- ✅ Model per-delivery in CIS (`po_receipts`) regardless
- ✅ Only finalize Lightspeed at the very end
- ✅ Graceful degradation for older accounts
- ✅ Feature flag for multi-delivery UI

### **4. Eventual Consistency**
**Problem:** No true two-phase commit with Lightspeed

**Solution:**
- ✅ Respect pagination and rate limits
- ✅ Implement exponential backoff retries
- ✅ Use `idempotency_keys` for duplicate prevention  
- ✅ Store `vend_version_tracking` to avoid lost updates
- ✅ Monitor sync health dashboards

### **5. Consignment Stock Isolation**
**Problem:** No granular "consignment tag" in X-Series inventory

**Lightspeed Recommendation:** Use dedicated outlet for consigned stock

**CIS Implementation:**
```sql
-- Create virtual "consignment" outlet
INSERT INTO vend_outlets (name, outlet_type, is_virtual)
VALUES ('Consignment Stock', 'warehouse', 1);

-- Transfer to consignment outlet first
-- Then to final destination when confirmed
```

---

## 🔌 API Playbook

### **Create OUTLET Transfer**
```bash
# 1. Create consignment
POST /api/2.0/consignments
{
  "type": "OUTLET",
  "status": "OPEN", 
  "source_outlet_id": "uuid_outlet_from",
  "destination_outlet_id": "uuid_outlet_to",
  "name": "Transfer TR-12345",
  "reference": "TR-12345"
}

# 2. Add products
POST /api/2.0/consignments/{id}/products
{
  "consignment_product": {
    "product_id": "uuid_product", 
    "count": 10
  }
}

# 3. Mark sent (or dispatched) 
PUT /api/2.0/consignments/{id}
{
  "status": "SENT"  # or "DISPATCHED" 
}

# 4. Receive products (when ready)
PUT /api/2.0/consignments/{id}/products/{product_id}
{
  "received": 8  # Allow partial
}

# 5. Finalize (IMMUTABLE after this!)
PUT /api/2.0/consignments/{id}
{
  "status": "RECEIVED"
}
```

### **Create SUPPLIER Order**
```bash
# 1. Create supplier consignment  
POST /api/2.0/consignments
{
  "type": "SUPPLIER",
  "status": "OPEN",
  "outlet_id": "uuid_destination_outlet", 
  "supplier_id": "uuid_supplier",
  "name": "PO-67890",
  "reference": "PO-67890"
}

# 2. Add order lines
POST /api/2.0/consignments/{id}/products
{
  "consignment_product": {
    "product_id": "uuid_product",
    "count": 50,
    "cost": "12.50"
  }
}

# 3. Send to supplier
PUT /api/2.0/consignments/{id}
{
  "status": "SENT"
}

# 4. Multi-delivery receipts (repeat as needed)
PUT /api/2.0/consignments/{id}/products/{product_id}
{
  "received": 20  # First delivery
}

PUT /api/2.0/consignments/{id}/products/{product_id}  
{
  "received": 30  # Second delivery (cumulative: 50)
}

# 5. Finalize when complete (IMMUTABLE!)
PUT /api/2.0/consignments/{id}
{
  "status": "RECEIVED"
}
```

### **Useful Helper Endpoints**
```bash
# List products on consignment
GET /api/2.0/consignments/{id}/products

# Get consignment totals for audit
GET /api/2.0/consignments/{id}/totals

# Bulk product operations (up to 500 products)
POST /api/2.0/consignments/{id}/bulk
{
  "products": [
    {"product_id": "uuid1", "count": 10},
    {"product_id": "uuid2", "count": 5}
  ]
}
```

---

## 🎯 Summary

This transfer system provides **enterprise-grade stock management** with:

✅ **4 Distinct Workflows** - Stock, Juice, Staff, Supplier  
✅ **Partial Fulfillment** - Multi-shipment waves with box tracking  
✅ **Lightspeed Integration** - Bidirectional sync with state mapping  
✅ **AI Optimization** - FreightAI courier selection, AutoPack algorithms  
✅ **Compliance Ready** - 7-year audit trails, regulatory tracking  
✅ **Mobile Optimized** - Barcode scanning, offline capability  
✅ **Exception Handling** - Damage tracking, returns, adjustments  

The **BASE transfer template** should provide common functionality across all 4 modes, with mode-specific customizations layered on top.

---

**Next Step:** Create the BASE transfer template with pre-loaded look and feel! 🚀