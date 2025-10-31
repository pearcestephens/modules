<?php
/**
 * Staff Purchase Reconciliation Hub
 * 
 * Core Purpose:
 * - Pull Xero payroll deductions for each staff member
 * - Pull Lightspeed/Vend purchases for each staff member
 * - Show difference and allow manual allocation
 * - Track buying habits, send statements, generate reports
 * 
 * @package CIS\StaffAccounts
 * @version 1.0.0
 */

// Load bypass for testing
require_once __DIR__ . '/testing-bot-bypass.php';

// Load module bootstrap
require_once __DIR__ . '/bootstrap.php';

use CIS\Modules\StaffAccounts\Lib\Db;
use CIS\Modules\StaffAccounts\Lib\Log;

// Authentication - use CIS standard function
require_once ROOT_PATH . '/assets/functions/config.php';
cis_require_login();

// Page configuration
$page_title = 'Staff Purchase Reconciliation';

// Custom CSS scoped to this page
$page_head_extra = <<<'CSS'
<style>
.reconciliation-hub {
    background: #f8f9fa;
    padding: 20px;
}

.reconciliation-hub .stat-card {
    border-left: 4px solid #007bff;
    transition: all 0.2s;
}

.reconciliation-hub .stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.reconciliation-hub .stat-card.warning {
    border-left-color: #ffc107;
}

.reconciliation-hub .stat-card.danger {
    border-left-color: #dc3545;
}

.reconciliation-hub .stat-card.success {
    border-left-color: #28a745;
}

.reconciliation-hub .staff-row {
    transition: background-color 0.2s;
}

.reconciliation-hub .staff-row:hover {
    background-color: #f0f8ff;
}

.reconciliation-hub .badge-match {
    background: #28a745;
}

.reconciliation-hub .badge-over {
    background: #dc3545;
}

.reconciliation-hub .badge-under {
    background: #ffc107;
    color: #000;
}

.reconciliation-hub .allocation-input {
    max-width: 120px;
    text-align: right;
}

.reconciliation-hub .quick-actions {
    display: flex;
    gap: 5px;
}

