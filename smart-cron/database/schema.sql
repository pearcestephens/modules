-- Smart Cron System - Complete Database Schema
-- Ultra-robust with failsafes, auditing, and monitoring
-- Version: 2.0
-- Created: 2025-11-05

-- ============================================================================
-- 1. TASK CONFIGURATION TABLE
-- ============================================================================
CREATE TABLE IF NOT EXISTS smart_cron_tasks_config (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    -- Task Identity
    task_name VARCHAR(100) NOT NULL UNIQUE,
    task_description VARCHAR(500) NOT NULL,
    task_script VARCHAR(255) NOT NULL,

    -- Scheduling
    schedule_pattern VARCHAR(50) NOT NULL COMMENT 'Cron pattern: minute hour day month weekday',
    priority TINYINT UNSIGNED NOT NULL DEFAULT 5 COMMENT '1=highest, 10=lowest',
    timeout_seconds INT UNSIGNED NOT NULL DEFAULT 300,

    -- Execution Control
    enabled TINYINT(1) NOT NULL DEFAULT 1,
    max_retries TINYINT UNSIGNED NOT NULL DEFAULT 3,
    retry_delay_seconds INT UNSIGNED NOT NULL DEFAULT 60,

    -- Failure Handling
    failure_threshold INT UNSIGNED NOT NULL DEFAULT 5 COMMENT 'Consecutive failures before alert',
    consecutive_failures INT UNSIGNED NOT NULL DEFAULT 0,
    last_failure_at DATETIME NULL,

    -- Monitoring
    alert_on_failure TINYINT(1) NOT NULL DEFAULT 1,
    alert_email VARCHAR(255) NULL,
    alert_slack_webhook VARCHAR(500) NULL,

    -- Statistics
    total_executions BIGINT UNSIGNED NOT NULL DEFAULT 0,
    total_successes BIGINT UNSIGNED NOT NULL DEFAULT 0,
    total_failures BIGINT UNSIGNED NOT NULL DEFAULT 0,
    avg_execution_time FLOAT NULL COMMENT 'Average in seconds',

    -- Status
    is_running TINYINT(1) NOT NULL DEFAULT 0,
    last_run_at DATETIME NULL,
    last_success_at DATETIME NULL,
    next_run_at DATETIME NULL,

    -- Metadata
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by INT UNSIGNED NULL,
    updated_by INT UNSIGNED NULL,

    -- Indexes
    INDEX idx_enabled (enabled),
    INDEX idx_next_run (next_run_at),
    INDEX idx_priority (priority),
    INDEX idx_is_running (is_running),
    INDEX idx_consecutive_failures (consecutive_failures)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 2. EXECUTION LOG TABLE (Complete History)
-- ============================================================================
CREATE TABLE IF NOT EXISTS smart_cron_executions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    -- Task Reference
    task_id INT UNSIGNED NOT NULL,
    task_name VARCHAR(100) NOT NULL,

    -- Execution Details
    started_at DATETIME NOT NULL,
    completed_at DATETIME NULL,
    execution_time FLOAT NULL COMMENT 'Seconds',

    -- Result
    exit_code INT NULL,
    success TINYINT(1) NOT NULL DEFAULT 0,
    retry_attempt TINYINT UNSIGNED NOT NULL DEFAULT 0,

    -- Output (Full Capture)
    stdout_output TEXT NULL,
    stderr_output TEXT NULL,
    output_size INT UNSIGNED NULL COMMENT 'Bytes',

    -- Error Tracking
    error_message VARCHAR(1000) NULL,
    error_type VARCHAR(100) NULL COMMENT 'timeout|crash|exception|user_error',

    -- Resource Usage
    memory_peak_mb INT UNSIGNED NULL,
    cpu_time FLOAT NULL,

    -- Context
    triggered_by VARCHAR(50) NOT NULL DEFAULT 'cron' COMMENT 'cron|manual|api|retry',
    triggered_by_user_id INT UNSIGNED NULL,
    server_hostname VARCHAR(100) NULL,
    pid INT UNSIGNED NULL,

    -- Metadata
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    -- Indexes
    INDEX idx_task_id (task_id),
    INDEX idx_task_name (task_name),
    INDEX idx_started_at (started_at),
    INDEX idx_success (success),
    INDEX idx_exit_code (exit_code),
    INDEX idx_triggered_by (triggered_by),
    FOREIGN KEY (task_id) REFERENCES smart_cron_tasks_config(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 3. SECURITY AUDIT LOG
-- ============================================================================
CREATE TABLE IF NOT EXISTS smart_cron_audit_log (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    -- Event Details
    event_type VARCHAR(100) NOT NULL COMMENT 'task_created|task_modified|task_deleted|task_executed|access_denied|security_violation',
    event_severity VARCHAR(20) NOT NULL DEFAULT 'info' COMMENT 'debug|info|warning|error|critical',

    -- User Context
    user_id INT UNSIGNED NULL,
    username VARCHAR(100) NULL,
    ip_address VARCHAR(45) NOT NULL,
    user_agent VARCHAR(500) NULL,

    -- Request Context
    request_uri VARCHAR(500) NULL,
    request_method VARCHAR(10) NULL,
    request_body TEXT NULL,

    -- Event Data
    task_id INT UNSIGNED NULL,
    task_name VARCHAR(100) NULL,
    changes_json JSON NULL COMMENT 'Before/after values',
    context_json JSON NULL,

    -- Result
    success TINYINT(1) NOT NULL DEFAULT 1,
    error_message VARCHAR(1000) NULL,

    -- Metadata
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    -- Indexes
    INDEX idx_event_type (event_type),
    INDEX idx_event_severity (event_severity),
    INDEX idx_user_id (user_id),
    INDEX idx_ip_address (ip_address),
    INDEX idx_created_at (created_at),
    INDEX idx_task_name (task_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 4. RATE LIMITING & SECURITY
-- ============================================================================
CREATE TABLE IF NOT EXISTS smart_cron_rate_limits (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    identifier VARCHAR(100) NOT NULL COMMENT 'user_id:XXX or ip:XXX.XXX.XXX.XXX',
    action VARCHAR(100) NOT NULL COMMENT 'api_call|task_create|task_execute',

    request_count INT UNSIGNED NOT NULL DEFAULT 1,
    window_start DATETIME NOT NULL,
    window_end DATETIME NOT NULL,

    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_identifier_action (identifier, action),
    INDEX idx_window_end (window_end)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS smart_cron_banned_ips (
    ip_address VARCHAR(45) PRIMARY KEY,

    reason VARCHAR(500) NOT NULL,
    ban_count INT UNSIGNED NOT NULL DEFAULT 1,

    banned_at DATETIME NOT NULL,
    banned_until DATETIME NOT NULL,

    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_banned_until (banned_until)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 5. ALERTS & NOTIFICATIONS
-- ============================================================================
CREATE TABLE IF NOT EXISTS smart_cron_alerts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    -- Alert Details
    alert_type VARCHAR(50) NOT NULL COMMENT 'task_failure|task_timeout|consecutive_failures|system_health',
    alert_severity VARCHAR(20) NOT NULL DEFAULT 'warning' COMMENT 'info|warning|error|critical',

    -- Task Reference
    task_id INT UNSIGNED NULL,
    task_name VARCHAR(100) NULL,
    execution_id BIGINT UNSIGNED NULL,

    -- Alert Content
    alert_title VARCHAR(255) NOT NULL,
    alert_message TEXT NOT NULL,
    alert_data JSON NULL,

    -- Notification Status
    notification_sent TINYINT(1) NOT NULL DEFAULT 0,
    notification_sent_at DATETIME NULL,
    notification_method VARCHAR(50) NULL COMMENT 'email|slack|webhook|sms',
    notification_response TEXT NULL,

    -- Resolution
    acknowledged TINYINT(1) NOT NULL DEFAULT 0,
    acknowledged_at DATETIME NULL,
    acknowledged_by INT UNSIGNED NULL,
    resolved TINYINT(1) NOT NULL DEFAULT 0,
    resolved_at DATETIME NULL,

    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    -- Indexes
    INDEX idx_alert_type (alert_type),
    INDEX idx_alert_severity (alert_severity),
    INDEX idx_task_id (task_id),
    INDEX idx_notification_sent (notification_sent),
    INDEX idx_acknowledged (acknowledged),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 6. SYSTEM HEALTH MONITORING
-- ============================================================================
CREATE TABLE IF NOT EXISTS smart_cron_health_checks (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    check_type VARCHAR(50) NOT NULL COMMENT 'database|filesystem|memory|cpu|disk_space|task_queue',
    check_status VARCHAR(20) NOT NULL COMMENT 'healthy|warning|critical',

    check_value FLOAT NULL,
    check_threshold FLOAT NULL,
    check_message VARCHAR(500) NULL,
    check_data JSON NULL,

    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_check_type (check_type),
    INDEX idx_check_status (check_status),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 7. BACKUP & RECOVERY
-- ============================================================================
CREATE TABLE IF NOT EXISTS smart_cron_backups (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    backup_type VARCHAR(50) NOT NULL COMMENT 'full|incremental|config_only',
    backup_path VARCHAR(500) NOT NULL,
    backup_size BIGINT UNSIGNED NULL COMMENT 'Bytes',
    backup_hash VARCHAR(64) NULL COMMENT 'SHA-256',

    tasks_count INT UNSIGNED NOT NULL DEFAULT 0,
    executions_count BIGINT UNSIGNED NOT NULL DEFAULT 0,

    compression VARCHAR(20) NULL COMMENT 'gzip|bzip2|none',
    encrypted TINYINT(1) NOT NULL DEFAULT 0,

    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NULL,

    INDEX idx_backup_type (backup_type),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 8. PERFORMANCE METRICS
-- ============================================================================
CREATE TABLE IF NOT EXISTS smart_cron_metrics (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    metric_name VARCHAR(100) NOT NULL,
    metric_value FLOAT NOT NULL,
    metric_unit VARCHAR(20) NULL COMMENT 'seconds|bytes|count|percentage',

    task_name VARCHAR(100) NULL,

    recorded_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_metric_name (metric_name),
    INDEX idx_task_name (task_name),
    INDEX idx_recorded_at (recorded_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- INITIAL DATA: System Health Monitor Task
-- ============================================================================
INSERT INTO smart_cron_tasks_config (
    task_name,
    task_description,
    task_script,
    schedule_pattern,
    priority,
    timeout_seconds,
    enabled,
    alert_on_failure
) VALUES (
    'smart_cron_health_monitor',
    'Monitors Smart Cron system health and generates alerts',
    '/modules/smart-cron/cron/health_monitor.php',
    '*/5 * * * *',
    1,
    60,
    1,
    1
) ON DUPLICATE KEY UPDATE
    task_description = VALUES(task_description),
    task_script = VALUES(task_script),
    schedule_pattern = VALUES(schedule_pattern);

-- ============================================================================
-- INITIAL DATA: Cleanup Old Data Task
-- ============================================================================
INSERT INTO smart_cron_tasks_config (
    task_name,
    task_description,
    task_script,
    schedule_pattern,
    priority,
    timeout_seconds,
    enabled
) VALUES (
    'smart_cron_cleanup',
    'Cleans up old execution logs and audit data (keeps 90 days)',
    '/modules/smart-cron/cron/cleanup_old_data.php',
    '0 2 * * *',
    5,
    300,
    1
) ON DUPLICATE KEY UPDATE
    task_description = VALUES(task_description),
    task_script = VALUES(task_script),
    schedule_pattern = VALUES(schedule_pattern);

-- ============================================================================
-- VIEWS FOR REPORTING
-- ============================================================================

-- Task Performance Summary
CREATE OR REPLACE VIEW smart_cron_task_performance AS
SELECT
    t.id,
    t.task_name,
    t.enabled,
    t.total_executions,
    t.total_successes,
    t.total_failures,
    ROUND((t.total_successes / NULLIF(t.total_executions, 0)) * 100, 2) as success_rate,
    t.avg_execution_time,
    t.consecutive_failures,
    t.last_run_at,
    t.last_success_at,
    t.next_run_at,
    TIMESTAMPDIFF(MINUTE, t.last_run_at, NOW()) as minutes_since_last_run
FROM smart_cron_tasks_config t;

-- Recent Failures
CREATE OR REPLACE VIEW smart_cron_recent_failures AS
SELECT
    e.id,
    e.task_name,
    e.started_at,
    e.execution_time,
    e.exit_code,
    e.error_message,
    e.error_type,
    e.triggered_by
FROM smart_cron_executions e
WHERE e.success = 0
  AND e.started_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
ORDER BY e.started_at DESC;

-- Active Alerts
CREATE OR REPLACE VIEW smart_cron_active_alerts AS
SELECT
    a.id,
    a.alert_type,
    a.alert_severity,
    a.task_name,
    a.alert_title,
    a.alert_message,
    a.notification_sent,
    a.acknowledged,
    a.created_at,
    TIMESTAMPDIFF(MINUTE, a.created_at, NOW()) as age_minutes
FROM smart_cron_alerts a
WHERE a.resolved = 0
ORDER BY
    FIELD(a.alert_severity, 'critical', 'error', 'warning', 'info'),
    a.created_at DESC;

-- System Health Status
CREATE OR REPLACE VIEW smart_cron_system_status AS
SELECT
    (SELECT COUNT(*) FROM smart_cron_tasks_config WHERE enabled = 1) as enabled_tasks,
    (SELECT COUNT(*) FROM smart_cron_tasks_config WHERE is_running = 1) as running_tasks,
    (SELECT COUNT(*) FROM smart_cron_tasks_config WHERE consecutive_failures >= failure_threshold) as failing_tasks,
    (SELECT COUNT(*) FROM smart_cron_executions WHERE started_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)) as executions_last_hour,
    (SELECT COUNT(*) FROM smart_cron_executions WHERE success = 0 AND started_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)) as failures_last_hour,
    (SELECT COUNT(*) FROM smart_cron_alerts WHERE resolved = 0 AND alert_severity IN ('critical', 'error')) as critical_alerts,
    (SELECT AVG(execution_time) FROM smart_cron_executions WHERE started_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)) as avg_execution_time_24h;

-- ============================================================================
-- STORED PROCEDURES
-- ============================================================================

DELIMITER $$

-- Log Task Execution
CREATE PROCEDURE IF NOT EXISTS sp_log_execution(
    IN p_task_id INT,
    IN p_task_name VARCHAR(100),
    IN p_started_at DATETIME,
    IN p_completed_at DATETIME,
    IN p_exit_code INT,
    IN p_success TINYINT,
    IN p_stdout TEXT,
    IN p_stderr TEXT,
    IN p_triggered_by VARCHAR(50)
)
BEGIN
    DECLARE v_execution_time FLOAT;

    SET v_execution_time = TIMESTAMPDIFF(MICROSECOND, p_started_at, p_completed_at) / 1000000.0;

    -- Insert execution log
    INSERT INTO smart_cron_executions (
        task_id, task_name, started_at, completed_at, execution_time,
        exit_code, success, stdout_output, stderr_output, triggered_by
    ) VALUES (
        p_task_id, p_task_name, p_started_at, p_completed_at, v_execution_time,
        p_exit_code, p_success, p_stdout, p_stderr, p_triggered_by
    );

    -- Update task statistics
    UPDATE smart_cron_tasks_config
    SET
        total_executions = total_executions + 1,
        total_successes = total_successes + IF(p_success = 1, 1, 0),
        total_failures = total_failures + IF(p_success = 0, 1, 0),
        consecutive_failures = IF(p_success = 1, 0, consecutive_failures + 1),
        last_run_at = p_completed_at,
        last_success_at = IF(p_success = 1, p_completed_at, last_success_at),
        last_failure_at = IF(p_success = 0, p_completed_at, last_failure_at),
        avg_execution_time = (COALESCE(avg_execution_time, 0) * (total_executions - 1) + v_execution_time) / total_executions,
        is_running = 0
    WHERE id = p_task_id;
END$$

DELIMITER ;

-- ============================================================================
-- INDEXES FOR PERFORMANCE
-- ============================================================================
ALTER TABLE smart_cron_executions ADD INDEX idx_task_started (task_id, started_at DESC);
ALTER TABLE smart_cron_audit_log ADD INDEX idx_composite (event_type, created_at DESC);
ALTER TABLE smart_cron_alerts ADD INDEX idx_unresolved (resolved, created_at DESC);

-- ============================================================================
-- MAINTENANCE EVENTS
-- ============================================================================

-- Auto-cleanup old rate limit records (daily at 3 AM)
CREATE EVENT IF NOT EXISTS cleanup_rate_limits
ON SCHEDULE EVERY 1 DAY STARTS '2025-11-06 03:00:00'
DO DELETE FROM smart_cron_rate_limits WHERE window_end < DATE_SUB(NOW(), INTERVAL 1 DAY);

-- Auto-cleanup expired bans (hourly)
CREATE EVENT IF NOT EXISTS cleanup_expired_bans
ON SCHEDULE EVERY 1 HOUR
DO DELETE FROM smart_cron_banned_ips WHERE banned_until < NOW();

-- ============================================================================
-- GRANT PERMISSIONS (Adjust as needed)
-- ============================================================================
-- GRANT SELECT, INSERT, UPDATE, DELETE ON smart_cron_* TO 'your_app_user'@'localhost';

-- ============================================================================
-- VERIFICATION QUERIES
-- ============================================================================
-- Run these to verify installation:
/*
SELECT COUNT(*) as total_tables FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name LIKE 'smart_cron_%';
SELECT * FROM smart_cron_tasks_config;
SELECT * FROM smart_cron_system_status;
*/
