-- Approval Threshold Configuration Tables
-- Migration: 2025-10-31-approval-thresholds
-- Description: Creates tables for storing default and outlet-specific approval thresholds

-- System configuration table (if doesn't exist)
CREATE TABLE IF NOT EXISTS `system_config` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `config_key` VARCHAR(100) NOT NULL,
  `config_value` TEXT NOT NULL,
  `description` VARCHAR(255) DEFAULT NULL,
  `updated_by` CHAR(36) DEFAULT NULL,
  `updated_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `config_key` (`config_key`),
  KEY `updated_by` (`updated_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Approval threshold overrides (outlet-specific)
CREATE TABLE IF NOT EXISTS `approval_threshold_overrides` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `outlet_id` CHAR(36) NOT NULL,
  `thresholds` JSON NOT NULL,
  `created_by` CHAR(36) NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_by` CHAR(36) DEFAULT NULL,
  `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `outlet_id` (`outlet_id`),
  KEY `created_by` (`created_by`),
  KEY `updated_by` (`updated_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default approval thresholds
INSERT INTO `system_config` (`config_key`, `config_value`, `description`, `updated_at`)
VALUES (
  'approval_thresholds',
  '{
    "1": {
      "min_amount": 0,
      "max_amount": 1000,
      "required_approvers": 1,
      "roles": ["manager"]
    },
    "2": {
      "min_amount": 1000,
      "max_amount": 2500,
      "required_approvers": 1,
      "roles": ["manager", "finance"]
    },
    "3": {
      "min_amount": 2500,
      "max_amount": 5000,
      "required_approvers": 2,
      "roles": ["manager", "finance"]
    },
    "4": {
      "min_amount": 5000,
      "max_amount": 10000,
      "required_approvers": 2,
      "roles": ["finance", "admin"]
    },
    "5": {
      "min_amount": 10000,
      "max_amount": null,
      "required_approvers": 3,
      "roles": ["admin"]
    }
  }',
  'Default approval thresholds for purchase orders',
  NOW()
)
ON DUPLICATE KEY UPDATE
  config_value = VALUES(config_value),
  updated_at = NOW();

-- Add comments
ALTER TABLE `system_config`
  COMMENT = 'System-wide configuration key-value pairs';

ALTER TABLE `approval_threshold_overrides`
  COMMENT = 'Outlet-specific approval threshold overrides';

-- Verification queries
SELECT 'System Config Table Created' AS Status;
SELECT 'Approval Threshold Overrides Table Created' AS Status;
SELECT 'Default Thresholds Inserted' AS Status;

-- Display current configuration
SELECT
  config_key,
  LEFT(config_value, 100) AS config_preview,
  updated_at
FROM system_config
WHERE config_key = 'approval_thresholds';
