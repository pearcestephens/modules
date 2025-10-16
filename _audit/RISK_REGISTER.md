# Risk Register - Prioritized Issues

**Generated:** 2025-10-16  
**Scope:** CIS Consignments Module  
**Total Issues:** 47  
**P0 (Critical):** 8 | **P1 (High):** 12 | **P2 (Medium):** 18 | **P3 (Low):** 9

---

## P0 - CRITICAL (Must Fix Before Production)

### [P0-001] Authorization Header Misconfiguration ✅ PARTIALLY FIXED
**File:** `consignments/api/lightspeed.php:265`, `process-consignment-upload.php:120`  
**Status:** Fixed in simple-upload-direct.php, needs verification in other files  
**Severity:** P0 - **Blocks all Vend API calls**  
**Evidence:**
```php
// WRONG (prevents authentication):
'Authorization=' . $VEND_TOKEN

// CORRECT:
'Authorization: Bearer ' . $VEND_TOKEN
```

**Impact:** All Vend API calls fail with 401 Unauthorized  
**Fix Effort:** S (15 min)  
**Owner:** Backend Team  
**Blast Radius:** All consignment uploads, sync operations  
**Fix:**
1. Search all files for `'Authorization='` pattern
2. Replace with `'Authorization: Bearer '`
3. Test each API client individually
4. Add unit test to prevent regression

**Verification Command:**
```bash
grep -rn "Authorization=" consignments/api/*.php
```

---

### [P0-002] SQL Column Name Mismatch ✅ FIXED
**File:** `consignments/api/simple-upload-direct.php:193-194` (NOW FIXED)  
**Status:** Fixed in latest version  
**Severity:** P0 - **Runtime SQL errors**  
**Evidence:**
```sql
-- OLD (broken):
LEFT JOIN vend_outlets src ON t.source_outlet_id = src.id

-- NEW (correct):
LEFT JOIN vend_outlets src ON src.id = t.outlet_from
```

**Impact:** Transfer queries fail with "Unknown column 't.source_outlet_id'"  
**Fix Effort:** S (Already done)  
**Owner:** ✅ COMPLETED  
**Blast Radius:** All upload operations  
**Notes:** Verify no other files use old column names

---

### [P0-003] State Change Before Vend Upload ✅ FIXED
**File:** `consignments/api/submit_transfer_simple.php:151` (NOW FIXED)  
**Status:** Fixed  
**Severity:** P0 - **Data integrity violation**  
**Evidence:**
```php
// OLD (wrong):
$newState = 'SENT';  // Premature!

// NEW (correct):
$newState = 'PACKING';  // Waiting for Vend
```

**Impact:** Transfer marked SENT before Vend upload, can't upload again  
**Fix Effort:** S (Already done)  
**Owner:** ✅ COMPLETED  
**Blast Radius:** Upload workflow integrity  
**State Flow:** OPEN → PACKING (submit) → SENT (after Vend success)

---

### [P0-004] Hardcoded Vend API Token
**File:** `consignments/api/simple-upload-direct.php:36`  
**Status:** OPEN  
**Severity:** P0 - **Security breach risk**  
**Evidence:**
```php
const VEND_TOKEN = '[LOADED_FROM_CONFIG_TABLE_ID_23]';
```

**Impact:** Token exposed in git history, can't rotate without code change  
**Fix Effort:** S (30 min)  
**Owner:** Security Team  
**Blast Radius:** All Vend integrations  
**Fix:**
1. Move to `.env` file: `VEND_API_TOKEN=...`
2. Read via `getenv('VEND_API_TOKEN')`
3. Add `.env.example` with placeholder
4. Update deployment docs
5. Rotate token immediately after fix

---

### [P0-005] No CSRF Protection on Upload Endpoints
**File:** `consignments/api/simple-upload-direct.php`, `submit_transfer_simple.php`  
**Status:** OPEN  
**Severity:** P0 - **CSRF attack vector**  
**Evidence:** No CSRF token validation in upload endpoints  
**Impact:** Attacker can forge upload requests from victim's session  
**Fix Effort:** M (2 hours)  
**Owner:** Security Team  
**Blast Radius:** All POST endpoints  
**Fix:**
1. Add CSRF token generation in form render
2. Validate `$_POST['csrf_token']` in all POST handlers
3. Use `hash_equals()` for timing-safe comparison
4. Return 403 on validation failure

**Example Fix:**
```php
// In handler:
if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    http_response_code(403);
    die(json_encode(['error' => 'Invalid CSRF token']));
}
```