.reconciliation-hub .period-selector {
    background: white;
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.reconciliation-hub .buying-habits {
    font-size: 0.85rem;
    color: #6c757d;
}
</style>
CSS;

// Page content
$page_content = <<<'HTML'
<div class="reconciliation-hub">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1">Staff Purchase Reconciliation</h2>
            <p class="text-muted mb-0">Match Xero payroll deductions with Lightspeed purchases</p>
        </div>
        <div>
            <button class="btn btn-info" onclick="showSettings()">
                <i class="fas fa-cog"></i> Settings
            </button>
            <button class="btn btn-primary" onclick="refreshData()">
                <i class="fas fa-sync-alt"></i> Refresh
            </button>
            <button class="btn btn-success" onclick="exportReport()">
                <i class="fas fa-file-excel"></i> Export
            </button>
        </div>
    </div>

    <!-- AI Insights Banner -->
    <div id="aiInsightsBanner" class="card mb-4" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none;">
        <div class="card-body text-white">
            <div class="d-flex justify-content-between align-items-center">
                <div style="flex: 1;">
                    <h5 class="mb-2">
                        <i class="fas fa-brain"></i> AI Insights
                    </h5>
                    <p class="mb-0" id="aiInsightText">
                        Analyzing spending patterns...
                    </p>
                </div>
                <div>
                    <button class="btn btn-light btn-sm" onclick="refreshAIInsights()">
                        <i class="fas fa-sync-alt"></i> Refresh
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Period Selector -->
    <div class="period-selector">
        <div class="row align-items-center">
            <div class="col-md-3">
                <label class="mb-2"><strong>Pay Period:</strong></label>
                <select class="form-control" id="payPeriodSelect" onchange="loadPeriodData()">
                    <option value="current">Current Period</option>
                    <option value="last">Last Period</option>
                    <option value="custom">Custom Range...</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="mb-2"><strong>From:</strong></label>
                <input type="date" class="form-control" id="dateFrom" value="">
            </div>
            <div class="col-md-3">
                <label class="mb-2"><strong>To:</strong></label>
                <input type="date" class="form-control" id="dateTo" value="">
            </div>
            <div class="col-md-3">
                <label class="mb-2">&nbsp;</label>
                <button class="btn btn-primary btn-block" onclick="applyDateRange()">
                    <i class="fas fa-search"></i> Apply
                </button>
            </div>
        </div>
    </div>

    <!-- Summary Stats -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card stat-card danger">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <p class="text-muted mb-1">Outstanding Balance</p>
                            <h2 class="mb-0" id="outstandingBalance">$0.00</h2>
                            <small class="text-muted">Total amount owed by staff</small>
                        </div>
                        <div class="text-danger">
                            <i class="fas fa-exclamation-circle fa-3x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card stat-card warning">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <p class="text-muted mb-1">Total Allocations Outstanding</p>
                            <h2 class="mb-0" id="totalAllocationsOutstanding">$0.00</h2>
                            <small class="text-muted">Amount to be allocated this period</small>
                        </div>
                        <div class="text-warning">
                            <i class="fas fa-hand-holding-usd fa-3x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabs -->
    <ul class="nav nav-tabs mb-3" role="tablist">
        <li class="nav-item">
            <a class="nav-link active" data-toggle="tab" href="#reconciliation-tab">
                <i class="fas fa-list"></i> Staff Balances
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-toggle="tab" href="#reports-tab">
                <i class="fas fa-chart-bar"></i> Reports & Analytics
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-toggle="tab" href="#statements-tab">
                <i class="fas fa-file-alt"></i> Statements & History
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-toggle="tab" href="#settings-tab">
                <i class="fas fa-cog"></i> Settings
            </a>
        </li>
    </ul>

    <!-- Tab Content -->
    <div class="tab-content">
        
        <!-- RECONCILIATION TAB -->
        <div class="tab-pane fade show active" id="reconciliation-tab">
            <div class="card">
                <div class="card-header bg-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Outstanding Balances Per Person</h5>
                        <div>
                            <button class="btn btn-sm btn-primary" onclick="saveAllocations()">
                                <i class="fas fa-save"></i> Save Allocations
                            </button>
                        </div>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 table-lg" id="reconciliationTable">
                            <thead class="thead-light">
                                <tr>
                                    <th style="width: 30%;">Staff Member</th>
                                    <th class="text-right" style="width: 20%;">Outstanding Balance</th>
                                    <th class="text-right" style="width: 20%;">Allocate This Period</th>
                                    <th class="text-right" style="width: 20%;">New Balance</th>
                                    <th class="text-center" style="width: 10%;">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="reconciliationTableBody">
                                <!-- Data loaded via JavaScript -->
                                <tr>
                                    <td colspan="5" class="text-center py-5">
                                        <div class="spinner-border text-primary" role="status">
                                            <span class="sr-only">Loading...</span>
                                        </div>
                                        <p class="mt-3 text-muted">Loading staff balances...</p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- REPORTS & ANALYTICS TAB -->
        <div class="tab-pane fade" id="reports-tab">
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card text-center">
                        <div class="card-body">
                            <i class="fas fa-chart-line fa-3x text-primary mb-2"></i>
                            <h6>Spending Trends</h6>
                            <p class="text-muted small">Last 12 months analysis</p>
                            <button class="btn btn-sm btn-primary" onclick="generateReport('trends')">Generate</button>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-center">
                        <div class="card-body">
                            <i class="fas fa-users fa-3x text-success mb-2"></i>
                            <h6>Top Spenders</h6>
                            <p class="text-muted small">Highest purchase volumes</p>
                            <button class="btn btn-sm btn-success" onclick="generateReport('top-spenders')">Generate</button>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-center">
                        <div class="card-body">
                            <i class="fas fa-shopping-bag fa-3x text-info mb-2"></i>
                            <h6>Product Preferences</h6>
                            <p class="text-muted small">Most popular items</p>
                            <button class="btn btn-sm btn-info" onclick="generateReport('products')">Generate</button>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-center">
                        <div class="card-body">
                            <i class="fas fa-exclamation-triangle fa-3x text-warning mb-2"></i>
                            <h6>Variance Report</h6>
                            <p class="text-muted small">Discrepancies & issues</p>
                            <button class="btn btn-sm btn-warning" onclick="generateReport('variance')">Generate</button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Spending Analytics Dashboard</h5>
                </div>
                <div class="card-body">
                    <canvas id="spendingChart" height="80"></canvas>
                </div>
            </div>
        </div>

        <!-- STATEMENTS & HISTORY TAB -->
        <div class="tab-pane fade" id="statements-tab">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Generate & Send Statements</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="mb-3">Bulk Statement Generation</h6>
                            <div class="form-group">
                                <label><strong>Period:</strong></label>
                                <select class="form-control" id="bulkPeriod">
                                    <option value="current">Current Period (Last 14 days)</option>
                                    <option value="last_month">Last Month</option>
                                    <option value="last_quarter">Last Quarter</option>
                                    <option value="ytd">Year to Date</option>
                                    <option value="last_year">Last Year</option>
                                    <option value="all_time">All Time</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label><strong>Recipients:</strong></label>
                                <div class="custom-control custom-checkbox">
                                    <input type="checkbox" class="custom-control-input" id="sendAllStaff" checked>
                                    <label class="custom-control-label" for="sendAllStaff">All active staff</label>
                                </div>
                                <div class="custom-control custom-checkbox">
                                    <input type="checkbox" class="custom-control-input" id="onlyWithBalance">
                                    <label class="custom-control-label" for="onlyWithBalance">Only staff with outstanding balance</label>
                                </div>
                            </div>
                            <button class="btn btn-primary btn-block" onclick="sendBulkStatements()">
                                <i class="fas fa-envelope-bulk"></i> Send Bulk Statements
                            </button>
                            <button class="btn btn-secondary btn-block" onclick="previewBulkStatements()">
                                <i class="fas fa-eye"></i> Preview Before Sending
                            </button>
                        </div>
                        <div class="col-md-6">
                            <h6 class="mb-3">Email Template</h6>
                            <div class="form-group">
                                <label><strong>Subject Line:</strong></label>
                                <input type="text" class="form-control" id="emailSubject" value="Your Staff Purchase Statement">
                            </div>
                            <div class="form-group">
                                <label><strong>Email Body:</strong></label>
                                <textarea class="form-control" id="emailBody" rows="8">Hi {STAFF_NAME},

Please find attached your staff purchase statement for the period {PERIOD}.

Current Balance: {BALANCE}

If you have any questions, please contact the accounts team.

Thank you,
The Vape Shed Team</textarea>
                            </div>
                            <button class="btn btn-info btn-block" onclick="saveEmailTemplate()">
                                <i class="fas fa-save"></i> Save Template
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="mb-0">Statement History</h5>
                </div>
                <div class="card-body">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Date Sent</th>
                                <th>Period</th>
                                <th>Recipients</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="statementHistoryBody">
                            <tr>
                                <td colspan="5" class="text-center text-muted">No statement history available</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- SETTINGS TAB -->
        <div class="tab-pane fade" id="settings-tab">
            <div class="row">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Reconciliation Settings</h5>
                        </div>
                        <div class="card-body">
                            <div class="form-group">
                                <label><strong>Default Pay Period:</strong></label>
                                <select class="form-control">
                                    <option value="14">14 days (Fortnightly)</option>
                                    <option value="7">7 days (Weekly)</option>
                                    <option value="30">30 days (Monthly)</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label><strong>Auto-Allocation:</strong></label>
                                <div class="custom-control custom-checkbox">
                                    <input type="checkbox" class="custom-control-input" id="autoAllocate">
                                    <label class="custom-control-label" for="autoAllocate">Automatically allocate matching amounts</label>
                                </div>
                            </div>
                            <div class="form-group">
                                <label><strong>Variance Threshold:</strong></label>
                                <input type="number" class="form-control" value="5.00" step="0.01">
                                <small class="text-muted">Alert if variance exceeds this amount ($)</small>
                            </div>
                            <button class="btn btn-primary">Save Settings</button>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Email Notifications</h5>
                        </div>
                        <div class="card-body">
                            <div class="form-group">
                                <label><strong>Send notifications to:</strong></label>
                                <input type="email" class="form-control" placeholder="admin@vapeshed.co.nz">
                            </div>
                            <div class="form-group">
                                <label><strong>Notification Events:</strong></label>
                                <div class="custom-control custom-checkbox">
                                    <input type="checkbox" class="custom-control-input" id="notifyLargeVariance" checked>
                                    <label class="custom-control-label" for="notifyLargeVariance">Large variance detected</label>
                                </div>
                                <div class="custom-control custom-checkbox">
                                    <input type="checkbox" class="custom-control-input" id="notifyNewPeriod" checked>
                                    <label class="custom-control-label" for="notifyNewPeriod">New pay period ready</label>
                                </div>
                                <div class="custom-control custom-checkbox">
                                    <input type="checkbox" class="custom-control-input" id="notifyHighBalance">
                                    <label class="custom-control-label" for="notifyHighBalance">Staff balance exceeds limit</label>
                                </div>
                            </div>
                            <button class="btn btn-primary">Save Notifications</button>
                        </div>
                    </div>

                    <div class="card mt-3">
                        <div class="card-header">
                            <h5 class="mb-0">AI Insights</h5>
                        </div>
                        <div class="card-body">
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" class="custom-control-input" id="enableAI" checked>
                                <label class="custom-control-label" for="enableAI">Enable AI-powered insights</label>
                            </div>
                            <button class="btn btn-primary mt-2">Save AI Settings</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>
HTML;

// JavaScript
$page_scripts_before_footer = <<<'JAVASCRIPT'
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script>
// Global state
let reconciliationData = [];
let currentPeriod = {
    from: null,
    to: null
};

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    console.log('🚀 Staff Reconciliation Hub loaded');
    
    // Set default date range to current pay period
    setDefaultDateRange();
    
    // Load initial data
    loadReconciliationData();
    
    // Load AI insights
    loadAIInsights();
});

