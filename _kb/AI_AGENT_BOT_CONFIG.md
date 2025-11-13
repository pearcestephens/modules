# 🤖 AI AGENT BOT CONFIGURATION

**Mission:** Payroll Hardening - Production-ready by Tuesday
**Mode:** Autonomous, Test-Driven, Micro-commits
**Quality Bar:** Enterprise financial system (zero-tolerance for errors)

---

## 🎯 AGENT INSTRUCTIONS

### **Your Mission**
You are an **Elite PHP Backend Engineer** specializing in financial systems. Your task is to implement **4 critical phases** of a payroll system with **perfect accuracy** and **bulletproof error handling**.

### **Operating Principles**

1. **Zero Float Arithmetic**
   - All money operations use **integer cents** (not floats)
   - Example: $150.00 = 15000 cents
   - Validation: `Validate::cents()` converts safely

2. **Idempotency Everywhere**
   - Every payment operation gets SHA-256 idempotency key
   - Pattern: `Idempotency::keyFor('namespace', ['key' => 'value'])`
   - Database: UNIQUE constraint on idempotency_key column
   - Application: INSERT IGNORE pattern to detect duplicates

3. **Error Recovery Built-In**
   - All exceptions → ErrorEnvelope → DLQ
   - DLQ structure: `{category, code, message, meta_json, created_at, retried_at, resolved_at}`
   - Replay must be idempotent (same key → no duplicate apply)

4. **Type Safety Required**
   - `declare(strict_types=1)` on every file
   - Type hints on all parameters and returns
   - PHPDoc with @param/@return types

5. **Test-Driven Development**
   - Write unit test first
   - Implement feature
   - Verify test passes
   - Commit both together

---

## 🎯 YOUR 4 PHASES

### **PHASE 1: Database Schema (12% of total)**
**Priority:** BLOCKING - All other phases depend on this

**Deliverables:**
- `migrations/2025_11_02_payroll_core.sql` with 6 tables
- Idempotent DDL (CREATE TABLE IF NOT EXISTS)
- UNIQUE constraints on idempotency keys
- Foreign key relationships
- Proper indexes

**Acceptance Test:**
```bash
# Run migration twice
mysql -u jcepnzzkmj -p'wprKh9Jq63' jcepnzzkmj < migrations/2025_11_02_payroll_core.sql
mysql -u jcepnzzkmj -p'wprKh9Jq63' jcepnzzkmj < migrations/2025_11_02_payroll_core.sql
# Second run should produce no errors (idempotent)

# Test UNIQUE constraint
mysql -u jcepnzzkmj -p'wprKh9Jq63' jcepnzzkmj -e "
INSERT INTO payroll_applications (provider_run_id, employee_id, idempotency_key, amount_cents)
VALUES ('PR_TEST', 'E123', 'abc123', 10000);
INSERT INTO payroll_applications (provider_run_id, employee_id, idempotency_key, amount_cents)
VALUES ('PR_TEST', 'E123', 'abc123', 10000);
"
# Second insert should fail with UNIQUE constraint violation
```

**Files to Create:** 1
**Estimated Time:** 2-3 hours

---

### **PHASE 4: FIFO Allocation + Idempotent Apply (14% of total)**
**Priority:** HIGH - Core financial logic

**Deliverables:**
- `services/AllocationService.php` - FIFO allocation logic
- `services/VendApplyService.php` - Idempotent Vend API wrapper
- `tests/Unit/AllocationServiceTest.php` - Unit tests
- `tests/Unit/VendApplyServiceTest.php` - Unit tests

**Acceptance Tests:**

```php
// Test FIFO allocation
$service = new AllocationService();
$result = $service->allocate(15000, [ // $150.00 deduction
    ['invoice_id' => 'INV-001', 'due_date' => '2025-10-01', 'balance_cents' => 10000],
    ['invoice_id' => 'INV-002', 'due_date' => '2025-10-15', 'balance_cents' => 8000],
]);

// Expected:
// INV-001 gets $100 (paid in full)
// INV-002 gets $50 (partial payment)
// Residual: $0
assert($result['allocated_cents'] === 15000);
assert($result['residual_cents'] === 0);
assert($result['applications'][0]['invoice_id'] === 'INV-001');
assert($result['applications'][0]['allocated_cents'] === 10000);
assert($result['applications'][1]['invoice_id'] === 'INV-002');
assert($result['applications'][1]['allocated_cents'] === 5000);

// Test idempotency
$service = new VendApplyService($pdo);
$result1 = $service->applyRun('PR_123', [
    ['employee_id' => 'E123', 'amount_cents' => 10000, 'applications' => []],
]);
$result2 = $service->applyRun('PR_123', [
    ['employee_id' => 'E123', 'amount_cents' => 10000, 'applications' => []],
]);

assert($result1['applications'] === 1);
assert($result1['duplicates'] === 0);
assert($result2['applications'] === 0); // No new application
assert($result2['duplicates'] === 1);   // Duplicate detected
```

