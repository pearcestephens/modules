-- Xero Payroll Integration Schema
-- Created: 2025-10-21
-- Purpose: Track Xero payrolls, deductions, and payment allocations

-- ============================================================================
-- Table: xero_payrolls
-- Purpose: Store Xero payroll runs with metadata
-- ============================================================================
CREATE TABLE IF NOT EXISTS `xero_payrolls` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `xero_payroll_id` VARCHAR(100) NOT NULL COMMENT 'Xero API payroll ID',
    `pay_period_start` DATE NOT NULL,
    `pay_period_end` DATE NOT NULL,
    `payment_date` DATE NOT NULL,
    `total_gross_pay` DECIMAL(10,2) DEFAULT 0.00,
    `total_deductions` DECIMAL(10,2) DEFAULT 0.00,
    `employee_count` INT UNSIGNED DEFAULT 0,
    `status` ENUM('draft', 'posted', 'processed') DEFAULT 'draft',
    `raw_data` LONGTEXT COMMENT 'Full JSON response from Xero API',
    `cached_at` DATETIME NOT NULL COMMENT 'When this payroll was cached',
    `is_cached` TINYINT(1) DEFAULT 0 COMMENT '1 if > 7 days old and cached',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    UNIQUE KEY `uk_xero_payroll_id` (`xero_payroll_id`),
    INDEX `idx_payment_date` (`payment_date`),
    INDEX `idx_cached` (`is_cached`, `cached_at`),
    INDEX `idx_pay_period` (`pay_period_start`, `pay_period_end`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Xero payroll runs';

-- ============================================================================
-- Table: xero_payroll_deductions
-- Purpose: Individual staff account deductions from each payroll
-- ============================================================================
CREATE TABLE IF NOT EXISTS `xero_payroll_deductions` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `payroll_id` INT UNSIGNED NOT NULL COMMENT 'FK to xero_payrolls',
    `xero_employee_id` VARCHAR(100) NOT NULL COMMENT 'Xero employee ID',
    `employee_name` VARCHAR(255) NOT NULL,
    `user_id` INT UNSIGNED NULL COMMENT 'FK to staff users table (if mapped)',
    `vend_customer_id` VARCHAR(100) NULL COMMENT 'Vend customer ID (if mapped)',
    
    -- Deduction details
    `deduction_type` VARCHAR(100) NOT NULL COMMENT 'e.g., Staff Account, Staff Purchase',
    `deduction_code` VARCHAR(50) NULL COMMENT 'Xero deduction code',
    `amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Deduction amount from payslip',
    `description` TEXT NULL,
    
    -- Payment allocation tracking
    `vend_payment_id` VARCHAR(100) NULL COMMENT 'Vend payment ID after allocation',
    `allocated_amount` DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Amount successfully allocated to Vend',
    `allocation_status` ENUM('pending', 'allocated', 'failed', 'partial') DEFAULT 'pending',
    `allocated_at` DATETIME NULL COMMENT 'When payment was allocated to Vend',
    `allocation_error` TEXT NULL COMMENT 'Error message if allocation failed',
    
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (`payroll_id`) REFERENCES `xero_payrolls`(`id`) ON DELETE CASCADE,
    INDEX `idx_employee` (`xero_employee_id`),
    INDEX `idx_user_vend` (`user_id`, `vend_customer_id`),
    INDEX `idx_allocation_status` (`allocation_status`),
    INDEX `idx_payroll_employee` (`payroll_id`, `xero_employee_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Staff account deductions from Xero payrolls';

-- ============================================================================
-- Table: staff_account_reconciliation
-- Purpose: Reconciliation between Xero deductions and Vend balances
-- ============================================================================
CREATE TABLE IF NOT EXISTS `staff_account_reconciliation` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NULL,
    `vend_customer_id` VARCHAR(100) NOT NULL,
    `employee_name` VARCHAR(255) NOT NULL,
    
    -- Xero side (what they owe/paid)
    `total_xero_deductions` DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Total from Xero payrolls',
    `total_allocated` DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Total allocated to Vend',
    `pending_allocation` DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Not yet allocated',
    
    -- Vend side (current balance)
    `vend_balance` DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Current Vend customer balance',
    `vend_balance_updated_at` DATETIME NULL,
    
    -- Reconciliation result
    `outstanding_amount` DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Balance - Allocated (what they still owe)',
    `status` ENUM('balanced', 'overpaid', 'underpaid', 'pending') DEFAULT 'pending',
    `last_reconciled_at` DATETIME NULL,
    `notes` TEXT NULL,
    
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    UNIQUE KEY `idx_vend_customer` (`vend_customer_id`),
    INDEX `idx_user` (`user_id`),
    INDEX `idx_status` (`status`),
    INDEX `idx_outstanding` (`outstanding_amount`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Reconciliation between Xero and Vend';

-- ============================================================================
-- Table: payment_allocation_log
-- Purpose: Audit trail for all payment allocations
-- ============================================================================
CREATE TABLE IF NOT EXISTS `payment_allocation_log` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `deduction_id` INT UNSIGNED NULL COMMENT 'FK to xero_payroll_deductions',
    `vend_customer_id` VARCHAR(100) NOT NULL,
    `employee_name` VARCHAR(255) NOT NULL,
    
    `action` ENUM('allocate', 'void', 'adjust', 'retry') NOT NULL,
    `amount` DECIMAL(10,2) NOT NULL,
    `vend_payment_id` VARCHAR(100) NULL,
    `vend_response` TEXT NULL COMMENT 'Full Vend API response',
    
    `success` TINYINT(1) DEFAULT 0,
    `error_message` TEXT NULL,
    
    `performed_by` INT UNSIGNED NULL COMMENT 'User ID who performed action',
    `performed_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `ip_address` VARCHAR(45) NULL,
    
    FOREIGN KEY (`deduction_id`) REFERENCES `xero_payroll_deductions`(`id`) ON DELETE SET NULL,
    INDEX `idx_customer` (`vend_customer_id`),
    INDEX `idx_action` (`action`, `performed_at`),
    INDEX `idx_success` (`success`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Audit log for payment allocations';

-- ============================================================================
-- Employee Mapping Table
-- Purpose: Map Xero employees to Vend customers and internal users
-- ============================================================================
CREATE TABLE IF NOT EXISTS `employee_mapping` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `xero_employee_id` VARCHAR(100) NOT NULL,
    `xero_employee_name` VARCHAR(255) NOT NULL,
    `user_id` INT UNSIGNED NULL COMMENT 'Internal user ID',
    `vend_customer_id` VARCHAR(100) NULL COMMENT 'Vend customer ID',
    `vend_customer_code` VARCHAR(100) NULL,
    
    `mapping_status` ENUM('mapped', 'unmapped', 'conflict') DEFAULT 'unmapped',
    `last_verified_at` DATETIME NULL,
    `notes` TEXT NULL,
    
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    UNIQUE KEY `uk_xero_employee_id` (`xero_employee_id`),
    INDEX `idx_user` (`user_id`),
    INDEX `idx_vend_customer` (`vend_customer_id`),
    INDEX `idx_status` (`mapping_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Xero employee to Vend customer mapping';
