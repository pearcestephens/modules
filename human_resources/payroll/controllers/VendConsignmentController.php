<?php
declare(strict_types=1);

namespace HumanResources\Payroll\Controllers;

/**
 * Vend Consignment Management Controller
 *
 * Complete API for managing Vend/Lightspeed consignments with full CRUD operations
 *
 * HTTP API Endpoints:
 *
 * CONSIGNMENT OPERATIONS:
 * - POST   /api/vend/consignments/create          - Create new consignment
 * - GET    /api/vend/consignments/:id             - Get consignment details
 * - GET    /api/vend/consignments/list            - List consignments (with filters)
 * - PUT    /api/vend/consignments/:id             - Update consignment
 * - PATCH  /api/vend/consignments/:id/status      - Update consignment status
 * - DELETE /api/vend/consignments/:id             - Delete consignment
 *
 * CONSIGNMENT PRODUCTS:
 * - POST   /api/vend/consignments/:id/products    - Add products to consignment
 * - GET    /api/vend/consignments/:id/products    - List consignment products
 * - PUT    /api/vend/consignments/:id/products/:pid - Update consignment product
 * - DELETE /api/vend/consignments/:id/products/:pid - Remove product
 * - POST   /api/vend/consignments/:id/products/bulk - Bulk add products
 *
 * SYNC OPERATIONS:
 * - POST   /api/vend/consignments/:id/sync        - Sync consignment to Lightspeed
 * - GET    /api/vend/consignments/:id/sync/status - Get sync status
 * - POST   /api/vend/consignments/:id/sync/retry  - Retry failed sync
 *
 * WORKFLOW OPERATIONS:
 * - POST   /api/vend/consignments/:id/send        - Send consignment (mark as SENT)
 * - POST   /api/vend/consignments/:id/receive     - Receive consignment
 * - POST   /api/vend/consignments/:id/cancel      - Cancel consignment
 *
 * REPORTING:
 * - GET    /api/vend/consignments/statistics      - Get consignment statistics
 * - GET    /api/vend/consignments/sync-history    - Get sync history
 *
 * @package HumanResources\Payroll\Controllers
 * @version 1.0.0
 */

use PDO;

class VendConsignmentController extends BaseController
{
    private PDO $db;
    private $vendAPI;
    private $syncService;
    private $queueService;

    /**
     * Constructor
     */
    public function __construct(PDO $db)
    {
        parent::__construct();
        $this->db = $db;

        // Initialize services
        $this->initializeServices();
    }

