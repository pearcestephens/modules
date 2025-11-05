<?php
/**
 * PurchaseOrdersAPI - Enterprise-Grade API for Purchase Orders
 *
 * Extends BaseAPI to provide standardized endpoints for purchase order operations.
 * Follows BASE module envelope design pattern.
 *
 * @package CIS\Consignments\Lib
 * @version 2.0.0
 * @author CIS Development Team
 * @created 2025-11-04
 */

declare(strict_types=1);

namespace CIS\Consignments\Lib;

require_once __DIR__ . '/../../base/lib/BaseAPI.php';
require_once __DIR__ . '/../lib/Services/PurchaseOrderService.php';

use CIS\Base\Lib\BaseAPI;
use CIS\Consignments\Services\PurchaseOrderService;

class PurchaseOrdersAPI extends BaseAPI {

    /**
     * Purchase Order service instance
     * @var PurchaseOrderService
     */
    private PurchaseOrderService $service;

    /**
     * Constructor
     *
     * @param array $config Configuration options
     */
    public function __construct(array $config = []) {
        // Configure API settings
        $apiConfig = array_merge([
            'require_auth' => true, // PO operations require authentication
            'allowed_methods' => ['POST', 'GET'],
            'log_requests' => true,
            'log_responses' => true,
            'timezone' => 'Pacific/Auckland'
        ], $config);

        parent::__construct($apiConfig);

        // Get PDO connection
        global $pdo;
        if (!isset($pdo)) {
            throw new \RuntimeException('Database connection not available');
        }

        // Initialize service
        $this->service = new PurchaseOrderService($pdo);
    }

    /**
     * Get default configuration
     *
     * @return array
     */
    protected function getDefaultConfig(): array {
        return array_merge(parent::getDefaultConfig(), [
            'require_csrf' => true, // CSRF required for write operations
            'cache_ttl' => 300 // 5 minutes cache for read operations
        ]);
    }

    // ========================================================================
    // READ OPERATIONS
    // ========================================================================

    /**
     * List purchase orders with pagination and filtering
     *
     * @param array $data Request data
     * @return array Response envelope
     */
    protected function handleList(array $data): array {
        // Parse and validate parameters
        $params = [
            'search' => $data['search'] ?? '',
            'status' => $data['status'] ?? null,
            'supplier_id' => isset($data['supplier_id']) ? (int)$data['supplier_id'] : null,
            'outlet_id' => isset($data['outlet_id']) ? (int)$data['outlet_id'] : null,
            'date_from' => $data['date_from'] ?? null,
            'date_to' => $data['date_to'] ?? null,
            'min_total' => isset($data['min_total']) ? (float)$data['min_total'] : null,
            'max_total' => isset($data['max_total']) ? (float)$data['max_total'] : null,
            'needs_approval' => isset($data['needs_approval']) ? filter_var($data['needs_approval'], FILTER_VALIDATE_BOOLEAN) : null,
            'created_by' => isset($data['created_by']) ? (int)$data['created_by'] : null,

            // Pagination
            'page' => isset($data['page']) ? max(1, (int)$data['page']) : 1,
            'per_page' => isset($data['per_page']) ? min(100, max(10, (int)$data['per_page'])) : 25,

            // Sorting
            'sort_by' => $data['sort_by'] ?? 'created_at',
            'sort_dir' => strtoupper($data['sort_dir'] ?? 'DESC')
        ];

        // Validate sort direction
        if (!in_array($params['sort_dir'], ['ASC', 'DESC'])) {
            $params['sort_dir'] = 'DESC';
        }

        // Validate sort field
        $validSortFields = ['id', 'consignment_number', 'supplier_name', 'total_amount', 'status', 'created_at', 'due_date'];
        if (!in_array($params['sort_by'], $validSortFields)) {
            $params['sort_by'] = 'created_at';
        }

        try {
            $result = $this->service->list($params);

            return $this->success(
                $result['data'],
                'Purchase orders retrieved successfully',
                [
                    'pagination' => [
                        'page' => $params['page'],
                        'per_page' => $params['per_page'],
                        'total' => $result['total'],
                        'total_pages' => ceil($result['total'] / $params['per_page'])
                    ],
                    'filters_applied' => array_filter($params, fn($v) => $v !== null && $v !== '')
                ]
            );

        } catch (\Exception $e) {
            return $this->error(
                $e->getMessage(),
                'LIST_FAILED',
                ['exception' => get_class($e)],
                500
            );
        }
    }

    /**
     * Get single purchase order with full details
     *
     * @param array $data Request data
     * @return array Response envelope
     */
    protected function handleGet(array $data): array {
        $this->validateRequired($data, ['id']);

        $id = $this->validateInt($data, 'id');

        try {
            $po = $this->service->getById($id);

            if (!$po) {
                return $this->error(
                    "Purchase order not found with ID: {$id}",
                    'NOT_FOUND',
                    ['id' => $id],
                    404
                );
            }

            return $this->success(
                $po,
                'Purchase order retrieved successfully'
            );

        } catch (\Exception $e) {
            return $this->error(
                $e->getMessage(),
                'GET_FAILED',
                ['id' => $id],
                500
            );
        }
    }

