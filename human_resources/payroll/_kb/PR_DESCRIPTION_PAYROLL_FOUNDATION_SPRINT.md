# PR #2: Payroll Foundation Sprint - Phases 1-6 Complete System

## 🎯 Objective
Build complete payroll foundation from scratch: schema + services + intake + payment processing + error handling. Enables CIS to process payroll runs end-to-end with Xero integration.

## 📊 Current Status
- **Completion:** 5% (Phase 0 foundation complete)
- **Existing:** 254 PHP files (legacy quality unknown)
- **Working:** PayrollDeputyService (production-ready), Xero service (stub)
- **Missing:** Schema, core services, business logic, error handling

## 🎯 Scope: Phases 1-6 (Core Foundation)

### Phase 1: Baseline Rules & Conventions (3-4 hours)
**Goal:** Establish payroll calculation rules and data validation standards

**Tasks:**
- [ ] Create `lib/PayrollRules.php` - Calculation rules (overtime, leave, tax)
- [ ] Create `lib/PayrollConventions.php` - Data standards (pay periods, rounding)
- [ ] Create `lib/PayrollValidator.php` - Input validation (rates, hours, dates)
- [ ] Unit tests for all rules (20+ test cases)

**Files to Create:**
```
lib/PayrollRules.php               # Calculation rules
lib/PayrollConventions.php         # Data standards
lib/PayrollValidator.php           # Validation logic
tests/unit/PayrollRulesTest.php
tests/unit/PayrollConventionsTest.php
tests/unit/PayrollValidatorTest.php
```

**Example Rules:**
```php
class PayrollRules {
    // Overtime calculation
    public static function calculateOvertime(float $hours, float $baseRate): float {
        if ($hours <= 40) return 0;
        return ($hours - 40) * $baseRate * 1.5;
    }

    // Leave accrual (NZ standard: 4 weeks/year)
    public static function calculateLeaveAccrual(float $hoursWorked): float {
        return $hoursWorked * (4.0 / 52.0);
    }

    // Tax calculation (NZ PAYE brackets)
    public static function calculatePAYE(float $grossPay): float {
        // Implement NZ tax brackets
    }
}
```

**Acceptance Criteria:**
- ✅ All rules tested with edge cases
- ✅ Validation catches invalid inputs
- ✅ Conventions documented in code comments

---

### Phase 2: Schema & Ledger (4-5 hours)
**Goal:** Create complete database schema for payroll system

**Tables to Create:**

