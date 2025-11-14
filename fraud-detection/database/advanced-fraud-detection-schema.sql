-- =====================================================
-- Advanced Fraud Detection System - Database Schema
-- Features: ML Prediction, Computer Vision, NLP, Customer Collusion, Digital Twins
-- Version: 2.0.0
-- Created: 2025-11-14
-- =====================================================

-- =====================================================
-- PREDICTIVE FRAUD FORECASTING TABLES
-- =====================================================

-- Store ML fraud predictions (30-day forecasts)
CREATE TABLE IF NOT EXISTS predictive_fraud_forecasts (
    forecast_id INT AUTO_INCREMENT PRIMARY KEY,
    staff_id INT NOT NULL,
    prediction_date DATE NOT NULL,
    forecast_period_days INT DEFAULT 30,
    fraud_probability DECIMAL(5,3) NOT NULL,
    risk_level ENUM('MINIMAL', 'LOW', 'MEDIUM', 'HIGH', 'CRITICAL') NOT NULL,
    confidence_score DECIMAL(5,3),

    -- Feature scores (7 dimensions)
    discount_escalation_score DECIMAL(5,3),
    after_hours_score DECIMAL(5,3),
    behavioral_deviation_score DECIMAL(5,3),
    financial_stress_score DECIMAL(5,3),
    peer_influence_score DECIMAL(5,3),
    life_events_score DECIMAL(5,3),
    historical_patterns_score DECIMAL(5,3),

    -- Risk trajectory
    risk_trajectory JSON, -- {current, trend, velocity, days_to_critical}

    -- Recommended interventions
    interventions JSON, -- Array of {level, action, priority, timeline}

    model_version VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_staff_date (staff_id, prediction_date),
    INDEX idx_risk_level (risk_level),
    INDEX idx_created (created_at),

    FOREIGN KEY (staff_id) REFERENCES staff(staff_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Staff financial indicators for ML model
CREATE TABLE IF NOT EXISTS staff_financial_indicators (
    indicator_id INT AUTO_INCREMENT PRIMARY KEY,
    staff_id INT NOT NULL,
    indicator_date DATE NOT NULL,

    -- Financial stress indicators
    credit_score INT,
    credit_score_change INT,
    payday_loan_detected BOOLEAN DEFAULT FALSE,
    debt_collection_activity BOOLEAN DEFAULT FALSE,
    bankruptcy_filing BOOLEAN DEFAULT FALSE,

    -- Behavioral financial indicators
    payroll_advance_requests INT DEFAULT 0,
    shift_pickup_frequency DECIMAL(5,3), -- Picking up extra shifts
    personal_call_frequency INT, -- Financial stress calls

    -- External data sources
    data_sources JSON, -- {credit_bureau, internal_hr, external_api}
    confidence_level DECIMAL(5,3),

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY unique_staff_date (staff_id, indicator_date),
    INDEX idx_created (created_at),

    FOREIGN KEY (staff_id) REFERENCES staff(staff_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Staff peer interactions for influence detection
CREATE TABLE IF NOT EXISTS staff_interactions (
    interaction_id INT AUTO_INCREMENT PRIMARY KEY,
    staff_id INT NOT NULL,
    peer_staff_id INT NOT NULL,
    interaction_date DATETIME NOT NULL,

    interaction_type ENUM('shift_overlap', 'communication', 'social_event', 'training', 'other'),
    interaction_duration_minutes INT,
    location VARCHAR(100),

    -- Relationship indicators
    relationship_strength DECIMAL(5,3), -- 0-1 scale
    relationship_type ENUM('work_only', 'friendly', 'close_friends', 'family', 'romantic', 'unknown'),

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_staff_peer (staff_id, peer_staff_id),
    INDEX idx_date (interaction_date),

    FOREIGN KEY (staff_id) REFERENCES staff(staff_id) ON DELETE CASCADE,
    FOREIGN KEY (peer_staff_id) REFERENCES staff(staff_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Staff life events for stress detection
CREATE TABLE IF NOT EXISTS staff_life_events (
    event_id INT AUTO_INCREMENT PRIMARY KEY,
    staff_id INT NOT NULL,
    event_date DATE NOT NULL,

    event_type ENUM('marriage', 'divorce', 'new_child', 'medical_issue', 'family_death',
                    'home_purchase', 'legal_issue', 'addiction', 'relationship_change', 'other'),
    event_severity ENUM('low', 'medium', 'high', 'critical'),
    stress_level INT, -- 1-10 scale

    -- Event details
    description TEXT,
    support_provided BOOLEAN DEFAULT FALSE,
    ongoing BOOLEAN DEFAULT TRUE,

    -- Source
    reported_by ENUM('self', 'manager', 'hr', 'peer', 'system'),
    confidential BOOLEAN DEFAULT TRUE,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_staff_date (staff_id, event_date),
    INDEX idx_event_type (event_type),
    INDEX idx_ongoing (ongoing),

    FOREIGN KEY (staff_id) REFERENCES staff(staff_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Historical fraud pattern library for matching
CREATE TABLE IF NOT EXISTS fraud_pattern_library (
    pattern_id INT AUTO_INCREMENT PRIMARY KEY,
    pattern_name VARCHAR(100) NOT NULL,
    pattern_category ENUM('discount_fraud', 'theft', 'refund_fraud', 'time_fraud',
                          'inventory_fraud', 'cash_handling', 'collusion', 'other'),

    -- Pattern characteristics
    behavioral_signature JSON, -- Pattern fingerprint
    feature_weights JSON, -- Which features are most important

    -- Historical data
    occurrence_count INT DEFAULT 1,
    detection_rate DECIMAL(5,3),
    false_positive_rate DECIMAL(5,3),

    -- Pattern details
    description TEXT,
    warning_signs JSON,
    typical_duration_days INT,
    typical_loss_amount DECIMAL(10,2),

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY unique_pattern (pattern_name),
    INDEX idx_category (pattern_category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- COMPUTER VISION BEHAVIORAL ANALYSIS TABLES
-- =====================================================

-- 30-day behavioral baselines per staff member
CREATE TABLE IF NOT EXISTS cv_behavioral_baselines (
    baseline_id INT AUTO_INCREMENT PRIMARY KEY,
    staff_id INT NOT NULL,

    -- Baseline period
    baseline_start_date DATE NOT NULL,
    baseline_end_date DATE NOT NULL,
    detection_count INT NOT NULL, -- Number of detections used

    -- Behavioral category baselines (averages and distributions)
    stress_signals_baseline JSON, -- {avg, stddev, distribution}
    concealment_baseline JSON,
    awareness_baseline JSON,
    transaction_anomalies_baseline JSON,

    -- Overall behavioral signature
    behavioral_signature VARCHAR(64), -- SHA256 hash

    -- Quality metrics
    data_quality_score DECIMAL(5,3),
    confidence_level DECIMAL(5,3),

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    next_recalibration DATE,

    INDEX idx_staff (staff_id),
    INDEX idx_created (created_at),

    FOREIGN KEY (staff_id) REFERENCES staff(staff_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Raw computer vision detections (from Python pipeline)
CREATE TABLE IF NOT EXISTS cv_behavioral_detections (
    detection_id BIGINT AUTO_INCREMENT PRIMARY KEY,
    staff_id INT NOT NULL,
    camera_id INT NOT NULL,
    detection_timestamp DATETIME NOT NULL,

    -- Frame information
    frame_id VARCHAR(100),
    video_stream_id VARCHAR(100),

    -- Detection categories (confidence scores 0-1)
    stress_signals JSON, -- {sweating: 0.85, fidgeting: 0.72, ...}
    concealment_behaviors JSON,
    camera_awareness JSON,
    transaction_anomalies JSON,

    -- ML model outputs
    emotion_detected VARCHAR(50),
    emotion_confidence DECIMAL(5,3),
    pose_data JSON,
    gaze_direction VARCHAR(50),
    action_detected VARCHAR(100),

    -- Processing metadata
    processing_latency_ms INT,
    model_version VARCHAR(20),
    gpu_used BOOLEAN DEFAULT TRUE,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_staff_timestamp (staff_id, detection_timestamp),
    INDEX idx_camera_timestamp (camera_id, detection_timestamp),
    INDEX idx_created (created_at),

    FOREIGN KEY (staff_id) REFERENCES staff(staff_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Analyzed behavioral results (aggregated from detections)
CREATE TABLE IF NOT EXISTS cv_analysis_results (
    analysis_id INT AUTO_INCREMENT PRIMARY KEY,
    staff_id INT NOT NULL,
    analysis_date DATE NOT NULL,
    analysis_period ENUM('hourly', 'daily', 'shift') NOT NULL,

    -- Behavioral scores (0-1 scale)
    stress_score DECIMAL(5,3),
    concealment_score DECIMAL(5,3),
    awareness_score DECIMAL(5,3),
    transaction_anomaly_score DECIMAL(5,3),

    -- Composite risk score
    behavioral_risk_score DECIMAL(5,3),
    risk_level ENUM('MINIMAL', 'LOW', 'MEDIUM', 'HIGH', 'CRITICAL'),

    -- Baseline comparison
    baseline_deviation_sigma DECIMAL(5,2),
    significantly_anomalous BOOLEAN DEFAULT FALSE,

    -- Top indicators
    top_stress_indicators JSON,
    top_concealment_indicators JSON,
    top_awareness_indicators JSON,

    -- Metadata
    detection_count INT,
    camera_count INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY unique_staff_period (staff_id, analysis_date, analysis_period),
    INDEX idx_risk_level (risk_level),
    INDEX idx_anomalous (significantly_anomalous),

    FOREIGN KEY (staff_id) REFERENCES staff(staff_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Behavioral alerts generated by CV system
CREATE TABLE IF NOT EXISTS cv_behavioral_alerts (
    alert_id INT AUTO_INCREMENT PRIMARY KEY,
    staff_id INT NOT NULL,
    alert_timestamp DATETIME NOT NULL,

    alert_type ENUM('STRESS', 'CONCEALMENT', 'AWARENESS', 'TRANSACTION_ANOMALY', 'BASELINE_DEVIATION'),
    severity ENUM('LOW', 'MEDIUM', 'HIGH', 'CRITICAL'),

    -- Alert details
    behavioral_score DECIMAL(5,3),
    baseline_deviation_sigma DECIMAL(5,2),
    top_indicators JSON,

    -- Evidence
    camera_ids JSON, -- Cameras that captured behavior
    frame_ids JSON, -- Key frame IDs
    video_clips JSON, -- Paths to video evidence

    -- Response
    alert_message TEXT,
    recommended_action TEXT,
    acknowledged BOOLEAN DEFAULT FALSE,
    acknowledged_by INT,
    acknowledged_at DATETIME,

    -- Investigation
    investigated BOOLEAN DEFAULT FALSE,
    investigation_result ENUM('false_positive', 'confirmed', 'inconclusive', 'pending'),
    investigation_notes TEXT,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_staff_timestamp (staff_id, alert_timestamp),
    INDEX idx_severity (severity),
    INDEX idx_acknowledged (acknowledged),
    INDEX idx_investigated (investigated),

    FOREIGN KEY (staff_id) REFERENCES staff(staff_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- NLP COMMUNICATION ANALYSIS TABLES
-- =====================================================

-- Communication analysis results
CREATE TABLE IF NOT EXISTS communication_analysis (
    analysis_id BIGINT AUTO_INCREMENT PRIMARY KEY,
    staff_id INT NOT NULL,
    message_id VARCHAR(255) NOT NULL, -- External message ID

    -- Message metadata
    platform ENUM('microsoft_365', 'google_workspace', 'slack', 'internal_messaging', 'sms', 'other'),
    message_type ENUM('email', 'chat', 'teams_message', 'direct_message', 'channel_message'),
    message_date DATETIME NOT NULL,

    -- Recipients
    recipient_ids JSON, -- Array of staff IDs or external contacts
    external_recipient BOOLEAN DEFAULT FALSE,

    -- Analysis results
    risk_score DECIMAL(5,3),
    risk_level ENUM('MINIMAL', 'LOW', 'MEDIUM', 'HIGH', 'CRITICAL'),

    -- Pattern detection (7 categories)
    pattern_scores JSON, -- {collusion: 0.85, evidence_destruction: 0.72, ...}
    patterns_detected JSON, -- Array of pattern names

    -- Code word detection
    code_words_detected JSON, -- Array of detected code words
    code_word_score DECIMAL(5,3),

    -- Sentiment analysis
    sentiment_score DECIMAL(5,3), -- -1 to +1
    sentiment_label ENUM('very_negative', 'negative', 'neutral', 'positive', 'very_positive'),

    -- Context analysis
    context_flags JSON, -- {off_hours: true, weekend: true, external: false}
    context_score DECIMAL(5,3),

    -- Collusion detection
    collusion_indicators JSON,
    potential_collusion_group JSON, -- Array of involved staff IDs

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY unique_message (platform, message_id),
    INDEX idx_staff_date (staff_id, message_date),
    INDEX idx_risk_level (risk_level),
    INDEX idx_created (created_at),

    FOREIGN KEY (staff_id) REFERENCES staff(staff_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Preserved communication evidence (CRITICAL messages)
CREATE TABLE IF NOT EXISTS communication_evidence (
    evidence_id INT AUTO_INCREMENT PRIMARY KEY,
    analysis_id BIGINT NOT NULL,
    staff_id INT NOT NULL,

    -- Preserved data
    message_content_encrypted BLOB, -- Encrypted message content
    message_metadata JSON, -- Headers, timestamps, etc.

    -- Evidence chain
    evidence_hash VARCHAR(64), -- SHA256 of original content
    preserved_at DATETIME NOT NULL,
    retention_expires_at DATETIME NOT NULL, -- 2 years for CRITICAL

    -- Legal hold
    legal_hold BOOLEAN DEFAULT FALSE,
    legal_hold_reason TEXT,
    legal_hold_expires_at DATETIME,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_analysis (analysis_id),
    INDEX idx_staff (staff_id),
    INDEX idx_expires (retention_expires_at),
    INDEX idx_legal_hold (legal_hold),

    FOREIGN KEY (analysis_id) REFERENCES communication_analysis(analysis_id) ON DELETE CASCADE,
    FOREIGN KEY (staff_id) REFERENCES staff(staff_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dynamic fraud pattern library (learns from communications)
CREATE TABLE IF NOT EXISTS communication_fraud_patterns (
    pattern_id INT AUTO_INCREMENT PRIMARY KEY,
    pattern_name VARCHAR(100) NOT NULL,
    pattern_category ENUM('collusion', 'evidence_destruction', 'off_hours_planning',
                          'financial_stress', 'resentment', 'external_coordination', 'discount_abuse'),

    -- Pattern keywords
    keywords JSON, -- Array of {keyword, weight}
    keyword_combinations JSON, -- Multi-word patterns

    -- Detection statistics
    detection_count INT DEFAULT 0,
    true_positive_count INT DEFAULT 0,
    false_positive_count INT DEFAULT 0,
    precision_score DECIMAL(5,3),

    -- Pattern evolution
    last_detected_at DATETIME,
    pattern_active BOOLEAN DEFAULT TRUE,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_category (pattern_category),
    INDEX idx_active (pattern_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- CUSTOMER LOYALTY COLLUSION DETECTION TABLES
-- =====================================================

-- Customer-staff collusion analysis results
CREATE TABLE IF NOT EXISTS customer_collusion_analysis (
    analysis_id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    staff_id INT NOT NULL,

    -- Collusion scores
    collusion_score DECIMAL(5,3) NOT NULL,
    risk_level ENUM('MINIMAL', 'LOW', 'MEDIUM', 'HIGH', 'CRITICAL') NOT NULL,

    -- Component scores
    relationship_score DECIMAL(5,3),
    transaction_frequency_score DECIMAL(5,3),
    discount_anomaly_score DECIMAL(5,3),
    return_pattern_score DECIMAL(5,3),

    -- Relationship detection
    relationship_detected BOOLEAN DEFAULT FALSE,
    relationship_confidence DECIMAL(5,3),
    relationship_indicators JSON, -- Array of detected indicators

    -- Transaction patterns
    transaction_count INT,
    frequency_with_staff_percentage DECIMAL(5,2),
    after_hours_transactions INT,

    -- Discount analysis
    avg_discount_percentage DECIMAL(5,2),
    discount_statistical_deviation DECIMAL(5,2),

    -- Collusion patterns detected
    patterns_detected JSON, -- Array of pattern objects

    -- Full analysis data
    analysis_data JSON,

    analyzed_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_customer_staff (customer_id, staff_id),
    INDEX idx_risk_level (risk_level),
    INDEX idx_analyzed (analyzed_at),

    FOREIGN KEY (staff_id) REFERENCES staff(staff_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Staff family member declarations (HR records)
CREATE TABLE IF NOT EXISTS staff_family_declarations (
    declaration_id INT AUTO_INCREMENT PRIMARY KEY,
    staff_id INT NOT NULL,
    customer_id INT,

    -- Family member details
    family_member_name VARCHAR(200),
    relationship ENUM('spouse', 'parent', 'child', 'sibling', 'grandparent', 'other'),

    -- Contact information
    phone VARCHAR(50),
    email VARCHAR(200),
    address TEXT,

    -- Declaration
    declared_date DATE NOT NULL,
    declared_by INT, -- Staff ID who entered declaration
    verified BOOLEAN DEFAULT FALSE,

    -- Policy acknowledgment
    policy_acknowledged BOOLEAN DEFAULT FALSE,
    discount_restrictions_applied BOOLEAN DEFAULT TRUE,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_staff (staff_id),
    INDEX idx_customer (customer_id),

    FOREIGN KEY (staff_id) REFERENCES staff(staff_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- AI SHADOW STAFF (DIGITAL TWINS) TABLES
-- =====================================================

-- Digital twin behavioral profiles
CREATE TABLE IF NOT EXISTS shadow_staff_profiles (
    twin_id INT AUTO_INCREMENT PRIMARY KEY,
    staff_id INT NOT NULL,

    -- Learning period
    learning_start_date DATE NOT NULL,
    learning_end_date DATE NOT NULL,
    learning_period_days INT NOT NULL,

    -- Behavioral profiles (JSON for each dimension)
    behavioral_profiles JSON NOT NULL, -- All 8 dimensions
    behavioral_signature VARCHAR(64) NOT NULL, -- SHA256 hash

    -- Quality metrics
    data_quality_score DECIMAL(5,3),
    confidence_level DECIMAL(5,3),
    dimension_count INT DEFAULT 8,

    -- Recalibration
    recalibration_due BOOLEAN DEFAULT FALSE,
    next_recalibration DATE,

    -- Metadata
    model_version VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_staff (staff_id),
    INDEX idx_recalibration (recalibration_due, next_recalibration),
    INDEX idx_created (created_at),

    FOREIGN KEY (staff_id) REFERENCES staff(staff_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Digital twin deviation comparisons
CREATE TABLE IF NOT EXISTS shadow_staff_comparisons (
    comparison_id BIGINT AUTO_INCREMENT PRIMARY KEY,
    staff_id INT NOT NULL,
    twin_id INT NOT NULL,

    -- Comparison period
    comparison_period ENUM('hourly', 'daily', 'weekly', 'monthly'),
    comparison_date DATE NOT NULL,

    -- Deviation scores
    deviation_score DECIMAL(5,3) NOT NULL,
    deviation_level ENUM('NORMAL', 'MINOR', 'MODERATE', 'MAJOR', 'CRITICAL') NOT NULL,

    -- Dimension-specific deviations
    dimension_deviations JSON, -- All 8 dimensions with scores

    -- Top deviating dimensions
    top_deviation_1 VARCHAR(50),
    top_deviation_2 VARCHAR(50),
    top_deviation_3 VARCHAR(50),

    -- Alert generated
    alert_generated BOOLEAN DEFAULT FALSE,
    alert_id INT,

    compared_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_staff_date (staff_id, comparison_date),
    INDEX idx_twin (twin_id),
    INDEX idx_deviation_level (deviation_level),
    INDEX idx_alert (alert_generated),

    FOREIGN KEY (staff_id) REFERENCES staff(staff_id) ON DELETE CASCADE,
    FOREIGN KEY (twin_id) REFERENCES shadow_staff_profiles(twin_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- CAMERA NETWORK INFRASTRUCTURE
-- =====================================================

-- Camera network registry (for CV system)
CREATE TABLE IF NOT EXISTS camera_network (
    camera_id INT AUTO_INCREMENT PRIMARY KEY,
    outlet_id INT NOT NULL,

    -- Camera details
    camera_name VARCHAR(100) NOT NULL,
    camera_location VARCHAR(200),
    camera_ip VARCHAR(50),
    camera_port INT DEFAULT 554,

    -- Stream configuration
    stream_url VARCHAR(500),
    stream_protocol ENUM('rtsp', 'http', 'https', 'webrtc'),
    stream_quality ENUM('low', 'medium', 'high', 'ultra'),

    -- Coverage
    coverage_area VARCHAR(200), -- e.g., "cash_register_1", "entrance", "stockroom"
    viewing_angle INT, -- Degrees
    priority ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',

    -- CV processing
    cv_enabled BOOLEAN DEFAULT TRUE,
    analysis_fps INT DEFAULT 5,
    gpu_accelerated BOOLEAN DEFAULT TRUE,

    -- Status
    online BOOLEAN DEFAULT TRUE,
    last_frame_at DATETIME,
    health_check_status ENUM('healthy', 'degraded', 'offline', 'error'),

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_outlet (outlet_id),
    INDEX idx_online (online),
    INDEX idx_priority (priority)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- SYSTEM PERFORMANCE & MONITORING
-- =====================================================

-- System access logging (for behavioral analysis)
CREATE TABLE IF NOT EXISTS system_access_log (
    log_id BIGINT AUTO_INCREMENT PRIMARY KEY,
    staff_id INT NOT NULL,

    access_timestamp DATETIME NOT NULL,
    screen_name VARCHAR(100),
    action VARCHAR(100),

    -- Session details
    session_id VARCHAR(100),
    ip_address VARCHAR(50),
    user_agent TEXT,

    -- Time tracking
    time_spent_seconds INT,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_staff_timestamp (staff_id, access_timestamp),
    INDEX idx_screen (screen_name),

    FOREIGN KEY (staff_id) REFERENCES staff(staff_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Customer interactions log (for Digital Twin customer service profiling)
CREATE TABLE IF NOT EXISTS customer_interactions (
    interaction_id BIGINT AUTO_INCREMENT PRIMARY KEY,
    staff_id INT NOT NULL,
    customer_id INT,

    interaction_timestamp DATETIME NOT NULL,
    interaction_type ENUM('sale', 'return', 'inquiry', 'complaint', 'phone_call', 'email', 'in_person') NOT NULL,
    interaction_duration_seconds INT,

    -- Interaction details
    outlet_id INT,
    product_categories JSON, -- Categories involved
    transaction_amount DECIMAL(10,2),

    -- Outcome
    customer_satisfaction_score INT, -- 1-5 if available
    issue_resolved BOOLEAN,
    escalated BOOLEAN DEFAULT FALSE,

    -- Metadata
    notes TEXT,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_staff_timestamp (staff_id, interaction_timestamp),
    INDEX idx_customer (customer_id),
    INDEX idx_outlet (outlet_id),
    INDEX idx_type (interaction_type),

    FOREIGN KEY (staff_id) REFERENCES staff(staff_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Inventory processing log (for Digital Twin inventory efficiency profiling)
CREATE TABLE IF NOT EXISTS inventory_processing_log (
    log_id BIGINT AUTO_INCREMENT PRIMARY KEY,
    staff_id INT NOT NULL,

    action_timestamp DATETIME NOT NULL,
    action_type ENUM('receive', 'count', 'adjust', 'transfer', 'write_off', 'audit') NOT NULL,

    -- Inventory details
    product_id INT,
    quantity INT,
    outlet_id INT,

    -- Processing metrics
    processing_time_seconds INT,
    accuracy_score DECIMAL(5,3), -- 0-1 based on variances
    errors_found INT DEFAULT 0,

    -- Related documents
    consignment_id INT,
    transfer_id INT,
    po_id INT,

    -- Metadata
    notes TEXT,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_staff_timestamp (staff_id, action_timestamp),
    INDEX idx_action_type (action_type),
    INDEX idx_product (product_id),
    INDEX idx_outlet (outlet_id),

    FOREIGN KEY (staff_id) REFERENCES staff(staff_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Staff timesheet (for Digital Twin work schedule profiling)
CREATE TABLE IF NOT EXISTS staff_timesheet (
    timesheet_id BIGINT AUTO_INCREMENT PRIMARY KEY,
    staff_id INT NOT NULL,

    shift_date DATE NOT NULL,
    clock_in DATETIME,
    clock_out DATETIME,

    -- Shift details
    scheduled_start DATETIME,
    scheduled_end DATETIME,
    outlet_id INT,
    role VARCHAR(100),

    -- Deviations
    late_minutes INT DEFAULT 0,
    early_departure_minutes INT DEFAULT 0,
    break_minutes INT,
    overtime_minutes INT DEFAULT 0,

    -- Flags
    no_show BOOLEAN DEFAULT FALSE,
    call_in_sick BOOLEAN DEFAULT FALSE,
    left_early BOOLEAN DEFAULT FALSE,

    -- Metadata
    notes TEXT,
    approved_by INT,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_staff_date (staff_id, shift_date),
    INDEX idx_outlet (outlet_id),
    INDEX idx_date (shift_date),

    FOREIGN KEY (staff_id) REFERENCES staff(staff_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Multi-source fraud analysis results (Orchestrator storage)
CREATE TABLE IF NOT EXISTS multi_source_fraud_analysis (
    analysis_id INT AUTO_INCREMENT PRIMARY KEY,
    staff_id INT NOT NULL,

    analysis_timestamp DATETIME NOT NULL,
    analysis_type ENUM('comprehensive', 'targeted', 'investigation', 'real_time') NOT NULL,

    -- Individual engine results
    ml_prediction_risk ENUM('MINIMAL', 'LOW', 'MEDIUM', 'HIGH', 'CRITICAL'),
    ml_fraud_probability DECIMAL(5,3),

    cv_behavior_risk ENUM('MINIMAL', 'LOW', 'MEDIUM', 'HIGH', 'CRITICAL'),
    cv_behavioral_score DECIMAL(5,3),

    nlp_communication_risk ENUM('MINIMAL', 'LOW', 'MEDIUM', 'HIGH', 'CRITICAL'),
    nlp_risk_score DECIMAL(5,3),

    collusion_risk ENUM('MINIMAL', 'LOW', 'MEDIUM', 'HIGH', 'CRITICAL'),
    collusion_score DECIMAL(5,3),

    digital_twin_deviation ENUM('NORMAL', 'MINOR', 'MODERATE', 'MAJOR', 'CRITICAL'),
    twin_deviation_score DECIMAL(5,3),

    -- Composite scoring
    composite_risk_score DECIMAL(5,3) NOT NULL,
    composite_risk_level ENUM('MINIMAL', 'LOW', 'MEDIUM', 'HIGH', 'CRITICAL') NOT NULL,
    confidence_score DECIMAL(5,3),

    -- Correlations detected
    correlations_found JSON, -- Array of {engine1, engine2, correlation_type, strength}

    -- Evidence & recommendations
    investigation_priority INT, -- 1-10
    evidence_summary JSON,
    recommended_actions JSON,

    -- Investigation tracking
    investigation_opened BOOLEAN DEFAULT FALSE,
    investigation_id INT,
    assigned_to INT,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_staff_timestamp (staff_id, analysis_timestamp),
    INDEX idx_composite_risk (composite_risk_level, composite_risk_score DESC),
    INDEX idx_investigation (investigation_opened),
    INDEX idx_created (created_at),

    FOREIGN KEY (staff_id) REFERENCES staff(staff_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- INDEXES FOR PERFORMANCE OPTIMIZATION
-- =====================================================

-- Additional composite indexes for common queries
ALTER TABLE predictive_fraud_forecasts
    ADD INDEX idx_high_risk (risk_level, fraud_probability DESC);

ALTER TABLE cv_analysis_results
    ADD INDEX idx_high_risk_recent (risk_level, analysis_date DESC);

ALTER TABLE communication_analysis
    ADD INDEX idx_critical_messages (risk_level, message_date DESC);

ALTER TABLE customer_collusion_analysis
    ADD INDEX idx_high_risk_collusion (risk_level, collusion_score DESC);

ALTER TABLE shadow_staff_comparisons
    ADD INDEX idx_major_deviations (deviation_level, compared_at DESC);

-- =====================================================
-- INITIAL DATA & CONFIGURATION
-- =====================================================

-- Insert default fraud patterns
INSERT INTO fraud_pattern_library (pattern_name, pattern_category, description, behavioral_signature) VALUES
('Gradual Discount Escalation', 'discount_fraud', 'Slowly increasing discounts over time to avoid detection', '{}'),
('After-Hours Inventory Manipulation', 'inventory_fraud', 'Adjusting inventory during off-hours', '{}'),
('Refund Fraud with Regular Customer', 'refund_fraud', 'Processing fraudulent refunds for known customers', '{}'),
('Collusion Ring', 'collusion', 'Multiple staff members working together', '{}');

-- Insert default communication fraud patterns
INSERT INTO communication_fraud_patterns (pattern_name, pattern_category, keywords, keyword_combinations) VALUES
('Collusion Planning', 'collusion',
 '["cover for me", "nobody needs to know", "between us", "our secret", "special customer"]',
 '[]'),
('Evidence Destruction', 'evidence_destruction',
 '["delete", "erase", "clear the logs", "wipe", "destroy the"]',
 '[]'),
('Financial Stress', 'financial_stress',
 '["need money", "desperate", "rent is due", "payday loan", "broke"]',
 '[]');

-- =====================================================
-- VIEWS FOR COMMON QUERIES
-- =====================================================

-- High-risk staff summary view
CREATE OR REPLACE VIEW v_high_risk_staff_summary AS
SELECT
    s.staff_id,
    s.staff_name,
    COALESCE(pff.risk_level, 'UNKNOWN') as ml_prediction_risk,
    COALESCE(pff.fraud_probability, 0) as ml_fraud_probability,
    COALESCE(cva.risk_level, 'UNKNOWN') as cv_behavior_risk,
    COALESCE(cva.behavioral_risk_score, 0) as cv_risk_score,
    COALESCE(ssc.deviation_level, 'UNKNOWN') as digital_twin_deviation,
    COALESCE(ssc.deviation_score, 0) as twin_deviation_score,
    COUNT(DISTINCT cca.customer_id) as suspicious_customer_relationships,
    COUNT(DISTINCT ca.analysis_id) as high_risk_communications
FROM staff s
LEFT JOIN (
    SELECT staff_id, risk_level, fraud_probability
    FROM predictive_fraud_forecasts
    WHERE prediction_date = CURDATE()
) pff ON s.staff_id = pff.staff_id
LEFT JOIN (
    SELECT staff_id, risk_level, behavioral_risk_score
    FROM cv_analysis_results
    WHERE analysis_date = CURDATE() AND analysis_period = 'daily'
) cva ON s.staff_id = cva.staff_id
LEFT JOIN (
    SELECT staff_id, deviation_level, deviation_score
    FROM shadow_staff_comparisons
    WHERE comparison_date = CURDATE()
    ORDER BY compared_at DESC
    LIMIT 1
) ssc ON s.staff_id = ssc.staff_id
LEFT JOIN customer_collusion_analysis cca ON s.staff_id = cca.staff_id
    AND cca.risk_level IN ('HIGH', 'CRITICAL')
LEFT JOIN communication_analysis ca ON s.staff_id = ca.staff_id
    AND ca.risk_level IN ('HIGH', 'CRITICAL')
    AND ca.message_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)
WHERE s.active = 1
GROUP BY s.staff_id, s.staff_name, pff.risk_level, pff.fraud_probability,
         cva.risk_level, cva.behavioral_risk_score, ssc.deviation_level, ssc.deviation_score;

-- =====================================================
-- SCHEMA COMPLETE
-- =====================================================
