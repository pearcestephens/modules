# Human Resources > Payroll Module

## 🎯 Overview

Complete payroll management system for The Vape Shed with:
- **Automated payroll runs** (every Tuesday via cron)
- **Complete state snapshots** (userObjects, Deputy, Vend, Xero)
- **Diff engine** (calculate changes between any two states)
- **Amendment workflow** (post-posting corrections)
- **Audit trail** (every button click logged)
- **Day in lieu implementation** (alternative holiday leave)
- **Performance optimizations** (start date caching, bulk operations)

---

## 📁 Module Structure

```
modules/human_resources/payroll/
├── _schema/
│   └── complete_payroll_schema.sql     # All 9 tables + triggers + views
│
├── lib/
│   ├── PayrollSnapshotManager.php      # Core snapshot/diff engine
│   ├── PayrollHelpers.php              # Shared utilities (TODO)
│   └── PayrollValidator.php            # Input validation (TODO)
│
├── cron/
│   └── payroll_auto_start.php          # Tuesday auto-start cron job
│
├── api/
│   ├── get_run.php                     # Get run details (TODO)
│   ├── get_snapshot.php                # Get snapshot data (TODO)
│   ├── calculate_diff.php              # Diff between snapshots (TODO)
│   └── create_amendment.php            # Create amendment record (TODO)
│
├── views/
│   ├── run_list.php                    # List all pay runs (TODO)
│   ├── run_detail.php                  # Single run detail view (TODO)
│   ├── snapshot_viewer.php             # View snapshot data (TODO)
│   ├── diff_viewer.php                 # Visual diff between snapshots (TODO)
│   └── amendment_form.php              # Amendment creation UI (TODO)
│
├── controllers/
│   └── PayrollController.php           # Main controller (TODO)
│
├── README.md                           # This file
└── MODULE_INFO.json                    # Module metadata
```

---

## 🗄️ Database Tables (9 total)

### 1. `payroll_runs`
**Purpose:** Container for each weekly pay run
**Key Fields:** run_uuid, period_start, period_end, payment_date, status
**Status Flow:** draft → in_progress → pushed_to_xero → posted

### 2. `payroll_run_revisions`
**Purpose:** Every button click / action within a run
**Actions:** load_payroll, calculate_bonuses, push_to_xero, amendment, etc.

### 3. `payroll_snapshots` ⭐
**Purpose:** Complete state capture (THE GOLD)
**Stores:**
- `user_objects_json` - Complete CIS userObjects
- `deputy_timesheets_json` - Raw Deputy data
- `vend_account_balances_json` - Vend balances
- `xero_payslips_json` - Xero responses
- `public_holidays_json` - Holiday data
- `bonus_calculations_json` - Bonus breakdown
- `config_snapshot_json` - System config

### 4. `payroll_employee_details`
**Purpose:** Normalized employee data for fast queries
**Why:** SQL queries on JSON blobs are slow; this extracts key fields

### 5. `payroll_earnings_lines`
**Purpose:** Individual earning line items per employee
**Examples:** Ordinary Time, Overtime, Commission, Bonuses

### 6. `payroll_deduction_lines`
**Purpose:** Individual deduction line items
**Examples:** Account Payment, Tax, KiwiSaver

### 7. `payroll_public_holidays`
**Purpose:** Public holiday tracking (day in lieu)
**Tracks:** Hours worked, preference, alternative holiday created

### 8. `payroll_amendments`
**Purpose:** Post-posting corrections
**Includes:** Approval workflow, reason tracking, payment method

### 9. `payroll_snapshot_diffs`
**Purpose:** Pre-computed diffs between snapshots
**Why:** Calculating diffs on-demand is slow; cache results

---

## 🔄 Typical Workflow

### Week 1: Automated Start (Tuesday 9am)
```
1. Cron runs: payroll_auto_start.php
2. Creates new payroll_runs record
3. Sends email to payroll staff
4. Status: "draft"
```

### Week 1: Manual Processing (Tuesday-Friday)
```
1. Staff opens payroll-process.php?run_id=123
2. Clicks "Load Payroll"
   → Creates revision #1 (load_payroll)
   → Fetches Deputy timesheets
   → Creates "pre_load" snapshot
3. Clicks "Calculate Bonuses"
   → Creates revision #2 (calculate_bonuses)
   → Creates snapshot
4. Manual adjustments
   → Creates revision #3 (adjust_hours)
   → Creates snapshot
5. Clicks "Push to Xero"
   → Creates revision #4 (push_to_xero)
   → Creates "pre_push" snapshot
   → Pushes to Xero API
   → Creates "post_push" snapshot
   → Status: "pushed_to_xero"
```

