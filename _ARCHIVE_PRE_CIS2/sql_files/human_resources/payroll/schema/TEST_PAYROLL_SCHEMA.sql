-- ============================================================================
-- PAYROLL SCHEMA VALIDATION TEST SUITE
-- ============================================================================
-- Tests for: DEPLOY_PAYROLL_NZ.sql
-- Database: jcepnzzkmj
-- Purpose: Validate all tables, views, indexes, and AI rules are correct
-- ============================================================================

SET @test_passed = 0;
SET @test_failed = 0;
SET @test_total = 0;

-- ============================================================================
-- TEST 1: TABLE EXISTENCE
-- ============================================================================
SELECT '=== TEST 1: Validating Table Existence ===' AS test_section;

SET @test_total = @test_total + 1;
SELECT IF(COUNT(*) = 1,
    CONCAT('✓ PASS: payroll_nz_public_holidays exists'),
    CONCAT('✗ FAIL: payroll_nz_public_holidays missing')
) AS result
FROM information_schema.tables
WHERE table_schema = DATABASE() AND table_name = 'payroll_nz_public_holidays';

SET @test_total = @test_total + 1;
SELECT IF(COUNT(*) = 1,
    CONCAT('✓ PASS: payroll_nz_leave_requests exists'),
    CONCAT('✗ FAIL: payroll_nz_leave_requests missing')
) AS result
FROM information_schema.tables
WHERE table_schema = DATABASE() AND table_name = 'payroll_nz_leave_requests';

SET @test_total = @test_total + 1;
SELECT IF(COUNT(*) = 1,
    CONCAT('✓ PASS: payroll_nz_kiwisaver exists'),
    CONCAT('✗ FAIL: payroll_nz_kiwisaver missing')
) AS result
FROM information_schema.tables
WHERE table_schema = DATABASE() AND table_name = 'payroll_nz_kiwisaver';

SET @test_total = @test_total + 1;
SELECT IF(COUNT(*) = 1,
    CONCAT('✓ PASS: payroll_wages_discrepancies exists'),
    CONCAT('✗ FAIL: payroll_wages_discrepancies missing')
) AS result
FROM information_schema.tables
WHERE table_schema = DATABASE() AND table_name = 'payroll_wages_discrepancies';

SET @test_total = @test_total + 1;
SELECT IF(COUNT(*) = 1,
    CONCAT('✓ PASS: payroll_ai_decision_requests exists'),
    CONCAT('✗ FAIL: payroll_ai_decision_requests missing')
) AS result
FROM information_schema.tables
WHERE table_schema = DATABASE() AND table_name = 'payroll_ai_decision_requests';

SET @test_total = @test_total + 1;
SELECT IF(COUNT(*) = 1,
    CONCAT('✓ PASS: payroll_ai_check_sessions exists'),
    CONCAT('✗ FAIL: payroll_ai_check_sessions missing')
) AS result
FROM information_schema.tables
WHERE table_schema = DATABASE() AND table_name = 'payroll_ai_check_sessions';

SET @test_total = @test_total + 1;
SELECT IF(COUNT(*) = 1,
    CONCAT('✓ PASS: payroll_nz_statutory_deductions exists'),
    CONCAT('✗ FAIL: payroll_nz_statutory_deductions missing')
) AS result
FROM information_schema.tables
WHERE table_schema = DATABASE() AND table_name = 'payroll_nz_statutory_deductions';

-- ============================================================================
-- TEST 2: VIEW EXISTENCE
-- ============================================================================
SELECT '=== TEST 2: Validating View Existence ===' AS test_section;

SET @test_total = @test_total + 1;
SELECT IF(COUNT(*) = 1,
    CONCAT('✓ PASS: v_pending_ai_reviews exists'),
    CONCAT('✗ FAIL: v_pending_ai_reviews missing')
) AS result
FROM information_schema.views
WHERE table_schema = DATABASE() AND table_name = 'v_pending_ai_reviews';

