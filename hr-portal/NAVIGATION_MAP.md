# HR PORTAL - NAVIGATION MAP

## Visual Navigation Structure

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                         HR PORTAL - index.php                               │
│                     (Auto-Pilot Control Center)                             │
│                                                                              │
│  ┌─────────────────────── QUICK NAVIGATION ──────────────────────────┐     │
│  │                                                                     │     │
│  │  [Staff Directory]  [Deputy & Xero]  [All Timesheets]  [All Payroll]    │
│  │         ↓                  ↓               ↓                ↓      │     │
│  └─────────┼──────────────────┼───────────────┼────────────────┼─────┘     │
│            │                  │               │                │            │
└────────────┼──────────────────┼───────────────┼────────────────┼────────────┘
             │                  │               │                │
             ↓                  ↓               │                │
                                               (future)       (future)
   ┌──────────────────┐    ┌──────────────────────────────────────┐
   │ STAFF DIRECTORY  │    │  INTEGRATIONS DASHBOARD              │
   │                  │    │  integrations.php                    │
   │ - Search staff   │    │                                      │
   │ - Filter active  │    │  ┌─────────────┐  ┌─────────────┐  │
   │ - See badges     │    │  │   DEPUTY    │  │    XERO     │  │
   │                  │    │  │  Connection │  │  Connection │  │
   │ Each card has:   │    │  │   Status    │  │   Status    │  │
   │ ┌──────────────┐ │    │  └─────────────┘  └─────────────┘  │
   │ │ Avatar (AB)  │ │    │                                      │
   │ │ John Smith   │ │    │  Actions:                           │
   │ │ ✓ Deputy     │ │    │  • Test Connection                  │
   │ │ ✓ Xero       │ │    │  • Sync Employees ──────┐           │
   │ │ ⚠ 2 pending  │ │    │  • Sync Timesheets      │           │
   │ │              │ │    │  • Sync Pay Runs        │           │
   │ │ [View Detail]│ ──┐  │  • View Logs            │           │
   │ │ [Timesheets] │ ──┼─┐│                          │           │
   │ │ [Payroll]    │ ──┼─┼┤  Recent Sync Activity:  │           │
   │ └──────────────┘ │ │ ││  - Last 50 syncs        │           │
   └──────────────────┘ │ ││  - Status (✓/✗)         │           │
                        │ ││  - External IDs          │           │
                        │ │└──────────────────────────┼───────────┘
                        │ │                           │
                        │ │                           ↓
                        │ │              Updates staff.deputy_id
                        │ │              Updates staff.xero_id
                        │ │
                        │ │
      ┌─────────────────┘ │
      │  ┌────────────────┘
      │  │
      ↓  ↓  ↓
   ┌────────────────────────────────────────────────┐
   │      STAFF DETAIL - staff-detail.php           │
   │      Breadcrumb: HR Portal > Staff Directory   │
   │                                                 │
   │  ┌───────┐  John Smith                         │
   │  │  JS   │  john@example.com                   │
   │  │       │  Deputy: 12345  Xero: ABC-123       │
   │  └───────┘  Status: Active                     │
   │                                                 │
   │  ┌─────────────────────────────────────────┐   │
   │  │ [Overview] [Timesheets] [Payroll] [AI]  │   │
   │  │                                          │   │
   │  │  Overview Tab:                          │   │
   │  │  • Quick Stats (pending, auto-approved) │   │
   │  │  • Activity Timeline (last 10 actions)  │   │
   │  │                                          │   │
   │  │  Timesheets Tab:                        │   │
   │  │  • Last 10 amendments                   │   │
   │  │  • [View All Timesheets] ───────────┐   │   │
   │  │                                      │   │   │
   │  │  Payroll Tab:                        │   │   │
   │  │  • Last 10 pay runs                  │   │   │
   │  │  • [View All Payroll] ──────────┐    │   │   │
   │  │                                  │    │   │   │
   │  │  AI History Tab:                 │    │   │   │
   │  │  • 20 AI decisions with conf.   │    │   │   │
   │  └──────────────────────────────────┼────┼───┘   │
   └─────────────────────────────────────┼────┼───────┘
                                         │    │
                    ┌────────────────────┘    └───────────────┐
                    ↓                                          ↓
   ┌────────────────────────────────────┐    ┌────────────────────────────────────┐
   │ STAFF TIMESHEETS                   │    │ STAFF PAYROLL                      │
   │ staff-timesheets.php               │    │ staff-payroll.php                  │
   │                                    │    │                                    │
   │ Breadcrumb: HR Portal > Staff Dir  │    │ Breadcrumb: HR Portal > Staff Dir  │
   │           > John Smith > Timesheets│    │           > John Smith > Payroll   │
   │                                    │    │                                    │
   │ Filter: [All] [Pending] [Approved] │    │ Filter: [All] [Pending] [Approved] │
   │                                    │    │                                    │
   │ [Sync All Approved to Deputy] ───┐ │    │ YTD Summary:                       │
   │                                  │ │    │ ┌──────┐ ┌──────┐ ┌──────┐        │
   │ Table (20/page):                 │ │    │ │$5,432│ │  12  │ │  15  │        │
   │ ┌──────────────────────────────┐ │ │    │ │Adjust│ │Apprvd│ │Total │        │
   │ │Date │Orig│New│Diff│Status│AI│ │ │    │ └──────┘ └──────┘ └──────┘        │
   │ │     │Hrs │Hrs│    │      │  │ │ │    │                                    │
   │ ├──────────────────────────────┤ │ │    │ [Sync All to Xero] [Export] ───┐  │
   │ │10Jan│8.0 │9.5│+1.5│✓ Appr│✓ │ │ │    │                                │  │
   │ │     │    │   │↑   │      │AI│ │ │    │ Table (20/page):               │  │
   │ │     │    │   │    │Synced│90│ │ │    │ ┌────────────────────────────┐ │  │
   │ │     │[View] [Sync to Deputy]───┼─┘    │ │Date│Orig │Adj │New│Status│ │ │  │
   │ ├──────────────────────────────┤ │      │ ├────────────────────────────┤ │  │
   │ │09Jan│8.0 │8.0 │  0 │Pending │ │      │ │Jan │$2100│+$50│$2150│✓ App│ │ │  │
   │ │     │    │    │    │Not Sync│ │      │ │    │     │↑   │     │Synced│ │  │
   │ │     │[View]              │    │      │ │    │[View] [Sync to Xero]──┼──┘
   │ └──────────────────────────────┘ │      │ ├────────────────────────────┤ │
   │                                  │      │ │Dec │$2000│  $0│$2000│Appvd│ │
   │ Pagination: [1] [2] [3] ...      │      │ │    │     │    │     │Not  │ │
   └──────────────────────────────────┘      │ │    │[View]                │ │
                      │                       │ └────────────────────────────┘ │
                      │                       │                                │
                      │                       │ Pagination: [1] [2] [3] ...    │
                      ↓                       └────────────────────────────────┘
                                                             │
           API: sync-timesheet.php                          ↓
           • POST with amendment ID          API: sync-payrun.php
           • Calls DeputyIntegration         • POST with amendment ID
           • Logs to integration_sync_log    • Calls XeroIntegration
           • Returns success/error JSON      • Logs to integration_sync_log
                      ↓                       • Returns success/error JSON
                                                             ↓
           ┌────────────────────────┐        ┌────────────────────────┐
           │ Deputy API             │        │ Xero API               │
           │ • Update timesheet     │        │ • Update pay run       │
           │ • Return external ID   │        │ • Return external ID   │
           └────────────────────────┘        └────────────────────────┘
