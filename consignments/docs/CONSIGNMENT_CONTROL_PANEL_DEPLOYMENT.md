# 🎛️ CONSIGNMENT CONTROL PANEL - DEPLOYMENT GUIDE

## 🚀 WHAT WAS BUILT

Complete consignment management system with:
- ✅ **Create** new consignments/transfers
- ✅ **Edit** existing consignments (move, reassign)
- ✅ **Delete** consignments (with audit trail)
- ✅ **Add/Remove** products
- ✅ **Adjust quantities** (increase/decrease)
- ✅ **Manual stock corrections** at source/destination
- ✅ **Move/reroute** consignments between outlets
- ✅ **Change status** (OPEN, SENT, RECEIVED, etc.)
- ✅ **Approve for Lightspeed** with state selection

---

## 📦 FILES CREATED

| File | Purpose | Status |
|------|---------|--------|
| `dashboard/api/consignment-control.php` | REST API for all operations | ✅ NEW |
| `dashboard/control-panels/consignment-control-modals.php` | UI modals & JavaScript | ✅ NEW |
| `sql/consignment-control-tables.sql` | Database schema | ✅ NEW |

---

## ⚡ QUICK DEPLOY (5 MINUTES)

### **Step 1: Create Database Tables**
```bash
cd /home/master/applications/jcepnzzkmj/public_html/assets/services/queue

mysql -u your_user -p your_database < sql/consignment-control-tables.sql
```

**OR run manually:**
```sql
-- Create deletion audit log
CREATE TABLE IF NOT EXISTS queue_consignment_deletion_log (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    consignment_id BIGINT UNSIGNED NOT NULL,
    reference VARCHAR(100) NOT NULL,
    deleted_by_user_id BIGINT UNSIGNED NOT NULL,
    reason TEXT,
    deleted_at DATETIME NOT NULL,
    consignment_data JSON NOT NULL,
    INDEX idx_deleted_at (deleted_at),
    INDEX idx_deleted_by (deleted_by_user_id)
) ENGINE=InnoDB;

-- Create inventory adjustments log
CREATE TABLE IF NOT EXISTS queue_inventory_adjustments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    outlet_id BIGINT UNSIGNED NOT NULL,
    variant_id BIGINT UNSIGNED NOT NULL,
    adjustment INT NOT NULL,
    reason TEXT NOT NULL,
    adjusted_by_user_id BIGINT UNSIGNED NOT NULL,
    consignment_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    INDEX idx_outlet_variant (outlet_id, variant_id),
    INDEX idx_consignment (consignment_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB;

-- Create notes table (if not exists)
CREATE TABLE IF NOT EXISTS queue_consignment_notes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    consignment_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    note TEXT NOT NULL,
    created_at DATETIME NOT NULL,
    INDEX idx_consignment (consignment_id),
    INDEX idx_user (user_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB;

-- Add columns if missing
ALTER TABLE queue_consignment_products 
ADD COLUMN IF NOT EXISTS updated_at DATETIME NULL;

ALTER TABLE queue_consignments 
ADD COLUMN IF NOT EXISTS created_by_user_id BIGINT UNSIGNED NULL;
```

---

### **Step 2: Add Modals to Consignment Hub**

Edit `dashboard/control-panels/consignment-hub.php` and add **BEFORE** the closing `<?php include footer.php; ?>`:

```php
<?php include __DIR__ . '/consignment-control-modals.php'; ?>
```

---

### **Step 3: Add Action Buttons to Transfer Cards**

Find the Recent Transfers section in `consignment-hub.php` and add action buttons to each transfer card:

```javascript
// Add to loadRecentTransfers() method after each transfer card
<div class="btn-group btn-group-sm mt-2" role="group">
    <button class="btn btn-outline-primary" onclick="consignmentHub.viewTransferDetails(${i})">
        <i class="fas fa-eye"></i> View
    </button>
    <button class="btn btn-outline-warning" onclick="consignmentHub.changeStatus(${consignmentId})">
        <i class="fas fa-exchange-alt"></i> Status
    </button>
    <button class="btn btn-outline-info" onclick="consignmentHub.openMoveConsignment(${consignmentId})">
        <i class="fas fa-route"></i> Move
    </button>
    <button class="btn btn-outline-success" onclick="consignmentHub.openLightspeedApproval(${consignmentId})">
        <i class="fas fa-check"></i> Approve
    </button>
    <button class="btn btn-outline-danger" onclick="consignmentHub.deleteConsignment(${consignmentId})">
        <i class="fas fa-trash"></i> Delete
    </button>
</div>
```

---

### **Step 4: Update Create Transfer Button**

Find the existing "New Transfer" button and update its onclick:

```javascript
<button class="btn btn-light btn-sm" onclick="consignmentHub.createTransfer()">
    <i class="fas fa-plus"></i> New Transfer
</button>
```

Make sure `createTransfer()` method opens the modal:

```javascript
createTransfer() {
    new bootstrap.Modal(document.getElementById('createConsignmentModal')).show();
}
```

---

## 🧪 TESTING

### **Test 1: Create Consignment**
1. Open Consignment Hub
2. Click "New Transfer"
3. Select source/destination outlets
4. Add notes
5. Click "Create Consignment"
6. ✅ Should see success message with reference number

### **Test 2: Delete Consignment**
1. Find a test consignment
2. Click "Delete" button
3. Enter reason
4. Confirm deletion
5. ✅ Should be removed and logged in `queue_consignment_deletion_log`

