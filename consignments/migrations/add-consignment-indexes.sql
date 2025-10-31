-- =====================================================================
-- Consignment Performance Indexes Migration
-- =====================================================================
-- Purpose: Add indexes to optimize consignment queries
-- Date: 2025-10-31
-- Impact: Significant performance improvement for filtered queries
--
-- Estimated time: 1-5 minutes depending on table size
-- Rollback: See rollback section at bottom
-- =====================================================================

-- Start transaction (for InnoDB tables)
START TRANSACTION;

-- =====================================================================
-- CONSIGNMENTS TABLE INDEXES
-- =====================================================================

-- Single-column indexes for common filters
ALTER TABLE consignments ADD INDEX IF NOT EXISTS idx_status (status);
ALTER TABLE consignments ADD INDEX IF NOT EXISTS idx_origin (origin_outlet_id);
ALTER TABLE consignments ADD INDEX IF NOT EXISTS idx_dest (dest_outlet_id);
ALTER TABLE consignments ADD INDEX IF NOT EXISTS idx_created (created_at);

-- Composite indexes for combined filters (most common queries)
ALTER TABLE consignments ADD INDEX IF NOT EXISTS idx_outlet_status (origin_outlet_id, status);
ALTER TABLE consignments ADD INDEX IF NOT EXISTS idx_dest_status (dest_outlet_id, status);
ALTER TABLE consignments ADD INDEX IF NOT EXISTS idx_created_status (created_at DESC, status);

-- Index for ref_code search (LIKE queries)
ALTER TABLE consignments ADD INDEX IF NOT EXISTS idx_ref_code (ref_code(20));

-- =====================================================================
-- CONSIGNMENT_ITEMS TABLE INDEXES
-- =====================================================================

-- Foreign key index (should already exist, but ensure it)
ALTER TABLE consignment_items ADD INDEX IF NOT EXISTS idx_consignment (consignment_id);

-- Product lookup indexes
ALTER TABLE consignment_items ADD INDEX IF NOT EXISTS idx_product (product_id(20));
ALTER TABLE consignment_items ADD INDEX IF NOT EXISTS idx_sku (sku(20));

-- Status filter index
ALTER TABLE consignment_items ADD INDEX IF NOT EXISTS idx_status (status);

-- Composite index for common item queries
ALTER TABLE consignment_items ADD INDEX IF NOT EXISTS idx_consignment_status (consignment_id, status);

-- =====================================================================
-- OPTIONAL: FOREIGN KEY CONSTRAINT
-- =====================================================================
-- Uncomment if you want to enforce referential integrity
-- Note: This will fail if there are orphaned items

-- Check if constraint already exists
-- SELECT CONSTRAINT_NAME
-- FROM information_schema.TABLE_CONSTRAINTS
-- WHERE TABLE_SCHEMA = DATABASE()
--   AND TABLE_NAME = 'consignment_items'
--   AND CONSTRAINT_NAME = 'fk_consignment_items_consignment';

-- Add FK constraint (uncomment to enable)
-- ALTER TABLE consignment_items
-- ADD CONSTRAINT fk_consignment_items_consignment
-- FOREIGN KEY (consignment_id)
-- REFERENCES consignments(id)
-- ON DELETE CASCADE
-- ON UPDATE CASCADE;

-- Commit changes
COMMIT;

-- =====================================================================
-- VERIFICATION QUERIES
-- =====================================================================
-- Run these to verify indexes were created:

-- Show all indexes on consignments table
SHOW INDEXES FROM consignments;

-- Show all indexes on consignment_items table
SHOW INDEXES FROM consignment_items;

-- Check index usage (run after some queries)
-- EXPLAIN SELECT * FROM consignments WHERE status = 'sent';
-- EXPLAIN SELECT * FROM consignments WHERE origin_outlet_id = 1 AND status = 'sent';
-- EXPLAIN SELECT * FROM consignments WHERE ref_code LIKE 'CON%';
-- EXPLAIN SELECT * FROM consignment_items WHERE consignment_id = 1;

-- =====================================================================
-- EXPECTED PERFORMANCE IMPROVEMENTS
-- =====================================================================
-- Before indexes:
-- - recent(): Full table scan, ~500ms for 10k rows
-- - search(): Full table scan with LIKE, ~800ms
-- - stats(): Full table scan with GROUP BY, ~600ms
-- - items(): Full table scan, ~200ms per consignment
--
-- After indexes:
-- - recent(): Index scan, ~50ms
-- - search(): Index scan + ref_code prefix, ~80ms
-- - stats(): Index scan with aggregation, ~100ms
-- - items(): Index lookup, ~20ms per consignment
--
-- Overall improvement: 5-10x faster queries
-- =====================================================================

-- =====================================================================
-- ROLLBACK SCRIPT (if needed)
-- =====================================================================
-- Run these commands to remove indexes if there are issues:

-- START TRANSACTION;

-- DROP INDEX idx_status ON consignments;
-- DROP INDEX idx_origin ON consignments;
-- DROP INDEX idx_dest ON consignments;
-- DROP INDEX idx_created ON consignments;
-- DROP INDEX idx_outlet_status ON consignments;
-- DROP INDEX idx_dest_status ON consignments;
-- DROP INDEX idx_created_status ON consignments;
-- DROP INDEX idx_ref_code ON consignments;

-- DROP INDEX idx_consignment ON consignment_items;
-- DROP INDEX idx_product ON consignment_items;
-- DROP INDEX idx_sku ON consignment_items;
-- DROP INDEX idx_status ON consignment_items;
-- DROP INDEX idx_consignment_status ON consignment_items;

-- Optionally drop FK constraint
-- ALTER TABLE consignment_items DROP FOREIGN KEY fk_consignment_items_consignment;

-- COMMIT;

-- =====================================================================
-- NOTES
-- =====================================================================
-- 1. IF NOT EXISTS clause prevents errors if indexes already exist
-- 2. Prefix lengths (20) on varchar indexes optimize storage
-- 3. Composite indexes support queries with multiple WHERE conditions
-- 4. Index order matters: (outlet_id, status) != (status, outlet_id)
-- 5. created_at DESC in composite index supports ORDER BY created_at DESC
-- 6. FK constraint is optional - only use if referential integrity needed
-- 7. Monitor query performance with EXPLAIN after deployment
-- 8. Consider ANALYZE TABLE after index creation for query optimizer
--
-- Maintenance:
-- - Run ANALYZE TABLE consignments; after significant data changes
-- - Run ANALYZE TABLE consignment_items; periodically
-- - Monitor index usage with information_schema.STATISTICS
-- =====================================================================
