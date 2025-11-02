# Lightspeed Integration Quick Reference

**Version:** 2.0.0
**Date:** 2025-10-31

---

## 🚀 QUICK START

### 1. Run Database Migration
```bash
cd /modules/consignments/database/migrations
mysql -u jcepnzzkmj -p'wprKh9Jq63' jcepnzzkmj < 2025-10-31-lightspeed-integration.sql
```

### 2. Test Connection
```bash
php modules/consignments/cli/lightspeed-cli.php vend:test
```

### 3. Start Worker
```bash
# Run in background
screen -dmS lightspeed php modules/consignments/cli/lightspeed-cli.php queue:work

# Or run in foreground (for testing)
php modules/consignments/cli/lightspeed-cli.php queue:work
```

### 4. Configure Webhook
```bash
php modules/consignments/cli/lightspeed-cli.php webhook:create \
  https://staff.vapeshed.co.nz/assets/services/webhooks/lightspeed_webhook_receiver.php
```

---

## 📋 COMMON COMMANDS

### Sync Operations
```bash
# Sync single PO
php lightspeed-cli.php sync:po PO-2025-001

# Sync all pending
php lightspeed-cli.php sync:pending

# Check status
php lightspeed-cli.php sync:status PO-2025-001

# Retry failed
php lightspeed-cli.php sync:retry PO-2025-001
```

### Queue Management
```bash
# View stats
php lightspeed-cli.php queue:stats

# List jobs
php lightspeed-cli.php queue:list

# Retry failed job
php lightspeed-cli.php queue:retry job_123

# Prune old jobs (7+ days)
php lightspeed-cli.php queue:prune 7
```

### API Testing
```bash
# Test connection
php lightspeed-cli.php vend:test

# List outlets
php lightspeed-cli.php vend:outlets

# Search products
php lightspeed-cli.php vend:products SKU123
```

### System Status
```bash
# Full status check
php lightspeed-cli.php status

# Show config
php lightspeed-cli.php config:show
```

---

## 🔄 SYNC WORKFLOW

### Automatic Sync (Recommended)

1. **User approves PO in UI**
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
php lightspeed-cli.php sync:po <po-id>

# Or via UI (if UI pages built)
# Purchase Orders → Sync → Manual Sync
```

---

## 🔍 MONITORING

### Check Queue Health
```bash
php lightspeed-cli.php queue:stats
```

**Look for:**
- ✅ Pending jobs being processed
- ✅ No stuck jobs (processing > 30 min)
- ✅ Low failed job count
- ❌ High failed job count = investigate

### Check Sync Status
```bash
# Single PO
php lightspeed-cli.php sync:status PO-2025-001

# Database query
SELECT * FROM lightspeed_sync_log
WHERE entity_id = 'PO-2025-001'
ORDER BY created_at DESC;
```

### Check Worker Status
```bash
# CLI command
php lightspeed-cli.php status

# Process check
ps aux | grep lightspeed-cli

# Database check
SELECT * FROM queue_jobs
WHERE status='PROCESSING'
AND worker_id IS NOT NULL;
```

---

## 🚨 TROUBLESHOOTING

### Worker Not Processing Jobs

**Symptoms:**
- Jobs stuck in PENDING
- No worker in `queue:stats` output

**Solutions:**
```bash
# 1. Check if worker running
ps aux | grep "lightspeed-cli.php queue:work"

# 2. Restart worker
pkill -f "lightspeed-cli.php queue:work"
screen -dmS lightspeed php modules/consignments/cli/lightspeed-cli.php queue:work

# 3. Check for errors
tail -f logs/lightspeed-worker.log
```

### API Authentication Failing

**Symptoms:**
- `vend:test` returns 401/403
- Sync jobs failing with auth errors

**Solutions:**
```bash
# 1. Verify token
php lightspeed-cli.php config:show | grep token

# 2. Update token
php lightspeed-cli.php config:set lightspeed_api_token "new_token_here"

