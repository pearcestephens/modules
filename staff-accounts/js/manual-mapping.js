/**
 * Manual Mapping Tools JavaScript
 * 
 * Handles customer search, selection, and manual employee-customer mapping
 * 
 * @package CIS\StaffAccounts\JS
 * @version 1.0.0
 */

// Global state
let currentEmployee = null;
let currentCustomer = null;
let searchResults = [];
let currentPage = 0;
let totalResults = 0;
let pageSize = 20;
let availableStores = [];
let customerGroups = [];

/**
 * Initialize the manual mapping interface
 */
function initManualMapping() {
    console.log('Initializing Manual Mapping Tools...');
    
    // Load initial data
    loadUnmappedEmployees();
    loadAvailableStores();
    loadCustomerGroups();
    
    // Set up event listeners
    setupManualMappingEventListeners();
}

/**
 * Set up event listeners for manual mapping
 */
function setupManualMappingEventListeners() {
    // Search form submission
    $('#customer-search-form').on('submit', function(e) {
        e.preventDefault();
        searchCustomers();
    });
    
    // Real-time search on input change (debounced)
    let searchTimeout;
    $('#search-query').on('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(function() {
            if ($('#search-query').val().length >= 2 || $('#search-query').val().length === 0) {
                searchCustomers();
            }
        }, 500);
    });
    
    // Filter changes trigger search
    $('#store-filter, #email-filter, #exclude-mapped').on('change', function() {
        searchCustomers();
    });
}

/**
 * Load unmapped employees for selection
 */
async function loadUnmappedEmployees() {
    try {
        const response = await fetch('api/employee-mapping.php?action=unmapped_employees&limit=100');
        const data = await response.json();
        
        if (data.success) {
            populateEmployeeSelector(data.data);
        } else {
            throw new Error(data.error || 'Failed to load employees');
        }
        
    } catch (error) {
        console.error('Error loading unmapped employees:', error);
        showManualMappingMessage('Failed to load unmapped employees: ' + error.message, 'error');
    }
}

/**
 * Populate employee selector dropdown
 */
function populateEmployeeSelector(employees) {
    const selector = $('#employee-selector');
    selector.empty().append('<option value="">Choose an unmapped employee...</option>');
    
    employees.forEach(employee => {
        const blockedAmount = parseFloat(employee.blocked_amount || 0);
        const displayText = `${employee.employee_name} (${employee.deduction_count} deductions, $${blockedAmount.toFixed(2)})`;
        
        selector.append(
            $('<option>', {
                value: employee.xero_employee_id,
                text: displayText,
                'data-employee': JSON.stringify(employee)
            })
        );
    });
}

/**
 * Handle employee selection for mapping
 */
function selectEmployeeForMapping() {
    const selector = $('#employee-selector');
    const selectedOption = selector.find('option:selected');
    
    if (!selectedOption.val()) {
        currentEmployee = null;
        $('#selected-employee-info').hide();
        $('#customer-search-section').hide();
        return;
    }
    
    // Get employee data
    currentEmployee = JSON.parse(selectedOption.attr('data-employee'));
    
    // Show employee info
    displaySelectedEmployee(currentEmployee);
    
    // Show customer search section
    $('#customer-search-section').show();
    
    // Trigger initial search
    searchCustomers();
}

/**
 * Display selected employee information
 */
function displaySelectedEmployee(employee) {
    const blockedAmount = parseFloat(employee.blocked_amount || 0);
    
    const html = `
        <div class="detail-row">
            <span class="detail-label">Name:</span>
            <span class="detail-value">${employee.employee_name}</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Email:</span>
            <span class="detail-value">${employee.employee_email || 'No email'}</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Deductions:</span>
            <span class="detail-value">${employee.deduction_count} pending</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Blocked Amount:</span>
            <span class="detail-value text-danger font-weight-bold">$${blockedAmount.toFixed(2)}</span>
        </div>
    `;
    
    $('#employee-details').html(html);
    $('#selected-employee-info').show();
}

/**
 * Load available stores for filtering
 */
async function loadAvailableStores() {
    try {
        const response = await fetch('api/customer-search.php?action=stores');
        const data = await response.json();
        
        if (data.success) {
            availableStores = data.data;
            populateStoreFilter(availableStores);
        }
        
    } catch (error) {
        console.error('Error loading stores:', error);
    }
}

/**
 * Populate store filter dropdown
 */
function populateStoreFilter(stores) {
    const filter = $('#store-filter');
    filter.empty().append('<option value="">All Stores</option>');
    
    stores.forEach(store => {
        filter.append(
            $('<option>', {
                value: store.id,
                text: `${store.name} (${store.customer_count} customers)`
            })
        );
    });
}

