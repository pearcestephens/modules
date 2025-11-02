<?php
/**
 * Outlet Transfer Service
 *
 * Domain service for store-to-store (outlet-to-outlet) transfers.
 * Handles outlet transfer business rules and workflows.
 *
 * Business Rules:
 * - Source outlet must have sufficient stock
 * - Transfers > $2,000 require approval
 * - Creates Lightspeed consignment on send
 * - Auto-updates stock levels on receive
 * - Full audit trail in transfer_audit_log
 *
 * Workflow States:
 * DRAFT → APPROVED → SENT → RECEIVED → COMPLETED
 *
 * @package Consignments\Domain\Services
 */

declare(strict_types=1);

namespace Consignments\Domain\Services;

use Consignments\Domain\ValueObjects\Status;
use Consignments\Infra\Lightspeed\LightspeedClient;
use Psr\Log\LoggerInterface;

class OutletTransferService
{
    private \PDO $pdo;
    private LightspeedClient $client;
    private LoggerInterface $logger;

    /** Approval threshold in dollars */
    private const APPROVAL_THRESHOLD = 2000.00;

    public function __construct(\PDO $pdo, LightspeedClient $client, LoggerInterface $logger)
    {
        $this->pdo = $pdo;
        $this->client = $client;
        $this->logger = $logger;
    }

