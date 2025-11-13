<?php
/**
 * Consignments Module - Purchase Orders Dashboard
 *
 * @package CIS\Consignments
 * @version 5.0.0 - Bootstrap 5 + Modern Theme
 */

declare(strict_types=1);

// Prepare data
$pdo = CIS\Base\Database::pdo();

$stmt = $pdo->query(<<<SQL
    SELECT
        c.id,
        COALESCE(c.vend_number, c.public_id) as name,
        c.supplier_id as source_outlet_id,
        c.outlet_to as destination_outlet,
        c.state,
        c.created_at,
        c.total_count,
        c.total_cost,
        s.name as supplier_name
    FROM vend_consignments c
    LEFT JOIN vend_suppliers s ON c.supplier_id = s.id
    WHERE c.transfer_category = 'PURCHASE_ORDER'
    ORDER BY c.created_at DESC
    LIMIT 100
SQL);

$purchaseOrders = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

// Calculate stats
$totalOrders = count($purchaseOrders);
$totalCost = array_sum(array_column($purchaseOrders, 'total_cost'));
$totalItems = array_sum(array_column($purchaseOrders, 'total_count'));
$receivedCount = count(array_filter($purchaseOrders, fn($po) => $po['state'] === 'RECEIVED'));
$pendingCount = $totalOrders - $receivedCount;

// Start content capture
ob_start();
?>

<!-- Page Header with Gradient -->
<div class="page-header fade-in mb-4">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
        <div>
            <h1 class="page-title mb-2">
                <i class="bi bi-cart-check"></i> Purchase Orders
            </h1>
            <p class="page-subtitle text-muted mb-0">
                Manage incoming orders from suppliers
            </p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <button class="btn btn-outline-primary" id="btnRefresh">
                <i class="bi bi-arrow-clockwise"></i> Refresh
            </button>
            <a href="/modules/consignments/purchase-orders/create.php" class="btn btn-success">
                <i class="bi bi-plus-circle"></i> Create Purchase Order
            </a>
        </div>
    </div>
</div>

<!-- Stats Cards Row -->
<div class="row g-4 mb-4">
    <!-- Total Orders -->
    <div class="col-md-3">
        <div class="card gradient-card-purple shadow-sm h-100 fade-in" style="animation-delay: 0.1s">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <div class="stat-icon bg-white bg-opacity-25 rounded-circle p-3">
                        <i class="bi bi-cart-check fs-4 text-white"></i>
                    </div>
                    <div class="text-end">
                        <div class="stat-value text-white"><?= number_format($totalOrders) ?></div>
                        <div class="stat-label text-white-50">Total Orders</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Pending Orders -->
    <div class="col-md-3">
        <div class="card gradient-card-warning shadow-sm h-100 fade-in" style="animation-delay: 0.2s">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <div class="stat-icon bg-white bg-opacity-25 rounded-circle p-3">
                        <i class="bi bi-clock-history fs-4 text-white"></i>
                    </div>
                    <div class="text-end">
                        <div class="stat-value text-white"><?= number_format($pendingCount) ?></div>
                        <div class="stat-label text-white-50">Pending</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Received Orders -->
    <div class="col-md-3">
        <div class="card gradient-card-success shadow-sm h-100 fade-in" style="animation-delay: 0.3s">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <div class="stat-icon bg-white bg-opacity-25 rounded-circle p-3">
                        <i class="bi bi-check-circle fs-4 text-white"></i>
                    </div>
                    <div class="text-end">
                        <div class="stat-value text-white"><?= number_format($receivedCount) ?></div>
                        <div class="stat-label text-white-50">Received</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Total Value -->
    <div class="col-md-3">
        <div class="card gradient-card-blue shadow-sm h-100 fade-in" style="animation-delay: 0.4s">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <div class="stat-icon bg-white bg-opacity-25 rounded-circle p-3">
                        <i class="bi bi-currency-dollar fs-4 text-white"></i>
                    </div>
                    <div class="text-end">
                        <div class="stat-value text-white">$<?= number_format($totalCost, 0) ?></div>
                        <div class="stat-label text-white-50">Total Value</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Purchase Orders Table -->
