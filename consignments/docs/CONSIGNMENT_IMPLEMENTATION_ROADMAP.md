---
title: Consignment System Implementation Roadmap
category: Project Plan
tags: [consignment, implementation, migration, roadmap]
created: 2025-10-07
updated: 2025-10-07
status: Approved - Ready to Execute
priority: Critical
---

# 🚀 Consignment System Implementation Roadmap

**Project**: Queue V2 Consignment Management System  
**Approved By**: Pearce Stephens  
**Date**: October 7, 2025  
**Status**: ✅ **APPROVED - READY TO BUILD**  

---

## 📋 Executive Decisions (From Pearce)

### ✅ **Decision 1: Purchase Orders**
**Ruling**: "WE CAN GO TO A NEW PURCHASE ORDER SETUP NO WORRIES, JUST HAVE TO MIGRATE DATA."

**Action Plan**:
- ✅ Create new `queue_consignments` system (type='SUPPLIER')
- ✅ Migrate existing purchase order data
- ✅ Deprecate legacy PO tables after migration
- ✅ Clean cutover (no dual-write needed)

**Tables to Migrate FROM**:
- `purchase_orders` (CIS legacy)
- `purchase_order_items` (CIS legacy)
- `cishub_purchase_orders` (CISHub)
- `cishub_purchase_order_lines` (CISHub)
- `ls_purchase_orders` (Lightspeed, if active)
- `ls_purchase_order_lines` (Lightspeed, if active)

**Tables to Migrate TO**:
- `queue_consignments` (master records)
- `queue_consignment_products` (line items)

---

### ✅ **Decision 2: Transfers**
**Ruling**: "TRANSFERS IS PRETTY ALREADY SETUP"

**Action Plan**:
- ✅ Keep existing transfer system (48 tables)
- ✅ New OUTLET consignments integrate with existing transfers
- ✅ Consignments create/update transfer records automatically
- ✅ No migration needed (coexistence strategy)

**Integration Strategy**:
```php
// When creating OUTLET consignment
$consignment = createConsignment(['type' => 'OUTLET', ...]);

// Auto-create matching transfer record
$transfer = createTransfer([
    'source_outlet_id' => $consignment->source_outlet_id,
    'destination_outlet_id' => $consignment->destination_outlet_id,
    'consignment_id' => $consignment->id, // Link them
    'status' => mapConsignmentStatusToTransferStatus($consignment->status)
]);

// Keep both systems in sync
$consignment->cis_transfer_id = $transfer->id;
$consignment->save();
```

**Benefits**:
- ✅ Keep existing transfer features (AI insights, tracking, labels, metrics)
- ✅ Gain Lightspeed sync via consignments
- ✅ Zero disruption to current operations

---

### ✅ **Decision 3: Juice Transfers**
**Ruling**: "JUICE TRANSFERS NEED TO MOVE TO TRANSFERS"

**Action Plan**:
- ✅ Migrate `juice_transfers` → `transfers`
- ✅ Migrate `juice_transfers_items` → `transfer_items`
- ✅ Add `transfer_type` column = 'JUICE' or 'E_LIQUID'
- ✅ Update juice transfer UI to use unified transfer system

**Tables to Consolidate**:
- `juice_transfers` (4,000+ records est.) → `transfers`
- `juice_transfers_items` → `transfer_items`

**Migration Script**:
```sql
-- Add transfer_type column if not exists
ALTER TABLE transfers 
ADD COLUMN transfer_type ENUM('GENERAL', 'JUICE', 'STAFF', 'AUTOMATED') 
DEFAULT 'GENERAL' AFTER status;

-- Migrate juice_transfers
INSERT INTO transfers (
    transfer_type, source_outlet_id, destination_outlet_id,
    reference, status, created_at, updated_at, created_by_staff_id
)
SELECT 
    'JUICE' AS transfer_type,
    source_outlet_id,
    destination_outlet_id,
    transfer_reference AS reference,
    CASE 
        WHEN status = 'completed' THEN 'RECEIVED'
        WHEN status = 'in_transit' THEN 'SENT'
        WHEN status = 'cancelled' THEN 'CANCELLED'
        ELSE 'OPEN'
    END AS status,
    created_at,
    updated_at,
    created_by_user_id AS created_by_staff_id
FROM juice_transfers;

-- Migrate juice_transfers_items
INSERT INTO transfer_items (
    transfer_id, product_id, quantity_ordered, quantity_received,
    created_at
)
SELECT
    t.id AS transfer_id,
    jti.product_id,
    jti.quantity AS quantity_ordered,
    COALESCE(jti.quantity_received, 0) AS quantity_received,
    jti.created_at
FROM juice_transfers_items jti
JOIN juice_transfers jt ON jt.id = jti.juice_transfer_id
JOIN transfers t ON t.reference = jt.transfer_reference AND t.transfer_type = 'JUICE';
```

