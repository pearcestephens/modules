<?php
declare(strict_types=1);

/**
 * 🎯 TRANSFERS HUB - Central Dashboard for All Roles
 *
 * Multi-dashboard system:
 * - Staff Dashboard: Their active transfers only
 * - Outlet Manager Dashboard: Outlet inventory + suggestions
 * - Management Control Panel: Full system control
 * - System Health Dashboard: Metrics & AI engine
 *
 * @package CIS\Consignments
 * @version 2.0.0
 * @created 2025-11-13
 */

session_start();

// Authentication required
if (empty($_SESSION['userID'])) {
    http_response_code(403);
    die(json_encode(['error' => 'Authentication required']));
}

require_once __DIR__ . '/bootstrap.php';

$userID = (int)$_SESSION['userID'];
$userRole = $_SESSION['role'] ?? 'staff';
$outlet_id = $_SESSION['outlet_id'] ?? null;

try {
    $pdo = cis_resolve_pdo();
} catch (Exception $e) {
    http_response_code(500);
    die(json_encode(['error' => 'Database connection failed']));
}

// Determine which dashboard to show
$dashboard = $_GET['tab'] ?? 'staff';

// Authorization check
if (!in_array($dashboard, ['staff', 'outlet', 'management', 'system'])) {
    $dashboard = 'staff';
}

if ($dashboard === 'management' && !in_array($userRole, ['manager', 'admin'])) {
    $dashboard = 'staff';
}

if ($dashboard === 'system' && $userRole !== 'admin') {
    $dashboard = 'staff';
}

