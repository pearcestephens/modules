-- ============================================================================
-- CREATE MISSING VIEWS FOR PAYROLL AI SYSTEM
-- ============================================================================
-- Database: jcepnzzkmj
-- MariaDB 10.5+
-- ============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================================
-- VIEW: v_pending_ai_reviews
-- Purpose: Show all pending AI decision reviews that need human approval
-- ============================================================================
DROP VIEW IF EXISTS `v_pending_ai_reviews`;

CREATE VIEW `v_pending_ai_reviews` AS
SELECT
    padr.id as decision_request_id,
    padr.rule_code,
    padr.staff_id,
    padr.decision_type,
    padr.request_data,
    padr.ai_recommendation,
    padr.confidence_score,
    padr.status,
    padr.created_at,
    padr.expires_at,
    rl.rule_name,
    rl.description,
    rl.legislation_reference,
    NULL AS staff_first_name,
    NULL AS staff_last_name,
    NULL AS staff_email,
    NULL AS staff_code
FROM payroll_ai_decision_requests padr
LEFT JOIN payroll_ai_decision_rules rl ON padr.rule_code = rl.rule_code
WHERE padr.status = 'PENDING_REVIEW'
  AND padr.requires_human_review = 1
  AND (padr.expires_at IS NULL OR padr.expires_at > NOW())
ORDER BY padr.confidence_score ASC, padr.created_at ASC;

-- ============================================================================
-- VIEW: v_active_ai_rules
-- Purpose: Show all active AI rules available for decision-making
-- ============================================================================
DROP VIEW IF EXISTS `v_active_ai_rules`;

CREATE VIEW `v_active_ai_rules` AS
SELECT
    id,
    rule_code,
    decision_type,
    rule_name,
    description,
    legislation_reference,
    case_law_reference,
    ird_guidance,
    conditions,
    decision_tree,
    confidence_threshold,
    system_prompt_template,
    user_prompt_template,
    response_schema,
    requires_evidence,
    evidence_types,
    auto_approve_conditions,
    auto_decline_conditions,
    always_require_human_review,
    human_review_conditions,
    times_applied,
    approval_rate,
    average_confidence,
    human_override_rate,
    effective_from,
    effective_to,
    created_at,
    updated_at
FROM payroll_ai_decision_rules
WHERE is_active = 1
  AND effective_from <= CURDATE()
  AND (effective_to IS NULL OR effective_to >= CURDATE())
ORDER BY rule_code ASC;

-- ============================================================================
-- VIEW: v_rule_performance
-- Purpose: Track rule accuracy and override rates for continuous improvement
-- ============================================================================
DROP VIEW IF EXISTS `v_rule_performance`;

CREATE VIEW `v_rule_performance` AS
SELECT
    rule_code,
    rule_name,
    decision_type,
    times_applied,
    approval_rate,
    average_confidence,
    human_override_rate,
    CASE
        WHEN times_applied = 0 THEN 'No Usage'
        WHEN approval_rate >= 0.85 AND human_override_rate <= 0.15 THEN 'Performing Well'
        WHEN approval_rate >= 0.70 AND human_override_rate <= 0.25 THEN 'Good'
        WHEN approval_rate >= 0.50 AND human_override_rate <= 0.40 THEN 'Needs Review'
        ELSE 'Poor Performance'
    END as performance_status,
    CASE
        WHEN times_applied < 10 THEN 'Insufficient Data'
        WHEN times_applied < 50 THEN 'Limited Data'
        WHEN times_applied < 200 THEN 'Moderate Data'
        ELSE 'Robust Data'
    END as data_sufficiency,
    CONCAT(ROUND(approval_rate * 100, 1), '%') as approval_pct,
    CONCAT(ROUND(average_confidence * 100, 1), '%') as avg_confidence_pct,
    CONCAT(ROUND(human_override_rate * 100, 1), '%') as override_pct,
    updated_at
FROM payroll_ai_decision_rules
WHERE is_active = 1
ORDER BY times_applied DESC, average_confidence DESC;

-- ============================================================================
-- VIEW: v_deductions_requiring_attention
-- Purpose: Flag deductions that may breach net pay protection or other concerns
-- ============================================================================
DROP VIEW IF EXISTS `v_deductions_requiring_attention`;

CREATE VIEW `v_deductions_requiring_attention` AS
SELECT
    psd.id,
    psd.payroll_run_id,
    psd.staff_id,
    psd.deduction_type,
    psd.amount,
    psd.description,
    psd.statutory_authority,
    psd.order_reference,
    psd.is_active,
    psd.created_at,
    NULL AS staff_code,
    NULL AS staff_first_name,
    NULL AS staff_last_name,
    NULL AS staff_email,
    (SELECT SUM(amount) FROM payroll_nz_statutory_deductions
     WHERE payroll_run_id = psd.payroll_run_id AND staff_id = psd.staff_id) as total_deductions,
    pr.gross_pay,
    CONCAT(
        ROUND(((SELECT SUM(amount) FROM payroll_nz_statutory_deductions
         WHERE payroll_run_id = psd.payroll_run_id AND staff_id = psd.staff_id) / pr.gross_pay) * 100, 1),
        '%'
    ) as deductions_pct_of_gross,
    CASE
        WHEN ((SELECT SUM(amount) FROM payroll_nz_statutory_deductions
         WHERE payroll_run_id = psd.payroll_run_id AND staff_id = psd.staff_id) / pr.gross_pay) > 0.25
        THEN 'WARNING: Exceeds 25% of gross'
        ELSE 'Normal'
    END as net_pay_status
