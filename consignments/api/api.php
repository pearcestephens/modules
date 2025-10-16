<?php
declare(strict_types=1);

/**
 * Consignments API Central Router
 * 
 * Routes API requests to specific endpoint files based on action parameter
 * Uses shared ApiResponse envelope for consistent responses
 * 
 * @package CIS\Consignments\API
 * @version 2.0.0
 */

header('Content-Type: application/json');

// Bootstrap: Loads app.php, ApiResponse, Session, and all shared files
require_once __DIR__ . '/../bootstrap.php';

// Get request data from POST or GET
$requestMethod = $_SERVER['REQUEST_METHOD'];
$data = [];

if ($requestMethod === 'POST') {
    // Try to get JSON from request body first
    $rawInput = file_get_contents('php://input');
    if (!empty($rawInput)) {
        $jsonData = json_decode($rawInput, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $data = $jsonData;
        }
    }
    // Merge with $_POST (for form data)
    $data = array_merge($data, $_POST);
} else {
    // GET request
    $data = $_GET;
}

// Get action parameter
$action = $data['action'] ?? $_GET['action'] ?? null;

if (!$action) {
    ApiResponse::error('Missing action parameter', 400, 'MISSING_ACTION');
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
        ApiResponse::error('Unknown action: ' . $action, 404, 'UNKNOWN_ACTION');
}
