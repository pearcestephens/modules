<?php
/**
 * EMPLOYEE DASHBOARD
 *
 * View all employees with sync status across all systems
 */

require_once __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/services/UniversalOnboardingService.php';

use CIS\EmployeeOnboarding\UniversalOnboardingService;

// Check authentication
if (!isset($_SESSION['userID'])) {
    header('Location: /login.php');
    exit;
}

$onboarding = new UniversalOnboardingService($pdo);

// Get all employees
$employees = $onboarding->getAllEmployees();

$pageTitle = 'Employee Management';
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
        body {
            background: #f8f9fa;
            padding: 40px 0;
        }

        .dashboard-header {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }

        .employee-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            transition: all 0.3s;
        }

        .employee-card:hover {
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }

        .sync-badge {
            display: inline-flex;
            align-items: center;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            margin-right: 5px;
        }

        .sync-success {
            background: #d4edda;
            color: #155724;
        }

        .sync-failed {
            background: #f8d7da;
            color: #721c24;
        }

        .sync-pending {
            background: #fff3cd;
            color: #856404;
        }

        .sync-disabled {
            background: #e9ecef;
            color: #6c757d;
        }

        .status-badge {
            padding: 5px 15px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.85rem;
        }

        .status-active { background: #d4edda; color: #155724; }
        .status-inactive { background: #e9ecef; color: #6c757d; }
        .status-pending { background: #fff3cd; color: #856404; }
    </style>
</head>
<body>
    <div class="container">
        <div class="dashboard-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1><i class="fas fa-users"></i> Employee Management</h1>
                    <p class="text-muted mb-0">Manage employees across all systems (CIS, Xero, Deputy, Lightspeed)</p>
                </div>
                <div>
                    <a href="onboarding-wizard.php" class="btn btn-primary btn-lg">
                        <i class="fas fa-user-plus"></i> Add New Employee
                    </a>
                </div>
            </div>
        </div>

        <?php if (empty($employees)): ?>
            <div class="employee-card text-center py-5">
                <i class="fas fa-users text-muted" style="font-size: 4rem;"></i>
                <h3 class="mt-3">No Employees Yet</h3>
                <p class="text-muted">Get started by adding your first employee</p>
                <a href="onboarding-wizard.php" class="btn btn-primary mt-3">
                    <i class="fas fa-plus"></i> Add Employee
                </a>
            </div>
        <?php else: ?>
            <?php foreach ($employees as $employee): ?>
                <?php
                    $roles = json_decode($employee['roles'], true) ?? [];
                    $roleNames = array_map(function($r) { return $r['display_name']; }, $roles);
                ?>
                <div class="employee-card">
                    <div class="row align-items-center">
                        <div class="col-md-4">
                            <h4 class="mb-1">
                                <?php echo htmlspecialchars($employee['full_name']); ?>
                                <span class="status-badge status-<?php echo $employee['status']; ?>">
                                    <?php echo ucfirst($employee['status']); ?>
                                </span>
                            </h4>
                            <p class="text-muted mb-1">
                                <i class="fas fa-briefcase"></i> <?php echo htmlspecialchars($employee['job_title'] ?? 'N/A'); ?>
                            </p>
                            <p class="text-muted mb-0">
                                <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($employee['email']); ?>
                            </p>
                        </div>

                        <div class="col-md-4">
                            <strong>Roles:</strong><br>
                            <?php if (!empty($roleNames)): ?>
                                <?php foreach ($roleNames as $roleName): ?>
                                    <span class="badge bg-primary me-1"><?php echo htmlspecialchars($roleName); ?></span>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <span class="text-muted">No roles assigned</span>
                            <?php endif; ?>
                        </div>

                        <div class="col-md-4">
                            <strong>System Sync:</strong><br>

                            <?php if ($employee['xero_sync_status']): ?>
                                <span class="sync-badge sync-<?php echo $employee['xero_sync_status']; ?>">
                                    <i class="fas fa-<?php echo $employee['xero_sync_status'] === 'synced' ? 'check' : 'times'; ?>"></i>
                                    Xero
                                </span>
                            <?php else: ?>
                                <span class="sync-badge sync-disabled">
                                    <i class="fas fa-minus"></i> Xero
                                </span>
                            <?php endif; ?>

                            <?php if ($employee['deputy_sync_status']): ?>
                                <span class="sync-badge sync-<?php echo $employee['deputy_sync_status']; ?>">
                                    <i class="fas fa-<?php echo $employee['deputy_sync_status'] === 'synced' ? 'check' : 'times'; ?>"></i>
                                    Deputy
                                </span>
                            <?php else: ?>
                                <span class="sync-badge sync-disabled">
                                    <i class="fas fa-minus"></i> Deputy
                                </span>
                            <?php endif; ?>

                            <?php if ($employee['lightspeed_sync_status']): ?>
                                <span class="sync-badge sync-<?php echo $employee['lightspeed_sync_status']; ?>">
                                    <i class="fas fa-<?php echo $employee['lightspeed_sync_status'] === 'synced' ? 'check' : 'times'; ?>"></i>
                                    Lightspeed
                                </span>
                            <?php else: ?>
                                <span class="sync-badge sync-disabled">
                                    <i class="fas fa-minus"></i> Lightspeed
                                </span>
                            <?php endif; ?>

                            <div class="mt-2">
                                <a href="employee-detail.php?id=<?php echo $employee['id']; ?>" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-eye"></i> View Details
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
