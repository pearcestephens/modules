-- Webhook and Communication Events Tables

-- Webhook receipt log
CREATE TABLE IF NOT EXISTS webhook_log (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    platform ENUM('cis_messenger', 'other') NOT NULL DEFAULT 'cis_messenger',
    payload LONGTEXT NOT NULL,
    headers JSON,
    received_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_platform_received (platform, received_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Communication events from CIS Messenger
CREATE TABLE IF NOT EXISTS communication_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    staff_id INT UNSIGNED NOT NULL,
    platform ENUM('cis_messenger', 'other') NOT NULL DEFAULT 'cis_messenger',
    event_type VARCHAR(100) NOT NULL COMMENT 'message.created, message.deleted, file.shared',
    message_id VARCHAR(255),
    recipient_staff_id INT UNSIGNED COMMENT 'For direct messages',
    channel_id VARCHAR(255) COMMENT 'For group chats',
    message_text TEXT,
    is_direct_message BOOLEAN DEFAULT FALSE,
    metadata JSON,
    received_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_staff_received (staff_id, received_at),
    INDEX idx_platform_event (platform, event_type),
    INDEX idx_channel (channel_id),
    INDEX idx_recipient (recipient_staff_id),
    INDEX idx_message_id (message_id),
    FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE CASCADE,
    FOREIGN KEY (recipient_staff_id) REFERENCES staff(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- CIS Messenger channels metadata
CREATE TABLE IF NOT EXISTS messenger_channels (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    channel_id VARCHAR(255) UNIQUE NOT NULL,
    channel_name VARCHAR(255) NOT NULL,
    channel_type ENUM('public', 'private', 'direct') DEFAULT 'public',
    created_by INT UNSIGNED,
    member_count INT UNSIGNED DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_channel (channel_id),
    INDEX idx_type (channel_type),
    INDEX idx_active (is_active),
    FOREIGN KEY (created_by) REFERENCES staff(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Fraud analysis queue
CREATE TABLE IF NOT EXISTS fraud_analysis_queue (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    staff_id INT UNSIGNED NOT NULL,
    trigger_source VARCHAR(100) NOT NULL COMMENT 'cis_messenger_webhook, system_access_anomaly, etc',
    trigger_data JSON,
    priority ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
    status ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending',
    result JSON,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    processed_at DATETIME,
    INDEX idx_staff_status (staff_id, status),
    INDEX idx_priority_created (priority, created_at),
    FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
