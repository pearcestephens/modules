<div class="container-fluid">
  <!-- Consignment Hub (verbatim layout as provided) -->

  <!-- Status Overview and Actions -->
  <div class="row mb-4">
    <div class="col-12">
      <div class="card border-0 shadow-sm">
        <div class="card-header bg-gradient-consignment text-white d-flex justify-content-between align-items-center">
          <div>
            <h3 class="mb-1"><i class="fas fa-truck"></i> Consignment Hub Control Center</h3>
            <p class="mb-0 opacity-75">Stock transfer and consignment management across 18 outlets</p>
          </div>
          <div>
            <button class="btn btn-light btn-sm me-2" onclick="consignmentHub && consignmentHub.refreshStatus ? consignmentHub.refreshStatus() : void(0)">
              <i class="fas fa-sync-alt"></i> Refresh
            </button>
            <button class="btn btn-light btn-sm" onclick="consignmentHub && consignmentHub.createTransfer ? consignmentHub.createTransfer() : void(0)">
              <i class="fas fa-plus"></i> New Transfer
            </button>
          </div>
        </div>
        <div class="card-body p-0">
          <!-- Status Grid -->
          <div class="consignment-status-grid">
            <!-- Pending Transfers -->
            <div class="consignment-status-card" id="pendingTransfersCard">
              <div class="consignment-indicator pending" id="pendingTransfersIndicator">
                <i class="fas fa-clock consignment-icon"></i>
              </div>
              <div class="consignment-content">
                <h5>Pending Transfers</h5>
                <div class="consignment-details">
                  <div class="metric"><span class="label">Awaiting Approval:</span><span class="value" id="transfersPendingApproval">--</span></div>
                  <div class="metric"><span class="label">Ready to Ship:</span><span class="value" id="transfersReadyToShip">--</span></div>
                  <div class="metric"><span class="label">Total Value:</span><span class="value" id="pendingTransfersValue">--</span></div>
                </div>
                <div class="consignment-actions">
                  <button class="btn btn-sm btn-warning" onclick="consignmentHub && consignmentHub.viewPendingTransfers ? consignmentHub.viewPendingTransfers() : void(0)">
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
                  <div class="metric"><span class="label">Active Transfers:</span><span class="value" id="activeTransfers">--</span></div>
                  <div class="metric"><span class="label">Est. Deliveries Today:</span><span class="value" id="deliveriesToday">--</span></div>
                  <div class="metric"><span class="label">Total Value:</span><span class="value" id="inTransitValue">--</span></div>
                </div>
                <div class="consignment-actions">
                  <button class="btn btn-sm btn-primary" onclick="consignmentHub && consignmentHub.trackTransfers ? consignmentHub.trackTransfers() : void(0)">
                    <i class="fas fa-map-marker-alt"></i> Track
                  </button>
                </div>
              </div>
            </div>

            <!-- Delivered (Today) -->
            <div class="consignment-status-card" id="deliveredCard">
              <div class="consignment-indicator delivered" id="deliveredIndicator">
                <i class="fas fa-check-circle consignment-icon"></i>
              </div>
              <div class="consignment-content">
                <h5>Delivered (Today)</h5>
                <div class="consignment-details">
                  <div class="metric"><span class="label">Completed:</span><span class="value" id="completedToday">--</span></div>
                  <div class="metric"><span class="label">Awaiting Receipt:</span><span class="value" id="awaitingReceipt">--</span></div>
                  <div class="metric"><span class="label">Total Value:</span><span class="value" id="deliveredValue">--</span></div>
                </div>
                <div class="consignment-actions">
                  <button class="btn btn-sm btn-success" onclick="consignmentHub && consignmentHub.viewDelivered ? consignmentHub.viewDelivered() : void(0)">
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
                  <div class="metric"><span class="label">Delivery Issues:</span><span class="value" id="deliveryIssues">--</span></div>
                  <div class="metric"><span class="label">Returns Pending:</span><span class="value" id="returnsPending">--</span></div>
                  <div class="metric"><span class="label">Damaged Items:</span><span class="value" id="damagedItems">--</span></div>
                </div>
                <div class="consignment-actions">
                  <button class="btn btn-sm btn-danger" onclick="consignmentHub && consignmentHub.viewIssues ? consignmentHub.viewIssues() : void(0)">
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

  <!-- Recent Transfers + Outlet Quick Actions -->
  <div class="row mb-4">
    <div class="col-lg-8 mb-4">
      <div class="card border-0 shadow-sm">
        <div class="card-header bg-gradient-consignment text-white d-flex justify-content-between align-items-center">
          <h5 class="mb-0"><i class="fas fa-list"></i> Recent Transfers</h5>
          <div>
            <select class="form-select form-select-sm bg-light" id="transferFilter" onchange="consignmentHub && consignmentHub.filterTransfers ? consignmentHub.filterTransfers() : void(0)">
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
    <div class="col-lg-4 mb-4">
      <div class="card border-0 shadow-sm">
        <div class="card-header bg-gradient-consignment text-white">
          <h5 class="mb-0"><i class="fas fa-store"></i> Outlet Quick Actions</h5>
        </div>
        <div class="card-body">
          <div class="outlet-actions">
            <div class="action-group mb-3">
              <h6 class="fw-bold mb-2">Stock Requests</h6>
              <button class="btn btn-outline-primary btn-sm w-100 mb-2" onclick="consignmentHub && consignmentHub.viewStockRequests ? consignmentHub.viewStockRequests() : void(0)">
                <i class="fas fa-inbox"></i> View Requests (5)
              </button>
              <button class="btn btn-outline-success btn-sm w-100" onclick="consignmentHub && consignmentHub.approveAllRequests ? consignmentHub.approveAllRequests() : void(0)">
                <i class="fas fa-check-double"></i> Approve All
              </button>
            </div>
            <div class="action-group mb-3">
              <h6 class="fw-bold mb-2">Emergency Transfers</h6>
              <button class="btn btn-outline-warning btn-sm w-100 mb-2" onclick="consignmentHub && consignmentHub.createEmergencyTransfer ? consignmentHub.createEmergencyTransfer() : void(0)">
                <i class="fas fa-bolt"></i> Create Emergency Transfer
              </button>
              <button class="btn btn-outline-info btn-sm w-100" onclick="consignmentHub && consignmentHub.checkLowStock ? consignmentHub.checkLowStock() : void(0)">
                <i class="fas fa-exclamation-circle"></i> Check Low Stock
              </button>
            </div>
            <div class="action-group mb-3">
              <h6 class="fw-bold mb-2">Bulk Operations</h6>
              <button class="btn btn-outline-secondary btn-sm w-100 mb-2" onclick="consignmentHub && consignmentHub.bulkApprove ? consignmentHub.bulkApprove() : void(0)">
                <i class="fas fa-tasks"></i> Bulk Approve
              </button>
              <button class="btn btn-outline-dark btn-sm w-100" onclick="consignmentHub && consignmentHub.generateReports ? consignmentHub.generateReports() : void(0)">
                <i class="fas fa-chart-bar"></i> Generate Reports
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Analytics + Outlet Map -->
  <div class="row mb-4">
    <div class="col-lg-8 mb-4">
      <div class="card border-0 shadow-sm">
        <div class="card-header bg-gradient-consignment text-white">
          <h5 class="mb-0"><i class="fas fa-chart-line"></i> Transfer Analytics (7 Days)</h5>
        </div>
        <div class="card-body">
          <div class="transfer-analytics"><canvas id="transferChart" height="300"></canvas></div>
          <div class="analytics-metrics mt-3">
            <div class="row g-3">
              <div class="col-3"><div class="metric-box"><div class="metric-value" id="totalTransfers">--</div><div class="metric-label">Total Transfers</div></div></div>
              <div class="col-3"><div class="metric-box"><div class="metric-value" id="avgDeliveryTime">--</div><div class="metric-label">Avg Delivery Time</div></div></div>
              <div class="col-3"><div class="metric-box"><div class="metric-value" id="completionRate">--</div><div class="metric-label">Completion Rate</div></div></div>
              <div class="col-3"><div class="metric-box"><div class="metric-value" id="totalValue">--</div><div class="metric-label">Total Value</div></div></div>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-lg-4 mb-4">
      <div class="card border-0 shadow-sm">
        <div class="card-header bg-gradient-consignment text-white">
          <h5 class="mb-0"><i class="fas fa-map"></i> Outlet Status Map</h5>
        </div>
        <div class="card-body">
          <div class="outlet-map" id="outletMap"></div>
          <div class="map-legend mt-3">
            <div class="legend-item"><div class="legend-color healthy"></div><span>Normal Operations</span></div>
            <div class="legend-item"><div class="legend-color low-stock"></div><span>Low Stock</span></div>
            <div class="legend-item"><div class="legend-color pending-transfers"></div><span>Pending Transfers</span></div>
            <div class="legend-item"><div class="legend-color issues"></div><span>Issues/Problems</span></div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Tools & Utilities -->
  <div class="row">
    <div class="col-12">
      <div class="card border-0 shadow-sm">
        <div class="card-header bg-gradient-consignment text-white">
          <h5 class="mb-0"><i class="fas fa-tools"></i> Transfer Tools & Utilities</h5>
        </div>
        <div class="card-body">
          <div class="transfer-tools-grid">
            <button class="transfer-tool-btn" onclick="consignmentHub && consignmentHub.createTransfer ? consignmentHub.createTransfer() : void(0)"><i class="fas fa-plus-circle"></i><span>Create Transfer</span><small>Manual stock transfer between outlets</small></button>
            <button class="transfer-tool-btn" onclick="consignmentHub && consignmentHub.automaticRebalance ? consignmentHub.automaticRebalance() : void(0)"><i class="fas fa-balance-scale"></i><span>Auto Rebalance</span><small>AI-powered stock redistribution</small></button>
            <button class="transfer-tool-btn" onclick="consignmentHub && consignmentHub.bulkTransfers ? consignmentHub.bulkTransfers() : void(0)"><i class="fas fa-layer-group"></i><span>Bulk Transfers</span><small>Create multiple transfers at once</small></button>
            <button class="transfer-tool-btn" onclick="consignmentHub && consignmentHub.stockForecast ? consignmentHub.stockForecast() : void(0)"><i class="fas fa-crystal-ball"></i><span>Stock Forecast</span><small>Predict future stock requirements</small></button>
            <button class="transfer-tool-btn" onclick="consignmentHub && consignmentHub.transferHistory ? consignmentHub.transferHistory() : void(0)"><i class="fas fa-history"></i><span>Transfer History</span><small>View complete transfer records</small></button>
            <button class="transfer-tool-btn" onclick="consignmentHub && consignmentHub.vendSync ? consignmentHub.vendSync() : void(0)"><i class="fas fa-sync"></i><span>Vend Sync</span><small>Sync with Lightspeed POS</small></button>
            <button class="transfer-tool-btn" onclick="consignmentHub && consignmentHub.printLabels ? consignmentHub.printLabels() : void(0)"><i class="fas fa-print"></i><span>Print Labels</span><small>Generate shipping labels</small></button>
            <button class="transfer-tool-btn" onclick="consignmentHub && consignmentHub.reconciliation ? consignmentHub.reconciliation() : void(0)"><i class="fas fa-check-square"></i><span>Reconciliation</span><small>Match physical vs system inventory</small></button>
          </div>
        </div>
      </div>
    </div>
  </div>