**Validation**:
```sql
-- Verify counts match
SELECT 
    (SELECT COUNT(*) FROM juice_transfers) AS legacy_count,
    (SELECT COUNT(*) FROM transfers WHERE transfer_type='JUICE') AS migrated_count;
```

---

### ✅ **Decision 4: Staff Transfers**
**Ruling**: "STAFF INSTORE STOCK TRANSFERS NEEDS TO MOVE TO TRANSFERS AS WELL"

**Action Plan**:
- ✅ Migrate `staff_transfers` → `transfers`
- ✅ Migrate `staff_transfers_products` → `transfer_items`
- ✅ Migrate `staff_transfers_shipments` → `transfer_shipments`
- ✅ Add `transfer_type` = 'STAFF'
- ✅ Update staff transfer UI to use unified system

**Tables to Consolidate**:
- `staff_transfers` → `transfers`
- `staff_transfers_products` → `transfer_items`
- `staff_transfers_shipments` → `transfer_shipments`

**Migration Script**:
```sql
-- Migrate staff_transfers
INSERT INTO transfers (
    transfer_type, source_outlet_id, destination_outlet_id,
    reference, status, notes, created_at, updated_at, created_by_staff_id
)
SELECT 
    'STAFF' AS transfer_type,
    source_outlet_id,
    destination_outlet_id,
    reference_number AS reference,
    CASE 
        WHEN status = 'completed' THEN 'RECEIVED'
        WHEN status = 'shipped' THEN 'SENT'
        WHEN status = 'cancelled' THEN 'CANCELLED'
        ELSE 'OPEN'
    END AS status,
    notes,
    created_at,
    updated_at,
    staff_id AS created_by_staff_id
FROM staff_transfers;

-- Migrate staff_transfers_products
INSERT INTO transfer_items (
    transfer_id, product_id, quantity_ordered, quantity_received,
    created_at
)
SELECT
    t.id AS transfer_id,
    stp.product_id,
    stp.quantity AS quantity_ordered,
    COALESCE(stp.quantity_received, 0) AS quantity_received,
    stp.created_at
FROM staff_transfers_products stp
JOIN staff_transfers st ON st.id = stp.staff_transfer_id
JOIN transfers t ON t.reference = st.reference_number AND t.transfer_type = 'STAFF';

-- Migrate staff_transfers_shipments (if shipment tracking exists)
INSERT INTO transfer_shipments (
    transfer_id, carrier, tracking_number, shipped_at, created_at
)
SELECT
    t.id AS transfer_id,
    sts.carrier,
    sts.tracking_number,
    sts.shipped_at,
    sts.created_at
FROM staff_transfers_shipments sts
JOIN staff_transfers st ON st.id = sts.staff_transfer_id
JOIN transfers t ON t.reference = st.reference_number AND t.transfer_type = 'STAFF';
```

---

## 🗺️ Implementation Timeline

### **Phase 0: Database Setup** (Week 1 - Days 1-2)

**Goal**: Create new tables, verify schema.

**Tasks**:
1. ✅ Run migration: `005_create_consignment_tables.sql`
2. ✅ Verify 5 tables created with all indexes/FKs
3. ✅ Add `transfer_type` column to `transfers` table
4. ✅ Backup all legacy tables before migration

**Deliverables**:
- 5 new `queue_consignment*` tables
- `transfers.transfer_type` column added
- Full database backup

**Commands**:
```bash
# Backup first
mysqldump -u jcepnzzkmj -p jcepnzzkmj > /private_html/backups/pre_consignment_migration_$(date +%F).sql

# Run migration
mysql -u jcepnzzkmj -p jcepnzzkmj < migrations/005_create_consignment_tables.sql

# Add transfer_type column
mysql -u jcepnzzkmj -p jcepnzzkmj -e "
ALTER TABLE transfers 
ADD COLUMN transfer_type ENUM('GENERAL', 'JUICE', 'STAFF', 'AUTOMATED') 
DEFAULT 'GENERAL' AFTER status;
"

# Verify
mysql -u jcepnzzkmj -p jcepnzzkmj -e "
SHOW TABLES LIKE 'queue_consignment%';
DESCRIBE transfers;
"
```

