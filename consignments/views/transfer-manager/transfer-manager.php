<?php
/**
 * Transfer Manager - Modern CISClassicTheme Version
 *
 * Enterprise transfer management with purple gradient design system.
 * Routes: /modules/consignments/?route=transfer-manager
 *
 * @package CIS\Consignments\TransferManager
 * @version 3.0.0
 * @created 2025-11-10
 */

declare(strict_types=1);

// Load CIS Classic Theme (same pattern as stock-transfers.php)
require_once __DIR__ . '/../../base/templates/themes/cis-classic/theme.php';
require_once __DIR__ . '/../bootstrap.php';

// Connect to database for outlets/suppliers
try {
    $db = CIS\Base\Database::pdo();
} catch (\Exception $e) {
    die('Database connection failed: ' . htmlspecialchars($e->getMessage()));
}

// Load outlets for dropdowns
$outlets = [];
$stmt = $db->query("SELECT outletID, outletName FROM outlets WHERE status = 'active' ORDER BY outletName");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $outlets[$row['outletID']] = $row['outletName'];
}

// Load suppliers for PO transfers
$suppliers = [];
$stmt = $db->query("SELECT supplierID, supplierName FROM suppliers WHERE status = 'active' ORDER BY supplierName");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $suppliers[$row['supplierID']] = $row['supplierName'];
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

// Start output buffering for content
ob_start();
?>

<!-- Inject APP_CONFIG before JS loads -->
<script>
window.APP_CONFIG = {
    CSRF: <?= json_encode($_SESSION['tt_csrf']) ?>,
    LS_CONSIGNMENT_BASE: '/modules/consignments/TransferManager/',
    OUTLET_MAP: <?= json_encode($outlets, JSON_UNESCAPED_SLASHES) ?>,
    SUPPLIER_MAP: <?= json_encode($suppliers, JSON_UNESCAPED_SLASHES) ?>,
    SYNC_ENABLED: <?= json_encode($syncEnabled) ?>
};
console.log('✅ APP_CONFIG injected:', window.APP_CONFIG);
</script>

<!-- Page Header with Gradient -->
<div class="page-header fade-in">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
        <div>
            <h1 class="page-title">
                <i class="bi bi-arrow-left-right"></i> Transfer Manager
                <span class="badge badge-info ms-2">Ad-hoc Tool</span>
            </h1>
            <p class="page-subtitle">Create and manage consignments across all outlets. Press <kbd>/</kbd> to search.</p>
        </div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <!-- Lightspeed Sync Control -->
            <div class="card" style="background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%); border: 2px solid #e2e8f0;">
                <div class="card-body py-2 px-3">
                    <div class="d-flex align-items-center gap-3">
                        <div class="form-check form-switch mb-0">
                            <input class="form-check-input" type="checkbox" id="syncToggle" <?= $syncEnabled ? 'checked' : '' ?>>
                            <label class="form-check-label fw-semibold" for="syncToggle">
                                <i class="bi bi-cloud-arrow-up me-1"></i> Lightspeed Sync
                            </label>
                        </div>
                        <button id="btnVerifySync" class="btn btn-sm btn-success" title="Verify all Lightspeed table data">
                            <i class="bi bi-shield-check me-1"></i> Verify
                        </button>
                    </div>
                </div>
            </div>
            <!-- Action Buttons -->
            <button id="btnNew" class="btn btn-success">
                <i class="bi bi-plus-lg me-1"></i> New Transfer
            </button>
            <button id="btnRefresh" class="btn btn-primary">
                <i class="bi bi-arrow-repeat me-1"></i> Refresh
            </button>
            <button id="btnHardRefresh" class="btn btn-outline-primary" title="Hard refresh (bypass cache)">
                <i class="bi bi-arrow-clockwise me-1"></i> Hard Refresh
            </button>
        </div>
    </div>
</div>

