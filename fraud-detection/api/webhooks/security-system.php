<?php

/**
 * Security System Events Webhook Receiver
 *
 * Receives events from external security/CCTV system for fraud detection integration.
 * Monitors camera events, motion detection, alerts, and suspicious activities.
 *
 * Features:
 * - Motion detection events
 * - Security alerts and flags
 * - Person tracking across cameras
 * - After-hours activity detection
 * - Zone-based event filtering
 * - Integration with fraud analysis queue
 *
 * @package FraudDetection\Webhooks
 * @version 1.0.0
 */

namespace FraudDetection\Webhooks;

use PDO;
use Exception;

class SecuritySystemWebhookReceiver
{
    private PDO $pdo;
    private string $webhookSecret;
    private array $highPriorityEventTypes = [
        'person_detected',
        'suspicious_activity',
        'after_hours_motion',
        'restricted_area_breach',
        'loitering_detected',
        'unusual_behavior'
    ];

    public function __construct(PDO $pdo, array $config = [])
    {
        $this->pdo = $pdo;
        $this->webhookSecret = $config['webhook_secret'] ?? getenv('SECURITY_SYSTEM_WEBHOOK_SECRET') ?? '';
    }

    /**
     * Handle incoming webhook from security system
     *
     * Expected payload format:
     * {
     *   "event_type": "motion_detected" | "person_detected" | "alert_triggered",
     *   "camera_id": "camera_001",
     *   "camera_name": "Store 1 - Checkout",
     *   "outlet_id": 1,
     *   "zone": "checkout" | "stockroom" | "entrance" | "parking",
     *   "timestamp": "2025-11-14T10:30:00Z",
     *   "confidence": 0.95,
     *   "detection_data": {
     *     "person_count": 2,
     *     "tracked_objects": [...],
     *     "frame_url": "https://...",
     *     "video_clip_url": "https://..."
     *   },
     *   "alert_level": "low" | "medium" | "high" | "critical",
     *   "metadata": { ... }
     * }
     *
     * @return array Response data
     */
    public function handle(): array
    {
        // Get raw input
        $rawInput = file_get_contents('php://input');
        $headers = getallheaders();

        // Log the webhook receipt
        $this->logWebhookReceipt('security_system', $rawInput, $headers);

        // Verify webhook signature
        if (!$this->verifySignature($rawInput, $headers)) {
            http_response_code(401);
            return [
                'success' => false,
                'error' => 'Invalid webhook signature'
            ];
        }

        // Parse payload
        $payload = json_decode($rawInput, true);
        if (!$payload) {
            http_response_code(400);
            return [
                'success' => false,
                'error' => 'Invalid JSON payload'
            ];
        }

        // Validate required fields
        if (!isset($payload['event_type']) || !isset($payload['camera_id'])) {
            http_response_code(400);
            return [
                'success' => false,
                'error' => 'Missing required fields: event_type, camera_id'
            ];
        }

        try {
            // Process the event
            $result = $this->processSecurityEvent($payload);

            return [
                'success' => true,
                'result' => $result
            ];
        } catch (Exception $e) {
            error_log("Security system webhook error: " . $e->getMessage());
            http_response_code(500);
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Verify webhook signature (HMAC SHA-256)
     *
     * @param string $payload Raw payload
     * @param array $headers Request headers
     * @return bool
     */
    private function verifySignature(string $payload, array $headers): bool
    {
        if (!$this->webhookSecret) {
            error_log("Security system webhook: No secret configured, skipping verification");
            return true; // Allow for initial setup without secret
        }

        // Get signature from header
        $signature = $headers['X-Security-Signature'] ?? $headers['x-security-signature'] ?? null;

        if (!$signature) {
            error_log("Security system webhook: No signature provided");
            return false;
        }

        // Compute expected signature
        $expectedSignature = hash_hmac('sha256', $payload, $this->webhookSecret);

        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Process security system event
     *
     * @param array $payload
     * @return array
     */
    private function processSecurityEvent(array $payload): array
    {
        $eventType = $payload['event_type'];
        $cameraId = $payload['camera_id'];
        $outletId = $payload['outlet_id'] ?? null;
        $zone = $payload['zone'] ?? 'unknown';
        $alertLevel = $payload['alert_level'] ?? 'low';
        $confidence = $payload['confidence'] ?? 0.0;

        // Store the security event
        $eventData = [
            'event_type' => $eventType,
            'camera_id' => $cameraId,
            'camera_name' => $payload['camera_name'] ?? null,
            'outlet_id' => $outletId,
            'zone' => $zone,
            'alert_level' => $alertLevel,
            'confidence' => $confidence,
            'detection_data' => json_encode($payload['detection_data'] ?? []),
            'metadata' => json_encode($payload['metadata'] ?? []),
            'event_timestamp' => $payload['timestamp'] ?? date('Y-m-d H:i:s'),
            'received_at' => date('Y-m-d H:i:s')
        ];

        $eventId = $this->storeSecurityEvent($eventData);

        // Try to correlate with staff
        $correlatedStaff = $this->correlateWithStaff($payload);

        // Check if event should trigger fraud analysis
        $shouldTrigger = $this->shouldTriggerFraudAnalysis($payload, $correlatedStaff);

        if ($shouldTrigger && !empty($correlatedStaff)) {
            foreach ($correlatedStaff as $staffId) {
                $this->triggerFraudAnalysis($staffId, $payload, $eventId);
            }
        }

        return [
            'event_id' => $eventId,
            'event_type' => $eventType,
            'alert_level' => $alertLevel,
            'correlated_staff' => $correlatedStaff,
            'fraud_analysis_triggered' => $shouldTrigger && !empty($correlatedStaff)
        ];
    }

    /**
     * Correlate security event with staff members
     * PRIORITY: Uses Lightspeed/Vend transaction data as PRIMARY source
     * FALLBACK: Location tracking, badge scans
     *
     * @param array $payload
     * @return array Array of staff IDs with correlation details
     */
    private function correlateWithStaff(array $payload): array
    {
        $outletId = $payload['outlet_id'] ?? null;
        $timestamp = strtotime($payload['timestamp'] ?? 'now');
        $zone = $payload['zone'] ?? 'unknown';

        if (!$outletId) {
            return [];
        }

        $correlatedStaff = [];

        try {
            // PRIORITY 1: Lightspeed/Vend Transactions (MOST ACCURATE)
            // Find staff who made sales/voids/refunds around event time
            $lightspeedStaff = $this->correlateWithLightspeedTransactions(
                $outletId,
                $timestamp,
                $zone
            );
            $correlatedStaff = array_merge($correlatedStaff, $lightspeedStaff);

            // PRIORITY 2: CIS Cash Register Activity
            // Cash ups, deposits, banking operations
            if (in_array($zone, ['checkout', 'office', 'safe'])) {
                $cisStaff = $this->correlateWithCISCashActivity(
                    $outletId,
                    $timestamp,
                    $zone
                );
                $correlatedStaff = array_merge($correlatedStaff, $cisStaff);
            }

            // PRIORITY 3: Location tracking (fallback)
            $locationStaff = $this->correlateWithLocationHistory(
                $outletId,
                $timestamp
            );
            $correlatedStaff = array_merge($correlatedStaff, $locationStaff);

            // Deduplicate and return unique staff IDs
            return array_values(array_unique($correlatedStaff));

        } catch (Exception $e) {
            error_log("Staff correlation failed: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Correlate with Lightspeed/Vend transaction data (PRIMARY SOURCE)
     * Finds staff who performed transactions near event time
     *
     * @param int $outletId
     * @param int $timestamp
     * @param string $zone
     * @return array Staff IDs
     */
    private function correlateWithLightspeedTransactions(
        int $outletId,
        int $timestamp,
        string $zone
    ): array {
        try {
            // Query Lightspeed data for sales/voids/refunds in ±5 minute window
            // This is MOST accurate - direct register activity
            $stmt = $this->pdo->prepare("
                SELECT DISTINCT user_id as staff_id
                FROM vend_sales
                WHERE outlet_id = :outlet_id
                AND sale_date BETWEEN
                    DATE_SUB(FROM_UNIXTIME(:timestamp), INTERVAL 5 MINUTE)
                    AND DATE_ADD(FROM_UNIXTIME(:timestamp), INTERVAL 5 MINUTE)
                AND status IN ('OPEN', 'CLOSED', 'SAVED', 'VOIDED')
                ORDER BY sale_date DESC
            ");
            $stmt->execute([
                'outlet_id' => $outletId,
                'timestamp' => $timestamp
            ]);

            $staffIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

            // Log the correlation for audit
            foreach ($staffIds as $staffId) {
                error_log("Security event correlation: Staff {$staffId} had Lightspeed activity at outlet {$outletId} near timestamp " . date('Y-m-d H:i:s', $timestamp));
            }

            return $staffIds;
        } catch (Exception $e) {
            error_log("Lightspeed correlation failed: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Correlate with CIS cash register activity (SECONDARY SOURCE)
     * Cash ups, store deposits, banking operations
     *
     * @param int $outletId
     * @param int $timestamp
     * @param string $zone
     * @return array Staff IDs
     */
    private function correlateWithCISCashActivity(
        int $outletId,
        int $timestamp,
        string $zone
    ): array {
        try {
            // Query CIS for cash-related activities
            // Cash ups, deposits, banking transactions
            $stmt = $this->pdo->prepare("
                SELECT DISTINCT staff_id
                FROM (
                    -- Cash ups
                    SELECT staff_id, created_at
                    FROM cash_register_reconciliation
                    WHERE outlet_id = :outlet_id
                    AND created_at BETWEEN
                        DATE_SUB(FROM_UNIXTIME(:timestamp), INTERVAL 10 MINUTE)
                        AND DATE_ADD(FROM_UNIXTIME(:timestamp), INTERVAL 10 MINUTE)

                    UNION ALL

                    -- Store deposits
                    SELECT staff_id, deposit_date as created_at
                    FROM store_deposits
                    WHERE outlet_id = :outlet_id
                    AND deposit_date BETWEEN
                        DATE_SUB(FROM_UNIXTIME(:timestamp), INTERVAL 10 MINUTE)
                        AND DATE_ADD(FROM_UNIXTIME(:timestamp), INTERVAL 10 MINUTE)

                    UNION ALL

                    -- Banking operations
                    SELECT staff_id, transaction_date as created_at
                    FROM banking_transactions
                    WHERE outlet_id = :outlet_id
                    AND transaction_date BETWEEN
                        DATE_SUB(FROM_UNIXTIME(:timestamp), INTERVAL 10 MINUTE)
                        AND DATE_ADD(FROM_UNIXTIME(:timestamp), INTERVAL 10 MINUTE)
                ) AS cash_activities
                ORDER BY created_at DESC
            ");
            $stmt->execute([
                'outlet_id' => $outletId,
                'timestamp' => $timestamp
            ]);

            $staffIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

            foreach ($staffIds as $staffId) {
                error_log("Security event correlation: Staff {$staffId} had CIS cash activity at outlet {$outletId} near timestamp " . date('Y-m-d H:i:s', $timestamp));
            }

            return $staffIds;
        } catch (Exception $e) {
            error_log("CIS cash activity correlation failed: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Correlate with location tracking (FALLBACK)
     *
     * @param int $outletId
     * @param int $timestamp
     * @return array Staff IDs
     */
    private function correlateWithLocationHistory(int $outletId, int $timestamp): array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT DISTINCT staff_id
                FROM staff_location_history
                WHERE outlet_id = :outlet_id
                AND recorded_at BETWEEN
                    DATE_SUB(FROM_UNIXTIME(:timestamp), INTERVAL 15 MINUTE)
                    AND DATE_ADD(FROM_UNIXTIME(:timestamp), INTERVAL 15 MINUTE)
                AND confidence >= 0.7
                ORDER BY recorded_at DESC
            ");
            $stmt->execute([
                'outlet_id' => $outletId,
                'timestamp' => $timestamp
            ]);

            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (Exception $e) {
            error_log("Location history correlation failed: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Determine if event should trigger fraud analysis
     *
     * @param array $payload
     * @param array $correlatedStaff
     * @return bool
     */
    private function shouldTriggerFraudAnalysis(array $payload, array $correlatedStaff): bool
    {
        $eventType = $payload['event_type'];
        $alertLevel = $payload['alert_level'] ?? 'low';
        $zone = $payload['zone'] ?? 'unknown';

        // Trigger 1: High-priority event types
        if (in_array($eventType, $this->highPriorityEventTypes)) {
            return true;
        }

        // Trigger 2: Critical or high alert level
        if (in_array($alertLevel, ['critical', 'high'])) {
            return true;
        }

        // Trigger 3: After-hours activity in restricted zones
        if ($this->isAfterHours() && in_array($zone, ['stockroom', 'office', 'safe'])) {
            return true;
        }

        // Trigger 4: Multiple staff detected in sensitive zones
        if (in_array($zone, ['stockroom', 'office']) && count($correlatedStaff) >= 2) {
            return true;
        }

        return false;
    }

    /**
     * Check if current time is after hours
     *
     * @return bool
     */
    private function isAfterHours(): bool
    {
        $hour = (int)date('G');
        // After hours: before 6 AM or after 10 PM
        return $hour < 6 || $hour >= 22;
    }

    /**
     * Store security event in database
     *
     * @param array $data
     * @return int Event ID
     */
    private function storeSecurityEvent(array $data): int
    {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO security_events
                (event_type, camera_id, camera_name, outlet_id, zone, alert_level,
                 confidence, detection_data, metadata, event_timestamp, received_at)
                VALUES
                (:event_type, :camera_id, :camera_name, :outlet_id, :zone, :alert_level,
                 :confidence, :detection_data, :metadata, :event_timestamp, :received_at)
            ");
            $stmt->execute($data);
            return (int)$this->pdo->lastInsertId();
        } catch (Exception $e) {
            error_log("Failed to store security event: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Trigger fraud analysis for staff member
     *
     * @param int $staffId
     * @param array $payload
     * @param int $eventId
     */
    private function triggerFraudAnalysis(int $staffId, array $payload, int $eventId): void
    {
        try {
            // Determine priority
            $priority = match ($payload['alert_level'] ?? 'low') {
                'critical' => 'critical',
                'high' => 'high',
                'medium' => 'medium',
                default => 'low'
            };

            $stmt = $this->pdo->prepare("
                INSERT INTO fraud_analysis_queue
                (staff_id, trigger_source, trigger_data, priority, created_at)
                VALUES (:staff_id, 'security_system_webhook', :trigger_data, :priority, NOW())
            ");
            $stmt->execute([
                'staff_id' => $staffId,
                'trigger_data' => json_encode([
                    'security_event_id' => $eventId,
                    'event_type' => $payload['event_type'],
                    'camera_id' => $payload['camera_id'],
                    'zone' => $payload['zone'] ?? null,
                    'alert_level' => $payload['alert_level'] ?? 'low',
                    'timestamp' => $payload['timestamp'] ?? date('Y-m-d H:i:s')
                ]),
                'priority' => $priority
            ]);
        } catch (Exception $e) {
            error_log("Failed to trigger fraud analysis: " . $e->getMessage());
        }
    }

    /**
     * Log webhook receipt
     *
     * @param string $platform
     * @param string $payload
     * @param array $headers
     */
    private function logWebhookReceipt(string $platform, string $payload, array $headers): void
    {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO webhook_log
                (platform, payload, headers, received_at)
                VALUES (:platform, :payload, :headers, NOW())
            ");
            $stmt->execute([
                'platform' => $platform,
                'payload' => $payload,
                'headers' => json_encode($headers)
            ]);
        } catch (Exception $e) {
            error_log("Failed to log webhook: " . $e->getMessage());
        }
    }

    /**
     * Get security event statistics for outlet
     *
     * @param int $outletId
     * @param int $days Number of days to analyze
     * @return array
     */
    public function getOutletSecurityStats(int $outletId, int $days = 30): array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    event_type,
                    zone,
                    alert_level,
                    COUNT(*) as event_count,
                    AVG(confidence) as avg_confidence,
                    MAX(event_timestamp) as last_event
                FROM security_events
                WHERE outlet_id = :outlet_id
                AND received_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
                GROUP BY event_type, zone, alert_level
                ORDER BY event_count DESC
            ");
            $stmt->execute([
                'outlet_id' => $outletId,
                'days' => $days
            ]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Failed to get security stats: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get after-hours events that triggered fraud analysis
     *
     * @param int $days Number of days to look back
     * @return array
     */
    public function getAfterHoursIncidents(int $days = 7): array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    se.event_type,
                    se.camera_name,
                    se.zone,
                    se.event_timestamp,
                    faq.staff_id,
                    s.name as staff_name
                FROM security_events se
                JOIN fraud_analysis_queue faq ON JSON_EXTRACT(faq.trigger_data, '$.security_event_id') = se.id
                JOIN staff s ON faq.staff_id = s.id
                WHERE faq.trigger_source = 'security_system_webhook'
                AND (HOUR(se.event_timestamp) < 6 OR HOUR(se.event_timestamp) >= 22)
                AND se.received_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
                ORDER BY se.event_timestamp DESC
            ");
            $stmt->execute(['days' => $days]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Failed to get after-hours incidents: " . $e->getMessage());
            return [];
        }
    }
}

// Endpoint handler
if (basename($_SERVER['SCRIPT_NAME']) === 'security-system.php') {
    require_once __DIR__ . '/../../bootstrap.php';

    header('Content-Type: application/json');

    $pdo = new PDO(
        "mysql:host=" . getenv('DB_HOST') . ";dbname=" . getenv('DB_NAME'),
        getenv('DB_USER'),
        getenv('DB_PASS'),
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    $receiver = new SecuritySystemWebhookReceiver($pdo);
    $result = $receiver->handle();

    echo json_encode($result);
}
