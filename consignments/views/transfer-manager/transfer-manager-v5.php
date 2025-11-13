<?php
/**
 * Transfer Manager - Bootstrap 5 Modern Theme Version
 *
 * Enterprise transfer management with Bootstrap 5 and modern theme.
 * Routes: /modules/consignments/?route=transfer-manager
 *
 * @package CIS\Consignments\TransferManager
 * @version 4.0.0 - Bootstrap 5
 * @created 2025-11-10
 */

declare(strict_types=1);

// Load Bootstrap
require_once __DIR__ . '/../bootstrap.php';

// Connect to database for outlets/suppliers
try {
    $db = CIS\Base\Database::pdo();
} catch (\Exception $e) {
    die('Database connection failed: ' . htmlspecialchars($e->getMessage()));
}

// Load outlets for dropdowns
$outlets = [];
$stmt = $db->query("SELECT id, name FROM vend_outlets WHERE deleted_at IS NULL ORDER BY name");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $outlets[$row['id']] = $row['name'];
}

// Load suppliers for PO transfers
$suppliers = [];
$stmt = $db->query("SELECT id, name FROM vend_suppliers WHERE deleted_at IS NULL ORDER BY name");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $suppliers[$row['id']] = $row['name'];
}

// Generate CSRF token for backend API calls
if (!isset($_SESSION['tt_csrf'])) {
    $_SESSION['tt_csrf'] = bin2hex(random_bytes(32));
}

// Check sync status from file
$syncEnabled = true;
$syncFile = __DIR__ . '/../TransferManager/.sync_enabled';
if (file_exists($syncFile)) {
    $syncEnabled = (trim(file_get_contents($syncFile)) === '1');
}

// ===== MODERN THEME SETUP =====
$pageTitle = 'Transfer Manager';
$breadcrumbs = [
    ['label' => 'Home', 'url' => '/', 'icon' => 'fa-home'],
    ['label' => 'Consignments', 'url' => '/modules/consignments/'],
    ['label' => 'Transfer Manager', 'active' => true]
];

$pageCSS = [
    'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css',
    '/modules/admin-ui/css/cms-design-system.css',
    '/modules/consignments/assets/css/tokens.css',
    '/modules/consignments/assets/css/transfer-manager-v2.css'
];

$pageJS = [];

$inlineScripts = '
// APP_CONFIG injection for backend API
window.APP_CONFIG = ' . json_encode([
    'CSRF' => $_SESSION['tt_csrf'],
    'LS_CONSIGNMENT_BASE' => '/modules/consignments/TransferManager/',
    'OUTLET_MAP' => $outlets,
    'SUPPLIER_MAP' => $suppliers,
    'SYNC_ENABLED' => $syncEnabled
], JSON_UNESCAPED_SLASHES) . ';
console.log("✅ APP_CONFIG injected:", window.APP_CONFIG);
';

// Start output buffering for content
ob_start();
?>

<!-- Page Header with Gradient -->
<div class="page-header fade-in mb-4">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
        <div>
            <h1 class="page-title mb-2">
                <i class="bi bi-arrow-left-right"></i> Transfer Manager
                <span class="badge bg-info ms-2">Ad-hoc Tool</span>
            </h1>
            <p class="page-subtitle text-muted mb-0">Create and manage consignments across all outlets. Press <kbd>/</kbd> to search.</p>
        </div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <!-- Lightspeed Sync Control -->
            <div class="card" style="background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%); border: 2px solid #e2e8f0;">
                <div class="card-body py-2 px-3">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-cloud" id="syncIcon"></i>
                        <span class="small fw-semibold">LS Sync</span>
                        <div class="form-check form-switch mb-0">
                            <input class="form-check-input" type="checkbox" id="syncToggle" <?= $syncEnabled ? 'checked' : '' ?>>
                        </div>
                    </div>
                </div>
            </div>
            <!-- New Transfer Button -->
            <button id="btnNew" class="btn btn-primary">
                <i class="bi bi-plus-circle"></i> New Transfer
            </button>
        </div>
    </div>
</div>

<!-- Filters Card -->
<div class="card shadow-sm fade-in mb-4" style="animation-delay: 0.1s">
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-3">
                <label class="form-label small fw-semibold">Transfer Type</label>
                <select id="filterType" class="form-select">
                    <option value="">All Types</option>
                    <option value="outlet">Outlet → Outlet</option>
                    <option value="supplier">Supplier → Outlet</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold">State</label>
                <select id="filterState" class="form-select">
                    <option value="">All States</option>
                    <option value="open">Open</option>
                    <option value="sent">Sent</option>
                    <option value="received">Received</option>
                    <option value="cancelled">Cancelled</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold">Outlet</label>
                <select id="filterOutlet" class="form-select">
                    <option value="">All Outlets</option>
                    <?php foreach ($outlets as $id => $name): ?>
                        <option value="<?= htmlspecialchars($id) ?>"><?= htmlspecialchars($name) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold">Smart Search</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" id="filterQ" class="form-control" placeholder="ID, name, or press /">
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Transfers Table -->
<div class="card shadow-sm fade-in" style="animation-delay: 0.2s">
    <div class="card-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
        <h5 class="mb-0"><i class="bi bi-table"></i> Transfers</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0" id="transfersTable">
                <thead style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                    <tr>
                        <th>ID</th>
                        <th>Type</th>
                        <th>From</th>
                        <th>To</th>
                        <th>State</th>
                        <th>Items</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="transfersTbody">
                    <tr>
                        <td colspan="8" class="text-center py-5">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <div class="mt-2 text-muted">LOADING TRANSFERS...</div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal: Quick View/Detail -->