<!-- Filters Card -->
<div class="card mb-4 fade-in">
    <div class="card-header">
        <i class="bi bi-funnel"></i> Filters
    </div>
    <div class="card-body">
        <div class="row g-3">
            <!-- Type Filter -->
            <div class="col-lg-2 col-md-4 col-sm-6">
                <label class="form-label fw-semibold">
                    <i class="bi bi-tag me-1"></i> Type
                </label>
                <select id="filterType" class="form-select">
                    <option value="">All Types</option>
                    <option>STOCK</option>
                    <option>JUICE</option>
                    <option>STAFF</option>
                    <option>RETURN</option>
                    <option>PURCHASE_ORDER</option>
                </select>
            </div>

            <!-- State Filter -->
            <div class="col-lg-2 col-md-4 col-sm-6">
                <label class="form-label fw-semibold">
                    <i class="bi bi-flag me-1"></i> State
                </label>
                <select id="filterState" class="form-select">
                    <option value="">All States</option>
                    <option>DRAFT</option>
                    <option>OPEN</option>
                    <option>PACKING</option>
                    <option>PACKAGED</option>
                    <option>SENT</option>
                    <option>RECEIVING</option>
                    <option>PARTIAL</option>
                    <option>RECEIVED</option>
                    <option>CANCELLED</option>
                    <option>CLOSED</option>
                </select>
            </div>

            <!-- Outlet Filter -->
            <div class="col-lg-3 col-md-4 col-sm-6">
                <label class="form-label fw-semibold">
                    <i class="bi bi-shop me-1"></i> Outlet
                </label>
                <select id="filterOutlet" class="form-select">
                    <option value="">All Outlets</option>
                    <?php foreach ($outlets as $id => $label): ?>
                        <option value="<?= htmlspecialchars($id, ENT_QUOTES) ?>"><?= htmlspecialchars($label, ENT_QUOTES) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Smart Search -->
            <div class="col-lg-5 col-md-12">
                <label class="form-label fw-semibold">
                    <i class="bi bi-search me-1"></i> Smart Search
                </label>
                <div class="input-group">
                    <span class="input-group-text bg-white">
                        <i class="bi bi-search text-primary"></i>
                    </span>
                    <input
                        id="filterQ"
                        type="text"
                        class="form-control"
                        placeholder="Transfer #, Vend #, outlet, supplier..."
                        title="Search across transfers, outlets, and suppliers">
                </div>
                <small class="text-muted d-block mt-1">
                    <i class="bi bi-lightbulb text-warning"></i> Press <kbd>/</kbd> for quick search
                </small>
            </div>
        </div>
    </div>
</div>

<!-- Transfers Table -->
<div class="table-container fade-in">
    <div class="table-header">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <i class="bi bi-list-ul me-2"></i>
                <span id="resultCount">Loading transfers...</span>
            </div>
            <div class="d-flex gap-2 align-items-center">
                <span class="text-white-50 d-none d-md-inline">Rows per page:</span>
                <select id="ddlPerPage" class="form-select form-select-sm" style="width: 80px;">
                    <option>10</option>
                    <option selected>25</option>
                    <option>50</option>
                    <option>100</option>
                </select>
                <button id="prevPage" class="btn btn-sm btn-outline-light">
                    <i class="bi bi-chevron-left"></i><span class="d-none d-md-inline"> Prev</span>
                </button>
                <button id="nextPage" class="btn btn-sm btn-outline-light">
                    <span class="d-none d-md-inline">Next </span><i class="bi bi-chevron-right"></i>
                </button>
            </div>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th><i class="bi bi-tag me-1"></i> Type</th>
                    <th><i class="bi bi-building me-1"></i> Supplier</th>
                    <th><i class="bi bi-geo-alt me-1"></i> Destination</th>
                    <th><i class="bi bi-activity me-1"></i> Progress</th>
                    <th><i class="bi bi-flag me-1"></i> State</th>
                    <th class="text-center"><i class="bi bi-box-seam me-1"></i> Boxes</th>
                    <th><i class="bi bi-clock me-1"></i> Updated</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody id="tblRows">
                <tr>
                    <td colspan="8" class="text-center text-muted py-5">
                        <div class="d-flex flex-column align-items-center gap-3">
                            <i class="bi bi-inbox fs-1 opacity-50"></i>
                            <div>
                                <div class="fw-semibold">No transfers found</div>
                                <small class="text-muted">Create a new transfer to get started</small>
                            </div>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Detail Modal -->
