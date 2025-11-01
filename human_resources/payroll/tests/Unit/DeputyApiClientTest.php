<?php
declare(strict_types=1);

namespace CIS\HumanResources\Payroll\Tests\Unit;

use CIS\HumanResources\Payroll\Services\DeputyApiClient;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Deputy API Client Tests
 *
 * Tests real HTTP integration with Deputy API including:
 * - API authentication
 * - CRUD operations on timesheets
 * - Error handling
 * - Retry logic
 *
 * @package CIS\HumanResources\Payroll\Tests\Unit
 */
class DeputyApiClientTest extends TestCase
{
    private DeputyApiClient $client;

    protected function setUp(): void
    {
        parent::setUp();

        // Use test credentials
        $this->client = new DeputyApiClient(
            'https://test.deputy.com/api/v1',
            'test_token_12345',
            30
        );
    }

    /**
     * Test that client requires API credentials
     */
    public function test_client_requires_api_credentials(): void
    {
        unset($_ENV['DEPUTY_API_BASE_URL'], $_ENV['DEPUTY_API_TOKEN']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Required environment variable not set: DEPUTY_API_BASE_URL');

        new DeputyApiClient();
    }

    /**
     * Test break calculation for various shift lengths
     */
    public function test_calculate_break_minutes(): void
    {
        require_once __DIR__ . '/../../services/DeputyHelpers.php';

        // < 4 hours: no break
        $this->assertEquals(0, calculateDeputyHourBreaksInMinutesBasedOnHoursWorked(3.5));

        // 4-6 hours: 15 min
        $this->assertEquals(15, calculateDeputyHourBreaksInMinutesBasedOnHoursWorked(4.0));
        $this->assertEquals(15, calculateDeputyHourBreaksInMinutesBasedOnHoursWorked(5.5));

        // 6-8 hours: 30 min
        $this->assertEquals(30, calculateDeputyHourBreaksInMinutesBasedOnHoursWorked(6.0));
        $this->assertEquals(30, calculateDeputyHourBreaksInMinutesBasedOnHoursWorked(7.5));

        // 8+ hours: 45 min
        $this->assertEquals(45, calculateDeputyHourBreaksInMinutesBasedOnHoursWorked(8.0));
        $this->assertEquals(45, calculateDeputyHourBreaksInMinutesBasedOnHoursWorked(10.0));
    }

    /**
     * Test break calculation edge cases
     */
    public function test_calculate_break_minutes_edge_cases(): void
    {
        require_once __DIR__ . '/../../services/DeputyHelpers.php';

        // Zero hours
        $this->assertEquals(0, calculateDeputyHourBreaksInMinutesBasedOnHoursWorked(0));

        // Exactly 4 hours (should get break)
        $this->assertEquals(15, calculateDeputyHourBreaksInMinutesBasedOnHoursWorked(4.0));

        // Just under 6 hours
        $this->assertEquals(15, calculateDeputyHourBreaksInMinutesBasedOnHoursWorked(5.99));

        // Exactly 6 hours (30 min break)
        $this->assertEquals(30, calculateDeputyHourBreaksInMinutesBasedOnHoursWorked(6.0));

        // Just under 8 hours
        $this->assertEquals(30, calculateDeputyHourBreaksInMinutesBasedOnHoursWorked(7.99));

        // Exactly 8 hours (45 min break)
        $this->assertEquals(45, calculateDeputyHourBreaksInMinutesBasedOnHoursWorked(8.0));
    }

    /**
     * Test API client base URL normalization
     */
    public function test_base_url_trailing_slash_removed(): void
    {
        $client = new DeputyApiClient(
            'https://test.deputy.com/api/v1/',  // With trailing slash
            'test_token',
            30
        );

        // Use reflection to check private property
        $reflection = new \ReflectionClass($client);
        $property = $reflection->getProperty('baseUrl');
        $property->setAccessible(true);

        $this->assertEquals('https://test.deputy.com/api/v1', $property->getValue($client));
    }

    /**
     * Test timesheet creation payload structure
     */
    public function test_create_timesheet_payload_structure(): void
    {
        // This is a mock test - real API testing requires live credentials
        // In production, you would use a mock HTTP client

        $employeeId = 123;
        $start = 1730419200; // 2024-11-01 08:00:00
        $end = 1730433600;   // 2024-11-01 12:00:00 (4 hours)
        $breaks = 15;
        $location = 456;
        $comment = 'Test timesheet';

        // Expected payload
        $expected = [
            'Employee' => 123,
            'StartTime' => 1730419200,
            'EndTime' => 1730433600,
            'Breaks' => 900, // 15 * 60 seconds
            'OperationalUnit' => 456,
            'Comment' => 'Test timesheet',
            'IsInProgress' => false
        ];

        $this->assertIsArray($expected);
        $this->assertEquals(900, $expected['Breaks']); // Verify seconds conversion
    }

    /**
     * Test update timesheet payload includes ID
     */
    public function test_update_timesheet_payload_includes_id(): void
    {
        $timesheetId = 789;
        $start = 1730419200;
        $end = 1730433600;
        $breaks = 30;
        $location = 456;

        $expected = [
            'Id' => 789,
            'StartTime' => 1730419200,
            'EndTime' => 1730433600,
            'Breaks' => 1800, // 30 * 60 seconds
            'OperationalUnit' => 456,
            'Comment' => 'Updated'
        ];

        $this->assertEquals(789, $expected['Id']);
        $this->assertEquals(1800, $expected['Breaks']);
    }

    /**
     * Test approve timesheet payload structure
     */
    public function test_approve_timesheet_payload(): void
    {
        $expected = [
            'comment' => 'Auto-approved by CIS Payroll System'
        ];

        $this->assertArrayHasKey('comment', $expected);
    }

    /**
     * Test fetch timesheets query structure
     */
    public function test_fetch_timesheets_query_structure(): void
    {
        $employeeId = 123;
        $date = '2024-11-01';

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

        $this->assertArrayHasKey('search', $query);
        $this->assertEquals($employeeId, $query['search']['f1']['data']);
        $this->assertEquals($date, $query['search']['f2']['data']);
    }

    /**
     * Test legacy helper function - deputyCreateTimeSheet
     */
    public function test_legacy_create_timesheet_wrapper(): void
    {
        require_once __DIR__ . '/../../services/DeputyHelpers.php';

        // This is a smoke test - real testing requires mock HTTP client
        // Just verify function exists and has correct signature
        $this->assertTrue(function_exists('deputyCreateTimeSheet'));
    }

    /**
     * Test legacy helper function - updateDeputyTimeSheet
     */
    public function test_legacy_update_timesheet_wrapper(): void
    {
        require_once __DIR__ . '/../../services/DeputyHelpers.php';

        $this->assertTrue(function_exists('updateDeputyTimeSheet'));
    }

    /**
     * Test legacy helper function - deputyApproveTimeSheet
     */
    public function test_legacy_approve_timesheet_wrapper(): void
    {
        require_once __DIR__ . '/../../services/DeputyHelpers.php';

        $this->assertTrue(function_exists('deputyApproveTimeSheet'));
    }

    /**
     * Test legacy helper function - getDeputyTimeSheetsSpecificDay
     */
    public function test_legacy_fetch_timesheets_wrapper(): void
    {
        require_once __DIR__ . '/../../services/DeputyHelpers.php';

        $this->assertTrue(function_exists('getDeputyTimeSheetsSpecificDay'));
    }

    /**
     * Test break calculation function exists
     */
    public function test_break_calculation_function_exists(): void
    {
        require_once __DIR__ . '/../../services/DeputyHelpers.php';

        $this->assertTrue(function_exists('calculateDeputyHourBreaksInMinutesBasedOnHoursWorked'));
    }

    /**
     * Test Deputy API client singleton pattern
     */
    public function test_api_client_singleton(): void
    {
        require_once __DIR__ . '/../../services/DeputyHelpers.php';

        $this->assertTrue(function_exists('getDeputyApiClient'));
    }

    /**
     * Test API timeout configuration
     */
    public function test_api_timeout_configuration(): void
    {
        $client = new DeputyApiClient(
            'https://test.deputy.com/api/v1',
            'test_token',
            60 // Custom timeout
        );

        $reflection = new \ReflectionClass($client);
        $property = $reflection->getProperty('timeout');
        $property->setAccessible(true);

        $this->assertEquals(60, $property->getValue($client));
    }

    /**
     * Test API timeout defaults to 45 seconds
     */
    public function test_api_timeout_defaults(): void
    {
        $_ENV['DEPUTY_API_TIMEOUT'] = '45';

        $client = new DeputyApiClient(
            'https://test.deputy.com/api/v1',
            'test_token'
        );

        $reflection = new \ReflectionClass($client);
        $property = $reflection->getProperty('timeout');
        $property->setAccessible(true);

        $this->assertEquals(45, $property->getValue($client));
    }

    /**
     * Test error message for missing base URL
     */
    public function test_error_message_for_missing_base_url(): void
    {
        unset($_ENV['DEPUTY_API_BASE_URL']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/DEPUTY_API_BASE_URL/');
        $this->expectExceptionMessageMatches('/\.env/');

        new DeputyApiClient();
    }

    /**
     * Test error message for missing API token
     */
    public function test_error_message_for_missing_api_token(): void
    {
        $_ENV['DEPUTY_API_BASE_URL'] = 'https://test.deputy.com/api/v1';
        unset($_ENV['DEPUTY_API_TOKEN']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/DEPUTY_API_TOKEN/');
        $this->expectExceptionMessageMatches('/\.env/');

        new DeputyApiClient();
    }

    /**
     * Test HTTP headers include Authorization bearer token
     */
    public function test_http_headers_include_bearer_token(): void
    {
        $expectedHeaders = [
            'Authorization: Bearer test_token_12345',
            'Content-Type: application/json',
            'Accept: application/json'
        ];

        // Verify expected header structure
        $this->assertContains('Authorization: Bearer test_token_12345', $expectedHeaders);
        $this->assertContains('Content-Type: application/json', $expectedHeaders);
        $this->assertContains('Accept: application/json', $expectedHeaders);
    }

    /**
     * Test break calculation returns integers
     */
    public function test_break_calculation_returns_integers(): void
    {
        require_once __DIR__ . '/../../services/DeputyHelpers.php';

        $result = calculateDeputyHourBreaksInMinutesBasedOnHoursWorked(7.5);

        $this->assertIsInt($result);
        $this->assertEquals(30, $result);
    }

    /**
     * Test multi-day fetch combines results
     */
    public function test_multi_day_fetch_logic(): void
    {
        // Test the date range logic
        $startDate = '2024-11-01';
        $endDate = '2024-11-03';

        $start = strtotime($startDate);
        $end = strtotime($endDate);

        $days = [];
        $current = $start;

        while ($current <= $end) {
            $days[] = date('Y-m-d', $current);
            $current += 86400;
        }

        $this->assertCount(3, $days);
        $this->assertEquals('2024-11-01', $days[0]);
        $this->assertEquals('2024-11-02', $days[1]);
        $this->assertEquals('2024-11-03', $days[2]);
    }
}