---

### **Phase 1: Data Migration - Purchase Orders** (Week 1 - Days 3-5)

**Goal**: Migrate all legacy PO data to new consignment system.

**Tasks**:
1. ✅ Audit existing PO tables (count records, identify active systems)
2. ✅ Create migration script: `006_migrate_purchase_orders_to_consignments.sql`
3. ✅ Run migration in staging environment
4. ✅ Validate data integrity (counts, FK relationships)
5. ✅ Run migration in production
6. ✅ Mark legacy PO tables as read-only

**Migration Priority**:
1. **Active POs first** (status != 'completed' and != 'cancelled')
2. **Recent completed POs** (last 12 months)
3. **Historical POs** (older than 12 months, optional)

**Data Mapping**:

| Legacy Table | Legacy Column | New Table | New Column | Notes |
|-------------|---------------|-----------|------------|-------|
| purchase_orders | id | queue_consignments | cis_purchase_order_id | Link back to legacy |
| purchase_orders | po_number | queue_consignments | reference | PO reference |
| purchase_orders | supplier_id | queue_consignments | supplier_id | FK to vend_suppliers |
| purchase_orders | outlet_id | queue_consignments | destination_outlet_id | Destination |
| purchase_orders | status | queue_consignments | status | Map: completed→RECEIVED, sent→SENT, etc. |
| purchase_orders | created_at | queue_consignments | created_at | Timestamp |
| purchase_order_items | id | queue_consignment_products | cis_line_item_id | Legacy FK |
| purchase_order_items | product_id | queue_consignment_products | cis_product_id | CIS product FK |
| purchase_order_items | quantity | queue_consignment_products | count_ordered | Ordered qty |
| purchase_order_items | quantity_received | queue_consignment_products | count_received | Received qty |
| purchase_order_items | unit_cost | queue_consignment_products | cost_per_unit | Cost |

**Validation Queries**:
```sql
-- Count records to migrate
SELECT 
    'purchase_orders' AS table_name,
    COUNT(*) AS total,
    COUNT(CASE WHEN status NOT IN ('completed', 'cancelled') THEN 1 END) AS active,
    COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH) THEN 1 END) AS recent
FROM purchase_orders

UNION ALL

SELECT 
    'cishub_purchase_orders',
    COUNT(*),
    COUNT(CASE WHEN status NOT IN ('completed', 'cancelled') THEN 1 END),
    COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH) THEN 1 END)
FROM cishub_purchase_orders;
```

**Deliverables**:
- Migration script: `006_migrate_purchase_orders_to_consignments.sql`
- Validation report: `PO_MIGRATION_VALIDATION.md`
- Legacy tables marked read-only (GRANT SELECT only)

---

### **Phase 2: Data Migration - Juice & Staff Transfers** (Week 1 - Day 5 to Week 2 - Day 2)

**Goal**: Consolidate juice_transfers and staff_transfers into main transfers table.

**Tasks**:
1. ✅ Migrate `juice_transfers` → `transfers` (type='JUICE')
2. ✅ Migrate `staff_transfers` → `transfers` (type='STAFF')
3. ✅ Update FK relationships in dependent tables
4. ✅ Validate data integrity
5. ✅ Mark legacy tables as read-only

**Migration Scripts**:
- `007_migrate_juice_transfers.sql`
- `008_migrate_staff_transfers.sql`

**Validation Queries**:
```sql
-- Verify juice transfers migrated
SELECT 
    (SELECT COUNT(*) FROM juice_transfers) AS legacy_count,
    (SELECT COUNT(*) FROM transfers WHERE transfer_type='JUICE') AS migrated_count,
    (SELECT COUNT(*) FROM juice_transfers_items) AS legacy_items,
    (SELECT COUNT(*) FROM transfer_items ti JOIN transfers t ON t.id=ti.transfer_id WHERE t.transfer_type='JUICE') AS migrated_items;

-- Verify staff transfers migrated
SELECT 
    (SELECT COUNT(*) FROM staff_transfers) AS legacy_count,
    (SELECT COUNT(*) FROM transfers WHERE transfer_type='STAFF') AS migrated_count;
```

