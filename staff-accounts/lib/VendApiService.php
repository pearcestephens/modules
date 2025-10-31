<?php
declare(strict_types=1);

namespace CIS\Modules\StaffAccounts;

use Exception;

/**
 * Vend API Service
 * 
 * Wrapper for Vend/Lightspeed API interactions
 * Uses existing VendAPI.php functions
 * 
 * @package CIS\Modules\StaffAccounts
 * @version 2.0.0
 */
class VendApiService
{
    /**
     * Test Vend API connection
     * 
     * @return array<string, mixed>
     */
    public static function testConnection(): array
    {
        try {
            if (!function_exists('getVendAccessToken')) {
                require_once $_SERVER['DOCUMENT_ROOT'] . '/assets/functions/VendAPI.php';
            }
            
            $token = getVendAccessToken();
            if (!$token) {
                return ['success' => false, 'error' => 'No Vend access token found in configuration'];
            }
            
            // Test API call to get outlets
            $response = vendApiRequest('https://vapeshed.retail.lightspeed.app/api/2.0/outlets');
            if ($response && isset($response['data'])) {
                return [
                    'success' => true, 
                    'message' => 'Vend API connection successful',
                    'outlets_count' => count($response['data']),
                    'token_length' => strlen($token)
                ];
            }
            
            return ['success' => false, 'error' => 'Invalid response from Vend API'];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => 'Vend API Error: ' . $e->getMessage()];
        }
    }
    
    /**
     * Get customer balance from Vend
     * 
     * @param string $customerId Vend customer ID
     * @return array<string, mixed>
     */
    public static function getCustomerBalance(string $customerId): array
    {
        try {
            if (!function_exists('vendApiRequest')) {
                require_once $_SERVER['DOCUMENT_ROOT'] . '/assets/functions/VendAPI.php';
            }
            
            $response = vendApiRequest("https://vapeshed.retail.lightspeed.app/api/2.0/customers/{$customerId}");
            if ($response && isset($response['data'])) {
                return [
                    'success' => true,
                    'balance' => $response['data']['balance'] ?? 0,
                    'customer_code' => $response['data']['customer_code'] ?? '',
                    'name' => ($response['data']['first_name'] ?? '') . ' ' . ($response['data']['last_name'] ?? '')
                ];
            }
            return ['success' => false, 'error' => 'Customer not found'];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
