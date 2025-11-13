<?php
/**
 * Employee Mapping System - Modern UI Demo
 * 
 * Uses CIS base template with sidebar
 * All custom CSS scoped to .employee-mapping-container
 * 
 * @package CIS\StaffAccounts
 * @version 1.0.0
 */

// CRITICAL: Load bot bypass FIRST (before bootstrap)
require_once __DIR__ . '/testing-bot-bypass.php';

// Load module bootstrap
require_once __DIR__ . '/bootstrap.php';

// Authentication - use CIS standard function
require_once ROOT_PATH . '/assets/functions/config.php';
cis_require_login();

// Page configuration
$page_title = 'Employee Mapping System';

// Scoped CSS - ALL styles target .employee-mapping-container only
$page_head_extra = <<<'CSS'
<style>
/* ALL STYLES SCOPED TO .employee-mapping-container */
.employee-mapping-container {
    background-color: #f8f9fa;
}

.employee-mapping-container .dashboard-card {
    transition: transform 0.2s, box-shadow 0.2s;
    border-left: 4px solid;
}

.employee-mapping-container .dashboard-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.employee-mapping-container .border-danger {
    border-left-color: #dc3545 !important;
}

.employee-mapping-container .border-warning {
    border-left-color: #ffc107 !important;
}

.employee-mapping-container .border-success {
    border-left-color: #28a745 !important;
}

.employee-mapping-container .border-info {
    border-left-color: #17a2b8 !important;
}

.employee-mapping-container .nav-tabs .nav-link {
    color: #495057;
    border: none;
    border-bottom: 3px solid transparent;
    padding: 0.75rem 1.25rem;
}

.employee-mapping-container .nav-tabs .nav-link:hover {
    border-bottom-color: #dee2e6;
}

.employee-mapping-container .nav-tabs .nav-link.active {
    color: #007bff;
    border-bottom-color: #007bff;
    background: transparent;
}

.employee-mapping-container .list-group-item {
    border-left: none;
    border-right: none;
}

.employee-mapping-container .list-group-item:first-child {
    border-top: none;
}

.employee-mapping-container .badge {
    padding: 0.35em 0.65em;
}

.employee-mapping-container .card {
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    border: none;
}

.employee-mapping-container .card-header {
    background-color: #fff;
    border-bottom: 2px solid #f0f0f0;
}

.employee-mapping-container .table thead th {
    border-top: none;
    border-bottom: 2px solid #dee2e6;
    font-weight: 600;
    color: #495057;
}

.employee-mapping-container .btn-sm {
    padding: 0.25rem 0.75rem;
    font-size: 0.875rem;
}

.employee-mapping-container .text-muted {
    color: #6c757d !important;
}

.employee-mapping-container .bg-light {
    background-color: #f8f9fa !important;
}
</style>
CSS;

