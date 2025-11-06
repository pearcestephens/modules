<?php
/**
 * STAFF DIRECTORY
 * Browse all staff with Deputy/Xero sync status
 */

require_once __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/includes/DeputyIntegration.php';
require_once __DIR__ . '/includes/XeroIntegration.php';

// Security check
if (!isset($_SESSION['userID'])) {
    header('Location: /login.php');
    exit;
}

$deputy = new DeputyIntegration($pdo);
$xero = new XeroIntegration($pdo);

// Get all staff
$stmt = $pdo->query("
    SELECT
        s.*,
        COUNT(DISTINCT ta.id) as pending_amendments,
        COUNT(DISTINCT pa.id) as pending_payruns
    FROM staff s
    LEFT JOIN payroll_timesheet_amendments ta ON s.id = ta.staff_id AND ta.status = 'pending'
    LEFT JOIN payroll_payrun_amendments pa ON s.id = pa.staff_id AND pa.status = 'pending'
    WHERE s.active = 1
    GROUP BY s.id
    ORDER BY s.name
");
$staff = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Staff Directory';
include __DIR__ . '/../../assets/template/html-header.php';
include __DIR__ . '/../../assets/template/header.php';
?>

<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-users"></i> Staff Directory</h2>
        <div>
            <a href="sync-employees.php" class="btn btn-primary">
                <i class="fas fa-sync"></i> Sync from Deputy/Xero
            </a>
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
    </div>

    <div class="row mb-3">
        <div class="col-md-12">
            <div class="card">
                <div class="card-body">
                    <div class="input-group">
                        <input type="text" id="searchStaff" class="form-control" placeholder="Search staff by name, email, or location...">
                        <button class="btn btn-outline-secondary" type="button">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <?php foreach ($staff as $member): ?>
            <div class="col-md-6 col-lg-4 mb-3 staff-card" data-name="<?php echo strtolower($member['name']); ?>">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-start">
                            <div class="flex-shrink-0">
                                <div class="avatar-circle">
                                    <?php echo strtoupper(substr($member['name'], 0, 2)); ?>
                                </div>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h5 class="card-title mb-1">
                                    <a href="staff-detail.php?id=<?php echo $member['id']; ?>">
                                        <?php echo htmlspecialchars($member['name']); ?>
                                    </a>
                                </h5>
                                <p class="text-muted mb-2">
                                    <small><?php echo htmlspecialchars($member['email'] ?? 'No email'); ?></small>
                                </p>

                                <div class="integration-badges mb-2">
                                    <?php if ($member['deputy_employee_id']): ?>
                                        <span class="badge bg-success" title="Synced with Deputy">
                                            <i class="fas fa-check"></i> Deputy
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary" title="Not linked to Deputy">
                                            <i class="fas fa-times"></i> Deputy
                                        </span>
                                    <?php endif; ?>

                                    <?php if ($member['xero_employee_id']): ?>
                                        <span class="badge bg-success" title="Synced with Xero">
                                            <i class="fas fa-check"></i> Xero
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary" title="Not linked to Xero">
                                            <i class="fas fa-times"></i> Xero
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <?php if ($member['pending_amendments'] > 0 || $member['pending_payruns'] > 0): ?>
                                    <div class="alert alert-warning py-1 px-2 mb-2">
                                        <small>
                                            <?php if ($member['pending_amendments'] > 0): ?>
                                                <strong><?php echo $member['pending_amendments']; ?></strong> pending timesheet<?php echo $member['pending_amendments'] > 1 ? 's' : ''; ?>
                                            <?php endif; ?>
                                            <?php if ($member['pending_payruns'] > 0): ?>
                                                <strong><?php echo $member['pending_payruns']; ?></strong> pending pay adjustment<?php echo $member['pending_payruns'] > 1 ? 's' : ''; ?>
                                            <?php endif; ?>
                                        </small>
                                    </div>
                                <?php endif; ?>

                                <div class="btn-group btn-group-sm" role="group">
                                    <a href="staff-detail.php?id=<?php echo $member['id']; ?>" class="btn btn-outline-primary">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                    <a href="staff-timesheets.php?id=<?php echo $member['id']; ?>" class="btn btn-outline-secondary">
                                        <i class="fas fa-clock"></i> Timesheets
                                    </a>
                                    <a href="staff-payroll.php?id=<?php echo $member['id']; ?>" class="btn btn-outline-secondary">
                                        <i class="fas fa-dollar-sign"></i> Payroll
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<style>
.avatar-circle {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 18px;
}

.integration-badges .badge {
    margin-right: 5px;
}

.staff-card {
    transition: transform 0.2s;
}

.staff-card:hover {
    transform: translateY(-5px);
}
</style>

<script>
document.getElementById('searchStaff').addEventListener('input', function(e) {
    const search = e.target.value.toLowerCase();
    document.querySelectorAll('.staff-card').forEach(card => {
        const name = card.dataset.name;
        card.style.display = name.includes(search) ? '' : 'none';
    });
});
</script>

<?php include __DIR__ . '/../../assets/template/footer.php'; ?>
