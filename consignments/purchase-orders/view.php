<?php
/**
 * Purchase Orders - View/Detail Page
 *
 * Displays complete purchase order details including:
 * - PO header information
 * - Line items table
 * - Approval history timeline
 * - State-specific actions
 *
 * Uses card.php layout for clean, organized display
 *
 * @package CIS\Consignments\PurchaseOrders
 * @version 1.0.0
 */

declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/app.php';
require_once __DIR__ . '/../lib/Services/PurchaseOrderService.php';
require_once __DIR__ . '/../lib/Services/ApprovalService.php';

use CIS\Services\Consignments\Core\PurchaseOrderService;
use CIS\Services\Consignments\Support\ApprovalService;

// Check authentication
if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

// Get PO ID from query string
$poId = (int)($_GET['id'] ?? 0);
if ($poId <= 0) {
    header('Location: index.php?error=invalid_id');
    exit;
}

// Initialize services
$db = get_db();
$poService = new PurchaseOrderService($db);
$approvalService = new ApprovalService($db);

// Fetch purchase order
try {
    $po = $poService->get($poId);

    if (!$po) {
        header('Location: index.php?error=not_found');
        exit;
    }

    $lineItems = $poService->getLineItems($poId);

    // Get approval info if applicable
    $approvalHistory = [];
    $pendingApprovers = [];

    if (in_array($po->state, ['PENDING_APPROVAL', 'APPROVED'])) {
        $approvalHistory = $approvalService->getApprovalHistory($poId);

        if ($po->state === 'PENDING_APPROVAL') {
            $approvalInfo = $approvalService->getRequiredApprovers($poId);
            $pendingApprovers = $approvalInfo['approvers'] ?? [];
        }
    }

} catch (Exception $e) {
    error_log("PO View Error: " . $e->getMessage());
    header('Location: index.php?error=load_failed');
    exit;
}

// Check user permissions for actions
$userId = $_SESSION['user_id'];
$canEdit = in_array($po->state, ['DRAFT', 'OPEN']);
$canDelete = ($po->state === 'DRAFT');
$canSubmit = ($po->state === 'DRAFT' && !empty($lineItems));

// Check if user is in approver list for PENDING_APPROVAL state
$canApprove = false;
if ($po->state === 'PENDING_APPROVAL' && !empty($pendingApprovers)) {
    $canApprove = in_array($userId, array_column($pendingApprovers, 'user_id'));
}

$canSend = ($po->state === 'APPROVED');
$canReceive = ($po->state === 'SENT');

// State display config
$stateConfig = [
    'DRAFT' => ['label' => 'Draft', 'badge' => 'secondary', 'icon' => 'pencil-alt'],
    'OPEN' => ['label' => 'Open', 'badge' => 'info', 'icon' => 'folder-open'],
    'PENDING_APPROVAL' => ['label' => 'Pending Approval', 'badge' => 'warning', 'icon' => 'clock'],
    'APPROVED' => ['label' => 'Approved', 'badge' => 'success', 'icon' => 'check-circle'],
    'SENT' => ['label' => 'Sent to Supplier', 'badge' => 'primary', 'icon' => 'paper-plane'],
    'RECEIVING' => ['label' => 'Receiving', 'badge' => 'info', 'icon' => 'truck-loading'],
    'RECEIVED' => ['label' => 'Received', 'badge' => 'success', 'icon' => 'check-double'],
    'COMPLETED' => ['label' => 'Completed', 'badge' => 'dark', 'icon' => 'check-circle'],
    'CANCELLED' => ['label' => 'Cancelled', 'badge' => 'danger', 'icon' => 'times-circle'],
];

$currentState = $stateConfig[$po->state] ?? $stateConfig['DRAFT'];

// Page metadata
$pageTitle = 'Purchase Order ' . htmlspecialchars($po->public_id);
$breadcrumbs = [
    ['title' => 'Home', 'url' => '/'],
    ['title' => 'Consignments', 'url' => '/modules/consignments/'],
    ['title' => 'Purchase Orders', 'url' => '/modules/consignments/purchase-orders/'],
    ['title' => $po->public_id, 'url' => null],
];

// Include header
include $_SERVER['DOCUMENT_ROOT'] . '/modules/consignments/shared/blocks/header.php';
?>

