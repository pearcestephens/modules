-- Migration 011: Fraud Analysis Results & Multi-Source Integration

-- Fraud analysis results storage
CREATE TABLE IF NOT EXISTS fraud_analysis_results (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    staff_id INT UNSIGNED NOT NULL,

    -- Fraud scoring
    fraud_score DECIMAL(5,2) DEFAULT 0.00,
    -- 0.00 to 100.00

    risk_level ENUM('low', 'medium', 'high', 'critical') DEFAULT 'low',

    -- Analysis metrics
    indicators_count INT UNSIGNED DEFAULT 0,
    sources_analyzed JSON,
    -- ["lightspeed_transactions", "cis_cash_activity", "security_events", ...]

    -- Full analysis data (JSON)
    analysis_data JSON,
    -- {
    --   "fraud_indicators": [...],
    --   "recommendations": [...],
    --   "sources_analyzed": [...],
    --   ...
    -- }

    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

    -- Indexes
    INDEX idx_staff_id (staff_id),
    INDEX idx_fraud_score (fraud_score),
    INDEX idx_risk_level (risk_level),
    INDEX idx_created_at (created_at),
    INDEX idx_high_risk (risk_level, fraud_score, created_at),

    FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- CIS Cash Register Reconciliation (if not exists)
CREATE TABLE IF NOT EXISTS cash_register_reconciliation (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    staff_id INT UNSIGNED NOT NULL,
    outlet_id INT UNSIGNED NOT NULL,

    -- Cash count data
    expected_amount DECIMAL(10,2) NOT NULL,
    actual_amount DECIMAL(10,2) NOT NULL,
    variance_amount DECIMAL(10,2) NOT NULL,
    -- Negative = shortage, Positive = overage

    -- Breakdown (JSON)
    denomination_breakdown JSON,
    -- {"100": 5, "50": 10, "20": 20, ...}

    notes TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_staff_id (staff_id),
    INDEX idx_outlet_id (outlet_id),
    INDEX idx_variance (variance_amount),
    INDEX idx_created_at (created_at),

    FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- CIS Store Deposits
CREATE TABLE IF NOT EXISTS store_deposits (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    staff_id INT UNSIGNED NOT NULL,
    outlet_id INT UNSIGNED NOT NULL,

    -- Deposit data
    deposit_amount DECIMAL(10,2) NOT NULL,
    expected_amount DECIMAL(10,2),
    discrepancy_amount DECIMAL(10,2) DEFAULT 0.00,

    deposit_date DATE NOT NULL,
    bank_name VARCHAR(100),
    deposit_slip_number VARCHAR(50),

    -- Verification
    verified_by INT UNSIGNED,
    verified_at DATETIME,

    notes TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_staff_id (staff_id),
    INDEX idx_outlet_id (outlet_id),
    INDEX idx_deposit_date (deposit_date),
    INDEX idx_discrepancy (discrepancy_amount),

    FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- CIS Banking Transactions
CREATE TABLE IF NOT EXISTS banking_transactions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    staff_id INT UNSIGNED NOT NULL,
    outlet_id INT UNSIGNED NOT NULL,

    -- Transaction data
    transaction_type ENUM('deposit', 'withdrawal', 'transfer', 'reconciliation') NOT NULL,
    amount DECIMAL(10,2) NOT NULL,

    transaction_date DATETIME NOT NULL,
    reference_number VARCHAR(100),

    -- Flagging
    is_flagged BOOLEAN DEFAULT FALSE,
    flag_reason TEXT,

    metadata JSON,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_staff_id (staff_id),
    INDEX idx_outlet_id (outlet_id),
    INDEX idx_transaction_type (transaction_type),
    INDEX idx_transaction_date (transaction_date),
    INDEX idx_is_flagged (is_flagged),

    FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Outlet Email Monitoring (Future feature)
CREATE TABLE IF NOT EXISTS outlet_email_monitoring (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    outlet_id INT UNSIGNED NOT NULL,
    email_address VARCHAR(255) NOT NULL,

    -- Email data
    subject VARCHAR(500),
    sender VARCHAR(255),
    recipient VARCHAR(255),
    received_at DATETIME NOT NULL,

    -- Content analysis
    content_excerpt TEXT,
    suspicious_keywords JSON,
    -- ["refund", "void", "cash", "discount", ...]

    alert_level ENUM('low', 'medium', 'high', 'critical') DEFAULT 'low',

    -- Staff correlation
    mentioned_staff JSON,
    -- [{"staff_id": 5, "context": "..."}]

    processed_at DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_outlet_id (outlet_id),
    INDEX idx_received_at (received_at),
    INDEX idx_alert_level (alert_level),
    INDEX idx_processed_at (processed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Fraud detection configuration
CREATE TABLE IF NOT EXISTS fraud_detection_config (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    config_key VARCHAR(100) UNIQUE NOT NULL,
    config_value TEXT NOT NULL,
    config_type ENUM('threshold', 'enable', 'schedule', 'alert') NOT NULL,

    description TEXT,

    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_config_type (config_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default fraud detection configuration
INSERT INTO fraud_detection_config (config_key, config_value, config_type, description) VALUES
('void_threshold_per_day', '3', 'threshold', 'Max voids per day before flagging'),
('refund_threshold_per_week', '5', 'threshold', 'Max refunds per week before flagging'),
('discount_threshold_percent', '15', 'threshold', 'Max average discount % before flagging'),
('cash_shortage_threshold', '50', 'threshold', 'Cash shortage amount ($) before flagging'),
('after_hours_minutes', '30', 'threshold', 'Minutes after closing for after-hours detection'),
('enable_email_scanning', 'false', 'enable', 'Enable outlet email monitoring'),
('enable_real_time_alerts', 'true', 'enable', 'Enable real-time fraud alerts'),
('analysis_window_days', '30', 'threshold', 'Days to analyze for fraud patterns'),
('confidence_threshold', '0.75', 'threshold', 'Minimum confidence for fraud detection (0-1)')
ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP;

-- Add comments
ALTER TABLE fraud_analysis_results COMMENT = 'Stores comprehensive fraud analysis results';
ALTER TABLE cash_register_reconciliation COMMENT = 'CIS cash register cash-up records';
ALTER TABLE store_deposits COMMENT = 'CIS store deposit records';
ALTER TABLE banking_transactions COMMENT = 'CIS banking transaction records';
ALTER TABLE outlet_email_monitoring COMMENT = 'Outlet email inbox monitoring (future)';
ALTER TABLE fraud_detection_config COMMENT = 'Fraud detection system configuration';
