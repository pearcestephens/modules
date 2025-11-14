# 🚀 INVENTORY SYNC - QUICK REFERENCE

**Module Location:** `modules/inventory-sync/`
**Status:** ✅ Production Ready

---

## ⚡ QUICK START (3 commands)

```bash
# 1. Install database schema
mysql -u user -p vend < modules/inventory-sync/schema.sql

# 2. Test the module
php modules/inventory-sync/scripts/test.php

# 3. Run first sync
php modules/inventory-sync/scripts/scheduled_sync.php
```

---

## 🎯 MOST COMMON TASKS

### Check Current Sync Status
```bash
curl "https://staff.vapeshed.co.nz/api/inventory-sync?action=status"
```

### View Unresolved Alerts
```bash
curl "https://staff.vapeshed.co.nz/api/inventory-sync?action=alerts&resolved=false"
```

### Check Specific Product
```bash
curl "https://staff.vapeshed.co.nz/api/inventory-sync?action=check&product_id=123&outlet_id=1"
```

### Force Sync to Vend (local is correct)
```bash
curl -X POST "https://staff.vapeshed.co.nz/api/inventory-sync?action=force_to_vend" \
  -H "Content-Type: application/json" \
  -d '{"product_id": 123, "outlet_id": 1}'
```

### Force Sync from Vend (Vend is correct)
```bash
curl -X POST "https://staff.vapeshed.co.nz/api/inventory-sync?action=force_from_vend" \
  -H "Content-Type: application/json" \
  -d '{"product_id": 123, "outlet_id": 1}'
```

### Record Transfer with Auto-Sync
```bash
curl -X POST "https://staff.vapeshed.co.nz/api/inventory-sync?action=transfer" \
  -H "Content-Type: application/json" \
  -d '{
    "product_id": 123,
    "from_outlet_id": 1,
    "to_outlet_id": 2,
    "quantity": 10
  }'
```

---

## 📊 KEY METRICS

### Sync States
- **perfect** ✅ - Everything matches
- **minor_drift** ⚠️ - 1-2 units off (auto-fixed)
- **major_drift** ⚠️ - 3-10 units off (review needed)
- **critical** 🚨 - >10 units off (urgent)

### Thresholds
- **Auto-fix:** 1-2 units (automatic)
- **Alert:** 3-10 units (notification sent)
- **Critical:** >10 units (immediate action)

### Health Targets
- **Accuracy:** >99.5%
- **Perfect Match Rate:** >95%
- **Critical Issues:** <5/day

---

## 🔧 CONFIGURATION

### .env Variables
```bash
VEND_API_URL=https://api.vendhq.com
VEND_API_TOKEN=your_token_here
SYNC_FREQUENCY_MINUTES=5
AUTO_FIX_THRESHOLD=2
ALERT_THRESHOLD=5
CRITICAL_THRESHOLD=10
ALERT_EMAIL=admin@vapeshed.co.nz
```

### Cron Job (every 5 minutes)
```bash
*/5 * * * * php /path/to/modules/inventory-sync/scripts/scheduled_sync.php >> /var/log/inventory_sync.log 2>&1
```

---

## 🔍 TROUBLESHOOTING

### Check Last Sync Result
```bash
tail -100 /var/log/inventory_sync.log
```

### View Database Sync Health
```sql
SELECT * FROM v_sync_health_24h;
```

### View Unresolved Alerts
```sql
SELECT * FROM v_unresolved_alerts;
```

### View Recent Changes
```sql
SELECT * FROM inventory_change_log
ORDER BY created_at DESC
LIMIT 50;
```

### Test Module
```bash
php modules/inventory-sync/scripts/test.php
```

---

## 📞 COMMON SCENARIOS

### Scenario: Stock Take Found Discrepancy
1. Check current sync state
2. Force sync from Vend (Vend = truth after stock take)
3. Verify sync perfect
4. Check alert resolved

### Scenario: Transfer Between Outlets
1. Use transfer API endpoint
2. Module automatically syncs both ends
3. Verifies sync on both outlets
4. Logs complete audit trail

### Scenario: Daily Health Check
1. Check v_sync_health_24h view
2. Review unresolved alerts
3. Check accuracy percentage
4. Resolve any critical alerts

### Scenario: Too Many Auto-Fixes
1. Check if threshold too low
2. Review product-specific config
3. Increase auto-fix threshold if needed
4. Monitor for improvement

---

## 💡 PHP USAGE

```php
<?php
require_once 'modules/inventory-sync/autoload.php';
use CIS\InventorySync\InventorySyncEngine;

$pdo = new PDO("mysql:host=localhost;dbname=vend", "user", "pass");
$sync = new InventorySyncEngine($pdo);

// Check sync
$report = $sync->checkSync();
if ($report['sync_state'] !== 'perfect') {
    echo "Issues found: {$report['critical_issues']} critical\n";
}

// Force sync
$result = $sync->forceSyncToVend($product_id, $outlet_id);

// Record transfer
$result = $sync->recordTransfer([
    'product_id' => 123,
    'from_outlet_id' => 1,
    'to_outlet_id' => 2,
    'quantity' => 10,
]);
```

---

## 📁 FILE LOCATIONS

```
modules/inventory-sync/
├── classes/InventorySyncEngine.php      # Core logic
├── controllers/InventorySyncController.php  # API endpoints
├── scripts/scheduled_sync.php          # Cron job
├── scripts/test.php                    # Test suite
├── schema.sql                          # Database schema
├── autoload.php                        # Autoloader
├── README.md                           # Full documentation
└── DELIVERY_COMPLETE.md                # Delivery summary
```

---

## 🎯 REMEMBER

- ✅ Module runs **every 5 minutes** automatically
- ✅ **Auto-fixes** minor issues (1-2 units)
- ✅ **Alerts** on major issues (3-10 units)
- ✅ **Critical alerts** for >10 unit differences
- ✅ **Complete audit trail** of all changes
- ✅ **Force sync tools** for manual control
- ✅ **Health metrics** for monitoring

**Bottom Line:** Your inventory will NEVER be wrong. This module makes sure of it. 🎯