#### Core Payroll Tables
```sql
-- Payroll runs (header)
CREATE TABLE payroll_runs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    run_period_start DATE NOT NULL,
    run_period_end DATE NOT NULL,
    status ENUM('draft','processing','approved','paid','closed') DEFAULT 'draft',
    total_gross DECIMAL(10,2) DEFAULT 0.00,
    total_net DECIMAL(10,2) DEFAULT 0.00,
    total_tax DECIMAL(10,2) DEFAULT 0.00,
    created_by INT,
    approved_by INT,
    approved_at DATETIME,
    xero_batch_id VARCHAR(36),
    idempotency_key VARCHAR(64) UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_period (run_period_start, run_period_end),
    INDEX idx_status (status)
);

-- Payroll line items (detail)
CREATE TABLE payroll_line_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    run_id INT NOT NULL,
    employee_id INT NOT NULL,
    hours_regular DECIMAL(6,2) DEFAULT 0.00,
    hours_overtime DECIMAL(6,2) DEFAULT 0.00,
    hours_leave DECIMAL(6,2) DEFAULT 0.00,
    rate_regular DECIMAL(8,2) NOT NULL,
    rate_overtime DECIMAL(8,2),
    gross_pay DECIMAL(10,2) NOT NULL,
    tax_amount DECIMAL(10,2) NOT NULL,
    net_pay DECIMAL(10,2) NOT NULL,
    status ENUM('pending','approved','paid','cancelled') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (run_id) REFERENCES payroll_runs(id),
    FOREIGN KEY (employee_id) REFERENCES users(id),
    INDEX idx_run (run_id),
    INDEX idx_employee (employee_id)
);

-- Leave balances tracking
CREATE TABLE payroll_leave_balances (
    id INT PRIMARY KEY AUTO_INCREMENT,
    employee_id INT NOT NULL,
    balance_type ENUM('annual','sick','bereavement','other') NOT NULL,
    balance_hours DECIMAL(8,2) NOT NULL DEFAULT 0.00,
    accrued_hours DECIMAL(8,2) NOT NULL DEFAULT 0.00,
    used_hours DECIMAL(8,2) NOT NULL DEFAULT 0.00,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES users(id),
    UNIQUE KEY uk_employee_type (employee_id, balance_type)
);

-- Bonus/allowance tracking
CREATE TABLE payroll_bonuses (
    id INT PRIMARY KEY AUTO_INCREMENT,
    employee_id INT NOT NULL,
    bonus_type ENUM('performance','retention','referral','other') NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    reason TEXT,
    approved_by INT,
    approved_at DATETIME,
    paid_in_run_id INT,
    status ENUM('pending','approved','paid','cancelled') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES users(id),
    FOREIGN KEY (paid_in_run_id) REFERENCES payroll_runs(id)
);

-- Payroll adjustments (corrections)
CREATE TABLE payroll_adjustments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    line_item_id INT NOT NULL,
    adjustment_type ENUM('correction','backpay','deduction','other') NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    reason TEXT NOT NULL,
    created_by INT NOT NULL,
    applied_in_run_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (line_item_id) REFERENCES payroll_line_items(id),
    FOREIGN KEY (applied_in_run_id) REFERENCES payroll_runs(id)
);

-- Xero sync tracking
CREATE TABLE payroll_xero_sync (
    id INT PRIMARY KEY AUTO_INCREMENT,
    run_id INT NOT NULL,
    xero_employee_id VARCHAR(36),
    xero_pay_run_id VARCHAR(36),
    sync_status ENUM('pending','success','failed') DEFAULT 'pending',
    sync_attempt INT DEFAULT 0,
    last_error TEXT,
    synced_at DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (run_id) REFERENCES payroll_runs(id),
    INDEX idx_run (run_id)
);

-- Audit log
CREATE TABLE payroll_audit_log (
    id INT PRIMARY KEY AUTO_INCREMENT,
    entity_type VARCHAR(50) NOT NULL,
    entity_id INT NOT NULL,
    action ENUM('create','update','delete','approve','cancel') NOT NULL,
    user_id INT NOT NULL,
    changes JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_entity (entity_type, entity_id),
    INDEX idx_user (user_id)
);

-- Error queue (DLQ)
CREATE TABLE payroll_error_queue (
    id INT PRIMARY KEY AUTO_INCREMENT,
    error_type VARCHAR(100) NOT NULL,
    entity_type VARCHAR(50),
    entity_id INT,
    error_message TEXT NOT NULL,
    error_context JSON,
    retry_count INT DEFAULT 0,
    status ENUM('pending','resolved','abandoned') DEFAULT 'pending',
    resolved_at DATETIME,
    resolved_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_entity (entity_type, entity_id)
);

-- Idempotency tracking
CREATE TABLE payroll_idempotency_keys (
    key_hash VARCHAR(64) PRIMARY KEY,
    operation VARCHAR(100) NOT NULL,
    result JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_created (created_at)
);
```

**Migration Files:**
```
migrations/
├── 001_create_payroll_runs.sql
├── 002_create_payroll_line_items.sql
├── 003_create_payroll_leave_balances.sql
├── 004_create_payroll_bonuses.sql
├── 005_create_payroll_adjustments.sql
├── 006_create_payroll_xero_sync.sql
├── 007_create_payroll_audit_log.sql
├── 008_create_payroll_error_queue.sql
├── 009_create_payroll_idempotency_keys.sql
└── 999_rollback_all.sql
```