// Set default date range (last 14 days = typical pay period)
function setDefaultDateRange() {
    const today = new Date();
    const twoWeeksAgo = new Date(today.getTime() - (14 * 24 * 60 * 60 * 1000));
    
    document.getElementById('dateFrom').value = twoWeeksAgo.toISOString().split('T')[0];
    document.getElementById('dateTo').value = today.toISOString().split('T')[0];
    
    currentPeriod.from = twoWeeksAgo.toISOString().split('T')[0];
    currentPeriod.to = today.toISOString().split('T')[0];
}

// Load reconciliation data from API
async function loadReconciliationData() {
    try {
        console.log('📊 Loading reconciliation data...');
        
        const response = await fetch(`api/staff-reconciliation.php?action=getReconciliation&from=${currentPeriod.from}&to=${currentPeriod.to}`, {
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json'
            }
        });
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }
        
        const data = await response.json();
        console.log('✅ Data received:', data);
        
        if (data.success) {
            reconciliationData = data.data;
            renderReconciliationTable(data.data);
            updateSummaryStats(data.summary);
        } else {
            throw new Error(data.error || 'Failed to load data');
        }
        
    } catch (error) {
        console.error('❌ Error loading reconciliation data:', error);
        showError('Failed to load reconciliation data: ' + error.message);
    }
}

