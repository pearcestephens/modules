-- ============================================================================
-- TABLE NAMING CONVENTION MIGRATION
-- ============================================================================
-- Date: 2025-11-09
-- Purpose: Rename brand new module tables with proper prefixes
-- SAFE: Only renames tables that have NO pre-existing conflicts
-- ============================================================================

-- ============================================================================
-- 1. ADMIN-UI → theme_ prefix (4 tables)
-- ============================================================================
RENAME TABLE admin_ui_themes TO theme_themes;
RENAME TABLE admin_ui_settings TO theme_settings;
RENAME TABLE ai_agent_configs TO theme_ai_configs;
RENAME TABLE admin_ui_analytics TO theme_analytics;

-- ============================================================================
-- 2. CONTROL-PANEL → cp_ prefix (5 tables)
-- ============================================================================
RENAME TABLE system_backups TO cp_backups;
RENAME TABLE system_config TO cp_config;
RENAME TABLE system_logs TO cp_logs;
RENAME TABLE module_registry TO cp_registry;
RENAME TABLE system_maintenance TO cp_maintenance;

-- ============================================================================
-- 3. HR-PORTAL → hr_ prefix (4 tables, skip employee_reviews)
-- ============================================================================
RENAME TABLE review_questions TO hr_review_questions;
RENAME TABLE review_responses TO hr_review_responses;
RENAME TABLE employee_tracking_definitions TO hr_tracking_defs;
RENAME TABLE employee_tracking_entries TO hr_tracking_entries;
-- NOTE: employee_reviews kept as-is (pre-existing)

-- ============================================================================
-- 4. ECOMMERCE-OPS → ecom_ prefix (5 tables)
-- ============================================================================
RENAME TABLE ecommerce_orders TO ecom_orders;
RENAME TABLE order_items TO ecom_order_items;
RENAME TABLE inventory_sync TO ecom_inventory_sync;
RENAME TABLE age_verification_submissions TO ecom_age_verify;
RENAME TABLE site_sync_log TO ecom_site_sync_log;

-- ============================================================================
-- 5. BANK-TRANSACTIONS → bank_ prefix (2 tables, skip reconciliation_rules)
-- ============================================================================
RENAME TABLE bank_transactions TO bank_transactions; -- Already has bank_ prefix
RENAME TABLE transaction_matches TO bank_matches;
-- NOTE: reconciliation_rules kept as-is (pre-existing)

-- ============================================================================
-- VERIFICATION QUERIES
-- ============================================================================
-- Run these after migration to verify:

-- Check theme_ tables
-- SHOW TABLES LIKE 'theme_%';

-- Check cp_ tables
-- SHOW TABLES LIKE 'cp_%';

-- Check hr_ tables
-- SHOW TABLES LIKE 'hr_%';

-- Check ecom_ tables
-- SHOW TABLES LIKE 'ecom_%';

-- Check bank_ tables
-- SHOW TABLES LIKE 'bank_%';

-- ============================================================================
-- ROLLBACK (if needed)
-- ============================================================================
/*
RENAME TABLE theme_themes TO admin_ui_themes;
RENAME TABLE theme_settings TO admin_ui_settings;
RENAME TABLE theme_ai_configs TO ai_agent_configs;
RENAME TABLE theme_analytics TO admin_ui_analytics;

RENAME TABLE cp_backups TO system_backups;
RENAME TABLE cp_config TO system_config;
RENAME TABLE cp_logs TO system_logs;
RENAME TABLE cp_registry TO module_registry;
RENAME TABLE cp_maintenance TO system_maintenance;

RENAME TABLE hr_review_questions TO review_questions;
RENAME TABLE hr_review_responses TO review_responses;
RENAME TABLE hr_tracking_defs TO employee_tracking_definitions;
RENAME TABLE hr_tracking_entries TO employee_tracking_entries;

RENAME TABLE ecom_orders TO ecommerce_orders;
RENAME TABLE ecom_order_items TO order_items;
RENAME TABLE ecom_inventory_sync TO inventory_sync;
RENAME TABLE ecom_age_verify TO age_verification_submissions;
RENAME TABLE ecom_site_sync_log TO site_sync_log;

RENAME TABLE bank_matches TO transaction_matches;
*/
