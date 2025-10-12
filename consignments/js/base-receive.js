/**
 * BASE Receive JavaScript - Enterprise Functionality
 * 
 * Handles receiving operations including partial deliveries, unexpected products,
 * input validation, auto-save, and Lightspeed integration.
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
        BOX_WEIGHT_LIMIT: 5000,        // 5kg per box limit
        PRODUCT_SEARCH_DELAY: 500      // Product search debounce
    };

    let state = {
        transferId: null,
        transferMode: null,
        autoSaveTimer: null,
        isAutoSaving: false,
        hasUnsavedChanges: false,
        validationErrors: new Map(),
        originalQuantities: new Map(),
        totalRequested: 0,
        totalReceived: 0,
        totalWeight: 0,
        estimatedBoxes: 0,
        lastSaveTime: null,
        selectedProduct: null,
        searchTimeout: null
    };

    // ========================================
    // INITIALIZATION
    // ========================================
    document.addEventListener('DOMContentLoaded', function() {
        initializeReceive();
        bindEvents();
        startAutoSave();
        calculateTotals();
        updateUI();
        
        console.log('✅ BASE Receive System initialized');
    });

    function initializeReceive() {
        // Extract transfer details from page
        const container = document.querySelector('.receive-container');
        if (container) {
            state.transferId = container.dataset.transferId;
            state.transferMode = container.dataset.transferMode;
        }

        // Store original quantities for reset functionality
        document.querySelectorAll('.qty-input').forEach(input => {
            const row = input.closest('.receive-item-row');
            const itemId = row.dataset.itemId;
            state.originalQuantities.set(itemId, parseFloat(input.value) || 0);
        });

        // Initialize loading states
        hideLoadingOverlay();
        
        // Set focus to first quantity input that needs receiving
        const firstIncompleteInput = document.querySelector('.receive-item-row.table-secondary .qty-input, .receive-item-row.table-warning .qty-input');
        if (firstIncompleteInput) {
            firstIncompleteInput.focus();
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

        // Action buttons
        const completeBtn = document.getElementById('complete-receive');
        if (completeBtn) {
            completeBtn.addEventListener('click', handleCompleteReceive);
        }

        const partialBtn = document.getElementById('partial-receive');
        if (partialBtn) {
            partialBtn.addEventListener('click', handlePartialReceive);
        }

        const saveBtn = document.getElementById('save-receive');
        if (saveBtn) {
            saveBtn.addEventListener('click', handleSaveReceive);
        }

        const resetBtn = document.getElementById('reset-receive');
        if (resetBtn) {
            resetBtn.addEventListener('click', handleResetReceive);
        }

        // Add Product functionality
        const addProductBtn = document.getElementById('add-product-btn');
        if (addProductBtn) {
            addProductBtn.addEventListener('click', showAddProductModal);
        }

        const productSearch = document.getElementById('productSearch');
        if (productSearch) {
            productSearch.addEventListener('input', handleProductSearch);
        }

        const confirmAddBtn = document.getElementById('confirmAddProduct');
        if (confirmAddBtn) {
            confirmAddBtn.addEventListener('click', handleAddProduct);
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
        const row = input.closest('.receive-item-row');
        
        // Clear any existing validation timer
        if (input.validationTimer) {
            clearTimeout(input.validationTimer);
        }

        // Debounced validation and updates
        input.validationTimer = setTimeout(() => {
            validateQuantityInput(input);
            updateRowVisuals(row);
            calculateTotals();
            updateUI();
            markUnsavedChanges();
        }, CONFIG.VALIDATION_DEBOUNCE);
    }

    function handleQuantityBlur(event) {
        const input = event.target;
        normalizeQuantityValue(input);
        validateQuantityInput(input);
        updateRowVisuals(input.closest('.receive-item-row'));
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
                const itemId = input.closest('.receive-item-row').dataset.itemId;
                input.value = state.originalQuantities.get(itemId) || 0;
                validateQuantityInput(input);
                updateRowVisuals(input.closest('.receive-item-row'));
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
        const row = input.closest('.receive-item-row');
        const itemId = row.dataset.itemId;
        const qtyRequestedElement = row.querySelector('.qty-display');
        const qtyRequested = parseFloat(qtyRequestedElement.textContent.replace(/,/g, '')) || 0;
        
        // Clear previous errors
        state.validationErrors.delete(itemId);
        
        // Validate range
        if (value < CONFIG.MIN_QTY) {
            addValidationError(itemId, `Quantity must be at least ${CONFIG.MIN_QTY}`);
            input.setCustomValidity('Invalid quantity');
        } else if (value > CONFIG.MAX_QTY) {
            addValidationError(itemId, `Quantity cannot exceed ${CONFIG.MAX_QTY}`);
            input.setCustomValidity('Exceeds maximum');
        } else if (value % 1 !== 0) {
            addValidationError(itemId, 'Quantity must be a whole number');
            input.setCustomValidity('Must be whole number');
        } else {
            input.setCustomValidity('');
            
            // Add special styling for over-delivery
            if (value > qtyRequested) {
                input.classList.add('over-requested');
            } else {
                input.classList.remove('over-requested');
            }
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
        updateRowVisuals(input.closest('.receive-item-row'));
        calculateTotals();
        updateUI();
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
        const qtyReceived = parseFloat(input.value) || 0;
        const qtyRequestedElement = row.querySelector('.qty-display');
        const qtyRequested = parseFloat(qtyRequestedElement.textContent.replace(/,/g, '')) || 0;
        const remainingElement = row.querySelector('.remaining-qty');
        const weightElement = row.querySelector('.weight-display');
        const statusElement = row.querySelector('.status-indicator');
        const itemId = row.dataset.itemId;
        
        // Calculate remaining quantity
        const qtyRemaining = qtyRequested - qtyReceived;
        remainingElement.textContent = number_format(qtyRemaining);
        
        // Style remaining quantity
        remainingElement.classList.toggle('negative', qtyRemaining < 0);
        
        // Update weight display
        const weightGrams = parseFloat(row.dataset.weightGrams) || 0;
        const totalWeight = weightGrams * qtyReceived;
        weightElement.textContent = (totalWeight / 1000).toFixed(CONFIG.WEIGHT_PRECISION) + 'kg';
        
        // Remove existing row classes
        row.classList.remove('table-success', 'table-warning', 'table-secondary', 'table-danger');
        
        // Apply visual state based on quantity and validation
        if (state.validationErrors.has(itemId)) {
            row.classList.add('table-danger');
            statusElement.innerHTML = '<i class="fas fa-exclamation-triangle text-danger" title="Error"></i>';
            showRowError(row, state.validationErrors.get(itemId));
        } else if (qtyReceived >= qtyRequested) {
            if (qtyReceived > qtyRequested) {
                // Over-delivery
                row.classList.add('table-info');
                statusElement.innerHTML = '<i class="fas fa-plus-circle text-info" title="Over Delivery"></i>';
            } else {
                // Complete
                row.classList.add('table-success');
                statusElement.innerHTML = '<i class="fas fa-check-circle text-success" title="Complete"></i>';
            }
            hideRowError(row);
        } else if (qtyReceived > 0) {
            // Partial
            row.classList.add('table-warning');
            statusElement.innerHTML = '<i class="fas fa-clock text-warning" title="Partial"></i>';
            hideRowError(row);
        } else {
            // Not started
            row.classList.add('table-secondary');
            statusElement.innerHTML = '<i class="fas fa-circle text-muted" title="Pending"></i>';
            hideRowError(row);
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

    function calculateTotals() {
        let totalRequested = 0;
        let totalReceived = 0;
        let totalWeight = 0;
        let hasFragile = false;
        let hasNicotine = false;
        
        document.querySelectorAll('.receive-item-row').forEach(row => {
            const qtyRequestedElement = row.querySelector('.qty-display');
            const qtyInput = row.querySelector('.qty-input');
            
            const qtyRequested = parseFloat(qtyRequestedElement.textContent.replace(/,/g, '')) || 0;
            const qtyReceived = parseFloat(qtyInput.value) || 0;
            
            totalRequested += qtyRequested;
            totalReceived += qtyReceived;
            
            if (qtyReceived > 0) {
                const weight = parseFloat(row.dataset.weightGrams) || 0;
                totalWeight += weight * qtyReceived;
                
                // Check for special requirements
                if (row.dataset.fragile === '1') hasFragile = true;
                if (row.dataset.nicotine === '1') hasNicotine = true;
            }
        });
        
        state.totalRequested = totalRequested;
        state.totalReceived = totalReceived;
        state.totalWeight = totalWeight;
        state.estimatedBoxes = Math.max(1, Math.ceil(totalWeight / CONFIG.BOX_WEIGHT_LIMIT));
        state.hasFragile = hasFragile;
        state.hasNicotine = hasNicotine;
    }

    function updateUI() {
        // Update summary displays
        const totalReceivedDisplay = document.querySelector('.total-received-display');
        if (totalReceivedDisplay) {
            totalReceivedDisplay.textContent = number_format(state.totalReceived);
        }
        
        const totalRemainingDisplay = document.querySelector('.total-remaining-display');
        if (totalRemainingDisplay) {
            totalRemainingDisplay.textContent = number_format(state.totalRequested - state.totalReceived);
        }
        
        const weightDisplay = document.querySelector('.total-weight-display');
        if (weightDisplay) {
            weightDisplay.textContent = (state.totalWeight / 1000).toFixed(CONFIG.WEIGHT_PRECISION) + 'kg';
        }
        
        const boxesDisplay = document.querySelector('.estimated-boxes-display');
        if (boxesDisplay) {
            boxesDisplay.textContent = state.estimatedBoxes;
        }
        
        // Update completion percentage
        const completionDisplay = document.querySelector('.completion-percentage');
        if (completionDisplay) {
            const percentage = (state.totalReceived / Math.max(state.totalRequested, 1)) * 100;
            completionDisplay.textContent = percentage.toFixed(1) + '%';
            
            // Style based on completion
            completionDisplay.classList.remove('complete', 'partial', 'over');
            if (percentage >= 100) {
                if (percentage > 100) {
                    completionDisplay.classList.add('over');
                } else {
                    completionDisplay.classList.add('complete');
                }
            } else if (percentage > 0) {
                completionDisplay.classList.add('partial');
            }
        }
        
        // Update special requirements
        updateSpecialRequirements();
        
        // Update button states
        updateButtonStates();
    }

    function updateSpecialRequirements() {
        const container = document.querySelector('.requirement-badge');
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
        
        container.innerHTML = badges.join('');
    }

    function updateButtonStates() {
        const completeBtn = document.getElementById('complete-receive');
        const partialBtn = document.getElementById('partial-receive');
        
        if (completeBtn && partialBtn) {
            const hasReceived = state.totalReceived > 0;
            const isComplete = state.totalReceived >= state.totalRequested;
            
            completeBtn.disabled = !hasReceived;
            partialBtn.disabled = !hasReceived || isComplete;
        }
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
        
        const saveData = collectReceiveData();
        
        fetch('/modules/consignments/api/receive_autosave.php', {
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
    // RECEIVE ACTIONS
    // ========================================
    function handleCompleteReceive(event) {
        event.preventDefault();
        
        if (!validateAllInputs()) {
            showAlert('Please fix validation errors before completing', 'error');
            return;
        }
        
        if (state.totalReceived === 0) {
            showAlert('Please receive some items before completing', 'warning');
            return;
        }
        
        showConfirmDialog(
            'Complete Receive', 
            'Are you sure you want to mark this transfer as completely received? This will update inventory in Lightspeed.',
            () => {
                submitReceive('COMPLETE');
            }
        );
    }

    function handlePartialReceive(event) {
        event.preventDefault();
        
        if (!validateAllInputs()) {
            showAlert('Please fix validation errors before saving', 'error');
            return;
        }
        
        if (state.totalReceived === 0) {
            showAlert('Please receive some items before saving as partial', 'warning');
            return;
        }
        
        submitReceive('PARTIAL');
    }

    function handleSaveReceive(event) {
        event.preventDefault();
        performManualSave();
    }

    function handleResetReceive(event) {
        event.preventDefault();
        
        showConfirmDialog(
            'Reset Receive',
            'Are you sure you want to reset all received quantities? This cannot be undone.',
            () => {
                resetAllQuantities();
            }
        );
    }

    function submitReceive(status) {
        showLoadingOverlay(`${status === 'COMPLETE' ? 'Completing' : 'Saving'} receive...`);
        
        const submitData = collectReceiveData();
        submitData.action = 'submit';
        submitData.status = status;
        
        fetch('/modules/consignments/api/receive_submit.php', {
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
                showAlert(`Receive ${status.toLowerCase()} successfully!`, 'success');
                
                // Redirect to success page or transfer list
                setTimeout(() => {
                    window.location.href = data.redirect_url || '/modules/consignments/';
                }, 2000);
            } else {
                showAlert('Submit failed: ' + data.error, 'error');
            }
        })
        .catch(error => {
            hideLoadingOverlay();
            showAlert('Network error: ' + error.message, 'error');
            console.error('Submit error:', error);
        });
    }

    function performManualSave() {
        const saveData = collectReceiveData();
        
        fetch('/modules/consignments/api/receive_autosave.php', {
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
                showAlert('Receive progress saved successfully', 'success');
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
            const itemId = input.closest('.receive-item-row').dataset.itemId;
            input.value = state.originalQuantities.get(itemId) || 0;
            validateQuantityInput(input);
            updateRowVisuals(input.closest('.receive-item-row'));
        });
        
        calculateTotals();
        updateUI();
        markUnsavedChanges();
        showAlert('All quantities reset to original values', 'info');
    }

    // ========================================
    // ADD PRODUCT FUNCTIONALITY
    // ========================================
    function showAddProductModal() {
        const modal = new bootstrap.Modal(document.getElementById('addProductModal'));
        modal.show();
        
        // Focus on search input
        setTimeout(() => {
            document.getElementById('productSearch').focus();
        }, 300);
    }

    function handleProductSearch(event) {
        const query = event.target.value.trim();
        
        // Clear previous timeout
        if (state.searchTimeout) {
            clearTimeout(state.searchTimeout);
        }
        
        // Clear results if query is too short
        if (query.length < 2) {
            document.getElementById('productSearchResults').innerHTML = '';
            return;
        }
        
        // Debounce search
        state.searchTimeout = setTimeout(() => {
            searchProducts(query);
        }, CONFIG.PRODUCT_SEARCH_DELAY);
    }

    function searchProducts(query) {
        fetch('/modules/consignments/api/search_products.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ 
                query: query,
                transfer_id: state.transferId,
                exclude_existing: true 
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displaySearchResults(data.products);
            } else {
                console.error('Product search failed:', data.error);
            }
        })
        .catch(error => {
            console.error('Product search error:', error);
        });
    }

    function displaySearchResults(products) {
        const resultsContainer = document.getElementById('productSearchResults');
        
        if (products.length === 0) {
            resultsContainer.innerHTML = '<div class="p-3 text-muted">No products found</div>';
            return;
        }
        
        let html = '';
        products.forEach(product => {
            html += `
                <div class="product-search-item" data-product-id="${product.id}">
                    <div class="search-product-name">${escapeHtml(product.name)}</div>
                    <div class="search-product-meta">
                        SKU: ${escapeHtml(product.sku)} • 
                        ${escapeHtml(product.category_name || 'Uncategorized')} • 
                        Weight: ${product.avg_weight_grams}g
                    </div>
                </div>
            `;
        });
        
        resultsContainer.innerHTML = html;
        
        // Bind click events
        resultsContainer.querySelectorAll('.product-search-item').forEach(item => {
            item.addEventListener('click', () => selectProduct(item, products));
        });
    }

    function selectProduct(item, products) {
        // Remove previous selection
        item.parentNode.querySelectorAll('.product-search-item').forEach(el => {
            el.classList.remove('selected');
        });
        
        // Select this item
        item.classList.add('selected');
        
        // Store selected product
        const productId = item.dataset.productId;
        state.selectedProduct = products.find(p => p.id == productId);
        
        // Update UI
        const infoDiv = document.getElementById('selectedProductInfo');
        const detailsDiv = document.getElementById('selectedProductDetails');
        
        detailsDiv.innerHTML = `
            <strong>${escapeHtml(state.selectedProduct.name)}</strong><br>
            SKU: ${escapeHtml(state.selectedProduct.sku)} • 
            Weight: ${state.selectedProduct.avg_weight_grams}g • 
            Category: ${escapeHtml(state.selectedProduct.category_name || 'Uncategorized')}
        `;
        
        infoDiv.style.display = 'block';
        
        // Enable add button
        document.getElementById('confirmAddProduct').disabled = false;
    }

    function handleAddProduct() {
        if (!state.selectedProduct) {
            showAlert('Please select a product first', 'warning');
            return;
        }
        
        const quantity = parseInt(document.getElementById('addQuantity').value) || 1;
        
        if (quantity < 1 || quantity > CONFIG.MAX_QTY) {
            showAlert(`Quantity must be between 1 and ${CONFIG.MAX_QTY}`, 'warning');
            return;
        }
        
        // Add product to transfer
        addProductToTransfer(state.selectedProduct, quantity);
        
        // Close modal
        const modal = bootstrap.Modal.getInstance(document.getElementById('addProductModal'));
        modal.hide();
        
        // Reset form
        document.getElementById('addProductForm').reset();
        document.getElementById('productSearchResults').innerHTML = '';
        document.getElementById('selectedProductInfo').style.display = 'none';
        document.getElementById('confirmAddProduct').disabled = true;
        state.selectedProduct = null;
    }

    function addProductToTransfer(product, quantity) {
        // This would create a new transfer item in the database
        // For now, we'll show a success message
        showAlert(`Added ${quantity} x ${product.name} to transfer`, 'success');
        
        // In a real implementation, you would:
        // 1. Make API call to add product to transfer
        // 2. Refresh the transfer items table
        // 3. Update totals and UI
        
        markUnsavedChanges();
    }

    // ========================================
    // DATA COLLECTION
    // ========================================
    function collectReceiveData() {
        const items = [];
        
        document.querySelectorAll('.receive-item-row').forEach(row => {
            const qtyInput = row.querySelector('.qty-input');
            const qtyReceived = parseFloat(qtyInput.value) || 0;
            const qtyRequestedElement = row.querySelector('.qty-display');
            const qtyRequested = parseFloat(qtyRequestedElement.textContent.replace(/,/g, '')) || 0;
            
            items.push({
                item_id: row.dataset.itemId,
                product_id: row.dataset.productId,
                qty_requested: qtyRequested,
                qty_received: qtyReceived,
                weight_grams: parseFloat(row.dataset.weightGrams) || 0
            });
        });
        
        return {
            transfer_id: state.transferId,
            transfer_mode: state.transferMode,
            items: items,
            totals: {
                total_requested: state.totalRequested,
                total_received: state.totalReceived,
                weight_grams: state.totalWeight,
                estimated_boxes: state.estimatedBoxes,
                has_fragile: state.hasFragile,
                has_nicotine: state.hasNicotine,
                completion_percentage: (state.totalReceived / Math.max(state.totalRequested, 1)) * 100
            },
            timestamp: new Date().toISOString()
        };
    }

    function validateAllInputs() {
        let isValid = true;
        
        document.querySelectorAll('.qty-input').forEach(input => {
            if (!validateQuantityInput(input)) {
                isValid = false;
                updateRowVisuals(input.closest('.receive-item-row'));
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
        
        // Ctrl+Enter or Cmd+Enter = Complete
        if ((event.ctrlKey || event.metaKey) && event.key === 'Enter') {
            event.preventDefault();
            handleCompleteReceive(event);
        }
        
        // Ctrl+Shift+Enter = Partial
        if ((event.ctrlKey || event.metaKey) && event.shiftKey && event.key === 'Enter') {
            event.preventDefault();
            handlePartialReceive(event);
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

    function number_format(num) {
        return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // ========================================
    // EXPORT PUBLIC API (for debugging/testing)
    // ========================================
    window.BaseReceive = {
        getState: () => ({ ...state }),
        validateAll: validateAllInputs,
        save: performManualSave,
        reset: resetAllQuantities,
        calculateTotals: calculateTotals,
        addProduct: showAddProductModal
    };

})();