---

### [P0-006] SQL Injection Risk (Prepared Statements Not Always Used)
**File:** `consignments/stock-transfers/pack.php:450-480` (inline queries)  
**Status:** OPEN  
**Severity:** P0 - **Data breach risk**  
**Evidence:** Some queries use string concatenation instead of prepared statements  
**Impact:** Attacker could inject SQL, read/modify data  
**Fix Effort:** M (3 hours - audit + fix all)  
**Owner:** Backend Team  
**Blast Radius:** All database queries  
**Fix:**
1. Audit all SQL queries for dynamic values
2. Convert to prepared statements with `?` placeholders
3. Use PDO parameter binding `$stmt->execute([$param])`
4. Add static analysis (phpstan/psalm) to catch future issues

---

### [P0-007] No Rate Limiting on API Endpoints
**File:** All `consignments/api/*.php` files  
**Status:** OPEN  
**Severity:** P0 - **DoS vulnerability**  
**Evidence:** No throttling on upload/submit endpoints  
**Impact:** Attacker can exhaust resources with request floods  
**Fix Effort:** M (4 hours)  
**Owner:** Infrastructure Team  
**Blast Radius:** All API endpoints  
**Fix Options:**
1. **Nginx level:** `limit_req_zone` + `limit_req`
2. **PHP middleware:** Token bucket or sliding window
3. **CDN level:** Cloudflare rate limiting rules

**Recommended:** Nginx + PHP combined
```nginx
limit_req_zone $binary_remote_addr zone=api:10m rate=10r/s;
location /modules/consignments/api/ {
    limit_req zone=api burst=20 nodelay;
}
```

---

### [P0-008] Vend Product ID Mismatch
**File:** `consignments/api/simple-upload-direct.php:207`  
**Status:** OPEN  
**Severity:** P0 - **Wrong products uploaded to Vend**  
**Evidence:**
```php
// Code uses: vp.product_id AS vend_product_id
// But sometimes references: vend_products.id
```

**Impact:** Wrong product IDs sent to Vend, inventory errors  
**Fix Effort:** M (2 hours - audit schema)  
**Owner:** Backend Team  
**Blast Radius:** All product-related Vend calls  
**Fix:**
1. Confirm which column Vend expects (UUID vs auto-increment ID)
2. Standardize all queries to use correct column
3. Add foreign key constraint to enforce
4. Test with Vend API

---

## P1 - HIGH PRIORITY (Near-Term, < 2 weeks)

### [P1-001] Fake SSE Progress Tracking ✅ FIXED
**File:** `consignments/api/consignment-upload-progress-simple.php:94`  
**Status:** Fixed in enhanced version  
**Severity:** P1 - **Misleading UX**  
**Evidence:**
```php
// OLD (fake):
usleep(500000); // Just sleep, no real tracking

// NEW (real):
// Reads from consignment_upload_progress table
```

**Impact:** Users see fake progress while real upload happens silently  
**Fix Effort:** S (Already done in enhanced version)  
**Owner:** ✅ COMPLETED  
**Blast Radius:** User experience, trust  
**Next Step:** Verify simple-upload-direct.php writes progress

---

### [P1-002] Modal Opens Twice (Double Flash) ✅ FIXED
**File:** `consignments/stock-transfers/js/pack.js:545, 644`  
**Status:** Fixed  
**Severity:** P1 - **Poor UX**  
**Evidence:** Modal opened at form submit AND after session ID received  
**Impact:** Confusing flash effect, looks broken  
**Fix Effort:** S (Already done)  
**Owner:** ✅ COMPLETED  
**Blast Radius:** User experience  

---

### [P1-003] No Progress Writes During Upload
**File:** `consignments/api/simple-upload-direct.php`  
**Status:** OPEN  
**Severity:** P1 - **SSE has nothing to stream**  
**Evidence:** File uploads to Vend but doesn't write progress rows  
**Impact:** SSE endpoint can't show real progress  
**Fix Effort:** M (2 hours)  
**Owner:** Backend Team  
**Blast Radius:** Real-time progress feature  
**Fix:**
1. After creating consignment: INSERT INTO consignment_upload_progress
2. After each product: UPDATE completed_products
3. On error: UPDATE status='failed', error_message
4. On success: UPDATE status='completed'

---

