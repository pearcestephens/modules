-- ============================================================
-- BARCODE ANALYTICS, SECURITY & GAMIFICATION SYSTEM
-- ============================================================
-- Purpose: Track scanning performance, detect fraud, enable competition

-- ============================================================
-- 1. BARCODE SCAN EVENTS (Complete audit trail)
-- ============================================================
CREATE TABLE IF NOT EXISTS BARCODE_SCAN_EVENTS (
    event_id BIGINT AUTO_INCREMENT PRIMARY KEY,

    -- Context
    transfer_id INT NOT NULL,
    transfer_type ENUM('stock_transfer', 'consignment', 'purchase_order') NOT NULL,
    user_id INT NOT NULL,
    outlet_id INT NOT NULL,
    session_id VARCHAR(64) COMMENT 'Browser session for grouping',

    -- Scan Data
    barcode VARCHAR(255) NOT NULL,
    product_id INT NULL COMMENT 'NULL if not found',
    expected_product_id INT NULL COMMENT 'What we expected to scan',
    scan_result ENUM('success', 'not_found', 'wrong_product', 'duplicate', 'invalid') NOT NULL,

    -- Timing (for speed analytics)
    scanned_at TIMESTAMP(3) DEFAULT CURRENT_TIMESTAMP(3) COMMENT 'Millisecond precision',
    time_since_last_scan_ms INT NULL COMMENT 'Milliseconds since previous scan',

    -- Security Flags
    is_suspicious BOOLEAN DEFAULT FALSE,
    fraud_score TINYINT DEFAULT 0 COMMENT '0-100, higher = more suspicious',
    fraud_reasons JSON NULL COMMENT 'Array of detected issues',

    -- Device Info
    device_type ENUM('usb_scanner', 'camera', 'manual', 'mobile') DEFAULT 'usb_scanner',
    user_agent TEXT NULL,
    ip_address VARCHAR(45) NULL,

    INDEX idx_transfer (transfer_id, transfer_type),
    INDEX idx_user_outlet (user_id, outlet_id),
    INDEX idx_scanned_at (scanned_at),
    INDEX idx_suspicious (is_suspicious, fraud_score),
    INDEX idx_session (session_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Complete audit trail of all barcode scans';

-- ============================================================
-- 2. RECEIVING SESSIONS (Track complete receiving process)
-- ============================================================
CREATE TABLE IF NOT EXISTS RECEIVING_SESSIONS (
    session_id INT AUTO_INCREMENT PRIMARY KEY,

    -- Context
    transfer_id INT NOT NULL UNIQUE,
    transfer_type ENUM('stock_transfer', 'consignment', 'purchase_order') NOT NULL,
    user_id INT NOT NULL,
    outlet_id INT NOT NULL,

    -- Session Tracking
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    duration_seconds INT NULL COMMENT 'Total time to complete',
    paused_duration_seconds INT DEFAULT 0 COMMENT 'Time spent paused',

    -- Required Photos
    requires_invoice_photo BOOLEAN DEFAULT FALSE,
    requires_packing_slip_photo BOOLEAN DEFAULT FALSE,
    requires_receipt_photo BOOLEAN DEFAULT FALSE,
    requires_damage_photos BOOLEAN DEFAULT FALSE,

    -- Photo Status
    invoice_photo_id INT NULL,
    packing_slip_photo_id INT NULL,
    receipt_photo_id INT NULL,
    has_all_required_photos BOOLEAN DEFAULT FALSE,

    -- Receiving Stats
    total_items INT NOT NULL,
    items_scanned INT DEFAULT 0,
    items_manual_entry INT DEFAULT 0,
    total_quantity_expected INT NOT NULL,
    total_quantity_received INT DEFAULT 0,
    total_quantity_damaged INT DEFAULT 0,
    total_quantity_missing INT DEFAULT 0,

    -- Performance Metrics
    scans_per_minute DECIMAL(5,2) NULL,
    accuracy_percentage DECIMAL(5,2) NULL COMMENT 'Target: 95%',
    error_count INT DEFAULT 0,
    duplicate_scan_count INT DEFAULT 0,
    wrong_product_count INT DEFAULT 0,

    -- Status
    status ENUM('in_progress', 'paused', 'completed', 'abandoned') DEFAULT 'in_progress',
    completion_type ENUM('full', 'partial', NULL) NULL,

    -- Gamification
    performance_score INT NULL COMMENT '0-100 based on speed + accuracy',
    achievement_badges JSON NULL COMMENT 'Array of earned badges',

    INDEX idx_user_outlet (user_id, outlet_id),
    INDEX idx_started_at (started_at),
    INDEX idx_status (status),
    INDEX idx_performance (accuracy_percentage, scans_per_minute)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Track complete receiving sessions for analytics';

-- ============================================================
-- 3. FRAUD DETECTION RULES
-- ============================================================
CREATE TABLE IF NOT EXISTS FRAUD_DETECTION_RULES (
    rule_id INT AUTO_INCREMENT PRIMARY KEY,

    rule_name VARCHAR(100) NOT NULL UNIQUE,
    rule_description TEXT,
    rule_type ENUM('invalid_barcode', 'timing_anomaly', 'duplicate', 'pattern', 'quantity') NOT NULL,

    -- Rule Configuration (JSON)
    rule_config JSON NOT NULL COMMENT 'Rule-specific parameters',
    /* Example configs:
       invalid_barcode: {"patterns": ["^9999$", "^0+$", "^[0-9]{1,2}$"]}
       timing_anomaly: {"min_ms": 50, "max_scans_per_second": 10}
       duplicate: {"window_seconds": 5}
       pattern: {"suspicious_sequences": ["12345", "11111"]}
       quantity: {"max_per_item": 1000, "total_variance_threshold": 20}
    */

    -- Scoring
    fraud_points INT DEFAULT 10 COMMENT 'Points added to fraud score',
    severity ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',

    -- Actions
    auto_flag BOOLEAN DEFAULT TRUE,
    require_supervisor_approval BOOLEAN DEFAULT FALSE,
    send_alert BOOLEAN DEFAULT FALSE,

    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Configurable fraud detection rules';

-- Insert default fraud rules
INSERT INTO FRAUD_DETECTION_RULES (rule_name, rule_description, rule_type, rule_config, fraud_points, severity) VALUES
('Invalid Barcode Pattern', 'Detects common invalid patterns like 9999, 09, single digits', 'invalid_barcode',
 '{"patterns": ["^9999+$", "^0+[0-9]{1,2}$", "^[0-9]{1,2}$", "^(.)\\\\1+$"]}', 50, 'high'),

('Too Fast Scanning', 'Scans happening faster than humanly possible', 'timing_anomaly',
 '{"min_ms_between_scans": 100, "max_scans_per_second": 8}', 30, 'high'),

('Duplicate Scan', 'Same barcode scanned multiple times in short period', 'duplicate',
 '{"window_seconds": 5, "max_duplicates": 2}', 20, 'medium'),

('Sequential Pattern', 'Suspicious sequential barcodes (12345, 11111, etc)', 'pattern',
 '{"suspicious_sequences": ["12345", "23456", "11111", "22222", "00000"]}', 40, 'high'),

('Excessive Quantity', 'Receiving quantity far exceeds expected', 'quantity',
 '{"max_variance_percentage": 20, "max_single_item": 1000}', 35, 'high');

-- ============================================================
-- 4. STAFF PERFORMANCE TRACKING
-- ============================================================
CREATE TABLE IF NOT EXISTS STAFF_PERFORMANCE_DAILY (
    id INT AUTO_INCREMENT PRIMARY KEY,

    date DATE NOT NULL,
    user_id INT NOT NULL,
    outlet_id INT NOT NULL,

    -- Volume Stats
    transfers_completed INT DEFAULT 0,
    total_items_received INT DEFAULT 0,
    total_quantity_received INT DEFAULT 0,

    -- Speed Metrics
    avg_scans_per_minute DECIMAL(5,2) NULL,
    fastest_transfer_seconds INT NULL,
    slowest_transfer_seconds INT NULL,
    total_receiving_time_seconds INT DEFAULT 0,

    -- Accuracy Metrics
    accuracy_percentage DECIMAL(5,2) NULL,
    error_count INT DEFAULT 0,
    duplicate_count INT DEFAULT 0,
    wrong_product_count INT DEFAULT 0,
    missing_item_count INT DEFAULT 0,
    damage_reports_filed INT DEFAULT 0,

    -- Fraud Indicators
    suspicious_scan_count INT DEFAULT 0,
    total_fraud_score INT DEFAULT 0,
    flagged_transfers INT DEFAULT 0,

    -- Overall Score (for leaderboards)
    performance_score INT NULL COMMENT '0-100 composite score',

    -- Rankings (updated nightly)
    outlet_rank INT NULL,
    company_rank INT NULL,

    UNIQUE KEY unique_user_date (user_id, date),
    INDEX idx_date (date),
    INDEX idx_outlet (outlet_id, date),
    INDEX idx_performance (performance_score DESC),
    INDEX idx_accuracy (accuracy_percentage DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Daily performance metrics per staff member';

-- ============================================================
-- 5. STORE PERFORMANCE TRACKING
-- ============================================================
CREATE TABLE IF NOT EXISTS STORE_PERFORMANCE_WEEKLY (
    id INT AUTO_INCREMENT PRIMARY KEY,

    week_start_date DATE NOT NULL,
    outlet_id INT NOT NULL,

    -- Sending Stats (if this store sent transfers)
    transfers_sent INT DEFAULT 0,
    items_sent INT DEFAULT 0,
    quantity_sent INT DEFAULT 0,

    -- Receiving Stats (if this store received transfers)
    transfers_received INT DEFAULT 0,
    items_received INT DEFAULT 0,
    quantity_received INT DEFAULT 0,

    -- Accuracy (from receiving stores' feedback)
    avg_receiving_accuracy DECIMAL(5,2) NULL COMMENT 'How accurate were our sends?',
    discrepancy_count INT DEFAULT 0,
    damage_reports_received INT DEFAULT 0 COMMENT 'Damages reported by receiving stores',

    -- Speed
    avg_pack_time_hours DECIMAL(5,2) NULL,
    avg_receive_time_minutes DECIMAL(5,2) NULL,

    -- Quality Score (for gamification)
    quality_score INT NULL COMMENT '0-100 composite score',

    -- Rankings
    company_rank_sending INT NULL,
    company_rank_receiving INT NULL,

    UNIQUE KEY unique_outlet_week (outlet_id, week_start_date),
    INDEX idx_week (week_start_date),
    INDEX idx_quality (quality_score DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Weekly store performance for management reviews';

-- ============================================================
-- 6. REQUIRED PHOTOS TRACKING
-- ============================================================
CREATE TABLE IF NOT EXISTS RECEIVING_REQUIRED_PHOTOS (
    id INT AUTO_INCREMENT PRIMARY KEY,

    transfer_id INT NOT NULL,
    transfer_type ENUM('stock_transfer', 'consignment', 'purchase_order') NOT NULL,

    -- Requirements
    requires_invoice BOOLEAN DEFAULT FALSE,
    requires_packing_slip BOOLEAN DEFAULT TRUE COMMENT 'Usually always required',
    requires_receipt BOOLEAN DEFAULT FALSE,
    requires_damage_photos BOOLEAN DEFAULT FALSE,

    -- Fulfillment
    invoice_uploaded_at TIMESTAMP NULL,
    packing_slip_uploaded_at TIMESTAMP NULL,
    receipt_uploaded_at TIMESTAMP NULL,
    all_requirements_met BOOLEAN DEFAULT FALSE,

    -- Enforcement
    can_complete_without_photos BOOLEAN DEFAULT FALSE COMMENT 'Override for emergencies',
    override_reason TEXT NULL,
    override_by_user_id INT NULL,
    override_at TIMESTAMP NULL,

    UNIQUE KEY unique_transfer (transfer_id, transfer_type),
    INDEX idx_incomplete (all_requirements_met, transfer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Track required photos for receiving';

-- ============================================================
-- 7. ACHIEVEMENTS & BADGES
-- ============================================================
CREATE TABLE IF NOT EXISTS ACHIEVEMENTS (
    achievement_id INT AUTO_INCREMENT PRIMARY KEY,

    achievement_code VARCHAR(50) NOT NULL UNIQUE,
    achievement_name VARCHAR(100) NOT NULL,
    achievement_description TEXT,
    badge_icon VARCHAR(100) COMMENT 'Font Awesome class or image path',

    -- Unlock Criteria (JSON)
    criteria JSON NOT NULL COMMENT 'Conditions to earn this badge',
    /* Examples:
       {"type": "accuracy", "threshold": 95, "transfers": 10}
       {"type": "speed", "scans_per_minute": 50, "transfers": 5}
       {"type": "volume", "transfers_in_day": 20}
       {"type": "perfect", "accuracy": 100, "transfers": 3}
       {"type": "streak", "consecutive_days": 7}
    */

    points INT DEFAULT 10 COMMENT 'Points awarded for earning',
    rarity ENUM('common', 'uncommon', 'rare', 'epic', 'legendary') DEFAULT 'common',

    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Defined achievements/badges';

-- Insert sample achievements
INSERT INTO ACHIEVEMENTS (achievement_code, achievement_name, achievement_description, badge_icon, criteria, points, rarity) VALUES
('speed_demon', '⚡ Speed Demon', 'Scan 50+ items per minute', 'fa-bolt', '{"type": "speed", "scans_per_minute": 50, "transfers": 3}', 25, 'uncommon'),
('accuracy_ace', '🎯 Accuracy Ace', 'Achieve 95%+ accuracy on 10 transfers', 'fa-bullseye', '{"type": "accuracy", "threshold": 95, "transfers": 10}', 30, 'uncommon'),
('perfect_score', '💯 Perfect Score', '100% accuracy on a transfer with 20+ items', 'fa-star', '{"type": "perfect", "accuracy": 100, "min_items": 20}', 50, 'rare'),
('workhorse', '🏋️ Workhorse', 'Complete 20 transfers in one day', 'fa-dumbbell', '{"type": "volume", "transfers_in_day": 20}', 40, 'rare'),
('seven_day_streak', '🔥 Week Warrior', 'Receive transfers 7 days in a row', 'fa-fire', '{"type": "streak", "consecutive_days": 7}', 60, 'epic'),
('no_errors', '✨ Flawless', '50 transfers with zero errors', 'fa-gem', '{"type": "flawless", "transfers": 50}', 100, 'legendary');

CREATE TABLE IF NOT EXISTS USER_ACHIEVEMENTS (
    id INT AUTO_INCREMENT PRIMARY KEY,

    user_id INT NOT NULL,
    achievement_id INT NOT NULL,
    earned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    -- Context when earned
    transfer_id INT NULL COMMENT 'Transfer that triggered achievement',
    metric_value DECIMAL(10,2) NULL COMMENT 'e.g., 98.5% accuracy',

    UNIQUE KEY unique_user_achievement (user_id, achievement_id),
    INDEX idx_user (user_id),
    INDEX idx_earned_at (earned_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='User-earned achievements';

-- ============================================================
-- 8. LEADERBOARDS (Daily/Weekly/All-Time)
-- ============================================================
CREATE TABLE IF NOT EXISTS LEADERBOARDS (
    id INT AUTO_INCREMENT PRIMARY KEY,

    period_type ENUM('daily', 'weekly', 'monthly', 'all_time') NOT NULL,
    period_date DATE NOT NULL COMMENT 'Start date of period',

    metric_type ENUM('speed', 'accuracy', 'volume', 'overall') NOT NULL,

    user_id INT NOT NULL,
    outlet_id INT NOT NULL,

    rank_position INT NOT NULL,
    metric_value DECIMAL(10,2) NOT NULL,

    -- Display Info
    display_name VARCHAR(100),
    avatar_url VARCHAR(255) NULL,

    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY unique_leaderboard (period_type, period_date, metric_type, user_id),
    INDEX idx_period_metric (period_type, period_date, metric_type, rank_position)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Leaderboard rankings';

-- ============================================================
-- 9. TRANSFER REVIEWS (From receiving store to sending store)
-- ============================================================
CREATE TABLE IF NOT EXISTS TRANSFER_REVIEWS (
    review_id INT AUTO_INCREMENT PRIMARY KEY,

    transfer_id INT NOT NULL,
    receiving_session_id INT NULL,

    -- Review Details
    reviewed_by_user_id INT NOT NULL,
    reviewed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    -- Ratings (1-5 stars)
    accuracy_rating TINYINT CHECK (accuracy_rating BETWEEN 1 AND 5),
    packing_quality_rating TINYINT CHECK (packing_quality_rating BETWEEN 1 AND 5),
    speed_rating TINYINT CHECK (speed_rating BETWEEN 1 AND 5) COMMENT 'How fast was it sent?',
    overall_rating DECIMAL(2,1) COMMENT 'Average of all ratings',

    -- Feedback
    positive_feedback TEXT NULL,
    negative_feedback TEXT NULL,
    issues_found JSON NULL COMMENT 'Array of specific issues',

    -- Actions
    requires_management_review BOOLEAN DEFAULT FALSE,
    management_reviewed_at TIMESTAMP NULL,

    UNIQUE KEY unique_transfer_review (transfer_id),
    INDEX idx_ratings (overall_rating DESC),
    INDEX idx_requires_review (requires_management_review)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Peer reviews from receiving stores';

-- ============================================================
-- VIEWS FOR EASY QUERYING
-- ============================================================

-- User Performance Summary
CREATE OR REPLACE VIEW V_USER_PERFORMANCE_SUMMARY AS
SELECT
    u.id AS user_id,
    u.full_name,
    u.email,
    spd.outlet_id,
    COUNT(DISTINCT spd.date) AS active_days,
    AVG(spd.accuracy_percentage) AS avg_accuracy,
    AVG(spd.avg_scans_per_minute) AS avg_speed,
    SUM(spd.transfers_completed) AS total_transfers,
    SUM(spd.total_items_received) AS total_items,
    AVG(spd.performance_score) AS avg_performance_score,
    COUNT(ua.achievement_id) AS total_achievements
FROM users u
LEFT JOIN STAFF_PERFORMANCE_DAILY spd ON u.id = spd.user_id
LEFT JOIN USER_ACHIEVEMENTS ua ON u.id = ua.user_id
GROUP BY u.id, u.full_name, u.email, spd.outlet_id;

-- Suspicious Scans Report
CREATE OR REPLACE VIEW V_SUSPICIOUS_SCANS AS
SELECT
    bse.*,
    u.full_name AS user_name,
    o.name AS outlet_name,
    p.product_name
FROM BARCODE_SCAN_EVENTS bse
LEFT JOIN users u ON bse.user_id = u.id
LEFT JOIN outlets o ON bse.outlet_id = o.id
LEFT JOIN products p ON bse.product_id = p.id
WHERE bse.is_suspicious = TRUE
ORDER BY bse.scanned_at DESC;

-- Daily Leaderboard (Top 10)
CREATE OR REPLACE VIEW V_DAILY_LEADERBOARD_TOP10 AS
SELECT
    l.*,
    u.full_name,
    o.name AS outlet_name
FROM LEADERBOARDS l
JOIN users u ON l.user_id = u.id
JOIN outlets o ON l.outlet_id = o.id
WHERE l.period_type = 'daily'
  AND l.period_date = CURDATE()
  AND l.rank_position <= 10
ORDER BY l.metric_type, l.rank_position;
