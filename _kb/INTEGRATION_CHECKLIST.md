# Wage Discrepancy System - Integration Checklist

**Use this checklist to integrate the wage discrepancy system into your live environment.**

---

## ✅ Pre-Installation Checks

- [ ] Confirm database credentials: `jcepnzzkmj` / `wprKh9Jq63`
- [ ] Verify tables don't already exist:
  ```bash
  mysql -u jcepnzzkmj -p'wprKh9Jq63' jcepnzzkmj \
    -e "SHOW TABLES LIKE 'payroll_wage_%'"
  ```
- [ ] Backup existing `payroll_wage_issues` table (if migrating):
  ```bash
  mysqldump -u jcepnzzkmj -p'wprKh9Jq63' jcepnzzkmj \
    payroll_wage_issues payroll_wage_issue_events > \
    /home/master/applications/jcepnzzkmj/local_backups/wage_issues_backup_$(date +%Y%m%d).sql
  ```

---

## 🗄️ Database Setup

### Step 1: Execute Schema

```bash
cd /home/master/applications/jcepnzzkmj/public_html/modules/human_resources/payroll/_schema

mysql -u jcepnzzkmj -p'wprKh9Jq63' jcepnzzkmj < wage_discrepancies_schema.sql
```

**Expected output:** Query OK, 0 rows affected (x2)

### Step 2: Verify Tables Created

```bash
mysql -u jcepnzzkmj -p'wprKh9Jq63' jcepnzzkmj -e "
  SELECT
    TABLE_NAME,
    TABLE_ROWS,
    CREATE_TIME
  FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = 'jcepnzzkmj'
  AND TABLE_NAME LIKE 'payroll_wage_%'
"
```

**Expected:**
```
+----------------------------------+------------+---------------------+
| TABLE_NAME                       | TABLE_ROWS | CREATE_TIME         |
+----------------------------------+------------+---------------------+
| payroll_wage_discrepancies       |          0 | 2025-01-27 10:30:00 |
| payroll_wage_discrepancy_events  |          0 | 2025-01-27 10:30:00 |
+----------------------------------+------------+---------------------+
```

- [ ] Both tables exist
- [ ] CREATE_TIME is today

### Step 3: Verify Foreign Keys

```bash
mysql -u jcepnzzkmj -p'wprKh9Jq63' jcepnzzkmj -e "
  SELECT
    CONSTRAINT_NAME,
    TABLE_NAME,
    REFERENCED_TABLE_NAME
  FROM information_schema.KEY_COLUMN_USAGE
  WHERE TABLE_SCHEMA = 'jcepnzzkmj'
  AND TABLE_NAME = 'payroll_wage_discrepancies'
  AND REFERENCED_TABLE_NAME IS NOT NULL
"
```

**Expected 5 foreign keys:**
- fk_discrepancy_staff → users
- fk_discrepancy_payslip → payroll_payslips
- fk_discrepancy_approved_by → users
- fk_discrepancy_declined_by → users
- fk_discrepancy_amendment → payroll_amendments

- [ ] All 5 foreign keys exist
- [ ] All reference correct tables

---

## 📁 File System Setup

### Step 4: Create Evidence Directory

```bash
# Create directory
mkdir -p /home/master/applications/jcepnzzkmj/private/payroll_evidence

# Set permissions (web server can write)
chmod 750 /home/master/applications/jcepnzzkmj/private/payroll_evidence

# Set ownership
chown master:www-data /home/master/applications/jcepnzzkmj/private/payroll_evidence
```

### Step 5: Verify Directory

```bash
ls -ld /home/master/applications/jcepnzzkmj/private/payroll_evidence
```

**Expected:** `drwxr-x--- 2 master www-data ... payroll_evidence`

- [ ] Directory exists
- [ ] Permissions are 750
- [ ] Owner is master
- [ ] Group is www-data

---

## 🔌 Routes Setup

### Step 6: Check Router Configuration

**File:** `/modules/human_resources/payroll/routes.php`

**Add these routes:**

