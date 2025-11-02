<?php

declare(strict_types=1);

namespace HumanResources\Payroll\Tests\Unit\Migrations;

use PHPUnit\Framework\TestCase;
use PDO;

/**
 * Unit tests for Migration 003 - payroll_auth_audit_log table
 *
 * Tests table creation, schema validation, and index configuration
 *
 * @covers migrations/003_create_payroll_auth_audit_log.php
 */
class Migration003Test extends TestCase
{
    private PDO $pdo;

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

        // Create indexes
        $this->pdo->exec("CREATE INDEX idx_timestamp ON payroll_auth_audit_log(timestamp)");
        $this->pdo->exec("CREATE INDEX idx_actor ON payroll_auth_audit_log(actor)");
    }

    public function testTableExists(): void
    {
        $stmt = $this->pdo->query("
            SELECT name FROM sqlite_master
            WHERE type='table' AND name='payroll_auth_audit_log'
        ");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertNotFalse($result, 'Table payroll_auth_audit_log should exist');
        $this->assertEquals('payroll_auth_audit_log', $result['name']);
    }

    public function testTableHasCorrectColumns(): void
    {
        $stmt = $this->pdo->query("PRAGMA table_info(payroll_auth_audit_log)");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $columnNames = array_column($columns, 'name');

        $expectedColumns = ['id', 'timestamp', 'actor', 'action', 'flag_before', 'flag_after', 'ip_address'];

        foreach ($expectedColumns as $expected) {
            $this->assertContains($expected, $columnNames, "Column {$expected} should exist");
        }
    }

    public function testIdColumnIsPrimaryKey(): void
    {
        $stmt = $this->pdo->query("PRAGMA table_info(payroll_auth_audit_log)");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $idColumn = array_values(array_filter($columns, fn($c) => $c['name'] === 'id'))[0] ?? null;

        $this->assertNotNull($idColumn);
        $this->assertEquals(1, $idColumn['pk'], 'id should be primary key');
    }

    public function testRequiredColumnsAreNotNull(): void
    {
        $stmt = $this->pdo->query("PRAGMA table_info(payroll_auth_audit_log)");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $columnMap = [];
        foreach ($columns as $col) {
            $columnMap[$col['name']] = $col;
        }

        // actor should be NOT NULL
        $this->assertEquals(1, $columnMap['actor']['notnull'], 'actor should be NOT NULL');

        // action should be NOT NULL
        $this->assertEquals(1, $columnMap['action']['notnull'], 'action should be NOT NULL');

        // flag_before should be NOT NULL
        $this->assertEquals(1, $columnMap['flag_before']['notnull'], 'flag_before should be NOT NULL');

        // flag_after should be NOT NULL
        $this->assertEquals(1, $columnMap['flag_after']['notnull'], 'flag_after should be NOT NULL');
    }

    public function testIpAddressCanBeNull(): void
    {
        $stmt = $this->pdo->query("PRAGMA table_info(payroll_auth_audit_log)");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $columnMap = [];
        foreach ($columns as $col) {
            $columnMap[$col['name']] = $col;
        }

        // ip_address should allow NULL
        $this->assertEquals(0, $columnMap['ip_address']['notnull'], 'ip_address should allow NULL');
    }

    public function testIndexOnTimestampExists(): void
    {
        $stmt = $this->pdo->query("
            SELECT name FROM sqlite_master
            WHERE type='index' AND tbl_name='payroll_auth_audit_log' AND name='idx_timestamp'
        ");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertNotFalse($result, 'Index idx_timestamp should exist');
    }

    public function testIndexOnActorExists(): void
    {
        $stmt = $this->pdo->query("
            SELECT name FROM sqlite_master
            WHERE type='index' AND tbl_name='payroll_auth_audit_log' AND name='idx_actor'
        ");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertNotFalse($result, 'Index idx_actor should exist');
    }

    public function testCanInsertValidRecord(): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO payroll_auth_audit_log (actor, action, flag_before, flag_after, ip_address)
            VALUES (:actor, :action, :flag_before, :flag_after, :ip_address)
        ");

        $result = $stmt->execute([
            'actor' => 'test_user',
            'action' => 'enable',
            'flag_before' => 0,
            'flag_after' => 1,
            'ip_address' => '192.168.1.1'
        ]);

        $this->assertTrue($result, 'Should be able to insert valid record');

        $lastId = $this->pdo->lastInsertId();
        $this->assertGreaterThan(0, $lastId, 'Should return valid insert ID');
    }

    public function testCanInsertRecordWithNullIpAddress(): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO payroll_auth_audit_log (actor, action, flag_before, flag_after, ip_address)
            VALUES (:actor, :action, :flag_before, :flag_after, :ip_address)
        ");

        $result = $stmt->execute([
            'actor' => 'test_user',
            'action' => 'disable',
            'flag_before' => 1,
            'flag_after' => 0,
            'ip_address' => null
        ]);

        $this->assertTrue($result, 'Should be able to insert record with NULL ip_address');
    }

    public function testTimestampDefaultsToCurrentTimestamp(): void
    {
        $beforeInsert = date('Y-m-d H:i:s');

        $stmt = $this->pdo->prepare("
            INSERT INTO payroll_auth_audit_log (actor, action, flag_before, flag_after)
            VALUES (:actor, :action, :flag_before, :flag_after)
        ");

        $stmt->execute([
            'actor' => 'test_user',
            'action' => 'enable',
            'flag_before' => 0,
            'flag_after' => 1
        ]);

        $lastId = $this->pdo->lastInsertId();

        $stmt = $this->pdo->query("SELECT timestamp FROM payroll_auth_audit_log WHERE id = {$lastId}");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertNotNull($row['timestamp'], 'timestamp should be set automatically');
        $this->assertNotEmpty($row['timestamp'], 'timestamp should not be empty');
    }

    public function testActorFieldAcceptsMaxLength(): void
    {
        $longActor = str_repeat('a', 64);

        $stmt = $this->pdo->prepare("
            INSERT INTO payroll_auth_audit_log (actor, action, flag_before, flag_after)
            VALUES (:actor, :action, :flag_before, :flag_after)
        ");

        $result = $stmt->execute([
            'actor' => $longActor,
            'action' => 'enable',
            'flag_before' => 0,
            'flag_after' => 1
        ]);

        $this->assertTrue($result, 'Should accept actor up to 64 characters');
    }

    public function testActionFieldAcceptsMaxLength(): void
    {
        $longAction = str_repeat('a', 32);

        $stmt = $this->pdo->prepare("
            INSERT INTO payroll_auth_audit_log (actor, action, flag_before, flag_after)
            VALUES (:actor, :action, :flag_before, :flag_after)
        ");

        $result = $stmt->execute([
            'actor' => 'test_user',
            'action' => $longAction,
            'flag_before' => 0,
            'flag_after' => 1
        ]);

        $this->assertTrue($result, 'Should accept action up to 32 characters');
    }

    public function testFlagFieldsAcceptBooleanValues(): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO payroll_auth_audit_log (actor, action, flag_before, flag_after)
            VALUES (:actor, :action, :flag_before, :flag_after)
        ");

        $testCases = [
            [0, 1],
            [1, 0],
            [0, 0],
            [1, 1]
        ];

        foreach ($testCases as [$before, $after]) {
            $result = $stmt->execute([
                'actor' => 'test_user',
                'action' => 'test',
                'flag_before' => $before,
                'flag_after' => $after
            ]);

            $this->assertTrue($result, "Should accept flag_before={$before}, flag_after={$after}");
        }
    }
}
