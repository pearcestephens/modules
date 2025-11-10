<?php
/**
 * Vend API Integration for Stock Transfer Engine
 *
 * Extends the Intelligence Hub VendService with transfer-specific functionality:
 * - Stock level tracking for excess detection
 * - Sales history for velocity analysis
 * - Product details with logistics data
 * - Consignment/transfer operations
 * - Purchase order distribution
 *
 * @package CIS\Services\StockTransfers
 * @version 1.0.0
 */

namespace CIS\Services\StockTransfers;

use PDO;
use Exception;

class VendTransferAPI
{
    private $db;
    private $logger;
    private $vendApiUrl;
    private $vendApiToken;
    private $cacheEnabled = true;
    private $cacheTTL = 300; // 5 minutes
    private $rateLimitDelay = 500000; // 0.5 seconds between API calls (microseconds)
    private $lastApiCall = 0;

    public function __construct(PDO $db, $logger = null)
    {
        $this->db = $db;
        $this->logger = $logger;

        // Load Vend API credentials from environment
        $this->vendApiUrl = getenv('VEND_API_URL') ?: 'https://vapeshed.vendhq.com/api/2.0';
        $this->vendApiToken = getenv('VEND_API_TOKEN') ?: '';

        if (empty($this->vendApiToken)) {
            $this->log('warning', 'Vend API token not configured');
        }
    }

