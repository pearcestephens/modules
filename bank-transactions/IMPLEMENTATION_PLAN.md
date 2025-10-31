# Bank Transactions Module - Implementation Plan
## Phase-by-Phase Detailed Execution Plan

**Date:** October 30, 2025
**Status:** IN PROGRESS
**Current Phase:** API Endpoints & Transaction Detail View

---

## PHASE 1: API ENDPOINTS (CRITICAL PATH)
**Priority:** HIGH - Required for JavaScript functionality
**Estimated Time:** 2-3 hours

### Step 1.1: Create `/api/dashboard-metrics.php` ⏳
**Purpose:** AJAX endpoint for real-time dashboard metrics
**Method:** GET
**Parameters:** `date` (optional, default today)
**Response:** JSON with metrics and type_breakdown
**Tests:**
- [ ] Test with curl: `curl "https://staff.vapeshed.co.nz/modules/bank-transactions/api/dashboard-metrics.php?date=2025-10-30"`
- [ ] Verify HTTP 200 response
- [ ] Verify JSON structure: `{success: true, data: {metrics: {...}, type_breakdown: [...]}}`
- [ ] Test with invalid date format
- [ ] Test with missing date parameter

### Step 1.2: Create `/api/auto-match-single.php` ⏳
**Purpose:** Auto-match single transaction
**Method:** POST
**Parameters:** `csrf_token`, `transaction_id`
**Response:** JSON with match result and new status
**Tests:**
- [ ] Test with curl POST with valid transaction ID
- [ ] Verify HTTP 200 response
- [ ] Verify JSON structure: `{success: true, data: {status: 'matched|review|unmatched', confidence: 123}}`
- [ ] Test with invalid transaction ID (should return 404 error)
- [ ] Test without CSRF token (should return 403 error)
- [ ] Test with already matched transaction (should return error)

### Step 1.3: Create `/api/auto-match-all.php` ⏳
**Purpose:** Auto-match all unmatched transactions for a date
**Method:** POST
**Parameters:** `csrf_token`, `date` (optional)
**Response:** JSON with counts (matched, review, failed)
**Tests:**
- [ ] Test with curl POST
- [ ] Verify HTTP 200 response
- [ ] Verify JSON structure: `{success: true, data: {matched: 10, review: 5, failed: 2}}`
- [ ] Test without CSRF token
- [ ] Verify database updates (check status changes)

### Step 1.4: Create `/api/bulk-auto-match.php` ⏳
**Purpose:** Auto-match selected transaction IDs
**Method:** POST
**Parameters:** `csrf_token`, `transaction_ids` (array)
**Response:** JSON with counts (matched, review, failed)
**Tests:**
- [ ] Test with curl POST with array of IDs
- [ ] Verify HTTP 200 response
- [ ] Verify JSON structure matches auto-match-all
- [ ] Test with empty array (should return error)
- [ ] Test with invalid IDs (should skip and report failed)
- [ ] Verify only valid IDs are processed

### Step 1.5: Create `/api/bulk-send-review.php` ⏳
**Purpose:** Send selected transactions to review queue
**Method:** POST
**Parameters:** `csrf_token`, `transaction_ids` (array)
**Response:** JSON with updated count
**Tests:**
- [ ] Test with curl POST with array of IDs
- [ ] Verify HTTP 200 response
- [ ] Verify JSON structure: `{success: true, data: {updated: 15}}`
- [ ] Verify database status changed to 'review'
- [ ] Test with already matched transactions (should return error)

### Step 1.6: Create `/api/export.php` ⏳
**Purpose:** Export transactions to CSV
**Method:** GET
**Parameters:** All filter parameters from transaction list
**Response:** CSV file download
**Tests:**
- [ ] Test with curl and save to file
- [ ] Verify HTTP 200 response
- [ ] Verify Content-Type: text/csv
- [ ] Verify Content-Disposition header with filename
- [ ] Open CSV in Excel/LibreOffice to verify format
- [ ] Test with different filter combinations

