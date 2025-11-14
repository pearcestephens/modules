-- ============================================================================
-- Universal Search System - Database Migrations
-- "The Search of the Decade" - Makes Gmail Look Like Trash
-- ============================================================================

-- Search Analytics Table
CREATE TABLE IF NOT EXISTS `search_analytics` (
  `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
  `staff_id` INT NOT NULL,
  `query` VARCHAR(500) NOT NULL,
  `context` ENUM('all', 'emails', 'products', 'orders', 'customers') DEFAULT 'all',
  `total_results` INT DEFAULT 0,
  `response_time_ms` DECIMAL(10,2),
  `clicked_result_id` INT NULL,
  `clicked_result_type` VARCHAR(50) NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_staff_id` (`staff_id`),
  INDEX `idx_query` (`query`(255)),
  INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- AI Search Analytics Table
CREATE TABLE IF NOT EXISTS `ai_search_analytics` (
  `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
  `staff_id` INT NOT NULL,
  `query` TEXT NOT NULL,
  `interpretation` JSON,
  `confidence` DECIMAL(3,2),
  `response_time_ms` DECIMAL(10,2),
  `user_feedback` ENUM('helpful', 'not_helpful', 'neutral') NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_staff_id` (`staff_id`),
  INDEX `idx_confidence` (`confidence`),
  INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Search Saved Queries Table (for power users)
CREATE TABLE IF NOT EXISTS `search_saved_queries` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `staff_id` INT NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `query` VARCHAR(500) NOT NULL,
  `context` VARCHAR(50),
  `filters` JSON,
  `is_favorite` TINYINT DEFAULT 0,
  `use_count` INT DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_staff_id` (`staff_id`),
  INDEX `idx_is_favorite` (`is_favorite`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- FULL-TEXT SEARCH INDEXES (Lightning Fast!)
-- ============================================================================

-- Email Full-Text Index
ALTER TABLE `emails`
ADD FULLTEXT INDEX `ft_email_search` (`subject`, `body`, `from_name`, `to_name`);

-- Add sentiment and priority columns if they don't exist
ALTER TABLE `emails`
ADD COLUMN IF NOT EXISTS `sentiment_score` DECIMAL(3,2) DEFAULT 0.0 COMMENT 'AI sentiment: -1 (negative) to 1 (positive)',
ADD COLUMN IF NOT EXISTS `priority_level` ENUM('normal', 'urgent', 'low') DEFAULT 'normal',
ADD COLUMN IF NOT EXISTS `conversation_id` INT NULL COMMENT 'Thread conversation ID',
ADD INDEX `idx_sentiment` (`sentiment_score`),
ADD INDEX `idx_priority` (`priority_level`),
ADD INDEX `idx_conversation` (`conversation_id`);

-- Product Full-Text Index (if products table exists)
-- ALTER TABLE `products`
-- ADD FULLTEXT INDEX `ft_product_search` (`name`, `description`, `sku`, `barcode`);

-- Order Search Index (if orders table exists)
-- ALTER TABLE `orders`
-- ADD INDEX `idx_order_search` (`order_number`, `customer_id`, `status`, `created_at`);

-- Customer Search Index (if customers table exists)
-- ALTER TABLE `customers`
-- ADD FULLTEXT INDEX `ft_customer_search` (`name`, `email`, `phone`, `company`);

-- ============================================================================
-- SEARCH CONFIGURATION TABLE
-- ============================================================================

CREATE TABLE IF NOT EXISTS `search_config` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `config_key` VARCHAR(100) NOT NULL UNIQUE,
  `config_value` TEXT,
  `description` VARCHAR(255),
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default search configuration
INSERT INTO `search_config` (`config_key`, `config_value`, `description`) VALUES
('max_results_per_type', '50', 'Maximum results to return per search type'),
('suggestion_limit', '10', 'Maximum number of suggestions to show'),
('cache_ttl_seconds', '300', 'Cache time-to-live in seconds'),
('ai_cache_ttl_seconds', '3600', 'AI cache time-to-live in seconds'),
('fuzzy_threshold', '0.75', 'Fuzzy matching threshold (0-1)'),
('min_query_length', '2', 'Minimum query length for search'),
('enable_ai_mode', '1', 'Enable AI-powered search mode'),
('enable_analytics', '1', 'Enable search analytics tracking')
ON DUPLICATE KEY UPDATE config_value=VALUES(config_value);

-- ============================================================================
-- SEARCH POPULAR QUERIES CACHE TABLE
-- ============================================================================

CREATE TABLE IF NOT EXISTS `search_popular_queries` (
  `query` VARCHAR(500) PRIMARY KEY,
  `search_count` INT DEFAULT 1,
  `last_searched_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_search_count` (`search_count` DESC),
  INDEX `idx_last_searched` (`last_searched_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- PERFORMANCE OPTIMIZATION VIEWS
-- ============================================================================

-- View: Top Search Queries (Last 30 Days)
CREATE OR REPLACE VIEW `v_top_search_queries` AS
SELECT
    query,
    COUNT(*) as search_count,
    AVG(response_time_ms) as avg_response_time_ms,
    AVG(total_results) as avg_results,
    MAX(created_at) as last_searched_at
FROM search_analytics
WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
GROUP BY query
ORDER BY search_count DESC
LIMIT 100;

-- View: Failed Searches (No Results)
CREATE OR REPLACE VIEW `v_failed_searches` AS
SELECT
    query,
    context,
    COUNT(*) as fail_count,
    MAX(created_at) as last_failed_at
FROM search_analytics
WHERE total_results = 0
  AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
GROUP BY query, context
ORDER BY fail_count DESC
LIMIT 50;

-- View: Search Performance by Context
CREATE OR REPLACE VIEW `v_search_performance_by_context` AS
SELECT
    context,
    COUNT(*) as total_searches,
    AVG(response_time_ms) as avg_response_time_ms,
    AVG(total_results) as avg_results,
    SUM(CASE WHEN total_results = 0 THEN 1 ELSE 0 END) as zero_result_count,
    (SUM(CASE WHEN total_results = 0 THEN 1 ELSE 0 END) / COUNT(*) * 100) as zero_result_percentage
FROM search_analytics
WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
GROUP BY context;

-- ============================================================================
-- CLEANUP & MAINTENANCE
-- ============================================================================

-- Event: Clean old search analytics (keep 90 days)
DELIMITER $$
CREATE EVENT IF NOT EXISTS `cleanup_old_search_analytics`
ON SCHEDULE EVERY 1 DAY
DO BEGIN
  DELETE FROM search_analytics WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY);
  DELETE FROM ai_search_analytics WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY);
END$$
DELIMITER ;

-- ============================================================================
-- SUCCESS MESSAGE
-- ============================================================================

SELECT '✅ Universal Search Database Schema Created Successfully!' as status,
       'Search system is ready to make Gmail look like trash! 🚀' as message;
