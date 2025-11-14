# Inventory Sync Module

**NEVER let inventory go out of sync between Vend POS and local system.**

## Purpose

This module ensures **bulletproof accuracy** between your Vend POS system and local inventory database. It continuously monitors for discrepancies, automatically fixes safe issues, and alerts on critical problems.

## The Problem It Solves

**Before this module:**
- ❌ Vend shows 50 units, system shows 42 → Which is correct?
- ❌ Transfer happens but only one end updates
- ❌ Sales recorded but inventory doesn't decrement
- ❌ Manual adjustments cause drift over time
- ❌ No audit trail of what changed when

**With this module:**
- ✅ Real-time sync verification every 5 minutes
- ✅ Automatic detection of any discrepancies
- ✅ Smart auto-fix for minor issues (1-2 units)
- ✅ Immediate alerts for critical issues (>10 units)
- ✅ Complete audit trail of every inventory change
- ✅ Transfer tracking with dual-end verification
- ✅ Force sync tools for manual reconciliation

## Features

### 1. Real-Time Sync Monitoring
- Checks all products every 5 minutes (configurable)
- Compares local inventory vs Vend inventory
- Detects discrepancies instantly
- Reports sync health metrics

### 2. Smart Discrepancy Handling
- **Minor Drift (1-2 units)**: Auto-fixes by choosing higher count (safer)
- **Major Drift (3-10 units)**: Triggers alert, requires review
- **Critical Drift (>10 units)**: Immediate alert, manual intervention required
- **Missing Data**: Flags as critical issue

### 3. Force Sync Tools
- **Force to Vend**: Push local count to Vend (local is master)
- **Force from Vend**: Pull Vend count to local (Vend is master)
- Useful for manual reconciliation after stock takes

### 4. Transfer Tracking
- Records transfers between outlets
- Updates both source and destination
- Syncs both ends with Vend
- Verifies sync immediately after transfer
- Complete audit trail

### 5. Complete Audit Trail
- Logs every inventory change (who, what, when, why)
- Tracks all sync operations
- Records all auto-fixes and manual adjustments
- Provides rollback capability

### 6. Health Monitoring
- Sync accuracy percentage (target: >99.5%)
- Perfect match rate
- Minor/major/critical drift counts
- Auto-fix success rate
- Alert rate
- Time since last successful sync

## Database Schema

**Main Tables:**
- `inventory_sync_checks` - Records every sync scan
- `inventory_sync_alerts` - Discrepancies requiring attention
- `inventory_change_log` - Complete audit trail
- `inventory_discrepancies` - Detailed discrepancy tracking
- `inventory_sync_metrics` - Daily health metrics
- `inventory_sync_config` - Per-product/outlet configuration

**Views:**
- `v_sync_health_24h` - 24-hour sync health summary
- `v_unresolved_alerts` - Current issues needing attention
- `v_product_sync_history` - 30-day change history per product

## API Endpoints

### Check Sync
```bash
GET /api/inventory-sync?action=check&product_id=123&outlet_id=1

Response:
{
  "success": true,
  "message": "Sync check completed",
  "data": {
    "scan_time": "2025-06-01 10:30:00",
    "products_checked": 1,
    "perfect_matches": 0,
    "minor_drifts": 1,
    "major_drifts": 0,
    "critical_issues": 0,
    "auto_fixed": 1,
    "discrepancies": [...],
    "sync_state": "perfect"
  }
}
```

### Force Sync to Vend
```bash
POST /api/inventory-sync?action=force_to_vend
Content-Type: application/json

{
  "product_id": 123,
  "outlet_id": 1
}

Response:
{
  "success": true,
  "message": "Successfully synced to Vend",
  "data": {
    "product_id": 123,
    "outlet_id": 1,
    "old_vend_count": 42,
    "new_vend_count": 50
  }
}
```

### Force Sync from Vend
```bash
POST /api/inventory-sync?action=force_from_vend
Content-Type: application/json

{
  "product_id": 123,
  "outlet_id": 1
}
```

### Record Transfer
```bash
POST /api/inventory-sync?action=transfer
Content-Type: application/json

{
  "product_id": 123,
  "from_outlet_id": 1,
  "to_outlet_id": 2,
  "quantity": 10
}

Response:
{
  "success": true,
  "message": "Transfer recorded and synced",
  "data": {
    "product_id": 123,
    "from_outlet": {
      "outlet_id": 1,
      "before": 50,
      "after": 40,
      "sync_verified": true
    },
    "to_outlet": {
      "outlet_id": 2,
      "before": 20,
      "after": 30,
      "sync_verified": true
    }
  }
}
```