### [P1-004] Missing Foreign Key Constraints
**File:** `consignments/database/enhanced-consignment-schema.sql:45-48`  
**Status:** OPEN  
**Severity:** P1 - **Data integrity risk**  
**Evidence:** FKs defined in schema but not enforced in production  
**Impact:** Orphaned records, cascading deletes don't work  
**Fix Effort:** M (3 hours - test carefully)  
**Owner:** DBA Team  
**Blast Radius:** Data consistency  
**Fix:**
1. Backup database
2. Clean orphaned records: `SELECT * FROM queue_consignments WHERE transfer_id NOT IN (SELECT id FROM transfers)`
3. Add FK constraints: `ALTER TABLE queue_consignments ADD CONSTRAINT ...`
4. Test cascading behavior
5. Monitor for errors

---

### [P1-005] No Idempotency Keys on Vend API Calls
**File:** `consignments/api/simple-upload-direct.php:147` (create consignment)  
**Status:** PARTIAL (checks vend_transfer_id but no idempotency key)  
**Severity:** P1 - **Duplicate consignment risk**  
**Evidence:** No `Idempotency-Key` header on POST requests  
**Impact:** Retry creates duplicate consignments in Vend  
**Fix Effort:** M (2 hours)  
**Owner:** Backend Team  
**Blast Radius:** All Vend POST operations  
**Fix:**
```php
$idempotencyKey = hash('sha256', "transfer-{$transferId}-{$sessionId}");
$headers[] = "Idempotency-Key: {$idempotencyKey}";
```

---

### [P1-006] No Circuit Breaker for Vend API
**File:** `consignments/api/lightspeed.php`, `simple-upload-direct.php`  
**Status:** OPEN  
**Severity:** P1 - **Cascading failures**  
**Evidence:** Continuous retry attempts when Vend is down  
**Impact:** Resource exhaustion, long wait times  
**Fix Effort:** L (1 day - implement pattern)  
**Owner:** Backend Team  
**Blast Radius:** All external API calls  
**Fix:** Implement circuit breaker pattern
- CLOSED: Normal operation
- OPEN: Fast-fail after N failures (no requests sent)
- HALF-OPEN: Test if service recovered

---

### [P1-007] No Dead Letter Queue for Failed Uploads
**File:** `consignments/api/process-consignment-upload.php`  
**Status:** OPEN  
**Severity:** P1 - **Lost uploads**  
**Evidence:** Failed uploads have no permanent failure handling  
**Impact:** Silent data loss, no visibility into failures  
**Fix Effort:** M (4 hours)  
**Owner:** Backend Team  
**Blast Radius:** Upload reliability  
**Fix:**
1. Add `queue_failed_jobs` table
2. After max retries, INSERT INTO queue_failed_jobs
3. Admin UI to view/retry failed jobs
4. Alert on failure spike

---

### [P1-008] SSE No Heartbeat/Keepalive
**File:** `consignments/api/consignment-upload-progress.php`  
**Status:** OPEN  
**Severity:** P1 - **Connection timeouts**  
**Evidence:** No periodic ping to keep connection alive  
**Impact:** Proxies/LBs close idle connections, progress lost  
**Fix Effort:** S (30 min)  
**Owner:** Backend Team  
**Blast Radius:** Long-running uploads  
**Fix:**
```php
// Send heartbeat every 15 seconds
if (time() - $lastPing > 15) {
    echo ": heartbeat\n\n";
    flush();
    $lastPing = time();
}
```

---

### [P1-009] No Rollback on Partial Vend Product Upload Failure
**File:** `consignments/api/simple-upload-direct.php:200-215`  
**Status:** OPEN  
**Severity:** P1 - **Incomplete state**  
**Evidence:** If 5/10 products upload, consignment left incomplete  
**Impact:** Partial data in Vend, manual cleanup required  
**Fix Effort:** M (3 hours)  
**Owner:** Backend Team  
**Blast Radius:** Upload reliability  
**Fix Options:**
1. **All-or-nothing:** Delete consignment if any product fails
2. **Best-effort:** Mark failed products, allow retry
3. **Idempotent retry:** Retry only failed products

**Recommended:** Option 2 (best-effort)

---

### [P1-010] Function Redeclaration Conflicts
**File:** Multiple files define `getDatabaseConnection()`  
**Status:** MITIGATED (function_exists checks)  
**Severity:** P1 - **Fatal errors**  
**Evidence:**
```php
if (!function_exists('getDatabaseConnection')) {
    function getDatabaseConnection() { ... }
}
```

