-- Create cis_user_events table for granular behavior logging
CREATE TABLE IF NOT EXISTS `cis_user_events` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT NULL,
  `session_id` VARCHAR(128) NULL,
  `event_type` VARCHAR(64) NOT NULL,
  `event_data` JSON NULL,
  `page_url` VARCHAR(1024) NULL,
  `occurred_at_ms` BIGINT NULL,
  `ip_address` VARCHAR(64) NULL,
  `user_agent` VARCHAR(512) NULL,
  `trace_id` VARCHAR(64) NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_time` (`user_id`, `occurred_at_ms`),
  KEY `idx_session_time` (`session_id`, `occurred_at_ms`),
  KEY `idx_event_type_time` (`event_type`, `occurred_at_ms`),
  KEY `idx_page_url` (`page_url`(191))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
