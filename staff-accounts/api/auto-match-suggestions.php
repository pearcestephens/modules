<?php
/**
 * Auto-Match Suggestions API
 * 
 * GET: Retrieve auto-match suggestions for review
 * POST: Approve/reject auto-match suggestions
 * 
 * @package CIS\StaffAccounts\API
 */

require_once '../bootstrap.php';

// Handle CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

use CIS\StaffAccounts\Lib\EmployeeMappingService;

$service = new EmployeeMappingService();

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Get auto-match suggestions
        $limit = (int)($_GET['limit'] ?? 50);
        $minConfidence = (float)($_GET['min_confidence'] ?? 0.6);
        
        $suggestions = $service->getAutoMatchSuggestions($limit, $minConfidence);
        
        echo json_encode([
            'success' => true,
            'data' => $suggestions,
            'count' => count($suggestions),
            'meta' => [
                'limit' => $limit,
                'min_confidence' => $minConfidence,
                'generated_at' => date('Y-m-d H:i:s')
            ]
        ], JSON_PRETTY_PRINT);
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Process approval/rejection actions
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            throw new \Exception('Invalid JSON input');
        }
        
        $action = $input['action'] ?? '';
        $mappingId = (int)($input['mapping_id'] ?? 0);
        $user = $input['user'] ?? 'system';
        
        switch ($action) {
            case 'approve':
                $notes = $input['notes'] ?? '';
                $result = $service->approveAutoMatch($mappingId, $user, $notes);
                
                echo json_encode([
                    'success' => $result,
                    'message' => $result ? 'Auto-match approved successfully' : 'Failed to approve auto-match',
                    'action' => 'approve',
                    'mapping_id' => $mappingId
                ]);
                break;
                
            case 'reject':
                $reason = $input['reason'] ?? '';
                if (empty($reason)) {
                    throw new \Exception('Rejection reason is required');
                }
                
                $result = $service->rejectAutoMatch($mappingId, $user, $reason);
                
                echo json_encode([
                    'success' => $result,
                    'message' => $result ? 'Auto-match rejected successfully' : 'Failed to reject auto-match',
                    'action' => 'reject',
                    'mapping_id' => $mappingId
                ]);
                break;
                
            case 'bulk_approve':
                $mappingIds = $input['mapping_ids'] ?? [];
                $notes = $input['notes'] ?? '';
                
                if (empty($mappingIds) || !is_array($mappingIds)) {
                    throw new \Exception('Mapping IDs array is required for bulk approval');
                }
                
                $results = $service->bulkApproveAutoMatches($mappingIds, $user, $notes);
                $successCount = count(array_filter($results));
                
                echo json_encode([
                    'success' => $successCount > 0,
                    'message' => "Bulk approval completed: {$successCount}/" . count($mappingIds) . " successful",
                    'action' => 'bulk_approve',
                    'results' => $results,
                    'success_count' => $successCount,
                    'total_count' => count($mappingIds)
                ]);
                break;
                
            default:
                throw new \Exception('Invalid action: ' . $action);
        }
        
    } else {
        throw new \Exception('Method not allowed');
    }
    
} catch (\Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}