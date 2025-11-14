<?php
/**
 * Product Service
 *
 * Handles product management, Vend sync, and website integration.
 * Replaces functionality from edit-website-product.php
 *
 * @package CIS\Modules\EcommerceOps\Services
 */

namespace CIS\Modules\EcommerceOps;

class ProductService {

    private $db;
    private $vendAPI;

    /**
     * Constructor
     */
    public function __construct() {
        global $conn, $VendAPI;
        $this->db = $conn;
        $this->vendAPI = $VendAPI;
    }

    /**
     * Get product by ID
     *
     * @param int $productId
     * @return array|null
     */
    public function getProduct(int $productId): ?array {
        $stmt = $this->db->prepare("
            SELECT
                p.*,
                b.name as brand_name,
                pt.name as product_type_name,
                s.name as supplier_name
            FROM vend_products p
            LEFT JOIN vend_brands b ON p.brand_id = b.id
            LEFT JOIN vend_product_types pt ON p.product_type_id = pt.id
            LEFT JOIN vend_suppliers s ON p.supplier_id = s.id
            WHERE p.id = ?
        ");

        $stmt->execute([$productId]);
        $product = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($product) {
            // Get inventory levels across all outlets
            $product['inventory'] = $this->getProductInventory($productId);
        }

        return $product ?: null;
    }

    /**
     * Get product by SKU
     *
     * @param string $sku
     * @return array|null
     */
    public function getProductBySku(string $sku): ?array {
        $stmt = $this->db->prepare("SELECT * FROM vend_products WHERE sku = ?");
        $stmt->execute([$sku]);
        $product = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($product) {
            $product['inventory'] = $this->getProductInventory($product['id']);
        }

        return $product ?: null;
    }

    /**
     * Get product inventory across outlets
     *
     * @param int $productId
     * @return array
     */
    public function getProductInventory(int $productId): array {
        $stmt = $this->db->prepare("
            SELECT
                i.*,
                o.name as outlet_name
            FROM vend_inventory i
            LEFT JOIN vend_outlets o ON i.outlet_id = o.id
            WHERE i.product_id = ?
        ");

        $stmt->execute([$productId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * List products with filters and pagination
     *
     * @param array $filters
     * @param int $page
     * @param int $perPage
     * @return array
     */
    public function listProducts(array $filters = [], int $page = 1, int $perPage = 50): array {
        $where = ['1=1'];
        $params = [];

        // Search filter
        if (!empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $where[] = "(p.name LIKE ? OR p.sku LIKE ? OR p.handle LIKE ?)";
            $params = array_merge($params, [$search, $search, $search]);
        }

        // Brand filter
        if (!empty($filters['brand_id'])) {
            $where[] = "p.brand_id = ?";
            $params[] = $filters['brand_id'];
        }

        // Product type filter
        if (!empty($filters['product_type_id'])) {
            $where[] = "p.product_type_id = ?";
            $params[] = $filters['product_type_id'];
        }

        // Supplier filter
        if (!empty($filters['supplier_id'])) {
            $where[] = "p.supplier_id = ?";
            $params[] = $filters['supplier_id'];
        }

        // Active/inactive filter
        if (isset($filters['active'])) {
            $where[] = "p.active = ?";
            $params[] = $filters['active'] ? 1 : 0;
        }

        // Low stock filter
        if (!empty($filters['low_stock'])) {
            $where[] = "EXISTS (
                SELECT 1 FROM vend_inventory i
                WHERE i.product_id = p.id
                AND i.count < p.reorder_point
            )";
        }

        // Out of stock filter
        if (!empty($filters['out_of_stock'])) {
            $where[] = "NOT EXISTS (
                SELECT 1 FROM vend_inventory i
                WHERE i.product_id = p.id
                AND i.count > 0
            )";
        }

        $whereSQL = implode(' AND ', $where);
        $offset = ($page - 1) * $perPage;

        // Get total count
        $countStmt = $this->db->prepare("
            SELECT COUNT(DISTINCT p.id)
            FROM vend_products p
            WHERE $whereSQL
        ");
        $countStmt->execute($params);
        $total = $countStmt->fetchColumn();

        // Get products
        $stmt = $this->db->prepare("
            SELECT
                p.*,
                b.name as brand_name,
                pt.name as product_type_name,
                s.name as supplier_name,
                SUM(i.count) as total_inventory
            FROM vend_products p
            LEFT JOIN vend_brands b ON p.brand_id = b.id
            LEFT JOIN vend_product_types pt ON p.product_type_id = pt.id
            LEFT JOIN vend_suppliers s ON p.supplier_id = s.id
            LEFT JOIN vend_inventory i ON p.id = i.product_id
            WHERE $whereSQL
            GROUP BY p.id
            ORDER BY p.name ASC
            LIMIT ? OFFSET ?
        ");

        $params[] = $perPage;
        $params[] = $offset;
        $stmt->execute($params);

        return [
            'products' => $stmt->fetchAll(\PDO::FETCH_ASSOC),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => ceil($total / $perPage)
        ];
    }

    /**
     * Update product
     *
     * @param int $productId
     * @param array $data
     * @return bool
     */
    public function updateProduct(int $productId, array $data): bool {
        $allowedFields = [
            'name', 'description', 'sku', 'handle', 'active',
            'brand_id', 'product_type_id', 'supplier_id',
            'retail_price', 'supply_price', 'tax_rate',
            'reorder_point', 'reorder_amount'
        ];

        $updates = [];
        $params = [];

        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $updates[] = "$field = ?";
                $params[] = $data[$field];
            }
        }

        if (empty($updates)) {
            return false;
        }

        $updates[] = "updated_at = NOW()";
        $params[] = $productId;

        $sql = "UPDATE vend_products SET " . implode(', ', $updates) . " WHERE id = ?";
        $stmt = $this->db->prepare($sql);

        $result = $stmt->execute($params);

        if ($result) {
            ecomm_log_error("Product updated", [
                'product_id' => $productId,
                'fields' => array_keys($data)
            ]);
        }

        return $result;
    }

    /**
     * Sync product from Vend
     *
     * @param string $vendProductId
     * @return bool
     */
    public function syncFromVend(string $vendProductId): bool {
        if (!$this->vendAPI) {
            return false;
        }

        try {
            $vendProduct = $this->vendAPI->getProduct($vendProductId);

            if (!$vendProduct) {
                return false;
            }

            // Check if product exists
            $stmt = $this->db->prepare("SELECT id FROM vend_products WHERE vend_product_id = ?");
            $stmt->execute([$vendProductId]);
            $existing = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($existing) {
                // Update existing product
                $stmt = $this->db->prepare("
                    UPDATE vend_products SET
                        name = ?,
                        description = ?,
                        sku = ?,
                        handle = ?,
                        active = ?,
                        brand_id = ?,
                        product_type_id = ?,
                        supplier_id = ?,
                        retail_price = ?,
                        supply_price = ?,
                        tax_rate = ?,
                        updated_at = NOW()
                    WHERE vend_product_id = ?
                ");

                return $stmt->execute([
                    $vendProduct['name'] ?? '',
                    $vendProduct['description'] ?? null,
                    $vendProduct['sku'] ?? '',
                    $vendProduct['handle'] ?? null,
                    $vendProduct['active'] ?? 1,
                    $vendProduct['brand_id'] ?? null,
                    $vendProduct['product_type_id'] ?? null,
                    $vendProduct['supplier_id'] ?? null,
                    $vendProduct['retail_price'] ?? 0,
                    $vendProduct['supply_price'] ?? 0,
                    $vendProduct['tax_rate'] ?? 0,
                    $vendProductId
                ]);
            } else {
                // Insert new product
                $stmt = $this->db->prepare("
                    INSERT INTO vend_products (
                        vend_product_id, name, description, sku, handle, active,
                        brand_id, product_type_id, supplier_id, retail_price,
                        supply_price, tax_rate, created_at, updated_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                ");

                return $stmt->execute([
                    $vendProductId,
                    $vendProduct['name'] ?? '',
                    $vendProduct['description'] ?? null,
                    $vendProduct['sku'] ?? '',
                    $vendProduct['handle'] ?? null,
                    $vendProduct['active'] ?? 1,
                    $vendProduct['brand_id'] ?? null,
                    $vendProduct['product_type_id'] ?? null,
                    $vendProduct['supplier_id'] ?? null,
                    $vendProduct['retail_price'] ?? 0,
                    $vendProduct['supply_price'] ?? 0,
                    $vendProduct['tax_rate'] ?? 0
                ]);
            }
        } catch (\Exception $e) {
            ecomm_log_error("Failed to sync product from Vend", [
                'vend_product_id' => $vendProductId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Get product performance statistics
     *
     * @param int $productId
     * @param array $filters
     * @return array
     */
    public function getPerformance(int $productId, array $filters = []): array {
        $where = ['sp.product_id = ?'];
        $params = [$productId];

        if (!empty($filters['date_from'])) {
            $where[] = "s.order_date >= ?";
            $params[] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where[] = "s.order_date <= ?";
            $params[] = $filters['date_to'] . ' 23:59:59';
        }
        if (!empty($filters['outlet_id'])) {
            $where[] = "s.outlet_id = ?";
            $params[] = $filters['outlet_id'];
        }

        $whereSQL = implode(' AND ', $where);

        $stmt = $this->db->prepare("
            SELECT
                COUNT(DISTINCT s.id) as total_orders,
                SUM(sp.quantity) as total_quantity_sold,
                SUM(sp.price * sp.quantity) as total_revenue,
                AVG(sp.price) as average_price
            FROM vend_sale_products sp
            JOIN vend_sales s ON sp.sale_id = s.id
            WHERE $whereSQL
        ");

        $stmt->execute($params);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Get low stock products
     *
     * @param int $outletId Optional outlet filter
     * @param int $limit
     * @return array
     */
    public function getLowStockProducts(?int $outletId = null, int $limit = 50): array {
        $where = ['i.count < p.reorder_point', 'p.active = 1'];
        $params = [];

        if ($outletId) {
            $where[] = "i.outlet_id = ?";
            $params[] = $outletId;
        }

        $params[] = $limit;

        $whereSQL = implode(' AND ', $where);

        $stmt = $this->db->prepare("
            SELECT
                p.*,
                i.count as current_stock,
                i.outlet_id,
                o.name as outlet_name,
                (p.reorder_point - i.count) as units_needed
            FROM vend_products p
            JOIN vend_inventory i ON p.id = i.product_id
            LEFT JOIN vend_outlets o ON i.outlet_id = o.id
            WHERE $whereSQL
            ORDER BY (p.reorder_point - i.count) DESC
            LIMIT ?
        ");

        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get brands list
     *
     * @return array
     */
    public function getBrands(): array {
        $stmt = $this->db->prepare("SELECT * FROM vend_brands ORDER BY name ASC");
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get product types list
     *
     * @return array
     */
    public function getProductTypes(): array {
        $stmt = $this->db->prepare("SELECT * FROM vend_product_types ORDER BY name ASC");
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get suppliers list
     *
     * @return array
     */
    public function getSuppliers(): array {
        $stmt = $this->db->prepare("SELECT * FROM vend_suppliers ORDER BY name ASC");
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
