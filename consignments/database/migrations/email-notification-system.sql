-- ===========================================================================
-- Consignments Email & Notification System - Database Migration
-- ===========================================================================
-- Version: 1.0.0
-- Created: 2025-11-08
-- Purpose: Email queue, template configuration, and audit logging
-- ===========================================================================

-- ---------------------------------------------------------------------------
-- 1. EMAIL NOTIFICATION QUEUE
-- ---------------------------------------------------------------------------
-- Stores outgoing emails for background processing
CREATE TABLE IF NOT EXISTS `consignment_notification_queue` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `recipient_email` VARCHAR(255) NOT NULL COMMENT 'Email address of recipient',
    `recipient_name` VARCHAR(255) NOT NULL COMMENT 'Name of recipient',
    `subject` VARCHAR(500) NOT NULL COMMENT 'Email subject line',
    `html_body` LONGTEXT NOT NULL COMMENT 'HTML email body',
    `text_body` TEXT NULL COMMENT 'Plain text email body (optional)',

    `priority` TINYINT UNSIGNED NOT NULL DEFAULT 3 COMMENT '1=urgent, 2=high, 3=normal, 4=low',
    `email_type` ENUM('internal', 'supplier') NOT NULL DEFAULT 'internal',

    `consignment_id` INT UNSIGNED NULL COMMENT 'Related consignment (optional)',
    `template_key` VARCHAR(100) NULL COMMENT 'Template used (if any)',
    `sent_by` INT UNSIGNED NULL COMMENT 'User who triggered send',

    `status` ENUM('pending', 'processing', 'sent', 'failed', 'cancelled') NOT NULL DEFAULT 'pending',
    `retry_count` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Number of send attempts',
    `last_error` TEXT NULL COMMENT 'Last error message (if failed)',

    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `processed_at` DATETIME NULL COMMENT 'When email was sent',
    `next_retry_at` DATETIME NULL COMMENT 'When to retry (if failed)',

    PRIMARY KEY (`id`),
    INDEX `idx_status_priority` (`status`, `priority`, `next_retry_at`),
    INDEX `idx_consignment` (`consignment_id`),
    INDEX `idx_template` (`template_key`),
    INDEX `idx_created` (`created_at`),
    INDEX `idx_email_type` (`email_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Email notification queue for background processing';

-- ---------------------------------------------------------------------------
-- 2. EMAIL TEMPLATES
-- ---------------------------------------------------------------------------
-- Template master configuration
CREATE TABLE IF NOT EXISTS `consignment_email_templates` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `template_key` VARCHAR(100) NOT NULL UNIQUE COMMENT 'Unique template identifier',
    `template_type` ENUM('internal', 'supplier') NOT NULL COMMENT 'Who receives this',
    `name` VARCHAR(255) NOT NULL COMMENT 'Human-readable template name',
    `description` TEXT NULL COMMENT 'What this template is used for',

    `subject_line` VARCHAR(500) NOT NULL COMMENT 'Subject with {placeholders}',
    `template_file` VARCHAR(255) NOT NULL COMMENT 'PHP template file path',

    `is_active` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Is template enabled',
    `priority` ENUM('urgent', 'high', 'normal', 'low') NOT NULL DEFAULT 'normal',

    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_template_key` (`template_key`),
    INDEX `idx_template_type` (`template_type`),
    INDEX `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Email template master configuration';

-- ---------------------------------------------------------------------------
-- 3. EMAIL TEMPLATE CONFIGURATION
-- ---------------------------------------------------------------------------
-- Global and supplier-specific template settings
CREATE TABLE IF NOT EXISTS `consignment_email_template_config` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `config_key` VARCHAR(100) NOT NULL COMMENT 'e.g., company_name, logo_url',
    `config_value` TEXT NOT NULL COMMENT 'Configuration value',
    `config_type` ENUM('string', 'boolean', 'integer', 'json') NOT NULL DEFAULT 'string',

    `is_supplier_specific` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Applies per supplier',
    `supplier_id` INT UNSIGNED NULL COMMENT 'Specific supplier (if applicable)',

    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_config_supplier` (`config_key`, `is_supplier_specific`, `supplier_id`),
    INDEX `idx_supplier` (`supplier_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Email template configuration (global and per-supplier)';

-- ---------------------------------------------------------------------------
-- 4. EMAIL AUDIT LOG
-- ---------------------------------------------------------------------------
-- Complete audit trail of all email activity
CREATE TABLE IF NOT EXISTS `consignment_email_log` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `queue_id` BIGINT UNSIGNED NULL COMMENT 'Related queue entry',

    `consignment_id` INT UNSIGNED NULL COMMENT 'Related consignment',
    `template_key` VARCHAR(100) NULL COMMENT 'Template used',

    `recipient_email` VARCHAR(255) NOT NULL,
    `recipient_name` VARCHAR(255) NOT NULL,
    `subject_line` VARCHAR(500) NOT NULL,

    `email_type` ENUM('internal', 'supplier') NOT NULL,
    `priority` TINYINT UNSIGNED NOT NULL,

    `sent_by` INT UNSIGNED NULL COMMENT 'User who triggered send',
    `status` ENUM('queued', 'sent', 'failed', 'cancelled') NOT NULL,

    `retry_count` TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `error_message` TEXT NULL,

    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `sent_at` DATETIME NULL COMMENT 'When successfully sent',

    PRIMARY KEY (`id`),
    INDEX `idx_queue` (`queue_id`),
    INDEX `idx_consignment` (`consignment_id`),
    INDEX `idx_template` (`template_key`),
    INDEX `idx_recipient` (`recipient_email`),
    INDEX `idx_status_created` (`status`, `created_at`),
    INDEX `idx_sent_by` (`sent_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Complete audit log of all email activity';

-- ---------------------------------------------------------------------------
-- 5. INSERT DEFAULT TEMPLATES
-- ---------------------------------------------------------------------------

INSERT INTO `consignment_email_templates` (
    `template_key`, `template_type`, `name`, `description`,
    `subject_line`, `template_file`, `priority`
) VALUES
-- Internal emails (staff/managers)
(
    'po_created_internal',
    'internal',
    'PO Created - Internal Notification',
    'Sent to store manager when new PO is created',
    'New Purchase Order #{po_number} Created',
    'internal/po_created.php',
    'normal'
),
(
    'po_pending_approval',
    'internal',
    'PO Awaiting Approval',
    'Sent to approvers when PO needs approval',
    'Purchase Order #{po_number} Awaiting Your Approval',
    'internal/po_pending_approval.php',
    'urgent'
),
(
    'po_approved',
    'internal',
    'PO Approved',
    'Sent to creator when PO is approved',
    'Purchase Order #{po_number} Approved',
    'internal/po_approved.php',
    'normal'
),
(
    'po_rejected',
    'internal',
    'PO Rejected',
    'Sent to creator when PO is rejected',
    'Purchase Order #{po_number} Rejected',
    'internal/po_rejected.php',
    'urgent'
),
(
    'consignment_received',
    'internal',
    'Consignment Received',
    'Sent when consignment is fully received',
    'Consignment #{consignment_number} Received',
    'internal/consignment_received.php',
    'normal'
),
(
    'discrepancy_alert',
    'internal',
    'Stock Discrepancy Alert',
    'Sent when significant discrepancies found',
    'Stock Discrepancy Alert - Consignment #{consignment_number}',
    'internal/discrepancy_alert.php',
    'urgent'
),

-- Supplier emails
(
    'po_created_supplier',
    'supplier',
    'PO Created - Supplier Notification',
    'Sent to supplier when PO is sent',
    'New Purchase Order from The Vape Shed - #{po_number}',
    'supplier/po_created.php',
    'normal'
),
(
    'po_amended_supplier',
    'supplier',
    'PO Amendment Notification',
    'Sent to supplier when PO is amended',
    'Purchase Order #{po_number} - Amendment Notice',
    'supplier/po_amended.php',
    'high'
),
(
    'shipment_request_supplier',
    'supplier',
    'Shipment Request',
    'Request supplier to ship the order',
    'Please Ship Purchase Order #{po_number}',
    'supplier/shipment_request.php',
    'normal'
);

-- ---------------------------------------------------------------------------
-- 6. INSERT DEFAULT CONFIGURATION
-- ---------------------------------------------------------------------------

INSERT INTO `consignment_email_template_config` (
    `config_key`, `config_value`, `config_type`, `is_supplier_specific`
) VALUES
('company_name', 'The Vape Shed', 'string', 0),
('company_address', '123 Queen Street\nAuckland Central\nAuckland 1010\nNew Zealand', 'string', 0),
('support_email', 'support@vapeshed.co.nz', 'string', 0),
('support_phone', '+64 9 123 4567', 'string', 0),
('logo_url', 'https://staff.vapeshed.co.nz/assets/img/brand/logo.jpg', 'string', 0),
('primary_color', '#000000', 'string', 0),
('accent_color', '#fbbf24', 'string', 0),
('footer_text', 'This is an automated message from The Vape Shed. Please do not reply directly to this email.', 'string', 0);

-- ---------------------------------------------------------------------------
-- 7. VERIFICATION
-- ---------------------------------------------------------------------------

SELECT 'Email notification queue table created' AS status
UNION ALL
SELECT CONCAT('Templates installed: ', COUNT(*), ' templates')
FROM consignment_email_templates
UNION ALL
SELECT CONCAT('Configuration entries: ', COUNT(*), ' settings')
FROM consignment_email_template_config;
