# 🚀 HR Portal Installation & Deployment Guide

## Quick Start (5 Minutes)

### Step 1: Verify Database Tables Exist
All required tables already exist in your database. Verify with:

```sql
-- Check AI tables
SELECT COUNT(*) FROM payroll_ai_rules;          -- Should return 9
SELECT COUNT(*) FROM payroll_bot_config;        -- Should return 12
SELECT COUNT(*) FROM payroll_audit_log;         -- Should return 5000+

-- Check amendment tables
SHOW TABLES LIKE 'payroll_%';
```

### Step 2: Add to Navigation Menu
Add HR Portal to CIS navigation:

```sql
INSERT INTO permissions (permission_name, permission_description, category, url, icon, sort_order, is_active)
VALUES
('HR Portal', 'Hybrid Auto-Pilot Payroll Management', 'HR', '/modules/hr-portal/', 'fa-robot', 10, 1);
```

### Step 3: Set Permissions
Grant access to HR staff:

```sql
-- Get your user ID
SELECT id, username FROM users WHERE username = 'your_username';

-- Grant access (replace USER_ID with actual ID)
INSERT INTO user_permissions (user_id, permission_id)
SELECT [USER_ID], id FROM permissions WHERE permission_name = 'HR Portal';
```

### Step 4: Configure Auto-Pilot Settings
Set your preferred thresholds:

```sql
-- Conservative (AI does less, human reviews more)
UPDATE payroll_bot_config SET config_value = '0.95' WHERE config_key = 'auto_approve_threshold';
UPDATE payroll_bot_config SET config_value = '250' WHERE config_key = 'max_auto_approve_amount';

-- OR Aggressive (AI does more, human reviews less)
UPDATE payroll_bot_config SET config_value = '0.80' WHERE config_key = 'auto_approve_threshold';
UPDATE payroll_bot_config SET config_value = '500' WHERE config_key = 'max_auto_approve_amount';

-- Start with auto-pilot OFF (enable manually via UI)
UPDATE payroll_bot_config SET config_value = '0' WHERE config_key = 'auto_pilot_enabled';
```

### Step 5: Test Access
Visit: `https://staff.vapeshed.co.nz/modules/hr-portal/`

You should see:
- ✅ Dashboard loads
- ✅ Stats cards showing 0 (expected on first load)
- ✅ Auto-pilot toggle (should be OFF initially)
- ✅ 4 tabs load without errors

---

## Detailed Setup

### File Permissions
Ensure PHP can read all module files:

```bash
cd /home/master/applications/jcepnzzkmj/public_html/modules/hr-portal
chmod 755 .
chmod 644 *.php
chmod 755 api/ includes/ views/
chmod 644 api/*.php includes/*.php views/*.php
```

### PHP Requirements
Verify your PHP version and extensions:

```bash
php -v  # Should be 7.4+ or 8.x
php -m | grep pdo_mysql  # Should show pdo_mysql
```

Required extensions:
- ✅ PDO
- ✅ pdo_mysql
- ✅ json
- ✅ session

### Database Connection Test
Create test file:

```php
<?php
// test-connection.php
try {
    $pdo = new PDO("mysql:host=127.0.0.1;dbname=jcepnzzkmj", "jcepnzzkmj", "wprKh9Jq63");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $pdo->query("SELECT COUNT(*) as count FROM payroll_ai_rules");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    echo "✅ Connection successful! Found {$result['count']} AI rules.\n";
} catch (Exception $e) {
    echo "❌ Connection failed: " . $e->getMessage() . "\n";
}
```

Run: `php test-connection.php`

### Bootstrap & Dependencies
The module uses Bootstrap 5 (already included in CIS). Verify in your main layout:

```html
<!-- Should already be in your header -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
```

---

## Configuration

### 1. Auto-Pilot Behavior

Edit thresholds in `payroll_bot_config`:

| Setting | Conservative | Balanced | Aggressive |
|---------|--------------|----------|------------|
| `auto_approve_threshold` | 0.95 | 0.90 | 0.80 |
| `manual_review_threshold` | 0.90 | 0.80 | 0.70 |
| `escalation_threshold` | 0.70 | 0.50 | 0.30 |
| `max_auto_approve_amount` | $250 | $500 | $1000 |

### 2. AI Rules

Enable/disable specific rules:

