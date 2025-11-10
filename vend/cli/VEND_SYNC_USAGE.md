# VEND SYNC MANAGER - Complete Usage Guide

**Production-Grade Enterprise Lightspeed/Vend Sync System**
Version: 1.0.0
Company: Ecigdis Limited / The Vape Shed
Author: CIS WebDev Boss Engineer

---

## Table of Contents

1. [Quick Start](#quick-start)
2. [Installation](#installation)
3. [Configuration](#configuration)
4. [Command Reference](#command-reference)
5. [Consignment State Machine](#consignment-state-machine)
6. [Cron Setup](#cron-setup)
7. [Troubleshooting](#troubleshooting)
8. [API Integration](#api-integration)

---

## Quick Start

### Test Connection
```bash
php vend-sync-manager.php test:connection
```

### Sync All Entities
```bash
php vend-sync-manager.php sync:all --full
```

### Check System Health
```bash
php vend-sync-manager.php health:check --verbose
```

### View Sync Status
```bash
php vend-sync-manager.php audit:sync-status
```

---

## Installation

### Prerequisites
- PHP 7.4+
- MySQL/MariaDB with jcepnzzkmj database
- CIS Bootstrap loaded
- Vend/Lightspeed API token

### Setup Steps

1. **Configure API Token** (choose one method):

   **Method 1: CIS Configuration Table (Recommended)**
   ```sql
   INSERT INTO configuration (config_label, config_value, config_description)
   VALUES ('vend_access_token', 'YOUR_TOKEN_HERE', 'Lightspeed/Vend API Access Token');
   ```

   **Method 2: Environment Variable**
   ```bash
   export VEND_API_TOKEN="YOUR_TOKEN_HERE"
   # OR
   export LIGHTSPEED_TOKEN="YOUR_TOKEN_HERE"
   ```

2. **Make Executable**
   ```bash
   chmod +x /path/to/vend-sync-manager.php
   ```

3. **Test Installation**
   ```bash
   php vend-sync-manager.php test:connection
   php vend-sync-manager.php --help
   ```

---

## Configuration

### Environment Variables

| Variable | Default | Description |
|----------|---------|-------------|
| `VEND_API_TOKEN` | - | Lightspeed API token (fallback) |
| `LIGHTSPEED_TOKEN` | - | Alternative token variable |
| `LIGHTSPEED_API_URL` | `https://api.vendhq.com/api/2.0` | API base URL |
| `LIGHTSPEED_TIMEOUT` | `30` | Request timeout (seconds) |
| `LIGHTSPEED_MAX_RETRIES` | `3` | Max retry attempts |
| `SYNC_BATCH_SIZE` | `100` | Database batch insert size |
| `SYNC_PAGE_SIZE` | `200` | API pagination size |
| `QUEUE_BATCH_SIZE` | `100` | Queue processing batch |
| `AUDIT_ENABLED` | `true` | Enable audit logging |

### Database Tables Required

- `vend_products`, `vend_sales`, `vend_customers`, `vend_inventory`
- `vend_consignments`, `vend_outlets`, `vend_categories`, `vend_brands`
- `vend_suppliers`, `vend_users`, `vend_queue`
- `vend_api_logs` (audit logging)
- `vend_sync_cursors` (incremental sync)
- `configuration` (for cis_vend_access_token)

---

## Command Reference

### Sync Commands

#### Products
```bash
# Full sync (all products)
php vend-sync-manager.php sync:products --full

# Incremental sync (since last cursor)
php vend-sync-manager.php sync:products

# Sync since specific date
php vend-sync-manager.php sync:products --since="2025-01-01"

# Sync for specific outlet
php vend-sync-manager.php sync:products --outlet=abc123
```

#### Sales
```bash
# Full sync
php vend-sync-manager.php sync:sales --full

# Date range sync
php vend-sync-manager.php sync:sales --since="2025-01-01" --until="2025-01-31"

# Incremental
php vend-sync-manager.php sync:sales
```

#### Customers
```bash
# Full customer sync
php vend-sync-manager.php sync:customers --full

# Incremental
php vend-sync-manager.php sync:customers
```

#### Inventory
```bash
# Sync all inventory
php vend-sync-manager.php sync:inventory

# Specific outlet
php vend-sync-manager.php sync:inventory --outlet=outlet_id

# Specific product
php vend-sync-manager.php sync:inventory --product=product_id
```

#### Consignments
```bash
# Full sync
php vend-sync-manager.php sync:consignments --full

# By status
php vend-sync-manager.php sync:consignments --status=OPEN
php vend-sync-manager.php sync:consignments --status=SENT

# Incremental
php vend-sync-manager.php sync:consignments
```

#### Reference Data (Outlets, Categories, Brands, Suppliers, Users)
```bash
# These are small datasets, always full sync
php vend-sync-manager.php sync:outlets
php vend-sync-manager.php sync:categories
php vend-sync-manager.php sync:brands
php vend-sync-manager.php sync:suppliers
php vend-sync-manager.php sync:users
```

#### Sync All
```bash
# Sync everything (full sync)
php vend-sync-manager.php sync:all --full

# Sync everything (incremental)
php vend-sync-manager.php sync:all

# Sync specific entities only
php vend-sync-manager.php sync:all --entity=products,customers,inventory
```

### Queue Commands

```bash
# Process 100 queue items
php vend-sync-manager.php queue:process --batch=100

# Process specific type
php vend-sync-manager.php queue:process --type=transfer

# View queue status
php vend-sync-manager.php queue:status

# View detailed statistics
php vend-sync-manager.php queue:stats

# Retry failed items
php vend-sync-manager.php queue:retry --failed-only
```

### Consignment Commands

#### Validate Consignment State
```bash
php vend-sync-manager.php consignment:validate --id=41677
```
**Output:**
- Current state
- Can edit? (Yes/No)
- Can cancel? (Yes/No)
- Can sync? (Yes/No)
- Is terminal? (Yes/No)
- Before/After sent status
- Allowed state transitions

#### Transition Consignment State
```bash
# Dry run (test without applying)
php vend-sync-manager.php consignment:transition --id=41677 --to=PACKING --dry-run

# Apply transition (with confirmation)
php vend-sync-manager.php consignment:transition --id=41677 --to=PACKING

# Valid transitions example:
# DRAFT → OPEN, CANCELLED
# OPEN → PACKING, CANCELLED, DRAFT
# PACKING → PACKAGED, OPEN
# PACKAGED → SENT, PACKING
# SENT → RECEIVING, CANCELLED (special)
# RECEIVING → PARTIAL, RECEIVED
# RECEIVED → CLOSED
# CLOSED → ARCHIVED
```

#### Cancel Consignment
```bash
# Cancel with reason
php vend-sync-manager.php consignment:cancel --id=41677 --reason="Order cancelled by customer"

# Cancel without reason
php vend-sync-manager.php consignment:cancel --id=41677
```
**Note:** Can only cancel consignments in DRAFT or OPEN state!

#### View State Machine Rules
```bash
php vend-sync-manager.php consignment:rules
```
**Shows:**
- All valid state transitions
- Cancellation rules by state
- Edit/amendment rules by state
- SENT timing rules
- Over-receipt policies

### Test Commands

```bash
# Test API connection
php vend-sync-manager.php test:connection

# Test authentication
php vend-sync-manager.php test:auth
```

### Health Commands

```bash
# Full health check
php vend-sync-manager.php health:check --verbose

# API connectivity only
php vend-sync-manager.php health:api

# Database connectivity only
php vend-sync-manager.php health:database
```

### Audit Commands

```bash
# View recent audit logs
php vend-sync-manager.php audit:logs

# Filter by entity
php vend-sync-manager.php audit:logs --entity=products

# Filter by date
php vend-sync-manager.php audit:logs --since="2025-01-01"

# Errors only
php vend-sync-manager.php audit:logs --errors-only

# View sync status (row counts)
php vend-sync-manager.php audit:sync-status

# Performance audit
php vend-sync-manager.php audit:performance --entity=products
```

### Utility Commands

```bash
# View sync cursor for entity
php vend-sync-manager.php util:cursor --entity=products

# Reset cursor (force full sync next time)
php vend-sync-manager.php util:cursor --entity=products --reset

# View version info
php vend-sync-manager.php util:version

# Cleanup old logs (30+ days)
php vend-sync-manager.php util:cleanup --days=30
```

---

## Consignment State Machine

### State Diagram

```
DRAFT ──────┐
    ↓       │
   OPEN ────┼───→ CANCELLED (terminal)
    ↓       │
 PACKING ───┘
    ↓
 PACKAGED
    ↓
   SENT ────────→ CANCELLED (special approval)
    ↓
 RECEIVING
    ↓  ↘
PARTIAL  RECEIVED
    ↓   ↙
 RECEIVED
    ↓
  CLOSED
    ↓
 ARCHIVED (terminal)
```

### State Descriptions

| State | Description | Editable | Cancellable | Syncable |
|-------|-------------|----------|-------------|----------|
| **DRAFT** | Initial creation, not sent to stores | ✅ 100% | ✅ Yes | ❌ No |
| **OPEN** | Active, can add/remove items | ✅ Yes | ✅ Yes | ✅ Yes |
| **PACKING** | Being prepared/packed | ✅ Quantities | ❌ No | ✅ Yes |
| **PACKAGED** | Ready to send | ✅ Can amend | ❌ No | ✅ Yes |
| **SENT** | In transit | ❌ No | ❌ Special only | ✅ Yes |
| **RECEIVING** | Receiving started | 🔄 Receive only | ❌ No | ✅ Yes |
| **PARTIAL** | Partially received | 🔄 Receive only | ❌ No | ✅ Yes |
| **RECEIVED** | Fully received | ✅ **CAN AMEND** | ❌ No | ✅ Yes |
| **CLOSED** | Completed | ❌ No | ❌ No | ✅ Yes |
| **CANCELLED** | Cancelled (terminal) | ❌ No | N/A | ❌ No |
| **ARCHIVED** | Archived (terminal) | ❌ No | N/A | ❌ No |

### Business Rules

#### Cancellation Rules
- ✅ **DRAFT**: Can cancel - not yet sent to stores
- ✅ **OPEN**: Can cancel - active but not packed
- ❌ **PACKING**: Cannot cancel - already being packed
- ❌ **PACKAGED**: Cannot cancel - ready to send
- ❌ **SENT**: Cannot cancel - in transit (contact management for special approval)
- ❌ **RECEIVING/RECEIVED**: Cannot cancel - already received

**Important:** Cancel button only visible to management users. Cancelled consignments are marked CANCELLED, NOT deleted.

#### Edit/Amendment Rules
- **DRAFT**: 100% editable (default state before sent to stores)
- **OPEN**: Can add/remove items, edit quantities
- **PACKING**: Can amend quantities while packing
- **PACKAGED**: Can amend once packed
- **SENT**: Cannot edit - already in transit
- **RECEIVING**: Cannot edit products, only receive quantities
- **RECEIVED**: **CAN AMEND** - add products, change quantities (no approval needed!)

#### SENT Timing
The SENT state is automatically applied:
- 12 hours after packing completes
- OR when courier webhook is received
- Whichever comes first

#### Over-Receipt Policy
**ANY quantity over stock is ACCEPTED without approval.**

---

## Cron Setup

### Recommended Cron Schedule

```cron
# Incremental sync every 15 minutes
*/15 * * * * cd /path/to/cli && php vend-sync-manager.php sync:all >> /var/log/vend-sync.log 2>&1

# Process queue every 5 minutes
*/5 * * * * cd /path/to/cli && php vend-sync-manager.php queue:process --batch=100 >> /var/log/vend-queue.log 2>&1

# Full sync daily at 2 AM
0 2 * * * cd /path/to/cli && php vend-sync-manager.php sync:all --full >> /var/log/vend-full-sync.log 2>&1

# Sync outlets/categories/brands hourly (small datasets)
0 * * * * cd /path/to/cli && php vend-sync-manager.php sync:outlets sync:categories sync:brands >> /var/log/vend-ref-data.log 2>&1

# Health check every hour
0 * * * * cd /path/to/cli && php vend-sync-manager.php health:check >> /var/log/vend-health.log 2>&1

# Cleanup old logs monthly
0 3 1 * * cd /path/to/cli && php vend-sync-manager.php util:cleanup --days=90 >> /var/log/vend-cleanup.log 2>&1
```

### CIS Smart Cron Integration

Add to `smart-cron` configuration:
```php
[
    'name' => 'Vend Incremental Sync',
    'schedule' => '*/15 * * * *',
    'command' => 'php /path/to/vend-sync-manager.php sync:all',
    'enabled' => true,
    'notification' => 'on_error',
],
[
    'name' => 'Vend Queue Processor',
    'schedule' => '*/5 * * * *',
    'command' => 'php /path/to/vend-sync-manager.php queue:process --batch=100',
    'enabled' => true,
],
```

---

## Troubleshooting

### Common Issues

#### 1. Token Not Configured
**Error:** `Lightspeed API token not configured`

**Solution:**
```sql
-- Check if token exists
SELECT * FROM configuration WHERE config_label = 'vend_access_token';

-- If not, add it
INSERT INTO configuration (config_label, config_value, config_description)
VALUES ('vend_access_token', 'YOUR_TOKEN', 'Vend API Token');

-- OR use environment variable
export VEND_API_TOKEN="YOUR_TOKEN"
```

#### 2. Connection Failed
**Error:** `Connection failed: HTTP 401/403`

**Solution:**
- Verify token is correct
- Check token hasn't expired
- Verify API permissions for token
- Test with: `php vend-sync-manager.php test:connection`

#### 3. Invalid State Transition
**Error:** `Invalid transition from OPEN to RECEIVED`

**Solution:**
```bash
# View current state and allowed transitions
php vend-sync-manager.php consignment:validate --id=<id>

# Follow the state machine rules
php vend-sync-manager.php consignment:rules
```

#### 4. Cannot Cancel Consignment
**Error:** `Cannot cancel consignment in state: SENT`

**Solution:**
- Can only cancel DRAFT or OPEN consignments
- For SENT/RECEIVED, contact management for special handling
- View rules: `php vend-sync-manager.php consignment:rules`

#### 5. Database Connection Failed
**Error:** `Unable to resolve PDO connection`

**Solution:**
- Ensure Bootstrap.php is loaded correctly
- Check database credentials in configuration
- Test: `php vend-sync-manager.php health:database`

#### 6. Memory Issues (Large Syncs)
**Error:** `Fatal error: Allowed memory size exhausted`

**Solution:**
```bash
# Increase PHP memory limit
php -d memory_limit=2G vend-sync-manager.php sync:all

# Or use batched sync
php vend-sync-manager.php sync:products --since="2025-01-01"
php vend-sync-manager.php sync:sales --since="2025-01-01" --until="2025-01-15"
```

### Debug Mode

```bash
# Verbose output
php vend-sync-manager.php health:check --verbose

# Check recent errors
php vend-sync-manager.php audit:logs --errors-only

# Test specific sync with dry run
php vend-sync-manager.php consignment:transition --id=123 --to=PACKING --dry-run
```

### Log Files

- Audit logs: `vend_api_logs` table
- Queue status: `vend_queue` table
- Application logs: Check Apache/PHP error logs
- Cron logs: `/var/log/vend-*.log`

---

## API Integration

### Using from PHP Code

```php
<?php
require_once 'vend-sync-manager.php';

// Initialize components
$output = new CLIOutput(false); // false = no colored output for web
$config = new ConfigManager();
$logger = new AuditLogger(true);
$api = new LightspeedAPIClient($config, $logger);
$db = new DatabaseManager($logger);
$queue = new QueueManager($db, $logger, $config);
$sync = new SyncEngine($api, $db, $queue, $logger, $output, $config);

// Sync products
$result = $sync->syncProducts(false); // false = incremental
echo "Synced: " . $result['synced'] . " products\n";

// Check consignment state
$canEdit = ConsignmentStateManager::canEdit('OPEN'); // true
$canCancel = ConsignmentStateManager::canCancel('SENT'); // false

// Validate transition
$validation = ConsignmentStateManager::validateTransition('OPEN', 'PACKING');
if ($validation['valid']) {
    echo "Transition allowed\n";
}

// Get queue stats
$stats = $queue->getStats();
echo "Pending: " . $stats['pending'] . "\n";
```

### JSON API Endpoint (To Be Implemented)

```bash
# Sync endpoint
POST /api/vend/sync/products
{
    "action": "sync",
    "full": false,
    "since": "2025-01-01"
}

# Queue endpoint
GET /api/vend/queue/status

# Consignment endpoint
POST /api/vend/consignment/transition
{
    "id": 41677,
    "to": "PACKING"
}
```

---

## Architecture

### 3-Tier Sync Model

```
┌─────────────────────────────────────────┐
│ TIER 1: Lightspeed API (Source of Truth)│
│   https://api.vendhq.com/api/2.0        │
└─────────────────────────────────────────┘
              ↓ ↑ (Webhooks + Poll)
┌─────────────────────────────────────────┐
│ TIER 2: Shadow Tables (vend_*)          │
│   + Queue (vend_queue)                  │
│   Local cache, staging, idempotency     │
└─────────────────────────────────────────┘
              ↓ ↑ (Queue Workers)
┌─────────────────────────────────────────┐
│ TIER 3: CIS Native Tables               │
│   (consignment_*, transfer_*, etc.)     │
│   Business logic, reporting, UI data    │
└─────────────────────────────────────────┘
```

### Class Structure

- **CLIOutput**: Beautiful terminal output with colors, tables, progress bars
- **ConfigManager**: Centralized config with CIS integration
- **LightspeedAPIClient**: API client with retry logic, pagination
- **DatabaseManager**: Database operations with batch upsert
- **QueueManager**: Queue processing with retry logic
- **SyncEngine**: Bidirectional sync orchestration
- **AuditLogger**: Comprehensive audit logging
- **ConsignmentStateManager**: State machine validation
- **CommandRouter**: CLI command dispatcher

### Supported Entities (28 Tables)

✓ Products (9,006)
✓ Sales (1,715,800)
✓ Sales Line Items (2,770,072)
✓ Customers (98,462)
✓ Inventory (189,293)
✓ Consignments (24,454)
✓ Consignment Line Items (131,326)
✓ Outlets (19)
✓ Categories (187)
✓ Brands (229)
✓ Suppliers (94)
✓ Users (59)
✓ Sales Payments (55,710)
✓ Product Qty History (80,027,741!)
✓ Queue (98,859)
✓ Plus 13 additional tables

---

## Performance Considerations

### Batch Sizes
- Database inserts: 100 rows (configurable via `SYNC_BATCH_SIZE`)
- API pagination: 200 rows (configurable via `SYNC_PAGE_SIZE`)
- Queue processing: 100 items (configurable via `QUEUE_BATCH_SIZE`)

### Incremental Sync
- Uses `vend_sync_cursors` table to track last sync version
- Only fetches changed records since last sync
- Significantly faster than full sync

### Large Datasets
For tables with millions of rows (sales, product_qty_history):
- Use date range filters: `--since` and `--until`
- Run during off-peak hours
- Monitor memory usage
- Consider splitting into multiple runs

---

## Security

- API token stored in `configuration` table (encrypted at rest)
- Audit logging of all sync operations
- State transition validation prevents invalid changes
- Cancellation restricted to DRAFT/OPEN states only
- Management approval required for special cases

---

## Support

For issues or questions:
1. Check logs: `php vend-sync-manager.php audit:logs --errors-only`
2. Run health check: `php vend-sync-manager.php health:check --verbose`
3. Review this documentation
4. Contact CIS WebDev team

---

## Version History

### v1.0.0 (2025-11-08)
- ✅ Initial production release
- ✅ 35+ CLI commands
- ✅ Full consignment state machine
- ✅ 28 table sync support
- ✅ Queue management system
- ✅ Comprehensive audit logging
- ✅ CIS config integration

---

**Built with ❤️ for The Vape Shed by CIS WebDev Boss Engineer**
