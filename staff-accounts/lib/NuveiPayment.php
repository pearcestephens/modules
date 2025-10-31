<?php
/**
 * Nuvei Payment Gateway Integration
 * 
 * Handles credit card payments for staff account balances
 * Uses Nuvei (formerly SafeCharge) payment gateway
 * 
 * @package CIS\StaffAccounts
 * @version 1.0.0
 */

class NuveiPayment {
    
    private $db;
    private $merchantId;
    private $merchantSiteId;
    private $merchantSecretKey;
    private $environment; // 'sandbox' or 'production'
    private $apiUrl;
    
    /**
     * Constructor - loads credentials from config
     */
    public function __construct($db) {
        $this->db = $db;
        $this->loadCredentials();
        
        // Set API URL based on environment
        $this->apiUrl = ($this->environment === 'production')
            ? 'https://secure.nuvei.com/ppp/api/v1/'
            : 'https://ppp-test.nuvei.com/ppp/api/v1/';
    }
    
    /**
     * Load Nuvei credentials from config table
     */
    private function loadCredentials() {
        $config = [
            'nuvei_merchant_id' => null,
            'nuvei_merchant_site_id' => null,
            'nuvei_secret_key' => null,
            'nuvei_environment' => 'sandbox'
        ];
        
        foreach ($config as $key => $value) {
            $query = "SELECT setting_value FROM config WHERE setting_key = ? LIMIT 1";
            $stmt = $this->db->prepare($query);
            $stmt->bind_param('s', $key);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result && $result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $config[$key] = $row['setting_value'];
            }
        }
        
        $this->merchantId = $config['nuvei_merchant_id'];
        $this->merchantSiteId = $config['nuvei_merchant_site_id'];
        $this->merchantSecretKey = $config['nuvei_secret_key'];
        $this->environment = $config['nuvei_environment'];
        
