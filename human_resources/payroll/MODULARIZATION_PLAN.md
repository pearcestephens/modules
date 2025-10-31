# Payroll Module Modularization Plan

**Date:** October 27, 2025
**Target:** Transform 3,328-line `/public_html/payroll-process.php` into professional module structure
**Goal:** Improve maintainability, testability, and separation of concerns while preserving all functionality

---

## Current State Analysis

### File Structure (payroll-process.php - 3,329 lines)

| Section | Lines | Purpose | Concerns |
|---------|-------|---------|----------|
| **Config & Setup** | 1-65 | Error handling, includes, timezone, constants | Environment setup |
| **Vend Functions** | 66-213 | Snapshot scanning, loading, register/payment resolution | Data access |
| **JSON Helpers** | 214-242 | Response formatting, CSRF validation | API utilities |
| **Deputy Helpers** | 243-636 | Timesheet parsing, matching, updating | Business logic |
| **AJAX Handlers** | 637-802 | Amendment actions (accept, decline, delete) | **MUST RUN BEFORE HTML** |
| **OAuth2 (disabled)** | 803-840 | Commented out OAuth2 setup | Legacy code |
| **Cache System** | 841-900 | File-based caching with TTL | Infrastructure |
| **Main Logic** | 901-1700 | Vend payments, Xero push, pay run creation | Core functionality |
| **HTML Rendering** | 1701-2700 | Tabs, tables, forms, modals | UI presentation |
| **JavaScript** | 2701-3100 | AJAX calls, pipeline orchestration, UI handlers | Client-side logic |
| **CSS** | 3101-3329 | Styling for payroll, leave, amendments | Presentation |

### Key Functions Identified

**Vend Operations (Lines 66-213):**
- `__vend_snapshot_dirs()` - Build snapshot directory list
- `__vend_scan_snapshots()` - Scan for saved snapshots
- `__vend_load_snapshot_by_run($runId)` - Load specific snapshot
- `vend_resolve_register_id($name)` - Map register name to UUID
- `vend_resolve_payment_type($name)` - Map payment type to UUID

**Deputy Operations (Lines 243-636):**
- `_parse_dtlocal($value)` - Parse datetime-local input
- `_pick_best_deputy_row($rows, $start, $end)` - Find best matching timesheet
- `_deputy_update_timesheet($row, $start, $end, $breakMin)` - Update Deputy timesheet
- `_sync_to_deputy_from_amendment($amend, $start, $end, $multiShiftIntent)` - Sync amendment to Deputy

**AJAX Actions (Lines 637-802):**
- POST: `markTimesheetAmendmentComplete` - Accept amendment, sync to Deputy
- POST: `declineTimesheetAmendment` - Decline with reason
- POST: `softDeleteTimesheetAmendment` - Archive amendment
- POST: `deleteTimesheetAmendment` - Hard delete

**Cache Operations (Lines 841-900):**
- `getCached($key, $ttl)` - Retrieve cached data
- `putCached($key, $data, $ttl)` - Store data with expiry

**Main Operations (Lines 901-1700):**
- GET: `createAccountPayments` - Allocate Vend account payments (with snapshot support)
- POST: `createXeroPayRun` - Create Xero pay run only
- POST: `pushPayrollToXeroBatch` - Push selected staff to Xero
- GET: `pushPayrollToXero` - Legacy full push
- GET: `pushPayrollToXeroSingle` - Single staff push

---

## Target Module Structure