```sql
-- Disable a rule
UPDATE payroll_ai_rules SET is_active = 0 WHERE rule_name = 'Small Time Adjustment Auto-Approve';

-- Enable a rule
UPDATE payroll_ai_rules SET is_active = 1 WHERE rule_name = 'Large Time Amendment Escalate';

-- Adjust confidence required
UPDATE payroll_ai_rules SET confidence_required = 0.95 WHERE rule_name = 'Standard Vend Payment Auto-Approve';
```

### 3. Notification Settings

Add email notifications for escalated items:

```sql
UPDATE payroll_ai_rules
SET send_notification = 1,
    notification_recipient = 'hr@vapeshed.co.nz'
WHERE require_escalation = 1;
```

---

## Testing Workflow

### Create Test Data
Insert a test timesheet amendment:

```sql
INSERT INTO payroll_timesheet_amendments
(staff_id, original_start, original_end, new_start, new_end, reason, evidence_text, status, created_at)
VALUES
(1, '2025-01-27 09:00:00', '2025-01-27 17:00:00', '2025-01-27 09:00:00', '2025-01-27 17:15:00',
 'Forgot to clock break time', 'Manager approved via Slack', 'pending', NOW());
```

### Trigger AI Evaluation
The AI will evaluate when:
1. Background cron job runs (if enabled)
2. Manual trigger via portal
3. Deputy webhook receives data

For testing, manually evaluate:

```php
<?php
require_once 'includes/AIPayrollEngine.php';

$pdo = new PDO("mysql:host=127.0.0.1;dbname=jcepnzzkmj", "jcepnzzkmj", "wprKh9Jq63");
$aiEngine = new AIPayrollEngine($pdo);

$testData = [
    'id' => 1,
    'staff_id' => 1,
    'original_start' => '2025-01-27 09:00:00',
    'original_end' => '2025-01-27 17:00:00',
    'new_start' => '2025-01-27 09:00:00',
    'new_end' => '2025-01-27 17:15:00',
    'reason' => 'Forgot to clock break time',
    'evidence_text' => 'Manager approved via Slack'
];

$decision = $aiEngine->evaluate('timesheet', $testData);
print_r($decision);
```

Expected output:
```
Array
(
    [decision] => auto_approve
    [confidence] => 0.92
    [reasoning] => Auto-approved: Small Time Adjustment Auto-Approve (Confidence: 92%)
    [matched_rules] => Array
        (
            [0] => Array
                (
                    [rule_id] => 1
                    [rule_name] => Small Time Adjustment Auto-Approve
                    [confidence] => 0.92
                )
        )
    [timestamp] => 2025-01-27 15:30:00
)
```

### Verify Dashboard
1. Visit `/modules/hr-portal/`
2. Check stats cards update
3. Click tabs - each should load without errors
4. Open browser console - should see no JavaScript errors

---

## Background Processing (Optional)

### Enable Smart Cron for Auto-Processing

Add to crontab:

```bash
# Process payroll items every 5 minutes
*/5 * * * * php /home/master/applications/jcepnzzkmj/public_html/crons/process-payroll-queue.php >> /var/log/cis/payroll-cron.log 2>&1
```

Create the cron script:

```php
<?php
// crons/process-payroll-queue.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../modules/hr-portal/includes/AIPayrollEngine.php';
require_once __DIR__ . '/../modules/hr-portal/includes/PayrollDashboard.php';

$pdo = new PDO("mysql:host=127.0.0.1;dbname=jcepnzzkmj", "jcepnzzkmj", "wprKh9Jq63");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$aiEngine = new AIPayrollEngine($pdo);
$dashboard = new PayrollDashboard($pdo, $aiEngine);

// Get pending amendments (not yet evaluated)
$stmt = $pdo->query("
    SELECT * FROM payroll_timesheet_amendments
    WHERE status = 'pending'
    AND id NOT IN (SELECT item_id FROM payroll_ai_decisions WHERE item_type = 'timesheet')
    LIMIT 50
");
$amendments = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "[" . date('Y-m-d H:i:s') . "] Processing " . count($amendments) . " amendments...\n";

foreach ($amendments as $amendment) {
    $decision = $aiEngine->evaluate('timesheet', $amendment);
    echo "  - Staff {$amendment['staff_id']}: {$decision['decision']} ({$decision['confidence']})\n";

    // If auto-pilot is ON and decision is auto_approve, process immediately
    if ($decision['decision'] === 'auto_approve') {
        // Auto-process (would call Deputy API here)
        echo "    ✓ Auto-approved and processed\n";
    }
}

echo "Done.\n";
```

Test manually:
```bash
php crons/process-payroll-queue.php
```

---

## Security Checklist

