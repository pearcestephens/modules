<?php
/**
 * Unit Test: PayrollAuthAuditService
 *
 * Validates auth audit log functionality.
 *
 * @package HumanResources\Payroll\Tests\Unit
 */

declare(strict_types=1);

namespace HumanResources\Payroll\Tests\Unit;

use PHPUnit\Framework\TestCase;
use PDO;
use HumanResources\Payroll\Services\PayrollAuthAuditService;

final class PayrollAuthAuditServiceTest extends TestCase
{
    private PDO $db;

    protected function setUp(): void
    {
        $this->db = new PDO('sqlite::memory:');
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->db->exec('
            CREATE TABLE payroll_auth_audit_log (
              id INTEGER PRIMARY KEY AUTOINCREMENT,
              timestamp TEXT DEFAULT CURRENT_TIMESTAMP,
              actor TEXT NOT NULL,
              action TEXT NOT NULL,
              flag_before INTEGER NOT NULL,
              flag_after INTEGER NOT NULL,
              ip_address TEXT
            )
        ');
    }

    public function testRecordToggleInsertsRow(): void
    {
        $service = PayrollAuthAuditService::make($this->db);

        $service->recordToggle('admin', 'enable', false, true, '192.168.1.1');

        $stmt = $this->db->query('SELECT * FROM payroll_auth_audit_log ORDER BY id DESC LIMIT 1');
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertNotFalse($row);
        $this->assertSame('admin', $row['actor']);
        $this->assertSame('enable', $row['action']);
        $this->assertSame(0, (int)$row['flag_before']);
        $this->assertSame(1, (int)$row['flag_after']);
        $this->assertSame('192.168.1.1', $row['ip_address']);
    }

    public function testRecordToggleWithNullIpAddress(): void
    {
        $service = PayrollAuthAuditService::make($this->db);

        $service->recordToggle('system', 'disable', true, false, null);

        $stmt = $this->db->query('SELECT * FROM payroll_auth_audit_log ORDER BY id DESC LIMIT 1');
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertNotFalse($row);
        $this->assertSame('system', $row['actor']);
        $this->assertSame('disable', $row['action']);
        $this->assertNull($row['ip_address']);
    }

    public function testGetRecentEntriesReturnsArray(): void
    {
        $service = PayrollAuthAuditService::make($this->db);

        $service->recordToggle('user1', 'enable', false, true);
        $service->recordToggle('user2', 'disable', true, false);
        $service->recordToggle('user3', 'enable', false, true);

        $entries = $service->getRecentEntries(2);

        $this->assertIsArray($entries);
        $this->assertCount(2, $entries);

        // Verify structure
        foreach ($entries as $entry) {
            $this->assertArrayHasKey('actor', $entry);
            $this->assertArrayHasKey('timestamp', $entry);
        }
    }

    public function testGetEntriesByActorFiltersCorrectly(): void
    {
        $service = PayrollAuthAuditService::make($this->db);

        $service->recordToggle('admin', 'enable', false, true);
        $service->recordToggle('user', 'enable', false, true);
        $service->recordToggle('admin', 'disable', true, false);

        $entries = $service->getEntriesByActor('admin', 10);

        $this->assertCount(2, $entries);
        $this->assertSame('admin', $entries[0]['actor']);
        $this->assertSame('admin', $entries[1]['actor']);
    }
}
