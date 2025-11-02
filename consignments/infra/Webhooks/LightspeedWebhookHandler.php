<?php
/**
 * Lightspeed Webhook Handler
 *
 * Securely processes Lightspeed webhook events with:
 * - HMAC-SHA256 signature verification
 * - Replay attack protection (event ID tracking)
 * - Quick 202 response (async processing)
 * - Structured logging with correlation IDs
 *
 * @package Consignments\Infra\Webhooks
 */

declare(strict_types=1);

namespace Consignments\Infra\Webhooks;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class LightspeedWebhookHandler
{
    private string $webhookSecret;
    private \PDO $pdo;
    private LoggerInterface $logger;

    // Replay protection window (events older than this are rejected)
    private int $maxEventAgeSeconds = 300; // 5 minutes

    public function __construct(\PDO $pdo, ?LoggerInterface $logger = null)
    {
        $this->pdo = $pdo;
        $this->logger = $logger ?? new NullLogger();

        $this->webhookSecret = $this->getWebhookSecret();
    }

    private function getWebhookSecret(): string
    {
        $secret = getenv('LS_WEBHOOK_SECRET');

        if (empty($secret)) {
            throw new \RuntimeException(
                'LS_WEBHOOK_SECRET environment variable is required for webhook validation'
            );
        }

        return $secret;
    }

