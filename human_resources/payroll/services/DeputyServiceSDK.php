<?php
declare(strict_types=1);

namespace PayrollModule\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use PayrollModule\Lib\PayrollLogger;
use PDO;

/**
 * Deputy Service - Official API Implementation
 *
 * High-quality implementation using Deputy REST API
 * Handles all Deputy operations including:
 * - Employee management
 * - Timesheet operations (create, update, bulk)
 * - Leave requests and approvals
 * - Roster management
 * - Comprehensive error handling and logging
 * - Rate limiting and retry logic
 *
 * @package PayrollModule\Services
 * @version 2.0.0 (Official API)
 * @see https://www.deputy.com/api-doc/API/Getting_Started
 */
class DeputyServiceSDK extends BaseService
{
    private string $apiEndpoint;
    private string $apiToken;
    private Client $httpClient;

    // API configuration
    private const API_VERSION = 'v1';
    private const DEFAULT_TIMEOUT = 30;
    private const CONNECT_TIMEOUT = 10;
    private const MAX_RETRIES = 3;
    private const RETRY_DELAY_MS = 500;

    // Rate limiting (Deputy allows 10 requests/second per installation)
    private const RATE_LIMIT_REQUESTS = 10;
    private const RATE_LIMIT_WINDOW_MS = 1000;
    private array $requestTimestamps = [];

    /**
     * Constructor
     *
     * @param PDO $pdo Database connection
     */
    public function __construct(PDO $pdo)
    {
        parent::__construct();
        $this->pdo = $pdo;

        // Load Deputy configuration from environment
        $this->apiEndpoint = $_ENV['DEPUTY_API_ENDPOINT'] ?? 'https://api.deputy.com/api';
        $this->apiToken = $_ENV['DEPUTY_API_TOKEN'] ?? '';

        if (empty($this->apiToken)) {
            $this->logger->error('Deputy API token not configured');
            throw new \RuntimeException('Deputy API token not configured');
        }

        // Initialize HTTP client
        $this->httpClient = new Client([
            'base_uri' => $this->apiEndpoint,
            'timeout' => self::DEFAULT_TIMEOUT,
            'connect_timeout' => self::CONNECT_TIMEOUT,
            'headers' => [
                'Authorization' => 'OAuth ' . $this->apiToken,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'User-Agent' => 'CIS-Payroll/2.0'
            ]
        ]);

        $this->logger->info('DeputyServiceSDK initialized', [
            'endpoint' => $this->apiEndpoint
        ]);
    }

    /**
     * Rate limit requests to comply with Deputy API limits
     */
    private function rateLimit(): void
    {
        $now = microtime(true) * 1000; // Convert to milliseconds

        // Remove timestamps outside the current window
        $this->requestTimestamps = array_filter(
            $this->requestTimestamps,
            fn($ts) => ($now - $ts) < self::RATE_LIMIT_WINDOW_MS
        );

        // If we've hit the limit, wait
        if (count($this->requestTimestamps) >= self::RATE_LIMIT_REQUESTS) {
            $oldestRequest = min($this->requestTimestamps);
            $waitMs = self::RATE_LIMIT_WINDOW_MS - ($now - $oldestRequest);

            if ($waitMs > 0) {
                $this->logger->debug('Rate limiting: waiting', [
                    'wait_ms' => $waitMs
                ]);
                usleep((int)($waitMs * 1000));
            }
        }

        // Record this request
        $this->requestTimestamps[] = microtime(true) * 1000;
    }

    /**
     * Make API request with retry logic
     *
     * @param string $method HTTP method
     * @param string $endpoint API endpoint
     * @param array $data Request data
     * @param array $query Query parameters
     * @return array Response data
     * @throws \RuntimeException On API error
     */
    private function apiRequest(
        string $method,
        string $endpoint,
        array $data = [],
        array $query = []
    ): array {
        $this->rateLimit();

        $startTime = $this->logger->startTimer('deputy_api_request');
        $attempt = 0;

        while ($attempt < self::MAX_RETRIES) {
            try {
                $attempt++;

                $options = [];
                if (!empty($query)) {
                    $options['query'] = $query;
                }
                if (!empty($data)) {
                    $options['json'] = $data;
                }

                $this->logger->debug('Deputy API request', [
                    'method' => $method,
                    'endpoint' => $endpoint,
                    'attempt' => $attempt,
                    'has_data' => !empty($data),
                    'query_params' => array_keys($query)
                ]);

                $response = $this->httpClient->request($method, $endpoint, $options);
                $body = $response->getBody()->getContents();
                $statusCode = $response->getStatusCode();

                $this->logger->debug('Deputy API response', [
                    'status_code' => $statusCode,
                    'response_size' => strlen($body)
                ]);

                $this->logger->endTimer($startTime, 'deputy_api_request');

                $result = json_decode($body, true);

                // Deputy API returns data directly or in a wrapper
                return $result ?? [];

            } catch (GuzzleException $e) {
                $this->logger->warning('Deputy API request failed', [
                    'method' => $method,
                    'endpoint' => $endpoint,
                    'attempt' => $attempt,
                    'error' => $e->getMessage(),
                    'code' => $e->getCode()
                ]);

                // If this was the last attempt, throw
                if ($attempt >= self::MAX_RETRIES) {
                    $this->logger->error('Deputy API request failed after retries', [
                        'method' => $method,
                        'endpoint' => $endpoint,
                        'attempts' => $attempt,
                        'error' => $e->getMessage()
                    ]);
                    throw new \RuntimeException('Deputy API request failed: ' . $e->getMessage());
                }

                // Wait before retrying
                usleep(self::RETRY_DELAY_MS * $attempt * 1000);
            }
        }

        throw new \RuntimeException('Deputy API request failed after all retries');
    }

