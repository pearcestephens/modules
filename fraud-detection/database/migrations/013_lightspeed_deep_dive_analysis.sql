-- =====================================================
-- MIGRATION 013: LIGHTSPEED DEEP DIVE ANALYSIS SCHEMA
-- =====================================================
-- Purpose: Complete POS data analysis covering ALL fraud vectors
-- 
-- Covers 7 major fraud categories:
-- 1. Payment Type Fraud (unusual/random payment types)
-- 2. Customer Account Fraud (fake accounts, credit manipulation)
-- 3. Inventory Movement Fraud (adjustments, transfers, shrinkage)
-- 4. Cash Register Closure Fraud (till discrepancies)
-- 5. Banking & Deposit Fraud (missing deposits, delays)
-- 6. Transaction Manipulation (voids, refunds, discounts)
-- 7. Reconciliation Fraud (daily/weekly gaps)
-- =====================================================

-- Main analysis results table
CREATE TABLE IF NOT EXISTS lightspeed_deep_dive_analysis (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    staff_id INT UNSIGNED NOT NULL,
    analysis_period_days INT NOT NULL,
    risk_score DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    risk_level ENUM('low', 'medium', 'high', 'critical') NOT NULL DEFAULT 'low',
    indicator_count INT NOT NULL DEFAULT 0,
    critical_alert_count INT NOT NULL DEFAULT 0,
    analysis_data JSON NOT NULL COMMENT 'Complete analysis results with all sections',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_staff_date (staff_id, created_at),
    INDEX idx_risk_level (risk_level, risk_score),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Comprehensive Lightspeed/Vend fraud analysis results';

-- =====================================================
-- VEND/LIGHTSPEED DATA TABLES (if not already exist)
-- =====================================================

-- Sales table (main transaction data)
CREATE TABLE IF NOT EXISTS vend_sales (
    id VARCHAR(50) PRIMARY KEY,
    sale_date TIMESTAMP NOT NULL,
    outlet_id VARCHAR(50) NOT NULL,
    register_id VARCHAR(50),
    user_id INT UNSIGNED NOT NULL,
    customer_id VARCHAR(50),
    customer_name VARCHAR(255),
    total_price DECIMAL(10,2) NOT NULL,
    total_tax DECIMAL(10,2) DEFAULT 0,
    total_discount DECIMAL(10,2) DEFAULT 0,
    payment_type VARCHAR(50),
    payment_types JSON COMMENT 'Array of payment types for split payments',
    status ENUM('OPEN', 'CLOSED', 'VOIDED', 'ONACCOUNT', 'LAYBY') NOT NULL,
    voided_at TIMESTAMP NULL,
    store_credit_used DECIMAL(10,2) DEFAULT 0,
    loyalty_points_earned INT DEFAULT 0,
    loyalty_points_redeemed INT DEFAULT 0,
    note TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user_date (user_id, sale_date),
    INDEX idx_outlet_date (outlet_id, sale_date),
    INDEX idx_customer (customer_id),
    INDEX idx_payment_type (payment_type),
    INDEX idx_status (status),
    INDEX idx_voided (voided_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Vend/Lightspeed sales transactions';

-- Sale line items (individual products)
CREATE TABLE IF NOT EXISTS vend_sale_line_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sale_id VARCHAR(50) NOT NULL,
    product_id VARCHAR(50) NOT NULL,
    product_name VARCHAR(255),
    quantity DECIMAL(10,2) NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    cost_price DECIMAL(10,2),
    discount DECIMAL(10,2) DEFAULT 0,
    tax DECIMAL(10,2) DEFAULT 0,
    price_override BOOLEAN DEFAULT FALSE,
    price_override_amount DECIMAL(10,2),
    line_total DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sale_id) REFERENCES vend_sales(id) ON DELETE CASCADE,
    INDEX idx_sale (sale_id),
    INDEX idx_product (product_id),
    INDEX idx_price_override (price_override)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Individual line items from sales';

-- Stock adjustments
CREATE TABLE IF NOT EXISTS vend_stock_adjustments (
    id VARCHAR(50) PRIMARY KEY,
    outlet_id VARCHAR(50) NOT NULL,
    product_id VARCHAR(50) NOT NULL,
    product_name VARCHAR(255),
    adjustment_qty DECIMAL(10,2) NOT NULL,
    cost_price DECIMAL(10,2),
    adjustment_reason VARCHAR(255),
    shrinkage_qty DECIMAL(10,2) DEFAULT 0 COMMENT 'Quantity marked as shrinkage',
    created_by_user_id INT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_date (created_by_user_id, created_at),
    INDEX idx_outlet_product (outlet_id, product_id),
    INDEX idx_reason (adjustment_reason)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Stock adjustments and inventory corrections';

-- Stock transfers (between outlets)
CREATE TABLE IF NOT EXISTS vend_stock_transfers (
    id VARCHAR(50) PRIMARY KEY,
    from_outlet_id VARCHAR(50) NOT NULL,
    to_outlet_id VARCHAR(50) NOT NULL,
    total_items INT NOT NULL,
    total_value DECIMAL(10,2) NOT NULL,
    status ENUM('PENDING', 'SENT', 'RECEIVED', 'CANCELLED') NOT NULL,
    created_by_user_id INT UNSIGNED NOT NULL,
    sent_at TIMESTAMP NULL,
    received_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (created_by_user_id),
    INDEX idx_from_outlet (from_outlet_id),
    INDEX idx_to_outlet (to_outlet_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Stock transfers between outlets';

-- Stock receiving (from suppliers)
CREATE TABLE IF NOT EXISTS vend_stock_receiving (
    id VARCHAR(50) PRIMARY KEY,
    outlet_id VARCHAR(50) NOT NULL,
    supplier_id VARCHAR(50),
    expected_items INT NOT NULL,
    received_items INT NOT NULL,
    discrepancy INT AS (received_items - expected_items) STORED,
    received_by_user_id INT UNSIGNED NOT NULL,
    received_date TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (received_by_user_id),
    INDEX idx_outlet (outlet_id),
    INDEX idx_discrepancy (discrepancy)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Stock receiving from suppliers';

-- Register closures (end-of-day till counts)
CREATE TABLE IF NOT EXISTS vend_register_closures (
    id VARCHAR(50) PRIMARY KEY,
    outlet_id VARCHAR(50) NOT NULL,
    register_id VARCHAR(50) NOT NULL,
    closure_date TIMESTAMP NOT NULL,
    closed_by_user_id INT UNSIGNED NOT NULL,
    expected_cash DECIMAL(10,2) NOT NULL,
    actual_cash DECIMAL(10,2) NOT NULL,
    variance DECIMAL(10,2) AS (actual_cash - expected_cash) STORED,
    float_amount DECIMAL(10,2) NOT NULL COMMENT 'Starting cash float',
    total_sales DECIMAL(10,2) NOT NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_date (closed_by_user_id, closure_date),
    INDEX idx_outlet_date (outlet_id, closure_date),
    INDEX idx_variance (variance)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Daily register closures and till counts';

-- Deposits (banking records)
CREATE TABLE IF NOT EXISTS vend_deposits (
    id VARCHAR(50) PRIMARY KEY,
    outlet_id VARCHAR(50) NOT NULL,
    deposit_date DATE NOT NULL,
    expected_amount DECIMAL(10,2) NOT NULL,
    deposited_amount DECIMAL(10,2) NOT NULL,
    discrepancy DECIMAL(10,2) AS (deposited_amount - expected_amount) STORED,
    bank_name VARCHAR(100),
    deposited_by_user_id INT UNSIGNED NOT NULL,
    created_by_user_id INT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_outlet_date (outlet_id, deposit_date),
    INDEX idx_deposited_by (deposited_by_user_id),
    INDEX idx_created_by (created_by_user_id),
    INDEX idx_discrepancy (discrepancy)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Daily banking deposits';

-- =====================================================
-- FRAUD TRACKING TABLES
-- =====================================================

-- Payment type fraud tracking
CREATE TABLE IF NOT EXISTS payment_type_fraud_tracking (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    staff_id INT UNSIGNED NOT NULL,
    analysis_id INT UNSIGNED NOT NULL,
    payment_type VARCHAR(50) NOT NULL,
    usage_count INT NOT NULL,
    total_amount DECIMAL(10,2) NOT NULL,
    fraud_type ENUM('unusual_payment_type', 'random_payment_type', 'excessive_split', 'abnormal_ratio') NOT NULL,
    severity DECIMAL(3,2) NOT NULL,
    details JSON,
    detected_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    investigated BOOLEAN DEFAULT FALSE,
    investigation_notes TEXT,
    FOREIGN KEY (analysis_id) REFERENCES lightspeed_deep_dive_analysis(id) ON DELETE CASCADE,
    INDEX idx_staff (staff_id),
    INDEX idx_fraud_type (fraud_type),
    INDEX idx_severity (severity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Payment type fraud incidents';

-- Customer account fraud tracking
CREATE TABLE IF NOT EXISTS customer_account_fraud_tracking (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    staff_id INT UNSIGNED NOT NULL,
    analysis_id INT UNSIGNED NOT NULL,
    customer_id VARCHAR(50),
    customer_name VARCHAR(255),
    fraud_type ENUM('excessive_account_sales', 'suspicious_customer', 'store_credit_abuse', 'loyalty_manipulation', 'random_assignment') NOT NULL,
    transaction_count INT NOT NULL,
    total_amount DECIMAL(10,2) NOT NULL,
    severity DECIMAL(3,2) NOT NULL,
    details JSON,
    detected_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    investigated BOOLEAN DEFAULT FALSE,
    investigation_notes TEXT,
    FOREIGN KEY (analysis_id) REFERENCES lightspeed_deep_dive_analysis(id) ON DELETE CASCADE,
    INDEX idx_staff (staff_id),
    INDEX idx_customer (customer_id),
    INDEX idx_fraud_type (fraud_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Customer account fraud incidents';

-- Inventory fraud tracking
CREATE TABLE IF NOT EXISTS inventory_fraud_tracking (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    staff_id INT UNSIGNED NOT NULL,
    analysis_id INT UNSIGNED NOT NULL,
    outlet_id VARCHAR(50),
    product_id VARCHAR(50),
    fraud_type ENUM('large_adjustment', 'adjustment_no_reason', 'unusual_transfer', 'receiving_discrepancy', 'excessive_shrinkage') NOT NULL,
    quantity_affected DECIMAL(10,2),
    value_affected DECIMAL(10,2),
    severity DECIMAL(3,2) NOT NULL,
    details JSON,
    detected_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    investigated BOOLEAN DEFAULT FALSE,
    investigation_notes TEXT,
    FOREIGN KEY (analysis_id) REFERENCES lightspeed_deep_dive_analysis(id) ON DELETE CASCADE,
    INDEX idx_staff (staff_id),
    INDEX idx_outlet (outlet_id),
    INDEX idx_fraud_type (fraud_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Inventory manipulation fraud incidents';

-- Register closure fraud tracking
CREATE TABLE IF NOT EXISTS register_closure_fraud_tracking (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    staff_id INT UNSIGNED NOT NULL,
    analysis_id INT UNSIGNED NOT NULL,
    register_id VARCHAR(50),
    closure_date DATE,
    fraud_type ENUM('cash_shortage', 'cash_overage', 'skimming_pattern', 'float_manipulation') NOT NULL,
    variance_amount DECIMAL(10,2),
    severity DECIMAL(3,2) NOT NULL,
    details JSON,
    detected_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    investigated BOOLEAN DEFAULT FALSE,
    investigation_notes TEXT,
    FOREIGN KEY (analysis_id) REFERENCES lightspeed_deep_dive_analysis(id) ON DELETE CASCADE,
    INDEX idx_staff (staff_id),
    INDEX idx_closure_date (closure_date),
    INDEX idx_fraud_type (fraud_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Register closure fraud incidents';

-- Banking fraud tracking
CREATE TABLE IF NOT EXISTS banking_fraud_tracking (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    staff_id INT UNSIGNED NOT NULL,
    analysis_id INT UNSIGNED NOT NULL,
    outlet_id VARCHAR(50),
    deposit_date DATE,
    fraud_type ENUM('deposit_discrepancy', 'delayed_deposit', 'missing_deposit', 'weekly_reconciliation_gap') NOT NULL,
    amount_affected DECIMAL(10,2),
    severity DECIMAL(3,2) NOT NULL,
    details JSON,
    detected_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    investigated BOOLEAN DEFAULT FALSE,
    investigation_notes TEXT,
    FOREIGN KEY (analysis_id) REFERENCES lightspeed_deep_dive_analysis(id) ON DELETE CASCADE,
    INDEX idx_staff (staff_id),
    INDEX idx_deposit_date (deposit_date),
    INDEX idx_fraud_type (fraud_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Banking and deposit fraud incidents';

-- Transaction manipulation tracking
CREATE TABLE IF NOT EXISTS transaction_manipulation_tracking (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    staff_id INT UNSIGNED NOT NULL,
    analysis_id INT UNSIGNED NOT NULL,
    fraud_type ENUM('excessive_voids', 'immediate_voids', 'excessive_refunds', 'excessive_discounts', 'excessive_price_overrides') NOT NULL,
    transaction_count INT NOT NULL,
    total_amount DECIMAL(10,2),
    percentage DECIMAL(5,2),
    severity DECIMAL(3,2) NOT NULL,
    details JSON,
    detected_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    investigated BOOLEAN DEFAULT FALSE,
    investigation_notes TEXT,
    FOREIGN KEY (analysis_id) REFERENCES lightspeed_deep_dive_analysis(id) ON DELETE CASCADE,
    INDEX idx_staff (staff_id),
    INDEX idx_fraud_type (fraud_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Transaction manipulation fraud incidents';

-- =====================================================
-- VIEWS FOR QUICK ACCESS
-- =====================================================

-- View: Staff with high risk scores
CREATE OR REPLACE VIEW v_high_risk_staff_lightspeed AS
SELECT 
    a.staff_id,
    s.name as staff_name,
    a.risk_score,
    a.risk_level,
    a.indicator_count,
    a.critical_alert_count,
    a.created_at as last_analysis,
    COUNT(DISTINCT pt.id) as payment_fraud_count,
    COUNT(DISTINCT ca.id) as customer_fraud_count,
    COUNT(DISTINCT inv.id) as inventory_fraud_count,
    COUNT(DISTINCT rc.id) as register_fraud_count,
    COUNT(DISTINCT b.id) as banking_fraud_count,
    COUNT(DISTINCT tm.id) as manipulation_fraud_count
FROM lightspeed_deep_dive_analysis a
LEFT JOIN staff_accounts s ON a.staff_id = s.id
LEFT JOIN payment_type_fraud_tracking pt ON a.id = pt.analysis_id AND pt.investigated = FALSE
LEFT JOIN customer_account_fraud_tracking ca ON a.id = ca.analysis_id AND ca.investigated = FALSE
LEFT JOIN inventory_fraud_tracking inv ON a.id = inv.analysis_id AND inv.investigated = FALSE
LEFT JOIN register_closure_fraud_tracking rc ON a.id = rc.analysis_id AND rc.investigated = FALSE
LEFT JOIN banking_fraud_tracking b ON a.id = b.analysis_id AND b.investigated = FALSE
LEFT JOIN transaction_manipulation_tracking tm ON a.id = tm.analysis_id AND tm.investigated = FALSE
WHERE a.risk_level IN ('high', 'critical')
GROUP BY a.staff_id, s.name, a.risk_score, a.risk_level, a.indicator_count, a.critical_alert_count, a.created_at
ORDER BY a.risk_score DESC;

-- View: Uninvestigated fraud incidents
CREATE OR REPLACE VIEW v_uninvestigated_fraud_incidents AS
SELECT 'payment_type' as fraud_category, id, staff_id, fraud_type, severity, detected_at 
FROM payment_type_fraud_tracking WHERE investigated = FALSE
UNION ALL
SELECT 'customer_account' as fraud_category, id, staff_id, fraud_type, severity, detected_at 
FROM customer_account_fraud_tracking WHERE investigated = FALSE
UNION ALL
SELECT 'inventory' as fraud_category, id, staff_id, fraud_type, severity, detected_at 
FROM inventory_fraud_tracking WHERE investigated = FALSE
UNION ALL
SELECT 'register_closure' as fraud_category, id, staff_id, fraud_type, severity, detected_at 
FROM register_closure_fraud_tracking WHERE investigated = FALSE
UNION ALL
SELECT 'banking' as fraud_category, id, staff_id, fraud_type, severity, detected_at 
FROM banking_fraud_tracking WHERE investigated = FALSE
UNION ALL
SELECT 'transaction_manipulation' as fraud_category, id, staff_id, fraud_type, severity, detected_at 
FROM transaction_manipulation_tracking WHERE investigated = FALSE
ORDER BY severity DESC, detected_at DESC;

-- View: Recent cash shortages (CRITICAL)
CREATE OR REPLACE VIEW v_cash_shortage_alerts AS
SELECT 
    rc.staff_id,
    s.name as staff_name,
    rc.register_id,
    rc.closure_date,
    rc.variance_amount,
    rc.severity,
    rc.details,
    rc.detected_at
FROM register_closure_fraud_tracking rc
LEFT JOIN staff_accounts s ON rc.staff_id = s.id
WHERE rc.fraud_type = 'cash_shortage'
AND rc.investigated = FALSE
AND rc.severity >= 0.8
ORDER BY rc.detected_at DESC;

-- =====================================================
-- DEPLOYMENT VERIFICATION
-- =====================================================

-- Verify all tables created
SELECT 
    'lightspeed_deep_dive_analysis' as table_name,
    COUNT(*) as record_count 
FROM lightspeed_deep_dive_analysis
UNION ALL
SELECT 'vend_sales', COUNT(*) FROM vend_sales
UNION ALL
SELECT 'vend_stock_adjustments', COUNT(*) FROM vend_stock_adjustments
UNION ALL
SELECT 'payment_type_fraud_tracking', COUNT(*) FROM payment_type_fraud_tracking
UNION ALL
SELECT 'customer_account_fraud_tracking', COUNT(*) FROM customer_account_fraud_tracking
UNION ALL
SELECT 'inventory_fraud_tracking', COUNT(*) FROM inventory_fraud_tracking
UNION ALL
SELECT 'register_closure_fraud_tracking', COUNT(*) FROM register_closure_fraud_tracking
UNION ALL
SELECT 'banking_fraud_tracking', COUNT(*) FROM banking_fraud_tracking
UNION ALL
SELECT 'transaction_manipulation_tracking', COUNT(*) FROM transaction_manipulation_tracking;

-- Migration complete
SELECT 'Migration 013: Lightspeed Deep Dive Analysis - COMPLETE' as status;
