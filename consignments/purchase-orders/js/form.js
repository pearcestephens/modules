/**
 * Purchase Order Form JavaScript
 *
 * Handles create/edit form interactions:
 * - Dynamic line item management
 * - Product search and selection
 * - Price calculations
 * - Form validation
 * - Autosave functionality
 * - Supplier selection
 *
 * @package CIS\Consignments\PurchaseOrders
 * @version 1.0.0
 */

(function($) {
    'use strict';

    // ========================================================================
    // STATE MANAGEMENT
    // ========================================================================

    const POForm = {
        // Form state
        poId: null,
        isDirty: false,
        isSubmitting: false,
        autosaveTimer: null,

        // Line items
        lineItems: [],
        nextLineId: 1,

        // Cached data
        products: {},
        suppliers: {},

        // Settings
        autosaveInterval: 30000, // 30 seconds

        /**
         * Initialize form
         */
        init: function() {
            this.poId = $('#po-form').data('po-id') || null;
            this.bindEvents();
            this.loadExistingLineItems();
            this.initAutosave();
            this.initSupplierSearch();
            this.initProductSearch();

            console.log('PO Form initialized', {poId: this.poId});
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Add line item button
            $('#add-line-item').on('click', () => this.addLineItem());

            // Remove line item (delegated)
            $(document).on('click', '.remove-line-item', (e) => {
                const lineId = $(e.currentTarget).data('line-id');
                this.removeLineItem(lineId);
            });

            // Quantity/price changes (delegated)
            $(document).on('input', '.line-quantity, .line-unit-cost', (e) => {
                const lineId = $(e.currentTarget).closest('.line-item-row').data('line-id');
                this.recalculateLineTotal(lineId);
                this.recalculatePOTotal();
                this.markDirty();
            });

            // Supplier change
            $('#supplier-select').on('change', () => {
                this.onSupplierChange();
                this.markDirty();
            });

            // Expected delivery date change
            $('#expected-delivery-date').on('change', () => this.markDirty());

            // Notes change
            $('#consignment-notes').on('input', () => this.markDirty());

            // Form submit
            $('#po-form').on('submit', (e) => {
                e.preventDefault();
                this.submitForm();
            });

            // Save draft button
            $('#save-draft-btn').on('click', () => this.saveDraft());

            // Submit for approval button
            $('#submit-approval-btn').on('click', () => this.submitForApproval());

            // Warn on page leave if dirty
            $(window).on('beforeunload', (e) => {
                if (this.isDirty && !this.isSubmitting) {
                    e.preventDefault();
                    return 'You have unsaved changes. Are you sure you want to leave?';
                }
            });
        },

        /**
         * Load existing line items (for edit mode)
         */
        loadExistingLineItems: function() {
            if (!this.poId) return;

            const existingRows = $('#line-items-tbody tr.line-item-row');
            existingRows.each((idx, row) => {
                const $row = $(row);
                const lineId = this.nextLineId++;

                $row.attr('data-line-id', lineId);

                this.lineItems.push({
                    id: lineId,
                    dbId: $row.data('db-id'),
                    productId: $row.find('.line-product').data('product-id'),
                    productName: $row.find('.line-product').text(),
                    sku: $row.find('.line-sku').text(),
                    quantity: parseFloat($row.find('.line-quantity').val()) || 0,
                    unitCost: parseFloat($row.find('.line-unit-cost').val()) || 0,
                    totalCost: parseFloat($row.find('.line-total').text()) || 0
                });
            });

            this.recalculatePOTotal();
        },

        /**
         * Initialize autosave
         */
        initAutosave: function() {
            if (!this.poId) return; // Only autosave on edit

            this.autosaveTimer = setInterval(() => {
                if (this.isDirty && !this.isSubmitting) {
                    this.autosave();
                }
            }, this.autosaveInterval);
        },

        /**
         * Initialize supplier search (Select2)
         */
        initSupplierSearch: function() {
            $('#supplier-select').select2({
                placeholder: 'Search suppliers...',
                allowClear: true,
                ajax: {
                    url: '/modules/consignments/api/purchase-orders/search-suppliers.php',
                    dataType: 'json',
                    delay: 250,
                    data: function(params) {
                        return {
                            q: params.term,
                            page: params.page || 1
                        };
                    },
                    processResults: function(data) {
                        return {
                            results: data.data.items.map(s => ({
                                id: s.id,
                                text: s.name,
                                email: s.email,
                                phone: s.phone
                            })),
                            pagination: {
                                more: data.data.has_more
                            }
                        };
                    }
                }
            });
        },

        /**
         * Initialize product search for line items
         */
        initProductSearch: function() {
            $(document).on('click', '.search-product-btn', (e) => {
                const lineId = $(e.currentTarget).closest('.line-item-row').data('line-id');
                this.openProductSearch(lineId);
            });
        },

        /**
         * Add new line item
         */
        addLineItem: function(product = null) {
            const lineId = this.nextLineId++;

            const lineItem = {
                id: lineId,
                dbId: null,
                productId: product?.id || null,
                productName: product?.name || '',
                sku: product?.sku || '',
                quantity: 1,
                unitCost: product?.supply_price || 0,
                totalCost: product?.supply_price || 0
            };

            this.lineItems.push(lineItem);
            this.renderLineItem(lineItem);
            this.recalculatePOTotal();
            this.markDirty();

            // Focus on quantity field
            $(`tr[data-line-id="${lineId}"] .line-quantity`).focus().select();
        },

        /**
         * Render a line item row
         */
        renderLineItem: function(item) {
            const row = `
                <tr class="line-item-row" data-line-id="${item.id}" data-db-id="${item.dbId || ''}">
                    <td>
                        <div class="product-cell">
                            <span class="line-product" data-product-id="${item.productId || ''}">${item.productName || 'Select product...'}</span>
                            <button type="button" class="btn btn-sm btn-link search-product-btn">
                                <i class="fas fa-search"></i> Search
                            </button>
                        </div>
                    </td>
                    <td class="line-sku">${item.sku}</td>
                    <td>
                        <input type="number"
                               class="form-control form-control-sm line-quantity"
                               value="${item.quantity}"
                               min="1"
                               step="1">
                    </td>
                    <td>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text">$</span>
                            <input type="number"
                                   class="form-control line-unit-cost"
                                   value="${item.unitCost.toFixed(2)}"
                                   min="0"
                                   step="0.01">
                        </div>
                    </td>
                    <td class="line-total text-end">$${item.totalCost.toFixed(2)}</td>
                    <td>
                        <button type="button"
                                class="btn btn-sm btn-danger remove-line-item"
                                data-line-id="${item.id}">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>
            `;

            $('#line-items-tbody').append(row);
        },

        /**
         * Remove line item
         */
        removeLineItem: function(lineId) {
            if (!confirm('Remove this line item?')) return;

            this.lineItems = this.lineItems.filter(item => item.id !== lineId);
            $(`tr[data-line-id="${lineId}"]`).remove();
            this.recalculatePOTotal();
            this.markDirty();
        },

        /**
         * Recalculate line item total
         */
        recalculateLineTotal: function(lineId) {
            const $row = $(`tr[data-line-id="${lineId}"]`);
            const quantity = parseFloat($row.find('.line-quantity').val()) || 0;
            const unitCost = parseFloat($row.find('.line-unit-cost').val()) || 0;
            const total = quantity * unitCost;

            $row.find('.line-total').text('$' + total.toFixed(2));

            // Update state
            const item = this.lineItems.find(i => i.id === lineId);
            if (item) {
                item.quantity = quantity;
                item.unitCost = unitCost;
                item.totalCost = total;
            }
        },

        /**
         * Recalculate PO total
         */
        recalculatePOTotal: function() {
            let subtotal = 0;

            this.lineItems.forEach(item => {
                subtotal += item.totalCost;
            });

            const tax = subtotal * 0.15; // 15% GST
            const total = subtotal + tax;

            $('#subtotal-amount').text('$' + subtotal.toFixed(2));
            $('#tax-amount').text('$' + tax.toFixed(2));
            $('#total-amount').text('$' + total.toFixed(2));
            $('#item-count').text(this.lineItems.length);
        },

        /**
         * Open product search modal
         */
        openProductSearch: function(lineId) {
            // This would open a modal with product search
            // For now, use a simple prompt (replace with modal later)
            const productName = prompt('Enter product name or SKU:');
            if (!productName) return;

            // Search products via AJAX
            $.ajax({
                url: '/modules/consignments/api/purchase-orders/search-products.php',
                method: 'GET',
                data: { q: productName },
                success: (response) => {
                    if (response.success && response.data.items.length > 0) {
                        const product = response.data.items[0];
                        this.selectProduct(lineId, product);
                    } else {
                        alert('Product not found');
                    }
                },
                error: () => {
                    alert('Error searching products');
                }
            });
        },

        /**
         * Select product for line item
         */
        selectProduct: function(lineId, product) {
            const $row = $(`tr[data-line-id="${lineId}"]`);

            $row.find('.line-product')
                .text(product.name)
                .data('product-id', product.id);
            $row.find('.line-sku').text(product.sku || '');
            $row.find('.line-unit-cost').val((product.supply_price || 0).toFixed(2));

            // Update state
            const item = this.lineItems.find(i => i.id === lineId);
            if (item) {
                item.productId = product.id;
                item.productName = product.name;
                item.sku = product.sku || '';
                item.unitCost = product.supply_price || 0;
            }

            this.recalculateLineTotal(lineId);
            this.recalculatePOTotal();
            this.markDirty();
        },

        /**
         * Handle supplier change
         */
        onSupplierChange: function() {
            const supplierId = $('#supplier-select').val();
            if (!supplierId) return;

            // Could pre-fill expected delivery based on supplier lead time
            // Could filter products by supplier
            console.log('Supplier changed to:', supplierId);
        },

        /**
         * Mark form as dirty
         */
        markDirty: function() {
            this.isDirty = true;
            $('#unsaved-indicator').show();
        },

        /**
         * Mark form as clean
         */
        markClean: function() {
            this.isDirty = false;
            $('#unsaved-indicator').hide();
        },

        /**
         * Autosave draft
         */
        autosave: function() {
            if (!this.poId) return;

            console.log('Autosaving...');

            $.ajax({
                url: `/modules/consignments/api/purchase-orders/autosave.php`,
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify(this.getFormData()),
                success: (response) => {
                    if (response.success) {
                        this.markClean();
                        this.showToast('Draft saved', 'success');
                    }
                },
                error: () => {
                    console.error('Autosave failed');
                }
            });
        },

        /**
         * Save draft manually
         */
        saveDraft: function() {
            this.submitForm('DRAFT');
        },

        /**
         * Submit for approval
         */
        submitForApproval: function() {
            if (!this.validateForm()) {
                return;
            }

            if (!confirm('Submit this Purchase Order for approval?')) {
                return;
            }

            this.submitForm('OPEN');
        },

        /**
         * Validate form
         */
        validateForm: function() {
            const errors = [];

            if (!$('#supplier-select').val()) {
                errors.push('Please select a supplier');
            }

            if (this.lineItems.length === 0) {
                errors.push('Please add at least one line item');
            }

            this.lineItems.forEach((item, idx) => {
                if (!item.productId) {
                    errors.push(`Line ${idx + 1}: Please select a product`);
                }
                if (item.quantity <= 0) {
                    errors.push(`Line ${idx + 1}: Quantity must be greater than 0`);
                }
                if (item.unitCost < 0) {
                    errors.push(`Line ${idx + 1}: Unit cost cannot be negative`);
                }
            });

            if (errors.length > 0) {
                alert('Please fix the following errors:\n\n' + errors.join('\n'));
                return false;
            }

            return true;
        },

        /**
         * Submit form
         */
        submitForm: function(targetState = null) {
            if (this.isSubmitting) return;

            if (!this.validateForm()) {
                return;
            }

            this.isSubmitting = true;
            $('#submit-btn, #save-draft-btn, #submit-approval-btn').prop('disabled', true);

            const data = this.getFormData();
            if (targetState) {
                data.target_state = targetState;
            }

            const url = this.poId
                ? `/modules/consignments/api/purchase-orders/update.php`
                : `/modules/consignments/api/purchase-orders/create.php`;

            $.ajax({
                url: url,
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify(data),
                success: (response) => {
                    if (response.success) {
                        this.markClean();
                        this.showToast('Purchase Order saved successfully', 'success');

                        // Redirect to view page
                        setTimeout(() => {
                            window.location.href = `/modules/consignments/purchase-orders/view.php?id=${response.data.id}`;
                        }, 1000);
                    } else {
                        alert('Error: ' + response.message);
                        this.isSubmitting = false;
                        $('#submit-btn, #save-draft-btn, #submit-approval-btn').prop('disabled', false);
                    }
                },
                error: (xhr) => {
                    alert('Error saving Purchase Order');
                    this.isSubmitting = false;
                    $('#submit-btn, #save-draft-btn, #submit-approval-btn').prop('disabled', false);
                }
            });
        },

        /**
         * Get form data
         */
        getFormData: function() {
            return {
                id: this.poId,
                supplier_id: $('#supplier-select').val(),
                outlet_to: $('#outlet-to-select').val(),
                expected_delivery_date: $('#expected-delivery-date').val(),
                supplier_reference: $('#supplier-reference').val(),
                consignment_notes: $('#consignment-notes').val(),
                line_items: this.lineItems.map(item => ({
                    id: item.dbId,
                    product_id: item.productId,
                    quantity: item.quantity,
                    unit_cost: item.unitCost,
                    total_cost: item.totalCost
                }))
            };
        },

        /**
         * Show toast notification
         */
        showToast: function(message, type = 'info') {
            // Simple toast implementation
            const toast = $(`
                <div class="toast-notification toast-${type}">
                    ${message}
                </div>
            `);

            $('body').append(toast);

            setTimeout(() => {
                toast.addClass('show');
            }, 100);

            setTimeout(() => {
                toast.removeClass('show');
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }
    };

    // ========================================================================
    // INITIALIZE ON DOCUMENT READY
    // ========================================================================

    $(document).ready(function() {
        POForm.init();
    });

})(jQuery);
