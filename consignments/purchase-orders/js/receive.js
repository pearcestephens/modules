/**
 * Purchase Order Receiving JavaScript
 *
 * Handles goods receipt workflow:
 * - Barcode scanning
 * - Item-by-item receiving
 * - Quantity verification
 * - Damage reporting with photos
 * - Discrepancy handling
 * - Receipt printing
 *
 * @package CIS\Consignments\PurchaseOrders
 * @version 1.0.0
 */

(function($) {
    'use strict';

    const POReceiving = {
        // State
        poId: null,
        expectedItems: [],
        receivedItems: [],
        scanner: null,

        /**
         * Initialize receiving interface
         */
        init: function() {
            this.poId = $('#receiving-panel').data('po-id');
            this.loadExpectedItems();
            this.bindEvents();
            this.initBarcodeScanner();

            console.log('PO Receiving initialized', {poId: this.poId});
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Manual barcode entry
            $('#barcode-input').on('keypress', (e) => {
                if (e.which === 13) { // Enter key
                    this.processBarcode($('#barcode-input').val());
                    $('#barcode-input').val('').focus();
                }
            });

            // Scan button
            $('#scan-btn').on('click', () => this.startScanning());

            // Receive item buttons (delegated)
            $(document).on('click', '.receive-item-btn', (e) => {
                const itemId = $(e.currentTarget).data('item-id');
                this.receiveItem(itemId);
            });

            // Adjust quantity
            $(document).on('click', '.adjust-qty-btn', (e) => {
                const itemId = $(e.currentTarget).data('item-id');
                this.adjustQuantity(itemId);
            });

            // Report damage
            $(document).on('click', '.report-damage-btn', (e) => {
                const itemId = $(e.currentTarget).data('item-id');
                this.reportDamage(itemId);
            });

            // Complete receiving
            $('#complete-receiving-btn').on('click', () => this.completeReceiving());

            // Partial receiving
            $('#partial-receiving-btn').on('click', () => this.partialReceiving());

            // Print receipt
            $('#print-receipt-btn').on('click', () => this.printReceipt());
        },

        /**
         * Initialize barcode scanner
         */
        initBarcodeScanner: function() {
            // Check if device has camera
            if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
                $('#scan-btn').show();
            } else {
                $('#scan-btn').hide();
                console.log('Camera not available for barcode scanning');
            }
        },

        /**
         * Start barcode scanning with camera
         */
        startScanning: function() {
            // This would integrate with a barcode scanning library
            // For now, show modal with camera feed
            alert('Camera scanning not yet implemented. Please use manual entry.');
        },

        /**
         * Load expected items from PO
         */
        loadExpectedItems: function() {
            $.ajax({
                url: `/modules/consignments/api/purchase-orders/get.php?id=${this.poId}`,
                method: 'GET',
                success: (response) => {
                    if (response.success) {
                        this.expectedItems = response.data.line_items || [];
                        this.renderExpectedItems();
                        this.updateProgress();
                    }
                },
                error: () => {
                    alert('Error loading PO items');
                }
            });
        },

        /**
         * Render expected items table
         */
        renderExpectedItems: function() {
            const $tbody = $('#expected-items-tbody');
            $tbody.empty();

            this.expectedItems.forEach(item => {
                const received = this.getReceivedQuantity(item.id);
                const remaining = item.quantity - received;
                const status = remaining === 0 ? 'complete' : (received > 0 ? 'partial' : 'pending');

                const $row = $(`
                    <tr data-item-id="${item.id}" class="item-row-${status}">
                        <td>
                            <strong>${item.product_name}</strong><br>
                            <small class="text-muted">${item.sku || ''}</small>
                        </td>
                        <td class="text-center">${item.quantity}</td>
                        <td class="text-center received-qty">${received}</td>
                        <td class="text-center remaining-qty">
                            <span class="badge ${this.getRemainingBadgeClass(remaining)}">${remaining}</span>
                        </td>
                        <td>
                            ${this.renderItemActions(item.id, status, remaining)}
                        </td>
                    </tr>
                `);

                $tbody.append($row);
            });
        },

        /**
         * Render action buttons for item
         */
        renderItemActions: function(itemId, status, remaining) {
            if (status === 'complete') {
                return `
                    <button class="btn btn-sm btn-success" disabled>
                        <i class="fas fa-check"></i> Complete
                    </button>
                `;
            }

            return `
                <div class="btn-group" role="group">
                    <button class="btn btn-sm btn-primary receive-item-btn" data-item-id="${itemId}">
                        <i class="fas fa-box"></i> Receive
                    </button>
                    <button class="btn btn-sm btn-secondary adjust-qty-btn" data-item-id="${itemId}">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="btn btn-sm btn-warning report-damage-btn" data-item-id="${itemId}">
                        <i class="fas fa-exclamation-triangle"></i>
                    </button>
                </div>
            `;
        },

        /**
         * Get badge class for remaining quantity
         */
        getRemainingBadgeClass: function(remaining) {
            if (remaining === 0) return 'bg-success';
            if (remaining < 5) return 'bg-warning';
            return 'bg-secondary';
        },

        /**
         * Process scanned barcode
         */
        processBarcode: function(barcode) {
            if (!barcode) return;

            console.log('Processing barcode:', barcode);

            // Find matching item
            const item = this.expectedItems.find(i => i.barcode === barcode || i.sku === barcode);

            if (item) {
                this.receiveItem(item.id, 1);
                this.showToast(`Scanned: ${item.product_name}`, 'success');
            } else {
                this.showToast('Product not found in this PO', 'error');
                // Play error sound
                this.playErrorSound();
            }
        },

        /**
         * Receive item
         */
        receiveItem: function(itemId, quantity = null) {
            const item = this.expectedItems.find(i => i.id === itemId);
            if (!item) return;

            const received = this.getReceivedQuantity(itemId);
            const remaining = item.quantity - received;

            if (remaining === 0) {
                alert('This item is already fully received');
                return;
            }

            // Prompt for quantity if not provided
            if (quantity === null) {
                quantity = prompt(`Enter quantity to receive (max: ${remaining}):`, remaining);
                quantity = parseInt(quantity);

                if (isNaN(quantity) || quantity <= 0) {
                    return;
                }

                if (quantity > remaining) {
                    alert(`Cannot receive more than ${remaining} units`);
                    return;
                }
            }

            // Record receipt
            this.recordReceipt(itemId, quantity);
        },

        /**
         * Record item receipt
         */
        recordReceipt: function(itemId, quantity) {
            $.ajax({
                url: '/modules/consignments/api/purchase-orders/receive-item.php',
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({
                    po_id: this.poId,
                    item_id: itemId,
                    quantity: quantity
                }),
                success: (response) => {
                    if (response.success) {
                        this.receivedItems.push({
                            item_id: itemId,
                            quantity: quantity,
                            timestamp: new Date()
                        });

                        this.renderExpectedItems();
                        this.updateProgress();
                        this.showToast('Item received', 'success');
                        this.playSuccessSound();
                    } else {
                        alert('Error: ' + response.message);
                    }
                },
                error: () => {
                    alert('Error recording receipt');
                }
            });
        },

        /**
         * Adjust received quantity
         */
        adjustQuantity: function(itemId) {
            const item = this.expectedItems.find(i => i.id === itemId);
            if (!item) return;

            const received = this.getReceivedQuantity(itemId);
            const newQty = prompt(`Adjust received quantity (currently ${received}):`, received);

            if (newQty === null) return;

            const qty = parseInt(newQty);
            if (isNaN(qty) || qty < 0) {
                alert('Invalid quantity');
                return;
            }

            if (qty > item.quantity) {
                if (!confirm(`Received quantity (${qty}) exceeds expected (${item.quantity}). Continue?`)) {
                    return;
                }
            }

            // Update quantity
            $.ajax({
                url: '/modules/consignments/api/purchase-orders/adjust-quantity.php',
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({
                    po_id: this.poId,
                    item_id: itemId,
                    quantity: qty
                }),
                success: (response) => {
                    if (response.success) {
                        this.loadExpectedItems(); // Reload to refresh
                        this.showToast('Quantity adjusted', 'success');
                    } else {
                        alert('Error: ' + response.message);
                    }
                },
                error: () => {
                    alert('Error adjusting quantity');
                }
            });
        },

        /**
         * Report damaged items
         */
        reportDamage: function(itemId) {
            const item = this.expectedItems.find(i => i.id === itemId);
            if (!item) return;

            const quantity = prompt('How many units are damaged?', '1');
            if (!quantity) return;

            const qty = parseInt(quantity);
            if (isNaN(qty) || qty <= 0) {
                alert('Invalid quantity');
                return;
            }

            const notes = prompt('Describe the damage:');
            if (!notes) {
                alert('Damage description is required');
                return;
            }

            // Would open photo upload modal here
            const uploadPhotos = confirm('Do you want to upload photos of the damage?');

            $.ajax({
                url: '/modules/consignments/api/purchase-orders/report-damage.php',
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({
                    po_id: this.poId,
                    item_id: itemId,
                    quantity: qty,
                    notes: notes
                }),
                success: (response) => {
                    if (response.success) {
                        this.showToast('Damage reported', 'warning');

                        if (uploadPhotos) {
                            // Open photo upload interface
                            this.openPhotoUpload(response.data.damage_report_id);
                        }
                    } else {
                        alert('Error: ' + response.message);
                    }
                },
                error: () => {
                    alert('Error reporting damage');
                }
            });
        },

        /**
         * Open photo upload interface
         */
        openPhotoUpload: function(damageReportId) {
            alert('Photo upload interface not yet implemented. Report ID: ' + damageReportId);
        },

        /**
         * Complete receiving (full)
         */
        completeReceiving: function() {
            const allReceived = this.expectedItems.every(item => {
                const received = this.getReceivedQuantity(item.id);
                return received === item.quantity;
            });

            if (!allReceived) {
                if (!confirm('Not all items have been fully received. Mark as complete anyway?')) {
                    return;
                }
            }

            if (!confirm('Mark this Purchase Order as fully received?')) {
                return;
            }

            this.finalizeReceiving('RECEIVED');
        },

        /**
         * Partial receiving
         */
        partialReceiving: function() {
            const anyReceived = this.receivedItems.length > 0;

            if (!anyReceived) {
                alert('No items have been received yet');
                return;
            }

            if (!confirm('Save partial receipt and close receiving process?')) {
                return;
            }

            this.finalizeReceiving('PARTIAL');
        },

        /**
         * Finalize receiving
         */
        finalizeReceiving: function(status) {
            $('#complete-receiving-btn, #partial-receiving-btn').prop('disabled', true);

            $.ajax({
                url: '/modules/consignments/api/purchase-orders/complete-receiving.php',
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({
                    po_id: this.poId,
                    status: status
                }),
                success: (response) => {
                    if (response.success) {
                        this.showToast('Receiving completed', 'success');

                        // Print receipt if requested
                        if (confirm('Print receiving receipt?')) {
                            this.printReceipt();
                        }

                        setTimeout(() => {
                            window.location.href = `/modules/consignments/purchase-orders/view.php?id=${this.poId}`;
                        }, 2000);
                    } else {
                        alert('Error: ' + response.message);
                        $('#complete-receiving-btn, #partial-receiving-btn').prop('disabled', false);
                    }
                },
                error: () => {
                    alert('Error completing receiving');
                    $('#complete-receiving-btn, #partial-receiving-btn').prop('disabled', false);
                }
            });
        },

        /**
         * Print receipt
         */
        printReceipt: function() {
            window.open(
                `/modules/consignments/purchase-orders/print-receipt.php?po_id=${this.poId}`,
                '_blank',
                'width=800,height=600'
            );
        },

        /**
         * Get received quantity for item
         */
        getReceivedQuantity: function(itemId) {
            return this.receivedItems
                .filter(r => r.item_id === itemId)
                .reduce((sum, r) => sum + r.quantity, 0);
        },

        /**
         * Update progress indicators
         */
        updateProgress: function() {
            const totalExpected = this.expectedItems.reduce((sum, item) => sum + item.quantity, 0);
            const totalReceived = this.receivedItems.reduce((sum, r) => sum + r.quantity, 0);
            const percentComplete = totalExpected > 0 ? Math.round((totalReceived / totalExpected) * 100) : 0;

            $('#progress-bar').css('width', percentComplete + '%').text(percentComplete + '%');
            $('#total-expected').text(totalExpected);
            $('#total-received').text(totalReceived);
            $('#percent-complete').text(percentComplete + '%');

            // Update progress color
            if (percentComplete === 100) {
                $('#progress-bar').removeClass('bg-warning bg-info').addClass('bg-success');
            } else if (percentComplete > 0) {
                $('#progress-bar').removeClass('bg-success bg-info').addClass('bg-warning');
            } else {
                $('#progress-bar').removeClass('bg-success bg-warning').addClass('bg-info');
            }
        },

        /**
         * Play success sound
         */
        playSuccessSound: function() {
            // Simple beep for successful scan
            try {
                const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
                const oscillator = audioCtx.createOscillator();
                oscillator.type = 'sine';
                oscillator.frequency.value = 800;
                oscillator.connect(audioCtx.destination);
                oscillator.start();
                oscillator.stop(audioCtx.currentTime + 0.1);
            } catch (e) {
                console.log('Audio not available');
            }
        },

        /**
         * Play error sound
         */
        playErrorSound: function() {
            try {
                const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
                const oscillator = audioCtx.createOscillator();
                oscillator.type = 'square';
                oscillator.frequency.value = 200;
                oscillator.connect(audioCtx.destination);
                oscillator.start();
                oscillator.stop(audioCtx.currentTime + 0.2);
            } catch (e) {
                console.log('Audio not available');
            }
        },

        /**
         * Show toast notification
         */
        showToast: function(message, type = 'info') {
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
    // INITIALIZE
    // ========================================================================

    $(document).ready(function() {
        POReceiving.init();
    });

})(jQuery);