```
modules/human_resources/payroll/
├── index.php                       # Module entry point (router)
├── MODULE_INFO.json                # Module metadata
├── README.md                       # Module overview
├── ARCHITECTURE.md                 # Design documentation
├── MODULARIZATION_PLAN.md          # This file
│
├── controllers/
│   ├── PayrollController.php       # Main payroll orchestration
│   ├── AmendmentController.php     # Timesheet amendment handling
│   └── AjaxController.php          # AJAX request routing
│
├── models/
│   ├── PayrollRun.php              # Pay run data model
│   ├── VendPayment.php             # Vend payment allocation model
│   ├── DeputyTimesheet.php         # Deputy timesheet model
│   └── LeaveRequest.php            # Leave request model
│
├── services/
│   ├── VendService.php             # Vend API & snapshot operations
│   ├── DeputyService.php           # Deputy API & timesheet operations
│   ├── XeroService.php             # Xero API wrapper (if new)
│   └── CacheService.php            # Caching layer
│
├── lib/
│   ├── PayrollSnapshotManager.php  # Already exists - complete snapshot system
│   └── PayrollHelpers.php          # Utility functions
│
├── api/
│   ├── amendments.php              # Amendment AJAX endpoints
│   ├── vend-payments.php           # Vend payment operations
│   └── xero-operations.php         # Xero push operations
│
├── views/
│   ├── main.php                    # Main payroll page container
│   ├── partials/
│   │   ├── header.php              # Module header
│   │   ├── tabs.php                # Tab navigation
│   │   ├── payroll_table.php       # Staff payroll table
│   │   ├── leave_tab.php           # Leave requests tab
│   │   ├── amendments_tab.php      # Timesheet amendments tab
│   │   ├── job_status_card.php     # Progress card UI
│   │   └── footer.php              # Module footer
│   └── modals/
│       ├── amendment_modal.php     # Amendment details modal
│       ├── snapshot_picker.php     # Snapshot selection modal
│       └── resume_run_modal.php    # Resume incomplete run modal
│
├── assets/
│   ├── js/
│   │   ├── payroll.js              # Main payroll JavaScript
│   │   ├── amendments.js           # Amendment-specific JS
│   │   └── pipeline.js             # Pipeline orchestration
│   └── css/
│       ├── payroll.css             # Main styles
│       └── amendments.css          # Amendment-specific styles
│
├── config/
│   ├── constants.php               # Module constants
│   ├── routes.php                  # AJAX/API routing table
│   └── settings.php                # Module settings
│
└── tests/
    ├── unit/
    │   ├── VendServiceTest.php
    │   ├── DeputyServiceTest.php
    │   └── CacheServiceTest.php
    ├── integration/
    │   ├── PayrollControllerTest.php
    │   └── AmendmentControllerTest.php
    └── e2e/
        └── FullPayrollFlowTest.php
```

---

## Migration Strategy

### Phase 1: Foundation (Structure & Services)

**Goal:** Create module structure and extract service classes

**Steps:**

1. **Create Directory Structure**
   ```bash
   mkdir -p modules/human_resources/payroll/{controllers,models,services,lib,api,views/{partials,modals},assets/{js,css},config,tests/{unit,integration,e2e}}
   ```

2. **Extract VendService** (Lines 66-213)
   - Methods:
     ```php
     class VendService {
         public function getSnapshotDirectories(): array;
         public function scanSnapshots(): array;
         public function loadSnapshotByRun(?string $runId): ?array;
         public function resolveRegisterId(string $name): ?string;
         public function resolvePaymentType(string $name): ?string;
     }
     ```
   - Dependencies: File system access, environment variables
   - Tests: Unit tests for each method

3. **Extract DeputyService** (Lines 243-636)
   - Methods:
     ```php
     class DeputyService {
         public function parseDateTime(?string $value): array;
         public function pickBestTimesheetRow(array $rows, int $startTs, int $endTs): ?array;
         public function updateTimesheet(array $row, int $startTs, int $endTs, ?int $breakMin = null): array;
         public function createTimesheet(int $employeeId, int $startTs, int $endTs, int $breakMin, int $ouId, string $comment): array;
         public function syncAmendmentToDeputy(object $amendment, int $startTs, int $endTs, ?array $multiShiftIntent = null): array;
     }
     ```
   - Dependencies: Deputy API functions, PayrollSnapshotManager for logging
   - Tests: Unit tests with mocked Deputy API

