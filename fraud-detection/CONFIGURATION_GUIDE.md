# 🔧 FRAUD DETECTION SYSTEM - CONFIGURATION & SETUP GUIDE

## 🎯 WHAT WE JUST ADDED

You now have a **PRODUCTION-GRADE configuration system** with:

✅ **External Configuration File** - No more hardcoded constants
✅ **Outlet-Specific Overrides** - Different thresholds per location
✅ **Staff Exclusions** - Managers can do unusual things without alerts
✅ **Whitelisting System** - Legitimate edge cases excluded
✅ **Alert Management** - Email/Slack/SMS with throttling
✅ **Grace Periods** - New staff get learning period
✅ **False Positive Tracking** - System learns from mistakes
✅ **Audit Trail** - Track who changed what and why
✅ **Seasonal Adjustments** - Christmas = more refunds expected
✅ **Dry-Run Mode** - Test without storing results

---

## 📁 NEW FILES CREATED

```
fraud-detection/
├── config/
│   └── fraud_detection_config.php (570 lines)
│       └── Complete configuration with 150+ settings
│
├── ConfigManager.php (350 lines)
│   └── Handles loading, validation, overrides
│
├── AlertManager.php (420 lines)
│   └── Sends alerts via email, Slack, SMS
│
└── database/migrations/
    └── 014_alert_config_tables.sql (150 lines)
        ├── fraud_alert_log
        ├── fraud_false_positives
        ├── fraud_config_audit
        ├── fraud_staff_grace_periods
        └── fraud_threshold_adjustments
```

**Total Added: 1,490 lines of configuration & alerting code**

---

## 🚀 QUICK START CONFIGURATION

### STEP 1: Review Configuration File

Open `config/fraud_detection_config.php` and review these key sections:

```php
// 1. Global Settings (line 20)
'global' => [
    'enabled' => true,              // Master on/off
    'dry_run_mode' => false,        // Set to true to test safely
    'analysis_window_days' => 30,   // How far back to analyze
    'grace_period_days' => 14,      // New staff learning period
],

// 2. Thresholds (lines 40-200)
// Each section has configurable thresholds
// Example:
'payment_type_fraud' => [
    'unusual_payment_type_threshold' => 5,  // Uses per week
    'cash_ratio_variance_threshold' => 20,  // % from outlet average
],

// 3. Alert Configuration (line 350)
'alerts' => [
    'email_alerts' => [
        'enabled' => true,
        'recipients' => [
            'security@company.com',    // ← CHANGE THIS!
            'manager@company.com',
        ],
    ],
],
```

---

## 🔧 CONFIGURATION WALKTHROUGH

### 1️⃣ **Enable Dry-Run Mode First**

**ALWAYS test in dry-run mode before going live:**

```php
// In config/fraud_detection_config.php (line 23):
'dry_run_mode' => true,  // Analyze but don't store results
```

Run analysis, verify thresholds are correct, then set to `false`.

---

### 2️⃣ **Configure Alert Recipients**

```php
// Line 354 in config file:
'alerts' => [
    'enabled' => true,

    'email_alerts' => [
        'enabled' => true,
        'recipients' => [
            'pearce.stephens@ecigdis.co.nz',  // Your email
            'security@vapeshed.co.nz',
        ],
        'from_address' => 'fraud-detection@vapeshed.co.nz',
    ],

    'slack_alerts' => [
        'enabled' => false,  // Enable after setting webhook
        'webhook_url' => '',  // Get from Slack workspace
        'channel' => '#fraud-alerts',
    ],
],
```

---

### 3️⃣ **Exclude Managers From Certain Checks**

**Managers legitimately do unusual things:**

```php
// Line 238 in config file:
'staff_exclusions' => [
    // Full exclusion (no fraud detection at all)
    'excluded_staff_ids' => [
        // 1,  // Owner - exclude completely
    ],

    // Partial exclusions (exclude specific sections)
    'partial_exclusions' => [
        5 => [  // Stock Manager ID
            'sections' => ['inventory_fraud'],  // Can do adjustments
        ],

        1 => [  // Store Manager ID
            'indicators' => [
                'excessive_price_overrides',  // Can override prices
                'excessive_discounts',         // Can give discounts
            ],
        ],
    ],
],
```

---

### 4️⃣ **Set Up Outlet-Specific Thresholds**

**Different outlets have different patterns:**

```php
// Line 220 in config file:
'outlet_overrides' => [
    // Outlet 1 is tourist area - more cash sales expected
    'outlet_1' => [
        'payment_type_fraud' => [
            'cash_ratio_variance_threshold' => 35,  // Higher tolerance
        ],
    ],

    // Outlet 5 is high-volume - more split payments
    'outlet_5' => [
        'payment_type_fraud' => [
            'split_payment_daily_threshold' => 15,
        ],
    ],

    // Outlet 3 sells perishables - more shrinkage expected
    'outlet_3' => [
        'inventory_fraud' => [
            'shrinkage_value_threshold' => 1000,  // Higher tolerance
        ],
    ],
],
```

