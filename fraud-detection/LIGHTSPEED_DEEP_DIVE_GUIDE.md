# LIGHTSPEED DEEP DIVE FRAUD ANALYSIS GUIDE

## 🎯 MISSION CRITICAL: Complete POS Fraud Detection

This system performs **COMPREHENSIVE** analysis of Lightspeed/Vend POS data to detect ALL possible fraud vectors. It goes far beyond simple transaction monitoring to examine payment patterns, customer account manipulation, inventory fraud, cash handling discrepancies, and banking anomalies.

---

## 📊 7 MAJOR FRAUD CATEGORIES ANALYZED

### SECTION 1: PAYMENT TYPE FRAUD

**What We Detect:**
- ✅ Payments to unusual/custom payment types
- ✅ Random payment type usage (low frequency, high value)
- ✅ Excessive split payment patterns
- ✅ Abnormal cash vs card ratios (compared to outlet average)

**Real-World Fraud Scenarios:**

**Scenario 1.1: "The Gift Card Shuffle"**
- Staff creates custom payment type "GIFT_CARD_REDEMPTION"
- Processes cash sales as gift card payments
- Pockets the cash
- **Detection**: Unusual payment type with high usage

**Scenario 1.2: "The Random Payment Roulette"**
- Staff randomly assigns different payment types to similar transactions
- Creates confusion in reconciliation
- Skims differences
- **Detection**: Payment type with <1 use/week but $100+ value

**Scenario 1.3: "The Split Payment Scheme"**
- Excessive split payments (e.g., $50 cash + $50 EFTPOS)
- Each component slightly off to pocket difference
- **Detection**: >5 split payments per day consistently

**Scenario 1.4: "The Cash Hoarder"**
- Staff has 80% cash sales vs outlet average 40%
- Selectively processes cash to maximize skimming opportunities
- **Detection**: Cash ratio >20% different from outlet average

---

### SECTION 2: CUSTOMER ACCOUNT FRAUD

**What We Detect:**
- ✅ Sales on random/fake customer accounts
- ✅ Excessive account credit usage
- ✅ Loyalty point manipulation
- ✅ Store credit abuse
- ✅ Random customer assignment patterns

**Real-World Fraud Scenarios:**

**Scenario 2.1: "The Phantom Customer"**
- Staff creates fake customer "Test Customer" or "Walk In"
- Processes sales on account
- Never collects payment
- Pockets cash equivalents
- **Detection**: Sales to suspicious customer names (test, dummy, fake)

**Scenario 2.2: "The Account Credit Scam"**
- Staff places sales on legitimate customer accounts
- Customer isn't aware
- Staff manipulates account credit to cover tracks
- **Detection**: >3 account sales per day to same customer

**Scenario 2.3: "The Loyalty Points Heist"**
- Staff redeems more loyalty points than customer earned
- Or manually adds points without purchases
- Customer's friend/family benefits
- **Detection**: Points redeemed > 2x points earned

**Scenario 2.4: "The Store Credit Carousel"**
- Staff issues excessive store credits
- Friends/family use credits for free products
- **Detection**: >$500 store credit used by single customer in analysis period

**Scenario 2.5: "The Random Customer Generator"**
- Nearly every sale assigned to different customer
- Creates chaos in customer records
- Hides fraudulent patterns
- **Detection**: Unique customers > 80% of total sales

---

### SECTION 3: INVENTORY MOVEMENT FRAUD

**What We Detect:**
- ✅ Large stock adjustments without reason
- ✅ Stock adjustments with missing/vague reasons
- ✅ Unusual transfer patterns between outlets
- ✅ Receiving discrepancies (expected vs actual)
- ✅ Excessive shrinkage claims

**Real-World Fraud Scenarios:**

**Scenario 3.1: "The Invisible Theft"**
- Staff adjusts inventory down by 20 units
- Reason: "Shrinkage" or left blank
- Actually stealing products
- **Detection**: Adjustment >10 units without proper reason

**Scenario 3.2: "The Transfer Shuffle"**
- Staff creates fake transfers between outlets
- Products "disappear" in transit
- Actually stolen or sold privately
- **Detection**: >10 transfers or $5,000+ in transfers

**Scenario 3.3: "The Receiving Discrepancy Scheme"**
- Staff receives stock from supplier
- Records fewer items than actually received
- Sells extra items privately
- **Detection**: Consistent receiving discrepancies >20 items total

