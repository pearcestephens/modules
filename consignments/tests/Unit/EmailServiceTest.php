<?php
declare(strict_types=1);

/**
 * EmailService Unit Tests
 *
 * @package CIS\Consignments\Tests
 */

namespace CIS\Consignments\Tests\Unit;

use PHPUnit\Framework\TestCase;
use CIS\Consignments\Services\EmailService;
use PDO;

class EmailServiceTest extends TestCase
{
    private PDO $pdo;
    private EmailService $service;

    protected function setUp(): void
    {
        // Use test database
        $this->pdo = new PDO(
            'mysql:host=localhost;dbname=cis_test',
            $_ENV['DB_USER'] ?? 'root',
            $_ENV['DB_PASS'] ?? ''
        );

        $this->service = new EmailService($this->pdo);

        // Clean test data
        $this->pdo->exec("DELETE FROM consignment_notification_queue WHERE recipient_email LIKE '%@test.com'");
        $this->pdo->exec("DELETE FROM consignment_email_log WHERE recipient_email LIKE '%@test.com'");
    }

    protected function tearDown(): void
    {
        // Clean up test data
        $this->pdo->exec("DELETE FROM consignment_notification_queue WHERE recipient_email LIKE '%@test.com'");
        $this->pdo->exec("DELETE FROM consignment_email_log WHERE recipient_email LIKE '%@test.com'");
    }

    // ========================================================================
    // TEMPLATE SENDING TESTS
    // ========================================================================

