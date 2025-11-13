<?php
declare(strict_types=1);

/**
 * Receiving Service
 *
 * Handles goods receipt workflow for purchase orders.
 *
 * Key Responsibilities:
 * - Start receiving process
 * - Record received quantities (full/partial)
 * - Handle variances (over/under delivery)
 * - Damage/defect tracking
 * - Update inventory levels
 * - Complete receiving workflow
 * - Barcode scanning support
 *
 * @package CIS\Consignments\Services
 * @version 1.0.0
 */

namespace CIS\Consignments\Services;

use PDO;
use PDOException;
use RuntimeException;
use InvalidArgumentException;

class ReceivingService
{
    private PDO $pdo;

    /**
     * Valid receiving statuses
     */
    private const VALID_STATUSES = [
        'pending',
        'sent',
        'received',
        'partial',
        'damaged',
        'cancelled'
    ];

    /**
     * Constructor
     */
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Start receiving process for a purchase order
     *
     * @param int $poId Purchase order ID
     * @param int $userId User starting the receiving process
     * @return array Receiving session details
     */
    public function startReceiving(int $poId, int $userId): array
    {
        // Verify PO is in SENT state
        $stmt = $this->pdo->prepare("
            SELECT id, public_id, state, outlet_to
            FROM vend_consignments
            WHERE id = ? AND transfer_category = 'PURCHASE_ORDER'
        ");
        $stmt->execute([$poId]);
        $po = $stmt->fetch(PDO::FETCH_OBJ);

        if (!$po) {
            throw new InvalidArgumentException("Purchase order not found: $poId");
        }

        if ($po->state !== 'SENT') {
            throw new RuntimeException("Purchase order must be in SENT state to begin receiving (current: {$po->state})");
        }

        $this->pdo->beginTransaction();

        try {
            // Update PO state to RECEIVING
            $stmt = $this->pdo->prepare("
                UPDATE vend_consignments
                SET state = 'RECEIVING',
                    receiving_started_at = NOW(),
                    receiving_started_by = :user_id,
                    updated_at = NOW()
                WHERE id = :po_id
            ");
            $stmt->execute([
                ':user_id' => $userId,
                ':po_id' => $poId
            ]);

            // Create receiving session
            $stmt = $this->pdo->prepare("
                INSERT INTO receiving_sessions (
                    transfer_id,
                    started_by,
                    outlet_id,
                    status,
                    created_at
                ) VALUES (?, ?, ?, 'active', NOW())
            ");
            $stmt->execute([$poId, $userId, $po->outlet_to]);

            $sessionId = (int)$this->pdo->lastInsertId();

            // Get line items to receive
            $stmt = $this->pdo->prepare("
                SELECT
                    id,
                    product_id,
                    sku,
                    name,
                    quantity,
                    quantity_sent,
                    quantity_received,
                    unit_cost
                FROM vend_consignment_line_items
                WHERE transfer_id = ? AND deleted_at IS NULL
                ORDER BY name ASC
            ");
            $stmt->execute([$poId]);
            $lineItems = $stmt->fetchAll(PDO::FETCH_OBJ);

            // Log start
            $this->logAudit($poId, 'receiving_started', 'RECEIVING', [
                'actor_id' => $userId,
                'session_id' => $sessionId
            ]);

            $this->pdo->commit();

            return [
                'session_id' => $sessionId,
                'po_id' => $poId,
                'po_public_id' => $po->public_id,
                'line_items' => $lineItems,
                'total_items' => count($lineItems)
            ];

        } catch (PDOException $e) {
            $this->pdo->rollBack();
            throw new RuntimeException("Failed to start receiving: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Receive item (record quantity received)
     *
     * @param int $lineItemId Line item ID
     * @param int $quantityReceived Quantity received
     * @param int $userId User receiving the item
     * @param array $options Additional options
     *   - damaged: int (quantity damaged)
     *   - notes: string (receiving notes)
     *   - barcode_scanned: bool (if barcode was scanned)
     * @return array Updated line item details
     */
    public function receiveItem(int $lineItemId, int $quantityReceived, int $userId, array $options = []): array
    {
        // Get line item and PO details
        $stmt = $this->pdo->prepare("
            SELECT
                vcli.*,
                vc.id AS po_id,
                vc.state AS po_state,
                vc.outlet_to
            FROM vend_consignment_line_items vcli
            JOIN vend_consignments vc ON vcli.transfer_id = vc.id
            WHERE vcli.id = ?
        ");
        $stmt->execute([$lineItemId]);
        $lineItem = $stmt->fetch(PDO::FETCH_OBJ);

        if (!$lineItem) {
            throw new InvalidArgumentException("Line item not found: $lineItemId");
        }

        if ($lineItem->po_state !== 'RECEIVING') {
            throw new RuntimeException("Purchase order must be in RECEIVING state");
        }

        // Validate quantities
        if ($quantityReceived < 0) {
            throw new InvalidArgumentException("Quantity received cannot be negative");
        }

        $quantityDamaged = max(0, (int)($options['damaged'] ?? 0));
        $totalReceived = $lineItem->quantity_received + $quantityReceived;

        if ($totalReceived > $lineItem->quantity) {
            // Allow over-delivery but log warning
            $this->logAudit($lineItem->po_id, 'over_delivery', 'RECEIVING', [
                'line_item_id' => $lineItemId,
                'expected' => $lineItem->quantity,
                'received' => $totalReceived,
                'excess' => $totalReceived - $lineItem->quantity
            ]);
        }

        $this->pdo->beginTransaction();

        try {
            // Update line item
            $stmt = $this->pdo->prepare("
                UPDATE vend_consignment_line_items
                SET quantity_received = quantity_received + :qty_received,
                    quantity_damaged = quantity_damaged + :qty_damaged,
                    receiving_notes = CONCAT(COALESCE(receiving_notes, ''), :notes),
                    status = CASE
                        WHEN (quantity_received + :qty_received) >= quantity THEN 'received'
                        WHEN (quantity_received + :qty_received) > 0 THEN 'partial'
                        ELSE 'pending'
                    END,
                    last_received_at = NOW(),
                    last_received_by = :user_id,
                    updated_at = NOW()
                WHERE id = :line_item_id
            ");

            $notes = !empty($options['notes']) ? "\n[" . date('Y-m-d H:i:s') . "] " . $options['notes'] : '';

            $stmt->execute([
                ':qty_received' => $quantityReceived,
                ':qty_damaged' => $quantityDamaged,
                ':notes' => $notes,
                ':user_id' => $userId,
                ':line_item_id' => $lineItemId
            ]);

            // Record receiving event
            $stmt = $this->pdo->prepare("
                INSERT INTO receiving_events (
                    line_item_id,
                    transfer_id,
                    quantity_received,
                    quantity_damaged,
                    received_by,
                    barcode_scanned,
                    notes,
                    created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $lineItemId,
                $lineItem->po_id,
                $quantityReceived,
                $quantityDamaged,
                $userId,
                (int)($options['barcode_scanned'] ?? false),
                $options['notes'] ?? null
            ]);

            // Get updated line item
            $stmt = $this->pdo->prepare("
                SELECT * FROM vend_consignment_line_items WHERE id = ?
            ");
            $stmt->execute([$lineItemId]);
            $updated = $stmt->fetch(PDO::FETCH_ASSOC);

            $this->pdo->commit();

            return $updated;

        } catch (PDOException $e) {
            $this->pdo->rollBack();
            throw new RuntimeException("Failed to receive item: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Complete receiving process
     *
     * @param int $poId Purchase order ID
     * @param int $userId User completing the receiving
     * @param bool $partialComplete True if completing with partial receipt
     * @return array Completion summary
     */
    public function completeReceiving(int $poId, int $userId, bool $partialComplete = false): array
    {
        // Get PO and line items status
        $stmt = $this->pdo->prepare("
            SELECT
                vc.id,
                vc.public_id,
                vc.state,
                vc.outlet_to,
                COUNT(vcli.id) AS total_items,
                SUM(CASE WHEN vcli.status = 'received' THEN 1 ELSE 0 END) AS received_items,
                SUM(CASE WHEN vcli.status = 'partial' THEN 1 ELSE 0 END) AS partial_items,
                SUM(vcli.quantity) AS total_quantity,
                SUM(vcli.quantity_received) AS received_quantity,
                SUM(vcli.quantity_damaged) AS damaged_quantity
            FROM vend_consignments vc
            LEFT JOIN vend_consignment_line_items vcli ON vc.id = vcli.transfer_id AND vcli.deleted_at IS NULL
            WHERE vc.id = ? AND vc.transfer_category = 'PURCHASE_ORDER'
            GROUP BY vc.id
        ");
        $stmt->execute([$poId]);
        $po = $stmt->fetch(PDO::FETCH_OBJ);

        if (!$po) {
            throw new InvalidArgumentException("Purchase order not found: $poId");
        }

        if ($po->state !== 'RECEIVING') {
            throw new RuntimeException("Purchase order must be in RECEIVING state");
        }

        // Determine completion state
        $isFullyReceived = $po->received_items === $po->total_items;
        $isPartiallyReceived = $po->partial_items > 0 || $po->received_items > 0;

        if (!$isPartiallyReceived && !$partialComplete) {
            throw new RuntimeException("No items have been received yet");
        }

        $newState = $isFullyReceived ? 'RECEIVED' : 'PARTIAL';

        if ($partialComplete && !$isFullyReceived) {
            // User is explicitly completing with partial receipt
            $newState = 'PARTIAL';
        }

        $this->pdo->beginTransaction();

        try {
            // Update PO state
            $stmt = $this->pdo->prepare("
                UPDATE vend_consignments
                SET state = :new_state,
                    received_at = NOW(),
                    received_by = :user_id,
                    receiving_completed_at = NOW(),
                    updated_at = NOW()
                WHERE id = :po_id
            ");
            $stmt->execute([
                ':new_state' => $newState,
                ':user_id' => $userId,
                ':po_id' => $poId
            ]);

            // Close receiving session
            $stmt = $this->pdo->prepare("
                UPDATE receiving_sessions
                SET status = 'completed',
                    completed_at = NOW(),
                    completed_by = :user_id
                WHERE transfer_id = :po_id
                  AND status = 'active'
            ");
            $stmt->execute([
                ':user_id' => $userId,
                ':po_id' => $poId
            ]);

            // Update inventory if fully or partially received
            if ($isPartiallyReceived) {
                $this->updateInventory($poId, $po->outlet_to);
            }

            // Create completion summary
            $summary = [
                'po_id' => $poId,
                'public_id' => $po->public_id,
                'final_state' => $newState,
                'total_items' => (int)$po->total_items,
                'received_items' => (int)$po->received_items,
                'partial_items' => (int)$po->partial_items,
                'total_quantity' => (int)$po->total_quantity,
                'received_quantity' => (int)$po->received_quantity,
                'damaged_quantity' => (int)$po->damaged_quantity,
                'receipt_rate' => $po->total_quantity > 0
                    ? round(($po->received_quantity / $po->total_quantity) * 100, 2)
                    : 0,
                'has_variances' => $po->received_quantity !== $po->total_quantity || $po->damaged_quantity > 0
            ];

            // Log completion
            $this->logAudit($poId, 'receiving_completed', $newState, [
                'actor_id' => $userId,
                'summary' => $summary
            ]);

            $this->pdo->commit();

            return $summary;

        } catch (PDOException $e) {
            $this->pdo->rollBack();
            throw new RuntimeException("Failed to complete receiving: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Update inventory levels based on received goods
     *
     * @param int $poId Purchase order ID
     * @param string $outletId Outlet receiving goods
     * @return int Number of inventory records updated
     */
    public function updateInventory(int $poId, string $outletId): int
    {
        // Get received line items
        $stmt = $this->pdo->prepare("
            SELECT
                product_id,
                quantity_received,
                quantity_damaged
            FROM vend_consignment_line_items
            WHERE transfer_id = ?
              AND quantity_received > 0
              AND deleted_at IS NULL
        ");
        $stmt->execute([$poId]);
        $items = $stmt->fetchAll(PDO::FETCH_OBJ);

        if (empty($items)) {
            return 0;
        }

        $updated = 0;

        foreach ($items as $item) {
            // Calculate net quantity (received - damaged)
            $netQuantity = $item->quantity_received - $item->quantity_damaged;

            if ($netQuantity <= 0) {
                continue; // Nothing to add to inventory
            }

            try {
                // Update vend_inventory
                $stmt = $this->pdo->prepare("
                    INSERT INTO vend_inventory (
                        outlet_id,
                        product_id,
                        count,
                        updated_at
                    ) VALUES (
                        :outlet_id,
                        :product_id,
                        :quantity,
                        NOW()
                    )
                    ON DUPLICATE KEY UPDATE
                        count = count + :quantity,
                        updated_at = NOW()
                ");

                $stmt->execute([
                    ':outlet_id' => $outletId,
                    ':product_id' => $item->product_id,
                    ':quantity' => $netQuantity
                ]);

                $updated++;

            } catch (PDOException $e) {
                // Log error but continue with other items
                error_log("Failed to update inventory for product {$item->product_id}: " . $e->getMessage());
            }
        }

        return $updated;
    }

    /**
     * Get receiving progress for a purchase order
     *
     * @param int $poId Purchase order ID
     * @return array Progress details
     */
    public function getReceivingProgress(int $poId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                vcli.id,
                vcli.product_id,
                vcli.sku,
                vcli.name,
                vcli.quantity AS quantity_ordered,
                vcli.quantity_sent,
                vcli.quantity_received,
                vcli.quantity_damaged,
                vcli.status,
                vcli.last_received_at,
                u.full_name AS last_received_by_name
            FROM vend_consignment_line_items vcli
            LEFT JOIN users u ON vcli.last_received_by = u.id
            WHERE vcli.transfer_id = ?
              AND vcli.deleted_at IS NULL
            ORDER BY
                CASE vcli.status
                    WHEN 'pending' THEN 1
                    WHEN 'partial' THEN 2
                    WHEN 'received' THEN 3
                    ELSE 4
                END,
                vcli.name ASC
        ");

        $stmt->execute([$poId]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Calculate summary
        $totalOrdered = array_sum(array_column($items, 'quantity_ordered'));
        $totalReceived = array_sum(array_column($items, 'quantity_received'));
        $totalDamaged = array_sum(array_column($items, 'quantity_damaged'));

        return [
            'items' => $items,
            'summary' => [
                'total_items' => count($items),
                'items_pending' => count(array_filter($items, fn($i) => $i['status'] === 'pending')),
                'items_partial' => count(array_filter($items, fn($i) => $i['status'] === 'partial')),
                'items_received' => count(array_filter($items, fn($i) => $i['status'] === 'received')),
                'total_ordered' => $totalOrdered,
                'total_received' => $totalReceived,
                'total_damaged' => $totalDamaged,
                'receipt_percentage' => $totalOrdered > 0 ? round(($totalReceived / $totalOrdered) * 100, 2) : 0
            ]
        ];
    }

    /**
     * Record damage or defect
     *
     * @param int $lineItemId Line item ID
     * @param int $quantity Quantity damaged
     * @param string $reason Damage reason
     * @param int $userId User reporting damage
     * @return bool Success
     */
    public function recordDamage(int $lineItemId, int $quantity, string $reason, int $userId): bool
    {
        if ($quantity <= 0) {
            throw new InvalidArgumentException("Damage quantity must be positive");
        }

        $this->pdo->beginTransaction();

        try {
            // Update line item
            $stmt = $this->pdo->prepare("
                UPDATE vend_consignment_line_items
                SET quantity_damaged = quantity_damaged + :quantity,
                    updated_at = NOW()
                WHERE id = :line_item_id
            ");
            $stmt->execute([
                ':quantity' => $quantity,
                ':line_item_id' => $lineItemId
            ]);

            // Record damage event
            $stmt = $this->pdo->prepare("
                INSERT INTO damage_reports (
                    line_item_id,
                    quantity,
                    reason,
                    reported_by,
                    created_at
                ) VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$lineItemId, $quantity, $reason, $userId]);

            $this->pdo->commit();

            return true;

        } catch (PDOException $e) {
            $this->pdo->rollBack();
            throw new RuntimeException("Failed to record damage: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Get receiving history/events
     *
     * @param int $poId Purchase order ID
     * @return array Receiving events
     */
    public function getReceivingHistory(int $poId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                re.id,
                re.line_item_id,
                re.quantity_received,
                re.quantity_damaged,
                re.barcode_scanned,
                re.notes,
                re.created_at,
                vcli.sku,
                vcli.name AS product_name,
                u.full_name AS received_by_name
            FROM receiving_events re
            JOIN vend_consignment_line_items vcli ON re.line_item_id = vcli.id
            LEFT JOIN users u ON re.received_by = u.id
            WHERE re.transfer_id = ?
            ORDER BY re.created_at DESC
        ");

        $stmt->execute([$poId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // =========================================================================
    // PRIVATE HELPER METHODS
    // =========================================================================

    /**
     * Log audit entry
     */
    private function logAudit(int $poId, string $action, string $status, array $metadata = []): void
    {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO consignment_audit_log (
                    entity_type,
                    entity_pk,
                    transfer_pk,
                    action,
                    status,
                    actor_type,
                    actor_id,
                    metadata,
                    created_at
                ) VALUES (
                    'transfer',
                    :po_id,
                    :po_id,
                    :action,
                    :status,
                    :actor_type,
                    :actor_id,
                    :metadata,
                    NOW()
                )
            ");

            $stmt->execute([
                ':po_id' => $poId,
                ':action' => $action,
                ':status' => $status,
                ':actor_type' => $metadata['actor_type'] ?? 'user',
                ':actor_id' => $metadata['actor_id'] ?? null,
                ':metadata' => json_encode($metadata)
            ]);
        } catch (PDOException $e) {
            error_log("Failed to log audit entry: " . $e->getMessage());
        }
    }
}
