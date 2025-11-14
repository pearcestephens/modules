<?php
/**
 * Order Service
 *
 * Handles order operations including processing, status updates, comments, and shipping.
 * Replaces functionality from view-web-order.php and view-web-order-outlet.php
 *
 * @package CIS\Modules\EcommerceOps\Services
 */

namespace CIS\Modules\EcommerceOps;

class OrderService {

    private $db;
    private $vendAPI;

    /**
     * Constructor
     */
    public function __construct() {
        global $conn, $VendAPI;
        $this->db = $conn;
        $this->vendAPI = $VendAPI;
    }

    /**
     * Get order by ID
     *
     * @param int $orderId
     * @return array|null
     */
    public function getOrder(int $orderId): ?array {
        $stmt = $this->db->prepare("
            SELECT
                o.*,
                c.first_name as customer_first_name,
                c.last_name as customer_last_name,
                c.email as customer_email,
                c.phone as customer_phone,
                vo.name as outlet_name,
                vo.id as outlet_id,
                av.verification_status as age_verification_status,
                fb.id as customer_is_blacklisted
            FROM vend_sales o
            LEFT JOIN vend_customers c ON o.customer_id = c.id
            LEFT JOIN vend_outlets vo ON o.outlet_id = vo.id
            LEFT JOIN ecommerce_age_verifications av ON c.id = av.customer_id AND av.verification_status = 'approved'
            LEFT JOIN ecommerce_fraud_blacklist fb ON (
                fb.email = c.email
                OR fb.phone = c.phone
            ) AND fb.is_active = 1
            WHERE o.id = ?
        ");

        $stmt->execute([$orderId]);
        $order = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($order) {
            // Get line items
            $order['line_items'] = $this->getOrderLineItems($orderId);

            // Get comments
            $order['comments'] = $this->getOrderComments($orderId);

            // Get fulfillment info
            $order['fulfillment'] = $this->getOrderFulfillment($orderId);
        }

        return $order ?: null;
    }

    /**
     * Get order line items
     *
     * @param int $orderId
     * @return array
     */
    public function getOrderLineItems(int $orderId): array {
        $stmt = $this->db->prepare("
            SELECT
                li.*,
                p.name as product_name,
                p.sku as product_sku,
                p.handle as product_handle
            FROM vend_sale_products li
            LEFT JOIN vend_products p ON li.product_id = p.id
            WHERE li.sale_id = ?
            ORDER BY li.id
        ");

        $stmt->execute([$orderId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get order comments
     *
     * @param int $orderId
     * @return array
     */
    public function getOrderComments(int $orderId): array {
        $stmt = $this->db->prepare("
            SELECT
                c.*,
                u.name as staff_name
            FROM order_comments c
            LEFT JOIN users u ON c.user_id = u.id
            WHERE c.order_id = ?
            ORDER BY c.created_at DESC
        ");

        $stmt->execute([$orderId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get order fulfillment info
     *
     * @param int $orderId
     * @return array|null
     */
    public function getOrderFulfillment(int $orderId): ?array {
        $stmt = $this->db->prepare("
            SELECT * FROM ecommerce_fulfillment_jobs
            WHERE order_id = ?
            ORDER BY created_at DESC
            LIMIT 1
        ");

        $stmt->execute([$orderId]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * List orders with filters and pagination
     *
     * @param array $filters
     * @param int $page
     * @param int $perPage
     * @return array
     */
    public function listOrders(array $filters = [], int $page = 1, int $perPage = 50): array {
        $where = ['1=1'];
        $params = [];

        // Date range filter
        if (!empty($filters['date_from'])) {
            $where[] = "o.order_date >= ?";
            $params[] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where[] = "o.order_date <= ?";
            $params[] = $filters['date_to'] . ' 23:59:59';
        }

        // Status filter
        if (!empty($filters['status'])) {
            $where[] = "o.status = ?";
            $params[] = $filters['status'];
        }

        // Outlet filter
        if (!empty($filters['outlet_id'])) {
            $where[] = "o.outlet_id = ?";
            $params[] = $filters['outlet_id'];
        }

        // Customer search
        if (!empty($filters['customer_search'])) {
            $search = '%' . $filters['customer_search'] . '%';
            $where[] = "(c.first_name LIKE ? OR c.last_name LIKE ? OR c.email LIKE ?)";
            $params = array_merge($params, [$search, $search, $search]);
        }

        // Order ID search
        if (!empty($filters['order_id'])) {
            $where[] = "o.id = ?";
            $params[] = $filters['order_id'];
        }

        // Age verification filter
        if (!empty($filters['age_verification'])) {
            if ($filters['age_verification'] === 'pending') {
                $where[] = "(av.verification_status IS NULL OR av.verification_status = 'pending')";
            } elseif ($filters['age_verification'] === 'approved') {
                $where[] = "av.verification_status = 'approved'";
            } elseif ($filters['age_verification'] === 'rejected') {
                $where[] = "av.verification_status = 'rejected'";
            }
        }

        // Blacklist filter
        if (!empty($filters['blacklisted']) && $filters['blacklisted'] === 'yes') {
            $where[] = "fb.id IS NOT NULL";
        }

        $whereSQL = implode(' AND ', $where);
        $offset = ($page - 1) * $perPage;

        // Get total count
        $countStmt = $this->db->prepare("
            SELECT COUNT(DISTINCT o.id)
            FROM vend_sales o
            LEFT JOIN vend_customers c ON o.customer_id = c.id
            LEFT JOIN ecommerce_age_verifications av ON c.id = av.customer_id
            LEFT JOIN ecommerce_fraud_blacklist fb ON (
                fb.email = c.email
                OR fb.phone = c.phone
            ) AND fb.is_active = 1
            WHERE $whereSQL
        ");
        $countStmt->execute($params);
        $total = $countStmt->fetchColumn();

        // Get orders
        $stmt = $this->db->prepare("
            SELECT
                o.*,
                c.first_name as customer_first_name,
                c.last_name as customer_last_name,
                c.email as customer_email,
                vo.name as outlet_name,
                av.verification_status as age_verification_status,
                CASE WHEN fb.id IS NOT NULL THEN 1 ELSE 0 END as customer_is_blacklisted,
                fj.status as fulfillment_status
            FROM vend_sales o
            LEFT JOIN vend_customers c ON o.customer_id = c.id
            LEFT JOIN vend_outlets vo ON o.outlet_id = vo.id
            LEFT JOIN ecommerce_age_verifications av ON c.id = av.customer_id
            LEFT JOIN ecommerce_fraud_blacklist fb ON (
                fb.email = c.email
                OR fb.phone = c.phone
            ) AND fb.is_active = 1
            LEFT JOIN ecommerce_fulfillment_jobs fj ON o.id = fj.order_id
            WHERE $whereSQL
            ORDER BY o.order_date DESC
            LIMIT ? OFFSET ?
        ");

        $params[] = $perPage;
        $params[] = $offset;
        $stmt->execute($params);

        return [
            'orders' => $stmt->fetchAll(\PDO::FETCH_ASSOC),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => ceil($total / $perPage)
        ];
    }

    /**
     * Add comment to order
     *
     * @param int $orderId
     * @param string $text
     * @param bool $isPrivate
     * @param int $userId
     * @return bool
     */
    public function addComment(int $orderId, string $text, bool $isPrivate, int $userId): bool {
        $stmt = $this->db->prepare("
            INSERT INTO order_comments (order_id, text, is_private, user_id, created_at)
            VALUES (?, ?, ?, ?, NOW())
        ");

        $result = $stmt->execute([$orderId, $text, $isPrivate ? 1 : 0, $userId]);

        if ($result) {
            ecomm_log_error("Order comment added", [
                'order_id' => $orderId,
                'user_id' => $userId,
                'is_private' => $isPrivate
            ]);
        }

        return $result;
    }

    /**
     * Update order status
     *
     * @param int $orderId
     * @param string $status
     * @param int $userId
     * @return bool
     */
    public function updateStatus(int $orderId, string $status, int $userId): bool {
        $stmt = $this->db->prepare("
            UPDATE vend_sales
            SET status = ?, updated_at = NOW()
            WHERE id = ?
        ");

        $result = $stmt->execute([$status, $orderId]);

        if ($result) {
            $this->addComment($orderId, "Order status changed to: $status", false, $userId);

            ecomm_log_error("Order status updated", [
                'order_id' => $orderId,
                'new_status' => $status,
                'user_id' => $userId
            ]);
        }

        return $result;
    }

    /**
     * Update shipping cost
     *
     * @param int $orderId
     * @param float $shippingCost
     * @param int $userId
     * @return bool
     */
    public function updateShippingCost(int $orderId, float $shippingCost, int $userId): bool {
        $order = $this->getOrder($orderId);

        if (!$order) {
            return false;
        }

        $oldShippingCost = $order['shipping_cost'] ?? 0;
        $priceDiff = $shippingCost - $oldShippingCost;
        $newTotalPrice = $order['total_price'] + $priceDiff;

        $stmt = $this->db->prepare("
            UPDATE vend_sales
            SET shipping_cost = ?, total_price = ?, updated_at = NOW()
            WHERE id = ?
        ");

        $result = $stmt->execute([$shippingCost, $newTotalPrice, $orderId]);

        if ($result) {
            $this->addComment(
                $orderId,
                sprintf(
                    "Shipping cost updated from $%.2f to $%.2f (Total: $%.2f → $%.2f)",
                    $oldShippingCost,
                    $shippingCost,
                    $order['total_price'],
                    $newTotalPrice
                ),
                false,
                $userId
            );

            ecomm_log_error("Order shipping cost updated", [
                'order_id' => $orderId,
                'old_cost' => $oldShippingCost,
                'new_cost' => $shippingCost,
                'user_id' => $userId
            ]);
        }

        return $result;
    }

    /**
     * Mark order as underage/fraud
     *
     * @param int $orderId
     * @param string $reason
     * @param int $userId
     * @return bool
     */
    public function markAsUnderage(int $orderId, string $reason, int $userId): bool {
        $order = $this->getOrder($orderId);

        if (!$order) {
            return false;
        }

        // Update order status
        $stmt = $this->db->prepare("
            UPDATE vend_sales
            SET status = 'cancelled',
                cancelled_reason = ?,
                updated_at = NOW()
            WHERE id = ?
        ");

        $stmt->execute([$reason, $orderId]);

        // Add to blacklist
        $customerService = new CustomerService();
        $customerService->addToBlacklist($order['customer_id'], $reason, $userId);

        // Add comment
        $this->addComment($orderId, "⚠️ UNDERAGE/FRAUD: $reason", false, $userId);

        ecomm_log_error("Order marked as underage/fraud", [
            'order_id' => $orderId,
            'customer_id' => $order['customer_id'],
            'reason' => $reason,
            'user_id' => $userId
        ]);

        return true;
    }

    /**
     * Clear underage/fraud flag
     *
     * @param int $orderId
     * @param int $userId
     * @return bool
     */
    public function clearUnderageFraud(int $orderId, int $userId): bool {
        $order = $this->getOrder($orderId);

        if (!$order) {
            return false;
        }

        // Update order status back to processing
        $stmt = $this->db->prepare("
            UPDATE vend_sales
            SET status = 'processing',
                cancelled_reason = NULL,
                updated_at = NOW()
            WHERE id = ?
        ");

        $result = $stmt->execute([$orderId]);

        // Remove from blacklist
        $customerService = new CustomerService();
        $customerService->removeFromBlacklist($order['customer_id'], $userId);

        // Add comment
        $this->addComment($orderId, "✅ Underage/fraud flag removed", false, $userId);

        ecomm_log_error("Order underage/fraud flag cleared", [
            'order_id' => $orderId,
            'customer_id' => $order['customer_id'],
            'user_id' => $userId
        ]);

        return $result;
    }

    /**
     * Check if order matches existing underage patterns
     *
     * @param array $order
     * @return array Matching blacklist entries
     */
    public function checkUnderagePatterns(array $order): array {
        $stmt = $this->db->prepare("
            SELECT * FROM ecommerce_fraud_blacklist
            WHERE is_active = 1
            AND (
                email = ?
                OR phone = ?
                OR LOWER(address_line1) = LOWER(?)
            )
        ");

        $stmt->execute([
            $order['customer_email'] ?? '',
            $order['customer_phone'] ?? '',
            $order['shipping_address_1'] ?? ''
        ]);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get orders by outlet
     *
     * @param int $outletId
     * @param array $filters
     * @param int $page
     * @param int $perPage
     * @return array
     */
    public function getOrdersByOutlet(int $outletId, array $filters = [], int $page = 1, int $perPage = 50): array {
        $filters['outlet_id'] = $outletId;
        return $this->listOrders($filters, $page, $perPage);
    }

    /**
     * Sync order from Vend
     *
     * @param string $vendSaleId
     * @return bool
     */
    public function syncFromVend(string $vendSaleId): bool {
        if (!$this->vendAPI) {
            return false;
        }

        try {
            $vendSale = $this->vendAPI->getSale($vendSaleId);

            if (!$vendSale) {
                return false;
            }

            // Check if order exists
            $stmt = $this->db->prepare("SELECT id FROM vend_sales WHERE vend_sale_id = ?");
            $stmt->execute([$vendSaleId]);
            $existing = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($existing) {
                // Update existing order
                $stmt = $this->db->prepare("
                    UPDATE vend_sales SET
                        customer_id = ?,
                        outlet_id = ?,
                        status = ?,
                        total_price = ?,
                        total_tax = ?,
                        shipping_cost = ?,
                        order_date = ?,
                        updated_at = NOW()
                    WHERE vend_sale_id = ?
                ");

                return $stmt->execute([
                    $vendSale['customer_id'] ?? null,
                    $vendSale['outlet_id'] ?? null,
                    $vendSale['status'] ?? 'open',
                    $vendSale['total_price'] ?? 0,
                    $vendSale['total_tax'] ?? 0,
                    $vendSale['shipping_cost'] ?? 0,
                    $vendSale['order_date'] ?? date('Y-m-d H:i:s'),
                    $vendSaleId
                ]);
            } else {
                // Insert new order
                $stmt = $this->db->prepare("
                    INSERT INTO vend_sales (
                        vend_sale_id, customer_id, outlet_id, status, total_price,
                        total_tax, shipping_cost, order_date, created_at, updated_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                ");

                return $stmt->execute([
                    $vendSaleId,
                    $vendSale['customer_id'] ?? null,
                    $vendSale['outlet_id'] ?? null,
                    $vendSale['status'] ?? 'open',
                    $vendSale['total_price'] ?? 0,
                    $vendSale['total_tax'] ?? 0,
                    $vendSale['shipping_cost'] ?? 0,
                    $vendSale['order_date'] ?? date('Y-m-d H:i:s')
                ]);
            }
        } catch (\Exception $e) {
            ecomm_log_error("Failed to sync order from Vend", [
                'vend_sale_id' => $vendSaleId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Calculate order statistics
     *
     * @param array $filters
     * @return array
     */
    public function getStatistics(array $filters = []): array {
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['date_from'])) {
            $where[] = "order_date >= ?";
            $params[] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where[] = "order_date <= ?";
            $params[] = $filters['date_to'] . ' 23:59:59';
        }
        if (!empty($filters['outlet_id'])) {
            $where[] = "outlet_id = ?";
            $params[] = $filters['outlet_id'];
        }

        $whereSQL = implode(' AND ', $where);

        $stmt = $this->db->prepare("
            SELECT
                COUNT(*) as total_orders,
                SUM(total_price) as total_revenue,
                AVG(total_price) as average_order_value,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_orders,
                SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_orders,
                SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) as processing_orders
            FROM vend_sales
            WHERE $whereSQL
        ");

        $stmt->execute($params);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];
    }
}