### Step 1.7: Create `/api/match-suggestions.php` ⏳
**Purpose:** Get AI match suggestions for a transaction
**Method:** GET
**Parameters:** `transaction_id`
**Response:** JSON with top 5 suggestions with confidence breakdown
**Tests:**
- [ ] Test with curl GET
- [ ] Verify HTTP 200 response
- [ ] Verify JSON structure: `{success: true, data: {suggestions: [{order: {...}, confidence: {...}}]}}`
- [ ] Test with transaction that has no matches (should return empty array)
- [ ] Verify confidence breakdown includes all factors

### Step 1.8: Create `/api/reassign-payment.php` ⏳
**Purpose:** Reassign payment from one order to another
**Method:** POST
**Parameters:** `csrf_token`, `transaction_id`, `old_order_id`, `new_order_id`, `reason`
**Response:** JSON with success/failure
**Tests:**
- [ ] Test with curl POST
- [ ] Verify HTTP 200 response
- [ ] Verify old order payment is voided
- [ ] Verify new order payment is created
- [ ] Verify audit trail entries created
- [ ] Test with invalid order IDs

---

## PHASE 2: TRANSACTION DETAIL VIEW
**Priority:** HIGH - Core functionality
**Estimated Time:** 1-2 hours

### Step 2.1: Create `views/transaction-detail.php` ⏳
**Purpose:** Forensics view with match suggestions
**Features:**
- Complete transaction information display
- Match suggestions cards with confidence breakdown
- Linked order details (if matched)
- Audit trail timeline
- Action buttons (Match, Unmatch, Reassign, Void)
**Tests:**
- [ ] Test with unmatched transaction URL
- [ ] Verify page loads without PHP errors
- [ ] Verify all transaction fields display correctly
- [ ] Test with matched transaction (should show order details)
- [ ] Test with review status transaction (should show suggestions)
- [ ] Verify audit trail displays correctly
- [ ] Check responsive design on mobile

### Step 2.2: Update TransactionController `detail()` method ⏳
**Purpose:** Enhance detail method to load all required data
**Changes:**
- Load transaction or 404
- Get match suggestions if unmatched/review
- Get order details if matched
- Get audit trail
- Calculate confidence for suggestions
**Tests:**
- [ ] Test detail method with valid ID
- [ ] Test detail method with invalid ID (should 404)
- [ ] Verify suggestions loaded for unmatched
- [ ] Verify order details loaded for matched
- [ ] Verify audit trail loaded

---

## PHASE 3: REVIEW QUEUE VIEW
**Priority:** HIGH - Core workflow
**Estimated Time:** 2-3 hours

### Step 3.1: Create `controllers/ReviewController.php` ⏳
**Purpose:** Manage review queue workflow
**Methods:**
- `queue()` - Display review queue
- `next()` - Get next transaction in queue
- `previous()` - Get previous transaction
- `acceptSuggestion()` - Accept AI suggestion
- `rejectAll()` - Reject all suggestions
- `skipTransaction()` - Skip to next
**Tests:**
- [ ] Test PHP syntax: `php -l ReviewController.php`
- [ ] Test queue() method loads transactions
- [ ] Test navigation methods (next/previous)
- [ ] Test acceptSuggestion() creates payment
- [ ] Verify audit logging on all actions

### Step 3.2: Create `views/review-queue.php` ⏳
**Purpose:** AI-powered review interface with keyboard shortcuts
**Features:**
- Full-screen focus mode
- Large transaction display
- AI suggestions with confidence visualization
- Keyboard shortcuts (1/2/3 accept, n/p navigate, s skip)
- Progress indicator
**Tests:**
- [ ] Test page loads without PHP errors
- [ ] Verify transaction displays correctly
- [ ] Verify suggestions display with confidence bars
- [ ] Test keyboard shortcut legend displays
- [ ] Check responsive design

### Step 3.3: Create `assets/js/review-queue.js` ⏳
**Purpose:** Keyboard shortcuts and real-time updates
**Features:**
- Keydown event handlers (1-5 accept, n/p navigate, s skip)
- AJAX suggestion acceptance
- Queue progression (preload next)
- Confidence visualization updates
**Tests:**
- [ ] Test keyboard shortcuts in browser
- [ ] Verify 1-5 keys accept corresponding suggestion
- [ ] Verify n/p keys navigate queue
- [ ] Verify s key skips transaction
- [ ] Test AJAX calls with browser dev tools
- [ ] Verify error handling