# 3. Test again
php lightspeed-cli.php vend:test
```

### Jobs Stuck in PROCESSING

**Symptoms:**
- Jobs show PROCESSING for > 30 minutes
- Worker appears dead

**Solutions:**
```bash
# 1. Check stuck jobs
SELECT * FROM queue_jobs
WHERE status='PROCESSING'
AND started_at < DATE_SUB(NOW(), INTERVAL 30 MINUTE);

# 2. Reset stuck jobs
UPDATE queue_jobs
SET status='PENDING', worker_id=NULL, error_message='Reset due to timeout'
WHERE status='PROCESSING'
AND started_at < DATE_SUB(NOW(), INTERVAL 30 MINUTE);

# 3. Restart worker
pkill -f "lightspeed-cli.php queue:work"
screen -dmS lightspeed php modules/consignments/cli/lightspeed-cli.php queue:work
```

### Webhooks Not Received

**Symptoms:**
- Lightspeed status changes not reflected locally
- No entries in webhook logs

**Solutions:**
```bash
# 1. Check webhook subscriptions
php lightspeed-cli.php webhook:list

# 2. Recreate webhook
php lightspeed-cli.php webhook:create \
  https://staff.vapeshed.co.nz/assets/services/webhooks/lightspeed_webhook_receiver.php

# 3. Test webhook manually
curl -X POST https://staff.vapeshed.co.nz/assets/services/webhooks/lightspeed_webhook_receiver.php \
  -H "Content-Type: application/json" \
  -d '{"type":"consignment.updated","id":"test","status":"SENT"}'

# 4. Check logs
tail -f logs/webhook-receiver.log
```

### High Failed Job Rate

**Symptoms:**
- Many jobs in FAILED status
- Sync operations not completing

**Solutions:**
```bash
# 1. Check failed jobs
php lightspeed-cli.php queue:list | grep FAILED

# 2. Get detailed errors
SELECT id, job_type, error_message, attempts
FROM queue_jobs
WHERE status='FAILED'
ORDER BY created_at DESC
LIMIT 20;

# 3. Retry specific job
php lightspeed-cli.php queue:retry <job-id>

# 4. Retry all failed
php lightspeed-cli.php queue:retry-all
```

---

## 📊 DATABASE QUERIES

### Recent Sync Operations
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

### POs Pending Sync
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

### Failed Sync Details
```sql
SELECT
    v.public_id,
    v.state,
    l.operation,
    l.error_message,
    l.created_at
FROM vend_consignments v
JOIN lightspeed_sync_log l ON v.id = l.entity_id
WHERE l.status = 'FAILED'
ORDER BY l.created_at DESC;
```

---

## 🔧 CONFIGURATION

### Environment Variables (.env)
```env
LIGHTSPEED_DOMAIN_PREFIX=vapeshed
LIGHTSPEED_API_TOKEN=your_token_here
LIGHTSPEED_WEBHOOK_SECRET=your_secret_here
QUEUE_MAX_RETRIES=3
SYNC_AUTO_ON_APPROVAL=true
```

### CLI Configuration
```bash
# View all settings
php lightspeed-cli.php config:show

# Update settings
php lightspeed-cli.php config:set <key> <value>
```

### Worker Configuration
```bash
# Max retries
php lightspeed-cli.php config:set queue_max_retries 5

# Retry delays (seconds)
php lightspeed-cli.php config:set queue_retry_delays "60,300,900"

