-- ═══════════════════════════════════════════════════════════════════════════
-- VEND SYNC MANAGER - DATABASE SETUP SCRIPT
-- ═══════════════════════════════════════════════════════════════════════════
-- Run this script to ensure all required tables and configuration exist
-- Database: jcepnzzkmj
-- ═══════════════════════════════════════════════════════════════════════════

-- Check if configuration table exists and has vend_access_token
SELECT
    'Configuration Check' AS check_name,
    CASE
        WHEN EXISTS(SELECT 1 FROM configuration WHERE config_label = 'vend_access_token')
        THEN '✓ Token configured'
        ELSE '✗ Token NOT configured - run INSERT below'
    END AS status;

-- ═══════════════════════════════════════════════════════════════════════════
-- STEP 1: Configure Vend API Token (REQUIRED)
-- ═══════════════════════════════════════════════════════════════════════════

-- Insert your Vend/Lightspeed API token
-- IMPORTANT: Replace 'YOUR_VEND_TOKEN_HERE' with your actual token
INSERT INTO configuration (config_label, config_value, config_description, created_at, updated_at)
VALUES (
    'vend_access_token',
    'YOUR_VEND_TOKEN_HERE',
    'Lightspeed/Vend API Access Token for sync operations',
    NOW(),
    NOW()
)
ON DUPLICATE KEY UPDATE
    config_value = VALUES(config_value),
    updated_at = NOW();

-- Verify token was inserted
SELECT config_label,
       CONCAT(LEFT(config_value, 10), '...') AS token_preview,
       config_description,
       created_at
FROM configuration
WHERE config_label = 'vend_access_token';

-- ═══════════════════════════════════════════════════════════════════════════
-- STEP 2: Verify Core Vend Tables Exist
-- ═══════════════════════════════════════════════════════════════════════════

SELECT
    TABLE_NAME,
    TABLE_ROWS,
    ROUND(((DATA_LENGTH + INDEX_LENGTH) / 1024 / 1024), 2) AS size_mb,
    CREATE_TIME
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = 'jcepnzzkmj'
  AND TABLE_NAME IN (
      'vend_products',
      'vend_sales',
      'vend_customers',
      'vend_inventory',
      'vend_consignments',
      'vend_consignment_line_items',
      'vend_outlets',
      'vend_categories',
      'vend_brands',
      'vend_suppliers',
      'vend_users',
      'vend_queue',
      'vend_api_logs',
      'vend_sync_cursors'
  )
ORDER BY TABLE_ROWS DESC;

-- ═══════════════════════════════════════════════════════════════════════════
-- STEP 3: Create vend_api_logs Table (if not exists)
-- ═══════════════════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS vend_api_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    correlation_id VARCHAR(64) NOT NULL,
    entity_type VARCHAR(50) NOT NULL,
    action VARCHAR(50) NOT NULL,
    status ENUM('success', 'error', 'warning', 'info') NOT NULL,
    message TEXT,
    context JSON,
    duration_ms INT UNSIGNED,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_correlation (correlation_id),
    INDEX idx_entity_action (entity_type, action),
    INDEX idx_status (status),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ═══════════════════════════════════════════════════════════════════════════
