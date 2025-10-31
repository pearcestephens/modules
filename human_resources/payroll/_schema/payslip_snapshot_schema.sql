-- ============================================================================
-- PAYSLIP SNAPSHOT SYSTEM - COMPLETE SCHEMA
-- ============================================================================
-- Creates all tables needed for payslip snapshot and line items
-- ============================================================================

-- ============================================================================
-- TABLE: payroll_payslips (MAIN SNAPSHOT TABLE)
-- ============================================================================

CREATE TABLE IF NOT EXISTS `payroll_payslips` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

  -- Xero References
  `xero_payslip_id` VARCHAR(100) NOT NULL UNIQUE COMMENT 'Xero PaySlip ID',
  `xero_pay_run_id` VARCHAR(100) NOT NULL COMMENT 'Xero PayRun ID',
  `xero_employee_id` VARCHAR(100) NOT NULL COMMENT 'Xero Employee ID',

  -- Staff Reference
  `staff_id` INT UNSIGNED NOT NULL COMMENT 'FK to users.id',

  -- Period Information
  `period_start` DATE NOT NULL COMMENT 'Pay period start date',
  `period_end` DATE NOT NULL COMMENT 'Pay period end date',
  `payment_date` DATE NOT NULL COMMENT 'Actual payment date',

  -- Financial Summary
  `gross_earnings` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Total gross earnings',
  `total_deductions` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Total deductions',
  `total_reimbursements` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Total reimbursements',
  `net_pay` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Net pay (take home)',
  `tax` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Total tax withheld',

  -- Deputy Integration
  `deputy_total_hours` DECIMAL(10,2) NULL COMMENT 'Total hours from Deputy',
  `deputy_ordinary_hours` DECIMAL(10,2) NULL COMMENT 'Ordinary hours from Deputy',
  `deputy_overtime_hours` DECIMAL(10,2) NULL COMMENT 'Overtime hours from Deputy',

  -- Snapshot Data
  `snapshot_json` LONGTEXT NULL COMMENT 'Complete snapshot (Xero + Deputy + CIS)',
  `snapshot_hash` CHAR(64) NULL COMMENT 'SHA256 hash of snapshot for integrity',
  `snapshot_status` ENUM('draft', 'final', 'amended') NOT NULL DEFAULT 'draft' COMMENT 'Snapshot status',
  `snapshot_created_at` DATETIME NULL COMMENT 'When snapshot was created',

  -- Timestamps
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,

  -- Indexes
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_xero_payslip` (`xero_payslip_id`),
  INDEX `idx_staff_id` (`staff_id`),
  INDEX `idx_xero_pay_run` (`xero_pay_run_id`),
  INDEX `idx_period` (`period_start`, `period_end`),
  INDEX `idx_payment_date` (`payment_date`),
  INDEX `idx_snapshot_status` (`snapshot_status`),

  -- Foreign Key
  CONSTRAINT `fk_payslip_staff`
    FOREIGN KEY (`staff_id`) REFERENCES `users`(`id`)
    ON DELETE CASCADE

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Payslip snapshots - immutable final truth from Xero/Deputy/CIS';


-- ============================================================================
-- TABLE: payroll_earnings_lines
-- ============================================================================

CREATE TABLE IF NOT EXISTS `payroll_earnings_lines` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

  `payslip_id` BIGINT UNSIGNED NOT NULL COMMENT 'FK to payroll_payslips.id',

  -- Xero Reference
  `xero_earnings_rate_id` VARCHAR(100) NULL COMMENT 'Xero Earnings Rate ID',

  -- Earnings Details
  `earnings_type` VARCHAR(50) NOT NULL COMMENT 'Type: Regular, Overtime, Bonus, etc',
  `description` VARCHAR(255) NOT NULL COMMENT 'Line description',
  `rate_per_unit` DECIMAL(10,4) NOT NULL DEFAULT 0.0000 COMMENT 'Hourly rate or rate per unit',
  `number_of_units` DECIMAL(10,4) NOT NULL DEFAULT 0.0000 COMMENT 'Hours or units',
  `fixed_amount` DECIMAL(10,2) NULL COMMENT 'Fixed amount (if not rate-based)',
  `amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Total amount for this line',

  -- Metadata
  `line_order` INT NOT NULL DEFAULT 0 COMMENT 'Display order',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

  -- Indexes
  PRIMARY KEY (`id`),
  INDEX `idx_payslip_id` (`payslip_id`),
  INDEX `idx_earnings_type` (`earnings_type`),

  -- Foreign Key
  CONSTRAINT `fk_earnings_payslip`
    FOREIGN KEY (`payslip_id`) REFERENCES `payroll_payslips`(`id`)
    ON DELETE CASCADE

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Individual earnings line items from payslips';


-- ============================================================================
-- TABLE: payroll_deduction_lines
-- ============================================================================

