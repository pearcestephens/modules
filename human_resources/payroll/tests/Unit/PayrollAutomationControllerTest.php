<?php
declare(strict_types=1);

namespace HumanResources\Payroll\Tests\Unit;

use HumanResources\Payroll\Controllers\PayrollAutomationController;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

class PayrollAutomationControllerTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private PayrollAutomationController $controller;
    private MockInterface $db;

    protected function setUp(): void
    {
        parent::setUp();

        if (!isset($_SESSION)) {
            $_SESSION = [];
        }
        $_SESSION['authenticated'] = true;
        $_SESSION['userID'] = 1;
        $_SESSION['admin'] = true;

        $this->controller = new PayrollAutomationController();
        $this->db = Mockery::mock(\PDO::class);

        $reflector = new \ReflectionObject($this->controller);
        $property = $reflector->getProperty('db');
        $property->setAccessible(true);
        $property->setValue($this->controller, $this->db);
    }

    public function testGetAutomationRulesReturnsAllRules(): void
    {
        $stmt = Mockery::mock(\PDOStatement::class);
        $stmt->shouldReceive('execute')->with([]);
        $stmt->shouldReceive('fetchAll')->with(\PDO::FETCH_ASSOC)->andReturn([
            [
                'id' => 1,
                'rule_name' => 'Auto-approve small amendments',
                'rule_type' => 'amendment_approval',
                'condition_field' => 'amendment_amount',
                'condition_operator' => '<',
                'condition_value' => '100',
                'enabled' => 1
            ]
        ]);

        $this->db->shouldReceive('prepare')->andReturn($stmt);

        ob_start();
        $this->controller->getAutomationRules();
        $output = ob_get_clean();

        $this->assertJson($output);
        $data = json_decode($output, true);
        $this->assertTrue($data['success']);
    }

    public function testCreateAutomationRuleValidatesInputFields(): void
    {
        $_POST = [
            'rule_name' => '',
            'rule_type' => 'amendment_approval'
        ];

        ob_start();
        $this->controller->createAutomationRule();
        $output = ob_get_clean();

        $this->assertJson($output);
        $data = json_decode($output, true);
        $this->assertFalse($data['success']);
    }

    public function testCreateAutomationRuleSuccessfully(): void
    {
        $_POST = [
            'rule_name' => 'Auto-approve time off',
            'rule_type' => 'leave_approval',
            'condition_field' => 'leave_type',
            'condition_value' => 'annual'
        ];

        $stmt = Mockery::mock(\PDOStatement::class);
        $stmt->shouldReceive('execute');

        $this->db->shouldReceive('prepare')->andReturn($stmt);
        $this->db->shouldReceive('lastInsertId')->andReturn(1);

        ob_start();
        $this->controller->createAutomationRule();
        $output = ob_get_clean();

        $this->assertJson($output);
        $data = json_decode($output, true);
        $this->assertTrue($data['success']);
    }

    public function testExecuteAutomationRuleProcessesEligibleItems(): void
    {
        $stmt = Mockery::mock(\PDOStatement::class);
        $stmt->shouldReceive('execute')->with([1]);
        $stmt->shouldReceive('fetch')->with(\PDO::FETCH_ASSOC)->andReturn([
            'id' => 1,
            'rule_name' => 'Auto-approve small amendments',
            'rule_type' => 'amendment_approval'
        ]);

        $itemStmt = Mockery::mock(\PDOStatement::class);
        $itemStmt->shouldReceive('execute');
        $itemStmt->shouldReceive('fetchAll')->andReturn([
            ['id' => 10, 'amendment_amount' => 50]
        ]);

        $this->db->shouldReceive('prepare')
            ->withArgs(function($query) {
                return strpos($query, 'SELECT') === 0;
            })
            ->andReturn($stmt, $itemStmt);
        $this->db->shouldReceive('prepare')
            ->withArgs(function($query) {
                return strpos($query, 'UPDATE') === 0;
            })
            ->andReturn($itemStmt);

        ob_start();
        $this->controller->executeAutomationRule(1);
        $output = ob_get_clean();

        $this->assertJson($output);
        $data = json_decode($output, true);
        $this->assertTrue($data['success']);
    }

    public function testDisableAutomationRuleUpdatesStatus(): void
    {
        $stmt = Mockery::mock(\PDOStatement::class);
        $stmt->shouldReceive('execute');

        $this->db->shouldReceive('prepare')->andReturn($stmt);

        ob_start();
        $this->controller->disableAutomationRule(1);
        $output = ob_get_clean();

        $this->assertJson($output);
        $data = json_decode($output, true);
        $this->assertTrue($data['success']);
    }
}
