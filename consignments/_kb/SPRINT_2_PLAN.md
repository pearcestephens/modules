# 🚀 SPRINT 2 EXECUTION PLAN

**Date:** October 31, 2025
**Sprint:** 2 of 4
**Focus:** Repository-Wide Bootstrap Pattern Migration
**Duration:** Autonomous execution (30-45 minutes)
**Status:** 🟡 IN PROGRESS

---

## 🎯 SPRINT 2 OBJECTIVES

Migrate all remaining modules from `app.php` to module-local `bootstrap.php` pattern, ensuring consistency with Sprint 1 fixes.

### Success Criteria
- ✅ Zero `require.*app.php` usage in active code (excluding docs/archives)
- ✅ All modules use `require_once __DIR__ . '/path/to/bootstrap.php'`
- ✅ All syntax validated (php -l)
- ✅ Critical paths tested

---

## 📋 MODULE MIGRATION CHECKLIST

### Phase 1: flagged_products Module
**Files to Fix:** 15
**Priority:** HIGH (gamification integration dependency)

- [ ] `/flagged_products/index.php`
- [ ] `/flagged_products/views/summary.php`
- [ ] `/flagged_products/views/leaderboard.php`
- [ ] `/flagged_products/views/dashboard.php`
- [ ] `/flagged_products/functions/api.php`
- [ ] `/flagged_products/api/report-violation.php`
- [ ] `/flagged_products/api/complete-product.php`
- [ ] `/flagged_products/api/cron_monitoring.php`
- [ ] `/flagged_products/cron/refresh_leaderboard.php`
- [ ] `/flagged_products/cron/check_achievements.php`
- [ ] `/flagged_products/cron/register_tasks.php`
- [ ] `/flagged_products/cron/generate_ai_insights.php`
- [ ] `/flagged_products/cron/refresh_store_stats.php`
- [ ] `/flagged_products/cron/generate_daily_products.php`
- [ ] `/flagged_products/scripts/migrate-historic-data.php`

**Special Handling:**
- Module bootstrap is at `/flagged_products/bootstrap.php`
- Already has `ROOT_PATH . '/app.php'` fallback - update to prefer bootstrap

---

### Phase 2: human_resources Module
**Files to Fix:** 6
**Priority:** MEDIUM

- [ ] `/human_resources/payroll/router.php`
- [ ] `/human_resources/payroll/views/payslip.php`
- [ ] `/human_resources/payroll/cron/process_automated_reviews.php`
- [ ] `/human_resources/payroll/cron/update_dashboard.php`
- [ ] `/human_resources/payroll/cron/sync_deputy.php`
- [ ] `/human_resources/payroll/cron/payroll_auto_start.php`

**Special Handling:**
- Some cron files reference `private_html/app.php` - need to locate correct bootstrap
- CLI tool references need parent directory traversal

---

### Phase 3: base Module
**Files to Fix:** 1
**Priority:** HIGH (core module)

- [ ] `/base/api/ai-request.php`

**Special Handling:**
- Base module is foundation - must not break
- Verify base bootstrap exists and loads properly

---

### Phase 4: staff-accounts Module
**Files to Fix:** 2
**Priority:** MEDIUM

- [ ] `/staff-accounts/tests/test-nuvei-connection.php`
- [ ] `/staff-accounts/lib/sync-payments.php`

**Special Handling:**
- Test files may need different pattern
- Check if module has bootstrap

---

### Phase 5: bank-transactions Module
**Files to Fix:** 2
**Priority:** LOW (test files)

- [ ] `/bank-transactions/TEST_ALL_ENDPOINTS.php`
- [ ] `/bank-transactions/TEST_BOT_COMPLETE.php`

**Special Handling:**
- Test files, not production code
- May need bootstrap or direct DB connection

---

### Phase 6: admin-ui Module
**Files to Fix:** 9
**Priority:** MEDIUM

- [ ] `/admin-ui/pages/metrics.php`
- [ ] `/admin-ui/pages/overview.php`
- [ ] `/admin-ui/pages/rules.php`
- [ ] `/admin-ui/pages/settings.php`
- [ ] `/admin-ui/pages/violations.php`
- [ ] `/admin-ui/pages/files.php`
- [ ] `/admin-ui/pages/dependencies.php`
- [ ] `/admin-ui/ai-theme-builder.php`
- [ ] `/admin-ui/api/page-loader.php`
- [ ] `/admin-ui/api/file-explorer-api.php`
- [ ] `/admin-ui/api/ai-agent-handler.php`

**Special Handling:**
- Admin UI may not have bootstrap (check first)
- May need to create admin-ui bootstrap or use shared bootstrap

---

### Phase 7: Remaining Consignments Files
**Files to Fix:** 11
**Priority:** HIGH (same module as Sprint 1)

