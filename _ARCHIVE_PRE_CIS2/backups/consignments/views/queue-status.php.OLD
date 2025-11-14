<?php
/**
 * Queue Status - Monitoring View (Simplified)
 */
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

$pageTitle = 'Queue Status';
$breadcrumbs = [
    ['label' => 'Home', 'url' => '/', 'icon' => 'bi-house-door'],
    ['label' => 'Consignments', 'url' => '/modules/consignments/', 'icon' => 'bi-box-seam'],
    ['label' => 'Queue Status', 'url' => '/modules/consignments/?route=queue-status', 'active' => true]
];

$pageCSS = [
    'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css',
    '/modules/admin-ui/css/cms-design-system.css',
    '/modules/shared/css/tokens.css'
];

$pageJS = [];
ob_start();
?>

<div class="page-header fade-in mb-4">
    <h1 class="page-title mb-2"><i class="bi bi-clock-history me-2"></i>Queue Status</h1>
    <p class="page-subtitle text-muted mb-0">Background job monitoring and worker stats</p>
</div>

<div class="row g-4">
    <div class="col-md-3">
        <div class="card gradient-card-purple shadow-sm h-100">
            <div class="card-body text-white">
                <h3 class="mb-3"><i class="bi bi-check-circle me-2"></i>Queue System</h3>
                <div class="display-4 mb-2">✓</div>
                <p class="mb-0">Running smoothly</p>
            </div>
        </div>
    </div>
    
    <div class="col-md-9">
        <div class="card shadow-sm">
            <div class="card-body text-center py-5">
                <i class="bi bi-gear display-1 text-muted mb-3"></i>
                <h3>Queue Monitoring Coming Soon</h3>
                <p class="text-muted">Advanced job queue monitoring and worker management will be available here.</p>
            </div>
        </div>
    </div>
</div>

<style>
.gradient-card-purple { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none; }
.fade-in { animation: fadeInUp 0.6s ease-out; }
@keyframes fadeInUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
</style>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../../base/templates/themes/modern/layouts/dashboard.php';
?>
