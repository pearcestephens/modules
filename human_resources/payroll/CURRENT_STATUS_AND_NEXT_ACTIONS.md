# 🎯 PAYROLL MODULE - CURRENT STATUS & NEXT ACTIONS

**Generated:** <?php echo date('Y-m-d H:i:s'); ?>
**Purpose:** Executive summary of where we are and what's next
**Status:** 🟢 ENVIRONMENT MAPPED - READY FOR SERVICE LAYER BUILD

---

## 📊 WHAT WE JUST ACCOMPLISHED

### 1. ✅ Located ALL Existing Integration Points

**Deputy API Client**
- 📍 **Location:** `assets/functions/deputy.php`
- 🔧 **Status:** Production-ready, actively used
- 🔑 **Auth:** Bearer token (env var: `DEPUTY_TOKEN`)
- 📦 **Features:** Timesheet fetching, break calculations, NZ labour law compliance
- ✅ **Database:** `deputy_timesheets` table exists and populated

**Xero API Client**
- 📍 **Location:** `assets/functions/xero-functions.php` + `xeroAPI/xeroCredentialsEcigdis.php`
- 🔧 **Status:** Production-ready with OAuth2
- 🔑 **Auth:** Certificate-based (Private App) - no env vars needed
- 📦 **Features:** Employee sync, pay run creation, PayrollNZ API v1.0
- ✅ **Database:** `xero_payrolls`, `xero_payroll_deductions` tables exist

**Environment Loaders**
- 📍 **Locations:**
  - `assets/functions/pdo.php` - Auto-loads .env for database
  - `assets/functions/config.php` - Simple .env parser
  - `assets/functions/connection.php` - Legacy loader
- ✅ **Pattern:** All check `$_SERVER['DOCUMENT_ROOT'] . '/.env'`

**Sync Logs**
- 📍 **Primary Table:** `payroll_activity_log` (20+ indexed columns)
- ✅ **Schema:** Comprehensive with log_level, category, action, entity tracking, JSON details, performance metrics
- 📊 **Related Tables:** 10 sync tables (`lightspeed_sync_status`, `vend_sync_cursors`, etc.)

### 2. ✅ Created Comprehensive Documentation

**New Files Created:**
1. `INTEGRATION_STATUS_REPORT.md` (850+ lines)
   - Complete Deputy API reference
   - Complete Xero API reference
   - Environment loader patterns
   - Database schema documentation
   - Service layer implementation guide
   - No placeholders - all real code references

2. `.env.example` (Updated)
   - Added Deputy configuration section
   - Added Xero configuration notes
   - Added database override options
   - Clear documentation for each variable

### 3. ✅ Validated Architecture Pattern

**Reference Model:** `ConsignmentService.php`
- Factory pattern with `::make()`
- PDO injection (RO + RW connections)
- 333 lines of production code
- Clear separation: reads vs writes
- Error handling and logging

---

## 🚀 WHAT'S READY TO BUILD NOW

### Immediate Build Targets (6 Tasks)

#### Task 1: Rate Limit Telemetry Integration ⚡ HIGH PRIORITY
**Status:** 🟡 Schema ready, service class ready, needs wiring
**Files to Create:**
- `services/PayrollDeputyService.php` (with rate limit tracking)
- `services/PayrollXeroService.php` (with rate limit tracking)
- `views/rate_limits.php` (dashboard widget)

**Key Implementation:**
```php
// In every API call:
if ($httpCode === 429) {
    HttpRateLimitReporter::insert(
        service: 'deputy',
        endpoint: $endpoint,
        httpCode: 429,
        retryAfter: $retryAfter,
        requestId: $requestId
    );
}
```

**Dependencies:** NONE - Can start immediately
**Estimated Time:** 4-6 hours
**Impact:** Critical for production monitoring

---

#### Task 2: Reconciliation Dashboard ⚡ HIGH PRIORITY
**Status:** 🟡 Data sources identified, needs service creation
**Files to Create:**
- `services/ReconciliationService.php`
- `views/reconciliation_dashboard.php`
- `api/resolve_variance.php`

