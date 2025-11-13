<?php
declare(strict_types=1);

namespace HumanResources\Payroll\Tests\Unit;

use HumanResources\Payroll\Controllers\VendPaymentController;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

class VendPaymentControllerTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private VendPaymentController $controller;
    private MockInterface $db;

    protected function setUp(): void
    {
        parent::setUp();

        if (!isset($_SESSION)) {
            $_SESSION = [];
        }
        $_SESSION['authenticated'] = true;
        $_SESSION['user_id'] = 1;

        $this->controller = new VendPaymentController();
        $this->db = Mockery::mock(\PDO::class);

        $reflector = new \ReflectionObject($this->controller);
        $property = $reflector->getProperty('db');
        $property->setAccessible(true);
        $property->setValue($this->controller, $this->db);
    }

    public function testGetPendingPaymentsReturnsListForAdmin(): void
    {
        $stmt = Mockery::mock(\PDOStatement::class);
        $stmt->shouldReceive('execute')->with([]);
        $stmt->shouldReceive('fetchAll')->with(\PDO::FETCH_ASSOC)->andReturn([
            [
                'id' => 1,
                'staff_id' => 5,
                'first_name' => 'John',
                'payment_amount' => 500.00,
                'account_type' => 'vend_commission',
                'status' => 'pending'
            ]
        ]);

        $this->db->shouldReceive('prepare')->andReturn($stmt);

        ob_start();
        $this->controller->getPendingPayments();
        $output = ob_get_clean();

        $this->assertJson($output);
        $data = json_decode($output, true);
        $this->assertTrue($data['success']);
    }

    public function testRequestPaymentValidatesRequiredFields(): void
    {
        $_POST = ['amount' => ''];

        ob_start();
        $this->controller->requestPayment();
        $output = ob_get_clean();

        $this->assertJson($output);
        $data = json_decode($output, true);
        $this->assertFalse($data['success']);
    }

    public function testRequestPaymentCreatesPaymentRequest(): void
    {
        $_POST = [
            'amount' => 250.00,
            'account_type' => 'vend_commission',
            'reason' => 'November commission'
        ];

        $stmt = Mockery::mock(\PDOStatement::class);
        $stmt->shouldReceive('execute');

        $this->db->shouldReceive('prepare')->andReturn($stmt);
        $this->db->shouldReceive('lastInsertId')->andReturn(1);

        ob_start();
        $this->controller->requestPayment();
        $output = ob_get_clean();

        $this->assertJson($output);
        $data = json_decode($output, true);
        $this->assertTrue($data['success']);
    }

    public function testApprovePaymentUpdatesStatus(): void
    {
        $stmt = Mockery::mock(\PDOStatement::class);
        $stmt->shouldReceive('execute');

        $this->db->shouldReceive('prepare')->andReturn($stmt);

        ob_start();
        $this->controller->approvePayment(1);
        $output = ob_get_clean();

        $this->assertJson($output);
        $data = json_decode($output, true);
        $this->assertTrue($data['success']);
    }

    public function testRejectPaymentWithReason(): void
    {
        $_POST = ['rejection_reason' => 'Insufficient balance'];

        $stmt = Mockery::mock(\PDOStatement::class);
        $stmt->shouldReceive('execute');

        $this->db->shouldReceive('prepare')->andReturn($stmt);

        ob_start();
        $this->controller->rejectPayment(1);
        $output = ob_get_clean();

        $this->assertJson($output);
        $data = json_decode($output, true);
        $this->assertTrue($data['success']);
    }

    public function testGetPaymentRetrievesDetails(): void
    {
        $stmt = Mockery::mock(\PDOStatement::class);
        $stmt->shouldReceive('execute')->with([1]);
        $stmt->shouldReceive('fetch')->with(\PDO::FETCH_ASSOC)->andReturn([
            'id' => 1,
            'staff_id' => 5,
            'payment_amount' => 250.00,
            'status' => 'pending'
        ]);

        $this->db->shouldReceive('prepare')->andReturn($stmt);

        ob_start();
        $this->controller->getPayment(1);
        $output = ob_get_clean();

        $this->assertJson($output);
        $data = json_decode($output, true);
        $this->assertTrue($data['success']);
    }
}
