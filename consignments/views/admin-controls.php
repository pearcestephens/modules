<?php
/**
 * Consignments Module - Admin Controls
 *
 * @package CIS\Consignments
 * @version 5.0.0 - Bootstrap 5 + Modern Theme
 * @updated 2025-11-11 - Bootstrap 5 conversion
 */

declare(strict_types=1);

// Modern Theme Setup
$pageTitle = 'Admin Controls';
$breadcrumbs = [
    ['label' => 'Home', 'url' => '/', 'icon' => 'bi-house-door'],
    ['label' => 'Consignments', 'url' => '/modules/consignments/', 'icon' => 'bi-box-seam'],
    ['label' => 'Admin Controls', 'url' => '/modules/consignments/?route=admin-controls', 'active' => true]
];

$pageCSS = [
    'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css',
    '/modules/admin-ui/css/cms-design-system.css',
    '/modules/shared/css/tokens.css'
];

$pageJS = [];

// Start content capture
ob_start();
?>

<div class="page-header fade-in mb-4">
    <h1 class="page-title mb-2"><i class="bi bi-gear me-2"></i>Admin Controls</h1>
    <p class="page-subtitle text-muted mb-0">System configuration and management</p>
</div>

<div class="container-fluid">
        </div>
    </div>

/**
 * Employee Mapping System - Admin Controls & Settings Interface
 *
 * Comprehensive administrative interface for:
 * - System configuration and settings
 * - Bulk operations management
 * - Data management and maintenance
 * - User permissions and access control
 * - Audit trail management
 * - Advanced system diagnostics
 *
 * @package CIS\Consignments\Views
 * @version 2.0.0
 * @since 2025-01-01
 */

// Security check
if (!defined('MODULE_ROOT')) {
    http_response_code(403);
    die('Direct access forbidden');
}

// Check admin permissions
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    http_response_code(403);
    die('Admin access required');
}
?>

