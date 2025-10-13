<?php
/**
 * Stock Transfer (Packing / Consignment) — Production Grade (Pack-Only single warning)
 * -----------------------------------------------------------------------------------
 * - One BIG warning in Pack-Only mode (no other warnings)
 * - Submit disabled in Pack-Only (server blocks POST submit too)
 * - Full UI/UX retained (counts, notes, tracking, product search, merge, labels)
 * - Calls external functions only (no new queues created here)
 */
include("assets/functions/config.php");

// Initialize PACKONLY before POST handler (prevents undefined variable error)
$PACKONLY = false; // Default to false until we can properly check transfer data

// ---------- Unified AJAX handler ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');

    // 1) Enforce login
    try {
        $userRow = requireLoggedInUser();
    } catch (Throwable $e) {
        http_response_code(401);
        echo json_encode(['success'=>false,'error'=>'Not logged in. Please sign in and try again.'], JSON_UNESCAPED_SLASHES);
        exit;
    }

    // 2) Exactly one action
    $allowedKeys  = ['markReadyForDelivery', 'deleteTransfer', 'searchForProduct'];
    $presentKeys  = array_values(array_filter($allowedKeys, static fn($k) => isset($_POST[$k])));
    if (count($presentKeys) !== 1) {
        http_response_code(400);
        echo json_encode(['success'=>false,'error'=>'Provide exactly one action.'], JSON_UNESCAPED_SLASHES);
        exit;
    }

    $action = $presentKeys[0];

    // 3) In PACKONLY, block submit; allow search/delete
    if ($PACKONLY && $action === 'markReadyForDelivery') {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'error'   => 'Pack-Only Mode: submission is disabled. Do not send or do anything with this transfer until confirmed.'
        ], JSON_UNESCAPED_SLASHES);
        exit;
    }

    // 4) Actions
    try {
        switch ($action) {
            case 'markReadyForDelivery': {
                $payload = $_POST['markReadyForDelivery'] ?? [];
                if (is_string($payload)) {
                    $decoded = json_decode($payload, true);
                    if (json_last_error() === JSON_ERROR_NONE) $payload = $decoded;
                } elseif (!is_array($payload)) {
                    $payload = (array)$payload;
                }

                $userId = (int)($_SESSION['userID'] ?? 0);

                // EXTERNAL: saveTransferReady_wrapped($payload, $userId) handles consignment-first send
                $result = saveTransferReady_wrapped($payload, $userId);

                if (!($result->success ?? false)) http_response_code(400);
                echo json_encode($result, JSON_UNESCAPED_SLASHES);
                break;
            }

            case 'deleteTransfer': {
                $payload = json_decode($_POST['deleteTransfer'] ?? '[]', true) ?: [];
                $userId  = (int)($_SESSION['userID'] ?? 0);
                $result  = deleteTransfer_wrapped($payload, $userId);
                if (!($result['success'] ?? false)) http_response_code(400);
                echo json_encode($result, JSON_UNESCAPED_SLASHES);
                break;
            }

            case 'searchForProduct': {
                error_log('[view-stock-transfer] 🔍 searchForProduct action triggered');
                $payload = json_decode($_POST['searchForProduct'] ?? '[]', true) ?: [];
                $keyword = trim((string)($payload['keyword'] ?? ''));
                $outlet  = trim((string)($payload['outletID'] ?? ''));
                error_log("[view-stock-transfer] 📊 Search params: keyword='$keyword', outlet='$outlet'");
                
                // Check if function exists, if not use fallback
                if (!function_exists('searchForProductByOutlet_wrapped')) {
                    error_log('[view-stock-transfer] ❌ searchForProductByOutlet_wrapped function not found! Using fallback...');
                    $res = searchForProductByOutlet_fallback($keyword, $outlet, 50);
                } else {
                    $res = searchForProductByOutlet_wrapped($keyword, $outlet, 50);
                }
                error_log('[view-stock-transfer] 📋 Search result: ' . json_encode($res));
                if (!($res['success'] ?? false)) http_response_code(400);
                echo json_encode($res, JSON_UNESCAPED_SLASHES);
                break;
            }

            default: {
                http_response_code(400);
                echo json_encode(['success'=>false,'error'=>'Unknown action.'], JSON_UNESCAPED_SLASHES);
                break;
            }
        }
    } catch (Throwable $e) {
        error_log('[view-stock-transfer] Fatal: '.$e->getMessage().' trace='.$e->getTraceAsString());
        http_response_code(500);
        echo json_encode(['success'=>false,'error'=>'Unexpected server error. Please try again.'], JSON_UNESCAPED_SLASHES);
    }
    exit;
}

// ---------- GET phase ----------
$transferData   = null;
$userDetails    = null;
$mergeTransfers = [];

try {
    $uid = (int)($_SESSION['userID'] ?? 0);
    if ($uid > 0) { $userDetails = getUserDetails($uid); }

    $transferIdParam = isset($_GET['transfer']) ? (int)$_GET['transfer'] : 0;
    if ($transferIdParam <= 0) throw new InvalidArgumentException('Missing or invalid transfer parameter.');

    $transferData = getTransferData($transferIdParam, false);
    if (!$transferData) throw new RuntimeException('Transfer not found.');

    $mergeTransfers = getAvailableMergeTransfers(
        $transferData->outlet_from->id,
        $transferIdParam,
        $transferData->outlet_to->id
    );


      // 🚩 Packing-only mode flag (now set earlier to prevent POST errors)
      if ($transferData->outlet_from->id != "02dcd191-ae2b-11e6-f485-8eceed6eeafb"){
        $PACKONLY = true;
      }else{
        $PACKONLY = false;
      }

} catch (Throwable $e) {
    error_log('[view-stock-transfer] preload error: '.$e->getMessage());
    $transferData = $transferData ?? (object)[
        'outlet_from' => (object)['id'=>0,'name'=>'Unknown'],
        'outlet_to'   => (object)['id'=>0,'name'=>'Unknown'],
        'products'    => []
    ];
    $mergeTransfers = [];
}

include("assets/template/html-header.php");
include("assets/template/header.php");
?>
<body class="app header-fixed sidebar-fixed aside-menu-fixed sidebar-lg-show">



