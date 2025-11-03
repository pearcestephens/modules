<?php
/**
 * Unit Test for PayslipController
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
use PayrollModule\Controllers\PayslipController;
use PayrollModule\Services\PayslipService;
use PayrollModule\Services\BonusService;
use PayrollModule\Services\BankExportService;

final class PayslipControllerTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private $pdo;
    private $payslipService;
    private $bonusService;
    private $bankExportService;
    private $controller;

    protected function setUp(): void
    {
        $this->pdo = Mockery::mock(PDO::class);
        $this->payslipService = Mockery::mock(PayslipService::class);
        $this->bonusService = Mockery::mock(BonusService::class);
        $this->bankExportService = Mockery::mock(BankExportService::class);

        $this->controller = new PayslipController($this->pdo);

        // Use reflection to inject our mocked dependencies
        $reflector = new \ReflectionObject($this->controller);

        try {
            $payslipServiceProperty = $reflector->getProperty('payslipService');
            $payslipServiceProperty->setAccessible(true);
            $payslipServiceProperty->setValue($this->controller, $this->payslipService);

            $bonusServiceProperty = $reflector->getProperty('bonusService');
            $bonusServiceProperty->setAccessible(true);
            $bonusServiceProperty->setValue($this->controller, $this->bonusService);

            $bankExportServiceProperty = $reflector->getProperty('bankExportService');
            $bankExportServiceProperty->setAccessible(true);
            $bankExportServiceProperty->setValue($this->controller, $this->bankExportService);
        } catch (\ReflectionException $e) {
            // Properties might not exist or might be private in parent
        }

        $_GET = [];
        $_POST = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
    }

    protected function tearDown(): void
    {
        Mockery::close();
    }

    /**
     * Test: calculatePayslips validates required fields
     */
    public function testCalculatePayslipsValidatesPeriodStart()
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['period_end' => '2025-01-31'];

        ob_start();
        $this->controller->calculatePayslips();
        $output = ob_get_clean();

        $this->assertJson($output);
        $data = json_decode($output, true);
        $this->assertFalse($data['success']);
        $this->assertStringContainsString('period_start', $data['error']);
    }

    /**
     * Test: calculatePayslips successfully calculates payslips
     */
    public function testCalculatePayslipsSuccess()
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = [
            'period_start' => '2025-01-01',
            'period_end' => '2025-01-31'
        ];

        $payslipResults = [
            ['id' => 1, 'staff_id' => 1, 'gross_pay' => 5000, 'net_pay' => 4000],
            ['id' => 2, 'staff_id' => 2, 'gross_pay' => 5500, 'net_pay' => 4400]
        ];

        $this->payslipService->shouldReceive('calculatePayslipsForPeriod')
            ->with('2025-01-01', '2025-01-31', null)
            ->andReturn($payslipResults);

        ob_start();
        $this->controller->calculatePayslips();
        $output = ob_get_clean();

        $this->assertJson($output);
        $data = json_decode($output, true);
        $this->assertTrue($data['success']);
        $this->assertCount(2, $data['data']);
        $this->assertStringContainsString('Calculated 2', $data['message']);
    }

    /**
     * Test: getPayslip returns payslip details
     */
    public function testGetPayslipSuccess()
    {
        $payslipData = [
            'id' => 1,
            'staff_id' => 1,
            'period_start' => '2025-01-01',
            'period_end' => '2025-01-31',
            'gross_pay' => 5000,
            'net_pay' => 4000
        ];

        $bonusSummary = ['unpaid' => 1000, 'pending' => 500];

        $this->payslipService->shouldReceive('getPayslipById')->with(1)->andReturn($payslipData);
        $this->bonusService->shouldReceive('getUnpaidBonusSummary')->with(1)->andReturn($bonusSummary);

        ob_start();
        $this->controller->getPayslip(1);
        $output = ob_get_clean();

        $this->assertJson($output);
        $data = json_decode($output, true);
        $this->assertTrue($data['success']);
        $this->assertEquals($payslipData, $data['data']['payslip']);
        $this->assertEquals($bonusSummary, $data['data']['bonus_summary']);
    }

    /**
     * Test: getPayslip returns 404 when not found
     */
    public function testGetPayslipNotFound()
    {
        $this->payslipService->shouldReceive('getPayslipById')->with(999)->andReturn(null);

        ob_start();
        $this->controller->getPayslip(999);
        $output = ob_get_clean();

        $this->assertJson($output);
        $data = json_decode($output, true);
        $this->assertFalse($data['success']);
        $this->assertStringContainsString('not found', $data['error']);
    }

    /**
     * Test: listPayslipsByPeriod returns payslips
     */
    public function testListPayslipsByPeriodSuccess()
    {
        $payslips = [
            ['id' => 1, 'staff_id' => 1, 'status' => 'approved'],
            ['id' => 2, 'staff_id' => 2, 'status' => 'approved']
        ];

        $this->payslipService->shouldReceive('getPayslipsByPeriod')
            ->with('2025-01-01', '2025-01-31', null)
            ->andReturn($payslips);

        ob_start();
        $this->controller->listPayslipsByPeriod('2025-01-01', '2025-01-31');
        $output = ob_get_clean();

        $this->assertJson($output);
        $data = json_decode($output, true);
        $this->assertTrue($data['success']);
        $this->assertEquals(2, $data['count']);
        $this->assertCount(2, $data['data']);
    }

    /**
     * Test: getStaffPayslips returns payslips for staff
     */
    public function testGetStaffPayslipsSuccess()
    {
        $_GET['limit'] = '5';

        $payslips = [
            ['id' => 1, 'period' => '2025-01', 'net_pay' => 4000],
            ['id' => 2, 'period' => '2024-12', 'net_pay' => 4000]
        ];

        $this->payslipService->shouldReceive('getStaffPayslips')
            ->with(1, 5)
            ->andReturn($payslips);

        ob_start();
        $this->controller->getStaffPayslips(1);
        $output = ob_get_clean();

        $this->assertJson($output);
        $data = json_decode($output, true);
        $this->assertTrue($data['success']);
        $this->assertEquals(2, $data['count']);
        $this->assertCount(2, $data['data']);
    }

    /**
     * Test: reviewPayslip successfully marks payslip as reviewed
     */
    public function testReviewPayslipSuccess()
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $this->payslipService->shouldReceive('reviewPayslip')->with(1, 1)->andReturn(true);

        ob_start();
        $this->controller->reviewPayslip(1);
        $output = ob_get_clean();

        $this->assertJson($output);
        $data = json_decode($output, true);
        $this->assertTrue($data['success']);
        $this->assertStringContainsString('reviewed', $data['message']);
    }

    /**
     * Test: reviewPayslip returns error on failure
     */
    public function testReviewPayslipFailure()
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $this->payslipService->shouldReceive('reviewPayslip')->with(1, 1)->andReturn(false);

        ob_start();
        $this->controller->reviewPayslip(1);
        $output = ob_get_clean();

        $this->assertJson($output);
        $data = json_decode($output, true);
        $this->assertFalse($data['success']);
        $this->assertStringContainsString('Failed to review', $data['error']);
    }

    /**
     * Test: approvePayslip successfully approves payslip
     */
    public function testApprovePayslipSuccess()
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $this->payslipService->shouldReceive('approvePayslip')->with(1, 1)->andReturn(true);

        ob_start();
        $this->controller->approvePayslip(1);
        $output = ob_get_clean();

        $this->assertJson($output);
        $data = json_decode($output, true);
        $this->assertTrue($data['success']);
        $this->assertStringContainsString('approved', $data['message']);
    }

    /**
     * Test: cancelPayslip successfully cancels payslip
     */
    public function testCancelPayslipSuccess()
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['reason' => 'Test cancellation'];

        $this->payslipService->shouldReceive('cancelPayslip')->with(1, 'Test cancellation')->andReturn(true);

        ob_start();
        $this->controller->cancelPayslip(1);
        $output = ob_get_clean();

        $this->assertJson($output);
        $data = json_decode($output, true);
        $this->assertTrue($data['success']);
        $this->assertStringContainsString('cancelled', $data['message']);
    }

    /**
     * Test: exportToBank validates required fields
     */
    public function testExportToBankValidatesPayslipIds()
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['from_account' => 'ACC001'];

        ob_start();
        $this->controller->exportToBank();
        $output = ob_get_clean();

        $this->assertJson($output);
        $data = json_decode($output, true);
        $this->assertFalse($data['success']);
        $this->assertStringContainsString('payslip_ids', $data['error']);
    }

    /**
     * Test: exportToBank validates from_account
     */
    public function testExportToBankValidatesFromAccount()
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['payslip_ids' => [1, 2]];

        ob_start();
        $this->controller->exportToBank();
        $output = ob_get_clean();

        $this->assertJson($output);
        $data = json_decode($output, true);
        $this->assertFalse($data['success']);
        $this->assertStringContainsString('from_account', $data['error']);
    }

    /**
     * Test: exportToBank successfully generates bank file
     */
    public function testExportToBankSuccess()
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = [
            'payslip_ids' => [1, 2],
            'from_account' => 'ACC001',
            'period' => '2025-01'
        ];

        $exportResult = [
            'file_id' => 'EX-001',
            'payslip_count' => 2,
            'total_amount' => 8000,
            'download_url' => '/downloads/EX-001.csv'
        ];

        $this->bankExportService->shouldReceive('generateBankFile')
            ->with([1, 2], 'ACC001', '2025-01')
            ->andReturn($exportResult);

        ob_start();
        $this->controller->exportToBank();
        $output = ob_get_clean();

        $this->assertJson($output);
        $data = json_decode($output, true);
        $this->assertTrue($data['success']);
        $this->assertEquals($exportResult, $data['data']);
        $this->assertStringContainsString('Exported 2', $data['message']);
    }

    /**
     * Test: getExport returns export details
     */
    public function testGetExportSuccess()
    {
        $exportData = [
            'id' => 1,
            'created_at' => '2025-01-31 10:00:00',
            'payslip_count' => 2,
            'total_amount' => 8000,
            'status' => 'completed'
        ];

        $this->bankExportService->shouldReceive('getExport')->with(1)->andReturn($exportData);

        ob_start();
        $this->controller->getExport(1);
        $output = ob_get_clean();

        $this->assertJson($output);
        $data = json_decode($output, true);
        $this->assertTrue($data['success']);
        $this->assertEquals($exportData, $data['data']['export']);
    }

    /**
     * Test: Exception handling
     */
    public function testCalculatePayslipsCatchesException()
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = [
            'period_start' => '2025-01-01',
            'period_end' => '2025-01-31'
        ];

        $this->payslipService->shouldReceive('calculatePayslipsForPeriod')
            ->andThrow(new \Exception('Database error'));

        ob_start();
        $this->controller->calculatePayslips();
        $output = ob_get_clean();

        $this->assertJson($output);
        $data = json_decode($output, true);
        $this->assertFalse($data['success']);
        $this->assertEquals(500, http_response_code());
    }
}