<div class="admin-controls-container">
    <!-- Admin Header -->
    <div class="admin-header mb-4">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h2 class="mb-1">
                    <i class="bi bi-gears text-primary me-2"></i>
                    Admin Controls & Settings
                </h2>
                <p class="text-muted mb-0">System configuration, bulk operations, and data management</p>
            </div>
            <div class="admin-actions">
                <button class="btn btn-outline-primary btn-sm" id="systemHealthCheck">
                    <i class="bi bi-heart-pulse me-1"></i> System Health Check
                </button>
                <button class="btn btn-outline-secondary btn-sm" id="exportSystemData">
                    <i class="bi bi-download me-1"></i> Export Data
                </button>
            </div>
        </div>
    </div>

    <!-- Admin Navigation Tabs -->
    <ul class="nav nav-tabs" id="adminTabs" role="tablist">
        <li class="nav-item">
            <a class="nav-link active" id="system-config-tab" data-toggle="tab" href="#system-config" role="tab">
                <i class="bi bi-sliders me-1"></i> System Configuration
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" id="bulk-operations-tab" data-toggle="tab" href="#bulk-operations" role="tab">
                <i class="bi bi-list-task me-1"></i> Bulk Operations
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" id="data-management-tab" data-toggle="tab" href="#data-management" role="tab">
                <i class="bi bi-database me-1"></i> Data Management
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" id="user-permissions-tab" data-toggle="tab" href="#user-permissions" role="tab">
                <i class="bi bi-people-cog me-1"></i> User Permissions
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" id="audit-trails-tab" data-toggle="tab" href="#audit-trails" role="tab">
                <i class="bi bi-clipboard-check me-1"></i> Audit Trails
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" id="system-diagnostics-tab" data-toggle="tab" href="#system-diagnostics" role="tab">
                <i class="bi bi-heart-pulse me-1"></i> Diagnostics
            </a>
        </li>
    </ul>

    <!-- Tab Content -->
    <div class="tab-content mt-3" id="adminTabContent">
        <!-- System Configuration Tab -->
        <div class="tab-pane fade show active" id="system-config" role="tabpanel">
            <div class="row">
                <!-- General Settings -->
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="bi bi-gear me-2"></i>General Settings
                            </h5>
                        </div>
                        <div class="card-body">
                            <form id="generalSettingsForm">
                                <div class="form-group">
                                    <label for="autoMatchThreshold">Auto-Match Confidence Threshold</label>
                                    <input type="range" class="form-control-range" id="autoMatchThreshold"
                                           min="50" max="95" value="85"
                                           oninput="document.getElementById('thresholdValue').textContent = this.value + '%'">
                                    <small class="form-text text-muted">
                                        Current: <span id="thresholdValue">85%</span> - Higher values = fewer but more accurate matches
                                    </small>
                                </div>

                                <div class="form-group">
                                    <label for="mappingTimeout">Mapping Process Timeout (seconds)</label>
                                    <select class="form-control" id="mappingTimeout">
                                        <option value="30">30 seconds</option>
                                        <option value="60" selected>60 seconds</option>
                                        <option value="120">2 minutes</option>
                                        <option value="300">5 minutes</option>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label for="maxBatchSize">Maximum Batch Processing Size</label>
                                    <input type="number" class="form-control" id="maxBatchSize"
                                           value="100" min="10" max="1000" step="10">
                                    <small class="form-text text-muted">Number of records processed in one batch</small>
                                </div>

                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="enableAutoApproval" checked>
                                    <label class="form-check-label" for="enableAutoApproval">
                                        Enable Auto-Approval for High Confidence Matches (>95%)
                                    </label>
                                </div>

                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="enableEmailNotifications" checked>
                                    <label class="form-check-label" for="enableEmailNotifications">
                                        Send Email Notifications for Mapping Completions
                                    </label>
                                </div>

                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="enableDetailedLogging">
                                    <label class="form-check-label" for="enableDetailedLogging">
                                        Enable Detailed Debug Logging (Performance Impact)
                                    </label>
                                </div>

                                <button type="submit" class="btn btn-primary mt-3">
                                    <i class="bi bi-save me-1"></i> Save Settings
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Alert Settings -->
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="bi bi-bell me-2"></i>Alert & Notification Settings
                            </h5>
                        </div>
                        <div class="card-body">
                            <form id="alertSettingsForm">
                                <div class="form-group">
                                    <label for="alertEmail">Alert Email Address</label>
                                    <input type="email" class="form-control" id="alertEmail"
                                           value="admin@vapeshed.co.nz" placeholder="admin@example.com">
                                </div>

                                <div class="form-group">
                                    <label>Alert Triggers</label>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="alertLargeAmounts" checked>
                                        <label class="form-check-label" for="alertLargeAmounts">
                                            Large Blocked Amounts (>$500)
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="alertFailedMappings" checked>
                                        <label class="form-check-label" for="alertFailedMappings">
                                            Failed Mapping Attempts
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="alertSystemErrors" checked>
                                        <label class="form-check-label" for="alertSystemErrors">
                                            System Errors & Exceptions
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="alertDailySummary">
                                        <label class="form-check-label" for="alertDailySummary">
                                            Daily Mapping Summary
                                        </label>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="alertFrequency">Alert Frequency Limit</label>
                                    <select class="form-control" id="alertFrequency">
                                        <option value="immediate">Immediate</option>
                                        <option value="hourly" selected>Hourly Digest</option>
                                        <option value="daily">Daily Digest</option>
                                    </select>
                                </div>

                                <button type="submit" class="btn btn-success mt-3">
                                    <i class="bi bi-bell me-1"></i> Update Alerts
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Bulk Operations Tab -->
        <div class="tab-pane fade" id="bulk-operations" role="tabpanel">
            <div class="row">
                <!-- Bulk Mapping Operations -->
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="bi bi-layers me-2"></i>Bulk Mapping Operations
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle me-2"></i>
                                <strong>Current Status:</strong> 56 unmapped employees, $9,543.36 blocked
                            </div>

                            <div class="operation-group mb-4">
                                <h6>Auto-Match Operations</h6>
                                <button class="btn btn-primary btn-block mb-2" id="bulkAutoMatch">
                                    <i class="bi bi-magic me-1"></i>
                                    Process All Auto-Matches (31 pending)
                                </button>
                                <button class="btn btn-outline-primary btn-block mb-2" id="bulkApproveHighConfidence">
                                    <i class="bi bi-check2-all me-1"></i>
                                    Approve High Confidence Matches Only (>90%)
                                </button>
                            </div>

                            <div class="operation-group mb-4">
                                <h6>Manual Operations</h6>
                                <button class="btn btn-warning btn-block mb-2" id="bulkResetMappings">
                                    <i class="bi bi-arrow-counterclockwise me-1"></i>
                                    Reset All Pending Mappings
                                </button>
                                <button class="btn btn-outline-warning btn-block mb-2" id="bulkFlagForReview">
                                    <i class="bi bi-flag me-1"></i>
                                    Flag Low Confidence for Manual Review
                                </button>
                            </div>

                            <div class="operation-group">
                                <h6>Data Operations</h6>
                                <button class="btn btn-info btn-block mb-2" id="refreshAllData">
                                    <i class="bi bi-arrow-clockwise me-1"></i>
                                    Refresh All Employee Data
                                </button>
                                <button class="btn btn-outline-info btn-block mb-2" id="recalculateAmounts">
                                    <i class="bi bi-calculator me-1"></i>
                                    Recalculate Blocked Amounts
                                </button>
                            </div>

                            <!-- Progress Indicator -->
                            <div class="progress-container mt-3" style="display: none;">
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="progress-label">Processing...</span>
                                    <span class="progress-percentage">0%</span>
                                </div>
                                <div class="progress">
                                    <div class="progress-bar" role="progressbar" style="width: 0%"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Bulk Data Import/Export -->
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="bi bi-arrow-left-right me-2"></i>Data Import/Export
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="import-section mb-4">
                                <h6>Import Operations</h6>
                                <div class="form-group">
                                    <label for="importFile">Upload Mapping File (CSV/Excel)</label>
                                    <div class="custom-file">
                                        <input type="file" class="custom-file-input" id="importFile"
                                               accept=".csv,.xlsx,.xls">
                                        <label class="custom-file-label" for="importFile">Choose file...</label>
                                    </div>
                                    <small class="form-text text-muted">
                                        Supported formats: CSV, Excel (.xlsx, .xls)
                                    </small>
                                </div>
                                <button class="btn btn-success btn-block" id="importMappings">
                                    <i class="bi bi-upload me-1"></i> Import Mappings
                                </button>
                            </div>

                            <div class="export-section mb-4">
                                <h6>Export Operations</h6>
                                <div class="btn-group-vertical w-100">
                                    <button class="btn btn-outline-primary mb-2" id="exportUnmapped">
                                        <i class="bi bi-download me-1"></i> Export Unmapped Employees
                                    </button>
                                    <button class="btn btn-outline-primary mb-2" id="exportMapped">
                                        <i class="bi bi-download me-1"></i> Export Mapped Employees
                                    </button>
                                    <button class="btn btn-outline-primary mb-2" id="exportAutoMatches">
                                        <i class="bi bi-download me-1"></i> Export Auto-Match Suggestions
                                    </button>
                                    <button class="btn btn-outline-primary mb-2" id="exportFullReport">
                                        <i class="bi bi-file-pdf me-1"></i> Export Full Report (PDF)
                                    </button>
                                </div>
                            </div>

                            <div class="template-section">
                                <h6>Templates</h6>
                                <button class="btn btn-outline-secondary btn-block" id="downloadTemplate">
                                    <i class="bi bi-file-earmark-arrow-down me-1"></i> Download Import Template
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Data Management Tab -->
        <div class="tab-pane fade" id="data-management" role="tabpanel">
            <div class="row">
                <!-- Database Operations -->
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="bi bi-database me-2"></i>Database Operations
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-warning">
                                <i class="bi bi-exclamation-triangle me-1"></i>
                                <strong>Warning:</strong> These operations affect live data
                            </div>

                            <div class="operation-group mb-3">
                                <h6>Maintenance</h6>
                                <button class="btn btn-outline-primary btn-sm btn-block mb-2" id="optimizeDatabase">
                                    <i class="bi bi-wrench me-1"></i> Optimize Database
                                </button>
                                <button class="btn btn-outline-primary btn-sm btn-block mb-2" id="rebuildIndexes">
                                    <i class="bi bi-list me-1"></i> Rebuild Indexes
                                </button>
                                <button class="btn btn-outline-primary btn-sm btn-block mb-2" id="cleanupOldData">
                                    <i class="bi bi-trash me-1"></i> Cleanup Old Data (>90 days)
                                </button>
                            </div>

                            <div class="operation-group mb-3">
                                <h6>Backup & Restore</h6>
                                <button class="btn btn-success btn-sm btn-block mb-2" id="createBackup">
                                    <i class="bi bi-save me-1"></i> Create Manual Backup
                                </button>
                                <button class="btn btn-info btn-sm btn-block mb-2" id="viewBackups">
                                    <i class="bi bi-clock-history me-1"></i> View Backup History
                                </button>
                            </div>

                            <div class="operation-group">
                                <h6>Data Integrity</h6>
                                <button class="btn btn-outline-warning btn-sm btn-block mb-2" id="validateDataIntegrity">
                                    <i class="bi bi-shield-check me-1"></i> Validate Data Integrity
                                </button>
                                <button class="btn btn-outline-danger btn-sm btn-block" id="repairCorruptedData">
                                    <i class="bi bi-tools me-1"></i> Repair Corrupted Data
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Cache Management -->
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="bi bi-cpu me-2"></i>Cache Management
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="cache-stats mb-3">
                                <div class="d-flex justify-content-between">
                                    <span>Cache Size:</span>
                                    <span class="badge badge-info">24.7 MB</span>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span>Hit Rate:</span>
                                    <span class="badge badge-success">94.2%</span>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span>Entries:</span>
                                    <span class="badge badge-secondary">1,247</span>
                                </div>
                            </div>

                            <div class="operation-group mb-3">
                                <h6>Cache Operations</h6>
                                <button class="btn btn-warning btn-sm btn-block mb-2" id="clearAllCache">
                                    <i class="bi bi-trash me-1"></i> Clear All Cache
                                </button>
                                <button class="btn btn-outline-warning btn-sm btn-block mb-2" id="clearMappingCache">
                                    <i class="bi bi-eraser me-1"></i> Clear Mapping Cache Only
                                </button>
                                <button class="btn btn-outline-info btn-sm btn-block mb-2" id="preloadCache">
                                    <i class="bi bi-rocket me-1"></i> Preload Frequently Used Data
                                </button>
                            </div>

                            <div class="operation-group">
                                <h6>Session Management</h6>
                                <button class="btn btn-outline-secondary btn-sm btn-block mb-2" id="viewActiveSessions">
                                    <i class="bi bi-people me-1"></i> View Active Sessions (3)
                                </button>
                                <button class="btn btn-outline-danger btn-sm btn-block" id="terminateOldSessions">
                                    <i class="bi bi-box-arrow-right me-1"></i> Terminate Old Sessions
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- System Health -->
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="bi bi-heart-pulse me-2"></i>System Health
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="health-metrics mb-3">
                                <div class="metric-item d-flex justify-content-between mb-2">
                                    <span>CPU Usage:</span>
                                    <div class="progress w-50">
                                        <div class="progress-bar bg-success" style="width: 23%">23%</div>
                                    </div>
                                </div>
                                <div class="metric-item d-flex justify-content-between mb-2">
                                    <span>Memory Usage:</span>
                                    <div class="progress w-50">
                                        <div class="progress-bar bg-warning" style="width: 67%">67%</div>
                                    </div>
                                </div>
                                <div class="metric-item d-flex justify-content-between mb-2">
                                    <span>Disk Usage:</span>
                                    <div class="progress w-50">
                                        <div class="progress-bar bg-info" style="width: 45%">45%</div>
                                    </div>
                                </div>
                                <div class="metric-item d-flex justify-content-between mb-2">
                                    <span>Database Load:</span>
                                    <div class="progress w-50">
                                        <div class="progress-bar bg-success" style="width: 12%">12%</div>
                                    </div>
                                </div>
                            </div>

                            <div class="system-info">
                                <h6>System Information</h6>
                                <small class="text-muted">
                                    <div>PHP: 8.1.27</div>
                                    <div>MySQL: 10.6.16-MariaDB</div>
                                    <div>Memory Limit: 256M</div>
                                    <div>Max Execution: 300s</div>
                                    <div>Upload Limit: 64M</div>
                                    <div>Last Restart: 2 days ago</div>
                                </small>
                            </div>

                            <button class="btn btn-primary btn-sm btn-block mt-3" id="refreshHealthMetrics">
                                <i class="bi bi-arrow-clockwise me-1"></i> Refresh Metrics
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- User Permissions Tab -->
        <div class="tab-pane fade" id="user-permissions" role="tabpanel">
            <div class="row">
                <!-- User Management -->
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">
                                <i class="bi bi-people me-2"></i>User Management
                            </h5>
                            <button class="btn btn-primary btn-sm" id="addNewUser">
                                <i class="bi bi-plus me-1"></i> Add User
                            </button>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped" id="usersTable">
                                    <thead>
                                        <tr>
                                            <th>User</th>
                                            <th>Role</th>
                                            <th>Permissions</th>
                                            <th>Last Active</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="avatar-sm bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-2">
                                                        PS
                                                    </div>
                                                    <div>
                                                        <div class="font-weight-bold">Pearce Stephens</div>
                                                        <small class="text-muted">pearce.stephens@ecigdis.co.nz</small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><span class="badge badge-danger">Super Admin</span></td>
                                            <td><span class="badge badge-success">Full Access</span></td>
                                            <td>2 minutes ago</td>
                                            <td><span class="badge badge-success">Active</span></td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-primary" title="Edit">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-info" title="View Activity">
                                                    <i class="bi bi-eye"></i>
                                                </button>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="avatar-sm bg-info text-white rounded-circle d-flex align-items-center justify-content-center me-2">
                                                        JD
                                                    </div>
                                                    <div>
                                                        <div class="font-weight-bold">John Doe</div>
                                                        <small class="text-muted">john.doe@vapeshed.co.nz</small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><span class="badge badge-warning">Manager</span></td>
                                            <td><span class="badge badge-info">Mapping + Reports</span></td>
                                            <td>1 hour ago</td>
                                            <td><span class="badge badge-success">Active</span></td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-primary" title="Edit">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-info" title="View Activity">
                                                    <i class="bi bi-eye"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-danger" title="Suspend">
                                                    <i class="bi bi-ban"></i>
                                                </button>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="avatar-sm bg-secondary text-white rounded-circle d-flex align-items-center justify-content-center me-2">
                                                        JS
                                                    </div>
                                                    <div>
                                                        <div class="font-weight-bold">Jane Smith</div>
                                                        <small class="text-muted">jane.smith@vapeshed.co.nz</small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><span class="badge badge-info">Operator</span></td>
                                            <td><span class="badge badge-secondary">Mapping Only</span></td>
                                            <td>3 days ago</td>
                                            <td><span class="badge badge-warning">Inactive</span></td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-primary" title="Edit">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-info" title="View Activity">
                                                    <i class="bi bi-eye"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-success" title="Activate">
                                                    <i class="bi bi-play"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Role Permissions -->
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="bi bi-shield-check me-2"></i>Role Permissions
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="role-section mb-3">
                                <h6 class="text-danger">Super Admin</h6>
                                <small class="text-muted">
                                    ✓ Full system access<br>
                                    ✓ User management<br>
                                    ✓ System configuration<br>
                                    ✓ Bulk operations<br>
                                    ✓ Data management<br>
                                    ✓ Audit trail access
                                </small>
                            </div>

                            <div class="role-section mb-3">
                                <h6 class="text-warning">Manager</h6>
                                <small class="text-muted">
                                    ✓ Employee mapping<br>
                                    ✓ Auto-match approval<br>
                                    ✓ Analytics dashboard<br>
                                    ✓ Export reports<br>
                                    ✗ System configuration<br>
                                    ✗ User management
                                </small>
                            </div>

                            <div class="role-section mb-3">
                                <h6 class="text-info">Operator</h6>
                                <small class="text-muted">
                                    ✓ View unmapped employees<br>
                                    ✓ Manual mapping only<br>
                                    ✓ Basic reports<br>
                                    ✗ Auto-match approval<br>
                                    ✗ Bulk operations<br>
                                    ✗ System access
                                </small>
                            </div>

                            <div class="role-section">
                                <h6 class="text-secondary">Viewer</h6>
                                <small class="text-muted">
                                    ✓ View-only access<br>
                                    ✓ Basic analytics<br>
                                    ✗ Any modifications<br>
                                    ✗ Data export<br>
                                    ✗ System configuration
                                </small>
                            </div>

                            <button class="btn btn-outline-primary btn-sm btn-block mt-3" id="manageRoles">
                                <i class="bi bi-gears me-1"></i> Manage Roles
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Audit Trails Tab -->
        <div class="tab-pane fade" id="audit-trails" role="tabpanel">
            <div class="row">
                <!-- Audit Log Viewer -->
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">
                                <i class="bi bi-clipboard-check me-2"></i>Audit Trail
                            </h5>
                            <div class="audit-controls">
                                <select class="form-control form-control-sm d-inline-block w-auto me-2" id="auditFilter">
                                    <option value="all">All Activities</option>
                                    <option value="mapping">Mapping Operations</option>
                                    <option value="admin">Admin Actions</option>
                                    <option value="system">System Events</option>
                                    <option value="errors">Errors & Warnings</option>
                                </select>
                                <button class="btn btn-outline-primary btn-sm" id="exportAuditLog">
                                    <i class="bi bi-download me-1"></i> Export
                                </button>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-striped table-sm mb-0" id="auditTable">
                                    <thead>
                                        <tr>
                                            <th>Timestamp</th>
                                            <th>User</th>
                                            <th>Action</th>
                                            <th>Details</th>
                                            <th>Result</th>
                                            <th>IP Address</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td>2025-01-01 14:23:15</td>
                                            <td>Pearce Stephens</td>
                                            <td><span class="badge badge-info">Manual Mapping</span></td>
                                            <td>Employee "Sarah Johnson" → Customer "ACME Corp"</td>
                                            <td><span class="badge badge-success">Success</span></td>
                                            <td>192.168.1.100</td>
                                        </tr>
                                        <tr>
                                            <td>2025-01-01 14:20:42</td>
                                            <td>John Doe</td>
                                            <td><span class="badge badge-primary">Auto-Match Approval</span></td>
                                            <td>Approved 15 high-confidence matches</td>
                                            <td><span class="badge badge-success">Success</span></td>
                                            <td>192.168.1.105</td>
                                        </tr>
                                        <tr>
                                            <td>2025-01-01 14:18:33</td>
                                            <td>System</td>
                                            <td><span class="badge badge-secondary">Data Refresh</span></td>
                                            <td>Employee data refreshed from Vend API</td>
                                            <td><span class="badge badge-success">Success</span></td>
                                            <td>127.0.0.1</td>
                                        </tr>
                                        <tr>
                                            <td>2025-01-01 14:15:21</td>
                                            <td>Pearce Stephens</td>
                                            <td><span class="badge badge-warning">Config Change</span></td>
                                            <td>Auto-match threshold changed: 80% → 85%</td>
                                            <td><span class="badge badge-success">Success</span></td>
                                            <td>192.168.1.100</td>
                                        </tr>
                                        <tr>
                                            <td>2025-01-01 14:12:08</td>
                                            <td>Jane Smith</td>
                                            <td><span class="badge badge-info">Manual Mapping</span></td>
                                            <td>Employee "Mike Wilson" → Customer "Tech Solutions"</td>
                                            <td><span class="badge badge-success">Success</span></td>
                                            <td>192.168.1.110</td>
                                        </tr>
                                        <tr>
                                            <td>2025-01-01 14:10:15</td>
                                            <td>System</td>
                                            <td><span class="badge badge-danger">Error</span></td>
                                            <td>Failed to connect to Vend API</td>
                                            <td><span class="badge badge-danger">Failed</span></td>
                                            <td>127.0.0.1</td>
                                        </tr>
                                        <tr>
                                            <td>2025-01-01 14:08:44</td>
                                            <td>John Doe</td>
                                            <td><span class="badge badge-primary">Bulk Operation</span></td>
                                            <td>Processed 25 auto-matches</td>
                                            <td><span class="badge badge-success">Success</span></td>
                                            <td>192.168.1.105</td>
                                        </tr>
                                        <tr>
                                            <td>2025-01-01 14:05:32</td>
                                            <td>Pearce Stephens</td>
                                            <td><span class="badge badge-success">Login</span></td>
                                            <td>Administrator login successful</td>
                                            <td><span class="badge badge-success">Success</span></td>
                                            <td>192.168.1.100</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="card-footer">
                            <nav aria-label="Audit trail pagination">
                                <ul class="pagination pagination-sm mb-0">
                                    <li class="page-item disabled">
                                        <a class="page-link" href="#" tabindex="-1">Previous</a>
                                    </li>
                                    <li class="page-item active"><a class="page-link" href="#">1</a></li>
                                    <li class="page-item"><a class="page-link" href="#">2</a></li>
                                    <li class="page-item"><a class="page-link" href="#">3</a></li>
                                    <li class="page-item">
                                        <a class="page-link" href="#">Next</a>
                                    </li>
                                </ul>
                            </nav>
                        </div>
                    </div>
                </div>

                <!-- Audit Statistics -->
                <div class="col-md-4">
                    <div class="card mb-3">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="bi bi-bar-chart me-2"></i>Audit Statistics
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="stat-item d-flex justify-content-between mb-2">
                                <span>Total Actions Today:</span>
                                <span class="badge badge-primary">247</span>
                            </div>
                            <div class="stat-item d-flex justify-content-between mb-2">
                                <span>Mapping Operations:</span>
                                <span class="badge badge-info">156</span>
                            </div>
                            <div class="stat-item d-flex justify-content-between mb-2">
                                <span>Admin Actions:</span>
                                <span class="badge badge-warning">23</span>
                            </div>
                            <div class="stat-item d-flex justify-content-between mb-2">
                                <span>System Events:</span>
                                <span class="badge badge-secondary">64</span>
                            </div>
                            <div class="stat-item d-flex justify-content-between mb-2">
                                <span>Errors:</span>
                                <span class="badge badge-danger">4</span>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="bi bi-clock me-2"></i>Recent Activity
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="timeline">
                                <div class="timeline-item">
                                    <div class="timeline-marker bg-primary"></div>
                                    <div class="timeline-content">
                                        <h6 class="timeline-title">Manual Mapping</h6>
                                        <p class="timeline-text">Pearce mapped Sarah Johnson</p>
                                        <small class="text-muted">2 minutes ago</small>
                                    </div>
                                </div>
                                <div class="timeline-item">
                                    <div class="timeline-marker bg-success"></div>
                                    <div class="timeline-content">
                                        <h6 class="timeline-title">Bulk Approval</h6>
                                        <p class="timeline-text">John approved 15 matches</p>
                                        <small class="text-muted">5 minutes ago</small>
                                    </div>
                                </div>
                                <div class="timeline-item">
                                    <div class="timeline-marker bg-info"></div>
                                    <div class="timeline-content">
                                        <h6 class="timeline-title">Data Refresh</h6>
                                        <p class="timeline-text">System updated employee data</p>
                                        <small class="text-muted">8 minutes ago</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- System Diagnostics Tab -->
        <div class="tab-pane fade" id="system-diagnostics" role="tabpanel">
            <div class="row">
                <!-- Diagnostic Tools -->
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="bi bi-heart-pulse me-2"></i>Diagnostic Tools
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="diagnostic-group mb-4">
                                <h6>Connection Tests</h6>
                                <button class="btn btn-outline-primary btn-block mb-2" id="testDatabaseConnection">
                                    <i class="bi bi-database me-1"></i> Test Database Connection
                                </button>
                                <button class="btn btn-outline-primary btn-block mb-2" id="testVendAPI">
                                    <i class="bi bi-plug me-1"></i> Test Vend API Connection
                                </button>
                                <button class="btn btn-outline-primary btn-block mb-2" id="testEmailService">
                                    <i class="bi bi-envelope me-1"></i> Test Email Service
                                </button>
                            </div>

                            <div class="diagnostic-group mb-4">
                                <h6>Performance Tests</h6>
                                <button class="btn btn-outline-info btn-block mb-2" id="testQueryPerformance">
                                    <i class="bi bi-speedometer2 me-1"></i> Test Query Performance
                                </button>
                                <button class="btn btn-outline-info btn-block mb-2" id="testMemoryUsage">
                                    <i class="bi bi-cpu me-1"></i> Test Memory Usage
                                </button>
                                <button class="btn btn-outline-info btn-block mb-2" id="testLoadCapacity">
                                    <i class="bi bi-app me-1"></i> Test Load Capacity
                                </button>
                            </div>

                            <div class="diagnostic-group">
                                <h6>System Validation</h6>
                                <button class="btn btn-outline-success btn-block mb-2" id="validateSystemIntegrity">
                                    <i class="bi bi-check-circle me-1"></i> Validate System Integrity
                                </button>
                                <button class="btn btn-outline-success btn-block mb-2" id="validateDataConsistency">
                                    <i class="bi bi-scales me-1"></i> Validate Data Consistency
                                </button>
                                <button class="btn btn-outline-warning btn-block" id="runFullDiagnostic">
                                    <i class="bi bi-play-circle me-1"></i> Run Full Diagnostic Suite
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Diagnostic Results -->
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="bi bi-clipboard-check me-2"></i>Diagnostic Results
                            </h5>
                        </div>
                        <div class="card-body">
                            <div id="diagnosticResults">
                                <div class="text-center text-muted py-5">
                                    <i class="bi bi-cup fa-3x mb-3"></i>
                                    <p>Run diagnostic tests to see results here</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- System Logs -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">
                                <i class="bi bi-file-text me-2"></i>System Logs
                            </h5>
                            <div class="log-controls">
                                <select class="form-control form-control-sm d-inline-block w-auto me-2" id="logLevel">
                                    <option value="all">All Levels</option>
                                    <option value="error">Errors Only</option>
                                    <option value="warning">Warnings & Errors</option>
                                    <option value="info">Info & Above</option>
                                </select>
                                <button class="btn btn-outline-primary btn-sm" id="refreshLogs">
                                    <i class="bi bi-arrow-clockwise me-1"></i> Refresh
                                </button>
                                <button class="btn btn-outline-secondary btn-sm" id="downloadLogs">
                                    <i class="bi bi-download me-1"></i> Download
                                </button>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <div class="log-viewer" style="height: 400px; overflow-y: auto; background: #1e1e1e; color: #f8f8f2; font-family: 'Courier New', monospace; font-size: 12px; padding: 15px;">
                                <div class="log-line">[2025-01-01 14:23:15] INFO: Employee mapping service started</div>
                                <div class="log-line">[2025-01-01 14:23:16] INFO: Database connection established</div>
                                <div class="log-line">[2025-01-01 14:23:17] INFO: Cache warmed up successfully</div>
                                <div class="log-line">[2025-01-01 14:23:18] DEBUG: Loading unmapped employees...</div>
                                <div class="log-line">[2025-01-01 14:23:19] INFO: Found 56 unmapped employees</div>
                                <div class="log-line">[2025-01-01 14:23:20] DEBUG: Calculating auto-match suggestions...</div>
                                <div class="log-line">[2025-01-01 14:23:22] INFO: Generated 31 auto-match suggestions</div>
                                <div class="log-line">[2025-01-01 14:23:23] INFO: Total blocked amount: $9,543.36</div>
                                <div class="log-line">[2025-01-01 14:23:24] DEBUG: Analytics data refreshed</div>
                                <div class="log-line">[2025-01-01 14:23:25] INFO: System ready for operations</div>
                                <div class="log-line">[2025-01-01 14:24:12] INFO: User 'Pearce Stephens' logged in</div>
                                <div class="log-line">[2025-01-01 14:24:33] DEBUG: Loading employee mapping interface</div>
                                <div class="log-line">[2025-01-01 14:25:45] INFO: Manual mapping: Employee 'Sarah Johnson' → Customer 'ACME Corp'</div>
                                <div class="log-line">[2025-01-01 14:26:12] WARNING: Auto-match confidence below threshold for employee 'Mike Davis'</div>
                                <div class="log-line">[2025-01-01 14:26:33] ERROR: Failed to connect to Vend API - timeout after 30 seconds</div>
                                <div class="log-line">[2025-01-01 14:26:34] INFO: Retrying Vend API connection...</div>
                                <div class="log-line">[2025-01-01 14:26:37] INFO: Vend API connection restored</div>
                                <div class="log-line">[2025-01-01 14:27:15] INFO: Bulk operation: Approved 15 high-confidence matches</div>
                                <div class="log-line">[2025-01-01 14:27:45] DEBUG: Cache hit rate: 94.2%</div>
                                <div class="log-line">[2025-01-01 14:28:00] INFO: Analytics dashboard accessed by 'John Doe'</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modals -->
