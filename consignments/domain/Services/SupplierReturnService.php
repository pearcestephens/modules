<?php
/**
 * Supplier Return Service
 *
 * Domain service for returning stock to suppliers.
 * Handles return business rules and workflows.
 *
 * Business Rules:
 * - Returns must have a reason (DAMAGED, INCORRECT, OVERSTOCK, WARRANTY)
 * - Photo evidence required for DAMAGED returns
 * - Refund/credit note tracking
 * - Creates negative consignment in Lightspeed (if supported)
 * - Full audit trail
 *
 * Workflow States:
 * DRAFT → APPROVED → SHIPPED → COMPLETED (with refund)
 *
 * @package Consignments\Domain\Services
 */

declare(strict_types=1);

namespace Consignments\Domain\Services;

use Consignments\Infra\Lightspeed\LightspeedClient;
use Psr\Log\LoggerInterface;

class SupplierReturnService
{
    private \PDO $pdo;
    private LightspeedClient $client;
    private LoggerInterface $logger;

    /** Valid return reasons */
    private const VALID_REASONS = ['DAMAGED', 'INCORRECT', 'OVERSTOCK', 'WARRANTY', 'OTHER'];

    public function __construct(\PDO $pdo, LightspeedClient $client, LoggerInterface $logger)
    {
        $this->pdo = $pdo;
        $this->client = $client;
        $this->logger = $logger;
    }

