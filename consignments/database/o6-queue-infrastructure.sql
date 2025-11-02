-- O6: Queue Worker Infrastructure
-- Creates: queue_jobs_dlq, sync_cursors, queue_consignments, heartbeat monitoring

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

-- Insert default cursor for consignments
INSERT INTO sync_cursors (cursor_type, last_processed_id)
VALUES ('consignments', '0')
ON DUPLICATE KEY UPDATE cursor_type = cursor_type;

-- ============================================================================
-- Queue Consignments Shadow Table
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
-- Add heartbeat column to queue_jobs (if not exists)
-- ============================================================================
ALTER TABLE queue_jobs
ADD COLUMN IF NOT EXISTS heartbeat_at TIMESTAMP NULL COMMENT 'Last worker heartbeat'
AFTER started_at;

ALTER TABLE queue_jobs
ADD INDEX IF NOT EXISTS idx_heartbeat (heartbeat_at);

-- ============================================================================
-- Add next_attempt_at for backoff scheduling
-- ============================================================================
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
-- Verification Queries
-- ============================================================================
-- Check tables created:
-- SELECT TABLE_NAME FROM information_schema.TABLES
-- WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('queue_jobs_dlq', 'sync_cursors', 'queue_consignments');

-- Check heartbeat column:
-- SHOW COLUMNS FROM queue_jobs LIKE 'heartbeat_at';

-- Check cursors initialized:
-- SELECT * FROM sync_cursors;
