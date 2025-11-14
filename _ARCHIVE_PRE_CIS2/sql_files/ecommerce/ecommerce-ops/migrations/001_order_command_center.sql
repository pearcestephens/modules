-- =====================================================
-- ORDER COMMAND CENTER - DATABASE SCHEMA
-- No bloat, just what we need for SSE + sorting
-- =====================================================

-- Track order changes for SSE
CREATE TABLE IF NOT EXISTS order_changes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    change_type VARCHAR(50) NOT NULL, -- 'status_change', 'comment_added', 'assigned', 'dispatched'
    old_value TEXT,
    new_value TEXT,
    changed_by INT,
    changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    notified TINYINT(1) DEFAULT 0, -- For SSE tracking
    INDEX idx_order_id (order_id),
    INDEX idx_notified (notified, changed_at),
    INDEX idx_changed_at (changed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Smart sorting state (per-user view optimization)
CREATE TABLE IF NOT EXISTS order_sort_states (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    user_id INT DEFAULT NULL, -- NULL = global optimization
    priority_score DECIMAL(10,2) DEFAULT 0.00, -- Calculated score
    optimal_outlet_id INT DEFAULT NULL, -- AI suggested outlet
    urgency_level ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
    fraud_risk_score TINYINT DEFAULT 0, -- 0-100
    estimated_pack_time INT DEFAULT 0, -- minutes
    carrier_preference VARCHAR(50), -- 'gss', 'nzpost', 'pickup'
    sort_group VARCHAR(50), -- 'express', 'standard', 'pickup', 'hold'
    last_calculated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    locked_by INT DEFAULT NULL, -- User currently processing
    locked_at TIMESTAMP NULL,
    INDEX idx_order_priority (priority_score DESC, urgency_level),
    INDEX idx_outlet (optimal_outlet_id),
    INDEX idx_user_view (user_id, priority_score DESC),
    INDEX idx_locked (locked_by, locked_at),
    UNIQUE KEY idx_order_user (order_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Courier batch printing (group orders for efficient label printing)
CREATE TABLE IF NOT EXISTS courier_print_batches (
    id INT AUTO_INCREMENT PRIMARY KEY,
    batch_code VARCHAR(20) UNIQUE NOT NULL, -- e.g., "BATCH-20251106-001"
    carrier VARCHAR(50) NOT NULL, -- 'gss', 'nzpost'
    outlet_id INT NOT NULL,
    created_by INT NOT NULL,
    order_count INT DEFAULT 0,
    total_weight DECIMAL(10,2) DEFAULT 0.00,
    labels_generated TINYINT(1) DEFAULT 0,
    printed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_outlet (outlet_id, created_at),
    INDEX idx_carrier (carrier),
    INDEX idx_created_by (created_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Link orders to print batches
CREATE TABLE IF NOT EXISTS courier_batch_orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    batch_id INT NOT NULL,
    order_id INT NOT NULL,
    label_url TEXT, -- PDF URL from carrier
    tracking_number VARCHAR(100),
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_batch (batch_id),
    INDEX idx_order (order_id),
    UNIQUE KEY idx_batch_order (batch_id, order_id),
    FOREIGN KEY (batch_id) REFERENCES courier_print_batches(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Quick reference: order processing metrics (for algorithm tuning)
CREATE TABLE IF NOT EXISTS order_processing_metrics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    outlet_id INT NOT NULL,
    time_to_assign INT DEFAULT 0, -- seconds from created to assigned
    time_to_dispatch INT DEFAULT 0, -- seconds from assigned to dispatched
    actual_pack_time INT DEFAULT 0, -- seconds spent packing
    processed_by INT,
    carrier_used VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_outlet_metrics (outlet_id, created_at),
    INDEX idx_order (order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Fraud detection blacklist
CREATE TABLE IF NOT EXISTS ecommerce_fraud_blacklist (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) DEFAULT NULL,
    phone VARCHAR(50) DEFAULT NULL,
    reason TEXT,
    is_active TINYINT(1) DEFAULT 1,
    added_by INT,
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    notes TEXT,
    INDEX idx_email (email),
    INDEX idx_phone (phone),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TRIGGERS: Auto-track changes for SSE
-- =====================================================

-- Drop trigger if exists (for re-running migration)
DROP TRIGGER IF EXISTS after_order_status_update;

DELIMITER //

-- Track status changes
CREATE TRIGGER after_order_status_update
AFTER UPDATE ON vend_sales
FOR EACH ROW
BEGIN
    IF OLD.status != NEW.status THEN
        INSERT INTO order_changes (order_id, change_type, old_value, new_value, changed_by)
        VALUES (NEW.id, 'status_change', OLD.status, NEW.status, @current_user_id);
    END IF;
END//

DELIMITER ;

-- =====================================================
-- SAMPLE DATA for testing
-- =====================================================

-- Initialize sort states for existing orders (run once)
-- INSERT INTO order_sort_states (order_id, priority_score, urgency_level)
-- SELECT id, 50.00, 'medium' FROM vend_sales WHERE created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
-- ON DUPLICATE KEY UPDATE last_calculated = NOW();
