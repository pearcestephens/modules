<?php
/**
 * HR PORTAL - HYBRID AUTO-PILOT + MANUAL CONTROL
 *
 * The best of both worlds:
 * - AI auto-approves safe items
 * - Flags exceptions for human review
 * - Full manual override capability
 * - Real-time monitoring
 * - Complete audit trail
 */

require_once __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/includes/AIPayrollEngine.php';
require_once __DIR__ . '/includes/PayrollDashboard.php';

// Security check - LOGGED IN ONLY
if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

$userID = (int)$_SESSION['user_id'];

// Get PDO connection from bootstrap DB class
$pdo = DB::pdo();
if (!$pdo) {
    die('Database connection failed. Please contact support.');
}

// Initialize dashboard
$dashboard = new PayrollDashboard($pdo);
$aiEngine = new AIPayrollEngine($pdo);
WH
// Get dashboard data
$todayStats = $dashboard->getTodayStats();
$pendingItems = $dashboard->getPendingItems();
$recentActivity = $dashboard->getRecentActivity();
$aiInsights = $aiEngine->getInsights();

// Page setup
$pageTitle = 'HR Portal - Auto-Pilot Control Center';
$breadcrumbs = [
    ['label' => 'Dashboard', 'href' => '/'],
    ['label' => 'HR Portal']
];

include __DIR__ . '/../../assets/template/html-header.php';
include __DIR__ . '/../../assets/template/header.php';
?>

<style>
/* HR Portal Custom Styles */
.stat-card {
    background: white;
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    transition: transform 0.2s;
}
.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.15);
}
.stat-value {
    font-size: 2.5rem;
    font-weight: bold;
    margin: 10px 0;
}
.stat-label {
    color: #6c757d;
    font-size: 0.875rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.auto-approved {
    color: #28a745;
    border-left: 4px solid #28a745;
}
.needs-review {
    color: #ffc107;
    border-left: 4px solid #ffc107;
}
.escalated {
    color: #dc3545;
    border-left: 4px solid #dc3545;
}
.ai-badge {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: bold;
}
.action-btn {
    min-width: 120px;
}
.confidence-meter {
    height: 8px;
    background: #e9ecef;
    border-radius: 4px;
    overflow: hidden;
    margin: 8px 0;
}
.confidence-fill {
    height: 100%;
    transition: width 0.3s;
}
.mode-toggle {
    position: fixed;
    top: 80px;
    right: 20px;
    z-index: 1000;
}
.alert-pulse {
    animation: pulse 2s infinite;
}
@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}
</style>

