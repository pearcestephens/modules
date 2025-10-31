-- ============================================================================
-- Credit Limit Management Tables
-- ============================================================================
-- Date: 2025-10-24
-- Purpose: Support credit limit management system
--
-- Tables:
-- 1. staff_account_config - Company-wide settings (default credit limit)
-- 2. staff_account_audit_log - Audit trail for credit limit changes
-- ============================================================================

-- Config table for company-wide settings
CREATE TABLE IF NOT EXISTS staff_account_config (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    config_key VARCHAR(100) NOT NULL UNIQUE,
    config_value TEXT NOT NULL,
    config_description TEXT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_config_key (config_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Company-wide configuration settings for staff accounts';

-- Insert default credit limit setting
INSERT INTO staff_account_config (config_key, config_value, config_description)
VALUES ('staff_default_credit_limit', '500.00', 'Default credit limit for all staff members (0 = unlimited)')
ON DUPLICATE KEY UPDATE config_value = config_value;

-- Audit log table for tracking all changes
CREATE TABLE IF NOT EXISTS staff_account_audit_log (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reconciliation_id INT UNSIGNED NOT NULL,
    action_type VARCHAR(50) NOT NULL,
    old_value TEXT NULL,
    new_value TEXT NULL,
    change_type VARCHAR(50) NULL COMMENT 'individual_override, company_default, revert, bulk',
    changed_by VARCHAR(100) NOT NULL,
    changed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    
    INDEX idx_reconciliation_id (reconciliation_id),
    INDEX idx_action_type (action_type),
    INDEX idx_changed_at (changed_at),
    INDEX idx_changed_by (changed_by),
    
    FOREIGN KEY (reconciliation_id) 
        REFERENCES staff_account_reconciliation(id) 
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Audit trail for all staff account changes';

-- ============================================================================
-- VERIFICATION
-- ============================================================================

-- Verify tables exist
SELECT 'staff_account_config' as table_name, COUNT(*) as row_count 
FROM staff_account_config
UNION ALL
SELECT 'staff_account_audit_log', COUNT(*) 
FROM staff_account_audit_log;

-- Show config
SELECT * FROM staff_account_config;
