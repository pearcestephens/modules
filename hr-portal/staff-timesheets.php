<?php
/**
 * STAFF TIMESHEETS - Detailed timesheet view for individual staff
 * Shows all timesheet amendments with Deputy sync status
 */

require_once __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/includes/DeputyIntegration.php';
require_once __DIR__ . '/includes/AIPayrollEngine.php';

if (!isset($_SESSION['userID'])) {
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

$deputy = new DeputyIntegration($pdo);
$aiEngine = new AIPayrollEngine($pdo);

// Build WHERE clause for status filter
$whereClause = "ta.staff_id = :staff_id";
$params = ['staff_id' => $staffId];

if ($status !== 'all') {
    $whereClause .= " AND ta.status = :status";
    $params['status'] = $status;
}

// Get timesheet amendments with sync status
$stmt = $pdo->prepare("
    SELECT
        ta.*,
        s.first_name, s.last_name,
        isl.status as sync_status,
        isl.external_id as deputy_timesheet_id,
        isl.created_at as synced_at,
        d.decision as ai_decision,
        d.confidence,
        d.reasoning as ai_reasoning
    FROM payroll_timesheet_amendments ta
    INNER JOIN staff s ON ta.staff_id = s.id
    LEFT JOIN integration_sync_log isl ON isl.item_type = 'timesheet' AND isl.item_id = ta.id AND isl.integration_name = 'deputy'
    LEFT JOIN payroll_ai_decisions d ON d.item_type = 'timesheet' AND d.item_id = ta.id
    WHERE $whereClause
    ORDER BY ta.created_at DESC
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$timesheets = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get total count for pagination
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM payroll_timesheet_amendments ta WHERE $whereClause
");
$stmt->execute($params);
$totalTimesheets = $stmt->fetchColumn();
$totalPages = ceil($totalTimesheets / $perPage);

$pageTitle = $staff['first_name'] . ' ' . $staff['last_name'] . ' - Timesheets';
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
            <li class="breadcrumb-item active">Timesheets</li>
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
                        <?php if ($staff['deputy_id']): ?>
                            <span class="badge bg-primary me-2">
                                <i class="fas fa-clock"></i> Deputy ID: <?php echo $staff['deputy_id']; ?>
                            </span>
                        <?php endif; ?>
                        Total Timesheets: <?php echo $totalTimesheets; ?>
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
                        <i class="fas fa-sync"></i> Sync All Approved to Deputy
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Timesheets Table -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0"><i class="fas fa-clock"></i> Timesheet Amendments</h5>
        </div>
        <div class="card-body">
            <?php if (empty($timesheets)): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> No timesheet amendments found.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Original Hours</th>
                                <th>New Hours</th>
                                <th>Difference</th>
                                <th>Reason</th>
                                <th>Status</th>
                                <th>AI Decision</th>
                                <th>Deputy Sync</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($timesheets as $ts): ?>
                                <?php
                                $diff = $ts['new_hours'] - $ts['original_hours'];
                                $diffClass = $diff > 0 ? 'text-success' : ($diff < 0 ? 'text-danger' : 'text-muted');
                                $diffIcon = $diff > 0 ? 'arrow-up' : ($diff < 0 ? 'arrow-down' : 'minus');
                                ?>
                                <tr>
                                    <td>
                                        <strong><?php echo date('d M Y', strtotime($ts['timesheet_date'])); ?></strong><br>
                                        <small class="text-muted"><?php echo date('D', strtotime($ts['timesheet_date'])); ?></small>
                                    </td>
                                    <td><?php echo number_format($ts['original_hours'], 2); ?>h</td>
                                    <td><?php echo number_format($ts['new_hours'], 2); ?>h</td>
                                    <td>
                                        <span class="<?php echo $diffClass; ?>">
                                            <i class="fas fa-<?php echo $diffIcon; ?>"></i>
                                            <?php echo ($diff > 0 ? '+' : '') . number_format($diff, 2); ?>h
                                        </span>
                                    </td>
                                    <td>
                                        <span title="<?php echo htmlspecialchars($ts['reason']); ?>">
                                            <?php echo htmlspecialchars(substr($ts['reason'], 0, 40)); ?>
                                            <?php if (strlen($ts['reason']) > 40) echo '...'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($ts['status'] === 'pending'): ?>
                                            <span class="badge bg-warning">Pending</span>
                                        <?php elseif ($ts['status'] === 'approved'): ?>
                                            <span class="badge bg-success">Approved</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Denied</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($ts['ai_decision']): ?>
                                            <span class="badge bg-<?php echo $ts['ai_decision'] === 'approve' ? 'success' : 'danger'; ?>">
                                                AI: <?php echo ucfirst($ts['ai_decision']); ?>
                                                (<?php echo round($ts['confidence']); ?>%)
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">No AI</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($ts['sync_status'] === 'success'): ?>
                                            <span class="badge bg-success">
                                                <i class="fas fa-check"></i> Synced
                                            </span><br>
                                            <small class="text-muted">ID: <?php echo $ts['deputy_timesheet_id']; ?></small>
                                        <?php elseif ($ts['sync_status'] === 'error'): ?>
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
                                                    onclick="viewDetails(<?php echo $ts['id']; ?>)"
                                                    title="View full details">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <?php if ($ts['status'] === 'approved' && $ts['sync_status'] !== 'success'): ?>
                                                <button class="btn btn-outline-success"
                                                        onclick="syncToDeputy(<?php echo $ts['id']; ?>)"
                                                        title="Sync to Deputy">
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
    // TODO: Show modal with full timesheet details
    alert('View timesheet details: ' + id);
}

function syncToDeputy(id) {
    if (!confirm('Sync this timesheet amendment to Deputy?')) return;

    fetch(`api/sync-timesheet.php?id=${id}`)
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
    if (!confirm('Sync all approved timesheets to Deputy? This may take a few minutes.')) return;

    const staffId = <?php echo $staffId; ?>;
    const btn = event.target;
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Syncing...';

    fetch(`api/sync-timesheet.php?staff_id=${staffId}&sync_all=1`)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                alert(`Success! Synced ${data.synced} timesheets.`);
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
</script>

<?php include __DIR__ . '/../../assets/template/footer.php'; ?>
