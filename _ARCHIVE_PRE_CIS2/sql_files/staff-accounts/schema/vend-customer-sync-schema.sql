-- ============================================================================
-- VEND CUSTOMER SYNC - ENHANCED SCHEMA
-- ============================================================================
-- Purpose: Comprehensive Vend/Lightspeed customer data sync
-- Created: 2025-10-24
-- Author: CIS Development
-- Version: 1.0.0
-- ============================================================================

-- ============================================================================
-- PHASE 1: ALTER staff_account_reconciliation (Add Customer Fields)
-- ============================================================================

ALTER TABLE staff_account_reconciliation 
ADD COLUMN IF NOT EXISTS credit_limit DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Max credit allowed (0=unlimited)' AFTER vend_balance,
ADD COLUMN IF NOT EXISTS credit_account_id VARCHAR(50) NULL COMMENT 'Vend CreditAccount ID' AFTER vend_customer_id,
ADD COLUMN IF NOT EXISTS customer_type_id INT NULL COMMENT 'Customer tier/category' AFTER credit_account_id,
ADD COLUMN IF NOT EXISTS discount_id INT NULL COMMENT 'Automatic discount level' AFTER customer_type_id,
ADD COLUMN IF NOT EXISTS customer_code VARCHAR(100) NULL COMMENT 'Custom employee/customer code' AFTER employee_name,
ADD COLUMN IF NOT EXISTS customer_company VARCHAR(255) NULL COMMENT 'Company name' AFTER customer_code,
ADD COLUMN IF NOT EXISTS customer_email VARCHAR(255) NULL COMMENT 'Primary email' AFTER customer_company,
ADD COLUMN IF NOT EXISTS customer_phone VARCHAR(50) NULL COMMENT 'Primary phone' AFTER customer_email,
ADD COLUMN IF NOT EXISTS customer_dob DATE NULL COMMENT 'Date of birth' AFTER customer_phone,
ADD COLUMN IF NOT EXISTS customer_archived TINYINT(1) DEFAULT 0 COMMENT 'Is customer archived in Vend' AFTER customer_dob,
ADD COLUMN IF NOT EXISTS customer_created_at DATETIME NULL COMMENT 'Vend account creation date' AFTER customer_archived,
ADD COLUMN IF NOT EXISTS vend_last_synced_at DATETIME NULL COMMENT 'Last full sync from Vend' AFTER vend_balance_updated_at,
ADD INDEX IF NOT EXISTS idx_credit_limit (credit_limit),
ADD INDEX IF NOT EXISTS idx_credit_account (credit_account_id),
ADD INDEX IF NOT EXISTS idx_customer_email (customer_email),
ADD INDEX IF NOT EXISTS idx_archived (customer_archived),
ADD INDEX IF NOT EXISTS idx_last_synced (vend_last_synced_at);

-- ============================================================================
-- PHASE 2: CREATE vend_credit_accounts (Credit Account Details)
-- ============================================================================

