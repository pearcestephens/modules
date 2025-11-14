<?php
/**
 * Web Operations Service
 *
 * Core service for managing web operations across all retail sites
 */

class WebOperationsService {
    private $pdo;
    private $cache = [];

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Get dashboard data for website
     */
    public function getDashboardData($website) {
        $cacheKey = "dashboard_{$website}";

        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        $data = new stdClass();

        // Get today's stats
        $data->ordersToday = $this->getOrdersForDate(date('Y-m-d'), $website);
        $data->revenueToday = $this->getRevenueForDate(date('Y-m-d'), $website);
        $data->visitorsToday = $this->getVisitorsForDate(date('Y-m-d'), $website);

        // Get weekly data
        $data->weeklyOrders = $this->getOrdersForDateRange(
            date('Y-m-d', strtotime('-7 days')),
            date('Y-m-d'),
            $website
        );
        $data->weeklyRevenue = $this->getRevenueForDateRange(
            date('Y-m-d', strtotime('-7 days')),
            date('Y-m-d'),
            $website
        );

        // Get KPIs
        $data->conversionRate = $this->getConversionRate($website);
        $data->avgOrderValue = $this->getAverageOrderValue($website);
        $data->customerCount = $this->getActiveCustomerCount($website);
        $data->productCount = $this->getActiveProductCount($website);

        // Get alerts
        $data->alerts = $this->getOperationalAlerts($website);

        $this->cache[$cacheKey] = $data;
        return $data;
    }