<div class="modal fade" id="modalQuick" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-box-seam me-2"></i> Transfer Details</h5>
                <button class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="qBody" class="d-grid gap-3"></div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Create Transfer Modal -->
<div class="modal fade" id="modalCreate" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i> Create Transfer</h5>
                <button class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formCreate" class="needs-validation" novalidate>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Type</label>
                        <select class="form-select" id="ct_type" required>
                            <option value="STOCK">STOCK</option>
                            <option value="JUICE">JUICE</option>
                            <option value="STAFF">STAFF</option>
                            <option value="RETURN">RETURN</option>
                            <option value="PURCHASE_ORDER">PURCHASE_ORDER</option>
                        </select>
                    </div>

                    <div class="mb-3" id="ct_supplier_wrap" style="display:none;">
                        <label class="form-label fw-semibold">Supplier</label>
                        <select class="form-select" id="ct_supplier_select" required>
                            <option value="">Choose supplier</option>
                            <?php foreach ($suppliers as $id => $name): ?>
                                <option value="<?= htmlspecialchars($id, ENT_QUOTES) ?>"><?= htmlspecialchars($name, ENT_QUOTES) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="invalid-feedback">Choose a supplier</div>
                        <small class="text-muted">Required for PURCHASE_ORDER</small>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">From (Outlet)</label>
                            <select class="form-select" id="ct_from_select" required>
                                <option value="">Choose outlet</option>
                                <?php foreach ($outlets as $id => $label): ?>
                                    <option value="<?= htmlspecialchars($id, ENT_QUOTES) ?>"><?= htmlspecialchars($label, ENT_QUOTES) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">Choose an outlet</div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">To (Outlet)</label>
                            <select class="form-select" id="ct_to_select" required>
                                <option value="">Choose outlet</option>
                                <?php foreach ($outlets as $id => $label): ?>
                                    <option value="<?= htmlspecialchars($id, ENT_QUOTES) ?>"><?= htmlspecialchars($label, ENT_QUOTES) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">Choose an outlet</div>
                        </div>
                    </div>

                    <div class="mt-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="ct_add_products" checked>
                            <label class="form-check-label" for="ct_add_products">
                                Add products immediately after creating
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancel</button>
                    <button class="btn btn-primary" type="submit">
                        <i class="bi bi-plus-lg me-1"></i> Create Transfer
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Action Modal (Generic) -->
<div class="modal fade" id="modalAction" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 id="maTitle" class="modal-title">Action</h5>
                <button class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div id="maBody" class="modal-body"></div>
            <div class="modal-footer">
                <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                <button class="btn btn-primary" id="maSubmit">Save</button>
            </div>
        </div>
    </div>
</div>

<!-- Confirm Modal -->
<div class="modal fade" id="modalConfirm" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 id="mcTitle" class="modal-title">Confirm Action</h5>
                <button class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div id="mcBody" class="modal-body">Are you sure you want to proceed?</div>
            <div class="modal-footer">
                <button class="btn btn-outline-secondary" data-bs-dismiss="modal">No, Cancel</button>
                <button class="btn btn-danger" id="mcYes">Yes, Confirm</button>
            </div>
        </div>
    </div>
</div>

