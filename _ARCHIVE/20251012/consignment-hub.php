<?php
/**
 * Consignment Hub - Transfer and Consignment Management System
 * 
 * Comprehensive control panel for managing stock transfers, consignments,
 * and inventory movements across all 18 retail outlets.
 * 
 * @package CIS Business Platform
 * @version 1.0.0
 * @link https://staff.vapeshed.co.nz
 */

declare(strict_types=1);

// Load configuration and base class
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/DashboardBase.php';

// Initialize dashboard
$dashboard = new DashboardBase();
$dashboard->setModule('consignment-hub');

// Set variables for template
$pageTitle = 'Consignment Hub';
$pageDescription = 'Stock transfer and consignment management across all outlets';
$currentModule = 'consignment-hub';
$breadcrumbs = $dashboard->getNavigation()->getBreadcrumbs();

// Include header and sidebar
include __DIR__ . '/../templates/header.php';
include __DIR__ . '/../templates/sidebar-new.php';
?>

<!-- Main Content -->
<div class="main-content">
    <div class="container-fluid">
        
        <!-- Consignment Status Overview -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-gradient-consignment text-white d-flex justify-content-between align-items-center">
                        <div>
                            <h3 class="mb-1"><i class="fas fa-truck"></i> Consignment Hub Control Center</h3>
                            <p class="mb-0 opacity-75">Stock transfer and consignment management across 18 outlets</p>
                        </div>
                        <div>
                            <button class="btn btn-light btn-sm me-2" onclick="consignmentHub.refreshStatus()">
                                <i class="fas fa-sync-alt"></i> Refresh
                            </button>
                            <button class="btn btn-light btn-sm" onclick="consignmentHub.createTransfer()">
                                <i class="fas fa-plus"></i> New Transfer
                            </button>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <!-- Consignment Status Grid -->
                        <div class="consignment-status-grid">
                            
                            <!-- Pending Transfers -->
                            <div class="consignment-status-card" id="pendingTransfersCard">
                                <div class="consignment-indicator pending" id="pendingTransfersIndicator">
                                    <i class="fas fa-clock consignment-icon"></i>
                                </div>
                                <div class="consignment-content">
                                    <h5>Pending Transfers</h5>
                                    <div class="consignment-details">
                                        <div class="metric">
                                            <span class="label">Awaiting Approval:</span>
                                            <span class="value" id="transfersPendingApproval">--</span>
                                        </div>
                                        <div class="metric">
                                            <span class="label">Ready to Ship:</span>
                                            <span class="value" id="transfersReadyToShip">--</span>
                                        </div>
                                        <div class="metric">
                                            <span class="label">Total Value:</span>
                                            <span class="value" id="pendingTransfersValue">--</span>
                                        </div>
                                    </div>
                                    <div class="consignment-actions">
                                        <button class="btn btn-sm btn-warning" onclick="consignmentHub.viewPendingTransfers()">
                                            <i class="fas fa-eye"></i> View All
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- In Transit -->
                            <div class="consignment-status-card" id="inTransitCard">
                                <div class="consignment-indicator in-transit" id="inTransitIndicator">
                                    <i class="fas fa-shipping-fast consignment-icon"></i>
                                </div>
                                <div class="consignment-content">
                                    <h5>In Transit</h5>
                                    <div class="consignment-details">
                                        <div class="metric">
                                            <span class="label">Active Transfers:</span>
                                            <span class="value" id="activeTransfers">--</span>
                                        </div>
                                        <div class="metric">
                                            <span class="label">Est. Deliveries Today:</span>
                                            <span class="value" id="deliveriesToday">--</span>
                                        </div>
                                        <div class="metric">
                                            <span class="label">Total Value:</span>
                                            <span class="value" id="inTransitValue">--</span>
                                        </div>
                                    </div>
                                    <div class="consignment-actions">
                                        <button class="btn btn-sm btn-primary" onclick="consignmentHub.trackTransfers()">
                                            <i class="fas fa-map-marker-alt"></i> Track
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Delivered -->
                            <div class="consignment-status-card" id="deliveredCard">
                                <div class="consignment-indicator delivered" id="deliveredIndicator">
                                    <i class="fas fa-check-circle consignment-icon"></i>
                                </div>
                                <div class="consignment-content">
                                    <h5>Delivered (Today)</h5>
                                    <div class="consignment-details">
                                        <div class="metric">
                                            <span class="label">Completed:</span>
                                            <span class="value" id="completedToday">--</span>
                                        </div>
                                        <div class="metric">
                                            <span class="label">Awaiting Receipt:</span>
                                            <span class="value" id="awaitingReceipt">--</span>
                                        </div>
                                        <div class="metric">
                                            <span class="label">Total Value:</span>
                                            <span class="value" id="deliveredValue">--</span>
                                        </div>
                                    </div>
                                    <div class="consignment-actions">
                                        <button class="btn btn-sm btn-success" onclick="consignmentHub.viewDelivered()">
                                            <i class="fas fa-list"></i> View All
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Issues/Returns -->
                            <div class="consignment-status-card" id="issuesCard">
                                <div class="consignment-indicator issues" id="issuesIndicator">
                                    <i class="fas fa-exclamation-triangle consignment-icon"></i>
                                </div>
                                <div class="consignment-content">
                                    <h5>Issues & Returns</h5>
                                    <div class="consignment-details">
                                        <div class="metric">
                                            <span class="label">Delivery Issues:</span>
                                            <span class="value" id="deliveryIssues">--</span>
                                        </div>
                                        <div class="metric">
                                            <span class="label">Returns Pending:</span>
                                            <span class="value" id="returnsPending">--</span>
                                        </div>
                                        <div class="metric">
                                            <span class="label">Damaged Items:</span>
                                            <span class="value" id="damagedItems">--</span>
                                        </div>
                                    </div>
                                    <div class="consignment-actions">
                                        <button class="btn btn-sm btn-danger" onclick="consignmentHub.viewIssues()">
                                            <i class="fas fa-tools"></i> Resolve
                                        </button>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Transfer Management & Outlet Map -->
        <div class="row mb-4">
            
            <!-- Recent Transfers -->
            <div class="col-lg-8 mb-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-gradient-consignment text-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-list"></i> Recent Transfers</h5>
                        <div>
                            <select class="form-select form-select-sm bg-light" id="transferFilter" onchange="consignmentHub.filterTransfers()">
                                <option value="all">All Transfers</option>
                                <option value="pending">Pending</option>
                                <option value="approved">Approved</option>
                                <option value="shipped">Shipped</option>
                                <option value="delivered">Delivered</option>
                            </select>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover" id="transfersTable">
                                <thead>
                                    <tr>
                                        <th>Transfer ID</th>
                                        <th>From → To</th>
                                        <th>Items</th>
                                        <th>Value</th>
                                        <th>Status</th>
                                        <th>Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="transfersBody">
                                    <tr>
                                        <td colspan="7" class="text-center text-muted py-4">
                                            <i class="fas fa-spinner fa-spin"></i> Loading transfers...
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Outlet Quick Actions -->
            <div class="col-lg-4 mb-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-gradient-consignment text-white">
                        <h5 class="mb-0"><i class="fas fa-store"></i> Outlet Quick Actions</h5>
                    </div>
                    <div class="card-body">
                        <div class="outlet-actions">
                            
                            <div class="action-group mb-3">
                                <h6 class="fw-bold mb-2">Stock Requests</h6>
                                <button class="btn btn-outline-primary btn-sm w-100 mb-2" onclick="consignmentHub.viewStockRequests()">
                                    <i class="fas fa-inbox"></i> View Requests (5)
                                </button>
                                <button class="btn btn-outline-success btn-sm w-100" onclick="consignmentHub.approveAllRequests()">
                                    <i class="fas fa-check-double"></i> Approve All
                                </button>
                            </div>

                            <div class="action-group mb-3">
                                <h6 class="fw-bold mb-2">Emergency Transfers</h6>
                                <button class="btn btn-outline-warning btn-sm w-100 mb-2" onclick="consignmentHub.createEmergencyTransfer()">
                                    <i class="fas fa-bolt"></i> Create Emergency Transfer
                                </button>
                                <button class="btn btn-outline-info btn-sm w-100" onclick="consignmentHub.checkLowStock()">
                                    <i class="fas fa-exclamation-circle"></i> Check Low Stock
                                </button>
                            </div>

                            <div class="action-group mb-3">
                                <h6 class="fw-bold mb-2">Bulk Operations</h6>
                                <button class="btn btn-outline-secondary btn-sm w-100 mb-2" onclick="consignmentHub.bulkApprove()">
                                    <i class="fas fa-tasks"></i> Bulk Approve
                                </button>
                                <button class="btn btn-outline-dark btn-sm w-100" onclick="consignmentHub.generateReports()">
                                    <i class="fas fa-chart-bar"></i> Generate Reports
                                </button>
                            </div>

                        </div>
                    </div>
                </div>
            </div>

        </div>

        <!-- Transfer Analytics & Outlet Map -->
        <div class="row mb-4">
            
            <!-- Transfer Analytics -->
            <div class="col-lg-8 mb-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-gradient-consignment text-white">
                        <h5 class="mb-0"><i class="fas fa-chart-line"></i> Transfer Analytics (7 Days)</h5>
                    </div>
                    <div class="card-body">
                        <div class="transfer-analytics">
                            <canvas id="transferChart" height="300"></canvas>
                        </div>
                        
                        <div class="analytics-metrics mt-3">
                            <div class="row g-3">
                                <div class="col-3">
                                    <div class="metric-box">
                                        <div class="metric-value" id="totalTransfers">--</div>
                                        <div class="metric-label">Total Transfers</div>
                                    </div>
                                </div>
                                <div class="col-3">
                                    <div class="metric-box">
                                        <div class="metric-value" id="avgDeliveryTime">--</div>
                                        <div class="metric-label">Avg Delivery Time</div>
                                    </div>
                                </div>
                                <div class="col-3">
                                    <div class="metric-box">
                                        <div class="metric-value" id="completionRate">--</div>
                                        <div class="metric-label">Completion Rate</div>
                                    </div>
                                </div>
                                <div class="col-3">
                                    <div class="metric-box">
                                        <div class="metric-value" id="totalValue">--</div>
                                        <div class="metric-label">Total Value</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Outlet Status Map -->
            <div class="col-lg-4 mb-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-gradient-consignment text-white">
                        <h5 class="mb-0"><i class="fas fa-map"></i> Outlet Status Map</h5>
                    </div>
                    <div class="card-body">
                        <div class="outlet-map" id="outletMap">
                            <!-- Outlet grid will be populated by JavaScript -->
                        </div>
                        
                        <div class="map-legend mt-3">
                            <div class="legend-item">
                                <div class="legend-color healthy"></div>
                                <span>Normal Operations</span>
                            </div>
                            <div class="legend-item">
                                <div class="legend-color low-stock"></div>
                                <span>Low Stock</span>
                            </div>
                            <div class="legend-item">
                                <div class="legend-color pending-transfers"></div>
                                <span>Pending Transfers</span>
                            </div>
                            <div class="legend-item">
                                <div class="legend-color issues"></div>
                                <span>Issues/Problems</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <!-- Transfer Creation & Tools -->
        <div class="row">
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-gradient-consignment text-white">
                        <h5 class="mb-0"><i class="fas fa-tools"></i> Transfer Tools & Utilities</h5>
                    </div>
                    <div class="card-body">
                        <div class="transfer-tools-grid">
                            
                            <button class="transfer-tool-btn" onclick="consignmentHub.createTransfer()">
                                <i class="fas fa-plus-circle"></i>
                                <span>Create Transfer</span>
                                <small>Manual stock transfer between outlets</small>
                            </button>

                            <button class="transfer-tool-btn" onclick="consignmentHub.automaticRebalance()">
                                <i class="fas fa-balance-scale"></i>
                                <span>Auto Rebalance</span>
                                <small>AI-powered stock redistribution</small>
                            </button>

                            <button class="transfer-tool-btn" onclick="consignmentHub.bulkTransfers()">
                                <i class="fas fa-layer-group"></i>
                                <span>Bulk Transfers</span>
                                <small>Create multiple transfers at once</small>
                            </button>

                            <button class="transfer-tool-btn" onclick="consignmentHub.stockForecast()">
                                <i class="fas fa-crystal-ball"></i>
                                <span>Stock Forecast</span>
                                <small>Predict future stock requirements</small>
                            </button>

                            <button class="transfer-tool-btn" onclick="consignmentHub.transferHistory()">
                                <i class="fas fa-history"></i>
                                <span>Transfer History</span>
                                <small>View complete transfer records</small>
                            </button>

                            <button class="transfer-tool-btn" onclick="consignmentHub.vendSync()">
                                <i class="fas fa-sync"></i>
                                <span>Vend Sync</span>
                                <small>Sync with Lightspeed POS</small>
                            </button>

                            <button class="transfer-tool-btn" onclick="consignmentHub.printLabels()">
                                <i class="fas fa-print"></i>
                                <span>Print Labels</span>
                                <small>Generate shipping labels</small>
                            </button>

                            <button class="transfer-tool-btn" onclick="consignmentHub.reconciliation()">
                                <i class="fas fa-check-square"></i>
                                <span>Reconciliation</span>
                                <small>Match physical vs system inventory</small>
                            </button>

                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- Transfer Details Modal -->