</div>

<!-- Include shared Consignment Control Modals -->
<?php
$__modals = $_SERVER['DOCUMENT_ROOT'] . '/modules/consignment-control-modals.php';
if (is_file($__modals)) { require $__modals; }
?>

<!-- Styles (from provided hub) -->
<style>
.bg-gradient-consignment { background: linear-gradient(135deg, #17a2b8 0%, #007bff 100%); }
.consignment-status-grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap:1.5rem; padding:1.5rem; }
.consignment-status-card { display:flex; align-items:center; padding:1.5rem; background:linear-gradient(135deg,#f8fcff 0%,#e8f4fd 100%); border-radius:12px; border:1px solid rgba(23,162,184,.1); transition: all .3s ease; }
.consignment-status-card:hover { transform: translateY(-2px); box-shadow:0 8px 25px rgba(23,162,184,.15); }
.consignment-indicator { width:70px; height:70px; border-radius:50%; display:flex; align-items:center; justify-content:center; margin-right:1.5rem; background:linear-gradient(135deg,#17a2b8 0%,#007bff 100%); position:relative; }
.consignment-indicator.pending { background:linear-gradient(135deg,#ffc107 0%, #fd7e14 100%); }
.consignment-indicator.in-transit { background:linear-gradient(135deg,#007bff 0%, #6610f2 100%); animation:pulse 2s infinite; }
.consignment-indicator.delivered { background:linear-gradient(135deg,#28a745 0%, #20c997 100%); }
.consignment-indicator.issues { background:linear-gradient(135deg,#dc3545 0%, #fd7e14 100%); }
.consignment-icon { font-size:1.8rem; color:#fff; }
.consignment-content h5 { margin-bottom:.75rem; font-weight:600; color:#495057; }
.consignment-details .metric { display:flex; justify-content:space-between; margin-bottom:.5rem; font-size:.9rem; }
.consignment-details .label { color:#6c757d; }
.consignment-details .value { font-weight:600; color:#495057; }
.transfer-tools-grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap:1rem; }
.transfer-tool-btn { display:flex; flex-direction:column; align-items:center; padding:1.25rem; background:linear-gradient(135deg,#f8fcff 0%,#e8f4fd 100%); border:1px solid rgba(23,162,184,.2); border-radius:12px; transition:all .2s ease; cursor:pointer; }
.transfer-tool-btn:hover { transform: translateY(-2px); box-shadow:0 10px 30px rgba(23,162,184,.2); background:linear-gradient(135deg,#17a2b8 0%,#007bff 100%); color:#fff; }
</style>
</script>