---

### 5️⃣ **Whitelist Legitimate Edge Cases**

```php
// Line 258 in config file:
'whitelisting' => [
    // Corporate accounts that legitimately use ACCOUNT payment
    'whitelisted_customer_ids' => [
        'CORP_MAIN_001',
        'WHOLESALE_ACCOUNT_002',
    ],

    // Products that naturally have high adjustments
    'whitelisted_product_ids' => [
        'DEMO_UNIT_001',  // Demo units get adjusted often
        'TESTER_PRODUCT_002',
    ],

    // Legitimate adjustment reasons (won't trigger alerts)
    'legitimate_adjustment_reasons' => [
        'damaged',
        'expired',
        'sample',
        'promotional display',
        'manager approved',
        'warranty return',
    ],
],
```

---

### 6️⃣ **Configure Grace Periods for New Staff**

**New staff need time to learn, don't alert on them immediately:**

```php
// Line 29 in config file:
'grace_period_days' => 14,  // 2 weeks learning period

// Then add new staff to grace period table:
INSERT INTO fraud_staff_grace_periods
(staff_id, hire_date, grace_period_end_date, grace_period_days)
VALUES
(25, '2024-11-01', DATE_ADD('2024-11-01', INTERVAL 14 DAY), 14);
```

---

### 7️⃣ **Set Up Seasonal Adjustments**

**Christmas has more refunds, adjust thresholds automatically:**

```php
// Line 445 in config file:
'seasonal_adjustments' => [
    'enabled' => true,
    'periods' => [
        'christmas' => [
            'start' => '12-01',
            'end' => '01-07',
            'multipliers' => [
                'refund_percentage_threshold' => 1.5,  // 50% higher
                'discount_percentage_threshold' => 1.3, // 30% higher
            ],
        ],

        'black_friday' => [
            'start' => '11-20',
            'end' => '11-30',
            'multipliers' => [
                'discount_percentage_threshold' => 2.0,  // 100% higher
            ],
        ],
    ],
],
```

---

### 8️⃣ **Configure Alert Throttling**

**Prevent alert spam:**

```php
// Line 376 in config file:
'alert_throttle' => [
    'max_alerts_per_staff_per_day' => 3,  // Max 3 alerts per staff per day
    'cooldown_hours' => 6,                 // 6 hours between alerts
],
```

---

## 💻 USING THE CONFIGURATION

### Load Configuration in Your Code

```php
use FraudDetection\ConfigManager;

// Get singleton instance
$config = ConfigManager::getInstance();

// Get any configuration value
$threshold = $config->get('payment_type_fraud.cash_variance_threshold');

// Get outlet-specific value
$outletThreshold = $config->getForOutlet(
    'payment_type_fraud.cash_variance_threshold',
    'outlet_5'
);

// Check if staff is excluded
if ($config->isStaffExcluded($staffId)) {
    // Skip this staff member
}

// Check if in dry-run mode
if ($config->isDryRun()) {
    // Don't store results
}

// Check if customer is whitelisted
if ($config->isCustomerWhitelisted($customerId)) {
    // Don't flag this customer
}
```

---

### Send Alerts

```php
use FraudDetection\AlertManager;

$alertManager = new AlertManager($pdo, $config);

// Send alert for high-risk staff
$results = $alertManager->sendFraudAlert($staffId, $analysisResults);

// Returns:
// [
//     'email' => ['status' => 'sent', 'recipients' => [...], 'count' => 2],
//     'slack' => ['status' => 'sent', 'http_code' => 200],
// ]
```

---

### Track False Positives

```php
// When investigating, if it's a false positive:
$stmt = $pdo->prepare("
    INSERT INTO fraud_false_positives
    (staff_id, indicator_type, fraud_category, original_severity,
     marked_false_positive_by, reason, incident_data)
    VALUES (?, ?, ?, ?, ?, ?, ?)
");
$stmt->execute([
    $staffId,
    'excessive_refunds',
    'transaction_manipulation',
    0.85,
    $managerId,  // Who marked it as FP
    'Customer returned defective products legitimately',
    json_encode($incidentDetails)
]);

// System will learn and adjust over time
```

---

## 📊 MONITORING & TUNING

### View Alert Summary

```sql
-- See all alerts sent
SELECT * FROM v_alert_summary_by_staff
ORDER BY total_alerts DESC;

-- See false positive rates
SELECT * FROM v_false_positive_rate_by_indicator
ORDER BY false_positive_rate_percentage DESC;

-- See staff in grace period
SELECT * FROM v_staff_in_grace_period;
```

---

### Adjust Thresholds Based on Data

