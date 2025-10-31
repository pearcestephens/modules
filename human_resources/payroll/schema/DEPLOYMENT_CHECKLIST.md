# Payroll AI Automation Schema - Deployment Checklist

**Schema File:** `payroll_ai_automation_schema.sql`
**Database:** MariaDB 10.5+
**Target Server:** Production CIS Database
**Date:** 2025-01-27

---

## ✅ PRE-DEPLOYMENT CHECKS

### 1. Database Compatibility
- [ ] Verify MariaDB version is 10.5 or higher
  ```sql
  SELECT VERSION();
  -- Should return 10.5.x or higher
  ```

- [ ] Check JSON column support
  ```sql
  SELECT JSON_VALID('{"test": true}');
  -- Should return 1
  ```

- [ ] Verify UTF8MB4 support
  ```sql
  SHOW CHARACTER SET LIKE 'utf8mb4';
  -- Should show utf8mb4
  ```

### 2. Database Permissions
- [ ] Verify user has CREATE TABLE permissions
  ```sql
  SHOW GRANTS;
  -- Should include CREATE, ALTER, INDEX, REFERENCES
  ```

- [ ] Verify user can create foreign keys
- [ ] Verify user can create views

### 3. Existing Tables Check
- [ ] Check if any old table names exist
  ```sql
  SHOW TABLES LIKE '%timesheet_amendments%';
  SHOW TABLES LIKE '%payrun_%';
  SHOW TABLES LIKE '%vend_payment%';
  SHOW TABLES LIKE '%bank_payment%';
  ```

- [ ] If old tables exist, decide on migration strategy:
  - [ ] Option A: Drop old tables (if empty/test data)
  - [ ] Option B: Rename old tables (if production data exists)
  - [ ] Option C: Migrate data from old to new tables

### 4. Disk Space Check
- [ ] Verify sufficient disk space (estimate: ~50MB for empty schema)
  ```sql
  SELECT table_schema AS 'Database',
         ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS 'Size (MB)'
  FROM information_schema.TABLES
  GROUP BY table_schema;
  ```

### 5. Backup Strategy
- [ ] Create full database backup before deployment
  ```bash
  mysqldump -u [user] -p [database] > backup_pre_ai_schema_$(date +%Y%m%d_%H%M%S).sql
  ```

- [ ] Verify backup file size and integrity
  ```bash
  ls -lh backup_pre_ai_schema_*.sql
  head -n 50 backup_pre_ai_schema_*.sql  # Check header
  tail -n 20 backup_pre_ai_schema_*.sql  # Check footer
  ```

---

## 🚀 DEPLOYMENT STEPS

### Step 1: Deploy Schema (Dry Run)
```bash
# Test syntax without executing
mysql -u [user] -p [database] --show-warnings < payroll_ai_automation_schema.sql --execute="SELECT 'Syntax OK';"
```

### Step 2: Deploy Schema (Production)
```bash
# Deploy with transaction safety
mysql -u [user] -p [database] < payroll_ai_automation_schema.sql
```

### Step 3: Verify Table Creation
```sql
-- Check all 16 new tables exist
SELECT table_name, engine, table_rows,
       ROUND(data_length / 1024 / 1024, 2) AS data_mb,
       ROUND(index_length / 1024 / 1024, 2) AS index_mb
FROM information_schema.TABLES
WHERE table_schema = '[database]'
  AND table_name LIKE 'payroll_%'
ORDER BY table_name;

-- Should show 26 total tables (16 new + 10 existing)
```

### Step 4: Verify Foreign Keys
```sql
-- Check all foreign key constraints
SELECT
  constraint_name,
  table_name,
  referenced_table_name
FROM information_schema.KEY_COLUMN_USAGE
WHERE table_schema = '[database]'
  AND referenced_table_name IS NOT NULL
  AND table_name LIKE 'payroll_%'
ORDER BY table_name;

-- Should show 6 foreign key constraints
```

### Step 5: Verify Views
```sql
-- Check views created successfully
SHOW FULL TABLES WHERE table_type = 'VIEW' AND Tables_in_[database] LIKE '%payroll%';

-- Should show:
-- v_pending_ai_reviews
-- v_payroll_automation_dashboard
```

### Step 6: Verify Indexes
```sql
-- Check indexes on critical tables
SHOW INDEX FROM payroll_timesheet_amendments;
SHOW INDEX FROM payroll_payrun_line_adjustments;
SHOW INDEX FROM payroll_vend_payment_requests;
SHOW INDEX FROM payroll_bank_payment_batches;
SHOW INDEX FROM payroll_ai_decisions;
```

### Step 7: Test Views
```sql
-- Test v_pending_ai_reviews (should return empty result set)
SELECT * FROM v_pending_ai_reviews LIMIT 5;

-- Test v_payroll_automation_dashboard (should return empty result set)
SELECT * FROM v_payroll_automation_dashboard LIMIT 5;
```

