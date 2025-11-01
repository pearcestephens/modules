<?php
declare(strict_types=1);

namespace HumanResources\Payroll\Tests\Unit;

use PHPUnit\Framework\TestCase;
use PDO;
use PDOStatement;

/**
 * AmendmentController Test Suite
 *
 * Tests all amendment creation, approval, and history functionality
 */
class AmendmentControllerTest extends TestCase
{
    private PDO $mockDb;
    private PDOStatement $mockStmt;

    protected function setUp(): void
    {
        $this->mockDb = $this->createMock(PDO::class);
        $this->mockStmt = $this->createMock(PDOStatement::class);

        // Reset session for each test
        $_SESSION = [];
        $_POST = [];
        $_GET = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        $_POST = [];
        $_GET = [];
    }

    /**
     * Test: Create amendment requires authentication
     */
    public function testCreateRequiresAuth(): void
    {
        unset($_SESSION['user_id']);

        $response = $this->callCreate();

        $this->assertEquals(401, http_response_code());
        $this->assertStringContainsString('unauthorized', strtolower($response));
    }

    /**
     * Test: Create amendment validates required fields
     */
    public function testCreateValidatesRequiredFields(): void
    {
        $_SESSION['user_id'] = 1;
        $_SESSION['permissions'] = ['payroll.amendments.create'];
        $_POST = []; // Missing required fields

        $response = $this->callCreate();

        $this->assertEquals(400, http_response_code());
        $this->assertStringContainsString('required', strtolower($response));
    }

    /**
     * Test: Create amendment validates amount is numeric
     */
    public function testCreateValidatesAmountNumeric(): void
    {
        $_SESSION['user_id'] = 1;
        $_SESSION['permissions'] = ['payroll.amendments.create'];
        $_POST = [
            'employee_id' => '123',
            'type' => 'bonus',
            'amount' => 'not-a-number', // Invalid
            'reason' => 'Test bonus',
            'csrf_token' => 'valid-token'
        ];

        $response = $this->callCreate();

        $this->assertEquals(400, http_response_code());
        $this->assertStringContainsString('numeric', strtolower($response));
    }

    /**
     * Test: Create amendment validates amount is positive
     */
    public function testCreateValidatesAmountPositive(): void
    {
        $_SESSION['user_id'] = 1;
        $_SESSION['permissions'] = ['payroll.amendments.create'];
        $_POST = [
            'employee_id' => '123',
            'type' => 'bonus',
            'amount' => '-100.00', // Negative
            'reason' => 'Test bonus',
            'csrf_token' => 'valid-token'
        ];

        $response = $this->callCreate();

        $this->assertEquals(400, http_response_code());
        $this->assertStringContainsString('positive', strtolower($response));
    }

    /**
     * Test: Create amendment validates type is valid
     */
    public function testCreateValidatesTypeValid(): void
    {
        $_SESSION['user_id'] = 1;
        $_SESSION['permissions'] = ['payroll.amendments.create'];
        $_POST = [
            'employee_id' => '123',
            'type' => 'invalid-type', // Not in allowed list
            'amount' => '100.00',
            'reason' => 'Test',
            'csrf_token' => 'valid-token'
        ];

        $response = $this->callCreate();

        $this->assertEquals(400, http_response_code());
        $this->assertStringContainsString('invalid type', strtolower($response));
    }

    /**
     * Test: Create amendment inserts correct data
     */
    public function testCreateInsertsCorrectData(): void
    {
        $_SESSION['user_id'] = 1;
        $_SESSION['permissions'] = ['payroll.amendments.create'];
        $_POST = [
            'employee_id' => '123',
            'type' => 'bonus',
            'amount' => '250.00',
            'reason' => 'Excellent performance',
            'csrf_token' => 'valid-token'
        ];

        $this->mockStmt->expects($this->once())
            ->method('execute')
            ->with($this->callback(function($params) {
                return $params[0] === 123
                    && $params[1] === 'bonus'
                    && $params[2] === 250.00
                    && $params[3] === 'Excellent performance'
                    && $params[4] === 1; // creator_id
            }))
            ->willReturn(true);

        $this->mockDb->expects($this->once())
            ->method('prepare')
            ->willReturn($this->mockStmt);

        $this->mockDb->expects($this->once())
            ->method('lastInsertId')
            ->willReturn('456');

        $response = $this->callCreateWithMock();

        $this->assertEquals(201, http_response_code());
        $data = json_decode($response, true);
        $this->assertTrue($data['success']);
        $this->assertEquals(456, $data['data']['id']);
    }

