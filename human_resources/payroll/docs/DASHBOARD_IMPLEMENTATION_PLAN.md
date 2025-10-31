# Payroll Dashboard Implementation Plan
**Date:** October 29, 2025
**Status:** Ready to Build

## Current System Inventory

### ✅ Available Controllers (6)
1. **AmendmentController** - Timesheet amendments
2. **PayrollAutomationController** - AI/bot automation
3. **PayslipController** - Payslip management
4. **WageDiscrepancyController** - Wage discrepancies (NEW)
5. **XeroController** - Xero integration
6. **BaseController** - Base functionality

### ✅ Available Services (11)
1. **AmendmentService** - Amendment workflow
2. **BonusService** - Bonus calculations (vape drops, reviews, monthly, commission)
3. **DeputyService** - Deputy API integration
4. **VendService** - Vend snapshot management
5. **WageDiscrepancyService** - Discrepancy handling (NEW)
6. **PayslipCalculationEngine** - Payslip calculations
7. **PayslipService** - Payslip operations
8. **BankExportService** - Bank file exports
9. **XeroService** - Xero operations
10. **PayrollAutomationService** - AI automation
11. **NZEmploymentLaw** - Legal compliance

### ✅ Available API Routes (24 endpoints)

#### Amendment Routes (6)
- `POST /api/payroll/amendments/create`
- `GET /api/payroll/amendments/:id`
- `POST /api/payroll/amendments/:id/approve`
- `POST /api/payroll/amendments/:id/decline`
- `GET /api/payroll/amendments/pending`
- `GET /api/payroll/amendments/history`

#### Automation Routes (5)
- `GET /api/payroll/automation/dashboard`
- `GET /api/payroll/automation/reviews/pending`
- `POST /api/payroll/automation/process`
- `GET /api/payroll/automation/rules`
- `GET /api/payroll/automation/stats`

#### Xero Routes (5)
- `POST /api/payroll/xero/payrun/create`
- `GET /api/payroll/xero/payrun/:id`
- `POST /api/payroll/xero/payments/batch`
- `GET /api/payroll/xero/oauth/authorize`
- `GET /api/payroll/xero/oauth/callback`

#### Wage Discrepancy Routes (8)
- `POST /api/payroll/discrepancies/submit`
- `GET /api/payroll/discrepancies/:id`
- `GET /api/payroll/discrepancies/pending`
- `GET /api/payroll/discrepancies/my-history`
- `POST /api/payroll/discrepancies/:id/approve`
- `POST /api/payroll/discrepancies/:id/decline`
- `POST /api/payroll/discrepancies/:id/upload-evidence`
- `GET /api/payroll/discrepancies/statistics`

## 📋 Required Dashboard Sections (5 Mandatory)

### 1. ✅ Timesheet Amendments
**Controller:** AmendmentController (exists)
**Service:** AmendmentService (exists)
**Routes:** 6 endpoints available
**Features:**
- Pending amendments list
- Approve/decline buttons
- History view
- Create amendment form
- Staff can submit, managers approve

### 2. ✅ Wage Discrepancies (NEW)
**Controller:** WageDiscrepancyController (exists)
**Service:** WageDiscrepancyService (exists)
**Routes:** 8 endpoints available
**Features:**
- Pending discrepancies queue
- Priority indicators (urgent/high/medium/low)
- AI analysis display (risk score, confidence)
- Approve/decline with reasoning
- Staff submission form
- Evidence upload
- Statistics dashboard

### 3. ❌ Leave Requests
**Controller:** NEEDS TO BE CREATED
**Service:** NEEDS TO BE CREATED
**Routes:** NEEDS TO BE CREATED
**Features:**
- Pending leave requests
- Approval workflow
- Leave balance display
- Calendar view

### 4. ⚠️ Bonuses
**Controller:** NEEDS TO BE CREATED
**Service:** BonusService (exists) ✅
**Routes:** NEEDS TO BE CREATED
**Features:**
- Vape drops (automatic from sales_intelligence)
- Google reviews (automatic)
- Monthly bonuses (manual entry)
- Commission calculations
- Approval workflow
- Bonus history

