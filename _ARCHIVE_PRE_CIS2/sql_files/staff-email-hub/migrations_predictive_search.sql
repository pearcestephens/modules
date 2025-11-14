-- ============================================================================
-- Predictive Search & Behavior Tracking Database Schema
-- "Search Just Knows What I'm Thinking"
-- ============================================================================

-- User Behavior Events Tracking
CREATE TABLE IF NOT EXISTS `user_behavior_events` (
  `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
  `staff_id` INT NOT NULL,
  `event_type` VARCHAR(50) NOT NULL,
  `page_url` VARCHAR(500),
  `event_data` JSON,
  `session_id` VARCHAR(100),
  `timestamp` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_staff_events` (`staff_id`, `timestamp`),
  INDEX `idx_event_type` (`event_type`),
  INDEX `idx_session` (`session_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Search Analytics (tracks every search)
CREATE TABLE IF NOT EXISTS `search_analytics` (
  `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
  `staff_id` INT NOT NULL,
  `search_query` VARCHAR(500) NOT NULL,
  `search_context` VARCHAR(50) DEFAULT 'all',
  `page_context` VARCHAR(200),
  `filters_used` JSON,
  `results_count` INT DEFAULT 0,
  `result_clicked` TINYINT DEFAULT 0,
  `result_id` VARCHAR(100),
  `time_to_click` INT, -- milliseconds
  `searched_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_staff_searches` (`staff_id`, `searched_at`),
  INDEX `idx_query` (`search_query`(100)),
  INDEX `idx_context` (`search_context`),
  FULLTEXT INDEX `ft_query` (`search_query`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Workflow Patterns (what action follows what)
CREATE TABLE IF NOT EXISTS `search_workflow_patterns` (
  `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
  `staff_id` INT NOT NULL,
  `current_action` VARCHAR(100) NOT NULL,
  `next_search_query` VARCHAR(500),
  `next_search_context` VARCHAR(50),
  `time_to_next_search` INT, -- seconds
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_staff_workflow` (`staff_id`, `current_action`),
  INDEX `idx_action` (`current_action`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- User Actions (for unfinished task detection)
CREATE TABLE IF NOT EXISTS `user_actions` (
  `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
  `staff_id` INT NOT NULL,
  `action_type` VARCHAR(50) NOT NULL,
  `entity_type` VARCHAR(50),
  `entity_id` VARCHAR(100),
  `action_data` JSON,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_staff_actions` (`staff_id`, `created_at`),
  INDEX `idx_entity` (`entity_type`, `entity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Predictive Search Cache (store predictions for fast access)
CREATE TABLE IF NOT EXISTS `predictive_search_cache` (
  `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
  `staff_id` INT NOT NULL,
  `cache_key` VARCHAR(200) NOT NULL,
  `predictions` JSON NOT NULL,
  `confidence_score` DECIMAL(3,2),
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `expires_at` TIMESTAMP,
  UNIQUE KEY `unique_cache` (`staff_id`, `cache_key`),
  INDEX `idx_expiry` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Search Performance Metrics
CREATE TABLE IF NOT EXISTS `search_performance_metrics` (
  `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
  `date` DATE NOT NULL,
  `hour` TINYINT NOT NULL,
  `staff_id` INT,
  `total_searches` INT DEFAULT 0,
  `successful_searches` INT DEFAULT 0,
  `avg_response_time_ms` INT,
  `avg_confidence_score` DECIMAL(3,2),
  `prediction_accuracy` DECIMAL(5,2),
  UNIQUE KEY `unique_metric` (`date`, `hour`, `staff_id`),
  INDEX `idx_date` (`date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Indexes for Performance
-- ============================================================================

-- Composite indexes for common queries
ALTER TABLE `user_behavior_events`
ADD INDEX `idx_staff_type_time` (`staff_id`, `event_type`, `timestamp`);

ALTER TABLE `search_analytics`
ADD INDEX `idx_staff_context_time` (`staff_id`, `search_context`, `searched_at`);

ALTER TABLE `search_workflow_patterns`
ADD INDEX `idx_staff_action_time` (`staff_id`, `current_action`, `created_at`);

-- ============================================================================
-- Initial Data / Test Data
-- ============================================================================

-- Sample search analytics (for testing prediction algorithms)
INSERT INTO `search_analytics` (staff_id, search_query, search_context, page_context, result_clicked, searched_at) VALUES
(1, 'urgent emails', 'email', '/dashboard', 1, DATE_SUB(NOW(), INTERVAL 1 HOUR)),
(1, 'order #12345', 'order', '/emails', 1, DATE_SUB(NOW(), INTERVAL 2 HOUR)),
(1, 'low stock products', 'product', '/dashboard', 1, DATE_SUB(NOW(), INTERVAL 3 HOUR)),
(1, 'customer John Smith', 'customer', '/orders', 1, DATE_SUB(NOW(), INTERVAL 1 DAY)),
(1, 'urgent emails', 'email', '/dashboard', 1, DATE_SUB(NOW(), INTERVAL 1 DAY));

-- Sample workflow patterns
INSERT INTO `search_workflow_patterns` (staff_id, current_action, next_search_query, next_search_context, time_to_next_search) VALUES
(1, 'page_view_dashboard', 'urgent emails', 'email', 30),
(1, 'search_email', 'related order', 'order', 120),
(1, 'search_order', 'customer details', 'customer', 60);

-- ============================================================================
-- Maintenance & Cleanup Procedures
-- ============================================================================

-- Event to clean old behavior events (keep 90 days)
DROP EVENT IF EXISTS `cleanup_old_behavior_events`;
CREATE EVENT `cleanup_old_behavior_events`
ON SCHEDULE EVERY 1 DAY
DO
  DELETE FROM `user_behavior_events`
  WHERE `timestamp` < DATE_SUB(NOW(), INTERVAL 90 DAY)
  LIMIT 10000;

-- Event to clean expired prediction cache
DROP EVENT IF EXISTS `cleanup_expired_predictions`;
CREATE EVENT `cleanup_expired_predictions`
ON SCHEDULE EVERY 1 HOUR
DO
  DELETE FROM `predictive_search_cache`
  WHERE `expires_at` < NOW()
  LIMIT 1000;

-- Event to aggregate search metrics daily
DROP EVENT IF EXISTS `aggregate_search_metrics`;
CREATE EVENT `aggregate_search_metrics`
ON SCHEDULE EVERY 1 HOUR
DO
BEGIN
  INSERT INTO `search_performance_metrics` (
    date, hour, staff_id, total_searches, successful_searches, avg_response_time_ms
  )
  SELECT
    DATE(searched_at) as date,
    HOUR(searched_at) as hour,
    staff_id,
    COUNT(*) as total_searches,
    SUM(result_clicked) as successful_searches,
    AVG(time_to_click) as avg_response_time_ms
  FROM `search_analytics`
  WHERE searched_at >= DATE_SUB(NOW(), INTERVAL 2 HOUR)
  AND searched_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)
  GROUP BY DATE(searched_at), HOUR(searched_at), staff_id
  ON DUPLICATE KEY UPDATE
    total_searches = VALUES(total_searches),
    successful_searches = VALUES(successful_searches),
    avg_response_time_ms = VALUES(avg_response_time_ms);
END;

-- ============================================================================
-- Views for Analytics
-- ============================================================================

-- Popular searches view
CREATE OR REPLACE VIEW `v_popular_searches` AS
SELECT
  search_query,
  search_context,
  COUNT(DISTINCT staff_id) as user_count,
  COUNT(*) as search_count,
  AVG(result_clicked) as success_rate,
  MAX(searched_at) as last_searched
FROM `search_analytics`
WHERE searched_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
GROUP BY search_query, search_context
HAVING search_count >= 5
ORDER BY user_count DESC, search_count DESC;

-- User search effectiveness view
CREATE OR REPLACE VIEW `v_user_search_effectiveness` AS
SELECT
  staff_id,
  COUNT(*) as total_searches,
  AVG(result_clicked) as effectiveness,
  AVG(time_to_click) as avg_time_to_click,
  COUNT(DISTINCT search_context) as contexts_used
FROM `search_analytics`
WHERE searched_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
GROUP BY staff_id;

-- ============================================================================
-- Complete!
-- ============================================================================

-- Verify tables created
SELECT
  TABLE_NAME,
  TABLE_ROWS,
  CREATE_TIME
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
AND TABLE_NAME IN (
  'user_behavior_events',
  'search_analytics',
  'search_workflow_patterns',
  'user_actions',
  'predictive_search_cache',
  'search_performance_metrics'
);
