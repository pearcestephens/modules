# 🤖 GitHub Copilot Autonomous Build Instructions

**Project:** CIS Consignments Module - Purchase Orders System  
**Status:** Phase 1 Day 1 Complete (6 API endpoints done)  
**Timeline:** 6 more days to complete autonomous build  
**Reference:** `/consignments/_kb/AUTONOMOUS_BUILD_EXECUTION.md`

---

## 🎯 YOUR MISSION

You are GitHub Copilot assigned to **autonomously complete** the Purchase Orders system for the CIS Consignments module. Work independently through Phases 1-6 over the next 6 days, committing progress to GitHub daily.

---

## 📋 COMPLETED (Day 1)

✅ **Phase 1 Day 1: API Endpoints** (DONE)
- `/api/purchase-orders/list.php` - List with filters ✅
- `/api/purchase-orders/get.php` - Single PO retrieval ✅
- `/api/purchase-orders/autosave.php` - Draft autosave ✅
- `/api/purchase-orders/receive.php` - Item receiving ✅
- `/api/purchase-orders/freight-quote.php` - Freight quotes ✅
- `/api/purchase-orders/create-label.php` - Label generation ✅
- `/purchase-orders/js/list.js` - DataTables UI ✅

---

## 🚀 TODO: REMAINING WORK (6 Days)

### **Phase 1 Day 2: Complete CRUD APIs + Service Classes**

**Priority:** URGENT - Start immediately

**Files to create:**

#### API Endpoints (6 more):
```
/api/purchase-orders/create.php          - POST new PO
/api/purchase-orders/update.php          - PUT existing PO
/api/purchase-orders/delete.php          - DELETE PO
/api/purchase-orders/submit.php          - POST submit for approval
/api/purchase-orders/approve.php         - POST approve action
/api/purchase-orders/reject.php          - POST reject action
```

#### Service Classes:
```
/lib/Services/PurchaseOrderService.php   - CRUD operations
/lib/Services/ApprovalService.php        - Workflow engine
/lib/Services/ReceivingService.php       - Goods receipt
/lib/Services/FreightService.php         - Freight integration wrapper
/lib/Models/PurchaseOrder.php            - Model class
/lib/Models/PurchaseOrderLineItem.php    - Line item model
```

**Database Requirements:**
- Verify `vend_consignments` table supports `category = 'PURCHASE_ORDER'`
- Add indexes for performance
- Create migration script if needed

**Acceptance Criteria:**
- All 6 new API endpoints functional
- Service classes with full PHPDoc
- Unit tests for service classes
- Error handling with JSON envelopes
- Commit by end of Day 2

---

### **Phase 1 Day 2 (Evening): UI Pages**

**Files to create:**
```
/purchase-orders/index.php               - List view (uses list.js already done)
/purchase-orders/create.php              - Create/edit form
/purchase-orders/edit.php                - Edit wrapper (uses create.php)
/purchase-orders/view.php                - Detail view
/purchase-orders/approve.php             - Approval dashboard
/purchase-orders/receive.php             - Receiving interface
```

**CSS Files:**
```
/purchase-orders/css/list.css            - Table styles
/purchase-orders/css/form.css            - Form layouts
/purchase-orders/css/approve.css         - Approval UI
/purchase-orders/css/print.css           - Print layouts
```

**JS Files:**
```
/purchase-orders/js/form.js              - Product selection + autosave
/purchase-orders/js/approve.js           - Approval actions
/purchase-orders/js/receive.js           - Barcode scanning + receipt
```

---

### **Phase 2: Freight Integration** (Day 3)

**Goal:** Integrate with `/assets/services/FreightIntegration.php`

**Tasks:**
1. Wrap FreightIntegration.php for PO context
2. Implement freight quote comparison UI
3. Label generation with NZ Post/GSS/StarShipIt
4. Webhook tracking updates
5. Store tracking in `consignment_parcels` table

**Freight UI Pages:**
```
/purchase-orders/freight-quote.php       - Compare carrier rates
/purchase-orders/freight-label.php       - Generate shipping label
/purchase-orders/tracking.php            - Track shipments
```

**Acceptance:**
- Get quotes from all carriers (NZ Post, NZ Courier, GSS, StarShipIt)
- Create labels via API
- Webhook updates working
- Tracking events logged

---

### **Phase 3: AI Integration** (Day 4)

**Goal:** Integrate with consignment AI tables

**AI Service:**
```
/lib/Services/AIService.php              - AI decision engine
```

**Capabilities:**
- Box size optimization
- Courier selection recommendations
- Delivery time predictions
- Anomaly detection
- Cost optimization

**AI Tables:**
- `consignment_ai_insights` - Store suggestions
- `consignment_ai_audit_log` - Decision audit trail
- `consignment_unified_log` - Event logging (274K rows)

**UI Integration:**
- Inline suggestions in pack.php
- "AI Assist" button on PO form
- Confidence scores
- Explainable AI audit trail

---

### **Phase 4: Approval Workflow** (Day 5)

**Goal:** Implement approval matrix and workflow

**Tables:**
- `queue_consignment_state_transitions` (8K rows)
- `queue_consignment_actions` (8K rows)

**Features:**
- Approval rules by outlet/amount
- Multi-level approvers
- Delegation support
- Email notifications

**UI:**
```
/purchase-orders/approve.php             - Already planned, enhance
/purchase-orders/approvals/dashboard.php - Approval queue
/purchase-orders/approvals/rules.php     - Configure rules
```

---

### **Phase 5: Receiving Interface** (Day 6)

**Goal:** Full receiving workflow

**Tables:**
- `consignment_receipts` (581 rows)
- `consignment_receipt_items` (30K rows)
- `consignment_parcel_items` (47K rows)

