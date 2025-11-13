<?php
/**
 * STAFF DETAIL PAGE
 * Complete view of staff member with all payroll data
 */

require_once __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/includes/DeputyIntegration.php';
require_once __DIR__ . '/includes/XeroIntegration.php';
require_once __DIR__ . '/includes/AIPayrollEngine.php';
require_once __DIR__ . '/includes/PayrollDashboard.php';

if (!isset($_SESSION['user_id']) || !isset($_GET['id'])) {
    header('Location: /login.php');
    exit;
}

$staffId = (int)$_GET['id'];
$deputy = new DeputyIntegration($pdo);
$xero = new XeroIntegration($pdo);
$aiEngine = new AIPayrollEngine($pdo);
$dashboard = new PayrollDashboard($pdo, $aiEngine);

// Get staff member
$stmt = $pdo->prepare("SELECT * FROM staff WHERE id = ?");
$stmt->execute([$staffId]);
$staff = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$staff) {
    die('Staff member not found');
}

// Get recent timesheets
$stmt = $pdo->prepare("
    SELECT * FROM payroll_timesheet_amendments
    WHERE staff_id = ?
    ORDER BY created_at DESC
    LIMIT 10
");
$stmt->execute([$staffId]);
$timesheets = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent pay adjustments
$stmt = $pdo->prepare("
    SELECT * FROM payroll_payrun_amendments
    WHERE staff_id = ?
    ORDER BY created_at DESC
    LIMIT 10
");
$stmt->execute([$staffId]);
$payruns = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get AI decisions for this staff
$stmt = $pdo->prepare("
    SELECT d.*,
        CASE
            WHEN d.item_type = 'timesheet' THEN ta.reason
            WHEN d.item_type = 'payrun' THEN pa.reason
        END as item_reason
    FROM payroll_ai_decisions d
    LEFT JOIN payroll_timesheet_amendments ta ON d.item_type = 'timesheet' AND d.item_id = ta.id
    LEFT JOIN payroll_payrun_amendments pa ON d.item_type = 'payrun' AND d.item_id = pa.id
    WHERE (ta.staff_id = ? OR pa.staff_id = ?)
    ORDER BY d.created_at DESC
    LIMIT 20
");
$stmt->execute([$staffId, $staffId]);
$aiDecisions = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Staff Detail: ' . htmlspecialchars($staff['name']);
include __DIR__ . '/../../assets/template/html-header.php';
include __DIR__ . '/../../assets/template/header.php';
?>

<div class="container-fluid mt-4">
    <!-- Staff Header -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row">
                <div class="col-md-2 text-center">
                    <div class="avatar-large">
                        <?php echo strtoupper(substr($staff['name'], 0, 2)); ?>
                    </div>
                </div>
                <div class="col-md-7">
                    <h2><?php echo htmlspecialchars($staff['name']); ?></h2>
                    <p class="text-muted mb-2"><?php echo htmlspecialchars($staff['email'] ?? 'No email'); ?></p>
                    <p class="mb-0">
                        <strong>Phone:</strong> <?php echo htmlspecialchars($staff['phone'] ?? 'N/A'); ?><br>
                        <strong>Status:</strong>
                        <span class="badge <?php echo $staff['active'] ? 'bg-success' : 'bg-secondary'; ?>">
                            <?php echo $staff['active'] ? 'Active' : 'Inactive'; ?>
                        </span>
                    </p>
                    <div class="mt-3">
                        <?php if ($staff['deputy_employee_id']): ?>
                            <span class="badge bg-success">
                                <i class="fas fa-link"></i> Deputy ID: <?php echo $staff['deputy_employee_id']; ?>
                            </span>
                        <?php endif; ?>
                        <?php if ($staff['xero_employee_id']): ?>
                            <span class="badge bg-success">
                                <i class="fas fa-link"></i> Xero ID: <?php echo substr($staff['xero_employee_id'], 0, 8); ?>...
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-md-3 text-end">
                    <a href="staff-directory.php" class="btn btn-secondary mb-2">
                        <i class="fas fa-arrow-left"></i> Back to Directory
                    </a><br>
                    <a href="staff-timesheets.php?id=<?php echo $staffId; ?>" class="btn btn-outline-primary mb-2">
                        <i class="fas fa-clock"></i> View Timesheets
                    </a><br>
                    <a href="staff-payroll.php?id=<?php echo $staffId; ?>" class="btn btn-outline-primary">
                        <i class="fas fa-dollar-sign"></i> View Payroll
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabs -->
    <ul class="nav nav-tabs mb-3" role="tablist">
        <li class="nav-item">
            <a class="nav-link active" data-bs-toggle="tab" href="#overview">
                <i class="fas fa-tachometer-alt"></i> Overview
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-bs-toggle="tab" href="#timesheets">
                <i class="fas fa-clock"></i> Timesheets (<?php echo count($timesheets); ?>)
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-bs-toggle="tab" href="#payroll">
                <i class="fas fa-dollar-sign"></i> Payroll (<?php echo count($payruns); ?>)
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-bs-toggle="tab" href="#ai-history">
                <i class="fas fa-robot"></i> AI History (<?php echo count($aiDecisions); ?>)
            </a>
        </li>
    </ul>

    <div class="tab-content">
        <!-- Overview Tab -->
        <div class="tab-pane fade show active" id="overview">
            <div class="row">
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="fas fa-chart-line"></i> Quick Stats</h5>
                        </div>
                        <div class="card-body">
                            <div class="stat-row">
                                <span>Pending Timesheets:</span>
                                <strong><?php echo count(array_filter($timesheets, fn($t) => $t['status'] === 'pending')); ?></strong>
                            </div>
                            <div class="stat-row">
                                <span>Pending Pay Adjustments:</span>
                                <strong><?php echo count(array_filter($payruns, fn($p) => $p['status'] === 'pending')); ?></strong>
                            </div>
                            <div class="stat-row">
                                <span>AI Auto-Approved (30d):</span>
                                <strong><?php echo count(array_filter($aiDecisions, fn($d) => $d['decision'] === 'auto_approve')); ?></strong>
                            </div>
                            <div class="stat-row">
                                <span>Human Reviews (30d):</span>
                                <strong><?php echo count(array_filter($aiDecisions, fn($d) => !empty($d['human_action']))); ?></strong>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="fas fa-clock"></i> Recent Activity</h5>
                        </div>
                        <div class="card-body">
                            <div class="timeline">
                                <?php
                                $allActivity = array_merge(
                                    array_map(fn($t) => ['type' => 'timesheet', 'data' => $t, 'time' => $t['created_at']], array_slice($timesheets, 0, 5)),
                                    array_map(fn($p) => ['type' => 'payrun', 'data' => $p, 'time' => $p['created_at']], array_slice($payruns, 0, 5))
                                );
                                usort($allActivity, fn($a, $b) => strtotime($b['time']) - strtotime($a['time']));
                                $allActivity = array_slice($allActivity, 0, 10);
                                ?>

                                <?php foreach ($allActivity as $activity): ?>
                                    <div class="timeline-item">
                                        <div class="timeline-marker <?php echo $activity['type'] === 'timesheet' ? 'bg-primary' : 'bg-success'; ?>">
                                            <i class="fas <?php echo $activity['type'] === 'timesheet' ? 'fa-clock' : 'fa-dollar-sign'; ?>"></i>
                                        </div>
                                        <div class="timeline-content">
                                            <div class="timeline-header">
                                                <strong>
                                                    <?php echo $activity['type'] === 'timesheet' ? 'Timesheet Amendment' : 'Pay Adjustment'; ?>
                                                </strong>
                                                <span class="text-muted float-end">
                                                    <?php echo date('d M Y, H:i', strtotime($activity['time'])); ?>
                                                </span>
                                            </div>
                                            <div class="timeline-body">
                                                <?php echo htmlspecialchars($activity['data']['reason'] ?? 'No reason provided'); ?>
                                            </div>
                                            <div class="timeline-footer">
                                                <span class="badge bg-<?php
                                                    echo $activity['data']['status'] === 'approved' ? 'success' :
                                                        ($activity['data']['status'] === 'denied' ? 'danger' : 'warning');
                                                ?>">
                                                    <?php echo ucfirst($activity['data']['status']); ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Timesheets Tab -->
        <div class="tab-pane fade" id="timesheets">
            <div class="card">
                <div class="card-body">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Original Hours</th>
                                <th>New Hours</th>
                                <th>Difference</th>
                                <th>Reason</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($timesheets as $ts): ?>
                                <?php
                                $originalHours = (strtotime($ts['original_end']) - strtotime($ts['original_start'])) / 3600;
                                $newHours = (strtotime($ts['new_end']) - strtotime($ts['new_start'])) / 3600;
                                $diff = $newHours - $originalHours;
                                ?>
                                <tr>
                                    <td><?php echo date('d M Y', strtotime($ts['original_start'])); ?></td>
                                    <td><?php echo number_format($originalHours, 2); ?>h</td>
                                    <td><?php echo number_format($newHours, 2); ?>h</td>
                                    <td class="<?php echo $diff > 0 ? 'text-success' : 'text-danger'; ?>">
                                        <?php echo ($diff > 0 ? '+' : '') . number_format($diff, 2); ?>h
                                    </td>
                                    <td><?php echo htmlspecialchars(substr($ts['reason'], 0, 50)); ?></td>
                                    <td>
                                        <span class="badge bg-<?php
                                            echo $ts['status'] === 'approved' ? 'success' :
                                                ($ts['status'] === 'denied' ? 'danger' : 'warning');
                                        ?>">
                                            <?php echo ucfirst($ts['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-primary" onclick="viewDetails('timesheet', <?php echo $ts['id']; ?>)">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Payroll Tab -->
        <div class="tab-pane fade" id="payroll">
            <div class="card">
                <div class="card-body">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Original Amount</th>
                                <th>Adjustment</th>
                                <th>New Amount</th>
                                <th>Reason</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($payruns as $pr): ?>
                                <tr>
                                    <td><?php echo date('d M Y', strtotime($pr['created_at'])); ?></td>
                                    <td>$<?php echo number_format($pr['original_amount'], 2); ?></td>
                                    <td class="<?php echo $pr['adjustment_amount'] > 0 ? 'text-success' : 'text-danger'; ?>">
                                        <?php echo ($pr['adjustment_amount'] > 0 ? '+' : '') . '$' . number_format($pr['adjustment_amount'], 2); ?>
                                    </td>
                                    <td>$<?php echo number_format($pr['original_amount'] + $pr['adjustment_amount'], 2); ?></td>
                                    <td><?php echo htmlspecialchars(substr($pr['reason'], 0, 50)); ?></td>
                                    <td>
                                        <span class="badge bg-<?php
                                            echo $pr['status'] === 'approved' ? 'success' :
                                                ($pr['status'] === 'denied' ? 'danger' : 'warning');
                                        ?>">
                                            <?php echo ucfirst($pr['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-primary" onclick="viewDetails('payrun', <?php echo $pr['id']; ?>)">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- AI History Tab -->
        <div class="tab-pane fade" id="ai-history">
            <div class="card">
                <div class="card-body">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Type</th>
                                <th>AI Decision</th>
                                <th>Confidence</th>
                                <th>Human Action</th>
                                <th>Reason</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($aiDecisions as $decision): ?>
                                <tr>
                                    <td><?php echo date('d M Y, H:i', strtotime($decision['created_at'])); ?></td>
                                    <td><span class="badge bg-secondary"><?php echo ucfirst($decision['item_type']); ?></span></td>
                                    <td>
                                        <span class="badge bg-<?php
                                            echo $decision['decision'] === 'auto_approve' ? 'success' :
                                                ($decision['decision'] === 'escalate' ? 'danger' : 'warning');
                                        ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $decision['decision'])); ?>
                                        </span>
                                    </td>
                                    <td><?php echo round($decision['confidence_score'] * 100); ?>%</td>
                                    <td>
                                        <?php if ($decision['human_action']): ?>
                                            <span class="badge bg-info"><?php echo ucfirst($decision['human_action']); ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars(substr($decision['item_reason'] ?? '', 0, 40)); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.avatar-large {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 48px;
    margin: 0 auto;
}

.stat-row {
    display: flex;
    justify-content: space-between;
    padding: 10px 0;
    border-bottom: 1px solid #e9ecef;
}

.timeline {
    position: relative;
    padding-left: 40px;
}

.timeline-item {
    position: relative;
    padding-bottom: 30px;
}

.timeline-marker {
    position: absolute;
    left: -40px;
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
}

.timeline-content {
    border-left: 2px solid #e9ecef;
    padding-left: 20px;
}

.timeline-header {
    margin-bottom: 8px;
}

.timeline-body {
    margin-bottom: 8px;
    color: #6c757d;
}
</style>

<script>
function viewDetails(type, id) {
    alert('View ' + type + ' details for ID: ' + id);
}
</script>

<?php include __DIR__ . '/../../assets/template/footer.php'; ?>
