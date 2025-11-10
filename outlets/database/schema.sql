-- ============================================================================
-- OUTLETS MODULE - DATABASE SCHEMA
-- ============================================================================
-- Purpose: Comprehensive management of all retail locations (19 stores)
-- Tracks: Addresses, landlords, photos, operating hours, closures, revenue
-- Version: 1.0.0
-- Date: 2025-11-05
-- ============================================================================

-- ============================================================================
-- TABLE 1: outlets (MASTER LOCATION TABLE)
-- ============================================================================
CREATE TABLE IF NOT EXISTS outlets (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    -- Basic Information
    outlet_name VARCHAR(100) NOT NULL COMMENT 'e.g., The Vape Shed - Queen Street',
    outlet_code VARCHAR(20) UNIQUE NOT NULL COMMENT 'e.g., VS-QST, VS-BOT',
    brand VARCHAR(50) DEFAULT 'The Vape Shed',

    -- Address
    street_address VARCHAR(255) NOT NULL,
    suburb VARCHAR(100) DEFAULT NULL,
    city VARCHAR(100) NOT NULL,
    region VARCHAR(100) DEFAULT NULL,
    postcode VARCHAR(10) DEFAULT NULL,
    country VARCHAR(50) DEFAULT 'New Zealand',

    -- Contact
    phone VARCHAR(20) DEFAULT NULL,
    email VARCHAR(100) DEFAULT NULL,

    -- Geographic
    latitude DECIMAL(10, 8) DEFAULT NULL,
    longitude DECIMAL(11, 8) DEFAULT NULL,
    google_maps_link TEXT DEFAULT NULL,

    -- Status
    status ENUM('active', 'inactive', 'closed_temporary', 'closed_permanent', 'coming_soon') DEFAULT 'active',
    opened_date DATE DEFAULT NULL,
    closed_date DATE DEFAULT NULL,

    -- Landlord Information
    landlord_name VARCHAR(255) DEFAULT NULL,
    landlord_contact VARCHAR(255) DEFAULT NULL,
    landlord_email VARCHAR(100) DEFAULT NULL,
    landlord_phone VARCHAR(20) DEFAULT NULL,
    property_manager VARCHAR(255) DEFAULT NULL,
    property_manager_contact VARCHAR(255) DEFAULT NULL,

    -- Lease Details
    lease_start_date DATE DEFAULT NULL,
    lease_end_date DATE DEFAULT NULL,
    lease_renewal_date DATE DEFAULT NULL,
    rent_amount DECIMAL(10,2) DEFAULT NULL COMMENT 'Monthly rent',
    rent_frequency ENUM('weekly', 'monthly', 'quarterly', 'annually') DEFAULT 'monthly',
    bond_amount DECIMAL(10,2) DEFAULT NULL,
    lease_type ENUM('fixed', 'periodic', 'franchise', 'owned') DEFAULT 'fixed',
    lease_notes TEXT DEFAULT NULL,

    -- Physical Details
    floor_area_sqm DECIMAL(8,2) DEFAULT NULL,
    storage_area_sqm DECIMAL(8,2) DEFAULT NULL,
    parking_spaces INT DEFAULT 0,
    has_street_frontage BOOLEAN DEFAULT TRUE,
    has_signage BOOLEAN DEFAULT TRUE,
    accessibility_features TEXT DEFAULT NULL COMMENT 'Wheelchair access, etc.',

    -- Store Manager
    manager_user_id INT UNSIGNED DEFAULT NULL COMMENT 'FK to users table',
    assistant_manager_user_id INT UNSIGNED DEFAULT NULL,

    -- Integration IDs
    lightspeed_outlet_id VARCHAR(100) DEFAULT NULL COMMENT 'Lightspeed/Vend outlet ID',
    xero_tracking_category_id VARCHAR(100) DEFAULT NULL COMMENT 'Xero tracking for expenses',

    -- Metadata
    notes TEXT DEFAULT NULL,
    internal_notes TEXT DEFAULT NULL COMMENT 'Private notes not visible to staff',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by INT UNSIGNED DEFAULT NULL,

    INDEX idx_outlet_code (outlet_code),
    INDEX idx_status (status),
    INDEX idx_city (city),
    INDEX idx_manager (manager_user_id),
    INDEX idx_lightspeed (lightspeed_outlet_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- TABLE 2: outlet_photos (STORE IMAGES)
-- ============================================================================
CREATE TABLE IF NOT EXISTS outlet_photos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    outlet_id INT UNSIGNED NOT NULL,

    -- Photo Details
    photo_type ENUM('exterior', 'interior', 'product_display', 'staff_area', 'signage', 'other') NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_size_kb INT UNSIGNED DEFAULT NULL,
    mime_type VARCHAR(50) DEFAULT 'image/jpeg',

    -- Metadata
    title VARCHAR(255) DEFAULT NULL,
    description TEXT DEFAULT NULL,
    taken_date DATE DEFAULT NULL,
    is_primary BOOLEAN DEFAULT FALSE COMMENT 'Primary display photo',
    display_order INT DEFAULT 0,

    -- Upload Info
    uploaded_by INT UNSIGNED DEFAULT NULL,
    uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (outlet_id) REFERENCES outlets(id) ON DELETE CASCADE,
    INDEX idx_outlet (outlet_id),
    INDEX idx_type (photo_type),
    INDEX idx_primary (is_primary)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- TABLE 3: outlet_operating_hours (OPENING TIMES)
-- ============================================================================
CREATE TABLE IF NOT EXISTS outlet_operating_hours (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    outlet_id INT UNSIGNED NOT NULL,

    -- Day of week (1=Monday, 7=Sunday)
    day_of_week TINYINT NOT NULL COMMENT '1=Mon, 2=Tue, 3=Wed, 4=Thu, 5=Fri, 6=Sat, 7=Sun',

    -- Hours
    opens_at TIME NOT NULL COMMENT 'e.g., 09:00:00',
    closes_at TIME NOT NULL COMMENT 'e.g., 18:00:00',
    is_closed BOOLEAN DEFAULT FALSE COMMENT 'Closed on this day',

    -- Special hours
    is_default BOOLEAN DEFAULT TRUE,
    effective_from DATE DEFAULT NULL COMMENT 'For temporary hour changes',
    effective_until DATE DEFAULT NULL,

    notes VARCHAR(255) DEFAULT NULL COMMENT 'e.g., "Christmas hours"',

    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (outlet_id) REFERENCES outlets(id) ON DELETE CASCADE,
    INDEX idx_outlet (outlet_id),
    INDEX idx_day (day_of_week),
    INDEX idx_default (is_default)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- TABLE 4: outlet_closure_history (TEMPORARY CLOSURES)
-- ============================================================================
CREATE TABLE IF NOT EXISTS outlet_closure_history (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    outlet_id INT UNSIGNED NOT NULL,

    -- Closure Details
    closure_type ENUM('planned', 'emergency', 'maintenance', 'weather', 'staffing', 'other') NOT NULL,
    reason TEXT NOT NULL,

    -- Timing
    closed_from DATETIME NOT NULL,
    closed_until DATETIME NOT NULL,
    actual_reopened_at DATETIME DEFAULT NULL,

    -- Impact
    revenue_loss_estimate DECIMAL(10,2) DEFAULT NULL COMMENT 'Estimated lost revenue',
    was_notified_to_customers BOOLEAN DEFAULT FALSE,
    notification_method VARCHAR(255) DEFAULT NULL COMMENT 'e.g., Facebook, email, signage',

    -- Resolution
    resolved BOOLEAN DEFAULT FALSE,
    resolution_notes TEXT DEFAULT NULL,

    created_by INT UNSIGNED DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (outlet_id) REFERENCES outlets(id) ON DELETE CASCADE,
    INDEX idx_outlet (outlet_id),
    INDEX idx_dates (closed_from, closed_until),
    INDEX idx_type (closure_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- TABLE 5: outlet_revenue_snapshots (DAILY REVENUE TRACKING)
-- ============================================================================
CREATE TABLE IF NOT EXISTS outlet_revenue_snapshots (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    outlet_id INT UNSIGNED NOT NULL,

    -- Period
    snapshot_date DATE NOT NULL,
    period_type ENUM('daily', 'weekly', 'monthly') DEFAULT 'daily',

    -- Revenue Metrics
    total_sales DECIMAL(12,2) DEFAULT 0.00,
    total_transactions INT DEFAULT 0,
    average_transaction_value DECIMAL(10,2) DEFAULT 0.00,

    -- Traffic
    customer_count INT DEFAULT NULL COMMENT 'Foot traffic if tracked',
    conversion_rate DECIMAL(5,2) DEFAULT NULL COMMENT 'Transactions / Visitors %',

    -- Product Mix
    nicotine_sales DECIMAL(10,2) DEFAULT 0.00,
    hardware_sales DECIMAL(10,2) DEFAULT 0.00,
    accessories_sales DECIMAL(10,2) DEFAULT 0.00,
    other_sales DECIMAL(10,2) DEFAULT 0.00,

    -- Comparisons
    vs_yesterday_pct DECIMAL(6,2) DEFAULT NULL,
    vs_last_week_pct DECIMAL(6,2) DEFAULT NULL,
    vs_last_month_pct DECIMAL(6,2) DEFAULT NULL,
    vs_last_year_pct DECIMAL(6,2) DEFAULT NULL,

    -- Source
    data_source ENUM('lightspeed', 'manual', 'import', 'calculated') DEFAULT 'lightspeed',
    lightspeed_sync_at DATETIME DEFAULT NULL,

    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uk_outlet_date (outlet_id, snapshot_date, period_type),
    FOREIGN KEY (outlet_id) REFERENCES outlets(id) ON DELETE CASCADE,
    INDEX idx_outlet (outlet_id),
    INDEX idx_date (snapshot_date),
    INDEX idx_period (period_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- TABLE 6: outlet_performance_metrics (KPIs & BENCHMARKS)
-- ============================================================================
CREATE TABLE IF NOT EXISTS outlet_performance_metrics (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    outlet_id INT UNSIGNED NOT NULL,

    -- Period
    metric_date DATE NOT NULL,
    metric_period ENUM('daily', 'weekly', 'monthly', 'quarterly', 'yearly') DEFAULT 'daily',

    -- Financial KPIs
    revenue DECIMAL(12,2) DEFAULT 0.00,
    cogs DECIMAL(12,2) DEFAULT 0.00 COMMENT 'Cost of goods sold',
    gross_profit DECIMAL(12,2) DEFAULT 0.00,
    gross_margin_pct DECIMAL(5,2) DEFAULT 0.00,

    -- Operational KPIs
    staff_hours DECIMAL(8,2) DEFAULT 0.00,
    staff_cost DECIMAL(10,2) DEFAULT 0.00,
    labor_cost_pct DECIMAL(5,2) DEFAULT 0.00 COMMENT '% of revenue',

    -- Efficiency KPIs
    revenue_per_sqm DECIMAL(10,2) DEFAULT NULL,
    revenue_per_staff_hour DECIMAL(10,2) DEFAULT NULL,
    transactions_per_hour DECIMAL(8,2) DEFAULT NULL,

    -- Quality KPIs
    customer_satisfaction_score DECIMAL(3,2) DEFAULT NULL COMMENT 'Out of 5',
    online_review_rating DECIMAL(3,2) DEFAULT NULL COMMENT 'Google/Facebook rating',
    complaint_count INT DEFAULT 0,

    -- Inventory KPIs
    stock_turn_days DECIMAL(6,2) DEFAULT NULL COMMENT 'Days to turn inventory',
    stockout_count INT DEFAULT 0,
    shrinkage_pct DECIMAL(5,2) DEFAULT NULL,

    -- Benchmark Position
    rank_revenue INT DEFAULT NULL COMMENT 'Rank among all outlets',
    rank_profit INT DEFAULT NULL,
    rank_growth INT DEFAULT NULL,

    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uk_outlet_metric_date (outlet_id, metric_date, metric_period),
    FOREIGN KEY (outlet_id) REFERENCES outlets(id) ON DELETE CASCADE,
    INDEX idx_outlet (outlet_id),
    INDEX idx_date (metric_date),
    INDEX idx_period (metric_period)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- TABLE 7: outlet_documents (LEASE AGREEMENTS, CERTIFICATES, ETC)
-- ============================================================================
CREATE TABLE IF NOT EXISTS outlet_documents (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    outlet_id INT UNSIGNED NOT NULL,

    -- Document Details
    document_type ENUM('lease_agreement', 'insurance', 'compliance_certificate', 'floor_plan', 'license', 'other') NOT NULL,
    document_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_size_kb INT UNSIGNED DEFAULT NULL,
    mime_type VARCHAR(50) DEFAULT NULL,

    -- Metadata
    description TEXT DEFAULT NULL,
    expiry_date DATE DEFAULT NULL COMMENT 'For renewals/compliance',
    reminder_days INT DEFAULT 30 COMMENT 'Days before expiry to remind',

    -- Visibility
    is_confidential BOOLEAN DEFAULT FALSE,

    uploaded_by INT UNSIGNED DEFAULT NULL,
    uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (outlet_id) REFERENCES outlets(id) ON DELETE CASCADE,
    INDEX idx_outlet (outlet_id),
    INDEX idx_type (document_type),
    INDEX idx_expiry (expiry_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- TABLE 8: outlet_maintenance_log (REPAIRS, UPGRADES, ISSUES)
-- ============================================================================
CREATE TABLE IF NOT EXISTS outlet_maintenance_log (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    outlet_id INT UNSIGNED NOT NULL,

    -- Issue Details
    issue_type ENUM('hvac', 'plumbing', 'electrical', 'structural', 'equipment', 'cleaning', 'other') NOT NULL,
    priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,

    -- Status
    status ENUM('reported', 'acknowledged', 'in_progress', 'completed', 'cancelled') DEFAULT 'reported',

    -- Timing
    reported_at DATETIME NOT NULL,
    acknowledged_at DATETIME DEFAULT NULL,
    completed_at DATETIME DEFAULT NULL,

    -- Cost
    estimated_cost DECIMAL(10,2) DEFAULT NULL,
    actual_cost DECIMAL(10,2) DEFAULT NULL,

    -- People
    reported_by INT UNSIGNED DEFAULT NULL,
    assigned_to VARCHAR(255) DEFAULT NULL COMMENT 'Contractor or staff',
    completed_by VARCHAR(255) DEFAULT NULL,

    -- Resolution
    resolution_notes TEXT DEFAULT NULL,

    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (outlet_id) REFERENCES outlets(id) ON DELETE CASCADE,
    INDEX idx_outlet (outlet_id),
    INDEX idx_status (status),
    INDEX idx_priority (priority)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- VIEWS: ANALYTICS
-- ============================================================================

-- View: Outlet Overview with Latest Metrics
CREATE OR REPLACE VIEW vw_outlets_overview AS
SELECT
    o.id,
    o.outlet_name,
    o.outlet_code,
    o.city,
    o.status,
    o.manager_user_id,
    CONCAT(u.first_name, ' ', u.last_name) as manager_name,

    -- Latest revenue (last 30 days)
    SUM(CASE WHEN ors.snapshot_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        THEN ors.total_sales ELSE 0 END) as revenue_last_30_days,

    -- Latest daily revenue
    (SELECT total_sales FROM outlet_revenue_snapshots
     WHERE outlet_id = o.id
     ORDER BY snapshot_date DESC LIMIT 1) as revenue_yesterday,

    -- Photo count
    (SELECT COUNT(*) FROM outlet_photos WHERE outlet_id = o.id) as photo_count,

    -- Primary photo
    (SELECT file_path FROM outlet_photos
     WHERE outlet_id = o.id AND is_primary = TRUE LIMIT 1) as primary_photo,

    o.landlord_name,
    o.lease_end_date,
    o.rent_amount,
    o.opened_date

FROM outlets o
LEFT JOIN users u ON o.manager_user_id = u.id
LEFT JOIN outlet_revenue_snapshots ors ON o.id = ors.outlet_id
GROUP BY o.id;

-- ============================================================================
-- SEED DATA: Insert 19 Outlet Locations
-- ============================================================================

INSERT INTO outlets (outlet_name, outlet_code, city, status, opened_date) VALUES
('The Vape Shed - Queen Street', 'VS-QST', 'Auckland CBD', 'active', '2015-06-01'),
('The Vape Shed - Botany', 'VS-BOT', 'Auckland', 'active', '2016-03-15'),
('The Vape Shed - Manukau', 'VS-MKU', 'Auckland', 'active', '2016-08-01'),
('The Vape Shed - Albany', 'VS-ALB', 'Auckland', 'active', '2017-01-10'),
('The Vape Shed - Henderson', 'VS-HEN', 'Auckland', 'active', '2017-05-20'),
('The Vape Shed - Papakura', 'VS-PAP', 'Auckland', 'active', '2017-11-01'),
('The Vape Shed - Takapuna', 'VS-TAK', 'Auckland', 'active', '2018-02-14'),
('The Vape Shed - Hamilton', 'VS-HAM', 'Hamilton', 'active', '2018-06-01'),
('The Vape Shed - Tauranga', 'VS-TAU', 'Tauranga', 'active', '2018-09-15'),
('The Vape Shed - Rotorua', 'VS-ROT', 'Rotorua', 'active', '2019-01-20'),
('The Vape Shed - Palmerston North', 'VS-PMR', 'Palmerston North', 'active', '2019-05-01'),
('The Vape Shed - Wellington', 'VS-WLG', 'Wellington', 'active', '2019-08-10'),
('The Vape Shed - Lower Hutt', 'VS-LHT', 'Wellington', 'active', '2020-01-15'),
('The Vape Shed - Christchurch', 'VS-CHC', 'Christchurch', 'active', '2020-06-01'),
('The Vape Shed - Dunedin', 'VS-DUD', 'Dunedin', 'active', '2020-10-20'),
('The Vape Shed - Invercargill', 'VS-IVC', 'Invercargill', 'active', '2021-03-01'),
('The Vape Shed - New Plymouth', 'VS-NPL', 'New Plymouth', 'active', '2021-07-15')
ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP;

-- ============================================================================
-- COMPLETE
-- ============================================================================
