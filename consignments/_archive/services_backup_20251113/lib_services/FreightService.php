<?php
declare(strict_types=1);

/**
 * Freight Service for Purchase Orders
 *
 * Specialized freight operations for Purchase Order consignments.
 * Wraps the generic FreightIntegration.php and adds PO-specific logic:
 * - Calculates freight from PO line items (not stock transfers)
 * - Handles supplier-to-store shipments
 * - Manages freight quotes and carrier selection
 * - Creates labels for inbound deliveries
 * - Tracks supplier shipments
 * - Coordinates with GSS, NZ Post, StarShipIt APIs
 *
 * Database Integration:
 * - vend_consignments (transfer_category='PURCHASE_ORDER')
 * - vend_consignment_line_items (PO items for weight/volume calc)
 * - consignment_parcels (labels, tracking, weights)
 * - consignment_shipments (delivery mode, carrier info)
 * - consignment_carrier_orders (carrier-specific data)
 * - consignment_ai_insights (AI freight recommendations)
 * - consignment_unified_log (freight event audit trail)
 *
 * Carrier Integration:
 * - NZ Courier (via webhook + API)
 * - GSS / Go Sweet Spot (full client library)
 * - StarShipIt (webhook + API)
 * - NZ Post (via FreightLibrary)
 * - Autocomplete API for carrier selection
 *
 * @package CIS\Consignments\Services
 * @version 1.0.0
 * @author AI Development Assistant
 * @date 2025-10-31
 */

namespace CIS\Consignments\Services;

use CIS\Modules\Consignments\FreightIntegration;
use PDO;
use PDOException;
use RuntimeException;
use InvalidArgumentException;

class FreightService
{
    private PDO $pdo;
    private FreightIntegration $freight;

    /**
     * Supported carriers
     */
    private const CARRIERS = [
        'nzpost' => 'NZ Post',
        'gss' => 'Go Sweet Spot (NZ Courier)',
        'starshipit' => 'StarShipIt',
        'courierpost' => 'CourierPost',
        'fastway' => 'Fastway',
        'nzcourier' => 'NZ Courier Direct'
    ];

    /**
     * Weight calculation strategies
     */
    private const WEIGHT_STRATEGIES = [
        'actual' => 'Use actual product weights',
        'estimated' => 'Use category averages',
        'volumetric' => 'Calculate from dimensions'
    ];

