<?php
/**
 * Customer Search and Manual Mapping API
 * 
 * GET: Search customers with advanced filtering
 * POST: Create manual employee-customer mappings
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
        $action = $_GET['action'] ?? 'search';
        
        switch ($action) {
            case 'search':
                // Search customers
                $query = $_GET['q'] ?? '';
                $limit = min((int)($_GET['limit'] ?? 20), 100); // Max 100 results
                $offset = (int)($_GET['offset'] ?? 0);
                
                // Build filters array
                $filters = [];
                if (!empty($_GET['store_id'])) {
                    $filters['store_id'] = (int)$_GET['store_id'];
                }
                if (!empty($_GET['has_email'])) {
                    $filters['has_email'] = $_GET['has_email'];
                }
                if (!empty($_GET['customer_group'])) {
                    $filters['customer_group'] = (int)$_GET['customer_group'];
                }
                if (!empty($_GET['created_from'])) {
                    $filters['created_from'] = $_GET['created_from'];
                }
                if (!empty($_GET['created_to'])) {
                    $filters['created_to'] = $_GET['created_to'];
                }
                if (!empty($_GET['exclude_mapped'])) {
                    $filters['exclude_mapped'] = $_GET['exclude_mapped'] === 'true';
                }
                
                $results = $service->searchCustomers($query, $filters, $limit, $offset);
                
                echo json_encode([
                    'success' => true,
                    'data' => $results['customers'],
                    'pagination' => $results['pagination'],
                    'filters_applied' => $filters,
                    'search_query' => $query
                ], JSON_PRETTY_PRINT);
                break;
                
            case 'details':
                // Get customer details
                $customerId = (int)($_GET['customer_id'] ?? 0);
                
                if (!$customerId) {
                    throw new \Exception('Customer ID is required');
                }
                
                $customer = $service->getCustomerDetails($customerId);
                
                if (!$customer) {
                    throw new \Exception('Customer not found');
                }
                
                echo json_encode([
                    'success' => true,
                    'data' => $customer
                ]);
                break;
                
            case 'stores':
                // Get available stores
                $stores = $service->getAvailableStores();
                
                echo json_encode([
                    'success' => true,
                    'data' => $stores
                ]);
                break;
                
            case 'customer_groups':
                // Get customer groups
                $groups = $service->getCustomerGroups();
                
                echo json_encode([
                    'success' => true,
                    'data' => $groups
                ]);
                break;
                
            case 'validate':
                // Validate potential mapping
                $employeeId = (int)($_GET['employee_id'] ?? 0);
                $customerId = (int)($_GET['customer_id'] ?? 0);
                
                if (!$employeeId || !$customerId) {
                    throw new \Exception('Both employee_id and customer_id are required');
                }
                
                $validation = $service->validateManualMapping($employeeId, $customerId);
                
                echo json_encode([
                    'success' => true,
                    'data' => $validation
                ]);
                break;
                
            default:
                throw new \Exception('Invalid action: ' . $action);
        }
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Create manual mapping
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            throw new \Exception('Invalid JSON input');
        }
        
        $action = $input['action'] ?? 'create_mapping';
        
        switch ($action) {
            case 'create_mapping':
                $employeeId = (int)($input['employee_id'] ?? 0);
                $customerId = (int)($input['customer_id'] ?? 0);
                $createdBy = $input['created_by'] ?? 'system';
                $notes = $input['notes'] ?? '';
                $verification = $input['verification'] ?? [];
                
                if (!$employeeId || !$customerId) {
                    throw new \Exception('Both employee_id and customer_id are required');
                }
                
                // Validate mapping first
                $validation = $service->validateManualMapping($employeeId, $customerId);
                
                if (!$validation['valid']) {
                    echo json_encode([
                        'success' => false,
                        'error' => 'Validation failed',
                        'validation' => $validation
                    ]);
                    break;
                }
                
                // Create the mapping
                $result = $service->createManualMapping(
                    $employeeId,
                    $customerId,
                    $createdBy,
                    $notes,
                    $verification
                );
                
                if ($result) {
                    echo json_encode([
                        'success' => true,
                        'message' => 'Manual mapping created successfully',
                        'validation' => $validation
                    ]);
                } else {
                    throw new \Exception('Failed to create manual mapping');
                }
                break;
                
            case 'validate_only':
                // Just validate without creating
                $employeeId = (int)($input['employee_id'] ?? 0);
                $customerId = (int)($input['customer_id'] ?? 0);
                
                if (!$employeeId || !$customerId) {
                    throw new \Exception('Both employee_id and customer_id are required');
                }
                
                $validation = $service->validateManualMapping($employeeId, $customerId);
                
                echo json_encode([
                    'success' => true,
                    'data' => $validation
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