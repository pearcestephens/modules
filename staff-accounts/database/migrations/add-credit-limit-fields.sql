-- ============================================================================
-- Add Lightspeed CreditAccount Fields (NO DUPLICATION)
-- ============================================================================
-- Date: 2025-10-12
-- Purpose: Add credit_limit tracking from Lightspeed CreditAccount API
-- 
-- DUPLICATION ANALYSIS RESULT:
-- ✅ vend_customers table already has 42 columns with ALL customer data
-- ✅ We only add CreditAccount-specific fields that don't exist elsewhere
-- ❌ We do NOT duplicate: email, phone, customer_code, company_name, dob, etc.
-- 
-- NEW FIELDS (4 total):
-- - credit_limit: From Lightspeed CreditAccount.creditLimit
-- - credit_account_id: From Lightspeed CreditAccount.creditAccountID  
-- - discount_id: From Lightspeed Customer.discountID (if applicable)
-- - vend_last_synced_at: Track when we last synced from API
-- ============================================================================

-- Add new columns (non-duplicate only)
ALTER TABLE staff_account_reconciliation 
ADD COLUMN credit_limit DECIMAL(10,2) DEFAULT 0.00 
    COMMENT 'Max credit allowed from Lightspeed CreditAccount API (0 = unlimited)' 
    AFTER vend_balance,
    
ADD COLUMN credit_account_id VARCHAR(50) NULL 
    COMMENT 'Lightspeed CreditAccount ID for this customer' 
    AFTER vend_customer_id,
    
ADD COLUMN discount_id VARCHAR(50) NULL 
    COMMENT 'Lightspeed Customer discount ID (if applicable)' 
    AFTER credit_account_id,
    
ADD COLUMN vend_last_synced_at DATETIME NULL 
    COMMENT 'Last time we synced credit_limit from Lightspeed CreditAccount API' 
    AFTER vend_balance_updated_at;

-- Add indexes for performance
ALTER TABLE staff_account_reconciliation 
ADD INDEX idx_credit_limit (credit_limit),
ADD INDEX idx_vend_last_synced (vend_last_synced_at);

-- ============================================================================
-- VERIFICATION QUERIES
-- ============================================================================

-- Verify new columns exist
SELECT 
    COLUMN_NAME, 
    COLUMN_TYPE, 
    IS_NULLABLE, 
    COLUMN_DEFAULT,
    COLUMN_COMMENT
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = 'staff_accounts_db'
  AND TABLE_NAME = 'staff_account_reconciliation'
  AND COLUMN_NAME IN ('credit_limit', 'credit_account_id', 'discount_id', 'vend_last_synced_at')
ORDER BY ORDINAL_POSITION;

-- Verify indexes exist
SHOW INDEX FROM staff_account_reconciliation 
WHERE Key_name IN ('idx_credit_limit', 'idx_vend_last_synced');

-- ============================================================================
-- ROLLBACK (if needed)
-- ============================================================================

-- To rollback this migration:
-- ALTER TABLE staff_account_reconciliation 
-- DROP COLUMN credit_limit,
-- DROP COLUMN credit_account_id,
-- DROP COLUMN discount_id,
-- DROP COLUMN vend_last_synced_at,
-- DROP INDEX idx_credit_limit,
-- DROP INDEX idx_vend_last_synced;

-- ============================================================================
-- DATA ACCESS PATTERN (USE JOINS, NOT DUPLICATION)
-- ============================================================================

-- CORRECT: Join vend_customers for customer data
-- SELECT 
--     sar.id,
--     sar.employee_name,
--     sar.vend_balance,
--     sar.credit_limit,              -- NEW: Only in reconciliation table
--     sar.outstanding_amount,
--     vc.email,                      -- From vend_customers (don't duplicate)
--     vc.phone,                      -- From vend_customers (don't duplicate)
--     vc.customer_code,              -- From vend_customers (don't duplicate)
--     vc.company_name,               -- From vend_customers (don't duplicate)
--     vc.date_of_birth,              -- From vend_customers (don't duplicate)
--     vc.balance AS vend_raw_balance -- From vend_customers
-- FROM staff_account_reconciliation sar
-- LEFT JOIN vend_customers vc ON sar.vend_customer_id = vc.id
-- WHERE sar.status != 'archived';

-- ============================================================================