**Files to Create:** 4
**Estimated Time:** 4-5 hours

---

### **PHASE 5: DLQ + Replay (10% of total)**
**Priority:** HIGH - Error recovery critical

**Deliverables:**
- `lib/DlqWriter.php` - DLQ insertion helper
- `cli/payroll-replay.php` - Replay CLI with filters
- `tests/Integration/DlqReplayTest.php` - Integration test

**Acceptance Tests:**

```php
// Test DLQ insertion
$dlqWriter = new DlqWriter($pdo);
try {
    throw new RuntimeException("Test error");
} catch (Throwable $e) {
    $dlqId = $dlqWriter->write($e, ['operation' => 'test']);
}

$stmt = $pdo->query("SELECT * FROM payroll_dlq WHERE id = {$dlqId}");
$row = $stmt->fetch(PDO::FETCH_ASSOC);
assert($row['category'] === 'INTERNAL');
assert($row['code'] === 'RUNTIME_ERROR');
assert($row['message'] === 'Test error');

// Test replay CLI
exec('php cli/payroll-replay.php --code=TEST_ERROR 2>&1', $output, $exitCode);
assert($exitCode === 0);
assert(strpos(implode("\n", $output), 'Replayed: 1') !== false);

// Test idempotent replay (should not duplicate)
exec('php cli/payroll-replay.php --code=TEST_ERROR 2>&1', $output2, $exitCode2);
assert($exitCode2 === 0);
assert(strpos(implode("\n", $output2), 'duplicates') !== false);
```

**Files to Create:** 3
**Estimated Time:** 3-4 hours

---

### **PHASE 8: Reconciliation + Drift (9% of total)**
**Priority:** MEDIUM - Audit tool

**Deliverables:**
- `services/ReconciliationService.php` - Drift detection
- `cli/payroll-drift-scan.php` - CSV export + DLQ alerts
- `tests/E2E/ReconciliationFlowTest.php` - E2E test

**Acceptance Tests:**

```php
// Test drift detection
$deputyService = $this->createMock(PayrollDeputyService::class);
$deputyService->method('fetchTimesheets')->willReturn([
    ['employee_id' => 'E123', 'TotalHours' => 40.0, 'GrossAmount' => 100000],
]);

$xeroService = $this->createMock(PayrollXeroService::class);
$xeroService->method('fetchPayslips')->willReturn([
    ['employee_id' => 'E123', 'TotalHours' => 39.5, 'GrossAmount' => 99500],
]);

$reconciliationService = new ReconciliationService($deputyService, $xeroService);
$result = $reconciliationService->compareDeputyToXero('2025-10-28', '2025-11-03');

assert($result['summary']['total_drifts'] === 1);
assert($result['drifts'][0]['drift_hours'] === 0.5);
assert($result['drifts'][0]['drift_cents'] === 500);
assert($result['drifts'][0]['severity'] === 'MEDIUM');

// Test CSV export
exec('php cli/payroll-drift-scan.php --start=2025-10-28 --end=2025-11-03 2>&1', $output, $exitCode);
assert($exitCode === 0);
assert(file_exists('reports/drift_*.csv')); // Wildcard check

// Test DLQ alert on high drift
$stmt = $pdo->query("SELECT COUNT(*) FROM payroll_dlq WHERE code LIKE '%DRIFT%'");
$count = $stmt->fetchColumn();
assert($count > 0); // At least one drift alert
```

**Files to Create:** 3
**Estimated Time:** 3-4 hours

---

## 🎯 COMMIT STRATEGY

### **Micro-Commits (≤ 2 files, ≤ 20KB each)**

**Phase 1:**
```bash
git add migrations/2025_11_02_payroll_core.sql
git commit -m "feat(payroll): Phase 1 - Core schema with idempotency"
```

