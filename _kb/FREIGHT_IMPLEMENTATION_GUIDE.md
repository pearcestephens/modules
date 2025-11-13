# 🔌 Freight Integration - Practical Implementation Guide

**Status:** ✅ READY TO IMPLEMENT
**Target:** `/modules/consignments/lib/FreightIntegrationBridge.php`
**Dependency:** `/assets/services/core/freight/api.php` (already complete)
**Time to Implement:** 2-3 hours

---

## Overview

This guide shows **exactly how** to integrate the freight API into the consignments module with working code examples.

---

## Part 1: Core Bridge Class

### File: `/modules/consignments/lib/FreightIntegrationBridge.php`

```php
<?php
/**
 * Freight Integration Bridge
 *
 * Connects the Consignments module to the Freight API.
 * Provides convenient methods for weight/volume/container/rate operations.
 *
 * Usage:
 *   $bridge = new FreightIntegrationBridge($pdo);
 *   $metrics = $bridge->getTransferMetrics($transfer_id);
 *   $label = $bridge->createLabel($transfer_id, 'nzpost', 'standard');
 *
 * @package CIS\Modules\Consignments
 * @version 1.0.0
 */

declare(strict_types=1);

namespace CIS\Modules\Consignments\Lib;

class FreightIntegrationBridge
{
    private const FREIGHT_API = '/assets/services/core/freight/api.php';
    private const CACHE_TTL_RATES = 1800;      // 30 minutes for rates
    private const CACHE_TTL_CONTAINERS = 3600; // 1 hour for containers

    public function __construct(private \PDO $pdo) {}

    // ============================================================================
    // PUBLIC API - HIGH-LEVEL METHODS (USE THESE)
    // ============================================================================

    /**
     * Get complete freight metrics for a transfer
     *
     * Returns: weight, volume, container options, carrier rates, recommendation
     *
     * @param int $transferId Transfer ID
     * @return array {
     *   'weight_kg': float,
     *   'volume_m3': float,
     *   'containers': array,
     *   'rates': array,
     *   'cheapest': array,
     *   'recommended': array,
     *   'total_cost': float,
     *   'ready_to_ship': bool
     * }
     */
    public function getTransferMetrics(int $transferId): array
    {
        $weight = $this->getWeight($transferId);
        $volume = $this->getVolume($transferId);
        $containers = $this->getContainers($transferId, 'min_cost');
        $rates = $this->getRates($transferId);

        return [
            'weight_kg' => $weight['total_weight_kg'] ?? 0,
            'volume_m3' => $volume['total_volume_m3'] ?? 0,
            'containers' => $containers['containers'] ?? [],
            'total_containers' => $containers['total_boxes'] ?? 0,
            'container_cost' => $containers['total_cost'] ?? 0,
            'rates' => $rates['rates'] ?? [],
            'cheapest' => $rates['cheapest'] ?? null,
            'recommended' => $rates['recommended'] ?? null,
            'total_cost' => ($containers['total_cost'] ?? 0) + ($rates['recommended']['price'] ?? 0),
            'ready_to_ship' => !empty($rates['recommended'])
        ];
    }

    /**
     * Create shipping label and get tracking number
     *
     * @param int $transferId Transfer ID
     * @param string $carrier Carrier code (nzpost, gss, etc)
     * @param string $service Service type (standard, express, etc)
     * @param array $senderInfo Optional sender address
     * @param array $recipientInfo Optional recipient address
     * @return array {
     *   'success': bool,
     *   'tracking_number': string,
     *   'label_url': string,
     *   'label_id': string,
     *   'message': string
     * }
     */
    public function createLabel(
        int $transferId,
        string $carrier,
        string $service,
        ?array $senderInfo = null,
        ?array $recipientInfo = null
    ): array {
        // Get transfer info
        $transfer = $this->getTransferInfo($transferId);
        if (!$transfer) {
            return [
                'success' => false,
                'tracking_number' => null,
                'label_url' => null,
                'label_id' => null,
                'message' => 'Transfer not found'
            ];
        }

        // Get outlet info for sender/recipient
        $sender = $senderInfo ?? $this->getOutletInfo($transfer['from_outlet_id']);
        $recipient = $recipientInfo ?? $this->getOutletInfo($transfer['to_outlet_id']);

        // Call freight API
        $response = $this->callFreightApi('create_courier_label', [
            'transfer_id' => $transferId,
            'carrier' => $carrier,
            'service' => $service,
            'sender' => $sender,
            'recipient' => $recipient
        ]);

        if (!$response['success']) {
            return [
                'success' => false,
                'tracking_number' => null,
                'label_url' => null,
                'label_id' => null,
                'message' => $response['error']['message'] ?? 'Failed to create label'
            ];
        }

        // Store label info in database
        $this->storeLabelInfo($transferId, $response['data']);

        return [
            'success' => true,
            'tracking_number' => $response['data']['tracking_number'],
            'label_url' => $response['data']['label_url'],
            'label_id' => $response['data']['label_id'],
            'message' => 'Label created successfully'
        ];
    }

    /**
     * Get tracking status for a shipment
     *
     * @param string $trackingNumber Tracking number
     * @return array {
     *   'status': string (in_transit|delivered|failed),
     *   'current_location': string,
     *   'estimated_delivery': string,
     *   'events': array [{date, location, description}, ...],
     *   'updated_at': string
     * }
     */
    public function getTracking(string $trackingNumber): array
    {
        $response = $this->callFreightApi('track_shipment', [
            'tracking_number' => $trackingNumber
        ]);

        if (!$response['success']) {
            return [
                'status' => 'unknown',
                'current_location' => 'Unable to track',
                'estimated_delivery' => null,
                'events' => [],
                'updated_at' => date('Y-m-d H:i:s'),
                'error' => $response['error']['message'] ?? 'Tracking unavailable'
            ];
        }

        return array_merge($response['data'], [
            'updated_at' => $response['timestamp']
        ]);
    }

    /**
     * Get preview of shipping label before committing
     *
     * @param int $transferId Transfer ID
     * @param string $format Format (a4, thermal4x6, thermal4x8)
     * @return array {
     *   'preview_url': string (PDF URL),
     *   'format': string,
     *   'dimensions': string,
     *   'includes': array
     * }
     */
    public function previewLabel(int $transferId, string $format = 'a4'): array
    {
        $response = $this->callFreightApi('preview_label', [
            'transfer_id' => $transferId,
            'format' => $format
        ]);

        return $response['success'] ? $response['data'] : [
            'preview_url' => null,
            'error' => $response['error']['message'] ?? 'Preview unavailable'
        ];
    }

    // ============================================================================
    // INTERNAL API - LOWER-LEVEL METHODS
    // ============================================================================

    /**
     * Get weight for transfer
     *
     * @param int $transferId Transfer ID
     * @return array {total_weight_kg, total_weight_g, items, warnings}
     */
    private function getWeight(int $transferId): array
    {
        // Build items list from transfer_items
        $items = [];
        $stmt = $this->pdo->prepare('
            SELECT ti.product_id, ti.quantity
            FROM transfer_items ti
            WHERE ti.transfer_id = ?
        ');
        $stmt->execute([$transferId]);

        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $items[] = [
                'product_id' => $row['product_id'],
                'quantity' => (int)$row['quantity']
            ];
        }

        if (empty($items)) {
            return [
                'total_weight_kg' => 0,
                'total_weight_g' => 0,
                'items' => [],
                'warnings' => ['No items in transfer']
            ];
        }

        // Call freight API
        $response = $this->callFreightApi('calculate_weight', [
            'items' => $items
        ]);

        return $response['success'] ? $response['data'] : [
            'total_weight_kg' => 0,
            'total_weight_g' => 0,
            'items' => [],
            'warnings' => [$response['error']['message'] ?? 'Weight calculation failed']
        ];
    }

    /**
     * Get volume for transfer
     *
     * @param int $transferId Transfer ID
     * @return array {total_volume_cm3, total_volume_m3, items, warnings}
     */
    private function getVolume(int $transferId): array
    {
        $response = $this->callFreightApi('calculate_volume', [
            'transfer_id' => $transferId
        ]);

        return $response['success'] ? $response['data'] : [
            'total_volume_cm3' => 0,
            'total_volume_m3' => 0,
            'items' => [],
            'warnings' => [$response['error']['message'] ?? 'Volume calculation failed']
        ];
    }

    /**
     * Get container suggestions
     *
     * @param int $transferId Transfer ID
     * @param string $strategy Strategy (min_cost, min_boxes, balanced)
     * @return array {containers, total_boxes, total_cost, utilization_pct}
     */
    private function getContainers(int $transferId, string $strategy = 'min_cost'): array
    {
        // Try cache first
        $cacheKey = "containers_{$transferId}_{$strategy}";
        $cached = apcu_fetch($cacheKey);
        if ($cached !== false) {
            return $cached;
        }

        $response = $this->callFreightApi('suggest_containers', [
            'transfer_id' => $transferId,
            'strategy' => $strategy
        ]);

        $result = $response['success'] ? $response['data'] : [
            'containers' => [],
            'total_boxes' => 0,
            'total_cost' => 0,
            'utilization_pct' => 0,
            'warnings' => [$response['error']['message'] ?? 'Container suggestion failed']
        ];

        // Cache result
        if ($response['success']) {
            apcu_store($cacheKey, $result, self::CACHE_TTL_CONTAINERS);
        }

        return $result;
    }

    /**
     * Get carrier rates
     *
     * @param int $transferId Transfer ID
     * @return array {rates, cheapest, fastest, recommended}
     */
    private function getRates(int $transferId): array
    {
        // Try cache first
        $cacheKey = "rates_{$transferId}";
        $cached = apcu_fetch($cacheKey);
        if ($cached !== false) {
            return $cached;
        }

        // Get transfer info for outlet IDs
        $transfer = $this->getTransferInfo($transferId);

        $response = $this->callFreightApi('get_rates', [
            'transfer_id' => $transferId,
            'from_outlet' => $transfer['from_outlet_id'] ?? null,
            'to_outlet' => $transfer['to_outlet_id'] ?? null
        ]);

        $result = $response['success'] ? $response['data'] : [
            'rates' => [],
            'cheapest' => null,
            'fastest' => null,
            'recommended' => null,
            'warnings' => [$response['error']['message'] ?? 'Rate query failed']
        ];

        // Cache result
        if ($response['success']) {
            apcu_store($cacheKey, $result, self::CACHE_TTL_RATES);
        }

        return $result;
    }

    // ============================================================================
    // HELPER METHODS
    // ============================================================================

    /**
     * Call freight API endpoint
     *
     * @param string $action Action name
     * @param array $params Parameters
     * @return array {success, data|error, request_id, timestamp}
     */
    private function callFreightApi(string $action, array $params): array
    {
        // Build query string
        $queryParams = array_merge($params, ['action' => $action]);

        // Convert nested arrays to JSON strings
        foreach ($queryParams as $key => $value) {
            if (is_array($value)) {
                $queryParams[$key] = json_encode($value);
            }
        }

        // Build full URL
        $url = self::FREIGHT_API . '?' . http_build_query($queryParams);

        try {
            // Make request
            $response = @file_get_contents($url);

            if ($response === false) {
                return [
                    'success' => false,
                    'error' => [
                        'code' => 'API_UNAVAILABLE',
                        'message' => 'Freight API is unavailable'
                    ]
                ];
            }

            // Parse response
            $result = json_decode($response, true);

            if (!is_array($result)) {
                return [
                    'success' => false,
                    'error' => [
                        'code' => 'INVALID_RESPONSE',
                        'message' => 'Freight API returned invalid JSON'
                    ]
                ];
            }

            return $result;

        } catch (\Throwable $e) {
            // Log error
            \CIS\Log\Logger::error('Freight API Error', [
                'action' => $action,
                'params' => $params,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => [
                    'code' => 'API_ERROR',
                    'message' => 'An error occurred calling the freight API'
                ]
            ];
        }
    }

    /**
     * Get transfer info from database
     *
     * @param int $transferId Transfer ID
     * @return array|null {id, from_outlet_id, to_outlet_id, status, ...}
     */
    private function getTransferInfo(int $transferId): ?array
    {
        $stmt = $this->pdo->prepare('
            SELECT * FROM transfers
            WHERE id = ?
        ');
        $stmt->execute([$transferId]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Get outlet info for address
     *
     * @param int $outletId Outlet ID
     * @return array {name, address, suburb, postcode, phone}
     */
    private function getOutletInfo(int $outletId): array
    {
        $stmt = $this->pdo->prepare('
            SELECT name, address, suburb, postcode, phone
            FROM vend_outlets
            WHERE id = ?
        ');
        $stmt->execute([$outletId]);
        $outlet = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $outlet ?? [
            'name' => 'The Vape Shed',
            'address' => 'Unknown',
            'suburb' => 'Unknown',
            'postcode' => '0000',
            'phone' => ''
        ];
    }

    /**
     * Store label info in database
     *
     * @param int $transferId Transfer ID
     * @param array $labelData {label_id, tracking_number, label_url, ...}
     * @return bool Success
     */
    private function storeLabelInfo(int $transferId, array $labelData): bool
    {
        try {
            $stmt = $this->pdo->prepare('
                INSERT INTO freight_labels
                (transfer_id, label_id, tracking_number, label_url, barcode_url, carrier, created_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE
                tracking_number = VALUES(tracking_number),
                label_url = VALUES(label_url),
                barcode_url = VALUES(barcode_url)
            ');

            return $stmt->execute([
                $transferId,
                $labelData['label_id'] ?? null,
                $labelData['tracking_number'] ?? null,
                $labelData['label_url'] ?? null,
                $labelData['barcode_url'] ?? null,
                $labelData['carrier'] ?? null
            ]);
        } catch (\Throwable $e) {
            \CIS\Log\Logger::error('Failed to store label info', [
                'transfer_id' => $transferId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
}
```

