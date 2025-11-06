<?php
/**
 * CIS Module Installer Dashboard
 * Unified installation and progress tracking for all modules
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Try multiple config paths
$configPaths = [
    __DIR__ . '/../config/database.php',
    __DIR__ . '/config/database.php',
    __DIR__ . '/../../config/database.php'
];

$pdo = null;
foreach ($configPaths as $path) {
    if (file_exists($path)) {
        require_once $path;
        break;
    }
}

// If still no PDO, create connection manually
if (!isset($pdo)) {
    try {
        $envFile = __DIR__ . '/../.env';
        if (!file_exists($envFile)) {
            $envFile = __DIR__ . '/../../.env';
        }

        if (file_exists($envFile)) {
            $env = parse_ini_file($envFile);
            $pdo = new PDO(
                "mysql:host={$env['DB_HOST']};dbname={$env['DB_NAME']};charset=utf8mb4",
                $env['DB_USER'],
                $env['DB_PASSWORD'],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
        } else {
            // Fallback to hardcoded if no .env
            $pdo = new PDO(
                "mysql:host=localhost;dbname=jcepnzzkmj;charset=utf8mb4",
                "jcepnzzkmj",
                "",
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
        }
    } catch (Exception $e) {
        die("Database connection failed: " . $e->getMessage());
    }
}

// Define all modules with their requirements
$modules = [
    'employee-onboarding' => [
        'name' => 'Employee Onboarding',
        'icon' => 'bi-person-plus-fill',
        'color' => 'primary',
        'description' => 'Universal employee provisioning to CIS, Xero, Deputy, and Lightspeed',
        'tables' => ['users', 'roles', 'permissions', 'role_permissions', 'user_roles', 'external_system_mappings', 'onboarding_log', 'sync_queue', 'user_permissions_override'],
        'views' => ['vw_users_complete'],
        'procedures' => ['check_user_permission'],
        'schema_file' => 'employee-onboarding/database/schema.sql',
        'dashboard' => 'employee-onboarding/dashboard.php',
        'priority' => 1
    ],
    'outlets' => [
        'name' => 'Outlets Management',
        'icon' => 'bi-shop-window',
        'color' => 'success',
        'description' => 'Complete management of 19 retail locations with landlords, leases, revenue tracking',
        'tables' => ['outlets', 'outlet_photos', 'outlet_operating_hours', 'outlet_closure_history', 'outlet_revenue_snapshots', 'outlet_performance_metrics', 'outlet_documents', 'outlet_maintenance_log'],
        'views' => ['vw_outlets_overview'],
        'procedures' => [],
        'schema_file' => 'outlets/database/schema.sql',
        'dashboard' => 'outlets/dashboard.php',
        'config_required' => ['Google Maps API Key'],
        'priority' => 2
    ],
    'business-intelligence' => [
        'name' => 'Business Intelligence',
        'icon' => 'bi-graph-up-arrow',
        'color' => 'info',
        'description' => 'Complete P&L tracking, profitability analysis, forecasting with 5 chart visualizations',
        'tables' => ['financial_snapshots', 'revenue_by_category', 'staff_costs_detail', 'overhead_allocation', 'benchmark_metrics', 'forecasts', 'target_settings', 'variance_analysis'],
        'views' => ['vw_current_month_pnl', 'vw_store_profitability_rankings', 'vw_monthly_trends', 'vw_performance_outliers'],
        'procedures' => ['sp_calculate_financial_snapshot'],
        'schema_file' => 'business-intelligence/database/schema.sql',
        'dashboard' => 'business-intelligence/dashboard.php',
        'priority' => 3
    ],
    'store-reports' => [
        'name' => 'Store Reports',
        'icon' => 'bi-file-earmark-text',
        'color' => 'warning',
        'description' => 'AI-powered store inspection reports with GPT-4 Vision analysis',
        'tables' => ['store_reports', 'store_report_photos', 'store_report_comments', 'store_report_action_items', 'store_report_scores', 'ai_analysis_requests'],
        'views' => ['vw_store_reports_summary', 'vw_pending_action_items'],
        'procedures' => [],
        'schema_file' => 'store-reports/database/schema.sql',
        'dashboard' => 'store-reports/dashboard.php',
        'priority' => 4
    ],
    'hr-portal' => [
        'name' => 'HR Portal',
        'icon' => 'bi-people-fill',
        'color' => 'danger',
        'description' => 'Employee reviews, tracking definitions, performance management',
        'tables' => ['employee_reviews', 'review_questions', 'review_responses', 'employee_tracking_definitions', 'employee_tracking_entries'],
        'views' => [],
        'procedures' => [],
        'schema_file' => 'hr-portal/database/schema.sql',
        'dashboard' => 'hr-portal/dashboard.php',
        'priority' => 5
    ],
    'staff-performance' => [
        'name' => 'Staff Performance',
        'icon' => 'bi-trophy',
        'color' => 'purple',
        'description' => 'Advanced performance tracking with BI engine and KPI dashboards',
        'tables' => ['staff_performance_metrics', 'staff_kpis', 'staff_goals', 'performance_reviews'],
        'views' => [],
        'procedures' => [],
        'schema_file' => 'staff-performance/database/schema.sql',
        'dashboard' => 'staff-performance/dashboard.php',
        'priority' => 6
    ],
    'consignments' => [
        'name' => 'Consignments',
        'icon' => 'bi-box-seam',
        'color' => 'secondary',
        'description' => 'Lightspeed consignment management and transfer tracking',
        'tables' => ['consignments', 'consignment_items', 'transfer_requests'],
        'views' => [],
        'procedures' => [],
        'schema_file' => 'consignments/database/schema.sql',
        'dashboard' => 'consignments/dashboard.php',
        'priority' => 7
    ],
    'bank-transactions' => [
        'name' => 'Bank Transactions',
        'icon' => 'bi-bank',
        'color' => 'success',
        'description' => 'Bank transaction reconciliation and matching algorithms',
        'tables' => ['bank_transactions', 'transaction_matches', 'reconciliation_rules'],
        'views' => [],
        'procedures' => [],
        'schema_file' => 'bank-transactions/database/schema.sql',
        'dashboard' => 'bank-transactions/dashboard.php',
        'priority' => 8
    ],
    'flagged_products' => [
        'name' => 'Flagged Products',
        'icon' => 'bi-flag-fill',
        'color' => 'warning',
        'description' => 'Product quality control and issue tracking',
        'tables' => ['flagged_products', 'product_flags', 'flag_resolutions'],
        'views' => [],
        'procedures' => [],
        'schema_file' => 'flagged_products/database/schema.sql',
        'dashboard' => 'flagged_products/dashboard.php',
        'priority' => 9
    ],
    'ecommerce-ops' => [
        'name' => 'Ecommerce Operations',
        'icon' => 'bi-cart-check',
        'color' => 'primary',
        'description' => 'E-commerce operations, orders, and inventory management',
        'tables' => ['ecommerce_orders', 'order_items', 'inventory_sync'],
        'views' => [],
        'procedures' => [],
        'schema_file' => 'ecommerce-ops/database/schema.sql',
        'dashboard' => 'ecommerce-ops/dashboard.php',
        'priority' => 10
    ]
];

// Check installation status for each module
function checkModuleStatus($pdo, $module) {
    $status = [
        'installed' => false,
        'tables_exist' => 0,
        'tables_total' => count($module['tables']),
        'views_exist' => 0,
        'views_total' => count($module['views']),
        'procedures_exist' => 0,
        'procedures_total' => count($module['procedures']),
        'data_count' => 0,
        'progress' => 0
    ];

    // Check tables
    foreach ($module['tables'] as $table) {
        try {
            $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
            if ($stmt->rowCount() > 0) {
                $status['tables_exist']++;
            }
        } catch (Exception $e) {}
    }

    // Check views
    foreach ($module['views'] as $view) {
        try {
            // Get database name dynamically
            $dbName = $pdo->query("SELECT DATABASE()")->fetchColumn();
            $stmt = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'VIEW' AND Tables_in_$dbName LIKE '$view'");
            if ($stmt->rowCount() > 0) {
                $status['views_exist']++;
            }
        } catch (Exception $e) {
            // Fallback: try simpler check
            try {
                $stmt = $pdo->query("SELECT 1 FROM INFORMATION_SCHEMA.VIEWS WHERE TABLE_NAME = '$view'");
                if ($stmt->rowCount() > 0) {
                    $status['views_exist']++;
                }
            } catch (Exception $e2) {}
        }
    }

    // Check procedures
    foreach ($module['procedures'] as $proc) {
        try {
            $dbName = $pdo->query("SELECT DATABASE()")->fetchColumn();
            $stmt = $pdo->query("SHOW PROCEDURE STATUS WHERE Db = '$dbName' AND Name = '$proc'");
            if ($stmt->rowCount() > 0) {
                $status['procedures_exist']++;
            }
        } catch (Exception $e) {}
    }

    // Calculate progress
    $totalComponents = $status['tables_total'] + $status['views_total'] + $status['procedures_total'];
    $existingComponents = $status['tables_exist'] + $status['views_exist'] + $status['procedures_exist'];

    if ($totalComponents > 0) {
        $status['progress'] = round(($existingComponents / $totalComponents) * 100);
        $status['installed'] = ($status['progress'] >= 100);
    }

    // Get data count from primary table if exists
    if ($status['tables_exist'] > 0 && isset($module['tables'][0])) {
        try {
            $stmt = $pdo->query("SELECT COUNT(*) FROM " . $module['tables'][0]);
            $status['data_count'] = $stmt->fetchColumn();
        } catch (Exception $e) {}
    }

    return $status;
}

// Get status for all modules
$moduleStatuses = [];
$totalModules = count($modules);
$installedModules = 0;
$totalProgress = 0;

foreach ($modules as $key => $module) {
    $moduleStatuses[$key] = checkModuleStatus($pdo, $module);
    if ($moduleStatuses[$key]['installed']) {
        $installedModules++;
    }
    $totalProgress += $moduleStatuses[$key]['progress'];
}

$overallProgress = round($totalProgress / $totalModules);

// Sort modules by priority
uasort($modules, function($a, $b) {
    return $a['priority'] - $b['priority'];
});
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CIS Module Installer - The Vape Shed</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px 0;
        }
        .dashboard-container {
            max-width: 1400px;
            margin: 0 auto;
        }
        .header-card {
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            margin-bottom: 30px;
        }
        .module-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }
        .module-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
        }
        .module-card.installed {
            border-left: 5px solid #28a745;
        }
        .module-card.partial {
            border-left: 5px solid #ffc107;
        }
        .module-card.not-installed {
            border-left: 5px solid #dc3545;
        }
        .progress-circle {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: conic-gradient(#667eea var(--progress), #e9ecef var(--progress));
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 1.2rem;
            position: relative;
        }
        .progress-circle::before {
            content: '';
            position: absolute;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: white;
        }
        .progress-circle span {
            position: relative;
            z-index: 1;
        }
        .btn-install {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
            transition: transform 0.2s ease;
        }
        .btn-install:hover {
            transform: scale(1.05);
            color: white;
        }
        .status-badge {
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        .overall-progress {
            height: 40px;
            border-radius: 20px;
            overflow: hidden;
            background: #e9ecef;
            margin: 20px 0;
        }
        .quick-install-panel {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
        }
        .module-icon-large {
            font-size: 2.5rem;
        }
        .stat-box {
            text-align: center;
            padding: 15px;
        }
        .stat-box h2 {
            font-size: 2.5rem;
            margin: 0;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Header -->
        <div class="header-card">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="mb-2">
                        <i class="bi bi-grid-3x3-gap text-primary"></i> CIS Module Installer
                    </h1>
                    <p class="text-muted mb-0">Central Information System - Module Management Dashboard</p>
                </div>
                <div class="col-md-4 text-end">
                    <a href="index.php" class="btn btn-outline-secondary me-2">
                        <i class="bi bi-arrow-left"></i> Back to Modules
                    </a>
                    <button class="btn btn-primary" onclick="location.reload()">
                        <i class="bi bi-arrow-clockwise"></i> Refresh
                    </button>
                </div>
            </div>

            <!-- Overall Progress -->
            <div class="mt-4">
                <div class="d-flex justify-content-between mb-2">
                    <strong>Overall Installation Progress</strong>
                    <span><?php echo $installedModules; ?> / <?php echo $totalModules; ?> Modules Complete</span>
                </div>
                <div class="overall-progress">
                    <div class="progress-bar bg-success" style="width: <?php echo $overallProgress; ?>%; height: 100%; transition: width 0.5s ease;">
                        <strong style="line-height: 40px;"><?php echo $overallProgress; ?>%</strong>
                    </div>
                </div>
            </div>

            <!-- Stats -->
            <div class="row mt-4">
                <div class="col-md-3">
                    <div class="stat-box">
                        <i class="bi bi-check-circle-fill text-success" style="font-size: 2rem;"></i>
                        <h2 class="text-success"><?php echo $installedModules; ?></h2>
                        <p class="text-muted mb-0">Installed</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-box">
                        <i class="bi bi-hourglass-split text-warning" style="font-size: 2rem;"></i>
                        <h2 class="text-warning"><?php echo $totalModules - $installedModules; ?></h2>
                        <p class="text-muted mb-0">Pending</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-box">
                        <i class="bi bi-percent text-info" style="font-size: 2rem;"></i>
                        <h2 class="text-info"><?php echo $overallProgress; ?>%</h2>
                        <p class="text-muted mb-0">Complete</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-box">
                        <i class="bi bi-lightning-fill text-primary" style="font-size: 2rem;"></i>
                        <h2 class="text-primary"><?php echo count(array_filter($moduleStatuses, fn($s) => $s['progress'] > 0 && $s['progress'] < 100)); ?></h2>
                        <p class="text-muted mb-0">In Progress</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Install Panel -->
        <?php if ($installedModules < $totalModules): ?>
        <div class="quick-install-panel">
            <h4 class="mb-3"><i class="bi bi-rocket-takeoff"></i> Quick Install All Pending Modules</h4>
            <p class="mb-3">Run this command to install all pending modules at once:</p>
            <div class="bg-dark text-white p-3 rounded mb-3" style="font-family: monospace; overflow-x: auto;">
                cd /home/master/applications/jcepnzzkmj/public_html/modules && \<br>
                <?php
                $commands = [];
                foreach ($modules as $key => $module) {
                    if (!$moduleStatuses[$key]['installed'] && file_exists($module['schema_file'])) {
                        $commands[] = "mysql -u jcepnzzkmj -p'\$(cat ../.env | grep DB_PASSWORD | cut -d= -f2)' jcepnzzkmj < {$module['schema_file']}";
                    }
                }
                echo implode(" && \<br>", $commands);
                ?>
            </div>
            <button class="btn btn-light" onclick="copyQuickInstall()">
                <i class="bi bi-clipboard"></i> Copy Command
            </button>
        </div>
        <?php endif; ?>

        <!-- Module Grid -->
        <div class="row">
            <?php foreach ($modules as $key => $module):
                $status = $moduleStatuses[$key];
                $statusClass = $status['installed'] ? 'installed' : ($status['progress'] > 0 ? 'partial' : 'not-installed');
            ?>
            <div class="col-md-6 col-lg-4">
                <div class="module-card <?php echo $statusClass; ?>">
                    <div class="d-flex align-items-start mb-3">
                        <div class="me-3">
                            <i class="bi <?php echo $module['icon']; ?> module-icon-large text-<?php echo $module['color']; ?>"></i>
                        </div>
                        <div class="flex-grow-1">
                            <h5 class="mb-1"><?php echo $module['name']; ?></h5>
                            <p class="text-muted small mb-2"><?php echo $module['description']; ?></p>
                        </div>
                        <div class="progress-circle" style="--progress: <?php echo $status['progress']; ?>%;">
                            <span><?php echo $status['progress']; ?>%</span>
                        </div>
                    </div>

                    <!-- Status Details -->
                    <div class="row g-2 mb-3 small">
                        <div class="col-4 text-center">
                            <div class="<?php echo $status['tables_exist'] == $status['tables_total'] ? 'text-success' : 'text-muted'; ?>">
                                <i class="bi bi-table"></i><br>
                                <strong><?php echo $status['tables_exist']; ?>/<?php echo $status['tables_total']; ?></strong><br>
                                Tables
                            </div>
                        </div>
                        <div class="col-4 text-center">
                            <div class="<?php echo $status['views_exist'] == $status['views_total'] ? 'text-success' : 'text-muted'; ?>">
                                <i class="bi bi-eye"></i><br>
                                <strong><?php echo $status['views_exist']; ?>/<?php echo $status['views_total']; ?></strong><br>
                                Views
                            </div>
                        </div>
                        <div class="col-4 text-center">
                            <div class="<?php echo $status['procedures_exist'] == $status['procedures_total'] ? 'text-success' : 'text-muted'; ?>">
                                <i class="bi bi-gear"></i><br>
                                <strong><?php echo $status['procedures_exist']; ?>/<?php echo $status['procedures_total']; ?></strong><br>
                                Procs
                            </div>
                        </div>
                    </div>

                    <?php if ($status['installed']): ?>
                        <!-- Installed - Show Actions -->
                        <div class="alert alert-success mb-2 py-2">
                            <i class="bi bi-check-circle-fill"></i> <strong>Installed</strong>
                            <?php if ($status['data_count'] > 0): ?>
                                - <?php echo number_format($status['data_count']); ?> records
                            <?php endif; ?>
                        </div>
                        <div class="d-grid gap-2">
                            <a href="<?php echo $module['dashboard']; ?>" class="btn btn-<?php echo $module['color']; ?> btn-sm">
                                <i class="bi bi-box-arrow-up-right"></i> Open Dashboard
                            </a>
                        </div>
                    <?php elseif ($status['progress'] > 0): ?>
                        <!-- Partial - Show Progress -->
                        <div class="alert alert-warning mb-2 py-2">
                            <i class="bi bi-exclamation-triangle-fill"></i> <strong>Incomplete Installation</strong>
                        </div>
                        <div class="d-grid gap-2">
                            <button class="btn btn-warning btn-sm" onclick="installModule('<?php echo $key; ?>')">
                                <i class="bi bi-arrow-clockwise"></i> Reinstall
                            </button>
                        </div>
                    <?php else: ?>
                        <!-- Not Installed - Show Install Button -->
                        <div class="alert alert-danger mb-2 py-2">
                            <i class="bi bi-x-circle-fill"></i> <strong>Not Installed</strong>
                        </div>
                        <div class="d-grid gap-2">
                            <button class="btn btn-install btn-sm" onclick="installModule('<?php echo $key; ?>')">
                                <i class="bi bi-download"></i> Install Now
                            </button>
                        </div>
                    <?php endif; ?>

                    <!-- Additional Info -->
                    <?php if (isset($module['config_required'])): ?>
                    <div class="mt-2">
                        <small class="text-muted">
                            <i class="bi bi-info-circle"></i> Requires: <?php echo implode(', ', $module['config_required']); ?>
                        </small>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Footer -->
        <div class="text-center text-white mt-4">
            <p class="mb-1"><i class="bi bi-building"></i> Ecigdis Limited / The Vape Shed</p>
            <p class="small opacity-75">CIS Module Installer v1.0.0</p>
        </div>
    </div>

    <!-- Install Modal -->
    <div class="modal fade" id="installModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Install Module</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="installInstructions"></div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function installModule(moduleKey) {
            const modules = <?php echo json_encode($modules); ?>;
            const module = modules[moduleKey];

            const command = `cd /home/master/applications/jcepnzzkmj/public_html/modules/${moduleKey} && mysql -u jcepnzzkmj -p'$(cat ../../.env | grep DB_PASSWORD | cut -d= -f2)' jcepnzzkmj < database/schema.sql`;

            const html = `
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i> Copy and run this command in your terminal:
                </div>
                <div class="bg-dark text-white p-3 rounded mb-3" style="font-family: monospace; word-break: break-all;">
                    ${command}
                </div>
                <button class="btn btn-primary" onclick="copyToClipboard('${command.replace(/'/g, "\\'")}')">
                    <i class="bi bi-clipboard"></i> Copy Command
                </button>
                <hr>
                <p class="text-muted small">After running the command, refresh this page to see the updated status.</p>
            `;

            document.getElementById('installInstructions').innerHTML = html;
            new bootstrap.Modal(document.getElementById('installModal')).show();
        }

        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(() => {
                alert('Command copied to clipboard!');
            });
        }

        function copyQuickInstall() {
            const codeBlock = document.querySelector('.quick-install-panel .bg-dark');
            const text = codeBlock.textContent.replace(/\\/g, '').replace(/\n/g, ' ');
            navigator.clipboard.writeText(text).then(() => {
                alert('Quick install command copied to clipboard!');
            });
        }
    </script>
</body>
</html>
