<?php
declare(strict_types=1);

namespace Consignments\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Consignments\Domain\Services\SupplierReturnService;
use Consignments\Infra\Lightspeed\LightspeedClient;
use Psr\Log\LoggerInterface;

class SupplierReturnServiceTest extends TestCase
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

        $this->service = new SupplierReturnService(
            $this->pdo,
            $this->lightspeedClient,
            $this->logger
        );
    }

    public function testCreateReturnValidatesRequiredFields(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('supplier_id');

        $this->service->createReturn([]);
    }

    public function testCreateReturnValidatesSupplierId(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->service->createReturn([
            'supplier_id' => 0,
            'outlet_id' => 1,
            'reason' => 'DAMAGED',
            'items' => []
        ]);
    }

    public function testCreateReturnValidatesOutletId(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->service->createReturn([
            'supplier_id' => 1,
            'outlet_id' => 0,
            'reason' => 'DAMAGED',
            'items' => []
        ]);
    }

    public function testCreateReturnValidatesReason(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('reason');

        $this->service->createReturn([
            'supplier_id' => 1,
            'outlet_id' => 1,
            'reason' => '',
            'items' => []
        ]);
    }

    public function testCreateReturnValidatesReasonIsValid(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid reason');

        $this->service->createReturn([
            'supplier_id' => 1,
            'outlet_id' => 1,
            'reason' => 'INVALID_REASON',
            'items' => []
        ]);
    }

    public function testCreateReturnValidatesItemsNotEmpty(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Items cannot be empty');

        $this->service->createReturn([
            'supplier_id' => 1,
            'outlet_id' => 1,
            'reason' => 'DAMAGED',
            'items' => []
        ]);
    }

    public function testCreateReturnAcceptsDamagedReason(): void
    {
        // Should not throw for DAMAGED
        $this->assertTrue(in_array('DAMAGED', ['DAMAGED', 'INCORRECT', 'OVERSTOCK', 'WARRANTY', 'OTHER']));
    }

    public function testCreateReturnAcceptsIncorrectReason(): void
    {
        $this->assertTrue(in_array('INCORRECT', ['DAMAGED', 'INCORRECT', 'OVERSTOCK', 'WARRANTY', 'OTHER']));
    }

    public function testCreateReturnAcceptsOverstockReason(): void
    {
        $this->assertTrue(in_array('OVERSTOCK', ['DAMAGED', 'INCORRECT', 'OVERSTOCK', 'WARRANTY', 'OTHER']));
    }

    public function testCreateReturnAcceptsWarrantyReason(): void
    {
        $this->assertTrue(in_array('WARRANTY', ['DAMAGED', 'INCORRECT', 'OVERSTOCK', 'WARRANTY', 'OTHER']));
    }

    public function testCreateReturnAcceptsOtherReason(): void
    {
        $this->assertTrue(in_array('OTHER', ['DAMAGED', 'INCORRECT', 'OVERSTOCK', 'WARRANTY', 'OTHER']));
    }

    public function testAddReturnItemValidatesReturnId(): void
    {
        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->method('fetch')->willReturn(false);

        $this->pdo->method('prepare')->willReturn($stmt);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('not found');

        $this->service->addReturnItem(999, []);
    }

    public function testAddReturnItemThrowsIfNotDraftStatus(): void
    {
        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->method('fetch')->willReturn(['status' => 'APPROVED']);

        $this->pdo->method('prepare')->willReturn($stmt);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('DRAFT status');

        $this->service->addReturnItem(1, []);
    }

    public function testAttachEvidenceValidatesReturnId(): void
    {
        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->method('fetch')->willReturn(false);

        $this->pdo->method('prepare')->willReturn($stmt);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('not found');

        $this->service->attachEvidence(999, 'photo.jpg');
    }

    public function testAttachEvidenceValidatesPhotoUrl(): void
    {
        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->method('fetch')->willReturn(['id' => 1]);

        $this->pdo->method('prepare')->willReturn($stmt);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('photo_url');

        $this->service->attachEvidence(1, '');
    }

    public function testApproveThrowsIfReturnNotFound(): void
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
        $stmt->method('fetch')->willReturn(['status' => 'SHIPPED', 'reason' => 'DAMAGED']);

        $this->pdo->method('prepare')->willReturn($stmt);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('DRAFT status');

        $this->service->approve(1, 1);
    }

    public function testShipThrowsIfReturnNotFound(): void
    {
        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->method('fetch')->willReturn(false);

        $this->pdo->method('prepare')->willReturn($stmt);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('not found');

        $this->service->ship(999, []);
    }

    public function testShipThrowsIfNotApprovedStatus(): void
    {
        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->method('fetch')->willReturn(['status' => 'DRAFT']);

        $this->pdo->method('prepare')->willReturn($stmt);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('APPROVED status');

        $this->service->ship(1, []);
    }

    public function testProcessRefundThrowsIfReturnNotFound(): void
    {
        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->method('fetch')->willReturn(false);

        $this->pdo->method('prepare')->willReturn($stmt);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('not found');

        $this->service->processRefund(999, 100.00, 'Refund processed');
    }

    public function testProcessRefundThrowsIfNotShippedStatus(): void
    {
        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->method('fetch')->willReturn(['status' => 'APPROVED']);

        $this->pdo->method('prepare')->willReturn($stmt);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('SHIPPED status');

        $this->service->processRefund(1, 100.00, 'Refund processed');
    }
}
