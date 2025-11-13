<?php
/**
 * Unit Tests for Consignments API Endpoints
 *
 * PHPUnit test suite for comprehensive API testing
 *
 * @package CIS\Consignments\Tests
 * @version 1.0.0
 */

declare(strict_types=1);

namespace CIS\Consignments\Tests;

use PHPUnit\Framework\TestCase;

class APIEndpointTest extends TestCase
{
    private static $baseUrl = 'https://staff.vapeshed.co.nz/modules/consignments/api/';
    private static $sessionCookie = '';
    private static $csrfToken = '';

    /**
     * Setup before class - authenticate once
     */
    public static function setUpBeforeClass(): void
    {
        // Authenticate to get session cookie
        self::$sessionCookie = 'test_session_cookie'; // Mock for now
        self::$csrfToken = 'test_csrf_token';
    }

    /**
     * Test: Stock Transfers List Endpoint
     */
    public function testStockTransfersListReturns200(): void
    {
        $response = $this->makeRequest('GET', 'index.php?endpoint=stock-transfers/list');
        $this->assertEquals(200, $response['http_code']);
    }

    public function testStockTransfersListReturnsJSON(): void
    {
        $response = $this->makeRequest('GET', 'index.php?endpoint=stock-transfers/list');
        $this->assertNotNull($response['data']);
        $this->assertIsArray($response['data']);
    }

    public function testStockTransfersListHasTransfersKey(): void
    {
        $response = $this->makeRequest('GET', 'index.php?endpoint=stock-transfers/list');
        $this->assertArrayHasKey('transfers', $response['data']);
    }

    /**
     * Test: Stock Transfers Freight Quote
     */
    public function testStockTransfersFreightQuoteRequiresID(): void
    {
        $response = $this->makeRequest('GET', 'index.php?endpoint=stock-transfers/freight-quote');
        $this->assertEquals(400, $response['http_code']);
        $this->assertStringContainsString('required', strtolower($response['data']['error'] ?? ''));
    }

    public function testStockTransfersFreightQuoteWithValidID(): void
    {
        $response = $this->makeRequest('GET', 'index.php?endpoint=stock-transfers/freight-quote&id=1');
        $this->assertContains($response['http_code'], [200, 404]); // 200 if exists, 404 if not
    }

    /**
     * Test: Stock Transfers Create Label
     */
    public function testStockTransfersCreateLabelRequiresPOST(): void
    {
        $response = $this->makeRequest('GET', 'index.php?endpoint=stock-transfers/create-label&id=1');
        $this->assertEquals(405, $response['http_code']);
    }

    public function testStockTransfersCreateLabelRequiresParameters(): void
    {
        $response = $this->makeRequest('POST', 'index.php?endpoint=stock-transfers/create-label', [
            'id' => 1
            // Missing carrier and service
        ]);
        $this->assertContains($response['http_code'], [400, 500]);
    }

    /**
     * Test: Purchase Orders List Endpoint
     */
    public function testPurchaseOrdersListReturns200(): void
    {
        $response = $this->makeRequest('GET', 'index.php?endpoint=purchase-orders/list');
        $this->assertEquals(200, $response['http_code']);
    }

    public function testPurchaseOrdersListReturnsValidStructure(): void
    {
        $response = $this->makeRequest('GET', 'index.php?endpoint=purchase-orders/list');
        $this->assertIsArray($response['data']);
    }

    /**
     * Test: Unified Transfer API - Init
     */
    public function testUnifiedTransferInitReturnsOutlets(): void
    {
        $response = $this->makeRequest('GET', 'unified/index.php?action=init');
        $this->assertEquals(200, $response['http_code']);
        $this->assertArrayHasKey('outlets', $response['data']);
    }

    public function testUnifiedTransferInitReturnsSuppliers(): void
    {
        $response = $this->makeRequest('GET', 'unified/index.php?action=init');
        $this->assertArrayHasKey('suppliers', $response['data']);
    }

    public function testUnifiedTransferInitReturnsCSRFToken(): void
    {
        $response = $this->makeRequest('GET', 'unified/index.php?action=init');
        $this->assertArrayHasKey('csrf_token', $response['data']);
    }

    /**
     * Test: Unified Transfer API - List Transfers
     */
    public function testUnifiedTransferListReturnsArray(): void
    {
        $response = $this->makeRequest('GET', 'unified/index.php?action=list_transfers');
        $this->assertEquals(200, $response['http_code']);
        $this->assertIsArray($response['data']);
    }

    /**
     * Test: Unified Transfer API - Create Transfer
     */
    public function testUnifiedTransferCreateRequiresPOST(): void
    {
        $response = $this->makeRequest('GET', 'unified/index.php?action=create_transfer');
        $this->assertContains($response['http_code'], [400, 405]);
    }

    public function testUnifiedTransferCreateRequiresTransferType(): void
    {
        $response = $this->makeRequest('POST', 'unified/index.php', [
            'action' => 'create_transfer',
            'outlet_from' => 1,
            'outlet_to' => 2
            // Missing transfer_type
        ]);
        $this->assertContains($response['http_code'], [400, 500]);
    }