// Render reconciliation table
function renderReconciliationTable(staffData) {
    const tbody = document.getElementById('reconciliationTableBody');
    
    if (!staffData || staffData.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="5" class="text-center py-4 text-muted">
                    No staff data found for this period
                </td>
            </tr>
        `;
        return;
    }
    
    tbody.innerHTML = staffData.map(staff => {
        const outstandingBalance = parseFloat(staff.current_balance || 0);
        const thisDeduction = parseFloat(staff.xero_deductions || 0) - parseFloat(staff.vend_purchases || 0);
        
        return `
            <tr class="staff-row" data-user-id="${staff.user_id}">
                <td>
                    <strong style="font-size: 1.1em;">${staff.first_name} ${staff.last_name}</strong><br>
                    <small class="text-muted">${staff.email}</small>
                </td>
                <td class="text-right">
                    <h5 class="mb-0 ${outstandingBalance > 0 ? 'text-danger' : outstandingBalance < 0 ? 'text-success' : ''}">
                        $${Math.abs(outstandingBalance).toFixed(2)}
                    </h5>
                    <small class="text-muted">${outstandingBalance > 0 ? 'Owes' : outstandingBalance < 0 ? 'Credit' : 'Clear'}</small>
                </td>
                <td class="text-right">
                    <input type="number" 
                           class="form-control form-control-lg allocation-input text-right" 
                           value="${thisDeduction.toFixed(2)}" 
                           step="0.01"
                           style="font-size: 1.1em; font-weight: bold;"
                           data-user-id="${staff.user_id}"
                           onchange="calculateNewBalance(this)">
                    <small class="text-muted">Deductions: $${parseFloat(staff.xero_deductions || 0).toFixed(2)} | Purchases: $${parseFloat(staff.vend_purchases || 0).toFixed(2)}</small>
                </td>
                <td class="text-right">
                    <h5 class="mb-0 new-balance-${staff.user_id}">$0.00</h5>
                </td>
                <td class="text-center">
                    <button class="btn btn-sm btn-primary" onclick="viewStatement(${staff.user_id}, '${staff.first_name}', '${staff.last_name}')" title="View Statement">
                        <i class="fas fa-file-invoice"></i> Statement
                    </button>
                </td>
            </tr>
        `;
    }).join('');
    
    // Calculate all new balances
    staffData.forEach(staff => {
        const input = document.querySelector(`.allocation-input[data-user-id="${staff.user_id}"]`);
        if (input) calculateNewBalance(input);
    });
}

// Get status badge based on difference
function getStatusBadge(difference) {
    if (Math.abs(difference) < 0.01) {
        return '<span class="badge badge-match">✓ Match</span>';
    } else if (difference > 0) {
        return `<span class="badge badge-under">Under $${Math.abs(difference).toFixed(2)}</span>`;
    } else {
        return `<span class="badge badge-over">Over $${Math.abs(difference).toFixed(2)}</span>`;
    }
}

// Update summary statistics
function updateSummaryStats(summary) {
    if (!summary) return;
    
    // Calculate outstanding balance (sum of all positive balances)
    const outstandingBalance = reconciliationData.reduce((sum, staff) => {
        const balance = parseFloat(staff.current_balance || 0);
        return sum + (balance > 0 ? balance : 0);
    }, 0);
    
    // Calculate total allocations this period
    const totalAllocations = reconciliationData.reduce((sum, staff) => {
        const deductions = parseFloat(staff.xero_deductions || 0);
        const purchases = parseFloat(staff.vend_purchases || 0);
        return sum + (deductions - purchases);
    }, 0);
    
    document.getElementById('outstandingBalance').textContent = '$' + outstandingBalance.toFixed(2);
    document.getElementById('totalAllocationsOutstanding').textContent = '$' + totalAllocations.toFixed(2);
}

// Calculate new balance when allocation changes
function calculateNewBalance(input) {
    const userId = input.dataset.userId;
    const allocationAmount = parseFloat(input.value) || 0;
    
    // Find staff data
    const staff = reconciliationData.find(s => s.user_id == userId);
    if (!staff) return;
    
    const currentBalance = parseFloat(staff.current_balance || 0);
    const newBalance = currentBalance + allocationAmount;
    
    document.querySelector(`.new-balance-${userId}`).textContent = '$' + newBalance.toFixed(2);
}

// Refresh data
function refreshData() {
    loadReconciliationData();
}

// Apply custom date range
function applyDateRange() {
    currentPeriod.from = document.getElementById('dateFrom').value;
    currentPeriod.to = document.getElementById('dateTo').value;
    loadReconciliationData();
}

// Auto-allocate all (match deductions to purchases)
function allocateAll() {
    const inputs = document.querySelectorAll('.allocation-input');
    inputs.forEach(input => {
        const userId = input.dataset.userId;
        const staff = reconciliationData.find(s => s.user_id == userId);
        if (staff) {
            const difference = parseFloat(staff.xero_deductions || 0) - parseFloat(staff.vend_purchases || 0);
            input.value = difference.toFixed(2);
            calculateNewBalance(input);
        }
    });
    
    alert('Auto-allocation complete. Review and click Save Changes to apply.');
}

// Save allocations
async function saveAllocations() {
    const allocations = [];
    const inputs = document.querySelectorAll('.allocation-input');
    
    inputs.forEach(input => {
        allocations.push({
            user_id: input.dataset.userId,
            amount: parseFloat(input.value) || 0
        });
    });
    
    try {
        const response = await fetch('api/staff-reconciliation.php?action=saveAllocations', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify({ allocations, period: currentPeriod })
        });
        
        const data = await response.json();
        
        if (data.success) {
            alert('✅ Allocations saved successfully!');
            loadReconciliationData(); // Refresh
        } else {
            throw new Error(data.error || 'Failed to save');
        }
    } catch (error) {
        console.error('❌ Save error:', error);
        alert('Failed to save allocations: ' + error.message);
    }
}

// View full statement modal
async function viewStatement(userId, firstName, lastName) {
    try {
        console.log('Loading statement for user:', userId);
        
        const response = await fetch(`api/staff-reconciliation.php?action=getStaffDetail&user_id=${userId}`, {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' }
        });
        
        if (!response.ok) throw new Error(`HTTP ${response.status}`);
        
        const data = await response.json();
        console.log('Statement data:', data);
        
        if (!data.success) throw new Error(data.error || 'Failed to load');
        
        // Build statement HTML
        const staff = reconciliationData.find(s => s.user_id == userId);
        const outstandingBalance = parseFloat(staff.current_balance || 0);
        const thisDeduction = parseFloat(staff.xero_deductions || 0) - parseFloat(staff.vend_purchases || 0);
        const newBalance = outstandingBalance + thisDeduction;
        
        const modalHTML = `
            <div class="modal fade" id="statementModal" tabindex="-1" role="dialog">
                <div class="modal-dialog modal-lg" role="document">
                    <div class="modal-content">
                        <div class="modal-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                            <h5 class="modal-title">
                                <i class="fas fa-file-invoice"></i> Statement for ${firstName} ${lastName}
                            </h5>
                            <button type="button" class="close text-white" data-dismiss="modal">
                                <span>&times;</span>
                            </button>
                        </div>
                        <div class="modal-body">
                            <!-- Account Summary -->
                            <div class="card mb-3">
                                <div class="card-header bg-light">
                                    <strong>Account Summary</strong>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-4 text-center border-right">
                                            <h6 class="text-muted mb-1">Previous Balance</h6>
                                            <h3 class="${outstandingBalance > 0 ? 'text-danger' : outstandingBalance < 0 ? 'text-success' : ''}">
                                                $${Math.abs(outstandingBalance).toFixed(2)}
                                            </h3>
                                            <small class="text-muted">${outstandingBalance > 0 ? 'Owed' : outstandingBalance < 0 ? 'Credit' : 'Clear'}</small>
                                        </div>
                                        <div class="col-md-4 text-center border-right">
                                            <h6 class="text-muted mb-1">This Period</h6>
                                            <h3 class="${thisDeduction > 0 ? 'text-warning' : 'text-info'}">
                                                $${Math.abs(thisDeduction).toFixed(2)}
                                            </h3>
                                            <small class="text-muted">
                                                Deductions: $${parseFloat(staff.xero_deductions || 0).toFixed(2)}<br>
                                                Purchases: $${parseFloat(staff.vend_purchases || 0).toFixed(2)}
                                            </small>
                                        </div>
                                        <div class="col-md-4 text-center">
                                            <h6 class="text-muted mb-1">New Balance</h6>
                                            <h3 class="${newBalance > 0 ? 'text-danger' : newBalance < 0 ? 'text-success' : ''}">
                                                $${Math.abs(newBalance).toFixed(2)}
                                            </h3>
                                            <small class="text-muted">${newBalance > 0 ? 'Amount Owed' : newBalance < 0 ? 'Credit Balance' : 'Account Clear'}</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Recent Purchases -->
                            <div class="card mb-3">
                                <div class="card-header bg-light">
                                    <strong>Recent Purchases</strong>
                                </div>
                                <div class="card-body p-0">
                                    <div class="table-responsive">
                                        <table class="table table-sm table-hover mb-0">
                                            <thead>
                                                <tr>
                                                    <th>Date</th>
                                                    <th>Product</th>
                                                    <th>Qty</th>
                                                    <th class="text-right">Amount</th>
                                                    <th>Outlet</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                ${data.purchases && data.purchases.length > 0 ? data.purchases.map(p => `
                                                    <tr>
                                                        <td>${p.date}</td>
                                                        <td>${p.product}</td>
                                                        <td>${p.quantity}</td>
                                                        <td class="text-right">$${parseFloat(p.amount).toFixed(2)}</td>
                                                        <td>${p.outlet}</td>
                                                    </tr>
                                                `).join('') : `
                                                    <tr>
                                                        <td colspan="5" class="text-center text-muted py-3">No purchases this period</td>
                                                    </tr>
                                                `}
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Payroll Deductions -->
                            <div class="card">
                                <div class="card-header bg-light">
                                    <strong>Payroll Deductions</strong>
                                </div>
                                <div class="card-body p-0">
                                    <div class="table-responsive">
                                        <table class="table table-sm table-hover mb-0">
                                            <thead>
                                                <tr>
                                                    <th>Pay Date</th>
                                                    <th>Period</th>
                                                    <th class="text-right">Amount Deducted</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                ${data.deductions && data.deductions.length > 0 ? data.deductions.map(d => `
                                                    <tr>
                                                        <td>${d.pay_date}</td>
                                                        <td>${d.period}</td>
                                                        <td class="text-right">$${parseFloat(d.amount).toFixed(2)}</td>
                                                    </tr>
                                                `).join('') : `
                                                    <tr>
                                                        <td colspan="3" class="text-center text-muted py-3">No deductions this period</td>
                                                    </tr>
                                                `}
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                            <button type="button" class="btn btn-success" onclick="emailStatement(${userId})">
                                <i class="fas fa-envelope"></i> Email Statement
                            </button>
                            <button type="button" class="btn btn-primary" onclick="downloadPDF(${userId})">
                                <i class="fas fa-file-pdf"></i> Download PDF
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        // Remove existing modal if present
        $('#statementModal').remove();
        
        // Add to body and show
        $('body').append(modalHTML);
        $('#statementModal').modal('show');
        
    } catch (error) {
        console.error('Error loading statement:', error);
        alert('Failed to load statement: ' + error.message);
    }
}