    /**
     * Create new supplier return
     *
     * @param array $data Return data
     *   - supplier_id: int (required)
     *   - outlet_id: int (required)
     *   - reason: string (required) - one of VALID_REASONS
     *   - items: array (required) [{product_id, quantity, unit_cost, reason_notes}]
     *   - notes: string (optional)
     *   - created_by: int (optional)
     * @return array Created return with ID
     * @throws \InvalidArgumentException if validation fails
     */
    public function createReturn(array $data): array
    {
        $this->validateCreateData($data);

        $this->pdo->beginTransaction();

        try {
            // Calculate total value
            $totalValue = $this->calculateTotalValue($data['items']);

            // Insert return header
            $stmt = $this->pdo->prepare("
                INSERT INTO supplier_returns (
                    supplier_id, outlet_id, reason, status, total_value,
                    notes, created_by, created_at
                ) VALUES (?, ?, ?, 'DRAFT', ?, ?, ?, NOW())
            ");

            $stmt->execute([
                $data['supplier_id'],
                $data['outlet_id'],
                $data['reason'],
                $totalValue,
                $data['notes'] ?? null,
                $data['created_by'] ?? null
            ]);

            $returnId = (int)$this->pdo->lastInsertId();

            // Insert items
            $itemStmt = $this->pdo->prepare("
                INSERT INTO supplier_return_items (
                    return_id, product_id, quantity, unit_cost, reason_notes
                ) VALUES (?, ?, ?, ?, ?)
            ");

            foreach ($data['items'] as $item) {
                $itemStmt->execute([
                    $returnId,
                    $item['product_id'],
                    $item['quantity'],
                    $item['unit_cost'] ?? 0.00,
                    $item['reason_notes'] ?? null
                ]);
            }

            // Audit log
            $this->logAudit($returnId, 'CREATE', null, [
                'status' => 'DRAFT',
                'supplier_id' => $data['supplier_id'],
                'outlet_id' => $data['outlet_id'],
                'reason' => $data['reason'],
                'total_value' => $totalValue,
                'item_count' => count($data['items'])
            ], $data['created_by'] ?? null);

            $this->pdo->commit();

            $this->logger->info('Supplier Return created', [
                'return_id' => $returnId,
                'supplier_id' => $data['supplier_id'],
                'reason' => $data['reason'],
                'total_value' => $totalValue
            ]);

            return [
                'return_id' => $returnId,
                'type' => 'SUPPLIER_RETURN',
                'status' => 'DRAFT',
                'reason' => $data['reason'],
                'total_value' => $totalValue,
                'created_at' => date('Y-m-d H:i:s')
            ];

        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            $this->logger->error('Failed to create Supplier Return', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Add return item to existing return
     *
     * @param int $returnId Return ID
     * @param array $item Item data
     * @return array Added item with ID
     * @throws \InvalidArgumentException if return not in DRAFT status
     */
    public function addReturnItem(int $returnId, array $item): array
    {
        $return = $this->getReturn($returnId);

        if ($return['status'] !== 'DRAFT') {
            throw new \InvalidArgumentException("Can only add items to DRAFT returns (currently {$return['status']})");
        }

        $this->validateItemData($item);

        $stmt = $this->pdo->prepare("
            INSERT INTO supplier_return_items (
                return_id, product_id, quantity, unit_cost, reason_notes
            ) VALUES (?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $returnId,
            $item['product_id'],
            $item['quantity'],
            $item['unit_cost'] ?? 0.00,
            $item['reason_notes'] ?? null
        ]);

        $itemId = (int)$this->pdo->lastInsertId();

        // Update total value
        $this->recalculateTotalValue($returnId);

        $this->logger->info('Item added to Supplier Return', [
            'return_id' => $returnId,
            'item_id' => $itemId,
            'product_id' => $item['product_id']
        ]);

        return [
            'item_id' => $itemId,
            'return_id' => $returnId,
            'product_id' => $item['product_id'],
            'quantity' => $item['quantity']
        ];
    }

    /**
     * Attach photo evidence to return
     *
     * @param int $returnId Return ID
     * @param string $photoUrl URL or path to photo
     * @return bool True on success
     */
    public function attachEvidence(int $returnId, string $photoUrl): bool
    {
        $return = $this->getReturn($returnId);

        // Photo evidence especially important for DAMAGED returns
        if ($return['reason'] === 'DAMAGED' && empty($photoUrl)) {
            throw new \InvalidArgumentException('Photo evidence required for DAMAGED returns');
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO supplier_return_evidence (
                return_id, evidence_type, file_path, created_at
            ) VALUES (?, 'photo', ?, NOW())
        ");

        $stmt->execute([$returnId, $photoUrl]);

        $this->logger->info('Evidence attached to Supplier Return', [
            'return_id' => $returnId,
            'photo_url' => $photoUrl
        ]);

        return true;
    }

    /**
     * Approve return (change status DRAFT → APPROVED)
     *
     * @param int $returnId Return ID
     * @param int $approverId User ID of approver
     * @return bool True on success
     * @throws \InvalidArgumentException if return not in DRAFT status
     */
    public function approve(int $returnId, int $approverId): bool
    {
        $return = $this->getReturn($returnId);

        if ($return['status'] !== 'DRAFT') {
            throw new \InvalidArgumentException("Return must be in DRAFT status to approve (currently {$return['status']})");
        }

        // Validate DAMAGED returns have photo evidence
        if ($return['reason'] === 'DAMAGED') {
            $evidenceCount = $this->getEvidenceCount($returnId);
            if ($evidenceCount === 0) {
                throw new \InvalidArgumentException('DAMAGED returns require photo evidence before approval');
            }
        }

        $this->pdo->beginTransaction();

        try {
            $stmt = $this->pdo->prepare("
                UPDATE supplier_returns
                SET status = 'APPROVED',
                    approved_by = ?,
                    approved_at = NOW(),
                    updated_at = NOW()
                WHERE id = ?
            ");

            $stmt->execute([$approverId, $returnId]);

            // Audit log
            $this->logAudit($returnId, 'APPROVE', 'DRAFT', [
                'status' => 'APPROVED',
                'approved_by' => $approverId
            ], $approverId);

            $this->pdo->commit();

            $this->logger->info('Supplier Return approved', [
                'return_id' => $returnId,
                'approver_id' => $approverId
            ]);

            return true;

        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            $this->logger->error('Failed to approve Supplier Return', [
                'return_id' => $returnId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Ship return to supplier
     *
     * @param int $returnId Return ID
     * @param array $shippingData Tracking info
     * @return array Shipping details
     * @throws \InvalidArgumentException if return not in APPROVED status
     */
    public function ship(int $returnId, array $shippingData = []): array
    {
        $return = $this->getReturn($returnId);

        if ($return['status'] !== 'APPROVED') {
            throw new \InvalidArgumentException("Return must be in APPROVED status to ship (currently {$return['status']})");
        }

        $this->pdo->beginTransaction();

        try {
            // Update status and shipping info
            $stmt = $this->pdo->prepare("
                UPDATE supplier_returns
                SET status = 'SHIPPED',
                    tracking_number = ?,
                    carrier = ?,
                    shipped_at = NOW(),
                    updated_at = NOW()
                WHERE id = ?
            ");

            $stmt->execute([
                $shippingData['tracking'] ?? null,
                $shippingData['carrier'] ?? null,
                $returnId
            ]);

            // Deduct stock from outlet
            $items = $this->getReturnItems($returnId);
            $this->deductStockFromOutlet($returnId, $return['outlet_id'], $items);

            // Audit log
            $this->logAudit($returnId, 'SHIP', 'APPROVED', [
                'status' => 'SHIPPED',
                'tracking' => $shippingData['tracking'] ?? null,
                'carrier' => $shippingData['carrier'] ?? null
            ]);

            $this->pdo->commit();

            $this->logger->info('Supplier Return shipped', [
                'return_id' => $returnId,
                'tracking' => $shippingData['tracking'] ?? null
            ]);

            return [
                'return_id' => $returnId,
                'status' => 'SHIPPED',
                'tracking' => $shippingData['tracking'] ?? null,
                'shipped_at' => date('Y-m-d H:i:s')
            ];

        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            $this->logger->error('Failed to ship Supplier Return', [
                'return_id' => $returnId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Process refund for return
     *
     * @param int $returnId Return ID
     * @param float $amount Refund amount
     * @return bool True on success
     */
    public function processRefund(int $returnId, float $amount): bool
    {
        $return = $this->getReturn($returnId);

        if ($return['status'] !== 'SHIPPED') {
            throw new \InvalidArgumentException("Return must be in SHIPPED status to process refund (currently {$return['status']})");
        }

        $this->pdo->beginTransaction();

        try {
            $stmt = $this->pdo->prepare("
                UPDATE supplier_returns
                SET status = 'COMPLETED',
                    refund_amount = ?,
                    refunded_at = NOW(),
                    updated_at = NOW()
                WHERE id = ?
            ");

            $stmt->execute([$amount, $returnId]);

            // Audit log
            $this->logAudit($returnId, 'REFUND', 'SHIPPED', [
                'status' => 'COMPLETED',
                'refund_amount' => $amount
            ]);

            $this->pdo->commit();

            $this->logger->info('Supplier Return refund processed', [
                'return_id' => $returnId,
                'refund_amount' => $amount
            ]);

            return true;

        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            $this->logger->error('Failed to process refund for Supplier Return', [
                'return_id' => $returnId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Update return status
     *
     * @param int $returnId Return ID
     * @param string $status New status
     * @return bool True on success
     */
    public function updateStatus(int $returnId, string $status): bool
    {
        $return = $this->getReturn($returnId);
        $oldStatus = $return['status'];

        $stmt = $this->pdo->prepare("
            UPDATE supplier_returns
            SET status = ?,
                updated_at = NOW()
            WHERE id = ?
        ");

        $stmt->execute([$status, $returnId]);

        // Audit log
        $this->logAudit($returnId, 'STATUS_UPDATE', $oldStatus, [
            'status' => $status
        ]);

        $this->logger->info('Supplier Return status updated', [
            'return_id' => $returnId,
            'old_status' => $oldStatus,
            'new_status' => $status
        ]);

        return true;
    }

    // ===========================
    // PRIVATE HELPER METHODS
    // ===========================

    private function validateCreateData(array $data): void
    {
        $required = ['supplier_id', 'outlet_id', 'reason', 'items'];

        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new \InvalidArgumentException("Missing required field: {$field}");
            }
        }

        if (!in_array($data['reason'], self::VALID_REASONS, true)) {
            throw new \InvalidArgumentException('Invalid return reason. Must be one of: ' . implode(', ', self::VALID_REASONS));
        }

        if (!is_array($data['items']) || empty($data['items'])) {
            throw new \InvalidArgumentException('Items must be non-empty array');
        }

        foreach ($data['items'] as $item) {
            $this->validateItemData($item);
        }
    }

    private function validateItemData(array $item): void
    {
        if (empty($item['product_id']) || empty($item['quantity'])) {
            throw new \InvalidArgumentException('Each item must have product_id and quantity');
        }

        if ($item['quantity'] <= 0) {
            throw new \InvalidArgumentException('Quantity must be positive');
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

    private function recalculateTotalValue(int $returnId): void
    {
        $items = $this->getReturnItems($returnId);
        $totalValue = $this->calculateTotalValue($items);

        $stmt = $this->pdo->prepare("
            UPDATE supplier_returns
            SET total_value = ?
            WHERE id = ?
        ");

        $stmt->execute([$totalValue, $returnId]);
    }

    private function getReturn(int $returnId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM supplier_returns WHERE id = ?
        ");
        $stmt->execute([$returnId]);
        $return = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$return) {
            throw new \InvalidArgumentException("Supplier Return {$returnId} not found");
        }

        return $return;
    }

    private function getReturnItems(int $returnId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM supplier_return_items WHERE return_id = ?
        ");
        $stmt->execute([$returnId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function getEvidenceCount(int $returnId): int
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM supplier_return_evidence WHERE return_id = ?
        ");
        $stmt->execute([$returnId]);
        return (int)$stmt->fetchColumn();
    }

    private function deductStockFromOutlet(int $returnId, int $outletId, array $items): void
    {
        foreach ($items as $item) {
            $stmt = $this->pdo->prepare("
                UPDATE vend_inventory
                SET inventory_count = inventory_count - ?
                WHERE product_id = ? AND outlet_id = ?
            ");
            $stmt->execute([$item['quantity'], $item['product_id'], $outletId]);
        }

        $this->logger->debug('Stock deducted from outlet for return', [
            'return_id' => $returnId,
            'outlet_id' => $outletId,
            'item_count' => count($items)
        ]);
    }

    private function logAudit(int $returnId, string $action, ?string $oldStatus, array $newData, ?int $userId = null): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO supplier_return_audit_log (
                return_id, action, old_status, new_data, user_id, created_at
            ) VALUES (?, ?, ?, ?, ?, NOW())
        ");

        $stmt->execute([
            $returnId,
            $action,
            $oldStatus,
            json_encode($newData),
            $userId
        ]);
    }
}
