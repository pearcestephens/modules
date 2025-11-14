-- ============================================================================
-- SMART CRON INTEGRATION – CONSIGNMENTS MODULE
--
-- Purpose:
--   Consolidated SQL required for the cron-driven sync/queue/webhook system.
--   Safe to run multiple times (IF NOT EXISTS used where applicable).
--
-- Includes:
--   - Dead Letter Queue (DLQ) for permanently failed jobs
--   - Sync cursors for polling pagination state
--   - Shadow table for Lightspeed consignments (reconciliation)
--   - Queue job hardening (heartbeat + backoff schedule)
--   - Webhook audit trail + helper views
--
-- Notes:
--   - Requires MySQL 8.0+ for IF NOT EXISTS on ADD COLUMN.
--   - Run with: mysql -u <user> -p <db> < SMART_CRON_INTEGRATION.sql
-- ============================================================================

-- ============================================================================
-- Dead Letter Queue (DLQ)
-- ============================================================================
CREATE TABLE IF NOT EXISTS queue_jobs_dlq (
	id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
	original_job_id BIGINT UNSIGNED NOT NULL COMMENT 'Original job ID from queue_jobs',
	job_type VARCHAR(100) NOT NULL,
	payload JSON NOT NULL,
	priority TINYINT UNSIGNED DEFAULT 5,
	final_error TEXT COMMENT 'Final error message before moving to DLQ',
	attempts INT UNSIGNED NOT NULL COMMENT 'Total attempts before failure',
	moved_to_dlq_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

	INDEX idx_job_type (job_type),
	INDEX idx_moved_at (moved_to_dlq_at),
	INDEX idx_original_job (original_job_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Dead Letter Queue for permanently failed jobs';

-- ============================================================================
-- Sync Cursors (ID-based pagination tracking)
-- ============================================================================
CREATE TABLE IF NOT EXISTS sync_cursors (
	id INT AUTO_INCREMENT PRIMARY KEY,
	cursor_type VARCHAR(50) NOT NULL UNIQUE COMMENT 'e.g., consignments, products, sales',
	last_processed_id VARCHAR(255) DEFAULT '0' COMMENT 'Last seen ID from external system',
	last_processed_at TIMESTAMP NULL COMMENT 'When last poll completed',
	created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
	updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

	INDEX idx_cursor_type (cursor_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Cursor-based pagination state for external API polling';

-- Default cursor for consignments
INSERT INTO sync_cursors (cursor_type, last_processed_id)
VALUES ('consignments', '0')
ON DUPLICATE KEY UPDATE cursor_type = cursor_type;

-- ============================================================================
-- Shadow table for Lightspeed consignments (source of truth for reconciliation)
-- ============================================================================
CREATE TABLE IF NOT EXISTS queue_consignments (
	id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
	consignment_id VARCHAR(100) NOT NULL UNIQUE COMMENT 'Lightspeed consignment ID',
	status VARCHAR(50) NOT NULL COMMENT 'Lightspeed status',
	outlet_id INT UNSIGNED COMMENT 'Outlet ID',
	raw_json JSON NOT NULL COMMENT 'Full Lightspeed response',
	first_seen_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'First time polled',
	last_synced_at TIMESTAMP NULL COMMENT 'Last time updated from Lightspeed',

	INDEX idx_consignment_id (consignment_id),
	INDEX idx_status (status),
	INDEX idx_outlet (outlet_id),
	INDEX idx_synced_at (last_synced_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Shadow table for Lightspeed consignments (reconciliation source)';

-- ============================================================================
-- Queue hardening (requires existing queue_jobs table)
-- ============================================================================
ALTER TABLE queue_jobs
ADD COLUMN IF NOT EXISTS heartbeat_at TIMESTAMP NULL COMMENT 'Last worker heartbeat'
AFTER started_at;

ALTER TABLE queue_jobs
ADD INDEX IF NOT EXISTS idx_heartbeat (heartbeat_at);

ALTER TABLE queue_jobs
ADD COLUMN IF NOT EXISTS next_attempt_at TIMESTAMP NULL COMMENT 'Earliest time for next retry'
AFTER heartbeat_at;

ALTER TABLE queue_jobs
ADD INDEX IF NOT EXISTS idx_next_attempt (next_attempt_at);

-- ============================================================================
-- Stuck Job Monitor View (helper for debugging)
-- ============================================================================
CREATE OR REPLACE VIEW v_stuck_jobs AS
SELECT
	id,
	job_type,
	status,
	worker_id,
	attempts,
	max_attempts,
	started_at,
	heartbeat_at,
	TIMESTAMPDIFF(MINUTE, heartbeat_at, NOW()) AS minutes_since_heartbeat,
	CASE
		WHEN status = 'processing' AND heartbeat_at < DATE_SUB(NOW(), INTERVAL 5 MINUTE) THEN 'STUCK'
		WHEN status = 'processing' AND heartbeat_at < DATE_SUB(NOW(), INTERVAL 2 MINUTE) THEN 'WARNING'
		ELSE 'OK'
	END AS health_status
FROM queue_jobs
WHERE status = 'processing'
ORDER BY started_at ASC;

-- ============================================================================
-- DLQ Summary View (helper for monitoring)
-- ============================================================================
CREATE OR REPLACE VIEW v_dlq_summary AS
SELECT
	job_type,
	COUNT(*) AS total_failed,
	MAX(moved_to_dlq_at) AS last_failure,
	AVG(attempts) AS avg_attempts
FROM queue_jobs_dlq
GROUP BY job_type
ORDER BY total_failed DESC;

-- ============================================================================
-- Webhook audit trail + helper views
-- ============================================================================
CREATE TABLE IF NOT EXISTS webhook_events (
	id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
	event_id VARCHAR(255) NOT NULL UNIQUE COMMENT 'External event ID (replay protection)',
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
COMMENT='Audit trail for all incoming webhook events';

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
-- End of SMART CRON INTEGRATION
-- ============================================================================

