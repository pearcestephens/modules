<?php
/**
 * Flagged Products Model
 *
 * Data access layer for flagged products functionality
 * Handles all database operations with proper error handling and validation
 *
 * @package CIS\Modules\FlaggedProducts
 * @version 1.0.0
 */

declare(strict_types=1);

namespace CIS\FlaggedProducts\Models;

use Exception;
use mysqli;

class FlaggedProductModel
{
    private mysqli $db;
    private string $table = 'flagged_products';

    public function __construct()
    {
        $this->db = db(); // CIS global database connection
    }

    /**
     * Get all flagged products for a specific outlet
     *
     * @param string $outletID
     * @return array
     */
    public function getByOutlet(string $outletID): array
    {
        $stmt = $this->db->prepare("
            SELECT
                fp.id,
                fp.product_id,
                fp.outlet_id,
                fp.reason,
                fp.qty_before,
                fp.flagged_datetime,
                fp.dummy_product,
                p.sku,
                p.name as product_name,
                p.supply_price,
                o.name as outlet_name,
                vi.inventory_level as current_stock
            FROM {$this->table} fp
            LEFT JOIN vend_products p ON fp.product_id = p.id
            LEFT JOIN vend_outlets o ON fp.outlet_id = o.id
            LEFT JOIN vend_inventory vi ON fp.product_id = vi.product_id
                AND fp.outlet_id = vi.outlet_id
            WHERE fp.outlet_id = ?
            AND fp.complete = 0
            AND fp.deleted_at IS NULL
            ORDER BY fp.flagged_datetime DESC
        ");

        $stmt->bind_param('s', $outletID);
        $stmt->execute();
        $result = $stmt->get_result();
        $products = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        return $products;
    }

    /**
     * Get count of pending flagged products for an outlet
     *
     * @param string $outletID
     * @return int
     */
    public function getPendingCount(string $outletID): int
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as count
            FROM {$this->table}
            WHERE outlet_id = ?
            AND complete = 0
            AND deleted_at IS NULL
        ");

        $stmt->bind_param('s', $outletID);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return (int)($result['count'] ?? 0);
    }

