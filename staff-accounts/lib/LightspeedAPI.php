<?php
/**
 * Lightspeed/Vend API Helper
 * 
 * Retrieves API credentials from config table and provides methods
 * to interact with Lightspeed Retail (Vend) API
 * 
 * @package CIS\StaffAccounts
 * @version 1.0.0
 */

class LightspeedAPI {
    
    private $db;
    private $vendToken;
    private $vendDomain;
    private $baseUrl;
    
    /**
     * Constructor - loads credentials from config table
     */
    public function __construct($db) {
        $this->db = $db;
        $this->loadCredentials();
    }
    
    /**
     * Load Vend credentials from config table
     */
    private function loadCredentials() {
        // Get Vend token from config table
        $tokenQuery = "SELECT setting_value FROM config WHERE setting_key = 'vend_access_token' LIMIT 1";
        $tokenResult = $this->db->query($tokenQuery);
        
        if ($tokenResult && $tokenResult->num_rows > 0) {
            $row = $tokenResult->fetch_assoc();
            $this->vendToken = $row['setting_value'];
        } else {
            throw new Exception("Vend access token not found in config table");
        }
        
        // Get Vend domain from config table
        $domainQuery = "SELECT setting_value FROM config WHERE setting_key = 'vend_domain' LIMIT 1";
        $domainResult = $this->db->query($domainQuery);
        
        if ($domainResult && $domainResult->num_rows > 0) {
            $row = $domainResult->fetch_assoc();
            $this->vendDomain = $row['setting_value'];
        } else {
            throw new Exception("Vend domain not found in config table");
        }
        
        // Construct base URL
        $this->baseUrl = "https://{$this->vendDomain}/api/2.0";
    }
    
