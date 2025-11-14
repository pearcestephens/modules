# 🔧 FRAUD DETECTION SYSTEM - TROUBLESHOOTING & FAQ

## 🚨 COMMON ISSUES & SOLUTIONS

### 1. Configuration Not Loading

**Symptom:**
```
Fatal error: Uncaught Exception: Configuration file not found
```

**Solution:**
```php
// Check file exists
if (!file_exists(__DIR__ . '/config/fraud_detection_config.php')) {
    echo "❌ Config file missing!\n";
}

// Check path in ConfigManager.php (line 20)
private string $configPath = __DIR__ . '/../config/fraud_detection_config.php';

// Adjust based on your directory structure
```

---

### 2. Alerts Not Sending (Email)

**Symptom:**
- No emails received
- `fraud_alert_log` shows `status = 'failed'`

**Diagnostic Steps:**

```sql
-- Check alert log for errors
SELECT * FROM fraud_alert_log
WHERE alert_type = 'email'
AND status = 'failed'
ORDER BY created_at DESC
LIMIT 5;
```

**Common Causes:**

**A) PHP mail() Not Configured**
```bash
# Test basic email
php -r "mail('test@example.com', 'Test', 'Testing');"

# Check if sent
tail -f /var/log/mail.log
```

**B) From Address Blocked**
```php
// In config file (line 358), use valid domain:
'from_address' => 'fraud-detection@vapeshed.co.nz',  // Must match server domain
```

**C) HTML Email Issues**
```php
// Test with plain text first:
$headers = "Content-Type: text/plain; charset=UTF-8\r\n";
$headers .= "From: fraud-detection@vapeshed.co.nz\r\n";
mail('test@example.com', 'Test Alert', 'Testing plain text', $headers);
```

---

### 3. Alerts Not Sending (Slack)

**Symptom:**
- Emails work fine
- Slack messages not appearing
- `fraud_alert_log` shows HTTP error

**Diagnostic Steps:**

```php
// Test Slack webhook directly
$webhookUrl = 'YOUR_WEBHOOK_URL';
$data = json_encode(['text' => 'Test from fraud system']);

$ch = curl_init($webhookUrl);
curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
echo "Response: $response\n";
// Expected: HTTP Code: 200, Response: ok
```

**Common Causes:**

**A) Invalid Webhook URL**
- Get fresh webhook from Slack: Settings → Incoming Webhooks
- Update in config file

**B) Slack App Permissions**
- Verify app has `incoming-webhook` scope
- Verify app installed to workspace

**C) Channel Not Found**
```php
// In config, use # prefix:
'channel' => '#fraud-alerts',  // Not 'fraud-alerts'
```

---

### 4. Too Many False Positives

**Symptom:**
- 20%+ of alerts are false positives
- Specific indicator always wrong
- Alerts feel like noise

**Diagnostic Query:**

```sql
SELECT
    indicator_type,
    fraud_category,
    COUNT(*) AS total_flags,
    SUM(CASE WHEN marked_false_positive = 1 THEN 1 ELSE 0 END) AS false_positives,
    ROUND(100.0 * SUM(CASE WHEN marked_false_positive = 1 THEN 1 ELSE 0 END) / COUNT(*), 2) AS fp_rate
FROM fraud_false_positives
GROUP BY indicator_type, fraud_category
HAVING fp_rate > 15
ORDER BY fp_rate DESC;
```

**Solutions:**

**A) Increase Threshold**
```php
// In config file, increase tolerance
// OLD:
'cash_ratio_variance_threshold' => 20,

// NEW:
'cash_ratio_variance_threshold' => 30,  // 50% increase
```

**B) Add Outlet Override**
```php
// Tourist outlet with naturally high cash
'outlet_overrides' => [
    'outlet_3' => [
        'payment_type_fraud' => [
            'cash_ratio_variance_threshold' => 40,  // Even higher
        ],
    ],
],
```

**C) Whitelist Legitimate Cases**
```php
'whitelisted_customer_ids' => [
    'CORP_ACCOUNT_001',  // Corporate account
],
```

**D) Exclude Staff From Check**
```php
'partial_exclusions' => [
    5 => [  // Store Manager
        'indicators' => [
            'excessive_price_overrides',  // Manager can override
        ],
    ],
],
```

---

### 5. No Alerts (Too Quiet)

**Symptom:**
- System running, no errors
- But no alerts generated
- Known issues not detected

**Diagnostic Steps:**

```sql
-- Check if analysis is running
SELECT * FROM fraud_tracking_summary
WHERE analysis_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
ORDER BY analysis_date DESC;
-- If empty, analysis not running

-- Check if staff have indicators
SELECT
    staff_id,
    COUNT(*) AS indicator_count,
    MAX(risk_score) AS highest_risk
FROM fraud_tracking_summary
WHERE analysis_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
GROUP BY staff_id
ORDER BY highest_risk DESC;
-- If all risk scores low, thresholds too lenient
```

