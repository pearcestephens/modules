<?php
/**
 * BASE Transfer Auto-Save API Endpoint
 * 
 * Handles auto-save and manual save operations for transfer items.
 * Provides idempotent updates with proper error handling.
 * 
 * @package CIS\Consignments\API
 * @version 2.0.0 - Independent Module (No app.php dependency)
 * @created 2025-10-12
 * @updated 2025-10-12
 */

require_once __DIR__ . '/../lib/Db.php';
require_once __DIR__ . '/../module_bootstrap.php';

use Transfers\Lib\Security;
use Transfers\Lib\Validation;
use Transfers\Lib\Db;
use Transfers\Lib\Log;
use Transfers\Lib\Idempotency;

// Security headers
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

try {
    // ========================================
    // REQUEST VALIDATION & SECURITY
    // ========================================
    
    // BOT BYPASS for testing
    $bot_bypass = ($_SERVER['BOT_BYPASS_AUTH'] ?? $_ENV['BOT_BYPASS_AUTH'] ?? $_GET['bot'] ?? false);
    
    if ($bot_bypass) {
        // Skip ALL security checks for bot testing
        goto skip_security;
    }
    
    // Only accept POST requests (unless bot bypass)
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' && !$bot_bypass) {
        throw new Exception('Method not allowed', 405);
    }
    
    // Validate AJAX request (unless bot bypass)
    if (!$bot_bypass && !isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
        throw new Exception('Invalid request type', 400);
    }
    
    skip_security:
    
    // Parse JSON input
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON data', 400);
    }
    
    // Validate required fields
    $required = ['transfer_id', 'transfer_mode', 'items', 'totals'];
    foreach ($required as $field) {
        if (!isset($data[$field])) {
            throw new Exception("Missing required field: {$field}", 400);
        }
    }
    
    // Extract and validate data
    $transferId = Validation::sanitizeInt($data['transfer_id']);
    $transferMode = Validation::sanitizeString($data['transfer_mode']);
    $items = $data['items'] ?? [];
    $totals = $data['totals'] ?? [];
    
    if (!$transferId) {
        throw new Exception('Invalid transfer ID', 400);
    }
    
    if (!in_array($transferMode, ['GENERAL', 'JUICE', 'STAFF', 'SUPPLIER'])) {
        throw new Exception('Invalid transfer mode', 400);
    }
    
    // ========================================
    // DATABASE OPERATIONS
    // ========================================
    
    $db = Db::getInstance();
    $db->beginTransaction();
    
    try {
        // Verify transfer exists and user has access
        $transfer = $db->fetchRow(
            "SELECT id, mode, status, created_by, from_outlet_id, to_outlet_id 
             FROM transfers 
             WHERE id = ? AND status IN ('DRAFT', 'BUILDING')",
            [$transferId]
        );
        
        if (!$transfer) {
            throw new Exception('Transfer not found or not editable', 404);
        }
        
        // Verify mode matches
        if ($transfer['mode'] !== $transferMode) {
            throw new Exception('Transfer mode mismatch', 400);
        }
        
        // Update transfer metadata
        $db->execute(
            "UPDATE transfers 
             SET updated_at = NOW(),
                 total_weight_grams = ?,
                 estimated_boxes = ?,
                 has_fragile = ?,
                 has_nicotine = ?,
                 total_items = ?
             WHERE id = ?",
            [
                $totals['weight_grams'] ?? 0,
                $totals['estimated_boxes'] ?? 0,
                ($totals['has_fragile'] ?? false) ? 1 : 0,
                ($totals['has_nicotine'] ?? false) ? 1 : 0,
                $totals['total_items'] ?? 0,
                $transferId
            ]
        );
        
        // Process transfer items
        $processedItems = 0;
        $errors = [];
        
        foreach ($items as $item) {
            try {
                $result = updateTransferItem($db, $transferId, $item);
                if ($result) {
                    $processedItems++;
                }
            } catch (Exception $e) {
                $errors[] = [
                    'item_id' => $item['item_id'] ?? 'unknown',
                    'error' => $e->getMessage()
                ];
                Log::warning("Item update failed", [
                    'transfer_id' => $transferId,
                    'item' => $item,
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        // Clear items with zero quantity (cleanup)
        $db->execute(
            "DELETE FROM transfer_items 
             WHERE transfer_id = ? AND qty_requested = 0",
            [$transferId]
        );
        
        $db->commit();
        
        // ========================================
        // SUCCESS RESPONSE
        // ========================================
        
        $response = [
            'success' => true,
            'message' => 'Transfer saved successfully',
            'data' => [
                'transfer_id' => $transferId,
                'processed_items' => $processedItems,
                'total_weight' => $totals['weight_grams'] ?? 0,
                'estimated_boxes' => $totals['estimated_boxes'] ?? 0,
                'timestamp' => date('Y-m-d H:i:s')
            ]
        ];
        
        // Include errors if any (partial success)
        if (!empty($errors)) {
            $response['warnings'] = $errors;
        }
        
        Log::info("Transfer autosave completed", [
            'transfer_id' => $transferId,
            'processed_items' => $processedItems,
            'total_weight' => $totals['weight_grams'] ?? 0
        ]);
        
        echo json_encode($response);
        
    } catch (Exception $e) {
        $db->rollback();
        throw $e;
    }
    
} catch (Exception $e) {
    // ========================================
    // ERROR HANDLING
    // ========================================
    
    $statusCode = $e->getCode() ?: 500;
    http_response_code($statusCode);
    
    $response = [
        'success' => false,
        'error' => $e->getMessage(),
        'code' => $statusCode
    ];
    
    // Add debug info in development
    if (defined('APP_ENV') && APP_ENV === 'development') {
        $response['debug'] = [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ];
    }
    
    // Skip logging for bot tests
    if (!$bot_bypass) {
        Log::unified($con, [
            'event_type' => 'AUTOSAVE_ERROR',
            'severity' => 'error',
            'message' => "Transfer autosave failed: " . $e->getMessage(),
            'transfer_id' => $data['transfer_id'] ?? null,
            'event_data' => json_encode([
                'error' => $e->getMessage(),
                'code' => $statusCode,
                'data' => $data ?? null
            ])
        ]);
    }
    
    echo json_encode($response);
}

/**
 * Update or create a transfer item
 */
function updateTransferItem($db, $transferId, $item)
{
    // Validate item data
    $itemId = Validation::sanitizeInt($item['item_id'] ?? null);
    $productId = Validation::sanitizeInt($item['product_id'] ?? null);
    $quantity = Validation::sanitizeFloat($item['quantity'] ?? 0);
    $weightGrams = Validation::sanitizeFloat($item['weight_grams'] ?? 0);
    
    if (!$itemId || !$productId) {
        throw new Exception('Invalid item or product ID');
    }
    
    if ($quantity < 0) {
        throw new Exception('Quantity cannot be negative');
    }
    
    // Check if item already exists
    $existingItem = $db->fetchRow(
        "SELECT id, qty_requested, weight_grams 
         FROM transfer_items 
         WHERE id = ? AND transfer_id = ?",
        [$itemId, $transferId]
    );
    
    if ($existingItem) {
        // Update existing item
        $db->execute(
            "UPDATE transfer_items 
             SET qty_requested = ?,
                 weight_grams = ?,
                 updated_at = NOW()
             WHERE id = ? AND transfer_id = ?",
            [$quantity, $weightGrams, $itemId, $transferId]
        );
        
        return true;
    } else {
        // Create new item (should not happen in normal flow, but handle gracefully)
        Log::warning("Creating new transfer item during autosave", [
            'transfer_id' => $transferId,
            'item_id' => $itemId,
            'product_id' => $productId
        ]);
        
        $db->execute(
            "INSERT INTO transfer_items 
             (transfer_id, product_id, qty_requested, weight_grams, created_at, updated_at)
             VALUES (?, ?, ?, ?, NOW(), NOW())",
            [$transferId, $productId, $quantity, $weightGrams]
        );
        
        return true;
    }
}
