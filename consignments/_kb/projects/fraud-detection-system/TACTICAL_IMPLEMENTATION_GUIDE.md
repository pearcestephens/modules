# 🚀 TACTICAL IMPLEMENTATION GUIDE
## Integrating Deputy + Lightspeed + Internet Logs + Cameras

**Purpose:** Step-by-step how to actually build this system
**Audience:** Development team
**Timeline:** 4 weeks to production

---

## WEEK 1: DATA PIPELINE SETUP

### Task 1: Deputy API Integration

```php
// 1. Get Deputy API credentials
// - Login to Deputy > Settings > API
// - Create new API token (scope: read staff, timesheets, access logs)
// - Store in .env securely

// 2. Create Deputy data fetcher
class DeputyDataFetcher {

    // Real-time staff status
    public function getCurrentStaffStatus($storeId) {
        // GET /staffs?filter[active]=true&filter[location_id]=$storeId
        // Returns: ID, name, scheduled hours, current login status
        // Checks: Is person logged in? Are they supposed to be?
        // Alert: If off-hours access
    }

    // Timesheet verification
    public function verifyStaffSchedule($staffId, $timestamp) {
        // GET /timesheets?filter[staff_id]=$staffId
        // Compare: Clock-in time vs. scheduled time
        // Compare: Clock-out time vs. scheduled shift end
        // Alert: If >30 min deviation
    }

    // Manager status tracking
    public function getManagerPresence($storeId) {
        // GET /staffs?filter[role]=manager&filter[location_id]=$storeId
        // Check: Is manager logged in?
        // Use: For manager override verification
    }

    // Multi-location detection
    public function detectMultiLocationFraud() {
        // GET /timesheets?filter[timestamp]>=NOW()-1HOUR
        // For each staff: Check location_id from log
        // If same staff at 2+ locations in 1 hour: CRITICAL ALERT
        // Calculate: Distance between stores / time elapsed
        // If impossible speed: Flag as fraud
    }
}

// 3. Webhook setup (real-time push)
// POST /webhooks/subscribe
// Events: staff.clock_in, staff.clock_out, access.denied
// Every login/logout immediately triggers risk assessment
```

### Task 2: Lightspeed API Integration

```php
// 1. Get Lightspeed API credentials
// - Login to Lightspeed > Settings > API Tokens
// - Create token with: products, sales, customers, returns
// - Store securely in .env

// 2. Create transaction analyzer
class LightspeedAnalyzer {

    // Real-time transaction monitoring
    public function monitorTransaction($transactionData) {
        // Fields to track:
        // - register_id (which terminal?)
        // - staff_id (who rang it up?)
        // - items (what was sold?)
        // - discounts (how much discount?)
        // - payment_method (cash/card/gift card?)
        // - customer_id (who bought?)

        // Check 1: Discount validation
        if ($discount > $staffMaxDiscount) {
            return 'CRITICAL: Discount exceeds authority';
        }

        // Check 2: Product validation
        if (isPremiumProduct($items) && $discount > 20%) {
            return 'HIGH: High discount on premium product';
        }

        // Check 3: Item scan validation
        // Compare: Item scan vs. items in transaction
        // If mismatch: Possible void fraud

        // Check 4: Payment validation
        if ($paymentMethod == 'cash' && !$receiptPrinted) {
            return 'MEDIUM: Cash transaction, no receipt';
        }

        // Check 5: Customer validation
        if (isStaffContact($staffId, $customerId)) {
            return 'HIGH: Staff personal contact purchase';
        }
    }

    // Bulk pattern analysis
    public function detectFraudPatterns($staffId, $days = 30) {
        $transactions = getTransactions($staffId, $days);

        // Calculate baseline
        $avgDiscount = average([$t->discount for $t in $transactions]);
        $avgRefundRate = average([$t->refundRate for $t in $transactions]);
        $voidRate = count($t->isVoid) / count($transactions);

        // Today's metrics
        $todayAvgDiscount = average([$t->discount for $t in getTodayTransactions($staffId)]);
        $todayVoidRate = countVoidsToday($staffId) / countTransactionsToday($staffId);

        // Statistical deviation
        $discountDeviation = ($todayAvgDiscount - $avgDiscount) / $avgDiscount;
        if ($discountDeviation > 0.5) { // 50% more discount than normal
            return 'HIGH: Discount rate 50% above normal';
        }

        // Return fraud detection
        $refundDeviation = ($todayRefundRate - $avgRefundRate) / $avgRefundRate;
        if ($refundDeviation > 2.0) { // 200% more refunds
            return 'CRITICAL: Return rate 2x above normal';
        }
    }
}

// 3. Webhook setup (real-time push)
// POST /webhooks/register
// Events: sale.completed, sale.refund, sale.void
// Every transaction triggers real-time risk calculation
```