**Common Causes:**

**A) Dry-Run Mode Still Enabled**
```php
// Check config file (line 23)
'dry_run_mode' => false,  // Should be false for production
```

**B) Alert Threshold Too High**
```php
// In config (line 370)
'min_risk_score_for_alert' => 70,  // Lower to 50 if too quiet
```

**C) Thresholds Too Lenient**
- Review and decrease thresholds
- Compare to historical fraud cases
- Test with known fraud scenarios

**D) Staff Exclusions Too Broad**
```php
// Check if accidentally excluding everyone
'excluded_staff_ids' => [
    // 1, 2, 3, 4, 5, 6, 7...  // WRONG - too many exclusions
],
```

---

### 6. Alert Spam (Too Many Alerts)

**Symptom:**
- Same staff getting 5+ alerts per day
- Inbox flooded
- Alert fatigue setting in

**Diagnostic Query:**

```sql
SELECT
    staff_id,
    DATE(created_at) AS alert_date,
    COUNT(*) AS alerts_today
FROM fraud_alert_log
WHERE created_at >= CURDATE()
GROUP BY staff_id, DATE(created_at)
HAVING alerts_today > 3
ORDER BY alerts_today DESC;
```

**Solutions:**

**A) Enable/Increase Throttling**
```php
// In config (line 376)
'alert_throttle' => [
    'enabled' => true,
    'max_alerts_per_staff_per_day' => 1,  // Reduce to 1
    'cooldown_hours' => 12,  // Increase to 12 hours
],
```

**B) Increase Min Risk Score**
```php
// Only alert on high-risk cases
'min_risk_score_for_alert' => 80,  // Was 70
```

**C) Consolidate Similar Alerts**
- Group related indicators
- Only alert once per category per day

---

### 7. Grace Period Not Working

**Symptom:**
- New staff getting alerts immediately
- Grace period seems ignored

**Diagnostic Steps:**

```sql
-- Check if staff has active grace period
SELECT * FROM v_staff_in_grace_period
WHERE staff_id = 25;
-- If empty, no grace period set

-- Check grace period table
SELECT * FROM fraud_staff_grace_periods
WHERE staff_id = 25;
```

**Solutions:**

**A) Add Grace Period**
```sql
INSERT INTO fraud_staff_grace_periods
(staff_id, hire_date, grace_period_end_date, grace_period_days)
VALUES (25, '2024-11-01', DATE_ADD('2024-11-01', INTERVAL 14 DAY), 14);
```

**B) Verify ConfigManager Checks Grace Period**
```php
// In FraudAnalyzer.php, before analyzing:
if ($config->isStaffInGracePeriod($staffId)) {
    echo "Staff $staffId is in grace period, skipping analysis\n";
    continue;
}
```

---

### 8. Performance Issues (Slow Analysis)

**Symptom:**
- Analysis takes > 10 minutes
- High memory usage
- Database locks

**Diagnostic Steps:**

```bash
# Check analysis time
time php analyze.php --all

# Check memory usage
php -d memory_limit=512M analyze.php --all
```

**Solutions:**

**A) Enable Batch Processing**
```php
// In config (line 420)
'batch_processing' => [
    'enabled' => true,
    'batch_size' => 10,  // Process 10 staff at a time
    'sleep_between_batches' => 1,  // 1 second pause
],
```

**B) Enable Caching**
```php
'cache_enabled' => true,
'cache_ttl' => 3600,  // 1 hour
```

**C) Add Database Indexes**
```sql
-- Add indexes to speed up queries
CREATE INDEX idx_analysis_date ON fraud_tracking_summary(analysis_date);
CREATE INDEX idx_staff_outlet ON fraud_tracking_summary(staff_id, outlet_id);
CREATE INDEX idx_risk_score ON fraud_tracking_summary(overall_risk_score);
```

**D) Limit Analysis Window**
```php
// In config (line 27)
'analysis_window_days' => 30,  // Was 90, reduce to 30
```

---

### 9. Database Connection Errors

**Symptom:**
```
SQLSTATE[HY000] [2002] No such file or directory
```

**Solution:**

```php
// Check PDO connection in analyze.php
$dsn = 'mysql:host=localhost;dbname=your_database;charset=utf8mb4';
$pdo = new PDO($dsn, 'username', 'password', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

// If using socket instead of localhost:
$dsn = 'mysql:unix_socket=/var/run/mysqld/mysqld.sock;dbname=your_database';
```

---

### 10. Threshold Adjustments Not Taking Effect

**Symptom:**
- Changed config file
- Re-ran analysis
- Still using old thresholds

**Diagnostic Steps:**

