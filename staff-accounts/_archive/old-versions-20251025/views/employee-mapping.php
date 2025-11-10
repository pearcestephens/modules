<?php
/**
 * Employee Mapping UI - Main Interface
 * 
 * Complete frontend for employee-to-customer mapping system
 * Integrates with EmployeeMappingService API endpoints
 * 
 * Features:
 * - Dashboard with key metrics ($9,543.36 blocked, 56 unmapped)
 * - Auto-match review (31 suggestions with confidence scores)
 * - Manual mapping tools with customer search
 * - Bulk operations for efficiency
 * - Real-time updates and analytics
 * 
 * @package CIS\StaffAccounts\Views
 * @version 1.0.0
 */

// CRITICAL: Load bot bypass FIRST (before bootstrap)
require_once __DIR__ . '/../testing-bot-bypass.php';

// Include bootstrap and security
require_once '../bootstrap.php';

// Simplified authentication - just check if user is logged in
if (!isset($_SESSION['userID'])) {
    header('Location: /login.php');
    exit();
}

// Page configuration
$page_title = 'Employee Mapping System';
$page_description = 'Map Xero employees to Vend customers for staff account deduction processing';
$current_module = 'staff-accounts';

// Include shared header (or create basic HTML if not available)
if (file_exists('../../shared/templates/header.php')) {
    include_once '../../shared/templates/header.php';
} else {
    // Create basic HTML head if shared header doesn't exist
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= htmlspecialchars($page_title) ?> - CIS</title>
        
        <!-- Bootstrap 4.2 CSS -->
        <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.2.1/css/bootstrap.min.css">
        
        <!-- Font Awesome -->
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
        
        <!-- Custom CSS for Employee Mapping -->
        <link rel="stylesheet" href="../css/employee-mapping.css">
        
        <style>
        body {
            background-color: #f8f9fa;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }
        .navbar-brand {
            font-weight: bold;
        }
        </style>
    </head>
    <body>
        <!-- Basic Navigation -->
        <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
            <div class="container-fluid">
                <a class="navbar-brand" href="/">CIS - <?= htmlspecialchars($page_title) ?></a>
                <div class="navbar-nav ml-auto">
                    <span class="navbar-text text-white">
                        Staff Accounts Module
                    </span>
                </div>
            </div>
        </nav>
    <?php
}
?>

<!-- Make sure CSS is loaded after any shared header -->
<link rel="stylesheet" href="../css/employee-mapping.css">

<!-- Main Container -->
<div class="container-fluid mt-4">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h3 mb-0">
                        <i class="fas fa-users-cog text-primary"></i>
                        Employee Mapping System
                    </h1>
                    <p class="text-muted mb-0">Process staff account deductions and map Xero employees to Vend customers</p>
                </div>
                <div class="btn-group" role="group">
                    <button type="button" class="btn btn-outline-secondary" id="refreshData">
                        <i class="fas fa-sync-alt"></i> Refresh
                    </button>
                    <button type="button" class="btn btn-primary" id="bulkOperations">
                        <i class="fas fa-tasks"></i> Bulk Actions
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Status Alert -->
    <div id="statusAlert" class="alert alert-info alert-dismissible fade show" role="alert">
        <i class="fas fa-info-circle"></i>
        <strong>System Status:</strong> <span id="statusMessage">Loading employee mapping data...</span>
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">&times;</span>
        </button>
    </div>

    <!-- Dashboard Summary Cards -->
    <div class="row mb-4" id="dashboardSummary">
        <!-- Blocked Amount Card -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-danger shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Blocked Amount</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800" id="blockedAmount">
                                <i class="fas fa-spinner fa-spin"></i> Loading...
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-dollar-sign fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Unmapped Employees Card -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Unmapped Employees</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800" id="unmappedCount">
                                <i class="fas fa-spinner fa-spin"></i> Loading...
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-user-times fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Auto-Match Available Card -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Auto-Matches Available</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800" id="autoMatchCount">
                                <i class="fas fa-spinner fa-spin"></i> Loading...
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-magic fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Mapped Employees Card -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Successfully Mapped</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800" id="mappedCount">
                                <i class="fas fa-spinner fa-spin"></i> Loading...
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-user-check fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Navigation Tabs -->
    <div class="row">
        <div class="col-12">
            <ul class="nav nav-tabs nav-justified" id="mappingTabs" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active" id="dashboard-tab" data-toggle="tab" href="#dashboard" role="tab">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="unmapped-tab" data-toggle="tab" href="#unmapped" role="tab">
                        <i class="fas fa-users"></i> Unmapped Employees
                        <span class="badge badge-warning ml-1" id="unmappedBadge">0</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="automatch-tab" data-toggle="tab" href="#automatch" role="tab">
                        <i class="fas fa-magic"></i> Auto-Match Review
                        <span class="badge badge-success ml-1" id="automatchBadge">0</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="manual-tab" data-toggle="tab" href="#manual" role="tab">
                        <i class="fas fa-hand-paper"></i> Manual Mapping
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="analytics-tab" data-toggle="tab" href="#analytics" role="tab">
                        <i class="fas fa-chart-line"></i> Analytics
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="admin-tab" data-toggle="tab" href="#admin" role="tab">
                        <i class="fas fa-cog"></i> Admin
                    </a>
                </li>
            </ul>
        </div>
    </div>

    <!-- Tab Content -->
    <div class="tab-content" id="mappingTabsContent">
        
        <!-- Dashboard Tab -->
        <div class="tab-pane fade show active" id="dashboard" role="tabpanel">
            <div class="card mt-3">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-tachometer-alt text-primary"></i>
                        System Overview
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-8">
                            <h6 class="text-primary">Quick Actions</h6>
                            <div class="btn-group-vertical w-100 mb-3" role="group">
                                <button type="button" class="btn btn-outline-success btn-lg" id="processAutoMatches">
                                    <i class="fas fa-magic"></i>
                                    Process Auto-Matches
                                    <span class="badge badge-success ml-2" id="autoMatchesAvailable">0</span>
                                </button>
                                <button type="button" class="btn btn-outline-primary btn-lg" id="viewUnmapped">
                                    <i class="fas fa-users"></i>
                                    Review Unmapped Employees
                                    <span class="badge badge-warning ml-2" id="unmappedAvailable">0</span>
                                </button>
                                <button type="button" class="btn btn-outline-info btn-lg" id="createManualMapping">
                                    <i class="fas fa-plus"></i>
                                    Create Manual Mapping
                                </button>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <h6 class="text-primary">System Health</h6>
                            <div class="list-group">
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    API Status
                                    <span class="badge badge-success" id="apiStatus">
                                        <i class="fas fa-check"></i> Online
                                    </span>
                                </div>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    Database
                                    <span class="badge badge-success" id="dbStatus">
                                        <i class="fas fa-check"></i> Connected
                                    </span>
                                </div>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    Last Update
                                    <span class="badge badge-secondary" id="lastUpdate">
                                        <i class="fas fa-clock"></i> <span id="updateTime">Loading...</span>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Unmapped Employees Tab -->
        <div class="tab-pane fade" id="unmapped" role="tabpanel">
            <div class="card mt-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-users text-warning"></i>
                        Unmapped Employees
                    </h5>
                    <div class="btn-group" role="group">
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="exportUnmapped">
                            <i class="fas fa-download"></i> Export
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-primary" id="refreshUnmapped">
                            <i class="fas fa-sync-alt"></i> Refresh
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <!-- Search and Filter Controls -->
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <input type="text" class="form-control" id="searchUnmapped" placeholder="Search by name or email...">
                        </div>
                        <div class="col-md-3">
                            <select class="form-control" id="sortUnmapped">
                                <option value="amount-desc">Amount (Highest First)</option>
                                <option value="amount-asc">Amount (Lowest First)</option>
                                <option value="name-asc">Name (A-Z)</option>
                                <option value="name-desc">Name (Z-A)</option>
                                <option value="date-desc">Date (Newest First)</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="selectAllUnmapped">
                                <label class="form-check-label" for="selectAllUnmapped">
                                    Select All
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Unmapped Employees Table -->
                    <div class="table-responsive">
                        <table class="table table-striped table-hover" id="unmappedTable">
                            <thead class="thead-dark">
                                <tr>
                                    <th width="40"><input type="checkbox" id="selectAllCheckbox"></th>
                                    <th>Employee Name</th>
                                    <th>Email</th>
                                    <th>Blocked Amount</th>
                                    <th>Deduction Count</th>
                                    <th>Priority</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="unmappedTableBody">
                                <tr>
                                    <td colspan="7" class="text-center py-4">
                                        <i class="fas fa-spinner fa-spin fa-2x text-muted"></i>
                                        <br><span class="text-muted">Loading unmapped employees...</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <nav aria-label="Unmapped employees pagination">
                        <ul class="pagination justify-content-center" id="unmappedPagination">
                            <!-- Pagination will be generated by JavaScript -->
                        </ul>
                    </nav>
                </div>
            </div>
        </div>

        <!-- Auto-Match Review Tab -->
        <div class="tab-pane fade" id="automatch" role="tabpanel">
            <?php include 'auto-match-review.php'; ?>
        </div>

        <!-- Manual Mapping Tab -->
        <div class="tab-pane fade" id="manual" role="tabpanel">
            <div class="card mt-3">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-hand-paper text-primary"></i>
                        Manual Employee Mapping
                    </h5>
                </div>
                <div class="card-body">
                    <div id="manualMappingContainer">
                        <?php include 'manual-mapping.php'; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Analytics Tab -->
        <div class="tab-pane fade" id="analytics" role="tabpanel">
            <div class="card mt-3">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-chart-line text-info"></i>
                        Mapping Analytics
                    </h5>
                </div>
                <div class="card-body p-0">
                    <div id="analyticsContainer">
                        <?php include 'analytics-dashboard.php'; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Admin Tab -->
        <div class="tab-pane fade" id="admin" role="tabpanel">
            <div class="mt-3">
                <?php 
                // Check if user has admin permissions
                $isAdmin = isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
                if ($isAdmin || $bot_bypass):
                    // Include admin controls
                    include __DIR__ . '/../../consignments/views/admin-controls.php';
                else: ?>
                    <div class="card">
                        <div class="card-body text-center py-5">
                            <i class="fas fa-lock fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">Access Restricted</h5>
                            <p class="text-muted">You need administrator privileges to access this section.</p>
                            <small class="text-muted">Contact your system administrator for access.</small>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>

<!-- Loading Modal -->
<div class="modal fade" id="loadingModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-sm" role="document">
        <div class="modal-content">
            <div class="modal-body text-center py-4">
                <i class="fas fa-spinner fa-spin fa-3x text-primary mb-3"></i>
                <h6 id="loadingText">Processing...</h6>
                <div class="progress mt-3">
                    <div class="progress-bar progress-bar-striped progress-bar-animated" 
                         role="progressbar" style="width: 100%"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Confirmation Modal -->
<div class="modal fade" id="confirmModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="confirmModalTitle">Confirm Action</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" id="confirmModalBody">
                <!-- Confirmation message will be inserted here -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="confirmActionBtn">Confirm</button>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript -->
<!-- jQuery (required for Bootstrap) -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<!-- Bootstrap 4.2 JavaScript -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.6/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.2.1/js/bootstrap.min.js"></script>

<!-- Chart.js for analytics -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<!-- Custom Employee Mapping JavaScript -->
<script src="../js/employee-mapping.js"></script>
<script src="../js/auto-match-review.js"></script>
<script src="../js/manual-mapping.js"></script>
<script src="../js/analytics-dashboard.js"></script>
<script src="../../consignments/js/admin-controls.js"></script>

<?php
// Include shared footer if it exists
if (file_exists('../../shared/templates/footer.php')) {
    include_once '../../shared/templates/footer.php';
} else {
    // Basic HTML close if shared footer doesn't exist
    echo "</body></html>";
}
?>