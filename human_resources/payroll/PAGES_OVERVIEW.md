# 📊 PAYROLL MODULE - PAGE OVERVIEW

## Live Pages: https://staff.vapeshed.co.nz/modules/human_resources/payroll/

---

## 1️⃣ DASHBOARD (Main Page)
**URL:** `/dashboard`

### What's on this page:
```
┌─────────────────────────────────────────────────────────┐
│  🎯 Payroll Dashboard                                    │
│  Comprehensive payroll management with AI automation    │
├─────────────────────────────────────────────────────────┤
│                                                          │
│  📊 STATS OVERVIEW (Top Cards):                         │
│  ├─ Pending Amendments                                  │
│  ├─ Urgent Discrepancies                                │
│  ├─ Leave Requests                                      │
│  ├─ Bonuses Pending                                     │
│  └─ Vend Payments Due                                   │
│                                                          │
├─────────────────────────────────────────────────────────┤
│                                                          │
│  📑 TABS (Main Sections):                               │
│                                                          │
│  1. ⏱️  TIMESHEET AMENDMENTS                            │
│     • Staff timesheet correction requests               │
│     • AI validation & approval workflow                 │
│     • Bulk processing tools                             │
│                                                          │
│  2. 💰 WAGE DISCREPANCIES (AI-Powered)                  │
│     • Deputy vs Xero wage mismatches                    │
│     • AI root cause analysis                            │
│     • Auto-fix suggestions                              │
│                                                          │
│  3. 🎁 BONUSES                                          │
│     Sub-tabs:                                            │
│     ├─ Monthly Bonuses                                  │
│     ├─ Vape Drops (product bonuses)                     │
│     └─ Google Reviews (review incentives)              │
│                                                          │
│  4. 💳 VEND ACCOUNT PAYMENTS                            │
│     • Staff account deductions                          │
│     • Purchase tracking                                 │
│     • Payment reconciliation                            │
│                                                          │
│  5. 🏖️  LEAVE REQUESTS                                  │
│     • Holiday requests                                  │
│     • Sick leave tracking                               │
│     • Approval workflow                                 │
│                                                          │
└─────────────────────────────────────────────────────────┘
```

---

## 2️⃣ PAY RUNS
**URL:** `/payruns`

### What's on this page:
```
┌─────────────────────────────────────────────────────────┐
│  📅 Pay Runs - Complete History                         │
├─────────────────────────────────────────────────────────┤
│                                                          │
│  📊 FEATURES:                                            │
│  ├─ List of all pay periods                            │
│  ├─ Pay run status (Draft/Submitted/Posted)            │
│  ├─ Total amounts & employee counts                    │
│  ├─ Quick actions (View/Edit/Submit)                   │
│  └─ Filter by date/status                              │
│                                                          │
│  🔗 INTEGRATIONS:                                        │
│  ├─ Xero Payroll API                                   │
│  ├─ Deputy Timesheets                                  │
│  └─ Vend Account Deductions                            │
│                                                          │
└─────────────────────────────────────────────────────────┘
```

---

## 3️⃣ PAY RUN DETAIL
**URL:** `/payrun-detail` (with ID parameter)

### What's on this page:
```
┌─────────────────────────────────────────────────────────┐
│  📋 Individual Pay Run Details                          │
├─────────────────────────────────────────────────────────┤
│                                                          │
│  📊 PAY RUN SUMMARY:                                     │
│  ├─ Pay period dates                                    │
│  ├─ Total gross wages                                   │
│  ├─ Total deductions                                    │
│  ├─ Total net pay                                       │
│  └─ Number of employees                                 │
│                                                          │
│  👥 EMPLOYEE BREAKDOWN:                                  │
│  ├─ Staff member list                                   │
│  ├─ Individual wages                                    │
│  ├─ Hours worked                                        │
│  ├─ Deductions (tax, KiwiSaver, Vend)                  │
│  └─ Net pay amounts                                     │
│                                                          │
│  🔧 ACTIONS:                                             │
│  ├─ Edit pay run                                        │
│  ├─ Submit to Xero                                      │
│  ├─ Generate payslips                                   │
│  └─ Export report                                       │
│                                                          │
└─────────────────────────────────────────────────────────┘
```

