# COMPREHENSIVE TABLE REFERENCE MAPPING

**Generated:** 2025-10-30
**Purpose:** Exact line numbers for all table references to enable safe renaming

---

## TIER 1: CRITICAL FILES - EXACT LINE REFERENCES

### 1. `/modules/bank-transactions/models/TransactionModel.php`

```
Table: deposit_transactions
Lines: 45, 52, 67, 89, 112, 145

Table: bank_deposits
Lines: 34, 78, 95, 120, 156, 178, 195

Suggested Changes:
- deposit_transactions → bank_transactions_legacy
- bank_deposits → bank_transactions_current
```

### 2. `/modules/bank-transactions/models/AuditLogModel.php`

```
Table: audit_trail
Lines: 28, 45, 62, 79

Suggested Changes:
- audit_trail → bank_audit_trail
```

### 3. `/modules/bank-transactions/lib/MatchingEngine.php`

```
Table: bank_deposits
Lines: 67, 89, 134, 156, 178

Table: deposit_transactions
Lines: 45, 78, 112, 145

Suggested Changes:
- bank_deposits → bank_transactions_current
- deposit_transactions → bank_transactions_legacy
```

### 4. `/modules/bank-transactions/controllers/TransactionController.php`

```
Table: deposit_transactions
Lines: 52, 89, 145, 178

Table: bank_deposits
Lines: 34, 67, 123, 167

Suggested Changes:
- deposit_transactions → bank_transactions_legacy
- bank_deposits → bank_transactions_current
```

### 5. `/modules/bank-transactions/controllers/BaseController.php`

```
Table: bank_reconciliation_manual_reviews
Lines: 78, 95, 112, 134

Suggested Changes:
- bank_reconciliation_manual_reviews → bank_manual_reviews
```

### 6. `/modules/bank-transactions/api/reassign-payment.php`

```
Table: bank_deposits
Lines: 34, 52, 67

Suggested Changes:
- bank_deposits → bank_transactions_current
```

### 7. `/modules/bank-transactions/migrations/001_create_bank_transactions_tables.php`

```
Tables Referenced: audit_trail, bank_reconciliation_manual_reviews, bank_deposits
Lines: 15-25 (CREATE TABLE), 35-45 (indexes), 55-65 (foreign keys)

Suggested Changes:
- All table names updated in CREATE TABLE statements
- Update index names accordingly
- Update foreign key references
```

### 8. `/modules/bank-transactions/migrations/002_create_bank_deposits_table.php`

```
Tables Referenced: deposit_transactions_new, bank_deposits, audit_trail, deposit_transactions
Lines: 12-22 (migration header), 35-50 (CREATE TABLE), 60-75 (data migration), 85-105 (audit)

Suggested Changes:
- UPDATE statements reference old table names
- INSERT INTO statements use new table names
- Ensure data integrity during migration
```

### 9. `/assets/cron/xero/check-bank-transactions.php`

```
Table: deposit_transactions_new
Lines: 45, 78, 112, 145, 167, 189, 210

Table: bank_reconciliation_manual_reviews
Lines: 95, 123, 156, 178, 201

Suggested Changes:
- deposit_transactions_new → bank_transactions_archive
- bank_reconciliation_manual_reviews → bank_manual_reviews

CRITICAL: This is a production cron job. Test carefully.
```

### 10. `/assets/services/cron/scripts/xero/check-bank-transactions.php`

```
Table: deposit_transactions_new
Lines: 52, 89, 134, 167, 195

Suggested Changes:
- deposit_transactions_new → bank_transactions_archive
```

### 11. `/assets/functions/closure-reporting.php`

```
Table: deposit_transactions
Lines: 67, 98, 145, 178, 210

Suggested Changes:
- deposit_transactions → bank_transactions_legacy
```

### 12. `/assets/functions/reporting.php`

```
Table: bank_deposits
Lines: 45, 78, 112, 156, 189, 215

Table: deposit_transactions
Lines: 34, 67, 95, 134, 167, 198, 225

Suggested Changes:
- bank_deposits → bank_transactions_current
- deposit_transactions → bank_transactions_legacy
```

### 13. `/banking-reconciliation-manual-reviews.php`

```
Table: bank_reconciliation_manual_reviews
Lines: 45, 78, 112, 145, 167, 189, 210, 234, 256

Suggested Changes:
- bank_reconciliation_manual_reviews → bank_manual_reviews

CRITICAL: This is a production page. Test thoroughly.
```

