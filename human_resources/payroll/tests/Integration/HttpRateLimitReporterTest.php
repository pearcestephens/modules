<?php
/**
 * Integration Test: HttpRateLimitReporter
 *
 * Validates 429 telemetry persistence via HttpRateLimitReporter.
 *
 * @package HumanResources\Payroll\Tests\Integration
 */

declare(strict_types=1);

namespace HumanResources\Payroll\Tests\Integration;

use PHPUnit\Framework\TestCase;
use PDO;
use HumanResources\Payroll\Services\HttpRateLimitReporter;

final class HttpRateLimitReporterTest extends TestCase
{
    private PDO $db;

    protected function setUp(): void
    {
        $this->db = new PDO('sqlite::memory:');
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

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

    public function testRecordInserts429Event(): void
    {
        $reporter = new HttpRateLimitReporter($this->db);
        $reporter->record('deputy', '/timesheets', 429, 60, 'req-abc-123', 'hash-xyz');

        $stmt = $this->db->query('SELECT * FROM payroll_rate_limits ORDER BY id DESC LIMIT 1');
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertNotFalse($row);
        $this->assertSame('deputy', $row['service']);
        $this->assertSame('/timesheets', $row['endpoint']);
        $this->assertSame(429, (int)$row['http_status']);
        $this->assertSame(60, (int)$row['retry_after_sec']);
        $this->assertSame('req-abc-123', $row['request_id']);
        $this->assertSame('hash-xyz', $row['payload_hash']);
    }

    public function testRecordHandlesNullRetryAfter(): void
    {
        $reporter = new HttpRateLimitReporter($this->db);
        $reporter->record('xero', '/employees', 503, null);

        $stmt = $this->db->query('SELECT * FROM payroll_rate_limits ORDER BY id DESC LIMIT 1');
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertNotFalse($row);
        $this->assertNull($row['retry_after_sec']);
    }
}
