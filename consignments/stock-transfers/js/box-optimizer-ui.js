/**
 * Box Optimizer UI Integration
 *
 * Real-time box optimization suggestions as users enter dimensions.
 * Integrates with pack.js and provides visual feedback.
 *
 * Features:
 * - Real-time validation as dimensions are entered
 * - Suggestions displayed in toast/modal
 * - One-click actions for consolidation/carrier switch
 * - Historical learning from past shipments
 * - Live cost preview updates
 *
 * @file box-optimizer-ui.js
 * @version 1.0.0
 */

class BoxOptimizerUI {
    constructor(options = {}) {
        this.apiEndpoint = options.apiEndpoint || '/modules/consignments/api/box-optimizer.php';
        this.carrier = options.carrier || 'nz_courier';
        this.transferId = options.transferId || null;
        this.debounceDelay = options.debounceDelay || 800; // ms
        this.debounceTimers = new Map();
        this.boxes = [];
        this.suggestions = [];
        this.init();
    }

    init() {
        console.log('[BoxOptimizer] Initializing UI integration');

        // Listen for dimension input changes
        this.setupDimensionListeners();

        // Listen for carrier selection changes
        this.setupCarrierListener();

        // Log when page loads
        console.log('[BoxOptimizer] Ready. Listening for box dimension inputs...');
    }

    /**
     * Setup listeners on dimension input fields
     */
    setupDimensionListeners() {
        const inputs = document.querySelectorAll(
            'input[data-box-dimension],' +
            'input[name*="length"],' +
            'input[name*="width"],' +
            'input[name*="height"],' +
            'input[name*="weight"]'
        );

        inputs.forEach(input => {
            input.addEventListener('change', (e) => this.onDimensionChange(e));
            input.addEventListener('blur', (e) => this.onDimensionChange(e));
        });
    }

    /**
     * Setup carrier selection listener
     */
    setupCarrierListener() {
        const carrierSelect = document.querySelector(
            'select[name="carrier"],' +
            'select[data-carrier],' +
            '#carrier-select'
        );

        if (carrierSelect) {
            carrierSelect.addEventListener('change', (e) => {
                this.carrier = e.target.value;
                this.analyzeCurrentBoxes();
            });
        }
    }

    /**
     * Handle dimension change - debounced
     */
    onDimensionChange(event) {
        const boxContainer = event.target.closest('[data-box-id]') ||
                           event.target.closest('.box-entry-form') ||
                           event.target.closest('form');

        if (!boxContainer) return;

        const boxId = boxContainer.dataset.boxId || 'current';

        // Cancel previous timer
        if (this.debounceTimers.has(boxId)) {
            clearTimeout(this.debounceTimers.get(boxId));
        }

        // Set new timer
        const timer = setTimeout(() => {
            this.analyzeCurrentBoxes();
            this.debounceTimers.delete(boxId);
        }, this.debounceDelay);

        this.debounceTimers.set(boxId, timer);
    }

