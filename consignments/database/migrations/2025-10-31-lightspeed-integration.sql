-- ============================================================================
-- Lightspeed Integration & Queue System Tables
-- ============================================================================
-- Version: 2.0.0
-- Date: 2025-10-31
-- Description: Database tables for Lightspeed sync and job queue system
-- ============================================================================

-- Drop existing tables if they exist (for clean re-runs)
DROP TABLE IF EXISTS queue_jobs;
DROP TABLE IF EXISTS lightspeed_sync_log;
DROP TABLE IF EXISTS lightspeed_mappings;
DROP TABLE IF EXISTS lightspeed_webhooks;

-- ============================================================================
-- QUEUE SYSTEM
-- ============================================================================

CREATE TABLE queue_jobs (
    id VARCHAR(64) PRIMARY KEY,
    job_type VARCHAR(64) NOT NULL,
    payload JSON NOT NULL,
    status ENUM('PENDING', 'PROCESSING', 'COMPLETED', 'FAILED', 'CANCELLED') NOT NULL DEFAULT 'PENDING',
    priority INT NOT NULL DEFAULT 50,

    -- Retry logic
    attempts INT NOT NULL DEFAULT 0,
    max_attempts INT NOT NULL DEFAULT 3,

    -- Dependencies
    depends_on VARCHAR(64) NULL,

    -- Timing
    available_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL,
    started_at DATETIME NULL,
    completed_at DATETIME NULL,
    failed_at DATETIME NULL,

    -- Worker tracking
    worker_id VARCHAR(128) NULL,

    -- Results
    output JSON NULL,
    error_message TEXT NULL,

    INDEX idx_status (status),
    INDEX idx_priority (priority DESC),
    INDEX idx_available (available_at),
    INDEX idx_job_type (job_type),
    INDEX idx_created (created_at DESC),
    INDEX idx_depends (depends_on),
    INDEX idx_processing (status, available_at, priority DESC),

    CONSTRAINT fk_queue_depends FOREIGN KEY (depends_on)
        REFERENCES queue_jobs(id) ON DELETE SET NULL

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- LIGHTSPEED SYNC LOG
-- ============================================================================

CREATE TABLE lightspeed_sync_log (
    id VARCHAR(64) PRIMARY KEY,

    -- What's being synced
    entity_id VARCHAR(64) NOT NULL COMMENT 'PO ID, Product ID, etc.',
    entity_type VARCHAR(32) NULL COMMENT 'purchase_order, product, inventory',
    operation VARCHAR(64) NOT NULL COMMENT 'CREATE_CONSIGNMENT, UPLOAD_PRODUCTS, etc.',

    -- Status
    status ENUM('PENDING', 'IN_PROGRESS', 'COMPLETED', 'FAILED') NOT NULL DEFAULT 'PENDING',

    -- Details
    data JSON NULL COMMENT 'Operation details, results, errors',
    error_message TEXT NULL,

    -- Timing
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    completed_at DATETIME NULL,
    failed_at DATETIME NULL,

    -- Links
    lightspeed_consignment_id VARCHAR(64) NULL,
    queue_job_id VARCHAR(64) NULL,

    INDEX idx_entity (entity_id),
    INDEX idx_operation (operation),
    INDEX idx_status (status),
    INDEX idx_created (created_at DESC),
    INDEX idx_consignment (lightspeed_consignment_id),
    INDEX idx_job (queue_job_id)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- LIGHTSPEED MAPPINGS
-- ============================================================================

CREATE TABLE lightspeed_mappings (
    id INT AUTO_INCREMENT PRIMARY KEY,

    -- Local entity
    local_id VARCHAR(64) NOT NULL,
    local_type VARCHAR(32) NOT NULL COMMENT 'purchase_order, purchase_order_line, product',

    -- Lightspeed entity
    lightspeed_id VARCHAR(64) NOT NULL,
    lightspeed_type VARCHAR(32) NOT NULL COMMENT 'consignment, consignment_product, product',

    -- Metadata
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    synced_at DATETIME NULL,

    UNIQUE KEY uk_local (local_id, local_type),
    UNIQUE KEY uk_lightspeed (lightspeed_id, lightspeed_type),
    INDEX idx_local (local_id),
    INDEX idx_lightspeed (lightspeed_id)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- LIGHTSPEED WEBHOOKS
-- ============================================================================

CREATE TABLE lightspeed_webhooks (
    id VARCHAR(64) PRIMARY KEY,

    -- Webhook event
    event_type VARCHAR(64) NOT NULL,
    event_id VARCHAR(64) NULL,

    -- Payload
    payload JSON NOT NULL,
    headers JSON NULL,

    -- Processing
    processed BOOLEAN NOT NULL DEFAULT FALSE,
    processed_at DATETIME NULL,

    -- Verification
    signature VARCHAR(128) NULL,
    verified BOOLEAN NOT NULL DEFAULT FALSE,

    -- Metadata
    received_at DATETIME NOT NULL,
    ip_address VARCHAR(45) NULL,

    INDEX idx_event (event_type),
    INDEX idx_processed (processed, received_at),
    INDEX idx_received (received_at DESC)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- ALTER EXISTING TABLES
-- ============================================================================

-- Add Lightspeed columns to vend_consignments (Purchase Orders)
ALTER TABLE vend_consignments
ADD COLUMN IF NOT EXISTS lightspeed_consignment_id VARCHAR(64) NULL AFTER supplier_id,
ADD COLUMN IF NOT EXISTS lightspeed_last_sync DATETIME NULL AFTER lightspeed_consignment_id,
ADD COLUMN IF NOT EXISTS lightspeed_sync_status ENUM('PENDING', 'SYNCED', 'FAILED') NULL AFTER lightspeed_last_sync,
ADD INDEX idx_lightspeed_id (lightspeed_consignment_id),
ADD INDEX idx_sync_status (lightspeed_sync_status);

-- Add Lightspeed columns to vend_products
ALTER TABLE vend_products
ADD COLUMN IF NOT EXISTS lightspeed_product_id VARCHAR(64) NULL AFTER id,
ADD COLUMN IF NOT EXISTS lightspeed_last_sync DATETIME NULL AFTER lightspeed_product_id,
ADD INDEX idx_lightspeed_product (lightspeed_product_id);

-- Add Lightspeed columns to vend_suppliers
ALTER TABLE vend_suppliers
ADD COLUMN IF NOT EXISTS lightspeed_supplier_id VARCHAR(64) NULL AFTER id,
ADD COLUMN IF NOT EXISTS lightspeed_last_sync DATETIME NULL AFTER lightspeed_supplier_id,
ADD INDEX idx_lightspeed_supplier (lightspeed_supplier_id);

-- Add Lightspeed columns to vend_outlets
ALTER TABLE vend_outlets
ADD COLUMN IF NOT EXISTS lightspeed_outlet_id VARCHAR(64) NULL AFTER id,
ADD COLUMN IF NOT EXISTS lightspeed_last_sync DATETIME NULL AFTER lightspeed_outlet_id,
ADD INDEX idx_lightspeed_outlet (lightspeed_outlet_id);

-- ============================================================================
-- STORED PROCEDURES
-- ============================================================================

DELIMITER $$

-- Get next available queue job
CREATE PROCEDURE IF NOT EXISTS sp_get_next_queue_job()
BEGIN
    SELECT j.*
    FROM queue_jobs j
    LEFT JOIN queue_jobs d ON j.depends_on = d.id
    WHERE j.status = 'PENDING'
    AND j.available_at <= NOW()
    AND (j.depends_on IS NULL OR d.status = 'COMPLETED')
    ORDER BY j.priority DESC, j.created_at ASC
    LIMIT 1
    FOR UPDATE SKIP LOCKED;
END$$

-- Prune old queue jobs
CREATE PROCEDURE IF NOT EXISTS sp_prune_queue_jobs(IN days INT)
BEGIN
    DELETE FROM queue_jobs
    WHERE status IN ('COMPLETED', 'FAILED', 'CANCELLED')
    AND created_at < DATE_SUB(NOW(), INTERVAL days DAY);

    SELECT ROW_COUNT() as deleted_count;
END$$

-- Prune old sync logs
CREATE PROCEDURE IF NOT EXISTS sp_prune_sync_logs(IN days INT)
BEGIN
    DELETE FROM lightspeed_sync_log
    WHERE status IN ('COMPLETED', 'FAILED')
    AND created_at < DATE_SUB(NOW(), INTERVAL days DAY);

    SELECT ROW_COUNT() as deleted_count;
END$$

-- Get sync statistics
CREATE PROCEDURE IF NOT EXISTS sp_get_sync_stats()
BEGIN
    SELECT
        COUNT(*) as total_syncs,
        SUM(CASE WHEN status = 'COMPLETED' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN status = 'FAILED' THEN 1 ELSE 0 END) as failed,
        SUM(CASE WHEN status = 'IN_PROGRESS' THEN 1 ELSE 0 END) as in_progress,
        SUM(CASE WHEN status = 'PENDING' THEN 1 ELSE 0 END) as pending,
        AVG(TIMESTAMPDIFF(SECOND, created_at, completed_at)) as avg_duration_seconds
    FROM lightspeed_sync_log
    WHERE created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR);
END$$

DELIMITER ;

-- ============================================================================
-- INITIAL DATA
-- ============================================================================

-- Insert system config for Lightspeed sync
INSERT INTO system_config (config_key, config_value, description, updated_by, updated_at)
VALUES (
    'lightspeed_sync_config',
    JSON_OBJECT(
        'auto_sync_on_approval', true,
        'auto_send_on_upload', false,
        'batch_size', 50,
        'retry_failed_after_minutes', 30,
        'delete_old_logs_after_days', 30,
        'enabled', true
    ),
    'Lightspeed synchronization configuration',
    'SYSTEM',
    NOW()
)
ON DUPLICATE KEY UPDATE
    config_value = VALUES(config_value),
    updated_at = NOW();

-- ============================================================================
-- VERIFICATION QUERIES
-- ============================================================================

-- Verify tables created
SELECT
    'queue_jobs' as table_name, COUNT(*) as row_count
FROM queue_jobs
UNION ALL
SELECT
    'lightspeed_sync_log' as table_name, COUNT(*) as row_count
FROM lightspeed_sync_log
UNION ALL
SELECT
    'lightspeed_mappings' as table_name, COUNT(*) as row_count
FROM lightspeed_mappings
UNION ALL
SELECT
    'lightspeed_webhooks' as table_name, COUNT(*) as row_count
FROM lightspeed_webhooks;

-- Verify columns added
SELECT
    TABLE_NAME,
    COLUMN_NAME,
    COLUMN_TYPE
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
AND TABLE_NAME IN ('vend_consignments', 'vend_products', 'vend_suppliers', 'vend_outlets')
AND COLUMN_NAME LIKE 'lightspeed%'
ORDER BY TABLE_NAME, ORDINAL_POSITION;

-- Show stored procedures
SHOW PROCEDURE STATUS WHERE Db = DATABASE() AND Name LIKE 'sp_%';

-- ============================================================================
-- COMPLETE
-- ============================================================================

SELECT '✓ Lightspeed Integration tables created successfully!' as status;
