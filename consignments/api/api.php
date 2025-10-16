<?php
declare(strict_types=1);

/**
 * Consignments API Central Router
 * 
 * Routes API requests to specific endpoint files based on action parameter
 * Uses StandardResponse envelope for consistent API responses (v1.0.0)
 * 
 * @package CIS\Consignments\API
 * @version 2.1.0 - Migrated to StandardResponse
 */

header('Content-Type: application/json');

// Bootstrap: Loads app.php, StandardResponse, ApiResponse, Session, and all shared files
require_once __DIR__ . '/../bootstrap.php';

// Get request data using standardized helper (handles JSON, POST, GET)
$data = getRequestData();

// Get action parameter
$action = $data['action'] ?? null;

if (!$action) {
    StandardResponse::error('Missing action parameter', 400, 'MISSING_ACTION');
}

// Route to specific endpoint based on action
switch ($action) {
    case 'autosave_transfer':
        require_once __DIR__ . '/autosave_transfer.php';
        break;
        
    case 'get_draft_transfer':
        require_once __DIR__ . '/get_draft_transfer.php';
        break;
        
    case 'submit_transfer':
    case 'create_consignment':
    case 'save_transfer':  // 🔧 FIX: Route save_transfer to same handler as submit_transfer
        require_once __DIR__ . '/submit_transfer_simple.php';  // SIMPLE VERSION - Works NOW!
        break;
        
    case 'lightspeed':
    case 'vend':
    case 'sync':
        require_once __DIR__ . '/lightspeed.php';
        break;
        
    case 'universal_transfer':
    case 'get_transfer':
    case 'update_transfer':
        require_once __DIR__ . '/universal_transfer_api.php';
        break;
        
    case 'log_error':
        require_once __DIR__ . '/log_error.php';
        break;
        
    default:
        StandardResponse::error('Unknown action: ' . $action, 404, 'UNKNOWN_ACTION');
}
