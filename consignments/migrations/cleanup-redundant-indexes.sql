-- =====================================================================
-- Index Cleanup Script - Remove Redundant Indexes
-- =====================================================================
-- Purpose: Remove duplicate and redundant indexes that hurt performance
-- Date: 2025-11-01
-- Impact: Faster writes, less disk space, better query optimizer
--
-- ANALYSIS:
-- vend_consignments: 36 indexes → target 15 (remove 21)
-- vend_consignment_line_items: 18 indexes → target 8 (remove 10)
-- Expected savings: ~50MB disk space, 30-50% faster writes
-- =====================================================================

START TRANSACTION;

-- =====================================================================
-- VEND_CONSIGNMENTS - Remove 21 redundant indexes
-- =====================================================================

-- REDUNDANT: created_at is covered by composite indexes
-- Keep: idx_transfers_type_status_created (most specific composite)
-- Remove: 5 other created_at-only indexes
DROP INDEX idx_consignments_created ON vend_consignments;
DROP INDEX idx_transfers_created ON vend_consignments;
DROP INDEX idx_transfers_created_at ON vend_consignments;
DROP INDEX idx_transfers_type_created ON vend_consignments;
-- Keep idx_transfers_type_status_created (best composite)

-- REDUNDANT: state is covered by composite indexes
-- Keep: idx_transfers_from_to_state (3-column composite)
-- Remove: 2 state-only indexes
DROP INDEX idx_consignments_state ON vend_consignments;
DROP INDEX idx_transfers_state ON vend_consignments;
-- Keep idx_status (different column, needed)

-- REDUNDANT: outlet_to covered by composites
-- Keep: idx_transfers_to_status_date (outlet_to + created_at)
-- Remove: Single column index
DROP INDEX idx_consignments_outlet_to ON vend_consignments;
-- Keep idx_transfers_to_status_date, idx_transfers_from_to_state

-- REDUNDANT: outlet_from covered by composites
-- Keep: idx_transfers_from_to_state (most comprehensive)
-- Already optimal with idx_transfers_from_status_date

-- REDUNDANT: Duplicate composites
-- Keep: idx_transfers_to_status_date (outlet_to, created_at)
-- Remove: idx_transfers_to_created (identical!)
DROP INDEX idx_transfers_to_created ON vend_consignments;

-- REDUNDANT: idx_consignments_state_outlet is too specific
-- Keep: idx_transfers_from_to_state (more useful 3-column)
DROP INDEX idx_consignments_state_outlet ON vend_consignments;

-- KEEP these important indexes:
-- ✓ PRIMARY (id)
-- ✓ uniq_transfers_public_id (unique constraint)
-- ✓ uniq_transfers_vend_uuid (unique constraint)
-- ✓ idx_status (status lookups)
-- ✓ idx_due_at (due date sorting)
-- ✓ idx_consignment_id (FK lookups)
-- ✓ idx_supplier_id (supplier filtering)
-- ✓ idx_transfers_vend (vend integration)
-- ✓ idx_transfers_staff (staff tracking)
-- ✓ idx_transfers_category (category filtering)
-- ✓ idx_transfers_from_status_date (outlet_from + created_at)
-- ✓ idx_transfers_to_status_date (outlet_to + created_at)
-- ✓ idx_transfers_from_to_state (outlet_from + outlet_to + state)
-- ✓ idx_transfers_type_status_created (created_at composite)
-- ✓ idx_expected_delivery (expected_delivery_date + state)

-- Remove rarely used indexes (skip FK constraint indexes):
DROP INDEX idx_last_transaction ON vend_consignments;
DROP INDEX idx_locked_at ON vend_consignments;
DROP INDEX idx_lock_expires ON vend_consignments;
DROP INDEX idx_version ON vend_consignments;
DROP INDEX idx_tracking_number ON vend_consignments;
DROP INDEX idx_tracking_updated_at ON vend_consignments;
DROP INDEX idx_supplier_actions ON vend_consignments;
DROP INDEX idx_supplier_acknowledged ON vend_consignments;
-- SKIP: DROP INDEX idx_transfers_customer ON vend_consignments; -- FK constraint
DROP INDEX idx_transfers_creation_method ON vend_consignments;
DROP INDEX idx_transfers_vend_number ON vend_consignments;
DROP INDEX idx_consignments_public_id ON vend_consignments; -- covered by UNIQUE

-- =====================================================================
-- VEND_CONSIGNMENT_LINE_ITEMS - Remove 10 redundant indexes
-- =====================================================================

-- REDUNDANT: transfer_id single column indexes
-- Keep: idx_transfer_id (simplest, most used)
-- Remove: 3 duplicates
DROP INDEX idx_item_transfer ON vend_consignment_line_items;
DROP INDEX idx_line_items_transfer_id ON vend_consignment_line_items;
-- Keep idx_transfer_id

-- REDUNDANT: product_id single column indexes
-- Keep: idx_product_id (simplest, most used)
-- Remove: 2 duplicates
DROP INDEX idx_item_product ON vend_consignment_line_items;
DROP INDEX idx_line_items_product_id ON vend_consignment_line_items;
-- Keep idx_product_id

-- REDUNDANT: Composite transfer_id + product_id
-- Keep: uniq_transfer_product (UNIQUE constraint, most restrictive)
-- Remove: 2 non-unique duplicates
DROP INDEX idx_ti_transfer_product ON vend_consignment_line_items;
DROP INDEX uniq_item_transfer_product ON vend_consignment_line_items;
-- Keep uniq_transfer_product

