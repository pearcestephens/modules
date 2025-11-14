<?php
/**
 * Consignments Module - Purchase Orders
 *
 * @package CIS\Consignments
 * @version 3.0.0
 */

declare(strict_types=1);

// Load CIS Template
require_once __DIR__ . '/../lib/CISTemplate.php';

// Initialize template
$template = new CISTemplate();
$template->setTitle('Purchase Orders');
$template->setBreadcrumbs([
    ['label' => 'Home', 'url' => '/', 'icon' => 'fa-home'],
    ['label' => 'Consignments', 'url' => '/modules/consignments/'],
    ['label' => 'Purchase Orders', 'url' => '/modules/consignments/?route=purchase-orders', 'active' => true]
]);

// Start content capture
$template->startContent();
?>

<div class="container-fluid">
    <div class="card mb-4">
        <div class="card-body">
            <h2 class="mb-0"><i class="fas fa-cart-shopping mr-2"></i>Purchase Orders</h2>
        </div>
    </div>

/**
declare(strict_types=1);

// Load CIS Template
require_once __DIR__ . '/../lib/CISTemplate.php';

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

// Initialize template
$template = new CISTemplate();
$template->setTitle('Purchase Orders');
$template->setBreadcrumbs([
    ['label' => 'Home', 'url' => '/', 'icon' => 'fa-home'],
    ['label' => 'Consignments', 'url' => '/modules/consignments/'],
    ['label' => 'Purchase Orders', 'url' => '/modules/consignments/?route=purchase-orders', 'active' => true]
]);

// Start content capture
$template->startContent();
?>

<div class="container-fluid">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div>
      <h2 class="mb-0"><i class="fas fa-shopping-cart mr-2"></i>Purchase Orders</h2>
      <div class="text-muted small">Manage incoming orders from suppliers</div>
    </div>
    <div class="left-actions">
      <a href="/modules/consignments/purchase-orders/create.php" class="btn btn-left-solid-pill btn-primary">
        <i class="fas fa-plus mr-2"></i> Create Purchase Order
      </a>
    </div>
  </div>
    <div>
        <a href="/modules/consignments/purchase-orders/create.php" class="btn btn-success">
<!-- (legacy header replaced by compact header above) -->
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

<script>
    // Safe init if DataTables is available
    (function($){
        $(function(){ if ($.fn.DataTable) { $('#poTable').DataTable({ order:[[6,'desc']], pageLength:25, responsive:true }); } });
    })(jQuery);
</script>

</div>

<?php
// End content capture and render
$template->endContent();
$template->render();