    /**
     * Check if a product is already flagged
     *
     * @param string $productID
     * @param string $outletID
     * @param string|null $reason
     * @return bool
     */
    public function exists(string $productID, string $outletID, ?string $reason = null): bool
    {
        if ($reason) {
            $stmt = $this->db->prepare("
                SELECT id FROM {$this->table}
                WHERE product_id = ?
                AND outlet_id = ?
                AND reason = ?
                AND complete = 0
                AND deleted_at IS NULL
                LIMIT 1
            ");
            $stmt->bind_param('sss', $productID, $outletID, $reason);
        } else {
            $stmt = $this->db->prepare("
                SELECT id FROM {$this->table}
                WHERE product_id = ?
                AND outlet_id = ?
                AND complete = 0
                AND deleted_at IS NULL
                LIMIT 1
            ");
            $stmt->bind_param('ss', $productID, $outletID);
        }

        $stmt->execute();
        $result = $stmt->get_result();
        $exists = $result->num_rows > 0;
        $stmt->close();

        return $exists;
    }

    /**
     * Create a new flagged product
     *
     * @param array $data
     * @return int Last inserted ID
     * @throws Exception
     */
    public function create(array $data): int
    {
        $required = ['product_id', 'outlet_id', 'reason', 'qty_before'];
        foreach ($required as $field) {
            if (!isset($data[$field])) {
                throw new Exception("Missing required field: {$field}");
            }
        }

        $stmt = $this->db->prepare("
            INSERT INTO {$this->table}
            (product_id, outlet_id, reason, qty_before, flagged_datetime, dummy_product)
            VALUES (?, ?, ?, ?, NOW(), ?)
        ");

        $productID = $data['product_id'];
        $outletID = $data['outlet_id'];
        $reason = $data['reason'];
        $qtyBefore = (int)$data['qty_before'];
        $dummyProduct = (int)($data['dummy_product'] ?? 0);

        $stmt->bind_param('sssii', $productID, $outletID, $reason, $qtyBefore, $dummyProduct);

        if (!$stmt->execute()) {
            throw new Exception("Failed to create flagged product: " . $stmt->error);
        }

        $insertID = $stmt->insert_id;
        $stmt->close();

        return $insertID;
    }

    /**
     * Mark a flagged product as complete
     *
     * @param string $productID
     * @param string $outletID
     * @param int $staffID
     * @param int $qtyAfter
     * @param int|null $qtyBefore
     * @return bool
     * @throws Exception
     */
    public function markComplete(
        string $productID,
        string $outletID,
        int $staffID,
        int $qtyAfter,
        ?int $qtyBefore = null
    ): bool {
        $stmt = $this->db->prepare("
            UPDATE {$this->table}
            SET complete = 1,
                completed_datetime = NOW(),
                staff_id = ?,
                qty_after = ?,
                qty_before = COALESCE(?, qty_before)
            WHERE product_id = ?
            AND outlet_id = ?
            AND complete = 0
            AND deleted_at IS NULL
        ");

        $stmt->bind_param('iiiss', $staffID, $qtyAfter, $qtyBefore, $productID, $outletID);

        if (!$stmt->execute()) {
            throw new Exception("Failed to complete flagged product: " . $stmt->error);
        }

        $affected = $stmt->affected_rows;
        $stmt->close();

        return $affected > 0;
    }

    /**
     * Delete flagged products
     *
     * @param string|null $outletID If null, deletes all incomplete flagged products
     * @return int Number of rows affected
     */
    public function delete(?string $outletID = null): int
    {
        if ($outletID) {
            $stmt = $this->db->prepare("
                UPDATE {$this->table}
                SET deleted_at = NOW()
                WHERE outlet_id = ?
                AND complete = 0
                AND deleted_at IS NULL
            ");
            $stmt->bind_param('s', $outletID);
        } else {
            $stmt = $this->db->prepare("
                UPDATE {$this->table}
                SET deleted_at = NOW()
                WHERE complete = 0
                AND deleted_at IS NULL
            ");
        }

        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();

        return $affected;
    }

    /**
     * Get last 30 days of completed flagged products for an outlet
     *
     * @param string $outletID
     * @return array
     */
    public function getLast30Days(string $outletID): array
    {
        $stmt = $this->db->prepare("
            SELECT
                fp.*,
                p.sku,
                p.name as product_name,
                s.name as staff_name,
                o.name as outlet_name,
                CASE
                    WHEN fp.qty_before = fp.qty_after THEN 1
                    ELSE 0
                END as is_accurate
            FROM {$this->table} fp
            LEFT JOIN vend_products p ON fp.product_id = p.id
            LEFT JOIN staff s ON fp.staff_id = s.id
            LEFT JOIN vend_outlets o ON fp.outlet_id = o.id
            WHERE fp.outlet_id = ?
            AND fp.complete = 1
            AND fp.completed_datetime >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            AND fp.deleted_at IS NULL
            ORDER BY fp.completed_datetime DESC
        ");

        $stmt->bind_param('s', $outletID);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        return $data;
    }

    /**
     * Get commonly inaccurate products for an outlet
     *
     * @param string $outletID
     * @param int $limit
     * @return array
     */
    public function getCommonlyInaccurate(string $outletID, int $limit = 20): array
    {
        $stmt = $this->db->prepare("
            SELECT
                fp.product_id,
                p.sku,
                p.name as product_name,
                COUNT(*) as inaccurate_count,
                AVG(ABS(fp.qty_before - fp.qty_after)) as avg_discrepancy,
                MAX(fp.completed_datetime) as last_inaccurate
            FROM {$this->table} fp
            LEFT JOIN vend_products p ON fp.product_id = p.id
            WHERE fp.outlet_id = ?
            AND fp.complete = 1
            AND fp.qty_before != fp.qty_after
            AND fp.completed_datetime >= DATE_SUB(NOW(), INTERVAL 90 DAY)
            AND fp.deleted_at IS NULL
            GROUP BY fp.product_id, p.sku, p.name
            HAVING inaccurate_count >= 2
            ORDER BY inaccurate_count DESC, avg_discrepancy DESC
            LIMIT ?
        ");

        $stmt->bind_param('si', $outletID, $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        return $data;
    }

    /**
     * Get accuracy statistics for an outlet
     *
     * @param string $outletID
     * @param int $days
     * @return array
     */
    public function getAccuracyStats(string $outletID, int $days = 30): array
    {
        $stmt = $this->db->prepare("
            SELECT
                COUNT(*) as total_checked,
                SUM(CASE WHEN qty_before = qty_after THEN 1 ELSE 0 END) as accurate,
                SUM(CASE WHEN qty_before != qty_after THEN 1 ELSE 0 END) as inaccurate,
                ROUND(
                    (SUM(CASE WHEN qty_before = qty_after THEN 1 ELSE 0 END) / COUNT(*)) * 100,
                    2
                ) as accuracy_percent
            FROM {$this->table}
            WHERE outlet_id = ?
            AND complete = 1
            AND completed_datetime >= DATE_SUB(NOW(), INTERVAL ? DAY)
            AND deleted_at IS NULL
        ");

        $stmt->bind_param('si', $outletID, $days);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $result ?: [
            'total_checked' => 0,
            'accurate' => 0,
            'inaccurate' => 0,
            'accuracy_percent' => 0
        ];
    }
}
