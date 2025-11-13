<?php
declare(strict_types=1);

namespace HumanResources\Payroll\Tests\Unit;

use HumanResources\Payroll\Controllers\WageDiscrepancyController;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

class WageDiscrepancyControllerTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private WageDiscrepancyController $controller;
    private MockInterface $db;

    protected function setUp(): void
    {
        parent::setUp();

        if (!isset($_SESSION)) {
            $_SESSION = [];
        }
        $_SESSION['authenticated'] = true;
        $_SESSION['user_id'] = 1;

        $this->controller = new WageDiscrepancyController();
        $this->db = Mockery::mock(\PDO::class);

        $reflector = new \ReflectionObject($this->controller);
        $property = $reflector->getProperty('db');
        $property->setAccessible(true);
        $property->setValue($this->controller, $this->db);
    }

    public function testGetPendingDiscrepanciesReturnsListForAdmin(): void
    {
        $stmt = Mockery::mock(\PDOStatement::class);
        $stmt->shouldReceive('execute')->with([]);
        $stmt->shouldReceive('fetchAll')->with(\PDO::FETCH_ASSOC)->andReturn([
            [
                'id' => 1,
                'staff_id' => 5,
                'first_name' => 'John',
                'discrepancy_amount' => 50.00,
                'reason' => 'Overtime not recorded',
                'status' => 'pending',
                'priority' => 'normal'
            ]
        ]);

        $this->db->shouldReceive('prepare')->andReturn($stmt);

        ob_start();
        $this->controller->getPendingDiscrepancies();
        $output = ob_get_clean();

        $this->assertJson($output);
        $data = json_decode($output, true);
        $this->assertTrue($data['success']);
    }

    public function testReportDiscrepancyValidatesRequiredFields(): void
    {
        $_POST = ['amount' => ''];

        ob_start();
        $this->controller->reportDiscrepancy();
        $output = ob_get_clean();

        $this->assertJson($output);
        $data = json_decode($output, true);
        $this->assertFalse($data['success']);
    }

    public function testReportDiscrepancyCreatesSuccessfully(): void
    {
        $_POST = [
            'amount' => 75.50,
            'reason' => 'Missing weekend premium',
            'affected_date' => '2025-10-25'
        ];

        $stmt = Mockery::mock(\PDOStatement::class);
        $stmt->shouldReceive('execute');

        $this->db->shouldReceive('prepare')->andReturn($stmt);
        $this->db->shouldReceive('lastInsertId')->andReturn(10);

        ob_start();
        $this->controller->reportDiscrepancy();
        $output = ob_get_clean();

        $this->assertJson($output);
        $data = json_decode($output, true);
        $this->assertTrue($data['success']);
    }

    public function testResolveDiscrepancyUpdatesStatus(): void
    {
        $_POST = ['resolution' => 'Approved - payment adjustment issued'];

        $stmt = Mockery::mock(\PDOStatement::class);
        $stmt->shouldReceive('execute');

        $this->db->shouldReceive('prepare')->andReturn($stmt);

        ob_start();
        $this->controller->resolveDiscrepancy(10);
        $output = ob_get_clean();

        $this->assertJson($output);
        $data = json_decode($output, true);
        $this->assertTrue($data['success']);
    }

    public function testGetDiscrepancyRetrievesDetails(): void
    {
        $stmt = Mockery::mock(\PDOStatement::class);
        $stmt->shouldReceive('execute')->with([10]);
        $stmt->shouldReceive('fetch')->with(\PDO::FETCH_ASSOC)->andReturn([
            'id' => 10,
            'staff_id' => 5,
            'discrepancy_amount' => 75.50,
            'status' => 'pending'
        ]);

        $this->db->shouldReceive('prepare')->andReturn($stmt);

        ob_start();
        $this->controller->getDiscrepancy(10);
        $output = ob_get_clean();

        $this->assertJson($output);
        $data = json_decode($output, true);
        $this->assertTrue($data['success']);
    }
}
