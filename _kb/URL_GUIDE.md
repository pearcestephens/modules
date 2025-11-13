# Payroll Module - URL Guide

## ✅ CORRECT URLs to Access the System

### Main Dashboard
```
https://staff.vapeshed.co.nz/modules/human_resources/payroll/
```
- This is the main entry point
- Shows the payroll dashboard
- Must be logged in

### Pay Runs List
```
https://staff.vapeshed.co.nz/modules/human_resources/payroll/?view=payruns
```
- Shows list of all pay runs
- With filters and search
- Must be logged in

### Pay Run Detail
```
https://staff.vapeshed.co.nz/modules/human_resources/payroll/?view=payrun&period=2025-01-13_2025-01-19
```
- Shows detail for specific pay run
- Replace period with actual period key
- Must be logged in

### API Endpoints (AJAX only)
```
https://staff.vapeshed.co.nz/modules/human_resources/payroll/?api=payruns/list
```
- Returns JSON data
- Must be logged in
- Must have valid session

## ❌ These URLs Will NOT Work

### Missing trailing slash:
```
❌ https://staff.vapeshed.co.nz/modules/human_resources/payroll
```
**Fix:** Add trailing slash or index.php

### Wrong path:
```
❌ https://staff.vapeshed.co.nz/payroll/
❌ https://staff.vapeshed.co.nz/modules/payroll/
```
**Fix:** Use full path: `/modules/human_resources/payroll/`

## 🔍 Troubleshooting

### If you see "404 Not Found":

1. **Check the exact URL** - Make sure it matches one of the correct URLs above
2. **Clear browser cache** - Press Ctrl+Shift+Delete
3. **Try direct URL** - `/modules/human_resources/payroll/index.php`
4. **Check if logged in** - System requires authentication

### If you see "403 Forbidden":

1. **Check permissions** - Your user account needs access to payroll module
2. **Contact admin** - May need to grant permissions

### If you see "500 Server Error":

1. **Check error log** - See errors below
2. **Report the error** - With exact time it occurred

## 📊 System Status

**Last Test:** October 29, 2025 07:25 UTC
**Status:** ✅ OPERATIONAL
**HTTP Response:** 302 Redirect (authentication required)
**Errors:** None detected

## 🛠️ For Developers

### Check Error Logs:
```bash
tail -50 /home/master/applications/jcepnzzkmj/logs/apache_phpstack-129337-518184.cloudwaysapps.com.error.log
```

### Test Without Browser:
```bash
curl -I https://staff.vapeshed.co.nz/modules/human_resources/payroll/
```

### Expected Response:
```
HTTP/2 302
location: /login.php?redirect=%2Fmodules%2Fhuman_resources%2Fpayroll%2F
```

This means the system is working - it's redirecting to login as expected.