    // ========================================================================
    // WRITE OPERATIONS (CSRF Required)
    // ========================================================================

    /**
     * Create new purchase order
     *
     * @param array $data Request data
     * @return array Response envelope
     */
    protected function handleCreate(array $data): array {
        // Validate CSRF token
        $this->validateCSRF($data);

        // Validate required fields
        $this->validateRequired($data, [
            'supplier_id',
            'destination_outlet_id',
            'created_by'
        ]);

        try {
            $poId = $this->service->create($data);

            $po = $this->service->getById($poId);

            return $this->success(
                $po,
                'Purchase order created successfully',
                ['http_code' => 201, 'po_id' => $poId]
            );

        } catch (\Exception $e) {
            return $this->error(
                $e->getMessage(),
                'CREATE_FAILED',
                ['exception' => get_class($e)],
                500
            );
        }
    }

    /**
     * Update purchase order
     *
     * @param array $data Request data
     * @return array Response envelope
     */
    protected function handleUpdate(array $data): array {
        // Validate CSRF token
        $this->validateCSRF($data);

        // Validate required fields
        $this->validateRequired($data, ['id']);

        $id = $this->validateInt($data, 'id');

        try {
            $updated = $this->service->update($id, $data);

            return $this->success(
                ['updated' => $updated, 'id' => $id],
                'Purchase order updated successfully'
            );

        } catch (\Exception $e) {
            return $this->error(
                $e->getMessage(),
                'UPDATE_FAILED',
                ['id' => $id],
                500
            );
        }
    }

    /**
     * Approve purchase order
     *
     * @param array $data Request data
     * @return array Response envelope
     */
    protected function handleApprove(array $data): array {
        // Validate CSRF token
        $this->validateCSRF($data);

        // Validate required fields
        $this->validateRequired($data, ['id', 'approved_by']);

        $id = $this->validateInt($data, 'id');
        $approvedBy = $this->validateInt($data, 'approved_by');
        $comments = $data['comments'] ?? null;

        try {
            $result = $this->service->approve($id, $approvedBy, $comments);

            return $this->success(
                $result,
                'Purchase order approved successfully'
            );

        } catch (\Exception $e) {
            return $this->error(
                $e->getMessage(),
                'APPROVAL_FAILED',
                ['id' => $id],
                422
            );
        }
    }

    /**
     * Delete purchase order
     *
     * @param array $data Request data
     * @return array Response envelope
     */
    protected function handleDelete(array $data): array {
        // Validate CSRF token
        $this->validateCSRF($data);

        // Validate required fields
        $this->validateRequired($data, ['id']);

        $id = $this->validateInt($data, 'id');

        try {
            $deleted = $this->service->delete($id);

            return $this->success(
                ['deleted' => $deleted, 'id' => $id],
                'Purchase order deleted successfully'
            );

        } catch (\Exception $e) {
            return $this->error(
                $e->getMessage(),
                'DELETE_FAILED',
                ['id' => $id],
                422
            );
        }
    }

    // ========================================================================
    // HELPER METHODS
    // ========================================================================

    /**
     * Validate CSRF token
     *
     * @param array $data Request data
     * @throws \Exception
     */
    private function validateCSRF(array $data): void {
        if (!$this->config['require_csrf']) {
            return;
        }

        $token = $data['csrf'] ?? '';

        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }

        $valid = isset($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], (string)$token);

        if (!$valid) {
            $this->logWarning('CSRF validation failed', [
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
            ]);

            throw new \Exception('Invalid CSRF token');
        }
    }

    /**
     * Validate integer parameter
     */
    private function validateInt(
        array $data,
        string $key,
        ?int $default = null,
        ?int $min = null,
        ?int $max = null,
        bool $nullable = false
    ): ?int {
        if (!isset($data[$key])) {
            if ($nullable || $default !== null) {
                return $default;
            }
            throw new \Exception("Missing required parameter: {$key}");
        }

        $value = filter_var($data[$key], FILTER_VALIDATE_INT);

        if ($value === false) {
            throw new \Exception("Invalid integer value for parameter: {$key}");
        }

        if ($min !== null && $value < $min) {
            throw new \Exception("{$key} must be at least {$min}");
        }

        if ($max !== null && $value > $max) {
            throw new \Exception("{$key} must be at most {$max}");
        }

        return $value;
    }

    /**
     * Validate required fields
     */
    private function validateRequired(array $data, array $required): void {
        $missing = [];

        foreach ($required as $field) {
            if (!isset($data[$field]) || $data[$field] === '') {
                $missing[] = $field;
            }
        }

        if (!empty($missing)) {
            throw new \Exception('Missing required fields: ' . implode(', ', $missing));
        }
    }
}