**Phase 4:**
```bash
git add services/AllocationService.php tests/Unit/AllocationServiceTest.php
git commit -m "feat(payroll): Phase 4.1 - FIFO allocation service"

git add services/VendApplyService.php tests/Unit/VendApplyServiceTest.php
git commit -m "feat(payroll): Phase 4.2 - Idempotent Vend apply service"
```

**Phase 5:**
```bash
git add lib/DlqWriter.php
git commit -m "feat(payroll): Phase 5.1 - DLQ writer helper"

git add cli/payroll-replay.php tests/Integration/DlqReplayTest.php
git commit -m "feat(payroll): Phase 5.2 - DLQ replay CLI with tests"
```

**Phase 8:**
```bash
git add services/ReconciliationService.php
git commit -m "feat(payroll): Phase 8.1 - Reconciliation service"

git add cli/payroll-drift-scan.php tests/E2E/ReconciliationFlowTest.php
git commit -m "feat(payroll): Phase 8.2 - Drift scanner CLI with E2E test"
```

---

## 🎯 CODE PATTERNS TO FOLLOW

### **Pattern 1: Service Structure**
```php
<?php
declare(strict_types=1);

namespace Payroll\Services;

use Payroll\Lib\Validate;
use PDO;

/**
 * Service Description
 *
 * @package Payroll\Services
 */
class ExampleService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Method description
     *
     * @param string $param Description
     * @return array ['key' => 'value']
     * @throws \InvalidArgumentException When validation fails
     */
    public function methodName(string $param): array
    {
        $param = Validate::employeeId($param);

        // Implementation

        return ['result' => 'value'];
    }
}
```

### **Pattern 2: Idempotency Check**
```php
use Payroll\Lib\Idempotency;

$idempotencyKey = Idempotency::keyFor('operation.name', [
    'param1' => $value1,
    'param2' => $value2,
]);

$stmt = $pdo->prepare("
    INSERT IGNORE INTO table_name (idempotency_key, data)
    VALUES (?, ?)
");
$stmt->execute([$idempotencyKey, $data]);

if ($stmt->rowCount() === 0) {
    // Duplicate detected - operation already performed
    return ['status' => 'DUPLICATE'];
}

// New operation - proceed
return ['status' => 'SUCCESS'];
```

### **Pattern 3: Error Handling**
```php
use Payroll\Lib\ErrorEnvelope;
use Payroll\Lib\DlqWriter;

$dlqWriter = new DlqWriter($pdo);

try {
    // Critical operation
    $result = $this->performOperation();
} catch (\Throwable $e) {
    // Write to DLQ for replay
    $dlqWriter->write($e, [
        'operation' => 'operation_name',
        'params' => ['key' => 'value'],
    ]);

    // Re-throw or return error envelope
    throw $e;
}
```

### **Pattern 4: CLI Structure**
```php
#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * CLI Script Description
 *
 * Usage:
 *   php script.php --flag=value
 */

require_once __DIR__ . '/../bootstrap.php';

// Parse arguments
$options = getopt('', ['flag:']);

if (!isset($options['flag'])) {
    echo "Usage: php script.php --flag=value\n";
    exit(1);
}

// Connect to database
$pdo = new PDO(
    "mysql:host=" . env('DB_HOST') . ";dbname=" . env('DB_NAME') . ";charset=utf8mb4",
    env('DB_USER'),
    env('DB_PASS'),
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

try {
    // Main logic
    echo "Processing...\n";

    exit(0);
} catch (\Throwable $e) {
    echo "ERROR: {$e->getMessage()}\n";
    exit(1);
}
```

---

## 🎯 TESTING REQUIREMENTS

### **Unit Tests (Fast, Isolated)**
```php
<?php
declare(strict_types=1);

namespace Payroll\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Payroll\Services\AllocationService;

class AllocationServiceTest extends TestCase
{
    public function testFifoAllocationFullPayment(): void
    {
        $service = new AllocationService();

        $result = $service->allocate(10000, [
            ['invoice_id' => 'INV-001', 'due_date' => '2025-10-01', 'balance_cents' => 10000],
        ]);

        $this->assertEquals(10000, $result['allocated_cents']);
        $this->assertEquals(0, $result['residual_cents']);
        $this->assertCount(1, $result['applications']);
    }

    public function testFifoAllocationPartialPayment(): void
    {
        // Test partial allocation
    }

    public function testFifoAllocationMultipleInvoices(): void
    {
        // Test FIFO ordering
    }
}
```

