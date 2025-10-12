# Transfer System Database Schema
**Complete Table Documentation**  
**Last Updated:** October 12, 2025  
**Status:** Production Schema v2.0

---

## 📋 Table of Contents

1. [System Overview](#system-overview)
2. [Core Transfer Tables](#core-transfer-tables)
3. [Transfer Lifecycle Tables](#transfer-lifecycle-tables)
4. [AI & Analytics Tables](#ai--analytics-tables)
5. [Logging & Audit Tables](#logging--audit-tables)
6. [Queue & Performance Tables](#queue--performance-tables)
7. [Media & Upload Tables](#media--upload-tables)
8. [Validation & Configuration Tables](#validation--configuration-tables)
9. [Transfer Modes](#transfer-modes)
10. [Table Relationships](#table-relationships)
11. [Key Indexes](#key-indexes)

---

## 🎯 System Overview

### What This System Does

The **Transfer System** manages stock movement between **17 retail outlets** across New Zealand. It handles:

- **Stock Transfers** (general inventory movement)
- **Juice Transfers** (e-liquid/vape juice specifically)
- **Staff Transfers** (employee personal orders)
- **Purchase Orders** (supplier → store)

### 4 Transfer Modes

Each mode has **custom logic and workflows**:

1. **GENERAL** (stock) - Standard outlet→outlet transfers
2. **JUICE** - E-liquid transfers (regulatory compliance, nicotine tracking)  
3. **STAFF** - Employee personal orders (special pricing, approval workflows)
4. **SUPPLIER** - Purchase orders (HQ→supplier)

### Architecture Highlights

- **Vend/Lightspeed Integration** - Two-way sync with POS system
- **Multi-shipment Support** - Transfers can have multiple shipments (partial fulfillment)
- **Parcel Tracking** - Box-level tracking with courier integration
- **AI Decision Engine** - Neural network for freight optimization
- **Comprehensive Audit Trail** - Every action logged with compliance retention
- **Queue System** - Background processing for Vend sync with retry logic
- **Performance Monitoring** - Real-time metrics and alerting

---

## 📦 Core Transfer Tables

### `transfers` (Main Entity)
**Purpose:** Atomic outlet→outlet transfer record  
**Rows:** ~26,915 transfers  
**Key Concept:** ONE transfer = ONE Vend consignment

```sql
CREATE TABLE `transfers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(40) NOT NULL COMMENT 'User-facing ID (e.g., TR-12345)',
  `vend_transfer_id` char(36) DEFAULT NULL COMMENT 'Vend UUID',
  `consignment_id` bigint(20) unsigned DEFAULT NULL COMMENT 'Queue link',
  `vend_resource` enum('consignment','purchase_order','transfer') NOT NULL,
  `vend_number` varchar(64) DEFAULT NULL,
  `vend_url` varchar(255) DEFAULT NULL,
  
  -- Transfer Classification
  `type` enum('stock','juice','staff','purchase_order','return') NOT NULL DEFAULT 'stock',
  `transfer_type` enum('GENERAL','JUICE','STAFF','AUTOMATED') DEFAULT 'GENERAL',
  
  -- Lifecycle Status
  `status` enum('draft','open','sent','partial','received','cancelled','archived') NOT NULL DEFAULT 'draft',
  `state` enum('OPEN','PACKING','PACKAGED','SENT','RECEIVING','RECEIVED','CLOSED','CANCELLED') NOT NULL DEFAULT 'OPEN',
  
  -- Outlets
  `outlet_from` varchar(100) NOT NULL COMMENT 'Source outlet UUID',
  `outlet_to` varchar(100) NOT NULL COMMENT 'Destination outlet UUID',
  
  -- Ownership
  `created_by` int(11) NOT NULL COMMENT 'CIS user ID',
  `staff_transfer_id` int(10) unsigned DEFAULT NULL COMMENT 'If staff type',
  `customer_id` varchar(45) DEFAULT NULL COMMENT 'If customer-related',
  
  -- Physical Details
  `total_boxes` int(10) unsigned NOT NULL DEFAULT 0,
  `total_weight_g` bigint(20) unsigned NOT NULL DEFAULT 0,
  
  -- Draft Support
  `draft_data` JSON DEFAULT NULL COMMENT 'Unsaved UI state',
  `draft_updated_at` timestamp NULL DEFAULT NULL,
  
  -- Timestamps
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_by` int(11) DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_transfers_public_id` (`public_id`),
  UNIQUE KEY `uniq_transfers_vend_uuid` (`vend_transfer_id`),
  CONSTRAINT `chk_transfers_outlets_diff` CHECK (`outlet_from` <> `outlet_to`)
) ENGINE=InnoDB;
```

**Key Fields:**
- `public_id` - User-facing ID (TR-12345)
- `vend_transfer_id` - Vend consignment UUID (syncs to POS)
- `type` vs `transfer_type` - Legacy dual classification (consolidating)
- `status` - Business workflow state (draft→open→sent→received)
- `state` - Detailed UI state machine (OPEN→PACKING→SENT→RECEIVING)

**Status Flow:**
```
draft → open → sent → partial → received
            ↓                      ↓
        cancelled              archived
```

---

### `transfer_items` (Product Lines)
**Purpose:** Products in a transfer (requested/sent/received quantities)  
**Rows:** ~253,005 items  
**Key Concept:** Tracks cumulative quantities across multiple shipments

```sql
CREATE TABLE `transfer_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `transfer_id` int(11) NOT NULL,
  `product_id` varchar(45) NOT NULL COMMENT 'Vend product UUID',
  
  -- Quantity Tracking
  `qty_requested` int(11) NOT NULL COMMENT 'Original request',
  `qty_sent_total` int(11) DEFAULT 0 COMMENT 'Cumulative sent (all shipments)',
  `qty_received_total` int(11) DEFAULT 0 COMMENT 'Cumulative received',
  
  -- Multi-Store Confirmation
  `confirmation_status` enum('pending','accepted','declined') NOT NULL DEFAULT 'pending',
  `confirmed_by_store` int(11) DEFAULT NULL COMMENT 'User ID from supplying store',
  
  -- Timestamps
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_by` int(11) DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_item_transfer_product` (`transfer_id`,`product_id`),
  CONSTRAINT `fk_items_transfer` FOREIGN KEY (`transfer_id`) REFERENCES `transfers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `chk_item_qtys_nonneg` CHECK (`qty_requested` >= 0 AND `qty_sent_total` >= 0 AND `qty_received_total` >= 0),
  CONSTRAINT `chk_item_qtys_bounds` CHECK (`qty_sent_total` <= `qty_requested` AND `qty_received_total` <= `qty_sent_total`)
) ENGINE=InnoDB;
```

**Business Rules:**
- ✅ `qty_sent_total` ≤ `qty_requested`
- ✅ `qty_received_total` ≤ `qty_sent_total`
- ✅ One row per transfer × product (unique constraint)
- ✅ Confirmation required from supplying store

---

## 🚚 Transfer Lifecycle Tables

### `transfer_shipments` (Partial Shipments)
**Purpose:** Multiple shipment waves per transfer  
**Rows:** ~5,280 shipments  
**Key Concept:** Supports partial fulfillment (send 100 units in 3 boxes today, 50 more tomorrow)

```sql
CREATE TABLE `transfer_shipments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `transfer_id` int(11) NOT NULL,
  
  -- Delivery Method
  `delivery_mode` enum('auto','manual','dropoff','pickup','courier','internal_drive') NOT NULL DEFAULT 'auto',
  
  -- Destination Details
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
  
  -- Shipment Status
  `status` enum('packed','in_transit','partial','received','cancelled') NOT NULL DEFAULT 'packed',
  
  -- Packing Details
  `packed_at` timestamp NULL DEFAULT NULL,
  `packed_by` int(11) DEFAULT NULL,
  `received_at` timestamp NULL DEFAULT NULL,
  `received_by` int(11) DEFAULT NULL,
  
  -- Internal Drive Support
  `driver_staff_id` int(11) DEFAULT NULL COMMENT 'If internal_drive',
  
  -- Regulatory
  `nicotine_in_shipment` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Compliance flag',
  
  -- Courier Tracking
  `carrier_name` varchar(120) DEFAULT NULL,
  `tracking_number` varchar(120) DEFAULT NULL,
  `tracking_url` varchar(300) DEFAULT NULL,
  `dispatched_at` datetime DEFAULT NULL,
  
  -- Timestamps
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_by` int(11) DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_shipments_transfer` FOREIGN KEY (`transfer_id`) REFERENCES `transfers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB;
```

**Delivery Modes:**
1. **auto** - System decides courier vs internal
2. **manual** - Staff manually arranged
3. **dropoff** - Store staff drops off
4. **pickup** - Destination staff picks up
5. **courier** - NZ Post, GSS, etc.
6. **internal_drive** - Company vehicle delivery

---

### `transfer_parcels` (Box-Level Tracking)
**Purpose:** Individual boxes/cartons within a shipment  
**Rows:** ~3,556 parcels  
**Key Concept:** Box-by-box receiving with condition tracking

```sql
CREATE TABLE `transfer_parcels` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `shipment_id` int(11) NOT NULL,
  `box_number` int(11) NOT NULL COMMENT '1..N within shipment',
  `parcel_number` int(11) NOT NULL DEFAULT 1,
  
  -- Tracking
  `tracking_number` varchar(191) DEFAULT NULL,
  `tracking_ref_raw` text DEFAULT NULL,
  `courier` varchar(50) DEFAULT NULL,
  
  -- Physical Dimensions
  `weight_grams` int(10) unsigned DEFAULT NULL,
  `weight_kg` decimal(10,2) DEFAULT NULL,
  `length_mm` int(10) unsigned DEFAULT NULL,
  `width_mm` int(10) unsigned DEFAULT NULL,
  `height_mm` int(10) unsigned DEFAULT NULL,
  
  -- Label
  `label_url` varchar(255) DEFAULT NULL,
  
  -- Status
  `status` enum('pending','labelled','manifested','in_transit','received','missing','damaged','cancelled','exception') NOT NULL DEFAULT 'pending',
  `notes` mediumtext DEFAULT NULL,
  `received_at` timestamp NULL DEFAULT NULL,
  
  -- Timestamps
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_by` int(11) DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_parcel_boxnum` (`shipment_id`,`box_number`),
  CONSTRAINT `fk_parcels_shipment` FOREIGN KEY (`shipment_id`) REFERENCES `transfer_shipments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB;
```

**Parcel Status Flow:**
```
pending → labelled → manifested → in_transit → received
                                      ↓
                                  missing / damaged / exception
```

---

### `transfer_shipment_items` (Wave Items)
**Purpose:** Which products are in which shipment wave  
**Key Concept:** Links transfer_items to shipments (many-to-many)

```sql
CREATE TABLE `transfer_shipment_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `shipment_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL COMMENT 'FK to transfer_items.id',
  `qty_sent` int(11) NOT NULL COMMENT 'Qty in THIS wave',
  `qty_received` int(11) NOT NULL DEFAULT 0 COMMENT 'Received for this wave',
  
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_shipment_item` (`shipment_id`,`item_id`),
  CONSTRAINT `fk_tsi_shipment` FOREIGN KEY (`shipment_id`) REFERENCES `transfer_shipments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_tsi_item` FOREIGN KEY (`item_id`) REFERENCES `transfer_items` (`id`) ON DELETE CASCADE,
  CONSTRAINT `chk_tsi_qtys_bounds` CHECK (`qty_received` <= `qty_sent`)
) ENGINE=InnoDB;
```

---

### `transfer_parcel_items` (Box Contents)
**Purpose:** Which products are in which specific box  
**Key Concept:** Most granular level - box × product with receive confirmation

```sql
CREATE TABLE `transfer_parcel_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `parcel_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL COMMENT 'FK to transfer_items.id',
  `qty` int(11) NOT NULL DEFAULT 0 COMMENT 'Qty in this box',
  `qty_received` int(11) NOT NULL DEFAULT 0,
  `locked_at` timestamp NULL DEFAULT current_timestamp() COMMENT 'Immutable after insert',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_parcel_item` (`parcel_id`,`item_id`),
  CONSTRAINT `fk_tpi_parcel` FOREIGN KEY (`parcel_id`) REFERENCES `transfer_parcels` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_tpi_item` FOREIGN KEY (`item_id`) REFERENCES `transfer_items` (`id`) ON DELETE CASCADE,
  CONSTRAINT `chk_tpi_bounds` CHECK (`qty_received` <= `qty`)
) ENGINE=InnoDB;
```

**Immutability:** Once created, rows are locked (audit trail integrity)

---

## 🧠 AI & Analytics Tables

### `transfer_ai_audit_log` (AI Decision Tracking)
**Purpose:** Complete audit trail for all AI decisions  
**Key Concept:** Governance, explainability, model versioning

```sql
CREATE TABLE `transfer_ai_audit_log` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `trace_id` varchar(100) NOT NULL COMMENT 'Distributed tracing',
  `decision_id` bigint(20) unsigned DEFAULT NULL COMMENT 'FK to ai_freight_decisions',
  
  -- Model Identification
  `model_name` varchar(100) NOT NULL COMMENT 'FreightAI, NeuroLink, AutoPack',
  `model_version` varchar(50) NOT NULL COMMENT 'For reproducibility',
  `algorithm` varchar(50) DEFAULT NULL COMMENT 'UCB1, epsilon-greedy, gradient-boost',
  
  -- Context
  `transfer_id` int(10) unsigned DEFAULT NULL,
  `input_features` JSON NOT NULL COMMENT 'All features used',
  `context_key` varchar(255) DEFAULT NULL COMMENT 'Contextual bandit bucket',
  
  -- Decision
  `recommendation` JSON NOT NULL COMMENT 'AI recommended action',
  `confidence_score` decimal(5,4) NOT NULL COMMENT '0.0000 to 1.0000',
  `alternative_options` JSON DEFAULT NULL COMMENT 'Other options with scores',
  `exploration_mode` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Exploring vs exploiting',
  
  -- Predictions
  `estimated_savings_nzd` decimal(10,2) DEFAULT NULL,
  `estimated_time_mins` int(10) unsigned DEFAULT NULL,
  `risk_score` decimal(5,4) DEFAULT NULL COMMENT '0=low, 1=high',
  
  -- Human Override
  `was_overridden` tinyint(1) NOT NULL DEFAULT 0,
  `override_reason` text DEFAULT NULL,
  `override_user_id` int(10) unsigned DEFAULT NULL,
  `override_at` timestamp NULL DEFAULT NULL,
  
  -- Actual Outcome
  `actual_outcome` JSON DEFAULT NULL COMMENT 'Real result',
  `actual_cost_nzd` decimal(10,2) DEFAULT NULL,
  `outcome_recorded_at` timestamp NULL DEFAULT NULL,
  
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  
  PRIMARY KEY (`id`),
  KEY `idx_trace` (`trace_id`),
  KEY `idx_model` (`model_name`,`model_version`,`created_at`),
  KEY `idx_override` (`was_overridden`,`created_at`)
) ENGINE=InnoDB COMMENT='AI decision audit for governance';
```

**AI Models Tracked:**
- **FreightAI** - Courier selection optimizer
- **NeuroLink** - Demand forecasting
- **AutoPack** - Box packing optimization

---

### `transfer_ai_insights` (GPT/Claude Insights)
**Purpose:** LLM-generated insights about transfers  
**Key Concept:** Cached AI analysis with expiry

```sql
CREATE TABLE `transfer_ai_insights` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `transfer_id` int(11) NOT NULL,
  `insight_text` text NOT NULL,
  `insight_json` JSON DEFAULT NULL COMMENT 'Structured data',
  `insight_type` varchar(50) DEFAULT 'general' COMMENT 'logistics, inventory, timing, cost, staff, risk',
  `priority` varchar(20) DEFAULT 'medium' COMMENT 'low, medium, high, critical',
  `confidence_score` decimal(3,2) DEFAULT 0.85,
  
  -- Model Details
  `model_provider` varchar(50) NOT NULL COMMENT 'openai, anthropic',
  `model_name` varchar(100) NOT NULL COMMENT 'gpt-4o, claude-3.5-sonnet',
  `tokens_used` int(11) DEFAULT 0,
  `processing_time_ms` int(11) DEFAULT 0,
  
  -- Cache
  `generated_at` datetime NOT NULL,
  `expires_at` datetime NOT NULL COMMENT 'Cache TTL',
  `created_by` int(11) DEFAULT NULL,
  
  PRIMARY KEY (`id`),
  KEY `idx_transfer_fresh` (`transfer_id`,`expires_at`),
  CONSTRAINT `transfer_ai_insights_ibfk_1` FOREIGN KEY (`transfer_id`) REFERENCES `transfers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB;
```

**Insight Types:**
- **logistics** - Route optimization, courier selection
- **inventory** - Stock level warnings, restock suggestions
- **timing** - Delivery time predictions, urgency
- **cost** - Cost analysis, savings opportunities
- **staff** - Workload distribution, performance tips
- **risk** - Damage risk, loss probability, compliance

---

### `transfer_behavior_patterns` (ML Features)
**Purpose:** Behavioral patterns for neural network training  
**Key Concept:** Time-series features for predictive models

```sql
CREATE TABLE `transfer_behavior_patterns` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `pattern_id` varchar(36) NOT NULL,
  `pattern_type` varchar(100) NOT NULL,
  
  -- Context
  `user_id` int(11) DEFAULT NULL,
  `transfer_id` int(11) DEFAULT NULL,
  `item_id` int(11) DEFAULT NULL,
  `product_id` varchar(36) DEFAULT NULL,
  
  -- Feature Vector
  `feature_vector` longtext NOT NULL COMMENT 'JSON array of features',
  `confidence_score` decimal(5,4) DEFAULT NULL,
  `sample_size` int(10) unsigned DEFAULT NULL,
  
  -- Temporal Features
  `hour_of_day` tinyint(3) unsigned DEFAULT NULL,
  `day_of_week` tinyint(3) unsigned DEFAULT NULL,
  `time_bucket` varchar(20) DEFAULT NULL COMMENT 'morning, afternoon, evening',
  
  -- Insight
  `insight_type` enum('efficiency','accuracy','speed','anomaly','improvement') NOT NULL,
  `insight_summary` text DEFAULT NULL,
  `actionable_recommendation` text DEFAULT NULL,
  
  -- Statistics
  `mean_value` decimal(10,4) DEFAULT NULL,
  `median_value` decimal(10,4) DEFAULT NULL,
  `std_deviation` decimal(10,4) DEFAULT NULL,
  `min_value` decimal(10,4) DEFAULT NULL,
  `max_value` decimal(10,4) DEFAULT NULL,
  
  -- Timestamps
  `detected_at` datetime(3) NOT NULL,
  `last_updated` datetime(3) DEFAULT current_timestamp(3) ON UPDATE current_timestamp(3),
  `expires_at` datetime DEFAULT NULL,
  
  PRIMARY KEY (`id`),
  UNIQUE KEY `pattern_id` (`pattern_id`),
  KEY `idx_composite` (`user_id`,`pattern_type`,`detected_at`)
) ENGINE=InnoDB COMMENT='Neural network ready behavior patterns';
```

---

## 📊 Logging & Audit Tables

### `transfer_unified_log` (Central Event Log)
**Purpose:** Single source of truth for all transfer events  
**Key Concept:** PSR-3 severity levels, distributed tracing, structured JSON

```sql
CREATE TABLE `transfer_unified_log` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `trace_id` varchar(100) NOT NULL COMMENT 'Distributed tracing ID',
  `correlation_id` varchar(100) DEFAULT NULL COMMENT 'Links related ops',
  
  -- Event Classification
  `category` varchar(50) NOT NULL COMMENT 'transfer, shipment, ai_decision, vend_sync, queue',
  `event_type` varchar(100) NOT NULL COMMENT 'Specific event name',
  `severity` enum('debug','info','notice','warning','error','critical','alert','emergency') NOT NULL DEFAULT 'info',
  `message` text NOT NULL COMMENT 'Human-readable description',
  
  -- Foreign Keys
  `transfer_id` int(10) unsigned DEFAULT NULL,
  `shipment_id` int(10) unsigned DEFAULT NULL,
  `parcel_id` int(10) unsigned DEFAULT NULL,
  `item_id` int(10) unsigned DEFAULT NULL,
  `outlet_id` varchar(50) DEFAULT NULL,
  `vend_consignment_id` varchar(100) DEFAULT NULL,
  `vend_transfer_id` varchar(100) DEFAULT NULL,
  
  -- AI Context
  `ai_decision_id` bigint(20) unsigned DEFAULT NULL,
  `ai_model_version` varchar(50) DEFAULT NULL,
  `ai_confidence` decimal(5,4) DEFAULT NULL,
  
  -- Actor
  `actor_user_id` int(10) unsigned DEFAULT NULL,
  `actor_role` varchar(50) DEFAULT NULL,
  `actor_ip` varchar(45) DEFAULT NULL,
  
  -- Payload
  `event_data` JSON DEFAULT NULL COMMENT 'Structured payload (PII sanitized)',
  `context_data` JSON DEFAULT NULL COMMENT 'Additional metadata',
  `tags` JSON DEFAULT NULL COMMENT 'Searchable tags',
  
  -- Performance
  `duration_ms` int(10) unsigned DEFAULT NULL,
  `memory_mb` decimal(10,2) DEFAULT NULL,
  `api_latency_ms` int(10) unsigned DEFAULT NULL,
  `db_query_ms` int(10) unsigned DEFAULT NULL,
  
  -- System
  `source_system` varchar(50) NOT NULL DEFAULT 'CIS',
  `environment` enum('dev','staging','production') NOT NULL DEFAULT 'production',
  `server_name` varchar(100) DEFAULT NULL,
  `php_version` varchar(20) DEFAULT NULL,
  
  -- Timestamps
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `event_timestamp` timestamp NULL DEFAULT NULL COMMENT 'Actual event time',
  
  PRIMARY KEY (`id`),
  KEY `idx_trace` (`trace_id`),
  KEY `idx_category_severity` (`category`,`severity`,`created_at`),
  KEY `idx_transfer` (`transfer_id`,`created_at`),
  FULLTEXT KEY `idx_message` (`message`)
) ENGINE=InnoDB COMMENT='Unified event log with AI/Vend integration';
```

**Log Categories:**
- `transfer` - Transfer lifecycle events
- `shipment` - Shipping/receiving events
- `ai_decision` - AI model decisions
- `vend_sync` - Vend API sync events
- `queue` - Background job processing
- `security` - Auth/access control
- `performance` - Performance metrics

---

### `transfer_audit_log` (Compliance Audit)
**Purpose:** Immutable audit trail for compliance (7-year retention)  
**Key Concept:** Who did what, when, and why

```sql
CREATE TABLE `transfer_audit_log` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `entity_type` enum('transfer','po') NOT NULL DEFAULT 'transfer',
  `entity_pk` int(11) DEFAULT NULL,
  `transfer_pk` int(11) DEFAULT NULL,
  `transfer_id` varchar(100) DEFAULT NULL COMMENT 'Internal TR-ID',
  `vend_consignment_id` varchar(100) DEFAULT NULL,
  `vend_transfer_id` char(36) DEFAULT NULL,
  
  -- Action
  `action` varchar(100) NOT NULL COMMENT 'CREATE, UPDATE, DELETE, SYNC, APPROVE',
  `status` varchar(50) NOT NULL COMMENT 'success, failed, pending',
  
  -- Actor
  `actor_type` enum('system','user','api','cron','webhook') NOT NULL,
  `actor_id` varchar(100) DEFAULT NULL,
  
  -- Location
  `outlet_from` varchar(100) DEFAULT NULL,
  `outlet_to` varchar(100) DEFAULT NULL,
  
  -- Change Tracking
  `data_before` JSON DEFAULT NULL COMMENT 'State before',
  `data_after` JSON DEFAULT NULL COMMENT 'State after',
  `metadata` JSON DEFAULT NULL COMMENT 'Additional context',
  `error_details` JSON DEFAULT NULL COMMENT 'If failed',
  
  -- Performance
  `processing_time_ms` int(10) unsigned DEFAULT NULL,
  `api_response` JSON DEFAULT NULL COMMENT 'External API response',
  
  -- Session
  `session_id` varchar(255) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  
  PRIMARY KEY (`id`),
  KEY `idx_transfer_id` (`transfer_id`),
  KEY `idx_action_status` (`action`,`status`),
  KEY `idx_audit_errors` (`status`,`created_at`)
) ENGINE=InnoDB COMMENT='Compliance audit trail (7-year retention)';
```

**Retention:** 7 years (regulatory compliance)

---

### `transfer_log_archive` (Historical Archive)
**Purpose:** Archived logs moved from `transfer_unified_log`  
**Key Concept:** Hot data (90 days) vs cold storage (7 years)

```sql
CREATE TABLE `transfer_log_archive` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `original_log_id` bigint(20) unsigned NOT NULL,
  `trace_id` varchar(100) NOT NULL,
  `category` varchar(50) NOT NULL,
  `event_type` varchar(100) NOT NULL,
  `severity` varchar(20) NOT NULL,
  `message` text NOT NULL,
  `event_data` JSON DEFAULT NULL,
  `created_at` timestamp NOT NULL COMMENT 'Original creation',
  `archived_at` timestamp NOT NULL DEFAULT current_timestamp(),
  
  PRIMARY KEY (`id`),
  KEY `idx_archived` (`archived_at`),
  KEY `idx_category` (`category`,`archived_at`)
) ENGINE=InnoDB COMMENT='Archived logs (7-year retention)';
```

**Archive Policy:**
- Logs older than 90 days → moved to archive
- Archive retention: 7 years
- Archive compressed quarterly

---

## ⚡ Queue & Performance Tables

### `transfer_queue_log` (Vend Sync Queue)
**Purpose:** Queue operations for Vend consignment sync  
**Key Concept:** Retry logic, idempotency, failure handling

```sql
CREATE TABLE `transfer_queue_log` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `trace_id` varchar(100) NOT NULL,
  `queue_name` varchar(100) NOT NULL COMMENT 'vend_consignment_sync',
  `operation` varchar(50) NOT NULL COMMENT 'enqueue, dequeue, retry, fail, complete',
  
  -- Transfer Context
  `transfer_id` int(10) unsigned DEFAULT NULL,
  `vend_consignment_id` varchar(100) DEFAULT NULL,
  
  -- Idempotency
  `idempotency_key` varchar(255) DEFAULT NULL COMMENT 'Prevents duplicates',
  
  -- Retry Logic
  `attempt_number` int(10) unsigned NOT NULL DEFAULT 1,
  `max_attempts` int(10) unsigned NOT NULL DEFAULT 3,
  `retry_delay_sec` int(10) unsigned DEFAULT NULL,
  `next_retry_at` timestamp NULL DEFAULT NULL,
  
  -- Payload
  `request_payload` JSON DEFAULT NULL,
  `response_data` JSON DEFAULT NULL,
  
  -- Error Handling
  `error_message` text DEFAULT NULL,
  `error_code` varchar(50) DEFAULT NULL,
  `http_status` int(10) unsigned DEFAULT NULL,
  
  -- Performance
  `processing_ms` int(10) unsigned DEFAULT NULL,
  `api_latency_ms` int(10) unsigned DEFAULT NULL,
  
  -- Status
  `status` enum('pending','processing','completed','failed','cancelled') NOT NULL,
  `priority` tinyint(3) unsigned NOT NULL DEFAULT 5 COMMENT '1=highest, 10=lowest',
  
  -- Timestamps
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `completed_at` timestamp NULL DEFAULT NULL,
  
  PRIMARY KEY (`id`),
  KEY `idx_queue` (`queue_name`,`status`,`priority`,`created_at`),
  KEY `idx_retry` (`next_retry_at`,`status`)
) ENGINE=InnoDB COMMENT='Queue for Vend sync with retry';
```

**Retry Strategy:**
- Attempt 1: Immediate
- Attempt 2: 5 minutes delay
- Attempt 3: 30 minutes delay
- Max attempts: 3 (then manual review)

---

### `transfer_performance_metrics` (Aggregated Metrics)
**Purpose:** Pre-aggregated metrics for dashboards  
**Key Concept:** Hourly/daily rollups for fast queries

```sql
CREATE TABLE `transfer_performance_metrics` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  
  -- Time Bucket
  `metric_date` date NOT NULL,
  `metric_hour` tinyint(3) unsigned DEFAULT NULL COMMENT '0-23',
  
  -- Classification
  `category` varchar(50) NOT NULL COMMENT 'pack, receive, sync, ai',
  `operation` varchar(100) NOT NULL COMMENT 'pack_submit, receive_scan',
  
  -- Volume
  `total_operations` int(10) unsigned NOT NULL DEFAULT 0,
  
  -- Latency (milliseconds)
  `total_duration_ms` bigint(20) unsigned NOT NULL DEFAULT 0,
  `avg_duration_ms` int(10) unsigned NOT NULL DEFAULT 0,
  `p50_duration_ms` int(10) unsigned DEFAULT NULL COMMENT 'Median',
  `p95_duration_ms` int(10) unsigned DEFAULT NULL,
  `p99_duration_ms` int(10) unsigned DEFAULT NULL,
  
  -- Success Rate
  `success_count` int(10) unsigned NOT NULL DEFAULT 0,
  `error_count` int(10) unsigned NOT NULL DEFAULT 0,
  `error_rate` decimal(5,4) GENERATED ALWAYS AS (
    CASE WHEN total_operations > 0 THEN error_count / total_operations ELSE 0 END
  ) STORED,
  
  -- AI Metrics
  `ai_decisions` int(10) unsigned NOT NULL DEFAULT 0,
  `ai_avg_confidence` decimal(5,4) DEFAULT NULL,
  
  -- Timestamps
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_metric` (`metric_date`,`metric_hour`,`category`,`operation`),
  KEY `idx_error_rate` (`error_rate`,`metric_date`)
) ENGINE=InnoDB COMMENT='Aggregated metrics for BI dashboards';
```

**Aggregation Schedule:**
- Hourly rollup: Every hour + 5 min
- Daily rollup: Daily at 2 AM
- Retention: 90 days hourly, 2 years daily

---

### `transfer_session_analytics` (User Session Metrics)
**Purpose:** Detailed user session tracking for UX optimization  
**Key Concept:** Core Web Vitals, interaction patterns, completion rates

```sql
CREATE TABLE `transfer_session_analytics` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `session_id` varchar(255) NOT NULL,
  `user_id` int(11) NOT NULL,
  `transfer_id` int(11) DEFAULT NULL,
  `page_type` enum('pack','receive','create','list','detail') NOT NULL,
  
  -- Duration Tracking
  `session_start` datetime(3) NOT NULL,
  `session_end` datetime(3) DEFAULT NULL,
  `total_duration_seconds` int(10) unsigned DEFAULT NULL,
  `active_duration_seconds` int(10) unsigned DEFAULT NULL,
  `idle_duration_seconds` int(10) unsigned DEFAULT NULL,
  
  -- Interaction Counts
  `total_interactions` int(10) unsigned DEFAULT 0,
  `scan_count` int(10) unsigned DEFAULT 0,
  `manual_entry_count` int(10) unsigned DEFAULT 0,
  `error_count` int(10) unsigned DEFAULT 0,
  
  -- Performance (Core Web Vitals)
  `avg_response_time_ms` int(10) unsigned DEFAULT NULL,
  `page_load_time_ms` int(10) unsigned DEFAULT NULL,
  `largest_contentful_paint_ms` int(10) unsigned DEFAULT NULL COMMENT 'LCP',
  `first_input_delay_ms` int(10) unsigned DEFAULT NULL COMMENT 'FID',
  `cumulative_layout_shift` decimal(5,3) DEFAULT NULL COMMENT 'CLS',
  
  -- Completion
  `items_processed` int(10) unsigned DEFAULT 0,
  `completion_percentage` decimal(5,2) DEFAULT NULL,
  `completed` tinyint(1) DEFAULT 0,
  
  -- Device Context
  `device_type` enum('desktop','mobile','tablet','unknown') DEFAULT 'unknown',
  `browser` varchar(100) DEFAULT NULL,
  `os` varchar(100) DEFAULT NULL,
  `screen_resolution` varchar(50) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  
  -- Timestamps
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  
  PRIMARY KEY (`id`),
  KEY `idx_composite` (`user_id`,`page_type`,`session_start`)
) ENGINE=InnoDB COMMENT='User session analytics for UX';
```

**Core Web Vitals Targets:**
- LCP (Largest Contentful Paint): < 2.5s
- FID (First Input Delay): < 100ms
- CLS (Cumulative Layout Shift): < 0.1

---

## 📸 Media & Upload Tables

### `transfer_media` (Photos/Videos)
**Purpose:** Proof of condition, damage documentation  
**Key Concept:** Attach photos to transfers/parcels/discrepancies

```sql
CREATE TABLE `transfer_media` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `transfer_id` int(11) NOT NULL,
  `parcel_id` int(11) DEFAULT NULL,
  `discrepancy_id` int(11) DEFAULT NULL,
  
  -- File Details
  `kind` enum('photo','video','other') NOT NULL DEFAULT 'photo',
  `mime_type` varchar(100) NOT NULL,
  `size_bytes` int(10) unsigned NOT NULL DEFAULT 0,
  `path` varchar(255) NOT NULL,
  `thumb_path` varchar(255) DEFAULT NULL,
  
  -- Upload Context
  `uploaded_by` int(11) DEFAULT NULL,
  `src_ip` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `note` varchar(255) DEFAULT NULL,
  
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  
  PRIMARY KEY (`id`),
  KEY `idx_transfer_time` (`transfer_id`,`created_at`),
  CONSTRAINT `fk_tm_transfer` FOREIGN KEY (`transfer_id`) REFERENCES `transfers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_tm_parcel` FOREIGN KEY (`parcel_id`) REFERENCES `transfer_parcels` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_tm_discrepancy` FOREIGN KEY (`discrepancy_id`) REFERENCES `transfer_discrepancies` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB;
```

**Use Cases:**
- Damage documentation (broken packaging, product damage)
- Proof of delivery photos
- Condition verification on receive
- Dispute resolution evidence

---

### `transfer_upload_tokens` (Secure Upload Links)
**Purpose:** Temporary tokens for mobile/QR code photo uploads  
**Key Concept:** Token-based upload without authentication

```sql
CREATE TABLE `transfer_upload_tokens` (
  `token` char(64) NOT NULL COMMENT 'SHA256 hash',
  `transfer_id` int(11) NOT NULL,
  `parcel_id` int(11) DEFAULT NULL,
  `discrepancy_id` int(11) DEFAULT NULL,
  
  -- Usage Limits
  `max_uses` int(10) unsigned NOT NULL DEFAULT 10,
  `used_count` int(10) unsigned NOT NULL DEFAULT 0,
  `expires_at` datetime NOT NULL,
  
  -- Creation
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  
  PRIMARY KEY (`token`),
  KEY `idx_transfer_exp` (`transfer_id`,`expires_at`),
  CONSTRAINT `fk_tut_transfer` FOREIGN KEY (`transfer_id`) REFERENCES `transfers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB;
```

**Token Flow:**
1. Generate token (SHA256, 64 chars)
2. Create QR code linking to upload page
3. Print QR on packing slip
4. Warehouse staff scans → uploads photo
5. Token expires after 24h or 10 uses

---

## ✅ Validation & Configuration Tables

### `transfer_validation_cache` (Validation Results Cache)
**Purpose:** Cache validation results for repeat transfers  
**Key Concept:** Avoid re-validating identical transfer patterns

```sql
CREATE TABLE `transfer_validation_cache` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `cache_key` varchar(255) NOT NULL COMMENT 'Hash of validation params',
  
  -- Original Request
  `transfer_data` JSON NOT NULL,
  
  -- Validation Result
  `validation_result` JSON NOT NULL,
  `status` enum('valid','invalid','error') NOT NULL,
  `errors` JSON DEFAULT NULL,
  `warnings` JSON DEFAULT NULL,
  `safeguards_applied` JSON DEFAULT NULL,
  
  -- Economic Analysis
  `economic_analysis` JSON DEFAULT NULL COMMENT 'Cost analysis',
  
  -- Transfer Details
  `outlet_from` varchar(50) NOT NULL,
  `outlet_to` varchar(50) NOT NULL,
  `total_items` int(10) unsigned NOT NULL DEFAULT 0,
  `total_value` decimal(12,2) NOT NULL DEFAULT 0.00,
  `courier_cost` decimal(10,2) DEFAULT NULL,
  
  -- Approval
  `requires_approval` tinyint(1) NOT NULL DEFAULT 0,
  `approval_reasons` JSON DEFAULT NULL,
  
  -- Cache TTL
  `expires_at` timestamp NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_cache_key` (`cache_key`),
  KEY `idx_cleanup_expired` (`expires_at`)
) ENGINE=InnoDB COMMENT='Cached validation results';
```

**Cache TTL:**
- Standard transfers: 1 hour
- High-value transfers (>$5000): 15 minutes
- Invalid results: 5 minutes (prevent spam)

---

### `transfer_configurations` (Allocation Config)
**Purpose:** Configuration presets for transfer allocation algorithms  
**Key Concept:** Different allocation strategies per transfer type

```sql
CREATE TABLE `transfer_configurations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  
  -- Allocation Method
  `allocation_method` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1=proportional, 2=equal, 3=priority',
  `power_factor` decimal(4,2) NOT NULL DEFAULT 2.00 COMMENT 'For weighted allocation',
  `min_allocation_pct` decimal(5,2) NOT NULL DEFAULT 5.00,
  `max_allocation_pct` decimal(5,2) NOT NULL DEFAULT 50.00,
  `rounding_method` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0=standard, 1=up, 2=down',
  
  -- Flags
  `is_preset` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'System preset (read-only)',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `enable_safety_checks` tinyint(1) NOT NULL DEFAULT 1,
  `enable_logging` tinyint(1) NOT NULL DEFAULT 1,
  
  -- Timestamps
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` varchar(50) DEFAULT NULL,
  `updated_by` varchar(50) DEFAULT NULL,
  
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_name` (`name`)
) ENGINE=InnoDB;
```

**Allocation Methods:**
1. **Proportional** - Allocate based on store demand/sales velocity
2. **Equal** - Even distribution across stores
3. **Priority** - High-priority stores first, then fill others

---

## 🔄 Transfer Modes (Detailed)

### Mode 1: GENERAL (Standard Stock)
**Purpose:** Regular inventory movement between stores  
**Business Rules:**
- Multi-store confirmation required (source store must accept/decline)
- Supports partial shipments
- AI-optimized courier selection
- Standard cost allocation

**Workflow:**
```
CREATE → CONFIRM → PACK → SHIP → IN_TRANSIT → RECEIVE → RECEIVED → SYNC_VEND
```

**Tables Used:**
- `transfers` (type='stock', transfer_type='GENERAL')
- `transfer_items` (confirmation_status workflow)
- `transfer_shipments` (delivery_mode='courier')
- `transfer_parcels` (box tracking)
- `transfer_queue_log` (Vend sync)

---

### Mode 2: JUICE (E-Liquid Compliance)
**Purpose:** E-liquid/vape juice transfers with regulatory compliance  
**Business Rules:**
- Nicotine tracking required (`nicotine_in_shipment` flag)
- Age-restricted shipping (18+ signature)
- Batch/lot tracking for recalls
- Separate courier requirements (some won't ship nicotine)

**Workflow:**
```
CREATE → NICOTINE_CHECK → COMPLIANCE_VERIFY → PACK → SPECIALIZED_COURIER → RECEIVE → AUDIT
```

**Tables Used:**
- `transfers` (type='juice', transfer_type='JUICE')
- `transfer_shipments` (nicotine_in_shipment=1)
- `transfer_audit_log` (compliance tracking)
- `transfer_media` (proof photos required)

**Regulatory Requirements:**
- NZ Smokefree Environments Act compliance
- Age verification on delivery
- Nicotine content labeling
- Transport documentation

---

### Mode 3: STAFF (Employee Orders)
**Purpose:** Staff personal purchases with special pricing  
**Business Rules:**
- Links to `staff_transfers` table (employee order system)
- Special pricing applied (staff discount)
- Approval workflow (manager approval required)
- Personal account billing (payroll deduction)

**Workflow:**
```
CREATE → MANAGER_APPROVE → PRICE_APPLY → PACK → INTERNAL_DELIVERY → RECEIVE → PAYROLL_DEDUCT
```

**Tables Used:**
- `transfers` (type='staff', transfer_type='STAFF', staff_transfer_id populated)
- `staff_transfers` (external staff order table)
- `transfer_shipments` (delivery_mode='internal_drive' or 'pickup')
- `transfer_audit_log` (approval tracking)

**Special Handling:**
- No courier costs (internal delivery or pickup)
- Pricing locked at order time
- Tax calculations (employee benefit)
- Privacy considerations (separate from business transfers)

---

### Mode 4: AUTOMATED (AI-Driven)
**Purpose:** Fully automated stock replenishment via AI  
**Business Rules:**
- No human confirmation required
- AI predicts demand and creates transfers
- Auto-pack and auto-ship (if confidence > 0.95)
- Cost optimization focus

**Workflow:**
```
AI_PREDICT → AUTO_CREATE → AUTO_ALLOCATE → AUTO_PACK → AUTO_SHIP → RECEIVE → FEEDBACK_LOOP
```

**Tables Used:**
- `transfers` (transfer_type='AUTOMATED')
- `transfer_ai_audit_log` (AI decision tracking)
- `transfer_ai_insights` (demand predictions)
- `transfer_allocations` (allocation execution)
- `transfer_executions` (allocation config used)
- `transfer_behavior_patterns` (ML features)

**AI Models:**
- **Demand Forecasting** - Predicts store inventory needs
- **Allocation Optimization** - Decides quantities per store
- **Courier Selection** - Chooses cheapest/fastest option
- **Risk Assessment** - Flags high-risk transfers

**Confidence Thresholds:**
- > 0.95: Auto-execute without human review
- 0.80-0.95: Auto-create, require human approval
- < 0.80: Flag for human review, provide recommendation

---

## 🔗 Table Relationships

### Entity Relationship Diagram (Simplified)

```
transfers (1) ────────────┬─ (N) transfer_items
  │                       │
  │                       ├─ (N) transfer_shipments
  ├─ (N) transfer_notes   │        │
  ├─ (N) transfer_logs    │        ├─ (N) transfer_shipment_items
  ├─ (N) transfer_media   │        │        │
  ├─ (1) staff_transfers  │        │        └─ (1) transfer_items (FK)
  ├─ (1) consignment_id   │        │
  │                       │        ├─ (N) transfer_parcels
  │                       │                 │
  │                       │                 ├─ (N) transfer_parcel_items
  │                       │                 │        │
  │                       │                 │        └─ (1) transfer_items (FK)
  │                       │                 │
  │                       │                 ├─ (N) transfer_tracking_events
  │                       │                 └─ (N) transfer_media
  │                       │
  │                       └─ (N) transfer_discrepancies
  │                                │
  │                                └─ (N) transfer_media
  │
  ├─ (N) transfer_ai_insights
  ├─ (N) transfer_ai_audit_log
  ├─ (N) transfer_unified_log
  ├─ (N) transfer_audit_log
  ├─ (N) transfer_session_analytics
  └─ (N) transfer_queue_log
```

### Key Relationships

**1:N Relationships:**
- 1 transfer → N items
- 1 transfer → N shipments
- 1 shipment → N parcels
- 1 transfer → N logs/notes/media

**M:N Relationships (via junction tables):**
- transfers ↔ transfer_items ↔ transfer_shipments (via `transfer_shipment_items`)
- transfers ↔ transfer_items ↔ transfer_parcels (via `transfer_parcel_items`)

**Foreign Key Constraints:**
- All child tables have FK to parent with `ON DELETE CASCADE` or `SET NULL`
- Soft deletes preserve audit trail (`deleted_at`, `deleted_by`)

---

## 🔍 Key Indexes

### High-Traffic Query Patterns

**1. Transfer Lookup (by ID):**
```sql
-- Primary keys cover this
SELECT * FROM transfers WHERE id = ?
SELECT * FROM transfers WHERE public_id = ?
SELECT * FROM transfers WHERE vend_transfer_id = ?
```

**2. Transfer List (by outlet + status):**
```sql
-- Covered by: idx_transfers_from_status_date
SELECT * FROM transfers 
WHERE outlet_from = ? AND status = ? 
ORDER BY created_at DESC
```

**3. Dashboard (recent activity):**
```sql
-- Covered by: idx_transfers_created
SELECT * FROM transfers 
ORDER BY created_at DESC 
LIMIT 50
```

**4. Shipment Items (for packing):**
```sql
-- Covered by: idx_tsi_shipment
SELECT * FROM transfer_shipment_items 
WHERE shipment_id = ?
```

**5. Log Search (by transfer + time range):**
```sql
-- Covered by: idx_transfer (transfer_id, created_at)
SELECT * FROM transfer_unified_log 
WHERE transfer_id = ? 
  AND created_at >= ? 
  AND created_at <= ?
ORDER BY created_at DESC
```

**6. AI Audit (by model + time):**
```sql
-- Covered by: idx_model (model_name, model_version, created_at)
SELECT * FROM transfer_ai_audit_log 
WHERE model_name = ? 
  AND created_at >= ?
ORDER BY created_at DESC
```

**7. Performance Metrics (aggregation):**
```sql
-- Covered by: idx_metric (metric_date, metric_hour, category, operation)
SELECT * FROM transfer_performance_metrics 
WHERE metric_date = ? 
  AND category = ?
```

### Composite Index Strategy

**Leftmost Prefix Rule:**
- Index `(outlet_from, status, created_at)` covers:
  - `outlet_from`
  - `outlet_from, status`
  - `outlet_from, status, created_at` ✅

**Covering Indexes:**
- Some queries use `SELECT COUNT(*)` → index alone sufficient (no table scan)

---

## 📈 Performance Considerations

### Table Sizes (Current Production)

| Table | Rows | Growth Rate | Retention |
|-------|------|-------------|-----------|
| `transfers` | 26,915 | ~100/day | Permanent |
| `transfer_items` | 253,005 | ~1,000/day | Permanent |
| `transfer_shipments` | 5,280 | ~50/day | Permanent |
| `transfer_parcels` | 3,556 | ~40/day | Permanent |
| `transfer_unified_log` | 1M+ | ~10,000/day | 90 days (then archive) |
| `transfer_audit_log` | 805 | ~10/day | 7 years |
| `transfer_ai_audit_log` | Growing | ~50/day | Permanent |
| `transfer_performance_metrics` | Growing | 24/day | 90 days hourly, 2 years daily |

### Optimization Strategies

**1. Partitioning:**
- `transfer_unified_log` - Partitioned by month (RANGE on `created_at`)
- `transfer_performance_metrics` - Partitioned by year

**2. Archival:**
- Logs > 90 days → `transfer_log_archive`
- Audit logs > 7 years → compressed cold storage

**3. Caching:**
- `transfer_validation_cache` - 1 hour TTL
- `transfer_ai_insights` - 6 hour TTL
- Query result cache for dashboard (5 min TTL)

**4. Sharding (Future):**
- Shard by `outlet_from` for multi-region scale
- Read replicas for analytics queries

---

## 🛡️ Security & Compliance

### Data Protection

**PII Sanitization:**
- `transfer_unified_log.event_data` - PII redacted before logging
- `transfer_audit_log.data_before/after` - Sensitive fields masked

**Access Control:**
- Row-level security via application layer (not DB-level)
- Outlet-based permissions (staff only see their outlet's transfers)
- Manager override for cross-outlet visibility

**Audit Requirements:**
- Immutable audit trail (no UPDATE/DELETE on audit logs)
- 7-year retention for compliance
- Actor tracking (who, when, from where)

### Regulatory Compliance

**Nicotine Tracking:**
- `transfer_shipments.nicotine_in_shipment` flag
- Required for NZ Smokefree Environments Act
- Audit trail for inspections

**Tax & Accounting:**
- Transfer cost tracking for GST reporting
- Inventory movement for stock audits
- Staff transfer pricing for FBT (Fringe Benefit Tax)

---

## 📝 Notes for Developers

### Common Patterns

**1. Creating a Transfer:**
```sql
-- Step 1: Insert transfer
INSERT INTO transfers (public_id, vend_resource, outlet_from, outlet_to, created_by, type, transfer_type, status)
VALUES (?, 'consignment', ?, ?, ?, 'stock', 'GENERAL', 'draft');

-- Step 2: Insert items
INSERT INTO transfer_items (transfer_id, product_id, qty_requested)
VALUES (?, ?, ?);

-- Step 3: Log event
INSERT INTO transfer_unified_log (trace_id, category, event_type, transfer_id, message, actor_user_id)
VALUES (?, 'transfer', 'CREATED', ?, 'Transfer created', ?);
```

**2. Packing a Shipment:**
```sql
-- Create shipment
INSERT INTO transfer_shipments (transfer_id, delivery_mode, status, packed_by)
VALUES (?, 'courier', 'packed', ?);

-- Add items to shipment
INSERT INTO transfer_shipment_items (shipment_id, item_id, qty_sent)
VALUES (?, ?, ?);

-- Create parcels
INSERT INTO transfer_parcels (shipment_id, box_number, weight_kg)
VALUES (?, 1, ?);

-- Add items to parcel
INSERT INTO transfer_parcel_items (parcel_id, item_id, qty)
VALUES (?, ?, ?);

-- Update transfer status
UPDATE transfers SET status = 'sent', state = 'SENT' WHERE id = ?;
```

**3. Receiving:**
```sql
-- Update parcel status
UPDATE transfer_parcels 
SET status = 'received', received_at = NOW() 
WHERE id = ?;

-- Update item received qty
UPDATE transfer_items 
SET qty_received_total = qty_received_total + ? 
WHERE id = ?;

-- Check if fully received
SELECT 
  SUM(qty_requested) as total_req,
  SUM(qty_received_total) as total_recv
FROM transfer_items 
WHERE transfer_id = ?;

-- If fully received, update transfer
UPDATE transfers 
SET status = 'received', state = 'RECEIVED' 
WHERE id = ?;
```

### Performance Tips

**1. Use JOINs carefully:**
```sql
-- ❌ BAD: N+1 query
SELECT * FROM transfers;
-- Then for each: SELECT * FROM transfer_items WHERE transfer_id = ?

-- ✅ GOOD: Single query with JOIN
SELECT t.*, ti.*
FROM transfers t
LEFT JOIN transfer_items ti ON t.id = ti.transfer_id
WHERE t.outlet_from = ?;
```

**2. Paginate large result sets:**
```sql
-- ✅ GOOD: Pagination
SELECT * FROM transfer_unified_log
WHERE transfer_id = ?
ORDER BY created_at DESC
LIMIT 100 OFFSET ?;
```

**3. Use EXPLAIN for slow queries:**
```sql
EXPLAIN SELECT * FROM transfers WHERE status = 'open';
-- Check for "Using index" in Extra column
```

---

## 🚀 Future Enhancements

### Planned Improvements

1. **Real-time Tracking API Integration**
   - NZ Post tracking webhook
   - GSS tracking events
   - Live parcel status updates

2. **Advanced AI Features**
   - Predictive packing (optimal box sizes)
   - Dynamic routing (cheapest path)
   - Demand forecasting (restock predictions)

3. **Mobile App Support**
   - Barcode scanning in app
   - Push notifications for status changes
   - Offline mode for receiving

4. **Blockchain Audit Trail**
   - Immutable audit on blockchain
   - Smart contracts for approvals
   - Cryptographic proof of delivery

5. **Analytics Dashboards**
   - Power BI integration
   - Custom report builder
   - Predictive analytics

---

## 📚 Related Documentation

- [Transfer API Documentation](./TRANSFER_API.md)
- [Transfer State Machine](./TRANSFER_STATE_MACHINE.md)
- [AI Decision Engine](./AI_DECISION_ENGINE.md)
- [Vend Sync Integration](./VEND_SYNC.md)
- [Performance Tuning Guide](./PERFORMANCE_TUNING.md)
- [Audit & Compliance](./AUDIT_COMPLIANCE.md)

---

**Document Status:** ✅ Complete  
**Last Reviewed:** October 12, 2025  
**Next Review:** January 2026  
**Maintained By:** CIS Development Team