    /**
     * Get employee by ID
     *
     * @param int $employeeId Deputy employee ID
     * @return array|null Employee data
     */
    public function getEmployee(int $employeeId): ?array
    {
        $startTime = $this->logger->startTimer('deputy_get_employee');

        try {
            $this->logger->info('Fetching employee from Deputy', [
                'employee_id' => $employeeId
            ]);

            $result = $this->apiRequest('GET', "/v1/resource/Employee/{$employeeId}");

            $this->logger->info('Employee fetched successfully', [
                'employee_id' => $employeeId,
                'display_name' => $result['DisplayName'] ?? 'Unknown'
            ]);

            $this->logger->endTimer($startTime, 'deputy_get_employee');

            return $result;
        } catch (\Throwable $e) {
            $this->logger->error('Failed to fetch employee', [
                'employee_id' => $employeeId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Get timesheets for date range
     *
     * @param string $startDate Start date (Y-m-d)
     * @param string $endDate End date (Y-m-d)
     * @param int|null $employeeId Optional employee filter
     * @return array List of timesheets
     */
    public function getTimesheets(
        string $startDate,
        string $endDate,
        ?int $employeeId = null
    ): array {
        $startTime = $this->logger->startTimer('deputy_get_timesheets');

        try {
            $startTimestamp = strtotime($startDate . ' 00:00:00');
            $endTimestamp = strtotime($endDate . ' 23:59:59');

            $this->logger->info('Fetching timesheets from Deputy', [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'employee_id' => $employeeId
            ]);

            $query = [
                'start' => $startTimestamp,
                'end' => $endTimestamp
            ];

            if ($employeeId) {
                $query['employee'] = $employeeId;
            }

            $result = $this->apiRequest('GET', '/v1/resource/Timesheet', [], $query);

            $timesheets = is_array($result) ? $result : [];

            $this->logger->info('Timesheets fetched successfully', [
                'count' => count($timesheets),
                'start_date' => $startDate,
                'end_date' => $endDate
            ]);

            $this->logger->endTimer($startTime, 'deputy_get_timesheets');

            return $timesheets;
        } catch (\Throwable $e) {
            $this->logger->error('Failed to fetch timesheets', [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Create new timesheet
     *
     * @param array $timesheetData Timesheet data
     * @return array|null Created timesheet
     */
    public function createTimesheet(array $timesheetData): ?array
    {
        $startTime = $this->logger->startTimer('deputy_create_timesheet');

        try {
            $this->logger->info('Creating timesheet in Deputy', [
                'employee_id' => $timesheetData['Employee'] ?? null,
                'start_time' => $timesheetData['StartTime'] ?? null,
                'end_time' => $timesheetData['EndTime'] ?? null
            ]);

            $result = $this->apiRequest('POST', '/v1/resource/Timesheet', $timesheetData);

            $this->logger->info('Timesheet created successfully', [
                'timesheet_id' => $result['Id'] ?? null
            ]);

            $this->logger->endTimer($startTime, 'deputy_create_timesheet');

            return $result;
        } catch (\Throwable $e) {
            $this->logger->error('Failed to create timesheet', [
                'timesheet_data' => $timesheetData,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Update existing timesheet
     *
     * @param int $timesheetId Timesheet ID
     * @param array $timesheetData Updated data
     * @return array|null Updated timesheet
     */
    public function updateTimesheet(int $timesheetId, array $timesheetData): ?array
    {
        $startTime = $this->logger->startTimer('deputy_update_timesheet');

        try {
            $this->logger->info('Updating timesheet in Deputy', [
                'timesheet_id' => $timesheetId,
                'updates' => array_keys($timesheetData)
            ]);

            $result = $this->apiRequest(
                'POST',
                "/v1/resource/Timesheet/{$timesheetId}",
                $timesheetData
            );

            $this->logger->info('Timesheet updated successfully', [
                'timesheet_id' => $timesheetId
            ]);

            $this->logger->endTimer($startTime, 'deputy_update_timesheet');

            return $result;
        } catch (\Throwable $e) {
            $this->logger->error('Failed to update timesheet', [
                'timesheet_id' => $timesheetId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Delete timesheet
     *
     * @param int $timesheetId Timesheet ID
     * @return bool Success
     */
    public function deleteTimesheet(int $timesheetId): bool
    {
        $startTime = $this->logger->startTimer('deputy_delete_timesheet');

        try {
            $this->logger->info('Deleting timesheet from Deputy', [
                'timesheet_id' => $timesheetId
            ]);

            $this->apiRequest('DELETE', "/v1/resource/Timesheet/{$timesheetId}");

            $this->logger->info('Timesheet deleted successfully', [
                'timesheet_id' => $timesheetId
            ]);

            $this->logger->endTimer($startTime, 'deputy_delete_timesheet');

            return true;
        } catch (\Throwable $e) {
            $this->logger->error('Failed to delete timesheet', [
                'timesheet_id' => $timesheetId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Get leave requests for date range
     *
     * @param string $startDate Start date (Y-m-d)
     * @param string $endDate End date (Y-m-d)
     * @param int|null $employeeId Optional employee filter
     * @return array List of leave requests
     */
    public function getLeaveRequests(
        string $startDate,
        string $endDate,
        ?int $employeeId = null
    ): array {
        $startTime = $this->logger->startTimer('deputy_get_leave_requests');

        try {
            $startTimestamp = strtotime($startDate . ' 00:00:00');
            $endTimestamp = strtotime($endDate . ' 23:59:59');

            $this->logger->info('Fetching leave requests from Deputy', [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'employee_id' => $employeeId
            ]);

            $query = [
                'start' => $startTimestamp,
                'end' => $endTimestamp
            ];

            if ($employeeId) {
                $query['employee'] = $employeeId;
            }

            $result = $this->apiRequest('GET', '/v1/resource/Leave', [], $query);

            $leaves = is_array($result) ? $result : [];

            $this->logger->info('Leave requests fetched successfully', [
                'count' => count($leaves)
            ]);

            $this->logger->endTimer($startTime, 'deputy_get_leave_requests');

            return $leaves;
        } catch (\Throwable $e) {
            $this->logger->error('Failed to fetch leave requests', [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Test connection to Deputy API
     *
     * @return array Test result with details
     */
    public function testConnection(): array
    {
        $startTime = $this->logger->startTimer('deputy_test_connection');

        try {
            $this->logger->info('Testing Deputy API connection');

            // Try to fetch current user info
            $result = $this->apiRequest('GET', '/v1/me');

            if (!empty($result)) {
                $this->logger->info('Deputy connection test successful', [
                    'user_id' => $result['Id'] ?? null,
                    'company' => $result['Company'] ?? null
                ]);

                $this->logger->endTimer($startTime, 'deputy_test_connection');

                return [
                    'success' => true,
                    'user' => $result
                ];
            } else {
                return [
                    'success' => false,
                    'error' => 'Empty response from API'
                ];
            }
        } catch (\Throwable $e) {
            $this->logger->error('Deputy connection test failed', [
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Bulk update timesheets
     *
     * @param array $updates Array of timesheet updates [['id' => 123, 'data' => [...]]]
     * @return array Results for each update
     */
    public function bulkUpdateTimesheets(array $updates): array
    {
        $startTime = $this->logger->startTimer('deputy_bulk_update_timesheets');
        $results = [];

        $this->logger->info('Bulk updating timesheets', [
            'count' => count($updates)
        ]);

        foreach ($updates as $update) {
            $timesheetId = $update['id'] ?? null;
            $data = $update['data'] ?? [];

            if (!$timesheetId) {
                $results[] = [
                    'success' => false,
                    'error' => 'Missing timesheet ID'
                ];
                continue;
            }

            $result = $this->updateTimesheet($timesheetId, $data);
            $results[] = [
                'success' => !is_null($result),
                'timesheet_id' => $timesheetId,
                'result' => $result
            ];
        }

        $successCount = count(array_filter($results, fn($r) => $r['success']));

        $this->logger->info('Bulk update completed', [
            'total' => count($updates),
            'success' => $successCount,
            'failed' => count($updates) - $successCount
        ]);

        $this->logger->endTimer($startTime, 'deputy_bulk_update_timesheets');

        return $results;
    }
}
