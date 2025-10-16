CREATE TABLE `queue_consignments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `vend_consignment_id` varchar(100) NOT NULL COMMENT 'Lightspeed consignment UUID',
  `vend_version` int(10) unsigned DEFAULT 0 COMMENT 'Optimistic locking version from Vend',
  `type` enum('SUPPLIER','OUTLET','RETURN','STOCKTAKE') NOT NULL COMMENT 'Consignment type',
  `status` enum('OPEN','SENT','DISPATCHED','RECEIVED','CANCELLED','STOCKTAKE','STOCKTAKE_SCHEDULED','STOCKTAKE_IN_PROGRESS','STOCKTAKE_IN_PROGRESS_PROCESSED','STOCKTAKE_COMPLETE') NOT NULL DEFAULT 'OPEN' COMMENT 'Current workflow state',
  `reference` varchar(255) DEFAULT NULL COMMENT 'PO number / Transfer reference',
  `name` text DEFAULT NULL COMMENT 'Internal notes / description',
  `source_outlet_id` varchar(100) DEFAULT NULL COMMENT 'Source outlet UUID (for OUTLET type)',
  `destination_outlet_id` varchar(100) DEFAULT NULL COMMENT 'Destination outlet UUID',
  `supplier_id` varchar(100) DEFAULT NULL COMMENT 'Supplier UUID (for SUPPLIER type)',
  `cis_user_id` int(10) unsigned DEFAULT NULL COMMENT 'CIS user who created this',
  `cis_purchase_order_id` int(10) unsigned DEFAULT NULL COMMENT 'Link to legacy PO table (if exists)',
  `cis_transfer_id` int(10) unsigned DEFAULT NULL COMMENT 'Link to legacy transfer table (if exists)',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `sent_at` timestamp NULL DEFAULT NULL COMMENT 'When marked SENT',
  `dispatched_at` timestamp NULL DEFAULT NULL COMMENT 'When marked DISPATCHED',
  `received_at` timestamp NULL DEFAULT NULL COMMENT 'When marked RECEIVED',
  `completed_at` timestamp NULL DEFAULT NULL COMMENT 'When fully processed (CIS inventory updated)',
  `trace_id` varchar(64) DEFAULT NULL COMMENT 'Request trace ID for debugging',
  `last_sync_at` timestamp NULL DEFAULT NULL COMMENT 'Last successful Vend API sync',
  `is_migrated` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Flag for historical migration data',
  `sync_source` enum('CIS','LIGHTSPEED','MIGRATION') NOT NULL DEFAULT 'CIS' COMMENT 'Origin of consignment data',
  `sync_last_pulled_at` datetime DEFAULT NULL COMMENT 'Last sync FROM Lightspeed',
  `sync_last_pushed_at` datetime DEFAULT NULL COMMENT 'Last sync TO Lightspeed',
  `created_by_user_id` bigint(20) unsigned DEFAULT NULL,
  `approved_for_lightspeed` tinyint(1) DEFAULT 0,
  `approved_by_user_id` bigint(20) unsigned DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `pushed_to_lightspeed_at` datetime DEFAULT NULL,
  `lightspeed_push_attempts` int(11) DEFAULT 0,
  `lightspeed_push_error` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `vend_consignment_id` (`vend_consignment_id`),
  KEY `idx_vend_consignment_id` (`vend_consignment_id`),
  KEY `idx_type_status` (`type`,`status`),
  KEY `idx_destination_outlet` (`destination_outlet_id`),
  KEY `idx_source_outlet` (`source_outlet_id`),
  KEY `idx_supplier` (`supplier_id`),
  KEY `idx_cis_user` (`cis_user_id`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_status_updated` (`status`,`updated_at`),
  KEY `idx_trace_id` (`trace_id`)
) ENGINE=InnoDB AUTO_INCREMENT=39644 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Master consignment records synced with Lightspeed - ADR-002';

