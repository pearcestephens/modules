-- O7: Webhook Infrastructure
-- Creates webhook_events table for audit trail and idempotency

-- ============================================================================
-- Webhook Events Table (Audit Trail + Idempotency)
-- ============================================================================
CREATE TABLE IF NOT EXISTS webhook_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_id VARCHAR(255) NOT NULL UNIQUE COMMENT 'Lightspeed event ID (for replay protection)',
    event_type VARCHAR(100) NOT NULL COMMENT 'e.g., consignment.created, transfer.updated',
    payload JSON NOT NULL COMMENT 'Full webhook payload',
    request_id VARCHAR(100) NOT NULL COMMENT 'Correlation ID for tracing',
    source_ip VARCHAR(45) COMMENT 'Client IP address',
    status ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending',
    received_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    processed_at TIMESTAMP NULL,
    error_message TEXT COMMENT 'Error if processing failed',

    INDEX idx_event_id (event_id),
    INDEX idx_event_type (event_type),
    INDEX idx_status (status),
    INDEX idx_received_at (received_at),
    INDEX idx_request_id (request_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Audit trail for all incoming Lightspeed webhook events';

-- ============================================================================
-- Webhook Stats View (Monitoring Helper)
-- ============================================================================
CREATE OR REPLACE VIEW v_webhook_stats AS
SELECT
    event_type,
    status,
    COUNT(*) AS event_count,
    MAX(received_at) AS last_received,
    AVG(TIMESTAMPDIFF(SECOND, received_at, processed_at)) AS avg_processing_seconds
FROM webhook_events
WHERE received_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
GROUP BY event_type, status
ORDER BY event_count DESC;

-- ============================================================================
-- Recent Webhook Failures View
-- ============================================================================
CREATE OR REPLACE VIEW v_webhook_failures AS
SELECT
    id,
    event_id,
    event_type,
    error_message,
    received_at,
    source_ip
FROM webhook_events
WHERE status = 'failed'
ORDER BY received_at DESC
LIMIT 100;

-- ============================================================================
-- Cleanup Old Webhook Events (Run monthly via cron)
-- ============================================================================
-- DELETE FROM webhook_events
-- WHERE received_at < DATE_SUB(NOW(), INTERVAL 90 DAY)
-- AND status IN ('completed', 'failed');
