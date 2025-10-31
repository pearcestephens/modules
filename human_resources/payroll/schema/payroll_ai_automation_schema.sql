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

-- Indexes for performance
CREATE INDEX IF NOT EXISTS idx_payroll_activity_log_performance ON payroll_activity_log (created_at, log_level, category);
CREATE INDEX IF NOT EXISTS idx_ai_decisions_performance ON payroll_ai_decisions (created_at, decision_type, decision);

-- Views for common queries
CREATE OR REPLACE VIEW `v_pending_ai_reviews` AS
SELECT
  'timesheet_amendment' as review_type,
  ta.id as entity_id,
  ta.staff_id,
  ta.created_at,
  ta.reason,
  ta.status
FROM payroll_timesheet_amendments ta
WHERE ta.status = 4 -- ai_review
AND ta.deleted_at IS NULL

UNION ALL

SELECT
  'payrun_adjustment' as review_type,
  pa.id as entity_id,
  pa.staff_id,
  pa.created_at,
  pa.reason,
  pa.status
FROM payroll_payrun_line_adjustments pa
WHERE pa.status = 'ai_review';

-- Performance monitoring view
CREATE OR REPLACE VIEW `v_payroll_automation_dashboard` AS
SELECT
  DATE(created_at) as metric_date,
  COUNT(*) as total_decisions,
  SUM(CASE WHEN decision = 'approve' THEN 1 ELSE 0 END) as auto_approved,
  SUM(CASE WHEN decision = 'decline' THEN 1 ELSE 0 END) as auto_declined,
  SUM(CASE WHEN decision = 'escalate' THEN 1 ELSE 0 END) as escalated,
  AVG(confidence_score) as avg_confidence,
  AVG(processing_time_ms) as avg_processing_time_ms,
  SUM(api_cost) as total_api_cost
FROM payroll_ai_decisions
WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
GROUP BY DATE(created_at)
ORDER BY metric_date DESC;

-- ============================================================================
-- END OF SCHEMA
-- ============================================================================
