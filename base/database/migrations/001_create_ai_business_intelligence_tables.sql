-- ============================================================================
-- AI Business Intelligence & Knowledge Management System
-- Database Migration v1.0
-- ============================================================================

-- Drop existing tables if re-running (BE CAREFUL IN PRODUCTION!)
-- DROP TABLE IF EXISTS ai_knowledge_queries;
-- DROP TABLE IF EXISTS ai_staff_energy_tracking;
-- DROP TABLE IF EXISTS ai_staff_knowledge_map;
-- DROP TABLE IF EXISTS ai_optimization_suggestions;
-- DROP TABLE IF EXISTS ai_business_insights;

-- ============================================================================
-- AI Business Insights Table
-- ============================================================================

CREATE TABLE IF NOT EXISTS ai_business_insights (
    insight_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    -- Classification
    insight_type ENUM(
        'sales_performance',
        'inventory_intelligence',
        'financial_health',
        'operational_efficiency',
        'customer_behavior',
        'supplier_performance',
        'staff_performance'
    ) NOT NULL,

    category VARCHAR(100) NOT NULL COMMENT 'Subcategory (e.g., "trending_products", "store_decline")',
    priority ENUM('critical', 'high', 'medium', 'low', 'info') NOT NULL DEFAULT 'medium',

    -- Content
    title VARCHAR(255) NOT NULL COMMENT 'Short insight summary',
    description TEXT NOT NULL COMMENT 'Detailed explanation',
    insight_data JSON NOT NULL COMMENT 'Structured insight details',

    -- AI Attribution
    model_name VARCHAR(100) NOT NULL,
    confidence_score DECIMAL(5, 4) NOT NULL COMMENT '0.0000 to 1.0000',
    reasoning TEXT COMMENT 'Why AI believes this insight is valid',

    -- Evidence
    data_sources JSON NOT NULL COMMENT 'Tables/files used for analysis',
    time_period_start DATETIME COMMENT 'Analysis period start',
    time_period_end DATETIME COMMENT 'Analysis period end',
    sample_size INT COMMENT 'Number of records analyzed',

    -- Recommendations
    recommendations JSON COMMENT 'Actionable suggestions',
    expected_impact JSON COMMENT 'Predicted outcomes (savings, time, etc)',
    implementation_difficulty ENUM('low', 'medium', 'high', 'very_high'),
    estimated_implementation_time VARCHAR(50) COMMENT 'e.g., "2 weeks", "3 days"',

    -- Lifecycle
    status ENUM('new', 'reviewed', 'actioned', 'dismissed', 'monitoring') NOT NULL DEFAULT 'new',
    reviewed_by INT UNSIGNED COMMENT 'User ID who reviewed',
    reviewed_at DATETIME,
    action_taken TEXT COMMENT 'What was done about this insight',
    outcome TEXT COMMENT 'What happened after action',

    -- Expiry & Relevance
    expires_at DATETIME COMMENT 'When insight becomes stale',
    is_recurring BOOLEAN DEFAULT FALSE COMMENT 'Repeats regularly?',
    recurrence_pattern VARCHAR(100) COMMENT 'e.g., "weekly", "monthly"',

    -- Metadata
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_type_priority (insight_type, priority),
    INDEX idx_status (status),
    INDEX idx_expires (expires_at),
    INDEX idx_created (created_at),
    INDEX idx_reviewed_by (reviewed_by)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='AI-generated business intelligence insights';


-- ============================================================================
-- Process Optimization Suggestions Table
-- ============================================================================

CREATE TABLE IF NOT EXISTS ai_optimization_suggestions (
    optimization_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    -- Target
    optimization_type ENUM(
        'workflow_efficiency',
        'resource_allocation',
        'cost_reduction',
        'time_savings',
        'quality_improvement',
        'automation_opportunity'
    ) NOT NULL,

    module_name VARCHAR(100) NOT NULL COMMENT 'Which module/process',
    process_name VARCHAR(255) NOT NULL COMMENT 'Specific process to optimize',

    -- Current State
    current_state TEXT NOT NULL COMMENT 'How it works now',
    current_metrics JSON NOT NULL COMMENT 'Current performance data',
    pain_points JSON COMMENT 'Identified problems',

    -- Proposed Change
    proposed_change TEXT NOT NULL COMMENT 'What to change',
    proposed_metrics JSON COMMENT 'Expected performance after change',

    -- Business Case
    expected_savings_nzd DECIMAL(10, 2) COMMENT 'Annual cost savings',
    expected_time_savings_hours DECIMAL(10, 2) COMMENT 'Time saved per week/month',
    affected_staff_count INT COMMENT 'How many staff benefit',
    roi_months DECIMAL(5, 2) COMMENT 'Payback period',

    -- Implementation
    implementation_steps JSON COMMENT 'How to implement',
    implementation_difficulty ENUM('low', 'medium', 'high', 'very_high'),
    estimated_implementation_time VARCHAR(50),
    required_resources JSON COMMENT 'What\'s needed (tools, training, etc)',
    risks JSON COMMENT 'Potential risks',

    -- AI Attribution
    model_name VARCHAR(100) NOT NULL,
    confidence_score DECIMAL(5, 4) NOT NULL,
    analysis_based_on JSON COMMENT 'Data sources used',
    similar_implementations JSON COMMENT 'Examples from other companies/industries',

    -- Lifecycle
    status ENUM('proposed', 'reviewing', 'approved', 'implementing', 'completed', 'rejected') DEFAULT 'proposed',
    reviewed_by INT UNSIGNED,
    reviewed_at DATETIME,
    approved_by INT UNSIGNED,
    approved_at DATETIME,
    rejection_reason TEXT,

    -- Results Tracking
    implementation_start DATETIME,
    implementation_end DATETIME,
    actual_savings_nzd DECIMAL(10, 2),
    actual_time_savings_hours DECIMAL(10, 2),
    success_rating ENUM('exceeded', 'met', 'partial', 'failed'),
    lessons_learned TEXT,

    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_type_status (optimization_type, status),
    INDEX idx_module (module_name),
    INDEX idx_roi (roi_months),
    INDEX idx_reviewed_by (reviewed_by),
    INDEX idx_approved_by (approved_by)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='AI-identified process optimization opportunities';


-- ============================================================================
-- Staff Knowledge & Expertise Mapping Table
-- ============================================================================

CREATE TABLE IF NOT EXISTS ai_staff_knowledge_map (
    knowledge_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    -- Staff Member
    user_id INT UNSIGNED NOT NULL,

    -- Knowledge Area
    knowledge_domain VARCHAR(100) NOT NULL COMMENT 'e.g., "inventory_transfers"',
    skill_name VARCHAR(255) NOT NULL COMMENT 'Specific skill',

    -- Proficiency
    proficiency_level ENUM('novice', 'competent', 'proficient', 'expert', 'master') NOT NULL,
    proficiency_score DECIMAL(5, 4) NOT NULL COMMENT 'AI-calculated 0-1 score',

    -- Evidence
    evidence_sources JSON NOT NULL COMMENT 'What data proves this knowledge',
    task_count INT COMMENT 'How many times performed',
    success_rate DECIMAL(5, 4) COMMENT 'Success percentage',
    avg_completion_time_minutes INT,
    error_rate DECIMAL(5, 4),

    -- Learning Journey
    first_demonstrated DATETIME COMMENT 'When first showed competency',
    last_demonstrated DATETIME COMMENT 'Most recent demonstration',
    improvement_rate DECIMAL(5, 4) COMMENT 'Rate of skill improvement',
    learning_velocity ENUM('slow', 'moderate', 'fast', 'very_fast'),

    -- Knowledge Sharing
    times_taught_others INT DEFAULT 0,
    mentorship_quality_score DECIMAL(5, 4) COMMENT 'Based on mentee performance',
    documentation_contributions INT DEFAULT 0,

    -- AI Analysis
    model_name VARCHAR(100) NOT NULL,
    confidence_score DECIMAL(5, 4) NOT NULL,
    last_analyzed DATETIME NOT NULL,

    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY unique_user_skill (user_id, knowledge_domain, skill_name),
    INDEX idx_user (user_id),
    INDEX idx_domain (knowledge_domain),
    INDEX idx_proficiency (proficiency_level),
    INDEX idx_last_analyzed (last_analyzed)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='AI-tracked staff expertise and knowledge levels';


CREATE TABLE IF NOT EXISTS ai_staff_energy_tracking (
    energy_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    -- Staff Member
    user_id INT UNSIGNED NOT NULL,

    -- Time Period
    tracking_date DATE NOT NULL,
    week_number INT NOT NULL,

    -- Workload Metrics
    tasks_completed INT NOT NULL DEFAULT 0,
    tasks_volume_percentile DECIMAL(5, 2) COMMENT 'vs. historical average',
    avg_task_duration_minutes INT,
    rushed_tasks_count INT COMMENT 'Completed faster than normal',

    -- Quality Indicators
    error_count INT DEFAULT 0,
    error_rate DECIMAL(5, 4),
    correction_count INT COMMENT 'How many fixes needed',

    -- Energy Indicators (AI-inferred)
    energy_score DECIMAL(5, 4) NOT NULL COMMENT '0-1, high = good energy',
    burnout_risk_score DECIMAL(5, 4) NOT NULL COMMENT '0-1, high = at risk',
    engagement_score DECIMAL(5, 4) COMMENT 'Based on activity patterns',

    -- Pattern Detection
    stress_indicators JSON COMMENT 'AI-detected stress signals',
    positive_indicators JSON COMMENT 'AI-detected positive signals',

    -- Recommendations
    ai_recommendations JSON COMMENT 'Support suggestions',

    -- Metadata
    model_name VARCHAR(100) NOT NULL,
    confidence_score DECIMAL(5, 4) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY unique_user_date (user_id, tracking_date),
    INDEX idx_user (user_id),
    INDEX idx_date (tracking_date),
    INDEX idx_week (week_number),
    INDEX idx_burnout_risk (burnout_risk_score)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='AI-powered staff energy and wellbeing tracking';


CREATE TABLE IF NOT EXISTS ai_knowledge_queries (
    query_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    -- Query Details
    user_id INT UNSIGNED NOT NULL COMMENT 'Who asked',
    query_text TEXT NOT NULL COMMENT 'Natural language question',
    query_type ENUM('how_to', 'who_knows', 'best_practice', 'troubleshoot', 'explain') NOT NULL,

    -- Context
    context_module VARCHAR(100) COMMENT 'Which module context',
    context_task VARCHAR(255) COMMENT 'What task triggered question',

    -- AI Response
    response_type ENUM('direct_answer', 'expert_referral', 'documentation_link', 'tutorial') NOT NULL,
    response_content JSON NOT NULL COMMENT 'The answer provided',
    confidence_score DECIMAL(5, 4) NOT NULL,

    -- Expert Matching (if applicable)
    suggested_expert_ids JSON COMMENT 'User IDs of suggested experts',
    expert_contacted BOOLEAN DEFAULT FALSE,
    expert_helped BOOLEAN,

    -- Sources
    knowledge_sources JSON COMMENT 'Where answer came from',
    related_docs JSON COMMENT 'Relevant documentation',

    -- Feedback
    was_helpful BOOLEAN,
    feedback_text TEXT,
    resolved_issue BOOLEAN,

    -- Metadata
    response_time_ms INT COMMENT 'How long AI took',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_user (user_id),
    INDEX idx_type (query_type),
    INDEX idx_created (created_at),
    INDEX idx_helpful (was_helpful)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Staff knowledge queries and AI responses';


-- ============================================================================
-- Sample Data (Optional - for testing)
-- ============================================================================

-- Insert a sample insight to demonstrate structure
INSERT INTO ai_business_insights (
    insight_type,
    category,
    priority,
    title,
    description,
    insight_data,
    model_name,
    confidence_score,
    reasoning,
    data_sources,
    recommendations,
    expected_impact,
    status,
    expires_at
) VALUES (
    'operational_efficiency',
    'system_setup',
    'info',
    'AI Business Intelligence System Activated',
    'The AI Business Intelligence System has been successfully installed and is ready to generate insights.',
    '{"setup_date": "2025-11-04", "modules_active": ["business_insights"], "status": "operational"}',
    'AIService v1.0',
    1.0000,
    'System initialization completed successfully. All tables created and services registered.',
    '["ai_business_insights", "ai_optimization_suggestions", "ai_staff_knowledge_map"]',
    '[{"action": "Run first analysis", "description": "Execute generateDailyInsights() to analyze current data", "impact": "Receive initial business intelligence"}]',
    '{"immediate_value": "Real-time business visibility", "long_term_value": "Continuous optimization"}',
    'new',
    DATE_ADD(NOW(), INTERVAL 30 DAY)
);


-- ============================================================================
-- Verification Queries
-- ============================================================================

-- Verify tables created
SELECT
    TABLE_NAME,
    TABLE_ROWS,
    CREATE_TIME,
    TABLE_COMMENT
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME LIKE 'ai_%'
ORDER BY TABLE_NAME;

-- Show sample insight
SELECT
    insight_id,
    insight_type,
    priority,
    title,
    status,
    confidence_score,
    created_at
FROM ai_business_insights
ORDER BY created_at DESC
LIMIT 5;
