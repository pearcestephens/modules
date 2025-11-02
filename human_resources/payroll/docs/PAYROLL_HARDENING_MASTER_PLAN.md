# Payroll Module - Production Hardening Master Plan

**Created:** November 2, 2025
**Version:** 1.0.0
**Status:** 🟡 Planning → Execution
**Target:** Production-grade, fully reconciled payroll system

---

## 🎯 Executive Summary

**Goal:** Transform the payroll module into a production-hardened system with:
1. **100% Reconciliation** - Every cent tracked, allocated, auditable
2. **Zero-Duplicate Guarantees** - Idempotent operations across all flows
3. **Operational Visibility** - Dashboard-driven workflow with DLQ/replay
4. **Leave Management** - Full support for Annual/Sick/Day-in-Lieu with unit conversions
5. **Drift Detection** - Automated divergence monitoring between CIS and Xero

**Timeline:** 12 tasks over 4-6 weeks (rate-limited, production-safe execution)

---

## 📊 Task Breakdown

### 🔴 CRITICAL FIXES (Week 0-2) - PRIORITY 1

#### **T1: Canonical ID Mapping Table**
**Status:** 🔴 Not Started
**Priority:** P0 (Blocker for all allocations)
**Estimated:** 4 hours

**Problem:**
- Unreliable joins between Xero employees and Vend customers
- Risk of missed/duplicate payment allocations
- No validation preventing unmapped staff processing

