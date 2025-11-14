-- ============================================================================
-- WAGE DISCREPANCY SYSTEM - DATABASE SCHEMA
-- ============================================================================
-- Version: 1.0.0
-- Purpose: Staff self-service wage discrepancy reporting with AI analysis
-- Integration: Part of payroll module snapshot/amendment system
-- ============================================================================

-- ============================================================================
-- TABLE: payroll_wage_discrepancies
-- ============================================================================
-- Purpose: Stores wage discrepancy submissions with AI analysis results
-- ============================================================================

CREATE TABLE IF NOT EXISTS `payroll_wage_discrepancies` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,

  -- Staff Information
  `staff_id` INT UNSIGNED NOT NULL COMMENT 'FK to users.id',
  `payslip_id` INT UNSIGNED NOT NULL COMMENT 'FK to payroll_payslips.id',

  -- Discrepancy Details
  `discrepancy_type` ENUM(
    'underpaid_hours',
    'overpaid_hours',
    'missing_break_deduction',
    'incorrect_break_deduction',
    'missing_overtime',
    'incorrect_rate',
    'missing_bonus',
    'missing_reimbursement',
    'incorrect_deduction',
    'duplicate_payment',
    'missing_holiday_pay',
    'other'
  ) NOT NULL COMMENT 'Type of discrepancy',

  `description` TEXT NOT NULL COMMENT 'Staff description of the issue (min 20 chars)',

  -- Line Item Reference (optional)
  `line_item_type` ENUM('earnings', 'deduction', 'reimbursement') NULL COMMENT 'Type of line item affected',
  `line_item_id` INT UNSIGNED NULL COMMENT 'ID of specific line item (if applicable)',

  -- Claimed Values
  `claimed_hours` DECIMAL(10,2) NULL COMMENT 'Hours claimed by staff',
  `claimed_amount` DECIMAL(10,2) NULL COMMENT 'Dollar amount claimed by staff',

  -- Evidence
  `evidence_path` VARCHAR(500) NULL COMMENT 'Path to uploaded evidence file',
  `evidence_hash` CHAR(64) NULL COMMENT 'SHA256 hash of evidence file',
  `ocr_data` JSON NULL COMMENT 'OCR results from evidence file',

  -- AI Analysis Results
  `ai_analysis` JSON NOT NULL COMMENT 'Complete AI analysis: risk_score, confidence, anomalies, reasoning',
  `risk_score` DECIMAL(3,2) NOT NULL COMMENT '0.00-1.00: Risk score from AI analysis',
  `confidence` DECIMAL(3,2) NOT NULL COMMENT '0.00-1.00: AI confidence in analysis',
  `priority` ENUM('urgent', 'high', 'medium', 'low') NOT NULL COMMENT 'Calculated priority level',
  `estimated_resolution_time` VARCHAR(50) NOT NULL COMMENT 'Human-readable resolution estimate',

  -- Status Workflow
  `status` ENUM(
    'pending_review',
    'auto_approved',
    'approved',
    'declined'
  ) NOT NULL DEFAULT 'pending_review' COMMENT 'Current status',

  -- Approval/Decline Info
  `approved_by` INT UNSIGNED NULL COMMENT 'FK to users.id - admin who approved',
  `approved_at` DATETIME NULL COMMENT 'Approval timestamp',
  `declined_by` INT UNSIGNED NULL COMMENT 'FK to users.id - admin who declined',
  `declined_at` DATETIME NULL COMMENT 'Decline timestamp',
  `decline_reason` TEXT NULL COMMENT 'Reason for declining (required if declined)',
  `admin_notes` TEXT NULL COMMENT 'Internal admin notes',

  -- Amendment Link
  `amendment_id` INT UNSIGNED NULL COMMENT 'FK to payroll_amendments.id if approved',

  -- Timestamps
  `submitted_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'When discrepancy was submitted',
  `resolved_at` DATETIME NULL COMMENT 'When discrepancy was approved or declined',

  -- Indexes
  PRIMARY KEY (`id`),
  INDEX `idx_staff_id` (`staff_id`),
  INDEX `idx_payslip_id` (`payslip_id`),
  INDEX `idx_status` (`status`),
  INDEX `idx_priority` (`priority`),
  INDEX `idx_discrepancy_type` (`discrepancy_type`),
  INDEX `idx_submitted_at` (`submitted_at`),
  INDEX `idx_evidence_hash` (`evidence_hash`) COMMENT 'For duplicate detection',
  INDEX `idx_amendment_id` (`amendment_id`),

  -- Composite index for pending queue
  INDEX `idx_pending_queue` (`status`, `priority`, `submitted_at`),

  -- Foreign Keys
  CONSTRAINT `fk_discrepancy_staff`
    FOREIGN KEY (`staff_id`) REFERENCES `users`(`id`)
    ON DELETE CASCADE,

  CONSTRAINT `fk_discrepancy_payslip`
    FOREIGN KEY (`payslip_id`) REFERENCES `payroll_payslips`(`id`)
    ON DELETE CASCADE,

  CONSTRAINT `fk_discrepancy_approved_by`
    FOREIGN KEY (`approved_by`) REFERENCES `users`(`id`)
    ON DELETE SET NULL,

  CONSTRAINT `fk_discrepancy_declined_by`
    FOREIGN KEY (`declined_by`) REFERENCES `users`(`id`)
    ON DELETE SET NULL,

  CONSTRAINT `fk_discrepancy_amendment`
    FOREIGN KEY (`amendment_id`) REFERENCES `payroll_amendments`(`id`)
    ON DELETE SET NULL

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Wage discrepancy submissions with AI analysis';


-- ============================================================================
-- TABLE: payroll_wage_discrepancy_events
-- ============================================================================
-- Purpose: Event log for discrepancy lifecycle (audit trail)
-- ============================================================================

CREATE TABLE IF NOT EXISTS `payroll_wage_discrepancy_events` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,

  -- Event Details
  `discrepancy_id` INT UNSIGNED NOT NULL COMMENT 'FK to payroll_wage_discrepancies.id',
  `event_type` ENUM(
    'submitted',
    'ai_analyzed',
    'auto_approved',
    'manager_approved',
    'manager_declined',
    'evidence_uploaded',
    'amendment_created',
    'notification_sent'
  ) NOT NULL COMMENT 'Type of event',

  -- Event Context
  `performed_by` INT UNSIGNED NULL COMMENT 'FK to users.id - who performed action (NULL for system)',
  `event_data` JSON NULL COMMENT 'Additional event data',
  `notes` TEXT NULL COMMENT 'Human-readable notes',

  -- Timestamp
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'When event occurred',

  -- Indexes
  PRIMARY KEY (`id`),
  INDEX `idx_discrepancy_id` (`discrepancy_id`),
  INDEX `idx_event_type` (`event_type`),
  INDEX `idx_created_at` (`created_at`),

  -- Foreign Key
  CONSTRAINT `fk_event_discrepancy`
    FOREIGN KEY (`discrepancy_id`) REFERENCES `payroll_wage_discrepancies`(`id`)
    ON DELETE CASCADE,

  CONSTRAINT `fk_event_performed_by`
    FOREIGN KEY (`performed_by`) REFERENCES `users`(`id`)
    ON DELETE SET NULL

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Event log for wage discrepancy lifecycle';


-- ============================================================================
-- INITIAL DATA / CONFIGURATION
-- ============================================================================

-- None required - tables are ready for use


-- ============================================================================
-- MIGRATION FROM OLD SYSTEM (OPTIONAL)
-- ============================================================================
-- Uncomment and run this if you want to migrate data from payroll_wage_issues
-- ============================================================================

/*
INSERT INTO payroll_wage_discrepancies (
  staff_id,
  payslip_id,
  discrepancy_type,
  description,
  line_item_type,
  claimed_hours,
  claimed_amount,
  evidence_path,
  evidence_hash,
  ocr_data,
  ai_analysis,
  risk_score,
  confidence,
  priority,
  estimated_resolution_time,
  status,
  approved_by,
  approved_at,
  admin_notes,
  submitted_at,
  resolved_at
)
SELECT
  staff_id,
  payslip_id,

  -- Map old line_type to new discrepancy_type
  CASE
    WHEN line_type = 'underpaid_time' THEN 'underpaid_hours'
    WHEN line_type = 'overpaid_time' THEN 'overpaid_hours'
    WHEN line_type = 'reimbursement' THEN 'missing_reimbursement'
    ELSE 'other'
  END,

  description,

  -- Map old line_type to new line_item_type
  CASE
    WHEN line_type IN ('underpaid_time', 'overpaid_time') THEN 'earnings'
    WHEN line_type = 'reimbursement' THEN 'reimbursement'
    ELSE NULL
  END,

  hours,
  amount,

  -- Extract evidence from JSON
  JSON_UNQUOTE(JSON_EXTRACT(evidence_json, '$.path')),
  JSON_UNQUOTE(JSON_EXTRACT(evidence_json, '$.hash')),
  ocr_json,

  -- Build AI analysis JSON from old anomaly data
  JSON_OBJECT(
    'risk_score', 0.5,
    'confidence', 0.6,
    'anomalies', anomaly_json,
    'recommendation', IF(status = 'approved', 'approve', 'review'),
    'auto_approve', FALSE,
    'reasoning', 'Migrated from legacy system'
  ),

  0.5, -- Default risk_score
  0.6, -- Default confidence
  'medium', -- Default priority
  '3-5 business days', -- Default resolution time

  -- Map old status to new status
  CASE
    WHEN status = 'approved' THEN 'approved'
    WHEN status = 'declined' THEN 'declined'
    ELSE 'pending_review'
  END,

  applied_by,
  applied_at,
  admin_notes,
  created_at,

  CASE
    WHEN status IN ('approved', 'declined') THEN applied_at
    ELSE NULL
  END

FROM payroll_wage_issues
WHERE 1=1; -- Add WHERE clause if you want to filter

-- Migrate events
INSERT INTO payroll_wage_discrepancy_events (
  discrepancy_id,
  event_type,
  performed_by,
  event_data,
  notes,
  created_at
)
SELECT
  -- Map old issue_id to new discrepancy_id
  (SELECT id FROM payroll_wage_discrepancies
   WHERE payroll_wage_discrepancies.staff_id = payroll_wage_issue_events.staff_id
   AND payroll_wage_discrepancies.submitted_at = (SELECT created_at FROM payroll_wage_issues WHERE id = payroll_wage_issue_events.issue_id)
   LIMIT 1),

  -- Map old event_type to new event_type
  CASE
    WHEN event_type = 'created' THEN 'submitted'
    WHEN event_type = 'approved' THEN 'manager_approved'
    WHEN event_type = 'declined' THEN 'manager_declined'
    WHEN event_type = 'evidence_added' THEN 'evidence_uploaded'
    ELSE 'submitted'
  END,

  user_id,
  NULL, -- event_data
  notes,
  created_at

FROM payroll_wage_issue_events
WHERE 1=1; -- Add WHERE clause if needed
*/


-- ============================================================================
-- PERFORMANCE VERIFICATION QUERIES
-- ============================================================================

-- Test query: Pending discrepancies queue (should use idx_pending_queue)
-- EXPLAIN SELECT * FROM payroll_wage_discrepancies
-- WHERE status = 'pending_review'
-- ORDER BY priority, submitted_at
-- LIMIT 100;

-- Test query: Staff discrepancy history (should use idx_staff_id)
-- EXPLAIN SELECT * FROM payroll_wage_discrepancies
-- WHERE staff_id = 123
-- ORDER BY submitted_at DESC
-- LIMIT 20;

-- Test query: Duplicate evidence check (should use idx_evidence_hash)
-- EXPLAIN SELECT id FROM payroll_wage_discrepancies
-- WHERE evidence_hash = 'abc123...'
-- LIMIT 1;

-- Test query: Statistics (30 days)
-- EXPLAIN SELECT
--   COUNT(*) as total,
--   SUM(CASE WHEN status = 'pending_review' THEN 1 ELSE 0 END) as pending,
--   SUM(CASE WHEN status = 'auto_approved' THEN 1 ELSE 0 END) as auto_approved,
--   AVG(claimed_amount) as avg_amount
-- FROM payroll_wage_discrepancies
-- WHERE submitted_at >= DATE_SUB(NOW(), INTERVAL 30 DAY);


-- ============================================================================
-- NOTES
-- ============================================================================

-- 1. Risk Score Interpretation:
--    0.00-0.20 = Low risk (auto-approve if other conditions met)
--    0.21-0.40 = Medium risk (manager review)
--    0.41-0.70 = High risk (careful review)
--    0.71-1.00 = Very high risk (urgent attention)

-- 2. Auto-Approval Conditions (all must be true):
--    - risk_score < 0.30
--    - confidence > 0.70
--    - claimed_amount < $200
--    - No anomalies flagged

-- 3. Priority Calculation:
--    urgent = risk_score > 0.70 OR multiple high-risk anomalies
--    high   = risk_score > 0.40
--    medium = risk_score > 0.20
--    low    = risk_score <= 0.20

-- 4. Estimated Resolution Times:
--    Immediate        = Auto-approved
--    24-48 hours      = Urgent priority
--    2-3 business days = High priority
--    3-5 business days = Medium priority
--    5-7 business days = Low priority

-- 5. Integration Points:
--    - Links to payroll_amendments.id when approved
--    - References payroll_payslips.id for context
--    - Uses users.id for staff_id and approval tracking
--    - Event log in payroll_wage_discrepancy_events

-- 6. Security Considerations:
--    - Evidence files stored outside public_html
--    - SHA256 hash for duplicate detection
--    - Staff can only view their own discrepancies
--    - Admins required for approve/decline actions
--    - All actions logged in event table

-- 7. AI Analysis JSON Structure:
--    {
--      "risk_score": 0.35,
--      "confidence": 0.82,
--      "anomalies": [
--        {"type": "deputy_mismatch", "severity": "medium", "details": "..."},
--        {"type": "timing_late", "severity": "low", "details": "..."}
--      ],
--      "recommendation": "approve|review|decline",
--      "auto_approve": true|false,
--      "reasoning": "Human-readable explanation..."
--    }

-- 8. Event Data JSON Examples:
--    submitted: {"ip_address": "...", "user_agent": "..."}
--    ai_analyzed: {"layers_executed": ["deputy", "historical", "amount"]}
--    evidence_uploaded: {"filename": "...", "size": 12345, "mime": "image/jpeg"}
--    notification_sent: {"type": "email", "to": "staff@example.com"}

-- ============================================================================
-- END OF SCHEMA
-- ============================================================================
