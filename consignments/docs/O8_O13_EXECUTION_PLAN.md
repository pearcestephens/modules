# O8-O13 Execution Plan - Autonomous Completion

**Date:** November 1, 2025
**Current Status:** 69% (O1-O7 Complete, O8-O13 Pending)
**Target:** 100% (All 13 Objectives Complete)
**Estimated Time:** 450 minutes (~7.5 hours)

---

## 📋 Objectives Remaining

| Objective | Description | Status | Est. Time |
|-----------|-------------|--------|-----------|
| **O8** | Transfer Type Services | 25% → 100% | 90 min |
| **O9** | Receiving & Evidence | 40% → 100% | 60 min |
| **O10** | Freight Integration | 80% → 100% | 30 min |
| **O11** | Admin Dashboard | 0% → 100% | 120 min |
| **O12** | Tests & CI | 55% → 100% | 90 min |
| **O13** | Documentation | 60% → 100% | 60 min |
| **TOTAL** | 6 Objectives | **69% → 100%** | **450 min** |

---

## 🎯 Execution Strategy

### Principles
1. **Autonomous execution** - No user confirmation required between steps
2. **Git commit per objective** - Atomic, reversible changes
3. **Test as we go** - Validate each objective before proceeding
4. **Document everything** - Update STATUS.md after each objective
5. **Production-ready code** - Follow all security/quality standards

### Pattern (Per Objective)
```
1. Read existing code/structure
2. Identify gaps (missing files, incomplete implementations)
3. Create/modify files
4. Test (syntax, logic, integration)
5. Update STATUS.md
6. Git add → commit → push
7. Move to next objective
```

---

## 📦 O8: Transfer Type Services (90 min)

**Goal:** Complete 25% → 100% - Add missing transfer type services

### Current State
- ✅ **Purchase Order Service** - Exists and working
- ❌ **Outlet Transfer Service** - Missing (store-to-store transfers)
- ❌ **Supplier Return Service** - Missing (return damaged/wrong items)
- ❌ **Stocktake Service** - Missing (stock adjustment transfers)

### Implementation Plan

#### 1. Create OutletTransferService.php (30 min)
**Location:** `domain/Services/OutletTransferService.php`

**Features:**
- Store-to-store transfer creation
- Stock level validation (ensure source has enough)
- Approval workflow (if value > $2k)
- Lightspeed consignment creation
- Auto-status transitions: draft → approved → sent → received

**Methods:**
```php
createTransfer(array $data): array
validateStockLevels(int $outletId, array $items): bool
requiresApproval(float $totalValue): bool
approve(int $transferId, int $approverId): bool
send(int $transferId, array $shippingData): bool
receive(int $transferId, array $receivedData): bool
```

**Tests:**
- Create outlet transfer (valid data)
- Reject if insufficient stock
- Require approval for high-value transfers
- Full workflow: create → approve → send → receive

#### 2. Create SupplierReturnService.php (30 min)
**Location:** `domain/Services/SupplierReturnService.php`

**Features:**
- Return creation (damaged, incorrect, overstock)
- Return reason tracking
- Photo evidence attachment
- Refund/credit note tracking
- Integration with accounting system

**Methods:**
```php
createReturn(array $data): array
addReturnItem(int $returnId, array $item): array
attachEvidence(int $returnId, string $photoUrl): bool
processRefund(int $returnId, float $amount): bool
updateStatus(int $returnId, string $status): bool
```

**Tests:**
- Create return with reason
- Attach multiple photos
- Track refund amount
- Status transitions

#### 3. Create StocktakeService.php (30 min)
**Location:** `domain/Services/StocktakeService.php`

**Features:**
- Stocktake creation (count vs system)
- Variance calculation
- Adjustment transfer generation
- Multi-stage approval for large variances
- Audit trail

**Methods:**
```php
createStocktake(int $outletId, array $counts): array
calculateVariances(int $stocktakeId): array
requiresApproval(array $variances): bool
approve(int $stocktakeId, int $approverId): bool
generateAdjustmentTransfer(int $stocktakeId): array
```

**Tests:**
- Create stocktake with counts
- Calculate positive/negative variances
- Require approval for large variances
- Generate adjustment transfer

#### 4. Update Controllers/API (15 min)
- Add routes for new services
- Add controller actions
- Update API documentation

#### 5. Integration Tests (15 min)
- Test each service end-to-end
- Verify database state after operations
- Test error handling

