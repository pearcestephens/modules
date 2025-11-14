# Troubleshooting & Operations Guide
## Behavioral Fraud Detection System - Issue Resolution & Best Practices

---

## Common Issues & Solutions

### Issue: Analysis Taking Too Long (>5 seconds per staff)

**Symptoms:**
- `/api/fraud-detection/analyze` endpoint timing out
- Scheduled daily analysis not completing within 2 hours
- Server CPU spiking during analysis windows

**Root Causes:**
1. **Missing database indexes** - Slow transaction queries
2. **Large result sets** - Too many transactions being loaded
3. **Peer comparison calculation** - Full table scans without limits
4. **Time window too large** - Analyzing 12 months instead of 30 days

**Solutions:**

```sql
-- Verify critical indexes exist
SHOW INDEX FROM sales_transactions WHERE Key_name IN ('idx_staff_id', 'idx_created_at', 'idx_store_id');

-- If missing, create them:
ALTER TABLE sales_transactions ADD INDEX idx_staff_id (staff_id);
ALTER TABLE sales_transactions ADD INDEX idx_created_at (created_at);
ALTER TABLE sales_transactions ADD INDEX idx_store_id (store_id);
ALTER TABLE refunds ADD INDEX idx_staff_id (staff_id);
ALTER TABLE refunds ADD INDEX idx_created_at (created_at);
ALTER TABLE inventory_movements ADD INDEX idx_staff_id (staff_id);
```

```php
// In BehavioralAnalyticsEngine.php - Optimize query
// BEFORE (slow):
$stmt = $pdo->prepare("SELECT * FROM sales_transactions WHERE staff_id = ?");
$stmt->execute([$staffId]);
$transactions = $stmt->fetchAll();

// AFTER (fast):
$stmt = $pdo->prepare("
    SELECT * FROM sales_transactions
    WHERE staff_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ORDER BY created_at DESC
    LIMIT 10000
");
$stmt->execute([$staffId]);
$transactions = $stmt->fetchAll();
```

**Prevention:**
- Keep analysis window to 30 days (not more)
- Run analysis during off-peak hours (2 AM)
- Monitor query execution times in logs

---

### Issue: Cameras Not Responding to Commands

**Symptoms:**
- Camera targeting activated but cameras don't move
- "Camera API failure" messages in logs
- PTZ presets not being applied

**Root Causes:**
1. **Network connectivity** - Cameras offline or unreachable
2. **Invalid authentication** - Wrong API key or signature
3. **Incorrect IP/endpoint** - Pointing to wrong camera server
4. **Firmware version mismatch** - API changed in new firmware

**Solutions:**

```php
// Test camera connectivity
$cameras = $targeting->testCameraConnectivity();

foreach ($cameras as $camera) {
    echo $camera['ip'] . ": " . $camera['status'];
    if ($camera['status'] !== 'OK') {
        // Troubleshoot individual camera
        $response = $targeting->sendCameraCommand($camera, [
            'action' => 'ping',
            'timeout' => 5
        ]);

        if (!$response['success']) {
            echo "Camera unreachable: " . $response['error'];
        }
    }
}
```

```bash
# Test camera API from command line
curl -X POST http://CAMERA_IP/api/command \
  -H "Content-Type: application/json" \
  -H "Authorization: HMAC-SHA256 signature" \
  -d '{"action": "ping"}'

# If fails, check:
# 1. Camera IP is correct
# 2. Camera API port is open (check firewall)
# 3. API credentials are correct
# 4. Network routing is correct (ping camera_ip from server)
ping CAMERA_IP

# Restart camera if needed
# (Different for each camera model - see vendor documentation)
```

**Prevention:**
- Keep camera firmware updated
- Test connectivity after network changes
- Maintain list of current camera IPs and API endpoints
- Monitor camera response times in dashboard

---

### Issue: Alerts Not Being Sent to Managers

**Symptoms:**
- Critical alerts generated but emails never arrive
- SMS alerts fail silently
- Push notifications not sent
- No error messages in logs

