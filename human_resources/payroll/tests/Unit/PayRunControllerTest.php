<?php
/**
 * Unit Test for PayRunController
 *
 * @package CIS\Payroll\Tests\Unit
 */

declare(strict_types=1);

namespace HumanResources\Payroll\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PDO;
use PDOStatement;
use HumanResources\Payroll\Controllers\PayRunController;
use PayrollModule\Lib\PayrollLogger;

// Mock the BaseController if it has abstract methods or significant constructor logic.
// For now, we assume it's concrete enough to extend.
if (!class_exists('BaseController')) {
    class BaseController {
        protected $logger;
        public function __construct() {
            // Mock constructor
        }
        protected function json(array $data, int $statusCode = 200): void {
            http_response_code($statusCode);
            echo json_encode($data);
        }
        protected function getCurrentUserId(): int {
            return 1; // Mocked
        }
        protected function getJsonInput(): array {
            return json_decode(file_get_contents('php://input'), true) ?? [];
        }
    }
}
// Mock the global function if it doesn't exist in the test environment
if (!function_exists('getPayrollDb')) {
    function getPayrollDb() {
        // This will be replaced by the mock injected via reflection
    }
}


final class PayRunControllerTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private $pdo;
    private $logger;
    private $controller;

    protected function setUp(): void
    {
        $this->pdo = Mockery::mock(PDO::class);
        $this->logger = Mockery::mock(PayrollLogger::class);
        $this->logger->shouldReceive('error'); // Default mock for error logging

        $this->controller = new PayRunController();

        // Use reflection to inject our mocked dependencies, bypassing the global function call.
        $reflector = new \ReflectionObject($this->controller);

        try {
            $dbProperty = $reflector->getProperty('db');
            $dbProperty->setAccessible(true);
            $dbProperty->setValue($this->controller, $this->pdo);
        } catch (\ReflectionException $e) {
            // Property might not exist in the version of the file, ignore.
        }

        // The logger is in the parent, so we need to go up.
        $parentReflector = $reflector->getParentClass();
        if ($parentReflector) {
            try {
                $loggerProperty = $parentReflector->getProperty('logger');
                $loggerProperty->setAccessible(true);
                $loggerProperty->setValue($this->controller, $this->logger);
            } catch (\ReflectionException $e) {
                // Property might not exist, ignore.
            }
        }

        $_GET = [];
        $_POST = [];
    }

    protected function tearDown(): void
    {
        Mockery::close();
    }

    public function testIndexRendersViewOnSuccess()
    {
        $statement = Mockery::mock(PDOStatement::class);
        $statement->shouldReceive('execute')->with([20, 0]);
        $statement->shouldReceive('fetchAll')->with(PDO::FETCH_ASSOC)->andReturn([]);

        $countStatement = Mockery::mock(PDOStatement::class);
        $countStatement->shouldReceive('fetchColumn')->andReturn(0);

        $this->pdo->shouldReceive('prepare')->andReturn($statement);
        $this->pdo->shouldReceive('query')->andReturn($countStatement);

        ob_start();
        $this->controller->index();
        $output = ob_get_clean();

        // We can't easily test the `require_once` output, but we can check that it doesn't throw an error
        // and that some expected output is present.
        $this->assertStringContainsString('Pay Runs', $output);
    }

    public function testListReturnsPayRunsJson()
    {
        $_GET['page'] = '1';
        $_GET['limit'] = '20';

        $statement = Mockery::mock(PDOStatement::class);
        $statement->shouldReceive('execute')->with([20, 0]);
        $statement->shouldReceive('fetchAll')->with(PDO::FETCH_ASSOC)->andReturn([['period_start' => '2025-01-01']]);

        $countStatement = Mockery::mock(PDOStatement::class);
        $countStatement->shouldReceive('fetchColumn')->andReturn(1);

        $this->pdo->shouldReceive('prepare')->andReturn($statement);
        $this->pdo->shouldReceive('query')->andReturn($countStatement);

        ob_start();
        $this->controller->list();
        $output = ob_get_clean();

        $this->assertJson($output);
        $data = json_decode($output, true);
        $this->assertTrue($data['success']);
        $this->assertCount(1, $data['data']['pay_runs']);
        $this->assertEquals(1, $data['data']['pagination']['total']);
    }

    public function testShowReturnsPayRunDetailsJson()
    {
        $_GET['period_start'] = '2025-01-01';
        $_GET['period_end'] = '2025-01-07';

        $statement = Mockery::mock(PDOStatement::class);
        $statement->shouldReceive('execute')->with(['2025-01-01', '2025-01-07']);
        $statement->shouldReceive('fetchAll')->with(PDO::FETCH_ASSOC)->andReturn([['staff_id' => 1, 'gross_pay' => 100]]);

        $this->pdo->shouldReceive('prepare')->andReturn($statement);

        ob_start();
        $this->controller->show();
        $output = ob_get_clean();

        $this->assertJson($output);
        $data = json_decode($output, true);
        $this->assertTrue($data['success']);
        $this->assertCount(1, $data['data']['payslips']);
        $this->assertEquals(100, $data['data']['summary']['total_gross']);
    }

    public function testApproveUpdatesStatusAndLogsAction()
    {
        $_POST['period_start'] = '2025-01-01';
        $_POST['period_end'] = '2025-01-07';

        $statement = Mockery::mock(PDOStatement::class);
        // We expect user ID 1 from the mocked BaseController
        $statement->shouldReceive('execute')->with([1, '2025-01-01', '2025-01-07'])->andReturn(true);
        $statement->shouldReceive('rowCount')->andReturn(5);

        $this->pdo->shouldReceive('prepare')->andReturn($statement);
        $this->logger->shouldReceive('info')->once()->with('Pay run approved', Mockery::any());

        ob_start();
        $this->controller->approve();
        $output = ob_get_clean();

        $this->assertJson($output);
        $data = json_decode($output, true);
        $this->assertTrue($data['success']);
        $this->assertEquals(5, $data['data']['approved_count']);
    }

    public function testShowReturns404ForNotFound()
    {
        $_GET['period_start'] = '2025-01-01';
        $_GET['period_end'] = '2025-01-07';

        $statement = Mockery::mock(PDOStatement::class);
        $statement->shouldReceive('execute')->with(['2025-01-01', '2025-01-07']);
        $statement->shouldReceive('fetchAll')->with(PDO::FETCH_ASSOC)->andReturn([]);

        $this->pdo->shouldReceive('prepare')->andReturn($statement);

        ob_start();
        $this->controller->show();
        $output = ob_get_clean();

        $this->assertJson($output);
        $data = json_decode($output, true);
        $this->assertFalse($data['success']);
        $this->assertEquals('Pay run not found', $data['error']);
        $this->assertEquals(404, http_response_code());
    }
}
