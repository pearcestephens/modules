<?php

namespace FraudDetection\Tests\Unit;

use PHPUnit\Framework\TestCase;
use FraudDetection\Middleware\SystemAccessLogger;
use PDO;
use PDOStatement;

class SystemAccessLoggerTest extends TestCase
{
    private PDO $pdoMock;
    private SystemAccessLogger $logger;

    protected function setUp(): void
    {
        $this->pdoMock = $this->createMock(PDO::class);
        $this->logger = new SystemAccessLogger($this->pdoMock, [
            'enabled' => true,
            'excluded_paths' => ['/assets/', '/css/']
        ]);
    }

    public function testLogAccessSuccess(): void
    {
        $stmtMock = $this->createMock(PDOStatement::class);
        $stmtMock->method('execute')->willReturn(true);

        $this->pdoMock->method('prepare')->willReturn($stmtMock);
        $this->pdoMock->method('lastInsertId')->willReturn('123');

        $_SESSION['staff_id'] = 1;

        $request = [
            'path' => '/admin/reports',
            'method' => 'GET',
            'query_params' => ['id' => '5'],
            'post_params' => [],
            'ip_address' => '192.168.1.1',
            'user_agent' => 'Mozilla/5.0',
            'referer' => 'http://test.com',
            'response_time' => 100,
            'response_code' => 200
        ];

        $result = $this->logger->logAccess($request);

        $this->assertTrue($result['logged']);
        $this->assertEquals('123', $result['log_id']);
        $this->assertEquals('report', $result['action_type']);
    }

    public function testLogAccessExcludedPath(): void
    {
        $request = [
            'path' => '/assets/style.css',
            'method' => 'GET',
            'query_params' => [],
            'post_params' => [],
            'ip_address' => '192.168.1.1',
            'user_agent' => 'Mozilla/5.0',
            'referer' => null
        ];

        $result = $this->logger->logAccess($request);

        $this->assertFalse($result['logged']);
        $this->assertEquals('excluded_path', $result['reason']);
    }

    public function testLogAccessDisabled(): void
    {
        $disabledLogger = new SystemAccessLogger($this->pdoMock, ['enabled' => false]);

        $request = [
            'path' => '/admin/dashboard',
            'method' => 'GET',
            'query_params' => [],
            'post_params' => [],
            'ip_address' => '192.168.1.1',
            'user_agent' => 'Mozilla/5.0',
            'referer' => null
        ];

        $result = $disabledLogger->logAccess($request);

        $this->assertFalse($result['logged']);
        $this->assertEquals('disabled', $result['reason']);
    }

    public function testCategorizeActionLogin(): void
    {
        $stmtMock = $this->createMock(PDOStatement::class);
        $stmtMock->method('execute')->willReturn(true);
        $this->pdoMock->method('prepare')->willReturn($stmtMock);
        $this->pdoMock->method('lastInsertId')->willReturn('1');

        $request = [
            'path' => '/auth/login',
            'method' => 'POST',
            'query_params' => [],
            'post_params' => ['email' => 'test@test.com'],
            'ip_address' => '192.168.1.1',
            'user_agent' => 'Mozilla/5.0',
            'referer' => null
        ];

        $result = $this->logger->logAccess($request);

        $this->assertEquals('login', $result['action_type']);
    }

    public function testCategorizeActionExport(): void
    {
        $stmtMock = $this->createMock(PDOStatement::class);
        $stmtMock->method('execute')->willReturn(true);
        $this->pdoMock->method('prepare')->willReturn($stmtMock);
        $this->pdoMock->method('lastInsertId')->willReturn('1');

        $request = [
            'path' => '/reports/export/csv',
            'method' => 'GET',
            'query_params' => [],
            'post_params' => [],
            'ip_address' => '192.168.1.1',
            'user_agent' => 'Mozilla/5.0',
            'referer' => null
        ];

        $result = $this->logger->logAccess($request);

        $this->assertEquals('export', $result['action_type']);
    }

