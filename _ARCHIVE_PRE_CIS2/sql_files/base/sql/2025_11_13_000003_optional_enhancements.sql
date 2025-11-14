-- Optional enhancements for behavior and security analytics

-- 1) Suspicious session flags (aggregates)
CREATE TABLE IF NOT EXISTS `cis_suspicious_sessions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `session_id` VARCHAR(128) NOT NULL,
  `user_id` INT NULL,
  `first_seen_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_seen_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `devtools_detected` TINYINT(1) DEFAULT 0,
  `rapid_clicks` TINYINT(1) DEFAULT 0,
  `abnormal_scroll` TINYINT(1) DEFAULT 0,
  `automation_signals` TINYINT(1) DEFAULT 0,
  `ip_changes` INT DEFAULT 0,
  `notes` TEXT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_session` (`session_id`),
  KEY `idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2) Page fingerprint performance aggregates
CREATE TABLE IF NOT EXISTS `cis_page_fingerprints` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `page_url` VARCHAR(1024) NOT NULL,
  `fingerprint` VARCHAR(64) NOT NULL,
  `avg_load_ms` INT NULL,
  `p95_load_ms` INT NULL,
  `samples` INT DEFAULT 0,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_page_fingerprint` (`page_url`(191), `fingerprint`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3) Indexes for user events scaling
ALTER TABLE `cis_user_events` 
  ADD KEY `idx_type_time` (`event_type`, `occurred_at_ms`),
  ADD KEY `idx_trace_time` (`trace_id`, `occurred_at_ms`);

-- If your environment uses cis_security_events, consider augmenting with review flags if not present
-- Example:
-- ALTER TABLE `cis_security_events` ADD COLUMN `is_false_positive` TINYINT(1) DEFAULT 0, ADD COLUMN `reviewed_at` DATETIME NULL;
