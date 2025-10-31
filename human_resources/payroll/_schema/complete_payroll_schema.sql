-- ============================================================================
-- COMPLETE PAYROLL SNAPSHOT SYSTEM SCHEMA
-- ============================================================================
-- Purpose: Store EVERYTHING about payroll runs for historical analysis,
--          amendments, auditing, and "what-if" scenarios
--
-- Design Philosophy:
--   1. Weekly pay runs start Tuesday (or on demand)
--   2. Snapshot captures state BEFORE any Xero push
--   3. Every button click = new revision within the run
--   4. Full diff capability between any two states
--   5. Amendment tracking with approval workflow
--
-- Created: 2025-10-29
-- Author: CIS Payroll Bot
-- ============================================================================

-- ============================================================================
-- 1. PAY RUN TRACKING (The Container)
-- ============================================================================
CREATE TABLE IF NOT EXISTS payroll_runs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    -- Run Identification
    run_uuid VARCHAR(36) NOT NULL UNIQUE,
    run_number INT UNSIGNED NOT NULL COMMENT 'Sequential: 1, 2, 3...',

    -- Timing
    period_start DATE NOT NULL COMMENT 'Pay period start (Monday)',
    period_end DATE NOT NULL COMMENT 'Pay period end (Sunday)',
    payment_date DATE NOT NULL COMMENT 'Actual payment date',
    started_at DATETIME NOT NULL COMMENT 'When "Load Payroll" clicked',
    completed_at DATETIME NULL COMMENT 'When final push completed',

    -- Status Tracking
    status ENUM('draft', 'in_progress', 'pushed_to_xero', 'posted', 'amended', 'cancelled')
        NOT NULL DEFAULT 'draft',

    -- Links
    xero_payroll_id VARCHAR(36) NULL COMMENT 'Xero PayRun ID (after push)',
    xero_tenant_id VARCHAR(36) NOT NULL,

    -- Summary Stats (denormalized for speed)
    employee_count INT UNSIGNED DEFAULT 0,
    total_hours DECIMAL(10,2) DEFAULT 0,
    total_gross DECIMAL(12,2) DEFAULT 0,
    total_deductions DECIMAL(12,2) DEFAULT 0,
    total_net DECIMAL(12,2) DEFAULT 0,

    -- User Context
    created_by_user_id INT UNSIGNED NULL,
    completed_by_user_id INT UNSIGNED NULL,

    -- Metadata
    notes TEXT NULL COMMENT 'User notes about this run',
    tags JSON NULL COMMENT 'Flexible tags: ["urgent", "amended", etc.]',

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_period (period_start, period_end),
    INDEX idx_status (status),
    INDEX idx_started (started_at),
    INDEX idx_xero_payroll (xero_payroll_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Weekly pay run container - one per pay period';

-- ============================================================================
-- 2. REVISION TRACKING (Every Button Click)
-- ============================================================================
CREATE TABLE IF NOT EXISTS payroll_run_revisions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    run_id INT UNSIGNED NOT NULL,
    revision_number INT UNSIGNED NOT NULL COMMENT 'Within this run: 1, 2, 3...',

    -- What Happened
    action_type ENUM(
        'load_payroll',          -- Initial load from Deputy
        'calculate_bonuses',     -- Monthly bonus calculation
        'add_commission',        -- Commission added
        'adjust_hours',          -- Manual hour adjustment
        'override_pay',          -- Manual pay override
        'add_deduction',         -- Add account payment
        'push_to_xero',          -- Pushed to Xero
        'create_day_in_lieu',    -- Alternative holiday created
        'amendment',             -- Post-posting amendment
        'other'
    ) NOT NULL,

    action_description TEXT NULL COMMENT 'Human-readable: "Added $50 Google review bonus to John"',

    -- Who & When
    performed_by_user_id INT UNSIGNED NULL,
    performed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    -- Snapshot Reference
    snapshot_id INT UNSIGNED NULL COMMENT 'Links to payroll_snapshots',

    -- Changes Summary
    employees_affected INT UNSIGNED DEFAULT 0,
    total_pay_delta DECIMAL(12,2) DEFAULT 0 COMMENT 'Net change in total pay',

    -- Technical Details
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (run_id) REFERENCES payroll_runs(id) ON DELETE CASCADE,
    INDEX idx_run_revision (run_id, revision_number),
    INDEX idx_action (action_type),
    INDEX idx_performed (performed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Every button click / action within a pay run';

-- ============================================================================
-- 3. COMPLETE SNAPSHOTS (The Gold - Full State Capture)
-- ============================================================================
CREATE TABLE IF NOT EXISTS payroll_snapshots (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    run_id INT UNSIGNED NOT NULL,
    revision_id INT UNSIGNED NULL COMMENT 'Which revision triggered this snapshot',

    -- Timing
    snapshot_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    snapshot_type ENUM('pre_load', 'pre_push', 'post_push', 'amendment', 'manual')
        NOT NULL DEFAULT 'manual',

    -- THE COMPLETE STATE (JSON Blobs)
    -- ====================================

    -- 1. All User Objects (as processed by CIS)
    user_objects_json LONGTEXT NOT NULL COMMENT 'Array of complete $userObject for all staff',

    -- 2. Deputy Data
    deputy_timesheets_json LONGTEXT NULL COMMENT 'Raw Deputy timesheet responses',
    deputy_leave_json TEXT NULL COMMENT 'Deputy leave data',

    -- 3. Vend Data
    vend_account_balances_json TEXT NULL COMMENT 'All Vend customer account balances at snapshot time',

    -- 4. Xero Data (if available)
    xero_payslips_json LONGTEXT NULL COMMENT 'Xero payslip responses (after push)',
    xero_employees_json TEXT NULL COMMENT 'Xero employee details',
    xero_leave_json TEXT NULL COMMENT 'Xero leave balances',

    -- 5. Public Holiday Data
    public_holidays_json TEXT NULL COMMENT 'Calendarific public holiday data for period',

    -- 6. Bonus Calculations
    bonus_calculations_json TEXT NULL COMMENT 'Detailed bonus calculation breakdown',

    -- 7. Amendments (if any)
    amendments_json TEXT NULL COMMENT 'Any manual adjustments made',

    -- 8. Configuration State
    config_snapshot_json TEXT NULL COMMENT 'Payroll config at time of snapshot',

    -- Hash for Integrity
    data_hash VARCHAR(64) NOT NULL COMMENT 'SHA256 of combined data for tamper detection',

    -- Compression (future)
    is_compressed BOOLEAN DEFAULT FALSE,
    compression_method VARCHAR(20) NULL COMMENT 'gzip, lz4, etc.',

    -- Stats (denormalized for quick queries)
    employee_count INT UNSIGNED DEFAULT 0,
    total_size_bytes INT UNSIGNED DEFAULT 0,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (run_id) REFERENCES payroll_runs(id) ON DELETE CASCADE,
    FOREIGN KEY (revision_id) REFERENCES payroll_run_revisions(id) ON DELETE SET NULL,
    INDEX idx_run (run_id),
    INDEX idx_snapshot_type (snapshot_type),
    INDEX idx_snapshot_at (snapshot_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Complete state snapshots - the gold standard for historical data';

-- ============================================================================
-- 4. PER-EMPLOYEE DETAILS (Normalized for Querying)
-- ============================================================================
CREATE TABLE IF NOT EXISTS payroll_employee_details (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    run_id INT UNSIGNED NOT NULL,
    snapshot_id INT UNSIGNED NULL COMMENT 'Which snapshot this came from',

    -- Employee Links
    user_id INT UNSIGNED NOT NULL,
    xero_employee_id VARCHAR(36) NULL,
    xero_payslip_id VARCHAR(36) NULL,
    deputy_employee_id VARCHAR(50) NULL,
    vend_customer_id VARCHAR(36) NULL,

    -- Identity
    employee_name VARCHAR(255) NOT NULL,
    employee_email VARCHAR(255) NULL,

    -- Hours & Time
    total_hours DECIMAL(10,2) DEFAULT 0,
    ordinary_hours DECIMAL(10,2) DEFAULT 0,
    overtime_hours DECIMAL(10,2) DEFAULT 0,
    leave_hours DECIMAL(10,2) DEFAULT 0,
    public_holiday_hours DECIMAL(10,2) DEFAULT 0,

    -- Pay Breakdown
    base_pay DECIMAL(10,2) DEFAULT 0,
    overtime_pay DECIMAL(10,2) DEFAULT 0,
    commission DECIMAL(10,2) DEFAULT 0,
    monthly_bonus DECIMAL(10,2) DEFAULT 0,
    google_review_bonus DECIMAL(10,2) DEFAULT 0,
    vape_drops_bonus DECIMAL(10,2) DEFAULT 0,
    other_bonuses DECIMAL(10,2) DEFAULT 0,
    leave_pay DECIMAL(10,2) DEFAULT 0,
    public_holiday_pay DECIMAL(10,2) DEFAULT 0,

    gross_earnings DECIMAL(10,2) DEFAULT 0,

    -- Deductions
    account_payment_deduction DECIMAL(10,2) DEFAULT 0,
    other_deductions DECIMAL(10,2) DEFAULT 0,
    total_deductions DECIMAL(10,2) DEFAULT 0,

    -- Net Pay
    net_pay DECIMAL(10,2) DEFAULT 0,

    -- Rates
    hourly_rate DECIMAL(10,4) NULL,
    salary_annual DECIMAL(10,2) NULL,

    -- Vend Context
    vend_account_balance DECIMAL(10,2) NULL COMMENT 'Vend balance at time of payroll',

    -- Deputy Context
    deputy_timesheet_count INT UNSIGNED DEFAULT 0,
    deputy_first_punch DATETIME NULL,
    deputy_last_punch DATETIME NULL,

    -- Public Holiday Details
    public_holiday_worked BOOLEAN DEFAULT FALSE,
    public_holiday_preference ENUM('day_in_lieu', 'pay_out') NULL,
    alternative_holiday_created BOOLEAN DEFAULT FALSE,
    alternative_holiday_hours DECIMAL(5,2) NULL,

    -- Status
    processing_status ENUM('pending', 'processed', 'pushed', 'error', 'skipped')
        DEFAULT 'pending',
    skip_reason TEXT NULL,
    error_message TEXT NULL,

    -- Full Data Reference
    full_user_object_json TEXT NULL COMMENT 'Complete $userObject for this employee',

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (run_id) REFERENCES payroll_runs(id) ON DELETE CASCADE,
    FOREIGN KEY (snapshot_id) REFERENCES payroll_snapshots(id) ON DELETE SET NULL,
    FOREIGN KEY (user_id) REFERENCES users(staff_id) ON DELETE CASCADE,

    INDEX idx_run_user (run_id, user_id),
    INDEX idx_xero_employee (xero_employee_id),
    INDEX idx_status (processing_status),
    UNIQUE KEY unique_run_employee (run_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Per-employee normalized data for fast querying';

-- ============================================================================
-- 5. EARNINGS LINE ITEMS (Detailed Breakdown)
-- ============================================================================
CREATE TABLE IF NOT EXISTS payroll_earnings_lines (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    employee_detail_id INT UNSIGNED NOT NULL,

    -- Earning Details
    earning_type VARCHAR(100) NOT NULL COMMENT 'Ordinary Time, Commission, Bonus, Leave, etc.',
    earning_rate_id VARCHAR(36) NULL COMMENT 'Xero earnings rate UUID',
    earning_rate_name VARCHAR(255) NULL,

    -- Units & Amounts
    units DECIMAL(10,4) NULL COMMENT 'Hours or quantity',
    rate_per_unit DECIMAL(10,4) NULL COMMENT 'e.g., $25.50/hr',
    fixed_amount DECIMAL(10,2) NULL COMMENT 'For bonuses',
    total_amount DECIMAL(10,2) NOT NULL,

    -- Flags
    is_leave BOOLEAN DEFAULT FALSE,
    is_overtime BOOLEAN DEFAULT FALSE,
    is_bonus BOOLEAN DEFAULT FALSE,
    is_public_holiday BOOLEAN DEFAULT FALSE,

    -- Source Tracking
    source_type ENUM('deputy', 'manual', 'calculated', 'xero', 'amendment') NOT NULL,
    source_reference VARCHAR(255) NULL COMMENT 'Deputy timesheet ID, activity ID, etc.',

    -- Metadata
    description TEXT NULL,
    calculation_notes TEXT NULL COMMENT 'How this was calculated',

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (employee_detail_id) REFERENCES payroll_employee_details(id) ON DELETE CASCADE,
    INDEX idx_employee (employee_detail_id),
    INDEX idx_earning_type (earning_type),
    INDEX idx_source (source_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Individual earning line items per employee';

-- ============================================================================
-- 6. DEDUCTION LINE ITEMS
-- ============================================================================
CREATE TABLE IF NOT EXISTS payroll_deduction_lines (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    employee_detail_id INT UNSIGNED NOT NULL,

    -- Deduction Details
    deduction_type VARCHAR(100) NOT NULL COMMENT 'Account Payment, Tax, KiwiSaver, etc.',
    deduction_code VARCHAR(36) NULL COMMENT 'Xero deduction type UUID',
    deduction_name VARCHAR(255) NULL,

    -- Amount
    amount DECIMAL(10,2) NOT NULL,

    -- Vend Integration
    vend_customer_id VARCHAR(36) NULL,
    vend_payment_id VARCHAR(36) NULL COMMENT 'After allocation',
    allocation_status ENUM('pending', 'allocated', 'failed') DEFAULT 'pending',
    allocated_at DATETIME NULL,
    allocation_error TEXT NULL,

    -- Source
    source_type ENUM('automatic', 'manual', 'amendment') NOT NULL,
    source_reference VARCHAR(255) NULL,

    -- Metadata
    description TEXT NULL,
    notes TEXT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (employee_detail_id) REFERENCES payroll_employee_details(id) ON DELETE CASCADE,
    INDEX idx_employee (employee_detail_id),
    INDEX idx_vend_customer (vend_customer_id),
    INDEX idx_allocation (allocation_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Individual deduction line items per employee';

-- ============================================================================
-- 7. PUBLIC HOLIDAY TRACKING
-- ============================================================================
CREATE TABLE IF NOT EXISTS payroll_public_holidays (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    employee_detail_id INT UNSIGNED NOT NULL,

    -- Holiday Details
    holiday_date DATE NOT NULL,
    holiday_name VARCHAR(255) NOT NULL,

    -- Work Details
    hours_worked DECIMAL(5,2) NULL COMMENT 'Null if didn\'t work',
    worked BOOLEAN DEFAULT FALSE,

    -- Employee Preference
    preference ENUM('day_in_lieu', 'pay_out') NOT NULL,

    -- Actions Taken
    earnings_zeroed BOOLEAN DEFAULT FALSE COMMENT 'Ordinary pay removed',
    alternative_holiday_created BOOLEAN DEFAULT FALSE,
    leave_hours_granted DECIMAL(5,2) NULL,
    xero_leave_id VARCHAR(36) NULL,

    -- Pay Impact
    ordinary_pay_removed DECIMAL(10,2) NULL,
    public_holiday_rate_applied BOOLEAN DEFAULT FALSE,
    total_pay_impact DECIMAL(10,2) NULL COMMENT 'Net impact on gross pay',

    -- Metadata
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (employee_detail_id) REFERENCES payroll_employee_details(id) ON DELETE CASCADE,
    INDEX idx_employee (employee_detail_id),
    INDEX idx_holiday (holiday_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Public holiday tracking for day in lieu implementation';

-- ============================================================================
-- 8. AMENDMENTS & ADJUSTMENTS (Post-Posting Changes)
-- ============================================================================
CREATE TABLE IF NOT EXISTS payroll_amendments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    run_id INT UNSIGNED NOT NULL,
    employee_detail_id INT UNSIGNED NULL COMMENT 'Null if run-wide amendment',

    -- Amendment Details
    amendment_type ENUM(
        'pay_correction',
        'hours_adjustment',
        'bonus_addition',
        'deduction_adjustment',
        'leave_correction',
        'other'
    ) NOT NULL,

    -- What Changed
    field_name VARCHAR(100) NULL COMMENT 'e.g., "gross_earnings", "commission"',
    old_value DECIMAL(10,2) NULL,
    new_value DECIMAL(10,2) NULL,
    delta DECIMAL(10,2) NULL COMMENT 'new - old',

    -- Reason & Approval
    reason TEXT NOT NULL COMMENT 'Why was this changed?',
    requested_by_user_id INT UNSIGNED NULL,
    approved_by_user_id INT UNSIGNED NULL,
    approval_status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',

    -- Payment
    payment_method ENUM('next_payroll', 'separate_payment', 'bank_transfer') NULL,
    payment_reference VARCHAR(255) NULL,
    paid_at DATETIME NULL,

    -- Snapshots
    before_snapshot_id INT UNSIGNED NULL,
    after_snapshot_id INT UNSIGNED NULL,

    -- Metadata
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    approved_at DATETIME NULL,

    FOREIGN KEY (run_id) REFERENCES payroll_runs(id) ON DELETE CASCADE,
    FOREIGN KEY (employee_detail_id) REFERENCES payroll_employee_details(id) ON DELETE CASCADE,
    INDEX idx_run (run_id),
    INDEX idx_status (approval_status),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Post-posting amendments and corrections';

-- ============================================================================
-- 9. DIFF TRACKING (What Changed Between Snapshots)
-- ============================================================================
CREATE TABLE IF NOT EXISTS payroll_snapshot_diffs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    from_snapshot_id INT UNSIGNED NOT NULL,
    to_snapshot_id INT UNSIGNED NOT NULL,

    -- Summary Stats
    employees_added INT UNSIGNED DEFAULT 0,
    employees_removed INT UNSIGNED DEFAULT 0,
    employees_changed INT UNSIGNED DEFAULT 0,
    total_pay_delta DECIMAL(12,2) DEFAULT 0,

    -- Detailed Changes
    changes_json LONGTEXT NULL COMMENT 'Detailed diff structure',

    -- Metadata
    computed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    computation_time_ms INT UNSIGNED NULL,

    FOREIGN KEY (from_snapshot_id) REFERENCES payroll_snapshots(id) ON DELETE CASCADE,
    FOREIGN KEY (to_snapshot_id) REFERENCES payroll_snapshots(id) ON DELETE CASCADE,
    INDEX idx_from (from_snapshot_id),
    INDEX idx_to (to_snapshot_id),
    UNIQUE KEY unique_diff (from_snapshot_id, to_snapshot_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Pre-computed diffs between snapshot pairs';

-- ============================================================================
-- 10. 🆕 XERO PAYSLIP LINE ITEMS (Detailed Breakdown)
-- ============================================================================
CREATE TABLE IF NOT EXISTS payroll_xero_payslip_lines (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    -- Links
    run_id INT UNSIGNED NOT NULL,
    snapshot_id INT UNSIGNED NOT NULL,
    employee_detail_id INT UNSIGNED NOT NULL,

    -- Xero IDs
    xero_payslip_id VARCHAR(36) NOT NULL,
    xero_employee_id VARCHAR(36) NOT NULL,

    -- Line Type
    line_category ENUM(
        'earnings',           -- Ordinary, overtime, bonuses
        'deduction',          -- Account payments, other deductions
        'leave_earnings',     -- Annual leave, sick leave
        'reimbursement',      -- Expense reimbursements
        'employee_tax',       -- PAYE, student loan
        'employer_tax',       -- Employer contributions
        'superannuation',     -- KiwiSaver employee/employer
        'leave_accrual',      -- Leave accrued this period
        'statutory_deduction' -- Child support, etc.
    ) NOT NULL,

    -- Line Details
    line_type_id VARCHAR(36) NULL COMMENT 'earnings_rate_id, deduction_type_id, etc.',
    display_name VARCHAR(255) NULL COMMENT 'e.g., "Ordinary Time", "Account Payment"',
    description TEXT NULL,

    -- Amounts & Calculations
    rate_per_unit DECIMAL(10,4) NULL COMMENT 'Hourly rate, etc.',
    number_of_units DECIMAL(10,2) NULL COMMENT 'Hours, days, etc.',
    fixed_amount DECIMAL(10,2) NULL COMMENT 'Fixed dollar amount',
    percentage DECIMAL(5,2) NULL COMMENT 'For percentage-based deductions',
    calculated_amount DECIMAL(10,2) NOT NULL COMMENT 'Final amount for this line',

    -- Flags
    is_linked_to_timesheet BOOLEAN DEFAULT FALSE,
    is_average_daily_pay_rate BOOLEAN DEFAULT FALSE,
    auto_calculate BOOLEAN DEFAULT FALSE,

    -- Tax/Super specific
    tax_type VARCHAR(50) NULL COMMENT 'PAYE, Student Loan, etc.',
    employee_contribution DECIMAL(10,2) NULL COMMENT 'For KiwiSaver',
    employer_contribution DECIMAL(10,2) NULL COMMENT 'For KiwiSaver',

    -- Leave specific
    leave_type_id VARCHAR(36) NULL,
    leave_units DECIMAL(10,2) NULL COMMENT 'Hours/days of leave',

    -- Dates
    period_start_date DATE NULL,
    period_end_date DATE NULL,
    payment_date DATE NULL,

    -- Full line data (for reference)
    full_line_json TEXT NULL COMMENT 'Complete line object as JSON',

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (run_id) REFERENCES payroll_runs(id) ON DELETE CASCADE,
    FOREIGN KEY (snapshot_id) REFERENCES payroll_snapshots(id) ON DELETE CASCADE,
    FOREIGN KEY (employee_detail_id) REFERENCES payroll_employee_details(id) ON DELETE CASCADE,

    INDEX idx_run (run_id),
    INDEX idx_snapshot (snapshot_id),
    INDEX idx_employee (employee_detail_id),
    INDEX idx_xero_payslip (xero_payslip_id),
    INDEX idx_line_category (line_category),
    INDEX idx_line_type (line_type_id),
    INDEX idx_payment_date (payment_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Individual line items from Xero payslips - earnings, deductions, tax, super, leave';

-- ============================================================================
-- VIEWS FOR COMMON QUERIES
-- ============================================================================

-- Latest snapshot per run
CREATE OR REPLACE VIEW payroll_runs_latest_snapshot AS
SELECT
    pr.id AS run_id,
    pr.run_uuid,
    pr.period_start,
    pr.period_end,
    pr.status,
    ps.id AS snapshot_id,
    ps.snapshot_at,
    ps.employee_count,
    pr.total_gross,
    pr.total_net
FROM payroll_runs pr
LEFT JOIN payroll_snapshots ps ON ps.id = (
    SELECT id FROM payroll_snapshots
    WHERE run_id = pr.id
    ORDER BY snapshot_at DESC
    LIMIT 1
);

-- Employee pay history (across all runs)
CREATE OR REPLACE VIEW payroll_employee_history AS
SELECT
    u.staff_id,
    u.first_name,
    u.last_name,
    pr.period_start,
    pr.period_end,
    ped.total_hours,
    ped.gross_earnings,
    ped.total_deductions,
    ped.net_pay,
    ped.commission,
    ped.monthly_bonus,
    pr.status AS run_status
FROM payroll_employee_details ped
JOIN payroll_runs pr ON pr.id = ped.run_id
JOIN users u ON u.staff_id = ped.user_id
ORDER BY pr.period_start DESC, u.last_name;

-- ============================================================================
-- INDEXES FOR PERFORMANCE
-- ============================================================================

-- Composite indexes for common queries
ALTER TABLE payroll_employee_details
ADD INDEX idx_user_period (user_id, run_id);

ALTER TABLE payroll_earnings_lines
ADD INDEX idx_employee_type (employee_detail_id, earning_type);

ALTER TABLE payroll_deduction_lines
ADD INDEX idx_employee_type (employee_detail_id, deduction_type);

-- Full-text search on notes/descriptions
ALTER TABLE payroll_runs
ADD FULLTEXT INDEX ft_notes (notes);

ALTER TABLE payroll_amendments
ADD FULLTEXT INDEX ft_reason (reason);

-- ============================================================================
-- TRIGGERS FOR AUTOMATIC STATS UPDATES
-- ============================================================================

DELIMITER //

-- Update run stats when employee details change
CREATE TRIGGER update_run_stats_after_employee_insert
AFTER INSERT ON payroll_employee_details
FOR EACH ROW
BEGIN
    UPDATE payroll_runs
    SET
        employee_count = (SELECT COUNT(*) FROM payroll_employee_details WHERE run_id = NEW.run_id),
        total_hours = (SELECT IFNULL(SUM(total_hours), 0) FROM payroll_employee_details WHERE run_id = NEW.run_id),
        total_gross = (SELECT IFNULL(SUM(gross_earnings), 0) FROM payroll_employee_details WHERE run_id = NEW.run_id),
        total_deductions = (SELECT IFNULL(SUM(total_deductions), 0) FROM payroll_employee_details WHERE run_id = NEW.run_id),
        total_net = (SELECT IFNULL(SUM(net_pay), 0) FROM payroll_employee_details WHERE run_id = NEW.run_id)
    WHERE id = NEW.run_id;
END//

CREATE TRIGGER update_run_stats_after_employee_update
AFTER UPDATE ON payroll_employee_details
FOR EACH ROW
BEGIN
    UPDATE payroll_runs
    SET
        total_hours = (SELECT IFNULL(SUM(total_hours), 0) FROM payroll_employee_details WHERE run_id = NEW.run_id),
        total_gross = (SELECT IFNULL(SUM(gross_earnings), 0) FROM payroll_employee_details WHERE run_id = NEW.run_id),
        total_deductions = (SELECT IFNULL(SUM(total_deductions), 0) FROM payroll_employee_details WHERE run_id = NEW.run_id),
        total_net = (SELECT IFNULL(SUM(net_pay), 0) FROM payroll_employee_details WHERE run_id = NEW.run_id)
    WHERE id = NEW.run_id;
END//

DELIMITER ;

-- ============================================================================
-- INITIAL DATA SETUP
-- ============================================================================

-- Set starting run number based on existing xero_payrolls
INSERT INTO payroll_runs (run_uuid, run_number, period_start, period_end, payment_date, started_at, status, xero_tenant_id, notes)
SELECT
    UUID() AS run_uuid,
    ROW_NUMBER() OVER (ORDER BY pay_period_start) AS run_number,
    pay_period_start,
    pay_period_end,
    payment_date,
    created_at AS started_at,
    'posted' AS status,
    'your-xero-tenant-id' AS xero_tenant_id,
    'Migrated from xero_payrolls' AS notes
FROM xero_payrolls
WHERE 1=0; -- DISABLED - uncomment to backfill historical data

-- ============================================================================
-- SCHEMA COMPLETE ✅
-- ============================================================================
-- Total Tables: 9
-- Total Views: 2
-- Total Triggers: 2
-- Total Indexes: 25+
--
-- Next Steps:
-- 1. Create storage functions in xero-payruns.php
-- 2. Hook snapshot creation into every button click
-- 3. Build diff engine for "what changed" queries
-- 4. Create amendment workflow UI
-- 5. Build reporting queries
-- ============================================================================