**Deliverables**:
- 2 migration scripts
- Validation report: `TRANSFER_CONSOLIDATION_VALIDATION.md`
- Legacy tables marked read-only

---

### **Phase 3: Handler Implementation** (Week 2 - Days 3-5)

**Goal**: Build core consignment handler classes.

**Tasks**:
1. ✅ Implement `ConsignmentStateMachine.php`
2. ✅ Implement `ConsignmentHandler.php` (orchestrator)
3. ✅ Implement `ConsignmentCommandFactory.php`
4. ✅ Implement `CreateConsignmentCommand.php` (proof-of-concept)
5. ✅ Unit tests for state machine

**Files to Create**:
```
src/domain/
  ├─ ConsignmentStateMachine.php
  ├─ ConsignmentCommandFactory.php
  └─ commands/
      ├─ ConsignmentCommandInterface.php
      ├─ CreateConsignmentCommand.php
      ├─ AddProductCommand.php
      ├─ UpdateConsignmentStatusCommand.php
      └─ CancelConsignmentCommand.php

src/handlers/
  └─ ConsignmentHandler.php

tests/unit/
  └─ ConsignmentStateMachineTest.php
```

**State Machine Example**:
```php
class ConsignmentStateMachine
{
    private const TRANSITIONS = [
        'SUPPLIER' => [
            'OPEN' => ['SENT', 'DISPATCHED', 'CANCELLED'],
            'SENT' => ['DISPATCHED', 'CANCELLED'],
            'DISPATCHED' => ['RECEIVED', 'CANCELLED'],
            'RECEIVED' => [], // Terminal
            'CANCELLED' => [] // Terminal
        ],
        'OUTLET' => [
            'OPEN' => ['SENT', 'CANCELLED'],
            'SENT' => ['RECEIVED', 'CANCELLED'],
            'RECEIVED' => [],
            'CANCELLED' => []
        ],
        // ... RETURN, STOCKTAKE
    ];

    public function canTransition(string $type, string $fromStatus, string $toStatus): bool
    {
        return in_array($toStatus, self::TRANSITIONS[$type][$fromStatus] ?? []);
    }

    public function validateTransition(array $consignment, string $toStatus): array
    {
        $errors = [];
        
        if (!$this->canTransition($consignment['type'], $consignment['status'], $toStatus)) {
            $errors[] = "Invalid transition: {$consignment['status']} → {$toStatus}";
        }
        
        if ($toStatus === 'RECEIVED' && $consignment['type'] === 'SUPPLIER') {
            // Must have at least one product with received > 0
            $hasReceived = $this->hasReceivedProducts($consignment['id']);
            if (!$hasReceived) {
                $errors[] = "Cannot mark RECEIVED: no products have received quantity";
            }
        }
        
        return $errors;
    }
}
```

**Deliverables**:
- 10+ PHP class files
- Unit test suite (80%+ coverage on state machine)
- API documentation for each handler

---

### **Phase 4: API Gateway Endpoints** (Week 3 - Days 1-3)

**Goal**: Build user-facing API endpoints.

**Tasks**:
1. ✅ Create 9 API endpoints in `public/api/consignments/`
2. ✅ Implement authentication/authorization
3. ✅ Implement trace_id generation
4. ✅ Implement job enqueuing
5. ✅ Integration tests for all endpoints

**Endpoints to Create**:
```
public/api/consignments/
  ├─ create.php           # POST - Create consignment
  ├─ update.php           # PUT - Update consignment
  ├─ add-product.php      # POST - Add product to consignment
  ├─ mark-sent.php        # POST - Mark as SENT
  ├─ mark-received.php    # POST - Mark as RECEIVED
  ├─ cancel.php           # POST - Cancel consignment
  ├─ reverse-action.php   # POST - Undo action
  ├─ audit-trail.php      # GET - Get audit log
  └─ status.php           # GET - Get job status
```

**Standard API Response Format**:
```json
{
  "success": true,
  "job_id": 789,
  "trace_id": "csn_2025100712345_abc123",
  "status_url": "/api/consignments/status/789",
  "message": "Request received, processing...",
  "data": {
    "consignment_id": 42,
    "vend_consignment_id": "vend-csn-xyz"
  }
}
```

