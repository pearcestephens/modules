<?php

declare(strict_types=1);

namespace Tests\Unit\App\Services;

use Consignments\App\Services\PurchaseOrderService;
use Consignments\Infra\Lightspeed\LightspeedClient;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class PurchaseOrderServiceTest extends TestCase
{
    private \PDO $pdo;
    private LightspeedClient $client;
    private LoggerInterface $logger;
    private PurchaseOrderService $service;

    protected function setUp(): void
    {
        $this->pdo = $this->createMock(\PDO::class);
        $this->client = $this->createMock(LightspeedClient::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->service = new PurchaseOrderService($this->pdo, $this->client, $this->logger);
    }

    public function testCreateRequiresSupplier(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required field: supplier_id');

        $this->service->create([
            'outlet_id' => 1,
            'items' => [['product_id' => 1, 'quantity' => 5]],
            'expected_date' => '2025-12-01',
        ]);
    }

    public function testCreateRequiresOutlet(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required field: outlet_id');

        $this->service->create([
            'supplier_id' => 1,
            'items' => [['product_id' => 1, 'quantity' => 5]],
            'expected_date' => '2025-12-01',
        ]);
    }

    public function testCreateRequiresItems(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required field: items');

        $this->service->create([
            'supplier_id' => 1,
            'outlet_id' => 1,
            'expected_date' => '2025-12-01',
        ]);
    }

    public function testCreateRequiresAtLeastOneItem(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Purchase Order must have at least one item');

        $this->service->create([
            'supplier_id' => 1,
            'outlet_id' => 1,
            'items' => [],
            'expected_date' => '2025-12-01',
        ]);
    }

    public function testCreateRequiresExpectedDate(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required field: expected_date');

        $this->service->create([
            'supplier_id' => 1,
            'outlet_id' => 1,
            'items' => [['product_id' => 1, 'quantity' => 5]],
        ]);
    }

    public function testCreateRequiresProductId(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Each item must have product_id and quantity');

        $this->service->create([
            'supplier_id' => 1,
            'outlet_id' => 1,
            'items' => [['quantity' => 5]],
            'expected_date' => '2025-12-01',
        ]);
    }

    public function testCreateRequiresPositiveQuantity(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Item quantity must be positive');

        $this->service->create([
            'supplier_id' => 1,
            'outlet_id' => 1,
            'items' => [['product_id' => 1, 'quantity' => 0]],
            'expected_date' => '2025-12-01',
        ]);
    }

    public function testCreateSuccessReturnsTransferId(): void
    {
        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->method('execute')->willReturn(true);

        $this->pdo->method('prepare')->willReturn($stmt);
        $this->pdo->method('lastInsertId')->willReturn('123');
        $this->pdo->method('beginTransaction')->willReturn(true);
        $this->pdo->method('commit')->willReturn(true);

        $result = $this->service->create([
            'supplier_id' => 1,
            'outlet_id' => 1,
            'items' => [
                ['product_id' => 1, 'quantity' => 5],
                ['product_id' => 2, 'quantity' => 10],
            ],
            'expected_date' => '2025-12-01',
            'notes' => 'Test PO',
        ]);

        $this->assertIsArray($result);
        $this->assertEquals(123, $result['transfer_id']);
        $this->assertEquals('PURCHASE_ORDER', $result['type']);
        $this->assertEquals('OPEN', $result['status']);
    }

    public function testCreateRollsBackOnError(): void
    {
        $this->pdo->method('beginTransaction')->willReturn(true);
        $this->pdo->method('prepare')->willThrowException(new \Exception('DB error'));
        $this->pdo->expects($this->once())->method('rollBack');

        $this->expectException(\Exception::class);

        $this->service->create([
            'supplier_id' => 1,
            'outlet_id' => 1,
            'items' => [['product_id' => 1, 'quantity' => 5]],
            'expected_date' => '2025-12-01',
        ]);
    }

    public function testSendThrowsIfNotInOpenStatus(): void
    {
        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->method('fetch')->willReturn([
            'id' => 1,
            'type' => 'PURCHASE_ORDER',
            'status' => 'SENT',
            'supplier_id' => 1,
        ]);

        $this->pdo->method('prepare')->willReturn($stmt);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Purchase Order must be in OPEN status to send');

        $this->service->send(1);
    }

    public function testReceiveThrowsIfNotInSentStatus(): void
    {
        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->method('fetch')->willReturn([
            'id' => 1,
            'type' => 'PURCHASE_ORDER',
            'status' => 'OPEN',
        ]);

        $this->pdo->method('prepare')->willReturn($stmt);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Purchase Order must be SENT before receiving');

        $this->service->receive(1, [['product_id' => 1, 'quantity' => 5]]);
    }

    public function testCancelThrowsIfAlreadySent(): void
    {
        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->method('fetch')->willReturn([
            'id' => 1,
            'type' => 'PURCHASE_ORDER',
            'status' => 'SENT',
        ]);

        $this->pdo->method('prepare')->willReturn($stmt);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot cancel Purchase Order in SENT status');

        $this->service->cancel(1, 'Changed mind');
    }
}