    /**
     * Initialize Vend API and sync services
     */
    private function initializeServices(): void
    {
        try {
            // Load VendAPI
            require_once $_SERVER['DOCUMENT_ROOT'] . '/assets/services/VendAPI.php';
            require_once $_SERVER['DOCUMENT_ROOT'] . '/assets/services/QueueService.php';
            require_once $_SERVER['DOCUMENT_ROOT'] . '/assets/services/LightspeedSyncService.php';

            $vendDomain = getenv('VEND_DOMAIN') ?: 'vapeshed';
            $vendToken = getenv('VEND_API_TOKEN');

            if (!$vendToken) {
                throw new \RuntimeException('VEND_API_TOKEN not configured');
            }

            $this->vendAPI = new \CIS\Services\VendAPI($vendDomain, $vendToken);
            $this->queueService = new \CIS\Services\QueueService($this->db);
            $this->syncService = new \CIS\Services\LightspeedSyncService(
                $this->db,
                $this->vendAPI,
                $this->queueService
            );

        } catch (\Exception $e) {
            $this->logger->error('Failed to initialize Vend services', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    // =========================================================================
    // CONSIGNMENT CRUD OPERATIONS
    // =========================================================================

    /**
     * Create new consignment
     *
     * POST /api/vend/consignments/create
     *
     * Required body params:
     * - name (string) - Consignment name
     * - type (string) - SUPPLIER, OUTLET, RETURN, STOCKTAKE
     * - outlet_id (string) - Destination outlet ID
     *
     * Optional params:
     * - source_outlet_id (string) - Source outlet (for OUTLET type)
     * - supplier_id (string) - Supplier ID (for SUPPLIER type)
     * - due_at (string) - Due date (ISO 8601)
     * - status (string) - OPEN, SENT, RECEIVED (default: OPEN)
     * - reference (string) - External reference number
     *
     * @return void Outputs JSON
     */
    public function create(): void
    {
        $this->requireAuth();
        $this->requirePost();
        $this->verifyCsrf();

        try {
            $data = $this->getJsonBody();

            // Validate required fields
            $required = ['name', 'type', 'outlet_id'];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    $this->jsonError("Missing required field: {$field}", null, 422);
                    return;
                }
            }

            // Validate type
            $validTypes = ['SUPPLIER', 'OUTLET', 'RETURN', 'STOCKTAKE'];
            if (!in_array($data['type'], $validTypes)) {
                $this->jsonError('Invalid type. Must be: ' . implode(', ', $validTypes), null, 422);
                return;
            }

            $this->logger->info('Creating Vend consignment', [
                'name' => $data['name'],
                'type' => $data['type'],
                'user_id' => $this->getCurrentUserId()
            ]);

            $result = $this->vendAPI->createConsignment($data);

            if ($result['ok']) {
                $consignment = $result['data'] ?? $result['body']['data'] ?? null;

                $this->logger->info('Consignment created successfully', [
                    'consignment_id' => $consignment['id'] ?? 'unknown',
                    'name' => $data['name']
                ]);

                $this->jsonSuccess('Consignment created successfully', [
                    'consignment' => $consignment
                ]);
            } else {
                $this->logger->error('Failed to create consignment', [
                    'error' => $result['error'] ?? 'Unknown error',
                    'data' => $data
                ]);

                $this->jsonError(
                    $result['error'] ?? 'Failed to create consignment',
                    ['details' => $result],
                    $result['status'] ?? 500
                );
            }

        } catch (\Exception $e) {
            $this->logger->error('Exception creating consignment', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->jsonError('Internal server error', [], 500);
        }
    }

    /**
     * Get consignment details
     *
     * GET /api/vend/consignments/:id
     *
     * @param string $consignmentId Vend consignment ID
     * @return void Outputs JSON
     */
    public function get(string $consignmentId): void
    {
        $this->requireAuth();

        try {
            // Validate ID parameter
            if (empty($consignmentId)) {
                $this->jsonError('Consignment ID is required', null, 400);
                return;
            }

            $this->logger->info('Fetching consignment', [
                'consignment_id' => $consignmentId
            ]);

            $result = $this->vendAPI->getConsignment($consignmentId);

            if ($result['ok']) {
                $consignment = $result['data'] ?? $result['body']['data'] ?? null;

                // Also fetch products
                $productsResult = $this->vendAPI->listConsignmentProducts($consignmentId);
                $products = $productsResult['ok']
                    ? ($productsResult['data'] ?? $productsResult['body']['data'] ?? [])
                    : [];

                $this->jsonSuccess('Success', [
                    'consignment' => $consignment,
                    'products' => $products
                ]);
            } else {
                $this->jsonError(
                    $result['error'] ?? 'Consignment not found',
                    null,
                    $result['status'] ?? 404
                );
            }

        } catch (\Exception $e) {
            $this->logger->error('Exception fetching consignment', [
                'consignment_id' => $consignmentId,
                'error' => $e->getMessage()
            ]);
            $this->jsonError('Internal server error', [], 500);
        }
    }

    /**
     * List consignments with filters
     *
     * GET /api/vend/consignments/list
     *
     * Query params:
     * - type (string) - Filter by type
     * - status (string) - Filter by status
     * - outlet_id (string) - Filter by outlet
     * - since (string) - Filter by date (ISO 8601)
     * - page_size (int) - Results per page (default: 50, max: 200)
     * - after (string) - Pagination cursor
     *
     * @return void Outputs JSON
     */
    public function listConsignments(): void
    {
        $this->requireAuth();

        try {
            $filters = [];

            // Build filters from query params (using getParam for safety)
            $type = $this->getParam('type');
            if (!empty($type)) {
                $filters['type'] = $type;
            }

            $status = $this->getParam('status');
            if (!empty($status)) {
                $filters['status'] = $status;
            }

            $outletId = $this->getParam('outlet_id');
            if (!empty($outletId)) {
                $filters['outlet_id'] = $outletId;
            }

            $since = $this->getParam('since');
            if (!empty($since)) {
                $filters['since'] = $since;
            }

            $pageSize = $this->getParam('page_size');
            if (!empty($pageSize)) {
                $filters['page_size'] = min((int)$pageSize, 200);
            }

            $after = $this->getParam('after');
            if (!empty($after)) {
                $filters['after'] = $after;
            }

            $this->logger->info('Listing consignments', ['filters' => $filters]);

            $result = $this->vendAPI->listConsignments($filters);

            if ($result['ok']) {
                $consignments = $result['data'] ?? $result['body']['data'] ?? [];
                $pagination = $result['body']['version'] ?? [];

                $this->jsonSuccess('Success', [
                    'consignments' => $consignments,
                    'pagination' => $pagination,
                    'count' => count($consignments)
                ]);
            } else {
                $this->jsonError(
                    $result['error'] ?? 'Failed to list consignments',
                    null,
                    $result['status'] ?? 500
                );
            }

        } catch (\Exception $e) {
            $this->logger->error('Exception listing consignments', [
                'error' => $e->getMessage()
            ]);
            $this->jsonError('Internal server error', [], 500);
        }
    }

    /**
     * Update consignment
     *
     * PUT /api/vend/consignments/:id
     *
     * Body params (all optional):
     * - name (string)
     * - due_at (string)
     * - reference (string)
     * - notes (string)
     *
     * @param string $consignmentId Vend consignment ID
     * @return void Outputs JSON
     */
    public function update(string $consignmentId): void
    {
        $this->requireAuth();
        $this->requireMethod('PUT');
        $this->verifyCsrf();

        try {
            // Validate ID parameter
            if (empty($consignmentId)) {
                $this->jsonError('Consignment ID is required', null, 400);
                return;
            }

            $data = $this->getJsonBody();

            $this->logger->info('Updating consignment', [
                'consignment_id' => $consignmentId,
                'updates' => array_keys($data)
            ]);

            $result = $this->vendAPI->updateConsignment($consignmentId, $data);

            if ($result['ok']) {
                $consignment = $result['data'] ?? $result['body']['data'] ?? null;

                $this->logger->info('Consignment updated successfully', [
                    'consignment_id' => $consignmentId
                ]);

                $this->jsonSuccess('Consignment updated successfully', [
                    'consignment' => $consignment
                ]);
            } else {
                $this->jsonError(
                    $result['error'] ?? 'Failed to update consignment',
                    ['details' => $result],
                    $result['status'] ?? 500
                );
            }

        } catch (\Exception $e) {
            $this->logger->error('Exception updating consignment', [
                'consignment_id' => $consignmentId,
                'error' => $e->getMessage()
            ]);
            $this->jsonError('Internal server error', [], 500);
        }
    }

    /**
     * Update consignment status
     *
     * PATCH /api/vend/consignments/:id/status
     *
     * Body params:
     * - status (string) - OPEN, SENT, RECEIVED, STOCKTAKE, CANCELLED
     *
     * @param string $consignmentId Vend consignment ID
     * @return void Outputs JSON
     */
    public function updateStatus(string $consignmentId): void
    {
        $this->requireAuth();
        $this->requireMethod('PATCH');
        $this->verifyCsrf();

        // Validate ID
        if (!$this->validateId($consignmentId, 'Consignment ID')) {
            return;
        }

        try {
            $data = $this->getJsonBody();

            if (empty($data['status'])) {
                $this->jsonError('Missing required field: status', null, 422);
                return;
            }

            $validStatuses = ['OPEN', 'SENT', 'RECEIVED', 'STOCKTAKE', 'CANCELLED'];
            if (!in_array($data['status'], $validStatuses)) {
                $this->jsonError('Invalid status. Must be: ' . implode(', ', $validStatuses), null, 422);
                return;
            }

            $this->logger->info('Updating consignment status', [
                'consignment_id' => $consignmentId,
                'status' => $data['status']
            ]);

            $result = $this->vendAPI->updateConsignmentStatus($consignmentId, $data['status']);

            if ($result['ok']) {
                $this->logger->info('Consignment status updated', [
                    'consignment_id' => $consignmentId,
                    'status' => $data['status']
                ]);

                $this->jsonSuccess('Status updated successfully', [
                    'consignment_id' => $consignmentId,
                    'status' => $data['status']
                ]);
            } else {
                $this->jsonError(
                    $result['error'] ?? 'Failed to update status',
                    null,
                    $result['status'] ?? 500
                );
            }

        } catch (\Exception $e) {
            $this->logger->error('Exception updating status', [
                'consignment_id' => $consignmentId,
                'error' => $e->getMessage()
            ]);
            $this->jsonError('Internal server error', [], 500);
        }
    }

    /**
     * Delete consignment
     *
     * DELETE /api/vend/consignments/:id
     *
     * @param string $consignmentId Vend consignment ID
     * @return void Outputs JSON
     */
    public function delete(string $consignmentId): void
    {
        $this->requireAuth();
        $this->requireMethod('DELETE');
        $this->verifyCsrf();

        try {
            $this->logger->warning('Deleting consignment', [
                'consignment_id' => $consignmentId,
                'user_id' => $this->getCurrentUserId()
            ]);

            $result = $this->vendAPI->deleteConsignment($consignmentId);

            if ($result['ok']) {
                $this->logger->info('Consignment deleted successfully', [
                    'consignment_id' => $consignmentId
                ]);

                $this->jsonSuccess('Consignment deleted successfully', [
                    'consignment_id' => $consignmentId
                ]);
            } else {
                $this->jsonError(
                    $result['error'] ?? 'Failed to delete consignment',
                    null,
                    $result['status'] ?? 500
                );
            }

        } catch (\Exception $e) {
            $this->logger->error('Exception deleting consignment', [
                'consignment_id' => $consignmentId,
                'error' => $e->getMessage()
            ]);
            $this->jsonError('Internal server error', [], 500);
        }
    }

    // =========================================================================
    // CONSIGNMENT PRODUCTS OPERATIONS
    // =========================================================================

    /**
     * Add products to consignment
     *
     * POST /api/vend/consignments/:id/products
     *
     * Body params:
     * - product_id (string) - Vend product ID
     * - count (float) - Quantity
     * - cost (float, optional) - Unit cost
     * - received (float, optional) - Received quantity (for receiving)
     *
     * @param string $consignmentId Vend consignment ID
     * @return void Outputs JSON
     */
    public function addProduct(string $consignmentId): void
    {
        $this->requireAuth();
        $this->requirePost();
        $this->verifyCsrf();

        try {
            $data = $this->getJsonBody();

            // Add consignment_id to data
            $data['consignment_id'] = $consignmentId;

            // Validate required fields
            if (empty($data['product_id']) || !isset($data['count'])) {
                $this->jsonError('Missing required fields: product_id, count', null, 422);
                return;
            }

            $this->logger->info('Adding product to consignment', [
                'consignment_id' => $consignmentId,
                'product_id' => $data['product_id'],
                'count' => $data['count']
            ]);

            $result = $this->vendAPI->addConsignmentProduct($data);

            if ($result['ok']) {
                $product = $result['data'] ?? $result['body']['data'] ?? null;

                $this->logger->info('Product added to consignment', [
                    'consignment_id' => $consignmentId,
                    'product_id' => $data['product_id']
                ]);

                $this->jsonSuccess('Product added successfully', [
                    'product' => $product
                ]);
            } else {
                $this->jsonError(
                    $result['error'] ?? 'Failed to add product',
                    null,
                    $result['status'] ?? 500
                );
            }

        } catch (\Exception $e) {
            $this->logger->error('Exception adding product', [
                'consignment_id' => $consignmentId,
                'error' => $e->getMessage()
            ]);
            $this->jsonError('Internal server error', [], 500);
        }
    }

    /**
     * List consignment products
     *
     * GET /api/vend/consignments/:id/products
     *
     * @param string $consignmentId Vend consignment ID
     * @return void Outputs JSON
     */
    public function listProducts(string $consignmentId): void
    {
        $this->requireAuth();

        try {
            $result = $this->vendAPI->listConsignmentProducts($consignmentId);

            if ($result['ok']) {
                $products = $result['data'] ?? $result['body']['data'] ?? [];

                $this->jsonSuccess('Success', [
                    'products' => $products,
                    'count' => count($products)
                ]);
            } else {
                $this->jsonError(
                    $result['error'] ?? 'Failed to list products',
                    null,
                    $result['status'] ?? 500
                );
            }

        } catch (\Exception $e) {
            $this->logger->error('Exception listing products', [
                'consignment_id' => $consignmentId,
                'error' => $e->getMessage()
            ]);
            $this->jsonError('Internal server error', [], 500);
        }
    }

    /**
     * Update consignment product
     *
     * PUT /api/vend/consignments/:id/products/:productId
     *
     * Body params (all optional):
     * - count (float)
     * - cost (float)
     * - received (float)
     *
     * @param string $consignmentId Vend consignment ID (unused but for routing)
     * @param string $productId Vend consignment product ID
     * @return void Outputs JSON
     */
    public function updateProduct(string $consignmentId, string $productId): void
    {
        $this->requireAuth();
        $this->requireMethod('PUT');
        $this->verifyCsrf();

        try {
            $data = $this->getJsonBody();

            $this->logger->info('Updating consignment product', [
                'consignment_id' => $consignmentId,
                'product_id' => $productId,
                'updates' => array_keys($data)
            ]);

            $result = $this->vendAPI->updateConsignmentProduct($productId, $data);

            if ($result['ok']) {
                $product = $result['data'] ?? $result['body']['data'] ?? null;

                $this->jsonSuccess('Product updated successfully', [
                    'product' => $product
                ]);
            } else {
                $this->jsonError(
                    $result['error'] ?? 'Failed to update product',
                    null,
                    $result['status'] ?? 500
                );
            }

        } catch (\Exception $e) {
            $this->logger->error('Exception updating product', [
                'product_id' => $productId,
                'error' => $e->getMessage()
            ]);
            $this->jsonError('Internal server error', [], 500);
        }
    }

    /**
     * Remove product from consignment
     *
     * DELETE /api/vend/consignments/:id/products/:productId
     *
     * @param string $consignmentId Vend consignment ID (unused but for routing)
     * @param string $productId Vend consignment product ID
     * @return void Outputs JSON
     */
    public function deleteProduct(string $consignmentId, string $productId): void
    {
        $this->requireAuth();
        $this->requireMethod('DELETE');
        $this->verifyCsrf();

        try {
            $this->logger->info('Removing product from consignment', [
                'consignment_id' => $consignmentId,
                'product_id' => $productId
            ]);

            $result = $this->vendAPI->deleteConsignmentProduct($productId);

            if ($result['ok']) {
                $this->jsonSuccess('Product removed successfully', [
                    'product_id' => $productId
                ]);
            } else {
                $this->jsonError(
                    $result['error'] ?? 'Failed to remove product',
                    null,
                    $result['status'] ?? 500
                );
            }

        } catch (\Exception $e) {
            $this->logger->error('Exception removing product', [
                'product_id' => $productId,
                'error' => $e->getMessage()
            ]);
            $this->jsonError('Internal server error', [], 500);
        }
    }

    /**
     * Bulk add products to consignment
     *
     * POST /api/vend/consignments/:id/products/bulk
     *
     * Body params:
     * - products (array) - Array of product objects, each with:
     *   - product_id (string)
     *   - count (float)
     *   - cost (float, optional)
     *
     * @param string $consignmentId Vend consignment ID
     * @return void Outputs JSON
     */
    public function bulkAddProducts(string $consignmentId): void
    {
        $this->requireAuth();
        $this->requirePost();
        $this->verifyCsrf();

        try {
            $data = $this->getJsonBody();

            if (empty($data['products']) || !is_array($data['products'])) {
                $this->jsonError('Missing or invalid products array', null, 422);
                return;
            }

            $this->logger->info('Bulk adding products to consignment', [
                'consignment_id' => $consignmentId,
                'product_count' => count($data['products'])
            ]);

            // Use smart bulk add (handles large batches)
            $result = $this->vendAPI->bulkAddConsignmentProductsSmart($consignmentId, $data['products']);

            if ($result['ok']) {
                $this->logger->info('Products bulk added successfully', [
                    'consignment_id' => $consignmentId,
                    'count' => count($data['products'])
                ]);

                $this->jsonSuccess('Products added successfully', [
                    'added' => count($data['products']),
                    'results' => $result['data'] ?? []
                ]);
            } else {
                $this->jsonError(
                    $result['error'] ?? 'Failed to bulk add products',
                    ['details' => $result],
                    $result['status'] ?? 500
                );
            }

        } catch (\Exception $e) {
            $this->logger->error('Exception bulk adding products', [
                'consignment_id' => $consignmentId,
                'error' => $e->getMessage()
            ]);
            $this->jsonError('Internal server error', [], 500);
        }
    }

    // =========================================================================
    // SYNC OPERATIONS
    // =========================================================================

    /**
     * Sync consignment to Lightspeed
     *
     * POST /api/vend/consignments/:id/sync
     *
     * Body params (optional):
     * - async (bool) - Use queue for async processing (default: true)
     *
     * @param string $consignmentId Local consignment ID (not Vend ID)
     * @return void Outputs JSON
     */
    public function sync(string $consignmentId): void
    {
        $this->requireAuth();
        $this->requirePost();
        $this->verifyCsrf();

        try {
            $data = $this->getJsonBody();
            $async = $data['async'] ?? true;

            $this->logger->info('Initiating consignment sync', [
                'consignment_id' => $consignmentId,
                'async' => $async
            ]);

            $result = $this->syncService->syncPurchaseOrder($consignmentId, $async);

            if ($result['ok']) {
                $message = $result['queued'] ?? false
                    ? 'Sync queued successfully'
                    : 'Sync completed successfully';

                $this->jsonSuccess($message, [
                    'consignment_id' => $consignmentId,
                    'job_id' => $result['job_id'] ?? null,
                    'vend_consignment_id' => $result['consignment_id'] ?? null,
                    'queued' => $result['queued'] ?? false
                ]);
            } else {
                $this->jsonError(
                    $result['error'] ?? 'Sync failed',
                    ['details' => $result],
                    500
                );
            }

        } catch (\Exception $e) {
            $this->logger->error('Exception syncing consignment', [
                'consignment_id' => $consignmentId,
                'error' => $e->getMessage()
            ]);
            $this->jsonError('Internal server error', [], 500);
        }
    }

    /**
     * Get sync status
     *
     * GET /api/vend/consignments/:id/sync/status
     *
     * @param string $consignmentId Local consignment ID
     * @return void Outputs JSON
     */
    public function syncStatus(string $consignmentId): void
    {
        $this->requireAuth();

        try {
            // Get sync logs
            $sql = "SELECT * FROM lightspeed_sync_log
                    WHERE entity_id = ?
                    ORDER BY created_at DESC
                    LIMIT 10";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$consignmentId]);
            $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Get consignment sync status
            $sql = "SELECT lightspeed_consignment_id, lightspeed_sync_status, lightspeed_status
                    FROM vend_consignments
                    WHERE id = ?";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$consignmentId]);
            $consignment = $stmt->fetch(PDO::FETCH_ASSOC);

            $this->jsonSuccess('Success', [
                'consignment_id' => $consignmentId,
                'vend_consignment_id' => $consignment['lightspeed_consignment_id'] ?? null,
                'sync_status' => $consignment['lightspeed_sync_status'] ?? 'not_synced',
                'lightspeed_status' => $consignment['lightspeed_status'] ?? null,
                'sync_logs' => $logs
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Exception fetching sync status', [
                'consignment_id' => $consignmentId,
                'error' => $e->getMessage()
            ]);
            $this->jsonError('Internal server error', [], 500);
        }
    }

    /**
     * Retry failed sync
     *
     * POST /api/vend/consignments/:id/sync/retry
     *
     * @param string $consignmentId Local consignment ID
     * @return void Outputs JSON
     */
    public function syncRetry(string $consignmentId): void
    {
        $this->requireAuth();
        $this->requirePost();
        $this->verifyCsrf();

        try {
            // Reset sync status
            $sql = "UPDATE vend_consignments
                    SET lightspeed_sync_status = NULL
                    WHERE id = ?";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$consignmentId]);

            $this->logger->info('Retrying failed sync', [
                'consignment_id' => $consignmentId
            ]);

            // Trigger sync
            $result = $this->syncService->syncPurchaseOrder($consignmentId, true);

            if ($result['ok']) {
                $this->jsonSuccess('Sync retry queued', [
                    'job_id' => $result['job_id'] ?? null
                ]);
            } else {
                $this->jsonError('Failed to queue retry', null, 500);
            }

        } catch (\Exception $e) {
            $this->logger->error('Exception retrying sync', [
                'consignment_id' => $consignmentId,
                'error' => $e->getMessage()
            ]);
            $this->jsonError('Internal server error', [], 500);
        }
    }

    // =========================================================================
    // WORKFLOW OPERATIONS
    // =========================================================================

    /**
     * Send consignment (mark as SENT)
     *
     * POST /api/vend/consignments/:id/send
     *
     * @param string $consignmentId Vend consignment ID
     * @return void Outputs JSON
     */
    public function send(string $consignmentId): void
    {
        $this->requireAuth();
        $this->requirePost();
        $this->verifyCsrf();

        try {
            $this->logger->info('Sending consignment', [
                'consignment_id' => $consignmentId
            ]);

            $result = $this->vendAPI->updateConsignmentStatus($consignmentId, 'SENT');

            if ($result['ok']) {
                $this->jsonSuccess('Consignment sent successfully', [
                    'consignment_id' => $consignmentId,
                    'status' => 'SENT'
                ]);
            } else {
                $this->jsonError(
                    $result['error'] ?? 'Failed to send consignment',
                    null,
                    $result['status'] ?? 500
                );
            }

        } catch (\Exception $e) {
            $this->logger->error('Exception sending consignment', [
                'consignment_id' => $consignmentId,
                'error' => $e->getMessage()
            ]);
            $this->jsonError('Internal server error', [], 500);
        }
    }

    /**
     * Receive consignment
     *
     * POST /api/vend/consignments/:id/receive
     *
     * Body params (optional):
     * - received_quantities (array) - Array of {product_id, received}
     *
     * @param string $consignmentId Vend consignment ID
     * @return void Outputs JSON
     */
    public function receive(string $consignmentId): void
    {
        $this->requireAuth();
        $this->requirePost();
        $this->verifyCsrf();

        try {
            $data = $this->getJsonBody();

            $this->logger->info('Receiving consignment', [
                'consignment_id' => $consignmentId,
                'has_quantities' => !empty($data['received_quantities'])
            ]);

            // If received quantities provided, update products first
            if (!empty($data['received_quantities']) && is_array($data['received_quantities'])) {
                foreach ($data['received_quantities'] as $item) {
                    if (isset($item['product_id']) && isset($item['received'])) {
                        $this->vendAPI->updateConsignmentProduct($item['product_id'], [
                            'received' => $item['received']
                        ]);
                    }
                }
            }

            // Mark consignment as RECEIVED
            $result = $this->vendAPI->updateConsignmentStatus($consignmentId, 'RECEIVED');

            if ($result['ok']) {
                $this->jsonSuccess('Consignment received successfully', [
                    'consignment_id' => $consignmentId,
                    'status' => 'RECEIVED'
                ]);
            } else {
                $this->jsonError(
                    $result['error'] ?? 'Failed to receive consignment',
                    null,
                    $result['status'] ?? 500
                );
            }

        } catch (\Exception $e) {
            $this->logger->error('Exception receiving consignment', [
                'consignment_id' => $consignmentId,
                'error' => $e->getMessage()
            ]);
            $this->jsonError('Internal server error', [], 500);
        }
    }

    /**
     * Cancel consignment
     *
     * POST /api/vend/consignments/:id/cancel
     *
     * @param string $consignmentId Vend consignment ID
     * @return void Outputs JSON
     */
    public function cancel(string $consignmentId): void
    {
        $this->requireAuth();
        $this->requirePost();
        $this->verifyCsrf();

        try {
            $this->logger->warning('Cancelling consignment', [
                'consignment_id' => $consignmentId,
                'user_id' => $this->getCurrentUserId()
            ]);

            $result = $this->vendAPI->updateConsignmentStatus($consignmentId, 'CANCELLED');

            if ($result['ok']) {
                $this->jsonSuccess('Consignment cancelled successfully', [
                    'consignment_id' => $consignmentId,
                    'status' => 'CANCELLED'
                ]);
            } else {
                $this->jsonError(
                    $result['error'] ?? 'Failed to cancel consignment',
                    null,
                    $result['status'] ?? 500
                );
            }

        } catch (\Exception $e) {
            $this->logger->error('Exception cancelling consignment', [
                'consignment_id' => $consignmentId,
                'error' => $e->getMessage()
            ]);
            $this->jsonError('Internal server error', [], 500);
        }
    }

    // =========================================================================
    // REPORTING
    // =========================================================================

    /**
     * Get consignment statistics
     *
     * GET /api/vend/consignments/statistics
     *
     * Query params:
     * - period (string) - 'today', 'week', 'month' (default: 'week')
     *
     * @return void Outputs JSON
     */
    public function statistics(): void
    {
        $this->requireAuth();

        try {
            $period = $this->input('period', 'week');

            $dateCondition = match($period) {
                'today' => "DATE(created_at) = CURDATE()",
                'month' => "created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)",
                default => "created_at >= DATE_SUB(NOW(), INTERVAL 1 WEEK)"
            };

            $sql = "SELECT
                        COUNT(*) as total_consignments,
                        SUM(CASE WHEN state = 'OPEN' THEN 1 ELSE 0 END) as open,
                        SUM(CASE WHEN state = 'SENT' THEN 1 ELSE 0 END) as sent,
                        SUM(CASE WHEN state = 'RECEIVED' THEN 1 ELSE 0 END) as received,
                        SUM(CASE WHEN state = 'CANCELLED' THEN 1 ELSE 0 END) as cancelled,
                        SUM(CASE WHEN lightspeed_consignment_id IS NOT NULL THEN 1 ELSE 0 END) as synced,
                        SUM(CASE WHEN lightspeed_sync_status = 'error' THEN 1 ELSE 0 END) as sync_errors
                    FROM vend_consignments
                    WHERE {$dateCondition}
                    AND deleted_at IS NULL";

            $stmt = $this->db->query($sql);
            $stats = $stmt->fetch(PDO::FETCH_ASSOC);

            $this->jsonSuccess('Success', [
                'period' => $period,
                'statistics' => $stats
            ]);

        } catch (\Exception $e) {
            $this->jsonSuccess('Success', [
                'period' => $period ?? 'week',
                'statistics' => [
                    'total_consignments' => 0,
                    'open' => 0,
                    'sent' => 0,
                    'received' => 0,
                    'cancelled' => 0,
                    'synced' => 0,
                    'sync_errors' => 0
                ],
                'note' => 'No data available'
            ]);
        }
    }

    /**
     * Get sync history
     *
     * GET /api/vend/consignments/sync-history
     *
     * Query params:
     * - limit (int) - Results limit (default: 50)
     * - status (string) - Filter by status (completed, failed, in_progress)
     *
     * @return void Outputs JSON
     */
    public function syncHistory(): void
    {
        $this->requireAuth();

        try {
            $limit = $this->getParam('limit');
            $limit = $limit ? min((int)$limit, 200) : 50;

            $status = $this->getParam('status');

            $logs = $this->syncService->getRecentLogs($limit);

            // Filter by status if provided
            if ($status) {
                $logs = array_filter($logs, fn($log) => $log['status'] === $status);
            }

            $this->jsonSuccess('Success', [
                'logs' => array_values($logs),
                'count' => count($logs)
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Exception fetching sync history', [
                'error' => $e->getMessage()
            ]);
            $this->jsonError('Internal server error', [], 500);
        }
    }

    // =========================================================================
    // HELPER METHODS
    // =========================================================================

    /**
     * Validate and sanitize an ID parameter
     *
     * @param string $id The ID to validate
     * @param string $name The parameter name (for error messages)
     * @return bool True if valid
     */
    private function validateId(string $id, string $name = 'ID'): bool
    {
        if (empty($id)) {
            $this->jsonError("{$name} is required", null, 400);
            return false;
        }

        // Additional validation: prevent path traversal, SQL injection, etc.
        if (preg_match('/[^a-zA-Z0-9_-]/', $id)) {
            $this->jsonError("Invalid {$name} format", null, 400);
            return false;
        }

        return true;
    }
}