CREATE TABLE IF NOT EXISTS `payroll_deduction_lines` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

  `payslip_id` BIGINT UNSIGNED NOT NULL COMMENT 'FK to payroll_payslips.id',

  -- Xero Reference
  `xero_deduction_type_id` VARCHAR(100) NULL COMMENT 'Xero Deduction Type ID',

  -- Deduction Details
  `deduction_type` VARCHAR(50) NOT NULL COMMENT 'Type: Tax, KiwiSaver, PAYE, etc',
  `description` VARCHAR(255) NOT NULL COMMENT 'Line description',
  `percentage` DECIMAL(5,2) NULL COMMENT 'Percentage (if percentage-based)',
  `fixed_amount` DECIMAL(10,2) NULL COMMENT 'Fixed amount (if fixed)',
  `amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Total deduction amount',

  -- Metadata
  `line_order` INT NOT NULL DEFAULT 0 COMMENT 'Display order',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

  -- Indexes
  PRIMARY KEY (`id`),
  INDEX `idx_payslip_id` (`payslip_id`),
  INDEX `idx_deduction_type` (`deduction_type`),

  -- Foreign Key
  CONSTRAINT `fk_deduction_payslip`
    FOREIGN KEY (`payslip_id`) REFERENCES `payroll_payslips`(`id`)
    ON DELETE CASCADE

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Individual deduction line items from payslips';


-- ============================================================================
-- TABLE: payroll_leave_lines
-- ============================================================================

CREATE TABLE IF NOT EXISTS `payroll_leave_lines` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

  `payslip_id` BIGINT UNSIGNED NOT NULL COMMENT 'FK to payroll_payslips.id',

  -- Xero Reference
  `xero_leave_type_id` VARCHAR(100) NULL COMMENT 'Xero Leave Type ID',

  -- Leave Details
  `leave_type` VARCHAR(50) NOT NULL COMMENT 'Type: Annual, Sick, etc',
  `description` VARCHAR(255) NOT NULL COMMENT 'Line description',
  `hours_taken` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Hours taken this period',
  `hours_accrued` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Hours accrued this period',
  `balance` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Current balance',

  -- Metadata
  `line_order` INT NOT NULL DEFAULT 0 COMMENT 'Display order',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

  -- Indexes
  PRIMARY KEY (`id`),
  INDEX `idx_payslip_id` (`payslip_id`),
  INDEX `idx_leave_type` (`leave_type`),

  -- Foreign Key
  CONSTRAINT `fk_leave_payslip`
    FOREIGN KEY (`payslip_id`) REFERENCES `payroll_payslips`(`id`)
    ON DELETE CASCADE

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Leave line items from payslips';


-- ============================================================================
-- TABLE: payroll_reimbursement_lines
-- ============================================================================

CREATE TABLE IF NOT EXISTS `payroll_reimbursement_lines` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

  `payslip_id` BIGINT UNSIGNED NOT NULL COMMENT 'FK to payroll_payslips.id',

  -- Xero Reference
  `xero_reimbursement_type_id` VARCHAR(100) NULL COMMENT 'Xero Reimbursement Type ID',

  -- Reimbursement Details
  `reimbursement_type` VARCHAR(50) NOT NULL COMMENT 'Type: Expense, Mileage, etc',
  `description` VARCHAR(255) NOT NULL COMMENT 'Line description',
  `amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Reimbursement amount',

  -- Metadata
  `line_order` INT NOT NULL DEFAULT 0 COMMENT 'Display order',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

  -- Indexes
  PRIMARY KEY (`id`),
  INDEX `idx_payslip_id` (`payslip_id`),
  INDEX `idx_reimbursement_type` (`reimbursement_type`),

  -- Foreign Key
  CONSTRAINT `fk_reimbursement_payslip`
    FOREIGN KEY (`payslip_id`) REFERENCES `payroll_payslips`(`id`)
    ON DELETE CASCADE

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Reimbursement line items from payslips';


-- ============================================================================
-- VERIFICATION QUERIES
-- ============================================================================

-- Verify all tables created
SELECT
  TABLE_NAME,
  TABLE_ROWS,
  CREATE_TIME
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = 'jcepnzzkmj'
AND TABLE_NAME LIKE 'payroll_%_lines'
ORDER BY TABLE_NAME;

-- Verify foreign keys
SELECT
  CONSTRAINT_NAME,
  TABLE_NAME,
  REFERENCED_TABLE_NAME,
  REFERENCED_COLUMN_NAME
FROM information_schema.KEY_COLUMN_USAGE
WHERE TABLE_SCHEMA = 'jcepnzzkmj'
AND TABLE_NAME IN ('payroll_payslips', 'payroll_earnings_lines', 'payroll_deduction_lines', 'payroll_leave_lines', 'payroll_reimbursement_lines')
AND REFERENCED_TABLE_NAME IS NOT NULL;
