# Technical Reference & Developer Documentation
## Behavioral Fraud Detection System - Complete API & Implementation Reference

---

## Class Reference Guide

### BehavioralAnalyticsEngine

The core analytics engine for analyzing staff behavioral patterns.

```php
use FraudDetection\BehavioralAnalyticsEngine;

$engine = new BehavioralAnalyticsEngine($pdo, [
    'high_risk_threshold' => 0.75,
    'medium_risk_threshold' => 0.50,
    'low_risk_threshold' => 0.25,
    'discount_threshold_percentage' => 15.0,
    'void_transaction_threshold' => 5,
    'refund_anomaly_threshold' => 3,
    'shrinkage_alert_threshold' => 50,
    'after_hours_access_alert' => true,
    'time_theft_check_enabled' => true,
    'peer_comparison_enabled' => true,
    'repeat_offender_weight' => 2.5,
]);
```

#### Methods

**analyzeAllStaff(string $timeWindow = 'daily'): array**
```php
// Run comprehensive analysis for all staff
$results = $engine->analyzeAllStaff('daily');

foreach ($results as $analysis) {
    echo $analysis['staff_name'] . ': ' . $analysis['risk_level'];
}
```
Returns array of staff with risk score > low_risk_threshold

**analyzeStaffMember(int $staffId, string $timeWindow = 'daily'): array**
```php
// Analyze single staff member
$analysis = $engine->analyzeStaffMember(45, 'daily');

echo json_encode([
    'staff_id' => $analysis['staff_id'],
    'risk_score' => $analysis['risk_score'],
    'risk_level' => $analysis['risk_level'],
    'should_target_cameras' => $analysis['should_target_cameras'],
    'risk_factors' => $analysis['risk_factors'],
    'recommendations' => $analysis['recommendations'],
]);
```

Returns comprehensive analysis array with risk score and factors

**saveAnalysisResults(array $analysis): bool**
```php
// Save analysis to database for auditing
$engine->saveAnalysisResults($analysis);
```

**getHistoricalAnalysis(int $staffId, int $days = 30): array**
```php
// Get trending data for staff member
$history = $engine->getHistoricalAnalysis(45, 30);

foreach ($history as $record) {
    echo $record['created_at'] . ': ' . $record['risk_score'];
}
```

Returns array of historical risk scores with timestamps

---

### DynamicCameraTargetingSystem

Manages real-time camera targeting and PTZ control.

```php
use FraudDetection\DynamicCameraTargetingSystem;

$targeting = new DynamicCameraTargetingSystem($pdo, [
    'total_cameras' => 102,
    'ptz_cameras_per_store' => 1,
    'enable_auto_targeting' => true,
    'min_risk_for_targeting' => 0.75,
    'tracking_duration_minutes' => 60,
    'max_concurrent_targets' => 5,
    'force_high_quality_recording' => true,
    'recording_bitrate_high_quality' => 'high',
    'send_alerts_to_managers' => true,
    'alert_channels' => ['email', 'sms', 'push'],
]);
```

#### Methods

**activateTargeting(array $analysis): bool**
```php
// Activate camera targeting for staff member
$success = $targeting->activateTargeting($analysis);

if ($success) {
    echo "Targeting activated for {$analysis['staff_name']}";
}
```

Requires analysis array from BehavioralAnalyticsEngine

**deactivateTargeting(int $staffId): bool**
```php
// Stop camera targeting
$success = $targeting->deactivateTargeting(45);

if ($success) {
    echo "Targeting deactivated";
}
```

**getActiveTargets(): array**
```php
// Get currently monitored individuals
$targets = $targeting->getActiveTargets();

foreach ($targets as $staffId => $targetInfo) {
    echo "{$staffId}: {$targetInfo['duration_minutes']} minutes remaining";
}
```

**getTargetingHistory(int $staffId, int $days = 30): array**
```php
// Get historical targeting records
$history = $targeting->getTargetingHistory(45, 30);

foreach ($history as $record) {
    echo "Activated: {$record['activated_at']}, Duration: {$record['duration_minutes']}";
}
```

---

### RealTimeAlertDashboard

Provides dashboard data and staff profile information.

