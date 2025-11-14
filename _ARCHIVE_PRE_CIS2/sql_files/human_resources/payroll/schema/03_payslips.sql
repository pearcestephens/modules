-- ============================================================================
-- PAYROLL PAYSLIPS & BANK EXPORTS
-- Complete payslip storage with bonuses, deductions, and export capability
-- ============================================================================

-- Main payslips table
CREATE TABLE IF NOT EXISTS `payroll_payslips` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `staff_id` INT UNSIGNED NOT NULL,
  `period_start` DATE NOT NULL,
  `period_end` DATE NOT NULL,

  -- Earnings breakdown
  `ordinary_hours` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `ordinary_pay` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `overtime_hours` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `overtime_pay` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `night_shift_hours` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `night_shift_pay` DECIMAL(10,2) NOT NULL DEFAULT 0.00,

  -- Public holiday pay
  `public_holiday_hours` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `public_holiday_pay` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `alternative_holidays_entitled` INT NOT NULL DEFAULT 0 COMMENT 'Days in lieu earned',

  -- Bonuses
  `vape_drops_bonus` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `google_reviews_bonus` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `monthly_bonus` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `commission` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `acting_position_pay` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `gamification_bonus` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Future: from gamification system',
  `total_bonuses` DECIMAL(10,2) NOT NULL DEFAULT 0.00,

  -- Deductions
  `leave_deduction` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Unpaid leave',
  `advances_deduction` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Loans/advances repayment',
  `student_loan_deduction` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `kiwisaver_deduction` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `other_deductions` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `total_deductions` DECIMAL(10,2) NOT NULL DEFAULT 0.00,

  -- Totals
  `gross_pay` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `net_pay` DECIMAL(10,2) NOT NULL DEFAULT 0.00,

  -- Metadata
  `timesheets_json` LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NULL COMMENT 'Full timesheet data with amendments applied' CHECK (json_valid(`timesheets_json`)),
  `amendments_applied` INT NOT NULL DEFAULT 0 COMMENT 'Number of amendments applied',
  `calculation_notes` TEXT NULL COMMENT 'Any notes about calculations or exceptions',

  -- Status tracking
  `status` ENUM('calculated', 'reviewed', 'approved', 'exported', 'paid', 'cancelled') NOT NULL DEFAULT 'calculated',
  `reviewed_by` INT UNSIGNED NULL,
  `reviewed_at` DATETIME NULL,
  `approved_by` INT UNSIGNED NULL,
  `approved_at` DATETIME NULL,
  `exported_at` DATETIME NULL,
  `paid_at` DATETIME NULL,

  -- Integration
  `xero_payslip_id` VARCHAR(100) NULL COMMENT 'Xero payslip ID if synced',
  `xero_synced_at` DATETIME NULL,
  `deputy_synced_at` DATETIME NULL,

  -- Timestamps
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_staff_period` (`staff_id`, `period_start`, `period_end`),
  KEY `idx_period` (`period_start`, `period_end`),
  KEY `idx_status` (`status`),
  KEY `idx_created` (`created_at`),
  KEY `idx_xero_payslip` (`xero_payslip_id`),

  CONSTRAINT `fk_payslip_staff` FOREIGN KEY (`staff_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_payslip_reviewed_by` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_payslip_approved_by` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Complete payslip storage with all earnings, bonuses, and deductions';

-- Bank export history
CREATE TABLE IF NOT EXISTS `payroll_bank_exports` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `export_type` VARCHAR(50) NOT NULL COMMENT 'asb_csv, direct_credit, anz_csv, etc.',
  `period_start` DATE NOT NULL,
  `period_end` DATE NOT NULL,
  `payslip_count` INT NOT NULL,
  `total_amount` DECIMAL(12,2) NOT NULL,
  `file_name` VARCHAR(255) NOT NULL,
  `file_path` VARCHAR(500) NULL,
  `file_hash` VARCHAR(64) NULL COMMENT 'SHA256 hash for integrity',
  `exported_by` INT UNSIGNED NOT NULL,
  `exported_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `notes` TEXT NULL,

  PRIMARY KEY (`id`),
  KEY `idx_period` (`period_start`, `period_end`),
  KEY `idx_type` (`export_type`),
  KEY `idx_exported` (`exported_at`),

  CONSTRAINT `fk_export_user` FOREIGN KEY (`exported_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Track all bank payment file exports';

