/**
 * ✨ PREMIUM STAFF BOX OPTIMIZER ✨
 *
 * Enhanced box selector UI for warehouse staff
 * - Huge touch-friendly buttons
 * - Visual box previews
 * - One-click dimension setting
 * - Autocomplete carrier selection
 * - Progress tracking
 *
 * @version 2.0.0
 * @created 2025-11-13
 */

class PremiumStaffBoxOptimizer {
    constructor(config = {}) {
        this.config = {
            transfer_id: config.transfer_id || 0,
            transfer_type: config.transfer_type || 'STOCK',
            carrier: config.carrier || 'nz_courier',
            show_cost_estimate: config.show_cost_estimate !== false,
            show_consolidation: config.show_consolidation !== false,
            hazmat_enabled: config.hazmat_enabled === true,
            ...config
        };

        this.selectedBoxes = [];
        this.suggestions = [];
        this.init();
    }

    init() {
        this.createUI();
        this.attachEventListeners();
        this.loadQuickSizes();
    }

    /**
     * Create the premium UI structure
     */
    createUI() {
        const container = document.getElementById('boxOptimizerContainer') ||
                         document.querySelector('[data-box-optimizer]');

        if (!container) return;

        const html = `
            <div class="premium-box-optimizer">

                <!-- HEADER -->
                <div class="optimizer-header">
                    <div class="header-left">
                        <h3>📦 Box Optimizer</h3>
                        <p>Select or customize box size</p>
                    </div>
                    <div class="header-right">
                        <div class="box-summary">
                            <div class="summary-item">
                                <span class="summary-label">Boxes:</span>
                                <span class="summary-value" id="boxCount">0</span>
                            </div>
                            <div class="summary-item">
                                <span class="summary-label">Weight:</span>
                                <span class="summary-value" id="totalWeight">0 kg</span>
                            </div>
                            <div class="summary-item">
                                <span class="summary-label">Utilization:</span>
                                <span class="summary-value" id="utilization">0%</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- QUICK SIZE BUTTONS (Staff Favorite!) -->
                <div class="quick-sizes-section">
                    <h5>⚡ Quick Sizes</h5>
                    <div class="quick-size-buttons">
                        <button class="quick-size-btn small-btn" data-size="small">
                            <div class="btn-icon">📦</div>
                            <div class="btn-label">Small</div>
                            <div class="btn-size">15×15×15</div>
                        </button>
                        <button class="quick-size-btn medium-btn" data-size="medium">
                            <div class="btn-icon">📦</div>
                            <div class="btn-label">Medium</div>
                            <div class="btn-size">25×20×15</div>
                        </button>
                        <button class="quick-size-btn large-btn" data-size="large">
                            <div class="btn-icon">📦</div>
                            <div class="btn-label">Large</div>
                            <div class="btn-size">35×25×25</div>
                        </button>
                        <button class="quick-size-btn xlarge-btn" data-size="xlarge">
                            <div class="btn-icon">📦</div>
                            <div class="btn-label">Extra Large</div>
                            <div class="btn-size">50×40×40</div>
                        </button>
                    </div>
                </div>

                <!-- CUSTOM DIMENSIONS -->
                <div class="custom-dimensions-section">
                    <h5>📏 Custom Dimensions</h5>
                    <div class="dimensions-grid">
                        <div class="dimension-input-group">
                            <label>Length (cm)</label>
                            <input type="number" id="dimLength" class="dimension-input" placeholder="Length" min="5" max="100">
                        </div>
                        <div class="dimension-input-group">
                            <label>Width (cm)</label>
                            <input type="number" id="dimWidth" class="dimension-input" placeholder="Width" min="5" max="100">
                        </div>
                        <div class="dimension-input-group">
                            <label>Height (cm)</label>
                            <input type="number" id="dimHeight" class="dimension-input" placeholder="Height" min="5" max="100">
                        </div>
                        <div class="dimension-input-group">
                            <label>Weight (kg)</label>
                            <input type="number" id="dimWeight" class="dimension-input" placeholder="Weight" min="0.1" max="50" step="0.1">
                        </div>
                    </div>
                </div>

                <!-- CARRIER SELECTION -->
                ${this.config.transfer_type !== 'STAFF' ? `
                <div class="carrier-selection-section">
                    <h5>🚚 Carrier</h5>
                    <select id="carrierSelect" class="carrier-dropdown">
                        <option value="nz_courier">NZ Courier (24-48hrs)</option>
                        <option value="courier_post">Courier Post (1-2 days)</option>
                        <option value="nz_post">NZ Post (3-5 days)</option>
                        <option value="dhl">DHL Express (1-2 days)</option>
                        <option value="fedex">FedEx (2-3 days)</option>
                    </select>
                </div>
                ` : ''}

                <!-- HAZMAT NOTICE (Juice Transfers) -->
                ${this.config.hazmat_enabled ? `
                <div class="hazmat-notice">
                    <i class="fas fa-exclamation-triangle"></i>
                    <div>
                        <strong>⚠️ Hazmat Requirements</strong>
                        <p>High nicotine: Double-wall boxes required, insulation padding, separation by strength</p>
                    </div>
                </div>
                ` : ''}

                <!-- SUGGESTIONS AREA -->
                <div class="suggestions-section" id="suggestionsSection" style="display:none;">
                    <h5>💡 Suggestions</h5>
                    <div id="suggestionsList" class="suggestions-list"></div>
                </div>

                <!-- COST ESTIMATE -->
                ${this.config.show_cost_estimate ? `
                <div class="cost-estimate-section">
                    <div class="cost-card">
                        <div class="cost-row">
                            <span>Estimated Shipping:</span>
                            <span id="shippingCost">$0.00</span>
                        </div>
                        <div class="cost-row">
                            <span>Packaging Cost:</span>
                            <span id="packagingCost">$0.00</span>
                        </div>
                        <div class="cost-row total">
                            <span>Total Cost:</span>
                            <span id="totalCost">$0.00</span>
                        </div>
                        <div class="cost-savings" id="costSavings" style="display:none;">
                            <i class="fas fa-lightbulb"></i>
                            <span id="savingsText">Save $10</span>
                        </div>
                    </div>
                </div>
                ` : ''}

                <!-- ACTION BUTTONS -->
                <div class="optimizer-actions">
                    <button id="analyzeBtn" class="btn-analyze btn-lg">
                        <i class="fas fa-magic"></i> Analyze Box
                    </button>
                    <button id="resetBtn" class="btn-reset">
                        <i class="fas fa-refresh"></i> Reset
                    </button>
                </div>

            </div>
        `;

        container.innerHTML = html;
    }

