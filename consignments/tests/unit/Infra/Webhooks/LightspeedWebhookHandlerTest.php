<?php
/**
 * Webhook Handler Unit Tests
 *
 * Tests HMAC validation, replay protection, and error handling
 *
 * @package Consignments\Tests\Unit\Infra\Webhooks
 */

declare(strict_types=1);

namespace Consignments\Tests\Unit\Infra\Webhooks;

use PHPUnit\Framework\TestCase;
use Consignments\Infra\Webhooks\LightspeedWebhookHandler;
use Consignments\Infra\Webhooks\WebhookException;

class LightspeedWebhookHandlerTest extends TestCase
{
    private $pdo;
    private $handler;

    protected function setUp(): void
    {
        // Mock PDO
        $this->pdo = $this->createMock(\PDO::class);

        // Set test webhook secret
        putenv('LS_WEBHOOK_SECRET=test_secret_key_12345');

        $this->handler = new LightspeedWebhookHandler($this->pdo);
    }

    protected function tearDown(): void
    {
        putenv('LS_WEBHOOK_SECRET');
    }

    public function testRequiresWebhookSecretEnvironmentVariable(): void
    {
        putenv('LS_WEBHOOK_SECRET'); // Unset

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('LS_WEBHOOK_SECRET environment variable is required');

        new LightspeedWebhookHandler($this->pdo);
    }

    public function testRejectsMissingSignatureHeader(): void
    {
        $payload = json_encode(['event_id' => '123', 'event_type' => 'test', 'created_at' => time()]);
        $headers = []; // No signature

        $result = $this->handler->handle($payload, $headers);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Missing X-Lightspeed-Signature', $result['error']);
    }

    public function testRejectsInvalidSignature(): void
    {
        $payload = json_encode(['event_id' => '123', 'event_type' => 'test', 'created_at' => time()]);
        $headers = [
            'X-Lightspeed-Signature' => 'invalid_signature_123'
        ];

        $result = $this->handler->handle($payload, $headers);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Invalid webhook signature', $result['error']);
    }

    public function testAcceptsValidSignature(): void
    {
        $payload = json_encode([
            'event_id' => 'evt_123',
            'event_type' => 'consignment.created',
            'created_at' => time(),
            'data' => ['id' => 456]
        ]);

        // Generate valid HMAC signature
        $signature = hash_hmac('sha256', $payload, 'test_secret_key_12345');
        $headers = ['X-Lightspeed-Signature' => $signature];

        // Mock PDO statements for replay check and insert
        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetch')->willReturn(false); // No duplicate

        $this->pdo->method('prepare')->willReturn($stmt);
        $this->pdo->method('beginTransaction')->willReturn(true);
        $this->pdo->method('commit')->willReturn(true);
        $this->pdo->method('lastInsertId')->willReturnOnConsecutiveCalls('1', '100');

        $result = $this->handler->handle($payload, $headers);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('job_id', $result);
        $this->assertArrayHasKey('request_id', $result);
    }

    public function testRejectsInvalidJson(): void
    {
        $payload = 'invalid{json}';
        $signature = hash_hmac('sha256', $payload, 'test_secret_key_12345');
        $headers = ['X-Lightspeed-Signature' => $signature];

        $result = $this->handler->handle($payload, $headers);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Invalid JSON', $result['error']);
    }

    public function testRejectsMissingRequiredFields(): void
    {
        $payload = json_encode(['event_id' => '123']); // Missing event_type, created_at
        $signature = hash_hmac('sha256', $payload, 'test_secret_key_12345');
        $headers = ['X-Lightspeed-Signature' => $signature];

        $result = $this->handler->handle($payload, $headers);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Missing required field', $result['error']);
    }

    public function testRejectsOldEvents(): void
    {
        $oldTimestamp = time() - 400; // 400 seconds ago (> 5 min limit)
        $payload = json_encode([
            'event_id' => 'evt_old',
            'event_type' => 'test',
            'created_at' => $oldTimestamp
        ]);

        $signature = hash_hmac('sha256', $payload, 'test_secret_key_12345');
        $headers = ['X-Lightspeed-Signature' => $signature];

        $result = $this->handler->handle($payload, $headers);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Event too old', $result['error']);
    }

    public function testRejectsDuplicateEventId(): void
    {
        $payload = json_encode([
            'event_id' => 'evt_duplicate',
            'event_type' => 'test',
            'created_at' => time()
        ]);

        $signature = hash_hmac('sha256', $payload, 'test_secret_key_12345');
        $headers = ['X-Lightspeed-Signature' => $signature];

        // Mock duplicate detection
        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetch')->willReturn(['id' => 1]); // Duplicate found

        $this->pdo->method('prepare')->willReturn($stmt);

        $result = $this->handler->handle($payload, $headers);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Duplicate event ID', $result['error']);
    }

    public function testCaseInsensitiveHeaderMatching(): void
    {
        $payload = json_encode([
            'event_id' => 'evt_123',
            'event_type' => 'test',
            'created_at' => time()
        ]);

        $signature = hash_hmac('sha256', $payload, 'test_secret_key_12345');

        // Test lowercase header
        $headers = ['x-lightspeed-signature' => $signature];

        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetch')->willReturn(false);

        $this->pdo->method('prepare')->willReturn($stmt);
        $this->pdo->method('beginTransaction')->willReturn(true);
        $this->pdo->method('commit')->willReturn(true);
        $this->pdo->method('lastInsertId')->willReturnOnConsecutiveCalls('1', '100');

        $result = $this->handler->handle($payload, $headers);

        $this->assertTrue($result['success']);
    }
}
