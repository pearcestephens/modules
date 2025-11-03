# 🧠 PAYROLL MODULE - COMPLETE DEEP DIVE ANALYSIS
**Generated:** 2025-11-02 23:15 NZDT
**Analyst:** Payroll Builder Bot
**Status:** Phase 0 - Discovery Complete

---

## 📊 EXECUTIVE SUMMARY

The Payroll module is a **sophisticated, AI-powered payroll automation system** that integrates:
- **Deputy** (timesheets & scheduling)
- **Xero** (pay runs & accounting)
- **Vend** (staff account deductions)
- **CIS** (staff identity mapping)

**Current State:** Production-ready foundation with 12+ services, comprehensive schema, health endpoints, and test coverage.

**Gap Analysis:** Missing Phase 1-12 components from the master plan (idempotency, DLQ, replay, bonus/leave services).

---

## 🗂️ DIRECTORY STRUCTURE MAPPING

```
human_resources/payroll/
├── _kb/                          ⭐ [NEW - THIS DOCUMENT]
├── _schema/                      📋 Complete database schemas
│   ├── complete_payroll_schema.sql
│   ├── payslip_snapshot_schema.sql
│   └── wage_discrepancies_schema.sql
├── ai/                          🤖 AI Decision Engine
│   └── AgentEngine.php
├── assets/                      🎨 CSS/JS assets
│   ├── css/
│   └── js/
├── cli/                         🔧 Command-line tools
│   ├── payroll-health.php
│   ├── sync_payruns.php
│   ├── map-staff-identity.php
│   ├── run-reconciliation.php
│   ├── snapshot_payslip.php
│   ├── activity-log.php
│   └── rate-limit-report.php
├── controllers/                 🎮 HTTP Request handlers
│   ├── BaseController.php       [Abstract base]
│   ├── DashboardController.php
│   ├── PayRunController.php
│   ├── PayslipController.php
│   ├── ReconciliationController.php
│   ├── BonusController.php
│   ├── LeaveController.php
│   ├── AmendmentController.php
│   ├── VendPaymentController.php
│   ├── WageDiscrepancyController.php
│   ├── XeroController.php
│   └── PayrollAutomationController.php
├── cron/                        ⏰ Scheduled jobs
├── dao/                         💾 Data Access Objects
│   └── StaffIdentityDao.php
├── docs/                        📚 Documentation
├── health/                      🩺 Health endpoints
│   └── index.php                ✅ CURRENT FILE - WORKING
├── lib/                         📦 Shared libraries
│   ├── PayrollLogger.php        [Structured logging]
│   ├── PayrollSyncService.php
│   ├── PayslipPdfGenerator.php
│   ├── PayslipEmailer.php
│   ├── EmailQueueHelper.php
│   ├── PiiRedactor.php
│   ├── XeroTokenStore.php
│   ├── PayrollSnapshotManager.php
│   └── VapeShedDb.php
├── logs/                        📝 Application logs
├── middleware/                  🛡️ Middleware layers
├── migrations/                  🗄️ [EMPTY - Need Phase 1]
├── router.php                   🚦 Main router
├── routes.php                   🗺️ Route definitions
├── schema/                      📐 Schema definitions
│   ├── payroll_ai_automation_schema.sql  [806 lines!]
│   ├── 12_rate_limits.sql
│   └── 03_payslips.sql
├── services/                    ⚙️ Business Logic Services
│   ├── BaseService.php          [Abstract]
│   ├── PayrollDeputyService.php ✅ PRODUCTION READY
│   ├── PayrollXeroService.php   🚧 STUB - needs expansion
│   ├── DeputyService.php
│   ├── XeroService.php
│   ├── VendService.php
│   ├── BonusService.php
│   ├── AmendmentService.php
│   ├── PayslipService.php
│   ├── PayslipCalculationEngine.php
│   ├── PayrollAutomationService.php
│   ├── BankExportService.php
│   ├── NZEmploymentLaw.php
│   ├── EncryptionService.php
│   ├── PayrollAuthAuditService.php
│   └── HttpRateLimitReporter.php
├── tests/                       🧪 Test suites
│   ├── E2E/
│   ├── Integration/
│   ├── Security/
│   ├── Unit/
│   └── Web/
└── views/                       👁️ UI Templates
    ├── layouts/
    ├── errors/
    └── widgets/
```