<div class="modal fade" id="transferDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-info-circle"></i> Transfer Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="transferDetailsContent">
                    <div class="text-center">
                        <i class="fas fa-spinner fa-spin"></i> Loading transfer details...
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Create Transfer Modal -->
<div class="modal fade" id="createTransferModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-plus"></i> Create New Transfer</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="createTransferForm">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">From Outlet</label>
                                <select class="form-select" id="fromOutlet" required>
                                    <option value="">Select source outlet...</option>
                                    <option value="1">Hamilton Central</option>
                                    <option value="2">Auckland CBD</option>
                                    <option value="3">Wellington Central</option>
                                    <option value="4">Christchurch</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">To Outlet</label>
                                <select class="form-select" id="toOutlet" required>
                                    <option value="">Select destination outlet...</option>
                                    <option value="1">Hamilton Central</option>
                                    <option value="2">Auckland CBD</option>
                                    <option value="3">Wellington Central</option>
                                    <option value="4">Christchurch</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Transfer Type</label>
                        <select class="form-select" id="transferType" required>
                            <option value="">Select transfer type...</option>
                            <option value="regular">Regular Transfer</option>
                            <option value="emergency">Emergency Transfer</option>
                            <option value="rebalance">Stock Rebalance</option>
                            <option value="return">Return Transfer</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Priority</label>
                        <select class="form-select" id="transferPriority">
                            <option value="normal">Normal</option>
                            <option value="high">High</option>
                            <option value="urgent">Urgent</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea class="form-control" id="transferNotes" rows="3" placeholder="Additional notes for this transfer..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="consignmentHub.submitTransfer()">
                    <i class="fas fa-save"></i> Create Transfer
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Consignment Hub Styles -->
<style>
.bg-gradient-consignment {
    background: linear-gradient(135deg, #17a2b8 0%, #007bff 100%);
}

.consignment-status-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 1.5rem;
    padding: 1.5rem;
}

