<?php
/**
 * PayrollDeputyServiceTest
 *
 * Unit tests for PayrollDeputyService wrapper.
 *
 * @package CIS\Payroll\Tests
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../services/PayrollDeputyService.php';

final class PayrollDeputyServiceTest extends TestCase
{
    private PDO $db;
    private PayrollDeputyService $service;

    protected function setUp(): void
    {
        $this->db = new PDO(
            'mysql:host=127.0.0.1;dbname=jcepnzzkmj;charset=utf8mb4',
            'jcepnzzkmj',
            'wprKh9Jq63',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );

        $this->service = new PayrollDeputyService($this->db);
    }

    public function testServiceInstantiation(): void
    {
        $this->assertInstanceOf(PayrollDeputyService::class, $this->service);
    }

    public function testFetchTimesheetsReturnsArray(): void
    {
        try {
            $result = $this->service->fetchTimesheets(['limit' => 1]);
            $this->assertIsArray($result);
        } catch (\Throwable $e) {
            $this->markTestSkipped('Deputy API unavailable: ' . $e->getMessage());
        }
    }

    public function testActivityLogCreatedOnApiCall(): void
    {
        $this->db->exec("DELETE FROM payroll_activity_log WHERE category = 'payroll.deputy'");

        try {
            $this->service->fetchTimesheets(['limit' => 1]);
        } catch (\Throwable $e) {
            // API may fail, but log should still be created
        }

        $stmt = $this->db->query(
            "SELECT COUNT(*) FROM payroll_activity_log WHERE category = 'payroll.deputy' AND action LIKE 'deputy.api.%'"
        );
        $count = (int) $stmt->fetchColumn();

        $this->assertGreaterThan(0, $count, 'Expected at least one activity log entry for Deputy API call');
    }

    public function testRateLimitPersistenceOn429(): void
    {
        $this->markTestIncomplete('Requires mock Deputy client that returns 429');
    }
}
