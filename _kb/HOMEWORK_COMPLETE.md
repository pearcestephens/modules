# TABLE RENAMING - HOMEWORK COMPLETE ✅

**Status:** Ready for Execution
**Date:** 2025-10-30
**Created By:** Bank Transactions Module Audit

---

## 📋 DOCUMENTS CREATED

1. **TABLE_RENAME_HOMEWORK.md**
   - Complete audit of all 46 files
   - Organized by priority (TIER 1, 2, 3)
   - Risk assessment and mitigation
   - Renaming strategy

2. **TABLE_REFERENCES_LINE_MAPPING.md**
   - Exact line numbers for all references
   - File-by-file breakdown
   - SQL/sed commands for execution
   - Verification commands

3. **CIS_DATABASE_TABLES.md**
   - Complete database table inventory
   - Connection credentials
   - Table statistics (rows, size)
   - MySQL commands

4. **AUDIT_REPORT.md**
   - Architecture overview
   - Current status (82% browser ready)
   - Detailed findings
   - What's needed to get 100%

---

## 🔍 HOMEWORK SUMMARY

### Files Analyzed: 46 total
- **TIER 1 (Critical):** 19 files - Production code, must rename
- **TIER 2 (Important):** 17 files - Services/cron, should rename
- **TIER 3 (Optional):** 10 files - Archive/old code, nice to rename

### Table References Found: 122 total
| Table Name | Count | Action |
|-----------|-------|--------|
| `deposit_transactions` | 69 | → `bank_transactions_legacy` |
| `bank_deposits` | 25 | → `bank_transactions_current` |
| `deposit_transactions_new` | 15 | → `bank_transactions_archive` |
| `bank_reconciliation_manual_reviews` | 8 | → `bank_manual_reviews` |
| `audit_trail` | 5 | → `bank_audit_trail` |

### Files by Location

**Bank Transactions Module (NEW - 8 files)**
```
✅ /modules/bank-transactions/models/TransactionModel.php
✅ /modules/bank-transactions/models/AuditLogModel.php
✅ /modules/bank-transactions/lib/MatchingEngine.php
✅ /modules/bank-transactions/controllers/TransactionController.php
✅ /modules/bank-transactions/controllers/BaseController.php
✅ /modules/bank-transactions/api/reassign-payment.php
✅ /modules/bank-transactions/migrations/001_create_bank_transactions_tables.php
✅ /modules/bank-transactions/migrations/002_create_bank_deposits_table.php
```

**Production Banking/Xero (9 files)**
```
✅ /assets/cron/xero/check-bank-transactions.php
✅ /assets/services/cron/scripts/xero/check-bank-transactions.php
✅ /assets/functions/closure-reporting.php
✅ /assets/functions/reporting.php
✅ /banking-reconciliation-manual-reviews.php
✅ /bank-transaction-debug.php
✅ /webhooks/receive.php
✅ /webhooks/public/receiver.php
✅ /webhooks/COMPREHENSIVE_FIELD_VALIDATION.php
```

**Services (2 files)**
```
✅ /assets/services/pipeline-simulator/app/Controllers/BulkController.php
✅ /assets/services/queue/config/consignments.php
```

**Backups & Archives (27 files)**
```
✅ 5 Archived utility scripts
✅ 8 Backup cron job variants
✅ 4 Schema/SQL files
✅ 4 Old module config files
✅ 3 Other archive files
✅ Plus additional documentation files
```

---

## ⚠️ CRITICAL FILES (MUST TEST AFTER RENAME)

1. **check-bank-transactions.php** (Production cron job)
   - Used daily for bank reconciliation
   - Test: Run cron manually, verify data processing

2. **banking-reconciliation-manual-reviews.php** (Production page)
   - User-facing interface
   - Test: Open in browser, verify data loads

3. **Webhook receivers** (Data ingest)
   - Receive bank transaction data from external systems
   - Test: Monitor webhook logs, verify data processed

4. **Reporting functions** (Used by multiple modules)
   - Used by dashboards, reports, exports
   - Test: Run all reports, verify data accuracy

---

## 🚀 EXECUTION STEPS

### Step 1: Prepare ✅ (5 min)
```bash
# Create backups of all files and database
mkdir -p /backups/bank_tables_$(date +%Y%m%d_%H%M%S)
cp -r /home/master/applications/jcepnzzkmj/public_html/modules/bank-transactions /backups/
mysqldump -h 127.0.0.1 -u jcepnzzkmj -pwprKh9Jq63 jcepnzzkmj > /backups/jcepnzzkmj_before.sql
```

