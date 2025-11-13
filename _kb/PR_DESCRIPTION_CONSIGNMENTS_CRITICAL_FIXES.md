# PR #1: Consignments Critical Fixes - Production Readiness

## 🎯 Objective
Fix critical blockers preventing consignments module deployment. Module is architecturally 96% complete but has deployment/configuration issues preventing production use.

## 📊 Current Status
- **Completion:** 96% architecture, 70% deployment-ready
- **Objectives Complete:** O1-O12 (all 100%), O13 at 60%
- **Critical Issues:** 3 blockers + 3 high priority
- **Code Quality:** Good (hexagonal architecture, modern patterns)

## 🚨 Critical Blockers (MUST FIX)

### 1. Queue Worker Not Running (2-3 hours)
**Problem:** Async jobs accumulating but not processing
- Jobs pile up in `queue_jobs` table
- DLQ exists but never receives failed jobs
- Code exists (O6 complete) but deployment/supervision not configured

**Tasks:**
- [ ] Create `bin/queue-worker.php` supervisor config
- [ ] Create systemd service OR supervisor.conf for production
- [ ] Add cron fallback if supervisor unavailable
- [ ] Test with real job volumes in staging
- [ ] Create runbook: `docs/Runbooks/QueueWorkerSetup.md`

**Files to Create:**
```
bin/queue-worker.php          # If not exists
config/supervisor/queue-worker.conf
config/systemd/queue-worker.service
docs/Runbooks/QueueWorkerSetup.md
```

**Acceptance Criteria:**
- ✅ Jobs process within 30 seconds of creation
- ✅ Failed jobs move to DLQ after max retries
- ✅ Worker restarts automatically if crashes
- ✅ Logs show job processing activity

---

### 2. Webhook Endpoint Missing/Broken (2-3 hours)
**Problem:** Can't receive Lightspeed real-time events
- `public/webhooks/lightspeed.php` may not exist or not accessible
- Lightspeed can't notify CIS of changes (must rely on polling only)
- HMAC validation code exists but endpoint not live

**Tasks:**
- [ ] Create/verify `public/webhooks/lightspeed.php` endpoint
- [ ] Configure Nginx/Apache route for `/webhooks/lightspeed`
- [ ] Register webhook URL in Lightspeed admin
- [ ] Test HMAC signature validation with real events
- [ ] Test idempotent job creation (replay same event)
- [ ] Create runbook: `docs/Runbooks/WebhookSetup.md`

**Files to Create/Modify:**
```
public/webhooks/lightspeed.php           # Create or verify
config/nginx/webhooks.conf               # Nginx config
docs/Runbooks/WebhookSetup.md           # Setup guide
tests/integration/WebhookTest.php       # Integration tests
```

**Acceptance Criteria:**
- ✅ Endpoint returns 202 for valid HMAC
- ✅ Endpoint returns 401 for invalid HMAC
- ✅ Duplicate events ignored (idempotency)
- ✅ Jobs created in queue_jobs table
- ✅ Events logged with correlation IDs

---

### 3. State Transitions Unvalidated (3-4 hours)
**Problem:** APIs allow invalid status changes
- Can set DRAFT → RECEIVED without intermediate steps (SENT → IN_TRANSIT)
- O2 "Canonical Status Map" code exists but not enforced in all write paths
- Risk: Data corruption, orphaned Lightspeed consignments

**Tasks:**
- [ ] Audit all 11 Transfer API endpoints for validation gaps
- [ ] Add `StateTransitionPolicy::validate()` to all status updates
- [ ] Return 422 with clear error for illegal transitions
- [ ] Add unit tests for illegal transitions (10-15 test cases)
- [ ] Update API docs with valid state machine diagram

**Files to Modify:**
```
app/api/transfers/create.php
app/api/transfers/update-status.php
app/api/transfers/send.php
app/api/transfers/receive.php
[... all 11 Transfer API endpoints ...]
tests/unit/StateTransitionPolicyTest.php
docs/API/TransferAPI.md
```

