<?php
/**
 * Purchase Orders - List Page
 *
 * Main listing page with filters, search, and pagination
 * Uses table.php layout for consistent styling
 *
 * Features:
 * - Server-side pagination (50 per page)
 * - Filters: state, supplier, outlet, date range
 * - Search: public_id, supplier_reference
 * - Actions: View, Edit (DRAFT only), Delete (DRAFT only)
 *
 * @package CIS\Consignments\PurchaseOrders
 * @version 1.0.0
 */

declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/app.php';
require_once __DIR__ . '/../lib/Services/PurchaseOrderService.php';

use CIS\Consignments\Services\PurchaseOrderService;

// Check authentication
if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

// Initialize service
$db = get_db();
$poService = new PurchaseOrderService($db);

// Parse filters from query string
$filters = [
    'state' => $_GET['state'] ?? null,
    'supplier_id' => $_GET['supplier_id'] ?? null,
    'outlet_id' => $_GET['outlet_id'] ?? null,
    'date_from' => $_GET['date_from'] ?? null,
    'date_to' => $_GET['date_to'] ?? null,
    'search' => $_GET['search'] ?? null,
];

// Remove null filters
$filters = array_filter($filters, function($value) {
    return $value !== null && $value !== '';
});

// Pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 50;
$offset = ($page - 1) * $perPage;

// Fetch purchase orders
try {
    $result = $poService->list($filters, $perPage, $offset);
    $purchaseOrders = $result['items'];
    $totalCount = $result['total'];
    $totalPages = (int)ceil($totalCount / $perPage);

    $suppliers = $poService->getSuppliers(); // For filter dropdown
    $outlets = $poService->getOutlets(); // For filter dropdown

} catch (Exception $e) {
    error_log("PO List Error: " . $e->getMessage());
    $purchaseOrders = [];
    $totalCount = 0;
    $totalPages = 0;
    $suppliers = [];
    $outlets = [];
    $errorMessage = "Error loading purchase orders. Please try again.";
}

// State options for filter
$stateOptions = [
    'DRAFT' => 'Draft',
    'OPEN' => 'Open',
    'PENDING_APPROVAL' => 'Pending Approval',
    'APPROVED' => 'Approved',
    'SENT' => 'Sent to Supplier',
    'RECEIVING' => 'Receiving',
    'RECEIVED' => 'Received',
    'COMPLETED' => 'Completed',
    'CANCELLED' => 'Cancelled',
];

// Page metadata
$pageTitle = 'Purchase Orders';
$breadcrumbs = [
    ['title' => 'Home', 'url' => '/'],
    ['title' => 'Consignments', 'url' => '/modules/consignments/'],
    ['title' => 'Purchase Orders', 'url' => null],
];

// Include header
include $_SERVER['DOCUMENT_ROOT'] . '/modules/consignments/shared/blocks/header.php';
?>

