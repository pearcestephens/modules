/*M!999999\- enable the sandbox mode */ 
-- MariaDB dump 10.19  Distrib 10.5.29-MariaDB, for debian-linux-gnu (x86_64)
--
-- Host: localhost    Database: jcepnzzkmj
-- ------------------------------------------------------
-- Server version	10.5.29-MariaDB-deb11-log

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `consignment_ai_audit_log`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `consignment_ai_audit_log` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `trace_id` varchar(100) NOT NULL,
  `decision_id` bigint(20) unsigned DEFAULT NULL COMMENT 'FK to ai_freight_decisions',
  `model_name` varchar(100) NOT NULL COMMENT 'FreightAI, NeuroLink, AutoPack, etc',
  `model_version` varchar(50) NOT NULL COMMENT 'Model version for reproducibility',
  `algorithm` varchar(50) DEFAULT NULL COMMENT 'UCB1, epsilon-greedy, gradient-boost, etc',
  `transfer_id` int(10) unsigned DEFAULT NULL,
  `input_features` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT 'All features used for decision' CHECK (json_valid(`input_features`)),
  `context_key` varchar(255) DEFAULT NULL COMMENT 'Contextual bandit bucket',
  `recommendation` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT 'AI recommended action' CHECK (json_valid(`recommendation`)),
  `confidence_score` decimal(5,4) NOT NULL COMMENT 'Confidence (0.0000 to 1.0000)',
  `alternative_options` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Other options considered with scores' CHECK (json_valid(`alternative_options`)),
  `exploration_mode` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'TRUE if exploring vs exploiting',
  `estimated_savings_nzd` decimal(10,2) DEFAULT NULL COMMENT 'Projected cost savings',
  `estimated_time_mins` int(10) unsigned DEFAULT NULL COMMENT 'Estimated time impact',
  `risk_score` decimal(5,4) DEFAULT NULL COMMENT 'Risk assessment (0=low, 1=high)',
  `was_overridden` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Did human override AI?',
  `override_reason` text DEFAULT NULL COMMENT 'Why human overrode AI decision',
  `override_user_id` int(10) unsigned DEFAULT NULL COMMENT 'Staff who overrode',
  `override_at` timestamp NULL DEFAULT NULL COMMENT 'When override occurred',
  `actual_outcome` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Actual result after action taken' CHECK (json_valid(`actual_outcome`)),
  `actual_cost_nzd` decimal(10,2) DEFAULT NULL COMMENT 'Actual cost incurred',
  `outcome_recorded_at` timestamp NULL DEFAULT NULL COMMENT 'When outcome was recorded',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_trace` (`trace_id`),
  KEY `idx_decision` (`decision_id`),
  KEY `idx_transfer` (`transfer_id`),
  KEY `idx_model` (`model_name`,`model_version`,`created_at`),
  KEY `idx_confidence` (`confidence_score`),
  KEY `idx_override` (`was_overridden`,`created_at`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Detailed AI decision audit trail for governance and explainability';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `consignment_ai_insights`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `consignment_ai_insights` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `transfer_id` int(11) NOT NULL,
  `insight_text` text NOT NULL,
  `insight_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Structured insight data' CHECK (json_valid(`insight_json`)),
  `insight_type` varchar(50) DEFAULT 'general' COMMENT 'logistics, inventory, timing, cost, staff, risk',
  `priority` varchar(20) DEFAULT 'medium' COMMENT 'low, medium, high, critical',
  `confidence_score` decimal(3,2) DEFAULT 0.85 COMMENT '0.00 to 1.00',
  `model_provider` varchar(50) NOT NULL COMMENT 'openai, anthropic',
  `model_name` varchar(100) NOT NULL COMMENT 'gpt-4o, claude-3.5-sonnet',
  `tokens_used` int(11) DEFAULT 0,
  `processing_time_ms` int(11) DEFAULT 0,
  `generated_at` datetime NOT NULL,
  `expires_at` datetime NOT NULL COMMENT 'Cache expiry time',
  `created_by` int(11) DEFAULT NULL COMMENT 'User ID if manually regenerated',
  PRIMARY KEY (`id`),
  KEY `idx_transfer_id` (`transfer_id`),
  KEY `idx_generated_at` (`generated_at`),
  KEY `idx_expires_at` (`expires_at`),
  KEY `idx_priority` (`priority`),
  KEY `idx_transfer_fresh` (`transfer_id`,`expires_at`),
  CONSTRAINT `consignment_ai_insights_ibfk_1` FOREIGN KEY (`transfer_id`) REFERENCES `vend_consignments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=113 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `consignment_alert_rules`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `consignment_alert_rules` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `rule_name` varchar(100) NOT NULL COMMENT 'Human-readable rule name',
  `category` varchar(50) NOT NULL COMMENT 'Log category to monitor',
  `event_type` varchar(100) DEFAULT NULL COMMENT 'Specific event or NULL for all',
  `severity` enum('debug','info','notice','warning','error','critical','alert','emergency') NOT NULL,
  `threshold_count` int(10) unsigned NOT NULL DEFAULT 1 COMMENT 'Number of events',
  `threshold_window_min` int(10) unsigned NOT NULL DEFAULT 5 COMMENT 'Time window in minutes',
  `alert_method` enum('email','slack','webhook','sms') NOT NULL DEFAULT 'email',
  `alert_recipients` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT 'Array of recipients (emails, slack channels, etc)' CHECK (json_valid(`alert_recipients`)),
  `alert_message_template` text DEFAULT NULL COMMENT 'Custom alert message template',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `last_triggered_at` timestamp NULL DEFAULT NULL,
  `trigger_count` int(10) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `rule_name` (`rule_name`),
  KEY `idx_active` (`is_active`,`category`,`severity`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Alert escalation rules for critical events';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `consignment_alerts_log`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `consignment_alerts_log` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `alert_config_id` int(10) unsigned NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `transfer_id` int(11) DEFAULT NULL,
  `session_id` varchar(255) DEFAULT NULL,
  `alert_message` text NOT NULL,
  `severity` enum('info','warning','error','critical') NOT NULL,
  `trigger_metric` varchar(100) DEFAULT NULL,
  `trigger_value` decimal(12,4) DEFAULT NULL,
  `threshold_value` decimal(12,4) DEFAULT NULL,
  `acknowledged` tinyint(1) DEFAULT 0,
  `acknowledged_by` int(11) DEFAULT NULL,
  `acknowledged_at` datetime DEFAULT NULL,
  `resolved` tinyint(1) DEFAULT 0,
  `resolution_notes` text DEFAULT NULL,
  `triggered_at` datetime(3) DEFAULT current_timestamp(3),
  PRIMARY KEY (`id`),
  KEY `idx_alert_config` (`alert_config_id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_transfer` (`transfer_id`),
  KEY `idx_triggered` (`triggered_at`),
  KEY `idx_unresolved` (`resolved`,`triggered_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Log of all triggered alerts for monitoring';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `consignment_audit_log`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `consignment_audit_log` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `transaction_id` varchar(50) DEFAULT NULL COMMENT 'Related transaction ID',
  `entity_type` enum('transfer','po','consignment') NOT NULL DEFAULT 'consignment',
  `entity_pk` int(11) DEFAULT NULL,
  `transfer_pk` int(11) DEFAULT NULL,
  `transfer_id` varchar(100) DEFAULT NULL COMMENT 'Internal transfer ID',
  `vend_consignment_id` varchar(100) DEFAULT NULL COMMENT 'Vend consignment ID',
  `vend_transfer_id` char(36) DEFAULT NULL,
  `action` varchar(100) NOT NULL COMMENT 'Action performed',
  `operation_type` varchar(50) DEFAULT NULL COMMENT 'Operation type for bulletproof compatibility',
  `status` varchar(50) NOT NULL COMMENT 'Action status',
  `actor_type` enum('system','user','api','cron','webhook') NOT NULL,
  `actor_id` varchar(100) DEFAULT NULL COMMENT 'User ID or system identifier',
  `user_id` int(11) DEFAULT NULL COMMENT 'User ID for bulletproof compatibility',
  `outlet_from` varchar(100) DEFAULT NULL,
  `outlet_to` varchar(100) DEFAULT NULL,
  `data_before` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'State before action' CHECK (json_valid(`data_before`)),
  `data_after` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'State after action' CHECK (json_valid(`data_after`)),
  `error_message` text DEFAULT NULL COMMENT 'Error details if failed',
  `rollback_details` longtext DEFAULT NULL COMMENT 'Rollback information if failed',
  `duration_seconds` decimal(10,3) DEFAULT NULL COMMENT 'Operation duration',
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Additional context data' CHECK (json_valid(`metadata`)),
  `error_details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Error information if failed' CHECK (json_valid(`error_details`)),
  `processing_time_ms` int(10) unsigned DEFAULT NULL,
  `api_response` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'External API response' CHECK (json_valid(`api_response`)),
  `session_id` varchar(255) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `completed_at` datetime DEFAULT NULL COMMENT 'When operation completed',
  PRIMARY KEY (`id`),
  KEY `idx_transfer_id` (`transfer_id`),
  KEY `idx_vend_consignment` (`vend_consignment_id`),
  KEY `idx_action_status` (`action`,`status`),
  KEY `idx_actor` (`actor_type`,`actor_id`),
  KEY `idx_outlet_from_to` (`outlet_from`,`outlet_to`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_error_tracking` (`status`,`created_at`) COMMENT 'For error rate monitoring',
  KEY `idx_transfer_pk` (`transfer_pk`),
  KEY `idx_vend_transfer` (`vend_transfer_id`),
  KEY `idx_entity` (`entity_type`,`entity_pk`),
  KEY `idx_audit_errors` (`status`,`created_at`),
  KEY `idx_tal_entity_action_time` (`entity_type`,`action`,`created_at`),
  KEY `idx_tal_transfer_time` (`transfer_id`,`created_at`),
  KEY `idx_tal_status_time` (`status`,`created_at`),
  KEY `idx_tal_actor_time` (`actor_type`,`actor_id`,`created_at`),
  KEY `idx_audit_transfer_created` (`transfer_id`,`created_at`),
  KEY `idx_audit_action_created` (`action`,`created_at`),
  KEY `idx_transaction_id` (`transaction_id`),
  KEY `idx_duration` (`duration_seconds`),
  KEY `idx_completed_at` (`completed_at`),
  KEY `idx_operation_type` (`operation_type`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_audit_transfer_id` (`transfer_id`),
  KEY `idx_audit_created` (`created_at`),
  KEY `idx_audit_action` (`action`),
  KEY `idx_audit_transfer_action` (`transfer_id`,`action`,`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=173148 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Comprehensive audit trail for all transfer operations';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `consignment_carrier_orders`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `consignment_carrier_orders` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Row ID',
  `transfer_id` int(11) NOT NULL COMMENT 'FK to transfers.id',
  `carrier` varchar(50) NOT NULL COMMENT 'Carrier code e.g., NZ_POST, GSS',
  `order_id` varchar(100) DEFAULT NULL COMMENT 'Carrier order identifier (string, may be numeric)',
  `order_number` varchar(100) NOT NULL COMMENT 'Our canonical order number (e.g., TR-1234)',
  `payload` longtext DEFAULT NULL COMMENT 'Raw API response snapshot (JSON)',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_transfer_carrier` (`transfer_id`,`carrier`),
  KEY `idx_order_id` (`order_id`),
  KEY `idx_order_number` (`order_number`),
  CONSTRAINT `fk_tco_transfer` FOREIGN KEY (`transfer_id`) REFERENCES `vend_consignments` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='External carrier orders per transfer (NZ Post, GSS, etc).';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `consignment_config`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `consignment_config` (
  `config_key` varchar(100) NOT NULL,
  `config_value` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`config_key`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `consignment_configurations`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `consignment_configurations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `allocation_method` tinyint(1) NOT NULL DEFAULT 1,
  `power_factor` decimal(4,2) NOT NULL DEFAULT 2.00,
  `min_allocation_pct` decimal(5,2) NOT NULL DEFAULT 5.00,
  `max_allocation_pct` decimal(5,2) NOT NULL DEFAULT 50.00,
  `rounding_method` tinyint(1) NOT NULL DEFAULT 0,
  `is_preset` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `enable_safety_checks` tinyint(1) NOT NULL DEFAULT 1,
  `enable_logging` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` varchar(50) DEFAULT NULL,
  `updated_by` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_name` (`name`),
  KEY `idx_preset` (`is_preset`),
  KEY `idx_active` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `consignment_idempotency`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `consignment_idempotency` (
  `idem_key` varchar(128) NOT NULL,
  `idem_hash` char(64) NOT NULL,
  `request_payload` longtext DEFAULT NULL,
  `transfer_id` int(10) unsigned DEFAULT NULL,
  `operation_type` varchar(64) DEFAULT NULL,
  `vend_id` varchar(64) DEFAULT NULL,
  `vend_number` varchar(64) DEFAULT NULL,
  `response_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`response_json`)),
  `status_code` smallint(6) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`idem_key`),
  KEY `idx_idem_hash` (`idem_hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `consignment_labels`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `consignment_labels` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `transfer_id` int(10) unsigned NOT NULL,
  `order_id` bigint(20) unsigned DEFAULT NULL,
  `carrier_code` varchar(32) NOT NULL,
  `tracking` varchar(64) NOT NULL,
  `label_url` varchar(255) NOT NULL,
  `spooled` tinyint(1) NOT NULL DEFAULT 0,
  `idem_key` varchar(80) DEFAULT NULL,
  `idem_hash` char(64) DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_transfer_tracking` (`transfer_id`,`tracking`),
  KEY `idx_order` (`order_id`),
  KEY `idx_transfer` (`transfer_id`),
  KEY `idx_labels_tracking` (`tracking`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `consignment_log_archive`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `consignment_log_archive` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `original_log_id` bigint(20) unsigned NOT NULL COMMENT 'Original ID from transfer_unified_log',
  `trace_id` varchar(100) NOT NULL,
  `category` varchar(50) NOT NULL,
  `event_type` varchar(100) NOT NULL,
  `severity` varchar(20) NOT NULL,
  `message` text NOT NULL,
  `event_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`event_data`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'Original creation timestamp',
  `archived_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_trace` (`trace_id`),
  KEY `idx_original` (`original_log_id`),
  KEY `idx_archived` (`archived_at`),
  KEY `idx_category` (`category`,`archived_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Archived logs for compliance (7 year retention)';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `consignment_logs`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `consignment_logs` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT COMMENT 'Log entry ID',
  `transfer_id` int(11) DEFAULT NULL COMMENT 'FK to transfers.id (optional, event scope)',
  `shipment_id` int(11) DEFAULT NULL COMMENT 'FK to transfer_shipments.id (optional)',
  `item_id` int(11) DEFAULT NULL COMMENT 'FK to transfer_items.id (optional)',
  `parcel_id` int(11) DEFAULT NULL COMMENT 'FK to transfer_parcels.id (optional)',
  `staff_transfer_id` int(10) unsigned DEFAULT NULL COMMENT 'FK to staff_transfers.id (optional, unsigned)',
  `event_type` varchar(100) NOT NULL COMMENT 'CREATE|STATUS_CHANGE|ADD_ITEM|PACKED|SENT|RECEIVED|CANCELLED|NOTE|WEBHOOK|... ',
  `event_data` longtext DEFAULT NULL COMMENT 'JSON payload (old/new values, raw webhook/API bodies)',
  `actor_user_id` int(11) DEFAULT NULL COMMENT 'User performing action (if any)',
  `actor_role` varchar(50) DEFAULT NULL COMMENT 'Role/group (optional)',
  `severity` enum('info','warning','error','critical') DEFAULT 'info',
  `source_system` varchar(50) NOT NULL DEFAULT 'CIS' COMMENT 'CIS|VendWebhook|API|TaskRunner etc.',
  `trace_id` varchar(64) DEFAULT NULL COMMENT 'Correlation ID for grouped events',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `customer_id` varchar(45) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_logs_transfer` (`transfer_id`,`created_at`),
  KEY `idx_logs_shipment` (`shipment_id`,`created_at`),
  KEY `idx_logs_item` (`item_id`,`created_at`),
  KEY `idx_logs_parcel` (`parcel_id`,`created_at`),
  KEY `idx_logs_staff` (`staff_transfer_id`,`created_at`),
  KEY `idx_logs_event` (`event_type`,`created_at`),
  KEY `idx_logs_customer` (`customer_id`),
  KEY `idx_tl_transfer_type_time` (`transfer_id`,`event_type`,`created_at`),
  KEY `idx_tl_trace` (`trace_id`),
  KEY `idx_tl_source_severity_time` (`source_system`,`severity`,`created_at`),
  CONSTRAINT `fk_logs_customer` FOREIGN KEY (`customer_id`) REFERENCES `vend_customers` (`id`),
  CONSTRAINT `fk_logs_item` FOREIGN KEY (`item_id`) REFERENCES `vend_consignment_line_items` (`id`) ON DELETE SET NULL ON UPDATE NO ACTION,
  CONSTRAINT `fk_logs_parcel` FOREIGN KEY (`parcel_id`) REFERENCES `consignment_parcels` (`id`) ON DELETE SET NULL ON UPDATE NO ACTION,
  CONSTRAINT `fk_logs_shipment` FOREIGN KEY (`shipment_id`) REFERENCES `consignment_shipments` (`id`) ON DELETE SET NULL ON UPDATE NO ACTION,
  CONSTRAINT `fk_logs_staff` FOREIGN KEY (`staff_transfer_id`) REFERENCES `staff_transfers` (`id`) ON DELETE SET NULL ON UPDATE NO ACTION,
  CONSTRAINT `fk_logs_transfer` FOREIGN KEY (`transfer_id`) REFERENCES `vend_consignments` (`id`) ON DELETE SET NULL ON UPDATE NO ACTION,
  CONSTRAINT `chk_logs_event_json` CHECK (`event_data` is null or json_valid(`event_data`))
) ENGINE=InnoDB AUTO_INCREMENT=3528 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Immutable audit log. One row per event; JSON payloads, actor, and origin.';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `consignment_media`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `consignment_media` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `transfer_id` int(11) NOT NULL,
  `parcel_id` int(11) DEFAULT NULL,
  `discrepancy_id` int(11) DEFAULT NULL,
  `kind` enum('photo','video','other') NOT NULL DEFAULT 'photo',
  `mime_type` varchar(100) NOT NULL,
  `size_bytes` int(10) unsigned NOT NULL DEFAULT 0,
  `path` varchar(255) NOT NULL,
  `thumb_path` varchar(255) DEFAULT NULL,
  `uploaded_by` int(11) DEFAULT NULL,
  `src_ip` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `note` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_transfer_time` (`transfer_id`,`created_at`),
  KEY `idx_parcel` (`parcel_id`),
  KEY `idx_discrepancy` (`discrepancy_id`),
  KEY `idx_tm_parcel_time` (`parcel_id`,`created_at`),
  CONSTRAINT `fk_tm_discrepancy` FOREIGN KEY (`discrepancy_id`) REFERENCES `consignment_discrepancies` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_tm_parcel` FOREIGN KEY (`parcel_id`) REFERENCES `consignment_parcels` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_tm_transfer` FOREIGN KEY (`transfer_id`) REFERENCES `vend_consignments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `consignment_metrics`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `consignment_metrics` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `transfer_id` int(11) DEFAULT NULL,
  `source_outlet_id` int(11) DEFAULT NULL,
  `destination_outlet_id` int(11) DEFAULT NULL,
  `total_items` int(11) DEFAULT 0,
  `total_quantity` int(11) DEFAULT 0,
  `status` varchar(50) DEFAULT 'pending',
  `processing_time_ms` int(11) DEFAULT 0,
  `api_calls_made` int(11) DEFAULT 0,
  `cost_calculated` decimal(10,2) DEFAULT 0.00,
  `created_at` datetime NOT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  PRIMARY KEY (`id`),
  KEY `idx_transfer_id` (`transfer_id`),
  KEY `idx_source_outlet` (`source_outlet_id`),
  KEY `idx_destination_outlet` (`destination_outlet_id`),
  KEY `idx_status` (`status`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_date_status` (`created_at`,`status`)
) ENGINE=InnoDB AUTO_INCREMENT=41 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `consignment_notes`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `consignment_notes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `transfer_id` int(11) NOT NULL COMMENT 'FK to transfers.id',
  `note_text` mediumtext NOT NULL COMMENT 'Staff-entered note about the transfer',
  `created_by` int(11) NOT NULL COMMENT 'User ID of staff who added the note',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'When the note was added',
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_note_created` (`created_at`),
  KEY `idx_note_deleted` (`deleted_at`),
  KEY `idx_notes_transfer_created` (`transfer_id`,`created_at`),
  CONSTRAINT `consignment_notes_ibfk_1` FOREIGN KEY (`transfer_id`) REFERENCES `vend_consignments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5579 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='History of notes attached to transfers (umbrella-level context).';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `consignment_notifications`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `consignment_notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `transaction_id` varchar(50) DEFAULT NULL COMMENT 'Related transaction ID',
  `transfer_id` int(11) DEFAULT NULL COMMENT 'Related transfer ID',
  `notification_type` varchar(30) NOT NULL COMMENT 'Type of notification (TRANSACTION_FAILURE, QUEUE_FAILURE, etc)',
  `severity` enum('LOW','MEDIUM','HIGH','CRITICAL') NOT NULL DEFAULT 'MEDIUM' COMMENT 'Severity level',
  `title` varchar(200) NOT NULL COMMENT 'Notification title',
  `message` text NOT NULL COMMENT 'Detailed notification message',
  `data` longtext DEFAULT NULL COMMENT 'Additional JSON data',
  `created_at` datetime NOT NULL DEFAULT current_timestamp() COMMENT 'When notification was created',
  `acknowledged_at` datetime DEFAULT NULL COMMENT 'When notification was acknowledged',
  `acknowledged_by` int(11) DEFAULT NULL COMMENT 'User who acknowledged notification',
  `requires_action` tinyint(1) DEFAULT 0 COMMENT 'Whether this requires manual action',
  `action_taken` text DEFAULT NULL COMMENT 'Description of action taken',
  `resolved_at` datetime DEFAULT NULL COMMENT 'When issue was resolved',
  `resolved_by` int(11) DEFAULT NULL COMMENT 'User who resolved issue',
  PRIMARY KEY (`id`),
  KEY `idx_transaction_id` (`transaction_id`),
  KEY `idx_transfer_id` (`transfer_id`),
  KEY `idx_notification_type` (`notification_type`),
  KEY `idx_severity` (`severity`),
  KEY `idx_requires_action` (`requires_action`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_acknowledged_at` (`acknowledged_at`)
) ENGINE=InnoDB AUTO_INCREMENT=29 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Stores failure notifications and resolution tracking';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `consignment_pack_lock_audit`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `consignment_pack_lock_audit` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `transfer_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `action` varchar(50) NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'success',
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `transfer_id` (`transfer_id`,`action`),
  KEY `user_id` (`user_id`,`created_at`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `consignment_pack_locks`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `consignment_pack_locks` (
  `transfer_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `acquired_at` datetime NOT NULL DEFAULT current_timestamp(),
  `expires_at` datetime NOT NULL,
  `heartbeat_at` datetime NOT NULL DEFAULT current_timestamp(),
  `client_fingerprint` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`transfer_id`),
  KEY `expires_at` (`expires_at`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `consignment_parcel_items`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `consignment_parcel_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Parcel item row ID',
  `parcel_id` int(11) NOT NULL COMMENT 'FK to transfer_parcels.id',
  `item_id` int(11) NOT NULL COMMENT 'FK to transfer_items.id',
  `qty_received` int(11) NOT NULL DEFAULT 0,
  `locked_at` timestamp NULL DEFAULT current_timestamp() COMMENT 'Locked upon insert; row is not editable afterwards',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `qty` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_parcel_item` (`parcel_id`,`item_id`),
  KEY `idx_tpi_parcel` (`parcel_id`),
  KEY `idx_tpi_item` (`item_id`),
  CONSTRAINT `fk_tpi_item` FOREIGN KEY (`item_id`) REFERENCES `vend_consignment_line_items` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION,
  CONSTRAINT `fk_tpi_parcel` FOREIGN KEY (`parcel_id`) REFERENCES `consignment_parcels` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION,
  CONSTRAINT `chk_tpi_qtys_nonneg` CHECK (`qty` >= 0 and `qty_received` >= 0),
  CONSTRAINT `chk_tpi_bounds` CHECK (`qty_received` <= `qty`)
) ENGINE=InnoDB AUTO_INCREMENT=53572 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Per-parcel receiving granularity to allow box-by-box acceptance.';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `consignment_parcels`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `consignment_parcels` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Parcel ID',
  `shipment_id` int(11) NOT NULL COMMENT 'FK to transfer_shipments.id',
  `box_number` int(11) NOT NULL COMMENT '1..N within a shipment',
  `tracking_number` varchar(191) DEFAULT NULL,
  `tracking_ref_raw` text DEFAULT NULL,
  `courier` varchar(50) DEFAULT NULL,
  `weight_grams` int(10) unsigned DEFAULT NULL,
  `length_mm` int(10) unsigned DEFAULT NULL,
  `width_mm` int(10) unsigned DEFAULT NULL,
  `height_mm` int(10) unsigned DEFAULT NULL,
  `weight_kg` decimal(10,2) DEFAULT NULL,
  `label_url` varchar(255) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'Last time this parcel was updated',
  `deleted_by` int(11) DEFAULT NULL COMMENT 'User ID of staff who soft-deleted this parcel',
  `deleted_at` timestamp NULL DEFAULT NULL COMMENT 'When the parcel was soft deleted',
  `status` enum('pending','labelled','manifested','in_transit','received','missing','damaged','cancelled','exception') NOT NULL DEFAULT 'pending',
  `notes` mediumtext DEFAULT NULL COMMENT 'Parcel-specific notes (damage, exception, missing context)',
  `received_at` timestamp NULL DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `parcel_number` int(11) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_parcel_boxnum` (`shipment_id`,`box_number`),
  UNIQUE KEY `uq_parcel_box` (`shipment_id`,`box_number`),
  KEY `idx_parcel_shipment` (`shipment_id`),
  KEY `idx_parcel_tracking` (`tracking_number`),
  KEY `idx_parcels_shipment_box` (`shipment_id`,`box_number`),
  KEY `idx_parcel_status_time` (`status`,`updated_at`),
  KEY `idx_parcel_shipment_status` (`shipment_id`,`status`),
  KEY `idx_parcels_shipment_updated` (`shipment_id`,`updated_at`),
  KEY `idx_parcels_shipment_id` (`shipment_id`),
  KEY `idx_parcels_tracking` (`tracking_number`),
  CONSTRAINT `fk_parcels_shipment` FOREIGN KEY (`shipment_id`) REFERENCES `consignment_shipments` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=10738 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Courier parcel metadata for a shipment (tracking, label, weight).';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `consignment_performance_logs`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `consignment_performance_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `operation` varchar(100) NOT NULL DEFAULT '',
  `transfer_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `event_type` enum('RELEASED_FOR_PACKING','PACKING_STARTED','PACKING_COMPLETED','CANCELLED') NOT NULL,
  `event_timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  `session_id` varchar(100) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `additional_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`additional_data`)),
  PRIMARY KEY (`id`),
  KEY `idx_transfer_events` (`transfer_id`,`event_type`),
  KEY `idx_user_performance` (`user_id`,`event_timestamp`),
  KEY `idx_timing_analysis` (`transfer_id`,`event_type`,`event_timestamp`),
  KEY `idx_tpl_user_time` (`user_id`,`event_timestamp`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `consignment_performance_metrics`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `consignment_performance_metrics` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `metric_date` date NOT NULL COMMENT 'Date of metrics',
  `metric_hour` tinyint(3) unsigned DEFAULT NULL COMMENT 'Hour (0-23) for hourly aggregation',
  `category` varchar(50) NOT NULL COMMENT 'Metric category',
  `operation` varchar(100) NOT NULL COMMENT 'Specific operation measured',
  `total_operations` int(10) unsigned NOT NULL DEFAULT 0 COMMENT 'Total ops in period',
  `total_duration_ms` bigint(20) unsigned NOT NULL DEFAULT 0 COMMENT 'Sum of all durations',
  `avg_duration_ms` int(10) unsigned NOT NULL DEFAULT 0 COMMENT 'Average duration',
  `p50_duration_ms` int(10) unsigned DEFAULT NULL COMMENT 'Median duration',
  `p95_duration_ms` int(10) unsigned DEFAULT NULL COMMENT '95th percentile',
  `p99_duration_ms` int(10) unsigned DEFAULT NULL COMMENT '99th percentile',
  `success_count` int(10) unsigned NOT NULL DEFAULT 0,
  `error_count` int(10) unsigned NOT NULL DEFAULT 0,
  `error_rate` decimal(5,4) GENERATED ALWAYS AS (case when `total_operations` > 0 then `error_count` / `total_operations` else 0 end) STORED COMMENT 'Error rate (0.0000 to 1.0000)',
  `ai_decisions` int(10) unsigned NOT NULL DEFAULT 0 COMMENT 'Number of AI decisions',
  `ai_avg_confidence` decimal(5,4) DEFAULT NULL COMMENT 'Average AI confidence',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_metric` (`metric_date`,`metric_hour`,`category`,`operation`),
  KEY `idx_date` (`metric_date`),
  KEY `idx_category` (`category`,`metric_date`),
  KEY `idx_error_rate` (`error_rate`,`metric_date`),
  KEY `idx_tpm_op_date` (`operation`,`metric_date`)
) ENGINE=InnoDB AUTO_INCREMENT=141165 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Aggregated performance metrics for BI dashboards and alerting';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `consignment_queue_log`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `consignment_queue_log` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `trace_id` varchar(100) NOT NULL,
  `queue_name` varchar(100) NOT NULL COMMENT 'Queue identifier (e.g. vend_consignment_sync)',
  `operation` varchar(50) NOT NULL COMMENT 'enqueue, dequeue, retry, fail, complete',
  `transfer_id` int(10) unsigned DEFAULT NULL,
  `vend_consignment_id` varchar(100) DEFAULT NULL,
  `idempotency_key` varchar(255) DEFAULT NULL COMMENT 'Prevents duplicate processing',
  `transaction_id` varchar(50) DEFAULT NULL COMMENT 'Related transaction ID',
  `correlation_id` varchar(50) DEFAULT NULL COMMENT 'For tracking related operations',
  `attempt_number` int(10) unsigned NOT NULL DEFAULT 1,
  `max_attempts` int(10) unsigned NOT NULL DEFAULT 3,
  `retry_delay_sec` int(10) unsigned DEFAULT NULL COMMENT 'Delay before next retry',
  `next_retry_at` timestamp NULL DEFAULT NULL COMMENT 'Scheduled retry time',
  `request_payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Original request data' CHECK (json_valid(`request_payload`)),
  `response_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'API response or result' CHECK (json_valid(`response_data`)),
  `error_message` text DEFAULT NULL COMMENT 'Error details if failed',
  `error_code` varchar(50) DEFAULT NULL COMMENT 'Error code for categorization',
  `http_status` int(10) unsigned DEFAULT NULL COMMENT 'HTTP status code from API',
  `processing_ms` int(10) unsigned DEFAULT NULL COMMENT 'Processing duration',
  `api_latency_ms` int(10) unsigned DEFAULT NULL COMMENT 'External API latency',
  `status` enum('pending','processing','completed','failed','cancelled') NOT NULL,
  `priority` tinyint(3) unsigned NOT NULL DEFAULT 5 COMMENT '1=highest, 10=lowest',
  `heartbeat_at` datetime DEFAULT NULL COMMENT 'Last worker heartbeat',
  `worker_id` varchar(50) DEFAULT NULL COMMENT 'ID of processing worker',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `completed_at` timestamp NULL DEFAULT NULL COMMENT 'When operation completed',
  `error_details` longtext DEFAULT NULL COMMENT 'Detailed error information',
  PRIMARY KEY (`id`),
  KEY `idx_trace` (`trace_id`),
  KEY `idx_queue` (`queue_name`,`status`,`priority`,`created_at`),
  KEY `idx_transfer` (`transfer_id`),
  KEY `idx_vend` (`vend_consignment_id`),
  KEY `idx_idempotency` (`idempotency_key`),
  KEY `idx_retry` (`next_retry_at`,`status`),
  KEY `idx_status` (`status`,`created_at`),
  KEY `idx_queue_retry_status` (`next_retry_at`,`status`,`priority`),
  KEY `idx_transaction_id` (`transaction_id`),
  KEY `idx_correlation_id` (`correlation_id`),
  KEY `idx_priority` (`priority`),
  KEY `idx_heartbeat` (`heartbeat_at`),
  KEY `idx_worker_id` (`worker_id`)
) ENGINE=InnoDB AUTO_INCREMENT=282569 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Queue operations for Vend consignment sync and retry logic';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `consignment_queue_metrics`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `consignment_queue_metrics` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `metric_type` varchar(100) NOT NULL COMMENT 'Type of metric collected',
  `queue_name` varchar(255) NOT NULL DEFAULT 'default',
  `job_type` varchar(100) DEFAULT NULL,
  `value` decimal(15,4) NOT NULL COMMENT 'Metric value',
  `unit` varchar(50) NOT NULL COMMENT 'Metric unit (ms, count, percent, etc)',
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Additional metric context' CHECK (json_valid(`metadata`)),
  `outlet_from` varchar(50) DEFAULT NULL,
  `outlet_to` varchar(50) DEFAULT NULL,
  `worker_id` varchar(255) DEFAULT NULL,
  `recorded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_metric_type_recorded` (`metric_type`,`recorded_at`),
  KEY `idx_queue_job_type` (`queue_name`,`job_type`),
  KEY `idx_outlet_metrics` (`outlet_from`,`outlet_to`,`recorded_at`),
  KEY `idx_worker_metrics` (`worker_id`,`recorded_at`),
  KEY `idx_cleanup_old_metrics` (`recorded_at`) COMMENT 'For metric retention cleanup'
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Performance and operational metrics for transfer queue system';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `consignment_receipt_items`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `consignment_receipt_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `receipt_id` int(11) NOT NULL,
  `transfer_item_id` int(11) NOT NULL,
  `qty_received` int(11) NOT NULL DEFAULT 0,
  `condition` varchar(32) DEFAULT 'ok',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `u_receipt_item` (`receipt_id`,`transfer_item_id`),
  KEY `idx_tri_receipt` (`receipt_id`),
  KEY `idx_tri_item` (`transfer_item_id`),
  CONSTRAINT `fk_tri_item` FOREIGN KEY (`transfer_item_id`) REFERENCES `vend_consignment_line_items` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION,
  CONSTRAINT `fk_tri_receipt` FOREIGN KEY (`receipt_id`) REFERENCES `consignment_receipts` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION,
  CONSTRAINT `chk_tri_qty_nonneg` CHECK (`qty_received` >= 0)
) ENGINE=InnoDB AUTO_INCREMENT=45909 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Lines received per transfer item with optional condition/notes';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `consignment_receipts`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `consignment_receipts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `transfer_id` int(11) NOT NULL,
  `received_by` int(11) DEFAULT NULL,
  `received_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tr_transfer` (`transfer_id`),
  KEY `idx_tr_created` (`created_at`),
  CONSTRAINT `fk_tr_transfer` FOREIGN KEY (`transfer_id`) REFERENCES `vend_consignments` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=586 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Header for receive sessions against a transfer';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `consignment_shipment_items`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `consignment_shipment_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Shipment line ID',
  `shipment_id` int(11) NOT NULL COMMENT 'FK to transfer_shipments.id',
  `item_id` int(11) NOT NULL COMMENT 'FK to transfer_items.id',
  `qty_sent` int(11) NOT NULL COMMENT 'Qty in this shipment wave',
  `qty_received` int(11) NOT NULL DEFAULT 0 COMMENT 'Qty received for this line+wave',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_shipment_item` (`shipment_id`,`item_id`),
  KEY `idx_tsi_shipment` (`shipment_id`),
  KEY `idx_tsi_item` (`item_id`),
  CONSTRAINT `fk_tsi_item` FOREIGN KEY (`item_id`) REFERENCES `vend_consignment_line_items` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION,
  CONSTRAINT `fk_tsi_shipment` FOREIGN KEY (`shipment_id`) REFERENCES `consignment_shipments` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION,
  CONSTRAINT `chk_tsi_qtys_nonneg` CHECK (`qty_sent` >= 0 and `qty_received` >= 0),
  CONSTRAINT `chk_tsi_qtys_bounds` CHECK (`qty_received` <= `qty_sent`),
  CONSTRAINT `chk_tsi_recv_bound` CHECK (`qty_received` <= `qty_sent`)
) ENGINE=InnoDB AUTO_INCREMENT=54550 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Items included in a particular shipment wave; validates sent/received bounds.';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `consignment_shipment_notes`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `consignment_shipment_notes` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Note ID',
  `shipment_id` int(11) NOT NULL COMMENT 'FK to transfer_shipments.id',
  `note_text` mediumtext NOT NULL COMMENT 'The note text entered by staff',
  `created_by` int(11) NOT NULL COMMENT 'User ID of the staff who wrote the note',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'When the note was written',
  PRIMARY KEY (`id`),
  KEY `idx_shipment` (`shipment_id`),
  CONSTRAINT `fk_shipment_notes` FOREIGN KEY (`shipment_id`) REFERENCES `consignment_shipments` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=666 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Free-form history notes for shipments; multiple entries allowed per shipment';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `consignment_shipments`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `consignment_shipments` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Partial shipment ID',
  `transfer_id` int(11) NOT NULL COMMENT 'FK to transfers.id',
  `delivery_mode` enum('auto','manual','dropoff','pickup','courier','internal_drive') NOT NULL DEFAULT 'auto',
  `dest_name` varchar(160) DEFAULT NULL,
  `dest_company` varchar(160) DEFAULT NULL,
  `dest_addr1` varchar(160) DEFAULT NULL,
  `dest_addr2` varchar(160) DEFAULT NULL,
  `dest_suburb` varchar(120) DEFAULT NULL,
  `dest_city` varchar(120) DEFAULT NULL,
  `dest_postcode` varchar(16) DEFAULT NULL,
  `dest_email` varchar(190) DEFAULT NULL,
  `dest_phone` varchar(50) DEFAULT NULL,
  `dest_instructions` varchar(500) DEFAULT NULL,
  `status` enum('packed','in_transit','partial','received','cancelled') NOT NULL DEFAULT 'packed' COMMENT 'Shipment lifecycle incl. partial support',
  `packed_at` timestamp NULL DEFAULT NULL,
  `packed_by` int(11) DEFAULT NULL,
  `received_at` timestamp NULL DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `received_by` int(11) DEFAULT NULL,
  `driver_staff_id` int(11) DEFAULT NULL COMMENT 'If internal_drive, who drove it',
  `nicotine_in_shipment` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Regulatory flag: this shipment contains nicotine',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'Last time this shipment was updated',
  `deleted_by` int(11) DEFAULT NULL COMMENT 'User ID of staff who soft-deleted this shipment',
  `deleted_at` timestamp NULL DEFAULT NULL COMMENT 'When the shipment was soft deleted',
  `carrier_name` varchar(120) DEFAULT NULL,
  `tracking_number` varchar(120) DEFAULT NULL,
  `tracking_url` varchar(300) DEFAULT NULL,
  `dispatched_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_shipments_transfer` (`transfer_id`),
  KEY `idx_shipments_status` (`status`),
  KEY `idx_shipments_mode` (`delivery_mode`),
  KEY `idx_shipments_packed_at` (`packed_at`),
  KEY `idx_shipments_received_at` (`received_at`),
  KEY `idx_shipments_transfer_id` (`transfer_id`),
  KEY `idx_shipments_tracking` (`tracking_number`),
  CONSTRAINT `fk_shipments_transfer` FOREIGN KEY (`transfer_id`) REFERENCES `vend_consignments` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=12463 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Partial shipment waves for a transfer. Supports courier vs internal drive vs pickup.';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `consignment_submissions_log`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `consignment_submissions_log` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `transfer_id` int(11) NOT NULL,
  `submitted_by` int(11) DEFAULT NULL,
  `submission_method` enum('web','api','mobile') NOT NULL DEFAULT 'web',
  `products_count` int(11) NOT NULL,
  `total_items` int(11) NOT NULL,
  `validation_status` enum('passed','failed','warning') NOT NULL,
  `validation_errors` text DEFAULT NULL,
  `processing_time_ms` int(11) DEFAULT NULL,
  `session_id` varchar(255) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_transfer_id` (`transfer_id`),
  KEY `idx_submitted_by` (`submitted_by`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Transfer submission tracking and metrics';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `consignment_system_health`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `consignment_system_health` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `check_name` varchar(50) NOT NULL COMMENT 'Name of health check',
  `status` enum('HEALTHY','WARNING','CRITICAL') NOT NULL DEFAULT 'HEALTHY',
  `response_time_ms` int(11) DEFAULT NULL COMMENT 'Response time in milliseconds',
  `error_message` text DEFAULT NULL COMMENT 'Error details if unhealthy',
  `checked_at` datetime NOT NULL DEFAULT current_timestamp() COMMENT 'When check was performed',
  PRIMARY KEY (`id`),
  KEY `idx_check_name` (`check_name`),
  KEY `idx_status` (`status`),
  KEY `idx_checked_at` (`checked_at`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Stores system health check results';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `consignment_tracking_events`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `consignment_tracking_events` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `transfer_id` int(11) NOT NULL,
  `parcel_id` int(11) DEFAULT NULL,
  `tracking_number` varchar(100) DEFAULT NULL,
  `carrier` varchar(50) NOT NULL,
  `event_code` varchar(64) NOT NULL,
  `event_text` varchar(255) NOT NULL,
  `occurred_at` datetime NOT NULL,
  `raw_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`raw_json`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_transfer_time` (`transfer_id`,`occurred_at`),
  KEY `idx_parcel_time` (`parcel_id`,`occurred_at`),
  KEY `idx_tracking` (`tracking_number`),
  KEY `idx_tte_carrier_tracking` (`carrier`,`tracking_number`),
  CONSTRAINT `fk_tte_parcel` FOREIGN KEY (`parcel_id`) REFERENCES `consignment_parcels` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_tte_transfer` FOREIGN KEY (`transfer_id`) REFERENCES `vend_consignments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `consignment_transactions`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `consignment_transactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `transaction_id` varchar(50) NOT NULL COMMENT 'Unique transaction identifier',
  `transfer_id` int(11) NOT NULL COMMENT 'Related transfer ID',
  `operation_type` varchar(20) NOT NULL COMMENT 'Type of operation (PACK_SUBMIT, RECEIVE_SUBMIT)',
  `status` enum('STARTED','COMMITTED','FAILED','ROLLED_BACK') NOT NULL DEFAULT 'STARTED',
  `started_at` datetime NOT NULL COMMENT 'When transaction began',
  `completed_at` datetime DEFAULT NULL COMMENT 'When transaction finished',
  `data_snapshot` longtext DEFAULT NULL COMMENT 'JSON snapshot of input data',
  `error_message` text DEFAULT NULL COMMENT 'Error details if failed',
  `user_id` int(11) DEFAULT NULL COMMENT 'User who initiated transaction',
  `session_id` varchar(64) DEFAULT NULL COMMENT 'Session identifier',
  `ip_address` varchar(45) DEFAULT NULL COMMENT 'User IP address',
  `user_agent` text DEFAULT NULL COMMENT 'Browser user agent',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `transaction_id` (`transaction_id`),
  KEY `idx_transaction_id` (`transaction_id`),
  KEY `idx_transfer_id` (`transfer_id`),
  KEY `idx_status` (`status`),
  KEY `idx_started_at` (`started_at`),
  KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=53 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tracks all pack/receive transactions for failure protection';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `consignment_ui_sessions`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `consignment_ui_sessions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `transfer_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `state_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`state_json`)),
  `autosave_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `resumed_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ui_transfer_user` (`transfer_id`,`user_id`),
  KEY `idx_ui_expiry` (`expires_at`),
  KEY `idx_tuis_transfer_exp` (`transfer_id`,`expires_at`),
  CONSTRAINT `fk_ui_transfer` FOREIGN KEY (`transfer_id`) REFERENCES `vend_consignments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `consignment_unified_log`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `consignment_unified_log` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `trace_id` varchar(100) NOT NULL COMMENT 'Distributed tracing ID',
  `correlation_id` varchar(100) DEFAULT NULL COMMENT 'Links related operations across services',
  `category` varchar(50) NOT NULL COMMENT 'transfer, shipment, ai_decision, vend_sync, queue, etc.',
  `event_type` varchar(100) NOT NULL COMMENT 'Specific event name',
  `severity` enum('debug','info','notice','warning','error','critical','alert','emergency') NOT NULL DEFAULT 'info' COMMENT 'PSR-3 severity levels',
  `message` text NOT NULL COMMENT 'Human-readable event description',
  `transfer_id` int(10) unsigned DEFAULT NULL,
  `shipment_id` int(10) unsigned DEFAULT NULL,
  `parcel_id` int(10) unsigned DEFAULT NULL,
  `item_id` int(10) unsigned DEFAULT NULL,
  `outlet_id` varchar(50) DEFAULT NULL,
  `vend_consignment_id` varchar(100) DEFAULT NULL COMMENT 'Vend consignment UUID',
  `vend_transfer_id` varchar(100) DEFAULT NULL COMMENT 'Vend transfer UUID',
  `ai_decision_id` bigint(20) unsigned DEFAULT NULL COMMENT 'FK to ai_freight_decisions or transfer_ai_audit_log',
  `ai_model_version` varchar(50) DEFAULT NULL COMMENT 'Model version used for decision',
  `ai_confidence` decimal(5,4) DEFAULT NULL COMMENT 'Confidence score (0.0000 to 1.0000)',
  `actor_user_id` int(10) unsigned DEFAULT NULL COMMENT 'Staff ID who triggered action',
  `actor_role` varchar(50) DEFAULT NULL COMMENT 'User role at time of action',
  `actor_ip` varchar(45) DEFAULT NULL COMMENT 'IPv4 or IPv6 address',
  `event_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Structured event payload (sanitized PII)' CHECK (json_valid(`event_data`)),
  `context_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Additional context metadata' CHECK (json_valid(`context_data`)),
  `tags` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Searchable tags array for filtering' CHECK (json_valid(`tags`)),
  `duration_ms` int(10) unsigned DEFAULT NULL COMMENT 'Operation duration in milliseconds',
  `memory_mb` decimal(10,2) DEFAULT NULL COMMENT 'Memory usage in MB',
  `api_latency_ms` int(10) unsigned DEFAULT NULL COMMENT 'External API call latency',
  `db_query_ms` int(10) unsigned DEFAULT NULL COMMENT 'Database query time',
  `source_system` varchar(50) NOT NULL DEFAULT 'CIS' COMMENT 'System that generated event',
  `environment` enum('dev','staging','production') NOT NULL DEFAULT 'production',
  `server_name` varchar(100) DEFAULT NULL COMMENT 'Hostname of server',
  `php_version` varchar(20) DEFAULT NULL COMMENT 'PHP version',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `event_timestamp` timestamp NULL DEFAULT NULL COMMENT 'When event actually occurred (vs logged)',
  PRIMARY KEY (`id`),
  KEY `idx_trace` (`trace_id`),
  KEY `idx_correlation` (`correlation_id`),
  KEY `idx_category_severity` (`category`,`severity`,`created_at`),
  KEY `idx_transfer` (`transfer_id`,`created_at`),
  KEY `idx_shipment` (`shipment_id`),
  KEY `idx_vend_consignment` (`vend_consignment_id`),
  KEY `idx_ai_decision` (`ai_decision_id`),
  KEY `idx_actor` (`actor_user_id`,`created_at`),
  KEY `idx_event_type` (`event_type`,`created_at`),
  KEY `idx_created` (`created_at`),
  KEY `idx_severity_created` (`severity`,`created_at`),
  KEY `idx_tul_transfer_created_conf` (`transfer_id`,`created_at`,`ai_confidence`),
  FULLTEXT KEY `idx_message` (`message`)
) ENGINE=InnoDB AUTO_INCREMENT=283957 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Unified transfer system event log with AI/Vend/queue integration';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `queue_consignment_actions`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `queue_consignment_actions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `consignment_id` bigint(20) unsigned NOT NULL,
  `action_type` varchar(100) NOT NULL COMMENT 'e.g., create_consignment, add_product, mark_sent',
  `action_payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT 'Full action parameters (inputs)' CHECK (json_valid(`action_payload`)),
  `action_result` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Result of action execution (outputs)' CHECK (json_valid(`action_result`)),
  `is_reversible` tinyint(1) DEFAULT 0 COMMENT 'Can this action be undone?',
  `is_reversed` tinyint(1) DEFAULT 0 COMMENT 'Has this been reversed?',
  `reversed_by_action_id` bigint(20) unsigned DEFAULT NULL COMMENT 'ID of reverse action',
  `reverse_action_type` varchar(100) DEFAULT NULL COMMENT 'Type of reverse action (e.g., delete_product)',
  `reverse_reason` text DEFAULT NULL COMMENT 'Why was this action reversed?',
  `status` enum('pending','executing','completed','failed','reversed') NOT NULL DEFAULT 'pending' COMMENT 'Action execution status',
  `error_message` text DEFAULT NULL COMMENT 'Error if failed',
  `error_stack` text DEFAULT NULL COMMENT 'Full error stack trace',
  `job_id` bigint(20) unsigned DEFAULT NULL COMMENT 'Queue job executing this action',
  `retry_count` int(10) unsigned DEFAULT 0 COMMENT 'Number of retry attempts',
  `max_retries` int(10) unsigned DEFAULT 3 COMMENT 'Maximum retries allowed',
  `triggered_by_user_id` int(10) unsigned DEFAULT NULL COMMENT 'CIS user who triggered',
  `trace_id` varchar(64) DEFAULT NULL COMMENT 'Request trace ID',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'When action was created',
  `executed_at` timestamp NULL DEFAULT NULL COMMENT 'When action started executing',
  `completed_at` timestamp NULL DEFAULT NULL COMMENT 'When action finished',
  `reversed_at` timestamp NULL DEFAULT NULL COMMENT 'When action was reversed',
  PRIMARY KEY (`id`),
  KEY `reversed_by_action_id` (`reversed_by_action_id`),
  KEY `idx_consignment` (`consignment_id`),
  KEY `idx_status` (`status`),
  KEY `idx_is_reversible` (`is_reversible`,`is_reversed`),
  KEY `idx_job` (`job_id`),
  KEY `idx_trace_id` (`trace_id`),
  KEY `idx_action_type` (`action_type`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `queue_consignment_actions_ibfk_1` FOREIGN KEY (`consignment_id`) REFERENCES `queue_consignments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `queue_consignment_actions_ibfk_2` FOREIGN KEY (`reversed_by_action_id`) REFERENCES `queue_consignment_actions` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=13915 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Command pattern action log with reversibility support - ADR-002';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `queue_consignment_deletion_log`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `queue_consignment_deletion_log` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `consignment_id` bigint(20) unsigned NOT NULL,
  `reference` varchar(100) NOT NULL,
  `deleted_by_user_id` bigint(20) unsigned NOT NULL,
  `reason` text DEFAULT NULL,
  `deleted_at` datetime NOT NULL,
  `consignment_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT 'Full consignment backup' CHECK (json_valid(`consignment_data`)),
  PRIMARY KEY (`id`),
  KEY `idx_deleted_at` (`deleted_at`),
  KEY `idx_deleted_by` (`deleted_by_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Audit trail for deleted consignments';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `queue_consignment_inventory_sync`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `queue_consignment_inventory_sync` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `consignment_id` bigint(20) unsigned NOT NULL,
  `consignment_product_id` bigint(20) unsigned DEFAULT NULL COMMENT 'Specific product line item',
  `cis_product_id` int(10) unsigned NOT NULL COMMENT 'CIS products table ID',
  `cis_outlet_id` int(10) unsigned DEFAULT NULL COMMENT 'CIS outlet/store ID',
  `cis_inventory_table` varchar(100) DEFAULT 'products' COMMENT 'Which CIS table was updated',
  `quantity_delta` int(11) NOT NULL COMMENT 'Change in inventory (+/- units)',
  `previous_quantity` int(11) DEFAULT NULL COMMENT 'Inventory before sync',
  `new_quantity` int(11) DEFAULT NULL COMMENT 'Inventory after sync',
  `sync_status` enum('pending','completed','failed','rolled_back') NOT NULL DEFAULT 'pending' COMMENT 'Sync execution status',
  `sync_error` text DEFAULT NULL COMMENT 'Error message if sync failed',
  `sync_error_stack` text DEFAULT NULL COMMENT 'Full error stack trace',
  `rollback_query` text DEFAULT NULL COMMENT 'SQL query to rollback this sync',
  `rolled_back_at` timestamp NULL DEFAULT NULL COMMENT 'When sync was rolled back',
  `rollback_reason` text DEFAULT NULL COMMENT 'Why was sync rolled back',
  `synced_at` timestamp NULL DEFAULT NULL COMMENT 'When sync completed successfully',
  `trace_id` varchar(64) DEFAULT NULL COMMENT 'Request trace ID',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'When sync was queued',
  PRIMARY KEY (`id`),
  KEY `idx_consignment` (`consignment_id`),
  KEY `idx_consignment_product` (`consignment_product_id`),
  KEY `idx_cis_product` (`cis_product_id`),
  KEY `idx_cis_outlet` (`cis_outlet_id`),
  KEY `idx_sync_status` (`sync_status`),
  KEY `idx_synced_at` (`synced_at`),
  KEY `idx_trace_id` (`trace_id`),
  CONSTRAINT `queue_consignment_inventory_sync_ibfk_1` FOREIGN KEY (`consignment_id`) REFERENCES `queue_consignments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `queue_consignment_inventory_sync_ibfk_2` FOREIGN KEY (`consignment_product_id`) REFERENCES `queue_consignment_products` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tracks inventory updates to CIS tables from consignments - ADR-002';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `queue_consignment_notes`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `queue_consignment_notes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `consignment_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `note` text NOT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_consignment` (`consignment_id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Notes and activity log for consignments';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `queue_consignment_products`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `queue_consignment_products` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `consignment_id` bigint(20) unsigned NOT NULL,
  `vend_product_id` varchar(100) NOT NULL COMMENT 'Lightspeed product UUID',
  `vend_consignment_product_id` varchar(100) DEFAULT NULL COMMENT 'Lightspeed consignment_product UUID',
  `product_name` varchar(500) DEFAULT NULL COMMENT 'Product name snapshot',
  `product_sku` varchar(255) DEFAULT NULL COMMENT 'Product SKU snapshot',
  `product_supplier_code` varchar(255) DEFAULT NULL COMMENT 'Supplier code snapshot',
  `count_ordered` int(10) unsigned NOT NULL DEFAULT 0 COMMENT 'Quantity ordered/sent',
  `count_received` int(10) unsigned NOT NULL DEFAULT 0 COMMENT 'Quantity actually received',
  `count_damaged` int(10) unsigned NOT NULL DEFAULT 0 COMMENT 'Damaged items (not added to inventory)',
  `cost_per_unit` decimal(10,2) DEFAULT NULL COMMENT 'Cost per unit (ex. tax)',
  `cost_total` decimal(10,2) DEFAULT NULL COMMENT 'Total cost (count * cost_per_unit)',
  `cis_product_id` int(10) unsigned DEFAULT NULL COMMENT 'Link to CIS products table',
  `inventory_updated` tinyint(1) DEFAULT 0 COMMENT 'Has CIS inventory been updated?',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `received_at` timestamp NULL DEFAULT NULL COMMENT 'When marked as received',
  `deleted_by` int(11) DEFAULT NULL COMMENT 'User who soft-deleted',
  `deleted_at` timestamp NULL DEFAULT NULL COMMENT 'When soft deleted',
  PRIMARY KEY (`id`),
  KEY `idx_consignment` (`consignment_id`),
  KEY `idx_vend_product` (`vend_product_id`),
  KEY `idx_vend_consignment_product` (`vend_consignment_product_id`),
  KEY `idx_cis_product` (`cis_product_id`),
  KEY `idx_inventory_updated` (`inventory_updated`),
  KEY `idx_product_sku` (`product_sku`),
  KEY `idx_deleted_at` (`deleted_at`),
  CONSTRAINT `queue_consignment_products_ibfk_1` FOREIGN KEY (`consignment_id`) REFERENCES `queue_consignments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=630513 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Product line items within consignments - ADR-002';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `queue_consignment_state_transitions`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `queue_consignment_state_transitions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `consignment_id` bigint(20) unsigned NOT NULL,
  `from_status` varchar(50) DEFAULT NULL COMMENT 'Previous status (NULL if first state)',
  `to_status` varchar(50) NOT NULL COMMENT 'New status',
  `trigger_type` enum('user_action','webhook','auto_transition','api_sync','system') NOT NULL COMMENT 'What triggered this transition',
  `trigger_user_id` int(10) unsigned DEFAULT NULL COMMENT 'CIS user who triggered (if user_action)',
  `trigger_job_id` bigint(20) unsigned DEFAULT NULL COMMENT 'Queue job that triggered this',
  `trigger_webhook_id` bigint(20) unsigned DEFAULT NULL COMMENT 'Webhook event that triggered this',
  `is_valid` tinyint(1) DEFAULT 1 COMMENT 'Was this a valid state transition?',
  `validation_error` text DEFAULT NULL COMMENT 'Error message if invalid transition',
  `api_request_url` varchar(500) DEFAULT NULL COMMENT 'Lightspeed API endpoint called',
  `api_request_method` varchar(10) DEFAULT NULL COMMENT 'HTTP method (GET/POST/PUT/DELETE)',
  `api_request_payload` text DEFAULT NULL COMMENT 'JSON payload sent to API',
  `api_response_code` int(11) DEFAULT NULL COMMENT 'HTTP response code',
  `api_response_body` text DEFAULT NULL COMMENT 'API response body (success or error)',
  `api_response_time_ms` int(11) DEFAULT NULL COMMENT 'API response time in milliseconds',
  `api_error` text DEFAULT NULL COMMENT 'API error message (if failed)',
  `trace_id` varchar(64) DEFAULT NULL COMMENT 'Request trace ID for full request tracking',
  `notes` text DEFAULT NULL COMMENT 'Additional context / reason for transition',
  `transitioned_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'When transition occurred',
  PRIMARY KEY (`id`),
  KEY `idx_consignment` (`consignment_id`),
  KEY `idx_trigger_user` (`trigger_user_id`),
  KEY `idx_trigger_job` (`trigger_job_id`),
  KEY `idx_trigger_webhook` (`trigger_webhook_id`),
  KEY `idx_trace_id` (`trace_id`),
  KEY `idx_transitioned_at` (`transitioned_at`),
  KEY `idx_from_to` (`from_status`,`to_status`),
  CONSTRAINT `queue_consignment_state_transitions_ibfk_1` FOREIGN KEY (`consignment_id`) REFERENCES `queue_consignments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=13914 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Full audit trail of all state transitions - ADR-002';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `queue_consignments`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `queue_consignments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `public_id` varchar(40) DEFAULT NULL,
  `vend_consignment_id` varchar(100) NOT NULL COMMENT 'Lightspeed consignment UUID',
  `lightspeed_consignment_id` varchar(100) DEFAULT NULL,
  `vend_version` int(10) unsigned DEFAULT 0 COMMENT 'Optimistic locking version from Vend',
  `type` enum('SUPPLIER','OUTLET','CUSTOMER','RETURN','STOCKTAKE') NOT NULL DEFAULT 'OUTLET',
  `transfer_category` enum('STOCK','JUICE','RETURN','PURCHASE_ORDER','INTERNAL','STOCKTAKE') NOT NULL DEFAULT 'STOCK',
  `status` enum('OPEN','SENT','DISPATCHED','RECEIVED','CANCELLED','STOCKTAKE','STOCKTAKE_SCHEDULED','STOCKTAKE_IN_PROGRESS','STOCKTAKE_IN_PROGRESS_PROCESSED','STOCKTAKE_COMPLETE') NOT NULL DEFAULT 'OPEN' COMMENT 'Current workflow state',
  `reference` varchar(255) DEFAULT NULL COMMENT 'PO number / Transfer reference',
  `name` text DEFAULT NULL COMMENT 'Internal notes / description',
  `source_outlet_id` varchar(100) DEFAULT NULL COMMENT 'Source outlet UUID (for OUTLET type)',
  `destination_outlet_id` varchar(100) DEFAULT NULL COMMENT 'Destination outlet UUID',
  `supplier_id` varchar(100) DEFAULT NULL COMMENT 'Supplier UUID (for SUPPLIER type)',
  `cis_user_id` int(10) unsigned DEFAULT NULL COMMENT 'CIS user who created this',
  `cis_purchase_order_id` int(10) unsigned DEFAULT NULL COMMENT 'Link to legacy PO table (if exists)',
  `cis_transfer_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `sent_at` timestamp NULL DEFAULT NULL COMMENT 'When marked SENT',
  `dispatched_at` timestamp NULL DEFAULT NULL COMMENT 'When marked DISPATCHED',
  `received_at` timestamp NULL DEFAULT NULL COMMENT 'When marked RECEIVED',
  `delivery_date` date DEFAULT NULL,
  `due_at` datetime DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL COMMENT 'When fully processed (CIS inventory updated)',
  `trace_id` varchar(64) DEFAULT NULL COMMENT 'Request trace ID for debugging',
  `last_sync_at` timestamp NULL DEFAULT NULL COMMENT 'Last successful Vend API sync',
  `is_migrated` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Flag for historical migration data',
  `sync_source` enum('CIS','LIGHTSPEED','MIGRATION') NOT NULL DEFAULT 'CIS' COMMENT 'Origin of consignment data',
  `sync_last_pulled_at` datetime DEFAULT NULL COMMENT 'Last sync FROM Lightspeed',
  `sync_last_pushed_at` datetime DEFAULT NULL COMMENT 'Last sync TO Lightspeed',
  `created_by_user_id` bigint(20) unsigned DEFAULT NULL,
  `approved_for_lightspeed` tinyint(1) DEFAULT 0,
  `approved_by_user_id` bigint(20) unsigned DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `pushed_to_lightspeed_at` datetime DEFAULT NULL,
  `lightspeed_push_attempts` int(11) DEFAULT 0,
  `lightspeed_push_error` text DEFAULT NULL,
  `upload_session_id` varchar(255) DEFAULT NULL,
  `upload_progress` int(11) DEFAULT 0,
  `upload_status` enum('pending','processing','completed','failed') DEFAULT 'pending',
  `upload_started_at` timestamp NULL DEFAULT NULL,
  `upload_completed_at` timestamp NULL DEFAULT NULL,
  `tracking_number` varchar(255) DEFAULT 'TRK-PENDING',
  `carrier` varchar(100) DEFAULT 'CourierPost',
  `delivery_type` enum('pickup','dropoff') DEFAULT 'dropoff',
  `pickup_location` varchar(255) DEFAULT NULL,
  `dropoff_location` varchar(255) DEFAULT NULL,
  `webhook_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Webhook payload data' CHECK (json_valid(`webhook_data`)),
  `received_by` varchar(100) DEFAULT NULL COMMENT 'Who/what received the consignment',
  `total_value` decimal(10,2) DEFAULT NULL,
  `total_cost` decimal(10,2) DEFAULT NULL,
  `item_count` int(11) DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL COMMENT 'User who soft-deleted',
  `deleted_at` timestamp NULL DEFAULT NULL COMMENT 'When soft deleted',
  PRIMARY KEY (`id`),
  UNIQUE KEY `vend_consignment_id` (`vend_consignment_id`),
  UNIQUE KEY `unique_public_id` (`public_id`),
  KEY `idx_vend_consignment_id` (`vend_consignment_id`),
  KEY `idx_type_status` (`type`,`status`),
  KEY `idx_destination_outlet` (`destination_outlet_id`),
  KEY `idx_source_outlet` (`source_outlet_id`),
  KEY `idx_supplier` (`supplier_id`),
  KEY `idx_cis_user` (`cis_user_id`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_status_updated` (`status`,`updated_at`),
  KEY `idx_trace_id` (`trace_id`),
  KEY `idx_lightspeed_consignment_id` (`lightspeed_consignment_id`),
  KEY `idx_trace_id_webhook` (`trace_id`),
  KEY `idx_received_at` (`received_at`),
  KEY `idx_transfer_category` (`transfer_category`),
  KEY `idx_deleted_at` (`deleted_at`)
) ENGINE=InnoDB AUTO_INCREMENT=774425 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Master consignment records synced with Lightspeed - ADR-002';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `vend_consignment_line_items`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `vend_consignment_line_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Transfer line ID',
  `transfer_id` int(11) NOT NULL COMMENT 'FK to transfers.id',
  `product_id` varchar(45) NOT NULL COMMENT 'Vend product UUID',
  `cis_product_id` int(10) unsigned DEFAULT NULL,
  `inventory_updated` tinyint(1) DEFAULT 0,
  `sku` varchar(100) DEFAULT NULL,
  `name` varchar(255) DEFAULT NULL,
  `unit_cost` decimal(10,4) DEFAULT 0.0000,
  `unit_price` decimal(10,4) DEFAULT 0.0000,
  `total_cost` decimal(10,2) DEFAULT 0.00,
  `total_price` decimal(10,2) DEFAULT 0.00,
  `status` enum('pending','sent','received','cancelled','damaged') DEFAULT 'pending',
  `sent_at` datetime DEFAULT NULL,
  `received_at` datetime DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `quantity` int(11) NOT NULL,
  `quantity_sent` int(11) DEFAULT 0,
  `quantity_received` int(11) DEFAULT 0,
  `confirmation_status` enum('pending','accepted','declined') NOT NULL DEFAULT 'pending' COMMENT 'Staff multi-store confirmation',
  `confirmed_by_store` int(11) DEFAULT NULL COMMENT 'UserID from supplying store who confirmed',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'Last time this transfer item was updated',
  `deleted_by` int(11) DEFAULT NULL COMMENT 'User ID of staff who soft-deleted this transfer item',
  `deleted_at` timestamp NULL DEFAULT NULL COMMENT 'When the transfer item was soft deleted',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_item_transfer_product` (`transfer_id`,`product_id`),
  UNIQUE KEY `uniq_transfer_product` (`transfer_id`,`product_id`),
  KEY `idx_item_transfer` (`transfer_id`),
  KEY `idx_item_product` (`product_id`),
  KEY `idx_item_confirm` (`confirmation_status`),
  KEY `idx_items_outstanding` (`transfer_id`,`confirmation_status`),
  KEY `idx_ti_transfer_product` (`transfer_id`,`product_id`),
  KEY `idx_items_transfer_status` (`transfer_id`,`confirmation_status`),
  KEY `idx_line_items_transfer_id` (`transfer_id`),
  KEY `idx_line_items_product_id` (`product_id`),
  KEY `idx_line_items_status` (`status`),
  KEY `idx_line_items_sku` (`sku`),
  KEY `idx_line_items_transfer_status` (`transfer_id`,`status`),
  KEY `idx_line_items_product_status` (`product_id`,`status`),
  KEY `idx_cis_product` (`cis_product_id`),
  CONSTRAINT `fk_items_transfer` FOREIGN KEY (`transfer_id`) REFERENCES `vend_consignments` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION,
  CONSTRAINT `chk_item_qtys_nonneg` CHECK (`quantity` >= 0 and `quantity_sent` >= 0 and `quantity_received` >= 0),
  CONSTRAINT `chk_item_qtys_bounds` CHECK (`quantity_sent` <= `quantity` and `quantity_received` <= `quantity_sent`)
) ENGINE=InnoDB AUTO_INCREMENT=286869 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Product lines for a transfer; tracks requested/sent/received and store confirmations.';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `vend_consignment_queue`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `vend_consignment_queue` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `trace_id` varchar(100) NOT NULL,
  `correlation_id` varchar(100) DEFAULT NULL COMMENT 'Link related operations',
  `transfer_id` int(10) unsigned NOT NULL,
  `external_ref` varchar(255) DEFAULT NULL COMMENT 'External reference (e.g. TRANSFER-12345)',
  `idempotency_key` varchar(255) NOT NULL COMMENT 'Prevents duplicate processing',
  `source_outlet_id` varchar(50) NOT NULL COMMENT 'Source outlet UUID',
  `destination_outlet_id` varchar(50) NOT NULL COMMENT 'Destination outlet UUID',
  `request_payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT 'Full request to Vend API' CHECK (json_valid(`request_payload`)),
  `status` enum('pending','processing','completed','failed','cancelled','dead_letter') NOT NULL DEFAULT 'pending',
  `priority` tinyint(3) unsigned NOT NULL DEFAULT 5 COMMENT '1=highest, 10=lowest',
  `attempt_count` int(10) unsigned NOT NULL DEFAULT 0 COMMENT 'Number of attempts made',
  `max_attempts` int(10) unsigned NOT NULL DEFAULT 5 COMMENT 'Max retry attempts',
  `next_retry_at` timestamp NULL DEFAULT NULL COMMENT 'Scheduled next retry time',
  `last_error` text DEFAULT NULL COMMENT 'Last error message',
  `last_error_code` varchar(50) DEFAULT NULL COMMENT 'Last error code',
  `last_http_status` int(10) unsigned DEFAULT NULL COMMENT 'Last HTTP status code',
  `vend_consignment_id` varchar(100) DEFAULT NULL COMMENT 'Vend consignment UUID on success',
  `vend_response` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Full Vend API response' CHECK (json_valid(`vend_response`)),
  `total_processing_ms` bigint(20) unsigned DEFAULT NULL COMMENT 'Total processing time across all attempts',
  `last_attempt_ms` int(10) unsigned DEFAULT NULL COMMENT 'Duration of last attempt',
  `api_latency_ms` int(10) unsigned DEFAULT NULL COMMENT 'Vend API latency',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `processing_started_at` timestamp NULL DEFAULT NULL COMMENT 'When processing started',
  `completed_at` timestamp NULL DEFAULT NULL COMMENT 'When successfully completed',
  PRIMARY KEY (`id`),
  UNIQUE KEY `idempotency_key` (`idempotency_key`),
  KEY `idx_status` (`status`,`priority`,`next_retry_at`),
  KEY `idx_transfer` (`transfer_id`),
  KEY `idx_retry` (`next_retry_at`,`status`),
  KEY `idx_created` (`created_at`),
  KEY `idx_trace` (`trace_id`),
  KEY `idx_idempotency` (`idempotency_key`),
  KEY `idx_vend` (`vend_consignment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Queue for Vend consignment stock synchronization with retry logic';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `vend_consignments`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `vend_consignments` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'CIS transfer ID (primary)',
  `public_id` varchar(40) NOT NULL,
  `vend_transfer_id` char(36) DEFAULT NULL COMMENT 'Vend/Lightspeed consignment UUID (unique when present)',
  `vend_consignment_id` varchar(64) DEFAULT NULL COMMENT 'Legacy/alternate Vend consignment UUID',
  `consignment_id` bigint(20) unsigned DEFAULT NULL COMMENT 'Link to queue_consignments.id for Lightspeed sync',
  `transfer_category` enum('STOCK','JUICE','RETURN','PURCHASE_ORDER','INTERNAL','STOCKTAKE') NOT NULL DEFAULT 'STOCK',
  `creation_method` enum('MANUAL','AUTOMATED') NOT NULL DEFAULT 'MANUAL',
  `vend_number` varchar(64) DEFAULT NULL,
  `vend_url` varchar(255) DEFAULT NULL,
  `vend_origin` enum('CONSIGNMENT','PURCHASE_ORDER','TRANSFER') DEFAULT NULL,
  `outlet_from` varchar(100) NOT NULL COMMENT 'Source outlet UUID (Vend)',
  `outlet_to` varchar(100) NOT NULL COMMENT 'Destination outlet UUID (Vend)',
  `created_by` int(11) NOT NULL COMMENT 'CIS user who created this transfer',
  `staff_transfer_id` int(10) unsigned DEFAULT NULL COMMENT 'Link to existing staff_transfers.id (unsigned)',
  `supplier_id` varchar(100) DEFAULT NULL COMMENT 'Supplier UUID - for queries and portal filtering',
  `supplier_invoice_number` varchar(100) DEFAULT NULL,
  `supplier_reference` varchar(100) DEFAULT NULL,
  `tracking_number` varchar(100) DEFAULT NULL,
  `tracking_carrier` varchar(50) DEFAULT NULL,
  `tracking_url` varchar(255) DEFAULT NULL,
  `tracking_updated_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expected_delivery_date` date DEFAULT NULL,
  `due_at` datetime DEFAULT NULL,
  `sent_at` datetime DEFAULT NULL,
  `received_at` datetime DEFAULT NULL,
  `supplier_sent_at` timestamp NULL DEFAULT NULL,
  `supplier_cancelled_at` timestamp NULL DEFAULT NULL,
  `supplier_acknowledged_at` timestamp NULL DEFAULT NULL COMMENT 'Timestamp when supplier first viewed the PO in portal',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `last_transaction_id` varchar(50) DEFAULT NULL COMMENT 'Last transaction that modified this transfer',
  `version` int(11) DEFAULT 1 COMMENT 'Optimistic locking version',
  `locked_at` datetime DEFAULT NULL COMMENT 'When transfer was locked',
  `locked_by` int(11) DEFAULT NULL COMMENT 'User who locked transfer',
  `lock_expires_at` datetime DEFAULT NULL COMMENT 'When lock expires',
  `deleted_by` int(11) DEFAULT NULL COMMENT 'User ID of staff who soft-deleted this transfer',
  `deleted_at` timestamp NULL DEFAULT NULL COMMENT 'When the transfer was soft deleted',
  `customer_id` varchar(45) DEFAULT NULL,
  `state` enum('DRAFT','OPEN','PACKING','PACKAGED','SENT','RECEIVING','PARTIAL','RECEIVED','CLOSED','CANCELLED','ARCHIVED') NOT NULL DEFAULT 'OPEN',
  `total_boxes` int(10) unsigned NOT NULL DEFAULT 0,
  `total_weight_g` bigint(20) unsigned NOT NULL DEFAULT 0,
  `total_count` int(11) DEFAULT 0,
  `total_cost` decimal(10,2) DEFAULT 0.00,
  `total_received` int(11) DEFAULT 0,
  `line_item_count` int(11) DEFAULT 0,
  `draft_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`draft_data`)),
  `draft_updated_at` timestamp NULL DEFAULT NULL,
  `lightspeed_sync_status` enum('pending','synced','failed') DEFAULT 'pending',
  `lightspeed_last_sync_at` timestamp NULL DEFAULT NULL,
  `lightspeed_push_attempts` int(11) DEFAULT 0,
  `lightspeed_push_error` text DEFAULT NULL,
  `status` enum('DRAFT','OPEN','SENT','DISPATCHED','RECEIVED','CANCELLED','STOCKTAKE') DEFAULT 'OPEN',
  `type` enum('SUPPLIER','OUTLET','CUSTOMER','RETURN') DEFAULT 'OUTLET',
  `consignment_notes` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_transfers_public_id` (`public_id`),
  UNIQUE KEY `uniq_transfers_vend_uuid` (`vend_transfer_id`),
  KEY `idx_transfers_from_status_date` (`outlet_from`,`created_at`),
  KEY `idx_transfers_to_status_date` (`outlet_to`,`created_at`),
  KEY `idx_transfers_staff` (`staff_transfer_id`),
  KEY `idx_transfers_created` (`created_at`),
  KEY `idx_transfers_type_created` (`created_at`),
  KEY `idx_transfers_to_created` (`outlet_to`,`created_at`),
  KEY `idx_transfers_vend` (`vend_transfer_id`),
  KEY `idx_transfers_customer` (`customer_id`),
  KEY `idx_transfers_state` (`state`),
  KEY `idx_consignment_id` (`consignment_id`),
  KEY `idx_transfers_type_status_created` (`created_at`),
  KEY `idx_transfers_vend_number` (`vend_number`),
  KEY `idx_transfers_category` (`transfer_category`),
  KEY `idx_transfers_creation_method` (`creation_method`),
  KEY `idx_transfers_from_to_state` (`outlet_from`,`outlet_to`,`state`),
  KEY `idx_transfers_created_at` (`created_at`),
  KEY `idx_supplier_id` (`supplier_id`),
  KEY `idx_last_transaction` (`last_transaction_id`),
  KEY `idx_version` (`version`),
  KEY `idx_locked_at` (`locked_at`),
  KEY `idx_lock_expires` (`lock_expires_at`),
  KEY `idx_expected_delivery` (`expected_delivery_date`,`state`),
  KEY `idx_supplier_actions` (`supplier_sent_at`,`supplier_cancelled_at`),
  KEY `idx_supplier_acknowledged` (`supplier_acknowledged_at`),
  KEY `idx_consignments_public_id` (`public_id`),
  KEY `idx_consignments_outlet_to` (`outlet_to`),
  KEY `idx_consignments_state` (`state`),
  KEY `idx_consignments_created` (`created_at`),
  KEY `idx_consignments_state_outlet` (`state`,`outlet_to`,`created_at`),
  KEY `idx_tracking_number` (`tracking_number`),
  KEY `idx_tracking_updated_at` (`tracking_updated_at`),
  CONSTRAINT `fk_transfers_consignment` FOREIGN KEY (`consignment_id`) REFERENCES `queue_consignments` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_transfers_customer` FOREIGN KEY (`customer_id`) REFERENCES `vend_customers` (`id`),
  CONSTRAINT `fk_transfers_staff` FOREIGN KEY (`staff_transfer_id`) REFERENCES `staff_transfers` (`id`) ON DELETE SET NULL ON UPDATE NO ACTION,
  CONSTRAINT `chk_transfers_outlets_diff` CHECK (`outlet_from` <> `outlet_to`)
) ENGINE=InnoDB AUTO_INCREMENT=28607 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Atomic outlet→outlet transfer. Single Vend consignment with strict lifecycle & Vend UUID.';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8 */ ;
/*!50003 SET character_set_results = utf8 */ ;
/*!50003 SET collation_connection  = utf8_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`jcepnzzkmj`@`%`*/ /*!50003 TRIGGER bi_transfers_public_id 
BEFORE INSERT ON `vend_consignments` FOR EACH ROW
BEGIN
  DECLARE v_period VARCHAR(10); 
  DECLARE v_assigned BIGINT UNSIGNED; 
  DECLARE v_seq BIGINT UNSIGNED; 
  DECLARE v_num BIGINT UNSIGNED; 
  DECLARE v_cd INT; 
  DECLARE v_type VARCHAR(32); 
  DECLARE v_code VARCHAR(6);
  
  IF NEW.public_id IS NULL OR NEW.public_id = '' THEN
    SET v_period = DATE_FORMAT(NOW(), '%Y%m');
    SET v_type = UPPER(IFNULL(NEW.transfer_category, 'GENERIC'));
    SET v_code = UPPER(REPLACE(SUBSTRING(v_type,1,3), ' ', ''));
    
    INSERT INTO ls_id_sequences (seq_type, period, next_value) 
    VALUES ('transfer', v_period, 2)
    ON DUPLICATE KEY UPDATE 
      next_value = LAST_INSERT_ID(next_value + 1), 
      updated_at = NOW();
    
    SET v_seq = LAST_INSERT_ID();
    SET v_assigned = IF(v_seq > 1, v_seq - 1, 1);
    SET v_num = CAST(CONCAT(v_period, LPAD(v_assigned,6,'0')) AS UNSIGNED);
    SET v_cd = (98 - MOD(v_num, 97)); 
    IF v_cd = 98 THEN SET v_cd = 0; END IF;
    
    SET NEW.public_id = CONCAT('TR-', v_code, '-', v_period, '-', LPAD(v_assigned,6,'0'), '-', LPAD(v_cd,2,'0'));
  END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`jcepnzzkmj`@`%`*/ /*!50003 TRIGGER `trg_notify_new_transfer_for_supplier` 
AFTER INSERT ON `vend_consignments` FOR EACH ROW
BEGIN
    -- Only create notification if transfer has a supplier and is PURCHASE_ORDER category
    IF NEW.supplier_id IS NOT NULL 
       AND NEW.transfer_category = 'PURCHASE_ORDER' 
       AND NEW.state IN ('OPEN', 'SENT') THEN
        
        INSERT INTO `supplier_portal_notifications` (
            supplier_id,
            type,
            title,
            message,
            related_type,
            related_id,
            created_at
        ) VALUES (
            NEW.supplier_id,
            'new_purchase_order',
            'New Purchase Order Received',
            CONCAT('Purchase Order #', NEW.public_id, ' has been created for ', 
                   (SELECT name FROM vend_outlets WHERE id = NEW.outlet_to AND deleted_at = '0000-00-00 00:00:00' LIMIT 1)),
            'transfer',
            NEW.id,
            NOW()
        );
    END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`jcepnzzkmj`@`%`*/ /*!50003 TRIGGER `trg_notify_transfer_state_change` 
AFTER UPDATE ON `vend_consignments` FOR EACH ROW
BEGIN
    -- Only notify supplier if state changed and has supplier_id
    IF NEW.supplier_id IS NOT NULL 
       AND NEW.transfer_category = 'PURCHASE_ORDER'
       AND OLD.state != NEW.state THEN
        
        -- Determine notification type and message based on state
        SET @notif_type = CASE NEW.state
            WHEN 'RECEIVED' THEN 'transfer_received'
            WHEN 'CANCELLED' THEN 'transfer_cancelled'
            WHEN 'CLOSED' THEN 'transfer_completed'
            ELSE 'transfer_updated'
        END;
        
        SET @notif_title = CASE NEW.state
            WHEN 'RECEIVED' THEN 'Purchase Order Received'
            WHEN 'CANCELLED' THEN 'Purchase Order Cancelled'
            WHEN 'CLOSED' THEN 'Purchase Order Completed'
            ELSE 'Purchase Order Updated'
        END;
        
        SET @notif_message = CONCAT('PO #', NEW.public_id, 
                                   ' status changed from ', OLD.state, 
                                   ' to ', NEW.state);
        
        INSERT INTO `supplier_portal_notifications` (
            supplier_id,
            type,
            title,
            message,
            related_type,
            related_id,
            created_at
        ) VALUES (
            NEW.supplier_id,
            @notif_type,
            @notif_title,
            @notif_message,
            'transfer',
            NEW.id,
            NOW()
        );
    END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`jcepnzzkmj`@`%`*/ /*!50003 TRIGGER `trg_set_supplier_acknowledged`
AFTER UPDATE ON `vend_consignments` FOR EACH ROW
BEGIN
    -- Only set once when supplier first views (would be set by portal code)
    IF NEW.supplier_acknowledged_at IS NOT NULL 
       AND OLD.supplier_acknowledged_at IS NULL THEN
        
        INSERT INTO `supplier_portal_notifications` (
            supplier_id,
            type,
            title,
            message,
            related_type,
            related_id,
            created_at
        ) VALUES (
            NEW.supplier_id,
            'po_acknowledged',
            'PO Acknowledged',
            CONCAT('Thank you for acknowledging PO #', NEW.public_id),
            'transfer',
            NEW.id,
            NOW()
        );
    END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `vend_consignments_backup_before_po_migration`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `vend_consignments_backup_before_po_migration` (
  `id` int(11) NOT NULL DEFAULT 0 COMMENT 'CIS transfer ID (primary)',
  `public_id` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `vend_transfer_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Vend/Lightspeed consignment UUID (unique when present)',
  `consignment_id` bigint(20) unsigned DEFAULT NULL COMMENT 'Link to queue_consignments.id for Lightspeed sync',
  `transfer_category` enum('STOCK','JUICE','RETURN','PURCHASE_ORDER','INTERNAL','STOCKTAKE') NOT NULL DEFAULT 'STOCK',
  `creation_method` enum('MANUAL','AUTOMATED') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'MANUAL',
  `vend_number` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `vend_url` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `vend_origin` enum('CONSIGNMENT','PURCHASE_ORDER','TRANSFER') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `outlet_from` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Source outlet UUID (Vend)',
  `outlet_to` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Destination outlet UUID (Vend)',
  `created_by` int(11) NOT NULL COMMENT 'CIS user who created this transfer',
  `staff_transfer_id` int(10) unsigned DEFAULT NULL COMMENT 'Link to existing staff_transfers.id (unsigned)',
  `supplier_id` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Supplier UUID - for queries and portal filtering',
  `supplier_invoice_number` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `supplier_reference` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expected_delivery_date` date DEFAULT NULL,
  `due_at` datetime DEFAULT NULL,
  `sent_at` datetime DEFAULT NULL,
  `received_at` datetime DEFAULT NULL,
  `supplier_sent_at` timestamp NULL DEFAULT NULL,
  `supplier_cancelled_at` timestamp NULL DEFAULT NULL,
  `supplier_acknowledged_at` timestamp NULL DEFAULT NULL COMMENT 'Timestamp when supplier first viewed the PO in portal',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `last_transaction_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Last transaction that modified this transfer',
  `version` int(11) DEFAULT 1 COMMENT 'Optimistic locking version',
  `locked_at` datetime DEFAULT NULL COMMENT 'When transfer was locked',
  `locked_by` int(11) DEFAULT NULL COMMENT 'User who locked transfer',
  `lock_expires_at` datetime DEFAULT NULL COMMENT 'When lock expires',
  `deleted_by` int(11) DEFAULT NULL COMMENT 'User ID of staff who soft-deleted this transfer',
  `deleted_at` timestamp NULL DEFAULT NULL COMMENT 'When the transfer was soft deleted',
  `customer_id` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `state` enum('DRAFT','OPEN','PACKING','PACKAGED','SENT','RECEIVING','PARTIAL','RECEIVED','CLOSED','CANCELLED','ARCHIVED') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'OPEN',
  `total_boxes` int(10) unsigned NOT NULL DEFAULT 0,
  `total_weight_g` bigint(20) unsigned NOT NULL DEFAULT 0,
  `total_count` int(11) DEFAULT 0,
  `total_cost` decimal(10,2) DEFAULT 0.00,
  `total_received` int(11) DEFAULT 0,
  `line_item_count` int(11) DEFAULT 0,
  `draft_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`draft_data`)),
  `draft_updated_at` timestamp NULL DEFAULT NULL,
  `vend_consignment_id` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `lightspeed_sync_status` enum('pending','synced','failed') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
  `lightspeed_last_sync_at` timestamp NULL DEFAULT NULL,
  `lightspeed_push_attempts` int(11) DEFAULT 0,
  `lightspeed_push_error` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('STOCKTAKE','OPEN','SENT','RECEIVED','CANCELLED','DRAFT') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'OPEN',
  `type` enum('SUPPLIER','OUTLET','CUSTOMER','RETURN') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'OUTLET',
  `consignment_notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `webhook_consignment_events`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `webhook_consignment_events` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `consignment_id` bigint(20) unsigned NOT NULL,
  `webhook_type` enum('consignment.send','consignment.receive') NOT NULL,
  `lightspeed_consignment_id` varchar(100) DEFAULT NULL,
  `webhook_payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`webhook_payload`)),
  `status` enum('pending','processing','completed','failed') DEFAULT 'pending',
  `processing_attempts` int(11) DEFAULT 0,
  `processed_at` timestamp NULL DEFAULT NULL,
  `error_message` text DEFAULT NULL,
  `trace_id` varchar(64) DEFAULT NULL,
  `created_by` varchar(100) DEFAULT 'WEBHOOK_SYSTEM',
  `updated_by` varchar(100) DEFAULT 'WEBHOOK_SYSTEM',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_consignment_id` (`consignment_id`),
  KEY `idx_webhook_type` (`webhook_type`),
  KEY `idx_status` (`status`),
  KEY `idx_trace_id` (`trace_id`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_lightspeed_id` (`lightspeed_consignment_id`)
) ENGINE=InnoDB AUTO_INCREMENT=848 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci COMMENT='Tracks consignment webhook events and processing';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Temporary table structure for view `webhook_consignment_status`
--

SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8mb4;
/*!50001 CREATE VIEW `webhook_consignment_status` AS SELECT
 1 AS `consignment_id`,
  1 AS `reference`,
  1 AS `consignment_status`,
  1 AS `lightspeed_consignment_id`,
  1 AS `sync_direction`,
  1 AS `sync_status`,
  1 AS `webhook_triggered`,
  1 AS `last_sync_at`,
  1 AS `webhook_type`,
  1 AS `webhook_status`,
  1 AS `webhook_processed_at`,
  1 AS `consignment_created`,
  1 AS `consignment_updated`,
  1 AS `trace_id` */;
