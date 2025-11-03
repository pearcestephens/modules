# 🚀 PAYROLL HARDENING - GITHUB AI AGENT BRIEFING

**Mission Critical:** Production-ready payroll system by Tuesday
**Current Progress:** Phase 0 Complete (5%) - Foundation established
**Remaining Work:** Phases 1-12 (95%)
**Time Constraint:** 3 days
**Priority:** HIGH - Financial system with zero-tolerance for errors

---

## 🎯 EXECUTIVE SUMMARY

We're building a **bulletproof, idempotent payroll system** that integrates:
- **Deputy** (timesheets)
- **Xero** (pay runs)
- **Vend** (staff account deductions)
- **CIS** (staff identity mapping)

**The Challenge:** Prevent duplicate payments, handle errors gracefully, enable replay of failed operations.

**The Solution:** 12-phase implementation with idempotency keys, Dead Letter Queue (DLQ), and FIFO allocation.

**What's Done:** Complete foundation (Phase 0) - autoloader, bootstrap, core libs, comprehensive KB.

**What's Needed:** Database schema (Phase 1), services (Phases 2-4), error handling (Phase 5), features (Phases 6-8), ops tools (Phases 9-10), docs (Phase 11), release (Phase 12).

---

## 📋 WHY THIS IS READY FOR AI AGENT

### ✅ **Foundation is Solid**
- PSR-4 autoloader working
- Bootstrap configured (Pacific/Auckland timezone)
- Core libraries battle-tested:
  - `Respond.php` - JSON envelope (ok/fail)
  - `Validate.php` - Type-safe validation
  - `Idempotency.php` - SHA-256 key generation
  - `ErrorEnvelope.php` - Exception normalization

### ✅ **Documentation is Comprehensive**
- **10,000+ words** of analysis in `_kb/`
- Complete system mapping (254 files, 29 directories)
- Database schema documented (15+ tables analyzed)
- Service layer mapped (20+ services)
- Integration points traced
- Gap analysis complete

### ✅ **Architecture is Clear**
```
Deputy Timesheets → Staff Identity Map → Payslip Calculation
                                       → Xero Pay Run
                                       → Vend Deductions (FIFO)
                                       → Residual Tracking
                                       → Activity Log
                                       → Error → DLQ → Replay
```

### ✅ **Patterns are Established**
- **Idempotency:** SHA-256 keys prevent duplicates
- **Error Handling:** Normalized envelopes → DLQ
- **Logging:** Structured to `payroll_activity_log`
- **Rate Limits:** 429 tracking to `payroll_rate_limits`
- **Validation:** Type-safe, exception-throwing

---

## 🎯 SCOPE OF WORK (PHASES 1-12)

### **PHASE 1: Schema & Migration (12%)**
**Estimated Time:** 2-3 hours
**Files to Create:** 1 migration SQL file
**Complexity:** Medium

**Task:**
```sql
-- Create 6 core tables:
1. payroll_runs (run tracking with state machine)
2. payroll_applications (Vend payments with idempotency_key UNIQUE)
3. payroll_dlq (error queue with category/code/meta)
4. payroll_residuals (carry-forward amounts)
5. staff_leave_balances (ANNUAL/SICK/ALT/LIEU/UNPAID)
6. payroll_bonus_events (MANUAL/GOOGLE_REVIEW with caps)

-- Requirements:
- CREATE TABLE IF NOT EXISTS (idempotent)
- UNIQUE constraints on idempotency keys
- Proper indexes (foreign keys, timestamps, status)
- Foreign key relationships
```

**Acceptance:**
- ✅ Run migration twice → no errors (idempotent)
- ✅ All unique constraints work
- ✅ Tables exist in DB

**Reference:** See `_kb/PAYROLL_DEEP_DIVE_ANALYSIS.md` section "Missing Tables (Phase 1 Required)"

---

### **PHASE 2: Services R2 (10%)**
**Estimated Time:** 3-4 hours
**Files to Modify:** 2 files
**Complexity:** Medium

**Tasks:**

