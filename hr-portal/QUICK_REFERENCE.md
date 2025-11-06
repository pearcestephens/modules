# HR PORTAL - QUICK REFERENCE CARD

## 🚀 Access URLs

```
Main Dashboard:      https://staff.vapeshed.co.nz/modules/hr-portal/
Staff Directory:     https://staff.vapeshed.co.nz/modules/hr-portal/staff-directory.php
Integrations:        https://staff.vapeshed.co.nz/modules/hr-portal/integrations.php
```

## 📊 Test Status

**Last Tested:** November 5, 2025
**Status:** ✅ ALL TESTS PASSED (78/78)
**Test Command:** `cd /home/master/applications/jcepnzzkmj/public_html/modules/hr-portal && php test-suite.php`

## 📁 File Structure

```
/modules/hr-portal/
├── index.php                    # Main dashboard with auto-pilot controls
├── integrations.php             # Deputy & Xero connection dashboard
├── staff-directory.php          # Browse all staff with search
├── staff-detail.php             # Staff profile with 4 tabs
├── staff-timesheets.php         # Timesheet amendments with Deputy sync
├── staff-payroll.php            # Pay runs with Xero sync
│
├── includes/
│   ├── DeputyIntegration.php   # Wrapper around existing Deputy services
│   ├── XeroIntegration.php     # Wrapper around existing Xero services
│   ├── AIPayrollEngine.php     # AI decision engine
│   └── PayrollDashboard.php    # Dashboard data aggregation
│
├── api/
│   ├── sync-timesheet.php      # Sync timesheet to Deputy
│   ├── sync-payrun.php         # Sync payrun to Xero
│   ├── sync-deputy.php         # Bulk sync from Deputy
│   ├── sync-xero.php           # Bulk sync from Xero
│   ├── approve-item.php        # Approve amendment
│   ├── deny-item.php           # Deny amendment
│   ├── batch-approve.php       # Bulk approve
│   ├── toggle-autopilot.php    # Toggle AI auto-pilot
│   └── dashboard-stats.php     # Get dashboard statistics
│
├── views/
│   ├── auto-activity.php       # Auto-pilot activity log
│   ├── manual-control.php      # Manual review queue
│   ├── audit-trail.php         # Full audit trail
│   └── ai-settings.php         # AI configuration
│
├── test-suite.php               # Comprehensive test suite (78 tests)
├── TEST_RESULTS.md              # Full test report
├── INTERCONNECTED_PAGES_COMPLETE.md  # Feature documentation
├── NAVIGATION_MAP.md            # Visual navigation guide
└── README.md                    # Setup instructions
```

## 🔌 Integration Architecture

```
Pages → Integration Wrappers → Existing Services → APIs
        (DeputyIntegration)     (PayrollModule\     (Deputy)
        (XeroIntegration)        Services)          (Xero)
```

**Key Files Used:**
- `/modules/human_resources/payroll/services/DeputyService.php`
- `/modules/human_resources/payroll/services/DeputyApiClient.php`
- `/modules/human_resources/payroll/services/PayrollDeputyService.php`
- `/modules/human_resources/payroll/services/XeroServiceSDK.php` (Official SDK)
- `/modules/human_resources/payroll/services/PayrollXeroService.php`

## 🗄️ Database Tables

**Required:**
- `staff` - Employee records
- `payroll_timesheet_amendments` - Timesheet changes
- `payroll_payrun_amendments` - Payroll adjustments
- `payroll_ai_decisions` - AI decisions
- `integration_sync_log` - Sync history

**Important Columns:**
- `staff.deputy_employee_id` or `staff.deputy_id` - Deputy link
- `staff.xero_employee_id` or `staff.xero_id` - Xero link

## 🎯 Key Features

### ✅ Deputy Integration
- **Visible on:** staff-directory, staff-detail, staff-timesheets, integrations
- **Badges:** Green = synced, Gray = not linked
- **Sync buttons:** Individual and bulk sync
- **Connection test:** integrations.php

