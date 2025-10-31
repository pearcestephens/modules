/**
 * Staff Account Management - Table Rendering & API Functions
 * 
 * Contains table rendering functions, API testing, and payment processing
 * 
 * @package CIS\Modules\StaffAccounts
 * @version 2.0.0
 */

/**
 * Render staff table
 */
function renderStaffTable(staff) {
    const tbody = document.getElementById('staff-table-body');
    
    if (!staff || staff.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" class="empty">No staff accounts found</td></tr>';
        return;
    }
    
    tbody.innerHTML = staff.map(member => `
        <tr>
            <td>${escapeHtml(member.name)}</td>
            <td>${escapeHtml(member.email)}</td>
            <td>${escapeHtml(member.customer_code || 'N/A')}</td>
            <td>$${parseFloat(member.balance || 0).toFixed(2)}</td>
            <td><span class="badge ${member.status === 'Active' ? 'badge-success' : 'badge-secondary'}">${member.status}</span></td>
            <td>
                <button class="btn btn-sm btn-primary" onclick="viewAccountDetails(${member.vend_customer_id})">View Details</button>
            </td>
        </tr>
    `).join('');
}

/**
 * Render failed payments table
 */
function renderFailedPaymentsTable(failedPayments) {
    const tbody = document.getElementById('failed-payments-table-body');
    
    if (!failedPayments || failedPayments.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" class="empty">No failed payments found</td></tr>';
        return;
    }
    
    tbody.innerHTML = failedPayments.map(payment => `
        <tr>
            <td>${escapeHtml(payment.user_name)}</td>
            <td>${payment.failed_count}</td>
            <td>$${parseFloat(payment.total_failed_amount).toFixed(2)}</td>
            <td>${safeFormatDate(payment.most_recent_failure)}</td>
            <td>
                <button class="btn btn-sm btn-warning" 
                        onclick="applyFailedToVend('${payment.vend_customer_id}', '${escapeHtml(payment.user_name)}', ${payment.total_failed_amount})">
                    Apply to Vend
                </button>
            </td>
        </tr>
    `).join('');
}

/**
 * Render manual payments table
 */
function renderManualPaymentsTable(data) {
    const tbody = document.getElementById('manual-payments-table-body');
    
    if (!data.payments || data.payments.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" class="empty">No manual payments found</td></tr>';
        return;
    }
    
    const summary = document.getElementById('manual-payments-summary');
    summary.innerHTML = `
        <div class="summary-item">
            <strong>Total Payments:</strong> ${data.summary.total_count}
        </div>
        <div class="summary-item">
            <strong>Total Amount:</strong> $${parseFloat(data.summary.total_amount).toFixed(2)}
        </div>
        <div class="summary-item">
            <strong>Unique Customers:</strong> ${data.summary.unique_customers}
        </div>
    `;
    
    tbody.innerHTML = data.payments.map(payment => `
        <tr>
            <td>${safeFormatDate(payment.sale_date)}</td>
            <td>${escapeHtml(payment.customer_name || 'N/A')}</td>
            <td>${escapeHtml(payment.customer_code || 'N/A')}</td>
            <td>$${parseFloat(payment.amount).toFixed(2)}</td>
            <td>${escapeHtml(payment.payment_type)}</td>
            <td>${escapeHtml(payment.register_name)}</td>
            <td>
                <button class="btn btn-sm btn-primary" onclick="viewSaleDetails('${payment.sale_id}')">View Sale</button>
            </td>
        </tr>
    `).join('');
}

/**
 * Render snapshots table
 */
function renderSnapshotsTable(snapshots) {
    const tbody = document.getElementById('snapshots-table-body');
    
    if (!snapshots || snapshots.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" class="empty">No snapshots found</td></tr>';
        return;
    }
    
    tbody.innerHTML = snapshots.map(snapshot => `
        <tr>
            <td>${safeFormatDate(snapshot.date)}</td>
            <td>${snapshot.total_employees}</td>
            <td>${snapshot.total_deductions}</td>
            <td>$${parseFloat(snapshot.total_amount).toFixed(2)}</td>
            <td>${snapshot.failed_payments || 0}</td>
            <td>
                <button class="btn btn-sm btn-primary" onclick="viewSnapshotDetails('${snapshot.filename}')">View Details</button>
            </td>
        </tr>
    `).join('');
}

