<?php

/**
 * CIS Internal Messenger Webhook Receiver
 *
 * Handles webhooks from CIS Internal Messenger for fraud detection.
 * Monitors chat messages for suspicious keywords, patterns, and behaviors.
 *
 * Features:
 * - Message content analysis
 * - Suspicious keyword detection
 * - File sharing monitoring
 * - After-hours communication flagging
 * - Direct message vs group chat tracking
 *
 * @package FraudDetection\Webhooks
 * @version 1.0.0
 */

namespace FraudDetection\Webhooks;

use PDO;
use Exception;

class CISMessengerWebhookReceiver
{
    private PDO $pdo;
    private string $webhookSecret;
    private array $suspiciousKeywords = [
        'password',
        'delete',
        'cover up',
        'hide',
        'secret',
        'dont tell',
        'between us',
        'off the record',
        'cash only',
        'no receipt',
        'void it',
        'discount',
        'refund',
        'inventory',
        'stock',
        'backdoor'
    ];

    public function __construct(PDO $pdo, array $config = [])
    {
        $this->pdo = $pdo;
        $this->webhookSecret = $config['webhook_secret'] ?? getenv('CIS_MESSENGER_WEBHOOK_SECRET') ?? '';

        // Allow custom suspicious keywords
        if (isset($config['suspicious_keywords'])) {
            $this->suspiciousKeywords = array_merge(
                $this->suspiciousKeywords,
                $config['suspicious_keywords']
            );
        }
    }

    /**
     * Handle incoming webhook
     *
     * Expected payload format:
     * {
     *   "event_type": "message.created" | "message.deleted" | "file.shared",
     *   "message_id": "12345",
     *   "sender_staff_id": 123,
     *   "recipient_staff_id": 456 (for direct messages),
     *   "channel_id": "general" (for group chats),
     *   "message_text": "Hello world",
     *   "is_direct_message": true,
     *   "timestamp": "2025-11-14 10:30:00",
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
        $this->logWebhookReceipt('cis_messenger', $rawInput, $headers);

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
        if (!isset($payload['event_type']) || !isset($payload['sender_staff_id'])) {
            http_response_code(400);
            return [
                'success' => false,
                'error' => 'Missing required fields: event_type, sender_staff_id'
            ];
        }

        try {
            // Process the event
            $result = $this->processEvent($payload);

            return [
                'success' => true,
                'result' => $result
            ];
        } catch (Exception $e) {
            error_log("CIS Messenger webhook error: " . $e->getMessage());
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
            error_log("CIS Messenger webhook: No secret configured, skipping verification");
            return true; // Allow for initial setup without secret
        }

        // Get signature from header
        $signature = $headers['X-CIS-Signature'] ?? $headers['x-cis-signature'] ?? null;

        if (!$signature) {
            error_log("CIS Messenger webhook: No signature provided");
            return false;
        }

        // Compute expected signature
        $expectedSignature = hash_hmac('sha256', $payload, $this->webhookSecret);

        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Process webhook event
     *
     * @param array $payload
     * @return array
     */
    private function processEvent(array $payload): array
    {
        $eventType = $payload['event_type'];
        $senderStaffId = (int)$payload['sender_staff_id'];

        // Verify sender exists
        if (!$this->staffExists($senderStaffId)) {
            throw new Exception("Invalid sender_staff_id: {$senderStaffId}");
        }

        // Store communication event
        $eventData = [
            'staff_id' => $senderStaffId,
            'platform' => 'cis_messenger',
            'event_type' => $eventType,
            'message_id' => $payload['message_id'] ?? null,
            'recipient_staff_id' => $payload['recipient_staff_id'] ?? null,
            'channel_id' => $payload['channel_id'] ?? null,
            'message_text' => $payload['message_text'] ?? null,
            'is_direct_message' => $payload['is_direct_message'] ?? false,
            'metadata' => json_encode($payload['metadata'] ?? []),
            'received_at' => date('Y-m-d H:i:s')
        ];

        $this->storeCommunicationEvent($eventData);

        // Analyze for suspicious activity
        $flags = $this->analyzeMessage($payload);

        if (!empty($flags)) {
            $this->triggerFraudAnalysis($senderStaffId, $payload, $flags);
        }

        return [
            'event_type' => $eventType,
            'staff_id' => $senderStaffId,
            'flags' => $flags,
            'fraud_analysis_triggered' => !empty($flags)
        ];
    }

