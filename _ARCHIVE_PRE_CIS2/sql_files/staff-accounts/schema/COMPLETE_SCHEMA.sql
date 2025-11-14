-- ============================================================================
-- STAFF ACCOUNTS MODULE - COMPLETE DATABASE SCHEMA
-- ============================================================================
-- Generated: 2025-10-25
-- Purpose: Complete schema reference for staff-accounts application
-- Database: jcepnzzkmj
-- Tables: 16 core tables + dependencies
-- ============================================================================

-- ----------------------------------------------------------------------------
-- TABLE: users
-- Purpose: CIS user accounts (staff members with system access)
-- Key Columns: id, email, vend_customer_account, xero_id, is_manager
-- Relationships: Links to staff_account_reconciliation via user_id
-- ----------------------------------------------------------------------------
CREATE TABLE `users` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `first_name` mediumtext NOT NULL COMMENT 'The first name of the user for identification and personalization in the system.',
  `last_name` mediumtext NOT NULL COMMENT 'The family name of the user for identification and communication purposes.',
  `email` mediumtext NOT NULL COMMENT 'Stores the user email address for communication and login purposes.',
  `password` mediumtext NOT NULL,
  `phone` mediumtext NOT NULL COMMENT 'Contact number for reaching the user directly for business communications.',
  `image` mediumtext DEFAULT NULL COMMENT 'Stores the URL or path to the user profile picture for identification.',
  `last_active` timestamp NULL DEFAULT NULL COMMENT 'Indicates the last time the user accessed the system for activity tracking.',
  `default_outlet` mediumtext DEFAULT NULL COMMENT 'The primary store location assigned to a user for their transactions and activities.',
  `role_id` int(11) NOT NULL COMMENT 'Identifies the user position or permissions within the company.',
  `is_manager` tinyint(1) DEFAULT 0,
  `stored_dashboard_view` mediumtext DEFAULT NULL COMMENT 'Stores the user preferred dashboard layout for personalized access.',
  `xero_id` varchar(45) DEFAULT NULL COMMENT 'Unique identifier linking the user to their Xero account for financial integration.',
  `vend_id` varchar(45) DEFAULT NULL COMMENT 'Unique identifier linking the user to their Vend account for sales and inventory management.',
  `vend_sync_at` timestamp NULL DEFAULT NULL,
  `deputy_id` varchar(45) DEFAULT NULL COMMENT 'Represents the unique identifier for a user profile in the Deputy workforce management system.',
  `account_locked` int(11) NOT NULL DEFAULT 0 COMMENT 'Indicates if a user account is currently restricted from access.',
  `staff_active` int(11) NOT NULL DEFAULT 1 COMMENT 'Indicates if a staff member is currently employed and active in the system.',
  `nicknames` mediumtext DEFAULT NULL COMMENT 'Alternative names or informal names used by staff members.',
  `vend_customer_account` varchar(45) DEFAULT NULL,
  `gpt_access` tinyint(1) DEFAULT 0 COMMENT 'Indicates if the user has permission to access GPT-related features.',
  `gpt_admin` tinyint(1) DEFAULT 0 COMMENT 'Indicates if the user has administrative privileges for GPT-related tasks.',
  PRIMARY KEY (`id`),
  UNIQUE KEY `vend_id_UNIQUE` (`vend_id`),
  UNIQUE KEY `vend_customer_account_UNIQUE` (`vend_customer_account`),
  UNIQUE KEY `deputy_id_UNIQUE` (`deputy_id`),
  UNIQUE KEY `xero_id_UNIQUE` (`xero_id`),
  KEY `idx_users_vend_id` (`vend_id`),
  KEY `idx_is_manager` (`is_manager`)
) ENGINE=InnoDB AUTO_INCREMENT=130 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- TABLE: staff_account_reconciliation
-- Purpose: CORE TABLE - Reconciliation between Xero deductions and Vend balances
-- Key Columns: vend_customer_id, vend_balance, total_allocated, outstanding_amount
-- Critical for: Staff purchase tracking, payment reconciliation, credit limits
-- ----------------------------------------------------------------------------
CREATE TABLE `staff_account_reconciliation` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned DEFAULT NULL,
  `vend_customer_id` varchar(100) NOT NULL,
  `credit_account_id` varchar(50) DEFAULT NULL COMMENT 'Lightspeed CreditAccount ID for this customer',
  `discount_id` varchar(50) DEFAULT NULL COMMENT 'Lightspeed Customer discount ID (if applicable)',
  `employee_name` varchar(255) NOT NULL,
  `total_xero_deductions` decimal(10,2) DEFAULT 0.00 COMMENT 'Total from Xero payrolls',
  `total_allocated` decimal(10,2) DEFAULT 0.00 COMMENT 'Total allocated to Vend',
  `pending_allocation` decimal(10,2) DEFAULT 0.00 COMMENT 'Not yet allocated',
  `vend_balance` decimal(10,2) DEFAULT 0.00 COMMENT 'Current Vend customer balance',
  `credit_limit` decimal(10,2) DEFAULT 0.00 COMMENT 'Max credit allowed from Lightspeed CreditAccount API (0 = unlimited)',
  `vend_balance_updated_at` datetime DEFAULT NULL,
  `vend_last_synced_at` datetime DEFAULT NULL COMMENT 'Last time we synced credit_limit from Lightspeed CreditAccount API',
  `outstanding_amount` decimal(10,2) DEFAULT 0.00 COMMENT 'Balance - Allocated (what they still owe)',
  `last_payment_date` datetime DEFAULT NULL,
  `last_payment_amount` decimal(10,2) DEFAULT NULL,
  `last_payment_transaction_id` varchar(100) DEFAULT NULL,
  `total_payments_ytd` decimal(10,2) DEFAULT 0.00,
  `status` enum('balanced','overpaid','underpaid','pending') DEFAULT 'pending',
  `last_reconciled_at` datetime DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_vend_customer` (`vend_customer_id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_status` (`status`),
  KEY `idx_outstanding` (`outstanding_amount`),
  KEY `idx_last_payment_date` (`last_payment_date`),
  KEY `idx_credit_limit` (`credit_limit`),
  KEY `idx_vend_last_synced` (`vend_last_synced_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
