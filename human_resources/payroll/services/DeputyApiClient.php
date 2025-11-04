<?php
declare(strict_types=1);

namespace PayrollModule\Services;

use RuntimeException;

/**
 * Deputy API Client
 *
 * Real HTTP integration with Deputy REST API for timesheet management.
 *
 * API Documentation: https://www.deputy.com/api-doc/API/Resource_Calls
 *
 * @package CIS\HumanResources\Payroll\Services
 * @version 1.0.0
 */
class DeputyApiClient
{
    /**
     * @var string Deputy API base URL
     */
    private string $baseUrl;

    /**
     * @var string Deputy API bearer token
     */
    private string $apiToken;

    /**
     * @var int HTTP timeout in seconds
     */
    private int $timeout;

    /**
     * @var int Maximum retry attempts for rate limits/server errors
     */
    private const MAX_RETRIES = 3;

    /**
     * Constructor
     *
     * @param string|null $baseUrl API base URL (defaults to env)
     * @param string|null $apiToken API token (defaults to env)
     * @param int|null $timeout Timeout in seconds (defaults to env or 45)
     * @throws RuntimeException If required credentials missing
     */
    public function __construct(?string $baseUrl = null, ?string $apiToken = null, ?int $timeout = null)
    {
        $this->baseUrl = $baseUrl ?? $this->requireEnv('DEPUTY_API_BASE_URL');
        $this->apiToken = $apiToken ?? $this->requireEnv('DEPUTY_API_TOKEN');
        $this->timeout = $timeout ?? (int)($_ENV['DEPUTY_API_TIMEOUT'] ?? 45);

        // Ensure base URL doesn't have trailing slash
        $this->baseUrl = rtrim($this->baseUrl, '/');
    }

    /**
     * Create a new timesheet in Deputy
     *
     * @param int $employeeId Deputy employee ID
     * @param int $startTimestamp Start time (UNIX timestamp)
     * @param int $endTimestamp End time (UNIX timestamp)
     * @param int $breakMinutes Break duration in minutes
     * @param int $operationalUnitId Location/department ID
     * @param string $comment Comment for timesheet
     * @return array Deputy API response (includes 'Id' on success)
     * @throws RuntimeException On API error
     */
    public function createTimesheet(
        int $employeeId,
        int $startTimestamp,
        int $endTimestamp,
        int $breakMinutes,
        int $operationalUnitId,
        string $comment
    ): array {
        $payload = [
            'Employee' => $employeeId,
            'StartTime' => $startTimestamp,
            'EndTime' => $endTimestamp,
            'Breaks' => $breakMinutes * 60, // Deputy uses seconds
            'OperationalUnit' => $operationalUnitId,
            'Comment' => $comment,
            'IsInProgress' => false
        ];

        error_log(sprintf(
            "DeputyApiClient: Creating timesheet for employee %d, %s to %s, %dmin break",
            $employeeId,
            date('Y-m-d H:i', $startTimestamp),
            date('H:i', $endTimestamp),
            $breakMinutes
        ));

        return $this->post('/resource/Timesheet', $payload);
    }

    /**
     * Update an existing timesheet in Deputy
     *
     * @param int $timesheetId Deputy timesheet ID
     * @param int $startTimestamp New start time (UNIX timestamp)
     * @param int $endTimestamp New end time (UNIX timestamp)
     * @param int $breakMinutes Break duration in minutes
     * @param int $operationalUnitId Location/department ID
     * @param string $comment Comment for update
     * @return array Deputy API response
     * @throws RuntimeException On API error
     */
    public function updateTimesheet(
        int $timesheetId,
        int $startTimestamp,
        int $endTimestamp,
        int $breakMinutes,
        int $operationalUnitId,
        string $comment
    ): array {
        $payload = [
            'Id' => $timesheetId,
            'StartTime' => $startTimestamp,
            'EndTime' => $endTimestamp,
            'Breaks' => $breakMinutes * 60, // Deputy uses seconds
            'OperationalUnit' => $operationalUnitId,
            'Comment' => $comment
        ];

        error_log("DeputyApiClient: Updating timesheet $timesheetId");

        return $this->post("/resource/Timesheet/$timesheetId", $payload);
    }

    /**
     * Approve a timesheet in Deputy
     *
     * @param int $timesheetId Deputy timesheet ID to approve
     * @return array Deputy API response
     * @throws RuntimeException On API error
     */
    public function approveTimesheet(int $timesheetId): array
    {
        error_log("DeputyApiClient: Approving timesheet $timesheetId");

        return $this->post("/supervise/timesheet/$timesheetId", [
            'comment' => 'Auto-approved by CIS Payroll System'
        ]);
    }

    /**
     * Fetch timesheets for an employee on a specific date
     *
     * @param int $employeeId Deputy employee ID
     * @param string $date Date in Y-m-d format
     * @return array Array of timesheet objects
     * @throws RuntimeException On API error
     */
    public function fetchTimesheetsForDate(int $employeeId, string $date): array
    {
        // Deputy QUERY format: search criteria as JSON
        $query = [
            'search' => [
                'f1' => [
                    'field' => 'Employee',
                    'type' => 'eq',
                    'data' => $employeeId
                ],
                'f2' => [
                    'field' => 'Date',
                    'type' => 'eq',
                    'data' => $date
                ]
            ]
        ];

        $queryJson = json_encode($query);
        $endpoint = '/resource/Timesheet/QUERY?' . http_build_query(['search' => $queryJson]);

        error_log("DeputyApiClient: Fetching timesheets for employee $employeeId on $date");

        return $this->get($endpoint);
    }

