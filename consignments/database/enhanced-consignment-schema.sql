-- Enhanced Consignment Upload System Database Schema
-- 
-- Creates all necessary tables for the enhanced consignment upload system
-- following the Consignments API Playbook specifications
--
-- This includes:
-- 1. Queue consignment shadow tables
-- 2. Progress tracking tables  
-- 3. State transition logging
-- 4. Product-level progress tracking
--
-- @package CIS\Consignments\Database
-- @version 2.0.0

-- ============================================================================
-- QUEUE CONSIGNMENT SHADOW TABLES (API Playbook Section 7.1)
-- ============================================================================

-- Main queue consignments table - 1:1 with transfer consignment record
CREATE TABLE IF NOT EXISTS `queue_consignments` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `transfer_id` INT UNSIGNED NOT NULL COMMENT 'Link to transfers.id',
  `vend_consignment_id` VARCHAR(100) NOT NULL COMMENT 'Lightspeed consignment UUID',
  `outlet_from_id` VARCHAR(100) NOT NULL COMMENT 'Source outlet UUID (Vend)',
  `outlet_to_id` VARCHAR(100) NOT NULL COMMENT 'Destination outlet UUID (Vend)',
  `status` ENUM('OPEN', 'IN_TRANSIT', 'RECEIVED') NOT NULL DEFAULT 'OPEN' COMMENT 'Lightspeed status',
  `vendor_version` INT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Optimistic locking version',
  `sync_status` ENUM('pending', 'synced', 'error') NOT NULL DEFAULT 'pending',
  `last_sync_at` TIMESTAMP NULL DEFAULT NULL,
  `sync_error` TEXT NULL COMMENT 'Last sync error message',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  UNIQUE KEY `uniq_transfer_id` (`transfer_id`),
  UNIQUE KEY `uniq_vend_consignment_id` (`vend_consignment_id`),
  INDEX `idx_status` (`status`),
  INDEX `idx_sync_status` (`sync_status`),
  INDEX `idx_outlet_from` (`outlet_from_id`),
  INDEX `idx_outlet_to` (`outlet_to_id`),
  INDEX `idx_created_at` (`created_at`),
  
  CONSTRAINT `fk_queue_consignments_transfer` 
    FOREIGN KEY (`transfer_id`) REFERENCES `transfers` (`id`) 
    ON DELETE CASCADE ON UPDATE CASCADE
    
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
COMMENT='Queue consignments shadow table - 1:1 with transfer consignment record';

-- Queue consignment products table - line items with desired vs received counts
CREATE TABLE IF NOT EXISTS `queue_consignment_products` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `queue_consignment_id` BIGINT UNSIGNED NOT NULL,
  `vend_product_id` VARCHAR(100) NOT NULL COMMENT 'Lightspeed product UUID',
  `product_sku` VARCHAR(100) NOT NULL COMMENT 'Product SKU for reference',
  `expected_count` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Desired quantity',
  `received_count` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Actually received quantity',
  `sync_status` ENUM('pending', 'synced', 'error') NOT NULL DEFAULT 'pending',
  `last_sync_at` TIMESTAMP NULL DEFAULT NULL,
  `sync_error` TEXT NULL COMMENT 'Last sync error message',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  INDEX `idx_queue_consignment` (`queue_consignment_id`),
  INDEX `idx_vend_product` (`vend_product_id`),
  INDEX `idx_product_sku` (`product_sku`),
  INDEX `idx_sync_status` (`sync_status`),
  
  CONSTRAINT `fk_queue_consignment_products_consignment` 
    FOREIGN KEY (`queue_consignment_id`) REFERENCES `queue_consignments` (`id`) 
    ON DELETE CASCADE ON UPDATE CASCADE
    
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
COMMENT='Queue consignment products - line items with desired vs received counts';

