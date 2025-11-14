# 🚀 FRAUD DETECTION SYSTEM - DEPLOYMENT CHECKLIST

## 📋 PRE-DEPLOYMENT CHECKLIST

### Phase 1: Database Setup ✅

- [ ] **Deploy Migration 014**
  ```bash
  cd /home/master/applications/jcepnzzkmj/public_html/modules/fraud-detection
  mysql -u username -p database_name < database/migrations/014_alert_config_tables.sql
  ```

- [ ] **Verify Tables Created**
  ```sql
  SHOW TABLES LIKE 'fraud_%';
  -- Expected: 17 tables (12 existing + 5 new)

  DESC fraud_alert_log;
  DESC fraud_false_positives;
  DESC fraud_config_audit;
  DESC fraud_staff_grace_periods;
  DESC fraud_threshold_adjustments;
  ```

- [ ] **Verify Views Created**
  ```sql
  SELECT * FROM v_alert_summary_by_staff LIMIT 1;
  SELECT * FROM v_false_positive_rate_by_indicator LIMIT 1;
  SELECT * FROM v_staff_in_grace_period LIMIT 1;
  ```

- [ ] **Add Resolution Columns to Existing Tables**
  ```sql
  -- Migration 014 adds these automatically
  DESC fraud_tracking_payment_type;
  -- Should see: resolved_by_staff_id, resolved_at, resolution_notes
  ```

---

### Phase 2: Configuration Setup ✅

- [ ] **Review Configuration File**
  - Open `config/fraud_detection_config.php`
  - Review all default thresholds
  - Adjust for your business needs

- [ ] **Set Alert Recipients**
  ```php
  // Line 354 in config file
  'recipients' => [
      'pearce.stephens@ecigdis.co.nz',  // Primary
      'security@vapeshed.co.nz',         // Security team
      // Add others as needed
  ],
  ```

- [ ] **Enable Dry-Run Mode First**
  ```php
  // Line 23 in config file
  'dry_run_mode' => true,  // IMPORTANT: Test first!
  ```

- [ ] **Configure Outlet Overrides**
  - Review each outlet's historical data
  - Set outlet-specific thresholds where needed
  - Document why overrides were added

- [ ] **Set Up Staff Exclusions**
  - Identify managers/owners who should be excluded
  - Add partial exclusions for specific checks
  - Document reasoning for each exclusion

- [ ] **Configure Whitelisting**
  - Add legitimate corporate customer accounts
  - Whitelist demo/tester products
  - List valid adjustment reasons

---

### Phase 3: Code Integration ✅

- [ ] **Verify Autoloading**
  ```bash
  cd /home/master/applications/jcepnzzkmj/public_html/modules/fraud-detection
  composer dump-autoload
  ```

- [ ] **Test ConfigManager Loading**
  ```php
  <?php
  require_once __DIR__ . '/vendor/autoload.php';

  use FraudDetection\ConfigManager;

  try {
      $config = ConfigManager::getInstance();
      echo "✅ Config loaded successfully\n";
      echo "Dry-run mode: " . ($config->isDryRun() ? 'YES' : 'NO') . "\n";
  } catch (Exception $e) {
      echo "❌ Error: " . $e->getMessage() . "\n";
  }
  ```

- [ ] **Test AlertManager Loading**
  ```php
  <?php
  use FraudDetection\AlertManager;

  try {
      $alertManager = new AlertManager($pdo, $config);
      echo "✅ AlertManager loaded successfully\n";
  } catch (Exception $e) {
      echo "❌ Error: " . $e->getMessage() . "\n";
  }
  ```

---

### Phase 4: Testing (Dry-Run Mode) ✅

- [ ] **Run Manual Analysis (1 Staff)**
  ```bash
  cd /home/master/applications/jcepnzzkmj/public_html/modules/fraud-detection
  php analyze.php --staff-id=5 --dry-run
  ```
  - ✅ No errors
  - ✅ Analysis completes
  - ✅ No database inserts (dry-run)
  - ✅ Results printed to screen

- [ ] **Run Full Nightly Analysis (Dry-Run)**
  ```bash
  php analyze.php --all --dry-run
  ```
  - ✅ All staff analyzed
  - ✅ Performance acceptable (< 5 minutes)
  - ✅ No memory issues
  - ✅ No database inserts