<body class="app header-fixed sidebar-fixed aside-menu-fixed sidebar-lg-show">
    <div class="app-body">
        <?php include __DIR__ . '/../../assets/template/sidemenu.php'; ?>

        <main class="main">
            <!-- Breadcrumb -->
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="/">Dashboard</a></li>
                <li class="breadcrumb-item active">HR Portal</li>

                <!-- Auto-Pilot Mode Toggle -->
                <li class="breadcrumb-menu d-md-down-none">
                    <div class="btn-group mode-toggle" role="group">
                        <button type="button" class="btn btn-sm btn-outline-success" id="autoPilotBtn" onclick="toggleAutoPilot()">
                            <i class="fas fa-robot"></i> Auto-Pilot: <span id="autoPilotStatus">ON</span>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="showManualControl()">
                            <i class="fas fa-hand-paper"></i> Manual Mode
                        </button>
                    </div>
                </li>
            </ol>

            <div class="container-fluid">

                <!-- QUICK NAVIGATION -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="mb-3"><i class="fas fa-compass"></i> Quick Navigation</h5>
                                <div class="row g-2">
                                    <div class="col-auto">
                                        <a href="staff-directory.php" class="btn btn-outline-primary">
                                            <i class="fas fa-users"></i> Staff Directory
                                        </a>
                                    </div>
                                    <div class="col-auto">
                                        <a href="integrations.php" class="btn btn-outline-success">
                                            <i class="fas fa-plug"></i> Deputy & Xero Integration
                                        </a>
                                    </div>
                                    <div class="col-auto">
                                        <button class="btn btn-outline-secondary" disabled title="Coming Soon">
                                            <i class="fas fa-clock"></i> All Timesheets (Coming Soon)
                                        </button>
                                    </div>
                                    <div class="col-auto">
                                        <button class="btn btn-outline-secondary" disabled title="Coming Soon">
                                            <i class="fas fa-dollar-sign"></i> All Payroll (Coming Soon)
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- TODAY'S PRIORITY BANNER -->
                <?php if ($todayStats['needs_attention'] > 0): ?>
                <div class="alert alert-warning alert-pulse mb-4" role="alert">
                    <h4 class="alert-heading">
                        <i class="fas fa-exclamation-triangle"></i>
                        <?php echo $todayStats['needs_attention']; ?> Item<?php echo $todayStats['needs_attention'] > 1 ? 's' : ''; ?> Need Your Attention
                    </h4>
                    <p class="mb-0">
                        AI auto-pilot has flagged these items for human review based on your configured rules.
                        <a href="#pending-review" class="alert-link">Review now →</a>
                    </p>
                </div>
                <?php endif; ?>

                <!-- STATS OVERVIEW -->
                <div class="row mb-4">
                    <div class="col-lg-3 col-md-6 mb-3">
                        <div class="stat-card auto-approved">
                            <div class="stat-label">
                                <i class="fas fa-check-circle"></i> Auto-Approved Today
                            </div>
                            <div class="stat-value"><?php echo number_format($todayStats['auto_approved']); ?></div>
                            <small class="text-muted">
                                Saved ~<?php echo round($todayStats['auto_approved'] * 3); ?> minutes
                            </small>
                        </div>
                    </div>

                    <div class="col-lg-3 col-md-6 mb-3">
                        <div class="stat-card needs-review">
                            <div class="stat-label">
                                <i class="fas fa-clock"></i> Needs Review
                            </div>
                            <div class="stat-value"><?php echo number_format($todayStats['needs_review']); ?></div>
                            <small class="text-muted">
                                Avg. review time: 2 min each
                            </small>
                        </div>
                    </div>

                    <div class="col-lg-3 col-md-6 mb-3">
                        <div class="stat-card escalated">
                            <div class="stat-label">
                                <i class="fas fa-exclamation-circle"></i> Escalated
                            </div>
                            <div class="stat-value"><?php echo number_format($todayStats['escalated']); ?></div>
                            <small class="text-muted">
                                High risk items
                            </small>
                        </div>
                    </div>

                    <div class="col-lg-3 col-md-6 mb-3">
                        <div class="stat-card" style="border-left: 4px solid #667eea;">
                            <div class="stat-label">
                                <i class="fas fa-tachometer-alt"></i> AI Accuracy
                            </div>
                            <div class="stat-value"><?php echo number_format($todayStats['ai_accuracy'], 1); ?>%</div>
                            <small class="text-muted">
                                Based on recent overrides
                            </small>
                        </div>
                    </div>
                </div>

                <!-- AI INSIGHTS BANNER -->
                <?php if (!empty($aiInsights)): ?>
                <div class="card mb-4" style="border-left: 4px solid #667eea;">
                    <div class="card-body">
                        <h5 class="card-title">
                            <span class="ai-badge">🤖 AI INSIGHTS</span>
                        </h5>
                        <ul class="mb-0">
                            <?php foreach ($aiInsights as $insight): ?>
                            <li>
                                <strong><?php echo htmlspecialchars($insight['title']); ?></strong>:
                                <?php echo htmlspecialchars($insight['message']); ?>
                                <?php if (!empty($insight['action'])): ?>
                                <a href="<?php echo htmlspecialchars($insight['action_url']); ?>" class="btn btn-sm btn-outline-primary ml-2">
                                    <?php echo htmlspecialchars($insight['action']); ?>
                                </a>
                                <?php endif; ?>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
                <?php endif; ?>

                <!-- PENDING ITEMS SECTION -->
                <div class="card mb-4" id="pending-review">
                    <div class="card-header">
                        <h4 class="mb-0">
                            <i class="fas fa-clipboard-list"></i> Items Needing Review
                            <span class="badge badge-warning float-right"><?php echo count($pendingItems); ?></span>
                        </h4>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($pendingItems)): ?>
                        <div class="text-center p-5">
                            <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                            <h5>All Clear! 🎉</h5>
                            <p class="text-muted">No items require your attention right now.</p>
                        </div>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th width="5%"></th>
                                        <th width="15%">Type</th>
                                        <th width="20%">Staff</th>
                                        <th width="25%">Details</th>
                                        <th width="15%">AI Confidence</th>
                                        <th width="20%">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pendingItems as $item): ?>
                                    <tr>
                                        <td>
                                            <i class="fas fa-<?php echo $item['icon']; ?> fa-lg" style="color: <?php echo $item['color']; ?>;"></i>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($item['type']); ?></strong>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($item['category']); ?></small>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($item['staff_name']); ?></strong>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($item['staff_role']); ?></small>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($item['description']); ?>
                                            <?php if (!empty($item['ai_reasoning'])): ?>
                                            <br><small class="text-info">
                                                <i class="fas fa-robot"></i> AI: <?php echo htmlspecialchars($item['ai_reasoning']); ?>
                                            </small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="confidence-meter">
                                                <div class="confidence-fill bg-<?php echo $item['confidence_color']; ?>"
                                                     style="width: <?php echo $item['confidence'] * 100; ?>%"></div>
                                            </div>
                                            <small><?php echo round($item['confidence'] * 100); ?>% confident</small>
                                        </td>
                                        <td>
                                            <div class="btn-group-vertical btn-group-sm" role="group">
                                                <button type="button" class="btn btn-success action-btn"
                                                        onclick="approveItem(<?php echo $item['id']; ?>, '<?php echo $item['type']; ?>')">
                                                    <i class="fas fa-check"></i> Approve
                                                </button>
                                                <button type="button" class="btn btn-danger action-btn"
                                                        onclick="denyItem(<?php echo $item['id']; ?>, '<?php echo $item['type']; ?>')">
                                                    <i class="fas fa-times"></i> Deny
                                                </button>
                                                <button type="button" class="btn btn-info action-btn"
                                                        onclick="viewDetails(<?php echo $item['id']; ?>, '<?php echo $item['type']; ?>')">
                                                    <i class="fas fa-eye"></i> Details
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Batch Actions -->
                        <div class="card-footer bg-light">
                            <button type="button" class="btn btn-success" onclick="batchApprove()">
                                <i class="fas fa-check-double"></i> Approve All High Confidence
                            </button>
                            <button type="button" class="btn btn-outline-secondary" onclick="exportPending()">
                                <i class="fas fa-download"></i> Export
                            </button>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- TABS: Auto Activity / Manual Control / Audit Trail -->
                <div class="card">
                    <div class="card-header">
                        <ul class="nav nav-tabs card-header-tabs" role="tablist">
                            <li class="nav-item">
                                <a class="nav-link active" data-toggle="tab" href="#auto-activity" role="tab">
                                    <i class="fas fa-robot"></i> Auto-Pilot Activity
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-toggle="tab" href="#manual-control" role="tab">
                                    <i class="fas fa-hand-paper"></i> Manual Control
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-toggle="tab" href="#audit-trail" role="tab">
                                    <i class="fas fa-history"></i> Audit Trail
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-toggle="tab" href="#ai-settings" role="tab">
                                    <i class="fas fa-cog"></i> AI Settings
                                </a>
                            </li>
                        </ul>
                    </div>
                    <div class="card-body">
                        <div class="tab-content">
                            <!-- AUTO-PILOT ACTIVITY TAB -->
                            <div class="tab-pane fade show active" id="auto-activity" role="tabpanel">
                                <h5 class="mb-3">Recent Auto-Pilot Decisions</h5>
                                <div id="autoActivityContent">
                                    <?php include __DIR__ . '/views/auto-activity.php'; ?>
                                </div>
                            </div>

                            <!-- MANUAL CONTROL TAB -->
                            <div class="tab-pane fade" id="manual-control" role="tabpanel">
                                <h5 class="mb-3">Manual Payroll Control</h5>
                                <div id="manualControlContent">
                                    <?php include __DIR__ . '/views/manual-control.php'; ?>
                                </div>
                            </div>

                            <!-- AUDIT TRAIL TAB -->
                            <div class="tab-pane fade" id="audit-trail" role="tabpanel">
                                <h5 class="mb-3">Complete Audit Trail</h5>
                                <div id="auditTrailContent">
                                    <?php include __DIR__ . '/views/audit-trail.php'; ?>
                                </div>
                            </div>

                            <!-- AI SETTINGS TAB -->
                            <div class="tab-pane fade" id="ai-settings" role="tabpanel">
                                <h5 class="mb-3">AI Auto-Pilot Configuration</h5>
                                <div id="aiSettingsContent">
                                    <?php include __DIR__ . '/views/ai-settings.php'; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </main>
    </div>