---

## 🗄️ DATABASE SCHEMA DEEP DIVE

### **Core Tables (Existing)**

#### 1. `payroll_activity_log`
**Purpose:** Centralized structured logging for all payroll operations
**Columns:**
- `id` (PK)
- `log_level` (info/warning/error/debug)
- `category` (deputy/xero/vend/reconciliation/bonus/leave)
- `action` (descriptive event name)
- `message` (human-readable)
- `details` (JSON - full context)
- `created_at`

**Indexes:**
- `idx_category_created` (category, created_at)
- `idx_level_created` (log_level, created_at)

---

#### 2. `payroll_rate_limits`
**Purpose:** Track 429 responses from external APIs (Deputy/Xero)
**Columns:**
- `id` (PK)
- `service` (ENUM: xero, deputy)
- `endpoint` (VARCHAR 120)
- `http_status` (SMALLINT)
- `retry_after_sec` (INT nullable)
- `occurred_at` (TIMESTAMP)
- `request_id` (VARCHAR 64)
- `payload_hash` (CHAR 64)

**View:** `v_rate_limit_7d` - 7-day summary by service/endpoint

---

#### 3. `payroll_timesheet_amendments`
**Purpose:** Staff timesheet correction requests with AI review
**Key Features:**
- Claimed vs Actual vs Approved times
- Break minutes tracking
- AI decision engine integration
- Status workflow (pending → ai_review → approved/declined/escalated)
- Deputy sync capability

**AI Fields:**
- `ai_reviewed` (boolean)
- `ai_decision` (ENUM: approve/decline/escalate/needs_info)
- `ai_confidence_score` (DECIMAL 0.0000-1.0000)
- `ai_reasoning` (TEXT)
- `ai_model_version`

---

#### 4. `payroll_payrun_line_adjustments`
**Purpose:** Staff requests to modify payslip line items
**Supports:**
- Add/modify/remove earnings/deductions
- Evidence attachment (JSON array of files)
- Financial impact calculation (gross/tax/net)
- AI risk scoring
- Xero application tracking

---

#### 5. `payroll_vend_payment_requests`
**Purpose:** Automated staff account deductions
**Workflow:**
- Request created (from payslip)
- Allocations calculated (FIFO against open invoices)
- Status tracking (pending → processing → completed/failed)
- Idempotency via `idempotency_key`

---

#### 6. `payroll_bank_payment_batches`
**Purpose:** ANZ bank file export batches
**Features:**
- Batch status tracking
- File hash verification
- Sent/acknowledged tracking
- Total amounts reconciliation

---

#### 7. `payroll_ai_decisions`
**Purpose:** Complete audit trail of AI decisions
**Captures:**
- Decision context (JSON)
- Model version & parameters
- Input/output data
- Confidence scores
- Override tracking

---

#### 8. `payroll_context_snapshots`
**Purpose:** Point-in-time snapshots of payroll state
**Use Cases:**
- Debugging
- Audit compliance
- Rollback capability
- Historical analysis

---

#### 9. `staff_identity_map`
**Purpose:** Cross-system staff ID mapping
**Links:**
- CIS user_id
- Deputy employee_id
- Xero employee_id
- Vend customer_id

**Critical for:** Idempotent operations across systems

---

### **Missing Tables (Phase 1 Required)**

#### 🚨 `payroll_runs`
**Purpose:** Track payroll processing runs
**Needed for:**
- Idempotency (provider_run_id)
- State tracking (NEW → APPLYING → APPLIED → FAILED)
- Period management (period_start, period_end)
- Replay capability

---

#### 🚨 `payroll_applications`
**Purpose:** Individual payment applications to Vend
**Needed for:**
- FIFO allocation tracking
- Duplicate prevention (idempotency_key)
- Status per employee
- Residual tracking

---

#### 🚨 `payroll_dlq` (Dead Letter Queue)
**Purpose:** Failed operations that need manual/automated retry
**Needed for:**
- Error envelope storage
- Replay mechanism
- Category-based filtering
- Retry tracking