<div class="modal fade" id="modalQuick" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                <h5 class="modal-title"><i class="bi bi-info-circle"></i> Transfer Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="qvBody">
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status"></div>
                    <div class="mt-2">Loading...</div>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Create Transfer -->
<div class="modal fade" id="modalCreate" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="bi bi-plus-circle"></i> Create New Transfer</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Transfer Type</label>
                    <select id="ct_type" class="form-select">
                        <option value="outlet">Outlet → Outlet</option>
                        <option value="supplier">Supplier → Outlet (PO)</option>
                    </select>
                </div>
                <div id="ct_supplier_wrap" style="display: none;" class="mb-3">
                    <label class="form-label fw-semibold">Supplier</label>
                    <select id="ct_supplier_select" class="form-select">
                        <option value="">Select Supplier...</option>
                        <?php foreach ($suppliers as $id => $name): ?>
                            <option value="<?= htmlspecialchars($id) ?>"><?= htmlspecialchars($name) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div id="ct_outlets_wrap" class="mb-3">
                    <label class="form-label fw-semibold">From Outlet</label>
                    <select id="ct_from_select" class="form-select">
                        <option value="">Select Source...</option>
                        <?php foreach ($outlets as $id => $name): ?>
                            <option value="<?= htmlspecialchars($id) ?>"><?= htmlspecialchars($name) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">To Outlet</label>
                    <select id="ct_to_select" class="form-select">
                        <option value="">Select Destination...</option>
                        <?php foreach ($outlets as $id => $name): ?>
                            <option value="<?= htmlspecialchars($id) ?>"><?= htmlspecialchars($name) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Name (optional)</label>
                    <input type="text" id="ct_name" class="form-control" placeholder="e.g., Weekly Restock">
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button id="btnCreateSubmit" class="btn btn-primary">
                    <i class="bi bi-check-circle"></i> Create Transfer
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Action (Send, Cancel, Delete, Revert) -->
<div class="modal fade" id="modalAction" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title" id="maTitle"><i class="bi bi-gear"></i> Action</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="maBody">
                <!-- Dynamic content -->
            </div>
            <div class="modal-footer bg-light">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button id="btnActionSubmit" class="btn btn-warning">Confirm</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Confirm -->
<div class="modal fade" id="modalConfirm" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="bi bi-exclamation-triangle"></i> Confirm Action</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="confirmBody">
                Are you sure?
            </div>
            <div class="modal-footer bg-light">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button id="btnConfirmYes" class="btn btn-danger">Confirm</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Receiving -->
<div class="modal fade" id="modalReceiving" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="bi bi-box-arrow-in-down"></i> Receive Transfer</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info">
                    <strong><i class="bi bi-info-circle"></i> Receiving Options:</strong>
                    <ul class="mb-0 mt-2">
                        <li><strong>"Item-by-Item"</strong> – Review and adjust quantities for each product before receiving.</li>
                        <li><strong>"Receive All"</strong> – Instantly receive all items at their sent quantities.</li>
                    </ul>
                </div>
                <div class="text-center">
                    <p class="fw-semibold mb-3">Choose how you want to proceed:</p>
                    <div class="d-flex gap-3 justify-content-center flex-wrap">
                        <button class="btn btn-outline-primary btn-lg" data-mode="item-by-item">
                            <i class="bi bi-list-check"></i> Item-by-Item
                        </button>
                        <button class="btn btn-success btn-lg" data-mode="receive-all">
                            <i class="bi bi-check-all"></i> Receive All
                        </button>
                    </div>
                </div>
                <hr class="my-4">
                <div class="small text-muted">
                    <strong>Recommendation:</strong><br>
                    Use <strong>"Item-by-Item"</strong> if you need to adjust quantities (damaged items, partial shipments, etc.).<br>
                    Use <strong>"Receive All"</strong> for complete shipments where all items arrived as expected.
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            </div>
        </div>
    </div>
</div>

<!-- Global Activity Overlay -->
<div id="globalActivityOverlay" class="global-activity-overlay" style="display: none;">
    <div class="d-flex flex-column align-items-center gap-3">
        <div class="spinner-border text-primary" style="width: 3rem; height: 3rem;" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
        <div>
            <div id="gaTitle" class="fw-semibold fs-5">Working...</div>
            <div id="gaSub" class="small text-muted">Please wait</div>
        </div>
    </div>
</div>

<!-- Toast Container -->
<div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 9999;" id="toastContainer"></div>

<!-- Modern JS Auto-Loading (Bootstrap 5 compatible) -->
<script src="/modules/consignments/assets/js/app-loader.js?v=<?= time() ?>"></script>

<?php
// Capture content
$content = ob_get_clean();

// Load the Modern Theme
require_once __DIR__ . '/../../base/templates/themes/modern/layouts/dashboard.php';
?>
