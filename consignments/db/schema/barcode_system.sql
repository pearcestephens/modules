-- ============================================================================
-- BARCODE SCANNING SYSTEM - COMPLETE DATABASE SCHEMA
-- ============================================================================
-- Purpose: Track barcode scans, configuration, and user preferences
-- Features: Multi-level settings (Global/Outlet/User), audit trail, analytics
-- Created: 2025-11-04
-- ============================================================================

-- -----------------------------------------------------------------------------
-- 1. BARCODE SCANS (Complete scan history with analytics)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS BARCODE_SCANS (
  id INT PRIMARY KEY AUTO_INCREMENT,

  -- Context
  transfer_id INT NULL COMMENT 'Link to stock transfer if applicable',
  consignment_id INT NULL COMMENT 'Link to consignment if applicable',
  purchase_order_id INT NULL COMMENT 'Link to PO if applicable',

  -- Scan data
  barcode_value VARCHAR(255) NOT NULL COMMENT 'Raw barcode string',
  barcode_format ENUM('EAN13','UPC','Code128','Code39','ITF','QR','DataMatrix','CUSTOM','UNKNOWN') DEFAULT 'UNKNOWN',
  scan_method ENUM('usb_scanner','camera','manual_entry') DEFAULT 'manual_entry',

  -- Product match
  vend_product_id VARCHAR(36) NULL COMMENT 'Matched product UUID',
  sku VARCHAR(100) NULL COMMENT 'Matched SKU',
  product_name VARCHAR(255) NULL COMMENT 'Product name at time of scan',
  match_confidence DECIMAL(5,2) NULL COMMENT 'Match confidence % (0-100)',

  -- Scan outcome
  scan_result ENUM('success','not_found','duplicate','quantity_exceeded','blocked') DEFAULT 'success',
  qty_scanned INT DEFAULT 1 COMMENT 'Quantity added/processed',
  audio_feedback ENUM('tone1','tone2','tone3','none') DEFAULT 'tone1',

  -- User & timing
  user_id INT NULL COMMENT 'User who performed scan',
  outlet_id INT NULL COMMENT 'Outlet where scan occurred',
  scan_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  scan_duration_ms INT NULL COMMENT 'Time from scan to validation (ms)',

  -- Device info
  device_type VARCHAR(100) NULL COMMENT 'Scanner model or camera type',
  user_agent TEXT NULL COMMENT 'Browser user agent',

  -- Metadata
  metadata JSON NULL COMMENT 'Additional context (expected_qty, notes, etc)',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

  INDEX idx_transfer (transfer_id),
  INDEX idx_consignment (consignment_id),
  INDEX idx_po (purchase_order_id),
  INDEX idx_barcode (barcode_value),
  INDEX idx_product (vend_product_id),
  INDEX idx_user (user_id),
  INDEX idx_outlet (outlet_id),
  INDEX idx_timestamp (scan_timestamp),
  INDEX idx_result (scan_result)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Complete barcode scan history with analytics';

-- -----------------------------------------------------------------------------
-- 2. BARCODE CONFIGURATION (Global & per-outlet settings)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS BARCODE_CONFIGURATION (
  id INT PRIMARY KEY AUTO_INCREMENT,

  -- Scope (NULL outlet_id = global default)
  outlet_id INT NULL COMMENT 'NULL = global default, INT = specific outlet',

  -- Scanner enable/disable
  enabled BOOLEAN DEFAULT 1 COMMENT 'Master enable/disable',
  usb_scanner_enabled BOOLEAN DEFAULT 1 COMMENT 'Enable USB hardware scanners',
  camera_scanner_enabled BOOLEAN DEFAULT 1 COMMENT 'Enable camera-based scanning',
  manual_entry_enabled BOOLEAN DEFAULT 1 COMMENT 'Allow manual barcode entry',

  -- Scanner behavior
  scan_mode ENUM('auto','usb_only','camera_only','manual_only') DEFAULT 'auto' COMMENT 'Scanner detection mode',
  require_exact_match BOOLEAN DEFAULT 0 COMMENT 'Require exact barcode match or allow fuzzy?',
  allow_duplicate_scans BOOLEAN DEFAULT 1 COMMENT 'Allow scanning same item multiple times?',
  block_on_qty_exceed BOOLEAN DEFAULT 0 COMMENT 'Block scan if qty exceeds expected?',

  -- Audio settings
  audio_enabled BOOLEAN DEFAULT 1 COMMENT 'Enable audio feedback tones',
  audio_volume DECIMAL(3,2) DEFAULT 0.5 COMMENT 'Volume 0.0-1.0',
  tone1_frequency INT DEFAULT 1200 COMMENT 'Success tone (Hz)',
  tone2_frequency INT DEFAULT 800 COMMENT 'Warning tone (Hz)',
  tone3_frequency INT DEFAULT 400 COMMENT 'Error tone (Hz)',
  tone_duration_ms INT DEFAULT 100 COMMENT 'Tone duration',

  -- Visual feedback
  visual_feedback_enabled BOOLEAN DEFAULT 1 COMMENT 'Show colored flash on scan',
  success_color VARCHAR(7) DEFAULT '#28a745' COMMENT 'Success flash color',
  warning_color VARCHAR(7) DEFAULT '#ffc107' COMMENT 'Warning flash color',
  error_color VARCHAR(7) DEFAULT '#dc3545' COMMENT 'Error flash color',
  flash_duration_ms INT DEFAULT 500 COMMENT 'Flash duration',

  -- Format preferences
  format_preference JSON NULL COMMENT 'Array of preferred formats in order',
  custom_barcode_pattern VARCHAR(255) NULL COMMENT 'Regex for custom barcode validation',

  -- Performance
  scan_cooldown_ms INT DEFAULT 100 COMMENT 'Min time between scans (prevent duplicates)',
  camera_fps INT DEFAULT 10 COMMENT 'Camera scan rate (frames per second)',
  camera_resolution VARCHAR(20) DEFAULT '640x480' COMMENT 'Camera resolution',

  -- Logging
  log_all_scans BOOLEAN DEFAULT 1 COMMENT 'Log successful scans',
  log_failed_scans BOOLEAN DEFAULT 1 COMMENT 'Log failed scan attempts',
  log_retention_days INT DEFAULT 90 COMMENT 'Days to retain scan logs',

  -- Metadata
  notes TEXT NULL COMMENT 'Configuration notes',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  created_by INT NULL COMMENT 'User who created config',
  updated_by INT NULL COMMENT 'User who last updated',

  UNIQUE KEY unique_outlet (outlet_id),
  INDEX idx_enabled (enabled),
  INDEX idx_outlet (outlet_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Barcode scanner configuration (global + per-outlet)';

-- -----------------------------------------------------------------------------
-- 3. BARCODE USER PREFERENCES (Per-user overrides)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS BARCODE_USER_PREFERENCES (
  id INT PRIMARY KEY AUTO_INCREMENT,

  user_id INT NOT NULL COMMENT 'User ID',
  outlet_id INT NULL COMMENT 'Specific outlet or NULL for all outlets',

  -- User overrides (NULL = inherit from outlet/global config)
  usb_scanner_enabled BOOLEAN NULL,
  camera_scanner_enabled BOOLEAN NULL,
  manual_entry_enabled BOOLEAN NULL,

  audio_enabled BOOLEAN NULL,
  audio_volume DECIMAL(3,2) NULL,

  visual_feedback_enabled BOOLEAN NULL,

  preferred_scan_method ENUM('auto','usb','camera','manual') DEFAULT 'auto',

  -- Device associations
  preferred_device VARCHAR(255) NULL COMMENT 'Preferred scanner device ID',
  device_history JSON NULL COMMENT 'Recently used devices',

  -- Stats & analytics
  total_scans INT DEFAULT 0,
  successful_scans INT DEFAULT 0,
  failed_scans INT DEFAULT 0,
  avg_scan_speed_ms INT NULL,
  last_scan_at TIMESTAMP NULL,

  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  UNIQUE KEY unique_user_outlet (user_id, outlet_id),
  INDEX idx_user (user_id),
  INDEX idx_outlet (outlet_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Per-user barcode scanner preferences and stats';

-- -----------------------------------------------------------------------------
-- 4. BARCODE AUDIT LOG (Track all configuration changes)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS BARCODE_AUDIT_LOG (
  id INT PRIMARY KEY AUTO_INCREMENT,

  action ENUM('config_created','config_updated','config_deleted','scanner_enabled','scanner_disabled','setting_changed') NOT NULL,

  target_type ENUM('global','outlet','user') NOT NULL,
  target_id INT NULL COMMENT 'Outlet ID or User ID',

  changed_by INT NULL COMMENT 'User who made change',

  field_name VARCHAR(100) NULL COMMENT 'Setting that changed',
  old_value TEXT NULL,
  new_value TEXT NULL,

  ip_address VARCHAR(45) NULL,
  user_agent TEXT NULL,

  metadata JSON NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

  INDEX idx_action (action),
  INDEX idx_target (target_type, target_id),
  INDEX idx_user (changed_by),
  INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Audit trail for all scanner configuration changes';

-- -----------------------------------------------------------------------------
-- 5. BARCODE ANALYTICS (Aggregated stats)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS BARCODE_ANALYTICS (
  id INT PRIMARY KEY AUTO_INCREMENT,

  date DATE NOT NULL,
  outlet_id INT NULL COMMENT 'NULL = global stats',
  user_id INT NULL COMMENT 'NULL = all users',

  total_scans INT DEFAULT 0,
  successful_scans INT DEFAULT 0,
  failed_scans INT DEFAULT 0,

  usb_scans INT DEFAULT 0,
  camera_scans INT DEFAULT 0,
  manual_scans INT DEFAULT 0,

  avg_scan_duration_ms DECIMAL(8,2) NULL,

  unique_products_scanned INT DEFAULT 0,
  unique_barcodes_scanned INT DEFAULT 0,

  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

  UNIQUE KEY unique_date_outlet_user (date, outlet_id, user_id),
  INDEX idx_date (date),
  INDEX idx_outlet (outlet_id),
  INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Daily aggregated barcode scanning statistics';

-- -----------------------------------------------------------------------------
-- 6. Insert default global configuration
-- -----------------------------------------------------------------------------
INSERT INTO BARCODE_CONFIGURATION (
  outlet_id,
  enabled,
  usb_scanner_enabled,
  camera_scanner_enabled,
  manual_entry_enabled,
  scan_mode,
  audio_enabled,
  audio_volume,
  visual_feedback_enabled,
  notes
) VALUES (
  NULL, -- Global default
  1,    -- Enabled
  1,    -- USB enabled
  1,    -- Camera enabled
  1,    -- Manual entry enabled
  'auto', -- Auto-detect scanner type
  1,    -- Audio enabled
  0.50, -- 50% volume
  1,    -- Visual feedback enabled
  'Global default configuration - all scanners enabled'
) ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP;

-- -----------------------------------------------------------------------------
-- 7. Create views for easy access
-- -----------------------------------------------------------------------------

-- View: Active configuration per outlet (with global fallback)
CREATE OR REPLACE VIEW v_barcode_config_active AS
SELECT
  COALESCE(oc.outlet_id, gc.outlet_id) as outlet_id,
  COALESCE(oc.enabled, gc.enabled) as enabled,
  COALESCE(oc.usb_scanner_enabled, gc.usb_scanner_enabled) as usb_scanner_enabled,
  COALESCE(oc.camera_scanner_enabled, gc.camera_scanner_enabled) as camera_scanner_enabled,
  COALESCE(oc.manual_entry_enabled, gc.manual_entry_enabled) as manual_entry_enabled,
  COALESCE(oc.scan_mode, gc.scan_mode) as scan_mode,
  COALESCE(oc.audio_enabled, gc.audio_enabled) as audio_enabled,
  COALESCE(oc.audio_volume, gc.audio_volume) as audio_volume,
  COALESCE(oc.tone1_frequency, gc.tone1_frequency) as tone1_frequency,
  COALESCE(oc.tone2_frequency, gc.tone2_frequency) as tone2_frequency,
  COALESCE(oc.tone3_frequency, gc.tone3_frequency) as tone3_frequency,
  COALESCE(oc.visual_feedback_enabled, gc.visual_feedback_enabled) as visual_feedback_enabled,
  COALESCE(oc.success_color, gc.success_color) as success_color,
  COALESCE(oc.warning_color, gc.warning_color) as warning_color,
  COALESCE(oc.error_color, gc.error_color) as error_color
FROM
  (SELECT * FROM BARCODE_CONFIGURATION WHERE outlet_id IS NULL) gc
LEFT JOIN
  BARCODE_CONFIGURATION oc ON oc.outlet_id IS NOT NULL;

-- View: Scan statistics per user
CREATE OR REPLACE VIEW v_barcode_user_stats AS
SELECT
  user_id,
  outlet_id,
  COUNT(*) as total_scans,
  SUM(CASE WHEN scan_result = 'success' THEN 1 ELSE 0 END) as successful_scans,
  SUM(CASE WHEN scan_result != 'success' THEN 1 ELSE 0 END) as failed_scans,
  AVG(scan_duration_ms) as avg_scan_duration_ms,
  COUNT(DISTINCT vend_product_id) as unique_products,
  MAX(scan_timestamp) as last_scan_at
FROM BARCODE_SCANS
WHERE user_id IS NOT NULL
GROUP BY user_id, outlet_id;

-- View: Daily scan summary
CREATE OR REPLACE VIEW v_barcode_daily_summary AS
SELECT
  DATE(scan_timestamp) as scan_date,
  outlet_id,
  COUNT(*) as total_scans,
  SUM(CASE WHEN scan_result = 'success' THEN 1 ELSE 0 END) as successful,
  SUM(CASE WHEN scan_method = 'usb_scanner' THEN 1 ELSE 0 END) as usb_scans,
  SUM(CASE WHEN scan_method = 'camera' THEN 1 ELSE 0 END) as camera_scans,
  SUM(CASE WHEN scan_method = 'manual_entry' THEN 1 ELSE 0 END) as manual_scans,
  AVG(scan_duration_ms) as avg_duration_ms,
  COUNT(DISTINCT user_id) as unique_users,
  COUNT(DISTINCT vend_product_id) as unique_products
FROM BARCODE_SCANS
GROUP BY DATE(scan_timestamp), outlet_id;

-- -----------------------------------------------------------------------------
-- COMPLETE - Schema ready for barcode scanning system
-- -----------------------------------------------------------------------------