### Get Sync Status
```bash
GET /api/inventory-sync?action=status

Response:
{
  "success": true,
  "data": {
    "health_24h": {
      "total_checks": 288,
      "total_products": 28800,
      "perfect_matches": 28650,
      "minor_drifts": 120,
      "major_drifts": 25,
      "critical_issues": 5,
      "auto_fixed": 110,
      "accuracy_percent": 99.48
    },
    "last_check": {...},
    "unresolved_alerts": 8,
    "overall_status": {
      "status": "excellent",
      "message": "Sync accuracy: 99.48%",
      "color": "green"
    }
  }
}
```

### Get Alerts
```bash
GET /api/inventory-sync?action=alerts&resolved=false&limit=50&offset=0

Response:
{
  "success": true,
  "data": {
    "alerts": [
      {
        "alert_id": 123,
        "product_id": 456,
        "product_name": "Product Name",
        "outlet_id": 1,
        "outlet_name": "Outlet Name",
        "alert_type": "major_drift",
        "local_count": 50,
        "vend_count": 42,
        "difference": 8,
        "resolved": false,
        "created_at": "2025-06-01 10:30:00"
      }
    ],
    "total": 8,
    "limit": 50,
    "offset": 0
  }
}
```

### Resolve Alert
```bash
POST /api/inventory-sync?action=resolve_alert
Content-Type: application/json

{
  "alert_id": 123,
  "resolution_notes": "Manually verified count, fixed in Vend"
}
```

### Get History
```bash
GET /api/inventory-sync?action=history&product_id=123&outlet_id=1&limit=100

Response:
{
  "success": true,
  "data": {
    "history": [
      {
        "log_id": 1,
        "product_id": 123,
        "product_name": "Product Name",
        "outlet_id": 1,
        "outlet_name": "Outlet Name",
        "change_type": "transfer_out",
        "old_count": 50,
        "new_count": 40,
        "difference": -10,
        "notes": "Transferred 10 units to outlet 2",
        "user_id": "john@example.com",
        "created_at": "2025-06-01 10:00:00"
      }
    ],
    "total": 156,
    "limit": 100,
    "offset": 0
  }
}
```

### Get Metrics
```bash
GET /api/inventory-sync?action=metrics&days=7

Response:
{
  "success": true,
  "data": {
    "metrics": [
      {
        "metric_id": 1,
        "metric_date": "2025-06-01",
        "total_checks": 288,
        "total_products_checked": 28800,
        "total_perfect_matches": 28650,
        "total_minor_drifts": 120,
        "total_major_drifts": 25,
        "total_critical_issues": 5,
        "total_auto_fixed": 110,
        "avg_sync_quality_score": 99.48
      }
    ],
    "days": 7
  }
}
```

## Usage Examples

### PHP Usage

```php
<?php
require_once 'modules/inventory-sync/autoload.php';

use CIS\InventorySync\InventorySyncEngine;

// Initialize
$pdo = new PDO("mysql:host=localhost;dbname=vend", "user", "pass");
$sync = new InventorySyncEngine($pdo);

// Check sync for all products
$report = $sync->checkSync();
echo "Sync state: {$report['sync_state']}\n";
echo "Perfect matches: {$report['perfect_matches']}\n";
echo "Issues: {$report['critical_issues']}\n";

// Check specific product
$report = $sync->checkSync($product_id = 123, $outlet_id = 1);

// Force sync to Vend
$result = $sync->forceSyncToVend(123, 1);
if ($result['success']) {
    echo "Synced: {$result['old_vend_count']} → {$result['new_vend_count']}\n";
}

// Record transfer
$result = $sync->recordTransfer([
    'product_id' => 123,
    'from_outlet_id' => 1,
    'to_outlet_id' => 2,
    'quantity' => 10,
]);
```

### Scheduled Sync Check (Cron)

```bash
# Run every 5 minutes
*/5 * * * * php /path/to/modules/inventory-sync/scripts/scheduled_sync.php
```

```php
<?php
// scripts/scheduled_sync.php
require_once __DIR__ . '/../autoload.php';

use CIS\InventorySync\InventorySyncEngine;

$pdo = new PDO("mysql:host=localhost;dbname=vend", "user", "pass");
$sync = new InventorySyncEngine($pdo);

// Check all products
$report = $sync->checkSync();

// Log results
error_log("Inventory Sync: {$report['sync_state']} - " .
          "Perfect: {$report['perfect_matches']}, " .
          "Issues: {$report['critical_issues']}");

// Alert if critical
if ($report['critical_issues'] > 0) {
    // Send notification
    mail('admin@example.com',
         'CRITICAL: Inventory Sync Issues',
         "Found {$report['critical_issues']} critical sync issues");
}
```