```php
use FraudDetection\RealTimeAlertDashboard;

$dashboard = new RealTimeAlertDashboard($pdo, [
    'dashboard_refresh_interval' => 5,
    'alert_retention_days' => 30,
    'show_historical_data' => true,
    'enable_export' => true,
]);
```

#### Methods

**getDashboardData(int $storeId = null): array**
```php
// Get comprehensive dashboard data for store or all stores
$data = $dashboard->getDashboardData(3); // Store 3

echo "Critical alerts: " . count($data['critical_alerts']);
echo "Targeted individuals: " . count($data['targeted_individuals']);
echo "Active investigations: " . count($data['active_investigations']);
echo "System health: " . $data['system_health']['overall_status'];
```

Returns array with:
- summary (metrics)
- critical_alerts
- targeted_individuals
- active_investigations
- system_health
- recent_incidents

**getStaffProfile(int $staffId): array**
```php
// Get detailed staff profile
$profile = $dashboard->getStaffProfile(45);

echo json_encode([
    'profile' => $profile['profile'],
    'current_analysis' => $profile['current_analysis'],
    'historical_trends' => $profile['historical_trends'],
    'incident_history' => $profile['incident_history'],
]);
```

Returns comprehensive staff profile with all data

---

## Database Schema Reference

### behavioral_analysis_results

```sql
CREATE TABLE behavioral_analysis_results (
    id INT AUTO_INCREMENT PRIMARY KEY,
    staff_id INT NOT NULL,
    store_id INT NOT NULL,
    analysis_period VARCHAR(50),           -- 'daily', 'weekly', 'monthly'
    risk_score FLOAT NOT NULL,              -- 0.0 to 1.0
    risk_level VARCHAR(20),                 -- 'CRITICAL', 'HIGH', 'MEDIUM', 'LOW'
    risk_factors JSON,                      -- Array of detected factors
    raw_scores JSON,                        -- Individual factor scores
    recommendations JSON,                   -- Actionable recommendations
    camera_targeting TINYINT DEFAULT 0,    -- 1 if cameras activated
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_analysis (staff_id, analysis_period, DATE(created_at)),
    INDEX idx_risk_score (risk_score),
    INDEX idx_risk_level (risk_level),
    INDEX idx_created_at (created_at)
);
```

### camera_targeting_records

```sql
CREATE TABLE camera_targeting_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    staff_id INT NOT NULL,
    store_id INT NOT NULL,
    target_cameras JSON,                    -- Array of camera IDs
    risk_score FLOAT NOT NULL,
    risk_factors JSON,
    recommendations JSON,
    status VARCHAR(20) DEFAULT 'ACTIVE',   -- 'ACTIVE', 'INACTIVE', 'EXPIRED'
    activated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP,                   -- When targeting expires
    deactivated_at TIMESTAMP NULL,          -- When manually deactivated
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_staff_id (staff_id),
    INDEX idx_status (status),
    INDEX idx_expires_at (expires_at)
);
```

### camera_presets

```sql
CREATE TABLE camera_presets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    camera_id INT NOT NULL,
    zone_name VARCHAR(100),                 -- 'checkout', 'products', 'entry', 'floor'
    preset_id VARCHAR(100),
    pan FLOAT,                              -- 0-360 degrees
    tilt FLOAT,                             -- -90 to +90 degrees
    zoom FLOAT,                             -- 1-4x
    status VARCHAR(20) DEFAULT 'ACTIVE',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_preset (camera_id, preset_id),
    INDEX idx_zone_name (zone_name)
);
```

### fraud_incidents

```sql
CREATE TABLE fraud_incidents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    staff_id INT NOT NULL,
    store_id INT NOT NULL,
    incident_type VARCHAR(100),             -- 'VOID_FRAUD', 'THEFT', 'REFUND_FRAUD', etc.
    severity VARCHAR(20),                   -- 'CRITICAL', 'HIGH', 'MEDIUM', 'LOW'
    status VARCHAR(20) DEFAULT 'OPEN',      -- 'OPEN', 'IN_PROGRESS', 'ESCALATED', 'RESOLVED', 'DISMISSED'
    evidence_collected TINYINT DEFAULT 0,
    investigator_notes LONGTEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_staff_id (staff_id),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
);
```

### fraud_evidence

