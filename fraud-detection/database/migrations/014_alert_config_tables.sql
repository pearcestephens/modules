-- =====================================================
-- FRAUD DETECTION: Alert Log & Configuration Tables
-- =====================================================

-- Alert log table (tracks all alerts sent)
CREATE TABLE IF NOT EXISTS fraud_alert_log (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    staff_id INT UNSIGNED NOT NULL,
    analysis_id INT UNSIGNED,
    alert_type ENUM('email', 'slack', 'sms', 'webhook') NOT NULL,
    risk_level ENUM('low', 'medium', 'high', 'critical') NOT NULL,
    risk_score DECIMAL(5,2) NOT NULL,
    recipient VARCHAR(255) NOT NULL COMMENT 'Email address, phone number, or channel',
    alert_data JSON COMMENT 'Complete alert details',
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    delivery_status ENUM('pending', 'sent', 'failed') DEFAULT 'pending',
    failure_reason TEXT,
    INDEX idx_staff_sent (staff_id, sent_at),
    INDEX idx_alert_type (alert_type),
    INDEX idx_delivery_status (delivery_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Log of all fraud alerts sent';

-- False positive tracking
CREATE TABLE IF NOT EXISTS fraud_false_positives (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    staff_id INT UNSIGNED NOT NULL,
    indicator_type VARCHAR(100) NOT NULL,
    fraud_category VARCHAR(100) NOT NULL,
    original_severity DECIMAL(3,2) NOT NULL,
    marked_false_positive_by INT UNSIGNED NOT NULL,
    marked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reason TEXT NOT NULL,
    incident_data JSON COMMENT 'Original incident details',
    INDEX idx_staff (staff_id),
    INDEX idx_indicator (indicator_type),
    INDEX idx_marked_by (marked_false_positive_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Track false positives for learning';

-- Configuration audit log
CREATE TABLE IF NOT EXISTS fraud_config_audit (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    config_key VARCHAR(255) NOT NULL,
    old_value TEXT,
    new_value TEXT,
    changed_by INT UNSIGNED NOT NULL,
    changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reason TEXT,
    INDEX idx_config_key (config_key),
    INDEX idx_changed_at (changed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Audit trail for configuration changes';

-- Staff grace period tracking (new staff learning period)
CREATE TABLE IF NOT EXISTS fraud_staff_grace_periods (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    staff_id INT UNSIGNED NOT NULL UNIQUE,
    hire_date DATE NOT NULL,
    grace_period_end_date DATE NOT NULL,
    grace_period_days INT NOT NULL,
    analysis_enabled BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_staff (staff_id),
    INDEX idx_grace_end (grace_period_end_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Track grace periods for new staff';

-- Threshold adjustment history (for auto-tuning)
CREATE TABLE IF NOT EXISTS fraud_threshold_adjustments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    threshold_key VARCHAR(255) NOT NULL,
    old_value DECIMAL(10,2) NOT NULL,
    new_value DECIMAL(10,2) NOT NULL,
    adjustment_reason ENUM('manual', 'auto_tune', 'seasonal', 'false_positive_rate') NOT NULL,
    false_positive_rate DECIMAL(5,4) COMMENT 'FP rate that triggered adjustment',
    adjusted_by INT UNSIGNED,
    adjusted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_threshold (threshold_key),
    INDEX idx_adjusted_at (adjusted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Track threshold adjustments over time';

-- Investigation notes and resolution tracking (enhanced)
ALTER TABLE transaction_camera_mismatches
ADD COLUMN IF NOT EXISTS resolved_by INT UNSIGNED AFTER investigation_notes,
ADD COLUMN IF NOT EXISTS resolved_at TIMESTAMP NULL AFTER resolved_by,
ADD COLUMN IF NOT EXISTS resolution_notes TEXT AFTER resolved_at;

-- Add similar fields to other tracking tables if they don't exist
ALTER TABLE payment_type_fraud_tracking
ADD COLUMN IF NOT EXISTS resolved_by INT UNSIGNED AFTER investigation_notes,
ADD COLUMN IF NOT EXISTS resolved_at TIMESTAMP NULL AFTER resolved_by,
ADD COLUMN IF NOT EXISTS resolution_notes TEXT AFTER resolved_at;

ALTER TABLE customer_account_fraud_tracking
ADD COLUMN IF NOT EXISTS resolved_by INT UNSIGNED AFTER investigation_notes,
ADD COLUMN IF NOT EXISTS resolved_at TIMESTAMP NULL AFTER resolved_by,
ADD COLUMN IF NOT EXISTS resolution_notes TEXT AFTER resolved_at;

ALTER TABLE inventory_fraud_tracking
ADD COLUMN IF NOT EXISTS resolved_by INT UNSIGNED AFTER investigation_notes,
ADD COLUMN IF NOT EXISTS resolved_at TIMESTAMP NULL AFTER resolved_by,
ADD COLUMN IF NOT EXISTS resolution_notes TEXT AFTER resolved_at;

ALTER TABLE register_closure_fraud_tracking
ADD COLUMN IF NOT EXISTS resolved_by INT UNSIGNED AFTER investigation_notes,
ADD COLUMN IF NOT EXISTS resolved_at TIMESTAMP NULL AFTER resolved_by,
ADD COLUMN IF NOT EXISTS resolution_notes TEXT AFTER resolved_at;

ALTER TABLE banking_fraud_tracking
ADD COLUMN IF NOT EXISTS resolved_by INT UNSIGNED AFTER investigation_notes,
ADD COLUMN IF NOT EXISTS resolved_at TIMESTAMP NULL AFTER resolved_by,
ADD COLUMN IF NOT EXISTS resolution_notes TEXT AFTER resolved_at;

ALTER TABLE transaction_manipulation_tracking
ADD COLUMN IF NOT EXISTS resolved_by INT UNSIGNED AFTER investigation_notes,
ADD COLUMN IF NOT EXISTS resolved_at TIMESTAMP NULL AFTER resolved_by,
ADD COLUMN IF NOT EXISTS resolution_notes TEXT AFTER resolved_at;

-- View: Alert summary by staff
CREATE OR REPLACE VIEW v_alert_summary_by_staff AS
SELECT
    staff_id,
    COUNT(*) as total_alerts,
    SUM(CASE WHEN alert_type = 'email' THEN 1 ELSE 0 END) as email_alerts,
    SUM(CASE WHEN alert_type = 'slack' THEN 1 ELSE 0 END) as slack_alerts,
    SUM(CASE WHEN alert_type = 'sms' THEN 1 ELSE 0 END) as sms_alerts,
    SUM(CASE WHEN risk_level = 'critical' THEN 1 ELSE 0 END) as critical_alerts,
    SUM(CASE WHEN risk_level = 'high' THEN 1 ELSE 0 END) as high_alerts,
    MAX(sent_at) as last_alert_sent,
    AVG(risk_score) as avg_risk_score
FROM fraud_alert_log
GROUP BY staff_id;

-- View: False positive rate by indicator type
CREATE OR REPLACE VIEW v_false_positive_rate_by_indicator AS
SELECT
    indicator_type,
    fraud_category,
    COUNT(*) as false_positive_count,
    AVG(original_severity) as avg_severity_of_fps,
    COUNT(*) / (
        SELECT COUNT(*)
        FROM lightspeed_deep_dive_analysis
        WHERE JSON_SEARCH(analysis_data, 'one', indicator_type, NULL, '$.fraud_indicators[*].type') IS NOT NULL
    ) * 100 as false_positive_rate_percentage
FROM fraud_false_positives
GROUP BY indicator_type, fraud_category
ORDER BY false_positive_count DESC;

-- View: Staff in grace period
CREATE OR REPLACE VIEW v_staff_in_grace_period AS
SELECT
    g.staff_id,
    s.name as staff_name,
    g.hire_date,
    g.grace_period_end_date,
    DATEDIFF(g.grace_period_end_date, CURDATE()) as days_remaining,
    g.analysis_enabled
FROM fraud_staff_grace_periods g
LEFT JOIN staff_accounts s ON g.staff_id = s.id
WHERE g.grace_period_end_date >= CURDATE()
ORDER BY g.grace_period_end_date ASC;

-- Verification query
SELECT 'Alert & Configuration Tables Created Successfully' as status,
       COUNT(*) as alert_log_records FROM fraud_alert_log
UNION ALL
SELECT 'False Positives Tracked', COUNT(*) FROM fraud_false_positives
UNION ALL
SELECT 'Config Audit Entries', COUNT(*) FROM fraud_config_audit
UNION ALL
SELECT 'Staff in Grace Period', COUNT(*) FROM v_staff_in_grace_period;