4. **Extract CacheService** (Lines 841-900)
   - Methods:
     ```php
     class CacheService {
         public function __construct(string $cacheDir);
         public function get(string $key, int $ttl = 3600): mixed;
         public function put(string $key, mixed $data, int $ttl = 3600): bool;
         public function bust(string $pattern = '*'): int;
         public function getCacheDir(): string;
     }
     ```
   - Dependencies: File system access
   - Tests: Unit tests for cache operations

5. **Move PayrollSnapshotManager**
   - Copy `/private_html/modules/payroll_snapshot/PayrollSnapshotManager.php` to `lib/`
   - Update namespace (if needed)
   - Ensure all dependencies are included

**Acceptance Criteria:**
- [ ] All service classes created
- [ ] Unit tests pass for each service
- [ ] Services have no side effects (pure functions where possible)
- [ ] PSR-12 coding standards followed

---

### Phase 2: API Layer (AJAX Endpoints)

**Goal:** Extract AJAX handlers into dedicated API files

**Steps:**

1. **Create api/amendments.php** (Lines 637-802)
   - Extract amendment actions:
     - `markTimesheetAmendmentComplete`
     - `declineTimesheetAmendment`
     - `softDeleteTimesheetAmendment`
     - `deleteTimesheetAmendment`
   - Use AmendmentController
   - Return consistent JSON responses
   - **CRITICAL:** Must execute before ANY HTML output

2. **Create api/vend-payments.php**
   - Extract: `createAccountPayments` GET/POST handler
   - Use VendService, PayrollSnapshotManager
   - Handle snapshot selection
   - Return JSON with progress updates

3. **Create api/xero-operations.php**
   - Extract: `createXeroPayRun`, `pushPayrollToXeroBatch`, `pushPayrollToXeroSingle`
   - Use XeroService (or existing xero-payruns.php)
   - Return JSON responses

4. **Create AjaxController.php**
   - Route AJAX requests to appropriate API files
   - Validate CSRF tokens
   - Handle errors consistently
   - Log all operations

**Routing Table (config/routes.php):**
```php
return [
    'POST' => [
        'markTimesheetAmendmentComplete' => 'api/amendments.php',
        'declineTimesheetAmendment'      => 'api/amendments.php',
        'softDeleteTimesheetAmendment'   => 'api/amendments.php',
        'deleteTimesheetAmendment'       => 'api/amendments.php',
        'createXeroPayRun'               => 'api/xero-operations.php',
        'pushPayrollToXeroBatch'         => 'api/xero-operations.php',
    ],
    'GET' => [
        'createAccountPayments'          => 'api/vend-payments.php',
        'pushPayrollToXero'              => 'api/xero-operations.php',
        'pushPayrollToXeroSingle'        => 'api/xero-operations.php',
        'listVendSnapshots'              => 'api/vend-payments.php',
        'debugVendSnapshots'             => 'api/vend-payments.php',
    ],
];
```

**Acceptance Criteria:**
- [ ] All AJAX endpoints working
- [ ] Consistent JSON response format
- [ ] CSRF validation on all POST requests
- [ ] Detailed error logging
- [ ] No HTML output before JSON

---

### Phase 3: Controllers & Models

**Goal:** Create controller layer and data models

**Steps:**

1. **Create PayrollController.php**
   - Method: `run()` - Main orchestration
     - Load Deputy timesheets
     - Load Xero employees
     - Match staff to Xero
     - Calculate balances, bonuses, leave
     - Prepare data for view
   - Method: `createAccountPayments()` - Vend allocation
     - Use VendService, PayrollSnapshotManager
     - Handle snapshot selection
     - Allocate payments to sales
   - Method: `pushToXero()` - Single/batch push
     - Use existing xero-payruns.php
     - Integrate PayrollSnapshotManager

2. **Create AmendmentController.php**
   - Method: `accept($id, $newStart, $newEnd, $updateHours, $multiShiftIntent)`
   - Method: `decline($id, $reason)`
   - Method: `delete($id)`
   - Method: `list($filter)`
   - Use DeputyService for syncing

