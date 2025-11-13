<?php
/**
 * Consignments Module - Home Page
 * Central hub for all consignment operations
 *
 * @package CIS\Consignments
 * @version 3.0.0
 */

declare(strict_types=1);

// Load Consignments bootstrap
require_once __DIR__ . '/../bootstrap.php';

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

// Load recent transfers
$recentTransfers = [];
try {
    $recentTransfers = getRecentTransfersEnrichedDB(5, 'STOCK');
} catch (Throwable $e) {
    $recentTransfers = [];
}

// ===== MODERN THEME SETUP (Bootstrap 5) =====
$pageTitle = 'Consignments Management';
$breadcrumbs = [
    ['label' => 'Home', 'url' => '/', 'icon' => 'fa-home'],
    ['label' => 'Consignments', 'active' => true]
];

$pageCSS = [
    'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css',
    '/modules/admin-ui/css/cms-design-system.css'
];

// Start output buffering for content
ob_start();
?>

<!-- Page Header with Gradient -->
<div class="page-header fade-in mb-4">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
        <div>
            <h1 class="page-title mb-2">
                <i class="bi bi-boxes"></i> Consignments Hub
            </h1>
            <p class="page-subtitle text-muted mb-0">
                Central command for transfers, purchase orders, and inventory management
            </p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="/modules/consignments/?route=transfer-manager" class="btn btn-primary">
                <i class="bi bi-plus-circle"></i> New Transfer
            </a>
            <a href="/modules/consignments/analytics/" class="btn btn-outline-primary">
                <i class="bi bi-graph-up"></i> Analytics
            </a>
        </div>
    </div>
</div>

<!-- Statistics Cards -->
<?php if (!empty($recentTransfers)): ?>
<div class="card shadow-sm mb-4 fade-in" style="animation-delay: 0.1s">
    <div class="card-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
        <div class="d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="bi bi-clock-history"></i> Recent Stock Transfers</h5>
            <a href="/modules/consignments/?route=stock-transfers" class="btn btn-sm btn-light">
                <i class="bi bi-list-ul"></i> View All
            </a>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="bg-light">
                    <tr>
                        <th class="border-0">Consignment</th>
                        <th class="border-0">Route</th>
                        <th class="border-0">Progress</th>
                        <th class="border-0">Contact</th>
                        <th class="border-0 text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentTransfers as $rt):
                        $received = (int)($rt['items_received'] ?? 0);
                        $total = (int)($rt['item_count_total'] ?? 0);
                        $pct = ($total>0) ? max(0, min(100, (int)round(($received/$total)*100))) : 0;
                        $badgeClass = $pct >= 90 ? 'success' : ($pct >= 50 ? 'warning' : ($pct > 0 ? 'danger' : 'secondary'));
                    ?>
                    <tr>
                        <td>
                            <div class="fw-semibold"><?= htmlspecialchars($rt['consignment_number'] ?? '') ?></div>
                        </td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge bg-light text-dark"><?= htmlspecialchars($rt['from_outlet_name'] ?? '-') ?></span>
                                <i class="bi bi-arrow-right text-muted"></i>
                                <span class="badge bg-light text-dark"><?= htmlspecialchars($rt['to_outlet_name'] ?? '-') ?></span>
                            </div>
                        </td>
                        <td>
                            <div class="d-flex align-items-center gap-2" style="min-width: 150px;">
                                <span class="badge bg-<?= $badgeClass ?>"><?= $pct ?>%</span>
                                <div class="progress flex-fill" style="height: 8px;">
                                    <div class="progress-bar bg-<?= $badgeClass ?>" style="width: <?= $pct ?>%;" role="progressbar"></div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <?php if (!empty($rt['to_outlet_phone'])): ?>
                            <a href="tel:<?= htmlspecialchars($rt['to_outlet_phone']) ?>" class="text-decoration-none">
                                <i class="bi bi-telephone text-primary"></i>
                                <span class="ms-1"><?= htmlspecialchars($rt['to_outlet_phone']) ?></span>
                            </a>
                            <?php else: ?>
                            <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <a href="/modules/consignments/stock-transfers/pack.php?id=<?= urlencode((string)($rt['cis_internal_id'] ?? $rt['id'] ?? '')) ?>" class="btn btn-sm btn-primary">
                                <i class="bi bi-box-arrow-up-right"></i> View
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="row g-4 mb-4 fade-in">
    <div class="col-md-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
            <div class="card-body text-white">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <div class="text-white-50 small mb-1">Active Transfers</div>
                        <h2 class="mb-0 fw-bold"><?= number_format($stats['active_transfers']) ?></h2>
                    </div>
                    <div class="bg-white bg-opacity-25 rounded-3 p-3">
                        <i class="bi bi-arrow-left-right fs-3"></i>
                    </div>
                </div>
                <div class="d-flex align-items-center text-white-50 small">
                    <i class="bi bi-clock me-1"></i> In progress
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);">
            <div class="card-body text-white">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <div class="text-white-50 small mb-1">Completed Today</div>
                        <h2 class="mb-0 fw-bold"><?= number_format($stats['completed_today']) ?></h2>
                    </div>
                    <div class="bg-white bg-opacity-25 rounded-3 p-3">
                        <i class="bi bi-check-circle fs-3"></i>
                    </div>
                </div>
                <div class="d-flex align-items-center text-white-50 small">
                    <i class="bi bi-arrow-up me-1"></i> Finished
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
            <div class="card-body text-white">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <div class="text-white-50 small mb-1">Pending Receive</div>
                        <h2 class="mb-0 fw-bold"><?= number_format($stats['pending_receive']) ?></h2>
                    </div>
                    <div class="bg-white bg-opacity-25 rounded-3 p-3">
                        <i class="bi bi-clock-history fs-3"></i>
                    </div>
                </div>
                <div class="d-flex align-items-center text-white-50 small">
                    <i class="bi bi-hourglass-split me-1"></i> Awaiting
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
            <div class="card-body text-white">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <div class="text-white-50 small mb-1">Active POs</div>
                        <h2 class="mb-0 fw-bold"><?= number_format($stats['active_pos']) ?></h2>
                    </div>
                    <div class="bg-white bg-opacity-25 rounded-3 p-3">
                        <i class="bi bi-cart-plus fs-3"></i>
                    </div>
                </div>
                <div class="d-flex align-items-center text-white-50 small">
                    <i class="bi bi-bag me-1"></i> Purchase orders
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Recent Transfers Table -->

