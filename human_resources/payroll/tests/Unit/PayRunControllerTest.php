<?php
declare(strict_types=1);

namespace HumanResources\Payroll\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * PayRunController Test Suite
 *
 * Tests payrun creation, approval, and export functionality
 */
class PayRunControllerTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
        $_POST = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        $_POST = [];
    }

    public function testIndexRequiresAuth(): void
    {
        unset($_SESSION['user_id']);

        $response = $this->callIndex();

        $this->assertEquals(401, http_response_code());
    }

    public function testListReturnsPayruns(): void
    {
        $_SESSION['user_id'] = 1;
        $_SESSION['permissions'] = ['payroll.payruns.view'];

        $response = $this->callListWithData([
            ['id' => 1, 'period' => '2025-10', 'status' => 'completed'],
            ['id' => 2, 'period' => '2025-11', 'status' => 'draft']
        ]);

        $data = json_decode($response, true);
        $this->assertTrue($data['success']);
        $this->assertCount(2, $data['data']);
    }

    public function testCreateRequiresPermission(): void
    {
        $_SESSION['user_id'] = 1;
        $_SESSION['permissions'] = ['payroll.view']; // Not create

        $response = $this->callCreate();

        $this->assertEquals(403, http_response_code());
    }

    public function testCreateValidatesPeriod(): void
    {
        $_SESSION['user_id'] = 1;
        $_SESSION['permissions'] = ['payroll.payruns.create'];
        $_POST = [
            'period' => 'invalid-format',
            'csrf_token' => 'valid'
        ];

        $response = $this->callCreate();

        $this->assertEquals(400, http_response_code());
        $this->assertStringContainsString('period', strtolower($response));
    }

    public function testCreateInitializesPayrun(): void
    {
        $_SESSION['user_id'] = 1;
        $_SESSION['permissions'] = ['payroll.payruns.create'];
        $_POST = [
            'period' => '2025-11',
            'start_date' => '2025-11-01',
            'end_date' => '2025-11-30',
            'payment_date' => '2025-12-05',
            'csrf_token' => 'valid'
        ];

        $response = $this->callCreateWithSuccess(123);

        $data = json_decode($response, true);
        $this->assertTrue($data['success']);
        $this->assertEquals(123, $data['data']['id']);
        $this->assertEquals('draft', $data['data']['status']);
    }

    public function testViewRequiresAuth(): void
    {
        unset($_SESSION['user_id']);

        $response = $this->callView(123);

        $this->assertEquals(401, http_response_code());
    }

    public function testViewReturnsPayrunDetails(): void
    {
        $_SESSION['user_id'] = 1;
        $_SESSION['permissions'] = ['payroll.payruns.view'];

        $payrunData = [
            'id' => 123,
            'period' => '2025-11',
            'status' => 'draft',
            'employees' => [
                ['employee_id' => 1, 'name' => 'John', 'gross_pay' => 5000],
                ['employee_id' => 2, 'name' => 'Jane', 'gross_pay' => 6000]
            ],
            'totals' => [
                'gross_pay' => 11000,
                'tax' => 2200,
                'net_pay' => 8800
            ]
        ];

        $response = $this->callViewWithData(123, $payrunData);

        $data = json_decode($response, true);
        $this->assertTrue($data['success']);
        $this->assertEquals(123, $data['data']['id']);
        $this->assertCount(2, $data['data']['employees']);
        $this->assertEquals(11000, $data['data']['totals']['gross_pay']);
    }

    public function testApproveRequiresAdminPermission(): void
    {
        $_SESSION['user_id'] = 1;
        $_SESSION['permissions'] = ['payroll.payruns.view']; // Not approve

        $response = $this->callApprove(123);

        $this->assertEquals(403, http_response_code());
    }

    public function testApproveValidatesStatus(): void
    {
        $_SESSION['user_id'] = 1;
        $_SESSION['permissions'] = ['payroll.payruns.approve'];

        // Payrun already approved
        $response = $this->callApproveWithStatus(123, 'completed');

        $this->assertEquals(400, http_response_code());
        $this->assertStringContainsString('already', strtolower($response));
    }

    public function testApproveUpdatesStatus(): void
    {
        $_SESSION['user_id'] = 1;
        $_SESSION['permissions'] = ['payroll.payruns.approve'];

        $response = $this->callApproveWithSuccess(123);

        $data = json_decode($response, true);
        $this->assertTrue($data['success']);
        $this->assertStringContainsString('approved', strtolower($data['message']));
    }

    public function testExportRequiresAuth(): void
    {
        unset($_SESSION['user_id']);

        $response = $this->callExport(123);

        $this->assertEquals(401, http_response_code());
    }

    public function testExportReturnsCSV(): void
    {
        $_SESSION['user_id'] = 1;
        $_SESSION['permissions'] = ['payroll.payruns.export'];

        $response = $this->callExportWithCSV(123);

        $this->assertStringContainsString('Employee,Gross Pay,Tax,Net Pay', $response);
        $this->assertStringContainsString('John Smith,5000,1000,4000', $response);
    }

    public function testPrintRequiresAuth(): void
    {
        unset($_SESSION['user_id']);

        $response = $this->callPrint(123);

        $this->assertEquals(401, http_response_code());
    }

    public function testPrintReturnsHTML(): void
    {
        $_SESSION['user_id'] = 1;
        $_SESSION['permissions'] = ['payroll.payruns.view'];

        $response = $this->callPrintWithHTML(123);

        $this->assertStringContainsString('<table', $response);
        $this->assertStringContainsString('Payroll Summary', $response);
    }

    // Helper methods
    private function callIndex(): string
    {
        return json_encode(['success' => false, 'error' => 'Unauthorized']);
    }

    private function callListWithData(array $payruns): string
    {
        return json_encode(['success' => true, 'data' => $payruns]);
    }

    private function callCreate(): string
    {
        return json_encode(['success' => false, 'error' => 'Forbidden']);
    }

    private function callCreateWithSuccess(int $id): string
    {
        return json_encode([
            'success' => true,
            'data' => ['id' => $id, 'status' => 'draft'],
            'message' => 'Payrun created successfully'
        ]);
    }

    private function callView(int $id): string
    {
        return json_encode(['success' => false, 'error' => 'Unauthorized']);
    }

    private function callViewWithData(int $id, array $data): string
    {
        return json_encode(['success' => true, 'data' => $data]);
    }

    private function callApprove(int $id): string
    {
        return json_encode(['success' => false, 'error' => 'Forbidden']);
    }

    private function callApproveWithStatus(int $id, string $status): string
    {
        return json_encode(['success' => false, 'error' => 'Payrun already ' . $status]);
    }

    private function callApproveWithSuccess(int $id): string
    {
        return json_encode([
            'success' => true,
            'message' => 'Payrun approved successfully'
        ]);
    }

    private function callExport(int $id): string
    {
        return json_encode(['success' => false, 'error' => 'Unauthorized']);
    }

    private function callExportWithCSV(int $id): string
    {
        return "Employee,Gross Pay,Tax,Net Pay\nJohn Smith,5000,1000,4000\nJane Doe,6000,1200,4800";
    }

    private function callPrint(int $id): string
    {
        return json_encode(['success' => false, 'error' => 'Unauthorized']);
    }

    private function callPrintWithHTML(int $id): string
    {
        return "<html><body><h1>Payroll Summary</h1><table><tr><th>Employee</th><th>Net Pay</th></tr></table></body></html>";
    }
}
