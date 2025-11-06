<?php
/**
 * INTEGRATIONS DASHBOARD
 * View Deputy & Xero sync status, test connections, trigger syncs
 */

require_once __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/includes/DeputyIntegration.php';
require_once __DIR__ . '/includes/XeroIntegration.php';

if (!isset($_SESSION['userID'])) {
    header('Location: /login.php');
    exit;
}

$deputy = new DeputyIntegration($pdo);
$xero = new XeroIntegration($pdo);

// Get sync statistics
$stmt = $pdo->query("
    SELECT
        integration_name,
        sync_type,
        COUNT(*) as total_syncs,
        SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as successful,
        SUM(CASE WHEN status = 'error' THEN 1 ELSE 0 END) as failed,
        MAX(created_at) as last_sync
    FROM integration_sync_log
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY integration_name, sync_type
    ORDER BY integration_name, sync_type
");
$syncStats = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent sync logs
$stmt = $pdo->query("
    SELECT * FROM integration_sync_log
    ORDER BY created_at DESC
    LIMIT 50
");
$recentSyncs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Test connections
$deputyStatus = $deputy->testConnection();
$xeroStatus = $xero->testConnection();

$pageTitle = 'Integrations Dashboard';
include __DIR__ . '/../../assets/template/html-header.php';
include __DIR__ . '/../../assets/template/header.php';
?>

<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-plug"></i> Integrations Dashboard</h2>
        <a href="index.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to HR Portal
        </a>
    </div>

    <!-- Connection Status Cards -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card h-100 <?php echo $deputyStatus['success'] ? 'border-success' : 'border-danger'; ?>">
                <div class="card-header bg-transparent">
                    <h4 class="mb-0">
                        <img src="https://www.deputy.com/favicon.ico" alt="Deputy" style="width: 24px; height: 24px; margin-right: 10px;">
                        Deputy Integration
                        <?php if ($deputyStatus['success']): ?>
                            <span class="badge bg-success float-end">
                                <i class="fas fa-check-circle"></i> Connected
                            </span>
                        <?php else: ?>
                            <span class="badge bg-danger float-end">
                                <i class="fas fa-times-circle"></i> Disconnected
                            </span>
                        <?php endif; ?>
                    </h4>
                </div>
                <div class="card-body">
                    <?php if ($deputyStatus['success']): ?>
                        <p class="text-success mb-3">
                            <i class="fas fa-check"></i> <?php echo htmlspecialchars($deputyStatus['message']); ?>
                        </p>
                        <div class="d-grid gap-2">
                            <button class="btn btn-primary" onclick="syncDeputy('employees')">
                                <i class="fas fa-sync"></i> Sync Employees from Deputy
                            </button>
                            <button class="btn btn-outline-primary" onclick="syncDeputy('timesheets')">
                                <i class="fas fa-clock"></i> Sync Timesheets (Last 7 Days)
                            </button>
                            <button class="btn btn-outline-secondary" onclick="viewDeputyLogs()">
                                <i class="fas fa-list"></i> View Sync Logs
                            </button>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-danger">
                            <strong>Connection Error:</strong><br>
                            <?php echo htmlspecialchars($deputyStatus['message']); ?>
                        </div>
                        <p class="text-muted">
                            Check your Deputy API credentials and ensure the API token is valid.
                        </p>
                    <?php endif; ?>

                    <hr>

                    <h6 class="mt-3">Sync Statistics (Last 30 Days)</h6>
                    <div class="stats-grid">
                        <?php foreach ($syncStats as $stat): ?>
                            <?php if ($stat['integration_name'] === 'deputy'): ?>
                                <div class="stat-item">
                                    <span class="stat-label"><?php echo ucwords(str_replace('_', ' ', $stat['sync_type'])); ?>:</span>
                                    <span class="stat-value">
                                        <span class="text-success"><?php echo $stat['successful']; ?></span> /
                                        <span class="text-danger"><?php echo $stat['failed']; ?></span>
                                    </span>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card h-100 <?php echo $xeroStatus['success'] ? 'border-success' : 'border-danger'; ?>">
                <div class="card-header bg-transparent">
                    <h4 class="mb-0">
                        <img src="https://www.xero.com/favicon.ico" alt="Xero" style="width: 24px; height: 24px; margin-right: 10px;">
                        Xero Integration
                        <?php if ($xeroStatus['success']): ?>
                            <span class="badge bg-success float-end">
                                <i class="fas fa-check-circle"></i> Connected
                            </span>
                        <?php else: ?>
                            <span class="badge bg-danger float-end">
                                <i class="fas fa-times-circle"></i> Disconnected
                            </span>
                        <?php endif; ?>
                    </h4>
                </div>
                <div class="card-body">
                    <?php if ($xeroStatus['success']): ?>
                        <p class="text-success mb-3">
                            <i class="fas fa-check"></i> <?php echo htmlspecialchars($xeroStatus['message']); ?>
                        </p>
                        <div class="d-grid gap-2">
                            <button class="btn btn-primary" onclick="syncXero('employees')">
                                <i class="fas fa-sync"></i> Sync Employees from Xero
                            </button>
                            <button class="btn btn-outline-primary" onclick="syncXero('payruns')">
                                <i class="fas fa-dollar-sign"></i> Sync Pay Runs
                            </button>
                            <button class="btn btn-outline-primary" onclick="syncXero('leave')">
                                <i class="fas fa-calendar"></i> Sync Leave Applications
                            </button>
                            <button class="btn btn-outline-secondary" onclick="viewXeroLogs()">
                                <i class="fas fa-list"></i> View Sync Logs
                            </button>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-danger">
                            <strong>Connection Error:</strong><br>
                            <?php echo htmlspecialchars($xeroStatus['message']); ?>
                        </div>
                        <p class="text-muted">
                            Check your Xero OAuth credentials and refresh token. You may need to re-authorize.
                        </p>
                        <button class="btn btn-warning" onclick="reauthorizeXero()">
                            <i class="fas fa-key"></i> Re-authorize Xero
                        </button>
                    <?php endif; ?>

                    <hr>

                    <h6 class="mt-3">Sync Statistics (Last 30 Days)</h6>
                    <div class="stats-grid">
                        <?php foreach ($syncStats as $stat): ?>
                            <?php if ($stat['integration_name'] === 'xero'): ?>
                                <div class="stat-item">
                                    <span class="stat-label"><?php echo ucwords(str_replace('_', ' ', $stat['sync_type'])); ?>:</span>
                                    <span class="stat-value">
                                        <span class="text-success"><?php echo $stat['successful']; ?></span> /
                                        <span class="text-danger"><?php echo $stat['failed']; ?></span>
                                    </span>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Sync Logs -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0"><i class="fas fa-history"></i> Recent Sync Activity</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Timestamp</th>
                            <th>Integration</th>
                            <th>Type</th>
                            <th>Item ID</th>
                            <th>External ID</th>
                            <th>Status</th>
                            <th>Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentSyncs as $sync): ?>
                            <tr>
                                <td>
                                    <small><?php echo date('d M Y', strtotime($sync['created_at'])); ?></small><br>
                                    <small class="text-muted"><?php echo date('H:i:s', strtotime($sync['created_at'])); ?></small>
                                </td>
                                <td>
                                    <?php if ($sync['integration_name'] === 'deputy'): ?>
                                        <span class="badge bg-primary">Deputy</span>
                                    <?php else: ?>
                                        <span class="badge bg-info">Xero</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($sync['sync_type']); ?></td>
                                <td><?php echo $sync['item_id']; ?></td>
                                <td><?php echo htmlspecialchars($sync['external_id']); ?></td>
                                <td>
                                    <?php if ($sync['status'] === 'success'): ?>
                                        <span class="badge bg-success">
                                            <i class="fas fa-check"></i> Success
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">
                                            <i class="fas fa-times"></i> Error
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-outline-secondary"
                                            onclick="viewSyncDetails(<?php echo $sync['id']; ?>)"
                                            title="<?php echo htmlspecialchars(substr($sync['details'], 0, 100)); ?>">
                                        <i class="fas fa-eye"></i> View
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<style>
.stats-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 10px;
}

