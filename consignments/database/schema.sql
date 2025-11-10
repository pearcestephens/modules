-- Consignments Module Schema
-- Lightspeed consignment management and transfer tracking

CREATE TABLE IF NOT EXISTS `consignments` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `vend_consignment_id` VARCHAR(36) UNIQUE,
    `consignment_number` VARCHAR(50) NOT NULL,
    `consignment_type` ENUM('supplier', 'outlet', 'return') NOT NULL,
    `status` ENUM('pending', 'sent', 'received', 'cancelled') DEFAULT 'pending',
    `source_outlet_id` INT UNSIGNED,
    `destination_outlet_id` INT UNSIGNED,
    `supplier_id` INT UNSIGNED,
    `total_cost` DECIMAL(10,2) DEFAULT 0.00,
    `total_items` INT DEFAULT 0,
    `notes` TEXT,
    `sent_at` TIMESTAMP NULL,
    `received_at` TIMESTAMP NULL,
    `created_by` INT UNSIGNED,
    `received_by` INT UNSIGNED,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_vend_id (`vend_consignment_id`),
    INDEX idx_number (`consignment_number`),
    INDEX idx_status (`status`),
    INDEX idx_source (`source_outlet_id`),
    INDEX idx_destination (`destination_outlet_id`),
    INDEX idx_created (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `consignment_items` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `consignment_id` INT UNSIGNED NOT NULL,
    `product_id` INT UNSIGNED NOT NULL,
    `vend_product_id` VARCHAR(36),
    `sku` VARCHAR(100),
    `product_name` VARCHAR(255),
    `quantity_sent` INT NOT NULL DEFAULT 0,
    `quantity_received` INT DEFAULT 0,
    `quantity_variance` INT DEFAULT 0,
    `cost_price` DECIMAL(10,2) DEFAULT 0.00,
    `total_cost` DECIMAL(10,2) DEFAULT 0.00,
    `notes` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`consignment_id`) REFERENCES `consignments`(`id`) ON DELETE CASCADE,
    INDEX idx_consignment (`consignment_id`),
    INDEX idx_product (`product_id`),
    INDEX idx_sku (`sku`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `transfer_requests` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `request_number` VARCHAR(50) NOT NULL UNIQUE,
    `from_outlet_id` INT UNSIGNED NOT NULL,
    `to_outlet_id` INT UNSIGNED NOT NULL,
    `status` ENUM('pending', 'approved', 'rejected', 'completed', 'cancelled') DEFAULT 'pending',
    `priority` ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
    `requested_by` INT UNSIGNED NOT NULL,
    `approved_by` INT UNSIGNED,
    `reason` TEXT,
    `notes` TEXT,
    `consignment_id` INT UNSIGNED,
    `requested_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `approved_at` TIMESTAMP NULL,
    `completed_at` TIMESTAMP NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`consignment_id`) REFERENCES `consignments`(`id`) ON DELETE SET NULL,
    INDEX idx_number (`request_number`),
    INDEX idx_status (`status`),
    INDEX idx_from_outlet (`from_outlet_id`),
    INDEX idx_to_outlet (`to_outlet_id`),
    INDEX idx_requested (`requested_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `transfer_request_items` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `transfer_request_id` INT UNSIGNED NOT NULL,
    `product_id` INT UNSIGNED NOT NULL,
    `sku` VARCHAR(100),
    `product_name` VARCHAR(255),
    `quantity_requested` INT NOT NULL,
    `quantity_approved` INT DEFAULT 0,
    `notes` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`transfer_request_id`) REFERENCES `transfer_requests`(`id`) ON DELETE CASCADE,
    INDEX idx_request (`transfer_request_id`),
    INDEX idx_product (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `consignment_sync_log` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `consignment_id` INT UNSIGNED,
    `sync_direction` ENUM('to_vend', 'from_vend') NOT NULL,
    `sync_status` ENUM('success', 'failed', 'partial') NOT NULL,
    `items_synced` INT DEFAULT 0,
    `error_message` TEXT,
    `sync_data` JSON,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_consignment (`consignment_id`),
    INDEX idx_status (`sync_status`),
    INDEX idx_created (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
