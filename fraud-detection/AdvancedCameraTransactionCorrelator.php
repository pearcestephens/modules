<?php

/**
 * Advanced Camera-Transaction Correlator
 *
 * DEEP ANALYSIS: Cross-references security camera data with business transactions
 * to detect sophisticated fraud patterns that single-source analysis would miss.
 *
 * CORRELATION TYPES:
 * 1. Till Activity vs Camera Visibility - Is staff at register during transaction?
 * 2. Login/Logout vs Physical Presence - Are they really there?
 * 3. Cash Transactions vs Camera Confirmation - Did camera see cash exchange?
 * 4. Transaction Anomalies - Ghost transactions or ghost presence?
 * 5. Multi-Person Detection - Is someone else operating their till?
 * 6. Zone Mismatch - Transaction at Outlet A, camera shows staff at Outlet B?
 * 7. Time-Gap Analysis - Transaction but no camera activity for hours?
 * 8. Rapid Location Jumps - Impossible movement between outlets?
 *
 * @package FraudDetection
 * @version 2.0.0
 */

namespace FraudDetection;

use PDO;
use Exception;
use DateTime;

class AdvancedCameraTransactionCorrelator
{
    private PDO $pdo;
    private array $config;
    private array $correlationResults = [];

    // Correlation thresholds
    private const TRANSACTION_CAMERA_WINDOW_SECONDS = 30; // ±30 seconds
    private const LOGIN_PRESENCE_WINDOW_MINUTES = 5;      // ±5 minutes
    private const CASH_TRANSACTION_CAMERA_REQUIRED = true; // Must see person for cash
    private const MIN_PERSON_CONFIDENCE = 0.75;            // Camera detection confidence
    private const MAX_OUTLET_TRAVEL_TIME_MINUTES = 30;    // Between outlets

    public function __construct(PDO $pdo, array $config = [])
    {
        $this->pdo = $pdo;
        $this->config = array_merge([
            'enable_deep_analysis' => true,
            'enable_video_frame_analysis' => false, // Future: AI video analysis
            'alert_on_mismatch' => true,
            'store_detailed_logs' => true,
        ], $config);
    }

