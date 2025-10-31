/**
 * Purchase Orders List JavaScript
 *
 * Handles datatables, filtering, bulk actions, and real-time updates
 * for the purchase orders list page.
 *
 * @package CIS\Consignments\JS
 * @version 1.0.0
 */

(function() {
    'use strict';

    // Configuration
    const CONFIG = {
        apiBase: '/modules/consignments/api/purchase-orders',
        refreshInterval: 30000, // 30 seconds
        autosaveDelay: 2000
    };

    // State
    let dataTable = null;
    let selectedRows = new Set();

    /**
     * Initialize the page
     */
    function init() {
        initDataTable();
        initFilters();
        initBulkActions();
        initRealTimeUpdates();
        bindEvents();
    }

    /**
     * Initialize DataTables
     */
    function initDataTable() {
        dataTable = $('#po-list-table').DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: CONFIG.apiBase + '/list.php',
                type: 'GET',
                data: function(d) {
                    // Add custom filters
                    d.status = $('#filter-status').val();
                    d.supplier_id = $('#filter-supplier').val();
                    d.outlet_id = $('#filter-outlet').val();
                    d.date_from = $('#filter-date-from').val();
                    d.date_to = $('#filter-date-to').val();
                    d.needs_approval = $('#filter-needs-approval').is(':checked') ? 1 : 0;
                    d.datatables = 'true';
                }
            },
            columns: [
                {
                    data: null,
                    orderable: false,
                    className: 'select-checkbox',
                    render: function(data, type, row) {
                        return '<input type="checkbox" class="row-select" data-id="' + row.id + '">';
                    }
                },
                {
                    data: 'consignment_number',
                    render: function(data, type, row) {
                        return '<a href="view.php?id=' + row.id + '" class="fw-bold">' + data + '</a>';
                    }
                },
                {
                    data: 'supplier_name',
                    render: function(data, type, row) {
                        return data || '<em class="text-muted">Unknown</em>';
                    }
                },
                {
                    data: 'outlet_name',
                    render: function(data, type, row) {
                        return data || '<em class="text-muted">Unknown</em>';
                    }
                },
                {
                    data: 'total_amount',
                    render: function(data, type, row) {
                        return '$' + parseFloat(data || 0).toFixed(2);
                    }
                },
                {
                    data: 'status',
                    render: function(data, type, row) {
                        return renderStatusBadge(data);
                    }
                },
                {
                    data: 'created_at',
                    render: function(data, type, row) {
                        return formatDateTime(data);
                    }
                },
                {
                    data: 'due_date',
                    render: function(data, type, row) {
                        if (!data) return '<em class="text-muted">Not set</em>';
                        const isOverdue = new Date(data) < new Date() && row.status !== 'RECEIVED';
                        return isOverdue
                            ? '<span class="text-danger fw-bold">' + formatDate(data) + '</span>'
                            : formatDate(data);
                    }
                },
                {
                    data: null,
                    orderable: false,
                    render: function(data, type, row) {
                        return renderActions(row);
                    }
                }
            ],
            order: [[6, 'desc']], // Created at DESC
            pageLength: 25,
            lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
            dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>rt<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
            language: {
                processing: '<div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div>',
                emptyTable: 'No purchase orders found',
                zeroRecords: 'No matching purchase orders found'
            }
        });

        // Handle row selection
        $('#po-list-table tbody').on('change', '.row-select', function() {
            const id = $(this).data('id');
            if ($(this).is(':checked')) {
                selectedRows.add(id);
            } else {
                selectedRows.delete(id);
            }
            updateBulkActions();
        });

        // Select all checkbox
        $('#select-all').on('change', function() {
            const checked = $(this).is(':checked');
            $('.row-select').prop('checked', checked).trigger('change');
        });
    }

    /**
     * Render status badge
     */
    function renderStatusBadge(status) {
        const badges = {
            'DRAFT': 'secondary',
            'OPEN': 'info',
            'PACKING': 'warning',
            'PACKAGED': 'primary',
            'SENT': 'info',
            'RECEIVING': 'warning',
            'PARTIAL': 'warning',
            'RECEIVED': 'success',
            'CLOSED': 'dark',
            'CANCELLED': 'danger',
            'ARCHIVED': 'secondary'
        };

        const badgeClass = badges[status] || 'secondary';
        return '<span class="badge bg-' + badgeClass + '">' + status + '</span>';
    }

    /**
     * Render action buttons
     */
    function renderActions(row) {
        let html = '<div class="btn-group btn-group-sm">';

        html += '<a href="view.php?id=' + row.id + '" class="btn btn-sm btn-outline-primary" title="View"><i class="fas fa-eye"></i></a>';

        if (row.can_edit) {
            html += '<a href="create.php?id=' + row.id + '" class="btn btn-sm btn-outline-secondary" title="Edit"><i class="fas fa-edit"></i></a>';
        }

        if (row.status === 'DRAFT' || row.status === 'OPEN') {
            html += '<button class="btn btn-sm btn-outline-success btn-submit" data-id="' + row.id + '" title="Submit"><i class="fas fa-paper-plane"></i></button>';
        }

        if (row.status === 'OPEN' && row.needs_approval) {
            html += '<a href="approvals/dashboard.php?id=' + row.id + '" class="btn btn-sm btn-outline-warning" title="Approve"><i class="fas fa-check-circle"></i></a>';
        }

        if (row.status === 'SENT' || row.status === 'RECEIVING' || row.status === 'PARTIAL') {
            html += '<button class="btn btn-sm btn-outline-info btn-receive" data-id="' + row.id + '" title="Receive"><i class="fas fa-box-open"></i></button>';
        }

        if (row.can_delete) {
            html += '<button class="btn btn-sm btn-outline-danger btn-delete" data-id="' + row.id + '" title="Delete"><i class="fas fa-trash"></i></button>';
        }

        html += '</div>';
        return html;
    }

    /**
     * Initialize filters
     */
    function initFilters() {
        // Filter change triggers table reload
        $('#filter-status, #filter-supplier, #filter-outlet, #filter-date-from, #filter-date-to, #filter-needs-approval').on('change', function() {
            dataTable.ajax.reload();
        });

        // Clear filters button
        $('#btn-clear-filters').on('click', function() {
            $('#filter-status, #filter-supplier, #filter-outlet').val('');
            $('#filter-date-from, #filter-date-to').val('');
            $('#filter-needs-approval').prop('checked', false);
            dataTable.ajax.reload();
        });
    }

    /**
     * Initialize bulk actions
     */
    function initBulkActions() {
        $('#btn-bulk-approve').on('click', handleBulkApprove);
        $('#btn-bulk-send').on('click', handleBulkSend);
        $('#btn-bulk-delete').on('click', handleBulkDelete);
        $('#btn-export-csv').on('click', handleExportCSV);
    }

    /**
     * Update bulk action buttons
     */
    function updateBulkActions() {
        const count = selectedRows.size;
        $('.bulk-actions-bar .count').text(count);
        if (count > 0) {
            $('.bulk-actions-bar').removeClass('d-none');
        } else {
            $('.bulk-actions-bar').addClass('d-none');
        }
    }

    /**
     * Handle bulk approve
     */
    function handleBulkApprove() {
        if (selectedRows.size === 0) return;

        if (!confirm('Approve ' + selectedRows.size + ' purchase order(s)?')) {
            return;
        }

        showLoading('Approving purchase orders...');

        $.ajax({
            url: CONFIG.apiBase + '/bulk-approve.php',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({
                ids: Array.from(selectedRows)
            }),
            success: function(response) {
                hideLoading();
                if (response.success) {
                    showSuccess('Purchase orders approved successfully');
                    selectedRows.clear();
                    dataTable.ajax.reload();
                } else {
                    showError(response.error.message || 'Failed to approve purchase orders');
                }
            },
            error: function(xhr) {
                hideLoading();
                showError('Failed to approve purchase orders');
            }
        });
    }

    /**
     * Handle bulk send
     */
    function handleBulkSend() {
        if (selectedRows.size === 0) return;

        if (!confirm('Send ' + selectedRows.size + ' purchase order(s) to suppliers?')) {
            return;
        }

        showLoading('Sending purchase orders...');

        $.ajax({
            url: CONFIG.apiBase + '/bulk-send.php',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({
                ids: Array.from(selectedRows)
            }),
            success: function(response) {
                hideLoading();
                if (response.success) {
                    showSuccess('Purchase orders sent successfully');
                    selectedRows.clear();
                    dataTable.ajax.reload();
                } else {
                    showError(response.error.message || 'Failed to send purchase orders');
                }
            },
            error: function(xhr) {
                hideLoading();
                showError('Failed to send purchase orders');
            }
        });
    }

    /**
     * Handle bulk delete
     */
    function handleBulkDelete() {
        if (selectedRows.size === 0) return;

        if (!confirm('Delete ' + selectedRows.size + ' purchase order(s)? This action cannot be undone.')) {
            return;
        }

        showLoading('Deleting purchase orders...');

        const deletePromises = Array.from(selectedRows).map(id => {
            return $.ajax({
                url: CONFIG.apiBase + '/delete.php',
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({ id: id })
            });
        });

        Promise.all(deletePromises)
            .then(results => {
                hideLoading();
                showSuccess('Purchase orders deleted successfully');
                selectedRows.clear();
                dataTable.ajax.reload();
            })
            .catch(error => {
                hideLoading();
                showError('Some purchase orders could not be deleted');
                dataTable.ajax.reload();
            });
    }

    /**
     * Handle export CSV
     */
    function handleExportCSV() {
        const params = new URLSearchParams({
            export: 'csv',
            status: $('#filter-status').val() || '',
            supplier_id: $('#filter-supplier').val() || '',
            outlet_id: $('#filter-outlet').val() || '',
            date_from: $('#filter-date-from').val() || '',
            date_to: $('#filter-date-to').val() || ''
        });

        window.location.href = CONFIG.apiBase + '/list.php?' + params.toString();
    }

    /**
     * Initialize real-time updates
     */
    function initRealTimeUpdates() {
        setInterval(function() {
            if (!document.hidden) {
                dataTable.ajax.reload(null, false); // Reload without resetting pagination
            }
        }, CONFIG.refreshInterval);
    }

    /**
     * Bind additional events
     */
    function bindEvents() {
        // Quick submit from list
        $(document).on('click', '.btn-submit', function() {
            const id = $(this).data('id');
            submitPurchaseOrder(id);
        });

        // Quick receive from list
        $(document).on('click', '.btn-receive', function() {
            const id = $(this).data('id');
            window.location.href = 'receive.php?id=' + id;
        });

        // Quick delete from list
        $(document).on('click', '.btn-delete', function() {
            const id = $(this).data('id');
            deletePurchaseOrder(id);
        });
    }

    /**
     * Submit purchase order
     */
    function submitPurchaseOrder(id) {
        if (!confirm('Submit this purchase order?')) return;

        showLoading('Submitting purchase order...');

        $.ajax({
            url: CONFIG.apiBase + '/submit.php',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ id: id }),
            success: function(response) {
                hideLoading();
                if (response.success) {
                    showSuccess('Purchase order submitted successfully');
                    dataTable.ajax.reload();
                } else {
                    showError(response.error.message || 'Failed to submit purchase order');
                }
            },
            error: function(xhr) {
                hideLoading();
                showError('Failed to submit purchase order');
            }
        });
    }

    /**
     * Delete purchase order
     */
    function deletePurchaseOrder(id) {
        if (!confirm('Delete this purchase order? This action cannot be undone.')) return;

        showLoading('Deleting purchase order...');

        $.ajax({
            url: CONFIG.apiBase + '/delete.php',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ id: id }),
            success: function(response) {
                hideLoading();
                if (response.success) {
                    showSuccess('Purchase order deleted successfully');
                    dataTable.ajax.reload();
                } else {
                    showError(response.error.message || 'Failed to delete purchase order');
                }
            },
            error: function(xhr) {
                hideLoading();
                showError('Failed to delete purchase order');
            }
        });
    }

    /**
     * Utility functions
     */
    function formatDateTime(datetime) {
        if (!datetime) return '';
        const d = new Date(datetime);
        return d.toLocaleString('en-NZ', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    }

    function formatDate(date) {
        if (!date) return '';
        const d = new Date(date);
        return d.toLocaleDateString('en-NZ', {
            year: 'numeric',
            month: 'short',
            day: 'numeric'
        });
    }

    function showLoading(message) {
        // Implement your loading spinner
        console.log('Loading:', message);
    }

    function hideLoading() {
        // Hide loading spinner
    }

    function showSuccess(message) {
        // Implement your success toast/alert
        alert(message);
    }

    function showError(message) {
        // Implement your error toast/alert
        alert('Error: ' + message);
    }

    // Initialize on document ready
    $(document).ready(init);

})();