CREATE TABLE `queue_dashboard_sessions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `session_token` char(64) NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `issued_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expires_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `last_seen_at` timestamp NULL DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `revoked_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_queue_dashboard_sessions_token` (`session_token`),
  KEY `idx_queue_dashboard_sessions_user` (`user_id`),
  KEY `idx_queue_dashboard_sessions_expires` (`expires_at`),
  CONSTRAINT `fk_queue_dashboard_sessions_user` FOREIGN KEY (`user_id`) REFERENCES `queue_dashboard_users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `queue_dashboard_users` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(120) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `display_name` varchar(190) DEFAULT NULL,
  `role` varchar(32) NOT NULL DEFAULT 'admin',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `last_login_at` timestamp NULL DEFAULT NULL,
  `last_login_ip` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_queue_dashboard_users_username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `queue_dlq` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `job_id` bigint(20) unsigned NOT NULL,
  `job_type` varchar(80) NOT NULL,
  `ref_id` varchar(128) DEFAULT NULL,
  `payload_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`payload_json`)),
  `attempts` int(10) unsigned NOT NULL,
  `last_error` text DEFAULT NULL,
  `dead_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_dead_type` (`job_type`,`dead_at`),
  KEY `idx_dead_ref` (`ref_id`),
  KEY `idx_job_id` (`job_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `queue_feature_endpoints` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `key` varchar(120) NOT NULL,
  `title` varchar(160) NOT NULL,
  `status` enum('enabled','disabled') NOT NULL DEFAULT 'enabled',
  `http_method` enum('POST','GET','PUT','PATCH') NOT NULL DEFAULT 'POST',
  `handler_type` enum('queue','sync') NOT NULL DEFAULT 'queue',
  `job_type` varchar(120) DEFAULT NULL,
  `payload_schema` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`payload_schema`)),
  `idempotency_mode` enum('header','field','off') NOT NULL DEFAULT 'header',
  `auth_mode` enum('open','bearer') NOT NULL DEFAULT 'bearer',
  `secret` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `key` (`key`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `queue_health` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `sampled_at` datetime NOT NULL,
  `tier` enum('green','amber','red') NOT NULL,
  `damage_score` decimal(5,2) NOT NULL DEFAULT 0.00,
  `metrics` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metrics`)),
  `notes` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_sampled` (`sampled_at`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `queue_inventory_adjustments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `outlet_id` bigint(20) unsigned NOT NULL,
  `variant_id` bigint(20) unsigned NOT NULL,
  `adjustment` int(11) NOT NULL COMMENT 'Positive or negative adjustment',
  `reason` text NOT NULL,
  `adjusted_by_user_id` bigint(20) unsigned NOT NULL,
  `consignment_id` bigint(20) unsigned DEFAULT NULL COMMENT 'If adjustment related to consignment',
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_outlet_variant` (`outlet_id`,`variant_id`),
  KEY `idx_consignment` (`consignment_id`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Manual stock adjustments audit trail';

CREATE TABLE `queue_job_log` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `job_id` bigint(20) unsigned NOT NULL,
  `job_type` varchar(80) NOT NULL,
  `event` enum('enqueued','reserved','running','retry','done','failed','dead','reaped') NOT NULL,
  `attempts` int(10) unsigned NOT NULL DEFAULT 0,
  `message` varchar(500) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_job` (`job_id`),
  KEY `idx_type_event_time` (`job_type`,`event`,`created_at`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `queue_jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `job_id` varchar(64) NOT NULL COMMENT 'Unique job identifier (UUID)',
  `job_type` varchar(64) NOT NULL COMMENT 'Type of job (vend_transfer, po_create, webhook_replay, etc.)',
  `queue_name` varchar(64) NOT NULL DEFAULT 'default' COMMENT 'Queue name for job routing',
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT 'Job data and parameters' CHECK (json_valid(`payload`)),
  `priority` tinyint(4) NOT NULL DEFAULT 5 COMMENT 'Job priority (1=highest, 10=lowest)',
  `status` enum('pending','processing','completed','failed','cancelled','dead_letter') NOT NULL DEFAULT 'pending',
  `attempts` tinyint(3) unsigned NOT NULL DEFAULT 0 COMMENT 'Number of processing attempts',
  `max_attempts` tinyint(3) unsigned NOT NULL DEFAULT 3 COMMENT 'Maximum retry attempts',
  `available_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'When job becomes available for processing',
  `started_at` timestamp NULL DEFAULT NULL COMMENT 'When job processing started',
  `completed_at` timestamp NULL DEFAULT NULL COMMENT 'When job completed successfully',
  `finished_at` timestamp NULL DEFAULT NULL,
  `failed_at` timestamp NULL DEFAULT NULL COMMENT 'When job finally failed',
  `next_retry_at` timestamp NULL DEFAULT NULL COMMENT 'Next retry time (exponential backoff)',
  `worker_id` varchar(128) DEFAULT NULL COMMENT 'ID of worker processing this job',
  `heartbeat_at` timestamp NULL DEFAULT NULL COMMENT 'Last worker heartbeat',
  `heartbeat_timeout` int(10) unsigned NOT NULL DEFAULT 300 COMMENT 'Heartbeat timeout in seconds',
  `leased_until` timestamp NULL DEFAULT NULL,
  `last_error` text DEFAULT NULL COMMENT 'Last error message',
  `error_details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Detailed error information' CHECK (json_valid(`error_details`)),
  `created_by_user` int(10) unsigned DEFAULT NULL COMMENT 'User who created this job',
  `processing_log` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Log of processing attempts and outcomes' CHECK (json_valid(`processing_log`)),
  `result_meta` longtext DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `job_id` (`job_id`),
  KEY `idx_status_available` (`status`,`available_at`),
  KEY `idx_job_type_status` (`job_type`,`status`),
  KEY `idx_queue_name_priority` (`queue_name`,`priority`,`available_at`),
  KEY `idx_worker_heartbeat` (`worker_id`,`heartbeat_at`),
  KEY `idx_retry_schedule` (`next_retry_at`,`status`),
  KEY `idx_created_user` (`created_by_user`),
  KEY `idx_job_lookup` (`job_id`,`job_type`),
  KEY `idx_queue_jobs_created_at` (`created_at`),
  KEY `idx_queue_jobs_claim` (`status`,`available_at`,`priority`,`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=19679 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Enterprise job queue with retry and DLQ support';

CREATE TABLE `queue_jobs_dlq` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `job_id` bigint(20) unsigned DEFAULT NULL,
  `job_type` varchar(100) NOT NULL,
  `attempts` int(11) NOT NULL DEFAULT 0,
  `failure_reason` text DEFAULT NULL,
  `payload_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`payload_json`)),
  `moved_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_type` (`job_type`),
  KEY `idx_moved` (`moved_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `queue_metrics` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `metric_name` varchar(255) NOT NULL,
  `value` decimal(20,6) NOT NULL,
  `labels` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`labels`)),
  `timestamp` int(10) unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_metric_name` (`metric_name`),
  KEY `idx_timestamp` (`timestamp`),
  KEY `idx_metric_time` (`metric_name`,`timestamp`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=97510 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `queue_migrations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) NOT NULL,
  `executed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `migration` (`migration`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `queue_performance_metrics` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `metric_name` varchar(100) NOT NULL,
  `metric_value` decimal(15,4) NOT NULL,
  `metric_unit` varchar(20) DEFAULT NULL,
  `tags` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`tags`)),
  `recorded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_metric_name` (`metric_name`),
  KEY `idx_recorded_at` (`recorded_at`),
  KEY `idx_name_time` (`metric_name`,`recorded_at`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci COMMENT='Performance metrics collection';

CREATE TABLE `queue_pipeline_execution_steps` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `execution_id` varchar(255) NOT NULL,
  `step_order` int(10) unsigned DEFAULT 0,
  `step_name` varchar(255) NOT NULL,
  `status` enum('pending','running','completed','failed','skipped') DEFAULT 'pending',
  `step_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`step_data`)),
  `duration_ms` decimal(10,2) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `completed_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_execution_id` (`execution_id`),
  KEY `idx_step_order` (`step_order`),
  KEY `idx_status` (`status`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `queue_pipeline_execution_steps_ibfk_1` FOREIGN KEY (`execution_id`) REFERENCES `queue_pipeline_executions` (`execution_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `queue_pipeline_executions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `execution_id` varchar(255) NOT NULL,
  `pipeline_id` varchar(255) NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`payload`)),
  `options` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`options`)),
  `status` enum('created','queued','running','background','completed','failed','timeout') DEFAULT 'created',
  `result` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`result`)),
  `execution_time_ms` decimal(10,2) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `started_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `execution_id` (`execution_id`),
  UNIQUE KEY `uk_execution_id` (`execution_id`),
  KEY `idx_pipeline_id` (`pipeline_id`),
  KEY `idx_status` (`status`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_completed_at` (`completed_at`),
  CONSTRAINT `queue_pipeline_executions_ibfk_1` FOREIGN KEY (`pipeline_id`) REFERENCES `queue_pipelines` (`pipeline_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `queue_pipeline_steps` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `pipeline_id` varchar(255) NOT NULL,
  `step_order` int(10) unsigned NOT NULL,
  `step_name` varchar(255) NOT NULL,
  `step_config` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`step_config`)),
  PRIMARY KEY (`id`),
  KEY `idx_pipeline_id` (`pipeline_id`),
  KEY `idx_step_order` (`step_order`),
  CONSTRAINT `queue_pipeline_steps_ibfk_1` FOREIGN KEY (`pipeline_id`) REFERENCES `queue_pipelines` (`pipeline_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `queue_pipeline_validation_rules` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `pipeline_id` varchar(255) NOT NULL,
  `field_name` varchar(255) NOT NULL,
  `rule_type` enum('required','type','min_length','max_length','pattern','enum') NOT NULL,
  `rule_value` varchar(500) DEFAULT NULL,
  `error_message` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_pipeline_id` (`pipeline_id`),
  KEY `idx_field_name` (`field_name`),
  CONSTRAINT `queue_pipeline_validation_rules_ibfk_1` FOREIGN KEY (`pipeline_id`) REFERENCES `queue_pipelines` (`pipeline_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `queue_pipelines` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `pipeline_id` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `label` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `handler_class` varchar(255) NOT NULL,
  `execution_mode` enum('sync','async','background','wait_for_completion') DEFAULT 'async',
  `timeout_seconds` int(10) unsigned DEFAULT 300,
  `retry_attempts` tinyint(3) unsigned DEFAULT 3,
  `config_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`config_json`)),
  `tags` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`tags`)),
  `enabled` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `pipeline_id` (`pipeline_id`),
  UNIQUE KEY `uk_pipeline_id` (`pipeline_id`),
  UNIQUE KEY `uk_name` (`name`),
  KEY `idx_enabled` (`enabled`),
  KEY `idx_execution_mode` (`execution_mode`),
  KEY `idx_handler_class` (`handler_class`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `queue_rate_limit_requests` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `limit_key` varchar(255) NOT NULL,
  `request_time` int(10) unsigned NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_key_time` (`limit_key`,`request_time`),
  KEY `idx_request_time` (`request_time`),
  KEY `idx_ip_address` (`ip_address`),
  KEY `idx_rate_limit_requests_cleanup` (`request_time`,`limit_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `queue_rate_limits` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `limit_key` varchar(255) NOT NULL,
  `tokens` decimal(10,2) NOT NULL DEFAULT 0.00,
  `max_tokens` int(10) unsigned NOT NULL,
  `refill_rate` decimal(10,4) NOT NULL,
  `last_refill` int(10) unsigned NOT NULL,
  `request_count` int(10) unsigned DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_request` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_limit_key` (`limit_key`),
  KEY `idx_last_request` (`last_request`),
  KEY `idx_last_refill` (`last_refill`),
  KEY `idx_rate_limits_key_refill` (`limit_key`,`last_refill`),
  KEY `idx_rate_limits_request_count` (`request_count`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `queue_recurring_jobs` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `job_type` varchar(255) NOT NULL,
  `job_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`job_data`)),
  `cron_expression` varchar(100) NOT NULL,
  `priority` tinyint(3) unsigned DEFAULT 5,
  `max_attempts` tinyint(3) unsigned DEFAULT 3,
  `enabled` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `last_run` timestamp NULL DEFAULT NULL,
  `next_run` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_name` (`name`),
  KEY `idx_job_type` (`job_type`),
  KEY `idx_enabled` (`enabled`),
  KEY `idx_next_run` (`next_run`),
  KEY `idx_last_run` (`last_run`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `queue_shadow_checkpoint` (
  `id` tinyint(4) NOT NULL DEFAULT 1,
  `last_legacy_id` bigint(20) unsigned NOT NULL DEFAULT 0,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `queue_sync_history` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `sync_type` varchar(50) NOT NULL COMMENT 'products, inventory, customers, sales, consignments',
  `date_from` datetime NOT NULL COMMENT 'Start of sync date range',
  `date_to` datetime NOT NULL COMMENT 'End of sync date range',
  `outlets` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Array of outlet IDs to sync (NULL = all)' CHECK (json_valid(`outlets`)),
  `initiated_by` bigint(20) unsigned NOT NULL COMMENT 'User ID who initiated sync',
  `status` enum('queued','processing','completed','failed','cancelled') NOT NULL DEFAULT 'queued',
  `items_processed` int(11) DEFAULT 0 COMMENT 'Number of items synced',
  `items_failed` int(11) DEFAULT 0 COMMENT 'Number of items failed',
  `error_message` text DEFAULT NULL COMMENT 'Error details if failed',
  `started_at` datetime DEFAULT NULL COMMENT 'When sync actually started',
  `completed_at` datetime DEFAULT NULL COMMENT 'When sync completed',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_sync_type` (`sync_type`),
  KEY `idx_status` (`status`),
  KEY `idx_initiated_by` (`initiated_by`),
  KEY `idx_date_range` (`date_from`,`date_to`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tracks historical sync requests and their progress';

CREATE TABLE `queue_system_health` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `check_type` varchar(50) NOT NULL,
  `status` enum('healthy','warning','critical') NOT NULL,
  `message` text DEFAULT NULL,
  `metrics` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metrics`)),
  `checked_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_check_type` (`check_type`),
  KEY `idx_status` (`status`),
  KEY `idx_checked_at` (`checked_at`)
) ENGINE=InnoDB AUTO_INCREMENT=2222 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci COMMENT='System health check results';

CREATE TABLE `queue_trace` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `trace_id` varchar(64) NOT NULL,
  `subject` enum('webhook','job','system') NOT NULL,
  `subject_id` varchar(64) DEFAULT NULL,
  `stage` varchar(64) NOT NULL,
  `level` varchar(16) NOT NULL DEFAULT 'info',
  `data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`data`)),
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_trace` (`trace_id`),
  KEY `idx_subject` (`subject`,`subject_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2302 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `queue_webhook_endpoints` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `name` varchar(120) NOT NULL,
  `token` char(24) NOT NULL,
  `status` enum('enabled','disabled') NOT NULL DEFAULT 'enabled',
  `mode` enum('open','hmac','bearer') NOT NULL DEFAULT 'open',
  `secret` varchar(255) DEFAULT NULL,
  `tolerance_sec` int(11) NOT NULL DEFAULT 300,
  `fanout_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `queue_webhook_events` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `webhook_id` varchar(64) NOT NULL,
  `webhook_type` varchar(100) NOT NULL,
  `status` enum('received','processing','completed','failed') NOT NULL DEFAULT 'received',
  `received_at` datetime NOT NULL DEFAULT current_timestamp(),
  `processed_at` datetime DEFAULT NULL,
  `queue_job_id` bigint(20) unsigned DEFAULT NULL,
  `hmac_valid` tinyint(1) DEFAULT NULL,
  `payload_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`payload_json`)),
  `headers_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`headers_json`)),
  `source_ip` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `error_message` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_webhook_id` (`webhook_id`),
  KEY `idx_type_status` (`webhook_type`,`status`),
  KEY `idx_received` (`received_at`),
  KEY `idx_qwe_status` (`status`),
  KEY `idx_qwe_created_at` (`created_at`),
  KEY `idx_qwe_queue_job_id` (`queue_job_id`)
) ENGINE=InnoDB AUTO_INCREMENT=19039 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `queue_worker_heartbeats` (
  `worker_id` varchar(50) NOT NULL,
  `hostname` varchar(100) NOT NULL,
  `process_id` int(11) NOT NULL,
  `last_heartbeat` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `jobs_processed` int(11) DEFAULT 0,
  `memory_usage_mb` decimal(10,2) DEFAULT 0.00,
  `cpu_usage_percent` decimal(5,2) DEFAULT 0.00,
  `status` enum('active','idle','stopping','dead') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`worker_id`),
  KEY `idx_last_heartbeat` (`last_heartbeat`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci COMMENT='Worker process heartbeat tracking';

CREATE TABLE `queue_worker_status` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `worker_id` varchar(50) NOT NULL,
  `event_type` enum('started','stopped','heartbeat','job_started','job_completed','error') NOT NULL,
  `event_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`event_data`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_worker_id` (`worker_id`),
  KEY `idx_event_type` (`event_type`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=130 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci COMMENT='Worker status event log';

CREATE TABLE `queue_workers` (
  `worker_id` varchar(64) NOT NULL,
  `started_at` datetime NOT NULL DEFAULT current_timestamp(),
  `heartbeat_at` datetime NOT NULL DEFAULT current_timestamp(),
  `info_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`info_json`)),
  PRIMARY KEY (`worker_id`),
  KEY `idx_heartbeat` (`heartbeat_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