<div class="card shadow-sm fade-in" style="animation-delay: 0.5s">
    <div class="card-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
        <div class="d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="bi bi-table"></i> Purchase Orders</h5>
            <span class="badge bg-white text-primary"><?= number_format($totalOrders) ?></span>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0" id="poTable">
                <thead class="bg-light">
                    <tr>
                        <th class="border-0">PO Number</th>
                        <th class="border-0">Supplier</th>
                        <th class="border-0">Destination</th>
                        <th class="border-0">Status</th>
                        <th class="border-0">Items</th>
                        <th class="border-0">Total Cost</th>
                        <th class="border-0">Created</th>
                        <th class="border-0 text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (count($purchaseOrders) > 0): ?>
                        <?php foreach ($purchaseOrders as $po): ?>
                            <tr style="cursor: pointer;" data-po-id="<?= $po['id'] ?>">
                                <td>
                                    <strong class="text-primary">
                                        <?= htmlspecialchars($po['name'] ?? $po['id']) ?>
                                    </strong>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <i class="bi bi-building me-2 text-muted"></i>
                                        <?= htmlspecialchars($po['supplier_name'] ?? 'Unknown') ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <i class="bi bi-geo-alt me-2 text-muted"></i>
                                        <?= htmlspecialchars($po['destination_outlet']) ?>
                                    </div>
                                </td>
                                <td>
                                    <?php
                                    $state = $po['state'];
                                    $badgeClass = match($state) {
                                        'RECEIVED' => 'bg-success',
                                        'SENT' => 'bg-warning text-dark',
                                        'OPEN' => 'bg-info text-dark',
                                        default => 'bg-secondary'
                                    };
                                    ?>
                                    <span class="badge <?= $badgeClass ?>">
                                        <?= htmlspecialchars($state) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark">
                                        <?= number_format((int)$po['total_count']) ?> items
                                    </span>
                                </td>
                                <td>
                                    <strong class="text-success">
                                        $<?= number_format((float)$po['total_cost'], 2) ?>
                                    </strong>
                                </td>
                                <td>
                                    <small class="text-muted">
                                        <?= date('M d, Y H:i', strtotime($po['created_at'])) ?>
                                    </small>
                                </td>
                                <td class="text-end">
                                    <a href="/modules/consignments/purchase-orders/view.php?id=<?= $po['id'] ?>"
                                       class="btn btn-sm btn-primary">
                                        <i class="bi bi-eye"></i> View
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="text-center py-5">
                                <div class="text-muted">
                                    <i class="bi bi-inbox display-4 d-block mb-3"></i>
                                    <h5>No Purchase Orders Found</h5>
                                    <p>Create your first purchase order to get started</p>
                                    <a href="/modules/consignments/purchase-orders/create.php" class="btn btn-success mt-2">
                                        <i class="bi bi-plus-circle"></i> Create Purchase Order
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
</div>

<style>
/* Gradient Cards */
.gradient-card-purple {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border: none;
}

.gradient-card-warning {
    background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
    border: none;
}

.gradient-card-success {
    background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
    border: none;
}

.gradient-card-blue {
    background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
    border: none;
}

/* Stats */
.stat-value {
    font-size: 2rem;
    font-weight: 700;
    line-height: 1;
}

.stat-label {
    font-size: 0.875rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    font-weight: 500;
}

.stat-icon {
    width: 60px;
    height: 60px;
    display: flex;
    align-items: center;
    justify-content: center;
}

/* Animations */
.fade-in {
    animation: fadeInUp 0.6s ease-out;
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Table Hover Effect */
.table-hover tbody tr:hover {
    background: #f9fafb;
    transform: translateX(2px);
    transition: all 0.2s ease;
}

/* DataTables Integration */
.dataTables_wrapper .dataTables_length select,
.dataTables_wrapper .dataTables_filter input {
    border: 1px solid #dee2e6;
    border-radius: 0.375rem;
    padding: 0.375rem 0.75rem;
}

.dataTables_wrapper .dataTables_paginate .paginate_button.current {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
    border-color: #667eea !important;
    color: white !important;
}
</style>

<script>
// Initialize DataTables
$(document).ready(function() {
    if ($.fn.DataTable && $('#poTable tbody tr').length > 1) {
        $('#poTable').DataTable({
            order: [[6, 'desc']], // Sort by created date
            pageLength: 25,
            responsive: true,
            language: {
                search: "_INPUT_",
                searchPlaceholder: "Search purchase orders..."
            }
        });
    }

    // Refresh button
    $('#btnRefresh').on('click', function() {
        location.reload();
    });

    // Row click to view (except on action buttons)
    $('#poTable tbody').on('click', 'tr', function(e) {
        if (!$(e.target).closest('a, button').length) {
            const poId = $(this).data('po-id');
            if (poId) {
                window.location.href = `/modules/consignments/purchase-orders/view.php?id=${poId}`;
            }
        }
    });
});
</script>

<?php
// Capture content
$content = ob_get_clean();

// Render using BASE framework
render('base', $content, [
    'pageTitle' => 'Purchase Orders',
    'pageSubtitle' => 'Manage incoming orders from suppliers',
    'breadcrumbs' => [
        ['title' => 'Consignments', 'url' => '/modules/consignments/'],
        ['title' => 'Purchase Orders', 'url' => null]
    ],
    'headerButtons' => [
        [
            'text' => 'Create Purchase Order',
            'url' => '/modules/consignments/purchase-orders/create.php',
            'class' => 'btn-success',
            'icon' => 'bi-plus-circle'
        ]
    ],
    'styles' => [
        'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css',
        '/modules/admin-ui/css/cms-design-system.css',
        '/modules/shared/css/tokens.css',
        'https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css'
    ],
    'scripts' => [
        'https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js',
        'https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js'
    ]
]);
?>