```sql
CREATE TABLE fraud_evidence (
    id INT AUTO_INCREMENT PRIMARY KEY,
    incident_id INT NOT NULL,
    evidence_type VARCHAR(100),             -- 'VIDEO', 'TRANSACTION', 'INVENTORY', etc.
    camera_id INT,
    timestamp_start DATETIME,               -- Evidence time range start
    timestamp_end DATETIME,                 -- Evidence time range end
    description LONGTEXT,
    file_path VARCHAR(500),                 -- Path to video/evidence file
    processed TINYINT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (incident_id) REFERENCES fraud_incidents(id),
    INDEX idx_incident_id (incident_id),
    INDEX idx_camera_id (camera_id)
);
```

---

## Integration Examples

### Example 1: Complete Fraud Detection Workflow

```php
<?php
require_once 'modules/fraud-detection/BehavioralAnalyticsEngine.php';
require_once 'modules/fraud-detection/DynamicCameraTargetingSystem.php';

use FraudDetection\BehavioralAnalyticsEngine;
use FraudDetection\DynamicCameraTargetingSystem;

// Initialize systems
$analytics = new BehavioralAnalyticsEngine($pdo);
$targeting = new DynamicCameraTargetingSystem($pdo);

// 1. Run daily analysis for all staff
$results = $analytics->analyzeAllStaff('daily');

// 2. Process each result
foreach ($results as $analysis) {
    // Save to database
    $analytics->saveAnalysisResults($analysis);

    // Check if targeting should be activated
    if ($analysis['should_target_cameras']) {
        try {
            $targeting->activateTargeting($analysis);

            // Log the activation
            $logger->info("Camera targeting activated", [
                'staff_id' => $analysis['staff_id'],
                'risk_score' => $analysis['risk_score'],
                'duration' => 60 . ' minutes'
            ]);
        } catch (Exception $e) {
            $logger->error("Failed to activate targeting: " . $e->getMessage());
        }
    }
}

// 3. Get dashboard data for display
$dashboardData = $dashboard->getDashboardData();

// 4. Display high-risk alerts
foreach ($dashboardData['critical_alerts'] as $alert) {
    echo "ALERT: {$alert['name']} - Risk Score: {$alert['risk_score']}";
}

// 5. Get active targets
$activeTargets = $targeting->getActiveTargets();
echo "Currently monitoring: " . count($activeTargets) . " individuals";
```

### Example 2: API Endpoint Implementation

```php
<?php
// /api/fraud-detection/custom-analysis.php

$staffId = $_GET['staff_id'] ?? null;
$timeWindow = $_GET['time_window'] ?? 'daily';

if (!$staffId) {
    http_response_code(400);
    echo json_encode(['error' => 'staff_id required']);
    exit;
}

try {
    $analytics = new BehavioralAnalyticsEngine($pdo);
    $analysis = $analytics->analyzeStaffMember($staffId, $timeWindow);

    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'staff_id' => $analysis['staff_id'],
        'staff_name' => $analysis['staff_name'],
        'risk_score' => round($analysis['risk_score'], 3),
        'risk_level' => $analysis['risk_level'],
        'factors' => $analysis['risk_factors'],
        'recommendations' => $analysis['recommendations'],
        'timestamp' => date('Y-m-d H:i:s'),
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
```

### Example 3: Scheduled Background Job

```php
<?php
// /jobs/daily-fraud-analysis.php
// Called by cron: 0 2 * * * php /path/to/daily-fraud-analysis.php

require_once 'bootstrap.php';

$pdo = getDatabaseConnection();
$analytics = new BehavioralAnalyticsEngine($pdo);
$targeting = new DynamicCameraTargetingSystem($pdo);
$logger = getLogger();

$startTime = microtime(true);
$logger->info("Starting daily fraud analysis");

try {
    // Run analysis
    $results = $analytics->analyzeAllStaff('daily');
    $logger->info("Analyzed " . count($results) . " staff members");

    // Process results
    $targetedCount = 0;
    foreach ($results as $analysis) {
        $analytics->saveAnalysisResults($analysis);

        if ($analysis['should_target_cameras']) {
            $targeting->activateTargeting($analysis);
            $targetedCount++;
        }
    }

    $elapsed = round(microtime(true) - $startTime, 2);
    $logger->info("Daily analysis complete", [
        'targeted' => $targetedCount,
        'duration' => $elapsed . 's'
    ]);
} catch (Exception $e) {
    $logger->error("Daily analysis failed: " . $e->getMessage());
}
```

