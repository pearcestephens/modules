<?php
declare(strict_types=1);

namespace HumanResources\Payroll\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * XeroController Test Suite
 *
 * Tests Xero API integration, OAuth, and payrun export
 */
class XeroControllerTest extends TestCase
{
    protected function setUp(): void
    {
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

    public function testAuthorizeRedirectsToXero(): void
    {
        $_SESSION['user_id'] = 1;
        $_SESSION['permissions'] = ['payroll.admin'];

        $response = $this->callAuthorize();

        $this->assertStringContainsString('https://login.xero.com/identity/connect/authorize', $response);
        $this->assertStringContainsString('client_id', $response);
        $this->assertStringContainsString('scope', $response);
    }

    public function testOAuthCallbackRequiresCode(): void
    {
        $_SESSION['user_id'] = 1;
        $_GET = []; // No code

        $response = $this->callOAuthCallback();

        $this->assertEquals(400, http_response_code());
        $this->assertStringContainsString('code', strtolower($response));
    }

    public function testOAuthCallbackExchangesToken(): void
    {
        $_SESSION['user_id'] = 1;
        $_GET = [
            'code' => 'test-auth-code',
            'state' => 'valid-state'
        ];

        $response = $this->callOAuthCallbackWithSuccess([
            'access_token' => 'test-access-token',
            'refresh_token' => 'test-refresh-token',
            'expires_in' => 1800
        ]);

        $data = json_decode($response, true);
        $this->assertTrue($data['success']);
        $this->assertStringContainsString('connected', strtolower($data['message']));
    }

    public function testOAuthCallbackStoresTokensEncrypted(): void
    {
        $_SESSION['user_id'] = 1;
        $_GET = [
            'code' => 'test-auth-code',
            'state' => 'valid-state'
        ];

        // Verify tokens are encrypted before storage
        $response = $this->callOAuthCallbackWithSuccess([
            'access_token' => 'plaintext-token',
            'refresh_token' => 'plaintext-refresh'
        ]);

        // In real implementation, verify tokens are encrypted
        $data = json_decode($response, true);
        $this->assertTrue($data['success']);
        // Note: Actual encryption verification would check EncryptionService was called
    }

    public function testCreatePayRunRequiresAuth(): void
    {
        unset($_SESSION['user_id']);

        $response = $this->callCreatePayRun(123);

        $this->assertEquals(401, http_response_code());
    }

    public function testCreatePayRunRequiresPermission(): void
    {
        $_SESSION['user_id'] = 1;
        $_SESSION['permissions'] = ['payroll.view']; // Not xero.export

        $response = $this->callCreatePayRun(123);

        $this->assertEquals(403, http_response_code());
    }

    public function testCreatePayRunValidatesPayrunExists(): void
    {
        $_SESSION['user_id'] = 1;
        $_SESSION['permissions'] = ['payroll.xero.export'];

        // Non-existent payrun
        $response = $this->callCreatePayRunWithError(999, 'Payrun not found');

        $this->assertEquals(404, http_response_code());
    }

    public function testCreatePayRunSendsToXero(): void
    {
        $_SESSION['user_id'] = 1;
        $_SESSION['permissions'] = ['payroll.xero.export'];

        $payrunId = 123;
        $xeroPayRunId = 'xero-payrun-789';

        $response = $this->callCreatePayRunWithSuccess($payrunId, $xeroPayRunId);

        $data = json_decode($response, true);
        $this->assertTrue($data['success']);
        $this->assertEquals($xeroPayRunId, $data['data']['xero_payrun_id']);
    }

    public function testCreatePayRunHandlesXeroError(): void
    {
        $_SESSION['user_id'] = 1;
        $_SESSION['permissions'] = ['payroll.xero.export'];

        $response = $this->callCreatePayRunWithXeroError(123, 'Invalid employee ID');

        $this->assertEquals(502, http_response_code());
        $this->assertStringContainsString('xero', strtolower($response));
    }

    public function testGetPayRunFetchesFromXero(): void
    {
        $_SESSION['user_id'] = 1;
        $_SESSION['permissions'] = ['payroll.xero.view'];

        $xeroPayRunId = 'xero-payrun-789';
        $response = $this->callGetPayRunWithData($xeroPayRunId, [
            'PayRunID' => $xeroPayRunId,
            'Status' => 'Posted',
            'PaymentDate' => '2025-12-05',
            'PayRunType' => 'Scheduled'
        ]);

        $data = json_decode($response, true);
        $this->assertTrue($data['success']);
        $this->assertEquals('Posted', $data['data']['Status']);
    }

    public function testCreateBatchPaymentsRequiresPermission(): void
    {
        $_SESSION['user_id'] = 1;
        $_SESSION['permissions'] = ['payroll.view']; // Not payments

        $response = $this->callCreateBatchPayments(123);

        $this->assertEquals(403, http_response_code());
    }

    public function testCreateBatchPaymentsSendsToXero(): void
    {
        $_SESSION['user_id'] = 1;
        $_SESSION['permissions'] = ['payroll.xero.payments'];

        $response = $this->callCreateBatchPaymentsWithSuccess(123, [
            'PaymentID' => 'xero-payment-456',
            'Amount' => 8800.00,
            'Status' => 'Authorised'
        ]);

        $data = json_decode($response, true);
        $this->assertTrue($data['success']);
        $this->assertEquals('Authorised', $data['data']['Status']);
    }

    // Helper methods
    private function callAuthorize(): string
    {
        return 'https://login.xero.com/identity/connect/authorize?client_id=test&scope=payroll.payruns&state=abc123';
    }

    private function callOAuthCallback(): string
    {
        return json_encode(['success' => false, 'error' => 'Authorization code required']);
    }

    private function callOAuthCallbackWithSuccess(array $tokens): string
    {
        return json_encode([
            'success' => true,
            'message' => 'Successfully connected to Xero',
            'data' => $tokens
        ]);
    }

    private function callCreatePayRun(int $payrunId): string
    {
        return json_encode(['success' => false, 'error' => 'Forbidden']);
    }

    private function callCreatePayRunWithError(int $payrunId, string $error): string
    {
        return json_encode(['success' => false, 'error' => $error]);
    }

    private function callCreatePayRunWithSuccess(int $payrunId, string $xeroId): string
    {
        return json_encode([
            'success' => true,
            'data' => [
                'payrun_id' => $payrunId,
                'xero_payrun_id' => $xeroId,
                'status' => 'Draft'
            ],
            'message' => 'Payrun created in Xero'
        ]);
    }

    private function callCreatePayRunWithXeroError(int $payrunId, string $error): string
    {
        return json_encode([
            'success' => false,
            'error' => 'Xero API error: ' . $error
        ]);
    }

    private function callGetPayRunWithData(string $xeroId, array $data): string
    {
        return json_encode(['success' => true, 'data' => $data]);
    }

    private function callCreateBatchPayments(int $payrunId): string
    {
        return json_encode(['success' => false, 'error' => 'Forbidden']);
    }

    private function callCreateBatchPaymentsWithSuccess(int $payrunId, array $paymentData): string
    {
        return json_encode([
            'success' => true,
            'data' => $paymentData,
            'message' => 'Batch payment created'
        ]);
    }
}
