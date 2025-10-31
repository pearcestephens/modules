# Sprint 2 Migration - Ready to Execute

## Status: ✅ READY FOR EXECUTION

All preparation complete. Migration script is production-ready and includes:
- Automatic backups
- Syntax validation
- Rollback on errors
- Comprehensive logging

---

## What Will Be Migrated

### Phase 1: Bootstrap Files (4 files)
- `modules/flagged_products/bootstrap.php`
- `modules/flagged-products/bootstrap.php`
- `modules/shared/bootstrap.php`
- `modules/consignments/bootstrap.php`

**Action:** Comment out direct app.php requires

### Phase 2: Module Files (~52 files)

#### By Module:
- **flagged_products**: 12 files (views, cron, API, functions)
- **human_resources/payroll**: 8 files (views, cron, router)
- **base**: 1 file (API)
- **staff-accounts**: 2 files (lib, tests)
- **bank-transactions**: 2 files (test scripts)
- **admin-ui**: 11 files (pages, APIs, theme builder)
- **consignments**: 16 files (purchase-orders UI, APIs, CLI)

**Action:** Replace all `require.*app.php` patterns with `require_once __DIR__ . '/relative/path/to/bootstrap.php'`

---

## Safety Features

### ✅ Automatic Backups
- Every file backed up to `/private_html/backups/sprint2_TIMESTAMP/`
- Individual file backups: `modulename_filename.php.bak`
- Bootstrap backups: `modulename_bootstrap.php.bak`

### ✅ Syntax Validation
- PHP lint check on every migrated file
- Automatic rollback on syntax errors
- Error reporting with file names

### ✅ Smart Exclusions
- Skips: `_archive/`, `/tests/`, `/docs/`, `.md`, backups
- Already migrated files detected and skipped
- Consignments Sprint 1 files protected

### ✅ Comprehensive Logging
- Real-time colored output
- Per-file status reporting
- Final summary with counts
- Remaining app.php usage report

---

## Execution

### Command:
```bash
cd /home/master/applications/jcepnzzkmj/public_html/modules/consignments/tests
chmod +x sprint2-complete-migration.sh
./sprint2-complete-migration.sh
```

### Expected Output:
```
==========================================
  Sprint 2: Bootstrap Pattern Migration
==========================================

📦 Creating backup directory...
✓ Backups will be saved to: /private_html/backups/sprint2_YYYYMMDD_HHMMSS

═══════════════════════════════════════
  PHASE 1: Fix Bootstrap Files
═══════════════════════════════════════

⚠ Found app.php in: flagged_products/bootstrap.php
  ✓ Fixed: flagged_products/bootstrap.php
...

Phase 1 Complete: Fixed 4 bootstrap files

═══════════════════════════════════════
  PHASE 2: Migrate Module Files
═══════════════════════════════════════

📁 Processing: flagged_products
    ✓ Migrated: index.php
    ✓ Migrated: dashboard.php
    ...

📁 Processing: admin-ui
    ✓ Migrated: files.php
    ...

═══════════════════════════════════════
  PHASE 3: Validation
═══════════════════════════════════════

Running syntax check on migrated files...
  ✓ All files pass syntax check

═══════════════════════════════════════
  MIGRATION COMPLETE
═══════════════════════════════════════

✓ Bootstrap files fixed: 4
✓ Module files migrated: 52
✗ Failed migrations: 0
✗ Syntax errors: 0

📦 Backups saved to:
   /private_html/backups/sprint2_YYYYMMDD_HHMMSS

Checking for remaining app.php usage...
✓ No app.php usage remaining in active code!

════════════════════════════════════════
  Sprint 2 Migration Complete! 🎉
════════════════════════════════════════
```

### Estimated Time: 30-60 seconds

---

## Post-Migration Validation

### 1. Syntax Check (Automated in script)
```bash
find modules/ -name '*.php' -exec php -l {} \; | grep -v 'No syntax errors'
```

### 2. Test Critical Endpoints
```bash
cd modules/consignments/tests
php test-sprint1-endpoints.php
```

### 3. Manual Spot Checks
```bash
# Check consignments accept endpoint
curl -X POST https://staff.vapeshed.co.nz/modules/consignments/api/purchase-orders/accept-ai-insight.php \
  -H "Content-Type: application/json" \
  -d '{"insight_id": 1}' \
  -b cookies.txt

# Check flagged products dashboard
curl https://staff.vapeshed.co.nz/modules/flagged_products/views/dashboard.php -b cookies.txt

# Check admin-ui
curl https://staff.vapeshed.co.nz/modules/admin-ui/pages/overview.php -b cookies.txt
```

### 4. Monitor Error Logs
```bash
tail -f /home/master/applications/jcepnzzkmj/logs/apache_*.error.log
```

---

## Rollback Procedure (if needed)

If anything goes wrong, restore from backups:

```bash
# Find your backup directory
BACKUP_DIR="/home/master/applications/jcepnzzkmj/private_html/backups/sprint2_YYYYMMDD_HHMMSS"

# Restore all files
for backup in $BACKUP_DIR/*.bak; do
    # Extract original path from backup filename
    original=$(echo "$backup" | sed 's/.bak$//' | sed 's/_/\//g' | sed "s|$BACKUP_DIR/|/home/master/applications/jcepnzzkmj/public_html/modules/|")
    cp "$backup" "$original"
    echo "Restored: $original"
done
```

---

## What Changed - Technical Details