    /**
     * Analyze message for suspicious patterns
     *
     * @param array $payload
     * @return array List of detected flags
     */
    private function analyzeMessage(array $payload): array
    {
        $flags = [];
        $messageText = strtolower($payload['message_text'] ?? '');
        $eventType = $payload['event_type'];
        $isDirect = $payload['is_direct_message'] ?? false;

        // Flag 1: Suspicious keywords
        foreach ($this->suspiciousKeywords as $keyword) {
            if (strpos($messageText, strtolower($keyword)) !== false) {
                $flags[] = "suspicious_keyword:{$keyword}";
            }
        }

        // Flag 2: Message deletion (potential evidence removal)
        if ($eventType === 'message.deleted') {
            $flags[] = 'message_deleted';
        }

        // Flag 3: After-hours communication
        if ($this->isAfterHours()) {
            $flags[] = 'after_hours_communication';
        }

        // Flag 4: Direct messages between non-managers
        if ($isDirect && !$this->bothAreManagers($payload)) {
            // Only flag if message contains sensitive keywords
            if (!empty(array_filter($flags, fn($f) => strpos($f, 'suspicious_keyword') === 0))) {
                $flags[] = 'suspicious_direct_message';
            }
        }

        // Flag 5: File sharing
        if ($eventType === 'file.shared') {
            $flags[] = 'file_shared';
        }

        // Flag 6: High message frequency
        if ($this->hasHighMessageFrequency($payload['sender_staff_id'])) {
            $flags[] = 'high_message_frequency';
        }

        return $flags;
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
     * Check if both parties are managers
     *
     * @param array $payload
     * @return bool
     */
    private function bothAreManagers(array $payload): bool
    {
        $senderId = $payload['sender_staff_id'];
        $recipientId = $payload['recipient_staff_id'] ?? null;

        if (!$recipientId) {
            return false;
        }

        try {
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*)
                FROM staff
                WHERE id IN (:sender, :recipient)
                AND role IN ('manager', 'admin', 'owner')
            ");
            $stmt->execute([
                'sender' => $senderId,
                'recipient' => $recipientId
            ]);
            return $stmt->fetchColumn() == 2;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Check for high message frequency (>50 messages in last 10 minutes)
     *
     * @param int $staffId
     * @return bool
     */
    private function hasHighMessageFrequency(int $staffId): bool
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*)
                FROM communication_events
                WHERE staff_id = :staff_id
                AND platform = 'cis_messenger'
                AND received_at >= DATE_SUB(NOW(), INTERVAL 10 MINUTE)
            ");
            $stmt->execute(['staff_id' => $staffId]);
            return $stmt->fetchColumn() > 50;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Check if staff member exists
     *
     * @param int $staffId
     * @return bool
     */
    private function staffExists(int $staffId): bool
    {
        try {
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM staff WHERE id = :id");
            $stmt->execute(['id' => $staffId]);
            return $stmt->fetchColumn() > 0;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Store communication event
     *
     * @param array $data
     */
    private function storeCommunicationEvent(array $data): void
    {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO communication_events
                (staff_id, platform, event_type, message_id, recipient_staff_id,
                 channel_id, message_text, is_direct_message, metadata, received_at)
                VALUES
                (:staff_id, :platform, :event_type, :message_id, :recipient_staff_id,
                 :channel_id, :message_text, :is_direct_message, :metadata, :received_at)
            ");
            $stmt->execute($data);
        } catch (Exception $e) {
            error_log("Failed to store communication event: " . $e->getMessage());
        }
    }

    /**
     * Trigger fraud analysis
     *
     * @param int $staffId
     * @param array $payload
     * @param array $flags
     */
    private function triggerFraudAnalysis(int $staffId, array $payload, array $flags): void
    {
        try {
            // Determine priority based on flags
            $priority = 'medium';
            if (in_array('message_deleted', $flags) ||
                in_array('suspicious_direct_message', $flags)) {
                $priority = 'high';
            }

            $stmt = $this->pdo->prepare("
                INSERT INTO fraud_analysis_queue
                (staff_id, trigger_source, trigger_data, priority, created_at)
                VALUES (:staff_id, 'cis_messenger_webhook', :trigger_data, :priority, NOW())
            ");
            $stmt->execute([
                'staff_id' => $staffId,
                'trigger_data' => json_encode([
                    'payload' => $payload,
                    'flags' => $flags
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
     * Get suspicious message statistics for a staff member
     *
     * @param int $staffId
     * @param int $days Number of days to analyze
     * @return array
     */
    public function getStaffMessageStats(int $staffId, int $days = 30): array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    COUNT(*) as total_messages,
                    SUM(CASE WHEN is_direct_message = 1 THEN 1 ELSE 0 END) as direct_messages,
                    COUNT(DISTINCT recipient_staff_id) as unique_recipients,
                    COUNT(DISTINCT channel_id) as channels_used,
                    SUM(CASE WHEN HOUR(received_at) < 6 OR HOUR(received_at) >= 22 THEN 1 ELSE 0 END) as after_hours_messages
                FROM communication_events
                WHERE staff_id = :staff_id
                AND platform = 'cis_messenger'
                AND received_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
            ");
            $stmt->execute([
                'staff_id' => $staffId,
                'days' => $days
            ]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        } catch (Exception $e) {
            error_log("Failed to get message stats: " . $e->getMessage());
            return [];
        }
    }
}

// Endpoint handler
if (basename($_SERVER['SCRIPT_NAME']) === 'cis-messenger.php') {
    require_once __DIR__ . '/../../bootstrap.php';

    header('Content-Type: application/json');

    $pdo = new PDO(
        "mysql:host=" . getenv('DB_HOST') . ";dbname=" . getenv('DB_NAME'),
        getenv('DB_USER'),
        getenv('DB_PASS'),
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    $receiver = new CISMessengerWebhookReceiver($pdo);
    $result = $receiver->handle();

    echo json_encode($result);
}