```php
// Verify config loading
$config = ConfigManager::getInstance();
$threshold = $config->get('payment_type_fraud.cash_ratio_variance_threshold');
echo "Current threshold: $threshold\n";
// Compare to config file value
```

**Solutions:**

**A) Clear Config Cache (If Using OPcache)**
```bash
# Restart PHP-FPM to clear OPcache
sudo systemctl restart php8.1-fpm

# Or clear from code:
if (function_exists('opcache_reset')) {
    opcache_reset();
}
```

**B) Verify Singleton Pattern**
```php
// ConfigManager should reload config on each getInstance() call
// Check ConfigManager.php constructor:
public function __construct() {
    $this->loadConfig();  // Should be called
}
```

---

## ❓ FREQUENTLY ASKED QUESTIONS

### Q1: How often should analysis run?

**Answer:** Daily at 2am is recommended.

```bash
# Crontab entry:
0 2 * * * cd /path/to/fraud-detection && php analyze.php --all >> logs/analysis.log 2>&1
```

**Rationale:**
- Gives fraud 1 day to occur
- Low server load at 2am
- Results ready by morning review

---

### Q2: What's a good false positive rate?

**Answer:** Target < 10% FP rate per indicator.

**Benchmarks:**
- ✅ < 5% = Excellent
- ✅ 5-10% = Good
- ⚠️ 10-20% = Needs tuning
- ❌ > 20% = Too many FPs, increase threshold

---

### Q3: Should I exclude store managers?

**Answer:** Use **partial exclusions**, not full exclusions.

```php
// ❌ DON'T (full exclusion):
'excluded_staff_ids' => [1, 2, 3],  // Managers get free pass

// ✅ DO (partial exclusion):
'partial_exclusions' => [
    1 => [  // Store Manager
        'indicators' => [
            'excessive_price_overrides',  // Legitimate
            'excessive_discounts',         // Legitimate
        ],
        // Still check for: cash shortages, missing deposits, etc.
    ],
],
```

---

### Q4: How do I whitelist a customer?

**Answer:** Add to `whitelisting` section:

```php
'whitelisting' => [
    'whitelisted_customer_ids' => [
        'CORP_MAIN_001',  // Corporate account
        'WHOLESALE_002',   // Wholesale customer
    ],
],
```

Then re-run analysis or mark past alerts as false positives.

---

### Q5: Can I have different thresholds per outlet?

**Answer:** Yes, use `outlet_overrides`:

```php
'outlet_overrides' => [
    'outlet_3' => [  // Tourist outlet
        'payment_type_fraud' => [
            'cash_ratio_variance_threshold' => 40,  // Higher cash tolerance
        ],
    ],
    'outlet_5' => [  // High-volume outlet
        'transaction_manipulation' => [
            'refund_percentage_threshold' => 15,  // More refunds ok
        ],
    ],
],
```

---

### Q6: How do I test without sending real alerts?

**Answer:** Use dry-run mode:

```php
// In config file
'dry_run_mode' => true,

// Or via CLI:
php analyze.php --all --dry-run
```

Dry-run mode:
- ✅ Runs full analysis
- ✅ Prints results to screen
- ❌ Does NOT store in database
- ❌ Does NOT send alerts

---

### Q7: What if I get a false positive?

**Answer:** Mark it in the database:

```sql
INSERT INTO fraud_false_positives
(staff_id, indicator_type, fraud_category, original_severity,
 marked_false_positive_by, reason, incident_data)
VALUES
(5, 'cash_ratio_variance', 'payment_type_fraud', 0.75, 1,
 'Tourist outlet - high cash sales are normal here',
 '{"date": "2024-11-14", "outlet_id": 3}');
```

Then:
1. System learns from this
2. May auto-adjust thresholds
3. Improves future accuracy

---

### Q8: How do I handle seasonal changes?

**Answer:** Use `seasonal_adjustments`:

```php
'seasonal_adjustments' => [
    'enabled' => true,
    'periods' => [
        'christmas' => [
            'start' => '12-01',
            'end' => '01-07',
            'multipliers' => [
                'refund_percentage_threshold' => 1.5,  // 50% higher
                'discount_percentage_threshold' => 1.3,
            ],
        ],
    ],
],
```

System automatically adjusts thresholds during defined periods.

---

### Q9: Can I get SMS alerts?

**Answer:** Yes, but requires Twilio setup:

1. Sign up for Twilio account
2. Get Account SID and Auth Token
3. Add to config:
```php
'sms_alerts' => [
    'enabled' => true,
    'twilio_account_sid' => 'YOUR_SID',
    'twilio_auth_token' => 'YOUR_TOKEN',
    'twilio_phone_number' => '+1234567890',
    'recipients' => [
        '+64211234567' => 'Manager',
    ],
],
```
4. Implement Twilio API calls in `AlertManager::sendSMSAlert()`

---

### Q10: How do I investigate an alert?

**Standard Investigation Process:**

