/**
 * Purchase Order Freight Integration JavaScript
 *
 * Frontend integration for freight operations:
 * - Quote comparison logic
 * - Label generation workflows
 * - Tracking updates
 * - Real-time status monitoring
 * - Carrier API integration
 *
 * @package CIS\Consignments\PurchaseOrders
 * @version 1.0.0
 */

const POFreight = {
    // Configuration
    config: {
        apiBase: '/modules/consignments/api/purchase-orders',
        refreshInterval: 30000, // 30 seconds
        cacheTimeout: 1800000, // 30 minutes
    },

    // State management
    state: {
        currentPoId: null,
        selectedCarrier: null,
        selectedService: null,
        quotes: [],
        trackingData: null,
        autoRefresh: false,
    },

    // Cache
    cache: {
        quotes: new Map(),
        tracking: new Map(),
    },

    /**
     * Initialize freight module
     */
    init(poId = null) {
        this.state.currentPoId = poId;
        this.bindEvents();
        this.loadInitialData();
    },

    /**
     * Bind event listeners
     */
    bindEvents() {
        // Quote selection
        document.querySelectorAll('.select-quote-btn').forEach(btn => {
            btn.addEventListener('click', (e) => this.handleQuoteSelection(e));
        });

        // Refresh quotes
        document.getElementById('refresh-quotes-btn')?.addEventListener('click', () => {
            this.refreshQuotes(true);
        });

        // Carrier filter
        document.getElementById('carrier-filter')?.addEventListener('change', (e) => {
            this.filterQuotes(e.target.value);
        });

        // Sort options
        document.getElementById('sort-quotes')?.addEventListener('change', (e) => {
            this.sortQuotes(e.target.value);
        });

        // Compare quotes
        document.getElementById('compare-quotes-btn')?.addEventListener('click', () => {
            this.showComparisonModal();
        });

        // Track shipment
        document.querySelectorAll('.track-shipment-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const poId = e.currentTarget.dataset.poId;
                this.trackShipment(poId);
            });
        });

        // Auto-refresh toggle
        document.getElementById('auto-refresh-toggle')?.addEventListener('change', (e) => {
            this.toggleAutoRefresh(e.target.checked);
        });

        // Manual freight entry
        document.getElementById('manual-freight-form')?.addEventListener('submit', (e) => {
            e.preventDefault();
            this.submitManualFreight();
        });

        // Label actions
        document.getElementById('print-label-btn')?.addEventListener('click', () => {
            this.printLabel();
        });

        document.getElementById('email-label-btn')?.addEventListener('click', () => {
            this.emailLabel();
        });

        document.getElementById('download-label-btn')?.addEventListener('click', () => {
            this.downloadLabel();
        });

        // Regenerate label
        document.getElementById('regenerate-label-btn')?.addEventListener('click', () => {
            this.regenerateLabel();
        });
    },

    /**
     * Load initial data
     */
    async loadInitialData() {
        if (this.state.currentPoId) {
            await this.loadQuotes(this.state.currentPoId);
        }
    },

    /**
     * Load freight quotes for a PO
     */
    async loadQuotes(poId, forceRefresh = false) {
        // Check cache
        if (!forceRefresh && this.cache.quotes.has(poId)) {
            const cached = this.cache.quotes.get(poId);
            if (Date.now() - cached.timestamp < this.config.cacheTimeout) {
                this.state.quotes = cached.data;
                this.renderQuotes();
                return;
            }
        }

        this.showLoading('quotes');

        try {
            const response = await fetch(
                `${this.config.apiBase}/freight-quote.php?po_id=${poId}${forceRefresh ? '&force=1' : ''}`
            );

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const result = await response.json();

            if (result.success) {
                this.state.quotes = result.quotes || [];

                // Cache the result
                this.cache.quotes.set(poId, {
                    data: this.state.quotes,
                    timestamp: Date.now()
                });

                this.renderQuotes();
                this.updateQuoteSummary();
            } else {
                this.showError('quotes', result.error || 'Failed to load quotes');
            }
        } catch (error) {
            console.error('Load quotes error:', error);
            this.showError('quotes', 'Network error loading quotes');
        } finally {
            this.hideLoading('quotes');
        }
    },

    /**
     * Render quotes in the UI
     */
    renderQuotes() {
        const container = document.getElementById('quotes-container');
        if (!container) return;

        if (this.state.quotes.length === 0) {
            container.innerHTML = `
                <div class="no-quotes-message">
                    <i class="fas fa-exclamation-triangle"></i>
                    <p>No freight quotes available</p>
                    <button class="btn btn-primary" onclick="POFreight.refreshQuotes(true)">
                        <i class="fas fa-sync"></i> Refresh Quotes
                    </button>
                </div>
            `;
            return;
        }

        // Find cheapest and fastest
        const cheapest = Math.min(...this.state.quotes.map(q => q.price));
        const fastest = Math.min(...this.state.quotes.map(q => q.estimated_days));

        const html = this.state.quotes.map(quote => {
            const isCheapest = quote.price === cheapest;
            const isFastest = quote.estimated_days === fastest;

            return `
                <div class="quote-card ${isCheapest ? 'cheapest' : ''} ${isFastest ? 'fastest' : ''}"
                     data-carrier="${this.escapeHtml(quote.carrier)}"
                     data-service="${this.escapeHtml(quote.service)}">

                    ${this.renderQuoteBadges(isCheapest, isFastest)}

                    <div class="quote-carrier-logo">
                        <img src="/assets/images/carriers/${quote.carrier.toLowerCase()}.png"
                             alt="${this.escapeHtml(quote.carrier)}"
                             onerror="this.src='/assets/images/carriers/default.png'">
                    </div>

                    <div class="quote-carrier-name">${this.escapeHtml(quote.carrier)}</div>
                    <div class="quote-service-type">${this.escapeHtml(quote.service)}</div>

                    <div class="quote-price">
                        $${quote.price.toFixed(2)}
                        <span class="price-gst">incl. GST</span>
                    </div>

                    <div class="quote-delivery-time">
                        <i class="fas fa-clock"></i>
                        ${quote.estimated_days} business day${quote.estimated_days !== 1 ? 's' : ''}
                    </div>

                    ${this.renderQuoteFeatures(quote.features)}

                    <button class="btn btn-primary select-quote-btn"
                            data-carrier="${this.escapeHtml(quote.carrier)}"
                            data-service="${this.escapeHtml(quote.service)}"
                            data-price="${quote.price}">
                        <i class="fas fa-check"></i> Select This Quote
                    </button>
                </div>
            `;
        }).join('');

        container.innerHTML = html;

        // Re-bind events for new buttons
        container.querySelectorAll('.select-quote-btn').forEach(btn => {
            btn.addEventListener('click', (e) => this.handleQuoteSelection(e));
        });
    },

    /**
     * Render quote badges
     */
    renderQuoteBadges(isCheapest, isFastest) {
        const badges = [];
        if (isCheapest) {
            badges.push('<span class="badge badge-success"><i class="fas fa-dollar-sign"></i> Cheapest</span>');
        }
        if (isFastest) {
            badges.push('<span class="badge badge-info"><i class="fas fa-tachometer-alt"></i> Fastest</span>');
        }
        return badges.length > 0 ? `<div class="quote-badges">${badges.join('')}</div>` : '';
    },

    /**
     * Render quote features
     */
    renderQuoteFeatures(features) {
        if (!features || features.length === 0) return '';

        const html = features.map(f =>
            `<div class="feature-item"><i class="fas fa-check"></i> ${this.escapeHtml(f)}</div>`
        ).join('');

        return `<div class="quote-features">${html}</div>`;
    },

    /**
     * Update quote summary statistics
     */
    updateQuoteSummary() {
        const summary = {
            total: this.state.quotes.length,
            avgPrice: 0,
            minPrice: 0,
            maxPrice: 0,
            avgDays: 0
        };

        if (this.state.quotes.length > 0) {
            const prices = this.state.quotes.map(q => q.price);
            const days = this.state.quotes.map(q => q.estimated_days);

            summary.avgPrice = prices.reduce((a, b) => a + b, 0) / prices.length;
            summary.minPrice = Math.min(...prices);
            summary.maxPrice = Math.max(...prices);
            summary.avgDays = days.reduce((a, b) => a + b, 0) / days.length;
        }

        // Update UI elements
        document.getElementById('quotes-count')?.textContent = summary.total;
        document.getElementById('avg-price')?.textContent = `$${summary.avgPrice.toFixed(2)}`;
        document.getElementById('min-price')?.textContent = `$${summary.minPrice.toFixed(2)}`;
        document.getElementById('avg-delivery')?.textContent = `${Math.round(summary.avgDays)} days`;
    },

    /**
     * Handle quote selection
     */
    handleQuoteSelection(event) {
        const btn = event.currentTarget;
        const carrier = btn.dataset.carrier;
        const service = btn.dataset.service;
        const price = parseFloat(btn.dataset.price);

        this.state.selectedCarrier = carrier;
        this.state.selectedService = service;

        // Show confirmation modal
        this.showConfirmationModal(carrier, service, price);
    },

    /**
     * Show confirmation modal
     */
    showConfirmationModal(carrier, service, price) {
        const modal = document.getElementById('confirm-selection-modal');
        if (!modal) return;

        document.getElementById('confirm-carrier').textContent = carrier;
        document.getElementById('confirm-service').textContent = service;
        document.getElementById('confirm-price').textContent = `$${price.toFixed(2)}`;

        modal.style.display = 'flex';

        // Bind confirm button
        const confirmBtn = document.getElementById('confirm-create-label-btn');
        if (confirmBtn) {
            confirmBtn.onclick = () => this.createLabel(carrier, service, price);
        }
    },

    /**
     * Create shipping label
     */
    async createLabel(carrier, service, price) {
        this.showLoading('create-label');

        try {
            const response = await fetch(`${this.config.apiBase}/create-label.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    po_id: this.state.currentPoId,
                    carrier: carrier,
                    service: service,
                    price: price
                })
            });

            const result = await response.json();

            if (result.success) {
                // Redirect to label page
                window.location.href = `/modules/consignments/purchase-orders/freight-label.php?po_id=${this.state.currentPoId}&label_id=${result.label_id}`;
            } else {
                this.showAlert('error', result.error || 'Failed to create label');
            }
        } catch (error) {
            console.error('Create label error:', error);
            this.showAlert('error', 'Network error creating label');
        } finally {
            this.hideLoading('create-label');
        }
    },

    /**
     * Submit manual freight entry
     */
    async submitManualFreight() {
        const form = document.getElementById('manual-freight-form');
        if (!form) return;

        const formData = new FormData(form);

        try {
            const response = await fetch(`${this.config.apiBase}/create-label.php`, {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                window.location.href = `/modules/consignments/purchase-orders/freight-label.php?po_id=${this.state.currentPoId}&label_id=${result.label_id}`;
            } else {
                this.showAlert('error', result.error || 'Failed to create manual entry');
            }
        } catch (error) {
            console.error('Manual freight error:', error);
            this.showAlert('error', 'Network error');
        }
    },

    /**
     * Track shipment
     */
    async trackShipment(poId) {
        // Check cache
        if (this.cache.tracking.has(poId)) {
            const cached = this.cache.tracking.get(poId);
            if (Date.now() - cached.timestamp < this.config.cacheTimeout) {
                this.state.trackingData = cached.data;
                this.renderTrackingData();
                return;
            }
        }

        this.showLoading('tracking');

        try {
            const response = await fetch(`${this.config.apiBase}/track.php?po_id=${poId}`);
            const result = await response.json();

            if (result.success) {
                this.state.trackingData = result.tracking;

                // Cache the result
                this.cache.tracking.set(poId, {
                    data: this.state.trackingData,
                    timestamp: Date.now()
                });

                this.renderTrackingData();
            } else {
                this.showError('tracking', result.error || 'Failed to load tracking');
            }
        } catch (error) {
            console.error('Track shipment error:', error);
            this.showError('tracking', 'Network error loading tracking');
        } finally {
            this.hideLoading('tracking');
        }
    },

    /**
     * Render tracking data
     */
    renderTrackingData() {
        const container = document.getElementById('tracking-container');
        if (!container || !this.state.trackingData) return;

        // Update status
        const statusEl = document.getElementById('tracking-status');
        if (statusEl) {
            statusEl.textContent = this.state.trackingData.status_text;
            statusEl.className = `status-badge status-${this.state.trackingData.status}`;
        }

        // Update events timeline
        this.renderTrackingEvents();

        // Update last update time
        const lastUpdateEl = document.getElementById('tracking-last-update');
        if (lastUpdateEl && this.state.trackingData.last_update) {
            lastUpdateEl.textContent = this.formatDate(this.state.trackingData.last_update);
        }
    },

    /**
     * Render tracking events
     */
    renderTrackingEvents() {
        const container = document.getElementById('tracking-events');
        if (!container || !this.state.trackingData.events) return;

        const html = this.state.trackingData.events.map(event => `
            <div class="event-item">
                <div class="event-marker">
                    <i class="fas fa-circle"></i>
                </div>
                <div class="event-content">
                    <div class="event-header">
                        <div class="event-description">${this.escapeHtml(event.description)}</div>
                        <div class="event-date">${this.formatDate(event.timestamp)}</div>
                    </div>
                    ${event.location ? `
                        <div class="event-location">
                            <i class="fas fa-map-marker-alt"></i>
                            ${this.escapeHtml(event.location)}
                        </div>
                    ` : ''}
                </div>
            </div>
        `).join('');

        container.innerHTML = html;
    },

    /**
     * Refresh quotes
     */
    async refreshQuotes(forceRefresh = false) {
        if (!this.state.currentPoId) return;
        await this.loadQuotes(this.state.currentPoId, forceRefresh);
    },

    /**
     * Filter quotes by carrier
     */
    filterQuotes(carrier) {
        const cards = document.querySelectorAll('.quote-card');
        cards.forEach(card => {
            if (!carrier || card.dataset.carrier === carrier) {
                card.style.display = 'block';
            } else {
                card.style.display = 'none';
            }
        });
    },

    /**
     * Sort quotes
     */
    sortQuotes(sortBy) {
        const sortFunctions = {
            'price-asc': (a, b) => a.price - b.price,
            'price-desc': (a, b) => b.price - a.price,
            'delivery-asc': (a, b) => a.estimated_days - b.estimated_days,
            'delivery-desc': (a, b) => b.estimated_days - a.estimated_days,
            'carrier': (a, b) => a.carrier.localeCompare(b.carrier),
        };

        if (sortFunctions[sortBy]) {
            this.state.quotes.sort(sortFunctions[sortBy]);
            this.renderQuotes();
        }
    },

    /**
     * Toggle auto-refresh
     */
    toggleAutoRefresh(enabled) {
        this.state.autoRefresh = enabled;

        if (enabled) {
            this.startAutoRefresh();
        } else {
            this.stopAutoRefresh();
        }
    },

    /**
     * Start auto-refresh
     */
    startAutoRefresh() {
        if (this.autoRefreshInterval) {
            clearInterval(this.autoRefreshInterval);
        }

        this.autoRefreshInterval = setInterval(() => {
            if (this.state.currentPoId) {
                this.trackShipment(this.state.currentPoId);
            }
        }, this.config.refreshInterval);
    },

    /**
     * Stop auto-refresh
     */
    stopAutoRefresh() {
        if (this.autoRefreshInterval) {
            clearInterval(this.autoRefreshInterval);
            this.autoRefreshInterval = null;
        }
    },

    /**
     * Print label
     */
    printLabel() {
        window.print();
    },

    /**
     * Email label
     */
    async emailLabel() {
        const modal = document.getElementById('email-label-modal');
        if (modal) {
            modal.style.display = 'flex';
        }
    },

    /**
     * Download label as PDF
     */
    downloadLabel() {
        const labelId = this.getCurrentLabelId();
        if (labelId) {
            window.location.href = `${this.config.apiBase}/download-label.php?label_id=${labelId}`;
        }
    },

    /**
     * Regenerate label
     */
    async regenerateLabel() {
        if (!confirm('Are you sure you want to regenerate this label? This will void the current tracking number.')) {
            return;
        }

        const labelId = this.getCurrentLabelId();
        if (!labelId) return;

        try {
            const response = await fetch(`${this.config.apiBase}/regenerate-label.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    label_id: labelId,
                    po_id: this.state.currentPoId
                })
            });

            const result = await response.json();

            if (result.success) {
                window.location.reload();
            } else {
                this.showAlert('error', result.error || 'Failed to regenerate label');
            }
        } catch (error) {
            console.error('Regenerate error:', error);
            this.showAlert('error', 'Network error');
        }
    },

    /**
     * Get current label ID
     */
    getCurrentLabelId() {
        const urlParams = new URLSearchParams(window.location.search);
        return urlParams.get('label_id');
    },

    /**
     * Show comparison modal
     */
    showComparisonModal() {
        // Implementation for side-by-side quote comparison
        console.log('Show comparison modal');
    },

    /**
     * Show loading indicator
     */
    showLoading(section) {
        const overlay = document.getElementById(`${section}-loading`);
        if (overlay) {
            overlay.style.display = 'flex';
        }
    },

    /**
     * Hide loading indicator
     */
    hideLoading(section) {
        const overlay = document.getElementById(`${section}-loading`);
        if (overlay) {
            overlay.style.display = 'none';
        }
    },

    /**
     * Show error message
     */
    showError(section, message) {
        const container = document.getElementById(`${section}-error`);
        if (container) {
            container.textContent = message;
            container.style.display = 'block';
        } else {
            this.showAlert('error', message);
        }
    },

    /**
     * Show alert message
     */
    showAlert(type, message) {
        // Create toast notification
        const toast = document.createElement('div');
        toast.className = `toast-notification toast-${type} show`;
        toast.innerHTML = `
            <div class="toast-content">
                <i class="fas fa-${type === 'error' ? 'exclamation-circle' : 'check-circle'}"></i>
                <span>${this.escapeHtml(message)}</span>
            </div>
        `;

        document.body.appendChild(toast);

        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    },

    /**
     * Format date
     */
    formatDate(dateString) {
        const date = new Date(dateString);
        return date.toLocaleString('en-NZ', {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    },

    /**
     * Escape HTML
     */
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    },

    /**
     * Cleanup on page unload
     */
    cleanup() {
        this.stopAutoRefresh();
        this.cache.quotes.clear();
        this.cache.tracking.clear();
    }
};

// Initialize on page load
document.addEventListener('DOMContentLoaded', () => {
    // Get PO ID from URL or page
    const urlParams = new URLSearchParams(window.location.search);
    const poId = urlParams.get('po_id') || document.querySelector('[data-po-id]')?.dataset.poId;

    if (poId) {
        POFreight.init(parseInt(poId));
    }
});

// Cleanup on page unload
window.addEventListener('beforeunload', () => {
    POFreight.cleanup();
});

// Export for use in other modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = POFreight;
}