**Root Causes:**
1. **Email service down** - SMTP unreachable
2. **Missing email configuration** - SMTP_HOST not set
3. **Rate limiting** - Too many emails triggered
4. **Invalid recipient addresses** - Typos in manager emails

**Solutions:**

```php
// Test email delivery
$tester = new AlertDeliveryTester($config);

// Test SMTP connection
$result = $tester->testSmtpConnection();
if (!$result['success']) {
    echo "SMTP Error: " . $result['error'];
    echo "Host: " . env('SMTP_HOST');
    echo "Port: " . env('SMTP_PORT');
}

// Send test email
$result = $tester->sendTestEmail('test@company.com');
if (!$result['success']) {
    echo "Email failed: " . $result['error'];
}

// Check alert configuration
$config = require 'config/fraud-detection.config.php';
echo "Alert channels: " . implode(', ', $config['alerts']['channels']);
echo "Recipients: " . implode(', ', $config['alerts']['recipients']);
```

```env
# Verify .env settings
SMTP_HOST=mail.company.com
SMTP_PORT=587
SMTP_USERNAME=alerts@company.com
SMTP_PASSWORD=your-app-password
SMTP_FROM=fraud-alerts@company.com

# Check if SMS service configured
SMS_PROVIDER=twilio  # or vonage
SMS_API_KEY=xxx
SMS_PHONE_NUMBERS=+64211234567,+64212345678
```

**Prevention:**
- Send test alert during deployment
- Monitor alert delivery logs
- Verify manager contact information quarterly
- Implement alert delivery monitoring (track sent/failed)

---

### Issue: False Positives (Legitimate Staff Flagged)

**Symptoms:**
- Staff with high discount patterns but valid reason (training)
- Void transactions that are legitimate customer issues
- Inventory discrepancies due to inventory count errors

**Root Causes:**
1. **Thresholds too sensitive** - 15% discount threshold too low
2. **Missing context** - Not accounting for legitimate business reasons
3. **Data quality issues** - Bad inventory counts affecting analysis
4. **Peer group comparison** - Store with different product mix

**Solutions:**

```php
// Review and adjust thresholds in fraud-detection.config.php
'risk_factors' => [
    'discount_anomalies' => [
        'threshold_percentage' => 15.0,      // Increase to 20-25% if too many false positives
        'minimum_transaction_count' => 10,   // Require at least N transactions
    ],
    'void_transactions' => [
        'threshold_multiplier' => 2.0,       // Increase from 2.0x to 3.0x
        'minimum_daily_voids' => 3,          // Require at least N voids
    ],
    'refund_anomalies' => [
        'threshold_multiplier' => 3.0,       // Increase threshold
        'exclude_categories' => ['training', 'damage'],  // Whitelist legit reasons
    ]
]

// Add exclusion rules for known false positives
$staff_exclusions = [
    45 => [                                  // Staff ID 45
        'exclude_discount_analysis' => true, // Training supervisor - high discounts
        'exclude_void_analysis' => false,
        'note' => 'Training supervisor, high discounts are legitimate'
    ]
];

// In analysis method, check exclusions
if ($staff_exclusions[$staffId]['exclude_discount_analysis'] ?? false) {
    unset($risk_factors['discount_anomalies']);
}

// Create whitelist for legitimate void transactions
$legitimate_voids = [
    'customer_satisfaction',
    'training_transaction',
    'system_error',
    'damaged_product'
];

// In void analysis, filter them out
$voids = $this->getVoidTransactions($staffId, $timeWindow);
$voids = array_filter($voids, fn($v) => !in_array($v['reason'], $legitimate_voids));
```

```sql
-- Audit recent false positives
SELECT * FROM behavioral_analysis_results
WHERE risk_level IN ('CRITICAL', 'HIGH')
AND staff_id IN (45, 46, 47)  -- Known false positives
ORDER BY created_at DESC
LIMIT 20;

-- Check incident status to find dismissed cases
SELECT * FROM fraud_incidents
WHERE status = 'DISMISSED'
ORDER BY created_at DESC
LIMIT 10;
```

