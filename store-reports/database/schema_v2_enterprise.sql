-- ============================================================================
-- STORE REPORTS MODULE - ENTERPRISE-GRADE AI-POWERED DATABASE SCHEMA V2
-- ============================================================================
-- Description: Complete mobile-first store inspection system with:
--   - AI Vision Analysis & Conversational AI
--   - Real-time autosave & recovery
--   - Voice memos & transcription
--   - Photo optimization pipeline
--   - Versioned checklists (backward compatible)
--   - Complete audit trail
--   - Mobile PWA support
--   - Legacy data migration
-- Created: 2025-11-13
-- Author: Enterprise Engineering Team
-- ============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================================
-- MAIN REPORTS TABLE (Enhanced with mobile & autosave features)
-- ============================================================================
CREATE TABLE IF NOT EXISTS store_reports (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    -- Basic Info
    outlet_id VARCHAR(255) NOT NULL COMMENT 'vend_outlets.id reference',
    performed_by_user INT UNSIGNED NOT NULL COMMENT 'Staff user ID who performed report',
    report_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    -- Checklist Version (supports changes over time)
    checklist_version_id INT UNSIGNED DEFAULT NULL COMMENT 'FK to store_report_checklist_versions',

    -- Scoring (Comparative: Staff vs AI)
    overall_score DECIMAL(5,2) DEFAULT 0.00 COMMENT 'Final calculated score (0-100)',
    staff_score DECIMAL(5,2) DEFAULT NULL COMMENT 'Staff-only assessment score',
    ai_score DECIMAL(5,2) DEFAULT NULL COMMENT 'AI-only assessment score',
    combined_score DECIMAL(5,2) DEFAULT NULL COMMENT 'Weighted average of both',
    score_variance DECIMAL(5,2) DEFAULT NULL COMMENT 'Difference between staff and AI',
    grade VARCHAR(5) DEFAULT NULL COMMENT 'A+, A, B+, B, etc.',
    manual_override_score DECIMAL(5,2) DEFAULT NULL COMMENT 'Manager override',

    -- Status & Workflow
    status ENUM('draft', 'in_progress', 'autosaved', 'ai_analyzing', 'pending_review', 'completed', 'archived') DEFAULT 'draft',
    completion_percentage DECIMAL(5,2) DEFAULT 0.00 COMMENT 'Progress tracker for mobile',

    -- AI Analysis Status
    ai_analysis_status ENUM('pending', 'queued', 'processing', 'completed', 'partial', 'failed') DEFAULT 'pending',
    ai_analysis_started_at DATETIME DEFAULT NULL,
    ai_analysis_completed_at DATETIME DEFAULT NULL,
    ai_analysis_duration_ms INT UNSIGNED DEFAULT NULL,
    ai_model_version VARCHAR(100) DEFAULT NULL COMMENT 'e.g., gpt-4-vision-preview',
    ai_tokens_used INT UNSIGNED DEFAULT 0 COMMENT 'API cost tracking',

    -- AI Insights & Recommendations
    ai_summary MEDIUMTEXT DEFAULT NULL COMMENT 'AI-generated executive summary',
    ai_strengths JSON DEFAULT NULL COMMENT 'Array of identified strengths',
    ai_concerns JSON DEFAULT NULL COMMENT 'Array of identified concerns',
    ai_recommendations JSON DEFAULT NULL COMMENT 'Array of AI recommendations with priority',
    ai_confidence_score DECIMAL(5,2) DEFAULT NULL COMMENT 'AI confidence (0-100)',
    ai_risk_assessment VARCHAR(50) DEFAULT NULL COMMENT 'low, medium, high, critical',

    -- AI Conversational Data
    ai_conversation_summary TEXT DEFAULT NULL COMMENT 'Summary of AI interactions',
    ai_questions_asked INT UNSIGNED DEFAULT 0 COMMENT 'How many questions AI asked',
    ai_photos_requested INT UNSIGNED DEFAULT 0 COMMENT 'How many follow-up photos requested',
    ai_satisfaction_rating DECIMAL(5,2) DEFAULT NULL COMMENT 'AI satisfaction with provided data',

    -- Human Notes & Review
    staff_notes MEDIUMTEXT DEFAULT NULL COMMENT 'Staff comments/observations',
    manager_review_notes MEDIUMTEXT DEFAULT NULL,
    reviewed_by_user INT UNSIGNED DEFAULT NULL,
    reviewed_at DATETIME DEFAULT NULL,

    -- Action Items & Follow-ups
    requires_follow_up BOOLEAN DEFAULT FALSE,
    follow_up_deadline DATE DEFAULT NULL,
    follow_up_completed_at DATETIME DEFAULT NULL,
    critical_issues_count INT UNSIGNED DEFAULT 0,

    -- Metadata & Counters
    total_items INT UNSIGNED DEFAULT 0 COMMENT 'Total checklist items',
    items_completed INT UNSIGNED DEFAULT 0,
    items_passed INT UNSIGNED DEFAULT 0,
    items_failed INT UNSIGNED DEFAULT 0,
    items_na INT UNSIGNED DEFAULT 0,
    total_images INT UNSIGNED DEFAULT 0,
    images_analyzed INT UNSIGNED DEFAULT 0,
    total_voice_memos INT UNSIGNED DEFAULT 0,
    voice_memos_transcribed INT UNSIGNED DEFAULT 0,

    -- Autosave & Recovery
    last_autosave_at DATETIME DEFAULT NULL,
    autosave_checkpoint_id INT UNSIGNED DEFAULT NULL COMMENT 'FK to store_report_autosave_checkpoints',
    device_id VARCHAR(255) DEFAULT NULL COMMENT 'Mobile device identifier',
    session_id VARCHAR(255) DEFAULT NULL COMMENT 'Session tracking',

    -- Mobile & Offline Support
    created_offline BOOLEAN DEFAULT FALSE,
    synced_at DATETIME DEFAULT NULL,
    sync_conflicts JSON DEFAULT NULL COMMENT 'Conflict resolution data',

    -- Duration Tracking
    started_at DATETIME DEFAULT NULL COMMENT 'When staff started the inspection',
    submitted_at DATETIME DEFAULT NULL COMMENT 'When staff submitted for review',
    time_spent_minutes INT UNSIGNED DEFAULT NULL COMMENT 'Total time on inspection',

    -- Location Data (if GPS available)
    gps_latitude DECIMAL(10,8) DEFAULT NULL,
    gps_longitude DECIMAL(11,8) DEFAULT NULL,
    gps_accuracy DECIMAL(10,2) DEFAULT NULL COMMENT 'Meters',

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
    INDEX idx_checklist_version (checklist_version_id),
    INDEX idx_session (session_id),
    INDEX idx_device (device_id),
    INDEX idx_deleted (deleted_at),
    INDEX idx_outlet_date_status (outlet_id, report_date, status),
    INDEX idx_follow_up (requires_follow_up, follow_up_deadline),

    -- Foreign Keys
    FOREIGN KEY (checklist_version_id) REFERENCES store_report_checklist_versions(id) ON DELETE SET NULL

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='AI-powered store inspection reports with mobile & autosave support';

-- ============================================================================
-- CHECKLIST VERSIONS (Allows checklist changes without breaking old reports)
-- ============================================================================
CREATE TABLE IF NOT EXISTS store_report_checklist_versions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    -- Version Info
    version_number VARCHAR(20) NOT NULL COMMENT 'e.g., 1.0.0, 1.1.0',
    version_name VARCHAR(255) DEFAULT NULL COMMENT 'e.g., "Q4 2025 Update"',
    description TEXT DEFAULT NULL,

    -- Status
    status ENUM('draft', 'active', 'deprecated', 'archived') DEFAULT 'draft',
    is_default BOOLEAN DEFAULT FALSE COMMENT 'Default for new reports',

    -- Change Tracking
    created_by_user INT UNSIGNED NOT NULL,
    effective_from DATE NOT NULL,
    effective_until DATE DEFAULT NULL,
    replaced_by_version_id INT UNSIGNED DEFAULT NULL COMMENT 'Migration path',

    -- Metadata
    total_questions INT UNSIGNED DEFAULT 0,
    migration_notes TEXT DEFAULT NULL COMMENT 'How to migrate old reports',

    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Indexes
    INDEX idx_status (status),
    INDEX idx_is_default (is_default),
    INDEX idx_effective (effective_from, effective_until),
    INDEX idx_version_number (version_number),

    -- Unique constraint
    UNIQUE KEY uk_version_number (version_number)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Versioned checklist system for backward compatibility';

-- ============================================================================
-- MASTER CHECKLIST (Enhanced with versioning & AI prompts)
-- ============================================================================
CREATE TABLE IF NOT EXISTS store_report_checklist (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    -- Versioning
    version_id INT UNSIGNED NOT NULL COMMENT 'FK to store_report_checklist_versions',

    -- Question Details
    category VARCHAR(100) NOT NULL COMMENT 'e.g., Cleanliness, Safety, Display, Compliance',
    subcategory VARCHAR(100) DEFAULT NULL COMMENT 'Optional grouping',
    name VARCHAR(255) NOT NULL COMMENT 'Machine name (e.g., cabinet_glass_clean)',
    title VARCHAR(500) NOT NULL COMMENT 'Display title',
    description TEXT DEFAULT NULL COMMENT 'Detailed explanation for staff',

    -- Question Type & Input
    question_type ENUM('rating', 'boolean', 'text', 'numeric', 'photo_required', 'voice_memo', 'multi_choice') DEFAULT 'rating',
    input_type ENUM('select', 'radio', 'checkbox', 'textarea', 'number', 'file', 'voice', 'slider') DEFAULT 'select',

    -- Scoring Configuration
    max_points INT UNSIGNED DEFAULT 4 COMMENT 'Maximum points (0-4 scale)',
    weight DECIMAL(5,2) DEFAULT 1.00 COMMENT 'Weight in overall score',
    is_critical BOOLEAN DEFAULT FALSE COMMENT 'Critical item (auto-fail if failed)',
    critical_threshold INT UNSIGNED DEFAULT NULL COMMENT 'Score below which = fail',
    counts_toward_grade BOOLEAN DEFAULT TRUE COMMENT 'Included in grade calculation',

    -- AI Configuration
    ai_analysis_enabled BOOLEAN DEFAULT TRUE COMMENT 'Enable AI analysis for this item',
    ai_detection_criteria JSON DEFAULT NULL COMMENT 'What AI should look for',
    ai_prompt_template TEXT DEFAULT NULL COMMENT 'Custom GPT prompt template',
    ai_vision_enabled BOOLEAN DEFAULT FALSE COMMENT 'Requires image analysis',
    ai_expected_objects JSON DEFAULT NULL COMMENT 'Objects AI should detect in photos',
    ai_conversation_enabled BOOLEAN DEFAULT FALSE COMMENT 'AI can ask follow-up questions',

    -- Photo Requirements
    photo_required BOOLEAN DEFAULT FALSE COMMENT 'Must have photo evidence',
    min_photos INT UNSIGNED DEFAULT 0 COMMENT 'Minimum photos required',
    max_photos INT UNSIGNED DEFAULT 10 COMMENT 'Maximum photos allowed',
    photo_guidelines TEXT DEFAULT NULL COMMENT 'What photos should show',

    -- Voice Memo Support
    voice_memo_allowed BOOLEAN DEFAULT TRUE,
    voice_memo_max_duration_seconds INT UNSIGNED DEFAULT 300 COMMENT '5 minutes max',

    -- Display & UX
    display_order INT UNSIGNED DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    help_text TEXT DEFAULT NULL COMMENT 'Contextual help for staff',
    placeholder_text VARCHAR(255) DEFAULT NULL,
    icon_class VARCHAR(50) DEFAULT NULL COMMENT 'FontAwesome icon',

    -- Options (for select/radio/multi-choice)
    options JSON DEFAULT NULL COMMENT 'Array of {value, label, points} objects',

    -- Conditional Logic
    depends_on_question_id INT UNSIGNED DEFAULT NULL COMMENT 'Show only if condition met',
    show_if_condition JSON DEFAULT NULL COMMENT 'Conditional display logic',

    -- Mobile Optimization
    mobile_layout ENUM('full_width', 'half_width', 'compact') DEFAULT 'full_width',
    touch_optimized BOOLEAN DEFAULT TRUE,

    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Indexes
    INDEX idx_version (version_id),
    INDEX idx_category (category),
    INDEX idx_active (is_active),
    INDEX idx_order (display_order),
    INDEX idx_critical (is_critical),
    INDEX idx_photo_required (photo_required),
    INDEX idx_version_category_order (version_id, category, display_order),

    -- Foreign Keys
    FOREIGN KEY (version_id) REFERENCES store_report_checklist_versions(id) ON DELETE CASCADE,
    FOREIGN KEY (depends_on_question_id) REFERENCES store_report_checklist(id) ON DELETE SET NULL,

    -- Unique constraint within version
    UNIQUE KEY uk_version_name (version_id, name)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Master checklist with versioning and AI integration';

-- ============================================================================
-- REPORT CHECKLIST ITEMS (Responses with AI comparison)
-- ============================================================================
CREATE TABLE IF NOT EXISTS store_report_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    -- Relations
    report_id INT NOT NULL COMMENT 'FK to store_reports.id (INT to match existing table)',
    checklist_id INT UNSIGNED NOT NULL COMMENT 'FK to store_report_checklist.id',

    -- Staff Response
    response_value INT DEFAULT NULL COMMENT 'Numeric response (0-4 rating)',
    response_text MEDIUMTEXT DEFAULT NULL COMMENT 'Free-text response',
    response_boolean BOOLEAN DEFAULT NULL COMMENT 'Yes/No response',
    response_json JSON DEFAULT NULL COMMENT 'Complex response data',
    is_na BOOLEAN DEFAULT FALSE COMMENT 'Not applicable flag',
    na_reason VARCHAR(500) DEFAULT NULL COMMENT 'Why marked N/A',

    -- Scoring
    max_points INT UNSIGNED DEFAULT 4 COMMENT 'Maximum points possible',
    points_earned DECIMAL(5,2) DEFAULT 0.00 COMMENT 'Actual points earned',
    weight DECIMAL(5,2) DEFAULT 1.00 COMMENT 'Weight multiplier',
    weighted_points DECIMAL(5,2) DEFAULT 0.00 COMMENT 'points_earned * weight',

    -- AI Analysis & Comparison
    ai_detected_issues JSON DEFAULT NULL COMMENT 'Array of AI-detected problems',
    ai_detected_positives JSON DEFAULT NULL COMMENT 'Array of good things AI found',
    ai_confidence DECIMAL(5,2) DEFAULT NULL COMMENT 'AI confidence in assessment',
    ai_suggested_score INT DEFAULT NULL COMMENT 'AI suggested rating',
    ai_reasoning TEXT DEFAULT NULL COMMENT 'AI explanation of its score',
    ai_agrees_with_staff BOOLEAN DEFAULT NULL COMMENT 'Does AI agree with staff score?',
    ai_variance DECIMAL(5,2) DEFAULT NULL COMMENT 'Difference between AI and staff',

    -- AI Follow-up
    ai_needs_clarification BOOLEAN DEFAULT FALSE,
    ai_clarification_request TEXT DEFAULT NULL COMMENT 'What AI wants to know',
    ai_clarification_response TEXT DEFAULT NULL COMMENT 'Staff response to AI question',

    -- Staff Notes & Evidence
    staff_notes MEDIUMTEXT DEFAULT NULL COMMENT 'Staff observations',
    photo_references JSON DEFAULT NULL COMMENT 'Array of image IDs',
    voice_memo_references JSON DEFAULT NULL COMMENT 'Array of voice memo IDs',

    -- Timing
    answered_at DATETIME DEFAULT NULL,
    time_spent_seconds INT UNSIGNED DEFAULT NULL,

    -- Autosave
    autosaved_at DATETIME DEFAULT NULL,

    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Indexes
    INDEX idx_report (report_id),
    INDEX idx_checklist (checklist_id),
    INDEX idx_response (response_value),
    INDEX idx_is_na (is_na),
    INDEX idx_ai_needs_clarification (ai_needs_clarification),

    -- Foreign Keys
    FOREIGN KEY (report_id) REFERENCES store_reports(id) ON DELETE CASCADE,
    FOREIGN KEY (checklist_id) REFERENCES store_report_checklist(id) ON DELETE CASCADE

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Individual checklist responses with AI comparison & analysis';

-- ============================================================================
-- REPORT IMAGES (Enhanced with optimization & AI vision)
-- ============================================================================
CREATE TABLE IF NOT EXISTS store_report_images (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    -- Relations
    report_id INT NOT NULL COMMENT 'FK to store_reports.id (INT to match existing table)',
    checklist_item_id INT DEFAULT NULL COMMENT 'FK to store_report_items.id',
    ai_request_id INT DEFAULT NULL COMMENT 'If fulfilling AI request',

    -- Original File Info
    original_filename VARCHAR(255) NOT NULL,
    original_file_path VARCHAR(500) NOT NULL,
    original_file_size INT UNSIGNED DEFAULT NULL COMMENT 'Bytes',
    original_mime_type VARCHAR(100) DEFAULT NULL,
    original_width INT UNSIGNED DEFAULT NULL,
    original_height INT UNSIGNED DEFAULT NULL,

    -- Optimized Versions
    optimized_file_path VARCHAR(500) DEFAULT NULL COMMENT 'WebP optimized version',
    optimized_file_size INT UNSIGNED DEFAULT NULL,
    thumbnail_path VARCHAR(500) DEFAULT NULL COMMENT 'Small thumbnail (150x150)',
    thumbnail_size INT UNSIGNED DEFAULT NULL,
    medium_path VARCHAR(500) DEFAULT NULL COMMENT 'Medium size (800x800)',
    medium_size INT UNSIGNED DEFAULT NULL,

    -- Optimization Stats
    optimization_completed_at DATETIME DEFAULT NULL,
    optimization_duration_ms INT UNSIGNED DEFAULT NULL,
    size_reduction_percent DECIMAL(5,2) DEFAULT NULL,
    quality_score DECIMAL(5,2) DEFAULT NULL COMMENT '0-100',

    -- EXIF Data
    exif_data JSON DEFAULT NULL COMMENT 'Camera, GPS, timestamp, etc.',
    capture_timestamp DATETIME DEFAULT NULL COMMENT 'From EXIF',
    camera_make VARCHAR(100) DEFAULT NULL,
    camera_model VARCHAR(100) DEFAULT NULL,
    gps_latitude DECIMAL(10,8) DEFAULT NULL,
    gps_longitude DECIMAL(11,8) DEFAULT NULL,

    -- Image Metadata
    uploaded_by_user INT UNSIGNED NOT NULL,
    upload_timestamp DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    caption TEXT DEFAULT NULL COMMENT 'Staff-provided caption',
    location_in_store VARCHAR(255) DEFAULT NULL COMMENT 'e.g., Front Counter, Bathroom',
    tags JSON DEFAULT NULL COMMENT 'User or AI tags',

    -- AI Vision Analysis
    ai_analyzed BOOLEAN DEFAULT FALSE,
    ai_analysis_timestamp DATETIME DEFAULT NULL,
    ai_analysis_duration_ms INT UNSIGNED DEFAULT NULL,
    ai_model_version VARCHAR(100) DEFAULT NULL COMMENT 'e.g., gpt-4-vision-preview',
    ai_tokens_used INT UNSIGNED DEFAULT 0,

    -- AI Detection Results
    ai_description TEXT DEFAULT NULL COMMENT 'AI-generated description',
    ai_detected_objects JSON DEFAULT NULL COMMENT 'Array of detected objects with confidence',
    ai_detected_text JSON DEFAULT NULL COMMENT 'OCR text extraction',
    ai_detected_issues JSON DEFAULT NULL COMMENT 'Problems detected',
    ai_detected_positives JSON DEFAULT NULL COMMENT 'Good things detected',

    -- AI Scoring Dimensions
    ai_cleanliness_score DECIMAL(5,2) DEFAULT NULL COMMENT '0-100',
    ai_organization_score DECIMAL(5,2) DEFAULT NULL COMMENT '0-100',
    ai_compliance_score DECIMAL(5,2) DEFAULT NULL COMMENT '0-100',
    ai_safety_score DECIMAL(5,2) DEFAULT NULL COMMENT '0-100',
    ai_branding_score DECIMAL(5,2) DEFAULT NULL COMMENT '0-100 brand standards',
    ai_overall_score DECIMAL(5,2) DEFAULT NULL COMMENT '0-100 overall',
    ai_confidence DECIMAL(5,2) DEFAULT NULL COMMENT '0-100 AI confidence',

    -- AI Recommendations & Flags
    ai_flags JSON DEFAULT NULL COMMENT 'Array of {type, severity, message}',
    ai_recommendations JSON DEFAULT NULL COMMENT 'Improvement suggestions',
    ai_priority_level ENUM('info', 'low', 'medium', 'high', 'critical') DEFAULT 'info',

    -- AI Follow-up Requests
    ai_follow_up_needed BOOLEAN DEFAULT FALSE,
    ai_follow_up_request TEXT DEFAULT NULL COMMENT 'What AI wants in follow-up photo',
    ai_follow_up_reason TEXT DEFAULT NULL COMMENT 'Why AI needs more info',

    -- Status & Classification
    status ENUM('uploaded', 'optimizing', 'optimized', 'queued', 'analyzing', 'analyzed', 'failed') DEFAULT 'uploaded',
    is_primary BOOLEAN DEFAULT FALSE COMMENT 'Primary/featured image',
    is_before BOOLEAN DEFAULT FALSE COMMENT 'Before photo',
    is_after BOOLEAN DEFAULT FALSE COMMENT 'After photo (for fixes)',
    image_type ENUM('overview', 'detail', 'issue', 'compliance', 'before', 'after', 'other') DEFAULT 'other',

    -- Quality Control
    blur_score DECIMAL(5,2) DEFAULT NULL COMMENT '0-100 (0=blurry, 100=sharp)',
    exposure_score DECIMAL(5,2) DEFAULT NULL COMMENT '0-100 (50=correct)',
    is_rejected BOOLEAN DEFAULT FALSE,
    rejection_reason VARCHAR(500) DEFAULT NULL,

    -- Error Handling
    ai_error_message TEXT DEFAULT NULL,
    ai_retry_count INT UNSIGNED DEFAULT 0,
    optimization_error_message TEXT DEFAULT NULL,

    -- Mobile & Offline
    uploaded_offline BOOLEAN DEFAULT FALSE,
    synced_at DATETIME DEFAULT NULL,

    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME DEFAULT NULL,

    -- Indexes
    INDEX idx_report (report_id),
    INDEX idx_checklist_item (checklist_item_id),
    INDEX idx_ai_request (ai_request_id),
    INDEX idx_ai_analyzed (ai_analyzed),
    INDEX idx_status (status),
    INDEX idx_uploaded_by (uploaded_by_user),
    INDEX idx_image_type (image_type),
    INDEX idx_priority (ai_priority_level),
    INDEX idx_follow_up (ai_follow_up_needed),
    INDEX idx_deleted (deleted_at),
    INDEX idx_report_status (report_id, status),

    -- Foreign Keys
    FOREIGN KEY (report_id) REFERENCES store_reports(id) ON DELETE CASCADE,
    FOREIGN KEY (checklist_item_id) REFERENCES store_report_items(id) ON DELETE SET NULL,
    FOREIGN KEY (ai_request_id) REFERENCES store_report_ai_requests(id) ON DELETE SET NULL

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Store report photos with optimization & comprehensive AI vision analysis';

-- ============================================================================
-- VOICE MEMOS (Audio recordings with transcription)
-- ============================================================================
CREATE TABLE IF NOT EXISTS store_report_voice_memos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    -- Relations
    report_id INT NOT NULL COMMENT 'FK to store_reports.id (INT to match existing table)',
    checklist_item_id INT DEFAULT NULL COMMENT 'Attached to specific question (INT to match existing store_report_items.id)',

    -- Audio File Info
    filename VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_size INT UNSIGNED DEFAULT NULL COMMENT 'Bytes',
    mime_type VARCHAR(100) DEFAULT NULL COMMENT 'audio/webm, audio/mp3, etc.',
    duration_seconds INT UNSIGNED DEFAULT NULL,

    -- Recording Metadata
    recorded_by_user INT UNSIGNED NOT NULL,
    recorded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    device_info JSON DEFAULT NULL COMMENT 'Device, browser, microphone info',

    -- Transcription
    transcription_status ENUM('pending', 'processing', 'completed', 'failed', 'skipped') DEFAULT 'pending',
    transcription_text MEDIUMTEXT DEFAULT NULL,
    transcription_confidence DECIMAL(5,2) DEFAULT NULL COMMENT '0-100',
    transcription_language VARCHAR(10) DEFAULT 'en',
    transcribed_at DATETIME DEFAULT NULL,
    transcription_service VARCHAR(50) DEFAULT NULL COMMENT 'whisper, google, azure, etc.',
    transcription_tokens_used INT UNSIGNED DEFAULT 0,

    -- AI Analysis of Transcript
    ai_analyzed BOOLEAN DEFAULT FALSE,
    ai_summary TEXT DEFAULT NULL COMMENT 'AI summary of voice memo content',
    ai_detected_concerns JSON DEFAULT NULL COMMENT 'Issues mentioned in memo',
    ai_action_items JSON DEFAULT NULL COMMENT 'Action items extracted',
    ai_sentiment VARCHAR(50) DEFAULT NULL COMMENT 'positive, neutral, negative, concerned',

    -- Playback & Access
    play_count INT UNSIGNED DEFAULT 0,
    last_played_at DATETIME DEFAULT NULL,

    -- Status
    status ENUM('recorded', 'uploaded', 'processing', 'ready', 'failed') DEFAULT 'recorded',
    is_private BOOLEAN DEFAULT FALSE COMMENT 'Manager-only access',

    -- Error Handling
    error_message TEXT DEFAULT NULL,
    retry_count INT UNSIGNED DEFAULT 0,

    -- Mobile & Offline
    recorded_offline BOOLEAN DEFAULT FALSE,
    synced_at DATETIME DEFAULT NULL,

    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME DEFAULT NULL,

    -- Indexes
    INDEX idx_report (report_id),
    INDEX idx_checklist_item (checklist_item_id),
    INDEX idx_recorded_by (recorded_by_user),
    INDEX idx_status (status),
    INDEX idx_transcription_status (transcription_status),
    INDEX idx_deleted (deleted_at),

    -- Foreign Keys
    FOREIGN KEY (report_id) REFERENCES store_reports(id) ON DELETE CASCADE,
    FOREIGN KEY (checklist_item_id) REFERENCES store_report_items(id) ON DELETE SET NULL

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Voice memos with transcription & AI analysis';

-- ============================================================================
-- AI REQUESTS (AI asks for follow-up photos/clarifications)
-- ============================================================================
CREATE TABLE IF NOT EXISTS store_report_ai_requests (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    -- Relations
    report_id INT NOT NULL COMMENT 'FK to store_reports.id (INT to match existing table)',
    trigger_image_id INT DEFAULT NULL COMMENT 'Image that triggered request',
    checklist_item_id INT DEFAULT NULL COMMENT 'Related checklist item',

    -- Request Details
    request_type ENUM('clarification', 'close_up', 'different_angle', 'specific_area', 'follow_up', 'compliance', 'safety_concern', 'verify_fix') DEFAULT 'clarification',
    priority ENUM('optional', 'low', 'medium', 'high', 'critical') DEFAULT 'medium',
    request_title VARCHAR(500) NOT NULL,
    request_description TEXT NOT NULL COMMENT 'Detailed explanation',
    ai_reasoning TEXT DEFAULT NULL COMMENT 'Why AI is requesting this',

    -- AI Prompt Context
    ai_detected_issue TEXT DEFAULT NULL COMMENT 'What issue triggered this',
    ai_expected_resolution TEXT DEFAULT NULL COMMENT 'What would satisfy AI',
    ai_example_description TEXT DEFAULT NULL COMMENT 'Example of good response',

    -- Response Status
    status ENUM('pending', 'acknowledged', 'fulfilled', 'partially_fulfilled', 'skipped', 'cannot_fulfill', 'expired') DEFAULT 'pending',
    fulfilled_by_image_id INT DEFAULT NULL COMMENT 'Image that fulfilled request',
    fulfilled_by_voice_memo_id INT UNSIGNED DEFAULT NULL COMMENT 'Or voice explanation',
    fulfilled_at DATETIME DEFAULT NULL,
    staff_response_note TEXT DEFAULT NULL COMMENT 'Staff explanation',

    -- AI Follow-up Analysis
    ai_satisfied BOOLEAN DEFAULT NULL COMMENT 'Was AI satisfied with response?',
    ai_satisfaction_score DECIMAL(5,2) DEFAULT NULL COMMENT '0-100',
    ai_satisfaction_note TEXT DEFAULT NULL,
    ai_still_concerned BOOLEAN DEFAULT FALSE,
    ai_escalation_reason TEXT DEFAULT NULL COMMENT 'Why AI wants manager review',

    -- Notification
    notification_sent BOOLEAN DEFAULT FALSE,
    notified_at DATETIME DEFAULT NULL,
    notification_read BOOLEAN DEFAULT FALSE,

    -- Expiration
    expires_at DATETIME DEFAULT NULL COMMENT 'Auto-expire old requests',

    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Indexes
    INDEX idx_report (report_id),
    INDEX idx_status (status),
    INDEX idx_priority (priority),
    INDEX idx_trigger_image (trigger_image_id),
    INDEX idx_checklist_item (checklist_item_id),
    INDEX idx_notification (notification_sent, notification_read),
    INDEX idx_expires (expires_at),

    -- Foreign Keys
    FOREIGN KEY (report_id) REFERENCES store_reports(id) ON DELETE CASCADE,
    FOREIGN KEY (trigger_image_id) REFERENCES store_report_images(id) ON DELETE SET NULL,
    FOREIGN KEY (checklist_item_id) REFERENCES store_report_items(id) ON DELETE SET NULL,
    FOREIGN KEY (fulfilled_by_image_id) REFERENCES store_report_images(id) ON DELETE SET NULL,
    FOREIGN KEY (fulfilled_by_voice_memo_id) REFERENCES store_report_voice_memos(id) ON DELETE SET NULL

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='AI-generated requests for additional evidence or clarification';

-- ============================================================================
-- AI CONVERSATIONS (Interactive Q&A between AI and staff)
-- ============================================================================
CREATE TABLE IF NOT EXISTS store_report_ai_conversations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    -- Relations (INT to match existing table types)
    report_id INT NOT NULL COMMENT 'FK to store_reports.id',
    checklist_item_id INT DEFAULT NULL COMMENT 'FK to store_report_items.id',
    ai_request_id INT DEFAULT NULL COMMENT 'FK to store_report_ai_requests.id',

    -- Message
    message_from ENUM('ai', 'staff', 'system') NOT NULL,
    user_id INT UNSIGNED DEFAULT NULL COMMENT 'If from staff',
    message_text MEDIUMTEXT NOT NULL,
    message_context JSON DEFAULT NULL COMMENT 'Referenced images, scores, etc.',

    -- AI Metadata
    ai_intent VARCHAR(100) DEFAULT NULL COMMENT 'question, clarification, suggestion, etc.',
    ai_confidence DECIMAL(5,2) DEFAULT NULL,
    ai_tokens_used INT UNSIGNED DEFAULT 0,

    -- Threading
    parent_message_id INT UNSIGNED DEFAULT NULL COMMENT 'Reply to message',
    thread_id INT UNSIGNED DEFAULT NULL COMMENT 'Conversation thread',

    -- Status
    requires_response BOOLEAN DEFAULT FALSE,
    responded_at DATETIME DEFAULT NULL,
    is_resolved BOOLEAN DEFAULT FALSE,

    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    -- Indexes
    INDEX idx_report (report_id),
    INDEX idx_checklist_item (checklist_item_id),
    INDEX idx_ai_request (ai_request_id),
    INDEX idx_message_from (message_from),
    INDEX idx_parent (parent_message_id),
    INDEX idx_thread (thread_id),
    INDEX idx_requires_response (requires_response),
    INDEX idx_created (created_at),

    -- Foreign Keys
    FOREIGN KEY (report_id) REFERENCES store_reports(id) ON DELETE CASCADE,
    FOREIGN KEY (checklist_item_id) REFERENCES store_report_items(id) ON DELETE SET NULL,
    FOREIGN KEY (ai_request_id) REFERENCES store_report_ai_requests(id) ON DELETE SET NULL,
    FOREIGN KEY (parent_message_id) REFERENCES store_report_ai_conversations(id) ON DELETE CASCADE

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Interactive AI-Staff conversation log';

-- ============================================================================
-- AUTOSAVE CHECKPOINTS (Recovery system)
-- ============================================================================
CREATE TABLE IF NOT EXISTS store_report_autosave_checkpoints (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    -- Relations
    report_id INT NOT NULL COMMENT 'FK to store_reports.id',
    user_id INT UNSIGNED NOT NULL,

    -- Checkpoint Data
    checkpoint_data JSON NOT NULL COMMENT 'Complete state snapshot',
    checkpoint_hash VARCHAR(64) NOT NULL COMMENT 'SHA256 of data for deduplication',

    -- Context
    session_id VARCHAR(255) DEFAULT NULL,
    device_id VARCHAR(255) DEFAULT NULL,
    page_url VARCHAR(500) DEFAULT NULL,
    scroll_position INT DEFAULT NULL,

    -- Metadata
    items_completed INT UNSIGNED DEFAULT 0,
    completion_percentage DECIMAL(5,2) DEFAULT 0.00,
    has_unsaved_changes BOOLEAN DEFAULT FALSE,

    -- Recovery
    is_recovery_point BOOLEAN DEFAULT FALSE COMMENT 'Marked as safe recovery point',
    recovered_from BOOLEAN DEFAULT FALSE COMMENT 'Was this checkpoint used for recovery?',
    recovered_at DATETIME DEFAULT NULL,

    -- Cleanup
    expires_at DATETIME DEFAULT NULL COMMENT 'Auto-cleanup old checkpoints',

    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    -- Indexes
    INDEX idx_report (report_id),
    INDEX idx_user (user_id),
    INDEX idx_session (session_id),
    INDEX idx_hash (checkpoint_hash),
    INDEX idx_recovery (is_recovery_point),
    INDEX idx_expires (expires_at),
    INDEX idx_created (created_at),

    -- Foreign Keys
    FOREIGN KEY (report_id) REFERENCES store_reports(id) ON DELETE CASCADE

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Autosave checkpoints for recovery & conflict resolution';

-- ============================================================================
-- MOBILE SESSIONS (Track mobile device sessions)
-- ============================================================================
CREATE TABLE IF NOT EXISTS store_report_mobile_sessions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    -- Session Info
    session_id VARCHAR(255) NOT NULL UNIQUE,
    device_id VARCHAR(255) NOT NULL,
    user_id INT UNSIGNED NOT NULL,

    -- Device Info
    device_type ENUM('mobile', 'tablet', 'desktop', 'unknown') DEFAULT 'unknown',
    os VARCHAR(50) DEFAULT NULL COMMENT 'iOS, Android, etc.',
    os_version VARCHAR(50) DEFAULT NULL,
    browser VARCHAR(50) DEFAULT NULL,
    browser_version VARCHAR(50) DEFAULT NULL,
    screen_width INT UNSIGNED DEFAULT NULL,
    screen_height INT UNSIGNED DEFAULT NULL,
    is_pwa BOOLEAN DEFAULT FALSE COMMENT 'Installed as PWA',

    -- Network
    connection_type VARCHAR(50) DEFAULT NULL COMMENT '4g, wifi, etc.',
    is_online BOOLEAN DEFAULT TRUE,
    last_online_at DATETIME DEFAULT NULL,

    -- Active Report
    current_report_id INT DEFAULT NULL,

    -- Session Stats
    reports_created INT UNSIGNED DEFAULT 0,
    reports_completed INT UNSIGNED DEFAULT 0,
    photos_uploaded INT UNSIGNED DEFAULT 0,
    voice_memos_recorded INT UNSIGNED DEFAULT 0,

    -- Sync Status
    pending_sync_items INT UNSIGNED DEFAULT 0,
    last_sync_at DATETIME DEFAULT NULL,
    sync_errors INT UNSIGNED DEFAULT 0,

    -- Location
    last_latitude DECIMAL(10,8) DEFAULT NULL,
    last_longitude DECIMAL(11,8) DEFAULT NULL,
    last_location_update_at DATETIME DEFAULT NULL,

    -- Session Management
    started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_activity_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    ended_at DATETIME DEFAULT NULL,
    is_active BOOLEAN DEFAULT TRUE,

    -- Indexes
    INDEX idx_session (session_id),
    INDEX idx_device (device_id),
    INDEX idx_user (user_id),
    INDEX idx_active (is_active),
    INDEX idx_last_activity (last_activity_at),
    INDEX idx_current_report (current_report_id),

    -- Foreign Keys
    FOREIGN KEY (current_report_id) REFERENCES store_reports(id) ON DELETE SET NULL

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Mobile device session tracking for offline support';

-- ============================================================================
-- PHOTO OPTIMIZATION QUEUE (Background processing)
-- ============================================================================
CREATE TABLE IF NOT EXISTS store_report_photo_optimization_queue (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    -- Relations
    image_id INT NOT NULL,
    report_id INT NOT NULL COMMENT 'FK to store_reports.id',

    -- Queue Status
    status ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending',
    priority INT UNSIGNED DEFAULT 5 COMMENT '1=highest, 10=lowest',
    attempts INT UNSIGNED DEFAULT 0,
    max_attempts INT UNSIGNED DEFAULT 3,

    -- Processing
    started_at DATETIME DEFAULT NULL,
    completed_at DATETIME DEFAULT NULL,
    processing_duration_ms INT UNSIGNED DEFAULT NULL,
    worker_id VARCHAR(100) DEFAULT NULL COMMENT 'Which worker processed this',

    -- Optimization Tasks
    tasks_required JSON DEFAULT NULL COMMENT '["resize", "webp", "thumbnail", "exif"]',
    tasks_completed JSON DEFAULT NULL,

    -- Results
    original_size INT UNSIGNED DEFAULT NULL,
    optimized_size INT UNSIGNED DEFAULT NULL,
    size_reduction_bytes INT UNSIGNED DEFAULT NULL,
    size_reduction_percent DECIMAL(5,2) DEFAULT NULL,

    -- Error Handling
    error_message TEXT DEFAULT NULL,
    last_error_at DATETIME DEFAULT NULL,

    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Indexes
    INDEX idx_image (image_id),
    INDEX idx_report (report_id),
    INDEX idx_status (status),
    INDEX idx_priority_status (priority, status),
    INDEX idx_created (created_at),

    -- Foreign Keys
    FOREIGN KEY (image_id) REFERENCES store_report_images(id) ON DELETE CASCADE,
    FOREIGN KEY (report_id) REFERENCES store_reports(id) ON DELETE CASCADE

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Background queue for photo optimization tasks';

-- ============================================================================
-- REPORT HISTORY / AUDIT LOG (Enhanced)
-- ============================================================================
CREATE TABLE IF NOT EXISTS store_report_history (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    -- Relations
    report_id INT NOT NULL COMMENT 'FK to store_reports.id',
    user_id INT UNSIGNED DEFAULT NULL COMMENT 'NULL for system/AI actions',

    -- Change Details
    action_type ENUM(
        'created', 'updated', 'submitted', 'autosaved',
        'ai_analyzed', 'ai_questioned', 'ai_satisfied',
        'reviewed', 'approved', 'rejected',
        'score_changed', 'grade_changed',
        'image_added', 'image_analyzed', 'image_optimized',
        'voice_memo_added', 'voice_memo_transcribed',
        'ai_request_created', 'ai_request_fulfilled',
        'follow_up_required', 'follow_up_completed',
        'comment_added', 'status_changed',
        'recovered', 'synced', 'conflict_resolved'
    ) NOT NULL,

    entity_type ENUM('report', 'item', 'image', 'voice_memo', 'ai_request', 'conversation', 'checkpoint') DEFAULT 'report',
    entity_id INT UNSIGNED DEFAULT NULL,

    field_changed VARCHAR(100) DEFAULT NULL,
    old_value MEDIUMTEXT DEFAULT NULL,
    new_value MEDIUMTEXT DEFAULT NULL,
    description TEXT DEFAULT NULL COMMENT 'Human-readable description',
    change_context JSON DEFAULT NULL COMMENT 'Additional metadata',

    -- Device & Network
    device_type VARCHAR(50) DEFAULT NULL,
    device_id VARCHAR(255) DEFAULT NULL,
    session_id VARCHAR(255) DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent TEXT DEFAULT NULL,

    -- Location (if available)
    gps_latitude DECIMAL(10,8) DEFAULT NULL,
    gps_longitude DECIMAL(11,8) DEFAULT NULL,

    -- Timestamp
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    -- Indexes
    INDEX idx_report (report_id),
    INDEX idx_user (user_id),
    INDEX idx_action (action_type),
    INDEX idx_entity (entity_type, entity_id),
    INDEX idx_created (created_at),
    INDEX idx_report_created (report_id, created_at),
    INDEX idx_session (session_id),

    -- Foreign Keys
    FOREIGN KEY (report_id) REFERENCES store_reports(id) ON DELETE CASCADE

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Complete audit trail with mobile & AI tracking';

-- ============================================================================
-- ANALYTICS & REPORTING VIEWS
-- ============================================================================

-- Store Performance Benchmarks
CREATE OR REPLACE VIEW vw_store_report_benchmarks AS
SELECT
    vo.id AS outlet_id,
    vo.name AS outlet_name,

    -- Latest Report
    (SELECT overall_score FROM store_reports WHERE outlet_id = vo.id AND deleted_at IS NULL ORDER BY report_date DESC LIMIT 1) AS latest_score,
    (SELECT staff_score FROM store_reports WHERE outlet_id = vo.id AND deleted_at IS NULL ORDER BY report_date DESC LIMIT 1) AS latest_staff_score,
    (SELECT ai_score FROM store_reports WHERE outlet_id = vo.id AND deleted_at IS NULL ORDER BY report_date DESC LIMIT 1) AS latest_ai_score,
    (SELECT grade FROM store_reports WHERE outlet_id = vo.id AND deleted_at IS NULL ORDER BY report_date DESC LIMIT 1) AS latest_grade,
    (SELECT report_date FROM store_reports WHERE outlet_id = vo.id AND deleted_at IS NULL ORDER BY report_date DESC LIMIT 1) AS latest_report_date,

    -- Statistics (Last 90 days)
    COUNT(CASE WHEN sr.report_date >= DATE_SUB(NOW(), INTERVAL 90 DAY) THEN sr.id END) AS reports_last_90_days,
    AVG(CASE WHEN sr.report_date >= DATE_SUB(NOW(), INTERVAL 90 DAY) THEN sr.overall_score END) AS avg_score_90d,
    AVG(CASE WHEN sr.report_date >= DATE_SUB(NOW(), INTERVAL 90 DAY) THEN sr.staff_score END) AS avg_staff_score_90d,
    AVG(CASE WHEN sr.report_date >= DATE_SUB(NOW(), INTERVAL 90 DAY) THEN sr.ai_score END) AS avg_ai_score_90d,

    -- All-time Stats
    COUNT(sr.id) AS total_reports,
    AVG(sr.overall_score) AS avg_score_alltime,
    MAX(sr.overall_score) AS best_score,
    MIN(sr.overall_score) AS worst_score,
    STDDEV(sr.overall_score) AS score_std_dev,

    -- AI Insights
    AVG(sr.ai_confidence_score) AS avg_ai_confidence,
    AVG(sr.score_variance) AS avg_staff_ai_variance,
    SUM(CASE WHEN sr.ai_analysis_status = 'completed' THEN 1 ELSE 0 END) AS ai_analyzed_count,
    SUM(sr.critical_issues_count) AS total_critical_issues,

    -- Trend (last 3 vs previous 3)
    (SELECT AVG(overall_score) FROM store_reports WHERE outlet_id = vo.id AND deleted_at IS NULL ORDER BY report_date DESC LIMIT 3) AS recent_avg,
    (SELECT AVG(overall_score) FROM store_reports WHERE outlet_id = vo.id AND deleted_at IS NULL ORDER BY report_date DESC LIMIT 3 OFFSET 3) AS previous_avg,

    -- Follow-ups
    SUM(CASE WHEN sr.requires_follow_up = 1 AND sr.follow_up_completed_at IS NULL THEN 1 ELSE 0 END) AS pending_follow_ups,

    -- Rankings
    RANK() OVER (ORDER BY (SELECT overall_score FROM store_reports WHERE outlet_id = vo.id AND deleted_at IS NULL ORDER BY report_date DESC LIMIT 1) DESC) AS current_rank

FROM vend_outlets vo
LEFT JOIN store_reports sr ON vo.id = sr.outlet_id AND sr.deleted_at IS NULL
GROUP BY vo.id, vo.name;

-- AI Performance Metrics
CREATE OR REPLACE VIEW vw_ai_analysis_metrics AS
SELECT
    DATE(ai_analysis_completed_at) AS analysis_date,
    COUNT(*) AS total_analyses,
    AVG(ai_analysis_duration_ms / 1000) AS avg_duration_seconds,
    AVG(ai_confidence_score) AS avg_confidence,
    AVG(ai_tokens_used) AS avg_tokens_used,
    SUM(ai_tokens_used) AS total_tokens_used,
    AVG(score_variance) AS avg_staff_ai_variance,
    SUM(CASE WHEN ai_analysis_status = 'completed' THEN 1 ELSE 0 END) AS successful_analyses,
    SUM(CASE WHEN ai_analysis_status = 'failed' THEN 1 ELSE 0 END) AS failed_analyses,
    AVG(images_analyzed) AS avg_images_per_report,
    AVG(ai_questions_asked) AS avg_questions_asked,
    AVG(ai_photos_requested) AS avg_photos_requested,
    SUM(CASE WHEN ai_risk_assessment = 'critical' THEN 1 WHEN ai_risk_assessment = 'high' THEN 1 ELSE 0 END) AS high_risk_count
FROM store_reports
WHERE ai_analysis_started_at IS NOT NULL
GROUP BY DATE(ai_analysis_completed_at)
ORDER BY analysis_date DESC;

-- Mobile Usage Stats
CREATE OR REPLACE VIEW vw_mobile_usage_stats AS
SELECT
    DATE(last_activity_at) AS usage_date,
    device_type,
    os,
    COUNT(DISTINCT user_id) AS unique_users,
    COUNT(DISTINCT session_id) AS total_sessions,
    AVG(reports_completed) AS avg_reports_per_session,
    AVG(photos_uploaded) AS avg_photos_per_session,
    AVG(voice_memos_recorded) AS avg_voice_memos_per_session,
    SUM(CASE WHEN is_pwa = 1 THEN 1 ELSE 0 END) AS pwa_sessions,
    AVG(pending_sync_items) AS avg_pending_sync,
    SUM(sync_errors) AS total_sync_errors
FROM store_report_mobile_sessions
WHERE last_activity_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
GROUP BY DATE(last_activity_at), device_type, os
ORDER BY usage_date DESC, device_type;

-- ============================================================================
-- INDEXES FOR PERFORMANCE (Additional Composite Indexes)
-- ============================================================================

-- Report queries by outlet and date range
CREATE INDEX idx_report_outlet_date_range ON store_reports(outlet_id, report_date, status, deleted_at);

-- Image optimization queries
CREATE INDEX idx_image_optimization ON store_report_images(status, ai_analyzed, report_id);

-- AI request fulfillment tracking
CREATE INDEX idx_ai_request_fulfillment ON store_report_ai_requests(status, priority, created_at);

-- Voice memo transcription queue
CREATE INDEX idx_voice_transcription ON store_report_voice_memos(transcription_status, created_at);

-- Autosave recovery
CREATE INDEX idx_autosave_recovery ON store_report_autosave_checkpoints(report_id, is_recovery_point, created_at);

-- Mobile session management
CREATE INDEX idx_mobile_active_sessions ON store_report_mobile_sessions(user_id, is_active, last_activity_at);

-- Audit log queries
CREATE INDEX idx_history_timeline ON store_report_history(report_id, action_type, created_at);

-- ============================================================================
-- SCHEMA VERSION TRACKING
-- ============================================================================
CREATE TABLE IF NOT EXISTS store_reports_schema_version (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    version VARCHAR(20) NOT NULL UNIQUE,
    description TEXT,
    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT INTO store_reports_schema_version (version, description)
VALUES
    ('2.0.0', 'Enterprise-grade schema with mobile, AI, voice memos, autosave, versioning')
ON DUPLICATE KEY UPDATE description = VALUES(description);

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================================
-- END OF SCHEMA
-- ============================================================================

-- Next Steps:
-- 1. Run migration script: database/migrate_legacy_data_v2.php
-- 2. Seed checklist: database/seed_checklist_v2.php
-- 3. Configure AI services: config/ai_config.php
-- 4. Set up photo optimization worker: scripts/photo_optimizer_worker.php
-- 5. Deploy mobile PWA manifest: public/manifest.json