### Step 3.4: Update `index.php` router ⏳
**Purpose:** Add review queue routes
**Routes:**
- `route=review` → ReviewController::queue()
- `route=review-next` → ReviewController::next()
- `route=review-accept` → ReviewController::acceptSuggestion()
**Tests:**
- [ ] Test route with curl: `curl -I ".../?route=review"`
- [ ] Verify 302 redirect to login (auth working)
- [ ] Test with authenticated session

---

## PHASE 4: REMAINING VIEWS
**Priority:** MEDIUM - Can be deferred
**Estimated Time:** 3-4 hours

### Step 4.1: Create `views/reports.php` ⏳
**Features:**
- Quick reports section (daily summary, outstanding, discrepancies)
- Custom report builder
- Scheduled reports management
- Download options (PDF, Excel, CSV)

### Step 4.2: Create `controllers/ReportController.php` ⏳
**Methods:**
- `index()` - Display reports page
- `generate()` - Generate custom report
- `schedule()` - Schedule report
- `download()` - Download report file

### Step 4.3: Create `views/reassignment.php` ⏳
**Features:**
- Two-step workflow (select transaction → select new order)
- Reason dropdown (mandatory)
- Confidence recalculation
- Confirmation step

### Step 4.4: Create `views/settings.php` ⏳
**Features:**
- 4 tabs (Matching, Notifications, Reports, Advanced)
- Threshold sliders (auto-match, review, reject)
- Email notification preferences
- Report schedule configuration

### Step 4.5: Create `controllers/SettingsController.php` ⏳
**Methods:**
- `index()` - Display settings
- `save()` - Save configuration
- `reset()` - Reset to defaults

---

## PHASE 5: COMPREHENSIVE TESTING
**Priority:** CRITICAL - Must pass before production
**Estimated Time:** 4-6 hours

### Step 5.1: Unit Testing (Models) ⏳
**Tests:**
- [ ] TransactionModel::findUnmatched() returns correct data
- [ ] TransactionModel::getDashboardMetrics() calculates correctly
- [ ] TransactionModel::getTypeBreakdown() groups correctly
- [ ] OrderModel::findById() retrieves order
- [ ] PaymentModel::create() inserts payment

### Step 5.2: Controller Testing ⏳
**Tests:**
- [ ] DashboardController renders without errors
- [ ] TransactionController list() paginates correctly
- [ ] TransactionController detail() loads data
- [ ] TransactionController autoMatch() executes correctly
- [ ] ReviewController queue() manages session

### Step 5.3: API Endpoint Testing ⏳
**Tests:**
- [ ] All 8 API endpoints return HTTP 200 on success
- [ ] All API endpoints return proper JSON structure
- [ ] All API endpoints validate CSRF tokens
- [ ] All API endpoints handle errors gracefully
- [ ] All API endpoints log to audit trail

### Step 5.4: JavaScript Testing ⏳
**Tests:**
- [ ] dashboard.js initializes without console errors
- [ ] transaction-list.js filters work correctly
- [ ] review-queue.js keyboard shortcuts functional
- [ ] All AJAX calls succeed with valid responses
- [ ] Toast notifications display correctly

### Step 5.5: Integration Testing ⏳
**Tests:**
- [ ] Complete workflow: Dashboard → List → Detail → Match
- [ ] Complete workflow: Dashboard → Review Queue → Accept → Verify Payment
- [ ] Bulk actions: Select multiple → Auto-match → Verify
- [ ] Export: Apply filters → Export CSV → Open in Excel
- [ ] Reassignment: Select transaction → Reassign → Verify audit trail

### Step 5.6: Performance Testing ⏳
**Tests:**
- [ ] Dashboard loads in < 1 second
- [ ] Transaction list with 50 items loads in < 2 seconds
- [ ] Auto-match single transaction completes in < 500ms
- [ ] Auto-match 100 transactions completes in < 30 seconds
- [ ] Database queries use proper indexes (EXPLAIN analysis)

