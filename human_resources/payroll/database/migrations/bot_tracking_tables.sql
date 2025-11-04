-- ============================================================================
-- BOT TRACKING DATABASE SCHEMA
-- Tracks all autonomous bot decisions, heartbeats, and events
-- ============================================================================

-- Bot Decision Log
-- Records every decision made by the autonomous payroll bot
CREATE TABLE IF NOT EXISTS payroll_bot_decisions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_type VARCHAR(50) NOT NULL COMMENT 'amendment, leave, timesheet, discrepancy, compliance',
    event_id INT NOT NULL COMMENT 'ID of the event being decided on',
    action VARCHAR(50) NOT NULL COMMENT 'approve, decline, escalate, fix, ignore',
    reasoning TEXT COMMENT 'Bot AI reasoning for the decision',
    confidence DECIMAL(3,2) COMMENT 'AI confidence score 0.00-1.00',
    metadata JSON COMMENT 'Additional bot metadata (model used, tokens, etc)',
    decided_at DATETIME NOT NULL COMMENT 'When bot made the decision',
    executed_at DATETIME COMMENT 'When action was executed',
    execution_result JSON COMMENT 'Result of action execution',
    execution_error TEXT COMMENT 'Error message if execution failed',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_event (event_type, event_id),
    INDEX idx_decided_at (decided_at),
    INDEX idx_confidence (confidence),
    INDEX idx_action (action)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Audit trail of all bot decisions';

