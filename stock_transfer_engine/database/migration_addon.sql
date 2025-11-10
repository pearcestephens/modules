-- ============================================================================
-- CIS Stock Transfer Engine - ADDITIVE Migration
-- Extends existing stock_transfers tables with AI and optimization features
-- SAFE: Uses IF NOT EXISTS and ADD COLUMN IF NOT EXISTS
-- ============================================================================

USE jcepnzzkmj;

-- ============================================================================
-- STEP 1: Extend existing stock_transfers table
-- ============================================================================
ALTER TABLE stock_transfers 
ADD COLUMN IF NOT EXISTS ai_confidence DECIMAL(5,2) COMMENT 'AI confidence score for excess detection (0-100)',
ADD COLUMN IF NOT EXISTS freight_cost DECIMAL(10,2) COMMENT 'Calculated freight cost for this transfer',
ADD COLUMN IF NOT EXISTS route_id INT COMMENT 'Optimized route ID from transfer_routes table',
ADD COLUMN IF NOT EXISTS box_count INT DEFAULT 0 COMMENT 'Number of boxes in this transfer',
ADD COLUMN IF NOT EXISTS total_weight DECIMAL(10,2) COMMENT 'Total weight in kg',
ADD COLUMN IF NOT EXISTS priority VARCHAR(20) DEFAULT 'NORMAL' COMMENT 'Transfer priority: URGENT, HIGH, NORMAL, LOW',
ADD COLUMN IF NOT EXISTS automated BOOLEAN DEFAULT FALSE COMMENT 'Whether this transfer was AI-generated';

-- ============================================================================
-- STEP 2: Extend existing stock_transfer_items table
-- ============================================================================
ALTER TABLE stock_transfer_items
ADD COLUMN IF NOT EXISTS excess_qty INT DEFAULT 0 COMMENT 'Quantity identified as excess by AI',
ADD COLUMN IF NOT EXISTS velocity_score DECIMAL(5,2) COMMENT 'Sales velocity score for this product',
ADD COLUMN IF NOT EXISTS days_of_stock INT COMMENT 'Calculated days of stock remaining',
ADD COLUMN IF NOT EXISTS box_id INT COMMENT 'Which box this item is packed in';

-- ============================================================================
-- STEP 3: Create new supplementary tables
-- ============================================================================

-- Excess Stock Alerts (AI Detection)
CREATE TABLE IF NOT EXISTS excess_stock_alerts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    outlet_id VARCHAR(100) NOT NULL,
    product_id VARCHAR(100) NOT NULL,
    current_stock INT NOT NULL,
    average_daily_sales DECIMAL(10,2),
    days_of_stock INT,
    excess_quantity INT,
    recommended_transfer_qty INT,
    confidence_score DECIMAL(5,2),
    reason TEXT,
    status VARCHAR(20) DEFAULT 'PENDING',
    transfer_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    resolved_at TIMESTAMP NULL,
    INDEX idx_outlet_product (outlet_id, product_id),
    INDEX idx_status (status),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='AI-detected excess stock requiring transfer';

