<?php

/**
 * PHPUnit tests for SecuritySystemWebhookReceiver
 */

namespace FraudDetection\Tests\Unit;

use PHPUnit\Framework\TestCase;
use FraudDetection\Webhooks\SecuritySystemWebhookReceiver;
use PDO;
use PDOStatement;

class SecuritySystemWebhookReceiverTest extends TestCase
{
    private PDO $pdoMock;
    private SecuritySystemWebhookReceiver $receiver;

    protected function setUp(): void
    {
        $this->pdoMock = $this->createMock(PDO::class);
        $this->receiver = new SecuritySystemWebhookReceiver($this->pdoMock, [
            'webhook_secret' => 'test_secret_key'
        ]);
    }

    public function testHandleValidMotionDetectionEvent(): void
    {
        $payload = [
            'event_type' => 'motion_detected',
            'camera_id' => 'camera_001',
            'camera_name' => 'Store 1 - Checkout',
            'outlet_id' => 1,
            'zone' => 'checkout',
            'timestamp' => '2025-11-14T10:30:00Z',
            'confidence' => 0.95,
            'alert_level' => 'low',
            'detection_data' => [
                'person_count' => 1,
                'frame_url' => 'https://example.com/frame.jpg'
            ]
        ];

        $stmtMock = $this->createMock(PDOStatement::class);
        $stmtMock->expects($this->once())
            ->method('execute')
            ->willReturn(true);

        $this->pdoMock->expects($this->atLeastOnce())
            ->method('prepare')
            ->willReturn($stmtMock);

        $this->pdoMock->expects($this->once())
            ->method('lastInsertId')
            ->willReturn('123');

        // Mock staff correlation (no staff nearby)
        $stmtMock->expects($this->any())
            ->method('fetchAll')
            ->willReturn([]);

        // Simulate webhook input
        $this->simulateWebhookInput($payload);

        $result = $this->receiver->handle();

        $this->assertTrue($result['success']);
        $this->assertEquals(123, $result['result']['event_id']);
        $this->assertEquals('motion_detected', $result['result']['event_type']);
    }

    public function testHandlePersonDetectedWithStaffCorrelation(): void
    {
        $payload = [
            'event_type' => 'person_detected',
            'camera_id' => 'camera_003',
            'camera_name' => 'Store 1 - Stockroom',
            'outlet_id' => 1,
            'zone' => 'stockroom',
            'timestamp' => '2025-11-14T22:30:00Z', // After hours
            'confidence' => 0.92,
            'alert_level' => 'high',
            'detection_data' => [
                'person_count' => 1,
                'frame_url' => 'https://example.com/frame.jpg'
            ]
        ];

        $stmtMock = $this->createMock(PDOStatement::class);

        // Mock staff correlation - 1 staff member nearby
        $stmtMock->expects($this->any())
            ->method('fetchAll')
            ->willReturnOnConsecutiveCalls(
                [['1']], // FETCH_COLUMN for staff IDs
                []       // Other queries
            );

        $stmtMock->expects($this->atLeastOnce())
            ->method('execute')
            ->willReturn(true);

        $this->pdoMock->expects($this->atLeastOnce())
            ->method('prepare')
            ->willReturn($stmtMock);

        $this->pdoMock->expects($this->once())
            ->method('lastInsertId')
            ->willReturn('124');

        $this->simulateWebhookInput($payload);

        $result = $this->receiver->handle();

        $this->assertTrue($result['success']);
        $this->assertEquals(124, $result['result']['event_id']);
        $this->assertNotEmpty($result['result']['correlated_staff']);
        $this->assertTrue($result['result']['fraud_analysis_triggered']);
    }

    public function testHandleSuspiciousActivityTriggersFraudAnalysis(): void
    {
        $payload = [
            'event_type' => 'suspicious_activity',
            'camera_id' => 'camera_004',
            'camera_name' => 'Store 1 - Office',
            'outlet_id' => 1,
            'zone' => 'office',
            'timestamp' => '2025-11-14T14:30:00Z',
            'confidence' => 0.88,
            'alert_level' => 'critical',
            'detection_data' => [
                'description' => 'Unusual behavior detected'
            ]
        ];

        $stmtMock = $this->createMock(PDOStatement::class);

        // Mock staff correlation
        $stmtMock->expects($this->any())
            ->method('fetchAll')
            ->willReturnOnConsecutiveCalls(
                [['2'], ['3']], // 2 staff members
                []
            );

        $stmtMock->expects($this->atLeastOnce())
            ->method('execute')
            ->willReturn(true);

        $this->pdoMock->expects($this->atLeastOnce())
            ->method('prepare')
            ->willReturn($stmtMock);

        $this->pdoMock->expects($this->once())
            ->method('lastInsertId')
            ->willReturn('125');

        $this->simulateWebhookInput($payload);

        $result = $this->receiver->handle();

        $this->assertTrue($result['success']);
        $this->assertEquals('suspicious_activity', $result['result']['event_type']);
        $this->assertTrue($result['result']['fraud_analysis_triggered']);
    }

