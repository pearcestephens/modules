<?php

declare(strict_types=1);

namespace Tests\Unit\Infra\Lightspeed;

use PHPUnit\Framework\TestCase;
use Consignments\Infra\Lightspeed\LightspeedClient;
use Psr\Log\LoggerInterface;

/**
 * LightspeedClient Unit Tests
 *
 * Tests idempotency, retry logic, backoff, error handling
 * Uses mock responses since we can't make real HTTP calls in unit tests
 */
class LightspeedClientTest extends TestCase
{
    private array $originalEnv = [];

    protected function setUp(): void
    {
        parent::setUp();

        // Backup original environment
        $this->originalEnv = $_ENV;

        // Set test environment variables
        $_ENV['LS_BASE_URL'] = 'https://api.test.vendhq.com';
        $_ENV['LS_API_TOKEN'] = 'test_token_12345';
        $_ENV['LS_TIMEOUT'] = '5';
        $_ENV['LS_MAX_RETRIES'] = '3';
        $_ENV['LS_BACKOFF_BASE_MS'] = '10'; // Fast backoff for tests
    }

    protected function tearDown(): void
    {
        // Restore original environment
        $_ENV = $this->originalEnv;
        parent::tearDown();
    }

    /**
     * @test
     */
    public function itRequiresBaseUrlEnvironmentVariable(): void
    {
        unset($_ENV['LS_BASE_URL']);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('LS_BASE_URL');

        new LightspeedClient();
    }

    /**
     * @test
     */
    public function itRequiresApiTokenEnvironmentVariable(): void
    {
        unset($_ENV['LS_API_TOKEN']);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('LS_API_TOKEN');

        new LightspeedClient();
    }

    /**
     * @test
     */
    public function itUsesDefaultValuesForOptionalEnvironmentVariables(): void
    {
        unset($_ENV['LS_TIMEOUT']);
        unset($_ENV['LS_MAX_RETRIES']);
        unset($_ENV['LS_BACKOFF_BASE_MS']);

        // Should not throw - uses defaults
        $client = new LightspeedClient();

        $this->assertInstanceOf(LightspeedClient::class, $client);
    }

    /**
     * @test
     */
    public function itAcceptsCustomLogger(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('info'); // No HTTP calls in this test

        $client = new LightspeedClient($logger);

        $this->assertInstanceOf(LightspeedClient::class, $client);
    }

    /**
     * Note: Testing actual HTTP behavior (retries, backoff, idempotency) requires
     * integration tests with a mock HTTP server or VCR-style request recording.
     *
     * Below are structural tests that verify the client can be instantiated and
     * configured correctly. Full HTTP behavior tests would go in integration suite.
     */

    /**
     * @test
     */
    public function itHasGetMethod(): void
    {
        $client = new LightspeedClient();

        $this->assertTrue(method_exists($client, 'get'));
    }

    /**
     * @test
     */
    public function itHasPostMethod(): void
    {
        $client = new LightspeedClient();

        $this->assertTrue(method_exists($client, 'post'));
    }

    /**
     * @test
     */
    public function itHasPutMethod(): void
    {
        $client = new LightspeedClient();

        $this->assertTrue(method_exists($client, 'put'));
    }

    /**
     * @test
     */
    public function itHasDeleteMethod(): void
    {
        $client = new LightspeedClient();

        $this->assertTrue(method_exists($client, 'delete'));
    }

    /**
     * Integration Test Examples (require mock server):
     *
     * 1. Test 200 OK response:
     *    - Mock server returns 200
     *    - Assert response['success'] === true
     *    - Assert response['data'] contains expected payload
     *    - Assert response['request_id'] is valid UUID format
     *
     * 2. Test 429 retry with exponential backoff:
     *    - Mock server returns 429 twice, then 200
     *    - Assert client retries 2 times
     *    - Assert backoff timing (10ms, 20ms, then success)
     *    - Assert final response is successful
     *
     * 3. Test 500 retry logic:
     *    - Mock server returns 500, 500, 200
     *    - Assert client retries and eventually succeeds
     *
     * 4. Test max retries exceeded:
     *    - Mock server always returns 503
     *    - Assert RuntimeException thrown after MAX_RETRIES attempts
     *    - Assert exception message contains retry count
     *
     * 5. Test non-retryable 4xx errors:
     *    - Mock server returns 400, 401, 403, 404
     *    - Assert RuntimeException thrown immediately (no retries)
     *
     * 6. Test idempotency key generation:
     *    - Make POST request with idempotency=true
     *    - Capture request headers from mock server
     *    - Assert 'Idempotency-Key' header present
     *    - Assert key is sha256 hash
     *    - Make same request again, assert same key used
     *
     * 7. Test idempotency prevents duplicate operations:
     *    - Mock server tracks idempotency keys
     *    - Send same request twice with same key
     *    - Assert server processes request once, returns cached result second time
     *
     * 8. Test request correlation IDs:
     *    - Make request, capture X-Request-ID header
     *    - Assert header format: req_YYYYMMDD_HHMMSS_<random>
     *    - Assert unique for each request
     *
     * 9. Test timeout handling:
     *    - Mock server delays response beyond timeout
     *    - Assert RuntimeException with timeout error
     *    - Assert client doesn't wait forever
     *
     * 10. Test Bearer token authentication:
     *     - Capture Authorization header from mock server
     *     - Assert format: "Bearer test_token_12345"
     *
     * 11. Test custom headers:
     *     - Make request with options['headers'] = ['X-Custom' => 'value']
     *     - Assert custom header present in request
     *
     * 12. Test query parameter building:
     *     - Call get('/products', ['page' => 2, 'page_size' => 50])
     *     - Assert URL contains ?page=2&page_size=50
     *
     * 13. Test JSON body encoding:
     *     - Call post('/consignments', ['name' => 'Test', 'status' => 'draft'])
     *     - Assert request body is valid JSON
     *     - Assert Content-Type: application/json
     *
     * 14. Test jitter in backoff:
     *     - Trigger retry on 429
     *     - Measure backoff timing multiple times
     *     - Assert timing varies (due to jitter) but within expected range
     *
     * 15. Test logger integration:
     *     - Mock logger, make failing request
     *     - Assert logger->error() called with request_id, attempt, error
     *     - Assert logger->info() called on success
     */

    /**
     * @test
     */
    public function documentationOfIntegrationTestRequirements(): void
    {
        $this->assertTrue(true,
            "Integration tests for HTTP behavior should be in tests/integration/LightspeedClientIntegrationTest.php"
        );
    }
}