1. **Review Alert Details**
   - Risk score, indicators triggered
   - Dates and times of suspicious activity

2. **Check Camera Footage**
   - Verify staff presence during flagged times
   - Look for suspicious behavior

3. **Review Transaction Logs**
   - Pull POS reports for flagged dates
   - Look for patterns (time of day, products, etc.)

4. **Interview Staff Member**
   - Ask about flagged transactions
   - Give chance to explain
   - Note their response

5. **Make Determination**
   - **Fraud:** Follow HR procedures, document evidence
   - **False Positive:** Mark in database, adjust config
   - **Needs Investigation:** Gather more evidence

6. **Close Case**
```sql
-- If fraud confirmed:
UPDATE fraud_tracking_summary
SET resolved_by_staff_id = 1,
    resolved_at = NOW(),
    resolution_notes = 'Confirmed fraud. Staff member terminated. Evidence: [link]'
WHERE staff_id = 5
AND analysis_date = '2024-11-14';

-- If false positive:
INSERT INTO fraud_false_positives (...);
```

---

## 📊 MONITORING QUERIES

### Daily Health Check

```sql
-- Did analysis run last night?
SELECT MAX(analysis_date) AS last_run
FROM fraud_tracking_summary;
-- Should be yesterday

-- Any alerts sent today?
SELECT COUNT(*) AS alerts_today
FROM fraud_alert_log
WHERE DATE(created_at) = CURDATE();

-- Any failed alerts?
SELECT * FROM fraud_alert_log
WHERE status = 'failed'
AND DATE(created_at) = CURDATE();
```

### Weekly Review

```sql
-- False positive rate
SELECT * FROM v_false_positive_rate_by_indicator
WHERE false_positive_count > 0
ORDER BY false_positive_rate_percentage DESC;

-- Top alerting staff
SELECT * FROM v_alert_summary_by_staff
ORDER BY total_alerts DESC
LIMIT 10;

-- Alert volume trend
SELECT
    DATE(created_at) AS alert_date,
    COUNT(*) AS alerts_sent
FROM fraud_alert_log
WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
GROUP BY DATE(created_at)
ORDER BY alert_date;
```

---

## 🛠️ USEFUL COMMANDS

### Test Configuration Loading
```bash
php -r "
require 'vendor/autoload.php';
\$config = FraudDetection\ConfigManager::getInstance();
echo 'Dry-run: ' . (\$config->isDryRun() ? 'YES' : 'NO') . PHP_EOL;
"
```

### Test Alert System
```bash
php -r "
require 'vendor/autoload.php';
\$pdo = new PDO('mysql:host=localhost;dbname=db', 'user', 'pass');
\$config = FraudDetection\ConfigManager::getInstance();
\$alertManager = new FraudDetection\AlertManager(\$pdo, \$config);
echo 'AlertManager loaded OK' . PHP_EOL;
"
```

### Manual Analysis (Single Staff)
```bash
php analyze.php --staff-id=5 --verbose
```

### Manual Analysis (All Staff, Dry-Run)
```bash
php analyze.php --all --dry-run
```

### Check Cron Logs
```bash
tail -f logs/analysis.log
```

---

## 📞 GETTING HELP

**Issue not listed here?**

1. Check `CONFIGURATION_GUIDE.md` for detailed config help
2. Check `DEPLOYMENT_CHECKLIST.md` for setup steps
3. Review error logs: `logs/errors.log`, `logs/analysis.log`
4. Check database: `fraud_alert_log`, `fraud_config_audit`
5. Verify cron job running: `crontab -l`

**Still stuck?**
- Review code comments in `ConfigManager.php` and `AlertManager.php`
- Test components individually (config loading, database connection, email sending)
- Enable verbose logging for more diagnostic info

---

## 🎯 QUICK REFERENCE

**Common Config Changes:**

| Need | Config Key | Location |
|------|-----------|----------|
| Increase cash tolerance | `cash_ratio_variance_threshold` | Line 50 |
| Reduce alert spam | `max_alerts_per_staff_per_day` | Line 376 |
| Add outlet override | `outlet_overrides` | Line 220 |
| Exclude manager | `partial_exclusions` | Line 238 |
| Whitelist customer | `whitelisted_customer_ids` | Line 260 |
| Enable Slack | `slack_alerts.enabled` | Line 362 |
| Change grace period | `grace_period_days` | Line 29 |

**Common SQL Queries:**

| Need | Query |
|------|-------|
| Check FP rate | `SELECT * FROM v_false_positive_rate_by_indicator` |
| Check alerts sent | `SELECT * FROM v_alert_summary_by_staff` |
| Check grace periods | `SELECT * FROM v_staff_in_grace_period` |
| Check config changes | `SELECT * FROM fraud_config_audit ORDER BY changed_at DESC` |

---

**You've got this! 🚀**
