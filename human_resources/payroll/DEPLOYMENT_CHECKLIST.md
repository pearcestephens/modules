# 🎯 DEPLOYMENT CHECKLIST - Payroll AI Automation

**Version:** 2.0.0
**Date:** October 29, 2025
**Deployment Date:** [PENDING]

---

## ✅ Pre-Deployment Checklist

### 1. Database Layer
- [x] Schema deployed (806 lines SQL)
- [x] 26 tables created with proper indexes
- [x] 9 default AI rules inserted
- [x] 2 views created (pending reviews, dashboard)
- [ ] Test data added (at least 2 staff, 1 pay period)
- [ ] Backup created before deployment

### 2. Code Files
- [x] All controller files created (3 controllers, 16 endpoints)
- [x] All service files verified (5 services)
- [x] All cron job files created (3 jobs)
- [x] Test suite created
- [x] Installation script created
- [x] Routes configuration created
- [x] All scripts made executable

### 3. Environment Configuration
- [ ] Database credentials verified
- [ ] Xero API credentials configured:
  - [ ] `XERO_CLIENT_ID`
  - [ ] `XERO_CLIENT_SECRET`
  - [ ] `XERO_REDIRECT_URI`
  - [ ] `XERO_CALENDAR_ID`
  - [ ] `XERO_BANK_ACCOUNT`
- [ ] Deputy API credentials verified (should exist)
- [ ] Vend API credentials verified (should exist)

### 4. Server Requirements
- [x] PHP 8.0+ installed
- [x] MySQL/MariaDB 10.5+ installed
- [x] Required PHP extensions:
  - [x] PDO
  - [x] pdo_mysql
  - [x] curl
  - [x] json
  - [x] mbstring
- [x] Cron daemon running
- [ ] Web server configured (Apache/Nginx)
- [ ] Log directory writable

---

## 🚀 Deployment Steps

### Step 1: Run Installation Script

```bash
cd /home/master/applications/jcepnzzkmj/public_html/modules/human_resources/payroll

# Run installation (without cron)
bash install.sh

# Expected output: "Installation Complete!"
```

**Verification:**
- [ ] All log files created
- [ ] Database connection successful
- [ ] All required tables exist
- [ ] PHP version check passed
- [ ] Scripts are executable

### Step 2: Run Test Suite

```bash
# Test amendment service
php tests/test_amendment_service.php

# Expected output: "All tests passed successfully!"
```

**Verification:**
- [ ] Amendment created successfully
- [ ] AI decision created
- [ ] AI rules executed
- [ ] Decision made (approved/declined/manual)
- [ ] No errors in logs

### Step 3: Install Cron Jobs

```bash
# Install cron jobs
bash install.sh --install-cron

# Verify cron installation
crontab -l | grep payroll
```

**Expected Cron Jobs:**
```
*/5 * * * * /usr/bin/php .../process_automated_reviews.php >> .../payroll_automation.log 2>&1
0 * * * * /usr/bin/php .../sync_deputy.php >> .../deputy_sync.log 2>&1
0 2 * * * /usr/bin/php .../update_dashboard.php >> .../dashboard_stats.log 2>&1
```

**Verification:**
- [ ] All 3 cron jobs installed
- [ ] Cron paths are correct
- [ ] Log paths are correct

### Step 4: Test Cron Jobs Manually

```bash
# Test automation processing
php cron/process_automated_reviews.php

# Test Deputy sync
php cron/sync_deputy.php

# Test dashboard update
php cron/update_dashboard.php
```

**Verification:**
- [ ] No fatal errors
- [ ] Logs show successful execution
- [ ] Database records updated

### Step 5: Configure Web Server Routes

Add to your router/dispatcher:

```php
// Load payroll routes
$payrollRoutes = require '/path/to/payroll/routes.php';

// Register routes with your router
foreach ($payrollRoutes as $route => $config) {
    $router->register($route, $config);
}
```

**Verification:**
- [ ] Routes loaded successfully
- [ ] No route conflicts
- [ ] Auth middleware applied correctly

### Step 6: Test API Endpoints

```bash
# Test 1: Get pending amendments (should return empty array initially)
curl https://staff.vapeshed.co.nz/api/payroll/amendments/pending

# Test 2: Get automation dashboard
curl https://staff.vapeshed.co.nz/api/payroll/automation/dashboard

# Test 3: Get AI rules
curl https://staff.vapeshed.co.nz/api/payroll/automation/rules
```

**Verification:**
- [ ] All endpoints return valid JSON
- [ ] No 500 errors
- [ ] Auth checks working
- [ ] CSRF protection active

### Step 7: Xero OAuth Setup (Optional for now)

```bash
# Navigate to Xero authorization URL
# (This will be done via browser)
GET https://staff.vapeshed.co.nz/api/payroll/xero/oauth/authorize
```

**Verification:**
- [ ] Redirects to Xero login
- [ ] After auth, redirects back to callback
- [ ] Tokens saved to database
- [ ] Access token valid

---

## 🧪 Post-Deployment Testing

### Test 1: Create Amendment via API

```bash
curl -X POST https://staff.vapeshed.co.nz/api/payroll/amendments/create \
  -H "Content-Type: application/json" \
  -H "Cookie: session=YOUR_SESSION" \
  -H "X-CSRF-Token: YOUR_TOKEN" \
  -d '{
    "staff_id": 1,
    "pay_period_id": 1,
    "original_start": "2025-10-29 09:00:00",
    "original_end": "2025-10-29 17:00:00",
    "original_hours": 7.5,
    "new_start": "2025-10-29 09:00:00",
    "new_end": "2025-10-29 18:00:00",
    "new_hours": 8.5,
    "reason": "Worked late on urgent project"
  }'
```

