<?php
/**
 * HTTP Rate Limit Reporter
 *
 * Persists 429 (and related) responses for external service calls so we can
 * monitor backoff pressure across Xero and Deputy integrations.
 *
 * @package HumanResources\Payroll\Services
 */

declare(strict_types=1);

namespace HumanResources\Payroll\Services;

use PDO;
use PDOException;

final class HttpRateLimitReporter
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Record a rate limit occurrence in telemetry table.
     */
    public function record(
        string $service,
        string $endpoint,
        int $status,
        ?int $retryAfter,
        ?string $requestId = null,
        ?string $payloadHash = null
    ): void {
        try {
            $stmt = $this->db->prepare(
                'INSERT INTO payroll_rate_limits (service, endpoint, http_status, retry_after_sec, request_id, payload_hash)
                 VALUES (:service, :endpoint, :status, :retry_after, :request_id, :payload_hash)'
            );
            $stmt->execute([
                'service' => $service,
                'endpoint' => $endpoint,
                'status' => $status,
                'retry_after' => $retryAfter,
                'request_id' => $requestId,
                'payload_hash' => $payloadHash,
            ]);
        } catch (PDOException $e) {
            error_log('[RateLimitReporter] Failed inserting telemetry: ' . $e->getMessage());
        }
    }

    /**
     * Persist a batch of rate limit events efficiently.
     *
     * @param array<int, array<string, mixed>> $events
     */
    public function recordMultiple(array $events): void
    {
        if (empty($events)) {
            return;
        }

        try {
            $placeholder = [];
            $params = [];
            foreach ($events as $index => $event) {
                $placeholder[] = "(:service{$index}, :endpoint{$index}, :status{$index}, :retry{$index}, :request{$index}, :hash{$index})";
                $params["service{$index}"] = $event['service'] ?? 'unknown';
                $params["endpoint{$index}"] = $event['endpoint'] ?? 'unknown';
                $params["status{$index}"] = $event['status'] ?? 429;
                $params["retry{$index}"] = $event['retry_after'] ?? null;
                $params["request{$index}"] = $event['request_id'] ?? null;
                $params["hash{$index}"] = $event['payload_hash'] ?? null;
            }

            $sql = sprintf(
                'INSERT INTO payroll_rate_limits (service, endpoint, http_status, retry_after_sec, request_id, payload_hash) VALUES %s',
                implode(', ', $placeholder)
            );

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
        } catch (PDOException $e) {
            error_log('[RateLimitReporter] Failed batch insert: ' . $e->getMessage());
        }
    }
}
