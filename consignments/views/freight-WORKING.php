<?php
/**
 * Freight Management - Simplified Working Version
 */
declare(strict_types=1);

$pageTitle = 'Freight Management';
$breadcrumbs = [
    ['label' => 'Home', 'url' => '/', 'icon' => 'bi-house-door'],
    ['label' => 'Consignments', 'url' => '/modules/consignments/', 'icon' => 'bi-box-seam'],
    ['label' => 'Freight Management', 'url' => '/modules/consignments/?route=freight', 'active' => true]
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
    <h1 class="page-title mb-2"><i class="bi bi-truck me-2"></i>Freight Management</h1>
    <p class="page-subtitle text-muted mb-0">Carrier management and shipping rates</p>
</div>

<div class="row g-4 mb-4">
    <div class="col-md-4">
        <div class="card gradient-card-purple shadow-sm h-100">
            <div class="card-body text-white">
                <h5 class="mb-3"><i class="bi bi-geo-alt me-2"></i>Carrier Integration</h5>
                <div class="display-4 mb-2">🚚</div>
                <p class="mb-0">Ready to connect carriers</p>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card gradient-card-success shadow-sm h-100">
            <div class="card-body text-white">
                <h5 class="mb-3"><i class="bi bi-currency-dollar me-2"></i>Rate Calculator</h5>
                <div class="display-4 mb-2">💰</div>
                <p class="mb-0">Compare shipping rates</p>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card gradient-card-blue shadow-sm h-100">
            <div class="card-body text-white">
                <h5 class="mb-3"><i class="bi bi-pin-map me-2"></i>Track Shipments</h5>
                <div class="display-4 mb-2">📦</div>
                <p class="mb-0">Real-time tracking</p>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body text-center py-5">
        <i class="bi bi-truck-front-fill display-1 text-primary mb-3"></i>
        <h3>Freight Management Coming Soon</h3>
        <p class="text-muted mb-4">Integrated carrier management, rate comparison, and shipment tracking will be available here.</p>
        <div class="d-flex gap-2 justify-content-center">
            <button class="btn btn-outline-primary" disabled>
                <i class="bi bi-plus-circle me-2"></i>Add Carrier
            </button>
            <button class="btn btn-outline-success" disabled>
                <i class="bi bi-calculator me-2"></i>Calculate Rate
            </button>
            <button class="btn btn-outline-info" disabled>
                <i class="bi bi-search me-2"></i>Track Package
            </button>
        </div>
    </div>
</div>

<style>
.gradient-card-purple { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none; }
.gradient-card-success { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); border: none; }
.gradient-card-blue { background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); border: none; }
.fade-in { animation: fadeInUp 0.6s ease-out; }
@keyframes fadeInUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
</style>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../../base/templates/themes/modern/layouts/dashboard.php';
?>