    /**
     * Pull current stock levels for all products at all outlets
     * Used by: Excess Detection Engine, Stock Velocity Tracker
     *
     * @param string|null $outletId Optional: Filter by specific outlet
     * @param string|null $productId Optional: Filter by specific product
     * @return array Stock levels with product and outlet details
     */
    public function pullStockLevels($outletId = null, $productId = null): array
    {
        try {
            $this->log('info', 'Pulling stock levels', [
                'outlet_id' => $outletId,
                'product_id' => $productId
            ]);

            $sql = "
                SELECT
                    pi.product_id,
                    p.name as product_name,
                    p.sku,
                    p.supply_price,
                    p.retail_price,
                    pi.outlet_id,
                    o.name as outlet_name,
                    o.outlet_type,
                    pi.count as stock_level,
                    pi.reorder_point,
                    pi.restock_level,
                    CASE
                        WHEN pi.count <= pi.reorder_point THEN 'critical'
                        WHEN pi.count <= pi.reorder_point * 1.5 THEN 'low'
                        WHEN pi.count >= pi.restock_level * 1.5 THEN 'overstock'
                        ELSE 'healthy'
                    END as stock_status,
                    ROUND((pi.count / NULLIF(pi.reorder_point, 0)) * 100, 2) as stock_percentage
                FROM vend_product_inventory pi
                JOIN vend_products p ON pi.product_id = p.id
                JOIN vend_outlets o ON pi.outlet_id = o.id
                WHERE p.deleted_at IS NULL
                AND p.active = 1
            ";

            $params = [];

            if ($outletId) {
                $sql .= " AND pi.outlet_id = ?";
                $params[] = $outletId;
            }

            if ($productId) {
                $sql .= " AND pi.product_id = ?";
                $params[] = $productId;
            }

            $sql .= " ORDER BY o.name, p.name";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            $stockLevels = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $this->log('info', 'Stock levels pulled', [
                'count' => count($stockLevels)
            ]);

            return $stockLevels;

        } catch (Exception $e) {
            $this->log('error', 'Failed to pull stock levels', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Pull sales history for velocity analysis
     * Used by: Stock Velocity Tracker, Excess Detection Engine
     *
     * @param array $params Parameters: date_from, date_to, outlet_id, product_id
     * @return array Sales data with quantities and dates
     */
    public function pullSalesHistory(array $params = []): array
    {
        $defaults = [
            'date_from' => date('Y-m-d', strtotime('-90 days')), // 90 days for velocity
            'date_to' => date('Y-m-d'),
            'outlet_id' => null,
            'product_id' => null
        ];

        $params = array_merge($defaults, $params);

        try {
            $this->log('info', 'Pulling sales history', $params);

            $sql = "
                SELECT
                    sl.product_id,
                    p.name as product_name,
                    p.sku,
                    s.outlet_id,
                    o.name as outlet_name,
                    DATE(s.sale_date) as sale_date,
                    SUM(sl.quantity) as quantity_sold,
                    COUNT(DISTINCT s.id) as transaction_count,
                    AVG(sl.price) as avg_price,
                    SUM(sl.price * sl.quantity) as total_revenue
                FROM vend_sale_lines sl
                JOIN vend_sales s ON sl.sale_id = s.id
                JOIN vend_products p ON sl.product_id = p.id
                JOIN vend_outlets o ON s.outlet_id = o.id
                WHERE s.sale_date >= ?
                AND s.sale_date <= ?
                AND s.status = 'CLOSED'
                AND s.deleted_at IS NULL
            ";

            $bindings = [$params['date_from'], $params['date_to']];

            if ($params['outlet_id']) {
                $sql .= " AND s.outlet_id = ?";
                $bindings[] = $params['outlet_id'];
            }

            if ($params['product_id']) {
                $sql .= " AND sl.product_id = ?";
                $bindings[] = $params['product_id'];
            }

            $sql .= "
                GROUP BY sl.product_id, s.outlet_id, DATE(s.sale_date)
                ORDER BY sale_date DESC, quantity_sold DESC
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($bindings);

            $salesHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $this->log('info', 'Sales history pulled', [
                'count' => count($salesHistory),
                'date_range' => "{$params['date_from']} to {$params['date_to']}"
            ]);

            return $salesHistory;

        } catch (Exception $e) {
            $this->log('error', 'Failed to pull sales history', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Pull product details with logistics data
     * Used by: Freight Calculator, Transfer Builder
     *
     * @param string $productId Vend product ID
     * @return array|null Product details with dimensions, weight, etc.
     */
    public function pullProductDetails(string $productId): ?array
    {
        try {
            $this->log('info', 'Pulling product details', ['product_id' => $productId]);

            $sql = "
                SELECT
                    p.id,
                    p.name,
                    p.sku,
                    p.handle,
                    p.brand_name,
                    p.supplier_name,
                    p.supplier_id,
                    p.supply_price,
                    p.retail_price,
                    p.active,
                    p.has_inventory,
                    p.variant_parent_id,
                    pl.weight_grams,
                    pl.length_cm,
                    pl.width_cm,
                    pl.height_cm,
                    pl.volumetric_weight,
                    pl.packaging_type,
                    pl.requires_bubble_wrap,
                    pl.is_fragile,
                    pl.is_hazmat,
                    pl.is_juice_product,
                    pl.ships_separately,
                    pl.max_items_per_box
                FROM vend_products p
                LEFT JOIN product_logistics pl ON p.id = pl.product_id
                WHERE p.id = ?
                AND p.deleted_at IS NULL
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$productId]);

            $product = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$product) {
                $this->log('warning', 'Product not found', ['product_id' => $productId]);
                return null;
            }

            $this->log('info', 'Product details pulled', [
                'product_id' => $productId,
                'sku' => $product['sku']
            ]);

            return $product;

        } catch (Exception $e) {
            $this->log('error', 'Failed to pull product details', [
                'product_id' => $productId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Get outlet details
     * Used by: Warehouse Manager, Freight Calculator
     *
     * @param string|null $outletId Optional: Specific outlet, or all if null
     * @return array Outlet details with freight zones
     */
    public function getOutletDetails($outletId = null): array
    {
        try {
            $this->log('info', 'Getting outlet details', ['outlet_id' => $outletId]);

            $sql = "
                SELECT
                    o.id,
                    o.name,
                    o.outlet_code,
                    ofz.outlet_type,
                    ofz.is_flagship,
                    ofz.is_hub_store,
                    ofz.can_manufacture_juice,
                    ofz.street_address,
                    ofz.suburb,
                    ofz.city,
                    ofz.postcode,
                    ofz.freight_zone,
                    ofz.is_rural,
                    ofz.distance_from_warehouse_km
                FROM vend_outlets o
                LEFT JOIN outlet_freight_zones ofz ON o.id = ofz.outlet_id
                WHERE o.deleted_at IS NULL
            ";

            $params = [];

            if ($outletId) {
                $sql .= " AND o.id = ?";
                $params[] = $outletId;
            }

            $sql .= " ORDER BY o.name";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            $outlets = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $this->log('info', 'Outlet details retrieved', [
                'count' => count($outlets)
            ]);

            return $outletId && count($outlets) === 1 ? $outlets[0] : $outlets;

        } catch (Exception $e) {
            $this->log('error', 'Failed to get outlet details', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Get active consignments (transfers in progress)
     * Used by: Smart Routing Engine (batching opportunities)
     *
     * @param array $params Filter parameters
     * @return array Active consignments
     */
    public function getConsignments(array $params = []): array
    {
        $defaults = [
            'status' => ['OPEN', 'SENT', 'RECEIVING'],
            'from_outlet_id' => null,
            'to_outlet_id' => null,
            'date_from' => date('Y-m-d', strtotime('-7 days'))
        ];

        $params = array_merge($defaults, $params);

        try {
            $this->log('info', 'Getting consignments', $params);

            $sql = "
                SELECT
                    c.id,
                    c.name,
                    c.type,
                    c.status,
                    c.source_outlet_id,
                    c.outlet_id as destination_outlet_id,
                    so.name as source_outlet_name,
                    do.name as destination_outlet_name,
                    c.due_at,
                    c.sent_at,
                    c.received_at,
                    c.total_cost,
                    COUNT(cp.id) as product_count,
                    SUM(cp.count) as total_units
                FROM vend_consignments c
                JOIN vend_outlets so ON c.source_outlet_id = so.id
                JOIN vend_outlets do ON c.outlet_id = do.id
                LEFT JOIN vend_consignment_product cp ON c.id = cp.consignment_id
                WHERE c.created_at >= ?
                AND c.deleted_at IS NULL
            ";

            $bindings = [$params['date_from']];

            if (!empty($params['status'])) {
                $placeholders = implode(',', array_fill(0, count($params['status']), '?'));
                $sql .= " AND c.status IN ($placeholders)";
                $bindings = array_merge($bindings, $params['status']);
            }

            if ($params['from_outlet_id']) {
                $sql .= " AND c.source_outlet_id = ?";
                $bindings[] = $params['from_outlet_id'];
            }

            if ($params['to_outlet_id']) {
                $sql .= " AND c.outlet_id = ?";
                $bindings[] = $params['to_outlet_id'];
            }

            $sql .= "
                GROUP BY c.id
                ORDER BY c.created_at DESC
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($bindings);

            $consignments = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $this->log('info', 'Consignments retrieved', [
                'count' => count($consignments)
            ]);

            return $consignments;

        } catch (Exception $e) {
            $this->log('error', 'Failed to get consignments', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Get purchase orders for auto-distribution
     * Used by: PurchaseOrderDistributionHandler
     *
     * @param array $params Filter parameters
     * @return array Purchase orders
     */
    public function getPurchaseOrders(array $params = []): array
    {
        $defaults = [
            'status' => ['RECEIVED', 'CLOSED'],
            'date_from' => date('Y-m-d', strtotime('-30 days')),
            'supplier_id' => null,
            'undistributed_only' => true
        ];

        $params = array_merge($defaults, $params);

        try {
            $this->log('info', 'Getting purchase orders', $params);

            $sql = "
                SELECT
                    c.id,
                    c.name,
                    c.type,
                    c.status,
                    c.supplier_name,
                    c.supplier_id,
                    c.outlet_id as receiving_warehouse_id,
                    o.name as warehouse_name,
                    c.received_at,
                    c.total_cost,
                    COUNT(cp.id) as product_count,
                    SUM(cp.count) as total_units,
                    cd.id as distribution_id,
                    cd.distribution_status
                FROM vend_consignments c
                JOIN vend_outlets o ON c.outlet_id = o.id
                LEFT JOIN vend_consignment_product cp ON c.id = cp.consignment_id
                LEFT JOIN consignment_distributions cd ON c.id = cd.consignment_id
                WHERE c.type = 'SUPPLIER'
                AND c.received_at >= ?
                AND c.deleted_at IS NULL
            ";

            $bindings = [$params['date_from']];

            if (!empty($params['status'])) {
                $placeholders = implode(',', array_fill(0, count($params['status']), '?'));
                $sql .= " AND c.status IN ($placeholders)";
                $bindings = array_merge($bindings, $params['status']);
            }

            if ($params['supplier_id']) {
                $sql .= " AND c.supplier_id = ?";
                $bindings[] = $params['supplier_id'];
            }

            if ($params['undistributed_only']) {
                $sql .= " AND (cd.id IS NULL OR cd.distribution_status != 'completed')";
            }

            $sql .= "
                GROUP BY c.id
                ORDER BY c.received_at DESC
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($bindings);

            $purchaseOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $this->log('info', 'Purchase orders retrieved', [
                'count' => count($purchaseOrders)
            ]);

            return $purchaseOrders;

        } catch (Exception $e) {
            $this->log('error', 'Failed to get purchase orders', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Update stock levels after transfer completion
     * Used by: Transfer Execution Engine
     *
     * @param string $transferId Stock transfer ID
     * @return bool Success status
     */
    public function updateStockOnTransfer(string $transferId): bool
    {
        try {
            $this->log('info', 'Updating stock on transfer', ['transfer_id' => $transferId]);

            // Get transfer details
            $stmt = $this->db->prepare("
                SELECT from_outlet_id, to_outlet_id, status
                FROM stock_transfers
                WHERE id = ?
            ");
            $stmt->execute([$transferId]);
            $transfer = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$transfer) {
                throw new Exception("Transfer not found: {$transferId}");
            }

            if ($transfer['status'] !== 'completed') {
                throw new Exception("Transfer not completed: {$transferId}");
            }

            // Get transfer items
            $stmt = $this->db->prepare("
                SELECT product_id, quantity_delivered
                FROM stock_transfer_items
                WHERE transfer_id = ?
            ");
            $stmt->execute([$transferId]);
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $this->db->beginTransaction();

            foreach ($items as $item) {
                // Reduce stock at source
                $stmt = $this->db->prepare("
                    UPDATE vend_product_inventory
                    SET count = count - ?
                    WHERE product_id = ?
                    AND outlet_id = ?
                ");
                $stmt->execute([
                    $item['quantity_delivered'],
                    $item['product_id'],
                    $transfer['from_outlet_id']
                ]);

                // Increase stock at destination
                $stmt = $this->db->prepare("
                    INSERT INTO vend_product_inventory
                    (product_id, outlet_id, count)
                    VALUES (?, ?, ?)
                    ON DUPLICATE KEY UPDATE count = count + ?
                ");
                $stmt->execute([
                    $item['product_id'],
                    $transfer['to_outlet_id'],
                    $item['quantity_delivered'],
                    $item['quantity_delivered']
                ]);
            }

            $this->db->commit();

            $this->log('info', 'Stock updated successfully', [
                'transfer_id' => $transferId,
                'items_updated' => count($items)
            ]);

            return true;

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            $this->log('error', 'Failed to update stock', [
                'transfer_id' => $transferId,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Rate limiting - enforce delay between API calls
     */
    private function enforceRateLimit(): void
    {
        $now = microtime(true);
        $elapsed = ($now - $this->lastApiCall) * 1000000; // Convert to microseconds

        if ($elapsed < $this->rateLimitDelay) {
            $sleepTime = $this->rateLimitDelay - $elapsed;
            usleep((int)$sleepTime);
        }

        $this->lastApiCall = microtime(true);
    }

    /**
     * Make API request to Vend
     *
     * @param string $method HTTP method
     * @param string $endpoint API endpoint
     * @param array|null $data Request data
     * @return array|null Response data
     */
    private function apiRequest(string $method, string $endpoint, $data = null): ?array
    {
        if (empty($this->vendApiToken)) {
            throw new Exception('Vend API token not configured');
        }

        $this->enforceRateLimit();

        $url = rtrim($this->vendApiUrl, '/') . '/' . ltrim($endpoint, '/');

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->vendApiToken,
            'Content-Type: application/json',
            'User-Agent: CIS-StockTransferEngine/1.0'
        ]);

        if ($method === 'POST' || $method === 'PUT') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            if ($data) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            throw new Exception("Vend API cURL error: {$curlError}");
        }

        if ($httpCode !== 200 && $httpCode !== 201) {
            $this->log('error', 'Vend API request failed', [
                'method' => $method,
                'endpoint' => $endpoint,
                'http_code' => $httpCode,
                'response' => substr($response, 0, 500)
            ]);
            return null;
        }

        $result = json_decode($response, true);
        return $result['data'] ?? $result ?? null;
    }

    /**
     * Logger helper
     */
    private function log(string $level, string $message, array $context = []): void
    {
        if ($this->logger && method_exists($this->logger, $level)) {
            $this->logger->$level($message, $context);
        }
    }

    /**
     * Clear API cache
     */
    public function clearCache(): void
    {
        if (function_exists('apcu_clear_cache')) {
            apcu_clear_cache();
        }
    }
}