# Worker sleep (seconds between checks)
php lightspeed-cli.php config:set queue_worker_sleep 5
```

---

## 📈 PERFORMANCE TUNING

### Optimize Queue Processing

**Increase Worker Count:**
```bash
# Run multiple workers
screen -dmS worker1 php lightspeed-cli.php queue:work
screen -dmS worker2 php lightspeed-cli.php queue:work
screen -dmS worker3 php lightspeed-cli.php queue:work
```

**Prioritize Critical Jobs:**
```php
// In code, when enqueueing
$queueService->enqueue('sync_po', $payload, 'critical');
```

**Batch Processing:**
```bash
# Sync multiple POs at once
php lightspeed-cli.php sync:pending --batch-size=50
```

### Optimize Webhook Processing

**Enable Direct Processing Mode:**
```php
// In lightspeed_webhook_receiver.php
define('PROCESS_DIRECTLY', true); // Skip queue for webhooks
```

**Increase Rate Limits:**
```php
// In webhook receiver config
define('RATE_LIMIT_MAX', 200);
define('RATE_LIMIT_WINDOW', 60);
```

---

## 🎯 BEST PRACTICES

### Development
1. ✅ Test with `--dry-run` flag first
2. ✅ Use `vend:test` to verify API connectivity
3. ✅ Check `queue:stats` regularly during testing
4. ✅ Review logs for errors: `tail -f logs/*.log`

### Production
1. ✅ Run worker as systemd service (auto-restart)
2. ✅ Monitor queue depth hourly
3. ✅ Alert on high failed job rate
4. ✅ Prune old jobs daily: `queue:prune 7`
5. ✅ Backup database before migrations

### Debugging
1. ✅ Enable verbose mode: `--verbose`
2. ✅ Check sync log: `SELECT * FROM lightspeed_sync_log`
3. ✅ Check API log: `SELECT * FROM lightspeed_api_log`
4. ✅ Use `status` command for overview

---

## 📞 SUPPORT

### Log Locations
```
logs/lightspeed-worker.log      - Worker daemon logs
logs/webhook-receiver.log        - Incoming webhook events
logs/api-requests.log            - Outgoing API requests
logs/sync-operations.log         - Sync operation details
```

### Database Tables
```
queue_jobs                       - Job queue
lightspeed_sync_log              - Sync operation history
lightspeed_api_log               - API request/response log
lightspeed_webhooks              - Raw webhook storage
lightspeed_mappings              - ID mappings (local ↔ Lightspeed)
vend_consignments                - Purchase orders (has lightspeed_consignment_id field)
```

### CLI Help
```bash
# General help
php lightspeed-cli.php help

# Command-specific help
php lightspeed-cli.php <command> --help
```

---

## ✅ HEALTH CHECK SCRIPT

Save as `health-check.sh`:

```bash
#!/bin/bash

echo "🔍 Lightspeed Integration Health Check"
echo "======================================="

# Check worker
if pgrep -f "lightspeed-cli.php queue:work" > /dev/null; then
    echo "✅ Worker: RUNNING"
else
    echo "❌ Worker: NOT RUNNING"
fi

# Check queue
PENDING=$(mysql -u jcepnzzkmj -p'wprKh9Jq63' jcepnzzkmj -N -e "SELECT COUNT(*) FROM queue_jobs WHERE status='PENDING'")
PROCESSING=$(mysql -u jcepnzzkmj -p'wprKh9Jq63' jcepnzzkmj -N -e "SELECT COUNT(*) FROM queue_jobs WHERE status='PROCESSING'")
FAILED=$(mysql -u jcepnzzkmj -p'wprKh9Jq63' jcepnzzkmj -N -e "SELECT COUNT(*) FROM queue_jobs WHERE status='FAILED'")

echo "📊 Queue Stats:"
echo "   Pending: $PENDING"
echo "   Processing: $PROCESSING"
echo "   Failed: $FAILED"

if [ $FAILED -gt 10 ]; then
    echo "⚠️  WARNING: High failed job count!"
fi

# Check API
php modules/consignments/cli/lightspeed-cli.php vend:test > /dev/null 2>&1
if [ $? -eq 0 ]; then
    echo "✅ API: CONNECTED"
else
    echo "❌ API: CONNECTION FAILED"
fi

echo "======================================="
```

Run with: `bash health-check.sh`

---

**END OF QUICK REFERENCE**

For detailed documentation, see `PHASE_4_COMPLETE.md`