/**
 * Load customer groups for filtering
 */
async function loadCustomerGroups() {
    try {
        const response = await fetch('api/customer-search.php?action=customer_groups');
        const data = await response.json();
        
        if (data.success) {
            customerGroups = data.data;
            // Could add customer group filter if needed
        }
        
    } catch (error) {
        console.error('Error loading customer groups:', error);
    }
}

/**
 * Search customers with current criteria
 */
async function searchCustomers() {
    if (!currentEmployee) {
        return;
    }
    
    try {
        showSearchLoading();
        
        const query = $('#search-query').val().trim();
        const storeId = $('#store-filter').val();
        const hasEmail = $('#email-filter').val();
        const excludeMapped = $('#exclude-mapped').is(':checked');
        
        // Build query parameters
        const params = new URLSearchParams({
            action: 'search',
            q: query,
            limit: pageSize,
            offset: currentPage * pageSize,
            exclude_mapped: excludeMapped ? 'true' : 'false'
        });
        
        if (storeId) params.append('store_id', storeId);
        if (hasEmail) params.append('has_email', hasEmail);
        
        const response = await fetch(`api/customer-search.php?${params}`);
        const data = await response.json();
        
        if (data.success) {
            searchResults = data.data;
            totalResults = data.pagination.total;
            displaySearchResults(searchResults);
            updatePaginationControls(data.pagination);
        } else {
            throw new Error(data.error || 'Search failed');
        }
        
    } catch (error) {
        console.error('Error searching customers:', error);
        showSearchError('Customer search failed: ' + error.message);
    } finally {
        hideSearchLoading();
    }
}

/**
 * Display search results
 */
function displaySearchResults(customers) {
    const container = $('#search-results-container');
    const template = document.getElementById('customer-card-template');
    
    container.empty();
    
    if (customers.length === 0) {
        showNoResults();
        return;
    }
    
    hideNoResults();
    
    customers.forEach(customer => {
        const card = createCustomerCard(customer, template);
        container.append(card);
    });
    
    updateResultsCount(customers.length, totalResults);
}

/**
 * Create customer result card
 */
function createCustomerCard(customer, template) {
    const card = $(template.content.cloneNode(true));
    
    // Set data attribute
    card.find('.customer-result-card').attr('data-customer-id', customer.id);
    
    // Fill customer details
    card.find('.customer-name').text(customer.name);
    card.find('.customer-email').text(customer.email || 'No email');
    card.find('.customer-code').text(customer.customer_code ? `Code: ${customer.customer_code}` : 'No code');
    card.find('.customer-phone').text(customer.phone || 'No phone');
    card.find('.customer-store').text(customer.outlet_name || 'No store');
    
    // Purchase statistics
    const totalAmount = parseFloat(customer.total_amount || 0);
    const purchaseCount = parseInt(customer.total_purchases || 0);
    
    card.find('.total-amount').text(`$${totalAmount.toFixed(2)}`);
    card.find('.purchase-count').text(`${purchaseCount} purchases`);
    
    if (customer.last_purchase_date) {
        const lastPurchase = new Date(customer.last_purchase_date);
        card.find('.last-purchase').text(`Last: ${lastPurchase.toLocaleDateString()}`);
    } else {
        card.find('.last-purchase').text('No purchases');
    }
    
    // Mapping status
    const statusBadge = card.find('.mapping-status-badge');
    if (customer.mapping_status === 'mapped') {
        statusBadge.html('<span class="badge badge-warning">Already Mapped</span>');
        card.find('.btn').prop('disabled', true).text('Unavailable');
    } else {
        statusBadge.html('<span class="badge badge-success">Available</span>');
    }
    
    return card;
}

/**
 * View customer details and mapping preview
 */
async function viewCustomerDetails(button) {
    const card = $(button).closest('.customer-result-card');
    const customerId = parseInt(card.attr('data-customer-id'));
    
    if (!currentEmployee) {
        alert('Please select an employee first');
        return;
    }
    
    try {
        // Show loading in button
        const originalText = $(button).html();
        $(button).html('<i class="fas fa-spinner fa-spin"></i> Loading...');
        
        // Get detailed customer information
        const response = await fetch(`api/customer-search.php?action=details&customer_id=${customerId}`);
        const data = await response.json();
        
        if (data.success) {
            currentCustomer = data.data;
            await showCustomerDetailsModal(currentCustomer);
        } else {
            throw new Error(data.error || 'Failed to load customer details');
        }
        
    } catch (error) {
        console.error('Error loading customer details:', error);
        showManualMappingMessage('Failed to load customer details: ' + error.message, 'error');
    } finally {
        $(button).html(originalText);
    }
}