```

## Navigation Patterns

### 1. **Top-Down Browsing** (Explore all staff)
```
index.php → staff-directory.php → staff-detail.php → staff-timesheets.php
                                                   → staff-payroll.php
```

### 2. **Direct Access** (Quick navigation from dashboard)
```
index.php → integrations.php (test/sync Deputy/Xero)
index.php → timesheets-all.php (future: all timesheets)
index.php → payroll-all.php (future: all payroll)
```

### 3. **Drill-Down** (From summary to detail)
```
staff-directory.php → staff-detail.php (Overview tab) → staff-timesheets.php
                                                      → staff-payroll.php
```

### 4. **Lateral Navigation** (Between related pages)
```
staff-timesheets.php ←→ staff-detail.php ←→ staff-payroll.php
         ↑                     ↓                     ↑
         └─── staff-directory.php ────────────────┘
```

### 5. **Action Flow** (Sync process)
```
integrations.php → Click "Sync Employees" → api/sync-deputy.php
                                           → Updates staff.deputy_id
                                           → Logs to integration_sync_log
                                           → Reloads with success message

staff-timesheets.php → Click "Sync to Deputy" → api/sync-timesheet.php
                                                → Calls DeputyIntegration
                                                → Updates Deputy via API
                                                → Logs sync
                                                → Reloads showing "Synced" status
