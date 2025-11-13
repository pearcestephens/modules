<?php
/**
 * Purchase Order Service
 *
 * Domain service for Purchase Order transfer type.
 * Handles PO-specific business rules and workflows.
 *
 * Business Rules:
 * - PO must have supplier
 * - PO must have expected delivery date
 * - Can receive partial quantities
 * - Generates consignment on send
 * - Updates expected stock levels
 *
 * @package Consignments\App\Services
 */

declare(strict_types=1);

namespace Consignments\App\Services;

use Consignments\Domain\ValueObjects\Status;
use Consignments\Infra\Lightspeed\LightspeedClient;
use Psr\Log\LoggerInterface;

class PurchaseOrderService
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
     * Create new Purchase Order
     *
     * @param array $data PO data (supplier_id, outlet_id, items[], expected_date, notes)
     * @return array Created PO with ID
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
                    type, supplier_id, destination_outlet_id,
                    status, expected_delivery_date, notes,
                    created_by, created_at
                ) VALUES (
                    'PURCHASE_ORDER', ?, ?, 'OPEN', ?, ?, ?, NOW()
                )
            ");

            $stmt->execute([
                $data['supplier_id'],
                $data['outlet_id'],
                $data['expected_date'],
                $data['notes'] ?? null,
                $data['created_by'] ?? null
            ]);

            $transferId = (int)$this->pdo->lastInsertId();

            // Insert items
            $itemStmt = $this->pdo->prepare("
                INSERT INTO stock_transfer_items (
                    transfer_id, product_id, ordered_qty, unit_cost
                ) VALUES (?, ?, ?, ?)
            ");

            foreach ($data['items'] as $item) {
                $itemStmt->execute([
                    $transferId,
                    $item['product_id'],
                    $item['quantity'],
                    $item['unit_cost'] ?? 0.00
                ]);
            }

            // Update expected stock levels
            $this->updateExpectedStockLevels($transferId, $data['outlet_id'], $data['items'], 'add');

            $this->pdo->commit();

            $this->logger->info('Purchase Order created', [
                'transfer_id' => $transferId,
                'supplier_id' => $data['supplier_id'],
                'outlet_id' => $data['outlet_id'],
                'item_count' => count($data['items'])
            ]);

            return [
                'transfer_id' => $transferId,
                'type' => 'PURCHASE_ORDER',
                'status' => 'OPEN',
                'created_at' => date('Y-m-d H:i:s')
            ];

        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            $this->logger->error('Failed to create Purchase Order', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Send PO to supplier (creates Lightspeed consignment)
     *
     * @param int $transferId Transfer ID
     * @return array Consignment details
     */
    public function send(int $transferId): array
    {
        $transfer = $this->getTransfer($transferId);

        if ($transfer['type'] !== 'PURCHASE_ORDER') {
            throw new \InvalidArgumentException("Transfer {$transferId} is not a Purchase Order");
        }

        if ($transfer['status'] !== 'OPEN') {
            throw new \InvalidArgumentException("Purchase Order must be in OPEN status to send");
        }

        $this->pdo->beginTransaction();

        try {
            // Get items
            $items = $this->getTransferItems($transferId);

            // Create consignment in Lightspeed
            $consignmentData = $this->buildConsignmentPayload($transfer, $items);
            $lsConsignment = $this->client->post('/api/2.0/consignments.json', $consignmentData);

            $consignmentId = $lsConsignment['consignment']['id'] ?? null;

            if (!$consignmentId) {
                throw new \RuntimeException('Failed to create Lightspeed consignment');
            }

            // Update transfer status
            $stmt = $this->pdo->prepare("
                UPDATE stock_transfers
                SET status = 'SENT',
                    lightspeed_consignment_id = ?,
                    sent_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$consignmentId, $transferId]);

            // Queue job for status tracking
            $this->queueStatusTrackingJob($transferId, $consignmentId);

            $this->pdo->commit();

            $this->logger->info('Purchase Order sent', [
                'transfer_id' => $transferId,
                'consignment_id' => $consignmentId
            ]);

            return [
                'transfer_id' => $transferId,
                'consignment_id' => $consignmentId,
                'status' => 'SENT'
            ];

        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            $this->logger->error('Failed to send Purchase Order', [
                'transfer_id' => $transferId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Receive PO (full or partial)
     *
     * @param int $transferId Transfer ID
     * @param array $items Items received with quantities
     * @return array Receipt details
     */
    public function receive(int $transferId, array $items): array
    {
        $transfer = $this->getTransfer($transferId);

        if ($transfer['status'] !== 'SENT') {
            throw new \InvalidArgumentException("Purchase Order must be SENT before receiving");
        }

        $this->pdo->beginTransaction();

        try {
            // Update received quantities
            foreach ($items as $item) {
                $stmt = $this->pdo->prepare("
                    UPDATE stock_transfer_items
                    SET received_qty = received_qty + ?,
                        last_received_at = NOW()
                    WHERE transfer_id = ? AND product_id = ?
                ");
                $stmt->execute([
                    $item['quantity'],
                    $transferId,
                    $item['product_id']
                ]);

                // Update actual stock levels
                $this->updateActualStockLevel(
                    $transfer['destination_outlet_id'],
                    $item['product_id'],
                    $item['quantity']
                );
            }

            // Check if fully received
            $fullyReceived = $this->isFullyReceived($transferId);
            $newStatus = $fullyReceived ? 'RECEIVED' : 'PARTIALLY_RECEIVED';

            $stmt = $this->pdo->prepare("
                UPDATE stock_transfers
                SET status = ?,
                    received_at = CASE WHEN ? = 'RECEIVED' THEN NOW() ELSE received_at END
                WHERE id = ?
            ");
            $stmt->execute([$newStatus, $newStatus, $transferId]);

            // Update expected stock (remove received qty)
            $this->updateExpectedStockLevels(
                $transferId,
                $transfer['destination_outlet_id'],
                $items,
                'subtract'
            );

            $this->pdo->commit();

            $this->logger->info('Purchase Order received', [
                'transfer_id' => $transferId,
                'status' => $newStatus,
                'items_received' => count($items)
            ]);

            return [
                'transfer_id' => $transferId,
                'status' => $newStatus,
                'fully_received' => $fullyReceived
            ];

        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Cancel PO (only if not sent)
     */
    public function cancel(int $transferId, string $reason): array
    {
        $transfer = $this->getTransfer($transferId);

        if (!in_array($transfer['status'], ['OPEN', 'DRAFT'])) {
            throw new \InvalidArgumentException("Cannot cancel Purchase Order in {$transfer['status']} status");
        }

        $this->pdo->beginTransaction();

        try {
            $stmt = $this->pdo->prepare("
                UPDATE stock_transfers
                SET status = 'CANCELLED',
                    cancellation_reason = ?,
                    cancelled_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$reason, $transferId]);

            // Revert expected stock levels
            $items = $this->getTransferItems($transferId);
            $this->updateExpectedStockLevels(
                $transferId,
                $transfer['destination_outlet_id'],
                $items,
                'subtract'
            );

            $this->pdo->commit();

            $this->logger->info('Purchase Order cancelled', [
                'transfer_id' => $transferId,
                'reason' => $reason
            ]);

            return ['transfer_id' => $transferId, 'status' => 'CANCELLED'];

        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    // ========================================================================
    // Private Helper Methods
    // ========================================================================

    private function validateCreateData(array $data): void
    {
        $required = ['supplier_id', 'outlet_id', 'items', 'expected_date'];
        foreach ($required as $field) {
            if (!isset($data[$field])) {
                throw new \InvalidArgumentException("Missing required field: {$field}");
            }
        }

        if (empty($data['items'])) {
            throw new \InvalidArgumentException("Purchase Order must have at least one item");
        }

        foreach ($data['items'] as $item) {
            if (!isset($item['product_id']) || !isset($item['quantity'])) {
                throw new \InvalidArgumentException("Each item must have product_id and quantity");
            }

            if ($item['quantity'] <= 0) {
                throw new \InvalidArgumentException("Item quantity must be positive");
            }
        }
    }

    private function getTransfer(int $transferId): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM stock_transfers WHERE id = ?");
        $stmt->execute([$transferId]);
        $transfer = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$transfer) {
            throw new \RuntimeException("Transfer {$transferId} not found");
        }

        return $transfer;
    }

    private function getTransferItems(int $transferId): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM stock_transfer_items WHERE transfer_id = ?");
        $stmt->execute([$transferId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function buildConsignmentPayload(array $transfer, array $items): array
    {
        $lineItems = [];
        foreach ($items as $item) {
            $lineItems[] = [
                'product_id' => $item['product_id'],
                'quantity' => $item['ordered_qty'],
                'cost' => $item['unit_cost'] ?? 0.00
            ];
        }

        return [
            'consignment' => [
                'outlet_id' => $transfer['destination_outlet_id'],
                'supplier_id' => $transfer['supplier_id'],
                'status' => 'RECEIVED', // Lightspeed status
                'due_at' => $transfer['expected_delivery_date'],
                'consignment_products' => $lineItems
            ]
        ];
    }

    private function updateExpectedStockLevels(int $transferId, int $outletId, array $items, string $operation): void
    {
        foreach ($items as $item) {
            $modifier = ($operation === 'add') ? '+' : '-';
            $stmt = $this->pdo->prepare("
                UPDATE vend_inventory
                SET expected_stock = expected_stock {$modifier} ?
                WHERE outlet_id = ? AND product_id = ?
            ");
            $stmt->execute([
                $item['quantity'],
                $outletId,
                $item['product_id']
            ]);
        }
    }

    private function updateActualStockLevel(int $outletId, string $productId, int $quantity): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE vend_inventory
            SET inventory_count = inventory_count + ?
            WHERE outlet_id = ? AND product_id = ?
        ");
        $stmt->execute([$quantity, $outletId, $productId]);
    }

    private function isFullyReceived(int $transferId): bool
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as incomplete
            FROM stock_transfer_items
            WHERE transfer_id = ? AND received_qty < ordered_qty
        ");
        $stmt->execute([$transferId]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        return ($result['incomplete'] ?? 1) === 0;
    }

    private function queueStatusTrackingJob(int $transferId, string $consignmentId): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO queue_jobs (job_type, payload, priority, status)
            VALUES ('transfer.track_status', ?, 5, 'pending')
        ");
        $stmt->execute([
            json_encode([
                'transfer_id' => $transferId,
                'consignment_id' => $consignmentId
            ])
        ]);
    }
}
