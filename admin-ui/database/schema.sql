-- Admin UI Module Schema
-- VS Code themed admin interface with AI agent configuration

CREATE TABLE IF NOT EXISTS `theme_themes` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `slug` VARCHAR(100) NOT NULL UNIQUE,
    `base_theme` ENUM('vs-dark', 'vs-light', 'hc-black') DEFAULT 'vs-dark',
    `primary_color` VARCHAR(7) NOT NULL DEFAULT '#007ACC',
    `secondary_color` VARCHAR(7) NOT NULL DEFAULT '#1E1E1E',
    `accent_color` VARCHAR(7) NOT NULL DEFAULT '#0098FF',
    `sidebar_bg` VARCHAR(7) NOT NULL DEFAULT '#252526',
    `editor_bg` VARCHAR(7) NOT NULL DEFAULT '#1E1E1E',
    `custom_css` TEXT,
    `is_active` BOOLEAN DEFAULT FALSE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_active (`is_active`),
    INDEX idx_slug (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `theme_settings` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED,
    `setting_key` VARCHAR(100) NOT NULL,
    `setting_value` TEXT,
    `setting_type` ENUM('string', 'boolean', 'integer', 'json') DEFAULT 'string',
    `category` VARCHAR(50) DEFAULT 'general',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_user_setting` (`user_id`, `setting_key`),
    INDEX idx_category (`category`),
    INDEX idx_user (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `theme_ai_configs` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `agent_type` VARCHAR(50) NOT NULL,
    `model` VARCHAR(100) DEFAULT 'gpt-4',
    `temperature` DECIMAL(3,2) DEFAULT 0.70,
    `max_tokens` INT DEFAULT 4000,
    `system_prompt` TEXT,
    `tools_enabled` JSON,
    `context_window` INT DEFAULT 8000,
    `is_active` BOOLEAN DEFAULT TRUE,
    `created_by` INT UNSIGNED,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_active (`is_active`),
    INDEX idx_type (`agent_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `theme_analytics` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED,
    `page_path` VARCHAR(255) NOT NULL,
    `action` VARCHAR(100),
    `duration_ms` INT UNSIGNED,
    `session_id` VARCHAR(64),
    `ip_address` VARCHAR(45),
    `user_agent` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (`user_id`),
    INDEX idx_page (`page_path`),
    INDEX idx_created (`created_at`),
    INDEX idx_session (`session_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default theme
INSERT INTO `theme_themes` (`name`, `slug`, `base_theme`, `primary_color`, `is_active`)
VALUES ('VS Code Dark', 'vscode-dark', 'vs-dark', '#007ACC', TRUE)
ON DUPLICATE KEY UPDATE `name` = `name`;

-- Insert default AI agent config
INSERT INTO `theme_ai_configs` (`name`, `agent_type`, `system_prompt`, `tools_enabled`)
VALUES (
    'Default CIS Assistant',
    'general',
    'You are an AI assistant for the CIS (Central Information System) at The Vape Shed. Help staff with tasks efficiently.',
    '["search", "file_read", "database_query"]'
)
ON DUPLICATE KEY UPDATE `name` = `name`;