-- STEP 4: Create vend_sync_cursors Table (if not exists)
-- ═══════════════════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS vend_sync_cursors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    entity_type VARCHAR(50) NOT NULL UNIQUE,
    cursor_value VARCHAR(255) NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_entity (entity_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ═══════════════════════════════════════════════════════════════════════════
-- STEP 5: Verify vend_consignments State Column
-- ═══════════════════════════════════════════════════════════════════════════

-- Check if state column exists and has correct ENUM values
SELECT
    COLUMN_NAME,
    COLUMN_TYPE,
    IS_NULLABLE,
    COLUMN_DEFAULT
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = 'jcepnzzkmj'
  AND TABLE_NAME = 'vend_consignments'
  AND COLUMN_NAME = 'state';

-- Expected result: ENUM with values:
-- 'DRAFT','OPEN','PACKING','PACKAGED','SENT','RECEIVING','PARTIAL','RECEIVED','CLOSED','CANCELLED','ARCHIVED'

-- ═══════════════════════════════════════════════════════════════════════════
-- STEP 6: Check Queue Status
-- ═══════════════════════════════════════════════════════════════════════════

SELECT
    status,
    COUNT(*) as count,
    entity_type,
    COUNT(*) as entity_count
FROM vend_queue
GROUP BY status, entity_type
ORDER BY status, entity_count DESC;

-- Overall queue summary
SELECT
    CASE status
        WHEN 0 THEN 'Pending'
        WHEN 1 THEN 'Success'
        WHEN 2 THEN 'Failed'
        ELSE 'Unknown'
    END AS status_name,
    COUNT(*) as count
FROM vend_queue
GROUP BY status;

-- ═══════════════════════════════════════════════════════════════════════════
-- STEP 7: Check Consignment States Distribution
-- ═══════════════════════════════════════════════════════════════════════════

SELECT
    state,
    COUNT(*) as count,
    ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM vend_consignments), 2) as percentage,
    MIN(created_at) as oldest,
    MAX(created_at) as newest
FROM vend_consignments
WHERE deleted_at IS NULL
GROUP BY state
ORDER BY count DESC;

-- ═══════════════════════════════════════════════════════════════════════════
-- STEP 8: Add System Config for Vend Sync (Optional)
-- ═══════════════════════════════════════════════════════════════════════════

-- Create system_config table if it doesn't exist
CREATE TABLE IF NOT EXISTS system_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    config_group VARCHAR(50) NOT NULL,
    config_key VARCHAR(100) NOT NULL,
    config_value TEXT,
    config_type ENUM('string', 'int', 'bool', 'json') DEFAULT 'string',
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_group_key (config_group, config_key),
    INDEX idx_group (config_group)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default vend_sync configuration
INSERT INTO system_config (config_group, config_key, config_value, config_type, description)
VALUES
    ('vend_sync', 'batch_size', '100', 'int', 'Database batch insert size'),
    ('vend_sync', 'page_size', '200', 'int', 'API pagination size'),
    ('vend_sync', 'max_concurrent', '5', 'int', 'Max concurrent API requests'),
    ('vend_sync', 'enable_webhooks', '1', 'bool', 'Enable webhook processing'),
    ('vend_sync', 'queue_max_attempts', '5', 'int', 'Max queue retry attempts'),
    ('vend_sync', 'audit_enabled', '1', 'bool', 'Enable audit logging'),
    ('vend_sync', 'audit_retention_days', '90', 'int', 'Audit log retention period')
ON DUPLICATE KEY UPDATE
    updated_at = NOW();

-- Verify system config
SELECT * FROM system_config WHERE config_group = 'vend_sync';

-- ═══════════════════════════════════════════════════════════════════════════
-- STEP 9: Performance Indexes (if not exist)
-- ═══════════════════════════════════════════════════════════════════════════

-- vend_queue indexes
CREATE INDEX IF NOT EXISTS idx_queue_status_entity ON vend_queue(status, entity_type);
CREATE INDEX IF NOT EXISTS idx_queue_created ON vend_queue(created_at);
CREATE INDEX IF NOT EXISTS idx_queue_locked ON vend_queue(locked_at, locked_by);

-- vend_consignments indexes
CREATE INDEX IF NOT EXISTS idx_consignment_state ON vend_consignments(state);
CREATE INDEX IF NOT EXISTS idx_consignment_created ON vend_consignments(created_at);
CREATE INDEX IF NOT EXISTS idx_consignment_outlet_from ON vend_consignments(outlet_from);
CREATE INDEX IF NOT EXISTS idx_consignment_outlet_to ON vend_consignments(outlet_to);

-- vend_products indexes
CREATE INDEX IF NOT EXISTS idx_product_active ON vend_products(active);
CREATE INDEX IF NOT EXISTS idx_product_brand ON vend_products(brand_id);
CREATE INDEX IF NOT EXISTS idx_product_supplier ON vend_products(supplier_id);

