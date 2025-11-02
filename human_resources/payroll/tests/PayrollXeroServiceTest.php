<?php
/**
 * PayrollXeroServiceTest
 * 
 * Unit tests for PayrollXeroService wrapper.
 *
 * @package CIS\Payroll\Tests
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../services/PayrollXeroService.php';

final class PayrollXeroServiceTest extends TestCase
{
    private PDO $db;
    private PayrollXeroService $service;

    protected function setUp(): void
    {
        $this->db = new PDO(
            'mysql:host=127.0.0.1;dbname=jcepnzzkmj;charset=utf8mb4',
            'jcepnzzkmj',
            'wprKh9Jq63',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );

        $this->service = PayrollXeroService::make($this->db);
    }

    public function testMakeFactoryReturnsInstance(): void
    {
        $this->assertInstanceOf(PayrollXeroService::class, $this->service);
    }

    public function testListEmployeesReturnsEmptyArray(): void
    {
        $result = $this->service->listEmployees();
        $this->assertIsArray($result);
        $this->assertEmpty($result, 'Expected empty array from stub implementation');
    }

    public function testLogActivityWritesToDatabase(): void
    {
        $this->db->exec("DELETE FROM payroll_activity_log WHERE category = 'xero' AND action = 'test_action'");

        $this->service->logActivity('test_action', 'Test log message', ['test_key' => 'test_value']);

        $stmt = $this->db->query(
            "SELECT COUNT(*) FROM payroll_activity_log WHERE category = 'xero' AND action = 'test_action'"
        );
        $count = (int) $stmt->fetchColumn();

        $this->assertSame(1, $count, 'Expected exactly one log entry for test action');
    }

    public function testLogActivityWithEmptyContext(): void
    {
        $this->service->logActivity('empty_context_test', 'Message without context');

        $stmt = $this->db->prepare(
            "SELECT details FROM payroll_activity_log WHERE category = 'xero' AND action = 'empty_context_test' ORDER BY id DESC LIMIT 1"
        );
        $stmt->execute();
        $details = $stmt->fetchColumn();

        $this->assertNull($details, 'Expected NULL details for empty context');
    }
}
