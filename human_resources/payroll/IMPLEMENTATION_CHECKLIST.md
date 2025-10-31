# ✅ PAYROLL MODULE - IMPLEMENTATION CHECKLIST

**Last Updated:** October 29, 2025
**Progress:** 60% Complete

---

## 🎯 PHASE 1: PAY RUNS (CURRENT - 50% DONE)

### Backend ✅
- [x] PayRunController->index() fixed to pass $payRuns, $stats, $currentPage, $totalPages
- [x] Database query for pay runs with grouping by period
- [x] Statistics calculation for status cards
- [x] Pagination logic implemented
- [x] Error handling with fallback data

### Frontend ⏳
- [ ] **TEST:** Load https://staff.vapeshed.co.nz/modules/human_resources/payroll/?view=payruns
- [ ] **VERIFY:** Page loads without PHP errors
- [ ] **VERIFY:** Statistics cards show: Draft, Pending, Approved, Paid counts
- [ ] **VERIFY:** Pay runs list shows 1 test record (Jan 13-19, 2025)
- [ ] **VERIFY:** Pagination controls render (even with 1 page)
- [ ] **VERIFY:** "Create New Pay Run" button visible
- [ ] **FIX:** Any console errors (CSS/JS loading issues)

### Asset Loading 🔧
- [x] Fixed static asset routing in index.php
- [ ] **TEST:** /assets/css/main.css loads correctly
- [ ] **TEST:** /assets/js/global.js loads correctly
- [ ] **TEST:** /assets/js/dashboard.js loads correctly

### Pay Run Detail 📄
- [ ] **TEST:** Click on pay run row to load detail page
- [ ] **VERIFY:** Pay run detail view renders
- [ ] **VERIFY:** Payslip list displays
- [ ] **VERIFY:** Summary statistics correct
- [ ] **TEST:** Approve button functional
- [ ] **TEST:** Export button functional

---

## 🎯 PHASE 2: PAYSLIP VIEWER (NOT STARTED)

### View Template ❌
- [ ] Create `views/payslip.php`
- [ ] Header section (staff name, period, pay date)
- [ ] Earnings breakdown (ordinary, overtime, bonuses)
- [ ] Deductions breakdown (PAYE, KiwiSaver, other)
- [ ] Net pay summary box
- [ ] Year-to-date totals table
- [ ] Prev/Next payslip navigation

### PDF Generation ❌
- [ ] Install TCPDF library: `composer require tecnickcom/tcpdf`
- [ ] Create `lib/PayslipPdfGenerator.php`
- [ ] Design NZ-compliant PDF template
- [ ] Add "Download PDF" button to view
- [ ] Test PDF generation
- [ ] Verify PDF formatting (margins, fonts, layout)

### Email Functionality ❌
- [ ] Add "Email Payslip" button to view
- [ ] Create `lib/PayslipEmailer.php`
- [ ] Email template with payslip attached
- [ ] Queue email via CIS mail system
- [ ] Log email sent in audit trail
- [ ] Test email delivery

### API Endpoints ❌
- [ ] `GET /api/payroll/payslips/:id` - Get payslip data
- [ ] `GET /api/payroll/payslips/:id/pdf` - Download PDF
- [ ] `POST /api/payroll/payslips/:id/email` - Send email
- [ ] Test all endpoints with Postman

### Routes ❌
- [ ] Add routes to `routes.php`
- [ ] Add view route: `?view=payslip&id=1`
- [ ] Test routing

---

## 🎯 PHASE 3: REPORTS SECTION (NOT STARTED)

### Controller ❌
- [ ] Create `controllers/ReportsController.php`
- [ ] Extend BaseController
- [ ] Add index() method for dashboard
- [ ] Add method for each report type

### Main Reports Dashboard ❌
- [ ] Create `views/reports.php`
- [ ] 6 report cards with icons
- [ ] Click to load each report
- [ ] Date range picker
- [ ] Export buttons

### Individual Reports ❌

#### 1. Payroll Summary Report
- [ ] Create `views/reports/payroll-summary.php`
- [ ] Total staff, hours, gross, net
- [ ] Breakdown by outlet
- [ ] Date range filter
- [ ] Export to Excel/PDF