<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New User</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="addUserForm">
                    <div class="form-group">
                        <label for="newUserName">Full Name</label>
                        <input type="text" class="form-control" id="newUserName" required>
                    </div>
                    <div class="form-group">
                        <label for="newUserEmail">Email Address</label>
                        <input type="email" class="form-control" id="newUserEmail" required>
                    </div>
                    <div class="form-group">
                        <label for="newUserRole">Role</label>
                        <select class="form-control" id="newUserRole" required>
                            <option value="">Select Role</option>
                            <option value="operator">Operator</option>
                            <option value="manager">Manager</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="newUserPassword">Temporary Password</label>
                        <input type="password" class="form-control" id="newUserPassword" required>
                        <small class="form-text text-muted">User will be required to change on first login</small>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="sendWelcomeEmail" checked>
                        <label class="form-check-label" for="sendWelcomeEmail">
                            Send welcome email with login instructions
                        </label>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="saveNewUser">Create User</button>
            </div>
        </div>
    </div>
</div>

<!-- Confirmation Modal -->
<div class="modal fade" id="confirmationModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirm Action</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p id="confirmationMessage">Are you sure you want to perform this action?</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmAction">Confirm</button>
            </div>
        </div>
    </div>
</div>

<style>
/* Custom styles for admin controls */
.admin-controls-container {
    max-width: 100%;
    margin: 0 auto;
}

