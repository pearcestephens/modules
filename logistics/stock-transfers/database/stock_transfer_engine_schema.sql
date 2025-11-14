-- ============================================================================
-- STOCK TRANSFER ENGINE - COMPLETE DATABASE SCHEMA
-- ============================================================================
-- Version: 1.0
-- Date: 2025-11-05
-- Description: Unified transfer system supporting stock, juice, internal,
--              and purchase order transfers with AI intelligence and dual
--              warehouse operation during transition period.
-- ============================================================================

-- ============================================================================
-- CORE TRANSFER TABLES
-- ============================================================================

-- Main transfers table
CREATE TABLE IF NOT EXISTS stock_transfers (
    id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    transfer_number VARCHAR(20) UNIQUE NOT NULL,  -- e.g., STOCK-20251105-001, JUICE-20251105-002

    -- Transfer classification
    type ENUM(
        'purchase_order_distribution',  -- From supplier consignment → stores
        'stock_transfer',               -- General merchandise (warehouse → store)
        'juice_transfer',               -- Frankton juice manufacturing → stores
        'internal_transfer',            -- Staff-initiated between stores
        'peer_transfer',                -- Store-to-store rebalancing
        'return_to_warehouse',          -- Store → warehouse returns
        'excess_rebalance'              -- AI-suggested redistribution
    ) NOT NULL,

    -- Location details
    source_outlet_id VARCHAR(50) NOT NULL,
    source_outlet_name VARCHAR(200),
    destination_outlet_id VARCHAR(50) NOT NULL,
    destination_outlet_name VARCHAR(200),

    -- Special flags
    is_juice_transfer BOOLEAN DEFAULT FALSE,
    packaging_type ENUM('small_bag', 'box', 'pallet') NULL,  -- Juice: bag <10 bottles, box ≥10

    -- Purchase order linking (if applicable)
    source_purchase_order_id VARCHAR(50) NULL,
    source_consignment_id VARCHAR(50) NULL,

    -- AI decision tracking
    ai_suggested BOOLEAN DEFAULT FALSE,
    ai_confidence DECIMAL(4,3) NULL,  -- 0.000 to 1.000
    ai_reasoning TEXT NULL,
    ai_alternative_routes JSON NULL,  -- Other routes AI considered

    -- Approval workflow
    requires_approval BOOLEAN DEFAULT TRUE,
    auto_approved BOOLEAN DEFAULT FALSE,
    was_modified BOOLEAN DEFAULT FALSE,  -- User changed AI suggestion
    modification_notes TEXT NULL,

    -- Status tracking
    status ENUM(
        'created',
        'pending_approval',
        'approved',
        'picking',
        'packing',
        'labeled',
        'shipped',
        'in_transit',
        'delivered',
        'completed',
        'cancelled'
    ) DEFAULT 'created',

    priority ENUM('critical', 'high', 'medium', 'low') DEFAULT 'medium',

    -- Reason/notes
    reason TEXT NULL,  -- 'Gap detected', 'Excess rebalance', 'Staff order', etc.
    internal_notes TEXT NULL,
    customer_facing_notes TEXT NULL,

    -- Shipment details
    scheduled_ship_date DATE NULL,
    actual_ship_date DATE NULL,
    estimated_delivery_date DATE NULL,
    actual_delivery_date DATE NULL,

    -- Courier details
    courier_name VARCHAR(100) NULL,
    courier_service VARCHAR(100) NULL,
    tracking_number VARCHAR(100) NULL,
    tracking_url TEXT NULL,
    label_pdf_url TEXT NULL,

    -- Costs
    freight_cost DECIMAL(10,2) DEFAULT 0.00,
    packaging_cost DECIMAL(10,2) DEFAULT 0.00,
    insurance_cost DECIMAL(10,2) DEFAULT 0.00,
    total_cost DECIMAL(10,2) DEFAULT 0.00,
    cost_savings DECIMAL(10,2) DEFAULT 0.00,  -- vs direct route

    -- Value tracking
    total_items INT DEFAULT 0,
    total_value DECIMAL(10,2) DEFAULT 0.00,
    total_margin DECIMAL(10,2) DEFAULT 0.00,
    margin_after_freight DECIMAL(10,2) DEFAULT 0.00,

    -- User tracking
    created_by INT UNSIGNED NOT NULL,
    approved_by INT UNSIGNED NULL,
    picked_by INT UNSIGNED NULL,
    packed_by INT UNSIGNED NULL,

    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    approved_at TIMESTAMP NULL,
    shipped_at TIMESTAMP NULL,
    delivered_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    cancelled_at TIMESTAMP NULL,

    -- Indexes for performance
    INDEX idx_transfer_number (transfer_number),
    INDEX idx_type (type),
    INDEX idx_status (status),
    INDEX idx_source (source_outlet_id, status),
    INDEX idx_destination (destination_outlet_id, status),
    INDEX idx_juice (is_juice_transfer, status),
    INDEX idx_tracking (tracking_number),
    INDEX idx_ai (ai_suggested, ai_confidence),
    INDEX idx_dates (scheduled_ship_date, status),
    INDEX idx_created_by (created_by),
    INDEX idx_consignment (source_consignment_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Transfer line items
CREATE TABLE IF NOT EXISTS stock_transfer_items (
    id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    transfer_id INT UNSIGNED NOT NULL,

    -- Product details
    product_id VARCHAR(50) NOT NULL,
    product_name VARCHAR(255),
    sku VARCHAR(100),

    -- Quantities
    quantity INT NOT NULL,
    quantity_picked INT DEFAULT 0,
    quantity_packed INT DEFAULT 0,
    quantity_delivered INT DEFAULT 0,

    -- Pricing
    unit_cost DECIMAL(10,2) DEFAULT 0.00,
    unit_sell DECIMAL(10,2) DEFAULT 0.00,
    unit_margin DECIMAL(10,2) DEFAULT 0.00,

    -- Calculated values
    total_cost DECIMAL(10,2) DEFAULT 0.00,
    total_sell DECIMAL(10,2) DEFAULT 0.00,
    total_margin DECIMAL(10,2) DEFAULT 0.00,

    -- Physical properties
    unit_weight_kg DECIMAL(6,3) DEFAULT 0.000,
    total_weight_kg DECIMAL(8,3) DEFAULT 0.000,

    -- Status
    status ENUM('pending', 'picking', 'picked', 'packed', 'shipped', 'delivered') DEFAULT 'pending',

    -- Notes
    notes TEXT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (transfer_id) REFERENCES stock_transfers(id) ON DELETE CASCADE,
    INDEX idx_transfer (transfer_id),
    INDEX idx_product (product_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Packing/box details
CREATE TABLE IF NOT EXISTS transfer_boxes (
    id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    transfer_id INT UNSIGNED NOT NULL,

    box_number INT NOT NULL,  -- Box 1, 2, 3 of this transfer
    box_type ENUM('small', 'medium', 'large', 'pallet', 'bag') DEFAULT 'medium',

    -- Dimensions
    length_cm DECIMAL(6,2) NULL,
    width_cm DECIMAL(6,2) NULL,
    height_cm DECIMAL(6,2) NULL,

    -- Weight
    actual_weight_kg DECIMAL(8,3) DEFAULT 0.000,
    volumetric_weight_kg DECIMAL(8,3) DEFAULT 0.000,
    chargeable_weight_kg DECIMAL(8,3) DEFAULT 0.000,

    -- Contents
    items_count INT DEFAULT 0,
    items_json JSON NULL,  -- [{product_id, quantity}]

    -- Tracking
    tracking_number VARCHAR(100) NULL,
    label_url TEXT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (transfer_id) REFERENCES stock_transfers(id) ON DELETE CASCADE,
    INDEX idx_transfer (transfer_id),
    INDEX idx_tracking (tracking_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tracking events (courier status updates)
CREATE TABLE IF NOT EXISTS transfer_tracking_events (
    id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    transfer_id INT UNSIGNED NOT NULL,

    event_type ENUM(
        'created',
        'approved',
        'picked',
        'packed',
        'labeled',
        'collected',
        'in_transit',
        'out_for_delivery',
        'delivered',
        'failed_delivery',
        'returned',
        'exception'
    ) NOT NULL,

    event_status VARCHAR(100),
    event_description TEXT,
    event_location VARCHAR(255) NULL,

    -- Courier data
    courier_status_code VARCHAR(50) NULL,
    courier_raw_data JSON NULL,

    -- Timestamp
    event_timestamp TIMESTAMP NOT NULL,
    received_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (transfer_id) REFERENCES stock_transfers(id) ON DELETE CASCADE,
    INDEX idx_transfer (transfer_id, event_timestamp),
    INDEX idx_type (event_type),
    INDEX idx_timestamp (event_timestamp)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- AI INTELLIGENCE TABLES
-- ============================================================================

-- Excess stock alerts (CORE PROBLEM SOLVER!)
CREATE TABLE IF NOT EXISTS excess_stock_alerts (
    id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,

    -- Location & product
    outlet_id VARCHAR(50) NOT NULL,
    outlet_name VARCHAR(200),
    product_id VARCHAR(50) NOT NULL,
    product_name VARCHAR(255),
    sku VARCHAR(100),

    -- Stock analysis
    current_stock INT NOT NULL,
    weekly_sales_avg DECIMAL(10,2) DEFAULT 0.00,
    weeks_of_stock DECIMAL(6,2) DEFAULT 0.00,  -- current / weekly_avg
    days_since_last_sale INT DEFAULT 0,

    -- Alert severity
    severity ENUM('caution', 'warning', 'critical') NOT NULL,
    -- caution: 8-12 weeks stock
    -- warning: 12-16 weeks stock
    -- critical: 16+ weeks OR dead stock (no sales 60+ days)

    -- AI suggested action
    suggested_action ENUM(
        'peer_transfer',
        'return_warehouse',
        'wait_monitor',
        'mark_clearance',
        'no_action'
    ) NOT NULL,

    suggested_destination_outlet_id VARCHAR(50) NULL,
    suggested_destination_name VARCHAR(200) NULL,
    suggested_quantity INT NULL,
    suggested_reason TEXT NULL,

    -- AI confidence
    ai_confidence DECIMAL(4,3) DEFAULT 0.000,
    ai_reasoning TEXT NULL,

    -- Status tracking
    status ENUM('new', 'reviewing', 'actioned', 'dismissed', 'expired') DEFAULT 'new',

    -- Action taken
    transfer_id INT UNSIGNED NULL,
    actioned_by INT UNSIGNED NULL,
    actioned_at TIMESTAMP NULL,
    action_notes TEXT NULL,

    -- Metadata
    detected_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL,  -- Alert becomes stale after 7 days

    FOREIGN KEY (transfer_id) REFERENCES stock_transfers(id) ON DELETE SET NULL,
    INDEX idx_outlet (outlet_id, status),
    INDEX idx_product (product_id),
    INDEX idx_severity (severity, status),
    INDEX idx_status (status),
    INDEX idx_suggested_action (suggested_action, status),
    INDEX idx_detected (detected_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Stock velocity tracking (sales speed analysis)
CREATE TABLE IF NOT EXISTS stock_velocity_tracking (
    id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,

    -- Location & product
    outlet_id VARCHAR(50) NOT NULL,
    product_id VARCHAR(50) NOT NULL,

    -- Sales data
    sales_last_7_days INT DEFAULT 0,
    sales_last_30_days INT DEFAULT 0,
    sales_last_90_days INT DEFAULT 0,
    avg_weekly_sales DECIMAL(10,2) DEFAULT 0.00,

    -- Velocity classification
    velocity ENUM('fast', 'medium', 'slow', 'dead') NOT NULL,
    -- fast: >10/week
    -- medium: 3-10/week
    -- slow: 0.5-3/week
    -- dead: <0.5/week

    -- Stock status
    current_stock INT DEFAULT 0,
    weeks_of_stock DECIMAL(6,2) DEFAULT 0.00,

    -- Trend analysis
    trend ENUM('increasing', 'stable', 'declining', 'unknown') DEFAULT 'unknown',
    trend_percentage DECIMAL(6,2) DEFAULT 0.00,  -- +/- %

    -- Stockout prediction
    predicted_stockout_date DATE NULL,
    stockout_risk ENUM('none', 'low', 'medium', 'high', 'critical') DEFAULT 'none',

    -- Metadata
    calculated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    data_points_count INT DEFAULT 0,  -- How many sales records analyzed

    INDEX idx_outlet_product (outlet_id, product_id),
    INDEX idx_velocity (velocity),
    INDEX idx_stockout_risk (stockout_risk),
    INDEX idx_calculated (calculated_at),
    UNIQUE KEY unique_outlet_product (outlet_id, product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- FREIGHT & LOGISTICS TABLES
-- ============================================================================

-- Freight costs (courier pricing by zone/weight)
CREATE TABLE IF NOT EXISTS freight_costs (
    id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,

    courier_name ENUM('nz_post', 'nz_couriers', 'aramex', 'mainfreight', 'peer_transfer', 'other') NOT NULL,
    service_level ENUM('standard', 'express', 'overnight', 'rural', 'economy') DEFAULT 'standard',

    -- Zone/region definition
    from_region VARCHAR(100) NOT NULL,
    to_region VARCHAR(100) NOT NULL,
    zone_code VARCHAR(20) NULL,

    -- Weight brackets (in kg)
    weight_min DECIMAL(6,2) DEFAULT 0.00,
    weight_max DECIMAL(6,2) DEFAULT 999.99,

    -- Pricing
    base_cost DECIMAL(10,2) NOT NULL,
    cost_per_kg DECIMAL(10,2) DEFAULT 0.00,
    rural_surcharge DECIMAL(10,2) DEFAULT 0.00,
    signature_fee DECIMAL(10,2) DEFAULT 0.00,
    insurance_percentage DECIMAL(5,2) DEFAULT 0.00,  -- % of declared value
    fuel_surcharge_percentage DECIMAL(5,2) DEFAULT 0.00,

    -- Constraints
    max_weight_kg DECIMAL(6,2) NULL,
    max_length_cm DECIMAL(6,2) NULL,
    max_girth_cm DECIMAL(6,2) NULL,  -- (width + height) * 2
    requires_account BOOLEAN DEFAULT FALSE,

    -- SLA
    estimated_delivery_days INT DEFAULT 3,
    guaranteed_delivery BOOLEAN DEFAULT FALSE,

    -- Validity period
    effective_from DATE NOT NULL,
    effective_to DATE NULL,

    -- API source (if rates pulled from API)
    is_api_rate BOOLEAN DEFAULT FALSE,
    last_api_update TIMESTAMP NULL,
    api_rate_id VARCHAR(100) NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_courier (courier_name, from_region, to_region),
    INDEX idx_weight (weight_min, weight_max),
    INDEX idx_zone (zone_code),
    INDEX idx_effective (effective_from, effective_to),
    INDEX idx_api (is_api_rate, last_api_update)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Product logistics data (weights, dimensions, packaging)
CREATE TABLE IF NOT EXISTS product_logistics (
    id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    product_id VARCHAR(50) UNIQUE NOT NULL,
    sku VARCHAR(100),

    -- Physical dimensions
    weight_kg DECIMAL(6,3) DEFAULT 0.000,
    length_cm DECIMAL(6,2) DEFAULT 0.00,
    width_cm DECIMAL(6,2) DEFAULT 0.00,
    height_cm DECIMAL(6,2) DEFAULT 0.00,
    volumetric_weight_kg DECIMAL(6,3) DEFAULT 0.000,  -- (L × W × H) / 5000

    -- Packaging requirements
    requires_bubble_wrap BOOLEAN DEFAULT FALSE,
    fragile BOOLEAN DEFAULT FALSE,
    hazmat BOOLEAN DEFAULT FALSE,
    stackable BOOLEAN DEFAULT TRUE,
    max_stack_height INT DEFAULT 10,

    -- Shipping constraints
    ships_separately BOOLEAN DEFAULT FALSE,
    min_box_size ENUM('small', 'medium', 'large', 'custom') DEFAULT 'medium',
    recommended_box_type VARCHAR(50) NULL,

    -- Cost tracking
    packaging_cost DECIMAL(6,2) DEFAULT 0.00,

    -- Product classification
    is_juice BOOLEAN DEFAULT FALSE,
    is_fast_moving BOOLEAN DEFAULT FALSE,

    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_product (product_id),
    INDEX idx_juice (is_juice),
    INDEX idx_fast_moving (is_fast_moving)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Outlet freight zones (store locations & freight classifications)
CREATE TABLE IF NOT EXISTS outlet_freight_zones (
    id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    outlet_id VARCHAR(50) UNIQUE NOT NULL,
    outlet_name VARCHAR(200) NOT NULL,
    outlet_type ENUM('warehouse', 'retail', 'juice_manufacturing', 'hybrid') DEFAULT 'retail',

    -- Address details
    address_line1 VARCHAR(200),
    address_line2 VARCHAR(200),
    city VARCHAR(100),
    region VARCHAR(100),
    postcode VARCHAR(10),

    -- Freight classification
    freight_zone VARCHAR(50),  -- 'METRO', 'REGIONAL', 'RURAL', 'ISLAND'
    courier_zone_nz_post VARCHAR(20),
    courier_zone_nz_couriers VARCHAR(20),
    courier_zone_aramex VARCHAR(20),

    -- Distance calculations
    distance_from_primary_warehouse_km DECIMAL(8,2) DEFAULT 0.00,
    distance_from_frankton_km DECIMAL(8,2) DEFAULT 0.00,

    -- Special handling flags
    is_rural BOOLEAN DEFAULT FALSE,
    is_island BOOLEAN DEFAULT FALSE,
    requires_ferry BOOLEAN DEFAULT FALSE,
    restricted_access BOOLEAN DEFAULT FALSE,

    -- Store classification
    is_flagship BOOLEAN DEFAULT FALSE,
    is_hub_store BOOLEAN DEFAULT FALSE,
    can_manufacture_juice BOOLEAN DEFAULT FALSE,  -- Currently only Frankton

    -- Operational metrics
    shipment_frequency_per_week DECIMAL(4,2) DEFAULT 0.00,
    avg_shipment_value DECIMAL(10,2) DEFAULT 0.00,

    -- Status
    is_active BOOLEAN DEFAULT TRUE,

    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_outlet (outlet_id),
    INDEX idx_zone (freight_zone),
    INDEX idx_type (outlet_type),
    INDEX idx_flagship (is_flagship),
    INDEX idx_hub (is_hub_store),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Transfer routes (performance tracking for routing decisions)
CREATE TABLE IF NOT EXISTS transfer_routes (
    id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,

    source_outlet_id VARCHAR(50) NOT NULL,
    destination_outlet_id VARCHAR(50) NOT NULL,
    route_type ENUM('direct', 'peer', 'hub', 'multi_hop') NOT NULL,

    -- Routing details
    intermediate_stops JSON NULL,  -- ['outlet_5', 'outlet_12'] for hub/multi-hop routes
    total_distance_km DECIMAL(8,2) DEFAULT 0.00,
    estimated_days INT DEFAULT 3,

    -- Cost comparison
    direct_freight_cost DECIMAL(10,2) DEFAULT 0.00,
    optimized_freight_cost DECIMAL(10,2) DEFAULT 0.00,
    cost_savings DECIMAL(10,2) DEFAULT 0.00,
    savings_percentage DECIMAL(5,2) DEFAULT 0.00,

    -- Performance metrics
    times_used INT DEFAULT 0,
    times_successful INT DEFAULT 0,
    times_failed INT DEFAULT 0,
    success_rate DECIMAL(5,2) DEFAULT 0.00,
    avg_delivery_time_days DECIMAL(4,2) DEFAULT 0.00,

    -- Metadata
    first_used_at TIMESTAMP NULL,
    last_used_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_route (source_outlet_id, destination_outlet_id),
    INDEX idx_savings (savings_percentage DESC),
    INDEX idx_performance (success_rate DESC, times_used DESC),
    UNIQUE KEY unique_route (source_outlet_id, destination_outlet_id, route_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- AUDIT & REJECTION TRACKING
-- ============================================================================

-- Transfer rejections (track when/why transfers were rejected)
CREATE TABLE IF NOT EXISTS transfer_rejections (
    id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,

    -- Product & routing
    product_id VARCHAR(50) NOT NULL,
    product_name VARCHAR(255),
    from_outlet_id VARCHAR(50) NOT NULL,
    to_outlet_id VARCHAR(50) NOT NULL,
    quantity INT NOT NULL,

    -- Rejection details
    rejection_reason ENUM(
        'freight_exceeds_margin',
        'margin_below_threshold',
        'low_value_high_freight',
        'margin_erosion_excessive',
        'total_value_too_low',
        'no_stock_available',
        'destination_overstocked',
        'other'
    ) NOT NULL,

    rejection_details TEXT,

    -- Financial analysis
    unit_cost DECIMAL(10,2),
    unit_sell DECIMAL(10,2),
    unit_margin DECIMAL(10,2),
    freight_cost DECIMAL(10,2),
    margin_after_freight DECIMAL(10,2),
    margin_percentage DECIMAL(5,2),

    -- Alternative suggested
    alternative_suggested ENUM('batch_wait', 'peer_transfer', 'hub_route', 'none') NULL,
    alternative_details TEXT NULL,

    -- Tracking
    rejected_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    rejected_by INT UNSIGNED NULL,  -- NULL = AI rejection, INT = manual rejection

    INDEX idx_product (product_id),
    INDEX idx_route (from_outlet_id, to_outlet_id),
    INDEX idx_reason (rejection_reason),
    INDEX idx_rejected (rejected_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- PURCHASE ORDER DISTRIBUTION TRACKING
-- ============================================================================

-- Consignment distributions (PO → Transfer automation)
CREATE TABLE IF NOT EXISTS consignment_distributions (
    id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,

    -- Lightspeed consignment details
    consignment_id VARCHAR(50) NOT NULL,
    consignment_number VARCHAR(50),
    supplier_id VARCHAR(50),
    supplier_name VARCHAR(200),
    received_date DATETIME NULL,
    total_units INT DEFAULT 0,
    total_value DECIMAL(10,2) DEFAULT 0.00,

    -- Distribution plan
    distribution_status ENUM(
        'pending',           -- Consignment received, planning not started
        'planned',           -- AI has created distribution plan
        'approved',          -- Plan approved by user
        'executing',         -- Transfers being created
        'completed',         -- All transfers created and executed
        'cancelled'
    ) DEFAULT 'pending',

    ai_distribution_plan JSON NULL,      -- AI's suggested allocation
    approved_distribution_plan JSON NULL, -- User's approved version (if modified)

    -- Links to transfers created
    transfer_ids JSON NULL,  -- Array of transfer IDs created from this consignment
    transfers_created_count INT DEFAULT 0,

    -- Tracking
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    planned_at TIMESTAMP NULL,
    approved_at TIMESTAMP NULL,
    approved_by INT UNSIGNED NULL,
    completed_at TIMESTAMP NULL,

    INDEX idx_consignment (consignment_id),
    INDEX idx_status (distribution_status),
    INDEX idx_received (received_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Consignment distribution items
CREATE TABLE IF NOT EXISTS consignment_distribution_items (
    id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    consignment_distribution_id INT UNSIGNED NOT NULL,

    -- Product details
    product_id VARCHAR(50) NOT NULL,
    product_name VARCHAR(255),
    quantity_received INT NOT NULL,

    -- Allocation plan
    allocation_warehouse INT DEFAULT 0,  -- Keep at warehouse
    allocation_transfers JSON NULL,      -- [{outlet_id, quantity, transfer_id, reason}]

    -- AI reasoning
    ai_reasoning TEXT NULL,
    ai_confidence DECIMAL(4,3) DEFAULT 0.000,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (consignment_distribution_id)
        REFERENCES consignment_distributions(id) ON DELETE CASCADE,
    INDEX idx_consignment (consignment_distribution_id),
    INDEX idx_product (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- SAMPLE DATA INSERTION (FOR TESTING)
-- ============================================================================

-- Insert Frankton outlet (current warehouse + juice manufacturing + retail)
INSERT IGNORE INTO outlet_freight_zones (
    outlet_id, outlet_name, outlet_type, city, region, freight_zone,
    is_flagship, is_hub_store, can_manufacture_juice,
    distance_from_primary_warehouse_km, distance_from_frankton_km
) VALUES (
    'frankton_001',
    'Frankton Store (Warehouse + Juice Mfg)',
    'hybrid',
    'Hamilton',
    'Waikato',
    'METRO',
    TRUE,
    TRUE,
    TRUE,
    0.00,  -- IS the primary warehouse currently
    0.00
);

-- Insert Hamilton East outlet (flagship retail)
INSERT IGNORE INTO outlet_freight_zones (
    outlet_id, outlet_name, outlet_type, city, region, freight_zone,
    is_flagship, is_hub_store, can_manufacture_juice,
    distance_from_primary_warehouse_km, distance_from_frankton_km
) VALUES (
    'hamilton_east_001',
    'Hamilton East Store',
    'retail',
    'Hamilton',
    'Waikato',
    'METRO',
    TRUE,
    FALSE,
    FALSE,
    5.00,   -- 5km from Frankton
    5.00
);

-- ============================================================================
-- SCHEMA COMPLETE
-- ============================================================================

-- Run this to verify table creation:
-- SHOW TABLES LIKE 'stock_%';
-- SHOW TABLES LIKE '%transfer%';
-- SHOW TABLES LIKE 'excess_%';
-- SHOW TABLES LIKE 'freight_%';
-- SHOW TABLES LIKE 'consignment_%';