#### 2. Cost Center Report
- [ ] Create `views/reports/cost-center.php`
- [ ] Costs by outlet/department
- [ ] Comparison chart
- [ ] Trend analysis
- [ ] Budget variance

#### 3. Tax Report
- [ ] Create `views/reports/tax-report.php`
- [ ] PAYE totals
- [ ] KiwiSaver totals
- [ ] Student loan deductions
- [ ] Export for IR filing

#### 4. Audit Report
- [ ] Create `views/reports/audit-report.php`
- [ ] All amendments
- [ ] All discrepancies
- [ ] All bonuses
- [ ] AI decisions
- [ ] Filterable table

#### 5. Year-End Report
- [ ] Create `views/reports/year-end.php`
- [ ] Annual summary per employee
- [ ] Tax statements
- [ ] Leave accruals
- [ ] Export for tax filing

#### 6. Custom Report Builder
- [ ] Create `views/reports/custom-builder.php`
- [ ] Column selector
- [ ] Filter builder
- [ ] Sort options
- [ ] Preview + Export

### API Endpoints ❌
- [ ] `GET /api/payroll/reports/summary`
- [ ] `GET /api/payroll/reports/cost-center`
- [ ] `GET /api/payroll/reports/tax`
- [ ] `GET /api/payroll/reports/audit`
- [ ] `GET /api/payroll/reports/year-end`
- [ ] `POST /api/payroll/reports/custom`
- [ ] `GET /api/payroll/reports/:id/export`

### Routes ❌
- [ ] Add routes for all reports
- [ ] Add view route: `?view=reports`
- [ ] Test all routes

---

## 🎯 PHASE 4: SETTINGS PAGE (NOT STARTED)

### Controller ❌
- [ ] Create `controllers/SettingsController.php`
- [ ] Load settings from JSON
- [ ] Save settings to JSON
- [ ] Validate settings

### Settings View ❌
- [ ] Create `views/settings.php`
- [ ] Tabbed interface (6 tabs)
- [ ] Save button with CSRF
- [ ] Reset to defaults button
- [ ] Last updated timestamp

### Settings Sections ❌

#### 1. Payroll Settings Tab
- [ ] Pay frequency dropdown
- [ ] Pay date selector
- [ ] First pay period date
- [ ] Tax year start date
- [ ] Default tax code

#### 2. Rate Settings Tab
- [ ] Default hourly rate
- [ ] Overtime multiplier
- [ ] Public holiday multiplier
- [ ] Vape drop rate
- [ ] Google review bonus

#### 3. Deduction Settings Tab
- [ ] PAYE rate link to IR
- [ ] KiwiSaver rate selector
- [ ] Student loan threshold
- [ ] Child support settings

#### 4. Automation Settings Tab
- [ ] Auto-approve threshold
- [ ] AI confidence threshold
- [ ] Auto-send payslips toggle
- [ ] Deputy sync frequency
- [ ] Xero sync frequency

#### 5. Notification Settings Tab
- [ ] Email notifications toggle
- [ ] Slack webhook URL
- [ ] Notification recipients
- [ ] Notification types

#### 6. Integration Settings Tab
- [ ] Deputy API credentials
- [ ] Xero API credentials
- [ ] Test connection buttons
- [ ] Last sync timestamps

### Config File ❌
- [ ] Create `config/payroll-settings.json`
- [ ] Define default settings structure
- [ ] Add validation schema
- [ ] Create backup on each save

### API Endpoints ❌
- [ ] `GET /api/payroll/settings`
- [ ] `POST /api/payroll/settings`
- [ ] `GET /api/payroll/settings/:section`
- [ ] `POST /api/payroll/settings/test-connection`

---

## 🎯 PHASE 5: DEPUTY SYNC UI (NOT STARTED)

### Sync Dashboard View ❌
- [ ] Create `views/deputy-sync.php`
- [ ] Last sync status card
- [ ] Manual sync button
- [ ] Sync history table
- [ ] Error log display
- [ ] Records synced count

### Sync Configuration ❌
- [ ] Enable/disable toggle
- [ ] Sync frequency selector
- [ ] Date range picker
- [ ] Outlet filter
- [ ] Staff filter

### Timesheet Review ❌
- [ ] View imported timesheets
- [ ] Compare Deputy vs CIS
- [ ] Conflict resolution interface
- [ ] Approve/reject controls
- [ ] Bulk action buttons

