-- ============================================================================
-- Client Error Log Table
-- Stores JavaScript and AJAX errors for debugging and monitoring
-- ============================================================================

CREATE TABLE IF NOT EXISTS `client_error_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `level` enum('ERROR','WARNING','INFO','DEBUG') NOT NULL DEFAULT 'ERROR',
  `message` varchar(500) NOT NULL,
  `context_json` text,
  `url` varchar(500) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `username` varchar(100) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_level` (`level`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_level_created` (`level`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
COMMENT='Client-side error logging for JavaScript and AJAX failures';

-- ============================================================================
-- Sample queries for monitoring
-- ============================================================================

-- Recent errors (last 24 hours)
-- SELECT * FROM client_error_log 
-- WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) 
-- ORDER BY created_at DESC;

-- Error frequency by level
-- SELECT level, COUNT(*) as count, MAX(created_at) as last_occurrence
-- FROM client_error_log
-- WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
-- GROUP BY level;

-- Most common errors
-- SELECT message, COUNT(*) as count, MAX(created_at) as last_occurrence
-- FROM client_error_log
-- WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
-- GROUP BY message
-- ORDER BY count DESC
-- LIMIT 20;

-- User with most errors
-- SELECT username, user_id, COUNT(*) as error_count
-- FROM client_error_log
-- WHERE user_id IS NOT NULL
-- AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
-- GROUP BY username, user_id
-- ORDER BY error_count DESC
-- LIMIT 10;
