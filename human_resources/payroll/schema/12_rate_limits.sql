-- Payroll Rate Limit Telemetry Schema
-- Captures 429 responses and retry metadata for external integrations

CREATE TABLE IF NOT EXISTS payroll_rate_limits (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    service ENUM('xero','deputy') NOT NULL,
    endpoint VARCHAR(120) NOT NULL,
    http_status SMALLINT NOT NULL,
    retry_after_sec INT DEFAULT NULL,
    occurred_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    request_id VARCHAR(64) DEFAULT NULL,
    payload_hash CHAR(64) DEFAULT NULL,
    KEY idx_service_time (service, occurred_at),
    KEY idx_endpoint (endpoint)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE OR REPLACE VIEW v_rate_limit_7d AS
SELECT
    service,
    endpoint,
    DATE(occurred_at) AS summary_date,
    COUNT(*) AS hit_count,
    AVG(IFNULL(retry_after_sec, 0)) AS avg_retry_seconds
FROM payroll_rate_limits
WHERE occurred_at >= (CURRENT_DATE - INTERVAL 7 DAY)
GROUP BY service, endpoint, DATE(occurred_at);
