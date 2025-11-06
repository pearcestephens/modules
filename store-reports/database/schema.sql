-- ============================================================================
-- STORE REPORTS MODULE - AI-POWERED DATABASE SCHEMA
-- ============================================================================
-- Description: Modern store inspection/audit system with AI image analysis
-- Created: 2025-11-05
-- Migration: From legacy store_quality* tables to AI-enhanced system
-- ============================================================================

-- ============================================================================
-- MAIN REPORTS TABLE
-- ============================================================================
CREATE TABLE IF NOT EXISTS store_reports (
    id INT AUTO_INCREMENT PRIMARY KEY,

    -- Basic Info
    outlet_id VARCHAR(255) NOT NULL COMMENT 'vend_outlets.id reference',
    performed_by_user INT NOT NULL COMMENT 'Staff user ID who performed report',
    report_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    -- Scoring
    overall_score DECIMAL(5,2) DEFAULT 0.00 COMMENT 'Final calculated score (0-100)',
    grade VARCHAR(5) DEFAULT NULL COMMENT 'A+, A, B+, B, etc.',
    manual_score DECIMAL(5,2) DEFAULT NULL COMMENT 'Human-reviewed score',
    ai_score DECIMAL(5,2) DEFAULT NULL COMMENT 'AI-calculated score',

    -- Status & Workflow
    status ENUM('draft', 'in_progress', 'ai_analyzing', 'completed', 'reviewed') DEFAULT 'draft',
    ai_analysis_status ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending',
    ai_analysis_started_at DATETIME DEFAULT NULL,
    ai_analysis_completed_at DATETIME DEFAULT NULL,

    -- AI Insights
    ai_summary TEXT DEFAULT NULL COMMENT 'AI-generated executive summary',
    ai_strengths TEXT DEFAULT NULL COMMENT 'JSON array of identified strengths',
    ai_concerns TEXT DEFAULT NULL COMMENT 'JSON array of identified concerns',
    ai_recommendations TEXT DEFAULT NULL COMMENT 'JSON array of AI recommendations',
    ai_confidence_score DECIMAL(5,2) DEFAULT NULL COMMENT 'AI confidence (0-100)',

    -- Human Notes
    staff_notes TEXT DEFAULT NULL COMMENT 'Staff comments/observations',
    manager_review_notes TEXT DEFAULT NULL,
    reviewed_by_user INT DEFAULT NULL,
    reviewed_at DATETIME DEFAULT NULL,

    -- Metadata
    total_items INT DEFAULT 0 COMMENT 'Total checklist items',
    items_passed INT DEFAULT 0,
    items_failed INT DEFAULT 0,
    items_na INT DEFAULT 0,
    total_images INT DEFAULT 0,
    images_analyzed INT DEFAULT 0,

    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME DEFAULT NULL COMMENT 'Soft delete',

    -- Indexes
    INDEX idx_outlet (outlet_id),
    INDEX idx_performed_by (performed_by_user),
    INDEX idx_report_date (report_date),
    INDEX idx_status (status),
    INDEX idx_ai_status (ai_analysis_status),
    INDEX idx_grade (grade),
    INDEX idx_overall_score (overall_score),
    INDEX idx_deleted (deleted_at)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='AI-powered store inspection/audit reports';

-- ============================================================================
-- REPORT CHECKLIST ITEMS (Question/Answer Recording)
-- ============================================================================
CREATE TABLE IF NOT EXISTS store_report_items (
    id INT AUTO_INCREMENT PRIMARY KEY,

    -- Relations
    report_id INT NOT NULL COMMENT 'FK to store_reports.id',
    checklist_id INT NOT NULL COMMENT 'FK to store_report_checklist.id',

    -- Response
    response_value INT DEFAULT NULL COMMENT '0-4 rating or boolean',
    response_text TEXT DEFAULT NULL COMMENT 'Free-text response if applicable',
    is_na BOOLEAN DEFAULT FALSE COMMENT 'Not applicable flag',

    -- Scoring
    max_points INT DEFAULT 4 COMMENT 'Maximum points possible',
    points_earned DECIMAL(5,2) DEFAULT 0.00 COMMENT 'Actual points earned',
    weight DECIMAL(5,2) DEFAULT 1.00 COMMENT 'Weight multiplier',

    -- AI Analysis
    ai_detected_issues TEXT DEFAULT NULL COMMENT 'JSON array of AI-detected problems',
    ai_confidence DECIMAL(5,2) DEFAULT NULL COMMENT 'AI confidence in assessment',
    ai_suggested_score INT DEFAULT NULL COMMENT 'AI suggested rating',
    ai_override_reason TEXT DEFAULT NULL COMMENT 'Why AI suggests different score',

    -- Staff Notes
    staff_notes TEXT DEFAULT NULL COMMENT 'Staff observations for this item',
    photo_references TEXT DEFAULT NULL COMMENT 'JSON array of image IDs',

    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Indexes
    INDEX idx_report (report_id),
    INDEX idx_checklist (checklist_id),
    INDEX idx_response (response_value),
    FOREIGN KEY (report_id) REFERENCES store_reports(id) ON DELETE CASCADE

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Individual checklist item responses with AI analysis';

-- ============================================================================
-- MASTER CHECKLIST (Questions/Criteria)
-- ============================================================================
CREATE TABLE IF NOT EXISTS store_report_checklist (
    id INT AUTO_INCREMENT PRIMARY KEY,

    -- Question Details
    category VARCHAR(100) NOT NULL COMMENT 'e.g., Cleanliness, Safety, Display, etc.',
    name VARCHAR(255) NOT NULL COMMENT 'Machine name (e.g., cabinet_glass_clean)',
    title VARCHAR(500) NOT NULL COMMENT 'Display title',
    description TEXT DEFAULT NULL COMMENT 'Detailed explanation',

    -- Question Type
    question_type ENUM('rating', 'boolean', 'text', 'photo_required') DEFAULT 'rating',
    input_type ENUM('select', 'radio', 'checkbox', 'textarea', 'file') DEFAULT 'select',

    -- Scoring
    max_points INT DEFAULT 4 COMMENT 'Maximum points (0-4)',
    weight DECIMAL(5,2) DEFAULT 1.00 COMMENT 'Weight in overall score',
    is_critical BOOLEAN DEFAULT FALSE COMMENT 'Critical item (auto-fail if failed)',
    counts_toward_grade BOOLEAN DEFAULT TRUE COMMENT 'Included in grade calculation',

    -- AI Guidance
    ai_analysis_enabled BOOLEAN DEFAULT TRUE COMMENT 'Enable AI analysis for this item',
    ai_detection_criteria TEXT DEFAULT NULL COMMENT 'JSON: What AI should look for',
    ai_prompt_template TEXT DEFAULT NULL COMMENT 'Custom AI prompt for this question',
    photo_required BOOLEAN DEFAULT FALSE COMMENT 'Must have photo evidence',
    min_photos INT DEFAULT 0 COMMENT 'Minimum photos required',

    -- Display
    display_order INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    help_text TEXT DEFAULT NULL COMMENT 'Guidance for staff',

    -- Options (for select/radio)
    options TEXT DEFAULT NULL COMMENT 'JSON array of options',

    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Indexes
    INDEX idx_category (category),
    INDEX idx_active (is_active),
    INDEX idx_order (display_order),
    INDEX idx_critical (is_critical)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Master checklist/questionnaire for store reports';

-- ============================================================================
-- REPORT IMAGES (with AI Analysis)
-- ============================================================================
CREATE TABLE IF NOT EXISTS store_report_images (
    id INT AUTO_INCREMENT PRIMARY KEY,

    -- Relations
    report_id INT NOT NULL COMMENT 'FK to store_reports.id',
    checklist_item_id INT DEFAULT NULL COMMENT 'FK to store_report_items.id (optional)',

    -- File Info
    filename VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_size INT DEFAULT NULL COMMENT 'Bytes',
    mime_type VARCHAR(100) DEFAULT NULL,
    width INT DEFAULT NULL,
    height INT DEFAULT NULL,

    -- Image Metadata
    uploaded_by_user INT NOT NULL,
    upload_timestamp DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    caption TEXT DEFAULT NULL COMMENT 'Staff-provided caption',
    location_in_store VARCHAR(255) DEFAULT NULL COMMENT 'e.g., Front Counter, Bathroom, Display',

    -- AI Analysis
    ai_analyzed BOOLEAN DEFAULT FALSE,
    ai_analysis_timestamp DATETIME DEFAULT NULL,
    ai_analysis_duration_ms INT DEFAULT NULL,
    ai_model_version VARCHAR(100) DEFAULT NULL COMMENT 'e.g., gpt-4-vision-preview',

    -- AI Detection Results
    ai_description TEXT DEFAULT NULL COMMENT 'AI-generated description of image',
    ai_detected_objects TEXT DEFAULT NULL COMMENT 'JSON array of detected objects',
    ai_detected_issues TEXT DEFAULT NULL COMMENT 'JSON array of problems detected',
    ai_detected_positives TEXT DEFAULT NULL COMMENT 'JSON array of good things detected',
    ai_cleanliness_score DECIMAL(5,2) DEFAULT NULL COMMENT '0-100 cleanliness',
    ai_organization_score DECIMAL(5,2) DEFAULT NULL COMMENT '0-100 organization',
    ai_compliance_score DECIMAL(5,2) DEFAULT NULL COMMENT '0-100 compliance',
    ai_safety_score DECIMAL(5,2) DEFAULT NULL COMMENT '0-100 safety',
    ai_overall_score DECIMAL(5,2) DEFAULT NULL COMMENT '0-100 overall',
    ai_confidence DECIMAL(5,2) DEFAULT NULL COMMENT '0-100 confidence',

    -- AI Recommendations
    ai_flags TEXT DEFAULT NULL COMMENT 'JSON array of flags (warning, danger, etc.)',
    ai_recommendations TEXT DEFAULT NULL COMMENT 'JSON array of improvement suggestions',
    ai_follow_up_needed BOOLEAN DEFAULT FALSE COMMENT 'AI requests follow-up photo',
    ai_follow_up_request TEXT DEFAULT NULL COMMENT 'What AI wants to see in follow-up',

    -- Status
    status ENUM('uploaded', 'queued', 'analyzing', 'analyzed', 'failed') DEFAULT 'uploaded',
    is_primary BOOLEAN DEFAULT FALSE COMMENT 'Primary/featured image',
    is_before BOOLEAN DEFAULT FALSE COMMENT 'Before photo',
    is_after BOOLEAN DEFAULT FALSE COMMENT 'After photo (for fixes)',

    -- Error Handling
    ai_error_message TEXT DEFAULT NULL,
    ai_retry_count INT DEFAULT 0,

    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME DEFAULT NULL,

    -- Indexes
    INDEX idx_report (report_id),
    INDEX idx_checklist_item (checklist_item_id),
    INDEX idx_ai_analyzed (ai_analyzed),
    INDEX idx_status (status),
    INDEX idx_uploaded_by (uploaded_by_user),
    INDEX idx_deleted (deleted_at),
    FOREIGN KEY (report_id) REFERENCES store_reports(id) ON DELETE CASCADE

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Store report photos with comprehensive AI analysis';

-- ============================================================================
-- AI PHOTO REQUESTS (AI asks for specific follow-ups)
-- ============================================================================
CREATE TABLE IF NOT EXISTS store_report_ai_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,

    -- Relations
    report_id INT NOT NULL COMMENT 'FK to store_reports.id',
    trigger_image_id INT DEFAULT NULL COMMENT 'Image that triggered request',
    checklist_item_id INT DEFAULT NULL COMMENT 'Related checklist item',

    -- Request Details
    request_type ENUM('clarification', 'close_up', 'different_angle', 'specific_area', 'follow_up', 'compliance') DEFAULT 'clarification',
    priority ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
    request_title VARCHAR(500) NOT NULL COMMENT 'e.g., "Please provide close-up of product display"',
    request_description TEXT NOT NULL COMMENT 'Detailed explanation of what AI wants to see',
    ai_reasoning TEXT DEFAULT NULL COMMENT 'Why AI is requesting this',

    -- Response
    status ENUM('pending', 'fulfilled', 'skipped', 'cannot_fulfill') DEFAULT 'pending',
    fulfilled_by_image_id INT DEFAULT NULL COMMENT 'Image that fulfilled request',
    fulfilled_at DATETIME DEFAULT NULL,
    staff_response_note TEXT DEFAULT NULL COMMENT 'Staff explanation if cannot fulfill',

    -- AI Follow-up Analysis
    ai_satisfied BOOLEAN DEFAULT NULL COMMENT 'Was AI satisfied with response',
    ai_satisfaction_note TEXT DEFAULT NULL,

    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Indexes
    INDEX idx_report (report_id),
    INDEX idx_status (status),
    INDEX idx_priority (priority),
    INDEX idx_trigger_image (trigger_image_id),
    FOREIGN KEY (report_id) REFERENCES store_reports(id) ON DELETE CASCADE

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='AI-generated requests for additional photos/clarification';

-- ============================================================================
-- REPORT HISTORY / AUDIT LOG
-- ============================================================================
CREATE TABLE IF NOT EXISTS store_report_history (
    id INT AUTO_INCREMENT PRIMARY KEY,

    -- Relations
    report_id INT NOT NULL COMMENT 'FK to store_reports.id',
    user_id INT DEFAULT NULL COMMENT 'User who made change (NULL for system/AI)',

    -- Change Details
    action_type ENUM('created', 'updated', 'submitted', 'ai_analyzed', 'reviewed', 'score_changed', 'image_added', 'image_analyzed', 'ai_request', 'comment_added') NOT NULL,
    field_changed VARCHAR(100) DEFAULT NULL COMMENT 'Which field was modified',
    old_value TEXT DEFAULT NULL,
    new_value TEXT DEFAULT NULL,
    description TEXT DEFAULT NULL COMMENT 'Human-readable description',

    -- Metadata
    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent VARCHAR(500) DEFAULT NULL,

    -- Timestamp
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    -- Indexes
    INDEX idx_report (report_id),
    INDEX idx_user (user_id),
    INDEX idx_action (action_type),
    INDEX idx_created (created_at),
    FOREIGN KEY (report_id) REFERENCES store_reports(id) ON DELETE CASCADE

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Complete audit trail of all report changes';

-- ============================================================================
-- COMPARISON & BENCHMARKING VIEW
-- ============================================================================
CREATE OR REPLACE VIEW vw_store_report_benchmarks AS
SELECT
    vo.id AS outlet_id,
    vo.name AS outlet_name,

    -- Latest Report
    (SELECT overall_score FROM store_reports WHERE outlet_id = vo.id AND deleted_at IS NULL ORDER BY report_date DESC LIMIT 1) AS latest_score,
    (SELECT grade FROM store_reports WHERE outlet_id = vo.id AND deleted_at IS NULL ORDER BY report_date DESC LIMIT 1) AS latest_grade,
    (SELECT report_date FROM store_reports WHERE outlet_id = vo.id AND deleted_at IS NULL ORDER BY report_date DESC LIMIT 1) AS latest_report_date,

    -- Statistics
    COUNT(sr.id) AS total_reports,
    AVG(sr.overall_score) AS avg_score,
    MAX(sr.overall_score) AS best_score,
    MIN(sr.overall_score) AS worst_score,
    STDDEV(sr.overall_score) AS score_std_dev,

    -- AI Insights
    AVG(sr.ai_confidence_score) AS avg_ai_confidence,
    SUM(CASE WHEN sr.ai_analysis_status = 'completed' THEN 1 ELSE 0 END) AS ai_analyzed_count,

    -- Trend (last 3 vs previous 3)
    (SELECT AVG(overall_score) FROM store_reports WHERE outlet_id = vo.id AND deleted_at IS NULL ORDER BY report_date DESC LIMIT 3) AS recent_avg,
    (SELECT AVG(overall_score) FROM store_reports WHERE outlet_id = vo.id AND deleted_at IS NULL ORDER BY report_date DESC LIMIT 3 OFFSET 3) AS previous_avg,

    -- Rankings
    RANK() OVER (ORDER BY (SELECT overall_score FROM store_reports WHERE outlet_id = vo.id AND deleted_at IS NULL ORDER BY report_date DESC LIMIT 1) DESC) AS current_rank

FROM vend_outlets vo
LEFT JOIN store_reports sr ON vo.id = sr.outlet_id AND sr.deleted_at IS NULL
GROUP BY vo.id, vo.name;

-- ============================================================================
-- AI PERFORMANCE METRICS VIEW
-- ============================================================================
CREATE OR REPLACE VIEW vw_ai_analysis_metrics AS
SELECT
    DATE(ai_analysis_completed_at) AS analysis_date,
    COUNT(*) AS total_analyses,
    AVG(TIMESTAMPDIFF(SECOND, ai_analysis_started_at, ai_analysis_completed_at)) AS avg_duration_seconds,
    AVG(ai_confidence_score) AS avg_confidence,
    SUM(CASE WHEN ai_analysis_status = 'completed' THEN 1 ELSE 0 END) AS successful_analyses,
    SUM(CASE WHEN ai_analysis_status = 'failed' THEN 1 ELSE 0 END) AS failed_analyses,
    AVG(images_analyzed) AS avg_images_per_report
FROM store_reports
WHERE ai_analysis_started_at IS NOT NULL
GROUP BY DATE(ai_analysis_completed_at)
ORDER BY analysis_date DESC;

-- ============================================================================
-- SEED DEFAULT CHECKLIST (Migration from old system)
-- ============================================================================
-- This will be populated from existing store_quality_score_checklist table
-- See migration script: database/migrate_legacy_data.php

-- ============================================================================
-- INDEXES FOR PERFORMANCE
-- ============================================================================
-- Additional composite indexes for common queries
CREATE INDEX idx_report_outlet_date ON store_reports(outlet_id, report_date, deleted_at);
CREATE INDEX idx_image_report_analyzed ON store_report_images(report_id, ai_analyzed, status);
CREATE INDEX idx_request_report_status ON store_report_ai_requests(report_id, status, priority);

-- ============================================================================
-- SCHEMA VERSION TRACKING
-- ============================================================================
CREATE TABLE IF NOT EXISTS store_reports_schema_version (
    id INT AUTO_INCREMENT PRIMARY KEY,
    version VARCHAR(20) NOT NULL,
    description TEXT,
    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT INTO store_reports_schema_version (version, description)
VALUES ('1.0.0', 'Initial AI-powered store reports schema')
ON DUPLICATE KEY UPDATE version = version;
