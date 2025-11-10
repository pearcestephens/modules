<?php
/**
 * Admin Dashboard Integration Tests
 *
 * @package Consignments\Tests\Integration\Admin
 */

declare(strict_types=1);

namespace Consignments\Tests\Integration\Admin;

use PHPUnit\Framework\TestCase;

class DashboardTest extends TestCase
{
    private \PDO $pdo;

    protected function setUp(): void
    {
        // Use test database connection
        $this->pdo = new \PDO(
            'mysql:host=127.0.0.1;dbname=test_consignments',
            'test_user',
            'test_pass'
        );
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        // Set up test session
        $_SESSION['userID'] = 1;
        $_SESSION['role'] = 'admin';
    }

    public function testSyncStatusEndpointReturnsValidJson(): void
    {
        // Create test data
        $this->pdo->exec("
            INSERT INTO queue_jobs (job_type, payload, status, created_at)
            VALUES
                ('test.job', '{}', 'pending', NOW()),
                ('test.job', '{}', 'processing', NOW()),
                ('test.job', '{}', 'failed', NOW())
        ");

        // Mock the API call (in real implementation, use cURL or Guzzle)
        ob_start();
        $_GET['test'] = true;
        require __DIR__ . '/../../../admin/api/sync-status.php';
        $output = ob_get_clean();

        $response = json_decode($output, true);

        $this->assertTrue($response['success']);
        $this->assertArrayHasKey('queue', $response);
        $this->assertArrayHasKey('webhooks', $response);
        $this->assertArrayHasKey('dlq', $response);
        $this->assertArrayHasKey('cursor', $response);
    }

    public function testDlqListEndpointReturnsJobs(): void
    {
        // Create test DLQ entries
        $this->pdo->exec("
            INSERT INTO queue_jobs_dlq (job_type, payload, failed_at, attempts, error_message)
            VALUES
                ('test.job', '{}', NOW(), 3, 'Test error'),
                ('test.job2', '{}', NOW(), 5, 'Another error')
        ");

        ob_start();
        require __DIR__ . '/../../../admin/api/dlq-list.php';
        $output = ob_get_clean();

        $response = json_decode($output, true);

        $this->assertTrue($response['success']);
        $this->assertArrayHasKey('jobs', $response);
        $this->assertGreaterThanOrEqual(2, $response['count']);
    }

    public function testRetryJobMovesJobFromDlqToQueue(): void
    {
        // Create DLQ entry
        $this->pdo->exec("
            INSERT INTO queue_jobs_dlq (job_type, payload, priority, failed_at, attempts, error_message)
            VALUES ('test.retry', '{\"test\": true}', 5, NOW(), 3, 'Test error')
        ");
        $dlqId = $this->pdo->lastInsertId();

        // Mock POST request
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $input = json_encode(['dlq_id' => $dlqId]);

        ob_start();
        // Simulate file_get_contents('php://input')
        file_put_contents('php://memory', $input);
        require __DIR__ . '/../../../admin/api/retry-job.php';
        $output = ob_get_clean();

        $response = json_decode($output, true);

        $this->assertTrue($response['success']);
        $this->assertArrayHasKey('new_job_id', $response);

        // Verify job moved to queue
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM queue_jobs WHERE id = ?");
        $stmt->execute([$response['new_job_id']]);
        $this->assertEquals(1, $stmt->fetchColumn());

        // Verify job removed from DLQ
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM queue_jobs_dlq WHERE id = ?");
        $stmt->execute([$dlqId]);
        $this->assertEquals(0, $stmt->fetchColumn());
    }

    public function testErrorLogEndpointReturnsRecentErrors(): void
    {
        // Create test errors
        $this->pdo->exec("
            INSERT INTO webhook_events (event_id, event_type, status, received_at)
            VALUES ('evt_123', 'test.event', 'failed', NOW())
        ");

        $this->pdo->exec("
            INSERT INTO queue_jobs (job_type, payload, status, error_message, created_at, updated_at)
            VALUES ('test.job', '{}', 'failed', 'Test error', NOW(), NOW())
        ");

        ob_start();
        require __DIR__ . '/../../../admin/api/error-log.php';
        $output = ob_get_clean();

        $response = json_decode($output, true);

        $this->assertTrue($response['success']);
        $this->assertArrayHasKey('errors', $response);
        $this->assertGreaterThanOrEqual(2, $response['count']);
    }

    public function testDashboardRequiresAuthentication(): void
    {
        // Unset session
        unset($_SESSION['userID']);
        unset($_SESSION['role']);

        ob_start();
        require __DIR__ . '/../../../admin/api/sync-status.php';
        $output = ob_get_clean();

        $response = json_decode($output, true);

        $this->assertArrayHasKey('error', $response);
        $this->assertEquals('Access denied', $response['error']);
    }

    public function testRetryJobRequiresPostMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';

        ob_start();
        require __DIR__ . '/../../../admin/api/retry-job.php';
        $output = ob_get_clean();

        $response = json_decode($output, true);

        $this->assertArrayHasKey('error', $response);
        $this->assertEquals('Method not allowed', $response['error']);
    }

    protected function tearDown(): void
    {
        // Clean up test data
        $this->pdo->exec("DELETE FROM queue_jobs WHERE job_type LIKE 'test.%'");
        $this->pdo->exec("DELETE FROM queue_jobs_dlq WHERE job_type LIKE 'test.%'");
        $this->pdo->exec("DELETE FROM webhook_events WHERE event_id LIKE 'evt_%'");
    }
}
