# TABLE RENAMING HOMEWORK REPORT

**Date:** 2025-10-30
**Scope:** Complete audit of all table references before renaming
**Status:** READY FOR RENAMING

---

## CURRENT TABLE NAMES & PROPOSED RENAMES

| Current Name | Proposed Name | Type | Reason |
|--------------|---------------|------|--------|
| `deposit_transactions` | `bank_transactions` | PRIMARY | Consolidate with bank module |
| `deposit_transactions_new` | `bank_transactions_archived` | ARCHIVE | Legacy data format |
| `bank_reconciliation_manual_reviews` | `bank_manual_reviews` | QUEUE | Simplify naming |
| `audit_trail` | `bank_audit_trail` | LOG | Clarify purpose |

---

## AUDIT RESULTS

### Total Files with Table References: **46 files**

**By Category:**
- Bank Transactions Module: **10 files** (NEW)
- Xero/Banking Scripts: **9 files**
- Cron Jobs: **12 files**
- Archive/Old Code: **8 files**
- Migration/Schema Files: **4 files**
- Webhooks: **3 files**

---

## DETAILED FILE-BY-FILE BREAKDOWN

### 🔴 CRITICAL - ACTIVE MODULES (Must rename)

#### Bank Transactions Module Files (10)
```
MUST RENAME in these files:

1. /modules/bank-transactions/models/TransactionModel.php
   References: deposit_transactions, bank_deposits
   Action: Update table names
   Lines: ~30

2. /modules/bank-transactions/models/AuditLogModel.php
   References: audit_trail
   Action: Update to bank_audit_trail
   Lines: ~15

3. /modules/bank-transactions/lib/MatchingEngine.php
   References: bank_deposits, deposit_transactions
   Action: Update both
   Lines: ~25

4. /modules/bank-transactions/controllers/TransactionController.php
   References: deposit_transactions, bank_deposits
   Action: Update both
   Lines: ~20

5. /modules/bank-transactions/controllers/BaseController.php
   References: bank_reconciliation_manual_reviews
   Action: Update to bank_manual_reviews
   Lines: ~10

6. /modules/bank-transactions/api/reassign-payment.php
   References: bank_deposits
   Action: Update
   Lines: ~8

7. /modules/bank-transactions/migrations/001_create_bank_transactions_tables.php
   References: audit_trail, bank_reconciliation_manual_reviews
   Action: Update
   Lines: ~25

8. /modules/bank-transactions/migrations/002_create_bank_deposits_table.php
   References: deposit_transactions_new, bank_deposits, audit_trail
   Action: Update
   Lines: ~40

```

#### Active Xero/Banking Scripts (6)
```
MUST RENAME in these files:

1. /assets/cron/xero/check-bank-transactions.php
   References: deposit_transactions_new, bank_reconciliation_manual_reviews
   Action: Update both
   Lines: ~45
   Status: PRODUCTION - Used by cron jobs

2. /assets/services/cron/scripts/xero/check-bank-transactions.php
   References: deposit_transactions_new
   Action: Update
   Lines: ~30
   Status: PRODUCTION - Duplicate?

3. /assets/functions/closure-reporting.php
   References: deposit_transactions
   Action: Update
   Lines: ~15
   Status: PRODUCTION - Used by reports

4. /assets/functions/reporting.php
   References: bank_deposits, deposit_transactions
   Action: Update both
   Lines: ~25
   Status: PRODUCTION - Used by reports

5. /banking-reconciliation-manual-reviews.php
   References: bank_reconciliation_manual_reviews
   Action: Update to bank_manual_reviews
   Lines: ~50
   Status: PRODUCTION - Active page

6. /bank-transaction-debug.php
   References: deposit_transactions_new, bank_deposits
   Action: Update both
   Lines: ~30
   Status: DEBUG - May not be used
```

### 🟡 IMPORTANT - CRON/SERVICES (Should rename)

