<?php
/**
 * Website Operations Service
 *
 * Enterprise-grade service for managing all website operations across
 * VapeShed.co.nz, Ecigdis.co.nz, and retail integrations
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

class WebsiteOperationsService
{
    private PDO $db;
    private array $config;
    private ?object $currentUser;

    // Service dependencies
    private OrderManagementService $orderService;
    private ProductManagementService $productService;
    private CustomerManagementService $customerService;
    private WholesaleService $wholesaleService;
    private PerformanceService $performanceService;

    /**
     * Initialize the service
     */
    public function __construct(PDO $db, array $config = [])
    {
        $this->db = $db;
        $this->config = $config;
        $this->currentUser = $_SESSION['user'] ?? null;

        // Initialize sub-services
        $this->orderService = new OrderManagementService($db);
        $this->productService = new ProductManagementService($db);
        $this->customerService = new CustomerManagementService($db);
        $this->wholesaleService = new WholesaleService($db);
        $this->performanceService = new PerformanceService($db);
    }

    /**
     * Get comprehensive dashboard data
     */
    public function getDashboardData(array $filters = []): array
    {
        try {
            $dateRange = $filters['date_range'] ?? '30d';
            $outlet = $filters['outlet'] ?? 'all';

            return [
                'summary' => $this->getDashboardSummary($dateRange, $outlet),
                'orders' => $this->orderService->getRecentOrders(20, $outlet),
                'performance' => $this->performanceService->getKeyMetrics($dateRange, $outlet),
                'alerts' => $this->getSystemAlerts(),
                'trending_products' => $this->productService->getTrendingProducts(10, $dateRange),
                'revenue' => $this->getRevenueData($dateRange, $outlet),
                'fulfillment' => $this->getFulfillmentMetrics($dateRange, $outlet)
            ];
        } catch (Exception $e) {
            error_log("Dashboard data error: " . $e->getMessage());
            return ['error' => 'Failed to load dashboard data'];
        }
    }

    /**
     * Get dashboard summary statistics
     */
    private function getDashboardSummary(string $dateRange, string $outlet): array
    {
        $days = $this->parseDateRange($dateRange);
        $outletFilter = ($outlet === 'all') ? '' : "AND outlet_id = :outlet";

        try {
            // Orders summary
            $stmt = $this->db->prepare("
                SELECT
                    COUNT(*) as total_orders,
                    COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_orders,
                    COUNT(CASE WHEN status = 'processing' THEN 1 END) as processing_orders,
                    COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_orders,
                    COUNT(CASE WHEN status = 'cancelled' THEN 1 END) as cancelled_orders,
                    SUM(total_amount) as total_revenue,
                    AVG(total_amount) as average_order_value,
                    COUNT(DISTINCT customer_id) as unique_customers
                FROM web_orders
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
                $outletFilter
            ");

            $stmt->bindValue(':days', $days, PDO::PARAM_INT);
            if ($outlet !== 'all') {
                $stmt->bindValue(':outlet', $outlet, PDO::PARAM_INT);
            }

            $stmt->execute();
            $orderStats = $stmt->fetch(PDO::FETCH_ASSOC);

            // Product stats
            $productStats = $this->productService->getProductStats();

            // Customer stats
            $customerStats = $this->customerService->getCustomerStats($days);

            return [
                'orders' => [
                    'total' => (int)$orderStats['total_orders'],
                    'pending' => (int)$orderStats['pending_orders'],
                    'processing' => (int)$orderStats['processing_orders'],
                    'completed' => (int)$orderStats['completed_orders'],
                    'cancelled' => (int)$orderStats['cancelled_orders']
                ],
                'revenue' => [
                    'total' => (float)$orderStats['total_revenue'],
                    'average_order' => (float)$orderStats['average_order_value'],
                    'growth' => $this->calculateGrowth('revenue', $days)
                ],
                'customers' => [
                    'total' => $customerStats['total'],
                    'active' => $customerStats['active'],
                    'new' => $customerStats['new'],
                    'wholesale' => $customerStats['wholesale']
                ],
                'products' => [
                    'total' => $productStats['total'],
                    'active' => $productStats['active'],
                    'low_stock' => $productStats['low_stock'],
                    'out_of_stock' => $productStats['out_of_stock']
                ],
                'fulfillment' => [
                    'average_processing_time' => $this->performanceService->getAverageProcessingTime($days),
                    'on_time_delivery_rate' => $this->performanceService->getOnTimeRate($days)
                ]
            ];
        } catch (PDOException $e) {
            error_log("Summary stats error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get revenue data for charts
     */
    private function getRevenueData(string $dateRange, string $outlet): array
    {
        $days = $this->parseDateRange($dateRange);
        $outletFilter = ($outlet === 'all') ? '' : "AND outlet_id = :outlet";

        try {
            $stmt = $this->db->prepare("
                SELECT
                    DATE(created_at) as date,
                    COUNT(*) as orders,
                    SUM(total_amount) as revenue,
                    AVG(total_amount) as avg_order
                FROM web_orders
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
                $outletFilter
                GROUP BY DATE(created_at)
                ORDER BY date ASC
            ");

            $stmt->bindValue(':days', $days, PDO::PARAM_INT);
            if ($outlet !== 'all') {
                $stmt->bindValue(':outlet', $outlet, PDO::PARAM_INT);
            }

            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Revenue data error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get fulfillment metrics
     */
    private function getFulfillmentMetrics(string $dateRange, string $outlet): array
    {
        return $this->performanceService->getFulfillmentMetrics($dateRange, $outlet);
    }

    /**
     * Get system alerts
     */
    private function getSystemAlerts(): array
    {
        $alerts = [];

        // Check for low stock items
        $lowStock = $this->productService->getLowStockCount();
        if ($lowStock > 0) {
            $alerts[] = [
                'type' => 'warning',
                'title' => 'Low Stock Alert',
                'message' => "$lowStock products are running low on stock",
                'action' => '/modules/website-operations/views/products.php?filter=low_stock',
                'priority' => 'medium'
            ];
        }

        // Check for pending orders
        $pendingOrders = $this->orderService->getPendingOrderCount();
        if ($pendingOrders > 10) {
            $alerts[] = [
                'type' => 'info',
                'title' => 'Pending Orders',
                'message' => "$pendingOrders orders waiting for processing",
                'action' => '/modules/website-operations/views/orders.php?status=pending',
                'priority' => 'high'
            ];
        }

        // Check for wholesale account approvals
        $pendingWholesale = $this->wholesaleService->getPendingApprovalCount();
        if ($pendingWholesale > 0) {
            $alerts[] = [
                'type' => 'info',
                'title' => 'Wholesale Approvals',
                'message' => "$pendingWholesale wholesale accounts need approval",
                'action' => '/modules/website-operations/views/wholesale.php?filter=pending',
                'priority' => 'medium'
            ];
        }

        // Check API connection status
        $apiStatus = $this->checkApiConnections();
        foreach ($apiStatus as $api => $status) {
            if (!$status['connected']) {
                $alerts[] = [
                    'type' => 'error',
                    'title' => 'API Connection Issue',
                    'message' => "$api API is not responding",
                    'action' => '/modules/website-operations/views/settings.php?tab=integrations',
                    'priority' => 'critical'
                ];
            }
        }

        return $alerts;
    }

    /**
     * Check API connection status
     */
    private function checkApiConnections(): array
    {
        return [
            'vapeshed' => [
                'connected' => $this->testApiConnection('vapeshed'),
                'last_check' => date('Y-m-d H:i:s')
            ],
            'ecigdis' => [
                'connected' => $this->testApiConnection('ecigdis'),
                'last_check' => date('Y-m-d H:i:s')
            ]
        ];
    }

    /**
     * Test API connection
     */
    private function testApiConnection(string $api): bool
    {
        $config = $this->config['integrations'][$api] ?? null;
        if (!$config || !$config['enabled']) {
            return false;
        }

        try {
            $ch = curl_init($config['base_url'] . '/api/health');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            return ($httpCode >= 200 && $httpCode < 300);
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Calculate growth percentage
     */
    private function calculateGrowth(string $metric, int $days): float
    {
        try {
            $currentPeriod = "
                SELECT SUM(total_amount) as total
                FROM web_orders
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL $days DAY)
            ";

            $previousPeriod = "
                SELECT SUM(total_amount) as total
                FROM web_orders
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL " . ($days * 2) . " DAY)
                AND created_at < DATE_SUB(NOW(), INTERVAL $days DAY)
            ";

            $current = $this->db->query($currentPeriod)->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
            $previous = $this->db->query($previousPeriod)->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

            if ($previous == 0) return 0;

            return round((($current - $previous) / $previous) * 100, 2);
        } catch (PDOException $e) {
            error_log("Growth calculation error: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Parse date range string to days
     */
    private function parseDateRange(string $range): int
    {
        $map = [
            '7d' => 7,
            '30d' => 30,
            '90d' => 90,
            '1y' => 365
        ];

        return $map[$range] ?? 30;
    }

    /**
     * Get order service
     */
    public function orders(): OrderManagementService
    {
        return $this->orderService;
    }

    /**
     * Get product service
     */
    public function products(): ProductManagementService
    {
        return $this->productService;
    }

    /**
     * Get customer service
     */
    public function customers(): CustomerManagementService
    {
        return $this->customerService;
    }

    /**
     * Get wholesale service
     */
    public function wholesale(): WholesaleService
    {
        return $this->wholesaleService;
    }

    /**
     * Get performance service
     */
    public function performance(): PerformanceService
    {
        return $this->performanceService;
    }
}
