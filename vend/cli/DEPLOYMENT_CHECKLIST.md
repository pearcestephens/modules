# VEND SYNC MANAGER - DEPLOYMENT CHECKLIST

**Version:** 1.0.0
**Date:** 2025-11-08
**Environment:** Production

---

## Pre-Deployment

### 1. Verify Code Integrity
- [ ] File exists: `/modules/vend/cli/vend-sync-manager.php` (2,800+ lines)
- [ ] File is executable: `chmod +x vend-sync-manager.php`
- [ ] PHP syntax check: `php -l vend-sync-manager.php` (no errors)
- [ ] File size > 100KB (comprehensive implementation)

### 2. Database Preparation
- [ ] Run `setup.sql` script
- [ ] Verify `configuration` table has `vend_access_token`
- [ ] Verify `vend_api_logs` table exists
- [ ] Verify `vend_sync_cursors` table exists
- [ ] Verify `system_config` table exists (optional)
- [ ] Check all 28 vend_* tables exist

### 3. Configuration
- [ ] API token configured in database OR environment
- [ ] Token tested and validated
- [ ] Proper PHP version (7.4+)
- [ ] Memory limit adequate (2GB+ recommended)
- [ ] Max execution time = 0 (unlimited for long syncs)

### 4. Dependencies
- [ ] CIS Bootstrap.php loaded correctly
- [ ] PDO extension available
- [ ] cURL extension available
- [ ] JSON extension available
- [ ] Database connection working (read + write)

---

## Deployment Steps

### Step 1: Install Files
```bash
# Navigate to modules directory
cd /home/master/applications/jcepnzzkmj/public_html/modules/vend/cli

# Verify file exists
ls -lah vend-sync-manager.php

# Make executable
chmod +x vend-sync-manager.php

# Check permissions
ls -l vend-sync-manager.php
# Expected: -rwxr-xr-x
```

### Step 2: Database Setup
```bash
# Run setup script
mysql -u USER -p jcepnzzkmj < setup.sql

# OR via PHP
php -r "
require 'assets/services/gpt/src/Bootstrap.php';
\$db = db_ro();
\$sql = file_get_contents('modules/vend/cli/setup.sql');
// Execute SQL statements
"
```

### Step 3: Configure API Token
Choose ONE method:

**Method A: Database (Recommended)**
```sql
INSERT INTO configuration (config_label, config_value, config_description)
VALUES ('vend_access_token', 'YOUR_TOKEN_HERE', 'Vend API Token');
```

**Method B: Environment Variable**
```bash
# Add to .bashrc or cron environment
export VEND_API_TOKEN="YOUR_TOKEN_HERE"
```

**Method C: Server Environment**
```bash
# Add to Apache/Nginx config
SetEnv VEND_API_TOKEN "YOUR_TOKEN_HERE"
```

### Step 4: Initial Testing
```bash
cd /path/to/modules/vend/cli

# Test 1: Version check
php vend-sync-manager.php util:version

# Test 2: Help display
php vend-sync-manager.php --help

# Test 3: Health check
php vend-sync-manager.php health:check --verbose

# Test 4: Database connectivity
php vend-sync-manager.php health:database

# Test 5: API connectivity
php vend-sync-manager.php test:connection

# Test 6: Sync status
php vend-sync-manager.php audit:sync-status

# Test 7: Queue stats
php vend-sync-manager.php queue:stats

# Test 8: Consignment rules
php vend-sync-manager.php consignment:rules
```

### Step 5: Test Sync Operations
```bash
# Test small dataset sync (outlets - only 19 rows)
php vend-sync-manager.php sync:outlets

# Test incremental sync (using cursor)
php vend-sync-manager.php sync:products

# Test queue processing
php vend-sync-manager.php queue:process --batch=10

# Monitor audit logs
php vend-sync-manager.php audit:logs
```

### Step 6: Validate Consignment State Machine
```bash
# Find a test consignment
php -r "
require 'assets/services/gpt/src/Bootstrap.php';
\$db = db_ro();
\$stmt = \$db->query('SELECT id, state FROM vend_consignments WHERE state = \"OPEN\" LIMIT 1');
\$row = \$stmt->fetch(PDO::FETCH_ASSOC);
echo \"Test ID: {\$row['id']}, State: {\$row['state']}\n\";
"

# Validate consignment (use ID from above)
php vend-sync-manager.php consignment:validate --id=<ID>

# Test valid transition (dry run)
php vend-sync-manager.php consignment:transition --id=<ID> --to=PACKING --dry-run

# Test invalid transition (should fail)
php vend-sync-manager.php consignment:transition --id=<ID> --to=RECEIVED --dry-run

# Test cancel (should work for OPEN/DRAFT)
php vend-sync-manager.php consignment:cancel --id=<ID> --reason="Test"
# (Choose "n" when prompted)
```

### Step 7: Setup Cron Jobs
```bash
# Edit crontab
crontab -e

# Add these entries:
# Incremental sync every 15 minutes
*/15 * * * * cd /path/to/vend/cli && php vend-sync-manager.php sync:all >> /var/log/vend-sync.log 2>&1

# Process queue every 5 minutes
*/5 * * * * cd /path/to/vend/cli && php vend-sync-manager.php queue:process --batch=100 >> /var/log/vend-queue.log 2>&1

# Full sync daily at 2 AM
0 2 * * * cd /path/to/vend/cli && php vend-sync-manager.php sync:all --full >> /var/log/vend-full-sync.log 2>&1

# Verify cron jobs
crontab -l | grep vend
```

