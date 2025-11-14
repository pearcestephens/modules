-- Migration 010: Security System Events Storage
-- Stores events from external security/CCTV system for fraud detection

-- Security events table
CREATE TABLE IF NOT EXISTS security_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    -- Event identification
    event_type VARCHAR(100) NOT NULL,
    -- motion_detected, person_detected, suspicious_activity,
    -- after_hours_motion, restricted_area_breach, loitering_detected, etc.

    -- Camera information
    camera_id VARCHAR(100) NOT NULL,
    camera_name VARCHAR(255),

    -- Location
    outlet_id INT UNSIGNED,
    zone VARCHAR(50),
    -- checkout, stockroom, entrance, parking, office, safe, etc.

    -- Alert data
    alert_level ENUM('low', 'medium', 'high', 'critical') DEFAULT 'low',
    confidence DECIMAL(5,4) DEFAULT 0.0,
    -- 0.0 to 1.0 confidence score

    -- Detection data (JSON)
    detection_data JSON,
    -- {
    --   "person_count": 2,
    --   "tracked_objects": [...],
    --   "frame_url": "https://...",
    --   "video_clip_url": "https://..."
    -- }

    -- Additional metadata (JSON)
    metadata JSON,

    -- Timestamps
    event_timestamp DATETIME NOT NULL,
    received_at DATETIME NOT NULL,

    -- Indexes
    INDEX idx_camera_id (camera_id),
    INDEX idx_outlet_id (outlet_id),
    INDEX idx_event_type (event_type),
    INDEX idx_alert_level (alert_level),
    INDEX idx_zone (zone),
    INDEX idx_event_timestamp (event_timestamp),
    INDEX idx_received_at (received_at),
    INDEX idx_composite_analysis (outlet_id, event_timestamp, alert_level)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Security event staff correlation table
