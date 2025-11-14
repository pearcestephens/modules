<?php

namespace FraudDetection\Tests\Integration;

use PHPUnit\Framework\TestCase;
use PDO;

class DatabaseIntegrationTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        // Connect to test database
        $this->pdo = new PDO(
            'mysql:host=' . getenv('DB_HOST') . ';dbname=' . getenv('DB_NAME'),
            getenv('DB_USER'),
            getenv('DB_PASS'),
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
    }

    public function testRequiredTablesExist(): void
    {
        $requiredTables = [
            'system_access_log',
            'staff_location_history',
            'badge_scans',
            'deputy_location_mapping',
            'webhook_log',
            'communication_events',
            'google_webhook_channels',
            'slack_channels',
            'fraud_analysis_queue',
            'access_anomalies'
        ];

        $stmt = $this->pdo->query("SHOW TABLES");
        $existingTables = $stmt->fetchAll(PDO::FETCH_COLUMN);

        foreach ($requiredTables as $table) {
            $this->assertContains(
                $table,
                $existingTables,
                "Required table '$table' does not exist"
            );
        }
    }

    public function testStaffLocationHistoryInsert(): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO staff_location_history
            (staff_id, outlet_id, outlet_name, confidence, source, recorded_at)
            VALUES (1, 1, 'Test Store', 0.95, 'badge_system', NOW())
        ");
        $result = $stmt->execute();

        $this->assertTrue($result);

        // Clean up
        $this->pdo->exec("DELETE FROM staff_location_history WHERE outlet_name = 'Test Store'");
    }

    public function testSystemAccessLogInsert(): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO system_access_log
            (staff_id, session_id, action_type, path, method, ip_address,
             user_agent, accessed_at)
            VALUES (1, 'test_session', 'view', '/test', 'GET', '127.0.0.1',
                    'Test Agent', NOW())
        ");
        $result = $stmt->execute();

        $this->assertTrue($result);

        // Clean up
        $this->pdo->exec("DELETE FROM system_access_log WHERE session_id = 'test_session'");
    }

    public function testWebhookLogInsert(): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO webhook_log
            (platform, payload, headers, received_at)
            VALUES ('slack', '{\"test\": true}', '{\"header\": \"value\"}', NOW())
        ");
        $result = $stmt->execute();

        $this->assertTrue($result);

        // Clean up
        $this->pdo->exec("DELETE FROM webhook_log WHERE payload = '{\"test\": true}'");
    }

    public function testFraudAnalysisQueueInsert(): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO fraud_analysis_queue
            (staff_id, trigger_source, trigger_data, priority, status, created_at)
            VALUES (1, 'test', '{\"test\": true}', 'medium', 'pending', NOW())
        ");
        $result = $stmt->execute();

        $this->assertTrue($result);

        $queueId = $this->pdo->lastInsertId();

        // Verify it can be queried
        $stmt = $this->pdo->prepare("
            SELECT * FROM fraud_analysis_queue WHERE id = :id
        ");
        $stmt->execute(['id' => $queueId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertEquals('test', $row['trigger_source']);
        $this->assertEquals('pending', $row['status']);

        // Clean up
        $this->pdo->exec("DELETE FROM fraud_analysis_queue WHERE id = $queueId");
    }

    public function testAccessAnomalyInsert(): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO access_anomalies
            (staff_id, anomaly_types, severity, log_entry, detected_at)
            VALUES (1, '[\"high_frequency\"]', 'medium', '{\"test\": true}', NOW())
        ");
        $result = $stmt->execute();

        $this->assertTrue($result);

        // Clean up
        $this->pdo->exec("DELETE FROM access_anomalies WHERE log_entry = '{\"test\": true}'");
    }

    public function testForeignKeyConstraints(): void
    {
        // Test that foreign key constraints are working
        $this->expectException(\PDOException::class);

        // Try to insert with non-existent staff_id
        $stmt = $this->pdo->prepare("
            INSERT INTO staff_location_history
            (staff_id, outlet_id, outlet_name, confidence, source, recorded_at)
            VALUES (999999, 1, 'Test', 0.5, 'test', NOW())
        ");
        $stmt->execute();
    }

    protected function tearDown(): void
    {
        // Clean up any test data
        $this->pdo = null;
    }
}