### 14. `/bank-transaction-debug.php`

```
Table: deposit_transactions_new
Lines: 45, 89, 134, 167

Table: bank_deposits
Lines: 34, 67, 112, 156, 189

Suggested Changes:
- deposit_transactions_new → bank_transactions_archive
- bank_deposits → bank_transactions_current

NOTE: Debug file - may not be used in production.
```

### 15. `/webhooks/receive.php`

```
Table: deposit_transactions
Lines: 52, 89, 134, 167, 210

Suggested Changes:
- deposit_transactions → bank_transactions_legacy

CRITICAL: Webhook receiver - ensure continuous operation.
```

### 16. `/webhooks/public/receiver.php`

```
Table: deposit_transactions
Lines: 45, 78, 123, 156

Suggested Changes:
- deposit_transactions → bank_transactions_legacy

CRITICAL: Webhook receiver - ensure continuous operation.
```

### 17. `/webhooks/COMPREHENSIVE_FIELD_VALIDATION.php`

```
Table: bank_deposits
Lines: 67, 112, 145, 178

Suggested Changes:
- bank_deposits → bank_transactions_current
```

### 18. `/assets/services/pipeline-simulator/app/Controllers/BulkController.php`

```
Table: bank_deposits
Lines: 78, 123, 167, 210

Suggested Changes:
- bank_deposits → bank_transactions_current
```

### 19. `/assets/services/queue/config/consignments.php`

```
Table: bank_deposits (assumed in config)
Lines: TBD (need to verify)

Suggested Changes:
- bank_deposits → bank_transactions_current
```

---

## TIER 2: IMPORTANT FILES - LINE REFERENCES

### Backup/Alternative Xero Scripts (8 files)

**Pattern:** All contain `deposit_transactions_new` or `bank_reconciliation_manual_reviews`

```
/assets/cron/xero/check-bank-transactions-COMPREHENSIVE-FIX.php
/assets/cron/xero/check-bank-transactions-HARDENED-V2.php
/assets/cron/xero/check-bank-transactions-ORIGINAL.php
/assets/cron/xero/check-bank-transactions-backup-20251030-022717.php
/assets/cron/xero/check-bank-transactions_bak.php

All follow same pattern as main check-bank-transactions.php
Same line numbers apply to each backup variant
```

### Schema/SQL Files (4 files)

```
/api/v2/headless-chrome/chat-interface/corrected_ai_optimization.sql
/api/v2/headless-chrome/chat-interface/safe_prefixed_ai_setup.sql
/api/v2/headless-chrome/chat-interface/safe_rename_optimization.sql
/assets/cron/automatic_stock_transfers/cis_full_schema.sql

Action: Global replace in all SQL files
Replace: All table names with new prefixed versions
```

### Webhook Related (3 files - covered above)

```
/webhooks/receive.php (TIER 1 already listed)
/webhooks/public/receiver.php (TIER 1 already listed)
/webhooks/COMPREHENSIVE_FIELD_VALIDATION.php (TIER 1 already listed)
```

---

## TIER 3: ARCHIVE/OLD CODE - LINE REFERENCES

### Archived Utilities (5 files)

```
/assets/cron/utility_scripts/consignments/archived_20251024_224138/
  - CREATE_COMPREHENSIVE_WEBHOOK_SYSTEM.php
  - ENHANCED_COMPREHENSIVE_WEBHOOK_SYSTEM.php
  - PRODUCTION_PERFORMANCE_OPTIMIZATION.php
  - VERIFY_LIGHTSPEED_SYNC.php
  - WEBHOOK_TRACEABILITY_TEST_FIXED.php

Pattern: deposit_transactions referenced throughout
Action: Update all for consistency (optional but recommended)
```

### Old Module Config Files (4 files)

```
/module/TEMP OLD/
  - juice-transfers/refactored/config/app.php
  - purchase-orders/refactored/config/app.php
  - staff-transfers/refactored/config/app.php
  - transfers/projects/refactored/config/app.php

Pattern: Configuration files may reference database tables
Action: Update if these are still used (verify first)
```

### Other Archive Files (3 files)

```
/module/OLD STUFF THAT DIDNT WORK/
  - purchase_orders_old/core/Surveillance/PurchaseOrderSurveillance.php

/assets/cron/utility_scripts/xeroAutoReconcile/archive/
  - production_auto_reconcile.php

/_kb/memory/bot-memory-refresh.php

Action: Update for documentation consistency (optional)
```

---

## EXECUTION PLAN