**Deliverables**:
- 9 API endpoint files
- Integration test suite
- API documentation: `API_ENDPOINTS_REFERENCE.md`

---

### **Phase 5: Inventory Sync Service** (Week 3 - Days 4-5)

**Goal**: Automatically update CIS inventory when consignments are received.

**Tasks**:
1. ✅ Implement `ConsignmentInventorySyncService.php`
2. ✅ Identify CIS inventory tables (products, stock_levels, etc.)
3. ✅ Implement transaction-safe updates
4. ✅ Implement rollback support
5. ✅ Integration tests

**Service Example**:
```php
class ConsignmentInventorySyncService
{
    public function syncInventory(int $consignmentId): array
    {
        $consignment = $this->loadConsignment($consignmentId);
        
        if ($consignment['status'] !== 'RECEIVED') {
            throw new Exception("Cannot sync: consignment not RECEIVED");
        }
        
        $products = $this->loadProducts($consignmentId);
        $syncResults = [];
        
        $this->db->beginTransaction();
        
        try {
            foreach ($products as $product) {
                if ($product['inventory_updated']) {
                    continue; // Already synced
                }
                
                $delta = $product['count_received'] - $product['count_damaged'];
                
                // Update CIS inventory
                $prevQty = $this->getProductQuantity($product['cis_product_id'], $consignment['destination_outlet_id']);
                $newQty = $prevQty + $delta;
                
                $this->updateProductQuantity(
                    $product['cis_product_id'],
                    $consignment['destination_outlet_id'],
                    $newQty
                );
                
                // Log sync
                $this->logInventorySync([
                    'consignment_id' => $consignmentId,
                    'consignment_product_id' => $product['id'],
                    'cis_product_id' => $product['cis_product_id'],
                    'cis_outlet_id' => $consignment['destination_outlet_id'],
                    'quantity_delta' => $delta,
                    'previous_quantity' => $prevQty,
                    'new_quantity' => $newQty,
                    'sync_status' => 'completed'
                ]);
                
                // Mark product as updated
                $this->markProductUpdated($product['id']);
                
                $syncResults[] = [
                    'product_id' => $product['cis_product_id'],
                    'delta' => $delta,
                    'new_quantity' => $newQty
                ];
            }
            
            $this->db->commit();
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
        
        return $syncResults;
    }
}
```

**Deliverables**:
- `ConsignmentInventorySyncService.php`
- Integration tests
- Documentation: `INVENTORY_SYNC_GUIDE.md`

---

### **Phase 6: UI Updates - Purchase Orders** (Week 4 - Days 1-3)

**Goal**: Update CIS purchase order forms to use new consignment system.

**Tasks**:
1. ✅ Update PO creation form → call `/api/consignments/create`
2. ✅ Update PO detail page → show consignment status
3. ✅ Add "Mark as Sent" button
4. ✅ Add "Mark as Received" button
5. ✅ Add audit trail viewer
6. ✅ Update PO list page → filter by status

**Files to Update**:
```
CIS Purchase Order Pages (location TBD):
  ├─ purchase-order-create.php    # Create PO form
  ├─ purchase-order-detail.php    # PO details + actions
  ├─ purchase-order-list.php      # List all POs
  └─ purchase-order-receive.php   # Receiving interface
```

**UI Changes**:
- Form submits to `/api/consignments/create` instead of legacy PO handler
- Show real-time job status (polling `/api/consignments/status/{job_id}`)
- Display trace_id for troubleshooting
- Add "View Audit Trail" link

**Deliverables**:
- Updated UI files
- User acceptance testing (UAT) with 5 staff members
- Training documentation

---

### **Phase 7: UI Updates - Transfers** (Week 4 - Days 4-5)

**Goal**: Update transfer forms to support unified system.

**Tasks**:
1. ✅ Update juice transfer forms → use `transfers` table (type='JUICE')
2. ✅ Update staff transfer forms → use `transfers` table (type='STAFF')
3. ✅ Add transfer_type filter to transfer list page
4. ✅ Ensure OUTLET consignments auto-create transfers

**Files to Update**:
```
Juice Transfer Pages:
  ├─ juice-transfer-create.php
  ├─ juice-transfer-list.php
  └─ juice-transfer-detail.php

Staff Transfer Pages:
  ├─ staff-transfer-create.php
  ├─ staff-transfer-list.php
  └─ staff-transfer-detail.php

General Transfer Pages:
  └─ transfer-list.php → Add filter for transfer_type
```