---

## 4️⃣ PAYSLIP
**URL:** `/payslip` (individual employee)

### What's on this page:
```
┌─────────────────────────────────────────────────────────┐
│  📄 Employee Payslip                                     │
├─────────────────────────────────────────────────────────┤
│                                                          │
│  👤 EMPLOYEE INFO:                                       │
│  ├─ Name & employee ID                                  │
│  ├─ Pay period                                          │
│  └─ Payment date                                        │
│                                                          │
│  💰 EARNINGS:                                            │
│  ├─ Regular hours                                       │
│  ├─ Overtime                                            │
│  ├─ Bonuses                                             │
│  └─ GROSS PAY                                           │
│                                                          │
│  ➖ DEDUCTIONS:                                          │
│  ├─ PAYE Tax                                            │
│  ├─ KiwiSaver                                           │
│  ├─ Vend Account                                        │
│  └─ Other deductions                                    │
│                                                          │
│  ✅ NET PAY (Take home)                                  │
│                                                          │
│  📥 ACTIONS:                                             │
│  ├─ Download PDF                                        │
│  ├─ Email payslip                                       │
│  └─ Print                                               │
│                                                          │
└─────────────────────────────────────────────────────────┘
```

---

## 5️⃣ RATE LIMIT ANALYTICS
**URL:** `/rate_limit_analytics`

### What's on this page:
```
┌─────────────────────────────────────────────────────────┐
│  📊 API Rate Limit Analytics                            │
├─────────────────────────────────────────────────────────┤
│                                                          │
│  📈 MONITORING:                                          │
│  ├─ Xero API usage                                      │
│  ├─ Deputy API usage                                    │
│  ├─ Vend API usage                                      │
│  └─ Rate limit thresholds                               │
│                                                          │
│  ⚠️  ALERTS:                                             │
│  ├─ Near-limit warnings                                 │
│  ├─ Exceeded limits                                     │
│  └─ Retry/backoff status                                │
│                                                          │
│  📊 CHARTS:                                              │
│  ├─ API calls per hour                                  │
│  ├─ Success/error rates                                 │
│  └─ Response time trends                                │
│                                                          │
└─────────────────────────────────────────────────────────┘
```

---

## 6️⃣ RECONCILIATION
**URL:** `/reconciliation`

### What's on this page:
```
┌─────────────────────────────────────────────────────────┐
│  🔄 Payment Reconciliation                              │
├─────────────────────────────────────────────────────────┤
│                                                          │
│  💳 VEND ACCOUNT PAYMENTS:                               │
│  ├─ Staff purchases from registers                      │
│  ├─ Account balances                                    │
│  ├─ Payment allocations                                 │
│  └─ Outstanding balances                                │
│                                                          │
│  🔗 XERO INTEGRATION:                                    │
│  ├─ Match Vend payments to payroll                      │
│  ├─ Auto-reconcile transactions                         │
│  ├─ Flag discrepancies                                  │
│  └─ Sync status                                         │
│                                                          │
│  ✅ RECONCILIATION STATUS:                               │
│  ├─ Matched (green)                                     │
│  ├─ Pending review (yellow)                             │
│  └─ Unmatched (red)                                     │
│                                                          │
└─────────────────────────────────────────────────────────┘
```

---

## 🎯 NAVIGATION

All pages accessible from:
- **Main dashboard** → Navigate via tabs
- **Direct URLs** → Clean URL routing enabled
- **Menu** → (If header nav implemented)

---

## 🤖 BOT AUTOMATION (Coming Next)

The bot will monitor and process:
- ✅ Timesheet amendments (auto-approve safe ones)
- ✅ Wage discrepancy fixes (AI-powered)
- ✅ Leave request approvals
- ✅ Bonus calculations
- ✅ Payment reconciliation

---

**Status:** ✅ All 6 pages are LIVE and accessible!

**Next Steps:** Configure bot infrastructure to automate these workflows.
