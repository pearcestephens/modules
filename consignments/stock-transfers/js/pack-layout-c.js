/**
 * Pack Layout C - Accordion Mobile Interface JavaScript
 * Handles accordion toggle, product packing, and freight calculations
 *
 * @version 1.0.0
 * @date 2025-11-09
 */

// Accordion toggle
function toggleAccordion(header) {
    const item = header.parentElement;
    const wasOpen = item.classList.contains('open');

    // Close all accordion items
    document.querySelectorAll('.accordion-item').forEach(i => {
        i.classList.remove('open');
    });

    // Open clicked item if it was closed
    if (!wasOpen) {
        item.classList.add('open');

        // Trigger updates based on section
        const title = header.querySelector('h3').textContent;
        if (title.includes('Freight')) {
            renderFreightMobile();
        }
    }
}

// Product packing logic
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
    const card = document.querySelector(`.product-mobile-card[data-product-id="${productId}"]`);
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
    const cards = document.querySelectorAll('.product-mobile-card');
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
    document.getElementById('total-weight').textContent = totalWeight.toFixed(2) + 'kg';
}

function recalculateFreight() {
    const cards = document.querySelectorAll('.product-mobile-card');
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

// Render Freight Mobile
function renderFreightMobile() {
    const container = document.getElementById('freight-mobile-body');

    if (!freightEngine || !freightEngine.freightData) {
        container.innerHTML = `
            <div class="loading-mobile">
                <div class="spinner-mobile"></div>
                <p>Pack items to calculate freight</p>
            </div>
        `;
        return;
    }

    const { parcels, rates, recommendation, weights } = freightEngine.freightData;

    const html = `
        <div class="weight-summary-mobile">
            <div class="weight-row">
                <span class="weight-label">Total Weight</span>
                <span class="weight-value">${parcels.total_weight_kg.toFixed(2)} kg</span>
            </div>
            <div class="weight-row">
                <span class="weight-label">Parcels</span>
                <span class="weight-value">${parcels.parcels.length}</span>
            </div>
            <div class="weight-row">
                <span class="weight-label">Weight Sources</span>
                <span class="weight-value" style="font-size: 12px;">${weights.legend}</span>
            </div>
        </div>

        <div class="carriers-mobile">
            ${Object.entries(rates).map(([carrier, rate]) => {
                const isRecommended = carrier === recommendation.carrier;
                const logo = carrier === 'nz_courier'
                    ? '<img src="/assets/images/carriers/nz-courier-logo.png" alt="NZ Courier" class="carrier-logo-mobile">'
                    : '<img src="/assets/images/carriers/nz-post-logo.png" alt="NZ Post" class="carrier-logo-mobile">';

                return `
                    <div class="carrier-mobile-card ${isRecommended ? 'recommended' : ''}"
                         data-carrier="${carrier}"
                         onclick="freightEngine.selectCarrier('${carrier}')">
                        <div class="carrier-mobile-header">
                            ${logo}
                            ${isRecommended ? '<span class="badge-ai-mobile">🤖 AI Pick</span>' : ''}
                        </div>
                        <div class="carrier-mobile-name">${rate.service_name}</div>
                        <div class="carrier-mobile-details">
                            <div class="carrier-mobile-detail">
                                <div class="carrier-mobile-detail-label">Price</div>
                                <div class="carrier-mobile-detail-value price-green">$${rate.total_cost.toFixed(2)}</div>
                            </div>
                            <div class="carrier-mobile-detail">
                                <div class="carrier-mobile-detail-label">Delivery</div>
                                <div class="carrier-mobile-detail-value">${rate.eta_days}d</div>
                            </div>
                        </div>
                        ${isRecommended ? `
                            <div style="margin-top: 10px; padding-top: 10px; border-top: 1px solid var(--border-color);">
                                <small style="color: var(--text-secondary); font-size: 11px; font-style: italic;">${recommendation.reason}</small>
                            </div>
                        ` : ''}
                    </div>
                `;
            }).join('')}
        </div>
    `;

    container.innerHTML = html;
}

// Search functionality
document.getElementById('product-search').addEventListener('input', function(e) {
    const query = e.target.value.toLowerCase();
    const cards = document.querySelectorAll('.product-mobile-card');

    cards.forEach(card => {
        const sku = card.dataset.sku.toLowerCase();
        const name = card.querySelector('.product-mobile-name').textContent.toLowerCase();

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

// Complete transfer
function completeTransfer() {
    if (!freightEngine || !freightEngine.selectedCarrier) {
        alert('⚠️ Please select a freight carrier first');

        // Open freight accordion
        document.querySelectorAll('.accordion-item').forEach(item => item.classList.remove('open'));
        document.querySelectorAll('.accordion-item')[1].classList.add('open');
        renderFreightMobile();

        return;
    }

    // Book freight
    freightEngine.bookFreight();
}

// Save progress
async function saveProgress() {
    const cards = document.querySelectorAll('.product-mobile-card');
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
            // Show success notification (mobile-friendly)
            showNotification('✅ Progress saved!', 'success');
        } else {
            showNotification('❌ Save failed: ' + data.message, 'error');
        }
    } catch (error) {
        showNotification('❌ Save error: ' + error.message, 'error');
    }
}

// Mobile notification
function showNotification(message, type) {
    const notification = document.createElement('div');
    notification.textContent = message;
    notification.style.cssText = `
        position: fixed;
        bottom: 20px;
        left: 50%;
        transform: translateX(-50%);
        background: ${type === 'success' ? 'var(--success-green)' : 'var(--danger-red)'};
        color: white;
        padding: 14px 24px;
        border-radius: 8px;
        font-weight: 600;
        z-index: 10000;
        box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        animation: slideUp 0.3s ease;
    `;

    document.body.appendChild(notification);

    setTimeout(() => {
        notification.style.animation = 'slideDown 0.3s ease';
        setTimeout(() => notification.remove(), 300);
    }, 2500);
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

            // Open products accordion
            document.querySelectorAll('.accordion-item').forEach(item => item.classList.remove('open'));
            document.querySelectorAll('.accordion-item')[0].classList.add('open');
        }
        barcodeBuffer = '';
    } else {
        barcodeBuffer += e.key;
    }
});

// Add slide animation styles
const style = document.createElement('style');
style.textContent = `
    @keyframes slideUp {
        from { transform: translateX(-50%) translateY(100px); opacity: 0; }
        to { transform: translateX(-50%) translateY(0); opacity: 1; }
    }
    @keyframes slideDown {
        from { transform: translateX(-50%) translateY(0); opacity: 1; }
        to { transform: translateX(-50%) translateY(100px); opacity: 0; }
    }
`;
document.head.appendChild(style);
