<?php
/**
 * PayrollDeputyService
 *
 * Thin wrapper for Deputy API (via assets/functions/deputy.php)
 * Adds rate-limit telemetry and logs all calls to payroll_activity_log.
 *
 * @author GitHub Copilot
 * @created 2025-11-02
 */

declare(strict_types=1);

class PayrollDeputyService
{
    private $db;
    private $logger;

    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->logger = function($msg, $meta = []) {
            $entry = [
                'timestamp' => date('Y-m-d H:i:s'),
                'message' => $msg,
                'meta' => json_encode($meta)
            ];
            $sql = "INSERT INTO payroll_activity_log (timestamp, message, meta) VALUES (?, ?, ?)";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$entry['timestamp'], $entry['message'], $entry['meta']]);
        };
    $docRoot = $_SERVER['DOCUMENT_ROOT'] ?? dirname(__DIR__, 4);
        $deputyPath = rtrim((string) $docRoot, '/') . '/assets/functions/deputy.php';
        if (!is_file($deputyPath)) {
            throw new RuntimeException('Deputy library not found at ' . $deputyPath);
        }
        /** @psalm-suppress UnresolvableInclude */
        require_once $deputyPath;
    }

    /**
     * Fetch timesheets from Deputy
     *
     * @param array $params
     * @return array
     */
    public function fetchTimesheets(array $params = []): array
    {
        $endpoint = 'Deputy::getTimesheets';
        try {
            $result = Deputy::getTimesheets($params);
            ($this->logger)("Deputy API call", [
                'endpoint' => $endpoint,
                'params' => $params,
                'result_count' => is_array($result) ? count($result) : 0
            ]);
            return $result;
        } catch (DeputyRateLimitException $e) {
            $retryAfter = $e->getRetryAfter() ?? null;
            ($this->logger)("Deputy API 429", [
                'endpoint' => $endpoint,
                'params' => $params,
                'error' => 'rate_limit',
                'retry_after' => $retryAfter
            ]);
            $this->persistRateLimit('deputy', $endpoint, $retryAfter);
            throw $e;
        } catch (\Throwable $e) {
            ($this->logger)("Deputy API error", [
                'endpoint' => $endpoint,
                'params' => $params,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Persist rate-limit event
     *
     * @param string $provider
     * @param string $endpoint
     * @param int|null $retryAfter
     */
    private function persistRateLimit(string $provider, string $endpoint, $retryAfter): void
    {
        $sql = "INSERT INTO payroll_rate_limits (provider, endpoint, retry_after, occurred_at) VALUES (?, ?, ?, NOW())";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$provider, $endpoint, $retryAfter]);
    }
}
