-- ============================================================================
-- PAYROLL AI SYSTEM - MISSING COMPONENTS DEPLOYMENT
-- ============================================================================
-- Deploys only missing tables and AI rules
-- Database: jcepnzzkmj
-- MariaDB 10.5+
-- ============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================================
-- CHECK: Verify which tables/rules are missing (informational)
-- ============================================================================
SELECT '=== CHECKING MISSING COMPONENTS ===' AS status;

SELECT IF(COUNT(*) = 0,
    'MISSING: payroll_nz_statutory_deductions',
    'EXISTS: payroll_nz_statutory_deductions'
) AS check_1
FROM information_schema.tables
WHERE table_schema = DATABASE() AND table_name = 'payroll_nz_statutory_deductions';

SELECT IF(COUNT(*) = 0,
    'MISSING: payroll_ai_decision_rules',
    'EXISTS: payroll_ai_decision_rules'
) AS check_2
FROM information_schema.tables
WHERE table_schema = DATABASE() AND table_name = 'payroll_ai_decision_rules';

SELECT IF(COUNT(*) = 0,
    'MISSING: v_pending_ai_reviews view',
    'EXISTS: v_pending_ai_reviews view'
) AS check_3
FROM information_schema.views
WHERE table_schema = DATABASE() AND table_name = 'v_pending_ai_reviews';