// Page content - wrapped in scoped container
$page_content = <<<'HTML'
<div class="employee-mapping-container">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h3 mb-0">
                        <i class="fas fa-users text-primary"></i> Employee Mapping System
                    </h1>
                    <p class="text-muted mb-0">Map Xero employees to Vend customers for staff account deductions</p>
                </div>
                <div>
                    <button class="btn btn-outline-primary btn-sm" id="refreshDataBtn">
                        <i class="fas fa-sync"></i> Refresh Data
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Dashboard Cards Row -->
    <div class="row mb-4">
        <!-- Blocked Amount Card -->
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card dashboard-card border-danger">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-title text-muted">Blocked Amount</h6>
                            <h3 class="card-text text-danger mb-0" id="blockedAmountValue">$9,543.36</h3>
                            <small class="text-muted">Requires immediate attention</small>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-exclamation-triangle fa-2x text-danger"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Unmapped Employees Card -->
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card dashboard-card border-warning">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-title text-muted">Unmapped Employees</h6>
                            <h3 class="card-text text-warning mb-0" id="unmappedCountValue">56</h3>
                            <small class="text-muted">Need customer mapping</small>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-user-clock fa-2x text-warning"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Active Mappings Card -->
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card dashboard-card border-success">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-title text-muted">Active Mappings</h6>
                            <h3 class="card-text text-success mb-0" id="activeMappingsValue">142</h3>
                            <small class="text-muted">Successfully mapped</small>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-check-circle fa-2x text-success"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Auto-Match Suggestions Card -->
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card dashboard-card border-info">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-title text-muted">Auto-Match Ready</h6>
                            <h3 class="card-text text-info mb-0" id="autoMatchValue">31</h3>
                            <small class="text-muted">Confidence &gt; 80%</small>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-magic fa-2x text-info"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Navigation Tabs -->
    <div class="row">
        <div class="col-12">
            <ul class="nav nav-tabs" id="mainTabs" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active" id="dashboard-tab" data-toggle="tab" href="#dashboard" role="tab">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="unmapped-tab" data-toggle="tab" href="#unmapped" role="tab">
                        <i class="fas fa-users"></i> Unmapped <span class="badge badge-warning ml-1">56</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="auto-match-tab" data-toggle="tab" href="#auto-match" role="tab">
                        <i class="fas fa-magic"></i> Auto-Match <span class="badge badge-info ml-1">31</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="manual-tab" data-toggle="tab" href="#manual" role="tab">
                        <i class="fas fa-hand-pointer"></i> Manual Mapping
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="analytics-tab" data-toggle="tab" href="#analytics" role="tab">
                        <i class="fas fa-chart-line"></i> Analytics
                    </a>
                </li>
            </ul>
        </div>
    </div>

    <!-- Tab Content -->
    <div class="tab-content mt-4" id="mainTabContent">
        <!-- Dashboard Tab -->
        <div class="tab-pane fade show active" id="dashboard" role="tabpanel">
            <div class="row">
                <div class="col-md-6">
                    <div class="card mb-3">
                        <div class="card-header">
                            <h5 class="mb-0">Recent Activity</h5>
                        </div>
                        <div class="card-body">
                            <div class="list-group list-group-flush">
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong>Sarah Johnson</strong> mapped to customer
                                        <small class="text-muted d-block">2 minutes ago</small>
                                    </div>
                                    <span class="badge badge-success">Mapped</span>
                                </div>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong>Mike Chen</strong> auto-match approved
                                        <small class="text-muted d-block">5 minutes ago</small>
                                    </div>
                                    <span class="badge badge-info">Auto</span>
                                </div>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong>Emma Davis</strong> mapping updated
                                        <small class="text-muted d-block">12 minutes ago</small>
                                    </div>
                                    <span class="badge badge-primary">Updated</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="card mb-3">
                        <div class="card-header">
                            <h5 class="mb-0">System Status</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <div class="d-flex justify-content-between mb-1">
                                    <span>Xero Connection</span>
                                    <span class="badge badge-success">Active</span>
                                </div>
                                <div class="progress" style="height: 5px;">
                                    <div class="progress-bar bg-success" style="width: 100%"></div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <div class="d-flex justify-content-between mb-1">
                                    <span>Vend Connection</span>
                                    <span class="badge badge-success">Active</span>
                                </div>
                                <div class="progress" style="height: 5px;">
                                    <div class="progress-bar bg-success" style="width: 100%"></div>
                                </div>
                            </div>
                            <div class="mb-0">
                                <div class="d-flex justify-content-between mb-1">
                                    <span>Processing Queue</span>
                                    <span class="badge badge-info">3 pending</span>
                                </div>
                                <div class="progress" style="height: 5px;">
                                    <div class="progress-bar bg-info" style="width: 15%"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Unmapped Tab -->
        <div class="tab-pane fade" id="unmapped" role="tabpanel">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Unmapped Employees (56)</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Employee Name</th>
                                    <th>Xero ID</th>
                                    <th>Email</th>
                                    <th>Blocked Amount</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><strong>John Smith</strong></td>
                                    <td><code>XE-12345</code></td>
                                    <td>john.smith@example.com</td>
                                    <td><span class="text-danger">$234.50</span></td>
                                    <td>
                                        <button class="btn btn-sm btn-primary">
                                            <i class="fas fa-link"></i> Map Now
                                        </button>
                                    </td>
                                </tr>
                                <tr>
                                    <td><strong>Jane Doe</strong></td>
                                    <td><code>XE-12346</code></td>
                                    <td>jane.doe@example.com</td>
                                    <td><span class="text-danger">$156.80</span></td>
                                    <td>
                                        <button class="btn btn-sm btn-primary">
                                            <i class="fas fa-link"></i> Map Now
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Auto-Match Tab -->
        <div class="tab-pane fade" id="auto-match" role="tabpanel">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Auto-Match Suggestions (31)</h5>
                    <button class="btn btn-sm btn-success">
                        <i class="fas fa-check-double"></i> Approve All High Confidence
                    </button>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> Review AI-suggested matches below. High confidence matches (>90%) can be batch-approved.
                    </div>
                    <div class="list-group">
                        <div class="list-group-item">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <strong>Robert Taylor</strong>
                                    <i class="fas fa-arrow-right mx-2"></i>
                                    <strong>Customer: Robert Taylor</strong>
                                    <br>
                                    <small class="text-muted">Match score: 98% (Email + Name exact match)</small>
                                </div>
                                <div>
                                    <span class="badge badge-success mr-2">98% Match</span>
                                    <button class="btn btn-sm btn-success">Approve</button>
                                    <button class="btn btn-sm btn-outline-secondary">Skip</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Manual Mapping Tab -->
        <div class="tab-pane fade" id="manual" role="tabpanel">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Manual Mapping Tool</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Select Xero Employee</label>
                                <select class="form-control">
                                    <option>Choose employee...</option>
                                    <option>Sarah Johnson (XE-12347)</option>
                                    <option>Mike Chen (XE-12348)</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Select Vend Customer</label>
                                <input type="text" class="form-control" placeholder="Search customers...">
                            </div>
                        </div>
                    </div>
                    <button class="btn btn-primary">
                        <i class="fas fa-link"></i> Create Mapping
                    </button>
                </div>
            </div>
        </div>

        <!-- Analytics Tab -->
        <div class="tab-pane fade" id="analytics" role="tabpanel">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Analytics Dashboard</h5>
                </div>
                <div class="card-body">
                    <canvas id="mappingChart" style="max-height: 400px;"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>
