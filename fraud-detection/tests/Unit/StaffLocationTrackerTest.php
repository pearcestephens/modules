<?php

namespace FraudDetection\Tests\Unit;

use PHPUnit\Framework\TestCase;
use FraudDetection\StaffLocationTracker;
use PDO;
use PDOStatement;

class StaffLocationTrackerTest extends TestCase
{
    private PDO $pdoMock;
    private StaffLocationTracker $tracker;

    protected function setUp(): void
    {
        $this->pdoMock = $this->createMock(PDO::class);
        $this->tracker = new StaffLocationTracker($this->pdoMock, [
            'deputy_api_key' => 'test_key',
            'deputy_api_url' => 'https://test.deputy.com/v1'
        ]);
    }

    public function testGetCurrentLocationFromBadgeSystem(): void
    {
        $stmtMock = $this->createMock(PDOStatement::class);
        $stmtMock->method('execute')->willReturn(true);
        $stmtMock->method('fetch')->willReturn([
            'outlet_id' => 1,
            'outlet_name' => 'Store 1',
            'scan_time' => date('Y-m-d H:i:s', strtotime('-10 minutes')),
            'scan_type' => 'in'
        ]);

        $this->pdoMock->method('prepare')->willReturn($stmtMock);

        $location = $this->tracker->getCurrentLocation(123);

        $this->assertIsArray($location);
        $this->assertEquals(1, $location['outlet_id']);
        $this->assertEquals('Store 1', $location['outlet_name']);
        $this->assertGreaterThan(0.9, $location['confidence']);
        $this->assertEquals('badge_system', $location['source']);
    }

    public function testGetCurrentLocationReturnsNullForInvalidStaff(): void
    {
        $stmtMock = $this->createMock(PDOStatement::class);
        $stmtMock->method('execute')->willReturn(true);
        $stmtMock->method('fetch')->willReturn(false);

        $this->pdoMock->method('prepare')->willReturn($stmtMock);

        $location = $this->tracker->getCurrentLocation(999);

        $this->assertNull($location);
    }

    public function testGetMultipleLocations(): void
    {
        $stmtMock = $this->createMock(PDOStatement::class);
        $stmtMock->method('execute')->willReturn(true);
        $stmtMock->method('fetch')->willReturnOnConsecutiveCalls(
            [
                'outlet_id' => 1,
                'outlet_name' => 'Store 1',
                'scan_time' => date('Y-m-d H:i:s'),
                'scan_type' => 'in'
            ],
            [
                'outlet_id' => 2,
                'outlet_name' => 'Store 2',
                'scan_time' => date('Y-m-d H:i:s'),
                'scan_type' => 'in'
            ]
        );

        $this->pdoMock->method('prepare')->willReturn($stmtMock);

        $locations = $this->tracker->getMultipleLocations([123, 456]);

        $this->assertIsArray($locations);
        $this->assertCount(2, $locations);
        $this->assertArrayHasKey(123, $locations);
        $this->assertArrayHasKey(456, $locations);
    }

    public function testGetStaffAtOutlet(): void
    {
        $stmtMock = $this->createMock(PDOStatement::class);
        $stmtMock->method('execute')->willReturn(true);
        $stmtMock->method('fetchAll')->willReturn([
            ['id' => 1, 'name' => 'John Doe', 'email' => 'john@test.com', 'default_outlet_id' => 1],
            ['id' => 2, 'name' => 'Jane Smith', 'email' => 'jane@test.com', 'default_outlet_id' => 1]
        ]);
        $stmtMock->method('fetch')->willReturnOnConsecutiveCalls(
            [
                'outlet_id' => 1,
                'outlet_name' => 'Store 1',
                'scan_time' => date('Y-m-d H:i:s'),
                'scan_type' => 'in'
            ],
            [
                'outlet_id' => 1,
                'outlet_name' => 'Store 1',
                'scan_time' => date('Y-m-d H:i:s'),
                'scan_type' => 'in'
            ]
        );

        $this->pdoMock->method('prepare')->willReturn($stmtMock);

        $staff = $this->tracker->getStaffAtOutlet(1);

        $this->assertIsArray($staff);
        $this->assertCount(2, $staff);
        $this->assertEquals('John Doe', $staff[0]['name']);
    }

