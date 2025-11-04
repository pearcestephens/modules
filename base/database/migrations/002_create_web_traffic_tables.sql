-- ============================================================================
-- CIS Base Module - Web Traffic & Monitoring Tables
-- Migration: 002_create_web_traffic_tables.sql
--
-- Creates tables for:
-- - Section 11: Traffic monitoring, performance analytics, error tracking
-- - Section 12: API testing history
-- ============================================================================

-- Drop tables if they exist (for clean re-run)
DROP TABLE IF EXISTS web_traffic_requests;
DROP TABLE IF EXISTS web_traffic_errors;
DROP TABLE IF EXISTS web_traffic_redirects;
DROP TABLE IF EXISTS web_health_checks;
DROP TABLE IF EXISTS api_test_history;

-- ============================================================================
-- Table: web_traffic_requests
-- Purpose: Log all HTTP requests for traffic monitoring and analytics
-- ============================================================================
CREATE TABLE web_traffic_requests (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    request_id VARCHAR(36) NOT NULL COMMENT 'UUID correlation ID',
    timestamp DATETIME(3) NOT NULL,
    method ENUM('GET','POST','PUT','DELETE','PATCH','OPTIONS','HEAD') NOT NULL,
    endpoint VARCHAR(500) NOT NULL,
    query_string TEXT,
    status_code SMALLINT UNSIGNED NOT NULL,
    response_time_ms INT UNSIGNED NOT NULL COMMENT 'Response time in milliseconds',
    memory_mb DECIMAL(10,2) COMMENT 'Peak memory usage in MB',
    ip_address VARCHAR(45) NOT NULL COMMENT 'IPv4 or IPv6',
    user_agent TEXT,
    referer TEXT,
    user_id INT UNSIGNED COMMENT 'Authenticated user ID',
    is_bot TINYINT(1) DEFAULT 0,
    bot_type VARCHAR(50) COMMENT 'googlebot, bingbot, etc',
    country_code CHAR(2) COMMENT 'ISO 3166-1 alpha-2',
    country_name VARCHAR(100),
    city VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_timestamp (timestamp),
    INDEX idx_endpoint (endpoint(255)),
    INDEX idx_status_code (status_code),
    INDEX idx_response_time (response_time_ms),
    INDEX idx_ip_address (ip_address),
    INDEX idx_user_id (user_id),
    INDEX idx_is_bot (is_bot),
    INDEX idx_country (country_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='HTTP request logs for traffic monitoring';

-- ============================================================================
-- Table: web_traffic_errors
-- Purpose: Track HTTP errors (404, 500, etc) with stack traces
-- ============================================================================
CREATE TABLE web_traffic_errors (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    request_id VARCHAR(36) NOT NULL COMMENT 'Links to web_traffic_requests',
    timestamp DATETIME(3) NOT NULL,
    error_code SMALLINT UNSIGNED NOT NULL COMMENT 'HTTP status code',
    error_type VARCHAR(100) NOT NULL COMMENT 'Exception class or error type',
    error_message TEXT NOT NULL,
    error_file VARCHAR(500),
    error_line INT UNSIGNED,
    stack_trace TEXT COMMENT 'Full stack trace (PII redacted)',
    endpoint VARCHAR(500) NOT NULL,
    method VARCHAR(10) NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    user_id INT UNSIGNED,
    is_resolved TINYINT(1) DEFAULT 0,
    resolved_at DATETIME,
    resolved_by INT UNSIGNED,
    resolution_notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_timestamp (timestamp),
    INDEX idx_error_code (error_code),
    INDEX idx_error_type (error_type),
    INDEX idx_endpoint (endpoint(255)),
    INDEX idx_is_resolved (is_resolved),
    INDEX idx_request_id (request_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='HTTP error tracking with stack traces';

-- ============================================================================
-- Table: web_traffic_redirects
-- Purpose: Manage URL redirects (301/302) for SEO and UX
-- ============================================================================
CREATE TABLE web_traffic_redirects (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    from_path VARCHAR(500) NOT NULL COMMENT 'Source URL path',
    to_path VARCHAR(500) NOT NULL COMMENT 'Target URL path',
    status_code SMALLINT UNSIGNED NOT NULL DEFAULT 301 COMMENT '301 permanent, 302 temporary',
    hit_count INT UNSIGNED DEFAULT 0 COMMENT 'Times redirect was triggered',
    last_hit_at DATETIME,
    is_active TINYINT(1) DEFAULT 1,
    notes TEXT COMMENT 'Reason for redirect',
    created_by INT UNSIGNED,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uk_from_path (from_path(255)),
    INDEX idx_is_active (is_active),
    INDEX idx_hit_count (hit_count)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='URL redirect management';

CREATE TABLE web_health_checks (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    check_type VARCHAR(50) NOT NULL COMMENT 'ssl, database, php_fpm, disk, vend_api, queue',
    status ENUM('pass','fail','warning') NOT NULL,
    response_time_ms INT UNSIGNED COMMENT 'Time to complete check',
    details JSON COMMENT 'Check-specific details', -- Updated to match the new format
    error_message TEXT,
    checked_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_check_type (check_type),
    INDEX idx_checked_at (checked_at),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='System health check results';

-- ============================================================================
-- Table: api_test_history
-- Purpose: Store API test results for Section 12 testing tools
-- ============================================================================
CREATE TABLE api_test_history (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    test_type VARCHAR(50) NOT NULL COMMENT 'webhook, vend_api, sync, queue, endpoint',
    test_name VARCHAR(100) NOT NULL COMMENT 'Descriptive test name',
    request_method VARCHAR(10) COMMENT 'GET, POST, PUT, DELETE',
    request_url TEXT,
    request_headers JSON,
    request_body TEXT,
    response_status SMALLINT UNSIGNED,
    response_time_ms INT UNSIGNED,
    response_headers JSON,
    response_body TEXT,
    success TINYINT(1) NOT NULL COMMENT '1 = pass, 0 = fail',
    error_message TEXT,
    user_id INT UNSIGNED,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_test_type (test_type),
    INDEX idx_test_name (test_name),
    INDEX idx_created_at (created_at),
    INDEX idx_user_id (user_id),
    INDEX idx_success (success)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='API testing history for debugging';

-- ============================================================================
-- Sample Data / Initial Records
-- ============================================================================

-- Insert initial health check (system startup)
INSERT INTO web_health_checks (check_type, status, response_time_ms, details, checked_at)
VALUES ('database', 'pass', 5, '{"message": "Tables created successfully"}', NOW());

-- ============================================================================
-- Verification Queries
-- ============================================================================

-- Show all tables created
SHOW TABLES LIKE 'web_%';
SHOW TABLES LIKE 'api_%';

-- Verify table structures
DESCRIBE web_traffic_requests;
DESCRIBE web_traffic_errors;
DESCRIBE web_traffic_redirects;
DESCRIBE web_health_checks;
DESCRIBE api_test_history;

-- Verify indexes
SHOW INDEX FROM web_traffic_requests;
SHOW INDEX FROM web_traffic_errors;

-- Test sample data
SELECT * FROM web_health_checks ORDER BY id DESC LIMIT 5;

-- ============================================================================
-- DONE - Tables Ready for Section 11 & 12
-- ============================================================================
