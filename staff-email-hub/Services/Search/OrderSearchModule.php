<?php
/**
 * Order Search Module
 *
 * Smart order search with customer linkage, status tracking,
 * payment history, and fulfillment intelligence.
 *
 * @package StaffEmailHub\Services\Search
 */

namespace StaffEmailHub\Services\Search;

class OrderSearchModule
{
    private $db;
    private $logger;
    private $staffId;

    public function __construct($db, $logger, $staffId)
    {
        $this->db = $db;
        $this->logger = $logger;
        $this->staffId = $staffId;
    }

    public function search(array $parsedQuery, array $options = []): array
    {
        try {
            $sql = "
                SELECT
                    o.*,
                    c.name as customer_name,
                    c.email as customer_email,
                    COUNT(DISTINCT oi.id) as item_count,
                    SUM(oi.quantity * oi.price) as order_total
                FROM orders o
                LEFT JOIN customers c ON o.customer_id = c.id
                LEFT JOIN order_items oi ON o.id = oi.order_id
                WHERE 1=1
            ";

            $params = [];

            // Keyword search
            if (!empty($parsedQuery['keywords'])) {
                $keywords = implode(' ', $parsedQuery['keywords']);
                $sql .= " AND (
                    o.order_number LIKE ?
                    OR c.name LIKE ?
                    OR o.notes LIKE ?
                )";
                $params[] = "%{$keywords}%";
                $params[] = "%{$keywords}%";
                $params[] = "%{$keywords}%";
            }

            // Order ID entity
            if (!empty($parsedQuery['entities']['order_id'])) {
                $orderId = str_replace(['ORD', 'ORDER', '#', '-'], '', $parsedQuery['entities']['order_id'][0]);
                $sql .= " AND o.order_number LIKE ?";
                $params[] = "%{$orderId}%";
            }

            // Status filter
            if (isset($parsedQuery['filters']['status'])) {
                $sql .= " AND o.status = ?";
                $params[] = $parsedQuery['filters']['status'];
            }

            // Date filter
            if (isset($parsedQuery['filters']['date'])) {
                $dateFilter = $this->parseDateFilter($parsedQuery['filters']['date']);
                $sql .= " AND o.created_at >= ?";
                $params[] = $dateFilter;
            }

            $sql .= " GROUP BY o.id ORDER BY o.created_at DESC LIMIT 50";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            return ['results' => $results, 'total' => count($results)];

        } catch (\Exception $e) {
            $this->logger->error('Order search failed', ['error' => $e->getMessage()]);
            return ['results' => [], 'total' => 0];
        }
    }

    private function parseDateFilter(string $filter): string
    {
        switch ($filter) {
            case 'today': return date('Y-m-d 00:00:00');
            case 'last_week': return date('Y-m-d 00:00:00', strtotime('-7 days'));
            case 'last_month': return date('Y-m-d 00:00:00', strtotime('-30 days'));
            default: return date('Y-m-d 00:00:00', strtotime('-30 days'));
        }
    }
}
