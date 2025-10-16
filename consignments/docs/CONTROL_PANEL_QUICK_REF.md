# 🎛️ CONSIGNMENT CONTROL PANEL - QUICK REFERENCE

## ⚡ ONE-COMMAND DEPLOY
```bash
php bin/deploy-control-panel.php --execute
```

## 🎯 WHAT IT DOES
- ✅ Create transfers on the fly
- ✅ Move/reroute between outlets
- ✅ Delete consignments (with backup)
- ✅ Add/remove products
- ✅ Adjust stock at source/destination
- ✅ Manual quantity edits
- ✅ Full audit trail

## 📦 FILES
| File | Purpose |
|------|---------|
| `dashboard/api/consignment-control.php` | 12 API endpoints |
| `dashboard/control-panels/consignment-control-modals.php` | 5 modals + JS |
| `sql/consignment-control-tables.sql` | 3 new tables |
| `bin/deploy-control-panel.php` | One-click installer |

## 🔌 API ENDPOINTS
```javascript
POST dashboard/api/consignment-control.php

action=create_consignment          // Create new
action=delete_consignment          // Delete with backup
action=move_consignment           // Reroute
action=adjust_source_stock        // Fix source stock
action=adjust_destination_stock   // Fix destination stock
action=adjust_product_qty         // Change quantity
action=change_status              // Update status
action=approve_for_lightspeed     // Queue for push
```

## 🎨 UI USAGE
```javascript
// Open create modal
consignmentHub.createTransfer();

// Delete with confirmation
consignmentHub.deleteConsignment(12345);

// Move/reroute
consignmentHub.openMoveConsignment(12345);

// Stock adjustment
consignmentHub.openStockAdjustment(12345, 6789, 'source');

// Approve for Lightspeed
consignmentHub.openLightspeedApproval(12345);
```

## 🔒 SAFETY
- Session auth required
- User ID tracked
- Full audit logs
- Transaction rollback
- JSON backups of deletions

## 📊 MONITORING
```sql
-- Recent activity
SELECT * FROM queue_consignment_notes 
ORDER BY created_at DESC LIMIT 20;

-- Stock adjustments
SELECT * FROM queue_inventory_adjustments 
ORDER BY created_at DESC LIMIT 20;

-- Deleted consignments
SELECT * FROM queue_consignment_deletion_log 
ORDER BY deleted_at DESC LIMIT 10;
```

## ✅ TEST CHECKLIST
- [ ] Deploy: `php bin/deploy-control-panel.php --execute`
- [ ] Open: https://staff.vapeshed.co.nz/dashboard/control-panels/consignment-hub.php
- [ ] Click "New Transfer"
- [ ] Create test consignment
- [ ] Test move/delete/adjust
- [ ] Check audit logs

## 🚀 GO LIVE
```bash
cd /home/master/applications/jcepnzzkmj/public_html/assets/services/queue
php bin/deploy-control-panel.php --execute
```

**That's it! Full control panel deployed.**
