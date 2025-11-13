<?php
/**
 * Consignments Module - Home Dashboard
 * Uses BASE framework ThemeManager for rendering
 */

declare(strict_types=1);

// Get statistics
$stats = [];
try {
    $stmt = $db->query("SELECT COUNT(*) FROM vend_consignments WHERE status = 'OPEN'");
    $stats['active_transfers'] = $stmt->fetchColumn();

    $stmt = $db->query("SELECT COUNT(*) FROM vend_consignments WHERE status = 'RECEIVED' AND DATE(received_at) = CURDATE()");
    $stats['completed_today'] = $stmt->fetchColumn();

    $stmt = $db->query("SELECT COUNT(*) FROM vend_consignments WHERE status = 'SENT'");
    $stats['pending_receive'] = $stmt->fetchColumn();

    $stmt = $db->query("SELECT COUNT(*) FROM purchase_orders WHERE status = 'OPEN'");
    $stats['active_pos'] = $stmt->fetchColumn() ?: 0;
} catch (Exception $e) {
    $stats = ['active_transfers' => 0, 'completed_today' => 0, 'pending_receive' => 0, 'active_pos' => 0];
}

// Build page content
ob_start();
?>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">Consignments Management</h1>
            <p class="text-muted mb-0">Manage stock transfers, purchase orders, and freight operations</p>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <div class="bg-primary bg-opacity-10 rounded-3 p-3">
                                <i class="fas fa-box text-primary fa-2x"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6 class="text-muted mb-1">Active Transfers</h6>
                            <h2 class="mb-0"><?= $stats['active_transfers'] ?></h2>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <div class="bg-success bg-opacity-10 rounded-3 p-3">
                                <i class="fas fa-check-circle text-success fa-2x"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6 class="text-muted mb-1">Completed Today</h6>
                            <h2 class="mb-0"><?= $stats['completed_today'] ?></h2>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <div class="bg-warning bg-opacity-10 rounded-3 p-3">
                                <i class="fas fa-truck text-warning fa-2x"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6 class="text-muted mb-1">Pending Receipt</h6>
                            <h2 class="mb-0"><?= $stats['pending_receive'] ?></h2>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <div class="bg-info bg-opacity-10 rounded-3 p-3">
                                <i class="fas fa-file-invoice text-info fa-2x"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6 class="text-muted mb-1">Active POs</h6>
                            <h2 class="mb-0"><?= $stats['active_pos'] ?></h2>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="row g-3">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <h5 class="card-title mb-3">
                        <i class="fas fa-exchange-alt text-primary me-2"></i>
                        Stock Transfers
                    </h5>
                    <p class="card-text text-muted">Manage transfers between outlets</p>
                    <div class="d-grid gap-2">
                        <a href="?route=stock-transfers" class="btn btn-primary">
                            <i class="fas fa-list me-2"></i>View Transfers
                        </a>
                        <a href="?route=stock-transfers&action=create" class="btn btn-outline-primary">
                            <i class="fas fa-plus me-2"></i>Create Transfer
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <h5 class="card-title mb-3">
                        <i class="fas fa-file-invoice text-info me-2"></i>
                        Purchase Orders
                    </h5>
                    <p class="card-text text-muted">Manage supplier orders</p>
                    <div class="d-grid gap-2">
                        <a href="?route=purchase-orders" class="btn btn-info">
                            <i class="fas fa-list me-2"></i>View Orders
                        </a>
                        <a href="?route=purchase-orders&action=create" class="btn btn-outline-info">
                            <i class="fas fa-plus me-2"></i>Create Order
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <h5 class="card-title mb-3">
                        <i class="fas fa-truck text-warning me-2"></i>
                        Freight & Shipping
                    </h5>
                    <p class="card-text text-muted">Manage freight operations</p>
                    <div class="d-grid gap-2">
                        <a href="?route=freight" class="btn btn-warning">
                            <i class="fas fa-truck me-2"></i>Freight Management
                        </a>
                        <a href="?route=receiving" class="btn btn-outline-warning">
                            <i class="fas fa-inbox me-2"></i>Receiving</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Additional Tools Row -->
    <div class="row g-3 mt-3">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h6 class="card-title">
                        <i class="fas fa-th-large text-success me-2"></i>
                        Transfer Manager
                    </h6>
                    <p class="card-text small text-muted">Unified view of all transfers</p>
                    <a href="?route=transfer-manager" class="btn btn-sm btn-outline-success">Open</a>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h6 class="card-title">
                        <i class="fas fa-cog text-secondary me-2"></i>
                        Control Panel
                    </h6>
                    <p class="card-text small text-muted">System settings and configuration</p>
                    <a href="?route=control-panel" class="btn btn-sm btn-outline-secondary">Open</a>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h6 class="card-title">
                        <i class="fas fa-chart-line text-info me-2"></i>
                        Queue Status
                    </h6>
                    <p class="card-text small text-muted">Monitor background jobs</p>
                    <a href="?route=queue-status" class="btn btn-sm btn-outline-info">Open</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();

// Render using BASE ThemeManager
render('base', $content, [
    'pageTitle' => 'Consignments Management',
    'breadcrumbs' => [
        ['label' => 'Home', 'url' => '/'],
        ['label' => 'Consignments', 'url' => '/modules/consignments/']
    ]
]);