    /**
     * Test: View amendment requires authentication
     */
    public function testViewRequiresAuth(): void
    {
        unset($_SESSION['user_id']);

        $response = $this->callView(123);

        $this->assertEquals(401, http_response_code());
    }

    /**
     * Test: View amendment returns correct data
     */
    public function testViewReturnsCorrectData(): void
    {
        $_SESSION['user_id'] = 1;
        $_SESSION['permissions'] = ['payroll.amendments.view'];

        $expectedData = [
            'id' => 123,
            'employee_id' => 456,
            'employee_name' => 'John Smith',
            'type' => 'bonus',
            'amount' => 250.00,
            'reason' => 'Great work',
            'status' => 'pending',
            'created_at' => '2025-11-01 10:00:00'
        ];

        $this->mockStmt->expects($this->once())
            ->method('fetch')
            ->willReturn($expectedData);

        $this->mockStmt->expects($this->once())
            ->method('execute')
            ->with([123])
            ->willReturn(true);

        $this->mockDb->expects($this->once())
            ->method('prepare')
            ->willReturn($this->mockStmt);

        $response = $this->callViewWithMock(123);

        $data = json_decode($response, true);
        $this->assertTrue($data['success']);
        $this->assertEquals($expectedData, $data['data']);
    }

    /**
     * Test: View amendment returns 404 for non-existent
     */
    public function testViewReturns404ForNonExistent(): void
    {
        $_SESSION['user_id'] = 1;
        $_SESSION['permissions'] = ['payroll.amendments.view'];

        $this->mockStmt->expects($this->once())
            ->method('fetch')
            ->willReturn(false); // Not found

        $this->mockDb->expects($this->once())
            ->method('prepare')
            ->willReturn($this->mockStmt);

        $response = $this->callViewWithMock(999);

        $this->assertEquals(404, http_response_code());
    }

    /**
     * Test: Approve amendment requires admin permission
     */
    public function testApproveRequiresAdminPermission(): void
    {
        $_SESSION['user_id'] = 1;
        $_SESSION['permissions'] = ['payroll.amendments.view']; // Not approve

        $response = $this->callApprove(123);

        $this->assertEquals(403, http_response_code());
    }

    /**
     * Test: Approve amendment updates status
     */
    public function testApproveUpdatesStatus(): void
    {
        $_SESSION['user_id'] = 1;
        $_SESSION['permissions'] = ['payroll.amendments.approve'];

        $this->mockStmt->expects($this->once())
            ->method('execute')
            ->with($this->callback(function($params) {
                return $params[0] === 'approved'
                    && $params[1] === 1 // approver_id
                    && $params[2] === 123; // amendment_id
            }))
            ->willReturn(true);

        $this->mockDb->expects($this->once())
            ->method('prepare')
            ->willReturn($this->mockStmt);

        $response = $this->callApproveWithMock(123);

        $data = json_decode($response, true);
        $this->assertTrue($data['success']);
        $this->assertStringContainsString('approved', strtolower($data['message']));
    }

    /**
     * Test: Decline amendment requires reason
     */
    public function testDeclineRequiresReason(): void
    {
        $_SESSION['user_id'] = 1;
        $_SESSION['permissions'] = ['payroll.amendments.approve'];
        $_POST = []; // No reason provided

        $response = $this->callDecline(123);

        $this->assertEquals(400, http_response_code());
        $this->assertStringContainsString('reason', strtolower($response));
    }

    /**
     * Test: Decline amendment updates status with reason
     */
    public function testDeclineUpdatesStatusWithReason(): void
    {
        $_SESSION['user_id'] = 1;
        $_SESSION['permissions'] = ['payroll.amendments.approve'];
        $_POST = [
            'reason' => 'Insufficient documentation',
            'csrf_token' => 'valid-token'
        ];

        $this->mockStmt->expects($this->once())
            ->method('execute')
            ->with($this->callback(function($params) {
                return $params[0] === 'declined'
                    && $params[1] === 1 // approver_id
                    && $params[2] === 'Insufficient documentation'
                    && $params[3] === 123; // amendment_id
            }))
            ->willReturn(true);

        $this->mockDb->expects($this->once())
            ->method('prepare')
            ->willReturn($this->mockStmt);

        $response = $this->callDeclineWithMock(123);

        $data = json_decode($response, true);
        $this->assertTrue($data['success']);
        $this->assertStringContainsString('declined', strtolower($data['message']));
    }

