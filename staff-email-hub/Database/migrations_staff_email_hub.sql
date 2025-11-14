-- Staff Email Hub Database Schema
-- Complete customer communication, ID verification, and email management system

-- ============================================
-- EMAIL CLIENT & COMMUNICATION TABLES
-- ============================================

CREATE TABLE IF NOT EXISTS `staff_emails` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `trace_id` VARCHAR(50) UNIQUE NOT NULL,
  `from_staff_id` BIGINT UNSIGNED,
  `to_email` VARCHAR(255) NOT NULL,
  `customer_id` BIGINT UNSIGNED,
  `subject` VARCHAR(500) NOT NULL,
  `body_html` LONGTEXT,
  `body_plain` LONGTEXT,
  `template_used` VARCHAR(100),
  `priority` ENUM('low','normal','high','urgent') DEFAULT 'normal',
  `status` ENUM('draft','scheduled','sent','bounced','failed') DEFAULT 'draft',
  `send_at` TIMESTAMP NULL,
  `sent_at` TIMESTAMP NULL,
  `read_at` TIMESTAMP NULL,
  `reply_count` INT DEFAULT 0,
  `attachment_count` INT DEFAULT 0,
  `tags` JSON,
  `notes` TEXT,
  `assigned_to` BIGINT UNSIGNED,
  `is_r18_flagged` BOOLEAN DEFAULT FALSE,
  `r18_flag_reason` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY `idx_trace_id` (`trace_id`),
  KEY `idx_from_staff` (`from_staff_id`),
  KEY `idx_customer` (`customer_id`),
  KEY `idx_to_email` (`to_email`),
  KEY `idx_status` (`status`),
  KEY `idx_created` (`created_at`),
  KEY `idx_assigned` (`assigned_to`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `staff_email_templates` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(255) NOT NULL UNIQUE,
  `category` VARCHAR(100),
  `subject` VARCHAR(500) NOT NULL,
  `body_html` LONGTEXT,
  `body_plain` LONGTEXT,
  `variables` JSON,
  `tags` JSON,
  `is_active` BOOLEAN DEFAULT TRUE,
  `usage_count` INT DEFAULT 0,
  `created_by` BIGINT UNSIGNED,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY `idx_category` (`category`),
  KEY `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `email_attachments` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `email_id` BIGINT UNSIGNED NOT NULL,
  `filename` VARCHAR(500),
  `file_path` VARCHAR(1000),
  `file_size` BIGINT,
  `mime_type` VARCHAR(100),
  `uploaded_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`email_id`) REFERENCES `staff_emails` (`id`) ON DELETE CASCADE,
  KEY `idx_email` (`email_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- CUSTOMER HUB TABLES
-- ============================================

CREATE TABLE IF NOT EXISTS `customer_hub_profile` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `customer_id` BIGINT UNSIGNED UNIQUE NOT NULL,
  `vend_customer_id` VARCHAR(100),
  `full_name` VARCHAR(255),
  `email` VARCHAR(255),
  `phone` VARCHAR(20),
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
  `total_spent` DECIMAL(10, 2) DEFAULT 0,
  `last_purchase_at` TIMESTAMP NULL,
  `loyalty_points` INT DEFAULT 0,
  `preferred_contact` ENUM('email','sms','phone') DEFAULT 'email',
  `communication_preference` JSON,
  `notes` LONGTEXT,
  `tags` JSON,
  `is_vip` BOOLEAN DEFAULT FALSE,
  `is_flagged` BOOLEAN DEFAULT FALSE,
  `flag_reason` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY `idx_customer` (`customer_id`),
  KEY `idx_vend_id` (`vend_customer_id`),
  KEY `idx_email` (`email`),
  KEY `idx_verified` (`id_verified`),
  KEY `idx_flagged` (`is_flagged`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `customer_id_uploads` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `customer_id` BIGINT UNSIGNED NOT NULL,
  `trace_id` VARCHAR(50) UNIQUE NOT NULL,
  `id_type` ENUM('passport','drivers_license','national_id') NOT NULL,
  `front_image_path` VARCHAR(1000),
  `back_image_path` VARCHAR(1000),
  `front_image_hash` VARCHAR(64),
  `back_image_hash` VARCHAR(64),
  `ocr_data` JSON,
  `extracted_name` VARCHAR(255),
  `extracted_dob` DATE,
  `extracted_id_number` VARCHAR(100),
  `verification_score` INT,
  `is_verified` BOOLEAN DEFAULT FALSE,
  `verification_status` ENUM('pending','verified','rejected','expired') DEFAULT 'pending',
  `verification_notes` TEXT,
  `verified_by_ai` BOOLEAN DEFAULT FALSE,
  `verified_by_staff` BIGINT UNSIGNED,
  `verified_at` TIMESTAMP NULL,
  `expiry_check` BOOLEAN DEFAULT FALSE,
  `expires_at` DATE,
  `is_expired` BOOLEAN DEFAULT FALSE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`customer_id`) REFERENCES `customer_hub_profile` (`customer_id`) ON DELETE CASCADE,
  KEY `idx_trace` (`trace_id`),
  KEY `idx_customer` (`customer_id`),
  KEY `idx_status` (`verification_status`),
  KEY `idx_verified` (`is_verified`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- CUSTOMER HISTORY & COMMUNICATION TABLES
-- ============================================

CREATE TABLE IF NOT EXISTS `customer_purchase_history` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `customer_id` BIGINT UNSIGNED NOT NULL,
  `vend_sale_id` VARCHAR(100),
  `outlet_id` VARCHAR(100),
  `sale_date` TIMESTAMP,
  `total_amount` DECIMAL(10, 2),
  `item_count` INT,
  `items_json` JSON,
  `payment_method` VARCHAR(100),
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`customer_id`) REFERENCES `customer_hub_profile` (`customer_id`) ON DELETE CASCADE,
  KEY `idx_customer` (`customer_id`),
  KEY `idx_sale_date` (`sale_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `customer_communication_log` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `customer_id` BIGINT UNSIGNED NOT NULL,
  `email_id` BIGINT UNSIGNED,
  `communication_type` ENUM('email','sms','phone','in_person','system') DEFAULT 'email',
  `direction` ENUM('inbound','outbound') DEFAULT 'outbound',
  `subject` VARCHAR(500),
  `summary` TEXT,
  `staff_id` BIGINT UNSIGNED,
  `tags` JSON,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`customer_id`) REFERENCES `customer_hub_profile` (`customer_id`) ON DELETE CASCADE,
  FOREIGN KEY (`email_id`) REFERENCES `staff_emails` (`id`) ON DELETE SET NULL,
  KEY `idx_customer` (`customer_id`),
  KEY `idx_type` (`communication_type`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- SEARCH & INDEXING TABLES
-- ============================================

CREATE TABLE IF NOT EXISTS `customer_search_index` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `customer_id` BIGINT UNSIGNED UNIQUE NOT NULL,
  `search_text` LONGTEXT,
  `keywords` JSON,
  `indexed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`customer_id`) REFERENCES `customer_hub_profile` (`customer_id`) ON DELETE CASCADE,
  FULLTEXT INDEX `idx_fulltext` (`search_text`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `email_search_index` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `email_id` BIGINT UNSIGNED UNIQUE NOT NULL,
  `search_text` LONGTEXT,
  `keywords` JSON,
  `indexed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`email_id`) REFERENCES `staff_emails` (`id`) ON DELETE CASCADE,
  FULLTEXT INDEX `idx_fulltext` (`search_text`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- AUTOMATION & WORKFLOW TABLES
-- ============================================

CREATE TABLE IF NOT EXISTS `email_automation_rules` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(255) NOT NULL,
  `trigger_type` ENUM('on_new_email','on_tag','on_customer_action','on_schedule') DEFAULT 'on_new_email',
  `conditions` JSON,
  `actions` JSON,
  `template_id` BIGINT UNSIGNED,
  `is_active` BOOLEAN DEFAULT TRUE,
  `priority` INT DEFAULT 100,
  `execution_count` INT DEFAULT 0,
  `last_executed_at` TIMESTAMP NULL,
  `created_by` BIGINT UNSIGNED,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY `idx_active` (`is_active`),
  KEY `idx_trigger` (`trigger_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- PRIVACY & SECURITY TABLES
-- ============================================

CREATE TABLE IF NOT EXISTS `id_verification_audit_log` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `id_upload_id` BIGINT UNSIGNED,
  `action` VARCHAR(100),
  `actor_id` BIGINT UNSIGNED,
  `actor_type` ENUM('staff','system','admin') DEFAULT 'system',
  `action_details` JSON,
  `ip_address` VARCHAR(45),
  `timestamp` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`id_upload_id`) REFERENCES `customer_id_uploads` (`id`) ON DELETE CASCADE,
  KEY `idx_upload` (`id_upload_id`),
  KEY `idx_timestamp` (`timestamp`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `email_access_log` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `email_id` BIGINT UNSIGNED,
  `staff_id` BIGINT UNSIGNED,
  `access_type` ENUM('view','edit','delete','forward') DEFAULT 'view',
  `ip_address` VARCHAR(45),
  `timestamp` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`email_id`) REFERENCES `staff_emails` (`id`) ON DELETE CASCADE,
  KEY `idx_email` (`email_id`),
  KEY `idx_staff` (`staff_id`),
  KEY `idx_timestamp` (`timestamp`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- INDEXES FOR PERFORMANCE
-- ============================================

CREATE INDEX idx_email_customer_status ON staff_emails(customer_id, status, created_at);
CREATE INDEX idx_email_assigned_status ON staff_emails(assigned_to, status, created_at);
CREATE INDEX idx_customer_profile_search ON customer_hub_profile(email, phone, vend_customer_id);
CREATE INDEX idx_purchase_history_customer_date ON customer_purchase_history(customer_id, sale_date);
CREATE INDEX idx_communication_customer_type ON customer_communication_log(customer_id, communication_type, created_at);
