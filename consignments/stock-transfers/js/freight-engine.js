/**
 * Freight Engine Integration - Stock Transfer Packing
 *
 * Integrates with /assets/services/core/freight/FreightEngine.php
 * - Weight/volume calculation with P→C→D hierarchy
 * - NZ Courier / NZ Post rate calculation
 * - AI-powered carrier selection
 * - Real-time freight optimization
 * - Outlet-based routing
 *
 * @version 1.0.0
 * @date 2025-11-09
 */

class FreightEngineIntegration {
    constructor(transferId, outletFrom, outletTo) {
        this.transferId = transferId;
        this.outletFrom = outletFrom;
        this.outletTo = outletTo;
        this.items = [];
        this.freightData = null;
        this.selectedCarrier = null;

        // API endpoints
        this.freightAPI = '/assets/services/core/freight/api.php';
        this.transferAPI = '/modules/consignments/TransferManager/backend.php';

        // Initialize
        this.init();
    }

    async init() {
        await this.loadOutletDetails();
        this.setupEventListeners();
    }

    /**
     * Load outlet addresses and freight preferences
     */
    async loadOutletDetails() {
        try {
            const response = await fetch(`${this.transferAPI}?action=get_outlet_freight_details`, {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    outlet_from: this.outletFrom,
                    outlet_to: this.outletTo
                })
            });

