-- ============================================================================
-- O9: Receiving & Evidence - Database Migration
-- ============================================================================
-- Purpose: Store receiving evidence (photos, signatures, damage notes)
-- Security: File paths validated before storage, no user input in paths
-- ============================================================================

-- Main evidence table
CREATE TABLE IF NOT EXISTS receiving_evidence (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    transfer_id BIGINT UNSIGNED NOT NULL,
    item_id BIGINT UNSIGNED NULL,
    evidence_type ENUM('photo', 'signature', 'note') NOT NULL,
    file_path VARCHAR(500) NULL,
    note TEXT NULL,
    uploaded_by INT UNSIGNED NULL,
    uploaded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    -- Foreign keys
    FOREIGN KEY (transfer_id) REFERENCES stock_transfers(id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES stock_transfer_items(id) ON DELETE CASCADE,

    -- Indexes
    INDEX idx_transfer_id (transfer_id),
    INDEX idx_item_id (item_id),
    INDEX idx_evidence_type (evidence_type),
    INDEX idx_uploaded_at (uploaded_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Stores receiving evidence: photos, signatures, damage notes';

-- Add column to track last received time per item
ALTER TABLE stock_transfer_items
ADD COLUMN IF NOT EXISTS last_received_at TIMESTAMP NULL
COMMENT='Last time this item received quantity';

-- View: Evidence summary per transfer
CREATE OR REPLACE VIEW v_receiving_evidence_summary AS
SELECT
    transfer_id,
    COUNT(*) as total_evidence,
    SUM(CASE WHEN evidence_type = 'photo' THEN 1 ELSE 0 END) as photo_count,
    SUM(CASE WHEN evidence_type = 'signature' THEN 1 ELSE 0 END) as signature_count,
    SUM(CASE WHEN evidence_type = 'note' THEN 1 ELSE 0 END) as note_count,
    MAX(uploaded_at) as latest_upload
FROM receiving_evidence
GROUP BY transfer_id;

-- View: Incomplete evidence (transfers missing required evidence)
-- Assumes signature required for all receives
CREATE OR REPLACE VIEW v_incomplete_evidence AS
SELECT
    t.id as transfer_id,
    t.type,
    t.status,
    COALESCE(e.signature_count, 0) as has_signature,
    CASE
        WHEN t.status IN ('RECEIVED', 'PARTIALLY_RECEIVED')
             AND COALESCE(e.signature_count, 0) = 0
        THEN 'MISSING_SIGNATURE'
        ELSE 'COMPLETE'
    END as evidence_status
FROM stock_transfers t
LEFT JOIN v_receiving_evidence_summary e ON t.id = e.transfer_id
WHERE t.status IN ('RECEIVED', 'PARTIALLY_RECEIVED');

-- View: Items with damage notes
CREATE OR REPLACE VIEW v_damaged_items AS
SELECT
    e.transfer_id,
    e.item_id,
    i.product_id,
    p.name as product_name,
    e.note as damage_description,
    e.uploaded_at as reported_at,
    t.outlet_id
FROM receiving_evidence e
INNER JOIN stock_transfer_items i ON e.item_id = i.id
INNER JOIN stock_transfers t ON e.transfer_id = t.id
LEFT JOIN vend_products p ON i.product_id = p.id
WHERE e.evidence_type = 'note'
ORDER BY e.uploaded_at DESC;

-- Sample queries for testing
-- ============================================================================

-- Insert test data (for development only, remove in production)
/*
INSERT INTO receiving_evidence (transfer_id, item_id, evidence_type, file_path, note) VALUES
(1, 1, 'photo', '/uploads/receiving/1/item1_damage.jpg', NULL),
(1, 1, 'note', NULL, 'Package arrived with water damage on corner'),
(1, NULL, 'signature', '/uploads/receiving/1/signature_123.png', NULL);
*/

-- Query: Get all evidence for transfer
/*
SELECT * FROM receiving_evidence WHERE transfer_id = 1 ORDER BY uploaded_at;
*/

-- Query: Check evidence completeness
/*
SELECT * FROM v_incomplete_evidence WHERE evidence_status = 'MISSING_SIGNATURE';
*/

-- Query: Get damaged items for outlet
/*
SELECT * FROM v_damaged_items WHERE outlet_id = 123 ORDER BY reported_at DESC LIMIT 20;
*/

-- Query: Evidence statistics
/*
SELECT
    evidence_type,
    COUNT(*) as count,
    DATE(uploaded_at) as upload_date
FROM receiving_evidence
WHERE uploaded_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
GROUP BY evidence_type, DATE(uploaded_at)
ORDER BY upload_date DESC, evidence_type;
*/

-- Rollback script (for testing)
-- ============================================================================
/*
DROP VIEW IF EXISTS v_damaged_items;
DROP VIEW IF EXISTS v_incomplete_evidence;
DROP VIEW IF EXISTS v_receiving_evidence_summary;
ALTER TABLE stock_transfer_items DROP COLUMN IF EXISTS last_received_at;
DROP TABLE IF EXISTS receiving_evidence;
*/
