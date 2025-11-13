<?php
/**
 * ConsignmentsAPI - Enterprise-Grade API for Consignments
 *
 * Extends BaseAPI to provide standardized endpoints for consignment operations.
 * Follows BASE module envelope design pattern with comprehensive error handling,
 * logging, validation, and security.
 *
 * @package CIS\Consignments\Lib
 * @version 2.0.0
 * @author CIS Development Team
 * @created 2025-11-04
 */

declare(strict_types=1);

namespace CIS\Consignments\Lib;

require_once __DIR__ . '/../../base/lib/BaseAPI.php';
require_once __DIR__ . '/../ConsignmentService.php';

use CIS\Base\Lib\BaseAPI;
use ConsignmentService;

class ConsignmentsAPI extends BaseAPI {

    /**
     * Consignment service instance
     * @var ConsignmentService
     */
    private ConsignmentService $service;

    /**
     * Constructor
     *
     * @param array $config Configuration options
     */
    public function __construct(array $config = []) {
        // Configure API settings
        $apiConfig = array_merge([
            'require_auth' => true, // Enforce staff auth for all endpoints
            'allowed_methods' => ['POST', 'GET'],
            'log_requests' => true,
            'log_responses' => true,
            'timezone' => 'Pacific/Auckland'
        ], $config);

        parent::__construct($apiConfig);

        // Initialize service
        $this->service = ConsignmentService::make();
    }

