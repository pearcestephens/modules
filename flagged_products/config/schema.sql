-- Flagged Products Anti-Cheat Database Schema
-- Creates tables for audit logging and security tracking

-- Completion attempts with full security context
CREATE TABLE IF NOT EXISTS `flagged_products_completion_attempts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `outlet_id` varchar(100) NOT NULL,
  `product_id` varchar(100) NOT NULL,
  `qty_before` int(11) NOT NULL,
  `qty_after` int(11) NOT NULL,
  `time_spent_seconds` decimal(10,2) NOT NULL,
  `had_focus` tinyint(1) DEFAULT 1,
  `tab_switches` int(11) DEFAULT 0,
  `devtools_detected` tinyint(1) DEFAULT 0,
  `extensions_detected` int(11) DEFAULT 0,
  `suspicious_timing` tinyint(1) DEFAULT 0,
  `mouse_movements` int(11) DEFAULT 0,
  `security_score` int(11) DEFAULT 100,
  `ip_address` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_outlet` (`outlet_id`),
  KEY `idx_product` (`product_id`),
  KEY `idx_created` (`created_at`),
  KEY `idx_security_score` (`security_score`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Detailed audit log for all suspicious activities
CREATE TABLE IF NOT EXISTS `flagged_products_audit_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `outlet_id` varchar(100) DEFAULT NULL,
  `activity_type` varchar(50) NOT NULL,
  `details` text,
  `severity` enum('low','medium','high','critical') DEFAULT 'medium',
  `ip_address` varchar(50) DEFAULT NULL,
  `user_agent` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_activity` (`activity_type`),
  KEY `idx_severity` (`severity`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Gamification: Points and achievements
CREATE TABLE IF NOT EXISTS `flagged_products_points` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `outlet_id` varchar(100) NOT NULL,
  `points_earned` int(11) NOT NULL,
  `reason` varchar(255) NOT NULL,
  `accuracy_percentage` decimal(5,2) DEFAULT NULL,
  `streak_days` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_outlet` (`outlet_id`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- User achievements/badges
CREATE TABLE IF NOT EXISTS `flagged_products_achievements` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `achievement_code` varchar(50) NOT NULL,
  `achievement_name` varchar(255) NOT NULL,
  `achievement_description` text,
  `points_awarded` int(11) DEFAULT 0,
  `earned_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_achievement` (`user_id`,`achievement_code`),
  KEY `idx_user` (`user_id`),
  KEY `idx_earned` (`earned_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Leaderboard rankings (updated daily)
CREATE TABLE IF NOT EXISTS `flagged_products_leaderboard` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `outlet_id` varchar(100) NOT NULL,
  `period_type` enum('daily','weekly','monthly','all_time') NOT NULL,
  `period_date` date NOT NULL,
  `total_points` int(11) DEFAULT 0,
  `accuracy_percentage` decimal(5,2) DEFAULT NULL,
  `products_completed` int(11) DEFAULT 0,
  `rank_outlet` int(11) DEFAULT NULL,
  `rank_company` int(11) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_period` (`user_id`,`period_type`,`period_date`),
  KEY `idx_outlet_period` (`outlet_id`,`period_type`,`period_date`),
  KEY `idx_rank_outlet` (`rank_outlet`),
  KEY `idx_rank_company` (`rank_company`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- AI insights and recommendations
CREATE TABLE IF NOT EXISTS `flagged_products_ai_insights` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `outlet_id` varchar(100) NOT NULL,
  `insight_type` varchar(50) NOT NULL COMMENT 'pattern_detected, improvement_suggestion, praise, warning',
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `data_json` text COMMENT 'Supporting data in JSON format',
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_outlet` (`outlet_id`),
  KEY `idx_type` (`insight_type`),
  KEY `idx_read` (`is_read`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Store statistics cache (updated hourly)
CREATE TABLE IF NOT EXISTS `flagged_products_store_stats` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `outlet_id` varchar(100) NOT NULL,
  `stat_date` date NOT NULL,
  `total_flagged` int(11) DEFAULT 0,
  `total_completed` int(11) DEFAULT 0,
  `accuracy_percentage` decimal(5,2) DEFAULT NULL,
  `avg_time_seconds` decimal(10,2) DEFAULT NULL,
  `top_performer_id` int(11) DEFAULT NULL,
  `most_inaccurate_product_id` varchar(100) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_outlet_date` (`outlet_id`,`stat_date`),
  KEY `idx_outlet` (`outlet_id`),
  KEY `idx_date` (`stat_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add indexes to existing flagged_products table
ALTER TABLE `flagged_products` 
  ADD INDEX IF NOT EXISTS `idx_outlet_completed` (`outlet`, `date_completed_stocktake`),
  ADD INDEX IF NOT EXISTS `idx_reason` (`reason`),
  ADD INDEX IF NOT EXISTS `idx_date_flagged` (`date_flagged`),
  ADD INDEX IF NOT EXISTS `idx_staff` (`completed_by_staff`);