        if (!$this->merchantId || !$this->merchantSiteId || !$this->merchantSecretKey) {
            throw new Exception("Nuvei credentials not configured in config table");
        }
    }
    
    /**
     * Generate checksum for API requests
     */
    private function generateChecksum($params) {
        $checksumString = '';
        foreach ($params as $value) {
            $checksumString .= $value;
        }
        return hash('sha256', $checksumString);
    }
    
    /**
     * Create payment session (step 1)
     * 
     * @param int $userId CIS user ID
     * @param float $amount Amount to charge
     * @param string $currency Currency code (NZD)
     * @return array Session token and response
     */
    public function createPaymentSession($userId, $amount, $currency = 'NZD') {
        try {
            // Get user details
            $userQuery = "SELECT first_name, last_name, email FROM users WHERE id = ? LIMIT 1";
            $stmt = $this->db->prepare($userQuery);
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();
            
            if (!$user) {
                throw new Exception("User not found");
            }
            
            $timestamp = date('YmdHis');
            $clientRequestId = uniqid('staff_payment_', true);
            
            // Calculate checksum
            $checksumParams = [
                $this->merchantId,
                $this->merchantSiteId,
                $clientRequestId,
                $amount,
                $currency,
                $timestamp,
                $this->merchantSecretKey
            ];
            $checksum = $this->generateChecksum($checksumParams);
            
            // API request data
            $requestData = [
                'merchantId' => $this->merchantId,
                'merchantSiteId' => $this->merchantSiteId,
                'clientRequestId' => $clientRequestId,
                'amount' => number_format($amount, 2, '.', ''),
                'currency' => $currency,
                'timeStamp' => $timestamp,
                'checksum' => $checksum,
                'billingAddress' => [
                    'email' => $user['email'],
                    'firstName' => $user['first_name'],
                    'lastName' => $user['last_name'],
                    'country' => 'NZ'
                ]
            ];
            
            // Make API call
            $response = $this->makeApiCall('getSessionToken', $requestData);
            
            if ($response['status'] === 'SUCCESS' && isset($response['sessionToken'])) {
                // Log session creation
                $this->logTransaction($userId, 'session_created', $amount, $clientRequestId, $response);
                
                return [
                    'success' => true,
                    'sessionToken' => $response['sessionToken'],
                    'clientRequestId' => $clientRequestId
                ];
            } else {
                throw new Exception($response['reason'] ?? 'Failed to create payment session');
            }
            
        } catch (Exception $e) {
            error_log("Nuvei Session Error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Process payment (step 2 - after user enters card details)
     * 
     * @param string $sessionToken Session token from step 1
     * @param array $cardData Card details
     * @param int $userId User ID
     * @param float $amount Amount
     * @return array Payment result
     */
    public function processPayment($sessionToken, $cardData, $userId, $amount) {
        try {
            $timestamp = date('YmdHis');
            $clientRequestId = uniqid('payment_', true);
            
            // Calculate checksum
            $checksumParams = [
                $this->merchantId,
                $this->merchantSiteId,
                $clientRequestId,
                $amount,
                'NZD',
                $timestamp,
                $this->merchantSecretKey
            ];
            $checksum = $this->generateChecksum($checksumParams);
            
            // Prepare payment request
            $requestData = [
                'sessionToken' => $sessionToken,
                'merchantId' => $this->merchantId,
                'merchantSiteId' => $this->merchantSiteId,
                'clientRequestId' => $clientRequestId,
                'amount' => number_format($amount, 2, '.', ''),
                'currency' => 'NZD',
                'timeStamp' => $timestamp,
                'checksum' => $checksum,
                'paymentOption' => [
                    'card' => [
                        'cardNumber' => $cardData['cardNumber'],
                        'cardHolderName' => $cardData['cardHolderName'],
                        'expirationMonth' => $cardData['expirationMonth'],
                        'expirationYear' => $cardData['expirationYear'],
                        'CVV' => $cardData['cvv']
                    ]
                ]
            ];
            
            // Make payment API call
            $response = $this->makeApiCall('payment', $requestData);
            
            if ($response['transactionStatus'] === 'APPROVED') {
                // Log successful payment
                $this->logTransaction($userId, 'payment_approved', $amount, $clientRequestId, $response);
                
                // Update staff account balance
                $this->updateStaffBalance($userId, $amount, $response['transactionId']);
                
                return [
                    'success' => true,
                    'transactionId' => $response['transactionId'],
                    'message' => 'Payment successful'
                ];
            } else {
                // Log failed payment
                $this->logTransaction($userId, 'payment_failed', $amount, $clientRequestId, $response);
                
                return [
                    'success' => false,
                    'error' => $response['gwErrorReason'] ?? 'Payment declined'
                ];
            }
            
        } catch (Exception $e) {
            error_log("Nuvei Payment Error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Make API call to Nuvei
     */
    private function makeApiCall($endpoint, $data) {
        $url = $this->apiUrl . $endpoint . '.do';
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json'
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            throw new Exception("API Error: HTTP {$httpCode}");
        }
        
        return json_decode($response, true);
    }
    
    /**
     * Log payment transaction
     */
    private function logTransaction($userId, $type, $amount, $requestId, $response) {
        $query = "INSERT INTO staff_payment_transactions 
                  (user_id, transaction_type, amount, request_id, response_data, created_at) 
                  VALUES (?, ?, ?, ?, ?, NOW())";
        
        $stmt = $this->db->prepare($query);
        $responseJson = json_encode($response);
        $stmt->bind_param('isdss', $userId, $type, $amount, $requestId, $responseJson);
        $stmt->execute();
    }
    
    /**
     * Update staff account balance after successful payment
     */
    private function updateStaffBalance($userId, $amount, $transactionId) {
        // Reduce staff account balance
        $query = "UPDATE staff_account_balance 
                  SET current_balance = current_balance - ?, 
                      last_payment_date = NOW(),
                      last_payment_amount = ?,
                      last_payment_transaction_id = ?
                  WHERE user_id = ?";
        
        $stmt = $this->db->prepare($query);
        $stmt->bind_param('ddsi', $amount, $amount, $transactionId, $userId);
        $stmt->execute();
        
        // Log payment in allocations table
        $query2 = "INSERT INTO staff_allocations 
                   (user_id, allocation_date, amount, payment_method, transaction_id, created_at) 
                   VALUES (?, CURDATE(), ?, 'credit_card', ?, NOW())";
        
        $stmt2 = $this->db->prepare($query2);
        $stmt2->bind_param('ids', $userId, $amount, $transactionId);
        $stmt2->execute();
    }
    
    /**
     * Save card for future payments (tokenization)
     */
    public function saveCard($userId, $cardToken) {
        $query = "INSERT INTO staff_saved_cards 
                  (user_id, card_token, last_four_digits, card_type, expiry_month, expiry_year, is_default, created_at) 
                  VALUES (?, ?, ?, ?, ?, ?, 1, NOW())";
        
        // Card details would come from Nuvei tokenization response
        $stmt = $this->db->prepare($query);
        $stmt->bind_param('isssii', 
            $userId, 
            $cardToken, 
            '4242', // Last 4 digits
            'Visa', // Card type
            12, // Expiry month
            2027 // Expiry year
        );
        $stmt->execute();
    }
    
    /**
     * Get payment history for user
     */
    public function getPaymentHistory($userId, $limit = 10) {
        $query = "SELECT 
                    transaction_type,
                    amount,
                    request_id,
                    response_data,
                    created_at
                  FROM staff_payment_transactions
                  WHERE user_id = ?
                  ORDER BY created_at DESC
                  LIMIT ?";
        
        $stmt = $this->db->prepare($query);
        $stmt->bind_param('ii', $userId, $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $history = [];
        while ($row = $result->fetch_assoc()) {
            $response = json_decode($row['response_data'], true);
            $history[] = [
                'type' => $row['transaction_type'],
                'amount' => $row['amount'],
                'status' => $response['transactionStatus'] ?? 'unknown',
                'date' => $row['created_at'],
                'transaction_id' => $response['transactionId'] ?? null
            ];
        }
        
        return $history;
    }
}

/**
 * Example Usage:
 * 
 * // Step 1: Create payment session
 * $nuvei = new NuveiPayment($db);
 * $session = $nuvei->createPaymentSession($userId, 125.00);
 * 
 * if ($session['success']) {
 *     // Return session token to frontend
 *     echo json_encode(['sessionToken' => $session['sessionToken']]);
 * }
 * 
 * // Step 2: Process payment (after user enters card)
 * $cardData = [
 *     'cardNumber' => '4111111111111111',
 *     'cardHolderName' => 'John Smith',
 *     'expirationMonth' => '12',
 *     'expirationYear' => '2027',
 *     'cvv' => '123'
 * ];
 * 
 * $result = $nuvei->processPayment($sessionToken, $cardData, $userId, 125.00);
 * 
 * if ($result['success']) {
 *     echo "Payment successful! Transaction ID: " . $result['transactionId'];
 * }
 */