### Week 2: Post-Posting Amendment
```
1. Employee: "I worked 2 extra hours on Saturday!"
2. Payroll creates amendment:
   - field: total_hours
   - old_value: 40.00
   - new_value: 42.00
   - delta: +2.00
   - reason: "Forgot to clock out on Saturday"
3. Manager approves
4. System calculates:
   - Load latest snapshot
   - Apply change
   - Calculate diff
   - Net difference: +$51.00
5. Create separate payment or add to next run
```

---

## 📊 Snapshot Philosophy

### Immutable Snapshots
- **Never modify** - Only append new snapshots
- **Complete state** - Store EVERYTHING (userObjects, Deputy, Vend, Xero)
- **Integrity hash** - SHA256 for tamper detection
- **Compression** - Future: gzip for old snapshots

### When to Snapshot
- **pre_load** - Before loading Deputy timesheets
- **pre_push** - Before pushing to Xero (most important!)
- **post_push** - After successful Xero push
- **amendment** - Before applying post-posting changes
- **manual** - On-demand via API

### Snapshot Size
- **Typical:** 500KB - 2MB per snapshot (42 employees)
- **With compression:** 100KB - 400KB
- **Monthly storage:** ~250MB (4 weeks × 5 snapshots/week × 2MB avg)
- **Annual storage:** ~3GB (totally manageable)

---

## 🔍 Diff Engine

### How It Works
```php
// Get latest snapshot
$latestSnapshot = $snapshotManager->getLatestSnapshot($runId);

// User wants to change something
$userObjects = json_decode($latestSnapshot['user_objects_json'], true);

// Apply changes
$userObjects[5]['grossEarnings'] += 51.00; // +2 hours @ $25.50/hr

// Calculate diff
$diff = $snapshotManager->calculateDiff($latestSnapshotId, $newSnapshotId);

// Result:
[
  'employees_changed' => [15],
  'total_pay_delta' => 51.00,
  'modifications' => [
    [
      'user_id' => 15,
      'name' => 'John Smith',
      'changes' => [
        'total_hours' => ['from' => 40, 'to' => 42, 'delta' => 2],
        'grossEarnings' => ['from' => 1020, 'to' => 1071, 'delta' => 51]
      ]
    ]
  ]
]
```

### Use Cases
1. **"What changed?"** - Compare pre_push vs post_push
2. **"What if?"** - Compare current vs hypothetical
3. **"Prove it"** - Show employee exactly what changed
4. **"Audit trail"** - Compliance evidence

---

## 🤖 Cron Job Setup

### Installation
```bash
# Make executable
chmod +x /path/to/modules/human_resources/payroll/cron/payroll_auto_start.php

# Add to crontab
crontab -e

# Add this line (runs every Tuesday at 9am NZT)
0 9 * * 2 /usr/bin/php /home/master/applications/jcepnzzkmj/public_html/modules/human_resources/payroll/cron/payroll_auto_start.php >> /home/master/applications/jcepnzzkmj/logs/payroll_cron.log 2>&1
```

### Testing
```bash
# Dry run (won't create anything)
php payroll_auto_start.php --dry-run

# Force run (even if not Tuesday)
php payroll_auto_start.php --force

# Manual trigger
php payroll_auto_start.php
```

### Monitoring
```bash
# View cron log
tail -f /home/master/applications/jcepnzzkmj/logs/payroll_cron.log

# Check last run
mysql -u jcepnzzkmj -p'wprKh9Jq63' jcepnzzkmj -e "SELECT * FROM payroll_runs ORDER BY id DESC LIMIT 1;"
```

---

## 🔗 Integration Points

### 1. xero-payruns.php
**Location:** `/assets/functions/xeroAPI/xero-payruns.php`
**Integration:** Lines 524-580, 850-890
**What:** Automatic snapshot capture on push

### 2. payroll-process.php
**Location:** `/payroll-process.php`
**Integration:** TODO - Need to set `$currentRunId` global
**What:** Initialize run, pass to xero-payruns.php

### 3. Deputy Integration
**Location:** Various
**Integration:** TODO - Pass raw timesheet data to snapshots
**What:** Store complete Deputy responses

### 4. Vend Integration
**Location:** Various
**Integration:** TODO - Collect account balances before payroll
**What:** Snapshot Vend customer balances

---

## 📈 Performance Considerations