---

#### 🚨 `payroll_residuals`
**Purpose:** Amounts that couldn't be fully allocated
**Needed for:**
- Carry-forward logic
- Allocation reconciliation
- Next-run processing

---

#### 🚨 `staff_leave_balances`
**Purpose:** Leave entitlement tracking
**Types:** ANNUAL, SICK, ALT, LIEU, UNPAID
**Units:** HOURS, DAYS

---

#### 🚨 `leave_conversion_rules`
**Purpose:** Convert between leave units (hours ↔ days)
**Example:** 1 DAY = 8 HOURS (factor: 8.0)

---

#### 🚨 `payroll_bonus_events`
**Purpose:** Bonus payments (manual & Google review-based)
**Features:**
- Type tracking (MANUAL, GOOGLE_REVIEW, PERFORMANCE)
- Evidence URL for Google reviews
- Cap enforcement ($50 for reviews)
- Approval workflow

---

## 🔧 SERVICE LAYER ANALYSIS

### **✅ Production-Ready Services**

#### `PayrollDeputyService`
**Location:** `services/PayrollDeputyService.php`
**Purpose:** Wrapper for Deputy API with rate-limit telemetry
**Features:**
- Wraps `assets/functions/deputy.php`
- Logs all calls to `payroll_activity_log`
- Captures 429 responses to `payroll_rate_limits`
- Structured error handling

**Methods:**
- `fetchTimesheets(string $start, string $end): array`

**Dependencies:**
- `PayrollLogger` (for structured logging)
- `Deputy` global functions (from assets/functions/deputy.php)

**Rate Limit Handling:**
```php
try {
    $result = Deputy::getTimesheets($params);
} catch (DeputyRateLimitException $e) {
    $retryAfter = $e->getRetryAfter();
    $this->persistRateLimit('deputy', $endpoint, $retryAfter);
    throw $e; // Propagate for caller to handle
}
```

---

#### `PayrollXeroService`
**Location:** `services/PayrollXeroService.php`
**Status:** 🚧 STUB - Needs expansion
**Current:**
- `listEmployees(): array` (returns empty array)
- `logActivity()` method for audit trail

**TODO Phase 2:**
- Fetch pay runs
- Fetch payslips
- Create/update pay runs
- Submit pay runs
- OAuth token refresh handling

---

#### `BonusService`
**Location:** `services/BonusService.php`
**Features:**
- Google review bonus automation ($50 cap)
- Manual bonus entry
- Evidence validation
- Integration with pay runs

---

#### `PayslipCalculationEngine`
**Location:** `services/PayslipCalculationEngine.php`
**Features:**
- Gross/net calculation
- Tax calculation (NZ PAYE)
- Deduction application
- Leave accrual

---

#### `VendService`
**Location:** `services/VendService.php`
**Purpose:** Staff account deduction management
**Features:**
- Fetch open invoices per customer
- Calculate deduction amounts
- Apply payments idempotently

---

### **🚧 Services Needed (Phase 1-7)**

#### `PayrunIntakeService`
**Purpose:** Ingest pay run data with windowing validation
**Features:**
- Period window validation (e.g., pay period must be Tue-Mon)
- Quarantine out-of-window entries
- Duplicate detection via idempotency

---

#### `AllocationService`
**Purpose:** FIFO allocation of deductions to open invoices
**Algorithm:**
```
allocated = []
remaining = deduction_amount
for invoice in open_invoices (sorted by date ASC):
    if remaining == 0: break
    apply = min(remaining, invoice.open_balance)
    allocated.append({invoice_id, amount: apply})
    remaining -= apply

return {
    allocated_cents,
    residual_cents: remaining,
    applications: allocated,
    notes: ["FIFO"]
}
```

---

#### `VendApplyService`
**Purpose:** Apply allocations to Vend with idempotency
**Features:**
- Idempotency key generation
- Duplicate detection (INSERT IGNORE)
- Status tracking (PENDING → SUCCESS/FAILED)
- Residual recording

---