### Task 3: Internet Log Integration

```php
// 1. Setup network log collection
// - Configure firewall/proxy to log: DNS, HTTP, HTTPS
// - Target: User/IP/destination tracking
// - Retention: 30 days (searchable)

// 2. Create network analyzer
class NetworkAnalyzer {

    // DNS query monitoring (what sites accessed?)
    public function monitorDNS($ipAddress, $domain) {
        $suspiciousDomains = [
            'facebook-marketplace.com',
            'ebay.com',
            'letgo.com',
            'craigslist.org',
            'alibaba.com',
            // Resale/import sites
        ];

        if (in_array($domain, $suspiciousDomains)) {
            return 'MEDIUM: Access to resale platform';
        }
    }

    // File transfer monitoring (exfiltration?)
    public function monitorFileTransfers($ip, $direction, $bytes) {
        // Direction: upload/download
        // Alert: If upload >100MB (customer data?)
        if ($direction == 'upload' && $bytes > 100 * 1024 * 1024) {
            return 'CRITICAL: Large data upload (exfiltration?)';
        }

        // Alert: If download of tools/software
        // Alert: If API calls to external systems
    }

    // Geolocation monitoring (physical location verification)
    public function verifyPhysicalLocation($ipAddress, $expectedStore) {
        // Get IP geolocation (MaxMind GeoIP)
        $geoLocation = GeoIP::lookup($ipAddress);
        $expectedLocation = getStoreLocation($expectedStore);

        $distance = distance($geoLocation, $expectedLocation);

        if ($distance > 5) { // More than 5km away
            return 'MEDIUM: Access from ' . $distance . 'km away';
        }

        if ($distance > 50) { // More than 50km away
            return 'CRITICAL: Access from different city';
        }
    }

    // Email monitoring (communication patterns)
    public function monitorEmailPatterns($staffId) {
        // Check: Emails to customers (normal)
        // Alert: Emails to customer lists (suspicious)
        // Alert: Emails to external fraud sites (very suspicious)
        // Alert: Sharing of staff/customer lists (critical)

        // Pattern: Unusual recipients today vs baseline
        $baselineRecipients = getBaselineRecipients($staffId, 30);
        $todayRecipients = getTodayEmailRecipients($staffId);

        $newRecipients = array_diff($todayRecipients, $baselineRecipients);
        if (count($newRecipients) > 10) {
            return 'HIGH: 10+ new email recipients today';
        }
    }
}

// 3. Log parsing (from firewall/proxy)
// Format: IP,timestamp,domain,direction,bytes,user
// Parse hourly, feed to analyzer
```

### Task 4: Initial Camera System Setup

