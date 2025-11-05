/**
 * CIS Barcode Widget - Easy Integration
 *
 * Usage: Just include this file after barcode-widget-advanced.js
 *
 * The widget will auto-initialize and handle everything.
 * You can customize behavior by setting window.cisScannerOptions before loading.
 */

(function() {
    'use strict';

    // Default options (can be overridden by setting window.cisScannerOptions)
    const defaultOptions = {
        // Auto-detect from page if available
        transferId: document.querySelector('[data-transfer-id]')?.dataset.transferId || null,
        transferType: document.querySelector('[data-transfer-type]')?.dataset.transferType || 'stock_transfer',
        userId: document.querySelector('[data-user-id]')?.dataset.userId || null,
        outletId: document.querySelector('[data-outlet-id]')?.dataset.outletId || null,

        // Callbacks
        onScan: async function(barcode) {
            console.log('Scanned:', barcode);

            // Example: Find product on page and highlight it
            const productRow = document.querySelector(`[data-barcode="${barcode}"]`);
            if (productRow) {
                productRow.classList.add('table-success');
                setTimeout(() => productRow.classList.remove('table-success'), 2000);
                return { success: true, message: 'Product found!' };
            } else {
                return { success: false, message: 'Product not found' };
            }
        },

        onPhoto: async function(photoBlob) {
            console.log('Photo captured:', photoBlob.size, 'bytes');

            // Example: Upload to server
            const formData = new FormData();
            formData.append('photo', photoBlob, 'damage-photo.jpg');
            formData.append('transfer_id', this.transferId);

            try {
                const response = await fetch('/modules/consignments/api/upload_photo.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();
                return result;
            } catch (error) {
                console.error('Photo upload failed:', error);
                throw error;
            }
        },

        onError: function(error) {
            console.error('Scanner error:', error);
            // Could show a toast notification here
        }
    };

    // Merge with custom options if provided
    const options = { ...defaultOptions, ...(window.cisScannerOptions || {}) };

    // Wait for DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    function init() {
        // Create global widget instance
        window.cisBarcodeWidget = new CISAdvancedBarcodeWidget(options);

        console.log('[Scanner Widget] Auto-initialized', {
            transferType: options.transferType,
            hasTransferId: !!options.transferId
        });
    }

    // Helper: Add data attributes to your page for auto-detection
    // Example: <body data-transfer-id="123" data-transfer-type="stock_transfer" data-user-id="45" data-outlet-id="2">

})();
