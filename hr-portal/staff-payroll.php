<?php
/**
 * STAFF PAYROLL - Detailed payroll view for individual staff
 * Shows all payrun amendments with Xero sync status
 */

require_once __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/includes/XeroIntegration.php';
require_once __DIR__ . '/includes/AIPayrollEngine.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

$staffId = $_GET['id'] ?? 0;
$page = $_GET['page'] ?? 1;
$status = $_GET['status'] ?? 'all';
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Get staff info
$stmt = $pdo->prepare("SELECT * FROM staff WHERE id = ?");
$stmt->execute([$staffId]);
$staff = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$staff) {
    die('Staff member not found');
}

$xero = new XeroIntegration($pdo);
$aiEngine = new AIPayrollEngine($pdo);

// Build WHERE clause
$whereClause = "pa.staff_id = :staff_id";
$params = ['staff_id' => $staffId];

if ($status !== 'all') {
    $whereClause .= " AND pa.status = :status";
    $params['status'] = $status;
}

// Get payrun amendments with sync status
$stmt = $pdo->prepare("
    SELECT
        pa.*,
        s.first_name, s.last_name,
        isl.status as sync_status,
        isl.external_id as xero_payrun_id,
        isl.created_at as synced_at,
        d.decision as ai_decision,
        d.confidence,
        d.reasoning as ai_reasoning
    FROM payroll_payrun_amendments pa
    INNER JOIN staff s ON pa.staff_id = s.id
    LEFT JOIN integration_sync_log isl ON isl.item_type = 'payrun' AND isl.item_id = pa.id AND isl.integration_name = 'xero'
    LEFT JOIN payroll_ai_decisions d ON d.item_type = 'payrun' AND d.item_id = pa.id
    WHERE $whereClause
    ORDER BY pa.created_at DESC
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$payruns = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get total count
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM payroll_payrun_amendments pa WHERE $whereClause
");
$stmt->execute($params);
$totalPayruns = $stmt->fetchColumn();
$totalPages = ceil($totalPayruns / $perPage);

// Calculate YTD summary
$stmt = $pdo->prepare("
    SELECT
        SUM(CASE WHEN status = 'approved' THEN adjustment_amount ELSE 0 END) as total_adjustments,
        COUNT(*) as total_amendments,
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_count
    FROM payroll_payrun_amendments
    WHERE staff_id = ?
    AND YEAR(created_at) = YEAR(NOW())
");
$stmt->execute([$staffId]);
$ytdSummary = $stmt->fetch(PDO::FETCH_ASSOC);

$pageTitle = $staff['first_name'] . ' ' . $staff['last_name'] . ' - Payroll';
include __DIR__ . '/../../assets/template/html-header.php';
include __DIR__ . '/../../assets/template/header.php';
?>

<div class="container-fluid mt-4">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="index.php">HR Portal</a></li>
            <li class="breadcrumb-item"><a href="staff-directory.php">Staff Directory</a></li>
            <li class="breadcrumb-item"><a href="staff-detail.php?id=<?php echo $staffId; ?>">
                <?php echo htmlspecialchars($staff['first_name'] . ' ' . $staff['last_name']); ?>
            </a></li>
            <li class="breadcrumb-item active">Payroll</li>
        </ol>
    </nav>

    <!-- Staff Header -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row align-items-center">
                <div class="col-auto">
                    <div class="avatar-large">
                        <?php
                        $initials = strtoupper(substr($staff['first_name'], 0, 1) . substr($staff['last_name'], 0, 1));
                        echo $initials;
                        ?>
                    </div>
                </div>
                <div class="col">
                    <h3 class="mb-1"><?php echo htmlspecialchars($staff['first_name'] . ' ' . $staff['last_name']); ?></h3>
                    <p class="text-muted mb-0">
                        <?php if ($staff['xero_id']): ?>
                            <span class="badge bg-info me-2">
                                <i class="fas fa-dollar-sign"></i> Xero ID: <?php echo $staff['xero_id']; ?>
                            </span>
                        <?php endif; ?>
                        Total Pay Runs: <?php echo $totalPayruns; ?>
                    </p>
                </div>
                <div class="col-auto">
                    <a href="staff-detail.php?id=<?php echo $staffId; ?>" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Profile
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- YTD Summary -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card border-primary">
                <div class="card-body text-center">
                    <h6 class="text-muted mb-2">YTD Adjustments</h6>
                    <h3 class="mb-0">$<?php echo number_format($ytdSummary['total_adjustments'], 2); ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-success">
                <div class="card-body text-center">
                    <h6 class="text-muted mb-2">Approved Amendments</h6>
                    <h3 class="mb-0"><?php echo $ytdSummary['approved_count']; ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-info">
                <div class="card-body text-center">
                    <h6 class="text-muted mb-2">Total Amendments</h6>
                    <h3 class="mb-0"><?php echo $ytdSummary['total_amendments']; ?></h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="get" class="row g-3">
                <input type="hidden" name="id" value="<?php echo $staffId; ?>">
                <div class="col-md-4">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select" onchange="this.form.submit()">
                        <option value="all" <?php echo $status === 'all' ? 'selected' : ''; ?>>All Statuses</option>
                        <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Pending Review</option>
                        <option value="approved" <?php echo $status === 'approved' ? 'selected' : ''; ?>>Approved</option>
                        <option value="denied" <?php echo $status === 'denied' ? 'selected' : ''; ?>>Denied</option>
                    </select>
                </div>
                <div class="col-md-8 text-end align-self-end">
                    <button type="button" class="btn btn-primary" onclick="syncAllApproved()">
                        <i class="fas fa-sync"></i> Sync All Approved to Xero
                    </button>
                    <button type="button" class="btn btn-outline-secondary" onclick="exportPayrollReport()">
                        <i class="fas fa-download"></i> Export Report
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Payrun Amendments Table -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0"><i class="fas fa-dollar-sign"></i> Payrun Amendments</h5>
        </div>
        <div class="card-body">
            <?php if (empty($payruns)): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> No payrun amendments found.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Original Amount</th>
                                <th>Adjustment</th>
                                <th>New Amount</th>
                                <th>Reason</th>
                                <th>Status</th>
                                <th>AI Decision</th>
                                <th>Xero Sync</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($payruns as $pr): ?>
                                <?php
                                $newAmount = $pr['original_amount'] + $pr['adjustment_amount'];
                                $adjClass = $pr['adjustment_amount'] > 0 ? 'text-success' : ($pr['adjustment_amount'] < 0 ? 'text-danger' : 'text-muted');
                                $adjIcon = $pr['adjustment_amount'] > 0 ? 'arrow-up' : ($pr['adjustment_amount'] < 0 ? 'arrow-down' : 'minus');
                                ?>
                                <tr>
                                    <td>
                                        <strong><?php echo date('d M Y', strtotime($pr['created_at'])); ?></strong><br>
                                        <small class="text-muted"><?php echo $pr['pay_period']; ?></small>
                                    </td>
                                    <td>$<?php echo number_format($pr['original_amount'], 2); ?></td>
                                    <td>
                                        <span class="<?php echo $adjClass; ?>">
                                            <i class="fas fa-<?php echo $adjIcon; ?>"></i>
                                            <?php echo ($pr['adjustment_amount'] > 0 ? '+' : '') . '$' . number_format($pr['adjustment_amount'], 2); ?>
                                        </span>
                                    </td>
                                    <td><strong>$<?php echo number_format($newAmount, 2); ?></strong></td>
                                    <td>
                                        <span title="<?php echo htmlspecialchars($pr['reason']); ?>">
                                            <?php echo htmlspecialchars(substr($pr['reason'], 0, 40)); ?>
                                            <?php if (strlen($pr['reason']) > 40) echo '...'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($pr['status'] === 'pending'): ?>
                                            <span class="badge bg-warning">Pending</span>
                                        <?php elseif ($pr['status'] === 'approved'): ?>
                                            <span class="badge bg-success">Approved</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Denied</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($pr['ai_decision']): ?>
                                            <span class="badge bg-<?php echo $pr['ai_decision'] === 'approve' ? 'success' : 'danger'; ?>">
                                                AI: <?php echo ucfirst($pr['ai_decision']); ?>
                                                (<?php echo round($pr['confidence']); ?>%)
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">No AI</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($pr['sync_status'] === 'success'): ?>
                                            <span class="badge bg-success">
                                                <i class="fas fa-check"></i> Synced
                                            </span><br>
                                            <small class="text-muted">ID: <?php echo $pr['xero_payrun_id']; ?></small>
                                        <?php elseif ($pr['sync_status'] === 'error'): ?>
                                            <span class="badge bg-danger">
                                                <i class="fas fa-times"></i> Error
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Not Synced</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <button class="btn btn-outline-primary"
                                                    onclick="viewDetails(<?php echo $pr['id']; ?>)"
                                                    title="View full details">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <?php if ($pr['status'] === 'approved' && $pr['sync_status'] !== 'success'): ?>
                                                <button class="btn btn-outline-success"
                                                        onclick="syncToXero(<?php echo $pr['id']; ?>)"
                                                        title="Sync to Xero">
                                                    <i class="fas fa-sync"></i>
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
                    <nav>
                        <ul class="pagination justify-content-center">
                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <li class="page-item <?php echo $i === (int)$page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?id=<?php echo $staffId; ?>&status=<?php echo $status; ?>&page=<?php echo $i; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                        </ul>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.avatar-large {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 28px;
    font-weight: bold;
}
</style>

<script>
function viewDetails(id) {
    // TODO: Show modal with full payrun details
    alert('View payrun details: ' + id);
}

function syncToXero(id) {
    if (!confirm('Sync this payrun amendment to Xero?')) return;

    fetch(`api/sync-payrun.php?id=${id}`)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                alert('Synced successfully!');
                location.reload();
            } else {
                alert('Sync failed: ' + data.error);
            }
        })
        .catch(e => alert('Error: ' + e.message));
}

function syncAllApproved() {
    if (!confirm('Sync all approved payruns to Xero? This may take a few minutes.')) return;

    const staffId = <?php echo $staffId; ?>;
    const btn = event.target;
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Syncing...';

    fetch(`api/sync-payrun.php?staff_id=${staffId}&sync_all=1`)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                alert(`Success! Synced ${data.synced} payruns.`);
                location.reload();
            } else {
                alert('Sync failed: ' + data.error);
            }
        })
        .catch(e => alert('Error: ' + e.message))
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML = originalText;
        });
}

function exportPayrollReport() {
    const staffId = <?php echo $staffId; ?>;
    window.location.href = `api/export-payroll.php?staff_id=${staffId}`;
}
</script>

<?php include __DIR__ . '/../../assets/template/footer.php'; ?>
