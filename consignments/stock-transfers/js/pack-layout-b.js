/**
 * Pack Layout B - Tabs Interface JavaScript
 * Handles tab switching, product packing, and freight calculations
 *
 * @version 1.0.0
 * @date 2025-11-09
 */

// Tab switching
document.querySelectorAll('.tab-link').forEach(link => {
    link.addEventListener('click', function() {
        const tab = this.dataset.tab;

        // Update tab links
        document.querySelectorAll('.tab-link').forEach(l => l.classList.remove('active'));
        this.classList.add('active');

        // Update tab content
        document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
        document.getElementById(`tab-${tab}`).classList.add('active');

        // Trigger updates based on tab
        if (tab === 'freight') {
            renderFreightTab();
        } else if (tab === 'ai') {
            renderAITab();
        } else if (tab === 'summary') {
            renderSummaryTab();
        }
    });
});

// Product packing logic (same as Layout A)
function adjustQty(productId, delta) {
    const input = document.getElementById(`qty-${productId}`);
    const currentQty = parseInt(input.value) || 0;
    const maxQty = parseInt(input.max);
    const newQty = Math.max(0, Math.min(maxQty, currentQty + delta));

    input.value = newQty;
    updateQty(productId, newQty);
}

function updateQty(productId, qty) {
    qty = parseInt(qty) || 0;
    const card = document.querySelector(`.product-card[data-product-id="${productId}"]`);
    const maxQty = parseInt(document.getElementById(`qty-${productId}`).max);

    if (qty > maxQty) qty = maxQty;
    if (qty < 0) qty = 0;

    document.getElementById(`qty-${productId}`).value = qty;

    // Update card visual state
    if (qty === 0) {
        card.classList.remove('packed');
    } else {
        card.classList.add('packed');
    }

    // Update stats and recalculate
    updateStats();
    recalculateFreight();
}

function updateStats() {
    const cards = document.querySelectorAll('.product-card');
    let totalPacked = 0;
    let totalWeight = 0;

    cards.forEach(card => {
        const productId = card.dataset.productId;
        const qty = parseInt(document.getElementById(`qty-${productId}`).value) || 0;
        const weightPerUnit = parseFloat(card.dataset.weight) || 100;

        if (qty > 0) {
            totalPacked++;
            totalWeight += (qty * weightPerUnit / 1000);
        }
    });

    document.getElementById('packed-items').textContent = totalPacked;
    document.getElementById('total-weight').textContent = totalWeight.toFixed(2) + ' kg';
}

function recalculateFreight() {
    const cards = document.querySelectorAll('.product-card');
    const items = [];

    cards.forEach(card => {
        const productId = card.dataset.productId;
        const qty = parseInt(document.getElementById(`qty-${productId}`).value) || 0;

        if (qty > 0) {
            items.push({
                product_id: productId,
                sku: card.dataset.sku,
                qty_packed: qty,
                weight_g: parseFloat(card.dataset.weight),
                weight_source: card.dataset.weightSource
            });
        }
    });

    if (items.length > 0 && typeof freightEngine !== 'undefined') {
        freightEngine.calculateFreight(items);
    }
}

