-- Control Panel Module Schema
-- System administration, backups, configuration management

CREATE TABLE IF NOT EXISTS `cp_backups` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `backup_type` ENUM('full', 'database', 'files', 'incremental') NOT NULL,
    `file_path` VARCHAR(500) NOT NULL,
    `file_size_bytes` BIGINT UNSIGNED,
    `backup_status` ENUM('pending', 'in_progress', 'completed', 'failed') DEFAULT 'pending',
    `started_at` TIMESTAMP NULL,
    `completed_at` TIMESTAMP NULL,
    `error_message` TEXT,
    `created_by` INT UNSIGNED,
    `retention_days` INT DEFAULT 30,
    `expires_at` TIMESTAMP NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_status (`backup_status`),
    INDEX idx_type (`backup_type`),
    INDEX idx_expires (`expires_at`),
    INDEX idx_created (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cp_config` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `config_key` VARCHAR(100) NOT NULL UNIQUE,
    `config_value` TEXT,
    `config_type` ENUM('string', 'integer', 'boolean', 'json', 'encrypted') DEFAULT 'string',
    `category` VARCHAR(50) DEFAULT 'general',
    `description` VARCHAR(255),
    `is_sensitive` BOOLEAN DEFAULT FALSE,
    `requires_restart` BOOLEAN DEFAULT FALSE,
    `updated_by` INT UNSIGNED,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_category (`category`),
    INDEX idx_key (`config_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cp_logs` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `log_level` ENUM('debug', 'info', 'warning', 'error', 'critical') NOT NULL,
    `log_category` VARCHAR(50) NOT NULL,
    `message` TEXT NOT NULL,
    `context` JSON,
    `user_id` INT UNSIGNED,
    `ip_address` VARCHAR(45),
    `request_uri` VARCHAR(500),
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_level (`log_level`),
    INDEX idx_category (`log_category`),
    INDEX idx_created (`created_at`),
    INDEX idx_user (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cp_registry` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `module_name` VARCHAR(100) NOT NULL UNIQUE,
    `module_slug` VARCHAR(100) NOT NULL UNIQUE,
    `version` VARCHAR(20),
    `is_enabled` BOOLEAN DEFAULT TRUE,
    `is_installed` BOOLEAN DEFAULT FALSE,
    `install_date` TIMESTAMP NULL,
    `last_updated` TIMESTAMP NULL,
    `dependencies` JSON,
    `config_schema` JSON,
    `database_version` INT DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_enabled (`is_enabled`),
    INDEX idx_installed (`is_installed`),
    INDEX idx_slug (`module_slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cp_maintenance` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `maintenance_type` VARCHAR(50) NOT NULL,
    `description` TEXT,
    `is_active` BOOLEAN DEFAULT FALSE,
    `scheduled_start` TIMESTAMP NULL,
    `scheduled_end` TIMESTAMP NULL,
    `actual_start` TIMESTAMP NULL,
    `actual_end` TIMESTAMP NULL,
    `notification_sent` BOOLEAN DEFAULT FALSE,
    `created_by` INT UNSIGNED,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_active (`is_active`),
    INDEX idx_scheduled (`scheduled_start`, `scheduled_end`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default system configs
INSERT INTO `cp_config` (`config_key`, `config_value`, `category`, `description`) VALUES
('system.timezone', 'Pacific/Auckland', 'system', 'Default system timezone'),
('backup.enabled', 'true', 'backup', 'Enable automatic backups'),
('backup.schedule', '0 2 * * *', 'backup', 'Cron schedule for backups'),
('maintenance.enabled', 'false', 'maintenance', 'Maintenance mode status')
ON DUPLICATE KEY UPDATE `config_key` = `config_key`;