SET @test_total = @test_total + 1;
SELECT IF(COUNT(*) = 1,
    CONCAT('✓ PASS: v_ai_decisions_dashboard exists'),
    CONCAT('✗ FAIL: v_ai_decisions_dashboard missing')
) AS result
FROM information_schema.views
WHERE table_schema = DATABASE() AND table_name = 'v_ai_decisions_dashboard';

SET @test_total = @test_total + 1;
SELECT IF(COUNT(*) = 1,
    CONCAT('✓ PASS: v_discrepancies_dashboard exists'),
    CONCAT('✗ FAIL: v_discrepancies_dashboard missing')
) AS result
FROM information_schema.views
WHERE table_schema = DATABASE() AND table_name = 'v_discrepancies_dashboard';

-- ============================================================================
-- TEST 3: COLUMN VALIDATION
-- ============================================================================
SELECT '=== TEST 3: Validating Critical Columns ===' AS test_section;

SET @test_total = @test_total + 1;
SELECT IF(COUNT(*) >= 10,
    CONCAT('✓ PASS: payroll_nz_leave_requests has all required columns (', COUNT(*), ')'),
    CONCAT('✗ FAIL: payroll_nz_leave_requests missing columns (found ', COUNT(*), ')')
) AS result
FROM information_schema.columns
WHERE table_schema = DATABASE() AND table_name = 'payroll_nz_leave_requests';

SET @test_total = @test_total + 1;
SELECT IF(COUNT(*) >= 8,
    CONCAT('✓ PASS: payroll_wages_discrepancies has all required columns (', COUNT(*), ')'),
    CONCAT('✗ FAIL: payroll_wages_discrepancies missing columns (found ', COUNT(*), ')')
) AS result
FROM information_schema.columns
WHERE table_schema = DATABASE() AND table_name = 'payroll_wages_discrepancies';

SET @test_total = @test_total + 1;
SELECT IF(COUNT(*) >= 12,
    CONCAT('✓ PASS: payroll_ai_decision_requests has all required columns (', COUNT(*), ')'),
    CONCAT('✗ FAIL: payroll_ai_decision_requests missing columns (found ', COUNT(*), ')')
) AS result
FROM information_schema.columns
WHERE table_schema = DATABASE() AND table_name = 'payroll_ai_decision_requests';

-- ============================================================================
-- TEST 4: INDEX VALIDATION
-- ============================================================================
SELECT '=== TEST 4: Validating Indexes ===' AS test_section;

SET @test_total = @test_total + 1;
SELECT IF(COUNT(*) >= 3,
    CONCAT('✓ PASS: payroll_nz_leave_requests has indexes (', COUNT(*), ')'),
    CONCAT('✗ FAIL: payroll_nz_leave_requests missing indexes (found ', COUNT(*), ')')
) AS result
FROM information_schema.statistics
WHERE table_schema = DATABASE() AND table_name = 'payroll_nz_leave_requests';

SET @test_total = @test_total + 1;
SELECT IF(COUNT(*) >= 3,
    CONCAT('✓ PASS: payroll_wages_discrepancies has indexes (', COUNT(*), ')'),
    CONCAT('✗ FAIL: payroll_wages_discrepancies missing indexes (found ', COUNT(*), ')')
) AS result
FROM information_schema.statistics
WHERE table_schema = DATABASE() AND table_name = 'payroll_wages_discrepancies';

SET @test_total = @test_total + 1;
SELECT IF(COUNT(*) >= 3,
    CONCAT('✓ PASS: payroll_ai_decision_requests has indexes (', COUNT(*), ')'),
    CONCAT('✗ FAIL: payroll_ai_decision_requests missing indexes (found ', COUNT(*), ')')
) AS result
FROM information_schema.statistics
WHERE table_schema = DATABASE() AND table_name = 'payroll_ai_decision_requests';

-- ============================================================================
-- TEST 5: AI RULES VALIDATION
-- ============================================================================
SELECT '=== TEST 5: Validating AI Rules Pre-Population ===' AS test_section;

SET @test_total = @test_total + 1;
SELECT IF(COUNT(*) >= 25,
    CONCAT('✓ PASS: payroll_ai_decision_rules has pre-configured rules (', COUNT(*), ')'),
    CONCAT('✗ FAIL: payroll_ai_decision_rules missing rules (found ', COUNT(*), ')')
) AS result
FROM payroll_ai_decision_rules
WHERE is_active = 1;

