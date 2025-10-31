<?php
/**
 * Manual Mapping Tools Interface
 * 
 * Advanced customer search and manual employee-customer mapping
 * 
 * @package CIS\StaffAccounts\Views
 */

if (!defined('CIS_LOADED')) {
    die('Direct access not permitted');
}
?>

<!-- Manual Mapping Tools Interface -->
<div id="manual-mapping-section" class="container-fluid mt-4">
    <div class="row">
        <div class="col-12">
            <!-- Header with Current Employee Selection -->
            <div class="card mb-4">
                <div class="card-header">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h4 class="mb-0">
                                <i class="fas fa-search text-primary"></i>
                                Manual Employee Mapping
                            </h4>
                            <small class="text-muted">Search and select customers for manual employee mapping</small>
                        </div>
                        <div class="col-md-4 text-right">
                            <button type="button" class="btn btn-outline-info" onclick="showMappingTips()">
                                <i class="fas fa-question-circle"></i> Mapping Tips
                            </button>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <!-- Employee Selection -->
                    <div class="row">
                        <div class="col-md-6">
                            <label for="employee-selector">Select Employee to Map:</label>
                            <select id="employee-selector" class="form-control" onchange="selectEmployeeForMapping()">
                                <option value="">Choose an unmapped employee...</option>
                                <!-- Options will be loaded dynamically -->
                            </select>
                        </div>
                        <div class="col-md-6">
                            <div id="selected-employee-info" class="bg-light p-3 rounded" style="display: none;">
                                <h6 class="text-primary mb-2">
                                    <i class="fas fa-user"></i> Selected Employee
                                </h6>
                                <div id="employee-details">
                                    <!-- Employee details will be shown here -->
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Customer Search Interface -->
            <div id="customer-search-section" style="display: none;">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-search-plus text-success"></i>
                            Customer Search & Selection
                        </h5>
                    </div>
                    <div class="card-body">
                        <!-- Advanced Search Form -->
                        <form id="customer-search-form" onsubmit="searchCustomers(); return false;">
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="search-query">Search Query:</label>
                                        <input type="text" id="search-query" class="form-control" 
                                               placeholder="Name, email, phone, customer code...">
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label for="store-filter">Store:</label>
                                        <select id="store-filter" class="form-control">
                                            <option value="">All Stores</option>
                                            <!-- Options loaded dynamically -->
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label for="email-filter">Email:</label>
                                        <select id="email-filter" class="form-control">
                                            <option value="">Any</option>
                                            <option value="yes">Has Email</option>
                                            <option value="no">No Email</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label>&nbsp;</label>
                                        <div class="form-check">
                                            <input type="checkbox" id="exclude-mapped" class="form-check-input" checked>
                                            <label for="exclude-mapped" class="form-check-label">
                                                Exclude mapped
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label>&nbsp;</label>
                                        <button type="submit" class="btn btn-primary btn-block">
                                            <i class="fas fa-search"></i> Search
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Search Results -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0">
                            <i class="fas fa-list"></i>
                            Search Results
                            <span id="results-count-badge" class="badge badge-secondary ml-2">0</span>
                        </h6>
                        <div class="btn-group btn-group-sm">
                            <button type="button" class="btn btn-outline-secondary" onclick="previousPage()" id="prev-btn" disabled>
                                <i class="fas fa-chevron-left"></i> Prev
                            </button>
                            <button type="button" class="btn btn-outline-secondary" onclick="nextPage()" id="next-btn" disabled>
                                Next <i class="fas fa-chevron-right"></i>
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <!-- Loading State -->
                        <div id="search-loading" class="text-center py-4" style="display: none;">
                            <div class="spinner-border text-primary" role="status">
                                <span class="sr-only">Searching...</span>
                            </div>
                            <p class="mt-3 text-muted">Searching customers...</p>
                        </div>

                        <!-- No Results State -->
                        <div id="no-results" class="text-center py-4" style="display: none;">
                            <i class="fas fa-search text-muted fa-3x mb-3"></i>
                            <h6>No Customers Found</h6>
                            <p class="text-muted">Try adjusting your search criteria or filters.</p>
                        </div>

                        <!-- Search Results Container -->
                        <div id="search-results-container">
                            <!-- Customer result cards will be inserted here -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Customer Details Modal -->