<!-- Main Content -->
<div class="container-fluid py-4">

    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-md-8">
            <h2 class="mb-2">
                <i class="fas fa-shopping-cart me-2"></i>
                Purchase Order <?= htmlspecialchars($po->public_id) ?>
            </h2>
            <span class="badge bg-<?= $currentState['badge'] ?> fs-6">
                <i class="fas fa-<?= $currentState['icon'] ?> me-2"></i>
                <?= $currentState['label'] ?>
            </span>
        </div>
        <div class="col-md-4 text-end">

            <!-- Action Buttons -->
            <div class="btn-group" role="group">

                <!-- Edit Button -->
                <?php if ($canEdit): ?>
                    <a href="edit.php?id=<?= $po->id ?>" class="btn btn-primary">
                        <i class="fas fa-edit me-2"></i> Edit
                    </a>
                <?php endif; ?>

                <!-- Submit for Approval Button -->
                <?php if ($canSubmit): ?>
                    <button type="button" class="btn btn-success" id="submitForApproval">
                        <i class="fas fa-paper-plane me-2"></i> Submit for Approval
                    </button>
                <?php endif; ?>

                <!-- Approve/Reject Buttons -->
                <?php if ($canApprove): ?>
                    <button type="button" class="btn btn-success" id="approveBtn">
                        <i class="fas fa-check me-2"></i> Approve
                    </button>
                    <button type="button" class="btn btn-danger" id="rejectBtn">
                        <i class="fas fa-times me-2"></i> Reject
                    </button>
                <?php endif; ?>

                <!-- Send to Supplier Button -->
                <?php if ($canSend): ?>
                    <button type="button" class="btn btn-primary" id="sendToSupplier">
                        <i class="fas fa-envelope me-2"></i> Send to Supplier
                    </button>
                <?php endif; ?>

                <!-- Start Receiving Button -->
                <?php if ($canReceive): ?>
                    <a href="../receiving/start.php?po_id=<?= $po->id ?>" class="btn btn-info">
                        <i class="fas fa-truck me-2"></i> Start Receiving
                    </a>
                <?php endif; ?>

                <!-- Delete Button -->
                <?php if ($canDelete): ?>
                    <button type="button" class="btn btn-outline-danger" id="deleteBtn">
                        <i class="fas fa-trash me-2"></i> Delete
                    </button>
                <?php endif; ?>

                <!-- Back Button -->
                <a href="index.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-2"></i> Back to List
                </a>

            </div>

        </div>
    </div>

    <!-- PO Details Card -->
    <div class="card mb-4">
        <div class="card-header bg-light">
            <h5 class="mb-0">
                <i class="fas fa-info-circle me-2"></i> Purchase Order Details
            </h5>
        </div>
        <div class="card-body">
            <div class="row">

                <!-- Left Column -->
                <div class="col-md-6">

                    <!-- Supplier -->
                    <div class="mb-3">
                        <label class="text-muted small">Supplier</label>
                        <div class="fw-bold"><?= htmlspecialchars($po->supplier_name) ?></div>
                        <?php if (!empty($po->supplier_code)): ?>
                            <div class="text-muted small">Code: <?= htmlspecialchars($po->supplier_code) ?></div>
                        <?php endif; ?>
                    </div>

                    <!-- Outlet -->
                    <div class="mb-3">
                        <label class="text-muted small">Destination Outlet</label>
                        <div class="fw-bold"><?= htmlspecialchars($po->outlet_name) ?></div>
                    </div>

                    <!-- Expected Date -->
                    <div class="mb-3">
                        <label class="text-muted small">Expected Delivery Date</label>
                        <div>
                            <?php if ($po->expected_date): ?>
                                <?= date('l, F j, Y', strtotime($po->expected_date)) ?>
                                <?php
                                $daysUntil = (int)((strtotime($po->expected_date) - time()) / 86400);
                                if ($daysUntil < 0):
                                ?>
                                    <span class="badge bg-danger ms-2">
                                        <?= abs($daysUntil) ?> days overdue
                                    </span>
                                <?php elseif ($daysUntil <= 3): ?>
                                    <span class="badge bg-warning ms-2">
                                        <?= $daysUntil ?> days away
                                    </span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="text-muted">Not set</span>
                            <?php endif; ?>
                        </div>
                    </div>

                </div>

                <!-- Right Column -->
                <div class="col-md-6">

                    <!-- Supplier Reference -->
                    <?php if (!empty($po->supplier_reference)): ?>
                        <div class="mb-3">
                            <label class="text-muted small">Supplier Reference</label>
                            <div class="fw-bold"><?= htmlspecialchars($po->supplier_reference) ?></div>
                        </div>
                    <?php endif; ?>

                    <!-- Created Info -->
                    <div class="mb-3">
                        <label class="text-muted small">Created</label>
                        <div><?= date('M j, Y g:i A', strtotime($po->created_at)) ?></div>
                        <?php if (!empty($po->created_by_name)): ?>
                            <div class="text-muted small">by <?= htmlspecialchars($po->created_by_name) ?></div>
                        <?php endif; ?>
                    </div>

                    <!-- Last Updated -->
                    <?php if ($po->updated_at && $po->updated_at !== $po->created_at): ?>
                        <div class="mb-3">
                            <label class="text-muted small">Last Updated</label>
                            <div><?= date('M j, Y g:i A', strtotime($po->updated_at)) ?></div>
                        </div>
                    <?php endif; ?>

                    <!-- Totals -->
                    <div class="mb-3">
                        <label class="text-muted small">Order Total</label>
                        <div class="fs-4 fw-bold text-primary">
                            $<?= number_format($po->total_cost ?? 0, 2) ?>
                        </div>
                        <div class="text-muted small">
                            <?= count($lineItems) ?> items
                        </div>
                    </div>

                </div>

            </div>

            <!-- Notes Section -->
            <?php if (!empty($po->notes)): ?>
                <div class="row mt-3">
                    <div class="col-12">
                        <label class="text-muted small">Notes</label>
                        <div class="border rounded p-3 bg-light">
                            <?= nl2br(htmlspecialchars($po->notes)) ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

        </div>
    </div>

    <!-- Line Items Card -->
    <div class="card mb-4">
        <div class="card-header bg-light">
            <h5 class="mb-0">
                <i class="fas fa-boxes me-2"></i> Line Items
                <span class="badge bg-secondary ms-2"><?= count($lineItems) ?></span>
            </h5>
        </div>
        <div class="card-body">

            <?php if (empty($lineItems)): ?>

                <!-- Empty State -->
                <div class="text-center py-5 text-muted">
                    <i class="fas fa-boxes fa-3x mb-3"></i>
                    <p>No items added yet</p>
                    <?php if ($canEdit): ?>
                        <a href="edit.php?id=<?= $po->id ?>" class="btn btn-primary">
                            <i class="fas fa-plus me-2"></i> Add Items
                        </a>
                    <?php endif; ?>
                </div>

            <?php else: ?>

                <!-- Items Table -->
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 50px">#</th>
                                <th>Product</th>
                                <th class="text-center" style="width: 100px">Quantity</th>
                                <th class="text-end" style="width: 120px">Unit Cost</th>
                                <th class="text-end" style="width: 120px">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $lineNumber = 1;
                            foreach ($lineItems as $item):
                            ?>
                                <tr>
                                    <!-- Line Number -->
                                    <td class="text-muted"><?= $lineNumber++ ?></td>

                                    <!-- Product -->
                                    <td>
                                        <div class="fw-bold"><?= htmlspecialchars($item->product_name) ?></div>
                                        <?php if (!empty($item->product_sku)): ?>
                                            <small class="text-muted">SKU: <?= htmlspecialchars($item->product_sku) ?></small>
                                        <?php endif; ?>
                                        <?php if (!empty($item->notes)): ?>
                                            <br>
                                            <small class="text-info">
                                                <i class="fas fa-sticky-note me-1"></i>
                                                <?= htmlspecialchars($item->notes) ?>
                                            </small>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Quantity -->
                                    <td class="text-center">
                                        <span class="badge bg-primary fs-6">
                                            <?= number_format($item->quantity) ?>
                                        </span>
                                    </td>

                                    <!-- Unit Cost -->
                                    <td class="text-end">
                                        $<?= number_format($item->cost, 2) ?>
                                    </td>

                                    <!-- Total -->
                                    <td class="text-end fw-bold">
                                        $<?= number_format($item->total_cost, 2) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>

                            <!-- Total Row -->
                            <tr class="table-secondary fw-bold">
                                <td colspan="4" class="text-end">Total:</td>
                                <td class="text-end fs-5">
                                    $<?= number_format($po->total_cost ?? 0, 2) ?>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

            <?php endif; ?>

        </div>
    </div>

    <!-- Approval History (if applicable) -->
    <?php if (!empty($approvalHistory) || !empty($pendingApprovers)): ?>
        <div class="card mb-4">
            <div class="card-header bg-light">
                <h5 class="mb-0">
                    <i class="fas fa-history me-2"></i> Approval History
                </h5>
            </div>
            <div class="card-body">

                <!-- Pending Approvers -->
                <?php if (!empty($pendingApprovers)): ?>
                    <div class="alert alert-warning mb-3">
                        <h6 class="alert-heading">
                            <i class="fas fa-clock me-2"></i> Awaiting Approval From:
                        </h6>
                        <ul class="mb-0">
                            <?php foreach ($pendingApprovers as $approver): ?>
                                <li>
                                    <?= htmlspecialchars($approver['role'] ?? 'Unknown Role') ?>
                                    <?php if (!empty($approver['user_name'])): ?>
                                        (<?= htmlspecialchars($approver['user_name']) ?>)
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <!-- Approval Timeline -->
                <?php if (!empty($approvalHistory)): ?>
                    <div class="timeline">
                        <?php foreach ($approvalHistory as $entry): ?>
                            <div class="timeline-item">
                                <div class="timeline-marker bg-<?= $entry->action === 'APPROVED' ? 'success' : 'danger' ?>">
                                    <i class="fas fa-<?= $entry->action === 'APPROVED' ? 'check' : 'times' ?>"></i>
                                </div>
                                <div class="timeline-content">
                                    <div class="fw-bold">
                                        <?= htmlspecialchars($entry->approver_name) ?>
                                        <span class="badge bg-<?= $entry->action === 'APPROVED' ? 'success' : 'danger' ?> ms-2">
                                            <?= htmlspecialchars($entry->action) ?>
                                        </span>
                                    </div>
                                    <div class="text-muted small">
                                        <?= date('M j, Y g:i A', strtotime($entry->created_at)) ?>
                                    </div>
                                    <?php if (!empty($entry->comments)): ?>
                                        <div class="mt-2 p-2 bg-light rounded">
                                            <i class="fas fa-comment me-2"></i>
                                            <?= nl2br(htmlspecialchars($entry->comments)) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

            </div>
        </div>
    <?php endif; ?>

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
                <form id="approvalForm">
                    <input type="hidden" id="approvalAction" name="action" value="APPROVED">

                    <div class="mb-3">
                        <label for="approvalComments" class="form-label">Comments</label>
                        <textarea
                            class="form-control"
                            id="approvalComments"
                            name="comments"
                            rows="3"
                            placeholder="Add any comments about this decision..."
                        ></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="submitApproval">Submit</button>
            </div>
        </div>
    </div>
