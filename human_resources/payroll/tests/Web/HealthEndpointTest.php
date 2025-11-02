<?php

declare(strict_types=1);

namespace HumanResources\Payroll\Tests\Web;

use PHPUnit\Framework\TestCase;

/**
 * Web tests for Health Endpoint
 * 
 * Tests the /health/index.php endpoint for proper responses
 * 
 * @covers health/index.php
 */
class HealthEndpointTest extends TestCase
{
    private string $baseUrl;
    private string $healthEndpoint;
    
    protected function setUp(): void
    {
        $this->baseUrl = 'https://staff.vapeshed.co.nz/modules/human_resources/payroll';
        $this->healthEndpoint = $this->baseUrl . '/health/index.php';
        
        // Skip if testing in isolation without live server
        if (getenv('SKIP_WEB_TESTS') === 'true') {
            $this->markTestSkipped('Web tests skipped (SKIP_WEB_TESTS=true)');
        }
    }
    
    public function testHealthEndpointIsAccessible(): void
    {
        $response = $this->makeRequest($this->healthEndpoint);
        
        $this->assertNotFalse($response, 'Health endpoint should be accessible');
    }
    
    public function testHealthEndpointReturnsJson(): void
    {
        $response = $this->makeRequest($this->healthEndpoint);
        
        $decoded = json_decode($response, true);
        
        $this->assertIsArray($decoded, 'Response should be valid JSON');
        $this->assertNotNull($decoded, 'Response should decode successfully');
    }
    
    public function testHealthEndpointHasOkField(): void
    {
        $response = $this->makeRequest($this->healthEndpoint);
        $data = json_decode($response, true);
        
        $this->assertArrayHasKey('ok', $data, 'Response should have "ok" field');
        $this->assertIsBool($data['ok'], '"ok" field should be boolean');
    }
    
    public function testHealthEndpointHasChecksField(): void
    {
        $response = $this->makeRequest($this->healthEndpoint);
        $data = json_decode($response, true);
        
        $this->assertArrayHasKey('checks', $data, 'Response should have "checks" field');
        $this->assertIsArray($data['checks'], '"checks" field should be array');
    }
    
    public function testHealthChecksHaveRequiredFields(): void
    {
        $response = $this->makeRequest($this->healthEndpoint);
        $data = json_decode($response, true);
        
        $this->assertNotEmpty($data['checks'], 'Should have at least one check');
        
        foreach ($data['checks'] as $check) {
            $this->assertArrayHasKey('name', $check, 'Each check should have name');
            $this->assertArrayHasKey('ok', $check, 'Each check should have ok status');
            
            $this->assertIsString($check['name'], 'Check name should be string');
            $this->assertIsBool($check['ok'], 'Check ok should be boolean');
        }
    }
    
    public function testHealthEndpointIncludesDatabaseCheck(): void
    {
        $response = $this->makeRequest($this->healthEndpoint);
        $data = json_decode($response, true);
        
        $checkNames = array_column($data['checks'], 'name');
        
        $hasDatabaseCheck = in_array('database_connectivity', $checkNames) ||
                           in_array('database', $checkNames) ||
                           in_array('db_ping', $checkNames);
        
        $this->assertTrue($hasDatabaseCheck, 'Should include database connectivity check');
    }
    
    public function testHealthEndpointIncludesTableChecks(): void
    {
        $response = $this->makeRequest($this->healthEndpoint);
        $data = json_decode($response, true);
        
        $checkNames = array_column($data['checks'], 'name');
        $fullCheckString = implode(',', $checkNames);
        
        $expectedTables = [
            'payroll_auth_audit_log',
            'payroll_activity_log',
            'payroll_rate_limits',
        ];
        
        foreach ($expectedTables as $table) {
            $hasTableCheck = strpos($fullCheckString, $table) !== false;
            $this->assertTrue($hasTableCheck, "Should check {$table} table");
        }
    }
    
    public function testHealthEndpointReturns200Status(): void
    {
        $headers = $this->getHeaders($this->healthEndpoint);
        
        $this->assertStringContainsString('200', $headers[0] ?? '', 'Should return 200 OK status');
    }
    
    public function testHealthEndpointSetsJsonContentType(): void
    {
        $headers = $this->getHeaders($this->healthEndpoint);
        $headersString = implode("\n", $headers);
        
        $this->assertStringContainsString('application/json', $headersString, 'Should set JSON content type');
    }
    
    public function testHealthEndpointHandlesMultipleRequests(): void
    {
        $responses = [];
        
        for ($i = 0; $i < 3; $i++) {
            $responses[] = $this->makeRequest($this->healthEndpoint);
        }
        
        foreach ($responses as $response) {
            $this->assertNotFalse($response);
            $data = json_decode($response, true);
            $this->assertIsArray($data);
        }
    }
    
    public function testHealthEndpointResponseTime(): void
    {
        $startTime = microtime(true);
        
        $this->makeRequest($this->healthEndpoint);
        
        $responseTime = microtime(true) - $startTime;
        
        $this->assertLessThan(2.0, $responseTime, 'Health endpoint should respond in under 2 seconds');
    }
    
    public function testHealthEndpointDoesNotExposeSecrets(): void
    {
        $response = $this->makeRequest($this->healthEndpoint);
        
        $sensitivePatterns = [
            '/wprKh9Jq63/',
            '/password/i',
            '/api[_-]?key/i',
            '/secret/i',
            '/token/i',
        ];
        
        foreach ($sensitivePatterns as $pattern) {
            $this->assertDoesNotMatchRegularExpression(
                $pattern,
                $response,
                "Response should not expose sensitive information matching {$pattern}"
            );
        }
    }
    
    public function testHealthEndpointHandlesMethodsCorrectly(): void
    {
        // GET should work
        $getResponse = $this->makeRequest($this->healthEndpoint, 'GET');
        $this->assertNotFalse($getResponse);
        
        // POST should also work for health checks
        $postResponse = $this->makeRequest($this->healthEndpoint, 'POST');
        $this->assertNotFalse($postResponse);
    }
    
    private function makeRequest(string $url, string $method = 'GET'): string|false
    {
        $context = stream_context_create([
            'http' => [
                'method' => $method,
                'timeout' => 5,
                'ignore_errors' => true,
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ]
        ]);
        
        return @file_get_contents($url, false, $context);
    }
    
    private function getHeaders(string $url): array
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'HEAD',
                'timeout' => 5,
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ]
        ]);
        
        @file_get_contents($url, false, $context);
        
        return $http_response_header ?? [];
    }
}
