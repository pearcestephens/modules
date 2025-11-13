-- Align core logger tables to Logger.php expectations (create if missing)

CREATE TABLE IF NOT EXISTS `cis_action_log` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `actor_type` VARCHAR(32) NOT NULL,
  `actor_id` INT NULL,
  `actor_name` VARCHAR(255) NULL,
  `action_category` VARCHAR(64) NOT NULL,
  `action_type` VARCHAR(64) NOT NULL,
  `action_result` VARCHAR(32) NOT NULL,
  `entity_type` VARCHAR(64) NULL,
  `entity_id` VARCHAR(64) NULL,
  `context_json` JSON NULL,
  `metadata_json` JSON NULL,
  `ip_address` VARCHAR(64) NULL,
  `user_agent` VARCHAR(512) NULL,
  `request_method` VARCHAR(16) NULL,
  `request_url` VARCHAR(1024) NULL,
  `session_id` VARCHAR(128) NULL,
  `execution_time_ms` INT NULL,
  `memory_usage_mb` DECIMAL(8,2) NULL,
  `trace_id` VARCHAR(64) NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_actor_time` (`actor_id`, `created_at`),
  KEY `idx_category_time` (`action_category`, `created_at`),
  KEY `idx_trace` (`trace_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cis_security_events` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `event_type` VARCHAR(64) NOT NULL,
  `severity` VARCHAR(32) NOT NULL,
  `user_id` INT NULL,
  `ip_address` VARCHAR(64) NULL,
  `user_agent` VARCHAR(512) NULL,
  `threat_indicators` JSON NULL,
  `action_taken` VARCHAR(255) NULL,
  `related_action_id` BIGINT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_time` (`user_id`, `created_at`),
  KEY `idx_event_time` (`event_type`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cis_performance_metrics` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `metric_type` VARCHAR(64) NOT NULL,
  `metric_name` VARCHAR(128) NOT NULL,
  `value` DECIMAL(12,3) NOT NULL,
  `unit` VARCHAR(16) NOT NULL,
  `page_url` VARCHAR(1024) NULL,
  `user_id` INT NULL,
  `outlet_id` INT NULL,
  `context_json` JSON NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_metric_time` (`metric_type`, `created_at`),
  KEY `idx_user_time` (`user_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cis_ai_context` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `context_type` VARCHAR(64) NOT NULL,
  `source_system` VARCHAR(64) NOT NULL,
  `user_id` INT NULL,
  `outlet_id` INT NULL,
  `prompt` MEDIUMTEXT NULL,
  `response` MEDIUMTEXT NULL,
  `reasoning` MEDIUMTEXT NULL,
  `input_data` JSON NULL,
  `output_data` JSON NULL,
  `confidence_score` DECIMAL(5,3) NULL,
  `tags` JSON NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_context_time` (`context_type`, `created_at`),
  KEY `idx_user_time` (`user_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cis_bot_pipeline_log` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `bot_name` VARCHAR(64) NOT NULL,
  `pipeline_stage` VARCHAR(64) NOT NULL,
  `status` VARCHAR(32) NOT NULL,
  `input_data` JSON NULL,
  `output_data` JSON NULL,
  `error_message` TEXT NULL,
  `execution_time_ms` INT NULL,
  `tokens_used` INT NULL,
  `trace_id` VARCHAR(64) NULL,
  `completed_at` DATETIME NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_bot_stage_time` (`bot_name`, `pipeline_stage`, `created_at`),
  KEY `idx_trace` (`trace_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