.admin-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 20px;
    border-radius: 10px;
    margin-bottom: 20px;
}

.admin-header h2 {
    color: white;
    margin-bottom: 5px;
}

.admin-actions .btn {
    margin-left: 10px;
}

.avatar-sm {
    width: 32px;
    height: 32px;
    font-size: 12px;
    font-weight: bold;
}

.operation-group h6 {
    font-weight: 600;
    color: #495057;
    margin-bottom: 10px;
    padding-bottom: 5px;
    border-bottom: 1px solid #e9ecef;
}

.progress-container {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 5px;
    border: 1px solid #dee2e6;
}

.cache-stats .d-flex {
    margin-bottom: 8px;
}

.health-metrics .metric-item {
    align-items: center;
}

.health-metrics .progress {
    height: 20px;
}

.system-info {
    background: #f8f9fa;
    padding: 10px;
    border-radius: 5px;
    margin-top: 15px;
}

.timeline {
    position: relative;
    padding-left: 30px;
}

.timeline-item {
    position: relative;
    margin-bottom: 20px;
}

.timeline-marker {
    position: absolute;
    left: -38px;
    top: 5px;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    border: 2px solid white;
}

.timeline-content {
    position: relative;
}

.timeline-title {
    font-size: 14px;
    font-weight: 600;
    margin-bottom: 5px;
}

