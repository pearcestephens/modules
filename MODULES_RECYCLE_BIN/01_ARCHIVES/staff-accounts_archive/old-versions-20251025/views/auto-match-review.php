<?php
/**
 * Auto-Match Review Interface
 * 
 * Review and approve/reject auto-match suggestions with confidence scores
 * 
 * @package CIS\StaffAccounts\Views
 */

if (!defined('CIS_LOADED')) {
    die('Direct access not permitted');
}
?>

<!-- Auto-Match Review Interface -->
<div id="auto-match-review-section" class="container-fluid mt-4">
    <div class="row">
        <div class="col-12">
            <!-- Header with Controls -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4 class="mb-0">
                        <i class="fas fa-magic text-primary"></i>
                        Auto-Match Review
                        <span id="suggestions-count-badge" class="badge badge-secondary ml-2">Loading...</span>
                    </h4>
                    <div class="btn-group">
                        <button type="button" class="btn btn-outline-primary" onclick="refreshSuggestions()">
                            <i class="fas fa-sync-alt"></i> Refresh
                        </button>
                        <button type="button" class="btn btn-outline-success" onclick="showBulkApproveModal()" disabled id="bulk-approve-btn">
                            <i class="fas fa-check-double"></i> Bulk Approve
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <!-- Filters -->
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="confidence-filter">Minimum Confidence:</label>
                            <select id="confidence-filter" class="form-control" onchange="applyFilters()">
                                <option value="0.5">50% - All Suggestions</option>
                                <option value="0.6" selected>60% - Good Matches</option>
                                <option value="0.7">70% - Strong Matches</option>
                                <option value="0.8">80% - Very Strong</option>
                                <option value="0.9">90% - Excellent Only</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="sort-filter">Sort By:</label>
                            <select id="sort-filter" class="form-control" onchange="applyFilters()">
                                <option value="confidence">Confidence Score</option>
                                <option value="amount">Blocked Amount</option>
                                <option value="created">Date Created</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label>&nbsp;</label>
                            <div class="form-control border-0 bg-light">
                                <small class="text-muted">
                                    <i class="fas fa-info-circle"></i>
                                    Review AI-suggested employee-customer matches
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Loading State -->
            <div id="loading-suggestions" class="text-center py-5">
                <div class="spinner-border text-primary" role="status">
                    <span class="sr-only">Loading suggestions...</span>
                </div>
                <p class="mt-3 text-muted">Loading auto-match suggestions...</p>
            </div>

            <!-- No Suggestions State -->
            <div id="no-suggestions" class="card text-center py-5" style="display: none;">
                <div class="card-body">
                    <i class="fas fa-check-circle text-success fa-3x mb-3"></i>
                    <h5>No Auto-Match Suggestions</h5>
                    <p class="text-muted">All employees have been reviewed or no confident matches found.</p>
                    <button type="button" class="btn btn-primary" onclick="refreshSuggestions()">
                        <i class="fas fa-sync-alt"></i> Check Again
                    </button>
                </div>
            </div>

            <!-- Suggestions Container -->
            <div id="suggestions-container" style="display: none;">
                <!-- Individual suggestion cards will be inserted here -->
            </div>
        </div>
    </div>
</div>

