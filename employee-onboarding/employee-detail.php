<?php
/**
 * EMPLOYEE DETAIL PAGE
 *
 * Shows a single employee with roles, permissions summary, and sync statuses.
 */

require_once __DIR__ . '/../shared/bootstrap.php';
require_once __DIR__ . '/services/UniversalOnboardingService.php';

use CIS\EmployeeOnboarding\UniversalOnboardingService;

// Auth
if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

// Resolve PDO
if (!isset($pdo) || !$pdo) {
    if (function_exists('cis_resolve_pdo')) {
        $pdo = cis_resolve_pdo();
    } elseif (isset($GLOBALS['pdo'])) {
        $pdo = $GLOBALS['pdo'];
    }
}

$onboarding = new UniversalOnboardingService($pdo);

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    http_response_code(400);
    echo 'Invalid employee ID';
    exit;
}

$employee = $onboarding->getEmployee($id);
if (!$employee) {
    http_response_code(404);
    echo 'Employee not found';
    exit;
}

$roles = json_decode($employee['roles'] ?? '[]', true) ?: [];

$pageTitle = 'Employee Detail - ' . htmlspecialchars($employee['full_name']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - CIS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: #f8f9fa; padding: 30px 0; }
        .card { box-shadow: 0 2px 10px rgba(0,0,0,0.06); }
        .sync-badge { display:inline-flex; align-items:center; padding:6px 12px; border-radius:20px; font-weight:600; font-size:.85rem; margin-right:6px; }
        .sync-synced { background:#d4edda; color:#155724; }
        .sync-pending { background:#fff3cd; color:#856404; }
        .sync-failed { background:#f8d7da; color:#721c24; }
        .sync-disabled { background:#e9ecef; color:#6c757d; }
    </style>
    </head>
<body>
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h1 class="h3 mb-0"><i class="fas fa-id-badge"></i> <?php echo htmlspecialchars($employee['full_name']); ?></h1>
            <div>
                <a href="dashboard.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left"></i> Back</a>
                <a href="onboarding-wizard.php" class="btn btn-primary"><i class="fas fa-user-plus"></i> Add Employee</a>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-lg-8">
                <div class="card mb-3">
                    <div class="card-body">
                        <h5 class="card-title mb-3">Profile</h5>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-2"><strong>Email:</strong> <?php echo htmlspecialchars($employee['email']); ?></div>
                                <div class="mb-2"><strong>Phone:</strong> <?php echo htmlspecialchars($employee['phone'] ?? ''); ?></div>
                                <div class="mb-2"><strong>Mobile:</strong> <?php echo htmlspecialchars($employee['mobile'] ?? ''); ?></div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-2"><strong>Job Title:</strong> <?php echo htmlspecialchars($employee['job_title'] ?? ''); ?></div>
                                <div class="mb-2"><strong>Department:</strong> <?php echo htmlspecialchars($employee['department'] ?? ''); ?></div>
                                <div class="mb-2"><strong>Status:</strong> <span class="badge bg-secondary"><?php echo htmlspecialchars($employee['status']); ?></span></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-body">
                        <h5 class="card-title mb-3">Roles</h5>
                        <?php if (!empty($roles)): ?>
                            <?php foreach ($roles as $role): ?>
                                <span class="badge bg-primary me-1"><?php echo htmlspecialchars($role['display_name']); ?></span>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <span class="text-muted">No roles assigned</span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-body">
                        <h5 class="card-title mb-3">Audit Log (Recent)</h5>
                <?php
                try {
                    $stmt = $pdo->prepare("SELECT action, system_name, status, error_message, created_at FROM audit_logs WHERE employee_id = ? ORDER BY created_at DESC LIMIT 10");
                    $stmt->execute([$id]);
                    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
                } catch (Throwable $e) {
                    error_log("[Employee Detail] Failed to fetch audit logs: " . $e->getMessage());
                    $logs = [];
                }

                if (empty($logs)) {
                    echo '<p>No recent activity found.</p>';
                } else {
                    echo '<table class="table table-striped">';
                    echo '<thead><tr><th>Action</th><th>System</th><th>Status</th><th>Error</th><th>Timestamp</th></tr></thead>';
                    echo '<tbody>';
                    foreach ($logs as $log) {
                        echo '<tr>';
                        echo '<td>' . htmlspecialchars($log['action']) . '</td>';
                        echo '<td>' . htmlspecialchars($log['system_name']) . '</td>';
                        echo '<td>' . htmlspecialchars($log['status']) . '</td>';
                        echo '<td>' . htmlspecialchars($log['error_message'] ?? 'N/A') . '</td>';
                        echo '<td>' . htmlspecialchars($log['created_at']) . '</td>';
                        echo '</tr>';
                    }
                    echo '</tbody></table>';
                }
                ?>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card mb-3">
                    <div class="card-body">
                        <h5 class="card-title mb-3">System Sync Status</h5>
                        <?php
                        $systems = [
                            'Xero' => $employee['xero_sync_status'] ?? null,
                            'Deputy' => $employee['deputy_sync_status'] ?? null,
                            'Lightspeed' => $employee['lightspeed_sync_status'] ?? null,
                        ];
                        foreach ($systems as $label => $status):
                            $cls = $status ? 'sync-' . $status : 'sync-disabled';
                        ?>
                            <div class="sync-badge <?php echo $cls; ?>">
                                <i class="fas fa-<?php echo ($status === 'synced') ? 'check' : ($status ? 'times' : 'minus'); ?> me-1"></i>
                                <?php echo htmlspecialchars($label); ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title mb-3">Actions</h5>
                        <a href="dashboard.php" class="btn btn-outline-secondary w-100 mb-2"><i class="fas fa-list"></i> Back to List</a>
                        <button class="btn btn-outline-primary w-100 mb-2" disabled title="Coming soon"><i class="fas fa-rotate"></i> Force Re-Sync</button>
                        <button class="btn btn-outline-danger w-100" disabled title="Coming soon"><i class="fas fa-user-slash"></i> Deactivate Employee</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
