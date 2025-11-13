<?php
/**
 * Freight Management - Simplified Working Version
 */
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../infra/Freight/CredentialsProvider.php';
use Consignments\Infra\Freight\CredentialsProvider;
requireAuth();

// Determine user's active outlet (fallback to 1). Adjust to match CIS session model if different.
$activeOutletId = 1;
if (isset($_SESSION['user_outlet_id']) && is_numeric($_SESSION['user_outlet_id'])) {
    $activeOutletId = (int)$_SESSION['user_outlet_id'];
} elseif (isset($_SESSION['outlet_id']) && is_numeric($_SESSION['outlet_id'])) {
    $activeOutletId = (int)$_SESSION['outlet_id'];
}

// Prefetch credential status server-side for faster first paint
$pdo = isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO ? $GLOBALS['pdo'] : (function(){
    if (function_exists('cis_resolve_pdo')) { return cis_resolve_pdo(); }
    return null;
})();
$freightInit = null;
if ($pdo instanceof PDO) {
    try {
        $provider = new CredentialsProvider($pdo);
        $freightInit = $provider->getOutletStatus($activeOutletId);
    } catch (Throwable $e) {
        $freightInit = null;
    }
}

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
    <div class="card-body py-4">
        <div class="d-flex align-items-start justify-content-between flex-wrap gap-3">
            <div>
                <h3 class="mb-1"><i class="bi bi-truck-front-fill text-primary me-2"></i>Freight Configuration Status</h3>
                <div class="text-muted">Outlet <span class="badge bg-secondary" id="freight-outlet-id"></span></div>
            </div>
            <div>
                <button id="btn-refresh-freight" class="btn btn-sm btn-outline-primary"><i class="bi bi-arrow-clockwise me-1"></i>Refresh</button>
            </div>
        </div>
        <hr/>
                <div id="freight-status" class="row g-3"></div>
                <script>
                    window.FREIGHT_BOOT = window.FREIGHT_BOOT || {};
                    window.FREIGHT_BOOT.outlet_id = <?php echo (int)$activeOutletId; ?>;
                    <?php if ($freightInit): ?>
                    window.FREIGHT_BOOT.initial = <?php echo json_encode($freightInit, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE); ?>;
                    <?php endif; ?>
                </script>
                <script src="/modules/consignments/assets/js/freight-status.js" defer></script>
        <div class="mt-4 small text-muted">Notes: Credentials are validated server-side. Secrets are never exposed in the browser.</div>
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
