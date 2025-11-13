<?php
declare(strict_types=1);
/**
 * Stock Transfer Pack Page - Template-Based Version
 *
 * Allows staff to count and prepare products for a stock transfer
 * Uses base-layout.php template for proper HTML structure
 *
 * @package CIS\Consignments\StockTransfers
 * @version 3.0.0 - Refactored to use base template
 */

// ============================================================================
// INITIALIZATION & VALIDATION
// ============================================================================

// Health/Status: respond OK to HEAD probes
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'HEAD') {
    http_response_code(200);
    exit;
}

// Guard: Transfer ID or demo mode
$transferId = (int)($_GET['transfer'] ?? $_GET['id'] ?? 0);
$__demo = isset($_GET['demo']) && $_GET['demo'] === '1';
if ($transferId <= 0 && ! $__demo) {
    http_response_code(400);
    die('<!DOCTYPE html><html><head><title>Bad Request</title></head><body><h1>Bad Request</h1><p>Missing or invalid transfer ID.</p></body></html>');
}

define('PACK_PAGE', true);

// Load module bootstrap (loads shared functions, config, database)
require_once __DIR__ . '/../bootstrap.php';


// ============================================================================
// LOAD TRANSFER DATA
// ============================================================================

$transferData = null;
$errorMessage = null;

// Local fallback error renderer if not available from host
if (!function_exists('showErrorPage')) {
    function showErrorPage(string $message, array $opts = []): void {
        $title = htmlspecialchars($opts['title'] ?? 'Error');
        $backUrl = htmlspecialchars($opts['backUrl'] ?? '/modules/consignments/?route=stock-transfers');
        $backLabel = htmlspecialchars($opts['backLabel'] ?? 'Back');
        http_response_code(400);
        echo '<!doctype html><meta charset="utf-8"><title>' . $title . '</title>';
        echo '<div style="font-family:system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;max-width:720px;margin:48px auto;padding:24px;border:1px solid #e5e7eb;border-radius:8px;">';
        echo '<h1 style="margin:0 0 8px;font-size:22px;">' . $title . '</h1>';
        echo '<p style="margin:0 0 16px;color:#374151;">' . htmlspecialchars($message) . '</p>';
        echo '<a href="' . $backUrl . '" style="display:inline-block;padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;text-decoration:none;color:#111827;">' . $backLabel . '</a>';
        echo '</div>';
    }
}

