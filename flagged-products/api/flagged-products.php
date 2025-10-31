<?php
/**
 * Flagged Products API
 * 
 * RESTful API for flagged products management
 * 
 * Endpoints:
 *   GET    /api/flagged-products.php?action=list&outlet=X        - List pending flags
 *   GET    /api/flagged-products.php?action=dashboard&outlet=X   - Get dashboard data
 *   POST   /api/flagged-products.php?action=create               - Create new flag
 *   POST   /api/flagged-products.php?action=complete             - Complete a flag
 *   POST   /api/flagged-products.php?action=bulk-complete        - Bulk complete
 *   DELETE /api/flagged-products.php?action=clear&outlet=X       - Clear pending
 *   GET    /api/flagged-products.php?action=stats                - System stats
 * 
 * @package CIS\FlaggedProducts\API
 * @version 2.0.0
 */

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

// CORS headers for modern AJAX
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Get action and method
$action = $_GET['action'] ?? $_POST['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

try {
    $service = getFlaggedProductsService();
    
    switch ($action) {
        // ====================================================================
        // GET: List pending flags for outlet
        // ====================================================================
        case 'list':
            if ($method !== 'GET') {
                jsonResponse(false, 'Method not allowed', '', 405);
            }
            
            $outletID = $_GET['outlet'] ?? '';
            if (empty($outletID)) {
                jsonResponse(false, 'outlet parameter required', '', 400);
            }
            
            $pending = $service->repo->getPendingForOutlet($outletID);
            
            jsonResponse(true, [
                'outlet' => $outletID,
                'count' => count($pending),
                'items' => $pending
            ]);
            break;
        
        // ====================================================================
        // GET: Dashboard data (pending, completed, accuracy, etc.)
        // ====================================================================
        case 'dashboard':
            if ($method !== 'GET') {
                jsonResponse(false, 'Method not allowed', '', 405);
            }
            
            $outletID = $_GET['outlet'] ?? '';
            if (empty($outletID)) {
                jsonResponse(false, 'outlet parameter required', '', 400);
            }
            
            $data = $service->getDashboardData($outletID);
            
            jsonResponse(true, $data);
            break;
        
        // ====================================================================
        // POST: Create new flag
        // ====================================================================
        case 'create':
            if ($method !== 'POST') {
                jsonResponse(false, 'Method not allowed', '', 405);
            }
            
            $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
            
            $productID = $input['product_id'] ?? '';
            $outletID = $input['outlet'] ?? '';
            $reason = $input['reason'] ?? 'Daily Product Stocktake';
            $qtyBefore = (int)($input['qty_before'] ?? 0);
            $isDummy = (int)($input['is_dummy'] ?? 0);
            
            if (empty($productID) || empty($outletID)) {
                jsonResponse(false, 'product_id and outlet required', '', 400);
            }
            
            $result = $service->flagProduct($productID, $outletID, $reason, $qtyBefore, $isDummy);
            
            jsonResponse(
                $result['success'],
                $result,
                $result['message'] ?? $result['error'] ?? '',
                $result['success'] ? 201 : 400
            );
            break;
        
        // ====================================================================
        // POST: Complete a flag
        // ====================================================================
        case 'complete':
            if ($method !== 'POST') {
                jsonResponse(false, 'Method not allowed', '', 405);
            }
            
            $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
            
            $productID = $input['product_id'] ?? '';
            $outletID = $input['outlet'] ?? '';
            $qtyAfter = isset($input['qty_after']) ? (int)$input['qty_after'] : null;
            $qtyBefore = isset($input['qty_before']) ? (int)$input['qty_before'] : null;
            $staffID = (int)($input['staff_id'] ?? $_SESSION['userID'] ?? 0);
            
            if (empty($productID) || empty($outletID) || $qtyAfter === null) {
                jsonResponse(false, 'product_id, outlet, and qty_after required', '', 400);
            }
            
            if ($staffID <= 0) {
                jsonResponse(false, 'Valid staff_id required', '', 401);
            }
            
            $result = $service->completeFlag($productID, $outletID, $staffID, $qtyAfter, $qtyBefore);
            
            jsonResponse(
                $result['success'],
                $result,
                $result['message'] ?? $result['error'] ?? '',
                $result['success'] ? 200 : 400
            );
            break;
        
        // ====================================================================
        // POST: Bulk complete multiple flags
        // ====================================================================
        case 'bulk-complete':
            if ($method !== 'POST') {
                jsonResponse(false, 'Method not allowed', '', 405);
            }
            
            $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
            
            $flags = $input['flags'] ?? [];
            $staffID = (int)($input['staff_id'] ?? $_SESSION['userID'] ?? 0);
            
            if (empty($flags) || !is_array($flags)) {
                jsonResponse(false, 'flags array required', '', 400);
            }
            
            if ($staffID <= 0) {
                jsonResponse(false, 'Valid staff_id required', '', 401);
            }
            
            $result = $service->bulkComplete($flags, $staffID);
            
            jsonResponse($result['success'], $result);
            break;
        
        // ====================================================================
        // DELETE: Clear pending flags for outlet
        // ====================================================================
        case 'clear':
            if ($method !== 'DELETE' && $method !== 'POST') {
                jsonResponse(false, 'Method not allowed', '', 405);
            }
            
            $outletID = $_GET['outlet'] ?? $_POST['outlet'] ?? '';
            if (empty($outletID)) {
                jsonResponse(false, 'outlet parameter required', '', 400);
            }
            
            $result = $service->clearPending($outletID);
            
            jsonResponse($result['success'], $result);
            break;
        
        // ====================================================================
        // GET: System-wide statistics
        // ====================================================================
        case 'stats':
            if ($method !== 'GET') {
                jsonResponse(false, 'Method not allowed', '', 405);
            }
            
            $stats = $service->getSystemStats();
            
            jsonResponse(true, $stats);
            break;
        
        // ====================================================================
        // POST: Generate dummy test product
        // ====================================================================
        case 'generate-dummy':
            if ($method !== 'POST') {
                jsonResponse(false, 'Method not allowed', '', 405);
            }
            
            $outletID = $_POST['outlet'] ?? '';
            if (empty($outletID)) {
                jsonResponse(false, 'outlet parameter required', '', 400);
            }
            
            $result = $service->generateDummyTest($outletID);
            
            jsonResponse(
                $result['success'],
                $result,
                $result['message'] ?? $result['error'] ?? '',
                $result['success'] ? 201 : 400
            );
            break;
        
        // ====================================================================
        // Unknown action
        // ====================================================================
        default:
            jsonResponse(false, 'Unknown action: ' . $action, '', 400);
    }
    
} catch (Exception $e) {
    error_log('[FLAGGED_PRODUCTS_API] Exception: ' . $e->getMessage());
    error_log('[FLAGGED_PRODUCTS_API] Trace: ' . $e->getTraceAsString());
    
    jsonResponse(
        false,
        'Internal server error: ' . $e->getMessage(),
        '',
        500
    );
}

?>