### Step 2: Rename Database Tables ✅ (2 min)
```sql
ALTER TABLE deposit_transactions RENAME TO bank_transactions_legacy;
ALTER TABLE bank_deposits RENAME TO bank_transactions_current;
ALTER TABLE deposit_transactions_new RENAME TO bank_transactions_archive;
ALTER TABLE bank_reconciliation_manual_reviews RENAME TO bank_manual_reviews;
ALTER TABLE audit_trail RENAME TO bank_audit_trail;
```

### Step 3: Update TIER 1 Files (19 files) ⏳ (30 min)
- Update models, controllers, libraries, APIs, migrations
- Use sed commands provided in TABLE_REFERENCES_LINE_MAPPING.md
- Syntax check each file

### Step 4: Test APIs ✅ (5 min)
```bash
php /modules/bank-transactions/QUICK_BOT_TEST.php
# Verify all 9 return HTTP 200 + valid JSON
```

### Step 5: Update TIER 2 Files (17 files) ⏳ (30 min)
- Update production cron scripts
- Update reporting functions
- Update webhook receivers
- Update supporting services

### Step 6: Test Production Operations ✅ (10 min)
```bash
# Test cron job
php /assets/cron/xero/check-bank-transactions.php --test

# Test reporting
php /assets/functions/reporting.php --test

# Test webhooks (monitor logs)
tail -f /logs/webhooks.log
```

### Step 7: Update TIER 3 Files (10 files) ⏳ (20 min)
- Update archived code
- Update documentation
- Update schema files

### Step 8: Final Verification ✅ (15 min)
- Test in browser: all pages load
- Test all APIs
- Run all reports
- Monitor logs for errors

---

## 📊 TESTING CHECKLIST

```
TIER 1 FILES TESTED:
- [ ] TransactionModel queries work
- [ ] AuditLogModel logging works
- [ ] MatchingEngine finds matches
- [ ] Controllers load correct data
- [ ] APIs return HTTP 200
- [ ] Reassign payment works

TIER 2 FILES TESTED:
- [ ] Xero cron job executes
- [ ] Reporting functions generate reports
- [ ] Webhook receivers process data
- [ ] Reconciliation page displays
- [ ] Bank debug page works

TIER 3 FILES TESTED:
- [ ] Archive files have correct syntax
- [ ] Documentation updated
- [ ] Schema files valid SQL

PRODUCTION VERIFICATION:
- [ ] Dashboard loads with data
- [ ] Transaction list displays
- [ ] Auto-matching works
- [ ] Manual review queue works
- [ ] Exports function correctly
- [ ] All page routes accessible
```

---

## 🔐 ROLLBACK PROCEDURE

If anything breaks, rollback in this order:

1. **Restore database from backup**
   ```bash
   mysql -h 127.0.0.1 -u jcepnzzkmj -pwprKh9Jq63 jcepnzzkmj < /backups/jcepnzzkmj_before.sql
   ```

2. **Restore files from backup**
   ```bash
   cp -r /backups/bank-transactions /home/master/applications/jcepnzzkmj/public_html/modules/
   ```

3. **Clear cache**
   ```bash
   php /app.php --cache:clear
   ```

4. **Verify restoration**
   ```bash
   php /modules/bank-transactions/QUICK_BOT_TEST.php
   ```

---

## 📈 ESTIMATED TIMELINE

| Phase | Files | Time | Status |
|-------|-------|------|--------|
| Prepare | - | 5 min | ✅ Ready |
| DB Rename | - | 2 min | ⏳ Awaiting approval |
| TIER 1 Update | 19 | 30 min | ⏳ Ready |
| TIER 1 Test | 9 APIs | 5 min | ⏳ Ready |
| TIER 2 Update | 17 | 30 min | ⏳ Ready |
| TIER 2 Test | Prod ops | 10 min | ⏳ Ready |
| TIER 3 Update | 10 | 20 min | ⏳ Ready |
| Final Verify | All | 15 min | ⏳ Ready |
| **TOTAL** | **46** | **~2 hours** | **✅ READY** |

---

## ✅ HOMEWORK COMPLETE

All files have been:
- ✅ Identified and catalogued
- ✅ Analyzed for dependencies
- ✅ Risk assessed
- ✅ Mapped with exact line numbers
- ✅ Organized by priority
- ✅ Documented with execution steps

**Status: READY FOR EXECUTION**

The team can proceed with Phase 1 (Database Rename) when ready, with confidence that all impacts have been identified and mitigated.

---

**Prepared for:** Bank Transactions Module Renaming
**Prepared by:** Comprehensive System Audit
**Ready to Execute:** YES ✅
**Next Action:** Approve and execute Phase 1 (Database Rename)
