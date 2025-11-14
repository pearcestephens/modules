<?php
/**
 * Customer Search Module
 *
 * Fuzzy customer search with email/phone lookup, purchase history,
 * and intelligent segmentation.
 *
 * @package StaffEmailHub\Services\Search
 */

namespace StaffEmailHub\Services\Search;

class CustomerSearchModule
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
                    c.*,
                    COUNT(DISTINCT o.id) as order_count,
                    SUM(o.total_amount) as lifetime_value,
                    MAX(o.created_at) as last_order_date
                FROM customers c
                LEFT JOIN orders o ON c.id = o.customer_id
                WHERE 1=1
            ";

            $params = [];

            // Keyword/name search with fuzzy matching
            if (!empty($parsedQuery['keywords'])) {
                $keywords = implode(' ', $parsedQuery['keywords']);
                $sql .= " AND (
                    c.name LIKE ?
                    OR c.email LIKE ?
                    OR c.phone LIKE ?
                    OR c.company LIKE ?
                )";
                $params[] = "%{$keywords}%";
                $params[] = "%{$keywords}%";
                $params[] = "%{$keywords}%";
                $params[] = "%{$keywords}%";
            }

            // Email entity
            if (!empty($parsedQuery['entities']['email'])) {
                $sql .= " AND c.email = ?";
                $params[] = $parsedQuery['entities']['email'][0];
            }

            // Phone entity
            if (!empty($parsedQuery['entities']['phone'])) {
                $phone = preg_replace('/\D/', '', $parsedQuery['entities']['phone'][0]);
                $sql .= " AND REPLACE(REPLACE(REPLACE(c.phone, ' ', ''), '-', ''), '+', '') LIKE ?";
                $params[] = "%{$phone}%";
            }

            $sql .= " GROUP BY c.id ORDER BY c.name ASC LIMIT 50";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Add customer segment
            foreach ($results as &$result) {
                $result['segment'] = $this->getCustomerSegment($result);
            }

            return ['results' => $results, 'total' => count($results)];

        } catch (\Exception $e) {
            $this->logger->error('Customer search failed', ['error' => $e->getMessage()]);
            return ['results' => [], 'total' => 0];
        }
    }

    private function getCustomerSegment(array $customer): string
    {
        $lifetimeValue = $customer['lifetime_value'] ?? 0;
        $orderCount = $customer['order_count'] ?? 0;

        if ($lifetimeValue > 10000 || $orderCount > 50) {
            return 'vip';
        } elseif ($lifetimeValue > 5000 || $orderCount > 20) {
            return 'regular';
        } elseif ($orderCount === 0) {
            return 'prospect';
        } else {
            return 'occasional';
        }
    }
}
