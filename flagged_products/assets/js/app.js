/**
 * Flagged Products Module - JavaScript Application
 *
 * @package CIS\Modules\FlaggedProducts
 * @version 1.0.0
 */

const OutletView = (function() {
    'use strict';

    let outletId = null;
    const API_BASE = 'api/FlaggedProductsAPI.php';

    /**
     * Initialize the outlet view
     */
    function init(id) {
        outletId = id;
        console.log('✅ OutletView initialized for outlet:', outletId);
    }

    /**
     * Complete a flagged product
     */
    window.completeProduct = function(productId, qtyBefore, currentStock) {
        if (!confirm('Mark this product as complete? This will record the stock accuracy.')) {
            return;
        }

        const staffId = 1; // TODO: Get from session

        fetch(API_BASE, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'complete',
                outlet_id: outletId,
                product_id: productId,
                staff_id: staffId,
                qty_before: qtyBefore,
                qty_after: currentStock
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('Product marked as complete!', 'success');
                setTimeout(() => location.reload(), 1000);
            } else {
                showNotification('Error: ' + (data.message || 'Unknown error'), 'danger');
            }
        })
        .catch(error => {
            console.error('Error completing product:', error);
            showNotification('Network error occurred', 'danger');
        });
    };

    /**
     * Delete a flagged product
     */
    window.deleteProduct = function(productId) {
        if (!confirm('Delete this flagged product entry? This cannot be undone.')) {
            return;
        }

        fetch(API_BASE, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'delete',
                outlet_id: outletId,
                product_id: productId
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('Flagged product deleted!', 'success');
                setTimeout(() => location.reload(), 1000);
            } else {
                showNotification('Error: ' + (data.message || 'Unknown error'), 'danger');
            }
        })
        .catch(error => {
            console.error('Error deleting product:', error);
            showNotification('Network error occurred', 'danger');
        });
    };

    /**
     * Show notification toast
     */
    function showNotification(message, type = 'info') {
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
        alertDiv.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
        alertDiv.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        document.body.appendChild(alertDiv);

        setTimeout(() => {
            alertDiv.remove();
        }, 5000);
    }

    /**
     * Bulk complete all flagged products
     */
    function bulkComplete() {
        if (!confirm('Mark ALL flagged products as complete for this outlet?')) {
            return;
        }

        const staffId = 1; // TODO: Get from session

        fetch(API_BASE, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'bulk_complete',
                outlet_id: outletId,
                staff_id: staffId
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification(`Completed ${data.count} products!`, 'success');
                setTimeout(() => location.reload(), 1500);
            } else {
                showNotification('Error: ' + (data.message || 'Unknown error'), 'danger');
            }
        })
        .catch(error => {
            console.error('Error bulk completing:', error);
            showNotification('Network error occurred', 'danger');
        });
    }

    /**
     * Delete all flagged products for outlet
     */
    function deleteAllForOutlet() {
        if (!confirm('DELETE ALL flagged products for this outlet? This cannot be undone!')) {
            return;
        }

        fetch(API_BASE, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'delete_all',
                outlet_id: outletId
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('All flagged products deleted!', 'success');
                setTimeout(() => location.reload(), 1000);
            } else {
                showNotification('Error: ' + (data.message || 'Unknown error'), 'danger');
            }
        })
        .catch(error => {
            console.error('Error deleting all:', error);
            showNotification('Network error occurred', 'danger');
        });
    }

    /**
     * Export flagged products to CSV
     */
    function exportToCSV() {
        window.location.href = `${API_BASE}?action=export&outlet_id=${outletId}`;
    }

    /**
     * Refresh stats without page reload
     */
    function refreshStats() {
        fetch(`${API_BASE}?action=stats&outlet_id=${outletId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update stats cards
                    updateStatsDisplay(data.stats);
                    showNotification('Stats refreshed!', 'info');
                }
            })
            .catch(error => {
                console.error('Error refreshing stats:', error);
            });
    }

    /**
     * Update stats display
     */
    function updateStatsDisplay(stats) {
        // Update stats cards with new data
        // Implementation depends on DOM structure
        console.log('Stats updated:', stats);
    }

    // Public API
    return {
        init: init,
        bulkComplete: bulkComplete,
        deleteAllForOutlet: deleteAllForOutlet,
        exportToCSV: exportToCSV,
        refreshStats: refreshStats
    };

})();

/**
 * Global utility functions
 */

// Format timestamp for display
function formatTimestamp(timestamp) {
    const date = new Date(timestamp);
    return date.toLocaleString('en-NZ', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

// Calculate accuracy percentage
function calculateAccuracy(accurate, total) {
    if (total === 0) return 0;
    return ((accurate / total) * 100).toFixed(1);
}

// Get accuracy class for styling
function getAccuracyClass(percentage) {
    if (percentage >= 95) return 'success';
    if (percentage >= 85) return 'warning';
    return 'danger';
}

console.log('✅ Flagged Products app.js loaded successfully');