.consignment-status-card {
    display: flex;
    align-items: center;
    padding: 1.5rem;
    background: linear-gradient(135deg, #f8fcff 0%, #e8f4fd 100%);
    border-radius: 12px;
    border: 1px solid rgba(23, 162, 184, 0.1);
    transition: all 0.3s ease;
}

.consignment-status-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(23, 162, 184, 0.15);
}

.consignment-indicator {
    width: 70px;
    height: 70px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 1.5rem;
    background: linear-gradient(135deg, #17a2b8 0%, #007bff 100%);
    position: relative;
}

.consignment-indicator.pending {
    background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);
}

.consignment-indicator.in-transit {
    background: linear-gradient(135deg, #007bff 0%, #6610f2 100%);
    animation: pulse 2s infinite;
}

.consignment-indicator.delivered {
    background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
}

.consignment-indicator.issues {
    background: linear-gradient(135deg, #dc3545 0%, #fd7e14 100%);
}

.consignment-icon {
    font-size: 1.8rem;
    color: white;
}

.consignment-content {
    flex: 1;
}

.consignment-content h5 {
    margin-bottom: 0.75rem;
    font-weight: 600;
    color: #495057;
}

.consignment-details .metric {
    display: flex;
    justify-content: space-between;
    margin-bottom: 0.5rem;
    font-size: 0.9rem;
}

.consignment-details .label {
    color: #6c757d;
}

.consignment-details .value {
    font-weight: 600;
    color: #495057;
}

.consignment-actions {
    margin-top: 1rem;
}

.outlet-actions .action-group {
    padding: 1rem;
    background: linear-gradient(135deg, #f8fcff 0%, #e8f4fd 100%);
    border-radius: 8px;
    border: 1px solid rgba(23, 162, 184, 0.1);
}

.outlet-map {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 0.5rem;
    margin-bottom: 1rem;
}

.outlet-dot {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: bold;
    font-size: 0.8rem;
    cursor: pointer;
    transition: all 0.3s ease;
}

.outlet-dot:hover {
    transform: scale(1.1);
}

.outlet-dot.healthy {
    background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
}

.outlet-dot.low-stock {
    background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);
}

.outlet-dot.pending-transfers {
    background: linear-gradient(135deg, #007bff 0%, #6610f2 100%);
}

.outlet-dot.issues {
    background: linear-gradient(135deg, #dc3545 0%, #fd7e14 100%);
}

.map-legend {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.legend-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.85rem;
}

.legend-color {
    width: 16px;
    height: 16px;
    border-radius: 50%;
}

.legend-color.healthy {
    background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
}

.legend-color.low-stock {
    background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);
}

.legend-color.pending-transfers {
    background: linear-gradient(135deg, #007bff 0%, #6610f2 100%);
}

.legend-color.issues {
    background: linear-gradient(135deg, #dc3545 0%, #fd7e14 100%);
}

.metric-box {
    text-align: center;
    padding: 1rem;
    background: linear-gradient(135deg, #f8fcff 0%, #e8f4fd 100%);
    border-radius: 8px;
    border: 1px solid rgba(23, 162, 184, 0.1);
}

.metric-box .metric-value {
    font-size: 1.5rem;
    font-weight: 700;
    color: #17a2b8;
    margin-bottom: 0.25rem;
}

.metric-box .metric-label {
    font-size: 0.85rem;
    color: #6c757d;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.transfer-tools-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1rem;
}

.transfer-tool-btn {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 2rem 1.5rem;
    background: linear-gradient(135deg, #f8fcff 0%, #e8f4fd 100%);
    border: 1px solid rgba(23, 162, 184, 0.2);
    border-radius: 12px;
    transition: all 0.3s ease;
    cursor: pointer;
    text-decoration: none;
    color: #495057;
}

.transfer-tool-btn:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 30px rgba(23, 162, 184, 0.2);
    background: linear-gradient(135deg, #17a2b8 0%, #007bff 100%);
    color: white;
}

.transfer-tool-btn i {
    font-size: 2rem;
    margin-bottom: 0.75rem;
    opacity: 0.8;
}

.transfer-tool-btn span {
    font-weight: 600;
    text-align: center;
    margin-bottom: 0.25rem;
}

.transfer-tool-btn small {
    text-align: center;
    opacity: 0.7;
}

@keyframes pulse {
    0% { transform: scale(1); opacity: 1; }
    50% { transform: scale(1.05); opacity: 0.8; }
    100% { transform: scale(1); opacity: 1; }
}

/* Status badges */
.status-badge {
    font-size: 0.75rem;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    font-weight: 600;
    text-transform: uppercase;
}

.status-badge.pending {
    background: #fff3cd;
    color: #856404;
}

.status-badge.approved {
    background: #d1ecf1;
    color: #0c5460;
}

.status-badge.shipped {
    background: #cce5ff;
    color: #004085;
}

.status-badge.delivered {
    background: #d4edda;
    color: #155724;
}

.status-badge.issues {
    background: #f8d7da;
    color: #721c24;
}

/* Responsive Design */
@media (max-width: 768px) {
    .consignment-status-grid, .transfer-tools-grid {
        grid-template-columns: 1fr;
        padding: 1rem;
    }
    
    .consignment-status-card {
        flex-direction: column;
        text-align: center;
    }
    
    .consignment-indicator {
        margin-right: 0;
        margin-bottom: 1rem;
    }
    
    .analytics-metrics .row {
        gap: 0.5rem !important;
    }
    
    .analytics-metrics .col-3 {
        flex: 0 0 auto;
        width: 50%;
    }
    
    .outlet-map {
        grid-template-columns: repeat(6, 1fr);
    }
}
</style>

<!-- Consignment Hub JavaScript -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.js"></script>
<script>
class ConsignmentHubController {
    constructor() {
        this.csrfToken = '<?php echo DashboardAuth::generateCsrfToken(); ?>';
        this.transferChart = null;
        this.updateInterval = null;
        
        this.init();
    }
    
    init() {
        console.log('Consignment Hub Controller initializing...');
        this.initChart();
        this.loadConsignmentStatus();
        this.loadRecentTransfers();
        this.loadOutletMap();
        this.startRealTimeUpdates();
        this.bindEvents();
    }
    
    initChart() {
        const ctx = document.getElementById('transferChart').getContext('2d');
        
        this.transferChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: Array.from({length: 7}, (_, i) => {
                    const date = new Date();
                    date.setDate(date.getDate() - (6 - i));
                    return date.toLocaleDateString('en-NZ', { weekday: 'short' });
                }),
                datasets: [
                    {
                        label: 'Transfers Created',
                        data: this.generateTransferData(),
                        borderColor: '#17a2b8',
                        backgroundColor: 'rgba(23, 162, 184, 0.1)',
                        borderWidth: 3,
                        fill: true,
                        tension: 0.4
                    },
                    {
                        label: 'Transfers Completed',
                        data: this.generateTransferData(),
                        borderColor: '#28a745',
                        backgroundColor: 'rgba(40, 167, 69, 0.1)',
                        borderWidth: 3,
                        fill: true,
                        tension: 0.4
                    },
                    {
                        label: 'Issues Reported',
                        data: this.generateIssueData(),
                        borderColor: '#dc3545',
                        backgroundColor: 'rgba(220, 53, 69, 0.1)',
                        borderWidth: 2,
                        fill: false,
                        tension: 0.4
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                        labels: { usePointStyle: true }
                    }
                },
                scales: {
                    x: {
                        title: { display: true, text: 'Days' }
                    },
                    y: {
                        title: { display: true, text: 'Count' },
                        beginAtZero: true
                    }
                }
            }
        });
    }
    
    generateTransferData() {
        return Array.from({length: 7}, () => Math.floor(Math.random() * 20) + 15);
    }
    
    generateIssueData() {
        return Array.from({length: 7}, () => Math.floor(Math.random() * 5) + 1);
    }
    
    loadConsignmentStatus() {
        // Simulate loading consignment status
        document.getElementById('transfersPendingApproval').textContent = Math.floor(Math.random() * 15) + 5;
        document.getElementById('transfersReadyToShip').textContent = Math.floor(Math.random() * 10) + 3;
        document.getElementById('pendingTransfersValue').textContent = '$' + (Math.floor(Math.random() * 50000) + 25000).toLocaleString();
        
        document.getElementById('activeTransfers').textContent = Math.floor(Math.random() * 20) + 8;
        document.getElementById('deliveriesToday').textContent = Math.floor(Math.random() * 12) + 4;
        document.getElementById('inTransitValue').textContent = '$' + (Math.floor(Math.random() * 75000) + 40000).toLocaleString();
        
        document.getElementById('completedToday').textContent = Math.floor(Math.random() * 25) + 12;
        document.getElementById('awaitingReceipt').textContent = Math.floor(Math.random() * 8) + 2;
        document.getElementById('deliveredValue').textContent = '$' + (Math.floor(Math.random() * 60000) + 30000).toLocaleString();
        
        document.getElementById('deliveryIssues').textContent = Math.floor(Math.random() * 5) + 1;
        document.getElementById('returnsPending').textContent = Math.floor(Math.random() * 3) + 1;
        document.getElementById('damagedItems').textContent = Math.floor(Math.random() * 4) + 1;
        
        // Update analytics metrics
        document.getElementById('totalTransfers').textContent = (Math.floor(Math.random() * 100) + 150).toLocaleString();
        document.getElementById('avgDeliveryTime').textContent = (Math.random() * 2 + 1).toFixed(1) + ' days';
        document.getElementById('completionRate').textContent = (Math.random() * 5 + 94).toFixed(1) + '%';
        document.getElementById('totalValue').textContent = '$' + (Math.floor(Math.random() * 200000) + 500000).toLocaleString();
    }
    
    loadRecentTransfers() {
        const transferTypes = ['Stock Rebalance', 'Emergency Transfer', 'Regular Transfer', 'Return Transfer'];
        const outlets = [
            'Hamilton Central', 'Auckland CBD', 'Wellington Central', 'Christchurch',
            'Dunedin', 'Tauranga', 'Rotorua', 'Palmerston North'
        ];
        const statuses = ['pending', 'approved', 'shipped', 'delivered'];
        const tbody = document.getElementById('transfersBody');
        
        const transfers = Array.from({length: Math.floor(Math.random() * 10) + 8}, (_, i) => {
            const fromOutlet = outlets[Math.floor(Math.random() * outlets.length)];
            let toOutlet = outlets[Math.floor(Math.random() * outlets.length)];
            while (toOutlet === fromOutlet) {
                toOutlet = outlets[Math.floor(Math.random() * outlets.length)];
            }
            
            const transferType = transferTypes[Math.floor(Math.random() * transferTypes.length)];
            const status = statuses[Math.floor(Math.random() * statuses.length)];
            const items = Math.floor(Math.random() * 50) + 10;
            const value = Math.floor(Math.random() * 10000) + 2000;
            const date = new Date(Date.now() - Math.random() * 7 * 24 * 60 * 60 * 1000);
            
            return {
                id: `TR${Date.now().toString().slice(-6)}${i}`,
                type: transferType,
                fromOutlet: fromOutlet,
                toOutlet: toOutlet,
                status: status,
                items: items,
                value: value,
                date: date
            };
        });
        
        tbody.innerHTML = transfers.map(transfer => `
            <tr>
                <td class="font-monospace">${transfer.id}</td>
                <td>
                    <div class="transfer-route">
                        <div class="from-outlet">${transfer.fromOutlet}</div>
                        <i class="fas fa-arrow-right text-muted mx-1"></i>
                        <div class="to-outlet">${transfer.toOutlet}</div>
                    </div>
                </td>
                <td>${transfer.items}</td>
                <td>$${transfer.value.toLocaleString()}</td>
                <td>
                    <span class="status-badge ${transfer.status}">${transfer.status}</span>
                </td>
                <td>${transfer.date.toLocaleDateString()}</td>
                <td>
                    <button class="btn btn-sm btn-outline-info" onclick="consignmentHub.viewTransferDetails('${transfer.id}')">
                        <i class="fas fa-eye"></i>
                    </button>
                    <button class="btn btn-sm btn-outline-primary" onclick="consignmentHub.editTransfer('${transfer.id}')">
                        <i class="fas fa-edit"></i>
                    </button>
                </td>
            </tr>
        `).join('');
    }
    
    loadOutletMap() {
        const outletMap = document.getElementById('outletMap');
        
        const outlets = [
            'Hamilton Central', 'Auckland CBD', 'Wellington Central', 'Christchurch',
            'Dunedin', 'Tauranga', 'Rotorua', 'Palmerston North', 'New Plymouth',
            'Gisborne', 'Napier', 'Hastings', 'Whangarei', 'Invercargill',
            'Nelson', 'Blenheim', 'Timaru', 'Queenstown'
        ];
        
        outletMap.innerHTML = outlets.map((outlet, index) => {
            const statuses = ['healthy', 'low-stock', 'pending-transfers', 'issues'];
            const weights = [0.5, 0.2, 0.2, 0.1];
            const status = this.weightedRandom(statuses, weights);
            
            return `
                <div class="outlet-dot ${status}" 
                     title="${outlet} - ${status.replace('-', ' ').replace(/\b\w/g, l => l.toUpperCase())}"
                     onclick="consignmentHub.viewOutletDetails('${outlet}')">
                    ${index + 1}
                </div>
            `;
        }).join('');
    }
    
    weightedRandom(items, weights) {
        const random = Math.random();
        let sum = 0;
        
        for (let i = 0; i < items.length; i++) {
            sum += weights[i];
            if (random <= sum) {
                return items[i];
            }
        }
        
        return items[items.length - 1];
    }
    
    startRealTimeUpdates() {
        this.updateInterval = setInterval(() => {
            this.updateChart();
            this.updateMetrics();
        }, 45000); // Update every 45 seconds
    }
    
    updateChart() {
        if (this.transferChart) {
            this.transferChart.data.datasets.forEach(dataset => {
                const newValue = dataset.label.includes('Issues') ? 
                    Math.floor(Math.random() * 5) + 1 : 
                    Math.floor(Math.random() * 20) + 15;
                dataset.data.shift();
                dataset.data.push(newValue);
            });
            this.transferChart.update('none');
        }
    }
    
    updateMetrics() {
        // Slightly update metrics to show activity
        const currentTransfers = parseInt(document.getElementById('totalTransfers').textContent.replace(/,/g, ''));
        document.getElementById('totalTransfers').textContent = (currentTransfers + Math.floor(Math.random() * 3)).toLocaleString();
    }
    
    bindEvents() {
        // Filter transfers
        document.getElementById('transferFilter').addEventListener('change', (e) => {
            this.filterTransfers(e.target.value);
        });
    }
    
    // Transfer Management Methods
    createTransfer() {
        console.log('Opening create consignment modal...');
        new bootstrap.Modal(document.getElementById('createConsignmentModal')).show();
    }
    
    submitTransfer() {
        const fromOutlet = document.getElementById('fromOutlet').value;
        const toOutlet = document.getElementById('toOutlet').value;
        const transferType = document.getElementById('transferType').value;
        const priority = document.getElementById('transferPriority').value;
        const notes = document.getElementById('transferNotes').value;
        
        if (!fromOutlet || !toOutlet || !transferType) {
            this.showNotification('Please fill in all required fields', 'danger');
            return;
        }
        
        if (fromOutlet === toOutlet) {
            this.showNotification('Source and destination outlets must be different', 'danger');
            return;
        }
        
        console.log('Creating transfer...', { fromOutlet, toOutlet, transferType, priority, notes });
        this.showNotification('Transfer created successfully', 'success');
        
        // Close modal and refresh transfers
        bootstrap.Modal.getInstance(document.getElementById('createTransferModal')).hide();
        setTimeout(() => {
            this.loadRecentTransfers();
        }, 1000);
    }
    
    viewTransferDetails(transferId) {
        console.log(`Viewing details for transfer ${transferId}...`);
        
        // Simulate transfer details
        const transferDetails = `
            <div class="transfer-details">
                <div class="row">
                    <div class="col-md-6">
                        <h6>Transfer Information</h6>
                        <table class="table table-sm">
                            <tr><td><strong>Transfer ID:</strong></td><td class="font-monospace">${transferId}</td></tr>
                            <tr><td><strong>Type:</strong></td><td>Stock Rebalance</td></tr>
                            <tr><td><strong>Priority:</strong></td><td><span class="badge bg-warning">High</span></td></tr>
                            <tr><td><strong>Status:</strong></td><td><span class="status-badge shipped">shipped</span></td></tr>
                            <tr><td><strong>Created:</strong></td><td>${new Date().toLocaleDateString()}</td></tr>
                            <tr><td><strong>Estimated Delivery:</strong></td><td>${new Date(Date.now() + 86400000).toLocaleDateString()}</td></tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h6>Route Information</h6>
                        <table class="table table-sm">
                            <tr><td><strong>From:</strong></td><td>Hamilton Central</td></tr>
                            <tr><td><strong>To:</strong></td><td>Auckland CBD</td></tr>
                            <tr><td><strong>Distance:</strong></td><td>126 km</td></tr>
                            <tr><td><strong>Carrier:</strong></td><td>NZ Post</td></tr>
                            <tr><td><strong>Tracking:</strong></td><td class="font-monospace">NZ12345678</td></tr>
                            <tr><td><strong>Total Value:</strong></td><td>$4,750</td></tr>
                        </table>
                    </div>
                </div>
                
                <h6 class="mt-3">Transfer Items</h6>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>SKU</th>
                                <th>Quantity</th>
                                <th>Unit Cost</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>SMOK Nord 4 Kit</td>
                                <td>SMK-N4-BLK</td>
                                <td>15</td>
                                <td>$45.00</td>
                                <td>$675.00</td>
                            </tr>
                            <tr>
                                <td>Vaporesso XROS 3 Pod Kit</td>
                                <td>VAP-XR3-SLV</td>
                                <td>20</td>
                                <td>$35.00</td>
                                <td>$700.00</td>
                            </tr>
                            <tr>
                                <td>Lost Vape Centaurus B80</td>
                                <td>LV-CB80-GUN</td>
                                <td>10</td>
                                <td>$95.00</td>
                                <td>$950.00</td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr class="fw-bold">
                                <td colspan="4">Total:</td>
                                <td>$2,325.00</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                
                <h6 class="mt-3">Transfer Timeline</h6>
                <div class="timeline">
                    <div class="timeline-item completed">
                        <div class="timeline-marker"></div>
                        <div class="timeline-content">
                            <strong>Transfer Created</strong><br>
                            <small class="text-muted">${new Date().toLocaleString()}</small>
                        </div>
                    </div>
                    <div class="timeline-item completed">
                        <div class="timeline-marker"></div>
                        <div class="timeline-content">
                            <strong>Approved by Manager</strong><br>
                            <small class="text-muted">${new Date(Date.now() - 3600000).toLocaleString()}</small>
                        </div>
                    </div>
                    <div class="timeline-item active">
                        <div class="timeline-marker"></div>
                        <div class="timeline-content">
                            <strong>In Transit</strong><br>
                            <small class="text-muted">Currently shipping</small>
                        </div>
                    </div>
                    <div class="timeline-item">
                        <div class="timeline-marker"></div>
                        <div class="timeline-content">
                            <strong>Delivered</strong><br>
                            <small class="text-muted">Pending</small>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        document.getElementById('transferDetailsContent').innerHTML = transferDetails;
        new bootstrap.Modal(document.getElementById('transferDetailsModal')).show();
    }
    
    editTransfer(transferId) {
        console.log(`Editing transfer ${transferId}...`);
        this.showNotification(`Opening transfer ${transferId} for editing`, 'info');
    }
    
    filterTransfers(status = null) {
        console.log(`Filtering transfers by status: ${status || 'all'}`);
        this.loadRecentTransfers(); // In real implementation, this would filter the existing data
        this.showNotification(`Showing ${status || 'all'} transfers`, 'info');
    }
    
    // Quick Action Methods
    viewStockRequests() {
        console.log('Viewing stock requests...');
        this.showNotification('Loading stock requests...', 'info');
    }
    
    approveAllRequests() {
        if (confirm('Approve all pending stock requests?')) {
            console.log('Approving all stock requests...');
            this.showNotification('All stock requests approved', 'success');
        }
    }
    
    createEmergencyTransfer() {
        console.log('Creating emergency transfer...');
        this.showNotification('Emergency transfer wizard opened', 'warning');
    }
    
    checkLowStock() {
        console.log('Checking low stock across outlets...');
        this.showNotification('Low stock check initiated...', 'info');
        
        setTimeout(() => {
            this.showNotification('Low stock check completed - 3 outlets need restocking', 'warning');
        }, 2000);
    }
    
    bulkApprove() {
        console.log('Opening bulk approval interface...');
        this.showNotification('Bulk approval interface loaded', 'info');
    }
    
    generateReports() {
        console.log('Generating transfer reports...');
        this.showNotification('Transfer reports are being generated...', 'info');
        
        setTimeout(() => {
            this.showNotification('Transfer reports generated successfully', 'success');
        }, 3000);
    }
    
    // Tool Methods
    automaticRebalance() {
        if (confirm('Start automatic stock rebalancing? This will analyze stock levels across all outlets and create optimal transfers.')) {
            console.log('Starting automatic rebalancing...');
            this.showNotification('AI-powered stock rebalancing initiated...', 'info');
            
            setTimeout(() => {
                this.showNotification('Automatic rebalancing completed - 8 transfers created', 'success');
                this.loadRecentTransfers();
            }, 5000);
        }
    }
    
    bulkTransfers() {
        console.log('Opening bulk transfer creator...');
        this.showNotification('Bulk transfer creation interface loaded', 'info');
    }
    
    stockForecast() {
        console.log('Opening stock forecast...');
        this.showNotification('Stock forecasting system loaded', 'info');
    }
    
    transferHistory() {
        console.log('Opening transfer history...');
        this.showNotification('Transfer history loaded', 'info');
    }
    
    vendSync() {
        console.log('Syncing with Vend/Lightspeed...');
        this.showNotification('Vend sync started...', 'info');
        
        setTimeout(() => {
            this.showNotification('Vend sync completed successfully', 'success');
        }, 3000);
    }
    
    printLabels() {
        console.log('Opening label printing...');
        this.showNotification('Label printing interface loaded', 'info');
    }
    
    reconciliation() {
        console.log('Starting inventory reconciliation...');
        this.showNotification('Inventory reconciliation started...', 'info');
        
        setTimeout(() => {
            this.showNotification('Reconciliation completed - 2 discrepancies found', 'warning');
        }, 4000);
    }
    
    // Outlet Methods
    viewOutletDetails(outletName) {
        console.log(`Viewing details for ${outletName}...`);
        this.showNotification(`Loading ${outletName} details...`, 'info');
    }
    
    viewPendingTransfers() {
        console.log('Viewing pending transfers...');
        this.showNotification('Loading pending transfers...', 'info');
    }
    
    trackTransfers() {
        console.log('Opening transfer tracking...');
        this.showNotification('Transfer tracking interface loaded', 'info');
    }
    
    viewDelivered() {
        console.log('Viewing delivered transfers...');
        this.showNotification('Loading delivered transfers...', 'info');
    }
    
    viewIssues() {
        console.log('Viewing transfer issues...');
        this.showNotification('Loading transfer issues...', 'info');
    }
    
    // Utility Methods
    refreshStatus() {
        console.log('Refreshing consignment status...');
        this.loadConsignmentStatus();
        this.loadRecentTransfers();
        this.loadOutletMap();
        this.showNotification('Consignment status refreshed', 'success');
    }
    
    showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
        notification.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 350px;';
        notification.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        document.body.appendChild(notification);
        
        setTimeout(() => {
            if (notification.parentNode) {
                notification.remove();
            }
        }, 5000);
    }
    
    // ====================================================================
    // NEW CONTROL PANEL METHODS
    // ====================================================================
    
    async submitCreateConsignment() {
        const formData = new FormData(document.getElementById('createConsignmentForm'));
        formData.append('action', 'create_consignment');
        
        try {
            const response = await fetch('../api/consignment-control.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.showNotification(`✅ Consignment ${result.reference} created successfully!`, 'success');
                bootstrap.Modal.getInstance(document.getElementById('createConsignmentModal')).hide();
                this.loadRecentTransfers();
            } else {
                this.showNotification('❌ Error: ' + result.error, 'danger');
            }
        } catch (error) {
            this.showNotification('❌ Network error: ' + error.message, 'danger');
        }
    }
    
    async deleteConsignment(consignmentId) {
        const reason = prompt('⚠️ REASON FOR DELETION:\n\nThis action will be logged and audited.');
        
        if (!reason || reason.trim() === '') {
            return;
        }
        
        if (!confirm('🗑️ DELETE CONSIGNMENT?\n\nThis will remove the consignment and all its products.\n\nContinue?')) {
            return;
        }
        
        const formData = new FormData();
        formData.append('action', 'delete_consignment');
        formData.append('consignment_id', consignmentId);
        formData.append('reason', reason);
        
        try {
            const response = await fetch('../api/consignment-control.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.showNotification('✅ ' + result.message, 'success');
                this.loadRecentTransfers();
            } else {
                this.showNotification('❌ Error: ' + result.error, 'danger');
            }
        } catch (error) {
            this.showNotification('❌ Network error: ' + error.message, 'danger');
        }
    }
    
    async changeStatus(consignmentId) {
        const newStatus = prompt('Enter new status:\n\nOPEN | SENT | RECEIVED | CANCELLED');
        
        if (!newStatus) {
            return;
        }
        
        const reason = prompt('Reason for status change:');
        
        const formData = new FormData();
        formData.append('action', 'change_status');
        formData.append('consignment_id', consignmentId);
        formData.append('new_status', newStatus.toUpperCase());
        formData.append('reason', reason || 'Manual status change');
        
        try {
            const response = await fetch('../api/consignment-control.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.showNotification(`✅ Status changed to ${result.new_status}`, 'success');
                this.loadRecentTransfers();
            } else {
                this.showNotification('❌ Error: ' + result.error, 'danger');
            }
        } catch (error) {
            this.showNotification('❌ Network error: ' + error.message, 'danger');
        }
    }
    
    openStockAdjustment(consignmentId, variantId, location) {
        document.getElementById('adjustConsignmentId').value = consignmentId;
        document.getElementById('adjustVariantId').value = variantId;
        document.getElementById('adjustmentLocation').value = location;
        
        new bootstrap.Modal(document.getElementById('stockAdjustmentModal')).show();
    }
    
    async submitStockAdjustment() {
        const consignmentId = document.getElementById('adjustConsignmentId').value;
        const variantId = document.getElementById('adjustVariantId').value;
        const location = document.getElementById('adjustmentLocation').value;
        const adjustment = document.getElementById('adjustmentAmount').value;
        const reason = document.getElementById('adjustmentReason').value;
        
        if (!adjustment || !reason) {
            this.showNotification('❌ Please fill in all fields', 'danger');
            return;
        }
        
        const formData = new FormData();
        formData.append('action', location === 'source' ? 'adjust_source_stock' : 'adjust_destination_stock');
        formData.append('consignment_id', consignmentId);
        formData.append('variant_id', variantId);
        formData.append('adjustment', adjustment);
        formData.append('reason', reason);
        
        try {
            const response = await fetch('../api/consignment-control.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.showNotification(`✅ Stock adjusted: ${result.adjustment > 0 ? '+' : ''}${result.adjustment} units`, 'success');
                bootstrap.Modal.getInstance(document.getElementById('stockAdjustmentModal')).hide();
            } else {
                this.showNotification('❌ Error: ' + result.error, 'danger');
            }
        } catch (error) {
            this.showNotification('❌ Network error: ' + error.message, 'danger');
        }
    }
    
    openMoveConsignment(consignmentId) {
        document.getElementById('moveConsignmentId').value = consignmentId;
        new bootstrap.Modal(document.getElementById('moveConsignmentModal')).show();
    }
    
    async submitMoveConsignment() {
        const formData = new FormData(document.getElementById('moveConsignmentForm'));
        formData.append('action', 'move_consignment');
        
        try {
            const response = await fetch('../api/consignment-control.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.showNotification('✅ ' + result.message, 'success');
                bootstrap.Modal.getInstance(document.getElementById('moveConsignmentModal')).hide();
                this.loadRecentTransfers();
            } else {
                this.showNotification('❌ Error: ' + result.error, 'danger');
            }
        } catch (error) {
            this.showNotification('❌ Network error: ' + error.message, 'danger');
        }
    }
    
    openLightspeedApproval(consignmentId) {
        document.getElementById('approvalConsignmentId').value = consignmentId;
        new bootstrap.Modal(document.getElementById('lightspeedApprovalModal')).show();
    }
    
    async submitLightspeedApproval() {
        const consignmentId = document.getElementById('approvalConsignmentId').value;
        const pushState = document.getElementById('approvalPushState').value;
        
        const formData = new FormData();
        formData.append('action', 'approve_for_lightspeed');
        formData.append('consignment_id', consignmentId);
        formData.append('push_state', pushState);
        
        try {
            const response = await fetch('../api/consignment-control.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.showNotification(`✅ Approved for Lightspeed as ${result.push_state}`, 'success');
                bootstrap.Modal.getInstance(document.getElementById('lightspeedApprovalModal')).hide();
                this.loadRecentTransfers();
            } else {
                this.showNotification('❌ Error: ' + result.error, 'danger');
            }
        } catch (error) {
            this.showNotification('❌ Network error: ' + error.message, 'danger');
        }
    }
}

// Initialize Consignment Hub Controller
document.addEventListener('DOMContentLoaded', function() {
    window.consignmentHub = new ConsignmentHubController();
});
</script>

<!-- Additional Timeline Styles -->
<style>
.timeline {
    position: relative;
    padding-left: 30px;
}

.timeline::before {
    content: '';
    position: absolute;
    left: 15px;
    top: 0;
    bottom: 0;
    width: 2px;
    background: #dee2e6;
}

.timeline-item {
    position: relative;
    margin-bottom: 20px;
}

.timeline-marker {
    position: absolute;
    left: -23px;
    top: 4px;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    background: #dee2e6;
    border: 2px solid white;
}

.timeline-item.completed .timeline-marker {
    background: #28a745;
}

.timeline-item.active .timeline-marker {
    background: #17a2b8;
    box-shadow: 0 0 0 4px rgba(23, 162, 184, 0.2);
}

.timeline-content {
    padding-left: 10px;
}

.transfer-route {
    display: flex;
    align-items: center;
    font-size: 0.9rem;
}

.from-outlet, .to-outlet {
    white-space: nowrap;
}
</style>

<?php 
// Include Consignment Control Panel Modals
include __DIR__ . '/consignment-control-modals.php'; 
?>

<?php include __DIR__ . '/../templates/footer-new.php'; ?>