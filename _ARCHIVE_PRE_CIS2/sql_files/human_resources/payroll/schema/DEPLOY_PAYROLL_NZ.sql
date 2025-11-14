-- ============================================================================
-- PAYROLL AI AUTOMATION - NZ COMPLIANT SCHEMA
-- ============================================================================
-- Quick Deploy Commands (Copy & Paste Ready)
--
-- Database: jcepnzzkmj
-- User: jcepnzzkmj
-- Password: [ENTER YOUR PASSWORD HERE]
-- ============================================================================

-- Step 1: Drop existing tables if re-deploying
SET @OLD_FOREIGN_KEY_CHECKS = @@FOREIGN_KEY_CHECKS;
SET FOREIGN_KEY_CHECKS = 0;

DROP VIEW IF EXISTS `v_pending_ai_reviews`;
DROP VIEW IF EXISTS `v_payroll_automation_dashboard`;
DROP VIEW IF EXISTS `v_staff_amendment_patterns`;
DROP VIEW IF EXISTS `v_vend_payment_queue`;
DROP VIEW IF EXISTS `v_bank_payment_batch_summary`;
DROP VIEW IF EXISTS `v_ai_rule_effectiveness`;
DROP VIEW IF EXISTS `v_notification_delivery_stats`;
DROP VIEW IF EXISTS `v_nz_upcoming_public_holidays`;
DROP VIEW IF EXISTS `v_nz_alternative_holidays_balance`;
DROP VIEW IF EXISTS `v_nz_leave_balances_summary`;
DROP VIEW IF EXISTS `v_nz_kiwisaver_compliance`;
DROP VIEW IF EXISTS `v_ai_decisions_pending_review`;
DROP VIEW IF EXISTS `v_ai_decisions_dashboard`;
DROP VIEW IF EXISTS `v_ai_decisions_low_confidence`;
DROP VIEW IF EXISTS `v_discrepancies_pending_ai_review`;
DROP VIEW IF EXISTS `v_discrepancies_awaiting_correction`;
DROP VIEW IF EXISTS `v_discrepancies_dashboard`;
DROP VIEW IF EXISTS `v_discrepancies_frequent_submitters`;
DROP VIEW IF EXISTS `v_ai_check_sessions_active`;
DROP VIEW IF EXISTS `v_ai_check_critical_issues`;
DROP VIEW IF EXISTS `v_ai_check_performance_30d`;
DROP VIEW IF EXISTS `v_ai_check_rule_effectiveness`;
DROP VIEW IF EXISTS `v_ai_rule_effectiveness`;

DROP TABLE IF EXISTS
  `payroll_timesheet_amendment_history`,
  `payroll_timesheet_amendments`,
  `payroll_payrun_adjustment_history`,
  `payroll_payrun_line_adjustments`,
  `payroll_vend_payment_allocations`,
  `payroll_vend_payment_requests`,
  `payroll_bank_payments`,
  `payroll_bank_payment_batches`,
  `payroll_ai_feedback`,
  `payroll_ai_decisions`,
  `payroll_context_snapshots`,
  `payroll_activity_log`,
  `payroll_ai_rule_executions`,
  `payroll_ai_rules`,
  `payroll_notifications`,
  `payroll_process_metrics`,
  `payroll_nz_deduction_applications`,
  `payroll_nz_statutory_deductions`,
  `payroll_ai_decision_history`,
  `payroll_ai_decision_performance`,
  `payroll_ai_decision_requests`,
  `payroll_ai_decision_rules`,
  `payroll_ai_check_results`,
  `payroll_ai_check_rules`,
  `payroll_ai_check_sessions`,
  `payroll_wages_discrepancy_history`,
  `payroll_wages_discrepancy_patterns`,
  `payroll_wages_discrepancies`,
  `payroll_nz_alternative_holidays`,
  `payroll_nz_public_holidays`,
  `payroll_nz_leave_balances`,
  `payroll_nz_leave_requests`,
  `payroll_nz_pay_rates`,
  `payroll_nz_minimum_wage_checks`,
  `payroll_nz_kiwisaver`,
  `payroll_nz_student_loans`,
  `payroll_nz_tax_codes`,
  `payroll_nz_ird_filings`;

SET FOREIGN_KEY_CHECKS = @OLD_FOREIGN_KEY_CHECKS;

-- ============================================================================
-- CREATE NZ COMPLIANCE TABLES
-- ============================================================================