### Acceptance Criteria
- ✅ 3 new service files created and tested
- ✅ All services follow same pattern as PO service
- ✅ Unit tests pass (min 20 tests per service)
- ✅ Integration tests pass (full workflow per type)
- ✅ API endpoints documented
- ✅ STATUS.md updated: O8 = 100%

---

## 📸 O9: Receiving & Evidence (60 min)

**Goal:** Complete 40% → 100% - Enhanced receiving with photos + signatures

### Current State
- ✅ Basic receiving flow exists
- 🟡 Photo capture partially implemented
- ❌ Signature capture missing
- ❌ Per-item variance tracking incomplete
- ❌ Evidence storage system not finalized

### Implementation Plan

#### 1. Enhance ReceivingService (20 min)
**Location:** `domain/Services/ReceivingService.php`

**Add Methods:**
```php
capturePhoto(int $itemId, string $photoData): array
captureSignature(int $consignmentId, string $signatureData): array
trackItemVariance(int $itemId, int $expected, int $actual, string $reason): bool
finalizeReceive(int $consignmentId, array $evidence): array
triggerLightspeedReceive(int $consignmentId, array $actualQuantities): bool
```

**Features:**
- Store photos in `/uploads/receiving/` with unique IDs
- Store signatures in DB (base64) + file backup
- Track variance per line item (not just total)
- Send actual received quantities to Lightspeed (Step 8)
- Complete audit trail in transfer_audit_log

#### 2. Create Evidence Storage Structure (10 min)
```bash
mkdir -p uploads/receiving/photos
mkdir -p uploads/receiving/signatures
chmod 755 uploads/receiving
```

**Database:**
- Add `receiving_evidence` table:
  - id, consignment_id, item_id, evidence_type (photo/signature)
  - file_path, file_hash, created_by, created_at

#### 3. Update Receiving UI (15 min)
**Location:** `views/receive.php`

**Add:**
- Photo capture button (mobile camera or file upload)
- Signature pad (HTML5 canvas or library)
- Per-item variance input with reason dropdown
- Evidence preview (thumbnails)

#### 4. Add Photo/Signature Validation (10 min)
- File type validation (JPEG, PNG for photos)
- File size limits (max 5MB per photo)
- Signature validation (non-empty canvas)
- Secure file naming (prevent path traversal)

#### 5. Integration Tests (10 min)
- Receive with photos + signature
- Verify evidence stored correctly
- Verify variances tracked per item
- Verify Lightspeed receives actual quantities

### Acceptance Criteria
- ✅ Photo capture working (upload + mobile camera)
- ✅ Signature capture working (canvas-based)
- ✅ Per-item variance tracking complete
- ✅ Lightspeed receives actual quantities
- ✅ Evidence stored securely with audit trail
- ✅ Integration tests pass (full receive workflow)
- ✅ STATUS.md updated: O9 = 100%

---

## 🚚 O10: Freight Integration Testing (30 min)

**Goal:** Complete 80% → 100% - Polish and comprehensive testing

### Current State
- ✅ Weight/volume calculation working
- ✅ Carrier API integrations (NZ Post, GoSweetSpot)
- ✅ Quote caching
- 🟡 Edge cases need handling
- 🟡 Tests incomplete

### Implementation Plan

#### 1. Create FreightService Wrapper (10 min)
**Location:** `domain/Services/FreightService.php`

**Wrap existing FreightIntegration:**
```php
class FreightService {
    private FreightIntegration $freight;

    public function __construct(FreightIntegration $freight) {
        $this->freight = $freight;
    }

    public function calculateWeightVolume(array $items): array
    public function suggestContainer(float $volume): string
    public function getQuote(string $carrier, array $shipment): array
    public function bookShipment(string $carrier, array $data): array
}
```

#### 2. Add Edge Case Handling (10 min)
- Handle missing product dimensions (use defaults)
- Handle carrier API timeouts (fallback to manual)
- Handle invalid addresses (validation before quote)
- Handle zero-weight items

#### 3. Comprehensive Tests (10 min)
**Location:** `tests/Unit/FreightServiceTest.php`