HTML;

// JavaScript to load REAL data from API
$page_scripts_before_footer = <<<'JS'
<script>
// Load real data when page loads
document.addEventListener('DOMContentLoaded', function() {
    console.log('Loading real Employee Mapping data...');
    
    // Load stats
    loadStats();
    
    // Load unmapped employees
    loadUnmappedEmployees();
    
    // Load recent activity
    loadRecentActivity();
});

async function loadStats() {
    try {
        console.log('📊 Fetching stats from API...');
        // Use relative path from current location - include credentials for session
        const response = await fetch('api/employee-mapping.php?action=getStats', {
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json'
            }
        });
        
        console.log('📡 Response received:', {
            status: response.status,
            statusText: response.statusText,
            ok: response.ok,
            headers: {
                contentType: response.headers.get('content-type')
            }
        });
        
        if (!response.ok) {
            const errorText = await response.text();
            console.error('❌ API Error Response:', errorText);
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        
        const data = await response.json();
        console.log('✅ Stats data received:', data);
        
        if (data.success) {
            document.getElementById('blockedAmountValue').textContent = '$' + (data.data.blocked_amount || '0.00');
            document.getElementById('unmappedCountValue').textContent = data.data.unmapped_count || '0';
            document.getElementById('activeMappingsValue').textContent = data.data.active_mappings || '0';
            document.getElementById('autoMatchValue').textContent = data.data.auto_match_suggestions || '0';
            
            // Update badges
            document.querySelector('#unmapped-tab .badge').textContent = data.data.unmapped_count || '0';
            document.querySelector('#auto-match-tab .badge').textContent = data.data.auto_match_suggestions || '0';
        }
    } catch (error) {
        console.error('❌ Error loading stats:', error);
        console.error('Error details:', {
            message: error.message,
            stack: error.stack
        });
    }
}

async function loadUnmappedEmployees() {
    try {
        // Use relative path from current location - include credentials for session
        const response = await fetch('api/employee-mapping.php?action=unmapped_employees&limit=10', {
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json'
            }
        });
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        
        const data = await response.json();
        
        if (data.success && data.data.length > 0) {
            const tbody = document.querySelector('#unmapped tbody');
            tbody.innerHTML = '';
            
            data.data.forEach(emp => {
                const row = `
                    <tr>
                        <td><strong>${emp.employee_name || 'Unknown'}</strong></td>
                        <td><code>${emp.xero_employee_id || 'N/A'}</code></td>
                        <td>${emp.email || 'No email'}</td>
                        <td><span class="text-danger">$${emp.blocked_amount || '0.00'}</span></td>
                        <td>
                            <button class="btn btn-sm btn-primary" onclick="mapEmployee('${emp.xero_employee_id}')">
                                <i class="fas fa-link"></i> Map Now
                            </button>
                        </td>
                    </tr>
                `;
                tbody.innerHTML += row;
            });
        }
    } catch (error) {
        console.error('Error loading unmapped employees:', error);
    }
}

async function loadRecentActivity() {
    try {
        // Use relative path from current location - include credentials for session
        const response = await fetch('api/employee-mapping.php?action=getRecentActivity&limit=5', {
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json'
            }
        });
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        
        const data = await response.json();
        
        if (data.success && data.data.length > 0) {
            const container = document.querySelector('#dashboard .list-group');
            container.innerHTML = '';
            
            data.data.forEach(activity => {
                const item = `
                    <div class="list-group-item d-flex justify-content-between align-items-center">
                        <div>
                            <strong>${activity.employee_name}</strong> ${activity.action}
                            <small class="text-muted d-block">${activity.time_ago}</small>
                        </div>
                        <span class="badge badge-${activity.badge_class}">${activity.status}</span>
                    </div>
                `;
                container.innerHTML += item;
            });
        }
    } catch (error) {
        console.error('Error loading recent activity:', error);
        // Keep demo data if API fails
    }
}

function mapEmployee(xeroId) {
    alert('Map employee: ' + xeroId);
    // TODO: Open mapping modal
}

// Refresh button
document.getElementById('refreshDataBtn').addEventListener('click', function() {
    this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Refreshing...';
    Promise.all([loadStats(), loadUnmappedEmployees(), loadRecentActivity()]).then(() => {
        this.innerHTML = '<i class="fas fa-sync"></i> Refresh Data';
    });
});
</script>
JS;

// Include the CIS base template (with sidebar)
require_once __DIR__ . '/../shared/templates/base-layout.php';