-- NZ Public Holidays (for statutory days, alternative holidays, etc.)
CREATE TABLE IF NOT EXISTS `payroll_nz_public_holidays` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `holiday_date` DATE NOT NULL,
  `holiday_name` VARCHAR(100) NOT NULL,
  `holiday_type` ENUM('national', 'regional', 'mondayised') NOT NULL DEFAULT 'national',
  `region` VARCHAR(50) NULL COMMENT 'For regional holidays (e.g., Auckland Anniversary)',
  `is_mondayised` TINYINT(1) DEFAULT 0 COMMENT 'Mondayised to Monday',
  `original_date` DATE NULL COMMENT 'If mondayised, original date',

  -- NZ-specific
  `is_statutory` TINYINT(1) DEFAULT 1 COMMENT 'Counts towards statutory holidays',
  `minimum_entitlement_applies` TINYINT(1) DEFAULT 1,
  `otherwise_working_day_required` TINYINT(1) DEFAULT 1,

  -- Audit
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_holiday_date` (`holiday_date`),
  KEY `idx_holiday_type` (`holiday_type`),
  KEY `idx_region` (`region`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='NZ public holidays for payroll calculations';

-- NZ Alternative Holidays (Days in Lieu)
CREATE TABLE IF NOT EXISTS `payroll_nz_alternative_holidays` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `staff_id` INT UNSIGNED NOT NULL,
  `xero_employee_id` VARCHAR(50) NULL,

  -- Holiday worked
  `public_holiday_id` INT UNSIGNED NULL,
  `holiday_date` DATE NOT NULL,
  `holiday_name` VARCHAR(100) NOT NULL,

  -- Work details
  `hours_worked` DECIMAL(5,2) NOT NULL,
  `pay_rate` DECIMAL(10,4) NOT NULL,
  `worked_at_outlet` VARCHAR(50) NULL,

  -- Alternative holiday created
  `alternative_holiday_created` TINYINT(1) DEFAULT 0,
  `xero_leave_application_id` VARCHAR(50) NULL COMMENT 'Xero API ID',
  `days_earned` DECIMAL(5,2) DEFAULT 1.00 COMMENT 'Usually 1 full day',

  -- Payment (time and a half for hours worked)
  `time_and_half_paid` DECIMAL(10,2) NULL,
  `paid_in_payroll_run` VARCHAR(50) NULL,
  `paid_at` DATETIME NULL,

  -- Status
  `status` ENUM('pending', 'confirmed', 'applied_to_xero', 'cancelled') NOT NULL DEFAULT 'pending',
  `status_changed_at` DATETIME NULL,

  -- Alternative holiday usage
  `taken_date` DATE NULL COMMENT 'When staff took the day in lieu',
  `taken_hours` DECIMAL(5,2) NULL,
  `balance_remaining` DECIMAL(5,2) NULL,

  -- Audit
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  `created_by` INT UNSIGNED NULL,
  `notes` TEXT NULL,

  PRIMARY KEY (`id`),
  KEY `idx_staff` (`staff_id`),
  KEY `idx_holiday_date` (`holiday_date`),
  KEY `idx_status` (`status`),
  KEY `idx_public_holiday` (`public_holiday_id`),
  CONSTRAINT `fk_alt_holiday_public_holiday`
    FOREIGN KEY (`public_holiday_id`)
    REFERENCES `payroll_nz_public_holidays` (`id`)
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='NZ alternative holidays (days in lieu) tracking';

-- NZ Leave Balances (Annual, Sick, Bereavement, etc.)
CREATE TABLE IF NOT EXISTS `payroll_nz_leave_balances` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `staff_id` INT UNSIGNED NOT NULL,
  `xero_employee_id` VARCHAR(50) NULL,

  -- Leave type (NZ-specific)
  `leave_type` ENUM(
    'annual_leave',           -- 4 weeks per year minimum
    'sick_leave',             -- 10 days per year after 6 months
    'bereavement_leave',      -- 3 days
    'alternative_holiday',    -- Days in lieu
    'parental_leave',         -- Primary/partner/extended
    'domestic_violence_leave',-- 10 days per year
    'public_holiday',         -- Public holidays worked
    'unpaid_leave',           -- Unpaid leave
    'other'
  ) NOT NULL,

  -- Balance tracking
  `balance_hours` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `balance_days` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `accrued_hours` DECIMAL(10,2) DEFAULT 0.00,
  `taken_hours` DECIMAL(10,2) DEFAULT 0.00,

  -- Accrual details
  `accrual_rate_per_hour` DECIMAL(10,6) NULL COMMENT 'For ongoing accrual',
  `last_accrual_date` DATE NULL,
  `next_accrual_date` DATE NULL,

  -- Anniversary dates (NZ employment law)
  `employment_start_date` DATE NULL,
  `leave_entitlement_start_date` DATE NULL COMMENT 'When leave entitlement started',
  `anniversary_date` DATE NULL COMMENT 'Annual leave anniversary',

  -- Sync with Xero
  `synced_from_xero` TINYINT(1) DEFAULT 0,
  `xero_sync_at` DATETIME NULL,
  `xero_balance_hours` DECIMAL(10,2) NULL,

  -- Audit
  `as_at_date` DATE NOT NULL COMMENT 'Balance accurate as at this date',
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_staff_leave_type` (`staff_id`, `leave_type`),
  KEY `idx_leave_type` (`leave_type`),
  KEY `idx_anniversary` (`anniversary_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='NZ leave balance tracking per employee';

-- NZ Leave Requests (Staff-initiated)
CREATE TABLE IF NOT EXISTS `payroll_nz_leave_requests` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `staff_id` INT UNSIGNED NOT NULL,
  `xero_employee_id` VARCHAR(50) NULL,

  -- Leave details
  `leave_type` ENUM(
    'annual_leave',
    'sick_leave',
    'bereavement_leave',
    'alternative_holiday',
    'parental_leave',
    'domestic_violence_leave',
    'unpaid_leave',
    'other'
  ) NOT NULL,

  `start_date` DATE NOT NULL,
  `end_date` DATE NOT NULL,
  `start_time` TIME NULL COMMENT 'For partial day leave',
  `end_time` TIME NULL,
  `total_hours` DECIMAL(5,2) NOT NULL,
  `total_days` DECIMAL(5,2) NOT NULL,

  -- Request details
  `reason` TEXT NULL,
  `is_partial_day` TINYINT(1) DEFAULT 0,
  `covers_public_holiday` TINYINT(1) DEFAULT 0,

  -- Approval workflow
  `status` ENUM('pending', 'approved', 'declined', 'cancelled', 'applied_to_xero') NOT NULL DEFAULT 'pending',
  `status_changed_at` DATETIME NULL,
  `approved_by` INT UNSIGNED NULL,
  `approved_at` DATETIME NULL,
  `decline_reason` TEXT NULL,

  -- Xero sync
  `xero_leave_application_id` VARCHAR(50) NULL,
  `applied_to_xero` TINYINT(1) DEFAULT 0,
  `xero_response` JSON NULL,

  -- Audit
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  KEY `idx_staff` (`staff_id`),
  KEY `idx_leave_type` (`leave_type`),
  KEY `idx_status` (`status`),
  KEY `idx_dates` (`start_date`, `end_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='NZ leave requests from staff';

-- NZ Pay Rates (for different types of work)
CREATE TABLE IF NOT EXISTS `payroll_nz_pay_rates` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `staff_id` INT UNSIGNED NOT NULL,
  `xero_employee_id` VARCHAR(50) NULL,

  -- Rate type (NZ employment law)
  `rate_type` ENUM(
    'ordinary_time',          -- Normal hourly rate
    'time_and_half',          -- 1.5x (first 3 hours overtime)
    'double_time',            -- 2x (after 3 hours overtime)
    'public_holiday',         -- Time and a half + alternative holiday
    'sick_leave',             -- Relevant daily pay or average daily pay
    'annual_leave',           -- 8% or higher
    'bereavement_leave',      -- Relevant daily pay or average daily pay
    'alternative_holiday',    -- Relevant daily pay
    'other'
  ) NOT NULL DEFAULT 'ordinary_time',

  -- Rate details
  `hourly_rate` DECIMAL(10,4) NOT NULL,
  `effective_from` DATE NOT NULL,
  `effective_to` DATE NULL,

  -- NZ-specific calculations
  `ordinary_weekly_pay` DECIMAL(10,2) NULL COMMENT 'For relevant daily pay calculation',
  `average_daily_pay` DECIMAL(10,2) NULL COMMENT 'Last 52 weeks average',
  `average_weekly_pay` DECIMAL(10,2) NULL,

  -- Xero integration
  `xero_earnings_rate_id` VARCHAR(50) NULL,

  -- Audit
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  `created_by` INT UNSIGNED NULL,

  PRIMARY KEY (`id`),
  KEY `idx_staff_rate_type` (`staff_id`, `rate_type`),
  KEY `idx_effective_dates` (`effective_from`, `effective_to`),
  KEY `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='NZ pay rates per employee';

-- NZ Minimum Wage Compliance Tracking
CREATE TABLE IF NOT EXISTS `payroll_nz_minimum_wage_checks` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `check_date` DATE NOT NULL,
  `staff_id` INT UNSIGNED NOT NULL,

  -- NZ minimum wage rates (as at check date)
  `adult_minimum_wage` DECIMAL(10,4) NOT NULL COMMENT 'Currently $23.15/hour (2024)',
  `starting_out_wage` DECIMAL(10,4) NOT NULL COMMENT '80% of adult min',
  `training_wage` DECIMAL(10,4) NOT NULL COMMENT '80% of adult min',

  -- Staff details
  `staff_age` INT UNSIGNED NULL,
  `staff_hourly_rate` DECIMAL(10,4) NOT NULL,
  `applicable_minimum` DECIMAL(10,4) NOT NULL,

  -- Compliance check
  `is_compliant` TINYINT(1) NOT NULL,
  `shortfall_per_hour` DECIMAL(10,4) NULL,
  `weeks_at_risk` INT UNSIGNED NULL,
  `estimated_backpay` DECIMAL(10,2) NULL,

  -- Action taken
  `action_required` TINYINT(1) DEFAULT 0,
  `action_taken` TEXT NULL,
  `resolved_at` DATETIME NULL,

  -- Audit
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `checked_by` INT UNSIGNED NULL,

  PRIMARY KEY (`id`),
  KEY `idx_check_date` (`check_date`),
  KEY `idx_staff` (`staff_id`),
  KEY `idx_compliance` (`is_compliant`, `action_required`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='NZ minimum wage compliance tracking';

-- NZ KiwiSaver Tracking
CREATE TABLE IF NOT EXISTS `payroll_nz_kiwisaver` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `staff_id` INT UNSIGNED NOT NULL,
  `xero_employee_id` VARCHAR(50) NULL,

  -- KiwiSaver enrollment
  `is_enrolled` TINYINT(1) NOT NULL DEFAULT 0,
  `enrollment_date` DATE NULL,
  `opt_out_date` DATE NULL COMMENT 'If opted out',

  -- Contribution rates
  `employee_contribution_rate` DECIMAL(5,4) NOT NULL DEFAULT 0.0300 COMMENT '3% minimum',
  `employer_contribution_rate` DECIMAL(5,4) NOT NULL DEFAULT 0.0300 COMMENT '3% minimum',

  -- IRD details
  `ird_number` VARCHAR(20) NULL COMMENT 'Encrypted',
  `kiwisaver_scheme_name` VARCHAR(255) NULL,
  `scheme_provider` VARCHAR(255) NULL,

  -- Contribution tracking (YTD)
  `ytd_employee_contributions` DECIMAL(10,2) DEFAULT 0.00,
  `ytd_employer_contributions` DECIMAL(10,2) DEFAULT 0.00,
  `ytd_esct` DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Employer Superannuation Contribution Tax',

  -- ESCT rate (based on employee income)
  `esct_rate` DECIMAL(5,4) NULL COMMENT '10.5%, 17.5%, 28%, 33%',

  -- Status
  `status` ENUM('active', 'opted_out', 'contributions_holiday', 'inactive') NOT NULL DEFAULT 'active',
  `status_changed_at` DATETIME NULL,

  -- Audit
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_staff` (`staff_id`),
  KEY `idx_enrollment_status` (`is_enrolled`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='NZ KiwiSaver enrollment and contribution tracking';

-- NZ Student Loan Deductions
CREATE TABLE IF NOT EXISTS `payroll_nz_student_loans` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `staff_id` INT UNSIGNED NOT NULL,
  `xero_employee_id` VARCHAR(50) NULL,

  -- Student loan details
  `has_student_loan` TINYINT(1) NOT NULL DEFAULT 0,
  `deduction_rate` DECIMAL(5,4) NOT NULL DEFAULT 0.1200 COMMENT '12% standard rate',

  -- Thresholds (2024/2025 tax year)
  `repayment_threshold_annual` DECIMAL(10,2) NOT NULL DEFAULT 24128.00,
  `repayment_threshold_weekly` DECIMAL(10,2) NOT NULL DEFAULT 464.00,

  -- Deduction tracking (YTD)
  `ytd_deductions` DECIMAL(10,2) DEFAULT 0.00,
  `ytd_gross_earnings` DECIMAL(10,2) DEFAULT 0.00,

  -- IRD tracking
  `ird_borrower_reference` VARCHAR(50) NULL,

  -- Status
  `is_active` TINYINT(1) DEFAULT 1,
  `start_date` DATE NULL,
  `end_date` DATE NULL COMMENT 'When loan paid off',

  -- Audit
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_staff` (`staff_id`),
  KEY `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='NZ student loan deduction tracking';

-- NZ PAYE Tax Codes
CREATE TABLE IF NOT EXISTS `payroll_nz_tax_codes` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `staff_id` INT UNSIGNED NOT NULL,
  `xero_employee_id` VARCHAR(50) NULL,

  -- NZ tax code (IRD)
  `tax_code` VARCHAR(10) NOT NULL COMMENT 'M, M SL, ME, ME SL, SB, S, SH, ST, etc.',
  `is_primary_employment` TINYINT(1) DEFAULT 1,

  -- Tax rates (depend on code and income)
  `effective_from` DATE NOT NULL,
  `effective_to` DATE NULL,

  -- Special tax codes
  `has_student_loan` TINYINT(1) DEFAULT 0 COMMENT 'SL suffix',
  `special_rate` DECIMAL(5,4) NULL COMMENT 'For ST (special tax rate)',

  -- Tailored tax code (for ACC earners levy, etc.)
  `is_tailored` TINYINT(1) DEFAULT 0,
  `tailored_rate` DECIMAL(5,4) NULL,

  -- IRD notification
  `ird_notification_received` TINYINT(1) DEFAULT 0,
  `ird_notification_date` DATE NULL,

  -- Audit
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  `created_by` INT UNSIGNED NULL,

  PRIMARY KEY (`id`),
  KEY `idx_staff_current` (`staff_id`, `effective_to`),
  KEY `idx_tax_code` (`tax_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='NZ IRD tax codes per employee';

-- NZ IRD Filing History (for EMS/Payday filing)
CREATE TABLE IF NOT EXISTS `payroll_nz_ird_filings` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `filing_type` ENUM('payday_filing', 'ems_monthly', 'employer_monthly_schedule', 'employment_information') NOT NULL,

  -- Filing period
  `pay_period_start` DATE NOT NULL,
  `pay_period_end` DATE NOT NULL,
  `payroll_run_id` VARCHAR(50) NULL,

  -- IRD details
  `ird_number` VARCHAR(20) NULL COMMENT 'Employer IRD number',
  `submission_key` VARCHAR(100) NULL COMMENT 'IRD submission reference',

  -- Filing data
  `total_gross_earnings` DECIMAL(12,2) NOT NULL,
  `total_paye` DECIMAL(12,2) NOT NULL,
  `total_student_loan` DECIMAL(12,2) NOT NULL,
  `total_kiwisaver_employee` DECIMAL(12,2) NOT NULL,
  `total_kiwisaver_employer` DECIMAL(12,2) NOT NULL,
  `total_esct` DECIMAL(12,2) NOT NULL,
  `employee_count` INT UNSIGNED NOT NULL,

  -- Filing status
  `status` ENUM('draft', 'submitted', 'accepted', 'rejected', 'amended') NOT NULL DEFAULT 'draft',
  `submitted_at` DATETIME NULL,
  `ird_response` JSON NULL,
  `ird_acknowledgement` VARCHAR(255) NULL,

  -- Error handling
  `error_count` INT UNSIGNED DEFAULT 0,
  `error_details` JSON NULL,
  `retry_count` INT UNSIGNED DEFAULT 0,

  -- Audit
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  `filed_by` INT UNSIGNED NULL,

  PRIMARY KEY (`id`),
  KEY `idx_filing_type_period` (`filing_type`, `pay_period_end`),
  KEY `idx_status` (`status`),
  KEY `idx_payroll_run` (`payroll_run_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='NZ IRD filing history (Payday filing / EMS)';

-- ============================================================================
-- WAGES DISCREPANCY SYSTEM (AI-Powered Payslip Correction)
-- ============================================================================

-- Wages discrepancy submissions (staff reports payslip issues)
CREATE TABLE IF NOT EXISTS `payroll_wages_discrepancies` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `staff_id` INT UNSIGNED NOT NULL,
  `xero_employee_id` VARCHAR(50) NULL,

  -- Payslip details
  `payroll_run_id` VARCHAR(50) NOT NULL COMMENT 'Affected pay run',
  `xero_payslip_id` VARCHAR(50) NULL,
  `pay_period_start` DATE NOT NULL,
  `pay_period_end` DATE NOT NULL,
  `payment_date` DATE NOT NULL,

  -- Issue details
  `discrepancy_type` ENUM(
    'hours_incorrect',        -- Wrong hours on payslip
    'rate_incorrect',         -- Wrong pay rate applied
    'missing_hours',          -- Hours worked but not paid
    'incorrect_leave',        -- Leave calculation wrong
    'missing_allowance',      -- Allowance not included
    'incorrect_deduction',    -- Wrong deduction amount
    'tax_incorrect',          -- PAYE calculation wrong
    'kiwisaver_incorrect',    -- KiwiSaver wrong
    'student_loan_incorrect', -- Student loan wrong
    'public_holiday_incorrect', -- Public holiday pay wrong
    'other'
  ) NOT NULL,

  `description` TEXT NOT NULL COMMENT 'Staff explanation of issue',
  `expected_amount` DECIMAL(10,2) NULL COMMENT 'What staff expected to receive',
  `actual_amount` DECIMAL(10,2) NULL COMMENT 'What was actually paid',
  `discrepancy_amount` DECIMAL(10,2) NULL COMMENT 'Difference (calculated)',

  -- Evidence
  `evidence_files` JSON NULL COMMENT 'Photos, screenshots, etc.',
  `deputy_timesheet_ids` JSON NULL COMMENT 'Related Deputy timesheets',
  `roster_reference` VARCHAR(100) NULL,

  -- AI Analysis
  `ai_reviewed` TINYINT(1) DEFAULT 0,
  `ai_decision` ENUM('valid', 'invalid', 'partial', 'needs_review', 'escalate') NULL,
  `ai_confidence_score` DECIMAL(5,4) NULL,
  `ai_reasoning` TEXT NULL COMMENT 'AI explanation',
  `ai_calculated_discrepancy` DECIMAL(10,2) NULL COMMENT 'AI calculated amount',
  `ai_reviewed_at` DATETIME NULL,
  `ai_model_version` VARCHAR(50) NULL,

  -- AI Validation Details
  `ai_validation_data` JSON NULL COMMENT 'Full AI analysis breakdown',
  `deputy_hours_verified` TINYINT(1) DEFAULT 0,
  `roster_hours_verified` TINYINT(1) DEFAULT 0,
  `rate_verified` TINYINT(1) DEFAULT 0,
  `payslip_line_items` JSON NULL COMMENT 'Xero payslip breakdown',
  `timesheet_summary` JSON NULL COMMENT 'Deputy timesheet data',

  -- Correction Calculation
  `correction_required` TINYINT(1) DEFAULT 0,
  `correction_amount_gross` DECIMAL(10,2) NULL,
  `correction_amount_tax` DECIMAL(10,2) NULL,
  `correction_amount_net` DECIMAL(10,2) NULL,
  `correction_breakdown` JSON NULL COMMENT 'Detailed correction calc',

  -- Status workflow
  `status` ENUM(
    'pending',           -- Submitted, awaiting AI review
    'ai_review',         -- AI currently analyzing
    'validated',         -- AI confirmed discrepancy
    'rejected',          -- AI determined no discrepancy
    'escalated',         -- Complex case, needs human review
    'approved',          -- Human approved correction
    'correction_queued', -- Queued for next pay run
    'corrected',         -- Correction applied
    'closed'             -- Issue resolved
  ) NOT NULL DEFAULT 'pending',
  `validated_at` DATETIME NULL,
  `status_changed_at` DATETIME NULL,
  `status_changed_by` INT UNSIGNED NULL,

  -- Human Review (if escalated)
  `reviewed_by` INT UNSIGNED NULL,
  `reviewed_at` DATETIME NULL,
  `review_notes` TEXT NULL,
  `override_ai_decision` TINYINT(1) DEFAULT 0,

  -- Correction Application
  `correction_applied_in_run` VARCHAR(50) NULL COMMENT 'Pay run where corrected',
  `correction_applied_at` DATETIME NULL,
  `xero_correction_line_id` VARCHAR(50) NULL,
  `correction_notes` TEXT NULL,

  -- Follow-up
  `staff_notified` TINYINT(1) DEFAULT 0,
  `staff_notified_at` DATETIME NULL,
  `staff_satisfied` TINYINT(1) NULL COMMENT 'Did staff accept resolution?',
  `staff_feedback` TEXT NULL,

  -- Audit
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  `ip_address` VARCHAR(45) NULL,
  `user_agent` TEXT NULL,

  PRIMARY KEY (`id`),
  KEY `idx_staff` (`staff_id`),
  KEY `idx_payroll_run` (`payroll_run_id`),
  KEY `idx_status` (`status`),
  KEY `idx_discrepancy_type` (`discrepancy_type`),
  KEY `idx_ai_review` (`ai_reviewed`, `status`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_pay_period` (`pay_period_start`, `pay_period_end`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
ROW_FORMAT=COMPRESSED KEY_BLOCK_SIZE=8
COMMENT='Staff-submitted wages discrepancies with AI validation';

-- Discrepancy history (audit trail)
CREATE TABLE IF NOT EXISTS `payroll_wages_discrepancy_history` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `discrepancy_id` INT UNSIGNED NOT NULL,
  `action` ENUM(
    'created',
    'ai_review_started',
    'ai_review_completed',
    'validated',
    'rejected',
    'escalated',
    'human_reviewed',
    'approved',
    'correction_queued',
    'correction_applied',
    'staff_notified',
    'closed'
  ) NOT NULL,
  `actor_type` ENUM('staff', 'ai', 'admin', 'system') NOT NULL,
  `actor_id` INT UNSIGNED NULL,
  `actor_name` VARCHAR(255) NULL,
  `old_status` VARCHAR(50) NULL,
  `new_status` VARCHAR(50) NULL,
  `notes` TEXT NULL,
  `metadata` JSON NULL COMMENT 'Additional context',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ip_address` VARCHAR(45) NULL,

  PRIMARY KEY (`id`),
  KEY `idx_discrepancy` (`discrepancy_id`),
  KEY `idx_action` (`action`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `fk_discrepancy_history`
    FOREIGN KEY (`discrepancy_id`)
    REFERENCES `payroll_wages_discrepancies` (`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Complete audit trail for wages discrepancies';

-- Discrepancy patterns (ML learning)
CREATE TABLE IF NOT EXISTS `payroll_wages_discrepancy_patterns` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `pattern_type` VARCHAR(100) NOT NULL COMMENT 'e.g., "recurring_hours_short_friday"',
  `discrepancy_type` VARCHAR(50) NOT NULL,
  `outlet_id` VARCHAR(50) NULL,
  `staff_id` INT UNSIGNED NULL COMMENT 'NULL = affects multiple staff',

  -- Pattern details
  `occurrences` INT UNSIGNED DEFAULT 1,
  `first_detected` DATE NOT NULL,
  `last_detected` DATE NULL,
  `average_discrepancy_amount` DECIMAL(10,2) NULL,
  `total_affected_staff` INT UNSIGNED DEFAULT 0,

  -- Pattern metadata
  `pattern_description` TEXT NULL,
  `root_cause` TEXT NULL COMMENT 'If identified',
  `common_factors` JSON NULL COMMENT 'Day of week, shift type, etc.',

  -- Resolution
  `is_resolved` TINYINT(1) DEFAULT 0,
  `resolved_at` DATETIME NULL,
  `resolution_notes` TEXT NULL,
  `preventive_action_taken` TEXT NULL,

  -- Alert
  `alert_threshold` INT UNSIGNED DEFAULT 3 COMMENT 'Alert after X occurrences',
  `alert_sent` TINYINT(1) DEFAULT 0,
  `alert_sent_at` DATETIME NULL,

  -- Audit
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  KEY `idx_pattern_type` (`pattern_type`),
  KEY `idx_outlet` (`outlet_id`),
  KEY `idx_staff` (`staff_id`),
  KEY `idx_resolved` (`is_resolved`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='ML pattern detection for recurring wage issues';

-- ============================================================================
-- WAGES DISCREPANCY VIEWS
-- ============================================================================

-- Active discrepancies pending AI review
CREATE OR REPLACE VIEW `v_discrepancies_pending_ai_review` AS
SELECT
  d.id,
  d.staff_id,
  d.payroll_run_id,
  d.discrepancy_type,
  d.description,
  d.discrepancy_amount,
  d.created_at,
  TIMESTAMPDIFF(HOUR, d.created_at, NOW()) as hours_waiting
FROM payroll_wages_discrepancies d
WHERE d.status IN ('pending', 'ai_review')
  AND d.ai_reviewed = 0
ORDER BY d.created_at ASC;

-- Validated discrepancies awaiting correction
CREATE OR REPLACE VIEW `v_discrepancies_awaiting_correction` AS
SELECT
  d.id,
  d.staff_id,
  d.payroll_run_id,
  d.discrepancy_type,
  d.correction_amount_net,
  d.status,
  d.ai_confidence_score,
  d.validated_at,
  DATEDIFF(NOW(), d.validated_at) as days_since_validation
FROM payroll_wages_discrepancies d
WHERE d.status IN ('validated', 'approved', 'correction_queued')
  AND d.correction_applied_at IS NULL
ORDER BY d.validated_at ASC;

-- Discrepancy dashboard (last 30 days)
CREATE OR REPLACE VIEW `v_discrepancies_dashboard` AS
SELECT
  DATE(created_at) as report_date,
  COUNT(*) as total_submissions,
  SUM(CASE WHEN status = 'validated' THEN 1 ELSE 0 END) as validated_count,
  SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_count,
  SUM(CASE WHEN status = 'corrected' THEN 1 ELSE 0 END) as corrected_count,
  SUM(CASE WHEN ai_reviewed = 1 THEN 1 ELSE 0 END) as ai_reviewed_count,
  AVG(CASE WHEN ai_confidence_score IS NOT NULL THEN ai_confidence_score ELSE NULL END) as avg_ai_confidence,
  SUM(CASE WHEN correction_applied_at IS NOT NULL THEN correction_amount_net ELSE 0 END) as total_corrections_paid,
  AVG(TIMESTAMPDIFF(HOUR, created_at,
    CASE WHEN ai_reviewed_at IS NOT NULL THEN ai_reviewed_at ELSE NULL END)) as avg_ai_review_time_hours
FROM payroll_wages_discrepancies
WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
GROUP BY DATE(created_at)
ORDER BY report_date DESC;

-- Staff with frequent discrepancies (potential issues)
CREATE OR REPLACE VIEW `v_discrepancies_frequent_submitters` AS
SELECT
  staff_id,
  COUNT(*) as total_submissions,
  SUM(CASE WHEN status = 'validated' THEN 1 ELSE 0 END) as validated_submissions,
  SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_submissions,
  SUM(CASE WHEN correction_applied_at IS NOT NULL THEN correction_amount_net ELSE 0 END) as total_corrections_received,
  MAX(created_at) as last_submission_date,
  DATEDIFF(NOW(), MAX(created_at)) as days_since_last_submission
FROM payroll_wages_discrepancies
WHERE created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)
GROUP BY staff_id
HAVING total_submissions >= 3
ORDER BY total_submissions DESC;

-- ============================================================================
-- AI DECISION ENGINE - NZ Employment Law Expert System
-- ============================================================================

-- AI Decision Requests (tracks all AI decision-making requests)
CREATE TABLE IF NOT EXISTS `payroll_ai_decision_requests` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `request_reference` VARCHAR(100) NOT NULL UNIQUE,
  `decision_type` ENUM(
    'sick_leave_validation',
    'bereavement_assessment',
    'domestic_violence_leave',
    'parental_leave_eligibility',
    'public_holiday_entitlement',
    'alternative_holiday_validity',
    'annual_leave_payout',
    'relevant_daily_pay_calculation',
    'otherwise_working_day',
    'employment_contract_interpretation',
    'pay_dispute_resolution',
    'leave_request_assessment',
    'wage_correction_validation',
    'compliance_interpretation',
    'custom'
  ) NOT NULL,

  -- Context
  `related_entity_type` VARCHAR(50) NULL COMMENT 'discrepancy, leave_request, check_result, etc.',
  `related_entity_id` INT UNSIGNED NULL,
  `staff_id` INT UNSIGNED NULL,
  `payroll_run_id` VARCHAR(50) NULL,

  -- Request details
  `scenario_description` TEXT NOT NULL COMMENT 'What needs to be decided',
  `context_data` JSON NOT NULL COMMENT 'All relevant context (staff info, dates, amounts, etc.)',
  `user_submitted_data` JSON NULL COMMENT 'Staff-provided information',
  `system_data` JSON NULL COMMENT 'System-retrieved data (Deputy, Xero, etc.)',

  -- AI Processing
  `ai_model` VARCHAR(100) DEFAULT 'gpt-4o' COMMENT 'Model used for decision',
  `ai_prompt` TEXT NULL COMMENT 'Actual prompt sent to AI',
  `ai_response_raw` TEXT NULL COMMENT 'Raw AI response',
  `ai_response_parsed` JSON NULL COMMENT 'Structured AI response',
  `processing_time_ms` INT UNSIGNED NULL,

  -- Decision outcome
  `decision` ENUM('approve', 'decline', 'escalate', 'request_evidence', 'partial_approve', 'pending') NULL,
  `confidence_score` DECIMAL(5,4) NULL COMMENT '0.0000 to 1.0000',
  `reasoning` TEXT NULL COMMENT 'AI explanation',
  `legal_basis` TEXT NULL COMMENT 'NZ law citations',
  `recommendations` JSON NULL COMMENT 'Action recommendations',
  `red_flags` JSON NULL COMMENT 'Concerns identified',

  -- Human review requirements
  `requires_human_review` TINYINT(1) DEFAULT 0,
  `human_review_reason` VARCHAR(255) NULL COMMENT 'Why human review needed',
  `escalation_priority` ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',

  -- Status tracking
  `status` ENUM(
    'pending',
    'processing',
    'ai_complete',
    'human_review',
    'approved',
    'declined',
    'implemented',
    'cancelled'
  ) NOT NULL DEFAULT 'pending',
  `status_changed_at` DATETIME NULL,

  -- Human review
  `reviewed_by` INT UNSIGNED NULL,
  `reviewed_at` DATETIME NULL,
  `human_decision` ENUM('approve_ai', 'override_approve', 'override_decline', 'request_more_info') NULL,
  `human_reasoning` TEXT NULL,
  `human_override` TINYINT(1) DEFAULT 0,

  -- Appeal tracking
  `appeal_submitted` TINYINT(1) DEFAULT 0,
  `appeal_reason` TEXT NULL,
  `appeal_reviewed_at` DATETIME NULL,
  `appeal_outcome` ENUM('upheld', 'overturned', 'partial', 'dismissed') NULL,

  -- Implementation
  `implemented` TINYINT(1) DEFAULT 0,
  `implemented_at` DATETIME NULL,
  `implemented_by` INT UNSIGNED NULL,
  `implementation_notes` TEXT NULL,

  -- Audit
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  `created_by` INT UNSIGNED NULL,
  `ip_address` VARCHAR(45) NULL,

  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_request_ref` (`request_reference`),
  KEY `idx_decision_type` (`decision_type`),
  KEY `idx_status` (`status`),
  KEY `idx_staff` (`staff_id`),
  KEY `idx_related_entity` (`related_entity_type`, `related_entity_id`),
  KEY `idx_requires_review` (`requires_human_review`, `status`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='AI decision-making requests with full audit trail';

-- AI Decision Rules (NZ employment law expert rules)
CREATE TABLE IF NOT EXISTS `payroll_ai_decision_rules` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `rule_code` VARCHAR(100) NOT NULL UNIQUE,
  `decision_type` VARCHAR(100) NOT NULL,
  `rule_name` VARCHAR(255) NOT NULL,
  `description` TEXT NOT NULL,

  -- NZ Law reference
  `legislation_reference` VARCHAR(255) NULL COMMENT 'e.g., Holidays Act 2003 s56',
  `case_law_reference` TEXT NULL COMMENT 'Relevant case law',
  `ird_guidance` TEXT NULL COMMENT 'IRD guidance links',

  -- Rule logic
  `conditions` JSON NOT NULL COMMENT 'Conditions that trigger this rule',
  `decision_tree` JSON NULL COMMENT 'Decision tree logic',
  `confidence_threshold` DECIMAL(5,4) DEFAULT 0.7000 COMMENT 'Min confidence before human review',

  -- AI prompt engineering
  `system_prompt_template` TEXT NULL COMMENT 'System prompt for this rule type',
  `user_prompt_template` TEXT NULL COMMENT 'User prompt template with variables',
  `response_schema` JSON NULL COMMENT 'Expected JSON response structure',

  -- Validation rules
  `requires_evidence` TINYINT(1) DEFAULT 0,
  `evidence_types` JSON NULL COMMENT 'Types of evidence required',
  `auto_approve_conditions` JSON NULL COMMENT 'Conditions for auto-approval',
  `auto_decline_conditions` JSON NULL COMMENT 'Conditions for auto-decline',

  -- Human review triggers
  `always_require_human_review` TINYINT(1) DEFAULT 0,
  `human_review_conditions` JSON NULL COMMENT 'Conditions requiring human review',

  -- Status
  `is_active` TINYINT(1) DEFAULT 1,
  `effective_from` DATE NOT NULL,
  `effective_to` DATE NULL,

  -- Performance tracking
  `times_applied` INT UNSIGNED DEFAULT 0,
  `approval_rate` DECIMAL(5,4) NULL,
  `average_confidence` DECIMAL(5,4) NULL,
  `human_override_rate` DECIMAL(5,4) NULL,

  -- Audit
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  `created_by` INT UNSIGNED NULL,

  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_rule_code` (`rule_code`),
  KEY `idx_decision_type` (`decision_type`),
  KEY `idx_active` (`is_active`, `effective_from`, `effective_to`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='NZ employment law decision rules and AI prompt templates';

-- AI Decision History (complete audit trail)
CREATE TABLE IF NOT EXISTS `payroll_ai_decision_history` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `decision_request_id` INT UNSIGNED NOT NULL,
  `action` ENUM(
    'created',
    'ai_processing_started',
    'ai_processing_complete',
    'escalated_to_human',
    'human_review_started',
    'human_approved',
    'human_declined',
    'human_overridden',
    'evidence_requested',
    'evidence_submitted',
    'appeal_submitted',
    'appeal_reviewed',
    'implemented',
    'cancelled'
  ) NOT NULL,
  `actor_type` ENUM('system', 'ai', 'staff', 'admin', 'hr_manager') NOT NULL,
  `actor_id` INT UNSIGNED NULL,
  `actor_name` VARCHAR(255) NULL,
  `old_status` VARCHAR(50) NULL,
  `new_status` VARCHAR(50) NULL,
  `old_decision` VARCHAR(50) NULL,
  `new_decision` VARCHAR(50) NULL,
  `notes` TEXT NULL,
  `metadata` JSON NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ip_address` VARCHAR(45) NULL,

  PRIMARY KEY (`id`),
  KEY `idx_decision_request` (`decision_request_id`),
  KEY `idx_action` (`action`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `fk_decision_history`
    FOREIGN KEY (`decision_request_id`)
    REFERENCES `payroll_ai_decision_requests` (`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Complete audit trail for AI decisions';

-- AI Decision Performance (analytics)
CREATE TABLE IF NOT EXISTS `payroll_ai_decision_performance` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `report_date` DATE NOT NULL,
  `decision_type` VARCHAR(100) NOT NULL,

  -- Volume metrics
  `total_requests` INT UNSIGNED DEFAULT 0,
  `ai_approved` INT UNSIGNED DEFAULT 0,
  `ai_declined` INT UNSIGNED DEFAULT 0,
  `ai_escalated` INT UNSIGNED DEFAULT 0,
  `human_reviewed` INT UNSIGNED DEFAULT 0,
  `human_overrides` INT UNSIGNED DEFAULT 0,

  -- Performance metrics
  `avg_confidence_score` DECIMAL(5,4) NULL,
  `avg_processing_time_ms` INT UNSIGNED NULL,
  `approval_rate_pct` DECIMAL(5,2) NULL,
  `override_rate_pct` DECIMAL(5,2) NULL,
  `escalation_rate_pct` DECIMAL(5,2) NULL,

  -- Quality metrics
  `appeals_submitted` INT UNSIGNED DEFAULT 0,
  `appeals_upheld` INT UNSIGNED DEFAULT 0,
  `appeals_overturned` INT UNSIGNED DEFAULT 0,

  -- Audit
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_date_type` (`report_date`, `decision_type`),
  KEY `idx_report_date` (`report_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Daily performance analytics for AI decisions';

-- ============================================================================
-- NZ STATUTORY DEDUCTIONS (Court fines, Child Support, etc.)
-- ============================================================================

-- Deduction Applications (IRD/Courts initiated orders against employees)
CREATE TABLE IF NOT EXISTS `payroll_nz_deduction_applications` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `staff_id` INT UNSIGNED NOT NULL,
  `xero_employee_id` VARCHAR(50) NULL,

  -- Order details
  `order_type` ENUM('court_fine', 'child_support', 'student_loan_arrears', 'tax_arrears', 'restitution', 'other') NOT NULL,
  `order_reference` VARCHAR(100) NOT NULL,
  `issuing_authority` VARCHAR(100) NOT NULL COMMENT 'e.g., IRD, Ministry of Justice',
  `received_date` DATE NOT NULL,
  `effective_from` DATE NOT NULL,
  `effective_to` DATE NULL,

  -- Deduction rules
  `calculation_method` ENUM('fixed_amount', 'percentage_of_net', 'percentage_of_gross', 'tiered', 'formula') NOT NULL,
  `amount` DECIMAL(10,2) NULL,
  `percentage` DECIMAL(5,4) NULL COMMENT 'e.g., 0.2000 = 20%',
  `min_net_protected` DECIMAL(10,2) NULL COMMENT 'Protected earnings (minimum net after deductions)',
  `priority` INT UNSIGNED DEFAULT 100 COMMENT 'Lower = higher priority deduction',
  `max_per_pay` DECIMAL(10,2) NULL,
  `arrears_amount` DECIMAL(10,2) NULL,
  `ongoing_amount` DECIMAL(10,2) NULL,

  -- Status
  `status` ENUM('active', 'suspended', 'completed', 'cancelled') NOT NULL DEFAULT 'active',
  `status_changed_at` DATETIME NULL,

  -- Documents
  `supporting_documents` JSON NULL,

  -- Audit
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  `created_by` INT UNSIGNED NULL,

  PRIMARY KEY (`id`),
  KEY `idx_staff` (`staff_id`),
  KEY `idx_order` (`order_type`, `order_reference`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='External deduction orders (court fines, child support, etc.)';

-- Per-pay deductions generated and applied
CREATE TABLE IF NOT EXISTS `payroll_nz_statutory_deductions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `payroll_run_id` VARCHAR(50) NOT NULL,
  `staff_id` INT UNSIGNED NOT NULL,
  `deduction_application_id` INT UNSIGNED NOT NULL,

  -- Calculation snapshot
  `gross_pay` DECIMAL(10,2) NOT NULL,
  `net_pay_before_deduction` DECIMAL(10,2) NOT NULL,
  `deduction_amount` DECIMAL(10,2) NOT NULL,
  `calculation_notes` TEXT NULL,

  -- Protection
  `protected_earnings_applied` TINYINT(1) DEFAULT 0,
  `protected_earnings_threshold` DECIMAL(10,2) NULL,

  -- Status
  `status` ENUM('pending', 'applied', 'reversed') NOT NULL DEFAULT 'pending',
  `applied_at` DATETIME NULL,
  `xero_payslip_line_id` VARCHAR(50) NULL,

  -- Audit
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  KEY `idx_run_staff` (`payroll_run_id`, `staff_id`),
  KEY `idx_application` (`deduction_application_id`),
  CONSTRAINT `fk_statutory_deduction_application`
    FOREIGN KEY (`deduction_application_id`)
    REFERENCES `payroll_nz_deduction_applications` (`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Per-pay statutory deductions generated/applicable with audit';

-- ============================================================================
-- SEED NZ EMPLOYMENT LAW DECISION RULES
-- ============================================================================

INSERT INTO `payroll_ai_decision_rules`
  (`rule_code`, `decision_type`, `rule_name`, `description`,
   `legislation_reference`, `confidence_threshold`, `is_active`, `effective_from`)
VALUES
  -- Sick Leave Rules
  ('NZ_SICK_LEAVE_BASIC', 'sick_leave_validation',
   'Basic Sick Leave Validation',
   'Assess if sick leave reason is valid under NZ Holidays Act 2003',
   'Holidays Act 2003 s65-71', 0.7500, 1, '2024-01-01'),

  ('NZ_SICK_LEAVE_MEDICAL_CERT', 'sick_leave_validation',
   'Medical Certificate Requirement',
   'Determine if medical certificate required (3+ consecutive days or reasonable grounds)',
   'Holidays Act 2003 s71', 0.8000, 1, '2024-01-01'),

  -- Bereavement Leave Rules
  ('NZ_BEREAVEMENT_IMMEDIATE_FAMILY', 'bereavement_assessment',
   'Immediate Family Definition',
   'Assess if relationship qualifies as immediate family under NZ law',
   'Holidays Act 2003 s69', 0.7000, 1, '2024-01-01'),

  ('NZ_BEREAVEMENT_DAYS_ENTITLED', 'bereavement_assessment',
   'Bereavement Days Entitlement',
   'Calculate days entitled (standard 3, may be more for exceptional circumstances)',
   'Holidays Act 2003 s69', 0.8000, 1, '2024-01-01'),

  -- Domestic Violence Leave Rules
  ('NZ_DV_LEAVE_ELIGIBILITY', 'domestic_violence_leave',
   'Domestic Violence Leave Eligibility',
   'Assess DV leave request with privacy sensitivity',
   'Holidays Act 2003 s72A-72E', 0.6000, 1, '2024-04-01'),

  -- Public Holiday Rules
  ('NZ_PUBLIC_HOLIDAY_ENTITLEMENT', 'public_holiday_entitlement',
   'Public Holiday Pay Entitlement',
   'Calculate public holiday pay (time and half + alt holiday if worked, otherwise RDP/ADP)',
   'Holidays Act 2003 s50-56', 0.8500, 1, '2024-01-01'),

  ('NZ_OTHERWISE_WORKING_DAY', 'otherwise_working_day',
   'Otherwise Working Day Test',
   'Determine if employee would have otherwise worked on public holiday',
   'Holidays Act 2003 s12', 0.7500, 1, '2024-01-01'),

  -- Alternative Holiday Rules
  ('NZ_ALT_HOLIDAY_ELIGIBILITY', 'alternative_holiday_validity',
   'Alternative Holiday Eligibility',
   'Assess if alt holiday should have been granted for public holiday worked',
   'Holidays Act 2003 s56', 0.8000, 1, '2024-01-01'),

  -- Annual Leave Rules
  ('NZ_ANNUAL_LEAVE_PAYOUT', 'annual_leave_payout',
   'Annual Leave Payout Calculation',
   'Calculate correct annual leave payout on termination (higher of 8% or average weekly pay)',
   'Holidays Act 2003 s28', 0.9000, 1, '2024-01-01'),

  -- Relevant Daily Pay Rules
  ('NZ_RELEVANT_DAILY_PAY', 'relevant_daily_pay_calculation',
   'Relevant Daily Pay Calculation',
   'Calculate RDP (or ADP if RDP not determinable)',
   'Holidays Act 2003 s9', 0.8500, 1, '2024-01-01'),

  -- Employment Contract Interpretation
  ('NZ_CONTRACT_INTERPRETATION', 'employment_contract_interpretation',
   'Employment Agreement Interpretation',
   'Interpret employment agreement terms in context of NZ employment law',
   'Employment Relations Act 2000', 0.7000, 1, '2024-01-01'),

  -- Pay Dispute Resolution
  ('NZ_PAY_DISPUTE_RESOLUTION', 'pay_dispute_resolution',
   'Wage Dispute Resolution',
   'Analyze complex pay disputes under NZ law',
   'Employment Relations Act 2000, Wages Protection Act 1983', 0.7500, 1, '2024-01-01');

-- ============================================================================
-- AI DECISION VIEWS
-- ============================================================================

-- Pending human review queue
CREATE OR REPLACE VIEW `v_ai_decisions_pending_review` AS
SELECT
  d.id,
  d.request_reference,
  d.decision_type,
  d.staff_id,
  d.scenario_description,
  d.decision,
  d.confidence_score,
  d.reasoning,
  d.requires_human_review,
  d.human_review_reason,
  d.escalation_priority,
  d.created_at,
  TIMESTAMPDIFF(HOUR, d.created_at, NOW()) as hours_waiting,
  CASE d.escalation_priority
    WHEN 'urgent' THEN 1
    WHEN 'high' THEN 2
    WHEN 'medium' THEN 3
    WHEN 'low' THEN 4
  END as priority_sort
FROM payroll_ai_decision_requests d
WHERE d.status = 'human_review'
  AND d.reviewed_at IS NULL
ORDER BY priority_sort ASC, d.created_at ASC;

-- AI decision dashboard (last 30 days)
CREATE OR REPLACE VIEW `v_ai_decisions_dashboard` AS
SELECT
  DATE(created_at) as decision_date,
  decision_type,
  COUNT(*) as total_decisions,
  SUM(CASE WHEN decision = 'approve' THEN 1 ELSE 0 END) as approved,
  SUM(CASE WHEN decision = 'decline' THEN 1 ELSE 0 END) as declined,
  SUM(CASE WHEN decision = 'escalate' THEN 1 ELSE 0 END) as escalated,
  SUM(CASE WHEN requires_human_review = 1 THEN 1 ELSE 0 END) as human_review_required,
  SUM(CASE WHEN human_override = 1 THEN 1 ELSE 0 END) as human_overrides,
  AVG(confidence_score) as avg_confidence,
  AVG(processing_time_ms) as avg_processing_ms
FROM payroll_ai_decision_requests
WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
GROUP BY DATE(created_at), decision_type
ORDER BY decision_date DESC;

-- Low confidence decisions (quality monitoring)
CREATE OR REPLACE VIEW `v_ai_decisions_low_confidence` AS
SELECT
  d.id,
  d.request_reference,
  d.decision_type,
  d.staff_id,
  d.decision,
  d.confidence_score,
  d.reasoning,
  d.status,
  d.human_override,
  d.created_at
FROM payroll_ai_decision_requests d
WHERE d.confidence_score < 0.7000
  AND d.confidence_score IS NOT NULL
  AND d.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
ORDER BY d.confidence_score ASC, d.created_at DESC;

-- ============================================================================
-- AI PAYROLL ORCHESTRATOR - Complete Pre-Pay Run Checks
-- ============================================================================

-- AI Payroll Check Sessions (comprehensive pre-pay run validation)
CREATE TABLE IF NOT EXISTS `payroll_ai_check_sessions` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `session_reference` VARCHAR(100) NOT NULL UNIQUE,
  `payroll_run_id` VARCHAR(50) NULL COMMENT 'If attached to specific pay run',

  -- Session details
  `check_type` ENUM(
    'pre_pay_run',           -- Full check before pay run
    'post_deputy_sync',      -- After Deputy timesheet import
    'manual_audit',          -- Admin-initiated check
    'scheduled_daily',       -- Daily scheduled check
    'discrepancy_triggered'  -- Triggered by discrepancy submission
  ) NOT NULL DEFAULT 'pre_pay_run',

  `pay_period_start` DATE NOT NULL,
  `pay_period_end` DATE NOT NULL,
  `staff_count` INT UNSIGNED NOT NULL,

  -- Check modules status
  `deputy_timesheets_checked` TINYINT(1) DEFAULT 0,
  `timesheet_amendments_checked` TINYINT(1) DEFAULT 0,
  `wages_discrepancies_checked` TINYINT(1) DEFAULT 0,
  `vend_accounts_checked` TINYINT(1) DEFAULT 0,
  `holiday_pay_checked` TINYINT(1) DEFAULT 0,
  `leave_balances_checked` TINYINT(1) DEFAULT 0,
  `tax_calculations_checked` TINYINT(1) DEFAULT 0,
  `kiwisaver_checked` TINYINT(1) DEFAULT 0,
  `student_loans_checked` TINYINT(1) DEFAULT 0,
  `minimum_wage_checked` TINYINT(1) DEFAULT 0,

  -- Overall status
  `status` ENUM(
    'initiated',
    'in_progress',
    'checks_complete',
    'issues_found',
    'review_required',
    'approved',
    'failed'
  ) NOT NULL DEFAULT 'initiated',

  -- Results summary
  `total_checks_run` INT UNSIGNED DEFAULT 0,
  `total_issues_found` INT UNSIGNED DEFAULT 0,
  `critical_issues` INT UNSIGNED DEFAULT 0,
  `warnings` INT UNSIGNED DEFAULT 0,
  `auto_resolved` INT UNSIGNED DEFAULT 0,
  `require_human_review` INT UNSIGNED DEFAULT 0,

  -- AI Performance
  `ai_confidence_avg` DECIMAL(5,4) NULL,
  `processing_time_seconds` INT UNSIGNED NULL,
  `started_at` DATETIME NOT NULL,
  `completed_at` DATETIME NULL,

  -- Results
  `check_results` JSON NULL COMMENT 'Complete results breakdown',
  `recommendations` JSON NULL COMMENT 'AI recommendations',
  `blocking_issues` JSON NULL COMMENT 'Issues preventing pay run',

  -- Human review
  `reviewed_by` INT UNSIGNED NULL,
  `reviewed_at` DATETIME NULL,
  `review_notes` TEXT NULL,
  `approved_for_payrun` TINYINT(1) DEFAULT 0,

  -- Audit
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  `triggered_by` INT UNSIGNED NULL,

  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_session_ref` (`session_reference`),
  KEY `idx_payroll_run` (`payroll_run_id`),
  KEY `idx_status` (`status`),
  KEY `idx_pay_period` (`pay_period_start`, `pay_period_end`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='AI orchestration sessions for comprehensive payroll checks';

-- Individual check results (granular detail)
CREATE TABLE IF NOT EXISTS `payroll_ai_check_results` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `session_id` INT UNSIGNED NOT NULL,
  `check_module` VARCHAR(100) NOT NULL COMMENT 'Which module ran the check',
  `staff_id` INT UNSIGNED NULL COMMENT 'If staff-specific check',

  -- Check details
  `check_name` VARCHAR(255) NOT NULL,
  `check_category` ENUM(
    'hours_validation',
    'rate_validation',
    'leave_calculation',
    'tax_calculation',
    'deduction_validation',
    'compliance_check',
    'data_integrity',
    'cross_system_sync'
  ) NOT NULL,

  -- Check result
  `result` ENUM('pass', 'fail', 'warning', 'info', 'error') NOT NULL,
  `severity` ENUM('critical', 'high', 'medium', 'low', 'info') NOT NULL DEFAULT 'info',

  -- Details
  `message` TEXT NOT NULL,
  `expected_value` VARCHAR(500) NULL,
  `actual_value` VARCHAR(500) NULL,
  `difference` VARCHAR(500) NULL,
  `check_data` JSON NULL COMMENT 'Full check context',

  -- AI analysis
  `ai_recommendation` TEXT NULL,
  `can_auto_resolve` TINYINT(1) DEFAULT 0,
  `auto_resolved` TINYINT(1) DEFAULT 0,
  `resolution_action` TEXT NULL,

  -- Links to source data
  `deputy_timesheet_id` INT UNSIGNED NULL,
  `amendment_id` INT UNSIGNED NULL,
  `discrepancy_id` INT UNSIGNED NULL,
  `related_entity_type` VARCHAR(50) NULL,
  `related_entity_id` INT UNSIGNED NULL,

  -- Follow-up
  `requires_action` TINYINT(1) DEFAULT 0,
  `action_taken` TEXT NULL,
  `action_taken_at` DATETIME NULL,
  `action_taken_by` INT UNSIGNED NULL,

  -- Audit
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `processing_time_ms` INT UNSIGNED NULL,

  PRIMARY KEY (`id`),
  KEY `idx_session` (`session_id`),
  KEY `idx_result` (`result`, `severity`),
  KEY `idx_check_module` (`check_module`),
  KEY `idx_staff` (`staff_id`),
  KEY `idx_requires_action` (`requires_action`),
  CONSTRAINT `fk_check_result_session`
    FOREIGN KEY (`session_id`)
    REFERENCES `payroll_ai_check_sessions` (`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
ROW_FORMAT=COMPRESSED KEY_BLOCK_SIZE=8
COMMENT='Granular results from AI payroll checks';

-- ============================================================================
-- AI CHECK CONFIGURATION (What gets checked)
-- ============================================================================

CREATE TABLE IF NOT EXISTS `payroll_ai_check_rules` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `rule_name` VARCHAR(100) NOT NULL UNIQUE,
  `check_module` VARCHAR(100) NOT NULL,
  `check_category` VARCHAR(100) NOT NULL,
  `description` TEXT NOT NULL,

  -- When to run
  `run_on_pre_pay_run` TINYINT(1) DEFAULT 1,
  `run_on_deputy_sync` TINYINT(1) DEFAULT 0,
  `run_on_manual_audit` TINYINT(1) DEFAULT 1,
  `run_on_schedule` TINYINT(1) DEFAULT 0,

  -- Check logic
  `check_sql` TEXT NULL COMMENT 'SQL query for check (if applicable)',
  `check_logic` JSON NULL COMMENT 'Logic/conditions for check',
  `threshold_critical` DECIMAL(10,2) NULL COMMENT 'Critical threshold value',
  `threshold_warning` DECIMAL(10,2) NULL COMMENT 'Warning threshold value',

  -- AI behavior
  `can_auto_resolve` TINYINT(1) DEFAULT 0,
  `auto_resolve_logic` JSON NULL COMMENT 'How to auto-resolve',
  `requires_human_review` TINYINT(1) DEFAULT 0,

  -- Status
  `is_active` TINYINT(1) DEFAULT 1,
  `priority` INT UNSIGNED DEFAULT 100 COMMENT 'Execution order',

  -- Audit
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  `last_run_at` DATETIME NULL,
  `run_count` INT UNSIGNED DEFAULT 0,
  `pass_count` INT UNSIGNED DEFAULT 0,
  `fail_count` INT UNSIGNED DEFAULT 0,

  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_rule_name` (`rule_name`),
  KEY `idx_module_category` (`check_module`, `check_category`),
  KEY `idx_active_priority` (`is_active`, `priority`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Configuration for AI payroll check rules';

-- ============================================================================
-- SEED DEFAULT CHECK RULES
-- ============================================================================

INSERT INTO `payroll_ai_check_rules`
  (`rule_name`, `check_module`, `check_category`, `description`,
   `run_on_pre_pay_run`, `can_auto_resolve`, `priority`, `is_active`)
VALUES
  -- Deputy Timesheet Checks
  ('deputy_missing_timesheets', 'deputy', 'data_integrity',
   'Check for staff with no timesheet entries for pay period', 1, 0, 10, 1),

  ('deputy_negative_hours', 'deputy', 'hours_validation',
   'Identify negative or zero hour entries', 1, 0, 20, 1),

  ('deputy_excessive_hours', 'deputy', 'hours_validation',
   'Flag shifts over 12 hours without break', 1, 0, 30, 1),

  ('deputy_clock_discrepancies', 'deputy', 'hours_validation',
   'Compare clock in/out vs rostered times', 1, 1, 40, 1),

  -- Timesheet Amendment Checks
  ('pending_amendments', 'amendments', 'hours_validation',
   'Check for unapproved timesheet amendments', 1, 0, 50, 1),

  ('amendment_large_changes', 'amendments', 'hours_validation',
   'Flag amendments changing >3 hours', 1, 0, 60, 1),

  ('amendment_pattern_suspicious', 'amendments', 'compliance_check',
   'Detect suspicious amendment patterns', 1, 0, 70, 1),

  -- Wages Discrepancy Checks
  ('unresolved_discrepancies', 'discrepancies', 'data_integrity',
   'Check for open wages discrepancy submissions', 1, 0, 80, 1),

  ('recurring_discrepancy_pattern', 'discrepancies', 'compliance_check',
   'Detect recurring discrepancy patterns by outlet/staff', 1, 0, 90, 1),

  -- Vend Account Checks
  ('vend_negative_balances', 'vend_accounts', 'deduction_validation',
   'Staff with negative Vend account balances', 1, 1, 100, 1),

  ('vend_excessive_balances', 'vend_accounts', 'deduction_validation',
   'Staff with Vend balances exceeding pay threshold', 1, 0, 110, 1),

  ('vend_payment_allocation_pending', 'vend_accounts', 'data_integrity',
   'Pending Vend payment allocations', 1, 1, 120, 1),

  -- Holiday Pay Checks
  ('public_holiday_not_paid', 'holiday_pay', 'leave_calculation',
   'Staff worked public holiday but no time-and-half paid', 1, 0, 130, 1),

  ('alternative_holiday_not_created', 'holiday_pay', 'leave_calculation',
   'Public holiday worked but alternative holiday not created', 1, 1, 140, 1),

  ('alternative_holiday_expired', 'holiday_pay', 'compliance_check',
   'Alternative holidays older than 12 months unused', 1, 0, 150, 1),

  -- Leave Balance Checks
  ('annual_leave_negative', 'leave_balances', 'leave_calculation',
   'Staff with negative annual leave balance', 1, 0, 160, 1),

  ('leave_accrual_mismatch', 'leave_balances', 'leave_calculation',
   'Leave accrual not matching hours worked', 1, 1, 170, 1),

  ('leave_anniversary_upcoming', 'leave_balances', 'compliance_check',
   'Staff approaching leave anniversary (4 weeks)', 1, 0, 180, 1),

  -- Tax Calculation Checks
  ('tax_code_missing', 'tax', 'tax_calculation',
   'Staff without valid tax code', 1, 0, 190, 1),

  ('paye_calculation_error', 'tax', 'tax_calculation',
   'PAYE calculation deviation >$5', 1, 0, 200, 1),

  ('student_loan_threshold_error', 'tax', 'tax_calculation',
   'Student loan deduction when under threshold', 1, 1, 210, 1),

  -- KiwiSaver Checks
  ('kiwisaver_below_minimum', 'kiwisaver', 'deduction_validation',
   'Contribution rate below 3% minimum', 1, 0, 220, 1),

  ('kiwisaver_esct_incorrect', 'kiwisaver', 'tax_calculation',
   'ESCT rate incorrect for income bracket', 1, 1, 230, 1),

  -- Minimum Wage Checks
  ('below_minimum_wage', 'minimum_wage', 'compliance_check',
   'Staff paid below NZ minimum wage ($23.15/hr)', 1, 0, 240, 1),

  ('relevant_daily_pay_error', 'minimum_wage', 'compliance_check',
   'Leave pay below relevant daily pay calculation', 1, 0, 250, 1),

  -- Cross-System Sync Checks
  ('deputy_xero_hours_mismatch', 'sync', 'cross_system_sync',
   'Hours in Deputy vs Xero payslip mismatch >0.5hr', 1, 0, 260, 1),

  ('xero_employee_not_in_deputy', 'sync', 'data_integrity',
   'Active Xero employee not found in Deputy', 1, 0, 270, 1);

-- ============================================================================
-- AI CHECK DASHBOARD VIEWS
-- ============================================================================

-- Active check sessions (in progress or pending review)
CREATE OR REPLACE VIEW `v_ai_check_sessions_active` AS
SELECT
  s.id,
  s.session_reference,
  s.check_type,
  s.pay_period_start,
  s.pay_period_end,
  s.status,
  s.staff_count,
  s.total_issues_found,
  s.critical_issues,
  s.require_human_review,
  s.started_at,
  TIMESTAMPDIFF(MINUTE, s.started_at, NOW()) as running_minutes,
  s.ai_confidence_avg
FROM payroll_ai_check_sessions s
WHERE s.status IN ('initiated', 'in_progress', 'checks_complete', 'issues_found', 'review_required')
ORDER BY s.started_at DESC;

-- Critical issues requiring immediate attention
CREATE OR REPLACE VIEW `v_ai_check_critical_issues` AS
SELECT
  r.id,
  r.session_id,
  s.session_reference,
  s.pay_period_start,
  s.pay_period_end,
  r.check_module,
  r.check_name,
  r.staff_id,
  r.severity,
  r.message,
  r.requires_action,
  r.auto_resolved,
  r.created_at
FROM payroll_ai_check_results r
JOIN payroll_ai_check_sessions s ON r.session_id = s.id
WHERE r.severity IN ('critical', 'high')
  AND r.result = 'fail'
  AND r.auto_resolved = 0
  AND s.status NOT IN ('approved', 'failed')
ORDER BY
  CASE r.severity
    WHEN 'critical' THEN 1
    WHEN 'high' THEN 2
  END,
  r.created_at ASC;

-- Check performance dashboard (last 30 days)
CREATE OR REPLACE VIEW `v_ai_check_performance_30d` AS
SELECT
  DATE(s.started_at) as check_date,
  s.check_type,
  COUNT(*) as total_sessions,
  SUM(s.total_checks_run) as total_checks,
  SUM(s.total_issues_found) as total_issues,
  SUM(s.critical_issues) as critical_issues,
  SUM(s.auto_resolved) as auto_resolved,
  SUM(s.require_human_review) as require_review,
  AVG(s.ai_confidence_avg) as avg_confidence,
  AVG(s.processing_time_seconds) as avg_processing_seconds,
  SUM(CASE WHEN s.status = 'approved' THEN 1 ELSE 0 END) as approved_count
FROM payroll_ai_check_sessions s
WHERE s.started_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
GROUP BY DATE(s.started_at), s.check_type
ORDER BY check_date DESC;

-- Rule effectiveness tracking
CREATE OR REPLACE VIEW `v_ai_check_rule_effectiveness` AS
SELECT
  r.rule_name,
  r.check_module,
  r.check_category,
  r.is_active,
  r.run_count,
  r.pass_count,
  r.fail_count,
  ROUND(100.0 * r.fail_count / NULLIF(r.run_count, 0), 2) as fail_rate_pct,
  r.can_auto_resolve,
  r.last_run_at,
  DATEDIFF(NOW(), r.last_run_at) as days_since_last_run
FROM payroll_ai_check_rules r
ORDER BY r.fail_count DESC, r.run_count DESC;

-- ============================================================================
-- DEPLOYMENT COMPLETE - RUN VERIFICATION
-- ============================================================================

SELECT 'NZ COMPLIANCE + WAGES DISCREPANCY + AI ORCHESTRATOR + AI DECISION ENGINE CREATED SUCCESSFULLY!' as status;
SELECT '========================================' as separator;
SELECT COUNT(*) as nz_tables_created FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME LIKE 'payroll_nz_%';
SELECT COUNT(*) as discrepancy_tables_created FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME LIKE '%wages_discrepanc%';
SELECT COUNT(*) as ai_check_tables_created FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME LIKE '%ai_check%';
SELECT COUNT(*) as ai_decision_tables_created FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME LIKE '%ai_decision%';
SELECT COUNT(*) as nz_statutory_deduction_tables_created FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME LIKE 'payroll_nz_%deduction%';
SELECT COUNT(*) as total_check_rules FROM payroll_ai_check_rules WHERE is_active = 1;
