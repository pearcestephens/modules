<?php
/**
 * Consignments Module - Home Page
 *
 * Central hub for all consignment operations
 *
 * @package CIS\Consignments
 * @version 3.0.0
 */

declare(strict_types=1);

// Page metadata
$pageTitle = 'Consignments Management';
$breadcrumbs = [
    ['label' => 'Home', 'url' => '/', 'icon' => 'fa-home'],
    ['label' => 'Consignments', 'url' => '/modules/consignments/', 'active' => true]
];

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
} catch (Exception $e) {
    $stats = ['active_transfers' => 0, 'completed_today' => 0, 'pending_receive' => 0, 'active_pos' => 0];
}

// Start output buffering
ob_start();
?>

<style>
.consignments-home { max-width: 1400px; margin: 0 auto; }
.page-header-box { background: #fff; border: 1px solid #dee2e6; border-radius: 6px; padding: 20px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
.page-header-box h1 { font-size: 24px; font-weight: 600; color: #333; margin: 0 0 8px 0; }
.page-header-box p { color: #6c757d; margin: 0; font-size: 14px; }
.stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px; margin-bottom: 20px; }
.stat-card { background: #fff; border: 1px solid #dee2e6; border-radius: 6px; padding: 18px; border-left: 3px solid #007bff; }
.stat-card.success { border-left-color: #28a745; }
.stat-card.warning { border-left-color: #ffc107; }
.stat-card.info { border-left-color: #17a2b8; }
.stat-value { font-size: 32px; font-weight: 700; color: #333; margin: 8px 0; }
.stat-label { font-size: 12px; color: #6c757d; text-transform: uppercase; font-weight: 600; letter-spacing: 0.5px; }
.quick-actions-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 15px; margin-bottom: 20px; }
.action-card { background: #fff; border: 1px solid #dee2e6; border-radius: 6px; padding: 20px; text-decoration: none; color: inherit; display: block; transition: all 0.2s; }
.action-card:hover { border-color: #007bff; box-shadow: 0 2px 8px rgba(0,123,255,0.15); text-decoration: none; transform: translateY(-2px); }
.action-card h3 { font-size: 16px; font-weight: 600; margin-bottom: 8px; color: #333; }
.action-card p { font-size: 13px; color: #6c757d; margin-bottom: 12px; line-height: 1.5; }
.action-badge { display: inline-block; padding: 3px 10px; border-radius: 3px; font-size: 11px; font-weight: 600; background: #e9ecef; color: #495057; }
.section-title { font-size: 18px; font-weight: 600; color: #333; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 2px solid #dee2e6; }
.links-section { background: #fff; border: 1px solid #dee2e6; border-radius: 6px; padding: 20px; margin-bottom: 20px; }
.link-item { display: flex; align-items: center; padding: 12px; border-bottom: 1px solid #f0f0f0; text-decoration: none; color: inherit; transition: background 0.2s; }
.link-item:hover { background: #f8f9fa; text-decoration: none; }
.link-item:last-child { border-bottom: none; }
.link-icon { width: 36px; height: 36px; border-radius: 4px; display: flex; align-items: center; justify-content: center; margin-right: 12px; font-size: 16px; background: #f8f9fa; color: #495057; }
.link-title { font-weight: 600; color: #333; font-size: 14px; margin-bottom: 2px; }
.link-desc { font-size: 12px; color: #6c757d; }
</style>

<div class="consignments-home">
    <div class="page-header-box">
        <h1><i class="fas fa-boxes me-2"></i>Consignments Management</h1>
        <p>Central hub for all consignment operations, transfers, and inventory management</p>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-label"><i class="fas fa-arrow-left-right me-1"></i>Active Transfers</div>
            <div class="stat-value"><?= number_format($stats['active_transfers']) ?></div>
        </div>
        <div class="stat-card success">
            <div class="stat-label"><i class="fas fa-check-circle me-1"></i>Completed Today</div>
            <div class="stat-value"><?= number_format($stats['completed_today']) ?></div>
        </div>
        <div class="stat-card warning">
            <div class="stat-label"><i class="fas fa-clock me-1"></i>Pending Receive</div>
            <div class="stat-value"><?= number_format($stats['pending_receive']) ?></div>
        </div>
        <div class="stat-card info">
            <div class="stat-label"><i class="fas fa-cart-shopping me-1"></i>Active Purchase Orders</div>
            <div class="stat-value"><?= number_format($stats['active_pos']) ?></div>
        </div>
    </div>

    <div class="section-title"><i class="fas fa-bolt me-2"></i>Quick Actions</div>

    <div class="quick-actions-grid">
        <a href="/modules/consignments/?route=transfer-manager" class="action-card">
            <h3><i class="fas fa-arrow-left-right me-2"></i>Transfer Manager</h3>
            <p>Manage stock transfers, create new consignments, and track shipments between outlets.</p>
            <span class="action-badge">Most Used</span>
        </a>
        <a href="/modules/consignments/?route=purchase-orders" class="action-card">
            <h3><i class="fas fa-cart-plus me-2"></i>Purchase Orders</h3>
            <p>View and manage purchase orders, supplier shipments, and incoming inventory.</p>
            <span class="action-badge">Active</span>
        </a>
        <a href="/modules/consignments/?route=stock-transfers" class="action-card">
            <h3><i class="fas fa-box me-2"></i>Stock Transfers</h3>
            <p>Browse all stock transfer history, search transfers, and view detailed reports.</p>
            <span class="action-badge">View All</span>
        </a>
        <a href="/modules/consignments/analytics/" class="action-card">
            <h3><i class="fas fa-chart-line me-2"></i>Analytics Dashboard</h3>
            <p>Performance tracking, leaderboards, achievements, and security monitoring.</p>
            <span class="action-badge">Analytics</span>
        </a>
        <a href="/modules/consignments/?route=freight" class="action-card">
            <h3><i class="fas fa-truck me-2"></i>Freight Management</h3>
            <p>Track freight shipments, manage carriers, and view delivery schedules.</p>
            <span class="action-badge">Logistics</span>
        </a>
        <a href="/modules/consignments/?route=control-panel" class="action-card">
            <h3><i class="fas fa-gauge me-2"></i>Control Panel</h3>
            <p>System monitoring, queue status, admin controls, and configuration settings.</p>
            <span class="action-badge">Admin</span>
        </a>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="links-section">
                <div class="section-title"><i class="fas fa-chart-bar me-2"></i>Analytics & Performance</div>
                <a href="/modules/consignments/analytics/performance-dashboard.php" class="link-item">
                    <div class="link-icon"><i class="fas fa-tachometer-alt"></i></div>
                    <div>
                        <div class="link-title">Performance Dashboard</div>
                        <div class="link-desc">Track scanning stats, achievements, and personal bests</div>
                    </div>
                </a>
                <a href="/modules/consignments/analytics/leaderboard.php" class="link-item">
                    <div class="link-icon"><i class="fas fa-trophy"></i></div>
                    <div>
                        <div class="link-title">Leaderboard Rankings</div>
                        <div class="link-desc">See how you rank against colleagues</div>
                    </div>
                </a>
                <a href="/modules/consignments/analytics/security-dashboard.php" class="link-item">
                    <div class="link-icon"><i class="fas fa-shield-alt"></i></div>
                    <div>
                        <div class="link-title">Security Dashboard</div>
                        <div class="link-desc">Monitor suspicious scans and fraud alerts</div>
                    </div>
                </a>
                <a href="/modules/consignments/analytics/" class="link-item">
                    <div class="link-icon"><i class="fas fa-vial"></i></div>
                    <div>
                        <div class="link-title">Testing Tools</div>
                        <div class="link-desc">Access system testing and health checks</div>
                    </div>
                </a>
            </div>
        </div>

        <div class="col-md-6">
            <div class="links-section">
                <div class="section-title"><i class="fas fa-tools me-2"></i>System Tools</div>
                <a href="/modules/consignments/?route=queue-status" class="link-item">
                    <div class="link-icon"><i class="fas fa-tasks"></i></div>
                    <div>
                        <div class="link-title">Queue Status</div>
                        <div class="link-desc">Monitor background jobs and queue workers</div>
                    </div>
                </a>
                <a href="/modules/consignments/?route=admin-controls" class="link-item">
                    <div class="link-icon"><i class="fas fa-cog"></i></div>
                    <div>
                        <div class="link-title">Admin Controls</div>
                        <div class="link-desc">System configuration and settings</div>
                    </div>
                </a>
                <a href="/modules/consignments/?route=ai-insights" class="link-item">
                    <div class="link-icon"><i class="fas fa-brain"></i></div>
                    <div>
                        <div class="link-title">AI Insights</div>
                        <div class="link-desc">AI-powered recommendations and analytics</div>
                    </div>
                </a>
                <a href="/modules/consignments/purchase-orders/approvals/dashboard.php" class="link-item">
                    <div class="link-icon"><i class="fas fa-check-square"></i></div>
                    <div>
                        <div class="link-title">PO Approvals</div>
                        <div class="link-desc">Review and approve purchase orders</div>
                    </div>
                </a>
            </div>
        </div>
    </div>

    <div class="text-center mt-4 mb-4" style="color: #6c757d; font-size: 13px;">
        <p class="mb-1"><i class="fas fa-info-circle me-1"></i>Consignments Module v3.0.0 | Last Updated: November 2025</p>
        <p class="mb-0">For support, contact IT Department</p>
    </div>
</div>

<?php
$content = ob_get_clean();
require_once dirname(dirname(__DIR__)) . '/base/_templates/layouts/dashboard.php';
