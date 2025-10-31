<?php
/**
 * Complete Flagged Product API
 * 
 * Handles product completion with full anti-cheat validation
 * 
 * @security MAXIMUM
 */

header('Content-Type: application/json');

require_once $_SERVER['DOCUMENT_ROOT'] . '/app.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/assets/services/CISLogger.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/modules/flagged_products/lib/Logger.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/modules/flagged_products/models/FlaggedProductsRepository.php';

use FlaggedProducts\Lib\Logger;

// Track API request start time
$apiStartTime = microtime(true);

// Validate session
if (!isset($_SESSION['userID'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Parse JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid JSON']);
    exit;
}

// Validate required fields
$required = ['product_id', 'quantity', 'time_taken', 'security_context'];
foreach ($required as $field) {
    if (!isset($input[$field])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => "Missing field: $field"]);
        exit;
    }
}

$productId = (int)$input['product_id'];
$quantity = (int)$input['quantity'];
$timeTaken = (int)$input['time_taken'];
$securityContext = $input['security_context'];
$userId = $_SESSION['userID'];

// MANDATORY: Get outlet_id from POST data (not session)
$outletId = $input['outlet_id'] ?? null;
if (empty($outletId)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing outlet_id']);
    exit;
}

// Validate quantity
if ($quantity < 0 || $quantity > 9999) {
    echo json_encode(['success' => false, 'error' => 'Invalid quantity']);
    exit;
}

// Validate time taken (2-120 seconds for human behavior)
if ($timeTaken < 2 || $timeTaken > 120) {
    CISLogger::security('suspicious_timing', 'high', $userId, [
        'product_id' => $productId,
        'time_taken' => $timeTaken,
        'expected_range' => '2-120 seconds'
    ]);
}

try {
    // Complete product through repository
    $result = FlaggedProductsRepository::completeProduct(
        $productId,
        $userId,
        $outletId,
        $quantity,
        $securityContext,
        $timeTaken
    );
    
    if ($result['success']) {
        // Log success with enhanced logger
        Logger::productCompleted(
            $productId,
            'user_action',
            $result['accuracy'] ?? null,
            $timeTaken / 1000.0, // Convert ms to seconds
            [
                'quantity' => $quantity,
                'points_awarded' => $result['points_awarded'],
                'security_score' => $securityContext['securityScore'] ?? null,
                'outlet_id' => $outletId
            ]
        );
        
        // Log API performance
        $apiDuration = (microtime(true) - $apiStartTime) * 1000;
        Logger::apiResponse('complete-product', $apiDuration, true);
        
        echo json_encode([
            'success' => true,
            'message' => $result['message'],
            'points_awarded' => $result['points_awarded'],
            'new_points' => $result['new_total_points'],
            'accuracy' => $result['accuracy'],
            'achievements' => $result['achievements'] ?? []
        ]);
        
    } else {
        // Log failure with enhanced logger
        Logger::error(
            'complete_product_failed',
            $result['error'],
            'product',
            $productId,
            ['quantity' => $quantity, 'outlet_id' => $outletId]
        );
        
        // Log API performance
        $apiDuration = (microtime(true) - $apiStartTime) * 1000;
        Logger::apiResponse('complete-product', $apiDuration, false, $result['error']);
        
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => $result['error']
        ]);
    }
    
} catch (Exception $e) {
    // Log exception with enhanced logger
    Logger::error(
        'complete_product_exception',
        $e->getMessage(),
        'product',
        $productId,
        ['exception_trace' => $e->getTraceAsString(), 'outlet_id' => $outletId]
    );
    
    // Log API performance
    $apiDuration = (microtime(true) - $apiStartTime) * 1000;
    Logger::apiResponse('complete-product', $apiDuration, false, $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Server error: ' . $e->getMessage()
    ]);
}
