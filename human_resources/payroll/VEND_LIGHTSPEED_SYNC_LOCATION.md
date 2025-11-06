# ✅ VEND/LIGHTSPEED CONSIGNMENT SYNCING - LOCATION GUIDE

**Date:** November 5, 2025
**Status:** ✅ FULLY OPERATIONAL IN CONSIGNMENTS MODULE

---

## 📍 LOCATION

The Vend/Lightspeed consignment syncing system is **NOT in the payroll module**. It is located in:

```
/home/master/applications/jcepnzzkmj/public_html/modules/consignments/
```

This is a **separate, dedicated module** for managing stock transfers, purchase orders, and Lightspeed Retail Manager synchronization.

---

## 🗂️ KEY FILES & SERVICES

### 1. Core Services (in `/assets/services/`)

**LightspeedSyncService.php** (756 lines)
- **Location:** `/home/master/applications/jcepnzzkmj/public_html/assets/services/LightspeedSyncService.php`
- **Purpose:** Orchestrates synchronization between CIS and Lightspeed Retail Manager
- **Features:**
  - Purchase Order → Consignment sync
  - Product upload to consignments
  - Status synchronization (bidirectional)
  - Inventory updates
  - Webhook event handling
  - Async job processing via queue
  - Error recovery and retry logic
  - Sync status tracking

**QueueService.php** (610 lines)
- **Location:** `/home/master/applications/jcepnzzkmj/public_html/assets/services/QueueService.php`
- **Purpose:** Background job queue system
- **Features:**
  - Job scheduling and execution
  - Priority queue management
  - Job retry logic
  - Failed job tracking
  - Queue statistics

**VendAPI.php** (943 lines)
- **Location:** `/home/master/applications/jcepnzzkmj/public_html/assets/services/VendAPI.php`
- **Purpose:** Vend/Lightspeed Retail Manager API client
- **Features:**
  - Full REST API wrapper
  - Consignment CRUD operations
  - Product management
  - Outlet/supplier queries
  - Rate limiting
  - Error handling

---

### 2. CLI Tool

**lightspeed-cli.php** (813 lines)
- **Location:** `/home/master/applications/jcepnzzkmj/public_html/modules/consignments/cli/lightspeed-cli.php`
- **Purpose:** Command-line interface for managing Lightspeed sync operations
- **Usage:** `php modules/consignments/cli/lightspeed-cli.php <command>`

---

## 🚀 AVAILABLE COMMANDS

### Sync Commands
```bash
php lightspeed-cli.php sync:po <id>         # Sync single PO to Lightspeed
php lightspeed-cli.php sync:pending         # Sync all pending POs
php lightspeed-cli.php sync:status <id>     # Check sync status
php lightspeed-cli.php sync:retry <id>      # Retry failed sync
```

### Queue Commands
```bash
php lightspeed-cli.php queue:work           # Start queue worker daemon
php lightspeed-cli.php queue:stats          # Show queue statistics
php lightspeed-cli.php queue:list           # List recent jobs
php lightspeed-cli.php queue:retry <id>     # Retry failed job
php lightspeed-cli.php queue:cancel <id>    # Cancel job
php lightspeed-cli.php queue:clear          # Clear all jobs (DANGER)
php lightspeed-cli.php queue:prune <days>   # Prune old jobs
```

### Vend API Commands
```bash
php lightspeed-cli.php vend:test                # Test API connection
php lightspeed-cli.php vend:outlets             # List all outlets
php lightspeed-cli.php vend:suppliers           # List all suppliers
php lightspeed-cli.php vend:products <sku>      # Search products
php lightspeed-cli.php vend:consignment <id>    # Get consignment details
```

### Webhook Commands
```bash
php lightspeed-cli.php webhook:list             # List webhook subscriptions
php lightspeed-cli.php webhook:create <url>     # Create webhook subscription
php lightspeed-cli.php webhook:delete <id>      # Delete webhook subscription
```

### Config & Status
```bash
php lightspeed-cli.php config:show              # Show configuration
php lightspeed-cli.php config:set <key> <val>   # Set config value
php lightspeed-cli.php status                   # Show system status
php lightspeed-cli.php help                     # Show help message
```

---

## 🔄 HOW IT WORKS

### Automatic Sync Workflow

