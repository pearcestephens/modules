-- ============================================================================
-- PARCEL & TRACKING DATA MODEL NORMALIZATION
-- ============================================================================
-- Date: 2025-10-31
-- Purpose: Remove redundant tracking fields from queue_consignments
--          Normalize to proper 1:many relationship via consignment_parcels
--          Add webhook support for NZ Courier and GSS tracking updates
--
-- CHANGES:
-- 1. Migrate tracking data from queue_consignments to consignment_parcels
-- 2. Drop redundant columns from queue_consignments
-- 3. Add webhook tracking tables
-- 4. Add proper foreign keys and indexes
-- ============================================================================

-- Start transaction for safety
START TRANSACTION;

-- ============================================================================
-- STEP 1: DATA MIGRATION - Copy tracking data before dropping columns
-- ============================================================================

-- Create temporary tracking data backup
CREATE TEMPORARY TABLE IF NOT EXISTS temp_tracking_backup AS
SELECT
    id,
    vend_consignment_id,
    cis_transfer_id,
    tracking_number,
    carrier,
    delivery_type,
    pickup_location,
    dropoff_location,
    created_at
FROM queue_consignments
WHERE tracking_number IS NOT NULL
  AND tracking_number != ''
  AND tracking_number != 'TRK-PENDING';

-- Log how many records we're migrating
SELECT
    COUNT(*) as records_to_migrate,
    COUNT(DISTINCT carrier) as unique_carriers
FROM temp_tracking_backup;

-- ============================================================================
-- STEP 2: Ensure consignment_shipments exist for all transfers with tracking
-- ============================================================================

-- Create shipment records if they don't exist
INSERT IGNORE INTO consignment_shipments (
    transfer_id,
    status,
    tracking_number,
    carrier_name,
    created_at,
    packed_at
)
SELECT
    tb.cis_transfer_id,
    'packed',
    tb.tracking_number,
    tb.carrier,
    tb.created_at,
    tb.created_at
FROM temp_tracking_backup tb
WHERE tb.cis_transfer_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1 FROM consignment_shipments cs
      WHERE cs.transfer_id = tb.cis_transfer_id
  );

-- ============================================================================
-- STEP 3: Ensure consignment_parcels exist for all tracking numbers
-- ============================================================================

-- Create parcel records if they don't exist
INSERT IGNORE INTO consignment_parcels (
    shipment_id,
    tracking_number,
    courier,
    status,
    box_number,
    parcel_number,
    created_at
)
SELECT
    cs.id as shipment_id,
    tb.tracking_number,
    tb.carrier,
    'pending',
    1,
    1,
    tb.created_at
FROM temp_tracking_backup tb
JOIN consignment_shipments cs ON cs.transfer_id = tb.cis_transfer_id
WHERE NOT EXISTS (
    SELECT 1 FROM consignment_parcels cp
    WHERE cp.tracking_number = tb.tracking_number
);

-- ============================================================================
-- STEP 4: Add indexes to consignment_parcels for webhook lookups
-- ============================================================================

-- Add index on tracking_number for fast webhook lookups
ALTER TABLE consignment_parcels
ADD INDEX idx_tracking_lookup (tracking_number, courier, status),
ADD INDEX idx_shipment_status (shipment_id, status),
ADD INDEX idx_courier_status (courier, status, created_at);

-- ============================================================================
-- STEP 5: Add indexes to consignment_shipments for tracking
-- ============================================================================

ALTER TABLE consignment_shipments
ADD INDEX idx_tracking_lookup (tracking_number, carrier_name, status),
ADD INDEX idx_transfer_status (transfer_id, status),
ADD INDEX idx_carrier_tracking (carrier_name, tracking_number);

-- ============================================================================
-- STEP 6: Create webhook tracking event table for NZ Courier / GSS
-- ============================================================================

