-- ============================================================================
-- Notification & Messenger System - CLEAN SCHEMA
-- ============================================================================
-- MariaDB 10.5.29
-- Fresh creation - all tables from scratch
-- ============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================================
-- NOTIFICATIONS TABLES
-- ============================================================================

CREATE TABLE notifications (
    notification_id BIGINT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    category VARCHAR(50) NOT NULL,
    priority VARCHAR(20) DEFAULT 'normal',
    title VARCHAR(255) NOT NULL,
    message TEXT,
    triggered_by_user_id INT,
    event_reference_id VARCHAR(255),
    event_reference_type VARCHAR(50),
    action_url VARCHAR(500),
    action_label VARCHAR(100),
    channels JSON,
    data JSON,
    is_read BOOLEAN DEFAULT FALSE,
    read_at DATETIME,
    is_archived BOOLEAN DEFAULT FALSE,
    is_deleted BOOLEAN DEFAULT FALSE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_user_id (user_id),
    KEY idx_category (category),
    KEY idx_priority (priority),
    KEY idx_is_read (is_read),
    KEY idx_created_at (created_at),
    KEY idx_event_ref (event_reference_type, event_reference_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE notifications ADD FULLTEXT INDEX ft_title_message (title, message);

CREATE TABLE notification_preferences (
    preference_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL UNIQUE,
    in_app_enabled BOOLEAN DEFAULT TRUE,
    in_app_sound BOOLEAN DEFAULT TRUE,
    in_app_desktop_alert BOOLEAN DEFAULT TRUE,
    email_enabled BOOLEAN DEFAULT TRUE,
    email_frequency VARCHAR(50) DEFAULT 'daily',
    email_critical_only BOOLEAN DEFAULT FALSE,
    email_verified BOOLEAN DEFAULT FALSE,
    email_address VARCHAR(255),
    push_enabled BOOLEAN DEFAULT TRUE,
    push_vibration BOOLEAN DEFAULT TRUE,
    push_sound BOOLEAN DEFAULT TRUE,
    push_verified BOOLEAN DEFAULT FALSE,
    sms_enabled BOOLEAN DEFAULT FALSE,
    sms_critical_only BOOLEAN DEFAULT TRUE,
    sms_verified BOOLEAN DEFAULT FALSE,
    sms_phone_number VARCHAR(20),
    message_notifications BOOLEAN DEFAULT TRUE,
    news_notifications BOOLEAN DEFAULT TRUE,
    issue_notifications BOOLEAN DEFAULT TRUE,
    alert_notifications BOOLEAN DEFAULT TRUE,
    dnd_enabled BOOLEAN DEFAULT FALSE,
    dnd_start_time TIME DEFAULT '22:00:00',
    dnd_end_time TIME DEFAULT '08:00:00',
    dnd_allow_critical BOOLEAN DEFAULT TRUE,
    muted_conversation_ids JSON,
    blocked_user_ids JSON,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE notification_delivery_queue (
    queue_id BIGINT PRIMARY KEY AUTO_INCREMENT,
    notification_id BIGINT NOT NULL,
    user_id INT NOT NULL,
    channel VARCHAR(50) NOT NULL,
    status VARCHAR(50) DEFAULT 'pending',
    error_message TEXT,
    attempts INT DEFAULT 0,
    last_attempt_at DATETIME,
    data JSON,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    delivered_at DATETIME,
    KEY idx_notification_id (notification_id),
    KEY idx_user_id (user_id),
    KEY idx_channel (channel),
    KEY idx_status (status),
    KEY idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- MESSENGER TABLES
-- ============================================================================

CREATE TABLE chat_conversations (
    conversation_id BIGINT PRIMARY KEY AUTO_INCREMENT,
    type VARCHAR(50) NOT NULL,
    name VARCHAR(255),
    description TEXT,
    avatar_url VARCHAR(500),
    created_by_user_id INT NOT NULL,
    last_message_id BIGINT,
    last_message_at DATETIME,
    message_count INT DEFAULT 0,
    member_count INT DEFAULT 0,
    is_archived BOOLEAN DEFAULT FALSE,
    is_muted BOOLEAN DEFAULT FALSE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_type (type),
    KEY idx_created_by (created_by_user_id),
    KEY idx_last_message_at (last_message_at),
    KEY idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE chat_messages (
    message_id BIGINT PRIMARY KEY AUTO_INCREMENT,
    conversation_id BIGINT NOT NULL,
    sender_user_id INT NOT NULL,
    message_text TEXT NOT NULL,
    reply_to_message_id BIGINT,
    thread_id BIGINT,
    attachments JSON,
    mentions JSON,
    reactions JSON,
    is_edited BOOLEAN DEFAULT FALSE,
    edited_at DATETIME,
    is_deleted BOOLEAN DEFAULT FALSE,
    deleted_by_user_id INT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_conversation_id (conversation_id),
    KEY idx_sender_user_id (sender_user_id),
    KEY idx_created_at (created_at),
    KEY idx_reply_to (reply_to_message_id),
    KEY idx_deleted (is_deleted)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE chat_messages ADD FULLTEXT INDEX ft_message_text (message_text);

CREATE TABLE chat_message_read_receipts (
    receipt_id BIGINT PRIMARY KEY AUTO_INCREMENT,
    message_id BIGINT NOT NULL,
    user_id INT NOT NULL,
    read_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    KEY idx_message_id (message_id),
    KEY idx_user_id (user_id),
    KEY idx_read_at (read_at),
    UNIQUE KEY idx_message_user (message_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE chat_group_members (
    member_id BIGINT PRIMARY KEY AUTO_INCREMENT,
    conversation_id BIGINT NOT NULL,
    user_id INT NOT NULL,
    role VARCHAR(50) DEFAULT 'member',
    is_admin BOOLEAN DEFAULT FALSE,
    is_moderator BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT TRUE,
    last_read_at DATETIME,
    last_read_message_id BIGINT,
    joined_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    left_at DATETIME,
    KEY idx_conversation_id (conversation_id),
    KEY idx_user_id (user_id),
    KEY idx_is_active (is_active),
    UNIQUE KEY idx_conv_user (conversation_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE chat_typing_indicators (
    indicator_id BIGINT PRIMARY KEY AUTO_INCREMENT,
    conversation_id BIGINT NOT NULL,
    user_id INT NOT NULL,
    is_typing BOOLEAN DEFAULT TRUE,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_conversation_id (conversation_id),
    KEY idx_user_id (user_id),
    UNIQUE KEY idx_conv_user (conversation_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE chat_blocked_users (
    block_id BIGINT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    blocked_user_id INT NOT NULL,
    reason VARCHAR(255),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    KEY idx_user_id (user_id),
    KEY idx_blocked_user_id (blocked_user_id),
    UNIQUE KEY idx_user_blocked (user_id, blocked_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- INTEGRATION TABLES
-- ============================================================================

CREATE TABLE notification_messenger_links (
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
-- DONE
-- ============================================================================

SET FOREIGN_KEY_CHECKS = 1;

SELECT 'Schema creation complete!' as status;