.timeline-text {
    font-size: 13px;
    margin-bottom: 5px;
    color: #6c757d;
}

.log-viewer {
    border: 1px solid #dee2e6;
    border-radius: 5px;
}

.log-line {
    padding: 2px 0;
    border-bottom: 1px solid rgba(255,255,255,0.1);
}

.log-line:hover {
    background: rgba(255,255,255,0.1);
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .admin-header .d-flex {
        flex-direction: column;
        text-align: center;
    }

    .admin-actions {
        margin-top: 15px;
    }

    .admin-actions .btn {
        margin: 5px;
    }
}

/* Animation for progress bars */
@keyframes progressAnimation {
    0% { width: 0%; }
}

.progress-bar {
    animation: progressAnimation 1s ease-out;
}

/* Custom tab styling */
.nav-tabs .nav-link {
    border: none;
    border-bottom: 3px solid transparent;
    background: none;
    color: #495057;
    font-weight: 500;
}

.nav-tabs .nav-link:hover {
    border-color: transparent;
    border-bottom-color: #dee2e6;
    background: #f8f9fa;
}

.nav-tabs .nav-link.active {
    background: none;
    border-color: transparent;
    border-bottom-color: #007bff;
    color: #007bff;
    font-weight: 600;
}

/* Status badges */
.badge {
    font-size: 11px;
    padding: 4px 8px;
}

/* Button enhancements */
.btn-sm {
    font-size: 12px;
    padding: 4px 8px;
}

.btn-block {
    display: block;
    width: 100%;
}

/* Card enhancements */
.card {
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    border: 1px solid #e3e6f0;
}

.card-header {
    background: #f8f9fc;
    border-bottom: 1px solid #e3e6f0;
    font-weight: 600;
}

/* Table enhancements */
.table th {
    background: #f8f9fc;
    border-top: none;
    font-weight: 600;
    font-size: 13px;
    color: #5a5c69;
}

.table td {
    font-size: 13px;
    vertical-align: middle;
}

.table-striped tbody tr:nth-of-type(odd) {
    background-color: rgba(0,0,0,.02);
}
</style>
</div>

<?php
// Capture content
$content = ob_get_clean();

// Load the Modern Theme (Bootstrap 5)
require_once __DIR__ . '/../../base/templates/themes/modern/layouts/dashboard.php';
?>
