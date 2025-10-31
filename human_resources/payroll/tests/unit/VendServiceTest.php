<?php
declare(strict_types=1);

namespace CIS\HumanResources\Payroll\Tests\Unit;

use CIS\HumanResources\Payroll\Services\VendService;
use PHPUnit\Framework\TestCase;

/**
 * VendService Unit Tests
 *
 * Tests all VendService methods in isolation
 *
 * @package CIS\HumanResources\Payroll\Tests\Unit
 */
class VendServiceTest extends TestCase
{
    private VendService $service;

    protected function setUp(): void
    {
        $this->service = new VendService();
    }

    /**
     * Test getSnapshotDirectories returns array
     */
    public function testGetSnapshotDirectoriesReturnsArray(): void
    {
        $dirs = $this->service->getSnapshotDirectories();

        $this->assertIsArray($dirs);
        $this->assertNotEmpty($dirs, 'Should find at least one snapshot directory');

        // Verify all returned paths are actual directories
        foreach ($dirs as $dir) {
            $this->assertIsString($dir);
            $this->assertDirectoryExists($dir, "Directory should exist: {$dir}");
        }
    }

    /**
     * Test getSnapshotDirectories caches results
     */
    public function testGetSnapshotDirectoriesCachesResults(): void
    {
        $dirs1 = $this->service->getSnapshotDirectories();
        $dirs2 = $this->service->getSnapshotDirectories();

        $this->assertSame($dirs1, $dirs2, 'Should return same array instance (cached)');
    }

    /**
     * Test scanSnapshots returns expected structure
     */
    public function testScanSnapshotsReturnsCorrectStructure(): void
    {
        $result = $this->service->scanSnapshots();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('snapshots', $result);
        $this->assertArrayHasKey('dirs_scanned', $result);
        $this->assertArrayHasKey('skips', $result);
        $this->assertArrayHasKey('errors', $result);

        $this->assertIsArray($result['snapshots']);
        $this->assertIsArray($result['dirs_scanned']);
        $this->assertIsArray($result['skips']);
        $this->assertIsArray($result['errors']);
    }

    /**
     * Test scanSnapshots returns valid snapshot metadata
     */
    public function testScanSnapshotsReturnsValidMetadata(): void
    {
        $result = $this->service->scanSnapshots();

        if (empty($result['snapshots'])) {
            $this->markTestSkipped('No snapshots found for testing');
        }

        $snapshot = $result['snapshots'][0];

        $this->assertArrayHasKey('run_id', $snapshot);
        $this->assertArrayHasKey('started', $snapshot);
        $this->assertArrayHasKey('ended', $snapshot);
        $this->assertArrayHasKey('users_count', $snapshot);
        $this->assertArrayHasKey('path', $snapshot);

        $this->assertIsString($snapshot['run_id']);
        $this->assertIsString($snapshot['started']);
        $this->assertIsString($snapshot['ended']);
        $this->assertIsInt($snapshot['users_count']);
        $this->assertIsString($snapshot['path']);
        $this->assertFileExists($snapshot['path']);
    }

    /**
     * Test scanSnapshots sorts by most recent first
     */
    public function testScanSnapshotsSortsByMostRecent(): void
    {
        $result = $this->service->scanSnapshots();

        if (count($result['snapshots']) < 2) {
            $this->markTestSkipped('Need at least 2 snapshots to test sorting');
        }

        $first = $result['snapshots'][0];
        $second = $result['snapshots'][1];

        $firstDate = $first['ended'] ?: $first['started'];
        $secondDate = $second['ended'] ?: $second['started'];

        $this->assertGreaterThanOrEqual(
            $secondDate,
            $firstDate,
            'First snapshot should be more recent than second'
        );
    }

    /**
     * Test loadSnapshotByRun with null returns latest
     */
    public function testLoadSnapshotByRunWithNullReturnsLatest(): void
    {
        $snapshot = $this->service->loadSnapshotByRun(null);

        if ($snapshot === null) {
            $this->markTestSkipped('No snapshots found for testing');
        }

        $this->assertIsArray($snapshot);
        $this->assertArrayHasKey('_summary_path', $snapshot);
        $this->assertArrayHasKey('run_id', $snapshot);
        $this->assertArrayHasKey('users', $snapshot);
    }

    /**
     * Test loadSnapshotByRun with invalid ID returns null
     */
    public function testLoadSnapshotByRunWithInvalidIdReturnsNull(): void
    {
        $snapshot = $this->service->loadSnapshotByRun('invalid_run_id_xyz123');

        $this->assertNull($snapshot);
    }

    /**
     * Test loadSnapshotByRun with valid ID returns snapshot
     */
    public function testLoadSnapshotByRunWithValidIdReturnsSnapshot(): void
    {
        // First get list of available snapshots
        $scan = $this->service->scanSnapshots();

        if (empty($scan['snapshots'])) {
            $this->markTestSkipped('No snapshots found for testing');
        }

        $runId = $scan['snapshots'][0]['run_id'];
        $snapshot = $this->service->loadSnapshotByRun($runId);

        $this->assertIsArray($snapshot);
        $this->assertEquals($runId, $snapshot['run_id']);
        $this->assertArrayHasKey('_summary_path', $snapshot);
    }

    /**
     * Test resolveRegisterId returns string
     */
    public function testResolveRegisterIdReturnsString(): void
    {
        $id = $this->service->resolveRegisterId();

        $this->assertIsString($id);
        $this->assertNotEmpty($id);

        // Should be a UUID format
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            $id,
            'Register ID should be a valid UUID'
        );
    }

    /**
     * Test resolveRegisterId with custom name
     */
    public function testResolveRegisterIdWithCustomName(): void
    {
        $id = $this->service->resolveRegisterId('Hamilton East');

        $this->assertIsString($id);
        $this->assertNotEmpty($id);
    }

    /**
     * Test resolvePaymentType returns string
     */
    public function testResolvePaymentTypeReturnsString(): void
    {
        $id = $this->service->resolvePaymentType();

        $this->assertIsString($id);
        $this->assertNotEmpty($id);
    }

    /**
     * Test resolvePaymentType with custom name
     */
    public function testResolvePaymentTypeWithCustomName(): void
    {
        $id = $this->service->resolvePaymentType('Internet Banking');

        $this->assertIsString($id);
        $this->assertNotEmpty($id);
    }

    /**
     * Test getDefaultRegisterName
     */
    public function testGetDefaultRegisterName(): void
    {
        $name = $this->service->getDefaultRegisterName();

        $this->assertEquals('Hamilton East', $name);
    }

    /**
     * Test getDefaultPaymentTypeName
     */
    public function testGetDefaultPaymentTypeName(): void
    {
        $name = $this->service->getDefaultPaymentTypeName();

        $this->assertEquals('Internet Banking', $name);
    }
}