- [ ] **Test Alert System (Fake Alert)**
  ```php
  <?php
  // Create test analysis results
  $testResults = [
      'staff_id' => 99999,
      'staff_name' => 'Test User',
      'outlet_id' => 1,
      'overall_risk_score' => 85,
      'risk_level' => 'high',
      'critical_alerts' => [
          'Test alert for system verification'
      ],
      'top_indicators' => [
          [
              'name' => 'test_indicator',
              'risk_score' => 0.85,
              'severity' => 'high',
              'description' => 'Test alert',
          ]
      ],
  ];

  $alertManager = new AlertManager($pdo, $config);
  $result = $alertManager->sendFraudAlert(99999, $testResults);

  print_r($result);
  // Expected: Email sent, Slack sent (if enabled)
  ```

- [ ] **Verify Test Email Received**
  - Check inbox for test alert
  - Verify formatting looks good
  - Test "View Full Analysis" link works

- [ ] **Review Dry-Run Results**
  - Check for false positives
  - Adjust thresholds if needed
  - Document any configuration changes

---

### Phase 5: Grace Period Setup ✅

- [ ] **Identify New Staff (< 30 days)**
  ```sql
  SELECT
      staff_id,
      staff_name,
      hire_date,
      DATEDIFF(CURDATE(), hire_date) AS days_employed
  FROM staff
  WHERE hire_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
  ORDER BY hire_date DESC;
  ```

- [ ] **Add Grace Periods**
  ```sql
  INSERT INTO fraud_staff_grace_periods
  (staff_id, hire_date, grace_period_end_date, grace_period_days, reason)
  VALUES
  (25, '2024-10-20', DATE_ADD('2024-10-20', INTERVAL 14 DAY), 14, 'New hire'),
  (26, '2024-11-01', DATE_ADD('2024-11-01', INTERVAL 14 DAY), 14, 'New hire');
  ```

- [ ] **Verify Grace Period View**
  ```sql
  SELECT * FROM v_staff_in_grace_period;
  -- Should show all new staff with active grace periods
  ```

---

### Phase 6: Tuning Week 1 (Observation) ✅

**Goal: Collect data, identify false positives, no action yet**

- [ ] **Monday: Enable Daily Analysis (Dry-Run)**
  - Set up cron job:
    ```bash
    0 2 * * * cd /path/to/fraud-detection && php analyze.php --all --dry-run >> logs/analysis.log 2>&1
    ```
  - Verify cron runs successfully

- [ ] **Tuesday-Friday: Daily Review**
  - Review analysis results each morning
  - Track false positives manually:
    ```
    Staff ID | Indicator | FP? | Reason
    ---------|-----------|-----|--------
    5        | cash_var  | YES | Tourist outlet, cash normal
    8        | refunds   | NO  | Actually suspicious
    ```
  - Note patterns (by outlet, by indicator type)

- [ ] **Friday: Week 1 Summary**
  - Count total alerts
  - Count false positives
  - Calculate FP rate per indicator
  - Identify indicators with > 20% FP rate

---

### Phase 7: Tuning Week 2 (Calibration) ✅

**Goal: Adjust thresholds to reduce false positives**

- [ ] **Adjust Thresholds Based on Week 1 Data**
  - For high FP indicators, increase tolerance
  - Example:
    ```php
    // If "cash_ratio_variance_threshold" had 30% FP rate:
    // OLD: 'cash_ratio_variance_threshold' => 20,
    // NEW: 'cash_ratio_variance_threshold' => 30,
    ```
  - Document each change in config audit

- [ ] **Add Outlet-Specific Overrides**
  - Tourist outlets: Higher cash variance tolerance
  - High-volume outlets: Higher transaction counts
  - Smaller outlets: Lower thresholds

- [ ] **Update Whitelisting**
  - Add customer IDs that triggered FPs
  - Add products with legitimate high adjustments
  - Add valid adjustment reasons

- [ ] **Configure Staff Exclusions**
  - Exclude store managers from price override checks
  - Exclude stock managers from adjustment checks
  - Document reasoning

- [ ] **Log Configuration Changes**
  ```sql
  INSERT INTO fraud_config_audit
  (config_key, old_value, new_value, changed_by, reason)
  VALUES
  ('payment_type_fraud.cash_ratio_variance_threshold', '20', '30', 1,
   'Week 1 data showed 30% FP rate for cash variance in tourist outlets');
  ```