    /**
     * Process incoming webhook request
     *
     * @param string $rawPayload Raw request body (for HMAC verification)
     * @param array $headers Request headers
     * @return array Response with status and message
     */
    public function handle(string $rawPayload, array $headers): array
    {
        $requestId = $this->generateRequestId();

        $this->logger->info('Webhook received', [
            'request_id' => $requestId,
            'payload_size' => strlen($rawPayload),
            'headers' => $this->maskSensitiveHeaders($headers)
        ]);

        try {
            // 1. Verify HMAC signature
            $this->verifySignature($rawPayload, $headers);

            // 2. Parse payload
            $payload = json_decode($rawPayload, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new WebhookException('Invalid JSON payload: ' . json_last_error_msg());
            }

            // 3. Validate required fields
            $this->validatePayload($payload);

            // 4. Check for replay attack
            $this->checkReplayProtection($payload);

            // 5. Queue for async processing
            $jobId = $this->queueWebhookEvent($payload, $requestId);

            $this->logger->info('Webhook queued successfully', [
                'request_id' => $requestId,
                'job_id' => $jobId,
                'event_type' => $payload['event_type'] ?? 'unknown'
            ]);

            return [
                'success' => true,
                'message' => 'Webhook received and queued for processing',
                'request_id' => $requestId,
                'job_id' => $jobId
            ];

        } catch (WebhookException $e) {
            $this->logger->warning('Webhook validation failed', [
                'request_id' => $requestId,
                'error' => $e->getMessage(),
                'code' => $e->getCode()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'request_id' => $requestId
            ];

        } catch (\Throwable $e) {
            $this->logger->error('Webhook processing error', [
                'request_id' => $requestId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => 'Internal server error',
                'request_id' => $requestId
            ];
        }
    }

    /**
     * Verify HMAC-SHA256 signature
     *
     * @throws WebhookException if signature is invalid or missing
     */
    private function verifySignature(string $payload, array $headers): void
    {
        // Lightspeed sends signature in X-Lightspeed-Signature header
        $signature = $headers['X-Lightspeed-Signature']
            ?? $headers['x-lightspeed-signature']
            ?? null;

        if (empty($signature)) {
            throw new WebhookException('Missing X-Lightspeed-Signature header', 401);
        }

        // Calculate expected signature
        $expectedSignature = hash_hmac('sha256', $payload, $this->webhookSecret);

        // Timing-safe comparison to prevent timing attacks
        if (!hash_equals($expectedSignature, $signature)) {
            $this->logger->warning('HMAC signature mismatch', [
                'expected_length' => strlen($expectedSignature),
                'received_length' => strlen($signature)
            ]);

            throw new WebhookException('Invalid webhook signature', 401);
        }
    }

    /**
     * Validate required payload fields
     *
     * @throws WebhookException if validation fails
     */
    private function validatePayload(array $payload): void
    {
        $requiredFields = ['event_id', 'event_type', 'created_at'];

        foreach ($requiredFields as $field) {
            if (!isset($payload[$field])) {
                throw new WebhookException("Missing required field: {$field}", 400);
            }
        }

        // Validate event_type format
        if (!preg_match('/^[a-z_\.]+$/', $payload['event_type'])) {
            throw new WebhookException('Invalid event_type format', 400);
        }

        // Validate timestamp (ISO 8601 or Unix timestamp)
        $createdAt = $payload['created_at'];
        if (!is_numeric($createdAt) && !strtotime($createdAt)) {
            throw new WebhookException('Invalid created_at timestamp', 400);
        }
    }

    /**
     * Check for replay attacks using event ID tracking
     *
     * @throws WebhookException if event is duplicate or too old
     */
    private function checkReplayProtection(array $payload): void
    {
        $eventId = $payload['event_id'];
        $createdAt = $payload['created_at'];

        // Convert timestamp to Unix timestamp if needed
        $timestamp = is_numeric($createdAt) ? (int)$createdAt : strtotime($createdAt);
        $eventAge = time() - $timestamp;

        // Reject events older than max age (replay protection)
        if ($eventAge > $this->maxEventAgeSeconds) {
            throw new WebhookException(
                "Event too old: {$eventAge}s (max: {$this->maxEventAgeSeconds}s)",
                400
            );
        }

        // Check if event ID already processed
        $stmt = $this->pdo->prepare(
            "SELECT id FROM webhook_events WHERE event_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)"
        );
        $stmt->execute([$eventId]);

        if ($stmt->fetch()) {
            throw new WebhookException("Duplicate event ID: {$eventId}", 409);
        }
    }

    /**
     * Queue webhook event for async processing
     */
    private function queueWebhookEvent(array $payload, string $requestId): int
    {
        $this->pdo->beginTransaction();

        try {
            // 1. Record webhook event (idempotency + audit trail)
            $stmt = $this->pdo->prepare("
                INSERT INTO webhook_events (
                    event_id, event_type, payload, request_id, received_at, status
                ) VALUES (?, ?, ?, ?, NOW(), 'pending')
            ");
            $stmt->execute([
                $payload['event_id'],
                $payload['event_type'],
                json_encode($payload),
                $requestId
            ]);

            $webhookEventId = (int)$this->pdo->lastInsertId();

            // 2. Queue job for processing
            $jobType = $this->getJobTypeForEvent($payload['event_type']);
            $jobPayload = [
                'webhook_event_id' => $webhookEventId,
                'event_id' => $payload['event_id'],
                'event_type' => $payload['event_type'],
                'data' => $payload['data'] ?? [],
                'request_id' => $requestId
            ];

            $stmt = $this->pdo->prepare("
                INSERT INTO queue_jobs (
                    job_type, payload, priority, status, created_at
                ) VALUES (?, ?, ?, 'pending', NOW())
            ");

            // High priority for webhook events (process quickly)
            $priority = 8;

            $stmt->execute([
                $jobType,
                json_encode($jobPayload),
                $priority
            ]);

            $jobId = (int)$this->pdo->lastInsertId();

            $this->pdo->commit();

            return $jobId;

        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Map webhook event type to queue job type
     */
    private function getJobTypeForEvent(string $eventType): string
    {
        // Map Lightspeed event types to our job types
        $mapping = [
            'consignment.created' => 'consignment.sync',
            'consignment.updated' => 'consignment.sync',
            'consignment.completed' => 'consignment.sync',
            'consignment.deleted' => 'consignment.sync',
            'transfer.created' => 'transfer.sync',
            'transfer.updated' => 'transfer.sync',
            'transfer.completed' => 'transfer.sync',
        ];

        return $mapping[$eventType] ?? 'webhook.process';
    }

    private function generateRequestId(): string
    {
        return sprintf(
            'webhook_%s_%s',
            date('YmdHis'),
            bin2hex(random_bytes(8))
        );
    }

    private function maskSensitiveHeaders(array $headers): array
    {
        $masked = [];
        $sensitiveKeys = ['authorization', 'x-lightspeed-signature', 'x-api-key'];

        foreach ($headers as $key => $value) {
            $lowerKey = strtolower($key);

            if (in_array($lowerKey, $sensitiveKeys)) {
                $masked[$key] = substr($value, 0, 8) . '...[REDACTED]';
            } else {
                $masked[$key] = $value;
            }
        }

        return $masked;
    }
}

/**
 * Webhook-specific exception
 */
class WebhookException extends \Exception
{
    // HTTP status code for response
}