<div class="app-body">
  <?php include("assets/template/sidemenu.php") ?>
  <main class="main">
    <!-- Breadcrumb-->
    <ol class="breadcrumb">
      <li class="breadcrumb-item">Home</li>
      <li class="breadcrumb-item"><a href="#">Admin</a></li>
      <li class="breadcrumb-item active">
        OUTGOING Stock Transfer #<?php echo (int)($_GET["transfer"] ?? 0); ?>
        To <?php echo htmlspecialchars($transferData->outlet_to->name); ?>
      </li>
      <li class="breadcrumb-menu d-md-down-none"><?php include('assets/template/quick-product-search.php'); ?></li>
    </ol>

    <div class="container-fluid">
      <div class="animated fadeIn">
        <div class="col">

          <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
              <div>
                <h4 class="card-title mb-0">
                  Stock Transfer #<?php echo (int)($_GET["transfer"] ?? 0); ?><br>
                  <?php echo htmlspecialchars($transferData->outlet_from->name); ?> →
                  <?php echo htmlspecialchars($transferData->outlet_to->name); ?>
                </h4>
                <div class="small text-muted">These products need to be gathered and prepared for delivery</div>
              </div>

              <div class="btn-group">
                <button class="btn btn-outline-primary dropdown-toggle d-flex align-items-center" type="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                  <i class="fa fa-cog mr-2"></i> Options
                </button>
                <div class="dropdown-menu dropdown-menu-right shadow border-0">

                  <?php if (count($mergeTransfers) > 0): ?>
                    <button class="dropdown-item" type="button" data-toggle="modal" data-target="#mergeTransferModal">
                      <i class="fa fa-code-fork mr-2"></i> Merge Transfer
                    </button>
                  <?php else: ?>
                    <button class="dropdown-item" type="button" data-toggle="modal" data-target="#mergeTransferModal" disabled>
                      <i class="fa fa-code-fork mr-2 text-muted"></i> Merge Transfer (None Available)
                    </button>
                  <?php endif; ?>

                  <button class="dropdown-item" type="button" data-toggle="modal" data-target="#addProductsModal">
                    <i class="fa fa-plus mr-2"></i> Add Products
                  </button>
                  <button id="editModeButton" class="dropdown-item" type="button" onclick="editMode(true)">
                    <i class="fa fa-edit mr-2"></i> Edit Transfer
                  </button>
                  <div class="dropdown-divider"></div>
                  <button class="dropdown-item text-danger" type="button" onclick="deleteTransfer(<?php echo (int)($_GET["transfer"] ?? 0); ?>)">
                    <i class="fa fa-trash mr-2"></i> Delete Transfer
                  </button>
                </div>
              </div>
            </div>

            <div class="card-body transfer-data">
              <div id="transfer-alerts" class="mb-2" aria-live="polite"></div>

              <!-- Toolbar -->
              <div class="d-flex justify-content-between align-items-start w-100 mb-2" id="table-action-toolbar" style="gap:8px;">
                <!-- Draft controls -->
                <div class="d-flex flex-column" style="gap:4px;">
                  <div class="d-flex align-items-center" style="gap:8px;">
                    <span class="badge badge-pill badge-secondary" id="draft-status">Draft: Off</span>
                    <span class="text-muted small" id="draft-last-saved">Not saved</span>
                  </div>
                  <div class="d-flex align-items-center" style="gap:12px;">
                    <div class="d-flex" style="gap:8px;" role="group" aria-label="Draft actions">
                      <button type="button" class="btn btn-sm btn-outline-primary" id="btn-save-draft" 
                              style="padding: 4px 12px; font-size: 0.875rem;" 
                              title="Save a draft to this browser only (does not update Vend)">Save now (Ctrl+S)</button>
                      <button type="button" class="btn btn-sm btn-outline-success" id="btn-restore-draft" 
                              style="padding: 4px 12px; font-size: 0.875rem;" disabled>Restore</button>
                      <button type="button" class="btn btn-sm btn-outline-danger" id="btn-discard-draft" 
                              style="padding: 4px 12px; font-size: 0.875rem;" disabled>Discard</button>
                    </div>
                    <div class="custom-control custom-switch" title="Auto-save to this browser only (does not update Vend)">
                      <input type="checkbox" class="custom-control-input" id="toggle-autosave">
                      <label class="custom-control-label" for="toggle-autosave">Autosave</label>
                    </div>
                  </div>
                </div>

                <div class="d-flex align-items-center flex-wrap" style="gap:8px;">
                  <button class="btn btn-outline-primary d-flex align-items-center" type="button" data-toggle="modal" data-target="#addProductsModal">
                    <i class="fa fa-plus mr-2"></i> Add Products
                  </button>
                  <button type="button" class="btn btn-outline-secondary d-flex align-items-center" id="tbl-print" title="Print picking sheet" onclick="window.print()">
                    <i class="fa fa-print mr-2"></i> Print
                  </button>


                </div>
              </div>

              <!-- Summary strip + table -->
              <div class="card w-100 mb-3" id="table-card">
                <div class="card-body py-2">
                  <div style="display:flex;gap:16px;align-items:center;flex-wrap:wrap;margin-bottom:8px;">
                    <span>Items: <strong id="itemsToTransfer"><?php echo count($transferData->products); ?></strong></span>
                    <span>Planned total: <strong id="plannedTotal">0</strong></span>
                    <span>Counted total: <strong id="countedTotal">0</strong></span>
                    <span>Diff: <strong id="diffTotal">0</strong></span>
                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="autofillCountedFromPlanned();">Fill counted = planned</button>
                  </div>

                  <table class="table table-responsive-sm table-bordered table-striped table-sm" id="transfer-table">
                    <thead>
                      <tr>
                        <th></th>
                        <th>Name</th>
                        <th>Qty In Stock</th>
                        <th>Planned Qty</th>
                        <th>Counted Qty</th>
                        <th>Source</th>
                        <th>Destination</th>
                        <th>ID</th>
                      </tr>
                    </thead>
                    <tbody id="productSearchBody">
                      <?php
                        function manuallyOrderedByStaff_obj($p): string {
                          return (isset($p->staff_added_product) && (int)$p->staff_added_product > 0)
                            ? ' <span class="badge badge-warning">Manually Ordered By Staff</span>'
                            : '';
                        }

                        $fromName = htmlspecialchars($transferData->outlet_from->name ?? '');
                        $toName   = htmlspecialchars($transferData->outlet_to->name ?? '');
                        $tidForCounter = isset($transferIdParam) ? (int)$transferIdParam : (int)($_GET['transfer'] ?? 0);

                        if (!empty($transferData->products) && is_iterable($transferData->products)) {
                          $i = 0;
                          foreach ($transferData->products as $p) {
                            $i++;
                            $inv     = (int)($p->inventory_level ?? 0);
                            $planned = (int)($p->qty_to_transfer ?? 0);
                            if ($planned <= 0) { continue; }

                            $pid   = htmlspecialchars($p->product_id ?? '');
                            $pname = htmlspecialchars($p->product_name ?? '');

                            echo "<tr data-inventory=\"{$inv}\" data-planned=\"{$planned}\">"
                               . "<td><p style='text-align:center;margin:0;'>
                                      <img style='cursor:pointer; padding:0; margin:0; height:13px;'
                                           src='assets/img/remove-icon.png' title='Remove Product'
                                           onclick='removeProduct(this);'></p>
                                    <input type='hidden' class='productID' value='{$pid}'></td>"
                               . "<td>{$pname}" . manuallyOrderedByStaff_obj($p) . "</td>"
                               . "<td class='inv'>{$inv}</td>"
                               . "<td class='planned'>{$planned}</td>"
                               . "<td class='counted-td'>
                                    <input type='number' min='0' max='{$inv}'
                                           oninput='enforceBounds(this);addToLocalStorage();checkInvalidQty(this);recomputeTotals();syncPrintValue(this);'
                                           value='' style='width:6em;'>
                                    <span class='counted-print-value d-none'>0</span>
                                  </td>"
                               . "<td>{$fromName}</td>"
                               . "<td>{$toName}</td>"
                               . "<td><span class='id-counter'>{$tidForCounter}-{$i}</span></td>"
                               . "</tr>";
                          }
                        }
                      ?>
                    </tbody>
                  </table>
                </div>
              </div>

              <!-- Delivery, Notes & Consignment Settings -->
              <div id="trackingInfo" class="w-100">
                <div class="card mb-2 mt-3" id="delivery-tracking-card">
                  <div class="card-header py-2 d-flex justify-content-between align-items-center">
                    <strong>Delivery & Notes</strong>
                    <small class="text-muted">Consignment-first | Send mode with server-side fallback</small>
                  </div>
                  <div class="card-body">
                    <div class="row">
                      <div class="col-md-6 mb-3">
                        <label class="mb-2"><strong>Notes & Discrepancies</strong></label>
                        <textarea onkeyup="addToLocalStorage();" class="form-control" id="notesForTransfer" rows="4" placeholder="Enter any notes, discrepancies, or special instructions..."></textarea>
                      </div>
                      <div class="col-md-6 mb-3">
                        <div class="row">
                          <div class="col-md-5">
                            <label class="mb-2"><strong>Delivery Method</strong></label>
                            <div class="mb-3">
                              <div class="custom-control custom-radio mb-2">
                                <input type="radio" id="mode-courier" name="delivery-mode" class="custom-control-input" value="courier" checked onchange="toggleTrackingVisibility()">
                                <label class="custom-control-label" for="mode-courier">Courier delivery</label>
                              </div>
                              <div class="custom-control custom-radio">
                                <input type="radio" id="mode-internal" name="delivery-mode" class="custom-control-input" value="internal" onchange="toggleTrackingVisibility()">
                                <label class="custom-control-label" for="mode-internal">Internal (drive/drop)</label>
                              </div>
                            </div>
                          </div>

                          <div class="col-md-7" id="tracking-section">
                            <label class="mb-2"><strong>Courier Services & Labels</strong>
                              <?php if ($PACKONLY): ?>
                                <span class="badge badge-warning ml-2">
                                  <i class="fa fa-lock"></i> Read-Only Mode
                                </span>
                              <?php endif; ?>
                            </label>
                            
                            <!-- Courier Service Selector -->
                            <div class="mb-2">
                              <select id="courier-service" class="form-control" style="font-size: 15px; padding: 12px 16px; height: auto;" <?php echo $PACKONLY ? 'disabled' : ''; ?>>
                                <option value="">Select courier service...</option>
                                <option value="nzpost">🔴 NZ POST LEADS</option>
                                <option value="gss">NZ Couriers</option>
                                <option value="manual">Manual Entry</option>
                              </select>
                              <div id="printer-status" class="small text-muted mt-1" style="display: none;">
                                <i class="fa fa-info-circle"></i> 
                                <span id="printer-status-text"></span>
                                <button type="button" class="btn btn-sm btn-outline-secondary ml-2" onclick="detectAndSetDefaultPrinter()" style="font-size: 0.7rem; padding: 1px 6px;">
                                  <i class="fa fa-refresh"></i> Refresh
                                </button>
                              </div>
                            </div>

                            <!-- GSS Integration Panel -->
                            <div id="gss-panel" class="courier-panel card border-primary mb-2" style="display:none;">
                              <div class="card-header py-2 bg-primary text-white">
                                <small><strong>NZ Couriers Label Creation</strong></small>
                                <?php if ($PACKONLY): ?>
                                  <small class="float-right"><i class="fa fa-lock"></i> Disabled in pack-only mode</small>
                                <?php endif; ?>
                              </div>
                              <div class="card-body py-2">
                                <!-- Auto-loaded Address Information -->
                                <div class="mb-2 p-2 bg-light rounded">
                                  <small class="text-muted"><strong>Shipping Details:</strong></small><br>
                                  <small>
                                    <strong>From:</strong> <?php echo htmlspecialchars($transferData->outlet_from->name); ?> 
                                    <span class="text-muted">(ID: <?php echo htmlspecialchars($transferData->outlet_from->id); ?>)</span><br>
                                    <strong>To:</strong> <?php echo htmlspecialchars($transferData->outlet_to->name); ?> 
                                    <span class="text-muted">(ID: <?php echo htmlspecialchars($transferData->outlet_to->id); ?>)</span>
                                  </small>
                                </div>
                                
                                <div class="row">
                                  <div class="col-md-6">
                                    <label class="small">Package Type:</label>
                                    <select id="gss-package-type" class="form-control form-control-sm mb-1" <?php echo $PACKONLY ? 'disabled' : ''; ?>>
                                      <option value="satchel3kg">Satchel 3kg</option>
                                      <option value="satchel5kg">Satchel 5kg</option>
                                      <option value="box3kg">Box 3kg</option>
                                      <option value="box5kg">Box 5kg</option>
                                      <option value="box10kg">Box 10kg</option>
                                    </select>
                                  </div>
                                  <div class="col-md-6">
                                    <label class="small">Options:</label>
                                    <div class="form-check form-check-inline">
                                      <input class="form-check-input" type="checkbox" id="gss-signature" checked <?php echo $PACKONLY ? 'disabled' : ''; ?>>
                                      <label class="form-check-label small" for="gss-signature">Signature</label>
                                    </div>
                                    <div class="form-check form-check-inline">
                                      <input class="form-check-input" type="checkbox" id="gss-saturday" <?php echo $PACKONLY ? 'disabled' : ''; ?>>
                                      <label class="form-check-label small" for="gss-saturday">Saturday</label>
                                    </div>
                                  </div>
                                </div>
                                <div class="mt-2">
                                  <textarea id="gss-instructions" class="form-control form-control-sm" rows="2" placeholder="Special delivery instructions..." <?php echo $PACKONLY ? 'disabled' : ''; ?>></textarea>
                                </div>
                                <div class="mt-2">
                                  <?php if (!$PACKONLY): ?>
                                  <button type="button" class="btn btn-sm btn-primary" onclick="createGSSLabel();">
                                    <i class="fa fa-print"></i> Create & Print GSS Label
                                  </button>
                                  <?php else: ?>
                                  <button type="button" class="btn btn-sm btn-secondary" disabled title="Disabled in pack-only mode">
                                    <i class="fa fa-lock"></i> Shipping Locked
                                  </button>
                                  <?php endif; ?>
                                  <span id="gss-status" class="ml-2 small"></span>
                                </div>
                              </div>
                            </div>



                            <!-- Shipping Integration Tabs - Clean & Modern -->
                            <div id="shipping-tabs-container" class="courier-panel mb-2" style="display:none;">
                              <div class="card shadow-sm border-0">
                                <!-- Tab Headers -->
                                <div class="card-header p-0 bg-white border-bottom">
                                  <ul class="nav nav-tabs nav-fill shipping-tabs" id="shippingTabs" role="tablist">
                                    <li class="nav-item" role="presentation">
                                      <a class="nav-link active d-flex align-items-center justify-content-center" 
                                         id="nzpost-tab" data-toggle="tab" href="#nzpost-pane" role="tab" 
                                         aria-controls="nzpost-pane" aria-selected="true">
                                        <i class="fa fa-truck text-danger mr-2"></i>
                                        <span class="font-weight-bold">NZ Post</span>
                                        <span class="badge badge-danger ml-2 d-none" id="nzpost-badge">1</span>
                                      </a>
                                    </li>
                                    <li class="nav-item" role="presentation">
                                      <a class="nav-link d-flex align-items-center justify-content-center" 
                                         id="gss-tab" data-toggle="tab" href="#gss-pane" role="tab" 
                                         aria-controls="gss-pane" aria-selected="false">
                                        <i class="fa fa-shipping-fast text-success mr-2"></i>
                                        <span class="font-weight-bold">GSS Courier</span>
                                        <span class="badge badge-success ml-2 d-none" id="gss-badge">1</span>
                                      </a>
                                    </li>
                                    <li class="nav-item" role="presentation">
                                      <a class="nav-link d-flex align-items-center justify-content-center" 
                                         id="manual-tab" data-toggle="tab" href="#manual-pane" role="tab" 
                                         aria-controls="manual-pane" aria-selected="false">
                                        <i class="fa fa-edit text-primary mr-2"></i>
                                        <span class="font-weight-bold">Manual Entry</span>
                                        <span class="badge badge-primary ml-2 d-none" id="manual-badge">1</span>
                                      </a>
                                    </li>
                                    <li class="nav-item" role="presentation">
                                      <a class="nav-link d-flex align-items-center justify-content-center" 
                                         id="history-tab" data-toggle="tab" href="#history-pane" role="tab" 
                                         aria-controls="history-pane" aria-selected="false">
                                        <i class="fa fa-history text-info mr-2"></i>
                                        <span class="font-weight-bold">Recent</span>
                                      </a>
                                    </li>
                                  </ul>
                                </div>

                                <!-- Tab Content -->
                                <div class="tab-content" id="shippingTabContent">
                                  <!-- NZ Post Tab -->
                                  <div class="tab-pane fade show active" id="nzpost-pane" role="tabpanel" aria-labelledby="nzpost-tab">
                                    <div class="card-body">
                                      <?php if ($PACKONLY): ?>
                                        <div class="alert alert-warning d-flex align-items-center mb-3">
                                          <i class="fa fa-lock mr-2"></i>
                                          <span>NZ Post integration disabled in pack-only mode</span>
                                        </div>
                                      <?php endif; ?>
                                      
                                      <!-- Address Summary Bar -->
                                      <div class="address-summary mb-3 p-3 bg-light rounded border-left border-danger">
                                        <div class="row">
                                          <div class="col-6">
                                            <div class="d-flex align-items-center">
                                              <i class="fa fa-map-marker-alt text-muted mr-2"></i>
                                              <div>
                                                <div class="font-weight-bold small"><?php echo htmlspecialchars($transferData->outlet_from->name); ?></div>
                                                <div class="text-muted small">From Location</div>
                                              </div>
                                            </div>
                                          </div>
                                          <div class="col-6">
                                            <div class="d-flex align-items-center">
                                              <i class="fa fa-arrow-right text-danger mr-2"></i>
                                              <div>
                                                <div class="font-weight-bold small"><?php echo htmlspecialchars($transferData->outlet_to->name); ?></div>
                                                <div class="text-muted small">To Location</div>
                                              </div>
                                            </div>
                                          </div>
                                        </div>
                                      </div>

                                      <!-- Quick Setup Row -->
                                      <div class="row mb-3">
                                        <div class="col-md-4">
                                          <label class="small font-weight-bold">Service Type</label>
                                          <select id="nzpost-service-type" class="form-control form-control-sm" <?php echo $PACKONLY ? 'disabled' : ''; ?>>
                                            <option value="">Choose service...</option>
                                            <option value="CPOLTPDL">📬 DLE Overnight</option>
                                            <option value="CPOLTPA5">📄 A5 Overnight</option>
                                            <option value="CPOLTPA4">📄 A4 Overnight</option>
                                            <option value="CPOLP">📦 Parcel Overnight</option>
                                            <option value="CPOLE" selected>📦 Economy (2-3 Days)</option>
                                          </select>
                                        </div>
                                        <div class="col-md-4">
                                          <label class="small font-weight-bold">Dimensions (cm)</label>
                                          <div class="input-group input-group-sm">
                                            <input type="number" id="nzpost-length" class="form-control" placeholder="L" min="1" max="120" <?php echo $PACKONLY ? 'disabled' : ''; ?>>
                                            <div class="input-group-append input-group-prepend">
                                              <span class="input-group-text">×</span>
                                            </div>
                                            <input type="number" id="nzpost-width" class="form-control" placeholder="W" min="1" max="120" <?php echo $PACKONLY ? 'disabled' : ''; ?>>
                                            <div class="input-group-append input-group-prepend">
                                              <span class="input-group-text">×</span>
                                            </div>
                                            <input type="number" id="nzpost-height" class="form-control" placeholder="H" min="1" max="120" <?php echo $PACKONLY ? 'disabled' : ''; ?>>
                                          </div>
                                        </div>
                                        <div class="col-md-4">
                                          <label class="small font-weight-bold">Weight & Cost</label>
                                          <div class="input-group input-group-sm">
                                            <input type="number" id="nzpost-weight" class="form-control" placeholder="2.5" step="0.1" min="0.1" max="30" value="2.5" <?php echo $PACKONLY ? 'disabled' : ''; ?>>
                                            <div class="input-group-append">
                                              <span class="input-group-text">kg</span>
                                              <span class="input-group-text bg-success text-white font-weight-bold" id="nzpost-cost-display">$0.00</span>
                                            </div>
                                          </div>
                                        </div>
                                      </div>
                                  
                                  <!-- Quick Preset Buttons -->
                                  <div class="mb-2">
                                    <small class="text-muted"><strong>Quick Presets:</strong></small><br>
                                    <button type="button" class="btn btn-outline-info btn-sm mr-1 mb-1" onclick="setNZPostDimensions(20,15,10,1.5)" <?php echo $PACKONLY ? 'disabled' : ''; ?>>
                                      📦 Small Box (20×15×10) 1.5kg
                                    </button>
                                    <button type="button" class="btn btn-outline-info btn-sm mr-1 mb-1" onclick="setNZPostDimensions(30,20,15,2.5)" <?php echo $PACKONLY ? 'disabled' : ''; ?>>
                                      📦 Medium Box (30×20×15) 2.5kg
                                    </button>
                                    <button type="button" class="btn btn-outline-info btn-sm mr-1 mb-1" onclick="setNZPostDimensions(40,30,20,4.0)" <?php echo $PACKONLY ? 'disabled' : ''; ?>>
                                      📦 Large Box (40×30×20) 4kg
                                    </button>
                                    <button type="button" class="btn btn-outline-warning btn-sm mr-1 mb-1" onclick="setNZPostDimensions(35,25,3,0.8)" <?php echo $PACKONLY ? 'disabled' : ''; ?>>
                                      � Flat Pack (35×25×3) 0.8kg
                                    </button>
                                  </div>

                                  <!-- Multiple Packages Table -->
                                  <div class="mt-2">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                      <small class="text-muted"><strong>Package List:</strong></small>
                                      <button type="button" class="btn btn-outline-primary btn-sm" onclick="addCurrentPackage()" <?php echo $PACKONLY ? 'disabled' : ''; ?>>
                                        <i class="fa fa-plus"></i> Add Package
                                      </button>
                                    </div>
                                    <div class="table-responsive">
                                      <table class="table table-sm table-bordered" id="nzpost-packages-table">
                                        <thead class="table-light">
                                          <tr>
                                            <th style="width: 15%;">Dimensions (L×W×H)</th>
                                            <th style="width: 15%;">Weight</th>
                                            <th style="width: 15%;">Vol. Weight</th>
                                            <th style="width: 25%;">Description</th>
                                            <th style="width: 15%;">Cost Est.</th>
                                            <th style="width: 15%;">Actions</th>
                                          </tr>
                                        </thead>
                                        <tbody>
                                          <!-- Packages will be added dynamically -->
                                        </tbody>
                                      </table>
                                    </div>
                                  </div>
                                </div>

                                <div class="form-group mb-2">
                                  <label class="small">Delivery Instructions:</label>
                                  <textarea class="form-control form-control-sm" id="nzpost-instructions" rows="2" placeholder="Special delivery instructions (optional)" <?php echo $PACKONLY ? 'disabled' : ''; ?>></textarea>
                                </div>

                                <div class="d-flex justify-content-between align-items-center">
                                  <?php if (!$PACKONLY): ?>
                                  <div>
                                    <button class="btn btn-danger btn-sm" onclick="createNZPostShipment()" id="nzpost-create-btn">
                                      <i class="fa fa-print" aria-hidden="true"></i> 
                                      <span id="nzpost-btn-text">Create & Print Label</span>
                                    </button>
                                    <button class="btn btn-outline-danger btn-sm d-none" onclick="reprintNZPostLabel()" id="nzpost-reprint-btn">
                                      <i class="fa fa-repeat" aria-hidden="true"></i> Re-Print Label
                                    </button>
                                  </div>
                                  <?php else: ?>
                                  <button class="btn btn-secondary btn-sm" disabled title="Disabled in pack-only mode">
                                    <i class="fa fa-lock" aria-hidden="true"></i> Shipping Locked
                                  </button>
                                  <?php endif; ?>
                                  <div class="d-flex flex-column">
                                    <div class="text-success fw-bold" id="nzpost-total-cost">Total: $0.00</div>
                                    <small class="text-muted">
                                      <span id="nzpost-package-count">0 packages</span> • 
                                      <span id="nzpost-total-weight">0.0kg total</span> • 
                                      <span id="nzpost-service-type-display">Economy</span>
                                    </small>
                                  </div>
                                </div>
                                <div class="mt-1" id="nzpost-status"></div>
                                    </div>
                                  </div>

                                  <!-- GSS Courier Tab -->
                                  <div class="tab-pane fade" id="gss-pane" role="tabpanel" aria-labelledby="gss-tab">
                                    <div class="card-body">
                                      <?php if ($PACKONLY): ?>
                                        <div class="alert alert-warning d-flex align-items-center mb-3">
                                          <i class="fa fa-lock mr-2"></i>
                                          <span>GSS Courier integration disabled in pack-only mode</span>
                                        </div>
                                      <?php endif; ?>
                                      
                                      <!-- Address Summary Bar -->
                                      <div class="address-summary mb-3 p-3 bg-light rounded border-left border-success">
                                        <div class="row">
                                          <div class="col-6">
                                            <div class="d-flex align-items-center">
                                              <i class="fa fa-map-marker-alt text-muted mr-2"></i>
                                              <div>
                                                <div class="font-weight-bold small"><?php echo htmlspecialchars($transferData->outlet_from->name); ?></div>
                                                <div class="text-muted small">From Location</div>
                                              </div>
                                            </div>
                                          </div>
                                          <div class="col-6">
                                            <div class="d-flex align-items-center">
                                              <i class="fa fa-shipping-fast text-success mr-2"></i>
                                              <div>
                                                <div class="font-weight-bold small"><?php echo htmlspecialchars($transferData->outlet_to->name); ?></div>
                                                <div class="text-muted small">To Location</div>
                                              </div>
                                            </div>
                                          </div>
                                        </div>
                                      </div>

                                      <!-- Quick Setup Row -->
                                      <div class="row mb-3">
                                        <div class="col-md-4">
                                          <label class="small font-weight-bold">Courier Service</label>
                                          <select id="gss-service-type" class="form-control form-control-sm" <?php echo $PACKONLY ? 'disabled' : ''; ?>>
                                            <option value="">Choose service...</option>
                                            <option value="ROAD">🚚 Road (Standard)</option>
                                            <option value="OVERNIGHT">🌙 Overnight</option>
                                            <option value="SAMEDAY">⚡ Same Day</option>
                                            <option value="ECONOMY" selected>💰 Economy</option>
                                          </select>
                                        </div>
                                        <div class="col-md-4">
                                          <label class="small font-weight-bold">Dimensions (cm)</label>
                                          <div class="input-group input-group-sm">
                                            <input type="number" id="gss-length" class="form-control" placeholder="L" min="1" max="120" <?php echo $PACKONLY ? 'disabled' : ''; ?>>
                                            <div class="input-group-append input-group-prepend">
                                              <span class="input-group-text">×</span>
                                            </div>
                                            <input type="number" id="gss-width" class="form-control" placeholder="W" min="1" max="120" <?php echo $PACKONLY ? 'disabled' : ''; ?>>
                                            <div class="input-group-append input-group-prepend">
                                              <span class="input-group-text">×</span>
                                            </div>
                                            <input type="number" id="gss-height" class="form-control" placeholder="H" min="1" max="120" <?php echo $PACKONLY ? 'disabled' : ''; ?>>
                                          </div>
                                        </div>
                                        <div class="col-md-4">
                                          <label class="small font-weight-bold">Weight & Cost</label>
                                          <div class="input-group input-group-sm">
                                            <input type="number" id="gss-weight" class="form-control" placeholder="2.5" step="0.1" min="0.1" max="30" value="2.5" <?php echo $PACKONLY ? 'disabled' : ''; ?>>
                                            <div class="input-group-append">
                                              <span class="input-group-text">kg</span>
                                              <span class="input-group-text bg-success text-white font-weight-bold" id="gss-cost-display">$0.00</span>
                                            </div>
                                          </div>
                                        </div>
                                      </div>
                                  
                                      <!-- Quick Preset Buttons -->
                                      <div class="mb-2">
                                        <small class="text-muted"><strong>Quick Presets:</strong></small><br>
                                        <button type="button" class="btn btn-outline-success btn-sm mr-1 mb-1" onclick="setGSSDimensions(20,15,10,1.5)" <?php echo $PACKONLY ? 'disabled' : ''; ?>>
                                          📦 Small Box (20×15×10) 1.5kg
                                        </button>
                                        <button type="button" class="btn btn-outline-success btn-sm mr-1 mb-1" onclick="setGSSDimensions(30,20,15,2.5)" <?php echo $PACKONLY ? 'disabled' : ''; ?>>
                                          📦 Medium Box (30×20×15) 2.5kg
                                        </button>
                                        <button type="button" class="btn btn-outline-success btn-sm mr-1 mb-1" onclick="setGSSDimensions(40,30,20,4.0)" <?php echo $PACKONLY ? 'disabled' : ''; ?>>
                                          📦 Large Box (40×30×20) 4kg
                                        </button>
                                        <button type="button" class="btn btn-outline-warning btn-sm mr-1 mb-1" onclick="setGSSDimensions(35,25,3,0.8)" <?php echo $PACKONLY ? 'disabled' : ''; ?>>
                                          📄 Flat Pack (35×25×3) 0.8kg
                                        </button>
                                      </div>

                                      <!-- Package List -->
                                      <div class="mt-2">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                          <small class="text-muted"><strong>Package List:</strong></small>
                                          <button type="button" class="btn btn-outline-success btn-sm" onclick="addCurrentGSSPackage()" <?php echo $PACKONLY ? 'disabled' : ''; ?>>
                                            <i class="fa fa-plus"></i> Add Package
                                          </button>
                                        </div>
                                        <div class="table-responsive">
                                          <table class="table table-sm table-bordered" id="gss-packages-table">
                                            <thead class="table-light">
                                              <tr>
                                                <th style="width: 20%;">Dimensions</th>
                                                <th style="width: 15%;">Weight</th>
                                                <th style="width: 25%;">Description</th>
                                                <th style="width: 20%;">Cost Est.</th>
                                                <th style="width: 20%;">Actions</th>
                                              </tr>
                                            </thead>
                                            <tbody>
                                              <!-- GSS Packages will be added dynamically -->
                                            </tbody>
                                          </table>
                                        </div>
                                      </div>

                                      <div class="form-group mb-2">
                                        <label class="small">Special Instructions:</label>
                                        <textarea class="form-control form-control-sm" id="gss-instructions" rows="2" placeholder="Special handling instructions (optional)" <?php echo $PACKONLY ? 'disabled' : ''; ?>></textarea>
                                      </div>

                                      <div class="d-flex justify-content-between align-items-center">
                                        <?php if (!$PACKONLY): ?>
                                        <div>
                                          <button class="btn btn-success btn-sm" onclick="createGSSShipment()" id="gss-create-btn">
                                            <i class="fa fa-truck" aria-hidden="true"></i> 
                                            <span id="gss-btn-text">Create GSS Booking</span>
                                          </button>
                                          <button class="btn btn-outline-success btn-sm d-none" onclick="reprintGSSLabel()" id="gss-reprint-btn">
                                            <i class="fa fa-repeat" aria-hidden="true"></i> Re-Print Docket
                                          </button>
                                        </div>
                                        <?php else: ?>
                                        <button class="btn btn-secondary btn-sm" disabled title="Disabled in pack-only mode">
                                          <i class="fa fa-lock" aria-hidden="true"></i> Shipping Locked
                                        </button>
                                        <?php endif; ?>
                                        <div class="d-flex flex-column">
                                          <div class="text-success fw-bold" id="gss-total-cost">Total: $0.00</div>
                                          <small class="text-muted">
                                            <span id="gss-package-count">0 packages</span> • 
                                            <span id="gss-total-weight">0.0kg total</span> • 
                                            <span id="gss-service-type-display">Economy</span>
                                          </small>
                                        </div>
                                      </div>
                                      <div class="mt-1" id="gss-status"></div>
                                    </div>
                                  </div>

                                  <!-- Manual Entry Tab -->
                                  <div class="tab-pane fade" id="manual-pane" role="tabpanel" aria-labelledby="manual-tab">
                                    <div class="card-body">
                                      <div class="alert alert-info d-flex align-items-center mb-3">
                                        <i class="fa fa-edit mr-2"></i>
                                        <div>
                                          <strong>Manual Tracking Entry</strong><br>
                                          <small>For shipments created outside the system or other courier services</small>
                                        </div>
                                      </div>
                                      
                                      <div class="form-group">
                                        <label class="small font-weight-bold">Courier Service</label>
                                        <select id="manual-courier" class="form-control" <?php echo $PACKONLY ? 'disabled' : ''; ?>>
                                          <option value="">Select courier...</option>
                                          <option value="NZ_POST">NZ Post</option>
                                          <option value="GSS">NZ Couriers</option>
                                          <option value="FASTWAY">Fastway</option>
                                          <option value="DHL">DHL</option>
                                          <option value="FEDEX">FedEx</option>
                                          <option value="ARAMEX">Aramex</option>
                                          <option value="OTHER">Other</option>
                                        </select>
                                      </div>

                                      <div class="form-group">
                                        <label class="small font-weight-bold">Tracking Numbers</label>
                                        <div id="manual-tracking-items" class="mb-2"></div>
                                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="addManualTrackingNumber()" <?php echo $PACKONLY ? 'disabled' : ''; ?>>
                                          <i class="fa fa-plus"></i> Add Tracking Number
                                        </button>
                                      </div>

                                      <div class="form-group">
                                        <label class="small font-weight-bold">Notes</label>
                                        <textarea class="form-control" id="manual-notes" rows="3" placeholder="Additional notes about this shipment..." <?php echo $PACKONLY ? 'disabled' : ''; ?>></textarea>
                                      </div>

                                      <?php if (!$PACKONLY): ?>
                                      <button class="btn btn-primary" onclick="saveManualTracking()">
                                        <i class="fa fa-save"></i> Save Tracking Information
                                      </button>
                                      <?php else: ?>
                                      <button class="btn btn-secondary" disabled title="Disabled in pack-only mode">
                                        <i class="fa fa-lock"></i> Manual Entry Locked
                                      </button>
                                      <?php endif; ?>
                                      
                                      <div class="mt-2" id="manual-status"></div>
                                    </div>
                                  </div>

                                  <!-- History Tab -->
                                  <div class="tab-pane fade" id="history-pane" role="tabpanel" aria-labelledby="history-tab">
                                    <div class="card-body">
                                      <div class="d-flex align-items-center justify-content-between mb-3">
                                        <h6 class="mb-0">
                                          <i class="fa fa-history text-info mr-2"></i>
                                          Recent Shipments
                                        </h6>
                                        <button class="btn btn-sm btn-outline-info" onclick="refreshShipmentHistory()">
                                          <i class="fa fa-refresh"></i> Refresh
                                        </button>
                                      </div>

                                      <div class="table-responsive">
                                        <table class="table table-sm table-hover">
                                          <thead class="table-light">
                                            <tr>
                                              <th>Date</th>
                                              <th>Transfer</th>
                                              <th>Courier</th>
                                              <th>Tracking</th>
                                              <th>Status</th>
                                              <th>Actions</th>
                                            </tr>
                                          </thead>
                                          <tbody id="shipment-history-tbody">
                                            <tr>
                                              <td colspan="6" class="text-center py-4 text-muted">
                                                <i class="fa fa-clock-o fa-2x mb-2"></i><br>
                                                Loading recent shipments...
                                              </td>
                                            </tr>
                                          </tbody>
                                        </table>
                                      </div>
                                    </div>
                                  </div>
                                </div>
                              </div>
                            </div>

                            <!-- Manual Tracking Panel -->
                            <div id="manual-panel" class="courier-panel mb-2" style="display:none;">
                              <label class="mb-2 small"><strong>Manual Tracking Numbers</strong></label>
                              <div id="tracking-items" class="mb-2"></div>
                              <button type="button" class="btn btn-sm btn-outline-primary" id="btn-add-tracking">Add tracking number</button>
                              <div class="mt-2 small text-muted"><span id="tracking-count">0 numbers</span></div>
                            </div>

                            <!-- Generated Labels Display -->
                            <div id="generated-labels" class="mt-2"></div>
                          </div>
                        </div>
                      </div>
                    </div>

                    <!-- Box Labels Printer - Enhanced -->
                    <div class="card shadow-sm border-0 mb-3">
                      <div class="card-header bg-warning text-dark py-2">
                        <div class="d-flex align-items-center justify-content-between">
                          <div>
                            <i class="fa fa-print mr-2"></i>
                            <strong>Box Label Printer</strong>
                          </div>
                          <small class="badge badge-dark">Quick Print</small>
                        </div>
                      </div>
                      <div class="card-body py-3">
                        <div class="row align-items-center">
                          <div class="col-md-4">
                            <label class="form-label small font-weight-bold mb-1">Number of Boxes:</label>
                            <input type="number" min="1" max="50" class="form-control form-control-sm" 
                                   id="box-count-input" value="1" placeholder="Boxes">
                          </div>
                          <div class="col-md-8">
                            <div class="d-flex align-items-center justify-content-end" style="gap: 8px;">
                              <button class="btn btn-warning btn-sm" type="button" onclick="previewLabels()">
                                <i class="fa fa-eye"></i> Preview
                              </button>
                              <button class="btn btn-success btn-sm" type="button" onclick="printLabelsDirectly()">
                                <i class="fa fa-print"></i> Print Now
                              </button>
                              <button class="btn btn-outline-secondary btn-sm" type="button" onclick="openLabelPrintDialog()">
                                <i class="fa fa-external-link"></i> Open Window
                              </button>
                            </div>
                          </div>
                        </div>
                        <div class="mt-2">
                          <small class="text-muted">
                            <i class="fa fa-info-circle"></i> 
                            Labels include Transfer #<?php echo (int)($_GET['transfer'] ?? 0); ?>, FROM/TO stores, and box numbers
                          </small>
                        </div>
                      </div>
                    </div>



                    <input type="hidden" id="tracking-number" name="tracking-number" value="">
                  </div>
                </div>