3. **Create Models**
   - PayrollRun: Represent a pay run instance
   - VendPayment: Represent a Vend payment allocation
   - DeputyTimesheet: Represent a timesheet record
   - LeaveRequest: Represent a leave request

**Acceptance Criteria:**
- [ ] Controllers handle business logic
- [ ] Models represent data structures
- [ ] Controllers use services (no direct API calls)
- [ ] Error handling in all controller methods

---

### Phase 4: Views & Assets

**Goal:** Separate presentation from logic

**Steps:**

1. **Extract HTML** (Lines 1701-2700)
   - Create `views/main.php` - Outer container
     ```php
     <?php
     // Views should receive prepared data as variables
     // No business logic in views

     include 'partials/header.php';
     include 'partials/tabs.php';
     ?>

     <div class="tab-content">
         <?php include 'partials/payroll_table.php'; ?>
         <?php include 'partials/leave_tab.php'; ?>
         <?php include 'partials/amendments_tab.php'; ?>
     </div>

     <?php include 'partials/footer.php'; ?>
     ```

2. **Create View Partials**
   - `payroll_table.php` - Staff table with balances, actions
     - Expects: `$userObjectArray`, `$outstandingStaffAccounts`, `$totalBonuses`
   - `leave_tab.php` - Leave requests grouped by status
     - Expects: `$currentlyOnLeave`, `$futurePending`, `$priorWeekLeave`
   - `amendments_tab.php` - Timesheet amendments table
     - Expects: `$timesheetAmendments`, `$timesheetAmendmentsHistoric`, `$cycleStartTs`, `$cycleEndTs`
   - `job_status_card.php` - Progress UI
     - Reusable progress card

3. **Extract JavaScript** (Lines 2701-3100)
   - Create `assets/js/payroll.js`:
     ```javascript
     // Global helpers
     function postJSON(url, data, callback) { ... }
     function showJob(title) { ... }
     function appendLog(line) { ... }
     function jobDone(ok, msg) { ... }

     // Pipeline functions
     function createAccountPayments(mode, snapshotRunId) { ... }
     function createPayRunOnly() { ... }
     function runFullPayrollPipeline() { ... }

     // Staff selection
     function toggleStaffSelection() { ... }
     function runSelectedPayroll() { ... }
     ```
   - Create `assets/js/amendments.js`:
     ```javascript
     function acceptAmendment(id, options) { ... }
     function declineAmendment(id, reason) { ... }
     function deleteAmendment(id) { ... }
     function filterAmendments(criteria) { ... }
     ```

4. **Extract CSS** (Lines 3101-3329)
   - Create `assets/css/payroll.css`:
     ```css
     /* Use CSS variables for colors */
     :root {
         --payroll-primary: #0d6efd;
         --payroll-success: #198754;
         --payroll-danger: #dc3545;
         --payroll-border: #dee2e6;
     }

     #payroll { font-size: 0.875rem; }
     .payroll-actions { display: flex; gap: .5rem; }
     /* ... */
     ```
   - Create `assets/css/amendments.css`:
     ```css
     .amnd-summary .badge { font-weight: 600; }
     .amnd-table td, .amnd-table th { font-size: .9rem; }
     /* ... */
     ```

**Acceptance Criteria:**
- [ ] Views contain no business logic
- [ ] Views receive all data as variables
- [ ] JavaScript is modular and maintainable
- [ ] CSS uses variables for theming
- [ ] All assets loaded correctly

---

### Phase 5: Integration

**Goal:** Connect all components and integrate PayrollSnapshotManager

**Steps:**