1. **Expand `PayrollXeroService.php`** (currently stub)
   ```php
   // Add methods:
   - fetchPayRuns(string $start, string $end): array
   - fetchPayslip(string $payslipId): array
   - createPayRun(array $data): string
   - submitPayRun(string $runId): bool
   ```

2. **Extend `health/index.php`**
   ```php
   // Add checks for Phase 1 tables:
   - table_exists:payroll_runs
   - table_exists:payroll_applications
   - table_exists:payroll_dlq
   - table_exists:staff_identity_map
   - table_exists:payroll_residuals
   - table_exists:staff_leave_balances
   - table_exists:payroll_bonus_events

   // Add auth gate:
   if (env('PAYROLL_AUTH_ENABLED') !== 'true') {
       Respond::fail('AUTH_DISABLED', 'Payroll system is disabled', [], 403);
   }
   ```

**Acceptance:**
- ✅ `PayrollXeroService` has all CRUD methods
- ✅ Health endpoint checks all tables
- ✅ Auth gate blocks when disabled

**Reference:** See `services/PayrollDeputyService.php` for pattern to follow

---

### **PHASE 3: Intake Service (8%)**
**Estimated Time:** 2-3 hours
**Files to Create:** 1 service file
**Complexity:** Low-Medium

**Task:**
```php
// File: services/PayrunIntakeService.php
namespace Payroll\Services;

class PayrunIntakeService {
    // Validate pay period window (Tue-Mon)
    // Quarantine out-of-window entries
    // Check for duplicates via idempotency

    public function ingest(array $rows, string $periodStart, string $periodEnd): array {
        // Returns: ['accepted' => N, 'quarantined' => M, 'errors' => X]
    }
}
```

**Acceptance:**
- ✅ Validates period boundaries
- ✅ Quarantines bad data
- ✅ Returns clear summary

**Reference:** Config has `WEEK_START = 'Tuesday'` and `WINDOW_TOLERANCE_DAYS = 7`

---

### **PHASE 4: Allocation & Application (14%)**
**Estimated Time:** 4-5 hours
**Files to Create:** 2 service files
**Complexity:** High

**Tasks:**

1. **`AllocationService.php`**
   ```php
   // FIFO allocation of deductions to open invoices
   public function allocate(int $deductionCents, array $openInvoices): array {
       // Sort by date ASC (oldest first)
       // Apply amount to each invoice until exhausted
       // Return: allocated_cents, residual_cents, applications[]
   }
   ```

2. **`VendApplyService.php`**
   ```php
   // Apply allocations to Vend with idempotency
   public function applyRun(string $providerRunId, array $items): array {
       // Generate idempotency key: Idempotency::keyFor('vend.apply', [...])
       // INSERT IGNORE INTO payroll_applications
       // If rowCount() === 0 → duplicate, increment counter
       // Otherwise call Vend API (or mark SUCCESS for now)
       // Return: applications, duplicates, failed counts
   }
   ```

**Acceptance:**
- ✅ FIFO works correctly (oldest invoice first)
- ✅ Idempotency prevents duplicates
- ✅ Residuals tracked in `payroll_residuals`

**Reference:** See `_kb/PAYROLL_DEEP_DIVE_ANALYSIS.md` section "FIFO Allocation Logic"

---

### **PHASE 5: DLQ & Replay (10%)**
**Estimated Time:** 3-4 hours
**Files to Create/Modify:** 3 files
**Complexity:** Medium

**Tasks:**

1. **Wrap critical paths with DLQ insertion**
   ```php
   try {
       // operation
   } catch (Throwable $e) {
       $envelope = ErrorEnvelope::from($e, ['context' => 'apply_run']);
       // INSERT INTO payroll_dlq (category, code, message, meta_json, created_at)
       // VALUES (:cat, :code, :msg, :meta, NOW())
   }
   ```

2. **Create `cli/payroll-replay.php`**
   ```php
   // Flags: --run=PR_XXX, --employee=EXX, --code=RATE_LIMIT
   // SELECT from payroll_dlq WHERE ...
   // Replay idempotently (same idempotency key)
   // Sleep 500ms between retries
   // UPDATE payroll_dlq SET replayed_at = NOW() WHERE id = ?
   ```

