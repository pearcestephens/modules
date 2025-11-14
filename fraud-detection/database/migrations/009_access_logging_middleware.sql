-- System Access Logging Tables for Digital Twin Profiling

-- Access anomalies table
CREATE TABLE IF NOT EXISTS access_anomalies (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    staff_id INT UNSIGNED NOT NULL,
    anomaly_types JSON NOT NULL COMMENT 'Array of detected anomaly types',
    severity ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
    log_entry JSON NOT NULL COMMENT 'Associated access log entry',
    detected_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    reviewed BOOLEAN DEFAULT FALSE,
    reviewed_by INT UNSIGNED,
    reviewed_at DATETIME,
    notes TEXT,
    INDEX idx_staff_detected (staff_id, detected_at),
    INDEX idx_severity (severity),
    INDEX idx_reviewed (reviewed),
    FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Enhance system_access_log if needed (should already exist from previous migration)
-- Adding additional indexes for performance
ALTER TABLE system_access_log
ADD INDEX IF NOT EXISTS idx_action_accessed (action_type, accessed_at),
ADD INDEX IF NOT EXISTS idx_ip_accessed (ip_address, accessed_at),
ADD INDEX IF NOT EXISTS idx_response_code (response_code);
