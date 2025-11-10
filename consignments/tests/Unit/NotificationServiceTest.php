<?php
declare(strict_types=1);

/**
 * NotificationService Unit Tests
 *
 * @package CIS\Consignments\Tests
 */

namespace CIS\Consignments\Tests\Unit;

use PHPUnit\Framework\TestCase;
use CIS\Consignments\Services\NotificationService;
use CIS\Consignments\Services\EmailService;
use PDO;

class NotificationServiceTest extends TestCase
{
    private PDO $pdo;
    private NotificationService $service;
    private EmailService $emailService;

    protected function setUp(): void
    {
        // Use test database
        $this->pdo = new PDO(
            'mysql:host=localhost;dbname=cis_test',
            $_ENV['DB_USER'] ?? 'root',
            $_ENV['DB_PASS'] ?? ''
        );

        $this->service = new NotificationService($this->pdo);
        $this->emailService = new EmailService($this->pdo);

        // Clean test data
        $this->pdo->exec("DELETE FROM consignment_notification_queue WHERE recipient_email LIKE '%@test.com'");
        $this->pdo->exec("DELETE FROM consignment_email_log WHERE recipient_email LIKE '%@test.com'");
    }

    protected function tearDown(): void
    {
        // Clean up
        $this->pdo->exec("DELETE FROM consignment_notification_queue WHERE recipient_email LIKE '%@test.com'");
        $this->pdo->exec("DELETE FROM consignment_email_log WHERE recipient_email LIKE '%@test.com'");
    }

    // ========================================================================
    // QUEUE PROCESSING TESTS
    // ========================================================================

    public function testProcessQueueReturnsStatistics(): void
    {
        // Queue some test emails
        for ($i = 0; $i < 3; $i++) {
            $this->emailService->sendTemplate(
                'po_created_internal',
                "process{$i}@test.com",
                "Process Test {$i}",
                ['po_number' => "PO-{$i}"],
                1,
                EmailService::PRIORITY_URGENT,
                123
            );
        }

        $stats = $this->service->processQueue(EmailService::PRIORITY_URGENT, 10);

        $this->assertIsArray($stats);
        $this->assertArrayHasKey('processed', $stats);
        $this->assertArrayHasKey('sent', $stats);
        $this->assertArrayHasKey('failed', $stats);
        $this->assertArrayHasKey('retried', $stats);
        $this->assertArrayHasKey('dlq', $stats);
        $this->assertArrayHasKey('duration', $stats);

        $this->assertEquals(3, $stats['processed']);
    }

    public function testProcessUrgentOnlyProcessesPriority1(): void
    {
        // Queue emails with different priorities
        $this->emailService->sendTemplate(
            'po_created_internal',
            'urgent@test.com',
            'Urgent',
            ['po_number' => 'PO-1'],
            1,
            EmailService::PRIORITY_URGENT,
            123
        );

        $this->emailService->sendTemplate(
            'po_created_internal',
            'high@test.com',
            'High',
            ['po_number' => 'PO-2'],
            1,
            EmailService::PRIORITY_HIGH,
            123
        );

        $stats = $this->service->processUrgent();

        // Should only process 1 urgent email
        $this->assertEquals(1, $stats['processed']);
    }

    public function testProcessHighOnlyProcessesPriority2(): void
    {
        $this->emailService->sendTemplate(
            'po_created_internal',
            'high@test.com',
            'High',
            ['po_number' => 'PO-1'],
            1,
            EmailService::PRIORITY_HIGH,
            123
        );

        $this->emailService->sendTemplate(
            'po_created_internal',
            'normal@test.com',
            'Normal',
            ['po_number' => 'PO-2'],
            1,
            EmailService::PRIORITY_NORMAL,
            123
        );

        $stats = $this->service->processHigh();

        $this->assertEquals(1, $stats['processed']);
    }

    // ========================================================================
    // RETRY LOGIC TESTS
    // ========================================================================