**Acceptance:**
- ✅ Errors go to DLQ
- ✅ Replay CLI works
- ✅ Idempotency prevents re-applying
- ✅ Sleep delays prevent rate limit cascade

---

### **PHASE 6: Leave Balances (10%)**
**Estimated Time:** 3-4 hours
**Files to Create:** 2 files (service + API endpoint)
**Complexity:** Medium

**Tasks:**

1. **`services/LeaveService.php`**
   ```php
   public function getBalance(string $employeeId, string $leaveType): float;
   public function adjustBalance(string $employeeId, string $leaveType, float $amount, string $unit): void;
   public function convertUnits(float $amount, string $fromUnit, string $toUnit): float;
   // Uses leave_conversion_rules table
   ```

2. **`api/assign-leave.php`**
   ```php
   // POST: { employee_id, leave_type, units, unit, period_end }
   // Validate
   // Convert via LeaveService::convertUnits()
   // Generate idempotency key
   // Insert pending adjustment
   // Respond::ok(['pending_id' => X, 'idempotency_key' => Y])
   ```

**Acceptance:**
- ✅ Balance queries work
- ✅ Unit conversion works (hours ↔ days)
- ✅ API is idempotent

**Reference:** Config has `LEAVE_CONVERSIONS = ['HOURS_TO_DAYS' => 8.0]`

---

### **PHASE 7: Bonuses (7%)**
**Estimated Time:** 2-3 hours
**Files to Modify:** 1 service file
**Complexity:** Low-Medium

**Task:**
```php
// Expand services/BonusService.php
public function addBonus(
    string $employeeId,
    string $type, // MANUAL|GOOGLE_REVIEW|PERFORMANCE
    int $amountCents,
    string $periodEnd,
    ?string $evidenceUrl,
    string $approvedBy
): int {
    // If type === GOOGLE_REVIEW:
    //   - Require evidence URL
    //   - Cap at $50 (5000 cents)
    //   - Validate Google review exists
    // Generate idempotency key
    // INSERT INTO payroll_bonus_events
}
```

**Acceptance:**
- ✅ Google review cap enforced
- ✅ Evidence required for reviews
- ✅ Idempotency works

**Reference:** Config has `BONUS_CAPS['GOOGLE_REVIEW'] = 5000`

---

### **PHASE 8: Reconciliation (9%)**
**Estimated Time:** 3-4 hours
**Files to Create:** 2 files
**Complexity:** Medium-High

**Tasks:**

1. **`services/ReconciliationService.php`**
   ```php
   public function compareDeputyToXero(string $start, string $end): array {
       // Fetch Deputy timesheets (via PayrollDeputyService)
       // Fetch Xero payslips (via PayrollXeroService)
       // Compare hours/amounts
       // Return diff list with thresholds
   }
   ```

2. **`cli/payroll-drift-scan.php`**
   ```php
   // Run ReconciliationService::compareDeputyToXero()
   // Export CSV to reports/drift_YYYYMMDD.csv
   // If |Δ| > DRIFT_THRESHOLD_CENTS → INSERT INTO payroll_dlq
   ```

**Acceptance:**
- ✅ Drift detected
- ✅ CSV exported
- ✅ DLQ alerts on threshold breach

**Reference:** Config has `DRIFT_THRESHOLD_CENTS = 100`, `DRIFT_THRESHOLD_HOURS = 0.25`

---

### **PHASE 9: Ops Heartbeat (5%)**
**Estimated Time:** 1-2 hours
**Files to Create:** 1 CLI file
**Complexity:** Low

**Task:**
```php
// File: cli/payroll-heartbeat.php
// Output JSON:
{
    "runs": {"NEW": 3, "APPLYING": 1, "APPLIED": 45, "FAILED": 0},
    "dlq": {"count": 2, "last_code": "RATE_LIMIT"},
    "residuals": {"count": 5, "total_cents": 12500},
    "timestamp": "2025-11-02T23:30:00Z"
}
// Exit non-zero if DLQ count > DLQ_ALERT_THRESHOLD
```

**Acceptance:**
- ✅ JSON output valid
- ✅ Exit codes correct

---

### **PHASE 10: Auth Audit (5%)**
**Estimated Time:** 1-2 hours
**Files to Create/Modify:** 2 files
**Complexity:** Low