**Test Coverage:**
- Weight/volume calculation (accurate formulas)
- Container suggestion (20ft, 40ft, LCL)
- Quote caching (don't hit API repeatedly)
- Booking idempotency (same request_id = same result)
- Error handling (timeouts, invalid data)

**Integration Tests:**
- Mock carrier API responses
- Verify quote retrieval
- Verify booking success

### Acceptance Criteria
- ✅ FreightService wrapper created
- ✅ All edge cases handled gracefully
- ✅ 15+ unit tests passing
- ✅ Integration tests with mocks
- ✅ Freight runbook documented
- ✅ STATUS.md updated: O10 = 100%

---

## 📊 O11: Admin Dashboard (120 min)

**Goal:** Complete 0% → 100% - Full sync status monitoring UI

### Current State
- ❌ No admin dashboard exists
- ❌ No queue metrics visibility
- ❌ No webhook event log UI
- ❌ No failed job retry interface

### Implementation Plan

#### 1. Create Dashboard Page (30 min)
**Location:** `admin/sync-status.php`

**Sections:**
- System Health (overall status indicator)
- Queue Metrics (depth, processing rate, error rate)
- Recent Failures (last 20, with retry button)
- Webhook Events (last 50, with timestamp)
- Poller Status (cursor position, last run)
- Worker Heartbeat (last ping, status)

**Layout:**
```
┌─────────────────────────────────────────────────┐
│ CONSIGNMENTS SYNC STATUS DASHBOARD             │
├─────────────────────────────────────────────────┤
│ System Health: 🟢 HEALTHY                      │
│ Last Updated: 2025-11-01 15:30:45 UTC          │
├─────────────────────────────────────────────────┤
│ QUEUE METRICS                                   │
│ - Jobs Pending: 12                              │
│ - Jobs Processing: 3                            │
│ - Jobs Completed (24h): 1,247                   │
│ - Jobs Failed (24h): 8                          │
│ - Processing Rate: 42/min                       │
│ - Error Rate: 0.6%                              │
├─────────────────────────────────────────────────┤
│ RECENT FAILURES                                 │
│ [Table with: ID, Job Type, Error, Time, Retry] │
├─────────────────────────────────────────────────┤
│ WEBHOOK EVENTS (Last 50)                        │
│ [Table with: ID, Event Type, Status, Time]     │
├─────────────────────────────────────────────────┤
│ WORKER STATUS                                   │
│ - Last Heartbeat: 12 seconds ago                │
│ - Status: ACTIVE                                │
│ - Current Job: webhook-123                      │
└─────────────────────────────────────────────────┘
```

#### 2. Create Dashboard API (30 min)
**Location:** `admin/api/sync-status.php`

**Endpoints:**
```php
// Get dashboard data
POST /admin/api/sync-status.php
{"action": "get_metrics"}

// Retry failed job
POST /admin/api/sync-status.php
{"action": "retry_job", "data": {"job_id": 123}}

// Get webhook events
POST /admin/api/sync-status.php
{"action": "get_webhooks", "data": {"limit": 50}}
```

**Metrics Calculation:**
```php
function getQueueMetrics(): array {
    // Query queue_jobs table
    return [
        'pending' => count(status = 'pending'),
        'processing' => count(status = 'processing'),
        'completed_24h' => count(status = 'completed' AND created_at > 24h ago),
        'failed_24h' => count(status = 'failed' AND created_at > 24h ago),
        'processing_rate' => completed_24h / 1440, // per minute
        'error_rate' => failed_24h / (completed_24h + failed_24h)
    ];
}
```

#### 3. Add Retry Functionality (20 min)
**Location:** `domain/Services/QueueService.php`

**Add Method:**
```php
public function retryJob(int $jobId): bool {
    // Reset attempts, clear error, set status = pending
    $stmt = $this->db->prepare("
        UPDATE queue_jobs
        SET status = 'pending',
            attempts = 0,
            error = NULL,
            updated_at = NOW()
        WHERE id = ?
    ");
    return $stmt->execute([$jobId]);
}
```

#### 4. Add Basic Counters (30 min)
**Location:** `infra/Metrics/SimpleCounters.php`

**File-based counters (no Prometheus dependency):**
```php
class SimpleCounters {
    private string $counterDir = '/tmp/cis-counters/';

    public function increment(string $metric): void {
        $file = $this->counterDir . $metric;
        $current = file_exists($file) ? (int)file_get_contents($file) : 0;
        file_put_contents($file, $current + 1);
    }

    public function get(string $metric): int {
        $file = $this->counterDir . $metric;
        return file_exists($file) ? (int)file_get_contents($file) : 0;
    }
}
```

**Metrics to track:**
- `jobs_processed_total`
- `jobs_failed_total`
- `webhooks_received_total`
- `poller_runs_total`

#### 5. Auto-refresh UI (10 min)
**Add JavaScript:**
```javascript
// Auto-refresh every 10 seconds
setInterval(async () => {
    const response = await fetch('/admin/api/sync-status.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({action: 'get_metrics'})
    });
    const data = await response.json();
    updateDashboard(data);
}, 10000);
```

### Acceptance Criteria
- ✅ Dashboard page created and accessible
- ✅ All metrics display correctly
- ✅ Retry button works (re-queues failed jobs)
- ✅ Auto-refresh every 10 seconds
- ✅ Worker heartbeat visible
- ✅ Webhook events log displayed
- ✅ STATUS.md updated: O11 = 100%

---

## 🧪 O12: Tests & CI (90 min)

**Goal:** Complete 55% → 100% - Comprehensive testing + CI pipeline

### Current State
- ✅ Some API tests exist
- 🟡 Unit tests incomplete (only ~20% coverage)
- ❌ Integration tests missing
- ❌ Smoke tests missing
- ❌ CI pipeline not configured

### Implementation Plan

#### 1. API Tests Extension (20 min)
**Location:** `tests/api/` (existing)

**Add Tests:**
- All status transitions (draft → approved → sent → received → completed)
- Receive workflow (with variance handling)
- Outlet transfer end-to-end
- Supplier return workflow
- Stocktake workflow
- Error cases (invalid transitions, missing data)

**Target:** 30+ API tests

#### 2. Unit Tests (30 min)
**Location:** `tests/Unit/`

**Create Tests For:**
- `StatusMap.php` (CIS ↔ LS conversions)
- `StateTransitionPolicy.php` (legal transitions)
- `LightspeedClient.php` (retry logic, idempotency)
- `OutletTransferService.php` (20 tests)
- `SupplierReturnService.php` (15 tests)
- `StocktakeService.php` (15 tests)
- `FreightService.php` (15 tests - already done in O10)
- `ReceivingService.php` (20 tests)

**Target:** 100+ unit tests

#### 3. Integration Tests (20 min)
**Location:** `tests/Integration/`

**Create Tests:**
- Queue worker (happy path + DLQ)
- Webhook handler (HMAC validation + job creation)
- End-to-end: create PO → send → receive (with mocks)
- End-to-end: outlet transfer (full workflow)
- Lightspeed sync (mock API responses)

**Target:** 10+ integration tests

#### 4. Smoke Tests (10 min)
**Location:** `tests/smoke/`

**Create Tests:**
- System health check (DB, Lightspeed API)
- Queue worker running
- Webhook endpoint accessible
- Critical pages load (< 3s)

**Target:** 5+ smoke tests

#### 5. CI Pipeline (10 min)
**Location:** `.github/workflows/consignments-ci.yml`

**GitHub Actions Workflow:**
```yaml
name: Consignments CI

on:
  push:
    branches: [main, develop]
    paths:
      - 'modules/consignments/**'
  pull_request:
    branches: [main, develop]
    paths:
      - 'modules/consignments/**'

jobs:
  test:
    runs-on: ubuntu-latest

    steps:
      - uses: actions/checkout@v3

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.1'
          extensions: pdo, pdo_mysql, mbstring

      - name: Install dependencies
        run: composer install --no-interaction --prefer-dist
        working-directory: modules/consignments

      - name: Run unit tests
        run: vendor/bin/phpunit tests/Unit
        working-directory: modules/consignments

      - name: Run integration tests
        run: vendor/bin/phpunit tests/Integration
        working-directory: modules/consignments

      - name: Security scan
        run: |
          composer require --dev sensiolabs/security-checker
          vendor/bin/security-checker security:check
        working-directory: modules/consignments

      - name: Check for secrets
        run: |
          ! grep -rE '(password|secret|token|api_key)\s*=\s*["\'][^"\']{8,}' modules/consignments --exclude-dir=vendor
```

**Features:**
- Run on every push/PR
- Test on PHP 8.1
- Run all test suites
- Security scan
- Secret scan
- Block merge if tests fail

### Acceptance Criteria
- ✅ 30+ API tests passing
- ✅ 100+ unit tests passing
- ✅ 10+ integration tests passing
- ✅ 5+ smoke tests passing
- ✅ CI pipeline configured and green
- ✅ Test coverage ≥ 90%
- ✅ All tests pass locally and in CI
- ✅ STATUS.md updated: O12 = 100%

---

## 📚 O13: Documentation (60 min)

**Goal:** Complete 60% → 100% - Consolidate and finalize all docs

### Current State
- ✅ STATUS.md exists (needs final update)
- ✅ Roadmap.md exists
- 🟡 API docs incomplete
- ❌ Runbooks missing
- ❌ Testing guide missing
- ❌ Troubleshooting guide missing

### Implementation Plan

#### 1. Update STATUS.md (10 min)
- Update completion percentages (all to 100%)
- Add final timestamp
- Update overall status to "COMPLETE"
- Add summary of what was delivered

#### 2. Create Runbooks (20 min)
**Location:** `docs/Runbooks/`

**Files to Create:**

**a) QueueAndPolling.md**
- How to start queue worker
- How to start poller
- Cron examples
- Supervisor config
- Troubleshooting worker failures
- Monitoring commands

