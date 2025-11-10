<?php
/**
 * Modules Landing Page
 * Shows available modules and completion status
 */

// Check database tables exist to determine installation status
require_once '../config/database.php';

function checkTableExists($pdo, $tableName) {
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE '$tableName'");
        return $stmt->rowCount() > 0;
    } catch (Exception $e) {
        return false;
    }
}

// Check module installation status
$outletsInstalled = checkTableExists($pdo, 'outlets');
$biInstalled = checkTableExists($pdo, 'financial_snapshots');
$employeeOnboardingInstalled = checkTableExists($pdo, 'users') && checkTableExists($pdo, 'roles');

// Check data exists
$outletsData = 0;
$biData = 0;
if ($outletsInstalled) {
    $stmt = $pdo->query("SELECT COUNT(*) FROM outlets");
    $outletsData = $stmt->fetchColumn();
}
if ($biInstalled) {
    $stmt = $pdo->query("SELECT COUNT(*) FROM financial_snapshots");
    $biData = $stmt->fetchColumn();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CIS Modules - The Vape Shed</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 40px 0;
        }
        .main-container {
            max-width: 1200px;
            margin: 0 auto;
        }
        .hero-card {
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            margin-bottom: 30px;
        }
        .module-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .module-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
        }
        .status-badge {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        .status-installed {
            background: #d4edda;
            color: #155724;
        }
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        .status-error {
            background: #f8d7da;
            color: #721c24;
        }
        .next-steps-card {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
            border-radius: 15px;
            padding: 30px;
            margin-top: 30px;
        }
        .step-item {
            background: rgba(255,255,255,0.2);
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 15px;
            border-left: 4px solid white;
        }
        .step-item.completed {
            opacity: 0.6;
            text-decoration: line-through;
        }
        .btn-launch {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
            padding: 12px 30px;
            border-radius: 25px;
            font-weight: 600;
            transition: transform 0.2s ease;
        }
        .btn-launch:hover {
            transform: scale(1.05);
            color: white;
        }
        .progress-ring {
            width: 120px;
            height: 120px;
            margin: 0 auto 20px;
        }
        .module-icon {
            font-size: 3rem;
            margin-bottom: 15px;
            display: block;
        }
    </style>
</head>
<body>
    <div class="main-container">
        <!-- Hero Card -->
        <div class="hero-card text-center">
            <h1 class="display-4 mb-3">
                <i class="bi bi-grid-3x3-gap text-primary"></i> CIS Modules
            </h1>
            <p class="lead text-muted">Central Information System for The Vape Shed</p>
            <hr class="my-4">
            <div class="row text-center">
                <div class="col-md-4">
                    <h2 class="text-primary"><?php echo $outletsInstalled ? $outletsData : '0'; ?></h2>
                    <p class="text-muted">Outlets Tracked</p>
                </div>
                <div class="col-md-4">
                    <h2 class="text-success"><?php echo $biData; ?></h2>
                    <p class="text-muted">Financial Snapshots</p>
                </div>
                <div class="col-md-4">
                    <h2 class="text-info"><?php echo ($outletsInstalled ? 1 : 0) + ($biInstalled ? 1 : 0) + ($employeeOnboardingInstalled ? 1 : 0); ?>/3</h2>
                    <p class="text-muted">Modules Active</p>
                </div>
            </div>
        </div>

        <!-- Modules Grid -->
        <div class="row">
            <!-- Employee Onboarding Module -->
            <div class="col-md-4">
                <div class="module-card">
                    <div class="text-center">
                        <i class="bi bi-person-plus-fill module-icon text-primary"></i>
                        <h4>Employee Onboarding</h4>
                        <?php if ($employeeOnboardingInstalled): ?>
                            <span class="status-badge status-installed">
                                <i class="bi bi-check-circle"></i> Installed
                            </span>
                        <?php else: ?>
                            <span class="status-badge status-pending">
                                <i class="bi bi-exclamation-triangle"></i> Not Installed
                            </span>
                        <?php endif; ?>
                    </div>
                    <hr>
                    <p class="text-muted small">Universal employee provisioning to CIS, Xero, Deputy, and Lightspeed with automated sync.</p>
                    <div class="d-grid gap-2">
                        <?php if ($employeeOnboardingInstalled): ?>
                            <a href="employee-onboarding/dashboard.php" class="btn btn-primary">
                                <i class="bi bi-box-arrow-up-right"></i> Open Dashboard
                            </a>
                            <a href="employee-onboarding/onboarding-wizard.php" class="btn btn-outline-primary">
                                <i class="bi bi-plus-circle"></i> Add Employee
                            </a>
                        <?php else: ?>
                            <button class="btn btn-warning" onclick="alert('Run: mysql -u jcepnzzkmj -p jcepnzzkmj < modules/employee-onboarding/database/schema.sql')">
                                <i class="bi bi-download"></i> Install Module
                            </button>
                        <?php endif; ?>
                        <a href="employee-onboarding/README.md" class="btn btn-outline-secondary btn-sm">
                            <i class="bi bi-book"></i> Documentation
                        </a>
                    </div>
                </div>
            </div>

            <!-- Outlets Module -->
            <div class="col-md-4">
                <div class="module-card">
                    <div class="text-center">
                        <i class="bi bi-shop-window module-icon text-success"></i>
                        <h4>Outlets Management</h4>
                        <?php if ($outletsInstalled): ?>
                            <span class="status-badge status-installed">
                                <i class="bi bi-check-circle"></i> Installed
                            </span>
                        <?php else: ?>
                            <span class="status-badge status-pending">
                                <i class="bi bi-exclamation-triangle"></i> Not Installed
                            </span>
                        <?php endif; ?>
                    </div>
                    <hr>
                    <p class="text-muted small">Comprehensive management of all 19 retail locations with landlords, leases, revenue tracking, and performance metrics.</p>
                    <div class="d-grid gap-2">
                        <?php if ($outletsInstalled): ?>
                            <a href="outlets/dashboard.php" class="btn btn-success">
                                <i class="bi bi-box-arrow-up-right"></i> Open Dashboard
                            </a>
                            <small class="text-muted text-center">
                                <i class="bi bi-geo-alt"></i> <?php echo $outletsData; ?> locations tracked
                            </small>
                        <?php else: ?>
                            <button class="btn btn-warning" onclick="showInstallCommand('outlets')">
                                <i class="bi bi-download"></i> Install Module
                            </button>
                        <?php endif; ?>
                        <a href="outlets/README.md" class="btn btn-outline-secondary btn-sm">
                            <i class="bi bi-book"></i> Documentation
                        </a>
                    </div>
                </div>
            </div>

            <!-- Business Intelligence Module -->
            <div class="col-md-4">
                <div class="module-card">
                    <div class="text-center">
                        <i class="bi bi-graph-up-arrow module-icon text-info"></i>
                        <h4>Business Intelligence</h4>
                        <?php if ($biInstalled): ?>
                            <span class="status-badge status-installed">
                                <i class="bi bi-check-circle"></i> Installed
                            </span>
                        <?php else: ?>
                            <span class="status-badge status-pending">
                                <i class="bi bi-exclamation-triangle"></i> Not Installed
                            </span>
                        <?php endif; ?>
                    </div>
                    <hr>
                    <p class="text-muted small">Complete P&L tracking, profitability analysis, forecasting, and financial insights with interactive visualizations.</p>
                    <div class="d-grid gap-2">
                        <?php if ($biInstalled): ?>
                            <a href="business-intelligence/dashboard.php" class="btn btn-info">
                                <i class="bi bi-box-arrow-up-right"></i> Open Dashboard
                            </a>
                            <small class="text-muted text-center">
                                <i class="bi bi-database"></i> <?php echo $biData; ?> snapshots recorded
                            </small>
                        <?php else: ?>
                            <button class="btn btn-warning" onclick="showInstallCommand('bi')">
                                <i class="bi bi-download"></i> Install Module
                            </button>
                        <?php endif; ?>
                        <a href="business-intelligence/README.md" class="btn btn-outline-secondary btn-sm">
                            <i class="bi bi-book"></i> Documentation
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Next Steps Card -->
        <?php if (!$outletsInstalled || !$biInstalled): ?>
        <div class="next-steps-card">
            <h3 class="mb-4">
                <i class="bi bi-list-check"></i> Next Steps to Complete Installation
            </h3>

            <?php if (!$outletsInstalled): ?>
            <div class="step-item">
                <h5><i class="bi bi-1-circle"></i> Install Outlets Module Database</h5>
                <p class="mb-2">Run the following command in your terminal:</p>
                <code style="background: rgba(0,0,0,0.2); padding: 10px; border-radius: 5px; display: block; margin-top: 10px;">
                    cd /home/master/applications/jcepnzzkmj/public_html/modules/outlets<br>
                    mysql -u jcepnzzkmj -p'wprKh9Jq63' -h 127.0.0.1 jcepnzzkmj &lt; database/schema.sql
                </code>
            </div>
            <?php endif; ?>

            <?php if (!$biInstalled): ?>
            <div class="step-item">
                <h5><i class="bi bi-2-circle"></i> Install Business Intelligence Module Database</h5>
                <p class="mb-2">Run the following command in your terminal:</p>
                <code style="background: rgba(0,0,0,0.2); padding: 10px; border-radius: 5px; display: block; margin-top: 10px;">
                    cd /home/master/applications/jcepnzzkmj/public_html/modules/business-intelligence<br>
                    mysql -u jcepnzzkmj -p'wprKh9Jq63' -h 127.0.0.1 jcepnzzkmj &lt; database/schema.sql
                </code>
            </div>
            <?php endif; ?>

            <?php if ($outletsInstalled && !$biInstalled): ?>
            <div class="step-item">
                <h5><i class="bi bi-3-circle"></i> Configure Google Maps API Key</h5>
                <p class="mb-2">Edit <code>modules/outlets/dashboard.php</code> line 206:</p>
                <ol class="small mb-0">
                    <li>Get API key from <a href="https://console.cloud.google.com/apis/credentials" target="_blank" class="text-white"><u>Google Cloud Console</u></a></li>
                    <li>Replace <code>YOUR_GOOGLE_MAPS_API_KEY</code> with your actual key</li>
                </ol>
            </div>
            <?php endif; ?>

            <?php if ($outletsInstalled || $biInstalled): ?>
            <div class="step-item">
                <h5><i class="bi bi-4-circle"></i> Set Up Data Sync Cron Jobs</h5>
                <p class="mb-2">Automate data imports from Lightspeed, Xero, and Deputy:</p>
                <ul class="small mb-0">
                    <li>Daily Lightspeed sales sync → <code>financial_snapshots</code></li>
                    <li>Weekly Xero expense sync → <code>overhead_allocation</code></li>
                    <li>Weekly Deputy labor cost sync → <code>staff_costs_detail</code></li>
                </ul>
            </div>
            <?php endif; ?>

            <div class="text-center mt-4">
                <a href="COMPLETION_REPORT.md" class="btn btn-light btn-lg">
                    <i class="bi bi-file-earmark-text"></i> View Full Completion Report
                </a>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($outletsInstalled && $biInstalled): ?>
        <div class="next-steps-card">
            <h3 class="mb-4 text-center">
                <i class="bi bi-check-circle-fill"></i> All Modules Installed! 🎉
            </h3>
            <p class="text-center lead">Your CIS system is ready. Here's what you can do next:</p>

            <div class="row mt-4">
                <div class="col-md-4 text-center">
                    <i class="bi bi-map" style="font-size: 3rem;"></i>
                    <h5 class="mt-3">Configure Maps</h5>
                    <p class="small">Add Google Maps API key to visualize store locations</p>
                </div>
                <div class="col-md-4 text-center">
                    <i class="bi bi-arrow-repeat" style="font-size: 3rem;"></i>
                    <h5 class="mt-3">Automate Sync</h5>
                    <p class="small">Set up cron jobs for Lightspeed, Xero, and Deputy data imports</p>
                </div>
                <div class="col-md-4 text-center">
                    <i class="bi bi-graph-up" style="font-size: 3rem;"></i>
                    <h5 class="mt-3">Analyze Data</h5>
                    <p class="small">Start tracking revenue, profitability, and performance metrics</p>
                </div>
            </div>

            <div class="text-center mt-4">
                <a href="COMPLETION_REPORT.md" class="btn btn-light btn-lg me-2">
                    <i class="bi bi-file-earmark-text"></i> View Full Report
                </a>
                <a href="../" class="btn btn-outline-light btn-lg">
                    <i class="bi bi-house"></i> Go to CIS Home
                </a>
            </div>
        </div>
        <?php endif; ?>

        <!-- Footer -->
        <div class="text-center text-white mt-5">
            <p class="mb-1">
                <i class="bi bi-building"></i> Ecigdis Limited / The Vape Shed
            </p>
            <p class="small opacity-75">
                CIS Modules v1.0.0 | Built November 2025
            </p>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function showInstallCommand(module) {
            let command = '';
            if (module === 'outlets') {
                command = "cd /home/master/applications/jcepnzzkmj/public_html/modules/outlets && mysql -u jcepnzzkmj -p'wprKh9Jq63' -h 127.0.0.1 jcepnzzkmj < database/schema.sql";
            } else if (module === 'bi') {
                command = "cd /home/master/applications/jcepnzzkmj/public_html/modules/business-intelligence && mysql -u jcepnzzkmj -p'wprKh9Jq63' -h 127.0.0.1 jcepnzzkmj < database/schema.sql";
            }

            alert('Copy and run this command in your terminal:\n\n' + command);

            // Copy to clipboard if possible
            if (navigator.clipboard) {
                navigator.clipboard.writeText(command).then(() => {
                    console.log('Command copied to clipboard!');
                });
            }
        }
    </script>
</body>
</html>