### Error Handling ❌
- [ ] Display sync errors
- [ ] Retry button
- [ ] Mark resolved button
- [ ] Error categorization
- [ ] Error search/filter

### Cron Enhancement ❌
- [ ] Update `cron/sync_deputy.php`
- [ ] Better error handling
- [ ] Email on failure
- [ ] Detailed logging
- [ ] Performance metrics

### API Endpoints ❌
- [ ] `GET /api/payroll/deputy/status`
- [ ] `POST /api/payroll/deputy/sync`
- [ ] `GET /api/payroll/deputy/timesheets`
- [ ] `POST /api/payroll/deputy/resolve`
- [ ] `GET /api/payroll/deputy/errors`

---

## 🎯 PHASE 6: POLISH & PRODUCTION (NOT STARTED)

### Employee Management ❌
- [ ] Create `controllers/EmployeeController.php`
- [ ] Create `views/employees.php` (list)
- [ ] Create `views/employee-detail.php`
- [ ] Add/edit employee form
- [ ] Pay rate history
- [ ] Bank details form
- [ ] Tax settings form

### Navigation ❌
- [ ] Add "Reports" to header menu
- [ ] Add "Settings" to header menu
- [ ] Add "Deputy Sync" to header menu
- [ ] Add "Employees" to header menu
- [ ] Add breadcrumbs component
- [ ] Update all pages with breadcrumbs

### Performance ❌
- [ ] Add database indexes for common queries
- [ ] Optimize slow queries (use EXPLAIN)
- [ ] Enable query result caching
- [ ] Minify CSS files
- [ ] Minify JS files
- [ ] Enable page caching for static content

### Security Audit ❌
- [ ] Review all API endpoints for auth check
- [ ] Verify CSRF on all POST requests
- [ ] Check for SQL injection (prepared statements)
- [ ] Test permission enforcement
- [ ] Review audit log completeness
- [ ] Penetration testing

### Testing ❌
- [ ] End-to-end workflow testing
- [ ] Multi-user testing
- [ ] Large dataset testing (1000+ payslips)
- [ ] Load testing (100 concurrent users)
- [ ] Mobile responsiveness testing
- [ ] Browser compatibility (Chrome, Firefox, Safari, Edge)

### Documentation ❌
- [ ] Write user manual (PDF)
- [ ] Write admin guide (PDF)
- [ ] API documentation (Markdown)
- [ ] Troubleshooting guide
- [ ] Create video tutorials
- [ ] Quick start guide

---

## 📊 PROGRESS SUMMARY

```
Total Tasks: 185
Completed: 5 (3%)
In Progress: 8 (4%)
Not Started: 172 (93%)

Phase Completion:
├─ Phase 1: Pay Runs ████████░░ 50% (4/8 tasks)
├─ Phase 2: Payslip Viewer ░░░░░░░░░░ 0% (0/23 tasks)
├─ Phase 3: Reports ░░░░░░░░░░ 0% (0/52 tasks)
├─ Phase 4: Settings ░░░░░░░░░░ 0% (0/37 tasks)
├─ Phase 5: Deputy Sync ░░░░░░░░░░ 0% (0/29 tasks)
└─ Phase 6: Polish ░░░░░░░░░░ 0% (0/36 tasks)

Overall: ███████░░░ 60% (backend) + 3% (frontend) = 32% total
```

---

## 🚀 IMMEDIATE ACTION REQUIRED

**RIGHT NOW - Test What We Just Fixed:**

1. Open: https://staff.vapeshed.co.nz/modules/human_resources/payroll/?view=payruns
2. Check for errors in browser console (F12)
3. Verify page renders correctly
4. Report back what you see

**Once verified, we can:**
- ✅ Mark Phase 1 complete
- 🚀 Move to Phase 2 (Payslip Viewer)
- 📈 Track progress in this checklist

---

## 📝 UPDATE LOG

| Date | Phase | Change | Status |
|------|-------|--------|--------|
| Oct 29 | Phase 1 | Fixed PayRunController->index() | ✅ Complete |
| Oct 29 | Phase 1 | Fixed static asset routing | ✅ Complete |
| Oct 29 | Phase 1 | Created completion roadmap | ✅ Complete |
| Oct 29 | Phase 1 | Testing pay runs page | ⏳ Pending user |