CREATE TABLE IF NOT EXISTS vend_credit_accounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    credit_account_id VARCHAR(50) NOT NULL UNIQUE COMMENT 'Vend CreditAccount ID',
    vend_customer_id VARCHAR(50) NOT NULL COMMENT 'Links to customer',
    name VARCHAR(255) COMMENT 'Account holder name',
    code VARCHAR(100) COMMENT 'Gift card code (if applicable)',
    credit_limit DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Max credit (0=unlimited)',
    current_balance DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Current balance',
    description TEXT COMMENT 'Account notes',
    is_gift_card TINYINT(1) DEFAULT 0 COMMENT 'Is this a gift card',
    archived TINYINT(1) DEFAULT 0 COMMENT 'Is archived',
    vend_created_at DATETIME NULL COMMENT 'Created in Vend',
    vend_updated_at DATETIME NULL COMMENT 'Last updated in Vend',
    last_synced_at DATETIME NULL COMMENT 'Last synced to CIS',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    UNIQUE KEY uk_credit_account (credit_account_id),
    UNIQUE KEY uk_vend_customer (vend_customer_id),
    INDEX idx_balance (current_balance),
    INDEX idx_limit (credit_limit),
    INDEX idx_archived (archived),
    INDEX idx_last_synced (last_synced_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Vend CreditAccount details with limits and balances';

-- ============================================================================
-- PHASE 3: CREATE vend_customer_contacts (Contact Information)
-- ============================================================================

CREATE TABLE IF NOT EXISTS vend_customer_contacts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vend_customer_id VARCHAR(50) NOT NULL COMMENT 'Links to vend customer',
    
    -- Email addresses
    email_primary VARCHAR(255) COMMENT 'Primary email',
    email_secondary VARCHAR(255) COMMENT 'Secondary email',
    
    -- Phone numbers
    phone_mobile VARCHAR(50) COMMENT 'Mobile phone',
    phone_home VARCHAR(50) COMMENT 'Home phone',
    phone_work VARCHAR(50) COMMENT 'Work phone',
    phone_fax VARCHAR(50) COMMENT 'Fax number',
    
    -- Physical address
    address_line1 VARCHAR(255) COMMENT 'Street address',
    address_line2 VARCHAR(255) COMMENT 'Apt/Suite/Unit',
    city VARCHAR(100) COMMENT 'City',
    state VARCHAR(100) COMMENT 'State/Province',
    state_code VARCHAR(10) COMMENT 'State ISO code',
    zip VARCHAR(20) COMMENT 'Postal/ZIP code',
    country VARCHAR(100) COMMENT 'Country',
    country_code VARCHAR(10) COMMENT 'Country ISO code',
    
    -- Communication preferences
    no_email TINYINT(1) DEFAULT 0 COMMENT 'Opt-out of email',
    no_phone TINYINT(1) DEFAULT 0 COMMENT 'Opt-out of phone',
    no_mail TINYINT(1) DEFAULT 0 COMMENT 'Opt-out of postal mail',
    
    -- Metadata
    vend_updated_at DATETIME NULL COMMENT 'Last updated in Vend',
    last_synced_at DATETIME NULL COMMENT 'Last synced to CIS',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    UNIQUE KEY uk_vend_customer (vend_customer_id),
    INDEX idx_email_primary (email_primary),
    INDEX idx_phone_mobile (phone_mobile),
    INDEX idx_city (city),
    INDEX idx_state (state),
    INDEX idx_country (country)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Vend customer contact details (email, phone, address)';

-- ============================================================================
-- PHASE 4: CREATE vend_customer_custom_fields (Custom Field Values)
-- ============================================================================

CREATE TABLE IF NOT EXISTS vend_customer_custom_fields (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vend_customer_id VARCHAR(50) NOT NULL COMMENT 'Links to vend customer',
    field_name VARCHAR(100) NOT NULL COMMENT 'Custom field name',
    field_value TEXT COMMENT 'Custom field value',
    field_type VARCHAR(50) COMMENT 'Field type (text, number, date, etc)',
    
    vend_updated_at DATETIME NULL COMMENT 'Last updated in Vend',
    last_synced_at DATETIME NULL COMMENT 'Last synced to CIS',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    UNIQUE KEY uk_customer_field (vend_customer_id, field_name),
    INDEX idx_customer (vend_customer_id),
    INDEX idx_field_name (field_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Vend customer custom field values';

-- ============================================================================
-- PHASE 5: CREATE vend_customer_tags (Customer Tags/Labels)
-- ============================================================================

CREATE TABLE IF NOT EXISTS vend_customer_tags (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vend_customer_id VARCHAR(50) NOT NULL COMMENT 'Links to vend customer',
    tag_id VARCHAR(50) NOT NULL COMMENT 'Vend tag ID',
    tag_name VARCHAR(100) COMMENT 'Tag display name',
    
    vend_created_at DATETIME NULL COMMENT 'Created in Vend',
    last_synced_at DATETIME NULL COMMENT 'Last synced to CIS',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    UNIQUE KEY uk_customer_tag (vend_customer_id, tag_id),
    INDEX idx_customer (vend_customer_id),
    INDEX idx_tag_name (tag_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Vend customer tags for segmentation';

-- ============================================================================
-- PHASE 6: CREATE vend_credit_account_transactions (Transaction History)
-- ============================================================================

CREATE TABLE IF NOT EXISTS vend_credit_account_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    credit_account_id VARCHAR(50) NOT NULL COMMENT 'Links to credit account',
    vend_customer_id VARCHAR(50) NOT NULL COMMENT 'Links to customer',
    sale_payment_id VARCHAR(50) NULL COMMENT 'Vend SalePayment ID',
    sale_id VARCHAR(50) NULL COMMENT 'Vend Sale ID',
    
    transaction_type ENUM('deposit', 'withdrawal', 'adjustment', 'refund') NOT NULL,
    amount DECIMAL(10,2) NOT NULL COMMENT 'Transaction amount',
    balance_before DECIMAL(10,2) COMMENT 'Balance before transaction',
    balance_after DECIMAL(10,2) COMMENT 'Balance after transaction',
    
    payment_type_id INT NULL COMMENT 'Vend PaymentType ID',
    register_id VARCHAR(50) NULL COMMENT 'Vend Register ID',
    outlet_id VARCHAR(50) NULL COMMENT 'Vend Outlet/Shop ID',
    employee_id VARCHAR(50) NULL COMMENT 'Vend Employee ID who processed',
    
    note TEXT COMMENT 'Transaction note',
    reference VARCHAR(100) COMMENT 'External reference',
    
    vend_transaction_date DATETIME NULL COMMENT 'Transaction date in Vend',
    vend_created_at DATETIME NULL COMMENT 'Created in Vend',
    last_synced_at DATETIME NULL COMMENT 'Last synced to CIS',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_credit_account (credit_account_id),
    INDEX idx_customer (vend_customer_id),
    INDEX idx_sale_payment (sale_payment_id),
    INDEX idx_sale (sale_id),
    INDEX idx_transaction_type (transaction_type),
    INDEX idx_transaction_date (vend_transaction_date),
    INDEX idx_outlet (outlet_id),
    INDEX idx_employee (employee_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Credit account transaction history from Vend';

-- ============================================================================
-- PHASE 7: CREATE vend_customer_type (Customer Types/Tiers)
-- ============================================================================

CREATE TABLE IF NOT EXISTS vend_customer_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_type_id INT NOT NULL UNIQUE COMMENT 'Vend CustomerType ID',
    name VARCHAR(100) NOT NULL COMMENT 'Type name (e.g., VIP, Staff, Wholesale)',
    
    vend_created_at DATETIME NULL COMMENT 'Created in Vend',
    vend_updated_at DATETIME NULL COMMENT 'Last updated in Vend',
    last_synced_at DATETIME NULL COMMENT 'Last synced to CIS',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    UNIQUE KEY uk_customer_type_id (customer_type_id),
    INDEX idx_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Vend customer types/tiers';

-- ============================================================================
-- PHASE 8: CREATE vend_sync_log (Track Sync Operations)
-- ============================================================================

CREATE TABLE IF NOT EXISTS vend_sync_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sync_type ENUM('customers', 'credit_accounts', 'contacts', 'transactions', 'tags', 'custom_fields', 'full') NOT NULL,
    sync_mode ENUM('full', 'incremental', 'single') NOT NULL DEFAULT 'incremental',
    
    started_at DATETIME NOT NULL,
    completed_at DATETIME NULL,
    duration_seconds INT NULL COMMENT 'Sync duration',
    
    status ENUM('running', 'completed', 'failed', 'partial') NOT NULL DEFAULT 'running',
    
    records_processed INT DEFAULT 0,
    records_created INT DEFAULT 0,
    records_updated INT DEFAULT 0,
    records_failed INT DEFAULT 0,
    
    error_message TEXT NULL,
    error_details JSON NULL,
    
    triggered_by ENUM('cron', 'manual', 'webhook', 'api') NOT NULL DEFAULT 'cron',
    triggered_by_user_id INT NULL,
    
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_sync_type (sync_type),
    INDEX idx_status (status),
    INDEX idx_started (started_at),
    INDEX idx_completed (completed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Vend sync operation audit trail';

-- ============================================================================
-- VERIFICATION QUERIES
-- ============================================================================

-- Verify all tables exist
SELECT 
    TABLE_NAME, 
    TABLE_ROWS, 
    CREATE_TIME,
    TABLE_COMMENT
FROM information_schema.TABLES 
WHERE TABLE_SCHEMA = DATABASE()
AND TABLE_NAME IN (
    'vend_credit_accounts',
    'vend_customer_contacts',
    'vend_customer_custom_fields',
    'vend_customer_tags',
    'vend_credit_account_transactions',
    'vend_customer_types',
    'vend_sync_log'
)
ORDER BY TABLE_NAME;

-- Verify staff_account_reconciliation columns
SELECT 
    COLUMN_NAME,
    DATA_TYPE,
    COLUMN_TYPE,
    IS_NULLABLE,
    COLUMN_DEFAULT,
    COLUMN_COMMENT
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
AND TABLE_NAME = 'staff_account_reconciliation'
AND COLUMN_NAME IN (
    'credit_limit',
    'credit_account_id',
    'customer_type_id',
    'discount_id',
    'customer_code',
    'customer_company',
    'customer_email',
    'customer_phone',
    'customer_dob',
    'customer_archived',
    'customer_created_at',
    'vend_last_synced_at'
)
ORDER BY ORDINAL_POSITION;