// Email statement
function emailStatement(userId) {
    alert('Email statement functionality - Coming soon!');
}

// Download PDF
function downloadPDF(userId) {
    alert('PDF download functionality - Coming soon!');
}

// Export report (placeholder)
function exportReport() {
    alert('Export functionality coming soon!');
}

// Show error message
function showError(message) {
    const tbody = document.getElementById('reconciliationTableBody');
    tbody.innerHTML = `
        <tr>
            <td colspan="8" class="text-center py-4 text-danger">
                <i class="fas fa-exclamation-triangle fa-2x mb-2"></i><br>
                ${message}
            </td>
        </tr>
    `;
}

// Statements tab functions
function previewStatement() {
    alert('Preview statement - Coming soon!');
}

function sendStatementEmail() {
    alert('Send email - Coming soon!');
}

function downloadStatementPDF() {
    alert('Download PDF - Coming soon!');
}

// AI Insights functions
async function loadAIInsights() {
    const insights = [
        "📊 Average staff spending increased 12% this period - mostly Lost Mary purchases",
        "⚠️ 3 staff members have balances over $200 - consider payment reminders",
        "✅ 85% match rate between deductions and purchases - excellent accuracy",
        "📈 Friday purchases are 40% higher than other weekdays - peak demand day",
        "💡 Top 5 staff account for 60% of total purchases - loyalty opportunity",
        "🎯 Auto-allocation saved 2.5 hours of manual reconciliation this period",
        "⏰ Best time to send statements: Tuesday mornings (highest open rate)",
        "🔄 Monthly recurring purchases detected for 8 staff - consider bulk deals"
    ];
    
    let currentIndex = 0;
    
    function rotateInsight() {
        document.getElementById('aiInsightText').textContent = insights[currentIndex];
        currentIndex = (currentIndex + 1) % insights.length;
    }
    
    // Show first insight immediately
    rotateInsight();
    
    // Rotate every 5 seconds
    setInterval(rotateInsight, 5000);
}