---

## Part 2: Usage Examples in Controllers

### File: `/modules/consignments/controllers/TransferController.php`

```php
<?php
// ... existing code ...

use CIS\Modules\Consignments\Lib\FreightIntegrationBridge;

class TransferController
{
    private FreightIntegrationBridge $freight;

    public function __construct(\PDO $pdo)
    {
        $this->freight = new FreightIntegrationBridge($pdo);
    }

    /**
     * GET /transfers/{id}/freight-metrics
     * Returns freight metrics for a transfer (AJAX endpoint)
     */
    public function getFreightMetrics(int $transferId): array
    {
        $metrics = $this->freight->getTransferMetrics($transferId);

        return [
            'success' => true,
            'data' => $metrics
        ];
    }

    /**
     * POST /transfers/{id}/create-label
     * Create shipping label and get tracking number
     */
    public function createLabel(int $transferId): array
    {
        $carrier = $_POST['carrier'] ?? 'nzpost';
        $service = $_POST['service'] ?? 'standard';

        $result = $this->freight->createLabel($transferId, $carrier, $service);

        return [
            'success' => $result['success'],
            'data' => $result,
            'message' => $result['message']
        ];
    }

    /**
     * GET /transfers/{id}/label-preview
     * Preview label before creating (AJAX endpoint)
     */
    public function previewLabel(int $transferId): array
    {
        $format = $_GET['format'] ?? 'a4';
        $preview = $this->freight->previewLabel($transferId, $format);

        return [
            'success' => !isset($preview['error']),
            'data' => $preview
        ];
    }

    /**
     * GET /shipments/{trackingNumber}/tracking
     * Get tracking status (AJAX endpoint)
     */
    public function getTracking(string $trackingNumber): array
    {
        $tracking = $this->freight->getTracking($trackingNumber);

        return [
            'success' => isset($tracking['status']) && $tracking['status'] !== 'unknown',
            'data' => $tracking
        ];
    }
}
```

