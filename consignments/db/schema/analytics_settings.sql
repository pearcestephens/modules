-- =====================================================
-- ANALYTICS SETTINGS & CUSTOMIZATION SYSTEM
-- =====================================================
-- Allows EVERYTHING to be customized and turned on/off
-- at global, outlet, user, and transfer levels
-- =====================================================

-- =====================================================
-- 1. GLOBAL SETTINGS (System Defaults)
-- =====================================================
CREATE TABLE IF NOT EXISTS ANALYTICS_GLOBAL_SETTINGS (
    setting_id INT AUTO_INCREMENT PRIMARY KEY,
    category ENUM('fraud_detection', 'performance_tracking', 'gamification', 'photo_requirements', 'notifications', 'leaderboards', 'reviews', 'ui_features') NOT NULL,
    setting_key VARCHAR(100) NOT NULL,
    setting_value TEXT,
    data_type ENUM('boolean', 'integer', 'float', 'string', 'json') DEFAULT 'boolean',
    description TEXT,
    is_enabled BOOLEAN DEFAULT TRUE,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    updated_by INT,
    UNIQUE KEY unique_setting (category, setting_key),
    INDEX idx_category (category),
    INDEX idx_enabled (is_enabled)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 2. OUTLET SETTINGS (Override Global per Store)
-- =====================================================
CREATE TABLE IF NOT EXISTS ANALYTICS_OUTLET_SETTINGS (
    outlet_setting_id INT AUTO_INCREMENT PRIMARY KEY,
    outlet_id VARCHAR(50) NOT NULL,
    category ENUM('fraud_detection', 'performance_tracking', 'gamification', 'photo_requirements', 'notifications', 'leaderboards', 'reviews', 'ui_features') NOT NULL,
    setting_key VARCHAR(100) NOT NULL,
    setting_value TEXT,
    inherit_from_global BOOLEAN DEFAULT FALSE,
    is_enabled BOOLEAN DEFAULT TRUE,
    notes TEXT COMMENT 'Why this outlet has custom settings',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    updated_by INT,
    UNIQUE KEY unique_outlet_setting (outlet_id, category, setting_key),
    INDEX idx_outlet (outlet_id),
    INDEX idx_enabled (is_enabled),
    FOREIGN KEY (outlet_id) REFERENCES outlets(outlet_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 3. USER PREFERENCES (Personal Customization)
-- =====================================================
CREATE TABLE IF NOT EXISTS ANALYTICS_USER_PREFERENCES (
    user_preference_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    category ENUM('fraud_detection', 'performance_tracking', 'gamification', 'photo_requirements', 'notifications', 'leaderboards', 'reviews', 'ui_features') NOT NULL,
    setting_key VARCHAR(100) NOT NULL,
    setting_value TEXT,
    inherit_from_outlet BOOLEAN DEFAULT FALSE,
    is_enabled BOOLEAN DEFAULT TRUE,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user_setting (user_id, category, setting_key),
    INDEX idx_user (user_id),
    INDEX idx_enabled (is_enabled),
    FOREIGN KEY (user_id) REFERENCES staff_accounts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 4. TRANSFER OVERRIDES (One-time Adjustments)
-- =====================================================
CREATE TABLE IF NOT EXISTS ANALYTICS_TRANSFER_OVERRIDES (
    override_id INT AUTO_INCREMENT PRIMARY KEY,
    transfer_id INT NOT NULL,
    category ENUM('fraud_detection', 'performance_tracking', 'gamification', 'photo_requirements', 'notifications', 'leaderboards', 'reviews', 'ui_features') NOT NULL,
    setting_key VARCHAR(100) NOT NULL,
    setting_value TEXT,
    override_reason TEXT,
    approved_by INT COMMENT 'Supervisor/Manager who approved',
    approved_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by INT,
    INDEX idx_transfer (transfer_id),
    INDEX idx_approved (approved_by),
    FOREIGN KEY (created_by) REFERENCES staff_accounts(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 5. FEATURE COMPLEXITY PRESETS
-- =====================================================
CREATE TABLE IF NOT EXISTS ANALYTICS_COMPLEXITY_PRESETS (
    preset_id INT AUTO_INCREMENT PRIMARY KEY,
    preset_name VARCHAR(100) NOT NULL UNIQUE,
    preset_level ENUM('very_basic', 'basic', 'intermediate', 'advanced', 'very_advanced', 'expert') NOT NULL,
    description TEXT,
    settings_json JSON COMMENT 'All settings for this preset',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_active BOOLEAN DEFAULT TRUE,
    INDEX idx_level (preset_level)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- INSERT DEFAULT GLOBAL SETTINGS
-- =====================================================

-- FRAUD DETECTION SETTINGS
INSERT INTO ANALYTICS_GLOBAL_SETTINGS (category, setting_key, setting_value, data_type, description) VALUES
('fraud_detection', 'enable_fraud_detection', 'true', 'boolean', 'Master switch for fraud detection system'),
('fraud_detection', 'auto_flag_suspicious', 'true', 'boolean', 'Automatically flag suspicious scans'),
('fraud_detection', 'fraud_score_threshold', '50', 'integer', 'Score above which scans are flagged (0-100)'),
('fraud_detection', 'require_supervisor_approval', 'false', 'boolean', 'Require supervisor to approve flagged transfers'),
('fraud_detection', 'block_invalid_barcodes', 'true', 'boolean', 'Prevent scanning known invalid patterns (9999, 09, etc.)'),
('fraud_detection', 'enable_timing_checks', 'true', 'boolean', 'Check for too-fast scanning'),
('fraud_detection', 'min_scan_interval_ms', '100', 'integer', 'Minimum milliseconds between scans'),
('fraud_detection', 'enable_duplicate_detection', 'true', 'boolean', 'Detect duplicate scans'),
('fraud_detection', 'duplicate_window_seconds', '5', 'integer', 'Time window for duplicate detection'),
('fraud_detection', 'enable_pattern_detection', 'true', 'boolean', 'Detect sequential patterns (12345, etc.)'),
('fraud_detection', 'real_time_alerts', 'false', 'boolean', 'Show alerts during scanning (vs end of session)'),
('fraud_detection', 'alert_sound', 'true', 'boolean', 'Play warning sound for suspicious scans');

-- PERFORMANCE TRACKING SETTINGS
INSERT INTO ANALYTICS_GLOBAL_SETTINGS (category, setting_key, setting_value, data_type, description) VALUES
('performance_tracking', 'enable_performance_tracking', 'true', 'boolean', 'Track scanning speed and accuracy'),
('performance_tracking', 'track_scan_speed', 'true', 'boolean', 'Track scans per minute'),
('performance_tracking', 'track_accuracy', 'true', 'boolean', 'Calculate accuracy percentage'),
('performance_tracking', 'accuracy_target', '95', 'integer', 'Target accuracy percentage'),
('performance_tracking', 'show_live_stats', 'true', 'boolean', 'Display stats during scanning'),
('performance_tracking', 'record_personal_bests', 'true', 'boolean', 'Track personal best records'),
('performance_tracking', 'daily_aggregation', 'true', 'boolean', 'Aggregate daily performance'),
('performance_tracking', 'weekly_aggregation', 'true', 'boolean', 'Aggregate weekly performance');

-- GAMIFICATION SETTINGS
INSERT INTO ANALYTICS_GLOBAL_SETTINGS (category, setting_key, setting_value, data_type, description) VALUES
('gamification', 'enable_gamification', 'true', 'boolean', 'Enable achievement and badge system'),
('gamification', 'enable_achievements', 'true', 'boolean', 'Award achievements/badges'),
('gamification', 'show_achievement_notifications', 'true', 'boolean', 'Show popup when achievement earned'),
('gamification', 'enable_points_system', 'true', 'boolean', 'Award points for achievements'),
('gamification', 'enable_end_session_summary', 'true', 'boolean', 'Show performance summary after completing'),
('gamification', 'summary_includes_rank', 'true', 'boolean', 'Show user rank in summary'),
('gamification', 'summary_includes_achievements', 'true', 'boolean', 'Show new achievements in summary'),
('gamification', 'summary_includes_tips', 'true', 'boolean', 'Show improvement tips'),
('gamification', 'animation_style', 'full', 'string', 'Animation style: none, minimal, standard, full');

-- LEADERBOARD SETTINGS
INSERT INTO ANALYTICS_GLOBAL_SETTINGS (category, setting_key, setting_value, data_type, description) VALUES
('leaderboards', 'enable_leaderboards', 'true', 'boolean', 'Enable leaderboard system'),
('leaderboards', 'show_daily_leaderboard', 'true', 'boolean', 'Daily rankings'),
('leaderboards', 'show_weekly_leaderboard', 'true', 'boolean', 'Weekly rankings'),
('leaderboards', 'show_monthly_leaderboard', 'true', 'boolean', 'Monthly rankings'),
('leaderboards', 'show_alltime_leaderboard', 'true', 'boolean', 'All-time rankings'),
('leaderboards', 'outlet_leaderboard', 'true', 'boolean', 'Show outlet-level leaderboard'),
('leaderboards', 'company_leaderboard', 'true', 'boolean', 'Show company-wide leaderboard'),
('leaderboards', 'show_top_count', '10', 'integer', 'Number of top performers to display'),
('leaderboards', 'show_user_rank', 'true', 'boolean', 'Always show current user rank'),
('leaderboards', 'anonymous_mode', 'false', 'boolean', 'Hide names, show only positions');

-- PHOTO REQUIREMENTS SETTINGS
INSERT INTO ANALYTICS_GLOBAL_SETTINGS (category, setting_key, setting_value, data_type, description) VALUES
('photo_requirements', 'enable_photo_requirements', 'true', 'boolean', 'Require photos for receiving'),
('photo_requirements', 'require_invoice_photo', 'true', 'boolean', 'Require invoice photo'),
('photo_requirements', 'require_packing_slip_photo', 'true', 'boolean', 'Require packing slip photo'),
('photo_requirements', 'require_receipt_photo', 'false', 'boolean', 'Require receipt photo'),
('photo_requirements', 'require_damage_photos', 'true', 'boolean', 'Require photos if damage reported'),
('photo_requirements', 'block_completion_without_photos', 'true', 'boolean', 'Hard block vs soft warning'),
('photo_requirements', 'allow_supervisor_override', 'true', 'boolean', 'Allow supervisor to override photo requirement'),
('photo_requirements', 'photo_quality_min', 'medium', 'string', 'Minimum photo quality: low, medium, high'),
('photo_requirements', 'enable_qr_upload', 'true', 'boolean', 'Allow mobile upload via QR code'),
('photo_requirements', 'qr_session_timeout_minutes', '15', 'integer', 'QR upload window timeout');

-- NOTIFICATION SETTINGS
INSERT INTO ANALYTICS_GLOBAL_SETTINGS (category, setting_key, setting_value, data_type, description) VALUES
('notifications', 'enable_notifications', 'true', 'boolean', 'Master notification switch'),
('notifications', 'fraud_alert_notifications', 'true', 'boolean', 'Alert on suspicious scans'),
('notifications', 'achievement_notifications', 'true', 'boolean', 'Notify when achievement earned'),
('notifications', 'daily_summary_email', 'true', 'boolean', 'Send daily performance email'),
('notifications', 'weekly_summary_email', 'true', 'boolean', 'Send weekly performance email'),
('notifications', 'notification_method', 'in_app', 'string', 'Method: in_app, email, both, sms'),
('notifications', 'sound_notifications', 'true', 'boolean', 'Play notification sounds');

-- REVIEW SETTINGS
INSERT INTO ANALYTICS_GLOBAL_SETTINGS (category, setting_key, setting_value, data_type, description) VALUES
('reviews', 'enable_transfer_reviews', 'true', 'boolean', 'Allow receiving stores to review sending stores'),
('reviews', 'require_review', 'false', 'boolean', 'Require review before marking complete'),
('reviews', 'weekly_store_reports', 'true', 'boolean', 'Send weekly reports to sending stores'),
('reviews', 'include_receiver_feedback', 'true', 'boolean', 'Include feedback in reports'),
('reviews', 'flag_low_ratings', 'true', 'boolean', 'Flag stores with low ratings'),
('reviews', 'low_rating_threshold', '3', 'integer', 'Rating below this triggers flag (1-5)');

-- UI FEATURES SETTINGS
INSERT INTO ANALYTICS_GLOBAL_SETTINGS (category, setting_key, setting_value, data_type, description) VALUES
('ui_features', 'show_performance_dashboard', 'true', 'boolean', 'Show performance dashboard link'),
('ui_features', 'show_leaderboard_link', 'true', 'boolean', 'Show leaderboard link'),
('ui_features', 'show_achievements_page', 'true', 'boolean', 'Show achievements page'),
('ui_features', 'show_live_stats_widget', 'true', 'boolean', 'Show live stats during scanning'),
('ui_features', 'show_fraud_indicator', 'true', 'boolean', 'Show fraud score/status indicator'),
('ui_features', 'show_personal_best', 'true', 'boolean', 'Show personal best records'),
('ui_features', 'compact_mode', 'false', 'boolean', 'Compact UI for experienced users'),
('ui_features', 'color_scheme', 'default', 'string', 'UI color scheme'),
('ui_features', 'icon_set', 'default', 'string', 'Icon set to use'),
('ui_features', 'table_style', 'detailed', 'string', 'Table style: minimal, standard, detailed');

-- =====================================================
-- INSERT COMPLEXITY PRESETS
-- =====================================================

-- VERY BASIC PRESET (New users, minimal features)
INSERT INTO ANALYTICS_COMPLEXITY_PRESETS (preset_name, preset_level, description, settings_json) VALUES
('Very Basic', 'very_basic', 'Minimal features for new users - just scan and receive', JSON_OBJECT(
    'fraud_detection', JSON_OBJECT(
        'enable_fraud_detection', false,
        'block_invalid_barcodes', true
    ),
    'performance_tracking', JSON_OBJECT(
        'enable_performance_tracking', false
    ),
    'gamification', JSON_OBJECT(
        'enable_gamification', false,
        'enable_end_session_summary', false
    ),
    'leaderboards', JSON_OBJECT(
        'enable_leaderboards', false
    ),
    'photo_requirements', JSON_OBJECT(
        'enable_photo_requirements', true,
        'require_invoice_photo', false,
        'require_packing_slip_photo', true,
        'block_completion_without_photos', false
    ),
    'notifications', JSON_OBJECT(
        'enable_notifications', false
    ),
    'reviews', JSON_OBJECT(
        'enable_transfer_reviews', false
    ),
    'ui_features', JSON_OBJECT(
        'compact_mode', false,
        'show_live_stats_widget', false,
        'table_style', 'minimal'
    )
));

-- BASIC PRESET (Simple with essentials)
INSERT INTO ANALYTICS_COMPLEXITY_PRESETS (preset_name, preset_level, description, settings_json) VALUES
('Basic', 'basic', 'Essential features for regular users', JSON_OBJECT(
    'fraud_detection', JSON_OBJECT(
        'enable_fraud_detection', true,
        'block_invalid_barcodes', true,
        'real_time_alerts', false
    ),
    'performance_tracking', JSON_OBJECT(
        'enable_performance_tracking', true,
        'show_live_stats', false
    ),
    'gamification', JSON_OBJECT(
        'enable_gamification', false,
        'enable_end_session_summary', true
    ),
    'leaderboards', JSON_OBJECT(
        'enable_leaderboards', false
    ),
    'photo_requirements', JSON_OBJECT(
        'enable_photo_requirements', true,
        'block_completion_without_photos', true
    ),
    'ui_features', JSON_OBJECT(
        'table_style', 'standard'
    )
));

-- INTERMEDIATE PRESET (Balanced features)
INSERT INTO ANALYTICS_COMPLEXITY_PRESETS (preset_name, preset_level, description, settings_json) VALUES
('Intermediate', 'intermediate', 'Balanced feature set for experienced users', JSON_OBJECT(
    'fraud_detection', JSON_OBJECT(
        'enable_fraud_detection', true,
        'auto_flag_suspicious', true,
        'real_time_alerts', false
    ),
    'performance_tracking', JSON_OBJECT(
        'enable_performance_tracking', true,
        'show_live_stats', true,
        'record_personal_bests', true
    ),
    'gamification', JSON_OBJECT(
        'enable_gamification', true,
        'enable_achievements', true,
        'enable_end_session_summary', true,
        'animation_style', 'standard'
    ),
    'leaderboards', JSON_OBJECT(
        'enable_leaderboards', true,
        'outlet_leaderboard', true,
        'company_leaderboard', false
    ),
    'photo_requirements', JSON_OBJECT(
        'enable_photo_requirements', true,
        'block_completion_without_photos', true
    ),
    'ui_features', JSON_OBJECT(
        'show_performance_dashboard', true,
        'show_leaderboard_link', true,
        'table_style', 'detailed'
    )
));

-- ADVANCED PRESET (Power users)
INSERT INTO ANALYTICS_COMPLEXITY_PRESETS (preset_name, preset_level, description, settings_json) VALUES
('Advanced', 'advanced', 'Full features for power users', JSON_OBJECT(
    'fraud_detection', JSON_OBJECT(
        'enable_fraud_detection', true,
        'auto_flag_suspicious', true,
        'real_time_alerts', true
    ),
    'performance_tracking', JSON_OBJECT(
        'enable_performance_tracking', true,
        'show_live_stats', true,
        'record_personal_bests', true,
        'daily_aggregation', true,
        'weekly_aggregation', true
    ),
    'gamification', JSON_OBJECT(
        'enable_gamification', true,
        'enable_achievements', true,
        'show_achievement_notifications', true,
        'enable_points_system', true,
        'enable_end_session_summary', true,
        'animation_style', 'full'
    ),
    'leaderboards', JSON_OBJECT(
        'enable_leaderboards', true,
        'outlet_leaderboard', true,
        'company_leaderboard', true,
        'show_daily_leaderboard', true,
        'show_weekly_leaderboard', true
    ),
    'photo_requirements', JSON_OBJECT(
        'enable_photo_requirements', true,
        'block_completion_without_photos', true,
        'enable_qr_upload', true
    ),
    'notifications', JSON_OBJECT(
        'enable_notifications', true,
        'achievement_notifications', true,
        'daily_summary_email', true
    ),
    'reviews', JSON_OBJECT(
        'enable_transfer_reviews', true,
        'weekly_store_reports', true
    ),
    'ui_features', JSON_OBJECT(
        'show_performance_dashboard', true,
        'show_leaderboard_link', true,
        'show_achievements_page', true,
        'show_live_stats_widget', true,
        'table_style', 'detailed'
    )
));

-- VERY ADVANCED PRESET (Managers/Experts)
INSERT INTO ANALYTICS_COMPLEXITY_PRESETS (preset_name, preset_level, description, settings_json) VALUES
('Very Advanced', 'very_advanced', 'All features including management tools', JSON_OBJECT(
    'fraud_detection', JSON_OBJECT(
        'enable_fraud_detection', true,
        'auto_flag_suspicious', true,
        'require_supervisor_approval', true,
        'real_time_alerts', true,
        'enable_timing_checks', true,
        'enable_duplicate_detection', true,
        'enable_pattern_detection', true
    ),
    'performance_tracking', JSON_OBJECT(
        'enable_performance_tracking', true,
        'track_scan_speed', true,
        'track_accuracy', true,
        'show_live_stats', true,
        'record_personal_bests', true,
        'daily_aggregation', true,
        'weekly_aggregation', true
    ),
    'gamification', JSON_OBJECT(
        'enable_gamification', true,
        'enable_achievements', true,
        'show_achievement_notifications', true,
        'enable_points_system', true,
        'enable_end_session_summary', true,
        'summary_includes_rank', true,
        'summary_includes_achievements', true,
        'summary_includes_tips', true,
        'animation_style', 'full'
    ),
    'leaderboards', JSON_OBJECT(
        'enable_leaderboards', true,
        'show_daily_leaderboard', true,
        'show_weekly_leaderboard', true,
        'show_monthly_leaderboard', true,
        'show_alltime_leaderboard', true,
        'outlet_leaderboard', true,
        'company_leaderboard', true
    ),
    'photo_requirements', JSON_OBJECT(
        'enable_photo_requirements', true,
        'require_invoice_photo', true,
        'require_packing_slip_photo', true,
        'require_receipt_photo', true,
        'require_damage_photos', true,
        'block_completion_without_photos', true,
        'enable_qr_upload', true
    ),
    'notifications', JSON_OBJECT(
        'enable_notifications', true,
        'fraud_alert_notifications', true,
        'achievement_notifications', true,
        'daily_summary_email', true,
        'weekly_summary_email', true
    ),
    'reviews', JSON_OBJECT(
        'enable_transfer_reviews', true,
        'weekly_store_reports', true,
        'include_receiver_feedback', true,
        'flag_low_ratings', true
    ),
    'ui_features', JSON_OBJECT(
        'show_performance_dashboard', true,
        'show_leaderboard_link', true,
        'show_achievements_page', true,
        'show_live_stats_widget', true,
        'show_fraud_indicator', true,
        'show_personal_best', true,
        'table_style', 'detailed'
    )
));

-- EXPERT PRESET (Everything enabled, full control)
INSERT INTO ANALYTICS_COMPLEXITY_PRESETS (preset_name, preset_level, description, settings_json) VALUES
('Expert', 'expert', 'Every feature enabled - full control and visibility', JSON_OBJECT(
    'fraud_detection', JSON_OBJECT(
        'enable_fraud_detection', true,
        'auto_flag_suspicious', true,
        'require_supervisor_approval', true,
        'block_invalid_barcodes', true,
        'enable_timing_checks', true,
        'enable_duplicate_detection', true,
        'enable_pattern_detection', true,
        'real_time_alerts', true,
        'alert_sound', true
    ),
    'performance_tracking', JSON_OBJECT(
        'enable_performance_tracking', true,
        'track_scan_speed', true,
        'track_accuracy', true,
        'show_live_stats', true,
        'record_personal_bests', true,
        'daily_aggregation', true,
        'weekly_aggregation', true
    ),
    'gamification', JSON_OBJECT(
        'enable_gamification', true,
        'enable_achievements', true,
        'show_achievement_notifications', true,
        'enable_points_system', true,
        'enable_end_session_summary', true,
        'summary_includes_rank', true,
        'summary_includes_achievements', true,
        'summary_includes_tips', true,
        'animation_style', 'full'
    ),
    'leaderboards', JSON_OBJECT(
        'enable_leaderboards', true,
        'show_daily_leaderboard', true,
        'show_weekly_leaderboard', true,
        'show_monthly_leaderboard', true,
        'show_alltime_leaderboard', true,
        'outlet_leaderboard', true,
        'company_leaderboard', true,
        'show_user_rank', true
    ),
    'photo_requirements', JSON_OBJECT(
        'enable_photo_requirements', true,
        'require_invoice_photo', true,
        'require_packing_slip_photo', true,
        'require_receipt_photo', true,
        'require_damage_photos', true,
        'block_completion_without_photos', true,
        'allow_supervisor_override', true,
        'enable_qr_upload', true
    ),
    'notifications', JSON_OBJECT(
        'enable_notifications', true,
        'fraud_alert_notifications', true,
        'achievement_notifications', true,
        'daily_summary_email', true,
        'weekly_summary_email', true,
        'sound_notifications', true
    ),
    'reviews', JSON_OBJECT(
        'enable_transfer_reviews', true,
        'weekly_store_reports', true,
        'include_receiver_feedback', true,
        'flag_low_ratings', true
    ),
    'ui_features', JSON_OBJECT(
        'show_performance_dashboard', true,
        'show_leaderboard_link', true,
        'show_achievements_page', true,
        'show_live_stats_widget', true,
        'show_fraud_indicator', true,
        'show_personal_best', true,
        'compact_mode', false,
        'table_style', 'detailed'
    )
));

-- =====================================================
-- 6. SETTINGS RESOLUTION VIEW (Cascade Logic)
-- =====================================================
-- This view shows what settings apply to each user
-- Priority: Transfer Override > User Pref > Outlet Setting > Global Default
-- =====================================================
CREATE OR REPLACE VIEW V_EFFECTIVE_SETTINGS AS
SELECT
    sa.id AS user_id,
    sa.outlet_id,
    gs.category,
    gs.setting_key,
    COALESCE(
        up.setting_value,  -- User preference first
        os.setting_value,  -- Then outlet setting
        gs.setting_value   -- Finally global default
    ) AS effective_value,
    CASE
        WHEN up.setting_value IS NOT NULL THEN 'user'
        WHEN os.setting_value IS NOT NULL THEN 'outlet'
        ELSE 'global'
    END AS source_level,
    gs.description,
    gs.data_type
FROM ANALYTICS_GLOBAL_SETTINGS gs
CROSS JOIN staff_accounts sa
LEFT JOIN ANALYTICS_USER_PREFERENCES up
    ON up.user_id = sa.id
    AND up.category = gs.category
    AND up.setting_key = gs.setting_key
    AND up.is_enabled = TRUE
LEFT JOIN ANALYTICS_OUTLET_SETTINGS os
    ON os.outlet_id = sa.outlet_id
    AND os.category = gs.category
    AND os.setting_key = gs.setting_key
    AND os.is_enabled = TRUE
    AND os.inherit_from_global = FALSE
WHERE gs.is_enabled = TRUE;

-- =====================================================
-- INDEXES FOR PERFORMANCE
-- =====================================================
CREATE INDEX idx_effective_user_category ON ANALYTICS_USER_PREFERENCES(user_id, category);
CREATE INDEX idx_effective_outlet_category ON ANALYTICS_OUTLET_SETTINGS(outlet_id, category);
CREATE INDEX idx_effective_global_category ON ANALYTICS_GLOBAL_SETTINGS(category, is_enabled);

-- =====================================================
-- EXAMPLE QUERIES
-- =====================================================

-- Get all settings for a specific user
-- SELECT * FROM V_EFFECTIVE_SETTINGS WHERE user_id = 123;

-- Get specific category for user
-- SELECT * FROM V_EFFECTIVE_SETTINGS WHERE user_id = 123 AND category = 'fraud_detection';

-- Get single setting value for user
-- SELECT effective_value FROM V_EFFECTIVE_SETTINGS
-- WHERE user_id = 123 AND category = 'fraud_detection' AND setting_key = 'enable_fraud_detection';

-- Apply a complexity preset to a user
-- INSERT INTO ANALYTICS_USER_PREFERENCES (user_id, category, setting_key, setting_value, is_enabled)
-- SELECT 123, 'fraud_detection', 'enable_fraud_detection',
--        JSON_UNQUOTE(JSON_EXTRACT(settings_json, '$.fraud_detection.enable_fraud_detection')),
--        TRUE
-- FROM ANALYTICS_COMPLEXITY_PRESETS
-- WHERE preset_name = 'Very Basic';