</body>

<script>
// Auto-refresh every 30 seconds
let autoRefreshEnabled = true;
let autoPilotEnabled = true;

setInterval(() => {
    if (autoRefreshEnabled) {
        refreshDashboard();
    }
}, 30000);

function refreshDashboard() {
    fetch('api/dashboard-stats.php')
        .then(response => response.json())
        .then(data => {
            updateStats(data);
            if (data.newItems > 0) {
                showNotification(`${data.newItems} new items need review`);
            }
        })
        .catch(error => console.error('Refresh error:', error));
}

function toggleAutoPilot() {
    autoPilotEnabled = !autoPilotEnabled;

    fetch('api/toggle-autopilot.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({enabled: autoPilotEnabled})
    })
    .then(response => response.json())
    .then(data => {
        document.getElementById('autoPilotStatus').textContent = autoPilotEnabled ? 'ON' : 'OFF';
        document.getElementById('autoPilotBtn').className = autoPilotEnabled
            ? 'btn btn-sm btn-success'
            : 'btn btn-sm btn-outline-secondary';
        showNotification(data.message);
    });
}

function approveItem(id, type) {
    if (!confirm('Approve this item?')) return;

    fetch('api/approve-item.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({id, type})
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('Item approved successfully', 'success');
            location.reload();
        } else {
            showNotification('Error: ' + data.error, 'danger');
        }
    });
}

function denyItem(id, type) {
    const reason = prompt('Reason for denial (optional):');

    fetch('api/deny-item.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({id, type, reason})
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('Item denied', 'success');
            location.reload();
        } else {
            showNotification('Error: ' + data.error, 'danger');
        }
    });
}

function viewDetails(id, type) {
    window.location.href = `details.php?id=${id}&type=${type}`;
}

function batchApprove() {
    if (!confirm('Approve all high-confidence items (>85%)?')) return;

    fetch('api/batch-approve.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({threshold: 0.85})
    })
    .then(response => response.json())
    .then(data => {
        showNotification(`Approved ${data.count} items`, 'success');
        location.reload();
    });
}

function showNotification(message, type = 'info') {
    // Use your existing notification system or create a toast
    console.log(`[${type}] ${message}`);
    alert(message); // Replace with better notification system
}

function showManualControl() {
    $('a[href="#manual-control"]').tab('show');
}
</script>

<?php include __DIR__ . '/../../assets/template/html-footer.php'; ?>