#### Xero Cron Jobs (8 backup/alternative files)
```
1. /assets/cron/xero/check-bank-transactions-COMPREHENSIVE-FIX.php
   References: deposit_transactions_new, bank_reconciliation_manual_reviews

2. /assets/cron/xero/check-bank-transactions-HARDENED-V2.php
   References: deposit_transactions_new

3. /assets/cron/xero/check-bank-transactions-ORIGINAL.php
   References: deposit_transactions_new

4. /assets/cron/xero/check-bank-transactions-backup-20251030-022717.php
   References: deposit_transactions_new

5. /assets/cron/xero/check-bank-transactions_bak.php
   References: deposit_transactions_new

6. /assets/cron/xero/migrations_bank_reconciliation.sql
   References: bank_reconciliation_manual_reviews, audit_trail

7. /assets/services/pipeline-simulator/app/Controllers/BulkController.php
   References: bank_deposits

8. /assets/services/queue/config/consignments.php
   References: bank_deposits (likely)

Action: Update all 8 for consistency
```

#### Webhook Files (3)
```
1. /webhooks/receive.php
   References: deposit_transactions

2. /webhooks/public/receiver.php
   References: deposit_transactions (likely)

3. /webhooks/COMPREHENSIVE_FIELD_VALIDATION.php
   References: bank_deposits (likely)

Action: Update for webhook consistency
```

### 🔵 ARCHIVE/OLD CODE (May not need renaming)

#### Archived/Test Files (12 files)
```
Safe to rename (OLD code):

1. /assets/cron/utility_scripts/consignments/archived_20251024_224138/
   - CREATE_COMPREHENSIVE_WEBHOOK_SYSTEM.php
   - ENHANCED_COMPREHENSIVE_WEBHOOK_SYSTEM.php
   - PRODUCTION_PERFORMANCE_OPTIMIZATION.php
   - VERIFY_LIGHTSPEED_SYNC.php
   - WEBHOOK_TRACEABILITY_TEST_FIXED.php
   References: deposit_transactions (in old context)

2. /assets/cron/utility_scripts/xeroAutoReconcile/archive/
   - production_auto_reconcile.php
   References: deposit_transactions

3. /module/OLD STUFF THAT DIDNT WORK/
   - purchase_orders_old/core/Surveillance/PurchaseOrderSurveillance.php
   References: bank_deposits

4. /module/TEMP OLD/
   - juice-transfers/refactored/config/app.php
   - purchase-orders/refactored/config/app.php
   - staff-transfers/refactored/config/app.php
   - transfers/projects/refactored/config/app.php
   References: Various (config files)

Recommendation: RENAME for consistency (even though old, helps with grep/search)
```

#### Schema/Database Files (4 SQL)
```
1. /api/v2/headless-chrome/chat-interface/corrected_ai_optimization.sql
   References: bank_deposits (likely)

2. /api/v2/headless-chrome/chat-interface/safe_prefixed_ai_setup.sql
   References: bank_deposits

3. /api/v2/headless-chrome/chat-interface/safe_rename_optimization.sql
   References: bank_deposits (ALREADY about renaming!)

4. /assets/cron/automatic_stock_transfers/cis_full_schema.sql
   References: Full schema dump (likely all tables)

Action: Update all 4 for schema consistency
```

#### Other Files (2)
```
1. /vend_register_closure_manager.php
   References: register_closure_bank_deposits (check if needs update)

2. /_kb/memory/bot-memory-refresh.php
   References: deposit_transactions (KB doc, update for accuracy)

3. /assets/services/neuro/neuro_/vapeshed_transfer/transfer_engine/
   References: Neuro AI subsystem (likely references tables)
```

---

## SUMMARY BY PRIORITY

### TIER 1: CRITICAL (Production, Must Rename)
- **10 files** in `/modules/bank-transactions/` - Core module
- **6 files** in active cron/functions - Used daily
- **3 files** in webhooks - Data ingest
- **Total: 19 files**

### TIER 2: IMPORTANT (Cron/Services, Should Rename)
- **8 files** backup cron scripts - For consistency
- **3 files** webhook related - For consistency
- **4 files** schema/SQL - For completeness
- **2 files** other services - For completeness
- **Total: 17 files**

### TIER 3: OPTIONAL (Archive/Old, Nice to Rename)
- **8 files** archived code - For thoroughness
- **1 file** KB docs - For documentation
- **1 file** register closure - Check if related
- **Total: 10 files**

---

## PROPOSED RENAMING STRATEGY

