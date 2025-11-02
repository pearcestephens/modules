<?php
/**
 * HttpRateLimitReporterTest
 *
 * Unit tests for HttpRateLimitReporter telemetry service.
 *
 * @package CIS\Payroll\Tests
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../services/HttpRateLimitReporter.php';

use HumanResources\Payroll\Services\HttpRateLimitReporter;

final class HttpRateLimitReporterTest extends TestCase
{
    private PDO $db;
    private HttpRateLimitReporter $reporter;

    protected function setUp(): void
    {
        $this->db = new PDO(
            'mysql:host=127.0.0.1;dbname=jcepnzzkmj;charset=utf8mb4',
            'jcepnzzkmj',
            'wprKh9Jq63',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );

        $this->reporter = new HttpRateLimitReporter($this->db);

        $this->db->exec("DELETE FROM payroll_rate_limits WHERE service IN ('test_service', 'deputy', 'xero')");
    }

    public function testRecordSingleRateLimitEvent(): void
    {
        $this->reporter->record(
            service: 'test_service',
            endpoint: '/api/test',
            status: 429,
            retryAfter: 60,
            requestId: 'req_test_123'
        );

        $stmt = $this->db->query(
            "SELECT COUNT(*) FROM payroll_rate_limits WHERE service = 'test_service' AND endpoint = '/api/test'"
        );
        $count = (int) $stmt->fetchColumn();

        $this->assertSame(1, $count, 'Expected exactly one rate-limit event recorded');
    }

    public function testRecordMultipleRateLimitEvents(): void
    {
        $events = [
            ['service' => 'deputy', 'endpoint' => '/timesheets', 'status' => 429, 'retry_after' => 30],
            ['service' => 'xero', 'endpoint' => '/employees', 'status' => 429, 'retry_after' => 60],
        ];

        $this->reporter->recordMultiple($events);

        $stmt = $this->db->query(
            "SELECT COUNT(*) FROM payroll_rate_limits WHERE service IN ('deputy', 'xero')"
        );
        $count = (int) $stmt->fetchColumn();

        $this->assertSame(2, $count, 'Expected two rate-limit events recorded');
    }

    public function testRecordMultipleWithEmptyArray(): void
    {
        $beforeCount = (int) $this->db->query("SELECT COUNT(*) FROM payroll_rate_limits")->fetchColumn();

        $this->reporter->recordMultiple([]);

        $afterCount = (int) $this->db->query("SELECT COUNT(*) FROM payroll_rate_limits")->fetchColumn();

        $this->assertSame($beforeCount, $afterCount, 'Expected no new records for empty input');
    }

    public function testRecordWithNullOptionalFields(): void
    {
        $this->reporter->record(
            service: 'test_service',
            endpoint: '/minimal',
            status: 429,
            retryAfter: null,
            requestId: null,
            payloadHash: null
        );

        $stmt = $this->db->prepare(
            "SELECT retry_after_sec, request_id, payload_hash FROM payroll_rate_limits
             WHERE service = 'test_service' AND endpoint = '/minimal'"
        );
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertNull($row['retry_after_sec']);
        $this->assertNull($row['request_id']);
        $this->assertNull($row['payload_hash']);
    }
}