-- Stock Velocity Tracking
CREATE TABLE IF NOT EXISTS stock_velocity_tracking (
    id INT AUTO_INCREMENT PRIMARY KEY,
    outlet_id VARCHAR(100) NOT NULL,
    product_id VARCHAR(100) NOT NULL,
    date DATE NOT NULL,
    units_sold INT DEFAULT 0,
    stock_level INT,
    velocity_score DECIMAL(10,4),
    trend VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_tracking (outlet_id, product_id, date),
    INDEX idx_outlet_date (outlet_id, date),
    INDEX idx_product_date (product_id, date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Daily sales velocity tracking for smart transfers';

-- Freight Costs Calculator
CREATE TABLE IF NOT EXISTS freight_costs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    from_outlet_id VARCHAR(100) NOT NULL,
    to_outlet_id VARCHAR(100) NOT NULL,
    service_type VARCHAR(50) DEFAULT 'STANDARD',
    weight_from DECIMAL(10,2),
    weight_to DECIMAL(10,2),
    cost DECIMAL(10,2) NOT NULL,
    cost_per_kg DECIMAL(10,2),
    effective_date DATE NOT NULL,
    expires_date DATE,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_route (from_outlet_id, to_outlet_id),
    INDEX idx_active (is_active, effective_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Freight cost calculations for transfer optimization';

-- Outlet Freight Zones
CREATE TABLE IF NOT EXISTS outlet_freight_zones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    outlet_id VARCHAR(100) NOT NULL UNIQUE,
    zone_name VARCHAR(100),
    region VARCHAR(100),
    base_freight_cost DECIMAL(10,2),
    per_kg_rate DECIMAL(10,2),
    rural_surcharge DECIMAL(10,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_zone (zone_name),
    INDEX idx_region (region)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Freight zone mapping for outlets';

-- Transfer Routes (Optimization)
CREATE TABLE IF NOT EXISTS transfer_routes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    from_outlet_id VARCHAR(100) NOT NULL,
    to_outlet_id VARCHAR(100) NOT NULL,
    distance_km DECIMAL(10,2),
    estimated_hours DECIMAL(5,2),
    preferred_carrier VARCHAR(100),
    route_notes TEXT,
    success_rate DECIMAL(5,2),
    avg_transit_days INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_route (from_outlet_id, to_outlet_id),
    INDEX idx_from (from_outlet_id),
    INDEX idx_to (to_outlet_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Optimized transfer routes between outlets';

-- Transfer Boxes (Packing Details)
CREATE TABLE IF NOT EXISTS transfer_boxes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transfer_id INT NOT NULL,
    box_number INT NOT NULL,
    weight DECIMAL(10,2),
    dimensions VARCHAR(50),
    tracking_number VARCHAR(100),
    status VARCHAR(20) DEFAULT 'PACKED',
    packed_at TIMESTAMP NULL,
    shipped_at TIMESTAMP NULL,
    delivered_at TIMESTAMP NULL,
    INDEX idx_transfer (transfer_id),
    INDEX idx_tracking (tracking_number),
    FOREIGN KEY (transfer_id) REFERENCES stock_transfers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Box-level tracking for transfers';

-- Transfer Rejections
CREATE TABLE IF NOT EXISTS transfer_rejections (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transfer_id INT NOT NULL,
    item_id INT,
    rejected_by VARCHAR(100),
    rejection_reason TEXT,
    rejected_qty INT,
    action_taken VARCHAR(50),
    rejected_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_transfer (transfer_id),
    INDEX idx_date (rejected_at),
    FOREIGN KEY (transfer_id) REFERENCES stock_transfers(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Track rejections and issues with transfers';

-- Transfer Tracking Events
CREATE TABLE IF NOT EXISTS transfer_tracking_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transfer_id INT NOT NULL,
    event_type VARCHAR(50) NOT NULL,
    event_description TEXT,
    location VARCHAR(200),
    latitude DECIMAL(10,8),
    longitude DECIMAL(11,8),
    created_by VARCHAR(100),
    metadata JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_transfer (transfer_id),
    INDEX idx_type (event_type),
    INDEX idx_created (created_at),
    FOREIGN KEY (transfer_id) REFERENCES stock_transfers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Real-time tracking events for transfers';

-- ============================================================================
-- STEP 4: Insert default freight zones for existing outlets
-- ============================================================================
INSERT IGNORE INTO outlet_freight_zones (outlet_id, zone_name, region, base_freight_cost, per_kg_rate)
SELECT 
    id as outlet_id,
    'North Island' as zone_name,
    physical_state as region,
    15.00 as base_freight_cost,
    2.50 as per_kg_rate
FROM vend_outlets 
WHERE is_warehouse = 0;

-- ============================================================================
-- Verification Queries
-- ============================================================================
SELECT 'Migration Complete!' as status;
SELECT COUNT(*) as new_tables_created FROM information_schema.tables 
WHERE table_schema = 'jcepnzzkmj' 
AND table_name IN ('excess_stock_alerts', 'stock_velocity_tracking', 'freight_costs', 
                   'outlet_freight_zones', 'transfer_routes', 'transfer_boxes',
                   'transfer_rejections', 'transfer_tracking_events');