**Prevention:**
- Regularly review dismissed incidents to identify patterns
- Adjust thresholds based on false positive rate
- Document legitimate reasons for high-risk behaviors
- Implement manager feedback loop for tuning

---

### Issue: Dashboard Slow or Not Updating

**Symptoms:**
- Dashboard loads in >5 seconds
- Critical alerts not showing in real-time
- Graphing data is stale (hours old)

**Root Causes:**
1. **Unoptimized queries** - Dashboard running N+1 queries
2. **No caching** - Recalculating metrics on every load
3. **Large date ranges** - Pulling too much historical data

**Solutions:**

```php
// Implement caching for dashboard data
$cacheKey = "dashboard_data_store_{$storeId}";
$cacheTTL = 300; // 5 minutes

// Check cache first
if ($cache->has($cacheKey)) {
    $data = $cache->get($cacheKey);
} else {
    // Calculate once
    $data = $dashboard->calculateDashboardData($storeId);

    // Store for next 5 minutes
    $cache->set($cacheKey, $data, $cacheTTL);
}

return $data;
```

```php
// In RealTimeAlertDashboard.php - Use aggregated queries
// BEFORE (slow):
$staffIds = $this->getAllStaff();
foreach ($staffIds as $id) {
    $alerts[] = $this->getCriticalAlertsForStaff($id);  // N queries!
}

// AFTER (fast):
$alerts = $pdo->query("
    SELECT
        s.id, s.name,
        COUNT(*) as alert_count,
        MAX(b.risk_score) as max_risk
    FROM behavioral_analysis_results b
    JOIN staff s ON b.staff_id = s.id
    WHERE b.risk_level IN ('CRITICAL', 'HIGH')
    AND b.created_at >= DATE_SUB(NOW(), INTERVAL 48 HOUR)
    GROUP BY s.id
    ORDER BY max_risk DESC
    LIMIT 10
")->fetchAll();
```

**Prevention:**
- Cache heavy calculations for 5-10 minutes
- Limit date ranges to 30 days default
- Use aggregated queries instead of loops
- Monitor dashboard load times in logs

---

### Issue: Memory Usage Growing (Memory Leak)

**Symptoms:**
- PHP process using 500MB+ RAM
- Memory errors in logs
- System performance degrading over time

**Root Causes:**
1. **Unbounded result sets** - Loading too many records
2. **Unclosed database connections** - Connection pool leak
3. **Logging too much** - Large log arrays in memory

**Solutions:**

```php
// Limit result sets
// BEFORE (memory-heavy):
$transactions = $pdo->query("SELECT * FROM sales_transactions")->fetchAll();

// AFTER (memory-efficient):
$stmt = $pdo->prepare("
    SELECT * FROM sales_transactions
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    LIMIT 10000
");
$stmt->execute();
$transactions = $stmt->fetchAll();
unset($stmt); // Release resources
```

```php
// Use statement iteration for large datasets
// Instead of fetchAll():
foreach ($stmt as $row) {
    // Process one row at a time
    processTransaction($row);

    // Memory stays constant
}
```

```php
// Monitor memory usage in logs
$memBefore = memory_get_usage();
$analysis = $engine->analyzeAllStaff('daily');
$memAfter = memory_get_usage();

$logger->info("Analysis memory delta: " . ($memAfter - $memBefore) . " bytes");
```

**Prevention:**
- Profile code regularly with Xdebug
- Set PHP memory_limit appropriately (256M typical)
- Use iterators for large datasets
- Run analysis during low-traffic hours

---

## Operational Tasks

### Daily Operations Checklist

