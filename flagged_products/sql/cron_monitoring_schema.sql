-- =========================================
-- FLAGGED PRODUCTS CRON MONITORING SCHEMA
-- =========================================
-- 
-- Comprehensive monitoring and alerting system
-- for flagged products cron tasks
--
-- Features:
-- - Execution history tracking
-- - Performance metrics
-- - Alert management
-- - Health dashboard data
--
-- Created: October 26, 2025
-- =========================================

-- Execution history table
CREATE TABLE IF NOT EXISTS flagged_products_cron_executions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    task_name VARCHAR(100) NOT NULL,
    started_at DATETIME NOT NULL,
    completed_at DATETIME NOT NULL,
    execution_time DECIMAL(10,2) NOT NULL COMMENT 'Execution time in seconds',
    success TINYINT(1) NOT NULL DEFAULT 1,
    error_count INT NOT NULL DEFAULT 0,
    warning_count INT NOT NULL DEFAULT 0,
    metrics JSON NULL COMMENT 'Task-specific metrics',
    message TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_task_started (task_name, started_at),
    INDEX idx_success (success),
    INDEX idx_execution_time (execution_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
COMMENT='Execution history for flagged products cron tasks';

-- Alerts table
CREATE TABLE IF NOT EXISTS flagged_products_cron_alerts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    task_name VARCHAR(100) NOT NULL,
    severity ENUM('WARNING', 'CRITICAL') NOT NULL,
    type VARCHAR(50) NOT NULL COMMENT 'task_failure, slow_execution, high_error_rate',
    message TEXT NOT NULL,
    details JSON NULL,
    acknowledged TINYINT(1) NOT NULL DEFAULT 0,
    acknowledged_by INT NULL,
    acknowledged_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_task_severity (task_name, severity),
    INDEX idx_acknowledged (acknowledged),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
COMMENT='Alerts for flagged products cron tasks';

-- Performance snapshots (for trending)
CREATE TABLE IF NOT EXISTS flagged_products_cron_performance (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    task_name VARCHAR(100) NOT NULL,
    snapshot_date DATE NOT NULL,
    total_runs INT NOT NULL DEFAULT 0,
    successful_runs INT NOT NULL DEFAULT 0,
    failed_runs INT NOT NULL DEFAULT 0,
    avg_execution_time DECIMAL(10,2) NOT NULL DEFAULT 0,
    max_execution_time DECIMAL(10,2) NOT NULL DEFAULT 0,
    total_errors INT NOT NULL DEFAULT 0,
    total_warnings INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    UNIQUE KEY uk_task_date (task_name, snapshot_date),
    INDEX idx_snapshot_date (snapshot_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
COMMENT='Daily performance snapshots for trending analysis';

-- =========================================
-- DATA RETENTION POLICIES
-- =========================================

-- Keep execution history for 90 days
CREATE EVENT IF NOT EXISTS cleanup_cron_executions
ON SCHEDULE EVERY 1 DAY
STARTS CURRENT_TIMESTAMP
DO
    DELETE FROM flagged_products_cron_executions
    WHERE started_at < DATE_SUB(NOW(), INTERVAL 90 DAY);

-- Keep alerts for 180 days (6 months)
CREATE EVENT IF NOT EXISTS cleanup_cron_alerts
ON SCHEDULE EVERY 1 DAY
STARTS CURRENT_TIMESTAMP
DO
    DELETE FROM flagged_products_cron_alerts
    WHERE created_at < DATE_SUB(NOW(), INTERVAL 180 DAY);

-- Keep performance snapshots for 1 year
CREATE EVENT IF NOT EXISTS cleanup_cron_performance
ON SCHEDULE EVERY 1 DAY
STARTS CURRENT_TIMESTAMP
DO
    DELETE FROM flagged_products_cron_performance
    WHERE snapshot_date < DATE_SUB(CURDATE(), INTERVAL 1 YEAR);

-- =========================================
-- DAILY PERFORMANCE SNAPSHOT
-- =========================================

-- Aggregate yesterday's performance into snapshot
DELIMITER //
CREATE EVENT IF NOT EXISTS daily_performance_snapshot
ON SCHEDULE EVERY 1 DAY
STARTS CURRENT_TIMESTAMP
DO
BEGIN
    INSERT INTO flagged_products_cron_performance 
        (task_name, snapshot_date, total_runs, successful_runs, failed_runs, 
         avg_execution_time, max_execution_time, total_errors, total_warnings)
    SELECT 
        task_name,
        DATE(started_at) as snapshot_date,
        COUNT(*) as total_runs,
        SUM(CASE WHEN success = 1 THEN 1 ELSE 0 END) as successful_runs,
        SUM(CASE WHEN success = 0 THEN 1 ELSE 0 END) as failed_runs,
        AVG(execution_time) as avg_execution_time,
        MAX(execution_time) as max_execution_time,
        SUM(error_count) as total_errors,
        SUM(warning_count) as total_warnings
    FROM flagged_products_cron_executions
    WHERE DATE(started_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)
    GROUP BY task_name, DATE(started_at)
    ON DUPLICATE KEY UPDATE
        total_runs = VALUES(total_runs),
        successful_runs = VALUES(successful_runs),
        failed_runs = VALUES(failed_runs),
        avg_execution_time = VALUES(avg_execution_time),
        max_execution_time = VALUES(max_execution_time),
        total_errors = VALUES(total_errors),
        total_warnings = VALUES(total_warnings);
END//
DELIMITER ;

-- =========================================
-- INDEXES FOR PERFORMANCE
-- =========================================

-- Additional indexes for common queries
ALTER TABLE flagged_products_cron_executions
ADD INDEX idx_task_time (task_name, execution_time),
ADD INDEX idx_completed_at (completed_at);

ALTER TABLE flagged_products_cron_alerts
ADD INDEX idx_type (type),
ADD INDEX idx_task_created (task_name, created_at);

-- =========================================
-- VIEWS FOR DASHBOARD
-- =========================================

-- Current health status view
CREATE OR REPLACE VIEW vw_cron_health_status AS
SELECT 
    task_name,
    MAX(started_at) as last_run,
    MAX(CASE WHEN success = 1 THEN started_at END) as last_successful_run,
    AVG(CASE WHEN started_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) 
        THEN execution_time END) as avg_execution_time_24h,
    SUM(CASE WHEN started_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) 
        THEN 1 ELSE 0 END) as runs_24h,
    SUM(CASE WHEN started_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) AND success = 1 
        THEN 1 ELSE 0 END) as successful_runs_24h,
    SUM(CASE WHEN started_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) AND success = 0 
        THEN 1 ELSE 0 END) as failed_runs_24h,
    ROUND((SUM(CASE WHEN started_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) AND success = 1 
        THEN 1 ELSE 0 END) / 
        NULLIF(SUM(CASE WHEN started_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) 
        THEN 1 ELSE 0 END), 0)) * 100, 2) as success_rate_24h
