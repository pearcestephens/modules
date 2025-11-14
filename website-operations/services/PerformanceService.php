<?php
/**
 * Performance Service
 *
 * Tracks and reports on key performance metrics
 *
 * @package    WebsiteOperations
 * @version    1.0.0
 */

namespace Modules\WebsiteOperations\Services;

use PDO;
use PDOException;

class PerformanceService
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Get key metrics
     */
    public function getKeyMetrics(string $dateRange, string $outlet = 'all'): array
    {
        $days = $this->parseDateRange($dateRange);
        $outletFilter = ($outlet === 'all') ? '' : "AND outlet_id = :outlet";

        try {
            $stmt = $this->db->prepare("
                SELECT
                    AVG(TIMESTAMPDIFF(HOUR, created_at, updated_at)) as avg_processing_hours,
                    AVG(total_amount) as avg_order_value,
                    COUNT(*) as total_orders
                FROM web_orders
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
                $outletFilter
            ");

            $stmt->bindValue(':days', $days, PDO::PARAM_INT);
            if ($outlet !== 'all') {
                $stmt->bindValue(':outlet', $outlet, PDO::PARAM_INT);
            }

            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Get key metrics error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get average processing time in hours
     */
    public function getAverageProcessingTime(int $days): float
    {
        try {
            $stmt = $this->db->prepare("
                SELECT AVG(TIMESTAMPDIFF(HOUR, created_at, updated_at)) as avg_hours
                FROM web_orders
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
                AND status = 'completed'
            ");

            $stmt->execute([':days' => $days]);
            return (float)$stmt->fetch(PDO::FETCH_ASSOC)['avg_hours'];
        } catch (PDOException $e) {
            return 0;
        }
    }

    /**
     * Get on-time delivery rate
     */
    public function getOnTimeRate(int $days): float
    {
        try {
            $stmt = $this->db->prepare("
                SELECT
                    COUNT(CASE WHEN delivered_at <= expected_delivery_at THEN 1 END) / COUNT(*) * 100 as rate
                FROM web_orders
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
                AND status = 'delivered'
            ");

            $stmt->execute([':days' => $days]);
            return (float)$stmt->fetch(PDO::FETCH_ASSOC)['rate'];
        } catch (PDOException $e) {
            return 0;
        }
    }

    /**
     * Get fulfillment metrics
     */
    public function getFulfillmentMetrics(string $dateRange, string $outlet): array
    {
        $days = $this->parseDateRange($dateRange);

        try {
            $stmt = $this->db->prepare("
                SELECT
                    COUNT(*) as total_orders,
                    SUM(shipping_cost) as total_shipping_cost,
                    SUM(shipping_cost_saved) as total_saved,
                    AVG(TIMESTAMPDIFF(HOUR, created_at, shipped_at)) as avg_ship_time
                FROM web_orders
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
            ");

            $stmt->execute([':days' => $days]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }

    private function parseDateRange(string $range): int
    {
        $map = ['7d' => 7, '30d' => 30, '90d' => 90, '1y' => 365];
        return $map[$range] ?? 30;
    }
}