```php
// =============================================================================
// WAGE DISCREPANCY ENDPOINTS
// =============================================================================

// Submit new discrepancy (Staff)
$router->post('/api/payroll/discrepancies/submit', [
    'controller' => 'WageDiscrepancyController',
    'action' => 'submit',
    'permission' => 'payroll.submit_discrepancy'
]);

// Get single discrepancy (Staff: own only, Admin: all)
$router->get('/api/payroll/discrepancies/{id}', [
    'controller' => 'WageDiscrepancyController',
    'action' => 'getDiscrepancy',
    'permission' => 'payroll.view_discrepancy'
]);

// Get pending queue (Admin only)
$router->get('/api/payroll/discrepancies/pending', [
    'controller' => 'WageDiscrepancyController',
    'action' => 'getPending',
    'permission' => 'payroll.manage_discrepancies'
]);

// Get my history (Staff)
$router->get('/api/payroll/discrepancies/my-history', [
    'controller' => 'WageDiscrepancyController',
    'action' => 'getMyHistory',
    'permission' => 'payroll.view_discrepancy'
]);

// Approve discrepancy (Admin only)
$router->post('/api/payroll/discrepancies/{id}/approve', [
    'controller' => 'WageDiscrepancyController',
    'action' => 'approve',
    'permission' => 'payroll.manage_discrepancies'
]);

// Decline discrepancy (Admin only)
$router->post('/api/payroll/discrepancies/{id}/decline', [
    'controller' => 'WageDiscrepancyController',
    'action' => 'decline',
    'permission' => 'payroll.manage_discrepancies'
]);

// Upload evidence (Staff: own only, Admin: all)
$router->post('/api/payroll/discrepancies/{id}/upload-evidence', [
    'controller' => 'WageDiscrepancyController',
    'action' => 'uploadEvidence',
    'permission' => 'payroll.submit_discrepancy'
]);

// Get statistics (Admin only)
$router->get('/api/payroll/discrepancies/statistics', [
    'controller' => 'WageDiscrepancyController',
    'action' => 'getStatistics',
    'permission' => 'payroll.manage_discrepancies'
]);
```

- [ ] Routes added to routes.php
- [ ] Syntax validated: `php -l routes.php`

---

## 🧪 API Testing

### Step 7: Test Endpoint Availability

```bash
# Test 1: Submit endpoint (should require auth)
curl -I https://staff.vapeshed.co.nz/api/payroll/discrepancies/submit

# Expected: 401 Unauthorized or 403 Forbidden
```

- [ ] Endpoint returns 401/403 (not 404)

```bash
# Test 2: Pending endpoint (should require auth)
curl -I https://staff.vapeshed.co.nz/api/payroll/discrepancies/pending

# Expected: 401 Unauthorized or 403 Forbidden
```

- [ ] Endpoint returns 401/403 (not 404)

### Step 8: Test Authentication

**Login as staff member first, then:**

```bash
# Get CSRF token
TOKEN=$(curl -s https://staff.vapeshed.co.nz/api/csrf-token | jq -r '.token')

# Test submission (with fake data)
curl -X POST https://staff.vapeshed.co.nz/api/payroll/discrepancies/submit \
  -H "Content-Type: application/json" \
  -H "X-CSRF-Token: $TOKEN" \
  -b cookies.txt \
  -d '{
    "payslip_id": 999999,
    "discrepancy_type": "underpaid_hours",
    "description": "Test submission for integration testing - please ignore this test record",
    "claimed_hours": 1.0,
    "claimed_amount": 23.00
  }'
```

**Expected responses:**
- ✅ Success: `{"success": true, "discrepancy_id": 1, ...}`
- ❌ Fail (payslip not found): `{"success": false, "error": "Payslip not found"}`
- ❌ Fail (validation): `{"success": false, "error": "Validation error"}`

- [ ] API responds (not 500 error)
- [ ] JSON response returned
- [ ] Error messages are clear

---

## 🔍 Service Layer Testing

### Step 9: Test Service Directly

Create test script: `/modules/human_resources/payroll/tests/test_discrepancy_service.php`

```php
<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/app.php';

use PayrollModule\Services\WageDiscrepancyService;

$service = new WageDiscrepancyService();

// Test 1: Submit low-risk discrepancy
echo "Test 1: Low-risk submission...\n";
$result = $service->submitDiscrepancy([
    'staff_id' => 1, // Replace with real staff ID
    'payslip_id' => 1, // Replace with real payslip ID
    'discrepancy_type' => 'underpaid_hours',
    'description' => 'Test: I worked 2 extra hours on Monday but they were not included in my pay',
    'claimed_hours' => 2.0,
    'claimed_amount' => 46.00
]);

print_r($result);

// Test 2: Get statistics
echo "\nTest 2: Get statistics...\n";
$stats = $service->getStatistics();
print_r($stats);

echo "\nTests complete!\n";
```