**Expected:**
- [ ] Status 200 OK
- [ ] Amendment ID returned
- [ ] AI decision ID returned
- [ ] Amendment appears in pending list

### Test 2: Wait for Automation (5 minutes)

Monitor the logs:
```bash
tail -f logs/payroll_automation.log
```

**Expected:**
- [ ] Cron runs within 5 minutes
- [ ] Amendment processed
- [ ] AI decision made
- [ ] Appropriate action taken
- [ ] Notification created

### Test 3: Check Dashboard

```bash
curl https://staff.vapeshed.co.nz/api/payroll/automation/dashboard
```

**Expected:**
- [ ] Stats updated
- [ ] Decision counts correct
- [ ] Processing time recorded
- [ ] Daily stats populated

### Test 4: Approve Amendment Manually

```bash
curl -X POST https://staff.vapeshed.co.nz/api/payroll/amendments/123/approve \
  -H "Content-Type: application/json" \
  -H "Cookie: session=YOUR_SESSION" \
  -H "X-CSRF-Token: YOUR_TOKEN" \
  -d '{
    "notes": "Manual approval for testing"
  }'
```

**Expected:**
- [ ] Status 200 OK
- [ ] Amendment status changed to 'approved'
- [ ] Deputy sync queued
- [ ] History record created

### Test 5: Deputy Sync (1 hour)

Monitor the logs:
```bash
tail -f logs/deputy_sync.log
```

**Expected:**
- [ ] Cron runs within 1 hour
- [ ] Amendment synced to Deputy
- [ ] deputy_synced flag set to 1
- [ ] deputy_synced_at timestamp recorded

---

## 🔍 Monitoring (First 24 Hours)

### Log Monitoring

```bash
# Watch all payroll logs
tail -f logs/payroll_automation.log logs/deputy_sync.log logs/dashboard_stats.log
```

### Database Monitoring

```sql
-- Check automation performance
SELECT
    DATE(created_at) as date,
    COUNT(*) as total,
    SUM(CASE WHEN decision = 'approved' THEN 1 ELSE 0 END) as approved,
    SUM(CASE WHEN decision = 'declined' THEN 1 ELSE 0 END) as declined,
    AVG(confidence_score) as avg_confidence
FROM payroll_ai_decisions
WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
GROUP BY DATE(created_at);

-- Check for errors
SELECT * FROM payroll_activity_log
WHERE level IN ('error', 'critical')
AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
ORDER BY created_at DESC;

-- Check cron execution
SELECT
    MAX(created_at) as last_run,
    COUNT(*) as total_decisions_today
FROM payroll_ai_decisions
WHERE DATE(created_at) = CURDATE();
```

### Health Checks

Every hour, check:
- [ ] Cron jobs running (check logs timestamps)
- [ ] No critical errors in logs
- [ ] Database connections stable
- [ ] API endpoints responding
- [ ] Deputy sync success rate

---

## 🚨 Rollback Plan

If critical issues occur:

### Immediate Rollback

```bash
# 1. Disable cron jobs
crontab -l > cron_backup.txt
crontab -r

# 2. Restore database backup
mysql -u jcepnzzkmj -p'wprKh9Jq63' jcepnzzkmj < backup_before_deployment.sql

# 3. Remove API routes from router
# (Comment out route registration in your router)

# 4. Notify team
echo "Payroll automation rolled back at $(date)" | mail -s "ROLLBACK" admin@example.com
```

### Partial Rollback (Keep database, disable automation)

```bash
# Just disable cron jobs
crontab -r

# Amendments can still be created manually
# No AI automation will run
```

---

## 📊 Success Metrics

Monitor these for 48 hours:

### Critical Metrics
- [ ] Zero critical errors
- [ ] Cron jobs running on schedule
- [ ] API uptime: 100%
- [ ] Database uptime: 100%

### Performance Metrics
- [ ] Average API response time < 500ms
- [ ] AI decision time < 5s
- [ ] Deputy sync success rate > 95%
- [ ] Cron processing time < 30s

### Business Metrics
- [ ] At least 5 amendments processed
- [ ] Auto-approval rate > 60%
- [ ] Manual review rate < 30%
- [ ] Zero staff complaints

---

## ✅ Go-Live Approval

**Sign-off required from:**

- [ ] **Developer:** All code tested, no known bugs
- [ ] **Database Admin:** Schema deployed, backups verified
- [ ] **System Admin:** Cron jobs configured, monitoring active
- [ ] **Payroll Manager:** Test workflows completed successfully
- [ ] **Business Owner:** Approved for production use

**Final Check:**
- [ ] All checkboxes above are checked
- [ ] Rollback plan tested and ready
- [ ] Team notified of go-live
- [ ] Monitoring dashboard accessible
- [ ] Support contact information distributed

---

## 📞 Support Contacts

### Technical Issues
- **Developer:** [Your contact]
- **Database:** [DBA contact]
- **System Admin:** [Sysadmin contact]

### Business Issues
- **Payroll Manager:** [Manager contact]
- **HR Director:** [Director contact]

### Emergency
- **On-Call:** [On-call contact]
- **Escalation:** [Escalation contact]

---

## 📝 Post-Deployment Notes

**Deployment Date:** _______________
**Deployed By:** _______________
**Go-Live Time:** _______________
**Rollback Time (if needed):** _______________

**Issues Encountered:**
```
[List any issues and resolutions]
```

**Performance Notes:**
```
[Record actual vs expected performance]
```

**Action Items:**
```
[ ] ...
[ ] ...
```

---

**Status:** ⏳ PENDING DEPLOYMENT
**Last Updated:** October 29, 2025
**Next Review:** [After 24 hours of production use]
