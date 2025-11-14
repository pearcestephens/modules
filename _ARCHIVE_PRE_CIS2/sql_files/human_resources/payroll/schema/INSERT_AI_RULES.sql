-- ============================================================================
-- INSERT 27 AI DECISION RULES INTO payroll_ai_decision_rules
-- ============================================================================
-- Corrected INSERT statement matching actual table schema
-- Database: jcepnzzkmj
-- MariaDB 10.5+
-- ============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================================
-- DELETE EXISTING RULES (if any) TO PREVENT DUPLICATES
-- ============================================================================
DELETE FROM payroll_ai_decision_rules WHERE rule_code IN (
  'SL_001', 'SL_002', 'SL_003', 'SL_004', 'SL_005', 'SL_006',
  'BER_001', 'BER_002', 'BER_003', 'BER_004',
  'DV_001', 'DV_002', 'DV_003',
  'PAY_001', 'PAY_002', 'PAY_003', 'PAY_004', 'PAY_005',
  'STAT_001', 'STAT_002', 'STAT_003', 'STAT_004', 'STAT_005',
  'ALT_001', 'ALT_002', 'ALT_003', 'ALT_004'
);

-- ============================================================================
-- SICK LEAVE VALIDATION RULES (6 rules)
-- ============================================================================
INSERT INTO `payroll_ai_decision_rules`
(rule_code, decision_type, rule_name, description, legislation_reference, conditions, confidence_threshold, is_active, effective_from)
VALUES
('SL_001', 'sick_leave_validation', 'Single Day Without Certificate', 'Approve single sick day without medical certificate', 'Holidays Act 2003', JSON_OBJECT('duration_days', 1, 'has_certificate', false), 0.6000, 1, CURDATE()),
('SL_002', 'sick_leave_validation', '2-3 Days Without Certificate', 'Request evidence for 2-3 day sick leave without cert', 'Holidays Act 2003 s.14', JSON_OBJECT('duration_days_min', 2, 'duration_days_max', 3, 'has_certificate', false), 0.5000, 1, CURDATE()),
('SL_003', 'sick_leave_validation', '4+ Days Requires Certificate', 'Decline/escalate 4+ days without medical certificate', 'Holidays Act 2003 s.14(1)', JSON_OBJECT('duration_days_min', 4, 'has_certificate', false), 0.7000, 1, CURDATE()),
('SL_004', 'sick_leave_validation', 'Frequent Pattern Detection', 'Escalate if frequent sick leave pattern detected', 'Employment Relations Act 2000', JSON_OBJECT('frequency_90d_min', 10, 'pattern_risk', 'high'), 0.6000, 1, CURDATE()),
('SL_005', 'sick_leave_validation', 'Medical Certificate Valid', 'Auto-approve with valid medical certificate', 'Holidays Act 2003 s.14', JSON_OBJECT('has_certificate', true, 'certificate_valid', true), 0.8000, 1, CURDATE()),
('SL_006', 'sick_leave_validation', 'Partial Day Request', 'Approve partial day sick leave (1-4 hours)', 'Holidays Act 2003', JSON_OBJECT('is_partial_day', true, 'hours_min', 0, 'hours_max', 4), 0.7000, 1, CURDATE());

-- ============================================================================
-- BEREAVEMENT LEAVE RULES (4 rules)
-- ============================================================================
INSERT INTO `payroll_ai_decision_rules`
(rule_code, decision_type, rule_name, description, legislation_reference, conditions, confidence_threshold, is_active, effective_from)
VALUES
('BER_001', 'bereavement_assessment', 'Immediate Family - 3 Days', 'Approve 3 days for spouse, child, parent, sibling', 'Holidays Act 2003 s.14A', JSON_OBJECT('relationship', JSON_ARRAY('spouse', 'child', 'parent', 'sibling')), 0.8500, 1, CURDATE()),
('BER_002', 'bereavement_assessment', 'Extended Family - 1 Day', 'Approve 1 day for grandparent, in-law, etc', 'Holidays Act 2003 s.14A', JSON_OBJECT('relationship', JSON_ARRAY('grandparent', 'in-law', 'uncle', 'aunt', 'cousin')), 0.7500, 1, CURDATE()),
('BER_003', 'bereavement_assessment', 'Frequent Claims Detection', 'Escalate if multiple bereavement claims in 24 months', 'Employment Relations Act 2000', JSON_OBJECT('claims_24m_min', 3), 0.6000, 1, CURDATE()),
('BER_004', 'bereavement_assessment', 'International Travel Required', 'Escalate for international travel bereavement cases', 'Holidays Act 2003 s.14A', JSON_OBJECT('international_travel', true), 0.6500, 1, CURDATE());

-- ============================================================================
-- DOMESTIC VIOLENCE LEAVE RULES (3 rules)
-- ============================================================================
INSERT INTO `payroll_ai_decision_rules`
(rule_code, decision_type, rule_name, description, legislation_reference, conditions, confidence_threshold, is_active, effective_from)
VALUES
('DV_001', 'domestic_violence_leave', 'Valid DV Claim - 10 Days/Year', 'Approve 10 days for validated domestic violence claims', 'Domestic Violence - Victims\' Protection Act 2018', JSON_OBJECT('dv_claim_valid', true, 'protective_order', true), 0.8500, 1, CURDATE()),
('DV_002', 'domestic_violence_leave', 'Unverified DV Claim', 'Escalate unverified domestic violence claims to HR', 'Domestic Violence - Victims\' Protection Act 2018', JSON_OBJECT('dv_claim_valid', false, 'evidence_provided', false), 0.6000, 1, CURDATE()),
('DV_003', 'domestic_violence_leave', 'DV Emergency Leave', 'Approve immediate leave for DV emergencies', 'Domestic Violence - Victims\' Protection Act 2018', JSON_OBJECT('dv_emergency', true, 'urgent', true), 0.8000, 1, CURDATE());

