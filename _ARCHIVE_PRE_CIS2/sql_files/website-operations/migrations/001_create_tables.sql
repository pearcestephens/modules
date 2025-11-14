-- Website Operations Module - Database Schema
-- Version: 1.0.0
-- Created: 2025-11-06
-- Purpose: Complete database structure for multi-channel e-commerce operations

-- ==========================================
-- 1. WEB ORDERS TABLE
-- ==========================================
CREATE TABLE IF NOT EXISTS `web_orders` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `customer_id` INT UNSIGNED NOT NULL,
  `outlet_id` INT UNSIGNED NULL COMMENT 'Fulfillment store',
  `channel` VARCHAR(50) NOT NULL COMMENT 'vapeshed, ecigdis',
  `order_number` VARCHAR(50) NOT NULL UNIQUE,
  `status` ENUM('pending', 'processing', 'completed', 'shipped', 'delivered', 'cancelled', 'refunded') NOT NULL DEFAULT 'pending',

  -- Financial
  `subtotal` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `shipping_cost` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `tax_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `discount_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `total_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,

  -- Shipping Details
  `shipping_address` VARCHAR(255) NULL,
  `shipping_city` VARCHAR(100) NULL,
  `shipping_postcode` VARCHAR(20) NULL,
  `shipping_country` VARCHAR(2) DEFAULT 'NZ',
  `shipping_carrier` VARCHAR(50) NULL COMMENT 'NZ Post, CourierPost, Fastway',
  `shipping_service` VARCHAR(50) NULL COMMENT 'Standard, Express, etc',
  `shipping_tracking` VARCHAR(100) NULL,
  `shipping_cost_saved` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'COST SAVINGS TRACKED HERE!',
  `fulfillment_location` VARCHAR(100) NULL,

  -- Timestamps
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
  `shipped_at` TIMESTAMP NULL,
  `delivered_at` TIMESTAMP NULL,
  `expected_delivery_at` TIMESTAMP NULL,

  -- Metadata
  `created_by` INT UNSIGNED NULL,
  `notes` TEXT NULL,

  INDEX `idx_customer` (`customer_id`),
  INDEX `idx_outlet` (`outlet_id`),
  INDEX `idx_status` (`status`),
  INDEX `idx_channel` (`channel`),
  INDEX `idx_created` (`created_at`),
  INDEX `idx_order_number` (`order_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==========================================
-- 2. WEB ORDER ITEMS TABLE
-- ==========================================
CREATE TABLE IF NOT EXISTS `web_order_items` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `order_id` INT UNSIGNED NOT NULL,
  `product_id` INT UNSIGNED NULL,
  `sku` VARCHAR(100) NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `quantity` INT NOT NULL DEFAULT 1,
  `price` DECIMAL(10,2) NOT NULL,
  `total` DECIMAL(10,2) NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

  INDEX `idx_order` (`order_id`),
  INDEX `idx_product` (`product_id`),
  INDEX `idx_sku` (`sku`),

  FOREIGN KEY (`order_id`) REFERENCES `web_orders`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==========================================