### ✅ Xero Integration
- **Visible on:** staff-directory, staff-detail, staff-payroll, integrations
- **Badges:** Green = synced, Gray = not linked
- **Sync buttons:** Individual and bulk sync
- **Connection test:** integrations.php
- **OAuth:** Re-authorization available if token expires

### ✅ Navigation
- **Breadcrumbs:** On all detail pages
- **Quick Nav:** index.php → all major pages
- **Back buttons:** On every page
- **Cross-links:** Directory ↔ Detail ↔ Timesheets/Payroll

### ✅ UI Features
- **Search:** Real-time filter on staff-directory.php
- **Pagination:** 20 items per page on timesheets/payroll
- **Status filters:** All / Pending / Approved / Denied
- **YTD summaries:** On staff-payroll.php
- **Sync logs:** Recent 50 syncs on integrations.php

## 🧪 Testing

**Run Full Test Suite:**
```bash
cd /home/master/applications/jcepnzzkmj/public_html/modules/hr-portal
php test-suite.php
```

**Expected Output:**
```
Total Tests:    78
✅ Passed:      78 (100%)
❌ Failed:      0
⚠️  Warnings:   0
```

**Test Categories:**
1. File Existence (19 tests)
2. PHP Syntax (19 tests)
3. Class Loading (4 tests)
4. Integration Wrappers (4 tests)
5. Navigation (8 tests)
6. Deputy Visibility (5 tests)
7. Xero Visibility (5 tests)
8. API Endpoints (5 tests)
9. SQL Queries (4 tests)
10. UI Features (5 tests)

## 🔧 Troubleshooting

### Deputy Not Connecting
1. Check `.env` has `DEPUTY_API_TOKEN=...`
2. Visit integrations.php and click "Test Connection"
3. Check existing services in `/modules/human_resources/payroll/services/`

### Xero Not Connecting
1. Check `.env` has `XERO_CLIENT_ID`, `XERO_CLIENT_SECRET`, `XERO_REGION=NZ`
2. Click "Re-authorize Xero" on integrations.php
3. Verify OAuth token not expired

### Staff Not Showing
1. Check `staff` table has `active = 1` records
2. Verify query in staff-directory.php runs without errors
3. Check bootstrap.php database connection

### Sync Not Working
1. Verify `integration_sync_log` table exists
2. Check API endpoint responses (network tab in browser)
3. Review sync logs in integrations.php

## 📞 Quick Commands

**Test Syntax:**
```bash
php -l /path/to/file.php
```

**Check Files:**
```bash
ls -lh /home/master/applications/jcepnzzkmj/public_html/modules/hr-portal/*.php
```

**View Logs:**
```bash
tail -f /home/master/applications/jcepnzzkmj/logs/*.log
```

**Run Test Suite:**
```bash
php test-suite.php
```

## 📚 Documentation

1. **TEST_RESULTS.md** - Full test report with all validation results
2. **INTERCONNECTED_PAGES_COMPLETE.md** - Complete feature documentation, testing checklist, deployment notes
3. **NAVIGATION_MAP.md** - ASCII diagrams showing page flow and navigation
4. **README.md** - Installation and setup instructions
5. **test-suite.php** - Automated test suite (78 tests)

## ✅ Production Checklist

- [ ] Database tables exist (staff, amendments, AI decisions, sync log)
- [ ] .env configured with Deputy API token
- [ ] .env configured with Xero OAuth credentials
- [ ] Run test-suite.php (should be 100% pass)
- [ ] Test Deputy connection on integrations.php
- [ ] Test Xero connection on integrations.php
- [ ] Browse staff directory with search
- [ ] View staff detail page with all tabs
- [ ] Test timesheet sync to Deputy
- [ ] Test payrun sync to Xero
- [ ] Verify sync logs appear in integrations.php

## 🎉 Ready to Deploy!

**Status:** ✅ 100% TEST PASS RATE
**Files:** 23 files (~100KB)
**URL:** https://staff.vapeshed.co.nz/modules/hr-portal/

---

*Last Updated: November 5, 2025*