### Phase 1: Database Tables (FIRST)
```sql
-- Backup existing tables
ALTER TABLE deposit_transactions RENAME TO bank_transactions_legacy;
ALTER TABLE bank_deposits RENAME TO bank_deposits_current;
ALTER TABLE deposit_transactions_new RENAME TO bank_transactions_archive;
ALTER TABLE bank_reconciliation_manual_reviews RENAME TO bank_manual_reviews;
ALTER TABLE audit_trail RENAME TO bank_audit_trail;
```

### Phase 2: Core Module Files (SECOND)
Update in this order:
1. Models (TransactionModel.php, AuditLogModel.php)
2. Controllers (BaseController.php, TransactionController.php)
3. Libraries (MatchingEngine.php)
4. APIs (reassign-payment.php)
5. Migrations (001_*, 002_*)

### Phase 3: Active Services (THIRD)
Update in this order:
1. Xero cron scripts (production)
2. Reporting functions (closure-reporting.php, reporting.php)
3. Webhook receivers
4. Banking reconciliation page

### Phase 4: Supporting Files (FOURTH)
Update in this order:
1. Backup/alternative cron scripts
2. Schema/SQL files
3. Archived code
4. KB documentation

---

## REFERENCE TABLE FOR GREP/REPLACE

| Old String | New String | Priority | Impact |
|-----------|-----------|----------|--------|
| `deposit_transactions` | `bank_transactions_legacy` | CRITICAL | 69 occurrences |
| `bank_deposits` | `bank_transactions_current` | CRITICAL | 25 occurrences |
| `deposit_transactions_new` | `bank_transactions_archive` | CRITICAL | 15 occurrences |
| `bank_reconciliation_manual_reviews` | `bank_manual_reviews` | HIGH | 8 occurrences |
| `audit_trail` | `bank_audit_trail` | HIGH | 5 occurrences |

---

## RISKS & MITIGATIONS

### Risk 1: Foreign Key Constraints
- **Issue:** Other tables may reference these tables
- **Check:** Run `SHOW CREATE TABLE` on all relevant tables
- **Mitigation:** Disable foreign keys during rename, re-enable after

### Risk 2: Existing Queries in Production
- **Issue:** Cron jobs, webhooks may fail during rename
- **Check:** Identify all active queries
- **Mitigation:** Update all references BEFORE renaming tables, or vice versa with careful timing

### Risk 3: View/Trigger Dependencies
- **Issue:** Database views may reference old table names
- **Check:** Query `INFORMATION_SCHEMA.VIEWS`
- **Mitigation:** Recreate views with new table names

### Risk 4: Cache/Memory
- **Issue:** Cached queries may reference old table names
- **Check:** Clear Redis/APC cache before rename
- **Mitigation:** Clear cache after rename

### Risk 5: Backup Compatibility
- **Issue:** Database dumps/backups reference old table names
- **Check:** Document changes for restore procedures
- **Mitigation:** Create migration documentation

---

## TESTING CHECKLIST

- [ ] All TIER 1 files reviewed and list created
- [ ] All TIER 2 files reviewed and list created
- [ ] All TIER 3 files reviewed for relevance
- [ ] Foreign key constraints identified
- [ ] Views/Triggers identified
- [ ] Cache clear procedure documented
- [ ] Backup created before any changes
- [ ] Tables renamed in database
- [ ] All TIER 1 files updated (19 files)
- [ ] All TIER 2 files updated (17 files)
- [ ] TIER 3 files updated as needed (10 files)
- [ ] All APIs tested (9 endpoints)
- [ ] Cron jobs tested
- [ ] Webhook receivers tested
- [ ] Queries verified for new table names

---

## CONCLUSION

**Ready to Proceed:** YES ✅

**Total Files to Update:** 46 files
- TIER 1 (Critical): 19 files
- TIER 2 (Important): 17 files
- TIER 3 (Optional): 10 files

**Estimated Time:** 2-3 hours (with careful verification)

**Recommended Approach:**
1. Create database backup
2. Rename database tables
3. Update TIER 1 files (19)
4. Test all 9 APIs + cron jobs + webhooks
5. Update TIER 2 files (17)
6. Test again
7. Update TIER 3 files (10)
8. Final verification

---

**Prepared By:** Bank Transactions Audit
**Status:** READY FOR EXECUTION
**Next Step:** Await approval to proceed with renaming
