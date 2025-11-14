-- Staff Email Hub Enhanced Database Schema
-- Complete customer communication, ID verification, email management, and onboarding
-- Enhanced with demo data support, IMAP sync, and email queue management

-- ============================================
-- CONFIGURATION TABLES
-- ============================================

CREATE TABLE IF NOT EXISTS `module_config` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `module` VARCHAR(100) NOT NULL,
  `config_key` VARCHAR(255) NOT NULL,
  `config_value` LONGTEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `unique_config` (`module`, `config_key`),
  KEY `idx_module` (`module`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- EMAIL CLIENT & COMMUNICATION TABLES
-- ============================================

CREATE TABLE IF NOT EXISTS `staff_emails` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `staff_id` BIGINT UNSIGNED NOT NULL DEFAULT 1,
  `customer_id` BIGINT UNSIGNED,
  `message_id` VARCHAR(500),
  `subject` VARCHAR(500) NOT NULL,
  `from_address` VARCHAR(255) NOT NULL,
  `to_address` VARCHAR(255) NOT NULL,
  `cc` VARCHAR(1000),
  `bcc` VARCHAR(1000),
  `body` LONGTEXT,
  `body_html` LONGTEXT,
  `status` ENUM('draft','scheduled','sent','received','bounced','failed') DEFAULT 'draft',
  `priority` ENUM('low','normal','high','urgent') DEFAULT 'normal',
  `send_at` TIMESTAMP NULL,
  `sent_at` TIMESTAMP NULL,
  `read_at` TIMESTAMP NULL,
  `reply_to_id` BIGINT UNSIGNED,
  `is_forwarded` BOOLEAN DEFAULT FALSE,
  `assigned_to` BIGINT UNSIGNED,
  `is_r18_flagged` BOOLEAN DEFAULT FALSE,
  `r18_flag_reason` TEXT,
  `tags` JSON,
  `notes` LONGTEXT,
  `is_demo_data` BOOLEAN DEFAULT FALSE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY `idx_staff` (`staff_id`),
  KEY `idx_customer` (`customer_id`),
  KEY `idx_message_id` (`message_id`),
  KEY `idx_status` (`status`),
  KEY `idx_assigned` (`assigned_to`),
  KEY `idx_created` (`created_at`),
  KEY `idx_demo_data` (`is_demo_data`),
  FULLTEXT KEY `ft_subject_body` (`subject`, `body`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `staff_email_templates` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(255) NOT NULL UNIQUE,
  `category` VARCHAR(100),
  `subject` VARCHAR(500) NOT NULL,
  `body` LONGTEXT,
  `variables` JSON,
  `tags` JSON,
  `is_active` BOOLEAN DEFAULT TRUE,
  `usage_count` INT DEFAULT 0,
  `created_by` BIGINT UNSIGNED,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY `idx_category` (`category`),
  KEY `idx_active` (`is_active`),
  KEY `idx_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `email_attachments` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `email_id` BIGINT UNSIGNED NOT NULL,
  `filename` VARCHAR(500),
  `original_filename` VARCHAR(500),
  `file_path` VARCHAR(1000),
  `file_size` BIGINT,
  `mime_type` VARCHAR(100),
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`email_id`) REFERENCES `staff_emails` (`id`) ON DELETE CASCADE,
  KEY `idx_email` (`email_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `email_queue` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `email_id` BIGINT UNSIGNED,
  `to_address` VARCHAR(255) NOT NULL,
  `cc` JSON,
  `bcc` JSON,
  `subject` VARCHAR(500) NOT NULL,
  `body` LONGTEXT,
  `attachments` JSON,
  `status` ENUM('queued','sent','failed','bounced') DEFAULT 'queued',
  `attempts` INT DEFAULT 0,
  `last_attempt_at` TIMESTAMP NULL,
  `sent_at` TIMESTAMP NULL,
  `error_message` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY `idx_status` (`status`),
  KEY `idx_created` (`created_at`),
  FOREIGN KEY (`email_id`) REFERENCES `staff_emails` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- CUSTOMER HUB TABLES
-- ============================================

CREATE TABLE IF NOT EXISTS `customer_hub_profile` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `vend_customer_id` VARCHAR(100) UNIQUE,
  `first_name` VARCHAR(100),
  `last_name` VARCHAR(100),
  `email` VARCHAR(255),
  `alt_email` VARCHAR(255),
  `phone` VARCHAR(20),
  `alt_phone` VARCHAR(20),
  `date_of_birth` DATE,
  `address` TEXT,
  `suburb` VARCHAR(100),
  `postcode` VARCHAR(10),
  `country` VARCHAR(100) DEFAULT 'NZ',
  `id_verified` BOOLEAN DEFAULT FALSE,
  `id_verified_at` TIMESTAMP NULL,
  `id_verified_by` BIGINT UNSIGNED,
  `age_verified` BOOLEAN DEFAULT FALSE,
  `age_verified_at` TIMESTAMP NULL,
  `purchase_count` INT DEFAULT 0,
  `total_spent` DECIMAL(12, 2) DEFAULT 0,
  `last_purchase_at` TIMESTAMP NULL,
  `loyalty_points` INT DEFAULT 0,
  `preferred_contact` ENUM('email','sms','phone') DEFAULT 'email',
  `is_vip` BOOLEAN DEFAULT FALSE,
  `is_flagged` BOOLEAN DEFAULT FALSE,
  `flag_reason` TEXT,
  `notes` LONGTEXT,
  `tags` JSON,
  `metadata` JSON,
  `is_demo_data` BOOLEAN DEFAULT FALSE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `unique_email` (`email`),
  KEY `idx_vend_id` (`vend_customer_id`),
  KEY `idx_phone` (`phone`),
  KEY `idx_vip` (`is_vip`),
  KEY `idx_flagged` (`is_flagged`),
  KEY `idx_verified` (`id_verified`),
  KEY `idx_created` (`created_at`),
  KEY `idx_demo_data` (`is_demo_data`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `customer_purchase_history` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `customer_id` BIGINT UNSIGNED NOT NULL,
  `vend_order_id` VARCHAR(100),
  `order_date` TIMESTAMP NOT NULL,
  `total_amount` DECIMAL(12, 2),
  `items` JSON,
  `status` ENUM('pending','completed','cancelled','refunded') DEFAULT 'completed',
  `notes` TEXT,
  `is_demo_data` BOOLEAN DEFAULT FALSE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`customer_id`) REFERENCES `customer_hub_profile` (`id`) ON DELETE CASCADE,
  KEY `idx_customer` (`customer_id`),
  KEY `idx_vend_order_id` (`vend_order_id`),
  KEY `idx_order_date` (`order_date`),
  KEY `idx_demo_data` (`is_demo_data`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `customer_communication_log` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `customer_id` BIGINT UNSIGNED NOT NULL,
  `type` ENUM('email','phone','sms','in_person','system') NOT NULL,
  `subject` VARCHAR(500),
  `notes` LONGTEXT,
  `staff_id` BIGINT UNSIGNED,
  `is_demo_data` BOOLEAN DEFAULT FALSE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`customer_id`) REFERENCES `customer_hub_profile` (`id`) ON DELETE CASCADE,
  KEY `idx_customer` (`customer_id`),
  KEY `idx_type` (`type`),
  KEY `idx_created` (`created_at`),
  KEY `idx_demo_data` (`is_demo_data`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `customer_search_index` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `customer_id` BIGINT UNSIGNED NOT NULL,
  `search_text` LONGTEXT,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `unique_customer` (`customer_id`),
  FULLTEXT KEY `ft_search` (`search_text`),
  FOREIGN KEY (`customer_id`) REFERENCES `customer_hub_profile` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- ID VERIFICATION TABLES
-- ============================================

CREATE TABLE IF NOT EXISTS `customer_id_uploads` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `customer_id` BIGINT UNSIGNED NOT NULL,
  `id_type` ENUM('passport','drivers_license','national_id','other') NOT NULL,
  `front_image_path` VARCHAR(1000),
  `back_image_path` VARCHAR(1000),
  `ocr_data` JSON,
  `verification_status` ENUM('pending','verified','rejected','expired') DEFAULT 'pending',
  `verified_at` TIMESTAMP NULL,
  `verified_by` BIGINT UNSIGNED,
  `expiry_date` DATE,
  `is_expired` BOOLEAN DEFAULT FALSE,
  `rejection_reason` TEXT,
  `confidence_score` DECIMAL(5, 2),
  `metadata` JSON,
  `is_demo_data` BOOLEAN DEFAULT FALSE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`customer_id`) REFERENCES `customer_hub_profile` (`id`) ON DELETE CASCADE,
  KEY `idx_customer` (`customer_id`),
  KEY `idx_status` (`verification_status`),
  KEY `idx_expiry` (`expiry_date`),
  KEY `idx_created` (`created_at`),
  KEY `idx_demo_data` (`is_demo_data`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `id_verification_audit_log` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `customer_id` BIGINT UNSIGNED NOT NULL,
  `id_upload_id` BIGINT UNSIGNED,
  `action` VARCHAR(100),
  `old_value` TEXT,
  `new_value` TEXT,
  `staff_id` BIGINT UNSIGNED,
  `reason` TEXT,
  `ip_address` VARCHAR(45),
  `user_agent` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`customer_id`) REFERENCES `customer_hub_profile` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`id_upload_id`) REFERENCES `customer_id_uploads` (`id`) ON DELETE SET NULL,
  KEY `idx_customer` (`customer_id`),
  KEY `idx_action` (`action`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- SEARCH & ANALYTICS TABLES
-- ============================================

CREATE TABLE IF NOT EXISTS `email_search_index` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `email_id` BIGINT UNSIGNED NOT NULL,
  `search_text` LONGTEXT,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `unique_email` (`email_id`),
  FULLTEXT KEY `ft_email_search` (`search_text`),
  FOREIGN KEY (`email_id`) REFERENCES `staff_emails` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `email_access_log` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `email_id` BIGINT UNSIGNED NOT NULL,
  `staff_id` BIGINT UNSIGNED,
  `action` VARCHAR(100),
  `ip_address` VARCHAR(45),
  `user_agent` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`email_id`) REFERENCES `staff_emails` (`id`) ON DELETE CASCADE,
  KEY `idx_email` (`email_id`),
  KEY `idx_staff` (`staff_id`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- IMAP SYNC TRACKING
-- ============================================

CREATE TABLE IF NOT EXISTS `imap_sync_log` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `folder_name` VARCHAR(255),
  `last_sync_at` TIMESTAMP NULL,
  `emails_synced` INT DEFAULT 0,
  `errors_count` INT DEFAULT 0,
  `status` ENUM('pending','in_progress','completed','failed') DEFAULT 'pending',
  `error_message` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY `idx_folder` (`folder_name`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- INITIAL DATA
-- ============================================

-- Insert default email templates
INSERT IGNORE INTO `staff_email_templates`
(`name`, `category`, `subject`, `body`, `variables`, `is_active`, `created_at`)
VALUES
('Welcome New Customer', 'onboarding', 'Welcome to The Vape Shed!', 'Welcome {{first_name}}! We''re excited to have you as part of our community. Get exclusive discounts and product recommendations tailored just for you.', '["first_name", "last_name", "email"]', TRUE, NOW()),
('Order Confirmation', 'orders', 'Your Order #{{order_id}} Confirmed', 'Thank you for your order! Your order #{{order_id}} has been confirmed and is being prepared for shipment.', '["order_id", "total", "items"]', TRUE, NOW()),
('Shipping Notification', 'orders', 'Your Order is on its way!', 'Great news! Your order #{{order_id}} has been dispatched and is on its way to you. Tracking: {{tracking_number}}', '["order_id", "tracking_number"]', TRUE, NOW()),
('Follow-up Feedback', 'feedback', 'How was your experience?', 'Hi {{first_name}}, we''d love to hear about your experience with our products! Your feedback helps us improve.', '["first_name"]', TRUE, NOW()),
('Special Offer', 'marketing', 'Exclusive Offer for You - {{discount}}% Off', 'Hi {{first_name}}, as a valued customer, we''re offering you {{discount}}% off your next purchase!', '["first_name", "discount", "code"]', TRUE, NOW());

-- ============================================
-- INDEXES FOR PERFORMANCE
-- ============================================

CREATE INDEX idx_customer_email_created ON customer_hub_profile(email, created_at);
CREATE INDEX idx_email_customer_status_created ON staff_emails(customer_id, status, created_at);
CREATE INDEX idx_purchase_customer_date ON customer_purchase_history(customer_id, order_date DESC);
CREATE INDEX idx_communication_customer_type ON customer_communication_log(customer_id, type);
CREATE INDEX idx_id_upload_status_created ON customer_id_uploads(verification_status, created_at);