    public function testSendTemplateQueuesEmail(): void
    {
        $queueId = $this->service->sendTemplate(
            'po_created_internal',
            'test@test.com',
            'Test User',
            ['po_number' => 'PO-12345', 'supplier_name' => 'Test Supplier', 'total_value' => '$1,000.00'],
            1, // consignment_id
            EmailService::PRIORITY_NORMAL,
            123 // sent_by user_id
        );

        $this->assertIsInt($queueId);
        $this->assertGreaterThan(0, $queueId);

        // Verify queue record
        $stmt = $this->pdo->prepare("SELECT * FROM consignment_notification_queue WHERE id = ?");
        $stmt->execute([$queueId]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertIsArray($record);
        $this->assertEquals('po_created_internal', $record['template_key']);
        $this->assertEquals('test@test.com', $record['recipient_email']);
        $this->assertEquals('Test User', $record['recipient_name']);
        $this->assertEquals('pending', $record['status']);
        $this->assertEquals(EmailService::PRIORITY_NORMAL, $record['priority']);
        $this->assertEquals(EmailService::TYPE_INTERNAL, $record['email_type']);
    }

    public function testSendTemplateWithUrgentPriority(): void
    {
        $queueId = $this->service->sendTemplate(
            'discrepancy_alert',
            'urgent@test.com',
            'Urgent Test',
            ['consignment_number' => 'C-123'],
            1,
            EmailService::PRIORITY_URGENT,
            123
        );

        $stmt = $this->pdo->prepare("SELECT priority FROM consignment_notification_queue WHERE id = ?");
        $stmt->execute([$queueId]);
        $priority = $stmt->fetchColumn();

        $this->assertEquals(EmailService::PRIORITY_URGENT, $priority);
    }

    public function testSendTemplateWithInvalidTemplateThrowsException(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Template not found');

        $this->service->sendTemplate(
            'invalid_template_key',
            'test@test.com',
            'Test User',
            [],
            1,
            EmailService::PRIORITY_NORMAL,
            123
        );
    }

    // ========================================================================
    // CUSTOM EMAIL TESTS
    // ========================================================================

    public function testSendCustomQueuesEmail(): void
    {
        $queueId = $this->service->sendCustom(
            'custom@test.com',
            'Custom Test Subject',
            '<p>Custom HTML body</p>',
            'Custom text body',
            'Test Recipient',
            1,
            EmailService::TYPE_INTERNAL,
            EmailService::PRIORITY_NORMAL,
            123
        );

        $this->assertIsInt($queueId);
        $this->assertGreaterThan(0, $queueId);

        $stmt = $this->pdo->prepare("SELECT * FROM consignment_notification_queue WHERE id = ?");
        $stmt->execute([$queueId]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertEquals('Custom Test Subject', $record['subject']);
        $this->assertStringContainsString('Custom HTML body', $record['html_body']);
        $this->assertEquals('Custom text body', $record['text_body']);
    }

    // ========================================================================
    // IMMEDIATE SEND TESTS
    // ========================================================================

    public function testSendImmediateBypassesQueue(): void
    {
        // Note: This test requires SendGrid to be configured
        // In production, we'd mock SendGridService

        $result = $this->service->sendImmediate(
            'immediate@test.com',
            'Immediate Test',
            '<p>Immediate HTML</p>',
            'Immediate text',
            'Immediate Recipient'
        );

        // Should return boolean, not queue ID
        $this->assertIsBool($result);

        // Should not be in queue
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM consignment_notification_queue
            WHERE recipient_email = 'immediate@test.com'
        ");
        $stmt->execute();
        $count = $stmt->fetchColumn();

        $this->assertEquals(0, $count);
    }

    // ========================================================================
    // STATISTICS TESTS
    // ========================================================================

    public function testGetStatisticsReturnsCorrectStructure(): void
    {
        // Queue some test emails
        for ($i = 0; $i < 5; $i++) {
            $this->service->sendTemplate(
                'po_created_internal',
                "stats{$i}@test.com",
                "Stats Test {$i}",
                ['po_number' => "PO-{$i}"],
                1,
                EmailService::PRIORITY_NORMAL,
                123
            );
        }

        $stats = $this->service->getStatistics(7);

        $this->assertIsArray($stats);
        $this->assertArrayHasKey('total_sent', $stats);
        $this->assertArrayHasKey('total_failed', $stats);
        $this->assertArrayHasKey('by_priority', $stats);
        $this->assertArrayHasKey('by_template', $stats);
        $this->assertArrayHasKey('by_type', $stats);
    }

    // ========================================================================
    // TEMPLATE LOADING TESTS
    // ========================================================================

    public function testLoadTemplateReturnsCorrectStructure(): void
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('loadTemplate');
        $method->setAccessible(true);

        $template = $method->invoke($this->service, 'po_created_internal');

        $this->assertIsArray($template);
        $this->assertArrayHasKey('subject_template', $template);
        $this->assertArrayHasKey('html_template', $template);
        $this->assertArrayHasKey('text_template', $template);
        $this->assertArrayHasKey('email_type', $template);
    }

    // ========================================================================
    // TEMPLATE RENDERING TESTS
    // ========================================================================

    public function testRenderTemplateReplacesVariables(): void
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('renderTemplate');
        $method->setAccessible(true);

        $template = 'Hello {{name}}, your PO is {{po_number}}';
        $variables = ['name' => 'John', 'po_number' => 'PO-12345'];

        $rendered = $method->invoke($this->service, $template, $variables);

        $this->assertEquals('Hello John, your PO is PO-12345', $rendered);
    }

    public function testRenderTemplateHandlesMissingVariables(): void
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('renderTemplate');
        $method->setAccessible(true);

        $template = 'Hello {{name}}, your PO is {{po_number}}';
        $variables = ['name' => 'John']; // Missing po_number

        $rendered = $method->invoke($this->service, $template, $variables);

        $this->assertEquals('Hello John, your PO is {{po_number}}', $rendered);
    }

    // ========================================================================
    // PRIORITY TESTS
    // ========================================================================

    public function testPriorityConstantsHaveCorrectValues(): void
    {
        $this->assertEquals(1, EmailService::PRIORITY_URGENT);
        $this->assertEquals(2, EmailService::PRIORITY_HIGH);
        $this->assertEquals(3, EmailService::PRIORITY_NORMAL);
        $this->assertEquals(4, EmailService::PRIORITY_LOW);
    }

    // ========================================================================
    // TYPE TESTS
    // ========================================================================

    public function testTypeConstantsHaveCorrectValues(): void
    {
        $this->assertEquals('internal', EmailService::TYPE_INTERNAL);
        $this->assertEquals('supplier', EmailService::TYPE_SUPPLIER);
    }
}