#### `LeaveService`
**Purpose:** Leave balance management
**Features:**
- Balance queries
- Unit conversion (hours ↔ days)
- Pending adjustments
- Assign to payslip

---

#### `ReconciliationService`
**Purpose:** Cross-system drift detection
**Features:**
- Compare Deputy hours → Xero hours
- Generate drift reports
- Threshold-based DLQ alerts

---

## 🩺 HEALTH ENDPOINT ANALYSIS

**Location:** `human_resources/payroll/health/index.php`

### Current Implementation ✅

```php
Checks:
1. db_ping - SELECT 1 test
2. table_exists:deputy_timesheets
3. table_exists:payroll_activity_log

Response Format:
{
    "ok": true/false,
    "checks": [
        {"name": "db_ping", "ok": true},
        {"name": "table_exists:deputy_timesheets", "ok": true},
        ...
    ]
}
```

### Missing Checks (Phase 2.3 Required)

```php
4. table_exists:payroll_runs
5. table_exists:payroll_applications
6. table_exists:payroll_dlq
7. table_exists:staff_identity_map
8. table_exists:payroll_residuals
9. table_exists:staff_leave_balances
10. table_exists:payroll_bonus_events
```

### Auth Gate (Phase 10.1 Required)

```php
// Check PAYROLL_AUTH_ENABLED flag
if (env('PAYROLL_AUTH_ENABLED', 'false') !== 'true') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Payroll system disabled']);
    exit;
}
```

---

## 🔐 SECURITY & COMPLIANCE

### Current Security Features ✅

1. **PII Redaction**
   - `PiiRedactor` class in `lib/`
   - Redacts sensitive fields from logs

2. **Encryption Service**
   - `EncryptionService` for token storage
   - Key rotation support

3. **Auth Audit**
   - `PayrollAuthAuditService` tracks enable/disable toggles
   - IP address logging

4. **Snapshot Management**
   - `PayrollSnapshotManager` for point-in-time backups
   - Encrypted snapshots

5. **Rate Limit Tracking**
   - Prevents API abuse
   - Retry-after header respect

### Missing Security (Phase 10)

1. **Auth Audit Log Table**
   - `payroll_auth_audit_log` (action, actor, flag_before, flag_after, IP)

2. **Prod Deployment Guard**
   - CI gate that blocks deploy unless `PAYROLL_AUTH_ENABLED=true` in prod `.env`

---

## 🔗 INTEGRATION POINTS

### Deputy Integration
**Entry Point:** `assets/functions/deputy.php` (global)
**Wrapper:** `PayrollDeputyService`
**Data Flow:**
1. Fetch timesheets via Deputy API
2. Log to `payroll_activity_log`
3. Map Deputy IDs via `staff_identity_map`
4. Process timesheet amendments

### Xero Integration
**Wrapper:** `PayrollXeroService` (stub)
**OAuth:** `XeroTokenStore` for token management
**Data Flow:**
1. Fetch pay runs & payslips
2. Calculate adjustments
3. Submit updated pay runs
4. Track in `payroll_runs` table

### Vend Integration
**Wrapper:** `VendService`
**Purpose:** Staff account deductions
**Data Flow:**
1. Query open invoices per `vend_customer_id`
2. Allocate deduction via FIFO
3. Apply payment to Vend
4. Record in `payroll_applications`
5. Track residuals

### CIS Integration
**Link:** `staff_identity_map` table
**Purpose:** Unified staff identity across systems
**Critical for:** Idempotency keys, cross-system queries

---

## 📋 GAP ANALYSIS - MASTER PLAN vs CURRENT STATE

### ✅ COMPLETE (Phases 0-partial 2)

| Component | Status |
|-----------|--------|
| Directory structure | ✅ |
| Services layer | ✅ (base classes) |
| Controllers | ✅ |
| Health endpoint shell | ✅ |
| Rate limit telemetry | ✅ |
| Activity logging | ✅ |
| PII redaction | ✅ |
| Encryption | ✅ |
| Snapshot management | ✅ |
| Test suites | ✅ |

### 🚧 IN PROGRESS

| Component | Status |
|-----------|--------|
| PayrollDeputyService | ✅ Production ready |
| PayrollXeroService | 🚧 Stub only |
| Health endpoint checks | 🚧 Missing new tables |