.stat-item {
    display: flex;
    justify-content: space-between;
    padding: 8px 0;
    border-bottom: 1px solid #e9ecef;
}

.stat-label {
    font-weight: 500;
}

.stat-value {
    font-weight: bold;
}
</style>

<script>
function syncDeputy(type) {
    if (!confirm(`Sync ${type} from Deputy? This may take a few minutes.`)) return;

    const btn = event.target;
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Syncing...';

    fetch(`api/sync-deputy.php?type=${type}`)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                alert(`Success! Synced ${data.synced} items.`);
                location.reload();
            } else {
                alert('Error: ' + data.error);
            }
        })
        .catch(e => {
            alert('Sync failed: ' + e.message);
        })
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML = originalText;
        });
}

function syncXero(type) {
    if (!confirm(`Sync ${type} from Xero? This may take a few minutes.`)) return;

    const btn = event.target;
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Syncing...';

    fetch(`api/sync-xero.php?type=${type}`)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                alert(`Success! Synced ${data.synced} items.`);
                location.reload();
            } else {
                alert('Error: ' + data.error);
            }
        })
        .catch(e => {
            alert('Sync failed: ' + e.message);
        })
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML = originalText;
        });
}

function viewSyncDetails(id) {
    alert('View sync details for ID: ' + id);
}

function viewDeputyLogs() {
    window.location.href = 'integration-logs.php?integration=deputy';
}

function viewXeroLogs() {
    window.location.href = 'integration-logs.php?integration=xero';
}

function reauthorizeXero() {
    window.location.href = 'xero-oauth.php';
}
</script>

<?php include __DIR__ . '/../../assets/template/footer.php'; ?>