**Example Validation Code:**
```php
use Consignments\Domain\Policies\StateTransitionPolicy;

// In each API endpoint before status update:
$currentStatus = $transfer['status'];
$newStatus = $_POST['status'];

if (!StateTransitionPolicy::isValidTransition($currentStatus, $newStatus)) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'error' => [
            'code' => 'INVALID_STATE_TRANSITION',
            'message' => "Cannot transition from {$currentStatus} to {$newStatus}",
            'valid_transitions' => StateTransitionPolicy::getValidTransitions($currentStatus)
        ]
    ]);
    exit;
}
```

**Acceptance Criteria:**
- ✅ All illegal transitions return 422
- ✅ Error response includes valid transitions
- ✅ Unit tests cover all transition combinations
- ✅ API docs show state machine diagram

---

## 🔴 High Priority (SECURITY/STABILITY)

### 4. Remove Remaining Secrets (1-2 hours)
**Problem:** Despite O4 completion, some secrets may remain
- Grep for: `password`, `token`, `secret`, `api_key`, `PIN_CODE`
- Move to `.env` with fail-closed validation
- Update `.env.example`

**Tasks:**
- [ ] Run: `grep -r "password\|token\|secret\|api_key\|PIN" --include="*.php" | grep -v ".env"`
- [ ] Move any found secrets to `.env`
- [ ] Add startup validation (fail if missing)
- [ ] Update `.env.example` with new variables
- [ ] Verify grep returns clean

**Acceptance Criteria:**
- ✅ `grep` search returns 0 hardcoded secrets
- ✅ App fails to start if required env vars missing
- ✅ `.env.example` has all required variables

---

### 5. Fix Method Name Mismatches (2-3 hours)
**Problem:** API endpoints call methods that don't exist
- Example: API calls `updateItemPackedQty()` but method is `setItemPackedQty()`
- Causes 500 errors when code paths hit

