<?php
/**
 * Integration Test: Payroll Deputy Service
 *
 * End-to-end validation of Deputy service with rate-limit telemetry.
 * Requires Deputy API credentials in environment.
 *
 * @package HumanResources\Payroll\Tests\Integration
 */

declare(strict_types=1);

namespace HumanResources\Payroll\Tests\Integration;

use PHPUnit\Framework\TestCase;
use PDO;
use HumanResources\Payroll\Services\PayrollDeputyService;

final class PayrollDeputyServiceIntegrationTest extends TestCase
{
    private PDO $db;

    protected function setUp(): void
    {
        if (!class_exists('Deputy')) {
            $this->markTestSkipped('Deputy class not available');
        }

        $this->db = new PDO('sqlite::memory:');
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->db->exec('
            CREATE TABLE payroll_activity_log (
              id INTEGER PRIMARY KEY AUTOINCREMENT,
              log_level TEXT NOT NULL,
              category TEXT NOT NULL,
              action TEXT NOT NULL,
              message TEXT,
              details TEXT,
              created_at TEXT DEFAULT CURRENT_TIMESTAMP
            )
        ');

        $this->db->exec('
            CREATE TABLE payroll_rate_limits (
              id INTEGER PRIMARY KEY AUTOINCREMENT,
              service TEXT NOT NULL,
              endpoint TEXT NOT NULL,
              http_status INTEGER NOT NULL,
              retry_after_sec INTEGER,
              request_id TEXT,
              payload_hash TEXT,
              occurred_at TEXT DEFAULT CURRENT_TIMESTAMP
            )
        ');
    }

    public function testFetchTimesheetsLogsActivity(): void
    {
        $service = PayrollDeputyService::make($this->db);

        try {
            $result = $service->fetchTimesheets('2025-01-01', '2025-01-07');
            $this->assertIsArray($result);
        } catch (\Exception $e) {
            $this->markTestSkipped('Deputy API unavailable: ' . $e->getMessage());
        }

        $stmt = $this->db->query('SELECT COUNT(*) as cnt FROM payroll_activity_log WHERE category = ?');
        $stmt->execute(['deputy']);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertGreaterThan(0, (int)$row['cnt']);
    }
}
