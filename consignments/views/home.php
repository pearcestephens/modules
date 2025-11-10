<?php
/**
 * Consignments Module - Home Page
 * Central hub for all consignment operations
 *
 * @package CIS\Consignments
 * @version 3.0.0
 */

declare(strict_types=1);

// Load Consignments bootstrap (shared helpers) and CIS Template
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../lib/CISTemplate.php';

// Get database connection
$pdo = CIS\Base\Database::pdo();

// Load statistics
$stats = [];
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM vend_consignments WHERE status = 'OPEN'");
    $stats['active_transfers'] = $stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(*) FROM vend_consignments WHERE status = 'RECEIVED' AND DATE(received_at) = CURDATE()");
    $stats['completed_today'] = $stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(*) FROM vend_consignments WHERE status = 'SENT'");
    $stats['pending_receive'] = $stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(*) FROM purchase_orders WHERE status = 'OPEN'");
    $stats['active_pos'] = $stmt->fetchColumn();

    // Open transfers created today
    $stmt = $pdo->query("SELECT COUNT(*) FROM vend_consignments WHERE status = 'OPEN' AND DATE(created_at) = CURDATE()");
    $stats['open_today'] = $stmt->fetchColumn();
} catch (Exception $e) {
    $stats = ['active_transfers' => 0, 'completed_today' => 0, 'pending_receive' => 0, 'active_pos' => 0, 'open_today' => 0];
}

// Initialize template
$template = new CISTemplate();
$template->setTitle('Consignments Management');
$template->setBreadcrumbs([
    ['label' => 'Home', 'url' => '/', 'icon' => 'fa-home'],
    ['label' => 'Consignments', 'url' => '/modules/consignments/', 'active' => true]
]);

// Start content capture
$template->startContent();
?>


<!-- Page Header -->
<div class="card mb-4">
    <div class="card-body">
        <h2 class="mb-2"><i class="fas fa-boxes mr-2"></i>Consignments Management</h2>
        <p class="text-muted mb-0">Central hub for all consignment operations, transfers, and inventory management</p>
    </div>
</div>

<!-- Recent Stock Transfers (quick view) -->
<?php
    $recentTransfers = [];
    try { $recentTransfers = getRecentTransfersEnrichedDB(5, 'STOCK'); } catch (Throwable $e) { $recentTransfers = []; }
?>
<?php if (!empty($recentTransfers)): ?>
<div class="card mb-4">
    <div class="card-header bg-light">
        <h5 class="mb-0"><i class="fas fa-clock mr-2"></i>Recent Stock Transfers</h5>
    </div>
    <div class="card-body p-0">
        <div class="list-group list-group-flush">
            <?php foreach ($recentTransfers as $rt):
                $received = (int)($rt['items_received'] ?? 0);
                $total = (int)($rt['item_count_total'] ?? 0);
                $pct = ($total>0) ? max(0, min(100, (int)round(($received/$total)*100))) : 0;
                $pctClass = $pct >= 90 ? 'success' : ($pct >= 50 ? 'warning' : ($pct > 0 ? 'danger' : 'secondary'));
            ?>
            <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center" href="/modules/consignments/stock-transfers/pack.php?id=<?= urlencode((string)($rt['cis_internal_id'] ?? $rt['id'] ?? '')) ?>">
                <div>
                    <div class="font-weight-bold"><?= htmlspecialchars($rt['consignment_number'] ?? '') ?></div>
                    <div class="small text-muted"><?= htmlspecialchars($rt['from_outlet_name'] ?? '-') ?> → <?= htmlspecialchars($rt['to_outlet_name'] ?? '-') ?></div>
                </div>
                <div class="text-right">
                    <div><span class="badge badge-<?= $pctClass ?>"><?= $pct ?>%</span></div>
                    <?php if (!empty($rt['to_outlet_phone'])): ?>
                    <div class="small"><i class="fas fa-phone mr-1"></i><a href="tel:<?= htmlspecialchars($rt['to_outlet_phone']) ?>" onclick="event.stopPropagation();" class="text-decoration-none"><?= htmlspecialchars($rt['to_outlet_phone']) ?></a></div>
                    <?php endif; ?>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="card-footer text-right py-2">
        <a href="/modules/consignments/?route=stock-transfers" class="btn btn-sm btn-outline-secondary">View all</a>
    </div>
    </div>