**b) FreightSetup.md**
- Carrier credential setup (NZ Post, GoSweetSpot)
- API key configuration
- Testing freight quotes
- Troubleshooting API failures
- Cost optimization tips

**c) EmergencyRecovery.md**
- System down procedures
- Data recovery from backups
- Queue DLQ processing
- Manual Lightspeed sync
- Contact escalation

#### 3. Create API Documentation (15 min)
**Location:** `docs/API/`

**Files to Create:**

**a) Endpoints.md**
- Full reference of all API endpoints
- Request/response examples
- Error codes and meanings
- Authentication requirements
- Rate limits

**b) LightspeedWebhooks.md**
- Webhook setup in Lightspeed admin
- HMAC signature validation
- Event types and payloads
- Testing webhooks locally
- Debugging webhook failures

#### 4. Create Testing Guide (10 min)
**Location:** `docs/Testing.md`

**Contents:**
- How to run unit tests
- How to run integration tests
- How to run smoke tests
- Test coverage report generation
- Writing new tests (conventions)
- Mock setup examples

#### 5. Create Troubleshooting Guide (5 min)
**Location:** `docs/Troubleshooting.md`

**Contents:**
- Common errors and solutions
- Queue worker not processing jobs
- Webhook endpoint not receiving events
- Lightspeed API rate limit errors
- Database connection issues
- Photo upload failures
- Signature capture issues

