<?php
/**
 * Return to Supplier Service
 *
 * Domain service for returning products to suppliers (RTS).
 * Handles damaged/defective product returns.
 *
 * Business Rules:
 * - Must reference supplier
 * - Must provide return reason
 * - Stock decremented on send
 * - No partial returns (all items sent together)
 * - Can track credit/refund from supplier
 *
 * @package Consignments\App\Services
 */

declare(strict_types=1);

namespace Consignments\App\Services;

use Consignments\Domain\ValueObjects\Status;
use Consignments\Infra\Lightspeed\LightspeedClient;
use Psr\Log\LoggerInterface;

class ReturnToSupplierService
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
     * Create new Return to Supplier
     *
     * @param array $data RTS data (supplier_id, outlet_id, items[], return_reason, original_po_id)
     * @return array Created RTS with ID
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
                    type, status, supplier_id, outlet_id, return_reason,
                    original_transfer_id, notes, created_at, updated_at
                ) VALUES (
                    'RETURN_TO_SUPPLIER', 'PENDING', :supplier_id, :outlet_id,
                    :return_reason, :original_po_id, :notes, NOW(), NOW()
                )
            ");
            $stmt->execute([
                'supplier_id' => $data['supplier_id'],
                'outlet_id' => $data['outlet_id'],
                'return_reason' => $data['return_reason'],
                'original_po_id' => $data['original_po_id'] ?? null,
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

            $this->pdo->commit();

            $this->logger->info('Return to supplier created', [
                'transfer_id' => $transferId,
                'supplier_id' => $data['supplier_id'],
                'return_reason' => $data['return_reason'],
                'item_count' => count($data['items']),
            ]);

            return [
                'transfer_id' => $transferId,
                'type' => 'RETURN_TO_SUPPLIER',
                'status' => 'PENDING',
            ];

        } catch (\Exception $e) {
            $this->pdo->rollBack();
            $this->logger->error('Failed to create return to supplier', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);
            throw $e;
        }
    }

    /**
     * Send Return to Supplier
     *
     * Marks return as SENT and decrements stock.
     * Creates outbound consignment in Lightspeed.
     *
     * @param int $transferId Transfer ID
     * @return array Updated transfer data
     * @throws \RuntimeException if transfer not in valid state
     */
    public function send(int $transferId): array
    {
        $transfer = $this->getTransfer($transferId);

        if ($transfer['status'] !== 'PENDING') {
            throw new \RuntimeException("Cannot send return in status: {$transfer['status']}");
        }

        $this->pdo->beginTransaction();
        try {
            // Decrement stock from outlet
            $items = $this->getTransferItems($transferId);
            foreach ($items as $item) {
                $this->updateActualStockLevel(
                    $item['product_id'],
                    $transfer['outlet_id'],
                    -$item['ordered_qty']
                );
            }

            // Create Lightspeed return consignment
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

            $this->pdo->commit();

            $this->logger->info('Return to supplier sent', [
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
            $this->logger->error('Failed to send return to supplier', [
                'transfer_id' => $transferId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Complete Return to Supplier
     *
     * Marks return as COMPLETED when supplier confirms receipt/credit.
     *
     * @param int $transferId Transfer ID
     * @param array $completionData Optional credit/refund details
     * @return array Updated transfer status
     * @throws \RuntimeException if transfer not in valid state
     */
    public function complete(int $transferId, array $completionData = []): array
    {
        $transfer = $this->getTransfer($transferId);

        if ($transfer['status'] !== 'SENT') {
            throw new \RuntimeException("Cannot complete return in status: {$transfer['status']}");
        }

        $this->pdo->beginTransaction();
        try {
            // Update transfer status
            $stmt = $this->pdo->prepare("
                UPDATE stock_transfers
                SET status = 'COMPLETED',
                    received_at = NOW(),
                    credit_amount = :credit_amount,
                    credit_reference = :credit_reference,
                    notes = CONCAT(COALESCE(notes, ''), :completion_notes),
                    updated_at = NOW()
                WHERE id = :transfer_id
            ");
            $stmt->execute([
                'credit_amount' => $completionData['credit_amount'] ?? null,
                'credit_reference' => $completionData['credit_reference'] ?? null,
                'completion_notes' => isset($completionData['notes']) ? "\n\nCompleted: " . $completionData['notes'] : '',
                'transfer_id' => $transferId,
            ]);

            // Mark all items as "received" (by supplier)
            $stmt = $this->pdo->prepare("
                UPDATE stock_transfer_items
                SET received_qty = ordered_qty,
                    updated_at = NOW()
                WHERE transfer_id = :transfer_id
            ");
            $stmt->execute(['transfer_id' => $transferId]);

            $this->pdo->commit();

            $this->logger->info('Return to supplier completed', [
                'transfer_id' => $transferId,
                'credit_amount' => $completionData['credit_amount'] ?? null,
            ]);

            return [
                'transfer_id' => $transferId,
                'status' => 'COMPLETED',
                'credit_amount' => $completionData['credit_amount'] ?? null,
            ];

        } catch (\Exception $e) {
            $this->pdo->rollBack();
            $this->logger->error('Failed to complete return to supplier', [
                'transfer_id' => $transferId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Cancel Return to Supplier
     *
     * Cancels return if not yet sent.
     *
     * @param int $transferId Transfer ID
     * @param string $reason Cancellation reason
     * @return array Updated transfer status
     * @throws \RuntimeException if return already sent
     */
    public function cancel(int $transferId, string $reason): array
    {
        $transfer = $this->getTransfer($transferId);

        if ($transfer['status'] !== 'PENDING') {
            throw new \RuntimeException("Cannot cancel return in status: {$transfer['status']}");
        }

        $this->pdo->beginTransaction();
        try {
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

            $this->logger->info('Return to supplier cancelled', [
                'transfer_id' => $transferId,
                'reason' => $reason,
            ]);

            return [
                'transfer_id' => $transferId,
                'status' => 'CANCELLED',
            ];

        } catch (\Exception $e) {
            $this->pdo->rollBack();
            $this->logger->error('Failed to cancel return to supplier', [
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
        if (empty($data['supplier_id'])) {
            throw new \InvalidArgumentException('supplier_id is required');
        }

        if (empty($data['outlet_id'])) {
            throw new \InvalidArgumentException('outlet_id is required');
        }

        if (empty($data['return_reason'])) {
            throw new \InvalidArgumentException('return_reason is required');
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
     * Build Lightspeed consignment payload for return
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
                'count' => -$item['ordered_qty'], // Negative for returns
            ];
        }

        return [
            'consignment' => [
                'outlet_id' => $transfer['outlet_id'],
                'supplier_id' => $transfer['supplier_id'],
                'consignment_products' => $consignmentProducts,
                'name' => "RTS #{$transfer['id']} - {$transfer['return_reason']}",
                'type' => 'RETURN',
            ],
        ];
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
}