COMMENT='Reconciliation between Xero and Vend';

-- ----------------------------------------------------------------------------
-- TABLE: staff_payment_plans
-- Purpose: Payment plans for staff with outstanding balances
-- Key Columns: total_amount, installment_amount, frequency, status
-- Relationships: FK to users, has many staff_payment_plan_installments
-- ----------------------------------------------------------------------------
CREATE TABLE `staff_payment_plans` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `installment_amount` decimal(10,2) NOT NULL,
  `frequency` enum('weekly','fortnightly','monthly') NOT NULL,
  `total_installments` int(11) NOT NULL,
  `completed_installments` int(11) DEFAULT 0,
  `next_payment_date` date NOT NULL,
  `status` enum('active','completed','cancelled','defaulted') DEFAULT 'active',
  `created_at` datetime DEFAULT current_timestamp(),
  `completed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_status` (`status`),
  KEY `idx_next_payment` (`next_payment_date`),
  KEY `idx_active_plans` (`user_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- TABLE: staff_payment_plan_installments
-- Purpose: Individual installment records for payment plans
-- Key Columns: plan_id, amount, due_date, paid_date, status
-- Relationships: FK to staff_payment_plans (CASCADE delete)
-- ----------------------------------------------------------------------------
CREATE TABLE `staff_payment_plan_installments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `plan_id` int(11) NOT NULL,
  `installment_number` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `due_date` date NOT NULL,
  `paid_date` datetime DEFAULT NULL,
  `transaction_id` varchar(100) DEFAULT NULL,
  `status` enum('pending','paid','failed','skipped') DEFAULT 'pending',
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_plan_id` (`plan_id`),
  KEY `idx_status` (`status`),
  KEY `idx_due_date` (`due_date`),
  CONSTRAINT `staff_payment_plan_installments_ibfk_1` 
    FOREIGN KEY (`plan_id`) REFERENCES `staff_payment_plans` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- TABLE: staff_payment_transactions
-- Purpose: Payment gateway transactions (Nuvei)
-- Key Columns: user_id, transaction_type, amount, request_id, response_data
-- Contains: Session creation, approvals, failures, refunds
-- ----------------------------------------------------------------------------
CREATE TABLE `staff_payment_transactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `transaction_type` enum('session_created','payment_approved','payment_failed','refund') NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `request_id` varchar(100) NOT NULL,
  `response_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`response_data`)),
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `request_id` (`request_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_type` (`transaction_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- TABLE: staff_saved_cards
-- Purpose: Saved payment cards for quick checkout
-- Key Columns: user_id, card_token, last_four_digits, is_default
-- Security: Only stores tokens, not actual card numbers
-- ----------------------------------------------------------------------------
CREATE TABLE `staff_saved_cards` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `card_token` varchar(255) NOT NULL,
  `last_four_digits` char(4) NOT NULL,
  `card_type` varchar(20) NOT NULL,
  `expiry_month` tinyint(4) NOT NULL,
  `expiry_year` smallint(6) NOT NULL,
  `is_default` tinyint(1) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  `last_used_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_token` (`user_id`,`card_token`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_is_default` (`is_default`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- TABLE: staff_members
-- Purpose: Staff member profiles (separate from users - for AI/recognition)
-- Key Columns: staff_id, display_name, position, outlet_id
-- Used by: CISWatch facial recognition, staff tracking
-- ----------------------------------------------------------------------------
CREATE TABLE `staff_members` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `staff_id` varchar(50) NOT NULL,
  `employee_number` varchar(50) DEFAULT NULL,
  `display_name` varchar(100) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `position` varchar(100) DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  `outlet_id` varchar(50) DEFAULT NULL,
  `face_vectors` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`face_vectors`)),
  `photos` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`photos`)),
  `overlay_color` varchar(7) DEFAULT '#00FF00',
  `recognition_enabled` tinyint(1) DEFAULT 1,
  `privacy_level` enum('full','name_only','position_only','anonymous') DEFAULT 'full',
  `status` enum('active','inactive','suspended') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `staff_id` (`staff_id`),
  KEY `idx_status` (`status`),
  KEY `idx_outlet` (`outlet_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- TABLE: vend_customers
-- Purpose: Vend/Lightspeed customer records (includes staff accounts)
-- Key Columns: id, customer_code, email, balance, loyalty_balance
-- Critical: Staff members have Vend customer accounts for purchases
-- ----------------------------------------------------------------------------
CREATE TABLE `vend_customers` (
  `id` varchar(45) NOT NULL COMMENT 'Unique identifier for each customer in the database.',
  `customer_code` varchar(100) DEFAULT NULL COMMENT 'Unique identifier for each customer used for tracking and reference in the system.',
  `first_name` mediumtext DEFAULT NULL COMMENT 'Stores the customer given name for identification and personalization in interactions.',
  `last_name` mediumtext DEFAULT NULL COMMENT 'Stores the customer family name for identification and communication purposes.',
  `email` varchar(100) DEFAULT NULL COMMENT 'Stores the customer email address for communication and account identification.',
  `year_to_date` varchar(45) DEFAULT NULL COMMENT 'Total purchases made by the customer this year in monetary value.',
  `balance` varchar(45) DEFAULT NULL COMMENT 'Outstanding amount the customer owes or credit they have with the company.',
  `loyalty_balance` varchar(45) DEFAULT NULL COMMENT 'Points accumulated by a customer for loyalty rewards.',
  `note` mediumtext DEFAULT NULL COMMENT 'Records important customer-related notes, such as underage or fraud alerts, for internal reference.',
  `gender` varchar(45) DEFAULT NULL COMMENT 'Indicates the customer gender for personalized marketing and communication.',
  `date_of_birth` varchar(45) DEFAULT NULL COMMENT 'Customer birth date for age verification and personalized marketing.',
  `company_name` varchar(100) DEFAULT NULL COMMENT 'Stores the name of the company associated with the customer for business reference.',
  `do_not_email` varchar(45) DEFAULT NULL COMMENT 'Indicates if a customer has opted out of receiving marketing emails.',
  `phone` varchar(100) DEFAULT NULL COMMENT 'Primary contact number for reaching the customer.',
  `mobile` varchar(100) DEFAULT NULL COMMENT 'Stores the customer mobile phone number for contact and communication purposes.',
  `physical_suburb` varchar(45) DEFAULT NULL COMMENT 'The suburb where the customer physical address is located.',
  `physical_city` varchar(45) DEFAULT NULL COMMENT 'Stores the city where the customer physical address is located for delivery and service purposes.',
  `physical_postcode` varchar(45) DEFAULT NULL COMMENT 'Stores the postcode for the customer physical address for delivery and location-based services.',
  `physical_state` varchar(45) DEFAULT NULL COMMENT 'Indicates the region or state where the customer physical address is located.',
  `postal_suburb` varchar(45) DEFAULT NULL COMMENT 'Stores the suburb part of a customer postal address for mailing purposes.',
  `postal_city` varchar(45) DEFAULT NULL COMMENT 'City for the customer postal address used for shipping and correspondence.',
  `postal_state` varchar(45) DEFAULT NULL COMMENT 'The state or region for the customer postal address used for shipping and correspondence.',
  `customer_group_id` varchar(45) DEFAULT NULL COMMENT 'Identifies the specific customer group for tailored marketing and sales strategies.',
  `enable_loyalty` varchar(45) DEFAULT NULL COMMENT 'Indicates if the customer is eligible to earn loyalty points.',
  `created_at` varchar(45) DEFAULT NULL COMMENT 'The date and time when the customer record was first created in the system.',
  `updated_at` varchar(45) DEFAULT NULL COMMENT 'Timestamp of the last update made to the customer record.',
  `deleted_at` varchar(45) DEFAULT NULL COMMENT 'Timestamp indicating when a customer record was marked as inactive or removed.',
  `version` varchar(45) DEFAULT NULL,
  `postal_postcode` varchar(45) DEFAULT NULL COMMENT 'Stores the postcode for the customer mailing address used for shipping and correspondence.',
  `name` mediumtext DEFAULT NULL COMMENT 'Full name of the customer for identification and communication purposes.',
  `physical_address_1` varchar(200) DEFAULT NULL COMMENT 'Stores the primary street address where the customer resides or receives deliveries.',
  `physical_address_2` varchar(200) DEFAULT NULL COMMENT 'Additional address details for customer deliveries or correspondence.',
  `physical_country_id` varchar(45) DEFAULT NULL COMMENT 'Stores the country code for the customer physical address location.',
  `postal_address_1` varchar(200) DEFAULT NULL COMMENT 'Primary line of the customer mailing address for shipping and correspondence.',
  `postal_address_2` varchar(200) DEFAULT NULL COMMENT 'Additional details for the customer mailing address, such as apartment or suite number.',
  `postal_country_id` varchar(45) DEFAULT NULL COMMENT 'Stores the country code for the customer postal address for shipping and communication purposes.',
  `custom_field_1` mediumtext DEFAULT NULL COMMENT 'This column stores miscellaneous customer notes or alerts for internal reference.',
  `custom_field_2` varchar(200) DEFAULT NULL,
  `custom_field_3` varchar(200) DEFAULT NULL,
  `custom_field_4` varchar(200) DEFAULT NULL,
  `website` varchar(200) DEFAULT NULL,
  `unsubscribe_account_balance` int(11) NOT NULL DEFAULT 0 COMMENT 'Indicates if a customer account balance should be excluded from email communications.',
  PRIMARY KEY (`id`),
  UNIQUE KEY `id_UNIQUE` (`id`),
  KEY `customerID` (`id`,`customer_code`,`email`),
  KEY `vs_code_idx` (`customer_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- TABLE: vend_sales
-- Purpose: All sales transactions from Vend POS
-- Key Columns: id, customer_id, user_id, outlet_id, total_price, sale_date
-- Used for: Staff purchase tracking, sales history
-- ----------------------------------------------------------------------------
CREATE TABLE `vend_sales` (
  `increment_id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Unique identifier for each sale transaction in the system.',
  `id` varchar(36) NOT NULL COMMENT 'Unique identifier for each sale transaction in the system.',
  `outlet_id` varchar(36) NOT NULL COMMENT 'Identifies the specific store location where the sale was made.',
  `register_id` varchar(36) NOT NULL COMMENT 'Identifies the specific cash register used for the sale transaction.',
  `user_id` varchar(36) NOT NULL COMMENT 'Identifies the staff member who processed the sale transaction.',
  `customer_id` varchar(36) NOT NULL COMMENT 'Unique identifier for the customer associated with each sale transaction.',
  `invoice_number` int(11) NOT NULL COMMENT 'Unique identifier for each sales transaction invoice.',
  `status` varchar(30) NOT NULL COMMENT 'Indicates the current progress or completion stage of a sale transaction.',
  `note` mediumtext NOT NULL COMMENT 'Additional details or comments related to a specific sale transaction.',
  `short_code` varchar(15) NOT NULL COMMENT 'Unique identifier for quick reference to specific sales transactions.',
  `return_for` varchar(100) DEFAULT NULL COMMENT 'Links a sale to its original transaction when processing a return.',
  `total_price` decimal(16,6) DEFAULT NULL COMMENT 'The total amount charged for a sale, including any discounts or returns.',
  `total_tax` decimal(16,6) DEFAULT NULL COMMENT 'The total tax amount applied to a sale transaction.',
  `total_loyalty` decimal(16,6) DEFAULT NULL COMMENT 'The total loyalty points earned by the customer from this sale.',
  `created_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00' COMMENT 'Timestamp of when the sale record was initially created in the system.',
  `updated_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00' COMMENT 'Timestamp of the last modification made to the sales record.',
  `sale_date` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00' COMMENT 'The date and time when the sale transaction was completed.',
  `deleted_at` timestamp NULL DEFAULT NULL COMMENT 'Timestamp indicating when the sale record was removed from active status.',
  `version` bigint(100) NOT NULL,
  `receipt_number` int(11) NOT NULL COMMENT 'Unique identifier for each sales transaction receipt issued to customers.',
  `version_max` bigint(100) DEFAULT NULL,
  `payments` longtext DEFAULT NULL,
  `sale_date_d` date GENERATED ALWAYS AS (cast(`sale_date` as date)) STORED,
  PRIMARY KEY (`increment_id`),
  UNIQUE KEY `SALE_ID_INDEX` (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `ix_vs_outlet_date_inc` (`outlet_id`,`sale_date`,`increment_id`),
  KEY `ix_vs_date_inc` (`sale_date`,`increment_id`),
  KEY `ix_vs_customer_date` (`customer_id`,`sale_date`),
  KEY `idx_vend_sales_customer_date` (`customer_id`,`sale_date`)
) ENGINE=InnoDB AUTO_INCREMENT=1176298816 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- TABLE: vend_outlets
-- Purpose: Store locations (17 physical stores)
-- Key Columns: id, name, outlet_lat, outlet_long, is_warehouse
-- Used for: Staff assignment, inventory management
-- ----------------------------------------------------------------------------
CREATE TABLE `vend_outlets` (
  `id` varchar(100) NOT NULL COMMENT 'Unique identifier for each retail outlet in the system.',
  `register_id` varchar(100) DEFAULT NULL COMMENT 'Unique identifier for the cash register used at a specific retail outlet.',
  `name` varchar(100) NOT NULL COMMENT 'The name of the physical store location for inventory and sales tracking.',
  `default_tax_id` varchar(100) DEFAULT NULL COMMENT 'Identifies the tax rate applied by default to sales at this outlet.',
  `currency` varchar(100) DEFAULT NULL COMMENT 'The currency used for transactions at this outlet.',
  `currency_symbol` varchar(100) DEFAULT NULL COMMENT 'Symbol used to represent the currency for transactions at this outlet.',
  `display_prices` varchar(100) DEFAULT NULL COMMENT 'Indicates whether prices shown to customers include tax.',
  `time_zone` varchar(100) DEFAULT NULL COMMENT 'Indicates the local time zone for scheduling and coordinating store operations.',
  `physical_street_number` varchar(45) DEFAULT NULL COMMENT 'Stores the street number for the physical location of the vendor outlet.',
  `physical_street` varchar(45) DEFAULT NULL COMMENT 'Street name for the outlet physical location.',
  `physical_address_1` varchar(100) DEFAULT NULL COMMENT 'Stores the first line of the outlet physical address for location identification.',
  `physical_address_2` varchar(100) DEFAULT NULL COMMENT 'Additional address details for a vendor outlet, such as a building or unit name.',
  `physical_suburb` varchar(100) DEFAULT NULL COMMENT 'Identifies the suburb where the physical store is located for delivery and regional analysis.',
  `physical_city` varchar(255) DEFAULT NULL,
  `physical_postcode` varchar(100) DEFAULT NULL COMMENT 'The postcode for the physical location of the retail outlet.',
  `physical_state` varchar(100) DEFAULT NULL COMMENT 'Indicates the region or province where the outlet is located for logistical and reporting purposes.',
  `physical_country_id` varchar(100) DEFAULT NULL COMMENT 'Stores the country code where the physical outlet is located for business operations.',
  `physical_phone_number` varchar(45) DEFAULT NULL COMMENT 'Contact number for reaching the physical store location.',
  `deleted_at` timestamp NULL DEFAULT NULL COMMENT 'Timestamp indicating when the outlet was marked as inactive or removed from active use.',
  `version` bigint(20) NOT NULL,
  `turn_over_rate` float NOT NULL DEFAULT 5 COMMENT 'The rate at which inventory is sold and replaced at this outlet.',
  `automatic_ordering` int(11) NOT NULL DEFAULT 1 COMMENT 'Indicates if the outlet uses an automated system for placing stock orders.',
  `facebook_page_id` varchar(45) DEFAULT NULL,
  `gss_token` varchar(100) DEFAULT NULL,
  `google_page_id` varchar(100) DEFAULT NULL,
  `total_review_count` int(11) DEFAULT NULL,
  `google_review_rating` float(2,1) DEFAULT NULL,
  `store_code` varchar(45) DEFAULT NULL COMMENT 'Unique identifier for each store location used in operations and reporting.',
  `magento_warehouse_id` int(11) DEFAULT NULL,
  `google_link` varchar(100) DEFAULT NULL,
  `outlet_lat` varchar(45) DEFAULT NULL COMMENT 'Latitude coordinate for the outlet physical location.',
  `outlet_long` varchar(45) DEFAULT NULL COMMENT 'Geographical longitude coordinate for the outlet location.',
  `website_active` int(11) NOT NULL DEFAULT 1,
  `website_outlet_id` int(11) DEFAULT NULL,
  `deposit_card_id` int(11) DEFAULT NULL,
  `vape_hq_shipping_id` varchar(45) DEFAULT NULL,
  `banking_days_allocated` int(11) NOT NULL DEFAULT 7,
  `email` varchar(45) DEFAULT NULL,
  `nz_post_api_key` varchar(45) DEFAULT NULL,
  `nz_post_subscription_key` varchar(45) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `deputy_location_id` int(11) NOT NULL DEFAULT 0,
  `eftpos_merchant_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_warehouse` int(11) NOT NULL DEFAULT 0 COMMENT 'Indicates if the outlet functions as a warehouse (1 for yes, 0 for no).',
  PRIMARY KEY (`id`),
  UNIQUE KEY `register_id_UNIQUE` (`register_id`),
  UNIQUE KEY `magento_warehouse_id_UNIQUE` (`website_outlet_id`),
  KEY `ix_vend_outlets_warehouse` (`is_warehouse`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- TABLE: cis_staff_vend_map
-- Purpose: Maps Xero employees to Vend customers
-- Key Columns: xero_employee_id (PK), vend_customer_id, email
-- Critical: Links staff accounts for deduction processing
-- ----------------------------------------------------------------------------
CREATE TABLE `cis_staff_vend_map` (
  `xero_employee_id` char(36) NOT NULL,
  `vend_customer_id` char(36) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `first_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`xero_employee_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ----------------------------------------------------------------------------
-- TABLE: xero_payroll_deductions
-- Purpose: Staff account deductions from Xero payslips
-- Key Columns: payroll_id, xero_employee_id, amount, allocation_status
-- Workflow: pending → allocated (creates Vend payment) → success/failure
-- ----------------------------------------------------------------------------
CREATE TABLE `xero_payroll_deductions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `payroll_id` int(10) unsigned NOT NULL COMMENT 'FK to xero_payrolls',
  `xero_employee_id` varchar(100) NOT NULL COMMENT 'Xero employee ID',
  `employee_name` varchar(255) NOT NULL,
  `user_id` int(10) unsigned DEFAULT NULL COMMENT 'FK to staff users table (if mapped)',
  `vend_customer_id` varchar(100) DEFAULT NULL COMMENT 'Vend customer ID (if mapped)',
  `deduction_type` varchar(100) NOT NULL COMMENT 'e.g., Staff Account, Staff Purchase',
  `deduction_code` varchar(50) DEFAULT NULL COMMENT 'Xero deduction code',
  `amount` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Deduction amount from payslip',
  `description` text DEFAULT NULL,
  `vend_payment_id` varchar(100) DEFAULT NULL COMMENT 'Vend payment ID after allocation',
  `allocated_amount` decimal(10,2) DEFAULT 0.00 COMMENT 'Amount successfully allocated to Vend',
  `allocation_status` enum('pending','allocated','failed','partial') DEFAULT 'pending',
  `allocated_at` datetime DEFAULT NULL COMMENT 'When payment was allocated to Vend',
  `allocation_error` text DEFAULT NULL COMMENT 'Error message if allocation failed',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_employee` (`xero_employee_id`),
  KEY `idx_user_vend` (`user_id`,`vend_customer_id`),
  KEY `idx_allocation_status` (`allocation_status`),
  KEY `idx_payroll_employee` (`payroll_id`,`xero_employee_id`),
  CONSTRAINT `xero_payroll_deductions_ibfk_1` 
    FOREIGN KEY (`payroll_id`) REFERENCES `xero_payrolls` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=249 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- TABLE: xero_payrolls
-- Purpose: Xero payroll runs (pay periods)
-- Key Columns: xero_payroll_id, payment_date, total_deductions, status
-- Relationships: Has many xero_payroll_deductions
-- ----------------------------------------------------------------------------
CREATE TABLE `xero_payrolls` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `xero_payroll_id` varchar(100) NOT NULL COMMENT 'Xero API payroll ID',
  `pay_period_start` date NOT NULL,
  `pay_period_end` date NOT NULL,
  `payment_date` date NOT NULL,
  `total_gross_pay` decimal(10,2) DEFAULT 0.00,
  `total_deductions` decimal(10,2) DEFAULT 0.00,
  `employee_count` int(10) unsigned DEFAULT 0,
  `status` enum('draft','posted','processed') DEFAULT 'draft',
  `raw_data` longtext DEFAULT NULL COMMENT 'Full JSON response from Xero API',
  `cached_at` datetime NOT NULL COMMENT 'When this payroll was cached',
  `is_cached` tinyint(1) DEFAULT 0 COMMENT '1 if > 7 days old and cached',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_xero_payroll_id` (`xero_payroll_id`),
  KEY `idx_payment_date` (`payment_date`),
  KEY `idx_cached` (`is_cached`,`cached_at`),
  KEY `idx_pay_period` (`pay_period_start`,`pay_period_end`)
) ENGINE=InnoDB AUTO_INCREMENT=69 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- TABLE: staff_reminder_log
-- Purpose: Audit log for reminders sent to staff
-- Key Columns: user_id, reminder_type, sent_at, status
-- Used for: Email/SMS reminder tracking
-- ----------------------------------------------------------------------------
CREATE TABLE `staff_reminder_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `reminder_type` enum('email','sms','push','in_app') NOT NULL,
  `subject` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `sent_by` int(11) NOT NULL,
  `sent_at` datetime DEFAULT current_timestamp(),
  `status` enum('sent','failed','pending') DEFAULT 'sent',
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_sent_at` (`sent_at`),
  KEY `idx_sent_by` (`sent_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- TABLE: staff_preferences
-- Purpose: Staff preferences for gamification/AI features
-- Key Columns: staff_id (PK), preferred_modes, difficulty_preference
-- Used by: AI training, gamification system
-- ----------------------------------------------------------------------------
CREATE TABLE `staff_preferences` (
  `staff_id` varchar(50) NOT NULL,
  `preferred_modes` longtext DEFAULT NULL CHECK (json_valid(`preferred_modes`)),
  `challenge_types` longtext DEFAULT NULL CHECK (json_valid(`challenge_types`)),
  `difficulty_preference` enum('easy','medium','hard') DEFAULT 'medium',
  `notification_frequency` enum('daily','weekly','never') DEFAULT 'weekly',
  `gamification_enabled` tinyint(1) DEFAULT 1,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`staff_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- QUICK REFERENCE: TABLE RELATIONSHIPS
-- ============================================================================
-- users.id → staff_account_reconciliation.user_id
-- users.vend_customer_account → vend_customers.customer_code
-- users.xero_id → cis_staff_vend_map.xero_employee_id
-- vend_customers.id → staff_account_reconciliation.vend_customer_id
-- vend_customers.id → vend_sales.customer_id
-- xero_employee_id → xero_payroll_deductions.xero_employee_id
-- staff_payment_plans.id → staff_payment_plan_installments.plan_id (CASCADE)
-- xero_payrolls.id → xero_payroll_deductions.payroll_id (CASCADE)

-- ============================================================================
-- CRITICAL QUERIES FOR STAFF ACCOUNTS MODULE
-- ============================================================================

-- Get staff account with balance:
-- SELECT * FROM staff_account_reconciliation WHERE vend_customer_id = ?

-- Get user's Vend customer:
-- SELECT vc.* FROM vend_customers vc
-- JOIN users u ON u.vend_customer_account = vc.customer_code
-- WHERE u.id = ?

-- Get staff purchases (sales where customer is staff):
-- SELECT * FROM vend_sales 
-- WHERE customer_id IN (
--   SELECT vend_customer_id FROM staff_account_reconciliation
-- ) AND sale_date >= '2025-01-01'

-- Get Xero deductions for employee:
-- SELECT * FROM xero_payroll_deductions
-- WHERE xero_employee_id = ? AND allocation_status = 'pending'

-- Get active payment plans:
-- SELECT * FROM staff_payment_plans
-- WHERE user_id = ? AND status = 'active'

-- ============================================================================
-- END OF SCHEMA
-- ============================================================================