<div class="modal fade" id="customer-details-modal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-user-tag text-success"></i>
                    Customer Details & Mapping Preview
                </h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <!-- Employee Info -->
                    <div class="col-md-5">
                        <div class="card border-primary">
                            <div class="card-header bg-primary text-white">
                                <h6 class="mb-0">
                                    <i class="fas fa-id-badge"></i>
                                    Employee (Xero)
                                </h6>
                            </div>
                            <div class="card-body" id="modal-employee-info">
                                <!-- Employee details will be loaded here -->
                            </div>
                        </div>
                    </div>

                    <!-- Mapping Arrow -->
                    <div class="col-md-2 text-center d-flex align-items-center justify-content-center">
                        <div class="mapping-arrow">
                            <i class="fas fa-arrow-right fa-3x text-success"></i>
                            <div class="mt-2">
                                <small class="text-muted">Mapping</small>
                            </div>
                        </div>
                    </div>

                    <!-- Customer Info -->
                    <div class="col-md-5">
                        <div class="card border-success">
                            <div class="card-header bg-success text-white">
                                <h6 class="mb-0">
                                    <i class="fas fa-user-tag"></i>
                                    Customer (Vend)
                                </h6>
                            </div>
                            <div class="card-body" id="modal-customer-info">
                                <!-- Customer details will be loaded here -->
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Validation Results -->
                <div id="validation-results" class="mt-4">
                    <!-- Validation messages will be shown here -->
                </div>

                <!-- Purchase History -->
                <div id="customer-purchase-history" class="mt-4" style="display: none;">
                    <h6>
                        <i class="fas fa-shopping-cart text-info"></i>
                        Recent Purchase History
                    </h6>
                    <div id="purchase-history-content">
                        <!-- Purchase history will be loaded here -->
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <div class="row w-100">
                    <div class="col-md-8">
                        <textarea id="mapping-notes" class="form-control" rows="2" 
                                  placeholder="Optional mapping notes..."></textarea>
                    </div>
                    <div class="col-md-4 text-right">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-success" onclick="confirmMapping()" id="confirm-mapping-btn">
                            <i class="fas fa-link"></i> Create Mapping
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Mapping Tips Modal -->
<div class="modal fade" id="mapping-tips-modal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-lightbulb text-warning"></i>
                    Manual Mapping Tips
                </h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6 class="text-success">
                            <i class="fas fa-check-circle"></i>
                            Good Mapping Indicators
                        </h6>
                        <ul class="list-unstyled">
                            <li><i class="fas fa-check text-success"></i> Email addresses match exactly</li>
                            <li><i class="fas fa-check text-success"></i> Names are identical or very similar</li>
                            <li><i class="fas fa-check text-success"></i> Purchase patterns match work schedule</li>
                            <li><i class="fas fa-check text-success"></i> Employee discount usage evident</li>
                            <li><i class="fas fa-check text-success"></i> Consistent purchase history</li>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <h6 class="text-warning">
                            <i class="fas fa-exclamation-triangle"></i>
                            Warning Signs
                        </h6>
                        <ul class="list-unstyled">
                            <li><i class="fas fa-times text-danger"></i> Completely different names</li>
                            <li><i class="fas fa-times text-danger"></i> Email domains don't match</li>
                            <li><i class="fas fa-times text-danger"></i> No recent purchase activity</li>
                            <li><i class="fas fa-times text-danger"></i> Customer already mapped to someone else</li>
                            <li><i class="fas fa-times text-danger"></i> Unusual purchase patterns</li>
                        </ul>
                    </div>
                </div>
                <hr>
                <div class="row">
                    <div class="col-12">
                        <h6 class="text-info">
                            <i class="fas fa-search"></i>
                            Search Tips
                        </h6>
                        <div class="row">
                            <div class="col-md-4">
                                <strong>By Name:</strong>
                                <small class="text-muted d-block">Search partial names, nicknames, or variations</small>
                            </div>
                            <div class="col-md-4">
                                <strong>By Email:</strong>
                                <small class="text-muted d-block">Use full email or domain (@ecigdis.co.nz)</small>
                            </div>
                            <div class="col-md-4">
                                <strong>By Phone:</strong>
                                <small class="text-muted d-block">Search with or without formatting</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" data-dismiss="modal">Got It!</button>
            </div>
        </div>
    </div>
</div>

<!-- Customer Card Template -->
<template id="customer-card-template">
    <div class="card mb-3 customer-result-card" data-customer-id="">
        <div class="card-body">
            <div class="row align-items-center">
                <div class="col-md-4">
                    <h6 class="mb-1 customer-name"></h6>
                    <small class="text-muted customer-email"></small>
                    <div class="customer-code text-muted small"></div>
                </div>
                <div class="col-md-3">
                    <div class="customer-contact">
                        <div class="customer-phone small"></div>
                        <div class="customer-store small text-muted"></div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="purchase-stats">
                        <div class="total-amount font-weight-bold"></div>
                        <div class="purchase-count small text-muted"></div>
                        <div class="last-purchase small text-muted"></div>
                    </div>
                </div>
                <div class="col-md-2 text-right">
                    <div class="mapping-status-badge mb-2"></div>
                    <button type="button" class="btn btn-primary btn-sm" onclick="viewCustomerDetails(this)">
                        <i class="fas fa-eye"></i> View Details
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<style>
/* Manual Mapping Specific Styles */
.customer-result-card {
    transition: all 0.3s ease;
    cursor: pointer;
}

.customer-result-card:hover {
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    transform: translateY(-2px);
}

.mapping-arrow {
    opacity: 0.8;
}

.validation-item {
    padding: 8px 12px;
    margin-bottom: 5px;
    border-radius: 4px;
}

.validation-success {
    background-color: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.validation-warning {
    background-color: #fff3cd;
    color: #856404;
    border: 1px solid #ffeaa7;
}

.validation-error {
    background-color: #f8d7da;
    color: #721c24;
    border: 1px solid #f1b0b7;
}

.purchase-history-item {
    border-left: 3px solid #007bff;
    padding-left: 15px;
    margin-bottom: 10px;
}

#customer-search-section {
    animation: slideDown 0.3s ease-out;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.detail-row {
    display: flex;
    justify-content: space-between;
    padding: 4px 0;
    border-bottom: 1px solid #eee;
}

.detail-row:last-child {
    border-bottom: none;
}

.detail-label {
    font-weight: 600;
    color: #6c757d;
    min-width: 100px;
}

.detail-value {
    text-align: right;
    flex: 1;
}
</style>

<script>
// Manual Mapping JavaScript will be loaded from separate file
</script>