---

## Part 3: JavaScript Usage in pack.js

### File: `/modules/consignments/stock-transfers/js/pack-freight.js` (NEW FILE)

```javascript
/**
 * Freight Integration for Packing Interface
 *
 * Usage:
 *   const freight = new PackFreight();
 *   freight.loadMetrics(transferId);
 *   freight.showCarrierRates();
 *   freight.createLabel(carrier, service);
 */

class PackFreight {
    constructor() {
        this.transferId = parseInt(document.querySelector('[data-transfer-id]')?.dataset.transferId || 0);
        this.metrics = null;
        this.setupEventListeners();
    }

    /**
     * Load freight metrics from API
     */
    loadMetrics() {
        $.ajax({
            url: `/transfers/${this.transferId}/freight-metrics`,
            type: 'GET',
            dataType: 'json',
            success: (response) => {
                this.metrics = response.data;
                this.displayMetrics();
                this.enableCarrierSelection();
            },
            error: (err) => {
                console.error('Failed to load freight metrics:', err);
                this.showError('Unable to load freight information');
            }
        });
    }

    /**
     * Display freight metrics in UI
     */
    displayMetrics() {
        if (!this.metrics) return;

        // Update weight/volume display
        document.getElementById('freight-weight').textContent =
            this.metrics.weight_kg.toFixed(2) + ' kg';

        document.getElementById('freight-volume').textContent =
            this.metrics.volume_m3.toFixed(3) + ' m³';

        // Update container suggestion
        document.getElementById('freight-containers').textContent =
            this.metrics.total_containers + ' container(s)';

        document.getElementById('freight-container-cost').textContent =
            '$' + this.metrics.container_cost.toFixed(2);

        // Update rate recommendation
        if (this.metrics.recommended) {
            document.getElementById('freight-recommended-carrier').textContent =
                this.metrics.recommended.carrier.toUpperCase();

            document.getElementById('freight-recommended-cost').textContent =
                '$' + this.metrics.recommended.price.toFixed(2);
        }

        // Show "Ready to Ship" indicator
        if (this.metrics.ready_to_ship) {
            document.getElementById('freight-ready').classList.add('ready');
            document.getElementById('create-label-btn').disabled = false;
        }
    }

    /**
     * Show carrier rate comparison
     */
    showCarrierRates() {
        if (!this.metrics || !this.metrics.rates) return;

        const html = this.metrics.rates.map(rate => `
            <div class="rate-option" data-carrier="${rate.carrier}" data-service="${rate.service}">
                <div class="rate-carrier">${rate.carrier.toUpperCase()}</div>
                <div class="rate-service">${rate.service}</div>
                <div class="rate-price">$${rate.price.toFixed(2)}</div>
                <div class="rate-transit">${rate.transit_days} days</div>
                <button class="btn btn-sm btn-outline" onclick="freight.selectCarrier('${rate.carrier}', '${rate.service}')">
                    Select
                </button>
            </div>
        `).join('');

        document.getElementById('carrier-rates').innerHTML = html;
    }

    /**
     * Select carrier and service
     */
    selectCarrier(carrier, service) {
        document.getElementById('selected-carrier').value = carrier;
        document.getElementById('selected-service').value = service;

        // Highlight selected
        document.querySelectorAll('.rate-option').forEach(el => {
            el.classList.remove('selected');
        });
        document.querySelector(`.rate-option[data-carrier="${carrier}"][data-service="${service}"]`)
            ?.classList.add('selected');
    }

    /**
     * Create shipping label
     */
    createLabel(carrier, service) {
        // Show preview first
        this.previewLabel(carrier, service);

        // Wait for user confirmation
        if (!confirm('Create shipping label with these settings?')) {
            return;
        }

        $.ajax({
            url: `/transfers/${this.transferId}/create-label`,
            type: 'POST',
            data: {carrier, service},
            dataType: 'json',
            success: (response) => {
                if (response.success) {
                    this.showSuccess(`Label created! Tracking: ${response.data.tracking_number}`);
                    this.displayLabel(response.data);
                    document.getElementById('create-label-btn').disabled = true;
                } else {
                    this.showError(response.data.message);
                }
            },
            error: (err) => {
                console.error('Failed to create label:', err);
                this.showError('Failed to create label');
            }
        });
    }

    /**
     * Preview label before creating
     */
    previewLabel(carrier, service) {
        $.ajax({
            url: `/transfers/${this.transferId}/label-preview?format=a4`,
            type: 'GET',
            dataType: 'json',
            success: (response) => {
                if (response.preview_url) {
                    // Open preview in new tab
                    window.open(response.preview_url, '_blank');
                }
            },
            error: (err) => {
                console.error('Failed to preview label:', err);
            }
        });
    }

    /**
     * Display created label
     */
    displayLabel(labelData) {
        const html = `
            <div class="label-display">
                <h4>Shipping Label Created</h4>
                <div class="label-info">
                    <p><strong>Tracking:</strong> ${labelData.tracking_number}</p>
                    <p><strong>Label ID:</strong> ${labelData.label_id}</p>
                </div>
                <div class="label-actions">
                    <a href="${labelData.label_url}" class="btn btn-primary" target="_blank">
                        📥 Download PDF
                    </a>
                    <button class="btn btn-secondary" onclick="freight.trackShipment('${labelData.tracking_number}')">
                        📍 Track Shipment
                    </button>
                </div>
            </div>
        `;

        document.getElementById('label-display').innerHTML = html;
    }

    /**
     * Track shipment status
     */
    trackShipment(trackingNumber) {
        $.ajax({
            url: `/shipments/${trackingNumber}/tracking`,
            type: 'GET',
            dataType: 'json',
            success: (response) => {
                if (response.success) {
                    this.displayTracking(response.data);
                } else {
                    this.showError('Unable to retrieve tracking information');
                }
            },
            error: (err) => {
                console.error('Failed to get tracking:', err);
                this.showError('Tracking unavailable');
            }
        });
    }

    /**
     * Display tracking information
     */
    displayTracking(trackingData) {
        const eventsHtml = (trackingData.events || []).map(event => `
            <div class="tracking-event">
                <div class="event-date">${event.date}</div>
                <div class="event-location">${event.location}</div>
                <div class="event-description">${event.description}</div>
            </div>
        `).join('');

        const html = `
            <div class="tracking-display">
                <h4>Tracking Status: ${trackingData.status.toUpperCase()}</h4>
                <div class="current-location">
                    <strong>Current:</strong> ${trackingData.current_location}
                </div>
                <div class="estimated-delivery">
                    <strong>Estimated Delivery:</strong> ${trackingData.estimated_delivery || 'Unknown'}
                </div>
                <div class="tracking-events">
                    ${eventsHtml}
                </div>
            </div>
        `;

        document.getElementById('tracking-display').innerHTML = html;
    }

    /**
     * Setup event listeners
     */
    setupEventListeners() {
        document.getElementById('load-metrics-btn')?.addEventListener('click', () => this.loadMetrics());
        document.getElementById('show-rates-btn')?.addEventListener('click', () => this.showCarrierRates());
        document.getElementById('create-label-btn')?.addEventListener('click', () => {
            const carrier = document.getElementById('selected-carrier').value;
            const service = document.getElementById('selected-service').value;
            this.createLabel(carrier, service);
        });
    }

    /**
     * Show error message
     */
    showError(message) {
        const alertEl = document.createElement('div');
        alertEl.className = 'alert alert-danger';
        alertEl.textContent = message;
        document.body.prepend(alertEl);
        setTimeout(() => alertEl.remove(), 5000);
    }

    /**
     * Show success message
     */
    showSuccess(message) {
        const alertEl = document.createElement('div');
        alertEl.className = 'alert alert-success';
        alertEl.textContent = message;
        document.body.prepend(alertEl);
        setTimeout(() => alertEl.remove(), 5000);
    }
}

// Initialize on document ready
document.addEventListener('DOMContentLoaded', () => {
    window.freight = new PackFreight();
});
```

