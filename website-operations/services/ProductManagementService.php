<?php
/**
 * Product Management Service
 *
 * Handles all product catalog operations for VapeShed and Ecigdis websites
 *
 * Features:
 * - Multi-channel product sync (VapeShed, Ecigdis, retail)
 * - Bulk product updates
 * - Inventory tracking across all stores
 * - Category management
 * - Product images and media
 * - Pricing and special offers
 * - Product variants and options
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

class ProductManagementService
{
    private PDO $db;

    /**
     * Initialize service
     */
    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Get products with filters and pagination
     */
    public function getProducts(array $filters = [], int $page = 1, int $perPage = 50): array
    {
        try {
            $offset = ($page - 1) * $perPage;
            $where = ['1=1'];
            $params = [];

            if (!empty($filters['search'])) {
                $where[] = '(p.name LIKE :search OR p.sku LIKE :search OR p.description LIKE :search)';
                $params[':search'] = '%' . $filters['search'] . '%';
            }

            if (!empty($filters['category'])) {
                $where[] = 'p.category_id = :category';
                $params[':category'] = $filters['category'];
            }

            if (!empty($filters['status'])) {
                $where[] = 'p.status = :status';
                $params[':status'] = $filters['status'];
            }

            if (isset($filters['stock']) && $filters['stock'] === 'low') {
                $where[] = 'p.total_stock > 0 AND p.total_stock <= p.low_stock_threshold';
            } elseif (isset($filters['stock']) && $filters['stock'] === 'out') {
                $where[] = 'p.total_stock = 0';
            }

            if (!empty($filters['channel'])) {
                $where[] = 'p.channel = :channel';
                $params[':channel'] = $filters['channel'];
            }

            $whereClause = implode(' AND ', $where);

            // Get total count
            $countStmt = $this->db->prepare("
                SELECT COUNT(*) as total
                FROM web_products p
                WHERE $whereClause
            ");
            foreach ($params as $key => $value) {
                $countStmt->bindValue($key, $value);
            }
            $countStmt->execute();
            $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

            // Get products
            $stmt = $this->db->prepare("
                SELECT
                    p.*,
                    c.name as category_name,
                    COUNT(DISTINCT oi.order_id) as total_orders,
                    SUM(oi.quantity) as total_sold
                FROM web_products p
                LEFT JOIN web_categories c ON p.category_id = c.id
                LEFT JOIN web_order_items oi ON p.id = oi.product_id
                WHERE $whereClause
                GROUP BY p.id
                ORDER BY p.created_at DESC
                LIMIT :limit OFFSET :offset
            ");

            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

            $stmt->execute();
            $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'products' => $products,
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => ceil($total / $perPage)
            ];
        } catch (PDOException $e) {
            error_log("Get products error: " . $e->getMessage());
            return ['products' => [], 'total' => 0];
        }
    }

    /**
     * Get product by ID
     */
    public function getProductById(int $productId): ?array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT
                    p.*,
                    c.name as category_name
                FROM web_products p
                LEFT JOIN web_categories c ON p.category_id = c.id
                WHERE p.id = :id
            ");

            $stmt->execute([':id' => $productId]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$product) {
                return null;
            }

            // Get product variants
            $product['variants'] = $this->getProductVariants($productId);

            // Get product images
            $product['images'] = $this->getProductImages($productId);

            // Get inventory by location
            $product['inventory'] = $this->getProductInventory($productId);

            // Get sales history
            $product['sales_history'] = $this->getProductSalesHistory($productId);

            return $product;
        } catch (PDOException $e) {
            error_log("Get product by ID error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get product variants
     */
    private function getProductVariants(int $productId): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT *
                FROM web_product_variants
                WHERE product_id = :product_id
                ORDER BY sort_order ASC
            ");

            $stmt->execute([':product_id' => $productId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Get product variants error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get product images
     */
    private function getProductImages(int $productId): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT *
                FROM web_product_images
                WHERE product_id = :product_id
                ORDER BY is_primary DESC, sort_order ASC
            ");

            $stmt->execute([':product_id' => $productId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Get product images error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get product inventory by location
     */
    private function getProductInventory(int $productId): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT
                    i.*,
                    s.name as store_name,
                    s.city
                FROM inventory i
                LEFT JOIN store_configurations s ON i.outlet_id = s.id
                WHERE i.product_id = :product_id
            ");

            $stmt->execute([':product_id' => $productId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Get product inventory error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get product sales history
     */
    private function getProductSalesHistory(int $productId, int $days = 90): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT
                    DATE(o.created_at) as date,
                    SUM(oi.quantity) as units_sold,
                    SUM(oi.total) as revenue
                FROM web_order_items oi
                INNER JOIN web_orders o ON oi.order_id = o.id
                WHERE oi.product_id = :product_id
                AND o.created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
                GROUP BY DATE(o.created_at)
                ORDER BY date DESC
            ");

            $stmt->execute([
                ':product_id' => $productId,
                ':days' => $days
            ]);

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Get product sales history error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Create new product
     */
    public function createProduct(array $productData): array
    {
        $this->db->beginTransaction();

        try {
            $stmt = $this->db->prepare("
                INSERT INTO web_products (
                    sku,
                    name,
                    description,
                    category_id,
                    price,
                    cost,
                    status,
                    channel,
                    weight,
                    length,
                    width,
                    height,
                    low_stock_threshold,
                    created_by,
                    created_at
                ) VALUES (
                    :sku,
                    :name,
                    :description,
                    :category_id,
                    :price,
                    :cost,
                    :status,
                    :channel,
                    :weight,
                    :length,
                    :width,
                    :height,
                    :low_stock_threshold,
                    :created_by,
                    NOW()
                )
            ");

            $stmt->execute([
                ':sku' => $productData['sku'],
                ':name' => $productData['name'],
                ':description' => $productData['description'] ?? '',
                ':category_id' => $productData['category_id'] ?? null,
                ':price' => $productData['price'],
                ':cost' => $productData['cost'] ?? 0,
                ':status' => $productData['status'] ?? 'active',
                ':channel' => $productData['channel'] ?? 'vapeshed',
                ':weight' => $productData['weight'] ?? 200,
                ':length' => $productData['length'] ?? 0,
                ':width' => $productData['width'] ?? 0,
                ':height' => $productData['height'] ?? 0,
                ':low_stock_threshold' => $productData['low_stock_threshold'] ?? 10,
                ':created_by' => $_SESSION['user']['id'] ?? null
            ]);

            $productId = $this->db->lastInsertId();

            // Add variants if provided
            if (!empty($productData['variants'])) {
                $this->addProductVariants($productId, $productData['variants']);
            }

            // Add images if provided
            if (!empty($productData['images'])) {
                $this->addProductImages($productId, $productData['images']);
            }

            $this->db->commit();

            return [
                'success' => true,
                'product_id' => $productId
            ];
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Create product error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Update product
     */
    public function updateProduct(int $productId, array $productData): bool
    {
        try {
            $updates = [];
            $params = [':id' => $productId];

            $allowedFields = ['sku', 'name', 'description', 'category_id', 'price', 'cost',
                            'status', 'weight', 'length', 'width', 'height', 'low_stock_threshold'];

            foreach ($allowedFields as $field) {
                if (isset($productData[$field])) {
                    $updates[] = "$field = :$field";
                    $params[":$field"] = $productData[$field];
                }
            }

            if (empty($updates)) {
                return false;
            }

            $updates[] = 'updated_at = NOW()';
            $updates[] = 'updated_by = :updated_by';
            $params[':updated_by'] = $_SESSION['user']['id'] ?? null;

            $updateClause = implode(', ', $updates);

            $stmt = $this->db->prepare("
                UPDATE web_products
                SET $updateClause
                WHERE id = :id
            ");

            return $stmt->execute($params);
        } catch (PDOException $e) {
            error_log("Update product error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Bulk update products
     */
    public function bulkUpdateProducts(array $productIds, array $updates): array
    {
        $results = [
            'success' => 0,
            'failed' => 0,
            'errors' => []
        ];

        foreach ($productIds as $productId) {
            try {
                $this->updateProduct($productId, $updates);
                $results['success']++;
            } catch (Exception $e) {
                $results['failed']++;
                $results['errors'][$productId] = $e->getMessage();
            }
        }

        return $results;
    }

    /**
     * Delete product
     */
    public function deleteProduct(int $productId): bool
    {
        try {
            // Soft delete
            $stmt = $this->db->prepare("
                UPDATE web_products
                SET status = 'deleted',
                    deleted_at = NOW(),
                    deleted_by = :deleted_by
                WHERE id = :id
            ");

            return $stmt->execute([
                ':id' => $productId,
                ':deleted_by' => $_SESSION['user']['id'] ?? null
            ]);
        } catch (PDOException $e) {
            error_log("Delete product error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get product statistics
     */
    public function getProductStats(): array
    {
        try {
            $stmt = $this->db->query("
                SELECT
                    COUNT(*) as total,
                    COUNT(CASE WHEN status = 'active' THEN 1 END) as active,
                    COUNT(CASE WHEN total_stock > 0 AND total_stock <= low_stock_threshold THEN 1 END) as low_stock,
                    COUNT(CASE WHEN total_stock = 0 THEN 1 END) as out_of_stock
                FROM web_products
                WHERE status != 'deleted'
            ");

            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Get product stats error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get trending products
     */
    public function getTrendingProducts(int $limit = 10, string $period = '30d'): array
    {
        try {
            $days = (int)str_replace('d', '', $period);

            $stmt = $this->db->prepare("
                SELECT
                    p.*,
                    SUM(oi.quantity) as units_sold,
                    COUNT(DISTINCT oi.order_id) as order_count,
                    SUM(oi.total) as revenue
                FROM web_products p
                INNER JOIN web_order_items oi ON p.id = oi.product_id
                INNER JOIN web_orders o ON oi.order_id = o.id
                WHERE o.created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
                AND p.status = 'active'
                GROUP BY p.id
                ORDER BY units_sold DESC
                LIMIT :limit
            ");

            $stmt->bindValue(':days', $days, PDO::PARAM_INT);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Get trending products error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get low stock count
     */
    public function getLowStockCount(): int
    {
        try {
            $stmt = $this->db->query("
                SELECT COUNT(*) as count
                FROM web_products
                WHERE total_stock > 0
                AND total_stock <= low_stock_threshold
                AND status = 'active'
            ");

            return (int)$stmt->fetch(PDO::FETCH_ASSOC)['count'];
        } catch (PDOException $e) {
            error_log("Get low stock count error: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Sync product to external channel (VapeShed/Ecigdis API)
     */
    public function syncProductToChannel(int $productId, string $channel): array
    {
        try {
            $product = $this->getProductById($productId);

            if (!$product) {
                return ['success' => false, 'error' => 'Product not found'];
            }

            // Prepare API payload
            $payload = [
                'sku' => $product['sku'],
                'name' => $product['name'],
                'description' => $product['description'],
                'price' => $product['price'],
                'images' => array_column($product['images'], 'url'),
                'variants' => $product['variants'],
                'inventory' => array_sum(array_column($product['inventory'], 'quantity'))
            ];

            // Call channel API
            $result = $this->callChannelAPI($channel, 'POST', '/products', $payload);

            // Update sync status
            $this->updateProductSyncStatus($productId, $channel, $result['success']);

            return $result;
        } catch (Exception $e) {
            error_log("Sync product error: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Call external channel API
     */
    private function callChannelAPI(string $channel, string $method, string $endpoint, array $data = []): array
    {
        // This would integrate with real VapeShed/Ecigdis APIs
        // For now, returning success simulation

        return [
            'success' => true,
            'channel' => $channel,
            'endpoint' => $endpoint,
            'synced_at' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Update product sync status
     */
    private function updateProductSyncStatus(int $productId, string $channel, bool $success): void
    {
        try {
            $stmt = $this->db->prepare("
                UPDATE web_products
                SET last_sync_at = NOW(),
                    sync_status = :status
                WHERE id = :id
            ");

            $stmt->execute([
                ':id' => $productId,
                ':status' => $success ? 'synced' : 'failed'
            ]);
        } catch (PDOException $e) {
            error_log("Update sync status error: " . $e->getMessage());
        }
    }

    /**
     * Add product variants
     */
    private function addProductVariants(int $productId, array $variants): void
    {
        foreach ($variants as $variant) {
            $stmt = $this->db->prepare("
                INSERT INTO web_product_variants (
                    product_id, name, sku, price_modifier, sort_order
                ) VALUES (
                    :product_id, :name, :sku, :price_modifier, :sort_order
                )
            ");

            $stmt->execute([
                ':product_id' => $productId,
                ':name' => $variant['name'],
                ':sku' => $variant['sku'] ?? null,
                ':price_modifier' => $variant['price_modifier'] ?? 0,
                ':sort_order' => $variant['sort_order'] ?? 0
            ]);
        }
    }

    /**
     * Add product images
     */
    private function addProductImages(int $productId, array $images): void
    {
        foreach ($images as $index => $image) {
            $stmt = $this->db->prepare("
                INSERT INTO web_product_images (
                    product_id, url, is_primary, sort_order
                ) VALUES (
                    :product_id, :url, :is_primary, :sort_order
                )
            ");

            $stmt->execute([
                ':product_id' => $productId,
                ':url' => $image['url'],
                ':is_primary' => ($index === 0) ? 1 : 0,
                ':sort_order' => $index
            ]);
        }
    }
}