-- ============================================================================
-- PAY DISPUTE RESOLUTION RULES (5 rules)
-- ============================================================================
INSERT INTO `payroll_ai_decision_rules`
(rule_code, decision_type, rule_name, description, legislation_reference, conditions, confidence_threshold, is_active, effective_from)
VALUES
('PAY_001', 'pay_dispute_resolution', 'Approved Timesheet Amendment', 'Approve pay correction for approved timesheet amendments', 'Employment Standards Act 2015', JSON_OBJECT('timesheet_amended', true, 'amendment_approved', true, 'reason_valid', true), 0.7500, 1, CURDATE()),
('PAY_002', 'pay_dispute_resolution', 'Overtime Calculation Error', 'Approve overtime recalculation when error confirmed', 'Employment Standards Act 2015', JSON_OBJECT('discrepancy_type', 'overtime_calc', 'error_confirmed', true), 0.8000, 1, CURDATE()),
('PAY_003', 'pay_dispute_resolution', 'Public Holiday Premium', 'Apply premium for work on statutory holidays', 'Holidays Act 2003 s.19', JSON_OBJECT('worked_public_holiday', true, 'entitled_premium', true), 0.8500, 1, CURDATE()),
('PAY_004', 'pay_dispute_resolution', 'Unidentified Discrepancy', 'Escalate unusual pay discrepancies for investigation', 'Employment Standards Act 2015', JSON_OBJECT('discrepancy_reason_unknown', true, 'pattern_unusual', true), 0.5000, 1, CURDATE()),
('PAY_005', 'pay_dispute_resolution', 'Duplicate Payment Detection', 'Flag duplicate payment for reversal', 'Employment Standards Act 2015', JSON_OBJECT('duplicate_detected', true, 'confirmed', true), 0.9000, 1, CURDATE());

-- ============================================================================
-- STATUTORY DEDUCTION RULES (5 rules)
-- ============================================================================
INSERT INTO `payroll_ai_decision_rules`
(rule_code, decision_type, rule_name, description, legislation_reference, conditions, confidence_threshold, is_active, effective_from)
VALUES
('STAT_001', 'statutory_deduction_assessment', 'Valid Court Order', 'Apply court-ordered deduction immediately', 'District Courts Act 1947, Crimes Victims\' Rights Act 2015', JSON_OBJECT('order_type', 'court_order', 'order_valid', true, 'court_verified', true), 0.8500, 1, CURDATE()),
('STAT_002', 'statutory_deduction_assessment', 'Child Support Order', 'Apply CSI-verified child support deduction', 'Child Support Act 1991', JSON_OBJECT('order_type', 'child_support', 'csi_verified', true), 0.9000, 1, CURDATE()),
('STAT_003', 'statutory_deduction_assessment', 'IRD Debt Deduction', 'Apply IRD-verified tax debt deduction', 'Tax Administration Act 1994', JSON_OBJECT('order_type', 'ird_debt', 'ird_verified', true, 'legal_authority', true), 0.9000, 1, CURDATE()),
('STAT_004', 'statutory_deduction_assessment', 'Student Loan Deduction', 'Apply NZSL-verified student loan deduction', 'Student Loan Scheme Act 2011', JSON_OBJECT('order_type', 'student_loan', 'nzsl_verified', true), 0.8500, 1, CURDATE()),
('STAT_005', 'statutory_deduction_assessment', 'Net Pay Protection', 'Cap deduction to maintain minimum net pay threshold', 'Employment Standards Act 2015 s.4', JSON_OBJECT('total_deductions_pct_min', 25, 'net_below_protected', true), 0.8000, 1, CURDATE());

-- ============================================================================
-- ALTERNATIVE LEAVE/HOLIDAY RULES (4 rules)
-- ============================================================================
INSERT INTO `payroll_ai_decision_rules`
(rule_code, decision_type, rule_name, description, legislation_reference, conditions, confidence_threshold, is_active, effective_from)
VALUES
('ALT_001', 'alternative_holiday_assessment', 'Worked Public Holiday', 'Grant alternative holiday for worked public holiday', 'Holidays Act 2003 s.16', JSON_OBJECT('worked_public_holiday', true, 'normally_working', true), 0.8500, 1, CURDATE()),
('ALT_002', 'alternative_holiday_assessment', 'Alt Holiday Expiry Alert', 'Alert if alternative holiday expires (6 months unused)', 'Holidays Act 2003 s.16(3)', JSON_OBJECT('alt_holiday_age_months_min', 6, 'unused', true), 0.9000, 1, CURDATE()),
('ALT_003', 'alternative_holiday_assessment', 'Anniversary Date Accrual', 'Automatically accrue 20 days annual leave on anniversary', 'Holidays Act 2003 s.13', JSON_OBJECT('anniversary_reached', true), 0.9500, 1, CURDATE()),
('ALT_004', 'alternative_holiday_assessment', 'Weekend Public Holiday', 'No premium for public holiday on non-working weekend', 'Holidays Act 2003 s.19', JSON_OBJECT('public_holiday_weekend', true, 'not_normally_working', true), 0.8500, 1, CURDATE());

-- ============================================================================
-- VERIFY INSERTS
-- ============================================================================
SELECT '=== VERIFICATION ===' AS status;
SELECT COUNT(*) as total_rules FROM payroll_ai_decision_rules WHERE is_active = 1;
SELECT decision_type, COUNT(*) as count FROM payroll_ai_decision_rules WHERE is_active = 1 GROUP BY decision_type;

SET FOREIGN_KEY_CHECKS = 1;
SELECT '=== INSERT COMPLETE ===' AS status;
