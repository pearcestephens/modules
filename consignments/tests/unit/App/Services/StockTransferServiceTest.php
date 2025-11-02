<?php

declare(strict_types=1);

namespace Tests\Unit\App\Services;

use Consignments\App\Services\StockTransferService;
use Consignments\Infra\Lightspeed\LightspeedClient;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class StockTransferServiceTest extends TestCase
{
    private \PDO $pdo;
    private LightspeedClient $client;
    private LoggerInterface $logger;
    private StockTransferService $service;

    protected function setUp(): void
    {
        $this->pdo = $this->createMock(\PDO::class);
        $this->client = $this->createMock(LightspeedClient::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->service = new StockTransferService($this->pdo, $this->client, $this->logger);
    }

    public function testCreateRequiresSourceOutlet(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('source_outlet_id is required');

        $this->service->create([
            'destination_outlet_id' => 2,
            'items' => [['product_id' => 1, 'quantity' => 5]],
            'expected_date' => '2025-12-01',
        ]);
    }

    public function testCreateRequiresDestinationOutlet(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('destination_outlet_id is required');

        $this->service->create([
            'source_outlet_id' => 1,
            'items' => [['product_id' => 1, 'quantity' => 5]],
            'expected_date' => '2025-12-01',
        ]);
    }

    public function testCreateRequiresDifferentOutlets(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('source and destination outlets must be different');

        $this->service->create([
            'source_outlet_id' => 1,
            'destination_outlet_id' => 1,
            'items' => [['product_id' => 1, 'quantity' => 5]],
            'expected_date' => '2025-12-01',
        ]);
    }

    public function testCreateRequiresItems(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('items array is required');

        $this->service->create([
            'source_outlet_id' => 1,
            'destination_outlet_id' => 2,
            'expected_date' => '2025-12-01',
        ]);
    }

    public function testCreateRequiresAtLeastOneItem(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('items array is required');

        $this->service->create([
            'source_outlet_id' => 1,
            'destination_outlet_id' => 2,
            'items' => [],
            'expected_date' => '2025-12-01',
        ]);
    }

    public function testCreateRequiresExpectedDate(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('expected_date is required');

        $this->service->create([
            'source_outlet_id' => 1,
            'destination_outlet_id' => 2,
            'items' => [['product_id' => 1, 'quantity' => 5]],
        ]);
    }

    public function testCreateRequiresPositiveQuantity(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('quantity must be positive for all items');

        $this->service->create([
            'source_outlet_id' => 1,
            'destination_outlet_id' => 2,
            'items' => [['product_id' => 1, 'quantity' => -5]],
            'expected_date' => '2025-12-01',
        ]);
    }

    public function testCreateSuccessReturnsTransferId(): void
    {
        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->method('execute')->willReturn(true);

        $this->pdo->method('prepare')->willReturn($stmt);
        $this->pdo->method('lastInsertId')->willReturn('456');
        $this->pdo->method('beginTransaction')->willReturn(true);
        $this->pdo->method('commit')->willReturn(true);

        $result = $this->service->create([
            'source_outlet_id' => 1,
            'destination_outlet_id' => 2,
            'items' => [
                ['product_id' => 1, 'quantity' => 5],
                ['product_id' => 2, 'quantity' => 10],
            ],
            'expected_date' => '2025-12-01',
            'notes' => 'Test transfer',
        ]);

        $this->assertIsArray($result);
        $this->assertEquals(456, $result['transfer_id']);
        $this->assertEquals('STOCK_TRANSFER', $result['type']);
        $this->assertEquals('DRAFT', $result['status']);
    }

    public function testSendThrowsIfNotInDraftStatus(): void
    {
        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->method('fetch')->willReturn([
            'id' => 1,
            'status' => 'SENT',
            'source_outlet_id' => 1,
            'destination_outlet_id' => 2,
        ]);

        $this->pdo->method('prepare')->willReturn($stmt);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot send transfer in status: SENT');

        $this->service->send(1);
    }

    public function testReceiveThrowsIfNotInValidStatus(): void
    {
        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->method('fetch')->willReturn([
            'id' => 1,
            'status' => 'DRAFT',
        ]);

        $this->pdo->method('prepare')->willReturn($stmt);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot receive transfer in status: DRAFT');

        $this->service->receive(1, [['item_id' => 1, 'quantity' => 5]]);
    }

    public function testCancelThrowsIfAlreadySent(): void
    {
        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->method('fetch')->willReturn([
            'id' => 1,
            'status' => 'SENT',
            'destination_outlet_id' => 2,
        ]);

        $this->pdo->method('prepare')->willReturn($stmt);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot cancel transfer in status: SENT');

        $this->service->cancel(1, 'Changed plans');
    }

    public function testCreateRollsBackOnError(): void
    {
        $this->pdo->method('beginTransaction')->willReturn(true);
        $this->pdo->method('prepare')->willThrowException(new \Exception('DB error'));
        $this->pdo->expects($this->once())->method('rollBack');

        $this->expectException(\Exception::class);

        $this->service->create([
            'source_outlet_id' => 1,
            'destination_outlet_id' => 2,
            'items' => [['product_id' => 1, 'quantity' => 5]],
            'expected_date' => '2025-12-01',
        ]);
    }
}