1. **Create Module Entry Point** (index.php)
   ```php
   <?php
   declare(strict_types=1);

   // Module bootstrap
   require_once __DIR__ . '/config/constants.php';
   require_once __DIR__ . '/config/routes.php';

   // Initialize services
   $cacheService = new CacheService(BASE_PATH . 'var/cache/payroll');
   $vendService = new VendService();
   $deputyService = new DeputyService();
   $snapshotManager = new PayrollSnapshotManager();

   // Routing
   $ajaxController = new AjaxController($routes);

   // Check if AJAX request
   if ($ajaxController->isAjaxRequest()) {
       // CRITICAL: Execute AJAX before ANY output
       $ajaxController->handleRequest();
       exit; // Always exit after AJAX
   }

   // Page load: Initialize controller
   $payrollController = new PayrollController(
       $vendService,
       $deputyService,
       $cacheService,
       $snapshotManager
   );

   // Run main logic
   $data = $payrollController->run();

   // Render view
   require_once __DIR__ . '/views/main.php';
   ```

2. **Integrate PayrollSnapshotManager**
   - In PayrollController->run():
     ```php
     public function run(): array {
         // Start pay run tracking
         if (isset($_GET['startPayRun']) || !$_SESSION['payroll_run_id'] ?? null) {
             $runId = $this->snapshotManager->startPayRun();
             $_SESSION['payroll_run_id'] = $runId;
             $_SESSION['payroll_run_started'] = time();
         }

         // Set global for xero-payruns.php
         global $currentRunId;
         $currentRunId = $_SESSION['payroll_run_id'] ?? null;

         // Capture pre-load snapshot
         $this->snapshotManager->captureSnapshot(
             $runId,
             $vendAccountPayments = null,
             $deputyTimesheets,
             $xeroEmployees,
             $xeroPayslips = null
         );

         // ... rest of logic
     }
     ```
   - In VendService->allocatePayments():
     ```php
     // Use snapshot logging
     $this->snapshotManager->logAllocation($user, $allocationDetails);
     $this->snapshotManager->logError($user, $errorMessage, $context);
     ```

3. **Update Legacy Entry Point**
   - Modify `/public_html/payroll-process.php`:
     ```php
     <?php
     /**
      * Payroll Process - Legacy Entry Point
      *
      * This file is kept for backward compatibility.
      * All logic has been moved to modules/human_resources/payroll/
      *
      * @deprecated Use modules/human_resources/payroll/index.php directly
      */

     // Forward to new module
     require_once __DIR__ . '/modules/human_resources/payroll/index.php';
     ```

**Acceptance Criteria:**
- [ ] Module entry point handles routing
- [ ] AJAX requests execute before HTML
- [ ] PayrollSnapshotManager integrated throughout
- [ ] Legacy URL still works
- [ ] All includes updated

---

### Phase 6: Testing & Documentation

**Goal:** Ensure reliability and maintainability

**Steps:**

1. **Unit Tests**
   - VendServiceTest.php - Test all Vend operations
   - DeputyServiceTest.php - Test timesheet operations
   - CacheServiceTest.php - Test caching logic
   - Use PHPUnit, mock external dependencies

2. **Integration Tests**
   - PayrollControllerTest.php - Test controller methods
   - AmendmentControllerTest.php - Test amendment workflow
   - Test database interactions
   - Test service integration

3. **End-to-End Tests**
   - FullPayrollFlowTest.php - Test complete payroll pipeline:
     - Load payroll
     - Create Vend payments
     - Push to Xero
     - Verify snapshots captured
     - Check error handling

4. **Documentation**
   - MODULE_INFO.json:
     ```json
     {
       "name": "Payroll Processing",
       "version": "2.0.0",
       "description": "Complete payroll management with Deputy, Vend, and Xero integration",
       "category": "Human Resources",
       "author": "CIS Development Team",
       "dependencies": [
         "PayrollSnapshotManager",
         "Deputy API",
         "Vend API",
         "Xero API"
       ],
       "routes": {
         "main": "/modules/human_resources/payroll/index.php",
         "ajax": "/modules/human_resources/payroll/api/"
       }
     }
     ```
   - README.md - Module overview, features, usage
   - ARCHITECTURE.md - Design decisions, data flow, service interactions

**Acceptance Criteria:**
- [ ] >80% code coverage
- [ ] All critical paths tested
- [ ] Documentation complete
- [ ] Examples for common tasks

---

## Data Flow Diagrams

### Current Flow (Monolithic)