**Acceptance Criteria:**
- ✅ All tables created with proper indexes
- ✅ Foreign keys enforce referential integrity
- ✅ Rollback script works (tested)
- ✅ Schema documented in `docs/DATABASE_SCHEMA.md`

---

### Phase 3: Core Services & Health (3-4 hours)
**Goal:** Build service layer for business logic

**Services to Create:**

#### 1. PayrollService (Main orchestrator)
```php
class PayrollService {
    public function createPayrollRun(string $periodStart, string $periodEnd): array;
    public function addLineItem(int $runId, int $employeeId, array $data): array;
    public function calculateTotals(int $runId): array;
    public function approveRun(int $runId, int $approverId): bool;
    public function cancelRun(int $runId, string $reason): bool;
}
```

#### 2. TimesheetService (Intake from Deputy)
```php
class TimesheetService {
    public function fetchTimesheets(string $periodStart, string $periodEnd): array;
    public function validateTimesheet(array $timesheet): bool;
    public function importTimesheet(array $timesheet): int;
}
```

#### 3. LeaveService (Leave balance management)
```php
class LeaveService {
    public function getBalance(int $employeeId, string $type): float;
    public function accrueLeave(int $employeeId, float $hours): void;
    public function deductLeave(int $employeeId, string $type, float $hours): void;
}
```

#### 4. PaymentService (Xero integration)
```php
class PaymentService {
    public function syncToXero(int $runId): array;
    public function verifyXeroPayment(string $xeroPayRunId): bool;
    public function reconcilePayments(int $runId): array;
}
```

**Files to Create:**
```
services/PayrollService.php
services/TimesheetService.php
services/LeaveService.php
services/PaymentService.php
tests/unit/PayrollServiceTest.php
tests/unit/TimesheetServiceTest.php
tests/unit/LeaveServiceTest.php
tests/unit/PaymentServiceTest.php
```

**Health Endpoint Extension:**
```php
// health/index.php
$health['payroll'] = [
    'database' => checkPayrollTables(),
    'services' => checkServices(),
    'xero_connection' => checkXeroAPI(),
    'deputy_connection' => checkDeputyAPI(),
];
```

**Acceptance Criteria:**
- ✅ All services instantiate without errors
- ✅ Unit tests for each service method
- ✅ Health endpoint returns 200 with payroll status

---

### Phase 4: Intake & Windowing (4-6 hours)
**Goal:** Import timesheets from Deputy and calculate pay periods

**Tasks:**
- [ ] Build Deputy timesheet importer
- [ ] Implement pay period windowing (weekly, fortnightly, monthly)
- [ ] Add validation (hours, rates, employee matching)
- [ ] Handle edge cases (public holidays, partial periods, new starters)

**Files to Create:**
```
lib/PayrollIntake.php              # Main intake orchestrator
lib/PayPeriodCalculator.php        # Window calculations
lib/DeputyImporter.php             # Deputy API integration
tests/unit/PayrollIntakeTest.php
tests/unit/PayPeriodCalculatorTest.php
tests/integration/DeputyImportTest.php
```

**Pay Period Logic:**
```php
class PayPeriodCalculator {
    public static function getPayPeriod(string $frequency, string $referenceDate): array {
        // weekly: Monday-Sunday
        // fortnightly: Every 2 weeks from reference
        // monthly: 1st-last day of month
        return [
            'start' => '2025-11-01',
            'end' => '2025-11-07',
            'frequency' => 'weekly'
        ];
    }

    public static function isPublicHoliday(string $date): bool;
    public static function getWorkingHours(string $start, string $end): float;
}
```

**Deputy Integration:**
```php
class DeputyImporter {
    public function fetchTimesheets(string $start, string $end): array;
    public function matchEmployee(array $deputyData): ?int; // Match to users.id
    public function validateHours(array $timesheet): bool;
    public function importToPayroll(array $timesheets): int; // Returns count
}
```