```
☐ Morning (Before 9 AM)
  - Check critical alerts on dashboard
  - Review any active investigations
  - Verify camera network status (102/102 online)
  - Check analysis completion (should be done by 2 AM)
  - Review alert delivery logs (any failures?)

☐ Midday (Around 12 PM)
  - Check for stuck/expired camera targeting
  - Review sales/transaction volume (looking normal?)
  - Verify no database performance issues
  - Check API response times

☐ Evening (Around 5 PM)
  - Review active incidents
  - Check for new patterns in fraud data
  - Verify backup completion
  - Prepare incident summary if needed

☐ Weekly (Monday Morning)
  - Review 7-day trend report
  - Analyze high-risk individuals
  - Check for repeat offenders
  - Update threat assessment
  - Review false positive rate
```

### Monthly Maintenance

```
☐ First Monday of Month
  1. Generate monthly report
     php /path/to/bootstrap.php report 30

  2. Review fraud trends
     - New patterns emerging?
     - Increase/decrease in incidents?
     - Geographic trends?

  3. Tune thresholds based on false positives
     - Update config/fraud-detection.config.php
     - Test with sample data
     - Deploy to production

  4. Review camera system
     - PTZ functionality test
     - Preset accuracy check
     - Recording quality verification

  5. Database maintenance
     - Analyze tables for fragmentation
     - Optimize indexes
     - Backup verification

☐ Database Maintenance
```

```sql
-- Monthly database optimization
ANALYZE TABLE behavioral_analysis_results;
OPTIMIZE TABLE behavioral_analysis_results;

ANALYZE TABLE camera_targeting_records;
OPTIMIZE TABLE camera_targeting_records;

ANALYZE TABLE fraud_incidents;
OPTIMIZE TABLE fraud_incidents;

-- Check disk usage
SELECT
    table_name,
    round(((data_length + index_length) / 1024 / 1024), 2) as size_mb
FROM information_schema.tables
WHERE table_schema = 'cis'
ORDER BY size_mb DESC;
```

### Quarterly Review

```
☐ Every 3 Months
  1. Update risk scoring weights
     - Review efficacy of each factor
     - Adjust based on actual fraud incidents
     - Validate new weights with test data

  2. Review camera coverage
     - Any blind spots identified?
     - Any cameras offline frequently?
     - Need additional cameras?

  3. Audit access logs
     - Who's accessing the system?
     - Are permissions correct?
     - Any suspicious activity?

  4. Staff training
     - Update manager training on new features
     - Review false positives with stores
     - Gather feedback for improvements

  5. Security audit
     - Review database access patterns
     - Check for SQL injection attempts
     - Verify authentication security
```

---

## Performance Tuning Guide

### Optimize Analysis Performance

```php
// Use prepared statements
$stmt = $pdo->prepare("
    SELECT * FROM sales_transactions
    WHERE staff_id = ?
    AND created_at >= ?
    LIMIT ?
");
$stmt->execute([$staffId, $dateThreshold, 10000]);

// Use PDO for connection pooling
$pdo = new PDO(
    'mysql:host=localhost;dbname=cis;charset=utf8mb4',
    'user',
    'pass',
    [
        PDO::ATTR_PERSISTENT => true,      // Connection pooling
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]
);
```

### Database Query Performance

```sql
-- Use EXPLAIN to optimize queries
EXPLAIN SELECT * FROM sales_transactions
WHERE staff_id = 45 AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY);

-- Should show:
-- - key: idx_staff_id or idx_created_at (using index)
-- - rows: reasonable number (not 1M+)
-- - type: range or ref (not ALL)

-- If type = ALL, add missing index:
ALTER TABLE sales_transactions ADD INDEX idx_staff_created (staff_id, created_at);
```

### PHP Configuration for Performance

```ini
; /etc/php/8.1/fpm/pool.d/cis.conf

; Process pool settings
pm = dynamic
pm.max_children = 20
pm.start_servers = 4
pm.min_spare_servers = 2
pm.max_spare_servers = 8

; Timeout for long-running analysis
request_terminate_timeout = 120

; Memory limit
php_value[memory_limit] = 256M

; Enable opcache for performance
php_flag[opcache.enable] = 1
php_value[opcache.memory_consumption] = 128
```

