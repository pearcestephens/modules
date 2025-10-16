<?php
declare(strict_types=1);
/**
 * Stock Transfer Pack Page
 * 
 * Allows staff to count and prepare products for a stock transfer
 * 
 * @package CIS\Consignments\StockTransfers
 * @version 2.0.0
 */

// ---- pack.php guard & param normalization ----
$transferId = (int)($_GET['transfer'] ?? $_GET['id'] ?? 0);
if ($transferId <= 0) {
  http_response_code(400);
  echo '<h1>Bad Request</h1><p>Missing or invalid transfer id.</p>';
  exit;
}

define('PACK_PAGE', true);

require_once __DIR__ . '/../bootstrap.php';

// Allow only this IP; block everyone else.
if (($_SERVER['REMOTE_ADDR'] ?? '') !== '125.236.217.224') {
  http_response_code(403);
  header('Content-Type: text/plain; charset=utf-8');
  exit("Come back shorltly.");
}

$transferType = 'STOCK';
$errorMessage = null;

// Get transfer data using universal function
$transferData = null;
try {
  // Get complete transfer data - uses optimized defaults (items + notes only)
  $transferData = getUniversalTransfer($transferId);

  if (!$transferData) {
    error_log("Pack page: Transfer #$transferId returned NULL from getUniversalTransfer");
    $errorMessage = "Transfer #$transferId not found or you don't have access to it.";
  } elseif ($transferData->transfer_category !== 'STOCK') {
    // Validate this is a STOCK transfer
    $errorMessage = "Transfer #$transferId is a {$transferData->transfer_category} transfer. This page only handles STOCK transfers.";
    $transferData = null;
  } elseif (!in_array($transferData->state, ['OPEN', 'PACKING'], true)) {
    // Validate transfer is in correct state for packing
    $errorMessage = "Transfer #$transferId is in '{$transferData->state}' state. Only OPEN or PACKING transfers can be packed.";
    $transferData = null;
  }
} catch (Exception $e) {
  error_log("Pack page error loading transfer $transferId: " . $e->getMessage());
  error_log("Stack trace: " . $e->getTraceAsString());
  $errorMessage = "Error loading transfer data: " . $e->getMessage();
  $transferData = null;
}

// Set variables that might be used in existing code
$transferIdParam = $transferId;
$mergeTransfers = []; // Initialize as empty for now
$PACKONLY = isset($_GET['pack_only']) && $_GET['pack_only'] === '1';
$userDetails = $_SESSION ?? [];

// If we have an error, display it using CIS error page helper
if ($errorMessage || !$transferData) {
    showErrorPage($errorMessage, [
        'title' => 'Unable to Load Transfer',
        'backUrl' => 'index.php',
        'backLabel' => 'Back to Transfer List'
    ]);
    exit;
}

// At this point we have valid transfer data
// Set page variables for html-header.php
$page_title = 'Pack Transfer #' . (int)$transferData->id;
$body_class = 'app header-fixed sidebar-fixed aside-menu-fixed sidebar-lg-show';
$csrf = htmlspecialchars($_SESSION['csrf'] ?? '', ENT_QUOTES);
$page_head_extra = <<<HTML
<link rel="stylesheet" href="/modules/consignments/stock-transfers/css/pack.css">
<link rel="stylesheet" href="/modules/consignments/stock-transfers/css/pack-print.css" media="print">
<meta name="csrf-token" content="{$csrf}">
HTML;

include(ROOT_PATH."/assets/template/html-header.php");
include(ROOT_PATH."/assets/template/header.php");
?>

<!-- Fixed Position Auto-Save Indicator -->
<div class="auto-save-container">
  <div id="autosave-indicator" class="auto-save-badge">
    <div class="save-status-icon"></div>
    <div class="save-status-text">
      <span class="save-status">IDLE</span>
      <span class="save-timestamp">Never</span>
    </div>
  </div>
</div>