    /**
     * Constructor
     *
     * @param PDO $pdo Database connection
     */
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->freight = new FreightIntegration($pdo);
    }

    /**
     * Calculate freight metrics for a Purchase Order
     *
     * Uses the PO's line items to calculate:
     * - Total weight (including packaging)
     * - Total volume
     * - Number of boxes needed
     * - Shipping dimensions
     *
     * @param int $poId Purchase order ID
     * @param string $strategy Weight calculation strategy
     * @return array {
     *   weight: float (kg),
     *   volume: float (m³),
     *   boxes: int,
     *   warnings: string[],
     *   line_items: array[]
     * }
     * @throws RuntimeException If PO not found or calculation fails
     */
    public function calculateMetrics(int $poId, string $strategy = 'actual'): array
    {
        // Verify this is a Purchase Order
        $po = $this->getPurchaseOrder($poId);
        if (!$po) {
            throw new RuntimeException("Purchase order #{$poId} not found");
        }

        // Get line items with product details
        $lineItems = $this->getLineItemsWithProducts($poId);
        if (empty($lineItems)) {
            return [
                'weight' => 0.0,
                'volume' => 0.0,
                'boxes' => 0,
                'warnings' => ['No line items found'],
                'line_items' => []
            ];
        }

        // Calculate weight for each line item
        $totalWeight = 0.0;
        $totalVolume = 0.0;
        $warnings = [];
        $itemDetails = [];

        foreach ($lineItems as $item) {
            $qty = (int)$item['quantity'];

            // Calculate weight based on strategy
            $itemWeight = $this->calculateItemWeight($item, $strategy);
            $itemVolume = $this->calculateItemVolume($item);

            $totalWeight += $itemWeight * $qty;
            $totalVolume += $itemVolume * $qty;

            $itemDetails[] = [
                'product_id' => $item['product_id'],
                'product_name' => $item['product_name'],
                'sku' => $item['sku'] ?? '',
                'quantity' => $qty,
                'unit_weight' => $itemWeight,
                'total_weight' => $itemWeight * $qty,
                'unit_volume' => $itemVolume,
                'total_volume' => $itemVolume * $qty
            ];

            // Flag warnings
            if ($itemWeight === 0.0) {
                $warnings[] = "Product '{$item['product_name']}' has no weight data";
            }
        }

        // Add packaging weight (estimate 5% of product weight + 0.5kg per box)
        $packagingWeight = ($totalWeight * 0.05);

        // Estimate number of boxes needed (assume 0.1m³ per standard box)
        $boxes = (int)ceil($totalVolume / 0.1) ?: 1;
        $packagingWeight += ($boxes * 0.5); // Add 500g per box

        $totalWeight += $packagingWeight;

        // Cache the results for rate quotes
        $this->cacheMetrics($poId, [
            'weight' => $totalWeight,
            'volume' => $totalVolume,
            'boxes' => $boxes,
            'packaging_weight' => $packagingWeight,
            'calculated_at' => date('Y-m-d H:i:s'),
            'strategy' => $strategy
        ]);

        // Log calculation in unified log
        $this->logFreightEvent($poId, 'metrics_calculated', [
            'weight_kg' => $totalWeight,
            'volume_m3' => $totalVolume,
            'boxes' => $boxes,
            'strategy' => $strategy,
            'item_count' => count($lineItems)
        ]);

        return [
            'weight' => round($totalWeight, 2),
            'volume' => round($totalVolume, 4),
            'boxes' => $boxes,
            'packaging_weight' => round($packagingWeight, 2),
            'warnings' => $warnings,
            'line_items' => $itemDetails,
            'strategy' => $strategy
        ];
    }

    /**
     * Get freight quotes from all available carriers
     *
     * @param int $poId Purchase order ID
     * @param bool $forceRecalculate Force fresh calculation (ignore cache)
     * @return array {
     *   rates: array[],
     *   cheapest: array,
     *   fastest: array,
     *   recommended: array,
     *   calculated_at: string
     * }
     * @throws RuntimeException If calculation fails
     */
    public function getQuotes(int $poId, bool $forceRecalculate = false): array
    {
        // Ensure metrics are calculated
        $metrics = $forceRecalculate
            ? $this->calculateMetrics($poId)
            : ($this->getCachedMetrics($poId) ?? $this->calculateMetrics($poId));

        // Get PO destination info
        $po = $this->getPurchaseOrder($poId);

        // Call freight API via FreightIntegration wrapper
        try {
            $rates = $this->freight->getTransferRates($poId);

            // Enhance with PO-specific data
            $rates['purchase_order_id'] = $poId;
            $rates['supplier'] = $po->supplier_name ?? 'Unknown';
            $rates['destination'] = $po->outlet_to_name ?? 'Unknown';
            $rates['metrics'] = $metrics;

            // Cache the rates (30 min TTL)
            $this->cacheRates($poId, $rates);

            // Log rate request
            $this->logFreightEvent($poId, 'rates_requested', [
                'carrier_count' => count($rates['rates'] ?? []),
                'cheapest_carrier' => $rates['cheapest']['carrier'] ?? null,
                'cheapest_price' => $rates['cheapest']['price'] ?? null
            ]);

            return $rates;

        } catch (\Exception $e) {
            throw new RuntimeException("Failed to get freight quotes: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Suggest optimal containers/boxes for shipping
     *
     * @param int $poId Purchase order ID
     * @param string $strategy 'min_cost' | 'min_boxes' | 'balanced'
     * @return array {
     *   containers: array[],
     *   total_boxes: int,
     *   total_cost: float,
     *   utilization_pct: float
     * }
     */
    public function suggestContainers(int $poId, string $strategy = 'min_cost'): array
    {
        // Ensure metrics calculated
        $metrics = $this->getCachedMetrics($poId) ?? $this->calculateMetrics($poId);

        try {
            $containers = $this->freight->suggestTransferContainers($poId, $strategy);

            // Add PO context
            $containers['purchase_order_id'] = $poId;
            $containers['strategy'] = $strategy;
            $containers['weight_kg'] = $metrics['weight'];
            $containers['volume_m3'] = $metrics['volume'];

            return $containers;

        } catch (\Exception $e) {
            throw new RuntimeException("Failed to suggest containers: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Create shipping label for a Purchase Order
     *
     * Generates label, tracking number, and updates PO state.
     *
     * @param int $poId Purchase order ID
     * @param string $carrier Carrier code (nzpost, gss, starshipit, etc)
     * @param string $service Service level (standard, express, overnight)
     * @param array $options Additional options
     *   - auto_print: bool (default false)
     *   - signature_required: bool (default false)
     *   - insurance_value: float (default 0)
     * @return array {
     *   tracking_number: string,
     *   label_id: string,
     *   label_url: string,
     *   barcode_url: string,
     *   carrier: string,
     *   cost: float,
     *   created_at: string
     * }
     * @throws RuntimeException If label creation fails
     */
    public function createLabel(
        int $poId,
        string $carrier,
        string $service,
        array $options = []
    ): array {
        // Validate carrier
        if (!isset(self::CARRIERS[$carrier])) {
            throw new InvalidArgumentException("Invalid carrier: {$carrier}");
        }

        // Get PO details
        $po = $this->getPurchaseOrder($poId);
        if (!$po) {
            throw new RuntimeException("Purchase order #{$poId} not found");
        }

        // Check if PO is in valid state for shipping
        if (!in_array($po->state, ['PACKAGED', 'SENT', 'RECEIVING'])) {
            throw new RuntimeException("Cannot create label for PO in state: {$po->state}");
        }

        $this->pdo->beginTransaction();

        try {
            // Create label via FreightIntegration
            $autoPrint = $options['auto_print'] ?? false;
            $label = $this->freight->createTransferLabel($poId, $carrier, $service, $autoPrint);

            // Store label in consignment_parcels
            $parcelId = $this->storeParcel($poId, $label, $carrier, $service, $options);

            // Update PO shipment info
            $this->updateShipmentInfo($poId, $carrier, $service, $label['tracking_number']);

            // Update PO state to SENT if not already
            if ($po->state !== 'SENT') {
                $this->updatePOState($poId, 'SENT', "Label created: {$label['tracking_number']}");
            }

            // Log label creation
            $this->logFreightEvent($poId, 'label_created', [
                'tracking_number' => $label['tracking_number'],
                'carrier' => $carrier,
                'service' => $service,
                'cost' => $label['cost'] ?? 0,
                'parcel_id' => $parcelId
            ]);

            $this->pdo->commit();

            return array_merge($label, [
                'purchase_order_id' => $poId,
                'parcel_id' => $parcelId,
                'carrier_name' => self::CARRIERS[$carrier]
            ]);

        } catch (\Exception $e) {
            $this->pdo->rollBack();
            throw new RuntimeException("Failed to create shipping label: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Track shipment status for a Purchase Order
     *
     * @param int $poId Purchase order ID
     * @return array {
     *   tracking_number: string,
     *   carrier: string,
     *   status: string,
     *   current_location: string,
     *   estimated_delivery: string,
     *   delivered: bool,
     *   events: array[]
     * }
     * @throws RuntimeException If tracking fails
     */
    public function trackShipment(int $poId): array
    {
        // Get tracking info from database
        $shipment = $this->getShipmentInfo($poId);
        if (!$shipment || !$shipment['tracking_number']) {
            throw new RuntimeException("No tracking information found for PO #{$poId}");
        }

        try {
            // Get real-time tracking from carrier
            $tracking = $this->freight->trackTransferShipment($poId);

            // Enhance with PO data
            $tracking['purchase_order_id'] = $poId;
            $tracking['carrier_name'] = self::CARRIERS[$shipment['carrier']] ?? $shipment['carrier'];
            $tracking['label_created_at'] = $shipment['label_created_at'] ?? null;

            // Update PO state if delivered
            if ($tracking['delivered'] ?? false) {
                $this->updatePOState($poId, 'RECEIVING', 'Shipment delivered, ready for receiving');
            }

            // Log tracking check
            $this->logFreightEvent($poId, 'tracking_checked', [
                'status' => $tracking['status'] ?? 'unknown',
                'delivered' => $tracking['delivered'] ?? false
            ]);

            return $tracking;

        } catch (\Exception $e) {
            throw new RuntimeException("Failed to track shipment: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Get AI-powered carrier recommendation
     *
     * Uses historical data and AI insights to recommend best carrier.
     *
     * @param int $poId Purchase order ID
     * @param string $priority 'cost' | 'speed' | 'reliability'
     * @return array {
     *   carrier: string,
     *   service: string,
     *   price: float,
     *   eta_days: int,
     *   confidence: float,
     *   reason: string
     * }
     */
    public function getRecommendation(int $poId, string $priority = 'cost'): array
    {
        // Get quotes first
        $quotes = $this->getQuotes($poId);

        // Get AI insights if available
        $aiInsight = $this->getAIInsight($poId, $priority);

        // If AI has a recommendation, use it
        if ($aiInsight && $aiInsight['confidence'] >= 0.7) {
            return $aiInsight;
        }

        // Fallback to simple rule-based recommendation
        return $this->getBasicRecommendation($quotes, $priority);
    }

    /**
     * Get all available carriers with their services
     *
     * @return array[] Array of carriers with service options
     */
    public function getAvailableCarriers(): array
    {
        return array_map(function($code, $name) {
            return [
                'code' => $code,
                'name' => $name,
                'services' => $this->getCarrierServices($code)
            ];
        }, array_keys(self::CARRIERS), array_values(self::CARRIERS));
    }

    /**
     * Validate shipping address
     *
     * @param array $address Address data
     * @return array {valid: bool, errors: string[], suggestions: array}
     */
    public function validateAddress(array $address): array
    {
        // This would call the autocomplete API
        // For now, basic validation
        $errors = [];

        if (empty($address['address'])) {
            $errors[] = 'Street address is required';
        }
        if (empty($address['suburb'])) {
            $errors[] = 'Suburb is required';
        }
        if (empty($address['postcode'])) {
            $errors[] = 'Postcode is required';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'suggestions' => [] // Would come from autocomplete API
        ];
    }

    // ========================================================================
    // PRIVATE HELPER METHODS
    // ========================================================================

    /**
     * Get Purchase Order by ID (with category check)
     */
    private function getPurchaseOrder(int $id): ?object
    {
        $stmt = $this->pdo->prepare("
            SELECT vc.*,
                   vo_from.name AS outlet_from_name,
                   vo_to.name AS outlet_to_name,
                   vs.name AS supplier_name
            FROM vend_consignments vc
            LEFT JOIN vend_outlets vo_from ON vc.outlet_from = vo_from.id
            LEFT JOIN vend_outlets vo_to ON vc.outlet_to = vo_to.id
            LEFT JOIN vend_suppliers vs ON vc.supplier_id = vs.id
            WHERE vc.id = :id
              AND vc.transfer_category = 'PURCHASE_ORDER'
              AND vc.deleted_at IS NULL
        ");
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_OBJ) ?: null;
    }

    /**
     * Get line items with product details
     */
    private function getLineItemsWithProducts(int $poId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                li.id,
                li.product_id,
                li.quantity,
                li.unit_cost,
                li.total_cost,
                p.name AS product_name,
                p.sku,
                p.supply_price,
                COALESCE(pd.weight, 0) AS product_weight,
                COALESCE(pd.length, 0) AS product_length,
                COALESCE(pd.width, 0) AS product_width,
                COALESCE(pd.height, 0) AS product_height
            FROM vend_consignment_line_items li
            LEFT JOIN vend_products p ON li.product_id = p.id
            LEFT JOIN product_dimensions pd ON p.id = pd.product_id
            WHERE li.transfer_id = :po_id
              AND li.deleted_at IS NULL
            ORDER BY li.created_at ASC
        ");
        $stmt->execute([':po_id' => $poId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Calculate weight for a single item based on strategy
     */
    private function calculateItemWeight(array $item, string $strategy): float
    {
        switch ($strategy) {
            case 'actual':
                // Use product_dimensions.weight if available
                return (float)($item['product_weight'] ?? 0.0);

            case 'estimated':
                // Use category average (would need category_averages table)
                // For now, fallback to actual
                return (float)($item['product_weight'] ?? 0.5); // Default 500g

            case 'volumetric':
                // Calculate from dimensions (weight = volume * density)
                $volume = $this->calculateItemVolume($item);
                $density = 250; // kg/m³ (average for packaged goods)
                return $volume * $density;

            default:
                return (float)($item['product_weight'] ?? 0.0);
        }
    }

    /**
     * Calculate volume for a single item
     */
    private function calculateItemVolume(array $item): float
    {
        $length = (float)($item['product_length'] ?? 0);
        $width = (float)($item['product_width'] ?? 0);
        $height = (float)($item['product_height'] ?? 0);

        if ($length === 0.0 || $width === 0.0 || $height === 0.0) {
            // Default to small package (10cm x 10cm x 5cm)
            return 0.0005; // 0.5 liters in m³
        }

        // Convert cm to m and calculate
        return ($length / 100) * ($width / 100) * ($height / 100);
    }

    /**
     * Cache freight metrics
     */
    private function cacheMetrics(int $poId, array $metrics): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO consignment_freight_cache (transfer_id, cache_type, cache_data, expires_at)
            VALUES (:po_id, 'metrics', :data, DATE_ADD(NOW(), INTERVAL 1 HOUR))
            ON DUPLICATE KEY UPDATE
                cache_data = VALUES(cache_data),
                expires_at = VALUES(expires_at),
                updated_at = NOW()
        ");
        $stmt->execute([
            ':po_id' => $poId,
            ':data' => json_encode($metrics)
        ]);
    }

    /**
     * Get cached metrics
     */
    private function getCachedMetrics(int $poId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT cache_data
            FROM consignment_freight_cache
            WHERE transfer_id = :po_id
              AND cache_type = 'metrics'
              AND expires_at > NOW()
        ");
        $stmt->execute([':po_id' => $poId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ? json_decode($result['cache_data'], true) : null;
    }

    /**
     * Cache freight rates
     */
    private function cacheRates(int $poId, array $rates): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO consignment_freight_cache (transfer_id, cache_type, cache_data, expires_at)
            VALUES (:po_id, 'rates', :data, DATE_ADD(NOW(), INTERVAL 30 MINUTE))
            ON DUPLICATE KEY UPDATE
                cache_data = VALUES(cache_data),
                expires_at = VALUES(expires_at),
                updated_at = NOW()
        ");
        $stmt->execute([
            ':po_id' => $poId,
            ':data' => json_encode($rates)
        ]);
    }

    /**
     * Store parcel record
     */
    private function storeParcel(
        int $poId,
        array $label,
        string $carrier,
        string $service,
        array $options
    ): int {
        $stmt = $this->pdo->prepare("
            INSERT INTO consignment_parcels (
                transfer_id,
                tracking_number,
                carrier,
                service_level,
                weight,
                label_url,
                barcode_url,
                signature_required,
                insurance_value,
                created_at
            ) VALUES (
                :po_id,
                :tracking,
                :carrier,
                :service,
                :weight,
                :label_url,
                :barcode_url,
                :signature,
                :insurance,
                NOW()
            )
        ");

        $metrics = $this->getCachedMetrics($poId);

        $stmt->execute([
            ':po_id' => $poId,
            ':tracking' => $label['tracking_number'],
            ':carrier' => $carrier,
            ':service' => $service,
            ':weight' => $metrics['weight'] ?? 0,
            ':label_url' => $label['label_url'] ?? null,
            ':barcode_url' => $label['barcode_url'] ?? null,
            ':signature' => $options['signature_required'] ?? false,
            ':insurance' => $options['insurance_value'] ?? 0
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Update shipment info
     */
    private function updateShipmentInfo(int $poId, string $carrier, string $service, string $tracking): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO consignment_shipments (
                transfer_id, carrier, service_level, tracking_number, status, created_at
            ) VALUES (
                :po_id, :carrier, :service, :tracking, 'in_transit', NOW()
            )
            ON DUPLICATE KEY UPDATE
                carrier = VALUES(carrier),
                service_level = VALUES(service_level),
                tracking_number = VALUES(tracking_number),
                status = 'in_transit',
                updated_at = NOW()
        ");
        $stmt->execute([
            ':po_id' => $poId,
            ':carrier' => $carrier,
            ':service' => $service,
            ':tracking' => $tracking
        ]);
    }

    /**
     * Get shipment info
     */
    private function getShipmentInfo(int $poId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM consignment_shipments
            WHERE transfer_id = :po_id
            ORDER BY created_at DESC
            LIMIT 1
        ");
        $stmt->execute([':po_id' => $poId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Update PO state
     */
    private function updatePOState(int $poId, string $state, string $reason): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE vend_consignments
            SET state = :state,
                updated_at = NOW()
            WHERE id = :po_id
        ");
        $stmt->execute([':po_id' => $poId, ':state' => $state]);

        // Log state change
        $this->logFreightEvent($poId, 'state_changed', [
            'new_state' => $state,
            'reason' => $reason
        ]);
    }

    /**
     * Log freight event to unified log
     */
    private function logFreightEvent(int $poId, string $action, array $details): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO consignment_unified_log (
                transfer_id, event_type, event_action, event_details, created_at
            ) VALUES (
                :po_id, 'freight', :action, :details, NOW()
            )
        ");
        $stmt->execute([
            ':po_id' => $poId,
            ':action' => $action,
            ':details' => json_encode($details)
        ]);
    }

    /**
     * Get AI insight for carrier recommendation
     */
    private function getAIInsight(int $poId, string $priority): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM consignment_ai_insights
            WHERE transfer_id = :po_id
              AND insight_type = 'carrier_recommendation'
              AND priority = :priority
              AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ORDER BY confidence DESC
            LIMIT 1
        ");
        $stmt->execute([':po_id' => $poId, ':priority' => $priority]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$result) {
            return null;
        }

        return [
            'carrier' => $result['recommended_carrier'],
            'service' => $result['recommended_service'],
            'price' => (float)$result['estimated_cost'],
            'eta_days' => (int)$result['estimated_days'],
            'confidence' => (float)$result['confidence'],
            'reason' => $result['reasoning']
        ];
    }

    /**
     * Get basic rule-based recommendation
     */
    private function getBasicRecommendation(array $quotes, string $priority): array
    {
        $rates = $quotes['rates'] ?? [];
        if (empty($rates)) {
            throw new RuntimeException("No rates available for recommendation");
        }

        switch ($priority) {
            case 'speed':
                return $quotes['fastest'] ?? $rates[0];

            case 'reliability':
                // Prefer NZ Post or established carriers
                foreach ($rates as $rate) {
                    if (in_array($rate['carrier'], ['nzpost', 'nzcourier'])) {
                        return array_merge($rate, [
                            'confidence' => 0.8,
                            'reason' => 'Reliable national carrier'
                        ]);
                    }
                }
                return array_merge($rates[0], ['confidence' => 0.6, 'reason' => 'Default selection']);

            case 'cost':
            default:
                return array_merge($quotes['cheapest'], [
                    'confidence' => 0.9,
                    'reason' => 'Lowest cost option'
                ]);
        }
    }

    /**
     * Get services for a carrier
     */
    private function getCarrierServices(string $carrier): array
    {
        // This would come from a database or carrier API
        // Hardcoded for now
        $services = [
            'nzpost' => ['standard', 'express', 'overnight'],
            'gss' => ['standard', 'express', 'overnight', 'same_day'],
            'starshipit' => ['standard', 'express'],
            'courierpost' => ['standard', 'express', 'overnight'],
            'nzcourier' => ['standard', 'express', 'overnight']
        ];

        return $services[$carrier] ?? ['standard'];
    }
}