**Impact:** Fatal error if functions declared twice  
**Fix Effort:** M (2 hours - refactor)  
**Owner:** Backend Team  
**Blast Radius:** Include order dependencies  
**Permanent Fix:**
1. Move all shared functions to single `bootstrap.php`
2. Use classes/namespaces instead of global functions
3. Remove `function_exists` workarounds

---

### [P1-011] Redirect After Upload Goes to Wrong Page ✅ FIXED
**File:** `consignments/stock-transfers/js/pack.js:1911`  
**Status:** Fixed  
**Severity:** P1 - **Poor UX**  
**Evidence:**
```javascript
// OLD: window.location.href = '/modules/consignments/index.php';
// NEW: window.location.href = '/';
```

**Impact:** User sent to wrong page after upload  
**Fix Effort:** S (Already done)  
**Owner:** ✅ COMPLETED  

---

### [P1-012] No Vend Webhook Handler
**File:** N/A - missing entirely  
**Status:** OPEN  
**Severity:** P1 - **One-way sync only**  
**Evidence:** No webhook receiver for Vend updates  
**Impact:** Changes in Vend don't reflect in CIS  
**Fix Effort:** L (2 days)  
**Owner:** Backend Team + DevOps  
**Blast Radius:** Data synchronization  
**Fix:**
1. Create `/consignments/api/vend-webhook.php`
2. Validate webhook signature (HMAC)
3. Process events: consignment.updated, consignment.received
4. Update local state: queue_consignments, transfers
5. Log all events: queue_webhook_events table
6. Register webhook URL in Vend dashboard

---

## P2 - MEDIUM PRIORITY (1-3 months)

### [P2-001] God Function: getUniversalTransfer (216 lines)
**File:** `consignments/shared/functions/transfers.php:22-238`  
**Severity:** P2 - **Maintainability**  
**Fix Effort:** L (1 day)  
**Owner:** Backend Team  
**Fix:** Refactor into smaller, focused functions

---

### [P2-002] Mixed PHP/HTML Template (pack.php 1109 lines)
**File:** `consignments/stock-transfers/pack.php`  
**Severity:** P2 - **Testability**  
**Fix Effort:** L (2 days)  
**Owner:** Frontend Team  
**Fix:** Extract to proper MVC (Controller + View template)

---

### [P2-003] No Pagination on Large Queries
**File:** `consignments/shared/functions/transfers.php` (various)  
**Severity:** P2 - **Performance**  
**Fix Effort:** M (4 hours)  
**Owner:** Backend Team  
**Fix:** Add `LIMIT`, `OFFSET`, pagination metadata

---

### [P2-004] No Caching Layer
**File:** All data access functions  
**Severity:** P2 - **Performance**  
**Fix Effort:** L (1 week - Redis/Memcached)  
**Owner:** Infrastructure Team  
**Fix:** Add cache for outlet list, product catalog, etc.

---

### [P2-005] 1757-Line JavaScript File
**File:** `consignments/stock-transfers/js/pack.js`  
**Severity:** P2 - **Maintainability**  
**Fix Effort:** L (2 days)  
**Owner:** Frontend Team  
**Fix:** Split into modules: modal.js, sse.js, validation.js, etc.

---

### [P2-006] No TypeScript
**File:** All JavaScript files  
**Severity:** P2 - **Type Safety**  
**Fix Effort:** L (1 week - migration)  
**Owner:** Frontend Team  
**Fix:** Migrate to TypeScript, add strict mode

---

### [P2-007] No Dependency Injection
**File:** All classes  
**Severity:** P2 - **Testability**  
**Fix Effort:** L (2 weeks - refactor)  
**Owner:** Backend Team  
**Fix:** Introduce DI container (PHP-DI, Pimple)

---

### [P2-008] Direct Superglobal Access
**File:** All API endpoints  
**Severity:** P2 - **Testability**  
**Fix Effort:** M (1 day)  
**Owner:** Backend Team  
**Fix:** Wrap in Request/Response objects

---

### [P2-009] Inconsistent Error Envelopes
**File:** Various API responses  
**Severity:** P2 - **API consistency**  
**Fix Effort:** M (4 hours)  
**Owner:** Backend Team  
**Fix:** Standardize on single format:
```json
{
  "success": bool,
  "data": {},
  "error": {"code": "", "message": "", "details": []},
  "meta": {"request_id": "", "timestamp": ""}
}
```

---