### Fast Queries (Use Normalized Tables)
```sql
-- Get employee pay history
SELECT * FROM payroll_employee_history
WHERE staff_id = 15
ORDER BY period_start DESC;

-- Total pay by month
SELECT
  DATE_FORMAT(period_start, '%Y-%m') AS month,
  SUM(gross_earnings) AS total_gross
FROM payroll_employee_details
WHERE user_id = 15
GROUP BY month;
```

### Slow Queries (Use Views or Cache)
```sql
-- DON'T do this (slow JSON parsing):
SELECT JSON_EXTRACT(user_objects_json, '$[*].grossEarnings')
FROM payroll_snapshots;

-- DO this instead (fast normalized data):
SELECT SUM(gross_earnings)
FROM payroll_employee_details
WHERE run_id = 123;
```

### Snapshot Compression (Future)
```php
// Store compressed JSON
$compressed = gzcompress(json_encode($data), 9);
$stmt->execute(['user_objects_json' => $compressed]);

// Retrieve
$data = json_decode(gzuncompress($row['user_objects_json']), true);
```

---

## 🧪 Testing

### 1. Create Test Run
```php
$pdo = new PDO(...);
$manager = new PayrollSnapshotManager($pdo, $xeroTenantId, $userId);

$result = $manager->startPayRun('2025-11-04', '2025-11-10', '2025-11-11');
// Returns: ['run_id' => 1, 'run_uuid' => '...', 'run_number' => 1]
```

### 2. Capture Snapshot
```php
$snapshotId = $manager->captureSnapshot(
    $runId,
    $revisionId,
    $userObjects,
    null, null, null, null, null, null, null,
    'manual'
);
```

### 3. Calculate Diff
```php
$diff = $manager->calculateDiff($snapshot1Id, $snapshot2Id);
print_r($diff);
```

---

## 🚨 Error Handling

### If Snapshot Fails
- **Don't block payroll** - Log error and continue
- **Alert payroll staff** - Email notification
- **Retry next run** - Will create snapshot on next action

### If Cron Fails
- **Email notification** sent to payroll staff
- **Manual fallback** - Staff can create run manually
- **Check logs** - `/logs/payroll_cron.log`

---

## 📝 TODO List

### Phase 1 (Core - DONE ✅)
- [x] Database schema (9 tables)
- [x] PayrollSnapshotManager class
- [x] Tuesday auto-start cron
- [x] Integration into xero-payruns.php

### Phase 2 (Integration - IN PROGRESS ⏳)
- [ ] Update payroll-process.php to set `$currentRunId`
- [ ] Pass Deputy timesheets to snapshots
- [ ] Collect Vend balances before payroll
- [ ] Store bonus calculation breakdown

### Phase 3 (UI - TODO 📋)
- [ ] Pay run list view
- [ ] Snapshot viewer
- [ ] Diff viewer (visual comparison)
- [ ] Amendment form
- [ ] Approval workflow UI

### Phase 4 (Advanced - TODO 📋)
- [ ] Snapshot compression
- [ ] Export to Excel
- [ ] Predictive analytics (forecast labor costs)
- [ ] Anomaly detection (flag unusual patterns)

---

## 🔐 Security

### Access Control
- **Payroll runs:** Admin + Payroll Manager only
- **Amendments:** Requires approval
- **Snapshots:** Read-only for auditors
- **Diff data:** Sensitive - log all access

### Data Protection
- **Encryption at rest:** Database encryption (optional)
- **Integrity hashes:** SHA256 on all snapshots
- **Audit logging:** Every access logged
- **PII redaction:** Consider masking personal data in logs

---

## 📞 Support

### Issues
- **Database:** Check `/logs/payroll_cron.log`
- **Snapshots:** Check `payroll_audit_log` table
- **Cron:** Verify crontab with `crontab -l`

### Escalation
- **Payroll issues:** payroll@vapeshed.co.nz
- **System issues:** Pearce Stephens <pearce.stephens@ecigdis.co.nz>

---

## 📚 References

### Internal
- [Payroll Process](../../payroll-process.php)
- [Xero API Functions](../../../assets/functions/xeroAPI/)
- [Deputy Integration](../../../assets/functions/deputyAPI/)

### External
- [Xero Payroll NZ API](https://developer.xero.com/documentation/api/payrollnz/overview)
- [Deputy API](https://www.deputy.com/api-doc)

---

**Version:** 1.0.0
**Created:** 2025-10-29
**Last Updated:** 2025-10-29
**Maintainer:** CIS Development Team
