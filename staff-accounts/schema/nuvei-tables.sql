-- Nuvei Payment Integration Tables
-- Creates tables for payment transactions, saved cards, and payment plans

-- Payment transactions log
CREATE TABLE IF NOT EXISTS staff_payment_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    transaction_type ENUM('session_created', 'payment_approved', 'payment_failed', 'refund') NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    request_id VARCHAR(100) NOT NULL UNIQUE,
    response_data JSON NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_created_at (created_at),
    INDEX idx_type (transaction_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Saved credit cards (tokenized)
CREATE TABLE IF NOT EXISTS staff_saved_cards (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    card_token VARCHAR(255) NOT NULL,
    last_four_digits CHAR(4) NOT NULL,
    card_type VARCHAR(20) NOT NULL,
    expiry_month TINYINT NOT NULL,
    expiry_year SMALLINT NOT NULL,
    is_default BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT TRUE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    last_used_at DATETIME NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_is_default (is_default),
    UNIQUE KEY unique_user_token (user_id, card_token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Payment plans (installments)
CREATE TABLE IF NOT EXISTS staff_payment_plans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    total_amount DECIMAL(10,2) NOT NULL,
    installment_amount DECIMAL(10,2) NOT NULL,
    frequency ENUM('weekly', 'fortnightly', 'monthly') NOT NULL,
    total_installments INT NOT NULL,
    completed_installments INT DEFAULT 0,
    next_payment_date DATE NOT NULL,
    status ENUM('active', 'completed', 'cancelled', 'defaulted') DEFAULT 'active',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    completed_at DATETIME NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_status (status),
    INDEX idx_next_payment (next_payment_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Payment plan installments (individual payments)
CREATE TABLE IF NOT EXISTS staff_payment_plan_installments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    plan_id INT NOT NULL,
    installment_number INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    due_date DATE NOT NULL,
    paid_date DATETIME NULL,
    transaction_id VARCHAR(100) NULL,
    status ENUM('pending', 'paid', 'failed', 'skipped') DEFAULT 'pending',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_plan_id (plan_id),
    INDEX idx_status (status),
    INDEX idx_due_date (due_date),
    FOREIGN KEY (plan_id) REFERENCES staff_payment_plans(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add payment fields to existing staff_account_balance table
ALTER TABLE staff_account_balance 
ADD COLUMN IF NOT EXISTS last_payment_date DATETIME NULL AFTER current_balance,
ADD COLUMN IF NOT EXISTS last_payment_amount DECIMAL(10,2) NULL AFTER last_payment_date,
ADD COLUMN IF NOT EXISTS last_payment_transaction_id VARCHAR(100) NULL AFTER last_payment_amount,
ADD COLUMN IF NOT EXISTS total_payments_ytd DECIMAL(10,2) DEFAULT 0 AFTER last_payment_transaction_id,
ADD INDEX idx_last_payment_date (last_payment_date);

-- Nuvei configuration in config table (run these INSERT statements manually with real credentials)
INSERT IGNORE INTO config (setting_key, setting_value, setting_group) VALUES
('nuvei_merchant_id', 'YOUR_MERCHANT_ID', 'payment'),
('nuvei_merchant_site_id', 'YOUR_SITE_ID', 'payment'),
('nuvei_secret_key', 'YOUR_SECRET_KEY', 'payment'),
('nuvei_environment', 'sandbox', 'payment');

-- Indexes for better performance
CREATE INDEX IF NOT EXISTS idx_user_transaction_date 
ON staff_payment_transactions(user_id, created_at DESC);

CREATE INDEX IF NOT EXISTS idx_active_plans 
ON staff_payment_plans(user_id, status);

-- Sample query to get payment summary
-- SELECT 
--     u.first_name, u.last_name,
--     sab.current_balance,
--     sab.last_payment_date,
--     sab.last_payment_amount,
--     COUNT(spt.id) as total_payments,
--     SUM(CASE WHEN spt.transaction_type = 'payment_approved' THEN spt.amount ELSE 0 END) as total_paid
-- FROM users u
-- LEFT JOIN staff_account_balance sab ON u.id = sab.user_id
-- LEFT JOIN staff_payment_transactions spt ON u.id = spt.user_id
-- WHERE u.is_active = 1
-- GROUP BY u.id;
