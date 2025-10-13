<?php

/**
 * receive-juice-transfer.php
 * - Loads transfer (GET ?transfer=ID)
 * - Saves receive (POST juiceReceive)
 * - Updates CIS inventory immediately
 * - Queues Vend via VEND_UpdateProductQty (helper builds correct 2.1 URL)
 */

include("assets/functions/config.php");
global $con;

/* --------------------------------------------
 * Data loader (header + active lines)
 * ------------------------------------------*/ 
function loadJuiceTransfer(int $transferID): ?object
{
  $tid = (int)$transferID;

  $hdr = sql_query_single_row("
        SELECT id, status, outlet_from, outlet_to, created, packed, received,
               packed_by, received_by, packed_notes, received_notes,
               nicotine_in_shipment, tracking_number,
               partial_transfer_staff_member, partial_transfer_timestamp
        FROM juice_transfers
        WHERE id = {$tid}
        LIMIT 1
    ");
  if (!$hdr) return null;

  $out = (object)[ 
    'id' => (int)$hdr->id,
    'status' => (int)$hdr->status,
    'outlet_from' => getSingleOutletFromDB($hdr->outlet_from),
    'outlet_to'   => getSingleOutletFromDB($hdr->outlet_to),
    'created' => $hdr->created,
    'packed' => $hdr->packed,
    'received' => $hdr->received,
    'packed_by' => $hdr->packed_by,
    'received_by' => $hdr->received_by,
    'packed_notes' => $hdr->packed_notes,
    'received_notes' => $hdr->received_notes,
    'nicotine_in_shipment' => (int)$hdr->nicotine_in_shipment,
    'tracking_number' => $hdr->tracking_number,
    'partial_transfer_staff_member' => $hdr->partial_transfer_staff_member,
    'partial_transfer_timestamp' => $hdr->partial_transfer_timestamp,
    'products' => []
  ];

  $items = sql_query_collection("
        SELECT I.id, I.product_id, I.qty_to_send, I.qty_sent, I.qty_received, VP.name
        FROM juice_transfers_items I
        JOIN vend_products VP ON VP.id = I.product_id
        WHERE I.juice_transfer_id = {$tid}
          AND I.status = 0
        ORDER BY VP.name ASC
    ");

  $dstId  = (string)($out->outlet_to->id ?? '');
  $dstIdE = db_escape($dstId);

  foreach ($items as $r) {
    $pidE = db_escape($r->product_id);
    $inv  = sql_query_single_row("
            SELECT inventory_level
            FROM vend_inventory
            WHERE product_id='{$pidE}' AND outlet_id='{$dstIdE}'
            LIMIT 1
        ");

    $out->products[] = (object)[
      'id' => (int)$r->id,
      'juice_transfer_id' => $tid,
      'product_id' => (string)$r->product_id,
      'name' => (string)$r->name,
      'qty_to_send' => (int)$r->qty_to_send,
      'qty_sent' => (int)$r->qty_sent,
      'qty_received' => isset($r->qty_received) ? (int)$r->qty_received : null,
      'inventory_level' => (int)($inv->inventory_level ?? 0)
    ];
  }

  return $out;
}

/* --------------------------------------------
 * POST: Unlock completed transfer for re-edit
 * ------------------------------------------*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['unlockTransfer'])) {
  header('Content-Type: application/json; charset=utf-8');
  if (function_exists('ini_set')) {
    @ini_set('display_errors', '0');
  }
  error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);

  $tid = (int)$_POST['unlockTransfer'];

  try {
    $con->begin_transaction();

    $hdr = sql_query_single_row("SELECT id, status FROM juice_transfers WHERE id={$tid} FOR UPDATE");
    if (!$hdr) throw new Exception("Transfer not found.");
    if ((int)$hdr->status !== 2) throw new Exception("Only completed transfers can be unlocked.");

    sql_query_update_or_insert("
            UPDATE juice_transfers
            SET status = 1,
                received = NULL,
                received_by = NULL,
                partial_transfer_staff_member = NULL,
                partial_transfer_timestamp = NULL
            WHERE id = {$tid}
            LIMIT 1
        ");

    $con->commit();

    $resp = json_encode(['success' => true]);
    header('Content-Length: ' . strlen($resp));
    echo $resp;
  } catch (Throwable $e) {
    $con->rollback();
    error_log("[unlockTransfer] " . $e->getMessage());
    $resp = json_encode(['success' => false, 'error' => 'Unlock failed.']);
    header('Content-Length: ' . strlen($resp));
    echo $resp;
  }
  exit;
}

/* --------------------------------------------
 * POST: Save receive (AJAX) key = juiceReceive (JSON string)
 * - Updates CIS instantly
 * - Queues Vend via VEND_UpdateProductQty (helper builds API URL, JSON, queue)
 * - Sends JSON response BEFORE any background work
 * ------------------------------------------*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['juiceReceive'])) {
  header('Content-Type: application/json; charset=utf-8');
  if (function_exists('ini_set')) {
    @ini_set('display_errors', '0');
  }
  error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);

  // Make sure no stray output precedes JSON
  if (function_exists('ob_get_level')) {
    while (ob_get_level()) {
      ob_end_clean();
    }
  }

  $payload = json_decode($_POST['juiceReceive'] ?? '', true);
  if (!is_array($payload)) {
    $resp = json_encode(['success' => false, 'error' => 'Bad payload']);
    header('Content-Length: ' . strlen($resp));
    echo $resp;
    exit;
  }

  $transferID = (int)($payload['_transferID'] ?? 0);
  $src        = (string)($payload['_outletFrom'] ?? '');
  $dst        = (string)($payload['_outletTo'] ?? '');
  $staffID    = (int)($payload['_staffID'] ?? 0);
  $notes      = (string)($payload['_notes'] ?? '');
  $lines      = $payload['_products'] ?? [];

  if ($transferID <= 0 || $dst === '' || $staffID <= 0 || !is_array($lines)) {
    $resp = json_encode(['success' => false, 'error' => 'Missing transferID/destination/staffID']);
    header('Content-Length: ' . strlen($resp));
    echo $resp;
    exit;
  }

  $complete = false;
  $updated  = 0;
  $pending  = 0;

  try {
    $con->begin_transaction();

    // Lock header & validate
    $hdr = sql_query_single_row("
            SELECT id, status, outlet_from, outlet_to
            FROM juice_transfers
            WHERE id={$transferID}
            FOR UPDATE
        ");
    if (!$hdr) throw new Exception('Transfer not found.');
    if ((int)$hdr->status === 4) throw new Exception('Transfer deleted.');
    if ($src !== '' && (string)$hdr->outlet_from !== $src) throw new Exception('Source mismatch.');
    if ((string)$hdr->outlet_to !== $dst) throw new Exception('Destination mismatch.');

    // Lock active items
    $active = sql_query_collection("
            SELECT product_id, qty_received
            FROM juice_transfers_items
            WHERE juice_transfer_id={$transferID} AND status=0
            FOR UPDATE
        ");
    if (empty($active)) throw new Exception('No active items.');

    $activeMap = [];
    foreach ($active as $r) $activeMap[(string)$r->product_id] = (int)($r->qty_received ?? 0);

    $itemsEntireTransfer = count($activeMap);
    $itemsInThisTransfer = 0;

    // Process lines
    foreach ($lines as $ln) {
      if (is_object($ln)) $ln = (array)$ln;
      $pid         = (string)($ln['productID'] ?? '');
      $rawReceived = (string)($ln['qtyReceived'] ?? '');
      $readonly    = $ln['readonly'] ?? null;
      $editable    = ($readonly === 'false' || $readonly === false || $readonly === 0 || $readonly === '0');

      if (is_numeric($rawReceived) && strlen($rawReceived) > 0) {
        $itemsInThisTransfer++;
      } else {
        continue; // pending/blank
      }

      if (!$editable) continue;
      if ($pid === '' || !isset($activeMap[$pid])) continue;

      $newQtyReceived = (int)$rawReceived;

      // Destination snapshot BEFORE change
      $dstOnHand = (int)getProductQtyFromOutlet($pid, $dst);

      // Update item received + snapshot
      $pidE = db_escape($pid);
      sql_query_update_or_insert("
                UPDATE juice_transfers_items
                SET qty_received = {$newQtyReceived},
                    qty_in_stock_source = {$dstOnHand},
                    status = 0
                WHERE juice_transfer_id = {$transferID}
                  AND product_id = '{$pidE}'
                LIMIT 1
            ");

      // Absolute destination qty = snapshot + received (legacy behavior)
      $newDstLevel = $dstOnHand + $newQtyReceived;

      // Update CIS inventory
      updateProductInventory($pid, $dst, $newDstLevel);

      // Queue Vend update via helper (builds correct URL & payload)
      VEND_UpdateProductQty(
        $pid,
        $dst,
        $newDstLevel,
        'juice_transfer_received',
        'juice_transfer_destination_update'
      );

      insertStockTakedProduct($pid, $dst, $staffID);
      $updated++;
    }

    $pending = max(0, $itemsEntireTransfer - $itemsInThisTransfer);

    // Append notes if provided
    if ($notes !== '') {
      $notesE = db_escape($notes);
      sql_query_update_or_insert("
                UPDATE juice_transfers
                SET received_notes = CONCAT(
                      COALESCE(received_notes,''),
                      CASE WHEN COALESCE(received_notes,'')='' THEN '' ELSE '\n' END,
                      '{$notesE}'
                )
                WHERE id={$transferID}
                LIMIT 1
            ");
    }

    // Partial vs complete
    if ($itemsInThisTransfer !== $itemsEntireTransfer) {
      sql_query_update_or_insert("
                UPDATE juice_transfers
                SET status = 1,
                    partial_transfer_timestamp = NOW(),
                    partial_transfer_staff_member = {$staffID}
                WHERE id = {$transferID}
                LIMIT 1
            ");
      createLog($staffID, 'Partial Juice Transfer Delivered', $payload);
      $complete = false;
    } else {
      sql_query_update_or_insert("
                UPDATE juice_transfers
                SET received = NOW(),
                    status   = 2,
                    received_by = {$staffID},
                    partial_transfer_staff_member = NULL,
                    partial_transfer_timestamp = NULL
                WHERE id = {$transferID}
                LIMIT 1
            ");
      createLog($staffID, 'Complete Juice Transfer Delivered', $payload);
      $complete = true;
    }

    $con->commit();

    // --- SEND RESPONSE FIRST (so the page does not show "failed") ---
    $resp = json_encode([
      'success'  => true,
      'updated'  => $updated,
      'pending'  => $pending,
      'complete' => $complete
    ]);
    header('Content-Length: ' . strlen($resp));
    echo $resp;

    // --- THEN background queue processing (optional) ---
    if (function_exists('fastcgi_finish_request')) {
      // Flush response to client, continue in background
      fastcgi_finish_request();
    }
    if (function_exists('runQueue')) {
      runQueue();
    }
    exit;
  } catch (Throwable $e) {
    $con->rollback();
    error_log('[juiceReceive] ' . $e->getMessage());
    $resp = json_encode(['success' => false, 'error' => 'Receive failed. Please retry.']);
    header('Content-Length: ' . strlen($resp));
    echo $resp;
    exit;
  }
}

/* --------------------------------------------
 * GET: Load transfer for UI
 * ------------------------------------------*/
$transferID   = (int)($_GET['transfer'] ?? 0);
$transferData = $transferID > 0 ? loadJuiceTransfer($transferID) : null;

if (!$transferData) {
  http_response_code(404);
  echo "<div class='container p-4'><div class='alert alert-danger'>Transfer not found.</div></div>";
  exit;
}

// Status guard for UI
$isComplete = ((int)($transferData->status ?? 0) === 2);

// Precompute planned total (qty_sent preferred; fallback qty_to_send)
$plannedTotal = 0;
foreach ($transferData->products as $p) {
  $planned = (int)($p->qty_sent ?? 0);
  if ($planned === 0) $planned = (int)($p->qty_to_send ?? 0);
  $plannedTotal += $planned;
}

// Templates
include("assets/template/html-header.php");
include("assets/template/header.php");
?>

<!-- ========= YOUR UI (unchanged structure, minor whitespace tidy) ========= -->

<body class="app header-fixed sidebar-fixed aside-menu-fixed sidebar-lg-show">
  <div class="app-body">
    <?php include("assets/template/sidemenu.php"); ?>
    <main class="main">
      <ol class="breadcrumb">
        <li class="breadcrumb-item">Home</li>
        <li class="breadcrumb-item"><a href="#">Admin</a></li>
        <li class="breadcrumb-item active">Receive Juice Transfer</li>
        <li class="breadcrumb-menu d-md-down-none">
          <?php include('assets/template/quick-product-search.php'); ?>
        </li>
      </ol>

      <div class="container-fluid">
        <div class="animated fadeIn">
          <div class="col">
            <div class="card">
              <div class="card-header d-flex align-items-center" style="gap:12px;">
                <div class="flex-grow-1">
                  <h4 class="card-title mb-0">
                    Juice Transfer #<?php echo (int)$transferID; ?>
                    <small class="text-muted ml-2">
                      From <strong><?php echo htmlspecialchars($transferData->outlet_from->name ?? ''); ?></strong>
                      → To <strong><?php echo htmlspecialchars($transferData->outlet_to->name ?? ''); ?></strong>
                    </small>
                  </h4>
                  <div class="small text-muted">
                    Created: <?php echo htmlspecialchars($transferData->created ?? ''); ?>
                    <?php if (!empty($transferData->packed)): ?> · Packed: <?php echo htmlspecialchars($transferData->packed); ?><?php endif; ?>
                      <?php if ($isComplete && !empty($transferData->received)): ?> · Received: <?php echo htmlspecialchars($transferData->received); ?><?php endif; ?>
                  </div>
                </div>
              </div>

              <!-- BODY: Receive UI -->
              <div class="card-body juice-data" <?php if ($isComplete) echo 'style="opacity:.75"'; ?>>

                <!-- Actionbar -->
                <div class="actionbar d-print-none">
                  <div class="actionbar__stats">
                    <span class="stat-chip">Items <strong id="j-items"><?php echo count($transferData->products); ?></strong></span>
                    <span class="stat-chip">Planned <strong id="j-planned"><?php echo number_format($plannedTotal); ?></strong></span>
                    <span class="stat-chip">Received <strong id="j-received">0</strong></span>
                    <span class="stat-chip">Diff <strong id="j-diff">0</strong></span>
                    <span class="stat-chip">Progress <strong><span id="j-progress">0</span>/<span id="j-total"><?php echo count($transferData->products); ?></span></strong></span>
                    <?php if ($isComplete): ?>
                      <span class="stat-chip stat-chip--done">Status <strong>Complete</strong></span>
                    <?php endif; ?>
                  </div>
                  <div class="actionbar__right">
                    <div class="btn-group btn-group-sm mr-2" role="group">
                      <button type="button" class="btn btn-outline-warning" id="btn-clear-all" title="Clear all entries" <?php if ($isComplete) echo 'disabled'; ?>>
                        <i class="fa fa-eraser"></i> Clear
                      </button>
                      <button type="button" class="btn btn-outline-dark" id="btn-export" title="Export to CSV">
                        <i class="fa fa-download"></i> CSV
                      </button>
                    </div>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="btn-print">
                      <i class="fa fa-print"></i> Print
                    </button>
                    <?php if ($isComplete): ?>
                      <button type="button" class="btn btn-warning btn-sm" id="btn-unlock">
                        <i class="fa fa-unlock"></i> Unlock
                      </button>
                    <?php endif; ?>
                  </div>
                </div>

                <!-- Confidence + Discrepancies -->
                <div class="row mb-2 d-print-none">
                  <div class="col-md-8">
                    <div class="card mb-2">
                      <div class="card-body py-2">
                        <div class="d-flex align-items-center">
                          <div class="mr-2">Confidence:</div>
                          <div class="progress flex-grow-1" style="height: 10px;">
                            <div id="j-conf-bar" class="progress-bar" role="progressbar" style="width: 0%"></div>
                          </div>
                          <div id="j-conf-text" class="ml-2 small text-muted">0%</div>
                        </div>
                        <div class="small text-muted mt-1">Confidence rises as more lines exactly match planned and fewer are blank/errors.</div>
                      </div>
                    </div>
                  </div>
                  <div class="col-md-4">
                    <div class="card mb-2">
                      <div class="card-body py-2">
                        <div class="small font-weight-bold">Discrepancies</div>
                        <div id="j-disc" class="small text-muted">No issues yet.</div>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Meta ribbon -->
                <div class="ribbon mb-2">
                  <?php if (!empty($transferData->nicotine_in_shipment)) { ?>
                    <span class="ribbon-badge ribbon-badge--warn">Nicotine present — refrigerate</span>
                  <?php } ?>
                  <?php if (!empty($transferData->tracking_number)) { ?>
                    <span class="ribbon-badge"><i class="fa fa-truck"></i> <?php echo htmlspecialchars($transferData->tracking_number); ?></span>
                  <?php } ?>
                  <?php if (!empty($transferData->packed_notes)) { ?>
                    <span class="ribbon-badge"><i class="fa fa-sticky-note"></i> <?php echo htmlspecialchars($transferData->packed_notes); ?></span>
                  <?php } ?>
                  <?php if (!empty($transferData->partial_transfer_timestamp)) { ?>
                    <span class="ribbon-badge ribbon-badge--info">
                      Partial last submitted: <?php echo htmlspecialchars($transferData->partial_transfer_timestamp); ?>
                    </span>
                  <?php } ?>
                </div>

                <!-- Table -->
                <div class="table-responsive">
                  <table class="table table-sm table-bordered table-striped" id="juice-table">
                    <thead class="thead-light">
                      <tr>
                        <th style="width:40px;" class="text-center">
                          <input type="checkbox" id="select-all-rows" title="Select all rows for bulk operations" style="transform:scale(1.1);">
                        </th>
                        <th>Product</th>
                        <th style="width:110px;">On Hand</th>
                        <th style="width:180px;">Received</th>
                        <th style="width:120px;">Planned</th>
                        <th>Destination</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($transferData->products as $p):
                        $pid   = htmlspecialchars($p->product_id);
                        $pname = htmlspecialchars($p->name ?? '');
                        $on    = (int)($p->inventory_level ?? 0);
                        $sent  = (int)($p->qty_sent ?? 0);
                        if ($sent === 0) $sent = (int)($p->qty_to_send ?? 0);
                        $rcv   = ($p->qty_received !== null) ? (int)$p->qty_received : null;
                        $readonly = ($rcv !== null) || $isComplete;
                      ?>
                        <tr data-planned="<?php echo $sent; ?>" data-product-id="<?php echo $pid; ?>">
                          <td class="text-center align-middle">
                            <input type="checkbox" class="row-selector" style="transform:scale(1.1);" title="Select this row">
                            <input type="hidden" class="pid" value="<?php echo $pid; ?>">
                          </td>
                          <td>
                            <div class="d-flex align-items-center">
                              <span class="product-name"><?php echo $pname; ?></span>
                              <span class="badge badge-light ml-2 status-badge" style="font-size:9px;display:none;"></span>
                            </div>
                          </td>
                          <td class="onhand text-right"><?php echo number_format($on); ?></td>
                          <td class="rcvtd position-relative">
                            <?php if ($readonly) { ?>
                              <input type="number" class="form-control form-control-sm qty" value="<?php echo (int)($rcv ?? 0); ?>" readonly disabled>
                            <?php } else { ?>
                              <div class="input-group input-group-sm">
                                <input type="number" class="form-control qty" min="0" value="" placeholder="0"
                                  data-planned="<?php echo $sent; ?>"
                                  autocomplete="off"
                                  title="Enter quantity received (Tab/Enter to move to next)">
                                <div class="input-group-append">
                                  <button class="btn btn-outline-secondary quick-fill" type="button" title="Fill with planned quantity (<?php echo $sent; ?>)" data-value="<?php echo $sent; ?>">
                                    <i class="fa fa-arrow-down" style="font-size:10px;"></i>
                                  </button>
                                </div>
                              </div>
                              <div class="quick-suggestions" style="position:absolute;top:100%;left:0;right:0;background:white;border:1px solid #ddd;border-top:0;border-radius:0 0 4px 4px;display:none;z-index:100;max-height:120px;overflow-y:auto;"></div>
                            <?php } ?>
                          </td>
                          <td class="planned text-right position-relative">
                            <span class="planned-value"><?php echo number_format($sent); ?></span>
                            <?php if (!$readonly && $sent > 0): ?>
                              <button type="button" class="btn btn-link btn-sm p-0 copy-planned"
                                style="position:absolute;right:-8px;top:50%;transform:translateY(-50%);font-size:10px;color:#007bff;opacity:0.7;"
                                title="Copy this planned amount to received field">
                                →
                              </button>
                            <?php endif; ?>
                          </td>
                          <td><?php echo htmlspecialchars($transferData->outlet_to->name ?? ''); ?></td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>

                <!-- Notes -->
                <label for="j-notes" class="mt-2">Notes & Discrepancies</label>
                <textarea id="j-notes" class="form-control" rows="3" <?php if ($isComplete) echo 'readonly'; ?>>
<?php echo htmlspecialchars($transferData->received_notes ?? '', ENT_QUOTES, 'UTF-8'); ?>
</textarea>

                <!-- Server warnings -->
                <div id="j-warn" class="alert alert-warning mt-2" style="display:none;"></div>

                <!-- CTA -->
                <div class="mt-3 d-print-none">
                  <?php if (!$isComplete): ?>
                    <button type="button" class="btn btn-primary" id="j-submit">Declare Transfer as Delivered &amp; Counted</button>
                  <?php else: ?>
                    <div class="alert alert-success mb-0"><strong>Transfer already completed.</strong> UI is read-only.</div>
                  <?php endif; ?>
                </div>

                <!-- Hidden IDs -->
                <input type="hidden" id="j-transfer" value="<?php echo (int)$transferID; ?>">
                <input type="hidden" id="j-src" value="<?php echo htmlspecialchars($transferData->outlet_from->id ?? ''); ?>">
                <input type="hidden" id="j-dst" value="<?php echo htmlspecialchars($transferData->outlet_to->id ?? ''); ?>">
                <input type="hidden" id="j-user" value="<?php echo (int)($_SESSION['userID'] ?? 0); ?>">
              </div>

              <!-- Post-save pane -->
              <div class="card-body done-success" style="display:none;">
                <div class="alert alert-primary" role="alert">
                  <strong>Saved.</strong> Vend sync has been queued. You can print this sheet or return to the dashboard.
                </div>
              </div>
            </div><!-- /card -->
          </div><!-- /col -->
        </div><!-- /animated -->
      </div><!-- /container-fluid -->
    </main>
    <?php include("assets/template/personalisation-menu.php"); ?>
  </div><!-- /app-body -->

  <!-- Print header (hidden in screen) -->
  <div class="d-none d-print-block w-100" style="padding:4px 0;border-bottom:1px solid #ddd;margin-bottom:6px;">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:4px 16px;font-size:10pt;">
      <div><strong>From:</strong> <?php echo htmlspecialchars($transferData->outlet_from->name ?? ''); ?></div>
      <div><strong>To:</strong> <?php echo htmlspecialchars($transferData->outlet_to->name ?? ''); ?></div>
      <div><strong>Created:</strong> <?php echo htmlspecialchars($transferData->created ?? ''); ?></div>
      <div><strong>Packed:</strong> <?php echo htmlspecialchars($transferData->packed ?? ''); ?></div>
      <div><strong>Printed at:</strong> <span id="printTime"></span></div>
      <div><strong>Transfer ID:</strong> <?php echo (int)$transferID; ?></div>
    </div>
  </div>

  <!-- Success Modal -->
  <div class="modal fade" id="jSuccessModal" tabindex="-1" role="dialog" aria-labelledby="jSuccessTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
      <div class="modal-content border-0 shadow-lg">
        <div class="modal-header" style="background:linear-gradient(90deg,#10b981,#34d399);">
          <h5 class="modal-title text-white mb-0" id="jSuccessTitle">Juice Transfer Received ✔</h5>
          <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
        </div>
        <div class="modal-body p-4">
          <div class="row">
            <div class="col-md-3">
              <div class="card mb-2">
                <div class="card-body py-3 text-center">
                  <div class="text-muted small">Items</div>
                  <div class="h4 mb-0" id="jx-items">0</div>
                </div>
              </div>
            </div>
            <div class="col-md-3">
              <div class="card mb-2">
                <div class="card-body py-3 text-center">
                  <div class="text-muted small">Planned</div>
                  <div class="h4 mb-0" id="jx-planned">0</div>
                </div>
              </div>
            </div>
            <div class="col-md-3">
              <div class="card mb-2">
                <div class="card-body py-3 text-center">
                  <div class="text-muted small">Received</div>
                  <div class="h4 mb-0" id="jx-received">0</div>
                </div>
              </div>
            </div>
            <div class="col-md-3">
              <div class="card mb-2">
                <div class="card-body py-3 text-center">
                  <div class="text-muted small">Confidence</div>
                  <div class="h4 mb-0" id="jx-conf">0%</div>
                </div>
              </div>
            </div>
          </div>
          <div class="small text-muted">Vend sync queued — print this or return to your dashboard when ready.</div>
        </div>
        <div class="modal-footer bg-light border-0 d-flex justify-content-between align-items-center">
          <div class="d-flex align-items-center gap-2">
            <button type="button" class="btn btn-outline-secondary" id="jx-print">Print</button>
            <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Continue here</button>
            <span id="jx-auto-redirect" class="text-muted small" style="display:none; margin-left:10px;">
              Redirecting to dashboard in <strong><span id="jx-count">5</span>s</strong>…
            </span>
          </div>
          <button type="button" class="btn btn-success" id="jx-done">Go to Dashboard</button>
        </div>

      </div>
    </div>
  </div>

  <style>
    /* (styles unchanged from your block; kept for brevity) */
    .actionbar {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: .5rem .75rem;
      border: 1px solid #e6e9f0;
      border-radius: 8px;
      background: #fff;
      margin-bottom: 10px;
    }

    .actionbar__stats {
      display: flex;
      gap: 8px;
      flex-wrap: wrap;
    }

    .stat-chip {
      display: inline-block;
      background: #f7f8fb;
      border: 1px solid #e6e9f0;
      border-radius: 6px;
      font-size: .85rem;
      padding: .25rem .5rem;
      color: #374151
    }

    .stat-chip--done {
      background: #ecfdf5;
      border-color: #a7f3d0;
      color: #065f46
    }

    .actionbar__right {
      display: flex;
      gap: 8px;
      align-items: center;
    }

    .ribbon {
      display: flex;
      gap: 6px;
      flex-wrap: wrap
    }

    .ribbon-badge {
      display: inline-block;
      border: 1px solid #e6e9f0;
      background: #fff;
      padding: 2px 8px;
      border-radius: 999px;
      font-size: .8rem;
      color: #374151
    }

    .ribbon-badge--warn {
      border-color: #f59e0b;
      color: #92400e;
      background: #fff7ed
    }

    .ribbon-badge--info {
      border-color: #93c5fd;
      color: #1e3a8a;
      background: #eff6ff
    }

    #j-conf-bar {
      transition: width .25s ease;
      background-image: linear-gradient(90deg, #10b981, #34d399);
    }

    .qty {
      transition: all .15s ease;
      font-weight: 500;
    }

    .qty:focus {
      border-color: #007bff;
      box-shadow: 0 0 0 .1rem rgba(0, 123, 255, .25);
      transform: scale(1.02);
    }

    .quick-suggestions {
      font-size: .85rem;
    }

    .suggestion-item {
      padding: 4px 8px;
      cursor: pointer;
      border-bottom: 1px solid #f1f1f1;
      transition: background-color .1s ease;
    }

    .suggestion-item:hover,
    .suggestion-item.active {
      background-color: #007bff;
      color: white;
    }

    .status-badge.match {
      background-color: #d4edda !important;
      color: #155724 !important;
    }

    .status-badge.warn {
      background-color: #fff3cd !important;
      color: #856404 !important;
    }

    .status-badge.error {
      background-color: #f8d7da !important;
      color: #721c24 !important;
    }

    tr.selected {
      background-color: rgba(0, 123, 255, .1) !important;
    }

    .quick-fill {
      border-left: 0 !important;
      opacity: .7;
      transition: opacity .15s ease;
    }

    .quick-fill:hover {
      opacity: 1;
    }

    .copy-planned {
      opacity: 0;
      transition: opacity .15s ease;
    }

    tr:hover .copy-planned {
      opacity: .7;
    }

    .copy-planned:hover {
      opacity: 1 !important;
    }

    @media print {
      @page {
        size: A4 portrait;
        margin: 10mm;
      }

      .app-header,
      header,
      nav,
      .sidebar,
      .aside-menu,
      .breadcrumb,
      .personalisation-menu,
      .app-footer,
      footer,
      .btn,
      .dropdown,
      .dropdown-menu,
      .alert,
      .actionbar,
      .ribbon,
      .modal {
        display: none !important;
      }

      input.qty {
        display: none !important;
      }

      .app-body,
      .main,
      .container-fluid,
      .card,
      .card-body,
      .table-responsive {
        padding: 0 !important;
        margin: 0 !important;
        border: 0 !important;
        box-shadow: none !important;
        background: #fff !important;
        width: 100% !important;
      }
    }
  </style>

  <?php include("assets/template/html-footer.php"); ?>
  <?php include("assets/template/footer.php"); ?>

  <script>
    (function() {
      'use strict';

      function showToast(msg, type) {
        try {
          if ($('#toast-container').length === 0) {
            $('body').append('<div id="toast-container" style="position:fixed;top:20px;right:20px;z-index:9999;"></div>');
          }
          var id = 't' + Date.now();
          var cls = (type === 'success' ? 'bg-success' : type === 'warning' ? 'bg-warning' : type === 'error' ? 'bg-danger' : 'bg-info');
          $('#toast-container').append('<div id="' + id + '" class="toast text-white ' + cls + ' p-2 px-3 mb-2" role="alert" style="display:none;">' + msg + '</div>');
          $('#' + id).fadeIn(120);
          setTimeout(function() {
            $('#' + id).fadeOut(180, function() {
              $(this).remove();
            });
          }, 3000);
        } catch (e) {
          alert(msg);
        }
      }

      function fillPrintHeaderNow() {
        var d = new Date(),
          t = d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0') + ' ' + String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0');
        var el = document.getElementById('printTime');
        if (el) el.textContent = t;
      }
      window.addEventListener('beforeprint', fillPrintHeaderNow);

      var $table = $('#juice-table');
      var $notes = $('#j-notes');
      var $btn = $('#j-submit');

      var tID = $('#j-transfer').val();
      var key = 'juice_receive_' + tID;
      var isComplete = <?php echo $isComplete ? 'true' : 'false'; ?>;

      function draftSave() {
        var data = {
          notes: $notes.val(),
          lines: []
        };
        $table.find('tbody tr').each(function() {
          var pid = $(this).find('.pid').val();
          var inp = $(this).find('input.qty')[0];
          var ro = !!(inp && (inp.readOnly || inp.disabled));
          var v = (inp && !ro) ? String(inp.value || '') : '';
          data.lines.push({
            pid: pid,
            v: v
          });
        });
        try {
          localStorage.setItem(key, JSON.stringify(data));
        } catch (e) {}
      }

      function draftLoadIfAny() {
        var raw = localStorage.getItem(key);
        if (!raw) return;
        try {
          var data = JSON.parse(raw);
          if (!data) return;
          if (data.notes) $notes.val(data.notes);
          if (Array.isArray(data.lines)) {
            data.lines.forEach(function(l) {
              var inp = $table.find('tbody tr').filter(function() {
                return $(this).find('.pid').val() === l.pid;
              }).find('input.qty')[0];
              if (inp && !inp.readOnly && !inp.disabled && l.v !== '') {
                inp.value = l.v;
              }
            });
          }
          if (confirm('Restore your saved draft for this transfer?')) {
            recompute();
          }
        } catch (e) {}
      }

      function recompute() {
        var planned = 0,
          received = 0,
          blanks = 0,
          warns = 0,
          errs = 0,
          matches = 0,
          progress = 0;

        $table.find('tbody tr').each(function() {
          var p = parseInt($(this).attr('data-planned') || '0', 10) || 0;
          planned += p;
          var inp = $(this).find('input.qty')[0];
          var ro = !!(inp && (inp.readOnly || inp.disabled));
          var $row = $(this);
          var $badge = $row.find('.status-badge');

          $row.removeClass('table-success table-danger table-warning');
          $badge.removeClass('match warn error').hide();

          if (!inp) return;
          var raw = String(inp.value || '').trim();

          if (raw === '') {
            if (!ro) {
              blanks++;
              $badge.text('Empty').addClass('error').show();
            }
            return;
          }
          if (!ro) progress++;

          if (!/^\d+$/.test(raw)) {
            $row.addClass('table-danger');
            $badge.text('Invalid').addClass('error').show();
            errs++;
            return;
          }
          var v = parseInt(raw, 10);
          received += v;
          if (v === p || (Math.abs(v - p) <= 1 && p > 0)) {
            $row.addClass('table-success');
            $badge.text(v === p ? 'Exact' : 'Close').addClass('match').show();
            matches++;
          } else {
            var shouldWarn = false;
            if (p > 0) {
              if (v > Math.max(p * 3, p + 10)) shouldWarn = true;
              if (v % 100 === 0 && v > 100 && p % 100 !== 0 && Math.abs(v - p) > 10) shouldWarn = true;
            } else {
              if (v > 100) shouldWarn = true;
            }
            if (v > 1000) shouldWarn = true;

            if (shouldWarn) {
              $row.addClass('table-warning');
              $badge.text('Check').addClass('warn').show();
              warns++;
            } else {
              var diff = v - p;
              $badge.text((diff > 0 ? '+' : '') + diff).addClass('warn').show();
            }
          }
        });

        $('#j-received').text(received.toLocaleString());
        $('#j-diff').text((received - parseInt($('#j-planned').text().replace(/,/g, ''), 10)).toLocaleString());
        $('#j-progress').text(progress);
        $('#j-total').text($table.find('tbody tr').length);

        var disc = [];
        if (errs > 0) disc.push('<span class="text-danger">' + errs + ' error' + (errs > 1 ? 's' : '') + '</span>');
        if (warns > 0) disc.push('<span class="text-warning">' + warns + ' warning' + (warns > 1 ? 's' : '') + '</span>');
        if (blanks > 0) disc.push('<span class="text-muted">' + blanks + ' blank' + (blanks > 1 ? 's' : '') + '</span>');
        if (matches > 0) disc.push('<span class="text-success">' + matches + ' match' + (matches > 1 ? 'es' : '') + '</span>');
        $('#j-disc').html(disc.length ? disc.join(' • ') : '<span class="text-muted">Ready to start counting...</span>');

        var total = $table.find('tbody tr').length;
        var conf = 0;
        if (total > 0) {
          var base = Math.round((matches / total) * 100);
          var penalty = warns * 3 + errs * 8 + blanks * 2;
          conf = Math.max(0, Math.min(100, base - penalty));
          if (blanks === 0 && errs === 0) conf = Math.min(100, conf + 10);
        }
        $('#j-conf-bar').css('width', conf + '%');
        $('#j-conf-text').text(conf + '%');

        var canSubmit = progress > 0 && errs === 0;
        $('#j-submit').prop('disabled', !canSubmit);
      }

      function fillPrintHeaderNow() {
        /* implemented above */ }

      async function submitReceive() {
        if (<?php echo $isComplete ? 'true' : 'false'; ?>) return;

        var products = [],
          partial = false,
          atLeastOne = false;

        $('#juice-table tbody tr').each(function() {
          var pid = $(this).find('.pid').val();
          var inp = $(this).find('input.qty')[0];
          var ro = !!(inp && (inp.readOnly || inp.disabled));
          var raw = inp ? String(inp.value || '').trim() : '';
          if (!ro) {
            if (raw === '') partial = true;
            if (/^\d+$/.test(raw) && parseInt(raw, 10) > 0) atLeastOne = true;
          }
          products.push({
            productID: pid,
            qtyReceived: raw,
            readonly: ro
          });
        });

        if (!atLeastOne) {
          showToast('Please enter at least one received quantity.', 'warning');
          return;
        }

        var payload = {
          _transferID: $('#j-transfer').val(),
          _outletFrom: $('#j-src').val(),
          _outletTo: $('#j-dst').val(),
          _staffID: $('#j-user').val(),
          _notes: $('#j-notes').val(),
          _products: products
        };

        try {
          $('#j-submit').prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Saving…');

          var res = await $.ajax({
            url: window.location.pathname + window.location.search,
            type: 'POST',
            dataType: 'json',
            data: {
              juiceReceive: JSON.stringify(payload)
            }
          });

          if (!res || res.success !== true) {
            throw new Error(res?.error || 'Save failed');
          }

          var outcomeText = res.complete ? 'Completed' : 'Saved as Partial';
          $('#jSuccessTitle').text('Juice Transfer ' + outcomeText + ' ✔');

          var inlineMsg = res.complete ?
            '<strong>Saved.</strong> Transfer is complete. Vend sync has been queued. You can print this sheet or return to the dashboard.' :
            '<strong>Saved.</strong> Transfer is <u>partial</u>. Vend sync has been queued. You can continue counting, print this sheet, or return to the dashboard.';
          $('.done-success .alert').html(inlineMsg);

          $('#jx-items').text($('#j-items').text());
          $('#jx-planned').text($('#j-planned').text());
          $('#jx-received').text($('#j-received').text());
          $('#jx-conf').text($('#j-conf-text').text());

          $('.juice-data').hide();
          $('.done-success').show();

          $('#jSuccessModal').modal({
            backdrop: 'static',
            keyboard: false
          });
          $('#jx-print').off('click').on('click', function() {
            fillPrintHeaderNow();
            window.print();
          });
          $('#jx-done').off('click').on('click', function() {
            window.location.assign('/');
          });

          try {
            localStorage.removeItem(key);
          } catch (e) {}
        } catch (e) {
          console.error(e);
          showToast(e?.message || 'Save failed', 'error');
        } finally {
          $('#j-submit').prop('disabled', false).text('Declare Transfer as Delivered & Counted');
        }
      }

      $(function() {
        draftLoadIfAny();
        recompute();
        $('#btn-print').on('click', function() {
          fillPrintHeaderNow();
          window.print();
        });
        $('#j-submit').on('click', submitReceive);

        <?php if ($isComplete): ?>
          $('#btn-unlock').on('click', async function() {
            if (!confirm('Unlock this transfer for re-edit? This will reopen the transfer and allow re-submission.')) return;
            try {
              const res = await $.ajax({
                url: window.location.pathname + window.location.search,
                type: 'POST',
                dataType: 'json',
                data: {
                  unlockTransfer: $('#j-transfer').val()
                }
              });
              if (!res || res.success !== true) throw new Error('Unlock failed.');
              window.location.reload();
            } catch (e) {
              console.error(e);
              showToast(e?.message || 'Unlock failed', 'error');
            }
          });
        <?php endif; ?>

        // Simple keyboard helpers
        $(document).on('keydown', function(e) {
          if (e.ctrlKey && e.key.toLowerCase() === 's') {
            e.preventDefault();
            draftSave();
            showToast('Draft saved', 'success');
          }
        });

        // Recompute on any qty edit
        $('#juice-table').on('input', 'input.qty', function() {
          var val = String(this.value || '').trim();
          if (/^0+\d/.test(val)) {
            val = val.replace(/^0+/, '');
            this.value = val;
          }
          if (val !== '' && (!/^\d+$/.test(val) || parseInt(val, 10) < 0)) {
            this.value = '';
            showToast('Please enter a valid positive number', 'warning');
          }
          draftSave();
          recompute();
        });

        setTimeout(function() {
          $('#juice-table').find('input.qty:first').focus();
        }, 100);
      });
    })();
  </script>
</body>

</html>