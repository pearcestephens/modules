-- ============================================================================
-- PAYROLL AI AUTOMATION - COMPLETE DATABASE SCHEMA
-- ============================================================================
-- Purpose: Full automation of payroll with AI assistance
-- Features:
--   - Timesheet amendments with AI approval
--   - Pay run line item adjustments with AI
--   - Vend payment automation
--   - Bank payment automation
--   - Complete audit trail and context tracking
--   - AI decision logging
--   - CIS Logger integration
--
-- Version: 2.0.0
-- Date: October 28, 2025
-- Database: MariaDB 10.5+
-- Naming: All tables prefixed with `payroll_`
-- ============================================================================

-- ============================================================================
-- 1. TIMESHEET AMENDMENTS (Enhanced from existing)
-- ============================================================================

-- Main timesheet amendments table (already exists but enhanced)
CREATE TABLE IF NOT EXISTS `payroll_timesheet_amendments` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `staff_id` INT UNSIGNED NOT NULL,
  `vend_outlet_id` VARCHAR(50) NULL,
  `deputy_timesheet_id` INT UNSIGNED NULL COMMENT 'Original Deputy timesheet ID',

  -- Times (what staff member claims vs what system has)
  `claimed_start_time` DATETIME NOT NULL COMMENT 'What staff claims they started',
  `claimed_end_time` DATETIME NOT NULL COMMENT 'What staff claims they ended',
  `actual_start_time` DATETIME NULL COMMENT 'System recorded start',
  `actual_end_time` DATETIME NULL COMMENT 'System recorded end',
  `approved_start_time` DATETIME NULL COMMENT 'Final approved start',
  `approved_end_time` DATETIME NULL COMMENT 'Final approved end',

  -- Break time
  `claimed_break_minutes` INT UNSIGNED DEFAULT 0,
  `calculated_break_minutes` INT UNSIGNED NULL,
  `approved_break_minutes` INT UNSIGNED NULL,

  -- Reason and context
  `reason` TEXT NULL COMMENT 'Staff explanation for amendment',
  `staff_notes` TEXT NULL COMMENT 'Additional notes from staff',
  `context_json` JSON NULL COMMENT 'Full context: roster, punches, location, etc.',

  -- Status workflow
  `status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0=pending, 1=accepted, 2=declined, 3=deleted, 4=ai_review, 5=escalated',
  `status_changed_at` DATETIME NULL,
  `status_changed_by` INT UNSIGNED NULL,

  -- AI processing
  `ai_reviewed` TINYINT(1) DEFAULT 0 COMMENT 'Has AI reviewed this?',
  `ai_decision` ENUM('approve', 'decline', 'escalate', 'needs_info') NULL,
  `ai_confidence_score` DECIMAL(5,4) NULL COMMENT 'AI confidence 0.0000-1.0000',
  `ai_reasoning` TEXT NULL COMMENT 'AI explanation for decision',
  `ai_reviewed_at` DATETIME NULL,
  `ai_model_version` VARCHAR(50) NULL,

  -- Escalation
  `escalated_to_user_id` INT UNSIGNED NULL,
  `escalation_reason` TEXT NULL,
  `escalated_at` DATETIME NULL,

  -- Deputy sync
  `synced_to_deputy` TINYINT(1) DEFAULT 0,
  `synced_at` DATETIME NULL,
  `deputy_response` JSON NULL,

  -- Audit
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` DATETIME NULL COMMENT 'Soft delete',
  `created_by_ip` VARCHAR(45) NULL,
  `created_by_user_agent` TEXT NULL,

  PRIMARY KEY (`id`),
  KEY `idx_staff_status` (`staff_id`, `status`),
  KEY `idx_status` (`status`),
  KEY `idx_ai_review` (`ai_reviewed`, `ai_decision`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_deputy_timesheet` (`deputy_timesheet_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Amendment approval history (complete audit trail)
CREATE TABLE IF NOT EXISTS `payroll_timesheet_amendment_history` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `amendment_id` INT UNSIGNED NOT NULL,
  `action` ENUM('created', 'updated', 'ai_reviewed', 'approved', 'declined', 'escalated', 'synced', 'deleted') NOT NULL,
  `actor_type` ENUM('staff', 'admin', 'ai', 'system') NOT NULL,
  `actor_id` INT UNSIGNED NULL COMMENT 'User ID or AI model ID',
  `actor_name` VARCHAR(255) NULL,
  `old_values` JSON NULL COMMENT 'Previous state',
  `new_values` JSON NULL COMMENT 'New state',
  `reason` TEXT NULL,
  `metadata` JSON NULL COMMENT 'Additional context',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ip_address` VARCHAR(45) NULL,
  PRIMARY KEY (`id`),
  KEY `idx_amendment` (`amendment_id`),
  KEY `idx_actor` (`actor_type`, `actor_id`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `fk_amendment_history_amendment`
    FOREIGN KEY (`amendment_id`)
    REFERENCES `payroll_timesheet_amendments` (`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 2. PAY RUN LINE ITEM ADJUSTMENTS (AI-Powered)
-- ============================================================================

-- Pay run adjustments (staff requests changes to pay items)
CREATE TABLE IF NOT EXISTS `payroll_payrun_line_adjustments` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `payroll_run_id` VARCHAR(50) NOT NULL COMMENT 'From payroll_runs.run_id',
  `employee_detail_id` INT UNSIGNED NULL COMMENT 'From payroll_employee_details.id',
  `staff_id` INT UNSIGNED NOT NULL,
  `xero_employee_id` VARCHAR(50) NULL,

  -- Original line item (from Xero payslip)
  `line_category` ENUM('earnings', 'deduction', 'leave_earnings', 'reimbursement', 'employee_tax', 'employer_tax', 'superannuation', 'leave_accrual', 'statutory_deduction') NOT NULL,
  `line_type_id` VARCHAR(50) NULL COMMENT 'Xero earnings/deduction type ID',
  `line_display_name` VARCHAR(255) NULL,
  `original_amount` DECIMAL(10,2) NULL,
  `original_units` DECIMAL(10,2) NULL,
  `original_rate` DECIMAL(10,4) NULL,

  -- Requested change
  `requested_amount` DECIMAL(10,2) NULL,
  `requested_units` DECIMAL(10,2) NULL,
  `requested_rate` DECIMAL(10,4) NULL,
  `adjustment_type` ENUM('add_line', 'modify_line', 'remove_line') NOT NULL,

  -- Reason and evidence
  `reason` TEXT NOT NULL COMMENT 'Staff explanation',
  `evidence_files` JSON NULL COMMENT 'Array of file paths/URLs',
  `context_json` JSON NULL COMMENT 'Full context: timesheet, roster, etc.',

  -- Status workflow
  `status` ENUM('pending', 'ai_review', 'approved', 'declined', 'escalated', 'applied', 'cancelled') NOT NULL DEFAULT 'pending',
  `status_changed_at` DATETIME NULL,
  `status_changed_by` INT UNSIGNED NULL,

  -- AI processing
  `ai_reviewed` TINYINT(1) DEFAULT 0,
  `ai_decision` ENUM('approve', 'decline', 'escalate', 'needs_info') NULL,
  `ai_confidence_score` DECIMAL(5,4) NULL,
  `ai_reasoning` TEXT NULL,
  `ai_reviewed_at` DATETIME NULL,
  `ai_model_version` VARCHAR(50) NULL,
  `ai_risk_score` DECIMAL(5,4) NULL COMMENT 'Financial/compliance risk',

  -- Escalation
  `escalated_to_user_id` INT UNSIGNED NULL,
  `escalation_reason` TEXT NULL,
  `escalated_at` DATETIME NULL,

  -- Application to Xero
  `applied_to_xero` TINYINT(1) DEFAULT 0,
  `applied_at` DATETIME NULL,
  `xero_response` JSON NULL,
  `xero_payslip_id` VARCHAR(50) NULL,

  -- Financial impact
  `gross_impact` DECIMAL(10,2) NULL COMMENT 'Impact on gross pay',
  `tax_impact` DECIMAL(10,2) NULL COMMENT 'Impact on tax',
  `net_impact` DECIMAL(10,2) NULL COMMENT 'Impact on net pay',

  -- Audit
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  `created_by_ip` VARCHAR(45) NULL,
  PRIMARY KEY (`id`),
  KEY `idx_payroll_run` (`payroll_run_id`),
  KEY `idx_staff` (`staff_id`),
  KEY `idx_status` (`status`),
  KEY `idx_ai_review` (`ai_reviewed`, `ai_decision`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Pay run adjustment history
CREATE TABLE IF NOT EXISTS `payroll_payrun_adjustment_history` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `adjustment_id` INT UNSIGNED NOT NULL,
  `action` ENUM('created', 'updated', 'ai_reviewed', 'approved', 'declined', 'escalated', 'applied', 'cancelled') NOT NULL,
  `actor_type` ENUM('staff', 'admin', 'ai', 'system') NOT NULL,
  `actor_id` INT UNSIGNED NULL,
  `actor_name` VARCHAR(255) NULL,
  `old_values` JSON NULL,
  `new_values` JSON NULL,
  `reason` TEXT NULL,
  `metadata` JSON NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ip_address` VARCHAR(45) NULL,
  PRIMARY KEY (`id`),
  KEY `idx_adjustment` (`adjustment_id`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `fk_payrun_adjustment_history`
    FOREIGN KEY (`adjustment_id`)
    REFERENCES `payroll_payrun_line_adjustments` (`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 3. VEND PAYMENT AUTOMATION
-- ============================================================================

-- Vend payment requests (AI-automated)
CREATE TABLE IF NOT EXISTS `payroll_vend_payment_requests` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `payroll_run_id` VARCHAR(50) NOT NULL,
  `staff_id` INT UNSIGNED NOT NULL,
  `vend_customer_id` VARCHAR(50) NULL,

  -- Payment details
  `account_balance` DECIMAL(10,2) NOT NULL COMMENT 'Staff account balance from Xero',
  `payment_amount` DECIMAL(10,2) NOT NULL COMMENT 'Amount to allocate',
  `register_id` VARCHAR(50) NOT NULL,
  `payment_type_id` VARCHAR(50) NOT NULL,

  -- Sales to allocate to
  `sales_json` JSON NULL COMMENT 'Array of sales with amounts',
  `allocation_strategy` ENUM('fifo', 'oldest_first', 'largest_first', 'manual') DEFAULT 'fifo',

  -- Status
  `status` ENUM('pending', 'ai_review', 'approved', 'processing', 'completed', 'failed', 'cancelled') NOT NULL DEFAULT 'pending',
  `status_changed_at` DATETIME NULL,

  -- AI processing
  `ai_reviewed` TINYINT(1) DEFAULT 0,
  `ai_decision` ENUM('approve', 'decline', 'escalate') NULL,
  `ai_confidence_score` DECIMAL(5,4) NULL,
  `ai_reasoning` TEXT NULL,
  `ai_reviewed_at` DATETIME NULL,

  -- Processing
  `processed_sales_count` INT UNSIGNED DEFAULT 0,
  `failed_sales_count` INT UNSIGNED DEFAULT 0,
  `total_allocated` DECIMAL(10,2) DEFAULT 0.00,
  `processing_started_at` DATETIME NULL,
  `processing_completed_at` DATETIME NULL,
  `processing_errors` JSON NULL,

  -- Vend API responses
  `vend_responses` JSON NULL COMMENT 'Array of API responses',

  -- Audit
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_payroll_run` (`payroll_run_id`),
  KEY `idx_staff` (`staff_id`),
  KEY `idx_status` (`status`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Vend payment allocations (detailed log)
CREATE TABLE IF NOT EXISTS `payroll_vend_payment_allocations` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `payment_request_id` INT UNSIGNED NOT NULL,
  `vend_sale_id` VARCHAR(50) NOT NULL,
  `sale_reference` VARCHAR(100) NULL,
  `allocation_amount` DECIMAL(10,2) NOT NULL,
  `balance_before` DECIMAL(10,2) NULL,
  `balance_after` DECIMAL(10,2) NULL,
  `success` TINYINT(1) NOT NULL DEFAULT 0,
  `error_message` TEXT NULL,
  `vend_response` JSON NULL,
  `attempted_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `retry_count` TINYINT UNSIGNED DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_payment_request` (`payment_request_id`),
  KEY `idx_vend_sale` (`vend_sale_id`),
  KEY `idx_success` (`success`),
  CONSTRAINT `fk_vend_allocation_request`
    FOREIGN KEY (`payment_request_id`)
    REFERENCES `payroll_vend_payment_requests` (`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 4. BANK PAYMENT AUTOMATION
-- ============================================================================

-- Bank payment batches (automated payroll payments)
CREATE TABLE IF NOT EXISTS `payroll_bank_payment_batches` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `batch_reference` VARCHAR(100) NOT NULL UNIQUE,
  `payroll_run_id` VARCHAR(50) NOT NULL,
  `xero_pay_run_id` VARCHAR(50) NULL,

  -- Batch details
  `payment_date` DATE NOT NULL,
  `total_amount` DECIMAL(12,2) NOT NULL,
  `payment_count` INT UNSIGNED NOT NULL,
  `bank_account_id` VARCHAR(50) NULL COMMENT 'Source bank account',

  -- Status
  `status` ENUM('pending', 'ai_review', 'approved', 'queued', 'processing', 'sent_to_bank', 'completed', 'failed', 'cancelled') NOT NULL DEFAULT 'pending',
  `status_changed_at` DATETIME NULL,

  -- AI approval
  `ai_reviewed` TINYINT(1) DEFAULT 0,
  `ai_decision` ENUM('approve', 'decline', 'escalate') NULL,
  `ai_confidence_score` DECIMAL(5,4) NULL,
  `ai_reasoning` TEXT NULL,
  `ai_risk_flags` JSON NULL COMMENT 'Fraud detection, anomalies',
  `ai_reviewed_at` DATETIME NULL,

  -- Human approval (if required)
  `requires_human_approval` TINYINT(1) DEFAULT 0,
  `approved_by_user_id` INT UNSIGNED NULL,
  `approved_at` DATETIME NULL,
  `approval_notes` TEXT NULL,

  -- Bank file generation
  `bank_file_format` VARCHAR(50) NULL COMMENT 'ABA, CSV, etc.',
  `bank_file_path` VARCHAR(255) NULL,
  `bank_file_generated_at` DATETIME NULL,

  -- Bank submission
  `submitted_to_bank_at` DATETIME NULL,
  `bank_reference` VARCHAR(100) NULL,
  `bank_response` JSON NULL,

  -- Reconciliation
  `reconciled` TINYINT(1) DEFAULT 0,
  `reconciled_at` DATETIME NULL,
  `reconciliation_notes` TEXT NULL,

  -- Audit
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  `created_by` INT UNSIGNED NULL,
  PRIMARY KEY (`id`),
  KEY `idx_batch_ref` (`batch_reference`),
  KEY `idx_payroll_run` (`payroll_run_id`),
  KEY `idx_status` (`status`),
  KEY `idx_payment_date` (`payment_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Individual bank payments
CREATE TABLE IF NOT EXISTS `payroll_bank_payments` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `batch_id` INT UNSIGNED NOT NULL,
  `staff_id` INT UNSIGNED NOT NULL,
  `xero_employee_id` VARCHAR(50) NULL,

  -- Payment details
  `payee_name` VARCHAR(255) NOT NULL,
  `bank_account_number` VARCHAR(50) NOT NULL COMMENT 'Encrypted',
  `bank_account_name` VARCHAR(255) NULL,
  `payment_amount` DECIMAL(10,2) NOT NULL,
  `payment_reference` VARCHAR(100) NULL,

  -- Breakdown
  `gross_pay` DECIMAL(10,2) NULL,
  `tax_withheld` DECIMAL(10,2) NULL,
  `deductions` DECIMAL(10,2) NULL,
  `net_pay` DECIMAL(10,2) NULL,

  -- Status
  `status` ENUM('pending', 'queued', 'sent', 'completed', 'failed', 'cancelled') NOT NULL DEFAULT 'pending',
  `status_changed_at` DATETIME NULL,
  `error_message` TEXT NULL,

  -- Bank tracking
  `bank_transaction_id` VARCHAR(100) NULL,
  `sent_to_bank_at` DATETIME NULL,
  `completed_at` DATETIME NULL,

  -- Audit
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_batch` (`batch_id`),
  KEY `idx_staff` (`staff_id`),
  KEY `idx_status` (`status`),
  CONSTRAINT `fk_bank_payment_batch`
    FOREIGN KEY (`batch_id`)
    REFERENCES `payroll_bank_payment_batches` (`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 5. AI DECISION TRACKING
-- ============================================================================

-- AI decisions (all AI actions logged)
CREATE TABLE IF NOT EXISTS `payroll_ai_decisions` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `decision_type` ENUM('timesheet_amendment', 'payrun_adjustment', 'vend_payment', 'bank_payment', 'anomaly_detection', 'fraud_check') NOT NULL,
  `entity_type` VARCHAR(50) NOT NULL COMMENT 'Table name',
  `entity_id` INT UNSIGNED NOT NULL COMMENT 'Record ID',

  -- AI model details
  `model_name` VARCHAR(100) NOT NULL COMMENT 'GPT-4, Claude, etc.',
  `model_version` VARCHAR(50) NULL,
  `prompt_hash` CHAR(64) NULL COMMENT 'SHA256 of prompt',

  -- Decision
  `decision` ENUM('approve', 'decline', 'escalate', 'needs_info', 'flag') NOT NULL,
  `confidence_score` DECIMAL(5,4) NOT NULL COMMENT '0.0000-1.0000',
  `reasoning` TEXT NOT NULL COMMENT 'AI explanation',
  `risk_score` DECIMAL(5,4) NULL COMMENT 'Risk assessment',
  `risk_flags` JSON NULL COMMENT 'Specific risk factors',

  -- Context
  `input_data` JSON NULL COMMENT 'Data provided to AI',
  `output_data` JSON NULL COMMENT 'Full AI response',
  `context_window_tokens` INT UNSIGNED NULL,
  `completion_tokens` INT UNSIGNED NULL,
  `processing_time_ms` INT UNSIGNED NULL,

  -- Human override
  `overridden` TINYINT(1) DEFAULT 0,
  `overridden_by` INT UNSIGNED NULL,
  `overridden_at` DATETIME NULL,
  `override_reason` TEXT NULL,

  -- Feedback loop (was AI correct?)
  `outcome` ENUM('correct', 'incorrect', 'unknown') NULL,
  `outcome_recorded_at` DATETIME NULL,
  `feedback_notes` TEXT NULL,

  -- Audit
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `api_cost` DECIMAL(10,6) NULL COMMENT 'API call cost in USD',
  PRIMARY KEY (`id`),
  KEY `idx_decision_type` (`decision_type`),
  KEY `idx_entity` (`entity_type`, `entity_id`),
  KEY `idx_decision` (`decision`),
  KEY `idx_confidence` (`confidence_score`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- AI training feedback (improve AI over time)
CREATE TABLE IF NOT EXISTS `payroll_ai_feedback` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `decision_id` INT UNSIGNED NOT NULL,
  `feedback_type` ENUM('accuracy', 'explanation_quality', 'false_positive', 'false_negative', 'suggestion') NOT NULL,
  `rating` TINYINT UNSIGNED NULL COMMENT '1-5 stars',
  `comments` TEXT NULL,
  `provided_by` INT UNSIGNED NOT NULL,
  `provided_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_decision` (`decision_id`),
  KEY `idx_feedback_type` (`feedback_type`),
  CONSTRAINT `fk_ai_feedback_decision`
    FOREIGN KEY (`decision_id`)
    REFERENCES `payroll_ai_decisions` (`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 6. CONTEXT TRACKING (For AI and Audit)
-- ============================================================================

-- Payroll context snapshots (everything AI needs to know)
CREATE TABLE IF NOT EXISTS `payroll_context_snapshots` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `context_type` VARCHAR(50) NOT NULL COMMENT 'amendment, adjustment, payment, etc.',
  `entity_id` INT UNSIGNED NOT NULL,
  `snapshot_hash` CHAR(64) NOT NULL COMMENT 'SHA256 of snapshot_data',

  -- Context data
  `snapshot_data` JSON NOT NULL COMMENT 'Complete state at time of decision',
  `staff_data` JSON NULL COMMENT 'Staff info, history, patterns',
  `roster_data` JSON NULL COMMENT 'Roster, shifts, expected hours',
  `timesheet_data` JSON NULL COMMENT 'Punches, Deputy data',
  `payroll_data` JSON NULL COMMENT 'Pay rates, YTD totals',
  `historical_data` JSON NULL COMMENT 'Previous amendments, patterns',
  `outlet_data` JSON NULL COMMENT 'Store hours, location',

  -- Metadata
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `data_sources` JSON NULL COMMENT 'Where data came from',
  `data_quality_score` DECIMAL(5,4) NULL COMMENT 'Completeness of context',
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_snapshot_hash` (`snapshot_hash`),
  KEY `idx_context_type` (`context_type`),
  KEY `idx_entity` (`entity_id`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 7. CIS LOGGER INTEGRATION
-- ============================================================================

-- Payroll activity log (comprehensive logging)
CREATE TABLE IF NOT EXISTS `payroll_activity_log` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `log_level` ENUM('debug', 'info', 'notice', 'warning', 'error', 'critical', 'alert', 'emergency') NOT NULL DEFAULT 'info',
  `category` VARCHAR(50) NOT NULL COMMENT 'amendment, adjustment, payment, ai, api, etc.',
  `action` VARCHAR(100) NOT NULL COMMENT 'created, updated, approved, etc.',

  -- Context
  `entity_type` VARCHAR(50) NULL,
  `entity_id` INT UNSIGNED NULL,
  `user_id` INT UNSIGNED NULL,
  `staff_id` INT UNSIGNED NULL,

  -- Message
  `message` TEXT NOT NULL,
  `details` JSON NULL COMMENT 'Additional structured data',

  -- Request context
  `request_id` VARCHAR(50) NULL COMMENT 'Trace request through system',
  `session_id` VARCHAR(50) NULL,
  `ip_address` VARCHAR(45) NULL,
  `user_agent` TEXT NULL,
  `url` VARCHAR(500) NULL,
  `http_method` VARCHAR(10) NULL,

  -- Performance
  `execution_time_ms` INT UNSIGNED NULL,
  `memory_usage_mb` DECIMAL(10,2) NULL,

  -- Errors
  `exception_class` VARCHAR(255) NULL,
  `exception_message` TEXT NULL,
  `stack_trace` TEXT NULL,

  -- Audit
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at_microseconds` INT UNSIGNED NULL COMMENT 'For high-precision timing',
  PRIMARY KEY (`id`),
  KEY `idx_log_level` (`log_level`),
  KEY `idx_category_action` (`category`, `action`),
  KEY `idx_entity` (`entity_type`, `entity_id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_staff` (`staff_id`),
  KEY `idx_request` (`request_id`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Log retention partitioning (monthly)
-- ALTER TABLE payroll_activity_log
-- PARTITION BY RANGE (TO_DAYS(created_at)) (
--   PARTITION p_current VALUES LESS THAN (TO_DAYS(CURRENT_DATE)),
--   PARTITION p_future VALUES LESS THAN MAXVALUE
-- );

-- ============================================================================
-- 8. AUTOMATION RULES (AI Configuration)
-- ============================================================================

-- AI automation rules (configurable thresholds)
CREATE TABLE IF NOT EXISTS `payroll_ai_rules` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `rule_name` VARCHAR(100) NOT NULL UNIQUE,
  `rule_type` ENUM('timesheet', 'payrun', 'vend', 'bank', 'fraud', 'anomaly') NOT NULL,
  `description` TEXT NULL,

  -- Conditions
  `conditions_json` JSON NOT NULL COMMENT 'Rule logic',
  `threshold_value` DECIMAL(10,2) NULL,
  `threshold_type` VARCHAR(50) NULL COMMENT 'amount, percentage, count, etc.',

  -- Actions
  `auto_approve` TINYINT(1) DEFAULT 0,
  `auto_decline` TINYINT(1) DEFAULT 0,
  `require_escalation` TINYINT(1) DEFAULT 0,
  `require_human_review` TINYINT(1) DEFAULT 0,
  `notification_emails` JSON NULL,

  -- Risk scoring
  `risk_weight` DECIMAL(5,4) DEFAULT 1.0000 COMMENT 'Multiplier for risk score',
  `confidence_required` DECIMAL(5,4) DEFAULT 0.8000 COMMENT 'Min confidence to auto-approve',

  -- Status
  `is_active` TINYINT(1) DEFAULT 1,
  `priority` INT UNSIGNED DEFAULT 100 COMMENT 'Higher = evaluated first',

  -- Audit
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  `created_by` INT UNSIGNED NULL,
  `last_triggered_at` DATETIME NULL,
  `trigger_count` INT UNSIGNED DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_rule_name` (`rule_name`),
  KEY `idx_rule_type` (`rule_type`),
  KEY `idx_active_priority` (`is_active`, `priority`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Rule execution log
CREATE TABLE IF NOT EXISTS `payroll_ai_rule_executions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `rule_id` INT UNSIGNED NOT NULL,
  `entity_type` VARCHAR(50) NOT NULL,
  `entity_id` INT UNSIGNED NOT NULL,
  `matched` TINYINT(1) NOT NULL COMMENT 'Did rule conditions match?',
  `action_taken` VARCHAR(100) NULL,
  `execution_time_ms` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_rule` (`rule_id`),
  KEY `idx_entity` (`entity_type`, `entity_id`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `fk_rule_execution_rule`
    FOREIGN KEY (`rule_id`)
    REFERENCES `payroll_ai_rules` (`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 9. NOTIFICATION QUEUE (For Staff + Admins)
-- ============================================================================

-- Payroll notifications (email, SMS, push)
CREATE TABLE IF NOT EXISTS `payroll_notifications` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `notification_type` VARCHAR(50) NOT NULL COMMENT 'amendment_approved, payment_sent, etc.',
  `recipient_type` ENUM('staff', 'admin', 'ai_admin', 'system') NOT NULL,
  `recipient_id` INT UNSIGNED NULL,
  `recipient_email` VARCHAR(255) NULL,
  `recipient_phone` VARCHAR(20) NULL,

  -- Message
  `subject` VARCHAR(255) NOT NULL,
  `message_body` TEXT NOT NULL,
  `message_html` TEXT NULL,
  `metadata` JSON NULL COMMENT 'Links, buttons, data',

  -- Channels
  `send_email` TINYINT(1) DEFAULT 1,
  `send_sms` TINYINT(1) DEFAULT 0,
  `send_push` TINYINT(1) DEFAULT 0,
  `send_slack` TINYINT(1) DEFAULT 0,

  -- Status
  `status` ENUM('pending', 'sent', 'failed', 'bounced', 'read') NOT NULL DEFAULT 'pending',
  `sent_at` DATETIME NULL,
  `read_at` DATETIME NULL,
  `error_message` TEXT NULL,

  -- Priority
  `priority` TINYINT UNSIGNED DEFAULT 5 COMMENT '1=urgent, 5=normal, 10=low',
  `scheduled_for` DATETIME NULL COMMENT 'Delayed sending',

  -- Audit
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `attempts` TINYINT UNSIGNED DEFAULT 0,
  `last_attempt_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `idx_recipient` (`recipient_type`, `recipient_id`),
  KEY `idx_status` (`status`),
  KEY `idx_scheduled` (`scheduled_for`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 10. PERFORMANCE METRICS & ANALYTICS
-- ============================================================================

-- Payroll process metrics (track automation performance)
CREATE TABLE IF NOT EXISTS `payroll_process_metrics` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `metric_date` DATE NOT NULL,
  `payroll_run_id` VARCHAR(50) NULL,

  -- Volumes
  `total_staff_count` INT UNSIGNED NOT NULL,
  `timesheet_amendments_count` INT UNSIGNED DEFAULT 0,
  `payrun_adjustments_count` INT UNSIGNED DEFAULT 0,
  `vend_payments_count` INT UNSIGNED DEFAULT 0,
  `bank_payments_count` INT UNSIGNED DEFAULT 0,

  -- AI performance
  `ai_decisions_count` INT UNSIGNED DEFAULT 0,
  `ai_auto_approved_count` INT UNSIGNED DEFAULT 0,
  `ai_declined_count` INT UNSIGNED DEFAULT 0,
  `ai_escalated_count` INT UNSIGNED DEFAULT 0,
  `ai_average_confidence` DECIMAL(5,4) NULL,
  `ai_override_count` INT UNSIGNED DEFAULT 0 COMMENT 'Human overrides',

  -- Processing times
  `avg_amendment_process_time_seconds` INT UNSIGNED NULL,
  `avg_payment_process_time_seconds` INT UNSIGNED NULL,
  `total_processing_time_seconds` INT UNSIGNED NULL,

  -- Financial
  `total_gross_pay` DECIMAL(12,2) NULL,
  `total_tax_withheld` DECIMAL(12,2) NULL,
  `total_net_pay` DECIMAL(12,2) NULL,
  `total_vend_allocated` DECIMAL(12,2) NULL,

  -- Quality
  `error_count` INT UNSIGNED DEFAULT 0,
  `manual_intervention_count` INT UNSIGNED DEFAULT 0,
  `sla_met_percentage` DECIMAL(5,2) NULL COMMENT 'SLA compliance',

  -- Created
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_metric_date_run` (`metric_date`, `payroll_run_id`),
  KEY `idx_metric_date` (`metric_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 11. DEFAULT AI RULES (Seed Data)
-- ============================================================================

-- Insert default automation rules
INSERT INTO `payroll_ai_rules`
  (`rule_name`, `rule_type`, `description`, `conditions_json`, `threshold_value`, `threshold_type`,
   `auto_approve`, `confidence_required`, `risk_weight`, `is_active`, `priority`)
VALUES
  -- Timesheet amendments
  ('Small Time Adjustment Auto-Approve', 'timesheet', 'Auto-approve amendments under 15 minutes with high confidence',
   '{"max_time_difference_minutes": 15, "requires_evidence": false}', 15.00, 'minutes',
   1, 0.9000, 0.1000, 1, 100),

  ('Break Time Adjustment', 'timesheet', 'Auto-approve break time corrections with evidence',
   '{"has_evidence": true, "break_time_only": true}', NULL, NULL,
   1, 0.8500, 0.2000, 1, 90),

  ('Large Time Amendment Escalate', 'timesheet', 'Escalate amendments over 2 hours',
   '{"min_time_difference_hours": 2}', 2.00, 'hours',
   0, NULL, 1.0000, 1, 200),

  -- Pay run adjustments
  ('Small Amount Adjustment', 'payrun', 'Auto-approve adjustments under $50 with evidence',
   '{"max_amount": 50, "has_evidence": true}', 50.00, 'amount',
   1, 0.8500, 0.3000, 1, 100),

  ('Large Pay Adjustment Require Review', 'payrun', 'Require human review for adjustments over $500',
   '{"min_amount": 500}', 500.00, 'amount',
   0, NULL, 2.0000, 1, 200),

  -- Vend payments
  ('Standard Vend Payment Auto-Approve', 'vend', 'Auto-approve standard Vend allocations',
   '{"valid_account_balance": true, "no_anomalies": true}', NULL, NULL,
   1, 0.9000, 0.1000, 1, 100),

  -- Bank payments
  ('Bank Payment Require Approval', 'bank', 'All bank payments require explicit approval',
   '{}', NULL, NULL,
   0, NULL, 1.0000, 1, 300),

  -- Fraud detection
  ('Duplicate Amendment Detection', 'fraud', 'Flag potential duplicate submissions',
   '{"duplicate_window_hours": 24}', 24.00, 'hours',
   0, NULL, 3.0000, 1, 500),

  ('Unusual Pattern Detection', 'anomaly', 'Flag unusual submission patterns',
   '{"deviation_threshold": 2.5}', 2.50, 'standard_deviations',
   0, NULL, 2.0000, 1, 400);

-- ============================================================================
-- SCHEMA COMPLETE
-- ============================================================================

-- ============================================================================
-- ULTRA-PERFORMANCE INDEXES (Production Optimized)
-- ============================================================================

-- Payroll activity log - Composite indexes for common queries
CREATE INDEX IF NOT EXISTS idx_payroll_activity_log_performance ON payroll_activity_log (created_at, log_level, category);
CREATE INDEX IF NOT EXISTS idx_payroll_activity_log_entity_lookup ON payroll_activity_log (entity_type, entity_id, created_at);
CREATE INDEX IF NOT EXISTS idx_payroll_activity_log_user_actions ON payroll_activity_log (user_id, category, created_at);
CREATE INDEX IF NOT EXISTS idx_payroll_activity_log_request_trace ON payroll_activity_log (request_id, created_at);
CREATE INDEX IF NOT EXISTS idx_payroll_activity_log_error_tracking ON payroll_activity_log (log_level, created_at) WHERE log_level IN ('error', 'critical', 'alert', 'emergency');

-- AI decisions - Covering indexes for dashboard queries
CREATE INDEX IF NOT EXISTS idx_ai_decisions_performance ON payroll_ai_decisions (created_at, decision_type, decision);
CREATE INDEX IF NOT EXISTS idx_ai_decisions_entity_lookup ON payroll_ai_decisions (entity_type, entity_id, created_at);
CREATE INDEX IF NOT EXISTS idx_ai_decisions_model_stats ON payroll_ai_decisions (model_name, decision, confidence_score);
CREATE INDEX IF NOT EXISTS idx_ai_decisions_override_analysis ON payroll_ai_decisions (overridden, outcome, created_at);
CREATE INDEX IF NOT EXISTS idx_ai_decisions_cost_tracking ON payroll_ai_decisions (created_at, api_cost);

-- Timesheet amendments - Hot path optimization
CREATE INDEX IF NOT EXISTS idx_timesheet_amendments_hot_pending ON payroll_timesheet_amendments (status, ai_reviewed, created_at) WHERE deleted_at IS NULL;
CREATE INDEX IF NOT EXISTS idx_timesheet_amendments_staff_recent ON payroll_timesheet_amendments (staff_id, created_at DESC) WHERE deleted_at IS NULL;
CREATE INDEX IF NOT EXISTS idx_timesheet_amendments_ai_queue ON payroll_timesheet_amendments (ai_reviewed, status, created_at) WHERE deleted_at IS NULL AND status = 4;
CREATE INDEX IF NOT EXISTS idx_timesheet_amendments_deputy_sync ON payroll_timesheet_amendments (synced_to_deputy, created_at) WHERE deleted_at IS NULL AND synced_to_deputy = 0;

-- Pay run adjustments - Composite covering indexes
CREATE INDEX IF NOT EXISTS idx_payrun_adjustments_pending_review ON payroll_payrun_line_adjustments (status, ai_reviewed, created_at);
CREATE INDEX IF NOT EXISTS idx_payrun_adjustments_run_staff ON payroll_payrun_line_adjustments (payroll_run_id, staff_id, status);
CREATE INDEX IF NOT EXISTS idx_payrun_adjustments_financial_impact ON payroll_payrun_line_adjustments (created_at, net_impact, status);

-- Vend payments - FIFO allocation optimization
CREATE INDEX IF NOT EXISTS idx_vend_payments_allocation_queue ON payroll_vend_payment_requests (status, created_at) WHERE status IN ('pending', 'ai_review', 'approved');
CREATE INDEX IF NOT EXISTS idx_vend_payments_staff_outstanding ON payroll_vend_payment_requests (staff_id, status, created_at);
CREATE INDEX IF NOT EXISTS idx_vend_allocations_sale_lookup ON payroll_vend_payment_allocations (vend_sale_id, success, attempted_at);
CREATE INDEX IF NOT EXISTS idx_vend_allocations_failed_retry ON payroll_vend_payment_allocations (success, retry_count, attempted_at) WHERE success = 0;

-- Bank payments - Batch processing optimization
CREATE INDEX IF NOT EXISTS idx_bank_batches_processing_queue ON payroll_bank_payment_batches (status, payment_date, created_at);
CREATE INDEX IF NOT EXISTS idx_bank_batches_reconciliation ON payroll_bank_payment_batches (reconciled, payment_date) WHERE reconciled = 0;
CREATE INDEX IF NOT EXISTS idx_bank_payments_batch_status ON payroll_bank_payments (batch_id, status);
CREATE INDEX IF NOT EXISTS idx_bank_payments_staff_tracking ON payroll_bank_payments (staff_id, status, created_at);

-- Context snapshots - Deduplication and lookup
CREATE INDEX IF NOT EXISTS idx_context_snapshots_entity_latest ON payroll_context_snapshots (context_type, entity_id, created_at DESC);
CREATE INDEX IF NOT EXISTS idx_context_snapshots_quality_score ON payroll_context_snapshots (data_quality_score, created_at) WHERE data_quality_score IS NOT NULL;

-- AI rules - Execution path optimization
CREATE INDEX IF NOT EXISTS idx_ai_rules_active_priority ON payroll_ai_rules (is_active, priority, rule_type) WHERE is_active = 1;
CREATE INDEX IF NOT EXISTS idx_ai_rule_executions_entity ON payroll_ai_rule_executions (entity_type, entity_id, created_at);
CREATE INDEX IF NOT EXISTS idx_ai_rule_executions_performance ON payroll_ai_rule_executions (rule_id, matched, execution_time_ms);

-- Notifications - Queue processing optimization
CREATE INDEX IF NOT EXISTS idx_notifications_send_queue ON payroll_notifications (status, priority, scheduled_for) WHERE status = 'pending';
CREATE INDEX IF NOT EXISTS idx_notifications_recipient_recent ON payroll_notifications (recipient_type, recipient_id, created_at DESC);
CREATE INDEX IF NOT EXISTS idx_notifications_failed_retry ON payroll_notifications (status, attempts, last_attempt_at) WHERE status = 'failed' AND attempts < 3;

-- Metrics - Reporting optimization
CREATE INDEX IF NOT EXISTS idx_process_metrics_date_range ON payroll_process_metrics (metric_date DESC, payroll_run_id);
CREATE INDEX IF NOT EXISTS idx_process_metrics_ai_performance ON payroll_process_metrics (metric_date, ai_average_confidence, ai_auto_approved_count);

-- ============================================================================
-- HIGH-PERFORMANCE MATERIALIZED VIEWS & REPORTING OPTIMIZATION
-- ============================================================================

-- Pending AI reviews - Optimized with index hints
CREATE OR REPLACE VIEW `v_pending_ai_reviews` AS
SELECT
  'timesheet_amendment' as review_type,
  ta.id as entity_id,
  ta.staff_id,
  ta.created_at,
  ta.reason,
  ta.status,
  ta.ai_confidence_score,
  ta.claimed_start_time,
  ta.claimed_end_time,
  TIMESTAMPDIFF(MINUTE, ta.claimed_start_time, ta.claimed_end_time) as claimed_hours_minutes
FROM payroll_timesheet_amendments ta USE INDEX (idx_timesheet_amendments_ai_queue)
WHERE ta.status = 4 -- ai_review
AND ta.deleted_at IS NULL
AND ta.ai_reviewed = 0

UNION ALL

SELECT
  'payrun_adjustment' as review_type,
  pa.id as entity_id,
  pa.staff_id,
  pa.created_at,
  pa.reason,
  pa.status,
  pa.ai_confidence_score,
  NULL as claimed_start_time,
  NULL as claimed_end_time,
  NULL as claimed_hours_minutes
FROM payroll_payrun_line_adjustments pa USE INDEX (idx_payrun_adjustments_pending_review)
WHERE pa.status = 'ai_review'
AND pa.ai_reviewed = 0
ORDER BY created_at ASC; -- FIFO processing

-- Real-time automation dashboard - 30-day rolling window
CREATE OR REPLACE VIEW `v_payroll_automation_dashboard` AS
SELECT
  DATE(created_at) as metric_date,
  COUNT(*) as total_decisions,
  SUM(CASE WHEN decision = 'approve' THEN 1 ELSE 0 END) as auto_approved,
  SUM(CASE WHEN decision = 'decline' THEN 1 ELSE 0 END) as auto_declined,
  SUM(CASE WHEN decision = 'escalate' THEN 1 ELSE 0 END) as escalated,
  SUM(CASE WHEN decision = 'needs_info' THEN 1 ELSE 0 END) as needs_info,
  AVG(confidence_score) as avg_confidence,
  MIN(confidence_score) as min_confidence,
  MAX(confidence_score) as max_confidence,
  AVG(processing_time_ms) as avg_processing_time_ms,
  MAX(processing_time_ms) as max_processing_time_ms,
  SUM(api_cost) as total_api_cost,
  SUM(CASE WHEN overridden = 1 THEN 1 ELSE 0 END) as override_count,
  SUM(CASE WHEN outcome = 'correct' THEN 1 ELSE 0 END) as correct_decisions,
  SUM(CASE WHEN outcome = 'incorrect' THEN 1 ELSE 0 END) as incorrect_decisions,
  ROUND(100.0 * SUM(CASE WHEN outcome = 'correct' THEN 1 ELSE 0 END) /
    NULLIF(SUM(CASE WHEN outcome IS NOT NULL THEN 1 ELSE 0 END), 0), 2) as accuracy_percentage
FROM payroll_ai_decisions USE INDEX (idx_ai_decisions_performance)
WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
GROUP BY DATE(created_at)
ORDER BY metric_date DESC;

-- Staff amendment patterns - Identify frequent submitters
CREATE OR REPLACE VIEW `v_staff_amendment_patterns` AS
SELECT
  staff_id,
  COUNT(*) as total_amendments,
  SUM(CASE WHEN status IN (1, 5) THEN 1 ELSE 0 END) as approved_count,
  SUM(CASE WHEN status = 2 THEN 1 ELSE 0 END) as declined_count,
  SUM(CASE WHEN status = 0 THEN 1 ELSE 0 END) as pending_count,
  AVG(CASE WHEN ai_confidence_score IS NOT NULL THEN ai_confidence_score ELSE NULL END) as avg_ai_confidence,
  MAX(created_at) as last_amendment_date,
  DATEDIFF(NOW(), MAX(created_at)) as days_since_last_amendment,
  AVG(TIMESTAMPDIFF(MINUTE, claimed_start_time, claimed_end_time)) as avg_hours_claimed
FROM payroll_timesheet_amendments
WHERE deleted_at IS NULL
AND created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)
GROUP BY staff_id
HAVING total_amendments > 0
ORDER BY total_amendments DESC;

-- Vend payment allocation queue - Real-time processing status
CREATE OR REPLACE VIEW `v_vend_payment_queue` AS
SELECT
  vpr.id,
  vpr.payroll_run_id,
  vpr.staff_id,
  vpr.account_balance,
  vpr.payment_amount,
  vpr.status,
  vpr.created_at,
  vpr.ai_confidence_score,
  vpr.processed_sales_count,
  vpr.failed_sales_count,
  vpr.total_allocated,
  TIMESTAMPDIFF(MINUTE, vpr.created_at, NOW()) as age_minutes,
  COUNT(vpa.id) as allocation_attempts,
  SUM(CASE WHEN vpa.success = 1 THEN 1 ELSE 0 END) as successful_allocations,
  SUM(CASE WHEN vpa.success = 0 THEN 1 ELSE 0 END) as failed_allocations
FROM payroll_vend_payment_requests vpr USE INDEX (idx_vend_payments_allocation_queue)
LEFT JOIN payroll_vend_payment_allocations vpa ON vpr.id = vpa.payment_request_id
WHERE vpr.status IN ('pending', 'ai_review', 'approved', 'processing')
GROUP BY vpr.id
ORDER BY
  CASE vpr.status
    WHEN 'processing' THEN 1
    WHEN 'approved' THEN 2
    WHEN 'ai_review' THEN 3
    WHEN 'pending' THEN 4
  END,
  vpr.created_at ASC;

-- Bank payment batch summary - Quick reconciliation view
CREATE OR REPLACE VIEW `v_bank_payment_batch_summary` AS
SELECT
  bpb.id,
  bpb.batch_reference,
  bpb.payroll_run_id,
  bpb.payment_date,
  bpb.total_amount,
  bpb.payment_count,
  bpb.status,
  bpb.ai_confidence_score,
  bpb.requires_human_approval,
  bpb.reconciled,
  COUNT(bp.id) as individual_payment_count,
  SUM(CASE WHEN bp.status = 'completed' THEN 1 ELSE 0 END) as completed_payments,
  SUM(CASE WHEN bp.status = 'failed' THEN 1 ELSE 0 END) as failed_payments,
  SUM(bp.payment_amount) as calculated_total,
  ABS(bpb.total_amount - SUM(bp.payment_amount)) as reconciliation_difference,
  DATEDIFF(bpb.payment_date, CURDATE()) as days_until_payment
FROM payroll_bank_payment_batches bpb
LEFT JOIN payroll_bank_payments bp ON bpb.id = bp.batch_id
GROUP BY bpb.id
ORDER BY bpb.payment_date DESC, bpb.created_at DESC;

-- AI rule effectiveness - Performance tracking
CREATE OR REPLACE VIEW `v_ai_rule_effectiveness` AS
SELECT
  r.id as rule_id,
  r.rule_name,
  r.rule_type,
  r.is_active,
  r.priority,
  r.confidence_required,
  r.trigger_count as total_triggers,
  COUNT(re.id) as execution_count,
  SUM(CASE WHEN re.matched = 1 THEN 1 ELSE 0 END) as match_count,
  ROUND(100.0 * SUM(CASE WHEN re.matched = 1 THEN 1 ELSE 0 END) / NULLIF(COUNT(re.id), 0), 2) as match_rate_percentage,
  AVG(re.execution_time_ms) as avg_execution_time_ms,
  MAX(re.execution_time_ms) as max_execution_time_ms,
  MAX(re.created_at) as last_execution,
  DATEDIFF(NOW(), MAX(re.created_at)) as days_since_last_execution
FROM payroll_ai_rules r
LEFT JOIN payroll_ai_rule_executions re ON r.id = re.rule_id
  AND re.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
GROUP BY r.id
ORDER BY r.is_active DESC, r.priority DESC, match_count DESC;

-- Notification delivery tracking - SLA monitoring
CREATE OR REPLACE VIEW `v_notification_delivery_stats` AS
SELECT
  DATE(created_at) as notification_date,
  notification_type,
  recipient_type,
  COUNT(*) as total_notifications,
  SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent_count,
  SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_count,
  SUM(CASE WHEN status = 'bounced' THEN 1 ELSE 0 END) as bounced_count,
  SUM(CASE WHEN status = 'read' THEN 1 ELSE 0 END) as read_count,
  AVG(CASE WHEN sent_at IS NOT NULL
    THEN TIMESTAMPDIFF(SECOND, created_at, sent_at) ELSE NULL END) as avg_send_delay_seconds,
  ROUND(100.0 * SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) / COUNT(*), 2) as delivery_rate_percentage
FROM payroll_notifications
WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
GROUP BY DATE(created_at), notification_type, recipient_type
ORDER BY notification_date DESC, total_notifications DESC;

-- ============================================================================
-- ULTRA-PERFORMANCE TABLE OPTIMIZATIONS
-- ============================================================================

-- Table optimization hints for InnoDB
ALTER TABLE payroll_timesheet_amendments
  ENGINE=InnoDB
  ROW_FORMAT=COMPRESSED
  KEY_BLOCK_SIZE=8
  COMMENT='Hot table - frequent inserts/updates';

ALTER TABLE payroll_ai_decisions
  ENGINE=InnoDB
  ROW_FORMAT=COMPRESSED
  KEY_BLOCK_SIZE=8
  COMMENT='High-volume logging - compress to save space';

ALTER TABLE payroll_activity_log
  ENGINE=InnoDB
  ROW_FORMAT=COMPRESSED
  KEY_BLOCK_SIZE=8
  COMMENT='High-volume logging - compress to save space';

-- Optimize frequently-accessed tables for in-memory caching
ALTER TABLE payroll_ai_rules ENGINE=InnoDB ROW_FORMAT=COMPACT;
ALTER TABLE payroll_vend_payment_requests ENGINE=InnoDB ROW_FORMAT=DYNAMIC;
ALTER TABLE payroll_bank_payment_batches ENGINE=InnoDB ROW_FORMAT=DYNAMIC;

-- ============================================================================
-- QUERY CACHE & PERFORMANCE TUNING RECOMMENDATIONS
-- ============================================================================

/*
PRODUCTION MySQL/MariaDB CONFIGURATION RECOMMENDATIONS:

1. InnoDB Buffer Pool (CRITICAL - Set to 70-80% of available RAM)
   innodb_buffer_pool_size = 4G   # Adjust based on your server
   innodb_buffer_pool_instances = 4

2. Query Cache (if using MySQL 5.7 or MariaDB)
   query_cache_type = 1
   query_cache_size = 256M
   query_cache_limit = 2M

3. Connection Pooling
   max_connections = 200
   thread_cache_size = 50

4. InnoDB Performance
   innodb_log_file_size = 256M
   innodb_flush_log_at_trx_commit = 2  # Better performance, slightly less durable
   innodb_flush_method = O_DIRECT
   innodb_io_capacity = 2000
   innodb_io_capacity_max = 4000

5. Table Statistics (for query optimization)
   innodb_stats_on_metadata = 0
   innodb_stats_persistent = 1

6. Parallel Query Execution (MariaDB 10.5+)
   innodb_parallel_read_threads = 4

7. Temp Tables
   tmp_table_size = 256M
   max_heap_table_size = 256M

APPLY AFTER SCHEMA DEPLOYMENT:
ANALYZE TABLE payroll_timesheet_amendments;
ANALYZE TABLE payroll_ai_decisions;
ANALYZE TABLE payroll_activity_log;
ANALYZE TABLE payroll_vend_payment_requests;
ANALYZE TABLE payroll_bank_payment_batches;
OPTIMIZE TABLE payroll_timesheet_amendments;
OPTIMIZE TABLE payroll_ai_decisions;
*/

-- ============================================================================
-- SCHEDULED MAINTENANCE PROCEDURES
-- ============================================================================

DELIMITER $$

-- Procedure: Archive old activity logs (keep 90 days, archive rest)
CREATE PROCEDURE IF NOT EXISTS sp_archive_old_activity_logs()
BEGIN
  DECLARE archived_count INT DEFAULT 0;

  -- Create archive table if not exists
  CREATE TABLE IF NOT EXISTS payroll_activity_log_archive LIKE payroll_activity_log;

  -- Move records older than 90 days to archive
  INSERT INTO payroll_activity_log_archive
  SELECT * FROM payroll_activity_log
  WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY);

  SET archived_count = ROW_COUNT();

  -- Delete archived records from main table
  DELETE FROM payroll_activity_log
  WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY);

  -- Log the archival
  INSERT INTO payroll_activity_log
    (log_level, category, action, message, details)
  VALUES
    ('info', 'maintenance', 'archive_logs',
     CONCAT('Archived ', archived_count, ' old activity log records'),
     JSON_OBJECT('archived_count', archived_count, 'cutoff_date', DATE_SUB(NOW(), INTERVAL 90 DAY)));
END$$

-- Procedure: Purge completed notifications (keep 30 days)
CREATE PROCEDURE IF NOT EXISTS sp_purge_old_notifications()
BEGIN
  DECLARE deleted_count INT DEFAULT 0;

  DELETE FROM payroll_notifications
  WHERE status IN ('sent', 'read')
  AND created_at < DATE_SUB(NOW(), INTERVAL 30 DAY);

  SET deleted_count = ROW_COUNT();

  -- Log the purge
  INSERT INTO payroll_activity_log
    (log_level, category, action, message, details)
  VALUES
    ('info', 'maintenance', 'purge_notifications',
     CONCAT('Purged ', deleted_count, ' old notification records'),
     JSON_OBJECT('deleted_count', deleted_count, 'cutoff_date', DATE_SUB(NOW(), INTERVAL 30 DAY)));
END$$

-- Procedure: Update table statistics for query optimizer
CREATE PROCEDURE IF NOT EXISTS sp_update_table_statistics()
BEGIN
  -- Analyze all payroll tables for optimal query plans
  ANALYZE TABLE payroll_timesheet_amendments;
  ANALYZE TABLE payroll_timesheet_amendment_history;
  ANALYZE TABLE payroll_payrun_line_adjustments;
  ANALYZE TABLE payroll_payrun_adjustment_history;
  ANALYZE TABLE payroll_vend_payment_requests;
  ANALYZE TABLE payroll_vend_payment_allocations;
  ANALYZE TABLE payroll_bank_payment_batches;
  ANALYZE TABLE payroll_bank_payments;
  ANALYZE TABLE payroll_ai_decisions;
  ANALYZE TABLE payroll_ai_feedback;
  ANALYZE TABLE payroll_context_snapshots;
  ANALYZE TABLE payroll_activity_log;
  ANALYZE TABLE payroll_ai_rules;
  ANALYZE TABLE payroll_ai_rule_executions;
  ANALYZE TABLE payroll_notifications;
  ANALYZE TABLE payroll_process_metrics;

  -- Log the update
  INSERT INTO payroll_activity_log
    (log_level, category, action, message)
  VALUES
    ('info', 'maintenance', 'update_statistics', 'Updated table statistics for query optimizer');
END$$

-- Procedure: Health check and performance report
CREATE PROCEDURE IF NOT EXISTS sp_payroll_health_check()
BEGIN
  SELECT
    'Payroll System Health Check' as report_type,
    NOW() as check_time,
    (SELECT COUNT(*) FROM payroll_timesheet_amendments WHERE status = 0 AND deleted_at IS NULL) as pending_amendments,
    (SELECT COUNT(*) FROM payroll_timesheet_amendments WHERE status = 4 AND deleted_at IS NULL) as ai_review_queue,
    (SELECT COUNT(*) FROM payroll_vend_payment_requests WHERE status IN ('pending', 'approved')) as vend_payment_queue,
    (SELECT COUNT(*) FROM payroll_bank_payment_batches WHERE status IN ('pending', 'approved')) as bank_batch_queue,
    (SELECT COUNT(*) FROM payroll_notifications WHERE status = 'pending') as notification_queue,
    (SELECT AVG(confidence_score) FROM payroll_ai_decisions WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)) as ai_avg_confidence_24h,
    (SELECT COUNT(*) FROM payroll_activity_log WHERE log_level IN ('error', 'critical') AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)) as recent_errors,
    (SELECT TABLE_ROWS FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payroll_activity_log') as activity_log_rows,
    (SELECT ROUND(DATA_LENGTH/1024/1024, 2) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payroll_activity_log') as activity_log_size_mb;
END$$

DELIMITER ;

-- ============================================================================
-- CRON JOB SCHEDULE RECOMMENDATIONS
-- ============================================================================

/*
ADD TO CRONTAB (adjust times for your timezone):

# Archive old activity logs - Daily at 2 AM
0 2 * * * mysql -u[USER] -p[PASS] [DB] -e "CALL sp_archive_old_activity_logs();"

# Purge old notifications - Daily at 3 AM
0 3 * * * mysql -u[USER] -p[PASS] [DB] -e "CALL sp_purge_old_notifications();"

# Update table statistics - Weekly on Sunday at 4 AM
0 4 * * 0 mysql -u[USER] -p[PASS] [DB] -e "CALL sp_update_table_statistics();"

# Health check - Every 15 minutes
*/15 * * * * mysql -u[USER] -p[PASS] [DB] -e "CALL sp_payroll_health_check();" >> /var/log/payroll_health.log 2>&1

# Optimize tables - Monthly on 1st at 5 AM
0 5 1 * * mysql -u[USER] -p[PASS] [DB] -e "OPTIMIZE TABLE payroll_timesheet_amendments, payroll_ai_decisions, payroll_activity_log;"
*/

-- ============================================================================
-- PERFORMANCE MONITORING QUERIES (Save for DBA troubleshooting)
-- ============================================================================

/*
-- Check slow queries on payroll tables
SELECT * FROM mysql.slow_log
WHERE sql_text LIKE '%payroll_%'
ORDER BY query_time DESC LIMIT 20;

-- Check table sizes
SELECT
  TABLE_NAME,
  TABLE_ROWS,
  ROUND(DATA_LENGTH/1024/1024, 2) as data_size_mb,
  ROUND(INDEX_LENGTH/1024/1024, 2) as index_size_mb,
  ROUND((DATA_LENGTH + INDEX_LENGTH)/1024/1024, 2) as total_size_mb
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
AND TABLE_NAME LIKE 'payroll_%'
ORDER BY (DATA_LENGTH + INDEX_LENGTH) DESC;

-- Check index usage
SELECT
  TABLE_NAME,
  INDEX_NAME,
  CARDINALITY,
  INDEX_TYPE
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = DATABASE()
AND TABLE_NAME LIKE 'payroll_%'
ORDER BY TABLE_NAME, SEQ_IN_INDEX;

-- Check for missing indexes (look for full table scans)
EXPLAIN SELECT * FROM payroll_timesheet_amendments WHERE staff_id = 123;
EXPLAIN SELECT * FROM payroll_ai_decisions WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY);
*/

-- ============================================================================
-- NEW ZEALAND PAYROLL COMPLIANCE TABLES
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
-- NZ-SPECIFIC VIEWS
-- ============================================================================

-- View: Upcoming public holidays
CREATE OR REPLACE VIEW `v_nz_upcoming_public_holidays` AS
SELECT
  holiday_date,
  holiday_name,
  holiday_type,
  region,
  is_mondayised,
  DATEDIFF(holiday_date, CURDATE()) as days_until_holiday
FROM payroll_nz_public_holidays
WHERE holiday_date >= CURDATE()
  AND holiday_date <= DATE_ADD(CURDATE(), INTERVAL 90 DAY)
ORDER BY holiday_date ASC;

-- View: Alternative holidays balance per staff
CREATE OR REPLACE VIEW `v_nz_alternative_holidays_balance` AS
SELECT
  staff_id,
  COUNT(*) as total_alternative_holidays,
  SUM(CASE WHEN taken_date IS NULL THEN days_earned ELSE 0 END) as untaken_days,
  SUM(CASE WHEN taken_date IS NOT NULL THEN days_earned ELSE 0 END) as taken_days,
  MAX(holiday_date) as most_recent_earned_date,
  MIN(CASE WHEN taken_date IS NULL THEN holiday_date ELSE NULL END) as oldest_untaken_date
FROM payroll_nz_alternative_holidays
WHERE status IN ('confirmed', 'applied_to_xero')
GROUP BY staff_id
HAVING untaken_days > 0
ORDER BY untaken_days DESC;

-- View: Leave balances summary
CREATE OR REPLACE VIEW `v_nz_leave_balances_summary` AS
SELECT
  staff_id,
  MAX(CASE WHEN leave_type = 'annual_leave' THEN balance_days ELSE 0 END) as annual_leave_days,
  MAX(CASE WHEN leave_type = 'sick_leave' THEN balance_days ELSE 0 END) as sick_leave_days,
  MAX(CASE WHEN leave_type = 'alternative_holiday' THEN balance_days ELSE 0 END) as alternative_holiday_days,
  MAX(CASE WHEN leave_type = 'bereavement_leave' THEN balance_days ELSE 0 END) as bereavement_leave_days,
  MAX(as_at_date) as as_at_date
FROM payroll_nz_leave_balances
GROUP BY staff_id;

-- View: KiwiSaver compliance check
CREATE OR REPLACE VIEW `v_nz_kiwisaver_compliance` AS
SELECT
  k.staff_id,
  k.is_enrolled,
  k.employee_contribution_rate,
  k.employer_contribution_rate,
  k.ytd_employee_contributions,
  k.ytd_employer_contributions,
  k.ytd_esct,
  CASE
    WHEN k.employee_contribution_rate < 0.03 THEN 'Below minimum'
    WHEN k.employer_contribution_rate < 0.03 THEN 'Employer below minimum'
    ELSE 'Compliant'
  END as compliance_status
FROM payroll_nz_kiwisaver k
WHERE k.is_enrolled = 1
  AND k.status = 'active';

-- ============================================================================
-- END OF ULTRA-PERFORMANCE SCHEMA
-- ============================================================================

/*
🚀 QUICK DEPLOYMENT COMMANDS:

Database Credentials:
  User: jcepnzzkmj
  Pass: XR4T8Pfs9k
  DB:   jcepnzzkmj

-- 1. Deploy the schema
mysql -u jcepnzzkmj -pXR4T8Pfs9k jcepnzzkmj < payroll_ai_automation_schema.sql

-- 2. Verify tables created (expect 25 tables)
mysql -u jcepnzzkmj -pXR4T8Pfs9k jcepnzzkmj -e "SELECT COUNT(*) as table_count FROM information_schema.TABLES WHERE TABLE_NAME LIKE 'payroll_%';"

-- 3. List all payroll tables
mysql -u jcepnzzkmj -pXR4T8Pfs9k jcepnzzkmj -e "SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_NAME LIKE 'payroll_%' ORDER BY TABLE_NAME;"

-- 4. Update table statistics (IMPORTANT for query optimization)
mysql -u jcepnzzkmj -pXR4T8Pfs9k jcepnzzkmj -e "CALL sp_update_table_statistics();"

-- 5. Run health check
mysql -u jcepnzzkmj -pXR4T8Pfs9k jcepnzzkmj -e "CALL sp_payroll_health_check();"

-- 6. Test core views
mysql -u jcepnzzkmj -pXR4T8Pfs9k jcepnzzkmj -e "SELECT * FROM v_pending_ai_reviews LIMIT 5;"
mysql -u jcepnzzkmj -pXR4T8Pfs9k jcepnzzkmj -e "SELECT * FROM v_payroll_automation_dashboard LIMIT 10;"

-- 7. Test NZ compliance views
mysql -u jcepnzzkmj -pXR4T8Pfs9k jcepnzzkmj -e "SELECT * FROM v_nz_upcoming_public_holidays LIMIT 10;"
mysql -u jcepnzzkmj -pXR4T8Pfs9k jcepnzzkmj -e "SELECT * FROM v_nz_leave_balances_summary LIMIT 10;"
mysql -u jcepnzzkmj -pXR4T8Pfs9k jcepnzzkmj -e "SELECT * FROM v_nz_kiwisaver_compliance LIMIT 10;"

-- 8. Verify stored procedures
mysql -u jcepnzzkmj -pXR4T8Pfs9k jcepnzzkmj -e "SHOW PROCEDURE STATUS WHERE Db = 'jcepnzzkmj' AND Name LIKE 'sp_%';"

-- 9. Check table sizes
mysql -u jcepnzzkmj -pXR4T8Pfs9k jcepnzzkmj -e "
  SELECT
    TABLE_NAME,
    TABLE_ROWS,
    ROUND(DATA_LENGTH/1024/1024, 2) as data_mb,
    ROUND(INDEX_LENGTH/1024/1024, 2) as index_mb,
    ROUND((DATA_LENGTH + INDEX_LENGTH)/1024/1024, 2) as total_mb
  FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = 'jcepnzzkmj'
  AND TABLE_NAME LIKE 'payroll_%'
  ORDER BY (DATA_LENGTH + INDEX_LENGTH) DESC;
"

-- 10. Verify indexes created
mysql -u jcepnzzkmj -pXR4T8Pfs9k jcepnzzkmj -e "
  SELECT
    TABLE_NAME,
    COUNT(*) as index_count
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = 'jcepnzzkmj'
  AND TABLE_NAME LIKE 'payroll_%'
  GROUP BY TABLE_NAME
  ORDER BY index_count DESC;
"

================================================================================

🚀 DEPLOYMENT CHECKLIST:

1. ✅ Run this schema on staging first
2. ✅ Verify all tables created: SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_NAME LIKE 'payroll_%';
3. ✅ Run ANALYZE TABLE on all payroll tables
4. ✅ Test views return data: SELECT * FROM v_pending_ai_reviews LIMIT 5;
5. ✅ Test stored procedures: CALL sp_payroll_health_check();
6. ✅ Configure MySQL/MariaDB per recommendations above
7. ✅ Set up cron jobs for maintenance
8. ✅ Monitor performance for first 24 hours
9. ✅ Adjust innodb_buffer_pool_size based on actual usage
10. ✅ Set up monitoring alerts for queue depths

PERFORMANCE TARGETS:
- Amendment submission → AI review: < 2 seconds
- AI decision generation: < 5 seconds
- Vend payment allocation: < 10 seconds per sale
- Bank batch generation: < 30 seconds for 100 employees
- Dashboard view load: < 500ms
- Activity log write: < 10ms

SCALABILITY:
- Designed for 100-500 employees
- Can handle 1000+ amendments per month
- Supports 10,000+ AI decisions per month
- Optimized for 100,000+ activity log entries per month
*/