<div class="app-body">
  <?php include(ROOT_PATH."/assets/template/sidemenu.php") ?>
  <main class="main">
    <!-- Breadcrumb-->
    <ol class="breadcrumb">
      <li class="breadcrumb-item">Home</li>
      <li class="breadcrumb-item"><a href="#">Admin</a></li>
      <li class="breadcrumb-item active">
        OUTGOING Stock Transfer #<?php echo $transferData->id; ?>
        To <?php echo htmlspecialchars($transferData->outlet_to->name); ?>
        </li>
        <li class="breadcrumb-menu d-md-down-none"><?php include(ROOT_PATH.'/assets/template/quick-product-search.php'); ?></li>
      </ol>

      <div class="container-fluid">
        <div class="animated fadeIn">
          <div class="col">

            <div class="card">
              <div class="card-header d-flex justify-content-between align-items-center">
                <div>
                  <h4 class="card-title mb-0">
                    Stock Transfer #<?php echo $transferData->id; ?><br>
                    <?php echo htmlspecialchars($transferData->outlet_from->name); ?> →
                    <?php echo htmlspecialchars($transferData->outlet_to->name); ?>
                  </h4>
                  <div class="small text-muted">These products need to be gathered and prepared for delivery</div>
                </div>

                <div class="btn-group">
                  <button class="btn btn-outline-primary" type="button" data-toggle="modal" data-target="#addProductsModal">
                    <i class="fa fa-plus mr-2"></i> Add Products
                  </button>
                  <button type="button" class="btn btn-outline-secondary" id="tbl-print-top" title="Print picking sheet" onclick="window.print()">
                    <i class="fa fa-print mr-2"></i> Print
                  </button>
                </div>
              </div>

              <div class="card-body cis-containe">
                <!-- Transfer Data for JavaScript -->
            
                <!-- Print-only header (hidden on screen) -->
                <div class="print-header" style="display: none;">
                  <h1>Stock Transfer Packing Slip</h1>
                  <div class="transfer-info">
                    <div>
                      <strong>Transfer ID:</strong> #<?php echo $transferData->id; ?><br>
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
                </div>
                
                <div id="transfer-alerts" class="mb-2" aria-live="polite"></div>

                <!-- Summary strip + table -->
                <div class="card w-100 mb-3" id="table-card">
                  <div class="card-body py-2">
                    <!-- Toolbar -->