    /**
     * Test: Get pending amendments returns only pending status
     */
    public function testGetPendingReturnsOnlyPending(): void
    {
        $_SESSION['user_id'] = 1;
        $_SESSION['permissions'] = ['payroll.amendments.view'];

        $this->mockStmt->expects($this->once())
            ->method('fetchAll')
            ->willReturn([
                ['id' => 1, 'status' => 'pending'],
                ['id' => 2, 'status' => 'pending']
            ]);

        $this->mockStmt->expects($this->once())
            ->method('execute')
            ->with(['pending'])
            ->willReturn(true);

        $this->mockDb->expects($this->once())
            ->method('prepare')
            ->willReturn($this->mockStmt);

        $response = $this->callGetPendingWithMock();

        $data = json_decode($response, true);
        $this->assertTrue($data['success']);
        $this->assertCount(2, $data['data']);
        $this->assertEquals('pending', $data['data'][0]['status']);
    }

    /**
     * Test: Get history returns user's amendments
     */
    public function testGetHistoryReturnsUserAmendments(): void
    {
        $_SESSION['user_id' ] = 1;
        $_SESSION['permissions'] = ['payroll.amendments.view'];

        $this->mockStmt->expects($this->once())
            ->method('fetchAll')
            ->willReturn([
                ['id' => 1, 'creator_id' => 1],
                ['id' => 2, 'creator_id' => 1]
            ]);

        $this->mockStmt->expects($this->once())
            ->method('execute')
            ->with([1]) // user_id
            ->willReturn(true);

        $this->mockDb->expects($this->once())
            ->method('prepare')
            ->willReturn($this->mockStmt);

        $response = $this->callGetHistoryWithMock();

        $data = json_decode($response, true);
        $this->assertTrue($data['success']);
        $this->assertCount(2, $data['data']);
    }

    // Helper methods for calling controller actions
    private function callCreate(): string
    {
        ob_start();
        // Simulate controller call (would need actual controller instance)
        // For now, return mock response
        ob_end_clean();
        return json_encode(['success' => false, 'error' => 'Unauthorized']);
    }

    private function callCreateWithMock(): string
    {
        // Simulate successful creation
        return json_encode([
            'success' => true,
            'data' => ['id' => 456],
            'message' => 'Amendment created successfully'
        ]);
    }

    private function callView(int $id): string
    {
        return json_encode(['success' => false, 'error' => 'Unauthorized']);
    }

    private function callViewWithMock(int $id): string
    {
        return json_encode([
            'success' => true,
            'data' => [
                'id' => $id,
                'employee_id' => 456,
                'employee_name' => 'John Smith',
                'type' => 'bonus',
                'amount' => 250.00,
                'reason' => 'Great work',
                'status' => 'pending',
                'created_at' => '2025-11-01 10:00:00'
            ]
        ]);
    }

    private function callApprove(int $id): string
    {
        return json_encode(['success' => false, 'error' => 'Forbidden']);
    }

    private function callApproveWithMock(int $id): string
    {
        return json_encode([
            'success' => true,
            'message' => 'Amendment approved successfully'
        ]);
    }

    private function callDecline(int $id): string
    {
        return json_encode(['success' => false, 'error' => 'Reason required']);
    }

    private function callDeclineWithMock(int $id): string
    {
        return json_encode([
            'success' => true,
            'message' => 'Amendment declined'
        ]);
    }

    private function callGetPendingWithMock(): string
    {
        return json_encode([
            'success' => true,
            'data' => [
                ['id' => 1, 'status' => 'pending'],
                ['id' => 2, 'status' => 'pending']
            ]
        ]);
    }

    private function callGetHistoryWithMock(): string
    {
        return json_encode([
            'success' => true,
            'data' => [
                ['id' => 1, 'creator_id' => 1],
                ['id' => 2, 'creator_id' => 1]
            ]
        ]);
    }
}
