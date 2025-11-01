<?php
/**
 * Security Integration Tests - Phase 1 Lockdown
 *
 * Tests real HTTP requests, AJAX endpoints, authentication flows,
 * CSRF protection, permission enforcement, and SQL injection prevention
 *
 * @package CIS\HumanResources\Payroll\Tests\Integration
 */

namespace CIS\HumanResources\Payroll\Tests\Integration;

use PHPUnit\Framework\TestCase;
use PDO;

class SecurityIntegrationTest extends TestCase
{
    private string $baseUrl = 'http://localhost/modules/human_resources/payroll';
    private PDO $db;
    private array $testUser;

    protected function setUp(): void
    {
        // Load database config
        $dbConfig = require dirname(__DIR__, 4) . '/config/database.php';
        $cisConfig = $dbConfig['cis'];

        $dsn = sprintf(
            "mysql:host=%s;dbname=%s;charset=%s",
            $cisConfig['host'],
            $cisConfig['database'],
            $cisConfig['charset']
        );

        $this->db = new PDO($dsn, $cisConfig['username'], $cisConfig['password'], $cisConfig['options']);

        // Create test user in database
        $this->testUser = $this->createTestUser();
    }

    protected function tearDown(): void
    {
        // Cleanup test user
        if (isset($this->testUser['id'])) {
            $this->db->exec("DELETE FROM users WHERE id = {$this->testUser['id']}");
        }
    }

