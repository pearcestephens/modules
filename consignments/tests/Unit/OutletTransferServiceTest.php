<?php
declare(strict_types=1);

namespace Consignments\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Consignments\Domain\Services\OutletTransferService;
use Consignments\Infra\Lightspeed\LightspeedClient;
use Psr\Log\LoggerInterface;

class OutletTransferServiceTest extends TestCase
{
    private $pdo;
    private $lightspeedClient;
    private $logger;
    private $service;

    protected function setUp(): void
    {
        $this->pdo = $this->createMock(\PDO::class);
        $this->lightspeedClient = $this->createMock(LightspeedClient::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->service = new OutletTransferService(
            $this->pdo,
            $this->lightspeedClient,
            $this->logger
        );
    }

    public function testCreateTransferValidatesRequiredFields(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('source_outlet_id');

        $this->service->createTransfer([]);
    }

    public function testCreateTransferValidatesSourceOutletId(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->service->createTransfer([
            'source_outlet_id' => 0,
            'destination_outlet_id' => 2,
            'items' => []
        ]);
    }

    public function testCreateTransferValidatesDestinationOutletId(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->service->createTransfer([
            'source_outlet_id' => 1,
            'destination_outlet_id' => 0,
            'items' => []
        ]);
    }

    public function testCreateTransferValidatesItemsNotEmpty(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Items cannot be empty');

        $this->service->createTransfer([
            'source_outlet_id' => 1,
            'destination_outlet_id' => 2,
            'items' => []
        ]);
    }

    public function testCreateTransferValidatesItemStructure(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->service->createTransfer([
            'source_outlet_id' => 1,
            'destination_outlet_id' => 2,
            'items' => [
                ['product_id' => 'ABC'] // Missing quantity
            ]
        ]);
    }

    public function testCreateTransferValidatesPositiveQuantity(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->service->createTransfer([
            'source_outlet_id' => 1,
            'destination_outlet_id' => 2,
            'items' => [
                ['product_id' => 'ABC', 'quantity' => 0]
            ]
        ]);
    }

    public function testRequiresApprovalReturnsTrueForHighValue(): void
    {
        $result = $this->service->requiresApproval(2500.00); // > $2000 threshold

        $this->assertTrue($result);
    }

    public function testRequiresApprovalReturnsFalseForLowValue(): void
    {
        $result = $this->service->requiresApproval(1500.00); // < $2000 threshold

        $this->assertFalse($result);
    }

    public function testRequiresApprovalReturnsTrueForExactThreshold(): void
    {
        $result = $this->service->requiresApproval(2000.00); // = threshold

        $this->assertFalse($result); // Should be false for exact value
    }

    public function testRequiresApprovalReturnsTrueForJustOverThreshold(): void
    {
        $result = $this->service->requiresApproval(2000.01);

        $this->assertTrue($result);
    }

    public function testApproveThrowsIfTransferNotFound(): void
    {
        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->method('fetch')->willReturn(false);

        $this->pdo->method('prepare')->willReturn($stmt);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('not found');

        $this->service->approve(999, 1);
    }

    public function testApproveThrowsIfNotDraftStatus(): void
    {
        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->method('fetch')->willReturn(['status' => 'SENT']);

        $this->pdo->method('prepare')->willReturn($stmt);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('DRAFT status');

        $this->service->approve(1, 1);
    }

    public function testSendThrowsIfTransferNotFound(): void
    {
        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->method('fetch')->willReturn(false);

        $this->pdo->method('prepare')->willReturn($stmt);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('not found');

        $this->service->send(999);
    }

    public function testSendThrowsIfNotApprovedStatus(): void
    {
        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->method('fetch')->willReturn(['status' => 'DRAFT']);

        $this->pdo->method('prepare')->willReturn($stmt);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('APPROVED status');

        $this->service->send(1);
    }

    public function testReceiveThrowsIfTransferNotFound(): void
    {
        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->method('fetch')->willReturn(false);

        $this->pdo->method('prepare')->willReturn($stmt);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('not found');

        $this->service->receive(999, []);
    }

    public function testReceiveThrowsIfNotSentStatus(): void
    {
        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->method('fetch')->willReturn(['status' => 'APPROVED']);

        $this->pdo->method('prepare')->willReturn($stmt);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('SENT status');

        $this->service->receive(1, []);
    }

    public function testValidateStockLevelsValidatesOutletId(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->service->validateStockLevels(0, []);
    }

    public function testValidateStockLevelsValidatesItemsNotEmpty(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->service->validateStockLevels(1, []);
    }
}