1. **User approves Purchase Order in UI**
2. **System automatically:**
   - Creates sync job in queue
   - Worker picks up job
   - Creates consignment in Lightspeed
   - Adds products to consignment
   - Sends consignment
   - Updates local PO with Lightspeed ID
   - Logs all operations

3. **When Lightspeed status changes:**
   - Webhook receiver gets notification
   - Updates local PO status
   - Logs state transition

### Manual Sync

```bash
# Sync specific PO
php lightspeed-cli.php sync:po PO-2025-001

# Sync all pending POs
php lightspeed-cli.php sync:pending

# Check sync status
php lightspeed-cli.php sync:status PO-2025-001

# Retry failed sync
php lightspeed-cli.php sync:retry PO-2025-001
```

---

## 📊 DATABASE TABLES

The sync system uses these tables:

```
queue_jobs                   - Background job queue
lightspeed_sync_log          - Sync operation history
lightspeed_api_log           - API request/response log
lightspeed_webhooks          - Raw webhook storage
lightspeed_mappings          - ID mappings (local ↔ Lightspeed)
vend_consignments            - Purchase orders (has lightspeed_consignment_id field)
queue_consignments           - Shadow cache of Lightspeed consignments
queue_consignment_products   - Shadow cache of consignment products
```

---

## 🔧 CONFIGURATION

### Environment Variables

The system expects these in `.env`:

```bash
VEND_DOMAIN=vapeshed                    # Your Vend domain prefix
VEND_API_TOKEN=your_token_here          # Vend API access token
```

### System Config

Configuration stored in `system_config` table:

```sql
SELECT config_value FROM system_config
WHERE config_key = 'lightspeed_sync_config';
```

**Default Settings:**
- `auto_sync_on_approval`: true
- `batch_size`: 50
- `retry_failed_after_minutes`: 30
- `delete_old_logs_after_days`: 30

---

## 📝 USAGE EXAMPLES

### Start the Queue Worker

```bash
# Run in background with screen
screen -dmS lightspeed php modules/consignments/cli/lightspeed-cli.php queue:work

# Or run in foreground for testing
php modules/consignments/cli/lightspeed-cli.php queue:work

# Check it's running
screen -ls
```

### Sync Purchase Orders

```bash
# Sync single PO
php lightspeed-cli.php sync:po PO-2025-123

# Sync all pending
php lightspeed-cli.php sync:pending

# Check status
php lightspeed-cli.php sync:status PO-2025-123
```

### Monitor Queue

```bash
# View queue statistics
php lightspeed-cli.php queue:stats

# List recent jobs
php lightspeed-cli.php queue:list

# Retry failed job
php lightspeed-cli.php queue:retry job_20251105_abc123
```

### Test API Connection

```bash
# Test Vend API
php lightspeed-cli.php vend:test

# List outlets
php lightspeed-cli.php vend:outlets

# Search products
php lightspeed-cli.php vend:products "SKU-123"
```

---

## 📈 MONITORING

### Check System Status

```bash
php lightspeed-cli.php status
```

**Shows:**
- Database connectivity (✓/✗)
- API connectivity (✓/✗)
- Queue statistics (pending, processing, completed, failed)
- Sync statistics (24h totals)
- Worker status

### View Recent Syncs

```sql
SELECT
    entity_id,
    operation,
    status,
    error_message,
    created_at
FROM lightspeed_sync_log
ORDER BY created_at DESC
LIMIT 50;
```

### Find Pending POs

```sql
SELECT
    id,
    public_id,
    state,
    lightspeed_consignment_id,
    lightspeed_status,
    created_at
FROM vend_consignments
WHERE transfer_category = 'PURCHASE_ORDER'
AND state = 'APPROVED'
AND lightspeed_consignment_id IS NULL
AND deleted_at IS NULL;
```

### Queue Statistics

```sql
SELECT
    status,
    COUNT(*) as count,
    MIN(created_at) as oldest,
    MAX(created_at) as newest
FROM queue_jobs
GROUP BY status;
```

---

## 🛠️ INTEGRATION WITH PAYROLL

**Question:** Why is this in consignments, not payroll?

**Answer:** This system is for **inventory/stock management**, not payroll. It handles:
- Stock transfers between outlets
- Purchase orders from suppliers
- Consignments in Lightspeed Retail Manager
- Inventory synchronization

