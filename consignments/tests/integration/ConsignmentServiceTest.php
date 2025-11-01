<?php declare(strict_types=1);

namespace Consignments\Tests\Integration;

use Consignments\Services\ConsignmentService;
use Consignments\Domain\ValueObjects\Status;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for ConsignmentService with state transitions
 *
 * These tests verify the service layer enforces state transition policy.
 * Requires test database with consignments table.
 */
final class ConsignmentServiceTest extends TestCase
{
    private PDO $pdo;
    private ConsignmentService $service;

    protected function setUp(): void
    {
        // Setup in-memory SQLite for testing
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Create minimal schema
        $this->pdo->exec('
            CREATE TABLE consignments (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                ref_code TEXT,
                status TEXT DEFAULT "draft",
                origin_outlet_id INTEGER,
                dest_outlet_id INTEGER,
                created_by INTEGER,
                created_at TEXT,
                updated_at TEXT
            )
        ');

        $this->pdo->exec('
            CREATE TABLE consignment_items (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                consignment_id INTEGER,
                product_id INTEGER,
                sku TEXT,
                qty INTEGER,
                packed_qty INTEGER DEFAULT 0,
                status TEXT DEFAULT "pending",
                created_at TEXT,
                updated_at TEXT
            )
        ');

        $this->service = new ConsignmentService($this->pdo, $this->pdo);
    }

    public function testUpdateItemPackedQtySuccess(): void
    {
        // Create consignment and item
        $consignmentId = $this->service->create([
            'ref_code' => 'TEST-001',
            'status' => 'draft',
            'origin_outlet_id' => 1,
            'dest_outlet_id' => 2,
            'created_by' => 1
        ]);

        $itemId = $this->service->addItem($consignmentId, [
            'product_id' => 123,
            'sku' => 'TEST-SKU',
            'qty' => 10,
            'packed_qty' => 0
        ]);

        // Update packed qty
        $result = $this->service->updateItemPackedQty($itemId, 5);
        $this->assertTrue($result);

        // Verify update
        $items = $this->service->items($consignmentId);
        $this->assertCount(1, $items);
        $this->assertEquals(5, $items[0]['packed_qty']);
    }

    public function testUpdateItemPackedQtyRejectsNegative(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('cannot be negative');

        $this->service->updateItemPackedQty(1, -5);
    }

    public function testUpdateStatusValidatesStatus(): void
    {
        $id = $this->service->create([
            'ref_code' => 'TEST-002',
            'status' => 'draft',
            'origin_outlet_id' => 1,
            'dest_outlet_id' => 2,
            'created_by' => 1
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid status "invalid_status"');

        $this->service->updateStatus($id, 'invalid_status');
    }

    public function testChangeStatusEnforcesTransitions(): void
    {
        $id = $this->service->create([
            'ref_code' => 'TEST-003',
            'status' => 'draft',
            'origin_outlet_id' => 1,
            'dest_outlet_id' => 2,
            'created_by' => 1
        ]);

        // Valid transition: draft → sent
        $result = $this->service->changeStatus($id, 'sent');
        $this->assertTrue($result['success']);
        $this->assertEquals('draft', $result['old_status']);
        $this->assertEquals('sent', $result['new_status']);
        $this->assertNull($result['error']);

        // Invalid transition: sent → completed (must go through receiving → received)
        $result = $this->service->changeStatus($id, 'completed');
        $this->assertFalse($result['success']);
        $this->assertEquals('sent', $result['old_status']);
        $this->assertEquals('completed', $result['new_status']);
        $this->assertStringContainsString('Illegal state transition', $result['error']);
    }

    public function testChangeStatusAllowsValidChain(): void
    {
        $id = $this->service->create([
            'ref_code' => 'TEST-004',
            'status' => 'draft',
            'origin_outlet_id' => 1,
            'dest_outlet_id' => 2,
            'created_by' => 1
        ]);

        // draft → sent
        $result = $this->service->changeStatus($id, 'sent');
        $this->assertTrue($result['success']);

        // sent → receiving
        $result = $this->service->changeStatus($id, 'receiving');
        $this->assertTrue($result['success']);

        // receiving → received
        $result = $this->service->changeStatus($id, 'received');
        $this->assertTrue($result['success']);

        // received → completed
        $result = $this->service->changeStatus($id, 'completed');
        $this->assertTrue($result['success']);

        // Verify final state
        $consignment = $this->service->get($id);
        $this->assertEquals('completed', $consignment['status']);
    }

    public function testChangeStatusAllowsCancellation(): void
    {
        $id = $this->service->create([
            'ref_code' => 'TEST-005',
            'status' => 'draft',
            'origin_outlet_id' => 1,
            'dest_outlet_id' => 2,
            'created_by' => 1
        ]);

        // draft → sent
        $this->service->changeStatus($id, 'sent');

        // sent → cancelled (allowed from any non-terminal state)
        $result = $this->service->changeStatus($id, 'cancelled');
        $this->assertTrue($result['success']);
        $this->assertEquals('cancelled', $result['new_status']);
    }

    public function testChangeStatusRejectsFromTerminalState(): void
    {
        $id = $this->service->create([
            'ref_code' => 'TEST-006',
            'status' => 'completed',
            'origin_outlet_id' => 1,
            'dest_outlet_id' => 2,
            'created_by' => 1
        ]);

        // completed → draft (terminal state, not allowed)
        $result = $this->service->changeStatus($id, 'draft');
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('terminal state', $result['error']);
    }
}
