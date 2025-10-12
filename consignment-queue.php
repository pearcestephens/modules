<?php
/**
 * Consignment Queue Handler Web Interface
 * 
 * Comprehensive web interface for managing the consignment queue system,
 * workers, and monitoring all queue operations in real-time.
 * 
 * @package CIS Dashboard
 * @author CIS Development Team
 * @version 2.0
 */

declare(strict_types=1);

// Load configuration and base class
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/DashboardBase.php';

// Initialize dashboard
$dashboard = new DashboardBase();
$dashboard->setModule('consignment-queue');

// Set variables for template
$pageTitle = 'Consignment Queue Manager';
$currentModule = 'consignment-queue';
$breadcrumbs = $dashboard->getNavigation()->getBreadcrumbs();

// Include header and sidebar
include __DIR__ . '/../templates/header.php';
include __DIR__ . '/../templates/sidebar-new.php';
?>

<!-- Main Content -->
<div class="main-content">
    <div class="container-fluid">
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">
                    <i class="fas fa-shipping-fast text-success me-2"></i>
                    Consignment Queue Manager
                </h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group me-2">
                        <button type="button" class="btn btn-sm btn-success" onclick="startWorkers()">
                            <i class="fas fa-play me-1"></i>Start Workers
                        </button>
                        <button type="button" class="btn btn-sm btn-warning" onclick="stopWorkers()">
                            <i class="fas fa-stop me-1"></i>Stop Workers
                        </button>
                        <button type="button" class="btn btn-sm btn-info" onclick="restartWorkers()">
                            <i class="fas fa-sync me-1"></i>Restart
                        </button>
                        <button type="button" class="btn btn-sm btn-primary" onclick="refreshDashboard()">
                            <i class="fas fa-refresh me-1"></i>Refresh
                        </button>
                    </div>
                    <div class="btn-group">
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="showQueueMonitor()">
                            <i class="fas fa-chart-line me-1"></i>Monitor
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="showWorkerLogs()">
                            <i class="fas fa-file-alt me-1"></i>Logs
                        </button>
                    </div>
                </div>
            </div>

            <!-- Real-time Status Cards -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card bg-primary text-white">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="flex-grow-1">
                                    <h6 class="card-title mb-0">Active Workers</h6>
                                    <h3 class="mb-0" id="stats-workers">
                                        <i class="fas fa-spinner fa-spin"></i>
                                    </h3>
                                </div>
                                <div class="text-white-50">
                                    <i class="fas fa-cogs fa-2x"></i>
                                </div>
                            </div>
                            <small class="text-white-75" id="worker-status-text">Checking status...</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-info text-white">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="flex-grow-1">
                                    <h6 class="card-title mb-0">Queue Pending</h6>
                                    <h3 class="mb-0" id="stats-pending">-</h3>
                                </div>
                                <div class="text-white-50">
                                    <i class="fas fa-clock fa-2x"></i>
                                </div>
                            </div>
                            <small class="text-white-75" id="pending-oldest">-</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-success text-white">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="flex-grow-1">
                                    <h6 class="card-title mb-0">Processed Today</h6>
                                    <h3 class="mb-0" id="stats-processed">-</h3>
                                </div>
                                <div class="text-white-50">
                                    <i class="fas fa-check-circle fa-2x"></i>
                                </div>
                            </div>
                            <small class="text-white-75" id="processing-rate">-</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-danger text-white">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="flex-grow-1">
                                    <h6 class="card-title mb-0">Failed Items</h6>
                                    <h3 class="mb-0" id="stats-failed">-</h3>
                                </div>
                                <div class="text-white-50">
                                    <i class="fas fa-exclamation-triangle fa-2x"></i>
                                </div>
                            </div>
                            <small class="text-white-75" id="failed-rate">-</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Queue Tabs Navigation -->
            <nav class="mb-4">
                <div class="nav nav-tabs" id="nav-tab" role="tablist">
                    <button class="nav-link active" id="nav-consignments-tab" data-bs-toggle="tab" data-bs-target="#nav-consignments" 
                            type="button" role="tab">
                        <i class="fas fa-boxes me-1"></i>Consignments
                    </button>
                    <button class="nav-link" id="nav-actions-tab" data-bs-toggle="tab" data-bs-target="#nav-actions" 
                            type="button" role="tab">
                        <i class="fas fa-tasks me-1"></i>Actions Queue
                    </button>
                    <button class="nav-link" id="nav-workers-tab" data-bs-toggle="tab" data-bs-target="#nav-workers" 
                            type="button" role="tab">
                        <i class="fas fa-server me-1"></i>Workers
                    </button>
                    <button class="nav-link" id="nav-monitor-tab" data-bs-toggle="tab" data-bs-target="#nav-monitor" 
                            type="button" role="tab">
                        <i class="fas fa-chart-area me-1"></i>Monitor
                    </button>
                    <button class="nav-link" id="nav-logs-tab" data-bs-toggle="tab" data-bs-target="#nav-logs" 
                            type="button" role="tab">
                        <i class="fas fa-file-alt me-1"></i>Logs
                    </button>
                </div>
            </nav>

            <div class="tab-content" id="nav-tabContent">
                <!-- Consignments Tab -->
                <div class="tab-pane fade show active" id="nav-consignments" role="tabpanel">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">
                                <i class="fas fa-boxes me-2"></i>Active Consignments
                            </h5>
                            <div class="btn-group btn-group-sm">
                                <button type="button" class="btn btn-outline-primary active" onclick="filterConsignments('all')">All</button>
                                <button type="button" class="btn btn-outline-info" onclick="filterConsignments('OPEN')">Open</button>
                                <button type="button" class="btn btn-outline-warning" onclick="filterConsignments('SENT')">Sent</button>
                                <button type="button" class="btn btn-outline-success" onclick="filterConsignments('RECEIVED')">Received</button>
                                <button type="button" class="btn btn-outline-danger" onclick="filterConsignments('FAILED')">Failed</button>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover table-sm" id="consignments-table">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>ID</th>
                                            <th>Type</th>
                                            <th>Reference</th>
                                            <th>Status</th>
                                            <th>Source → Destination</th>
                                            <th>Products</th>
                                            <th>Created</th>
                                            <th>Last Update</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="consignments-list">
                                        <tr>
                                            <td colspan="9" class="text-center text-muted py-4">
                                                <i class="fas fa-spinner fa-spin me-2"></i>Loading consignments...
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Actions Queue Tab -->
                <div class="tab-pane fade" id="nav-actions" role="tabpanel">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">
                                <i class="fas fa-tasks me-2"></i>Action Queue
                            </h5>
                            <div class="btn-group btn-group-sm">
                                <button type="button" class="btn btn-outline-primary active" onclick="filterActions('all')">All</button>
                                <button type="button" class="btn btn-outline-warning" onclick="filterActions('pending')">Pending</button>
                                <button type="button" class="btn btn-outline-info" onclick="filterActions('executing')">Executing</button>
                                <button type="button" class="btn btn-outline-success" onclick="filterActions('completed')">Completed</button>
                                <button type="button" class="btn btn-outline-danger" onclick="filterActions('failed')">Failed</button>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover table-sm" id="actions-table">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>Action ID</th>
                                            <th>Consignment</th>
                                            <th>Action Type</th>
                                            <th>Status</th>
                                            <th>Retries</th>
                                            <th>User</th>
                                            <th>Created</th>
                                            <th>Duration</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="actions-list">
                                        <tr>
                                            <td colspan="9" class="text-center text-muted py-4">
                                                <i class="fas fa-spinner fa-spin me-2"></i>Loading actions...
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Workers Tab -->
                <div class="tab-pane fade" id="nav-workers" role="tabpanel">
                    <div class="row">
                        <div class="col-md-8">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0">
                                        <i class="fas fa-server me-2"></i>Worker Processes
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-hover" id="workers-table">
                                            <thead class="table-dark">
                                                <tr>
                                                    <th>Worker ID</th>
                                                    <th>PID</th>
                                                    <th>Status</th>
                                                    <th>CPU %</th>
                                                    <th>Memory</th>
                                                    <th>Runtime</th>
                                                    <th>Processed</th>
                                                    <th>Last Activity</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody id="workers-list">
                                                <tr>
                                                    <td colspan="9" class="text-center text-muted py-4">
                                                        <i class="fas fa-spinner fa-spin me-2"></i>Loading workers...
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card">
                                <div class="card-header">
                                    <h6 class="mb-0">Worker Controls</h6>
                                </div>
                                <div class="card-body">
                                    <form id="worker-config-form">
                                        <div class="mb-3">
                                            <label for="worker-count" class="form-label">Number of Workers</label>
                                            <select class="form-select" id="worker-count" name="worker_count">
                                                <option value="1">1 Worker</option>
                                                <option value="2">2 Workers</option>
                                                <option value="3" selected>3 Workers</option>
                                                <option value="4">4 Workers</option>
                                                <option value="5">5 Workers</option>
                                            </select>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="worker-timeout" class="form-label">Worker Timeout (seconds)</label>
                                            <select class="form-select" id="worker-timeout" name="timeout">
                                                <option value="300" selected>5 minutes</option>
                                                <option value="600">10 minutes</option>
                                                <option value="1800">30 minutes</option>
                                                <option value="3600">1 hour</option>
                                            </select>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="daemon-mode" name="daemon_mode">
                                                <label class="form-check-label" for="daemon-mode">
                                                    Daemon Mode (auto-restart)
                                                </label>
                                            </div>
                                        </div>
                                        
                                        <div class="d-grid gap-2">
                                            <button type="button" class="btn btn-success" onclick="applyWorkerConfig()">
                                                <i class="fas fa-save me-1"></i>Apply & Restart
                                            </button>
                                            <button type="button" class="btn btn-outline-warning" onclick="killAllWorkers()">
                                                <i class="fas fa-skull me-1"></i>Kill All Workers
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                            
                            <div class="card mt-3">
                                <div class="card-header">
                                    <h6 class="mb-0">System Resources</h6>
                                </div>
                                <div class="card-body">
                                    <div class="mb-2">
                                        <small class="text-muted">CPU Usage</small>
                                        <div class="progress" style="height: 8px;">
                                            <div class="progress-bar" id="cpu-usage" role="progressbar" style="width: 0%"></div>
                                        </div>
                                        <small class="text-muted" id="cpu-text">0%</small>
                                    </div>
                                    
                                    <div class="mb-2">
                                        <small class="text-muted">Memory Usage</small>
                                        <div class="progress" style="height: 8px;">
                                            <div class="progress-bar bg-info" id="memory-usage" role="progressbar" style="width: 0%"></div>
                                        </div>
                                        <small class="text-muted" id="memory-text">0 MB</small>
                                    </div>
                                    
                                    <div class="mb-2">
                                        <small class="text-muted">Load Average</small>
                                        <div class="progress" style="height: 8px;">
                                            <div class="progress-bar bg-warning" id="load-usage" role="progressbar" style="width: 0%"></div>
                                        </div>
                                        <small class="text-muted" id="load-text">0.00</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Monitor Tab -->
                <div class="tab-pane fade" id="nav-monitor" role="tabpanel">
                    <div class="row mb-4">
                        <div class="col-md-12">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0">
                                        <i class="fas fa-chart-line me-2"></i>Queue Performance
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <canvas id="performance-chart" height="100"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h6 class="mb-0">Action Types Distribution</h6>
                                </div>
                                <div class="card-body">
                                    <canvas id="actions-pie-chart"></canvas>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h6 class="mb-0">Error Analysis</h6>
                                </div>
                                <div class="card-body">
                                    <canvas id="errors-chart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Logs Tab -->
                <div class="tab-pane fade" id="nav-logs" role="tabpanel">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">
                                <i class="fas fa-file-alt me-2"></i>Worker Logs
                            </h5>
                            <div class="btn-group btn-group-sm">
                                <button type="button" class="btn btn-outline-primary" onclick="refreshLogs()">
                                    <i class="fas fa-sync me-1"></i>Refresh
                                </button>
                                <button type="button" class="btn btn-outline-success" onclick="downloadLogs()">
                                    <i class="fas fa-download me-1"></i>Download
                                </button>
                                <button type="button" class="btn btn-outline-warning" onclick="clearLogs()">
                                    <i class="fas fa-trash me-1"></i>Clear
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <select class="form-select form-select-sm" id="log-worker-filter">
                                        <option value="all">All Workers</option>
                                        <option value="1">Worker 1</option>
                                        <option value="2">Worker 2</option>
                                        <option value="3">Worker 3</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <select class="form-select form-select-sm" id="log-level-filter">
                                        <option value="all">All Levels</option>
                                        <option value="info">Info</option>
                                        <option value="warning">Warning</option>
                                        <option value="error">Error</option>
                                        <option value="critical">Critical</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="auto-scroll" checked>
                                        <label class="form-check-label" for="auto-scroll">
                                            Auto-scroll
                                        </label>
                                    </div>
                                </div>
                            </div>
                            
                            <div id="log-container" style="height: 500px; overflow-y: auto; background: #1e1e1e; color: #fff; font-family: monospace; padding: 15px; border-radius: 5px;">
                                <div class="text-center text-muted">
                                    <i class="fas fa-spinner fa-spin me-2"></i>Loading logs...
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </main>
    </div>
</div>

<!-- Consignment Details Modal -->
<div class="modal fade" id="consignmentModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-box me-2"></i>Consignment Details
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="consignment-details-content">
                <!-- Consignment details will be loaded here -->
            </div>
        </div>
    </div>
</div>

<!-- Action Details Modal -->
<div class="modal fade" id="actionModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-task me-2"></i>Action Details
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="action-details-content">
                <!-- Action details will be loaded here -->
            </div>
        </div>
    </div>
</div>

<!-- Toast Notifications -->
<div class="toast-container position-fixed bottom-0 end-0 p-3">
    <div id="notification-toast" class="toast" role="alert">
        <div class="toast-header">
            <i class="fas fa-info-circle text-primary me-2"></i>
            <strong class="me-auto">Queue Manager</strong>
            <button type="button" class="btn-close" data-bs-dismiss="toast"></button>
        </div>
        <div class="toast-body">
            <!-- Notification message -->
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="<?= $baseUrl ?>/assets/js/consignment-queue.js"></script>

<?php include __DIR__ . '/../templates/footer-new.php'; ?>