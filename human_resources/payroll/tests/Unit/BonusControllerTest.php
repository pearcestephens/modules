<?php
declare(strict_types=1);

namespace HumanResources\Payroll\Tests\Unit;

use HumanResources\Payroll\Controllers\BonusController;
use HumanResources\Payroll\Services\BonusService;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

class BonusControllerTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private BonusController $controller;
    private MockInterface $db;
    private MockInterface $bonusService;

    protected function setUp(): void
    {
        parent::setUp();

        if (!isset($_SESSION)) {
            $_SESSION = [];
        }
        $_SESSION['authenticated'] = true;
        $_SESSION['user_id'] = 1;

        $this->db = Mockery::mock(\PDO::class);
        $this->bonusService = Mockery::mock(BonusService::class);
        $this->controller = new BonusController($this->db);

        $reflector = new \ReflectionObject($this->controller);
        $property = $reflector->getProperty('bonusService');
        $property->setAccessible(true);
        $property->setValue($this->controller, $this->bonusService);
    }

    /**
     * Test getPending returns all pending bonuses for admin
     */
    public function testGetPendingReturnsAllBonusesForAdmin(): void
    {
        $stmt = Mockery::mock(\PDOStatement::class);
        $stmt->shouldReceive('execute')->with([]);
        $stmt->shouldReceive('fetchAll')->with(\PDO::FETCH_ASSOC)->andReturn([
            [
                'id' => 1,
                'staff_id' => 5,
                'first_name' => 'John',
                'bonus_amount' => 150.00,
                'approved' => 0,
                'created_at' => '2025-11-01'
            ]
        ]);

        $this->db->shouldReceive('prepare')
            ->andReturn($stmt);

        $this->bonusService->shouldReceive('getUnpaidBonusSummary')
            ->with(5)
            ->andReturn(['vape_drops' => 2, 'google_reviews' => 5]);

        ob_start();
        $this->controller->getPending();
        $output = ob_get_clean();

        $this->assertJson($output);
        $data = json_decode($output, true);
        $this->assertTrue($data['success']);
        $this->assertCount(1, $data['data']);
    }

    /**
     * Test getPending returns only user's bonuses for non-admin
     */
    public function testGetPendingReturnsOnlyUserBonusesForNonAdmin(): void
    {
        $stmt = Mockery::mock(\PDOStatement::class);
        $stmt->shouldReceive('execute')->with([1]);
        $stmt->shouldReceive('fetchAll')->with(\PDO::FETCH_ASSOC)->andReturn([
            [
                'id' => 1,
                'staff_id' => 1,
                'first_name' => 'John',
                'bonus_amount' => 100.00,
                'approved' => 0
            ]
        ]);

        $this->db->shouldReceive('prepare')
            ->andReturn($stmt);

        $this->bonusService->shouldReceive('getUnpaidBonusSummary')
            ->andReturn([]);

        ob_start();
        $this->controller->getPending();
        $output = ob_get_clean();

        $this->assertJson($output);
        $data = json_decode($output, true);
        $this->assertTrue($data['success']);
    }

    /**
     * Test getPending handles exceptions gracefully
     */
    public function testGetPendingHandlesExceptionsGracefully(): void
    {
        $this->db->shouldReceive('prepare')
            ->andThrow(new \Exception('Database error'));

        ob_start();
        $this->controller->getPending();
        $output = ob_get_clean();

        $this->assertJson($output);
        $data = json_decode($output, true);
        $this->assertFalse($data['success']);
    }

    /**
     * Test getHistory returns paginated bonus history
     */
    public function testGetHistoryReturnsPaginatedHistory(): void
    {
        $_GET['limit'] = 25;
        $_GET['offset'] = 0;

        $stmt = Mockery::mock(\PDOStatement::class);
        $stmt->shouldReceive('execute')->with([25, 0]);
        $stmt->shouldReceive('fetchAll')->with(\PDO::FETCH_ASSOC)->andReturn([
            ['id' => 1, 'staff_id' => 1, 'bonus_amount' => 100.00, 'approved' => 1]
        ]);

        $countStmt = Mockery::mock(\PDOStatement::class);
        $countStmt->shouldReceive('execute')->with([]);
        $countStmt->shouldReceive('fetch')->with(\PDO::FETCH_ASSOC)->andReturn(['total' => 1]);

        $this->db->shouldReceive('prepare')
            ->andReturnValues([$stmt, $countStmt]);

        ob_start();
        $this->controller->getHistory();
        $output = ob_get_clean();

        $this->assertJson($output);
        $data = json_decode($output, true);
        $this->assertTrue($data['success']);
    }

    /**
     * Test getHistory enforces maximum limit
     */
    public function testGetHistoryEnforcesMaximumLimit(): void
    {
        $_GET['limit'] = 300;
        $_GET['offset'] = 0;

        $stmt = Mockery::mock(\PDOStatement::class);
        $stmt->shouldReceive('execute')->with([200, 0]); // Capped at 200
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
    }

    /**
     * Test createMonthlyBonus validates required fields
     */
    public function testCreateMonthlyBonusValidatesRequiredFields(): void
    {
        $_POST = ['bonus_amount' => ''];  // Empty required field

        ob_start();
        $this->controller->createMonthlyBonus();
        $output = ob_get_clean();

        $this->assertJson($output);
        $data = json_decode($output, true);
        $this->assertFalse($data['success']);
    }

    /**
     * Test createMonthlyBonus creates bonus successfully
     */
    public function testCreateMonthlyBonusCreatesSuccessfully(): void
    {
        $_POST = [
            'staff_id' => 5,
            'bonus_amount' => 250.00,
            'reason' => 'Excellent performance',
            'month' => '2025-11'
        ];

        $stmt = Mockery::mock(\PDOStatement::class);
        $stmt->shouldReceive('execute');

        $this->db->shouldReceive('prepare')
            ->andReturn($stmt);
        $this->db->shouldReceive('lastInsertId')->andReturn(42);

        ob_start();
        $this->controller->createMonthlyBonus();
        $output = ob_get_clean();

        $this->assertJson($output);
        $data = json_decode($output, true);
        $this->assertTrue($data['success']);
        $this->assertEquals(42, $data['data']['bonus_id']);
    }

    /**
     * Test approveBonus updates approval status
     */
    public function testApproveBonusUpdatesApprovalStatus(): void
    {
        $stmt = Mockery::mock(\PDOStatement::class);
        $stmt->shouldReceive('execute');

        $this->db->shouldReceive('prepare')
            ->andReturn($stmt);

        ob_start();
        $this->controller->approveBonus(100);
        $output = ob_get_clean();

        $this->assertJson($output);
        $data = json_decode($output, true);
        $this->assertTrue($data['success']);
    }

    /**
     * Test declineBonus rejects bonus with reason
     */
    public function testDeclineBonusRejectsWithReason(): void
    {
        $_POST = ['decline_reason' => 'Insufficient evidence'];

        $stmt = Mockery::mock(\PDOStatement::class);
        $stmt->shouldReceive('execute');

        $this->db->shouldReceive('prepare')
            ->andReturn($stmt);

        ob_start();
        $this->controller->declineBonus(100);
        $output = ob_get_clean();

        $this->assertJson($output);
        $data = json_decode($output, true);
        $this->assertTrue($data['success']);
    }

    /**
     * Test getBonus retrieves specific bonus details
     */
    public function testGetBonusRetrievesDetails(): void
    {
        $stmt = Mockery::mock(\PDOStatement::class);
        $stmt->shouldReceive('execute')->with([100]);
        $stmt->shouldReceive('fetch')->with(\PDO::FETCH_ASSOC)->andReturn([
            'id' => 100,
            'staff_id' => 5,
            'bonus_amount' => 200.00,
            'approved' => 1
        ]);

        $this->db->shouldReceive('prepare')
            ->andReturn($stmt);

        ob_start();
        $this->controller->getBonus(100);
        $output = ob_get_clean();

        $this->assertJson($output);
        $data = json_decode($output, true);
        $this->assertTrue($data['success']);
        $this->assertEquals(200.00, $data['data']['bonus_amount']);
    }

    /**
     * Test getBonus returns 404 for non-existent bonus
     */
    public function testGetBonusReturns404ForNonExistent(): void
    {
        $stmt = Mockery::mock(\PDOStatement::class);
        $stmt->shouldReceive('execute')->with([999]);
        $stmt->shouldReceive('fetch')->with(\PDO::FETCH_ASSOC)->andReturn(null);

        $this->db->shouldReceive('prepare')
            ->andReturn($stmt);

        ob_start();
        $this->controller->getBonus(999);
        $output = ob_get_clean();

        $this->assertJson($output);
        $data = json_decode($output, true);
        $this->assertFalse($data['success']);
    }
}
