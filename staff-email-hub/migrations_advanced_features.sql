-- ============================================================================
-- Staff Email Hub - Advanced Features Database Schema
-- ============================================================================
-- This migration adds support for:
-- - Rackspace legacy email integration
-- - Multiple staff profiles with delegation
-- - AI message enhancement and smart replies
-- - Advanced features (templates, scheduling, read receipts, etc.)
-- ============================================================================

-- ============================================================================
-- RACKSPACE LEGACY EMAIL SUPPORT
-- ============================================================================

CREATE TABLE IF NOT EXISTS `staff_email_accounts` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `staff_id` INT NOT NULL,
  `email` VARCHAR(255) NOT NULL,
  `display_name` VARCHAR(255),
  `account_type` ENUM('standard', 'rackspace', 'legacy') DEFAULT 'standard',
  `custom_signature` LONGTEXT,
  `is_default` TINYINT DEFAULT 0,
  `is_active` TINYINT DEFAULT 1,
  `is_legacy` TINYINT DEFAULT 0,
  `sync_status` ENUM('pending_sync', 'syncing', 'synced', 'error') DEFAULT 'pending_sync',
  `last_sync_at` TIMESTAMP NULL,
  `encrypted_password` LONGTEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `unique_email_staff` (`staff_id`, `email`),
  INDEX `idx_staff_id` (`staff_id`),
  INDEX `idx_sync_status` (`sync_status`),
  INDEX `idx_is_legacy` (`is_legacy`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `legacy_email_sync_config` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `account_id` INT NOT NULL,
  `sync_interval_seconds` INT DEFAULT 300,
  `enabled` TINYINT DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `unique_account` (`account_id`),
  FOREIGN KEY (`account_id`) REFERENCES `staff_email_accounts`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- MULTI-PROFILE & DELEGATION SUPPORT
-- ============================================================================

CREATE TABLE IF NOT EXISTS `staff_profile_access` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `staff_id` INT NOT NULL,
  `profile_id` INT NOT NULL,
  `role` ENUM('owner', 'admin', 'delegate', 'read_only') DEFAULT 'delegate',
  `granted_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `unique_access` (`staff_id`, `profile_id`),
  INDEX `idx_profile_id` (`profile_id`),
  INDEX `idx_role` (`role`),
  FOREIGN KEY (`profile_id`) REFERENCES `staff_email_accounts`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- AI MESSAGE ENHANCEMENT & SMART REPLIES
-- ============================================================================

CREATE TABLE IF NOT EXISTS `email_enhancements` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `email_id` INT NOT NULL,
  `staff_id` INT NOT NULL,
  `original_message` LONGTEXT NOT NULL,
  `enhanced_message` LONGTEXT NOT NULL,
  `tone` ENUM('professional', 'friendly', 'formal', 'casual', 'warm') DEFAULT 'professional',
  `metadata` JSON,
  `status` ENUM('pending_approval', 'approved', 'rejected', 'applied') DEFAULT 'pending_approval',
  `approved_at` TIMESTAMP NULL,
  `rejected_at` TIMESTAMP NULL,
  `rejection_reason` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_email_id` (`email_id`),
  INDEX `idx_staff_id` (`staff_id`),
  INDEX `idx_status` (`status`),
  FOREIGN KEY (`email_id`) REFERENCES `emails`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `smart_reply_suggestions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `email_id` INT NOT NULL,
  `suggestion_text` LONGTEXT NOT NULL,
  `suggestion_order` INT DEFAULT 0,
  `relevance_score` INT DEFAULT 75,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_email_id` (`email_id`),
  INDEX `idx_relevance` (`relevance_score`),
  FOREIGN KEY (`email_id`) REFERENCES `emails`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `smart_reply_usage` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `suggestion_id` INT NOT NULL,
  `staff_id` INT NOT NULL,
  `used_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `was_customized` TINYINT DEFAULT 0,
  INDEX `idx_suggestion_id` (`suggestion_id`),
  INDEX `idx_staff_id` (`staff_id`),
  FOREIGN KEY (`suggestion_id`) REFERENCES `smart_reply_suggestions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `smart_reply_feedback` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `suggestion_id` INT NOT NULL,
  `staff_id` INT NOT NULL,
  `helpful` TINYINT DEFAULT 1,
  `notes` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_suggestion_id` (`suggestion_id`),
  INDEX `idx_helpful` (`helpful`),
  FOREIGN KEY (`suggestion_id`) REFERENCES `smart_reply_suggestions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- EMAIL CONVERSATIONS & THREADING
-- ============================================================================

CREATE TABLE IF NOT EXISTS `email_conversations` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `staff_id` INT NOT NULL,
  `thread_subject` VARCHAR(512),
  `primary_participant` VARCHAR(255),
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_staff_id` (`staff_id`),
  INDEX `idx_subject` (`thread_subject`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `emails` ADD COLUMN IF NOT EXISTS `conversation_id` INT,
                     ADD CONSTRAINT `fk_conversation` FOREIGN KEY (`conversation_id`) REFERENCES `email_conversations`(`id`) ON DELETE SET NULL;

-- ============================================================================
-- ADVANCED FEATURES: TEMPLATES, SCHEDULING, REMINDERS
-- ============================================================================

CREATE TABLE IF NOT EXISTS `email_templates` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `staff_id` INT NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `subject` VARCHAR(512),
  `body` LONGTEXT NOT NULL,
  `category` VARCHAR(100) DEFAULT 'general',
  `tags` JSON,
  `usage_count` INT DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_staff_id` (`staff_id`),
  INDEX `idx_category` (`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `scheduled_emails` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `staff_id` INT NOT NULL,
  `to_address` VARCHAR(255) NOT NULL,
  `from_profile` VARCHAR(255),
  `subject` VARCHAR(512),
  `body` LONGTEXT,
  `scheduled_send_at` TIMESTAMP NOT NULL,
  `status` ENUM('pending', 'sent', 'failed', 'cancelled') DEFAULT 'pending',
  `sent_at` TIMESTAMP NULL,
  `error_message` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_staff_id` (`staff_id`),
  INDEX `idx_scheduled_send_at` (`scheduled_send_at`),
  INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `follow_up_reminders` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `email_id` INT NOT NULL,
  `staff_id` INT NOT NULL,
  `remind_at` TIMESTAMP NOT NULL,
  `note` TEXT,
  `status` ENUM('pending', 'sent', 'dismissed') DEFAULT 'pending',
  `sent_at` TIMESTAMP NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_email_id` (`email_id`),
  INDEX `idx_staff_id` (`staff_id`),
  INDEX `idx_remind_at` (`remind_at`),
  INDEX `idx_status` (`status`),
  FOREIGN KEY (`email_id`) REFERENCES `emails`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- READ RECEIPTS & TRACKING
-- ============================================================================

ALTER TABLE `emails` ADD COLUMN IF NOT EXISTS `track_opens` TINYINT DEFAULT 0,
                     ADD COLUMN IF NOT EXISTS `open_tracking_token` VARCHAR(255);

CREATE TABLE IF NOT EXISTS `email_open_tracking` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `email_id` INT NOT NULL,
  `opened_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `ip_address` VARCHAR(45),
  `user_agent` TEXT,
  `INDEX idx_email_id` (`email_id`),
  INDEX `idx_opened_at` (`opened_at`),
  FOREIGN KEY (`email_id`) REFERENCES `emails`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- DRAFTS & COMPOSITION
-- ============================================================================

CREATE TABLE IF NOT EXISTS `email_drafts` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `email_id` INT,
  `staff_id` INT NOT NULL,
  `to_address` VARCHAR(255) NOT NULL,
  `cc_address` VARCHAR(255),
  `bcc_address` VARCHAR(255),
  `subject` VARCHAR(512),
  `body` LONGTEXT,
  `suggestion_id` INT,
  `status` ENUM('draft', 'scheduled', 'sent') DEFAULT 'draft',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_staff_id` (`staff_id`),
  INDEX `idx_status` (`status`),
  FOREIGN KEY (`email_id`) REFERENCES `emails`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`suggestion_id`) REFERENCES `smart_reply_suggestions`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- CONVERSATION ANALYSIS
-- ============================================================================

CREATE TABLE IF NOT EXISTS `conversation_analysis` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `conversation_id` INT NOT NULL,
  `staff_id` INT NOT NULL,
  `sentiment` ENUM('positive', 'neutral', 'negative'),
  `urgency_score` INT DEFAULT 0,
  `key_topics` JSON,
  `sentiment_trend` JSON,
  `analyzed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `unique_analysis` (`conversation_id`, `staff_id`),
  INDEX `idx_sentiment` (`sentiment`),
  INDEX `idx_urgency` (`urgency_score`),
  FOREIGN KEY (`conversation_id`) REFERENCES `email_conversations`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- PERFORMANCE INDEXES FOR ADVANCED QUERIES
-- ============================================================================

-- Optimize email retrieval with multiple filters
CREATE INDEX IF NOT EXISTS `idx_emails_staff_folder_date`
ON `emails` (`staff_id`, `folder`, `received_at`);

-- Optimize conversation queries
CREATE INDEX IF NOT EXISTS `idx_emails_conversation_staff`
ON `emails` (`conversation_id`, `staff_id`);

-- Optimize search across enhancements and suggestions
CREATE INDEX IF NOT EXISTS `idx_emails_enhanced_suggestions`
ON `emails` (`staff_id`, `id`);

-- Optimize scheduled email queries
CREATE INDEX IF NOT EXISTS `idx_scheduled_send_status`
ON `scheduled_emails` (`status`, `scheduled_send_at`);

-- Optimize reminder queries
CREATE INDEX IF NOT EXISTS `idx_reminders_staff_pending`
ON `follow_up_reminders` (`staff_id`, `status`, `remind_at`);