-- vend_inventory indexes
CREATE INDEX IF NOT EXISTS idx_inventory_outlet ON vend_inventory(outlet_id);
CREATE INDEX IF NOT EXISTS idx_inventory_product ON vend_inventory(product_id);
CREATE INDEX IF NOT EXISTS idx_inventory_level ON vend_inventory(inventory_level);

-- ═══════════════════════════════════════════════════════════════════════════
-- STEP 10: Health Check Summary
-- ═══════════════════════════════════════════════════════════════════════════

SELECT '════════════════════════════════════════' AS separator;
SELECT 'VEND SYNC MANAGER - HEALTH CHECK SUMMARY' AS title;
SELECT '════════════════════════════════════════' AS separator;

-- Token status
SELECT
    'API Token' AS component,
    CASE
        WHEN EXISTS(SELECT 1 FROM configuration WHERE config_label = 'vend_access_token' AND config_value != '')
        THEN '✓ CONFIGURED'
        ELSE '✗ NOT CONFIGURED'
    END AS status;

-- Table counts
SELECT
    'Vend Tables' AS component,
    CONCAT('✓ ', COUNT(*), ' tables exist') AS status
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = 'jcepnzzkmj'
  AND TABLE_NAME LIKE 'vend_%';

-- Queue status
SELECT
    'Queue System' AS component,
    CONCAT('✓ ',
        (SELECT COUNT(*) FROM vend_queue WHERE status = 1), ' success, ',
        (SELECT COUNT(*) FROM vend_queue WHERE status = 0), ' pending, ',
        (SELECT COUNT(*) FROM vend_queue WHERE status = 2), ' failed'
    ) AS status;

-- Consignment states
SELECT
    'Consignments' AS component,
    CONCAT('✓ ', COUNT(*), ' total, ',
        SUM(CASE WHEN state IN ('DRAFT','OPEN') THEN 1 ELSE 0 END), ' active'
    ) AS status
FROM vend_consignments
WHERE deleted_at IS NULL;

-- Audit logging
SELECT
    'Audit Logs' AS component,
    CASE
        WHEN EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = 'jcepnzzkmj' AND TABLE_NAME = 'vend_api_logs')
        THEN CONCAT('✓ Table exists with ', (SELECT COUNT(*) FROM vend_api_logs), ' entries')
        ELSE '✗ Table missing'
    END AS status;

-- Sync cursors
SELECT
    'Sync Cursors' AS component,
    CASE
        WHEN EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = 'jcepnzzkmj' AND TABLE_NAME = 'vend_sync_cursors')
        THEN CONCAT('✓ Table exists with ', (SELECT COUNT(*) FROM vend_sync_cursors), ' cursors')
        ELSE '✗ Table missing'
    END AS status;

SELECT '════════════════════════════════════════' AS separator;
SELECT 'Setup complete! Run test:connection to verify API access' AS next_step;
SELECT '════════════════════════════════════════' AS separator;

-- ═══════════════════════════════════════════════════════════════════════════
-- CLEANUP QUERIES (Run periodically)
-- ═══════════════════════════════════════════════════════════════════════════

-- Clean up old audit logs (older than 90 days)
-- DELETE FROM vend_api_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY);

-- Clean up old queue entries (successful, older than 30 days)
-- DELETE FROM vend_queue WHERE status = 1 AND updated_at < DATE_SUB(NOW(), INTERVAL 30 DAY);

-- Archive old consignments (closed > 1 year ago)
-- UPDATE vend_consignments SET state = 'ARCHIVED'
-- WHERE state = 'CLOSED'
--   AND updated_at < DATE_SUB(NOW(), INTERVAL 1 YEAR)
--   AND state != 'ARCHIVED';

-- ═══════════════════════════════════════════════════════════════════════════
-- END OF SETUP SCRIPT
-- ═══════════════════════════════════════════════════════════════════════════