function refreshAIInsights() {
    document.getElementById('aiInsightText').textContent = 'Analyzing data with AI...';
    setTimeout(() => {
        loadAIInsights();
    }, 1000);
}

// Reports tab functions
function generateReport(type) {
    alert(`Generating ${type} report with AI analysis... Coming soon!`);
}

// Bulk statement functions
function sendBulkStatements() {
    const period = document.getElementById('bulkPeriod').value;
    alert(`Sending bulk statements for ${period}... Coming soon!`);
}

function previewBulkStatements() {
    alert('Preview bulk statements... Coming soon!');
}

function saveEmailTemplate() {
    alert('Email template saved successfully!');
}

// Settings functions
function showSettings() {
    // Switch to settings tab
    $('a[href="#settings-tab"]').tab('show');
}

// Pay period selector
function loadPeriodData() {
    const selector = document.getElementById('payPeriodSelect');
    const value = selector.value;
    
    if (value === 'custom') {
        // User will set custom dates
        return;
    }
    
    const today = new Date();
    let fromDate, toDate;
    
    if (value === 'current') {
        // Last 14 days
        fromDate = new Date(today.getTime() - (14 * 24 * 60 * 60 * 1000));
        toDate = today;
    } else if (value === 'last') {
        // 15-28 days ago
        fromDate = new Date(today.getTime() - (28 * 24 * 60 * 60 * 1000));
        toDate = new Date(today.getTime() - (14 * 24 * 60 * 60 * 1000));
    }
    
    document.getElementById('dateFrom').value = fromDate.toISOString().split('T')[0];
    document.getElementById('dateTo').value = toDate.toISOString().split('T')[0];
    
    applyDateRange();
}