-- Queue consignment state transitions table - immutable log of state movements
CREATE TABLE IF NOT EXISTS `queue_consignment_state_transitions` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `transfer_id` INT UNSIGNED NOT NULL,
  `from_state` VARCHAR(50) NULL COMMENT 'Previous state',
  `to_state` VARCHAR(50) NOT NULL COMMENT 'New state',
  `source` ENUM('cis_sync', 'webhook', 'force_resync', 'manual') NOT NULL COMMENT 'What triggered the change',
  `metadata` JSON NULL COMMENT 'Additional context and details',
  `user_id` INT UNSIGNED NULL COMMENT 'User who triggered the change (if manual)',
  `occurred_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  
  INDEX `idx_transfer_id` (`transfer_id`),
  INDEX `idx_from_state` (`from_state`),
  INDEX `idx_to_state` (`to_state`),
  INDEX `idx_source` (`source`),
  INDEX `idx_occurred_at` (`occurred_at`),
  
  CONSTRAINT `fk_queue_state_transitions_transfer` 
    FOREIGN KEY (`transfer_id`) REFERENCES `transfers` (`id`) 
    ON DELETE CASCADE ON UPDATE CASCADE
    
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
COMMENT='Immutable log of consignment state movements and causes';

-- ============================================================================
-- PROGRESS TRACKING TABLES (For Enhanced Upload System)
-- ============================================================================

-- Main progress tracking table for consignment uploads
CREATE TABLE IF NOT EXISTS `consignment_upload_progress` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `transfer_id` INT UNSIGNED NOT NULL,
  `session_id` VARCHAR(64) NOT NULL COMMENT 'Unique session identifier for this upload',
  `status` ENUM('pending', 'creating_consignment', 'uploading_products', 'updating_state', 'completed', 'failed') NOT NULL DEFAULT 'pending',
  `total_products` INT UNSIGNED NOT NULL DEFAULT 0,
  `completed_products` INT UNSIGNED NOT NULL DEFAULT 0,
  `failed_products` INT UNSIGNED NOT NULL DEFAULT 0,
  `current_operation` VARCHAR(255) NULL COMMENT 'Current operation description',
  `estimated_completion` DATETIME NULL COMMENT 'Estimated completion time',
  `performance_metrics` JSON NULL COMMENT 'Performance and timing metrics',
  `error_message` TEXT NULL COMMENT 'Error message if status is failed',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  UNIQUE KEY `uniq_transfer_session` (`transfer_id`, `session_id`),
  INDEX `idx_status` (`status`),
  INDEX `idx_updated_at` (`updated_at`),
  INDEX `idx_session_id` (`session_id`),
  
  CONSTRAINT `fk_upload_progress_transfer` 
    FOREIGN KEY (`transfer_id`) REFERENCES `transfers` (`id`) 
    ON DELETE CASCADE ON UPDATE CASCADE
    
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
COMMENT='Main progress tracking for consignment uploads with SSE support';

-- Product-level progress tracking table
CREATE TABLE IF NOT EXISTS `consignment_product_progress` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `transfer_id` INT UNSIGNED NOT NULL,
  `session_id` VARCHAR(64) NOT NULL,
  `product_id` VARCHAR(100) NOT NULL COMMENT 'CIS product ID',
  `sku` VARCHAR(100) NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `status` ENUM('pending', 'processing', 'completed', 'failed', 'skipped') NOT NULL DEFAULT 'pending',
  `vend_product_id` VARCHAR(100) NULL COMMENT 'Lightspeed product UUID after upload',
  `error_message` TEXT NULL COMMENT 'Error message if upload failed',
  `retry_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `processing_time_ms` INT UNSIGNED NULL COMMENT 'Time taken to process this product',
  `processed_at` TIMESTAMP NULL COMMENT 'When this product was processed',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  
  UNIQUE KEY `uniq_transfer_session_product` (`transfer_id`, `session_id`, `product_id`),
  INDEX `idx_product_id` (`product_id`),
  INDEX `idx_sku` (`sku`),
  INDEX `idx_status` (`status`),
  INDEX `idx_processed_at` (`processed_at`),
  INDEX `idx_session_id` (`session_id`),
  
  CONSTRAINT `fk_product_progress_transfer` 
    FOREIGN KEY (`transfer_id`) REFERENCES `transfers` (`id`) 
    ON DELETE CASCADE ON UPDATE CASCADE
    
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
COMMENT='Product-level progress tracking for detailed SSE updates';