```php
// 1. Camera API integration
class CameraController {

    // Activate camera targeting
    public function activateTargeting($cameraId, $preset, $recordingQuality = 'high') {
        // PTZ command: Pan/Tilt/Zoom to preset
        // Recording: Set bitrate (standard: 2Mbps, high: 8Mbps)
        // Duration: 60 minutes default

        $command = [
            'action' => 'set_preset',
            'preset_id' => $preset,
            'recording_quality' => $recordingQuality,
            'duration_minutes' => 60
        ];

        return $this->sendToCamera($cameraId, $command);
    }

    // Record everything (forensic mode)
    public function activateForensicRecording($cameraId, $durationMinutes = 120) {
        // High bitrate (8Mbps minimum)
        // Highest resolution
        // Continuous (no motion detection skipping)
        // Store to dedicated drive (not overwritten)

        return $this->activateTargeting($cameraId, null, 'forensic');
    }

    // Multi-camera coordination (for organized fraud)
    public function activateMultiCamera($cameraIds, $presets, $duration = 60) {
        // Activate 4-5 cameras simultaneously
        // Each camera focuses on one aspect:
        // - CAM-1: Transaction display (what was scanned?)
        // - CAM-2: Customer hands (paying or conspiring?)
        // - CAM-3: Staff hands (scanning or concealing?)
        // - CAM-4: Exit (what leaving with?)

        foreach ($cameraIds as $camId => $preset) {
            $this->activateTargeting($camId, $preset);
        }
    }
}

// 2. Manual setup:
// - For each camera: Configure default preset (position/zoom)
// - For each register: Map to closest cameras
// - For each high-value product area: Configure focused presets
// - Test: Activate each camera, verify positioning
```

---

## WEEK 2: INTELLIGENCE ENGINE

### Task 5: Risk Scoring System