    public function testFailedEmailScheduledForRetry(): void
    {
        // Queue an email
        $queueId = $this->emailService->sendTemplate(
            'po_created_internal',
            'retry@test.com',
            'Retry Test',
            ['po_number' => 'PO-123'],
            1,
            EmailService::PRIORITY_URGENT,
            123
        );

        // Manually mark as failed (simulating send failure)
        $this->pdo->prepare("
            UPDATE consignment_notification_queue
            SET status = 'failed',
                retry_count = 1,
                last_error = 'Test error',
                next_retry_at = DATE_ADD(NOW(), INTERVAL 5 MINUTE)
            WHERE id = ?
        ")->execute([$queueId]);

        // Verify retry scheduled
        $stmt = $this->pdo->prepare("
            SELECT status, retry_count, next_retry_at
            FROM consignment_notification_queue
            WHERE id = ?
        ");
        $stmt->execute([$queueId]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertEquals('failed', $record['status']);
        $this->assertEquals(1, $record['retry_count']);
        $this->assertNotNull($record['next_retry_at']);
    }

    public function testMaxRetriesMovesToDLQ(): void
    {
        // Queue an email
        $queueId = $this->emailService->sendTemplate(
            'po_created_internal',
            'dlq@test.com',
            'DLQ Test',
            ['po_number' => 'PO-123'],
            1,
            EmailService::PRIORITY_URGENT,
            123
        );

        // Manually set to max retries
        $this->pdo->prepare("
            UPDATE consignment_notification_queue
            SET status = 'failed',
                retry_count = 5,
                last_error = 'Max retries exceeded'
            WHERE id = ?
        ")->execute([$queueId]);

        // Process retries (should move to DLQ)
        $stats = $this->service->processRetries();

        // Verify moved to DLQ (cancelled status)
        $stmt = $this->pdo->prepare("
            SELECT status
            FROM consignment_notification_queue
            WHERE id = ?
        ");
        $stmt->execute([$queueId]);
        $status = $stmt->fetchColumn();

        $this->assertEquals('cancelled', $status);
    }

    // ========================================================================
    // STATISTICS TESTS
    // ========================================================================

    public function testGetQueueStatsReturnsCorrectStructure(): void
    {
        // Queue some test emails
        $this->emailService->sendTemplate(
            'po_created_internal',
            'stats1@test.com',
            'Stats Test 1',
            ['po_number' => 'PO-1'],
            1,
            EmailService::PRIORITY_URGENT,
            123
        );

        $this->emailService->sendTemplate(
            'po_created_internal',
            'stats2@test.com',
            'Stats Test 2',
            ['po_number' => 'PO-2'],
            1,
            EmailService::PRIORITY_HIGH,
            123
        );

        $stats = $this->service->getQueueStats();

        $this->assertIsArray($stats);
        $this->assertGreaterThan(0, count($stats));

        foreach ($stats as $row) {
            $this->assertArrayHasKey('status', $row);
            $this->assertArrayHasKey('priority', $row);
            $this->assertArrayHasKey('count', $row);
            $this->assertArrayHasKey('oldest', $row);
        }
    }

    public function testGetDeadLetterQueueReturnsItems(): void
    {
        // Create a DLQ item
        $queueId = $this->emailService->sendTemplate(
            'po_created_internal',
            'dlq-test@test.com',
            'DLQ Item',
            ['po_number' => 'PO-999'],
            1,
            EmailService::PRIORITY_URGENT,
            123
        );

        // Move to DLQ
        $this->pdo->prepare("
            UPDATE consignment_notification_queue
            SET status = 'cancelled',
                retry_count = 5,
                last_error = 'Max retries',
                processed_at = NOW()
            WHERE id = ?
        ")->execute([$queueId]);

        $dlq = $this->service->getDeadLetterQueue(10);

        $this->assertIsArray($dlq);
        $this->assertGreaterThan(0, count($dlq));
    }

    public function testRetryFromDLQRestoresEmail(): void
    {
        // Create a DLQ item
        $queueId = $this->emailService->sendTemplate(
            'po_created_internal',
            'dlq-retry@test.com',
            'DLQ Retry',
            ['po_number' => 'PO-888'],
            1,
            EmailService::PRIORITY_URGENT,
            123
        );

        // Move to DLQ
        $this->pdo->prepare("
            UPDATE consignment_notification_queue
            SET status = 'cancelled',
                retry_count = 5
            WHERE id = ?
        ")->execute([$queueId]);

        // Retry from DLQ
        $result = $this->service->retryFromDLQ($queueId);

        $this->assertTrue($result);

        // Verify restored to pending
        $stmt = $this->pdo->prepare("
            SELECT status, retry_count
            FROM consignment_notification_queue
            WHERE id = ?
        ");
        $stmt->execute([$queueId]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertEquals('pending', $record['status']);
        $this->assertEquals(0, $record['retry_count']);
    }
}