    /**
     * Create new outlet-to-outlet transfer
     *
     * @param array $data Transfer data
     *   - source_outlet_id: int (required)
     *   - dest_outlet_id: int (required)
     *   - items: array (required) [{product_id, quantity, unit_cost}]
     *   - notes: string (optional)
     *   - created_by: int (optional)
     * @return array Created transfer with ID
     * @throws \InvalidArgumentException if validation fails
     */
    public function createTransfer(array $data): array
    {
        $this->validateCreateData($data);

        // Validate stock levels at source
        if (!$this->validateStockLevels($data['source_outlet_id'], $data['items'])) {
            throw new \InvalidArgumentException('Insufficient stock at source outlet');
        }

        $this->pdo->beginTransaction();

        try {
            // Calculate total value
            $totalValue = $this->calculateTotalValue($data['items']);

            // Determine initial status (DRAFT if requires approval, APPROVED otherwise)
            $requiresApproval = $this->requiresApproval($totalValue);
            $initialStatus = $requiresApproval ? 'DRAFT' : 'APPROVED';

            // Insert transfer header
            $stmt = $this->pdo->prepare("
                INSERT INTO stock_transfers (
                    type, source_outlet_id, destination_outlet_id,
                    status, total_value, requires_approval, notes,
                    created_by, created_at
                ) VALUES (
                    'OUTLET_TRANSFER', ?, ?, ?, ?, ?, ?, ?, NOW()
                )
            ");

            $stmt->execute([
                $data['source_outlet_id'],
                $data['dest_outlet_id'],
                $initialStatus,
                $totalValue,
                $requiresApproval ? 1 : 0,
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

            // Reserve stock at source outlet
            $this->reserveStockAtSource($transferId, $data['source_outlet_id'], $data['items']);

            // Audit log
            $this->logAudit($transferId, 'CREATE', null, [
                'status' => $initialStatus,
                'source_outlet_id' => $data['source_outlet_id'],
                'dest_outlet_id' => $data['dest_outlet_id'],
                'total_value' => $totalValue,
                'requires_approval' => $requiresApproval,
                'item_count' => count($data['items'])
            ], $data['created_by'] ?? null);

            $this->pdo->commit();

            $this->logger->info('Outlet Transfer created', [
                'transfer_id' => $transferId,
                'source' => $data['source_outlet_id'],
                'dest' => $data['dest_outlet_id'],
                'total_value' => $totalValue,
                'requires_approval' => $requiresApproval
            ]);

            return [
                'transfer_id' => $transferId,
                'type' => 'OUTLET_TRANSFER',
                'status' => $initialStatus,
                'total_value' => $totalValue,
                'requires_approval' => $requiresApproval,
                'created_at' => date('Y-m-d H:i:s')
            ];

        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            $this->logger->error('Failed to create Outlet Transfer', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Validate stock levels at source outlet
     *
     * @param int $outletId Source outlet ID
     * @param array $items Items to transfer
     * @return bool True if sufficient stock exists
     */
    public function validateStockLevels(int $outletId, array $items): bool
    {
        foreach ($items as $item) {
            $stmt = $this->pdo->prepare("
                SELECT inventory_count
                FROM vend_inventory
                WHERE product_id = ? AND outlet_id = ?
            ");
            $stmt->execute([$item['product_id'], $outletId]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);

            $available = $row ? (int)$row['inventory_count'] : 0;

            if ($available < $item['quantity']) {
                $this->logger->warning('Insufficient stock for transfer', [
                    'outlet_id' => $outletId,
                    'product_id' => $item['product_id'],
                    'required' => $item['quantity'],
                    'available' => $available
                ]);
                return false;
            }
        }

        return true;
    }

    /**
     * Check if transfer requires approval based on total value
     *
     * @param float $totalValue Total transfer value
     * @return bool True if approval required
     */
    public function requiresApproval(float $totalValue): bool
    {
        return $totalValue > self::APPROVAL_THRESHOLD;
    }

    /**
     * Approve transfer (change status DRAFT → APPROVED)
     *
     * @param int $transferId Transfer ID
     * @param int $approverId User ID of approver
     * @return bool True on success
     * @throws \InvalidArgumentException if transfer not in DRAFT status
     */
    public function approve(int $transferId, int $approverId): bool
    {
        $transfer = $this->getTransfer($transferId);

        if ($transfer['status'] !== 'DRAFT') {
            throw new \InvalidArgumentException("Transfer must be in DRAFT status to approve (currently {$transfer['status']})");
        }

        $this->pdo->beginTransaction();

        try {
            $stmt = $this->pdo->prepare("
                UPDATE stock_transfers
                SET status = 'APPROVED',
                    approved_by = ?,
                    approved_at = NOW(),
                    updated_at = NOW()
                WHERE id = ?
            ");

            $stmt->execute([$approverId, $transferId]);

            // Audit log
            $this->logAudit($transferId, 'APPROVE', 'DRAFT', [
                'status' => 'APPROVED',
                'approved_by' => $approverId
            ], $approverId);

            $this->pdo->commit();

            $this->logger->info('Outlet Transfer approved', [
                'transfer_id' => $transferId,
                'approver_id' => $approverId
            ]);

            return true;

        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            $this->logger->error('Failed to approve Outlet Transfer', [
                'transfer_id' => $transferId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Send transfer (creates Lightspeed consignment)
     *
     * @param int $transferId Transfer ID
     * @param array $shippingData Shipping info (optional)
     * @return array Consignment details
     * @throws \InvalidArgumentException if transfer not in APPROVED status
     */
    public function send(int $transferId, array $shippingData = []): array
    {
        $transfer = $this->getTransfer($transferId);

        if ($transfer['status'] !== 'APPROVED') {
            throw new \InvalidArgumentException("Transfer must be in APPROVED status to send (currently {$transfer['status']})");
        }

        $this->pdo->beginTransaction();

        try {
            // Get items
            $items = $this->getTransferItems($transferId);

            // Create consignment in Lightspeed
            $consignmentData = $this->buildConsignmentPayload($transfer, $items, $shippingData);
            $lsConsignment = $this->client->post('/api/2.0/consignments.json', $consignmentData);

            $consignmentId = $lsConsignment['consignment']['id'] ?? null;

            if (!$consignmentId) {
                throw new \RuntimeException('Failed to create Lightspeed consignment');
            }

            // Update transfer with consignment ID and status
            $stmt = $this->pdo->prepare("
                UPDATE stock_transfers
                SET status = 'SENT',
                    lightspeed_consignment_id = ?,
                    sent_at = NOW(),
                    updated_at = NOW()
                WHERE id = ?
            ");

            $stmt->execute([$consignmentId, $transferId]);

            // Deduct stock from source outlet
            $this->deductStockFromSource($transferId, $transfer['source_outlet_id'], $items);

            // Audit log
            $this->logAudit($transferId, 'SEND', 'APPROVED', [
                'status' => 'SENT',
                'lightspeed_consignment_id' => $consignmentId,
                'shipping_data' => $shippingData
            ]);

            $this->pdo->commit();

            $this->logger->info('Outlet Transfer sent', [
                'transfer_id' => $transferId,
                'consignment_id' => $consignmentId
            ]);

            return [
                'transfer_id' => $transferId,
                'consignment_id' => $consignmentId,
                'status' => 'SENT',
                'sent_at' => date('Y-m-d H:i:s')
            ];

        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            $this->logger->error('Failed to send Outlet Transfer', [
                'transfer_id' => $transferId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Receive transfer at destination outlet
     *
     * @param int $transferId Transfer ID
     * @param array $receivedData Received quantities + evidence
     * @return array Receipt details
     * @throws \InvalidArgumentException if transfer not in SENT status
     */
    public function receive(int $transferId, array $receivedData): array
    {
        $transfer = $this->getTransfer($transferId);

        if ($transfer['status'] !== 'SENT') {
            throw new \InvalidArgumentException("Transfer must be in SENT status to receive (currently {$transfer['status']})");
        }

        $this->pdo->beginTransaction();

        try {
            // Update items with received quantities
            $itemStmt = $this->pdo->prepare("
                UPDATE stock_transfer_items
                SET received_qty = ?,
                    variance = ? - ordered_qty,
                    received_at = NOW()
                WHERE id = ?
            ");

            foreach ($receivedData['items'] as $item) {
                $itemStmt->execute([
                    $item['received_qty'],
                    $item['received_qty'],
                    $item['item_id']
                ]);
            }

            // Update transfer status
            $stmt = $this->pdo->prepare("
                UPDATE stock_transfers
                SET status = 'RECEIVED',
                    received_by = ?,
                    received_at = NOW(),
                    updated_at = NOW()
                WHERE id = ?
            ");

            $stmt->execute([
                $receivedData['received_by'] ?? null,
                $transferId
            ]);

            // Add stock to destination outlet
            $this->addStockToDestination($transferId, $transfer['destination_outlet_id'], $receivedData['items']);

            // Store evidence (photos, signatures)
            if (!empty($receivedData['evidence'])) {
                $this->storeEvidence($transferId, $receivedData['evidence']);
            }

            // Trigger Lightspeed receive (with actual quantities)
            $this->triggerLightspeedReceive($transferId, $transfer['lightspeed_consignment_id'], $receivedData['items']);

            // Audit log
            $this->logAudit($transferId, 'RECEIVE', 'SENT', [
                'status' => 'RECEIVED',
                'received_by' => $receivedData['received_by'] ?? null,
                'items' => $receivedData['items'],
                'evidence_count' => count($receivedData['evidence'] ?? [])
            ], $receivedData['received_by'] ?? null);

            $this->pdo->commit();

            $this->logger->info('Outlet Transfer received', [
                'transfer_id' => $transferId,
                'received_by' => $receivedData['received_by'] ?? null
            ]);

            return [
                'transfer_id' => $transferId,
                'status' => 'RECEIVED',
                'received_at' => date('Y-m-d H:i:s')
            ];

        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            $this->logger->error('Failed to receive Outlet Transfer', [
                'transfer_id' => $transferId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    // ===========================
    // PRIVATE HELPER METHODS
    // ===========================

    private function validateCreateData(array $data): void
    {
        $required = ['source_outlet_id', 'dest_outlet_id', 'items'];

        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new \InvalidArgumentException("Missing required field: {$field}");
            }
        }

        if (!is_array($data['items']) || empty($data['items'])) {
            throw new \InvalidArgumentException('Items must be non-empty array');
        }

        if ($data['source_outlet_id'] === $data['dest_outlet_id']) {
            throw new \InvalidArgumentException('Source and destination outlets cannot be the same');
        }

        foreach ($data['items'] as $item) {
            if (empty($item['product_id']) || empty($item['quantity'])) {
                throw new \InvalidArgumentException('Each item must have product_id and quantity');
            }
        }
    }

    private function calculateTotalValue(array $items): float
    {
        $total = 0.0;

        foreach ($items as $item) {
            $unitCost = $item['unit_cost'] ?? 0.00;
            $quantity = $item['quantity'] ?? 0;
            $total += $unitCost * $quantity;
        }

        return round($total, 2);
    }

    private function getTransfer(int $transferId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM stock_transfers WHERE id = ?
        ");
        $stmt->execute([$transferId]);
        $transfer = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$transfer) {
            throw new \InvalidArgumentException("Transfer {$transferId} not found");
        }

        return $transfer;
    }

    private function getTransferItems(int $transferId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM stock_transfer_items WHERE transfer_id = ?
        ");
        $stmt->execute([$transferId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function reserveStockAtSource(int $transferId, int $outletId, array $items): void
    {
        // Update reserved_qty in vend_inventory (optional if table has this column)
        // For now, just log the reservation
        $this->logger->debug('Stock reserved at source', [
            'transfer_id' => $transferId,
            'outlet_id' => $outletId,
            'item_count' => count($items)
        ]);
    }

    private function deductStockFromSource(int $transferId, int $outletId, array $items): void
    {
        foreach ($items as $item) {
            $stmt = $this->pdo->prepare("
                UPDATE vend_inventory
                SET inventory_count = inventory_count - ?
                WHERE product_id = ? AND outlet_id = ?
            ");
            $stmt->execute([$item['ordered_qty'], $item['product_id'], $outletId]);
        }
    }

    private function addStockToDestination(int $transferId, int $outletId, array $items): void
    {
        foreach ($items as $item) {
            $stmt = $this->pdo->prepare("
                UPDATE vend_inventory
                SET inventory_count = inventory_count + ?
                WHERE product_id = ? AND outlet_id = ?
            ");
            $stmt->execute([$item['received_qty'], $item['product_id'], $outletId]);
        }
    }

    private function buildConsignmentPayload(array $transfer, array $items, array $shippingData): array
    {
        return [
            'consignment' => [
                'name' => "Transfer-{$transfer['id']}",
                'outlet_id' => $transfer['destination_outlet_id'],
                'supplier_id' => null, // Outlet transfers don't have suppliers
                'type' => 'OUTLET',
                'products' => array_map(function($item) {
                    return [
                        'product_id' => $item['product_id'],
                        'count' => $item['ordered_qty']
                    ];
                }, $items),
                'tracking_number' => $shippingData['tracking'] ?? null,
                'carrier' => $shippingData['carrier'] ?? null
            ]
        ];
    }

    private function triggerLightspeedReceive(int $transferId, ?string $consignmentId, array $items): void
    {
        if (!$consignmentId) {
            $this->logger->warning('Cannot trigger Lightspeed receive: no consignment ID', [
                'transfer_id' => $transferId
            ]);
            return;
        }

        try {
            // Mark consignment as received in Lightspeed
            $this->client->put("/api/2.0/consignments/{$consignmentId}.json", [
                'consignment' => [
                    'status' => 'RECEIVED',
                    'products' => array_map(function($item) {
                        return [
                            'product_id' => $item['product_id'],
                            'received' => $item['received_qty']
                        ];
                    }, $items)
                ]
            ]);

            $this->logger->info('Lightspeed receive triggered', [
                'transfer_id' => $transferId,
                'consignment_id' => $consignmentId
            ]);

        } catch (\Throwable $e) {
            $this->logger->error('Failed to trigger Lightspeed receive', [
                'transfer_id' => $transferId,
                'consignment_id' => $consignmentId,
                'error' => $e->getMessage()
            ]);
            // Don't throw - receiving should succeed even if LS sync fails
        }
    }

    private function storeEvidence(int $transferId, array $evidence): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO receiving_evidence (
                transfer_id, evidence_type, file_path, file_hash, created_at
            ) VALUES (?, ?, ?, ?, NOW())
        ");

        foreach ($evidence as $item) {
            $stmt->execute([
                $transferId,
                $item['type'], // 'photo' or 'signature'
                $item['file_path'],
                $item['file_hash'] ?? null
            ]);
        }
    }

    private function logAudit(int $transferId, string $action, ?string $oldStatus, array $newData, ?int $userId): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO transfer_audit_log (
                transfer_id, action, old_status, new_data, user_id, created_at
            ) VALUES (?, ?, ?, ?, ?, NOW())
        ");

        $stmt->execute([
            $transferId,
            $action,
            $oldStatus,
            json_encode($newData),
            $userId
        ]);
    }
}
