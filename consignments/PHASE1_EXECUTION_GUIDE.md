# Phase 1 Migration + Testing - Execution Guide

## 🚀 READY TO EXECUTE!

All scripts have been created and are ready to run. Execute Phase 1 migration and testing with a single command.

---

## Quick Start (Single Command)

```bash
cd /home/master/applications/jcepnzzkmj/public_html/modules/consignments
chmod +x bin/execute-phase1.php
php bin/execute-phase1.php
```

---

## What Will Happen

### Step 1: Make Scripts Executable
- `run-migration.php` → Database migration executor
- `test-phase1.php` → Comprehensive test suite
- `notification-worker.php` → Background email processor

### Step 2: Database Migration
Creates 4 tables:
- `consignment_notification_queue` - Email queue with retry logic
- `consignment_email_templates` - 9 pre-configured templates
- `consignment_email_template_config` - Global and per-supplier settings
- `consignment_email_log` - Complete audit trail

### Step 3: Comprehensive Tests
Runs:
- Environment checks (PHP version, extensions, composer)
- Database verification (tables, templates, config)
- Unit tests (PHPUnit - 27 tests)
- Integration tests (EmailService, NotificationService)
- Worker tests (notification-worker.php)

### Step 4: Worker Verification
Tests:
- `--stats` command
- `--help` command
- Queue statistics display

---

## Manual Step-by-Step (If You Prefer)

### 1. Database Migration Only
```bash
cd /home/master/applications/jcepnzzkmj/public_html/modules/consignments
chmod +x bin/run-migration.php
php bin/run-migration.php
```

**Dry Run (Preview Only):**
```bash
php bin/run-migration.php --dry-run
```

**Force (If Tables Exist):**
```bash
php bin/run-migration.php --force
```

### 2. Run Tests Only
```bash
chmod +x bin/test-phase1.php
php bin/test-phase1.php --verbose
```

**Unit Tests Only:**
```bash
php bin/test-phase1.php --unit-only
```

**Integration Tests Only:**
```bash
php bin/test-phase1.php --integration-only
```

### 3. Test Worker
```bash
chmod +x bin/notification-worker.php

# View help
php bin/notification-worker.php --help

# View queue stats
php bin/notification-worker.php --stats

# Process urgent emails
php bin/notification-worker.php --priority=1 --verbose
```

---

## Expected Output

```
═══════════════════════════════════════════════════════════════════
  PHASE 1: EMAIL NOTIFICATION SYSTEM
  Complete Migration + Testing
═══════════════════════════════════════════════════════════════════

📝 Step 1: Making scripts executable...
───────────────────────────────────────────────────────────────────
✓ run-migration.php
✓ test-phase1.php
✓ notification-worker.php

🗄️  Step 2: Running database migration...
───────────────────────────────────────────────────────────────────
✓ Database connection established
  Database: jcepnzzkmj

Executing migration...
───────────────────────────────────────────────────────────────────
[1/X] CREATE TABLE IF NOT EXISTS `consignment_notification_queue...
[2/X] CREATE TABLE IF NOT EXISTS `consignment_email_templates...
[3/X] CREATE TABLE IF NOT EXISTS `consignment_email_template_co...
[4/X] CREATE TABLE IF NOT EXISTS `consignment_email_log...
[5/X] INSERT INTO consignment_email_templates...
───────────────────────────────────────────────────────────────────

✅ Migration completed successfully!
   Executed: X statements

Verifying migration...
───────────────────────────────────────────────────────────────────
✓ Table consignment_notification_queue: exists
✓ Table consignment_email_templates: exists
✓ Table consignment_email_template_config: exists
✓ Table consignment_email_log: exists
✓ Email templates: 9 templates (expected 9)
✓ Config entries: 8 entries (expected 8)
✓ Queue indexes: created
───────────────────────────────────────────────────────────────────

🎉 All verification checks passed!

🧪 Step 3: Running comprehensive test suite...
───────────────────────────────────────────────────────────────────
🔍 Running Environment Checks...
  ✓ PHP Version >= 8.0               Current: 8.x.x
  ✓ PDO Extension                    Installed
  ✓ PDO MySQL Driver                 Installed
  ✓ Composer Autoload                Found

🗄️  Running Database Verification...
  ✓ Database Connection              Connected
  ✓ Table: consignment_notification_queue  Exists
  ✓ Table: consignment_email_templates     Exists
  ✓ Table: consignment_email_template_config  Exists
  ✓ Table: consignment_email_log           Exists
  ✓ Email Templates                  9 templates
  ✓ Config Entries                   8 entries

🧪 Running Unit Tests (PHPUnit)...
  ✓ PHPUnit Test Suite              All tests passed

🔗 Running Integration Tests...
  ✓ EmailService::sendTemplate()    Queued with ID: X
  ✓ Queue Record Created            Found
  ✓ Queue Status = pending          Status: pending
  ✓ NotificationService::getQueueStats()  Returned array

⚙️  Running Worker Tests...
  ✓ Worker Script Exists            Found
  ✓ Worker Script Executable        Yes
  ✓ Worker --help                   Works
  ✓ Worker --stats                  Works

═══════════════════════════════════════════════════════════════════
  TEST SUMMARY
═══════════════════════════════════════════════════════════════════

✅ ENV                  : XX/XX passed
✅ DB                   : XX/XX passed
✅ UNIT                 : XX/XX passed
✅ INTEGRATION          : XX/XX passed
✅ WORKER               : XX/XX passed
───────────────────────────────────────────────────────────────────
   TOTAL               : XX/XX passed
   Duration            : X.XXs

🎉 ALL TESTS PASSED!

═══════════════════════════════════════════════════════════════════
  🎉 PHASE 1 COMPLETE!
═══════════════════════════════════════════════════════════════════

✅ Database migrated successfully
✅ All tests passed
✅ Worker operational
```

