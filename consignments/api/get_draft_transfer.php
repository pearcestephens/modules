<?php
declare(strict_types=1);

/**
 * Get Draft Transfer Data API Endpoint
 * 
 * Retrieves saved draft data for a transfer
 * 
 * @package CIS\Consignments\API
 * @version 1.0.0
 */

// This file is included by api.php, so bootstrap is already loaded

// Get request data (already parsed by api.php)
$transferId = $data['transfer_id'] ?? null;

// Validate required parameters
if (!$transferId) {
    ApiResponse::validationError([
        'transfer_id' => 'Transfer ID is required'
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
    
    // Get draft data from transfers table
    $stmt = $pdo->prepare("
        SELECT draft_data, draft_updated_at 
        FROM transfers 
        WHERE id = ?
    ");
    
    $stmt->execute([(int)$transferId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$result) {
        ApiResponse::error('Transfer not found', 404, ['transfer_id' => $transferId]);
        exit;
    }
    
    // Parse draft data (JSON)
    $draftData = null;
    if (!empty($result['draft_data'])) {
        $draftData = json_decode($result['draft_data'], true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            // Invalid JSON - treat as no draft data
            $draftData = null;
        }
    }
    
    ApiResponse::success([
        'draft_data' => $draftData,
        'draft_updated_at' => $result['draft_updated_at'],
        'has_draft' => !empty($draftData)
    ], 'Draft data retrieved successfully');
    
} catch (Exception $e) {
    ApiResponse::serverError('Failed to retrieve draft data', $e);
}