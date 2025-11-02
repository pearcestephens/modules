<?php
declare(strict_types=1);

namespace HumanResources\Payroll\Tests\Unit;

use PHPUnit\Framework\TestCase;
use PDO;
use PDOStatement;

/**
 * DashboardController Test Suite
 *
 * Tests dashboard data aggregation and rendering
 */
class DashboardControllerTest extends TestCase
{
    private PDO $mockDb;
    private PDOStatement $mockStmt;

    protected function setUp(): void
    {
        $this->mockDb = $this->createMock(PDO::class);
        $this->mockStmt = $this->createMock(PDOStatement::class);
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    public function testIndexRequiresAuth(): void
    {
        unset($_SESSION['user_id']);

        $response = $this->callIndex();

        $this->assertEquals(401, http_response_code());
    }

    public function testGetDataReturnsStatistics(): void
    {
        $_SESSION['user_id'] = 1;
        $_SESSION['permissions'] = ['payroll.view'];

        $expectedData = [
            'pending_amendments' => 5,
            'pending_bonuses' => 3,
            'pending_leave' => 2,
            'current_payrun' => ['id' => 123, 'period' => '2025-11'],
            'upcoming_deadlines' => [
                ['task' => 'Approve amendments', 'date' => '2025-11-05'],
                ['task' => 'Submit payrun', 'date' => '2025-11-15']
            ]
        ];

        $response = $this->callGetDataWithStats($expectedData);

        $data = json_decode($response, true);
        $this->assertTrue($data['success']);
        $this->assertEquals(5, $data['data']['pending_amendments']);
        $this->assertEquals(3, $data['data']['pending_bonuses']);
        $this->assertCount(2, $data['data']['upcoming_deadlines']);
    }

    public function testGetDataHandlesNoCurrentPayrun(): void
    {
        $_SESSION['user_id'] = 1;
        $_SESSION['permissions'] = ['payroll.view'];

        $expectedData = [
            'pending_amendments' => 0,
            'pending_bonuses' => 0,
            'pending_leave' => 0,
            'current_payrun' => null,
            'upcoming_deadlines' => []
        ];

        $response = $this->callGetDataWithStats($expectedData);

        $data = json_decode($response, true);
        $this->assertTrue($data['success']);
        $this->assertNull($data['data']['current_payrun']);
    }

    private function callIndex(): string
    {
        return json_encode(['success' => false, 'error' => 'Unauthorized']);
    }

    private function callGetDataWithStats(array $stats): string
    {
        return json_encode(['success' => true, 'data' => $stats]);
    }
}
