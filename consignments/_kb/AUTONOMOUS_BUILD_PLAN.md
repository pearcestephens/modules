# 🚀 PURCHASE ORDERS MODULE - AUTONOMOUS BUILD PLAN

**Date:** October 31, 2025
**Status:** READY TO BUILD
**Build Mode:** AUTONOMOUS (No user interaction required)
**Timeline:** Complete system in 7-10 days

---

## 📋 WHAT I UNDERSTAND

### ✅ **Your Requirements** (Crystal Clear)

1. **You're EXHAUSTED** - Been working on this for months
2. **You want it DONE** - No more back and forth
3. **You want it to WORK** - Actually function in production
4. **You need 4 transfer methods working:**
   - Stock Transfers (outlet → outlet)
   - Purchase Orders (supplier → outlet)
   - Staff Transfers (staff → staff)
   - Returns (outlet → supplier)
5. **Must sync with Vend/Lightspeed** - Both ways (to/from)
6. **Must use existing consignment tables** - `vend_consignments`, `queue_consignments`, etc.
7. **Must use base template system** - `/modules/base/_templates/layouts/`
8. **All requirements from Q1-Q35 MUST be implemented**

---

## ✅ **What I Have** (Complete Context)

### **Database Schema** ✅
- All 48 consignment tables dumped and analyzed
- `vend_consignments` table supports `PURCHASE_ORDER` category
- `vend_consignment_line_items` for products
- `queue_consignments` for Lightspeed sync queue
- Complete audit, logging, and tracking tables

### **Business Requirements** ✅
- Q1-Q35 all answered with specifications
- Q21-Q26 approval workflow defined
- Q27-Q35 operations/integration requirements
- Email templates (Q27)
- Freight integration (Q27-Q35)
- All validation rules, state machines, workflows documented

### **Technical Architecture** ✅
- Lightspeed API integration patterns from existing code
- ConsignmentsService.php pattern for uploads
- LightspeedClient.php for API calls
- Auto-save system from stock-transfers
- Template system (`dashboard.php`, `table.php`, `card.php`, `blank.php`)
- Asset auto-loading system
- CSRF protection patterns

### **Existing Good Code** ✅
- `/consignments/lib/ConsignmentsService.php` - Upload workflow
- `/consignments/lib/LightspeedClient.php` - API client
- `/consignments/stock-transfers/pack.php` - Auto-save pattern
- `/shared/functions/auto-load-assets.php` - Asset loading
- Error handling, progress tracking, queue system

---

## 🎯 BUILD STRATEGY

### **Phase 1: Foundation** (Day 1) ⏳
Create core structure for Purchase Orders using existing patterns

**Deliverables:**
1. Purchase Order service classes
2. Database migration (extend vend_consignments)
3. Base API endpoints structure
4. Permission/validation services

### **Phase 2: Core CRUD** (Days 2-3) ⏳
Build main pages and workflows

**Deliverables:**
1. List page (table.php layout) - View all POs
2. Create page (dashboard.php layout) - New PO form
3. Edit page (dashboard.php layout) - Modify existing PO
4. View/Detail page (card.php layout) - PO details
5. Auto-save system (from pack.php pattern)
6. Product selection modal

### **Phase 3: Approval Workflow** (Days 4-5) ⏳
Implement Q21-Q26 approval system

**Deliverables:**
1. Approval matrix configuration (by outlet/amount)
2. Approval dashboard
3. Approve/reject/amend actions
4. Email notifications (internal template from Q27)
5. Escalation logic
6. Delegation system

### **Phase 4: Lightspeed Integration** (Days 6-7) ⏳
Sync with Vend/Lightspeed at appropriate times

**Deliverables:**
1. Create consignment in Lightspeed when PO approved
2. Upload products to consignment
3. Sync status updates (sent/received)
4. Queue-based processing (queue_consignments)
5. Error handling and retry logic
6. Idempotent operations

### **Phase 5: Receiving & Completion** (Day 8) ⏳
Goods receipt workflow

**Deliverables:**
1. Receiving page (dashboard.php layout)
2. Barcode scanning support (optional)
3. Quantity verification
4. Damage/variance handling
5. Inventory update at completion
6. Receipt confirmation

### **Phase 6: Supplier Integration** (Day 9) ⏳
External supplier features

**Deliverables:**
1. Supplier email notifications (Q27 template)
2. Supplier portal integration (if exists)
3. Freight integration (GSS, NZ Post, FreightEngine)
4. Tracking number updates
5. Delivery date management

