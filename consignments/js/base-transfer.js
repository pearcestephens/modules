/**
 * BASE Transfer JavaScript - Enterprise Functionality
 * 
 * Handles input validation, auto-save, real-time calculations,
 * and Lightspeed queue integration for transfer processing.
 * 
 * @author CIS Development Team  
 * @version 1.0.0
 * @created 2025-10-12
 */

(function() {
    'use strict';

    // ========================================
    // CONFIGURATION & GLOBALS
    // ========================================
    const CONFIG = {
        AUTO_SAVE_INTERVAL: 2000,      // 2 seconds
        VALIDATION_DEBOUNCE: 300,      // 300ms
        MAX_QTY: 9999,                 // Maximum quantity allowed
        MIN_QTY: 0,                    // Minimum quantity
        API_TIMEOUT: 30000,            // 30 seconds
        RETRY_ATTEMPTS: 3,             // Retry failed requests
        WEIGHT_PRECISION: 2,           // Decimal places for weight
        BOX_WEIGHT_LIMIT: 5000         // 5kg per box limit
    };

    let state = {
        transferId: null,
        transferMode: null,
        autoSaveTimer: null,
        isAutoSaving: false,
        hasUnsavedChanges: false,
        validationErrors: new Map(),
        totalWeight: 0,
        estimatedBoxes: 0,
        lastSaveTime: null
    };

    // ========================================
    // INITIALIZATION
    // ========================================
    document.addEventListener('DOMContentLoaded', function() {
        initializeTransfer();
        bindEvents();
        startAutoSave();
        validateAllInputs();
        updateFreightSummary();
        
        console.log('✅ BASE Transfer System initialized');
    });

    function initializeTransfer() {
        // Extract transfer details from page
        const container = document.querySelector('.transfer-container');
        if (container) {
            state.transferId = container.dataset.transferId;
            state.transferMode = container.dataset.transferMode;
        }

        // Initialize loading states
        hideLoadingOverlay();
        
        // Set focus to first quantity input
        const firstQtyInput = document.querySelector('.qty-input');
        if (firstQtyInput) {
            firstQtyInput.focus();
        }
    }

    // ========================================
    // EVENT BINDING
    // ========================================
    function bindEvents() {
        // Quantity input events
        document.querySelectorAll('.qty-input').forEach(input => {
            input.addEventListener('input', handleQuantityChange);
            input.addEventListener('blur', handleQuantityBlur);
            input.addEventListener('keydown', handleQuantityKeydown);
            input.addEventListener('focus', handleQuantityFocus);
        });

        // Transfer action buttons
        const submitBtn = document.getElementById('submit-transfer');
        if (submitBtn) {
            submitBtn.addEventListener('click', handleSubmitTransfer);
        }

        const saveBtn = document.getElementById('save-transfer');
        if (saveBtn) {
            saveBtn.addEventListener('click', handleSaveTransfer);
        }

        const resetBtn = document.getElementById('reset-transfer');
        if (resetBtn) {
            resetBtn.addEventListener('click', handleResetTransfer);
        }

        // Keyboard shortcuts
        document.addEventListener('keydown', handleKeyboardShortcuts);

        // Window events
        window.addEventListener('beforeunload', handleBeforeUnload);
        
        // Auto-save indicator click
        const autoSaveIndicator = document.querySelector('.auto-save-indicator');
        if (autoSaveIndicator) {
            autoSaveIndicator.addEventListener('click', showSaveDetails);
        }
    }

    // ========================================
    // QUANTITY INPUT HANDLING
    // ========================================
    function handleQuantityChange(event) {
        const input = event.target;
        const row = input.closest('.transfer-item-row');
        
        // Clear any existing validation timer
        if (input.validationTimer) {
            clearTimeout(input.validationTimer);
        }

        // Debounced validation
        input.validationTimer = setTimeout(() => {
            validateQuantityInput(input);
            updateRowVisuals(row);
            updateFreightSummary();
            markUnsavedChanges();
        }, CONFIG.VALIDATION_DEBOUNCE);
    }

    function handleQuantityBlur(event) {
        const input = event.target;
        normalizeQuantityValue(input);
        validateQuantityInput(input);
    }

    function handleQuantityKeydown(event) {
        const input = event.target;
        
        // Handle special keys
        switch (event.key) {
            case 'Enter':
                event.preventDefault();
                moveToNextInput(input);
                break;
            case 'Escape':
                event.preventDefault();
                input.value = input.dataset.originalValue || '0';
                validateQuantityInput(input);
                break;
            case 'ArrowUp':
                event.preventDefault();
                incrementQuantity(input, 1);
                break;
            case 'ArrowDown':
                event.preventDefault();
                incrementQuantity(input, -1);
                break;
        }

        // Prevent invalid characters
        if (!isValidKeyForNumber(event.key)) {
            event.preventDefault();
        }
    }

    function handleQuantityFocus(event) {
        const input = event.target;
        input.dataset.originalValue = input.value;
        input.select(); // Select all text for easy replacement
    }

    function validateQuantityInput(input) {
        const value = parseFloat(input.value) || 0;
        const row = input.closest('.transfer-item-row');
        const itemId = row.dataset.itemId;
        const maxQty = parseFloat(input.max) || CONFIG.MAX_QTY;
        
        // Clear previous errors
        state.validationErrors.delete(itemId);
        
        // Validate range
        if (value < CONFIG.MIN_QTY) {
            addValidationError(itemId, `Quantity must be at least ${CONFIG.MIN_QTY}`);
            input.setCustomValidity('Invalid quantity');
        } else if (value > maxQty) {
            addValidationError(itemId, `Quantity cannot exceed ${maxQty}`);
            input.setCustomValidity('Exceeds maximum');
        } else if (value % 1 !== 0) {
            addValidationError(itemId, 'Quantity must be a whole number');
            input.setCustomValidity('Must be whole number');
        } else {
            input.setCustomValidity('');
        }
        
        return state.validationErrors.size === 0;
    }

    function normalizeQuantityValue(input) {
        let value = parseFloat(input.value) || 0;
        
        // Clamp to valid range
        const min = parseFloat(input.min) || CONFIG.MIN_QTY;
        const max = parseFloat(input.max) || CONFIG.MAX_QTY;
        value = Math.max(min, Math.min(max, Math.floor(value)));
        
        input.value = value;
    }

    function incrementQuantity(input, delta) {
        let value = parseFloat(input.value) || 0;
        value += delta;
        
        const min = parseFloat(input.min) || CONFIG.MIN_QTY;
        const max = parseFloat(input.max) || CONFIG.MAX_QTY;
        value = Math.max(min, Math.min(max, value));
        
        input.value = value;
        validateQuantityInput(input);
        updateRowVisuals(input.closest('.transfer-item-row'));
        updateFreightSummary();
        markUnsavedChanges();
    }

    function moveToNextInput(currentInput) {
        const allInputs = Array.from(document.querySelectorAll('.qty-input'));
        const currentIndex = allInputs.indexOf(currentInput);
        
        if (currentIndex < allInputs.length - 1) {
            allInputs[currentIndex + 1].focus();
        }
    }

    function isValidKeyForNumber(key) {
        // Allow: backspace, delete, tab, escape, enter, arrows
        if (['Backspace', 'Delete', 'Tab', 'Escape', 'Enter', 'ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown'].includes(key)) {
            return true;
        }
        
        // Allow: Ctrl+A, Ctrl+C, Ctrl+V, Ctrl+X, Ctrl+Z
        if (key.length === 1 && (event.ctrlKey || event.metaKey)) {
            return true;
        }
        
        // Allow: digits 0-9
        if (/^[0-9]$/.test(key)) {
            return true;
        }
        
        return false;
    }

    // ========================================
    // VISUAL UPDATES
    // ========================================
    function updateRowVisuals(row) {
        const input = row.querySelector('.qty-input');
        const qtyValue = parseFloat(input.value) || 0;
        const itemId = row.dataset.itemId;
        
        // Remove existing classes
        row.classList.remove('table-success', 'table-warning', 'table-secondary');
        
        // Apply visual state based on quantity and validation
        if (state.validationErrors.has(itemId)) {
            row.classList.add('table-danger');
            showRowError(row, state.validationErrors.get(itemId));
        } else if (qtyValue > 0) {
            row.classList.add('table-success');
            hideRowError(row);
        } else {
            row.classList.add('table-secondary');
            hideRowError(row);
        }
        
        // Update status indicator
        const statusIndicator = row.querySelector('.status-indicator');
        if (statusIndicator) {
            if (state.validationErrors.has(itemId)) {
                statusIndicator.innerHTML = '<i class="fas fa-exclamation-triangle text-danger"></i>';
            } else if (qtyValue > 0) {
                statusIndicator.innerHTML = '<i class="fas fa-check-circle text-success"></i>';
            } else {
                statusIndicator.innerHTML = '<i class="fas fa-circle text-muted"></i>';
            }
        }
    }

    function showRowError(row, message) {
        let errorDiv = row.querySelector('.row-error');
        if (!errorDiv) {
            errorDiv = document.createElement('div');
            errorDiv.className = 'row-error alert alert-danger alert-sm mt-2';
            errorDiv.style.fontSize = '0.8rem';
            row.querySelector('.product-cell').appendChild(errorDiv);
        }
        errorDiv.textContent = message;
        errorDiv.style.display = 'block';
    }

    function hideRowError(row) {
        const errorDiv = row.querySelector('.row-error');
        if (errorDiv) {
            errorDiv.style.display = 'none';
        }
    }

    function updateFreightSummary() {
        calculateTotals();
        
        // Update weight display
        const weightDisplay = document.querySelector('.total-weight-display');
        if (weightDisplay) {
            weightDisplay.textContent = `${(state.totalWeight / 1000).toFixed(CONFIG.WEIGHT_PRECISION)}kg`;
        }
        
        // Update box estimate
        const boxesDisplay = document.querySelector('.estimated-boxes-display');
        if (boxesDisplay) {
            boxesDisplay.textContent = state.estimatedBoxes;
        }
        
        // Update special requirements
        updateSpecialRequirements();
    }

    function calculateTotals() {
        let totalWeight = 0;
        let hasFragile = false;
        let hasNicotine = false;
        let totalItems = 0;
        
        document.querySelectorAll('.transfer-item-row').forEach(row => {
            const qtyInput = row.querySelector('.qty-input');
            const qty = parseFloat(qtyInput.value) || 0;
            
            if (qty > 0) {
                const weight = parseFloat(row.dataset.weightGrams) || 0;
                totalWeight += weight * qty;
                totalItems += qty;
                
                // Check for special requirements
                if (row.dataset.fragile === '1') hasFragile = true;
                if (row.dataset.nicotine === '1') hasNicotine = true;
            }
        });
        
        state.totalWeight = totalWeight;
        state.estimatedBoxes = Math.max(1, Math.ceil(totalWeight / CONFIG.BOX_WEIGHT_LIMIT));
        
        // Store for other functions
        state.hasFragile = hasFragile;
        state.hasNicotine = hasNicotine;
        state.totalItems = totalItems;
    }

    function updateSpecialRequirements() {
        const container = document.querySelector('.special-requirements');
        if (!container) return;
        
        let badges = [];
        
        if (state.hasFragile) {
            badges.push('<span class="badge bg-warning me-1"><i class="fas fa-exclamation-triangle"></i> Fragile</span>');
        }
        
        if (state.hasNicotine) {
            badges.push('<span class="badge bg-danger me-1"><i class="fas fa-ban"></i> Nicotine</span>');
        }
        
        if (state.totalWeight > 10000) { // > 10kg
            badges.push('<span class="badge bg-info me-1"><i class="fas fa-weight-hanging"></i> Heavy</span>');
        }
        
        if (badges.length === 0) {
            badges.push('<span class="badge bg-success"><i class="fas fa-check"></i> Standard</span>');
        }
        
        container.querySelector('.requirement-badge').innerHTML = badges.join('');
    }

    // ========================================
    // AUTO-SAVE FUNCTIONALITY
    // ========================================
    function startAutoSave() {
        if (state.autoSaveTimer) {
            clearInterval(state.autoSaveTimer);
        }
        
        state.autoSaveTimer = setInterval(() => {
            if (state.hasUnsavedChanges && !state.isAutoSaving) {
                performAutoSave();
            }
        }, CONFIG.AUTO_SAVE_INTERVAL);
    }

    function performAutoSave() {
        if (state.isAutoSaving) return;
        
        state.isAutoSaving = true;
        updateAutoSaveIndicator('saving');
        
        const saveData = collectTransferData();
        
        fetch('/modules/consignments/api/autosave.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify(saveData)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                state.hasUnsavedChanges = false;
                state.lastSaveTime = new Date();
                updateAutoSaveIndicator('saved');
            } else {
                updateAutoSaveIndicator('error');
                console.error('Auto-save failed:', data.error);
            }
        })
        .catch(error => {
            updateAutoSaveIndicator('error');
            console.error('Auto-save error:', error);
        })
        .finally(() => {
            state.isAutoSaving = false;
        });
    }

    function updateAutoSaveIndicator(status) {
        const indicator = document.querySelector('.auto-save-indicator');
        if (!indicator) return;
        
        indicator.classList.remove('saving', 'error');
        
        switch (status) {
            case 'saving':
                indicator.classList.add('saving');
                indicator.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
                break;
            case 'saved':
                indicator.innerHTML = '<i class="fas fa-check text-success"></i> Auto-saved ' + 
                    formatTimeAgo(state.lastSaveTime);
                break;
            case 'error':
                indicator.classList.add('error');
                indicator.innerHTML = '<i class="fas fa-exclamation-triangle text-danger"></i> Save failed';
                break;
        }
    }

    function markUnsavedChanges() {
        state.hasUnsavedChanges = true;
    }

    // ========================================
    // TRANSFER ACTIONS
    // ========================================
    function handleSubmitTransfer(event) {
        event.preventDefault();
        
        if (!validateAllInputs()) {
            showAlert('Please fix validation errors before submitting', 'error');
            return;
        }
        
        if (state.totalItems === 0) {
            showAlert('Please add some items to the transfer', 'warning');
            return;
        }
        
        showConfirmDialog(
            'Submit Transfer', 
            'Are you sure you want to submit this transfer? This will send it to Lightspeed for processing.',
            () => {
                submitToLightspeed();
            }
        );
    }

    function handleSaveTransfer(event) {
        event.preventDefault();
        performManualSave();
    }

    function handleResetTransfer(event) {
        event.preventDefault();
        
        showConfirmDialog(
            'Reset Transfer',
            'Are you sure you want to reset all quantities to zero? This cannot be undone.',
            () => {
                resetAllQuantities();
            }
        );
    }

    function submitToLightspeed() {
        showLoadingOverlay('Submitting to Lightspeed...');
        
        const submitData = collectTransferData();
        submitData.action = 'submit';
        
        fetch('/modules/consignments/api/pack_submit.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify(submitData)
        })
        .then(response => response.json())
        .then(data => {
            hideLoadingOverlay();
            
            if (data.success) {
                showAlert('Transfer submitted successfully!', 'success');
                
                // Redirect to success page or transfer list
                setTimeout(() => {
                    window.location.href = data.redirect_url || '/modules/consignments/';
                }, 2000);
            } else {
                showAlert('Submission failed: ' + data.error, 'error');
            }
        })
        .catch(error => {
            hideLoadingOverlay();
            showAlert('Network error: ' + error.message, 'error');
            console.error('Submit error:', error);
        });
    }

    function performManualSave() {
        const saveData = collectTransferData();
        
        fetch('/modules/consignments/api/autosave.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify(saveData)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showAlert('Transfer saved successfully', 'success');
                state.hasUnsavedChanges = false;
                state.lastSaveTime = new Date();
                updateAutoSaveIndicator('saved');
            } else {
                showAlert('Save failed: ' + data.error, 'error');
            }
        })
        .catch(error => {
            showAlert('Save error: ' + error.message, 'error');
        });
    }

    function resetAllQuantities() {
        document.querySelectorAll('.qty-input').forEach(input => {
            input.value = '0';
            validateQuantityInput(input);
            updateRowVisuals(input.closest('.transfer-item-row'));
        });
        
        updateFreightSummary();
        markUnsavedChanges();
        showAlert('All quantities reset to zero', 'info');
    }

    // ========================================
    // DATA COLLECTION
    // ========================================
    function collectTransferData() {
        const items = [];
        
        document.querySelectorAll('.transfer-item-row').forEach(row => {
            const qtyInput = row.querySelector('.qty-input');
            const qty = parseFloat(qtyInput.value) || 0;
            
            if (qty > 0) {
                items.push({
                    item_id: row.dataset.itemId,
                    product_id: row.dataset.productId,
                    quantity: qty,
                    weight_grams: parseFloat(row.dataset.weightGrams) || 0
                });
            }
        });
        
        return {
            transfer_id: state.transferId,
            transfer_mode: state.transferMode,
            items: items,
            totals: {
                weight_grams: state.totalWeight,
                estimated_boxes: state.estimatedBoxes,
                has_fragile: state.hasFragile,
                has_nicotine: state.hasNicotine,
                total_items: state.totalItems
            },
            timestamp: new Date().toISOString()
        };
    }

    function validateAllInputs() {
        let isValid = true;
        
        document.querySelectorAll('.qty-input').forEach(input => {
            if (!validateQuantityInput(input)) {
                isValid = false;
                updateRowVisuals(input.closest('.transfer-item-row'));
            }
        });
        
        return isValid;
    }

    // ========================================
    // KEYBOARD SHORTCUTS
    // ========================================
    function handleKeyboardShortcuts(event) {
        // Ctrl+S or Cmd+S = Save
        if ((event.ctrlKey || event.metaKey) && event.key === 's') {
            event.preventDefault();
            performManualSave();
        }
        
        // Ctrl+Enter or Cmd+Enter = Submit
        if ((event.ctrlKey || event.metaKey) && event.key === 'Enter') {
            event.preventDefault();
            handleSubmitTransfer(event);
        }
        
        // Escape = Reset focus
        if (event.key === 'Escape') {
            document.activeElement.blur();
        }
    }

    // ========================================
    // UI HELPERS
    // ========================================
    function showLoadingOverlay(message = 'Loading...') {
        let overlay = document.querySelector('.loading-overlay');
        if (!overlay) {
            overlay = document.createElement('div');
            overlay.className = 'loading-overlay';
            overlay.innerHTML = `
                <div class="loading-content">
                    <div class="loading-spinner mb-3"></div>
                    <div class="loading-message">${message}</div>
                </div>
            `;
            document.body.appendChild(overlay);
        } else {
            overlay.querySelector('.loading-message').textContent = message;
            overlay.style.display = 'flex';
        }
    }

    function hideLoadingOverlay() {
        const overlay = document.querySelector('.loading-overlay');
        if (overlay) {
            overlay.style.display = 'none';
        }
    }

    function showAlert(message, type = 'info') {
        // Create toast notification
        const toast = document.createElement('div');
        toast.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
        toast.style.cssText = 'top: 20px; right: 20px; z-index: 10000; min-width: 300px;';
        toast.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        document.body.appendChild(toast);
        
        // Auto-remove after 5 seconds
        setTimeout(() => {
            if (toast.parentNode) {
                toast.parentNode.removeChild(toast);
            }
        }, 5000);
    }

    function showConfirmDialog(title, message, onConfirm) {
        if (confirm(`${title}\n\n${message}`)) {
            onConfirm();
        }
    }

    function showSaveDetails() {
        const lastSave = state.lastSaveTime ? 
            `Last saved: ${state.lastSaveTime.toLocaleString()}` : 
            'No recent saves';
        
        showAlert(lastSave, 'info');
    }

    // ========================================
    // UTILITY FUNCTIONS
    // ========================================
    function formatTimeAgo(date) {
        if (!date) return '';
        
        const now = new Date();
        const diff = now - date;
        const seconds = Math.floor(diff / 1000);
        const minutes = Math.floor(seconds / 60);
        
        if (seconds < 60) return `${seconds}s ago`;
        if (minutes < 60) return `${minutes}m ago`;
        return date.toLocaleTimeString();
    }

    function addValidationError(itemId, message) {
        state.validationErrors.set(itemId, message);
    }

    function handleBeforeUnload(event) {
        if (state.hasUnsavedChanges) {
            event.preventDefault();
            event.returnValue = 'You have unsaved changes. Are you sure you want to leave?';
            return event.returnValue;
        }
    }

    // ========================================
    // EXPORT PUBLIC API (for debugging/testing)
    // ========================================
    window.BaseTransfer = {
        getState: () => ({ ...state }),
        validateAll: validateAllInputs,
        save: performManualSave,
        reset: resetAllQuantities,
        calculateTotals: calculateTotals
    };

})();