-- ============================================================================
-- CREATE: payroll_nz_statutory_deductions (if missing)
-- ============================================================================
CREATE TABLE IF NOT EXISTS `payroll_nz_statutory_deductions` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `staff_id` INT UNSIGNED NOT NULL,
  `order_type` ENUM('court_order', 'child_support', 'student_loan', 'ird_debt', 'other') NOT NULL,
  `order_reference` VARCHAR(100) NOT NULL COMMENT 'Court order number, IRD ref, etc.',
  `order_date` DATE NULL,
  `effective_from` DATE NOT NULL,
  `effective_to` DATE NULL,

  -- Deduction calculation
  `calculation_method` ENUM('fixed_amount', 'percentage', 'formula') NOT NULL DEFAULT 'fixed_amount',
  `amount` DECIMAL(10,2) NULL COMMENT 'Fixed deduction amount',
  `percentage` DECIMAL(5,2) NULL COMMENT 'Percentage of gross',
  `formula` VARCHAR(500) NULL COMMENT 'Custom formula for calculation',

  -- Protection thresholds (NZ compliance)
  `min_net_protected` DECIMAL(10,2) DEFAULT 160.00 COMMENT 'Minimum protected net (approx 1/3 minimum wage)',

  -- Status tracking
  `status` ENUM('active', 'suspended', 'discharged', 'expired') NOT NULL DEFAULT 'active',
  `reason_for_status` VARCHAR(500) NULL,
  `status_changed_at` TIMESTAMP NULL,
  `status_changed_by_staff_id` INT UNSIGNED NULL,

  -- Government document
  `document_filename` VARCHAR(255) NULL COMMENT 'Stored copy of court order or govt letter',
  `document_parsed_at` TIMESTAMP NULL,
  `document_parsed_by_staff_id` INT UNSIGNED NULL,

  -- Audit
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by_staff_id` INT UNSIGNED NULL,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by_staff_id` INT UNSIGNED NULL,

  -- AI Processing
  `ai_processed` TINYINT(1) DEFAULT 0,
  `ai_processed_at` TIMESTAMP NULL,
  `ai_verified_compliance` TINYINT(1) DEFAULT 0 COMMENT 'AI confirmed NZ law compliance',

  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_order_reference` (`order_reference`),
  KEY `idx_staff_id` (`staff_id`),
  KEY `idx_status` (`status`),
  KEY `idx_effective_dates` (`effective_from`, `effective_to`),
  KEY `idx_order_type` (`order_type`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- CREATE: payroll_ai_decision_rules (if missing)
-- ============================================================================
CREATE TABLE IF NOT EXISTS `payroll_ai_decision_rules` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `decision_type` VARCHAR(100) NOT NULL COMMENT 'leave_request_assessment, pay_dispute_resolution, etc.',
  `rule_name` VARCHAR(255) NOT NULL,
  `rule_description` TEXT NULL,
  `rule_category` ENUM('validation', 'calculation', 'compliance_check', 'escalation') NOT NULL DEFAULT 'validation',

  -- Rule logic
  `condition_json` LONGTEXT NOT NULL COMMENT 'JSON: {"field": "reason", "operator": "contains", "value": "flu"}',
  `action_json` LONGTEXT NOT NULL COMMENT 'JSON: {"decision": "approve", "confidence_boost": 0.1}',
  `priority` INT DEFAULT 100,

  -- NZ Law Reference
  `nz_law_reference` VARCHAR(500) NULL COMMENT 'e.g., "Holidays Act 2003 s.15"',
  `guidance_url` VARCHAR(500) NULL,

  -- Thresholds
  `min_confidence_to_apply` DECIMAL(3,2) DEFAULT 0.50,
  `auto_apply_if_confidence_above` DECIMAL(3,2) DEFAULT 0.85,
  `requires_human_review_if_below` DECIMAL(3,2) DEFAULT 0.70,

  -- Status
  `is_active` TINYINT(1) DEFAULT 1,
  `activation_date` DATE NULL,
  `deactivation_date` DATE NULL,
  `deactivation_reason` VARCHAR(500) NULL,

  -- Audit
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by_staff_id` INT UNSIGNED NULL,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by_staff_id` INT UNSIGNED NULL,

  PRIMARY KEY (`id`),
  KEY `idx_decision_type` (`decision_type`),
  KEY `idx_is_active` (`is_active`),
  KEY `idx_priority` (`priority`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- INSERT: 27 Pre-Configured AI Decision Rules
-- ============================================================================

-- SICK LEAVE VALIDATION RULES (6 rules)
INSERT INTO `payroll_ai_decision_rules` (decision_type, rule_name, rule_category, condition_json, action_json, priority, nz_law_reference, is_active, min_confidence_to_apply, auto_apply_if_confidence_above, requires_human_review_if_below) VALUES
('sick_leave_validation', 'Sick Leave - Single Day Without Cert', 'validation', '{"duration_days": 1, "has_medical_certificate": false}', '{"decision": "approve", "confidence_boost": 0.2}', 100, 'Holidays Act 2003', 1, 0.60, 0.90, 0.75),
('sick_leave_validation', 'Sick Leave - 2-3 Days Without Cert', 'validation', '{"duration_days": {"min": 2, "max": 3}, "has_medical_certificate": false}', '{"decision": "request_evidence", "confidence_boost": 0}', 90, 'Holidays Act 2003 s.14', 1, 0.50, 0.85, 0.70),
('sick_leave_validation', 'Sick Leave - 4+ Days Requires Certificate', 'compliance_check', '{"duration_days": {"min": 4}, "has_medical_certificate": false}', '{"decision": "decline", "confidence_boost": 0}', 80, 'Holidays Act 2003 s.14(1)', 1, 0.70, 0.90, 0.80),
('sick_leave_validation', 'Sick Leave - Frequent Pattern Detection', 'escalation', '{"frequency_last_90_days": {"min": 10}, "pattern_risk": "high"}', '{"decision": "escalate", "confidence_boost": 0}', 70, 'Employment Relations Act 2000', 1, 0.60, 0.85, 0.70),
('sick_leave_validation', 'Sick Leave - Medical Certificate Provided', 'validation', '{"has_medical_certificate": true, "certificate_valid": true}', '{"decision": "approve", "confidence_boost": 0.3}', 110, 'Holidays Act 2003 s.14', 1, 0.80, 0.95, 0.85),
('sick_leave_validation', 'Sick Leave - Partial Day Request', 'validation', '{"is_partial_day": true, "hours_requested": {"min": 0, "max": 4}}', '{"decision": "approve", "confidence_boost": 0.15}', 95, 'Holidays Act 2003', 1, 0.70, 0.90, 0.75);

-- BEREAVEMENT LEAVE RULES (4 rules)
INSERT INTO `payroll_ai_decision_rules` (decision_type, rule_name, rule_category, condition_json, action_json, priority, nz_law_reference, is_active, min_confidence_to_apply, auto_apply_if_confidence_above, requires_human_review_if_below) VALUES
('bereavement_assessment', 'Bereavement - Immediate Family (3 days)', 'compliance_check', '{"relationship": ["spouse", "child", "parent", "sibling"]}', '{"decision": "approve", "recommended_days": 3, "confidence_boost": 0.3}', 110, 'Holidays Act 2003 s.14A', 1, 0.80, 0.95, 0.85),
('bereavement_assessment', 'Bereavement - Extended Family (1 day)', 'validation', '{"relationship": ["grandparent", "grandchild", "in-law", "uncle", "aunt", "cousin"]}', '{"decision": "approve", "recommended_days": 1, "confidence_boost": 0.2}', 100, 'Holidays Act 2003 s.14A', 1, 0.75, 0.90, 0.80),
('bereavement_assessment', 'Bereavement - Frequent Claims Detection', 'escalation', '{"bereavement_claims_last_24_months": {"min": 3}}', '{"decision": "escalate", "confidence_boost": 0}', 70, 'Employment Relations Act 2000', 1, 0.60, 0.85, 0.70),
('bereavement_assessment', 'Bereavement - International Travel Required', 'escalation', '{"international_travel_required": true}', '{"decision": "escalate", "additional_days_possible": "case_dependent", "confidence_boost": 0}', 75, 'Holidays Act 2003 s.14A', 1, 0.65, 0.80, 0.70);

-- DOMESTIC VIOLENCE LEAVE RULES (3 rules)
INSERT INTO `payroll_ai_decision_rules` (decision_type, rule_name, rule_category, condition_json, action_json, priority, nz_law_reference, is_active, min_confidence_to_apply, auto_apply_if_confidence_above, requires_human_review_if_below) VALUES
('domestic_violence_leave', 'Domestic Violence - Valid Claim (10 days/year)', 'compliance_check', '{"dv_claim_valid": true, "dv_protective_order": true}', '{"decision": "approve", "recommended_days": 10, "confidence_boost": 0.3}', 110, 'Domestic Violence - Victims'' Protection Act 2018', 1, 0.85, 0.95, 0.90),
('domestic_violence_leave', 'Domestic Violence - Unverified Claim', 'escalation', '{"dv_claim_valid": false, "evidence_provided": false}', '{"decision": "escalate", "requires_hr_review": true, "confidence_boost": 0}', 70, 'Domestic Violence - Victims'' Protection Act 2018', 1, 0.60, 0.80, 0.70),
('domestic_violence_leave', 'Domestic Violence - Emergency Leave (immediate)', 'compliance_check', '{"dv_emergency": true, "urgent": true}', '{"decision": "approve", "immediate": true, "confidence_boost": 0.4}', 120, 'Domestic Violence - Victims'' Protection Act 2018', 1, 0.80, 0.95, 0.85);

-- PAY DISPUTE RESOLUTION RULES (5 rules)
INSERT INTO `payroll_ai_decision_rules` (decision_type, rule_name, rule_category, condition_json, action_json, priority, nz_law_reference, is_active, min_confidence_to_apply, auto_apply_if_confidence_above, requires_human_review_if_below) VALUES
('pay_dispute_resolution', 'Pay Dispute - Timesheet Amendment Approved', 'validation', '{"timesheet_amended": true, "amendment_approved": true, "amendment_reason_valid": true}', '{"decision": "approve", "action": "correct_pay", "confidence_boost": 0.25}', 105, 'Employment Standards Act 2015', 1, 0.75, 0.90, 0.80),
('pay_dispute_resolution', 'Pay Dispute - Overtime Calculation Error', 'calculation', '{"discrepancy_type": "overtime_calculation", "error_confirmed": true}', '{"decision": "approve", "action": "recalculate_ot", "confidence_boost": 0.2}', 100, 'Employment Standards Act 2015', 1, 0.80, 0.95, 0.85),
('pay_dispute_resolution', 'Pay Dispute - Public Holiday Work Entitlement', 'compliance_check', '{"worked_public_holiday": true, "entitled_premium": true}', '{"decision": "approve", "action": "apply_holiday_premium", "confidence_boost": 0.3}', 110, 'Holidays Act 2003 s.19', 1, 0.85, 0.95, 0.90),
('pay_dispute_resolution', 'Pay Dispute - Unidentified Discrepancy', 'escalation', '{"discrepancy_reason_unknown": true, "pattern_unusual": true}', '{"decision": "escalate", "requires_investigation": true, "confidence_boost": 0}', 65, 'Employment Standards Act 2015', 1, 0.50, 0.75, 0.60),
('pay_dispute_resolution', 'Pay Dispute - Duplicate Payment Detection', 'compliance_check', '{"duplicate_payment_detected": true, "confirmed": true}', '{"decision": "decline", "action": "flag_for_reversal", "confidence_boost": 0.2}', 115, 'Employment Standards Act 2015', 1, 0.90, 0.98, 0.95);

-- STATUTORY DEDUCTION RULES (5 rules)
INSERT INTO `payroll_ai_decision_rules` (decision_type, rule_name, rule_category, condition_json, action_json, priority, nz_law_reference, is_active, min_confidence_to_apply, auto_apply_if_confidence_above, requires_human_review_if_below) VALUES
('statutory_deduction_assessment', 'Statutory Deduction - Valid Court Order', 'compliance_check', '{"order_type": "court_order", "order_valid": true, "court_verified": true}', '{"decision": "approve", "apply_immediately": true, "confidence_boost": 0.3}', 110, 'District Courts Act 1947, Crimes Victims' Rights Act 2015', 1, 0.85, 0.98, 0.90),
('statutory_deduction_assessment', 'Statutory Deduction - Child Support Order', 'compliance_check', '{"order_type": "child_support", "csi_verified": true}', '{"decision": "approve", "apply_immediately": true, "priority": "high", "confidence_boost": 0.3}', 115, 'Child Support Act 1991', 1, 0.90, 0.99, 0.95),
('statutory_deduction_assessment', 'Statutory Deduction - IRD Debt', 'compliance_check', '{"order_type": "ird_debt", "ird_verified": true, "legal_authority": true}', '{"decision": "approve", "apply_immediately": true, "priority": "critical", "confidence_boost": 0.3}', 120, 'Tax Administration Act 1994', 1, 0.90, 0.99, 0.95),
('statutory_deduction_assessment', 'Statutory Deduction - Student Loan', 'compliance_check', '{"order_type": "student_loan", "nzsl_verified": true}', '{"decision": "approve", "apply_automatically": true, "confidence_boost": 0.25}', 105, 'Student Loan Scheme Act 2011', 1, 0.85, 0.95, 0.90),
('statutory_deduction_assessment', 'Statutory Deduction - Exceeds Net Protection Threshold', 'validation', '{"total_deductions_percentage": {"min": 25}, "net_pay_below_protected": true}', '{"decision": "decline", "action": "cap_at_protected_minimum", "confidence_boost": 0.2}', 95, 'Employment Standards Act 2015 s.4', 1, 0.80, 0.95, 0.85);

-- ALTERNATIVE LEAVE/HOLIDAY RULES (4 rules)
INSERT INTO `payroll_ai_decision_rules` (decision_type, rule_name, rule_category, condition_json, action_json, priority, nz_law_reference, is_active, min_confidence_to_apply, auto_apply_if_confidence_above, requires_human_review_if_below) VALUES
('alternative_holiday_assessment', 'Alternative Holiday - Worked Public Holiday', 'compliance_check', '{"worked_public_holiday": true, "not_normally_working": false}', '{"decision": "approve", "action": "grant_alt_holiday", "confidence_boost": 0.3}', 110, 'Holidays Act 2003 s.16', 1, 0.85, 0.98, 0.90),
('alternative_holiday_assessment', 'Alternative Holiday - Must be Taken Within 6 Months', 'validation', '{"alt_holiday_granted_date_age_months": {"min": 6}}', '{"decision": "escalate", "action": "urgent_reminder", "confidence_boost": 0}', 80, 'Holidays Act 2003 s.16(3)', 1, 0.90, 0.99, 0.95),
('alternative_holiday_assessment', 'Anniversary Date - Annual Leave Accrual', 'calculation', '{"anniversary_date_reached": true}', '{"decision": "approve", "action": "accrue_20_days", "confidence_boost": 0.3}', 110, 'Holidays Act 2003 s.13', 1, 0.95, 0.99, 0.98),
('alternative_holiday_assessment', 'Public Holiday on Weekend - No Premium', 'validation', '{"public_holiday_on_weekend": true, "not_normally_working_day": true}', '{"decision": "decline", "action": "no_premium_required", "confidence_boost": 0.25}', 90, 'Holidays Act 2003 s.19', 1, 0.85, 0.95, 0.90);

-- ============================================================================
-- CREATE: v_pending_ai_reviews view (if missing)
-- ============================================================================
DROP VIEW IF EXISTS `v_pending_ai_reviews`;

CREATE VIEW `v_pending_ai_reviews` AS
SELECT
    'leave_request' as review_type,
    lr.id as entity_id,
    e.name as staff_name,
    lr.leave_type as category,
    lr.reason as details,
    lr.start_date as date_field,
    lr.created_at as submitted_at,
    lr.status as current_status
FROM payroll_nz_leave_requests lr
LEFT JOIN employee e ON lr.staff_id = e.id
WHERE lr.status = 'pending'

UNION ALL

SELECT
    'wage_discrepancy' as review_type,
    wd.id as entity_id,
    e.name as staff_name,
    wd.discrepancy_type as category,
    wd.description as details,
    wd.pay_period_start as date_field,
    wd.created_at as submitted_at,
    wd.status as current_status
FROM payroll_wages_discrepancies wd
LEFT JOIN employee e ON wd.staff_id = e.id
WHERE wd.status IN ('pending', 'ai_review')

UNION ALL

SELECT
    'statutory_deduction' as review_type,
    psd.id as entity_id,
    e.name as staff_name,
    psd.order_type as category,
    psd.order_reference as details,
    psd.effective_from as date_field,
    psd.created_at as submitted_at,
    psd.status as current_status
FROM payroll_nz_statutory_deductions psd
LEFT JOIN employee e ON psd.staff_id = e.id
WHERE psd.status = 'active'

ORDER BY submitted_at DESC;

-- ============================================================================
-- FINAL CHECKS
-- ============================================================================
SELECT '=== DEPLOYMENT COMPLETE ===' AS status;

SELECT COUNT(*) as total_ai_rules FROM payroll_ai_decision_rules WHERE is_active = 1;
SELECT COUNT(*) as sick_leave_rules FROM payroll_ai_decision_rules WHERE decision_type = 'sick_leave_validation' AND is_active = 1;
SELECT COUNT(*) as bereavement_rules FROM payroll_ai_decision_rules WHERE decision_type = 'bereavement_assessment' AND is_active = 1;
SELECT COUNT(*) as dv_leave_rules FROM payroll_ai_decision_rules WHERE decision_type = 'domestic_violence_leave' AND is_active = 1;
SELECT COUNT(*) as pay_dispute_rules FROM payroll_ai_decision_rules WHERE decision_type = 'pay_dispute_resolution' AND is_active = 1;

SET FOREIGN_KEY_CHECKS = 1;
SELECT '=== READY TO DEPLOY ===' AS status;