**Run:**
```bash
cd /home/master/applications/jcepnzzkmj/public_html/modules/human_resources/payroll/tests
php test_discrepancy_service.php
```

**Expected output:**
```
Test 1: Low-risk submission...
Array
(
    [success] => 1
    [discrepancy_id] => 1
    [status] => auto_approved
    [ai_analysis] => Array
        (
            [risk_score] => 0.15
            [confidence] => 0.85
            ...
        )
)

Test 2: Get statistics...
Array
(
    [total] => 1
    [pending] => 0
    [auto_approved] => 1
    ...
)

Tests complete!
```

- [ ] Test script runs without errors
- [ ] Discrepancy created successfully
- [ ] AI analysis present
- [ ] Statistics returned

---

## 🔐 Permissions Setup

### Step 10: Add Permissions to Database

```sql
-- Add permissions (if using permissions table)
INSERT INTO permissions (name, description, module)
VALUES
  ('payroll.submit_discrepancy', 'Can submit wage discrepancies', 'payroll'),
  ('payroll.view_discrepancy', 'Can view own wage discrepancies', 'payroll'),
  ('payroll.manage_discrepancies', 'Can review and approve/decline discrepancies', 'payroll')
ON DUPLICATE KEY UPDATE description = VALUES(description);

-- Grant to all staff (submit + view)
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
CROSS JOIN permissions p
WHERE r.name = 'Staff'
AND p.name IN ('payroll.submit_discrepancy', 'payroll.view_discrepancy')
ON DUPLICATE KEY UPDATE role_id = VALUES(role_id);

-- Grant to managers (all permissions)
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
CROSS JOIN permissions p
WHERE r.name IN ('Manager', 'Admin')
AND p.name LIKE 'payroll.%discrepanc%'
ON DUPLICATE KEY UPDATE role_id = VALUES(role_id);
```

- [ ] Permissions added
- [ ] Staff have submit + view
- [ ] Managers have manage permission

---

## 📧 Notification Setup

### Step 11: Configure Email Templates

**Create:** `/modules/human_resources/payroll/email_templates/discrepancy_submitted.html`

```html
<!DOCTYPE html>
<html>
<head>
    <title>Wage Discrepancy Submitted</title>
</head>
<body>
    <h2>Your wage discrepancy has been submitted</h2>
    <p>Hi {{staff_name}},</p>
    <p>Your wage discrepancy has been submitted successfully.</p>

    <h3>Details:</h3>
    <ul>
        <li><strong>Type:</strong> {{discrepancy_type}}</li>
        <li><strong>Amount:</strong> ${{claimed_amount}}</li>
        <li><strong>Status:</strong> {{status}}</li>
        <li><strong>Estimated resolution:</strong> {{estimated_resolution_time}}</li>
    </ul>

    {{#if auto_approved}}
    <p style="color: green;"><strong>Good news!</strong> Your discrepancy has been automatically approved and will be processed in the next pay run.</p>
    {{else}}
    <p>Your discrepancy is under review. You will be notified once it has been processed.</p>
    {{/if}}

    <p>Reference: #{{discrepancy_id}}</p>
</body>
</html>
```

**Repeat for:**
- `discrepancy_approved.html`
- `discrepancy_declined.html`
- `discrepancy_pending_review.html` (for managers)

- [ ] Email templates created
- [ ] Variables match service output
- [ ] Templates tested with sample data

---

## 🎨 UI Integration (Optional)

### Step 12: Add Navigation Menu Items

**Staff Menu:**
```html
<li>
    <a href="/payroll/discrepancies/submit">
        <i class="fa fa-exclamation-triangle"></i>
        Report Pay Issue
    </a>
</li>
<li>
    <a href="/payroll/discrepancies/my-history">
        <i class="fa fa-history"></i>
        My Pay Issues
    </a>
</li>
```

