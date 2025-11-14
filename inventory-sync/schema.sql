-- ============================================================================
-- INVENTORY SYNC MODULE DATABASE SCHEMA
-- Ensures perfect accuracy between Vend POS and local inventory
-- ============================================================================

-- Main sync check log (every scan gets recorded)
CREATE TABLE IF NOT EXISTS inventory_sync_checks (
    check_id INT AUTO_INCREMENT PRIMARY KEY,
    scan_time DATETIME NOT NULL,
    products_checked INT NOT NULL DEFAULT 0,
    perfect_matches INT NOT NULL DEFAULT 0,
    minor_drifts INT NOT NULL DEFAULT 0,
    major_drifts INT NOT NULL DEFAULT 0,
    critical_issues INT NOT NULL DEFAULT 0,
    auto_fixed INT NOT NULL DEFAULT 0,
    alerts_triggered INT NOT NULL DEFAULT 0,
    sync_state ENUM('perfect', 'minor_drift', 'major_drift', 'critical', 'unknown') DEFAULT 'unknown',
    report_json JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_scan_time (scan_time),
    INDEX idx_sync_state (sync_state)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Records every inventory sync check';

-- Alert log for discrepancies that need attention
CREATE TABLE IF NOT EXISTS inventory_sync_alerts (
    alert_id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    outlet_id INT NOT NULL,
    alert_type ENUM('minor_drift', 'major_drift', 'critical_drift', 'missing_data') NOT NULL,
    local_count INT,
    vend_count INT,
    difference INT,
    resolved BOOLEAN DEFAULT FALSE,
    resolved_at DATETIME,
    resolved_by VARCHAR(100),
    resolution_notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_product_outlet (product_id, outlet_id),
    INDEX idx_alert_type (alert_type),
    INDEX idx_resolved (resolved),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Tracks inventory sync alerts';

-- Complete audit trail of all inventory changes
CREATE TABLE IF NOT EXISTS inventory_change_log (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    outlet_id INT NOT NULL,
    change_type ENUM(
        'sale',
        'transfer_in',
        'transfer_out',
        'consignment_in',
        'manual_adjustment',
        'auto_fix',
        'force_sync_to_vend',
        'force_sync_from_vend',
        'return',
        'damage',
        'theft'
    ) NOT NULL,
    old_count INT,
    new_count INT,
    difference INT,
    notes TEXT,
    user_id VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_product_outlet (product_id, outlet_id),
    INDEX idx_change_type (change_type),
    INDEX idx_created (created_at),
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Complete audit trail of inventory changes';

-- Discrepancy details (more granular than alerts)
CREATE TABLE IF NOT EXISTS inventory_discrepancies (
    discrepancy_id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    outlet_id INT NOT NULL,
    check_id INT,
    expected_count INT NOT NULL,
    actual_count INT NOT NULL,
    difference INT NOT NULL,
    severity ENUM('low', 'medium', 'high', 'critical') NOT NULL,
    source ENUM('local', 'vend', 'both') NOT NULL,
    resolved BOOLEAN DEFAULT FALSE,
    resolution_action VARCHAR(255),
    detected_at DATETIME NOT NULL,
    resolved_at DATETIME,
    INDEX idx_product_outlet (product_id, outlet_id),
    INDEX idx_severity (severity),
    INDEX idx_resolved (resolved),
    INDEX idx_detected (detected_at),
    FOREIGN KEY (check_id) REFERENCES inventory_sync_checks(check_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Detailed discrepancy tracking';

-- Sync health metrics (for dashboard)
CREATE TABLE IF NOT EXISTS inventory_sync_metrics (
    metric_id INT AUTO_INCREMENT PRIMARY KEY,
    metric_date DATE NOT NULL,
    total_checks INT DEFAULT 0,
    total_products_checked INT DEFAULT 0,
    total_perfect_matches INT DEFAULT 0,
    total_minor_drifts INT DEFAULT 0,
    total_major_drifts INT DEFAULT 0,
    total_critical_issues INT DEFAULT 0,
    total_auto_fixed INT DEFAULT 0,
    total_alerts INT DEFAULT 0,
    avg_sync_quality_score DECIMAL(5,2) DEFAULT 100.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY idx_metric_date (metric_date),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Daily sync health metrics';

-- Sync configuration per product/outlet (override defaults)
CREATE TABLE IF NOT EXISTS inventory_sync_config (
    config_id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT,
    outlet_id INT,
    auto_fix_enabled BOOLEAN DEFAULT TRUE,
    auto_fix_threshold INT DEFAULT 2,
    alert_threshold INT DEFAULT 5,
    critical_threshold INT DEFAULT 10,
    sync_frequency_minutes INT DEFAULT 5,
    master_source ENUM('local', 'vend', 'auto') DEFAULT 'auto',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY idx_product_outlet (product_id, outlet_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Sync configuration overrides';

-- ============================================================================
-- VIEWS FOR EASY REPORTING
-- ============================================================================

-- Current sync health (last 24 hours)
CREATE OR REPLACE VIEW v_sync_health_24h AS
SELECT
    COUNT(*) as total_checks,
    SUM(products_checked) as total_products,
    SUM(perfect_matches) as perfect_matches,
    SUM(minor_drifts) as minor_drifts,
    SUM(major_drifts) as major_drifts,
    SUM(critical_issues) as critical_issues,
    SUM(auto_fixed) as auto_fixed,
    SUM(alerts_triggered) as alerts_triggered,
    ROUND(SUM(perfect_matches) * 100.0 / NULLIF(SUM(products_checked), 0), 2) as accuracy_percent
FROM inventory_sync_checks
WHERE scan_time >= DATE_SUB(NOW(), INTERVAL 24 HOUR);

-- Unresolved alerts
CREATE OR REPLACE VIEW v_unresolved_alerts AS
SELECT
    a.*,
    p.name as product_name,
    p.sku,
    o.name as outlet_name
FROM inventory_sync_alerts a
LEFT JOIN vend_products p ON a.product_id = p.product_id
LEFT JOIN vend_outlets o ON a.outlet_id = o.outlet_id
WHERE a.resolved = FALSE
ORDER BY
    CASE a.alert_type
        WHEN 'critical_drift' THEN 1
        WHEN 'major_drift' THEN 2
        WHEN 'minor_drift' THEN 3
        ELSE 4
    END,
    a.created_at DESC;

-- Product sync history (last 30 days)
CREATE OR REPLACE VIEW v_product_sync_history AS
SELECT
    product_id,
    outlet_id,
    COUNT(*) as total_changes,
    SUM(CASE WHEN change_type = 'auto_fix' THEN 1 ELSE 0 END) as auto_fixes,
    SUM(CASE WHEN change_type IN ('force_sync_to_vend', 'force_sync_from_vend') THEN 1 ELSE 0 END) as manual_syncs,
    MIN(created_at) as first_change,
    MAX(created_at) as last_change
FROM inventory_change_log
WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
GROUP BY product_id, outlet_id;

-- ============================================================================
-- SAMPLE DATA & TESTING
-- ============================================================================

-- Insert sample sync check
INSERT INTO inventory_sync_checks
(scan_time, products_checked, perfect_matches, minor_drifts, major_drifts, critical_issues, auto_fixed, alerts_triggered, sync_state)
VALUES
(NOW(), 100, 95, 3, 1, 1, 2, 2, 'minor_drift');

-- Insert sample alert
-- INSERT INTO inventory_sync_alerts
-- (product_id, outlet_id, alert_type, local_count, vend_count, difference)
-- VALUES
-- (12345, 1, 'major_drift', 50, 42, 8);

-- ============================================================================
-- MAINTENANCE & CLEANUP
-- ============================================================================

-- Archive old sync checks (keep 90 days)
-- DELIMITER $$
-- CREATE EVENT IF NOT EXISTS archive_old_sync_checks
-- ON SCHEDULE EVERY 1 DAY
-- DO BEGIN
--     DELETE FROM inventory_sync_checks
--     WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY);
-- END$$
-- DELIMITER ;

-- Archive old change log (keep 1 year)
-- DELIMITER $$
-- CREATE EVENT IF NOT EXISTS archive_old_change_log
-- ON SCHEDULE EVERY 1 WEEK
-- DO BEGIN
--     DELETE FROM inventory_change_log
--     WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 YEAR);
-- END$$
-- DELIMITER ;