            const data = await response.json();
            if (data.success) {
                this.outletFromData = data.data.from;
                this.outletToData = data.data.to;
                this.renderOutletInfo();
            }
        } catch (error) {
            console.error('Failed to load outlet details:', error);
        }
    }

    /**
     * Calculate freight for packed items
     */
    async calculateFreight(items) {
        this.items = items;

        // Show loading state
        this.showLoadingState();

        try {
            // Step 1: Calculate weights using FreightEngine P→C→D hierarchy
            const weightData = await this.resolveWeights(items);

            // Step 2: Calculate volume and parcel optimization
            const parcelData = await this.optimizeParcels(items, weightData);

            // Step 3: Get rates from carriers (NZ Courier, NZ Post)
            const rates = await this.getCarrierRates(parcelData);

            // Step 4: AI recommendation
            const recommendation = await this.getAIRecommendation(rates, parcelData);

            // Store result
            this.freightData = {
                weights: weightData,
                parcels: parcelData,
                rates: rates,
                recommendation: recommendation
            };

            // Render freight console
            this.renderFreightConsole();

        } catch (error) {
            console.error('Freight calculation failed:', error);
            this.showErrorState(error.message);
        }
    }

    /**
     * Resolve weights using P→C→D hierarchy
     * P = Product weight, C = Category weight, D = Default 100g
     */
    async resolveWeights(items) {
        const productIds = items.map(item => item.product_id);

        const response = await fetch(this.freightAPI, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                action: 'resolve_weights',
                product_ids: productIds
            })
        });

        const data = await response.json();
        if (!data.success) throw new Error(data.message);

        return data.data;
    }

    /**
     * Optimize parcel packing (satchel-first algorithm)
     */
    async optimizeParcels(items, weightData) {
        // Calculate total weight and volume
        const itemsWithWeights = items.map(item => ({
            ...item,
            weight_g: weightData.weights[item.product_id]?.resolved_weight_g || 100,
            weight_source: weightData.weights[item.product_id]?.legend_code || 'D',
            quantity: item.qty_packed || 0
        }));

        const response = await fetch(this.freightAPI, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                action: 'optimize_parcels',
                items: itemsWithWeights,
                strategy: 'satchel_first', // Cheapest packaging first
                outlet_from: this.outletFrom,
                outlet_to: this.outletTo
            })
        });

        const data = await response.json();
        if (!data.success) throw new Error(data.message);

        return data.data;
    }

    /**
     * Get rates from NZ Courier and NZ Post
     */
    async getCarrierRates(parcelData) {
        const response = await fetch(this.freightAPI, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                action: 'get_carrier_rates',
                parcels: parcelData.parcels,
                from_address: this.outletFromData.address,
                to_address: this.outletToData.address,
                carriers: ['nz_courier', 'nz_post'] // Both carriers
            })
        });

        const data = await response.json();
        if (!data.success) throw new Error(data.message);

        return data.data;
    }

    /**
     * Get AI-powered carrier recommendation
     */
    async getAIRecommendation(rates, parcelData) {
        const response = await fetch(this.freightAPI, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                action: 'ai_recommend_carrier',
                rates: rates,
                parcels: parcelData,
                outlet_from: this.outletFrom,
                outlet_to: this.outletTo,
                transfer_id: this.transferId
            })
        });

        const data = await response.json();
        if (!data.success) {
            // Fallback to cheapest if AI fails
            return this.cheapestCarrier(rates);
        }

        return data.data;
    }

    /**
     * Fallback: Select cheapest carrier
     */
    cheapestCarrier(rates) {
        let cheapest = null;
        let minCost = Infinity;

        for (const carrier in rates) {
            if (rates[carrier].total_cost < minCost) {
                minCost = rates[carrier].total_cost;
                cheapest = carrier;
            }
        }

        return {
            carrier: cheapest,
            reason: 'Lowest cost',
            confidence: 100
        };
    }

    /**
     * Render freight console UI
     */
    renderFreightConsole() {
        const container = document.getElementById('freight-console-body');
        if (!container) return;

        const { parcels, rates, recommendation, weights } = this.freightData;

        // Weight summary with P→C→D legend
        const weightSummary = `
            <div class="weight-summary">
                <div class="metric-row">
                    <span class="metric-label">Total Weight</span>
                    <span class="metric-value">${(parcels.total_weight_kg).toFixed(2)} kg</span>
                </div>
                <div class="metric-row">
                    <span class="metric-label">Weight Sources</span>
                    <span class="metric-value">${weights.legend}</span>
                </div>
                <div class="weight-legend">
                    <small><strong>P</strong>=Product • <strong>C</strong>=Category • <strong>D</strong>=Default (100g)</small>
                </div>
            </div>
        `;

        // Parcel breakdown
        const parcelsList = parcels.parcels.map((parcel, idx) => `
            <div class="parcel-item">
                <strong>📦 Parcel ${idx + 1}</strong>
                <span>${parcel.type}: ${parcel.weight_kg.toFixed(2)}kg • ${parcel.items.length} items</span>
            </div>
        `).join('');

        // Carrier options with logos
        const carrierOptions = Object.entries(rates).map(([carrier, rate]) => {
            const isRecommended = carrier === recommendation.carrier;
            const logo = carrier === 'nz_courier'
                ? '<img src="/assets/images/carriers/nz-courier-logo.png" alt="NZ Courier" class="carrier-logo">'
                : '<img src="/assets/images/carriers/nz-post-logo.png" alt="NZ Post" class="carrier-logo">';

            return `
                <div class="carrier-option ${isRecommended ? 'recommended' : ''}"
                     data-carrier="${carrier}"
                     onclick="freightEngine.selectCarrier('${carrier}')">
                    <div class="carrier-header">
                        ${logo}
                        ${isRecommended ? '<span class="badge-ai">🤖 AI Recommended</span>' : ''}
                    </div>
                    <div class="carrier-details">
                        <div class="carrier-name">${rate.service_name}</div>
                        <div class="carrier-price">$${rate.total_cost.toFixed(2)} <small>+GST</small></div>
                        <div class="carrier-eta">${rate.eta_days} days</div>
                    </div>
                    ${isRecommended ? `<div class="ai-reason"><small>${recommendation.reason}</small></div>` : ''}
                </div>
            `;
        }).join('');

        // Render everything
        container.innerHTML = `
            ${weightSummary}

            <div class="section-divider"></div>

            <h4 class="console-subtitle">📦 Parcels (${parcels.parcels.length})</h4>
            ${parcelsList}

            <div class="section-divider"></div>

            <h4 class="console-subtitle">🚚 Carrier Options</h4>
            <div class="carrier-options">
                ${carrierOptions}
            </div>

            <button class="btn btn-primary btn-block mt-3" onclick="freightEngine.bookFreight()">
                📦 Book Freight & Generate Labels
            </button>
        `;
    }

    /**
     * Select carrier
     */
    selectCarrier(carrier) {
        this.selectedCarrier = carrier;

        // Update UI
        document.querySelectorAll('.carrier-option').forEach(el => {
            el.classList.remove('selected');
        });
        document.querySelector(`[data-carrier="${carrier}"]`).classList.add('selected');
    }

    /**
     * Book freight and generate labels
     */
    async bookFreight() {
        if (!this.selectedCarrier) {
            alert('Please select a carrier first');
            return;
        }

        const btn = event.target;
        btn.disabled = true;
        btn.textContent = '⏳ Booking freight...';

        try {
            const response = await fetch(this.freightAPI, {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    action: 'book_freight',
                    transfer_id: this.transferId,
                    carrier: this.selectedCarrier,
                    parcels: this.freightData.parcels.parcels,
                    rate: this.freightData.rates[this.selectedCarrier]
                })
            });

            const data = await response.json();

            if (data.success) {
                // Store tracking numbers
                this.trackingNumbers = data.data.tracking_numbers;

                // Update transfer with tracking
                await this.updateTransferTracking(data.data);

                // Show success
                this.showBookingSuccess(data.data);
            } else {
                throw new Error(data.message);
            }

        } catch (error) {
            alert('Booking failed: ' + error.message);
        } finally {
            btn.disabled = false;
            btn.textContent = '📦 Book Freight & Generate Labels';
        }
    }

    /**
     * Update transfer with tracking numbers
     */
    async updateTransferTracking(bookingData) {
        await fetch(this.transferAPI, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                action: 'update_freight_details',
                transfer_id: this.transferId,
                carrier: this.selectedCarrier,
                tracking_numbers: bookingData.tracking_numbers,
                booking_reference: bookingData.booking_id,
                freight_cost: bookingData.total_cost
            })
        });
    }

    /**
     * Show booking success modal
     */
    showBookingSuccess(data) {
        const modal = `
            <div class="modal-overlay" onclick="this.remove()">
                <div class="modal-content" onclick="event.stopPropagation()">
                    <h2>✅ Freight Booked Successfully</h2>
                    <div class="booking-details">
                        <p><strong>Carrier:</strong> ${this.selectedCarrier.replace('_', ' ').toUpperCase()}</p>
                        <p><strong>Booking ID:</strong> ${data.booking_id}</p>
                        <p><strong>Total Cost:</strong> $${data.total_cost.toFixed(2)} + GST</p>
                        <h3>Tracking Numbers:</h3>
                        <ul>
                            ${data.tracking_numbers.map(tn => `<li><code>${tn}</code></li>`).join('')}
                        </ul>
                    </div>
                    <div class="modal-actions">
                        <button class="btn btn-primary" onclick="window.open('${data.label_url}', '_blank')">
                            🖨️ Print Labels
                        </button>
                        <button class="btn btn-success" onclick="freightEngine.completeTransfer()">
                            ✅ Mark as Sent
                        </button>
                    </div>
                </div>
            </div>
        `;

        document.body.insertAdjacentHTML('beforeend', modal);
    }

    /**
     * Complete transfer (mark as SENT)
     */
    async completeTransfer() {
        const response = await fetch(this.transferAPI, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                action: 'update_transfer_status',
                transfer_id: this.transferId,
                status: 'SENT',
                csrf: document.querySelector('meta[name="csrf-token"]').content
            })
        });

        const data = await response.json();

        if (data.success) {
            window.location.href = '/modules/consignments/TransferManager/frontend.php?success=transfer_sent';
        }
    }

    /**
     * Render outlet info in UI
     */
    renderOutletInfo() {
        const fromContainer = document.getElementById('outlet-from-info');
        const toContainer = document.getElementById('outlet-to-info');

        if (fromContainer && this.outletFromData) {
            fromContainer.innerHTML = `
                <strong>${this.outletFromData.name}</strong><br>
                <small>${this.outletFromData.address}</small>
            `;
        }

        if (toContainer && this.outletToData) {
            toContainer.innerHTML = `
                <strong>${this.outletToData.name}</strong><br>
                <small>${this.outletToData.address}</small>
            `;
        }
    }

    /**
     * Show loading state
     */
    showLoadingState() {
        const container = document.getElementById('freight-console-body');
        if (container) {
            container.innerHTML = `
                <div class="loading-state">
                    <div class="spinner"></div>
                    <p>Calculating freight options...</p>
                    <small>Analyzing weight, volume, and carrier rates</small>
                </div>
            `;
        }
    }

    /**
     * Show error state
     */
    showErrorState(message) {
        const container = document.getElementById('freight-console-body');
        if (container) {
            container.innerHTML = `
                <div class="error-state">
                    <span class="error-icon">⚠️</span>
                    <p>Freight calculation failed</p>
                    <small>${message}</small>
                    <button class="btn btn-sm btn-secondary mt-2" onclick="freightEngine.calculateFreight(freightEngine.items)">
                        🔄 Retry
                    </button>
                </div>
            `;
        }
    }

    /**
     * Setup event listeners
     */
    setupEventListeners() {
        // Listen for quantity changes
        document.addEventListener('quantityChanged', (e) => {
            if (this.items.length > 0) {
                this.calculateFreight(this.items);
            }
        });
    }
}

// Global instance
let freightEngine = null;

// Initialize on page load
document.addEventListener('DOMContentLoaded', () => {
    const transferId = new URLSearchParams(window.location.search).get('transfer_id');
    const outletFrom = document.getElementById('outlet-from')?.dataset.outletId;
    const outletTo = document.getElementById('outlet-to')?.dataset.outletId;

    if (transferId && outletFrom && outletTo) {
        freightEngine = new FreightEngineIntegration(transferId, outletFrom, outletTo);
    }
});
