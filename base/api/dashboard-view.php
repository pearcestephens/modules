/**
 * Dashboard View Toggle System
 * Switches between ENTIRE VIEW (all stores) and LOCAL VIEW (single store with feed)
 *
 * Files:
 * - This file: /modules/base/api/dashboard-view.php
 * - UI Toggle: /admin/ui/dashboard-view-toggle.js
 * - Styles: /admin/ui/css/dashboard-view.css
 * - Template: /admin/views/dashboard-local-view.php
 */

<?php

namespace Base\API;

use Base\Database\Database;
use Base\Auth\AuthManager;

class DashboardViewController {
    private $db;
    private $auth;
    private $userId;
    private $userOutletId;

    public function __construct() {
        $this->db = Database::getInstance();
        $this->auth = new AuthManager();
        $this->userId = $this->auth->getUserId();
        $this->userOutletId = $this->auth->getUserOutletId();
    }

    /**
     * Get dashboard view mode (entire or local)
     * GET /api/dashboard/view-mode
     */
    public function getViewMode() {
        try {
            // Get user's saved preference
            $preference = $this->db->query(
                "SELECT view_mode FROM user_preferences
                 WHERE user_id = ?
                 LIMIT 1",
                [$this->userId]
            );

            $viewMode = $preference ? $preference[0]['view_mode'] : 'entire';

            return [
                'status' => 'ok',
                'viewMode' => $viewMode,
                'currentOutletId' => $this->userOutletId,
                'timestamp' => date('c')
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Set dashboard view mode
     * POST /api/dashboard/view-mode
     */
    public function setViewMode() {
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            $viewMode = $data['viewMode'] ?? 'entire';

            if (!in_array($viewMode, ['entire', 'local'])) {
                throw new \Exception('Invalid view mode');
            }

            // Save preference
            $this->db->query(
                "INSERT INTO user_preferences (user_id, view_mode, updated_at)
                 VALUES (?, ?, NOW())
                 ON DUPLICATE KEY UPDATE
                 view_mode = VALUES(view_mode),
                 updated_at = NOW()",
                [$this->userId, $viewMode]
            );

            return [
                'status' => 'ok',
                'viewMode' => $viewMode,
                'message' => "Switched to " . ucfirst($viewMode) . " view"
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Get local store dashboard data (with feed)
     * GET /api/dashboard/local?outlet_id=123
     */
    public function getLocalDashboard() {
        try {
            $outletId = $_GET['outlet_id'] ?? $this->userOutletId;

            // Verify user has access to this outlet
            $hasAccess = $this->verifyOutletAccess($outletId);
            if (!$hasAccess) {
                throw new \Exception('Access denied to this outlet');
            }

            return [
                'status' => 'ok',
                'view' => 'local',
                'outlet' => $this->getOutletInfo($outletId),
                'metrics' => $this->getLocalMetrics($outletId),
                'feed' => $this->getLocalFeed($outletId),
                'alerts' => $this->getLocalAlerts($outletId),
                'staff' => $this->getOutletStaff($outletId),
                'recentOrders' => $this->getRecentOrders($outletId),
                'inventory' => $this->getLocalInventory($outletId),
                'timestamp' => date('c')
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Get entire network dashboard data
     * GET /api/dashboard/entire
     */
    public function getEntireDashboard() {
        try {
            return [
                'status' => 'ok',
                'view' => 'entire',
                'networkMetrics' => $this->getNetworkMetrics(),
                'topPerformers' => $this->getTopPerformingOutlets(),
                'networkFeed' => $this->getNetworkFeed(),
                'networkAlerts' => $this->getNetworkAlerts(),
                'outlets' => $this->getAllOutlets(),
                'overallStats' => $this->getOverallStats(),
                'timestamp' => date('c')
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }

    // ==================== LOCAL VIEW METHODS ====================

    private function getOutletInfo($outletId) {
        $result = $this->db->query(
            "SELECT id, name, address, city, phone, manager_id,
                    employee_count, monthly_revenue
             FROM outlets WHERE id = ?",
            [$outletId]
        );
        return $result ? $result[0] : null;
    }

    private function getLocalMetrics($outletId) {
        return [
            'todayRevenue' => $this->getTodayRevenue($outletId),
            'todayOrders' => $this->getTodayOrderCount($outletId),
            'todayCustomers' => $this->getTodayCustomerCount($outletId),
            'weekRevenue' => $this->getWeekRevenue($outletId),
            'monthRevenue' => $this->getMonthRevenue($outletId),
            'averageOrderValue' => $this->getAverageOrderValue($outletId),
            'topProducts' => $this->getTopProducts($outletId, 5)
        ];
    }

    private function getLocalFeed($outletId) {
        /**
         * Returns activity feed for specific outlet
         * Combines:
         * - News items relevant to this outlet
         * - Local staff activity
         * - Inventory updates
         * - Order activity
         * - Customer interactions
         */

        $feedItems = [];

        // Get news aggregator items for this outlet
        $newsItems = $this->db->query(
            "SELECT id, title, content, category, created_at
             FROM news_feeds
             WHERE outlet_id = ? OR outlet_id IS NULL
             ORDER BY created_at DESC
             LIMIT 10",
            [$outletId]
        );

        // Get recent orders
        $orders = $this->db->query(
            "SELECT id, customer_name, total, created_at
             FROM orders
             WHERE outlet_id = ?
             ORDER BY created_at DESC
             LIMIT 10",
            [$outletId]
        );

        // Get staff activity
        $staffActivity = $this->db->query(
            "SELECT id, user_id, action, timestamp
             FROM activity_log
             WHERE outlet_id = ?
             ORDER BY timestamp DESC
             LIMIT 10",
            [$outletId]
        );

        // Combine and sort by timestamp
        foreach ($newsItems as $item) {
            $feedItems[] = [
                'type' => 'news',
                'title' => $item['title'],
                'content' => $item['content'],
                'category' => $item['category'],
                'timestamp' => $item['created_at'],
                'icon' => 'newspaper'
            ];
        }

        foreach ($orders as $order) {
            $feedItems[] = [
                'type' => 'order',
                'title' => "Order from {$order['customer_name']}",
                'content' => "Amount: \$" . number_format($order['total'], 2),
                'timestamp' => $order['created_at'],
                'icon' => 'shopping-cart'
            ];
        }

        // Sort by timestamp descending
        usort($feedItems, function($a, $b) {
            return strtotime($b['timestamp']) - strtotime($a['timestamp']);
        });

        return array_slice($feedItems, 0, 15);
    }

    private function getLocalAlerts($outletId) {
        /**
         * Get alerts specific to this outlet:
         * - Low stock items
         * - Staff issues
         * - Revenue alerts
         * - Customer complaints
         * - System issues
         */

        $alerts = [];

        // Low stock
        $lowStock = $this->db->query(
            "SELECT product_id, name, quantity, reorder_level
             FROM inventory
             WHERE outlet_id = ? AND quantity < reorder_level",
            [$outletId]
        );

        foreach ($lowStock as $item) {
            $alerts[] = [
                'type' => 'warning',
                'category' => 'inventory',
                'title' => 'Low Stock Alert',
                'message' => "{$item['name']} is below reorder level",
                'severity' => 'medium',
                'timestamp' => date('c'),
                'actionUrl' => "/inventory?outlet={$outletId}"
            ];
        }

        return $alerts;
    }

    private function getRecentOrders($outletId, $limit = 10) {
        return $this->db->query(
            "SELECT id, customer_name, total, status, created_at
             FROM orders
             WHERE outlet_id = ?
             ORDER BY created_at DESC
             LIMIT ?",
            [$outletId, $limit]
        );
    }

    private function getLocalInventory($outletId) {
        return $this->db->query(
            "SELECT product_id, name, quantity, reorder_level
             FROM inventory
             WHERE outlet_id = ?
             ORDER BY quantity ASC
             LIMIT 20",
            [$outletId]
        );
    }

    private function getOutletStaff($outletId) {
        return $this->db->query(
            "SELECT id, name, email, role, active
             FROM users
             WHERE outlet_id = ? AND active = 1
             ORDER BY name",
            [$outletId]
        );
    }

    // ==================== ENTIRE VIEW METHODS ====================

    private function getNetworkMetrics() {
        return [
            'todayNetworkRevenue' => $this->getTodayNetworkRevenue(),
            'todayNetworkOrders' => $this->getTodayNetworkOrderCount(),
            'todayNetworkCustomers' => $this->getTodayNetworkCustomerCount(),
            'weekNetworkRevenue' => $this->getWeekNetworkRevenue(),
            'monthNetworkRevenue' => $this->getMonthNetworkRevenue(),
            'avgOrderValue' => $this->getNetworkAverageOrderValue(),
            'outletCount' => $this->getActiveOutletCount()
        ];
    }

    private function getTopPerformingOutlets() {
        return $this->db->query(
            "SELECT id, name, revenue_today, revenue_week, revenue_month,
                    employee_count
             FROM outlets
             ORDER BY revenue_month DESC
             LIMIT 10"
        );
    }

    private function getNetworkFeed() {
        /**
         * Get feed items across entire network
         * - Major news announcements
         * - Best performing outlets
         * - Network-wide promotions
         * - Company milestones
         */

        return $this->db->query(
            "SELECT id, title, content, category, created_at
             FROM news_feeds
             WHERE outlet_id IS NULL
             ORDER BY created_at DESC
             LIMIT 20"
        );
    }

    private function getNetworkAlerts() {
        // System-wide alerts, critical issues
        return $this->db->query(
            "SELECT id, title, message, severity, created_at
             FROM alerts
             WHERE network_wide = 1
             ORDER BY created_at DESC
             LIMIT 10"
        );
    }

    private function getAllOutlets() {
        return $this->db->query(
            "SELECT id, name, city, active, employee_count,
                    revenue_month, status
             FROM outlets
             ORDER BY name"
        );
    }

    private function getOverallStats() {
        $result = $this->db->query(
            "SELECT
                COUNT(DISTINCT o.id) as outlet_count,
                COUNT(DISTINCT u.id) as staff_count,
                SUM(o.revenue_month) as total_monthly_revenue,
                AVG(o.revenue_month) as avg_outlet_revenue
             FROM outlets o
             JOIN users u ON u.outlet_id = o.id
             WHERE o.active = 1"
        );

        return $result ? $result[0] : [];
    }

    // ==================== HELPER METHODS ====================

    private function verifyOutletAccess($outletId) {
        // Check if user belongs to this outlet or is admin
        $result = $this->db->query(
            "SELECT id FROM users
             WHERE id = ? AND (outlet_id = ? OR role = 'admin')",
            [$this->userId, $outletId]
        );
        return !empty($result);
    }

    private function getTodayRevenue($outletId) {
        $result = $this->db->query(
            "SELECT SUM(total) as revenue FROM orders
             WHERE outlet_id = ? AND DATE(created_at) = CURDATE()",
            [$outletId]
        );
        return $result ? floatval($result[0]['revenue']) : 0;
    }

    private function getTodayOrderCount($outletId) {
        $result = $this->db->query(
            "SELECT COUNT(*) as count FROM orders
             WHERE outlet_id = ? AND DATE(created_at) = CURDATE()",
            [$outletId]
        );
        return $result ? intval($result[0]['count']) : 0;
    }

    private function getTodayCustomerCount($outletId) {
        $result = $this->db->query(
            "SELECT COUNT(DISTINCT customer_id) as count FROM orders
             WHERE outlet_id = ? AND DATE(created_at) = CURDATE()",
            [$outletId]
        );
        return $result ? intval($result[0]['count']) : 0;
    }

    private function getWeekRevenue($outletId) {
        $result = $this->db->query(
            "SELECT SUM(total) as revenue FROM orders
             WHERE outlet_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
            [$outletId]
        );
        return $result ? floatval($result[0]['revenue']) : 0;
    }

    private function getMonthRevenue($outletId) {
        $result = $this->db->query(
            "SELECT SUM(total) as revenue FROM orders
             WHERE outlet_id = ? AND YEAR(created_at) = YEAR(NOW())
             AND MONTH(created_at) = MONTH(NOW())",
            [$outletId]
        );
        return $result ? floatval($result[0]['revenue']) : 0;
    }

    private function getAverageOrderValue($outletId) {
        $result = $this->db->query(
            "SELECT AVG(total) as avg FROM orders
             WHERE outlet_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
            [$outletId]
        );
        return $result ? floatval($result[0]['avg']) : 0;
    }

    private function getTopProducts($outletId, $limit = 5) {
        return $this->db->query(
            "SELECT p.id, p.name, COUNT(oi.id) as sales_count, SUM(oi.quantity) as quantity_sold
             FROM products p
             JOIN order_items oi ON oi.product_id = p.id
             JOIN orders o ON o.id = oi.order_id
             WHERE o.outlet_id = ? AND o.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
             GROUP BY p.id
             ORDER BY sales_count DESC
             LIMIT ?",
            [$outletId, $limit]
        );
    }

    // Network-wide helpers
    private function getTodayNetworkRevenue() {
        $result = $this->db->query(
            "SELECT SUM(total) as revenue FROM orders
             WHERE DATE(created_at) = CURDATE()"
        );
        return $result ? floatval($result[0]['revenue']) : 0;
    }

    private function getTodayNetworkOrderCount() {
        $result = $this->db->query(
            "SELECT COUNT(*) as count FROM orders
             WHERE DATE(created_at) = CURDATE()"
        );
        return $result ? intval($result[0]['count']) : 0;
    }

    private function getTodayNetworkCustomerCount() {
        $result = $this->db->query(
            "SELECT COUNT(DISTINCT customer_id) as count FROM orders
             WHERE DATE(created_at) = CURDATE()"
        );
        return $result ? intval($result[0]['count']) : 0;
    }

    private function getWeekNetworkRevenue() {
        $result = $this->db->query(
            "SELECT SUM(total) as revenue FROM orders
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
        );
        return $result ? floatval($result[0]['revenue']) : 0;
    }

    private function getMonthNetworkRevenue() {
        $result = $this->db->query(
            "SELECT SUM(total) as revenue FROM orders
             WHERE YEAR(created_at) = YEAR(NOW())
             AND MONTH(created_at) = MONTH(NOW())"
        );
        return $result ? floatval($result[0]['revenue']) : 0;
    }

    private function getNetworkAverageOrderValue() {
        $result = $this->db->query(
            "SELECT AVG(total) as avg FROM orders
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
        );
        return $result ? floatval($result[0]['avg']) : 0;
    }

    private function getActiveOutletCount() {
        $result = $this->db->query(
            "SELECT COUNT(*) as count FROM outlets WHERE active = 1"
        );
        return $result ? intval($result[0]['count']) : 0;
    }
}

// API routing
$controller = new DashboardViewController();
$endpoint = $_GET['endpoint'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

header('Content-Type: application/json');

$response = match($endpoint) {
    'view-mode' => match($method) {
        'GET' => $controller->getViewMode(),
        'POST' => $controller->setViewMode(),
        default => ['status' => 'error', 'message' => 'Method not allowed']
    },
    'local' => $controller->getLocalDashboard(),
    'entire' => $controller->getEntireDashboard(),
    default => ['status' => 'error', 'message' => 'Unknown endpoint']
};

echo json_encode($response);