- [ ] `/consignments/purchase-orders/index.php`
- [ ] `/consignments/purchase-orders/tracking.php`
- [ ] `/consignments/purchase-orders/freight-label.php`
- [ ] `/consignments/purchase-orders/ai-insights.php`
- [ ] `/consignments/purchase-orders/view.php`
- [ ] `/consignments/purchase-orders/create.php`
- [ ] `/consignments/purchase-orders/freight-quote.php`
- [ ] `/consignments/cli/lightspeed-cli.php`
- [ ] `/consignments/api/products/search.php`
- [ ] `/consignments/api/purchase-orders/delete.php`
- [ ] `/consignments/api/purchase-orders/submit.php`
- [ ] `/consignments/api/purchase-orders/approve.php`
- [ ] `/consignments/api/purchase-orders/send.php`
- [ ] `/consignments/api/purchase-orders/create.php`
- [ ] `/consignments/api/purchase-orders/update.php`

**Special Handling:**
- These are UI pages and remaining API endpoints
- All should use `/consignments/bootstrap.php`

---

## 🔧 IMPLEMENTATION STRATEGY

### Automated Script
Location: `/tests/sprint2-migrate-bootstrap.sh`

**Features:**
- Automatic backup of all modified files
- Pattern detection and replacement
- Relative path calculation
- Bootstrap existence validation
- Syntax checking
- Rollback capability

### Manual Intervention Required For:
1. Modules without bootstrap.php (create new or use shared)
2. Files with complex include logic
3. Test files that need special handling

---

## 📊 EXECUTION LOG

### Pre-Flight Checks
- [ ] Verify all module bootstrap files exist
- [ ] Create backup directory: `/private_html/backups/sprint2_TIMESTAMP/`
- [ ] Git status clean (commit Sprint 1 first)

### Execution
- [ ] Run Phase 1: flagged_products
- [ ] Run Phase 2: human_resources
- [ ] Run Phase 3: base
- [ ] Run Phase 4: staff-accounts
- [ ] Run Phase 5: bank-transactions
- [ ] Run Phase 6: admin-ui
- [ ] Run Phase 7: remaining consignments

### Post-Execution
- [ ] Syntax check all modified files
- [ ] Verify critical endpoints still work
- [ ] Check error logs for new errors
- [ ] Run test suite
- [ ] Git commit with detailed message

---

## 🧪 VALIDATION PLAN

### Automated Tests
```bash
# 1. Syntax validation
find /home/master/applications/jcepnzzkmj/public_html/modules -name "*.php" \
  -exec php -l {} \; 2>&1 | grep -v "No syntax errors"

# 2. Check for remaining app.php (should be zero)
grep -r "require.*app\.php" /home/master/applications/jcepnzzkmj/public_html/modules \
  --exclude-dir=_archive --exclude-dir=_docs --exclude="*.md" | wc -l

# 3. Verify bootstrap usage
grep -r "require.*bootstrap\.php" /home/master/applications/jcepnzzkmj/public_html/modules \
  | grep -v ".md" | wc -l
```

### Manual Smoke Tests
- [ ] Open purchase order list page
- [ ] Create new purchase order
- [ ] Accept AI insight
- [ ] Check flagged products dashboard
- [ ] View payroll page
- [ ] Run admin-ui page

---

## 📈 EXPECTED OUTCOMES

**Before Sprint 2:**
- 78 files using `app.php` pattern
- Inconsistent bootstrap across modules
- Potential runtime errors

**After Sprint 2:**
- 0 files using `app.php` in active code
- Consistent bootstrap pattern
- All modules using local context
- Clean, maintainable codebase

---

## 🚨 ROLLBACK PLAN

If critical issues arise:

1. **Immediate rollback:**
```bash
BACKUP_DIR="/private_html/backups/sprint2_TIMESTAMP"
# Restore all files from backup
cp -r $BACKUP_DIR/* /home/master/applications/jcepnzzkmj/public_html/modules/
```

2. **Partial rollback** (single module):
```bash
# Example: rollback flagged_products only
cp $BACKUP_DIR/flagged_products_* /path/to/module/
```

3. **Git revert:**
```bash
git revert HEAD
```

---

## 📝 PROGRESS TRACKING

| Phase | Module | Files | Status | Time |
|-------|--------|-------|--------|------|
| 1 | flagged_products | 15 | 🟡 Pending | - |
| 2 | human_resources | 6 | 🟡 Pending | - |
| 3 | base | 1 | 🟡 Pending | - |
| 4 | staff-accounts | 2 | 🟡 Pending | - |
| 5 | bank-transactions | 2 | 🟡 Pending | - |
| 6 | admin-ui | 11 | 🟡 Pending | - |
| 7 | consignments | 15 | 🟡 Pending | - |

**Total:** 52 files to migrate

---

## 🎯 NEXT ACTIONS

1. **Execute sprint2-migrate-bootstrap.sh**
2. **Validate all changes**
3. **Update this document with results**
4. **Commit changes to git**
5. **Begin Sprint 3 (medium priority fixes)**

---

**Sprint Owner:** AI Development Agent
**Review Required:** Post-execution validation
**Deployment:** After validation + approval
