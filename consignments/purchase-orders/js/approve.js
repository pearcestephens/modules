/**
 * Purchase Order Approval JavaScript
 *
 * Handles approval workflow interactions:
 * - Bulk approval selection
 * - Approval/rejection actions
 * - Comments and conditions
 * - Approval history timeline
 * - Real-time status updates
 *
 * @package CIS\Consignments\PurchaseOrders
 * @version 1.0.0
 */

(function($) {
    'use strict';

    const POApproval = {
        // State
        selectedPOs: new Set(),
        currentPOId: null,

        /**
         * Initialize approval interface
         */
        init: function() {
            this.currentPOId = $('#approval-panel').data('po-id') || null;
            this.bindEvents();
            this.loadApprovalHistory();

            console.log('PO Approval initialized', {poId: this.currentPOId});
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Approve button
            $('#approve-btn').on('click', () => this.approveSingle());

            // Reject button
            $('#reject-btn').on('click', () => this.rejectSingle());

            // Approve with conditions
            $('#approve-conditions-btn').on('click', () => this.approveWithConditions());

            // Bulk select checkboxes
            $('#select-all-pos').on('change', (e) => this.toggleSelectAll(e.target.checked));

            $(document).on('change', '.po-select-checkbox', (e) => {
                const poId = $(e.target).val();
                if (e.target.checked) {
                    this.selectedPOs.add(poId);
                } else {
                    this.selectedPOs.delete(poId);
                }
                this.updateBulkActions();
            });

            // Bulk approve
            $('#bulk-approve-btn').on('click', () => this.bulkApprove());

            // Bulk reject
            $('#bulk-reject-btn').on('click', () => this.bulkReject());

            // Request changes
            $('#request-changes-btn').on('click', () => this.requestChanges());

            // Delegate approval
            $('#delegate-btn').on('click', () => this.delegateApproval());
        },

        /**
         * Approve single PO
         */
        approveSingle: function() {
            if (!this.currentPOId) return;

            const comments = $('#approval-comments').val();

            if (!confirm('Approve this Purchase Order?')) {
                return;
            }

            this.approve([this.currentPOId], comments);
        },

        /**
         * Reject single PO
         */
        rejectSingle: function() {
            if (!this.currentPOId) return;

            const reason = prompt('Please provide a reason for rejection:');
            if (!reason) {
                alert('Rejection reason is required');
                return;
            }

            this.reject([this.currentPOId], reason);
        },

        /**
         * Approve with conditions
         */
        approveWithConditions: function() {
            if (!this.currentPOId) return;

            const conditions = prompt('Enter approval conditions:');
            if (!conditions) return;

            this.approve([this.currentPOId], conditions, 'conditional');
        },

        /**
         * Bulk approve selected POs
         */
        bulkApprove: function() {
            if (this.selectedPOs.size === 0) {
                alert('Please select at least one Purchase Order');
                return;
            }

            const comments = prompt('Optional approval comments:');

            if (!confirm(`Approve ${this.selectedPOs.size} Purchase Order(s)?`)) {
                return;
            }

            this.approve(Array.from(this.selectedPOs), comments || '');
        },

        /**
         * Bulk reject selected POs
         */
        bulkReject: function() {
            if (this.selectedPOs.size === 0) {
                alert('Please select at least one Purchase Order');
                return;
            }

            const reason = prompt('Please provide a reason for rejection:');
            if (!reason) {
                alert('Rejection reason is required');
                return;
            }

            this.reject(Array.from(this.selectedPOs), reason);
        },

        /**
         * Request changes to PO
         */
        requestChanges: function() {
            if (!this.currentPOId) return;

            const changes = prompt('Please describe the required changes:');
            if (!changes) {
                alert('Change description is required');
                return;
            }

            $.ajax({
                url: '/modules/consignments/api/purchase-orders/request-changes.php',
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({
                    po_id: this.currentPOId,
                    changes_requested: changes
                }),
                success: (response) => {
                    if (response.success) {
                        this.showToast('Changes requested', 'success');
                        this.loadApprovalHistory();
                        setTimeout(() => window.location.reload(), 1500);
                    } else {
                        alert('Error: ' + response.message);
                    }
                },
                error: () => {
                    alert('Error requesting changes');
                }
            });
        },

        /**
         * Delegate approval to another user
         */
        delegateApproval: function() {
            if (!this.currentPOId) return;

            // This would open a user selection modal
            const delegateTo = prompt('Enter user ID to delegate to:');
            if (!delegateTo) return;

            $.ajax({
                url: '/modules/consignments/api/purchase-orders/delegate.php',
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({
                    po_id: this.currentPOId,
                    delegate_to: delegateTo
                }),
                success: (response) => {
                    if (response.success) {
                        this.showToast('Approval delegated', 'success');
                        setTimeout(() => window.location.reload(), 1500);
                    } else {
                        alert('Error: ' + response.message);
                    }
                },
                error: () => {
                    alert('Error delegating approval');
                }
            });
        },

        /**
         * Approve POs
         */
        approve: function(poIds, comments = '', approvalType = 'full') {
            $('#approve-btn, #bulk-approve-btn').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Approving...');

            $.ajax({
                url: '/modules/consignments/api/purchase-orders/approve.php',
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({
                    po_ids: poIds,
                    comments: comments,
                    approval_type: approvalType
                }),
                success: (response) => {
                    if (response.success) {
                        this.showToast(`${poIds.length} PO(s) approved`, 'success');

                        if (this.currentPOId) {
                            this.loadApprovalHistory();
                            setTimeout(() => window.location.reload(), 1500);
                        } else {
                            setTimeout(() => window.location.reload(), 1500);
                        }
                    } else {
                        alert('Error: ' + response.message);
                        $('#approve-btn, #bulk-approve-btn').prop('disabled', false).html('<i class="fas fa-check"></i> Approve');
                    }
                },
                error: () => {
                    alert('Error approving Purchase Order(s)');
                    $('#approve-btn, #bulk-approve-btn').prop('disabled', false).html('<i class="fas fa-check"></i> Approve');
                }
            });
        },

        /**
         * Reject POs
         */
        reject: function(poIds, reason) {
            $('#reject-btn, #bulk-reject-btn').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Rejecting...');

            $.ajax({
                url: '/modules/consignments/api/purchase-orders/reject.php',
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({
                    po_ids: poIds,
                    reason: reason
                }),
                success: (response) => {
                    if (response.success) {
                        this.showToast(`${poIds.length} PO(s) rejected`, 'warning');

                        if (this.currentPOId) {
                            this.loadApprovalHistory();
                            setTimeout(() => window.location.reload(), 1500);
                        } else {
                            setTimeout(() => window.location.reload(), 1500);
                        }
                    } else {
                        alert('Error: ' + response.message);
                        $('#reject-btn, #bulk-reject-btn').prop('disabled', false).html('<i class="fas fa-times"></i> Reject');
                    }
                },
                error: () => {
                    alert('Error rejecting Purchase Order(s)');
                    $('#reject-btn, #bulk-reject-btn').prop('disabled', false).html('<i class="fas fa-times"></i> Reject');
                }
            });
        },

        /**
         * Load approval history timeline
         */
        loadApprovalHistory: function() {
            if (!this.currentPOId) return;

            $.ajax({
                url: `/modules/consignments/api/purchase-orders/approval-history.php?po_id=${this.currentPOId}`,
                method: 'GET',
                success: (response) => {
                    if (response.success) {
                        this.renderApprovalHistory(response.data);
                    }
                },
                error: () => {
                    console.error('Failed to load approval history');
                }
            });
        },

        /**
         * Render approval history timeline
         */
        renderApprovalHistory: function(history) {
            const $timeline = $('#approval-timeline');
            $timeline.empty();

            if (!history || history.length === 0) {
                $timeline.html('<p class="text-muted">No approval history yet</p>');
                return;
            }

            history.forEach(event => {
                const $item = $(`
                    <div class="timeline-item">
                        <div class="timeline-marker ${this.getEventMarkerClass(event.action)}">
                            <i class="fas ${this.getEventIcon(event.action)}"></i>
                        </div>
                        <div class="timeline-content">
                            <div class="timeline-header">
                                <strong>${event.user_name}</strong>
                                <span class="text-muted">${event.action_display}</span>
                            </div>
                            <div class="timeline-body">
                                ${event.comments || ''}
                            </div>
                            <div class="timeline-footer">
                                <small class="text-muted">${event.created_at_formatted}</small>
                            </div>
                        </div>
                    </div>
                `);

                $timeline.append($item);
            });
        },

        /**
         * Get CSS class for event marker
         */
        getEventMarkerClass: function(action) {
            const classes = {
                'approved': 'bg-success',
                'rejected': 'bg-danger',
                'changes_requested': 'bg-warning',
                'delegated': 'bg-info',
                'submitted': 'bg-primary'
            };
            return classes[action] || 'bg-secondary';
        },

        /**
         * Get icon for event
         */
        getEventIcon: function(action) {
            const icons = {
                'approved': 'fa-check',
                'rejected': 'fa-times',
                'changes_requested': 'fa-edit',
                'delegated': 'fa-user-friends',
                'submitted': 'fa-paper-plane'
            };
            return icons[action] || 'fa-circle';
        },

        /**
         * Toggle select all
         */
        toggleSelectAll: function(checked) {
            $('.po-select-checkbox').prop('checked', checked);

            if (checked) {
                $('.po-select-checkbox').each((idx, el) => {
                    this.selectedPOs.add($(el).val());
                });
            } else {
                this.selectedPOs.clear();
            }

            this.updateBulkActions();
        },

        /**
         * Update bulk action buttons
         */
        updateBulkActions: function() {
            const count = this.selectedPOs.size;

            if (count > 0) {
                $('#bulk-actions-panel').show();
                $('#selected-count').text(count);
            } else {
                $('#bulk-actions-panel').hide();
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
        POApproval.init();
    });

})(jQuery);