<?php endif; ?>

<!-- Statistics Cards -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card border-left-primary shadow-sm h-100">
            <div class="card-body">
                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Active Transfers</div>
                <div class="h3 mb-0 font-weight-bold text-gray-800"><?= number_format($stats['active_transfers']) ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-left-success shadow-sm h-100">
            <div class="card-body">
                <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Completed Today</div>
                <div class="h3 mb-0 font-weight-bold text-gray-800"><?= number_format($stats['completed_today']) ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-left-warning shadow-sm h-100">
            <div class="card-body">
                <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Pending Receive</div>
                <div class="h3 mb-0 font-weight-bold text-gray-800"><?= number_format($stats['pending_receive']) ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-left-info shadow-sm h-100">
            <div class="card-body">
                <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Active Purchase Orders</div>
                <div class="h3 mb-0 font-weight-bold text-gray-800"><?= number_format($stats['active_pos']) ?></div>
            </div>
        </div>
    </div>
</div>

<!-- Quick Actions Section -->
<h4 class="mb-3"><i class="fas fa-bolt mr-2"></i>Quick Actions</h4>
<div class="row mb-4">
    <div class="col-md-4 mb-3">
        <div class="card h-100 hover-shadow">
            <div class="card-body">
                <h5 class="card-title"><i class="fas fa-arrow-left-right mr-2 text-primary"></i>Transfer Manager</h5>
                <p class="card-text">Manage stock transfers, create new consignments, and track shipments between outlets.</p>
                <a href="/modules/consignments/?route=transfer-manager" class="btn btn-primary btn-sm">Open Manager</a>
                <span class="badge badge-info ml-2">Most Used</span>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-3">
        <div class="card h-100 hover-shadow">
            <div class="card-body">
                <h5 class="card-title"><i class="fas fa-cart-plus mr-2 text-success"></i>Purchase Orders</h5>
                <p class="card-text">View and manage purchase orders, supplier shipments, and incoming inventory.</p>
                <a href="/modules/consignments/?route=purchase-orders" class="btn btn-success btn-sm">View POs</a>
                <span class="badge badge-success ml-2">Active</span>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-3">
        <div class="card h-100 hover-shadow">
            <div class="card-body">
                <h5 class="card-title"><i class="fas fa-box mr-2 text-info"></i>Stock Transfers</h5>
                <p class="card-text">Browse all stock transfer history, search transfers, and view detailed reports.</p>
                <a href="/modules/consignments/?route=stock-transfers" class="btn btn-info btn-sm">View All</a>
                <?php if (isset($stats['open_today'])): ?>
                  <span class="badge badge-info ml-2" title="Open transfers created today"><?= (int)$stats['open_today'] ?> today</span>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-3">
        <div class="card h-100 hover-shadow">
            <div class="card-body">
                <h5 class="card-title"><i class="fas fa-chart-line mr-2 text-warning"></i>Analytics Dashboard</h5>
                <p class="card-text">Performance tracking, leaderboards, achievements, and security monitoring.</p>
                <a href="/modules/consignments/analytics/" class="btn btn-warning btn-sm">View Analytics</a>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-3">
        <div class="card h-100 hover-shadow">
            <div class="card-body">
                <h5 class="card-title"><i class="fas fa-truck mr-2 text-secondary"></i>Freight Management</h5>
                <p class="card-text">Track freight shipments, manage carriers, and view delivery schedules.</p>
                <a href="/modules/consignments/?route=freight" class="btn btn-secondary btn-sm">Manage Freight</a>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-3">
        <div class="card h-100 hover-shadow">
            <div class="card-body">
                <h5 class="card-title"><i class="fas fa-gauge mr-2 text-dark"></i>Control Panel</h5>
                <p class="card-text">System monitoring, queue status, admin controls, and configuration settings.</p>
                <a href="/modules/consignments/?route=control-panel" class="btn btn-dark btn-sm">Open Panel</a>
            </div>
        </div>
    </div>
