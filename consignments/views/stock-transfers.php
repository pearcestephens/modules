<?php
/**
 * Stock Transfers - Modern List View (Bootstrap 5)
 *
 * Enterprise-grade transfer management with live AJAX data and modal interactions.
 * Uses Modern Theme for Bootstrap 5 UI/UX.
 *
 * @package CIS\Consignments\StockTransfers
 * @version 5.0.0 - Bootstrap 5
 * @updated 2025-11-10
 */

declare(strict_types=1);

// Load bootstrap
require_once __DIR__ . '/../bootstrap.php';

// Get filter state from URL
$state = isset($_GET['state']) ? (string)$_GET['state'] : '';
$scope = isset($_GET['scope']) ? (string)$_GET['scope'] : '';
$uid = $_SESSION['user_id'] ?? null;

// ===== MODERN THEME SETUP (Bootstrap 5) =====
$pageTitle = 'Stock Transfers';
$breadcrumbs = [
    ['label' => 'Home', 'url' => '/', 'icon' => 'fa-home'],
    ['label' => 'Consignments', 'url' => '/modules/consignments/'],
    ['label' => 'Stock Transfers', 'active' => true]
];

$pageCSS = [
    'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css',
    '/modules/admin-ui/css/cms-design-system.css',
    '/modules/consignments/assets/css/tokens.css',
    '/modules/consignments/stock-transfers/css/stock-transfers.css'
];

$pageJS = [
    '/modules/consignments/stock-transfers/js/stock-transfers.js'
];

// Start output buffering
ob_start();
?>

<!-- Page Header with Gradient -->
<div class="page-header fade-in mb-4">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
        <div>
            <h1 class="page-title mb-2">
                <i class="bi bi-box-seam"></i> Stock Transfers
            </h1>
            <p class="page-subtitle text-muted mb-0">
                Manage inter-outlet inventory transfers and track shipments
            </p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <button class="btn btn-outline-primary" id="btnRefresh">
                <i class="bi bi-arrow-clockwise"></i> Refresh
            </button>
            <a href="/modules/consignments/?route=transfer-manager" class="btn btn-success">
                <i class="bi bi-plus-circle"></i> New Transfer
            </a>
        </div>
    </div>
</div>

<!-- Hidden data for JS -->
<div id="pageData"
     data-state="<?= htmlspecialchars($state) ?>"
     data-scope="<?= htmlspecialchars($scope) ?>"
     data-user-id="<?= $uid ?>"
     style="display:none;"></div>

<!-- Filters Card -->
<div class="card shadow-sm mb-4 fade-in" style="animation-delay: 0.1s">
    <div class="card-body">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-3">
            <h5 class="mb-0"><i class="bi bi-funnel"></i> Filters</h5>
            <button class="btn btn-sm btn-outline-secondary" id="btnClearFilters">
                <i class="bi bi-x-circle"></i> Clear All
            </button>
        </div>
        <div class="filter-pills" id="filterPills">
            <!-- JS will populate -->
            <div class="text-center py-2">
                <div class="spinner-border spinner-border-sm text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <span class="ms-2 text-muted">Loading filters...</span>
            </div>
        </div>
    </div>
</div>

<!-- Transfers Table Card -->
<div class="card shadow-sm fade-in" style="animation-delay: 0.2s">
    <div class="card-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
        <div class="d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="bi bi-table"></i> Stock Transfers</h5>
            <span class="badge bg-white text-primary" id="totalCount">0</span>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0" id="transfersTable">
                <thead class="bg-light">
                    <tr>
                        <th class="border-0">Consignment</th>
                        <th class="border-0">From → To</th>
                        <th class="border-0">Status</th>
                        <th class="border-0">Items</th>
                        <th class="border-0">Progress</th>
                        <th class="border-0">Updated</th>
                        <th class="border-0 text-end">Actions</th>
                    </tr>
                </thead>
                <tbody id="transfersBody">
                    <!-- JS will populate -->
                    <tr>
                        <td colspan="7" class="text-center py-5">
                            <div class="spinner-border text-primary mb-3" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <div class="text-muted">Loading transfers...</div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Transfer Detail Modal (Bootstrap 5) -->
<div class="modal fade" id="transferModal" tabindex="-1" aria-labelledby="modalTitle">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                <h5 class="modal-title" id="modalTitle">
                    <i class="bi bi-info-circle"></i> Transfer Details
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="modalBody">
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <div class="mt-2 text-muted">Loading details...</div>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<style>
/* Additional animations and polish */
.fade-in {
    animation: fadeInUp 0.6s ease-out;
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.filter-pills {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.filter-pill {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    background: #f3f4f6;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s ease;
}

.filter-pill:hover {
    background: #e5e7eb;
    transform: translateY(-1px);
}

.filter-pill.active {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
}

.table-hover tbody tr {
    cursor: pointer;
    transition: all 0.2s ease;
}

.table-hover tbody tr:hover {
    background: #f9fafb;
    transform: translateX(2px);
}
</style>

<?php
// Capture content
$content = ob_get_clean();

// Load the Modern Theme (Bootstrap 5)
require_once __DIR__ . '/../../base/templates/themes/modern/layouts/dashboard.php';
?>