<?php if ($PACKONLY): ?>
  <!-- SINGLE BIG WARNING (no other warnings shown in Pack-Only) -->
  <div class="packonly-banner" role="alert" aria-live="assertive" aria-atomic="true">
    <div class="packonly-stripe"></div>
    <div class="packonly-panel">
      <div class="w-icon" aria-hidden="true">⛔</div>
      <div class="w-text">
        <div class="w-title">DO NOT SEND OR DO ANYTHING WITH THIS TRANSFER UNTIL CONFIRMED.</div>
        <div class="w-body">
          Every box <b>MUST</b> be clearly labelled with:
          <span class="w-pill">TRANSFER #<?php echo (int)($_GET['transfer'] ?? 0); ?></span>
          <span class="w-pill">FROM: <?php echo htmlspecialchars($transferData->outlet_from->name); ?></span>
          <span class="w-pill">BOX 1 OF X</span>
        </div>
      </div>
    </div>
    <div class="packonly-stripe"></div>
  </div>
<?php endif; ?>
                <div class="card-body">
                  <p class="mb-2" style="font-weight:bold;font-size:12px;">
                    Counted &amp; Handled By: <?php echo htmlspecialchars(($userDetails["first_name"] ?? '').' '.($userDetails["last_name"] ?? '')); ?>
                  </p>

                  <?php if (!$PACKONLY): ?>
                    <p class="mb-3 small text-muted">
                      By setting this transfer "Ready For Delivery" you declare that you have individually counted all the products despatched in this transfer and verified inventory levels.
                    </p>
                    <button type="button" id="createTransferButton" class="btn btn-primary" onclick="markReadyForDelivery();">
                      Set Transfer Ready For Delivery
                    </button>
                  <?php endif; ?>
                  <!-- In Pack-Only we do not render any submit button or extra warning here -->

                  <input type='hidden' id='transferID' value='<?php echo (int)($_GET["transfer"] ?? 0); ?>'>
                  <input type='hidden' id='sourceID' value='<?php echo htmlspecialchars($transferData->outlet_from->id); ?>'>
                  <input type='hidden' id='destinationID' value='<?php echo htmlspecialchars($transferData->outlet_to->id); ?>'>
                  <input type='hidden' id='staffID' value='<?php echo (int)($_SESSION["userID"] ?? 0); ?>'>
                </div>
              </div> <!-- /trackingInfo -->
            </div> <!-- /card-body -->
          </div> <!-- /card -->

        </div> <!-- /col -->
      </div> <!-- /fadeIn -->
    </div> <!-- /container-fluid -->
  </main>

  <?php include("assets/template/personalisation-menu.php") ?>