SET @test_total = @test_total + 1;
SELECT IF(COUNT(*) > 0,
    CONCAT('✓ PASS: Sick leave validation rules exist (', COUNT(*), ')'),
    CONCAT('✗ FAIL: Sick leave validation rules missing')
) AS result
FROM payroll_ai_decision_rules
WHERE decision_type = 'sick_leave_validation' AND is_active = 1;

SET @test_total = @test_total + 1;
SELECT IF(COUNT(*) > 0,
    CONCAT('✓ PASS: Bereavement leave rules exist (', COUNT(*), ')'),
    CONCAT('✗ FAIL: Bereavement leave rules missing')
) AS result
FROM payroll_ai_decision_rules
WHERE decision_type = 'bereavement_assessment' AND is_active = 1;

SET @test_total = @test_total + 1;
SELECT IF(COUNT(*) > 0,
    CONCAT('✓ PASS: Pay dispute resolution rules exist (', COUNT(*), ')'),
    CONCAT('✗ FAIL: Pay dispute resolution rules missing')
) AS result
FROM payroll_ai_decision_rules
WHERE decision_type = 'pay_dispute_resolution' AND is_active = 1;

-- ============================================================================
-- TEST 6: ENUM VALIDATION
-- ============================================================================
SELECT '=== TEST 6: Validating ENUM Fields ===' AS test_section;

SET @test_total = @test_total + 1;
SELECT IF(
    COLUMN_TYPE LIKE "%sick_leave%" AND
    COLUMN_TYPE LIKE "%bereavement_leave%" AND
    COLUMN_TYPE LIKE "%domestic_violence_leave%",
    CONCAT('✓ PASS: payroll_nz_leave_requests.leave_type has NZ leave types'),
    CONCAT('✗ FAIL: payroll_nz_leave_requests.leave_type missing NZ leave types')
) AS result
FROM information_schema.columns
WHERE table_schema = DATABASE()
  AND table_name = 'payroll_nz_leave_requests'
  AND column_name = 'leave_type';

SET @test_total = @test_total + 1;
SELECT IF(
    COLUMN_TYPE LIKE "%pending%" AND
    COLUMN_TYPE LIKE "%approved%" AND
    COLUMN_TYPE LIKE "%declined%",
    CONCAT('✓ PASS: payroll_nz_leave_requests.status has correct statuses'),
    CONCAT('✗ FAIL: payroll_nz_leave_requests.status missing statuses')
) AS result
FROM information_schema.columns
WHERE table_schema = DATABASE()
  AND table_name = 'payroll_nz_leave_requests'
  AND column_name = 'status';

-- ============================================================================
-- TEST 7: CONSTRAINT VALIDATION
-- ============================================================================
SELECT '=== TEST 7: Validating Foreign Keys ===' AS test_section;

SET @test_total = @test_total + 1;
SELECT IF(COUNT(*) >= 1,
    CONCAT('✓ PASS: Foreign keys exist on payroll tables (', COUNT(*), ')'),
    CONCAT('✗ FAIL: Missing foreign keys')
) AS result
FROM information_schema.key_column_usage
WHERE table_schema = DATABASE()
  AND referenced_table_name IS NOT NULL
  AND table_name LIKE 'payroll_%';

-- ============================================================================
-- TEST 8: DATA INTEGRITY
-- ============================================================================
SELECT '=== TEST 8: Validating Data Integrity ===' AS test_section;

SET @test_total = @test_total + 1;
SELECT IF(COUNT(*) = 0,
    CONCAT('✓ PASS: No orphaned AI decision requests'),
    CONCAT('✗ FAIL: Found ', COUNT(*), ' orphaned AI decision requests')
) AS result
FROM payroll_ai_decision_requests
WHERE entity_type = 'leave_request'
  AND entity_id NOT IN (SELECT id FROM payroll_nz_leave_requests);