// Render Freight Tab
function renderFreightTab() {
    const container = document.getElementById('freight-section-body');

    if (!freightEngine || !freightEngine.freightData) {
        container.innerHTML = `
            <div class="loading-state">
                <p>No freight data yet</p>
                <small>Pack some items first to see freight options</small>
            </div>
        `;
        return;
    }

    const { parcels, rates, recommendation, weights } = freightEngine.freightData;

    const html = `
        <h2 class="section-title">⚖️ Weight Summary</h2>
        <div class="weight-summary-grid">
            <div class="weight-card">
                <div class="weight-card-label">Total Weight</div>
                <div class="weight-card-value">${parcels.total_weight_kg.toFixed(2)} kg</div>
            </div>
            <div class="weight-card">
                <div class="weight-card-label">Parcels</div>
                <div class="weight-card-value">${parcels.parcels.length}</div>
            </div>
            <div class="weight-card">
                <div class="weight-card-label">Items Packed</div>
                <div class="weight-card-value">${document.getElementById('packed-items').textContent}</div>
            </div>
        </div>

        <div class="weight-legend">
            <strong>Weight Sources:</strong> ${weights.legend}<br>
            <small><strong>P</strong>=Product weight • <strong>C</strong>=Category default • <strong>D</strong>=System default (100g)</small>
        </div>

        <h2 class="section-title" style="margin-top: 40px;">🚚 Carrier Comparison</h2>
        <div class="carrier-comparison">
            ${Object.entries(rates).map(([carrier, rate]) => {
                const isRecommended = carrier === recommendation.carrier;
                const logo = carrier === 'nz_courier'
                    ? '<img src="/assets/images/carriers/nz-courier-logo.png" alt="NZ Courier" class="carrier-logo">'
                    : '<img src="/assets/images/carriers/nz-post-logo.png" alt="NZ Post" class="carrier-logo">';

                return `
                    <div class="carrier-card ${isRecommended ? 'recommended' : ''}"
                         data-carrier="${carrier}"
                         onclick="freightEngine.selectCarrier('${carrier}')">
                        <div class="carrier-card-header">
                            ${logo}
                            ${isRecommended ? '<span class="badge-ai">🤖 AI Recommended</span>' : ''}
                        </div>
                        <div class="carrier-service-name">${rate.service_name}</div>
                        <div class="carrier-details-grid">
                            <div class="carrier-detail">
                                <div class="carrier-detail-label">Price</div>
                                <div class="carrier-detail-value carrier-price">$${rate.total_cost.toFixed(2)}</div>
                            </div>
                            <div class="carrier-detail">
                                <div class="carrier-detail-label">Delivery</div>
                                <div class="carrier-detail-value">${rate.eta_days} days</div>
                            </div>
                        </div>
                        ${isRecommended ? `
                            <div style="margin-top: 12px; padding-top: 12px; border-top: 1px solid var(--gray-light);">
                                <small style="color: var(--text-secondary); font-style: italic;">${recommendation.reason}</small>
                            </div>
                        ` : ''}
                    </div>
                `;
            }).join('')}
        </div>

        <button class="complete-button" onclick="freightEngine.bookFreight()" style="margin-top: 30px;">
            📦 Book Freight & Generate Labels
        </button>
    `;

    container.innerHTML = html;
}

// Render AI Tab
function renderAITab() {
    const container = document.getElementById('ai-section-body');

    if (!freightEngine || !freightEngine.freightData) {
        container.innerHTML = `
            <div class="loading-state">
                <p>No AI insights yet</p>
                <small>Complete freight calculation first</small>
            </div>
        `;
        return;
    }

    const { recommendation, rates, parcels } = freightEngine.freightData;

    const html = `
        <div class="ai-recommendation-card">
            <h2>🤖 AI Recommendation</h2>
            <p>${recommendation.reason}</p>
            <p style="margin-top: 12px;"><strong>Confidence:</strong> ${recommendation.confidence}%</p>
        </div>

        <div class="insights-grid">
            <div class="insight-card">
                <h3>💰 Cost Optimization</h3>
                <p>Based on historical data, ${recommendation.carrier.replace('_', ' ').toUpperCase()}
                provides the best value for this route. You're saving an estimated
                $${Math.abs(Math.min(...Object.values(rates).map(r => r.total_cost)) -
                    Math.max(...Object.values(rates).map(r => r.total_cost))).toFixed(2)}
                compared to the most expensive option.</p>
            </div>

            <div class="insight-card">
                <h3>📦 Packing Efficiency</h3>
                <p>Your ${parcels.parcels.length} parcel${parcels.parcels.length > 1 ? 's' : ''}
                ${parcels.parcels.length === 1 ? 'is' : 'are'} optimally packed.
                The AI selected the most cost-effective packaging mix based on weight and dimensional constraints.</p>
            </div>

            <div class="insight-card">
                <h3>🚀 Speed vs Cost</h3>
                <p>This recommendation balances delivery speed and cost.
                ${recommendation.carrier === 'nz_courier'
                    ? 'NZ Courier typically offers faster delivery with reliable tracking.'
                    : 'NZ Post provides competitive pricing with nationwide coverage.'}
                </p>
            </div>
        </div>
    `;

    container.innerHTML = html;
}

