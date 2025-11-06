<?php
/**
 * Control Panel - System Monitoring Dashboard
 *
 * Professional admin interface for managing stock transfers.
 * Shows statistics, queue status, and system health.
 *
 * @package CIS\Consignments
 * @version 3.0.0
 */

declare(strict_types=1);

// Page metadata
$pageTitle = 'Transfer System Control Panel';
$breadcrumbs = [
    ['label' => 'Home', 'url' => '/', 'icon' => 'fa-home'],
    ['label' => 'Consignments', 'url' => '/modules/consignments/'],
    ['label' => 'Control Panel', 'url' => '', 'active' => true]
];

// Get database connection
$pdo = CIS\Base\Database::pdo();

// Load system stats
$stats = [
    'open_transfers' => 0,
    'in_transit' => 0,
    'pending_receive' => 0,
    'sync_queue' => 0,
    'total_value' => 0
];

// Open transfers
$stmt = $pdo->query("SELECT COUNT(*) as cnt FROM vend_consignments WHERE state = 'OPEN' AND transfer_category = 'STOCK_TRANSFER'");
if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $stats['open_transfers'] = (int)$row['cnt'];
}

// In transit
$stmt = $pdo->query("SELECT COUNT(*) as cnt FROM vend_consignments WHERE state IN ('SENT', 'RECEIVING') AND transfer_category = 'STOCK_TRANSFER'");
if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $stats['in_transit'] = (int)$row['cnt'];
}

// Pending receive
$stmt = $pdo->query("SELECT COUNT(*) as cnt, SUM(total_cost) as val FROM vend_consignments WHERE state IN ('RECEIVING', 'PARTIAL') AND transfer_category = 'STOCK_TRANSFER'");
if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $stats['pending_receive'] = (int)$row['cnt'];
    $stats['total_value'] = (float)($row['val'] ?? 0);
}

// Sync queue (pending jobs)
$stmt = $pdo->query("SELECT COUNT(*) as cnt FROM vend_consignment_queue WHERE status IN ('pending', 'processing')");
if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $stats['sync_queue'] = (int)$row['cnt'];
}

// Route analytics
$routeStmt = $pdo->query("
    SELECT c.outlet_from as from_outlet,
           c.outlet_to as to_outlet,
           COUNT(*) as transfer_count,
           SUM(c.total_count) as total_items
    FROM vend_consignments c
    WHERE c.transfer_category = 'STOCK_TRANSFER'
    AND c.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY c.outlet_from, c.outlet_to
    ORDER BY transfer_count DESC
    LIMIT 10
");

$routes = [];
while ($row = $routeStmt->fetch(PDO::FETCH_ASSOC)) {
    $routes[] = $row;
}

// Start output buffering
ob_start();
?>

<style>
/* Utility classes for control panel */
.h2 { font-size: 1.75rem; font-weight: 600; color: var(--cis-gray-900, #1a202c); }
.mb-0 { margin-bottom: 0 !important; }
.mb-1 { margin-bottom: 0.25rem !important; }
.mb-4 { margin-bottom: 1.5rem !important; }
.me-2 { margin-right: 0.5rem !important; }
.text-primary { color: var(--cis-primary, #0066cc) !important; }
.text-muted { color: var(--cis-gray-600, #6c757d) !important; }
.d-flex { display: flex !important; }
.justify-content-between { justify-content: space-between !important; }
.align-items-center { align-items: center !important; }

/* Stats cards */
.stat-card {
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
}

.stat-value {
    font-size: 2rem;
    font-weight: 700;
    color: #1a202c;
    margin: 0;
}

.stat-label {
    font-size: 0.875rem;
    color: #6c757d;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
</style>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h2 mb-1">
            <i class="fas fa-exchange-alt text-primary me-2"></i>
            Transfer System Control Panel
        </h1>
        <p class="text-muted mb-0">Manage stock transfers and sync operations</p>
    </div>
</div>

<!-- Stats Cards -->
<div class="row">
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-label">Open Transfers</div>
            <p class="stat-value"><?= number_format($stats['open_transfers']) ?></p>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-label">In Transit</div>
            <p class="stat-value"><?= number_format($stats['in_transit']) ?></p>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-label">Pending Receive</div>
            <p class="stat-value"><?= number_format($stats['pending_receive']) ?></p>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-label">Sync Queue</div>
            <p class="stat-value"><?= number_format($stats['sync_queue']) ?></p>
        </div>
    </div>
</div>

<!-- Top Routes -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">Top Transfer Routes (Last 30 Days)</h5>
    </div>
    <div class="card-body">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th>From Outlet</th>
                    <th>To Outlet</th>
                    <th class="text-end">Transfers</th>
                    <th class="text-end">Total Items</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($routes) > 0): ?>
                    <?php foreach ($routes as $route): ?>
                        <tr>
                            <td><?= htmlspecialchars($route['from_outlet']) ?></td>
                            <td><?= htmlspecialchars($route['to_outlet']) ?></td>
                            <td class="text-end"><?= number_format($route['transfer_count']) ?></td>
                            <td class="text-end"><?= number_format($route['total_items']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4" class="text-center text-muted">No transfer data available</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
// Get buffered content
$content = ob_get_clean();

// Include BASE dashboard layout
require_once dirname(dirname(__DIR__)) . '/base/_templates/layouts/dashboard.php';