```

## Badge System (Visual Status Indicators)

### Deputy Status:
- 🟢 **Green Badge** "Deputy: 12345" - Staff has deputy_id, linked
- ⚪ **Gray Badge** "No Deputy ID" - Staff not linked to Deputy

### Xero Status:
- 🔵 **Blue Badge** "Xero: ABC-123" - Staff has xero_id, linked
- ⚪ **Gray Badge** "No Xero ID" - Staff not linked to Xero

### Sync Status:
- ✅ **Green "Synced"** - Successfully synced, shows external ID
- ❌ **Red "Error"** - Sync failed, click to view error details
- ⚪ **Gray "Not Synced"** - Approved but not yet synced

### Amendment Status:
- 🟡 **Yellow "Pending"** - Awaiting human/AI review
- 🟢 **Green "Approved"** - Ready to sync
- 🔴 **Red "Denied"** - Rejected by human/AI

### AI Decision:
- 🟢 **Green "AI: Approve (95%)"** - AI recommends approval with confidence
- 🔴 **Red "AI: Deny (87%)"** - AI recommends denial with confidence
- ⚪ **Gray "No AI"** - Manual review, no AI decision

## Data Flow

```
┌──────────────────────────────────────────────────────────────────┐
│                      DEPUTY API                                   │
│  • Employees (GET /resource/Employee)                            │
│  • Timesheets (GET /resource/Timesheet)                          │
└────────────────────┬─────────────────────────────────────────────┘
                     │ Deputy sync
                     ↓
          ┌──────────────────────┐
          │  DeputyIntegration   │ ← Wrapper class
          │  (uses existing      │
          │   DeputyService,     │
          │   DeputyApiClient)   │
          └──────────┬───────────┘
                     │
                     ↓
┌────────────────────────────────────────────────────────────────────┐
│                      CIS DATABASE                                  │
│  • staff (deputy_id, xero_id)                                     │
│  • payroll_timesheet_amendments                                   │
│  • payroll_payrun_amendments                                      │
│  • payroll_ai_decisions                                           │
│  • integration_sync_log                                           │
└────────────────────┬───────────────────────────────────────────────┘
                     │
                     ↓
          ┌──────────────────────┐
          │  XeroIntegration     │ ← Wrapper class
          │  (uses XeroServiceSDK│
          │   PayrollXeroService)│
          └──────────┬───────────┘
                     │ Xero sync
                     ↓
┌──────────────────────────────────────────────────────────────────┐
│                      XERO API                                     │
│  • Employees (GET /payroll.xro/2.0/Employees)                    │
│  • Pay Runs (GET /payroll.xro/2.0/PayRuns)                       │
│  • Leave Applications (GET /payroll.xro/2.0/LeaveApplications)   │
└──────────────────────────────────────────────────────────────────┘
```

## Breadcrumb Examples

```
index.php
  └─ "HR Portal" (active)

staff-directory.php
  └─ "HR Portal" > "Staff Directory" (active)

staff-detail.php
  └─ "HR Portal" > "Staff Directory" > "John Smith" (active)

staff-timesheets.php
  └─ "HR Portal" > "Staff Directory" > "John Smith" > "Timesheets" (active)

staff-payroll.php
  └─ "HR Portal" > "Staff Directory" > "John Smith" > "Payroll" (active)

integrations.php
  └─ "HR Portal" > "Integrations" (active)
```

---

**Every page is connected. Every integration is visible. Everything is browsable.** ✅
