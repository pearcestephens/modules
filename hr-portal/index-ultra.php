<?php
/**
 * HR Portal Module - CONVERTED TO BASE TEMPLATE
 *
 * Now uses VapeUltra base template system
 *
 * @package CIS\Modules\HRPortal
 * @version 2.0.0 - ULTRA EDITION
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../base/Template/Renderer.php';
require_once __DIR__ . '/../base/middleware/MiddlewarePipeline.php';

use App\Template\Renderer;
use App\Middleware\MiddlewarePipeline;

// Create authenticated middleware pipeline
$pipeline = MiddlewarePipeline::createAuthenticated();

// Execute pipeline
$pipeline->handle($_REQUEST, function($request) {

    $pdo = cis_resolve_pdo();

    // Get HR statistics
    $stats = $pdo->query("
        SELECT
            COUNT(*) as total_employees,
            COUNT(CASE WHEN staff_active = 1 THEN 1 END) as active_employees,
            COUNT(CASE WHEN staff_active = 0 THEN 1 END) as inactive_employees
        FROM users
        WHERE is_staff = 1
    ")->fetch(PDO::FETCH_ASSOC);

    // Get recent activities
    $activities = $pdo->query("
        SELECT * FROM hr_activities
        ORDER BY created_at DESC
        LIMIT 10
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Start output buffering
    ob_start();
    ?>

    <div class="container-fluid">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1>HR Portal</h1>
                <p class="text-muted">Human Resources Management Dashboard</p>
            </div>
            <button class="btn btn-primary" onclick="addNewEmployee()">
                <i class="bi bi-person-plus"></i>
                Add Employee
            </button>
        </div>

        <!-- Statistics Grid -->
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <p class="text-muted mb-1">Total Employees</p>
                                <h3 class="mb-0"><?= $stats['total_employees'] ?></h3>
                            </div>
                            <div class="text-primary fs-2">
                                <i class="bi bi-people"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <p class="text-muted mb-1">Active</p>
                                <h3 class="mb-0 text-success"><?= $stats['active_employees'] ?></h3>
                            </div>
                            <div class="text-success fs-2">
                                <i class="bi bi-person-check"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <p class="text-muted mb-1">Inactive</p>
                                <h3 class="mb-0 text-muted"><?= $stats['inactive_employees'] ?></h3>
                            </div>
                            <div class="text-muted fs-2">
                                <i class="bi bi-person-x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="card hover-lift cursor-pointer" onclick="window.location.href='/modules/hr-portal/employees.php'">
                    <div class="card-body text-center">
                        <i class="bi bi-people fs-1 text-primary mb-2"></i>
                        <h5>View Employees</h5>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card hover-lift cursor-pointer" onclick="window.location.href='/modules/hr-portal/attendance.php'">
                    <div class="card-body text-center">
                        <i class="bi bi-calendar-check fs-1 text-success mb-2"></i>
                        <h5>Attendance</h5>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card hover-lift cursor-pointer" onclick="window.location.href='/modules/hr-portal/payroll.php'">
                    <div class="card-body text-center">
                        <i class="bi bi-cash-coin fs-1 text-warning mb-2"></i>
                        <h5>Payroll</h5>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card hover-lift cursor-pointer" onclick="window.location.href='/modules/hr-portal/reports.php'">
                    <div class="card-body text-center">
                        <i class="bi bi-file-earmark-bar-graph fs-1 text-info mb-2"></i>
                        <h5>Reports</h5>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Recent Activity</h5>
            </div>
            <div class="card-body">
                <div class="timeline">
                    <?php if (empty($activities)): ?>
                        <p class="text-muted">No recent activity</p>
                    <?php else: ?>
                        <?php foreach ($activities as $activity): ?>
                        <div class="timeline-item">
                            <div class="timeline-marker"></div>
                            <div class="timeline-content">
                                <p class="mb-1"><?= htmlspecialchars($activity['description'] ?? 'Activity') ?></p>
                                <small class="text-muted"><?= date('M d, Y H:i', strtotime($activity['created_at'])) ?></small>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <?php
    $moduleContent = ob_get_clean();

    // Render with VapeUltra base template
    $renderer = new Renderer();
    $renderer->render($moduleContent, [
        'title' => 'HR Portal - Vape Shed CIS Ultra',
        'class' => 'page-hr-portal',
        'layout' => 'main',
        'scripts' => [
            '/modules/hr-portal/assets/js/hr-portal.js',
        ],
        'styles' => [
            '/modules/hr-portal/assets/css/hr-portal.css',
        ],
        'inline_scripts' => "
            VapeUltra.Core.registerModule('HRPortal', {
                init: function() {
                    console.log('✅ HR Portal module initialized');
                }
            });

            function addNewEmployee() {
                window.location.href = '/modules/hr-portal/employee-create.php';
            }
        ",
        'nav_items' => [
            'hr-portal' => [
                'title' => 'HR Portal',
                'items' => [
                    ['icon' => 'house-door', 'label' => 'Dashboard', 'href' => '/modules/hr-portal/', 'badge' => null],
                    ['icon' => 'people', 'label' => 'Employees', 'href' => '/modules/hr-portal/employees.php', 'badge' => $stats['total_employees']],
                    ['icon' => 'calendar-check', 'label' => 'Attendance', 'href' => '/modules/hr-portal/attendance.php', 'badge' => null],
                    ['icon' => 'cash-coin', 'label' => 'Payroll', 'href' => '/modules/hr-portal/payroll.php', 'badge' => null],
                    ['icon' => 'file-earmark-bar-graph', 'label' => 'Reports', 'href' => '/modules/hr-portal/reports.php', 'badge' => null],
                ]
            ]
        ]
    ]);

});