/**
 * Test API connections
 */
async function testAPIConnections() {
    console.log('Testing API connections...');
    
    const vendStatus = document.getElementById('vend-api-status');
    const xeroStatus = document.getElementById('xero-api-status');
    
    vendStatus.innerHTML = '<span class="badge badge-secondary">Testing...</span>';
    xeroStatus.innerHTML = '<span class="badge badge-secondary">Testing...</span>';
    
    try {
        const response = await fetch('?action=test_api');
        const data = await response.json();
        
        if (data.success && data.data) {
            vendStatus.innerHTML = data.data.vend_status 
                ? '<span class="badge badge-success">Connected</span>' 
                : '<span class="badge badge-danger">Failed</span>';
            
            xeroStatus.innerHTML = data.data.xero_status 
                ? '<span class="badge badge-success">Connected</span>' 
                : '<span class="badge badge-danger">Failed</span>';
            
            if (data.data.vend_message) {
                vendStatus.innerHTML += `<br><small>${escapeHtml(data.data.vend_message)}</small>`;
            }
            if (data.data.xero_message) {
                xeroStatus.innerHTML += `<br><small>${escapeHtml(data.data.xero_message)}</small>`;
            }
        }
    } catch (error) {
        console.error('Error testing APIs:', error);
        vendStatus.innerHTML = '<span class="badge badge-danger">Error</span>';
        xeroStatus.innerHTML = '<span class="badge badge-danger">Error</span>';
    }
}

/**
 * Debug Vend token
 */
async function debugVendToken() {
    const output = document.getElementById('vend-token-output');
    output.innerHTML = '<div class="loading">Checking token...</div>';
    
    try {
        const response = await fetch('?action=debug_vend_token');
        const data = await response.json();
        
        if (data.success && data.data) {
            output.innerHTML = `
                <div class="debug-info">
                    <h4>Vend API Token Debug</h4>
                    <pre>${JSON.stringify(data.data, null, 2)}</pre>
                </div>
            `;
        } else {
            output.innerHTML = `<div class="error">Error: ${escapeHtml(data.error || 'Unknown error')}</div>`;
        }
    } catch (error) {
        console.error('Error debugging Vend token:', error);
        output.innerHTML = `<div class="error">Error: ${error.message}</div>`;
    }
}

/**
 * Debug Xero connection
 */
async function debugXeroConnection() {
    const output = document.getElementById('xero-connection-output');
    output.innerHTML = '<div class="loading">Checking connection...</div>';
    
    try {
        const response = await fetch('?action=debug_xero_connection');
        const data = await response.json();
        
        if (data.success && data.data) {
            output.innerHTML = `
                <div class="debug-info">
                    <h4>Xero Connection Debug</h4>
                    <pre>${JSON.stringify(data.data, null, 2)}</pre>
                </div>
            `;
        } else {
            output.innerHTML = `<div class="error">Error: ${escapeHtml(data.error || 'Unknown error')}</div>`;
        }
    } catch (error) {
        console.error('Error debugging Xero connection:', error);
        output.innerHTML = `<div class="error">Error: ${error.message}</div>`;
    }
}

/**
 * Test customer balance
 */
async function testCustomerBalance() {
    const customerId = document.getElementById('test-customer-id').value;
    const output = document.getElementById('balance-test-output');
    
    if (!customerId) {
        output.innerHTML = '<div class="error">Please enter a customer ID</div>';
        return;
    }
    
    output.innerHTML = '<div class="loading">Fetching balance...</div>';
    
    try {
        const response = await fetch(`?action=test_customer_balance&customer_id=${customerId}`);
        const data = await response.json();
        
        if (data.success && data.data) {
            output.innerHTML = `
                <div class="debug-info">
                    <h4>Customer Balance</h4>
                    <pre>${JSON.stringify(data.data, null, 2)}</pre>
                </div>
            `;
        } else {
            output.innerHTML = `<div class="error">Error: ${escapeHtml(data.error || 'Unknown error')}</div>`;
        }
    } catch (error) {
        console.error('Error testing customer balance:', error);
        output.innerHTML = `<div class="error">Error: ${error.message}</div>`;
    }
}