    /**
     * Make API request to Vend
     * 
     * @param string $endpoint API endpoint (e.g., '/customers/123')
     * @param string $method HTTP method (GET, POST, PUT, DELETE)
     * @param array $data Data to send (for POST/PUT)
     * @return array API response
     */
    private function makeRequest($endpoint, $method = 'GET', $data = null) {
        $url = $this->baseUrl . $endpoint;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer {$this->vendToken}",
            'Accept: application/json',
            'Content-Type: application/json'
        ]);
        
        // Set method
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        
        // Add data for POST/PUT
        if ($data && in_array($method, ['POST', 'PUT'])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new Exception("CURL Error: {$error}");
        }
        
        if ($httpCode >= 400) {
            throw new Exception("API Error: HTTP {$httpCode} - {$response}");
        }
        
        return json_decode($response, true);
    }
    
    /**
     * Get customer details including credit limit
     * 
     * @param string $customerId Vend customer ID
     * @return array Customer data with credit limit info
     */
    public function getCustomerCreditLimit($customerId) {
        try {
            $response = $this->makeRequest("/customers/{$customerId}");
            
            if (!isset($response['data'])) {
                return [
                    'success' => false,
                    'error' => 'Customer not found'
                ];
            }
            
            $customer = $response['data'];
            
            // Extract credit-related fields
            $creditInfo = [
                'success' => true,
                'customer_id' => $customer['id'] ?? null,
                'customer_code' => $customer['customer_code'] ?? null,
                'name' => trim(($customer['first_name'] ?? '') . ' ' . ($customer['last_name'] ?? '')),
                'email' => $customer['email'] ?? null,
                
                // Credit fields (may not all be present)
                'credit_limit' => isset($customer['credit_limit']) ? floatval($customer['credit_limit']) : null,
                'account_balance' => isset($customer['balance']) ? floatval($customer['balance']) : 
                                   (isset($customer['account_balance']) ? floatval($customer['account_balance']) : null),
                'available_credit' => null,
                'credit_terms' => $customer['credit_terms'] ?? null,
                'store_credit' => isset($customer['store_credit']) ? floatval($customer['store_credit']) : null,
                
                // Custom fields (in case credit limit is stored there)
                'custom_fields' => $customer['custom_fields'] ?? [],
                
                // Customer group (may have group-level limits)
                'customer_group' => $customer['customer_group'] ?? null,
            ];
            
            // Calculate available credit if we have both limit and balance
            if ($creditInfo['credit_limit'] !== null && $creditInfo['account_balance'] !== null) {
                $creditInfo['available_credit'] = $creditInfo['credit_limit'] - $creditInfo['account_balance'];
            }
            
            // Check custom fields for credit limit
            if (isset($customer['custom_fields']['credit_limit'])) {
                $creditInfo['credit_limit_custom'] = floatval($customer['custom_fields']['credit_limit']);
            }
            
            // Check customer group for credit limit
            if (isset($customer['customer_group']['credit_limit'])) {
                $creditInfo['credit_limit_group'] = floatval($customer['customer_group']['credit_limit']);
            }
            
            return $creditInfo;
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get customer by customer code (staff code)
     * 
     * @param string $customerCode Customer code
     * @return array Customer data
     */
    public function getCustomerByCode($customerCode) {
        try {
            $response = $this->makeRequest("/customers?customer_code=" . urlencode($customerCode));
            
            if (!isset($response['data']) || empty($response['data'])) {
                return [
                    'success' => false,
                    'error' => 'Customer not found'
                ];
            }
            
            // Return first match
            $customer = $response['data'][0];
            return $this->getCustomerCreditLimit($customer['id']);
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get customer sales history
     * 
     * @param string $customerId Vend customer ID
     * @param int $limit Number of sales to retrieve
     * @return array Sales data
     */
    public function getCustomerSales($customerId, $limit = 50) {
        try {
            $response = $this->makeRequest("/sales?customer_id={$customerId}&page_size={$limit}");
            
            if (!isset($response['data'])) {
                return [
                    'success' => false,
                    'error' => 'No sales found'
                ];
            }
            
            return [
                'success' => true,
                'sales' => $response['data'],
                'count' => count($response['data'])
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Update customer credit limit
     * 
     * @param string $customerId Vend customer ID
     * @param float $newLimit New credit limit
     * @return array Result
     */
    public function updateCustomerCreditLimit($customerId, $newLimit) {
        try {
            $data = [
                'credit_limit' => floatval($newLimit)
            ];
            
            $response = $this->makeRequest("/customers/{$customerId}", 'PUT', $data);
            
            return [
                'success' => true,
                'message' => 'Credit limit updated successfully',
                'new_limit' => $newLimit
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get all customers with pagination
     * 
     * @param int $page Page number
     * @param int $pageSize Number of customers per page
     * @return array Customers data
     */
    public function getCustomers($page = 1, $pageSize = 50) {
        try {
            $response = $this->makeRequest("/customers?page={$page}&page_size={$pageSize}");
            
            return [
                'success' => true,
                'customers' => $response['data'] ?? [],
                'pagination' => $response['version'] ?? []
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Search customers by name or email
     * 
     * @param string $searchTerm Search term
     * @return array Matching customers
     */
    public function searchCustomers($searchTerm) {
        try {
            // URL encode the search term
            $encoded = urlencode($searchTerm);
            
            // Try searching by email first
            $response = $this->makeRequest("/customers?email={$encoded}");
            
            if (!empty($response['data'])) {
                return [
                    'success' => true,
                    'customers' => $response['data']
                ];
            }
            
            // If no results by email, try by name (requires different endpoint)
            // Note: Vend API may not support name search directly
            return [
                'success' => true,
                'customers' => [],
                'message' => 'No customers found matching search term'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get product details
     * 
     * @param string $productId Vend product ID
     * @return array Product data
     */
    public function getProduct($productId) {
        try {
            $response = $this->makeRequest("/products/{$productId}");
            
            return [
                'success' => true,
                'product' => $response['data'] ?? []
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Test API connection
     * 
     * @return array Connection status
     */
    public function testConnection() {
        try {
            // Try a simple API call to verify credentials
            $response = $this->makeRequest("/outlets?page_size=1");
            
            return [
                'success' => true,
                'message' => 'Successfully connected to Lightspeed API',
                'domain' => $this->vendDomain
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get staff member credit info by user ID
     * 
     * @param int $userId CIS user ID
     * @return array Credit information
     */
    public function getStaffCreditInfo($userId) {
        // Get user's vend_customer_account from users table
        $query = "SELECT vend_customer_account, vend_id, first_name, last_name, email 
                  FROM users 
                  WHERE id = ? 
                  LIMIT 1";
        
        $stmt = $this->db->prepare($query);
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            return [
                'success' => false,
                'error' => 'User not found'
            ];
        }
        
        $user = $result->fetch_assoc();
        
        if (empty($user['vend_customer_account'])) {
            return [
                'success' => false,
                'error' => 'User not linked to Vend customer account'
            ];
        }
        
        // Get credit info from Vend
        return $this->getCustomerCreditLimit($user['vend_customer_account']);
    }
}

/**
 * Example Usage:
 * 
 * // Initialize
 * require_once __DIR__ . '/../../app.php';
 * $lightspeed = new LightspeedAPI($db);
 * 
 * // Test connection
 * $test = $lightspeed->testConnection();
 * 
 * // Get customer credit limit
 * $creditInfo = $lightspeed->getCustomerCreditLimit('abc123-customer-id');
 * if ($creditInfo['success']) {
 *     echo "Credit Limit: $" . $creditInfo['credit_limit'];
 *     echo "Available Credit: $" . $creditInfo['available_credit'];
 * }
 * 
 * // Get by customer code
 * $creditInfo = $lightspeed->getCustomerByCode('STAFF001');
 * 
 * // Get staff member credit info from CIS user ID
 * $creditInfo = $lightspeed->getStaffCreditInfo(42);
 * 
 * // Update credit limit
 * $result = $lightspeed->updateCustomerCreditLimit('abc123', 750.00);
 * 
 * // Get sales history
 * $sales = $lightspeed->getCustomerSales('abc123', 50);
 */
