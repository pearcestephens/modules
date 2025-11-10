<?php
/**
 * Purchase Orders - Approval Dashboard
 *
 * Shows pending purchase orders requiring approval from current user.
 * Allows quick approve/reject actions and bulk operations.
 *
 * Features:
 * - Filter: My approvals vs All approvals (admin only)
 * - Sort: By priority, amount, date submitted
 * - Quick actions: Approve/Reject buttons
 * - Bulk approval: Select multiple POs
 * - Real-time updates: AJAX-based actions
 *
 * @package CIS\Consignments\PurchaseOrders
 * @subpackage Approvals
 * @since 1.0.0
 * @author AI Assistant
 * @date 2025-10-31
 */

declare(strict_types=1);

// Bootstrap application
require_once $_SERVER['DOCUMENT_ROOT'] . '/modules/consignments/bootstrap.php';

use Consignments\Lib\Services\ApprovalService;
use Consignments\Lib\Services\PurchaseOrderService;

// Check authentication
if (!isset($_SESSION['userID'])) {
    header('Location: /login.php');
    exit;
}

$db = getDB();
$approvalService = new ApprovalService($db);
$poService = new PurchaseOrderService($db);

// Get current user info
$currentUserId = $_SESSION['userID'];
$isAdmin = ($_SESSION['user_role'] ?? '') === 'admin';

// Filters
$showAll = isset($_GET['show_all']) && $_GET['show_all'] === '1' && $isAdmin;
$sortBy = $_GET['sort_by'] ?? 'priority'; // priority, amount, date
$sortOrder = $_GET['sort_order'] ?? 'DESC';

// Get pending approvals
if ($showAll) {
    // Admin: Show all pending approvals
    $sql = "
        SELECT
            po.id,
            po.public_id,
            po.state,
            po.expected_date,
            po.total_cost,
            po.created_at,
            po.created_by,
            s.name AS supplier_name,
            s.supplier_code,
            o.name AS outlet_name,
            u.username AS created_by_username,
            COUNT(ar.id) AS pending_approvers,
            MIN(ar.tier) AS min_tier
        FROM vend_consignments po
        LEFT JOIN vend_suppliers s ON po.supplier_id = s.id
        LEFT JOIN vend_outlets o ON po.outlet_id = o.id
        LEFT JOIN users u ON po.created_by = u.id
        LEFT JOIN approval_requests ar ON ar.entity_type = 'purchase_order'
            AND ar.entity_id = po.id
            AND ar.status = 'PENDING'
        WHERE po.transfer_category = 'PURCHASE_ORDER'
        AND po.state = 'PENDING_APPROVAL'
        AND po.deleted_at IS NULL
        GROUP BY po.id
    ";
} else {
    // Regular user: Show only their pending approvals
    $sql = "
        SELECT
            po.id,
            po.public_id,
            po.state,
            po.expected_date,
            po.total_cost,
            po.created_at,
            po.created_by,
            s.name AS supplier_name,
            s.supplier_code,
            o.name AS outlet_name,
            u.username AS created_by_username,
            ar.tier,
            ar.created_at AS approval_request_date
        FROM vend_consignments po
        INNER JOIN approval_requests ar ON ar.entity_type = 'purchase_order'
            AND ar.entity_id = po.id
            AND ar.approver_id = ?
            AND ar.status = 'PENDING'
        LEFT JOIN vend_suppliers s ON po.supplier_id = s.id
        LEFT JOIN vend_outlets o ON po.outlet_id = o.id
        LEFT JOIN users u ON po.created_by = u.id
        WHERE po.transfer_category = 'PURCHASE_ORDER'
        AND po.state = 'PENDING_APPROVAL'
        AND po.deleted_at IS NULL
    ";
}

// Add sorting
switch ($sortBy) {
    case 'amount':
        $sql .= " ORDER BY po.total_cost " . $sortOrder;
        break;
    case 'date':
        $sql .= " ORDER BY po.created_at " . $sortOrder;
        break;
    case 'priority':
    default:
        if ($showAll) {
            $sql .= " ORDER BY min_tier ASC, po.total_cost DESC";
        } else {
            $sql .= " ORDER BY ar.tier ASC, po.total_cost DESC";
        }
        break;
}

$stmt = $db->prepare($sql);
if (!$showAll) {
    $stmt->execute([$currentUserId]);
} else {
    $stmt->execute();
}
$pendingApprovals = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Page metadata
$pageTitle = 'Approval Dashboard';
$pageDescription = 'Review and approve pending purchase orders';

// Include header
include $_SERVER['DOCUMENT_ROOT'] . '/modules/consignments/shared/blocks/header.php';
?>