/**
 * Show customer details modal with mapping preview
 */
async function showCustomerDetailsModal(customer) {
    // Fill employee info
    displayModalEmployeeInfo(currentEmployee);
    
    // Fill customer info
    displayModalCustomerInfo(customer);
    
    // Validate mapping
    await validateMapping();
    
    // Show purchase history if available
    if (customer.recent_purchases && customer.recent_purchases.length > 0) {
        displayPurchaseHistory(customer.recent_purchases);
    }
    
    // Show modal
    $('#customer-details-modal').modal('show');
}

/**
 * Display employee info in modal
 */
function displayModalEmployeeInfo(employee) {
    const blockedAmount = parseFloat(employee.blocked_amount || 0);
    
    const html = `
        <div class="detail-row">
            <span class="detail-label">Name:</span>
            <span class="detail-value">${employee.employee_name}</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Email:</span>
            <span class="detail-value">${employee.employee_email || 'No email'}</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Employee ID:</span>
            <span class="detail-value">${employee.xero_employee_id}</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Deductions:</span>
            <span class="detail-value">${employee.deduction_count} pending</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Blocked Amount:</span>
            <span class="detail-value text-danger font-weight-bold">$${blockedAmount.toFixed(2)}</span>
        </div>
    `;
    
    $('#modal-employee-info').html(html);
}

/**
 * Display customer info in modal
 */
function displayModalCustomerInfo(customer) {
    const totalAmount = parseFloat(customer.total_amount || 0);
    const purchaseCount = parseInt(customer.total_purchases || 0);
    
    const html = `
        <div class="detail-row">
            <span class="detail-label">Name:</span>
            <span class="detail-value">${customer.name}</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Email:</span>
            <span class="detail-value">${customer.email || 'No email'}</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Phone:</span>
            <span class="detail-value">${customer.phone || 'No phone'}</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Customer Code:</span>
            <span class="detail-value">${customer.customer_code || 'No code'}</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Store:</span>
            <span class="detail-value">${customer.outlet_name || 'No store'}</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Total Purchases:</span>
            <span class="detail-value">${purchaseCount} ($${totalAmount.toFixed(2)})</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Member Since:</span>
            <span class="detail-value">${new Date(customer.created_at).toLocaleDateString()}</span>
        </div>
    `;
    
    $('#modal-customer-info').html(html);
}

/**
 * Validate the potential mapping
 */
async function validateMapping() {
    try {
        const response = await fetch(`api/customer-search.php?action=validate&employee_id=${currentEmployee.xero_employee_id}&customer_id=${currentCustomer.id}`);
        const data = await response.json();
        
        if (data.success) {
            displayValidationResults(data.data);
        }
        
    } catch (error) {
        console.error('Error validating mapping:', error);
    }
}

/**
 * Display validation results
 */
function displayValidationResults(validation) {
    const container = $('#validation-results');
    let html = '<h6><i class="fas fa-clipboard-check text-info"></i> Mapping Validation</h6>';
    
    // Overall status
    if (validation.valid) {
        html += '<div class="validation-item validation-success"><i class="fas fa-check-circle"></i> Mapping validation passed</div>';
    } else {
        html += '<div class="validation-item validation-error"><i class="fas fa-times-circle"></i> Mapping validation failed</div>';
    }
    
    // Errors
    if (validation.errors && validation.errors.length > 0) {
        validation.errors.forEach(error => {
            html += `<div class="validation-item validation-error"><i class="fas fa-times"></i> ${error}</div>`;
        });
    }
    
    // Warnings
    if (validation.warnings && validation.warnings.length > 0) {
        validation.warnings.forEach(warning => {
            html += `<div class="validation-item validation-warning"><i class="fas fa-exclamation-triangle"></i> ${warning}</div>`;
        });
    }
    
    // Suggestions
    if (validation.suggestions && validation.suggestions.length > 0) {
        validation.suggestions.forEach(suggestion => {
            html += `<div class="validation-item validation-success"><i class="fas fa-lightbulb"></i> ${suggestion}</div>`;
        });
    }
    
    container.html(html);
    
    // Enable/disable confirm button
    $('#confirm-mapping-btn').prop('disabled', !validation.valid);
}

/**
 * Display purchase history
 */
function displayPurchaseHistory(purchases) {
    let html = '';
    
    purchases.slice(0, 5).forEach(purchase => {
        const date = new Date(purchase.date);
        const amount = parseFloat(purchase.amount);
        
        html += `
            <div class="purchase-history-item">
                <div class="d-flex justify-content-between">
                    <strong>${date.toLocaleDateString()}</strong>
                    <span class="text-success">$${amount.toFixed(2)}</span>
                </div>
                <small class="text-muted">${purchase.items} items</small>
            </div>
        `;
    });
    
    $('#purchase-history-content').html(html);
    $('#customer-purchase-history').show();
}