### Step 8: Verify Default AI Rules
```sql
-- Check default AI rules were inserted
SELECT rule_name, rule_type, is_active, priority
FROM payroll_ai_rules
ORDER BY priority DESC;

-- Should show 9 default rules:
-- 1. Small Time Adjustment Auto-Approve
-- 2. Break Time Adjustment
-- 3. Large Time Amendment Escalate
-- 4. Small Amount Adjustment
-- 5. Large Pay Adjustment Require Review
-- 6. Standard Vend Payment Auto-Approve
-- 7. Bank Payment Require Approval
-- 8. Duplicate Amendment Detection
-- 9. Unusual Pattern Detection
```

---

## 🧪 POST-DEPLOYMENT TESTING

### Test 1: Insert Test Timesheet Amendment
```sql
-- Insert test amendment
INSERT INTO payroll_timesheet_amendments
  (staff_id, deputy_timesheet_id, original_start_time, original_end_time,
   amended_start_time, amended_end_time, reason, status, created_by_ip)
VALUES
  (1, 'TEST001', '2025-01-27 09:00:00', '2025-01-27 17:00:00',
   '2025-01-27 09:15:00', '2025-01-27 17:00:00', 'Test amendment - forgot to clock in',
   0, '127.0.0.1');

-- Verify insert
SELECT * FROM payroll_timesheet_amendments WHERE deputy_timesheet_id = 'TEST001';

-- Check history table (should be empty for now)
SELECT * FROM payroll_timesheet_amendment_history WHERE amendment_id = LAST_INSERT_ID();

-- Cleanup test data
DELETE FROM payroll_timesheet_amendments WHERE deputy_timesheet_id = 'TEST001';
```

### Test 2: Insert Test AI Decision
```sql
-- Insert test AI decision
INSERT INTO payroll_ai_decisions
  (decision_type, entity_type, entity_id, model_name, model_version,
   decision, confidence_score, reasoning)
VALUES
  ('timesheet_amendment', 'payroll_timesheet_amendments', 999,
   'GPT-4', '2024-01', 'approve', 0.9500,
   'Test AI decision - high confidence approval for minor time adjustment');

-- Verify insert
SELECT * FROM payroll_ai_decisions WHERE entity_id = 999;

-- Cleanup test data
DELETE FROM payroll_ai_decisions WHERE entity_id = 999 AND entity_type = 'payroll_timesheet_amendments';
```

### Test 3: Test View Performance
```sql
-- Test v_pending_ai_reviews view
EXPLAIN SELECT * FROM v_pending_ai_reviews;

-- Should show index usage on status columns
```

### Test 4: Test Foreign Key Constraints
```sql
-- Test cascade delete
START TRANSACTION;

-- Insert parent
INSERT INTO payroll_timesheet_amendments
  (staff_id, deputy_timesheet_id, reason, status)
VALUES
  (1, 'FK_TEST', 'Foreign key test', 0);

SET @test_id = LAST_INSERT_ID();

-- Insert child
INSERT INTO payroll_timesheet_amendment_history
  (amendment_id, action, actor_type)
VALUES
  (@test_id, 'created', 'staff');

-- Verify child exists
SELECT COUNT(*) FROM payroll_timesheet_amendment_history WHERE amendment_id = @test_id;
-- Should return 1

-- Delete parent (should cascade to child)
DELETE FROM payroll_timesheet_amendments WHERE id = @test_id;

-- Verify child was deleted
SELECT COUNT(*) FROM payroll_timesheet_amendment_history WHERE amendment_id = @test_id;
-- Should return 0

ROLLBACK;  -- Rollback test transaction
```

---

## 🔍 MONITORING & VALIDATION

### Check Table Sizes
```sql
SELECT
  table_name,
  table_rows,
  ROUND(data_length / 1024 / 1024, 2) AS data_mb,
  ROUND(index_length / 1024 / 1024, 2) AS index_mb,
  ROUND((data_length + index_length) / 1024 / 1024, 2) AS total_mb
FROM information_schema.TABLES
WHERE table_schema = '[database]'
  AND table_name LIKE 'payroll_%'
ORDER BY (data_length + index_length) DESC;
```

### Check Index Usage
```sql
-- After some usage, check index statistics
SELECT
  table_name,
  index_name,
  cardinality,
  nullable
FROM information_schema.STATISTICS
WHERE table_schema = '[database]'
  AND table_name LIKE 'payroll_%'
ORDER BY table_name, seq_in_index;
```

