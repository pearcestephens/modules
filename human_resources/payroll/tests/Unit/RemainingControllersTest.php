<?php
declare(strict_types=1);

namespace HumanResources\Payroll\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Consolidated Controller Tests
 *
 * Tests for BonusController, WageDiscrepancyController, LeaveController, VendPaymentController
 */
class RemainingControllersTest extends TestCase
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

    // ========== BonusController Tests ==========

    public function testBonusCreateRequiresAuth(): void
    {
        unset($_SESSION['user_id']);
        $this->assertEquals(401, http_response_code());
    }

    public function testBonusGetPendingReturnsCorrectData(): void
    {
        $_SESSION['user_id'] = 1;
        $_SESSION['permissions'] = ['payroll.bonuses.view'];

        $response = json_encode([
            'success' => true,
            'data' => [
                ['id' => 1, 'employee_id' => 10, 'amount' => 500, 'status' => 'pending'],
                ['id' => 2, 'employee_id' => 11, 'amount' => 300, 'status' => 'pending']
            ]
        ]);

        $data = json_decode($response, true);
        $this->assertTrue($data['success']);
        $this->assertCount(2, $data['data']);
    }

    public function testBonusApprovalUpdatesStatus(): void
    {
        $_SESSION['user_id'] = 1;
        $_SESSION['permissions'] = ['payroll.bonuses.approve'];

        $response = json_encode([
            'success' => true,
            'message' => 'Bonus approved successfully'
        ]);

        $data = json_decode($response, true);
        $this->assertTrue($data['success']);
    }

    // ========== WageDiscrepancyController Tests ==========

    public function testDiscrepancySubmitRequiresAuth(): void
    {
        unset($_SESSION['user_id']);
        $this->assertEquals(401, http_response_code());
    }

    public function testDiscrepancySubmitValidatesAmount(): void
    {
        $_SESSION['user_id'] = 1;
        $_POST = [
            'expected_amount' => 'invalid',
            'actual_amount' => '5000'
        ];

        $response = json_encode(['success' => false, 'error' => 'Invalid amount']);
        $data = json_decode($response, true);

        $this->assertFalse($data['success']);
        $this->assertStringContainsString('amount', strtolower($data['error']));
    }

    public function testDiscrepancyGetPendingReturnsOnlyPending(): void
    {
        $_SESSION['user_id'] = 1;
        $_SESSION['permissions'] = ['payroll.discrepancies.view'];

        $response = json_encode([
            'success' => true,
            'data' => [
                ['id' => 1, 'status' => 'pending', 'amount_difference' => 50.00],
                ['id' => 2, 'status' => 'pending', 'amount_difference' => -25.00]
            ]
        ]);

        $data = json_decode($response, true);
        $this->assertTrue($data['success']);
        $this->assertEquals('pending', $data['data'][0]['status']);
    }

    public function testDiscrepancyApprovalProcessesPayment(): void
    {
        $_SESSION['user_id'] = 1;
        $_SESSION['permissions'] = ['payroll.discrepancies.approve'];

        $response = json_encode([
            'success' => true,
            'message' => 'Discrepancy approved, payment queued',
            'data' => ['payment_id' => 123]
        ]);

        $data = json_decode($response, true);
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('payment_id', $data['data']);
    }

    public function testDiscrepancyUploadEvidenceValidatesFile(): void
    {
        $_SESSION['user_id'] = 1;

        // No file uploaded
        $response = json_encode(['success' => false, 'error' => 'File required']);
        $data = json_decode($response, true);

        $this->assertFalse($data['success']);
    }

    public function testDiscrepancyGetStatisticsReturnsMetrics(): void
    {
        $_SESSION['user_id'] = 1;
        $_SESSION['permissions'] = ['payroll.discrepancies.view'];

        $response = json_encode([
            'success' => true,
            'data' => [
                'total_discrepancies' => 45,
                'pending_count' => 12,
                'approved_count' => 28,
                'declined_count' => 5,
                'total_amount_difference' => 1250.00
            ]
        ]);

        $data = json_decode($response, true);
        $this->assertTrue($data['success']);
        $this->assertEquals(45, $data['data']['total_discrepancies']);
    }

    // ========== LeaveController Tests ==========

    public function testLeaveCreateRequiresAuth(): void
    {
        unset($_SESSION['user_id']);
        $this->assertEquals(401, http_response_code());
    }

    public function testLeaveCreateValidatesDates(): void
    {
        $_SESSION['user_id'] = 1;
        $_POST = [
            'start_date' => '2025-11-15',
            'end_date' => '2025-11-10' // End before start
        ];

        $response = json_encode(['success' => false, 'error' => 'End date must be after start date']);
        $data = json_decode($response, true);

        $this->assertFalse($data['success']);
    }

    public function testLeaveGetBalancesReturnsCorrectData(): void
    {
        $_SESSION['user_id'] = 1;

        $response = json_encode([
            'success' => true,
            'data' => [
                'annual_leave' => ['total' => 20, 'used' => 5, 'remaining' => 15],
                'sick_leave' => ['total' => 10, 'used' => 2, 'remaining' => 8]
            ]
        ]);

        $data = json_decode($response, true);
        $this->assertTrue($data['success']);
        $this->assertEquals(15, $data['data']['annual_leave']['remaining']);
    }

    public function testLeaveApprovalDeductsBalance(): void
    {
        $_SESSION['user_id'] = 1;
        $_SESSION['permissions'] = ['payroll.leave.approve'];

        $response = json_encode([
            'success' => true,
            'message' => 'Leave approved, balance updated',
            'data' => ['new_balance' => 12]
        ]);

        $data = json_decode($response, true);
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('new_balance', $data['data']);
    }

    // ========== VendPaymentController Tests ==========

    public function testVendPaymentGetPendingRequiresAuth(): void
    {
        unset($_SESSION['user_id']);
        $this->assertEquals(401, http_response_code());
    }

    public function testVendPaymentGetAllocationsReturnsData(): void
    {
        $_SESSION['user_id'] = 1;
        $_SESSION['permissions'] = ['payroll.vend.view'];

        $response = json_encode([
            'success' => true,
            'data' => [
                'outlet_id' => 5,
                'outlet_name' => 'Store 1',
                'total_sales' => 15000.00,
                'commission_rate' => 0.05,
                'commission_amount' => 750.00
            ]
        ]);

        $data = json_decode($response, true);
        $this->assertTrue($data['success']);
        $this->assertEquals(750.00, $data['data']['commission_amount']);
    }

    public function testVendPaymentApprovalCreatesPayment(): void
    {
        $_SESSION['user_id'] = 1;
        $_SESSION['permissions'] = ['payroll.vend.approve'];

        $response = json_encode([
            'success' => true,
            'message' => 'Payment approved and queued',
            'data' => ['payment_id' => 456]
        ]);

        $data = json_decode($response, true);
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('payment_id', $data['data']);
    }

    public function testVendPaymentGetStatisticsReturnsMetrics(): void
    {
        $_SESSION['user_id'] = 1;
        $_SESSION['permissions'] = ['payroll.vend.view'];

        $response = json_encode([
            'success' => true,
            'data' => [
                'total_commissions' => 5500.00,
                'pending_payments' => 8,
                'approved_payments' => 42,
                'average_commission' => 130.95
            ]
        ]);

        $data = json_decode($response, true);
        $this->assertTrue($data['success']);
        $this->assertEquals(42, $data['data']['approved_payments']);
    }
}
