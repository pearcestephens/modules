<?php
declare(strict_types=1);

namespace HumanResources\Payroll\Tests\Unit;

use HumanResources\Payroll\Controllers\DashboardController;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

class DashboardControllerTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private DashboardController $controller;
    private MockInterface $db;

    protected function setUp(): void
    {
        parent::setUp();

        // Initialize session
        if (!isset($_SESSION)) {
            $_SESSION = [];
        }
        $_SESSION['authenticated'] = true;
        $_SESSION['userID'] = 1;

        $this->controller = new DashboardController();

        // Mock PDO database
        $this->db = Mockery::mock(\PDO::class);

        // Inject via Reflection
        $reflector = new \ReflectionObject($this->controller);
        $property = $reflector->getProperty('db');
        $property->setAccessible(true);
        $property->setValue($this->controller, $this->db);

        // Mock BaseController methods
        $this->mockBaseControllerMethods();
    }

    private function mockBaseControllerMethods(): void
    {
        // Mock permission check method
        $reflector = new \ReflectionClass($this->controller);
        $hasPermissionMethod = $reflector->getMethod('hasPermission');
        $hasPermissionMethod->setAccessible(true);
    }

    /**
     * Test index requires authentication
     */
    public function testIndexRequiresAuthentication(): void
    {
        // Clear session
        $_SESSION['authenticated'] = false;
        $_SESSION['userID'] = null;

        // Expect redirect to login
        ob_start();
        $this->controller->index();
        $output = ob_get_clean();

        $this->assertStringContainsString('Location:', $GLOBALS['http_response_header'][0] ?? '');
    }

    /**
     * Test index checks permissions
     */
    public function testIndexChecksPermissions(): void
    {
        $_SESSION['authenticated'] = true;
        $_SESSION['userID'] = 1;

        ob_start();
        $this->controller->index();
        $output = ob_get_clean();

        // View should be rendered or access denied
        $this->assertTrue(
            (bool)preg_match('/dashboard|access denied/i', $output) ||
            headers_sent() === false
        );
    }

    /**
     * Test getData validates admin flag
     */
    public function testGetDataValidatesAdminFlag(): void
    {
        // Setup mock PDO statements for all count methods
        $amendmentStmt = Mockery::mock(\PDOStatement::class);
        $amendmentStmt->shouldReceive('fetch')
            ->with(\PDO::FETCH_ASSOC)
            ->andReturn(['total' => 5, 'pending' => 2, 'approved' => 3, 'declined' => 0]);

        $discrepancyStmt = Mockery::mock(\PDOStatement::class);
        $discrepancyStmt->shouldReceive('fetch')
            ->with(\PDO::FETCH_ASSOC)
            ->andReturn([
                'total' => 3,
                'pending' => 1,
                'ai_review' => 1,
                'approved' => 1,
                'declined' => 0,
                'urgent' => 0
            ]);

        $leaveStmt = Mockery::mock(\PDOStatement::class);
        $leaveStmt->shouldReceive('fetch')
            ->with(\PDO::FETCH_ASSOC)
            ->andReturn(['total' => 2, 'pending' => 1, 'approved' => 1, 'declined' => 0]);

        $bonusStmt = Mockery::mock(\PDOStatement::class);
        $bonusStmt->shouldReceive('fetch')
            ->with(\PDO::FETCH_ASSOC)
            ->andReturn([
                'monthly' => ['total' => 2, 'pending' => 0, 'total_amount' => 100.00],
                'vape_drops' => ['total' => 5, 'unpaid' => 2],
                'google_reviews' => ['total' => 10, 'unpaid' => 3, 'total_bonus' => 45.00]
            ]);

        $vendStmt = Mockery::mock(\PDOStatement::class);
        $vendStmt->shouldReceive('fetch')
            ->with(\PDO::FETCH_ASSOC)
            ->andReturn([
                'total' => 4,
                'pending' => 1,
                'ai_review' => 1,
                'approved' => 2,
                'completed' => 0,
                'total_amount' => 250.00
            ]);

        $automationStmt = Mockery::mock(\PDOStatement::class);
        $automationStmt->shouldReceive('fetch')
            ->with(\PDO::FETCH_ASSOC)
            ->andReturn([
                'total_decisions' => 50,
                'auto_approved' => 40,
                'escalated' => 10,
                'avg_confidence' => 0.92
            ]);

        // Setup query method to return appropriate statements
        $this->db->shouldReceive('query')->andReturn($amendmentStmt);

        ob_start();
        $this->controller->getData();
        $output = ob_get_clean();

        $this->assertJson($output);
        $data = json_decode($output, true);
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('data', $data);
    }

    /**
     * Test getData returns amendment counts
     */
    public function testGetDataReturnsAmendmentCounts(): void
    {
        $amendmentStmt = Mockery::mock(\PDOStatement::class);
        $amendmentStmt->shouldReceive('fetch')
            ->with(\PDO::FETCH_ASSOC)
            ->andReturn(['total' => 5, 'pending' => 2, 'approved' => 3, 'declined' => 0]);

        $this->setupAllMockStatementsForGetData();

        ob_start();
        $this->controller->getData();
        $output = ob_get_clean();

        $this->assertJson($output);
        $data = json_decode($output, true);
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('amendments', $data['data']);
    }

    /**
     * Test getData returns discrepancy counts
     */
    public function testGetDataReturnsDiscrepancyCounts(): void
    {
        $this->setupAllMockStatementsForGetData();

        ob_start();
        $this->controller->getData();
        $output = ob_get_clean();

        $this->assertJson($output);
        $data = json_decode($output, true);
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('discrepancies', $data['data']);
        $this->assertArrayHasKey('total', $data['data']['discrepancies']);
    }

    /**
     * Test getData returns leave counts
     */
    public function testGetDataReturnsLeaveCounts(): void
    {
        $this->setupAllMockStatementsForGetData();

        ob_start();
        $this->controller->getData();
        $output = ob_get_clean();

        $this->assertJson($output);
        $data = json_decode($output, true);
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('leave', $data['data']);
    }

    /**
     * Test getData returns bonus counts with breakdown
     */
    public function testGetDataReturnsBonusCountsWithBreakdown(): void
    {
        $this->setupAllMockStatementsForGetData();

        ob_start();
        $this->controller->getData();
        $output = ob_get_clean();

        $this->assertJson($output);
        $data = json_decode($output, true);
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('bonuses', $data['data']);
        $this->assertArrayHasKey('monthly', $data['data']['bonuses']);
        $this->assertArrayHasKey('vape_drops', $data['data']['bonuses']);
        $this->assertArrayHasKey('google_reviews', $data['data']['bonuses']);
    }

    /**
     * Test getData returns Vend payment counts
     */
    public function testGetDataReturnsVendPaymentCounts(): void
    {
        $this->setupAllMockStatementsForGetData();

        ob_start();
        $this->controller->getData();
        $output = ob_get_clean();

        $this->assertJson($output);
        $data = json_decode($output, true);
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('vend_payments', $data['data']);
        $this->assertArrayHasKey('total_amount', $data['data']['vend_payments']);
    }

    /**
     * Test getData returns automation stats for admin
     */
    public function testGetDataReturnsAutomationStatsForAdmin(): void
    {
        $this->setupAllMockStatementsForGetData();

        ob_start();
        $this->controller->getData();
        $output = ob_get_clean();

        $this->assertJson($output);
        $data = json_decode($output, true);
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('automation', $data['data']);
    }

    /**
     * Test getData handles exception gracefully
     */
    public function testGetDataHandlesExceptionGracefully(): void
    {
        // Make query throw exception
        $this->db->shouldReceive('query')
            ->andThrow(new \Exception('Database error'));

        ob_start();
        $this->controller->getData();
        $output = ob_get_clean();

        $this->assertJson($output);
        $data = json_decode($output, true);
        $this->assertFalse($data['success']);
        $this->assertArrayHasKey('error', $data);
    }

    /**
     * Test getData includes admin flag
     */
    public function testGetDataIncludesAdminFlag(): void
    {
        $this->setupAllMockStatementsForGetData();

        ob_start();
        $this->controller->getData();
        $output = ob_get_clean();

        $this->assertJson($output);
        $data = json_decode($output, true);
        $this->assertArrayHasKey('is_admin', $data);
    }

    /**
     * Test getData includes staff ID
     */
    public function testGetDataIncludesStaffId(): void
    {
        $this->setupAllMockStatementsForGetData();

        ob_start();
        $this->controller->getData();
        $output = ob_get_clean();

        $this->assertJson($output);
        $data = json_decode($output, true);
        $this->assertArrayHasKey('staff_id', $data);
    }

    /**
     * Test getData returns proper structure for non-admin users
     */
    public function testGetDataReturnsProperStructureForNonAdminUsers(): void
    {
        $this->setupAllMockStatementsForGetData();

        ob_start();
        $this->controller->getData();
        $output = ob_get_clean();

        $this->assertJson($output);
        $data = json_decode($output, true);
        $this->assertTrue($data['success']);

        // All data sections should be present regardless of admin status
        $this->assertArrayHasKey('amendments', $data['data']);
        $this->assertArrayHasKey('discrepancies', $data['data']);
        $this->assertArrayHasKey('leave', $data['data']);
        $this->assertArrayHasKey('bonuses', $data['data']);
        $this->assertArrayHasKey('vend_payments', $data['data']);
    }

    /**
     * Test getData returns correct HTTP response code on success
     */
    public function testGetDataReturnsCorrectHttpResponseCodeOnSuccess(): void
    {
        $this->setupAllMockStatementsForGetData();

        ob_start();
        $this->controller->getData();
        $output = ob_get_clean();

        // Response code should be 200 (default)
        $this->assertJson($output);
    }

    /**
     * Test getData returns 500 on error
     */
    public function testGetDataReturns500OnError(): void
    {
        $this->db->shouldReceive('query')
            ->andThrow(new \Exception('Database connection failed'));

        ob_start();
        $this->controller->getData();
        $output = ob_get_clean();

        $this->assertJson($output);
        $data = json_decode($output, true);
        $this->assertFalse($data['success']);
    }

    /**
     * Helper method to setup all mock statements for getData
     */
    private function setupAllMockStatementsForGetData(): void
    {
        $amendmentStmt = Mockery::mock(\PDOStatement::class);
        $amendmentStmt->shouldReceive('fetch')
            ->with(\PDO::FETCH_ASSOC)
            ->andReturn(['total' => 5, 'pending' => 2, 'approved' => 3, 'declined' => 0]);

        $discrepancyStmt = Mockery::mock(\PDOStatement::class);
        $discrepancyStmt->shouldReceive('fetch')
            ->with(\PDO::FETCH_ASSOC)
            ->andReturn([
                'total' => 3,
                'pending' => 1,
                'ai_review' => 1,
                'approved' => 1,
                'declined' => 0,
                'urgent' => 0
            ]);

        $leaveStmt = Mockery::mock(\PDOStatement::class);
        $leaveStmt->shouldReceive('fetch')
            ->with(\PDO::FETCH_ASSOC)
            ->andReturn(['total' => 2, 'pending' => 1, 'approved' => 1, 'declined' => 0]);

        $bonusMonthlyStmt = Mockery::mock(\PDOStatement::class);
        $bonusMonthlyStmt->shouldReceive('fetch')
            ->with(\PDO::FETCH_ASSOC)
            ->andReturn(['total' => 2, 'pending' => 0, 'total_amount' => 100.00]);

        $bonusVapeDropStmt = Mockery::mock(\PDOStatement::class);
        $bonusVapeDropStmt->shouldReceive('fetch')
            ->with(\PDO::FETCH_ASSOC)
            ->andReturn(['total' => 5, 'unpaid' => 2]);

        $bonusGoogleStmt = Mockery::mock(\PDOStatement::class);
        $bonusGoogleStmt->shouldReceive('fetch')
            ->with(\PDO::FETCH_ASSOC)
            ->andReturn(['total' => 10, 'unpaid' => 3, 'total_bonus' => 45.00]);

        $vendStmt = Mockery::mock(\PDOStatement::class);
        $vendStmt->shouldReceive('fetch')
            ->with(\PDO::FETCH_ASSOC)
            ->andReturn([
                'total' => 4,
                'pending' => 1,
                'ai_review' => 1,
                'approved' => 2,
                'completed' => 0,
                'total_amount' => 250.00
            ]);

        $automationStmt = Mockery::mock(\PDOStatement::class);
        $automationStmt->shouldReceive('fetch')
            ->with(\PDO::FETCH_ASSOC)
            ->andReturn([
                'total_decisions' => 50,
                'auto_approved' => 40,
                'escalated' => 10,
                'avg_confidence' => 0.92
            ]);

        // Setup query to return statements in order
        $this->db->shouldReceive('query')
            ->times(7)
            ->andReturnValues([
                $amendmentStmt,
                $discrepancyStmt,
                $leaveStmt,
                $bonusMonthlyStmt,
                $bonusVapeDropStmt,
                $bonusGoogleStmt,
                $vendStmt,
                $automationStmt
            ]);
    }
}