**Manager Menu:**
```html
<li>
    <a href="/payroll/discrepancies/pending">
        <i class="fa fa-tasks"></i>
        Pending Discrepancies
        <span class="badge" id="pending-count">0</span>
    </a>
</li>
```

- [ ] Menu items added
- [ ] Icons display correctly
- [ ] Links work

---

## 📊 Monitoring Setup

### Step 13: Add to Monitoring Dashboard

**Key metrics to track:**
- Total discrepancies submitted (30 days)
- Auto-approval rate
- Pending count (real-time)
- Average resolution time
- Declined rate

**API endpoint for metrics:**
```
GET /api/payroll/discrepancies/statistics
```

- [ ] Metrics added to dashboard
- [ ] Real-time updates working
- [ ] Alerts configured for high pending count

---

## 🚀 Go-Live Checklist

### Before Enabling for Staff:

- [ ] Database tables created
- [ ] Foreign keys verified
- [ ] Evidence directory created with correct permissions
- [ ] Routes added and tested
- [ ] API endpoints respond correctly
- [ ] Service layer tested
- [ ] Permissions assigned
- [ ] Email templates created
- [ ] Navigation menu updated
- [ ] Monitoring dashboard configured

### Announcement to Staff:

**Subject:** New Self-Service Pay Issue Reporting Available

**Body:**
```
Hi team,

We've launched a new self-service system for reporting pay discrepancies.

If you notice an issue with your pay (missing hours, wrong rate, missing bonus, etc.):

1. Go to: Payroll > Report Pay Issue
2. Select your payslip and describe the issue
3. Upload evidence if you have it (optional)
4. Submit

Our AI will review it instantly. Most issues are approved automatically and will be fixed in the next pay run.

For questions, contact [HR Manager].

Thanks!
```

- [ ] Announcement sent
- [ ] Help documentation available
- [ ] Support team briefed

---

## 🐛 Troubleshooting

### Issue: Database tables not created

**Check:**
```bash
# See actual error
mysql -u jcepnzzkmj -p'wprKh9Jq63' jcepnzzkmj < wage_discrepancies_schema.sql 2>&1 | tee error.log
```

**Common fixes:**
- Foreign key constraint failed → Ensure payroll_payslips, users, payroll_amendments tables exist
- Duplicate table → Drop old tables first: `DROP TABLE IF EXISTS payroll_wage_discrepancies;`

### Issue: 404 on API endpoints

**Check:**
1. Routes file syntax: `php -l routes.php`
2. Controller file exists: `ls -l controllers/WageDiscrepancyController.php`
3. Namespace correct: `namespace PayrollModule\Controllers;`
4. Apache rewrite rules: `cat /conf/server.apache | grep RewriteRule`

### Issue: File upload fails

**Check:**
1. Directory exists: `ls -ld /home/master/applications/jcepnzzkmj/private/payroll_evidence`
2. Permissions: Should be 750
3. PHP upload limit: `php -i | grep upload_max_filesize` (should be ≥10MB)
4. POST size: `php -i | grep post_max_size` (should be ≥10MB)

### Issue: Auto-approval not working

**Check:**
1. AI analysis running: Look for `ai_analysis` in database
2. Thresholds: Check WageDiscrepancyService.php lines 474-476
3. Anomalies: If any anomalies, auto-approval is disabled
4. Amendment service: Check AmendmentService exists and works

### Issue: Foreign key constraint failed

**Ensure these tables exist:**
```sql
SELECT TABLE_NAME
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = 'jcepnzzkmj'
AND TABLE_NAME IN ('users', 'payroll_payslips', 'payroll_amendments');
```

If missing, create them first before running discrepancy schema.

---

## 📞 Support Contacts

**For technical issues:**
- IT Manager: [contact]
- Database Admin: [contact]

**For business questions:**
- HR Manager: [contact]
- Payroll Manager: [contact]

---

## ✅ Final Sign-Off

**Before marking complete, verify:**

- [ ] All checklist items completed
- [ ] All tests passed
- [ ] No errors in logs
- [ ] Backup taken
- [ ] Rollback plan documented
- [ ] Staff trained
- [ ] Announcement sent

**Completed by:** _______________
**Date:** _______________
**Sign-off:** _______________

---

**Version:** 1.0.0
**Last Updated:** 2025-01-27
**Status:** Ready for deployment
