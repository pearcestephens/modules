<?php
declare(strict_types=1);

namespace PayrollModule\Services;

/**
 * Xero Service
 *
 * Handles all Xero API operations for payroll:
 * - Pay run creation and submission
 * - Employee pay items
 * - Bank payments and reconciliation
 * - OAuth token management
 *
 * @package PayrollModule\Services
 * @version 1.0.0
 */

use PayrollModule\Lib\PayrollLogger;

class XeroService extends BaseService
{
    private string $clientId;
    private string $clientSecret;
    private string $redirectUri;
    private ?string $accessToken = null;
    private ?string $refreshToken = null;
    private ?int $tokenExpiry = null;
    // HTTP timeout and retry controls
    private int $httpTimeoutSeconds = 20;       // total request timeout
    private int $httpConnectTimeoutSeconds = 5; // TCP connect timeout
    private int $httpMaxRetries = 2;            // number of retries on timeout
    private int $httpRetryBackoffMs = 250;      // backoff between retries

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();

        // Load Xero credentials from environment
        $this->clientId = $_ENV['XERO_CLIENT_ID'] ?? '';
        $this->clientSecret = $_ENV['XERO_CLIENT_SECRET'] ?? '';
        $this->redirectUri = $_ENV['XERO_REDIRECT_URI'] ?? '';