    /**
     * Analyze all currently entered boxes
     */
    async analyzeCurrentBoxes() {
        const boxes = this.extractBoxDataFromForm();

        if (boxes.length === 0) {
            return;
        }

        try {
            const response = await fetch(this.apiEndpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: boxes.length > 1 ? 'analyze_multiple' : 'analyze_box',
                    boxes: boxes,
                    carrier: this.carrier,
                    transfer_id: this.transferId,
                }),
            });

            if (!response.ok) {
                console.error('[BoxOptimizer] API error:', response.statusText);
                return;
            }

            const data = await response.json();

            if (!data.success) {
                console.error('[BoxOptimizer] API returned error:', data.error);
                return;
            }

            this.handleAnalysisResult(data.data);
        } catch (error) {
            console.error('[BoxOptimizer] Fetch error:', error);
        }
    }

    /**
     * Extract box data from form fields
     */
    extractBoxDataFromForm() {
        const boxes = [];

        // Try to find box containers
        const boxContainers = document.querySelectorAll('[data-box-id]');

        if (boxContainers.length > 0) {
            // Multiple boxes format
            boxContainers.forEach(container => {
                const box = this.extractBoxFromContainer(container);
                if (box) boxes.push(box);
            });
        } else {
            // Single box format - look for individual inputs
            const box = this.extractBoxFromForm(document);
            if (box) boxes.push(box);
        }

        return boxes.filter(box => box && box.length && box.width && box.height && box.weight);
    }

    /**
     * Extract box data from a container
     */
    extractBoxFromContainer(container) {
        const length = this.parseInput(container, 'length');
        const width = this.parseInput(container, 'width');
        const height = this.parseInput(container, 'height');
        const weight = this.parseInput(container, 'weight');

        if (length && width && height && weight) {
            return { length, width, height, weight };
        }

        return null;
    }

    /**
     * Extract box data from form
     */
    extractBoxFromForm(form) {
        const length = this.parseInput(form, 'length');
        const width = this.parseInput(form, 'width');
        const height = this.parseInput(form, 'height');
        const weight = this.parseInput(form, 'weight');

        if (length && width && height && weight) {
            return { length, width, height, weight };
        }

        return null;
    }

    /**
     * Parse input value from form
     */
    parseInput(container, fieldName) {
        // Try various selector patterns
        const selectors = [
            `input[name*="${fieldName}"]`,
            `input[data-field="${fieldName}"]`,
            `input[placeholder*="${fieldName}"]`,
            `.${fieldName}-input`,
        ];

        for (let selector of selectors) {
            const input = container.querySelector(selector);
            if (input) {
                const value = parseFloat(input.value);
                if (!isNaN(value) && value > 0) {
                    return value;
                }
            }
        }

        return null;
    }

    /**
     * Handle analysis result and display suggestions
     */
    handleAnalysisResult(analysis) {
        // Display validations (errors)
        if (analysis.validations && analysis.validations.length > 0) {
            this.showValidationErrors(analysis.validations);
        }

        // Display warnings
        if (analysis.warnings && analysis.warnings.length > 0) {
            this.showWarnings(analysis.warnings);
        }

        // Display suggestions
        if (analysis.suggestions && analysis.suggestions.length > 0) {
            this.showSuggestions(analysis.suggestions);
        }

        // Update cost estimate if available
        if (analysis.metrics) {
            this.updateCostEstimate(analysis.metrics);
        }

        // Store for later use
        this.suggestions = analysis.suggestions || [];
    }

    /**
     * Show validation errors
     */
    showValidationErrors(errors) {
        const container = document.querySelector('[data-optimization-errors]') ||
                         this.createAlertContainer();

        container.innerHTML = '';

        errors.forEach(error => {
            const div = document.createElement('div');
            div.className = 'alert alert-danger alert-dismissible fade show';
            div.innerHTML = `
                <i class="bi bi-exclamation-circle"></i>
                <strong>Validation Error:</strong> ${error}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            container.appendChild(div);
        });
    }

    /**
     * Show warnings
     */
    showWarnings(warnings) {
        const container = document.querySelector('[data-optimization-warnings]') ||
                         this.createAlertContainer();

        warnings.forEach(warning => {
            // Skip if already shown
            if (document.querySelector(`[data-warning-id="${warning.type}"]`)) {
                return;
            }

            const div = document.createElement('div');
            div.className = 'alert alert-warning alert-dismissible fade show';
            div.dataset.warningId = warning.type;
            div.innerHTML = `
                <i class="bi bi-exclamation-triangle"></i>
                <strong>Warning:</strong> ${warning.message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            container.appendChild(div);
        });
    }

    /**
     * Show optimization suggestions
     */
    showSuggestions(suggestions) {
        const container = document.querySelector('[data-optimization-suggestions]') ||
                         this.createSuggestionsContainer();

        container.innerHTML = '';

        suggestions.forEach((suggestion, idx) => {
            const div = document.createElement('div');
            div.className = 'card mb-2 border-success';
            div.style.backgroundColor = '#f0fdf4';

            let content = `
                <div class="card-body p-3">
                    <div style="display: flex; justify-content: space-between; align-items: start;">
                        <div style="flex: 1;">
                            <h6 class="card-title mb-2">
                                <i class="bi bi-lightbulb text-warning"></i>
                                ${this.getSuggestionTitle(suggestion.type)}
                            </h6>
                            <p class="card-text small mb-2" style="color: #4b5563;">
                                ${suggestion.description}
                            </p>
            `;

            // Add savings highlight if applicable
            if (suggestion.savings) {
                content += `
                    <div style="background: #dcfce7; padding: 8px 12px; border-radius: 4px; display: inline-block;">
                        <strong style="color: #15803d; font-size: 14px;">
                            💰 Save: $${suggestion.savings.toFixed(2)}
                        </strong>
                    </div>
                `;
            }

            // Add action button
            if (suggestion.type === 'consolidation') {
                content += `
                    <br/><br/>
                    <button class="btn btn-sm btn-outline-success" onclick="boxOptimizer.applyConsolidation(${idx})">
                        <i class="bi bi-check-circle"></i> Apply Consolidation
                    </button>
                `;
            } else if (suggestion.type === 'smaller_box') {
                content += `
                    <br/><br/>
                    <button class="btn btn-sm btn-outline-success" onclick="boxOptimizer.applySmallerBox(${idx})">
                        <i class="bi bi-check-circle"></i> Use Smaller Box
                    </button>
                `;
            } else if (suggestion.type === 'carrier_switch') {
                content += `
                    <br/><br/>
                    <button class="btn btn-sm btn-outline-success" onclick="boxOptimizer.switchCarrier('${suggestion.to_carrier}')">
                        <i class="bi bi-check-circle"></i> Switch to ${suggestion.to_carrier}
                    </button>
                `;
            }

            content += `
                        </div>
                        <div style="font-size: 12px; color: #999; min-width: 60px; text-align: right;">
                            Confidence: ${(suggestion.confidence * 100).toFixed(0)}%
                        </div>
                    </div>
                </div>
            `;

            div.innerHTML = content;
            container.appendChild(div);
        });

        // Show container if not visible
        container.style.display = suggestions.length > 0 ? 'block' : 'none';
    }

    /**
     * Get human-readable suggestion title
     */
    getSuggestionTitle(type) {
        const titles = {
            'consolidation': 'Consolidation Opportunity',
            'smaller_box': 'Smaller Box Available',
            'tier_crossing': 'Weight Tier Alert',
            'carrier_switch': 'Carrier Switch Recommended',
        };
        return titles[type] || 'Optimization Suggestion';
    }

    /**
     * Update cost estimate display
     */
    updateCostEstimate(metrics) {
        const display = document.querySelector('[data-cost-estimate]') ||
                       document.querySelector('.cost-estimate');

        if (!display) return;

        const util = metrics.utilization_pct || 0;
        const utilColor = util < 25 ? '#ef4444' : util < 60 ? '#f59e0b' : '#10b981';

        display.innerHTML = `
            <div style="padding: 12px; background: #f9fafb; border-radius: 6px;">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; font-size: 12px;">
                    <div>
                        <div style="color: #999; margin-bottom: 4px;">Weight</div>
                        <div style="font-weight: 600; font-size: 16px;">${metrics.weight_kg.toFixed(1)} kg</div>
                    </div>
                    <div>
                        <div style="color: #999; margin-bottom: 4px;">Volume</div>
                        <div style="font-weight: 600; font-size: 16px;">${(metrics.volume_m3 * 1000).toFixed(0)} L</div>
                    </div>
                    <div colspan="2">
                        <div style="color: #999; margin-bottom: 4px;">Utilization</div>
                        <div style="background: #e5e7eb; height: 6px; border-radius: 3px; overflow: hidden;">
                            <div style="background: ${utilColor}; height: 100%; width: ${util}%;"></div>
                        </div>
                        <div style="font-size: 11px; margin-top: 4px;">
                            ${util.toFixed(0)}% of ideal capacity
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    /**
     * Create alert container if doesn't exist
     */
    createAlertContainer() {
        const container = document.createElement('div');
        container.setAttribute('data-optimization-alerts', '');
        container.style.marginBottom = '20px';

        // Insert after header or at top
        const header = document.querySelector('.page-header') ||
                      document.querySelector('h1') ||
                      document.querySelector('form');

        if (header && header.parentElement) {
            header.parentElement.insertBefore(container, header.nextElementSibling);
        } else {
            document.body.insertBefore(container, document.body.firstChild);
        }

        return container;
    }

    /**
     * Create suggestions container if doesn't exist
     */
    createSuggestionsContainer() {
        const container = document.createElement('div');
        container.setAttribute('data-optimization-suggestions', '');
        container.style.marginBottom = '20px';
        container.style.marginTop = '20px';

        // Insert before submit button or at end of form
        const form = document.querySelector('form') ||
                    document.querySelector('[role="main"]');

        if (form) {
            const submit = form.querySelector('button[type="submit"]') ||
                         form.querySelector('.btn-primary');

            if (submit) {
                submit.parentElement.insertBefore(container, submit);
            } else {
                form.appendChild(container);
            }
        } else {
            document.body.appendChild(container);
        }

        return container;
    }

    /**
     * Apply consolidation suggestion
     */
    applyConsolidation(suggestionIndex) {
        const suggestion = this.suggestions[suggestionIndex];
        if (!suggestion) return;

        alert(`Consolidating boxes:\n\n${suggestion.description}\n\nSaving: $${suggestion.savings.toFixed(2)}`);
        // TODO: Implement actual consolidation logic
    }

    /**
     * Apply smaller box suggestion
     */
    applySmallerBox(suggestionIndex) {
        const suggestion = this.suggestions[suggestionIndex];
        if (!suggestion) return;

        // Find the dimension inputs and update them
        const lengthInput = document.querySelector('input[name*="length"]');
        const widthInput = document.querySelector('input[name*="width"]');
        const heightInput = document.querySelector('input[name*="height"]');

        if (lengthInput && widthInput && heightInput) {
            lengthInput.value = suggestion.new_box.length;
            widthInput.value = suggestion.new_box.width;
            heightInput.value = suggestion.new_box.height;

            // Trigger change event
            lengthInput.dispatchEvent(new Event('change'));

            alert(`Box dimensions updated to ${suggestion.new_box.length}×${suggestion.new_box.width}×${suggestion.new_box.height}cm\n\nSaving: $${suggestion.savings.toFixed(2)}`);
        }
    }

    /**
     * Switch carrier
     */
    switchCarrier(newCarrier) {
        const carrierSelect = document.querySelector('select[name="carrier"]') ||
                            document.querySelector('[data-carrier]') ||
                            document.querySelector('#carrier-select');

        if (carrierSelect) {
            carrierSelect.value = newCarrier;
            carrierSelect.dispatchEvent(new Event('change'));
            alert(`Switched to ${newCarrier.toUpperCase()}`);
        }
    }
}

// Auto-initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    if (typeof boxOptimizer === 'undefined') {
        window.boxOptimizer = new BoxOptimizerUI({
            transferId: document.querySelector('[data-transfer-id]')?.dataset.transferId,
        });
    }
});