    /**
     * Attach all event listeners
     */
    attachEventListeners() {
        // Quick size buttons
        document.querySelectorAll('.quick-size-btn').forEach(btn => {
            btn.addEventListener('click', (e) => this.setQuickSize(e.target.closest('.quick-size-btn').dataset.size));
        });

        // Custom dimensions
        const dimensionInputs = document.querySelectorAll('.dimension-input');
        dimensionInputs.forEach(input => {
            input.addEventListener('change', () => this.analyzeCustomBox());
            input.addEventListener('input', () => this.debounceAnalyze());
        });

        // Analyze button
        document.getElementById('analyzeBtn')?.addEventListener('click', () => this.analyzeCurrentBox());

        // Reset button
        document.getElementById('resetBtn')?.addEventListener('click', () => this.reset());

        // Carrier selection
        document.getElementById('carrierSelect')?.addEventListener('change', () => this.analyzeCurrentBox());
    }

    /**
     * Set quick size from preset button
     */
    setQuickSize(size) {
        const sizes = {
            'small': { l: 15, w: 15, h: 15, kg: 2 },
            'medium': { l: 25, w: 20, h: 15, kg: 5 },
            'large': { l: 35, w: 25, h: 25, kg: 15 },
            'xlarge': { l: 50, w: 40, h: 40, kg: 25 }
        };

        const dims = sizes[size];
        if (!dims) return;

        document.getElementById('dimLength').value = dims.l;
        document.getElementById('dimWidth').value = dims.w;
        document.getElementById('dimHeight').value = dims.h;
        document.getElementById('dimWeight').value = dims.kg;

        // Visual feedback
        document.querySelectorAll('.quick-size-btn').forEach(btn => btn.classList.remove('active'));
        event.target.closest('.quick-size-btn')?.classList.add('active');

        this.analyzeCurrentBox();
    }

