<?php
/**
 * Stock Transfer Service
 *
 * Domain service for inter-outlet stock transfers.
 * Handles stock movement between outlets.
 *
 * Business Rules:
 * - Must have source and destination outlets
 * - Source and destination must be different
 * - Stock decremented from source, incremented at destination
 * - Generates consignment on send
 * - Can receive partial quantities
 *
 * @package Consignments\App\Services
 */

declare(strict_types=1);

namespace Consignments\App\Services;

use Consignments\Domain\ValueObjects\Status;
use Consignments\Infra\Lightspeed\LightspeedClient;
use Psr\Log\LoggerInterface;

class StockTransferService
{
    private \PDO $pdo;
    private LightspeedClient $client;
    private LoggerInterface $logger;

    public function __construct(\PDO $pdo, LightspeedClient $client, LoggerInterface $logger)
    {
        $this->pdo = $pdo;
        $this->client = $client;
        $this->logger = $logger;
    }

    /**
     * Create new Stock Transfer
     *
     * @param array $data Transfer data (source_outlet_id, destination_outlet_id, items[], expected_date, notes)
     * @return array Created transfer with ID
     * @throws \InvalidArgumentException if validation fails
     */
    public function create(array $data): array
    {
        $this->validateCreateData($data);

        $this->pdo->beginTransaction();
        try {
            // Insert transfer header
            $stmt = $this->pdo->prepare("
                INSERT INTO stock_transfers (
                    type, status, source_outlet_id, destination_outlet_id,
                    expected_date, notes, created_at, updated_at
                ) VALUES (
                    'STOCK_TRANSFER', 'DRAFT', :source_outlet_id, :destination_outlet_id,
                    :expected_date, :notes, NOW(), NOW()
                )
            ");
            $stmt->execute([
                'source_outlet_id' => $data['source_outlet_id'],
                'destination_outlet_id' => $data['destination_outlet_id'],
                'expected_date' => $data['expected_date'],
                'notes' => $data['notes'] ?? null,
            ]);

            $transferId = (int)$this->pdo->lastInsertId();

            // Insert items
            $stmt = $this->pdo->prepare("
                INSERT INTO stock_transfer_items (
                    transfer_id, product_id, ordered_qty, received_qty, created_at
                ) VALUES (
                    :transfer_id, :product_id, :ordered_qty, 0, NOW()
                )
            ");

            foreach ($data['items'] as $item) {
                $stmt->execute([
                    'transfer_id' => $transferId,
                    'product_id' => $item['product_id'],
                    'ordered_qty' => $item['quantity'],
                ]);
            }

            // Update expected stock at destination
            $this->updateExpectedStockLevels($transferId, $data['destination_outlet_id'], 'increment');

            $this->pdo->commit();

            $this->logger->info('Stock transfer created', [
                'transfer_id' => $transferId,
                'source_outlet_id' => $data['source_outlet_id'],
                'destination_outlet_id' => $data['destination_outlet_id'],
                'item_count' => count($data['items']),
            ]);

            return [
                'transfer_id' => $transferId,
                'type' => 'STOCK_TRANSFER',
                'status' => 'DRAFT',
            ];

        } catch (\Exception $e) {
            $this->pdo->rollBack();
            $this->logger->error('Failed to create stock transfer', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);
            throw $e;
        }
    }

    /**
     * Send Stock Transfer
     *
     * Marks transfer as SENT and creates Lightspeed consignment.
     * Decrements stock from source outlet.
     *
     * @param int $transferId Transfer ID
     * @return array Updated transfer data
     * @throws \RuntimeException if transfer not in valid state
     */
    public function send(int $transferId): array
    {
        $transfer = $this->getTransfer($transferId);

        if ($transfer['status'] !== 'DRAFT') {
            throw new \RuntimeException("Cannot send transfer in status: {$transfer['status']}");
        }

        $this->pdo->beginTransaction();
        try {
            // Decrement actual stock from source outlet
            $items = $this->getTransferItems($transferId);
            foreach ($items as $item) {
                $this->updateActualStockLevel(
                    $item['product_id'],
                    $transfer['source_outlet_id'],
                    -$item['ordered_qty']
                );
            }

            // Create Lightspeed consignment
            $consignmentPayload = $this->buildConsignmentPayload($transfer, $items);
            $response = $this->client->post('/api/2.0/consignments.json', $consignmentPayload);

            // Update transfer status
            $stmt = $this->pdo->prepare("
                UPDATE stock_transfers
                SET status = 'SENT',
                    lightspeed_consignment_id = :consignment_id,
                    sent_at = NOW(),
                    updated_at = NOW()
                WHERE id = :transfer_id
            ");
            $stmt->execute([
                'consignment_id' => $response['consignment']['id'] ?? null,
                'transfer_id' => $transferId,
            ]);

            // Queue tracking job
            $this->queueStatusTrackingJob($transferId, $response['consignment']['id'] ?? null);

            $this->pdo->commit();

            $this->logger->info('Stock transfer sent', [
                'transfer_id' => $transferId,
                'consignment_id' => $response['consignment']['id'] ?? null,
            ]);

            return [
                'transfer_id' => $transferId,
                'consignment_id' => $response['consignment']['id'] ?? null,
                'status' => 'SENT',
            ];

        } catch (\Exception $e) {
            $this->pdo->rollBack();
            $this->logger->error('Failed to send stock transfer', [
                'transfer_id' => $transferId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Receive Stock Transfer
     *
     * Records received quantities at destination outlet.
     * Updates actual inventory and checks if fully received.
     *
     * @param int $transferId Transfer ID
     * @param array $items Items received [['item_id' => int, 'quantity' => int], ...]
     * @return array Updated transfer status
     * @throws \RuntimeException if transfer not in valid state
     */
    public function receive(int $transferId, array $items): array
    {
        $transfer = $this->getTransfer($transferId);

        if (!in_array($transfer['status'], ['SENT', 'PARTIALLY_RECEIVED'])) {
            throw new \RuntimeException("Cannot receive transfer in status: {$transfer['status']}");
        }

        $this->pdo->beginTransaction();
        try {
            foreach ($items as $item) {
                // Update received quantity
                $stmt = $this->pdo->prepare("
                    UPDATE stock_transfer_items
                    SET received_qty = received_qty + :quantity,
                        updated_at = NOW()
                    WHERE id = :item_id AND transfer_id = :transfer_id
                ");
                $stmt->execute([
                    'quantity' => $item['quantity'],
                    'item_id' => $item['item_id'],
                    'transfer_id' => $transferId,
                ]);

                // Get product_id for this item
                $stmt = $this->pdo->prepare("
                    SELECT product_id FROM stock_transfer_items WHERE id = :item_id
                ");
                $stmt->execute(['item_id' => $item['item_id']]);
                $productId = $stmt->fetchColumn();

                // Increment actual inventory at destination
                $this->updateActualStockLevel(
                    $productId,
                    $transfer['destination_outlet_id'],
                    $item['quantity']
                );

                // Decrement expected stock at destination
                $this->updateExpectedStockLevels($transferId, $transfer['destination_outlet_id'], 'decrement');
            }

            // Check if fully received
            $fullyReceived = $this->isFullyReceived($transferId);
            $newStatus = $fullyReceived ? 'RECEIVED' : 'PARTIALLY_RECEIVED';

            $stmt = $this->pdo->prepare("
                UPDATE stock_transfers
                SET status = :status,
                    received_at = CASE WHEN :fully_received THEN NOW() ELSE received_at END,
                    updated_at = NOW()
                WHERE id = :transfer_id
            ");
            $stmt->execute([
                'status' => $newStatus,
                'fully_received' => $fullyReceived,
                'transfer_id' => $transferId,
            ]);

            $this->pdo->commit();

            $this->logger->info('Stock transfer received', [
                'transfer_id' => $transferId,
                'status' => $newStatus,
                'fully_received' => $fullyReceived,
            ]);

            return [
                'transfer_id' => $transferId,
                'status' => $newStatus,
                'fully_received' => $fullyReceived,
            ];

        } catch (\Exception $e) {
            $this->pdo->rollBack();
            $this->logger->error('Failed to receive stock transfer', [
                'transfer_id' => $transferId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Cancel Stock Transfer
     *
     * Cancels transfer if not yet sent. Reverts expected stock changes.
     *
     * @param int $transferId Transfer ID
     * @param string $reason Cancellation reason
     * @return array Updated transfer status
     * @throws \RuntimeException if transfer already sent
     */
    public function cancel(int $transferId, string $reason): array
    {
        $transfer = $this->getTransfer($transferId);

        if (!in_array($transfer['status'], ['DRAFT', 'OPEN'])) {
            throw new \RuntimeException("Cannot cancel transfer in status: {$transfer['status']}");
        }

        $this->pdo->beginTransaction();
        try {
            // Revert expected stock at destination
            $this->updateExpectedStockLevels($transferId, $transfer['destination_outlet_id'], 'decrement');

            // Update transfer status
            $stmt = $this->pdo->prepare("
                UPDATE stock_transfers
                SET status = 'CANCELLED',
                    notes = CONCAT(COALESCE(notes, ''), '\n\nCancelled: ', :reason),
                    updated_at = NOW()
                WHERE id = :transfer_id
            ");
            $stmt->execute([
                'reason' => $reason,
                'transfer_id' => $transferId,
            ]);

            $this->pdo->commit();

            $this->logger->info('Stock transfer cancelled', [
                'transfer_id' => $transferId,
                'reason' => $reason,
            ]);

            return [
                'transfer_id' => $transferId,
                'status' => 'CANCELLED',
            ];

        } catch (\Exception $e) {
            $this->pdo->rollBack();
            $this->logger->error('Failed to cancel stock transfer', [
                'transfer_id' => $transferId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Validate create data
     *
     * @param array $data
     * @throws \InvalidArgumentException if validation fails
     */
    private function validateCreateData(array $data): void
    {
        if (empty($data['source_outlet_id'])) {
            throw new \InvalidArgumentException('source_outlet_id is required');
        }

        if (empty($data['destination_outlet_id'])) {
            throw new \InvalidArgumentException('destination_outlet_id is required');
        }

        if ($data['source_outlet_id'] === $data['destination_outlet_id']) {
            throw new \InvalidArgumentException('source and destination outlets must be different');
        }

        if (empty($data['items']) || !is_array($data['items'])) {
            throw new \InvalidArgumentException('items array is required');
        }

        if (count($data['items']) === 0) {
            throw new \InvalidArgumentException('items array is required');
        }

        foreach ($data['items'] as $item) {
            if (empty($item['product_id'])) {
                throw new \InvalidArgumentException('product_id is required for all items');
            }
            if (empty($item['quantity']) || $item['quantity'] <= 0) {
                throw new \InvalidArgumentException('quantity must be positive for all items');
            }
        }

        if (empty($data['expected_date'])) {
            throw new \InvalidArgumentException('expected_date is required');
        }
    }

    /**
     * Get transfer by ID
     *
     * @param int $transferId
     * @return array Transfer data
     * @throws \RuntimeException if not found
     */
    private function getTransfer(int $transferId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM stock_transfers WHERE id = :transfer_id
        ");
        $stmt->execute(['transfer_id' => $transferId]);
        $transfer = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$transfer) {
            throw new \RuntimeException("Transfer not found: {$transferId}");
        }

        return $transfer;
    }

    /**
     * Get transfer items
     *
     * @param int $transferId
     * @return array Items
     */
    private function getTransferItems(int $transferId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM stock_transfer_items WHERE transfer_id = :transfer_id
        ");
        $stmt->execute(['transfer_id' => $transferId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Build Lightspeed consignment payload
     *
     * @param array $transfer
     * @param array $items
     * @return array Payload for Lightspeed API
     */
    private function buildConsignmentPayload(array $transfer, array $items): array
    {
        $consignmentProducts = [];
        foreach ($items as $item) {
            $consignmentProducts[] = [
                'product_id' => $item['product_id'],
                'count' => $item['ordered_qty'],
            ];
        }

        return [
            'consignment' => [
                'outlet_id' => $transfer['destination_outlet_id'],
                'consignment_products' => $consignmentProducts,
                'due_at' => $transfer['expected_date'],
                'name' => "Stock Transfer #{$transfer['id']}",
                'type' => 'SUPPLIER',
            ],
        ];
    }

    /**
     * Update expected stock levels
     *
     * @param int $transferId
     * @param int $outletId
     * @param string $operation 'increment' or 'decrement'
     */
    private function updateExpectedStockLevels(int $transferId, int $outletId, string $operation): void
    {
        $items = $this->getTransferItems($transferId);
        $operator = $operation === 'increment' ? '+' : '-';

        foreach ($items as $item) {
            $stmt = $this->pdo->prepare("
                UPDATE vend_inventory
                SET expected_stock = expected_stock {$operator} :quantity
                WHERE product_id = :product_id AND outlet_id = :outlet_id
            ");
            $stmt->execute([
                'quantity' => $item['ordered_qty'],
                'product_id' => $item['product_id'],
                'outlet_id' => $outletId,
            ]);
        }
    }

    /**
     * Update actual stock level
     *
     * @param int $productId
     * @param int $outletId
     * @param int $delta Change in quantity (positive or negative)
     */
    private function updateActualStockLevel(int $productId, int $outletId, int $delta): void
    {
        $operator = $delta >= 0 ? '+' : '-';
        $absQuantity = abs($delta);

        $stmt = $this->pdo->prepare("
            UPDATE vend_inventory
            SET inventory_count = inventory_count {$operator} :quantity,
                updated_at = NOW()
            WHERE product_id = :product_id AND outlet_id = :outlet_id
        ");
        $stmt->execute([
            'quantity' => $absQuantity,
            'product_id' => $productId,
            'outlet_id' => $outletId,
        ]);
    }

    /**
     * Check if transfer is fully received
     *
     * @param int $transferId
     * @return bool
     */
    private function isFullyReceived(int $transferId): bool
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as incomplete_count
            FROM stock_transfer_items
            WHERE transfer_id = :transfer_id
              AND received_qty < ordered_qty
        ");
        $stmt->execute(['transfer_id' => $transferId]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $result['incomplete_count'] == 0;
    }

    /**
     * Queue status tracking job
     *
     * @param int $transferId
     * @param string|null $consignmentId
     */
    private function queueStatusTrackingJob(int $transferId, ?string $consignmentId): void
    {
        if (!$consignmentId) {
            return;
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO queue_jobs (type, payload, status, priority, created_at)
            VALUES ('consignment.track', :payload, 'pending', 5, NOW())
        ");
        $stmt->execute([
            'payload' => json_encode([
                'transfer_id' => $transferId,
                'consignment_id' => $consignmentId,
            ]),
        ]);
    }
}