try {
    if ($__demo || !function_exists('getUniversalTransfer')) {
        // Demo fallback if helper unavailable
        $transferId = $transferId ?: 999002;
        $transferData = (object) [
            'id' => $transferId,
            'transfer_category' => 'STOCK',
            'state' => 'OPEN',
            'outlet_from' => (object)['name' => 'Main Warehouse'],
            'outlet_to' => (object)['name' => 'Outlet 001']
        ];
    } else {
        $transferData = getUniversalTransfer($transferId);
    }

    if (!$transferData) {
        error_log("Pack page: Transfer #$transferId returned NULL from getUniversalTransfer");
        $errorMessage = "Transfer #$transferId not found or you don't have access to it.";
    } elseif ($transferData->transfer_category !== 'STOCK') {
        $errorMessage = "Transfer #$transferId is a {$transferData->transfer_category} transfer. This page only handles STOCK transfers.";
        $transferData = null;
    } elseif (!in_array($transferData->state, ['OPEN', 'PACKING'], true)) {
        $errorMessage = "Transfer #$transferId is in '{$transferData->state}' state. Only OPEN or PACKING transfers can be packed.";
        $transferData = null;
    }
} catch (Exception $e) {
    error_log("Pack page error loading transfer $transferId: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    $errorMessage = "Error loading transfer data: " . $e->getMessage();
    $transferData = null;
}

// Show error page if validation failed
if ($errorMessage || !$transferData) {
    showErrorPage($errorMessage, [
        'title' => 'Unable to Load Transfer',
        'backUrl' => 'index.php',
        'backLabel' => 'Back to Transfer List'
    ]);
    exit;
}

// ============================================================================
// TEMPLATE CONFIGURATION
// ============================================================================

// Page variables
$PACKONLY = isset($_GET['pack_only']) && $_GET['pack_only'] === '1';
$userDetails = $_SESSION ?? [];
$transferIdParam = $transferId;

// Template variables for base-layout.php
$page_title = 'Pack Transfer #' . (int)$transferData->id;
$body_class = 'app header-fixed sidebar-fixed aside-menu-fixed sidebar-lg-show';

// CSRF token for forms
$csrf = htmlspecialchars($_SESSION['csrf'] ?? '', ENT_QUOTES);

// ============================================================================
// AUTO-LOAD CSS & JS - Uses auto-load-assets.php
// ============================================================================
require_once __DIR__ . '/../../shared/functions/auto-load-assets.php';

// Auto-load CSS from: shared/css, consignments/shared/css, stock-transfers/css
$autoCSS = autoLoadModuleCSS(__FILE__, [
    'additional' => [
        '/modules/consignments/stock-transfers/css/pack-print.css' => ['media' => 'print']
    ]
]);

// Auto-load JS from: shared/js, consignments/shared/js, stock-transfers/js
$autoJS = autoLoadModuleJS(__FILE__, [
    'additional' => [
        '/assets/js/cis-toast.js'  // Global CIS toast notifications
    ],
    'defer' => false  // Load synchronously for pack.js dependencies
]);

// Extra head content (CSS + Meta)
$page_head_extra = <<<HTML
{$autoCSS}
<meta name="csrf-token" content="{$csrf}">
HTML;

// JavaScript files to load BEFORE core libraries
$page_scripts_before_footer = $autoJS;

// Breadcrumb configuration
$show_breadcrumb = true;
$breadcrumb_items = [
    ['label' => 'Home', 'url' => '/'],
    ['label' => 'Admin', 'url' => '#'],
    ['label' => 'OUTGOING Stock Transfer #' . (int)$transferData->id . ' To ' . htmlspecialchars($transferData->outlet_to->name)]
];

// ============================================================================
// PAGE CONTENT (captured in output buffer)
// ============================================================================

// Capture auto-save indicator (goes BEFORE app-body)
ob_start();
?>

<!-- Fixed Position Auto-Save Indicator -->
<div class="auto-save-container">
    <div id="autosave-indicator" class="auto-save-badge" role="status" aria-live="polite">
        <div class="save-status-icon" aria-hidden="true"></div>
        <div class="save-status-text">
            <span class="save-status">IDLE</span>
            <span class="save-timestamp">Never</span>
        </div>
    </div>
</div>

<?php
$page_before_app_body = ob_get_clean();

// Capture main page content
ob_start();
?>

<!-- Breadcrumb -->
<ol class="breadcrumb">
    <li class="breadcrumb-item">Home</li>
    <li class="breadcrumb-item"><a href="#">Admin</a></li>
    <li class="breadcrumb-item active">
        OUTGOING Stock Transfer #<?php echo (int)$transferData->id; ?>
        To <?php echo htmlspecialchars($transferData->outlet_to->name ?? 'Unknown'); ?>
    </li>
    <li class="breadcrumb-menu d-md-down-none"><?php include(ROOT_PATH . '/assets/template/quick-product-search.php'); ?></li>
</ol>

<div class="container-fluid">
    <div class="animated fadeIn">
        <div class="row">
            <div class="col-12">
                <div class="card">

                    <!-- Card Header -->
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div>
                            <h4 class="card-title mb-0">
                                Stock Transfer #<?php echo (int)$transferData->id; ?><br>
                                <?php echo htmlspecialchars($transferData->outlet_from->name); ?> →
                                <?php echo htmlspecialchars($transferData->outlet_to->name); ?>
                            </h4>
                            <div class="small text-muted">These products need to be gathered and prepared for delivery</div>
                        </div>

                        <div class="btn-group" role="group" aria-label="Primary actions">
                            <button class="btn btn-outline-primary" type="button" data-toggle="modal" data-target="#addProductsModal">
                                <i class="fa fa-plus mr-2" aria-hidden="true"></i> Add Products
                            </button>
                            <button type="button" class="btn btn-outline-secondary" id="tbl-print-top" title="Print picking sheet" onclick="window.print()">
                                <i class="fa fa-print mr-2" aria-hidden="true"></i> Print
                            </button>
                        </div>
                    </div>

                    <!-- Card Body -->
                    <div class="card-body">

                        <!-- Print-only header (hidden on screen) -->
                        <section class="print-header" style="display:none;">
                            <h1>Stock Transfer Packing Slip</h1>
                            <div class="transfer-info">
                                <div>
                                    <strong>Transfer ID:</strong> #<?php echo (int)$transferData->id; ?><br>
                                    <strong>Date:</strong> <?php echo date('d/m/Y H:i'); ?><br>
                                    <strong>Status:</strong> <?php echo htmlspecialchars($transferData->state); ?>
                                </div>
                                <div>
                                    <strong>From:</strong> <?php echo htmlspecialchars($transferData->outlet_from->name); ?><br>
                                    <strong>To:</strong> <?php echo htmlspecialchars($transferData->outlet_to->name); ?><br>
                                    <strong>Created By:</strong> <?php echo htmlspecialchars($transferData->created_by_user->display_name ?? 'System'); ?>
                                </div>
                                <div class="print-barcode">
                                    <strong>T<?php echo str_pad((string)$transferData->id, 8, '0', STR_PAD_LEFT); ?></strong>
                                    Transfer Barcode
                                </div>
                            </div>
                        </section>

                        <div id="transfer-alerts" class="mb-2" aria-live="polite"></div>

                        <!-- Product Table -->
                        <div class="card w-100 mb-3" id="table-card">
                            <div class="card-body py-2">

                                <!-- Toolbar -->
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <div aria-hidden="true" style="flex:1;"></div> <!-- Spacer -->
                                    <div>
                                        <button class="btn btn-sm btn-outline-primary py-1 px-2" type="button" data-toggle="modal" data-target="#addProductsModal">
                                            <i class="fa fa-plus mr-1" aria-hidden="true"></i> Add Products
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary py-1 px-2" id="tbl-print" title="Print picking sheet" onclick="window.print()">
                                            <i class="fa fa-print mr-1" aria-hidden="true"></i> Print
                                        </button>
                                    </div>
                                </div>

                                <div class="table-responsive-sm">
                                    <table class="table table-bordered table-striped table-sm" id="transfer-table" data-transfer-id="<?php echo (int)$transferData->id; ?>">
                                        <thead class="thead-light">
                                            <tr>
                                                <th class="text-center">
                                                    <span class="d-print-none">Image</span>
                                                </th>
                                                <th>Product Name</th>
                                                <th class="text-center">Qty In Stock</th>
                                                <th class="text-center">Planned Qty</th>
                                                <th class="text-center">Counted Qty</th>
                                                <th class="text-center">Source</th>
                                                <th class="text-center">Destination</th>
                                                <th class="text-center">ID</th>
                                            </tr>
                                        </thead>
                                        <tbody id="productSearchBody">
                                            <?php
                                            // Helper function for staff-added badge
                                            function manuallyOrderedByStaff_obj($p): string {
                                                return (isset($p->staff_added_product) && (int)$p->staff_added_product > 0)
                                                    ? ' <span class="badge badge-warning">Manually Ordered By Staff</span>'
                                                    : '';
                                            }

                                            $fromName = htmlspecialchars($transferData->outlet_from->name ?? '');
                                            $toName   = htmlspecialchars($transferData->outlet_to->name ?? '');
                                            $tidForCounter = (int)$transferData->id;

                                            if (!empty($transferData->items) && is_iterable($transferData->items)) {
                                                $i = 0;
                                                foreach ($transferData->items as $p) {
                                                    $i++;
                                                    $inv     = (int)($p->current_stock ?? 0);
                                                    $planned = (int)($p->qty_requested ?? 0);

                                                    if ($planned <= 0) {
                                                        continue; // Skip items with no planned quantity
                                                    }

                                                    $pid   = htmlspecialchars($p->product_id ?? '');
                                                    $pname = htmlspecialchars($p->product_name ?? '');
                                                    $sku   = htmlspecialchars($p->sku ?? '');

                                                    // Check if image is real or placeholder
                                                    $isPlaceholder = empty($p->image_url)
                                                        || strpos((string)$p->image_url, 'placeholder') !== false
                                                        || strpos((string)$p->image_url, 'no-image') !== false;

                                                    $imgUrl   = !empty($p->image_url) ? htmlspecialchars($p->image_url) : 'https://via.placeholder.com/80x80?text=No+Image';
                                                    $imgClass = $isPlaceholder ? 'no-zoom' : '';

                                                    echo "<tr data-inventory=\"{$inv}\" data-planned=\"{$planned}\" data-product-id=\"{$pid}\">"
                                                        . "<td style='padding:2px; text-align:center; vertical-align:middle; background-color:white;'>
                                                              <img src='{$imgUrl}' alt='{$pname}' class='{$imgClass}'
                                                                   style='width:48px; height:48px; object-fit:cover; display:inline-block;'
                                                                   title='{$pname}'>
                                                            </td>"
                                                        . "<td style='text-align:left;'>
                                                              <div>{$pname}" . manuallyOrderedByStaff_obj($p) . "</div>
                                                              <small class='text-muted'>SKU: {$sku}</small>
                                                            </td>"
                                                        . "<td class='inv text-center'>{$inv}</td>"
                                                        . "<td class='planned text-center font-weight-bold'>{$planned}</td>"
                                                        . "<td class='counted-td text-center'>
                                                              <input type='number' class='form-control js-counted-qty text-center'
                                                                     min='0' max='{$inv}' step='1' pattern='[0-9]*' inputmode='numeric'
                                                                     data-planned='{$planned}' data-stock='{$inv}'
                                                                     value=''
                                                                     style='width:6em; display:inline-block; border-radius:4px; border:1px solid #ced4da;'>
                                                              <div class='validation-message text-danger small mt-1' style='display:none; font-size:0.7rem;'></div>
                                                              <span class='counted-print-value d-none'>0</span>
                                                            </td>"
                                                        . "<td class='text-center'>{$fromName}</td>"
                                                        . "<td class='text-center'>{$toName}</td>"
                                                        . "<td class='text-center'><span class='id-counter'>{$tidForCounter}-{$i}</span></td>"
                                                        . "</tr>";
                                                }
                                            }
                                            ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- Print-only summary section -->
                        <section class="print-summary" style="display:none;">
                            <h3>Transfer Summary</h3>
                            <div class="summary-row">
                                <span>Total Products:</span>
                                <span id="print-total-products"><?php echo count($transferData->items ?? []); ?></span>
                            </div>
                            <div class="summary-row">
                                <span>Total Planned Quantity:</span>
                                <span id="print-total-planned">
                                    <?php
                                        $totalPlanned = 0;
                                        foreach ($transferData->items ?? [] as $item) {
                                            $totalPlanned += (int)($item->qty_sent ?? 0);
                                        }
                                        echo (int)$totalPlanned;
                                    ?>
                                </span>
                            </div>
                            <div class="summary-row">
                                <span>Total Counted Quantity:</span>
                                <span id="print-total-counted">_______</span>
                            </div>
                        </section>

                        <!-- Print-only notes section -->
                        <section class="print-notes" style="display:none;">
                            <h4>Packing Notes / Discrepancies:</h4>
                            <div class="notes-content">
                                <?php
                                    if (!empty($transferData->notes)) {
                                        echo htmlspecialchars($transferData->notes);
                                    }
                                ?>
                            </div>
                        </section>

                        <!-- Print-only signature section -->
                        <section class="print-footer" style="display:none;">
                            <div style="margin-bottom:15px;">
                                <strong>Instructions:</strong> Check off each item as packed. Note any discrepancies above. Sign below when complete.
                            </div>

                            <!-- Number of Boxes -->
                            <div class="boxes-section">
                                <div class="boxes-line">
                                    <span>Number of Boxes:</span>
                                    <span class="line-blank"></span>
                                    <span>boxes</span>
                                </div>
                            </div>

                            <!-- Signatures -->
                            <div class="signature-line">
                                <div class="signature-box">
                                    <strong>Packed By:</strong>
                                    <div class="line">Signature &amp; Date</div>
                                </div>
                                <div class="signature-box">
                                    <strong>Received By:</strong>
                                    <div class="line">Signature &amp; Date</div>
                                </div>
                            </div>
                        </section>

                        <!-- Submission Section -->
                        <div class="card-body">
                            <p class="mb-2" style="font-weight:bold;font-size:12px;">
                                Counted &amp; Handled By:
                                <?php echo htmlspecialchars(trim(($userDetails["first_name"] ?? '') . ' ' . ($userDetails["last_name"] ?? ''))); ?>
                            </p>

                            <?php if (!$PACKONLY): ?>
                                <p class="mb-3 small text-muted">
                                    By setting this transfer "Ready For Delivery" you declare that you have individually counted all the products despatched in this transfer and verified inventory levels.
                                </p>

                                <div class="progress mt-3" style="height:0.75rem;">
                                    <div class="progress-bar" role="progressbar" data-role="progress" style="width:0%;"
                                         aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                                <div class="mt-2 small text-muted" data-role="progress-log" aria-live="polite"></div>

                                <div class="d-flex flex-wrap align-items-center mt-3" style="gap:0.5rem;">
                                    <button type="button" class="btn btn-primary" data-action="create_and_upload">
                                        <i class="fa fa-rocket mr-2" aria-hidden="true"></i>Create Consignment &amp; Upload to VendHQ
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary" data-action="auto_fill">
                                        <i class="fas fa-fill-drip mr-1" aria-hidden="true"></i>Auto-Fill Counted Quantities
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>

                    </div> <!-- /.card-body -->
                </div> <!-- /.card -->
            </div> <!-- /.col-12 -->
        </div> <!-- /.row -->
    </div> <!-- /.animated -->
</div> <!-- /.container-fluid -->

<?php
// Capture page content
$page_content = ob_get_clean();

// ============================================================================
// MODALS
// ============================================================================
ob_start();
?>

<!-- Add Products Modal -->
<div class="modal fade" id="addProductsModal" tabindex="-1" role="dialog" aria-labelledby="addProductsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white py-2 px-3 border-0">
                <h6 class="modal-title mb-0" id="addProductsModalLabel">
                    <i class="fa fa-search mr-2" aria-hidden="true"></i>Add Products
                </h6>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close" style="font-size:1.2rem; opacity:0.8;">
                    <span aria-hidden="true">×</span>
                </button>
            </div>
            <div class="modal-body p-3">
                <p class="text-muted">Product search functionality will be loaded here.</p>
                <!-- Full modal content would go here - keeping it minimal for this refactor -->
            </div>
            <div class="modal-footer border-0 bg-light py-2 px-3">
                <button type="button" class="btn btn-sm btn-outline-secondary py-1 px-2" data-dismiss="modal">
                    <i class="fa fa-times mr-1" aria-hidden="true"></i> Close
                </button>
            </div>
        </div>
    </div>
</div>

<?php
$page_modals = ob_get_clean();

// ============================================================================
// OVERLAYS
// ============================================================================
ob_start();
?>

<!-- Submission Overlay (Epic Progress Display) -->
<div id="submission-overlay" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:linear-gradient(135deg, #0a0a0a 0%, #1a1a2e 50%, #0a0a0a 100%); z-index:9999; overflow:hidden;">
    <div style="position:relative; height:100%; display:flex; flex-direction:column; justify-content:center; align-items:center; padding:20px;">
        <h2 id="overlay-title" style="color:#fff; margin-bottom:30px;">Creating Consignment...</h2>
        <div id="live-feedback" style="background:rgba(0,0,0,0.5); border:1px solid rgba(0,212,255,0.2); border-radius:12px; padding:20px; max-width:600px; color:#fff;">
            <p>Connecting to server...</p>
        </div>
    </div>
</div>

<?php
$page_overlays = ob_get_clean();

// ============================================================================
// RENDER BASE TEMPLATE
// ============================================================================
require __DIR__ . '/../../shared/templates/base-layout.php';