### **Phase 7: Polish & Deploy** (Day 10) ⏳
Final touches and production deployment

**Deliverables:**
1. All print stylesheets
2. Mobile responsive testing
3. Performance optimization
4. Security audit
5. Documentation
6. Deployment checklist

---

## 🔧 IMPLEMENTATION DETAILS

### **File Structure** (To Be Created)

```
modules/consignments/
├── purchase-orders/
│   ├── index.php                 (List view - table.php)
│   ├── create.php                (Create form - dashboard.php)
│   ├── edit.php                  (Edit form - dashboard.php)
│   ├── view.php                  (Detail view - card.php)
│   ├── receive.php               (Receiving - dashboard.php)
│   └── approve.php               (Approval - dashboard.php)
│
├── api/purchase-orders/
│   ├── create.php
│   ├── update.php
│   ├── delete.php
│   ├── list.php
│   ├── get.php
│   ├── approve.php
│   ├── reject.php
│   ├── submit.php
│   ├── receive.php
│   └── autosave.php
│
├── lib/Services/
│   ├── PurchaseOrderService.php
│   ├── ApprovalService.php
│   ├── ReceivingService.php
│   └── SupplierService.php
│
├── lib/Models/
│   ├── PurchaseOrder.php
│   └── PurchaseOrderLineItem.php
│
├── js/purchase-orders/
│   ├── list.js
│   ├── create.js
│   ├── edit.js
│   ├── approve.js
│   └── receive.js
│
├── css/purchase-orders/
│   ├── list.css
│   ├── form.css
│   ├── approve.css
│   └── print.css
│
└── migrations/
    └── 001_extend_vend_consignments_for_po.sql
```

### **Database Strategy**

**REUSE existing tables** (don't create new ones):
- `vend_consignments` (set `transfer_category = 'PURCHASE_ORDER'`)
- `vend_consignment_line_items` (products)
- `queue_consignments` (Lightspeed sync)
- `consignment_audit_log` (audit trail)
- All other consignment_* tables

**Add new columns** (if needed):
- Approval workflow columns (if not already present)
- Supplier-specific fields (if not already present)
- Freight tracking fields (if not already present)

### **Lightspeed Sync Workflow**

```
1. User creates PO in CIS (DRAFT state)
   ↓
2. User submits PO for approval (PENDING_APPROVAL state)
   ↓
3. Approver reviews and approves (APPROVED state)
   ↓
4. Background job creates consignment in Lightspeed
   - POST /consignments to Lightspeed API
   - Store vend_transfer_id in vend_consignments
   - INSERT into queue_consignments
   ↓
5. Background job uploads products to consignment
   - Loop through line items
   - POST /consignment_products for each item
   - INSERT into queue_consignment_products
   ↓
6. User marks PO as SENT (when shipped to supplier)
   - UPDATE vend_consignments (state='SENT')
   - PATCH Lightspeed consignment status
   ↓
7. Goods arrive, user starts RECEIVING
   - Scan barcodes or manual entry
   - Verify quantities
   - Note discrepancies
   ↓
8. User completes receiving
   - UPDATE inventory in vend_inventory
   - UPDATE vend_consignments (state='RECEIVED')
   - PATCH Lightspeed consignment status
   - Close PO
```

---

## ✅ SUCCESS CRITERIA

The build is complete when:

1. ✅ All 4 transfer types work (STOCK, PURCHASE_ORDER, STAFF, RETURN)
2. ✅ Users can create POs with products
3. ✅ Approval workflow functions (Q21-Q26)
4. ✅ Lightspeed sync works bidirectionally
5. ✅ Receiving updates inventory correctly
6. ✅ All pages use proper base layouts
7. ✅ Auto-save works on all forms
8. ✅ Email notifications send (Q27)
9. ✅ Freight integration works (Q27-Q35)
10. ✅ All Q1-Q35 requirements implemented
11. ✅ Security is rock-solid (CSRF, validation, auth)
12. ✅ Performance is good (P95 < 1s)
13. ✅ Tests pass
14. ✅ Documentation is complete
15. ✅ User can actually USE it in production

---

## 🚀 STARTING NOW

**Build Mode:** AUTONOMOUS
**Timeline:** 7-10 days
**User Involvement:** NONE (I'll work while you sleep)
**Communication:** I'll provide daily progress summaries

**Next Action:** Begin Phase 1 - Foundation setup

Let me start building... 🔧

---

**Note:** All code will be production-ready, following all patterns from your existing codebase. No shortcuts, no placeholders, no "TODO" comments. Everything will WORK.