    /**
     * Get orders for specific date
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
     * Get revenue for date
     */
    public function getRevenueForDate($date, $website = null) {
        $sql = "SELECT SUM(total) as revenue FROM web_orders
                WHERE DATE(created_at) = ? AND status != 'cancelled' ";
        $params = [$date];

        if ($website) {
            $sql .= "AND website = ? ";
            $params[] = $website;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch(PDO::FETCH_OBJ);
        return $result->revenue ?? 0;
    }

    /**
     * Get visitors for date
     */
    public function getVisitorsForDate($date, $website = null) {
        $sql = "SELECT COUNT(DISTINCT session_id) as visitors FROM web_analytics
                WHERE DATE(timestamp) = ? ";
        $params = [$date];

        if ($website) {
            $sql .= "AND website = ? ";
            $params[] = $website;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch(PDO::FETCH_OBJ);
        return $result->visitors ?? 0;
    }

    /**
     * Get orders for date range
     */
    public function getOrdersForDateRange($dateFrom, $dateTo, $website = null) {
        $sql = "SELECT DATE(created_at) as date, COUNT(*) as count, SUM(total) as revenue
                FROM web_orders
                WHERE created_at BETWEEN ? AND ? AND status != 'cancelled' ";
        $params = [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'];

        if ($website) {
            $sql .= "AND website = ? ";
            $params[] = $website;
        }

        $sql .= "GROUP BY DATE(created_at) ORDER BY date";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Get revenue for date range
     */
    public function getRevenueForDateRange($dateFrom, $dateTo, $website = null) {
        $orders = $this->getOrdersForDateRange($dateFrom, $dateTo, $website);
        return array_sum(array_map(fn($o) => $o->revenue, $orders));
    }

    /**
     * Get conversion rate
     */
    public function getConversionRate($website = null) {
        $sql = "SELECT
                    COUNT(DISTINCT CASE WHEN status NOT IN ('abandoned', 'failed') THEN session_id END) as conversions,
                    COUNT(DISTINCT session_id) as visitors
                FROM web_analytics
                WHERE DATE(timestamp) = ? ";
        $params = [date('Y-m-d')];

        if ($website) {
            $sql .= "AND website = ? ";
            $params[] = $website;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch(PDO::FETCH_OBJ);

        if ($result->visitors == 0) return 0;
        return round(($result->conversions / $result->visitors) * 100, 2);
    }

    /**
     * Get average order value
     */
    public function getAverageOrderValue($website = null) {
        $sql = "SELECT AVG(total) as avg_value FROM web_orders
                WHERE DATE(created_at) = ? AND status != 'cancelled' ";
        $params = [date('Y-m-d')];

        if ($website) {
            $sql .= "AND website = ? ";
            $params[] = $website;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch(PDO::FETCH_OBJ);
        return round($result->avg_value ?? 0, 2);
    }

    /**
     * Get active customer count
     */
    public function getActiveCustomerCount($website = null) {
        $sql = "SELECT COUNT(DISTINCT customer_id) as count FROM web_orders
                WHERE DATE(created_at) >= ? ";
        $params = [date('Y-m-d', strtotime('-30 days'))];

        if ($website) {
            $sql .= "AND website = ? ";
            $params[] = $website;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch(PDO::FETCH_OBJ);
        return $result->count ?? 0;
    }

    /**
     * Get active product count
     */
    public function getActiveProductCount($website = null) {
        $sql = "SELECT COUNT(*) as count FROM web_products
                WHERE status = 1 ";

        if ($website) {
            $sql .= "AND website = ? ";
        }

        $stmt = $this->pdo->prepare($sql);
        if ($website) {
            $stmt->execute([$website]);
        } else {
            $stmt->execute();
        }

        $result = $stmt->fetch(PDO::FETCH_OBJ);
        return $result->count ?? 0;
    }

    /**
     * Get operational alerts
     */
    public function getOperationalAlerts($website = null) {
        $alerts = [];

        // Check for pending orders
        $pending = $this->countPendingOrders($website);
        if ($pending > 5) {
            $alerts[] = [
                'type' => 'warning',
                'message' => "You have $pending pending orders",
                'action' => 'view-orders',
                'severity' => $pending > 20 ? 'high' : 'medium'
            ];
        }

        // Check for low stock
        $lowStock = $this->countLowStockProducts($website);
        if ($lowStock > 0) {
            $alerts[] = [
                'type' => 'warning',
                'message' => "$lowStock products are low in stock",
                'action' => 'view-inventory',
                'severity' => 'medium'
            ];
        }

        // Check for pending reviews
        $pendingReviews = $this->countPendingReviews($website);
        if ($pendingReviews > 0) {
            $alerts[] = [
                'type' => 'info',
                'message' => "$pendingReviews reviews pending approval",
                'action' => 'view-reviews',
                'severity' => 'low'
            ];
        }

        return $alerts;
    }

    /**
     * Get user permissions
     */
    public function getUserPermissions($userId) {
        $sql = "SELECT permissions FROM user_permissions WHERE user_id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$userId]);
        $result = $stmt->fetch(PDO::FETCH_OBJ);

        if ($result && $result->permissions) {
            return json_decode($result->permissions, true);
        }

        return [];
    }

    /**
     * Count pending orders
     */
    private function countPendingOrders($website = null) {
        $sql = "SELECT COUNT(*) as count FROM web_orders WHERE status IN ('pending', 'processing') ";
        $params = [];

        if ($website) {
            $sql .= "AND website = ? ";
            $params[] = $website;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch(PDO::FETCH_OBJ);
        return $result->count ?? 0;
    }

    /**
     * Count low stock products
     */
    private function countLowStockProducts($website = null) {
        $sql = "SELECT COUNT(*) as count FROM web_products
                WHERE quantity <= reorder_level AND status = 1 ";
        $params = [];

        if ($website) {
            $sql .= "AND website = ? ";
            $params[] = $website;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch(PDO::FETCH_OBJ);
        return $result->count ?? 0;
    }

    /**
     * Count pending reviews
     */
    private function countPendingReviews($website = null) {
        $sql = "SELECT COUNT(*) as count FROM web_reviews WHERE status = 'pending' ";
        $params = [];

        if ($website) {
            $sql .= "AND website = ? ";
            $params[] = $website;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch(PDO::FETCH_OBJ);
        return $result->count ?? 0;
    }

    /**
     * Get website list
     */
    public function getWebsites() {
        return [
            ['code' => 'vapeshed', 'name' => 'Vape Shed', 'url' => 'https://www.vapeshed.co.nz'],
            ['code' => 'vapingkiwi', 'name' => 'Vaping Kiwi', 'url' => 'https://www.vapingkiwi.co.nz'],
            ['code' => 'vapehq', 'name' => 'VapeHQ', 'url' => 'https://www.vapehq.co.nz'],
            ['code' => 'wholesale', 'name' => 'Wholesale Portal', 'url' => 'https://wholesale.vapeshed.co.nz']
        ];
    }
}
