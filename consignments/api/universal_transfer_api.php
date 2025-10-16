<?php
declare(strict_types=1);

/**
 * Universal Transfer Data API Endpoint
 * 
 * RESTful API endpoint for retrieving complete transfer data using the 
 * universal transfer function. Supports multiple transfer types and 
 * various data formats.
 * 
 * @package CIS\Consignments\API
 * @version 1.0.0
 * @created 2025-10-15
 */

// Set headers for API response
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Include required files
require_once __DIR__ . '/../../bootstrap/app.php';
require_once __DIR__ . '/universal_transfer_data.php';

/**
 * Send JSON response and exit
 */
function sendJsonResponse(array $data, int $statusCode = 200): void 
{
    http_response_code($statusCode);
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit();
}

/**
 * Send error response
 */
function sendErrorResponse(string $message, string $code = 'ERROR', int $statusCode = 400, array $details = []): void 
{
    sendJsonResponse([
        'success' => false,
        'error' => [
            'code' => $code,
            'message' => $message,
            'details' => $details
        ],
        'timestamp' => date('c')
    ], $statusCode);
}

/**
 * Validate required parameters
 */
function validateParameters(array $required, array $data): void 
{
    $missing = [];
    foreach ($required as $param) {
        if (!isset($data[$param]) || empty($data[$param])) {
            $missing[] = $param;
        }
    }
    
    if (!empty($missing)) {
        sendErrorResponse(
            'Missing required parameters',
            'MISSING_PARAMETERS',
            400,
            ['missing' => $missing]
        );
    }
}