if ($dashboard === 'outlet' && !$outlet_id) {
    $dashboard = 'staff';
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transfers Hub - CIS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css">
    <style>
        :root {
            --primary: #0d6efd;
            --success: #198754;
            --danger: #dc3545;
            --warning: #ffc107;
            --info: #0dcaf0;
        }

        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }

        .transfers-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            margin-bottom: 2rem;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }

        .transfers-header h1 {
            margin: 0;
            font-size: 2.5rem;
            font-weight: 700;
        }

        .transfers-header p {
            margin: 0.5rem 0 0 0;
            opacity: 0.95;
            font-size: 1rem;
        }

        .dashboard-tabs {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }

        .dashboard-tab {
            padding: 0.75rem 1.5rem;
            border: 2px solid #ddd;
            background: white;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 600;
            color: #666;
        }

        .dashboard-tab:hover {
            border-color: var(--primary);
            color: var(--primary);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .dashboard-tab.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        .dashboard-tab.locked {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .dashboard-tab.locked:hover {
            transform: none;
            box-shadow: none;
            border-color: #ddd;
        }

        .widget-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .widget-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            border-left: 4px solid #ddd;
        }

        .widget-card:hover {
            box-shadow: 0 8px 24px rgba(0,0,0,0.15);
            transform: translateY(-4px);
        }

        .widget-card.urgent {
            border-left-color: var(--danger);
        }

        .widget-card.success {
            border-left-color: var(--success);
        }

        .widget-card.warning {
            border-left-color: var(--warning);
        }

        .widget-card.info {
            border-left-color: var(--info);
        }

        .widget-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: #333;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .widget-icon {
            font-size: 1.5rem;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            background: #f0f0f0;
        }

        .widget-content {
            font-size: 0.95rem;
            color: #555;
            line-height: 1.6;
        }

        .stat-row {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem 0;
            border-bottom: 1px solid #eee;
        }

        .stat-row:last-child {
            border-bottom: none;
        }

        .stat-label {
            color: #888;
            font-weight: 600;
        }

        .stat-value {
            color: #333;
            font-weight: 700;
            font-size: 1.1rem;
        }

        .status-badge {
            display: inline-block;
            padding: 0.35rem 0.75rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            margin: 0.25rem 0;
        }

        .status-in-progress {
            background: rgba(13, 110, 253, 0.1);
            color: #0d6efd;
        }

        .status-ready {
            background: rgba(25, 135, 84, 0.1);
            color: #198754;
        }

        .status-sent {
            background: rgba(13, 202, 240, 0.1);
            color: #0dcaf0;
        }

        .status-draft {
            background: rgba(108, 117, 125, 0.1);
            color: #6c757d;
        }

        .quick-action-btn {
            display: inline-block;
            padding: 0.5rem 1rem;
            margin: 0.35rem 0.25rem 0;
            border-radius: 6px;
            background: var(--primary);
            color: white;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.85rem;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .quick-action-btn:hover {
            background: #0b5ed7;
            text-decoration: none;
            color: white;
            transform: scale(1.05);
        }

        .metric-box {
            text-align: center;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 8px;
            margin: 0.5rem 0;
        }

        .metric-value {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--primary);
        }

        .metric-label {
            font-size: 0.85rem;
            color: #888;
            margin-top: 0.25rem;
        }

        .alert-box {
            background: rgba(220, 53, 69, 0.1);
            border-left: 4px solid #dc3545;
            padding: 1rem;
            border-radius: 6px;
            margin-bottom: 1rem;
        }

        .alert-box.warning {
            background: rgba(255, 193, 7, 0.1);
            border-left-color: #ffc107;
        }

        .alert-box.info {
            background: rgba(13, 202, 240, 0.1);
            border-left-color: #0dcaf0;
        }

        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #999;
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @media (max-width: 768px) {
            .widget-grid {
                grid-template-columns: 1fr;
            }

            .transfers-header h1 {
                font-size: 1.8rem;
            }

            .dashboard-tabs {
                flex-wrap: wrap;
            }

            .dashboard-tab {
                flex: 1;
                min-width: 120px;
                text-align: center;
                padding: 0.5rem;
                font-size: 0.85rem;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid py-4">
        <!-- HEADER -->
        <div class="transfers-header">
            <h1><i class="fas fa-exchange-alt"></i> Transfers Hub</h1>
            <p>Manage all inter-outlet transfers, inventory movements, and logistics</p>
        </div>

        <!-- NAVIGATION TABS -->
        <div class="dashboard-tabs">
            <button class="dashboard-tab <?= ($dashboard === 'staff' ? 'active' : '') ?>"
                    onclick="switchTab('staff')">
                <i class="fas fa-user"></i> My Transfers
            </button>

            <?php if ($outlet_id): ?>
            <button class="dashboard-tab <?= ($dashboard === 'outlet' ? 'active' : '') ?>"
                    onclick="switchTab('outlet')">
                <i class="fas fa-store"></i> Outlet Manager
            </button>
            <?php else: ?>
            <button class="dashboard-tab locked" title="Outlet Manager requires outlet assignment">
                <i class="fas fa-store"></i> Outlet Manager <i class="fas fa-lock"></i>
            </button>
            <?php endif; ?>

            <?php if (in_array($userRole, ['manager', 'admin'])): ?>
            <button class="dashboard-tab <?= ($dashboard === 'management' ? 'active' : '') ?>"
                    onclick="switchTab('management')">
                <i class="fas fa-chart-bar"></i> Management Panel
            </button>
            <?php else: ?>
            <button class="dashboard-tab locked" title="Management Panel requires manager access">
                <i class="fas fa-chart-bar"></i> Management <i class="fas fa-lock"></i>
            </button>
            <?php endif; ?>

            <?php if ($userRole === 'admin'): ?>
            <button class="dashboard-tab <?= ($dashboard === 'system' ? 'active' : '') ?>"
                    onclick="switchTab('system')">
                <i class="fas fa-heartbeat"></i> System Health
            </button>
            <?php else: ?>
            <button class="dashboard-tab locked" title="System Health requires admin access">
                <i class="fas fa-heartbeat"></i> System <i class="fas fa-lock"></i>
            </button>
            <?php endif; ?>
        </div>

        <!-- STAFF DASHBOARD -->
        <div id="staff-content" class="tab-content <?= ($dashboard === 'staff' ? 'active' : '') ?>">
            <?php include __DIR__ . '/dashboards/staff-dashboard-widget.php'; ?>
        </div>

        <!-- OUTLET MANAGER DASHBOARD -->
        <div id="outlet-content" class="tab-content <?= ($dashboard === 'outlet' ? 'active' : '') ?>">
            <?php if ($outlet_id): ?>
                <div style="margin-bottom: 2rem;">
                    <a href="/modules/consignments/outlet-dashboard-full.php" class="btn btn-primary btn-lg">
                        <i class="fas fa-expand"></i> Open Full Page Dashboard
                    </a>
                </div>
                <?php include __DIR__ . '/dashboards/outlet-dashboard-widget.php'; ?>
            <?php else: ?>
                <div class="alert alert-warning">
                    <i class="fas fa-lock"></i> Outlet Manager Dashboard requires outlet assignment
                </div>
            <?php endif; ?>
        </div>

        <!-- MANAGEMENT CONTROL PANEL -->
        <div id="management-content" class="tab-content <?= ($dashboard === 'management' ? 'active' : '') ?>">
            <?php if (in_array($userRole, ['manager', 'admin'])): ?>
                <div style="margin-bottom: 2rem;">
                    <a href="/modules/consignments/management-dashboard-full.php" class="btn btn-primary btn-lg">
                        <i class="fas fa-expand"></i> Open Full Page Dashboard
                    </a>
                </div>
                <?php include __DIR__ . '/dashboards/management-dashboard-widget.php'; ?>
            <?php else: ?>
                <div class="alert alert-danger">
                    <i class="fas fa-lock"></i> Management Control Panel requires manager or admin access
                </div>
            <?php endif; ?>
        </div>

        <!-- SYSTEM HEALTH DASHBOARD -->
        <div id="system-content" class="tab-content <?= ($dashboard === 'system' ? 'active' : '') ?>">
            <?php if ($userRole === 'admin'): ?>
                <div style="margin-bottom: 2rem;">
                    <a href="/modules/consignments/system-dashboard-full.php" class="btn btn-primary btn-lg">
                        <i class="fas fa-expand"></i> Open Full Page Dashboard
                    </a>
                </div>
                <?php include __DIR__ . '/dashboards/system-dashboard-widget.php'; ?>
            <?php else: ?>
                <div class="alert alert-danger">
                    <i class="fas fa-lock"></i> System Health Dashboard requires admin access
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function switchTab(tabName) {
            // Hide all content
            document.querySelectorAll('.tab-content').forEach(el => {
                el.classList.remove('active');
            });

            // Remove active from all tabs
            document.querySelectorAll('.dashboard-tab').forEach(el => {
                el.classList.remove('active');
            });

            // Show selected content
            const contentEl = document.getElementById(tabName + '-content');
            if (contentEl) {
                contentEl.classList.add('active');
            }

            // Mark tab as active
            event.target.closest('.dashboard-tab')?.classList.add('active');

            // Update URL
            history.pushState({ tab: tabName }, '', '?tab=' + tabName);

            // Trigger any reload if needed
            if (window.refreshDashboard) {
                window.refreshDashboard();
            }
        }

        // Auto-refresh every 30 seconds
        setInterval(() => {
            if (window.refreshDashboard) {
                window.refreshDashboard();
            }
        }, 30000);
    </script>
</body>
</html>