**Scenario 3.4: "The Shrinkage Epidemic"**
- Staff claims excessive shrinkage
- Products actually taken or sold off-books
- **Detection**: >$500 shrinkage value in analysis period

---

### SECTION 4: CASH REGISTER CLOSURE FRAUD

**What We Detect:**
- ✅ Till closure discrepancies (shortages/overages)
- ✅ Pattern of consistent small shortages (skimming)
- ✅ Float manipulation (variable float amounts)
- ✅ Expected vs actual cash variances

**Real-World Fraud Scenarios:**

**Scenario 4.1: "The Daily Skim"**
- Staff consistently shorts the till by $10-$20 per day
- Small enough to avoid immediate attention
- Adds up to hundreds per month
- **Detection**: ≥5 small shortages ($5-$20) in analysis period

**Scenario 4.2: "The Big Score"**
- Staff shorts till by $50+
- Blames "counting error" or "busy day"
- **Detection**: Single variance >$50

**Scenario 4.3: "The Float Juggle"**
- Staff varies the opening float amount
- Uses variable float to hide shortages
- **Detection**: Float standard deviation >$50

**Scenario 4.4: "The Overage Coverup"**
- Staff overages till to hide previous shortage
- Creates false impression of balance
- **Detection**: Pattern of shortage followed by overage

---

### SECTION 5: BANKING & DEPOSIT FRAUD

**What We Detect:**
- ✅ Deposit discrepancies (expected vs deposited)
- ✅ Delayed deposits (>3 days)
- ✅ Missing deposits (cash sales with no corresponding deposit)
- ✅ Weekly reconciliation gaps

**Real-World Fraud Scenarios:**

**Scenario 5.1: "The Missing Deposit"**
- Staff collects $500 cash for the day
- Never makes deposit
- Keeps cash
- **Detection**: Cash sales with no matching deposit record

**Scenario 5.2: "The Deposit Discount"**
- Staff deposits $450 instead of $500
- Pockets $50 difference
- **Detection**: Deposit discrepancy >$50

**Scenario 5.3: "The Delayed Deposit Game"**
- Staff delays deposit by 5+ days
- Uses cash for personal expenses
- Eventually deposits (minus "interest")
- **Detection**: Deposit delay >3 days

**Scenario 5.4: "The Weekly Reconciliation Gap"**
- Staff's weekly deposits don't match weekly cash sales
- Consistent gap accumulates
- **Detection**: Weekly sales vs deposits gap >$200

---

### SECTION 6: TRANSACTION MANIPULATION

**What We Detect:**
- ✅ Excessive void rate (>10% of transactions)
- ✅ Immediate void patterns (<5 minutes after sale)
- ✅ Excessive refund rate (>15% of sales)
- ✅ Discount abuse (>20% average discount)
- ✅ Excessive price overrides

**Real-World Fraud Scenarios:**

**Scenario 6.1: "The Void Void"**
- Staff completes sale, customer pays cash
- Staff immediately voids transaction
- Pockets cash
- **Detection**: >5 voids within 5 minutes of sale

**Scenario 6.2: "The Refund Racket"**
- Staff processes fake refunds
- Keeps refund cash
- No actual product returned
- **Detection**: Refund rate >15% of sales

**Scenario 6.3: "The Discount Buddy System"**
- Staff gives excessive discounts to friends/family
- 50%+ off regular prices
- **Detection**: Average discount >20%

**Scenario 6.4: "The Price Override Scheme"**
- Staff overrides prices to artificially low amounts
- Friends/family get products at cost
- **Detection**: >20 price overrides in analysis period

---

### SECTION 7: RECONCILIATION FRAUD

**What We Detect:**
- ✅ Daily sales vs register closure discrepancies
- ✅ Cross-outlet anomalies (if staff works multiple locations)
- ✅ Weekly summary gaps

**Real-World Fraud Scenarios:**

**Scenario 7.1: "The Daily Discrepancy"**
- Staff's daily sales total doesn't match register closure
- $50+ gap consistently
- **Detection**: Daily reconciliation gap >$50

**Scenario 7.2: "The Outlet Hopper"**
- Staff works multiple outlets
- Average transaction at Outlet A: $100
- Average transaction at Outlet B: $25
- Suspicious pattern indicating different behavior
- **Detection**: Max avg transaction / Min avg transaction > 2x

---

## 🎯 USAGE EXAMPLES

### Example 1: Analyze Single Staff Member

