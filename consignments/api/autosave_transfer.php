<?php
declare(strict_types=1);

/**
 * Transfer Auto-Save API Endpoint
 * 
 * Saves draft transfer data to database (draft_data and draft_updated_at columns)
 * Called via api.php router with action=autosave_transfer
 * Uses shared ApiResponse envelope
 * 
 * @package CIS\Consignments\API
 * @version 2.0.0
 */

// This file is included by api.php, so $data and ApiResponse are already available

$transferId = $data['transfer_id'] ?? null;
$draftData = $data['draft_data'] ?? null;

if (!$transferId || !is_array($draftData)) {
    ApiResponse::validationError([
        'transfer_id' => !$transferId ? 'Transfer ID is required' : null,
        'draft_data' => !is_array($draftData) ? 'Draft data must be an array' : null
    ]);
}

try {
    // Get database connection - support both patterns
    global $pdo;
    if (!isset($pdo) || $pdo === null) {
        if (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] !== null) {
            $pdo = $GLOBALS['pdo'];
        } else {
            ApiResponse::serverError('Database connection not available', ['debug' => 'pdo not initialized']);
            exit;
        }
    }
    
    // Encode JSON first and check for errors
    $draftJson = json_encode($draftData);
    if ($draftJson === false) {
        ApiResponse::error('Invalid JSON data', 400, ['json_error' => json_last_error_msg()]);
        exit;
    }
    
    // Update draft_data and draft_updated_at columns
    $stmt = $pdo->prepare("
        UPDATE transfers 
        SET draft_data = ?,
            draft_updated_at = NOW()
        WHERE id = ?
    ");
    
    $success = $stmt->execute([$draftJson, (int)$transferId]);
    
    if (!$success) {
        $errorInfo = $stmt->errorInfo();
        ApiResponse::serverError('Database update failed', [
            'sqlstate' => $errorInfo[0],
            'error_code' => $errorInfo[1],
            'error_message' => $errorInfo[2]
        ]);
        exit;
    }
    
    // Check if any rows were affected (transfer exists)
    if ($stmt->rowCount() === 0) {
        ApiResponse::error('Transfer not found', 404, ['transfer_id' => $transferId]);
        exit;
    }
    
    // Get the updated timestamp
    $stmt = $pdo->prepare("
        SELECT draft_updated_at 
        FROM transfers 
        WHERE id = ?
    ");
    $stmt->execute([(int)$transferId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    ApiResponse::success([
        'updated_at' => $result['draft_updated_at'] ?? date('Y-m-d H:i:s'),
        'items_count' => count($draftData)
    ], 'Transfer draft saved successfully');
    
} catch (Exception $e) {
    ApiResponse::serverError('Failed to save transfer draft', $e);
}