---

## Part 4: HTML Integration in pack-pro.php

### Add to `/modules/consignments/stock-transfers/pack-pro.php`

```html
<!-- FREIGHT CONSOLE SECTION -->
<div class="freight-console card mt-4">
    <div class="card-header bg-info text-white">
        <h5 class="m-0">📦 Freight & Shipping</h5>
    </div>

    <div class="card-body">
        <!-- Metrics Panel -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="metric-box">
                    <div class="metric-label">Total Weight</div>
                    <div class="metric-value" id="freight-weight">-</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="metric-box">
                    <div class="metric-label">Volume</div>
                    <div class="metric-value" id="freight-volume">-</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="metric-box">
                    <div class="metric-label">Containers</div>
                    <div class="metric-value" id="freight-containers">-</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="metric-box">
                    <div class="metric-label">Recommended</div>
                    <div class="metric-value" id="freight-recommended-carrier">-</div>
                </div>
            </div>
        </div>

        <!-- Button Group -->
        <div class="btn-group mb-4" role="group">
            <button id="load-metrics-btn" class="btn btn-primary">
                🔄 Load Metrics
            </button>
            <button id="show-rates-btn" class="btn btn-info">
                💰 Show Rates
            </button>
            <button id="create-label-btn" class="btn btn-success" disabled>
                📋 Create Label
            </button>
        </div>

        <!-- Carrier Rates -->
        <div id="carrier-rates" class="carrier-rates-panel mb-4"></div>

        <!-- Label Display -->
        <div id="label-display" class="label-panel mb-4"></div>

        <!-- Tracking Display -->
        <div id="tracking-display" class="tracking-panel mb-4"></div>

        <!-- Hidden Fields -->
        <input type="hidden" id="selected-carrier" value="nzpost">
        <input type="hidden" id="selected-service" value="standard">
        <div id="freight-ready" class="freight-ready-indicator"></div>
    </div>
</div>

<!-- FREIGHT CONSOLE CSS -->
<style>
.freight-console {
    background: #f8f9fa;
}

.metric-box {
    background: white;
    padding: 15px;
    border-radius: 5px;
    text-align: center;
    border-left: 4px solid #0066cc;
}

.metric-label {
    font-size: 12px;
    color: #666;
    font-weight: bold;
    text-transform: uppercase;
}

.metric-value {
    font-size: 24px;
    font-weight: bold;
    color: #333;
    margin-top: 5px;
}

.carrier-rates-panel {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
}

.rate-option {
    background: white;
    border: 2px solid #ddd;
    border-radius: 5px;
    padding: 15px;
    cursor: pointer;
    transition: all 0.3s;
}

.rate-option:hover,
.rate-option.selected {
    border-color: #0066cc;
    background: #f0f5ff;
}

.rate-carrier {
    font-weight: bold;
    font-size: 14px;
    color: #333;
}

.rate-service {
    font-size: 12px;
    color: #666;
}

.rate-price {
    font-size: 18px;
    font-weight: bold;
    color: #28a745;
    margin: 10px 0;
}

.rate-transit {
    font-size: 12px;
    color: #666;
}

.label-panel,
.tracking-panel {
    background: white;
    border: 1px solid #ddd;
    border-radius: 5px;
    padding: 15px;
}

.label-info,
.tracking-events {
    margin: 10px 0;
}

.tracking-event {
    background: #f8f9fa;
    padding: 10px;
    margin: 5px 0;
    border-left: 3px solid #0066cc;
}

.freight-ready-indicator {
    display: none;
}

.freight-ready-indicator.ready::before {
    content: '✓ Ready to Ship';
    color: #28a745;
    font-weight: bold;
}
</style>

<script src="/modules/consignments/stock-transfers/js/pack-freight.js"></script>
```