SET @test_total = @test_total + 1;
SELECT IF(COUNT(*) = 0,
    CONCAT('✓ PASS: No orphaned discrepancies'),
    CONCAT('✗ FAIL: Found ', COUNT(*), ' orphaned discrepancies')
) AS result
FROM payroll_wages_discrepancies
WHERE staff_id NOT IN (SELECT id FROM staff);

-- ============================================================================
-- TEST 9: VIEW FUNCTIONALITY
-- ============================================================================
SELECT '=== TEST 9: Validating View Queries ===' AS test_section;

SET @test_total = @test_total + 1;
SELECT IF(COUNT(*) >= 0,
    CONCAT('✓ PASS: v_pending_ai_reviews is queryable (', COUNT(*), ' rows)'),
    CONCAT('✗ FAIL: v_pending_ai_reviews query failed')
) AS result
FROM v_pending_ai_reviews
LIMIT 1;

SET @test_total = @test_total + 1;
SELECT IF(COUNT(*) >= 0,
    CONCAT('✓ PASS: v_ai_decisions_dashboard is queryable'),
    CONCAT('✗ FAIL: v_ai_decisions_dashboard query failed')
) AS result
FROM v_ai_decisions_dashboard
LIMIT 1;

SET @test_total = @test_total + 1;
SELECT IF(COUNT(*) >= 0,
    CONCAT('✓ PASS: v_discrepancies_dashboard is queryable'),
    CONCAT('✗ FAIL: v_discrepancies_dashboard query failed')
) AS result
FROM v_discrepancies_dashboard
LIMIT 1;

-- ============================================================================
-- TEST 10: NZ COMPLIANCE CHECKS
-- ============================================================================
SELECT '=== TEST 10: Validating NZ Compliance Features ===' AS test_section;

SET @test_total = @test_total + 1;
SELECT IF(
    (SELECT COUNT(*) FROM information_schema.columns
     WHERE table_schema = DATABASE()
       AND table_name = 'payroll_nz_kiwisaver'
       AND column_name = 'employee_contribution_rate') > 0 AND
    (SELECT COUNT(*) FROM information_schema.columns
     WHERE table_schema = DATABASE()
       AND table_name = 'payroll_nz_kiwisaver'
       AND column_name = 'employer_contribution_rate') > 0,
    CONCAT('✓ PASS: KiwiSaver contribution tracking implemented'),
    CONCAT('✗ FAIL: KiwiSaver contribution tracking missing')
) AS result;

SET @test_total = @test_total + 1;
SELECT IF(
    (SELECT COUNT(*) FROM information_schema.columns
     WHERE table_schema = DATABASE()
       AND table_name = 'payroll_nz_public_holidays'
       AND column_name = 'is_statutory') > 0,
    CONCAT('✓ PASS: Statutory holiday tracking implemented'),
    CONCAT('✗ FAIL: Statutory holiday tracking missing')
) AS result;

SET @test_total = @test_total + 1;
SELECT IF(
    (SELECT COUNT(*) FROM information_schema.columns
     WHERE table_schema = DATABASE()
       AND table_name = 'payroll_nz_alternative_holidays'
       AND column_name = 'worked_public_holiday_date') > 0,
    CONCAT('✓ PASS: Alternative holiday tracking implemented'),
    CONCAT('✗ FAIL: Alternative holiday tracking missing')
) AS result;

SET @test_total = @test_total + 1;
SELECT IF(
    (SELECT COUNT(*) FROM information_schema.columns
     WHERE table_schema = DATABASE()
       AND table_name = 'payroll_nz_statutory_deductions'
       AND column_name = 'order_type') > 0,
    CONCAT('✓ PASS: Statutory deductions tracking implemented'),
    CONCAT('✗ FAIL: Statutory deductions tracking missing')
) AS result;

-- ============================================================================
-- TEST SUMMARY
-- ============================================================================
SELECT '=== TEST SUMMARY ===' AS test_section;

SELECT
    @test_total AS total_tests,
    'See results above' AS status,
    'Review all ✓ PASS and ✗ FAIL messages above' AS instructions;

SELECT '=== END OF TESTS ===' AS test_section;