### 5. ⚠️ Vend Account Payments
**Controller:** NEEDS TO BE CREATED
**Service:** VendService (exists) ✅
**Routes:** NEEDS TO BE CREATED
**Features:**
- Pending payment requests
- Payment allocation status
- Manual payment entry
- Payment history

## 🚀 Implementation Steps

### Phase 1: Create Missing Controllers & Routes (30 mins)
1. Create `BonusController.php` with actions:
   - `getPending()` - Get pending bonuses
   - `approve()` - Approve bonus
   - `create()` - Create manual bonus
   - `getHistory()` - Get bonus history

2. Create `VendPaymentController.php` with actions:
   - `getPending()` - Get pending Vend payments
   - `allocate()` - Allocate payment
   - `getHistory()` - Get payment history

3. Create `LeaveController.php` with actions:
   - `getPending()` - Get pending leave requests
   - `approve()` - Approve leave
   - `decline()` - Decline leave
   - `create()` - Create leave request
   - `getBalances()` - Get staff leave balances

4. Add routes to `routes.php` for all three controllers

### Phase 2: Create Comprehensive Dashboard UI (60 mins)
1. Create `views/dashboard.php` with:
   - Modern Bootstrap 5 layout
   - 5 tabbed sections (one per feature)
   - Real-time data loading via AJAX
   - Manual controls for everything
   - Beautiful professional design

2. Create section-specific views:
   - `views/partials/amendments_section.php`
   - `views/partials/discrepancies_section.php`
   - `views/partials/leave_section.php`
   - `views/partials/bonuses_section.php`
   - `views/partials/vend_payments_section.php`

### Phase 3: Create Dashboard Controller (15 mins)
1. Create `DashboardController.php` with actions:
   - `index()` - Render dashboard view
   - `getData()` - Aggregate data from all services

2. Add dashboard routes:
   - `GET /payroll/dashboard`
   - `GET /api/payroll/dashboard/data`

### Phase 4: Test & Polish (20 mins)
1. Test all API endpoints
2. Test dashboard loading
3. Verify permissions
4. Check responsive design
5. Add loading states
6. Add error handling

## 📊 Design Requirements

### User Requirements:
> "FULLY 100% FEATURE RICH. EVERY DATA, AND TO KNOW EVERYTHING POSSIBLE. I WANT TO MANUALLY BE ABLE TO DO EVERYTHING AS WELL IF I NEED TOO. BEAUTIFUL UI."

### Mandatory Features:
- ✅ Show ALL data (no hiding information)
- ✅ Manual controls for EVERYTHING
- ✅ Beautiful professional design
- ✅ Real-time updates
- ✅ Responsive (mobile + desktop)
- ✅ Loading states
- ✅ Error handling
- ✅ Success notifications

### Design Elements:
- Modern color scheme (blue/green for payroll)
- Icons for visual clarity
- Badges for status indicators
- Charts for statistics
- Tables for data lists
- Modals for forms
- Toast notifications
- Smooth animations

## ⏱️ Timeline

**Total Estimated Time:** 2-3 hours

- Phase 1: 30 minutes (controllers + routes)
- Phase 2: 60 minutes (dashboard UI)
- Phase 3: 15 minutes (dashboard controller)
- Phase 4: 20 minutes (testing)
- Buffer: 15-55 minutes (polish + fixes)

## 🎯 Success Criteria

Dashboard is complete when:
- ✅ All 5 sections visible and functional
- ✅ All pending items load correctly
- ✅ Approve/decline actions work
- ✅ Manual entry forms work
- ✅ UI is beautiful and professional
- ✅ No 404 errors on API calls
- ✅ Permissions enforced correctly
- ✅ Responsive on mobile
- ✅ User can "do everything manually"

## 📝 Notes

- Old system (payroll-process.php) is 3,329 lines and working
- User wants NEW MODULE version, not old system
- User urgently needs this: "CHANGES AND AMENDMENTS I NEED TO PUSH THROUGH TODAY"
- User challenged: "ID BE SURPRISED IF YOU GET IT RIGHT"
- Must exceed expectations!