<!-- Individual Suggestion Card Template -->
<template id="suggestion-card-template">
    <div class="card mb-4 suggestion-card" data-mapping-id="">
        <div class="card-header">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h6 class="mb-1">
                        <i class="fas fa-user text-primary"></i>
                        <span class="employee-name font-weight-bold"></span>
                    </h6>
                    <small class="text-muted employee-email"></small>
                </div>
                <div class="col-md-3 text-center">
                    <span class="confidence-badge badge badge-lg"></span>
                    <div class="confidence-percentage small text-muted mt-1"></div>
                </div>
                <div class="col-md-3 text-right">
                    <div class="blocked-amount font-weight-bold text-danger"></div>
                    <small class="text-muted deduction-count"></small>
                </div>
            </div>
        </div>
        <div class="card-body">
            <!-- Side-by-side comparison -->
            <div class="row">
                <!-- Employee Details -->
                <div class="col-md-5">
                    <h6 class="text-primary mb-3">
                        <i class="fas fa-id-badge"></i> Employee (Xero)
                    </h6>
                    <div class="employee-details">
                        <div class="detail-row">
                            <strong>Name:</strong> <span class="employee-name-detail"></span>
                        </div>
                        <div class="detail-row">
                            <strong>Email:</strong> <span class="employee-email-detail"></span>
                        </div>
                        <div class="detail-row">
                            <strong>ID:</strong> <span class="employee-id-detail"></span>
                        </div>
                    </div>
                </div>

                <!-- Match Indicator -->
                <div class="col-md-2 text-center">
                    <div class="match-indicator py-4">
                        <i class="fas fa-arrows-alt-h text-muted fa-2x"></i>
                        <div class="confidence-bar mt-2">
                            <div class="progress" style="height: 8px;">
                                <div class="progress-bar confidence-progress" role="progressbar"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Customer Details -->
                <div class="col-md-5">
                    <h6 class="text-success mb-3">
                        <i class="fas fa-user-tag"></i> Customer (Vend)
                    </h6>
                    <div class="customer-details">
                        <div class="detail-row">
                            <strong>Name:</strong> <span class="customer-name-detail"></span>
                        </div>
                        <div class="detail-row">
                            <strong>Email:</strong> <span class="customer-email-detail"></span>
                        </div>
                        <div class="detail-row">
                            <strong>Code:</strong> <span class="customer-code-detail"></span>
                        </div>
                        <div class="detail-row">
                            <strong>Phone:</strong> <span class="customer-phone-detail"></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Match Reasoning -->
            <div class="mt-4">
                <h6 class="text-info">
                    <i class="fas fa-brain"></i> AI Reasoning
                </h6>
                <div class="match-reasoning bg-light p-3 rounded">
                    <!-- Reasoning details will be inserted here -->
                </div>
            </div>

            <!-- Risk Factors -->
            <div class="risk-factors mt-3" style="display: none;">
                <h6 class="text-warning">
                    <i class="fas fa-exclamation-triangle"></i> Risk Factors
                </h6>
                <div class="risk-list">
                    <!-- Risk items will be inserted here -->
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="mt-4 pt-3 border-top">
                <div class="row">
                    <div class="col-md-8">
                        <div class="form-row">
                            <div class="col-md-6">
                                <input type="text" class="form-control approval-notes" placeholder="Optional notes...">
                            </div>
                            <div class="col-md-6">
                                <select class="form-control rejection-reason" style="display: none;">
                                    <option value="">Select rejection reason...</option>
                                    <option value="name_mismatch">Name doesn't match</option>
                                    <option value="email_mismatch">Email doesn't match</option>
                                    <option value="wrong_person">Wrong person entirely</option>
                                    <option value="low_confidence">Confidence too low</option>
                                    <option value="manual_review">Needs manual review</option>
                                    <option value="other">Other reason</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 text-right">
                        <div class="btn-group action-buttons">
                            <button type="button" class="btn btn-success approve-btn" onclick="approveSuggestion(this)">
                                <i class="fas fa-check"></i> Approve
                            </button>
                            <button type="button" class="btn btn-danger reject-btn" onclick="rejectSuggestion(this)">
                                <i class="fas fa-times"></i> Reject
                            </button>
                            <button type="button" class="btn btn-secondary cancel-btn" onclick="cancelAction(this)" style="display: none;">
                                Cancel
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<!-- Bulk Approve Modal -->
<div class="modal fade" id="bulk-approve-modal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-check-double text-success"></i>
                    Bulk Approve Auto-Matches
                </h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i>
                    You are about to approve <strong id="bulk-count">0</strong> auto-match suggestions.
                    This action cannot be undone.
                </div>
                
                <div class="form-group">
                    <label for="bulk-notes">Approval Notes (Optional):</label>
                    <textarea id="bulk-notes" class="form-control" rows="3" 
                        placeholder="Add notes for this bulk approval..."></textarea>
                </div>
                
                <div id="bulk-preview" class="bg-light p-3 rounded" style="max-height: 300px; overflow-y: auto;">
                    <!-- Selected items preview -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" onclick="confirmBulkApprove()">
                    <i class="fas fa-check-double"></i> Approve All
                </button>
            </div>
        </div>
    </div>
</div>

<style>
/* Auto-Match Review Specific Styles */
.suggestion-card {
    transition: all 0.3s ease;
}

.suggestion-card:hover {
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    transform: translateY(-2px);
}

.confidence-badge {
    font-size: 0.9rem;
    padding: 8px 12px;
}

.confidence-progress {
    transition: width 0.5s ease;
}

.detail-row {
    margin-bottom: 8px;
    padding: 4px 0;
}

.detail-row strong {
    display: inline-block;
    width: 60px;
    color: #6c757d;
}

.match-indicator {
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    height: 100%;
}

.risk-list .alert {
    margin-bottom: 5px;
    padding: 8px 12px;
    font-size: 0.875rem;
}

.action-buttons .btn {
    min-width: 80px;
}

.suggestion-card.processing {
    opacity: 0.7;
    pointer-events: none;
}

.suggestion-card.approved {
    border-left: 4px solid #28a745;
    background-color: #f8fff9;
}

.suggestion-card.rejected {
    border-left: 4px solid #dc3545;
    background-color: #fff8f8;
}

/* Selection checkboxes for bulk operations */
.suggestion-card .card-header {
    position: relative;
}

.suggestion-card .selection-checkbox {
    position: absolute;
    top: 15px;
    right: 15px;
}

.bulk-selection-active .selection-checkbox {
    display: block !important;
}

/* Animation for confidence bars */
@keyframes fillProgress {
    from { width: 0%; }
    to { width: var(--confidence-percent); }
}

.confidence-progress {
    animation: fillProgress 1s ease-out;
}
</style>

<script>
// Auto-Match Review JavaScript will be loaded from separate file
</script>