---

## After Successful Execution

### 1. Setup Cron Jobs (CRITICAL!)

Add to crontab:
```bash
crontab -e
```

Paste these lines:
```cron
# Consignments Email Queue Workers
* * * * * php /home/master/applications/jcepnzzkmj/public_html/modules/consignments/bin/notification-worker.php --priority=1 >> /var/log/consignments-email-urgent.log 2>&1
*/5 * * * * php /home/master/applications/jcepnzzkmj/public_html/modules/consignments/bin/notification-worker.php --priority=2 >> /var/log/consignments-email-high.log 2>&1
*/30 * * * * php /home/master/applications/jcepnzzkmj/public_html/modules/consignments/bin/notification-worker.php --priority=3 >> /var/log/consignments-email-normal.log 2>&1
0 2 * * * php /home/master/applications/jcepnzzkmj/public_html/modules/consignments/bin/notification-worker.php --priority=4 >> /var/log/consignments-email-low.log 2>&1
*/15 * * * * php /home/master/applications/jcepnzzkmj/public_html/modules/consignments/bin/notification-worker.php --retry >> /var/log/consignments-email-retry.log 2>&1
```

### 2. Send Test Email

```bash
cd /home/master/applications/jcepnzzkmj/public_html/modules/consignments

php -r 'require "bootstrap.php"; use CIS\Consignments\Services\EmailService; $s = EmailService::make(); $id = $s->sendTemplate("po_created_internal", "your-email@example.com", "Test User", ["po_number"=>"TEST-123","supplier_name"=>"Test Supplier","total_value"=>"$100.00","created_by"=>"Test Runner","created_at"=>date("Y-m-d H:i:s"),"po_url"=>"https://staff.vapeshed.co.nz/modules/consignments/purchase-orders/view.php?id=123"], null, 3, 1); echo "Queued with ID: $id\n";'
```

### 3. Process The Queue

```bash
php bin/notification-worker.php --priority=1 --verbose
```

### 4. Monitor Queue

```bash
# One-time stats
php bin/notification-worker.php --stats

# Continuous monitoring (refreshes every 5 seconds)
watch -n 5 "php /home/master/applications/jcepnzzkmj/public_html/modules/consignments/bin/notification-worker.php --stats"
```

---

## Troubleshooting

### Migration Fails

**Error: "Table already exists"**
```bash
# Use --force to proceed anyway
php bin/run-migration.php --force
```

**Error: "Database connection failed"**
- Check `.env` file exists in module root
- Verify DB_HOST, DB_NAME, DB_USER, DB_PASS are correct
- Test connection: `mysql -u username -p database_name`

### Tests Fail

**Error: "PHPUnit not found"**
```bash
# Install dependencies
cd /home/master/applications/jcepnzzkmj/public_html/modules/consignments
composer install
```

**Error: "Function db() not defined"**
- Verify `bootstrap.php` loads correctly
- Check `modules/base/bootstrap.php` has the `db()` helper function

### Worker Issues

**Error: "Permission denied"**
```bash
chmod +x bin/notification-worker.php
```

**Error: "Class not found"**
```bash
# Regenerate autoload
composer dump-autoload
```

---

## Files Created in This Phase

### Core Services
- `/lib/Services/EmailService.php` (479 lines)
- `/lib/Services/NotificationService.php` (562 lines)

### Database
- `/database/migrations/email-notification-system.sql` (302 lines)

### Templates
- `/templates/email/base.html`
- `/templates/email/po_created_internal.html`
- `/templates/email/po_pending_approval.html`
- `/templates/email/po_approved.html`
- `/templates/email/po_rejected.html`
- `/templates/email/consignment_received.html`
- `/templates/email/discrepancy_alert.html`
- `/templates/email/po_created_supplier.html`

### Scripts
- `/bin/notification-worker.php` (400+ lines)
- `/bin/run-migration.php` (300+ lines)
- `/bin/test-phase1.php` (400+ lines)
- `/bin/execute-phase1.php` (100+ lines)

### Tests
- `/tests/Unit/EmailServiceTest.php` (300+ lines)
- `/tests/Unit/NotificationServiceTest.php` (350+ lines)

### Total Lines: ~3,500 lines of production code + tests

---

## Ready?

**Execute now:**
```bash
cd /home/master/applications/jcepnzzkmj/public_html/modules/consignments && chmod +x bin/execute-phase1.php && php bin/execute-phase1.php
```

🚀 **Let's go!**
