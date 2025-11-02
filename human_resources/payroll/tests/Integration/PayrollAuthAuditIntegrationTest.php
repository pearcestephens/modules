<?php

declare(strict_types=1);

namespace HumanResources\Payroll\Tests\Integration;

use PHPUnit\Framework\TestCase;
use PDO;

require_once __DIR__ . '/../../services/PayrollAuthAuditService.php';

use HumanResources\Payroll\Services\PayrollAuthAuditService;

/**
 * Integration tests for PayrollAuthAuditService
 *
 * Tests complete workflows and database integration
 *
 * @covers HumanResources\Payroll\Services\PayrollAuthAuditService
 */
class PayrollAuthAuditIntegrationTest extends TestCase
{
    private PDO $pdo;
    private PayrollAuthAuditService $service;

    protected function setUp(): void
    {
        // Create in-memory SQLite database
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Create the audit log table
        $this->pdo->exec("
            CREATE TABLE payroll_auth_audit_log (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
                actor VARCHAR(64) NOT NULL,
                action VARCHAR(32) NOT NULL,
                flag_before TINYINT(1) NOT NULL,
                flag_after TINYINT(1) NOT NULL,
                ip_address VARCHAR(64)
            )
        ");

        $this->service = PayrollAuthAuditService::make($this->pdo);
    }

    public function testCompleteEnableWorkflow(): void
    {
        // Scenario: Admin enables authentication
        $this->service->recordToggle(
            actor: 'admin@vapeshed.co.nz',
            action: 'enable',
            flagBefore: false,
            flagAfter: true,
            ipAddress: '192.168.1.100'
        );

        // Verify the record was created
        $entries = $this->service->getRecentEntries(limit: 1);

        $this->assertCount(1, $entries);
        $this->assertEquals('admin@vapeshed.co.nz', $entries[0]['actor']);
        $this->assertEquals('enable', $entries[0]['action']);
        $this->assertEquals(0, $entries[0]['flag_before']);
        $this->assertEquals(1, $entries[0]['flag_after']);
        $this->assertEquals('192.168.1.100', $entries[0]['ip_address']);
    }

    public function testCompleteDisableWorkflow(): void
    {
        // Scenario: Emergency admin disables authentication
        $this->service->recordToggle(
            actor: 'emergency_admin',
            action: 'disable',
            flagBefore: true,
            flagAfter: false,
            ipAddress: '10.0.0.5'
        );

        // Verify the record
        $entries = $this->service->getRecentEntries(limit: 1);

        $this->assertCount(1, $entries);
        $this->assertEquals('emergency_admin', $entries[0]['actor']);
        $this->assertEquals('disable', $entries[0]['action']);
        $this->assertEquals(1, $entries[0]['flag_before']);
        $this->assertEquals(0, $entries[0]['flag_after']);
        $this->assertEquals('10.0.0.5', $entries[0]['ip_address']);
    }

    public function testMultipleToggleHistory(): void
    {
        // Scenario: Authentication toggled multiple times
        $toggles = [
            ['admin1', 'enable', false, true, '192.168.1.1'],
            ['admin2', 'disable', true, false, '192.168.1.2'],
            ['admin1', 'enable', false, true, '192.168.1.1'],
            ['admin3', 'disable', true, false, '192.168.1.3'],
        ];

        foreach ($toggles as [$actor, $action, $before, $after, $ip]) {
            $this->service->recordToggle($actor, $action, $before, $after, $ip);
        }

        // Get all entries
        $entries = $this->service->getRecentEntries(limit: 10);

        $this->assertCount(4, $entries);

        // Verify most recent first
        $this->assertEquals('admin3', $entries[0]['actor']);
        $this->assertEquals('admin2', $entries[1]['actor']);
        $this->assertEquals('admin1', $entries[2]['actor']);
        $this->assertEquals('admin1', $entries[3]['actor']);
    }

    public function testActorFilteringWithMultipleUsers(): void
    {
        // Scenario: Multiple admins toggle auth
        $this->service->recordToggle('alice', 'enable', false, true, '1.1.1.1');
        $this->service->recordToggle('bob', 'disable', true, false, '2.2.2.2');
        $this->service->recordToggle('alice', 'disable', true, false, '1.1.1.1');
        $this->service->recordToggle('charlie', 'enable', false, true, '3.3.3.3');
        $this->service->recordToggle('alice', 'enable', false, true, '1.1.1.1');

        // Get Alice's actions only
        $aliceEntries = $this->service->getEntriesByActor('alice', limit: 10);

        $this->assertCount(3, $aliceEntries);

        foreach ($aliceEntries as $entry) {
            $this->assertEquals('alice', $entry['actor']);
        }

        // Get Bob's actions only
        $bobEntries = $this->service->getEntriesByActor('bob', limit: 10);

        $this->assertCount(1, $bobEntries);
        $this->assertEquals('bob', $bobEntries[0]['actor']);
    }

    public function testLimitParameterWorks(): void
    {
        // Insert 10 records
        for ($i = 1; $i <= 10; $i++) {
            $this->service->recordToggle(
                actor: "user{$i}",
                action: $i % 2 === 0 ? 'enable' : 'disable',
                flagBefore: $i % 2 === 1,
                flagAfter: $i % 2 === 0,
                ipAddress: "192.168.1.{$i}"
            );
        }

        // Request only 5
        $entries = $this->service->getRecentEntries(limit: 5);

        $this->assertCount(5, $entries);

        // Verify most recent first
        $this->assertEquals('user10', $entries[0]['actor']);
        $this->assertEquals('user9', $entries[1]['actor']);
        $this->assertEquals('user8', $entries[2]['actor']);
        $this->assertEquals('user7', $entries[3]['actor']);
        $this->assertEquals('user6', $entries[4]['actor']);
    }

    public function testNullIpAddressHandling(): void
    {
        // Scenario: Toggle from CLI without IP
        $this->service->recordToggle(
            actor: 'cli_user',
            action: 'enable',
            flagBefore: false,
            flagAfter: true,
            ipAddress: null
        );

        $entries = $this->service->getRecentEntries(limit: 1);

        $this->assertCount(1, $entries);
        $this->assertNull($entries[0]['ip_address']);
    }

    public function testTimestampOrdering(): void
    {
        // Insert with slight delays to ensure timestamp differences
        $this->service->recordToggle('user1', 'enable', false, true, '1.1.1.1');
        usleep(1000); // 1ms delay
        $this->service->recordToggle('user2', 'disable', true, false, '2.2.2.2');
        usleep(1000);
        $this->service->recordToggle('user3', 'enable', false, true, '3.3.3.3');

        $entries = $this->service->getRecentEntries(limit: 3);

        // Most recent first
        $this->assertEquals('user3', $entries[0]['actor']);
        $this->assertEquals('user2', $entries[1]['actor']);
        $this->assertEquals('user1', $entries[2]['actor']);

        // Verify timestamps are in descending order
        $timestamp1 = strtotime($entries[0]['timestamp']);
        $timestamp2 = strtotime($entries[1]['timestamp']);
        $timestamp3 = strtotime($entries[2]['timestamp']);

        $this->assertGreaterThanOrEqual($timestamp2, $timestamp1);
        $this->assertGreaterThanOrEqual($timestamp3, $timestamp2);
    }

    public function testEmptyResultsWhenNoRecords(): void
    {
        // No records inserted
        $entries = $this->service->getRecentEntries(limit: 10);

        $this->assertIsArray($entries);
        $this->assertEmpty($entries);
    }

    public function testEmptyResultsWhenActorNotFound(): void
    {
        $this->service->recordToggle('alice', 'enable', false, true, '1.1.1.1');

        // Query for non-existent actor
        $entries = $this->service->getEntriesByActor('bob', limit: 10);

        $this->assertIsArray($entries);
        $this->assertEmpty($entries);
    }

    public function testFactoryMethodReturnsServiceInstance(): void
    {
        $service = PayrollAuthAuditService::make($this->pdo);

        $this->assertInstanceOf(PayrollAuthAuditService::class, $service);
    }

    public function testServiceCanBeReused(): void
    {
        // Use service multiple times
        $this->service->recordToggle('user1', 'enable', false, true, '1.1.1.1');
        $this->service->recordToggle('user2', 'disable', true, false, '2.2.2.2');

        $entries1 = $this->service->getRecentEntries(limit: 10);
        $entries2 = $this->service->getRecentEntries(limit: 10);

        $this->assertEquals($entries1, $entries2, 'Multiple calls should return same data');
    }

    public function testLargeDatasetPerformance(): void
    {
        $startTime = microtime(true);

        // Insert 100 records
        for ($i = 1; $i <= 100; $i++) {
            $this->service->recordToggle(
                actor: "user{$i}",
                action: $i % 2 === 0 ? 'enable' : 'disable',
                flagBefore: $i % 2 === 1,
                flagAfter: $i % 2 === 0,
                ipAddress: "192.168.1." . ($i % 256)
            );
        }

        $insertTime = microtime(true) - $startTime;

        // Query recent entries
        $queryStartTime = microtime(true);
        $entries = $this->service->getRecentEntries(limit: 50);
        $queryTime = microtime(true) - $queryStartTime;

        $this->assertCount(50, $entries);
        $this->assertLessThan(1.0, $insertTime, 'Inserts should complete in under 1 second');
        $this->assertLessThan(0.1, $queryTime, 'Queries should complete in under 100ms');
    }

    public function testSpecialCharactersInActor(): void
    {
        $specialActors = [
            'user@domain.com',
            'user-name_123',
            'ADMIN.USER',
            'user\'with\'quotes',
        ];

        foreach ($specialActors as $actor) {
            $this->service->recordToggle($actor, 'enable', false, true, '1.1.1.1');
        }

        $entries = $this->service->getRecentEntries(limit: 10);

        $this->assertCount(4, $entries);
    }

    public function testIpv6AddressSupport(): void
    {
        $ipv6Address = '2001:0db8:85a3:0000:0000:8a2e:0370:7334';

        $this->service->recordToggle(
            actor: 'user1',
            action: 'enable',
            flagBefore: false,
            flagAfter: true,
            ipAddress: $ipv6Address
        );

        $entries = $this->service->getRecentEntries(limit: 1);

        $this->assertCount(1, $entries);
        $this->assertEquals($ipv6Address, $entries[0]['ip_address']);
    }
}
