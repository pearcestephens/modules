<?php
/**
 * Purchase Orders - Approval History
 *
 * Complete audit trail of all approval actions across all purchase orders.
 * Provides extensive filtering, search, and export capabilities.
 *
 * Features:
 * - Filter by: Date range, approver, action, tier, PO state
 * - Search: PO ID, supplier name
 * - Pagination: 50 records per page
 * - Export: CSV download
 * - Drill-down: Click PO to view details
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

// Check authentication
if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

$db = getDB();
$approvalService = new ApprovalService($db);

// Pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 50;
$offset = ($page - 1) * $perPage;

// Filters
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';
$approverId = $_GET['approver_id'] ?? '';
$action = $_GET['action'] ?? '';
$tier = $_GET['tier'] ?? '';
$poState = $_GET['po_state'] ?? '';
$search = $_GET['search'] ?? '';

// Build WHERE clauses
$whereClauses = ["ar.status != 'PENDING'"];
$params = [];

if ($dateFrom) {
    $whereClauses[] = "ar.updated_at >= ?";
    $params[] = $dateFrom . ' 00:00:00';
}

if ($dateTo) {
    $whereClauses[] = "ar.updated_at <= ?";
    $params[] = $dateTo . ' 23:59:59';
}

if ($approverId) {
    $whereClauses[] = "ar.approver_id = ?";
    $params[] = $approverId;
}

if ($action) {
    $whereClauses[] = "ar.status = ?";
    $params[] = $action;
}

if ($tier) {
    $whereClauses[] = "ar.tier = ?";
    $params[] = intval($tier);
}

if ($poState) {
    $whereClauses[] = "po.state = ?";
    $params[] = $poState;
}

if ($search) {
    $whereClauses[] = "(po.public_id LIKE ? OR s.name LIKE ?)";
    $searchTerm = '%' . $search . '%';
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

$whereSQL = implode(' AND ', $whereClauses);

// Get total count
$countSQL = "
    SELECT COUNT(*)
    FROM approval_requests ar
    INNER JOIN vend_consignments po ON ar.entity_type = 'purchase_order' AND ar.entity_id = po.id
    LEFT JOIN vend_suppliers s ON po.supplier_id = s.id
    WHERE $whereSQL
";
$stmt = $db->prepare($countSQL);
$stmt->execute($params);
$totalRecords = $stmt->fetchColumn();
$totalPages = ceil($totalRecords / $perPage);

// Get approval history
$sql = "
    SELECT
        ar.id,
        ar.tier,
        ar.status,
        ar.comments,
        ar.created_at,
        ar.updated_at,
        po.id AS po_id,
        po.public_id,
        po.state AS po_state,
        po.total_cost,
        s.name AS supplier_name,
        u.username AS approver_name
    FROM approval_requests ar
    INNER JOIN vend_consignments po ON ar.entity_type = 'purchase_order' AND ar.entity_id = po.id
    LEFT JOIN vend_suppliers s ON po.supplier_id = s.id
    LEFT JOIN users u ON ar.approver_id = u.id
    WHERE $whereSQL
    ORDER BY ar.updated_at DESC
    LIMIT $perPage OFFSET $offset
";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$approvals = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all approvers for filter dropdown
$approversSQL = "
    SELECT DISTINCT u.id, u.username
    FROM approval_requests ar
    INNER JOIN users u ON ar.approver_id = u.id
    WHERE ar.status != 'PENDING'
    ORDER BY u.username
";
$approvers = $db->query($approversSQL)->fetchAll(PDO::FETCH_ASSOC);

// Page metadata
$pageTitle = 'Approval History';
$pageDescription = 'Complete audit trail of purchase order approvals';

// Include header
include $_SERVER['DOCUMENT_ROOT'] . '/modules/consignments/shared/blocks/header.php';
?>

<div class="container-fluid mt-4">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">
                <i class="fas fa-history text-info me-2"></i>
                Approval History
            </h1>
            <p class="text-muted mb-0">Complete audit trail of all approval actions</p>
        </div>
        <div>
            <a href="/modules/consignments/purchase-orders/approvals/dashboard.php" class="btn btn-outline-secondary me-2">
                <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
            </a>
            <button id="exportBtn" class="btn btn-success">
                <i class="fas fa-file-csv me-2"></i>Export CSV
            </button>
        </div>
    </div>

    <!-- Stats Summary -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card border-success">
                <div class="card-body">
                    <h5 class="card-title text-muted mb-1">Total Approvals</h5>
                    <h2 class="mb-0 text-success"><?= number_format($totalRecords) ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-primary">
                <div class="card-body">
                    <h5 class="card-title text-muted mb-1">Approved</h5>
                    <?php
                    $approvedSQL = "SELECT COUNT(*) FROM approval_requests WHERE status = 'APPROVED' AND $whereSQL";
                    $stmt = $db->prepare($approvedSQL);
                    $stmt->execute($params);
                    $approvedCount = $stmt->fetchColumn();
                    ?>
                    <h2 class="mb-0 text-primary"><?= number_format($approvedCount) ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-danger">
                <div class="card-body">
                    <h5 class="card-title text-muted mb-1">Rejected</h5>
                    <?php
                    $rejectedSQL = "SELECT COUNT(*) FROM approval_requests WHERE status = 'REJECTED' AND $whereSQL";
                    $stmt = $db->prepare($rejectedSQL);
                    $stmt->execute($params);
                    $rejectedCount = $stmt->fetchColumn();
                    ?>
                    <h2 class="mb-0 text-danger"><?= number_format($rejectedCount) ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-warning">
                <div class="card-body">
                    <h5 class="card-title text-muted mb-1">Changes Requested</h5>
                    <?php
                    $changesSQL = "SELECT COUNT(*) FROM approval_requests WHERE status = 'REQUEST_CHANGES' AND $whereSQL";
                    $stmt = $db->prepare($changesSQL);
                    $stmt->execute($params);
                    $changesCount = $stmt->fetchColumn();
                    ?>
                    <h2 class="mb-0 text-warning"><?= number_format($changesCount) ?></h2>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-header">
            <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#filterPanel">
                <i class="fas fa-filter me-2"></i>
                Filters
                <i class="fas fa-chevron-down ms-2"></i>
            </button>
            <?php if (!empty(array_filter([$dateFrom, $dateTo, $approverId, $action, $tier, $poState, $search]))): ?>
                <a href="?" class="btn btn-sm btn-outline-secondary ms-2">
                    <i class="fas fa-times me-2"></i>Clear Filters
                </a>
            <?php endif; ?>
        </div>
        <div class="collapse" id="filterPanel">
            <div class="card-body">
                <form method="GET" action="">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Date From</label>
                            <input type="date"
                                   name="date_from"
                                   class="form-control"
                                   value="<?= htmlspecialchars($dateFrom) ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Date To</label>
                            <input type="date"
                                   name="date_to"
                                   class="form-control"
                                   value="<?= htmlspecialchars($dateTo) ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Approver</label>
                            <select name="approver_id" class="form-select">
                                <option value="">All Approvers</option>
                                <?php foreach ($approvers as $approver): ?>
                                    <option value="<?= $approver['id'] ?>" <?= $approverId == $approver['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($approver['username']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Action</label>
                            <select name="action" class="form-select">
                                <option value="">All Actions</option>
                                <option value="APPROVED" <?= $action === 'APPROVED' ? 'selected' : '' ?>>Approved</option>
                                <option value="REJECTED" <?= $action === 'REJECTED' ? 'selected' : '' ?>>Rejected</option>
                                <option value="REQUEST_CHANGES" <?= $action === 'REQUEST_CHANGES' ? 'selected' : '' ?>>
                                    Changes Requested
                                </option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Tier</label>
                            <select name="tier" class="form-select">
                                <option value="">All Tiers</option>
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <option value="<?= $i ?>" <?= $tier == $i ? 'selected' : '' ?>>
                                        Tier <?= $i ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">PO State</label>
                            <select name="po_state" class="form-select">
                                <option value="">All States</option>
                                <option value="DRAFT" <?= $poState === 'DRAFT' ? 'selected' : '' ?>>Draft</option>
                                <option value="PENDING_APPROVAL" <?= $poState === 'PENDING_APPROVAL' ? 'selected' : '' ?>>
                                    Pending Approval
                                </option>
                                <option value="APPROVED" <?= $poState === 'APPROVED' ? 'selected' : '' ?>>Approved</option>
                                <option value="SENT" <?= $poState === 'SENT' ? 'selected' : '' ?>>Sent</option>
                                <option value="RECEIVING" <?= $poState === 'RECEIVING' ? 'selected' : '' ?>>Receiving</option>
                                <option value="COMPLETE" <?= $poState === 'COMPLETE' ? 'selected' : '' ?>>Complete</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Search (PO ID or Supplier)</label>
                            <input type="text"
                                   name="search"
                                   class="form-control"
                                   placeholder="Search..."
                                   value="<?= htmlspecialchars($search) ?>">
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-search me-2"></i>Apply Filters
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- History Table -->
    <div class="card">
        <div class="card-body">
            <?php if (empty($approvals)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-inbox text-muted" style="font-size: 4rem;"></i>
                    <h4 class="mt-3">No approval history found</h4>
                    <p class="text-muted">
                        <?php if (!empty(array_filter([$dateFrom, $dateTo, $approverId, $action, $tier, $poState, $search]))): ?>
                            Try adjusting your filters or <a href="?">clearing all filters</a>.
                        <?php else: ?>
                            Approval actions will appear here once purchase orders are reviewed.
                        <?php endif; ?>
                    </p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Date/Time</th>
                                <th>PO ID</th>
                                <th>Supplier</th>
                                <th class="text-end">Amount</th>
                                <th>Approver</th>
                                <th>Tier</th>
                                <th>Action</th>
                                <th>PO State</th>
                                <th width="200">Comments</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($approvals as $approval): ?>
                                <tr>
                                    <td>
                                        <small class="d-block"><?= date('Y-m-d', strtotime($approval['updated_at'])) ?></small>
                                        <small class="text-muted"><?= date('H:i:s', strtotime($approval['updated_at'])) ?></small>
                                    </td>
                                    <td>
                                        <a href="/modules/consignments/purchase-orders/view.php?id=<?= $approval['po_id'] ?>"
                                           class="text-decoration-none">
                                            <strong><?= htmlspecialchars($approval['public_id']) ?></strong>
                                        </a>
                                    </td>
                                    <td><?= htmlspecialchars($approval['supplier_name']) ?></td>
                                    <td class="text-end">
                                        <strong>$<?= number_format($approval['total_cost'], 2) ?></strong>
                                    </td>
                                    <td><?= htmlspecialchars($approval['approver_name']) ?></td>
                                    <td>
                                        <span class="badge bg-info">Tier <?= $approval['tier'] ?></span>
                                    </td>
                                    <td>
                                        <?php
                                        $badgeClass = match($approval['status']) {
                                            'APPROVED' => 'bg-success',
                                            'REJECTED' => 'bg-danger',
                                            'REQUEST_CHANGES' => 'bg-warning',
                                            default => 'bg-secondary'
                                        };
                                        $badgeText = match($approval['status']) {
                                            'APPROVED' => 'Approved',
                                            'REJECTED' => 'Rejected',
                                            'REQUEST_CHANGES' => 'Changes Requested',
                                            default => $approval['status']
                                        };
                                        ?>
                                        <span class="badge <?= $badgeClass ?>">
                                            <i class="fas fa-<?= $approval['status'] === 'APPROVED' ? 'check' : 'times' ?> me-1"></i>
                                            <?= $badgeText ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php
                                        $stateBadgeClass = match($approval['po_state']) {
                                            'DRAFT' => 'bg-secondary',
                                            'OPEN' => 'bg-primary',
                                            'PENDING_APPROVAL' => 'bg-warning',
                                            'APPROVED' => 'bg-success',
                                            'SENT' => 'bg-info',
                                            'RECEIVING' => 'bg-purple',
                                            'COMPLETE' => 'bg-dark',
                                            default => 'bg-secondary'
                                        };
                                        ?>
                                        <span class="badge <?= $stateBadgeClass ?>">
                                            <?= htmlspecialchars($approval['po_state']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($approval['comments']): ?>
                                            <span class="text-truncate d-inline-block"
                                                  style="max-width: 200px;"
                                                  data-bs-toggle="tooltip"
                                                  title="<?= htmlspecialchars($approval['comments']) ?>">
                                                <?= htmlspecialchars($approval['comments']) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <nav aria-label="Approval history pagination" class="mt-4">
                        <ul class="pagination justify-content-center">
                            <!-- Previous -->
                            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                <a class="page-link" href="?page=<?= $page - 1 ?><?= http_build_query(array_filter($_GET)) ? '&' . http_build_query(array_filter(array_diff_key($_GET, ['page' => '']))) : '' ?>">
                                    Previous
                                </a>
                            </li>

                            <!-- Pages -->
                            <?php
                            $startPage = max(1, $page - 2);
                            $endPage = min($totalPages, $page + 2);

                            if ($startPage > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=1<?= http_build_query(array_filter($_GET)) ? '&' . http_build_query(array_filter(array_diff_key($_GET, ['page' => '']))) : '' ?>">1</a>
                                </li>
                                <?php if ($startPage > 2): ?>
                                    <li class="page-item disabled"><span class="page-link">...</span></li>
                                <?php endif; ?>
                            <?php endif; ?>

                            <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                    <a class="page-link" href="?page=<?= $i ?><?= http_build_query(array_filter($_GET)) ? '&' . http_build_query(array_filter(array_diff_key($_GET, ['page' => '']))) : '' ?>">
                                        <?= $i ?>
                                    </a>
                                </li>
                            <?php endfor; ?>

                            <?php if ($endPage < $totalPages): ?>
                                <?php if ($endPage < $totalPages - 1): ?>
                                    <li class="page-item disabled"><span class="page-link">...</span></li>
                                <?php endif; ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?= $totalPages ?><?= http_build_query(array_filter($_GET)) ? '&' . http_build_query(array_filter(array_diff_key($_GET, ['page' => '']))) : '' ?>">
                                        <?= $totalPages ?>
                                    </a>
                                </li>
                            <?php endif; ?>

                            <!-- Next -->
                            <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                                <a class="page-link" href="?page=<?= $page + 1 ?><?= http_build_query(array_filter($_GET)) ? '&' . http_build_query(array_filter(array_diff_key($_GET, ['page' => '']))) : '' ?>">
                                    Next
                                </a>
                            </li>
                        </ul>
                    </nav>

                    <p class="text-center text-muted">
                        Showing <?= $offset + 1 ?> to <?= min($offset + $perPage, $totalRecords) ?> of <?= number_format($totalRecords) ?> records
                    </p>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips
    const tooltips = document.querySelectorAll('[data-bs-toggle="tooltip"]');
    tooltips.forEach(el => new bootstrap.Tooltip(el));

    // Export CSV button
    document.getElementById('exportBtn').addEventListener('click', async function() {
        const btn = this;
        const originalHTML = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Exporting...';

        // Build export URL with current filters
        const params = new URLSearchParams(window.location.search);
        params.set('export', '1');

        try {
            const response = await fetch('?' + params.toString());
            const blob = await response.blob();

            // Create download link
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'approval-history-' + new Date().toISOString().split('T')[0] + '.csv';
            document.body.appendChild(a);
            a.click();
            window.URL.revokeObjectURL(url);
            document.body.removeChild(a);

            btn.disabled = false;
            btn.innerHTML = originalHTML;
        } catch (error) {
            alert('Export failed: ' + error.message);
            btn.disabled = false;
            btn.innerHTML = originalHTML;
        }
    });
});
</script>

<?php
// CSV Export
if (isset($_GET['export']) && $_GET['export'] === '1') {
    // Re-query without limit for full export
    $exportSQL = str_replace("LIMIT $perPage OFFSET $offset", '', $sql);
    $stmt = $db->prepare($exportSQL);
    $stmt->execute($params);
    $allApprovals = $stmt->fetchAll(PDO::FETCH_ASSOC);

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="approval-history-' . date('Y-m-d') . '.csv"');

    $output = fopen('php://output', 'w');

    // Header row
    fputcsv($output, [
        'Date/Time',
        'PO ID',
        'Supplier',
        'Amount',
        'Approver',
        'Tier',
        'Action',
        'PO State',
        'Comments'
    ]);

    // Data rows
    foreach ($allApprovals as $approval) {
        fputcsv($output, [
            $approval['updated_at'],
            $approval['public_id'],
            $approval['supplier_name'],
            $approval['total_cost'],
            $approval['approver_name'],
            $approval['tier'],
            $approval['status'],
            $approval['po_state'],
            $approval['comments']
        ]);
    }

    fclose($output);
    exit;
}

// Include footer
include $_SERVER['DOCUMENT_ROOT'] . '/modules/consignments/shared/blocks/footer.php';
?>
