-- ============================================================================
-- Notification & Messenger System Database Schema
-- ============================================================================
-- MariaDB 10.5.29 Compatible Schema
-- Creates all tables needed for notifications and messenger system
-- Run this once to initialize the database structure
-- ============================================================================

-- ============================================================================
-- CLEANUP: DROP ALL EXISTING TABLES (Fresh Start)
-- ============================================================================
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS notification_messenger_links;
DROP TABLE IF EXISTS chat_blocked_users;
DROP TABLE IF EXISTS chat_typing_indicators;
DROP TABLE IF EXISTS chat_group_members;
DROP TABLE IF EXISTS chat_message_read_receipts;
DROP TABLE IF EXISTS chat_messages;
DROP TABLE IF EXISTS chat_conversations;
DROP TABLE IF EXISTS notification_delivery_queue;
DROP TABLE IF EXISTS notification_preferences;
DROP TABLE IF EXISTS notifications;

-- KEEP FK CHECKS OFF DURING TABLE CREATION - will re-enable at end

-- ============================================================================
-- NOTIFICATIONS SYSTEM TABLES
-- ============================================================================

/**
 * Notifications Table
 * Stores all notifications sent to users
 */
CREATE TABLE IF NOT EXISTS notifications (
    notification_id BIGINT PRIMARY KEY AUTO_INCREMENT,

    -- User and trigger info
    user_id INT NOT NULL,
    category VARCHAR(50) NOT NULL,      -- 'message', 'news', 'issue', 'alert'
    priority VARCHAR(20) NOT NULL DEFAULT 'normal', -- 'low', 'normal', 'high', 'critical'

    -- Notification content
    title VARCHAR(255) NOT NULL,
    message TEXT,
    triggered_by_user_id INT,           -- User who triggered the notification

    -- Event reference (link back to originating event)
    event_reference_id VARCHAR(255),    -- ID of the thing that triggered this
    event_reference_type VARCHAR(50),   -- Type: message_id, post_id, issue_id, etc.

    -- Action
    action_url VARCHAR(500),            -- URL to act on the notification
    action_label VARCHAR(100),          -- Text for action button

    -- Channel configuration (JSON)
    channels JSON,                      -- ['in-app', 'email', 'push', 'sms']
    data JSON,                          -- Additional notification data

    -- Status tracking
    is_read BOOLEAN DEFAULT FALSE,
    read_at DATETIME,
    is_archived BOOLEAN DEFAULT FALSE,
    is_deleted BOOLEAN DEFAULT FALSE,

    -- Timestamps
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Indexes
    KEY idx_user_id (user_id),
    KEY idx_category (category),
    KEY idx_priority (priority),
    KEY idx_is_read (is_read),
    KEY idx_created_at (created_at),
    KEY idx_event_reference (event_reference_type, event_reference_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add FULLTEXT index separately (MariaDB compatibility)
ALTER TABLE notifications ADD FULLTEXT INDEX ft_title_message (title, message);

/**
 * Notification Preferences Table
 * Stores user notification settings and preferences
 */
CREATE TABLE IF NOT EXISTS notification_preferences (
    preference_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL UNIQUE,

    -- In-app notifications
    in_app_enabled BOOLEAN DEFAULT TRUE,
    in_app_sound BOOLEAN DEFAULT TRUE,
    in_app_desktop_alert BOOLEAN DEFAULT TRUE,

    -- Email notifications
    email_enabled BOOLEAN DEFAULT TRUE,
    email_frequency VARCHAR(50) DEFAULT 'daily',  -- 'immediate', 'hourly', 'daily', 'weekly'
    email_critical_only BOOLEAN DEFAULT FALSE,
    email_verified BOOLEAN DEFAULT FALSE,
    email_address VARCHAR(255),

    -- Push notifications
    push_enabled BOOLEAN DEFAULT TRUE,
    push_vibration BOOLEAN DEFAULT TRUE,
    push_sound BOOLEAN DEFAULT TRUE,
    push_verified BOOLEAN DEFAULT FALSE,

    -- SMS notifications
    sms_enabled BOOLEAN DEFAULT FALSE,
    sms_critical_only BOOLEAN DEFAULT TRUE,
    sms_verified BOOLEAN DEFAULT FALSE,
    sms_phone_number VARCHAR(20),

    -- Category preferences
    message_notifications BOOLEAN DEFAULT TRUE,
    news_notifications BOOLEAN DEFAULT TRUE,
    issue_notifications BOOLEAN DEFAULT TRUE,
    alert_notifications BOOLEAN DEFAULT TRUE,

    -- Do Not Disturb
    dnd_enabled BOOLEAN DEFAULT FALSE,
    dnd_start_time TIME DEFAULT '22:00:00',
    dnd_end_time TIME DEFAULT '08:00:00',
    dnd_allow_critical BOOLEAN DEFAULT TRUE,

    -- Muting
    muted_conversation_ids JSON,        -- Conversation IDs to mute
    blocked_user_ids JSON,              -- User IDs to block

    -- Timestamps
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    KEY idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/**
 * Notification Delivery Queue
 * Tracks delivery of notifications to different channels
 */
CREATE TABLE IF NOT EXISTS notification_delivery_queue (
    queue_id BIGINT PRIMARY KEY AUTO_INCREMENT,
    notification_id BIGINT NOT NULL,
    user_id INT NOT NULL,
    channel VARCHAR(50) NOT NULL,      -- 'email', 'push', 'sms'

    -- Delivery tracking
    status VARCHAR(50) DEFAULT 'pending',  -- 'pending', 'sent', 'failed', 'bounced'
    error_message TEXT,
    attempts INT DEFAULT 0,
    last_attempt_at DATETIME,

    -- Data
    data JSON,                          -- Channel-specific data

    -- Timestamps
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    delivered_at DATETIME,

    KEY idx_notification_id (notification_id),
    KEY idx_user_id (user_id),
    KEY idx_channel (channel),
    KEY idx_status (status),
    KEY idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- MESSENGER SYSTEM TABLES
-- ============================================================================

/**
 * Chat Conversations Table
 * Stores conversation metadata for all chat types
 */
CREATE TABLE IF NOT EXISTS chat_conversations (
    conversation_id BIGINT PRIMARY KEY AUTO_INCREMENT,

    -- Conversation type
    type VARCHAR(50) NOT NULL,         -- 'direct', 'group', 'broadcast', 'bot'

    -- Names and descriptions
    name VARCHAR(255),                 -- Required for group/broadcast
    description TEXT,                  -- Optional for group/broadcast
    avatar_url VARCHAR(500),           -- Group/broadcast avatar

    -- Metadata
    created_by_user_id INT NOT NULL,

    -- Message tracking
    last_message_id BIGINT,
    last_message_at DATETIME,
    message_count INT DEFAULT 0,

    -- Settings
    is_archived BOOLEAN DEFAULT FALSE,
    is_muted BOOLEAN DEFAULT FALSE,    -- For group chats
    member_count INT DEFAULT 0,

    -- Timestamps
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Indexes
    KEY idx_type (type),
    KEY idx_created_by (created_by_user_id),
    KEY idx_last_message_at (last_message_at),
    KEY idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/**
 * Chat Messages Table
 * Stores individual messages in conversations
 */
CREATE TABLE IF NOT EXISTS chat_messages (
    message_id BIGINT PRIMARY KEY AUTO_INCREMENT,

    -- References (no FK constraints - added separately later)
    conversation_id BIGINT NOT NULL,
    sender_user_id INT NOT NULL,

    -- Message content
    message_text TEXT NOT NULL,

    -- Threading support
    reply_to_message_id BIGINT,
    thread_id BIGINT,                  -- For message threads

    -- Rich media
    attachments JSON,                  -- Array of files/media

    -- Mentions and reactions
    mentions JSON,                     -- Array of mentioned user IDs
    reactions JSON,                    -- Object: {emoji: [user_ids]}

    -- Status tracking
    is_edited BOOLEAN DEFAULT FALSE,
    edited_at DATETIME,
    is_deleted BOOLEAN DEFAULT FALSE,
    deleted_by_user_id INT,

    -- Timestamps
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Indexes (FULLTEXT added separately below)
    KEY idx_conversation_id (conversation_id),
    KEY idx_sender_user_id (sender_user_id),
    KEY idx_created_at (created_at),
    KEY idx_reply_to (reply_to_message_id),
    KEY idx_deleted (is_deleted)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add FULLTEXT index separately (MariaDB compatibility)
ALTER TABLE chat_messages ADD FULLTEXT INDEX ft_message_text (message_text);

/**
 * Chat Message Read Receipts
 * Tracks which users have read which messages
 */
CREATE TABLE IF NOT EXISTS chat_message_read_receipts (
    receipt_id BIGINT PRIMARY KEY AUTO_INCREMENT,
    message_id BIGINT NOT NULL,
    user_id INT NOT NULL,
    read_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    -- Indexes
    KEY idx_message_id (message_id),
    KEY idx_user_id (user_id),
    KEY idx_read_at (read_at),
    UNIQUE KEY idx_message_user (message_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/**
 * Chat Group Members
 * Tracks members of group conversations
 */
CREATE TABLE IF NOT EXISTS chat_group_members (
    member_id BIGINT PRIMARY KEY AUTO_INCREMENT,
    conversation_id BIGINT NOT NULL,
    user_id INT NOT NULL,

    -- Member role and status
    role VARCHAR(50) DEFAULT 'member', -- 'admin', 'moderator', 'member'
    is_admin BOOLEAN DEFAULT FALSE,
    is_moderator BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT TRUE,

    -- Last seen info
    last_read_at DATETIME,
    last_read_message_id BIGINT,

    -- Timestamps
    joined_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    left_at DATETIME,

    -- Indexes
    KEY idx_conversation_id (conversation_id),
    KEY idx_user_id (user_id),
    KEY idx_is_active (is_active),
    UNIQUE KEY idx_conv_user (conversation_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/**
 * Chat Typing Indicators
 * Tracks who is typing in each conversation (real-time)
 */
CREATE TABLE IF NOT EXISTS chat_typing_indicators (
    indicator_id BIGINT PRIMARY KEY AUTO_INCREMENT,
    conversation_id BIGINT NOT NULL,
    user_id INT NOT NULL,
    is_typing BOOLEAN DEFAULT TRUE,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Indexes
    KEY idx_conversation_id (conversation_id),
    KEY idx_user_id (user_id),
    UNIQUE KEY idx_conv_user (conversation_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/**
 * Chat Blocked Users
 * Tracks blocked users for each user
 */
CREATE TABLE IF NOT EXISTS chat_blocked_users (
    block_id BIGINT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    blocked_user_id INT NOT NULL,
    reason VARCHAR(255),
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    KEY idx_user_id (user_id),
    KEY idx_blocked_user_id (blocked_user_id),
    UNIQUE KEY idx_user_blocked (user_id, blocked_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- INTEGRATION TABLES
-- ============================================================================

/**
 * Notification-Message Integration
 * Links notifications to messenger conversations
 */
CREATE TABLE IF NOT EXISTS notification_messenger_links (
    link_id BIGINT PRIMARY KEY AUTO_INCREMENT,
    notification_id BIGINT NOT NULL,
    conversation_id BIGINT,
    message_id BIGINT,

    KEY idx_notification_id (notification_id),
    KEY idx_conversation_id (conversation_id),
    KEY idx_message_id (message_id),
    UNIQUE KEY idx_notif_conv (notification_id, conversation_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- FOREIGN KEY CONSTRAINTS SECTION
-- ============================================================================
-- IMPORTANT: If you get foreign key errors, you have 3 options:
--
-- OPTION 1: Comment out the ALTER TABLE statements below (safest for now)
--           The tables will work without foreign keys
--
-- OPTION 2: Ensure cis_users exists and has user_id as INT PRIMARY KEY
--           Then uncomment these statements and run them separately
--
-- OPTION 3: Use the standalone script version (includes user table creation)
-- ============================================================================

-- Uncomment these lines ONLY after confirming cis_users table exists
-- and has user_id column as INT PRIMARY KEY
/*

ALTER TABLE notifications
ADD CONSTRAINT fk_notif_user FOREIGN KEY (user_id) REFERENCES cis_users(user_id) ON DELETE CASCADE,
ADD CONSTRAINT fk_notif_triggered_by FOREIGN KEY (triggered_by_user_id) REFERENCES cis_users(user_id) ON DELETE SET NULL;

ALTER TABLE notification_preferences
ADD CONSTRAINT fk_pref_user FOREIGN KEY (user_id) REFERENCES cis_users(user_id) ON DELETE CASCADE;

ALTER TABLE notification_delivery_queue
ADD CONSTRAINT fk_delivery_notif FOREIGN KEY (notification_id) REFERENCES notifications(notification_id) ON DELETE CASCADE,
ADD CONSTRAINT fk_delivery_user FOREIGN KEY (user_id) REFERENCES cis_users(user_id) ON DELETE CASCADE;

ALTER TABLE chat_conversations
ADD CONSTRAINT fk_conv_created_by FOREIGN KEY (created_by_user_id) REFERENCES cis_users(user_id) ON DELETE CASCADE;

ALTER TABLE chat_messages
ADD CONSTRAINT fk_msg_conversation FOREIGN KEY (conversation_id) REFERENCES chat_conversations(conversation_id) ON DELETE CASCADE,
ADD CONSTRAINT fk_msg_sender FOREIGN KEY (sender_user_id) REFERENCES cis_users(user_id) ON DELETE CASCADE,
ADD CONSTRAINT fk_msg_reply_to FOREIGN KEY (reply_to_message_id) REFERENCES chat_messages(message_id) ON DELETE SET NULL;

ALTER TABLE chat_message_read_receipts
ADD CONSTRAINT fk_receipt_message FOREIGN KEY (message_id) REFERENCES chat_messages(message_id) ON DELETE CASCADE,
ADD CONSTRAINT fk_receipt_user FOREIGN KEY (user_id) REFERENCES cis_users(user_id) ON DELETE CASCADE;

ALTER TABLE chat_group_members
ADD CONSTRAINT fk_member_conversation FOREIGN KEY (conversation_id) REFERENCES chat_conversations(conversation_id) ON DELETE CASCADE,
ADD CONSTRAINT fk_member_user FOREIGN KEY (user_id) REFERENCES cis_users(user_id) ON DELETE CASCADE;

ALTER TABLE chat_typing_indicators
ADD CONSTRAINT fk_typing_conversation FOREIGN KEY (conversation_id) REFERENCES chat_conversations(conversation_id) ON DELETE CASCADE,
ADD CONSTRAINT fk_typing_user FOREIGN KEY (user_id) REFERENCES cis_users(user_id) ON DELETE CASCADE;

ALTER TABLE chat_blocked_users
ADD CONSTRAINT fk_block_user FOREIGN KEY (user_id) REFERENCES cis_users(user_id) ON DELETE CASCADE,
ADD CONSTRAINT fk_block_blocked_user FOREIGN KEY (blocked_user_id) REFERENCES cis_users(user_id) ON DELETE CASCADE;

ALTER TABLE notification_messenger_links
ADD CONSTRAINT fk_link_notif FOREIGN KEY (notification_id) REFERENCES notifications(notification_id) ON DELETE CASCADE,
ADD CONSTRAINT fk_link_conversation FOREIGN KEY (conversation_id) REFERENCES chat_conversations(conversation_id) ON DELETE SET NULL,
ADD CONSTRAINT fk_link_message FOREIGN KEY (message_id) REFERENCES chat_messages(message_id) ON DELETE SET NULL;

*/

-- ============================================================================
-- SETUP COMPLETE
-- ============================================================================
-- All tables created successfully!
-- Total Tables: 10 notification/messenger tables
-- Total Indexes: 40+ for optimal query performance
--
-- Re-enable foreign key checks
SET FOREIGN_KEY_CHECKS = 1;

-- NEXT STEPS:
-- 1. Run this script: mysql -u user -p database < notification_messenger_schema.sql
-- 2. Tables will be created (without foreign keys for now - safe!)
-- 3. Test the APIs to confirm data flow works
-- 4. Once cis_users table is confirmed, uncomment the FK section above and run separately
--
-- VERIFICATION:
-- SHOW TABLES;
-- DESCRIBE notifications;
-- SELECT COUNT(*) FROM chat_conversations;