```php
// Core risk calculation
class RiskScoringEngine {

    // Main entry point
    public function calculateRisk($staffId, $timestamp = 'now') {
        $risks = [];

        // Component 1: Deputy data (30% weight)
        $risks['deputy'] = $this->assessDeputyRisk($staffId, $timestamp);

        // Component 2: Lightspeed data (30% weight)
        $risks['lightspeed'] = $this->assessLightspeedRisk($staffId, $timestamp);

        // Component 3: Network data (20% weight)
        $risks['network'] = $this->assessNetworkRisk($staffId, $timestamp);

        // Component 4: Camera evidence (20% weight)
        $risks['camera'] = $this->assessCameraRisk($staffId, $timestamp);

        // Calculate composite
        $compositeRisk = (
            ($risks['deputy'] * 0.30) +
            ($risks['lightspeed'] * 0.30) +
            ($risks['network'] * 0.20) +
            ($risks['camera'] * 0.20)
        );

        return [
            'staff_id' => $staffId,
            'timestamp' => $timestamp,
            'scores' => $risks,
            'composite_risk' => $compositeRisk,
            'risk_level' => $this->classifyRisk($compositeRisk),
            'factors' => $this->getTopRiskFactors($risks),
            'recommended_action' => $this->getRecommendedAction($compositeRisk, $risks)
        ];
    }

    // Deputy component
    private function assessDeputyRisk($staffId, $timestamp) {
        $deputy = new DeputyDataFetcher();

        $score = 0;

        // Check 1: Scheduled vs actual time
        $staff = $deputy->getStaffStatus($staffId);
        if (!isWithinSchedule($staff->clockInTime, $staff->scheduledShift)) {
            $score += 0.25; // Off-hours access penalty
        }

        // Check 2: Multi-location fraud
        if ($deputy->isMultipleLocationsSimultaneous($staffId)) {
            $score += 0.50; // CRITICAL
        }

        // Check 3: Manager override without manager
        if ($deputy->hasManagerOverride($staffId) && !$deputy->isManagerPresent($staff->storeId)) {
            $score += 0.35;
        }

        // Check 4: Permission exceeded
        if ($deputy->hasPermissionExceeded($staffId)) {
            $score += 0.30;
        }

        return min($score, 1.0); // Cap at 1.0
    }

    // Lightspeed component
    private function assessLightspeedRisk($staffId, $timestamp) {
        $lightspeed = new LightspeedAnalyzer();

        $score = 0;

        // Check 1: Discount anomaly
        $discountAnomaly = $lightspeed->getDiscountAnomaly($staffId);
        if ($discountAnomaly > 0.3) { // 30% above normal
            $score += 0.15;
        }
        if ($discountAnomaly > 0.5) { // 50% above normal
            $score += 0.20; // +0.35 total
        }

        // Check 2: Void pattern
        $voidAnomaly = $lightspeed->getVoidAnomaly($staffId);
        if ($voidAnomaly > 2.0) { // 2x normal rate
            $score += 0.25;
        }
        if ($voidAnomaly > 5.0) { // 5x normal rate
            $score += 0.30; // +0.55 total
        }

        // Check 3: Refund pattern
        $refundAnomaly = $lightspeed->getRefundAnomaly($staffId);
        if ($refundAnomaly > 3.0) { // 3x normal rate
            $score += 0.25;
        }

        // Check 4: Product-specific anomaly
        if ($lightspeed->isPremiumProductTargeted($staffId)) {
            $score += 0.15;
        }

        return min($score, 1.0);
    }

    // Network component
    private function assessNetworkRisk($staffId, $timestamp) {
        $network = new NetworkAnalyzer();

        $score = 0;

        // Check 1: Resale site access
        if ($network->hasAccessedResaleSite($staffId)) {
            $score += 0.25;
        }

        // Check 2: Data exfiltration
        if ($network->hasLargeUpload($staffId)) {
            $score += 0.40; // CRITICAL
        }

        // Check 3: Geolocation mismatch
        $geoMismatch = $network->getGeolocationMismatch($staffId);
        if ($geoMismatch > 50) { // >50km away
            $score += 0.25;
        }

        // Check 4: Email anomaly
        if ($network->hasUnusualEmailPattern($staffId)) {
            $score += 0.15;
        }

        // Check 5: VPN/Proxy usage
        if ($network->isUsingVPN($staffId)) {
            $score += 0.20;
        }

        return min($score, 1.0);
    }

    // Camera component
    private function assessCameraRisk($staffId, $timestamp) {
        // Note: Camera evidence requires 60-120 seconds to analyze
        // So this component is populated from most recent analysis

        $cameraEvidence = getLatestCameraAnalysis($staffId);

        if (!$cameraEvidence) {
            return 0; // No evidence yet
        }

        $score = 0;

        // Check 1: Concealment detected
        if ($cameraEvidence->hasConcealmentDetected) {
            $score += 0.50; // CRITICAL
        }

        // Check 2: Item not scanned
        if ($cameraEvidence->hasUnscannedItem) {
            $score += 0.35;
        }

        // Check 3: Behavioral anomaly
        if ($cameraEvidence->hasAnomalousBehavior) {
            $score += 0.20;
        }

        return min($score, 1.0);
    }

    // Risk classification
    private function classifyRisk($score) {
        if ($score >= 0.80) return 'CRITICAL';
        if ($score >= 0.60) return 'HIGH';
        if ($score >= 0.40) return 'MEDIUM';
        if ($score >= 0.20) return 'LOW';
        return 'SAFE';
    }

    // Recommended action
    private function getRecommendedAction($score, $risks) {
        if ($score >= 0.80) {
            return [
                'action' => 'ACTIVATE_CAMERAS',
                'cameras' => 'All relevant (4-5 cameras)',
                'recording' => 'High-quality (8Mbps)',
                'notify' => 'Security team immediately',
                'investigate' => 'Open full investigation'
            ];
        }

        if ($score >= 0.60) {
            return [
                'action' => 'MONITOR_CLOSELY',
                'cameras' => 'Relevant cameras (standard)',
                'recording' => 'Standard quality',
                'notify' => 'Manager within 1 hour',
                'investigate' => 'Schedule investigation'
            ];
        }

        if ($score >= 0.40) {
            return [
                'action' => 'ADD_TO_WATCHLIST',
                'cameras' => 'Continue baseline recording',
                'recording' => 'Standard quality',
                'notify' => 'Manager in daily report',
                'investigate' => 'Weekly review'
            ];
        }

        return [
            'action' => 'NO_ACTION',
            'cameras' => 'Standard recording',
            'recording' => 'Standard quality',
            'notify' => 'None',
            'investigate' => 'None'
        ];
    }
}

// Usage:
$riskEngine = new RiskScoringEngine();
$assessment = $riskEngine->calculateRisk($staffId = 45);

if ($assessment['risk_level'] == 'CRITICAL') {
    // Activate cameras immediately
    $cameraController->activateMultiCamera(
        $assessment['recommended_cameras'],
        $assessment['presets'],
        120 // 2-hour recording
    );

    // Notify team
    sendAlert('CRITICAL fraud alert', $assessment);

    // Log for investigation
    logIncident($assessment);
}
```