#### 6. Remove Duplicate/Conflicting Docs (5 min)
- Audit all markdown files
- Identify duplicates
- Merge or remove as needed
- Ensure single source of truth

### Acceptance Criteria
- ✅ STATUS.md updated to 100% complete
- ✅ All runbooks created and comprehensive
- ✅ Complete API documentation
- ✅ Testing guide complete
- ✅ Troubleshooting guide complete
- ✅ No duplicate/conflicting docs
- ✅ Docs lint passes (no broken links)
- ✅ STATUS.md updated: O13 = 100%

---

## 🎉 Final Validation (After All Objectives Complete)

### Checklist
- [ ] All 6 objectives (O8-O13) complete
- [ ] STATUS.md shows 100% completion
- [ ] All tests pass (unit, integration, API, smoke)
- [ ] CI pipeline green
- [ ] All documentation complete and accurate
- [ ] No secrets in code
- [ ] No broken links in docs
- [ ] Git history clean (1 commit per objective)
- [ ] All commits pushed to GitHub

### Final Commit
```bash
git add .
git commit -m "feat(consignments): All 13 objectives complete - 100% 🎉

- O8: Transfer Type Services (100%)
- O9: Receiving & Evidence (100%)
- O10: Freight Integration Testing (100%)
- O11: Admin Dashboard (100%)
- O12: Tests & CI (100%)
- O13: Documentation (100%)

DELIVERABLES:
- 3 new transfer type services (Outlet, SupplierReturn, Stocktake)
- Enhanced receiving with photos + signatures
- Comprehensive freight testing
- Full admin sync status dashboard
- 145+ tests (30 API, 100 unit, 10 integration, 5 smoke)
- CI/CD pipeline with GitHub Actions
- Complete documentation (runbooks, API docs, guides)

TEST COVERAGE: >90%
SECURITY: All secrets removed, CSRF enforced
STATUS: PRODUCTION-READY ✅"

git push origin payroll-hardening-20251101
```

---

## 🚀 Ready to Execute

**Estimated Total Time:** 450 minutes (~7.5 hours)
**Execution Mode:** Autonomous (no user confirmation needed)
**Safety:** Each objective committed separately (reversible)

**START EXECUTION NOW!** 🚀