<!-- Main Content -->
<div class="container-fluid py-4">

    <!-- Page Header with Actions -->
    <div class="row mb-4">
        <div class="col-md-8">
            <h2 class="mb-0">
                <i class="fas fa-shopping-cart me-2"></i> Purchase Orders
            </h2>
            <p class="text-muted mb-0">Manage supplier purchase orders</p>
        </div>
        <div class="col-md-4 text-end">
            <a href="create.php" class="btn btn-primary">
                <i class="fas fa-plus me-2"></i> Create Purchase Order
            </a>
        </div>
    </div>

    <?php if (isset($errorMessage)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <?= htmlspecialchars($errorMessage) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Filters Card -->
    <div class="card mb-4">
        <div class="card-header bg-light">
            <h5 class="mb-0">
                <i class="fas fa-filter me-2"></i> Filters
                <button type="button" class="btn btn-sm btn-link float-end" id="toggleFilters">
                    <i class="fas fa-chevron-down"></i>
                </button>
            </h5>
        </div>
        <div class="card-body" id="filterPanel">
            <form method="GET" action="index.php" id="filterForm">
                <div class="row g-3">

                    <!-- Search -->
                    <div class="col-md-4">
                        <label for="search" class="form-label">Search</label>
                        <input
                            type="text"
                            class="form-control"
                            id="search"
                            name="search"
                            placeholder="PO ID or Supplier Reference"
                            value="<?= htmlspecialchars($_GET['search'] ?? '') ?>"
                        >
                    </div>

                    <!-- State Filter -->
                    <div class="col-md-3">
                        <label for="state" class="form-label">State</label>
                        <select class="form-select" id="state" name="state">
                            <option value="">All States</option>
                            <?php foreach ($stateOptions as $value => $label): ?>
                                <option
                                    value="<?= $value ?>"
                                    <?= ($filters['state'] ?? '') === $value ? 'selected' : '' ?>
                                >
                                    <?= htmlspecialchars($label) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Supplier Filter -->
                    <div class="col-md-3">
                        <label for="supplier_id" class="form-label">Supplier</label>
                        <select class="form-select" id="supplier_id" name="supplier_id">
                            <option value="">All Suppliers</option>
                            <?php foreach ($suppliers as $supplier): ?>
                                <option
                                    value="<?= htmlspecialchars($supplier->id) ?>"
                                    <?= ($filters['supplier_id'] ?? '') === $supplier->id ? 'selected' : '' ?>
                                >
                                    <?= htmlspecialchars($supplier->name) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Outlet Filter -->
                    <div class="col-md-2">
                        <label for="outlet_id" class="form-label">Outlet</label>
                        <select class="form-select" id="outlet_id" name="outlet_id">
                            <option value="">All Outlets</option>
                            <?php foreach ($outlets as $outlet): ?>
                                <option
                                    value="<?= htmlspecialchars($outlet->id) ?>"
                                    <?= ($filters['outlet_id'] ?? '') === $outlet->id ? 'selected' : '' ?>
                                >
                                    <?= htmlspecialchars($outlet->name) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Date From -->
                    <div class="col-md-3">
                        <label for="date_from" class="form-label">Created From</label>
                        <input
                            type="date"
                            class="form-control"
                            id="date_from"
                            name="date_from"
                            value="<?= htmlspecialchars($_GET['date_from'] ?? '') ?>"
                        >
                    </div>

                    <!-- Date To -->
                    <div class="col-md-3">
                        <label for="date_to" class="form-label">Created To</label>
                        <input
                            type="date"
                            class="form-control"
                            id="date_to"
                            name="date_to"
                            value="<?= htmlspecialchars($_GET['date_to'] ?? '') ?>"
                        >
                    </div>

                    <!-- Filter Buttons -->
                    <div class="col-md-6 d-flex align-items-end gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search me-2"></i> Apply Filters
                        </button>
                        <a href="index.php" class="btn btn-outline-secondary">
                            <i class="fas fa-redo me-2"></i> Reset
                        </a>
                        <div class="ms-auto text-muted small">
                            <?= number_format($totalCount) ?> results
                        </div>
                    </div>

                </div>
            </form>
        </div>
    </div>

    <!-- Purchase Orders Table -->
    <div class="card">
        <div class="card-body">

            <?php if (empty($purchaseOrders)): ?>

                <!-- Empty State -->
                <div class="text-center py-5">
                    <i class="fas fa-shopping-cart fa-4x text-muted mb-3"></i>
                    <h4>No Purchase Orders Found</h4>
                    <p class="text-muted">
                        <?php if (!empty($filters)): ?>
                            Try adjusting your filters or <a href="index.php">clear all filters</a>
                        <?php else: ?>
                            Get started by creating your first purchase order
                        <?php endif; ?>
                    </p>
                    <?php if (empty($filters)): ?>
                        <a href="create.php" class="btn btn-primary mt-3">
                            <i class="fas fa-plus me-2"></i> Create Purchase Order
                        </a>
                    <?php endif; ?>
                </div>

            <?php else: ?>

                <!-- Results Table -->
                <div class="table-responsive">
                    <table class="table table-hover table-striped align-middle">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 120px">PO ID</th>
                                <th>Supplier</th>
                                <th>Outlet</th>
                                <th>Expected Date</th>
                                <th class="text-center">Items</th>
                                <th class="text-end">Total</th>
                                <th class="text-center">State</th>
                                <th class="text-center" style="width: 180px">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($purchaseOrders as $po): ?>
                                <tr>
                                    <!-- PO ID -->
                                    <td>
                                        <a href="view.php?id=<?= $po->id ?>" class="text-decoration-none fw-bold">
                                            <?= htmlspecialchars($po->public_id) ?>
                                        </a>
                                        <?php if (!empty($po->supplier_reference)): ?>
                                            <br>
                                            <small class="text-muted">
                                                Ref: <?= htmlspecialchars($po->supplier_reference) ?>
                                            </small>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Supplier -->
                                    <td>
                                        <?= htmlspecialchars($po->supplier_name) ?>
                                        <?php if (!empty($po->supplier_code)): ?>
                                            <br>
                                            <small class="text-muted">
                                                <?= htmlspecialchars($po->supplier_code) ?>
                                            </small>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Outlet -->
                                    <td><?= htmlspecialchars($po->outlet_name) ?></td>

                                    <!-- Expected Date -->
                                    <td>
                                        <?php if ($po->expected_date): ?>
                                            <?= date('M j, Y', strtotime($po->expected_date)) ?>
                                            <?php
                                            $daysUntil = (int)((strtotime($po->expected_date) - time()) / 86400);
                                            if ($daysUntil < 0):
                                            ?>
                                                <br>
                                                <small class="badge bg-danger">
                                                    <?= abs($daysUntil) ?> days overdue
                                                </small>
                                            <?php elseif ($daysUntil <= 3): ?>
                                                <br>
                                                <small class="badge bg-warning">
                                                    <?= $daysUntil ?> days away
                                                </small>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-muted">Not set</span>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Item Count -->
                                    <td class="text-center">
                                        <span class="badge bg-secondary">
                                            <?= number_format($po->item_count ?? 0) ?>
                                        </span>
                                    </td>

                                    <!-- Total -->
                                    <td class="text-end fw-bold">
                                        $<?= number_format($po->total_cost ?? 0, 2) ?>
                                    </td>

                                    <!-- State -->
                                    <td class="text-center">
                                        <?php
                                        $stateBadges = [
                                            'DRAFT' => 'secondary',
                                            'OPEN' => 'info',
                                            'PENDING_APPROVAL' => 'warning',
                                            'APPROVED' => 'success',
                                            'SENT' => 'primary',
                                            'RECEIVING' => 'info',
                                            'RECEIVED' => 'success',
                                            'COMPLETED' => 'dark',
                                            'CANCELLED' => 'danger',
                                        ];
                                        $badgeClass = $stateBadges[$po->state] ?? 'secondary';
                                        ?>
                                        <span class="badge bg-<?= $badgeClass ?>">
                                            <?= htmlspecialchars($stateOptions[$po->state] ?? $po->state) ?>
                                        </span>
                                    </td>

                                    <!-- Actions -->
                                    <td class="text-center">
                                        <div class="btn-group btn-group-sm" role="group">

                                            <!-- View Button (Always available) -->
                                            <a
                                                href="view.php?id=<?= $po->id ?>"
                                                class="btn btn-outline-primary"
                                                title="View Details"
                                            >
                                                <i class="fas fa-eye"></i>
                                            </a>

                                            <!-- Edit Button (DRAFT and OPEN only) -->
                                            <?php if (in_array($po->state, ['DRAFT', 'OPEN'])): ?>
                                                <a
                                                    href="edit.php?id=<?= $po->id ?>"
                                                    class="btn btn-outline-secondary"
                                                    title="Edit"
                                                >
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                            <?php endif; ?>

                                            <!-- Delete Button (DRAFT only) -->
                                            <?php if ($po->state === 'DRAFT'): ?>
                                                <button
                                                    type="button"
                                                    class="btn btn-outline-danger delete-po"
                                                    data-po-id="<?= $po->id ?>"
                                                    data-po-public-id="<?= htmlspecialchars($po->public_id) ?>"
                                                    title="Delete"
                                                >
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            <?php endif; ?>

                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <nav aria-label="Purchase order pagination">
                        <ul class="pagination justify-content-center mb-0 mt-3">

                            <!-- Previous -->
                            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                <a class="page-link" href="<?= buildPaginationUrl($page - 1, $filters) ?>">
                                    <i class="fas fa-chevron-left"></i> Previous
                                </a>
                            </li>

                            <!-- Page Numbers -->
                            <?php
                            $startPage = max(1, $page - 2);
                            $endPage = min($totalPages, $page + 2);

                            if ($startPage > 1):
                            ?>
                                <li class="page-item">
                                    <a class="page-link" href="<?= buildPaginationUrl(1, $filters) ?>">1</a>
                                </li>
                                <?php if ($startPage > 2): ?>
                                    <li class="page-item disabled">
                                        <span class="page-link">...</span>
                                    </li>
                                <?php endif; ?>
                            <?php endif; ?>

                            <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                    <a class="page-link" href="<?= buildPaginationUrl($i, $filters) ?>">
                                        <?= $i ?>
                                    </a>
                                </li>
                            <?php endfor; ?>

                            <?php if ($endPage < $totalPages): ?>
                                <?php if ($endPage < $totalPages - 1): ?>
                                    <li class="page-item disabled">
                                        <span class="page-link">...</span>
                                    </li>
                                <?php endif; ?>
                                <li class="page-item">
                                    <a class="page-link" href="<?= buildPaginationUrl($totalPages, $filters) ?>">
                                        <?= $totalPages ?>
                                    </a>
                                </li>
                            <?php endif; ?>

                            <!-- Next -->
                            <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                                <a class="page-link" href="<?= buildPaginationUrl($page + 1, $filters) ?>">
                                    Next <i class="fas fa-chevron-right"></i>
                                </a>
                            </li>

                        </ul>
                    </nav>

                    <!-- Pagination Info -->
                    <div class="text-center text-muted mt-2 small">
                        Showing <?= number_format($offset + 1) ?> to
                        <?= number_format(min($offset + $perPage, $totalCount)) ?> of
                        <?= number_format($totalCount) ?> results
                    </div>
                <?php endif; ?>

            <?php endif; ?>

        </div>
    </div>

</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">
                    <i class="fas fa-exclamation-triangle me-2"></i> Confirm Delete
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this purchase order?</p>
                <p class="fw-bold mb-0" id="deletePOId"></p>
                <p class="text-danger small mb-0">This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmDelete">
                    <i class="fas fa-trash me-2"></i> Delete
                </button>
            </div>
        </div>
    </div>
</div>

<?php
/**
 * Helper function to build pagination URLs with filters preserved
 */
function buildPaginationUrl(int $page, array $filters): string {
    $params = array_merge($filters, ['page' => $page]);
    return 'index.php?' . http_build_query($params);
}
?>

<!-- Page JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function() {

    // Toggle filter panel
    const toggleBtn = document.getElementById('toggleFilters');
    const filterPanel = document.getElementById('filterPanel');

    if (toggleBtn && filterPanel) {
        toggleBtn.addEventListener('click', function() {
            filterPanel.classList.toggle('d-none');
            const icon = this.querySelector('i');
            icon.classList.toggle('fa-chevron-down');
            icon.classList.toggle('fa-chevron-up');
        });
    }

    // Delete PO functionality
    let deletePoId = null;
    const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));

    document.querySelectorAll('.delete-po').forEach(btn => {
        btn.addEventListener('click', function() {
            deletePoId = this.dataset.poId;
            const publicId = this.dataset.poPublicId;

            document.getElementById('deletePOId').textContent = 'PO ID: ' + publicId;
            deleteModal.show();
        });
    });

    document.getElementById('confirmDelete').addEventListener('click', async function() {
        if (!deletePoId) return;

        const btn = this;
        const originalHtml = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Deleting...';

        try {
            const response = await fetch('../api/purchase-orders/delete.php', {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ id: deletePoId })
            });

            const result = await response.json();

            if (result.success) {
                // Show success message and reload
                alert('Purchase order deleted successfully');
                window.location.reload();
            } else {
                alert('Error: ' + (result.error || 'Failed to delete purchase order'));
                btn.disabled = false;
                btn.innerHTML = originalHtml;
            }

        } catch (error) {
            console.error('Delete error:', error);
            alert('Network error. Please try again.');
            btn.disabled = false;
            btn.innerHTML = originalHtml;
        }
    });

});
</script>

<?php
// Include footer
include $_SERVER['DOCUMENT_ROOT'] . '/modules/consignments/shared/blocks/footer.php';
?>