-- Bot Heartbeat
-- Tracks bot health and activity
CREATE TABLE IF NOT EXISTS payroll_bot_heartbeat (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bot_instance VARCHAR(100) COMMENT 'Bot instance identifier',
    status VARCHAR(20) NOT NULL COMMENT 'active, idle, error, stopped',
    events_processed INT DEFAULT 0 COMMENT 'Events processed this heartbeat',
    decisions_made INT DEFAULT 0 COMMENT 'Decisions made this heartbeat',
    errors_count INT DEFAULT 0 COMMENT 'Errors encountered this heartbeat',
    last_event_id INT COMMENT 'Last event processed',
    system_health JSON COMMENT 'System health checks (DB, Deputy, Xero, Vend)',
    performance_metrics JSON COMMENT 'Performance data (avg decision time, queue depth)',
    last_seen DATETIME NOT NULL COMMENT 'Last heartbeat timestamp',
    uptime_seconds INT COMMENT 'Bot uptime in seconds',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_last_seen (last_seen),
    INDEX idx_status (status),
    INDEX idx_bot_instance (bot_instance)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Bot health monitoring and heartbeat';

-- Bot Event Queue
-- Tracks events and their processing status
CREATE TABLE IF NOT EXISTS payroll_bot_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_type VARCHAR(50) NOT NULL,
    entity_id INT NOT NULL COMMENT 'ID of amendment, leave, timesheet, etc',
    priority INT DEFAULT 50 COMMENT 'Priority score 0-100',
    status VARCHAR(20) DEFAULT 'pending' COMMENT 'pending, processing, completed, failed, escalated',
    assigned_to_bot VARCHAR(100) COMMENT 'Bot instance handling this event',
    requires_ai_decision TINYINT(1) DEFAULT 1,
    ai_context JSON COMMENT 'Context provided to AI for decision',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    assigned_at DATETIME COMMENT 'When bot picked up this event',
    completed_at DATETIME COMMENT 'When processing completed',
    retry_count INT DEFAULT 0,
    last_error TEXT,

    INDEX idx_status (status),
    INDEX idx_priority (priority),
    INDEX idx_event (event_type, entity_id),
    INDEX idx_created_at (created_at),
    INDEX idx_assigned_to (assigned_to_bot)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Event queue for bot processing';

-- Bot Performance Metrics
-- Aggregated performance stats per day
CREATE TABLE IF NOT EXISTS payroll_bot_metrics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    date DATE NOT NULL,
    bot_instance VARCHAR(100),
    total_events INT DEFAULT 0,
    auto_approved INT DEFAULT 0,
    auto_declined INT DEFAULT 0,
    escalated INT DEFAULT 0,
    errors INT DEFAULT 0,
    avg_confidence DECIMAL(3,2),
    avg_decision_time_ms INT COMMENT 'Average time to make decision',
    accuracy_rate DECIMAL(5,2) COMMENT 'Percentage of correct decisions (if validated)',
    uptime_percentage DECIMAL(5,2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY unique_date_bot (date, bot_instance),
    INDEX idx_date (date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Daily aggregated bot performance metrics';

-- Escalation Tracking
-- Track escalations that require human intervention
CREATE TABLE IF NOT EXISTS payroll_escalations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_type VARCHAR(50) NOT NULL,
    event_id INT NOT NULL,
    reason TEXT NOT NULL,
    bot_confidence DECIMAL(3,2),
    severity VARCHAR(20) DEFAULT 'medium' COMMENT 'low, medium, high, critical',
    assigned_to_user_id INT COMMENT 'Manager assigned to review',
    status VARCHAR(20) DEFAULT 'pending' COMMENT 'pending, in_review, resolved',
    escalated_at DATETIME NOT NULL,
    resolved_at DATETIME,
    resolution_notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_status (status),
    INDEX idx_severity (severity),
    INDEX idx_event (event_type, event_id),
    INDEX idx_escalated_at (escalated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Human escalation tracking';

-- Bot Configuration
-- Bot behavior settings and thresholds
CREATE TABLE IF NOT EXISTS payroll_bot_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    config_key VARCHAR(100) NOT NULL UNIQUE,
    config_value TEXT,
    config_type VARCHAR(20) DEFAULT 'string' COMMENT 'string, int, float, bool, json',
    description TEXT,
    last_updated DATETIME,
    updated_by_user_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_key (config_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Bot configuration settings';

-- Insert default bot configuration
INSERT INTO payroll_bot_config (config_key, config_value, config_type, description, last_updated) VALUES
('auto_approve_threshold', '0.9', 'float', 'Confidence threshold for auto-approval', NOW()),
('manual_review_threshold', '0.8', 'float', 'Below this, send to manual review', NOW()),
('escalation_threshold', '0.5', 'float', 'Below this, escalate to manager', NOW()),
('max_auto_approve_amount', '500', 'float', 'Max dollar amount for auto-approval', NOW()),
('poll_interval_seconds', '30', 'int', 'How often bot polls for events', NOW()),
('heartbeat_interval_seconds', '60', 'int', 'How often bot sends heartbeat', NOW()),
('enable_auto_leave_approval', '1', 'bool', 'Allow bot to auto-approve leave', NOW()),
('enable_auto_timesheet_approval', '1', 'bool', 'Allow bot to auto-approve timesheets', NOW()),
('enable_auto_amendment_approval', '1', 'bool', 'Allow bot to auto-approve amendments', NOW()),
('enable_auto_discrepancy_fix', '1', 'bool', 'Allow bot to auto-fix wage discrepancies', NOW()),
('max_retry_attempts', '3', 'int', 'Max retries for failed actions', NOW()),
('compliance_check_required', '1', 'bool', 'Always run compliance checks', NOW())
ON DUPLICATE KEY UPDATE
    config_value = VALUES(config_value),
    last_updated = NOW();

-- Add bot-related columns to existing tables
-- (These may already exist - ALTER IF NOT EXISTS pattern)

-- Timesheet amendments - track bot involvement
ALTER TABLE payroll_timesheet_amendments
ADD COLUMN IF NOT EXISTS approved_by_bot TINYINT(1) DEFAULT 0 COMMENT 'Approved by autonomous bot',
ADD COLUMN IF NOT EXISTS bot_confidence DECIMAL(3,2) COMMENT 'Bot confidence score',
ADD COLUMN IF NOT EXISTS bot_decision_id INT COMMENT 'FK to payroll_bot_decisions',
ADD INDEX IF NOT EXISTS idx_bot_approved (approved_by_bot);

-- Leave requests - track bot involvement
ALTER TABLE leave_requests
ADD COLUMN IF NOT EXISTS decided_by_bot TINYINT(1) DEFAULT 0 COMMENT 'Decided by autonomous bot',
ADD COLUMN IF NOT EXISTS bot_confidence DECIMAL(3,2) COMMENT 'Bot confidence score',
ADD COLUMN IF NOT EXISTS bot_decision_id INT COMMENT 'FK to payroll_bot_decisions',
ADD COLUMN IF NOT EXISTS escalated TINYINT(1) DEFAULT 0,
ADD COLUMN IF NOT EXISTS escalation_reason TEXT,
ADD COLUMN IF NOT EXISTS escalated_at DATETIME,
ADD INDEX IF NOT EXISTS idx_bot_decided (decided_by_bot),
ADD INDEX IF NOT EXISTS idx_escalated (escalated);

-- Deputy timesheets - track bot approval
ALTER TABLE deputy_timesheets
ADD COLUMN IF NOT EXISTS approved_by_bot TINYINT(1) DEFAULT 0,
ADD COLUMN IF NOT EXISTS bot_confidence DECIMAL(3,2),
ADD COLUMN IF NOT EXISTS bot_decision_id INT,
ADD INDEX IF NOT EXISTS idx_bot_approved (approved_by_bot);

-- Wage discrepancies - track bot resolution
ALTER TABLE payroll_wage_discrepancies
ADD COLUMN IF NOT EXISTS resolved_by_bot TINYINT(1) DEFAULT 0,
ADD COLUMN IF NOT EXISTS bot_confidence DECIMAL(3,2),
ADD COLUMN IF NOT EXISTS bot_decision_id INT,
ADD INDEX IF NOT EXISTS idx_bot_resolved (resolved_by_bot);

-- ============================================================================
-- VIEWS FOR BOT MONITORING DASHBOARD
-- ============================================================================

-- Real-time bot activity view
CREATE OR REPLACE VIEW v_bot_activity_realtime AS
SELECT
    bd.id,
    bd.event_type,
    bd.event_id,
    bd.action,
    bd.confidence,
    bd.decided_at,
    bd.executed_at,
    TIMESTAMPDIFF(SECOND, bd.decided_at, bd.executed_at) as execution_time_seconds,
    CASE
        WHEN bd.execution_error IS NULL THEN 'success'
        ELSE 'error'
    END as status,
    bd.execution_error
FROM payroll_bot_decisions bd
WHERE bd.decided_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
ORDER BY bd.decided_at DESC
LIMIT 100;

-- Bot performance summary (last 24 hours)
CREATE OR REPLACE VIEW v_bot_performance_24h AS
SELECT
    COUNT(*) as total_decisions,
    SUM(CASE WHEN action = 'approve' THEN 1 ELSE 0 END) as approved,
    SUM(CASE WHEN action = 'decline' THEN 1 ELSE 0 END) as declined,
    SUM(CASE WHEN action = 'escalate' THEN 1 ELSE 0 END) as escalated,
    SUM(CASE WHEN execution_error IS NOT NULL THEN 1 ELSE 0 END) as errors,
    AVG(confidence) as avg_confidence,
    AVG(TIMESTAMPDIFF(SECOND, decided_at, executed_at)) as avg_execution_time,
    MIN(decided_at) as first_decision,
    MAX(decided_at) as last_decision
FROM payroll_bot_decisions
WHERE decided_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR);

-- Pending escalations requiring human review
CREATE OR REPLACE VIEW v_pending_escalations AS
SELECT
    e.id,
    e.event_type,
    e.event_id,
    e.reason,
    e.severity,
    e.escalated_at,
    TIMESTAMPDIFF(HOUR, e.escalated_at, NOW()) as hours_pending,
    CASE e.event_type
        WHEN 'timesheet_amendment' THEN (
            SELECT CONCAT(u.first_name, ' ', u.last_name)
            FROM payroll_timesheet_amendments a
            JOIN users u ON a.staff_id = u.id
            WHERE a.id = e.event_id
        )
        WHEN 'leave_request' THEN (
            SELECT CONCAT(u.first_name, ' ', u.last_name)
            FROM leave_requests lr
            JOIN users u ON lr.staff_id = u.id
            WHERE lr.id = e.event_id
        )
        ELSE 'Unknown'
    END as staff_name
FROM payroll_escalations e
WHERE e.status = 'pending'
ORDER BY e.severity DESC, e.escalated_at ASC;

-- Bot health status
CREATE OR REPLACE VIEW v_bot_health_current AS
SELECT
    bot_instance,
    status,
    last_seen,
    TIMESTAMPDIFF(SECOND, last_seen, NOW()) as seconds_since_heartbeat,
    CASE
        WHEN TIMESTAMPDIFF(SECOND, last_seen, NOW()) < 120 THEN 'healthy'
        WHEN TIMESTAMPDIFF(SECOND, last_seen, NOW()) < 300 THEN 'warning'
        ELSE 'offline'
    END as health_status,
    events_processed,
    decisions_made,
    errors_count,
    uptime_seconds / 3600 as uptime_hours
FROM payroll_bot_heartbeat
WHERE id IN (
    SELECT MAX(id)
    FROM payroll_bot_heartbeat
    GROUP BY bot_instance
)
ORDER BY last_seen DESC;

-- ============================================================================
-- STORED PROCEDURES FOR BOT OPERATIONS
-- ============================================================================

DELIMITER $$

-- Get next high-priority event for bot processing
CREATE PROCEDURE IF NOT EXISTS sp_get_next_bot_event(
    IN p_bot_instance VARCHAR(100)
)
BEGIN
    DECLARE v_event_id INT;

    -- Find highest priority pending event
    SELECT id INTO v_event_id
    FROM payroll_bot_events
    WHERE status = 'pending'
    AND requires_ai_decision = 1
    ORDER BY priority DESC, created_at ASC
    LIMIT 1
    FOR UPDATE;

    IF v_event_id IS NOT NULL THEN
        -- Assign to bot
        UPDATE payroll_bot_events
        SET status = 'processing',
            assigned_to_bot = p_bot_instance,
            assigned_at = NOW()
        WHERE id = v_event_id;

        -- Return event details
        SELECT * FROM payroll_bot_events WHERE id = v_event_id;
    ELSE
        -- No events available
        SELECT NULL as id;
    END IF;
END$$

-- Record bot heartbeat
CREATE PROCEDURE IF NOT EXISTS sp_record_bot_heartbeat(
    IN p_bot_instance VARCHAR(100),
    IN p_status VARCHAR(20),
    IN p_events_processed INT,
    IN p_decisions_made INT,
    IN p_errors_count INT,
    IN p_system_health JSON,
    IN p_uptime_seconds INT
)
BEGIN
    INSERT INTO payroll_bot_heartbeat (
        bot_instance,
        status,
        events_processed,
        decisions_made,
        errors_count,
        system_health,
        performance_metrics,
        last_seen,
        uptime_seconds
    ) VALUES (
        p_bot_instance,
        p_status,
        p_events_processed,
        p_decisions_made,
        p_errors_count,
        p_system_health,
        NULL,
        NOW(),
        p_uptime_seconds
    );
END$$

DELIMITER ;

-- ============================================================================
-- SAMPLE DATA FOR TESTING
-- ============================================================================

-- Insert sample bot configuration
-- (Already done above in default config)

-- Grant permissions (adjust user as needed)
-- GRANT SELECT, INSERT, UPDATE ON payroll_bot_decisions TO 'payroll_bot'@'localhost';
-- GRANT SELECT, INSERT, UPDATE ON payroll_bot_heartbeat TO 'payroll_bot'@'localhost';
-- GRANT SELECT, INSERT, UPDATE ON payroll_bot_events TO 'payroll_bot'@'localhost';
