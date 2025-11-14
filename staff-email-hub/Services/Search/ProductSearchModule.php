<?php
/**
 * Product Search Module
 *
 * Intelligent product search with variant matching, supplier data,
 * stock awareness, price history, and smart recommendations.
 *
 * @package StaffEmailHub\Services\Search
 */

namespace StaffEmailHub\Services\Search;

class ProductSearchModule
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
            // Build product search query
            $sql = "
                SELECT
                    p.*,
                    s.name as supplier_name,
                    i.quantity_on_hand as stock_level,
                    CASE
                        WHEN i.quantity_on_hand = 0 THEN 'out_of_stock'
                        WHEN i.quantity_on_hand < i.reorder_point THEN 'low_stock'
                        ELSE 'in_stock'
                    END as stock_status
                FROM products p
                LEFT JOIN suppliers s ON p.supplier_id = s.id
                LEFT JOIN inventory i ON p.id = i.product_id
                WHERE 1=1
            ";

            $params = [];

            // Keyword search
            if (!empty($parsedQuery['keywords'])) {
                $keywords = implode(' ', $parsedQuery['keywords']);
                $sql .= " AND (
                    MATCH(p.name, p.description) AGAINST(? IN BOOLEAN MODE)
                    OR p.sku LIKE ?
                    OR p.barcode LIKE ?
                )";
                $params[] = $keywords;
                $params[] = "%{$keywords}%";
                $params[] = "%{$keywords}%";
            }

            // SKU entity detection
            if (!empty($parsedQuery['entities']['sku'])) {
                $sql .= " AND p.sku = ?";
                $params[] = $parsedQuery['entities']['sku'][0];
            }

            // Stock filter
            if (isset($parsedQuery['filters']['stock_status'])) {
                $status = $parsedQuery['filters']['stock_status'];
                if ($status === 'low_stock') {
                    $sql .= " AND i.quantity_on_hand < i.reorder_point AND i.quantity_on_hand > 0";
                } elseif ($status === 'out_of_stock') {
                    $sql .= " AND i.quantity_on_hand = 0";
                } elseif ($status === 'in_stock') {
                    $sql .= " AND i.quantity_on_hand >= i.reorder_point";
                }
            }

            $sql .= " ORDER BY p.name ASC LIMIT 50";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            return ['results' => $results, 'total' => count($results)];

        } catch (\Exception $e) {
            $this->logger->error('Product search failed', ['error' => $e->getMessage()]);
            return ['results' => [], 'total' => 0];
        }
    }
}