    /**
     * Execute HTTP POST request with retry logic
     *
     * @param string $endpoint API endpoint (relative to base URL)
     * @param array $payload Request body
     * @param int $attempt Current attempt number (for retry logic)
     * @return array Parsed JSON response
     * @throws RuntimeException On final failure
     */
    private function post(string $endpoint, array $payload, int $attempt = 1): array
    {
        $url = $this->baseUrl . $endpoint;
        $json = json_encode($payload);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $json,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->apiToken,
                'Content-Type: application/json',
                'Accept: application/json'
            ]
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        // Handle cURL errors
        if ($response === false) {
            return $this->handleNetworkError($endpoint, $payload, $error, $attempt);
        }

        // Handle HTTP errors
        return $this->handleHttpResponse($endpoint, $payload, $response, $httpCode, $attempt);
    }

    /**
     * Execute HTTP GET request
     *
     * @param string $endpoint API endpoint (relative to base URL)
     * @param int $attempt Current attempt number (for retry logic)
     * @return array Parsed JSON response
     * @throws RuntimeException On final failure
     */
    private function get(string $endpoint, int $attempt = 1): array
    {
        $url = $this->baseUrl . $endpoint;

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->apiToken,
                'Accept: application/json'
            ]
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        // Handle cURL errors
        if ($response === false) {
            if ($attempt < 2) {
                error_log("DeputyApiClient: Network error on GET, retrying: $error");
                sleep(2);
                return $this->get($endpoint, $attempt + 1);
            }
            throw new RuntimeException("Deputy API network error: $error");
        }

        // Handle HTTP errors
        if ($httpCode < 200 || $httpCode >= 300) {
            throw new RuntimeException("Deputy API error: HTTP $httpCode - $response");
        }

        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException("Deputy API returned invalid JSON: " . json_last_error_msg());
        }

        return $data ?? [];
    }

    /**
     * Handle network errors with retry logic
     *
     * @param string $endpoint API endpoint
     * @param array $payload Request payload
     * @param string $error cURL error message
     * @param int $attempt Current attempt number
     * @return array Response on retry success
     * @throws RuntimeException On final failure
     */
    private function handleNetworkError(string $endpoint, array $payload, string $error, int $attempt): array
    {
        if ($attempt < 2) {
            error_log("DeputyApiClient: Network error, retrying ($attempt/" . self::MAX_RETRIES . "): $error");
            sleep(2 * $attempt); // Exponential backoff
            return $this->post($endpoint, $payload, $attempt + 1);
        }

        error_log("DeputyApiClient: Network error after retries: $error");
        throw new RuntimeException("Deputy API network error after retries: $error");
    }

    /**
     * Handle HTTP response codes with retry logic
     *
     * @param string $endpoint API endpoint
     * @param array $payload Request payload
     * @param string $response Raw response body
     * @param int $httpCode HTTP status code
     * @param int $attempt Current attempt number
     * @return array Parsed response
     * @throws RuntimeException On error
     */
    private function handleHttpResponse(
        string $endpoint,
        array $payload,
        string $response,
        int $httpCode,
        int $attempt
    ): array {
        // Success: 2xx
        if ($httpCode >= 200 && $httpCode < 300) {
            $data = json_decode($response, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new RuntimeException("Deputy API returned invalid JSON: " . json_last_error_msg());
            }
            return $data ?? [];
        }

        // Rate limit: 429 - Retry with exponential backoff
        if ($httpCode === 429 && $attempt < self::MAX_RETRIES) {
            $waitSeconds = pow(2, $attempt); // 2, 4, 8 seconds
            error_log("DeputyApiClient: Rate limit hit, waiting {$waitSeconds}s before retry ($attempt/" . self::MAX_RETRIES . ")");
            sleep($waitSeconds);
            return $this->post($endpoint, $payload, $attempt + 1);
        }

        // Server error: 500-503 - Retry once
        if ($httpCode >= 500 && $httpCode < 504 && $attempt < 2) {
            error_log("DeputyApiClient: Server error HTTP $httpCode, retrying ($attempt/2)");
            sleep(3);
            return $this->post($endpoint, $payload, $attempt + 1);
        }

        // Auth error: 401/403 - Don't retry
        if ($httpCode === 401 || $httpCode === 403) {
            error_log("DeputyApiClient: Authentication failed (HTTP $httpCode). Check DEPUTY_API_TOKEN.");
            throw new RuntimeException("Deputy API authentication failed: HTTP $httpCode");
        }

        // All other errors
        error_log("DeputyApiClient: HTTP $httpCode error: $response");
        throw new RuntimeException("Deputy API error: HTTP $httpCode - $response");
    }

    /**
     * Require environment variable (fail-fast if missing)
     *
     * @param string $key Environment variable name
     * @return string Value
     * @throws RuntimeException If variable missing
     */
    private function requireEnv(string $key): string
    {
        $value = $_ENV[$key] ?? getenv($key) ?: null;

        if ($value === null || $value === '') {
            throw new RuntimeException(
                "Required environment variable not set: $key. " .
                "Please ensure Deputy API credentials are configured in .env"
            );
        }

        return (string)$value;
    }
}