### Step 5.7: Security Testing ⏳
**Tests:**
- [ ] Unauthenticated access redirects to login
- [ ] CSRF token validation on all POST requests
- [ ] SQL injection attempts blocked (test with malicious input)
- [ ] XSS attempts blocked (test with script tags)
- [ ] Permission checks enforced on all routes

### Step 5.8: Accessibility Testing ⏳
**Tests:**
- [ ] Keyboard navigation works (Tab, Enter, Space)
- [ ] Screen reader friendly (ARIA labels)
- [ ] Color contrast meets WCAG 2.1 AA standards
- [ ] Focus indicators visible
- [ ] Forms have proper labels

### Step 5.9: Responsive Testing ⏳
**Tests:**
- [ ] Mobile (375px width) - All views usable
- [ ] Tablet (768px width) - Optimal layout
- [ ] Desktop (1920px width) - Full features
- [ ] Print layout works correctly

---

## PHASE 6: DOCUMENTATION & POLISH
**Priority:** LOW - Final touches
**Estimated Time:** 1-2 hours

### Step 6.1: Create User Documentation ⏳
- [ ] Dashboard overview
- [ ] Transaction list usage
- [ ] Review queue workflow
- [ ] Reports generation
- [ ] Settings configuration

### Step 6.2: Create Developer Documentation ⏳
- [ ] API endpoint reference
- [ ] Database schema documentation
- [ ] Code architecture overview
- [ ] Deployment instructions

### Step 6.3: Code Cleanup ⏳
- [ ] Remove console.log statements
- [ ] Remove commented-out code
- [ ] Add missing PHPDoc comments
- [ ] Consistent code formatting

---

## PROGRESS TRACKING

### Overall Status
- ✅ Phase 0: Foundation (100%)
- ✅ Phase 1: Database Migration (100%)
- ✅ Phase 2: Models (100%)
- ⏳ Phase 3: Controllers (40% - 2 of 5)
- ⏳ Phase 4: Views (30% - 2 of 7)
- ⏳ Phase 5: JavaScript (50% - 2 of 4)
- ✅ Phase 6: CSS (100%)
- ⏳ Phase 7: APIs (0% - 0 of 8)
- ⏳ Phase 8: Testing (0%)

### Current Focus
**NOW:** Creating API endpoints (Phase 1)

### Next Up
1. API Endpoints (8 files)
2. Transaction Detail View
3. Review Queue (Controller + View + JS)
4. Comprehensive Testing

---

## TESTING COMMANDS REFERENCE

### PHP Syntax Check
```bash
php -l /path/to/file.php
```

### API Testing with curl
```bash
# GET request
curl -i "https://staff.vapeshed.co.nz/modules/bank-transactions/api/endpoint.php?param=value"

# POST request with JSON
curl -i -X POST \
  -H "Content-Type: application/json" \
  -H "X-CSRF-Token: TOKEN" \
  -d '{"key":"value"}' \
  "https://staff.vapeshed.co.nz/modules/bank-transactions/api/endpoint.php"

# With cookie authentication
curl -i -b "PHPSESSID=session_id" "URL"
```

### Database Query Testing
```bash
mysql -u jcepnzzkmj -p'wprKh9Jq63' jcepnzzkmj -e "SELECT * FROM bank_deposits LIMIT 5"
```

### Check Apache Error Log
```bash
tail -100 /home/master/applications/jcepnzzkmj/logs/apache_*.error.log
```

---

## SUCCESS CRITERIA

### For API Endpoints
- ✅ HTTP 200 response on success
- ✅ Proper JSON structure: `{success: true/false, data: {...}, error: {...}}`
- ✅ CSRF token validation working
- ✅ Error handling with descriptive messages
- ✅ Audit logging on all mutations

### For Views
- ✅ No PHP errors in page source
- ✅ All dynamic data displays correctly
- ✅ Responsive design works on mobile
- ✅ CSRF tokens embedded
- ✅ Proper HTML escaping (no XSS)

### For JavaScript
- ✅ No console errors on page load
- ✅ All event handlers attached
- ✅ AJAX calls succeed
- ✅ Error handling with user feedback
- ✅ Loading states during async operations

---

**Last Updated:** October 30, 2025 12:45 PM
**Next Review:** After Phase 1 completion