    /**
     * Run comprehensive camera-transaction correlation for staff member
     *
     * @param int $staffId
     * @param int $days Number of days to analyze
     * @return array Correlation results with fraud indicators
     */
    public function analyzeStaffCorrelation(int $staffId, int $days = 7): array
    {
        $this->correlationResults = [
            'staff_id' => $staffId,
            'analysis_period_days' => $days,
            'analysis_timestamp' => date('Y-m-d H:i:s'),
            'correlations' => [],
            'mismatches' => [],
            'fraud_indicators' => [],
            'summary' => [
                'total_transactions' => 0,
                'camera_confirmed' => 0,
                'camera_missing' => 0,
                'suspicious_patterns' => 0,
                'ghost_transactions' => 0,
                'ghost_presence' => 0,
            ]
        ];

        try {
            // 1. Till activity vs camera visibility
            $this->analyzeTillCameraCorrelation($staffId, $days);

            // 2. Login/logout vs physical presence
            $this->analyzeLoginPresenceCorrelation($staffId, $days);

            // 3. Cash transactions vs camera confirmation
            $this->analyzeCashTransactionCameraConfirmation($staffId, $days);

            // 4. Transaction anomalies (ghost transactions)
            $this->detectGhostTransactions($staffId, $days);

            // 5. Ghost presence (camera shows person but no transactions)
            $this->detectGhostPresence($staffId, $days);

            // 6. Multi-person detection at till
            $this->detectMultiPersonAtTill($staffId, $days);

            // 7. Zone/location mismatches
            $this->detectLocationMismatches($staffId, $days);

            // 8. Impossible movement patterns
            $this->detectImpossibleMovement($staffId, $days);

            // Calculate correlation score
            $this->calculateCorrelationScore();

            // Store results
            if ($this->config['store_detailed_logs']) {
                $this->storeCorrelationResults();
            }

            return $this->correlationResults;

        } catch (Exception $e) {
            error_log("Camera-transaction correlation failed for staff {$staffId}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * 1. TILL ACTIVITY vs CAMERA VISIBILITY
     *
     * For every transaction:
     * - Check if camera detected person at register ±30 seconds
     * - Verify person count matches expected (1 person = staff member)
     * - Validate confidence score
     *
     * FRAUD INDICATORS:
     * - Transaction without camera detection (ghost transaction)
     * - Multiple people at till during transaction
     * - Low confidence detection during high-value transaction
     */
    private function analyzeTillCameraCorrelation(int $staffId, int $days): void
    {
        try {
            // Get all transactions for this staff
            $stmt = $this->pdo->prepare("
                SELECT
                    vs.id as transaction_id,
                    vs.sale_date,
                    vs.total_price,
                    vs.status,
                    vs.outlet_id,
                    vs.register_id,
                    vs.payment_type,
                    vs.total_discount
                FROM vend_sales vs
                WHERE vs.user_id = :staff_id
                AND vs.sale_date >= DATE_SUB(NOW(), INTERVAL :days DAY)
                ORDER BY vs.sale_date ASC
            ");
            $stmt->execute(['staff_id' => $staffId, 'days' => $days]);
            $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $this->correlationResults['summary']['total_transactions'] = count($transactions);

            foreach ($transactions as $transaction) {
                $transactionTime = strtotime($transaction['sale_date']);
                $outletId = $transaction['outlet_id'];
                $registerId = $transaction['register_id'];

                // Find camera covering this register
                $camera = $this->getCameraForRegister($outletId, $registerId);

                if (!$camera) {
                    // No camera coverage - can't verify
                    $this->addMismatch(
                        'no_camera_coverage',
                        $transaction,
                        null,
                        'No camera covering register',
                        0.3
                    );
                    continue;
                }

                // Look for camera events ±30 seconds around transaction
                $cameraEvents = $this->getCameraEventsInWindow(
                    $camera['camera_id'],
                    $transactionTime,
                    self::TRANSACTION_CAMERA_WINDOW_SECONDS
                );

                if (empty($cameraEvents)) {
                    // GHOST TRANSACTION - Transaction without camera detection
                    $this->correlationResults['summary']['ghost_transactions']++;
                    $this->addMismatch(
                        'ghost_transaction',
                        $transaction,
                        null,
                        'Transaction occurred but camera detected no person at register',
                        0.9 // HIGH severity
                    );
                    continue;
                }

                // Validate camera event
                $bestMatch = $this->findBestCameraMatch($cameraEvents, $transactionTime);

                if ($bestMatch['confidence'] < self::MIN_PERSON_CONFIDENCE) {
                    $this->addMismatch(
                        'low_confidence_detection',
                        $transaction,
                        $bestMatch,
                        "Low camera confidence ({$bestMatch['confidence']}) during transaction",
                        0.6
                    );
                }

                // Check person count
                $personCount = $bestMatch['detection_data']['person_count'] ?? 1;
                if ($personCount > 1) {
                    $this->addMismatch(
                        'multiple_people_at_till',
                        $transaction,
                        $bestMatch,
                        "Multiple people ({$personCount}) detected at till during transaction",
                        0.8
                    );
                }

                // Check if high-value transaction
                if (abs($transaction['total_price']) > 500) {
                    if ($bestMatch['confidence'] < 0.9) {
                        $this->addMismatch(
                            'high_value_low_confidence',
                            $transaction,
                            $bestMatch,
                            "High-value transaction (\${$transaction['total_price']}) with low camera confidence",
                            0.85
                        );
                    }
                }

                // Success - camera confirmed
                $this->correlationResults['summary']['camera_confirmed']++;
                $this->correlationResults['correlations'][] = [
                    'type' => 'till_camera_match',
                    'transaction' => $transaction,
                    'camera_event' => $bestMatch,
                    'confidence' => $bestMatch['confidence'],
                    'time_diff_seconds' => abs($transactionTime - strtotime($bestMatch['event_timestamp']))
                ];
            }

        } catch (Exception $e) {
            error_log("Till-camera correlation failed: " . $e->getMessage());
        }
    }

    /**
     * 2. LOGIN/LOGOUT vs PHYSICAL PRESENCE
     *
     * Check if staff is physically present when they log in/out
     *
     * FRAUD INDICATORS:
     * - Login without physical presence (remote login from home?)
     * - Logout but camera shows continued presence
     * - Long periods logged in but no camera activity
     */
    private function analyzeLoginPresenceCorrelation(int $staffId, int $days): void
    {
        try {
            // Get all login/logout events
            $stmt = $this->pdo->prepare("
                SELECT
                    action,
                    outlet_id,
                    accessed_at,
                    ip_address,
                    user_agent
                FROM system_access_log
                WHERE staff_id = :staff_id
                AND action IN ('login', 'logout', 'clock_in', 'clock_out')
                AND accessed_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
                ORDER BY accessed_at ASC
            ");
            $stmt->execute(['staff_id' => $staffId, 'days' => $days]);
            $accessEvents = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($accessEvents as $event) {
                $eventTime = strtotime($event['accessed_at']);
                $outletId = $event['outlet_id'];

                if (!$outletId) continue; // Can't verify without outlet

                // Get cameras at this outlet
                $cameras = $this->getCamerasForOutlet($outletId);

                // Look for person detection ±5 minutes around login/logout
                $cameraDetections = [];
                foreach ($cameras as $camera) {
                    $events = $this->getCameraEventsInWindow(
                        $camera['camera_id'],
                        $eventTime,
                        self::LOGIN_PRESENCE_WINDOW_MINUTES * 60
                    );
                    $cameraDetections = array_merge($cameraDetections, $events);
                }

                if (empty($cameraDetections)) {
                    // Login/logout without physical presence detected
                    $this->addMismatch(
                        'login_without_presence',
                        $event,
                        null,
                        "{$event['action']} occurred but no camera detected presence at outlet",
                        0.75
                    );
                }

                // Check for suspicious IP (not from outlet network)
                if ($this->isSuspiciousIP($event['ip_address'], $outletId)) {
                    $this->addMismatch(
                        'suspicious_login_ip',
                        $event,
                        null,
                        "Login from IP not matching outlet network: {$event['ip_address']}",
                        0.85
                    );
                }
            }

        } catch (Exception $e) {
            error_log("Login-presence correlation failed: " . $e->getMessage());
        }
    }

    /**
     * 3. CASH TRANSACTIONS vs CAMERA CONFIRMATION
     *
     * For cash transactions, MUST see physical cash exchange on camera
     *
     * FRAUD INDICATORS:
     * - Cash transaction without camera seeing person
     * - Cash transaction but camera shows no hand movement/exchange
     * - Multiple cash transactions in rapid succession
     */
    private function analyzeCashTransactionCameraConfirmation(int $staffId, int $days): void
    {
        try {
            // Get all CASH transactions
            $stmt = $this->pdo->prepare("
                SELECT
                    vs.id,
                    vs.sale_date,
                    vs.total_price,
                    vs.outlet_id,
                    vs.register_id,
                    vs.payment_type
                FROM vend_sales vs
                WHERE vs.user_id = :staff_id
                AND vs.payment_type = 'CASH'
                AND vs.sale_date >= DATE_SUB(NOW(), INTERVAL :days DAY)
                ORDER BY vs.sale_date ASC
            ");
            $stmt->execute(['staff_id' => $staffId, 'days' => $days]);
            $cashTransactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($cashTransactions as $transaction) {
                $transactionTime = strtotime($transaction['sale_date']);

                // For CASH, camera confirmation is MANDATORY
                $camera = $this->getCameraForRegister(
                    $transaction['outlet_id'],
                    $transaction['register_id']
                );

                if (!$camera) {
                    $this->addMismatch(
                        'cash_no_camera',
                        $transaction,
                        null,
                        'CASH transaction without camera coverage - CRITICAL',
                        0.95 // CRITICAL
                    );
                    continue;
                }

                $cameraEvents = $this->getCameraEventsInWindow(
                    $camera['camera_id'],
                    $transactionTime,
                    self::TRANSACTION_CAMERA_WINDOW_SECONDS
                );

                if (empty($cameraEvents)) {
                    // CRITICAL: Cash transaction without person detection
                    $this->addMismatch(
                        'cash_ghost_transaction',
                        $transaction,
                        null,
                        'CASH transaction (\$' . number_format($transaction['total_price'], 2) . ') without camera confirmation - CRITICAL',
                        0.95 // CRITICAL
                    );
                }

                // Future: Check for hand movement in video frame
                // if ($this->config['enable_video_frame_analysis']) {
                //     $hasHandMovement = $this->detectHandMovement($cameraEvents);
                //     if (!$hasHandMovement) {
                //         // Person visible but no hand movement = suspicious
                //     }
                // }
            }

        } catch (Exception $e) {
            error_log("Cash-camera correlation failed: " . $e->getMessage());
        }
    }

    /**
     * 4. GHOST TRANSACTIONS
     *
     * Transactions that occur when camera shows NO activity
     */
    private function detectGhostTransactions(int $staffId, int $days): void
    {
        // This is partially covered in analyzeTillCameraCorrelation
        // Additional checks for patterns:

        try {
            // Find transactions during periods of NO camera activity
            $stmt = $this->pdo->prepare("
                SELECT
                    vs.id,
                    vs.sale_date,
                    vs.total_price,
                    vs.outlet_id,
                    vs.status
                FROM vend_sales vs
                WHERE vs.user_id = :staff_id
                AND vs.sale_date >= DATE_SUB(NOW(), INTERVAL :days DAY)
                AND NOT EXISTS (
                    SELECT 1 FROM security_events se
                    JOIN camera_network cn ON se.camera_id = cn.camera_id
                    WHERE cn.outlet_id = vs.outlet_id
                    AND se.event_timestamp BETWEEN
                        DATE_SUB(vs.sale_date, INTERVAL 2 MINUTE)
                        AND DATE_ADD(vs.sale_date, INTERVAL 2 MINUTE)
                )
                ORDER BY vs.sale_date DESC
            ");
            $stmt->execute(['staff_id' => $staffId, 'days' => $days]);
            $ghostTransactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($ghostTransactions as $transaction) {
                $this->addMismatch(
                    'ghost_transaction_pattern',
                    $transaction,
                    null,
                    'Transaction during period of zero camera activity at outlet',
                    0.85
                );
            }

        } catch (Exception $e) {
            error_log("Ghost transaction detection failed: " . $e->getMessage());
        }
    }

    /**
     * 5. GHOST PRESENCE
     *
     * Camera shows person at register but NO transactions occurring
     */
    private function detectGhostPresence(int $staffId, int $days): void
    {
        try {
            // Find camera events at checkout where staff is detected but no transaction
            $stmt = $this->pdo->prepare("
                SELECT
                    se.id,
                    se.camera_id,
                    se.camera_name,
                    se.outlet_id,
                    se.zone,
                    se.event_timestamp,
                    se.detection_data
                FROM security_events se
                JOIN security_event_staff_correlation sc ON se.id = sc.security_event_id
                WHERE sc.staff_id = :staff_id
                AND se.zone IN ('checkout', 'register')
                AND se.event_type = 'person_detected'
                AND se.event_timestamp >= DATE_SUB(NOW(), INTERVAL :days DAY)
                AND NOT EXISTS (
                    SELECT 1 FROM vend_sales vs
                    WHERE vs.user_id = :staff_id
                    AND vs.outlet_id = se.outlet_id
                    AND vs.sale_date BETWEEN
                        DATE_SUB(se.event_timestamp, INTERVAL 2 MINUTE)
                        AND DATE_ADD(se.event_timestamp, INTERVAL 2 MINUTE)
                )
            ");
            $stmt->execute(['staff_id' => $staffId, 'days' => $days]);
            $ghostPresence = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($ghostPresence as $event) {
                $this->correlationResults['summary']['ghost_presence']++;
                $this->addMismatch(
                    'ghost_presence',
                    null,
                    $event,
                    'Staff detected at register by camera but no transaction recorded',
                    0.7
                );
            }

        } catch (Exception $e) {
            error_log("Ghost presence detection failed: " . $e->getMessage());
        }
    }

    /**
     * 6. MULTI-PERSON DETECTION
     *
     * Camera shows multiple people at till during transaction
     */
    private function detectMultiPersonAtTill(int $staffId, int $days): void
    {
        // Covered in analyzeTillCameraCorrelation
        // This adds pattern analysis for REPEATED multi-person events

        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    DATE(se.event_timestamp) as event_date,
                    COUNT(*) as occurrence_count
                FROM security_events se
                JOIN security_event_staff_correlation sc ON se.id = sc.security_event_id
                WHERE sc.staff_id = :staff_id
                AND se.zone IN ('checkout', 'register')
                AND JSON_EXTRACT(se.detection_data, '$.person_count') > 1
                AND se.event_timestamp >= DATE_SUB(NOW(), INTERVAL :days DAY)
                GROUP BY DATE(se.event_timestamp)
                HAVING occurrence_count > 3
            ");
            $stmt->execute(['staff_id' => $staffId, 'days' => $days]);
            $patterns = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($patterns as $pattern) {
                $this->addMismatch(
                    'repeated_multi_person_pattern',
                    null,
                    $pattern,
                    "Multiple people at till detected {$pattern['occurrence_count']} times on {$pattern['event_date']}",
                    0.75
                );
            }

        } catch (Exception $e) {
            error_log("Multi-person detection failed: " . $e->getMessage());
        }
    }

    /**
     * 7. LOCATION/ZONE MISMATCHES
     *
     * Transaction at Outlet A but camera shows staff at Outlet B
     */
    private function detectLocationMismatches(int $staffId, int $days): void
    {
        try {
            // Find transactions where camera shows staff at DIFFERENT outlet
            $stmt = $this->pdo->prepare("
                SELECT
                    vs.id as transaction_id,
                    vs.sale_date,
                    vs.outlet_id as transaction_outlet,
                    se.outlet_id as camera_outlet,
                    se.camera_name,
                    TIMESTAMPDIFF(SECOND, se.event_timestamp, vs.sale_date) as time_diff
                FROM vend_sales vs
                JOIN security_events se ON se.event_timestamp BETWEEN
                    DATE_SUB(vs.sale_date, INTERVAL 5 MINUTE)
                    AND DATE_ADD(vs.sale_date, INTERVAL 5 MINUTE)
                JOIN security_event_staff_correlation sc ON se.id = sc.security_event_id
                WHERE vs.user_id = :staff_id
                AND sc.staff_id = :staff_id
                AND vs.outlet_id != se.outlet_id
                AND vs.sale_date >= DATE_SUB(NOW(), INTERVAL :days DAY)
            ");
            $stmt->execute(['staff_id' => $staffId, 'days' => $days]);
            $mismatches = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($mismatches as $mismatch) {
                $this->addMismatch(
                    'location_mismatch',
                    $mismatch,
                    null,
                    "Transaction at outlet {$mismatch['transaction_outlet']} but camera shows staff at outlet {$mismatch['camera_outlet']}",
                    0.9 // HIGH - this is VERY suspicious
                );
            }

        } catch (Exception $e) {
            error_log("Location mismatch detection failed: " . $e->getMessage());
        }
    }

    /**
     * 8. IMPOSSIBLE MOVEMENT
     *
     * Staff detected at Outlet A, then Outlet B impossibly fast
     */
    private function detectImpossibleMovement(int $staffId, int $days): void
    {
        try {
            // Get all location events ordered by time
            $stmt = $this->pdo->prepare("
                SELECT
                    outlet_id,
                    recorded_at
                FROM staff_location_history
                WHERE staff_id = :staff_id
                AND recorded_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
                ORDER BY recorded_at ASC
            ");
            $stmt->execute(['staff_id' => $staffId, 'days' => $days]);
            $locations = $stmt->fetchAll(PDO::FETCH_ASSOC);

            for ($i = 1; $i < count($locations); $i++) {
                $prev = $locations[$i - 1];
                $current = $locations[$i];

                if ($prev['outlet_id'] === $current['outlet_id']) {
                    continue; // Same outlet, no movement
                }

                $timeDiff = strtotime($current['recorded_at']) - strtotime($prev['recorded_at']);
                $minutesDiff = $timeDiff / 60;

                if ($minutesDiff < self::MAX_OUTLET_TRAVEL_TIME_MINUTES) {
                    // Impossible to travel between outlets this fast
                    $this->addMismatch(
                        'impossible_movement',
                        [
                            'from_outlet' => $prev['outlet_id'],
                            'to_outlet' => $current['outlet_id'],
                            'time_diff_minutes' => round($minutesDiff, 2),
                            'from_time' => $prev['recorded_at'],
                            'to_time' => $current['recorded_at']
                        ],
                        null,
                        "Impossible movement: Outlet {$prev['outlet_id']} to {$current['outlet_id']} in " . round($minutesDiff, 1) . " minutes",
                        0.95 // CRITICAL - physically impossible
                    );
                }
            }

        } catch (Exception $e) {
            error_log("Impossible movement detection failed: " . $e->getMessage());
        }
    }

    /**
     * Helper: Get camera for specific register
     */
    private function getCameraForRegister(int $outletId, ?string $registerId): ?array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT camera_id, camera_name, zone
                FROM camera_network
                WHERE outlet_id = :outlet_id
                AND zone = 'checkout'
                AND is_active = 1
                LIMIT 1
            ");
            $stmt->execute(['outlet_id' => $outletId]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Helper: Get all cameras for outlet
     */
    private function getCamerasForOutlet(int $outletId): array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT camera_id, camera_name, zone
                FROM camera_network
                WHERE outlet_id = :outlet_id
                AND is_active = 1
            ");
            $stmt->execute(['outlet_id' => $outletId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Helper: Get camera events in time window
     */
    private function getCameraEventsInWindow(string $cameraId, int $timestamp, int $windowSeconds): array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT *
                FROM security_events
                WHERE camera_id = :camera_id
                AND event_timestamp BETWEEN
                    FROM_UNIXTIME(:start_time)
                    AND FROM_UNIXTIME(:end_time)
                AND event_type IN ('person_detected', 'motion_detected')
                ORDER BY confidence DESC, event_timestamp ASC
            ");
            $stmt->execute([
                'camera_id' => $cameraId,
                'start_time' => $timestamp - $windowSeconds,
                'end_time' => $timestamp + $windowSeconds
            ]);

            $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Parse JSON fields
            foreach ($events as &$event) {
                if (isset($event['detection_data']) && is_string($event['detection_data'])) {
                    $event['detection_data'] = json_decode($event['detection_data'], true);
                }
            }

            return $events;
        } catch (Exception $e) {
            error_log("Failed to get camera events: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Helper: Find best camera match for transaction time
     */
    private function findBestCameraMatch(array $cameraEvents, int $transactionTime): array
    {
        $bestMatch = null;
        $smallestTimeDiff = PHP_INT_MAX;

        foreach ($cameraEvents as $event) {
            $eventTime = strtotime($event['event_timestamp']);
            $timeDiff = abs($eventTime - $transactionTime);

            if ($timeDiff < $smallestTimeDiff) {
                $smallestTimeDiff = $timeDiff;
                $bestMatch = $event;
            }
        }

        return $bestMatch ?? [];
    }

    /**
     * Helper: Check if IP is suspicious (not from outlet network)
     */
    private function isSuspiciousIP(string $ip, int $outletId): bool
    {
        // TODO: Implement IP range checking against outlet networks
        // For now, basic check for private vs public IP

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE)) {
            // Public IP - potentially suspicious if not VPN
            return true;
        }

        return false;
    }

    /**
     * Add mismatch to results
     */
    private function addMismatch(
        string $type,
        ?array $transaction,
        ?array $cameraEvent,
        string $description,
        float $severity
    ): void {
        $this->correlationResults['mismatches'][] = [
            'type' => $type,
            'transaction' => $transaction,
            'camera_event' => $cameraEvent,
            'description' => $description,
            'severity' => $severity,
            'detected_at' => date('Y-m-d H:i:s')
        ];

        $this->correlationResults['summary']['suspicious_patterns']++;

        // Add as fraud indicator if severity is high
        if ($severity >= 0.7) {
            $this->correlationResults['fraud_indicators'][] = [
                'type' => $type,
                'description' => $description,
                'severity' => $severity,
                'evidence' => [
                    'transaction' => $transaction,
                    'camera_event' => $cameraEvent
                ]
            ];
        }
    }

    /**
     * Calculate overall correlation score
     */
    private function calculateCorrelationScore(): void
    {
        $totalTransactions = $this->correlationResults['summary']['total_transactions'];
        $cameraConfirmed = $this->correlationResults['summary']['camera_confirmed'];
        $suspiciousPatterns = $this->correlationResults['summary']['suspicious_patterns'];

        if ($totalTransactions === 0) {
            $this->correlationResults['correlation_score'] = 100.0;
            $this->correlationResults['risk_level'] = 'low';
            return;
        }

        // Base score = % of transactions with camera confirmation
        $confirmationRate = ($cameraConfirmed / $totalTransactions) * 100;

        // Penalties for suspicious patterns
        $patternPenalty = min(50, $suspiciousPatterns * 5); // Max 50 point penalty

        $finalScore = max(0, 100 - (100 - $confirmationRate) - $patternPenalty);

        $this->correlationResults['correlation_score'] = round($finalScore, 2);

        // Determine risk level
        $this->correlationResults['risk_level'] = match (true) {
            $finalScore >= 80 => 'low',
            $finalScore >= 60 => 'medium',
            $finalScore >= 40 => 'high',
            default => 'critical'
        };
    }

    /**
     * Store correlation results in database
     */
    private function storeCorrelationResults(): void
    {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO camera_transaction_correlation_log
                (staff_id, analysis_period_days, correlation_score, risk_level,
                 total_transactions, camera_confirmed, suspicious_patterns,
                 correlation_data, created_at)
                VALUES
                (:staff_id, :days, :score, :risk_level,
                 :total_transactions, :camera_confirmed, :suspicious_patterns,
                 :data, NOW())
            ");
            $stmt->execute([
                'staff_id' => $this->correlationResults['staff_id'],
                'days' => $this->correlationResults['analysis_period_days'],
                'score' => $this->correlationResults['correlation_score'],
                'risk_level' => $this->correlationResults['risk_level'],
                'total_transactions' => $this->correlationResults['summary']['total_transactions'],
                'camera_confirmed' => $this->correlationResults['summary']['camera_confirmed'],
                'suspicious_patterns' => $this->correlationResults['summary']['suspicious_patterns'],
                'data' => json_encode($this->correlationResults)
            ]);
        } catch (Exception $e) {
            error_log("Failed to store correlation results: " . $e->getMessage());
        }
    }
}