    /**
     * Debounced analysis (800ms)
     */
    debounceAnalyze() {
        clearTimeout(this.analyzeTimeout);
        this.analyzeTimeout = setTimeout(() => this.analyzeCurrentBox(), 800);
    }

    /**
     * Main analysis function
     */
    analyzeCurrentBox() {
        const length = parseFloat(document.getElementById('dimLength').value) || 0;
        const width = parseFloat(document.getElementById('dimWidth').value) || 0;
        const height = parseFloat(document.getElementById('dimHeight').value) || 0;
        const weight = parseFloat(document.getElementById('dimWeight').value) || 0;

        if (length === 0 || width === 0 || height === 0 || weight === 0) {
            this.clearSuggestions();
            return;
        }

        // Call API for analysis
        this.callOptimzerAPI({
            length, width, height, weight,
            transfer_type: this.config.transfer_type,
            carrier: document.getElementById('carrierSelect')?.value || this.config.carrier
        });
    }

    /**
     * Call box optimizer API
     */
    async callOptimzerAPI(data) {
        try {
            const response = await fetch('/modules/consignments/api/box-optimizer.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'analyze',
                    ...data
                })
            });

            const result = await response.json();
            if (result.success) {
                this.displaySuggestions(result);
                this.updateCostEstimate(result);
            }
        } catch (error) {
            console.error('API error:', error);
        }
    }

    /**
     * Display suggestions with visual cards
     */
    displaySuggestions(result) {
        const section = document.getElementById('suggestionsSection');
        const list = document.getElementById('suggestionsList');

        if (!result.suggestions || result.suggestions.length === 0) {
            section.style.display = 'none';
            return;
        }

        section.style.display = 'block';
        list.innerHTML = result.suggestions.map(sug => `
            <div class="suggestion-card ${sug.type}">
                <div class="suggestion-icon">
                    ${sug.type === 'consolidation' ? '🎯' : sug.type === 'warning' ? '⚠️' : '💡'}
                </div>
                <div class="suggestion-content">
                    <strong>${sug.description}</strong>
                    ${sug.savings ? `<p>💰 Save $${sug.savings.toFixed(2)}</p>` : ''}
                </div>
                <button class="btn-apply-suggestion" onclick="optimizerApplySuggestion(${sug.box_id})">
                    Apply
                </button>
            </div>
        `).join('');
    }

    /**
     * Update cost estimate display
     */
    updateCostEstimate(result) {
        if (!this.config.show_cost_estimate) return;

        const shipping = result.shipping_cost || 0;
        const packaging = result.packaging_cost || 0;
        const total = shipping + packaging;

        document.getElementById('shippingCost').textContent = '$' + shipping.toFixed(2);
        document.getElementById('packagingCost').textContent = '$' + packaging.toFixed(2);
        document.getElementById('totalCost').textContent = '$' + total.toFixed(2);

        // Show savings if consolidation available
        if (result.potential_savings && result.potential_savings > 0) {
            const savingsDiv = document.getElementById('costSavings');
            savingsDiv.style.display = 'flex';
            document.getElementById('savingsText').textContent =
                `Save $${result.potential_savings.toFixed(2)}`;
        }
    }

    /**
     * Load quick size presets
     */
    loadQuickSizes() {
        // Could load from database if needed
    }

    /**
     * Clear suggestions
     */
    clearSuggestions() {
        document.getElementById('suggestionsSection').style.display = 'none';
    }

    /**
     * Reset all fields
     */
    reset() {
        document.getElementById('dimLength').value = '';
        document.getElementById('dimWidth').value = '';
        document.getElementById('dimHeight').value = '';
        document.getElementById('dimWeight').value = '';
        this.clearSuggestions();
        document.querySelectorAll('.quick-size-btn').forEach(btn => btn.classList.remove('active'));
    }
}