**Integration Logic**:
```php
// In ConsignmentHandler for OUTLET type
public function handleOutletConsignment($consignment)
{
    // Step 1: Create/update Lightspeed consignment via API
    $vendResponse = $this->vendApi->createConsignment([...]);
    
    // Step 2: Create matching transfer record
    $transfer = $this->transferService->create([
        'transfer_type' => 'AUTOMATED', // or 'GENERAL'
        'source_outlet_id' => $consignment['source_outlet_id'],
        'destination_outlet_id' => $consignment['destination_outlet_id'],
        'reference' => $consignment['reference'],
        'status' => $this->mapStatus($consignment['status']),
        'consignment_id' => $consignment['id']
    ]);
    
    // Step 3: Link them
    $this->updateConsignment($consignment['id'], [
        'cis_transfer_id' => $transfer->id
    ]);
}
```

**Deliverables**:
- Updated transfer UI files
- Integration verified (OUTLET consignment → transfer created)
- User documentation

---

### **Phase 8: Dashboard Module** (Week 5 - Days 1-5)

**Goal**: Build unified consignment management dashboard.

**Tasks**:
1. ✅ Create `public/admin/modules/consignment-management.php`
2. ✅ Implement list view (filterable, searchable)
3. ✅ Implement detail view (state timeline, products, actions)
4. ✅ Implement trace viewer
5. ✅ Implement bulk actions (mark sent, export)
6. ✅ E2E testing with real Lightspeed sandbox

**Dashboard Features**:
- **List View**: Filter by type, status, supplier, date range
- **Detail View**: Complete consignment info with action buttons
- **State Timeline**: Visual workflow progress
- **Action History**: All actions with undo buttons (where allowed)
- **Trace Viewer**: Full audit log from button → API → inventory
- **Performance Metrics**: Lead times, fill rates, costs

**Deliverables**:
- Dashboard module file
- UI/UX mockups
- E2E test suite
- User training videos

---

### **Phase 9: Testing & Validation** (Week 6 - Days 1-3)

**Goal**: Comprehensive testing across all systems.

**Test Types**:

1. **Unit Tests**: All handler classes, state machine, commands
2. **Integration Tests**: API endpoints, inventory sync, Lightspeed sync
3. **E2E Tests**: Full workflows (create PO → send → receive → inventory updated)
4. **Load Tests**: 100 concurrent consignment creations
5. **Regression Tests**: Ensure existing transfer features still work

**Test Scenarios**:

| Scenario | Steps | Expected Result |
|----------|-------|-----------------|
| Create SUPPLIER consignment | API call → handler → Lightspeed API | Consignment created, job completed |
| Mark as SENT | API call → state transition | Status = SENT, webhook fires |
| Partial receive | Bulk update received quantities | Status = DISPATCHED, partial inventory update |
| Full receive | Mark remaining products | Status = RECEIVED, inventory fully updated |
| Create OUTLET consignment | API call → handler → transfer created | Consignment + transfer linked |
| Cancel consignment (OPEN) | API call → state check → cancel | Status = CANCELLED, no inventory change |
| Undo add product (OPEN) | Reverse action API | Product removed, action marked reversed |

**Deliverables**:
- Test suite (200+ tests)
- Test report: `TESTING_COMPLETE.md`
- Performance benchmark results

---

### **Phase 10: Production Deployment** (Week 6 - Days 4-5)

**Goal**: Deploy to production with monitoring.

**Tasks**:
1. ✅ Final staging environment test
2. ✅ Production database backup
3. ✅ Deploy code to production
4. ✅ Run production migrations (with rollback ready)
5. ✅ Monitor for 48 hours
6. ✅ Staff training sessions (3 sessions, all outlets)

**Deployment Checklist**:
- [ ] Backup production DB (full + incremental)
- [ ] Deploy code to production server
- [ ] Run migrations (005, 006, 007, 008)
- [ ] Verify all tables created
- [ ] Smoke test: Create 1 test consignment
- [ ] Enable monitoring (error logs, API latency, job queue depth)
- [ ] Communicate to staff (email + Slack announcement)
- [ ] On-call support ready (Pearce + IT team)