-- Links security events to staff members detected nearby
CREATE TABLE IF NOT EXISTS security_event_staff_correlation (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    security_event_id BIGINT UNSIGNED NOT NULL,
    staff_id INT UNSIGNED NOT NULL,

    -- Correlation metrics
    correlation_type ENUM('location', 'badge_scan', 'manual') DEFAULT 'location',
    confidence DECIMAL(5,4) DEFAULT 0.0,

    -- Distance/proximity
    distance_meters INT UNSIGNED,
    -- Distance between staff location and camera at event time

    -- Timing
    time_difference_seconds INT,
    -- How many seconds between staff location ping and event

    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

    -- Indexes
    INDEX idx_security_event (security_event_id),
    INDEX idx_staff (staff_id),
    INDEX idx_correlation_type (correlation_type),

    FOREIGN KEY (security_event_id) REFERENCES security_events(id) ON DELETE CASCADE,
    FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Camera network directory
-- Maps cameras to outlets/zones for correlation
CREATE TABLE IF NOT EXISTS camera_network (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    camera_id VARCHAR(100) UNIQUE NOT NULL,
    camera_name VARCHAR(255) NOT NULL,

    outlet_id INT UNSIGNED,
    zone VARCHAR(50),

    -- Camera location
    latitude DECIMAL(10,8),
    longitude DECIMAL(11,8),

    -- Camera capabilities
    has_person_detection BOOLEAN DEFAULT FALSE,
    has_motion_detection BOOLEAN DEFAULT FALSE,
    has_facial_recognition BOOLEAN DEFAULT FALSE,

    -- Status
    is_active BOOLEAN DEFAULT TRUE,
    last_seen DATETIME,

    -- Configuration (JSON)
    configuration JSON,

    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Indexes
    INDEX idx_outlet_id (outlet_id),
    INDEX idx_zone (zone),
    INDEX idx_is_active (is_active),
    INDEX idx_capabilities (has_person_detection, has_motion_detection)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Security event alerts (for notifications/escalation)
CREATE TABLE IF NOT EXISTS security_event_alerts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    security_event_id BIGINT UNSIGNED NOT NULL,

    -- Alert details
    alert_type ENUM('email', 'sms', 'push', 'internal') NOT NULL,
    recipient VARCHAR(255),
    -- Email, phone number, or staff ID

    message TEXT,

    -- Status
    sent_at DATETIME,
    acknowledged_at DATETIME,
    acknowledged_by INT UNSIGNED,

    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

    -- Indexes
    INDEX idx_security_event (security_event_id),
    INDEX idx_sent_at (sent_at),
    INDEX idx_acknowledged (acknowledged_at),

    FOREIGN KEY (security_event_id) REFERENCES security_events(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Security event patterns (for ML/analysis)
CREATE TABLE IF NOT EXISTS security_event_patterns (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    -- Pattern identification
    pattern_type VARCHAR(100) NOT NULL,
    -- repeat_offender, time_clustering, zone_anomaly, etc.

    pattern_name VARCHAR(255) NOT NULL,
    description TEXT,

    -- Pattern criteria (JSON)
    criteria JSON,
    -- {
    --   "min_occurrences": 3,
    --   "time_window_hours": 24,
    --   "zones": ["stockroom", "office"],
    --   "event_types": ["after_hours_motion"]
    -- }

    -- Actions (JSON)
    actions JSON,
    -- {
    --   "alert": ["manager@email.com"],
    --   "trigger_fraud_analysis": true,
    --   "escalate_to": "security_team"
    -- }

    -- Status
    is_active BOOLEAN DEFAULT TRUE,

    -- Statistics
    times_triggered INT UNSIGNED DEFAULT 0,
    last_triggered DATETIME,

    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Indexes
    INDEX idx_pattern_type (pattern_type),
    INDEX idx_is_active (is_active),
    INDEX idx_last_triggered (last_triggered)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert sample camera network data (example)
INSERT INTO camera_network (camera_id, camera_name, outlet_id, zone, has_person_detection, has_motion_detection, is_active) VALUES
('camera_001', 'Store 1 - Main Entrance', 1, 'entrance', TRUE, TRUE, TRUE),
('camera_002', 'Store 1 - Checkout Area', 1, 'checkout', TRUE, TRUE, TRUE),
('camera_003', 'Store 1 - Stockroom', 1, 'stockroom', TRUE, TRUE, TRUE),
('camera_004', 'Store 1 - Office', 1, 'office', TRUE, TRUE, TRUE),
('camera_005', 'Store 1 - Parking Lot', 1, 'parking', TRUE, TRUE, TRUE)
ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP;

-- Insert sample security event patterns
INSERT INTO security_event_patterns (pattern_type, pattern_name, description, criteria, actions, is_active) VALUES
(
    'after_hours_stockroom',
    'After-Hours Stockroom Access',
    'Detects staff accessing stockroom outside business hours',
    JSON_OBJECT(
        'min_occurrences', 1,
        'zones', JSON_ARRAY('stockroom'),
        'event_types', JSON_ARRAY('person_detected', 'motion_detected'),
        'time_conditions', JSON_OBJECT('before_hour', 6, 'after_hour', 22)
    ),
    JSON_OBJECT(
        'trigger_fraud_analysis', TRUE,
        'alert_priority', 'high',
        'notify', JSON_ARRAY('security@company.com', 'manager@company.com')
    ),
    TRUE
),
(
    'repeat_restricted_area',
    'Repeated Restricted Area Access',
    'Multiple visits to restricted zones in short timeframe',
    JSON_OBJECT(
        'min_occurrences', 3,
        'time_window_hours', 4,
        'zones', JSON_ARRAY('office', 'safe', 'server_room'),
        'event_types', JSON_ARRAY('person_detected')
    ),
    JSON_OBJECT(
        'trigger_fraud_analysis', TRUE,
        'alert_priority', 'critical',
        'escalate_to', 'security_team'
    ),
    TRUE
)
ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP;

-- Add comment
ALTER TABLE security_events COMMENT = 'Stores events from external security/CCTV system';
ALTER TABLE security_event_staff_correlation COMMENT = 'Links security events to staff members';
ALTER TABLE camera_network COMMENT = 'Directory of cameras mapped to outlets/zones';
ALTER TABLE security_event_alerts COMMENT = 'Alerts generated from security events';
ALTER TABLE security_event_patterns COMMENT = 'Pattern definitions for automated detection';