FROM payroll_nz_statutory_deductions psd
LEFT JOIN payroll_runs pr ON psd.payroll_run_id = pr.id
WHERE psd.is_active = 1
  AND ((SELECT SUM(amount) FROM payroll_nz_statutory_deductions
       WHERE payroll_run_id = psd.payroll_run_id AND staff_id = psd.staff_id) / pr.gross_pay) >= 0.20
ORDER BY deductions_pct_of_gross DESC;

-- ============================================================================
-- VIEW: v_ai_decision_audit_trail
-- Purpose: Complete audit trail of all AI decisions for compliance and review
-- ============================================================================
DROP VIEW IF EXISTS `v_ai_decision_audit_trail`;

CREATE VIEW `v_ai_decision_audit_trail` AS
SELECT
    padr.id,
    padr.rule_code,
    padr.staff_id,
    padr.decision_type,
    padr.request_data,
    padr.ai_recommendation,
    padr.confidence_score,
    padr.status,
    padr.requires_human_review,
    padr.human_review_reason,
    padr.reviewed_by,
    padr.reviewed_at,
    padr.review_notes,
    padr.final_decision,
    padr.created_at,
    rl.rule_name,
    rl.legislation_reference,
    NULL AS staff_code,
    NULL AS staff_first_name,
    NULL AS staff_last_name,
    NULL AS reviewed_by_first,
    NULL AS reviewed_by_last,
    DATE_FORMAT(padr.created_at, '%Y-%m-%d %H:%i:%s') as decision_timestamp,
    CASE
        WHEN padr.status = 'APPROVED' THEN 'AI Decision Applied'
        WHEN padr.status = 'PENDING_REVIEW' THEN 'Awaiting Human Review'
        WHEN padr.status = 'DECLINED' THEN 'Decision Rejected'
        WHEN padr.status = 'ESCALATED' THEN 'Escalated to Manager'
        ELSE padr.status
    END as decision_status_description
FROM payroll_ai_decision_requests padr
LEFT JOIN payroll_ai_decision_rules rl ON padr.rule_code = rl.rule_code
ORDER BY padr.created_at DESC;

-- ============================================================================
-- VIEW: v_leave_balance_with_ai_status
-- Purpose: Show leave balances with AI rule compliance status
-- ============================================================================
DROP VIEW IF EXISTS `v_leave_balance_with_ai_status`;

CREATE VIEW `v_leave_balance_with_ai_status` AS
SELECT
    plr.id,
    plr.staff_id,
    plr.leave_type,
    plr.requested_days,
    plr.approved_days,
    plr.status,
    plr.ai_decision_status,
    plr.approval_chain_level,
    st.staff_code,
    st.first_name,
    st.last_name,
    st.job_title,
    plr.requested_start_date,
    plr.requested_end_date,
    plr.submission_date,
    plr.approval_date,
    DATEDIFF(CURDATE(), plr.submission_date) as days_pending,
    (SELECT SUM(approved_days) FROM payroll_nz_leave_requests
     WHERE staff_id = plr.staff_id AND leave_type = plr.leave_type
     AND YEAR(approval_date) = YEAR(CURDATE())
     AND approval_date IS NOT NULL) as ytd_approved,
    CASE
        WHEN plr.ai_decision_status = 'AUTO_APPROVED' THEN 'AI Auto-Approved'
        WHEN plr.ai_decision_status = 'PENDING_AI_REVIEW' THEN 'Awaiting AI'
        WHEN plr.ai_decision_status = 'HUMAN_OVERRIDE' THEN 'Human Override'
        ELSE plr.ai_decision_status
    END as ai_decision_description
FROM payroll_nz_leave_requests plr
LEFT JOIN staff st ON plr.staff_id = st.id
WHERE plr.status IN ('PENDING', 'APPROVED', 'PENDING_REVIEW')
ORDER BY FIELD(plr.status, 'PENDING', 'PENDING_REVIEW', 'APPROVED'), plr.submission_date DESC;

-- ============================================================================
-- VERIFICATION
-- ============================================================================
SELECT '=== VIEWS CREATED ===' AS status;
SELECT CONCAT(TABLE_SCHEMA, '.', TABLE_NAME) as view_name
FROM INFORMATION_SCHEMA.TABLES
WHERE TABLE_SCHEMA = 'jcepnzzkmj'
  AND TABLE_TYPE = 'VIEW'
  AND TABLE_NAME LIKE 'v_%'
ORDER BY TABLE_NAME;

SET FOREIGN_KEY_CHECKS = 1;