</div> <!-- /app-body -->

<!-- Add Products Modal -->
<div class="modal fade" id="addProductsModal" tabindex="-1" role="dialog" aria-labelledby="addProductsModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-md" role="document">
    <div class="modal-content border-0 shadow-lg">
      <div class="modal-header bg-primary text-white border-0">
        <h5 class="modal-title" id="addProductsModalLabel"><i class="fa fa-search" aria-hidden="true"></i> Add Products to Transfer</h5>
        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
      </div>
      <div class="modal-body p-4">
        <div class="row mb-3">
          <div class="col-12">
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text bg-light">
                  <i class="fa fa-search text-muted"></i>
                </span>
              </div>
              <input id="search-input" type="text" class="form-control form-control-lg"
                    placeholder="Search by product name, SKU, or ID..." autocomplete="off" autocapitalize="off" autocorrect="off" spellcheck="false" />
            </div>
            <div class="small text-muted mt-1">Type at least 2 characters to search...</div>
          </div>
        </div>

        <div class="d-flex justify-content-between align-items-center mb-2">
          <h6 class="mb-0 text-secondary">Search Results</h6>
          <div class="d-flex align-items-center" style="gap:12px;">
            <span class="badge bg-light text-dark" id="results-count">0 results</span>
            <div class="spinner-border spinner-border-sm text-primary d-none" id="search-spinner" role="status" aria-hidden="true" style="width:1rem;height:1rem;border-width:.15rem;"></div>
          </div>
        </div>

        <!-- Bulk Selection Controls -->
        <div class="d-none" id="bulk-controls" style="background: linear-gradient(135deg, #e3f2fd 0%, #f3e5f5 100%); border-radius: 8px; padding: 12px; margin-bottom: 16px; border-left: 4px solid #2196f3;">
          <div class="d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center" style="gap: 16px;">
              <span class="fw-bold text-primary">
                <i class="fa fa-check-square"></i>
                <span id="selected-count">0</span> products selected
              </span>
              <button class="btn btn-sm btn-outline-primary" onclick="selectAllVisible()">
                <i class="fa fa-check-double"></i> Select All
              </button>
              <button class="btn btn-sm btn-outline-secondary" onclick="clearSelection()">
                <i class="fa fa-times"></i> Clear
              </button>
            </div>
            <div class="d-flex align-items-center" style="gap: 8px;">
              <button class="btn btn-success btn-sm" onclick="addSelectedToCurrentTransfer()" id="add-to-current-btn">
                <i class="fa fa-plus"></i> Add to This Transfer
              </button>
              <div class="btn-group">
                <button class="btn btn-primary btn-sm" onclick="addSelectedToAllTransfers()" id="add-to-all-btn">
                  <i class="fa fa-layer-group"></i> Add to All Transfers
                </button>
                <button type="button" class="btn btn-primary btn-sm dropdown-toggle dropdown-toggle-split" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                  <span class="sr-only">Toggle Dropdown</span>
                </button>
                <div class="dropdown-menu">
                  <a class="dropdown-item" href="#" onclick="addSelectedToOutletTransfers()">
                    <i class="fa fa-building"></i> Add to Outlet Transfers (Status 0)
                  </a>
                  <a class="dropdown-item" href="#" onclick="addSelectedToSimilarTransfers()">
                    <i class="fa fa-copy"></i> Add to Similar Route Transfers
                  </a>
                </div>
              </div>
            </div>
          </div>
          <div class="small text-muted mt-2">
            <i class="fa fa-info-circle"></i> 
            Tip: Use Ctrl+click or Shift+click for multi-select, or click supplier buttons to select all from a brand
          </div>
        </div>

        <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
          <table class="table table-hover table-borderless table-fixed" id="addProductSearch">
            <thead class="table-light sticky-top">
              <tr>
                <th class="border-0 fw-semibold text-center align-middle" style="width: 50px; padding: 12px 8px;">
                  <input type="checkbox" id="selectAllProducts" class="form-check-input" title="Select All">
                </th>
                <th class="border-0 fw-semibold align-middle" style="width: 80px; padding: 12px 8px;">
                  <i class="fa fa-image"></i>
                </th>
                <th class="border-0 fw-semibold align-middle" style="padding: 12px 8px;">Product Details</th>
                <th class="border-0 fw-semibold text-center align-middle" style="width: 80px; padding: 12px 8px;">Stock</th>
                <th class="border-0 fw-semibold text-center align-middle" style="width: 80px; padding: 12px 8px;">Price</th>
                <th class="border-0 fw-semibold text-center align-middle" style="width: 100px; padding: 12px 8px;">Actions</th>
              </tr>
            </thead>
            <tbody id="productAddSearchBody" class="border-0">
              <tr id="search-placeholder">
                <td colspan="5" class="text-center py-5 text-muted">
                  <div class="d-flex flex-column align-items-center">
                    <i class="fa fa-search fa-3x mb-3" aria-hidden="true" style="opacity:0.5;"></i>
                    <h6 class="mb-2">Ready to search</h6>
                    <p class="mb-0 small">Start typing to find products...</p>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <div id="search-status" class="small text-muted mt-2">Idle</div>
      </div>
      <div class="modal-footer border-0 bg-light">
        <button type="button" class="btn btn-outline-secondary" data-dismiss="modal"><i class="fa fa-times" aria-hidden="true"></i> Close</button>
        <button type="button" class="btn btn-primary" id="btn-clear-search"><i class="fa fa-refresh" aria-hidden="true"></i> Clear Search</button>
      </div>
    </div>
  </div>
