<?php
declare(strict_types=1);

namespace HumanResources\Payroll\Tests\Unit;

use HumanResources\Payroll\Controllers\ReconciliationController;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

class ReconciliationControllerTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private ReconciliationController $controller;
    private MockInterface $db;

    protected function setUp(): void
    {
        parent::setUp();

        if (!isset($_SESSION)) {
            $_SESSION = [];
        }
        $_SESSION['authenticated'] = true;
        $_SESSION['user_id'] = 1;

        $this->controller = new ReconciliationController();
        $this->db = Mockery::mock(\PDO::class);

        $reflector = new \ReflectionObject($this->controller);
        $property = $reflector->getProperty('db');
        $property->setAccessible(true);
        $property->setValue($this->controller, $this->db);
    }

    public function testStartReconciliationValidatesPayrunId(): void
    {
        $_POST = ['payrun_id' => ''];

        ob_start();
        $this->controller->startReconciliation();
        $output = ob_get_clean();

        $this->assertJson($output);
        $data = json_decode($output, true);
        $this->assertFalse($data['success']);
    }

    public function testStartReconciliationCreatesReconciliationRecord(): void
    {
        $_POST = ['payrun_id' => 100];

        $stmt = Mockery::mock(\PDOStatement::class);
        $stmt->shouldReceive('execute');

        $this->db->shouldReceive('prepare')->andReturn($stmt);
        $this->db->shouldReceive('lastInsertId')->andReturn(50);

        ob_start();
        $this->controller->startReconciliation();
        $output = ob_get_clean();

        $this->assertJson($output);
        $data = json_decode($output, true);
        $this->assertTrue($data['success']);
    }

    public function testGetReconciliationStatusReturnsDetails(): void
    {
        $stmt = Mockery::mock(\PDOStatement::class);
        $stmt->shouldReceive('execute')->with([50]);
        $stmt->shouldReceive('fetch')->with(\PDO::FETCH_ASSOC)->andReturn([
            'id' => 50,
            'payrun_id' => 100,
            'status' => 'in_progress',
            'matched_payments' => 450,
            'unmatched_payments' => 25,
            'discrepancies' => 3
        ]);

        $this->db->shouldReceive('prepare')->andReturn($stmt);

        ob_start();
        $this->controller->getReconciliationStatus(50);
        $output = ob_get_clean();

        $this->assertJson($output);
        $data = json_decode($output, true);
        $this->assertTrue($data['success']);
    }

    public function testGetReconciliationStatusHandles404(): void
    {
        $stmt = Mockery::mock(\PDOStatement::class);
        $stmt->shouldReceive('execute')->with([999]);
        $stmt->shouldReceive('fetch')->with(\PDO::FETCH_ASSOC)->andReturn(null);

        $this->db->shouldReceive('prepare')->andReturn($stmt);

        ob_start();
        $this->controller->getReconciliationStatus(999);
        $output = ob_get_clean();

        $this->assertJson($output);
        $data = json_decode($output, true);
        $this->assertFalse($data['success']);
    }

    public function testReportMismatchesReturnsUnmatchedItems(): void
    {
        $stmt = Mockery::mock(\PDOStatement::class);
        $stmt->shouldReceive('execute')->with([50, 50]);
        $stmt->shouldReceive('fetchAll')->with(\PDO::FETCH_ASSOC)->andReturn([
            ['id' => 1, 'amount' => 100.00, 'reason' => 'Payment not cleared'],
            ['id' => 2, 'amount' => 50.00, 'reason' => 'Duplicate entry']
        ]);

        $this->db->shouldReceive('prepare')->andReturn($stmt);

        ob_start();
        $this->controller->reportMismatches(50);
        $output = ob_get_clean();

        $this->assertJson($output);
        $data = json_decode($output, true);
        $this->assertTrue($data['success']);
        $this->assertCount(2, $data['data']);
    }

    public function testCompleteReconciliationUpdatesStatus(): void
    {
        $stmt = Mockery::mock(\PDOStatement::class);
        $stmt->shouldReceive('execute');

        $this->db->shouldReceive('prepare')->andReturn($stmt);

        ob_start();
        $this->controller->completeReconciliation(50);
        $output = ob_get_clean();

        $this->assertJson($output);
        $data = json_decode($output, true);
        $this->assertTrue($data['success']);
    }
}
