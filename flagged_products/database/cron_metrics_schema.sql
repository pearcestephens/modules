-- Flagged Products Cron System - Database Schema
-- Version: 2.0.0
-- Purpose: Performance metrics, monitoring, and Smart Cron V2 integration

-- Metrics table for performance tracking
CREATE TABLE IF NOT EXISTS `flagged_products_cron_metrics` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `task_name` VARCHAR(100) NOT NULL,
    `success` TINYINT(1) NOT NULL DEFAULT 0,
    `execution_time` DECIMAL(10,3) NOT NULL COMMENT 'Seconds',
    `memory_used` BIGINT NOT NULL COMMENT 'Bytes',
    `peak_memory` BIGINT NOT NULL COMMENT 'Bytes',
    `created_at` DATETIME NOT NULL,
    KEY `idx_task_name` (`task_name`),
    KEY `idx_created_at` (`created_at`),
    KEY `idx_success` (`success`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Performance summary view
CREATE OR REPLACE VIEW `vw_flagged_products_cron_performance` AS
SELECT
    task_name,
    COUNT(*) as total_executions,
    SUM(CASE WHEN success = 1 THEN 1 ELSE 0 END) as successful_executions,
    SUM(CASE WHEN success = 0 THEN 1 ELSE 0 END) as failed_executions,
    ROUND((SUM(CASE WHEN success = 1 THEN 1 ELSE 0 END) / COUNT(*)) * 100, 2) as success_rate,
    ROUND(AVG(execution_time), 3) as avg_execution_time,
    ROUND(MIN(execution_time), 3) as min_execution_time,
    ROUND(MAX(execution_time), 3) as max_execution_time,
    ROUND(AVG(memory_used) / 1024 / 1024, 2) as avg_memory_mb,
    ROUND(MAX(peak_memory) / 1024 / 1024, 2) as peak_memory_mb,
    MAX(created_at) as last_execution,
    DATE(MAX(created_at)) = CURDATE() as executed_today
FROM flagged_products_cron_metrics
WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
GROUP BY task_name;

-- Daily performance trends
CREATE OR REPLACE VIEW `vw_flagged_products_cron_daily_trends` AS
SELECT
    DATE(created_at) as execution_date,
    task_name,
    COUNT(*) as executions,
    SUM(CASE WHEN success = 1 THEN 1 ELSE 0 END) as successes,
    SUM(CASE WHEN success = 0 THEN 1 ELSE 0 END) as failures,
    ROUND(AVG(execution_time), 3) as avg_time,
    ROUND(AVG(memory_used) / 1024 / 1024, 2) as avg_memory_mb
FROM flagged_products_cron_metrics
WHERE created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)
GROUP BY DATE(created_at), task_name
ORDER BY execution_date DESC, task_name;

-- Health status view
CREATE OR REPLACE VIEW `vw_flagged_products_cron_health` AS
SELECT
    task_name,
    MAX(created_at) as last_run,
    TIMESTAMPDIFF(MINUTE, MAX(created_at), NOW()) as minutes_since_last_run,
    CASE
        WHEN MAX(created_at) >= DATE_SUB(NOW(), INTERVAL 2 HOUR) THEN 'healthy'
        WHEN MAX(created_at) >= DATE_SUB(NOW(), INTERVAL 6 HOUR) THEN 'warning'
        ELSE 'critical'
    END as health_status,
    (SELECT success FROM flagged_products_cron_metrics fcm2
     WHERE fcm2.task_name = fcm.task_name
     ORDER BY created_at DESC LIMIT 1) as last_run_success
FROM flagged_products_cron_metrics fcm
GROUP BY task_name;

-- Insert initial records for tracking
INSERT IGNORE INTO flagged_products_cron_metrics
(task_name, success, execution_time, memory_used, peak_memory, created_at)
VALUES
('flagged_products_generate_daily_products', 1, 0, 0, 0, DATE_SUB(NOW(), INTERVAL 1 DAY)),
('flagged_products_refresh_leaderboard', 1, 0, 0, 0, DATE_SUB(NOW(), INTERVAL 1 DAY)),
('flagged_products_generate_ai_insights', 1, 0, 0, 0, DATE_SUB(NOW(), INTERVAL 1 HOUR)),
('flagged_products_check_achievements', 1, 0, 0, 0, DATE_SUB(NOW(), INTERVAL 6 HOUR)),
('flagged_products_refresh_store_stats', 1, 0, 0, 0, DATE_SUB(NOW(), INTERVAL 30 MINUTE));

-- Create indexes for performance
ALTER TABLE flagged_products_cron_metrics
ADD INDEX idx_task_created (task_name, created_at),
ADD INDEX idx_success_created (success, created_at);