// CSS STYLING
const style = document.createElement('style');
style.textContent = `
    /* PREMIUM STAFF BOX OPTIMIZER STYLES */

    .premium-box-optimizer {
        background: white;
        border-radius: 12px;
        padding: 20px;
        box-shadow: 0 5px 25px rgba(0,0,0,0.08);
        margin: 20px 0;
    }

    .optimizer-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 25px;
        padding-bottom: 15px;
        border-bottom: 2px solid #e9ecef;
    }

    .optimizer-header h3 {
        margin: 0;
        color: #2d3748;
        font-weight: 700;
        font-size: 22px;
    }

    .optimizer-header p {
        margin: 5px 0 0 0;
        color: #718096;
        font-size: 14px;
    }

    .box-summary {
        display: flex;
        gap: 20px;
    }

    .summary-item {
        display: flex;
        flex-direction: column;
        align-items: center;
    }

    .summary-label {
        font-size: 12px;
        color: #718096;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .summary-value {
        font-size: 20px;
        font-weight: 700;
        color: #667eea;
        margin-top: 4px;
    }

    /* QUICK SIZE BUTTONS - HUGE & TOUCH-FRIENDLY */
    .quick-sizes-section {
        margin-bottom: 25px;
    }

    .quick-sizes-section h5 {
        margin: 0 0 15px 0;
        color: #2d3748;
        font-weight: 700;
    }

    .quick-size-buttons {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
        gap: 12px;
    }

    .quick-size-btn {
        padding: 15px;
        border: 2px solid #e9ecef;
        background: white;
        border-radius: 10px;
        cursor: pointer;
        transition: all 0.3s ease;
        text-align: center;
        font-size: 14px;
        font-weight: 600;
        min-height: 110px;
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        gap: 8px;
    }

    .quick-size-btn:hover {
        border-color: #667eea;
        background: #f8fafb;
        transform: translateY(-4px);
        box-shadow: 0 8px 20px rgba(102, 126, 234, 0.2);
    }

    .quick-size-btn.active {
        border-color: #667eea;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
    }

    .btn-icon {
        font-size: 32px;
        line-height: 1;
    }

    .btn-label {
        font-size: 14px;
        font-weight: 700;
    }

    .btn-size {
        font-size: 11px;
        opacity: 0.7;
        font-family: monospace;
    }

    /* CUSTOM DIMENSIONS */
    .custom-dimensions-section {
        margin-bottom: 25px;
    }

    .custom-dimensions-section h5 {
        margin: 0 0 15px 0;
        color: #2d3748;
        font-weight: 700;
    }

    .dimensions-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 15px;
    }

    .dimension-input-group {
        display: flex;
        flex-direction: column;
    }

    .dimension-input-group label {
        font-size: 13px;
        font-weight: 600;
        color: #718096;
        margin-bottom: 6px;
        text-transform: uppercase;
        letter-spacing: 0.3px;
    }

    .dimension-input {
        padding: 12px;
        border: 2px solid #e9ecef;
        border-radius: 8px;
        font-size: 15px;
        transition: all 0.2s ease;
    }

    .dimension-input:focus {
        outline: none;
        border-color: #667eea;
        background: #f8fafb;
    }

    /* CARRIER SELECTION */
    .carrier-selection-section {
        margin-bottom: 25px;
    }

    .carrier-selection-section h5 {
        margin: 0 0 15px 0;
        color: #2d3748;
        font-weight: 700;
    }

    .carrier-dropdown {
        width: 100%;
        padding: 12px;
        border: 2px solid #e9ecef;
        border-radius: 8px;
        font-size: 15px;
        cursor: pointer;
        background: white;
    }

    .carrier-dropdown:focus {
        outline: none;
        border-color: #667eea;
    }

    /* HAZMAT NOTICE */
    .hazmat-notice {
        display: flex;
        gap: 12px;
        padding: 15px;
        background: #fef3c7;
        border-left: 4px solid #f59e0b;
        border-radius: 8px;
        margin-bottom: 25px;
        color: #92400e;
    }

    .hazmat-notice i {
        font-size: 20px;
        flex-shrink: 0;
    }

    .hazmat-notice strong {
        display: block;
        margin-bottom: 4px;
    }

    .hazmat-notice p {
        margin: 0;
        font-size: 13px;
        line-height: 1.4;
    }

    /* SUGGESTIONS */
    .suggestions-section {
        margin-bottom: 25px;
    }

    .suggestions-section h5 {
        margin: 0 0 15px 0;
        color: #2d3748;
        font-weight: 700;
    }

    .suggestions-list {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }

    .suggestion-card {
        display: flex;
        gap: 12px;
        padding: 12px;
        background: #f0f9ff;
        border-left: 4px solid #0284c7;
        border-radius: 8px;
        align-items: flex-start;
    }

    .suggestion-card.warning {
        background: #fef3c7;
        border-left-color: #f59e0b;
    }

    .suggestion-card.error {
        background: #fee2e2;
        border-left-color: #dc2626;
    }

    .suggestion-icon {
        font-size: 20px;
        flex-shrink: 0;
    }

    .suggestion-content {
        flex: 1;
    }

    .suggestion-content strong {
        display: block;
        margin-bottom: 4px;
        color: #1e293b;
    }

    .suggestion-content p {
        margin: 0;
        font-size: 13px;
        color: #475569;
    }

    .btn-apply-suggestion {
        background: #0284c7;
        color: white;
        border: none;
        padding: 6px 12px;
        border-radius: 6px;
        font-size: 12px;
        font-weight: 600;
        cursor: pointer;
        white-space: nowrap;
        flex-shrink: 0;
    }

    .btn-apply-suggestion:hover {
        background: #0369a1;
    }

    /* COST ESTIMATE */
    .cost-estimate-section {
        margin-bottom: 25px;
    }

    .cost-card {
        background: linear-gradient(135deg, #f0f9ff 0%, #f5f3ff 100%);
        padding: 15px;
        border-radius: 10px;
        border: 2px solid #e0e7ff;
    }

    .cost-row {
        display: flex;
        justify-content: space-between;
        padding: 8px 0;
        font-size: 14px;
        color: #475569;
    }

    .cost-row.total {
        border-top: 2px solid #e0e7ff;
        padding-top: 12px;
        margin-top: 12px;
        font-weight: 700;
        color: #667eea;
        font-size: 16px;
    }

    .cost-savings {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-top: 12px;
        padding: 10px;
        background: #d1fae5;
        color: #065f46;
        border-radius: 6px;
        font-weight: 600;
        font-size: 14px;
    }

    /* ACTION BUTTONS */
    .optimizer-actions {
        display: flex;
        gap: 10px;
    }

    .btn-analyze {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border: none;
        padding: 16px 32px;
        border-radius: 10px;
        font-size: 16px;
        font-weight: 700;
        cursor: pointer;
        flex: 1;
        transition: all 0.3s ease;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .btn-analyze:hover {
        transform: translateY(-3px);
        box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
    }

    .btn-analyze.btn-lg {
        padding: 20px 40px;
        font-size: 17px;
    }

    .btn-reset {
        background: #e2e8f0;
        color: #2d3748;
        border: none;
        padding: 16px 24px;
        border-radius: 10px;
        font-size: 15px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .btn-reset:hover {
        background: #cbd5e0;
    }

    /* RESPONSIVE */
    @media (max-width: 768px) {
        .optimizer-header {
            flex-direction: column;
            align-items: flex-start;
            gap: 15px;
        }

        .box-summary {
            width: 100%;
        }

        .quick-size-buttons {
            grid-template-columns: repeat(2, 1fr);
        }

        .dimensions-grid {
            grid-template-columns: repeat(2, 1fr);
        }

        .optimizer-actions {
            flex-direction: column;
        }
    }
`;

document.head.appendChild(style);

// Auto-initialize if target element exists
document.addEventListener('DOMContentLoaded', () => {
    const container = document.getElementById('boxOptimizerContainer') ||
                     document.querySelector('[data-box-optimizer]');
    if (container && !window.staffBoxOptimizer) {
        // Configuration will be provided by PHP
        const config = window.boxOptimizerConfig || {};
        window.staffBoxOptimizer = new PremiumStaffBoxOptimizer(config);
    }
});
