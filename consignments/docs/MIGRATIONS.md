# Consignments Module - Migration Log

**Purpose:** Track all database schema changes with forward + rollback instructions

---

## Migration Format

Each migration entry must include:
1. **Migration ID:** Unique identifier (YYYYMMDD_HHMMSS_description)
2. **Date:** When applied
3. **Author:** Who created it
4. **Description:** What changed and why
5. **Forward SQL:** Commands to apply changes
6. **Rollback SQL:** Commands to undo changes
7. **Risk Assessment:** Impact analysis
8. **Verification:** How to confirm success

---

## Active Migrations

### 20251101_030000_add_status_map_tables

**Date:** November 1, 2025
**Author:** AI Development Agent
**Status:** Planned (Not Yet Applied)

**Description:**
Add tables to support canonical status mapping and state transition logging:
- `consignment_status_map` - CIS ↔ Lightspeed status mappings
- `status_transition_log` - Audit trail for all status changes with validation results

**Forward SQL:**
```sql
-- Status mapping reference table
CREATE TABLE IF NOT EXISTS consignment_status_map (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    internal_status VARCHAR(50) NOT NULL COMMENT 'CIS internal status',
    lightspeed_status VARCHAR(50) NOT NULL COMMENT 'Lightspeed API status',
    direction ENUM('to_ls', 'from_ls', 'both') NOT NULL DEFAULT 'both',
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_internal_status (internal_status),
    INDEX idx_ls_status (lightspeed_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Canonical mapping between CIS and Lightspeed consignment statuses';

-- Status transition audit log
CREATE TABLE IF NOT EXISTS status_transition_log (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    transfer_id INT UNSIGNED NOT NULL,
    consignment_id VARCHAR(100) NULL COMMENT 'Lightspeed consignment ID',
    from_status VARCHAR(50) NOT NULL,
    to_status VARCHAR(50) NOT NULL,
    is_allowed BOOLEAN NOT NULL COMMENT 'Was this transition valid per policy?',
    validation_error TEXT NULL COMMENT 'Error message if transition blocked',
    changed_by INT UNSIGNED NOT NULL COMMENT 'User ID who initiated change',
    changed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    context_data JSON NULL COMMENT 'Additional metadata about the change',
    request_id VARCHAR(100) NULL COMMENT 'Correlation ID for tracing',
    INDEX idx_transfer_id (transfer_id),
    INDEX idx_consignment_id (consignment_id),
    INDEX idx_changed_at (changed_at),
    INDEX idx_is_allowed (is_allowed)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Audit trail for all status transitions with validation results';

-- Populate initial status mappings
INSERT INTO consignment_status_map (internal_status, lightspeed_status, direction, notes) VALUES
('draft', 'OPEN', 'both', 'Initial state, editable in CIS'),
('sent', 'SENT', 'both', 'Consignment sent to destination'),
('receiving', 'DISPATCHED', 'both', 'Partially received, in progress'),
('received', 'RECEIVED', 'both', 'Fully received, pending finalization'),
('completed', 'RECEIVED', 'to_ls', 'Finalized in CIS, maps to RECEIVED in LS'),
('cancelled', 'CANCELLED', 'both', 'Cancelled at any stage')
ON DUPLICATE KEY UPDATE
    lightspeed_status = VALUES(lightspeed_status),
    notes = VALUES(notes),
    updated_at = CURRENT_TIMESTAMP;
```

**Rollback SQL:**
```sql
DROP TABLE IF EXISTS status_transition_log;
DROP TABLE IF EXISTS consignment_status_map;
```

**Risk Assessment:**
- **Impact:** LOW - New tables only, no existing table modifications
- **Downtime:** None - Can be applied online
- **Rollback Safety:** Safe - Simple DROP TABLE

**Verification:**
```sql
-- Verify tables created
SHOW TABLES LIKE '%status%';

-- Verify initial data loaded
SELECT * FROM consignment_status_map;

-- Verify indexes
SHOW INDEX FROM status_transition_log;
```

---

### 20251101_040000_add_idempotency_keys

**Date:** November 1, 2025
**Author:** AI Development Agent
**Status:** Planned (Not Yet Applied)

**Description:**
Add idempotency tracking to prevent duplicate Lightspeed API calls on retry

**Forward SQL:**
```sql
-- Idempotency key tracking
CREATE TABLE IF NOT EXISTS api_idempotency_keys (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    idempotency_key VARCHAR(128) NOT NULL COMMENT 'Unique key for operation',
    endpoint VARCHAR(255) NOT NULL COMMENT 'API endpoint called',
    http_method VARCHAR(10) NOT NULL COMMENT 'GET, POST, PUT, DELETE',
    request_payload JSON NULL COMMENT 'Request body (masked PII)',
    response_payload JSON NULL COMMENT 'Response body (masked PII)',
    status_code INT UNSIGNED NULL COMMENT 'HTTP status code',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL COMMENT 'When operation completed',
    expires_at TIMESTAMP NOT NULL COMMENT 'When key expires (7 days default)',
    UNIQUE KEY uk_idempotency_key (idempotency_key),
    INDEX idx_endpoint (endpoint),
    INDEX idx_expires_at (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Idempotency keys for preventing duplicate API operations';
```

**Rollback SQL:**
```sql
DROP TABLE IF EXISTS api_idempotency_keys;
```

**Risk Assessment:**
- **Impact:** LOW - New table only
- **Downtime:** None
- **Rollback Safety:** Safe

**Verification:**
```sql
SHOW CREATE TABLE api_idempotency_keys;
```

---

## Historical Migrations (Pre-Refactor)

### 20241015_000000_initial_consignments_schema

**Date:** October 15, 2024
**Author:** Legacy Team
**Status:** Applied (Production)

**Description:**
Initial schema for consignments module with core tables:
- `vend_consignments`
- `vend_consignment_line_items`
- `transfers`
- `transfer_items`
- `transfer_status_log`
- `transfer_audit_log`
- `queue_consignments`
- `queue_consignment_products`
- `queue_jobs`
- `queue_webhook_events`

**Forward SQL:** See `/migrations/initial_schema.sql`

**Rollback SQL:** Not available (initial schema)

---

## Migration Checklist

Before applying any migration:

- [ ] Review forward + rollback SQL
- [ ] Backup production database
- [ ] Test on staging environment first
- [ ] Document risk assessment
- [ ] Get approval for high-risk changes
- [ ] Schedule during maintenance window if needed
- [ ] Verify data integrity after apply
- [ ] Test rollback procedure
- [ ] Update this log with results

---

## Emergency Rollback Procedure

If migration causes issues:

1. **Stop Application:** Prevent new writes
2. **Assess Impact:** Check error logs, data integrity
3. **Execute Rollback SQL:** From this document
4. **Verify Rollback:** Confirm original state restored
5. **Restart Application:** Resume normal operations
6. **Postmortem:** Document what went wrong
7. **Fix & Retry:** Update migration, test thoroughly

---

**Last Updated:** November 1, 2025
**Next Review:** Before each new migration
