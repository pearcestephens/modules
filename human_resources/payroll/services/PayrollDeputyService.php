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

use PayrollModule\Lib\PayrollLogger;

class PayrollDeputyService
{
    private PDO $db;
    private PayrollLogger $logger;

    private function __construct(PDO $db)
    {
        $this->db = $db;

        require_once __DIR__ . '/../lib/PayrollLogger.php';
        $this->logger = new PayrollLogger();
    $docRoot = !empty($_SERVER['DOCUMENT_ROOT']) ? $_SERVER['DOCUMENT_ROOT'] : dirname(__DIR__, 4);
        $deputyPath = rtrim((string) $docRoot, '/') . '/assets/functions/deputy.php';
        if (!is_file($deputyPath)) {
            throw new RuntimeException('Deputy library not found at ' . $deputyPath);
        }
        /** @psalm-suppress UnresolvableInclude */
        require_once $deputyPath;
    }

    public static function make(PDO $db): self
    {
        return new self($db);
    }

    /**
     * Fetch timesheets from Deputy
     *
     * @param string $start Start date YYYY-MM-DD
     * @param string $end End date YYYY-MM-DD
     * @return array
     */
    public function fetchTimesheets(string $start, string $end): array
    {
        $endpoint = 'Deputy::getTimesheets';
        $params = ['start' => $start, 'end' => $end];

        try {
            $result = Deputy::getTimesheets($params);
            $this->logInfo('deputy.api.call', 'Deputy API call successful', [
                'endpoint' => $endpoint,
                'start' => $start,
                'end' => $end,
                'result_count' => is_array($result) ? count($result) : 0
            ]);
            return $result;
        } catch (DeputyRateLimitException $e) {
            $retryAfter = $e->getRetryAfter() ?? null;
            $this->logWarning('deputy.api.rate_limit', 'Deputy API returned 429', [
                'endpoint' => $endpoint,
                'start' => $start,
                'end' => $end,
                'error' => 'rate_limit',
                'retry_after' => $retryAfter
            ]);
            $this->persistRateLimit('deputy', $endpoint, $retryAfter);
            throw $e;
        } catch (\Throwable $e) {
            $this->logError('deputy.api.error', 'Deputy API error', [
                'endpoint' => $endpoint,
                'start' => $start,
                'end' => $end,
                'error' => $e->getMessage(),
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

    private function logInfo(string $action, string $message, array $context = []): void
    {
        $this->logger->log(PayrollLogger::INFO, $message, array_merge($context, [
            'module' => 'payroll.deputy',
            'action' => $action,
        ]));
    }

    private function logWarning(string $action, string $message, array $context = []): void
    {
        $this->logger->log(PayrollLogger::WARNING, $message, array_merge($context, [
            'module' => 'payroll.deputy',
            'action' => $action,
        ]));
    }

    private function logError(string $action, string $message, array $context = []): void
    {
        $this->logger->log(PayrollLogger::ERROR, $message, array_merge($context, [
            'module' => 'payroll.deputy',
            'action' => $action,
        ]));
    }
}