**Acceptance Criteria:**
- ✅ Can import timesheets from Deputy sandbox
- ✅ Pay periods calculated correctly (weekly, fortnightly, monthly)
- ✅ Employee matching works (Deputy ID → CIS user ID)
- ✅ Invalid timesheets rejected with clear errors
- ✅ Public holidays identified and flagged

---

### Phase 5: Account-Payment Application (5-7 hours)
**Goal:** Calculate gross/net pay and sync to Xero

**Tasks:**
- [ ] Implement pay calculation (regular + overtime + leave)
- [ ] Calculate PAYE tax (NZ tax brackets)
- [ ] Calculate KiwiSaver deductions (if applicable)
- [ ] Integrate with Xero Payroll API
- [ ] Handle payment approval workflow

**Files to Create:**
```
lib/PayCalculator.php              # Main calculation engine
lib/TaxCalculator.php              # NZ PAYE calculations
lib/XeroPayrollClient.php          # Xero API integration
tests/unit/PayCalculatorTest.php
tests/unit/TaxCalculatorTest.php
tests/integration/XeroPayrollTest.php
```

**Pay Calculation:**
```php
class PayCalculator {
    public function calculateGrossPay(array $lineItem): float {
        $regular = $lineItem['hours_regular'] * $lineItem['rate_regular'];
        $overtime = $lineItem['hours_overtime'] * $lineItem['rate_overtime'];
        $leave = $lineItem['hours_leave'] * $lineItem['rate_regular'];
        return $regular + $overtime + $leave;
    }

    public function calculateNetPay(float $grossPay, float $taxRate): float {
        $tax = TaxCalculator::calculatePAYE($grossPay);
        $kiwisaver = $grossPay * 0.03; // 3% default
        return $grossPay - $tax - $kiwisaver;
    }
}
```

**NZ Tax Brackets (2025):**
```php
class TaxCalculator {
    private const BRACKETS = [
        ['min' => 0,     'max' => 14000,  'rate' => 0.105], // 10.5%
        ['min' => 14000, 'max' => 48000,  'rate' => 0.175], // 17.5%
        ['min' => 48000, 'max' => 70000,  'rate' => 0.30],  // 30%
        ['min' => 70000, 'max' => 180000, 'rate' => 0.33],  // 33%
        ['min' => 180000,'max' => PHP_INT_MAX, 'rate' => 0.39], // 39%
    ];

    public static function calculatePAYE(float $annualGross): float {
        // Calculate tax based on brackets
    }
}
```

**Xero Integration:**
```php
class XeroPayrollClient {
    public function createPayRun(array $payrollRun): string; // Returns Xero pay run ID
    public function addEmployee(array $employee): string; // Returns Xero employee ID
    public function getPayRunStatus(string $payRunId): array;
    public function finalizePayRun(string $payRunId): bool;
}
```

**Acceptance Criteria:**
- ✅ Gross pay calculated correctly (regular + OT + leave)
- ✅ PAYE tax calculated per NZ brackets
- ✅ Net pay = gross - tax - deductions
- ✅ Can create pay run in Xero sandbox
- ✅ Approval workflow works (draft → approved → paid)
- ✅ Idempotency prevents duplicate payments

---

### Phase 6: Error Envelopes & DLQ (2-3 hours)
**Goal:** Robust error handling and dead letter queue

**Tasks:**
- [ ] Create exception hierarchy
- [ ] Implement error envelope pattern
- [ ] Build DLQ for failed operations
- [ ] Add retry logic with exponential backoff
- [ ] Create error dashboard

**Files to Create:**
```
lib/exceptions/PayrollException.php
lib/exceptions/CalculationException.php
lib/exceptions/ValidationException.php
lib/exceptions/XeroSyncException.php
lib/ErrorHandler.php
lib/DeadLetterQueue.php
api/admin/error-queue.php          # View errors
api/admin/retry-error.php          # Retry failed operation
tests/unit/ErrorHandlerTest.php
```