```php
use FraudDetection\LightspeedDeepDiveAnalyzer;

$analyzer = new LightspeedDeepDiveAnalyzer($pdo, [
    'analysis_window_days' => 30,
    'enable_all_checks' => true,
    'alert_on_critical' => true,
]);

$results = $analyzer->analyzeStaff(
    staffId: 42,
    days: 30
);

// Check risk level
if ($results['risk_level'] === 'critical') {
    echo "⚠️ CRITICAL RISK: {$results['risk_score']}/100\n";
    echo "Indicators found: " . count($results['fraud_indicators']) . "\n";
    
    // Show critical alerts
    foreach ($results['critical_alerts'] as $alert) {
        echo "🚨 {$alert['description']}\n";
    }
}
```

### Example 2: Automated Nightly Analysis

```php
// Run for all active staff
$stmt = $pdo->query("SELECT id FROM staff_accounts WHERE active = 1");
$staffIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

foreach ($staffIds as $staffId) {
    $results = $analyzer->analyzeStaff($staffId, 7); // Last 7 days
    
    // Send alerts for high-risk staff
    if ($results['risk_score'] >= 60) {
        sendAlertToManagement($staffId, $results);
    }
}
```

### Example 3: Integration with Main Fraud Analyzer

```php
use FraudDetection\MultiSourceFraudAnalyzer;

$multiSource = new MultiSourceFraudAnalyzer($pdo, [
    'analysis_window_days' => 30,
    'enable_deep_camera_correlation' => true,
]);

// Automatically includes Lightspeed deep-dive
$analysis = $multiSource->analyzeStaff(42);

// Access Lightspeed-specific results
$lightspeedResults = $analysis['lightspeed_deep_dive'];

// View by category
foreach ($lightspeedResults['sections'] as $section => $data) {
    echo "{$data['section_name']}: " . count($data['issues_found']) . " issues\n";
}
```

---

## 📊 DATABASE SCHEMA

### Main Analysis Table

```sql
lightspeed_deep_dive_analysis
- id (PK)
- staff_id
- analysis_period_days
- risk_score (0-100)
- risk_level (low/medium/high/critical)
- indicator_count
- critical_alert_count
- analysis_data (JSON - complete results)
- created_at
```

### Fraud Tracking Tables (6 tables)

1. **payment_type_fraud_tracking** - Payment type issues
2. **customer_account_fraud_tracking** - Customer account manipulation
3. **inventory_fraud_tracking** - Inventory fraud
4. **register_closure_fraud_tracking** - Till discrepancies
5. **banking_fraud_tracking** - Deposit issues
6. **transaction_manipulation_tracking** - Void/refund abuse

Each tracking table has:
- Staff ID linkage
- Fraud type classification
- Severity score
- Investigation workflow (investigated flag + notes)
- Complete details in JSON

---

## 🚨 THRESHOLDS & SCORING

### Fraud Severity Levels

- **0.90-1.00**: CRITICAL - Immediate investigation required
- **0.75-0.89**: HIGH - Investigate within 24 hours
- **0.60-0.74**: MEDIUM - Investigate within 1 week
- **0.00-0.59**: LOW - Monitor and review

### Risk Score Calculation

Risk Score = (Average Severity × 100) + (Indicator Count × 2)

**Example:**
- 5 fraud indicators
- Average severity: 0.75
- Risk Score = (0.75 × 100) + (5 × 2) = 85

### Thresholds (Configurable)

```php
UNUSUAL_PAYMENT_TYPE_THRESHOLD = 5;      // Uses per week
CASH_VARIANCE_THRESHOLD = 50;            // Dollars
INVENTORY_ADJUSTMENT_THRESHOLD = 10;     // Qty per adjustment
CUSTOMER_ACCOUNT_SALES_THRESHOLD = 3;    // Sales per day
REFUND_PERCENTAGE_THRESHOLD = 15;        // % of sales
VOID_PERCENTAGE_THRESHOLD = 10;          // % of transactions
DISCOUNT_PERCENTAGE_THRESHOLD = 20;      // % discount
DEPOSIT_DELAY_THRESHOLD_DAYS = 3;        // Days to deposit
```

---

## 🔍 INVESTIGATION WORKFLOW

### Step 1: Review Analysis Results

```php
// Get latest analysis
$stmt = $pdo->prepare("
    SELECT * FROM lightspeed_deep_dive_analysis
    WHERE staff_id = ?
    ORDER BY created_at DESC
    LIMIT 1
");
$stmt->execute([$staffId]);
$analysis = $stmt->fetch(PDO::FETCH_ASSOC);

$data = json_decode($analysis['analysis_data'], true);
```

