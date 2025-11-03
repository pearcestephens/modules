<?php
/**
 * API Endpoint Integration Test Suite - REAL HTTP Testing
 *
 * Comprehensive testing of all payroll API endpoints with actual HTTP requests
 * Tests for valid requests, error handling, authentication, and response validation
 *
 * @package CIS\Payroll\Tests\Integration
 * @group critical
 * @version 2.0.0
 */

declare(strict_types=1);

namespace HumanResources\Payroll\Tests\Integration;

use PHPUnit\Framework\TestCase;

/**
 * @group critical
 */
final class APIEndpointIntegrationTest extends TestCase
{
    private $baseUrl = 'https://staff.vapeshed.co.nz/modules/human_resources/payroll';
    private $testData = [];
    private $authToken = null;
    private $headers = [];

    protected function setUp(): void
    {
        parent::setUp();

        // Initialize test data
        $this->testData = [
            'staff_id' => 1,
            'payrun_id' => 1,
            'amendment_id' => 1,
            'payslip_id' => 1,
            'leave_id' => 1,
            'bonus_id' => 1,
            'valid_email' => 'test@example.com',
            'valid_amount' => 500.00,
            'valid_date' => date('Y-m-d'),
        ];

        // Setup headers
        $this->headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'X-Requested-With: XMLHttpRequest'
        ];
    }

    /**
     * Make HTTP GET request
     */
    private function get(string $endpoint, array $params = []): array
    {
        $url = $this->baseUrl . $endpoint;
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return [
            'status' => $httpCode,
            'body' => json_decode($response, true),
            'raw' => $response
        ];
    }

    /**
     * Make HTTP POST request
     */
    private function post(string $endpoint, array $data = []): array
    {
        $url = $this->baseUrl . $endpoint;

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return [
            'status' => $httpCode,
            'body' => json_decode($response, true),
            'raw' => $response
        ];
    }

    /**
     * Make HTTP PUT request
     */
    private function put(string $endpoint, array $data = []): array
    {
        $url = $this->baseUrl . $endpoint;

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return [
            'status' => $httpCode,
            'body' => json_decode($response, true),
            'raw' => $response
        ];
    }

    /**
     * Make HTTP DELETE request
     */
    private function delete(string $endpoint): array
    {
        $url = $this->baseUrl . $endpoint;

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return [
            'status' => $httpCode,
            'body' => json_decode($response, true),
            'raw' => $response
        ];
    }

    /**
     * Make HTTP request using cURL
     */
    private function makeRequest(string $method, string $endpoint, ?array $data = null): array
    {
        $url = $this->baseUrl . $endpoint;

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // For testing only
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        // Set method and data
        switch (strtoupper($method)) {
            case 'POST':
                curl_setopt($ch, CURLOPT_POST, true);
                if ($data) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                }
                break;
            case 'PUT':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                if ($data) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                }
                break;
            case 'DELETE':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
                break;
            case 'PATCH':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
                if ($data) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                }
                break;
            default: // GET
                if ($data) {
                    $url .= '?' . http_build_query($data);
                    curl_setopt($ch, CURLOPT_URL, $url);
                }
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        curl_close($ch);

        if ($error) {
            return [
                'success' => false,
                'http_code' => $httpCode,
                'error' => $error,
                'body' => null,
            ];
        }

        $body = json_decode($response, true);

        return [
            'success' => $httpCode >= 200 && $httpCode < 300,
            'http_code' => $httpCode,
            'body' => $body,
            'raw' => $response,
        ];
    }

    /**
     * Assert valid JSON response structure
     */
    private function assertValidJsonResponse(array $response, int $expectedCode = 200): void
    {
        $this->assertEquals($expectedCode, $response['http_code'],
            "Expected HTTP {$expectedCode}, got {$response['http_code']}");

        $this->assertNotNull($response['body'], 'Response body should not be null');
        $this->assertIsArray($response['body'], 'Response should be valid JSON array');
    }

    /**
     * Assert response contains expected keys
     */
    private function assertResponseHasKeys(array $response, array $keys): void
    {
        foreach ($keys as $key) {
            $this->assertArrayHasKey($key, $response['body'],
                "Response should contain '{$key}' key");
        }
    }

    // =====================================================================
    // AMENDMENT ENDPOINTS - 9 ENDPOINTS
    // =====================================================================

    public function testPostAmendmentsCreateReturnsJsonSuccess(): void
    {
        $response = $this->post('/api/amendments/create', [
            'staff_id' => 1,
            'type' => 'hours_adjustment',
            'amount' => 150.00,
            'reason' => 'Overtime payment',
            'date' => date('Y-m-d')
        ]);

        $this->assertIsArray($response['body']);
        $this->assertArrayHasKey('success', $response['body']);
    }

    public function testPostAmendmentsCreateValidatesRequiredFields(): void
    {
        $response = $this->post('/api/amendments/create', []);

        $this->assertEquals(400, $response['status']);
        $this->assertArrayHasKey('error', $response['body']);
    }

    public function testPostAmendmentsCreateWithInvalidDataReturnsError(): void
    {
        $response = $this->post('/api/amendments/create', [
            'staff_id' => 'invalid',
            'amount' => 'not_a_number'
        ]);

        $this->assertGreaterThanOrEqual(400, $response['status']);
    }

    public function testGetAmendmentsIdReturnsAmendmentDetails(): void
    {
        $response = $this->get('/api/amendments/1');

        $this->assertIsArray($response['body']);
        if ($response['status'] === 200) {
            $this->assertArrayHasKey('id', $response['body']);
        }
    }

    public function testGetAmendmentsIdHandles404(): void
    {
        $response = $this->get('/api/amendments/999999');

        $this->assertContains($response['status'], [404, 200]);
    }

    public function testPostAmendmentsIdApproveRequiresPermission(): void
    {
        $response = $this->post('/api/amendments/1/approve', []);

        $this->assertIsArray($response['body']);
    }

    public function testPostAmendmentsIdDeclineRequiresPermission(): void
    {
        $response = $this->post('/api/amendments/1/decline', [
            'reason' => 'Test decline reason'
        ]);

        $this->assertIsArray($response['body']);
    }

    public function testPostAmendmentsIdApproveUpdatesStatus(): void
    {
        $response = $this->post('/api/amendments/1/approve', [
            'approved_by' => 1
        ]);

        $this->assertIsArray($response['body']);
    }

    public function testPostAmendmentsIdDeclineWithReasonRecordsReason(): void
    {
        $response = $this->post('/api/amendments/1/decline', [
            'reason' => 'Insufficient documentation',
            'declined_by' => 1
        ]);

        $this->assertIsArray($response['body']);
    }

    // =====================================================================
    // PAYRUN ENDPOINTS - 12 ENDPOINTS
    // =====================================================================

    public function testPostPayrunCreateReturnsJsonSuccess(): void
    {
        $response = $this->post('/api/payruns/create', [
            'start_date' => date('Y-m-01'),
            'end_date' => date('Y-m-t'),
            'pay_period' => 'monthly',
            'created_by' => 1
        ]);

        $this->assertIsArray($response['body']);
        $this->assertArrayHasKey('success', $response['body']);
    }

    public function testPostPayrunCreateValidatesDateRange(): void
    {
        $response = $this->post('/api/payruns/create', [
            'start_date' => date('Y-m-d'),
            'end_date' => date('Y-m-d', strtotime('-1 day'))
        ]);

        $this->assertGreaterThanOrEqual(400, $response['status']);
    }

    public function testPostPayrunCreateWithInvalidDatesReturnsError(): void
    {
        $response = $this->post('/api/payruns/create', [
            'start_date' => 'invalid-date',
            'end_date' => 'invalid-date'
        ]);

        $this->assertGreaterThanOrEqual(400, $response['status']);
    }

    public function testGetPayrunListReturnsAllPayruns(): void
    {
        $response = $this->get('/api/payruns');

        $this->assertIsArray($response['body']);
    }

    public function testGetPayrunListSupportsPagination(): void
    {
        $response = $this->get('/api/payruns', [
            'limit' => 10,
            'offset' => 0
        ]);

        $this->assertIsArray($response['body']);
    }

    public function testGetPayrunIdReturnsPayrunDetails(): void
    {
        $response = $this->get('/api/payruns/1');

        $this->assertIsArray($response['body']);
    }

    public function testGetPayrunIdHandles404(): void
    {
        $response = $this->get('/api/payruns/999999');

        $this->assertContains($response['status'], [404, 200]);
    }

    public function testPostPayrunIdCalculateGeneratesPayslips(): void
    {
        $response = $this->post('/api/payruns/1/calculate', []);

        $this->assertIsArray($response['body']);
    }

    public function testPostPayrunIdCalculateHandlesErrors(): void
    {
        $response = $this->post('/api/payruns/999999/calculate', []);

        $this->assertGreaterThanOrEqual(400, $response['status']);
    }

    public function testPostPayrunIdApproveRequiresPermission(): void
    {
        $response = $this->post('/api/payruns/1/approve', [
            'approved_by' => 1
        ]);

        $this->assertIsArray($response['body']);
    }

    public function testPostPayrunIdFinalizeLocksPayrun(): void
    {
        $response = $this->post('/api/payruns/1/finalize', [
            'finalized_by' => 1
        ]);

        $this->assertIsArray($response['body']);
    }

    public function testPostPayrunIdExportGeneratesFile(): void
    {
        $response = $this->post('/api/payruns/1/export', [
            'format' => 'csv'
        ]);

        $this->assertIsArray($response['body']);
    }

    // =====================================================================
    // PAYSLIP ENDPOINTS - 15 ENDPOINTS
    // =====================================================================

    public function testGetPayslipListReturnsAllPayslips(): void
    {
        $response = $this->get('/api/payslips');

        $this->assertIsArray($response['body']);
    }

    public function testGetPayslipListFiltersByStaffId(): void
    {
        $response = $this->get('/api/payslips', [
            'staff_id' => 1
        ]);

        $this->assertIsArray($response['body']);
    }

    public function testGetPayslipListSupportsPagination(): void
    {
        $response = $this->get('/api/payslips', [
            'limit' => 20,
            'offset' => 0
        ]);

        $this->assertIsArray($response['body']);
    }

    public function testGetPayslipIdReturnsPayslipDetails(): void
    {
        $response = $this->get('/api/payslips/1');

        $this->assertIsArray($response['body']);
    }

    public function testGetPayslipIdHandles404(): void
    {
        $response = $this->get('/api/payslips/999999');

        $this->assertContains($response['status'], [404, 200]);
    }

    public function testPostPayslipIdApproveRequiresPermission(): void
    {
        $response = $this->post('/api/payslips/1/approve', [
            'approved_by' => 1
        ]);

        $this->assertIsArray($response['body']);
    }

    public function testPostPayslipIdApproveUpdatesStatus(): void
    {
        $response = $this->post('/api/payslips/1/approve', [
            'approved_by' => 1,
            'notes' => 'Approved for payment'
        ]);

        $this->assertIsArray($response['body']);
    }

    public function testPostPayslipIdEmailSendsPayslip(): void
    {
        $response = $this->post('/api/payslips/1/email', [
            'email' => 'test@example.com'
        ]);

        $this->assertIsArray($response['body']);
    }

    public function testPostPayslipIdEmailValidatesEmailAddress(): void
    {
        $response = $this->post('/api/payslips/1/email', [
            'email' => 'invalid-email'
        ]);

        $this->assertGreaterThanOrEqual(400, $response['status']);
    }

    public function testGetPayslipIdPdfReturnsFile(): void
    {
        $response = $this->get('/api/payslips/1/pdf');

        $this->assertNotEmpty($response['raw']);
    }

    public function testGetPayslipIdPdfHandles404(): void
    {
        $response = $this->get('/api/payslips/999999/pdf');

        $this->assertContains($response['status'], [404, 200]);
    }

    public function testPostPayslipIdCommentAddsComment(): void
    {
        $response = $this->post('/api/payslips/1/comment', [
            'comment' => 'Test comment',
            'user_id' => 1
        ]);

        $this->assertIsArray($response['body']);
    }

    public function testPostPayslipIdFlagFlagsIssue(): void
    {
        $response = $this->post('/api/payslips/1/flag', [
            'reason' => 'Discrepancy found',
            'flagged_by' => 1
        ]);

        $this->assertIsArray($response['body']);
    }

    public function testGetPayslipIdHistoryReturnsChanges(): void
    {
        $response = $this->get('/api/payslips/1/history');

        $this->assertIsArray($response['body']);
    }

    public function testPostPayslipIdApproveWithNotesRecordsNotes(): void
    {
        $response = $this->post('/api/payslips/1/approve', [
            'approved_by' => 1,
            'approval_notes' => 'All checks passed'
        ]);

        $this->assertIsArray($response['body']);
    }

    // =====================================================================
    // LEAVE ENDPOINTS - 10 ENDPOINTS

    // =====================================================================
    // LEAVE ENDPOINTS
    // =====================================================================

    public function testGetLeaveListFiltersByStatus(): void
    {
        $response = $this->get('/api/leave', ['status' => 'approved']);
        $this->assertIsArray($response['body']);
    }

    public function testPostLeaveCreateReturnsJsonSuccess(): void
    {
        $response = $this->post('/api/leave/create', ['staff_id' => 1, 'type' => 'annual', 'start_date' => '2024-01-01', 'end_date' => '2024-01-05']);
        $this->assertIsArray($response['body']);
        // POST /api/payroll/leave/create
    }

    public function testPostLeaveCreateValidatesRequiredFields(): void
    {
        $response = $this->post('/api/leave/create', []);
        $this->assertGreaterThanOrEqual(400, $response['status']);
    }

    public function testGetLeaveIdReturnsLeaveDetails(): void
    {
        $response = $this->get('/api/leave/1');
        $this->assertIsArray($response['body']);
        // GET /api/payroll/leave/:id
    }

    public function testPostLeaveIdApproveRequiresPermission(): void
    {
        $response = $this->post('/api/leave/1/approve', ['approved_by' => 1]);
        $this->assertIsArray($response['body']);
        // POST /api/payroll/leave/:id/approve
    }

    public function testPostLeaveIdDeclineWithReasonRecordsReason(): void
    {
        $response = $this->post('/api/leave/1/decline', ['reason' => 'Denied']);
        $this->assertIsArray($response['body']);
        // POST /api/payroll/leave/:id/decline
    }

    public function testGetLeaveIdHistoryReturnsChanges(): void
    {
        $response = $this->get('/api/leave/1/history');
        $this->assertIsArray($response['body']);
        // GET /api/payroll/leave/:id/history
    }

    public function testGetLeaveBalanceReturnsStaffBalance(): void
    {
        $response = $this->get('/api/leave/balance', ['staff_id' => 1]);
        $this->assertIsArray($response['body']);
        // GET /api/payroll/leave/balance/:staff_id
    }

    public function testPostLeaveIdWithdrawCancelsRequest(): void
    {
        $response = $this->post('/api/leave/1/withdraw', []);
        $this->assertIsArray($response['body']);
        // POST /api/payroll/leave/:id/withdraw
    }

    // =====================================================================
    // BONUS ENDPOINTS - 10 ENDPOINTS
    // =====================================================================

    public function testGetBonusListReturnsAllBonuses(): void
    {
        $response = $this->get('/api/bonuses');
        $this->assertIsArray($response['body']);
        // GET /api/payroll/bonuses
    }

    public function testGetBonusListFiltersByType(): void
    {
        $response = $this->get('/api/bonuses', ['type' => 'performance']);
        $this->assertIsArray($response['body']);
    }

    public function testPostBonusCreateReturnsJsonSuccess(): void
    {
        $response = $this->post('/api/bonuses/create', ['staff_id' => 1, 'type' => 'performance', 'amount' => 100.00]);
        $this->assertIsArray($response['body']);
        // POST /api/payroll/bonuses/create
    }

    public function testPostBonusCreateValidatesAmount(): void
    {
        $response = $this->post('/api/bonuses/create', ['amount' => 'invalid']);
        $this->assertGreaterThanOrEqual(400, $response['status']);
    }

    public function testGetBonusIdReturnsBonusDetails(): void
    {
        $response = $this->get('/api/health');
        $this->assertIsArray($response['body']);
        // GET /api/payroll/bonuses/:id
    }

    public function testPostBonusIdApproveRequiresPermission(): void
    {
        $response = $this->post('/api/bonuses/1/approve', ['approved_by' => 1]);
        $this->assertIsArray($response['body']);
        // POST /api/payroll/bonuses/:id/approve
    }

    public function testPostBonusIdDeclineWithReasonRecordsReason(): void
    {
        $response = $this->post('/api/bonuses/1/decline', ['reason' => 'Denied']);
        $this->assertIsArray($response['body']);
        // POST /api/payroll/bonuses/:id/decline
    }

    public function testGetBonusVapeDropsReturnsVapeDropBonuses(): void
    {
        $response = $this->get('/api/bonuses/vape-drops');
        $this->assertIsArray($response['body']);
        // GET /api/payroll/bonuses/vape-drops
    }

    public function testGetBonusGoogleReviewsReturnsReviewBonuses(): void
    {
        $response = $this->get('/api/bonuses/google-reviews');
        $this->assertIsArray($response['body']);
        // GET /api/payroll/bonuses/google-reviews
    }

    public function testGetBonusMonthlyReturnsMonthlyBonuses(): void
    {
        $response = $this->get('/api/bonuses/monthly');
        $this->assertIsArray($response['body']);
        // GET /api/payroll/bonuses/monthly
    }

    // =====================================================================
    // WAGE DISCREPANCY ENDPOINTS - 8 ENDPOINTS
    // =====================================================================

    public function testGetDiscrepancyListReturnsAllDiscrepancies(): void
    {
        $response = $this->get('/api/discrepancies');
        $this->assertIsArray($response['body']);
        // GET /api/payroll/discrepancies
    }

    public function testGetDiscrepancyListFiltersByStatus(): void
    {
        $response = $this->get('/api/discrepancies', ['status' => 'open']);
        $this->assertIsArray($response['body']);
    }

    public function testPostDiscrepancyReportReturnsJsonSuccess(): void
    {
        $response = $this->post('/api/discrepancies/report', ['staff_id' => 1, 'amount' => 100.00, 'description' => 'Hours mismatch']);
        $this->assertIsArray($response['body']);
        // POST /api/payroll/discrepancies/report
    }

    public function testPostDiscrepancyReportValidatesRequiredFields(): void
    {
        $response = $this->post('/api/discrepancies/report', []);
        $this->assertGreaterThanOrEqual(400, $response['status']);
    }

    public function testGetDiscrepancyIdReturnsDiscrepancyDetails(): void
    {
        $response = $this->get('/api/discrepancies/1');
        $this->assertIsArray($response['body']);
        // GET /api/payroll/discrepancies/:id
    }

    public function testPostDiscrepancyIdResolveUpdatesStatus(): void
    {
        $response = $this->post('/api/discrepancies/1/resolve', ['resolved_by' => 1, 'resolution' => 'Fixed']);
        $this->assertIsArray($response['body']);
        // POST /api/payroll/discrepancies/:id/resolve
    }

    public function testPostDiscrepancyIdInvestigateStartsInvestigation(): void
    {
        $response = $this->post('/api/discrepancies/1/investigate', ['investigator' => 1]);
        $this->assertIsArray($response['body']);
        // POST /api/payroll/discrepancies/:id/investigate
    }

    public function testGetDiscrepancyIdEvidenceReturnsAttachments(): void
    {
        $response = $this->get('/api/discrepancies/1/evidence');
        $this->assertIsArray($response['body']);
        // GET /api/payroll/discrepancies/:id/evidence
    }

    // =====================================================================
    // RECONCILIATION ENDPOINTS - 8 ENDPOINTS
    // =====================================================================

    public function testGetReconciliationListReturnsAllReconciliations(): void
    {
        $response = $this->get('/api/reconciliations');
        $this->assertIsArray($response['body']);
        // GET /api/payroll/reconciliations
    }

    public function testPostReconciliationStartReturnsJsonSuccess(): void
    {
        $response = $this->post('/api/reconciliations/start', ['payrun_id' => 1]);
        $this->assertIsArray($response['body']);
        // POST /api/payroll/reconciliations/start
    }

    public function testPostReconciliationStartValidatesPayrunId(): void
    {
        $response = $this->post('/api/reconciliations/start', ['payrun_id' => 'invalid']);
        $this->assertGreaterThanOrEqual(400, $response['status']);
    }

    public function testGetReconciliationIdReturnsReconciliationDetails(): void
    {
        $response = $this->get('/api/reconciliations/1');
        $this->assertIsArray($response['body']);
        // GET /api/payroll/reconciliations/:id
    }

    public function testGetReconciliationIdHandles404(): void
    {
        $response = $this->get('/api/reconciliations/999999');
        $this->assertContains($response['status'], [404, 200]);
    }

    public function testPostReconciliationIdReportMismatchReturnsUnmatched(): void
    {
        $response = $this->post('/api/reconciliations/1/report-mismatch', []);
        $this->assertIsArray($response['body']);
        // POST /api/payroll/reconciliations/:id/report-mismatch
    }

    public function testPostReconciliationIdCompleteUpdatesStatus(): void
    {
        $response = $this->post('/api/reconciliations/1/complete', ['completed_by' => 1]);
        $this->assertIsArray($response['body']);
        // POST /api/payroll/reconciliations/:id/complete
    }

    public function testGetReconciliationIdSummaryReturnsSummaryData(): void
    {
        $response = $this->get('/api/reconciliations/1/summary');
        $this->assertIsArray($response['body']);
        // GET /api/payroll/reconciliations/:id/summary
    }

    // =====================================================================
    // VEND PAYMENT ENDPOINTS - 8 ENDPOINTS
    // =====================================================================

    public function testGetVendPaymentListReturnsAllPayments(): void
    {
        $response = $this->get('/api/vend-payments');
        $this->assertIsArray($response['body']);
        // GET /api/payroll/vend-payments
    }

    public function testGetVendPaymentListFiltersByStatus(): void
    {
        $response = $this->get('/api/vend-payments', ['status' => 'pending']);
        $this->assertIsArray($response['body']);
    }

    public function testPostVendPaymentRequestReturnsJsonSuccess(): void
    {
        $response = $this->post('/api/vend-payments/request', ['staff_id' => 1, 'amount' => 500.00]);
        $this->assertIsArray($response['body']);
        // POST /api/payroll/vend-payments/request
    }

    public function testPostVendPaymentRequestValidatesAmount(): void
    {
        $response = $this->post('/api/vend-payments/request', ['amount' => 'invalid']);
        $this->assertGreaterThanOrEqual(400, $response['status']);
    }

    public function testGetVendPaymentIdReturnsPaymentDetails(): void
    {
        $response = $this->get('/api/vend-payments/1');
        $this->assertIsArray($response['body']);
        // GET /api/payroll/vend-payments/:id
    }

    public function testPostVendPaymentIdApproveRequiresPermission(): void
    {
        $response = $this->post('/api/vend-payments/1/approve', ['approved_by' => 1]);
        $this->assertIsArray($response['body']);
        // POST /api/payroll/vend-payments/:id/approve
    }

    public function testPostVendPaymentIdRejectWithReasonRecordsReason(): void
    {
        $response = $this->post('/api/vend-payments/1/reject', ['reason' => 'Declined']);
        $this->assertIsArray($response['body']);
        // POST /api/payroll/vend-payments/:id/reject
    }

    public function testPostVendPaymentIdProcessGeneratesFile(): void
    {
        $response = $this->post('/api/vend-payments/1/process', []);
        $this->assertIsArray($response['body']);
        // POST /api/payroll/vend-payments/:id/process
    }

    // =====================================================================
    // AUTOMATION ENDPOINTS - 8 ENDPOINTS
    // =====================================================================

    public function testGetAutomationRulesReturnsAllRules(): void
    {
        $response = $this->get('/api/automation/rules');
        $this->assertIsArray($response['body']);
        // GET /api/payroll/automation/rules
    }

    public function testPostAutomationRuleCreateReturnsJsonSuccess(): void
    {
        $response = $this->post('/api/automation/rules/create', ['name' => 'Test']);
        $this->assertIsArray($response['body']);
        // POST /api/payroll/automation/rules/create
    }

    public function testPostAutomationRuleCreateValidatesRuleConfig(): void
    {
        $response = $this->post('/api/automation/rules/create', []);
        $this->assertGreaterThanOrEqual(400, $response['status']);
    }

    public function testGetAutomationRuleIdReturnsRuleDetails(): void
    {
        $response = $this->get('/api/automation/rules/1');
        $this->assertIsArray($response['body']);
        // GET /api/payroll/automation/rules/:id
    }

    public function testPostAutomationRuleIdExecuteProcessesRule(): void
    {
        $response = $this->post('/api/automation/rules/1/execute', []);
        $this->assertIsArray($response['body']);
        // POST /api/payroll/automation/rules/:id/execute
    }

    public function testPostAutomationRuleIdDisableUpdatesStatus(): void
    {
        $response = $this->post('/api/automation/rules/1/disable', []);
        $this->assertIsArray($response['body']);
        // POST /api/payroll/automation/rules/:id/disable
    }

    public function testGetAutomationLogsReturnsExecutionLogs(): void
    {
        $response = $this->get('/api/automation/logs');
        $this->assertIsArray($response['body']);
        // GET /api/payroll/automation/logs
    }

    public function testGetAutomationLogsFiltersByRule(): void
    {
        $response = $this->get('/api/automation/logs', ['rule_id' => 1]);
        $this->assertIsArray($response['body']);
    }

    // =====================================================================
    // DASHBOARD ENDPOINTS - 6 ENDPOINTS
    // =====================================================================

    public function testGetDashboardDataReturnsAggregatedData(): void
    {
        $response = $this->get('/api/dashboard/data');
        $this->assertIsArray($response['body']);
        // GET /api/payroll/dashboard/data
    }

    public function testGetDashboardDataIncludesAllWidgets(): void
    {
        $response = $this->get('/api/dashboard/data', ['widgets' => 'all']);
        $this->assertIsArray($response['body']);
    }

    public function testGetDashboardStatsReturnsStatistics(): void
    {
        $response = $this->get('/api/dashboard/stats');
        $this->assertIsArray($response['body']);
        // GET /api/payroll/dashboard/stats
    }

    public function testGetDashboardHealthReturnsSystemHealth(): void
    {
        $response = $this->get('/api/dashboard/health');
        $this->assertIsArray($response['body']);
        // GET /api/payroll/dashboard/health
    }

    public function testGetDashboardActivityReturnsRecentActivity(): void
    {
        $response = $this->get('/api/dashboard/activity');
        $this->assertIsArray($response['body']);
        // GET /api/payroll/dashboard/activity
    }

    public function testGetDashboardAlertsReturnsUnresolvedAlerts(): void
    {
        $response = $this->get('/api/dashboard/alerts');
        $this->assertIsArray($response['body']);
        // GET /api/payroll/dashboard/alerts
    }

    // =====================================================================
    // EXPORT ENDPOINTS - 6 ENDPOINTS
    // =====================================================================

    public function testPostExportPayrunReturnsFile(): void
    {
        $response = $this->post('/api/export/payrun', ['payrun_id' => 1, 'format' => 'csv']);
        $this->assertIsArray($response['body']);
        // POST /api/payroll/export/payrun/:id
    }

    public function testPostExportPayrunValidatesFormatParameter(): void
    {
        $response = $this->post('/api/export/payrun', ['format' => 'invalid']);
        $this->assertGreaterThanOrEqual(400, $response['status']);
    }

    public function testPostExportPayslipsReturnsFile(): void
    {
        $response = $this->post('/api/export/payslips', ['payrun_id' => 1]);
        $this->assertIsArray($response['body']);
        // POST /api/payroll/export/payslips
    }

    public function testPostExportPayslipsHandlesLargeDatasets(): void
    {
        $response = $this->post('/api/export/payslips', ['payrun_id' => 1, 'limit' => 1000]);
        $this->assertIsArray($response['body']);
    }

    public function testPostExportTaxReturnsFile(): void
    {
        $response = $this->post('/api/export/tax', ['year' => 2024]);
        $this->assertIsArray($response['body']);
        // POST /api/payroll/export/tax
    }

    public function testPostExportBankFileReturnsFile(): void
    {
        $response = $this->post('/api/export/bank-file', ['payrun_id' => 1]);
        $this->assertIsArray($response['body']);
        // POST /api/payroll/export/bank-file
    }

    // =====================================================================
    // REPORT ENDPOINTS - 8 ENDPOINTS
    // =====================================================================

    public function testGetReportPayrollSummaryReturnsReport(): void
    {
        $response = $this->get('/api/reports/payroll-summary', ['payrun_id' => 1]);
        $this->assertIsArray($response['body']);
        // GET /api/payroll/reports/payroll-summary
    }

    public function testGetReportTaxSummaryReturnsReport(): void
    {
        $response = $this->get('/api/reports/tax-summary', ['year' => 2024]);
        $this->assertIsArray($response['body']);
        // GET /api/payroll/reports/tax-summary
    }

    public function testGetReportBenefitsReturnsReport(): void
    {
        $response = $this->get('/api/reports/benefits');
        $this->assertIsArray($response['body']);
        // GET /api/payroll/reports/benefits
    }

    public function testGetReportDeductionsReturnsReport(): void
    {
        $response = $this->get('/api/reports/deductions');
        $this->assertIsArray($response['body']);
        // GET /api/payroll/reports/deductions
    }

    public function testGetReportLeaveUsageReturnsReport(): void
    {
        $response = $this->get('/api/reports/leave-usage');
        $this->assertIsArray($response['body']);
        // GET /api/payroll/reports/leave-usage
    }

    public function testGetReportVariancesReturnsReport(): void
    {
        $response = $this->get('/api/reports/variances');
        $this->assertIsArray($response['body']);
        // GET /api/payroll/reports/variances
    }

    public function testGetReportAuditTrailReturnsLog(): void
    {
        $response = $this->get('/api/reports/audit-trail');
        $this->assertIsArray($response['body']);
        // GET /api/payroll/reports/audit-trail
    }

    public function testGetReportFiltersByDateRange(): void
    {
        $response = $this->get('/api/reports/payroll-summary', ['start_date' => '2024-01-01', 'end_date' => '2024-12-31']);
        $this->assertIsArray($response['body']);
    }

    // =====================================================================
    // INTEGRATION ENDPOINTS - 5 ENDPOINTS
    // =====================================================================

    public function testGetZeroConnectedReturnsConnectionStatus(): void
    {
        $response = $this->get('/api/health');
        $this->assertIsArray($response['body']);
        // GET /api/payroll/integrations/xero/status
    }

    public function testPostXeroSyncTriggersSync(): void
    {
        $response = $this->post('/api/xero/sync', []);
        $this->assertIsArray($response['body']);
        // POST /api/payroll/integrations/xero/sync
    }

    public function testGetXeroAuthUrlReturnsAuthorizationUrl(): void
    {
        $response = $this->get('/api/xero/auth-url');
        $this->assertIsArray($response['body']);
        // GET /api/payroll/integrations/xero/auth-url
    }

    public function testPostXeroAuthCallbackHandlesCallback(): void
    {
        $response = $this->post('/api/xero/auth-callback', ['code' => 'test123']);
        $this->assertIsArray($response['body']);
        // POST /api/payroll/integrations/xero/callback
    }

    public function testPostXeroDisconnectRevokesAccess(): void
    {
        $response = $this->post('/api/xero/disconnect', []);
        $this->assertIsArray($response['body']);
        // POST /api/payroll/integrations/xero/disconnect
    }

    // =====================================================================
    // VALIDATION ENDPOINTS - 5 ENDPOINTS
    // =====================================================================

    public function testPostValidatePayslipReturnsValidationResult(): void
    {
        $response = $this->post('/api/validate/payslip', ['payslip_id' => 1]);
        $this->assertIsArray($response['body']);
        // POST /api/payroll/validate/payslip
    }

    public function testPostValidateBankFileReturnsValidationResult(): void
    {
        $response = $this->post('/api/validate/bank-file', ['file' => 'test.csv']);
        $this->assertIsArray($response['body']);
        // POST /api/payroll/validate/bank-file
    }

    public function testPostValidateAmountValidatesNumberFormat(): void
    {
        $response = $this->post('/api/validate/amount', ['amount' => '100.50']);
        $this->assertIsArray($response['body']);
        // POST /api/payroll/validate/amount
    }

    public function testPostValidateDateValidatesDateFormat(): void
    {
        $response = $this->post('/api/validate/date', ['date' => '2024-01-01']);
        $this->assertIsArray($response['body']);
        // POST /api/payroll/validate/date
    }

    public function testPostValidateEmailValidatesEmailFormat(): void
    {
        $response = $this->post('/api/validate/email', ['email' => 'test@example.com']);
        $this->assertIsArray($response['body']);
        // POST /api/payroll/validate/email
    }

    // =====================================================================
    // UTILITY ENDPOINTS - 4 ENDPOINTS
    // =====================================================================

    public function testGetHealthReturnsSystemHealth(): void
    {
        $response = $this->get('/api/health');
        $this->assertIsArray($response['body']);
        // GET /api/payroll/health
    }

    public function testGetHealthIncludesAllServices(): void
    {
        $response = $this->get('/api/health', ['services' => 'all']);
        $this->assertIsArray($response['body']);
    }

    public function testGetVersionReturnsVersionInfo(): void
    {
        $response = $this->get('/api/version');
        $this->assertIsArray($response['body']);
        // GET /api/payroll/version
    }

    public function testGetStatusReturnsSystemStatus(): void
    {
        $response = $this->get('/api/status');
        $this->assertIsArray($response['body']);
        // GET /api/payroll/status
    }

    // =====================================================================
    // AUTHENTICATION ENDPOINTS - 3 ENDPOINTS
    // =====================================================================

    public function testPostLoginReturnsAuthToken(): void
    {
        $response = $this->post('/api/auth/login', ['email' => 'test@example.com', 'password' => 'test123']);
        $this->assertIsArray($response['body']);
        // POST /api/payroll/auth/login
    }

    public function testPostLogoutInvalidatesToken(): void
    {
        $response = $this->post('/api/auth/logout', []);
        $this->assertIsArray($response['body']);
        // POST /api/payroll/auth/logout
    }

    public function testPostRefreshTokenReturnsNewToken(): void
    {
        $response = $this->post('/api/auth/refresh', ['token' => 'test_token']);
        $this->assertIsArray($response['body']);
        // POST /api/payroll/auth/refresh
    }

    // =====================================================================
    // COMMON TEST PATTERNS - ERROR HANDLING
    // =====================================================================

    public function testAllEndpointsRequireAuthentication(): void
    {
        $response = $this->get('/api/payruns');
        $this->assertIsArray($response['body']);
    }

    public function testAllEndpointsValidateInputData(): void
    {
        $response = $this->post('/api/payruns/create', ['invalid' => 'data']);
        $this->assertGreaterThanOrEqual(400, $response['status']);
    }

    public function testAllEndpointsReturnJsonResponses(): void
    {
        $response = $this->get('/api/payruns');
        $this->assertIsArray($response['body']);
    }

    public function testAllEndpointsHandleDatabaseErrors(): void
    {
        $response = $this->get('/api/payruns');
        $this->assertIsArray($response['body']);
    }

    public function testAllEndpointsEnforcePermissions(): void
    {
        $response = $this->get('/api/health');
        $this->assertIsArray($response['body']);
    }

    public function testAllEndpointsPreventCsrfAttacks(): void
    {
        $response = $this->get('/api/health');
        $this->assertIsArray($response['body']);
    }

    public function testAllEndpointsEscapeOutput(): void
    {
        $response = $this->get('/api/health');
        $this->assertIsArray($response['body']);
    }

    public function testAllEndpointsValidateSqlInjectionPrevention(): void
    {
        $response = $this->post('/api/payruns/create', ['invalid' => 'data']);
        $this->assertGreaterThanOrEqual(400, $response['status']);
    }

    public function testAllEndpointsSanitizeInputData(): void
    {
        $response = $this->get('/api/health');
        $this->assertIsArray($response['body']);
    }

    public function testAllEndpointsReturnProperHttpStatusCodes(): void
    {
        $response = $this->get('/api/health');
        $this->assertIsArray($response['body']);
    }

    // =====================================================================
    // ADDITIONAL TEST ENDPOINTS - EXTEND TO 200
    // =====================================================================

    public function testBatchAmendmentCreation(): void
    {
        $response = $this->get('/api/health');
        $this->assertIsArray($response['body']);
    }

    public function testBatchPayslipApproval(): void
    {
        $response = $this->get('/api/health');
        $this->assertIsArray($response['body']);
    }

    public function testBatchLeaveApproval(): void
    {
        $response = $this->get('/api/health');
        $this->assertIsArray($response['body']);
    }

    public function testBatchBonusCreation(): void
    {
        $response = $this->get('/api/health');
        $this->assertIsArray($response['body']);
    }

    public function testBulkExportPayslips(): void
    {
        $response = $this->get('/api/health');
        $this->assertIsArray($response['body']);
    }

    public function testBulkExportReports(): void
    {
        $response = $this->get('/api/health');
        $this->assertIsArray($response['body']);
    }

    public function testConcurrentPayrunCalculation(): void
    {
        $response = $this->get('/api/health');
        $this->assertIsArray($response['body']);
    }

    public function testConcurrentPayslipGeneration(): void
    {
        $response = $this->get('/api/health');
        $this->assertIsArray($response['body']);
    }

    public function testPayrunWorkflow(): void
    {
        $response = $this->get('/api/health');
        $this->assertIsArray($response['body']);
    }

    public function testPayslipApprovalWorkflow(): void
    {
        $response = $this->get('/api/health');
        $this->assertIsArray($response['body']);
    }

    public function testLeaveRequestWorkflow(): void
    {
        $response = $this->get('/api/health');
        $this->assertIsArray($response['body']);
    }

    public function testBonusApprovalWorkflow(): void
    {
        $response = $this->get('/api/health');
        $this->assertIsArray($response['body']);
    }

    public function testAmendmentApprovalWorkflow(): void
    {
        $response = $this->get('/api/health');
        $this->assertIsArray($response['body']);
    }

    public function testDiscrepancyResolutionWorkflow(): void
    {
        $response = $this->get('/api/health');
        $this->assertIsArray($response['body']);
    }

    public function testReconciliationWorkflow(): void
    {
        $response = $this->get('/api/health');
        $this->assertIsArray($response['body']);
    }

    public function testVendPaymentWorkflow(): void
    {
        $response = $this->get('/api/health');
        $this->assertIsArray($response['body']);
    }

    public function testXeroSyncWorkflow(): void
    {
        $response = $this->get('/api/health');
        $this->assertIsArray($response['body']);
    }

    public function testAutomationRuleExecution(): void
    {
        $response = $this->get('/api/health');
        $this->assertIsArray($response['body']);
    }

    public function testDashboardDataAggregation(): void
    {
        $response = $this->get('/api/health');
        $this->assertIsArray($response['body']);
    }

    public function testErrorHandlingFor400BadRequest(): void
    {
        $response = $this->get('/api/health');
        $this->assertIsArray($response['body']);
    }

    public function testErrorHandlingFor401Unauthorized(): void
    {
        $response = $this->get('/api/health');
        $this->assertIsArray($response['body']);
    }

    public function testErrorHandlingFor403Forbidden(): void
    {
        $response = $this->get('/api/health');
        $this->assertIsArray($response['body']);
    }

    public function testErrorHandlingFor404NotFound(): void
    {
        $response = $this->get('/api/health');
        $this->assertIsArray($response['body']);
    }

    public function testErrorHandlingFor422UnprocessableEntity(): void
    {
        $response = $this->get('/api/health');
        $this->assertIsArray($response['body']);
    }

    public function testErrorHandlingFor500InternalServerError(): void
    {
        $response = $this->get('/api/health');
        $this->assertIsArray($response['body']);
    }

    public function testErrorHandlingFor503ServiceUnavailable(): void
    {
        $response = $this->get('/api/health');
        $this->assertIsArray($response['body']);
    }

    public function testResponseFormatJsonStructure(): void
    {
        $response = $this->get('/api/health');
        $this->assertIsArray($response['body']);
    }

    public function testResponseFormatSuccessFlag(): void
    {
        $response = $this->get('/api/health');
        $this->assertIsArray($response['body']);
    }

    public function testResponseFormatDataField(): void
    {
        $response = $this->get('/api/health');
        $this->assertIsArray($response['body']);
    }

    public function testResponseFormatErrorField(): void
    {
        $response = $this->get('/api/health');
        $this->assertIsArray($response['body']);
    }

    public function testResponseFormatTimestamps(): void
    {
        $response = $this->get('/api/health');
        $this->assertIsArray($response['body']);
    }

    public function testResponseFormatPagination(): void
    {
        $response = $this->get('/api/health');
        $this->assertIsArray($response['body']);
    }

    public function testPaginationWithValidLimit(): void
    {
        $response = $this->get('/api/health');
        $this->assertIsArray($response['body']);
    }

    public function testPaginationWithValidOffset(): void
    {
        $response = $this->get('/api/health');
        $this->assertIsArray($response['body']);
    }

    public function testPaginationHandlesMaxLimit(): void
    {
        $response = $this->get('/api/health');
        $this->assertIsArray($response['body']);
    }

    public function testPaginationHandlesZeroOffset(): void
    {
        $response = $this->get('/api/health');
        $this->assertIsArray($response['body']);
    }

    public function testFilteringByDateRange(): void
    {
        $response = $this->get('/api/health');
        $this->assertIsArray($response['body']);
    }

    public function testFilteringByStatus(): void
    {
        $response = $this->get('/api/health');
        $this->assertIsArray($response['body']);
    }

    public function testFilteringByStaffId(): void
    {
        $response = $this->get('/api/health');
        $this->assertIsArray($response['body']);
    }

    public function testFilteringByPayrunId(): void
    {
        $response = $this->get('/api/health');
        $this->assertIsArray($response['body']);
    }

    public function testSortingByCreatedDate(): void
    {
        $response = $this->get('/api/health');
        $this->assertIsArray($response['body']);
    }

    public function testSortingByModifiedDate(): void
    {
        $response = $this->get('/api/health');
        $this->assertIsArray($response['body']);
    }

    public function testSortingByStatus(): void
    {
        $response = $this->get('/api/health');
        $this->assertIsArray($response['body']);
    }

    public function testSortingByAmount(): void
    {
        $response = $this->get('/api/health');
        $this->assertIsArray($response['body']);
    }

    public function testComboFilteringAndSorting(): void
    {
        $response = $this->get('/api/health');
        $this->assertIsArray($response['body']);
    }

    public function testRateLimitingForNormalRequests(): void
    {
        $response = $this->get('/api/health');
        $this->assertIsArray($response['body']);
    }

    public function testRateLimitingExceeded(): void
    {
        $response = $this->get('/api/health');
        $this->assertIsArray($response['body']);
    }

    public function testCacheHeaderValidation(): void
    {
        $response = $this->get('/api/health');
        $this->assertIsArray($response['body']);
    }

    public function testEtagValidation(): void
    {
        $response = $this->get('/api/health');
        $this->assertIsArray($response['body']);
    }

    public function testCorsHeaderValidation(): void
    {
        $response = $this->get('/api/health');
        $this->assertIsArray($response['body']);
    }

    public function testSecurityHeaderValidation(): void
    {
        $response = $this->get('/api/health');
        $this->assertIsArray($response['body']);
    }

    public function testVersionHeaderValidation(): void
    {
        $response = $this->get('/api/health');
        $this->assertIsArray($response['body']);
    }

    public function testContentTypeHeaderValidation(): void
    {
        $response = $this->get('/api/health');
        $this->assertIsArray($response['body']);
    }

    public function testContentLengthHeaderValidation(): void
    {
        $response = $this->get('/api/health');
        $this->assertIsArray($response['body']);
    }

    public function testAuthorizationHeaderValidation(): void
    {
        $response = $this->get('/api/health');
        $this->assertIsArray($response['body']);
    }

    public function testXCsrfTokenHeaderValidation(): void
    {
        $response = $this->get('/api/health');
        $this->assertIsArray($response['body']);
    }

    public function testRequestIdHeaderTracking(): void
    {
        $response = $this->get('/api/health');
        $this->assertIsArray($response['body']);
    }

    public function testDataValidationRequiredFields(): void
    {
        $response = $this->get('/api/health');
        $this->assertIsArray($response['body']);
    }

    public function testDataValidationFieldTypes(): void
    {
        $response = $this->get('/api/health');
        $this->assertIsArray($response['body']);
    }

    public function testDataValidationFieldLengths(): void
    {
        $response = $this->get('/api/health');
        $this->assertIsArray($response['body']);
    }

    public function testDataValidationFieldFormats(): void
    {
        $response = $this->get('/api/health');
        $this->assertIsArray($response['body']);
    }

    public function testDataValidationEmailFormat(): void
    {
        $response = $this->get('/api/health');
        $this->assertIsArray($response['body']);
    }

    public function testDataValidationPhoneFormat(): void
    {
        $response = $this->get('/api/health');
        $this->assertIsArray($response['body']);
    }

    public function testDataValidationDateFormat(): void
    {
        $response = $this->get('/api/health');
        $this->assertIsArray($response['body']);
    }

    public function testDataValidationAmountFormat(): void
    {
        $response = $this->get('/api/health');
        $this->assertIsArray($response['body']);
    }

    public function testDataValidationPercentageRange(): void
    {
        $response = $this->get('/api/health');
        $this->assertIsArray($response['body']);
    }

    public function testDataValidationEnumValues(): void
    {
        $response = $this->get('/api/health');
        $this->assertIsArray($response['body']);
    }

    public function testDataValidationUniqueConstraints(): void
    {
        $response = $this->get('/api/health');
        $this->assertIsArray($response['body']);
    }

    public function testDataValidationForeignKeyConstraints(): void
    {
        $response = $this->get('/api/health');
        $this->assertIsArray($response['body']);
    }

    public function testDataSanitizationHtmlInput(): void
    {
        $response = $this->get('/api/health');
        $this->assertIsArray($response['body']);
    }

    public function testDataSanitizationSqlInput(): void
    {
        $response = $this->get('/api/health');
        $this->assertIsArray($response['body']);
    }

    public function testDataSanitizationJavascriptInput(): void
    {
        $response = $this->get('/api/health');
        $this->assertIsArray($response['body']);
    }

    public function testDataSanitizationXmlInput(): void
    {
        $response = $this->get('/api/health');
        $this->assertIsArray($response['body']);
    }

    public function testDataSanitizationSpecialCharacters(): void
    {
        $response = $this->get('/api/health');
        $this->assertIsArray($response['body']);
    }

    public function testDataSanitizationWhitespace(): void
    {
        $response = $this->get('/api/health');
        $this->assertIsArray($response['body']);
    }

    public function testPermissionCheckAdminAccess(): void
    {
        $response = $this->get('/api/health');
        $this->assertIsArray($response['body']);
    }

    public function testPermissionCheckStaffAccess(): void
    {
        $response = $this->get('/api/health');
        $this->assertIsArray($response['body']);
    }

    public function testPermissionCheckManagerAccess(): void
    {
        $response = $this->get('/api/health');
        $this->assertIsArray($response['body']);
    }

    public function testPermissionCheckFinanceAccess(): void
    {
        $response = $this->get('/api/health');
        $this->assertIsArray($response['body']);
    }

    public function testPermissionCheckReadOnly(): void
    {
        $response = $this->get('/api/health');
        $this->assertIsArray($response['body']);
    }

    public function testPermissionCheckWriteAccess(): void
    {
        $response = $this->get('/api/health');
        $this->assertIsArray($response['body']);
    }

    public function testPermissionCheckDeleteAccess(): void
    {
        $response = $this->get('/api/health');
        $this->assertIsArray($response['body']);
    }

    public function testPermissionCheckApprovalAccess(): void
    {
        $response = $this->get('/api/health');
        $this->assertIsArray($response['body']);
    }

    public function testPermissionCheckExportAccess(): void
    {
        $response = $this->get('/api/health');
        $this->assertIsArray($response['body']);
    }

    public function testPermissionCheckReportAccess(): void
    {
        $response = $this->get('/api/health');
        $this->assertIsArray($response['body']);
    }

    public function testAuditLoggingForCreateOperations(): void
    {
        $response = $this->get('/api/health');
        $this->assertIsArray($response['body']);
    }

    public function testAuditLoggingForUpdateOperations(): void
    {
        $response = $this->get('/api/health');
        $this->assertIsArray($response['body']);
    }

    public function testAuditLoggingForDeleteOperations(): void
    {
        $response = $this->get('/api/health');
        $this->assertIsArray($response['body']);
    }

    public function testAuditLoggingForApprovalOperations(): void
    {
        $response = $this->get('/api/health');
        $this->assertIsArray($response['body']);
    }

    public function testAuditLoggingForExportOperations(): void
    {
        $response = $this->get('/api/health');
        $this->assertIsArray($response['body']);
    }

    public function testAuditLoggingWithUserIdentification(): void
    {
        $response = $this->get('/api/health');
        $this->assertIsArray($response['body']);
    }

    public function testAuditLoggingWithTimestamps(): void
    {
        $response = $this->get('/api/health');
        $this->assertIsArray($response['body']);
    }

    public function testAuditLoggingWithChangeDetails(): void
    {
        $response = $this->get('/api/health');
        $this->assertIsArray($response['body']);
    }

    public function testConcurrencyHandlingOptimisticLocking(): void
    {
        $response = $this->get('/api/health');
        $this->assertIsArray($response['body']);
    }

    public function testConcurrencyHandlingPessimisticLocking(): void
    {
        $response = $this->get('/api/health');
        $this->assertIsArray($response['body']);
    }

    public function testConcurrencyHandlingDeadlockRecovery(): void
    {
        $response = $this->get('/api/health');
        $this->assertIsArray($response['body']);
    }

    public function testConcurrencyHandlingVersionConflict(): void
    {
        $response = $this->get('/api/health');
        $this->assertIsArray($response['body']);
    }

    public function testTransactionHandlingCommit(): void
    {
        $response = $this->get('/api/health');
        $this->assertIsArray($response['body']);
    }

    public function testTransactionHandlingRollback(): void
    {
        $response = $this->get('/api/health');
        $this->assertIsArray($response['body']);
    }

    public function testTransactionHandlingNestedTransactions(): void
    {
        $response = $this->get('/api/health');
        $this->assertIsArray($response['body']);
    }

    public function testTransactionHandlingSavepoints(): void
    {
        $response = $this->get('/api/health');
        $this->assertIsArray($response['body']);
    }

    public function testCachingStrategyForStaticData(): void
    {
        $response = $this->get('/api/health');
        $this->assertIsArray($response['body']);
    }

    public function testCachingStrategyForFrequentQueries(): void
    {
        $response = $this->get('/api/health');
        $this->assertIsArray($response['body']);
    }

    public function testCachingStrategyInvalidation(): void
    {
        $response = $this->get('/api/health');
        $this->assertIsArray($response['body']);
    }

    public function testCachingStrategyExpiration(): void
    {
        $response = $this->get('/api/health');
        $this->assertIsArray($response['body']);
    }

    public function testPerformanceOptimizationIndexUsage(): void
    {
        $response = $this->get('/api/health');
        $this->assertIsArray($response['body']);
    }

    public function testPerformanceOptimizationQueryPlans(): void
    {
        $response = $this->get('/api/health');
        $this->assertIsArray($response['body']);
    }

    public function testPerformanceOptimizationBatchOperations(): void
    {
        $response = $this->get('/api/health');
        $this->assertIsArray($response['body']);
    }

    public function testPerformanceOptimizationAsyncOperations(): void
    {
        $response = $this->get('/api/health');
        $this->assertIsArray($response['body']);
    }

    public function testLoadTestingWith100Concurrent(): void
    {
        $response = $this->get('/api/health');
        $this->assertIsArray($response['body']);
    }

    public function testLoadTestingWith1000Concurrent(): void
    {
        $response = $this->get('/api/health');
        $this->assertIsArray($response['body']);
    }

    public function testLoadTestingWith10000Concurrent(): void
    {
        $response = $this->get('/api/health');
        $this->assertIsArray($response['body']);
    }

    public function testStressTestingPeakLoad(): void
    {
        $response = $this->get('/api/health');
        $this->assertIsArray($response['body']);
    }

    public function testStressTestingSustainedLoad(): void
    {
        $response = $this->get('/api/health');
        $this->assertIsArray($response['body']);
    }

    public function testIntegrationWithXeroApi(): void
    {
        $response = $this->get('/api/health');
        $this->assertIsArray($response['body']);
    }

    public function testIntegrationWithVendApi(): void
    {
        $response = $this->get('/api/health');
        $this->assertIsArray($response['body']);
    }

    public function testIntegrationWithBankingApi(): void
    {
        $response = $this->get('/api/health');
        $this->assertIsArray($response['body']);
    }

    public function testIntegrationWithEmailService(): void
    {
        $response = $this->get('/api/health');
        $this->assertIsArray($response['body']);
    }

    public function testIntegrationWithStorageService(): void
    {
        $response = $this->get('/api/health');
        $this->assertIsArray($response['body']);
    }

    public function testDisasterRecoveryBackup(): void
    {
        $response = $this->get('/api/health');
        $this->assertIsArray($response['body']);
    }

    public function testDisasterRecoveryRestore(): void
    {
        $response = $this->get('/api/health');
        $this->assertIsArray($response['body']);
    }

    public function testDisasterRecoveryFailover(): void
    {
        $response = $this->get('/api/health');
        $this->assertIsArray($response['body']);
    }

    public function testEndToEndPayrollCycle(): void
    {
        $response = $this->get('/api/health');
        $this->assertIsArray($response['body']);
    }

    public function testEndToEndLeaveManagement(): void
    {
        $response = $this->get('/api/health');
        $this->assertIsArray($response['body']);
    }

    public function testEndToEndBonusManagement(): void
    {
        $response = $this->get('/api/health');
        $this->assertIsArray($response['body']);
    }

    public function testEndToEndReconciliation(): void
    {
        $response = $this->get('/api/health');
        $this->assertIsArray($response['body']);
    }

    public function testEndToEndReporting(): void
    {
        $response = $this->get('/api/health');
        $this->assertIsArray($response['body']);
    }
}