**Tasks:**
- [ ] Audit all 11 API endpoint → Service method calls
- [ ] List all mismatches (method names that don't exist)
- [ ] Either rename methods OR add wrapper methods
- [ ] Add method existence tests (prevent future mismatches)
- [ ] Smoke test all 11 endpoints

**Files to Audit:**
```
app/api/transfers/*.php  (11 endpoints)
app/Domain/Services/*.php (TransferService, OutletTransferService, etc.)
```

**Acceptance Criteria:**
- ✅ All API calls invoke existing methods
- ✅ Tests verify method existence at call sites
- ✅ All 11 endpoints return 200/201 for valid input

---

### 6. Add CSRF Protection (2-3 hours)
**Problem:** All write endpoints vulnerable to CSRF
- O4 added `Security::validateCsrf()` helper but not applied
- No token generation in forms

**Tasks:**
- [ ] Add `Security::generateCsrfToken()` to forms
- [ ] Add `Security::validateCsrf()` to all POST/PUT/DELETE endpoints
- [ ] Test with missing/invalid/expired tokens (expect 403)
- [ ] Update API docs (require `X-CSRF-Token` header)

**Files to Modify:**
```
app/api/transfers/*.php  (all write endpoints)
views/transfers/*.php    (forms)
infra/Http/Security.php  (already has helpers)
docs/API/Authentication.md
```

**Acceptance Criteria:**
- ✅ All write endpoints validate CSRF token
- ✅ Missing token returns 403
- ✅ Invalid token returns 403
- ✅ Valid token allows request through

---

## 🟡 Medium Priority (OPTIONAL FOR TUESDAY)

### 7. Increase Test Coverage (6-8 hours) - CAN DEFER
- Current: 20% coverage (60 unit + 3 integration tests)
- Target: 50% coverage (add 20-30 unit tests + 3-5 integration tests)
- Focus: Transfer services, receiving flow, freight integration

### 8. Consolidate Documentation (4-6 hours) - CAN DEFER
- Create master index (like payroll `_kb/INDEX.md`)
- Consolidate overlapping docs (12+ files currently)
- Write missing runbooks (troubleshooting, deployment)

### 9. Setup CI Pipeline (1-2 hours) - CAN DEFER
- Create `.github/workflows/ci.yml`
- Run PHPUnit on PRs
- Block merge if tests fail

---

## 📂 Files to Create/Modify Summary

### New Files (Create These)
```
bin/queue-worker.php                     # Queue worker script (if missing)
config/supervisor/queue-worker.conf      # Supervisor config
config/systemd/queue-worker.service      # Systemd service
config/nginx/webhooks.conf               # Nginx webhook route
docs/Runbooks/QueueWorkerSetup.md       # Queue worker runbook
docs/Runbooks/WebhookSetup.md           # Webhook setup runbook
docs/Runbooks/Troubleshooting.md        # Troubleshooting guide
tests/integration/WebhookTest.php        # Webhook integration tests
tests/unit/StateTransitionPolicyTest.php # State validation tests
```

### Files to Modify
```
public/webhooks/lightspeed.php          # Verify/fix webhook endpoint
app/api/transfers/*.php                 # Add state validation + CSRF (11 files)
app/Domain/Services/*.php               # Fix method name mismatches
views/transfers/*.php                   # Add CSRF tokens to forms
.env.example                            # Add missing env vars
docs/API/TransferAPI.md                 # Document state machine + CSRF
docs/STATUS.md                          # Update completion percentages
```

---

## ✅ Definition of Done

**This PR is complete when:**
- ✅ Queue worker processes jobs in production
- ✅ Webhook endpoint receives Lightspeed events
- ✅ State transitions validated (illegal transitions return 422)
- ✅ CSRF tokens required on all write endpoints
- ✅ No secrets in code (`grep` returns clean)
- ✅ All API endpoints call existing methods (no 500 errors)
- ✅ All tests pass (existing 63 tests + new tests)
- ✅ Runbooks exist for queue worker + webhook setup
- ✅ Staging deployment succeeds with smoke tests
- ✅ Ready for production deployment

---

## 🎯 Success Metrics

**Before This PR:**
- Queue jobs: Accumulating ❌
- Webhooks: Not working ❌
- State validation: Missing ❌
- CSRF protection: Missing ❌
- Deployment-ready: 70% 🟡

**After This PR:**
- Queue jobs: Processing ✅
- Webhooks: Receiving events ✅
- State validation: Enforced ✅
- CSRF protection: Active ✅
- Deployment-ready: 95% ✅

---

## 📚 Reference Documents

- **Full Analysis:** `/modules/human_resources/payroll/_kb/CONSIGNMENTS_TIME_ESTIMATE.md`
- **Comparison:** `/modules/human_resources/payroll/_kb/PAYROLL_VS_CONSIGNMENTS_COMPLETE_COMPARISON.md`
- **Current Status:** `/modules/consignments/docs/STATUS.md`
- **Roadmap:** `/modules/consignments/docs/Roadmap.md`

---

## ⏱️ Estimated Time

**Total Work:** 17-18 hours with AI Agent assistance
- Critical blockers: 4.5 hours
- High priority: 5 hours
- Medium priority (if time): 8 hours

**Target Completion:** Sunday evening (Nov 3, 2025) for deployment Monday morning

---

## 🤖 AI Agent Instructions

**Approach:**
1. Start with queue worker config (highest impact)
2. Then webhook endpoint (enables real-time sync)
3. Then state validation (prevents data corruption)
4. Then CSRF + secrets + method fixes (security/stability)
5. Tests throughout (add as you build)

**Patterns to Follow:**
- Use existing `infra/Http/Security.php` helpers
- Follow hexagonal architecture (domain → infrastructure)
- Match existing code style (PSR-12, strict types)
- Add PHPDoc comments to all new methods
- Log all errors with correlation IDs

**Testing Strategy:**
- Unit tests for state transitions
- Integration tests for webhooks
- Smoke tests for all API endpoints
- Manual staging deployment test

**Questions?** Tag @pearcestephens in PR comments.

---

**Ready to deploy consignments to production by Monday! 🚀**
