/**
 * Staff Account Management - Main Application JavaScript
 * 
 * Handles all client-side interactions for staff account management dashboard
 * 
 * @package CIS\Modules\StaffAccounts
 * @version 2.0.0
 */

// Global variables
let staffData = [];
let failedPaymentsData = [];
let snapshotsData = [];

// CSRF Token is injected by PHP in index.php as a global const

/**
 * Global error display function
 * Shows user-friendly error messages on screen
 */
function showError(message, details = null) {
    console.error('ERROR:', message, details);
    
    // Create or get error container
    let errorContainer = document.getElementById('global-error-container');
    if (!errorContainer) {
        errorContainer = document.createElement('div');
        errorContainer.id = 'global-error-container';
        errorContainer.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            max-width: 400px;
            z-index: 9999;
        `;
        document.body.appendChild(errorContainer);
    }
    
    // Create error alert
    const alertDiv = document.createElement('div');
    alertDiv.className = 'alert alert-danger alert-dismissible fade show';
    alertDiv.innerHTML = `
        <strong>Error:</strong> ${message}
        ${details ? `<br><small>${details}</small>` : ''}
        <button type="button" class="close" data-dismiss="alert">&times;</button>
    `;
    
    errorContainer.appendChild(alertDiv);
    
    // Auto-dismiss after 10 seconds
    setTimeout(() => {
        alertDiv.classList.remove('show');
        setTimeout(() => alertDiv.remove(), 150);
    }, 10000);
}

/**
 * API Call Helper Function
 * Makes AJAX requests to the module API endpoints
 * 
 * @param {string} action - API action name
 * @param {object} data - Additional data to send
 * @param {string|function} method - HTTP method (GET/POST) or callback function (legacy)
 * @returns {Promise} - Resolves with API response data
 */
async function apiCall(action, data = {}, method = 'POST') {
    // BACKWARD COMPATIBILITY: If method is a function, it's old callback-style
    // Convert to modern Promise-based call
    if (typeof method === 'function') {
        const callback = method;
        method = 'POST'; // Default to POST for legacy calls
        
        try {
            const result = await apiCall(action, data, method);
            callback({success: true, data: result});
        } catch (error) {
            callback({success: false, error: error.message});
        }
        return;
    }
    
    const url = new URL(window.location.href);
    url.search = ''; // Clear existing params
    url.searchParams.set('action', action);
    
    // Ensure data is an object (handle null/undefined)
    if (!data || typeof data !== 'object') {
        data = {};
    }
    
    // Ensure method is a valid string
    if (typeof method !== 'string') {
        method = 'POST';
    }
    
    const options = {
        method: method.toUpperCase(), // Ensure uppercase
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        }
    };
    
    // Add CSRF token ONLY for POST requests (not GET)
    if (method.toUpperCase() === 'POST' && typeof CSRF_TOKEN !== 'undefined') {
        data.csrf_token = CSRF_TOKEN;
    }
    
    if (method.toUpperCase() === 'POST') {
        options.body = JSON.stringify(data);
    } else if (Object.keys(data).length > 0) {
        // For GET requests, add params to URL (but NOT csrf_token)
        Object.keys(data).forEach(key => {
            if (key !== 'csrf_token') {  // Skip csrf_token for GET
                url.searchParams.set(key, data[key]);
            }
        });
    }
    
    try {
        const response = await fetch(url.toString(), options);
        
        // Get response text first to see what we actually received
        const responseText = await response.text();
        
        if (!response.ok) {
            console.error('HTTP Error:', response.status, responseText);
            showError(`HTTP ${response.status}: ${responseText.substring(0, 200)}`);
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        
        // Try to parse as JSON
        let result;
        try {
            result = JSON.parse(responseText);
        } catch (jsonError) {
            console.error('Invalid JSON response:', responseText);
            showError('Server returned invalid JSON: ' + responseText.substring(0, 200));
            throw new Error('Invalid JSON response from server');
        }
        
        if (!result.success) {
            throw new Error(result.error || 'Unknown API error');
        }
        
        return result.data;
        
    } catch (error) {
        console.error('API Call Failed:', action, error);
        showError(`Failed to ${action}`, error.message);
        throw error;
    }
}

/**
 * Initialize the application
 */
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM Content Loaded - Starting initialization...');
    
    // Initialize tabs first (fast)
    initializeTabs();
    
    // ONLY load data for the active tab (Failed Payments by default)
    loadFailedPayments();
    
    // Add a global function to window for easy testing
    window.showFailedPayments = showFailedPaymentsTab;
    
    console.log('✅ Page loaded - Failed Payments tab ready');
    console.log('Other tabs will load when you click them');
});

/**
 * Tab switching functionality with lazy loading
 */
function initializeTabs() {
    const tabs = document.querySelectorAll('.tab');
    const contents = document.querySelectorAll('.tab-content');
    
    console.log('Initializing tabs:', tabs.length, 'tabs found');
    console.log('Tab contents:', contents.length, 'contents found');
    
    tabs.forEach((tab, index) => {
        const targetTab = tab.getAttribute('data-tab');
        console.log('Tab', index, ':', targetTab);
        
        tab.addEventListener('click', function() {
            const targetTab = this.getAttribute('data-tab');
            console.log('Clicked tab:', targetTab);
            
            // Update tab states
            tabs.forEach(t => t.classList.remove('active'));
            this.classList.add('active');
            
            // Update content states
            contents.forEach(c => c.classList.remove('active'));
            const targetContent = document.querySelector('.tab-content#' + targetTab);
            if (targetContent) {
                targetContent.classList.add('active');
                console.log('Activated content:', targetTab);
            } else {
                console.error('Target content not found:', targetTab);
            }
            
            // LAZY LOAD: Load data when tab is clicked for the first time
            switch(targetTab) {
                case 'staff-overview':
                    if (!window.staffLoaded) {
                        console.log('Loading staff data...');
                        loadStaffAccounts();
                        window.staffLoaded = true;
                    }
                    break;
                case 'manual-payments':
                    if (!window.manualPaymentsLoaded) {
                        console.log('Loading manual payments...');
                        loadManualPayments();
                        window.manualPaymentsLoaded = true;
                    }
                    break;
                case 'snapshot-analysis':
                    if (!window.snapshotsLoaded) {
                        console.log('Loading snapshots...');
                        loadSnapshots();
                        window.snapshotsLoaded = true;
                    }
                    break;
                case 'api-debug':
                    if (!window.apiTestLoaded) {
                        console.log('Testing APIs...');
                        testAPIConnections();
                        window.apiTestLoaded = true;
                    }
                    break;
            }
        });
    });
}

/**
 * Quick function to show Failed Payments tab (for testing)
 */
function showFailedPaymentsTab() {
    console.log('Manually showing Failed Payments tab...');
    
    document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
    
    const failedTab = document.querySelector('.tab[data-tab="failed-payments"]');
    const failedContent = document.querySelector('.tab-content#failed-payments');
    
    if (failedTab) {
        failedTab.classList.add('active');
        console.log('Failed payments tab activated');
    }
    
    if (failedContent) {
        failedContent.classList.add('active');
        console.log('Failed payments content activated');
    }
}

/**
 * Load staff accounts
 */
async function loadStaffAccounts() {
    try {
        const response = await fetch('?action=get_staff');
        const data = await response.json();
        
        if (data.success && data.data && data.data.staff) {
            staffData = data.data.staff;
            renderStaffTable(staffData);
            updateStats();
            populateUserSelects();
        }
    } catch (error) {
        console.error('Error loading staff accounts:', error);
    }
}

/**
 * Load failed payments
 */
async function loadFailedPayments() {
    try {
        console.log('Loading failed payments...');
        const response = await fetch('?action=get_failed_summary');
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const data = await response.json();
        console.log('Failed payments response:', data);
        
        if (data.success && data.data && data.data.failed_payments) {
            failedPaymentsData = data.data.failed_payments;
            console.log('Failed payments count:', failedPaymentsData.length);
            
            renderFailedPaymentsTable(failedPaymentsData);
            updateStats();
            
            console.log('Failed payments table rendered successfully');
        } else {
            console.error('Failed payments request unsuccessful:', data);
            const tbody = document.getElementById('failed-payments-table-body');
            if (tbody) {
                tbody.innerHTML = '<tr><td colspan="5" class="error">Failed to load failed payments data</td></tr>';
            }
        }
    } catch (error) {
        console.error('Error loading failed payments:', error);
        const tbody = document.getElementById('failed-payments-table-body');
        if (tbody) {
            tbody.innerHTML = '<tr><td colspan="5" class="error">Error loading failed payments: ' + error.message + '</td></tr>';
        }
    }
}

/**
 * Load manual payments
 */
async function loadManualPayments() {
    try {
        const daysSelect = document.getElementById('manual-payment-days');
        const days = daysSelect ? daysSelect.value : '21';
        
        const tbody = document.getElementById('manual-payments-table-body');
        tbody.innerHTML = '<tr><td colspan="7" class="loading">Loading manual payments...</td></tr>';
        
        const response = await fetch(`?action=get_manual_payments&days=${days}`);
        const data = await response.json();
        
        if (data.success && data.data) {
            renderManualPaymentsTable(data.data);
        } else {
            tbody.innerHTML = `<tr><td colspan="7" class="error">Error: ${data.error || 'Unknown error'}</td></tr>`;
        }
    } catch (error) {
        console.error('Error loading manual payments:', error);
        const tbody = document.getElementById('manual-payments-table-body');
        tbody.innerHTML = '<tr><td colspan="7" class="error">Error loading manual payments</td></tr>';
    }
}

/**
 * Load snapshots
 */
async function loadSnapshots() {
    try {
        const response = await fetch('?action=get_snapshots');
        const data = await response.json();
        
        if (data.success && data.data && data.data.snapshots) {
            snapshotsData = data.data.snapshots;
            renderSnapshotsTable(data.data.snapshots);
        }
        updateStats();
    } catch (error) {
        console.error('Error loading snapshots:', error);
        updateStats();
    }
}

/**
 * Update statistics
 */
function updateStats() {
    const totalStaffEl = document.getElementById('total-staff');
    const failedCountEl = document.getElementById('failed-payments-count');
    const recentSnapshotsEl = document.getElementById('recent-snapshots');
    const totalOutstandingEl = document.getElementById('total-outstanding');
    
    // Only update if elements exist
    if (totalStaffEl) {
        totalStaffEl.textContent = staffData.length;
    }
    
    if (failedCountEl) {
        failedCountEl.textContent = failedPaymentsData.length;
    }
    
    if (recentSnapshotsEl) {
        recentSnapshotsEl.textContent = snapshotsData.length;
    }
    
    if (totalOutstandingEl) {
        const totalOutstanding = failedPaymentsData.reduce((sum, payment) => sum + payment.total_failed_amount, 0);
        totalOutstandingEl.textContent = '$' + totalOutstanding.toFixed(2);
    }
}

/**
 * Populate user select dropdowns
 */
function populateUserSelects() {
    const historySelect = document.getElementById('history-user-select');
    const searchSelect = document.getElementById('search-user-select');
    
    const options = staffData.map(member => 
        `<option value="${member.vend_customer_id}" data-user-id="${member.user_id}">${escapeHtml(member.name)}</option>`
    ).join('');
    
    historySelect.innerHTML = '<option value="">Select staff member...</option>' + options;
    searchSelect.innerHTML = '<option value="">All users</option>' + options;
}

/**
 * Utility: Escape HTML
 */
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

/**
 * Utility: Format Date
 */
function formatDate(dateString) {
    try {
        const date = new Date(dateString);
        return date.toLocaleDateString() + ' ' + date.toLocaleTimeString();
    } catch (e) {
        return dateString;
    }
}

/**
 * Utility: Format DateTime
 */
function formatDateTime(dateString) {
    try {
        const date = new Date(dateString);
        return date.toLocaleDateString() + ' ' + date.toLocaleTimeString();
    } catch (e) {
        return dateString;
    }
}

/**
 * Utility: Safe date formatting
 */
function safeFormatDate(dateString) {
    if (!dateString) return 'N/A';
    try {
        const date = new Date(dateString);
        if (isNaN(date.getTime())) return dateString;
        return formatDate(dateString);
    } catch (error) {
        console.warn('Date formatting error:', error, 'for date:', dateString);
        return dateString;
    }
}

/**
 * Utility: Format pay period from start and end dates
 */
function formatPayPeriod(startDate, endDate) {
    if (!startDate || !endDate) return 'N/A';
    try {
        const start = new Date(startDate);
        const end = new Date(endDate);
        if (isNaN(start.getTime()) || isNaN(end.getTime())) return 'N/A';
        
        // Format as "Oct 14 - 20, 2025"
        const startFormatted = start.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
        const endFormatted = end.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
        
        // If same month, show "Oct 14 - 20, 2025"
        if (start.getMonth() === end.getMonth() && start.getFullYear() === end.getFullYear()) {
            const startDay = start.getDate();
            const endDay = end.getDate();
            const monthYear = end.toLocaleDateString('en-US', { month: 'short', year: 'numeric' });
            return `${monthYear.split(' ')[0]} ${startDay} - ${endDay}, ${monthYear.split(' ')[1]}`;
        } else {
            // Different months: "Sep 30 - Oct 6, 2025"
            return `${startFormatted} - ${endFormatted}`;
        }
    } catch (error) {
        console.warn('Pay period formatting error:', error);
        return 'N/A';
    }
}

/**
 * Utility: Get event type badge class
 */
function getEventTypeBadge(type) {
    switch (type) {
        case 'allocation':
        case 'success':
            return 'badge-success';
        case 'error':
        case 'failed':
            return 'badge-danger';
        case 'planned_deduction':
            return 'badge-warning';
        default:
            return '';
    }
}

// ============================================================================
// NEW FUNCTIONS FOR RESTRUCTURED UI
// ============================================================================

/**
 * Load Dashboard Stats (all 4 stats)
 */
async function loadDashboardStats() {
    console.log('Loading dashboard stats...');
    
    try {
        // Stat 1: Pending Deductions
        const pendingSummary = await apiCall('get_pending_deductions_summary', {}, 'GET');
        if (pendingSummary && pendingSummary.summary) {
            const summary = pendingSummary.summary;
            const countEl = document.getElementById('pending-deductions-count');
            const amountEl = document.getElementById('pending-deductions-amount');
            if (countEl) countEl.textContent = summary.total_count || 0;
            if (amountEl) amountEl.textContent = '$' + parseFloat(summary.total_amount || 0).toFixed(2);
        }
    } catch (error) {
        console.error('Failed to load pending deductions:', error);
    }
    
    try {
        // Stat 2: Allocated This Month
        const allocStats = await apiCall('get_allocation_statistics', {}, 'GET');
        if (allocStats && allocStats.statistics) {
            const stats = allocStats.statistics;
            const allocated = stats.find(s => s.status === 'allocated');
            const countEl = document.getElementById('allocated-this-month-count');
            const amountEl = document.getElementById('allocated-this-month-amount');
            if (countEl) countEl.textContent = allocated ? allocated.count : 0;
            if (amountEl) amountEl.textContent = allocated ? '$' + parseFloat(allocated.total).toFixed(2) : '$0.00';
        }
    } catch (error) {
        console.error('Failed to load allocation statistics:', error);
    }
    
    try {
        // Stat 3: Failed Allocations
        const missed = await apiCall('get_missed_payments', {}, 'GET');
        if (missed && missed.missed) {
            const missedPayments = missed.missed;
            const totalFailed = missedPayments.reduce((sum, m) => sum + parseInt(m.failed_count || 0), 0);
            const totalAmount = missedPayments.reduce((sum, m) => sum + parseFloat(m.total_failed || 0), 0);
            const countEl = document.getElementById('failed-allocations-count');
            const amountEl = document.getElementById('failed-allocations-amount');
            if (countEl) countEl.textContent = totalFailed;
            if (amountEl) amountEl.textContent = '$' + totalAmount.toFixed(2);
        }
    } catch (error) {
        console.error('Failed to load missed payments:', error);
    }
    
    try {
        // Stat 4: Success Rate
        const rateData = await apiCall('get_allocation_success_rate', {days: 30}, 'GET');
        if (rateData) {
            const percentEl = document.getElementById('success-rate-percentage');
            const ratioEl = document.getElementById('success-rate-ratio');
            if (percentEl) percentEl.textContent = (rateData.success_rate || 0) + '%';
            if (ratioEl) ratioEl.textContent = (rateData.successful || 0) + ' / ' + (rateData.total || 0);
        }
    } catch (error) {
        console.error('Failed to load success rate:', error);
    }
}

/**
 * Set Allocation Mode (Database vs API)
 */
function setAllocationMode(mode) {
    const useLiveApi = (mode === 'api');
    
    apiCall('set_allocation_mode', {use_live_api: useLiveApi}, (response) => {
        if (response.success) {
            // Update UI
            if (mode === 'db') {
                document.getElementById('mode-db').classList.remove('btn-outline-primary');
                document.getElementById('mode-db').classList.add('btn-primary');
                document.getElementById('mode-api').classList.remove('btn-primary');
                document.getElementById('mode-api').classList.add('btn-outline-primary');
                document.getElementById('mode-status').innerHTML = '✅ Using Database mode (100x faster)';
                document.getElementById('mode-status').style.color = '#28a745';
            } else {
                document.getElementById('mode-api').classList.remove('btn-outline-primary');
                document.getElementById('mode-api').classList.add('btn-primary');
                document.getElementById('mode-db').classList.remove('btn-primary');
                document.getElementById('mode-db').classList.add('btn-outline-primary');
                document.getElementById('mode-status').innerHTML = '🌐 Using API mode (most accurate)';
                document.getElementById('mode-status').style.color = '#007bff';
            }
            showToast('Mode updated to: ' + mode.toUpperCase(), 'success');
        }
    });
}

/**
 * Load Current Allocation Mode
 */
function loadCurrentMode() {
    apiCall('get_allocation_mode', null, (response) => {
        if (response.success && response.data) {
            const useLiveApi = response.data.use_live_api;
            const mode = useLiveApi ? 'api' : 'db';
            
            // Update UI without API call
            if (mode === 'db') {
                document.getElementById('mode-db').classList.remove('btn-outline-primary');
                document.getElementById('mode-db').classList.add('btn-primary');
                document.getElementById('mode-api').classList.remove('btn-primary');
                document.getElementById('mode-api').classList.add('btn-outline-primary');
                document.getElementById('mode-status').innerHTML = '✅ Using Database mode (100x faster)';
                document.getElementById('mode-status').style.color = '#28a745';
            } else {
                document.getElementById('mode-api').classList.remove('btn-outline-primary');
                document.getElementById('mode-api').classList.add('btn-primary');
                document.getElementById('mode-db').classList.remove('btn-primary');
                document.getElementById('mode-db').classList.add('btn-outline-primary');
                document.getElementById('mode-status').innerHTML = '🌐 Using API mode (most accurate)';
                document.getElementById('mode-status').style.color = '#007bff';
            }
        }
    });
}

/**
 * Sync Payrolls from Xero
 */
async function syncFromXero() {
    const btn = event?.target;
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Syncing...';
    }
    
    try {
        const result = await apiCall('sync_payrolls', {}, 'POST');
        
        if (result.synced !== undefined || result.cached !== undefined) {
            const synced = result.synced || 0;
            const cached = result.cached || 0;
            const errors = result.errors || [];
            const total = synced + cached;
            
            // Build summary message
            let message = `✅ Sync complete!\n`;
            message += `Synced: ${synced} recent payrolls\n`;
            message += `Cached: ${cached} old payrolls\n`;
            
            if (errors.length > 0) {
                message += `\n⚠️ ${errors.length} errors occurred`;
                
                // Show first 3 errors as examples
                message += `:\n\nFirst 3 errors:\n`;
                errors.slice(0, 3).forEach((err, i) => {
                    // Truncate long error messages
                    const shortErr = err.length > 80 ? err.substring(0, 80) + '...' : err;
                    message += `${i+1}. ${shortErr}\n`;
                });
                
                if (errors.length > 3) {
                    message += `\n... and ${errors.length - 3} more errors`;
                }
            }
            
            showError(message, total > 0 ? 'success' : 'warning');
            
            if (document.getElementById('last-sync-time')) {
                document.getElementById('last-sync-time').textContent = 'Last Sync: Just now';
            }
            
            // Reload data
            setTimeout(() => {
                loadPendingDeductions();
                loadDashboardStats();
            }, 500);
        } else {
            showError('⚠️ Sync completed but no data was returned');
        }
        
    } catch (error) {
        console.error('Sync error:', error);
        showError(`❌ Sync failed: ${error.message}`);
    } finally {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fa fa-sync"></i> Sync from Xero';
        }
    }
}

/**
 * Load Pending Deductions
 */
function loadPendingDeductions() {
    console.log('Loading pending deductions...');
    apiCall('get_pending_deductions', null, (response) => {
        const tbody = document.getElementById('deductions-table-body');
        if (!response.success || !response.data.deductions || response.data.deductions.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center" style="padding: 40px; color: #999;">No pending deductions</td></tr>';
            return;
        }
        
        tbody.innerHTML = response.data.deductions.map(d => `
            <tr>
                <td>${d.employee_name || 'Unknown'}</td>
                <td>${d.vend_customer_id || 'Not Mapped'}</td>
                <td>${formatPayPeriod(d.pay_period_start, d.pay_period_end)}</td>
                <td>$${parseFloat(d.amount || 0).toFixed(2)}</td>
                <td><span class="badge badge-warning">Pending</span></td>
                <td>
                    <button class="btn btn-sm btn-primary" onclick="allocateSingle(${d.id})">Allocate</button>
                </td>
            </tr>
        `).join('');
    });
}

/**
 * Allocate Single Deduction
 */
function allocateSingle(id) {
    if (!confirm('Allocate this deduction to Vend?')) return;
    
    showToast('Allocating deduction...', 'info');
    apiCall('allocate_deduction', {id: id}, (response) => {
        if (response.success) {
            showToast('Allocation successful', 'success');
            loadPendingDeductions();
            loadDashboardStats();
        } else {
            showToast('Allocation failed: ' + (response.error.message || 'Unknown error'), 'error');
        }
    });
}

/**
 * Allocate All Pending
 */
function allocateAllPending() {
    if (!confirm('Allocate all pending deductions? This may take a few moments.')) return;
    
    showToast('Allocating all pending deductions...', 'info');
    apiCall('allocate_all_pending', null, (response) => {
        if (response.success) {
            showToast('Allocated ' + (response.data.allocated_count || 0) + ' deductions', 'success');
            loadPendingDeductions();
            loadDashboardStats();
        } else {
            showToast('Allocation failed: ' + (response.error.message || 'Unknown error'), 'error');
        }
    });
}

/**
 * Load Missed Payments
 */
function loadMissedPayments() {
    console.log('Loading missed payments...');
    apiCall('get_missed_payments', null, (response) => {
        const tbody = document.getElementById('failed-table-body');
        if (!response.success || !response.data.missed || response.data.missed.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center" style="padding: 40px; color: #999;">No failed allocations</td></tr>';
            return;
        }
        
        tbody.innerHTML = response.data.missed.map(m => `
            <tr>
                <td>${m.employee_name || 'Unknown'}</td>
                <td>${m.vend_customer_id || 'Not Mapped'}</td>
                <td>$${parseFloat(m.total_failed || 0).toFixed(2)}</td>
                <td>${m.last_failed_at || 'N/A'}</td>
                <td><span class="error-reason" style="color: #dc3545; font-size: 12px;">${m.last_error || 'Unknown error'}</span></td>
                <td>
                    <button class="btn btn-sm btn-primary" onclick="retrySingle(${m.id})">Retry</button>
                </td>
            </tr>
        `).join('');
    });
}

/**
 * Retry Single Failed Deduction
 */
function retrySingle(id) {
    showToast('Retrying allocation...', 'info');
    apiCall('retry_failed_deduction', {id: id}, (response) => {
        if (response.success) {
            showToast('Retry successful', 'success');
            loadMissedPayments();
            loadDashboardStats();
        } else {
            showToast('Retry failed: ' + (response.error.message || 'Unknown error'), 'error');
        }
    });
}

/**
 * Retry All Failed
 */
function retryAllFailed() {
    if (!confirm('Retry all failed allocations? This may take a few moments.')) return;
    
    showToast('Retrying all failed allocations...', 'info');
    apiCall('retry_all_failed', null, (response) => {
        if (response.success) {
            showToast('Retried ' + (response.data.retry_count || 0) + ' allocations', 'success');
            loadMissedPayments();
            loadDashboardStats();
        } else {
            showToast('Retry failed: ' + (response.error.message || 'Unknown error'), 'error');
        }
    });
}

/**
 * Reconcile All
 */
function reconcileAll() {
    showToast('Reconciling Xero vs Vend balances...', 'info');
    apiCall('reconcile_all', null, (response) => {
        const tbody = document.getElementById('reconciliation-table-body');
        if (!response.success || !response.data.results) {
            tbody.innerHTML = '<tr><td colspan="5" class="text-center" style="padding: 40px; color: #dc3545;">Reconciliation failed</td></tr>';
            showToast('Reconciliation failed', 'error');
            return;
        }
        
        const results = response.data.results;
        tbody.innerHTML = results.map(r => {
            const diff = parseFloat(r.difference || 0);
            let statusBadge = '';
            if (diff === 0) {
                statusBadge = '<span class="badge badge-success">✅ OK</span>';
            } else if (diff < 0) {
                statusBadge = '<span class="badge badge-warning">⚠️ Under</span>';
            } else {
                statusBadge = '<span class="badge badge-danger">⚠️ Over</span>';
            }
            
            return `
                <tr>
                    <td>${r.employee_name || 'Unknown'}</td>
                    <td>$${parseFloat(r.xero_total || 0).toFixed(2)}</td>
                    <td>$${parseFloat(r.vend_balance || 0).toFixed(2)}</td>
                    <td class="${diff === 0 ? '' : 'text-danger'}">$${diff.toFixed(2)}</td>
                    <td>${statusBadge}</td>
                </tr>
            `;
        }).join('');
        
        showToast('Reconciliation complete', 'success');
    });
}

/**
 * Export Discrepancies
 */
function exportDiscrepancies() {
    window.location.href = '?action=export_discrepancies&format=csv';
}

/**
 * Load Allocation Log
 */
function loadAllocationLog() {
    console.log('Loading allocation log...');
    apiCall('get_recent_allocations', {limit: 100}, (response) => {
        const tbody = document.getElementById('allocation-log-body');
        if (!response.success || !response.data.allocations || response.data.allocations.length === 0) {
            tbody.innerHTML = '<tr><td colspan="5" class="text-center" style="padding: 40px; color: #999;">No recent allocations</td></tr>';
            return;
        }
        
        tbody.innerHTML = response.data.allocations.map(a => {
            const statusBadge = a.status === 'allocated' 
                ? '<span class="badge badge-success">✅ Success</span>' 
                : '<span class="badge badge-danger">❌ Failed</span>';
            
            return `
                <tr>
                    <td>${a.allocated_at || 'N/A'}</td>
                    <td>${a.employee_name || 'Unknown'}</td>
                    <td>$${parseFloat(a.amount || 0).toFixed(2)}</td>
                    <td>${statusBadge}</td>
                    <td>${a.sales_count || 0} sales</td>
                </tr>
            `;
        }).join('');
    });
}

/**
 * Export Allocation Log
 */
function exportAllocationLog() {
    window.location.href = '?action=export_allocations&format=csv';
}

/**
 * Load Statistics
 */
function loadStatistics() {
    console.log('Loading statistics...');
    
    // Success rate
    apiCall('get_allocation_success_rate', {days: 30}, (response) => {
        if (response.success && response.data) {
            const rate = response.data;
            const percentage = rate.success_rate || 0;
            document.getElementById('success-rate-bar').style.width = percentage + '%';
            document.getElementById('success-rate-bar').textContent = percentage + '%';
            document.getElementById('success-rate-details').textContent = 
                (rate.successful || 0) + ' successful / ' + (rate.failed || 0) + ' failed out of ' + (rate.total || 0) + ' total';
        }
    });
    
    // Allocation by status
    apiCall('get_allocation_statistics', null, (response) => {
        if (response.success && response.data.statistics) {
            const stats = response.data.statistics;
            const statusDiv = document.getElementById('allocation-by-status');
            statusDiv.innerHTML = stats.map(s => `
                <div style="margin: 10px 0; padding: 10px; background: #f8f9fa; border-radius: 6px;">
                    <strong>${s.status}:</strong> ${s.count} ($${parseFloat(s.total || 0).toFixed(2)})
                </div>
            `).join('');
        }
    });
}

/**
 * Export Statistics
 */
function exportStatistics() {
    window.location.href = '?action=export_statistics&format=pdf';
}

/**
 * Show Add Mapping Modal
 */
function showAddMappingModal() {
    alert('Employee Mapping feature coming soon!');
}

/**
 * Auto Match Employees
 */
function autoMatch() {
    alert('Auto-match feature coming soon!');
}

/**
 * Update Tab Switching to Load New Tabs
 */
(function() {
    // Override the initializeTabs function to add new tab handlers
    const originalInit = window.initializeTabs;
    window.initializeTabs = function() {
        if (originalInit) originalInit();
        
        // Add new tab handlers
        const tabs = document.querySelectorAll('.tab');
        tabs.forEach(tab => {
            tab.addEventListener('click', function() {
                const targetTab = this.getAttribute('data-tab');
                
                switch(targetTab) {
                    case 'payroll-deductions':
                        if (!window.payrollLoaded) {
                            loadPendingDeductions();
                            window.payrollLoaded = true;
                        }
                        break;
                    case 'missed-payments':
                        if (!window.missedLoaded) {
                            loadMissedPayments();
                            window.missedLoaded = true;
                        }
                        break;
                    case 'allocation-log':
                        if (!window.logLoaded) {
                            loadAllocationLog();
                            window.logLoaded = true;
                        }
                        break;
                    case 'statistics':
                        if (!window.statsLoaded) {
                            loadStatistics();
                            window.statsLoaded = true;
                        }
                        break;
                }
            });
        });
    };
})();

// Initialize stats and mode on page load
document.addEventListener('DOMContentLoaded', function() {
    // Don't auto-sync - let user trigger it manually for better UX
    // checkAndAutoSync();
    
    setTimeout(() => {
        loadDashboardStats();
        loadCurrentMode();
        loadPendingDeductions(); // Load first tab
    }, 100);
});

/**
 * Check if auto-sync is needed (no payroll data) and trigger if necessary
 */
async function checkAndAutoSync() {
    try {
        // Check localStorage to see if we've already synced today
        const lastSync = localStorage.getItem('xero_last_sync');
        const today = new Date().toDateString();
        
        if (lastSync === today) {
            console.log('Already synced today, skipping auto-sync');
            return;
        }
        
        // Check if we have any payroll data
        const summary = await apiCall('get_pending_deductions_summary', {}, 'GET');
        
        // If summary is empty array, trigger sync
        if (!summary.summary || summary.summary.length === 0) {
            console.log('No payroll data found - triggering auto-sync from Xero...');
            
            showError('First time setup: Syncing payroll data from Xero...', 'info');
            
            const result = await apiCall('sync_payrolls', {}, 'POST');
            
            if (result.synced !== undefined) {
                localStorage.setItem('xero_last_sync', today);
                showError(`✅ Sync complete! Imported ${result.synced + result.cached} payrolls`, 'success');
                
                // Reload data after sync
                setTimeout(() => {
                    loadDashboardStats();
                    loadPendingDeductions();
                }, 1000);
            }
        }
    } catch (error) {
        console.error('Auto-sync check failed:', error);
        // ALWAYS show errors on screen!
        showError('Auto-sync failed: ' + error.message + ' - Use "Sync from Xero" button manually');
    }
}

/**
 * Update mode UI buttons
 */
function updateModeUI(useLiveApi) {
    const dbBtn = document.getElementById("mode-db");
    const apiBtn = document.getElementById("mode-api");
    const status = document.getElementById("mode-status");
    
    if (useLiveApi) {
        dbBtn.className = "btn btn-outline-primary";
        apiBtn.className = "btn btn-primary";
        if (status) status.innerHTML = "🌐 Using API mode (slow, accurate)";
    } else {
        dbBtn.className = "btn btn-primary";
        apiBtn.className = "btn btn-outline-primary";
        if (status) status.innerHTML = "📊 Using Database mode (100x faster)";
    }
}

/**
 * Stub functions for other tab loaders (to be implemented)
 */
function loadMissedPayments() {
    console.log("Loading missed payments...");
    // TODO: Implement
}

function loadAllocationLog() {
    console.log("Loading allocation log...");
    // TODO: Implement
}

function loadStatistics() {
    console.log("Loading statistics...");
    // TODO: Implement
}

function loadReconciliation() {
    console.log("Loading reconciliation...");
    // TODO: Implement
}

function loadApiDebug() {
    console.log("Loading API debug...");
    // TODO: Implement
}