    public function testGetCamerasForStaffLocation(): void
    {
        $stmtMock = $this->createMock(PDOStatement::class);
        $stmtMock->method('execute')->willReturn(true);
        $stmtMock->method('fetch')->willReturn([
            'outlet_id' => 1,
            'outlet_name' => 'Store 1',
            'confidence' => 0.95,
            'scan_time' => date('Y-m-d H:i:s'),
            'scan_type' => 'in'
        ]);
        $stmtMock->method('fetchAll')->willReturn([
            ['id' => 1, 'name' => 'Camera 1', 'rtsp_url' => 'rtsp://cam1', 'zone' => 'checkout'],
            ['id' => 2, 'name' => 'Camera 2', 'rtsp_url' => 'rtsp://cam2', 'zone' => 'stockroom']
        ]);

        $this->pdoMock->method('prepare')->willReturn($stmtMock);

        $cameras = $this->tracker->getCamerasForStaffLocation(123);

        $this->assertIsArray($cameras);
        $this->assertCount(2, $cameras);
        $this->assertEquals('Camera 1', $cameras[0]['name']);
    }

    public function testConfidenceDecreaseOverTime(): void
    {
        // Recent scan should have high confidence
        $recentStmt = $this->createMock(PDOStatement::class);
        $recentStmt->method('execute')->willReturn(true);
        $recentStmt->method('fetch')->willReturn([
            'outlet_id' => 1,
            'outlet_name' => 'Store 1',
            'scan_time' => date('Y-m-d H:i:s', strtotime('-5 minutes')),
            'scan_type' => 'in'
        ]);

        $this->pdoMock->method('prepare')->willReturn($recentStmt);

        $recentLocation = $this->tracker->getCurrentLocation(123);
        $this->assertGreaterThan(0.95, $recentLocation['confidence']);

        // Old scan should have lower confidence
        $oldStmt = $this->createMock(PDOStatement::class);
        $oldStmt->method('execute')->willReturn(true);
        $oldStmt->method('fetch')->willReturn([
            'outlet_id' => 1,
            'outlet_name' => 'Store 1',
            'scan_time' => date('Y-m-d H:i:s', strtotime('-3 hours')),
            'scan_type' => 'in'
        ]);

        $this->pdoMock->method('prepare')->willReturn($oldStmt);
        $this->tracker->clearCache();

        $oldLocation = $this->tracker->getCurrentLocation(123);
        $this->assertLessThan(0.75, $oldLocation['confidence']);
    }

    public function testCachingBehavior(): void
    {
        $stmtMock = $this->createMock(PDOStatement::class);
        $stmtMock->expects($this->once())->method('execute')->willReturn(true);
        $stmtMock->method('fetch')->willReturn([
            'outlet_id' => 1,
            'outlet_name' => 'Store 1',
            'scan_time' => date('Y-m-d H:i:s'),
            'scan_type' => 'in'
        ]);

        $this->pdoMock->method('prepare')->willReturn($stmtMock);

        // First call - should hit database
        $location1 = $this->tracker->getCurrentLocation(123);

        // Second call - should use cache (execute called only once)
        $location2 = $this->tracker->getCurrentLocation(123);

        $this->assertEquals($location1, $location2);
    }

    public function testUpdateDeputyMapping(): void
    {
        $stmtMock = $this->createMock(PDOStatement::class);
        $stmtMock->method('execute')->willReturn(true);

        $this->pdoMock->method('prepare')->willReturn($stmtMock);

        $result = $this->tracker->updateDeputyMapping(100, 1);

        $this->assertTrue($result);
    }
}