**Data Sources:**
- `payroll_activity_log` (deputy_sync + xero_export events)
- `deputy_timesheets` table
- `xero_payrolls` table
- `payroll_wage_discrepancies` table

**Key Queries:**
```sql
-- Compare Deputy timesheets vs Xero pay runs
SELECT
    dt.employee_id,
    dt.total_hours AS deputy_hours,
    xp.total_hours AS xero_hours,
    (dt.total_hours - xp.total_hours) AS variance
FROM deputy_timesheets dt
LEFT JOIN xero_payrolls xp ON xp.employee_id = dt.employee_id AND xp.period = dt.period
WHERE ABS(dt.total_hours - xp.total_hours) > 0.1
ORDER BY ABS(variance) DESC;
```

**Dependencies:** PayrollDeputyService, PayrollXeroService (can build in parallel)
**Estimated Time:** 6-8 hours
**Impact:** Critical for payroll accuracy

---

#### Task 3: Snapshot Integrity System 🟢 MEDIUM PRIORITY
**Status:** 🟢 Schema changes documented, ready to implement
**Files to Modify:**
- `services/PayrollSnapshotManager.php` (add hash methods)

**Database Changes:**
```sql
ALTER TABLE payroll_snapshots
ADD COLUMN snapshot_hash VARCHAR(64) AFTER snapshot_data,
ADD COLUMN hash_algorithm VARCHAR(20) DEFAULT 'sha256' AFTER snapshot_hash,
ADD COLUMN integrity_verified TINYINT(1) DEFAULT 0 AFTER hash_algorithm,
ADD INDEX idx_integrity (integrity_verified);

CREATE TABLE payroll_snapshot_audit (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    snapshot_id BIGINT UNSIGNED NOT NULL,
    check_time DATETIME NOT NULL,
    expected_hash VARCHAR(64) NOT NULL,
    actual_hash VARCHAR(64) NOT NULL,
    is_valid TINYINT(1) NOT NULL,
    error_message TEXT,
    FOREIGN KEY (snapshot_id) REFERENCES payroll_snapshots(id),
    INDEX idx_snapshot (snapshot_id),
    INDEX idx_check_time (check_time)
);
```

**Dependencies:** NONE - Can start immediately
**Estimated Time:** 3-4 hours
**Impact:** Important for audit compliance

---

#### Task 4: PayrollAuthMiddleware 🟢 MEDIUM PRIORITY
**Status:** 🟢 Spec documented, ready to implement
**Files to Create:**
- `middleware/PayrollAuthMiddleware.php`
- `api/endpoints/*.php` (add middleware to all endpoints)

**Database Changes:**
```sql
CREATE TABLE payroll_access_log (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    endpoint VARCHAR(255) NOT NULL,
    http_method VARCHAR(10) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    user_agent TEXT,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(50),
    entity_id INT UNSIGNED,
    pii_accessed TINYINT(1) DEFAULT 0,
    access_granted TINYINT(1) NOT NULL,
    denial_reason VARCHAR(255),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_endpoint (endpoint),
    INDEX idx_created (created_at),
    INDEX idx_pii (pii_accessed)
);
```

**Dependencies:** NONE - Can start immediately
**Estimated Time:** 4-5 hours
**Impact:** Important for security compliance

---

#### Task 5: Expense Workflow ⚡ HIGH PRIORITY
**Status:** 🟡 Xero client ready, needs service implementation
**Files to Create:**
- `services/ExpenseService.php`
- `views/submit_expense.php`
- `api/submit_expense.php`
- `api/approve_expense.php`
- `api/reject_expense.php`