---

## Configuration Reference

### Environment Variables

```env
# Core System
FRAUD_DETECTION_ENABLED=true
FRAUD_DETECTION_MODE=production        # production | development | testing

# Risk Thresholds
FRAUD_DETECTION_HIGH_RISK_THRESHOLD=0.75
FRAUD_DETECTION_MEDIUM_RISK_THRESHOLD=0.50
FRAUD_DETECTION_LOW_RISK_THRESHOLD=0.25
FRAUD_DETECTION_MIN_CONFIDENCE_FOR_TARGETING=0.75

# Camera Settings
FRAUD_DETECTION_TRACKING_DURATION=60   # minutes
FRAUD_DETECTION_MAX_CONCURRENT_TARGETS=5
FRAUD_DETECTION_FORCE_HIGH_QUALITY=true
FRAUD_DETECTION_RECORDING_QUALITY=high # high | standard

# Analysis Windows
FRAUD_DETECTION_ANALYSIS_WINDOW=daily  # daily | weekly | monthly

# Camera API
CAMERA_API_TIMEOUT=5                   # seconds
CAMERA_API_SECRET=your-api-secret-key
CAMERA_API_ENDPOINT=http://camera-server/api

# Alerts
SEND_ALERTS_TO_MANAGERS=true
ALERT_CHANNELS=email,sms,push
ALERT_EMAIL_RECIPIENTS=security@company.com,managers@company.com

# Data Retention
ALERT_RETENTION_DAYS=30
TARGETING_RETENTION_DAYS=30
INCIDENT_RETENTION_DAYS=365
```

### Config File (fraud-detection.config.php)

```php
<?php
return [
    'system' => [
        'enabled' => env('FRAUD_DETECTION_ENABLED', true),
        'mode' => env('FRAUD_DETECTION_MODE', 'production'),
    ],
    'thresholds' => [
        'high_risk' => env('FRAUD_DETECTION_HIGH_RISK_THRESHOLD', 0.75),
        'medium_risk' => env('FRAUD_DETECTION_MEDIUM_RISK_THRESHOLD', 0.50),
        'low_risk' => env('FRAUD_DETECTION_LOW_RISK_THRESHOLD', 0.25),
        'min_confidence' => env('FRAUD_DETECTION_MIN_CONFIDENCE_FOR_TARGETING', 0.75),
    ],
    'cameras' => [
        'total' => 102,
        'tracking_duration_minutes' => env('FRAUD_DETECTION_TRACKING_DURATION', 60),
        'max_concurrent_targets' => env('FRAUD_DETECTION_MAX_CONCURRENT_TARGETS', 5),
        'high_quality_recording' => env('FRAUD_DETECTION_FORCE_HIGH_QUALITY', true),
    ],
    'alerts' => [
        'enabled' => env('SEND_ALERTS_TO_MANAGERS', true),
        'channels' => explode(',', env('ALERT_CHANNELS', 'email,sms')),
        'recipients' => explode(',', env('ALERT_EMAIL_RECIPIENTS', '')),
    ],
];
```

---

## Error Handling & Logging

### Log Locations

```
/var/log/fraud-detection/
├── behavioral-analytics.log     # Analysis engine logs
├── camera-targeting.log         # Camera control logs
├── dashboard.log                # Dashboard access logs
└── api.log                       # API request logs
```

### Error Codes

```
200  OK              - Request successful
400  Bad Request     - Invalid parameters
401  Unauthorized    - Authentication required
403  Forbidden       - Permission denied
404  Not Found       - Resource not found
500  Server Error    - Internal system error
503  Unavailable     - System temporarily unavailable
```

### Exception Handling

```php
try {
    $analysis = $engine->analyzeStaffMember($staffId);
} catch (Exception $e) {
    // Log the error
    $logger->error("Analysis failed: " . $e->getMessage());

    // Graceful failure
    return [
        'success' => false,
        'error' => 'Analysis unavailable',
        'timestamp' => now(),
    ];
}
```

---

## Testing & Validation

