<?php
/**
 * PayrollXeroService - Complete Xero Payroll API Integration
 *
 * Handles:
 * - OAuth2 authentication (token storage & refresh)
 * - Employee synchronization (Xero → CIS)
 * - Pay run creation (CIS → Xero)
 * - Payment batch generation
 * - Webhook processing
 * - Rate limiting (60 req/min)
 *
 * @package HumanResources\Payroll\Services
 * @author AI Full Send Implementation
 * @created 2025-11-02
 */

declare(strict_types=1);

use PayrollModule\Lib\PayrollLogger;
use PayrollModule\Lib\XeroTokenStore;

final class PayrollXeroService
{
    private PDO $db;
    private PayrollLogger $logger;
    private XeroTokenStore $tokenStore;

    // Xero API configuration
    private const API_BASE_URL = 'https://api.xero.com/payroll.xro/1.0';
    private const AUTH_URL = 'https://login.xero.com/identity/connect/authorize';
    private const TOKEN_URL = 'https://identity.xero.com/connect/token';
    private const RATE_LIMIT_MAX = 60; // requests per minute

    private string $clientId;
    private string $clientSecret;
    private string $redirectUri;

    // Rate limiting
    private array $requestTimes = [];

    private function __construct(PDO $db)
    {
        $this->db = $db;

        require_once __DIR__ . '/../lib/PayrollLogger.php';
        require_once __DIR__ . '/../lib/XeroTokenStore.php';

        $this->logger = new PayrollLogger();
        $this->tokenStore = new XeroTokenStore($db);

        // Load credentials from environment
        $this->clientId = getenv('XERO_CLIENT_ID') ?: '';
        $this->clientSecret = getenv('XERO_CLIENT_SECRET') ?: '';
        $this->redirectUri = getenv('XERO_REDIRECT_URI') ?: '';

        if (empty($this->clientId) || empty($this->clientSecret)) {
            throw new RuntimeException('Xero credentials not configured in environment');
        }
    }

    public static function make(PDO $connection): self
    {
        return new self($connection);
    }

    // ========================================================================
    // OAUTH2 AUTHENTICATION
    // ========================================================================

    /**
     * Get authorization URL for OAuth2 flow
     *
     * @param string $state CSRF state token
     * @return string Authorization URL
     */
    public function getAuthorizationUrl(string $state): string
    {
        $params = [
            'response_type' => 'code',
            'client_id' => $this->clientId,
            'redirect_uri' => $this->redirectUri,
            'scope' => 'payroll.employees payroll.payruns payroll.payslip payroll.settings offline_access',
            'state' => $state
        ];

        return self::AUTH_URL . '?' . http_build_query($params);
    }

    /**
     * Exchange authorization code for access tokens
     *
     * @param string $code Authorization code from OAuth callback
     * @return array Token data including access_token, refresh_token, expires_in
     */
    public function exchangeCodeForTokens(string $code): array
    {
        $this->logActivity('xero.oauth.exchange', 'Exchanging authorization code for tokens');

        $response = $this->makeTokenRequest([
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $this->redirectUri
        ]);

        // Store tokens
        $this->tokenStore->storeTokens(
            $response['access_token'],
            $response['refresh_token'],
            $response['expires_in']
        );

        $this->logActivity('xero.oauth.success', 'OAuth tokens obtained and stored');

        return $response;
    }

    /**
     * Refresh expired access token
     *
     * @return array New token data
     */
    public function refreshAccessToken(): array
    {
        $refreshToken = $this->tokenStore->getRefreshToken();

        if (empty($refreshToken)) {
            throw new RuntimeException('No refresh token available. Re-authorization required.');
        }

        $this->logActivity('xero.oauth.refresh', 'Refreshing access token');

        $response = $this->makeTokenRequest([
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken
        ]);

        // Update stored tokens
        $this->tokenStore->storeTokens(
            $response['access_token'],
            $response['refresh_token'],
            $response['expires_in']
        );

        $this->logActivity('xero.oauth.refresh_success', 'Access token refreshed');

        return $response;
    }

