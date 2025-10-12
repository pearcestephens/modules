<?php
/**
 * Receive Auto-Save API Endpoint - Enhanced with Standard Response Envelope
 * 
 * Handles auto-saving of receive progress with enterprise-grade error handling,
 * idempotency, and proper validation using CIS API Response standards.
 * 
 * @package CIS\Consignments\API
 * @version 2.0.0 - Enhanced with API Response envelope
 * @created 2025-10-12
 */

// Load environment and initialize independent module bootstrap
require_once __DIR__ . '/../lib/Db.php';
require_once __DIR__ . '/../lib/ApiResponse.php';
require_once __DIR__ . '/../lib/Security.php';

use Transfers\Lib\Security;
use Transfers\Lib\Db;

// Initialize independent environment
Db::loadEnv();
Db::startSession();

// Initialize security context and tracing
Security::initializeContext();
$correlation_id = Security::getCorrelationId();

// Initialize API response handler
$debugMode = ($_ENV['APP_ENV'] ?? 'production') !== 'production';
CISApiResponse::init($debugMode);

try {
    // ========================================
    // ENTERPRISE SECURITY VALIDATION
    // ========================================
    
    Security::addTraceEvent('request_start', [
        'method' => $_SERVER['REQUEST_METHOD'],
        'uri' => $_SERVER['REQUEST_URI'] ?? 'unknown'
    ]);
    
    // Validate request method
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        Security::logSecurityEvent('invalid_method_attempt', [
            'method' => $_SERVER['REQUEST_METHOD'],
            'endpoint' => 'receive_autosave'
        ]);
        CISApiResponse::methodNotAllowed(['POST']);
    }
    
    // Rate limiting check
    if (!Security::checkRateLimit('receive_autosave', 120, 60)) { // 120 requests per minute
        Security::logSecurityEvent('rate_limit_exceeded', [
            'action' => 'receive_autosave',
            'limit' => 120
        ]);
        CISApiResponse::error('Rate limit exceeded. Please slow down your requests.', 429);
        exit;
    }
    
    // Require AJAX request
    CISApiResponse::requireAjax();
    
    // Validate and parse JSON input
    $data = CISApiResponse::validateJson();
    
    // Enterprise-grade input validation
    $validation_rules = [
        'transfer_id' => [
            'required' => true,
            'type' => 'int',
            'min' => 1,
            'max' => 999999999
        ],
        'transfer_mode' => [
            'required' => true,
            'type' => 'enum',
            'values' => ['GENERAL', 'JUICE', 'STAFF', 'SUPPLIER']
        ],
        'items' => [
            'required' => true,
            'type' => 'array'
        ],
        'totals' => [
            'required' => true,
            'type' => 'array'
        ],
        'csrf_token' => [
            'required' => false,
            'type' => 'string',
            'max_length' => 64
        ],
        'receiver_name' => [
            'required' => false,
            'type' => 'string',
            'max_length' => 255
        ],
        'delivery_notes' => [
            'required' => false,
            'type' => 'string',
            'max_length' => 1000
        ],
        'tracking_number' => [
            'required' => false,
            'type' => 'string',
            'max_length' => 100
        ]
    ];
    
    $validation_result = Security::validateInput($data, $validation_rules);
    
    if (!$validation_result['valid']) {
        Security::addTraceEvent('validation_failed', $validation_result['errors']);
        CISApiResponse::validationError('Input validation failed', $validation_result['errors']);
        exit;
    }
    
    $data = $validation_result['data'];
    Security::addTraceEvent('input_validation_passed');
    
    $transfer_id = $data['transfer_id'];
    $transfer_mode = $data['transfer_mode'];
    $items = $data['items'];
    $totals = $data['totals'];
    $timestamp = $data['timestamp'] ?? date('Y-m-d H:i:s');
    
    Security::addTraceEvent('data_extracted', [
        'transfer_id' => $transfer_id,
        'transfer_mode' => $transfer_mode,
        'items_count' => count($items)
    ]);
    
    // Validate items array structure
    if (empty($items)) {
        CISApiResponse::validationError(['items' => 'Items array cannot be empty']);
        exit;
    }
    
    // ========================================
    // DATABASE CONNECTION & TRANSACTION
    // ========================================
    
    try {
        $pdo = Db::pdo();
        $pdo->beginTransaction();
        
        // Generate idempotency key for this save operation
        $json_input = json_encode($data);
        $idempotency_key = $transfer_id . '_' . md5($json_input . date('Y-m-d H:i'));
        
        // Check if this exact save was already processed (idempotency)
        $stmt = $pdo->prepare("
            SELECT id, draft_updated_at, draft_data
            FROM transfers 
            WHERE id = ? AND transfer_mode = ?
        ");
        $stmt->execute([$transfer_id, $transfer_mode]);
        $transfer = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$transfer) {
            CISApiResponse::notFound('Transfer not found or mode mismatch');
        }
        
        // Parse existing draft data
        $existing_draft = json_decode($transfer['draft_data'] ?? '{}', true);
        $existing_idempotency = $existing_draft['idempotency_key'] ?? null;
        
        if ($existing_idempotency === $idempotency_key) {
            // This save was already processed within the last minute
            $time_diff = time() - strtotime($transfer['draft_updated_at']);
            if ($time_diff < 60) {
                $pdo->commit();
                CISApiResponse::success([
                    'transfer_id' => $transfer_id,
                    'timestamp' => $transfer['draft_updated_at'],
                    'idempotent' => true
                ], 'Already saved (idempotency)');
            }
        }
        
        // ========================================
        // VERIFY TRANSFER EXISTS & PERMISSIONS
        // ========================================
        
        // Get full transfer details with outlet information
        $stmt = $pdo->prepare("
            SELECT t.*, 
                   o_from.name AS from_outlet_name,
                   o_to.name AS to_outlet_name,
                   u.username AS created_by_username
            FROM transfers t
            LEFT JOIN vend_outlets o_from ON t.from_outlet_id = o_from.id
            LEFT JOIN vend_outlets o_to ON t.to_outlet_id = o_to.id
            LEFT JOIN users u ON t.created_by = u.id
            WHERE t.id = ? AND t.transfer_mode = ?
        ");
        $stmt->execute([$transfer_id, $transfer_mode]);
        $transfer_details = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$transfer_details) {
            CISApiResponse::notFound('Transfer not found or mode mismatch');
        }
        
        // Check if transfer is in a receivable state
        $receivable_statuses = ['PACKED', 'IN_TRANSIT', 'PARTIAL_RECEIVED'];
        if (!in_array($transfer_details['status'], $receivable_statuses)) {
            CISApiResponse::validationError([
                'status' => "Transfer cannot be received in current status: {$transfer_details['status']}"
            ]);
        }
        
        // TODO: Add user permission checks here
        // - Check if user has permission to receive for this outlet
        // - Check if user is authorized for this transfer mode
        // - Check if transfer is locked by another user
        
        // ========================================
        // VALIDATE TRANSFER ITEMS
        // ========================================
        
        // Get all valid items for this transfer
        $stmt = $pdo->prepare("
            SELECT ti.*, p.name AS product_name, p.sku, p.avg_weight_grams
            FROM transfer_items ti
            LEFT JOIN vend_products p ON ti.product_id = p.id
            WHERE ti.transfer_id = ?
        ");
        $stmt->execute([$transfer_id]);
        $valid_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $valid_item_ids = array_column($valid_items, 'id');
        
        // Validate each item in the request
        $validated_items = [];
        $total_received_check = 0;
        $total_weight_check = 0;
        
        foreach ($items as $item) {
            // Required item fields
            $item_required = ['item_id', 'product_id', 'qty_requested', 'qty_received'];
            foreach ($item_required as $field) {
                if (!isset($item[$field])) {
                    throw new InvalidArgumentException("Item missing required field: {$field}");
                }
            }
            
            $item_id = (int) $item['item_id'];
            $product_id = (int) $item['product_id'];
            $qty_requested = (float) $item['qty_requested'];
            $qty_received = (float) $item['qty_received'];
            $weight_grams = (float) ($item['weight_grams'] ?? 0);
            
            // Validate item exists in transfer
            if (!in_array($item_id, $valid_item_ids)) {
                throw new InvalidArgumentException("Invalid item ID: {$item_id}");
            }
            
            // Validate quantities
            if ($qty_received < 0) {
                throw new InvalidArgumentException("Received quantity cannot be negative for item {$item_id}");
            }
            
            if ($qty_received > 99999) {
                throw new InvalidArgumentException("Received quantity too large for item {$item_id}");
            }
            
            // Find matching valid item for additional validation
            $valid_item = array_filter($valid_items, function($vi) use ($item_id) {
                return $vi['id'] == $item_id;
            });
            $valid_item = reset($valid_item);
            
            if ($valid_item && $product_id != $valid_item['product_id']) {
                throw new InvalidArgumentException("Product ID mismatch for item {$item_id}");
            }
            
            $validated_items[] = [
                'item_id' => $item_id,
                'product_id' => $product_id,
                'qty_requested' => $qty_requested,
                'qty_received' => $qty_received,
                'weight_grams' => $weight_grams,
                'product_name' => $valid_item['product_name'] ?? 'Unknown'
            ];
            
            $total_received_check += $qty_received;
            $total_weight_check += $weight_grams * $qty_received;
        }
        
        // Validate totals match calculated values (with small tolerance for floating point)
        $tolerance = 0.01;
        if (abs($totals['total_received'] - $total_received_check) > $tolerance) {
            throw new InvalidArgumentException('Total received quantity mismatch');
        }
        
        // Calculate completion percentage and new status
        $completion_percentage = ($totals['total_received'] / max($totals['total_requested'], 1)) * 100;
        
        $new_status = $transfer_details['status'];
        if ($completion_percentage > 0 && $transfer_details['status'] === 'PACKED') {
            $new_status = 'PARTIAL_RECEIVED';
        }
        
        // ========================================
        // SAVE AUTO-SAVE RECORD TO TRANSFERS.DRAFT_DATA
        // ========================================
        
        // Build comprehensive draft data with all user input
        $draft_data = [
            'action' => 'receive_autosave',
            'idempotency_key' => $idempotency_key,
            'autosave_version' => '2.0.0',
            'user_input' => [
                'items' => $validated_items,
                'totals' => $totals,
                'receiver_name' => $data['receiver_name'] ?? '',
                'delivery_notes' => $data['delivery_notes'] ?? '',
                'tracking_number' => $data['tracking_number'] ?? '',
                'delivery_method' => $data['delivery_method'] ?? '',
                'unexpected_products' => $data['unexpected_products'] ?? [],
                'partial_delivery_reason' => $data['partial_delivery_reason'] ?? '',
                'supplier_invoice_ref' => $data['supplier_invoice_ref'] ?? '',
                'damage_notes' => $data['damage_notes'] ?? '',
                'temperature_check' => $data['temperature_check'] ?? null,
                'packaging_condition' => $data['packaging_condition'] ?? '',
                'signature_required' => $data['signature_required'] ?? false,
                'photos_attached' => $data['photos_attached'] ?? false
            ],
            'metadata' => [
                'user_id' => $_SESSION['user_id'] ?? 0,
                'user_ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
                'browser_timestamp' => $data['browser_timestamp'] ?? $timestamp,
                'server_timestamp' => date('Y-m-d H:i:s'),
                'correlation_id' => uniqid('receive_', true),
                'autosave_count' => ($existing_draft['metadata']['autosave_count'] ?? 0) + 1,
                'session_id' => session_id(),
                'csrf_token' => $data['csrf_token'] ?? null
            ],
            'validation' => [
                'items_count' => count($validated_items),
                'total_received_check' => $total_received_check,
                'total_weight_check' => $total_weight_check,
                'completion_percentage' => round($completion_percentage, 2),
                'status_transition' => [
                    'from' => $transfer_details['status'],
                    'to' => $new_status
                ]
            ]
        ];
        
        // Update transfers table with draft data
        $stmt = $pdo->prepare("
            UPDATE transfers 
            SET draft_data = ?,
                draft_updated_at = NOW(),
                updated_at = NOW()
            WHERE id = ?
        ");
        
        $draft_json = json_encode($draft_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $stmt->execute([$draft_json, $transfer_id]);
        
        // ========================================
        // UPDATE TRANSFER ITEMS WITH RECEIVED QUANTITIES
        // ========================================
        
        foreach ($validated_items as $item) {
            $stmt = $pdo->prepare("
                UPDATE transfer_items 
                SET qty_received = ?, 
                    weight_received_grams = ?,
                    updated_at = NOW()
                WHERE id = ? AND transfer_id = ?
            ");
            
            $weight_received = $item['weight_grams'] * $item['qty_received'];
            
            $stmt->execute([
                $item['qty_received'],
                $weight_received,
                $item['item_id'],
                $transfer_id
            ]);
        }
        
        // ========================================
        // UPDATE TRANSFER STATUS IF NEEDED
        // ========================================
        
        $stmt = $pdo->prepare("
            UPDATE transfers 
            SET total_received = ?,
                weight_received_grams = ?,
                completion_percentage = ?,
                status = ?,
                last_received_at = NOW(),
                updated_at = NOW()
            WHERE id = ?
        ");
        
        $stmt->execute([
            $totals['total_received'],
            $totals['weight_grams'],
            $completion_percentage,
            $new_status,
            $transfer_id
        ]);
        
        // ========================================
        // LOG THE AUTO-SAVE ACTION
        // ========================================
        
        $user_id = $_SESSION['user_id'] ?? 0;
        $correlation_id = $draft_data['metadata']['correlation_id'];
        
        $log_data = [
            'action' => 'receive_autosave',
            'transfer_id' => $transfer_id,
            'transfer_mode' => $transfer_mode,
            'items_count' => count($validated_items),
            'total_received' => $totals['total_received'],
            'completion_percentage' => round($completion_percentage, 2),
            'correlation_id' => $correlation_id,
            'autosave_count' => $draft_data['metadata']['autosave_count'],
            'user_id' => $user_id,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        $stmt = $pdo->prepare("
            INSERT INTO activity_logs (
                module,
                action,
                reference_id,
                reference_type,
                data,
                user_id,
                created_at
            ) VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            'consignments',
            'receive_autosave',
            $transfer_id,
            'transfer',
            json_encode($log_data),
            $user_id
        ]);
        
        // ========================================
        // COMMIT TRANSACTION & SUCCESS RESPONSE
        // ========================================
        
        // Commit transaction
        $pdo->commit();
        Security::addTraceEvent('transaction_committed');
        
        // Get comprehensive trace data for monitoring
        $trace_data = Security::getTraceData();
        
        $response_data = [
            'transfer_id' => $transfer_id,
            'items_saved' => count($validated_items),
            'total_received' => $totals['total_received'],
            'completion_percentage' => round($completion_percentage, 2),
            'new_status' => $new_status,
            'correlation_id' => $correlation_id,
            'autosave_count' => $draft_data['metadata']['autosave_count'],
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        // Include trace data in debug mode
        if ($debugMode) {
            $response_data['_debug'] = [
                'trace' => $trace_data,
                'security_context' => Security::getTraceData()['security_context'] ?? null
            ];
        }
        
        CISApiResponse::success($response_data, 'Receive progress saved successfully');
        
    } catch (PDOException $e) {
        // Database error - rollback transaction
        if ($pdo->inTransaction()) {
            $pdo->rollback();
        }
        
        Security::addTraceEvent('database_error', [
            'error_code' => $e->getCode(),
            'error_message' => $e->getMessage()
        ]);
        
        Security::logSecurityEvent('database_error', [
            'transfer_id' => $transfer_id ?? null,
            'error_code' => $e->getCode(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]);
        
        error_log("Database error in receive_autosave.php: " . $e->getMessage());
        CISApiResponse::serverError('Database error occurred', null, $e);
    }
    
} catch (InvalidArgumentException $e) {
    // Validation error
    Security::addTraceEvent('validation_error', ['message' => $e->getMessage()]);
    CISApiResponse::validationError([$e->getMessage()]);
    
} catch (Exception $e) {
    // General error
    Security::addTraceEvent('general_error', [
        'type' => get_class($e),
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
    
    Security::logSecurityEvent('api_error', [
        'endpoint' => 'receive_autosave',
        'error_type' => get_class($e),
        'message' => $e->getMessage()
    ]);
    
    error_log("Error in receive_autosave.php: " . $e->getMessage());
    CISApiResponse::serverError('Server error occurred', null, $e);
}
?>