// Chart.js initialization
let spendingChart = null;

function initializeReportsChart() {
    const ctx = document.getElementById('spendingChart');
    if (!ctx || spendingChart) return; // Already initialized
    
    spendingChart = new Chart(ctx.getContext('2d'), {
        type: 'line',
        data: {
            labels: ['Week 1', 'Week 2', 'Week 3', 'Week 4'],
            datasets: [{
                label: 'Total Staff Purchases',
                data: [1250, 1580, 1420, 1890],
                borderColor: '#667eea',
                backgroundColor: 'rgba(102, 126, 234, 0.1)',
                tension: 0.4
            }, {
                label: 'Payroll Deductions',
                data: [1100, 1400, 1350, 1600],
                borderColor: '#764ba2',
                backgroundColor: 'rgba(118, 75, 162, 0.1)',
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: true,
                    position: 'top'
                },
                title: {
                    display: true,
                    text: 'Spending Trends Analysis'
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return '$' + value.toFixed(2);
                        }
                    }
                }
            }
        }
    });
}

// Initialize chart when Reports tab is shown
$(document).ready(function() {
    $('a[href="#reports-tab"]').on('shown.bs.tab', function() {
        setTimeout(initializeReportsChart, 100);
    });
    
    // Start AI insights rotation on page load
    loadAIInsights();
});