**Exception Hierarchy:**
```php
class PayrollException extends Exception {
    protected $context = [];

    public function __construct(string $message, array $context = []) {
        parent::__construct($message);
        $this->context = $context;
    }

    public function toErrorEnvelope(): array {
        return [
            'error_type' => get_class($this),
            'message' => $this->getMessage(),
            'context' => $this->context,
            'trace' => $this->getTraceAsString()
        ];
    }
}

class CalculationException extends PayrollException {}
class ValidationException extends PayrollException {}
class XeroSyncException extends PayrollException {}
```

**DLQ Implementation:**
```php
class DeadLetterQueue {
    public function addError(Exception $e, string $entityType, int $entityId): int;
    public function getErrors(string $status = 'pending'): array;
    public function retry(int $errorId): bool;
    public function abandon(int $errorId, string $reason): bool;
}
```

**Error Dashboard:**
```php
// api/admin/error-queue.php
$errors = $dlq->getErrors('pending');
echo json_encode([
    'success' => true,
    'errors' => $errors,
    'summary' => [
        'pending' => count($errors),
        'resolved' => $dlq->count('resolved'),
        'abandoned' => $dlq->count('abandoned')
    ]
]);
```

**Acceptance Criteria:**
- ✅ All exceptions inherit from PayrollException
- ✅ Errors logged to payroll_error_queue table
- ✅ Admin can view pending errors
- ✅ Admin can retry failed operations
- ✅ Retry logic uses exponential backoff
- ✅ Error context includes full operation details

---

## 📂 Complete File List

### New Files to Create (50+ files)

**Lib (Business Logic):**
```
lib/PayrollRules.php
lib/PayrollConventions.php
lib/PayrollValidator.php
lib/PayrollIntake.php
lib/PayPeriodCalculator.php
lib/DeputyImporter.php
lib/PayCalculator.php
lib/TaxCalculator.php
lib/XeroPayrollClient.php
lib/ErrorHandler.php
lib/DeadLetterQueue.php
lib/exceptions/PayrollException.php
lib/exceptions/CalculationException.php
lib/exceptions/ValidationException.php
lib/exceptions/XeroSyncException.php
```

**Services:**
```
services/PayrollService.php
services/TimesheetService.php
services/LeaveService.php
services/PaymentService.php
```

**Migrations:**
```
migrations/001_create_payroll_runs.sql
migrations/002_create_payroll_line_items.sql
migrations/003_create_payroll_leave_balances.sql
migrations/004_create_payroll_bonuses.sql
migrations/005_create_payroll_adjustments.sql
migrations/006_create_payroll_xero_sync.sql
migrations/007_create_payroll_audit_log.sql
migrations/008_create_payroll_error_queue.sql
migrations/009_create_payroll_idempotency_keys.sql
migrations/999_rollback_all.sql
```

**API Endpoints:**
```
api/admin/error-queue.php
api/admin/retry-error.php
api/payroll/create-run.php
api/payroll/approve-run.php
api/payroll/sync-to-xero.php
```

**Tests (30+ test files):**
```
tests/unit/PayrollRulesTest.php
tests/unit/PayrollConventionsTest.php
tests/unit/PayrollValidatorTest.php
tests/unit/PayrollServiceTest.php
tests/unit/TimesheetServiceTest.php
tests/unit/LeaveServiceTest.php
tests/unit/PaymentServiceTest.php
tests/unit/PayrollIntakeTest.php
tests/unit/PayPeriodCalculatorTest.php
tests/unit/PayCalculatorTest.php
tests/unit/TaxCalculatorTest.php
tests/unit/ErrorHandlerTest.php
tests/integration/DeputyImportTest.php
tests/integration/XeroPayrollTest.php
```

**Documentation:**
```
docs/DATABASE_SCHEMA.md
docs/API_REFERENCE.md
docs/CALCULATION_RULES.md
docs/XERO_INTEGRATION.md
```

### Files to Modify
```
health/index.php                    # Add payroll health checks
config.php                          # Add payroll config section
```

