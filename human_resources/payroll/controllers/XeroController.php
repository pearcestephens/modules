<?php
declare(strict_types=1);

namespace PayrollModule\Controllers;

/**
 * Xero Controller
 *
 * HTTP API endpoints for Xero integration:
 * - POST /api/payroll/xero/payrun/create
 * - GET /api/payroll/xero/payrun/:id
 * - POST /api/payroll/xero/payments/batch
 * - GET /api/payroll/xero/oauth/callback
 *
 * @package PayrollModule\Controllers
 * @version 1.0.0
 */

use PayrollModule\Services\XeroService;
use PayrollModule\Lib\PayrollLogger;

class XeroController extends BaseController
{
    private XeroService $xeroService;

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
        $this->xeroService = new XeroService();
    }

    /**
     * Create a Xero pay run
     *
     * POST /api/payroll/xero/payrun/create
     *
     * Required POST fields:
     * - pay_period_id (int) - Pay period to create pay run for
     *
     * @return void Outputs JSON response
     */
    public function createPayRun(): void
    {
        $this->requireAuth();
        $this->requirePost();
        $this->verifyCsrf();

        try {
            // Validate input
            if (!isset($_POST['pay_period_id'])) {
                $this->jsonError('pay_period_id is required', null, 400);
                return;
            }

            $payPeriodId = (int)$_POST['pay_period_id'];

            // Get pay period details
            $sql = "SELECT * FROM payroll_pay_periods WHERE id = ?";
            $stmt = $this->xeroService->query($sql, [$payPeriodId]);
            $payPeriod = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$payPeriod) {
                $this->jsonError('Pay period not found', null, 404);
                return;
            }

            // Get all approved payroll data for this period
            $sql = "SELECT
                        ps.id as staff_id,
                        ps.first_name,
                        ps.last_name,
                        ps.xero_employee_id,
                        ps.hourly_rate,
                        COALESCE(SUM(pta.new_hours), 0) as total_hours,
                        COALESCE(SUM(pta.new_break_minutes), 0) as total_break_minutes
                    FROM payroll_staff ps
                    LEFT JOIN payroll_timesheet_amendments pta
                        ON ps.id = pta.staff_id
                        AND pta.pay_period_id = ?
                        AND pta.status = 'approved'
                    WHERE ps.is_active = 1
                    GROUP BY ps.id
                    HAVING total_hours > 0";

            $stmt = $this->xeroService->query($sql, [$payPeriodId]);
            $payrunData = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            if (empty($payrunData)) {
                $this->jsonError('No approved timesheets found for this pay period', null, 400);
                return;
            }

            // Create pay run in Xero
            $result = $this->xeroService->createPayRun($payPeriodId, $payrunData);

            if ($result['success']) {
                $this->logger->info('Xero pay run created', [
                    'pay_period_id' => $payPeriodId,
                    'xero_payrun_id' => $result['xero_payrun_id'],
                    'employee_count' => count($payrunData)
                ]);

                $this->jsonSuccess([
                    'xero_payrun_id' => $result['xero_payrun_id'],
                    'employee_count' => count($payrunData),
                    'message' => 'Pay run created successfully in Xero'
                ]);
            } else {
                $this->jsonError('Failed to create pay run', $result['error'] ?? 'Unknown error');
            }

        } catch (\Exception $e) {
            $this->logger->error('Failed to create Xero pay run', [
                'pay_period_id' => $_POST['pay_period_id'] ?? null,
                'error' => $e->getMessage()
            ]);
            $this->jsonError('Internal server error: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Get Xero pay run details
     *
     * GET /api/payroll/xero/payrun/:id
     *
     * @param string $xeroPayRunId Xero pay run ID
     * @return void Outputs JSON response
     */
    public function getPayRun(string $xeroPayRunId): void
    {
        $this->requireAuth();

        try {
            $payRun = $this->xeroService->getPayRun($xeroPayRunId);

            if ($payRun) {
                $this->jsonSuccess(['payrun' => $payRun]);
            } else {
                $this->jsonError('Pay run not found', null, 404);
            }

        } catch (\Exception $e) {
            $this->logger->error('Failed to fetch Xero pay run', [
                'xero_payrun_id' => $xeroPayRunId,
                'error' => $e->getMessage()
            ]);
            $this->jsonError('Internal server error', null, 500);
        }
    }

    /**
     * Create batch bank payments
     *
     * POST /api/payroll/xero/payments/batch
     *
     * Required POST fields:
     * - pay_period_id (int) - Pay period to create payments for
     *
     * @return void Outputs JSON response
     */
    public function createBatchPayments(): void
    {
        $this->requireAuth();
        $this->requirePost();
        $this->verifyCsrf();

        try {
            if (!isset($_POST['pay_period_id'])) {
                $this->jsonError('pay_period_id is required', null, 400);
                return;
            }

            $payPeriodId = (int)$_POST['pay_period_id'];

            // Get approved staff payments for this period
            $sql = "SELECT
                        ps.id as staff_id,
                        ps.first_name,
                        ps.last_name,
                        ps.bank_account_number,
                        ps.hourly_rate,
                        COALESCE(SUM(pta.new_hours), 0) as total_hours,
                        COALESCE(SUM(pta.new_hours * ps.hourly_rate), 0) as gross_pay
                    FROM payroll_staff ps
                    LEFT JOIN payroll_timesheet_amendments pta
                        ON ps.id = pta.staff_id
                        AND pta.pay_period_id = ?
                        AND pta.status = 'approved'
                    WHERE ps.is_active = 1
                    AND ps.bank_account_number IS NOT NULL
                    GROUP BY ps.id
                    HAVING total_hours > 0";

            $stmt = $this->xeroService->query($sql, [$payPeriodId]);
            $payments = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            if (empty($payments)) {
                $this->jsonError('No approved payments found for this pay period', null, 400);
                return;
            }

            // Create batch payment
            $result = $this->xeroService->createBankPaymentBatch($payments);

            if ($result['success']) {
                $this->logger->info('Xero batch payment created', [
                    'pay_period_id' => $payPeriodId,
                    'payment_count' => count($payments),
                    'total_amount' => array_sum(array_column($payments, 'gross_pay'))
                ]);

                $this->jsonSuccess([
                    'batch_payment_id' => $result['batch_payment_id'],
                    'payment_count' => count($payments),
                    'total_amount' => array_sum(array_column($payments, 'gross_pay')),
                    'message' => 'Batch payment created successfully'
                ]);
            } else {
                $this->jsonError('Failed to create batch payment', $result['error'] ?? 'Unknown error');
            }

        } catch (\Exception $e) {
            $this->logger->error('Failed to create Xero batch payment', [
                'pay_period_id' => $_POST['pay_period_id'] ?? null,
                'error' => $e->getMessage()
            ]);
            $this->jsonError('Internal server error', null, 500);
        }
    }

    /**
     * OAuth callback handler
     *
     * GET /api/payroll/xero/oauth/callback
     *
     * Xero redirects here after OAuth authorization
     * Exchanges authorization code for access token
     *
     * @return void Outputs HTML or redirects
     */
    public function oauthCallback(): void
    {
        try {
            if (!isset($_GET['code'])) {
                $this->jsonError('Authorization code not received', null, 400);
                return;
            }

            $authCode = $_GET['code'];

            // Exchange code for tokens
            $tokenUrl = 'https://identity.xero.com/connect/token';
            $clientId = getenv('XERO_CLIENT_ID');
            $clientSecret = getenv('XERO_CLIENT_SECRET');
            $redirectUri = getenv('XERO_REDIRECT_URI');

            $postData = [
                'grant_type' => 'authorization_code',
                'code' => $authCode,
                'redirect_uri' => $redirectUri
            ];

            $ch = curl_init($tokenUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Basic ' . base64_encode($clientId . ':' . $clientSecret),
                'Content-Type: application/x-www-form-urlencoded'
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode !== 200) {
                throw new \Exception('Failed to exchange authorization code: ' . $response);
            }

            $tokens = json_decode($response, true);

            // Save tokens to database
            $sql = "INSERT INTO payroll_api_tokens (
                        provider, access_token, refresh_token,
                        expires_at, token_data, created_at, updated_at
                    ) VALUES (
                        'xero', ?, ?,
                        DATE_ADD(NOW(), INTERVAL ? SECOND),
                        ?, NOW(), NOW()
                    )
                    ON DUPLICATE KEY UPDATE
                        access_token = VALUES(access_token),
                        refresh_token = VALUES(refresh_token),
                        expires_at = VALUES(expires_at),
                        token_data = VALUES(token_data),
                        updated_at = NOW()";

            $this->xeroService->execute($sql, [
                $tokens['access_token'],
                $tokens['refresh_token'],
                $tokens['expires_in'],
                json_encode($tokens)
            ]);

            $this->logger->info('Xero OAuth tokens saved', [
                'expires_in' => $tokens['expires_in']
            ]);

            // Redirect to success page
            header('Location: /payroll/xero/oauth/success');
            exit;

        } catch (\Exception $e) {
            $this->logger->error('Xero OAuth callback failed', [
                'error' => $e->getMessage()
            ]);
            $this->jsonError('OAuth authorization failed: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Initiate Xero OAuth flow
     *
     * GET /api/payroll/xero/oauth/authorize
     *
     * Redirects to Xero authorization page
     *
     * @return void Redirects to Xero
     */
    public function authorize(): void
    {
        $clientId = getenv('XERO_CLIENT_ID');
        $redirectUri = getenv('XERO_REDIRECT_URI');
        $scope = 'payroll.payitems payroll.payruns payroll.employees accounting.transactions';

        $authUrl = 'https://login.xero.com/identity/connect/authorize?' . http_build_query([
            'response_type' => 'code',
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'scope' => $scope,
            'state' => bin2hex(random_bytes(16))
        ]);

        header('Location: ' . $authUrl);
        exit;
    }
}
