-- AI Agent Integration Database Schema
-- Creates tables for AI Agent conversations, caching, metrics, and function calls
-- Version: 1.0.0
-- Created: 2025-11-04

-- ============================================================================
-- Table: ai_agent_conversations
-- Purpose: Store all AI Agent interactions with full conversation history
-- ============================================================================

CREATE TABLE IF NOT EXISTS `ai_agent_conversations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `conversation_id` varchar(64) DEFAULT NULL COMMENT 'Groups related messages in a conversation',
  `action` varchar(50) NOT NULL COMMENT 'chat, recommend, analyze, predict, function_call',
  `prompt` text NOT NULL COMMENT 'User input or system prompt',
  `response` longtext NOT NULL COMMENT 'AI response',
  `context_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'JSON context' CHECK (json_valid(`context_data`)),
  `provider` varchar(50) NOT NULL COMMENT 'openai, anthropic, custom',
  `model` varchar(100) NOT NULL COMMENT 'gpt-4o, claude-3.5-sonnet, etc',
  `tokens_used` int(11) DEFAULT 0 COMMENT 'Total tokens consumed',
  `processing_time_ms` int(11) DEFAULT 0 COMMENT 'Processing duration in milliseconds',
  `confidence_score` decimal(3,2) DEFAULT NULL COMMENT '0.00 to 1.00',
  `user_id` int(11) DEFAULT NULL COMMENT 'CIS user who initiated request',
  `transfer_id` int(11) DEFAULT NULL COMMENT 'Related transfer if applicable',
  `status` enum('pending','completed','failed','cancelled') DEFAULT 'completed',
  `error_message` text DEFAULT NULL COMMENT 'Error details if status=failed',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_conversation_id` (`conversation_id`),
  KEY `idx_action` (`action`),
  KEY `idx_provider_model` (`provider`,`model`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_transfer_id` (`transfer_id`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_status` (`status`),
  FULLTEXT KEY `ft_prompt_response` (`prompt`,`response`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='AI Agent conversation history and interactions';

-- ============================================================================
-- Table: ai_agent_cache
-- Purpose: Cache AI responses to reduce API calls and improve performance
-- ============================================================================

CREATE TABLE IF NOT EXISTS `ai_agent_cache` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `cache_key` varchar(64) NOT NULL COMMENT 'MD5 hash of request',
  `action` varchar(50) NOT NULL,
  `request_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT 'JSON request' CHECK (json_valid(`request_data`)),
  `response_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT 'JSON response' CHECK (json_valid(`response_data`)),
  `provider` varchar(50) NOT NULL,
  `model` varchar(100) NOT NULL,
  `hit_count` int(11) DEFAULT 0 COMMENT 'Number of cache hits',
  `last_hit_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at` timestamp NOT NULL COMMENT 'Cache expiry time',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_cache_key` (`cache_key`),
  KEY `idx_action` (`action`),
  KEY `idx_expires_at` (`expires_at`),
  KEY `idx_provider_model` (`provider`,`model`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='AI Agent response cache for performance optimization';

-- ============================================================================
-- Table: ai_agent_metrics
-- Purpose: Track AI Agent usage, performance, costs, and ROI
-- ============================================================================

CREATE TABLE IF NOT EXISTS `ai_agent_metrics` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `metric_date` date NOT NULL,
  `provider` varchar(50) NOT NULL,
  `model` varchar(100) NOT NULL,
  `action` varchar(50) NOT NULL,
  `request_count` int(11) DEFAULT 0,
  `success_count` int(11) DEFAULT 0,
  `failure_count` int(11) DEFAULT 0,
  `cache_hit_count` int(11) DEFAULT 0,
  `cache_miss_count` int(11) DEFAULT 0,
  `total_tokens` bigint(20) DEFAULT 0,
  `total_cost_usd` decimal(10,4) DEFAULT 0.0000 COMMENT 'Estimated API cost',
  `avg_processing_time_ms` int(11) DEFAULT 0,
  `avg_confidence_score` decimal(3,2) DEFAULT NULL,
  `total_savings_nzd` decimal(10,2) DEFAULT 0.00 COMMENT 'Cost savings from recommendations',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_metrics` (`metric_date`,`provider`,`model`,`action`),
  KEY `idx_metric_date` (`metric_date`),
  KEY `idx_provider_model` (`provider`,`model`),
  KEY `idx_action` (`action`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='AI Agent usage metrics and cost tracking';

-- ============================================================================
-- Table: ai_agent_function_calls
-- Purpose: Log AI-triggered function calls (function calling feature)
-- ============================================================================

CREATE TABLE IF NOT EXISTS `ai_agent_function_calls` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `conversation_id` varchar(64) DEFAULT NULL,
  `function_name` varchar(100) NOT NULL COMMENT 'create_transfer, book_freight, etc',
  `function_params` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT 'JSON parameters' CHECK (json_valid(`function_params`)),
  `function_result` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'JSON result' CHECK (json_valid(`function_result`)),
  `status` enum('pending','executing','completed','failed') DEFAULT 'pending',
  `error_message` text DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL COMMENT 'User who authorized execution',
  `authorized_at` timestamp NULL DEFAULT NULL,
  `executed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_conversation_id` (`conversation_id`),
  KEY `idx_function_name` (`function_name`),
  KEY `idx_status` (`status`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='AI-triggered function call logs';

-- ============================================================================
-- Table: ai_agent_feedback
-- Purpose: Track user feedback on AI recommendations for learning/improvement
-- ============================================================================

CREATE TABLE IF NOT EXISTS `ai_agent_feedback` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `conversation_id` varchar(64) DEFAULT NULL,
  `ai_conversation_id` bigint(20) unsigned DEFAULT NULL COMMENT 'FK to ai_agent_conversations',
  `feedback_type` enum('thumbs_up','thumbs_down','helpful','not_helpful','incorrect','excellent') NOT NULL,
  `feedback_notes` text DEFAULT NULL COMMENT 'Optional user comments',
  `rating` tinyint(4) DEFAULT NULL COMMENT '1-5 star rating',
  `user_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_conversation_id` (`conversation_id`),
  KEY `idx_ai_conversation_id` (`ai_conversation_id`),
  KEY `idx_feedback_type` (`feedback_type`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `fk_feedback_conversation`
    FOREIGN KEY (`ai_conversation_id`)
    REFERENCES `ai_agent_conversations` (`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='User feedback on AI Agent responses';

-- ============================================================================
-- Table: ai_agent_prompts
-- Purpose: Store reusable system prompts and templates
-- ============================================================================

CREATE TABLE IF NOT EXISTS `ai_agent_prompts` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `prompt_name` varchar(100) NOT NULL,
  `prompt_category` varchar(50) NOT NULL COMMENT 'carrier, packing, analysis, etc',
  `prompt_template` longtext NOT NULL COMMENT 'Template with {placeholders}',
  `variables` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'JSON variable definitions' CHECK (json_valid(`variables`)),
  `is_active` tinyint(1) DEFAULT 1,
  `version` int(11) DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_prompt_name_version` (`prompt_name`,`version`),
  KEY `idx_prompt_category` (`prompt_category`),
  KEY `idx_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Reusable AI prompt templates';

-- ============================================================================
-- Insert default prompts
-- ============================================================================

INSERT INTO `ai_agent_prompts` (`prompt_name`, `prompt_category`, `prompt_template`, `variables`) VALUES
('carrier_recommendation', 'carrier',
'Recommend the best carrier for shipping from {origin} to {destination}.
Weight: {weight}kg
Dimensions: {length}cm x {width}cm x {height}cm
Urgency: {urgency}
Historical carrier performance data:
{carrier_history}

Provide recommendation with confidence score and reasoning.',
'{"origin": "string", "destination": "string", "weight": "number", "length": "number", "width": "number", "height": "number", "urgency": "string", "carrier_history": "json"}'),

('box_packing_optimization', 'packing',
'Optimize box packing for these items:
{items_list}

Available container types:
{container_types}

Strategy: {strategy} (min_cost, min_boxes, or balanced)

Provide optimal packing plan with utilization percentage.',
'{"items_list": "json", "container_types": "json", "strategy": "string"}'),

('transfer_analysis', 'analysis',
'Analyze this transfer and provide insights:
Transfer ID: {transfer_id}
Status: {status}
Origin: {origin_outlet}
Destination: {destination_outlet}
Items: {item_count}
Total weight: {total_weight}kg
Created: {created_at}
Completed: {completed_at}

Provide insights on efficiency, potential improvements, and any risks.',
'{"transfer_id": "number", "status": "string", "origin_outlet": "string", "destination_outlet": "string", "item_count": "number", "total_weight": "number", "created_at": "datetime", "completed_at": "datetime"}'),

('cost_prediction', 'prediction',
'Predict shipping cost for:
Route: {origin} to {destination}
Weight: {weight}kg
Carrier: {carrier}
Historical data:
{historical_costs}

Provide predicted cost with confidence interval.',
'{"origin": "string", "destination": "string", "weight": "number", "carrier": "string", "historical_costs": "json"}');

-- ============================================================================
-- Create indexes for performance
-- ============================================================================

-- Composite index for fast conversation retrieval
ALTER TABLE `ai_agent_conversations`
ADD INDEX `idx_conversation_lookup` (`conversation_id`, `created_at`);

-- Index for cache cleanup
ALTER TABLE `ai_agent_cache`
ADD INDEX `idx_cache_cleanup` (`expires_at`, `hit_count`);

-- Index for metrics aggregation
ALTER TABLE `ai_agent_metrics`
ADD INDEX `idx_metrics_aggregation` (`metric_date`, `provider`, `action`);

-- ============================================================================
-- Create views for common queries
-- ============================================================================

-- View: Recent AI conversations
CREATE OR REPLACE VIEW `v_ai_recent_conversations` AS
SELECT
    c.id,
    c.conversation_id,
    c.action,
    LEFT(c.prompt, 100) as prompt_preview,
    LEFT(c.response, 200) as response_preview,
    c.provider,
    c.model,
    c.confidence_score,
    c.tokens_used,
    c.processing_time_ms,
    c.user_id,
    c.created_at,
    f.feedback_type,
    f.rating
FROM ai_agent_conversations c
LEFT JOIN ai_agent_feedback f ON c.id = f.ai_conversation_id
WHERE c.created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
ORDER BY c.created_at DESC;

-- View: AI metrics summary
CREATE OR REPLACE VIEW `v_ai_metrics_summary` AS
SELECT
    DATE(metric_date) as date,
    provider,
    model,
    SUM(request_count) as total_requests,
    SUM(success_count) as total_success,
    SUM(failure_count) as total_failures,
    SUM(cache_hit_count) as total_cache_hits,
    SUM(total_tokens) as total_tokens,
    SUM(total_cost_usd) as total_cost,
    SUM(total_savings_nzd) as total_savings,
    AVG(avg_confidence_score) as avg_confidence
FROM ai_agent_metrics
GROUP BY DATE(metric_date), provider, model
ORDER BY metric_date DESC;

-- View: Cache efficiency
CREATE OR REPLACE VIEW `v_ai_cache_efficiency` AS
SELECT
    DATE(created_at) as date,
    provider,
    model,
    action,
    COUNT(*) as cache_entries,
    SUM(hit_count) as total_hits,
    AVG(hit_count) as avg_hits_per_entry,
    SUM(CASE WHEN expires_at > NOW() THEN 1 ELSE 0 END) as active_entries,
    SUM(CASE WHEN expires_at <= NOW() THEN 1 ELSE 0 END) as expired_entries
FROM ai_agent_cache
GROUP BY DATE(created_at), provider, model, action
ORDER BY date DESC;

-- ============================================================================
-- Create stored procedures for common operations
-- ============================================================================

DELIMITER $$

-- Procedure: Clean expired cache entries
CREATE PROCEDURE `sp_ai_clean_expired_cache`()
BEGIN
    DELETE FROM ai_agent_cache
    WHERE expires_at < DATE_SUB(NOW(), INTERVAL 1 DAY);

    SELECT ROW_COUNT() as deleted_rows;
END$$

-- Procedure: Get conversation history
CREATE PROCEDURE `sp_ai_get_conversation_history`(
    IN p_conversation_id VARCHAR(64),
    IN p_limit INT
)
BEGIN
    SELECT
        id,
        action,
        prompt,
        response,
        confidence_score,
        created_at
    FROM ai_agent_conversations
    WHERE conversation_id = p_conversation_id
    ORDER BY created_at DESC
    LIMIT p_limit;
END$$

-- Procedure: Update daily metrics
CREATE PROCEDURE `sp_ai_update_daily_metrics`(IN p_date DATE)
BEGIN
    INSERT INTO ai_agent_metrics (
        metric_date,
        provider,
        model,
        action,
        request_count,
        success_count,
        failure_count,
        cache_hit_count,
        cache_miss_count,
        total_tokens,
        avg_processing_time_ms,
        avg_confidence_score
    )
    SELECT
        DATE(created_at) as metric_date,
        provider,
        model,
        action,
        COUNT(*) as request_count,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as success_count,
        SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failure_count,
        0 as cache_hit_count, -- Updated separately from cache table
        0 as cache_miss_count,
        SUM(tokens_used) as total_tokens,
        AVG(processing_time_ms) as avg_processing_time_ms,
        AVG(confidence_score) as avg_confidence_score
    FROM ai_agent_conversations
    WHERE DATE(created_at) = p_date
    GROUP BY DATE(created_at), provider, model, action
    ON DUPLICATE KEY UPDATE
        request_count = VALUES(request_count),
        success_count = VALUES(success_count),
        failure_count = VALUES(failure_count),
        total_tokens = VALUES(total_tokens),
        avg_processing_time_ms = VALUES(avg_processing_time_ms),
        avg_confidence_score = VALUES(avg_confidence_score);
END$$

DELIMITER ;

-- ============================================================================
-- Create events for automated maintenance
-- ============================================================================

-- Event: Clean expired cache daily at 3 AM
CREATE EVENT IF NOT EXISTS `evt_ai_clean_cache`
ON SCHEDULE EVERY 1 DAY
STARTS TIMESTAMP(CURRENT_DATE + INTERVAL 1 DAY + INTERVAL 3 HOUR)
DO
    CALL sp_ai_clean_expired_cache();

-- Event: Update daily metrics at 1 AM
CREATE EVENT IF NOT EXISTS `evt_ai_update_metrics`
ON SCHEDULE EVERY 1 DAY
STARTS TIMESTAMP(CURRENT_DATE + INTERVAL 1 DAY + INTERVAL 1 HOUR)
DO
    CALL sp_ai_update_daily_metrics(CURDATE() - INTERVAL 1 DAY);

-- ============================================================================
-- Verification queries
-- ============================================================================

-- Check if all tables exist
SELECT
    TABLE_NAME,
    TABLE_ROWS,
    AUTO_INCREMENT,
    CREATE_TIME,
    TABLE_COMMENT
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
AND TABLE_NAME LIKE 'ai_agent%'
ORDER BY TABLE_NAME;

-- Show indexes
SELECT
    TABLE_NAME,
    INDEX_NAME,
    GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) as COLUMNS
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = DATABASE()
AND TABLE_NAME LIKE 'ai_agent%'
GROUP BY TABLE_NAME, INDEX_NAME
ORDER BY TABLE_NAME, INDEX_NAME;
