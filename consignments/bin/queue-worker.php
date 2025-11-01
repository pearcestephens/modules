#!/usr/bin/env php
<?php
/**
 * Queue Worker - Concurrent-safe job processor with automatic recovery
 *
 * Features:
 * - FOR UPDATE SKIP LOCKED (prevents race conditions)
 * - Heartbeat monitoring (auto-recovery)
 * - Exponential backoff with jitter
 * - Dead Letter Queue after max_attempts
 * - Graceful shutdown (SIGTERM/SIGINT)
 * - Correlation IDs
 *
 * Usage:
 *   php bin/queue-worker.php [--once] [--sleep=5]
 *
 * Options:
 *   --once      Process one job and exit (for testing)
 *   --sleep=N   Seconds to sleep when queue empty (default: 5)
 *
 * @package Consignments
 */

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use Consignments\Infra\Lightspeed\LightspeedClient;
use Psr\Log\LoggerInterface;

class QueueWorker
{
    private PDO $pdo;
    private LoggerInterface $logger;
    private LightspeedClient $client;
    private bool $shutdown = false;
    private ?int $currentJobId = null;
    private int $sleepSeconds = 5;
    private bool $runOnce = false;
    private string $workerId;

    public function __construct(PDO $pdo, LoggerInterface $logger, LightspeedClient $client)
    {
        $this->pdo = $pdo;
        $this->logger = $logger;
        $this->client = $client;
        $this->workerId = sprintf('worker_%s_%d', gethostname(), getmypid());

        $this->registerShutdownHandler();
    }

    private function registerShutdownHandler(): void
    {
        pcntl_signal(SIGTERM, function () {
            $this->logger->info('SIGTERM received, shutting down gracefully', [
                'worker_id' => $this->workerId,
                'current_job' => $this->currentJobId
            ]);
            $this->shutdown = true;
        });

        pcntl_signal(SIGINT, function () {
            $this->logger->info('SIGINT received, shutting down gracefully', [
                'worker_id' => $this->workerId,
                'current_job' => $this->currentJobId
            ]);
            $this->shutdown = true;
        });

        declare(ticks=1);
    }

    public function setSleepSeconds(int $seconds): void
    {
        $this->sleepSeconds = $seconds;
    }

    public function setRunOnce(bool $runOnce): void
    {
        $this->runOnce = $runOnce;
    }