<!-- Quick Actions Section -->
<h3 class="mb-3 fw-bold" style="color: #1e293b;">
    <i class="bi bi-lightning-charge-fill text-warning"></i> Quick Actions
</h3>

<div class="row g-4 mb-4 fade-in" style="animation-delay: 0.2s">
    <!-- Transfer Manager -->
    <div class="col-md-6 col-lg-4">
        <div class="card h-100 border-0 shadow-sm hover-lift">
            <div class="card-body">
                <div class="d-flex align-items-start mb-3">
                    <div class="bg-primary bg-opacity-10 rounded-3 p-3 me-3">
                        <i class="bi bi-arrow-left-right fs-3 text-primary"></i>
                    </div>
                    <div class="flex-fill">
                        <h5 class="card-title mb-1">Transfer Manager</h5>
                        <span class="badge bg-info">Most Used</span>
                    </div>
                </div>
                <p class="text-muted small mb-3">
                    Manage stock transfers, create new consignments, and track shipments between outlets.
                </p>
                <a href="/modules/consignments/?route=transfer-manager" class="btn btn-primary w-100">
                    <i class="bi bi-arrow-right-circle"></i> Open Manager
                </a>
            </div>
        </div>
    </div>
    <!-- Purchase Orders -->
    <div class="col-md-6 col-lg-4">
        <div class="card h-100 border-0 shadow-sm hover-lift">
            <div class="card-body">
                <div class="d-flex align-items-start mb-3">
                    <div class="bg-success bg-opacity-10 rounded-3 p-3 me-3">
                        <i class="bi bi-cart-plus fs-3 text-success"></i>
                    </div>
                    <div class="flex-fill">
                        <h5 class="card-title mb-1">Purchase Orders</h5>
                        <span class="badge bg-success">Active</span>
                    </div>
                </div>
                <p class="text-muted small mb-3">
                    View and manage purchase orders, supplier shipments, and incoming inventory.
                </p>
                <a href="/modules/consignments/?route=purchase-orders" class="btn btn-success w-100">
                    <i class="bi bi-list-check"></i> View POs
                </a>
            </div>
        </div>
    </div>
    <!-- Stock Transfers -->
    <div class="col-md-6 col-lg-4">
        <div class="card h-100 border-0 shadow-sm hover-lift">
            <div class="card-body">
                <div class="d-flex align-items-start mb-3">
                    <div class="bg-info bg-opacity-10 rounded-3 p-3 me-3">
                        <i class="bi bi-box-seam fs-3 text-info"></i>
                    </div>
                    <div class="flex-fill">
                        <h5 class="card-title mb-1">Stock Transfers</h5>
                        <?php if (isset($stats['open_today']) && $stats['open_today'] > 0): ?>
                        <span class="badge bg-info"><?= (int)$stats['open_today'] ?> today</span>
                        <?php endif; ?>
                    </div>
                </div>
                <p class="text-muted small mb-3">
                    Browse all stock transfer history, search transfers, and view detailed reports.
                </p>
                <a href="/modules/consignments/?route=stock-transfers" class="btn btn-info w-100">
                    <i class="bi bi-list-ul"></i> View All
                </a>
            </div>
        </div>
    </div>
    <!-- Analytics Dashboard -->
    <div class="col-md-6 col-lg-4">
        <div class="card h-100 border-0 shadow-sm hover-lift">
            <div class="card-body">
                <div class="d-flex align-items-start mb-3">
                    <div class="bg-warning bg-opacity-10 rounded-3 p-3 me-3">
                        <i class="bi bi-graph-up-arrow fs-3 text-warning"></i>
                    </div>
                    <div>
                        <h5 class="card-title mb-0">Analytics Dashboard</h5>
                    </div>
                </div>
                <p class="text-muted small mb-3">
                    Performance tracking, leaderboards, achievements, and security monitoring.
                </p>
                <a href="/modules/consignments/analytics/" class="btn btn-warning w-100">
                    <i class="bi bi-bar-chart"></i> View Analytics
                </a>
            </div>
        </div>
    </div>
    <!-- Freight Management -->
    <div class="col-md-6 col-lg-4">
        <div class="card h-100 border-0 shadow-sm hover-lift">
            <div class="card-body">
                <div class="d-flex align-items-start mb-3">
                    <div class="bg-secondary bg-opacity-10 rounded-3 p-3 me-3">
                        <i class="bi bi-truck fs-3 text-secondary"></i>
                    </div>
                    <div>
                        <h5 class="card-title mb-0">Freight Management</h5>
                    </div>
                </div>
                <p class="text-muted small mb-3">
                    Track freight shipments, manage carriers, and view delivery schedules.
                </p>
                <a href="/modules/consignments/?route=freight" class="btn btn-outline-secondary w-100">
                    <i class="bi bi-truck-front"></i> Manage Freight
                </a>
            </div>
        </div>
    </div>
    <!-- Control Panel -->
    <div class="col-md-6 col-lg-4">
        <div class="card h-100 border-0 shadow-sm hover-lift">
            <div class="card-body">
                <div class="d-flex align-items-start mb-3">
                    <div class="bg-dark bg-opacity-10 rounded-3 p-3 me-3">
                        <i class="bi bi-sliders fs-3 text-dark"></i>
                    </div>
                    <div>
                        <h5 class="card-title mb-0">Control Panel</h5>
                    </div>
                </div>
                <p class="text-muted small mb-3">
                    System monitoring, queue status, admin controls, and configuration settings.
                </p>
                <a href="/modules/consignments/?route=control-panel" class="btn btn-outline-dark w-100">
                    <i class="bi bi-gear"></i> Open Panel
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Additional Tools Section -->
<div class="row">
    <div class="col-md-6 mb-4">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="bi bi-bar-chart me-2"></i>Analytics & Performance</h5>
            </div>
            <div class="list-group list-group-flush">
                <a href="/modules/consignments/analytics/performance-dashboard.php" class="list-group-item list-group-item-action">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-speedometer2 text-primary me-3 fs-4"></i>
                        <div>
                            <h6 class="mb-0">Performance Dashboard</h6>
                            <small class="text-muted">Track scanning stats, achievements, and personal bests</small>
                        </div>
                    </div>
                </a>
                <a href="/modules/consignments/analytics/leaderboard.php" class="list-group-item list-group-item-action">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-trophy text-warning me-3 fs-4"></i>
                        <div>
                            <h6 class="mb-0">Leaderboard Rankings</h6>
                            <small class="text-muted">See how you rank against colleagues</small>
                        </div>
                    </div>
                </a>
                <a href="/modules/consignments/analytics/security-dashboard.php" class="list-group-item list-group-item-action">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-shield-check text-danger me-3 fs-4"></i>
                        <div>
                            <h6 class="mb-0">Security Dashboard</h6>
                            <small class="text-muted">Monitor suspicious scans and fraud alerts</small>
                        </div>
                    </div>
                </a>
                <a href="/modules/consignments/analytics/" class="list-group-item list-group-item-action">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-tools text-info me-3 fs-4"></i>
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
                <h5 class="mb-0"><i class="bi bi-tools me-2"></i>System Tools</h5>
            </div>
            <div class="list-group list-group-flush">
                <a href="/modules/consignments/?route=queue-status" class="list-group-item list-group-item-action">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-list-task text-secondary me-3 fs-4"></i>
                        <div>
                            <h6 class="mb-0">Queue Status</h6>
                            <small class="text-muted">Monitor background jobs and queue workers</small>
                        </div>
                    </div>
                </a>
                <a href="/modules/consignments/?route=admin-controls" class="list-group-item list-group-item-action">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-gear text-secondary me-3 fs-4"></i>
                        <div>
                            <h6 class="mb-0">Admin Controls</h6>
                            <small class="text-muted">System configuration and settings</small>
                        </div>
                    </div>
                </a>
                <a href="/modules/consignments/?route=ai-insights" class="list-group-item list-group-item-action">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-robot text-primary me-3 fs-4"></i>
                        <div>
                            <h6 class="mb-0">AI Insights</h6>
                            <small class="text-muted">AI-powered recommendations and analytics</small>
                        </div>
                    </div>
                </a>
                <a href="/modules/consignments/purchase-orders/approvals/dashboard.php" class="list-group-item list-group-item-action">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-check-square text-success me-3 fs-4"></i>
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
    <p class="mb-1"><i class="bi bi-info-circle me-1"></i><strong>Consignments Module v3.0.0</strong> | Last Updated: November 2025</p>
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
// Capture content
$content = ob_get_clean();

// Load the Modern Theme (Bootstrap 5)
require_once __DIR__ . '/../../base/templates/themes/modern/layouts/dashboard.php';