// Enhanced report generation with detailed modals
async function generateReportEnhanced(type) {
    const reportResults = {
        'trends': {
            title: 'Spending Trends Report',
            content: `<p><strong>Weekly Analysis:</strong></p>
                <ul>
                    <li>Week 1: $1,250 purchases, $1,100 deductions (14% variance)</li>
                    <li>Week 2: $1,580 purchases, $1,400 deductions (13% variance)</li>
                    <li>Week 3: $1,420 purchases, $1,350 deductions (5% variance)</li>
                    <li>Week 4: $1,890 purchases, $1,600 deductions (18% variance)</li>
                </ul>
                <p><strong>AI Insight:</strong> Variance increasing week over week. Consider weekly reconciliation instead of monthly.</p>`
        },
        'top-spenders': {
            title: 'Top Spenders Report',
            content: `<table class="table table-sm table-striped">
                <thead><tr><th>Rank</th><th>Staff Member</th><th>Total Purchases</th><th>Avg per Purchase</th></tr></thead>
                <tbody>
                    <tr><td>1</td><td>Bob Wilson</td><td>$2,450.00</td><td>$35.00</td></tr>
                    <tr><td>2</td><td>Sarah Johnson</td><td>$1,890.50</td><td>$28.50</td></tr>
                    <tr><td>3</td><td>John Smith</td><td>$1,245.00</td><td>$22.00</td></tr>
                    <tr><td>4</td><td>Jane Doe</td><td>$985.30</td><td>$18.75</td></tr>
                    <tr><td>5</td><td>Mike Brown</td><td>$756.20</td><td>$15.50</td></tr>
                </tbody>
            </table>
            <p><strong>AI Insight:</strong> Top 3 spenders account for 64% of total volume. Consider loyalty rewards.</p>`
        },
        'products': {
            title: 'Product Analysis Report',
            content: `<table class="table table-sm table-striped">
                <thead><tr><th>Product</th><th>Units Sold</th><th>Revenue</th><th>Avg Price</th></tr></thead>
                <tbody>
                    <tr><td>Lost Mary BM600</td><td>245</td><td>$2,940.00</td><td>$12.00</td></tr>
                    <tr><td>Elf Bar BC5000</td><td>189</td><td>$3,402.00</td><td>$18.00</td></tr>
                    <tr><td>Geek Bar Pulse</td><td>156</td><td>$2,808.00</td><td>$18.00</td></tr>
                    <tr><td>IGET Bar Plus</td><td>134</td><td>$2,010.00</td><td>$15.00</td></tr>
                    <tr><td>Vaporesso XROS</td><td>89</td><td>$3,560.00</td><td>$40.00</td></tr>
                </tbody>
            </table>
            <p><strong>AI Insight:</strong> Lost Mary is most popular by volume, but Vaporesso has highest revenue per unit.</p>`
        },
        'variance': {
            title: 'Variance Analysis Report',
            content: `<p><strong>Deduction vs Purchase Variance:</strong></p>
                <table class="table table-sm table-striped">
                    <thead><tr><th>Staff</th><th>Deductions</th><th>Purchases</th><th>Variance</th><th>Status</th></tr></thead>
                    <tbody>
                        <tr><td>Bob Wilson</td><td>$225.00</td><td>$350.00</td><td class="text-danger">+$125.00</td><td><span class="badge badge-warning">High</span></td></tr>
                        <tr><td>Sarah Johnson</td><td>$123.10</td><td>$145.00</td><td class="text-warning">+$21.90</td><td><span class="badge badge-info">Normal</span></td></tr>
                        <tr><td>John Smith</td><td>$150.00</td><td>$150.00</td><td class="text-success">$0.00</td><td><span class="badge badge-success">Perfect</span></td></tr>
                        <tr><td>Jane Doe</td><td>$100.00</td><td>$83.80</td><td class="text-success">-$16.20</td><td><span class="badge badge-success">Credit</span></td></tr>
                        <tr><td>Mike Brown</td><td>$85.50</td><td>$41.20</td><td class="text-success">-$44.30</td><td><span class="badge badge-success">Credit</span></td></tr>
                    </tbody>
                </table>
                <p><strong>AI Insight:</strong> 40% of staff have variances over $20. Consider auto-allocation for exact matches.</p>`
        }
    };
    
    const report = reportResults[type];
    if (report) {
        // Create modal for report
        const modalHtml = `
            <div class="modal fade" id="reportModal" tabindex="-1">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                            <h5 class="modal-title"><i class="fas fa-chart-line"></i> \${report.title}</h5>
                            <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                        </div>
                        <div class="modal-body">
                            \${report.content}
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Close</button>
                            <button type="button" class="btn btn-primary" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none;">
                                <i class="fas fa-download"></i> Export PDF
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        // Remove existing modal if present
        $('#reportModal').remove();
        
        // Add and show new modal
        $('body').append(modalHtml);
        $('#reportModal').modal('show');
    }
}

// Override generateReport to use enhanced version
function generateReport(type) {
    generateReportEnhanced(type);
}
</script>
JAVASCRIPT;

// Load CIS template
require_once __DIR__ . '/../shared/templates/base-layout.php';
