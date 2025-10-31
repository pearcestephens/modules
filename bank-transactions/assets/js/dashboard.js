/**
 * Bank Transactions Dashboard JavaScript
 *
 * Handles dashboard interactions, real-time updates, and auto-match functionality
 */

(function() {
    'use strict';

    // DOM elements
    const datePicker = document.getElementById('date-picker');
    const runAutoMatchBtn = document.getElementById('run-auto-match');
    const csrfToken = document.getElementById('csrf-token').value;

    // Initialize
    document.addEventListener('DOMContentLoaded', function() {
        initDatePicker();
        initAutoMatch();
        initRefresh();
    });

    /**
     * Initialize date picker
     */
    function initDatePicker() {
        if (!datePicker) return;

        datePicker.addEventListener('change', function() {
            const selectedDate = this.value;
            window.location.href = `?date=${selectedDate}`;
        });
    }

    /**
     * Initialize auto-match button
     */
    function initAutoMatch() {
        if (!runAutoMatchBtn) return;

        runAutoMatchBtn.addEventListener('click', function() {
            runAutoMatch();
        });
    }

    /**
     * Run auto-match for all unmatched transactions
     */
    function runAutoMatch() {
        // Disable button
        runAutoMatchBtn.disabled = true;
        runAutoMatchBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Running...';

        // Show progress toast
        showToast('Starting auto-match process...', 'info');

        fetch('/modules/bank-transactions/api/auto-match-all.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken
            },
            body: JSON.stringify({
                csrf_token: csrfToken
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast(
                    `Auto-match completed! ${data.data.matched} matched, ${data.data.review} sent to review, ${data.data.failed} failed.`,
                    'success'
                );

                // Reload page after 2 seconds
                setTimeout(() => {
                    window.location.reload();
                }, 2000);
            } else {
                showToast(data.error.message, 'error');

                // Re-enable button
                runAutoMatchBtn.disabled = false;
                runAutoMatchBtn.innerHTML = '<i class="fas fa-magic me-1"></i> Run Auto-Match';
            }
        })
        .catch(error => {
            console.error('Auto-match error:', error);
            showToast('Auto-match failed. Please try again.', 'error');

            // Re-enable button
            runAutoMatchBtn.disabled = false;
            runAutoMatchBtn.innerHTML = '<i class="fas fa-magic me-1"></i> Run Auto-Match';
        });
    }

    /**
     * Initialize auto-refresh
     */
    function initRefresh() {
        // Refresh metrics every 5 minutes
        setInterval(refreshMetrics, 5 * 60 * 1000);
    }

    /**
     * Refresh dashboard metrics
     */
    function refreshMetrics() {
        const date = datePicker ? datePicker.value : new Date().toISOString().split('T')[0];

        fetch(`/modules/bank-transactions/api/dashboard-metrics.php?date=${date}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updateMetrics(data.data);
                }
            })
            .catch(error => {
                console.error('Metrics refresh error:', error);
            });
    }

    /**
     * Update metrics on page
     *
     * @param {Object} data Metrics data
     */
    function updateMetrics(data) {
        // Update metric cards
        const metrics = data.metrics;

        // Total
        updateMetricValue('total', metrics.total);

        // Unmatched
        updateMetricValue('unmatched', metrics.unmatched);
        updateMetricValue('unmatched-amount', formatCurrency(metrics.unmatched_amount));

        // Matched
        updateMetricValue('matched', metrics.matched);
        updateMetricValue('matched-amount', formatCurrency(metrics.matched_amount));

        // Review
        updateMetricValue('review', metrics.review);
    }

    /**
     * Update metric value
     *
     * @param {string} key Metric key
     * @param {string|number} value New value
     */
    function updateMetricValue(key, value) {
        const element = document.querySelector(`[data-metric="${key}"]`);
        if (element) {
            element.textContent = value;
        }
    }

    /**
     * Format currency
     *
     * @param {number} amount Amount
     * @return {string} Formatted currency
     */
    function formatCurrency(amount) {
        return '$' + parseFloat(amount).toLocaleString('en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    /**
     * Show toast notification
     *
     * @param {string} message Message
     * @param {string} type Type (success, error, info, warning)
     */
    function showToast(message, type = 'info') {
        // Create toast container if it doesn't exist
        let toastContainer = document.getElementById('toast-container');
        if (!toastContainer) {
            toastContainer = document.createElement('div');
            toastContainer.id = 'toast-container';
            toastContainer.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                z-index: 9999;
            `;
            document.body.appendChild(toastContainer);
        }

        // Create toast
        const toast = document.createElement('div');
        toast.className = `alert alert-${type} alert-dismissible fade show`;
        toast.style.cssText = `
            min-width: 300px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            margin-bottom: 10px;
        `;

        // Icon based on type
        let icon = 'info-circle';
        if (type === 'success') icon = 'check-circle';
        if (type === 'error' || type === 'danger') icon = 'exclamation-circle';
        if (type === 'warning') icon = 'exclamation-triangle';

        toast.innerHTML = `
            <i class="fas fa-${icon} me-2"></i>
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;

        toastContainer.appendChild(toast);

        // Auto-remove after 5 seconds
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 150);
        }, 5000);
    }

})();