```
User Request
     │
     ├─── AJAX? ──> AJAX Handlers (Lines 637-802)
     │                    │
     │                    └─> Deputy API / Database
     │                              │
     │                              └─> JSON Response
     │
     └─── Page Load ──> Main Logic (Lines 901-1700)
                             │
                             ├─> Vend API
                             ├─> Deputy API
                             ├─> Xero API
                             ├─> Database
                             │
                             └─> HTML Rendering (Lines 1701-2700)
                                      │
                                      └─> JavaScript (Lines 2701-3100)
```

### Target Flow (Modular)

```
User Request
     │
     ├─── AJAX?
     │     │
     │     └─> AjaxController (index.php)
     │              │
     │              └─> Routes to API file (config/routes.php)
     │                       │
     │                       ├─> api/amendments.php
     │                       │        │
     │                       │        └─> AmendmentController
     │                       │                 │
     │                       │                 └─> DeputyService
     │                       │
     │                       ├─> api/vend-payments.php
     │                       │        │
     │                       │        └─> PayrollController->createAccountPayments()
     │                       │                 │
     │                       │                 ├─> VendService
     │                       │                 └─> PayrollSnapshotManager
     │                       │
     │                       └─> api/xero-operations.php
     │                                │
     │                                └─> PayrollController->pushToXero()
     │                                         │
     │                                         └─> xero-payruns.php
     │
     └─── Page Load
           │
           └─> PayrollController (index.php)
                    │
                    ├─> PayrollController->run()
                    │         │
                    │         ├─> VendService->scanSnapshots()
                    │         ├─> DeputyService (get timesheets)
                    │         ├─> Xero API (get employees, payslips)
                    │         ├─> Database (get amendments, leave)
                    │         └─> PayrollSnapshotManager->captureSnapshot()
                    │
                    └─> View Rendering (views/main.php)
                              │
                              ├─> views/partials/payroll_table.php
                              ├─> views/partials/leave_tab.php
                              ├─> views/partials/amendments_tab.php
                              │
                              └─> Client-Side (assets/js/payroll.js)
```

---

## Risk Assessment

### High-Risk Areas

1. **AJAX Handlers Must Execute First**
   - **Risk:** HTML output before JSON breaks AJAX
   - **Mitigation:**
     - AjaxController checks for AJAX params first
     - Early exit after JSON response
     - No includes before AJAX check

2. **Breaking Existing Functionality**
   - **Risk:** Refactoring breaks critical payroll operations
   - **Mitigation:**
     - Comprehensive testing at each phase
     - Keep legacy entry point working
     - Gradual migration (can run old version in parallel)

3. **Deputy API Changes**
   - **Risk:** Deputy API behavior changes during refactor
   - **Mitigation:**
     - Extract Deputy logic early
     - Add detailed logging
     - Test against real Deputy API

4. **Xero Integration Complexity**
   - **Risk:** Xero push logic is complex, easy to break
   - **Mitigation:**
     - Keep existing xero-payruns.php intact initially
     - Wrap in service layer
     - Test with real Xero sandbox

5. **Session Management**
   - **Risk:** PayrollSnapshotManager relies on session state
   - **Mitigation:**
     - Centralize session handling in PayrollController
     - Clear session documentation
     - Test session persistence

### Medium-Risk Areas

1. **Cache Invalidation**
   - **Risk:** Stale cache causes incorrect data display
   - **Mitigation:** CacheService with proper TTL, bust parameter

2. **CSRF Token Management**
   - **Risk:** CSRF validation breaks after refactor
   - **Mitigation:** Centralized CSRF in AjaxController

3. **Multi-Shift Amendments**
   - **Risk:** Complex multi-shift logic is hard to extract
   - **Mitigation:** Comprehensive testing of multi-shift scenarios

### Low-Risk Areas

1. **CSS/JS Extraction**
   - **Risk:** Minimal - mostly moving files
   - **Mitigation:** Test in browser after extraction

2. **View Rendering**
   - **Risk:** Low - views receive prepared data
   - **Mitigation:** Ensure all variables passed correctly

