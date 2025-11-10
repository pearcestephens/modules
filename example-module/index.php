<?php
/**
 * Example Module - Dashboard
 * 
 * Demonstrates proper usage of CIS Base Bootstrap and template system
 */

// 1. Load base bootstrap (MANDATORY)
require_once __DIR__ . '/../base/bootstrap.php';

// 2. Require authentication
requireAuth();

// 3. Check permissions
requirePermission('example.view');

// 4. Your module logic here
$data = [
    'stats' => [
        'total_items' => 150,
        'active_items' => 120,
        'pending_items' => 30
    ],
    'recent_activity' => [
        ['action' => 'Item created', 'time' => '2 minutes ago'],
        ['action' => 'Item updated', 'time' => '5 minutes ago'],
        ['action' => 'Item deleted', 'time' => '10 minutes ago']
    ]
];

// Get current user
$user = getCurrentUser();
$userName = e($user['username'] ?? 'Unknown');

// 5. Build your page content
ob_start();
?>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <h1>Example Module Dashboard</h1>
            <p class="text-muted">Welcome, <?= $userName ?>! This is an example module demonstrating the CIS template system.</p>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h5 class="card-title">Total Items</h5>
                    <h2><?= $data['stats']['total_items'] ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h5 class="card-title">Active Items</h5>
                    <h2><?= $data['stats']['active_items'] ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <h5 class="card-title">Pending Items</h5>
                    <h2><?= $data['stats']['pending_items'] ?></h2>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Activity -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5>Recent Activity</h5>
                </div>
                <div class="card-body">
                    <div class="list-group">
                        <?php foreach ($data['recent_activity'] as $activity): ?>
                            <div class="list-group-item">
                                <div class="d-flex w-100 justify-content-between">
                                    <h6 class="mb-1"><?= e($activity['action']) ?></h6>
                                    <small class="text-muted"><?= e($activity['time']) ?></small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Theme & Config Info -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5>System Information</h5>
                </div>
                <div class="card-body">
                    <table class="table table-sm">
                        <tbody>
                            <tr>
                                <th>Active Theme:</th>
                                <td><?= e(theme()) ?></td>
                            </tr>
                            <tr>
                                <th>User Role:</th>
                                <td><?= e(getUserRole()) ?></td>
                            </tr>
                            <tr>
                                <th>User ID:</th>
                                <td><?= getUserId() ?></td>
                            </tr>
                            <tr>
                                <th>Session ID:</th>
                                <td><?= substr(session_id(), 0, 16) ?>...</td>
                            </tr>
                            <tr>
                                <th>Bootstrap Loaded:</th>
                                <td><span class="badge bg-success">Yes</span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();

// 6. Render with theme layout
render('dashboard', $content, [
    'pageTitle' => 'Example Module',
    'breadcrumbs' => [
        'Example Module',
        'Dashboard'
    ]
]);
