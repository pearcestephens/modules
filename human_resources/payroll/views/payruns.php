<?php
/**
 * Pay Run List View
 * Displays all pay runs with filtering and search
 *
 * Following payroll-process.php UI patterns
 *
 * @var array $payRuns
 * @var array $stats
 * @var int $currentPage
 * @var int $totalPages
 */

// Include layout header
require_once __DIR__ . '/layouts/header.php';
?>

<div class="container-fluid py-4">
    <!-- Header Section -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">Pay Runs</h1>
            <p class="text-muted mb-0">Manage payroll processing and payslips</p>
        </div>
        <div>
            <button type="button" class="btn btn-primary" onclick="createNewPayRun()">
                <i class="fas fa-plus"></i> Create New Pay Run
            </button>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-grow-1">
                            <h6 class="text-muted mb-1">Draft</h6>
                            <h3 class="mb-0"><?= $stats['draft'] ?? 0 ?></h3>
                        </div>
                        <div class="ms-3">
                            <div class="icon-shape bg-warning bg-opacity-10 text-warning rounded-circle">
                                <i class="fas fa-edit"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-grow-1">
                            <h6 class="text-muted mb-1">Calculated</h6>
                            <h3 class="mb-0"><?= $stats['calculated'] ?? 0 ?></h3>
                        </div>
                        <div class="ms-3">
                            <div class="icon-shape bg-info bg-opacity-10 text-info rounded-circle">
                                <i class="fas fa-calculator"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-grow-1">
                            <h6 class="text-muted mb-1">Approved</h6>
                            <h3 class="mb-0"><?= $stats['approved'] ?? 0 ?></h3>
                        </div>
                        <div class="ms-3">
                            <div class="icon-shape bg-success bg-opacity-10 text-success rounded-circle">
                                <i class="fas fa-check-circle"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-grow-1">
                            <h6 class="text-muted mb-1">Total Amount</h6>
                            <h3 class="mb-0">$<?= number_format($stats['total_amount'] ?? 0, 2) ?></h3>
                        </div>
                        <div class="ms-3">
                            <div class="icon-shape bg-primary bg-opacity-10 text-primary rounded-circle">
                                <i class="fas fa-dollar-sign"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label small">Status</label>
                    <select class="form-select form-select-sm" id="filterStatus" onchange="filterPayRuns()">
                        <option value="">All Status</option>
                        <option value="calculated">Calculated</option>
                        <option value="reviewed">Reviewed</option>
                        <option value="approved">Approved</option>
                        <option value="exported">Exported</option>
                        <option value="paid">Paid</option>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label small">Year</label>
                    <select class="form-select form-select-sm" id="filterYear" onchange="filterPayRuns()">
                        <option value="">All Years</option>
                        <?php for ($year = date('Y'); $year >= 2020; $year--): ?>
                            <option value="<?= $year ?>"><?= $year ?></option>
                        <?php endfor; ?>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label small">Search</label>
                    <input type="text" class="form-control form-control-sm" id="searchPayRuns"
                           placeholder="Search by period, employee..." onkeyup="filterPayRuns()">
                </div>

                <div class="col-md-2">
                    <label class="form-label small">&nbsp;</label>
                    <button type="button" class="btn btn-sm btn-outline-secondary w-100" onclick="clearFilters()">
                        <i class="fas fa-times"></i> Clear
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Pay Runs Table -->
    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle" id="payRunsTable">
                    <thead class="table-light">
                        <tr>
                            <th>Period</th>
                            <th>Employees</th>
                            <th>Gross Pay</th>
                            <th>Net Pay</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($payRuns)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-5">
                                    <div class="text-muted">
                                        <i class="fas fa-inbox fa-3x mb-3"></i>
                                        <p>No pay runs found</p>
                                        <button type="button" class="btn btn-primary btn-sm" onclick="createNewPayRun()">
                                            Create First Pay Run
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($payRuns as $payRun): ?>
                                <tr data-payrun-id="<?= $payRun['period_key'] ?>">
                                    <td>
                                        <div class="fw-bold">
                                            <?= date('M d', strtotime($payRun['period_start'])) ?> -
                                            <?= date('M d, Y', strtotime($payRun['period_end'])) ?>
                                        </div>
                                        <small class="text-muted">
                                            Week <?= date('W', strtotime($payRun['period_start'])) ?>
                                        </small>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary rounded-pill">
                                            <?= $payRun['employee_count'] ?> employees
                                        </span>
                                    </td>
                                    <td class="fw-bold">
                                        $<?= number_format($payRun['total_gross'], 2) ?>
                                    </td>
                                    <td class="fw-bold text-success">
                                        $<?= number_format($payRun['total_net'], 2) ?>
                                    </td>
                                    <td>
                                        <?php
                                        $statusColors = [
                                            'calculated' => 'info',
                                            'reviewed' => 'primary',
                                            'approved' => 'success',
                                            'exported' => 'warning',
                                            'paid' => 'success',
                                            'cancelled' => 'danger'
                                        ];
                                        $color = $statusColors[$payRun['status']] ?? 'secondary';
                                        ?>
                                        <span class="badge bg-<?= $color ?>">
                                            <?= ucfirst($payRun['status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <small class="text-muted">
                                            <?= date('M d, Y', strtotime($payRun['created_at'])) ?>
                                        </small>
                                    </td>
                                    <td class="text-end">
                                        <div class="btn-group btn-group-sm" role="group">
                                            <button type="button" class="btn btn-outline-primary"
                                                    onclick="viewPayRun('<?= $payRun['period_key'] ?>')"
                                                    title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </button>

                                            <?php if ($payRun['status'] === 'calculated'): ?>
                                                <button type="button" class="btn btn-outline-success"
                                                        onclick="approvePayRun('<?= $payRun['period_key'] ?>')"
                                                        title="Approve">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                            <?php endif; ?>

                                            <?php if ($payRun['status'] === 'approved'): ?>
                                                <button type="button" class="btn btn-outline-warning"
                                                        onclick="exportPayRun('<?= $payRun['period_key'] ?>')"
                                                        title="Export to Xero">
                                                    <i class="fas fa-download"></i>
                                                </button>
                                            <?php endif; ?>

                                            <button type="button" class="btn btn-outline-secondary"
                                                    onclick="printPayRun('<?= $payRun['period_key'] ?>')"
                                                    title="Print">
                                                <i class="fas fa-print"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <nav aria-label="Pay run pagination" class="mt-4">
                    <ul class="pagination pagination-sm justify-content-center mb-0">
                        <li class="page-item <?= $currentPage <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="?view=payruns&page=<?= $currentPage - 1 ?>">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        </li>

                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <?php if ($i === 1 || $i === $totalPages || abs($i - $currentPage) <= 2): ?>
                                <li class="page-item <?= $i === $currentPage ? 'active' : '' ?>">
                                    <a class="page-link" href="?view=payruns&page=<?= $i ?>"><?= $i ?></a>
                                </li>
                            <?php elseif (abs($i - $currentPage) === 3): ?>
                                <li class="page-item disabled"><span class="page-link">...</span></li>
                            <?php endif; ?>
                        <?php endfor; ?>

                        <li class="page-item <?= $currentPage >= $totalPages ? 'disabled' : '' ?>">
                            <a class="page-link" href="?view=payruns&page=<?= $currentPage + 1 ?>">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Create New Pay Run Modal -->
<div class="modal fade" id="createPayRunModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Create New Pay Run</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="createPayRunForm">
                    <div class="mb-3">
                        <label class="form-label">Pay Period Start</label>
                        <input type="date" class="form-control" name="period_start" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Pay Period End</label>
                        <input type="date" class="form-control" name="period_end" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description (Optional)</label>
                        <textarea class="form-control" name="description" rows="2"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="submitCreatePayRun()">
                    <i class="fas fa-plus"></i> Create Pay Run
                </button>
            </div>
        </div>
    </div>
</div>

<style>
.icon-shape {
    width: 48px;
    height: 48px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
}
</style>

<script>
// Pay Run List functionality
function createNewPayRun() {
    const modal = new bootstrap.Modal(document.getElementById('createPayRunModal'));
    modal.show();
}

function submitCreatePayRun() {
    const form = document.getElementById('createPayRunForm');
    const formData = new FormData(form);

    fetch('?api=payruns/create', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('Pay run created successfully', 'success');
            location.reload();
        } else {
            showNotification(data.error || 'Failed to create pay run', 'error');
        }
    })
    .catch(error => {
        showNotification('Network error: ' + error.message, 'error');
    });
}