### **Integration Tests (Database)**
```php
<?php
declare(strict_types=1);

namespace Payroll\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Payroll\Lib\DlqWriter;

class DlqReplayTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        // Use test database
        $this->pdo = new PDO(
            'mysql:host=127.0.0.1;dbname=jcepnzzkmj_test;charset=utf8mb4',
            'jcepnzzkmj',
            'wprKh9Jq63'
        );

        // Truncate tables
        $this->pdo->exec('TRUNCATE TABLE payroll_dlq');
    }

    public function testDlqInsertionAndRetrieval(): void
    {
        $dlqWriter = new DlqWriter($this->pdo);

        $dlqId = $dlqWriter->write(
            new \RuntimeException('Test error'),
            ['operation' => 'test']
        );

        $this->assertGreaterThan(0, $dlqId);

        // Verify insertion
        $stmt = $this->pdo->query("SELECT * FROM payroll_dlq WHERE id = {$dlqId}");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertEquals('INTERNAL', $row['category']);
        $this->assertEquals('Test error', $row['message']);
    }
}
```

---

## 🎯 QUALITY GATES

### **Pre-Commit Checks**
```bash
# Syntax check
find . -name "*.php" -exec php -l {} \; | grep -v "No syntax errors"

# Type check (if psalm/phpstan installed)
vendor/bin/psalm --show-info=false

# Tests
vendor/bin/phpunit tests/

# Style check
vendor/bin/phpcs --standard=PSR12 services/ lib/ cli/
```

### **Pre-PR Checks**
- [ ] All unit tests pass
- [ ] All integration tests pass
- [ ] No syntax errors
- [ ] PHPDoc complete
- [ ] Type hints on all methods
- [ ] No float arithmetic for money
- [ ] Idempotency keys present
- [ ] Error handling with DLQ
- [ ] Commit messages follow convention

---

## 🎯 DELIVERY FORMAT

### **Pull Request Structure**
```
Title: feat(payroll): Phase X - Short description

Body:
## What
- Describe what was built

## Why
- Explain business value

## How
- Technical approach

## Testing
- Unit tests: X passed
- Integration tests: Y passed
- Manual testing: Z scenarios

## Checklist
- [x] Syntax clean
- [x] Tests pass
- [x] Idempotency implemented
- [x] Error handling with DLQ
- [x] Type hints complete
- [x] PHPDoc complete
```

---

## 🚀 SUCCESS CRITERIA

**Phase 1 Complete When:**
- ✅ Migration runs idempotently (twice without error)
- ✅ All 6 tables exist
- ✅ UNIQUE constraints work (test duplicate insert fails)
- ✅ Foreign keys defined

**Phase 4 Complete When:**
- ✅ FIFO allocates oldest invoice first (unit test)
- ✅ Residuals calculated correctly (unit test)
- ✅ Idempotency prevents duplicates (integration test)
- ✅ All tests pass

**Phase 5 Complete When:**
- ✅ Errors go to DLQ (integration test)
- ✅ Replay CLI works (manual test)
- ✅ Idempotent replay (no duplicate apply)
- ✅ All tests pass

**Phase 8 Complete When:**
- ✅ Drift detected (unit test with mocks)
- ✅ CSV exports (integration test)
- ✅ DLQ alert on high drift (integration test)
- ✅ All tests pass

---

## 🎯 TIMELINE

**Saturday Evening:**
- Phase 1 complete (2-3 hours)

**Sunday:**
- Phase 4 complete (4-5 hours)
- Phase 5 complete (3-4 hours)

**Monday:**
- Phase 8 complete (3-4 hours)
- Final testing & PR review

**Total Estimate:** 12-16 hours

---

## 🚨 CRITICAL REMINDERS

1. **Money = Integer Cents** (never floats)
2. **Idempotency Keys = SHA-256** (use `Idempotency::keyFor()`)
3. **Errors → DLQ** (use `DlqWriter::write()`)
4. **FIFO = Oldest First** (sort by due_date ASC)
5. **Tests = Required** (no PR without tests)
6. **Type Safety = Mandatory** (`declare(strict_types=1)`)

---

**You've Got This! Let's Build Something Bulletproof! 🚀**
