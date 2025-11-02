<?php

declare(strict_types=1);

namespace Tests\Unit\App\Services;

use Consignments\App\Services\ReturnToSupplierService;
use Consignments\Infra\Lightspeed\LightspeedClient;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class ReturnToSupplierServiceTest extends TestCase
{
    private \PDO $pdo;
    private LightspeedClient $client;
    private LoggerInterface $logger;
    private ReturnToSupplierService $service;

    protected function setUp(): void
    {
        $this->pdo = $this->createMock(\PDO::class);
        $this->client = $this->createMock(LightspeedClient::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->service = new ReturnToSupplierService($this->pdo, $this->client, $this->logger);
    }

    public function testCreateRequiresSupplier(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('supplier_id is required');

        $this->service->create([
            'outlet_id' => 1,
            'return_reason' => 'Damaged',
            'items' => [['product_id' => 1, 'quantity' => 5]],
        ]);
    }

    public function testCreateRequiresOutlet(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('outlet_id is required');

        $this->service->create([
            'supplier_id' => 1,
            'return_reason' => 'Damaged',
            'items' => [['product_id' => 1, 'quantity' => 5]],
        ]);
    }

    public function testCreateRequiresReturnReason(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('return_reason is required');

        $this->service->create([
            'supplier_id' => 1,
            'outlet_id' => 1,
            'items' => [['product_id' => 1, 'quantity' => 5]],
        ]);
    }

    public function testCreateRequiresItems(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('items array is required');

        $this->service->create([
            'supplier_id' => 1,
            'outlet_id' => 1,
            'return_reason' => 'Damaged',
        ]);
    }

    public function testCreateRequiresAtLeastOneItem(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('items array is required');

        $this->service->create([
            'supplier_id' => 1,
            'outlet_id' => 1,
            'return_reason' => 'Damaged',
            'items' => [],
        ]);
    }

    public function testCreateRequiresPositiveQuantity(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('quantity must be positive for all items');

        $this->service->create([
            'supplier_id' => 1,
            'outlet_id' => 1,
            'return_reason' => 'Damaged',
            'items' => [['product_id' => 1, 'quantity' => 0]],
        ]);
    }

    public function testCreateSuccessReturnsTransferId(): void
    {
        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->method('execute')->willReturn(true);

        $this->pdo->method('prepare')->willReturn($stmt);
        $this->pdo->method('lastInsertId')->willReturn('789');
        $this->pdo->method('beginTransaction')->willReturn(true);
        $this->pdo->method('commit')->willReturn(true);

        $result = $this->service->create([
            'supplier_id' => 1,
            'outlet_id' => 1,
            'return_reason' => 'Damaged on arrival',
            'items' => [
                ['product_id' => 1, 'quantity' => 5],
            ],
            'original_po_id' => 123,
            'notes' => 'Visible damage to packaging',
        ]);

        $this->assertIsArray($result);
        $this->assertEquals(789, $result['transfer_id']);
        $this->assertEquals('RETURN_TO_SUPPLIER', $result['type']);
        $this->assertEquals('PENDING', $result['status']);
    }

    public function testSendThrowsIfNotInPendingStatus(): void
    {
        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->method('fetch')->willReturn([
            'id' => 1,
            'status' => 'SENT',
            'supplier_id' => 1,
            'outlet_id' => 1,
        ]);

        $this->pdo->method('prepare')->willReturn($stmt);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot send return in status: SENT');

        $this->service->send(1);
    }

    public function testCompleteThrowsIfNotInSentStatus(): void
    {
        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->method('fetch')->willReturn([
            'id' => 1,
            'status' => 'PENDING',
        ]);

        $this->pdo->method('prepare')->willReturn($stmt);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot complete return in status: PENDING');

        $this->service->complete(1);
    }

    public function testCancelThrowsIfAlreadySent(): void
    {
        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->method('fetch')->willReturn([
            'id' => 1,
            'status' => 'SENT',
        ]);

        $this->pdo->method('prepare')->willReturn($stmt);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot cancel return in status: SENT');

        $this->service->cancel(1, 'Mistake');
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
            'return_reason' => 'Damaged',
            'items' => [['product_id' => 1, 'quantity' => 5]],
        ]);
    }
}