---

## ✅ Definition of Done

**This PR is complete when:**
- ✅ All 9 database tables created (schema + migrations)
- ✅ All 4 core services implemented (Payroll, Timesheet, Leave, Payment)
- ✅ Intake logic working (Deputy timesheets → CIS payroll)
- ✅ Pay calculation working (gross → net with tax)
- ✅ Xero integration working (create pay run in sandbox)
- ✅ Error handling complete (DLQ + retry logic)
- ✅ All unit tests pass (30+ tests)
- ✅ Integration tests pass (Deputy + Xero)
- ✅ Health endpoint returns payroll status
- ✅ Documentation complete (schema + API + rules)
- ✅ Can process complete payroll run end-to-end

---

## 🎯 Success Metrics

**Before This PR:**
- Payroll completion: 5%
- Can process payroll: ❌
- Xero integration: Stub only ❌
- Error handling: Basic ❌

**After This PR:**
- Payroll completion: 50% (Phases 1-6 complete)
- Can process payroll: ✅ (end-to-end working)
- Xero integration: ✅ (creates pay runs)
- Error handling: ✅ (DLQ + retry)

---

## 🚧 Known Limitations (Deferred to Phases 7-12)

**Not included in this PR:**
- Leave balance calculation (Phase 7)
- Bonus/allowance processing (Phase 8)
- Reconciliation reports (Phase 9)
- Advanced health checks (Phase 10)
- Auth audit logging (Phase 11)
- Full documentation (Phase 12)

**These are documented for next sprint but not blocking basic payroll processing.**

---

## 📚 Reference Documents

- **Complete Briefing:** `/modules/human_resources/payroll/_kb/GITHUB_AI_AGENT_BRIEFING.md`
- **Analysis:** `/modules/human_resources/payroll/_kb/PAYROLL_DEEP_DIVE_ANALYSIS.md`
- **Time Estimate:** `/modules/human_resources/payroll/_kb/PAYROLL_VS_CONSIGNMENTS_COMPLETE_COMPARISON.md`
- **Quick Reference:** `/modules/human_resources/payroll/_kb/QUICK_REFERENCE.md`

---

## ⏱️ Estimated Time

**Total Work:** 20-24 hours with AI Agent assistance
- Phase 1: 3-4 hours (rules + conventions)
- Phase 2: 4-5 hours (schema + migrations)
- Phase 3: 3-4 hours (services + health)
- Phase 4: 4-6 hours (intake + windowing)
- Phase 5: 5-7 hours (payments + Xero)
- Phase 6: 2-3 hours (error handling)

**Target Completion:** Monday evening (Nov 4, 2025)

---

## 🤖 AI Agent Instructions

**Approach:**
1. **Phase 1 first** (rules + conventions) - Foundation for everything
2. **Phase 2 next** (schema) - Creates data layer
3. **Phase 3** (services) - Business logic layer
4. **Phase 4** (intake) - Gets data into system
5. **Phase 5** (payments) - Outputs to Xero
6. **Phase 6** (errors) - Makes it production-ready

**DO NOT skip phases** - they build on each other.

**Patterns to Follow:**
- Use existing `lib/Respond.php`, `lib/Validate.php`, `lib/Idempotency.php`
- Follow PSR-12, strict types, PHPDoc comments
- Use prepared statements (no raw SQL)
- Log with correlation IDs
- Test everything (TDD preferred)

**Testing Strategy:**
- Unit tests for all lib/ files
- Integration tests for Deputy + Xero
- End-to-end test: timesheet import → pay run → Xero sync

**Database:**
- Host: 127.0.0.1
- Database: jcepnzzkmj
- User: jcepnzzkmj
- Password: (from .env)

**External APIs:**
- Deputy: Use existing `PayrollDeputyService.php` as reference
- Xero: Use sandbox (credentials in .env)

**Questions?** Tag @pearcestephens in PR comments.

---

**Ready to process payroll by Monday evening! 💰**