<div class="d-flex justify-content-between align-items-start mb-2">
  <!-- Auto-save indicator repositioned as fixed overlay -->
  
  <div style="flex: 1;"></div> <!-- Spacer -->
                      
  <!-- Action buttons on right -->
                      <div>
                        <button class="btn btn-sm btn-outline-primary py-1 px-2" type="button" data-toggle="modal" data-target="#addProductsModal">
                          <i class="fa fa-plus mr-1"></i> Add Products
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary py-1 px-2" id="tbl-print" title="Print picking sheet" onclick="window.print()">
                          <i class="fa fa-print mr-1"></i> Print
                        </button>
                      </div>
                    </div>

                    <table class="table table-responsive-sm table-bordered table-striped table-sm" id="transfer-table" data-transfer-id="<?php echo $transferData->id; ?>">
                      <thead>
                        <tr>
                          <th class="text-center">
                            <span class="d-print-none">Image</span>
                            <!-- ✓ icon will appear in print view via CSS -->
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
                        function manuallyOrderedByStaff_obj($p): string
                        {
                          return (isset($p->staff_added_product) && (int)$p->staff_added_product > 0)
                            ? ' <span class="badge badge-warning">Manually Ordered By Staff</span>'
                            : '';
                        }

                        $fromName = htmlspecialchars($transferData->outlet_from->name ?? '');
                        $toName   = htmlspecialchars($transferData->outlet_to->name ?? '');
                        $tidForCounter = $transferData->id;

                        if (!empty($transferData->items) && is_iterable($transferData->items)) {
                          $i = 0;
                          foreach ($transferData->items as $p) {
                            $i++;
                            $inv     = (int)($p->current_stock ?? 0);
                            $planned = (int)($p->qty_requested ?? 0);
                            if ($planned <= 0) {
                              continue;
                            }

                            $pid   = htmlspecialchars($p->product_id ?? '');
                            $pname = htmlspecialchars($p->product_name ?? '');
                            $sku = htmlspecialchars($p->sku ?? '');
                            
                            // Check if image is real or a placeholder
                            $isPlaceholder = empty($p->image_url) 
                              || strpos($p->image_url, 'placeholder') !== false
                              || strpos($p->image_url, 'no-image') !== false;
                            
                            $imgUrl = !empty($p->image_url)
                              ? htmlspecialchars($p->image_url) 
                              : 'https://via.placeholder.com/80x80?text=No+Image';
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

                <!-- Print-only summary section -->
                <div class="print-summary" style="display: none;">
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
                        echo $totalPlanned;
                      ?>
                    </span>
                  </div>
                  <div class="summary-row">
                    <span>Total Counted Quantity:</span>
                    <span id="print-total-counted">_______</span>
                  </div>
                </div>

                <!-- Print-only notes section -->
                <div class="print-notes" style="display: none;">
                  <h4>Packing Notes / Discrepancies:</h4>
                  <div class="notes-content">
                    <?php 
                      if (!empty($transferData->notes)) {
                        echo htmlspecialchars($transferData->notes);
                      }
                    ?>
                  </div>
                </div>

                <!-- Print-only signature section -->
                <div class="print-footer" style="display: none;">
                  <div style="margin-bottom: 15px;">
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
                      <div class="line">Signature & Date</div>
                    </div>
                    <div class="signature-box">
                      <strong>Received By:</strong>
                      <div class="line">Signature & Date</div>
                    </div>
                  </div>
                </div>

                <!-- Delivery, Notes & Consignment Settings -->
             
                       <!-- =================== Courier Freight Control Center (TABBED) =================== -->



    




                <div class="card-body">
                  <p class="mb-2" style="font-weight:bold;font-size:12px;">
                    Counted &amp; Handled By: <?php echo htmlspecialchars(($userDetails["first_name"] ?? '') . ' ' . ($userDetails["last_name"] ?? '')); ?>
                  </p>

                  <?php if (!$PACKONLY): ?>
                    <p class="mb-3 small text-muted">
                      By setting this transfer "Ready For Delivery" you declare that you have individually counted all the products despatched in this transfer and verified inventory levels.
                    </p>

                    <div class="progress mt-3" style="height: 0.75rem;">
                      <div class="progress-bar" role="progressbar" data-role="progress" style="width:0%;"
                        aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
        

                    <div class="d-flex flex-wrap align-items-center mt-3" style="gap: 0.5rem;">
                      <button type="button" class="btn btn-primary" data-action="create_and_upload">
                        <i class="fa fa-rocket mr-2"></i>Packaged & Ready To Go
                      </button>
           
                    </div>
                  <?php endif; ?>
                  <!-- In Pack-Only we do not render any submit button or extra warning here -->

                </div>
              </div> <!-- /trackingInfo -->
            </div> <!-- /card-body -->
          </div> <!-- /card -->

        </div> <!-- /col -->
      </div> <!-- /fadeIn -->
  </div> <!-- /container-fluid -->
  </main>

  <?php 
  // Include personalisation menu if it exists
  $personalisation_menu = ROOT_PATH."/assets/template/personalisation-menu.php";
  if (file_exists($personalisation_menu)) {
    include($personalisation_menu);
  }
  ?>
  </div> <!-- /app-body -->
  
  <!-- Enterprise AJAX Manager -->
  <script src="/modules/consignments/shared/js/ajax-manager.js"></script>
  <!-- Pack Page JavaScript -->
  <script src="/modules/consignments/stock-transfers/js/pack.js"></script>
  <!-- Pack Page Auto-Fill Hotfix -->
  <script src="/modules/consignments/stock-transfers/js/pack-fix.js"></script>
  
  <?php include(ROOT_PATH . "/assets/template/html-footer.php"); ?>
  <?php include(ROOT_PATH . "/assets/template/footer.php"); ?>

  <!-- Add Products Modal -->
  <div class="modal fade" id="addProductsModal" tabindex="-1" role="dialog" aria-labelledby="addProductsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
      <div class="modal-content border-0 shadow">
        <div class="modal-header bg-primary text-white py-2 px-3 border-0">
          <h6 class="modal-title mb-0" id="addProductsModalLabel"><i class="fa fa-search mr-2"></i>Add Products</h6>
          <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close" style="font-size: 1.2rem; opacity: 0.8;">
            <span aria-hidden="true">×</span>
          </button>
        </div>
        <div class="modal-body p-3">
          <div class="row mb-2">
            <div class="col-12">
              <div class="input-group input-group-sm">
                <div class="input-group-prepend">
                  <span class="input-group-text bg-light">
                    <i class="fa fa-search text-muted"></i>
                  </span>
                </div>
                <input id="search-input" type="text" class="form-control"
                  placeholder="Search by product name, SKU, or ID..." autocomplete="off" autocapitalize="off" autocorrect="off" spellcheck="false" />
              </div>
              <div class="small text-muted mt-1" style="font-size: 0.75rem;">Type at least 2 characters to search...</div>
            </div>
          </div>

          <div class="d-flex justify-content-between align-items-center mb-2">
            <small class="mb-0 text-secondary font-weight-bold">Search Results</small>
            <div class="d-flex align-items-center" style="gap:8px;">
              <span class="badge badge-light text-dark" style="font-size: 0.7rem;" id="results-count">0 results</span>
              <div class="spinner-border spinner-border-sm text-primary d-none" id="search-spinner" role="status" aria-hidden="true" style="width:0.8rem;height:0.8rem;border-width:.12rem;"></div>
            </div>
          </div>

          <!-- Bulk Selection Controls -->
          <div class="d-none" id="bulk-controls" style="background: #f0f7ff; border-radius: 4px; padding: 8px; margin-bottom: 12px; border-left: 3px solid #2196f3;">
            <div class="d-flex justify-content-between align-items-center">
              <div class="d-flex align-items-center" style="gap: 8px;">
                <span class="font-weight-bold text-primary" style="font-size: 0.85rem;">
                  <i class="fa fa-check-square"></i>
                  <span id="selected-count">0</span> selected
                </span>
                <button class="btn btn-xs btn-outline-primary py-0 px-2" style="font-size: 0.75rem;" onclick="selectAllVisible()">
                  <i class="fa fa-check-double"></i> All
                </button>
                <button class="btn btn-xs btn-outline-secondary py-0 px-2" style="font-size: 0.75rem;" onclick="clearSelection()">
                  <i class="fa fa-times"></i> Clear
                </button>
              </div>
              <div class="d-flex align-items-center" style="gap: 6px;">
                <button class="btn btn-success btn-xs py-0 px-2" style="font-size: 0.75rem;" onclick="addSelectedToCurrentTransfer()" id="add-to-current-btn">
                  <i class="fa fa-plus"></i> Add to Transfer
                </button>
                <div class="btn-group">
                  <button class="btn btn-primary btn-xs py-0 px-2" style="font-size: 0.75rem;" onclick="addSelectedToAllTransfers()" id="add-to-all-btn">
                    <i class="fa fa-layer-group"></i> All Transfers
                  </button>
                  <button type="button" class="btn btn-primary btn-xs dropdown-toggle dropdown-toggle-split py-0 px-1" style="font-size: 0.75rem;" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    <span class="sr-only">Toggle Dropdown</span>
                  </button>
                  <div class="dropdown-menu dropdown-menu-right">
                    <a class="dropdown-item py-1 px-2" style="font-size: 0.8rem;" href="#" onclick="addSelectedToOutletTransfers()">
                      <i class="fa fa-building mr-1"></i> Outlet Transfers
                    </a>
                    <a class="dropdown-item py-1 px-2" style="font-size: 0.8rem;" href="#" onclick="addSelectedToSimilarTransfers()">
                      <i class="fa fa-copy mr-1"></i> Similar Routes
                    </a>
                  </div>
                </div>
              </div>
            </div>
            <div class="small text-muted mt-1" style="font-size: 0.7rem;">
              <i class="fa fa-info-circle"></i>
              Tip: Ctrl+click or Shift+click for multi-select
            </div>
          </div>

          <div class="table-responsive" style="max-height: 450px; overflow-y: auto;">
            <table class="table table-hover table-sm table-borderless" id="addProductSearch">
              <thead class="table-light sticky-top">
                <tr>
                  <th class="border-0 font-weight-bold text-center align-middle py-2 px-2" style="width: 40px;">
                    <input type="checkbox" id="selectAllProducts" class="form-check-input" title="Select All">
                  </th>
                  <th class="border-0 font-weight-bold align-middle py-2 px-2" style="width: 60px;">
                    <i class="fa fa-image"></i>
                  </th>
                  <th class="border-0 font-weight-bold align-middle py-2 px-2">Product Details</th>
                  <th class="border-0 font-weight-bold text-center align-middle py-2 px-2" style="width: 60px;">Stock</th>
                  <th class="border-0 font-weight-bold text-center align-middle py-2 px-2" style="width: 70px;">Price</th>
                  <th class="border-0 font-weight-bold text-center align-middle py-2 px-2" style="width: 80px;">Actions</th>
                </tr>
              </thead>
              <tbody id="productAddSearchBody" class="border-0">
                <tr id="search-placeholder">
                  <td colspan="6" class="text-center py-4 text-muted">
                    <div class="d-flex flex-column align-items-center">
                      <i class="fa fa-search fa-2x mb-2" aria-hidden="true" style="opacity:0.4;"></i>
                      <small class="mb-1 font-weight-bold">Ready to search</small>
                      <small class="mb-0" style="font-size: 0.75rem;">Start typing to find products...</small>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
          <div id="search-status" class="small text-muted mt-2" style="font-size: 0.75rem;">Idle</div>
        </div>
        <div class="modal-footer border-0 bg-light py-2 px-3">
          <button type="button" class="btn btn-sm btn-outline-secondary py-1 px-2" data-dismiss="modal">
            <i class="fa fa-times mr-1"></i> Close
          </button>
          <button type="button" class="btn btn-sm btn-primary py-1 px-2" id="btn-clear-search">
            <i class="fa fa-refresh mr-1"></i> Clear
          </button>
        </div>
      </div>
    </div>
  </div>


  <!-- Global CIS Toast Notification System (template-wide) -->
  <script src="/assets/js/cis-toast.js"></script>
  
  <style>
    
  </style>

  <!-- 🎆 EPIC SUBMISSION OVERLAY - Full Brand Experience -->
  <div id="submission-overlay" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: linear-gradient(135deg, #0a0a0a 0%, #1a1a2e 50%, #0a0a0a 100%); z-index: 9999; overflow: hidden;">
    
    <!-- Animated Background Particles -->
    <canvas id="particle-canvas" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; opacity: 0.3;"></canvas>
    
    <!-- Main Content Container -->
    <div style="position: relative; height: 100%; display: flex; flex-direction: column; justify-content: center; align-items: center; padding: 20px; overflow-y: auto;">
      
      <!-- Success Celebration Container (Hidden by default) -->
      <div id="celebration-container" style="display: none; position: absolute; top: 0; left: 0; width: 100%; height: 100%; pointer-events: none; z-index: 10;">
        <canvas id="confetti-canvas" style="width: 100%; height: 100%;"></canvas>
      </div>
      
      <!-- Header with Pulsing Logo -->
      <div id="overlay-header" style="margin-bottom: 40px; text-align: center;">
        <div style="position: relative; display: inline-block;">
          <div class="pulse-ring"></div>
          <i class="fa fa-rocket" id="header-icon" style="font-size: 64px; color: #00d4ff; text-shadow: 0 0 20px rgba(0,212,255,0.5); transition: all 0.5s ease;"></i>
        </div>
        <h2 id="overlay-title" style="font-size: 32px; font-weight: 700; margin: 20px 0 10px 0; color: #fff; text-shadow: 0 2px 10px rgba(0,0,0,0.5); letter-spacing: 1px;">
          Creating Consignment
        </h2>
        <p id="overlay-subtitle" style="font-size: 16px; color: #8b92a8; margin: 0; font-weight: 400;">
          Building the future of logistics...
        </p>
      </div>

      <!-- Progress Container -->
      <div style="width: 100%; max-width: 700px; margin: 0 auto;">
        
        <!-- Overall Progress Bar -->
        <div style="margin-bottom: 30px;">
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
            <span style="color: #8b92a8; font-size: 14px; font-weight: 500;">Overall Progress</span>
            <span id="overall-percentage" style="color: #00d4ff; font-size: 18px; font-weight: 700;">0%</span>
          </div>
          <div style="background: rgba(255,255,255,0.05); height: 8px; border-radius: 10px; overflow: hidden; box-shadow: inset 0 2px 4px rgba(0,0,0,0.3);">
            <div id="overall-progress-bar" style="height: 100%; width: 0%; background: linear-gradient(90deg, #00d4ff 0%, #0099ff 50%, #00d4ff 100%); background-size: 200% 100%; animation: shimmer 2s infinite; transition: width 0.5s cubic-bezier(0.4, 0, 0.2, 1); box-shadow: 0 0 10px rgba(0,212,255,0.5);"></div>
          </div>
        </div>

        <!-- Progress Steps -->
        <div id="progress-steps" style="margin-bottom: 30px;">
          
          <div class="progress-step" data-step="validation">
            <div class="step-content">
              <div class="step-number">1</div>
              <div class="step-info">
                <div class="step-title">Validating Transfer Data</div>
                <div class="step-details">Checking quantities and validating products...</div>
                <div class="step-progress-bar">
                  <div class="step-progress-fill" data-progress="0"></div>
                </div>
              </div>
              <div class="step-icon">
                <i class="fa fa-check-circle"></i>
              </div>
            </div>
          </div>

          <div class="progress-step" data-step="consignment">
            <div class="step-content">
              <div class="step-number">2</div>
              <div class="step-info">
                <div class="step-title">Creating Consignment</div>
                <div class="step-details">Generating consignment in Lightspeed...</div>
                <div class="step-progress-bar">
                  <div class="step-progress-fill" data-progress="0"></div>
                </div>
              </div>
              <div class="step-icon">
                <i class="fa fa-box"></i>
              </div>
            </div>
          </div>

          <div class="progress-step" data-step="products">
            <div class="step-content">
              <div class="step-number">3</div>
              <div class="step-info">
                <div class="step-title">Processing Products</div>
                <div class="step-details">Adding products to consignment...</div>
                <div class="step-progress-bar">
                  <div class="step-progress-fill" data-progress="0"></div>
                </div>
              </div>
              <div class="step-icon">
                <i class="fa fa-boxes"></i>
              </div>
            </div>
          </div>

          <div class="progress-step" data-step="complete">
            <div class="step-content">
              <div class="step-number">4</div>
              <div class="step-info">
                <div class="step-title">Finalizing Transfer</div>
                <div class="step-details">Marking transfer as sent...</div>
                <div class="step-progress-bar">
                  <div class="step-progress-fill" data-progress="0"></div>
                </div>
              </div>
              <div class="step-icon">
                <i class="fa fa-flag-checkered"></i>
              </div>
            </div>
          </div>

        </div>

        <!-- Real-Time SSE Feed -->
        <div id="live-feedback-container" style="background: rgba(0,0,0,0.5); border: 1px solid rgba(0,212,255,0.2); border-radius: 12px; padding: 20px; max-height: 250px; overflow-y: auto; backdrop-filter: blur(10px); box-shadow: 0 4px 20px rgba(0,0,0,0.3);">
          <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 1px solid rgba(255,255,255,0.1);">
            <h4 style="margin: 0; font-size: 14px; font-weight: 600; color: #00d4ff; text-transform: uppercase; letter-spacing: 1px;">
              <i class="fa fa-satellite-dish mr-2"></i>Live Feed
            </h4>
            <div id="connection-status" style="display: flex; align-items: center; gap: 6px;">
              <div class="connection-dot"></div>
              <span style="font-size: 11px; color: #8b92a8; font-weight: 500;">CONNECTED</span>
            </div>
          </div>
          <div id="live-feedback" class="feedback-messages"></div>
        </div>

        <!-- Error State (Hidden by default) -->
        <div id="error-state" style="display: none; background: linear-gradient(135deg, rgba(255,59,48,0.1) 0%, rgba(255,69,58,0.05) 100%); border: 2px solid rgba(255,59,48,0.3); border-radius: 12px; padding: 30px; margin-top: 30px; backdrop-filter: blur(10px); box-shadow: 0 4px 20px rgba(255,59,48,0.2);">
          <div style="text-align: center; margin-bottom: 20px;">
            <i class="fa fa-shield-alt" style="font-size: 48px; color: #ff3b30; margin-bottom: 15px;"></i>
            <h3 style="color: #ff6b6b; margin: 0 0 10px 0; font-size: 24px; font-weight: 600;">
              Oops! Something Went Wrong
            </h3>
            <p style="color: #b8bcc8; margin: 0 0 15px 0; font-size: 15px; line-height: 1.6;">
              Don't worry! Your hard work is <strong style="color: #00d4ff;">100% safe</strong>. We've saved everything locally, and nothing has been lost.
            </p>
          </div>
          <div id="error-message" style="background: rgba(0,0,0,0.3); border-left: 4px solid #ff3b30; padding: 15px; border-radius: 8px; margin-bottom: 20px; font-family: 'Courier New', monospace; font-size: 13px; color: #ff9999;"></div>
          <div style="display: flex; gap: 10px; justify-content: center;">
            <button class="btn btn-outline-light" onclick="closeSubmissionOverlay();" style="padding: 10px 20px;">
              <i class="fa fa-times mr-2"></i>Close
            </button>
            <button class="btn btn-primary" onclick="location.reload();" style="background: linear-gradient(135deg, #00d4ff 0%, #0099ff 100%); border: none; padding: 10px 20px;">
              <i class="fa fa-redo mr-2"></i>Try Again
            </button>
          </div>
        </div>

      </div>

    </div>
  </div>

</body>

</html>