**Features:**
- Barcode scanner integration
- Box-by-box acceptance
- Damage/variance tracking
- Photo upload
- Print receipt

**UI Enhancements:**
```
/purchase-orders/receive.php             - Already planned, enhance
/purchase-orders/receiving/scan.php      - Barcode scanning
/purchase-orders/receiving/history.php   - Receiving history
```

---

### **Phase 6: Monitoring Dashboard** (Day 7)

**Goal:** Real-time metrics dashboard

**Tables:**
- `consignment_metrics`
- `consignment_performance_metrics`
- `consignment_system_health`

**Dashboard:**
```
/purchase-orders/dashboard.php           - Main metrics dashboard
```

**Widgets:**
- Active POs by status (pie chart)
- Late deliveries alert
- Courier performance comparison
- Cost analysis
- Approval bottlenecks
- System health

---

## 🔧 TECHNICAL REQUIREMENTS

### **Code Standards:**
- PHP 8.1+ with `declare(strict_types=1)`
- PSR-12 coding style
- Full PHPDoc comments
- Prepared statements for all SQL
- CSRF protection on all forms
- JSON API envelopes: `{success, data|error, timestamp}`

### **Database:**
- Use existing 48 consignment tables
- Main table: `vend_consignments` with `category = 'PURCHASE_ORDER'`
- Line items: `vend_consignment_line_items`
- Freight: `consignment_parcels`, `consignment_shipments`

### **Security:**
- Check `$_SESSION['user_id']` on all endpoints
- Validate permissions via `hasPermission()` helper
- Escape all output with `htmlspecialchars()`
- Rate limit API endpoints

### **Testing:**
- Create `/tests/PurchaseOrderTest.php`
- Test all CRUD operations
- Test freight integration
- Test approval workflow

---

## 📦 INTEGRATION POINTS

### **Existing Services to Use:**

1. **FreightIntegration.php** (already exists):
```php
$freight = new FreightIntegration($pdo);
$metrics = $freight->calculateTransferMetrics($poId);
$quotes = $freight->getTransferRates($poId);
$containers = $freight->suggestTransferContainers($poId);
$label = $freight->createTransferLabel($poId, $options);
$tracking = $freight->trackTransferShipment($poId);
```

2. **Vend API** (for product data):
- Use existing `vend_products` table
- Join with `vend_inventory` for stock levels

3. **Lightspeed Sync** (queue system):
- Use `queue_consignments` for async sync
- Hook into existing queue workers

---

## 🚨 CRITICAL RULES

### **DO:**
✅ Commit to GitHub after each major feature
✅ Follow existing code patterns in `/consignments/`
✅ Use existing base classes and utilities
✅ Test each endpoint before moving on
✅ Update `AUTONOMOUS_BUILD_EXECUTION.md` with progress
✅ Use proper error handling with JSON envelopes
✅ Log all actions to `consignment_audit_log`

### **DON'T:**
❌ Break existing functionality
❌ Skip validation or security checks
❌ Hard-code credentials (use .env)
❌ Create duplicate code (reuse existing services)
❌ Skip documentation (PHPDoc required)
❌ Commit broken code
❌ Change database schema without migration script

---

## 📊 PROGRESS TRACKING

After each day, update this section:

**Day 1:** ✅ API endpoints (6/12 done) + list.js  
**Day 2:** ⏳ Complete CRUD APIs + Service classes + UI pages  
**Day 3:** ⏳ Freight integration  
**Day 4:** ⏳ AI integration  
**Day 5:** ⏳ Approval workflow  
**Day 6:** ⏳ Receiving interface  
**Day 7:** ⏳ Monitoring dashboard  

---

## 🎯 SUCCESS CRITERIA

Project is complete when:

1. ✅ All 12 API endpoints functional
2. ✅ All 6 UI pages working
3. ✅ Freight quotes from all carriers
4. ✅ AI suggestions displayed
5. ✅ Approval workflow functions
6. ✅ Receiving updates inventory
7. ✅ Dashboard shows metrics
8. ✅ All 48 tables integrated
9. ✅ Tests pass
10. ✅ Documentation complete
11. ✅ Security audited
12. ✅ Performance tested (P95 < 1s)

---

## 🚀 START HERE

**Immediate Next Steps:**

1. Read `/consignments/_kb/AUTONOMOUS_BUILD_EXECUTION.md` (full spec)
2. Create `/lib/Services/PurchaseOrderService.php` (start with CRUD)
3. Implement `/api/purchase-orders/create.php`
4. Test create endpoint
5. Implement `/api/purchase-orders/update.php`
6. Continue through remaining APIs
7. Commit progress
8. Move to UI pages

**Estimated Time:** 6 hours for Day 2 completion

---

## 📞 HELP & RESOURCES

**Existing Code Examples:**
- `/consignments/pack.php` - Transfer workflow reference
- `/consignments/send.php` - Sending workflow reference
- `/assets/services/FreightIntegration.php` - Freight service

**Database Schema:**
- `/consignments/_kb/AUTONOMOUS_BUILD_EXECUTION.md` - Table list
- Check `vend_consignments` structure first

**Knowledge Base:**
- `/_kb/` - Project documentation
- `/consignments/_kb/` - Module-specific docs

---

## 🎉 YOU'VE GOT THIS!

You're an autonomous AI agent capable of completing this project. Work methodically, test thoroughly, commit frequently, and deliver production-ready code.

**Remember:** This isn't a prototype. This is production code that will handle real purchase orders for 17 retail stores.

**Timeline:** Complete by November 6, 2025 (6 days from now)

**Let's build something amazing! 🚀**
