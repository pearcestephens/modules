# 🚀 Quick Start Guide - Payroll Dashboard

## Access the Dashboard

### Main Dashboard URL
```
https://staff.vapeshed.co.nz/modules/human_resources/payroll/index.php?view=dashboard
```

### Or simply
```
https://staff.vapeshed.co.nz/modules/human_resources/payroll/
```
(Automatically redirects to dashboard)

---

## 📋 5 Sections Available

1. **Timesheet Amendments** - Correct hours/shifts
2. **Wage Discrepancies** - Report pay issues (AI-powered)
3. **Bonuses** - Monthly/Vape Drops/$6 each/Google Reviews
4. **Vend Payments** - Staff purchase deductions
5. **Leave Requests** - Request time off

---

## 🔑 Required Permissions

### View Dashboard
- Permission: `payroll.view_dashboard`
- Who: All staff (can view own data)

### Approve Actions
- `payroll.approve_amendments`
- `payroll.approve_discrepancies`
- `payroll.approve_bonuses`
- `payroll.approve_vend_payments`
- `payroll.approve_leave`
- Who: Managers/Admin only

---

## 🎨 Dashboard Features

✅ **5 Statistics Cards** - Live counts
✅ **Auto-Refresh** - Updates every 30 seconds
✅ **Beautiful Design** - Purple gradient header
✅ **Status Badges** - Color-coded (pending/approved/declined)
✅ **AI Indicators** - Confidence scores for automated decisions
✅ **Toast Notifications** - Instant feedback
✅ **Lazy Loading** - Fast initial load

---

## 📊 Stats Overview

The dashboard shows:
- **Pending Items** - Awaiting your action
- **Urgent Items** - Need immediate attention
- **AI Reviews** - Flagged for human check
- **Auto-Approved** - Processed by AI (last 7 days)
- **Total Bonuses** - This month's bonuses

---

## 🔧 For Developers

### API Base URL
```
/modules/human_resources/payroll/index.php?api={endpoint}
```

### Example API Calls
```javascript
// Get dashboard data
fetch('index.php?api=dashboard/data')

// Get pending amendments
fetch('index.php?api=amendments/pending')

// Approve a bonus
fetch('index.php?api=bonuses/123/approve', {
    method: 'POST',
    headers: {
        'X-CSRF-TOKEN': csrfToken
    }
})
```

### All Endpoints
See: `README_URLS.md` for complete list of 46 API endpoints

---

## 🐛 Troubleshooting

### Can't Access Dashboard
- Check you're logged in
- Verify you have `payroll.view_dashboard` permission
- Check with admin if permission needed

### Can't Approve Items
- Need specific approval permissions
- Contact admin to grant:
  - `payroll.approve_amendments`
  - `payroll.approve_discrepancies`
  - `payroll.approve_bonuses`
  - `payroll.approve_vend_payments`
  - `payroll.approve_leave`

### Empty Dashboard
- Check if you have any pending items
- Try refreshing (auto-refreshes every 30s)
- Check filter settings (coming soon)

---

## 📱 Browser Support

✅ Chrome/Edge (recommended)
✅ Firefox
✅ Safari
✅ Mobile browsers

---

## 🎯 Quick Actions

### As Staff Member
1. Go to dashboard
2. Click tab for what you need
3. View your items
4. Submit new requests

### As Manager
1. Go to dashboard
2. Check "Pending Items" count
3. Click relevant tab
4. Review and approve/decline
5. AI confidence helps prioritize

---

## 💡 Pro Tips

- **Badge Counts** on tabs show pending items
- **Urgent Items** are highlighted in red
- **AI Confidence** bars help prioritize reviews
- **Auto-Refresh** keeps data current
- **Hover Effects** show additional info

---

## 📞 Support

Issues? Contact:
- **IT Support:** helpdesk@vapeshed.co.nz
- **Payroll:** payroll@vapeshed.co.nz

---

**Last Updated:** October 29, 2025
**Version:** 2.0.0
**Status:** ✅ Production Ready