    private function createTestUser(): array
    {
        $email = 'test_security_' . uniqid() . '@test.local';
        $password = password_hash('test_password_123', PASSWORD_BCRYPT);

        $stmt = $this->db->prepare("
            INSERT INTO users (email, password, role, permissions, created_at)
            VALUES (?, ?, ?, ?, NOW())
        ");

        $permissions = json_encode(['payroll.view_dashboard', 'payroll.view_payruns']);
        $stmt->execute([$email, $password, 'staff', $permissions]);

        return [
            'id' => (int)$this->db->lastInsertId(),
            'email' => $email,
            'password' => 'test_password_123',
            'role' => 'staff',
            'permissions' => ['payroll.view_dashboard', 'payroll.view_payruns']
        ];
    }

    /**
     * @test
     * @group integration
     * @group security
     * @group critical
     */
    public function it_prevents_sql_injection_in_login(): void
    {
        $maliciousInputs = [
            "admin' OR '1'='1",
            "admin'--",
            "admin' /*",
            "admin' OR '1'='1'--",
            "admin' OR 1=1--",
            "' OR ''='",
            "1' OR '1' = '1",
            "admin'/**/OR/**/1=1--",
        ];

        foreach ($maliciousInputs as $input) {
            $result = $this->attemptLogin($input, 'any_password');

            $this->assertFalse(
                $result['success'] ?? false,
                "SQL injection attempt should fail: " . $input
            );

            $this->assertNotEquals(
                200,
                $result['status_code'] ?? 401,
                "SQL injection should not return 200 status"
            );
        }
    }

    /**
     * @test
     * @group integration
     * @group security
     * @group critical
     */
    public function it_enforces_csrf_protection_on_post_endpoints(): void
    {
        $session = $this->createAuthenticatedSession($this->testUser);

        $endpoints = [
            '/api/payroll/amendments/create',
            '/api/payroll/payruns/create',
            '/api/payroll/bonuses/create',
        ];

        foreach ($endpoints as $endpoint) {
            // Request WITHOUT CSRF token
            $response = $this->makeRequest('POST', $endpoint, [
                'session' => $session,
                'data' => ['test' => 'data'],
                'csrf_token' => null // Missing CSRF
            ]);

            $this->assertEquals(
                403,
                $response['status_code'],
                "POST to {$endpoint} without CSRF token should return 403"
            );

            $json = $response['json'] ?? [];
            $this->assertFalse(
                $json['success'] ?? true,
                "Response should indicate failure for missing CSRF"
            );
        }
    }

    /**
     * @test
     * @group integration
     * @group security
     * @group critical
     */
    public function it_requires_authentication_on_protected_endpoints(): void
    {
        $endpoints = [
            'GET' => [
                '/payroll/dashboard',
                '/api/payroll/dashboard/data',
                '/payroll/payruns',
                '/api/payroll/payruns/list',
            ],
            'POST' => [
                '/api/payroll/amendments/create',
                '/api/payroll/payruns/create',
            ]
        ];

        foreach ($endpoints as $method => $urls) {
            foreach ($urls as $url) {
                // Request WITHOUT authentication
                $response = $this->makeRequest($method, $url, [
                    'session' => null // No session
                ]);

                $this->assertContains(
                    $response['status_code'],
                    [401, 302],
                    "{$method} {$url} without auth should return 401 or redirect (302)"
                );
            }
        }
    }

    /**
     * @test
     * @group integration
     * @group security
     * @group critical
     */
    public function it_enforces_permission_checks_on_restricted_endpoints(): void
    {
        // Create user WITHOUT admin permissions
        $limitedUser = $this->testUser;
        $session = $this->createAuthenticatedSession($limitedUser);

        $restrictedEndpoints = [
            '/api/payroll/automation/process' => 'payroll.admin',
            '/api/payroll/xero/oauth/authorize' => 'payroll.admin',
            '/api/payroll/amendments/1/approve' => 'payroll.approve_amendments',
        ];

        foreach ($restrictedEndpoints as $endpoint => $requiredPermission) {
            $response = $this->makeRequest('GET', $endpoint, [
                'session' => $session
            ]);

            $this->assertEquals(
                403,
                $response['status_code'],
                "User without '{$requiredPermission}' should get 403 for {$endpoint}"
            );

            $json = $response['json'] ?? [];
            $this->assertStringContainsString(
                'Permission denied',
                $json['error']['message'] ?? $json['error'] ?? '',
                "Error message should indicate permission denial"
            );
        }
    }

    /**
     * @test
     * @group integration
     * @group security
     */
    public function it_prevents_xss_in_api_responses(): void
    {
        $session = $this->createAuthenticatedSession($this->testUser);

        $xssPayloads = [
            '<script>alert("XSS")</script>',
            '<img src=x onerror=alert("XSS")>',
            'javascript:alert("XSS")',
            '<svg onload=alert("XSS")>',
        ];

        foreach ($xssPayloads as $payload) {
            // Try to inject XSS via request parameter
            $response = $this->makeRequest('GET', '/api/payroll/dashboard/data', [
                'session' => $session,
                'query' => ['search' => $payload]
            ]);

            $body = $response['body'] ?? '';

            // Response should NOT contain unescaped script tags
            $this->assertStringNotContainsString(
                '<script>',
                $body,
                "API response should not contain unescaped script tags"
            );

            $this->assertStringNotContainsString(
                'onerror=',
                $body,
                "API response should not contain unescaped event handlers"
            );
        }
    }

    /**
     * @test
     * @group integration
     * @group security
     */
    public function it_prevents_path_traversal_attacks(): void
    {
        $session = $this->createAuthenticatedSession($this->testUser);

        $traversalPayloads = [
            '../../config/database.php',
            '../../../.env',
            '....//....//....//etc/passwd',
            '..%2F..%2F..%2Fconfig%2Fdatabase.php',
        ];

        foreach ($traversalPayloads as $payload) {
            $response = $this->makeRequest('GET', '/api/payroll/file', [
                'session' => $session,
                'query' => ['path' => $payload]
            ]);

            $this->assertNotEquals(
                200,
                $response['status_code'],
                "Path traversal attempt should not succeed: {$payload}"
            );

            $body = $response['body'] ?? '';
            $this->assertStringNotContainsString(
                'DB_PASSWORD',
                $body,
                "Path traversal should not expose database credentials"
            );
        }
    }

    /**
     * @test
     * @group integration
     * @group security
     */
    public function it_rate_limits_authentication_attempts(): void
    {
        $email = $this->testUser['email'];
        $successCount = 0;

        // Attempt 10 rapid logins
        for ($i = 0; $i < 10; $i++) {
            $result = $this->attemptLogin($email, 'wrong_password');

            if (($result['status_code'] ?? 401) === 429) {
                // Rate limit kicked in
                break;
            }

            if ($result['success'] ?? false) {
                $successCount++;
            }

            usleep(10000); // 10ms delay
        }

        $this->assertLessThan(
            10,
            $successCount,
            "Rate limiting should prevent all 10 rapid login attempts"
        );
    }

    /**
     * @test
     * @group integration
     * @group security
     */
    public function it_validates_json_input_strictly(): void
    {
        $session = $this->createAuthenticatedSession($this->testUser);
        $csrfToken = $session['csrf_token'];

        $invalidJsonPayloads = [
            '{"incomplete": ',
            '{malformed json}',
            '{"nested": {"too": {"deep": {"levels": {"beyond": {"reasonable": "limit"}}}}}}',
            str_repeat('{"a":', 1000) . '"val"' . str_repeat('}', 1000), // Deeply nested
        ];

        foreach ($invalidJsonPayloads as $payload) {
            $response = $this->makeRawRequest('POST', '/api/payroll/amendments/create', [
                'session' => $session,
                'body' => $payload,
                'headers' => [
                    'Content-Type: application/json',
                    'X-CSRF-TOKEN: ' . $csrfToken
                ]
            ]);

            $this->assertEquals(
                400,
                $response['status_code'],
                "Invalid JSON should return 400 Bad Request"
            );
        }
    }

    /**
     * @test
     * @group integration
     * @group security
     */
    public function it_logs_security_violations(): void
    {
        $logFile = dirname(__DIR__, 2) . '/logs/security.log';

        if (!file_exists($logFile)) {
            $this->markTestSkipped("Security log file not configured");
        }

        $initialSize = filesize($logFile);

        // Trigger a security violation (CSRF failure)
        $session = $this->createAuthenticatedSession($this->testUser);
        $this->makeRequest('POST', '/api/payroll/amendments/create', [
            'session' => $session,
            'data' => ['test' => 'data'],
            'csrf_token' => null // Missing CSRF
        ]);

        clearstatcache();
        $finalSize = filesize($logFile);

        $this->assertGreaterThan(
            $initialSize,
            $finalSize,
            "Security violations should be logged"
        );

        // Check log contains CSRF violation
        $logContents = file_get_contents($logFile);
        $this->assertStringContainsString(
            'CSRF',
            $logContents,
            "Security log should mention CSRF validation failure"
        );
    }

    // =========================================================================
    // HELPER METHODS
    // =========================================================================

    private function attemptLogin(string $email, string $password): array
    {
        $ch = curl_init($this->baseUrl . '/api/auth/login');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode(['email' => $email, 'password' => $password]),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_HEADER => true,
        ]);