-- Vape drops tracking (if not exists)
CREATE TABLE IF NOT EXISTS `vape_drops` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `staff_id` INT UNSIGNED NOT NULL,
  `customer_name` VARCHAR(255) NULL,
  `address` TEXT NULL,
  `completed` TINYINT(1) NOT NULL DEFAULT 0,
  `completed_at` DATETIME NULL,
  `bonus_paid` TINYINT(1) NOT NULL DEFAULT 0,
  `bonus_paid_in_payslip_id` BIGINT UNSIGNED NULL,
  `notes` TEXT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  KEY `idx_staff` (`staff_id`, `completed`),
  KEY `idx_completed` (`completed_at`),
  KEY `idx_bonus_paid` (`bonus_paid`),

  CONSTRAINT `fk_vape_drop_staff` FOREIGN KEY (`staff_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_vape_drop_payslip` FOREIGN KEY (`bonus_paid_in_payslip_id`) REFERENCES `payroll_payslips` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Google reviews tracking (if not exists)
CREATE TABLE IF NOT EXISTS `google_reviews` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `staff_id` INT UNSIGNED NOT NULL,
  `customer_name` VARCHAR(255) NULL,
  `review_text` TEXT NULL,
  `rating` TINYINT UNSIGNED NOT NULL COMMENT '1-5 stars',
  `review_date` DATE NOT NULL,
  `verified` TINYINT(1) NOT NULL DEFAULT 0,
  `verified_at` DATETIME NULL,
  `verified_by` INT UNSIGNED NULL,
  `bonus_paid` TINYINT(1) NOT NULL DEFAULT 0,
  `bonus_paid_in_payslip_id` BIGINT UNSIGNED NULL,
  `review_url` VARCHAR(500) NULL,
  `notes` TEXT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  KEY `idx_staff` (`staff_id`, `verified`),
  KEY `idx_review_date` (`review_date`),
  KEY `idx_bonus_paid` (`bonus_paid`),

  CONSTRAINT `fk_google_review_staff` FOREIGN KEY (`staff_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_google_review_verified_by` FOREIGN KEY (`verified_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_google_review_payslip` FOREIGN KEY (`bonus_paid_in_payslip_id`) REFERENCES `payroll_payslips` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Monthly bonuses (if not exists)
CREATE TABLE IF NOT EXISTS `monthly_bonuses` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `staff_id` INT UNSIGNED NOT NULL,
  `bonus_type` VARCHAR(100) NOT NULL COMMENT 'performance, sales_target, manager_discretion, etc.',
  `bonus_amount` DECIMAL(10,2) NOT NULL,
  `pay_period_start` DATE NOT NULL,
  `pay_period_end` DATE NOT NULL,
  `reason` TEXT NULL,
  `approved` TINYINT(1) NOT NULL DEFAULT 0,
  `approved_by` INT UNSIGNED NULL,
  `approved_at` DATETIME NULL,
  `paid_in_payslip_id` BIGINT UNSIGNED NULL,
  `created_by` INT UNSIGNED NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  KEY `idx_staff_period` (`staff_id`, `pay_period_start`, `pay_period_end`),
  KEY `idx_approved` (`approved`),

  CONSTRAINT `fk_monthly_bonus_staff` FOREIGN KEY (`staff_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_monthly_bonus_approved_by` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_monthly_bonus_payslip` FOREIGN KEY (`paid_in_payslip_id`) REFERENCES `payroll_payslips` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_monthly_bonus_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Staff advances/loans (if not exists)
CREATE TABLE IF NOT EXISTS `staff_advances` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `staff_id` INT UNSIGNED NOT NULL,
  `advance_amount` DECIMAL(10,2) NOT NULL,
  `deduction_amount` DECIMAL(10,2) NOT NULL COMMENT 'Amount to deduct per pay period',
  `deduction_start_date` DATE NOT NULL,
  `deduction_end_date` DATE NULL COMMENT 'When fully repaid',
  `total_deducted` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `balance_remaining` DECIMAL(10,2) NOT NULL,
  `reason` TEXT NULL,
  `status` ENUM('pending', 'approved', 'active', 'completed', 'cancelled') NOT NULL DEFAULT 'pending',
  `approved_by` INT UNSIGNED NULL,
  `approved_at` DATETIME NULL,
  `created_by` INT UNSIGNED NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  KEY `idx_staff_status` (`staff_id`, `status`),
  KEY `idx_deduction_period` (`deduction_start_date`, `deduction_end_date`),

  CONSTRAINT `fk_advance_staff` FOREIGN KEY (`staff_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_advance_approved_by` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_advance_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Deputy timesheets cache (if not exists)
-- NOTE: outlet_id references vend_outlets which may not have matching data type
-- Changed to INT to match users.outlet column instead of vend_outlets.id
CREATE TABLE IF NOT EXISTS deputy_timesheets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    deputy_id INT NOT NULL COMMENT 'Deputy timesheet ID',
    staff_id INT NOT NULL COMMENT 'CIS staff ID',
    outlet_id INT NULL COMMENT 'Store/outlet ID (matches users.default_outlet)',
    date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    total_hours DECIMAL(5, 2) NOT NULL,
    break_hours DECIMAL(5, 2) DEFAULT 0.00,
    ordinary_hours DECIMAL(5, 2) DEFAULT 0.00,
    overtime_hours DECIMAL(5, 2) DEFAULT 0.00,
    night_shift_hours DECIMAL(5, 2) DEFAULT 0.00,
    public_holiday_hours DECIMAL(5, 2) DEFAULT 0.00,
    is_public_holiday BOOLEAN DEFAULT FALSE,
    cost DECIMAL(10, 2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_deputy_timesheet (deputy_id),
    KEY idx_staff (staff_id),
    KEY idx_date (date),
    KEY idx_outlet (outlet_id),
    CONSTRAINT fk_deputy_timesheet_staff FOREIGN KEY (staff_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Cached Deputy timesheet data for fast payroll processing';

-- Indexes for performance
CREATE INDEX IF NOT EXISTS idx_payslips_staff_status ON payroll_payslips(staff_id, status);

-- MariaDB 10.5 doesn't support filtered indexes - create regular index instead
CREATE INDEX IF NOT EXISTS idx_payslips_export_ready ON payroll_payslips(status, period_end);

-- ============================================================================
-- VIEWS FOR REPORTING
-- ============================================================================

-- Current period payslips summary
CREATE OR REPLACE VIEW v_current_period_payslips AS
SELECT
    p.*,
    CONCAT(u.first_name, ' ', u.last_name) as staff_name,
    u.xero_id,
    o.name as outlet_name,
    CONCAT(rev.first_name, ' ', rev.last_name) as reviewed_by_name,
    CONCAT(app.first_name, ' ', app.last_name) as approved_by_name
FROM payroll_payslips p
JOIN users u ON p.staff_id = u.id
LEFT JOIN vend_outlets o ON u.default_outlet = o.id
LEFT JOIN users rev ON p.reviewed_by = rev.id
LEFT JOIN users app ON p.approved_by = app.id
WHERE p.period_start = (
    SELECT MAX(period_start) FROM payroll_payslips
);

-- Staff bonus summary
CREATE OR REPLACE VIEW v_staff_bonus_summary AS
SELECT
    u.id as staff_id,
    CONCAT(u.first_name, ' ', u.last_name) as staff_name,
    o.name as outlet_name,
    COUNT(DISTINCT p.id) as total_payslips,
    SUM(p.vape_drops_bonus) as total_vape_drops_bonus,
    SUM(p.google_reviews_bonus) as total_google_reviews_bonus,
    SUM(p.monthly_bonus) as total_monthly_bonus,
    SUM(p.commission) as total_commission,
    SUM(p.acting_position_pay) as total_acting_position_pay,
    SUM(p.gamification_bonus) as total_gamification_bonus,
    SUM(p.vape_drops_bonus + p.google_reviews_bonus + p.monthly_bonus +
        p.commission + p.acting_position_pay + p.gamification_bonus) as total_all_bonuses,
    MIN(p.period_start) as first_period,
    MAX(p.period_end) as last_period
FROM users u
LEFT JOIN vend_outlets o ON u.default_outlet = o.id
LEFT JOIN payroll_payslips p ON u.id = p.staff_id
GROUP BY u.id, u.first_name, u.last_name, o.name;