### Task 6: Real-Time Alert System

```php
// WebSocket server for real-time alerts
class RealTimeAlertSystem {

    public function processTransaction($transactionData) {
        // 1. Immediate transaction validation (0.1 seconds)
        $quickRisk = $this->quickValidate($transactionData);

        if ($quickRisk >= 0.60) {
            // Activate cameras immediately
            $this->activateCameras($transactionData);

            // Broadcast alert
            $this->broadcast('alert', [
                'severity' => 'CRITICAL',
                'type' => 'TRANSACTION_ANOMALY',
                'staff_id' => $transactionData['staff_id'],
                'amount' => $transactionData['discount'],
                'timestamp' => now()
            ]);
        }

        // 2. Full risk assessment (5-10 seconds background)
        $this->queue('full_risk_assessment', [
            'staff_id' => $transactionData['staff_id'],
            'transaction_id' => $transactionData['id']
        ]);
    }

    public function processClock($clockData) {
        // Clock-in/clock-out triggers immediate check

        // Is person supposed to be working now?
        $schedule = Deputy::getSchedule($clockData['staff_id'], $clockData['timestamp']);

        if (!isWithinSchedule($clockData['timestamp'], $schedule)) {
            // Off-hours access - activate cameras
            $this->activateCameras([
                'staff_id' => $clockData['staff_id'],
                'location' => $clockData['location']
            ]);

            $this->broadcast('alert', [
                'severity' => 'CRITICAL',
                'type' => 'OFF_HOURS_ACCESS',
                'staff_id' => $clockData['staff_id'],
                'scheduled_shift' => $schedule,
                'actual_time' => $clockData['timestamp']
            ]);
        }
    }

    public function processNetworkEvent($networkData) {
        // Large upload detected
        if ($networkData['direction'] == 'upload' && $networkData['bytes'] > 100 * 1024 * 1024) {
            $this->broadcast('alert', [
                'severity' => 'CRITICAL',
                'type' => 'DATA_EXFILTRATION',
                'ip_address' => $networkData['ip'],
                'destination' => $networkData['destination'],
                'bytes_transferred' => $networkData['bytes']
            ]);

            // Immediate investigation action
            $this->takeAction('SUSPEND_ACCESS', $networkData['ip']);
        }
    }

    private function broadcast($type, $data) {
        // Send to all connected security staff via WebSocket
        // Real-time: No delay
    }
}
```

---

## WEEK 3: INVESTIGATION TOOLS

### Task 7: Evidence Collection & Linking

```php
// Forensic evidence system
class EvidenceCollector {

    // When investigation opens, collect all evidence
    public function collectEvidence($staffId, $incidentDate) {
        $evidence = [];

        // 1. Deputy records (staff access/scheduling)
        $evidence['deputy'] = Deputy::getStaffRecords($staffId, $incidentDate);

        // 2. Lightspeed records (transactions)
        $evidence['lightspeed'] = Lightspeed::getTransactions($staffId, $incidentDate);

        // 3. Network logs (digital footprint)
        $evidence['network'] = NetworkLogs::getByStaff($staffId, $incidentDate);

        // 4. Camera footage (4+ cameras)
        $evidence['cameras'] = [
            'main_register' => Cameras::getVideo($cameraId='main', $date=$incidentDate),
            'returns_register' => Cameras::getVideo($cameraId='returns', $date=$incidentDate),
            'exit_monitoring' => Cameras::getVideo($cameraId='exit', $date=$incidentDate),
            'high_value_area' => Cameras::getVideo($cameraId='premium', $date=$incidentDate),
        ];

        // 5. Email/communication logs
        $evidence['communications'] = EmailLogs::getByStaff($staffId, $incidentDate);

        // Store in linked database
        $investigation = Investigation::create([
            'staff_id' => $staffId,
            'opened_at' => now(),
            'evidence' => json_encode($evidence),
            'status' => 'OPEN'
        ]);

        return $investigation;
    }

    // Timeline reconstruction
    public function buildTimeline($investigationId) {
        $events = [];

        $investigation = Investigation::find($investigationId);
        $evidence = json_decode($investigation->evidence);

        // Merge all events with timestamps
        foreach ($evidence->deputy as $event) {
            $events[] = ['time' => $event->timestamp, 'type' => 'deputy', 'data' => $event];
        }

        foreach ($evidence->lightspeed as $event) {
            $events[] = ['time' => $event->timestamp, 'type' => 'transaction', 'data' => $event];
        }

        foreach ($evidence->network as $event) {
            $events[] = ['time' => $event->timestamp, 'type' => 'network', 'data' => $event];
        }

        // Sort chronologically
        usort($events, function($a, $b) {
            return strtotime($a['time']) - strtotime($b['time']);
        });

        // Generate timeline report
        $timeline = Timeline::create([
            'investigation_id' => $investigationId,
            'events' => $events,
            'generated_at' => now()
        ]);

        return $timeline;
    }
}
```