-- 3. WEB PRODUCTS TABLE
-- ==========================================
CREATE TABLE IF NOT EXISTS `web_products` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `sku` VARCHAR(100) NOT NULL UNIQUE,
  `name` VARCHAR(255) NOT NULL,
  `description` TEXT NULL,
  `category_id` INT UNSIGNED NULL,

  -- Pricing
  `price` DECIMAL(10,2) NOT NULL,
  `cost` DECIMAL(10,2) NULL COMMENT 'Product cost for margin calculation',
  `compare_at_price` DECIMAL(10,2) NULL COMMENT 'Original/RRP for discount display',

  -- Status
  `status` ENUM('active', 'inactive', 'deleted') NOT NULL DEFAULT 'active',
  `channel` VARCHAR(50) NOT NULL DEFAULT 'vapeshed' COMMENT 'vapeshed, ecigdis, both',

  -- Dimensions (for shipping calculation)
  `weight` INT NULL COMMENT 'Weight in grams',
  `length` DECIMAL(10,2) NULL COMMENT 'Length in cm',
  `width` DECIMAL(10,2) NULL COMMENT 'Width in cm',
  `height` DECIMAL(10,2) NULL COMMENT 'Height in cm',

  -- Inventory
  `total_stock` INT NOT NULL DEFAULT 0 COMMENT 'Calculated from inventory table',
  `low_stock_threshold` INT NOT NULL DEFAULT 10,

  -- Media
  `image_url` VARCHAR(500) NULL,

  -- Sync Status
  `last_sync_at` TIMESTAMP NULL,
  `sync_status` ENUM('pending', 'synced', 'failed') NULL,

  -- Timestamps
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` TIMESTAMP NULL,
  `created_by` INT UNSIGNED NULL,
  `updated_by` INT UNSIGNED NULL,
  `deleted_by` INT UNSIGNED NULL,

  INDEX `idx_sku` (`sku`),
  INDEX `idx_category` (`category_id`),
  INDEX `idx_status` (`status`),
  INDEX `idx_channel` (`channel`),
  FULLTEXT `idx_search` (`name`, `description`, `sku`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==========================================
-- 4. WEB PRODUCT VARIANTS TABLE
-- ==========================================
CREATE TABLE IF NOT EXISTS `web_product_variants` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `product_id` INT UNSIGNED NOT NULL,
  `name` VARCHAR(100) NOT NULL COMMENT 'Color, Size, Flavor, etc',
  `sku` VARCHAR(100) NULL,
  `price_modifier` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Add/subtract from base price',
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

  INDEX `idx_product` (`product_id`),
  FOREIGN KEY (`product_id`) REFERENCES `web_products`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==========================================
-- 5. WEB PRODUCT IMAGES TABLE
-- ==========================================
CREATE TABLE IF NOT EXISTS `web_product_images` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `product_id` INT UNSIGNED NOT NULL,
  `url` VARCHAR(500) NOT NULL,
  `is_primary` TINYINT(1) NOT NULL DEFAULT 0,
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

  INDEX `idx_product` (`product_id`),
  FOREIGN KEY (`product_id`) REFERENCES `web_products`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==========================================
-- 6. WEB CUSTOMERS TABLE
-- ==========================================
CREATE TABLE IF NOT EXISTS `web_customers` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(255) NOT NULL,
  `email` VARCHAR(255) NOT NULL UNIQUE,
  `phone` VARCHAR(50) NULL,
  `company` VARCHAR(255) NULL,

  -- Customer Type
  `is_wholesale` TINYINT(1) NOT NULL DEFAULT 0,

  -- Status
  `status` ENUM('active', 'inactive', 'suspended', 'deleted') NOT NULL DEFAULT 'active',

  -- Statistics
  `total_orders` INT NOT NULL DEFAULT 0,
  `total_spent` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `last_order_at` TIMESTAMP NULL,

  -- Timestamps
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,

  INDEX `idx_email` (`email`),
  INDEX `idx_wholesale` (`is_wholesale`),
  INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==========================================
-- 7. WEB CATEGORIES TABLE
-- ==========================================
CREATE TABLE IF NOT EXISTS `web_categories` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(255) NOT NULL,
  `slug` VARCHAR(255) NOT NULL UNIQUE,
  `parent_id` INT UNSIGNED NULL,
  `description` TEXT NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

  INDEX `idx_parent` (`parent_id`),
  INDEX `idx_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==========================================
-- 8. WHOLESALE ACCOUNTS TABLE
-- ==========================================
CREATE TABLE IF NOT EXISTS `wholesale_accounts` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `customer_id` INT UNSIGNED NOT NULL,
  `business_name` VARCHAR(255) NOT NULL,
  `abn` VARCHAR(50) NULL COMMENT 'Business number',
  `discount_percentage` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `credit_limit` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `payment_terms` VARCHAR(50) NULL COMMENT '30 days, 60 days, etc',
  `status` ENUM('pending', 'approved', 'suspended', 'rejected') NOT NULL DEFAULT 'pending',

  -- Approval
  `approved_at` TIMESTAMP NULL,
  `approved_by` INT UNSIGNED NULL,

  -- Timestamps
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,

  INDEX `idx_customer` (`customer_id`),
  INDEX `idx_status` (`status`),

  FOREIGN KEY (`customer_id`) REFERENCES `web_customers`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==========================================
