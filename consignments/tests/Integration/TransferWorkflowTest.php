<?php
declare(strict_types=1);

namespace Consignments\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Consignments\Domain\Services\OutletTransferService;
use Consignments\Domain\Services\SupplierReturnService;
use Consignments\Domain\Services\StocktakeService;
use Consignments\Infra\Lightspeed\LightspeedClient;
use Psr\Log\LoggerInterface;

/**
 * Integration tests for complete transfer workflows
 *
 * Tests end-to-end workflows across all 3 transfer services
 */
class TransferWorkflowTest extends TestCase
{
    private $pdo;
    private $lightspeedClient;
    private $logger;

    protected function setUp(): void
    {
        // Use test database
        $dsn = sprintf('mysql:host=%s;dbname=%s_test;charset=utf8mb4',
            getenv('DB_HOST') ?: 'localhost',
            getenv('DB_NAME') ?: 'cis'
        );

        $this->pdo = new \PDO($dsn, getenv('DB_USER'), getenv('DB_PASS'), [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC
        ]);

        $this->lightspeedClient = $this->createMock(LightspeedClient::class);

        $this->logger = new class implements LoggerInterface {
            use \Psr\Log\LoggerTrait;
            public function log($level, $message, array $context = []): void {
                error_log("[{$level}] {$message} " . json_encode($context));
            }
        };

        // Clean test data
        $this->cleanTestData();
    }

    protected function tearDown(): void
    {
        $this->cleanTestData();
    }

    public function testOutletTransferCompleteWorkflow(): void
    {
        $service = new OutletTransferService($this->pdo, $this->lightspeedClient, $this->logger);

        // Create transfer
        $transfer = $service->createTransfer([
            'source_outlet_id' => 1,
            'destination_outlet_id' => 2,
            'items' => [
                ['product_id' => 'TEST-001', 'quantity' => 10, 'unit_cost' => 50.00]
            ],
            'notes' => 'Test transfer'
        ]);

        $this->assertArrayHasKey('transfer_id', $transfer);
        $this->assertEquals('DRAFT', $transfer['status']);
        $this->assertTrue($transfer['requires_approval']); // $500 > threshold

        // Approve transfer
        $approved = $service->approve($transfer['transfer_id'], 1);
        $this->assertTrue($approved);

        // Send transfer
        $sent = $service->send($transfer['transfer_id'], [
            'tracking_number' => 'TEST123',
            'carrier' => 'NZ Post'
        ]);

        $this->assertArrayHasKey('consignment_id', $sent);
        $this->assertEquals('SENT', $sent['status']);

        // Receive transfer
        $received = $service->receive($transfer['transfer_id'], [
            'items' => [
                ['product_id' => 'TEST-001', 'received_quantity' => 10]
            ],
            'received_by' => 1
        ]);

        $this->assertEquals('RECEIVED', $received['status']);
    }

    public function testSupplierReturnCompleteWorkflow(): void
    {
        $service = new SupplierReturnService($this->pdo, $this->lightspeedClient, $this->logger);

        // Create return
        $return = $service->createReturn([
            'supplier_id' => 1,
            'outlet_id' => 1,
            'reason' => 'DAMAGED',
            'items' => [
                ['product_id' => 'TEST-002', 'quantity' => 5, 'unit_cost' => 30.00]
            ]
        ]);

        $this->assertArrayHasKey('return_id', $return);
        $this->assertEquals('DRAFT', $return['status']);
        $this->assertEquals('DAMAGED', $return['reason']);

        // Attach evidence (required for DAMAGED)
        $evidenceAttached = $service->attachEvidence($return['return_id'], 'uploads/evidence/damaged_001.jpg');
        $this->assertTrue($evidenceAttached);

        // Approve return
        $approved = $service->approve($return['return_id'], 1);
        $this->assertTrue($approved);

        // Ship return
        $shipped = $service->ship($return['return_id'], [
            'tracking_number' => 'RETURN123',
            'carrier' => 'GoSweetSpot'
        ]);

        $this->assertTrue($shipped);

        // Process refund
        $refunded = $service->processRefund($return['return_id'], 150.00, 'Full refund processed');
        $this->assertTrue($refunded);
    }