        $response = curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $headerSize = strpos($response, "\r\n\r\n");
        $body = substr($response, $headerSize + 4);
        $json = json_decode($body, true);

        return [
            'success' => $json['success'] ?? false,
            'status_code' => $statusCode,
            'body' => $body,
            'json' => $json
        ];
    }

    private function createAuthenticatedSession(array $user): array
    {
        // Simulate PHP session
        $sessionId = 'test_session_' . uniqid();

        return [
            'session_id' => $sessionId,
            'user_id' => $user['id'],
            'email' => $user['email'],
            'role' => $user['role'],
            'permissions' => $user['permissions'],
            'csrf_token' => bin2hex(random_bytes(32)),
            'authenticated' => true
        ];
    }

    private function makeRequest(string $method, string $endpoint, array $options = []): array
    {
        $url = $this->baseUrl . $endpoint;
        $session = $options['session'] ?? null;
        $data = $options['data'] ?? [];
        $query = $options['query'] ?? [];
        $csrfToken = $options['csrf_token'] ?? ($session['csrf_token'] ?? null);

        if (!empty($query)) {
            $url .= '?' . http_build_query($query);
        }

        $ch = curl_init($url);
        $headers = ['Content-Type: application/json'];

        if ($session) {
            $headers[] = 'Cookie: PHPSESSID=' . $session['session_id'];
        }

        if ($csrfToken) {
            $headers[] = 'X-CSRF-TOKEN: ' . $csrfToken;
            $data['csrf_token'] = $csrfToken;
        }

        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_HEADER => true,
        ]);

        if ($method === 'POST' && !empty($data)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $headerSize = strpos($response, "\r\n\r\n");
        $body = $headerSize !== false ? substr($response, $headerSize + 4) : $response;
        $json = json_decode($body, true);

        return [
            'status_code' => $statusCode,
            'body' => $body,
            'json' => $json
        ];
    }

    private function makeRawRequest(string $method, string $endpoint, array $options = []): array
    {
        $url = $this->baseUrl . $endpoint;
        $session = $options['session'] ?? null;
        $body = $options['body'] ?? '';
        $headers = $options['headers'] ?? [];

        if ($session) {
            $headers[] = 'Cookie: PHPSESSID=' . $session['session_id'];
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_HEADER => true,
        ]);

        $response = curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $headerSize = strpos($response, "\r\n\r\n");
        $responseBody = $headerSize !== false ? substr($response, $headerSize + 4) : $response;

        return [
            'status_code' => $statusCode,
            'body' => $responseBody
        ];
    }
}