- [ ] **Re-run Week 1 Data With New Config**
  - Test adjusted config against Week 1 results
  - Verify FP rate drops to < 10%

---

### Phase 8: Tuning Week 3 (Testing) ✅

**Goal: Enable live mode, test alerts, monitor volume**

- [ ] **Monday: Disable Dry-Run Mode**
  ```php
  // In config file
  'dry_run_mode' => false,  // LIVE MODE!
  ```

- [ ] **Enable Email Alerts Only (Start Conservative)**
  ```php
  'email_alerts' => ['enabled' => true],
  'slack_alerts' => ['enabled' => false],  // Add later
  ```

- [ ] **Update Cron Job (Remove --dry-run)**
  ```bash
  0 2 * * * cd /path/to/fraud-detection && php analyze.php --all >> logs/analysis.log 2>&1
  ```

- [ ] **Monitor Alert Volume Daily**
  ```sql
  SELECT
      DATE(created_at) AS alert_date,
      COUNT(*) AS alerts_sent
  FROM fraud_alert_log
  GROUP BY DATE(created_at)
  ORDER BY alert_date DESC;
  ```

- [ ] **Check Throttling Is Working**
  ```sql
  -- Staff shouldn't get more than configured max per day
  SELECT
      staff_id,
      DATE(created_at) AS alert_date,
      COUNT(*) AS alerts_received,
      (SELECT max_alerts_per_staff_per_day
       FROM fraud_detection_config) AS configured_max
  FROM fraud_alert_log
  GROUP BY staff_id, DATE(created_at)
  HAVING COUNT(*) > configured_max;
  -- Should return 0 rows
  ```

- [ ] **Fine-Tune Alert Settings**
  - Adjust throttling if too many/few alerts
  - Adjust minimum risk score for alerts
  - Test different alert levels

- [ ] **Track False Positives in Database**
  ```sql
  -- When investigating, mark FPs:
  INSERT INTO fraud_false_positives
  (staff_id, indicator_type, fraud_category, original_severity,
   marked_false_positive_by, reason)
  VALUES (5, 'cash_ratio_variance', 'payment_type_fraud', 0.75, 1,
          'Tourist outlet - high cash sales are normal here');
  ```

---

### Phase 9: Week 4 (Production) ✅

**Goal: Full deployment with all features**

- [ ] **Enable Slack Alerts**
  ```php
  'slack_alerts' => [
      'enabled' => true,
      'webhook_url' => 'https://hooks.slack.com/services/YOUR/WEBHOOK/URL',
      'channel' => '#fraud-alerts',
  ],
  ```

- [ ] **Test Slack Integration**
  - Send test alert
  - Verify formatting
  - Verify channel routing

- [ ] **Train Management Team**
  - [ ] Schedule training session
  - [ ] Walk through alert emails
  - [ ] Demonstrate investigation workflow
  - [ ] Show how to mark false positives
  - [ ] Explain resolution process

- [ ] **Document Investigation Procedures**
  ```markdown
  When you receive a fraud alert:

  1. Review the alert details (risk score, indicators)
  2. Check camera footage for the flagged dates
  3. Review POS transaction logs
  4. Interview the staff member
  5. Make determination (fraud/false positive/needs investigation)
  6. If false positive:
     - Mark in database
     - Document reason
     - Consider config adjustment
  7. If fraud:
     - Follow HR procedures
     - Document evidence
     - Mark as resolved in database
  ```

- [ ] **Set Up Weekly Review Meeting**
  - Every Monday at 9am
  - Review previous week's alerts
  - Discuss false positive trends
  - Adjust configuration as needed
  - Review resolution outcomes

---

## 📊 POST-DEPLOYMENT MONITORING

### Daily Checks (First 2 Weeks)

- [ ] Check cron job ran successfully
- [ ] Review alert volume
- [ ] Check for errors in logs
- [ ] Respond to any critical alerts

### Weekly Metrics