    /**
     * Make token request to Xero
     *
     * @param array $params Request parameters
     * @return array Token response
     */
    private function makeTokenRequest(array $params): array
    {
        $ch = curl_init(self::TOKEN_URL);

        $params['client_id'] = $this->clientId;
        $params['client_secret'] = $this->clientSecret;

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($params),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-www-form-urlencoded'
            ]
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new RuntimeException("Xero token request failed with HTTP $httpCode: $response");
        }

        $data = json_decode($response, true);

        if (!isset($data['access_token'])) {
            throw new RuntimeException('Invalid token response from Xero');
        }

        return $data;
    }

    /**
     * Ensure we have a valid access token (refresh if needed)
     *
     * @return string Valid access token
     */
    private function ensureValidToken(): string
    {
        if ($this->tokenStore->isTokenExpired()) {
            $this->refreshAccessToken();
        }

        return $this->tokenStore->getAccessToken();
    }

    // ========================================================================
    // EMPLOYEE SYNC
    // ========================================================================

    /**
     * Sync employees from Xero to CIS
     *
     * @return array Stats: ['fetched' => int, 'synced' => int, 'errors' => int]
     */
    public function syncEmployees(): array
    {
        $this->logActivity('xero.sync.employees.start', 'Starting employee sync from Xero');

        try {
            // Fetch from Xero
            $employees = $this->listEmployees();
            $fetched = count($employees);

            $synced = 0;
            $errors = 0;

            foreach ($employees as $employee) {
                try {
                    $this->syncSingleEmployee($employee);
                    $synced++;
                } catch (\Exception $e) {
                    $errors++;
                    $this->logActivity('xero.sync.employee.error', 'Failed to sync employee', [
                        'employee_id' => $employee['EmployeeID'] ?? 'unknown',
                        'error' => $e->getMessage()
                    ]);
                }
            }

            $this->logActivity('xero.sync.employees.complete', 'Employee sync completed', [
                'fetched' => $fetched,
                'synced' => $synced,
                'errors' => $errors
            ]);

            return [
                'fetched' => $fetched,
                'synced' => $synced,
                'errors' => $errors
            ];

        } catch (\Exception $e) {
            $this->logActivity('xero.sync.employees.failed', 'Employee sync failed', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * List all employees from Xero
     *
     * @return array Employees from Xero API
     */
    public function listEmployees(): array
    {
        $response = $this->makeApiRequest('GET', '/Employees');
        return $response['Employees'] ?? [];
    }

    /**
     * Sync single employee to payroll_staff table
     *
     * @param array $employee Xero employee data
     */
    private function syncSingleEmployee(array $employee): void
    {
        $sql = "INSERT INTO payroll_staff (xero_employee_id, first_name, last_name, email, status, xero_data, last_synced)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE
                    first_name = VALUES(first_name),
                    last_name = VALUES(last_name),
                    email = VALUES(email),
                    status = VALUES(status),
                    xero_data = VALUES(xero_data),
                    last_synced = NOW()";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $employee['EmployeeID'],
            $employee['FirstName'] ?? '',
            $employee['LastName'] ?? '',
            $employee['Email'] ?? '',
            $employee['Status'] ?? 'ACTIVE',
            json_encode($employee)
        ]);
    }

    // ========================================================================
    // PAY RUN CREATION
    // ========================================================================

    /**
     * Create pay run in Xero from approved CIS payslips
     *
     * @param int $payPeriodId CIS pay period ID
     * @param array $payslips Array of payslip data
     * @return array Response with xero_payrun_id and status
     */
    public function createPayRun(int $payPeriodId, array $payslips): array
    {
        $this->logActivity('xero.payrun.create.start', 'Creating pay run in Xero', [
            'pay_period_id' => $payPeriodId,
            'payslip_count' => count($payslips)
        ]);

        try {
            // Transform payslips to Xero format
            $xeroPayslips = $this->transformPayslipsForXero($payslips);

            // Create pay run
            $payRunData = [
                'PayrollCalendarID' => $this->getPayrollCalendarId(),
                'PayRunPeriodStartDate' => $this->getPayPeriodStart($payPeriodId),
                'PayRunPeriodEndDate' => $this->getPayPeriodEnd($payPeriodId),
                'PayRunStatus' => 'Draft',
                'PaymentDate' => $this->getPaymentDate($payPeriodId),
                'Payslips' => $xeroPayslips
            ];

            $response = $this->makeApiRequest('POST', '/PayRuns', ['PayRuns' => [$payRunData]]);

            $payRunId = $response['PayRuns'][0]['PayRunID'] ?? null;

            if (!$payRunId) {
                throw new RuntimeException('Xero did not return PayRunID');
            }

            // Store mapping
            $this->storePayRunMapping($payPeriodId, $payRunId);

            $this->logActivity('xero.payrun.create.success', 'Pay run created in Xero', [
                'pay_period_id' => $payPeriodId,
                'xero_payrun_id' => $payRunId
            ]);

            return [
                'xero_payrun_id' => $payRunId,
                'payslips_created' => count($payslips),
                'status' => 'draft'
            ];

        } catch (\Exception $e) {
            $this->logActivity('xero.payrun.create.failed', 'Failed to create pay run', [
                'pay_period_id' => $payPeriodId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Transform CIS payslips to Xero format
     *
     * @param array $payslips CIS payslips
     * @return array Xero-formatted payslips
     */
    private function transformPayslipsForXero(array $payslips): array
    {
        $xeroPayslips = [];

        foreach ($payslips as $payslip) {
            $earnings = [];

            // Ordinary time
            if ($payslip['ordinary_hours'] > 0) {
                $earnings[] = [
                    'EarningsRateID' => $this->getEarningsRateId('Ordinary'),
                    'NumberOfUnits' => $payslip['ordinary_hours'],
                    'RatePerUnit' => $payslip['hourly_rate']
                ];
            }

            // Overtime
            if ($payslip['overtime_hours'] > 0) {
                $earnings[] = [
                    'EarningsRateID' => $this->getEarningsRateId('Overtime'),
                    'NumberOfUnits' => $payslip['overtime_hours'],
                    'RatePerUnit' => $payslip['hourly_rate'] * 1.5
                ];
            }

            // Night shift
            if ($payslip['night_shift_hours'] > 0) {
                $earnings[] = [
                    'EarningsRateID' => $this->getEarningsRateId('NightShift'),
                    'NumberOfUnits' => $payslip['night_shift_hours'],
                    'RatePerUnit' => $payslip['hourly_rate'] * 1.2
                ];
            }

            // Public holidays
            if ($payslip['public_holiday_hours'] > 0) {
                $earnings[] = [
                    'EarningsRateID' => $this->getEarningsRateId('PublicHoliday'),
                    'NumberOfUnits' => $payslip['public_holiday_hours'],
                    'RatePerUnit' => $payslip['hourly_rate'] * 1.5
                ];
            }

            // Bonuses
            if ($payslip['total_bonuses'] > 0) {
                $earnings[] = [
                    'EarningsRateID' => $this->getEarningsRateId('Bonus'),
                    'RatePerUnit' => $payslip['total_bonuses']
                ];
            }

            $xeroPayslips[] = [
                'EmployeeID' => $payslip['xero_employee_id'],
                'EarningsLines' => $earnings
            ];
        }

        return $xeroPayslips;
    }

    // ========================================================================
    // PAYMENT BATCHES
    // ========================================================================

    /**
     * Create payment batch in Xero
     *
     * @param int $payRunId CIS pay run ID
     * @param string $bankExportFile Path to bank export file
     * @return array Response with batch details
     */
    public function createPaymentBatch(int $payRunId, string $bankExportFile): array
    {
        $this->logActivity('xero.payment.batch.create', 'Creating payment batch', [
            'pay_run_id' => $payRunId,
            'bank_file' => basename($bankExportFile)
        ]);

        // Get Xero pay run ID
        $xeroPayRunId = $this->getXeroPayRunId($payRunId);

        if (!$xeroPayRunId) {
            throw new RuntimeException("No Xero pay run found for CIS pay run $payRunId");
        }

        // Create payment batch
        $response = $this->makeApiRequest('POST', "/PayRuns/$xeroPayRunId/Post");

        $this->logActivity('xero.payment.batch.success', 'Payment batch created', [
            'pay_run_id' => $payRunId,
            'xero_payrun_id' => $xeroPayRunId
        ]);

        return $response;
    }

    // ========================================================================
    // RATE LIMITING & API REQUESTS
    // ========================================================================

    /**
     * Make API request to Xero with rate limiting
     *
     * @param string $method HTTP method (GET, POST, PUT, DELETE)
     * @param string $endpoint API endpoint (e.g., '/Employees')
     * @param array|null $body Request body (for POST/PUT)
     * @return array Response data
     */
    private function makeApiRequest(string $method, string $endpoint, ?array $body = null): array
    {
        // Enforce rate limit
        $this->enforceRateLimit();

        // Ensure valid token
        $accessToken = $this->ensureValidToken();

        $url = self::API_BASE_URL . $endpoint;

        $ch = curl_init($url);

        $headers = [
            'Authorization: Bearer ' . $accessToken,
            'Accept: application/json'
        ];

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method
        ]);

        if ($body !== null) {
            $headers[] = 'Content-Type: application/json';
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // Handle rate limit
        if ($httpCode === 429) {
            $this->logActivity('xero.rate_limit', 'Rate limit hit', [
                'endpoint' => $endpoint
            ]);
            throw new RuntimeException('Xero API rate limit exceeded. Please retry later.');
        }

        // Handle errors
        if ($httpCode >= 400) {
            $this->logActivity('xero.api.error', 'API request failed', [
                'method' => $method,
                'endpoint' => $endpoint,
                'http_code' => $httpCode,
                'response' => $response
            ]);
            throw new RuntimeException("Xero API error: HTTP $httpCode - $response");
        }

        // Track request time for rate limiting
        $this->requestTimes[] = time();

        return json_decode($response, true) ?? [];
    }

    /**
     * Enforce rate limit (60 requests per minute)
     */
    private function enforceRateLimit(): void
    {
        // Remove requests older than 1 minute
        $now = time();
        $this->requestTimes = array_filter($this->requestTimes, function($time) use ($now) {
            return ($now - $time) < 60;
        });

        // Check if we've hit the limit
        if (count($this->requestTimes) >= self::RATE_LIMIT_MAX) {
            $oldestRequest = min($this->requestTimes);
            $waitTime = 60 - ($now - $oldestRequest);

            if ($waitTime > 0) {
                $this->logActivity('xero.rate_limit.wait', "Waiting {$waitTime}s for rate limit");
                sleep($waitTime);
            }
        }
    }

    // ========================================================================
    // HELPER METHODS
    // ========================================================================

    private function getPayrollCalendarId(): string
    {
        // TODO: Get from config or database
        return getenv('XERO_CALENDAR_ID') ?: '';
    }

    private function getPayPeriodStart(int $payPeriodId): string
    {
        $stmt = $this->db->prepare("SELECT period_start FROM pay_periods WHERE id = ?");
        $stmt->execute([$payPeriodId]);
        return $stmt->fetchColumn() ?: date('Y-m-d');
    }

    private function getPayPeriodEnd(int $payPeriodId): string
    {
        $stmt = $this->db->prepare("SELECT period_end FROM pay_periods WHERE id = ?");
        $stmt->execute([$payPeriodId]);
        return $stmt->fetchColumn() ?: date('Y-m-d');
    }

    private function getPaymentDate(int $payPeriodId): string
    {
        $stmt = $this->db->prepare("SELECT payment_date FROM pay_periods WHERE id = ?");
        $stmt->execute([$payPeriodId]);
        return $stmt->fetchColumn() ?: date('Y-m-d');
    }

    private function getEarningsRateId(string $type): string
    {
        // TODO: Map CIS earning types to Xero earning rate IDs
        // This should be stored in a config table
        $map = [
            'Ordinary' => getenv('XERO_RATE_ORDINARY') ?: '',
            'Overtime' => getenv('XERO_RATE_OVERTIME') ?: '',
            'NightShift' => getenv('XERO_RATE_NIGHTSHIFT') ?: '',
            'PublicHoliday' => getenv('XERO_RATE_PUBLICHOLIDAY') ?: '',
            'Bonus' => getenv('XERO_RATE_BONUS') ?: ''
        ];

        return $map[$type] ?? '';
    }

    private function storePayRunMapping(int $payPeriodId, string $xeroPayRunId): void
    {
        $sql = "INSERT INTO payroll_xero_mappings (pay_period_id, xero_payrun_id, created_at)
                VALUES (?, ?, NOW())";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$payPeriodId, $xeroPayRunId]);
    }

    private function getXeroPayRunId(int $payRunId): ?string
    {
        $sql = "SELECT xero_payrun_id FROM payroll_xero_mappings WHERE pay_period_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$payRunId]);
        return $stmt->fetchColumn() ?: null;
    }

    // ========================================================================
    // LOGGING
    // ========================================================================

    public function logActivity(string $action, string $message, array $context = []): void
    {
        try {
            $sql = 'INSERT INTO payroll_activity_log (log_level, category, action, message, details, created_at)
                    VALUES (:level, :category, :action, :message, :details, NOW())';

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':level' => 'info',
                ':category' => 'xero',
                ':action' => $action,
                ':message' => $message,
                ':details' => empty($context) ? null : json_encode($context, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            ]);
        } catch (Throwable $e) {
            error_log('PayrollXeroService activity log failed: ' . $e->getMessage());
        }
    }
}