### Pattern Replacements

**Before:**
```php
require_once $_SERVER['DOCUMENT_ROOT'] . '/app.php';
```

**After:**
```php
require_once __DIR__ . '/../../bootstrap.php';  // Relative to module bootstrap
```

### Bootstrap Files

**Before:**
```php
if (file_exists(ROOT_PATH . '/app.php')) {
    require_once ROOT_PATH . '/app.php';
}
```

**After:**
```php
// REMOVED: require_once ROOT_PATH . '/app.php'; // Sprint 2: Use shared bootstrap
```

---

## Files Modified Summary

### Consignments Module (Sprint 1 - Already Complete ✅)
- ✅ `api/purchase-orders/accept-ai-insight.php`
- ✅ `api/purchase-orders/dismiss-ai-insight.php`
- ✅ `api/purchase-orders/bulk-accept-ai-insights.php`
- ✅ `api/purchase-orders/bulk-dismiss-ai-insights.php`
- ✅ `api/purchase-orders/log-interaction.php` (NEW)
- ✅ `api/purchase-orders/receive.php` (UPDATED - hardened review scheduling)
- ✅ `lib/Services/TransferReviewService.php` (REWRITTEN)
- ✅ `lib/PurchaseOrderLogger.php` (ENHANCED - added reviewScheduled method)

### Sprint 2 - To Be Migrated
- **flagged_products**: 12 files
- **human_resources/payroll**: 8 files
- **base**: 1 file
- **staff-accounts**: 2 files
- **bank-transactions**: 2 files
- **admin-ui**: 11 files
- **consignments** (remaining UI): 16 files

**Total Sprint 2:** ~52 files

---

## Success Criteria

### Must Pass:
- ✅ All files have syntax-valid PHP
- ✅ No app.php usage in active code (excluding docs/archives)
- ✅ Critical endpoints respond successfully
- ✅ No error log spikes after migration
- ✅ Backups created and verified

### Should Achieve:
- 🎯 Zero failed migrations
- 🎯 Zero syntax errors
- 🎯 100% of active files migrated
- 🎯 All modules use bootstrap pattern consistently

---

## Risk Assessment

### Low Risk ✅
- Automatic backups for every file
- Syntax validation catches parse errors
- Smart exclusions prevent breaking tests/docs
- Rollback procedure documented

### Medium Risk ⚠️
- Relative path calculation (handled by realpath)
- Human_resources module structure (has payroll subdirectory)
- Flagged-products vs flagged_products naming (both handled)

### Mitigations Applied:
- Test script on non-production files first (Sprint 1 proven)
- Backup directory timestamped and outside public_html
- Syntax check with automatic rollback
- Manual validation steps documented

---

## Timeline

| Phase | Duration | Status |
|-------|----------|--------|
| Sprint 1 | Completed | ✅ Done |
| Script Preparation | 30 min | ✅ Done |
| Migration Execution | 1-2 min | ⏳ Ready |
| Validation | 5-10 min | 📋 Planned |
| Monitoring | 24 hours | 📋 Planned |

---

## Authorization Required

**User Confirmation Needed Before Execution:**

This script will modify **~52 production files** across **7 modules**.

- ✅ Backups will be created automatically
- ✅ Syntax validation will catch errors
- ✅ Rollback procedure is documented

**Execute Sprint 2 migration?** (yes/no)

If **yes**, the agent will:
1. Run `chmod +x sprint2-complete-migration.sh`
2. Execute the migration script
3. Report results
4. Run validation checks
5. Update todo list

---

## Post-Migration Next Steps

After successful Sprint 2:

1. ✅ **Mark Sprint 2 complete** in todo
2. 🔄 **Run test suite** (test-sprint1-endpoints.php)
3. 📊 **Create weekly report scheduler** (Sprint task)
4. 🎮 **Complete gamification integration** (verify points/achievements)
5. 🧪 **End-to-end integration test** (full PO workflow)

---

## Questions & Concerns

### Q: What if a file fails syntax check?
**A:** Script automatically restores from backup for that file and continues.

### Q: What about files in _archive/?
**A:** Skipped automatically. Archives remain untouched.

### Q: Can I test first without modifying files?
**A:** Yes, comment out the `migrate_file` function body and run in dry-run mode.

### Q: How do I verify the migration worked?
**A:** Script performs syntax check and reports remaining app.php usage. Then run test suite.

### Q: What if I need to rollback everything?
**A:** Use the rollback procedure above to restore all files from the timestamped backup directory.

---

## Ready to Execute

**Script Location:**
```
/home/master/applications/jcepnzzkmj/public_html/modules/consignments/tests/sprint2-complete-migration.sh
```

**Command:**
```bash
cd /home/master/applications/jcepnzzkmj/public_html/modules/consignments/tests
chmod +x sprint2-complete-migration.sh
./sprint2-complete-migration.sh
```

**Awaiting user confirmation to proceed...**

---

## Agent Status

✅ Sprint 1: Complete
✅ Sprint 2 Preparation: Complete
⏳ Sprint 2 Execution: Ready (awaiting permission)
📋 Sprint 3: Planned
📋 Sprint 4: Planned
📋 Final Integration: Planned

**Current Focus:** Sprint 2 execution
**Next Milestone:** Complete bootstrap migration across all modules
**Final Goal:** Fully integrated, tested, production-ready PO/Consignments subsystem with AI insights, gamification, and automated reviews.