### [P2-010] No Structured Logging
**File:** All files  
**Severity:** P2 - **Observability**  
**Fix Effort:** M (1 day)  
**Owner:** Backend Team  
**Fix:** Use Monolog with JSON formatter + context

---

### [P2-011] No Health/Readiness Endpoints
**File:** N/A - missing  
**Severity:** P2 - **Ops**  
**Fix Effort:** S (2 hours)  
**Owner:** Backend Team  
**Fix:** Add `/health` (liveness) and `/ready` (readiness with DB check)

---

### [P2-012] No Retry Logic with Exponential Backoff
**File:** `consignments/api/lightspeed.php`, `simple-upload-direct.php`  
**Severity:** P2 - **Reliability**  
**Fix Effort:** M (3 hours)  
**Owner:** Backend Team  
**Fix:** Add retry with exp backoff + jitter for transient failures

---

### [P2-013] No Request ID / Correlation ID
**File:** All API handlers  
**Severity:** P2 - **Observability**  
**Fix Effort:** M (2 hours)  
**Owner:** Backend Team  
**Fix:** Generate UUID per request, pass through logs/responses

---

### [P2-014] No Validation Layer
**File:** All API endpoints  
**Severity:** P2 - **Security + Data Quality**  
**Fix Effort:** L (1 week)  
**Owner:** Backend Team  
**Fix:** Use validation library (Respect/Validation, Symfony Validator)

---

### [P2-015] No API Versioning
**File:** All API endpoints  
**Severity:** P2 - **BC Breaks**  
**Fix Effort:** M (4 hours)  
**Owner:** Backend Team  
**Fix:** Add `/api/v1/` prefix to all endpoints

---

### [P2-016] No Database Migrations Tool
**File:** Manual SQL files  
**Severity:** P2 - **Deployment Risk**  
**Fix Effort:** M (1 day)  
**Owner:** DBA Team  
**Fix:** Use Phinx or similar with version tracking

---

### [P2-017] No Transaction Isolation Level Control
**File:** All database transactions  
**Severity:** P2 - **Concurrency**  
**Fix Effort:** S (1 hour)  
**Owner:** Backend Team  
**Fix:** Explicitly set isolation level per transaction criticality

---

### [P2-018] No Query Profiling
**File:** All database queries  
**Severity:** P2 - **Performance**  
**Fix Effort:** M (4 hours)  
**Owner:** Backend Team  
**Fix:** Add query timer logging, aggregate slow queries

---

## P3 - LOW PRIORITY (Nice-to-Have, > 3 months)

### [P3-001] No i18n Support
**Fix Effort:** L (2 weeks)  
**Owner:** Frontend Team  

### [P3-002] No Dark Mode
**Fix Effort:** M (1 day)  
**Owner:** Frontend Team  

### [P3-003] No A11y Audit
**Fix Effort:** M (2 days)  
**Owner:** Frontend Team  

### [P3-004] No API Documentation (OpenAPI/Swagger)
**Fix Effort:** M (1 day)  
**Owner:** Backend Team  

### [P3-005] No Unit Tests
**Fix Effort:** L (2 weeks)  
**Owner:** All Teams  

### [P3-006] No Integration Tests
**Fix Effort:** L (1 week)  
**Owner:** QA Team  

### [P3-007] No E2E Tests
**Fix Effort:** L (1 week)  
**Owner:** QA Team  

### [P3-008] No CI/CD Pipeline
**Fix Effort:** M (3 days)  
**Owner:** DevOps  

### [P3-009] No Monitoring/Alerting
**Fix Effort:** M (2 days)  
**Owner:** DevOps  

---

## Summary Statistics

| Severity | Count | Estimated Effort |
|----------|-------|------------------|
| P0 | 8 | 18 hours |
| P1 | 12 | 15 days |
| P2 | 18 | 6 weeks |
| P3 | 9 | 3 months |

**Total Technical Debt:** ~4.5 months of work

---

## Dependency Order (Fix These First)

1. **P0-004** Secrets to .env (blocks secure deployments)
2. **P0-001** Authorization header (blocks all Vend calls)
3. **P0-005** CSRF protection (blocks secure launch)
4. **P0-006** SQL injection audit (blocks secure launch)
5. **P0-007** Rate limiting (blocks DoS protection)
6. **P1-003** Progress tracking writes (enables real SSE)
7. **P1-004** Foreign keys (enables data integrity)
8. **P1-005** Idempotency keys (enables safe retries)

---

**Next Review:** After P0 fixes deployed  
**Owner Assignment:** Pending team capacity review
