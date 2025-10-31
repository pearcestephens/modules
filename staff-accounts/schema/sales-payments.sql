-- ----------------------------------------------------------------------------
-- TABLE: sales_payments
-- Purpose: Extracted payment data from vend_sales.payments JSON field
-- Data Source: Synced from ALL vend_sales.payments (customers AND staff)
-- 
-- This table extracts payment details from the vend_sales.payments JSON column
-- for faster querying without parsing JSON every time.
--
-- Populated by: modules/staff-accounts/lib/sync-payments.php
-- Schedule: Hourly via cron
-- 
-- BASED ON ACTUAL VEND JSON STRUCTURE (verified from vend_sales table)
-- ----------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `sales_payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  
  -- Link to source sale
  `vend_sale_id` varchar(36) NOT NULL COMMENT 'Foreign key to vend_sales.id',
  `vend_customer_id` varchar(36) DEFAULT NULL COMMENT 'Foreign key to vend_customers.id',
  
  -- Payment details from Vend JSON (exact field names from webhook)
  `payment_id` varchar(36) DEFAULT NULL COMMENT 'Vend payment.id from JSON',
  `payment_type_id` varchar(36) DEFAULT NULL COMMENT 'Vend payment_type_id from JSON',
  `retailer_payment_type_id` varchar(36) DEFAULT NULL COMMENT 'Retailer payment_type_id from JSON',
  `amount` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Payment amount from JSON',
  `name` varchar(100) DEFAULT NULL COMMENT 'Payment method name from JSON (e.g., "Cash", "Internet Banking", "Hamilton East")',
  `payment_date` datetime NOT NULL COMMENT 'Payment date from JSON',
  
  -- Additional Vend fields for complete data
  `register_id` varchar(36) DEFAULT NULL COMMENT 'Register ID from JSON',
  `register_open_sequence_id` varchar(36) DEFAULT NULL COMMENT 'Register open sequence from JSON',
  `outlet_id` varchar(36) DEFAULT NULL COMMENT 'Outlet ID from JSON',
  `surcharge` decimal(10,2) DEFAULT NULL COMMENT 'Surcharge amount from JSON',
  `source_id` varchar(36) DEFAULT NULL COMMENT 'Source ID from JSON',
  `deleted_at` datetime DEFAULT NULL COMMENT 'Soft delete timestamp from JSON',
  
  -- Sale context (from vend_sales)
  `sale_date` datetime NOT NULL COMMENT 'Date from vend_sales.sale_date',
  `outlet_name` varchar(100) DEFAULT NULL COMMENT 'Outlet name for display',
  `sale_status` varchar(50) DEFAULT NULL COMMENT 'Sale status (CLOSED, ONACCOUNT, etc)',
  `sale_total` decimal(10,2) DEFAULT NULL COMMENT 'Total sale amount for reference',
  
  -- Timestamps
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  
  PRIMARY KEY (`id`),
  
  -- Indexes for performance
  KEY `idx_vend_sale_id` (`vend_sale_id`),
  KEY `idx_vend_customer_id` (`vend_customer_id`),
  KEY `idx_payment_id` (`payment_id`),
  KEY `idx_payment_date` (`payment_date`),
  KEY `idx_sale_date` (`sale_date`),
  KEY `idx_name` (`name`),
  KEY `idx_outlet_id` (`outlet_id`),
  KEY `idx_sale_status` (`sale_status`),
  KEY `idx_register_id` (`register_id`),
  
  -- Composite indexes for common queries
  KEY `idx_customer_date` (`vend_customer_id`, `payment_date`),
  KEY `idx_outlet_date` (`outlet_id`, `payment_date`),
  KEY `idx_name_date` (`name`, `payment_date`)
  
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Extracted payments from vend_sales.payments JSON - synced hourly - verified structure from actual Vend data';

