-- Database migrations for consignments module
-- Date: 2025-11-01
-- Purpose: Add indexes for performance optimization on consignments and consignment_items tables

-- Consignments table indexes
ALTER TABLE consignments
  ADD KEY idx_status (status),
  ADD KEY idx_origin (origin_outlet_id),
  ADD KEY idx_dest (dest_outlet_id),
  ADD KEY idx_created (created_at);

-- Consignment items table indexes
ALTER TABLE consignment_items
  ADD KEY idx_consignment (consignment_id),
  ADD KEY idx_sku (sku),
  ADD KEY idx_status (status);
