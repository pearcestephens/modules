<?php
declare(strict_types=1);

namespace Consignments\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Consignments\Domain\Services\StocktakeService;
use Consignments\Infra\Lightspeed\LightspeedClient;
use Psr\Log\LoggerInterface;

class StocktakeServiceTest extends TestCase
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

        $this->service = new StocktakeService(
            $this->pdo,
            $this->lightspeedClient,
            $this->logger
        );
    }

    public function testCreateStocktakeValidatesOutletId(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('outlet_id');

        $this->service->createStocktake(0, []);
    }

    public function testCreateStocktakeValidatesCountsNotEmpty(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('non-empty array');

        $this->service->createStocktake(1, []);
    }

    public function testCreateStocktakeValidatesCountStructure(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->service->createStocktake(1, [
            ['product_id' => 'ABC'] // Missing physical_count
        ]);
    }

    public function testCreateStocktakeValidatesPositiveCount(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('negative');

        $this->service->createStocktake(1, [
            ['product_id' => 'ABC', 'physical_count' => -5]
        ]);
    }

    public function testRequiresApprovalReturnsTrueForHighValue(): void
    {
        $variances = [
            ['variance' => 10, 'variance_value' => 600.00] // > $500 threshold
        ];

        $result = $this->service->requiresApproval($variances);

        $this->assertTrue($result);
    }

    public function testRequiresApprovalReturnsTrueForHighUnits(): void
    {
        $variances = [
            ['variance' => 55, 'variance_value' => 100.00] // > 50 units threshold
        ];

        $result = $this->service->requiresApproval($variances);

        $this->assertTrue($result);
    }

    public function testRequiresApprovalReturnsFalseForLowValues(): void
    {
        $variances = [
            ['variance' => 5, 'variance_value' => 50.00] // < both thresholds
        ];

        $result = $this->service->requiresApproval($variances);

        $this->assertFalse($result);
    }

    public function testRequiresApprovalUsesAbsoluteValues(): void
    {
        $variances = [
            ['variance' => -60, 'variance_value' => -100.00] // Negative but abs > threshold
        ];

        $result = $this->service->requiresApproval($variances);

        $this->assertTrue($result); // Should be true due to absolute unit count
    }

    public function testApproveThrowsIfStocktakeNotFound(): void
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
        $stmt->method('fetch')->willReturn(['status' => 'ADJUSTED']);

        $this->pdo->method('prepare')->willReturn($stmt);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('DRAFT status');

        $this->service->approve(1, 1);
    }

    public function testGenerateAdjustmentThrowsIfStocktakeNotFound(): void
    {
        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->method('fetch')->willReturn(false);

        $this->pdo->method('prepare')->willReturn($stmt);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('not found');

        $this->service->generateAdjustmentTransfer(999);
    }

    public function testGenerateAdjustmentThrowsIfNotApprovedStatus(): void
    {
        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->method('fetch')->willReturn(['status' => 'DRAFT', 'outlet_id' => 1]);

        $this->pdo->method('prepare')->willReturn($stmt);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('APPROVED status');

        $this->service->generateAdjustmentTransfer(1);
    }

    public function testCalculateVariancesReturnsPositiveAndNegative(): void
    {
        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->method('fetchAll')->willReturn([
            ['product_id' => 'A', 'physical_count' => 10, 'system_count' => 8, 'variance' => 2, 'variance_value' => 20.00],
            ['product_id' => 'B', 'physical_count' => 5, 'system_count' => 10, 'variance' => -5, 'variance_value' => -50.00],
            ['product_id' => 'C', 'physical_count' => 0, 'system_count' => 0, 'variance' => 0, 'variance_value' => 0.00]
        ]);

        $this->pdo->method('prepare')->willReturn($stmt);

        $result = $this->service->calculateVariances(1);

        $this->assertArrayHasKey('positive', $result);
        $this->assertArrayHasKey('negative', $result);
        $this->assertCount(1, $result['positive']); // One positive variance
        $this->assertCount(1, $result['negative']); // One negative variance
        $this->assertEquals(20.00, $result['total_positive_value']);
        $this->assertEquals(-50.00, $result['total_negative_value']);
        $this->assertEquals(-30.00, $result['net_variance_value']);
    }

    public function testCalculateVariancesHandlesAllPositive(): void
    {
        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->method('fetchAll')->willReturn([
            ['product_id' => 'A', 'physical_count' => 10, 'system_count' => 8, 'variance' => 2, 'variance_value' => 20.00],
            ['product_id' => 'B', 'physical_count' => 15, 'system_count' => 10, 'variance' => 5, 'variance_value' => 50.00]
        ]);

        $this->pdo->method('prepare')->willReturn($stmt);

        $result = $this->service->calculateVariances(1);

        $this->assertCount(2, $result['positive']);
        $this->assertCount(0, $result['negative']);
        $this->assertEquals(70.00, $result['net_variance_value']);
    }

    public function testCalculateVariancesHandlesAllNegative(): void
    {
        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->method('fetchAll')->willReturn([
            ['product_id' => 'A', 'physical_count' => 5, 'system_count' => 10, 'variance' => -5, 'variance_value' => -50.00],
            ['product_id' => 'B', 'physical_count' => 0, 'system_count' => 3, 'variance' => -3, 'variance_value' => -30.00]
        ]);

        $this->pdo->method('prepare')->willReturn($stmt);

        $result = $this->service->calculateVariances(1);

        $this->assertCount(0, $result['positive']);
        $this->assertCount(2, $result['negative']);
        $this->assertEquals(-80.00, $result['net_variance_value']);
    }
}
