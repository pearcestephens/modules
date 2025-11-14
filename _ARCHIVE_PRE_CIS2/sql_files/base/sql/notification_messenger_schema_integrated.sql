-- ============================================================================
-- Notification & Messenger System Database Schema (INTEGRATED VERSION)
-- ============================================================================
-- MariaDB 10.5.29 Compatible
-- This version integrates with EXISTING chat tables
-- ============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================================
-- NOTIFICATIONS SYSTEM TABLES (NEW)
-- ============================================================================

DROP TABLE IF EXISTS notification_messenger_links;
DROP TABLE IF EXISTS notification_delivery_queue;
DROP TABLE IF EXISTS notification_preferences;
DROP TABLE IF EXISTS notifications;

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
-- MESSENGER SYSTEM TABLES (EXTEND EXISTING)
-- ============================================================================

-- Note: chat_conversations, chat_channels, etc. already exist
-- We'll add any missing columns or tables needed

-- Add missing columns to chat_conversations if they don't exist
-- (These are metadata for enhanced functionality)
ALTER TABLE chat_conversations
ADD COLUMN IF NOT EXISTS last_message_id BIGINT,
ADD COLUMN IF NOT EXISTS last_message_at DATETIME,
ADD COLUMN IF NOT EXISTS message_count INT DEFAULT 0,
ADD COLUMN IF NOT EXISTS member_count INT DEFAULT 0,
ADD COLUMN IF NOT EXISTS is_archived BOOLEAN DEFAULT FALSE;

-- Create chat_messages table for individual messages
-- Using simpler structure that works with existing chat system
CREATE TABLE IF NOT EXISTS chat_messages (
    message_id BIGINT PRIMARY KEY AUTO_INCREMENT,

    -- References
    conversation_id BIGINT NOT NULL,
    sender_user_id INT NOT NULL,

    -- Message content
    message_text TEXT NOT NULL,

    -- Threading support
    reply_to_message_id BIGINT,
    thread_id BIGINT,

    -- Rich media
    attachments JSON,

    -- Mentions and reactions
    mentions JSON,
    reactions JSON,

    -- Status tracking
    is_edited BOOLEAN DEFAULT FALSE,
    edited_at DATETIME,
    is_deleted BOOLEAN DEFAULT FALSE,
    deleted_by_user_id INT,

    -- Timestamps
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Indexes
    KEY idx_conversation_id (conversation_id),
    KEY idx_sender_user_id (sender_user_id),
    KEY idx_created_at (created_at),
    KEY idx_reply_to (reply_to_message_id),
    KEY idx_deleted (is_deleted)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add FULLTEXT index separately
ALTER TABLE chat_messages ADD FULLTEXT INDEX ft_message_text (message_text);

-- Create group members table if it doesn't exist
CREATE TABLE IF NOT EXISTS chat_group_members (
    member_id BIGINT PRIMARY KEY AUTO_INCREMENT,
    conversation_id BIGINT NOT NULL,
    user_id INT NOT NULL,

    -- Member role and status
    role VARCHAR(50) DEFAULT 'member',
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

-- Create blocked users table
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
 * Links notifications to messenger conversations/messages
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
-- SETUP COMPLETE
-- ============================================================================
SET FOREIGN_KEY_CHECKS = 1;

-- Verification
SELECT 'Notification & Messenger System Tables Created Successfully!' as status;
SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES
WHERE TABLE_SCHEMA = DATABASE()
AND (TABLE_NAME LIKE 'notification%' OR TABLE_NAME LIKE 'chat_%')
ORDER BY TABLE_NAME;