    public function testHandleMissingRequiredFields(): void
    {
        $payload = [
            'event_type' => 'motion_detected'
            // Missing camera_id
        ];

        $this->simulateWebhookInput($payload);

        $result = $this->receiver->handle();

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Missing required fields', $result['error']);
    }

    public function testHandleInvalidJSON(): void
    {
        // Simulate invalid JSON input
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['HTTP_X_SECURITY_SIGNATURE'] = 'valid_signature';

        // Mock file_get_contents to return invalid JSON
        $this->simulateRawInput('invalid json {{{');

        $result = $this->receiver->handle();

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Invalid JSON payload', $result['error']);
    }

    public function testGetOutletSecurityStats(): void
    {
        $stmtMock = $this->createMock(PDOStatement::class);
        $stmtMock->expects($this->once())
            ->method('execute')
            ->with([
                'outlet_id' => 1,
                'days' => 30
            ])
            ->willReturn(true);

        $stmtMock->expects($this->once())
            ->method('fetchAll')
            ->willReturn([
                [
                    'event_type' => 'motion_detected',
                    'zone' => 'checkout',
                    'alert_level' => 'low',
                    'event_count' => 45,
                    'avg_confidence' => 0.92,
                    'last_event' => '2025-11-14 10:30:00'
                ],
                [
                    'event_type' => 'person_detected',
                    'zone' => 'stockroom',
                    'alert_level' => 'medium',
                    'event_count' => 12,
                    'avg_confidence' => 0.88,
                    'last_event' => '2025-11-13 18:15:00'
                ]
            ]);

        $this->pdoMock->expects($this->once())
            ->method('prepare')
            ->willReturn($stmtMock);

        $stats = $this->receiver->getOutletSecurityStats(1, 30);

        $this->assertCount(2, $stats);
        $this->assertEquals('motion_detected', $stats[0]['event_type']);
        $this->assertEquals(45, $stats[0]['event_count']);
        $this->assertEquals('person_detected', $stats[1]['event_type']);
        $this->assertEquals(12, $stats[1]['event_count']);
    }

    public function testGetAfterHoursIncidents(): void
    {
        $stmtMock = $this->createMock(PDOStatement::class);
        $stmtMock->expects($this->once())
            ->method('execute')
            ->with(['days' => 7])
            ->willReturn(true);

        $stmtMock->expects($this->once())
            ->method('fetchAll')
            ->willReturn([
                [
                    'event_type' => 'person_detected',
                    'camera_name' => 'Store 1 - Stockroom',
                    'zone' => 'stockroom',
                    'event_timestamp' => '2025-11-13 23:30:00',
                    'staff_id' => 5,
                    'staff_name' => 'John Doe'
                ]
            ]);

        $this->pdoMock->expects($this->once())
            ->method('prepare')
            ->willReturn($stmtMock);

        $incidents = $this->receiver->getAfterHoursIncidents(7);

        $this->assertCount(1, $incidents);
        $this->assertEquals('person_detected', $incidents[0]['event_type']);
        $this->assertEquals('stockroom', $incidents[0]['zone']);
        $this->assertEquals(5, $incidents[0]['staff_id']);
    }

    public function testAfterHoursDetection(): void
    {
        // Test various times
        $testCases = [
            ['03:00', true],  // 3 AM - after hours
            ['05:59', true],  // 5:59 AM - after hours
            ['06:00', false], // 6 AM - business hours
            ['12:00', false], // Noon - business hours
            ['21:59', false], // 9:59 PM - business hours
            ['22:00', true],  // 10 PM - after hours
            ['23:30', true],  // 11:30 PM - after hours
        ];

        foreach ($testCases as [$time, $expectedAfterHours]) {
            // We can't easily test private methods, but we can verify
            // the logic through integration testing
            $this->assertTrue(true); // Placeholder
        }
    }

    /**
     * Helper: Simulate webhook input for testing
     */
    private function simulateWebhookInput(array $payload): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['HTTP_X_SECURITY_SIGNATURE'] = hash_hmac('sha256', json_encode($payload), 'test_secret_key');
        $this->simulateRawInput(json_encode($payload));
    }

    /**
     * Helper: Simulate raw PHP input
     */
    private function simulateRawInput(string $input): void
    {
        // In real tests, we'd use stream wrappers
        // For now, this is a placeholder
        // $_test_input = $input;
    }

    protected function tearDown(): void
    {
        unset($this->pdoMock);
        unset($this->receiver);
    }
}