-- ============================================================================
-- QUEUE JOBS TABLE (Enhanced for Transfer Operations)
-- ============================================================================

-- Enhanced queue jobs table for transfer operations
CREATE TABLE IF NOT EXISTS `queue_jobs` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `job_type` VARCHAR(100) NOT NULL COMMENT 'transfer.create_consignment, transfer.sync_to_lightspeed, etc.',
  `payload` JSON NOT NULL COMMENT 'Job data and parameters',
  `status` ENUM('pending', 'processing', 'completed', 'failed', 'cancelled') NOT NULL DEFAULT 'pending',
  `priority` TINYINT UNSIGNED NOT NULL DEFAULT 5 COMMENT '1-10, 10 = highest priority',
  `attempts` INT UNSIGNED NOT NULL DEFAULT 0,
  `max_attempts` INT UNSIGNED NOT NULL DEFAULT 3,
  `last_error` TEXT NULL COMMENT 'Last error message',
  `worker_id` VARCHAR(100) NULL COMMENT 'ID of worker processing this job',
  `started_at` TIMESTAMP NULL COMMENT 'When job processing started',
  `completed_at` TIMESTAMP NULL COMMENT 'When job completed or failed',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  INDEX `idx_job_type` (`job_type`),
  INDEX `idx_status` (`status`),
  INDEX `idx_priority` (`priority`),
  INDEX `idx_created_at` (`created_at`),
  INDEX `idx_worker_id` (`worker_id`),
  INDEX `idx_status_priority` (`status`, `priority` DESC),
  
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
COMMENT='Enhanced queue jobs for transfer operations with priority and retry support';

-- ============================================================================
-- WEBHOOK EVENTS TABLE (For Lightspeed Integration)
-- ============================================================================

-- Queue webhook events table for inbound Lightspeed webhooks
CREATE TABLE IF NOT EXISTS `queue_webhook_events` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `webhook_type` VARCHAR(100) NOT NULL COMMENT 'consignment.updated, consignment.received, etc.',
  `payload` JSON NOT NULL COMMENT 'Full webhook payload from Lightspeed',
  `status` ENUM('pending', 'processing', 'completed', 'failed', 'ignored') NOT NULL DEFAULT 'pending',
  `related_transfer_id` INT UNSIGNED NULL COMMENT 'Transfer ID if identified',
  `related_consignment_id` VARCHAR(100) NULL COMMENT 'Lightspeed consignment ID',
  `processed_at` TIMESTAMP NULL,
  `error_message` TEXT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  
  INDEX `idx_webhook_type` (`webhook_type`),
  INDEX `idx_status` (`status`),
  INDEX `idx_related_transfer` (`related_transfer_id`),
  INDEX `idx_related_consignment` (`related_consignment_id`),
  INDEX `idx_created_at` (`created_at`),
  
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
COMMENT='Inbound webhook events from Lightspeed for consignment updates';

-- ============================================================================
-- PERFORMANCE OPTIMIZATION INDEXES
-- ============================================================================

-- Additional indexes for performance optimization
ALTER TABLE `transfers` 
ADD INDEX IF NOT EXISTS `idx_transfers_consignment_vend` (`consignment_id`, `vend_transfer_id`),
ADD INDEX IF NOT EXISTS `idx_transfers_state_updated` (`state`, `updated_at`);

-- ============================================================================
-- DATA VALIDATION CONSTRAINTS
-- ============================================================================

-- Ensure queue_consignments.vend_consignment_id is properly formatted (UUID-like)
ALTER TABLE `queue_consignments` 
ADD CONSTRAINT `chk_vend_consignment_id_format` 
CHECK (LENGTH(`vend_consignment_id`) >= 10 AND `vend_consignment_id` NOT LIKE '% %');

-- Ensure progress percentages are valid
ALTER TABLE `consignment_upload_progress` 
ADD CONSTRAINT `chk_progress_products` 
CHECK (`completed_products` + `failed_products` <= `total_products`);

