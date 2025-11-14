-- Staff Location Tracking Tables
-- Support for multi-source location detection with confidence scoring

-- Badge scan tracking
CREATE TABLE IF NOT EXISTS badge_scans (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    staff_id INT UNSIGNED NOT NULL,
    outlet_id INT UNSIGNED NOT NULL,
    scan_type ENUM('in', 'out', 'break') NOT NULL DEFAULT 'in',
    badge_id VARCHAR(100),
    scan_time DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    device_id VARCHAR(100),
    INDEX idx_staff_time (staff_id, scan_time),
    INDEX idx_outlet_time (outlet_id, scan_time),
    FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE CASCADE,
    FOREIGN KEY (outlet_id) REFERENCES outlets(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Staff location history for analytics
CREATE TABLE IF NOT EXISTS staff_location_history (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    staff_id INT UNSIGNED NOT NULL,
    outlet_id INT UNSIGNED NOT NULL,
    outlet_name VARCHAR(255) NOT NULL,
    confidence DECIMAL(3, 2) NOT NULL COMMENT 'Location confidence 0.00-1.00',
    source ENUM('badge_system', 'deputy_api', 'last_known', 'default_outlet') NOT NULL,
    recorded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    metadata JSON COMMENT 'Additional location context',
    INDEX idx_staff_recorded (staff_id, recorded_at),
    INDEX idx_outlet_recorded (outlet_id, recorded_at),
    INDEX idx_confidence (confidence),
    FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE CASCADE,
    FOREIGN KEY (outlet_id) REFERENCES outlets(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Deputy location to outlet mapping
CREATE TABLE IF NOT EXISTS deputy_location_mapping (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    deputy_location_id INT UNSIGNED NOT NULL UNIQUE,
    outlet_id INT UNSIGNED NOT NULL,
    location_name VARCHAR(255),
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_deputy_location (deputy_location_id),
    FOREIGN KEY (outlet_id) REFERENCES outlets(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add Deputy employee ID to staff table if not exists
ALTER TABLE staff
ADD COLUMN IF NOT EXISTS deputy_employee_id INT UNSIGNED,
ADD INDEX IF NOT EXISTS idx_deputy_id (deputy_employee_id);