---

## Part 5: Implementation Checklist

### ✅ Step-by-Step Implementation

1. **Create Bridge Class** (10 min)
   - [ ] Copy `FreightIntegrationBridge.php` to `/modules/consignments/lib/`
   - [ ] Verify namespace matches: `CIS\Modules\Consignments\Lib`
   - [ ] Test basic API call: `$bridge->getTransferMetrics($transferId)`

2. **Create Database Tables** (5 min)
   - [ ] Create `freight_labels` table (see schema below)
   - [ ] Run migration: `ALTER TABLE transfers ADD COLUMN label_id VARCHAR(255);`

3. **Update Controller** (15 min)
   - [ ] Add freight endpoints to TransferController
   - [ ] Wire up `/transfers/{id}/freight-metrics` endpoint
   - [ ] Wire up `/transfers/{id}/create-label` endpoint
   - [ ] Wire up `/shipments/{trackingNumber}/tracking` endpoint

4. **Add JavaScript** (20 min)
   - [ ] Create `pack-freight.js` (copy from Part 3)
   - [ ] Add event listeners to buttons
   - [ ] Test AJAX calls

5. **Update HTML** (10 min)
   - [ ] Add freight console section to pack-pro.php
   - [ ] Add CSS styles
   - [ ] Link JavaScript file