CREATE TABLE IF NOT EXISTS courier_webhook_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    -- Webhook identification
    webhook_source ENUM('nzcourier', 'gss', 'starshipit', 'nzpost', 'courierpost') NOT NULL,
    event_type VARCHAR(64) NOT NULL,
    event_id VARCHAR(128) NULL, -- External event ID from courier

    -- Tracking info
    tracking_number VARCHAR(128) NOT NULL,
    carrier VARCHAR(64) NOT NULL,

    -- Event data
    event_code VARCHAR(64) NULL,
    event_description TEXT NULL,
    event_location VARCHAR(255) NULL,
    event_timestamp DATETIME NOT NULL,

    -- Status mapping
    status_before VARCHAR(64) NULL,
    status_after VARCHAR(64) NULL,

    -- Related records
    parcel_id INT NULL,
    shipment_id INT NULL,
    transfer_id INT NULL,

    -- Webhook payload
    raw_payload LONGTEXT NULL,
    headers_json JSON NULL,

    -- Processing
    processed TINYINT(1) DEFAULT 0,
    processed_at DATETIME NULL,
    processing_error TEXT NULL,

    -- Audit
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ip_address VARCHAR(45) NULL,

    INDEX idx_tracking (tracking_number, carrier),
    INDEX idx_source_type (webhook_source, event_type),
    INDEX idx_processed (processed, created_at),
    INDEX idx_parcel (parcel_id),
    INDEX idx_shipment (shipment_id),
    INDEX idx_transfer (transfer_id),
    INDEX idx_event_time (event_timestamp),

    FOREIGN KEY (parcel_id) REFERENCES consignment_parcels(id) ON DELETE SET NULL,
    FOREIGN KEY (shipment_id) REFERENCES consignment_shipments(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- STEP 7: Create courier webhook configuration table
-- ============================================================================

CREATE TABLE IF NOT EXISTS courier_webhook_config (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    courier_name ENUM('nzcourier', 'gss', 'starshipit', 'nzpost', 'courierpost') NOT NULL UNIQUE,

    -- Webhook settings
    webhook_url VARCHAR(512) NOT NULL,
    webhook_secret VARCHAR(256) NULL,
    signature_header VARCHAR(64) NULL,
    signature_algorithm ENUM('sha256', 'sha1', 'md5') DEFAULT 'sha256',

    -- Status mapping (JSON map of courier status -> CIS status)
    status_mapping JSON NULL,

    -- Configuration
    enabled TINYINT(1) DEFAULT 1,
    verify_signature TINYINT(1) DEFAULT 1,
    auto_update_status TINYINT(1) DEFAULT 1,

    -- Retry settings
    retry_failed TINYINT(1) DEFAULT 1,
    max_retries INT DEFAULT 3,

    -- Stats
    last_webhook_at DATETIME NULL,
    total_webhooks_received INT DEFAULT 0,
    total_webhooks_processed INT DEFAULT 0,
    total_webhooks_failed INT DEFAULT 0,

    -- Audit
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by INT NULL,

    INDEX idx_enabled (enabled, courier_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- STEP 8: Insert default courier webhook configurations
-- ============================================================================

INSERT INTO courier_webhook_config (
    courier_name,
    webhook_url,
    webhook_secret,
    signature_header,
    status_mapping,
    enabled
) VALUES
(
    'nzcourier',
    'https://staff.vapeshed.co.nz/assets/services/webhooks/courier/nzcourier.php',
    NULL, -- Set this from your NZ Courier dashboard
    'X-NZCourier-Signature',
    JSON_OBJECT(
        'PICKED_UP', 'in_transit',
        'IN_TRANSIT', 'in_transit',
        'OUT_FOR_DELIVERY', 'in_transit',
        'DELIVERED', 'received',
        'FAILED_DELIVERY', 'exception',
        'RETURNED', 'exception',
        'CANCELLED', 'cancelled'
    ),
    1
),
(
    'gss',
    'https://staff.vapeshed.co.nz/assets/services/webhooks/courier/gss.php',
    NULL, -- Set this from GSS/StarShipIt dashboard
    'X-GSS-Signature',
    JSON_OBJECT(
        'Picked Up', 'in_transit',
        'In Transit', 'in_transit',
        'Out for Delivery', 'in_transit',
        'Delivered', 'received',
        'Delivery Failed', 'exception',
        'Returned to Sender', 'exception',
        'Cancelled', 'cancelled'
    ),
    1
),
(
    'starshipit',
    'https://staff.vapeshed.co.nz/assets/services/webhooks/courier/starshipit.php',
    NULL, -- Set this from StarShipIt dashboard
    'X-StarShipIt-Signature',
    JSON_OBJECT(
        'PickedUp', 'in_transit',
        'InTransit', 'in_transit',
        'OutForDelivery', 'in_transit',
        'Delivered', 'received',
        'DeliveryFailed', 'exception',
        'ReturnedToSender', 'exception',
        'Cancelled', 'cancelled'
    ),
    1
);

-- ============================================================================
-- STEP 9: Create courier webhook retry queue
-- ============================================================================

CREATE TABLE IF NOT EXISTS courier_webhook_retry_queue (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    webhook_event_id BIGINT UNSIGNED NOT NULL,
    attempt_number INT DEFAULT 1,

    scheduled_at DATETIME NOT NULL,
    attempted_at DATETIME NULL,

    error_message TEXT NULL,

    status ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending',

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_scheduled (status, scheduled_at),
    INDEX idx_event (webhook_event_id),

    FOREIGN KEY (webhook_event_id) REFERENCES courier_webhook_events(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- STEP 10: DROP redundant columns from queue_consignments
-- ============================================================================

-- These fields now live in consignment_parcels and consignment_shipments
ALTER TABLE queue_consignments
DROP COLUMN IF EXISTS tracking_number,
DROP COLUMN IF EXISTS carrier,
DROP COLUMN IF EXISTS delivery_type,
DROP COLUMN IF EXISTS pickup_location,
DROP COLUMN IF EXISTS dropoff_location;

-- ============================================================================
-- STEP 11: Create view for easy tracking lookups
-- ============================================================================

CREATE OR REPLACE VIEW v_consignment_tracking AS
SELECT
    qc.id as consignment_id,
    qc.vend_consignment_id,
    qc.lightspeed_consignment_id,
    qc.cis_transfer_id as transfer_id,
    qc.reference as consignment_reference,
    qc.status as consignment_status,

    cs.id as shipment_id,
    cs.status as shipment_status,
    cs.carrier_name,
    cs.tracking_number as shipment_tracking,
    cs.packed_at,
    cs.received_at as shipment_received_at,

    cp.id as parcel_id,
    cp.tracking_number as parcel_tracking,
    cp.courier,
    cp.status as parcel_status,
    cp.box_number,
    cp.parcel_number,
    cp.weight_kg,
    cp.label_url,

    (SELECT COUNT(*) FROM consignment_parcel_items WHERE parcel_id = cp.id) as items_in_parcel,

    (SELECT MAX(event_timestamp)
     FROM courier_webhook_events
     WHERE tracking_number = cp.tracking_number) as last_tracking_event,

    (SELECT event_description
     FROM courier_webhook_events
     WHERE tracking_number = cp.tracking_number
     ORDER BY event_timestamp DESC
     LIMIT 1) as latest_tracking_status

FROM queue_consignments qc
LEFT JOIN consignment_shipments cs ON cs.transfer_id = qc.cis_transfer_id
LEFT JOIN consignment_parcels cp ON cp.shipment_id = cs.id
WHERE qc.deleted_at IS NULL;

-- ============================================================================
-- STEP 12: Create stored procedure for webhook processing
-- ============================================================================

DROP PROCEDURE IF EXISTS process_courier_webhook_event;

DELIMITER $$

CREATE PROCEDURE process_courier_webhook_event(
    IN p_webhook_source VARCHAR(64),
    IN p_tracking_number VARCHAR(128),
    IN p_event_code VARCHAR(64),
    IN p_event_description TEXT,
    IN p_event_timestamp DATETIME,
    IN p_raw_payload LONGTEXT
)
BEGIN
    DECLARE v_parcel_id INT;
    DECLARE v_shipment_id INT;
    DECLARE v_transfer_id INT;
    DECLARE v_status_mapping JSON;
    DECLARE v_new_status VARCHAR(64);
    DECLARE v_webhook_event_id BIGINT;

    -- Get configuration for this courier
    SELECT status_mapping
    INTO v_status_mapping
    FROM courier_webhook_config
    WHERE courier_name = p_webhook_source
      AND enabled = 1;

    -- Map courier status to CIS status
    SET v_new_status = JSON_UNQUOTE(JSON_EXTRACT(v_status_mapping, CONCAT('$."', p_event_code, '"')));

    -- Find the parcel
    SELECT id, shipment_id
    INTO v_parcel_id, v_shipment_id
    FROM consignment_parcels
    WHERE tracking_number = p_tracking_number
    LIMIT 1;

    -- Find the transfer
    IF v_shipment_id IS NOT NULL THEN
        SELECT transfer_id INTO v_transfer_id
        FROM consignment_shipments
        WHERE id = v_shipment_id;
    END IF;

    -- Insert webhook event
    INSERT INTO courier_webhook_events (
        webhook_source,
        event_type,
        tracking_number,
        carrier,
        event_code,
        event_description,
        event_timestamp,
        status_after,
        parcel_id,
        shipment_id,
        transfer_id,
        raw_payload,
        processed
    ) VALUES (
        p_webhook_source,
        'tracking_update',
        p_tracking_number,
        p_webhook_source,
        p_event_code,
        p_event_description,
        p_event_timestamp,
        v_new_status,
        v_parcel_id,
        v_shipment_id,
        v_transfer_id,
        p_raw_payload,
        1
    );

    SET v_webhook_event_id = LAST_INSERT_ID();

    -- Update parcel status if we have a valid mapping
    IF v_new_status IS NOT NULL AND v_parcel_id IS NOT NULL THEN
        UPDATE consignment_parcels
        SET status = v_new_status,
            updated_at = NOW()
        WHERE id = v_parcel_id;

        -- Update shipment status if all parcels are delivered
        IF v_new_status = 'received' AND v_shipment_id IS NOT NULL THEN
            UPDATE consignment_shipments cs
            SET cs.status = 'received',
                cs.received_at = NOW()
            WHERE cs.id = v_shipment_id
              AND NOT EXISTS (
                  SELECT 1 FROM consignment_parcels cp
                  WHERE cp.shipment_id = cs.id
                    AND cp.status != 'received'
              );
        END IF;
    END IF;

    -- Log to tracking events table
    IF v_transfer_id IS NOT NULL THEN
        INSERT INTO consignment_tracking_events (
            transfer_id,
            parcel_id,
            tracking_number,
            carrier,
            event_code,
            event_text,
            occurred_at,
            raw_json
        ) VALUES (
            v_transfer_id,
            v_parcel_id,
            p_tracking_number,
            p_webhook_source,
            p_event_code,
            p_event_description,
            p_event_timestamp,
            p_raw_payload
        );
    END IF;

    -- Update config stats
    UPDATE courier_webhook_config
    SET last_webhook_at = NOW(),
        total_webhooks_received = total_webhooks_received + 1,
        total_webhooks_processed = total_webhooks_processed + 1
    WHERE courier_name = p_webhook_source;

    SELECT v_webhook_event_id as webhook_event_id;
END$$

DELIMITER ;

-- ============================================================================
-- STEP 13: Create indexes on consignment_tracking_events
-- ============================================================================

ALTER TABLE consignment_tracking_events
ADD INDEX IF NOT EXISTS idx_tracking_lookup (tracking_number, carrier, occurred_at),
ADD INDEX IF NOT EXISTS idx_transfer_time (transfer_id, occurred_at),
ADD INDEX IF NOT EXISTS idx_parcel_time (parcel_id, occurred_at);

-- ============================================================================
-- STEP 14: Verification queries
-- ============================================================================

-- Show migration summary
SELECT
    'Tracking Data Migration Summary' as report_section,
    COUNT(*) as total_tracking_records_migrated
FROM temp_tracking_backup;

SELECT
    'Parcels Created' as report_section,
    COUNT(*) as total_parcels
FROM consignment_parcels;

SELECT
    'Shipments Created' as report_section,
    COUNT(*) as total_shipments
FROM consignment_shipments;

SELECT
    'Webhook Config' as report_section,
    courier_name,
    enabled,
    webhook_url
FROM courier_webhook_config;

-- ============================================================================
-- COMMIT TRANSACTION
-- ============================================================================

COMMIT;

-- ============================================================================
-- POST-MIGRATION CLEANUP
-- ============================================================================

-- Drop temporary table
DROP TEMPORARY TABLE IF EXISTS temp_tracking_backup;

-- ============================================================================
-- ROLLBACK SCRIPT (Run only if needed)
-- ============================================================================
-- To rollback these changes:
--
-- ALTER TABLE queue_consignments
-- ADD COLUMN tracking_number VARCHAR(255) DEFAULT 'TRK-PENDING',
-- ADD COLUMN carrier VARCHAR(100) DEFAULT 'CourierPost',
-- ADD COLUMN delivery_type ENUM('pickup','dropoff') DEFAULT 'dropoff',
-- ADD COLUMN pickup_location VARCHAR(255) NULL,
-- ADD COLUMN dropoff_location VARCHAR(255) NULL;
--
-- DROP VIEW IF EXISTS v_consignment_tracking;
-- DROP PROCEDURE IF EXISTS process_courier_webhook_event;
-- DROP TABLE IF EXISTS courier_webhook_retry_queue;
-- DROP TABLE IF EXISTS courier_webhook_events;
-- DROP TABLE IF EXISTS courier_webhook_config;
-- ============================================================================