### Step 8: Create Log Directory
```bash
# Create log directory if using file-based logs
sudo mkdir -p /var/log/vend-sync
sudo chown www-data:www-data /var/log/vend-sync
sudo chmod 755 /var/log/vend-sync

# Verify
ls -ld /var/log/vend-sync
```

---

## Post-Deployment Verification

### Smoke Tests
- [ ] `php vend-sync-manager.php --help` displays commands
- [ ] `php vend-sync-manager.php test:connection` returns success
- [ ] `php vend-sync-manager.php health:check` all systems green
- [ ] `php vend-sync-manager.php audit:sync-status` shows data
- [ ] `php vend-sync-manager.php queue:stats` shows queue info
- [ ] `php vend-sync-manager.php consignment:rules` displays rules

### Functional Tests
- [ ] Sync outlets completed successfully
- [ ] Sync products updated cursor in `vend_sync_cursors`
- [ ] Audit logs appear in `vend_api_logs` table
- [ ] Queue processing works without errors
- [ ] Consignment validation returns correct results
- [ ] State transition validation works (valid/invalid)
- [ ] Cancel only works for DRAFT/OPEN states

### Performance Tests
- [ ] Products sync completes in < 5 minutes (incremental)
- [ ] Sales sync handles date ranges correctly
- [ ] Queue processes 100 items in < 30 seconds
- [ ] Memory usage stays under 2GB
- [ ] No database deadlocks or timeouts

### Integration Tests
- [ ] CIS config integration works (`cis_vend_access_token`)
- [ ] Database read/write operations functional
- [ ] Lightspeed API calls succeed
- [ ] Cursor tracking works correctly
- [ ] Batch upserts don't create duplicates

---

## Monitoring Setup

### 1. Create Monitoring Dashboard
```sql
-- Daily sync summary query
SELECT
    DATE(created_at) as date,
    entity_type,
    COUNT(*) as operations,
    SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as successes,
    SUM(CASE WHEN status = 'error' THEN 1 ELSE 0 END) as errors,
    AVG(duration_ms) as avg_duration_ms
FROM vend_api_logs
WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
GROUP BY DATE(created_at), entity_type
ORDER BY date DESC, operations DESC;
```

### 2. Alert Queries
```sql
-- Failed syncs in last hour
SELECT * FROM vend_api_logs
WHERE status = 'error'
  AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
ORDER BY created_at DESC;

-- Queue backlog (pending > 1000)
SELECT COUNT(*) as pending FROM vend_queue WHERE status = 0;

-- Stale cursors (not updated in 24 hours)
SELECT * FROM vend_sync_cursors
WHERE updated_at < DATE_SUB(NOW(), INTERVAL 24 HOUR);
```

### 3. Health Check Cron
```bash
# Health check every hour with email alert on failure
0 * * * * cd /path/to/vend/cli && php vend-sync-manager.php health:check || echo "Vend Sync Health Check Failed" | mail -s "Alert: Vend Sync" admin@example.com
```

---

## Rollback Plan

### If Issues Occur:

**1. Stop Cron Jobs**
```bash
# Comment out vend-sync cron jobs
crontab -e
# Add # before each vend line
```

**2. Revert to Previous Version**
```bash
# If backup exists
cd /path/to/vend/cli
cp vend-sync-manager.php.backup vend-sync-manager.php
```

**3. Clear Queue Backlog**
```sql
-- Mark all pending as failed to prevent processing
UPDATE vend_queue SET status = 2 WHERE status = 0;
```

**4. Disable API Token**
```sql
-- Temporarily disable to prevent API calls
UPDATE configuration
SET config_value = 'DISABLED'
WHERE config_label = 'vend_access_token';
```

---

## Documentation Links

- **Usage Guide:** `VEND_SYNC_USAGE.md`
- **Setup Script:** `setup.sql`
- **Code File:** `vend-sync-manager.php`
- **CIS Config:** `modules/shared/functions/config.php`

---

## Support Contacts

- **Developer:** CIS WebDev Boss Engineer
- **System Admin:** [TBC]
- **Database Admin:** [TBC]

---

## Sign-Off

### Deployed By
- Name: ________________
- Date: ________________
- Signature: ________________

### Verified By
- Name: ________________
- Date: ________________
- Signature: ________________

### Approved By
- Name: ________________
- Date: ________________
- Signature: ________________

---

## Notes

_Add any deployment-specific notes, issues, or observations below:_

---

**END OF CHECKLIST**

---

## Quick Reference Commands

```bash
# Location
cd /home/master/applications/jcepnzzkmj/public_html/modules/vend/cli

# Help
php vend-sync-manager.php --help

# Health Check
php vend-sync-manager.php health:check --verbose

# Sync All (Incremental)
php vend-sync-manager.php sync:all

# Queue Process
php vend-sync-manager.php queue:process --batch=100

# Audit Logs
php vend-sync-manager.php audit:logs --errors-only

# Consignment Rules
php vend-sync-manager.php consignment:rules
```