-- 9. STORE CONFIGURATIONS TABLE
-- ==========================================
CREATE TABLE IF NOT EXISTS `store_configurations` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(255) NOT NULL,
  `code` VARCHAR(50) NOT NULL UNIQUE,

  -- Address
  `address` VARCHAR(255) NOT NULL,
  `city` VARCHAR(100) NOT NULL,
  `postcode` VARCHAR(20) NOT NULL,
  `latitude` DECIMAL(10,7) NULL,
  `longitude` DECIMAL(10,7) NULL,

  -- Settings
  `shipping_enabled` TINYINT(1) NOT NULL DEFAULT 1,
  `is_primary` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Primary warehouse',
  `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',

  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

  INDEX `idx_code` (`code`),
  INDEX `idx_shipping` (`shipping_enabled`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==========================================
-- 10. SHIPPING RATES TABLE
-- ==========================================
CREATE TABLE IF NOT EXISTS `shipping_rates` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `carrier` VARCHAR(50) NOT NULL COMMENT 'nzpost, courierpost, fastway',
  `service` VARCHAR(50) NOT NULL COMMENT 'standard, express, overnight',
  `weight_from` INT NOT NULL COMMENT 'Grams',
  `weight_to` INT NOT NULL COMMENT 'Grams',
  `zone` VARCHAR(50) NULL COMMENT 'metro, regional, rural',
  `rate` DECIMAL(10,2) NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

  INDEX `idx_carrier` (`carrier`),
  INDEX `idx_weight` (`weight_from`, `weight_to`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==========================================
-- 11. ORDER STATUS HISTORY (AUDIT LOG)
-- ==========================================
CREATE TABLE IF NOT EXISTS `order_status_history` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `order_id` INT UNSIGNED NOT NULL,
  `status` VARCHAR(50) NOT NULL,
  `notes` TEXT NULL,
  `user_id` INT UNSIGNED NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

  INDEX `idx_order` (`order_id`),
  FOREIGN KEY (`order_id`) REFERENCES `web_orders`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==========================================
-- 12. ORDER SHIPPING HISTORY (OPTIMIZATION TRACKING)
-- ==========================================
CREATE TABLE IF NOT EXISTS `order_shipping_history` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `order_id` INT UNSIGNED NOT NULL,
  `carrier` VARCHAR(50) NOT NULL,
  `service` VARCHAR(50) NOT NULL,
  `cost` DECIMAL(10,2) NOT NULL,
  `alternatives_considered` JSON NULL COMMENT 'All shipping options evaluated',
  `cost_saved` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Money saved vs most expensive option',
  `optimization_strategy` VARCHAR(50) NULL COMMENT 'cost, speed, balanced',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

  INDEX `idx_order` (`order_id`),
  FOREIGN KEY (`order_id`) REFERENCES `web_orders`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==========================================
-- SAMPLE DATA INSERTS
-- ==========================================

-- Sample Store Configurations
INSERT IGNORE INTO `store_configurations` (`name`, `code`, `address`, `city`, `postcode`, `latitude`, `longitude`, `is_primary`) VALUES
('Auckland Central', 'AKL-C', '123 Queen Street', 'Auckland', '1010', -36.8485, 174.7633, 1),
('Wellington CBD', 'WLG-C', '456 Lambton Quay', 'Wellington', '6011', -41.2865, 174.7762, 0),
('Christchurch Central', 'CHC-C', '789 Cashel Street', 'Christchurch', '8011', -43.5320, 172.6306, 0);

-- Sample Categories
INSERT IGNORE INTO `web_categories` (`name`, `slug`, `status`) VALUES
('Vape Devices', 'vape-devices', 'active'),
('E-Liquids', 'e-liquids', 'active'),
('Accessories', 'accessories', 'active'),
('Wholesale', 'wholesale', 'active');

-- Sample Shipping Rates (NZ Post Standard)
INSERT IGNORE INTO `shipping_rates` (`carrier`, `service`, `weight_from`, `weight_to`, `zone`, `rate`) VALUES
('nzpost', 'standard', 0, 500, 'metro', 5.50),
('nzpost', 'standard', 501, 1000, 'metro', 7.90),
('nzpost', 'standard', 1001, 3000, 'metro', 10.50),
('courierpost', 'standard', 0, 500, 'metro', 6.20),
('courierpost', 'standard', 501, 1000, 'metro', 8.50),
('fastway', 'parcel', 0, 3000, 'metro', 5.90);

-- ==========================================
-- PERFORMANCE INDEXES
-- ==========================================

-- Composite indexes for common queries
CREATE INDEX `idx_order_customer_date` ON `web_orders`(`customer_id`, `created_at`);
CREATE INDEX `idx_order_status_date` ON `web_orders`(`status`, `created_at`);
CREATE INDEX `idx_product_category_status` ON `web_products`(`category_id`, `status`);
CREATE INDEX `idx_customer_wholesale_status` ON `web_customers`(`is_wholesale`, `status`);

-- ==========================================
-- COMPLETION MESSAGE
-- ==========================================
SELECT 'Website Operations Module - Database schema created successfully!' AS Status;
SELECT COUNT(*) AS Tables_Created FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME LIKE 'web_%';