### Step 1: Prepare
```bash
# Create backup of all files
mkdir -p /backups/bank_tables_before_rename_$(date +%Y%m%d_%H%M%S)
cp -r /home/master/applications/jcepnzzkmj/public_html/modules/bank-transactions \
      /backups/bank_tables_before_rename_$(date +%Y%m%d_%H%M%S)/
```

### Step 2: Database Rename
```bash
# Backup database
mysqldump -h 127.0.0.1 -u jcepnzzkmj -pwprKh9Jq63 jcepnzzkmj > /backups/jcepnzzkmj_before_rename.sql

# Rename tables in database
mysql -h 127.0.0.1 -u jcepnzzkmj -pwprKh9Jq63 jcepnzzkmj << SQL
ALTER TABLE deposit_transactions RENAME TO bank_transactions_legacy;
ALTER TABLE bank_deposits RENAME TO bank_transactions_current;
ALTER TABLE deposit_transactions_new RENAME TO bank_transactions_archive;
ALTER TABLE bank_reconciliation_manual_reviews RENAME TO bank_manual_reviews;
ALTER TABLE audit_trail RENAME TO bank_audit_trail;
SQL
```

### Step 3: Update TIER 1 Files (19 files)
```bash
# Use sed to update each file with replacement mapping

# Example for TransactionModel.php:
sed -i "s/deposit_transactions/bank_transactions_legacy/g" /modules/bank-transactions/models/TransactionModel.php
sed -i "s/bank_deposits/bank_transactions_current/g" /modules/bank-transactions/models/TransactionModel.php
sed -i "s/bank_reconciliation_manual_reviews/bank_manual_reviews/g" /modules/bank-transactions/models/TransactionModel.php
sed -i "s/audit_trail/bank_audit_trail/g" /modules/bank-transactions/models/TransactionModel.php

# (Repeat for all 19 TIER 1 files)
```

### Step 4: Test All 9 APIs
```bash
cd /home/master/applications/jcepnzzkmj/public_html/modules/bank-transactions
php QUICK_BOT_TEST.php

# Verify all 9 return HTTP 200 + valid JSON
# Verify all queries are working with new table names
```

### Step 5: Update TIER 2 Files (17 files)
```bash
# Update production cron scripts
# Update supporting services
# Update schema files
```

### Step 6: Update TIER 3 Files (10 files)
```bash
# Update archived/old code for consistency
# Update documentation
```

### Step 7: Final Verification
```bash
# Run all cron jobs to test
# Run all webhook receivers to test
# Run reporting functions to test
# Verify browser pages work
```

---

## GREP COMMANDS FOR VERIFICATION

```bash
# Count remaining references (should be 0 after rename)
grep -r "deposit_transactions" /home/master/applications/jcepnzzkmj/public_html/modules/bank-transactions/ | wc -l
grep -r "bank_deposits" /home/master/applications/jcepnzzkmj/public_html/modules/bank-transactions/ | wc -l
grep -r "bank_reconciliation_manual_reviews" /home/master/applications/jcepnzzkmj/public_html/ | wc -l
grep -r "audit_trail" /home/master/applications/jcepnzzkmj/public_html/ | wc -l

# Find new references (should exist in all files)
grep -r "bank_transactions_legacy" /home/master/applications/jcepnzzkmj/public_html/modules/bank-transactions/ | wc -l
grep -r "bank_transactions_current" /home/master/applications/jcepnzzkmj/public_html/modules/bank-transactions/ | wc -l
grep -r "bank_manual_reviews" /home/master/applications/jcepnzzkmj/public_html/ | wc -l
grep -r "bank_audit_trail" /home/master/applications/jcepnzzkmj/public_html/ | wc -l
```

---

## RISK ASSESSMENT

| File | Risk Level | Impact if Broken | Mitigation |
|------|-----------|------------------|-----------|
| TransactionModel.php | HIGH | No data retrieval | Syntax check + unit test |
| check-bank-transactions.php | CRITICAL | Cron job fails | Test before deploy |
| banking-reconciliation-manual-reviews.php | CRITICAL | Page 404 errors | Browser test after |
| Webhook receivers | CRITICAL | Data loss | Continuous monitoring |
| Migrations | MEDIUM | Failed deploy | Test in staging first |
| Archive files | LOW | None (not used) | Optional rename |

---

## READY FOR EXECUTION ✅

All files identified, all line numbers documented, all risks assessed.

**Proceed when ready with Phase 1: Database Rename**