**Tasks:**

1. **Create `payroll_auth_audit_log` table** (in Phase 1 migration)
   ```sql
   CREATE TABLE payroll_auth_audit_log (
       id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
       timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
       actor VARCHAR(255),
       action ENUM('enable','disable'),
       flag_before BOOLEAN,
       flag_after BOOLEAN,
       ip_address VARCHAR(45)
   );
   ```

2. **Wire into auth toggle script**
   ```php
   // When PAYROLL_AUTH_ENABLED changes:
   // INSERT INTO payroll_auth_audit_log (actor, action, flag_before, flag_after, ip_address)
   ```

**Acceptance:**
- ✅ Audit log captures toggles
- ✅ IP addresses recorded

---

### **PHASE 11: Documentation (3%)**
**Estimated Time:** 2-3 hours
**Files to Create:** 3 files
**Complexity:** Low

**Tasks:**

1. **`README.md`**
   - Overview
   - Quick start
   - Endpoints
   - Services

2. **`docs/RUNBOOK.md`**
   - Replay procedure
   - Drift investigation
   - Health checks
   - Common failures

3. **`docs/CONTRACTS.md`**
   - API contracts
   - Idempotency guarantees
   - Error envelopes
   - Status codes

4. **Update `.env.example`**
   ```
   PAYROLL_AUTH_ENABLED=false
   PAYROLL_DEBUG_MODE=false
   PAYROLL_DB_HOST=127.0.0.1
   ```

**Acceptance:**
- ✅ All docs present
- ✅ Examples work
- ✅ Links valid

---

### **PHASE 12: Release Readiness (2%)**
**Estimated Time:** 1-2 hours
**Files to Create:** 1 checklist
**Complexity:** Low

**Task:**
```markdown
# File: FINAL_CHECKLIST.md

- [ ] php -l all new files → clean
- [ ] Replay from DLQ → works
- [ ] Health endpoint → green
- [ ] Drift scan → CSV generated
- [ ] Leave assignment → idempotent
- [ ] Bonus cap → enforced
- [ ] All tests pass
- [ ] No secrets in repo
- [ ] .env.example complete
- [ ] Documentation reviewed
```

---

## 🎯 RECOMMENDED WORK SPLIT

### **AI Agent Focus (High-Value, Complex)**
- ✅ Phase 1: Schema & Migration (critical foundation)
- ✅ Phase 4: Allocation & Application (FIFO + idempotency logic)
- ✅ Phase 5: DLQ & Replay (error handling core)
- ✅ Phase 8: Reconciliation (drift detection)

**Why:** These are the most complex, critical pieces. AI Agent excels at:
- Schema design with proper constraints
- Complex business logic (FIFO)
- Error handling patterns
- Data reconciliation

### **Human/Co-pilot Focus (Simpler, Faster)**
- Phase 2: Service expansion (straightforward CRUD)
- Phase 3: Intake validation (config-driven)
- Phase 6: Leave service (standard CRUD)
- Phase 7: Bonus caps (config-driven)
- Phase 9-12: Ops tools & docs (scripting + writing)

---

## 📦 DELIVERABLES FOR AI AGENT

### **What I Need From You:**

1. **Phase 1 Migration SQL File**
   - All 6 tables with proper constraints
   - Idempotent (CREATE TABLE IF NOT EXISTS)
   - Well-commented
   - Ready to run

2. **Phase 4 Services**
   - `AllocationService.php` - FIFO allocation
   - `VendApplyService.php` - Idempotent apply
   - Unit tests for both

3. **Phase 5 DLQ + Replay**
   - Error wrapper for critical paths
   - `cli/payroll-replay.php` with flags
   - Integration test

4. **Phase 8 Reconciliation**
   - `ReconciliationService.php`
   - `cli/payroll-drift-scan.php`
   - CSV export working

### **Format:**