```sql
-- Alert volume trend
SELECT
    WEEK(created_at) AS week_num,
    COUNT(*) AS total_alerts,
    COUNT(DISTINCT staff_id) AS unique_staff
FROM fraud_alert_log
GROUP BY WEEK(created_at)
ORDER BY week_num DESC;

-- False positive rate
SELECT * FROM v_false_positive_rate_by_indicator
ORDER BY false_positive_rate_percentage DESC;

-- Top alerting indicators
SELECT
    indicator_type,
    COUNT(*) AS times_triggered
FROM fraud_tracking_summary
GROUP BY indicator_type
ORDER BY times_triggered DESC
LIMIT 10;

-- Staff with most alerts
SELECT * FROM v_alert_summary_by_staff
ORDER BY total_alerts DESC
LIMIT 10;
```

### Monthly Review

- [ ] Calculate overall false positive rate
- [ ] Review configuration changes made
- [ ] Assess system effectiveness
- [ ] Identify areas for improvement
- [ ] Update thresholds based on seasonal trends
- [ ] Review and close resolved cases

---

## 🚨 TROUBLESHOOTING

### Issue: Too Many False Positives

**Symptoms:**
- FP rate > 15% for specific indicator
- Alerts feel like noise
- Management ignoring alerts

**Solutions:**
1. Increase threshold for that indicator
2. Add outlet-specific overrides
3. Whitelist legitimate edge cases
4. Review if indicator is valuable

### Issue: Too Few Alerts

**Symptoms:**
- No alerts for known issues
- Obvious fraud not detected
- System feels useless

**Solutions:**
1. Decrease thresholds
2. Review staff exclusions (too broad?)
3. Check grace periods (expired?)
4. Verify analysis is running

### Issue: Alert Spam

**Symptoms:**
- Same staff getting multiple alerts per day
- Inbox flooded
- Alert fatigue

**Solutions:**
1. Enable/adjust throttling:
   ```php
   'max_alerts_per_staff_per_day' => 1,  // Reduce to 1
   'cooldown_hours' => 12,  // Increase cooldown
   ```
2. Increase minimum risk score for alerts
3. Consolidate similar indicators

### Issue: Alerts Not Sending

**Check:**
1. Email configuration correct?
2. Slack webhook URL valid?
3. Check `fraud_alert_log` for errors:
   ```sql
   SELECT * FROM fraud_alert_log
   WHERE status = 'failed'
   ORDER BY created_at DESC;
   ```
4. Check PHP error logs
5. Test with simple email:
   ```php
   mail('test@example.com', 'Test', 'Testing email from fraud system');
   ```

---

## ✅ FINAL CHECKLIST

Before marking deployment as COMPLETE:

- [ ] Database migration deployed successfully
- [ ] All 17 fraud tables exist
- [ ] Configuration file reviewed and customized
- [ ] Dry-run testing completed (1 week)
- [ ] False positive rate acceptable (< 10%)
- [ ] Thresholds adjusted based on data
- [ ] Outlet overrides configured
- [ ] Staff exclusions documented
- [ ] Whitelisting configured
- [ ] Grace periods set for new staff
- [ ] Alert system tested (email + Slack)
- [ ] Throttling working correctly
- [ ] Cron job running nightly
- [ ] Management team trained
- [ ] Investigation procedures documented
- [ ] Weekly review meeting scheduled
- [ ] Monitoring dashboard accessible
- [ ] Error handling tested
- [ ] Performance acceptable (< 5 min)
- [ ] All configuration changes documented

---

## 🎉 DEPLOYMENT COMPLETE!

**Your fraud detection system is now:**
- ✅ Production-hardened with comprehensive configuration
- ✅ Tuned to your business patterns
- ✅ Alerting via multiple channels
- ✅ Tracking false positives for learning
- ✅ Excluding legitimate edge cases
- ✅ Protecting against alert spam
- ✅ Documented and maintainable

**Next: Continue monitoring and tuning based on real-world usage!**

---

## 📞 SUPPORT

**Questions? Issues?**
- Review `CONFIGURATION_GUIDE.md` for detailed config help
- Check `README.md` for system overview
- Review logs: `logs/analysis.log`, `logs/errors.log`
- Check database: `fraud_alert_log`, `fraud_config_audit`

**Need advanced features?**
- Auto-tuning based on FP rates
- Integration with ticketing system
- Mobile app for alerts
- Advanced analytics dashboard
- Machine learning risk scoring
