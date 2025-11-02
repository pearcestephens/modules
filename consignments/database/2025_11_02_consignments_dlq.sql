CREATE TABLE IF NOT EXISTS consignments_dlq (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  request_id VARCHAR(64) NOT NULL,
  endpoint   VARCHAR(255) NOT NULL,
  payload_json LONGTEXT NULL,
  error_code VARCHAR(64) NULL,
  error_message TEXT NULL,
  retry_count INT UNSIGNED NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_request (request_id),
  INDEX idx_endpoint (endpoint),
  INDEX idx_created (created_at)
);
