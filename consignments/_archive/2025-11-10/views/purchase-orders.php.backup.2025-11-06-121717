<?php
/**
 * Purchase Orders - List View
 *
 * Browse and manage purchase orders from suppliers.
 *
 * @package CIS\Consignments
 * @version 3.0.0
 */

declare(strict_types=1);

// Page metadata
$pageTitle = 'Purchase Orders';
$breadcrumbs = [
    ['label' => 'Home', 'url' => '/', 'icon' => 'fa-home'],
    ['label' => 'Consignments', 'url' => '/modules/consignments/'],
    ['label' => 'Purchase Orders', 'url' => '', 'active' => true]
];

// Get database connection
$pdo = CIS\Base\Database::pdo();

// Load recent purchase orders
$stmt = $pdo->query("
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
");

$purchaseOrders = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $purchaseOrders[] = $row;
}

// Start output buffering
ob_start();
?>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h2 mb-1">
            <i class="fas fa-truck text-primary me-2"></i>
            Purchase Orders
        </h1>
        <p class="text-muted mb-0">Manage incoming orders from suppliers</p>
    </div>
    <div>
        <a href="/modules/consignments/purchase-orders/create.php" class="btn btn-success">
            <i class="fas fa-plus me-2"></i>
            Create Purchase Order
        </a>
    </div>
</div>

<!-- Purchase Orders Table -->
<div class="card">
    <div class="card-body">
        <table class="table table-hover" id="poTable">
            <thead>
                <tr>
                    <th>PO #</th>
                    <th>Supplier</th>
                    <th>Destination</th>
                    <th>Status</th>
                    <th>Items</th>
                    <th>Total Cost</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($purchaseOrders) > 0): ?>
                    <?php foreach ($purchaseOrders as $po): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($po['name'] ?? $po['id']) ?></strong></td>
                            <td><?= htmlspecialchars($po['supplier_name'] ?? 'Unknown') ?></td>
                            <td><?= htmlspecialchars($po['destination_outlet']) ?></td>
                            <td>
                                <span class="badge bg-<?= $po['state'] === 'RECEIVED' ? 'success' : ($po['state'] === 'SENT' ? 'warning' : 'secondary') ?>">
                                    <?= htmlspecialchars($po['state']) ?>
                                </span>
                            </td>
                            <td><?= number_format((int)$po['total_count']) ?></td>
                            <td>$<?= number_format((float)$po['total_cost'], 2) ?></td>
                            <td><?= date('Y-m-d H:i', strtotime($po['created_at'])) ?></td>
                            <td>
                                <a href="/modules/consignments/purchase-orders/view.php?id=<?= $po['id'] ?>" class="btn btn-sm btn-primary">
                                    <i class="fas fa-eye"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">No purchase orders found</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
// Get buffered content
$content = ob_get_clean();

// Add inline script (will be executed after jQuery loads)
$inlineScripts = "
    // Initialize DataTable
    $(document).ready(function() {
        $('#poTable').DataTable({
            order: [[6, 'desc']], // Sort by created date desc
            pageLength: 25,
            responsive: true
        });
    });
";

// Include BASE dashboard layout
require_once dirname(dirname(__DIR__)) . '/base/_templates/layouts/dashboard.php';