    /**
     * Authentication hook used by BaseAPI when require_auth=true
     * Ensures session is authenticated; returns JSON 401 on failure (no HTML redirects)
     */
    protected function authenticate(): void {
        // If a global auth helper exists, use it
        if (function_exists('isLoggedIn')) {
            if (!isLoggedIn()) {
                header('Content-Type: application/json; charset=utf-8', true, 401);
                echo json_encode([
                    'success' => false,
                    'error' => [
                        'code' => 'UNAUTHORIZED',
                        'message' => 'Authentication required. Please log in.'
                    ],
                    'request_id' => $this->getRequestIdForResponse()
                ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                exit;
            }
            return;
        }

        // Fallback: check PHP session user
        if (session_status() !== \PHP_SESSION_ACTIVE) @session_start();
        if (!isset($_SESSION['user_id']) || !$_SESSION['user_id']) {
            header('Content-Type: application/json; charset=utf-8', true, 401);
            echo json_encode([
                'success' => false,
                'error' => [
                    'code' => 'UNAUTHORIZED',
                    'message' => 'Authentication required.'
                ],
                'request_id' => $this->getRequestIdForResponse()
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            exit;
        }
    }

    /**
     * Provide request id to responses from within this class
     */
    private function getRequestIdForResponse(): string {
        // BaseAPI keeps requestId private; mirror generation for consistency
        return 'req_' . substr(md5(uniqid('', true)), 0, 12);
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
     * Get recent consignments
     *
     * @param array $data Request data
     * @return array Response envelope
     */
    protected function handleGetRecent(array $data): array {
        $this->validateRequired($data, []);

        $limit = $this->validateInt($data, 'limit', 50, 1, 100);

        $rows = $this->service->recent($limit);

        return $this->success([
            'rows' => $rows,
            'count' => count($rows)
        ], 'Recent consignments retrieved successfully');
    }

    /**
     * Get single consignment with items
     *
     * @param array $data Request data
     * @return array Response envelope
     */
    protected function handleGetConsignment(array $data): array {
        $this->validateRequired($data, ['id']);

        $id = $this->validateInt($data, 'id');

        $consignment = $this->service->get($id);

        if (!$consignment) {
            return $this->error(
                "Consignment not found with ID: {$id}",
                'NOT_FOUND',
                ['id' => $id],
                404
            );
        }

        $items = $this->service->items($id);

        return $this->success([
            'consignment' => $consignment,
            'items' => $items,
            'item_count' => count($items)
        ], 'Consignment retrieved successfully');
    }

    /**
     * Search consignments
     *
     * @param array $data Request data
     * @return array Response envelope
     */
    protected function handleSearchConsignments(array $data): array {
        $refCode = $this->validateString($data, 'ref_code', '');
        $outletId = $this->validateInt($data, 'outlet_id', null, null, null, true);
        $limit = $this->validateInt($data, 'limit', 50, 1, 100);

        $rows = $this->service->search($refCode, $outletId, $limit);

        return $this->success([
            'rows' => $rows,
            'count' => count($rows),
            'filters' => [
                'ref_code' => $refCode,
                'outlet_id' => $outletId,
                'limit' => $limit
            ]
        ], 'Search completed successfully');
    }

    /**
     * Get consignment statistics
     *
     * @param array $data Request data
     * @return array Response envelope
     */
    protected function handleGetStats(array $data): array {
        $outletId = $this->validateInt($data, 'outlet_id', null, null, null, true);

        $stats = $this->service->stats($outletId);

        return $this->success($stats, 'Statistics retrieved successfully', [
            'outlet_id' => $outletId
        ]);
    }

    // ========================================================================
    // WRITE OPERATIONS (CSRF Required)
    // ========================================================================

    /**
     * Create new consignment
     *
     * @param array $data Request data
     * @return array Response envelope
     */
    protected function handleCreateConsignment(array $data): array {
        // Validate CSRF token
        $this->validateCSRF($data);

        // Validate required fields
        $this->validateRequired($data, [
            'ref_code',
            'origin_outlet_id',
            'dest_outlet_id',
            'created_by'
        ]);

        // Additional validation
        $this->validateString($data, 'ref_code', null, 1, 100);
        $this->validateInt($data, 'origin_outlet_id', null, 1);
        $this->validateInt($data, 'dest_outlet_id', null, 1);
        $this->validateInt($data, 'created_by', null, 1);

        // Check that origin and destination are different
        if ($data['origin_outlet_id'] === $data['dest_outlet_id']) {
            return $this->error(
                'Origin and destination outlets must be different',
                'VALIDATION_ERROR',
                [
                    'field' => 'dest_outlet_id',
                    'value' => $data['dest_outlet_id']
                ],
                422
            );
        }

        try {
            $created = $this->service->create($data);

            return $this->success(
                $created,
                'Consignment created successfully',
                ['http_code' => 201]
            );

        } catch (\RuntimeException $e) {
            return $this->error(
                $e->getMessage(),
                'CREATE_FAILED',
                ['exception' => get_class($e)],
                500
            );
        }
    }

    /**
     * Add item to consignment
     *
     * @param array $data Request data
     * @return array Response envelope
     */
    protected function handleAddItem(array $data): array {
        // Validate CSRF token
        $this->validateCSRF($data);

        // Validate required fields
        $this->validateRequired($data, [
            'consignment_id',
            'product_id',
            'sku',
            'qty'
        ]);

        $consignmentId = $this->validateInt($data, 'consignment_id', null, 1);
        $this->validateInt($data, 'product_id', null, 1);
        $this->validateString($data, 'sku', null, 1, 100);
        $this->validateInt($data, 'qty', null, 1);

        try {
            $result = $this->service->addItem($consignmentId, $data);

            return $this->success(
                $result,
                'Item added to consignment successfully',
                ['http_code' => 201]
            );

        } catch (\RuntimeException $e) {
            return $this->error(
                $e->getMessage(),
                'ADD_ITEM_FAILED',
                ['consignment_id' => $consignmentId],
                500
            );
        }
    }

    /**
     * Update consignment status
     *
     * @param array $data Request data
     * @return array Response envelope
     */
    protected function handleUpdateStatus(array $data): array {
        // Validate CSRF token
        $this->validateCSRF($data);

        // Validate required fields
        $this->validateRequired($data, ['id', 'status']);

        $id = $this->validateInt($data, 'id', null, 1);
        $status = $this->validateString($data, 'status', null, 1, 50);

        // Validate status value
        $validStatuses = ['draft', 'sent', 'receiving', 'received', 'completed', 'cancelled'];
        if (!in_array($status, $validStatuses)) {
            return $this->error(
                "Invalid status value: {$status}",
                'VALIDATION_ERROR',
                [
                    'field' => 'status',
                    'value' => $status,
                    'allowed' => $validStatuses
                ],
                422
            );
        }

        try {
            $updated = $this->service->updateStatus($id, $status);

            return $this->success([
                'updated' => $updated,
                'id' => $id,
                'status' => $status
            ], 'Status updated successfully');

        } catch (\InvalidArgumentException $e) {
            return $this->error(
                $e->getMessage(),
                'INVALID_STATUS_TRANSITION',
                [
                    'id' => $id,
                    'status' => $status
                ],
                422
            );
        } catch (\RuntimeException $e) {
            return $this->error(
                $e->getMessage(),
                'UPDATE_FAILED',
                ['id' => $id],
                500
            );
        }
    }

    /**
     * Update item packed quantity
     *
     * @param array $data Request data
     * @return array Response envelope
     */
    protected function handleUpdateItemQty(array $data): array {
        // Validate CSRF token
        $this->validateCSRF($data);

        // Validate required fields
        $this->validateRequired($data, ['item_id', 'packed_qty']);

        $itemId = $this->validateInt($data, 'item_id', null, 1);
        $packedQty = $this->validateInt($data, 'packed_qty', null, 0);

        try {
            $updated = $this->service->updateItemPackedQty($itemId, $packedQty);

            return $this->success([
                'updated' => $updated,
                'item_id' => $itemId,
                'packed_qty' => $packedQty
            ], 'Item quantity updated successfully');

        } catch (\InvalidArgumentException $e) {
            return $this->error(
                $e->getMessage(),
                'VALIDATION_ERROR',
                [
                    'field' => 'packed_qty',
                    'value' => $packedQty
                ],
                422
            );
        } catch (\RuntimeException $e) {
            return $this->error(
                $e->getMessage(),
                'UPDATE_FAILED',
                ['item_id' => $itemId],
                500
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
     *
     * @param array $data Request data
     * @param string $key Parameter name
     * @param int|null $default Default value
     * @param int|null $min Minimum value
     * @param int|null $max Maximum value
     * @param bool $nullable Allow null
     * @return int|null
     * @throws \Exception
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
     * Validate string parameter
     *
     * @param array $data Request data
     * @param string $key Parameter name
     * @param string|null $default Default value
     * @param int|null $minLength Minimum length
     * @param int|null $maxLength Maximum length
     * @return string|null
     * @throws \Exception
     */
    private function validateString(
        array $data,
        string $key,
        ?string $default = null,
        ?int $minLength = null,
        ?int $maxLength = null
    ): ?string {
        if (!isset($data[$key])) {
            return $default;
        }

        $value = (string)$data[$key];

        if ($minLength !== null && strlen($value) < $minLength) {
            throw new \Exception("{$key} must be at least {$minLength} characters");
        }

        if ($maxLength !== null && strlen($value) > $maxLength) {
            throw new \Exception("{$key} must be at most {$maxLength} characters");
        }

        return $value;
    }

    /**
     * Validate required fields
     *
     * @param array $data Request data
     * @param array $required Required field names
     * @throws \Exception
     */
    protected function validateRequired(array $data, array $required): void {
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