**Payroll uses different Vend data:**
- The **payroll module** uses `VendServiceSDK.php` for payment snapshots
- Gets **Vend account payments** (customer payments via internet banking)
- Allocates these payments to staff for commission calculations
- Completely separate from consignment syncing

---

## 📚 DOCUMENTATION

Complete documentation exists in the consignments module:

```
/modules/consignments/PHASE_4_COMPLETE.md
/modules/consignments/LIGHTSPEED_QUICK_REF.md
/modules/consignments/PROJECT_COMPLETE.md
/modules/consignments/_kb/CORRECTED_BRIEF_LIGHTSPEED_NATIVE.md
/modules/consignments/_kb/STRATEGIC_REPORT_WHERE_WE_ARE_AND_WHERE_TO_GO.md
```

---

## 🎯 QUICK START

### 1. Test Connection
```bash
cd /home/master/applications/jcepnzzkmj/public_html/modules/consignments
php cli/lightspeed-cli.php vend:test
```

### 2. Start Worker
```bash
screen -dmS lightspeed php cli/lightspeed-cli.php queue:work
```

### 3. Sync Pending POs
```bash
php cli/lightspeed-cli.php sync:pending
```

### 4. Monitor Queue
```bash
php cli/lightspeed-cli.php queue:stats
```

---

## 🔗 RELATED SYSTEMS

### In Consignments Module
- ✅ Transfer Manager UI
- ✅ Purchase Order creation/approval
- ✅ Consignment receiving workflow
- ✅ Stock transfer management
- ✅ Lightspeed API integration
- ✅ Queue system for background jobs
- ✅ Webhook receiver for Lightspeed events

### In Payroll Module
- ✅ VendServiceSDK.php (payment snapshots)
- ✅ VendAllocationService.php (commission calculations)
- ✅ Staff payment tracking
- ✅ Commission reporting

**These are separate concerns with different purposes!**

---

## ⚠️ IMPORTANT NOTES

1. **Different Modules, Different Purposes:**
   - Consignments = Stock/inventory management
   - Payroll = Staff payments and commissions

2. **CLI Requires Database:**
   - The CLI tool needs `app.php` bootstrap for PDO connection
   - Cannot run standalone without database access

3. **Queue Worker Must Run:**
   - Background sync requires active queue worker
   - Use `screen` or systemd service for production

4. **API Rate Limits:**
   - Vend API has rate limits (enforced by VendAPI.php)
   - Queue batching prevents hitting limits

5. **Webhook Configuration:**
   - Lightspeed webhooks must point to webhook receiver
   - URL: `https://staff.vapeshed.co.nz/assets/services/webhooks/lightspeed_webhook_receiver.php`

---

## 📞 SUPPORT

### Log Locations
```
logs/lightspeed-worker.log      - Worker daemon logs
logs/webhook-receiver.log        - Incoming webhook events
logs/api-requests.log            - Outgoing API requests
logs/sync-operations.log         - Sync operation details
```

### Troubleshooting

**Sync Not Working?**
1. Check queue worker is running: `screen -ls`
2. Check API connection: `php cli/lightspeed-cli.php vend:test`
3. Check queue stats: `php cli/lightspeed-cli.php queue:stats`
4. View failed jobs: `php cli/lightspeed-cli.php queue:list failed`

**Job Stuck?**
```bash
# Retry specific job
php cli/lightspeed-cli.php queue:retry <job_id>

# Cancel stuck job
php cli/lightspeed-cli.php queue:cancel <job_id>
```

**Clear Failed Jobs?**
```bash
# Prune jobs older than 7 days
php cli/lightspeed-cli.php queue:prune 7
```

---

## ✅ SUMMARY

**The Vend/Lightspeed consignment syncing system is:**
- ✅ Located in `/modules/consignments/`
- ✅ Fully operational with 3,122 lines of code
- ✅ CLI tool with 22 commands
- ✅ Complete API integration
- ✅ Background queue system
- ✅ Webhook support
- ✅ Comprehensive logging
- ✅ Production-ready

**It is NOT in the payroll module because:**
- It handles inventory/stock, not payroll
- Separate concern with different purpose
- Has its own dedicated module

**To use it:**
```bash
cd /home/master/applications/jcepnzzkmj/public_html/modules/consignments
php cli/lightspeed-cli.php help
```

---

**Report Generated:** November 5, 2025
**System Status:** ✅ OPERATIONAL
**Documentation:** COMPLETE