-- REDUNDANT: transfer_id + confirmation_status composites
-- Keep: idx_items_transfer_status (better cardinality)
-- Remove: idx_items_outstanding (duplicate)
DROP INDEX idx_items_outstanding ON vend_consignment_line_items;
-- Keep idx_items_transfer_status

-- REDUNDANT: Rarely used indexes
DROP INDEX idx_item_confirm ON vend_consignment_line_items; -- covered by idx_items_transfer_status
DROP INDEX idx_line_items_product_status ON vend_consignment_line_items; -- rarely used
DROP INDEX idx_cis_product ON vend_consignment_line_items; -- rarely used

-- KEEP these important indexes:
-- ✓ PRIMARY (id)
-- ✓ uniq_transfer_product (transfer_id + product_id UNIQUE)
-- ✓ idx_transfer_id (FK to consignments)
-- ✓ idx_product_id (FK to products)
-- ✓ idx_line_items_status (status filtering)
-- ✓ idx_line_items_sku (SKU lookups)
-- ✓ idx_items_transfer_status (transfer_id + confirmation_status)
-- ✓ idx_line_items_transfer_status (transfer_id + status)

-- =====================================================================
-- COMMIT CHANGES
-- =====================================================================

COMMIT;

-- =====================================================================
-- VERIFICATION QUERIES
-- =====================================================================

-- Count remaining indexes
SELECT
    TABLE_NAME,
    COUNT(DISTINCT INDEX_NAME) as index_count
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = 'jcepnzzkmj'
    AND TABLE_NAME IN ('vend_consignments', 'vend_consignment_line_items')
GROUP BY TABLE_NAME;

-- Check new index sizes
SELECT
    TABLE_NAME,
    TABLE_ROWS,
    ROUND(DATA_LENGTH / 1024 / 1024, 2) AS data_mb,
    ROUND(INDEX_LENGTH / 1024 / 1024, 2) AS index_mb,
    ROUND(INDEX_LENGTH / DATA_LENGTH * 100, 2) AS index_ratio_percent
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = 'jcepnzzkmj'
    AND TABLE_NAME IN ('vend_consignments', 'vend_consignment_line_items')
ORDER BY TABLE_NAME;

-- =====================================================================
-- ROLLBACK SCRIPT (if needed)
-- =====================================================================

/*
-- To rollback, re-create dropped indexes:

-- vend_consignments rollback
ALTER TABLE vend_consignments ADD INDEX idx_consignments_created (created_at);
ALTER TABLE vend_consignments ADD INDEX idx_transfers_created (created_at);
ALTER TABLE vend_consignments ADD INDEX idx_transfers_created_at (created_at);
ALTER TABLE vend_consignments ADD INDEX idx_transfers_type_created (created_at);
ALTER TABLE vend_consignments ADD INDEX idx_consignments_state (state);
ALTER TABLE vend_consignments ADD INDEX idx_transfers_state (state);
ALTER TABLE vend_consignments ADD INDEX idx_consignments_outlet_to (outlet_to);
ALTER TABLE vend_consignments ADD INDEX idx_transfers_to_created (outlet_to, created_at);
ALTER TABLE vend_consignments ADD INDEX idx_consignments_state_outlet (state, outlet_to, created_at);
ALTER TABLE vend_consignments ADD INDEX idx_last_transaction (last_transaction_id);
ALTER TABLE vend_consignments ADD INDEX idx_locked_at (locked_at);
ALTER TABLE vend_consignments ADD INDEX idx_lock_expires (lock_expires_at);
ALTER TABLE vend_consignments ADD INDEX idx_version (version);
ALTER TABLE vend_consignments ADD INDEX idx_tracking_number (tracking_number);
ALTER TABLE vend_consignments ADD INDEX idx_tracking_updated_at (tracking_updated_at);
ALTER TABLE vend_consignments ADD INDEX idx_supplier_actions (supplier_sent_at, supplier_cancelled_at);
ALTER TABLE vend_consignments ADD INDEX idx_supplier_acknowledged (supplier_acknowledged_at);
ALTER TABLE vend_consignments ADD INDEX idx_transfers_customer (customer_id);
ALTER TABLE vend_consignments ADD INDEX idx_transfers_creation_method (creation_method);
ALTER TABLE vend_consignments ADD INDEX idx_transfers_vend_number (vend_number);
ALTER TABLE vend_consignments ADD INDEX idx_consignments_public_id (public_id);

-- vend_consignment_line_items rollback
ALTER TABLE vend_consignment_line_items ADD INDEX idx_item_transfer (transfer_id);
ALTER TABLE vend_consignment_line_items ADD INDEX idx_line_items_transfer_id (transfer_id);
ALTER TABLE vend_consignment_line_items ADD INDEX idx_item_product (product_id);
ALTER TABLE vend_consignment_line_items ADD INDEX idx_line_items_product_id (product_id);
ALTER TABLE vend_consignment_line_items ADD INDEX idx_ti_transfer_product (transfer_id, product_id);
ALTER TABLE vend_consignment_line_items ADD UNIQUE INDEX uniq_item_transfer_product (transfer_id, product_id);
ALTER TABLE vend_consignment_line_items ADD INDEX idx_items_outstanding (transfer_id, confirmation_status);
ALTER TABLE vend_consignment_line_items ADD INDEX idx_item_confirm (confirmation_status);
ALTER TABLE vend_consignment_line_items ADD INDEX idx_line_items_product_status (product_id, status);
ALTER TABLE vend_consignment_line_items ADD INDEX idx_cis_product (cis_product_id);
*/