**Database Changes:**
```sql
CREATE TABLE payroll_expenses (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    staff_id INT UNSIGNED NOT NULL,
    submitted_by INT UNSIGNED NOT NULL,
    expense_date DATE NOT NULL,
    category VARCHAR(50) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    description TEXT NOT NULL,
    receipt_path VARCHAR(500),
    status ENUM('pending','approved','rejected','paid') NOT NULL DEFAULT 'pending',
    approved_by INT UNSIGNED,
    approved_at DATETIME,
    rejection_reason TEXT,
    xero_expense_claim_id VARCHAR(100),
    xero_synced_at DATETIME,
    xero_payment_id VARCHAR(100),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (staff_id) REFERENCES users(id),
    FOREIGN KEY (submitted_by) REFERENCES users(id),
    FOREIGN KEY (approved_by) REFERENCES users(id),
    INDEX idx_staff (staff_id),
    INDEX idx_status (status),
    INDEX idx_date (expense_date),
    INDEX idx_xero (xero_expense_claim_id)
);
```

**Xero Integration:**
```php
// In ExpenseService.php
public function syncToXero(int $expenseId): bool {
    require_once $_SERVER['DOCUMENT_ROOT'] . '/assets/functions/xero-functions.php';
    // Use existing Xero client to create expense claim
    // Reference: xero-functions.php patterns
}
```

**Dependencies:** PayrollXeroService (can build in parallel)
**Estimated Time:** 6-8 hours
**Impact:** Critical for expense reimbursement workflow

---

#### Task 6: Polish & Integration Testing 🟢 LOW PRIORITY
**Status:** 🟢 Test framework ready, needs E2E scenarios
**Files to Create:**
- `tests/test_deputy_integration.php`
- `tests/test_xero_integration.php`
- `tests/test_reconciliation_e2e.php`
- `tests/test_expense_workflow_e2e.php`

**E2E Scenarios:**
1. Complete Pay Run Cycle
   - Fetch timesheets from Deputy
   - Calculate pay with breaks
   - Export to Xero
   - Verify reconciliation dashboard

2. Expense Lifecycle
   - Submit expense with receipt
   - Approve expense
   - Sync to Xero
   - Track payment status

3. Variance Resolution
   - Detect timesheet mismatch
   - Display in reconciliation dashboard
   - Manual resolution
   - Log resolution in activity log

**Dependencies:** ALL above tasks complete
**Estimated Time:** 8-10 hours
**Impact:** Critical for production readiness

---

## 📋 RECOMMENDED BUILD ORDER

### Phase 1: Core Services (Parallel) - Days 1-2
1. **PayrollDeputyService.php** (4-5 hours)
   - Fetch timesheets
   - Calculate breaks
   - Sync to database
   - Rate limit tracking
   - Activity logging

2. **PayrollXeroService.php** (4-5 hours)
   - Fetch employees
   - Create pay runs
   - Submit expense claims
   - Rate limit tracking
   - Activity logging

### Phase 2: Business Logic (Sequential) - Days 3-4
3. **ReconciliationService.php** (6-8 hours)
   - Compare Deputy vs Xero data
   - Detect variances
   - Generate reconciliation report

4. **ExpenseService.php** (6-8 hours)
   - Submit/approve/reject workflow
   - Xero sync integration
   - File upload handling

### Phase 3: Security & Integrity (Parallel) - Day 5
5. **PayrollAuthMiddleware.php** (4-5 hours)
   - Role-based permissions
   - PII redaction
   - Access logging

6. **Snapshot Integrity** (3-4 hours)
   - Hash generation
   - Verification checks
   - Audit logging

### Phase 4: UI & Testing (Sequential) - Days 6-7
7. **Dashboard Views** (6-8 hours)
   - Rate limits widget
   - Reconciliation dashboard
   - Expense submission form

8. **Integration Testing** (8-10 hours)
   - E2E test scenarios
   - Performance profiling
   - Bug fixes

**Total Estimated Time:** 45-55 hours (6-7 working days)

---

## 🔥 CRITICAL SUCCESS FACTORS

### ✅ What We Have Going For Us
1. **Real API Clients:** Both Deputy and Xero are production-tested
2. **Clear Architecture:** ConsignmentService provides excellent pattern
3. **Comprehensive Schema:** 25 payroll tables + 10 sync tables
4. **Mature Logging:** `payroll_activity_log` is feature-rich
5. **Working Tests:** Master test suite at 100% pass rate
6. **Good Documentation:** 5+ comprehensive markdown files