function viewPayRun(periodKey) {
    window.location.href = '?view=payrun&period=' + encodeURIComponent(periodKey);
}

function approvePayRun(periodKey) {
    if (!confirm('Are you sure you want to approve this pay run?')) {
        return;
    }

    fetch('?api=payruns/approve', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({period: periodKey})
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('Pay run approved successfully', 'success');
            location.reload();
        } else {
            showNotification(data.error || 'Failed to approve pay run', 'error');
        }
    });
}

function exportPayRun(periodKey) {
    if (!confirm('Export this pay run to Xero?')) {
        return;
    }

    showNotification('Exporting to Xero...', 'info');

    fetch('?api=payruns/export', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({period: periodKey})
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('Pay run exported to Xero successfully', 'success');
            location.reload();
        } else {
            showNotification(data.error || 'Failed to export pay run', 'error');
        }
    });
}

function printPayRun(periodKey) {
    window.open('?view=payrun&period=' + encodeURIComponent(periodKey) + '&print=1', '_blank');
}

function filterPayRuns() {
    const status = document.getElementById('filterStatus').value;
    const year = document.getElementById('filterYear').value;
    const search = document.getElementById('searchPayRuns').value.toLowerCase();

    const rows = document.querySelectorAll('#payRunsTable tbody tr');
    rows.forEach(row => {
        const periodCell = row.querySelector('td:first-child');
        const statusCell = row.querySelector('td:nth-child(5)');

        if (!periodCell || !statusCell) return;

        const periodText = periodCell.textContent;
        const statusText = statusCell.textContent.toLowerCase().trim();
        const matchesStatus = !status || statusText === status;
        const matchesYear = !year || periodText.includes(year);
        const matchesSearch = !search || periodText.toLowerCase().includes(search);

        row.style.display = (matchesStatus && matchesYear && matchesSearch) ? '' : 'none';
    });
}

function clearFilters() {
    document.getElementById('filterStatus').value = '';
    document.getElementById('filterYear').value = '';
    document.getElementById('searchPayRuns').value = '';
    filterPayRuns();
}

function showNotification(message, type = 'info') {
    // Bootstrap toast or alert
    alert(message); // Placeholder - integrate with your notification system
}
</script>

<?php require_once __DIR__ . '/layouts/footer.php'; ?>