<!-- Receiving Mode Selection Modal -->
<div class="modal fade" id="modalReceiving" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%); color: white;">
                <h5 id="receivingTitle" class="modal-title">
                    <i class="bi bi-box-arrow-in-down me-2"></i> Choose Receiving Method
                </h5>
                <button class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info mb-4">
                    <i class="bi bi-info-circle me-2"></i>
                    <strong>Transfer Summary:</strong>
                    <span id="receivingItemCount">0</span> items,
                    <span id="receivingTotalQty">0</span> total units
                </div>

                <div class="row g-4">
                    <!-- Option 1: Begin Receiving (Manual) -->
                    <div class="col-md-6">
                        <div class="card h-100 border-warning shadow-sm">
                            <div class="card-body d-flex flex-column">
                                <div class="mb-3">
                                    <div class="d-flex align-items-center mb-2">
                                        <i class="bi bi-pencil-square fs-2 text-warning me-2"></i>
                                        <h5 class="card-title mb-0">Begin Receiving</h5>
                                    </div>
                                    <p class="text-muted small mb-0">Manual entry mode</p>
                                </div>

                                <ul class="list-unstyled mb-4 flex-grow-1">
                                    <li class="mb-2"><i class="bi bi-check-circle text-success me-2"></i> Enter actual received quantities</li>
                                    <li class="mb-2"><i class="bi bi-check-circle text-success me-2"></i> Handle partial shipments</li>
                                    <li class="mb-2"><i class="bi bi-check-circle text-success me-2"></i> Verify each item individually</li>
                                    <li class="mb-2"><i class="bi bi-check-circle text-success me-2"></i> Complete when ready</li>
                                </ul>

                                <button class="btn btn-warning btn-lg w-100" id="btnBeginReceiving">
                                    <i class="bi bi-pencil-square me-2"></i> Begin Receiving
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Option 2: Receive All (Auto-Fill) -->
                    <div class="col-md-6">
                        <div class="card h-100 border-success shadow-sm">
                            <div class="card-body d-flex flex-column">
                                <div class="mb-3">
                                    <div class="d-flex align-items-center mb-2">
                                        <i class="bi bi-lightning-charge-fill fs-2 text-success me-2"></i>
                                        <h5 class="card-title mb-0">Receive All</h5>
                                    </div>
                                    <p class="text-muted small mb-0">Auto-complete instantly</p>
                                </div>

                                <ul class="list-unstyled mb-4 flex-grow-1">
                                    <li class="mb-2"><i class="bi bi-check-circle text-success me-2"></i> Auto-fill all quantities</li>
                                    <li class="mb-2"><i class="bi bi-check-circle text-success me-2"></i> Update inventory immediately</li>
                                    <li class="mb-2"><i class="bi bi-check-circle text-success me-2"></i> Complete transfer in one click</li>
                                    <li class="mb-2"><i class="bi bi-check-circle text-success me-2"></i> Sync to Lightspeed instantly</li>
                                </ul>

                                <button class="btn btn-success btn-lg w-100" id="btnReceiveAll">
                                    <i class="bi bi-lightning-charge-fill me-2"></i> Receive All Now
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="alert alert-light border mt-4 mb-0">
                    <div class="d-flex align-items-start">
                        <i class="bi bi-lightbulb text-primary me-2 mt-1"></i>
                        <div class="small">
                            <strong>Tip:</strong> Use <strong>"Begin Receiving"</strong> if you need to verify each item or handle partial deliveries.
                            Use <strong>"Receive All"</strong> for complete shipments where all items arrived as expected.
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
            </div>
        </div>
    </div>
</div>

<!-- Activity Overlay -->
<div id="globalActivity" aria-live="polite" aria-atomic="true" style="display:none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 9999; display: flex; align-items: center; justify-content: center;">
    <div class="ga-box" style="background: white; padding: 2rem; border-radius: 12px; box-shadow: 0 10px 40px rgba(0,0,0,0.3); display: flex; align-items: center; gap: 1rem;">
        <div class="spinner-border text-primary" role="status" aria-hidden="true"></div>
        <div>
            <div id="gaTitle" class="fw-semibold">Working...</div>
            <div id="gaSub" class="small text-muted">Please wait</div>
        </div>
    </div>
</div>

<!-- Toast Container -->
<div class="toast-container position-fixed top-0 end-0 p-3" id="toastContainer" aria-live="polite" aria-atomic="true"></div>

<!-- Modern JS Auto-Loading -->
<script src="/modules/consignments/assets/js/app-loader.js?v=<?= time() ?>"></script>

<?php
// Get buffered content and render with BASE template
$content = ob_get_clean();

// Render using BASE framework
render('base', $content, [
    'pageTitle' => 'Transfer Manager',
    'pageSubtitle' => 'Ad-hoc consignment management tool',
    'breadcrumbs' => [
        ['title' => 'Consignments', 'url' => '/modules/consignments/'],
        ['title' => 'Transfer Manager', 'url' => null]
    ],
    'styles' => [
        '/modules/admin-ui/css/cms-design-system.css',
        'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css',
        '/modules/consignments/assets/css/tokens.css?v=' . time(),
        '/modules/consignments/assets/css/transfer-manager-v2.css?v=' . time()
    ]
]);
?>
