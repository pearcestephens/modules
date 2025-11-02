<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../services/PayrollXeroService.php';

final class PayrollXeroServiceTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->exec('CREATE TABLE payroll_activity_log (
            id INTEGER PRIMARY KEY,
            log_level TEXT,
            category TEXT,
            action TEXT,
            message TEXT,
            details TEXT,
            created_at TEXT
        )');
    }

    public function testMakeReturnsInstance(): void
    {
        $service = PayrollXeroService::make($this->pdo);
        $this->assertInstanceOf(PayrollXeroService::class, $service);
    }

    public function testListEmployeesReturnsArray(): void
    {
        $service = PayrollXeroService::make($this->pdo);
        $result = $service->listEmployees();
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testLogActivityInsertsRow(): void
    {
        $service = PayrollXeroService::make($this->pdo);
        $service->logActivity('test.action', 'Test message', ['key' => 'value']);
        
        $stmt = $this->pdo->query('SELECT COUNT(*) FROM payroll_activity_log');
        $count = (int) $stmt->fetchColumn();
        $this->assertSame(1, $count);
    }
}