// Render Summary Tab
function renderSummaryTab() {
    const container = document.getElementById('summary-section-body');

    if (!freightEngine || !freightEngine.freightData) {
        container.innerHTML = `
            <div class="loading-state">
                <p>No summary available yet</p>
                <small>Complete packing and select freight option</small>
            </div>
        `;
        return;
    }

    const { parcels, rates, recommendation } = freightEngine.freightData;
    const selectedRate = rates[freightEngine.selectedCarrier || recommendation.carrier];

    const html = `
        <div class="summary-grid">
            <div class="summary-card">
                <h3>📦 Packing Summary</h3>
                <div class="summary-row">
                    <span class="summary-label">Total Items Packed</span>
                    <span class="summary-value">${document.getElementById('packed-items').textContent}</span>
                </div>
                <div class="summary-row">
                    <span class="summary-label">Total Weight</span>
                    <span class="summary-value">${document.getElementById('total-weight').textContent}</span>
                </div>
                <div class="summary-row">
                    <span class="summary-label">Number of Parcels</span>
                    <span class="summary-value">${parcels.parcels.length}</span>
                </div>
            </div>

            <div class="summary-card">
                <h3>🚚 Freight Summary</h3>
                <div class="summary-row">
                    <span class="summary-label">Selected Carrier</span>
                    <span class="summary-value">${(freightEngine.selectedCarrier || recommendation.carrier).replace('_', ' ').toUpperCase()}</span>
                </div>
                <div class="summary-row">
                    <span class="summary-label">Service</span>
                    <span class="summary-value">${selectedRate.service_name}</span>
                </div>
                <div class="summary-row">
                    <span class="summary-label">Freight Cost</span>
                    <span class="summary-value" style="color: var(--success-green);">$${selectedRate.total_cost.toFixed(2)} + GST</span>
                </div>
                <div class="summary-row">
                    <span class="summary-label">Estimated Delivery</span>
                    <span class="summary-value">${selectedRate.eta_days} business days</span>
                </div>
            </div>

            <div class="summary-card">
                <h3>📋 Transfer Details</h3>
                <div class="summary-row">
                    <span class="summary-label">Transfer ID</span>
                    <span class="summary-value">#${new URLSearchParams(window.location.search).get('transfer_id')}</span>
                </div>
                <div class="summary-row">
                    <span class="summary-label">From</span>
                    <span class="summary-value">${freightEngine.outletFromData?.name || 'Loading...'}</span>
                </div>
                <div class="summary-row">
                    <span class="summary-label">To</span>
                    <span class="summary-value">${freightEngine.outletToData?.name || 'Loading...'}</span>
                </div>
                <div class="summary-row">
                    <span class="summary-label">Packed By</span>
                    <span class="summary-value">${document.querySelector('.user-name')?.textContent || 'Current User'}</span>
                </div>
            </div>
        </div>

        <button class="complete-button" onclick="freightEngine.bookFreight()">
            ✅ Complete Transfer & Book Freight
        </button>
    `;

    container.innerHTML = html;
}

// Search functionality
document.getElementById('product-search').addEventListener('input', function(e) {
    const query = e.target.value.toLowerCase();
    const cards = document.querySelectorAll('.product-card');

    cards.forEach(card => {
        const sku = card.dataset.sku.toLowerCase();
        const name = card.querySelector('.product-card-name').textContent.toLowerCase();

        if (sku.includes(query) || name.includes(query)) {
            card.style.display = '';
        } else {
            card.style.display = 'none';
        }
    });
});

// Packing slip
function printPackingSlip() {
    const transferId = new URLSearchParams(window.location.search).get('transfer_id');
    window.open(`/modules/consignments/stock-transfers/packing-slip.php?transfer_id=${transferId}`, '_blank');
}

// Save progress
async function saveProgress() {
    const cards = document.querySelectorAll('.product-card');
    const items = [];

    cards.forEach(card => {
        const productId = card.dataset.productId;
        const qty = parseInt(document.getElementById(`qty-${productId}`).value) || 0;

        items.push({
            product_id: productId,
            qty_packed: qty
        });
    });

    try {
        const response = await fetch('/modules/consignments/TransferManager/backend.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                action: 'save_packing_progress',
                transfer_id: new URLSearchParams(window.location.search).get('transfer_id'),
                items: items,
                csrf: document.querySelector('meta[name="csrf-token"]').content
            })
        });

        const data = await response.json();

        if (data.success) {
            alert('✅ Progress saved successfully!');
        } else {
            alert('❌ Failed to save: ' + data.message);
        }
    } catch (error) {
        alert('❌ Save failed: ' + error.message);
    }
}

// Auto-save every 30 seconds
setInterval(saveProgress, 30000);

// Barcode scanner support
let barcodeBuffer = '';
let lastKeypressTime = Date.now();

document.addEventListener('keypress', function(e) {
    const now = Date.now();

    if (now - lastKeypressTime > 100) {
        barcodeBuffer = '';
    }

    lastKeypressTime = now;

    if (e.key === 'Enter') {
        if (barcodeBuffer.length > 3) {
            document.getElementById('product-search').value = barcodeBuffer;
            document.getElementById('product-search').dispatchEvent(new Event('input'));
        }
        barcodeBuffer = '';
    } else {
        barcodeBuffer += e.key;
    }
});
