-- Bank Transactions Module Schema
-- Bank reconciliation and transaction matching

CREATE TABLE IF NOT EXISTS `bank_transactions` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `transaction_date` DATE NOT NULL,
    `transaction_time` TIME,
    `description` VARCHAR(500) NOT NULL,
    `reference` VARCHAR(255),
    `particular` VARCHAR(100),
    `code` VARCHAR(50),
    `amount` DECIMAL(12,2) NOT NULL,
    `balance` DECIMAL(12,2),
    `transaction_type` ENUM('debit', 'credit') NOT NULL,
    `bank_account` VARCHAR(100) NOT NULL,
    `category` VARCHAR(100),
    `matched_status` ENUM('unmatched', 'matched', 'partial', 'ignored') DEFAULT 'unmatched',
    `matched_to_id` BIGINT UNSIGNED,
    `matched_to_type` VARCHAR(50),
    `matched_by` INT UNSIGNED,
    `matched_at` TIMESTAMP NULL,
    `reconciled` TINYINT(1) DEFAULT 0,
    `reconciled_at` TIMESTAMP NULL,
    `notes` TEXT,
    `import_batch_id` VARCHAR(50),
    `imported_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_date (`transaction_date`),
    INDEX idx_status (`matched_status`),
    INDEX idx_reconciled (`reconciled`),
    INDEX idx_account (`bank_account`),
    INDEX idx_reference (`reference`),
    INDEX idx_batch (`import_batch_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `bank_matches` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `bank_transaction_id` BIGINT UNSIGNED NOT NULL,
    `matched_type` VARCHAR(50) NOT NULL COMMENT 'vend_sale, xero_invoice, manual_entry, etc',
    `matched_id` VARCHAR(100) NOT NULL,
    `matched_amount` DECIMAL(12,2) NOT NULL,
    `confidence_score` DECIMAL(3,2) COMMENT 'Match confidence 0.00-1.00',
    `match_method` ENUM('auto', 'manual', 'rule-based') NOT NULL,
    `match_details` JSON COMMENT 'Detailed match information',
    `matched_by` INT UNSIGNED,
    `matched_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `verified` TINYINT(1) DEFAULT 0,
    `verified_by` INT UNSIGNED,
    `verified_at` TIMESTAMP NULL,
    `notes` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`bank_transaction_id`) REFERENCES `bank_transactions`(`id`) ON DELETE CASCADE,
    INDEX idx_bank_transaction (`bank_transaction_id`),
    INDEX idx_matched_type (`matched_type`),
    INDEX idx_matched_id (`matched_id`),
    INDEX idx_verified (`verified`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `reconciliation_rules` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `rule_name` VARCHAR(100) NOT NULL,
    `priority` INT DEFAULT 100 COMMENT 'Lower number = higher priority',
    `active` TINYINT(1) DEFAULT 1,
    `conditions` JSON NOT NULL COMMENT 'Match conditions',
    `actions` JSON NOT NULL COMMENT 'Actions to perform on match',
    `match_type` VARCHAR(50) NOT NULL,
    `category` VARCHAR(100),
    `auto_reconcile` TINYINT(1) DEFAULT 0,
    `require_verification` TINYINT(1) DEFAULT 1,
    `times_applied` INT DEFAULT 0,
    `last_applied_at` TIMESTAMP NULL,
    `created_by` INT UNSIGNED,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_active (`active`),
    INDEX idx_priority (`priority`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `bank_import_batches` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `batch_id` VARCHAR(50) UNIQUE NOT NULL,
    `bank_account` VARCHAR(100) NOT NULL,
    `file_name` VARCHAR(255),
    `import_format` VARCHAR(50),
    `date_from` DATE,
    `date_to` DATE,
    `total_transactions` INT DEFAULT 0,
    `total_credits` DECIMAL(12,2) DEFAULT 0.00,
    `total_debits` DECIMAL(12,2) DEFAULT 0.00,
    `imported_by` INT UNSIGNED,
    `imported_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_batch (`batch_id`),
    INDEX idx_account (`bank_account`),
    INDEX idx_imported (`imported_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default reconciliation rules
INSERT INTO `reconciliation_rules` (`rule_name`, `priority`, `conditions`, `actions`, `match_type`, `category`, `auto_reconcile`) VALUES
('Vend Daily Sales', 10, '{"description_contains": ["VEND", "PAYMENT"], "transaction_type": "credit"}', '{"match_to": "vend_sales", "auto_categorize": "sales"}', 'vend_sale', 'Sales', 0),
('EFTPOS Settlements', 20, '{"description_contains": ["EFTPOS", "SETTLEMENT"], "transaction_type": "credit"}', '{"match_to": "vend_payments", "auto_categorize": "payment_processing"}', 'vend_payment', 'Payment Processing', 0),
('Staff Wage Payments', 30, '{"description_contains": ["WAGES", "SALARY", "PAY"], "transaction_type": "debit"}', '{"match_to": "payroll", "auto_categorize": "wages"}', 'payroll', 'Wages', 0);