/**
 * Open payment modal
 */
function openPaymentModal(vendCustomerId, userName, currentBalance) {
    const modal = document.getElementById('payment-modal');
    const customerInfo = document.getElementById('modal-customer-info');
    
    customerInfo.innerHTML = `
        <p><strong>Customer:</strong> ${escapeHtml(userName)}</p>
        <p><strong>Current Balance:</strong> $${parseFloat(currentBalance).toFixed(2)}</p>
    `;
    
    document.getElementById('payment-customer-id').value = vendCustomerId;
    document.getElementById('payment-amount').value = '';
    document.getElementById('payment-note').value = '';
    
    modal.style.display = 'block';
}

/**
 * Close modal
 */
function closeModal() {
    document.getElementById('payment-modal').style.display = 'none';
}

/**
 * Process payment
 */
async function processPayment() {
    const customerId = document.getElementById('payment-customer-id').value;
    const amount = document.getElementById('payment-amount').value;
    const note = document.getElementById('payment-note').value;
    
    if (!amount || parseFloat(amount) <= 0) {
        alert('Please enter a valid payment amount');
        return;
    }
    
    const submitBtn = document.querySelector('#payment-modal .btn-primary');
    submitBtn.disabled = true;
    submitBtn.textContent = 'Processing...';
    
    try {
        const formData = new FormData();
        formData.append('action', 'process_payment');
        formData.append('customer_id', customerId);
        formData.append('amount', amount);
        formData.append('note', note);
        formData.append('csrf_token', CSRF_TOKEN);
        
        const response = await fetch('', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            alert('Payment processed successfully!');
            closeModal();
            loadStaffAccounts();
            loadFailedPayments();
        } else {
            alert('Error: ' + (data.error || 'Unknown error'));
        }
    } catch (error) {
        console.error('Error processing payment:', error);
        alert('Error processing payment: ' + error.message);
    } finally {
        submitBtn.disabled = false;
        submitBtn.textContent = 'Process Payment';
    }
}

/**
 * Apply failed payment to Vend
 */
async function applyFailedToVend(vendCustomerId, userName, amount) {
    if (!confirm(`Apply $${amount.toFixed(2)} failed payments for ${userName} to Vend?`)) {
        return;
    }
    
    try {
        const formData = new FormData();
        formData.append('action', 'apply_failed_to_vend');
        formData.append('vend_customer_id', vendCustomerId);
        formData.append('csrf_token', CSRF_TOKEN);
        
        const response = await fetch('', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            alert('Success: ' + (data.message || 'Applied successfully'));
            loadFailedPayments();
            loadStaffAccounts();
        } else {
            alert('Error: ' + (data.error || 'Unknown error'));
        }
    } catch (error) {
        console.error('Error applying failed payment:', error);
        alert('Error: ' + error.message);
    }
}

/**
 * Apply all failed payments
 */
async function applyAllFailedPayments() {
    if (!confirm('Apply ALL failed payments to Vend? This may take several minutes.')) {
        return;
    }
    
    const btn = event.target;
    btn.disabled = true;
    btn.textContent = 'Processing...';
    
    try {
        const formData = new FormData();
        formData.append('action', 'apply_all_failed');
        formData.append('csrf_token', CSRF_TOKEN);
        
        const response = await fetch('', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success && data.data) {
            alert(`Success: Applied ${data.data.successful || 0} of ${data.data.total || 0} payments`);
            if (data.data.failed > 0) {
                alert(`Note: ${data.data.failed} payments failed to apply`);
            }
            loadFailedPayments();
            loadStaffAccounts();
        } else {
            alert('Error: ' + (data.error || 'Unknown error'));
        }
    } catch (error) {
        console.error('Error applying all failed payments:', error);
        alert('Error: ' + error.message);
    } finally {
        btn.disabled = false;
        btn.textContent = 'Apply All Failed Payments';
    }
}