---

## Logging & Debugging

### Enable Debug Logging

```php
// In fraud-detection.config.php
'logging' => [
    'level' => 'DEBUG',  // DEBUG, INFO, WARNING, ERROR
    'handlers' => [
        'file' => [
            'path' => '/var/log/fraud-detection/debug.log',
            'format' => '[{timestamp}] {level}: {message}'
        ],
        'syslog' => [
            'enabled' => true,
            'facility' => LOG_LOCAL0,
        ]
    ]
];
```

### View Recent Logs

```bash
# Real-time log monitoring
tail -f /var/log/fraud-detection/behavioral-analytics.log

# Search for errors
grep ERROR /var/log/fraud-detection/*.log | tail -50

# Monitor camera commands
grep "CAMERA_COMMAND" /var/log/fraud-detection/camera-targeting.log

# Check API requests
tail -100 /var/log/fraud-detection/api.log | grep "GET\|POST"
```

### Debug Specific Analysis

```php
// Enable detailed debugging for one staff member
$logger->setLevel('DEBUG');
$logger->context = ['staff_id' => 45];

$analysis = $engine->analyzeStaffMember(45, 'daily');

// Logs will include:
// - Query execution times
// - Data loaded (transaction count, etc)
// - Risk factor calculations
// - Final scoring steps
```

---

## Incident Response

### If System Unavailable

```
1. Check service status
   systemctl status php-fpm
   systemctl status mysql
   systemctl status nginx

2. View error logs
   tail -100 /var/log/php-fpm.log
   tail -100 /var/log/mysql/error.log

3. Restart services
   systemctl restart php-fpm
   systemctl restart mysql

4. Verify database
   mysql -u root -p -e "SELECT 1"

5. Check disk space
   df -h

6. Review recent changes
   git log --oneline -10
```

### If Analysis Not Running

```
1. Check cron jobs
   crontab -l | grep fraud

2. Check last execution
   ls -la /var/log/fraud-detection/
   tail -50 /var/log/fraud-detection/behavioral-analytics.log

3. Run analysis manually
   php /path/to/bootstrap.php daily-analysis

4. Check output
   - Errors? Review the error messages
   - Success? Check database for new results

5. Verify data sources
   - Can connect to CIS database?
   - Any data in sales_transactions table?
   - Deputy integration working?
```

### If Cameras Not Responding

```
1. Check camera network
   ping camera_ip
   nmap -p 80,443,8080 camera_ip

2. Verify API endpoint
   curl -v http://camera_ip/api/status

3. Check authentication
   - Are API credentials correct?
   - Is HMAC signature calculation correct?

4. Review recent commands
   tail -50 /var/log/fraud-detection/camera-targeting.log

5. Test individual camera
   curl -X POST http://camera_ip/api/command \
     -H "Authorization: HMAC-SHA256 [signature]" \
     -d '{"action": "ping"}'

6. Restart camera if needed
   (Method depends on camera model)
```

---

## Backup & Recovery

### Database Backup

```bash
# Daily automated backup
mysqldump -u cis_user -p cis_database \
  --single-transaction \
  --quick \
  --lock-tables=false | gzip > /backups/cis_$(date +%Y%m%d).sql.gz

# Verify backup
gunzip -t /backups/cis_$(date +%Y%m%d).sql.gz
```

### Recovery Procedure

```bash
# 1. Stop the application
systemctl stop php-fpm

# 2. Restore database
gunzip < /backups/cis_20251114.sql.gz | mysql -u root -p cis_database

# 3. Verify restoration
mysql -u root -p cis_database -e "SELECT COUNT(*) FROM behavioral_analysis_results"

# 4. Start application
systemctl start php-fpm

# 5. Verify everything works
curl http://localhost/api/fraud-detection/dashboard
```

---

**Comprehensive Troubleshooting & Operations Guide Complete**

For additional support, contact the development team or refer to specific module documentation.

*Last Updated: November 14, 2025*