**Solution:**
```sql
CREATE TABLE staff_identity_map (
  id INT AUTO_INCREMENT PRIMARY KEY,
  xero_employee_id VARCHAR(64) NOT NULL UNIQUE,
  vend_customer_id VARCHAR(64) NOT NULL UNIQUE,
  staff_number VARCHAR(64) NULL,
  display_name VARCHAR(255) NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_xero_emp (xero_employee_id),
  INDEX idx_vend_cust (vend_customer_id),
  INDEX idx_active (active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Deliverables:**
- [x] Migration: `001_create_staff_identity_map.php`
- [x] DAO: `StaffIdentityDao.php` with CRUD operations
- [ ] Middleware: Block payroll apply if unmapped employees
- [ ] CLI wizard: `cli/map-staff-identity.php`
- [ ] Tests: 12 unit tests covering edge cases

**Acceptance:**
- ✅ All payroll jobs reject with clear error if unmapped staff detected
- ✅ CLI/UI allows ops to add/fix mappings before re-run
- ✅ No orphaned/duplicate staff records possible

---

#### **T2: Idempotent "Account Payment" Application**
**Status:** 🔴 Not Started
**Priority:** P0 (Prevents duplicate charges)
**Estimated:** 6 hours

**Problem:**
- Re-runs risk double-applying payments to staff accounts
- No tracking of which pay runs have been applied
- No protection against concurrent processing

**Solution:**
```sql
CREATE TABLE payroll_runs (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  provider_run_id VARCHAR(64) NOT NULL UNIQUE,
  period_start DATE NOT NULL,
  period_end DATE NOT NULL,
  posted_at DATETIME NOT NULL,
  state ENUM('NEW','APPLYING','PARTIAL','APPLIED','FAILED','ROLLED_BACK') NOT NULL DEFAULT 'NEW',
  total_employees INT NOT NULL DEFAULT 0,
  total_amount_cents BIGINT NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_provider_run (provider_run_id),
  INDEX idx_period (period_start, period_end),
  INDEX idx_state (state)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE payroll_applications (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  payroll_run_id BIGINT NOT NULL,
  provider_run_id VARCHAR(64) NOT NULL,
  xero_employee_id VARCHAR(64) NOT NULL,
  vend_customer_id VARCHAR(64) NOT NULL,
  idempotency_key VARCHAR(128) NOT NULL UNIQUE,
  amount_cents INT NOT NULL,
  status ENUM('PENDING','SUCCESS','DUPLICATE','FAILED') NOT NULL DEFAULT 'PENDING',
  error_code VARCHAR(64) NULL,
  error_message TEXT NULL,
  applied_at TIMESTAMP NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (payroll_run_id) REFERENCES payroll_runs(id) ON DELETE CASCADE,
  INDEX idx_payroll_run (payroll_run_id),
  INDEX idx_idempotency (idempotency_key),
  INDEX idx_status (status),
  INDEX idx_employee (xero_employee_id),
  INDEX idx_customer (vend_customer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Idempotency Key Formula:**
```php
$key = sprintf('xero:%s:emp:%s:amt:%d', $payrunId, $employeeId, $amountCents);
```

**Deliverables:**
- [ ] Migration: `002_create_payroll_runs_applications.php`
- [ ] DAO: `PayrollRunDao.php` with state machine
- [ ] DAO: `PayrollApplicationDao.php` with idempotency checks
- [ ] Service: `PayrunIntakeService.php` - handle intake from Xero
- [ ] Service: `AllocationService.php` - apply payments idempotently
- [ ] Tests: 20 unit tests (duplicate detection, state transitions)

**Acceptance:**
- ✅ Re-running same pay run always results in DUPLICATE (no extra charge)
- ✅ Concurrent runs blocked by database constraints
- ✅ Every application has unique idempotency key

---

#### **T3: Strict Weekly Windowing (Tue→Mon)**
**Status:** 🔴 Not Started
**Priority:** P1 (Data integrity)
**Estimated:** 3 hours

**Problem:**
- Off-by-one date bugs cause leakage between pay periods
- No enforcement of company pay cycle boundaries
- CSV imports may contain wrong period data

**Solution:**
- Single source of truth: `PAYROLL_WEEK_START=Tuesday` in config
- Validation layer rejects any intake outside period boundaries
- Quarantine table for problematic records

```sql
CREATE TABLE payroll_quarantine (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  provider_run_id VARCHAR(64) NOT NULL,
  employee_id VARCHAR(64) NOT NULL,
  amount_cents INT NOT NULL,
  period_start DATE NOT NULL,
  period_end DATE NOT NULL,
  reason_code VARCHAR(64) NOT NULL,
  reason_message TEXT NOT NULL,
  quarantined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  resolved_at TIMESTAMP NULL,
  resolved_by INT NULL,
  INDEX idx_provider_run (provider_run_id),
  INDEX idx_reason (reason_code),
  INDEX idx_resolved (resolved_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Deliverables:**
- [ ] Migration: `003_create_payroll_quarantine.php`
- [ ] Config: Add `PAYROLL_WEEK_START`, `PAYROLL_PERIOD_DAYS` (default 7)
- [ ] Service: `PeriodValidator.php` with strict window checks
- [ ] CLI: `cli/review-quarantine.php` - ops tool to resolve
- [ ] Tests: 8 unit tests (edge cases: DST, leap year, year boundary)

**Acceptance:**
- ✅ Any item outside Tuesday→Monday window is quarantined
- ✅ Clear actionable error with expected vs actual dates
- ✅ Ops can review/approve/reject quarantined items

---

#### **T4: Robust Amount Reconciliation (Vend sales vs deduction)**
**Status:** 🔴 Not Started
**Priority:** P0 (Financial accuracy)
**Estimated:** 8 hours

**Problem:**
- Rounding errors cause "off by pennies" mismatches
- Complex allocation logic scattered across multiple files
- No residual tracking for unallocated amounts

**Solution:**
- Centralized `AllocationService::allocate()` library function
- Strategy chain: Invoice FIFO → Sale-line fallback → eCom reconstruction
- Explicit residuals table for tracking

```sql
CREATE TABLE payroll_residuals (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  payroll_run_id BIGINT NOT NULL,
  employee_id VARCHAR(64) NOT NULL,
  vend_customer_id VARCHAR(64) NOT NULL,
  residual_cents INT NOT NULL,
  reason_code VARCHAR(64) NOT NULL,
  reason_message TEXT NOT NULL,
  metadata JSON NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  resolved_at TIMESTAMP NULL,
  FOREIGN KEY (payroll_run_id) REFERENCES payroll_runs(id) ON DELETE CASCADE,
  INDEX idx_payroll_run (payroll_run_id),
  INDEX idx_reason (reason_code),
  INDEX idx_resolved (resolved_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Allocation Strategies:**
1. **Invoice FIFO** - Apply to oldest unpaid invoice first
2. **Sale-line fallback** - If invoices exhausted, apply to oldest sale line
3. **eCom reconstruction** - If no sales, reconstruct from web orders
4. **Residual logging** - Record any unallocated pennies with reason

**Deliverables:**
- [ ] Migration: `004_create_payroll_residuals.php`
- [ ] Service: `AllocationService.php` with strategy chain
- [ ] DAO: `PayrollResidualDao.php`
- [ ] Helper: `MoneyHelper.php` - cents conversions, rounding rules
- [ ] Tests: 25 unit tests (edge cases: zero balance, overpayment, rounding)

**Acceptance:**
- ✅ 100% of staff lines either fully applied or have documented residual
- ✅ Residuals never exceed ±$0.10 (10 cents tolerance)
- ✅ All strategies unit-tested with property-based tests

---

#### **T5: Error Envelopes & Dead Letter Queue**
**Status:** 🔴 Not Started
**Priority:** P1 (Operational visibility)
**Estimated:** 5 hours

**Problem:**
- Mixed exception types make debugging hard
- No centralized view of failures
- Failed operations get lost

**Solution:**
```php
// Standard error envelope
{
  "ok": false,
  "request_id": "uuid-v4",
  "category": "INPUT|AUTHN|UPSTREAM|DB|CONFLICT|INTERNAL",
  "code": "VEND_API_BAD_JSON",
  "message": "Upstream JSON parse error",
  "hint": "See payroll_run_id=PR_2025_11_02, application_id=123",
  "meta": {
    "provider_run_id": "PR_2025_11_02",
    "employee_id": "E123",
    "timestamp": "2025-11-02T10:30:00Z"
  }
}
```

```sql
CREATE TABLE payroll_dlq (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  request_id VARCHAR(36) NOT NULL UNIQUE,
  payroll_run_id BIGINT NULL,
  employee_id VARCHAR(64) NULL,
  category ENUM('INPUT','AUTHN','UPSTREAM','DB','CONFLICT','INTERNAL') NOT NULL,
  error_code VARCHAR(64) NOT NULL,
  error_message TEXT NOT NULL,
  error_hint TEXT NULL,
  metadata JSON NULL,
  retry_count INT NOT NULL DEFAULT 0,
  max_retries INT NOT NULL DEFAULT 3,
  next_retry_at TIMESTAMP NULL,
  resolved_at TIMESTAMP NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_category (category),
  INDEX idx_error_code (error_code),
  INDEX idx_retry (next_retry_at),
  INDEX idx_resolved (resolved_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Deliverables:**
- [ ] Migration: `005_create_payroll_dlq.php`
- [ ] Helper: `ErrorEnvelope.php` - standard response builder
- [ ] DAO: `PayrollDlqDao.php`
- [ ] Service: `DlqService.php` - push to DLQ, mark resolved
- [ ] View: `views/dlq-monitor.php` - ops dashboard
- [ ] Tests: 10 unit tests (error envelope validation)

**Acceptance:**
- ✅ Every failure path returns normalized envelope
- ✅ All errors automatically enter DLQ
- ✅ Ops can view/filter/replay from DLQ dashboard

---

#### **T6: Replay Engine**
**Status:** 🔴 Not Started
**Priority:** P1 (Recovery capability)
**Estimated:** 6 hours

**Problem:**
- No way to retry failed operations without code changes
- Ops must wait for dev intervention
- Risk of manual errors during recovery

**Solution:**
- CLI + UI to replay failed operations
- Scoped replay: whole run, single employee, by error code
- Respects idempotency keys

**Deliverables:**
- [ ] Service: `ReplayService.php` - orchestrate retries
- [ ] CLI: `cli/payroll-replay.php` - command-line interface
- [ ] API: `api/payroll/replay.php` - REST endpoint
- [ ] View: `views/replay-manager.php` - ops UI with filters
- [ ] Tests: 15 unit tests (idempotency, throttling, scope filters)

**CLI Examples:**
```bash
# Replay entire pay run
php cli/payroll-replay.php --run=PR_2025_11_02

# Replay single employee
php cli/payroll-replay.php --employee=E123

# Replay by error code
php cli/payroll-replay.php --error-code=VEND_API_TIMEOUT --limit=10

# Dry run
php cli/payroll-replay.php --run=PR_2025_11_02 --dry-run
```

**Acceptance:**
- ✅ Any failed staff line can be replayed from UI
- ✅ Replay respects idempotency (no duplicates)
- ✅ Throttling prevents API rate limit violations

---

### 🟠 HIGH-IMPACT IMPROVEMENTS (Week 2-4) - PRIORITY 2

#### **T7: Leave Model (incl. Day-in-Lieu)**
**Status:** 🔴 Not Started
**Priority:** P2 (Feature completeness)
**Estimated:** 8 hours

**Problem:**
- Inconsistent leave units cause balance drift
- No canonical leave type system
- Day-in-Lieu not properly tracked

**Solution:**
```sql
CREATE TABLE staff_leave_balances (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  employee_id VARCHAR(64) NOT NULL,
  leave_type ENUM('ANNUAL','SICK','ALT','LIEU','UNPAID','BEREAVEMENT','PARENTAL') NOT NULL,
  balance_units DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  unit_type ENUM('HOURS','DAYS') NOT NULL DEFAULT 'HOURS',
  accrual_rate DECIMAL(10,4) NULL,
  last_accrued_at DATE NULL,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY unique_employee_leave (employee_id, leave_type),
  INDEX idx_employee (employee_id),
  INDEX idx_leave_type (leave_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE leave_conversion_rules (
  id INT AUTO_INCREMENT PRIMARY KEY,
  provider VARCHAR(64) NOT NULL,
  provider_leave_code VARCHAR(64) NOT NULL,
  internal_leave_type ENUM('ANNUAL','SICK','ALT','LIEU','UNPAID','BEREAVEMENT','PARENTAL') NOT NULL,
  hours_per_day DECIMAL(4,2) NOT NULL DEFAULT 8.00,
  UNIQUE KEY unique_provider_code (provider, provider_leave_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Deliverables:**
- [ ] Migration: `006_create_leave_tables.php`
- [ ] DAO: `LeaveBalanceDao.php`
- [ ] Service: `LeaveService.php` - balance management, conversions
- [ ] Helper: `LeaveUnitConverter.php` - hours↔days with precision
- [ ] Seed data: Insert Xero leave code mappings
- [ ] Tests: 18 unit tests (conversion edge cases)

**Acceptance:**
- ✅ All leave types have canonical enum values
- ✅ Import/export always converts with no silent truncation
- ✅ Day-in-Lieu properly accrued and decremented

---

#### **T8: "Assign Leave to Payslip" Pipeline**
**Status:** 🔴 Not Started
**Priority:** P2 (Ops workflow)
**Estimated:** 6 hours

**Problem:**
- No way to add Day-in-Lieu/Holiday adjustments mid-cycle
- Manual Xero edits bypass audit trail
- Entitlement validation missing

**Solution:**
```sql
CREATE TABLE leave_assignments (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  employee_id VARCHAR(64) NOT NULL,
  payroll_run_id BIGINT NULL,
  leave_type ENUM('ANNUAL','SICK','ALT','LIEU','UNPAID','BEREAVEMENT','PARENTAL') NOT NULL,
  units DECIMAL(10,2) NOT NULL,
  unit_type ENUM('HOURS','DAYS') NOT NULL,
  status ENUM('PENDING','APPROVED','REJECTED','APPLIED') NOT NULL DEFAULT 'PENDING',
  requested_by INT NOT NULL,
  approved_by INT NULL,
  applied_at TIMESTAMP NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (payroll_run_id) REFERENCES payroll_runs(id) ON DELETE SET NULL,
  INDEX idx_employee (employee_id),
  INDEX idx_status (status),
  INDEX idx_payroll_run (payroll_run_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Workflow:**
1. Ops creates leave assignment request (PENDING)
2. Manager approves/rejects
3. System validates entitlement vs balance
4. Exporter merges into pay run payload sent to Xero
5. On success, update balance and mark APPLIED

**Deliverables:**
- [ ] Migration: `007_create_leave_assignments.php`
- [ ] Service: `LeaveAssignmentService.php` - request/approve/apply
- [ ] API: `api/payroll/leave-assignments.php` - REST CRUD
- [ ] View: `views/leave-assignment.php` - ops UI
- [ ] Tests: 12 unit tests (validation, conflicts, rollback)

**Acceptance:**
- ✅ Ops can add Day-in-Lieu to current cycle with approval
- ✅ System prevents over-allocation (insufficient balance)
- ✅ Full audit trail maintained

---

#### **T9: Balance Snapshots & Drift Detection**
**Status:** 🔴 Not Started
**Priority:** P2 (Data quality)
**Estimated:** 5 hours

**Problem:**
- Silent divergence between CIS and Xero balances
- No alerting on discrepancies
- Drift discovered too late

**Solution:**
```sql
CREATE TABLE balance_snapshots (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  snapshot_date DATE NOT NULL,
  employee_id VARCHAR(64) NOT NULL,
  leave_type ENUM('ANNUAL','SICK','ALT','LIEU','UNPAID','BEREAVEMENT','PARENTAL') NOT NULL,
  cis_balance_units DECIMAL(10,2) NOT NULL,
  provider_balance_units DECIMAL(10,2) NOT NULL,
  drift_units DECIMAL(10,2) NOT NULL,
  drift_percentage DECIMAL(5,2) NULL,
  threshold_exceeded TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY unique_snapshot (snapshot_date, employee_id, leave_type),
  INDEX idx_snapshot_date (snapshot_date),
  INDEX idx_threshold (threshold_exceeded)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Nightly Job:**
1. Fetch balances from Xero API for all staff
2. Compare with CIS balances
3. Calculate drift: `Δ = |CIS - Xero|`
4. Flag if `Δ > threshold` (configurable, default 1.0 units)
5. Auto-create incident for ops review

**Deliverables:**
- [ ] Migration: `008_create_balance_snapshots.php`
- [ ] Service: `DriftService.php` - compare, detect, alert
- [ ] CLI: `cli/payroll-drift-scan.php` - nightly cron job
- [ ] View: `views/drift-report.php` - dashboard widget
- [ ] Tests: 10 unit tests (threshold logic, alert generation)

**Acceptance:**
- ✅ Nightly job runs and logs drift report
- ✅ Dashboard shows zero drifts or clear exceptions
- ✅ Incidents auto-created when threshold exceeded

---

#### **T10: Payment Source & Narrative Policy**
**Status:** 🔴 Not Started
**Priority:** P2 (Reconciliation hygiene)
**Estimated:** 3 hours

**Problem:**
- Vend payment narratives vary, making reconciliation hard
- No standard reference format
- Hard to trace payments back to pay runs

**Solution:**
- Standardize: `payment_reference = "PAYRUN:{provider_run_id}:EMP:{employee_id}"`
- Include in every Vend API call and local row
- Validation layer enforces format

**Deliverables:**
- [ ] Helper: `PaymentReferenceBuilder.php` - generate standard refs
- [ ] Update: `VendService.php` - enforce reference in all calls
- [ ] Update: `AllocationService.php` - store reference in applications
- [ ] Tests: 5 unit tests (format validation)

**Acceptance:**
- ✅ Every applied payment has deterministic reference
- ✅ References searchable in Vend and CIS
- ✅ Format: `PAYRUN:PR_2025_11_02:EMP:E123`

---

### 🟡 UX/POLISH & OPS QUALITY (Week 3-6) - PRIORITY 3

#### **T11: Payroll Ops Dashboard**
**Status:** 🔴 Not Started
**Priority:** P3 (Ops efficiency)
**Estimated:** 8 hours

**Features:**
- Run status cards (NEW/APPLYING/PARTIAL/APPLIED/FAILED)
- Staff line outcomes table (SUCCESS/DUPLICATE/FAILED with reasons)
- Residuals widget (total unallocated pennies)
- Drift widget (balance divergence summary)
- Replay buttons (scoped by run/employee/error)
- DLQ monitor with filters
- Quick actions: Retry, Approve, Quarantine review

**Deliverables:**
- [ ] View: `views/payroll-dashboard.php` - main ops page
- [ ] API: `api/payroll/dashboard-stats.php` - metrics endpoint
- [ ] Assets: `assets/js/payroll-dashboard.js` - auto-refresh
- [ ] Assets: `assets/css/payroll-dashboard.css` - styling
- [ ] Tests: 5 integration tests (page load, data accuracy)

**Acceptance:**
- ✅ On-call can close weekly payroll without shell access
- ✅ All critical metrics visible at a glance
- ✅ One-click actions for common ops tasks

---

#### **T12: Guided Mapping Wizard**
**Status:** 🔴 Not Started
**Priority:** P3 (UX improvement)
**Estimated:** 4 hours

**Problem:**
- Unmapped staff errors confusing for ops
- Manual SQL required to add mappings
- No approval workflow

**Solution:**
- 2-step wizard: Search Vend customer → Confirm mapping
- Preview of staff details before save
- Approval logging

**Deliverables:**
- [ ] View: `views/staff-mapping-wizard.php` - guided UI
- [ ] API: `api/payroll/staff-mapping.php` - CRUD endpoints
- [ ] Tests: 8 unit tests (validation, duplicate detection)

**Acceptance:**
- ✅ Zero unmapped staff after intake
- ✅ Wizard shows clear preview before save
- ✅ Audit log records who created mapping

---

## 🧱 PLATFORM & RESILIENCE - PRIORITY 4

#### **T13: Rate Limits, Retries, Circuit-Breakers**
**Status:** 🔴 Not Started
**Priority:** P4 (Stability)
**Estimated:** 6 hours

**Solution:**
- Exponential backoff with jitter: `delay = base * 2^attempt + random(0, jitter)`
- Per-provider circuit breaker states (CLOSED, OPEN, HALF_OPEN)
- Bulkhead isolation (separate thread pools for Vend/Xero)
- Concurrency caps

**Deliverables:**
- [ ] Helper: `RetryHelper.php` - exponential backoff
- [ ] Service: `CircuitBreaker.php` - state machine
- [ ] Config: Add rate limit settings per provider
- [ ] Tests: 12 unit tests (state transitions, jitter distribution)

---

#### **T14: Config Surface**
**Status:** 🔴 Not Started
**Priority:** P4 (Maintainability)
**Estimated:** 2 hours

**Keys to externalize:**
- `PAYROLL_WEEK_START` (default: Tuesday)
- `ALLOW_NEGATIVE_BALANCE` (default: false)
- `ROUNDING_MODE` (default: HALF_UP)
- `MAX_REPLAY_PER_MINUTE` (default: 10)
- `VEND_BATCH_SIZE` (default: 50)
- `PROVIDER_TIMEOUT_MS` (default: 30000)
- `DRIFT_THRESHOLD_UNITS` (default: 1.0)

**Deliverables:**
- [ ] Update: `.env.example` with all new keys
- [ ] Helper: `ConfigHelper.php` - typed getters
- [ ] Tests: 5 unit tests (defaults, validation)

---

#### **T15: PII Scrubbing**
**Status:** 🔴 Not Started
**Priority:** P4 (Security/Privacy)
**Estimated:** 3 hours

**Solution:**
- Central logger masks: names, bank account fragments, emails
- Debug mode toggle: `debug=full` shows unmasked

**Deliverables:**
- [ ] Helper: `LogMasker.php` - regex-based redaction
- [ ] Update: All services to use masked logger
- [ ] Tests: 8 unit tests (masking patterns)


### 🆕 ALIGNMENT & REPORTING (Week 4-6) - PRIORITY 2-3

#### **T16: Schema Reconciliation & Canonical SQL Sync**
**Status:** 🔴 Not Started
**Priority:** P2 (Data integrity)
**Estimated:** 4 hours

**Problem:**
- `_schema/complete_payroll_schema.sql` and automation schema lag behind new migrations
- Missing canonical definitions for `payroll_runs`, `payroll_applications`, `payroll_dlq`, `payroll_residuals`, `staff_identity_map`, `payroll_bonus_events`, `leave_balances`, `leave_conversion_rules`
- AI automation tables require new columns (`request_id`, `idempotency_key`, `risk`, `evidence_url`, `approved_by`)

**Deliverables:**
- [ ] Update `_schema/complete_payroll_schema.sql` with all new/updated tables
- [ ] Update `schema/payroll_ai_automation_schema.sql` with automation columns
- [ ] Validation script comparing migrations ↔ canonical schema
- [ ] Document schema diffs in `docs/SCHEMA_CHANGELOG.md`

**Acceptance:**
- ✅ Running migrations then exporting schema yields zero diff against canonical SQL
- ✅ AI automation tables support CommentOps/replay metadata

---

#### **T17: README & Runbook Refresh**
**Status:** 🔴 Not Started
**Priority:** P3 (Operational clarity)
**Estimated:** 3 hours

**Problem:**
- README lacks Tue→Mon runbook, re-run policy, replay/DLQ steps, drift thresholds
- No documented data contracts for bonuses, leave assignments, timesheet amendments, account deductions

**Deliverables:**
- [ ] Update `README.md` with execution runbook and replay guidance
- [ ] Add data contract snippets (bonus, leave assign, timesheet amendment, deductions)
- [ ] Document DLQ processing and drift thresholds
- [ ] Add CommentOps command reference (`/plan`, `/apply`, etc.)

**Acceptance:**
- ✅ Ops can follow README to execute weekly cycle without tribal knowledge
- ✅ All API/data contracts mirrored in documentation

---

#### **T18: Bonus & Incentive Engine (incl. Google Reviews)**
**Status:** 🔴 Not Started
**Priority:** P2 (Financial completeness)
**Estimated:** 6 hours

**Problem:**
- Bonuses (manual & Google review) not captured with idempotency/evidence
- No exporter integration or rejection handling

**Deliverables:**
- [ ] Migration: `009_create_payroll_bonus_events.php`
- [ ] DAO/Service: `BonusEventDao.php`, `BonusService.php`
- [ ] Rule engine enforcing evidence, per-period caps, DLQ on rejection
- [ ] Exporter integration (merge approved bonuses into pay run)
- [ ] Tests: 12 unit tests (idempotency, evidence, caps)

**Acceptance:**
- ✅ Bonuses applied idempotently with evidence captured
- ✅ Invalid bonuses enter DLQ with actionable reason codes

---

#### **T19: Timesheet Amendment Pipeline**
**Status:** 🔴 Not Started
**Priority:** P2 (Payroll accuracy)
**Estimated:** 5 hours

**Problem:**
- Approved timesheet amendments not reflected in pay runs
- No idempotent recalculation keyed by shift

**Deliverables:**
- [ ] Intake endpoint (controller + validation) for approved amendments
- [ ] Service to recalc gross/OT/leave accrual for Tue→Mon period
- [ ] Idempotency key `ts_amend:{shift_id}:{hours_after}`
- [ ] Tests: 10 unit tests (recalc logic, duplicate skips)

**Acceptance:**
- ✅ Amendments adjust pay run exactly once and log history
- ✅ Conflicts flagged to DLQ with category `CONFLICT`

---

#### **T20: Pay Discrepancy Detector & Weekly Report**
**Status:** 🔴 Not Started
**Priority:** P3 (Monitoring)
**Estimated:** 4 hours

**Problem:**
- No automated detection for HR vs Xero rate mismatches, hour deltas, unapplied bonuses, invalid leave units

**Deliverables:**
- [ ] Scheduled report comparing HR data vs provider
- [ ] Summary stored in `payroll_reports` table + CSV export
- [ ] Dashboard widget surfacing discrepancies with replay links
- [ ] Tests: 6 unit tests (threshold logic)

**Acceptance:**
- ✅ Weekly report generated automatically and linked on dashboard
- ✅ Ops can remediate discrepancies before payday

---

### 🆕 Scope Additions (Nov 2, 2025 Review) - PRIORITY MIXED

#### **T16: Schema Reconciliation Pass (SQL + AI Automation)**
**Status:** 🔴 Not Started
**Priority:** P0 (Keep canonical schema accurate)
**Estimated:** 4 hours

**Problem:**
- Canonical SQL files (`_schema/complete_payroll_schema.sql`, `schema/payroll_ai_automation_schema.sql`) drifted from planned migrations
- Missing tables/columns for idempotency, DLQ, residuals, bonuses, leave, AI automation fields

**Solution:**
- Audit SQL files for presence of: `payroll_runs`, `payroll_applications`, `payroll_dlq`, `payroll_residuals`, `staff_identity_map`, `payroll_bonus_events`, `leave_balances`, `leave_conversion_rules`
- Patch AI automation schema with `request_id`, `idempotency_key`, `risk`, `evidence_url`, `approved_by`
- Generate migration deltas where canonical SQL lacks structures
- Add regression tests ensuring migrations and canonical SQL stay aligned

**Acceptance:**
- ✅ Canonical SQL reflects every production table/column referenced in code
- ✅ New migrations created for any gaps
- ✅ Schema lint script passes with no diffs

---

#### **T17: README & Runbook Refresh**
**Status:** 🔴 Not Started
**Priority:** P1 (Operational clarity)
**Estimated:** 3 hours

**Scope:**
- Extend `human_resources/payroll/README.md` with: Tuesday→Monday runbook, re-run/idempotency policy, replay & DLQ workflow, drift thresholds
- Document data contracts for Bonuses, Leave Assign-to-Payslip, Timesheet Amendments, Account Deductions (include JSON samples)
- Highlight CommentOps commands (`/plan`, `/apply`, `/risk`, `/hold`, `/status`)

**Acceptance:**
- ✅ README reflects current operational model
- ✅ Data contracts copy/paste ready for integrators
- ✅ Ops can follow replay + DLQ steps without external docs

---

#### **T18: Bonus Engine (Including Google Review Rules)**
**Status:** 🔴 Not Started
**Priority:** P1 (Revenue-linked incentives)
**Estimated:** 8 hours

**Deliverables:**
- [ ] Table: `payroll_bonus_events` with idempotency key + evidence URL
- [ ] DAO/Service for manual + automated bonus ingestion
- [ ] Rule engine for Google Review bonuses (per-review amount, period caps, evidence required)
- [ ] Exporter integration to include approved bonuses in current pay run
- [ ] DLQ wiring for rejection reasons (missing evidence, rule violations)

**Acceptance:**
- ✅ Bonuses applied idempotently: `(employee_id, period_end, type, amount_cents)`
- ✅ Invalid submissions land in DLQ with actionable code
- ✅ Google bonus cap enforced with audit trail

---

#### **T19: Timesheet Amendment Pipeline**
**Status:** 🔴 Not Started
**Priority:** P2 (Accuracy)
**Estimated:** 6 hours

**Deliverables:**
- [ ] Intake endpoint for approved amendments (`shift_id`, `hours_before`, `hours_after`, approvals)
- [ ] Idempotency key: `ts_amend:{shift_id}:{hours_after}`
- [ ] Recalculate gross/OT/leave accrual diffs within the Tuesday→Monday window
- [ ] Integration with discrepancy detector + DLQ on conflicts

**Acceptance:**
- ✅ Re-running same amendment is a no-op
- ✅ Payroll reflects amended hours in next run
- ✅ Audit log captures before/after + approver

---

#### **T20: Payroll Discrepancy Detector**
**Status:** 🔴 Not Started
**Priority:** P2 (Quality assurance)
**Estimated:** 5 hours

**Deliverables:**
- [ ] Weekly job comparing HR vs Xero rates, hours deltas, unapplied bonuses, invalid leave units
- [ ] Report feed into Ops Dashboard + CSV export
- [ ] Notification hook when thresholds exceeded

**Acceptance:**
- ✅ Weekly report produced automatically
- ✅ Dashboard surfaces discrepancies with remediation buttons
- ✅ Alerts fired when mismatches breach tolerance

---

#### **T21: XeroPayrollService Hardening (Idempotency & DLQ)**
**Status:** 🔴 Not Started
**Priority:** P0 (Critical path)
**Estimated:** 6 hours

**Scope:**
- Patch `staff-accounts/lib/XeroPayrollService.php`
- Embed idempotency keys (`xero:{payrun_id}:emp:{employee_id}:amt:{amount_cents}`) & write to `payroll_applications`
- Enforce Tuesday→Monday window; quarantine out-of-window rows
- Standardize Vend references: `PAYRUN:{payrun_id}:EMP:{employee_id}`
- Emit normalized error envelopes, push failures to `payroll_dlq`

**Acceptance:**
- ✅ Applying same pay run twice results in `DUPLICATE` in ledger (no double charges)
- ✅ Window violations blocked with quarantine entry
- ✅ All failures visible via DLQ dashboard

---

## 🧪 Testing Strategy

### Unit Tests (Target: 150+ tests)
- [ ] **AllocationService:** 25 tests (FIFO, fallback, residuals, rounding)
- [ ] **IdempotencyKey:** 10 tests (collisions, uniqueness)
- [ ] **LeaveUnitConverter:** 18 tests (hours↔days, precision)
- [ ] **PeriodValidator:** 8 tests (DST, leap year, boundaries)
- [ ] **ErrorEnvelope:** 10 tests (schema validation)
- [ ] **ReplayService:** 15 tests (scope, throttling, idempotency)
- [ ] **DriftService:** 10 tests (threshold logic, alerts)
- [ ] **RetryHelper:** 12 tests (exponential backoff, jitter)
- [ ] **CircuitBreaker:** 12 tests (state transitions)
- [ ] **LogMasker:** 8 tests (PII redaction)
- [ ] **Remaining services:** 22 tests

### Integration Tests (Target: 20+ tests)
- [ ] **Payrun intake → allocation → Vend apply** (happy path)
- [ ] **Idempotency:** Re-run same pay run (no duplicates)
- [ ] **Partial failure → replay → success** (recovery flow)
- [ ] **Leave assignment → payrun merge → Xero export**
- [ ] **Drift detection → incident creation**
- [ ] **DLQ → retry → resolution**
- [ ] **Quarantine → ops review → approval**

### Property Tests (Target: 5+ tests)
- [ ] **Conservation of cents:** ∑(applications) = ∑(deductions)
- [ ] **Idempotency:** f(x) = f(f(x))
- [ ] **Leave balance:** balance_before - units_taken = balance_after
- [ ] **Rounding:** All money operations within ±$0.01
- [ ] **Drift bounds:** |Δ| ≤ threshold

---

## 📦 File Structure (Proposed)

```
modules/human_resources/payroll/
├── controllers/
│   └── PayrollController.php (existing)
├── services/
│   ├── PayrunIntakeService.php          [T2]
│   ├── AllocationService.php            [T4]
│   ├── VendApplyService.php             [T4]
│   ├── LeaveService.php                 [T7]
│   ├── LeaveAssignmentService.php       [T8]
│   ├── DriftService.php                 [T9]
│   ├── ReplayService.php                [T6]
│   ├── DlqService.php                   [T5]
│   └── (existing services...)
├── dao/
│   ├── StaffIdentityDao.php             [T1]
│   ├── PayrollRunDao.php                [T2]
│   ├── PayrollApplicationDao.php        [T2]
│   ├── PayrollResidualDao.php           [T4]
│   ├── PayrollDlqDao.php                [T5]
│   ├── PayrollQuarantineDao.php         [T3]
│   ├── LeaveBalanceDao.php              [T7]
│   └── LeaveAssignmentDao.php           [T8]
├── domain/
│   ├── Payrun.php                       [T2]
│   ├── ApplicationResult.php            [T2]
│   ├── LeaveType.php                    [T7]
│   └── ErrorEnvelope.php                [T5]
├── helpers/
│   ├── MoneyHelper.php                  [T4]
│   ├── LeaveUnitConverter.php           [T7]
│   ├── PaymentReferenceBuilder.php      [T10]
│   ├── PeriodValidator.php              [T3]
│   ├── RetryHelper.php                  [T13]
│   ├── CircuitBreaker.php               [T13]
│   ├── LogMasker.php                    [T15]
│   └── ConfigHelper.php                 [T14]
├── cli/
│   ├── payroll-intake.php               [T2]
│   ├── payroll-apply.php                [T2]
│   ├── payroll-replay.php               [T6]
│   ├── payroll-drift-scan.php           [T9]
│   ├── map-staff-identity.php           [T1]
│   └── review-quarantine.php            [T3]
├── views/
│   ├── payroll-dashboard.php            [T11]
│   ├── dlq-monitor.php                  [T5]
│   ├── drift-report.php                 [T9]
│   ├── replay-manager.php               [T6]
│   ├── leave-assignment.php             [T8]
│   └── staff-mapping-wizard.php         [T12]
├── migrations/
│   ├── 001_create_staff_identity_map.php         [T1]
│   ├── 002_create_payroll_runs_applications.php  [T2]
│   ├── 003_create_payroll_quarantine.php         [T3]
│   ├── 004_create_payroll_residuals.php          [T4]
│   ├── 005_create_payroll_dlq.php                [T5]
│   ├── 006_create_leave_tables.php               [T7]
│   ├── 007_create_leave_assignments.php          [T8]
│   └── 008_create_balance_snapshots.php          [T9]
├── tests/
│   ├── Unit/
│   │   ├── AllocationServiceTest.php             [T4]
│   │   ├── LeaveUnitConverterTest.php            [T7]
│   │   ├── IdempotencyKeyTest.php                [T2]
│   │   └── (150+ tests total)
│   ├── Integration/
│   │   ├── PayrunFlowTest.php                    [T2-T4]
│   │   ├── ReplayFlowTest.php                    [T6]
│   │   └── (20+ tests total)
│   └── Property/
│       ├── ConservationOfCentsTest.php           [T4]
│       └── (5+ tests total)
└── docs/
    ├── PAYROLL_HARDENING_MASTER_PLAN.md (this file)
    ├── RUNBOOKS/
    │   ├── WeeklyPayrollClose.md
    │   ├── RecoveryFromFailure.md
    │   └── DriftResolution.md
    └── API/
        └── PayrollEndpoints.md
```

---

## 📊 Progress Tracking

| Task | Priority | Status | Estimated | Actual | Completion |
|------|----------|--------|-----------|--------|------------|
| T1: ID Mapping | P0 | � In Progress | 4h | - | 40% |
| T2: Idempotency | P0 | 🔴 Not Started | 6h | - | 0% |
| T3: Weekly Windows | P1 | 🔴 Not Started | 3h | - | 0% |
| T4: Reconciliation | P0 | 🔴 Not Started | 8h | - | 0% |
| T5: Error/DLQ | P1 | 🔴 Not Started | 5h | - | 0% |
| T6: Replay Engine | P1 | 🔴 Not Started | 6h | - | 0% |
| T7: Leave Model | P2 | 🔴 Not Started | 8h | - | 0% |
| T8: Leave Assignment | P2 | 🔴 Not Started | 6h | - | 0% |
| T9: Drift Detection | P2 | 🔴 Not Started | 5h | - | 0% |
| T10: Payment Refs | P2 | 🔴 Not Started | 3h | - | 0% |
| T11: Dashboard | P3 | 🔴 Not Started | 8h | - | 0% |
| T12: Mapping Wizard | P3 | 🔴 Not Started | 4h | - | 0% |
| T13: Rate Limits | P4 | 🔴 Not Started | 6h | - | 0% |
| T14: Config | P4 | 🔴 Not Started | 2h | - | 0% |
| T15: PII Scrubbing | P4 | 🔴 Not Started | 3h | - | 0% |
| T16: Schema Reconciliation | P0 | 🔴 Not Started | 4h | - | 0% |
| T17: README Refresh | P1 | 🔴 Not Started | 3h | - | 0% |
| T18: Bonus Engine | P1 | 🔴 Not Started | 8h | - | 0% |
| T19: Timesheet Amendments | P2 | 🔴 Not Started | 6h | - | 0% |
| T20: Discrepancy Detector | P2 | 🔴 Not Started | 5h | - | 0% |
| T21: XeroPayrollService Hardening | P0 | 🔴 Not Started | 6h | - | 0% |
| **TOTAL** | | | **109h** | **0h** | **0%** |

---

## 🎯 Success Criteria

### Week 2 (Critical Fixes Complete)
- ✅ T1-T6 complete (ID mapping, idempotency, windows, reconciliation, DLQ, replay)
- ✅ Zero duplicate payment risk
- ✅ 100% staff mapped before apply
- ✅ All failures enter DLQ with replay capability
- ✅ 50+ unit tests passing

### Week 4 (High-Impact Complete)
- ✅ T7-T10 complete (leave model, assignments, drift, payment refs)
- ✅ Day-in-Lieu fully supported
- ✅ Nightly drift detection running
- ✅ 100+ unit tests + 10 integration tests passing

### Week 6 (Production-Ready)
- ✅ T11-T15 complete (dashboard, wizard, rate limits, config, PII)
- ✅ Ops can manage payroll from UI (no shell access)
- ✅ 150+ unit tests, 20+ integration tests, 5+ property tests
- ✅ All runbooks complete
- ✅ Load testing passes (no thundering herd)
- ✅ Security audit clean (PII redacted)

---

## 🚀 Execution Strategy

### Rate-Limited Approach
- **Work in 30-minute focused blocks**
- **Pause 5 minutes between blocks** to avoid rate limits
- **Commit after each task completion** (T1, T2, etc.)
- **Run tests before committing** to catch regressions early
- **One PR per week** to facilitate review

### Quality Gates
- [ ] All new code has tests (min 80% coverage)
- [ ] No SQL injection vulnerabilities (prepared statements only)
- [ ] All money operations use cents (never floats)
- [ ] PII redacted in logs by default
- [ ] Error envelopes for all failure paths

### Communication
- Update this document after each task completion
- Commit message format: `feat(payroll): T{N} Complete - {Description}`
- Tag commits: `payroll-hardening-t1`, `payroll-hardening-t2`, etc.

---

## 📚 Reference Materials

### Code Snippets

#### Idempotency Key Generation
```php
// services/PayrunIntakeService.php
private function generateIdempotencyKey(string $payrunId, string $employeeId, int $amountCents): string
{
    return sprintf('xero:%s:emp:%s:amt:%d', $payrunId, $employeeId, $amountCents);
}
```

#### Allocation Result Contract
```php
// domain/ApplicationResult.php
class ApplicationResult
{
    public int $allocatedCents;
    public int $residualCents;
    public array $applications; // ['invoice_id' => 'S-123', 'applied_cents' => 120000]
    public array $notes; // ['FIFO', 'sale-line fallback not needed']
}
```

#### Error Envelope Example
```php
// helpers/ErrorEnvelope.php
ErrorEnvelope::upstream(
    'VEND_429',
    'Too many requests',
    'Retry after 30s',
    [
        'provider_run_id' => 'PR_2025_11_02',
        'employee_id' => 'E123'
    ]
);
```

### Runbook Checklists

#### Before Apply
- [ ] Mapping = 100% (all staff have Vend customers)
- [ ] Intake rows in valid window (Tuesday-Monday)
- [ ] Provider auth OK (Xero API responding)
- [ ] Vend health OK (API responding, no maintenance)
- [ ] Previous run state = APPLIED (not stuck in APPLYING)

#### If Partial Failure
1. Open DLQ monitor
2. Group by error_code
3. Replay by error class (e.g., all VEND_TIMEOUT)
4. Check residuals widget
5. Re-apply idempotently
6. Verify state = APPLIED

#### End-of-Week
- [ ] Drift report = clean (no threshold violations)
- [ ] Dashboard shows all runs = APPLIED (green)
- [ ] Archive logs (rotate older than 30 days)
- [ ] Snapshot balances for next week

---

## 🔐 Security Considerations

### PII Handling
- **Names:** Mask in logs (`John D***`)
- **Bank accounts:** Mask all but last 4 digits (`***1234`)
- **Emails:** Mask domain (`j***@example.com`)
- **Amounts:** Never log full salary, only deduction amounts

### Database
- **Encryption at rest:** Enable for sensitive columns
- **Prepared statements:** 100% coverage (no string concatenation)
- **Audit logging:** Who/when for all write operations
- **Row-level security:** Staff can only see own data

### API
- **Authentication:** JWT tokens with 1-hour expiry
- **Authorization:** Role-based (admin, manager, staff)
- **Rate limiting:** 100 req/min per user
- **HTTPS only:** No plain HTTP allowed

---

## 📞 Support & Escalation

### On-Call Procedures
- **P0 (Critical):** Zero staff paid, Vend API down → Page immediately
- **P1 (High):** Partial failure, <10% staff affected → Alert ops channel
- **P2 (Medium):** Drift detected, DLQ growing → Create ticket
- **P3 (Low):** Residuals accumulating, UI polish → Backlog

### Contact Points
- **Payroll Lead:** [TBD]
- **Engineering Lead:** [TBD]
- **Finance Lead:** [TBD]
- **On-Call Rotation:** [TBD]

---

## 📅 Timeline Visualization

```
Week 0-2: CRITICAL FIXES (T1-T6)
├─ T1: ID Mapping (4h)                    [Mon-Tue]
├─ T2: Idempotency (6h)                   [Tue-Wed]
├─ T3: Weekly Windows (3h)                [Wed]
├─ T4: Reconciliation (8h)                [Thu-Fri]
├─ T5: Error/DLQ (5h)                     [Fri]
└─ T6: Replay Engine (6h)                 [Mon Week 2]

Week 2-4: HIGH-IMPACT (T7-T10)
├─ T7: Leave Model (8h)                   [Tue-Wed Week 2]
├─ T8: Leave Assignment (6h)              [Thu Week 2]
├─ T9: Drift Detection (5h)               [Fri Week 2]
└─ T10: Payment Refs (3h)                 [Mon Week 3]

Week 3-6: POLISH & PLATFORM (T11-T15)
├─ T11: Dashboard (8h)                    [Tue-Wed Week 3]
├─ T12: Mapping Wizard (4h)               [Thu Week 3]
├─ T13: Rate Limits (6h)                  [Thu-Fri Week 4]
├─ T14: Config (2h)                       [Mon Week 5]
└─ T15: PII Scrubbing (3h)                [Mon Week 5]

Week 6: TESTING & DOCUMENTATION
├─ Integration tests (16h)                [Mon-Wed]
├─ Property tests (8h)                    [Thu]
├─ Runbooks (4h)                          [Fri]
└─ Load testing (4h)                      [Fri]
```

---

**END OF MASTER PLAN**

*This document will be updated as tasks complete. Current status: Planning phase.*