/**
 * Confirm and create the mapping
 */
async function confirmMapping() {
    if (!currentEmployee || !currentCustomer) {
        alert('Employee and customer must be selected');
        return;
    }
    
    try {
        // Show processing state
        const button = $('#confirm-mapping-btn');
        const originalText = button.html();
        button.html('<i class="fas fa-spinner fa-spin"></i> Creating...').prop('disabled', true);
        
        const notes = $('#mapping-notes').val().trim();
        
        const response = await fetch('api/customer-search.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'create_mapping',
                employee_id: currentEmployee.xero_employee_id,
                customer_id: currentCustomer.id,
                created_by: 'current_user', // Replace with actual user
                notes: notes,
                verification: {
                    employee_name: currentEmployee.employee_name,
                    customer_name: currentCustomer.name,
                    verified_at: new Date().toISOString()
                }
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Success!
            $('#customer-details-modal').modal('hide');
            showManualMappingMessage('Manual mapping created successfully!', 'success');
            
            // Reset and refresh
            resetMappingInterface();
            
        } else {
            throw new Error(data.error || 'Mapping creation failed');
        }
        
    } catch (error) {
        console.error('Error creating mapping:', error);
        showManualMappingMessage('Failed to create mapping: ' + error.message, 'error');
        
        // Restore button
        $('#confirm-mapping-btn').html(originalText).prop('disabled', false);
    }
}

/**
 * Reset the mapping interface after successful mapping
 */
function resetMappingInterface() {
    // Clear selections
    currentEmployee = null;
    currentCustomer = null;
    
    // Reset UI
    $('#employee-selector').val('');
    $('#selected-employee-info').hide();
    $('#customer-search-section').hide();
    $('#mapping-notes').val('');
    
    // Reload unmapped employees
    loadUnmappedEmployees();
}

/**
 * Show mapping tips modal
 */
function showMappingTips() {
    $('#mapping-tips-modal').modal('show');
}

/**
 * Pagination functions
 */
function previousPage() {
    if (currentPage > 0) {
        currentPage--;
        searchCustomers();
    }
}

function nextPage() {
    if ((currentPage + 1) * pageSize < totalResults) {
        currentPage++;
        searchCustomers();
    }
}

/**
 * Update pagination controls
 */
function updatePaginationControls(pagination) {
    $('#prev-btn').prop('disabled', pagination.offset === 0);
    $('#next-btn').prop('disabled', !pagination.has_more);
}

/**
 * Update results count display
 */
function updateResultsCount(shown, total) {
    $('#results-count-badge').text(`${shown} of ${total}`);
}

/**
 * Show search loading state
 */
function showSearchLoading() {
    $('#search-loading').show();
    $('#search-results-container').hide();
    $('#no-results').hide();
}

/**
 * Hide search loading state
 */
function hideSearchLoading() {
    $('#search-loading').hide();
    $('#search-results-container').show();
}

/**
 * Show no results state
 */
function showNoResults() {
    $('#no-results').show();
    $('#search-results-container').hide();
    updateResultsCount(0, 0);
}

/**
 * Hide no results state
 */
function hideNoResults() {
    $('#no-results').hide();
}

/**
 * Show search error
 */
function showSearchError(message) {
    const errorHtml = `
        <div class="text-center py-4">
            <i class="fas fa-exclamation-triangle text-danger fa-3x mb-3"></i>
            <h6>Search Error</h6>
            <p class="text-muted">${message}</p>
            <button type="button" class="btn btn-primary" onclick="searchCustomers()">
                <i class="fas fa-redo"></i> Try Again
            </button>
        </div>
    `;
    
    $('#search-results-container').html(errorHtml);
    updateResultsCount(0, 0);
}

/**
 * Show manual mapping messages
 */
function showManualMappingMessage(message, type) {
    const alertClass = type === 'success' ? 'alert-success' : type === 'error' ? 'alert-danger' : 'alert-info';
    
    const alert = $(`
        <div class="alert ${alertClass} alert-dismissible fade show position-fixed" 
             style="top: 20px; right: 20px; z-index: 9999; min-width: 350px;">
            ${message}
            <button type="button" class="close" data-dismiss="alert">
                <span>&times;</span>
            </button>
        </div>
    `);
    
    $('body').append(alert);
    
    setTimeout(function() {
        alert.alert('close');
    }, 5000);
}

// Initialize when document is ready
$(document).ready(function() {
    if ($('#manual-mapping-section').length > 0) {
        initManualMapping();
    }
});