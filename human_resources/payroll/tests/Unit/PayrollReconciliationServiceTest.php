<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../services/ReconciliationService.php';

use HumanResources\Payroll\Services\ReconciliationService;

final class PayrollReconciliationServiceTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
    }

    public function testCompareDeputyToXeroReturnsArray(): void
    {
        $service = new ReconciliationService($this->pdo);
        $result = $service->compareDeputyToXero('2025-11-01', '2025-11-30');

        $this->assertIsArray($result);
    }

    public function testServiceInstantiates(): void
    {
        $service = new ReconciliationService($this->pdo);
        $this->assertInstanceOf(ReconciliationService::class, $service);
    }
}