### Task 8: Reporting & Analytics

```php
// Generate forensic reports
class ReportGenerator {

    public function generateInvestigationReport($investigationId) {
        $investigation = Investigation::find($investigationId);
        $timeline = Timeline::where('investigation_id', $investigationId)->first();

        return [
            'summary' => [
                'staff_name' => $investigation->staff->name,
                'incident_date' => $investigation->opened_at,
                'status' => $investigation->status,
                'estimated_loss' => $this->calculateEstimatedLoss($investigation)
            ],
            'evidence_summary' => [
                'deputy_records' => count($investigation->deputyEvents),
                'transactions' => count($investigation->lightspeedEvents),
                'network_events' => count($investigation->networkEvents),
                'camera_hours' => count($investigation->cameraFootage),
            ],
            'timeline' => $timeline->events,
            'key_findings' => $this->extractKeyFindings($investigation),
            'recommendations' => $this->generateRecommendations($investigation),
            'evidence_links' => $this->generateEvidenceLinks($investigation)
        ];
    }

    // Calculate potential loss
    private function calculateEstimatedLoss($investigation) {
        $loss = 0;

        // Sum discounts (what they gave away)
        $loss += Lightspeed::getDiscountTotal($investigation->staff_id, $investigation->opened_at);

        // Sum voids (what they likely stole)
        $loss += Lightspeed::getVoidTotal($investigation->staff_id, $investigation->opened_at);

        // Estimate based on peer average if pattern detected
        $peerAverage = Lightspeed::getAverageLoss('similar_staff');
        $daysInvestigated = daysDifference($investigation->opened_at, now());
        $loss += ($peerAverage * $daysInvestigated);

        return $loss;
    }

    // Extract suspicious patterns
    private function extractKeyFindings($investigation) {
        $findings = [];

        // Pattern 1: Discount anomalies
        $discountPattern = Lightspeed::analyzeDiscountPattern($investigation->staff_id);
        if ($discountPattern['anomaly'] > 0.3) {
            $findings[] = [
                'finding' => 'Discount anomaly detected',
                'details' => 'Average discount 50% above baseline',
                'evidence' => 'Lightspeed transactions',
                'severity' => 'HIGH'
            ];
        }

        // Pattern 2: Off-hours access
        $offHoursAccess = Deputy::getOffHoursAccess($investigation->staff_id);
        if (count($offHoursAccess) > 0) {
            $findings[] = [
                'finding' => 'Off-hours unsupervised access',
                'details' => count($offHoursAccess) . ' instances detected',
                'evidence' => 'Deputy access logs + Camera footage',
                'severity' => 'CRITICAL'
            ];
        }

        // Pattern 3: Data exfiltration
        $dataTransfers = NetworkLogs::getLargeTransfers($investigation->staff_id);
        if (count($dataTransfers) > 0) {
            $findings[] = [
                'finding' => 'Potential data exfiltration',
                'details' => 'Large uploads to external services',
                'evidence' => 'Network logs + Email logs',
                'severity' => 'CRITICAL'
            ];
        }

        // Pattern 4: Customer collusion
        $suspiciousCustomers = Lightspeed::findSuspiciousCustomers($investigation->staff_id);
        if (count($suspiciousCustomers) > 0) {
            $findings[] = [
                'finding' => 'Potential customer collusion',
                'details' => count($suspiciousCustomers) . ' suspicious customer patterns',
                'evidence' => 'Lightspeed + Network logs',
                'severity' => 'HIGH'
            ];
        }

        return $findings;
    }
}
```