### Monitor Query Performance
```sql
-- Enable slow query log
SET GLOBAL slow_query_log = 'ON';
SET GLOBAL long_query_time = 0.5;  -- Log queries slower than 0.5 seconds

-- Monitor for slow queries on new tables
-- Check slow query log after some usage
```

---

## 🐛 TROUBLESHOOTING

### Issue: Foreign Key Constraint Fails
**Symptom:** Error 1215 (HY000): Cannot add foreign key constraint

**Solution:**
```sql
-- Check parent table exists
SHOW TABLES LIKE 'payroll_timesheet_amendments';

-- Check column types match exactly
DESCRIBE payroll_timesheet_amendments;
DESCRIBE payroll_timesheet_amendment_history;

-- Verify InnoDB engine
SELECT table_name, engine
FROM information_schema.TABLES
WHERE table_name IN ('payroll_timesheet_amendments', 'payroll_timesheet_amendment_history');
```

### Issue: View Creation Fails
**Symptom:** Error 1356 (HY000): View references invalid table

**Solution:**
```sql
-- Check if all referenced tables exist
SHOW TABLES LIKE 'payroll_timesheet_amendments';
SHOW TABLES LIKE 'payroll_payrun_line_adjustments';

-- Drop and recreate view
DROP VIEW IF EXISTS v_pending_ai_reviews;
-- Then recreate from schema file
```

### Issue: JSON Column Not Supported
**Symptom:** Error 1064 (42000): You have an error in your SQL syntax near 'JSON'

**Solution:**
```sql
-- Check MariaDB version
SELECT VERSION();

-- If < 10.2, upgrade MariaDB or use TEXT columns instead
-- (Though schema requires 10.5+)
```

### Issue: Duplicate Key on AI Rules Insert
**Symptom:** Error 1062 (23000): Duplicate entry for key 'rule_name'

**Solution:**
```sql
-- Check if rules already exist
SELECT rule_name FROM payroll_ai_rules;

-- Delete old rules if safe to do so
DELETE FROM payroll_ai_rules WHERE rule_name LIKE '%Test%';

-- Or update INSERT to INSERT IGNORE or ON DUPLICATE KEY UPDATE
```

---

## 📊 SUCCESS CRITERIA

Deployment is successful when:

- [ ] All 16 new tables created successfully
- [ ] All 6 foreign key constraints active
- [ ] All 2 views created and queryable
- [ ] 9 default AI rules inserted
- [ ] Test inserts work on all main tables
- [ ] Foreign key cascade deletes work
- [ ] Views return correct empty result sets
- [ ] No errors in database error log
- [ ] Backup file created and verified
- [ ] Documentation updated with deployment date

---

## 📝 ROLLBACK PROCEDURE

If deployment fails or issues are detected:

### Option 1: Restore from Backup
```bash
# Stop application (if needed)
mysql -u [user] -p [database] < backup_pre_ai_schema_YYYYMMDD_HHMMSS.sql
```

### Option 2: Drop New Tables Only
```sql
-- Drop new tables in reverse order (respecting foreign keys)
DROP VIEW IF EXISTS v_payroll_automation_dashboard;
DROP VIEW IF EXISTS v_pending_ai_reviews;

DROP TABLE IF EXISTS payroll_ai_rule_executions;
DROP TABLE IF EXISTS payroll_ai_rules;
DROP TABLE IF EXISTS payroll_process_metrics;
DROP TABLE IF EXISTS payroll_notifications;
DROP TABLE IF EXISTS payroll_activity_log;
DROP TABLE IF EXISTS payroll_context_snapshots;
DROP TABLE IF EXISTS payroll_ai_feedback;
DROP TABLE IF EXISTS payroll_ai_decisions;
DROP TABLE IF EXISTS payroll_bank_payments;
DROP TABLE IF EXISTS payroll_bank_payment_batches;
DROP TABLE IF EXISTS payroll_vend_payment_allocations;
DROP TABLE IF EXISTS payroll_vend_payment_requests;
DROP TABLE IF EXISTS payroll_payrun_adjustment_history;
DROP TABLE IF EXISTS payroll_payrun_line_adjustments;
DROP TABLE IF EXISTS payroll_timesheet_amendment_history;
DROP TABLE IF EXISTS payroll_timesheet_amendments;
```

---

## ✅ DEPLOYMENT SIGN-OFF

- **Deployed By:** ___________________________
- **Date/Time:** ___________________________
- **Environment:** ☐ Development  ☐ Staging  ☐ Production
- **Backup Taken:** ☐ Yes  ☐ No
- **Tests Passed:** ☐ Yes  ☐ No
- **Issues Found:** ___________________________
- **Rollback Required:** ☐ Yes  ☐ No

---

**Notes:**
- Keep this checklist with deployment documentation
- Update CIS system documentation after successful deployment
- Notify development team of new tables available
- Schedule follow-up review after 7 days of production use
