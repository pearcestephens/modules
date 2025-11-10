-- Flagged Products Module Schema
-- Product quality control and issue tracking

CREATE TABLE IF NOT EXISTS `flagged_products` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `product_id` INT UNSIGNED NOT NULL,
    `vend_product_id` VARCHAR(36),
    `sku` VARCHAR(100),
    `product_name` VARCHAR(255),
    `flag_status` ENUM('active', 'investigating', 'resolved', 'closed') DEFAULT 'active',
    `severity` ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
    `total_flags` INT DEFAULT 1,
    `first_flagged_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `last_flagged_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `resolved_at` TIMESTAMP NULL,
    `resolution_notes` TEXT,
    `resolved_by` INT UNSIGNED,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_product (`product_id`),
    INDEX idx_vend (`vend_product_id`),
    INDEX idx_sku (`sku`),
    INDEX idx_status (`flag_status`),
    INDEX idx_severity (`severity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `product_flags` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `flagged_product_id` INT UNSIGNED NOT NULL,
    `flag_type` ENUM('quality_issue', 'customer_complaint', 'safety_concern', 'inventory_discrepancy', 'pricing_error', 'other') NOT NULL,
    `flag_source` ENUM('customer', 'staff', 'supplier', 'automated_check') NOT NULL,
    `outlet_id` INT UNSIGNED,
    `reported_by` INT UNSIGNED,
    `customer_id` INT UNSIGNED,
    `description` TEXT NOT NULL,
    `severity` ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
    `quantity_affected` INT DEFAULT 1,
    `batch_number` VARCHAR(100),
    `sale_id` VARCHAR(50) COMMENT 'Related Vend sale ID if applicable',
    `images` JSON COMMENT 'Array of image URLs',
    `metadata` JSON COMMENT 'Additional context data',
    `flagged_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`flagged_product_id`) REFERENCES `flagged_products`(`id`) ON DELETE CASCADE,
    INDEX idx_flagged_product (`flagged_product_id`),
    INDEX idx_type (`flag_type`),
    INDEX idx_source (`flag_source`),
    INDEX idx_outlet (`outlet_id`),
    INDEX idx_flagged (`flagged_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `flag_resolutions` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `flagged_product_id` INT UNSIGNED NOT NULL,
    `resolution_type` ENUM('product_removed', 'supplier_contacted', 'customer_refunded', 'batch_recalled', 'issue_resolved', 'no_action_required') NOT NULL,
    `action_taken` TEXT NOT NULL,
    `outcome` TEXT,
    `supplier_notified` TINYINT(1) DEFAULT 0,
    `supplier_response` TEXT,
    `affected_units_handled` INT DEFAULT 0,
    `cost_impact` DECIMAL(10,2),
    `resolved_by` INT UNSIGNED NOT NULL,
    `resolved_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `follow_up_required` TINYINT(1) DEFAULT 0,
    `follow_up_date` DATE,
    `follow_up_notes` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`flagged_product_id`) REFERENCES `flagged_products`(`id`) ON DELETE CASCADE,
    INDEX idx_flagged_product (`flagged_product_id`),
    INDEX idx_type (`resolution_type`),
    INDEX idx_follow_up (`follow_up_required`, `follow_up_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `flag_notifications` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `flag_id` BIGINT UNSIGNED NOT NULL,
    `notification_type` ENUM('email', 'sms', 'system') NOT NULL,
    `recipient_type` ENUM('staff', 'manager', 'supplier', 'customer') NOT NULL,
    `recipient_id` INT UNSIGNED,
    `recipient_email` VARCHAR(255),
    `subject` VARCHAR(255),
    `message` TEXT,
    `sent_status` ENUM('pending', 'sent', 'failed') DEFAULT 'pending',
    `sent_at` TIMESTAMP NULL,
    `error_message` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`flag_id`) REFERENCES `product_flags`(`id`) ON DELETE CASCADE,
    INDEX idx_flag (`flag_id`),
    INDEX idx_status (`sent_status`),
    INDEX idx_sent (`sent_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