/**
 * Load payment history for specific user
 */
async function loadPaymentHistory() {
    const select = document.getElementById('history-user-select');
    const userId = select.options[select.selectedIndex].getAttribute('data-user-id');
    const vendCustomerId = select.value;
    const tbody = document.getElementById('payment-history-table-body');
    
    if (!vendCustomerId) {
        tbody.innerHTML = '<tr><td colspan="4" class="empty">Please select a staff member</td></tr>';
        return;
    }
    
    tbody.innerHTML = '<tr><td colspan="4" class="loading">Loading payment history...</td></tr>';
    
    try {
        const response = await fetch(`?action=get_payment_history&user_id=${userId}&vend_customer_id=${vendCustomerId}`);
        const data = await response.json();
        
        if (data.success && data.data && data.data.history) {
            if (data.data.history.length === 0) {
                tbody.innerHTML = '<tr><td colspan="4" class="empty">No payment history found</td></tr>';
            } else {
                tbody.innerHTML = data.data.history.map(event => `
                    <tr>
                        <td>${safeFormatDate(event.timestamp)}</td>
                        <td><span class="badge ${getEventTypeBadge(event.type)}">${escapeHtml(event.type)}</span></td>
                        <td>$${parseFloat(event.amount || 0).toFixed(2)}</td>
                        <td>${escapeHtml(event.description || '')}</td>
                    </tr>
                `).join('');
            }
        } else {
            tbody.innerHTML = `<tr><td colspan="4" class="error">Error: ${data.error || 'Unknown error'}</td></tr>`;
        }
    } catch (error) {
        console.error('Error loading payment history:', error);
        tbody.innerHTML = '<tr><td colspan="4" class="error">Error loading payment history</td></tr>';
    }
}

/**
 * Search transactions
 */
async function searchTransactions() {
    const select = document.getElementById('search-user-select');
    const userId = select.value;
    const searchTerm = document.getElementById('transaction-search-term').value;
    const tbody = document.getElementById('search-results-table-body');
    
    if (!searchTerm) {
        tbody.innerHTML = '<tr><td colspan="5" class="empty">Please enter a search term</td></tr>';
        return;
    }
    
    tbody.innerHTML = '<tr><td colspan="5" class="loading">Searching...</td></tr>';
    
    try {
        const response = await fetch(`?action=search_transactions&user_id=${userId}&search=${encodeURIComponent(searchTerm)}`);
        const data = await response.json();
        
        if (data.success && data.data && data.data.results) {
            if (data.data.results.length === 0) {
                tbody.innerHTML = '<tr><td colspan="5" class="empty">No results found</td></tr>';
            } else {
                tbody.innerHTML = data.data.results.map(result => `
                    <tr>
                        <td>${escapeHtml(result.user_name)}</td>
                        <td>${safeFormatDate(result.date)}</td>
                        <td><span class="badge ${getEventTypeBadge(result.type)}">${escapeHtml(result.type)}</span></td>
                        <td>$${parseFloat(result.amount || 0).toFixed(2)}</td>
                        <td>${escapeHtml(result.description || '')}</td>
                    </tr>
                `).join('');
            }
        } else {
            tbody.innerHTML = `<tr><td colspan="5" class="error">Error: ${data.error || 'Unknown error'}</td></tr>`;
        }
    } catch (error) {
        console.error('Error searching transactions:', error);
        tbody.innerHTML = '<tr><td colspan="5" class="error">Error searching transactions</td></tr>';
    }
}

// Event listener for staff search filter
document.addEventListener('DOMContentLoaded', function() {
    const staffSearch = document.getElementById('staff-search');
    if (staffSearch) {
        staffSearch.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const filteredStaff = staffData.filter(member =>
                member.name.toLowerCase().includes(searchTerm) ||
                member.email.toLowerCase().includes(searchTerm) ||
                (member.customer_code && member.customer_code.toLowerCase().includes(searchTerm))
            );
            renderStaffTable(filteredStaff);
        });
    }
});

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('payment-modal');
    if (event.target === modal) {
        closeModal();
    }
};