### Unit Test Examples

```php
<?php
// Test risk scoring algorithm
function testRiskScoring() {
    $engine = new BehavioralAnalyticsEngine($pdo);

    // Test case: High-risk individual
    $analysis = $engine->analyzeStaffMember(45, 'daily');

    assert($analysis['risk_score'] > 0.75, 'Expected high risk');
    assert($analysis['risk_level'] === 'CRITICAL', 'Expected CRITICAL level');
    assert($analysis['should_target_cameras'] === true, 'Expected camera targeting');
}

// Test camera targeting
function testCameraTargeting() {
    $targeting = new DynamicCameraTargetingSystem($pdo);

    $analysis = [
        'staff_id' => 45,
        'risk_score' => 0.85,
        'should_target_cameras' => true,
        'risk_factors' => [['type' => 'void_transactions']],
    ];

    $result = $targeting->activateTargeting($analysis);
    assert($result === true, 'Expected targeting activation');

    $active = $targeting->getActiveTargets();
    assert(isset($active[45]), 'Expected staff in active targets');
}
```

### Integration Test Examples

```php
<?php
// Full workflow test
function testFullWorkflow() {
    $analytics = new BehavioralAnalyticsEngine($pdo);
    $targeting = new DynamicCameraTargetingSystem($pdo);
    $dashboard = new RealTimeAlertDashboard($pdo);

    // 1. Analyze
    $analysis = $analytics->analyzeStaffMember(45, 'daily');

    // 2. Save
    $analytics->saveAnalysisResults($analysis);

    // 3. Target
    if ($analysis['should_target_cameras']) {
        $targeting->activateTargeting($analysis);
    }

    // 4. Dashboard
    $data = $dashboard->getDashboardData();
    assert(count($data['critical_alerts']) > 0, 'Expected alerts');
    assert(count($data['targeted_individuals']) > 0, 'Expected targets');
}
```

---

## Performance Optimization

### Query Optimization Tips

```php
// GOOD: Use indexes and limit results
SELECT * FROM behavioral_analysis_results
WHERE staff_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
ORDER BY created_at DESC
LIMIT 100;

// BAD: Full table scan
SELECT * FROM behavioral_analysis_results
WHERE risk_score > 0.5;

// GOOD: Aggregate at database level
SELECT staff_id, AVG(risk_score) as avg_risk
FROM behavioral_analysis_results
WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
GROUP BY staff_id;
```

### Caching Strategies

```php
// Cache analysis results
$cacheKey = "staff_analysis_{$staffId}_{$date}";

if ($cache->has($cacheKey)) {
    return $cache->get($cacheKey);
}

$analysis = $engine->analyzeStaffMember($staffId);
$cache->set($cacheKey, $analysis, 3600); // 1 hour TTL

return $analysis;
```

### Batch Processing

```php
// Process multiple staff in batches
$staffIds = [45, 46, 47, 48, 49];
$batchSize = 10;

for ($i = 0; $i < count($staffIds); $i += $batchSize) {
    $batch = array_slice($staffIds, $i, $batchSize);

    foreach ($batch as $staffId) {
        // Process...
    }

    // Allow system to cool between batches
    sleep(1);
}
```

---

## Monitoring & Alerting

### Key Metrics to Monitor

```
1. Analysis Performance
   - Average analysis duration (should be <1s per staff)
   - Number of staff analyzed per run
   - Success rate (% without errors)

2. Camera System
   - Active targeting count
   - PTZ command success rate
   - Recording quality distribution

3. System Health
   - Database query performance
   - Memory usage
   - API response times
   - Alert delivery rate

4. Business Metrics
   - Fraud detection rate
   - False positive rate
   - Investigation success rate
   - Average investigation duration
```

### Alert Triggers

```
CRITICAL:
- System availability < 99%
- Unprocessed alerts > 100
- Database connectivity lost
- Camera API failure

WARNING:
- Analysis duration > 5 seconds
- Alert delivery failure > 5%
- Memory usage > 80%
- Disk space < 10%
```

---

**Complete Technical Reference Delivered**

All systems are production-ready and fully documented. For questions or support, refer to the IMPLEMENTATION_GUIDE.md or contact the development team.

*Last Updated: November 14, 2025*