6. **Test Endpoints** (30 min)
   - [ ] Test weight calculation
   - [ ] Test volume calculation
   - [ ] Test container suggestion
   - [ ] Test rate quoting
   - [ ] Test label creation
   - [ ] Test tracking

7. **Production Deployment** (30 min)
   - [ ] Deploy to staging first
   - [ ] Test with real transfers
   - [ ] Deploy to production
   - [ ] Monitor error logs

**Total Time: 2-3 hours**

---

## Required Database Schema

```sql
-- Shipping labels table
CREATE TABLE IF NOT EXISTS freight_labels (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transfer_id INT NOT NULL UNIQUE,
    label_id VARCHAR(255),
    tracking_number VARCHAR(255) UNIQUE,
    carrier VARCHAR(50),
    service VARCHAR(50),
    label_url TEXT,
    barcode_url TEXT,
    status ENUM('active', 'cancelled', 'delivered') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_tracking (tracking_number),
    INDEX idx_transfer (transfer_id),
    FOREIGN KEY (transfer_id) REFERENCES transfers(id)
);

-- Add column to transfers table
ALTER TABLE transfers ADD COLUMN label_id VARCHAR(255) AFTER status;
```

---

## Testing the Integration

### Unit Test Example

```php
public function test_freight_bridge_integration(): void
{
    $pdo = // ... get PDO connection
    $bridge = new FreightIntegrationBridge($pdo);

    // Test with a real transfer ID
    $transferId = 12345;

    // Get metrics
    $metrics = $bridge->getTransferMetrics($transferId);
    $this->assertIsArray($metrics);
    $this->assertGreater($metrics['weight_kg'], 0);

    // Preview label
    $preview = $bridge->previewLabel($transferId, 'a4');
    $this->assertArrayHasKey('preview_url', $preview);

    // Create label
    $label = $bridge->createLabel($transferId, 'nzpost', 'standard');
    $this->assertTrue($label['success']);
    $this->assertNotNull($label['tracking_number']);
}
```

---

## Summary

✅ **Complete freight integration ready to implement**

- ✅ Bridge class handles all API communication
- ✅ Controller endpoints ready to wire
- ✅ JavaScript UI ready to integrate
- ✅ HTML templates provided
- ✅ Database schema defined
- ✅ Testing examples provided

**Next Step:** Start with Part 1 (create bridge class), test with Part 5, then proceed to UI integration.
