<?php
/**
 * Order Management Service
 *
 * Handles all web order operations across retail and wholesale channels
 */

class OrderManagementService {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Get all orders with filtering
     */
    public function getOrders($website = null, $status = null, $limit = 100, $offset = 0, $search = null) {
        $sql = "SELECT * FROM web_orders WHERE 1=1 ";
        $params = [];

        if ($website) {
            $sql .= "AND website = ? ";
            $params[] = $website;
        }

        if ($status) {
            $sql .= "AND status = ? ";
            $params[] = $status;
        }

        if ($search) {
            $sql .= "AND (order_number LIKE ? OR customer_name LIKE ? OR customer_email LIKE ?) ";
            $search = "%{$search}%";
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
        }

        $sql .= "ORDER BY created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Get order by ID
     */
    public function getOrderById($orderId) {
        $sql = "SELECT * FROM web_orders WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$orderId]);
        return $stmt->fetch(PDO::FETCH_OBJ);
    }

    /**
     * Get order items
     */
    public function getOrderItems($orderId) {
        $sql = "SELECT * FROM web_order_items WHERE order_id = ? ORDER BY position";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$orderId]);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Update order status
     */
    public function updateOrderStatus($orderId, $status, $notes = null) {
        $sql = "UPDATE web_orders SET status = ?, updated_at = NOW() ";
        $params = [$status];

        if ($notes) {
            $sql .= ", notes = CONCAT(IFNULL(notes, ''), '\n[' . NOW() . '] " . $notes . "') ";
        }

        $sql .= "WHERE id = ?";
        $params[] = $orderId;

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Get order statistics
     */
    public function getOrderStats($website = null, $days = 30) {
        $dateFrom = date('Y-m-d', strtotime("-{$days} days"));

        $sql = "SELECT
                    COUNT(*) as total_orders,
                    SUM(total) as total_revenue,
                    AVG(total) as avg_order_value,
                    COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_orders,
                    COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_orders,
                    COUNT(CASE WHEN status = 'processing' THEN 1 END) as processing_orders,
                    COUNT(CASE WHEN status = 'failed' THEN 1 END) as failed_orders,
                    COUNT(CASE WHEN status = 'abandoned' THEN 1 END) as abandoned_orders
                FROM web_orders
                WHERE DATE(created_at) >= ? ";

        $params = [$dateFrom];

        if ($website) {
            $sql .= "AND website = ? ";
            $params[] = $website;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_OBJ);
    }

    /**
     * Get orders for date
     */
    public function getOrdersForDate($date, $website = null) {
        $sql = "SELECT * FROM web_orders WHERE DATE(created_at) = ? ";
        $params = [$date];

        if ($website) {
            $sql .= "AND website = ? ";
            $params[] = $website;
        }

        $sql .= "ORDER BY created_at DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Get processing time metrics
     */
    public function getProcessingTimeMetrics($website = null) {
        $sql = "SELECT
                    AVG(TIMESTAMPDIFF(MINUTE, created_at, shipped_at)) as avg_minutes,
                    MIN(TIMESTAMPDIFF(MINUTE, created_at, shipped_at)) as min_minutes,
                    MAX(TIMESTAMPDIFF(MINUTE, created_at, shipped_at)) as max_minutes,
                    STDDEV(TIMESTAMPDIFF(MINUTE, created_at, shipped_at)) as stddev
                FROM web_orders
                WHERE shipped_at IS NOT NULL AND status = 'completed' ";

        $params = [];

        if ($website) {
            $sql .= "AND website = ? ";
            $params[] = $website;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_OBJ);
    }

    /**
     * Create order note
     */
    public function addOrderNote($orderId, $note, $userId) {
        $sql = "INSERT INTO web_order_notes (order_id, note, created_by, created_at)
                VALUES (?, ?, ?, NOW())";

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$orderId, $note, $userId]);
    }

    /**
     * Get order notes
     */
    public function getOrderNotes($orderId) {
        $sql = "SELECT * FROM web_order_notes WHERE order_id = ? ORDER BY created_at DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$orderId]);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Get problematic orders (failures, stuck, etc)
     */
    public function getProblematicOrders($website = null) {
        $sql = "SELECT * FROM web_orders WHERE status IN ('failed', 'pending')
                AND created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR) ";
        $params = [];

        if ($website) {
            $sql .= "AND website = ? ";
            $params[] = $website;
        }

        $sql .= "ORDER BY created_at ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Get recently completed orders
     */
    public function getRecentlyCompletedOrders($website = null, $limit = 20) {
        $sql = "SELECT * FROM web_orders
                WHERE status = 'completed'
                AND completed_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) ";
        $params = [];

        if ($website) {
            $sql .= "AND website = ? ";
            $params[] = $website;
        }

        $sql .= "ORDER BY completed_at DESC LIMIT ?";
        $params[] = $limit;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Get order performance by outlet
     */
    public function getOrderPerformanceByOutlet($website = null) {
        $sql = "SELECT
                    outlet_id,
                    COUNT(*) as total_orders,
                    SUM(total) as total_revenue,
                    AVG(total) as avg_order_value,
                    AVG(TIMESTAMPDIFF(MINUTE, created_at, shipped_at)) as avg_process_time
                FROM web_orders
                WHERE outlet_id IS NOT NULL AND status = 'completed'
                AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) ";

        $params = [];

        if ($website) {
            $sql .= "AND website = ? ";
            $params[] = $website;
        }

        $sql .= "GROUP BY outlet_id ORDER BY total_revenue DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Export orders to CSV
     */
    public function exportOrders($website = null, $dateFrom = null, $dateTo = null) {
        $sql = "SELECT * FROM web_orders WHERE 1=1 ";
        $params = [];

        if ($website) {
            $sql .= "AND website = ? ";
            $params[] = $website;
        }

        if ($dateFrom && $dateTo) {
            $sql .= "AND created_at BETWEEN ? AND ? ";
            $params[] = $dateFrom . ' 00:00:00';
            $params[] = $dateTo . ' 23:59:59';
        }

        $sql .= "ORDER BY created_at DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }
}