```php
// After running for a week, check false positive rate
$stmt = $pdo->query("
    SELECT
        indicator_type,
        false_positive_count,
        false_positive_rate_percentage
    FROM v_false_positive_rate_by_indicator
    WHERE false_positive_rate_percentage > 10  -- > 10% FP rate
");

// For high FP rates, increase thresholds in config file
// Then log the change:
$stmt = $pdo->prepare("
    INSERT INTO fraud_config_audit
    (config_key, old_value, new_value, changed_by, reason)
    VALUES (?, ?, ?, ?, ?)
");
$stmt->execute([
    'payment_type_fraud.cash_ratio_variance_threshold',
    '20',
    '25',
    $managerId,
    'False positive rate was 15%, increased tolerance'
]);
```

---

## 🎯 RECOMMENDED TUNING PROCESS

### Week 1: Observation
1. ✅ Enable dry-run mode
2. ✅ Run nightly analysis
3. ✅ Review all alerts (don't act on them)
4. ✅ Track false positive rate

### Week 2: Calibration
1. ✅ Adjust thresholds based on FP rate
2. ✅ Add outlet-specific overrides
3. ✅ Whitelist legitimate edge cases
4. ✅ Exclude managers from certain checks

### Week 3: Testing
1. ✅ Disable dry-run mode
2. ✅ Enable alerts (start with email only)
3. ✅ Monitor alert volume
4. ✅ Fine-tune throttling settings

### Week 4: Production
1. ✅ Full production deployment
2. ✅ Enable Slack alerts
3. ✅ Train management on investigation workflow
4. ✅ Weekly review meetings

---

## 🚨 ALERT EXAMPLES

### Email Alert Example

```
Subject: [FRAUD ALERT] CRITICAL Risk - John Smith (Score: 92/100)

🚨 FRAUD DETECTION ALERT
92/100
Risk Level: critical

Staff Member
Name: John Smith
ID: 42
Outlet: Auckland CBD
Analysis Date: 2024-11-14 09:15:00

Critical Alerts (3)
• Cash shortage: $125 on 2024-11-13
• Missing deposit: $500 cash sales with no deposit
• Ghost transaction: 5 transactions without camera presence

[View Full Analysis Button]
```

### Slack Alert Example

```
🚨 critical Risk Alert - John Smith
Risk Score: 92/100

Staff Member: John Smith (ID: 42)
Fraud Indicators: 12

Critical Alerts:
• Cash shortage: $125 on 2024-11-13
• Missing deposit: $500 cash sales
• Ghost transaction: 5 without camera
```

---

## 📋 CONFIGURATION CHECKLIST

Before going to production:

- [ ] Review all thresholds in config file
- [ ] Set alert recipients (email addresses)
- [ ] Configure outlet-specific overrides
- [ ] Add staff exclusions (managers)
- [ ] Whitelist legitimate edge cases
- [ ] Set grace periods for new staff
- [ ] Enable dry-run mode first
- [ ] Test alert emails (send test)
- [ ] Set up Slack webhook (optional)
- [ ] Configure alert throttling
- [ ] Deploy database migration 014
- [ ] Test configuration loading
- [ ] Review and adjust seasonal periods
- [ ] Document any custom changes
- [ ] Train management on system

---

## 🎓 BEST PRACTICES

### DO:
✅ Start with dry-run mode
✅ Review false positives weekly
✅ Adjust thresholds based on data
✅ Document configuration changes
✅ Test alerts before enabling
✅ Use outlet-specific overrides
✅ Exclude managers appropriately
✅ Monitor alert volume

### DON'T:
❌ Deploy without testing first
❌ Ignore false positive rates
❌ Set thresholds too sensitive
❌ Alert on every single indicator
❌ Forget to document changes
❌ Enable all alerts immediately
❌ Skip the tuning process

---

## 🔥 ADVANCED FEATURES

### Auto-Tuning (Experimental)

```php
// In config file (line 442):
'auto_tune_thresholds' => true,  // Enable after 1 month of data
'auto_tune_target_false_positive_rate' => 0.05,  // Target 5% FP rate

// System will automatically adjust thresholds to maintain target FP rate
// Logs all changes to fraud_threshold_adjustments table
```

### Custom Thresholds Per Staff

```php
// For staff who legitimately have unusual patterns
$config->set("staff_overrides.{$staffId}.cash_variance_threshold", 100);
```

---

## 📞 NEXT STEPS

**You're now ready to:**

1. ✅ Review and customize the configuration file
2. ✅ Deploy database migration 014
3. ✅ Test in dry-run mode
4. ✅ Send test alerts
5. ✅ Begin tuning process

**Need help with:**
- Configuring specific thresholds?
- Setting up Slack integration?
- Creating custom outlet overrides?
- Implementing auto-tuning?

**Just ask!** 🚀