        // Load stored tokens
        $this->loadTokens();
    }

    /**
     * Create Xero pay run from payroll data
     *
     * @param int $payPeriodId Pay period ID
     * @param array $payrunData Payrun data with employee pay items
     * @return array Result with xero_payrun_id
     */
    public function createPayRun(int $payPeriodId, array $payrunData): array
    {
        $startTime = $this->logger->startTimer('xero_create_payrun');

        try {
            // Ensure we have valid token
            if (!$this->ensureValidToken()) {
                throw new \RuntimeException('Xero authentication failed');
            }

            // Get pay period details
            $payPeriod = $this->queryOne(
                "SELECT * FROM payroll_pay_periods WHERE id = :id",
                [':id' => $payPeriodId]
            );

            if (!$payPeriod) {
                throw new \RuntimeException('Pay period not found');
            }

            // Build Xero pay run payload
            $payload = $this->buildPayRunPayload($payPeriod, $payrunData);

            // Send to Xero API
            $response = $this->xeroApiRequest('POST', '/payroll.xro/1.0/PayRuns', $payload);

            if (isset($response['PayRuns'][0]['PayRunID'])) {
                $xeroPayRunId = $response['PayRuns'][0]['PayRunID'];

                // Log the creation
                $this->logger->info('Xero pay run created', [
                    'pay_period_id' => $payPeriodId,
                    'xero_payrun_id' => $xeroPayRunId
                ]);

                $this->logger->endTimer($startTime, 'xero_create_payrun');

                return [
                    'success' => true,
                    'xero_payrun_id' => $xeroPayRunId,
                    'response' => $response
                ];
            } else {
                throw new \RuntimeException('Xero API returned invalid response');
            }

        } catch (\Exception $e) {
            $this->logger->error('Failed to create Xero pay run', [
                'pay_period_id' => $payPeriodId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get Xero pay run details
     *
     * @param string $xeroPayRunId Xero pay run ID
     * @return array|null Pay run details
     */
    public function getPayRun(string $xeroPayRunId): ?array
    {
        try {
            if (!$this->ensureValidToken()) {
                throw new \RuntimeException('Xero authentication failed');
            }

            $response = $this->xeroApiRequest('GET', "/payroll.xro/1.0/PayRuns/{$xeroPayRunId}");

            return $response['PayRuns'][0] ?? null;

        } catch (\Exception $e) {
            $this->logger->error('Failed to get Xero pay run', [
                'xero_payrun_id' => $xeroPayRunId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Create Xero bank payment batch
     *
     * @param array $payments Array of payment data
     * @return array Result with batch_id
     */
    public function createBankPaymentBatch(array $payments): array
    {
        $startTime = $this->logger->startTimer('xero_create_payment_batch');

        try {
            $this->beginTransaction();

            // Create batch record
            $sql = "INSERT INTO payroll_bank_payment_batches (
                        total_amount, payment_count, status, created_at
                    ) VALUES (
                        :total, :count, 'pending', NOW()
                    )";

            $totalAmount = array_sum(array_column($payments, 'amount'));
            $batchId = $this->execute($sql, [
                ':total' => $totalAmount,
                ':count' => count($payments)
            ]);

            // Create individual payment records
            foreach ($payments as $payment) {
                $sql = "INSERT INTO payroll_bank_payments (
                            batch_id, staff_id, amount, bank_account,
                            reference, status, created_at
                        ) VALUES (
                            :batch_id, :staff_id, :amount, :bank_account,
                            :reference, 'pending', NOW()
                        )";

                $this->execute($sql, [
                    ':batch_id' => $batchId,
                    ':staff_id' => $payment['staff_id'],
                    ':amount' => $payment['amount'],
                    ':bank_account' => $payment['bank_account'],
                    ':reference' => $payment['reference'] ?? 'Payroll'
                ]);
            }

            // Send to Xero (if configured)
            if ($this->ensureValidToken()) {
                $xeroResult = $this->sendPaymentsToXero($batchId, $payments);

                if ($xeroResult['success']) {
                    $this->execute(
                        "UPDATE payroll_bank_payment_batches SET xero_batch_id = :xero_id WHERE id = :id",
                        [':xero_id' => $xeroResult['xero_batch_id'], ':id' => $batchId]
                    );
                }
            }

            $this->commit();

            $this->logger->endTimer($startTime, 'xero_create_payment_batch');
            $this->logger->info('Bank payment batch created', [
                'batch_id' => $batchId,
                'payment_count' => count($payments),
                'total_amount' => $totalAmount
            ]);

            return [
                'success' => true,
                'batch_id' => $batchId,
                'payment_count' => count($payments),
                'total_amount' => $totalAmount
            ];

        } catch (\Exception $e) {
            $this->rollback();
            $this->logger->error('Failed to create payment batch', [
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Build Xero pay run payload
     *
     * @param array $payPeriod Pay period data
     * @param array $payrunData Payrun data
     * @return array Xero API payload
     */
    private function buildPayRunPayload(array $payPeriod, array $payrunData): array
    {
        $payslipLines = [];

        foreach ($payrunData['employees'] as $employee) {
            $earningsLines = [];

            // Regular hours
            if ($employee['regular_hours'] > 0) {
                $earningsLines[] = [
                    'EarningsRateID' => $this->getXeroEarningsRateId('ORD'),
                    'NumberOfUnits' => $employee['regular_hours']
                ];
            }

            // Overtime hours
            if (!empty($employee['overtime_hours']) && $employee['overtime_hours'] > 0) {
                $earningsLines[] = [
                    'EarningsRateID' => $this->getXeroEarningsRateId('OT'),
                    'NumberOfUnits' => $employee['overtime_hours']
                ];
            }

            $payslipLines[] = [
                'EmployeeID' => $employee['xero_employee_id'],
                'EarningsLines' => $earningsLines
            ];
        }

        return [
            'PayRun' => [
                'PayrollCalendarID' => $this->getXeroCalendarId(),
                'PayRunPeriodStartDate' => $payPeriod['start_date'],
                'PayRunPeriodEndDate' => $payPeriod['end_date'],
                'PayRunStatus' => 'Draft',
                'Payslips' => $payslipLines
            ]
        ];
    }

    /**
     * Send payments to Xero
     *
     * @param int $batchId Batch ID
     * @param array $payments Payments
     * @return array Result
     */
    private function sendPaymentsToXero(int $batchId, array $payments): array
    {
        try {
            // Build Xero batch payment payload
            $payload = [
                'BatchPayments' => [
                    [
                        'Account' => $this->getXeroBankAccountCode(),
                        'Reference' => "Payroll Batch {$batchId}",
                        'Details' => 'Staff Payroll',
                        'Payments' => array_map(function($payment) {
                            return [
                                'Amount' => $payment['amount'],
                                'Reference' => $payment['reference'] ?? 'Payroll',
                                'AccountNumber' => $payment['bank_account']
                            ];
                        }, $payments)
                    ]
                ]
            ];

            $response = $this->xeroApiRequest('POST', '/api.xro/2.0/BatchPayments', $payload);

            if (isset($response['BatchPayments'][0]['BatchPaymentID'])) {
                return [
                    'success' => true,
                    'xero_batch_id' => $response['BatchPayments'][0]['BatchPaymentID']
                ];
            }

            return [
                'success' => false,
                'error' => 'Invalid Xero response'
            ];

        } catch (\Exception $e) {
            $this->logger->error('Failed to send payments to Xero', [
                'batch_id' => $batchId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Make Xero API request
     *
     * @param string $method HTTP method
     * @param string $endpoint API endpoint
     * @param array|null $data Request data
     * @return array Response data
     */
    private function xeroApiRequest(string $method, string $endpoint, ?array $data = null): array
    {
        $url = 'https://api.xero.com' . $endpoint;

        $headers = [
            'Authorization: Bearer ' . $this->accessToken,
            'Content-Type: application/json',
            'Accept: application/json'
        ];

        $attempt = 0;
        $lastError = null;

        do {
            $attempt++;

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->httpConnectTimeoutSeconds);
            curl_setopt($ch, CURLOPT_TIMEOUT, $this->httpTimeoutSeconds);

            if ($method === 'POST' || $method === 'PUT') {
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
                if ($data) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                }
            }

            $response = curl_exec($ch);
            $curlErrNo = curl_errno($ch);
            $curlErr = curl_error($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            // Retry on timeout errors only
            if ($curlErrNo === CURLE_OPERATION_TIMEDOUT) {
                $lastError = $curlErr ?: 'cURL operation timed out';
                $this->logger->warning('Xero API request timed out, retrying', [
                    'endpoint' => $endpoint,
                    'attempt' => $attempt,
                    'max_retries' => $this->httpMaxRetries,
                ]);
                if ($attempt <= $this->httpMaxRetries) {
                    usleep($this->httpRetryBackoffMs * 1000);
                    continue;
                }
                throw new \RuntimeException('Xero API request timed out: ' . $lastError);
            }

            if ($httpCode >= 400) {
                throw new \RuntimeException("Xero API error: HTTP {$httpCode} - {$response}");
            }

            return json_decode($response, true) ?? [];

        } while ($attempt <= $this->httpMaxRetries);

        // Should not reach here; fallback error
        throw new \RuntimeException('Xero API request failed unexpectedly' . ($lastError ? (': ' . $lastError) : ''));
    }

    /**
     * Ensure we have a valid access token
     *
     * @return bool True if token is valid
     */
    private function ensureValidToken(): bool
    {
        // Check if token exists and not expired
        if ($this->accessToken && $this->tokenExpiry && time() < $this->tokenExpiry) {
            return true;
        }

        // Try to refresh token
        if ($this->refreshToken) {
            return $this->refreshAccessToken();
        }

        $this->logger->warning('Xero token expired and no refresh token available');
        return false;
    }

    /**
     * Refresh Xero access token
     *
     * @return bool True if successful
     */
    private function refreshAccessToken(): bool
    {
        try {
            $attempt = 0;
            do {
                $attempt++;
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, 'https://identity.xero.com/connect/token');
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
                    'grant_type' => 'refresh_token',
                    'refresh_token' => $this->refreshToken
                ]));
                curl_setopt($ch, CURLOPT_USERPWD, $this->clientId . ':' . $this->clientSecret);
                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->httpConnectTimeoutSeconds);
                curl_setopt($ch, CURLOPT_TIMEOUT, $this->httpTimeoutSeconds);

                $response = curl_exec($ch);
                $curlErrNo = curl_errno($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if ($curlErrNo === CURLE_OPERATION_TIMEDOUT && $attempt <= $this->httpMaxRetries) {
                    usleep($this->httpRetryBackoffMs * 1000);
                    continue;
                }

            if ($httpCode === 200) {
                $data = json_decode($response, true);
                $this->accessToken = $data['access_token'];
                $this->refreshToken = $data['refresh_token'];
                $this->tokenExpiry = time() + $data['expires_in'];

                // Save tokens
                $this->saveTokens();

                $this->logger->info('Xero token refreshed successfully');
                return true;
            }

                throw new \RuntimeException("Token refresh failed: HTTP {$httpCode}");
            } while ($attempt <= $this->httpMaxRetries);

        } catch (\Exception $e) {
            $this->logger->error('Failed to refresh Xero token', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Load stored tokens from database
     */
    private function loadTokens(): void
    {
        $tokens = $this->queryOne(
            "SELECT * FROM payroll_api_tokens WHERE provider = 'xero' AND is_active = 1 LIMIT 1"
        );

        if ($tokens) {
            $this->accessToken = $tokens['access_token'];
            $this->refreshToken = $tokens['refresh_token'];
            $this->tokenExpiry = strtotime($tokens['expires_at']);
        }
    }

    /**
     * Save tokens to database
     */
    private function saveTokens(): void
    {
        $sql = "INSERT INTO payroll_api_tokens (
                    provider, access_token, refresh_token, expires_at, is_active
                ) VALUES (
                    'xero', :access, :refresh, :expires, 1
                ) ON DUPLICATE KEY UPDATE
                    access_token = :access2,
                    refresh_token = :refresh2,
                    expires_at = :expires2,
                    updated_at = NOW()";

        $expiresAt = date('Y-m-d H:i:s', $this->tokenExpiry);

        $this->execute($sql, [
            ':access' => $this->accessToken,
            ':refresh' => $this->refreshToken,
            ':expires' => $expiresAt,
            ':access2' => $this->accessToken,
            ':refresh2' => $this->refreshToken,
            ':expires2' => $expiresAt
        ]);
    }

    /**
     * Get Xero earnings rate ID
     */
    private function getXeroEarningsRateId(string $type): string
    {
        // These would come from config/database
        $rates = [
            'ORD' => '12345-67890',  // Regular earnings
            'OT' => '12345-67891'     // Overtime earnings
        ];

        return $rates[$type] ?? $rates['ORD'];
    }

    /**
     * Get Xero calendar ID
     */
    private function getXeroCalendarId(): string
    {
        // This would come from config
        return $_ENV['XERO_CALENDAR_ID'] ?? 'default-calendar-id';
    }

    /**
     * Get Xero bank account code
     */
    private function getXeroBankAccountCode(): string
    {
        // This would come from config
        return $_ENV['XERO_BANK_ACCOUNT'] ?? '1-1010';
    }
}
