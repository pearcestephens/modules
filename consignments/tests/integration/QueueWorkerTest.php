<?php
/**
 * Queue Worker Integration Tests
 *
 * Tests concurrent execution and job processing.
 *
 * @package Consignments\Tests\Integration
 */

declare(strict_types=1);

namespace Consignments\Tests\Integration;

use PHPUnit\Framework\TestCase;

class QueueWorkerTest extends TestCase
{
    private \PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new \PDO(
            'mysql:host=127.0.0.1;dbname=test_consignments',
            'test_user',
            'test_pass'
        );
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
    }

    public function testWorkerClaimsJobsAtomically(): void
    {
        // Create 10 test jobs
        for ($i = 0; $i < 10; $i++) {
            $this->pdo->exec("
                INSERT INTO queue_jobs (job_type, payload, status, created_at)
                VALUES ('test.concurrent', '{\"id\": $i}', 'pending', NOW())
            ");
        }

        // Simulate concurrent workers using FOR UPDATE SKIP LOCKED
        $claimed1 = $this->claimJobs('worker1', 5);
        $claimed2 = $this->claimJobs('worker2', 5);

        // Verify no overlapping claims
        $intersection = array_intersect($claimed1, $claimed2);
        $this->assertEmpty($intersection, 'Workers claimed same jobs');

        // Verify total claimed
        $this->assertCount(5, $claimed1);
        $this->assertCount(5, $claimed2);
    }

    public function testWorkerHandlesJobFailureWithRetry(): void
    {
        // Create a job that will fail
        $this->pdo->exec("
            INSERT INTO queue_jobs (job_type, payload, status, attempts, created_at)
            VALUES ('test.fail', '{\"fail\": true}', 'pending', 0, NOW())
        ");
        $jobId = $this->pdo->lastInsertId();

        // Process job (will fail)
        $this->processJob($jobId, false);

        // Verify retry scheduled
        $stmt = $this->pdo->prepare("
            SELECT status, attempts, next_attempt_at
            FROM queue_jobs
            WHERE id = ?
        ");
        $stmt->execute([$jobId]);
        $job = $stmt->fetch(\PDO::FETCH_ASSOC);

        $this->assertEquals('pending', $job['status']);
        $this->assertEquals(1, $job['attempts']);
        $this->assertNotNull($job['next_attempt_at']);
    }

    public function testWorkerMovesJobToDLQAfterMaxAttempts(): void
    {
        // Create a job with max attempts
        $this->pdo->exec("
            INSERT INTO queue_jobs (job_type, payload, status, attempts, created_at)
            VALUES ('test.maxretries', '{\"fail\": true}', 'pending', 3, NOW())
        ");
        $jobId = $this->pdo->lastInsertId();

        // Process job (will fail)
        $this->processJob($jobId, false);

        // Verify moved to DLQ
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM queue_jobs_dlq WHERE id = ?");
        $stmt->execute([$jobId]);
        $this->assertEquals(1, $stmt->fetchColumn());

        // Verify removed from queue_jobs
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM queue_jobs WHERE id = ?");
        $stmt->execute([$jobId]);
        $this->assertEquals(0, $stmt->fetchColumn());
    }

    public function testWorkerUpdatesHeartbeat(): void
    {
        $this->pdo->exec("
            INSERT INTO queue_jobs (job_type, payload, status, created_at)
            VALUES ('test.heartbeat', '{}', 'pending', NOW())
        ");
        $jobId = $this->pdo->lastInsertId();

        // Claim job
        $this->pdo->exec("
            UPDATE queue_jobs
            SET status = 'processing', heartbeat_at = NOW()
            WHERE id = $jobId
        ");

        sleep(2);

        // Update heartbeat
        $this->pdo->exec("
            UPDATE queue_jobs
            SET heartbeat_at = NOW()
            WHERE id = $jobId
        ");

        // Verify heartbeat updated
        $stmt = $this->pdo->prepare("
            SELECT TIMESTAMPDIFF(SECOND, created_at, heartbeat_at) as elapsed
            FROM queue_jobs
            WHERE id = ?
        ");
        $stmt->execute([$jobId]);
        $elapsed = $stmt->fetchColumn();

        $this->assertGreaterThanOrEqual(2, $elapsed);
    }

    public function testWorkerResetsStuckJobs(): void
    {
        // Create a stuck job (old heartbeat)
        $this->pdo->exec("
            INSERT INTO queue_jobs (job_type, payload, status, heartbeat_at, created_at)
            VALUES ('test.stuck', '{}', 'processing', DATE_SUB(NOW(), INTERVAL 10 MINUTE), NOW())
        ");
        $jobId = $this->pdo->lastInsertId();

        // Reset stuck jobs (heartbeat older than 5 minutes)
        $this->pdo->exec("
            UPDATE queue_jobs
            SET status = 'pending', heartbeat_at = NULL
            WHERE status = 'processing'
              AND heartbeat_at < DATE_SUB(NOW(), INTERVAL 5 MINUTE)
        ");

        // Verify job reset
        $stmt = $this->pdo->prepare("SELECT status FROM queue_jobs WHERE id = ?");
        $stmt->execute([$jobId]);
        $this->assertEquals('pending', $stmt->fetchColumn());
    }

    public function testWorkerProcessesJobsInPriorityOrder(): void
    {
        // Create jobs with different priorities
        $this->pdo->exec("
            INSERT INTO queue_jobs (job_type, payload, status, priority, created_at)
            VALUES
                ('test.priority', '{\"id\": 1}', 'pending', 1, NOW()),
                ('test.priority', '{\"id\": 2}', 'pending', 10, NOW()),
                ('test.priority', '{\"id\": 3}', 'pending', 5, NOW())
        ");

        // Claim jobs (should get highest priority first)
        $stmt = $this->pdo->query("
            SELECT id, priority, payload
            FROM queue_jobs
            WHERE status = 'pending'
            ORDER BY priority DESC, created_at ASC
            LIMIT 3
        ");
        $jobs = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $this->assertEquals(10, $jobs[0]['priority']);
        $this->assertEquals(5, $jobs[1]['priority']);
        $this->assertEquals(1, $jobs[2]['priority']);
    }

    private function claimJobs(string $workerId, int $limit): array
    {
        $stmt = $this->pdo->prepare("
            SELECT id
            FROM queue_jobs
            WHERE status = 'pending'
            ORDER BY priority DESC, created_at ASC
            LIMIT ?
            FOR UPDATE SKIP LOCKED
        ");
        $stmt->execute([$limit]);
        $jobIds = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        if (!empty($jobIds)) {
            $placeholders = implode(',', array_fill(0, count($jobIds), '?'));
            $updateStmt = $this->pdo->prepare("
                UPDATE queue_jobs
                SET status = 'processing', worker_id = ?, heartbeat_at = NOW()
                WHERE id IN ($placeholders)
            ");
            $updateStmt->execute(array_merge([$workerId], $jobIds));
        }

        return $jobIds;
    }

    private function processJob(int $jobId, bool $success): void
    {
        if ($success) {
            $this->pdo->prepare("
                UPDATE queue_jobs
                SET status = 'completed', completed_at = NOW()
                WHERE id = ?
            ")->execute([$jobId]);
        } else {
            // Get current attempts
            $stmt = $this->pdo->prepare("SELECT attempts FROM queue_jobs WHERE id = ?");
            $stmt->execute([$jobId]);
            $attempts = $stmt->fetchColumn();

            if ($attempts >= 3) {
                // Move to DLQ
                $this->pdo->prepare("
                    INSERT INTO queue_jobs_dlq (id, job_type, payload, priority, attempts, error_message, failed_at)
                    SELECT id, job_type, payload, priority, attempts, 'Max retries exceeded', NOW()
                    FROM queue_jobs
                    WHERE id = ?
                ")->execute([$jobId]);

                $this->pdo->prepare("DELETE FROM queue_jobs WHERE id = ?")->execute([$jobId]);
            } else {
                // Schedule retry
                $backoff = 200 * pow(2, $attempts); // Exponential backoff
                $this->pdo->prepare("
                    UPDATE queue_jobs
                    SET status = 'pending',
                        attempts = attempts + 1,
                        next_attempt_at = DATE_ADD(NOW(), INTERVAL ? MILLISECOND),
                        error_message = 'Job failed'
                    WHERE id = ?
                ")->execute([$backoff, $jobId]);
            }
        }
    }

    protected function tearDown(): void
    {
        $this->pdo->exec("DELETE FROM queue_jobs WHERE job_type LIKE 'test.%'");
        $this->pdo->exec("DELETE FROM queue_jobs_dlq WHERE job_type LIKE 'test.%'");
    }
}