try {
    // Parse input based on request method
    $input = [];
    
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $input = $_GET;
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        
        if (strpos($contentType, 'application/json') !== false) {
            $jsonInput = json_decode(file_get_contents('php://input'), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                sendErrorResponse('Invalid JSON in request body', 'INVALID_JSON', 400);
            }
            $input = $jsonInput ?? [];
        } else {
            $input = $_POST;
        }
    } else {
        sendErrorResponse('Method not allowed', 'METHOD_NOT_ALLOWED', 405);
    }
    
    // Determine action
    $action = $input['action'] ?? 'get_transfer';
    $transferType = strtoupper($input['transfer_type'] ?? 'STOCK');
    
    // Validate transfer type
    $validTypes = ['STOCK', 'JUICE', 'STAFF', 'RETURN', 'PURCHASE_ORDER'];
    if (!in_array($transferType, $validTypes)) {
        sendErrorResponse(
            'Invalid transfer type', 
            'INVALID_TRANSFER_TYPE', 
            400,
            ['valid_types' => $validTypes]
        );
    }
    
    // Parse options
    $options = [];
    if (isset($input['audit_limit'])) {
        $options['audit_limit'] = (int)$input['audit_limit'];
    }
    
    switch ($action) {
        case 'get_transfer':
            // Get transfer by ID
            if (isset($input['id'])) {
                validateParameters(['id'], $input);
                $transferId = (int)$input['id'];
                
                $transfer = getCompleteTransferData($transferId, $transferType, $options);
                
                if (!$transfer) {
                    sendErrorResponse(
                        "Transfer with ID $transferId not found",
                        'TRANSFER_NOT_FOUND',
                        404
                    );
                }
                
                sendJsonResponse([
                    'success' => true,
                    'data' => $transfer,
                    'metadata' => [
                        'transfer_id' => $transferId,
                        'transfer_type' => $transferType,
                        'generated_at' => date('c'),
                        'data_completeness' => [
                            'items' => count($transfer->items),
                            'shipments' => count($transfer->shipments),
                            'receipts' => count($transfer->receipts),
                            'notes' => count($transfer->notes),
                            'audit_entries' => count($transfer->audit_log)
                        ]
                    ]
                ]);
                
            } elseif (isset($input['public_id'])) {
                // Get transfer by public ID
                validateParameters(['public_id'], $input);
                $publicId = $input['public_id'];
                
                $transfer = getCompleteTransferDataByPublicId($publicId, $transferType, $options);
                
                if (!$transfer) {
                    sendErrorResponse(
                        "Transfer with public ID '$publicId' not found",
                        'TRANSFER_NOT_FOUND',
                        404
                    );
                }
                
                sendJsonResponse([
                    'success' => true,
                    'data' => $transfer,
                    'metadata' => [
                        'public_id' => $publicId,
                        'transfer_type' => $transferType,
                        'generated_at' => date('c')
                    ]
                ]);
                
            } elseif (isset($input['vend_id'])) {
                // Get transfer by Vend ID
                validateParameters(['vend_id'], $input);
                $vendId = $input['vend_id'];
                
                $transfer = getCompleteTransferDataByVendId($vendId, $transferType, $options);
                
                if (!$transfer) {
                    sendErrorResponse(
                        "Transfer with Vend ID '$vendId' not found",
                        'TRANSFER_NOT_FOUND',
                        404
                    );
                }
                
                sendJsonResponse([
                    'success' => true,
                    'data' => $transfer,
                    'metadata' => [
                        'vend_id' => $vendId,
                        'transfer_type' => $transferType,
                        'generated_at' => date('c')
                    ]
                ]);
                
            } else {
                sendErrorResponse(
                    'Must provide either id, public_id, or vend_id',
                    'MISSING_IDENTIFIER',
                    400
                );
            }
            break;
            
        case 'get_transfer_list':
            // Get list of transfers with filters
            $filters = [];
            $limit = (int)($input['limit'] ?? 50);
            $offset = (int)($input['offset'] ?? 0);
            
            // Maximum limit for safety
            if ($limit > 100) {
                $limit = 100;
            }
            
            // Build filters
            $filterableFields = [
                'transfer_category', 'state', 'outlet_from', 'outlet_to',
                'created_after', 'created_before'
            ];
            
            foreach ($filterableFields as $field) {
                if (isset($input[$field]) && !empty($input[$field])) {
                    $filters[$field] = $input[$field];
                }
            }
            
            // Force transfer category if provided
            if ($transferType !== 'STOCK') {
                $filters['transfer_category'] = $transferType;
            }
            
            $transfers = getTransferList($filters, $limit, $offset);
            
            sendJsonResponse([
                'success' => true,
                'data' => $transfers,
                'metadata' => [
                    'count' => count($transfers),
                    'limit' => $limit,
                    'offset' => $offset,
                    'filters' => $filters,
                    'generated_at' => date('c')
                ]
            ]);
            break;
            
        case 'get_transfer_summary':
            // Get just the summary data (lightweight)
            validateParameters(['id'], $input);
            $transferId = (int)$input['id'];
            
            $transfer = getCompleteTransferData($transferId, $transferType, ['audit_limit' => 0]);
            
            if (!$transfer) {
                sendErrorResponse(
                    "Transfer with ID $transferId not found",
                    'TRANSFER_NOT_FOUND',
                    404
                );
            }
            
            sendJsonResponse([
                'success' => true,
                'data' => [
                    'transfer' => $transfer->transfer,
                    'outlets' => [
                        'from' => $transfer->outlet_from,
                        'to' => $transfer->outlet_to
                    ],
                    'summary' => $transfer->summary
                ],
                'metadata' => [
                    'transfer_id' => $transferId,
                    'transfer_type' => $transferType,
                    'generated_at' => date('c')
                ]
            ]);
            break;
            
        case 'get_transfer_items':
            // Get only items data
            validateParameters(['id'], $input);
            $transferId = (int)$input['id'];
            
            $transfer = getCompleteTransferData($transferId, $transferType, ['audit_limit' => 0]);
            
            if (!$transfer) {
                sendErrorResponse(
                    "Transfer with ID $transferId not found",
                    'TRANSFER_NOT_FOUND',
                    404
                );
            }
            
            sendJsonResponse([
                'success' => true,
                'data' => [
                    'items' => $transfer->items,
                    'summary' => [
                        'total_items' => $transfer->summary->total_items,
                        'total_qty_requested' => $transfer->summary->total_qty_requested,
                        'total_qty_sent' => $transfer->summary->total_qty_sent,
                        'total_qty_received' => $transfer->summary->total_qty_received,
                        'pack_completion_pct' => $transfer->summary->pack_completion_pct,
                        'receive_completion_pct' => $transfer->summary->receive_completion_pct
                    ]
                ],
                'metadata' => [
                    'transfer_id' => $transferId,
                    'generated_at' => date('c')
                ]
            ]);
            break;
            
        case 'get_transfer_shipments':
            // Get only shipments data
            validateParameters(['id'], $input);
            $transferId = (int)$input['id'];
            
            $transfer = getCompleteTransferData($transferId, $transferType, ['audit_limit' => 0]);
            
            if (!$transfer) {
                sendErrorResponse(
                    "Transfer with ID $transferId not found",
                    'TRANSFER_NOT_FOUND',
                    404
                );
            }
            
            sendJsonResponse([
                'success' => true,
                'data' => [
                    'shipments' => $transfer->shipments,
                    'summary' => [
                        'total_shipments' => count($transfer->shipments),
                        'total_parcels' => array_sum(array_map(function($s) { return count($s->parcels); }, $transfer->shipments))
                    ]
                ],
                'metadata' => [
                    'transfer_id' => $transferId,
                    'generated_at' => date('c')
                ]
            ]);
            break;
            
        default:
            sendErrorResponse(
                "Unknown action: $action",
                'UNKNOWN_ACTION',
                400,
                [
                    'valid_actions' => [
                        'get_transfer',
                        'get_transfer_list', 
                        'get_transfer_summary',
                        'get_transfer_items',
                        'get_transfer_shipments'
                    ]
                ]
            );
            break;
    }
    
} catch (Exception $e) {
    error_log("Universal Transfer API Error: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    
    sendErrorResponse(
        'Internal server error',
        'INTERNAL_ERROR',
        500,
        ['error_id' => uniqid('ERR_')]
    );
}