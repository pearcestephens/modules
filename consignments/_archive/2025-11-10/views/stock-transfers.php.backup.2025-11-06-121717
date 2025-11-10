<?php
/**
 * Stock Transfers - List View
 *
 * Browse and manage inter-outlet stock transfers.
 *
 * @package CIS\Consignments
 * @version 3.0.0
 */

declare(strict_types=1);

// Page metadata
$pageTitle = 'Stock Transfers';
$breadcrumbs = [
    ['label' => 'Home', 'url' => '/', 'icon' => 'fa-home'],
    ['label' => 'Consignments', 'url' => '/modules/consignments/'],
    ['label' => 'Stock Transfers', 'url' => '', 'active' => true]
];

// Get database connection
$pdo = CIS\Base\Database::pdo();

// Load recent stock transfers
$stmt = $pdo->query("
    SELECT
        c.id,
        COALESCE(c.vend_number, c.public_id) as name,
        c.outlet_from,
        c.outlet_to,
        c.state,
        c.created_at,
        c.total_count,
        c.total_cost
    FROM vend_consignments c
    WHERE c.transfer_category = 'STOCK_TRANSFER'
    ORDER BY c.created_at DESC
    LIMIT 100
");

$transfers = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $transfers[] = $row;
}

// Start output buffering
ob_start();
?>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h2 mb-1">
            <i class="fas fa-exchange-alt text-primary me-2"></i>
            Stock Transfers
        </h1>
        <p class="text-muted mb-0">Manage inter-outlet inventory transfers</p>
    </div>
    <div>
        <a href="/modules/consignments/?endpoint=transfer-manager" class="btn btn-success">
            <i class="fas fa-plus me-2"></i>
            New Transfer
        </a>
    </div>
</div>

<!-- Transfers Table -->
<div class="card">
    <div class="card-body">
        <table class="table table-hover" id="transfersTable">
            <thead>
                <tr>
                    <th>Transfer #</th>
                    <th>From Outlet</th>
                    <th>To Outlet</th>
                    <th>Status</th>
                    <th>Items</th>
                    <th>Total Value</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($transfers) > 0): ?>
                    <?php foreach ($transfers as $transfer): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($transfer['name'] ?? $transfer['id']) ?></strong></td>
                            <td><?= htmlspecialchars($transfer['outlet_from']) ?></td>
                            <td><?= htmlspecialchars($transfer['outlet_to']) ?></td>
                            <td>
                                <?php
                                $badgeClass = 'secondary';
                                switch ($transfer['state']) {
                                    case 'RECEIVED':
                                        $badgeClass = 'success';
                                        break;
                                    case 'SENT':
                                    case 'RECEIVING':
                                        $badgeClass = 'warning';
                                        break;
                                    case 'OPEN':
                                        $badgeClass = 'info';
                                        break;
                                }
                                ?>
                                <span class="badge bg-<?= $badgeClass ?>">
                                    <?= htmlspecialchars($transfer['state']) ?>
                                </span>
                            </td>
                            <td><?= number_format($transfer['total_count']) ?></td>
                            <td>$<?= number_format($transfer['total_cost'], 2) ?></td>
                            <td><?= date('Y-m-d H:i', strtotime($transfer['created_at'])) ?></td>
                            <td>
                                <a href="/modules/consignments/stock-transfers/pack.php?id=<?= $transfer['id'] ?>" class="btn btn-sm btn-primary">
                                    <i class="fas fa-box"></i> Pack
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">No stock transfers found</td>
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
        $('#transfersTable').DataTable({
            order: [[6, 'desc']], // Sort by created date desc
            pageLength: 25,
            responsive: true
        });
    });
";

// Include BASE dashboard layout
require_once dirname(dirname(__DIR__)) . '/base/_templates/layouts/dashboard.php';