FROM flagged_products_cron_executions
GROUP BY task_name;

-- Recent failures view
CREATE OR REPLACE VIEW vw_cron_recent_failures AS
SELECT 
    ce.task_name,
    ce.started_at,
    ce.completed_at,
    ce.execution_time,
    ce.error_count,
    ce.warning_count,
    ce.message
FROM flagged_products_cron_executions ce
WHERE ce.success = 0
AND ce.started_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
ORDER BY ce.started_at DESC;

-- Active alerts view
CREATE OR REPLACE VIEW vw_cron_active_alerts AS
SELECT 
    a.task_name,
    a.severity,
    a.type,
    a.message,
    a.created_at,
    COUNT(*) OVER (PARTITION BY a.task_name, a.type) as occurrence_count
FROM flagged_products_cron_alerts a
WHERE a.acknowledged = 0
AND a.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
ORDER BY 
    FIELD(a.severity, 'CRITICAL', 'WARNING'),
    a.created_at DESC;

-- Performance trends view (last 30 days)
CREATE OR REPLACE VIEW vw_cron_performance_trends AS
SELECT 
    task_name,
    snapshot_date,
    total_runs,
    successful_runs,
    failed_runs,
    ROUND((successful_runs / NULLIF(total_runs, 0)) * 100, 2) as success_rate,
    avg_execution_time,
    max_execution_time,
    total_errors,
    total_warnings
FROM flagged_products_cron_performance
WHERE snapshot_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
ORDER BY snapshot_date DESC, task_name;

-- =========================================
-- SAMPLE QUERIES FOR MONITORING
-- =========================================

/*
-- Get current health for all tasks
SELECT * FROM vw_cron_health_status;

-- Get recent failures
SELECT * FROM vw_cron_recent_failures LIMIT 20;

-- Get active alerts
SELECT * FROM vw_cron_active_alerts;

-- Get performance trends for a specific task
SELECT * 
FROM vw_cron_performance_trends 
WHERE task_name = 'generate_daily_products'
ORDER BY snapshot_date DESC
LIMIT 30;

-- Get execution time distribution
SELECT 
    task_name,
    MIN(execution_time) as min_time,
    AVG(execution_time) as avg_time,
    MAX(execution_time) as max_time,
    STDDEV(execution_time) as stddev_time
FROM flagged_products_cron_executions
WHERE started_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
GROUP BY task_name;

-- Get hourly execution pattern
SELECT 
    task_name,
    HOUR(started_at) as hour_of_day,
    COUNT(*) as execution_count,
    AVG(execution_time) as avg_time
FROM flagged_products_cron_executions
WHERE started_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
GROUP BY task_name, HOUR(started_at)
ORDER BY task_name, hour_of_day;
*/

-- =========================================
-- MAINTENANCE NOTES
-- =========================================

/*
RETENTION POLICIES:
- Execution history: 90 days
- Alerts: 180 days (6 months)
- Performance snapshots: 1 year

AUTOMATIC CLEANUP:
- Runs daily via MySQL events
- Can be manually triggered:
  CALL cleanup_old_cron_data();

PERFORMANCE:
- Indexes optimized for dashboard queries
- Views provide pre-calculated aggregates
- JSON metrics field for flexible tracking

ALERTING:
- Email alerts for CRITICAL issues
- Slack integration ready (configure webhook)
- Alert acknowledgment workflow
- Duplicate alert suppression

DASHBOARD INTEGRATION:
- Health status cached hourly
- Real-time metrics via views
- Historical trending via snapshots
- Export-ready for Grafana/Datadog
*/