### ❌ MISSING (Phases 1-12)

#### Phase 0: Baseline (5%) - **PARTIAL**
- ❌ `autoload.php` (PSR-4 for `Payroll\` namespace)
- ❌ `bootstrap.php` (timezone, error_reporting, env-loader)
- ❌ `lib/Respond.php` (JSON envelope helper)
- ❌ `lib/Validate.php` (input validation)
- ❌ `lib/Idempotency.php` (key generation)
- ❌ `lib/ErrorEnvelope.php` (error normalization)

#### Phase 1: Schema & Ledger (12%) - **MISSING**
- ❌ Migration: `migrations/2025_11_XX_core.sql`
- ❌ Tables: `payroll_runs`, `payroll_applications`, `payroll_dlq`, `payroll_residuals`, `payroll_bonus_events`
- ❌ Idempotency enforcement

#### Phase 2: Services & Health (10%) - **PARTIAL**
- ✅ `PayrollDeputyService` (DONE)
- 🚧 `PayrollXeroService` (stub needs expansion)
- 🚧 Health endpoint (needs new table checks + auth gate)

#### Phase 3: Intake & Windowing (8%) - **MISSING**
- ❌ `config.php` (WEEK_START, TZ, HEALTH_TABLES)
- ❌ `services/PayrunIntakeService.php`
- ❌ Quarantine logic for out-of-window rows

#### Phase 4: Allocation & Application (14%) - **MISSING**
- ❌ `services/AllocationService.php` (FIFO logic)
- ❌ `services/VendApplyService.php` (idempotent apply)
- ❌ Residual tracking

#### Phase 5: DLQ & Replay (10%) - **MISSING**
- ❌ Error envelope insertion to `payroll_dlq`
- ❌ `cli/payroll-replay.php` (flags: --run, --employee, --code)
- ❌ Replay idempotency

#### Phase 6: Leave Balances (10%) - **MISSING**
- ❌ Tables: `staff_leave_balances`, `leave_conversion_rules`
- ❌ `services/LeaveService.php`
- ❌ `api/assign-leave.php` endpoint

#### Phase 7: Bonuses (7%) - **PARTIAL**
- ✅ `BonusService` exists
- ❌ Google review bonus automation
- ❌ Cap enforcement ($50)
- ❌ Evidence validation

#### Phase 8: Reconciliation (9%) - **PARTIAL**
- ✅ `cli/run-reconciliation.php` exists
- ❌ `services/ReconciliationService.php` (compareDeputyToXero)
- ❌ `cli/payroll-drift-scan.php` (CSV export)
- ❌ Drift DLQ alerts

#### Phase 9: Ops Heartbeat (5%) - **MISSING**
- ❌ Extended health checks (new tables)
- ❌ `cli/payroll-heartbeat.php` (runs/DLQ/residuals summary JSON)

#### Phase 10: Auth Audit (5%) - **PARTIAL**
- ✅ `PayrollAuthAuditService` exists
- ❌ `payroll_auth_audit_log` table
- ❌ Audit insertion on toggle
- ❌ Prod deployment guard

#### Phase 11: Documentation (3%) - **PARTIAL**
- ❌ `README.md`
- ❌ `docs/RUNBOOK.md`
- ❌ `docs/CONTRACTS.md`
- ❌ `.env.example` updates

#### Phase 12: Release Readiness (2%) - **MISSING**
- ❌ Test sweep
- ❌ Replay verification
- ❌ `FINAL_CHECKLIST.md`

---

## 🎯 RECOMMENDED IMPLEMENTATION ORDER

### **Sprint 1: Foundation (Phases 0-1) - 17%**
1. Create `autoload.php` + `bootstrap.php`
2. Create lib helpers (Respond, Validate, Idempotency, ErrorEnvelope)
3. Create migration with all missing tables
4. Run migration idempotently
5. **Deliverable:** Core infrastructure ready

### **Sprint 2: Services R1 (Phases 2-3) - 18%**
1. Expand `PayrollXeroService`
2. Add table checks to health endpoint
3. Add auth gate to health endpoint
4. Create `config.php`
5. Create `PayrunIntakeService`
6. **Deliverable:** Intake + health validated

### **Sprint 3: Application Logic (Phase 4) - 14%**
1. Create `AllocationService` with FIFO
2. Create `VendApplyService` with idempotency
3. Wire into existing `VendService`
4. **Deliverable:** Payment application works, no dupes

### **Sprint 4: Error Handling (Phase 5) - 10%**
1. Add DLQ inserts on exceptions
2. Create `cli/payroll-replay.php`
3. Test replay scenarios
4. **Deliverable:** Failed ops can be retried

### **Sprint 5: Leave & Bonus (Phases 6-7) - 17%**
1. Create leave tables
2. Create `LeaveService`
3. Create `api/assign-leave.php`
4. Expand `BonusService` for Google reviews
5. **Deliverable:** Leave/bonus features complete

### **Sprint 6: Reconciliation (Phase 8) - 9%**
1. Create `ReconciliationService`
2. Create `cli/payroll-drift-scan.php`
3. Wire drift alerts to DLQ
4. **Deliverable:** Drift detection automated

### **Sprint 7: Ops & Auth (Phases 9-10) - 10%**
1. Extend health checks
2. Create `cli/payroll-heartbeat.php`
3. Create `payroll_auth_audit_log` table
4. Wire audit logging
5. Add prod deployment guard
6. **Deliverable:** Ops visibility + auth tracking

### **Sprint 8: Documentation & Release (Phases 11-12) - 5%**
1. Write README
2. Write RUNBOOK
3. Write CONTRACTS
4. Update .env.example
5. Run full test sweep
6. Create FINAL_CHECKLIST
7. **Deliverable:** Production-ready sign-off

---

## 🔍 KEY VARIABLE & FUNCTION TRACING

### **Staff Identity Resolution**
```
CIS user_id → staff_identity_map → deputy_employee_id
                                 → xero_employee_id
                                 → vend_customer_id
```

**Critical Function:** `StaffIdentityDao::resolveIdentity(int $userId): array`

---

### **Idempotency Key Generation**
```php
namespace Payroll\Lib;

class Idempotency {
    public static function keyFor(string $ns, array $parts): string {
        ksort($parts); // Deterministic order
        $payload = $ns . '|' . json_encode($parts, JSON_UNESCAPED_SLASHES);
        return hash('sha256', $payload);
    }
}

// Example usage:
$key = Idempotency::keyFor('xero.apply', [
    'run' => 'PR_2025_10_27',
    'emp' => 'E123',
    'cents' => 45000
]);
// Always same key for same inputs → prevents duplicate application
```

---

### **FIFO Allocation Logic**
```php
// In AllocationService::allocate()
public function allocate(int $deductionCents, array $openInvoices): array {
    $apps = [];
    $left = $deductionCents;

    // Invoices pre-sorted by date ASC
    foreach ($openInvoices as $inv) {
        if ($left <= 0) break;

        $apply = min($left, $inv['open_cents']);
        if ($apply > 0) {
            $apps[] = [
                'invoice_id' => $inv['invoice_id'],
                'applied_cents' => $apply
            ];
            $left -= $apply;
        }
    }

    return [
        'allocated_cents' => $deductionCents - $left,
        'residual_cents' => $left,
        'applications' => $apps,
        'notes' => ['FIFO']
    ];
}
```

---

### **Error Envelope Normalization**
```php
namespace Payroll\Lib;

class ErrorEnvelope {
    public static function from(\Throwable $e, array $meta = []): array {
        $code = $e instanceof \PDOException ? 'DB_ERROR' : 'UNEXPECTED';
        $category = $e instanceof \PDOException ? 'DB' : 'INTERNAL';

        return [
            'ok' => false,
            'request_id' => bin2hex(random_bytes(8)),
            'category' => $category,
            'code' => $code,
            'message' => substr($e->getMessage(), 0, 240),
            'meta' => $meta
        ];
    }
}

// Insert to payroll_dlq for retry
```

---

### **Rate Limit Persistence**
```php
// In PayrollDeputyService
private function persistRateLimit(string $provider, string $endpoint, $retryAfter): void {
    $sql = "INSERT INTO payroll_rate_limits
            (provider, endpoint, retry_after, occurred_at)
            VALUES (?, ?, ?, NOW())";
    $stmt = $this->db->prepare($sql);
    $stmt->execute([$provider, $endpoint, $retryAfter]);
}
```

---

### **Activity Logging Pattern**
```php
// All services use PayrollLogger
$this->logger->log(PayrollLogger::INFO, $message, [
    'module' => 'payroll.deputy',
    'action' => 'deputy.api.call',
    'start' => $start,
    'end' => $end,
    'result_count' => count($result)
]);

// Inserts to payroll_activity_log with:
// - log_level (info/warning/error/debug)
// - category
// - action
// - message
// - details (JSON)
// - created_at
```

---

## 🧪 TEST COVERAGE ANALYSIS

### Existing Test Structure ✅
```
tests/
├── E2E/
│   └── FullReconciliationFlowTest.php
├── Integration/
│   └── (multiple integration tests)
├── Security/
│   └── (security-focused tests)
├── Unit/
│   ├── RouteDefinitionsTest.php
│   ├── ValidationEngineTest.php
│   ├── AmendmentControllerTest.php
│   ├── PayrollReconciliationServiceTest.php
│   ├── SecurityConfigTest.php
│   └── Migrations/
└── Web/
    └── (browser-based tests)
```

### Test Gaps (Phases 1-12)
- ❌ Idempotency tests (duplicate prevention)
- ❌ AllocationService FIFO tests
- ❌ VendApplyService retry tests
- ❌ DLQ insertion tests
- ❌ Replay CLI tests
- ❌ Leave service unit tests
- ❌ Bonus cap enforcement tests
- ❌ Drift detection tests

---

## 🚀 NEXT ACTIONS (PRIORITIZED)

### **IMMEDIATE (Next 2 hours)**
1. ✅ **This document created** - knowledge base established
2. 🔧 **Create Phase 0 files** (autoload, bootstrap, lib helpers)
3. 🗄️ **Create Phase 1 migration** (all missing tables)
4. 🩺 **Extend health endpoint** (new table checks + auth gate)
5. 📝 **Commit & push** - Mark Phase 0 complete

### **SHORT TERM (Next session)**
1. Create `config.php`
2. Create `PayrunIntakeService`
3. Create `AllocationService`
4. Create `VendApplyService`
5. Test idempotency flow

### **MEDIUM TERM (This week)**
1. DLQ + Replay implementation
2. Leave & Bonus services
3. Reconciliation service
4. Ops heartbeat CLI

### **LONG TERM (Next sprint)**
1. Full documentation
2. Test coverage to 90%+
3. Final checklist
4. Production sign-off

---

## 📚 CRITICAL RESOURCES

### Configuration
- `.env` - Database credentials, API keys
- `.env.example` - Template (needs PAYROLL_AUTH_ENABLED)

### External Dependencies
- `assets/functions/deputy.php` - Deputy API wrapper
- `config/env-loader.php` - Environment variable loader
- `base/` module - Shared CIS infrastructure

### Documentation Needed
- `README.md` - Module overview
- `docs/RUNBOOK.md` - Operational procedures
- `docs/CONTRACTS.md` - API contracts

---

## 🎯 SUCCESS METRICS

### Phase 0 Complete When:
- ✅ All lib helpers created (Respond, Validate, Idempotency, ErrorEnvelope)
- ✅ Bootstrap + autoload work
- ✅ `php -l` clean on all new files

### Phase 1 Complete When:
- ✅ Migration runs idempotently
- ✅ All new tables exist in DB
- ✅ Unique constraints prevent duplicates

### Production Ready When:
- ✅ All 12 phases complete
- ✅ Test coverage >85%
- ✅ Health endpoint green
- ✅ Replay verified
- ✅ Documentation complete
- ✅ No secrets in repo
- ✅ Drift scan generates CSV
- ✅ Final checklist signed off

---

**[progress] STEP 1/13 — Phase 0 Discovery — 5% complete**

---

*Generated by Payroll Builder Bot*
*Next: Create Phase 0 foundation files*