<div class="container-fluid mt-4">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">
                <i class="fas fa-check-circle text-success me-2"></i>
                Approval Dashboard
            </h1>
            <p class="text-muted mb-0">Review and approve pending purchase orders</p>
        </div>
        <div>
            <?php if ($isAdmin): ?>
            <a href="/modules/consignments/purchase-orders/admin/approval-thresholds.php" class="btn btn-outline-primary me-2">
                <i class="fas fa-cog me-2"></i>Configure Thresholds
            </a>
            <?php endif; ?>
            <a href="/modules/consignments/purchase-orders/index.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-2"></i>Back to POs
            </a>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card border-primary">
                <div class="card-body">
                    <h5 class="card-title text-muted mb-1">Pending Approvals</h5>
                    <h2 class="mb-0"><?= count($pendingApprovals) ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-warning">
                <div class="card-body">
                    <h5 class="card-title text-muted mb-1">High Value (>$5k)</h5>
                    <h2 class="mb-0">
                        <?= count(array_filter($pendingApprovals, fn($po) => $po['total_cost'] > 5000)) ?>
                    </h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-danger">
                <div class="card-body">
                    <h5 class="card-title text-muted mb-1">Urgent (>3 days)</h5>
                    <h2 class="mb-0">
                        <?php
                        $urgentCount = 0;
                        foreach ($pendingApprovals as $po) {
                            $daysAgo = (time() - strtotime($po['created_at'])) / 86400;
                            if ($daysAgo > 3) $urgentCount++;
                        }
                        echo $urgentCount;
                        ?>
                    </h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-info">
                <div class="card-body">
                    <h5 class="card-title text-muted mb-1">Total Value</h5>
                    <h2 class="mb-0">
                        $<?= number_format(array_sum(array_column($pendingApprovals, 'total_cost')), 2) ?>
                    </h2>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters and Actions -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <div class="btn-group" role="group">
                        <a href="?show_all=0"
                           class="btn <?= !$showAll ? 'btn-primary' : 'btn-outline-primary' ?>">
                            <i class="fas fa-user me-2"></i>My Approvals
                        </a>
                        <?php if ($isAdmin): ?>
                            <a href="?show_all=1"
                               class="btn <?= $showAll ? 'btn-primary' : 'btn-outline-primary' ?>">
                                <i class="fas fa-users me-2"></i>All Approvals
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-md-6 text-end">
                    <div class="btn-group me-2" role="group">
                        <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                            <i class="fas fa-sort me-2"></i>
                            Sort: <?= ucfirst($sortBy) ?>
                        </button>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="?sort_by=priority&show_all=<?= $showAll ? '1' : '0' ?>">
                                <i class="fas fa-star me-2"></i>Priority
                            </a></li>
                            <li><a class="dropdown-item" href="?sort_by=amount&show_all=<?= $showAll ? '1' : '0' ?>">
                                <i class="fas fa-dollar-sign me-2"></i>Amount
                            </a></li>
                            <li><a class="dropdown-item" href="?sort_by=date&show_all=<?= $showAll ? '1' : '0' ?>">
                                <i class="fas fa-calendar me-2"></i>Date
                            </a></li>
                        </ul>
                    </div>
                    <button id="bulkApproveBtn" class="btn btn-success" disabled>
                        <i class="fas fa-check-double me-2"></i>Approve Selected
                    </button>
                    <button id="bulkRejectBtn" class="btn btn-danger" disabled>
                        <i class="fas fa-times-circle me-2"></i>Reject Selected
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Approvals Table -->
    <div class="card">
        <div class="card-body">
            <?php if (empty($pendingApprovals)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-check-circle text-success" style="font-size: 4rem;"></i>
                    <h4 class="mt-3">All caught up!</h4>
                    <p class="text-muted">You have no pending approvals at this time.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th width="40">
                                    <input type="checkbox" id="selectAll" class="form-check-input">
                                </th>
                                <th>PO ID</th>
                                <th>Supplier</th>
                                <th>Outlet</th>
                                <th class="text-end">Amount</th>
                                <th>Submitted By</th>
                                <th>Age</th>
                                <?php if ($showAll): ?>
                                    <th>Tier</th>
                                    <th>Pending</th>
                                <?php else: ?>
                                    <th>Your Tier</th>
                                <?php endif; ?>
                                <th width="200">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pendingApprovals as $po):
                                $daysAgo = floor((time() - strtotime($po['created_at'])) / 86400);
                                $isUrgent = $daysAgo > 3;
                                $isHighValue = $po['total_cost'] > 5000;
                                $tier = $showAll ? $po['min_tier'] : $po['tier'];
                            ?>
                                <tr class="<?= $isUrgent ? 'table-warning' : '' ?>">
                                    <td>
                                        <input type="checkbox"
                                               class="form-check-input po-checkbox"
                                               value="<?= $po['id'] ?>"
                                               data-po-id="<?= $po['id'] ?>">
                                    </td>
                                    <td>
                                        <a href="/modules/consignments/purchase-orders/view.php?id=<?= $po['id'] ?>"
                                           class="text-decoration-none">
                                            <strong><?= htmlspecialchars($po['public_id']) ?></strong>
                                        </a>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars($po['supplier_name']) ?>
                                        <br>
                                        <small class="text-muted"><?= htmlspecialchars($po['supplier_code']) ?></small>
                                    </td>
                                    <td><?= htmlspecialchars($po['outlet_name']) ?></td>
                                    <td class="text-end">
                                        <strong class="<?= $isHighValue ? 'text-danger' : '' ?>">
                                            $<?= number_format($po['total_cost'], 2) ?>
                                        </strong>
                                        <?php if ($isHighValue): ?>
                                            <br><span class="badge bg-danger">High Value</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($po['created_by_username']) ?></td>
                                    <td>
                                        <span class="badge bg-<?= $isUrgent ? 'danger' : 'secondary' ?>">
                                            <?= $daysAgo ?> day<?= $daysAgo != 1 ? 's' : '' ?>
                                        </span>
                                    </td>
                                    <?php if ($showAll): ?>
                                        <td>
                                            <span class="badge bg-info">Tier <?= $tier ?></span>
                                        </td>
                                        <td>
                                            <span class="badge bg-warning">
                                                <?= $po['pending_approvers'] ?> approver<?= $po['pending_approvers'] != 1 ? 's' : '' ?>
                                            </span>
                                        </td>
                                    <?php else: ?>
                                        <td>
                                            <span class="badge bg-info">Tier <?= $tier ?></span>
                                        </td>
                                    <?php endif; ?>
                                    <td>
                                        <div class="btn-group btn-group-sm" role="group">
                                            <button class="btn btn-success approve-btn"
                                                    data-po-id="<?= $po['id'] ?>"
                                                    data-po-name="<?= htmlspecialchars($po['public_id']) ?>">
                                                <i class="fas fa-check me-1"></i>Approve
                                            </button>
                                            <button class="btn btn-danger reject-btn"
                                                    data-po-id="<?= $po['id'] ?>"
                                                    data-po-name="<?= htmlspecialchars($po['public_id']) ?>">
                                                <i class="fas fa-times me-1"></i>Reject
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Approval Modal -->
<div class="modal fade" id="approvalModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="approvalModalTitle">Approve Purchase Order</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p id="approvalModalMessage"></p>
                <div class="mb-3">
                    <label for="approvalComments" class="form-label">Comments (optional)</label>
                    <textarea class="form-control"
                              id="approvalComments"
                              rows="3"
                              placeholder="Add any comments about your decision..."></textarea>
                </div>
                <input type="hidden" id="approvalAction">
                <input type="hidden" id="approvalPoId">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="confirmApprovalBtn">
                    <span class="btn-text">Confirm</span>
                    <span class="spinner-border spinner-border-sm d-none" role="status"></span>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Bulk Approval Modal -->
