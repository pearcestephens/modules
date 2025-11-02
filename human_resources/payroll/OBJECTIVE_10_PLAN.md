# OBJECTIVE 10: Comprehensive Test Coverage

**Status:** 🔄 IN PROGRESS
**Estimated Time:** 90 minutes
**Priority:** HIGH (Quality Assurance, Regression Prevention)

---

## Objective

Ensure all controllers and services have comprehensive unit and integration test coverage to:
- Prevent regressions when making changes
- Document expected behavior
- Enable confident refactoring
- Validate business logic
- Meet quality standards (target: 80%+ coverage)

---

## Current Test Inventory

### Tests Created in Previous Objectives:

**Objective 1 (Controller Helpers):** 51 tests
- BaseControllerTest.php - 51 tests
- Coverage: requireAuth(), requireAdmin(), requirePermission(), json(), error()

**Objective 2 (Validator):** 28 tests
- ValidatorTest.php - 28 tests
- Coverage: All validation rules (required, email, numeric, date, etc.)

**Objective 3 (Static File Security):** 20 tests
- StaticFilesTest.php - 20 tests
- Coverage: File serving, MIME types, path traversal prevention

**Objective 4 (DB Security):** 11 tests
- DatabaseConfigTest.php - 11 tests
- Coverage: requireEnv(), .env loading, fail-fast

**Objective 5 (Auth/CSRF):** 16 tests
- AuthCsrfAuditTest.php - 16 tests
- Coverage: Auth patterns, CSRF validation, session security

**Objective 6 (Deputy API):** 23 tests
- DeputyApiClientTest.php - 23 tests
- Coverage: API calls, retry logic, break calculations

**Objective 7 (Encryption):** 25 tests
- EncryptionServiceTest.php - 25 tests
- Coverage: AES-256-GCM encryption/decryption, key validation

**Objective 8 (Routes):** 10 tests
- RouteDefinitionsTest.php - 10 tests
- Coverage: Route structure, CSRF, permissions, naming

**Total Tests So Far:** 184 tests

---

## Coverage Gaps to Fill

### 1. Controllers (HIGH PRIORITY)

Need unit tests for:
- [ ] **AmendmentController** (6 actions)
  - create, view, approve, decline, pending, history

- [ ] **PayrollAutomationController** (5 actions)
  - dashboard, pendingReviews, processNow, rules, stats

- [ ] **XeroController** (5 actions)
  - createPayRun, getPayRun, createBatchPayments, authorize, oauthCallback

- [ ] **WageDiscrepancyController** (8 actions)
  - submit, getDiscrepancy, getPending, getMyHistory, approve, decline, uploadEvidence, getStatistics

- [ ] **BonusController** (8 actions)
  - getPending, getHistory, create, approve, decline, getSummary, getVapeDrops, getGoogleReviews

- [ ] **VendPaymentController** (6 actions)
  - getPending, getHistory, getAllocations, approve, decline, getStatistics

- [ ] **LeaveController** (6 actions)
  - getPending, getHistory, create, approve, decline, getBalances

- [ ] **DashboardController** (2 actions)
  - index, getData

- [ ] **PayRunController** (7 actions)
  - index, view, list, create, approve, export, print

- [ ] **ReconciliationController** (4 actions)
  - index, dashboard, getVariances, compareRun

**Total Controller Actions:** 57 actions

### 2. Services (MEDIUM PRIORITY)

Need unit tests for:
- [x] EncryptionService - DONE (25 tests)
- [x] DeputyApiClient - DONE (23 tests)
- [ ] **XeroTokenStore** (4 methods)
  - getAccessToken, getRefreshToken, saveTokens, refreshIfNeeded

- [ ] **PayrollSnapshotManager** (if exists)
  - Snapshot creation, validation, comparison

### 3. Integration Tests (MEDIUM PRIORITY)

Need integration tests for:
- [ ] **Amendment Workflow**
  - Staff creates → Admin approves → Payment calculated

- [ ] **Bonus Workflow**
  - Auto-calculation → Admin review → Approval → Payrun inclusion

- [ ] **Leave Workflow**
  - Staff submits → Manager approves → Payrun deduction

- [ ] **Xero Integration**
  - OAuth flow → Token refresh → Payrun export → Payment creation

- [ ] **Deputy Integration**
  - Timesheet fetch → Break calculation → Pay calculation

---

## Test Strategy

### Unit Tests (Controllers)
**Pattern:**
```php
class AmendmentControllerTest extends TestCase {
    private AmendmentController $controller;
    private PDO $mockDb;

    protected function setUp(): void {
        $this->mockDb = $this->createMock(PDO::class);
        $this->controller = new AmendmentController($this->mockDb);
    }

    public function testCreateRequiresAuth(): void {
        $_SESSION = []; // No auth
        $this->expectException(UnauthorizedException::class);
        $this->controller->create();
    }

    public function testCreateValidatesInput(): void {
        // Test validation
    }

    public function testCreateInsertsToDatabase(): void {
        // Test DB insertion
    }
}
```

### Integration Tests
**Pattern:**
```php
class AmendmentWorkflowTest extends TestCase {
    private PDO $db;

    protected function setUp(): void {
        // Use test database
        $this->db = new PDO('mysql:host=127.0.0.1;dbname=test_db', 'user', 'pass');
        $this->db->exec('START TRANSACTION');
    }

    protected function tearDown(): void {
        $this->db->exec('ROLLBACK');
    }

    public function testFullAmendmentWorkflow(): void {
        // 1. Staff creates amendment
        // 2. Admin approves
        // 3. Payment calculated
        // 4. Verify all states
    }
}
```

---

## Acceptance Criteria

1. ✅ All controller actions have unit tests (57 actions)
2. ✅ All service methods have unit tests
3. ✅ Critical workflows have integration tests (5 workflows)
4. ✅ Test coverage report generated
5. ✅ Coverage meets 80%+ target
6. ✅ All tests passing
7. ✅ Documentation updated with testing guide

---

## Execution Plan

**Phase 1: Controller Tests** (60 minutes)
- Create test class for each controller
- 3-5 tests per action minimum
- Mock dependencies (PDO, services)
- Test auth, validation, business logic

**Phase 2: Service Tests** (15 minutes)
- XeroTokenStore tests (encryption integration)
- PayrollSnapshotManager tests (if exists)

**Phase 3: Integration Tests** (15 minutes)
- Amendment workflow
- Bonus workflow
- Xero integration

**Phase 4: Coverage & Documentation** (15 minutes)
- Run PHPUnit with coverage
- Generate HTML report
- Create TESTING.md guide
- Update main documentation

---

## Starting Test Creation...

Let me begin with the highest-priority controller tests.
