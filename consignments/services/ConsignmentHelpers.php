<?php
/**
 * Consignment Helpers - Shared Utility Functions
 *
 * DRY utilities used across stock transfers and purchase orders:
 * - Data validation and sanitization
 * - Status management
 * - Audit logging
 * - Common calculations
 * - Error handling
 *
 * @package CIS\Consignments\Services
 * @version 1.0.0
 */

declare(strict_types=1);

namespace CIS\Consignments\Services;

use PDO;

class ConsignmentHelpers
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Valid consignment statuses
     */
    public const STATUSES = [
        'DRAFT' => 'Draft',
        'PENDING' => 'Pending',
        'APPROVED' => 'Approved',
        'PACKED' => 'Packed',
        'SHIPPED' => 'Shipped',
        'IN_TRANSIT' => 'In Transit',
        'DELIVERED' => 'Delivered',
        'RECEIVED' => 'Received',
        'CANCELLED' => 'Cancelled'
    ];

    /**
     * Log audit event
     */
    public function logEvent(int $consignmentId, string $action, array $data = [], ?int $userId = null): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO consignment_unified_log (
                consignment_id,
                action,
                data,
                user_id,
                created_at
            ) VALUES (?, ?, ?, ?, NOW())
        ");

        $stmt->execute([
            $consignmentId,
            $action,
            json_encode($data),
            $userId ?? $_SESSION['user_id'] ?? null
        ]);
    }

    /**
     * Update consignment status
     */
    public function updateStatus(int $consignmentId, string $status, ?string $note = null): bool
    {
        if (!isset(self::STATUSES[$status])) {
            throw new \InvalidArgumentException("Invalid status: {$status}");
        }

        $stmt = $this->db->prepare("
            UPDATE vend_consignments
            SET status = ?,
                updated_at = NOW()
            WHERE id = ?
        ");

        $result = $stmt->execute([$status, $consignmentId]);

        if ($result) {
            $this->logEvent($consignmentId, 'status_change', [
                'old_status' => $this->getConsignmentStatus($consignmentId),
                'new_status' => $status,
                'note' => $note
            ]);
        }

        return $result;
    }

    /**
     * Get current consignment status
     */
    public function getConsignmentStatus(int $consignmentId): ?string
    {
        $stmt = $this->db->prepare("
            SELECT status
            FROM vend_consignments
            WHERE id = ?
        ");
        $stmt->execute([$consignmentId]);
        return $stmt->fetchColumn() ?: null;
    }

    /**
     * Validate consignment data
     */
    public function validateConsignment(array $data): array
    {
        $errors = [];

        if (empty($data['source_outlet_id']) && empty($data['supplier_id'])) {
            $errors[] = 'Source outlet or supplier is required';
        }

        if (empty($data['destination_outlet_id'])) {
            $errors[] = 'Destination outlet is required';
        }

        if (empty($data['transfer_category'])) {
            $errors[] = 'Transfer category is required';
        }

        return $errors;
    }

    /**
     * Calculate total value of consignment
     */
    public function calculateTotalValue(int $consignmentId): float
    {
        $stmt = $this->db->prepare("
            SELECT COALESCE(SUM(quantity * unit_cost), 0) AS total
            FROM vend_consignment_line_items
            WHERE transfer_id = ?
                AND deleted_at IS NULL
        ");
        $stmt->execute([$consignmentId]);
        return (float) $stmt->fetchColumn();
    }

    /**
     * Calculate total items in consignment
     */
    public function calculateTotalItems(int $consignmentId): int
    {
        $stmt = $this->db->prepare("
            SELECT COALESCE(SUM(quantity), 0) AS total
            FROM vend_consignment_line_items
            WHERE transfer_id = ?
                AND deleted_at IS NULL
        ");
        $stmt->execute([$consignmentId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Get consignment audit trail
     */
    public function getAuditTrail(int $consignmentId, int $limit = 50): array
    {
        $stmt = $this->db->prepare("
            SELECT
                l.*,
                u.name AS user_name
            FROM consignment_unified_log l
            LEFT JOIN users u ON l.user_id = u.id
            WHERE l.consignment_id = ?
            ORDER BY l.created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$consignmentId, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Check if consignment can be edited
     */
    public function canEdit(int $consignmentId): bool
    {
        $status = $this->getConsignmentStatus($consignmentId);
        return in_array($status, ['DRAFT', 'PENDING']);
    }

    /**
     * Check if consignment can be cancelled
     */
    public function canCancel(int $consignmentId): bool
    {
        $status = $this->getConsignmentStatus($consignmentId);
        return !in_array($status, ['RECEIVED', 'CANCELLED']);
    }

    /**
     * Format currency
     */
    public function formatCurrency(float $amount): string
    {
        return '$' . number_format($amount, 2);
    }

    /**
     * Format weight
     */
    public function formatWeight(float $weightKg): string
    {
        if ($weightKg < 1) {
            return round($weightKg * 1000) . 'g';
        }
        return round($weightKg, 2) . 'kg';
    }

    /**
     * Format date
     */
    public function formatDate(?string $date): string
    {
        if (!$date) {
            return '—';
        }
        return date('d/m/Y H:i', strtotime($date));
    }

    /**
     * Sanitize input
     */
    public function sanitize(string $input): string
    {
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }
}