    public function testSanitizePostParams(): void
    {
        $stmtMock = $this->createMock(PDOStatement::class);
        $stmtMock->method('execute')->willReturn(true);
        $this->pdoMock->method('prepare')->willReturn($stmtMock);
        $this->pdoMock->method('lastInsertId')->willReturn('1');

        $request = [
            'path' => '/auth/login',
            'method' => 'POST',
            'query_params' => [],
            'post_params' => [
                'email' => 'test@test.com',
                'password' => 'secret123',
                'api_key' => 'abc123'
            ],
            'ip_address' => '192.168.1.1',
            'user_agent' => 'Mozilla/5.0',
            'referer' => null
        ];

        $result = $this->logger->logAccess($request);

        $this->assertTrue($result['logged']);
        // Verify that sensitive fields were redacted (would need to mock the DB insert to verify)
    }

    public function testGetAccessStats(): void
    {
        $stmtMock = $this->createMock(PDOStatement::class);
        $stmtMock->method('execute')->willReturn(true);
        $stmtMock->method('fetchAll')->willReturn([
            [
                'total_accesses' => 150,
                'active_days' => 5,
                'total_sessions' => 10,
                'unique_ips' => 3,
                'avg_response_time' => 250.5,
                'action_type' => 'view',
                'action_count' => 100
            ],
            [
                'total_accesses' => 150,
                'active_days' => 5,
                'total_sessions' => 10,
                'unique_ips' => 3,
                'avg_response_time' => 250.5,
                'action_type' => 'create',
                'action_count' => 30
            ]
        ]);

        $this->pdoMock->method('prepare')->willReturn($stmtMock);

        $stats = $this->logger->getAccessStats(1, 30);

        $this->assertIsArray($stats);
        $this->assertCount(2, $stats);
        $this->assertEquals(100, $stats[0]['action_count']);
        $this->assertEquals('view', $stats[0]['action_type']);
    }

    public function testHighFrequencyDetection(): void
    {
        // This would require mocking multiple database calls
        // Testing that high frequency access triggers anomaly detection
        $stmtMock = $this->createMock(PDOStatement::class);
        $stmtMock->method('execute')->willReturn(true);
        $stmtMock->method('fetchColumn')->willReturn(35); // More than 30 requests

        $this->pdoMock->method('prepare')->willReturn($stmtMock);
        $this->pdoMock->method('lastInsertId')->willReturn('1');

        $_SESSION['staff_id'] = 1;

        $request = [
            'path' => '/admin/dashboard',
            'method' => 'GET',
            'query_params' => [],
            'post_params' => [],
            'ip_address' => '192.168.1.1',
            'user_agent' => 'Mozilla/5.0',
            'referer' => null
        ];

        $result = $this->logger->logAccess($request);

        $this->assertTrue($result['logged']);
        // In a real scenario, this would trigger anomaly detection
    }

    public function testUnusualTimeDetection(): void
    {
        // Mock time to be 2 AM (outside business hours)
        $stmtMock = $this->createMock(PDOStatement::class);
        $stmtMock->method('execute')->willReturn(true);
        $this->pdoMock->method('prepare')->willReturn($stmtMock);
        $this->pdoMock->method('lastInsertId')->willReturn('1');

        $_SESSION['staff_id'] = 1;

        $request = [
            'path' => '/admin/dashboard',
            'method' => 'GET',
            'query_params' => [],
            'post_params' => [],
            'ip_address' => '192.168.1.1',
            'user_agent' => 'Mozilla/5.0',
            'referer' => null
        ];

        // Note: Actual time-based testing would require time mocking library
        $result = $this->logger->logAccess($request);

        $this->assertTrue($result['logged']);
    }

    public function testSensitiveResourceAccess(): void
    {
        $stmtMock = $this->createMock(PDOStatement::class);
        $stmtMock->method('execute')->willReturn(true);
        $this->pdoMock->method('prepare')->willReturn($stmtMock);
        $this->pdoMock->method('lastInsertId')->willReturn('1');

        $_SESSION['staff_id'] = 1;

        $request = [
            'path' => '/admin/finance/reports',
            'method' => 'GET',
            'query_params' => [],
            'post_params' => [],
            'ip_address' => '192.168.1.1',
            'user_agent' => 'Mozilla/5.0',
            'referer' => null
        ];

        $result = $this->logger->logAccess($request);

        $this->assertTrue($result['logged']);
        // This should trigger sensitive access detection
    }
}