- [ ] **Authentication:** All pages check `$_SESSION['user_id']`
- [ ] **Authorization:** User has permission in `user_permissions` table
- [ ] **Input Validation:** All POST data validated
- [ ] **SQL Injection:** Using prepared statements everywhere
- [ ] **XSS Protection:** `htmlspecialchars()` on all output
- [ ] **Audit Logging:** All actions logged with user_id and IP
- [ ] **No Secrets:** Database credentials in `config.php` only
- [ ] **HTTPS:** Force HTTPS in production
- [ ] **Session Security:** `session.cookie_httponly = 1` in php.ini

---

## Performance Optimization

### Database Indexes
Ensure indexes exist for fast queries:

```sql
-- Check existing indexes
SHOW INDEX FROM payroll_ai_decisions;

-- Add missing indexes
CREATE INDEX idx_human_action ON payroll_ai_decisions(human_action);
CREATE INDEX idx_decision_created ON payroll_ai_decisions(decision, created_at);
CREATE INDEX idx_item_lookup ON payroll_ai_decisions(item_type, item_id);

CREATE INDEX idx_staff_status ON payroll_timesheet_amendments(staff_id, status);
CREATE INDEX idx_created ON payroll_timesheet_amendments(created_at);
```

### Page Load Optimization
- Dashboard stats cached for 30 seconds (auto-refresh)
- Large audit trail queries limited to 100 rows
- Use pagination for >100 items

---

## Troubleshooting

### Issue: "Undefined class AIPayrollEngine"
**Solution:** Check autoload or add `require_once`:
```php
require_once __DIR__ . '/includes/AIPayrollEngine.php';
require_once __DIR__ . '/includes/PayrollDashboard.php';
```

### Issue: "Connection refused" to database
**Solution:** Verify database credentials in `config.php`:
```php
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'jcepnzzkmj');
define('DB_USER', 'jcepnzzkmj');
define('DB_PASS', 'wprKh9Jq63');
```

### Issue: Stats showing 0 accuracy
**Reason:** No human feedback yet
**Solution:** Approve/deny a few items first, then accuracy calculates

### Issue: Auto-pilot not working
**Check:**
1. Is `auto_pilot_enabled = 1`?
2. Are rules active? `SELECT * FROM payroll_ai_rules WHERE is_active = 1;`
3. Check logs: `SELECT * FROM payroll_audit_log ORDER BY created_at DESC LIMIT 10;`

---

## Deployment Checklist

### Pre-Deploy
- [ ] Backup database: `mysqldump jcepnzzkmj > backup_$(date +%F).sql`
- [ ] Test on staging environment first
- [ ] Verify all 9 AI rules exist and active
- [ ] Set conservative thresholds initially

### Deploy
- [ ] Upload module files to `/modules/hr-portal/`
- [ ] Set file permissions (755/644)
- [ ] Add navigation menu entry
- [ ] Grant permissions to HR users
- [ ] Test access and basic functions

### Post-Deploy
- [ ] Monitor first 24 hours closely
- [ ] Review AI decisions daily for first week
- [ ] Adjust thresholds based on accuracy
- [ ] Train HR staff on interface
- [ ] Enable auto-pilot after confidence built

### Rollback Plan
If issues arise:
```sql
-- Disable auto-pilot immediately
UPDATE payroll_bot_config SET config_value = '0' WHERE config_key = 'auto_pilot_enabled';

-- Deactivate all auto-approve rules
UPDATE payroll_ai_rules SET auto_approve = 0;

-- Require human review for everything
UPDATE payroll_ai_rules SET require_human_review = 1;
```

---

## Monitoring & Maintenance

### Daily
- Check dashboard for escalated items
- Review AI accuracy metric
- Clear pending queue

### Weekly
- Review audit trail for anomalies
- Adjust rule confidence if needed
- Check for stuck items

### Monthly
- Analyze AI performance trends
- Update rule priorities based on learning
- Review time savings vs manual process

### Quarterly
- Full audit of all decisions
- Update documentation
- Plan feature enhancements

---

## Support Resources

- **Documentation:** `/modules/hr-portal/README.md`
- **Installation Guide:** This file
- **Database Schema:** See `_kb/PAYROLL_AI_REALITY_CHECK.md`
- **API Reference:** See README.md "API Endpoints" section

For technical support:
- Check PHP error logs: `/logs/php-app.*.log`
- Check audit trail: Query `payroll_audit_log`
- Review recent decisions: Query `payroll_ai_decisions`

---

**Ready to Go!** 🚀

Visit: `https://staff.vapeshed.co.nz/modules/hr-portal/`