SET character_set_client = @saved_cs_client;

--
-- Table structure for table `webhook_consignment_sync_status`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `webhook_consignment_sync_status` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `consignment_id` bigint(20) unsigned NOT NULL,
  `sync_direction` enum('CIS_TO_LIGHTSPEED','LIGHTSPEED_TO_CIS') NOT NULL,
  `status` enum('pending','in_progress','completed','failed') DEFAULT 'pending',
  `sync_attempts` int(11) DEFAULT 0,
  `last_sync_at` timestamp NULL DEFAULT NULL,
  `last_error` text DEFAULT NULL,
  `webhook_triggered` tinyint(1) DEFAULT 0,
  `trace_id` varchar(64) DEFAULT NULL,
  `created_by` varchar(100) DEFAULT 'WEBHOOK_SYSTEM',
  `updated_by` varchar(100) DEFAULT 'WEBHOOK_SYSTEM',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_consignment_direction` (`consignment_id`,`sync_direction`),
  KEY `idx_status` (`status`),
  KEY `idx_webhook_triggered` (`webhook_triggered`),
  KEY `idx_last_sync_at` (`last_sync_at`),
  KEY `idx_trace_id` (`trace_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci COMMENT='Tracks bi-directional consignment sync status';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Final view structure for view `webhook_consignment_status`
