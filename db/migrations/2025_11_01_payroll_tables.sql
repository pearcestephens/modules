-- Database migrations for payroll module
-- Date: 2025-11-01
-- Purpose: Create tables for Xero PayrollNZ sync (payruns, payslips, deductions)

-- Payruns table
CREATE TABLE IF NOT EXISTS xero_payruns (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    payrun_id VARCHAR(36) NOT NULL UNIQUE,
    payroll_calendar_id VARCHAR(36) NULL,
    period_start_date DATETIME NULL,
    period_end_date DATETIME NULL,
    payment_date DATE NULL,
    total_cost DECIMAL(15,2) NULL,
    total_pay DECIMAL(15,2) NULL,
    status VARCHAR(50) NULL,
    posted_date_time DATETIME NULL,
    synced_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_payrun_id (payrun_id),
    KEY idx_payment_date (payment_date),
    KEY idx_status (status),
    KEY idx_synced_at (synced_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Payslips table
CREATE TABLE IF NOT EXISTS xero_payslips (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    payslip_id VARCHAR(36) NOT NULL UNIQUE,
    payrun_id VARCHAR(36) NOT NULL,
    employee_id VARCHAR(36) NULL,
    first_name VARCHAR(100) NULL,
    last_name VARCHAR(100) NULL,
    total_earnings DECIMAL(15,2) NULL,
    gross_earnings DECIMAL(15,2) NULL,
    total_pay DECIMAL(15,2) NULL,
    total_employer_taxes DECIMAL(15,2) NULL,
    total_employee_taxes DECIMAL(15,2) NULL,
    total_deductions DECIMAL(15,2) NULL,
    total_reimbursements DECIMAL(15,2) NULL,
    total_statutory_deductions DECIMAL(15,2) NULL,
    total_superannuation DECIMAL(15,2) NULL,
    bacs_hash VARCHAR(255) NULL,
    payment_method VARCHAR(50) NULL,
    synced_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_payslip_id (payslip_id),
    KEY idx_payrun_id (payrun_id),
    KEY idx_employee_id (employee_id),
    KEY idx_synced_at (synced_at),
    FOREIGN KEY (payrun_id) REFERENCES xero_payruns(payrun_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Payslip deductions table
CREATE TABLE IF NOT EXISTS xero_payslip_deductions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    payslip_id VARCHAR(36) NOT NULL,
    deduction_type_id VARCHAR(36) NULL,
    display_name VARCHAR(255) NULL,
    calculation_type VARCHAR(50) NULL,
    standard_amount DECIMAL(15,2) NULL,
    reduces_super_liability TINYINT(1) DEFAULT 0,
    reduces_tax_liability TINYINT(1) DEFAULT 0,
    amount DECIMAL(15,2) NULL,
    synced_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_payslip_id (payslip_id),
    KEY idx_deduction_type_id (deduction_type_id),
    KEY idx_synced_at (synced_at),
    UNIQUE KEY unique_payslip_deduction (payslip_id, deduction_type_id),
    FOREIGN KEY (payslip_id) REFERENCES xero_payslips(payslip_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