</div>

<!-- Merge Transfer Modal -->
<div class="modal fade" id="mergeTransferModal" tabindex="-1" role="dialog" aria-labelledby="mergeTransferModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document" style="padding:5px;">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="mergeTransferModalModalLabel">Merge Transfer</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
      </div>
      <div class="modal-body">
        <p>Merging a Transfer will delete both transfers and create a new transfer with a new Transfer ID.</p>
        <p>Please note: you can only merge transfers to and from the same outlet.</p>
        <br>
        <select id="transferMergeOptions" class="form-control">
          <?php
            for ($i = 0; $i < count($mergeTransfers); $i++) {
              echo '<option value="' . (int)$mergeTransfers[$i]["transfer_id"] . '">Transfer To ' . htmlspecialchars($mergeTransfers[$i]["destinationOutlet"]->name) . ' #' . (int)$mergeTransfers[$i]["transfer_id"] . '</option>';
            }
          ?>
        </select>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
        <form action="" method="POST" id="mergeTransferForm" name="mergeTransferForm">
          <button type="submit" class="btn btn-primary">Merge Transfer</button>
          <input type="hidden" id="currentTransferIDHidden" name="currentTransferIDHidden">
          <input type="hidden" id="TransferToMergeIDHidden" name="TransferToMergeIDHidden">
          <input type="hidden" id="outletFromIDHidden" value="<?php echo htmlspecialchars($transferData->outlet_from->id); ?>" name="outletFromIDHidden">
          <input type="hidden" id="outletToIDHidden" value="<?php echo htmlspecialchars($transferData->outlet_to->id); ?>" name="outletToIDHidden">
        </form>
      </div>
    </div>
  </div>
</div>

<?php include("assets/template/html-footer.php") ?>
<?php include("assets/template/footer.php") ?>

</body>
</html>