**Rollback Plan** (if issues arise):
```bash
# Restore database
mysql -u jcepnzzkmj -p jcepnzzkmj < /private_html/backups/pre_consignment_migration_2025-10-07.sql

# Revert code
git revert <commit-hash>
git push origin main

# Restart PHP-FPM
service php8.2-fpm restart
```

**Deliverables**:
- Production deployment complete
- 48-hour stability report
- Staff training completion report

---

## 📊 Success Metrics

### **Performance Targets**:
- ✅ API Gateway response: < 100ms (p95)
- ✅ Job processing: < 5 seconds per consignment (p95)
- ✅ Inventory sync: < 15 seconds for 100 products (p95)
- ✅ Dashboard page load: < 2 seconds (p95)

### **Reliability Targets**:
- ✅ 99.9% job success rate
- ✅ Zero inventory discrepancies
- ✅ 100% audit trail completeness

### **Usability Targets**:
- ✅ Create consignment: < 2 minutes (staff feedback)
- ✅ Mark received: < 30 seconds (staff feedback)
- ✅ Real-time updates: < 30s refresh (polling interval)

---

## 🚨 Risk Mitigation

### **Risk 1: Data Loss During Migration**
**Mitigation**:
- ✅ Full database backup before every migration
- ✅ Test migrations in staging first
- ✅ Validate record counts before/after
- ✅ Keep legacy tables read-only (not deleted) for 6 months

### **Risk 2: Lightspeed API Downtime**
**Mitigation**:
- ✅ Queue system handles retries automatically
- ✅ Jobs moved to DLQ after 5 failures
- ✅ Manual replay available from dashboard
- ✅ Circuit breaker after 50% failure rate

### **Risk 3: Staff Training Gap**
**Mitigation**:
- ✅ 3 training sessions (all shifts covered)
- ✅ Video tutorials recorded
- ✅ Quick reference guide (1-page PDF)
- ✅ Helpdesk support 24/7 first week

### **Risk 4: Performance Degradation**
**Mitigation**:
- ✅ 32 database indexes created
- ✅ Load testing completed before production
- ✅ Query profiling enabled
- ✅ Caching strategy for dashboard

---

## 📋 Deliverables Checklist

### **Code**:
- [ ] 5 database tables created
- [ ] 10+ handler classes implemented
- [ ] 9 API endpoints created
- [ ] Dashboard module built
- [ ] 200+ unit/integration tests

### **Documentation**:
- [ ] API Reference (23,000 words) ✅ DONE
- [ ] Architecture Decision (18,000 words) ✅ DONE
- [ ] Implementation Roadmap (this document)
- [ ] Migration scripts (SQL)
- [ ] Testing report
- [ ] Staff training guide
- [ ] Troubleshooting guide

### **Data**:
- [ ] Purchase orders migrated
- [ ] Juice transfers migrated
- [ ] Staff transfers migrated
- [ ] Legacy tables archived
- [ ] Validation reports complete

---

## 🎯 Next Immediate Actions

### **Today (October 7, 2025)**:
1. ✅ Approve this roadmap (Pearce)
2. ⏳ Run database migration (create 5 consignment tables)
3. ⏳ Add `transfer_type` column to `transfers` table
4. ⏳ Backup all legacy tables

### **This Week (Week 1)**:
1. Migrate purchase order data
2. Migrate juice transfer data
3. Migrate staff transfer data
4. Begin handler implementation

### **Next Week (Week 2)**:
1. Complete handler classes
2. Build API endpoints
3. Implement inventory sync service

---

## 🏁 Final Approval

**Project Manager**: Pearce Stephens  
**Approval Date**: October 7, 2025  
**Approved**: ✅ YES  

**Scope Summary**:
- ✅ New purchase order system (migrate all legacy POs)
- ✅ Keep existing transfers (integration strategy)
- ✅ Consolidate juice transfers into main transfers table
- ✅ Consolidate staff transfers into main transfers table
- ✅ 6-week implementation timeline
- ✅ Zero disruption to operations

**Budget**: [To be determined]  
**Team**: [To be assigned]  
**Start Date**: October 7, 2025  
**Target Completion**: November 18, 2025  

---

**Ready to build?** Say:
- **"Run Phase 0"** - I'll create the database tables
- **"Show me migration scripts"** - I'll generate SQL for PO/transfer migrations
- **"Start building handlers"** - I'll implement ConsignmentStateMachine.php
- **"I need time"** - Take your time, comprehensive review recommended

🚀 **Let's build this!**