</div>

<!-- Additional Tools Section -->
<div class="row">
    <div class="col-md-6 mb-4">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-chart-bar mr-2"></i>Analytics & Performance</h5>
            </div>
            <div class="list-group list-group-flush">
                <a href="/modules/consignments/analytics/performance-dashboard.php" class="list-group-item list-group-item-action">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-tachometer-alt text-primary mr-3 fa-lg"></i>
                        <div>
                            <h6 class="mb-0">Performance Dashboard</h6>
                            <small class="text-muted">Track scanning stats, achievements, and personal bests</small>
                        </div>
                    </div>
                </a>
                <a href="/modules/consignments/analytics/leaderboard.php" class="list-group-item list-group-item-action">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-trophy text-warning mr-3 fa-lg"></i>
                        <div>
                            <h6 class="mb-0">Leaderboard Rankings</h6>
                            <small class="text-muted">See how you rank against colleagues</small>
                        </div>
                    </div>
                </a>
                <a href="/modules/consignments/analytics/security-dashboard.php" class="list-group-item list-group-item-action">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-shield-alt text-danger mr-3 fa-lg"></i>
                        <div>
                            <h6 class="mb-0">Security Dashboard</h6>
                            <small class="text-muted">Monitor suspicious scans and fraud alerts</small>
                        </div>
                    </div>
                </a>
                <a href="/modules/consignments/analytics/" class="list-group-item list-group-item-action">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-vial text-info mr-3 fa-lg"></i>
                        <div>
                            <h6 class="mb-0">Testing Tools</h6>
                            <small class="text-muted">Access system testing and health checks</small>
                        </div>
                    </div>
                </a>
            </div>
        </div>
    </div>

    <div class="col-md-6 mb-4">
        <div class="card">
            <div class="card-header bg-dark text-white">
                <h5 class="mb-0"><i class="fas fa-tools mr-2"></i>System Tools</h5>
            </div>
            <div class="list-group list-group-flush">
                <a href="/modules/consignments/?route=queue-status" class="list-group-item list-group-item-action">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-tasks text-secondary mr-3 fa-lg"></i>
                        <div>
                            <h6 class="mb-0">Queue Status</h6>
                            <small class="text-muted">Monitor background jobs and queue workers</small>
                        </div>
                    </div>
                </a>
                <a href="/modules/consignments/?route=admin-controls" class="list-group-item list-group-item-action">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-cog text-secondary mr-3 fa-lg"></i>
                        <div>
                            <h6 class="mb-0">Admin Controls</h6>
                            <small class="text-muted">System configuration and settings</small>
                        </div>
                    </div>
                </a>
                <a href="/modules/consignments/?route=ai-insights" class="list-group-item list-group-item-action">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-brain text-primary mr-3 fa-lg"></i>
                        <div>
                            <h6 class="mb-0">AI Insights</h6>
                            <small class="text-muted">AI-powered recommendations and analytics</small>
                        </div>
                    </div>
                </a>
                <a href="/modules/consignments/purchase-orders/approvals/dashboard.php" class="list-group-item list-group-item-action">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-check-square text-success mr-3 fa-lg"></i>
                        <div>
                            <h6 class="mb-0">PO Approvals</h6>
                            <small class="text-muted">Review and approve purchase orders</small>
                        </div>
                    </div>
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Footer Info -->
<div class="alert alert-light text-center mt-4">
    <p class="mb-1"><i class="fas fa-info-circle mr-1"></i><strong>Consignments Module v3.0.0</strong> | Last Updated: November 2025</p>
    <p class="mb-0 text-muted small">For support, contact IT Department</p>
</div>

<style>
.border-left-primary { border-left: 4px solid #007bff !important; }
.border-left-success { border-left: 4px solid #28a745 !important; }
.border-left-warning { border-left: 4px solid #ffc107 !important; }
.border-left-info { border-left: 4px solid #17a2b8 !important; }
.hover-shadow { transition: all 0.3s ease; }
.hover-shadow:hover { box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.15) !important; transform: translateY(-2px); }
</style>

<?php
// End content capture and render
$template->endContent();
$template->render();