### Step 2: Prioritize by Severity

```php
// Get critical alerts
$criticalAlerts = $data['critical_alerts'];

// Sort by severity
usort($criticalAlerts, fn($a, $b) => $b['severity'] <=> $a['severity']);
```

### Step 3: Investigate Each Indicator

```php
// Mark as under investigation
$stmt = $pdo->prepare("
    UPDATE payment_type_fraud_tracking
    SET investigated = TRUE,
        investigated_by = ?,
        investigated_at = NOW(),
        investigation_notes = ?
    WHERE id = ?
");
$stmt->execute([$managerId, $notes, $indicatorId]);
```

### Step 4: Resolve Incident

```php
// After investigation, mark resolution
$stmt = $pdo->prepare("
    UPDATE transaction_camera_mismatches
    SET resolution = 'fraud_confirmed',
        resolved_by = ?,
        resolved_at = NOW(),
        resolution_notes = ?
    WHERE id = ?
");
$stmt->execute([$managerId, $resolutionNotes, $mismatchId]);
```

---

## 📈 VIEWS FOR QUICK ACCESS

### View: High Risk Staff

```sql
SELECT * FROM v_high_risk_staff_lightspeed;
```

Shows:
- Staff with high/critical risk levels
- Breakdown by fraud category
- Uninvestigated incident counts

### View: Uninvestigated Incidents

```sql
SELECT * FROM v_uninvestigated_fraud_incidents;
```

Shows all uninvestigated fraud incidents across all categories, sorted by severity.

### View: Cash Shortage Alerts

```sql
SELECT * FROM v_cash_shortage_alerts;
```

Shows CRITICAL cash shortages requiring immediate attention.

---

## ⚡ PERFORMANCE CONSIDERATIONS

### Indexes Created

All fraud tracking tables have composite indexes:
- `(staff_id, detected_at)` - Staff timeline queries
- `(fraud_type)` - Category filtering
- `(severity DESC)` - Priority sorting
- `(investigated)` - Workflow filtering

### Query Optimization

The analyzer uses:
- Single pass aggregation queries
- JSON storage for complete context (no joins needed)
- Computed columns for common calculations (variance, discrepancy)
- Materialized views for common reports

---

## 🔧 CONFIGURATION

### Config Array

```php
$config = [
    'analysis_window_days' => 30,        // How far back to analyze
    'enable_all_checks' => true,         // Run all 7 sections
    'alert_on_critical' => true,         // Send alerts for critical issues
];
```

### Environment Variables

```env
FRAUD_DETECTION_ENABLED=true
LIGHTSPEED_DEEP_DIVE_ENABLED=true
FRAUD_ALERT_EMAIL=security@company.com
FRAUD_ALERT_THRESHOLD=80
```

---

## 🚀 DEPLOYMENT CHECKLIST

### 1. Database Migration

```bash
mysql -u root -p < database/migrations/013_lightspeed_deep_dive_analysis.sql
```

### 2. Verify Tables Created

```sql
SHOW TABLES LIKE '%fraud%';
SHOW TABLES LIKE 'vend_%';
```

### 3. Test Analysis

```php
$results = $analyzer->analyzeStaff(1, 7);
var_dump($results);
```

### 4. Set Up Automated Analysis

```bash
# Add to crontab
0 2 * * * /usr/bin/php /path/to/run_nightly_analysis.php
```

### 5. Configure Alerts

```php
// In MultiSourceFraudAnalyzer
if ($results['risk_score'] >= 80) {
    sendCriticalAlert($results);
}
```

### 6. Train Management

- Show how to access views
- Explain investigation workflow
- Define escalation procedures

---

## 📋 SUMMARY

The Lightspeed Deep Dive Analyzer provides **FORENSIC-GRADE** POS fraud detection covering:

✅ **7 fraud categories** with 20+ specific fraud types
✅ **Real-world fraud scenarios** with detection methods
✅ **Automated analysis** with risk scoring
✅ **Investigation workflow** with resolution tracking
✅ **Database views** for quick access to critical data
✅ **Complete integration** with multi-source fraud detection

**This is the MOST COMPREHENSIVE POS fraud detection system available.**

Every possible way staff can manipulate Lightspeed/Vend data is analyzed, scored, tracked, and reported.

🎯 **MISSION: DETECT ALL FRAUD. LEAVE NO STONE UNTURNED.**
