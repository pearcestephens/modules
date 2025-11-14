-- Migration 012: Advanced Camera-Transaction Correlation System

-- Camera-Transaction Correlation Log
-- Stores detailed correlation analysis results
CREATE TABLE IF NOT EXISTS camera_transaction_correlation_log (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    staff_id INT UNSIGNED NOT NULL,
    analysis_period_days INT UNSIGNED NOT NULL,

    -- Correlation metrics
    correlation_score DECIMAL(5,2) DEFAULT 0.00,
    -- 0-100 score based on camera confirmation rate

    risk_level ENUM('low', 'medium', 'high', 'critical') DEFAULT 'low',

    -- Summary statistics
    total_transactions INT UNSIGNED DEFAULT 0,
    camera_confirmed INT UNSIGNED DEFAULT 0,
    camera_missing INT UNSIGNED DEFAULT 0,
    suspicious_patterns INT UNSIGNED DEFAULT 0,
    ghost_transactions INT UNSIGNED DEFAULT 0,
    ghost_presence INT UNSIGNED DEFAULT 0,

    -- Full analysis data (JSON)
    correlation_data JSON,
    -- {
    --   "correlations": [...],
    --   "mismatches": [...],
    --   "fraud_indicators": [...],
    --   "summary": {...}
    -- }

    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

    -- Indexes
    INDEX idx_staff_id (staff_id),
    INDEX idx_correlation_score (correlation_score),
    INDEX idx_risk_level (risk_level),
    INDEX idx_created_at (created_at),
    INDEX idx_high_risk (risk_level, correlation_score),
    INDEX idx_staff_analysis (staff_id, created_at),

    FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Transaction-Camera Mismatch Details
-- Stores specific instances of mismatches for investigation
CREATE TABLE IF NOT EXISTS transaction_camera_mismatches (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    staff_id INT UNSIGNED NOT NULL,

    -- Mismatch type
    mismatch_type ENUM(
        'ghost_transaction',
        'ghost_presence',
        'no_camera_coverage',
        'low_confidence_detection',
        'multiple_people_at_till',
        'high_value_low_confidence',
        'login_without_presence',
        'suspicious_login_ip',
        'cash_no_camera',
        'cash_ghost_transaction',
        'location_mismatch',
        'impossible_movement',
        'ghost_transaction_pattern',
        'repeated_multi_person_pattern'
    ) NOT NULL,

    -- Transaction data
    transaction_id BIGINT UNSIGNED,
    transaction_date DATETIME,
    transaction_amount DECIMAL(10,2),
    outlet_id INT UNSIGNED,

    -- Camera data
    camera_event_id BIGINT UNSIGNED,
    camera_id VARCHAR(100),
    camera_detection_time DATETIME,

    -- Mismatch details
    description TEXT,
    severity DECIMAL(3,2) DEFAULT 0.00,
    -- 0.00 to 1.00

    -- Evidence (JSON)
    evidence JSON,

    -- Investigation
    investigated BOOLEAN DEFAULT FALSE,
    investigated_by INT UNSIGNED,
    investigated_at DATETIME,
    investigation_notes TEXT,
    resolution ENUM('false_positive', 'legitimate', 'fraud_confirmed', 'pending') DEFAULT 'pending',

    detected_at DATETIME NOT NULL,

    -- Indexes
    INDEX idx_staff_id (staff_id),
    INDEX idx_mismatch_type (mismatch_type),
    INDEX idx_severity (severity),
    INDEX idx_detected_at (detected_at),
    INDEX idx_outlet_id (outlet_id),
    INDEX idx_investigated (investigated, resolution),
    INDEX idx_high_severity (severity, investigated),

    FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE CASCADE,
    FOREIGN KEY (camera_event_id) REFERENCES security_events(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Cash Transaction Camera Verification Log
-- CRITICAL: Every cash transaction MUST have camera verification
CREATE TABLE IF NOT EXISTS cash_transaction_camera_verification (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    transaction_id BIGINT UNSIGNED NOT NULL,
    staff_id INT UNSIGNED NOT NULL,

    transaction_date DATETIME NOT NULL,
    transaction_amount DECIMAL(10,2) NOT NULL,
    outlet_id INT UNSIGNED NOT NULL,
    register_id VARCHAR(50),

    -- Camera verification
    camera_verified BOOLEAN DEFAULT FALSE,
    camera_id VARCHAR(100),
    camera_event_id BIGINT UNSIGNED,
    verification_confidence DECIMAL(5,4) DEFAULT 0.0000,

    -- Person detection details
    person_count INT UNSIGNED DEFAULT 0,
    detection_timestamp DATETIME,
    time_diff_seconds INT,

    -- Manual verification (if camera failed)
    manual_verification BOOLEAN DEFAULT FALSE,
    verified_by INT UNSIGNED,
    verified_at DATETIME,
    verification_notes TEXT,

    -- Alert status
    alert_triggered BOOLEAN DEFAULT FALSE,
    alert_sent_at DATETIME,

    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

    -- Indexes
    INDEX idx_transaction_id (transaction_id),
    INDEX idx_staff_id (staff_id),
    INDEX idx_camera_verified (camera_verified),
    INDEX idx_outlet_date (outlet_id, transaction_date),
    INDEX idx_unverified_cash (camera_verified, alert_triggered),
    INDEX idx_high_value (transaction_amount, camera_verified),

    FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE CASCADE,
    FOREIGN KEY (camera_event_id) REFERENCES security_events(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Staff Login Camera Correlation
-- Tracks if staff physical presence matches login/logout events
CREATE TABLE IF NOT EXISTS staff_login_camera_correlation (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    staff_id INT UNSIGNED NOT NULL,

    -- Login event
    login_action ENUM('login', 'logout', 'clock_in', 'clock_out') NOT NULL,
    login_timestamp DATETIME NOT NULL,
    outlet_id INT UNSIGNED NOT NULL,

    -- Network data
    ip_address VARCHAR(45),
    user_agent TEXT,
    is_suspicious_ip BOOLEAN DEFAULT FALSE,

    -- Camera correlation
    camera_presence_detected BOOLEAN DEFAULT FALSE,
    camera_event_ids JSON,
    -- Array of security_event IDs that detected this staff

    detection_confidence DECIMAL(5,4) DEFAULT 0.0000,
    time_diff_minutes INT,

    -- Mismatch flag
    is_mismatch BOOLEAN DEFAULT FALSE,
    mismatch_reason TEXT,

    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

    -- Indexes
    INDEX idx_staff_id (staff_id),
    INDEX idx_login_timestamp (login_timestamp),
    INDEX idx_outlet_id (outlet_id),
    INDEX idx_is_mismatch (is_mismatch),
    INDEX idx_suspicious (is_suspicious_ip, camera_presence_detected),

    FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Register Camera Mapping
-- Maps specific registers to specific cameras for precise correlation
CREATE TABLE IF NOT EXISTS register_camera_mapping (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    outlet_id INT UNSIGNED NOT NULL,
    register_id VARCHAR(50) NOT NULL,
    register_name VARCHAR(255),

    camera_id VARCHAR(100) NOT NULL,
    camera_name VARCHAR(255),

    -- Coverage details
    coverage_quality ENUM('excellent', 'good', 'fair', 'poor') DEFAULT 'good',
    has_clear_view BOOLEAN DEFAULT TRUE,
    can_see_cash_exchange BOOLEAN DEFAULT FALSE,
    can_see_screen BOOLEAN DEFAULT FALSE,

    -- Status
    is_active BOOLEAN DEFAULT TRUE,

    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Indexes
    INDEX idx_outlet_id (outlet_id),
    INDEX idx_register_id (register_id),
    INDEX idx_camera_id (camera_id),
    INDEX idx_is_active (is_active),
    UNIQUE KEY unique_register (outlet_id, register_id),

    FOREIGN KEY (camera_id) REFERENCES camera_network(camera_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Outlet Network IP Ranges
-- Defines legitimate IP ranges for each outlet to detect suspicious logins
CREATE TABLE IF NOT EXISTS outlet_network_ip_ranges (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    outlet_id INT UNSIGNED NOT NULL,

    ip_range_start VARCHAR(45) NOT NULL,
    ip_range_end VARCHAR(45) NOT NULL,

    network_type ENUM('outlet_wifi', 'outlet_lan', 'vpn', 'corporate') DEFAULT 'outlet_wifi',

    description VARCHAR(255),

    is_active BOOLEAN DEFAULT TRUE,

    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Indexes
    INDEX idx_outlet_id (outlet_id),
    INDEX idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ghost Transaction Patterns
-- Tracks patterns of transactions without camera confirmation
CREATE TABLE IF NOT EXISTS ghost_transaction_patterns (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    staff_id INT UNSIGNED NOT NULL,

    -- Pattern detection
    detection_date DATE NOT NULL,
    pattern_type ENUM(
        'consistent_ghost_transactions',
        'periodic_ghost_transactions',
        'high_value_ghost_transactions',
        'cash_ghost_transactions',
        'after_hours_ghost_transactions'
    ) NOT NULL,

    -- Pattern metrics
    occurrence_count INT UNSIGNED DEFAULT 0,
    total_amount DECIMAL(10,2) DEFAULT 0.00,

    -- Time range
    first_occurrence DATETIME,
    last_occurrence DATETIME,

    -- Pattern details (JSON)
    pattern_data JSON,

    -- Investigation
    investigated BOOLEAN DEFAULT FALSE,
    investigation_notes TEXT,

    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

    -- Indexes
    INDEX idx_staff_id (staff_id),
    INDEX idx_detection_date (detection_date),
    INDEX idx_pattern_type (pattern_type),
    INDEX idx_investigated (investigated),

    FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add comments
ALTER TABLE camera_transaction_correlation_log COMMENT = 'Advanced camera-transaction correlation analysis results';
ALTER TABLE transaction_camera_mismatches COMMENT = 'Specific instances of camera-transaction mismatches for investigation';
ALTER TABLE cash_transaction_camera_verification COMMENT = 'CRITICAL: Camera verification log for all cash transactions';
ALTER TABLE staff_login_camera_correlation COMMENT = 'Correlates staff logins with physical presence detection';
ALTER TABLE register_camera_mapping COMMENT = 'Maps registers to cameras for precise transaction correlation';
ALTER TABLE outlet_network_ip_ranges COMMENT = 'Legitimate IP ranges for outlets to detect suspicious logins';
ALTER TABLE ghost_transaction_patterns COMMENT = 'Tracks patterns of transactions without camera confirmation';

-- Create views for quick analysis

-- View: Unverified Cash Transactions (CRITICAL)
CREATE OR REPLACE VIEW v_unverified_cash_transactions AS
SELECT
    ctcv.id,
    ctcv.transaction_id,
    ctcv.staff_id,
    s.name as staff_name,
    ctcv.transaction_date,
    ctcv.transaction_amount,
    ctcv.outlet_id,
    o.name as outlet_name,
    ctcv.camera_verified,
    ctcv.alert_triggered,
    DATEDIFF(NOW(), ctcv.transaction_date) as days_ago
FROM cash_transaction_camera_verification ctcv
JOIN staff s ON ctcv.staff_id = s.id
JOIN outlets o ON ctcv.outlet_id = o.id
WHERE ctcv.camera_verified = FALSE
AND ctcv.manual_verification = FALSE
ORDER BY ctcv.transaction_date DESC;

-- View: High-Risk Staff (based on correlation score)
CREATE OR REPLACE VIEW v_high_risk_staff_correlation AS
SELECT
    ctcl.staff_id,
    s.name as staff_name,
    ctcl.correlation_score,
    ctcl.risk_level,
    ctcl.total_transactions,
    ctcl.camera_confirmed,
    ctcl.suspicious_patterns,
    ctcl.ghost_transactions,
    ctcl.created_at as last_analysis
FROM camera_transaction_correlation_log ctcl
JOIN staff s ON ctcl.staff_id = s.id
WHERE ctcl.risk_level IN ('high', 'critical')
OR ctcl.correlation_score < 60
ORDER BY ctcl.correlation_score ASC, ctcl.suspicious_patterns DESC;

-- View: Pending Investigations
CREATE OR REPLACE VIEW v_pending_mismatch_investigations AS
SELECT
    tcm.id,
    tcm.staff_id,
    s.name as staff_name,
    tcm.mismatch_type,
    tcm.transaction_date,
    tcm.transaction_amount,
    tcm.outlet_id,
    o.name as outlet_name,
    tcm.severity,
    tcm.detected_at,
    DATEDIFF(NOW(), tcm.detected_at) as days_pending
FROM transaction_camera_mismatches tcm
JOIN staff s ON tcm.staff_id = s.id
LEFT JOIN outlets o ON tcm.outlet_id = o.id
WHERE tcm.investigated = FALSE
AND tcm.severity >= 0.7
ORDER BY tcm.severity DESC, tcm.detected_at ASC;