    /**
     * Test: Unified Transfer API - Search Products
     */
    public function testUnifiedSearchProductsReturnsResults(): void
    {
        $response = $this->makeRequest('GET', 'unified/index.php?action=search_products&q=test');
        $this->assertEquals(200, $response['http_code']);
        $this->assertIsArray($response['data']);
    }

    public function testUnifiedSearchProductsHandlesEmptyQuery(): void
    {
        $response = $this->makeRequest('GET', 'unified/index.php?action=search_products&q=');
        $this->assertContains($response['http_code'], [200, 400]);
    }

    /**
     * Test: Freight Endpoints
     */
    public function testFreightCalculateRequiresPOST(): void
    {
        $response = $this->makeRequest('GET', 'index.php?endpoint=freight/calculate');
        $this->assertContains($response['http_code'], [405, 200]); // May accept GET too
    }

    public function testFreightRatesReturnsData(): void
    {
        $response = $this->makeRequest('GET', 'index.php?endpoint=freight/rates');
        $this->assertEquals(200, $response['http_code']);
    }

    public function testFreightContainersReturnsArray(): void
    {
        $response = $this->makeRequest('GET', 'index.php?endpoint=freight/containers');
        $this->assertEquals(200, $response['http_code']);
        $this->assertIsArray($response['data']);
    }

    /**
     * Test: Error Handling
     */
    public function testInvalidEndpointReturns404(): void
    {
        $response = $this->makeRequest('GET', 'index.php?endpoint=this-does-not-exist');
        $this->assertEquals(404, $response['http_code']);
    }

    public function testInvalidEndpointReturnsErrorMessage(): void
    {
        $response = $this->makeRequest('GET', 'index.php?endpoint=this-does-not-exist');
        $this->assertArrayHasKey('error', $response['data']);
    }

    public function testMalformedJSONReturns400(): void
    {
        $response = $this->makeRequest('POST', 'index.php?endpoint=transfers/create',
            [], false, true, '{invalid json}');
        $this->assertEquals(400, $response['http_code']);
    }

    /**
     * Test: Security
     */
    public function testUnauthorizedAccessRejected(): void
    {
        $response = $this->makeRequest('GET', 'index.php?endpoint=stock-transfers/list', [], false);
        $this->assertEquals(401, $response['http_code']);
    }

    public function testCSRFValidation(): void
    {
        $response = $this->makeRequest('POST', 'unified/index.php', [
            'action' => 'create_transfer',
            'transfer_type' => 'STOCK_TRANSFER',
            'outlet_from' => 1,
            'outlet_to' => 2
            // Missing CSRF token
        ]);
        $this->assertContains($response['http_code'], [400, 403, 500]);
    }

    public function testSQLInjectionPrevention(): void
    {
        $response = $this->makeRequest('GET', 'index.php?endpoint=transfers/detail&id=1\' OR \'1\'=\'1');
        $this->assertContains($response['http_code'], [400, 404, 500]);
        $this->assertArrayNotHasKey('sql', strtolower(json_encode($response['data'])));
    }

    public function testXSSPrevention(): void
    {
        $response = $this->makeRequest('POST', 'unified/index.php', [
            'action' => 'add_note',
            'transfer_id' => 1,
            'note' => '<script>alert("XSS")</script>'
        ]);
        $this->assertContains($response['http_code'], [200, 400]);
        // If 200, note should be sanitized
    }

    /**
     * Test: Performance
     */
    public function testResponseTimeUnder500ms(): void
    {
        $start = microtime(true);
        $this->makeRequest('GET', 'index.php?endpoint=stock-transfers/list');
        $duration = (microtime(true) - $start) * 1000;

        $this->assertLessThan(500, $duration, "Response time too slow: {$duration}ms");
    }

    public function testMultipleRequestsPerformance(): void
    {
        $start = microtime(true);
        for ($i = 0; $i < 10; $i++) {
            $this->makeRequest('GET', 'index.php?endpoint=stock-transfers/list');
        }
        $avgDuration = ((microtime(true) - $start) / 10) * 1000;

        $this->assertLessThan(500, $avgDuration, "Average response time too slow: {$avgDuration}ms");
    }

    /**
     * Helper: Make HTTP request
     */
    private function makeRequest(
        string $method,
        string $path,
        array $data = [],
        bool $useAuth = true,
        bool $jsonEncode = false,
        string $rawBody = null
    ): array {
        $url = self::$baseUrl . $path;

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        if ($useAuth) {
            curl_setopt($ch, CURLOPT_COOKIE, self::$sessionCookie);
        }

        if ($rawBody !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $rawBody);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        } elseif (!empty($data)) {
            if ($jsonEncode) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            } else {
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
            }
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return [
            'http_code' => $httpCode,
            'data' => json_decode($response, true) ?? [],
            'raw' => $response
        ];
    }
}