---

## Success Criteria

### Functional Requirements
- [ ] All existing features work identically
- [ ] AJAX endpoints return correct responses
- [ ] Payroll calculations accurate
- [ ] Deputy sync works
- [ ] Vend payments allocated correctly
- [ ] Xero push successful
- [ ] Amendments accepted/declined properly
- [ ] Leave requests processed
- [ ] Snapshots captured automatically

### Non-Functional Requirements
- [ ] Code is maintainable (single responsibility)
- [ ] Code is testable (dependency injection)
- [ ] Code is documented (PHPDoc, README)
- [ ] Code follows PSR-12 standards
- [ ] No code duplication (DRY principle)
- [ ] Error handling is comprehensive
- [ ] Logging is detailed
- [ ] Performance is equal or better

### Quality Metrics
- [ ] >80% code coverage
- [ ] 0 critical bugs
- [ ] <5 medium bugs
- [ ] All P0 issues resolved before deployment

---

## Rollout Plan

### Stage 1: Development (Week 1-2)
- Extract services (VendService, DeputyService, CacheService)
- Extract AJAX endpoints
- Create controllers
- Unit tests

### Stage 2: Integration (Week 3)
- Create module entry point
- Integrate PayrollSnapshotManager
- Extract views and assets
- Integration tests

### Stage 3: Testing (Week 4)
- End-to-end testing
- User acceptance testing
- Performance testing
- Bug fixes

### Stage 4: Deployment (Week 5)
- Deploy to staging
- Final testing
- Deploy to production
- Monitor for issues

### Stage 5: Cleanup (Week 6)
- Remove old code (keep backup)
- Update documentation
- Team training
- Postmortem

---

## Rollback Plan

If critical issues arise:

1. **Immediate Rollback**
   - Restore `/public_html/payroll-process.php` from backup
   - Verify functionality
   - Investigate issue

2. **Fix Forward**
   - If minor issue, fix in place
   - Deploy hotfix
   - Monitor

3. **Hybrid Approach**
   - Keep old file working alongside new module
   - Add feature flag to switch between versions
   - Migrate gradually

---

## Notes & Considerations

### Dependencies
- PayrollSnapshotManager (already complete)
- Deputy API functions (getDeputyTimeSheetsSpecificDay, deputyUpdateTimeSheet, deputyCreateTimeSheet)
- Vend API functions (getVendRegisters, getVendPaymentTypes, vend_add_payment_minimal)
- Xero API (xero-payruns.php, xero-init.php, xero-helpers.php)
- Database tables (users, timesheet_amendments, vend_outlets, etc.)

### Backward Compatibility
- `/public_html/payroll-process.php` must continue working
- All existing bookmarks/links must work
- Session state must be preserved
- AJAX endpoints must maintain same URLs (or redirect)

### Future Enhancements (Post-Modularization)
- Admin UI for payroll settings
- Bulk amendment operations
- Advanced reporting
- Export functionality (CSV, PDF)
- Email notifications
- Slack/Teams integration
- Real-time progress (WebSocket/SSE instead of polling)
- Mobile-responsive UI improvements

---

## Conclusion

This modularization transforms a 3,328-line monolithic file into a professional, maintainable module structure following **MVC** principles and **SOLID** design patterns. The result will be:

- **More maintainable:** Each component has a single responsibility
- **More testable:** Services can be unit tested in isolation
- **More scalable:** New features can be added without touching core logic
- **Better documented:** Clear architecture and API documentation
- **Higher quality:** Comprehensive testing ensures reliability

**Estimated Effort:** 5-6 weeks (1 developer full-time)
**Priority:** High - Core business functionality
**Risk:** Medium - Requires careful testing and gradual rollout

---

**Next Steps:**
1. Review this plan with team
2. Get approval to proceed
3. Start Phase 1: Create structure and extract services
4. Regular check-ins to ensure progress
5. Adjust timeline as needed

**Questions? Contact:** CIS Development Team