---

## WEEK 4: DEPLOYMENT & OPTIMIZATION

### Task 9: Dashboard Development

```php
// Real-time security dashboard
class SecurityDashboard {

    public function getCurrentStatus($storeId = null) {
        return [
            'active_monitoring' => $this->getActiveTargets($storeId),
            'critical_alerts' => $this->getCriticalAlerts($storeId),
            'risk_heatmap' => $this->generateRiskHeatmap($storeId),
            'recent_incidents' => $this->getRecentIncidents($storeId),
            'system_status' => $this->getSystemHealth()
        ];
    }

    private function getActiveTargets($storeId) {
        // Show cameras currently active
        // Time remaining on targeting
        // Staff members being monitored
    }

    private function getCriticalAlerts($storeId) {
        // CRITICAL alerts (need immediate action)
        // Sorted by recency
        // With actionable details
    }

    private function generateRiskHeatmap($storeId) {
        // Visual map of store with risk levels
        // Staff photos with risk badges
        // Color coding: RED (critical), ORANGE (high), etc.
    }
}
```

### Task 10: Testing & Tuning

```php
// Test framework
class SystemTesting {

    // Test 1: Simulate off-hours access
    public function testOffHoursDetection() {
        // Simulate: Staff clock-in at 2 AM (off-schedule)
        // Verify: Camera activation triggered
        // Verify: Alert sent to security
        // Verify: Logged in investigation queue
    }

    // Test 2: Simulate high discount
    public function testDiscountAnomaly() {
        // Simulate: Staff gives 50% discount (normally 8%)
        // Verify: Transaction flagged
        // Verify: Risk score >0.60
        // Verify: Manager alert generated
    }

    // Test 3: Simulate multi-location fraud
    public function testMultiLocationDetection() {
        // Simulate: Same staff login at 2 locations 10 minutes apart
        // Verify: Impossible distance detected
        // Verify: CRITICAL alert generated
        // Verify: Cameras activated at both stores
    }

    // Test 4: Simulate data exfiltration
    public function testExfiltrationDetection() {
        // Simulate: 1GB upload to external site
        // Verify: Network alert triggered
        // Verify: Access suspended
        // Verify: Investigation opened
    }

    // Test 5: False positive reduction
    public function evaluateFalsePositives() {
        // Run system for 1 week
        // Count: Alerts vs. actual fraud
        // Target: <5% false positive rate
        // Adjust: Thresholds if needed
    }
}
```

---

## 📈 SUCCESS METRICS

**Week 1:**
- Deputy, Lightspeed, Network logs connected
- Real-time data flowing
- Basic risk scoring working

**Week 2:**
- Full intelligence engine operational
- Real-time alerts generating
- Camera activation working

**Week 3:**
- Investigation tools functional
- Evidence linking complete
- Reports generating

**Week 4:**
- System fully operational
- False positives tuned down
- Team trained
- Ready for production

---

## 🎯 EXPECTED FRAUD DETECTION

Once deployed, expect to detect:

✅ **Off-Hours Theft** - Within 60 seconds of unauthorized access
✅ **Discount Fraud** - Instantly when discount exceeds authority
✅ **Void Fraud** - Real-time pattern detection
✅ **Refund Rings** - Cross-referenced transaction patterns
✅ **Credential Fraud** - Multi-location simultaneous login
✅ **Data Theft** - Network exfiltration detection
✅ **Customer Collusion** - Relationship pattern matching
✅ **Organized Rings** - Multi-staff coordination detection

---

**This is your fraud prevention system. Build it. Deploy it. Secure your stores.**