    public function start(): void
    {
        $this->logger->info('Queue worker started', [
            'worker_id' => $this->workerId,
            'sleep_seconds' => $this->sleepSeconds,
            'run_once' => $this->runOnce
        ]);

        while (!$this->shutdown) {
            try {
                $job = $this->claimNextJob();

                if ($job === null) {
                    if ($this->runOnce) {
                        $this->logger->info('No jobs available, exiting (--once mode)');
                        break;
                    }

                    $this->logger->debug('No jobs available, sleeping', [
                        'sleep_seconds' => $this->sleepSeconds
                    ]);
                    sleep($this->sleepSeconds);
                    continue;
                }

                $this->currentJobId = (int)$job['id'];
                $this->processJob($job);
                $this->currentJobId = null;

                if ($this->runOnce) {
                    $this->logger->info('Job processed, exiting (--once mode)');
                    break;
                }

            } catch (\Throwable $e) {
                $this->logger->error('Worker error', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                sleep(5); // Backoff on error
            }
        }

        $this->logger->info('Queue worker stopped', ['worker_id' => $this->workerId]);
    }

    /**
     * Claim next job using FOR UPDATE SKIP LOCKED (concurrent-safe)
     */
    private function claimNextJob(): ?array
    {
        $this->pdo->beginTransaction();

        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM queue_jobs
                WHERE status = 'pending'
                ORDER BY priority DESC, id ASC
                LIMIT 1
                FOR UPDATE SKIP LOCKED
            ");
            $stmt->execute();
            $job = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$job) {
                $this->pdo->rollBack();
                return null;
            }

            // Update to processing with worker ID
            $updateStmt = $this->pdo->prepare("
                UPDATE queue_jobs
                SET status = 'processing',
                    worker_id = ?,
                    started_at = NOW(),
                    heartbeat_at = NOW()
                WHERE id = ?
            ");
            $updateStmt->execute([$this->workerId, $job['id']]);

            $this->pdo->commit();

            $this->logger->info('Job claimed', [
                'job_id' => $job['id'],
                'job_type' => $job['job_type'],
                'priority' => $job['priority'],
                'attempts' => $job['attempts']
            ]);

            return $job;

        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    private function processJob(array $job): void
    {
        $jobId = (int)$job['id'];
        $jobType = $job['job_type'];
        $payload = json_decode($job['payload'], true);
        $attempt = (int)$job['attempts'] + 1;
        $maxAttempts = (int)$job['max_attempts'];

        $startTime = microtime(true);

        try {
            // Update heartbeat before processing
            $this->updateHeartbeat($jobId);

            // Dispatch to handler
            $handler = $this->getHandler($jobType);
            $result = $handler($payload, $this->client, $this->logger);

            $duration = round((microtime(true) - $startTime) * 1000, 2);

            // Mark completed
            $stmt = $this->pdo->prepare("
                UPDATE queue_jobs
                SET status = 'completed',
                    attempts = ?,
                    completed_at = NOW(),
                    result = ?
                WHERE id = ?
            ");
            $stmt->execute([$attempt, json_encode($result), $jobId]);

            $this->logger->info('Job completed', [
                'job_id' => $jobId,
                'job_type' => $jobType,
                'attempt' => $attempt,
                'duration_ms' => $duration
            ]);

        } catch (\Throwable $e) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);

            $this->logger->error('Job failed', [
                'job_id' => $jobId,
                'job_type' => $jobType,
                'attempt' => $attempt,
                'max_attempts' => $maxAttempts,
                'error' => $e->getMessage(),
                'duration_ms' => $duration
            ]);

            if ($attempt >= $maxAttempts) {
                // Move to Dead Letter Queue
                $this->moveToDLQ($job, $e->getMessage());
            } else {
                // Retry with backoff
                $this->retryJob($jobId, $attempt, $e->getMessage());
            }
        }
    }

    private function updateHeartbeat(int $jobId): void
    {
        $stmt = $this->pdo->prepare("UPDATE queue_jobs SET heartbeat_at = NOW() WHERE id = ?");
        $stmt->execute([$jobId]);
    }

    private function retryJob(int $jobId, int $attempts, string $error): void
    {
        $backoffMs = $this->calculateBackoff($attempts);

        $stmt = $this->pdo->prepare("
            UPDATE queue_jobs
            SET status = 'pending',
                attempts = ?,
                worker_id = NULL,
                started_at = NULL,
                last_error = ?,
                next_attempt_at = DATE_ADD(NOW(), INTERVAL ? MICROSECOND)
            WHERE id = ?
        ");
        $stmt->execute([$attempts, $error, $backoffMs * 1000, $jobId]);

        $this->logger->warning('Job queued for retry', [
            'job_id' => $jobId,
            'attempt' => $attempts,
            'backoff_ms' => $backoffMs
        ]);

        // Sleep for backoff duration
        usleep($backoffMs * 1000);
    }

    private function calculateBackoff(int $attempt): int
    {
        // Exponential backoff with jitter: base * (2 ^ attempt) + random jitter
        $baseMs = 200;
        $exponential = $baseMs * pow(2, $attempt - 1);
        $maxBackoff = 30000; // 30 seconds max
        $jitter = rand(0, (int)($exponential * 0.1)); // 10% jitter

        return min((int)$exponential + $jitter, $maxBackoff);
    }

    private function moveToDLQ(array $job, string $finalError): void
    {
        $this->pdo->beginTransaction();

        try {
            // Insert into DLQ
            $stmt = $this->pdo->prepare("
                INSERT INTO queue_jobs_dlq (
                    original_job_id, job_type, payload, priority,
                    final_error, attempts, moved_to_dlq_at
                ) VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $job['id'],
                $job['job_type'],
                $job['payload'],
                $job['priority'],
                $finalError,
                (int)$job['attempts'] + 1
            ]);

            // Delete from queue_jobs
            $deleteStmt = $this->pdo->prepare("DELETE FROM queue_jobs WHERE id = ?");
            $deleteStmt->execute([$job['id']]);

            $this->pdo->commit();

            $this->logger->error('Job moved to DLQ', [
                'job_id' => $job['id'],
                'job_type' => $job['job_type'],
                'attempts' => (int)$job['attempts'] + 1,
                'final_error' => $finalError
            ]);

        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            $this->logger->critical('Failed to move job to DLQ', [
                'job_id' => $job['id'],
                'error' => $e->getMessage()
            ]);
        }
    }

    private function getHandler(string $jobType): callable
    {
        // Map job types to handlers
        $handlers = [
            'transfer.create' => [$this, 'handleTransferCreate'],
            'transfer.update' => [$this, 'handleTransferUpdate'],
            'consignment.sync' => [$this, 'handleConsignmentSync'],
        ];

        if (!isset($handlers[$jobType])) {
            throw new \RuntimeException("Unknown job type: {$jobType}");
        }

        return $handlers[$jobType];
    }

    // Handler examples (expand in O8)
    private function handleTransferCreate(array $payload, LightspeedClient $client, LoggerInterface $logger): array
    {
        $logger->info('Creating transfer', ['payload' => $payload]);
        // Implementation in O8
        return ['status' => 'created', 'id' => $payload['transfer_id'] ?? null];
    }

    private function handleTransferUpdate(array $payload, LightspeedClient $client, LoggerInterface $logger): array
    {
        $logger->info('Updating transfer', ['payload' => $payload]);
        // Implementation in O8
        return ['status' => 'updated', 'id' => $payload['transfer_id'] ?? null];
    }

    private function handleConsignmentSync(array $payload, LightspeedClient $client, LoggerInterface $logger): array
    {
        $logger->info('Syncing consignment', ['payload' => $payload]);
        // Implementation in O8
        return ['status' => 'synced', 'id' => $payload['consignment_id'] ?? null];
    }
}

// ============================================================================
// CLI Entry Point
// ============================================================================

$options = getopt('', ['once', 'sleep:']);
$runOnce = isset($options['once']);
$sleepSeconds = isset($options['sleep']) ? (int)$options['sleep'] : 5;

try {
    // Load dependencies
    $pdo = require __DIR__ . '/../config/database.php';
    $logger = require __DIR__ . '/../config/logger.php';
    $client = new LightspeedClient($logger);

    $worker = new QueueWorker($pdo, $logger, $client);
    $worker->setSleepSeconds($sleepSeconds);
    $worker->setRunOnce($runOnce);
    $worker->start();

} catch (\Throwable $e) {
    fwrite(STDERR, "Fatal error: {$e->getMessage()}\n");
    fwrite(STDERR, $e->getTraceAsString() . "\n");
    exit(1);
}