--

/*!50001 DROP VIEW IF EXISTS `webhook_consignment_status`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`jcepnzzkmj`@`%` SQL SECURITY DEFINER */
/*!50001 VIEW `webhook_consignment_status` AS select `c`.`id` AS `consignment_id`,`c`.`reference` AS `reference`,`c`.`status` AS `consignment_status`,`c`.`lightspeed_consignment_id` AS `lightspeed_consignment_id`,`css`.`sync_direction` AS `sync_direction`,`css`.`status` AS `sync_status`,`css`.`webhook_triggered` AS `webhook_triggered`,`css`.`last_sync_at` AS `last_sync_at`,`cwe`.`webhook_type` AS `webhook_type`,`cwe`.`status` AS `webhook_status`,`cwe`.`processed_at` AS `webhook_processed_at`,`c`.`created_at` AS `consignment_created`,`c`.`updated_at` AS `consignment_updated`,`c`.`trace_id` AS `trace_id` from ((`queue_consignments` `c` left join `webhook_consignment_sync_status` `css` on(`c`.`id` = `css`.`consignment_id`)) left join `webhook_consignment_events` `cwe` on(`c`.`id` = `cwe`.`consignment_id`)) where `c`.`lightspeed_consignment_id` is not null order by `c`.`updated_at` desc */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2025-10-31 10:14:04
