<?php
/**
 * receive-stock-transfer.php
 * ------------------------------------------------------------
 * Destination-side receiving UI for stock transfers (DB-only).
 *
 * This version:
 * - CONTAINS all receive logic inline (no external wrappers for receive).
 * - Updates ONLY the database (no Vend API calls, no webhooks/queues).
 * - Supports Partial + Complete:
 *    • Partial → stamps partial receiver + timestamp, leaves status open.
 *    • Complete → sets status=2, micro_status='DESTINATION_RECEIVED'.
 * - Keeps UI/UX: scanner mode, local drafts, validations, compact summary.
 * - Optional summary helpers used if available (compute_* / build_*).
 *
 * Notes:
 * - Lines are merged by product_id before applying idempotent updates.
 * - new_total_at_destination = current on-hand at destination (if known) + counted.
 * - "current on-hand" is read from local vend_inventory (best-effort).
 */

include("assets/functions/config.php");

/* ---------- Unified AJAX handler (single action only) ---------- */ 
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  header('Content-Type: application/json; charset=utf-8');

  try { $userRow = requireLoggedInUser(); } catch (Throwable $e) {
    http_response_code(401);
    echo json_encode(['success'=>false,'error'=>'Not logged in. Please sign in and try again.'], JSON_UNESCAPED_SLASHES);
    exit;
  }

  // Allowed actions — removed any Vend/webhook actions
  $allowed = [
    'receiveTransfer',
    'addUnexpectedProduct',
    'removeProductFromTransfer',
    'searchForProduct',
    'revertBackToDraft',
  ];
  $present = array_values(array_filter($allowed, static fn($k)=>isset($_POST[$k])));

  if (count($present) !== 1) {
    http_response_code(400);
    echo json_encode(['success'=>false,'error'=>'Provide exactly one action'], JSON_UNESCAPED_SLASHES);
    exit;
  }

  $action = $present[0];
  $uid = (int)($_SESSION['userID'] ?? 0);

  try {
    // Dispatch action
    switch ($action) {
      case 'receiveTransfer': {
        // Parse payload
        $p = json_decode($_POST['receiveTransfer'] ?? '[]', true) ?: [];
        $tid   = (int)($p['_transferID'] ?? 0);
        $src   = (string)($p['_sourceID'] ?? '');
        $dst   = (string)($p['_destinationID'] ?? '');
        $notes = trim((string)($p['_transferNotes'] ?? ''));
        $lines = is_array($p['_products'] ?? null) ? $p['_products'] : [];

        // Merge duplicates by productID: keep max qtySent; accumulate non-delivered counts; delivered lines override
        $merged = [];
        foreach ($lines as $ln) {
          $pid = (string)($ln['productID'] ?? '');
          if ($pid === '') { continue; }
          $qtySent = (int)($ln['qtySent'] ?? 0);
          $qrRaw   = $ln['qtyReceived'] ?? null; // may be '' for blank
          $qtyReceived = null;
          if ($qrRaw !== null && $qrRaw !== '' && preg_match('/^\d+$/', (string)$qrRaw)) {
            $qtyReceived = (int)$qrRaw;
          }
          $beenDelivered = (int)($ln['beenDelivered'] ?? 0) === 1;

          if (!isset($merged[$pid])) {
            $merged[$pid] = ['qtySent' => 0, 'qtyReceived' => null, 'beenDelivered' => false];
          }
          $merged[$pid]['qtySent'] = max((int)$merged[$pid]['qtySent'], $qtySent);
          if ($beenDelivered) {
            $merged[$pid]['qtyReceived'] = $qtyReceived;
            $merged[$pid]['beenDelivered'] = true;
          } elseif ($qtyReceived !== null) {
            $merged[$pid]['qtyReceived'] = (int)(($merged[$pid]['qtyReceived'] ?? 0) + $qtyReceived);
          }
        }

        $out = [
          'success'   => false,
          'updated'   => 0,
          'skipped'   => 0,
          'warnings'  => [],
          'pending'   => 0,
          'completed' => false,
          'confidence'=> 0,
          'ai_summary'=> ''
        ];
        $warn = function(string $msg) use (&$out){ $out['warnings'][] = $msg; };
        $esc  = fn($s) => mysqli_real_escape_string($con, $s);

        mysqli_begin_transaction($con);
        try {
          // Lock header row
          $hdrSQL = "
            SELECT transfer_id, status
            FROM stock_transfers
            WHERE transfer_id={$tid} AND deleted_at IS NULL
            FOR UPDATE
          ";
          $hdrRes = $con->query($hdrSQL);
          $hdr    = $hdrRes ? $hdrRes->fetch_object() : null;
          if (!$hdr) { mysqli_rollback($con); http_response_code(404); echo json_encode(['success'=>false,'error'=>'Transfer not found'], JSON_UNESCAPED_SLASHES); break; }

          // Apply per-product updates
          foreach ($merged as $pid => $rec) {
            $pidEsc   = $esc($pid);

            // Planned across active duplicates
            $planSQL  = "
              SELECT SUM(COALESCE(qty_transferred_at_source,0)) AS planned_sum
              FROM stock_products_to_transfer
              WHERE transfer_id={$tid} AND product_id='{$pidEsc}' AND deleted_at IS NULL
              FOR UPDATE
            ";
            $planRes  = $con->query($planSQL);
            $planRow  = $planRes ? $planRes->fetch_object() : null;
            $planned  = (int)($planRow->planned_sum ?? 0);
            $inScope  = ($planned > 0) || ($rec['qtyReceived'] !== null); // unexpected lines allowed if counted

            // Pending detection: non-delivered in-scope with blank received
            if (!$rec['beenDelivered'] && $inScope && $rec['qtyReceived'] === null) {
              $out['pending']++;
              continue;
            }

            // Validate numeric
            if ($rec['qtyReceived'] !== null && !is_int($rec['qtyReceived'])) {
              $warn("{$pid}: non-integer received; skipped");
              $out['skipped']++;
              continue;
            }

            // No count provided on an actionable row → skip (warning already covered by pending)
            if ($rec['qtyReceived'] === null) {
              $warn("{$pid}: no count provided; skipped");
              $out['skipped']++;
              continue;
            }

            $qty = (int)$rec['qtyReceived'];

            // Best-effort current on-hand at destination
            $invSQL = "
              SELECT COALESCE(inventory_level, 0) AS lvl
              FROM vend_inventory
              WHERE product_id='{$pidEsc}' AND outlet_id='{$esc($dst)}'
              LIMIT 1
            ";
            $invRes = $con->query($invSQL);
            $invRow = $invRes ? $invRes->fetch_object() : null;
            $onHandDest = (int)($invRow->lvl ?? 0);
            $newTotal   = $onHandDest + $qty;

            // Idempotent update across all active dupes for this pid
            $updSQL = "
              UPDATE stock_products_to_transfer
              SET qty_counted_at_destination = {$qty},
                  new_total_at_destination   = {$newTotal}
              WHERE transfer_id = {$tid}
                AND product_id  = '{$pidEsc}'
                AND deleted_at IS NULL
            ";
            if (!$con->query($updSQL)) {
              $warn("{$pid}: DB update failed (".$con->error.")");
              $out['skipped']++;
              continue;
            }
            $out['updated']++;
          }

          // Append notes (preserve existing)
          if ($notes !== '') {
            $noteSQL = "
              UPDATE stock_transfers
                 SET completed_notes = CONCAT(
                       COALESCE(completed_notes,''),
                       CASE WHEN COALESCE(completed_notes,'')='' THEN '' ELSE '\n' END,
                       '".$esc($notes)."'
                     )
               WHERE transfer_id = {$tid}
                 AND deleted_at   IS NULL
               LIMIT 1
            ";
            $con->query($noteSQL);
          }

          // Determine pending lines (ACTIVE)
          $pendingSQL = "
            SELECT COUNT(*) AS pending
            FROM stock_products_to_transfer
            WHERE transfer_id = {$tid}
              AND deleted_at IS NULL
              AND qty_counted_at_destination IS NULL
              AND (unexpected_product_added = 1
                   OR (qty_transferred_at_source IS NOT NULL AND qty_transferred_at_source > 0))
          ";
          $pr = $con->query($pendingSQL);
          $pendingRow = $pr ? $pr->fetch_object() : null;
          $hasPending = ((int)($pendingRow->pending ?? 0)) > 0;

          if ($hasPending) {
            // Mark partial receiver + timestamp; keep status as-is (open)
            $con->query("
              UPDATE stock_transfers
                 SET transfer_partially_received_by_user = ".(int)$uid.",
                     transfer_partially_received_timestamp = NOW()
               WHERE transfer_id = {$tid}
                 AND deleted_at IS NULL
               LIMIT 1
            ");
            $out['completed'] = false;
          } else {
            // Flip to completed
            $con->query("
              UPDATE stock_transfers
                 SET status  = 2,
                     micro_status = 'DESTINATION_RECEIVED',
                     transfer_received_by_user = ".(int)$uid.",
                     recieve_completed = COALESCE(recieve_completed, NOW()),
                     transfer_partially_received_by_user = NULL,
                     transfer_partially_received_timestamp = NULL
               WHERE transfer_id = {$tid}
                 AND deleted_at IS NULL
               LIMIT 1
            ");
            $out['completed'] = true;
          }

          // Audit (best-effort)
          try {
            if (function_exists('createLog')) {
              createLog($uid, $hasPending ? 'Transfer Destination: Partially received' : 'Transfer Destination: Completed', $tid);
            }
          } catch (Throwable $e) {}

          mysqli_commit($con);
          $out['success'] = true;
          $out['pending'] = (int)($pendingRow->pending ?? 0);

          // ---- Build compact HTML summary for the modal (server-truth) ----
          try {
            $transferData = getTransferData($tid, true);
            if ($transferData) {
              if (function_exists('compute_receive_stats_from_object') && function_exists('build_receive_summary_html')) {
                $stats       = compute_receive_stats_from_object($transferData);
                $summaryHtml = build_receive_summary_html($stats, $transferData);
                $out['ai_summary'] = $summaryHtml ?: '';
                if (isset($stats['confidence'])) $out['confidence'] = (int)$stats['confidence'];
                if (!isset($out['pending']) && isset($stats['pending'])) $out['pending'] = (int)$stats['pending'];
                if ($summaryHtml !== '' && function_exists('save_transfer_summary_json')) {
                  save_transfer_summary_json($con, $tid, $summaryHtml);
                }
              } else {
                $from = htmlspecialchars($transferData->outlet_from->name ?? '');
                $to   = htmlspecialchars($transferData->outlet_to->name   ?? '');
                $tot  = is_array($transferData->products) ? count($transferData->products) : 0;
                $pend = (int)$out['pending'];
                $conf = (int)$out['confidence'];
                $summaryHtml =
                  '<div class="review-box">'.
                    '<div class="review-title"><i class="fa fa-bullhorn"></i> Receive Summary</div>'.
                    '<div class="review-sub">'.$from.' → '.$to.'</div>'.
                    '<div class="review-row"><strong>Lines:</strong> '.$tot.' &nbsp;•&nbsp; '.
                    '<strong>Pending:</strong> '.$pend.' &nbsp;•&nbsp; '.
                    '<strong>Confidence:</strong> '.$conf.'%</div>'.
                  '</div>';
                $out['ai_summary'] = $summaryHtml;
                if ($summaryHtml !== '' && function_exists('save_transfer_summary_json')) {
                  save_transfer_summary_json($con, $tid, $summaryHtml);
                }
              }
            }
          } catch (Throwable $e) {
            error_log('[receiveTransfer summary] '.$e->getMessage());
          }

          echo json_encode($out, JSON_UNESCAPED_SLASHES);
          break;

        } catch (Throwable $e) {
          mysqli_rollback($con);
          error_log('[receiveTransfer INLINE] '.$e->getMessage().' trace='.$e->getTraceAsString());
          $dev = (isset($_GET['dev']) && $_GET['dev']) || (isset($_POST['dev']) && $_POST['dev']);
          http_response_code(500);
          echo json_encode(['success'=>false,'error'=> $dev ? ('Receive failed: '.$e->getMessage()) : 'Receive failed. Please correct highlighted lines and retry.'], JSON_UNESCAPED_SLASHES);
          break;
        }
      }

      /* ---------- Keep your existing helper endpoints ---------- */
      case 'addUnexpectedProduct': {
        $p = json_decode($_POST['addUnexpectedProduct'] ?? '[]', true) ?: [];
        $res = addProductToTransferAfterReceived_wrapped(
          (string)($p['productID'] ?? ''),
          (int)($p['transferID'] ?? 0),
          $uid
        );
        if (!($res['success'] ?? false)) { http_response_code(400); }
        echo json_encode($res, JSON_UNESCAPED_SLASHES);
        break;
      }

      case 'removeProductFromTransfer': {
        $p = json_decode($_POST['removeProductFromTransfer'] ?? '[]', true) ?: [];
        $res = removeProductFromTransfer_wrapped(
          (string)($p['productID'] ?? ''),
          (int)($p['transferID'] ?? 0),
          $uid
        );
        if (!($res['success'] ?? false)) { http_response_code(400); }
        echo json_encode($res, JSON_UNESCAPED_SLASHES);
        break;
      }

      case 'searchForProduct': {
        $p = json_decode($_POST['searchForProduct'] ?? '[]', true) ?: [];
        $res = searchForProductByOutlet_wrapped(
          (string)($p['keyword'] ?? ''),
          (string)($p['outletID'] ?? ''),
          50
        );
        if (!($res['success'] ?? false)) { http_response_code(400); }
        echo json_encode($res, JSON_UNESCAPED_SLASHES);
        break;
      }

      case 'revertBackToDraft': {
        $p = json_decode($_POST['revertBackToDraft'] ?? '[]', true) ?: [];
        $res = revertTransferBackToDraft_wrapped((int)($p['transferID'] ?? 0), $uid);
        if (!($res['success'] ?? false)) { http_response_code(400); }
        echo json_encode($res, JSON_UNESCAPED_SLASHES);
        break;
      }
    }
  } catch (Throwable $e) {
    error_log('[receive-stock-transfer] Fatal: '.$e->getMessage().' trace='.$e->getTraceAsString());
    http_response_code(500);
    echo json_encode(['success'=>false,'error'=>'Unexpected server error. Please try again.'], JSON_UNESCAPED_SLASHES);
  }
  exit;
}

/* ---------- GET phase (view) ---------- */
if (!isset($_GET['transfer'])) { header('Location: index.php'); exit; }
$transferIdParam = (int)$_GET['transfer'];
$transferData = getTransferData($transferIdParam, true); // lines must already filter deleted_at IS NULL
if (!$transferData) { header('Location: index.php'); exit; }

/**
 * Self-heal header micro_status mismatches on load.
 * If header says status=1 but micro_status indicates completed, move it back to a pending micro_state.
 */
try {
  if ((int)($transferData->status ?? 0) === 1 && (($transferData->micro_status ?? '') === 'DESTINATION_RECEIVED')) {
    mysqli_query($con, "UPDATE stock_transfers
                        SET micro_status = 'DESTINATION_PENDING'
                        WHERE transfer_id = ".(int)$transferData->transfer_id."
                          AND deleted_at IS NULL
                        LIMIT 1");
    $transferData->micro_status = 'DESTINATION_PENDING';
  }
} catch (Throwable $e) { error_log('[receive-stock-transfer] micro_status heal: '.$e->getMessage()); }

// Auto-complete reconcile (no refetch)
$auto = reconcileTransferCompletionOnLoad_inplace($transferData, (int)($_SESSION['userID'] ?? 0));

$isComplete = ((int)($transferData->status ?? 0) === 2);
$bodyClass  = "app header-fixed sidebar-fixed aside-menu-fixed sidebar-lg-show" . ($isComplete ? " rx-readonly" : "");

include("assets/template/html-header.php");
include("assets/template/header.php");

/* Helpers */
function full_name_any($x): string {
  if (is_array($x))  return trim(($x['first_name'] ?? '').' '.($x['last_name'] ?? ''));
  if (is_object($x)) return trim(($x->first_name ?? '').' '.($x->last_name ?? ''));
  return '';
}

$userDetails = null;
try { if (!empty($_SESSION['userID'])) { $userDetails = getUserDetails((int)$_SESSION['userID']); } } catch(Throwable $e){}

/* Dummy/test transfer banner check */
$isDummy = false;
if (isset($transferData->source_module) && is_string($transferData->source_module)) {
  $isDummy = (strcasecmp($transferData->source_module, 'TESTING') === 0);
}
if (!$isDummy && isset($transferData->transferNotes) && is_string($transferData->transferNotes)) {
  $isDummy = (stripos($transferData->transferNotes, 'DUMMY') !== false || stripos($transferData->transferNotes, 'TEST') !== false);
}

/* Partial-in-progress banner: some counted, some pending */
$hasCounted = false; $hasPending = false;
if (isset($transferData->products) && is_array($transferData->products)) {
  foreach ($transferData->products as $p) {
    $counted = isset($p->qty_counted_at_destination) && $p->qty_counted_at_destination !== null;
    if ($counted) $hasCounted = true;
    $planned = (int)($p->qty_transferred_at_source ?? 0);
    $unexpected = (int)($p->unexpected_product_added ?? 0);
    $inScope = ($planned > 0) || ($unexpected === 1);
    if ($inScope && !$counted) $hasPending = true;
  }
}
$partialInProgress = (!$isComplete && $hasCounted && $hasPending);
?>
<body class="<?php echo $bodyClass; ?>">
  <input type="hidden" id="outletFromIDHidden" value="<?php echo htmlspecialchars($transferData->outlet_from->id); ?>">
  <div class="app-body">
    <?php include("assets/template/sidemenu.php"); ?>
    <main class="main">
      <ol class="breadcrumb">
        <li class="breadcrumb-item">Home</li>
        <li class="breadcrumb-item"><a href="#">Admin</a></li>
        <li class="breadcrumb-item active">Receive Stock Transfer</li>
        <li class="breadcrumb-menu d-md-down-none"><?php include('assets/template/quick-product-search.php'); ?></li>
      </ol>

      <div class="container-fluid">
        <div class="animated fadeIn">
          <div class="col">

            <?php if ($partialInProgress): ?>
              <div class="alert alert-warning d-print-none" role="alert" style="border:2px solid #f59e0b;background:#fff7ed;">
                <strong>Partial shipment in progress ⚠️</strong> — Some items have been received, and some are still pending. You can continue receiving what's arrived now and finish the rest later.
              </div>
            <?php endif; ?>

            <?php if ($isComplete): ?>
              <div class="alert alert-success d-print-none" role="alert" style="border:1px solid #a7f3d0;background:#ecfdf5;">
                <strong>Transfer Completed (Read-Only)</strong>
                <?php if (!empty($auto['autoCompleted'])): ?>
                  — Auto-completed on load because all destination counts were already entered.
                <?php endif; ?>
              </div>
              <script>try{localStorage.removeItem('transfer_receive_<?php echo (int)$transferData->transfer_id; ?>');}catch(e){}</script>
            <?php endif; ?>

            <?php if ($isDummy): ?>
              <div class="alert alert-danger" role="alert" style="border:2px solid #b91c1c;background:#fdecec;">
                <strong>TESTING TRANSFER</strong> — This job is marked as DUMMY/TEST.
              </div>
            <?php endif; ?>

            <div class="card">
              <!-- Card header -->
              <div class="card-header d-flex align-items-center justify-content-between">
                <div>
                  <h4 class="card-title mb-0">
                    Receive Transfer #<?php echo (int)$transferIdParam; ?>
                    <?php if ($isComplete): ?><span class="ro-chip">READ-ONLY</span><?php endif; ?>
                  </h4>
                  <div class="small text-muted">
                    From <strong><?php echo htmlspecialchars($transferData->outlet_from->name); ?></strong> →
                    To <strong><?php echo htmlspecialchars($transferData->outlet_to->name); ?></strong>
                  </div>
                </div>

                <!-- Options + Scan status -->
                <div class="d-flex align-items-center" style="gap:10px;">
                  <button type="button" class="btn btn-link p-0 ml-1" id="scannerInfoBtn" style="font-size:1.1em;" title="Scanner Info" aria-label="Scanner Info">
                    <i class="fa fa-info-circle text-info"></i>
                  </button>
                  <button type="button" id="scannerChip" class="chip-square chip-muted" title="Toggle scanner (barcode wedge)">
                    Scanner OFF
                  </button>

                  <div class="btn-group">
                    <button class="btn btn-secondary dropdown-toggle" type="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                      Options <span id="scan-status-dot" class="scan-dot" aria-hidden="true"></span>
                    </button>
                    <div class="dropdown-menu dropdown-menu-right p-0" style="min-width:320px;">
                      <div class="menu-sub px-3 py-2">Quick actions</div>

                      <button class="dropdown-item d-flex align-items-start py-2" type="button" data-toggle="modal" data-target="#addProductsModal">
                        <i class="fa fa-plus-square mr-2 mt-1"></i>
                        <div>
                          Add products
                          <div class="hint">Add unexpected items (receive-side)</div>
                        </div>
                      </button>

                      <button class="dropdown-item d-flex align-items-start py-2" type="button" id="menu-scanner-toggle">
                        <i class="fa fa-barcode mr-2 mt-1"></i>
                        <div>
                          <span id="menu-scanner-label">Enable Scanner Mode</span>
                          <div class="hint">Scan barcode → find line &amp; focus/increment</div>
                        </div>
                      </button>

                      <div class="dropdown-item py-2">
                        <label class="mb-0 d-flex align-items-center" style="gap:8px;cursor:pointer;">
                          <input type="checkbox" id="menu-scan-inc"/>
                          <span>Scan to increment</span>
                          <span class="hint">+1 counted when a line is found</span>
                        </label>
                      </div>

                      <div class="menu-divider"></div>
                      <div class="menu-sub px-3 py-2">Counting &amp; view</div>

                      <button class="dropdown-item d-flex align-items-start py-2" type="button" id="menu-clear">
                        <i class="fa fa-eraser mr-2 mt-1"></i>
                        <div>Clear all counts<div class="hint">Reset every counted field to blank</div></div>
                      </button>

                      <div class="dropdown-item d-flex align-items-start py-2" id="menu-print">
                        <i class="fa fa-print mr-2 mt-1"></i><div>Print</div>
                      </div>
                      <div class="dropdown-item d-flex align-items-start py-2" id="menu-readonly">
                        <i class="fa fa-eye mr-2 mt-1"></i><div>Open Read-Only</div>
                      </div>

                      <div class="menu-divider"></div>
                      <div class="dropdown-item d-flex align-items-start py-2 text-danger" id="menu-revert">
                        <i class="fa fa-undo mr-2 mt-1"></i><div>Revert to Draft [WARNING]</div>
                      </div>
                    </div>
                  </div>
                </div>
              </div><!-- /card-header -->

              <!-- Card body -->
              <div class="card-body transfer-data">
                <!-- Top stats -->
                <div class="d-flex align-items-end justify-content-between mb-2">
                  <div class="small text-muted">
                    Packed by: <?php echo htmlspecialchars(full_name_any($transferData->transfer_created_by_user ?? [])); ?>
                  </div>
                  <div class="topstats">
                    <span class="t-chip">Items <strong id="stat-items"><?php echo count($transferData->products); ?></strong></span>
                    <span class="t-chip">Planned <strong id="plannedTotal">0</strong></span>
                    <span class="t-chip">Counted <strong id="countedTotal">0</strong></span>
                    <span class="t-chip">Diff <strong id="diffTotal">0</strong></span>
                  </div>
                </div>

                <!-- Filter toolbar -->
                <div class="filter-toolbar d-print-none mb-2">
                  <div class="filter-toolbar__label">View</div>
                  <div class="filter-toolbar__set">
                    <button type="button" id="qf-mismatches" class="ft-btn ft-btn--warn"><i class="fa fa-exclamation-circle"></i> Mismatches</button>
                    <button type="button" id="qf-warnings"  class="ft-btn ft-btn--danger"><i class="fa fa-flag"></i> Warnings</button>
                    <button type="button" id="qf-already"   class="ft-btn ft-btn--ok"><i class="fa fa-check-circle"></i> Already</button>
                    <button type="button" id="qf-reset"     class="ft-btn ft-btn--neutral"><i class="fa fa-undo"></i> Reset</button>
                  </div>
                  <div class="filter-toolbar__aside">
                    <span class="kbd-hint">Shortcut:</span> <span class="kbd">Ctrl+S</span> save draft
                  </div>
                </div>

                <!-- Transfer table -->
                <div class="table-responsive">
                  <table class="table table-sm table-bordered table-striped" id="transfer-table">
                    <thead class="thead-light">
                      <tr>
                        <th style="width:36px;"></th>
                        <th>Name</th>
                        <th style="width:120px;">Counted Qty</th>
                        <th style="width:120px;">Planned Qty</th>
                        <th>Outlet Source</th>
                        <th>Outlet Destination</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php
                        $nameEscFrom = htmlspecialchars($transferData->outlet_from->name);
                        $nameEscTo   = htmlspecialchars($transferData->outlet_to->name);
                        foreach ($transferData->products as $i => $prod) {
                          $planned = (int)($prod->qty_transferred_at_source ?? 0);
                          // show rows either planned>0 OR unexpected line
                          if ($planned <= 0 && (int)($prod->unexpected_product_added ?? 0) !== 1) continue;

                          $already  = (isset($prod->qty_counted_at_destination) && $prod->qty_counted_at_destination !== null);
                          $attrsStr = "data-productdelivered='".($already?1:0)."' data-planned='{$planned}'";
                          $pid      = htmlspecialchars($prod->product_id);
                          $pname    = htmlspecialchars($prod->product_name ?? '');
                          $val      = $already ? (int)$prod->qty_counted_at_destination : '';
                          $disabled = $already ? "disabled" : "";
                          $skuSafe  = htmlspecialchars($prod->sku ?? '');

                          echo "<tr {$attrsStr} data-sku='{$skuSafe}'>";
                          echo "  <td class='text-center align-middle'>
                                    <img src='assets/img/remove-icon.png' title='Remove Product' style='cursor:pointer;height:13px;".(($already || !((int)($prod->unexpected_product_added ?? 0)===1 || (int)($prod->staff_added_product ?? 0)===1)) ? "display:none" : "")."' onclick='uiRemoveProduct(this)'>
                                    <input type='hidden' class='productID' value='{$pid}'>
                                  </td>";

                          echo "  <td>{$pname}";
                          if ((int)($prod->unexpected_product_added ?? 0) === 1) echo " <span class='badge badge-warning'>Added at Receive</span>";
                          if ($already) echo " <span class='badge badge-success'>Already Received</span>";
                          echo "  </td>";

                          echo "  <td class='counted-td'>
                                    <input type='number' min='0' {$disabled}
                             oninput='enforceBounds(this);syncPrintValue(this);validateCell(this);recomputeTotals();confidenceTick();localSave();'

                                      value='{$val}' style='width:6em;'>
                                    <span class='counted-print-value d-none d-print-inline'>".($val === '' ? '0' : (int)$val)."</span>
                                  </td>";

                          echo "  <td class='planned'>{$planned}</td>";
                          echo "  <td>{$nameEscFrom}</td>";
                          echo "  <td>{$nameEscTo}</td>";
                          echo "</tr>";
                        }
                      ?>
                    </tbody>
                  </table>
                </div>

                <!-- Confidence + discrepancy digest -->
                <div class="row d-print-none mt-2">
                  <div class="col-md-8">
                    <div class="card" id="conf-card">
                      <div class="card-body py-2">
                        <div class="d-flex align-items-center">
                          <div class="mr-2">Confidence:</div>
                          <div class="progress flex-grow-1" style="height:10px;">
                            <div id="conf-bar" class="progress-bar" role="progressbar" style="width:0%"></div>
                          </div>
                          <div id="conf-text" class="ml-2 small text-muted">0%</div>
                        </div>
                        <div class="small text-muted mt-1">Confidence increases as more lines match planned and fewer lines are flagged.</div>
                      </div>
                    </div>
                  </div>
                  <div class="col-md-4">
                    <div class="card"><div class="card-body py-2">
                      <div class="small font-weight-bold">Discrepancies</div>
                      <div id="disc-summary" class="small text-muted">No issues yet.</div>
                    </div></div>
                  </div>
                </div>

              </div><!-- /card-body -->
            </div><!-- /card -->

            <!-- Exceptions & Declaration card -->
            <div class="card mt-3 d-print-none">
              <div class="card-header">
                <strong>Exceptions &amp; Declaration</strong>
              </div>
              <div class="card-body">
                <div id="serverWarnings" class="alert alert-warning" style="display:none;"></div>

                <label for="notesForTransfer" class="mb-1">Notes &amp; Discrepancies</label>
                <textarea id="notesForTransfer" class="form-control" rows="3"
                  placeholder="Record carton IDs, courier refs, damage, shortages/overages, or explanations for mismatches."
                  oninput="localSave()"></textarea>

                <div class="legal-block mt-3">
                  <div class="legal-title">Recipient’s Statutory Declaration</div>
                  <div class="legal-text">
                    By clicking <em>Declare &amp; Commit</em>, I, the receiving staff member, solemnly declare that I have personally verified the physical
                    quantities for this transfer and that the counts entered are true and correct to the best of my knowledge.
                    I understand that this declaration is recorded against my user account, date and time stamped, and may be
                    audited. I further acknowledge that intentional miscounting, negligence, or failure to investigate material
                    discrepancies may constitute misconduct and can trigger internal investigation and disciplinary action.
                  </div>
                </div>

                <div class="d-flex align-items-center mt-3" style="gap:8px;">
                  <button type="button" class="btn btn-primary" id="btn-submit">
                    Declare &amp; Commit
                  </button>
                  <button type="button" class="btn btn-outline-secondary" id="btn-print">
                    Print
                  </button>
                </div>
                <div class="small text-muted mt-2">
                  Counted by: <?php echo htmlspecialchars(full_name_any($userDetails ?? [])); ?>
                </div>

                <!-- Hidden ids -->
                <input type="hidden" id="transferID" value="<?php echo (int)$transferIdParam; ?>">
                <input type="hidden" id="sourceID" value="<?php echo htmlspecialchars($transferData->outlet_from->id); ?>">
                <input type="hidden" id="destinationID" value="<?php echo htmlspecialchars($transferData->outlet_to->id); ?>">
              </div>
            </div>

          </div><!-- /col -->
        </div><!-- /fadeIn -->
      </div><!-- /container -->
    </main>

    <?php include("assets/template/personalisation-menu.php"); ?>
  </div><!-- /app-body -->

  <!-- Add Products Modal -->
  <div class="modal fade" id="addProductsModal" tabindex="-1" role="dialog" aria-labelledby="addProductsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
      <div class="modal-content border-0 shadow-lg">
        <div class="modal-header bg-primary text-white border-0">
          <h5 class="modal-title" id="addProductsModalLabel"><i class="fa fa-search"></i> Add Products</h5>
          <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
        </div>
        <div class="modal-body p-3">
          <div class="input-group mb-2">
            <div class="input-group-prepend"><span class="input-group-text"><i class="fa fa-search"></i></span></div>
            <input type="text" class="form-control" id="search-input" placeholder="Search by name, SKU…" autocomplete="off">
            <div class="input-group-append"><button class="btn btn-outline-secondary" type="button" id="btn-clear-search">Clear</button></div>
          </div>
          <div class="small text-muted mb-2">Searching source outlet: <strong><?php echo htmlspecialchars($transferData->outlet_from->name); ?></strong></div>
          <div class="table-responsive" style="max-height:420px;overflow:auto;">
            <table class="table table-hover table-sm" id="addProductSearch">
              <thead class="thead-light">
                <tr>
                  <th>Product</th>
                  <th class="text-center" style="width:90px;">Stock</th>
                  <th class="text-center" style="width:120px;">Actions</th>
                </tr>
              </thead>
              <tbody id="productAddSearchBody">
                <tr id="search-placeholder">
                  <td colspan="3" class="text-center text-muted py-5">
                    <i class="fa fa-search fa-2x mb-2" style="opacity:.4;"></i><br>
                    Start typing to find products…
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
          <div class="small text-muted" id="search-status">Idle</div>
        </div>
        <div class="modal-footer border-0 bg-light">
          <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Scanner Info Modal -->
  <div class="modal fade" id="scannerInfoModal" tabindex="-1" role="dialog" aria-labelledby="scannerInfoModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document" style="max-width:700px;">
      <div class="modal-content border-0 shadow">
        <div class="modal-header bg-info text-white py-2">
          <h6 class="modal-title" id="scannerInfoModalLabel" style="font-size:1.35em;">
            <i class="fa fa-barcode"></i> Barcode Scanner – Instructions & Limitations
          </h6>
          <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
        </div>
        <div class="modal-body py-3" style="font-size:1.05em;">
          <div class="small" style="font-size:1em;">
            <strong>How to Use Barcode Scanner Mode</strong>
            <ul class="pl-3 mb-2">
              <li>Connect a barcode scanner or use a keyboard wedge (USB / Bluetooth).</li>
              <li>Ensure <strong>Scanner Mode</strong> is ON (green dot in header).</li>
              <li>If <em>Scan to increment</em> is enabled, scanning adds <em>+1</em> to the matching line.</li>
              <li>If disabled, scanning focuses the matching line for manual entry.</li>
              <li>If no matching line, you’ll be prompted to add as an unexpected product.</li>
            </ul>
            <strong>Notes</strong>
            <ul class="pl-3 mb-0">
              <li>Scanners should send an <em>Enter</em> after each scan for best results.</li>
              <li>Rapid/partial scans may be ignored; scan cleanly and wait for confirmation.</li>
              <li>Mobile/Bluetooth scanners may need special config.</li>
            </ul>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Receive Success Modal -->
  <div class="modal fade" id="receiveSuccessModal" tabindex="-1" role="dialog" aria-labelledby="receiveSuccessTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
      <div class="modal-content border-0 shadow-lg">
        <div class="modal-header">
          <h5 class="modal-title" id="receiveSuccessTitle">Transfer Saved ✔</h5>
          <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
        </div>
        <div class="modal-body p-4">
          <div class="d-flex align-items-center" style="gap:24px;">
            <div style="font-size:42px;line-height:1;">🎉</div>
            <div>
              <div class="h5 mb-1">Great work!</div>
              <div class="text-muted">Database updated. Here’s a quick summary.</div>
            </div>
          </div>
          <div class="row mt-3">
            <div class="col-md-3"><div class="card mb-2"><div class="card-body py-3 text-center"><div class="text-muted small">Items</div><div class="h4 mb-0" id="sx-items">0</div></div></div></div>
            <div class="col-md-3"><div class="card mb-2"><div class="card-body py-3 text-center"><div class="text-muted small">Planned</div><div class="h4 mb-0" id="sx-planned">0</div></div></div></div>
            <div class="col-md-3"><div class="card mb-2"><div class="card-body py-3 text-center"><div class="text-muted small">Counted</div><div class="h4 mb-0" id="sx-counted">0</div></div></div></div>
            <div class="col-md-3"><div class="card mb-2"><div class="card-body py-3 text-center"><div class="text-muted small">Confidence</div><div class="h4 mb-0" id="sx-conf">0%</div></div></div></div>
          </div>
          <div class="small text-muted mt-2" id="sx-note"></div>
        </div>
        <div class="modal-footer bg-light border-0 d-flex justify-content-between">
          <button type="button" class="btn btn-success" id="sx-done">Go to Dashboard</button>
        </div>
      </div>
    </div>
  </div>

  <style>
:root{
  --vs-indigo:#4f46e5; --vs-sky:#0ea5e9; --vs-emerald:#10b981; --vs-amber:#f59e0b; --vs-rose:#e11d48;
  --vs-surface:#f7f8fb; --vs-border:#e6e9f0; --vs-muted:#6b7280; --vs-ink:#111827;
}
.card { border:1px solid var(--vs-border); border-radius:4px; }
.card-title { color: var(--vs-ink); }
.badge { border-radius:3px; }
.ro-chip{ display:inline-block; margin-left:8px; font-size:.72rem; font-weight:700; padding:2px 6px; border:1px solid #d6d9e0; border-radius:4px; background:#f8fafc; color:#334155; }

/* Header scan dot */
.scan-dot{ display:inline-block;width:8px;height:8px;background:#9aa0a6;margin-left:8px;vertical-align:middle; }

/* Menu styling */
.menu-sub{ padding:8px 12px; font-size:.75rem; text-transform:uppercase; letter-spacing:.04em; color:#6c757d; background:#f8f9fa; border-bottom:1px solid #eef1f4; }
.menu-divider{ height:1px; background:#eef1f4; margin:4px 0; }
.dropdown-item .hint{ font-size:.8rem; color:#6c757d; line-height:1.2; }

/* Chips */
.chip-square{ display:inline-block; padding:.35rem .6rem; border:1px solid #d6d9e0; border-radius:4px; background:#fff; color:#374151; font-weight:600; line-height:1; cursor:pointer; user-select:none; }
.chip-muted{ background:#fff; }

/* Top-right counters */
.topstats .t-chip{ display:inline-block; padding:.2rem .5rem; border:1px solid #d6d9e0; background:#fff; color:#374151; border-radius:4px; font-size:.85rem; margin-left:6px; }
.topstats strong{ font-weight:700; }

/* Filter toolbar */
.filter-toolbar{ display:flex; align-items:center; gap:10px; border:1px solid var(--vs-border); border-radius:4px; background:#fff; padding:6px 10px; }
.filter-toolbar__label{ font-size:.78rem; font-weight:600; color:#556070; text-transform:uppercase; letter-spacing:.04em; }
.filter-toolbar__set{ display:flex; gap:6px; flex-wrap:wrap; }
.filter-toolbar__aside{ margin-left:auto; font-size:.8rem; color:#6b7280; display:flex; align-items:center; gap:.35rem; }
.kbd{ font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, "Liberation Mono", monospace; font-size:.72rem; padding:.05rem .3rem; border:1px solid #d7dbe4; border-bottom:2px solid #c9cfda; border-radius:3px; background:#fff; color:#495057; }
.ft-btn{ border:1px solid #d1d5db; background:#fff; border-radius:4px; padding:.25rem .5rem; font-size:.82rem; line-height:1.2; cursor:pointer; }
.ft-btn:hover{ background:#f9fafb; }
.ft-btn--warn{ border-color:#f59e0b; background:#fffbeb; color:#92400e; }
.ft-btn--danger{ border-color:#ef4444; background:#fef2f2; color:#7f1d1d; }
.ft-btn--ok{ border-color:#10b981; background:#ecfdf5; color:#065f46; }

/* Confidence bar */
#conf-card .progress{ background:#eef2ff; }
#conf-bar{ transition: width .25s ease; background-image: linear-gradient(90deg,var(--vs-indigo), var(--vs-sky)); }

/* Input warnings */
.warning-input { border-color:#ffc107 !important; background:#fff8e1 !important; }
.input-warning { font-size: 11px; line-height:1.2; padding:2px 4px; border-radius:3px; margin-top:2px; }

.review-box{
  border:1px solid #e6e9f0; border-left:4px solid #4f46e5;
  background:#f8faff; border-radius:6px; padding:12px 14px;
}
.review-title{ font-weight:700; color:#111827; margin-bottom:4px; display:flex; align-items:center; gap:8px; }
.review-title .fa{ color:#4f46e5; }
.review-sub{ color:#6b7280; font-size:.9rem; margin-bottom:6px; }
.review-row{ color:#374151; font-size:.95rem; margin:3px 0; }
.review-list{ margin-top:6px; }
.rs-item{ color:#065f46; font-size:.92rem; margin-left:18px; }
.rs-item .fa{ color:#10b981; margin-right:6px; }
.rs-more{ color:#6b7280; margin-left:18px; font-size:.9rem; }

/* Legal block */
.legal-block{ border:1px solid #dfe3ee; background:#fafbff; border-radius:4px; padding:12px; }
.legal-title{ font-weight:700; margin-bottom:4px; }
.legal-text{ font-size:.92rem; color:#374151; }

/* Submit hover */
#btn-submit{ box-shadow: 0 0 0 rgba(0,0,0,0); transition: box-shadow .15s ease; }
#btn-submit:hover{ box-shadow: 0 6px 18px rgba(79,70,229,.18); }

/* Scanner ON tint */
body.scan-on .scan-dot{ background:#10b981 !important; }

/* Toasts */
#toast-container .toast{ border-radius:4px; }

/* AI summary block */
.ai-summary { padding:12px; border:1px solid #e6e9f0; border-radius:6px; background:#f8faff; font-size:0.95rem; line-height:1.4; white-space:pre-wrap; }

/* Read-only via class (status=2) */
body.rx-readonly .transfer-data input[type="number"],
body.rx-readonly .transfer-data textarea,
body.rx-readonly .transfer-data .btn,
body.rx-readonly #btn-submit,
body.rx-readonly #scannerChip,
body.rx-readonly #menu-scanner-toggle,
body.rx-readonly #menu-scan-inc { pointer-events:none; opacity:.6; }

/* Print */
.print-chip{ display:inline-block; border:1px solid #999; padding:1px 6px; border-radius:2px; margin-right:3mm; }
@media print {
  @page { size: A4 portrait; margin: 10mm; }
  .app-header, header, nav, .sidebar, .aside-menu, .breadcrumb,
  .personalisation-menu, .app-footer, footer,
  .btn, .dropdown, .dropdown-menu, .alert, .filter-toolbar,
  #addProductsModal, #receiveSuccessModal, #toast-container { display:none !important; }

  .app-body, .main, .container-fluid, .card, .card-body, .table-responsive {
    padding:0 !important; margin:0 !important; border:0 !important; width:100% !important; background:#fff !important;
  }
  input, select, textarea { display:none !important; }
  .counted-print-value { display:inline !important; }
  table { border-collapse: collapse !important; width:100% !important; font-size:9.5pt; }
  th, td { border:1px solid #ccc !important; padding:4px 6px !important; vertical-align:top; }
  thead { display:table-header-group !important; }
  tr { page-break-inside: avoid; }
}
  </style>

  <?php include("assets/template/html-footer.php"); ?>
  <?php include("assets/template/footer.php"); ?>

<!-- Blocking overlay used during save -->
<div id="rxReceiveOverlay"
     style="display:none;position:fixed;inset:0;background:rgba(17,24,39,.88);z-index:5000;align-items:center;justify-content:center;flex-direction:column;color:#fff;">
  <div class="spinner-border text-light" style="width:3rem;height:3rem;"></div>
  <div class="h5 mt-3">Processing...Please Wait</div>
</div>

<script>
(function(){
  'use strict';

  $.ajaxSetup({ headers: { 'X-Requested-With': 'XMLHttpRequest' }, cache:false, timeout: 30000 });

  /* ---------- Toasts ---------- */
  function showToast(message, type='info'){
    try{
      if ($('#toast-container').length===0){
        $('body').append('<div id="toast-container" style="position:fixed;top:20px;right:20px;z-index:9999;" aria-live="polite" aria-atomic="true"></div>');
      }
      const id='t-'+Date.now();
      const cls= type==='success'?'bg-success': type==='warning'?'bg-warning': type==='error'?'bg-danger':'bg-info';
      const html=`<div id="${id}" class="toast align-items-center text-white ${cls} border-0 p-2 px-3" role="alert" style="margin-bottom:10px;display:none;min-width:260px;">
        <div class="d-flex"><div class="toast-body">${message}</div></div></div>`;
      $('#toast-container').append(html);
      const $t=$('#'+id); $t.fadeIn(120); setTimeout(()=> $t.fadeOut(180, ()=> $t.remove()), 3600);
    }catch(e){ alert(message); }
  }
  window._ui = { showToast };

  /* ---------- Print fill ---------- */
  function fillPrintHeaderNow(){
    const get = id => document.getElementById(id);
    (get('printItemsCount')||{}).textContent = (document.getElementById('stat-items')?.textContent||'0');
    (get('printPlannedTotal')||{}).textContent = (document.getElementById('plannedTotal')?.textContent||'0');
    (get('printCountedTotal')||{}).textContent = (document.getElementById('countedTotal')?.textContent||'0');
    (get('printDiffTotal')||{}).textContent = (document.getElementById('diffTotal')?.textContent||'0');
  }
  window.addEventListener('beforeprint', fillPrintHeaderNow);
  window.fillPrintHeaderNow = fillPrintHeaderNow;

  /* ---------- Local draft ---------- */
  const transferId = $('#transferID').val();
  const draftKey = 'transfer_receive_'+transferId;
  function localSave(){
    const data = { notes: $('#notesForTransfer').val(), lines: [] };
    $('#transfer-table tbody tr').each(function(){
      const pid = $(this).find('.productID').val();
      const inp = $(this).find('input[type="number"]')[0];
      const counted = inp && inp.value!=='' ? String(inp.value) : '';
      data.lines.push({ pid, counted });
    });
    try{ localStorage.setItem(draftKey, JSON.stringify(data)); }catch(e){}
  }
  function localLoadIfAny(){
    const raw = localStorage.getItem(draftKey); if (!raw) return;
    let data=null; try{ data=JSON.parse(raw);}catch{}
    if (!data) return;
    if (data.notes) $('#notesForTransfer').val(data.notes);
    if (Array.isArray(data.lines)){
      data.lines.forEach(l=>{
        if (!l || !l.pid) return;
        $('#transfer-table tbody tr').each(function(){
          const pid = $(this).find('.productID').val();
          if (pid===l.pid){
            const inp=$(this).find('input[type="number"]')[0];
            if (inp && !inp.disabled && (l.counted!=='')){ inp.value=l.counted; syncPrintValue(inp); validateCell(inp); }
          }
        });
      });
    }
    recomputeTotals(); confidenceTick(); showToast('Draft restored','success');
  }
  window.localSave = localSave;

  /* ---------- Validation / totals / confidence ---------- */
  function syncPrintValue(input){ $(input).siblings('.counted-print-value').text(input.value || '0'); }
  function enforceBounds(input){
    try {
      const v = parseInt(input.value || '0', 10);
      if (!Number.isFinite(v) || v<0) input.value='0';
      if (v>1000000) input.value='1000000';
    } catch(e) {
      input.value = '0';
    }
  }
  function _smartCellWarnings(raw, planned){
    const out={level:null,msg:[]}; if (raw==='') return out;
    if (!/^\d+$/.test(raw)){ out.level='danger'; out.msg.push('Non-integer value'); return out; }
    const v=parseInt(raw,10);
    if (/^0+[1-9]/.test(raw) && planned>0 && v>=10*planned){ out.msg.push(`Leading zeros → ${v} vs planned ${planned}`); }
    if (v>=11 && v<=99){ const s=String(v); if (s[0]===s[1]){ const single=parseInt(s[0],10); if (planned>0 && Math.abs(single-planned)<Math.abs(v-planned)){ out.msg.push(`Doubled digit? Entered ${v} (maybe ${single})`); } } }
    if (planned>0 && v===10*planned){ out.msg.push(`10× planned (${planned}→${v})`); }
    if ((planned>0 && v>50*planned) || v>500){ out.msg.push(`Unrealistic: ${v}`); }
    if (out.msg.length) out.level='warning';
    return out;
  }
  function validateCell(input){
    const $row=$(input).closest('tr');
    const planned=parseInt($row.find('.planned').text()||'0',10);
    const raw=String(input.value||'').trim();
    $row.removeClass('table-warning table-danger table-success'); $(input).removeClass('warning-input'); $row.find('.input-warning').remove();
    if (raw==='') return;
    if (!/^\d+$/.test(raw)){ $row.addClass('table-danger'); return; }
    const val=parseInt(raw,10); if (planned===val){ $row.addClass('table-success'); }
    const warn=_smartCellWarnings(raw,planned);
    if (warn.level){ $(input).addClass('warning-input'); $row.addClass('table-warning'); $row.find('.counted-td').append(`<div class="input-warning text-warning small mt-1">${warn.msg.join('<br>')}</div>`); }
  }
  function recomputeTotals(){
    let planned=0,counted=0,warned=0,errors=0,matches=0;
    $('#transfer-table tbody tr').each(function(){
      const p=parseInt($(this).attr('data-planned')||'0',10)||0; planned+=p;
      const inp=$(this).find('input[type="number"]')[0];
      const raw=inp?String(inp.value||'').trim():'';
      if (/^\d+$/.test(raw)){ const v=parseInt(raw,10); counted+=v; if (v===p) matches++; }
      if ($(this).hasClass('table-warning')) warned++;
      if ($(this).hasClass('table-danger'))  errors++;
    });
    $('#plannedTotal').text(planned.toLocaleString());
    $('#countedTotal').text(counted.toLocaleString());
    $('#diffTotal').text((counted-planned).toLocaleString());
    const disc=[];
    if (errors>0) disc.push(`${errors} error${errors>1?'s':''}`);
    if (warned>0) disc.push(`${warned} warn${warned>1?'s':''}`);
    if (matches>0) disc.push(`${matches} match${matches>1?'es':''}`);
    $('#disc-summary').text(disc.length?disc.join(' • '):'No issues yet.');
  }
  function confidenceTick(){
    let total=0,ok=0,warn=0,err=0;
    $('#transfer-table tbody tr').each(function(){ total++; if ($(this).hasClass('table-success')) ok++; if ($(this).hasClass('table-warning')) warn++; if ($(this).hasClass('table-danger')) err++; });
    let score=0; if (total>0){ score=Math.max(0,Math.min(100,Math.round((ok/total)*100 - warn*3 - err*7))); }
    $('#conf-bar').css('width',score+'%'); $('#conf-text').text(score+'%');
  }
  window.enforceBounds=enforceBounds; window.validateCell=validateCell; window.syncPrintValue=syncPrintValue;

  /* ---------- API helper (tolerant of 400 JSON) ---------- */
  function postTop(field, payload){
    return $.ajax({
      url: window.location.pathname + window.location.search,
      type: 'POST',
      dataType: 'json',
      data: { [field]: JSON.stringify(payload || {}), dev: 1 }
    }).catch(xhr => {
      try { return JSON.parse(xhr.responseText); } catch { throw xhr; }
    });
  }

  /* ---------- Collect lines (merge dupes; respect delivered) ---------- */
  function collectLines(){
    const map = new Map(); // pid -> {qtySent, qtyReceived, beenDelivered}
    $('#transfer-table tbody tr').each(function(){
      const pid = $(this).find('.productID').val();
      if (!pid) return;
      const planned = parseInt($(this).attr('data-planned')||'0',10)||0;
      const delivered = $(this).attr('data-productdelivered')==='1';
      const inp = $(this).find('input[type="number"]')[0];
      const raw = inp ? String(inp.value||'').trim() : '';
      const counted = /^\d+$/.test(raw) ? parseInt(raw,10) : (delivered ? planned : null);

      const cur = map.get(pid) || { qtySent: 0, qtyReceived: null, beenDelivered: false };
      cur.qtySent = Math.max(cur.qtySent, planned);
      if (delivered) {
        cur.qtyReceived = counted;
        cur.beenDelivered = true;
      } else if (counted !== null) {
        cur.qtyReceived = (cur.qtyReceived ?? 0) + counted;
      }
      map.set(pid, cur);
    });

    const arr = [];
    for (const [pid, v] of map.entries()){
      const inScope = (v.qtySent > 0) || (v.qtyReceived !== null);
      if (!inScope) continue;
      arr.push({
        productID: pid,
        qtyReceived: v.qtyReceived === null ? '' : String(v.qtyReceived),
        qtySent: v.qtySent,
        beenDelivered: v.beenDelivered ? 1 : 0
      });
    }
    return arr;
  }

  /* ---------- Preflight validation ---------- */
  function preflightValidate(){
    const issues = [];
    let editableRows = 0;
    let blanks = 0;

    $('#transfer-table tbody tr').each(function(){
      const delivered  = $(this).attr('data-productdelivered')==='1';
      const planned    = parseInt($(this).attr('data-planned')||'0',10)||0;
      const unexpected = $(this).find('.badge.badge-warning').length>0;
      const inScope    = !delivered && (planned > 0 || unexpected);
      const $inp       = $(this).find('input[type="number"]');

      if (!inScope || !$inp.length) return;

      editableRows++;
      const raw = String($inp.val()||'').trim();
      if (raw === '') { blanks++; }
      else if (!/^\d+$/.test(raw)) {
        issues.push('Non-integer entry on a pending line.');
      }
    });

    if (blanks > 0) {
      issues.unshift(`${blanks} line(s) are pending with blank counts.`);
    }
    return { ok: issues.length===0, issues, editableRows, blanks };
  }

  /* ---------- Clear counts ---------- */
  function clearAllCounts(){
    if (!confirm('Clear all counted quantities?')) return;
    $('#transfer-table tbody tr').each(function(){
      const inp=$(this).find('input[type="number"]')[0];
      if (inp && !inp.disabled){ inp.value=''; syncPrintValue(inp); validateCell(inp); }
    });
    recomputeTotals(); confidenceTick(); localSave();
  }
  window.clearAllCounts = clearAllCounts;

  /* ---------- Submit receive (DB-only) ---------- */
  let _submitting=false;
  async function submitReceive(){
    if (_submitting) return; _submitting = true;
    const $btn = $('#btn-submit');
    $('#serverWarnings').hide().empty();

    const pf = preflightValidate();
    if (!pf.ok){
      $('#serverWarnings').html('<strong>Fix these before saving:</strong><ul style="margin:6px 0 0 18px;">'
        + pf.issues.map(s=>`<li>${s}</li>`).join('') + '</ul>').show();
      _ui.showToast('Please correct highlighted entries', 'warning');
      $btn.prop('disabled', false).text('Declare & Commit');
      _submitting=false; return;
    }
    if (pf.editableRows === 0) {
      _ui.showToast('Nothing to declare — all rows are already received.', 'warning');
      $btn.prop('disabled', false).text('Declare & Commit');
      _submitting=false; return;
    }

    const lines = collectLines();

    // Determine partial: any in-scope non-delivered that is blank
    let partial = false;
    $('#transfer-table tbody tr').each(function(){
      const delivered=$(this).attr('data-productdelivered')==='1';
      const planned=parseInt($(this).attr('data-planned')||'0',10)||0;
      const unexpected = $(this).find('.badge.badge-warning').length>0;
      const inScope = delivered ? false : (planned>0 || unexpected);
      const $inp=$(this).find('input[type="number"]');
      if (inScope && $inp.length && String($inp.val()||'').trim()===''){ partial=true; }
    });

    const payload = {
      _products: lines,
      _transferID: $('#transferID').val(),
      _sourceID: $('#sourceID').val(),
      _destinationID: $('#destinationID').val(),
      _transferNotes: $('#notesForTransfer').val(),
      _isPartial: partial
    };

    try {
      $btn.prop('disabled', true).text('Saving…');
      $('#rxReceiveOverlay').fadeIn(120);
      const res = await postTop('receiveTransfer', payload);

      if (!res || res.success !== true) {
        const list = Array.isArray(res?.warnings) && res.warnings.length
          ? '<strong>Some lines were not processed:</strong><ul style="margin:6px 0 0 18px;">'
            + res.warnings.map(w=>`<li>${String(w).replace(/</g,'&lt;')}</li>`).join('')
            + '</ul><div class="mt-2">Please correct the highlighted items above, then click Declare again.</div>'
          : '';
        if (list) { $('#serverWarnings').html(list).show(); }
        if (res && res.error) {
          $('#serverWarnings').append(
            '<div class="mt-2"><strong>Server:</strong> '
            + $('<div>').text(res.error).html()
            + '</div>'
          ).show();
        }
        throw new Error((res && (res.error || res.message)) || 'Save failed');
      }

      // Tiles from page truth
      $('#sx-items').text(document.getElementById('stat-items')?.textContent || '0');
      $('#sx-planned').text(document.getElementById('plannedTotal')?.textContent || '0');
      $('#sx-counted').text(document.getElementById('countedTotal')?.textContent || '0');
      if (typeof res.confidence === 'number') { $('#sx-conf').text(res.confidence + '%'); }
      else { $('#sx-conf').text(document.getElementById('conf-text')?.textContent || '0%'); }

      // Title + note by completion
      if (res.completed === true) {
        $('#receiveSuccessTitle').text('Transfer Completed ✔');
      } else {
        $('#receiveSuccessTitle').text('Partial Receive Saved ⚠️');
      }
      $('#sx-note').html(res.ai_summary || 'Database updated.');

      $('#receiveSuccessModal').modal({backdrop:'static', keyboard:false});
      confettiBurst(140);
      $('#sx-done').off('click').on('click', ()=> window.location.href = 'index.php');

    } catch (err) {
      $('#sx-note').html(
        `<div class="ai-summary">${err?.message || 'Some items remain pending. Please correct and try again.'}</div>`
      );
      $('#receiveSuccessTitle').text('Save Failed');
      $('#receiveSuccessModal').modal({backdrop:'static', keyboard:false});
      $('#sx-done').off('click').on('click', ()=> window.location.reload());
      console.error('receiveTransfer error:', err);
      _ui.showToast(err?.message || 'Failed to save','error');
    } finally {
      $('#rxReceiveOverlay').fadeOut(120);
      $btn.prop('disabled', false).text('Declare & Commit');
      _submitting = false;
    }
  }
  $('#btn-submit').on('click', submitReceive);

  async function revertToDraft(){
    if (!confirm('Revert this transfer back to Draft? This clears destination counts.')) return;
    try{
      const tid=$('#transferID').val();
      const res=await postTop('revertBackToDraft',{ transferID: tid });
      if (!res || res.success!==true) throw new Error(res?.error||'Revert failed');
      _ui.showToast('Reverted to Draft','success'); setTimeout(()=>location.reload(),600);
    }catch(e){ _ui.showToast(e?.message||'Revert failed','error'); }
  }
  $('#menu-revert').on('click', revertToDraft);

  /* ---------- Add/remove line ---------- */
  async function addUnexpectedProduct(productId, productSku) {
    const tid = $('#transferID').val();
    const payload = { productID: String(productId), transferID: tid };

    try {
      const res = await postTop('addUnexpectedProduct', payload);
      if (!res || res.success !== true) throw new Error(res?.error || 'Add failed');

      if (res.reopened === true) {
        _ui.showToast('Transfer re-opened. Reloading…', 'info');
        location.reload();
        return;
      }

      if (res.message && /already present/i.test(res.message)) {
        const $row = $('#transfer-table tbody tr').filter(function () {
          return $(this).find('.productID').val() === String(productId);
        }).first();
        if ($row.length) {
          $row.addClass('table-info'); setTimeout(()=>$row.removeClass('table-info'), 800);
          const $inp = $row.find('input[type="number"]:not([disabled])').first();
          if ($inp.length) { $inp.focus().select(); }
        }
        _ui.showToast('Product already on transfer', 'info');
        return;
      }

      _ui.showToast('Product added to transfer', 'success');

      let $existing = $('#transfer-table tbody tr').filter(function () {
        return $(this).find('.productID').val() === String(productId);
      }).first();

      if ($existing.length) {
        $existing.removeClass('d-none table-warning').attr('data-productdelivered', '0');
        const $inp = $existing.find('input[type="number"]');
        if ($inp.length && $inp.prop('disabled')) $inp.prop('disabled', false);
        $existing.addClass('table-info'); setTimeout(()=>$existing.removeClass('table-info'), 800);
      } else {
        const src = $('#transfer-table tbody tr:first td:nth-child(5)').text() || 'Source';
        const dst = $('#transfer-table tbody tr:first td:nth-child(6)').text() || 'Destination';
        const skuAttr = String(productSku || '').replace(/"/g, '&quot;');

        const newRow = `
          <tr data-productdelivered="0" data-planned="0" class="table-warning" data-sku="${skuAttr}">
            <td class="text-center align-middle">
              <img src="assets/img/remove-icon.png" title="Remove Product" style="cursor:pointer;height:13px;" onclick="uiRemoveProduct(this)">
              <input type="hidden" class="productID" value="${String(productId)}">
            </td>
            <td>Added product <span class="badge badge-warning">Added at Receive</span></td>
            <td class="counted-td">
              <input type="number" min="0"
                oninput="enforceBounds(this);localSave();validateCell(this);recomputeTotals();syncPrintValue(this);" value="" style="width:6em;">
              <span class="counted-print-value d-none">0</span>
            </td>
            <td class="planned">0</td>
            <td>${src}</td><td>${dst}</td>
          </tr>`;
        $('#transfer-table tbody').prepend(newRow);
      }

      if (typeof recomputeTotals === 'function') recomputeTotals();
      if (typeof confidenceTick === 'function') confidenceTick();
      if (typeof localSave === 'function') localSave();

    } catch (e) {
      _ui.showToast(e?.message || 'Add failed', 'error');
    }
  }
  async function uiRemoveProduct(imgBtn){
    const $tr=$(imgBtn).closest('tr'); const pid=$tr.find('.productID').val(); if (!pid) return;
    if (!confirm('Remove this product line?')) return;
    try{
      const res=await postTop('removeProductFromTransfer', { productID: pid, transferID: $('#transferID').val() });
      if (!res || res.success!==true) throw new Error(res?.error||'Remove failed');
      $tr.remove(); recomputeTotals(); confidenceTick(); localSave(); _ui.showToast('Line removed','success');
    }catch(e){ _ui.showToast(e?.message||'Remove failed','error'); }
  }
  window.addUnexpectedProduct=addUnexpectedProduct; window.uiRemoveProduct=uiRemoveProduct;

  /* ---------- Search modal ---------- */
  let searchTimer=null, searchCache=new Map();
  async function doSearch(q){
    $('#search-status').text('Searching…');
    try{
      const outId=$('#outletFromIDHidden').val();
      const cached=searchCache.get(q);
      const data=cached || await postTop('searchForProduct', { keyword:q, outletID:outId });
      if (!cached) searchCache.set(q,data);
      const items=(Array.isArray(data) ? data : (data?.data||[]));
      renderSearch(items,q);
    }catch{
      $('#productAddSearchBody').html(`<tr><td colspan="3" class="text-center text-danger py-4">Search failed. Check connection.</td></tr>`);
    }
    finally{ $('#search-status').text('Idle'); }
  }
  function renderSearch(list,q){
    if (!Array.isArray(list) || list.length===0){
      $('#productAddSearchBody').html(`<tr><td colspan="3" class="text-center text-muted py-4">No results for “${$('<div>').text(q).html()}”</td></tr>`); return;
    }
    let html=''; list.slice(0,50).forEach(p=>{
      const stock=parseInt(p.stock||0,10); const safeName=$('<div>').text(p.name||'Unnamed').html(); const safeSku=(p.sku||'').replace(/'/g,'&#39;');
      html += `<tr>
        <td>${safeName}${p.sku?`<div class="small text-muted">${safeSku}</div>`:''}</td>
        <td class="text-center">${isNaN(stock)?0:stock}</td>
        <td class="text-center"><button class="btn btn-sm btn-primary" onclick="addUnexpectedProduct('${String(p.id)}','${safeSku}')">Add</button></td>
      </tr>`;
    });
    $('#productAddSearchBody').html(html);
  }
  $('#addProductsModal').on('shown.bs.modal', function(){
    $('#search-input').val(''); $('#productAddSearchBody').html($('#search-placeholder')); $('#search-input').focus();
  });
  $('#btn-clear-search').on('click', function(){
    $('#search-input').val(''); $('#productAddSearchBody').html($('#search-placeholder')); $('#search-status').text('Idle'); $('#search-input').focus();
  });
  $('#search-input').on('input', function(){
    const q=String(this.value||'').trim(); clearTimeout(searchTimer);
    if (q.length<2){ $('#productAddSearchBody').html($('#search-placeholder')); $('#search-status').text('Type at least 2 characters'); return; }
    $('#search-status').text('Typing…'); searchTimer=setTimeout(()=>doSearch(q),260);
  });

  /* ---------- Keyboard: save draft ---------- */
  $(document).on('keydown', function(e){
    if (e.ctrlKey && e.key.toLowerCase()==='s'){ e.preventDefault(); localSave(); _ui.showToast('Draft saved','success'); }
  });

  /* ---------- Scanner Controller ---------- */
  (function(){
    const tableBody=document.querySelector('#transfer-table tbody');
    const scanDot=document.getElementById('scan-status-dot');
    const chip=document.getElementById('scannerChip');
    const label=document.getElementById('menu-scanner-label');
    const chkInc=document.getElementById('menu-scan-inc');
    const LS_SCAN='rx_scan_mode_'+transferId;
    const LS_INC='rx_scan_inc_'+transferId;

    let scannerOn = true;
    let scanInc   = true;
    try { scannerOn = (localStorage.getItem(LS_SCAN)==='1') || true; } catch {}
    try { scanInc   = (localStorage.getItem(LS_INC)==='1') || true; } catch {}

    function paint(){
      document.body.classList.toggle('scan-on', !!scannerOn);
      if (scanDot) scanDot.style.background = scannerOn ? '#10b981' : '#9aa0a6';
      if (label) label.textContent = scannerOn ? 'Disable Scanner Mode' : 'Enable Scanner Mode';
      if (chip){
        chip.textContent = scannerOn? 'Scanner ON' : 'Scanner OFF';
        chip.classList.toggle('chip-muted', !scannerOn);
      }
      if (chkInc) chkInc.checked = !!scanInc;
    }
    function persist(){
      try{ localStorage.setItem(LS_SCAN, scannerOn?'1':'0'); }catch{}
      try{ localStorage.setItem(LS_INC, scanInc ?'1':'0'); }catch{}
    }
    function setScanner(on){ scannerOn=!!on; persist(); paint(); try{ document.dispatchEvent(new CustomEvent('scanner:changed',{detail:{on:scannerOn}})); }catch{} return scannerOn; }
    function setIncrement(on){ scanInc=!!on; persist(); paint(); }

    function findRowByCode(code){
      if (!tableBody || !code) return null;
      const norm=String(code).trim(); if (!norm) return null;
      for (const r of tableBody.querySelectorAll('tr')){
        const pid=r.querySelector('.productID'); if (pid && pid.value===norm) return r;
      }
      const skuUp=norm.toUpperCase();
      for (const r of tableBody.querySelectorAll('tr')){
        const sku=(r.getAttribute('data-sku')||'').toUpperCase();
        if (sku && sku===skuUp) return r;
      }
      return null;
    }
    function activateRow(row){
      if (!row) return false;
      const inp=row.querySelector('input[type="number"]:not([disabled])'); if (!inp) return false;
      if (scanInc){
        const v=parseInt(inp.value||'0',10)||0; inp.value=String(v+1);
        enforceBounds(inp); syncPrintValue(inp); validateCell(inp); localSave(); recomputeTotals(); confidenceTick();
      }else{
        inp.focus(); inp.select();
      }
      row.classList.add('table-info'); setTimeout(()=>row.classList.remove('table-info'),800);
      return true;
    }

    let buf='', timer=null, lastTs=0;
    const SCAN_INTERVAL_MS=35, FINALIZE_DELAY_MS=80;
    function isTextInput(el){ return el && (el.tagName==='INPUT' || el.tagName==='TEXTAREA' || el.isContentEditable); }
    async function tryScanToAdd(code){
      _ui.showToast('Searching ‘'+code+'’…','info');
      const outId=$('#outletFromIDHidden').val();
      let res=null;
      try{
        res = await $.ajax({ url: window.location.pathname + window.location.search, type:'POST', dataType:'json',
                             data:{ searchForProduct: JSON.stringify({ keyword:String(code||''), outletID:String(outId||'') }) } });
      }catch{ _ui.showToast('Search failed. Check connection.','error'); return; }
      const list = Array.isArray(res)?res:(res&&res.data?res.data:[]);
      const exact = list.find(x => String(x.sku||'').toUpperCase()===String(code||'').toUpperCase());
      const candidate = exact || (list.length===1 ? list[0] : null);
      if (candidate){
        try{
          await addUnexpectedProduct(String(candidate.id), String(candidate.sku||''));
          setTimeout(()=>{ const row=findRowByCode(String(candidate.id)); if (row) activateRow(row); }, 150);
          _ui.showToast('Added ‘'+(candidate.name||'product')+'’','success');
        }catch(e){ _ui.showToast('Add failed: '+(e.message||'error'),'error'); }
      }else{
        $('#addProductsModal').modal('show');
        setTimeout(()=>{ $('#search-input').val(String(code||'')).trigger('input').focus(); }, 160);
        _ui.showToast('Select item to add for ‘'+code+'’','info');
      }
    }
    function finalizeScan(){
      if (timer){ clearTimeout(timer); timer=null; }
      const code=buf.trim(); buf=''; if (!code) return;
      const row=findRowByCode(code); if (row) activateRow(row); else tryScanToAdd(code);
    }
    function onKeyDown(e){
      if (!scannerOn) return;
      if (e.ctrlKey || e.altKey || e.metaKey) return;
      const t=e.target;
      if (isTextInput(t) && t.type==='number') return;

      const now=e.timeStamp || Date.now();
      const fast=(now-lastTs)<=SCAN_INTERVAL_MS; lastTs=now;

      if (e.key==='Enter'){ e.preventDefault(); finalizeScan(); return; }
      if (e.key && e.key.length===1 && /[0-9A-Za-z\-_]/.test(e.key)){
        if (!fast && buf.length>0) finalizeScan();
        buf += e.key;
        if (timer) clearTimeout(timer);
        timer = setTimeout(finalizeScan, FINALIZE_DELAY_MS);
      }
    }

    $('#menu-scanner-toggle').on('click', ()=> setScanner(!scannerOn));
    $('#menu-scan-inc').on('change', function(){ setIncrement(this.checked); });
    if (chip){ chip.addEventListener('click', ()=> setScanner(!scannerOn)); }
    document.addEventListener('keydown', onKeyDown, true);
    document.addEventListener('scanner:changed', paint);

    // default ON + increment ON for receiving
    scannerOn = true; scanInc = true; persist(); paint();
  })();

  /* ---------- Page init ---------- */
  $(document).ready(function(){
    localLoadIfAny();
    $('#transfer-table input[type="number"]').each(function(){ syncPrintValue(this); validateCell(this); });
    recomputeTotals(); confidenceTick();

    $('#btn-print').on('click', ()=>{ fillPrintHeaderNow(); window.print(); });

    // quick filters
    $('#qf-mismatches').on('click', function(e){
      e.preventDefault();
      $('#transfer-table tbody tr').each(function(){
        const p=parseInt($(this).attr('data-planned')||'0',10)||0;
        const v=parseInt($(this).find('input[type="number"]').val()||'0',10)||0;
        $(this).toggle(p!==v);
      });
    });
    $('#qf-warnings').on('click', function(e){
      e.preventDefault();
      $('#transfer-table tbody tr').each(function(){
        $(this).toggle($(this).hasClass('table-warning') || $(this).hasClass('table-danger'));
      });
    });
    $('#qf-already').on('click', function(e){
      e.preventDefault();
      $('#transfer-table tbody tr').each(function(){
        $(this).toggle($(this).attr('data-productdelivered')==='1');
      });
    });
    $('#qf-reset').on('click', function(e){ e.preventDefault(); $('#transfer-table tbody tr').show(); });

    // scanner info modal
    $('#scannerInfoBtn').on('click', function(e){ e.preventDefault(); $('#scannerInfoModal').modal('show'); });
  });

  /* ---------- Confetti ---------- */
  function confettiBurst(n=80){
    const colors=['r','b','g','y','p'];
    for (let i=0;i<n;i++){
      const el=document.createElement('div'); el.className='confetti '+colors[Math.floor(Math.random()*colors.length)];
      el.style.left=(5+Math.random()*90)+'vw'; el.style.opacity=1; el.style.transform=`rotate(${Math.random()*360}deg)`;
      document.body.appendChild(el);
      const dur=1200+Math.random()*1200; const dx=(Math.random()-.5)*200; const delay=Math.random()*160;
      setTimeout(()=>{ el.animate([{ transform:`translate(0,-20px) rotate(${Math.random()*360}deg)`, opacity:1 },
                                  { transform:`translate(${dx}px, 90vh) rotate(${720*Math.random()}deg)`, opacity:0 }],
                                  { duration:dur, easing:'cubic-bezier(.2,.7,.2,1)' }); setTimeout(()=>el.remove(),dur+80); }, delay);
    }
  }
  window.confettiBurst = confettiBurst;

  // Live recalc for ANY change to number inputs
  $('#transfer-table').on('input change', 'input[type="number"]', function(){
    try { syncPrintValue(this); validateCell(this); recomputeTotals(); confidenceTick(); localSave(); }
    catch(e){ console.warn('live-recalc failed', e); }
  });

})();
</script>

<script>
/* Tooltips (kept minimal) */
$(function(){ $('[data-toggle="tooltip"]').tooltip(); });
</script>