</div>

<!-- Client-side Instrumentation -->
<script src="js/interaction-logger.js"></script>
<script src="js/security-monitor.js"></script>

<!-- Page JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function() {

    const poId = <?= $po->id ?>;
    const approvalModal = new bootstrap.Modal(document.getElementById('approvalModal'));

    // Initialize security monitoring for PO view page
    try {
        SecurityMonitor.init({
            poId: poId,
            page: 'view',
            enabled: true
        });
    } catch (error) {
        console.error('SecurityMonitor init failed:', error);
    }

    // Submit for Approval
    const submitBtn = document.getElementById('submitForApproval');
    if (submitBtn) {
        submitBtn.addEventListener('click', async function() {
            if (!confirm('Submit this purchase order for approval?')) return;

            const btn = this;
            const originalHtml = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Submitting...';

            try {
                const response = await fetch('../api/purchase-orders/submit.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ po_id: poId })
                });

                const result = await response.json();

                if (result.success) {
                    alert('Purchase order submitted for approval successfully');
                    window.location.reload();
                } else {
                    alert('Error: ' + (result.error || 'Failed to submit'));
                    btn.disabled = false;
                    btn.innerHTML = originalHtml;
                }
            } catch (error) {
                console.error('Submit error:', error);
                alert('Network error. Please try again.');
                btn.disabled = false;
                btn.innerHTML = originalHtml;
            }
        });
    }

    // Approve Button
    const approveBtn = document.getElementById('approveBtn');
    if (approveBtn) {
        approveBtn.addEventListener('click', function() {
            // Log modal opening
            try {
                InteractionLogger.track({
                    event_type: 'modal_opened',
                    event_data: {
                        modal_type: 'approval',
                        action: 'approve',
                        po_id: poId
                    },
                    page: 'view'
                });
            } catch (error) {
                console.error('Interaction logging failed:', error);
            }

            document.getElementById('approvalModalTitle').textContent = 'Approve Purchase Order';
            document.getElementById('approvalAction').value = 'APPROVED';
            approvalModal.show();
        });
    }

    // Reject Button
    const rejectBtn = document.getElementById('rejectBtn');
    if (rejectBtn) {
        rejectBtn.addEventListener('click', function() {
            // Log modal opening
            try {
                InteractionLogger.track({
                    event_type: 'modal_opened',
                    event_data: {
                        modal_type: 'approval',
                        action: 'reject',
                        po_id: poId
                    },
                    page: 'view'
                });
            } catch (error) {
                console.error('Interaction logging failed:', error);
            }

            document.getElementById('approvalModalTitle').textContent = 'Reject Purchase Order';
            document.getElementById('approvalAction').value = 'REJECTED';
            approvalModal.show();
        });
    }

    // Submit Approval
    document.getElementById('submitApproval').addEventListener('click', async function() {
        const modalOpenedAt = Date.now();
        const action = document.getElementById('approvalAction').value;
        const comments = document.getElementById('approvalComments').value;

        const btn = this;
        const originalHtml = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Processing...';

        try {
            const response = await fetch('../api/purchase-orders/approve.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    po_id: poId,
                    action: action,
                    comments: comments
                })
            });

            const result = await response.json();

            if (result.success) {
                // Log successful approval decision
                try {
                    InteractionLogger.track({
                        event_type: 'button_clicked',
                        event_data: {
                            button_id: 'submitApproval',
                            action: action,
                            po_id: poId,
                            has_comments: comments.length > 0,
                            decision_time_seconds: Math.round((Date.now() - modalOpenedAt) / 1000)
                        },
                        page: 'view'
                    });
                } catch (error) {
                    console.error('Interaction logging failed:', error);
                }

                alert('Approval decision submitted successfully');
                window.location.reload();
            } else {
                alert('Error: ' + (result.error || 'Failed to process approval'));
                btn.disabled = false;
                btn.innerHTML = originalHtml;
            }
        } catch (error) {
            console.error('Approval error:', error);
            alert('Network error. Please try again.');
            btn.disabled = false;
            btn.innerHTML = originalHtml;
        }
    });

    // Send to Supplier
    const sendBtn = document.getElementById('sendToSupplier');
    if (sendBtn) {
        sendBtn.addEventListener('click', async function() {
            if (!confirm('Send this purchase order to the supplier via email?')) return;

            const btn = this;
            const originalHtml = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Sending...';

            try {
                const response = await fetch('../api/purchase-orders/send.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ po_id: poId })
                });

                const result = await response.json();

                if (result.success) {
                    alert('Purchase order sent to supplier successfully');
                    window.location.reload();
                } else {
                    alert('Error: ' + (result.error || 'Failed to send'));
                    btn.disabled = false;
                    btn.innerHTML = originalHtml;
                }
            } catch (error) {
                console.error('Send error:', error);
                alert('Network error. Please try again.');
                btn.disabled = false;
                btn.innerHTML = originalHtml;
            }
        });
    }

    // Delete PO
    const deleteBtn = document.getElementById('deleteBtn');
    if (deleteBtn) {
        deleteBtn.addEventListener('click', async function() {
            if (!confirm('Are you sure you want to delete this purchase order? This cannot be undone.')) return;

            const btn = this;
            const originalHtml = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Deleting...';

            try {
                const response = await fetch('../api/purchase-orders/delete.php', {
                    method: 'DELETE',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: poId })
                });

                const result = await response.json();

                if (result.success) {
                    alert('Purchase order deleted successfully');
                    window.location.href = 'index.php';
                } else {
                    alert('Error: ' + (result.error || 'Failed to delete'));
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
    }

});
</script>

<!-- Timeline Styles -->
<style>
.timeline {
    position: relative;
    padding-left: 50px;
}

.timeline::before {
    content: '';
    position: absolute;
    left: 20px;
    top: 10px;
    bottom: 10px;
    width: 2px;
    background: #dee2e6;
}

.timeline-item {
    position: relative;
    margin-bottom: 30px;
}

.timeline-marker {
    position: absolute;
    left: -38px;
    top: 5px;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 18px;
}

.timeline-content {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 8px;
}
</style>

<?php
// Include footer
include $_SERVER['DOCUMENT_ROOT'] . '/modules/consignments/shared/blocks/footer.php';
?>
