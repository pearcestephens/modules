-- ============================================================================
-- O10: Freight Integration - Database Migration
-- ============================================================================
-- Purpose: Store freight bookings and tracking information
-- ============================================================================

-- Freight bookings table
CREATE TABLE IF NOT EXISTS freight_bookings (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    transfer_id BIGINT UNSIGNED NOT NULL,
    provider VARCHAR(50) NOT NULL COMMENT 'Provider identifier (freight_now, toll, etc)',
    booking_reference VARCHAR(100) NOT NULL COMMENT 'Provider booking reference',
    tracking_number VARCHAR(100) NOT NULL COMMENT 'Tracking/consignment number',
    label_url VARCHAR(500) NULL COMMENT 'URL to label PDF',
    status ENUM('BOOKED', 'PENDING', 'IN_TRANSIT', 'OUT_FOR_DELIVERY', 'DELIVERED', 'FAILED', 'RETURNED', 'CANCELLED') NOT NULL DEFAULT 'BOOKED',
    booking_data JSON NULL COMMENT 'Full API response from provider',
    cancellation_reason TEXT NULL,
    cost DECIMAL(10,2) NULL COMMENT 'Freight cost',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    cancelled_at TIMESTAMP NULL,
    last_tracking_update TIMESTAMP NULL,

    -- Foreign keys
    FOREIGN KEY (transfer_id) REFERENCES stock_transfers(id) ON DELETE CASCADE,

    -- Indexes
    INDEX idx_transfer_id (transfer_id),
    INDEX idx_tracking_number (tracking_number),
    INDEX idx_provider (provider),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at),

    -- Unique constraint
    UNIQUE KEY uk_booking_reference (provider, booking_reference)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Freight bookings for stock transfers';

-- Add columns to stock_transfers table
ALTER TABLE stock_transfers
ADD COLUMN IF NOT EXISTS freight_booking_id BIGINT UNSIGNED NULL
COMMENT 'Current/active freight booking',
ADD COLUMN IF NOT EXISTS freight_tracking_number VARCHAR(100) NULL
COMMENT 'Quick access to tracking number',
ADD COLUMN IF NOT EXISTS freight_booked_at TIMESTAMP NULL
COMMENT 'When freight was booked';

-- Add foreign key
ALTER TABLE stock_transfers
ADD CONSTRAINT fk_freight_booking
FOREIGN KEY (freight_booking_id) REFERENCES freight_bookings(id) ON DELETE SET NULL;

-- View: Active freight bookings
CREATE OR REPLACE VIEW v_active_freight_bookings AS
SELECT
    fb.id as booking_id,
    fb.transfer_id,
    fb.provider,
    fb.tracking_number,
    fb.status as freight_status,
    fb.cost as freight_cost,
    fb.created_at as booked_at,
    t.type as transfer_type,
    t.status as transfer_status,
    t.outlet_id,
    o.name as outlet_name
FROM freight_bookings fb
INNER JOIN stock_transfers t ON fb.transfer_id = t.id
LEFT JOIN vend_outlets o ON t.outlet_id = o.id
WHERE fb.status NOT IN ('DELIVERED', 'CANCELLED')
ORDER BY fb.created_at DESC;

-- View: Freight booking summary
CREATE OR REPLACE VIEW v_freight_booking_summary AS
SELECT
    provider,
    status,
    COUNT(*) as booking_count,
    SUM(cost) as total_cost,
    DATE(created_at) as booking_date
FROM freight_bookings
WHERE created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)
GROUP BY provider, status, DATE(created_at)
ORDER BY booking_date DESC, provider, status;

-- Sample queries
-- ============================================================================

-- Query: Get all bookings for transfer
/*
SELECT * FROM freight_bookings WHERE transfer_id = 123 ORDER BY created_at DESC;
*/

-- Query: Get active bookings
/*
SELECT * FROM v_active_freight_bookings WHERE freight_status = 'IN_TRANSIT';
*/

-- Query: Freight cost summary by provider
/*
SELECT
    provider,
    COUNT(*) as bookings,
    SUM(cost) as total_cost,
    AVG(cost) as avg_cost
FROM freight_bookings
WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
  AND cost IS NOT NULL
GROUP BY provider;
*/

-- Rollback script
-- ============================================================================
/*
ALTER TABLE stock_transfers DROP FOREIGN KEY fk_freight_booking;
ALTER TABLE stock_transfers
    DROP COLUMN freight_booking_id,
    DROP COLUMN freight_tracking_number,
    DROP COLUMN freight_booked_at;
DROP VIEW IF EXISTS v_freight_booking_summary;
DROP VIEW IF EXISTS v_active_freight_bookings;
DROP TABLE IF EXISTS freight_bookings;
*/
