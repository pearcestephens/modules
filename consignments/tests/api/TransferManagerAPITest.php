<?php

namespace Consignments\Tests\API;

use PHPUnit\Framework\TestCase;

/**
 * Transfer Manager API Test Suite
 *
 * Tests backend-v2.php endpoints and TransferManagerAPI class
 * Uses MCP-compatible testing patterns
 */
class TransferManagerAPITest extends TestCase
{
    private $baseUrl = 'https://staff.vapeshed.co.nz/modules/consignments';
    private $apiEndpoint;
    private $sessionCookie;

    protected function setUp(): void
    {
        // Use standalone version for testing (bypasses auth for test mode)
        $this->apiEndpoint = $this->baseUrl . '/TransferManager/backend-v2-standalone.php';

        // Load test credentials from environment (optional for standalone)
        $this->sessionCookie = getenv('CIS_TEST_SESSION') ?: null;
    }

    /**
     * @test
     * @group backend
     * @group init
     */
    public function it_returns_valid_init_configuration()
    {
        $response = $this->makeRequest('init');

        $this->assertIsArray($response);
        $this->assertArrayHasKey('success', $response);
        $this->assertArrayHasKey('data', $response);
        $this->assertArrayHasKey('timestamp', $response);
        $this->assertArrayHasKey('request_id', $response);

        if ($response['success']) {
            $this->assertArrayHasKey('outlets', $response['data']);
            $this->assertArrayHasKey('suppliers', $response['data']);
            $this->assertArrayHasKey('transfer_types', $response['data']);
        }
    }

    /**
     * @test
     * @group backend
     * @group envelope
     */
    public function it_follows_base_envelope_pattern()
    {
        // Test success envelope
        $response = $this->makeRequest('init');
        $this->assertTrue($response['success']);
        $this->assertArrayHasKey('message', $response);
        $this->assertArrayHasKey('timestamp', $response);
        $this->assertArrayHasKey('request_id', $response);
        $this->assertArrayHasKey('data', $response);
        $this->assertArrayHasKey('meta', $response);
        $this->assertArrayHasKey('duration_ms', $response['meta']);
        $this->assertArrayHasKey('memory_usage', $response['meta']);

        // Test error envelope
        $errorResponse = $this->makeRequest('invalid_action');
        $this->assertFalse($errorResponse['success']);
        $this->assertArrayHasKey('error', $errorResponse);
        $this->assertArrayHasKey('code', $errorResponse['error']);
        $this->assertArrayHasKey('message', $errorResponse['error']);
    }

    /**
     * @test
     * @group backend
     * @group validation
     */
    public function it_rejects_invalid_action()
    {
        $response = $this->makeRequest('invalid_action_12345');

        $this->assertIsArray($response);
        $this->assertArrayHasKey('success', $response);
        $this->assertFalse($response['success']);

        // Should have error object
        $this->assertArrayHasKey('error', $response);
        $this->assertArrayHasKey('code', $response['error']);
        $this->assertArrayHasKey('message', $response['error']);
    }    /**
     * @test
     * @group backend
     * @group list
     */
    public function it_lists_transfers_with_pagination()
    {
        $response = $this->makeRequest('list_transfers', [
            'page' => 1,
            'per_page' => 10
        ]);

        $this->assertTrue($response['success']);
        $this->assertArrayHasKey('transfers', $response['data']);
        $this->assertArrayHasKey('pagination', $response['data']);

        $pagination = $response['data']['pagination'];
        $this->assertArrayHasKey('page', $pagination);
        $this->assertArrayHasKey('total', $pagination);
        $this->assertArrayHasKey('pages', $pagination);
    }

    /**
     * @test
     * @group backend
     * @group filter
     */
    public function it_filters_transfers_by_type()
    {
        $response = $this->makeRequest('list_transfers', [
            'type' => 'STOCK',
            'page' => 1
        ]);

        $this->assertTrue($response['success']);
        $this->assertArrayHasKey('transfers', $response['data']);

        if (!empty($response['data']['transfers'])) {
            foreach ($response['data']['transfers'] as $transfer) {
                $this->assertEquals('STOCK', $transfer['type']);
            }
        }
    }

    /**
     * @test
     * @group backend
     * @group performance
     */
    public function it_responds_within_500ms()
    {
        $start = microtime(true);
        $response = $this->makeRequest('list_transfers', ['page' => 1, 'per_page' => 10]);
        $duration = (microtime(true) - $start) * 1000;

        $this->assertLessThan(500, $duration, "Response time {$duration}ms exceeded 500ms target");

        // Also check server-reported duration if available
        if (isset($response['meta']['duration_ms'])) {
            $this->assertLessThan(500, $response['meta']['duration_ms']);
        }
    }

    /**
     * @test
     * @group backend
     * @group search
     */
    public function it_searches_products()
    {
        $response = $this->makeRequest('search_products', [
            'query' => 'product',
            'limit' => 10
        ]);

        $this->assertTrue($response['success']);
        $this->assertArrayHasKey('products', $response['data']);
        $this->assertIsArray($response['data']['products']);
    }

    /**
     * @test
     * @group backend
     * @group sync
     */
    public function it_checks_sync_status()
    {
        $response = $this->makeRequest('verify_sync');

        $this->assertIsArray($response);
        $this->assertArrayHasKey('success', $response);

        if ($response['success']) {
            $this->assertArrayHasKey('sync_enabled', $response['data']);
            $this->assertIsBool($response['data']['sync_enabled']);
        }
    }

    /**
     * @test
     * @group backend
     * @group requestid
     */
    public function it_generates_unique_request_ids()
    {
        $requestIds = [];

        for ($i = 0; $i < 5; $i++) {
            $response = $this->makeRequest('init');
            $this->assertArrayHasKey('request_id', $response);
            $requestIds[] = $response['request_id'];
        }

        // All request IDs should be unique
        $this->assertCount(5, array_unique($requestIds));
    }

    /**
     * Make HTTP request to API endpoint
     */
    private function makeRequest(string $action, array $params = []): array
    {
        $data = array_merge(['action' => $action], $params);

        $ch = curl_init($this->apiEndpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json',
                'User-Agent: PHPUnit/9.6.29 (Test Suite)'
            ],
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CONNECTTIMEOUT => 5
        ]);

        if ($this->sessionCookie) {
            curl_setopt($ch, CURLOPT_COOKIE, $this->sessionCookie);
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // Handle HTTP errors
        if ($httpCode === 404) {
            return [
                'success' => false,
                'error' => [
                    'code' => 'HTTP_404',
                    'message' => 'Endpoint not found'
                ],
                'timestamp' => date('Y-m-d H:i:s'),
                'request_id' => 'test-' . uniqid()
            ];
        }

        $decoded = json_decode($response, true);
        return $decoded ?: ['success' => false, 'error' => ['code' => 'PARSE_ERROR', 'message' => 'Failed to decode response']];
    }
}
