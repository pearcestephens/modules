# Payroll Module - URL Structure Guide

## 📍 URL Routing

The payroll module uses **index.php** as a single entry point with flexible routing that supports both query parameters and future clean URLs.

---

## 🔗 URL Formats

### Current (Query Parameter) Style
```
/modules/human_resources/payroll/index.php?view={page}
/modules/human_resources/payroll/index.php?api={endpoint}
```

### Future (Clean URL) Style (when .htaccess is configured)
```
/modules/human_resources/payroll/{page}
/modules/human_resources/payroll/api/{endpoint}
```

Both styles work with the same index.php dispatcher!

---

## 📄 View Routes (HTML Pages)

### Dashboard
- **Current:** `/modules/human_resources/payroll/index.php?view=dashboard`
- **Future:** `/modules/human_resources/payroll/dashboard`
- **Permission:** `payroll.view_dashboard`
- **Description:** Main dashboard with all 5 sections

### Default (no params)
- **Current:** `/modules/human_resources/payroll/` or `/modules/human_resources/payroll/index.php`
- **Redirects to:** Dashboard
- **Permission:** `payroll.view_dashboard`

---

## 🔌 API Routes (JSON Responses)

### Dashboard API
```
GET /modules/human_resources/payroll/index.php?api=dashboard/data
```
**Returns:** Aggregated statistics for all sections
**Auth:** Required
**Permission:** `payroll.view_dashboard`

### Amendments
```
POST /index.php?api=amendments/create
GET  /index.php?api=amendments/:id
POST /index.php?api=amendments/:id/approve
POST /index.php?api=amendments/:id/decline
GET  /index.php?api=amendments/pending
GET  /index.php?api=amendments/history
```

### Discrepancies
```
POST /index.php?api=discrepancies/submit
GET  /index.php?api=discrepancies/:id
GET  /index.php?api=discrepancies/pending
GET  /index.php?api=discrepancies/my-history
POST /index.php?api=discrepancies/:id/approve
POST /index.php?api=discrepancies/:id/decline
POST /index.php?api=discrepancies/:id/upload-evidence
GET  /index.php?api=discrepancies/statistics
```

### Bonuses
```
GET  /index.php?api=bonuses/pending
GET  /index.php?api=bonuses/history
POST /index.php?api=bonuses/create
POST /index.php?api=bonuses/:id/approve
POST /index.php?api=bonuses/:id/decline
GET  /index.php?api=bonuses/summary
GET  /index.php?api=bonuses/vape-drops
GET  /index.php?api=bonuses/google-reviews
```

### Vend Payments
```
GET  /index.php?api=vend-payments/pending
GET  /index.php?api=vend-payments/history
GET  /index.php?api=vend-payments/:id/allocations
POST /index.php?api=vend-payments/:id/approve
POST /index.php?api=vend-payments/:id/decline
GET  /index.php?api=vend-payments/statistics
```

### Leave Requests
```
GET  /index.php?api=leave/pending
GET  /index.php?api=leave/history
POST /index.php?api=leave/create
POST /index.php?api=leave/:id/approve
POST /index.php?api=leave/:id/decline
GET  /index.php?api=leave/balances
```

### Automation
```
GET  /index.php?api=automation/dashboard
GET  /index.php?api=automation/reviews/pending
POST /index.php?api=automation/process
GET  /index.php?api=automation/rules
GET  /index.php?api=automation/stats
```

### Xero Integration
```
POST /index.php?api=xero/payrun/create
GET  /index.php?api=xero/payrun/:id
POST /index.php?api=xero/payments/batch
GET  /index.php?api=xero/oauth/authorize
GET  /index.php?api=xero/oauth/callback
```

---

## 🔒 Authentication & Permissions

### Authentication Required
All routes require authentication except:
- Error pages (404, 500)
- OAuth callbacks (handled specially)

**Unauthenticated users:**
- **Views:** Redirect to `/login.php?redirect={current_url}`
- **API:** Return `401` with JSON error

### Permissions
Different permissions for different actions:
- `payroll.view_dashboard` - View dashboard and basic data
- `payroll.approve_amendments` - Approve/decline amendments
- `payroll.approve_discrepancies` - Approve/decline wage discrepancies
- `payroll.approve_bonuses` - Approve/decline bonuses
- `payroll.approve_vend_payments` - Approve/decline Vend account payments
- `payroll.approve_leave` - Approve/decline leave requests
- `payroll.manage_automation` - Configure AI automation rules
- `payroll.xero_admin` - Manage Xero integration

**Admin role:** Has all permissions automatically

---

## 🧪 Testing

### Test Dashboard (requires auth)
```bash
curl -I "https://staff.vapeshed.co.nz/modules/human_resources/payroll/index.php?view=dashboard"
# Should return: 302 redirect to login (if not authenticated)
```

### Test API (requires auth)
```bash
curl -s "https://staff.vapeshed.co.nz/modules/human_resources/payroll/index.php?api=dashboard/data"
# Should return: 401 JSON error (if not authenticated)
```

### With Authentication
```bash
# Set PHPSESSID cookie from browser or login
curl -b "PHPSESSID=your_session_id" \
  "https://staff.vapeshed.co.nz/modules/human_resources/payroll/index.php?api=dashboard/data"
# Should return: JSON with dashboard data
```

---

## 🔄 Future: Clean URLs with .htaccess

To enable clean URLs, add this `.htaccess` in the payroll folder:

```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteBase /modules/human_resources/payroll/

    # Redirect to index.php for views
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule ^([^/]+)/?$ index.php?view=$1 [QSA,L]

    # Redirect to index.php for API
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^api/(.+)$ index.php?api=$1 [QSA,L]
</IfModule>
```

This will enable URLs like:
- `/modules/human_resources/payroll/dashboard`
- `/modules/human_resources/payroll/api/dashboard/data`

The index.php router is already prepared to handle both styles!

---

## 📝 Notes

1. **Single Entry Point:** All requests go through `index.php`
2. **Flexible Routing:** Works with or without `.htaccess`
3. **Security First:** Auth and permission checks on every route
4. **RESTful:** Uses proper HTTP methods (GET, POST, PUT, DELETE)
5. **CSRF Protected:** All POST/PUT/DELETE require CSRF token
6. **Parameter Support:** Routes like `:id` extract parameters automatically

---

## 🎯 Quick Reference

| What | URL Pattern | Example |
|------|-------------|---------|
| **View Dashboard** | `?view=dashboard` | `index.php?view=dashboard` |
| **API Call** | `?api={endpoint}` | `index.php?api=dashboard/data` |
| **With ID** | `?api={endpoint}/{id}` | `index.php?api=amendments/123` |
| **Default** | `index.php` or `/` | Redirects to dashboard |

All API responses return JSON with structure:
```json
{
  "success": true|false,
  "data": {...},      // On success
  "error": "...",     // On failure
  "message": "..."    // Optional details
}
```

---

**Version:** 2.0.0
**Last Updated:** October 29, 2025
**Status:** ✅ Production Ready