## Configuration

Environment variables (in `.env`):
```bash
VEND_API_URL=https://api.vendhq.com
VEND_API_TOKEN=your_api_token_here

# Sync settings
SYNC_FREQUENCY_MINUTES=5
AUTO_FIX_THRESHOLD=2
ALERT_THRESHOLD=5
CRITICAL_THRESHOLD=10
```

Per-product configuration in `inventory_sync_config` table:
```sql
INSERT INTO inventory_sync_config
(product_id, outlet_id, auto_fix_enabled, auto_fix_threshold, alert_threshold)
VALUES
(123, 1, TRUE, 2, 5);
```

## Dashboard Integration

```php
<?php
// Get sync health for dashboard
$controller = new InventorySyncController($pdo);

// Get status
$status = file_get_contents('/api/inventory-sync?action=status');
$data = json_decode($status, true);

// Display health badge
$status = $data['data']['overall_status'];
echo "<span class='badge badge-{$status['color']}'>
        {$status['status']}: {$status['message']}
      </span>";

// Display alerts
$alerts = file_get_contents('/api/inventory-sync?action=alerts&resolved=false&limit=5');
$alert_data = json_decode($alerts, true);

foreach ($alert_data['data']['alerts'] as $alert) {
    echo "<div class='alert alert-warning'>
            {$alert['product_name']} at {$alert['outlet_name']}:
            Local {$alert['local_count']} vs Vend {$alert['vend_count']}
            (diff: {$alert['difference']})
          </div>";
}
```

## Testing

```bash
# Install database schema
mysql -u user -p vend < modules/inventory-sync/schema.sql

# Test API endpoints
curl "http://localhost/api/inventory-sync?action=status"

curl -X POST "http://localhost/api/inventory-sync?action=check" \
  -H "Content-Type: application/json" \
  -d '{"product_id": 123, "outlet_id": 1}'

# Run sync check
php modules/inventory-sync/scripts/scheduled_sync.php
```

## Monitoring & Alerts

**Health Targets:**
- Sync accuracy: **>99.5%**
- Perfect match rate: **>95%**
- Auto-fix success: **>90%**
- Critical issues: **<5 per day**

**Alert Thresholds:**
- Minor drift: 1-2 units (auto-fix)
- Major drift: 3-10 units (review required)
- Critical drift: >10 units (immediate action)

**Monitoring Queries:**
```sql
-- Current sync health
SELECT * FROM v_sync_health_24h;

-- Unresolved critical alerts
SELECT * FROM v_unresolved_alerts
WHERE alert_type = 'critical_drift';

-- Products with frequent issues
SELECT product_id, COUNT(*) as issue_count
FROM inventory_sync_alerts
WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
GROUP BY product_id
HAVING issue_count > 5
ORDER BY issue_count DESC;
```

## Troubleshooting

**Issue: Sync accuracy dropping**
- Check Vend API connectivity
- Review auto-fix threshold (maybe too aggressive)
- Check for products with frequent manual adjustments

**Issue: Too many false positives**
- Increase auto-fix threshold
- Add product-specific configuration
- Review transfer recording process

**Issue: Critical alerts not resolving**
- Check force sync endpoints working
- Verify Vend API permissions
- Review audit trail for root cause

## Integration with Other Modules

**Forecasting Module:**
- Uses ConversionRateOptimizer to detect stock-outs
- Feeds sync data for better accuracy
- Alerts forecasting when inventory unreliable

**Ordering Module:**
- Uses sync health to validate order quantities
- Won't order if sync critical
- References audit trail for recent changes

**Transfer Module:**
- Uses recordTransfer() for all transfers
- Ensures dual-end sync verification
- Provides rollback on sync failure

## Files

```
modules/inventory-sync/
├── classes/
│   └── InventorySyncEngine.php     # Core sync logic (750 lines)
├── controllers/
│   └── InventorySyncController.php # API endpoints (400 lines)
├── docs/
│   └── (this file)
├── scripts/
│   └── scheduled_sync.php          # Cron job script
├── tests/
│   └── (unit tests - TODO)
├── schema.sql                      # Database schema (200 lines)
├── autoload.php                    # PSR-4 autoloader
└── README.md                       # This file
```

## Maintenance

**Daily:**
- Review unresolved alerts
- Check sync accuracy metric
- Verify scheduled sync running

**Weekly:**
- Review products with frequent issues
- Analyze auto-fix success rate
- Check alert threshold appropriateness

**Monthly:**
- Archive old sync checks (>90 days)
- Review and tune configuration
- Update Vend API integration if needed

---

**Built with ❤️ for The Vape Shed**
*NEVER let anything go out of sync!*