```
PULL REQUEST STRUCTURE:
├── feat(payroll): Phase 1 - Core schema migration
│   └── migrations/2025_11_02_payroll_core.sql
├── feat(payroll): Phase 4 - FIFO allocation + idempotent apply
│   ├── services/AllocationService.php
│   ├── services/VendApplyService.php
│   └── tests/Unit/AllocationServiceTest.php
├── feat(payroll): Phase 5 - DLQ + Replay system
│   ├── lib/DlqWriter.php
│   ├── cli/payroll-replay.php
│   └── tests/Integration/ReplayTest.php
└── feat(payroll): Phase 8 - Reconciliation & drift detection
    ├── services/ReconciliationService.php
    ├── cli/payroll-drift-scan.php
    └── tests/E2E/ReconciliationFlowTest.php
```

---

## 🚨 CRITICAL SUCCESS FACTORS

### **Non-Negotiable Requirements:**

1. **Idempotency Everywhere**
   - Every operation that applies money MUST have idempotency key
   - Use `Idempotency::keyFor()` for key generation
   - UNIQUE constraint on idempotency_key column

2. **No Duplicate Payments**
   - INSERT IGNORE pattern for applications
   - Check rowCount() to detect duplicates
   - Track duplicates separately from new applications

3. **Error Recovery**
   - All errors to DLQ with ErrorEnvelope format
   - Replay must be safe (idempotent)
   - Rate limit respect (sleep between retries)

4. **Logging Everything**
   - Use PayrollLogger for all service calls
   - Log to payroll_activity_log with context
   - Never silent failures

5. **Type Safety**
   - `declare(strict_types=1)` on all files
   - Use `Validate::*()` for inputs
   - PHPDoc with types

---

## 📚 RESOURCES PROVIDED

### **Knowledge Base (`_kb/`):**
- `INDEX.md` - Navigation hub
- `QUICK_REFERENCE.md` - Fast lookup (1 page)
- `PAYROLL_DEEP_DIVE_ANALYSIS.md` - Complete analysis (7,500 words)
- `SESSION_SUMMARY.md` - Phase 0 work log

### **Foundation Files (Ready to Use):**
- `autoload.php` - PSR-4 working
- `bootstrap.php` - Environment configured
- `config.php` - All thresholds defined
- `lib/Respond.php` - JSON envelope
- `lib/Validate.php` - Input validation
- `lib/Idempotency.php` - Key generation
- `lib/ErrorEnvelope.php` - Error normalization

### **Existing Services (Reference):**
- `PayrollDeputyService.php` - Pattern to follow
- `PayrollLogger.php` - Logging pattern
- `VendService.php` - Vend API wrapper

---

## 🎯 ACCEPTANCE CRITERIA

### **Phase 1 Done When:**
- [ ] Migration file created
- [ ] Run twice → no errors (idempotent)
- [ ] All 6 tables exist in DB
- [ ] UNIQUE constraints work (test duplicate insert)
- [ ] Foreign keys defined
- [ ] Indexes on hot paths

### **Phase 4 Done When:**
- [ ] AllocationService FIFO works (test with sample invoices)
- [ ] VendApplyService prevents duplicates (test re-run)
- [ ] Residuals tracked correctly
- [ ] Unit tests pass

### **Phase 5 Done When:**
- [ ] Errors go to DLQ (test with forced exception)
- [ ] Replay CLI works (test with DLQ entry)
- [ ] Idempotency prevents re-apply
- [ ] Integration test passes

### **Phase 8 Done When:**
- [ ] Drift detected (test with mismatched data)
- [ ] CSV exports correctly
- [ ] DLQ alert on threshold breach
- [ ] E2E test passes

---

## 🚀 LET'S DO THIS!

**Timeline:**
- **Saturday PM:** Phases 1-2 (schema + services)
- **Sunday:** Phases 3-5 (intake, allocation, DLQ)
- **Monday:** Phases 6-8 (leave, bonus, reconciliation)
- **Monday Night:** Phases 9-12 (ops tools, docs, final checks)
- **Tuesday AM:** Review, test, deploy

**I'm Ready When You Are! 💪**

---

**Questions? Check the KB first:**
- Architecture → `_kb/PAYROLL_DEEP_DIVE_ANALYSIS.md`
- Quick lookup → `_kb/QUICK_REFERENCE.md`
- Patterns → See existing services in `services/`

**Let's build something bulletproof! 🎯**