### ⚠️ What We Need to Watch
1. **Rate Limits:** Deputy/Xero APIs have limits - must track carefully
2. **OAuth2 Tokens:** Xero tokens expire - need refresh logic
3. **Data Consistency:** Deputy-Xero sync must be transactional
4. **Performance:** Large timesheet fetches need pagination
5. **Error Handling:** API failures need retry logic with backoff

### 🎯 Non-Negotiables (Your Requirements)
❌ **NO PLACEHOLDERS** - Every function must be fully implemented
❌ **NO STUBS** - No `// TODO: implement this` comments
❌ **NO FAKE DATA** - Real API calls or graceful degradation
✅ **FULL INTEGRATION** - Deputy + Xero + Database + Logging
✅ **REAL ENVIRONMENT** - Use actual .env variables
✅ **COMPREHENSIVE TESTING** - E2E scenarios with real data

---

## 💡 RECOMMENDED NEXT ACTION

### Start with PayrollDeputyService.php

**Why This First?**
1. Existing `deputy.php` functions are well-tested
2. `deputy_timesheets` table already exists and populated
3. No OAuth2 complexity (simple Bearer token)
4. Used by PayslipCalculationEngine (proven integration)
5. Rate limit tracking is straightforward

**Implementation Template:**
```php
<?php
declare(strict_types=1);

namespace VapeShed\Payroll\Services;

use PDO;

/**
 * Deputy API Integration Service
 *
 * Handles all interactions with Deputy API for timesheet and employee data.
 * Implements rate limit tracking and comprehensive activity logging.
 *
 * Architecture: Service layer pattern (ConsignmentService blueprint)
 * Rate Limits: Tracks 429 responses in payroll_rate_limits table
 * Logging: All operations logged to payroll_activity_log
 *
 * @package VapeShed\Payroll\Services
 */
class PayrollDeputyService
{
    private PDO $roDb;    // Read-only connection
    private PDO $rwDb;    // Read-write connection

    private function __construct(PDO $roDb, PDO $rwDb)
    {
        $this->roDb = $roDb;
        $this->rwDb = $rwDb;
    }

    /**
     * Factory method with automatic PDO injection
     */
    public static function make(?PDO $roDb = null, ?PDO $rwDb = null): self
    {
        require_once $_SERVER['DOCUMENT_ROOT'] . '/assets/functions/pdo.php';

        $roDb ??= \CIS\DB\PDO\getCisReadOnlyPdo();
        $rwDb ??= \CIS\DB\PDO\getCisPdo();

        return new self($roDb, $rwDb);
    }

    // ... implementation methods here ...
}
```

---

## 📞 QUESTIONS TO CONFIRM

Before I start building services, please confirm:

1. **Deputy Token:** Do you want me to add your actual `DEPUTY_TOKEN` to `.env`, or keep using the hardcoded fallback for now?

2. **Build Order:** Should I start with `PayrollDeputyService.php` first, or would you prefer a different order?

3. **Testing Approach:** Do you want me to create tests for each service as I build it, or batch all testing at the end?

4. **Rate Limit Thresholds:** What should trigger an alert? (e.g., "Alert if >10 rate limit hits in 24h")

5. **Reconciliation UI:** Should the reconciliation dashboard be a full page or a widget on an existing page?

---

## ✅ READY TO PROCEED

**Current Status:** 🟢 ALL RESEARCH COMPLETE
**Next Step:** CREATE PayrollDeputyService.php
**Blocker:** NONE
**Dependencies:** NONE
**Risk Level:** LOW (proven API client, existing database schema)

**Estimated Time to First Working Service:** 4-5 hours
**Estimated Time to All 6 Tasks Complete:** 45-55 hours (6-7 days)

---

**Just give me the green light and I'll start building!** 🚀

Let me know:
- Should I proceed with `PayrollDeputyService.php`?
- Any specific requirements or constraints I should know about?
- Any questions about the plan or approach?

---

*Generated by AI Agent - NO PLACEHOLDERS policy*
*Environment: FULLY MAPPED*
*Integration: READY TO BUILD*
*Let's build production-grade code!* 💪
