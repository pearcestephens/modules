<?php
/**
 * Order Management Service
 *
 * Handles all order lifecycle operations with intelligent shipping cost optimization
 *
 * KEY FEATURE: Shipping algorithm that SAVES MONEY on every order by:
 * - Selecting optimal fulfillment location based on inventory + shipping cost
 * - Multi-carrier rate comparison (NZ Post, CourierPost, Fastway)
 * - Smart package consolidation
 * - Real-time rate calculation
 *
 * @package    WebsiteOperations
 * @version    1.0.0
 * @author     Ecigdis Development Team
 * @copyright  2025 Ecigdis Limited
 */

namespace Modules\WebsiteOperations\Services;

use PDO;
use PDOException;
use Exception;

class OrderManagementService
{
    private PDO $db;
    private ShippingOptimizationService $shippingOptimizer;

    /**
     * Initialize service
     */
    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->shippingOptimizer = new ShippingOptimizationService($db);
    }

    /**
     * Get recent orders with filters
     */
    public function getRecentOrders(int $limit = 20, string $outlet = 'all', array $filters = []): array
    {
        try {
            $where = ['1=1'];
            $params = [];

            if ($outlet !== 'all') {
                $where[] = 'o.outlet_id = :outlet';
                $params[':outlet'] = $outlet;
            }

            if (!empty($filters['status'])) {
                $where[] = 'o.status = :status';
                $params[':status'] = $filters['status'];
            }

            if (!empty($filters['channel'])) {
                $where[] = 'o.channel = :channel';
                $params[':channel'] = $filters['channel'];
            }

            if (!empty($filters['date_from'])) {
                $where[] = 'o.created_at >= :date_from';
                $params[':date_from'] = $filters['date_from'];
            }

            if (!empty($filters['date_to'])) {
                $where[] = 'o.created_at <= :date_to';
                $params[':date_to'] = $filters['date_to'];
            }

            $whereClause = implode(' AND ', $where);

            $stmt = $this->db->prepare("
                SELECT
                    o.*,
                    c.name as customer_name,
                    c.email as customer_email,
                    COUNT(oi.id) as item_count,
                    s.name as outlet_name,
                    o.shipping_cost_saved
                FROM web_orders o
                LEFT JOIN web_customers c ON o.customer_id = c.id
                LEFT JOIN web_order_items oi ON o.id = oi.order_id
                LEFT JOIN store_configurations s ON o.outlet_id = s.id
                WHERE $whereClause
                GROUP BY o.id
                ORDER BY o.created_at DESC
                LIMIT :limit
            ");

            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);

            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Get orders error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get order by ID with full details
     */
    public function getOrderById(int $orderId): ?array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT
                    o.*,
                    c.name as customer_name,
                    c.email as customer_email,
                    c.phone as customer_phone,
                    c.company as customer_company,
                    s.name as outlet_name,
                    s.address as outlet_address
                FROM web_orders o
                LEFT JOIN web_customers c ON o.customer_id = c.id
                LEFT JOIN store_configurations s ON o.outlet_id = s.id
                WHERE o.id = :id
            ");

            $stmt->execute([':id' => $orderId]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$order) {
                return null;
            }

            // Get order items
            $order['items'] = $this->getOrderItems($orderId);

            // Get shipping history
            $order['shipping_history'] = $this->getShippingHistory($orderId);

            // Get status history
            $order['status_history'] = $this->getStatusHistory($orderId);

            return $order;
        } catch (PDOException $e) {
            error_log("Get order by ID error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get order items
     */
    private function getOrderItems(int $orderId): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT
                    oi.*,
                    p.name as product_name,
                    p.sku as product_sku,
                    p.image_url
                FROM web_order_items oi
                LEFT JOIN web_products p ON oi.product_id = p.id
                WHERE oi.order_id = :order_id
            ");

            $stmt->execute([':order_id' => $orderId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Get order items error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get shipping history
     */
    private function getShippingHistory(int $orderId): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT *
                FROM order_shipping_history
                WHERE order_id = :order_id
                ORDER BY created_at DESC
            ");

            $stmt->execute([':order_id' => $orderId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Get shipping history error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get status history
     */
    private function getStatusHistory(int $orderId): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT *
                FROM order_status_history
                WHERE order_id = :order_id
                ORDER BY created_at DESC
            ");

            $stmt->execute([':order_id' => $orderId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Get status history error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Create new order with intelligent fulfillment routing
     * THIS IS WHERE WE SAVE MONEY!!!
     */
    public function createOrder(array $orderData): array
    {
        $this->db->beginTransaction();

        try {
            // 1. Extract order details
            $customerId = $orderData['customer_id'];
            $items = $orderData['items'];
            $shippingAddress = $orderData['shipping_address'];
            $channel = $orderData['channel'] ?? 'vapeshed';

            // 2. CRITICAL: Find optimal fulfillment location to SAVE MONEY
            $optimization = $this->shippingOptimizer->findOptimalFulfillment(
                $items,
                $shippingAddress,
                $orderData['shipping_preference'] ?? 'cost' // 'cost', 'speed', or 'balanced'
            );

            // 3. Calculate totals
            $subtotal = array_sum(array_column($items, 'total'));
            $shippingCost = $optimization['shipping_cost'];
            $taxAmount = $subtotal * 0.15; // 15% GST
            $total = $subtotal + $shippingCost + $taxAmount;

            // 4. Insert order
            $stmt = $this->db->prepare("
                INSERT INTO web_orders (
                    customer_id,
                    outlet_id,
                    channel,
                    order_number,
                    status,
                    subtotal,
                    shipping_cost,
                    tax_amount,
                    total_amount,
                    shipping_address,
                    shipping_city,
                    shipping_postcode,
                    shipping_country,
                    shipping_carrier,
                    shipping_service,
                    shipping_cost_saved,
                    fulfillment_location,
                    created_at
                ) VALUES (
                    :customer_id,
                    :outlet_id,
                    :channel,
                    :order_number,
                    'pending',
                    :subtotal,
                    :shipping_cost,
                    :tax_amount,
                    :total_amount,
                    :shipping_address,
                    :shipping_city,
                    :shipping_postcode,
                    :shipping_country,
                    :shipping_carrier,
                    :shipping_service,
                    :shipping_cost_saved,
                    :fulfillment_location,
                    NOW()
                )
            ");

            $orderNumber = $this->generateOrderNumber($channel);

            $stmt->execute([
                ':customer_id' => $customerId,
                ':outlet_id' => $optimization['outlet_id'],
                ':channel' => $channel,
                ':order_number' => $orderNumber,
                ':subtotal' => $subtotal,
                ':shipping_cost' => $shippingCost,
                ':tax_amount' => $taxAmount,
                ':total_amount' => $total,
                ':shipping_address' => $shippingAddress['address'],
                ':shipping_city' => $shippingAddress['city'],
                ':shipping_postcode' => $shippingAddress['postcode'],
                ':shipping_country' => $shippingAddress['country'] ?? 'NZ',
                ':shipping_carrier' => $optimization['carrier'],
                ':shipping_service' => $optimization['service'],
                ':shipping_cost_saved' => $optimization['cost_saved'], // TRACK SAVINGS!
                ':fulfillment_location' => $optimization['location_name']
            ]);

            $orderId = $this->db->lastInsertId();

            // 5. Insert order items
            foreach ($items as $item) {
                $this->insertOrderItem($orderId, $item);
            }

            // 6. Record status change
            $this->recordStatusChange($orderId, 'pending', 'Order created');

            // 7. Record shipping optimization details
            $this->recordShippingOptimization($orderId, $optimization);

            $this->db->commit();

            return [
                'success' => true,
                'order_id' => $orderId,
                'order_number' => $orderNumber,
                'total' => $total,
                'shipping_cost' => $shippingCost,
                'cost_saved' => $optimization['cost_saved'],
                'fulfillment_location' => $optimization['location_name'],
                'optimization_details' => $optimization
            ];

        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Create order error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Insert order item
     */
    private function insertOrderItem(int $orderId, array $item): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO web_order_items (
                order_id,
                product_id,
                sku,
                name,
                quantity,
                price,
                total,
                created_at
            ) VALUES (
                :order_id,
                :product_id,
                :sku,
                :name,
                :quantity,
                :price,
                :total,
                NOW()
            )
        ");

        $stmt->execute([
            ':order_id' => $orderId,
            ':product_id' => $item['product_id'],
            ':sku' => $item['sku'],
            ':name' => $item['name'],
            ':quantity' => $item['quantity'],
            ':price' => $item['price'],
            ':total' => $item['total']
        ]);
    }

    /**
     * Update order status
     */
    public function updateOrderStatus(int $orderId, string $newStatus, string $notes = ''): bool
    {
        try {
            $stmt = $this->db->prepare("
                UPDATE web_orders
                SET status = :status,
                    updated_at = NOW()
                WHERE id = :id
            ");

            $stmt->execute([
                ':status' => $newStatus,
                ':id' => $orderId
            ]);

            // Record status change
            $this->recordStatusChange($orderId, $newStatus, $notes);

            return true;
        } catch (PDOException $e) {
            error_log("Update order status error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Record status change
     */
    private function recordStatusChange(int $orderId, string $status, string $notes = ''): void
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO order_status_history (
                    order_id,
                    status,
                    notes,
                    user_id,
                    created_at
                ) VALUES (
                    :order_id,
                    :status,
                    :notes,
                    :user_id,
                    NOW()
                )
            ");

            $stmt->execute([
                ':order_id' => $orderId,
                ':status' => $status,
                ':notes' => $notes,
                ':user_id' => $_SESSION['user']['id'] ?? null
            ]);
        } catch (PDOException $e) {
            error_log("Record status change error: " . $e->getMessage());
        }
    }

    /**
     * Record shipping optimization details for reporting
     */
    private function recordShippingOptimization(int $orderId, array $optimization): void
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO order_shipping_history (
                    order_id,
                    carrier,
                    service,
                    cost,
                    alternatives_considered,
                    cost_saved,
                    optimization_strategy,
                    created_at
                ) VALUES (
                    :order_id,
                    :carrier,
                    :service,
                    :cost,
                    :alternatives,
                    :cost_saved,
                    :strategy,
                    NOW()
                )
            ");

            $stmt->execute([
                ':order_id' => $orderId,
                ':carrier' => $optimization['carrier'],
                ':service' => $optimization['service'],
                ':cost' => $optimization['shipping_cost'],
                ':alternatives' => json_encode($optimization['alternatives'] ?? []),
                ':cost_saved' => $optimization['cost_saved'],
                ':strategy' => $optimization['strategy']
            ]);
        } catch (PDOException $e) {
            error_log("Record shipping optimization error: " . $e->getMessage());
        }
    }

    /**
     * Generate order number
     */
    private function generateOrderNumber(string $channel): string
    {
        $prefix = ($channel === 'ecigdis') ? 'WS' : 'VS';
        $timestamp = date('ymd');
        $random = str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);

        return "$prefix-$timestamp-$random";
    }

    /**
     * Get pending order count
     */
    public function getPendingOrderCount(): int
    {
        try {
            $stmt = $this->db->query("
                SELECT COUNT(*) as count
                FROM web_orders
                WHERE status = 'pending'
            ");

            return (int)$stmt->fetch(PDO::FETCH_ASSOC)['count'];
        } catch (PDOException $e) {
            error_log("Get pending count error: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Get total cost savings from shipping optimization
     */
    public function getTotalShippingSavings(int $days = 30): float
    {
        try {
            $stmt = $this->db->prepare("
                SELECT SUM(shipping_cost_saved) as total_saved
                FROM web_orders
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
            ");

            $stmt->execute([':days' => $days]);
            return (float)$stmt->fetch(PDO::FETCH_ASSOC)['total_saved'];
        } catch (PDOException $e) {
            error_log("Get shipping savings error: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Bulk update orders
     */
    public function bulkUpdateOrders(array $orderIds, array $updates): array
    {
        $results = [
            'success' => 0,
            'failed' => 0,
            'errors' => []
        ];

        foreach ($orderIds as $orderId) {
            try {
                if (isset($updates['status'])) {
                    $this->updateOrderStatus($orderId, $updates['status'], $updates['notes'] ?? '');
                }

                $results['success']++;
            } catch (Exception $e) {
                $results['failed']++;
                $results['errors'][$orderId] = $e->getMessage();
            }
        }

        return $results;
    }
}
