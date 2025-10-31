/**
 * Bank Transactions - Transaction List JavaScript
 *
 * Handles filtering, search, bulk actions, and pagination
 */

(function() {
    'use strict';

    // DOM elements
    const filtersForm = document.getElementById('filters-form');
    const searchInput = document.getElementById('search');
    const clearFiltersBtn = document.getElementById('clear-filters');
    const clearFiltersEmptyBtn = document.getElementById('clear-filters-empty');
    const bulkActionsToggle = document.getElementById('bulk-actions-toggle');
    const bulkActionsBar = document.getElementById('bulk-actions-bar');
    const selectAllCheckbox = document.getElementById('select-all');
    const transactionCheckboxes = document.querySelectorAll('.transaction-checkbox');
    const bulkSelectedCount = document.getElementById('bulk-selected-count');
    const bulkAutoMatchBtn = document.getElementById('bulk-auto-match');
    const bulkSendReviewBtn = document.getElementById('bulk-send-review');
    const bulkDeselectBtn = document.getElementById('bulk-deselect');
    const exportBtn = document.getElementById('export-transactions');
    const autoMatchBtns = document.querySelectorAll('.auto-match-btn');
    const csrfToken = document.getElementById('csrf-token').value;

    // State
    let bulkMode = false;
    let selectedTransactions = new Set();
    let searchTimeout = null;

    // Initialize
    document.addEventListener('DOMContentLoaded', function() {
        initFilters();
        initSearch();
        initBulkActions();
        initAutoMatch();
        initExport();
    });

    /**
     * Initialize filters
     */
    function initFilters() {
        if (!filtersForm) return;

        // Auto-submit on filter change
        const filterSelects = filtersForm.querySelectorAll('select, input[type="date"]');
        filterSelects.forEach(select => {
            select.addEventListener('change', function() {
                filtersForm.submit();
            });
        });

        // Clear filters
        if (clearFiltersBtn) {
            clearFiltersBtn.addEventListener('click', clearFilters);
        }
        if (clearFiltersEmptyBtn) {
            clearFiltersEmptyBtn.addEventListener('click', clearFilters);
        }
    }

    /**
     * Clear all filters
     */
    function clearFilters() {
        window.location.href = window.location.pathname;
    }

    /**
     * Initialize search with debounce
     */
    function initSearch() {
        if (!searchInput) return;

        searchInput.addEventListener('input', function() {
            // Clear previous timeout
            if (searchTimeout) {
                clearTimeout(searchTimeout);
            }

            // Debounce search (300ms)
            searchTimeout = setTimeout(() => {
                filtersForm.submit();
            }, 300);
        });
    }

    /**
     * Initialize bulk actions
     */
    function initBulkActions() {
        if (!bulkActionsToggle) return;

        // Toggle bulk mode
        bulkActionsToggle.addEventListener('click', function() {
            bulkMode = !bulkMode;
            toggleBulkMode();
        });

        // Select all
        if (selectAllCheckbox) {
            selectAllCheckbox.addEventListener('change', function() {
                const checked = this.checked;
                transactionCheckboxes.forEach(checkbox => {
                    checkbox.checked = checked;
                    if (checked) {
                        selectedTransactions.add(checkbox.value);
                    } else {
                        selectedTransactions.delete(checkbox.value);
                    }
                });
                updateBulkCount();
            });
        }

        // Individual checkboxes
        transactionCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                if (this.checked) {
                    selectedTransactions.add(this.value);
                } else {
                    selectedTransactions.delete(this.value);
                }
                updateBulkCount();

                // Update select all
                if (selectAllCheckbox) {
                    selectAllCheckbox.checked = selectedTransactions.size === transactionCheckboxes.length;
                }
            });
        });

        // Bulk auto-match
        if (bulkAutoMatchBtn) {
            bulkAutoMatchBtn.addEventListener('click', bulkAutoMatch);
        }

        // Bulk send to review
        if (bulkSendReviewBtn) {
            bulkSendReviewBtn.addEventListener('click', bulkSendReview);
        }

        // Deselect all
        if (bulkDeselectBtn) {
            bulkDeselectBtn.addEventListener('click', function() {
                selectedTransactions.clear();
                transactionCheckboxes.forEach(checkbox => {
                    checkbox.checked = false;
                });
                if (selectAllCheckbox) {
                    selectAllCheckbox.checked = false;
                }
                updateBulkCount();
            });
        }
    }

    /**
     * Toggle bulk mode
     */
    function toggleBulkMode() {
        if (bulkMode) {
            bulkActionsBar.classList.remove('d-none');
            bulkActionsToggle.classList.add('active');
        } else {
            bulkActionsBar.classList.add('d-none');
            bulkActionsToggle.classList.remove('active');

            // Clear selections
            selectedTransactions.clear();
            transactionCheckboxes.forEach(checkbox => {
                checkbox.checked = false;
            });
            if (selectAllCheckbox) {
                selectAllCheckbox.checked = false;
            }
            updateBulkCount();
        }
    }

    /**
     * Update bulk selected count
     */
    function updateBulkCount() {
        if (bulkSelectedCount) {
            bulkSelectedCount.textContent = selectedTransactions.size;
        }
    }

    /**
     * Bulk auto-match
     */
    function bulkAutoMatch() {
        if (selectedTransactions.size === 0) {
            showToast('Please select at least one transaction', 'warning');
            return;
        }

        if (!confirm(`Auto-match ${selectedTransactions.size} selected transactions?`)) {
            return;
        }

        // Disable button
        bulkAutoMatchBtn.disabled = true;
        bulkAutoMatchBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Processing...';

        // Show progress
        showToast('Starting bulk auto-match...', 'info');

        fetch('/modules/bank-transactions/api/bulk-auto-match.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken
            },
            body: JSON.stringify({
                csrf_token: csrfToken,
                transaction_ids: Array.from(selectedTransactions)
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast(
                    `Bulk auto-match completed! ${data.data.matched} matched, ${data.data.review} sent to review, ${data.data.failed} failed.`,
                    'success'
                );

                // Reload page after 2 seconds
                setTimeout(() => {
                    window.location.reload();
                }, 2000);
            } else {
                showToast(data.error.message, 'error');

                // Re-enable button
                bulkAutoMatchBtn.disabled = false;
                bulkAutoMatchBtn.innerHTML = '<i class="fas fa-magic me-1"></i> Auto-Match Selected';
            }
        })
        .catch(error => {
            console.error('Bulk auto-match error:', error);
            showToast('Bulk auto-match failed. Please try again.', 'error');

            // Re-enable button
            bulkAutoMatchBtn.disabled = false;
            bulkAutoMatchBtn.innerHTML = '<i class="fas fa-magic me-1"></i> Auto-Match Selected';
        });
    }

    /**
     * Bulk send to review
     */
    function bulkSendReview() {
        if (selectedTransactions.size === 0) {
            showToast('Please select at least one transaction', 'warning');
            return;
        }

        if (!confirm(`Send ${selectedTransactions.size} selected transactions to review queue?`)) {
            return;
        }

        // Disable button
        bulkSendReviewBtn.disabled = true;
        bulkSendReviewBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Processing...';

        fetch('/modules/bank-transactions/api/bulk-send-review.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken
            },
            body: JSON.stringify({
                csrf_token: csrfToken,
                transaction_ids: Array.from(selectedTransactions)
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast(`${data.data.updated} transactions sent to review queue.`, 'success');

                // Reload page after 1 second
                setTimeout(() => {
                    window.location.reload();
                }, 1000);
            } else {
                showToast(data.error.message, 'error');

                // Re-enable button
                bulkSendReviewBtn.disabled = false;
                bulkSendReviewBtn.innerHTML = '<i class="fas fa-eye me-1"></i> Send to Review';
            }
        })
        .catch(error => {
            console.error('Bulk send to review error:', error);
            showToast('Failed to send to review. Please try again.', 'error');

            // Re-enable button
            bulkSendReviewBtn.disabled = false;
            bulkSendReviewBtn.innerHTML = '<i class="fas fa-eye me-1"></i> Send to Review';
        });
    }

    /**
     * Initialize individual auto-match buttons
     */
    function initAutoMatch() {
        autoMatchBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                const transactionId = this.getAttribute('data-transaction-id');
                autoMatchTransaction(transactionId, this);
            });
        });
    }

    /**
     * Auto-match single transaction
     *
     * @param {string} transactionId Transaction ID
     * @param {HTMLElement} btn Button element
     */
    function autoMatchTransaction(transactionId, btn) {
        // Disable button
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

        fetch('/modules/bank-transactions/api/auto-match-single.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken
            },
            body: JSON.stringify({
                csrf_token: csrfToken,
                transaction_id: transactionId
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                if (data.data.status === 'matched') {
                    showToast('Transaction matched successfully!', 'success');
                } else if (data.data.status === 'review') {
                    showToast('Transaction sent to review queue.', 'info');
                } else {
                    showToast('No suitable match found.', 'warning');
                }

                // Reload page after 1 second
                setTimeout(() => {
                    window.location.reload();
                }, 1000);
            } else {
                showToast(data.error.message, 'error');

                // Re-enable button
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-magic"></i>';
            }
        })
        .catch(error => {
            console.error('Auto-match error:', error);
            showToast('Auto-match failed. Please try again.', 'error');

            // Re-enable button
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-magic"></i>';
        });
    }

    /**
     * Initialize export
     */
    function initExport() {
        if (!exportBtn) return;

        exportBtn.addEventListener('click', function() {
            // Get current filters
            const params = new URLSearchParams(window.location.search);
            params.set('export', 'csv');

            // Redirect to export URL
            window.location.href = `/modules/bank-transactions/api/export.php?${params.toString()}`;
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
