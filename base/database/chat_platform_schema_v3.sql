-- ============================================
-- CIS CHAT PLATFORM v3.0 - COMPLETE SCHEMA
-- ============================================
-- Enterprise chat with AI integration and gamification
-- Author: CIS Team
-- Created: 2025-11-11

-- ============================================
-- CORE CHAT TABLES
-- ============================================

-- Channels (group chats, departments, stores)
CREATE TABLE IF NOT EXISTS chat_channels (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    channel_type ENUM('direct', 'group', 'department', 'store', 'announcement', 'ai_assistant') DEFAULT 'group',
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_archived TINYINT(1) DEFAULT 0,
    is_private TINYINT(1) DEFAULT 0,
    max_members INT DEFAULT 500,
    INDEX idx_type (channel_type),
    INDEX idx_created_by (created_by),
    INDEX idx_archived (is_archived)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Channel participants (who's in which channel)
CREATE TABLE IF NOT EXISTS chat_channel_participants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    channel_id INT NOT NULL,
    user_id INT NOT NULL,
    role ENUM('owner', 'admin', 'member') DEFAULT 'member',
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_muted TINYINT(1) DEFAULT 0,
    is_pinned TINYINT(1) DEFAULT 0,
    last_read_at TIMESTAMP NULL,
    notification_settings JSON COMMENT '{"mentions": true, "all_messages": false}',
    UNIQUE KEY unique_participant (channel_id, user_id),
    INDEX idx_user_id (user_id),
    INDEX idx_channel_id (channel_id),
    FOREIGN KEY (channel_id) REFERENCES chat_channels(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Messages
CREATE TABLE IF NOT EXISTS chat_messages (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    channel_id INT NOT NULL,
    user_id INT NOT NULL,
    message TEXT NOT NULL,
    message_type ENUM('text', 'file', 'image', 'voice', 'video', 'system', 'ai_response') DEFAULT 'text',
    parent_message_id BIGINT NULL COMMENT 'For threaded replies',
    is_priority TINYINT(1) DEFAULT 0,
    is_pinned TINYINT(1) DEFAULT 0,
    is_edited TINYINT(1) DEFAULT 0,
    is_ai_generated TINYINT(1) DEFAULT 0,
    edited_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    metadata JSON COMMENT '{"links": [], "mentions": [], "ai_confidence": 0.95}',
    INDEX idx_channel_id (channel_id),
    INDEX idx_user_id (user_id),
    INDEX idx_created_at (created_at),
    INDEX idx_parent (parent_message_id),
    INDEX idx_deleted (deleted_at),
    FOREIGN KEY (channel_id) REFERENCES chat_channels(id) ON DELETE CASCADE,
    FULLTEXT KEY ft_message (message)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Message reads (who read what)
CREATE TABLE IF NOT EXISTS chat_message_reads (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    message_id BIGINT NOT NULL,
    user_id INT NOT NULL,
    read_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_read (message_id, user_id),
    INDEX idx_message_id (message_id),
    INDEX idx_user_id (user_id),
    FOREIGN KEY (message_id) REFERENCES chat_messages(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Message reactions (emoji reactions)
CREATE TABLE IF NOT EXISTS chat_message_reactions (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    message_id BIGINT NOT NULL,
    user_id INT NOT NULL,
    reaction VARCHAR(10) NOT NULL COMMENT 'Emoji unicode or shortcode',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_reaction (message_id, user_id, reaction),
    INDEX idx_message_id (message_id),
    INDEX idx_user_id (user_id),
    FOREIGN KEY (message_id) REFERENCES chat_messages(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- File attachments
CREATE TABLE IF NOT EXISTS chat_attachments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    message_id BIGINT NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_size INT NOT NULL COMMENT 'Bytes',
    mime_type VARCHAR(100) NOT NULL,
    thumbnail_path VARCHAR(500) NULL,
    ai_analysis JSON COMMENT '{"description": "", "has_text": false, "detected_objects": []}',
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    downloads INT DEFAULT 0,
    INDEX idx_message_id (message_id),
    INDEX idx_mime_type (mime_type),
    FOREIGN KEY (message_id) REFERENCES chat_messages(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Typing indicators (ephemeral - can be in-memory or short-lived DB)
CREATE TABLE IF NOT EXISTS chat_typing (
    id INT AUTO_INCREMENT PRIMARY KEY,
    channel_id INT NOT NULL,
    user_id INT NOT NULL,
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_typing (channel_id, user_id),
    INDEX idx_channel_user (channel_id, user_id),
    INDEX idx_started_at (started_at)
) ENGINE=MEMORY;

-- User presence status
CREATE TABLE IF NOT EXISTS chat_presence (
    user_id INT PRIMARY KEY,
    status ENUM('online', 'away', 'busy', 'offline', 'dnd') DEFAULT 'offline',
    status_message VARCHAR(200) NULL,
    last_seen TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    device_info JSON COMMENT '{"type": "web", "browser": "Chrome"}',
    INDEX idx_status (status),
    INDEX idx_last_seen (last_seen)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Mentions (@username)
CREATE TABLE IF NOT EXISTS chat_mentions (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    message_id BIGINT NOT NULL,
    mentioned_user_id INT NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    notified TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_mentioned_user (mentioned_user_id),
    INDEX idx_message_id (message_id),
    INDEX idx_unread (is_read, mentioned_user_id),
    FOREIGN KEY (message_id) REFERENCES chat_messages(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- AI INTEGRATION TABLES
-- ============================================

-- AI Insights generated from conversations
CREATE TABLE IF NOT EXISTS chat_ai_insights (
    id INT AUTO_INCREMENT PRIMARY KEY,
    message_id BIGINT NOT NULL,
    insight_type ENUM('answer', 'suggestion', 'clarification', 'warning', 'tip') DEFAULT 'answer',
    insight_text TEXT NOT NULL,
    confidence DECIMAL(3,2) DEFAULT 0.85,
    sources JSON COMMENT '["kb_article_123", "previous_conversation"]',
    is_helpful TINYINT(1) NULL COMMENT 'User feedback',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_message_id (message_id),
    INDEX idx_type (insight_type),
    FOREIGN KEY (message_id) REFERENCES chat_messages(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- AI Assistant conversations
CREATE TABLE IF NOT EXISTS chat_ai_conversations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    channel_id INT NULL,
    conversation_context JSON COMMENT 'Stores recent message history for context',
    model_used VARCHAR(50) DEFAULT 'gpt-4o-mini',
    total_messages INT DEFAULT 0,
    total_tokens INT DEFAULT 0,
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_message_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_channel_id (channel_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- AI Content moderation logs
CREATE TABLE IF NOT EXISTS chat_ai_moderation (
    id INT AUTO_INCREMENT PRIMARY KEY,
    message_id BIGINT NULL,
    user_id INT NOT NULL,
    content TEXT NOT NULL,
    flagged TINYINT(1) DEFAULT 0,
    flag_reason ENUM('spam', 'harassment', 'inappropriate', 'malicious_link', 'pii_leak') NULL,
    confidence DECIMAL(3,2),
    action_taken ENUM('none', 'flagged', 'blocked', 'user_warned') DEFAULT 'none',
    reviewed_by INT NULL COMMENT 'Admin who reviewed',
    reviewed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_flagged (flagged),
    INDEX idx_user_id (user_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- GAMIFICATION TABLES
-- ============================================

-- User statistics and gamification
CREATE TABLE IF NOT EXISTS chat_user_stats (
    user_id INT PRIMARY KEY,
    total_points INT DEFAULT 0,
    level INT DEFAULT 1,
    messages_sent INT DEFAULT 0,
    files_shared INT DEFAULT 0,
    helpful_reactions INT DEFAULT 0,
    channels_created INT DEFAULT 0,
    streak_days INT DEFAULT 0,
    last_message_date DATE NULL,
    achievements JSON COMMENT '["first_message", "100_messages", "file_master"]',
    week_start DATE NULL COMMENT 'For weekly leaderboard',
    week_points INT DEFAULT 0,
    month_points INT DEFAULT 0,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_total_points (total_points),
    INDEX idx_level (level),
    INDEX idx_week (week_start, week_points)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Achievement definitions
CREATE TABLE IF NOT EXISTS chat_achievements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) UNIQUE NOT NULL,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    icon VARCHAR(50) COMMENT 'Emoji or icon class',
    points_required INT DEFAULT 0,
    criteria JSON COMMENT '{"messages_sent": 100, "files_shared": 10}',
    badge_color VARCHAR(20) DEFAULT 'primary',
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- User achievements earned
CREATE TABLE IF NOT EXISTS chat_user_achievements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    achievement_id INT NOT NULL,
    earned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_showcased TINYINT(1) DEFAULT 0,
    UNIQUE KEY unique_user_achievement (user_id, achievement_id),
    INDEX idx_user_id (user_id),
    INDEX idx_earned_at (earned_at),
    FOREIGN KEY (achievement_id) REFERENCES chat_achievements(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Points transaction log
CREATE TABLE IF NOT EXISTS chat_points_log (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    points INT NOT NULL,
    reason VARCHAR(100) NOT NULL,
    reference_type ENUM('message', 'file', 'reaction', 'achievement', 'bonus') NOT NULL,
    reference_id BIGINT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- NOTIFICATION TABLES
-- ============================================

-- Push notification subscriptions
CREATE TABLE IF NOT EXISTS chat_push_subscriptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    endpoint TEXT NOT NULL,
    public_key VARCHAR(255) NOT NULL,
    auth_token VARCHAR(255) NOT NULL,
    device_type ENUM('web', 'mobile', 'desktop') DEFAULT 'web',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_used TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Notification queue
CREATE TABLE IF NOT EXISTS chat_notifications (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type ENUM('mention', 'direct_message', 'channel_invite', 'reaction', 'achievement', 'system') NOT NULL,
    title VARCHAR(200) NOT NULL,
    body TEXT NOT NULL,
    action_url VARCHAR(500) NULL,
    is_read TINYINT(1) DEFAULT 0,
    sent_push TINYINT(1) DEFAULT 0,
    sent_email TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    read_at TIMESTAMP NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_unread (user_id, is_read),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- ADMIN & MODERATION TABLES
-- ============================================

-- Pinned messages
CREATE TABLE IF NOT EXISTS chat_pinned_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    channel_id INT NOT NULL,
    message_id BIGINT NOT NULL,
    pinned_by INT NOT NULL,
    pinned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL,
    INDEX idx_channel_id (channel_id),
    FOREIGN KEY (channel_id) REFERENCES chat_channels(id) ON DELETE CASCADE,
    FOREIGN KEY (message_id) REFERENCES chat_messages(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Moderation actions log
CREATE TABLE IF NOT EXISTS chat_moderation_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    moderator_id INT NOT NULL,
    action_type ENUM('delete_message', 'mute_user', 'ban_user', 'warn_user', 'archive_channel') NOT NULL,
    target_user_id INT NULL,
    target_message_id BIGINT NULL,
    target_channel_id INT NULL,
    reason TEXT NOT NULL,
    duration INT NULL COMMENT 'Minutes for temporary actions',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_moderator (moderator_id),
    INDEX idx_target_user (target_user_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- ANALYTICS TABLES
-- ============================================

-- Daily chat analytics
CREATE TABLE IF NOT EXISTS chat_analytics_daily (
    id INT AUTO_INCREMENT PRIMARY KEY,
    date DATE NOT NULL,
    total_messages INT DEFAULT 0,
    total_files INT DEFAULT 0,
    total_reactions INT DEFAULT 0,
    active_users INT DEFAULT 0,
    new_channels INT DEFAULT 0,
    peak_concurrent_users INT DEFAULT 0,
    avg_response_time DECIMAL(10,2) DEFAULT 0 COMMENT 'Seconds',
    ai_insights_generated INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_date (date),
    INDEX idx_date (date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- SAMPLE DATA FOR TESTING
-- ============================================

-- Create default channels
INSERT IGNORE INTO chat_channels (id, name, description, channel_type, created_by) VALUES
(1, 'general', 'Company-wide announcements and general chat', 'announcement', 1),
(2, 'random', 'Off-topic discussions and fun stuff', 'group', 1),
(3, 'it-support', 'Technical help and CIS issues', 'department', 1),
(4, 'retail-team', 'Retail staff discussions', 'department', 1),
(5, 'warehouse', 'Warehouse operations and logistics', 'department', 1),
(6, 'ai-assistant', 'Chat with CIS AI Assistant', 'ai_assistant', 1);

-- Create default achievements
INSERT IGNORE INTO chat_achievements (code, name, description, icon, points_required, criteria) VALUES
('first_message', 'First Message', 'Sent your first message', '💬', 0, '{"messages_sent": 1}'),
('chatty_100', 'Chatty Cathy', 'Sent 100 messages', '🗣️', 100, '{"messages_sent": 100}'),
('chatty_1000', 'Chat Master', 'Sent 1,000 messages', '🎯', 1000, '{"messages_sent": 1000}'),
('file_master', 'File Master', 'Shared 50 files', '📁', 250, '{"files_shared": 50}'),
('helpful_hero', 'Helpful Hero', 'Received 100 helpful reactions', '⭐', 300, '{"helpful_reactions": 100}'),
('channel_creator', 'Channel Creator', 'Created 5 channels', '🏗️', 100, '{"channels_created": 5}'),
('week_warrior', 'Week Warrior', '7-day streak', '🔥', 70, '{"streak_days": 7}'),
('month_master', 'Month Master', '30-day streak', '👑', 300, '{"streak_days": 30}');

-- ============================================
-- VIEWS FOR REPORTING
-- ============================================

CREATE OR REPLACE VIEW chat_channel_activity AS
SELECT
    c.id,
    c.name,
    c.channel_type,
    COUNT(DISTINCT m.id) as total_messages,
    COUNT(DISTINCT m.user_id) as unique_users,
    COUNT(DISTINCT cp.user_id) as total_members,
    MAX(m.created_at) as last_message_at
FROM chat_channels c
LEFT JOIN chat_messages m ON c.id = m.channel_id AND m.deleted_at IS NULL
LEFT JOIN chat_channel_participants cp ON c.id = cp.channel_id
WHERE c.is_archived = 0
GROUP BY c.id;

CREATE OR REPLACE VIEW chat_user_activity AS
SELECT
    u.id,
    u.username,
    u.full_name,
    s.total_points,
    s.level,
    s.messages_sent,
    s.files_shared,
    s.streak_days,
    p.status,
    p.last_seen
FROM staff_accounts u
LEFT JOIN chat_user_stats s ON u.id = s.user_id
LEFT JOIN chat_presence p ON u.id = p.user_id;

-- ============================================
-- INDEXES FOR PERFORMANCE
-- ============================================

-- Add composite indexes for common queries
ALTER TABLE chat_messages ADD INDEX idx_channel_created (channel_id, created_at);
ALTER TABLE chat_messages ADD INDEX idx_user_created (user_id, created_at);
ALTER TABLE chat_message_reactions ADD INDEX idx_message_user (message_id, user_id);

-- ============================================
-- TRIGGERS FOR AUTO-STATS UPDATE
-- ============================================

DELIMITER //

CREATE TRIGGER after_message_insert
AFTER INSERT ON chat_messages
FOR EACH ROW
BEGIN
    -- Update user stats
    INSERT INTO chat_user_stats (user_id, messages_sent, last_message_date)
    VALUES (NEW.user_id, 1, CURDATE())
    ON DUPLICATE KEY UPDATE
        messages_sent = messages_sent + 1,
        last_message_date = CURDATE(),
        streak_days = IF(last_message_date = DATE_SUB(CURDATE(), INTERVAL 1 DAY),
                        streak_days + 1,
                        IF(last_message_date < DATE_SUB(CURDATE(), INTERVAL 1 DAY), 1, streak_days)
                     );

    -- Update daily analytics
    INSERT INTO chat_analytics_daily (date, total_messages, active_users)
    VALUES (CURDATE(), 1, 1)
    ON DUPLICATE KEY UPDATE
        total_messages = total_messages + 1;
END//

CREATE TRIGGER after_file_upload
AFTER INSERT ON chat_attachments
FOR EACH ROW
BEGIN
    -- Update user stats
    INSERT INTO chat_user_stats (user_id, files_shared)
    SELECT user_id, 1
    FROM chat_messages
    WHERE id = NEW.message_id
    ON DUPLICATE KEY UPDATE files_shared = files_shared + 1;

    -- Update daily analytics
    INSERT INTO chat_analytics_daily (date, total_files)
    VALUES (CURDATE(), 1)
    ON DUPLICATE KEY UPDATE total_files = total_files + 1;
END//

CREATE TRIGGER after_reaction_insert
AFTER INSERT ON chat_message_reactions
FOR EACH ROW
BEGIN
    -- Award points to message author
    UPDATE chat_user_stats s
    JOIN chat_messages m ON m.user_id = s.user_id
    SET s.helpful_reactions = s.helpful_reactions + 1
    WHERE m.id = NEW.message_id
      AND NEW.reaction IN ('👍', '❤️', '🔥', '💯');

    -- Update daily analytics
    INSERT INTO chat_analytics_daily (date, total_reactions)
    VALUES (CURDATE(), 1)
    ON DUPLICATE KEY UPDATE total_reactions = total_reactions + 1;
END//

DELIMITER ;

-- ============================================
-- GRANTS AND PERMISSIONS
-- ============================================

-- Grant necessary permissions to application user
GRANT SELECT, INSERT, UPDATE, DELETE ON jcepnzzkmj.chat_* TO 'jcepnzzkmj'@'localhost';
GRANT SELECT ON jcepnzzkmj.staff_accounts TO 'jcepnzzkmj'@'localhost';
FLUSH PRIVILEGES;

-- ============================================
-- COMPLETION
-- ============================================

SELECT 'Chat Platform Schema v3.0 installed successfully!' as Status;
SELECT COUNT(*) as total_tables FROM information_schema.tables
WHERE table_schema = 'jcepnzzkmj' AND table_name LIKE 'chat_%';