### **Test 3: Change Status**
1. Click "Status" on any consignment
2. Enter new status (OPEN, SENT, RECEIVED)
3. Enter reason
4. ✅ Should update and show in notes

### **Test 4: Stock Adjustment**
1. View a consignment with products
2. Click adjust stock button
3. Enter adjustment (+10 or -5)
4. Enter reason
5. ✅ Should log in `queue_inventory_adjustments`

### **Test 5: Approve for Lightspeed**
1. Click "Approve" button
2. Select push state (OPEN/SENT/RECEIVED)
3. Confirm
4. ✅ Should mark approved_for_lightspeed=1

---

## 📊 VERIFICATION QUERIES

### **Check Deleted Consignments**
```sql
SELECT * FROM queue_consignment_deletion_log 
ORDER BY deleted_at DESC LIMIT 10;
```

### **Check Stock Adjustments**
```sql
SELECT a.*, o.name as outlet_name
FROM queue_inventory_adjustments a
LEFT JOIN outlets o ON a.outlet_id = o.id
ORDER BY created_at DESC LIMIT 20;
```

### **Check Consignment Notes**
```sql
SELECT n.*, u.name as user_name
FROM queue_consignment_notes n
LEFT JOIN users u ON n.user_id = u.id
ORDER BY created_at DESC LIMIT 20;
```

### **Check Approved for Lightspeed**
```sql
SELECT id, reference, status, approved_for_lightspeed, approved_at
FROM queue_consignments
WHERE approved_for_lightspeed = 1
ORDER BY approved_at DESC;
```

---

## 🎯 API ENDPOINTS AVAILABLE

All accessible via `POST dashboard/api/consignment-control.php`:

| Action | Parameters | Description |
|--------|-----------|-------------|
| `create_consignment` | source_outlet_id, destination_outlet_id, type, status | Create new consignment |
| `edit_consignment` | consignment_id, [fields to update] | Edit existing consignment |
| `delete_consignment` | consignment_id, reason | Delete with audit trail |
| `add_products` | consignment_id, products[] | Add products to consignment |
| `remove_products` | consignment_id, product_ids[] | Remove products |
| `adjust_product_qty` | product_id, adjustment, reason | Adjust quantity |
| `adjust_source_stock` | consignment_id, variant_id, adjustment, reason | Adjust source stock |
| `adjust_destination_stock` | consignment_id, variant_id, adjustment, reason | Adjust destination stock |
| `move_consignment` | consignment_id, new_source_id, new_destination_id | Move/reroute |
| `change_status` | consignment_id, new_status, reason | Change status |
| `get_consignment_details` | consignment_id | Get full details |
| `approve_for_lightspeed` | consignment_id, push_state | Approve for push |

---

## 🔒 SECURITY FEATURES

- ✅ **Session authentication** required
- ✅ **User ID tracking** on all actions
- ✅ **Audit trail** for deletions
- ✅ **Stock adjustment logging**
- ✅ **Notes logged** for all changes
- ✅ **Transaction rollback** on errors
- ✅ **Validation** on all inputs

---

## 🚨 IMPORTANT NOTES

### **Consignments Already Pushed to Lightspeed**
- Cannot be edited via this interface
- Must be changed in Lightspeed first
- Prevents sync conflicts

### **Deletion Safety**
- Full consignment data backed up as JSON
- Can be recovered from `queue_consignment_deletion_log`
- User ID and reason tracked

### **Stock Adjustments**
- All adjustments logged in audit table
- Linked to consignment if related
- Requires reason for compliance

---

## 📞 USAGE EXAMPLES

### **Create Emergency Transfer (JavaScript)**
```javascript
const formData = new FormData();
formData.append('action', 'create_consignment');
formData.append('source_outlet_id', 5);
formData.append('destination_outlet_id', 12);
formData.append('type', 'EMERGENCY');
formData.append('status', 'OPEN');
formData.append('notes', 'Urgent: Stock out at Auckland CBD');

const response = await fetch('../api/consignment-control.php', {
    method: 'POST',
    body: formData
});

const result = await response.json();
console.log(result.reference); // e.g., "TR-20251008-ABC123"
```

### **Adjust Stock After Mistake (JavaScript)**
```javascript
const formData = new FormData();
formData.append('action', 'adjust_destination_stock');
formData.append('consignment_id', 12345);
formData.append('variant_id', 6789);
formData.append('adjustment', -5); // Remove 5 units
formData.append('reason', 'Damaged items found on delivery');

const response = await fetch('../api/consignment-control.php', {
    method: 'POST',
    body: formData
});
```

---

## ✅ CHECKLIST

Before going live:

- [ ] SQL tables created
- [ ] Modals included in consignment-hub.php
- [ ] Action buttons added to transfer cards
- [ ] Outlets populated in dropdowns
- [ ] API tested with real consignment
- [ ] Deletion audit working
- [ ] Stock adjustment logging verified
- [ ] Lightspeed approval tested

---

## 🎉 YOU'RE READY!

The complete control panel is now deployed. Staff can:
- Create transfers on the fly
- Edit/move/delete as needed
- Manually adjust stock if mistakes happen
- Approve for Lightspeed with state selection
- Full audit trail maintained

**Next:** Test with real data and monitor the `queue_consignment_notes` table for activity logs!
