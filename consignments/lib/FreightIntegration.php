<?php
/**
 * Consignments Module - Freight Integration
 * 
 * Wrapper around the generic freight service that handles consignment-specific logic:
 * - Translates transfer data → generic freight API format
 * - Updates transfer records with freight info
 * - Logs freight actions in consignment audit trail
 * - Handles UI callbacks (success/error messages)
 * 
 * @package CIS\Modules\Consignments
 * @version 1.0.0
 * @author Pearce Stephens
 * @date 2025-10-25
 */

declare(strict_types=1);

namespace CIS\Modules\Consignments;

class FreightIntegration
{
    private \PDO $pdo;
    private string $freight_api_url;
    
    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->freight_api_url = '/assets/services/core/freight/api.php';
    }
    
    /**
     * Calculate weight and volume for a transfer
     * 
     * @param int $transfer_id Transfer ID
     * @return array {weight, volume, warnings}
     */
    public function calculateTransferMetrics(int $transfer_id): array
    {
        // Call generic freight service
        $response = $this->callFreightAPI('calculate_all', [
            'transfer_id' => $transfer_id
        ]);
        
        if (!$response['success']) {
            throw new \Exception($response['error']['message'] ?? 'Failed to calculate metrics');
        }
        
        // Log action in consignment audit trail
        $this->logFreightAction($transfer_id, 'calculate_metrics', $response['data']);
        
        return $response['data'];
    }
    
    /**
     * Get freight quotes for a transfer
     * 
     * @param int $transfer_id Transfer ID
     * @return array {rates, cheapest, fastest, recommended}
     */
    public function getTransferRates(int $transfer_id): array
    {
        $response = $this->callFreightAPI('get_rates', [
            'transfer_id' => $transfer_id
        ]);
        
        if (!$response['success']) {
            throw new \Exception($response['error']['message'] ?? 'Failed to get rates');
        }
        
        $this->logFreightAction($transfer_id, 'get_rates', $response['data']);
        
        return $response['data'];
    }
    
    /**
     * Suggest containers for a transfer
     * 
     * @param int $transfer_id Transfer ID
     * @param string $strategy 'min_cost'|'min_boxes'|'balanced'
     * @return array {containers, total_boxes, total_cost, utilization_pct}
     */
    public function suggestTransferContainers(int $transfer_id, string $strategy = 'min_cost'): array
    {
        $response = $this->callFreightAPI('suggest_containers', [
            'transfer_id' => $transfer_id,
            'strategy' => $strategy
        ]);
        
        if (!$response['success']) {
            throw new \Exception($response['error']['message'] ?? 'Failed to suggest containers');
        }
        
        return $response['data'];
    }
    
    /**
     * Create shipping label for a transfer
     * 
     * @param int $transfer_id Transfer ID
     * @param string $carrier 'nzpost'|'gss'
     * @param string $service Service code
     * @param bool $auto_print Auto-print label
     * @return array {tracking_number, label_url, cost}
     */
    public function createTransferLabel(
        int $transfer_id, 
        string $carrier, 
        string $service, 
        bool $auto_print = false
    ): array {
        $response = $this->callFreightAPI('create_label', [
            'transfer_id' => $transfer_id,
            'carrier' => $carrier,
            'service' => $service,
            'auto_print' => $auto_print ? 1 : 0
        ]);
        
        if (!$response['success']) {
            throw new \Exception($response['error']['message'] ?? 'Failed to create label');
        }
        
        $label_data = $response['data'];
        
        // Update transfer record with tracking info
        $this->updateTransferWithLabel($transfer_id, $label_data);
        
        // Log action
        $this->logFreightAction($transfer_id, 'create_label', $label_data);
        
        // Update transfer status if needed
        $this->updateTransferStatus($transfer_id, 'shipped');
        
        return $label_data;
    }
    
    /**
     * Track shipment for a transfer
     * 
     * @param int $transfer_id Transfer ID
     * @return array {status, events, estimated_delivery, delivered}
     */
    public function trackTransferShipment(int $transfer_id): array
    {
        $response = $this->callFreightAPI('track_shipment', [
            'transfer_id' => $transfer_id
        ]);
        
        if (!$response['success']) {
            throw new \Exception($response['error']['message'] ?? 'Failed to track shipment');
        }
        
        return $response['data'];
    }
    
    /**
     * Get AI-powered freight recommendation for a transfer
     * 
     * @param int $transfer_id Transfer ID
     * @param string $priority 'cost'|'speed'|'balanced'
     * @return array {carrier, service, price, eta_days, confidence, reason}
     */
    public function getTransferRecommendation(int $transfer_id, string $priority = 'cost'): array
    {
        $response = $this->callFreightAPI('recommend_carrier', [
            'transfer_id' => $transfer_id,
            'priority' => $priority
        ]);
        
        if (!$response['success']) {
            throw new \Exception($response['error']['message'] ?? 'Failed to get recommendation');
        }
        
        return $response['data'];
    }
    
    // ========================================================================
    // PRIVATE HELPER METHODS
    // ========================================================================
    
    /**
     * Call freight service API
     * 
     * @param string $action API action
     * @param array $params Parameters
     * @return array API response
     */
    private function callFreightAPI(string $action, array $params = []): array
    {
        $params['action'] = $action;
        
        // Build query string
        $query = http_build_query($params);
        $url = $_SERVER['DOCUMENT_ROOT'] . $this->freight_api_url . '?' . $query;
        
        // For internal calls, use file_get_contents (faster than CURL for same server)
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => 'Content-Type: application/x-www-form-urlencoded',
                'content' => $query,
                'timeout' => 30
            ]
        ]);
        
        $response = @file_get_contents($url, false, $context);
        
        if ($response === false) {
            return [
                'success' => false,
                'error' => [
                    'code' => 'API_ERROR',
                    'message' => 'Failed to connect to freight service'
                ]
            ];
        }
        
        return json_decode($response, true) ?? [
            'success' => false,
            'error' => [
                'code' => 'PARSE_ERROR',
                'message' => 'Invalid JSON response from freight service'
            ]
        ];
    }
    
    /**
     * Update transfer record with label information
     * 
     * @param int $transfer_id Transfer ID
     * @param array $label_data Label data from freight service
     */
    private function updateTransferWithLabel(int $transfer_id, array $label_data): void
    {
        $tracking_number = $label_data['tracking_number'] ?? null;
        $carrier = $label_data['carrier'] ?? null;
        $freight_cost = $label_data['cost'] ?? null;
        
        if (!$tracking_number) {
            return;
        }
        
        $stmt = $this->pdo->prepare("
            UPDATE stock_transfers 
            SET 
                tracking_number = ?,
                carrier = ?,
                freight_cost = ?,
                label_created_at = NOW()
            WHERE id = ?
        ");
        
        $stmt->execute([
            $tracking_number,
            $carrier,
            $freight_cost,
            $transfer_id
        ]);
    }
    
    /**
     * Update transfer status
     * 
     * @param int $transfer_id Transfer ID
     * @param string $status New status
     */
    private function updateTransferStatus(int $transfer_id, string $status): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE stock_transfers 
            SET status = ?
            WHERE id = ?
        ");
        
        $stmt->execute([$status, $transfer_id]);
    }
    
    /**
     * Log freight action in audit trail
     * 
     * @param int $transfer_id Transfer ID
     * @param string $action Action type
     * @param array $data Action data
     */
    private function logFreightAction(int $transfer_id, string $action, array $data): void
    {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO transfer_freight_audit 
                (transfer_id, action, data, user_id, created_at)
                VALUES (?, ?, ?, ?, NOW())
            ");
            
            $user_id = $_SESSION['user_id'] ?? null;
            
            $stmt->execute([
                $transfer_id,
                $action,
                json_encode($data),
                $user_id
            ]);
        } catch (\Exception $e) {
            // Fail silently if audit table doesn't exist yet
            error_log("Freight audit log failed: " . $e->getMessage());
        }
    }
}