    public function testStocktakeCompleteWorkflow(): void
    {
        $service = new StocktakeService($this->pdo, $this->lightspeedClient, $this->logger);

        // Setup: Insert test inventory data
        $this->setupTestInventory();

        // Create stocktake with variances
        $stocktake = $service->createStocktake(1, [
            ['product_id' => 'TEST-003', 'physical_count' => 95, 'unit_cost' => 10.00], // -5 variance
            ['product_id' => 'TEST-004', 'physical_count' => 110, 'unit_cost' => 15.00]  // +10 variance
        ]);

        $this->assertArrayHasKey('stocktake_id', $stocktake);
        $this->assertEquals('DRAFT', $stocktake['status']);
        $this->assertCount(2, $stocktake['variances']);
        $this->assertTrue($stocktake['requires_approval']); // Total > 50 units

        // Calculate variances
        $variances = $service->calculateVariances($stocktake['stocktake_id']);
        $this->assertArrayHasKey('positive', $variances);
        $this->assertArrayHasKey('negative', $variances);
        $this->assertCount(1, $variances['positive']); // +10 units
        $this->assertCount(1, $variances['negative']); // -5 units

        // Approve stocktake
        $approved = $service->approve($stocktake['stocktake_id'], 1);
        $this->assertTrue($approved);

        // Generate adjustment transfer
        $adjustment = $service->generateAdjustmentTransfer($stocktake['stocktake_id']);

        $this->assertArrayHasKey('transfer_id', $adjustment);
        $this->assertEquals('ADJUSTED', $adjustment['status']);
        $this->assertEquals(2, $adjustment['adjustment_count']); // 2 variances
    }

    // ===========================
    // HELPER METHODS
    // ===========================

    private function cleanTestData(): void
    {
        $this->pdo->exec("DELETE FROM stocktake_audit_log WHERE stocktake_id IN (SELECT id FROM stocktakes WHERE outlet_id = 999)");
        $this->pdo->exec("DELETE FROM stocktake_items WHERE stocktake_id IN (SELECT id FROM stocktakes WHERE outlet_id = 999)");
        $this->pdo->exec("DELETE FROM stocktakes WHERE outlet_id = 999");

        $this->pdo->exec("DELETE FROM supplier_return_audit_log WHERE return_id IN (SELECT id FROM supplier_returns WHERE outlet_id = 999)");
        $this->pdo->exec("DELETE FROM supplier_return_evidence WHERE return_id IN (SELECT id FROM supplier_returns WHERE outlet_id = 999)");
        $this->pdo->exec("DELETE FROM supplier_return_items WHERE return_id IN (SELECT id FROM supplier_returns WHERE outlet_id = 999)");
        $this->pdo->exec("DELETE FROM supplier_returns WHERE outlet_id = 999");

        $this->pdo->exec("DELETE FROM transfer_audit_log WHERE transfer_id IN (SELECT id FROM stock_transfers WHERE source_outlet_id = 999 OR destination_outlet_id = 999)");
        $this->pdo->exec("DELETE FROM stock_transfer_items WHERE transfer_id IN (SELECT id FROM stock_transfers WHERE source_outlet_id = 999 OR destination_outlet_id = 999)");
        $this->pdo->exec("DELETE FROM stock_transfers WHERE source_outlet_id = 999 OR destination_outlet_id = 999");

        $this->pdo->exec("DELETE FROM vend_inventory WHERE product_id LIKE 'TEST-%'");
    }

    private function setupTestInventory(): void
    {
        // Insert test inventory data for stocktake
        $stmt = $this->pdo->prepare("
            INSERT INTO vend_inventory (outlet_id, product_id, inventory_count)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE inventory_count = VALUES(inventory_count)
        ");

        $stmt->execute([1, 'TEST-003', 100]); // Will have -5 variance
        $stmt->execute([1, 'TEST-004', 100]); // Will have +10 variance
    }
}
