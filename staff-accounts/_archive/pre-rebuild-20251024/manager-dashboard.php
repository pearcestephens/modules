<?php
/**
 * Manager Dashboard - Staff Account Overview
 * 
 * Centralized dashboard for managers to monitor all staff accounts
 * Shows balances, trends, alerts, and action items
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/lib/StaffAccountService.php';

// Authentication - use CIS standard function
require_once ROOT_PATH . '/assets/functions/config.php';
cis_require_login();

// Service uses static methods, no instantiation needed
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manager Dashboard - Staff Accounts</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/staff-accounts/css/staff-reconciliation.css">
    <style>
        .dashboard-card {
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transition: transform 0.2s;
        }
        .dashboard-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 20px;
        }
        .stat-card.danger {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }
        .stat-card.success {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }
        .stat-card.warning {
            background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
        }
        .stat-value {
            font-size: 2.5rem;
            font-weight: bold;
            margin: 10px 0;
        }
        .stat-label {
            font-size: 0.9rem;
            opacity: 0.9;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .action-item {
            padding: 15px;
            border-left: 4px solid #dc3545;
            background: #fff3f3;
            margin-bottom: 10px;
            border-radius: 4px;
        }
        .action-item.medium {
            border-left-color: #ffc107;
            background: #fff9e6;
        }
        .action-item.low {
            border-left-color: #28a745;
            background: #f0f9f4;
        }
        .risk-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        .risk-high {
            background: #dc3545;
            color: white;
        }
        .risk-medium {
            background: #ffc107;
            color: #000;
        }
        .risk-low {
            background: #28a745;
            color: white;
        }
        .chart-container {
            position: relative;
            height: 300px;
            margin: 20px 0;
        }
        .filter-bar {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .quick-action-btn {
            width: 100%;
            margin-bottom: 10px;
        }
        .staff-row {
            cursor: pointer;
            transition: background 0.2s;
        }
        .staff-row:hover {
            background: #f8f9fa;
        }
    </style>
</head>
<body>

<div class="container-fluid mt-4">
    <div class="row mb-4">
        <div class="col-12">
            <h1><i class="fas fa-chart-line"></i> Manager Dashboard - Staff Accounts</h1>
            <p class="text-muted">Real-time overview of all staff account balances and activity</p>
        </div>
    </div>

    <!-- Executive Summary -->
    <div class="row mb-4" id="executiveSummary">
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-label">Total Staff Debt</div>
                <div class="stat-value" id="totalDebt">$0</div>
                <small><i class="fas fa-users"></i> <span id="staffCount">0</span> active staff</small>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card danger">
                <div class="stat-label">High Risk Accounts</div>
                <div class="stat-value" id="highRiskCount">0</div>
                <small><i class="fas fa-exclamation-triangle"></i> >$500 or >80% utilization</small>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card success">
                <div class="stat-label">Payments This Month</div>
                <div class="stat-value" id="paymentsThisMonth">$0</div>
                <small><i class="fas fa-check-circle"></i> <span id="paymentCount">0</span> transactions</small>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card warning">
                <div class="stat-label">Average Balance</div>
                <div class="stat-value" id="avgBalance">$0</div>
                <small><i class="fas fa-balance-scale"></i> per staff member</small>
            </div>
        </div>
    </div>

    <!-- Action Items -->
    <div class="row mb-4">
        <div class="col-md-8">
            <!-- Staff List -->
            <div class="card dashboard-card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-users"></i> All Staff Accounts</h5>
                </div>
                <div class="card-body">
                    <!-- Filters -->
                    <div class="filter-bar">
                        <div class="row">
                            <div class="col-md-4">
                                <input type="text" class="form-control" id="searchStaff" placeholder="🔍 Search by name...">
                            </div>
                            <div class="col-md-3">
                                <select class="form-control" id="filterDepartment">
                                    <option value="">All Departments</option>
                                    <option value="retail">Retail</option>
                                    <option value="warehouse">Warehouse</option>
                                    <option value="office">Office</option>
                                    <option value="management">Management</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <select class="form-control" id="filterRisk">
                                    <option value="">All Risk Levels</option>
                                    <option value="high">High Risk</option>
                                    <option value="medium">Medium Risk</option>
                                    <option value="low">Low Risk</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <button class="btn btn-success btn-block" onclick="exportReport()">
                                    <i class="fas fa-download"></i> Export
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Staff Table -->
                    <div class="table-responsive">
                        <table class="table table-hover" id="staffTable">
                            <thead>
                                <tr>
                                    <th>Staff Member</th>
                                    <th>Department</th>
                                    <th>Balance</th>
                                    <th>Credit Limit</th>
                                    <th>Utilization</th>
                                    <th>Risk</th>
                                    <th>Last Purchase</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="staffTableBody">
                                <tr>
                                    <td colspan="8" class="text-center">
                                        <i class="fas fa-spinner fa-spin"></i> Loading staff data...
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <!-- Action Items Panel -->
            <div class="card dashboard-card mb-3">
                <div class="card-header bg-danger text-white">
                    <h5 class="mb-0"><i class="fas fa-exclamation-circle"></i> Action Items</h5>
                </div>
                <div class="card-body" id="actionItems">
                    <div class="text-center text-muted">
                        <i class="fas fa-spinner fa-spin"></i> Loading...
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="card dashboard-card">
                <div class="card-header bg-secondary text-white">
                    <h5 class="mb-0"><i class="fas fa-bolt"></i> Quick Actions</h5>
                </div>
                <div class="card-body">
                    <button class="btn btn-primary quick-action-btn" onclick="sendBulkReminders()">
                        <i class="fas fa-bell"></i> Send Payment Reminders
                    </button>
                    <button class="btn btn-info quick-action-btn" onclick="showCreditLimitManager()">
                        <i class="fas fa-credit-card"></i> Manage Credit Limits
                    </button>
                    <button class="btn btn-warning quick-action-btn" onclick="reviewPaymentPlans()">
                        <i class="fas fa-calendar-alt"></i> Review Payment Plans
                    </button>
                    <button class="btn btn-success quick-action-btn" onclick="generateReport()">
                        <i class="fas fa-file-pdf"></i> Generate PDF Report
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card dashboard-card">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="fas fa-chart-line"></i> Balance Trend (Last 30 Days)</h5>
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas id="balanceTrendChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card dashboard-card">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="fas fa-chart-bar"></i> Top 10 Staff by Balance</h5>
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas id="top10Chart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Department Comparison -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card dashboard-card">
                <div class="card-header bg-warning text-dark">
                    <h5 class="mb-0"><i class="fas fa-building"></i> Department Comparison</h5>
                </div>
                <div class="card-body">
                    <div class="chart-container" style="height: 250px;">
                        <canvas id="departmentChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Staff Detail Modal -->
<div class="modal fade" id="staffDetailModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-user"></i> Staff Account Details</h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body" id="staffDetailBody">
                <div class="text-center">
                    <i class="fas fa-spinner fa-spin"></i> Loading...
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>

<script>
let allStaffData = [];
let charts = {};

// Load dashboard data
$(document).ready(function() {
    loadDashboardData();
    setupFilters();
    
    // Auto-refresh every 60 seconds
    setInterval(loadDashboardData, 60000);
});

function loadDashboardData() {
    $.ajax({
        url: '/staff-accounts/api/manager-dashboard.php',
        method: 'GET',
        data: { action: 'getDashboardData' },
        success: function(response) {
            if (response.success) {
                allStaffData = response.staffData;
                updateExecutiveSummary(response.summary);
                updateStaffTable(response.staffData);
                updateActionItems(response.actionItems);
                updateCharts(response.chartData);
            }
        },
        error: function() {
            console.error('Failed to load dashboard data');
        }
    });
}

function updateExecutiveSummary(summary) {
    $('#totalDebt').text('$' + Number(summary.totalDebt).toLocaleString('en-NZ', {minimumFractionDigits: 2}));
    $('#staffCount').text(summary.staffCount);
    $('#highRiskCount').text(summary.highRiskCount);
    $('#paymentsThisMonth').text('$' + Number(summary.paymentsThisMonth).toLocaleString('en-NZ', {minimumFractionDigits: 2}));
    $('#paymentCount').text(summary.paymentCount);
    $('#avgBalance').text('$' + Number(summary.avgBalance).toLocaleString('en-NZ', {minimumFractionDigits: 2}));
}

function updateStaffTable(staffData) {
    const tbody = $('#staffTableBody');
    tbody.empty();
    
    if (staffData.length === 0) {
        tbody.html('<tr><td colspan="8" class="text-center">No staff data found</td></tr>');
        return;
    }
    
    staffData.forEach(staff => {
        const utilization = staff.credit_limit > 0 ? (staff.balance / staff.credit_limit * 100) : 0;
        let riskLevel = 'low';
        let riskBadge = 'risk-low';
        
        if (staff.balance > 500 || utilization > 80) {
            riskLevel = 'high';
            riskBadge = 'risk-high';
        } else if (staff.balance > 300 || utilization > 60) {
            riskLevel = 'medium';
            riskBadge = 'risk-medium';
        }
        
        const row = `
            <tr class="staff-row" data-user-id="${staff.user_id}" onclick="showStaffDetail(${staff.user_id})">
                <td><strong>${staff.name}</strong></td>
                <td>${staff.department || 'N/A'}</td>
                <td><strong>$${Number(staff.balance).toFixed(2)}</strong></td>
                <td>$${Number(staff.credit_limit).toFixed(2)}</td>
                <td>
                    <div class="progress" style="height: 20px;">
                        <div class="progress-bar ${utilization > 80 ? 'bg-danger' : utilization > 60 ? 'bg-warning' : 'bg-success'}" 
                             style="width: ${Math.min(utilization, 100)}%">
                            ${utilization.toFixed(0)}%
                        </div>
                    </div>
                </td>
                <td><span class="risk-badge ${riskBadge}">${riskLevel.toUpperCase()}</span></td>
                <td>${staff.last_purchase_date || 'Never'}</td>
                <td>
                    <button class="btn btn-sm btn-primary" onclick="event.stopPropagation(); sendReminder(${staff.user_id})">
                        <i class="fas fa-envelope"></i>
                    </button>
                </td>
            </tr>
        `;
        tbody.append(row);
    });
}

function updateActionItems(actionItems) {
    const container = $('#actionItems');
    container.empty();
    
    if (actionItems.length === 0) {
        container.html('<p class="text-muted">No action items at this time</p>');
        return;
    }
    
    actionItems.forEach(item => {
        const severity = item.severity || 'low';
        const html = `
            <div class="action-item ${severity}">
                <strong>${item.title}</strong>
                <p class="mb-1">${item.description}</p>
                <small class="text-muted"><i class="fas fa-clock"></i> ${item.timestamp}</small>
            </div>
        `;
        container.append(html);
    });
}

function updateCharts(chartData) {
    // Balance Trend Chart
    const balanceCtx = document.getElementById('balanceTrendChart').getContext('2d');
    if (charts.balanceTrend) charts.balanceTrend.destroy();
    
    charts.balanceTrend = new Chart(balanceCtx, {
        type: 'line',
        data: {
            labels: chartData.balanceTrend.labels,
            datasets: [{
                label: 'Total Balance',
                data: chartData.balanceTrend.data,
                borderColor: '#667eea',
                backgroundColor: 'rgba(102, 126, 234, 0.1)',
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            }
        }
    });
    
    // Top 10 Chart
    const top10Ctx = document.getElementById('top10Chart').getContext('2d');
    if (charts.top10) charts.top10.destroy();
    
    charts.top10 = new Chart(top10Ctx, {
        type: 'bar',
        data: {
            labels: chartData.top10.labels,
            datasets: [{
                label: 'Balance',
                data: chartData.top10.data,
                backgroundColor: '#f5576c'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            indexAxis: 'y',
            plugins: {
                legend: { display: false }
            }
        }
    });
    
    // Department Chart
    const deptCtx = document.getElementById('departmentChart').getContext('2d');
    if (charts.department) charts.department.destroy();
    
    charts.department = new Chart(deptCtx, {
        type: 'bar',
        data: {
            labels: chartData.departments.labels,
            datasets: [{
                label: 'Total Balance',
                data: chartData.departments.data,
                backgroundColor: ['#667eea', '#764ba2', '#f093fb', '#4facfe']
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            }
        }
    });
}

function setupFilters() {
    $('#searchStaff, #filterDepartment, #filterRisk').on('change keyup', function() {
        const search = $('#searchStaff').val().toLowerCase();
        const dept = $('#filterDepartment').val();
        const risk = $('#filterRisk').val();
        
        const filtered = allStaffData.filter(staff => {
            const matchesSearch = staff.name.toLowerCase().includes(search);
            const matchesDept = !dept || staff.department === dept;
            
            let staffRisk = 'low';
            const utilization = staff.credit_limit > 0 ? (staff.balance / staff.credit_limit * 100) : 0;
            if (staff.balance > 500 || utilization > 80) staffRisk = 'high';
            else if (staff.balance > 300 || utilization > 60) staffRisk = 'medium';
            
            const matchesRisk = !risk || staffRisk === risk;
            
            return matchesSearch && matchesDept && matchesRisk;
        });
        
        updateStaffTable(filtered);
    });
}

function showStaffDetail(userId) {
    $('#staffDetailModal').modal('show');
    
    // Load detailed info from staff-reconciliation API
    $.ajax({
        url: '/staff-accounts/api/staff-reconciliation.php',
        method: 'GET',
        data: { action: 'getStaffStatement', userId: userId },
        success: function(response) {
            if (response.success) {
                // Render detailed view (reuse statement template)
                $('#staffDetailBody').html(renderStaffStatement(response.data));
            }
        }
    });
}

function sendReminder(userId) {
    if (!confirm('Send payment reminder to this staff member?')) return;
    
    $.ajax({
        url: '/staff-accounts/api/manager-dashboard.php',
        method: 'POST',
        data: { action: 'sendReminder', userId: userId },
        success: function(response) {
            if (response.success) {
                alert('Reminder sent successfully!');
            } else {
                alert('Failed to send reminder: ' + response.error);
            }
        }
    });
}

function sendBulkReminders() {
    if (!confirm('Send payment reminders to all high-risk accounts?')) return;
    
    $.ajax({
        url: '/staff-accounts/api/manager-dashboard.php',
        method: 'POST',
        data: { action: 'sendBulkReminders' },
        success: function(response) {
            if (response.success) {
                alert(`Reminders sent to ${response.count} staff members`);
            }
        }
    });
}

function exportReport() {
    window.location.href = '/staff-accounts/api/manager-dashboard.php?action=exportCSV';
}

function generateReport() {
    window.open('/staff-accounts/api/manager-dashboard.php?action=generatePDF', '_blank');
}
</script>

</body>
</html>
