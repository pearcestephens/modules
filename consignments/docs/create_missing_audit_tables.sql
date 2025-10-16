-- Create missing audit/logging tables for transfer submission
-- Run this: mysql < create_missing_audit_tables.sql

CREATE TABLE IF NOT EXISTS `inventory_audit_log` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `transfer_id` int(11) DEFAULT NULL,
  `product_id` bigint(20) unsigned NOT NULL,
  `outlet_id` varchar(100) NOT NULL,
  `action` enum('counted','adjusted','received','sent') NOT NULL,
  `quantity_before` int(11) NOT NULL DEFAULT 0,
  `quantity_after` int(11) NOT NULL DEFAULT 0,
  `quantity_change` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `session_id` varchar(255) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_transfer_id` (`transfer_id`),
  KEY `idx_product_outlet` (`product_id`, `outlet_id`),
  KEY `idx_action` (`action`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Inventory movement audit trail';

CREATE TABLE IF NOT EXISTS `transfer_submissions_log` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `transfer_id` int(11) NOT NULL,
  `submitted_by` int(11) DEFAULT NULL,
  `submission_method` enum('web','api','mobile') NOT NULL DEFAULT 'web',
  `products_count` int(11) NOT NULL,
  `total_items` int(11) NOT NULL,
  `validation_status` enum('passed','failed','warning') NOT NULL,
  `validation_errors` text DEFAULT NULL,
  `processing_time_ms` int(11) DEFAULT NULL,
  `session_id` varchar(255) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_transfer_id` (`transfer_id`),
  KEY `idx_submitted_by` (`submitted_by`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Transfer submission tracking and metrics';