<div class="modal fade" id="bulkApprovalModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="bulkApprovalModalTitle">Bulk Approve</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p id="bulkApprovalMessage"></p>
                <div class="mb-3">
                    <label for="bulkApprovalComments" class="form-label">Comments (optional)</label>
                    <textarea class="form-control"
                              id="bulkApprovalComments"
                              rows="3"
                              placeholder="Add comments for all selected POs..."></textarea>
                </div>
                <input type="hidden" id="bulkApprovalAction">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="confirmBulkApprovalBtn">
                    <span class="btn-text">Confirm</span>
                    <span class="spinner-border spinner-border-sm d-none" role="status"></span>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const selectAllCheckbox = document.getElementById('selectAll');
    const poCheckboxes = document.querySelectorAll('.po-checkbox');
    const bulkApproveBtn = document.getElementById('bulkApproveBtn');
    const bulkRejectBtn = document.getElementById('bulkRejectBtn');
    const approvalModal = new bootstrap.Modal(document.getElementById('approvalModal'));
    const bulkApprovalModal = new bootstrap.Modal(document.getElementById('bulkApprovalModal'));

    // Select all checkbox
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            poCheckboxes.forEach(cb => cb.checked = this.checked);
            updateBulkButtons();
        });
    }

    // Individual checkboxes
    poCheckboxes.forEach(cb => {
        cb.addEventListener('change', updateBulkButtons);
    });

    function updateBulkButtons() {
        const checkedCount = Array.from(poCheckboxes).filter(cb => cb.checked).length;
        bulkApproveBtn.disabled = checkedCount === 0;
        bulkRejectBtn.disabled = checkedCount === 0;
    }

    // Individual approve buttons
    document.querySelectorAll('.approve-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const poId = this.dataset.poId;
            const poName = this.dataset.poName;

            document.getElementById('approvalModalTitle').textContent = 'Approve Purchase Order';
            document.getElementById('approvalModalMessage').textContent =
                `Are you sure you want to approve ${poName}?`;
            document.getElementById('approvalAction').value = 'APPROVED';
            document.getElementById('approvalPoId').value = poId;
            document.getElementById('approvalComments').value = '';

            approvalModal.show();
        });
    });

    // Individual reject buttons
    document.querySelectorAll('.reject-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const poId = this.dataset.poId;
            const poName = this.dataset.poName;

            document.getElementById('approvalModalTitle').textContent = 'Reject Purchase Order';
            document.getElementById('approvalModalMessage').textContent =
                `Are you sure you want to reject ${poName}? Please provide a reason in the comments.`;
            document.getElementById('approvalAction').value = 'REJECTED';
            document.getElementById('approvalPoId').value = poId;
            document.getElementById('approvalComments').value = '';

            approvalModal.show();
        });
    });

    // Confirm individual approval
    document.getElementById('confirmApprovalBtn').addEventListener('click', async function() {
        const btn = this;
        const btnText = btn.querySelector('.btn-text');
        const spinner = btn.querySelector('.spinner-border');

        btn.disabled = true;
        btnText.classList.add('d-none');
        spinner.classList.remove('d-none');

        const poId = document.getElementById('approvalPoId').value;
        const action = document.getElementById('approvalAction').value;
        const comments = document.getElementById('approvalComments').value;

        try {
            const response = await fetch('/modules/consignments/api/purchase-orders/approve.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ po_id: parseInt(poId), action, comments })
            });

            const result = await response.json();

            if (result.success) {
                alert(`Purchase order ${action.toLowerCase()} successfully!`);
                window.location.reload();
            } else {
                alert('Error: ' + result.message);
                btn.disabled = false;
                btnText.classList.remove('d-none');
                spinner.classList.add('d-none');
            }
        } catch (error) {
            alert('Error: ' + error.message);
            btn.disabled = false;
            btnText.classList.remove('d-none');
            spinner.classList.add('d-none');
        }
    });

    // Bulk approve button
    bulkApproveBtn.addEventListener('click', function() {
        const selectedPOs = Array.from(poCheckboxes)
            .filter(cb => cb.checked)
            .map(cb => cb.value);

        document.getElementById('bulkApprovalModalTitle').textContent = 'Bulk Approve';
        document.getElementById('bulkApprovalMessage').textContent =
            `Are you sure you want to approve ${selectedPOs.length} purchase order${selectedPOs.length != 1 ? 's' : ''}?`;
        document.getElementById('bulkApprovalAction').value = 'APPROVED';
        document.getElementById('bulkApprovalComments').value = '';

        bulkApprovalModal.show();
    });

    // Bulk reject button
    bulkRejectBtn.addEventListener('click', function() {
        const selectedPOs = Array.from(poCheckboxes)
            .filter(cb => cb.checked)
            .map(cb => cb.value);

        document.getElementById('bulkApprovalModalTitle').textContent = 'Bulk Reject';
        document.getElementById('bulkApprovalMessage').textContent =
            `Are you sure you want to reject ${selectedPOs.length} purchase order${selectedPOs.length != 1 ? 's' : ''}? Please provide a reason in the comments.`;
        document.getElementById('bulkApprovalAction').value = 'REJECTED';
        document.getElementById('bulkApprovalComments').value = '';

        bulkApprovalModal.show();
    });

    // Confirm bulk approval
    document.getElementById('confirmBulkApprovalBtn').addEventListener('click', async function() {
        const btn = this;
        const btnText = btn.querySelector('.btn-text');
        const spinner = btn.querySelector('.spinner-border');

        btn.disabled = true;
        btnText.classList.add('d-none');
        spinner.classList.remove('d-none');

        const selectedPOs = Array.from(poCheckboxes)
            .filter(cb => cb.checked)
            .map(cb => parseInt(cb.value));
        const action = document.getElementById('bulkApprovalAction').value;
        const comments = document.getElementById('bulkApprovalComments').value;

        try {
            const response = await fetch('/modules/consignments/api/purchase-orders/bulk-approve.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ po_ids: selectedPOs, action, comments })
            });

            const result = await response.json();

            if (result.success) {
                alert(`${result.data.processed} purchase orders ${action.toLowerCase()} successfully!`);
                window.location.reload();
            } else {
                alert('Error: ' + result.message);
                btn.disabled = false;
                btnText.classList.remove('d-none');
                spinner.classList.add('d-none');
            }
        } catch (error) {
            alert('Error: ' + error.message);
            btn.disabled = false;
            btnText.classList.remove('d-none');
            spinner.classList.add('d-none');
        }
    });
});
</script>

<?php
// Include footer
include $_SERVER['DOCUMENT_ROOT'] . '/modules/consignments/shared/blocks/footer.php';
?>
