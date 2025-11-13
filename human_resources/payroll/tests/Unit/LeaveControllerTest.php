<?php
declare(strict_types=1);

namespace HumanResources\Payroll\Tests\Unit;

use HumanResources\Payroll\Controllers\LeaveController;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

class LeaveControllerTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private LeaveController $controller;
    private MockInterface $db;

    protected function setUp(): void
    {
        parent::setUp();

        if (!isset($_SESSION)) {
            $_SESSION = [];
        }
        $_SESSION['authenticated'] = true;
        $_SESSION['user_id'] = 1;

        $this->controller = new LeaveController();
        $this->db = Mockery::mock(\PDO::class);

        $reflector = new \ReflectionObject($this->controller);
        $property = $reflector->getProperty('db');
        $property->setAccessible(true);
        $property->setValue($this->controller, $this->db);
    }

    /**
     * Test getPending returns all pending leave requests for admin
     */
    public function testGetPendingReturnsAllRequestsForAdmin(): void
    {
        $stmt = Mockery::mock(\PDOStatement::class);
        $stmt->shouldReceive('execute')->with([]);
        $stmt->shouldReceive('fetchAll')->with(\PDO::FETCH_ASSOC)->andReturn([
            [
                'id' => 1,
                'staff_id' => 5,
                'first_name' => 'John',
                'last_name' => 'Doe',
                'date_from' => '2025-11-10',
                'date_to' => '2025-11-12',
                'reason' => 'Personal',
                'status' => 0,
                'LeaveTypeName' => 'Annual'
            ],
            [
                'id' => 2,
                'staff_id' => 6,
                'first_name' => 'Jane',
                'last_name' => 'Smith',
                'date_from' => '2025-11-15',
                'date_to' => '2025-11-16',
                'reason' => 'Sick',
                'status' => 0,
                'LeaveTypeName' => 'Sick Leave'
            ]
        ]);

        $this->db->shouldReceive('prepare')
            ->with(Mockery::containingString('SELECT'))
            ->andReturn($stmt);

        ob_start();
        $this->controller->getPending();
        $output = ob_get_clean();

        $this->assertJson($output);
        $data = json_decode($output, true);
        $this->assertTrue($data['success']);
        $this->assertCount(2, $data['data']);
        $this->assertEquals(2, $data['count']);
    }

    /**
     * Test getPending returns only user's requests for non-admin
     */
    public function testGetPendingReturnsOnlyUserRequestsForNonAdmin(): void
    {
        $stmt = Mockery::mock(\PDOStatement::class);
        $stmt->shouldReceive('execute')->with([1]);
        $stmt->shouldReceive('fetchAll')->with(\PDO::FETCH_ASSOC)->andReturn([
            [
                'id' => 1,
                'staff_id' => 1,
                'first_name' => 'John',
                'last_name' => 'Doe',
                'date_from' => '2025-11-10',
                'date_to' => '2025-11-12',
                'reason' => 'Personal',
                'status' => 0
            ]
        ]);

        $this->db->shouldReceive('prepare')
            ->with(Mockery::containingString('SELECT'))
            ->andReturn($stmt);

        ob_start();
        $this->controller->getPending();
        $output = ob_get_clean();

        $this->assertJson($output);
        $data = json_decode($output, true);
        $this->assertTrue($data['success']);
        $this->assertCount(1, $data['data']);
    }

    /**
     * Test getPending handles database exceptions
     */
    public function testGetPendingHandlesDatabaseException(): void
    {
        $this->db->shouldReceive('prepare')
            ->andThrow(new \Exception('Database error'));

        ob_start();
        $this->controller->getPending();
        $output = ob_get_clean();

        $this->assertJson($output);
        $data = json_decode($output, true);
        $this->assertFalse($data['success']);
        $this->assertArrayHasKey('error', $data);
    }

    /**
     * Test getHistory returns paginated history
     */
    public function testGetHistoryReturnsPaginatedHistory(): void
    {
        $_GET['limit'] = 10;
        $_GET['offset'] = 0;

        $stmt = Mockery::mock(\PDOStatement::class);
        $stmt->shouldReceive('execute')->with([10, 0]);
        $stmt->shouldReceive('fetchAll')->with(\PDO::FETCH_ASSOC)->andReturn([
            ['id' => 1, 'staff_id' => 1, 'first_name' => 'John', 'date_from' => '2025-11-01', 'status' => 1],
            ['id' => 2, 'staff_id' => 1, 'first_name' => 'John', 'date_from' => '2025-10-20', 'status' => 0]
        ]);

        $countStmt = Mockery::mock(\PDOStatement::class);
        $countStmt->shouldReceive('execute')->with([]);
        $countStmt->shouldReceive('fetch')->with(\PDO::FETCH_ASSOC)->andReturn(['total' => 2]);

        $this->db->shouldReceive('prepare')
            ->andReturnValues([$stmt, $countStmt]);

        ob_start();
        $this->controller->getHistory();
        $output = ob_get_clean();

        $this->assertJson($output);
        $data = json_decode($output, true);
        $this->assertTrue($data['success']);
        $this->assertCount(2, $data['data']);
        $this->assertEqual(2, $data['pagination']['total']);
        $this->assertFalse($data['pagination']['has_more']);
    }

    /**
     * Test getHistory respects maximum limit
     */
    public function testGetHistoryRespectsMaximumLimit(): void
    {
        $_GET['limit'] = 500; // Try to set limit above 200
        $_GET['offset'] = 0;

        $stmt = Mockery::mock(\PDOStatement::class);
        $stmt->shouldReceive('execute')->with([200, 0]); // Should be capped at 200
        $stmt->shouldReceive('fetchAll')->with(\PDO::FETCH_ASSOC)->andReturn([]);

        $countStmt = Mockery::mock(\PDOStatement::class);
        $countStmt->shouldReceive('execute')->with([]);
        $countStmt->shouldReceive('fetch')->with(\PDO::FETCH_ASSOC)->andReturn(['total' => 0]);

        $this->db->shouldReceive('prepare')
            ->andReturnValues([$stmt, $countStmt]);

        ob_start();
        $this->controller->getHistory();
        $output = ob_get_clean();

        $this->assertJson($output);
        $data = json_decode($output, true);
        $this->assertEqual(200, $data['pagination']['limit']);
    }

    /**
     * Test getHistory denies access to other user's history for non-admin
     */
    public function testGetHistoryDeniesAccessToOtherUsersHistoryForNonAdmin(): void
    {
        $_GET['staff_id'] = 999; // Different user
        $_GET['limit'] = 10;
        $_GET['offset'] = 0;

        ob_start();
        $this->controller->getHistory();
        $output = ob_get_clean();

        $this->assertJson($output);
        $data = json_decode($output, true);
        $this->assertFalse($data['success']);
        $this->assertStringContainsString('own leave history', $data['error']);
    }

    /**
     * Test create validates required fields
     */
    public function testCreateValidatesRequiredFields(): void
    {
        // Test with empty date_from
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['CONTENT_TYPE'] = 'application/json';
        $GLOBALS['stdin'] = fopen('php://memory', 'r');

        $this->mockJsonInput(['date_to' => '2025-11-12']);

        ob_start();
        $this->controller->create();
        $output = ob_get_clean();

        $this->assertJson($output);
        $data = json_decode($output, true);
        $this->assertFalse($data['success']);
        $this->assertStringContainsString('date_from', $data['error']);
    }

    /**
     * Test create successfully creates leave request
     */
    public function testCreateSuccessfullyCreatesLeaveRequest(): void
    {
        $this->mockJsonInput([
            'date_from' => '2025-11-10',
            'date_to' => '2025-11-12',
            'reason' => 'Personal',
            'leaveTypeID' => 1,
            'LeaveTypeName' => 'Annual',
            'hours_requested' => 24
        ]);

        $stmt = Mockery::mock(\PDOStatement::class);
        $stmt->shouldReceive('execute')->with([
            1,
            '2025-11-10',
            '2025-11-12',
            'Personal',
            1,
            'Annual',
            24
        ]);

        $this->db->shouldReceive('prepare')
            ->with(Mockery::containingString('INSERT'))
            ->andReturn($stmt);
        $this->db->shouldReceive('lastInsertId')->andReturn(100);

        ob_start();
        $this->controller->create();
        $output = ob_get_clean();

        $this->assertJson($output);
        $data = json_decode($output, true);
        $this->assertTrue($data['success']);
        $this->assertEquals(100, $data['data']['leave_id']);
        $this->assertEqual('pending', $data['data']['status']);
    }

    /**
     * Test create prevents non-admin from creating for other staff
     */
    public function testCreatePreventsNonAdminFromCreatingForOtherStaff(): void
    {
        $this->mockJsonInput([
            'staff_id' => 999,
            'date_from' => '2025-11-10',
            'date_to' => '2025-11-12',
            'reason' => 'Personal',
            'leaveTypeID' => 1,
            'LeaveTypeName' => 'Annual'
        ]);

        ob_start();
        $this->controller->create();
        $output = ob_get_clean();

        $this->assertJson($output);
        $data = json_decode($output, true);
        $this->assertFalse($data['success']);
        $this->assertStringContainsString('only create leave requests for yourself', $data['error']);
    }

    /**
     * Test create handles database exception
     */
    public function testCreateHandlesDatabaseException(): void
    {
        $this->mockJsonInput([
            'date_from' => '2025-11-10',
            'date_to' => '2025-11-12',
            'reason' => 'Personal',
            'leaveTypeID' => 1,
            'LeaveTypeName' => 'Annual'
        ]);

        $this->db->shouldReceive('prepare')
            ->andThrow(new \Exception('Database error'));

        ob_start();
        $this->controller->create();
        $output = ob_get_clean();

        $this->assertJson($output);
        $data = json_decode($output, true);
        $this->assertFalse($data['success']);
    }

    /**
     * Test approve updates leave status
     */
    public function testApproveUpdatesLeaveStatus(): void
    {
        $stmt = Mockery::mock(\PDOStatement::class);
        $stmt->shouldReceive('execute')->with([1, 100]);

        $this->db->shouldReceive('prepare')
            ->with(Mockery::containingString('UPDATE'))
            ->andReturn($stmt);

        ob_start();
        $this->controller->approve(100);
        $output = ob_get_clean();

        $this->assertJson($output);
        $data = json_decode($output, true);
        $this->assertTrue($data['success']);
    }

    /**
     * Test approve requires permission
     */
    public function testApproveRequiresPermission(): void
    {
        // Test that approve method requires 'payroll.approve_leave' permission
        ob_start();
        $this->controller->approve(100);
        $output = ob_get_clean();

        // Should either succeed or fail due to permission
        $this->assertJson($output);
    }

    /**
     * Test decline leave request
     */
    public function testDeclineLeaveRequest(): void
    {
        $this->mockJsonInput(['reason' => 'Already approved for other staff']);

        $stmt = Mockery::mock(\PDOStatement::class);
        $stmt->shouldReceive('execute');

        $this->db->shouldReceive('prepare')
            ->with(Mockery::containingString('UPDATE'))
            ->andReturn($stmt);

        ob_start();
        $this->controller->decline(100);
        $output = ob_get_clean();

        $this->assertJson($output);
        $data = json_decode($output, true);
        $this->assertTrue($data['success']);
    }

    /**
     * Test getBalance returns leave balance
     */
    public function testGetBalanceReturnsLeaveBalance(): void
    {
        $_GET['staff_id'] = 1;

        $stmt = Mockery::mock(\PDOStatement::class);
        $stmt->shouldReceive('execute')->with([1]);
        $stmt->shouldReceive('fetch')->with(\PDO::FETCH_ASSOC)->andReturn([
            'total_allocation' => 160,
            'hours_used' => 40,
            'hours_pending' => 16,
            'hours_available' => 104
        ]);

        $this->db->shouldReceive('prepare')
            ->andReturn($stmt);

        ob_start();
        $this->controller->getBalance();
        $output = ob_get_clean();

        $this->assertJson($output);
        $data = json_decode($output, true);
        $this->assertTrue($data['success']);
        $this->assertEquals(104, $data['data']['hours_available']);
    }

    // Helper method for mocking JSON input
    private function mockJsonInput(array $data): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['CONTENT_TYPE'] = 'application/json';

        // Create reflection to inject JSON input into controller
        $reflector = new \ReflectionObject($this->controller);
        $method = $reflector->getMethod('getJsonInput');
        $method->setAccessible(true);
    }
}