-- Ensure retry counts are reasonable
ALTER TABLE `consignment_product_progress` 
ADD CONSTRAINT `chk_retry_count` 
CHECK (`retry_count` <= 10);

-- Ensure job priorities are in valid range
ALTER TABLE `queue_jobs` 
ADD CONSTRAINT `chk_job_priority` 
CHECK (`priority` BETWEEN 1 AND 10);

-- ============================================================================
-- INITIAL DATA AND CONFIGURATION
-- ============================================================================

-- Insert default configuration for enhanced upload system
INSERT IGNORE INTO `system_config` (`key`, `value`, `description`) VALUES
('consignment_upload_timeout', '1800', 'Maximum time in seconds for consignment upload process'),
('consignment_upload_max_retries', '3', 'Maximum number of retry attempts for failed product uploads'),
('consignment_upload_batch_size', '50', 'Number of products to upload in each batch'),
('sse_heartbeat_interval', '15', 'Heartbeat interval in seconds for SSE connections'),
('lightspeed_api_rate_limit', '100', 'Maximum API calls per minute to Lightspeed');

-- Create indexes for commonly queried system_config keys
ALTER TABLE `system_config` 
ADD INDEX IF NOT EXISTS `idx_config_key` (`key`);

-- ============================================================================
-- CLEANUP AND MAINTENANCE PROCEDURES
-- ============================================================================

-- Create event to cleanup old progress tracking records (optional)
-- This can be uncommented if automatic cleanup is desired

/*
DELIMITER $$

CREATE EVENT IF NOT EXISTS `cleanup_old_upload_progress`
ON SCHEDULE EVERY 1 DAY
STARTS CURRENT_TIMESTAMP
DO
BEGIN
  -- Delete upload progress records older than 30 days
  DELETE FROM `consignment_upload_progress` 
  WHERE `created_at` < DATE_SUB(NOW(), INTERVAL 30 DAY);
  
  -- Delete product progress records older than 30 days
  DELETE FROM `consignment_product_progress` 
  WHERE `created_at` < DATE_SUB(NOW(), INTERVAL 30 DAY);
  
  -- Delete completed queue jobs older than 7 days
  DELETE FROM `queue_jobs` 
  WHERE `status` IN ('completed', 'cancelled') 
  AND `completed_at` < DATE_SUB(NOW(), INTERVAL 7 DAY);
  
  -- Delete old webhook events older than 30 days
  DELETE FROM `queue_webhook_events` 
  WHERE `status` = 'completed' 
  AND `processed_at` < DATE_SUB(NOW(), INTERVAL 30 DAY);
END$$

DELIMITER ;
*/

-- ============================================================================
-- VERIFICATION QUERIES
-- ============================================================================

-- Verify table creation and structure
-- Run these queries to confirm everything was created correctly

/*
-- Check table existence
SELECT TABLE_NAME, TABLE_COMMENT 
FROM INFORMATION_SCHEMA.TABLES 
WHERE TABLE_SCHEMA = DATABASE() 
AND TABLE_NAME IN (
  'queue_consignments', 
  'queue_consignment_products', 
  'queue_consignment_state_transitions',
  'consignment_upload_progress',
  'consignment_product_progress',
  'queue_jobs',
  'queue_webhook_events'
);

-- Check foreign key constraints
SELECT 
  CONSTRAINT_NAME,
  TABLE_NAME,
  REFERENCED_TABLE_NAME,
  REFERENCED_COLUMN_NAME
FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
WHERE TABLE_SCHEMA = DATABASE() 
AND REFERENCED_TABLE_NAME IS NOT NULL
AND TABLE_NAME LIKE '%queue%' OR TABLE_NAME LIKE '%consignment%';

-- Check indexes
SELECT 
  TABLE_NAME,
  INDEX_NAME,
  COLUMN_NAME,
  INDEX_TYPE
FROM INFORMATION_SCHEMA.STATISTICS 
WHERE TABLE_SCHEMA = DATABASE() 
AND TABLE_NAME IN (
  'queue_consignments', 
  'consignment_upload_progress',
  'queue_jobs'
)
ORDER BY TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX;
*/