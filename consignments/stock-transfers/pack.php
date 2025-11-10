<?php
declare(strict_types=1);
/**
 * Stock Transfer Packing — PRODUCTION (CIS Template)
 * - Uses CIS module template wrapper
 * - Uses new Lightspeed upload pipeline with SSE
 * - Keeps existing UI (pack.js) and safely overrides submitTransfer()
 * - CSS is scoped within the inner container only
 */

define('PACK_PAGE', true);

// -----------------------------------------------------------------------------
// Param normalization
// -----------------------------------------------------------------------------
$transferId = (int)($_GET['transfer_id'] ?? $_GET['transfer'] ?? $_GET['id'] ?? 0);
$__demo = isset($_GET['demo']) && $_GET['demo'] === '1';
if ($transferId <= 0 && ! $__demo) {
  http_response_code(400);
  echo '<!doctype html><meta charset="utf-8"><title>Bad Request</title><h1>Bad Request</h1><p>Missing or invalid transfer id.</p>';
  exit;
}

// -----------------------------------------------------------------------------
// Load CIS + shared helpers
// -----------------------------------------------------------------------------
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../lib/CISTemplate.php';

// -----------------------------------------------------------------------------
// Fallback error renderer (avoid fatal on missing helpers)
// -----------------------------------------------------------------------------
if (!function_exists('showErrorPage')) {
  function showErrorPage(string $message, array $opts = []): void {
    $title = $opts['title'] ?? 'Error';
    $backUrl = $opts['backUrl'] ?? null;
    $backLabel = $opts['backLabel'] ?? 'Back';
    $tpl = new CISTemplate();
    $tpl->setTitle($title);
    $tpl->startContent();
    echo '<div class="container-fluid">'
       . '<div class="alert alert-danger"><strong>' . htmlspecialchars($title) . ':</strong> '
       . htmlspecialchars($message) . '</div>';
    if ($backUrl) {
      echo '<a class="btn btn-secondary" href="' . htmlspecialchars($backUrl) . '">' . htmlspecialchars($backLabel) . '</a>';
    }
    echo '</div>';
    $tpl->endContent();
    $tpl->render();
    exit;
  }
}

// Fallback error renderer if host project helper is unavailable
if (!function_exists('showErrorPage')) {
  function showErrorPage(string $message, array $opts = []): void {
    http_response_code(400);
    $title = htmlspecialchars($opts['title'] ?? 'Error');
    $backUrl = htmlspecialchars($opts['backUrl'] ?? '/modules/consignments/?route=stock-transfers');
    $backLabel = htmlspecialchars($opts['backLabel'] ?? 'Back');
    echo '<!doctype html><meta charset="utf-8"><title>' . $title . '</title>';
    echo '<div style="font-family:system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;max-width:720px;margin:48px auto;padding:24px;border:1px solid #e5e7eb;border-radius:8px;">';
    echo '<h1 style="margin:0 0 8px;font-size:22px;">' . $title . '</h1>';
    echo '<p style="margin:0 0 16px;color:#374151;">' . htmlspecialchars($message) . '</p>';
    echo '<a href="' . $backUrl . '" style="display:inline-block;padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;text-decoration:none;color:#111827;">' . $backLabel . '</a>';
    echo '</div>';
    exit;
  }
}

// DEV-ONLY BLOCK (disabled by default):
// if (getenv('PACK_DEV_IP_ONLY') === '1' && ($_SERVER['REMOTE_ADDR'] ?? '') !== '125.236.217.224') {
//     http_response_code(403);
//     header('Content-Type: text/plain; charset=utf-8');
//     exit("Access temporarily limited. Please try again soon.");
// }

// -----------------------------------------------------------------------------
// Pull transfer (canonical helper) and validate state/category
// -----------------------------------------------------------------------------
// Pull transfer data if helper exists
$transferData = null;
if ($transferId > 0 && function_exists('getUniversalTransfer')) {
  try {
    $transferData = getUniversalTransfer($transferId);
  } catch (Throwable $e) {
    $transferData = null;
    error_log("Pack page error loading transfer $transferId: " . $e->getMessage());
  }
}
if (!$transferData && $__demo) {
  // Build demo transfer
  $transferId = $transferId ?: 999001;
  $transferData = (object) [
    'id' => $transferId,
    'transfer_category' => 'STOCK',
    'state' => 'OPEN',
    'outlet_from' => (object)['name' => 'Main Warehouse'],
    'outlet_to' => (object)['name' => 'Outlet 001'],
    'items' => [
      ['product_id' => 'SKU-1001', 'name' => 'Vape Juice 100ml (Strawberry)', 'sku' => 'VJ-100-STR', 'qty_requested' => 12, 'stock_on_hand' => 84],
      ['product_id' => 'SKU-2002', 'name' => 'Coil Pack 5pcs (0.8Ω)', 'sku' => 'COIL-08-5', 'qty_requested' => 8, 'stock_on_hand' => 240],
      ['product_id' => 'SKU-3003', 'name' => 'Pod Kit XROS 3', 'sku' => 'KIT-XR3', 'qty_requested' => 3, 'stock_on_hand' => 22]
    ]
  ];
}
if (!$transferData && ! $__demo) {
  showErrorPage("Transfer #$transferId not found or you don't have access to it.", [
    'title' => 'Unable to Load Transfer',
    'backUrl' => 'index.php',
    'backLabel' => 'Back to Transfer List'
  ]);
  exit;
}
if (!$__demo && ($transferData->transfer_category ?? 'STOCK') !== 'STOCK') {
    showErrorPage("Transfer #$transferId is a {$transferData->transfer_category} transfer. This page handles STOCK transfers.", [
        'title' => 'Wrong Transfer Type',
        'backUrl' => 'index.php',
        'backLabel' => 'Back to Transfer List'
    ]);
    exit;
}
if (!$__demo && !in_array(($transferData->state ?? ''), ['OPEN','PACKING'], true)) {
    showErrorPage("Transfer #$transferId is in '{$transferData->state}' state. Only OPEN or PACKING can be packed.", [
        'title' => 'Invalid State',
        'backUrl' => 'index.php',
        'backLabel' => 'Back to Transfer List'
    ]);
    exit;
}

// -----------------------------------------------------------------------------
// Page variables + CSRF
// -----------------------------------------------------------------------------
$PACKONLY = isset($_GET['pack_only']) && $_GET['pack_only'] === '1';
$page_title = 'Pack Transfer #' . (int)$transferData->id;
$csrf = htmlspecialchars(function_exists('cis_csrf_token') ? (string)cis_csrf_token() : (string)($_SESSION['csrf'] ?? ''), ENT_QUOTES);

// -----------------------------------------------------------------------------
// Auto-load assets (CSS/JS) — shared → module → page; plus extra includes
// -----------------------------------------------------------------------------
require_once __DIR__ . '/../../shared/functions/auto-load-assets.php';
$autoCSS = autoLoadModuleCSS(__FILE__, [
  'additional' => [
    '/modules/consignments/stock-transfers/css/pack-print.css' => ['media' => 'print']
  ]
]);
$autoJS = autoLoadModuleJS(__FILE__, [
  'additional' => [
    // New orchestrator + small hotfix script
    '/modules/consignments/stock-transfers/js/pipeline.js',
    '/modules/consignments/stock-transfers/js/pack-fix.js',
  ],
  'defer' => false
]);

// -----------------------------------------------------------------------------
// CIS Template Wrapper
// -----------------------------------------------------------------------------
$template = new CISTemplate();
$template->setTitle($page_title);
$template->setBreadcrumbs([
    ['label' => 'Home', 'url' => '/', 'icon' => 'fa-home'],
    ['label' => 'Consignments', 'url' => '/modules/consignments/'],
    ['label' => 'Stock Transfers', 'url' => '/modules/consignments/?route=stock-transfers'],
    ['label' => $page_title, 'url' => '', 'active' => true]
]);

// Start page content
$template->startContent();
?>
<style>
  /* A few tiny reinforcements */
  .pack-page .auto-save-badge{position:fixed;right:14px;bottom:18px;z-index:1030}
  .pack-page .progress-xs{height:.5rem}
  .pack-page .modal .progress{height:12px}
</style>

<div class="pack-page">
  <div class="animated fadeIn">

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

        <div class="d-flex" style="gap:16px;align-items:flex-start;">
          <div class="flex-grow-1">
            <div class="card">
              <div class="card-header d-flex justify-content-between align-items-center">
            <div>
              <h4 class="card-title mb-0">
                Stock Transfer #<?= (int)$transferData->id; ?><br>
                <?= htmlspecialchars($transferData->outlet_from->name ?? '') ?> →
                <?= htmlspecialchars($transferData->outlet_to->name ?? '') ?>
              </h4>
              <div class="small text-muted">Gather and prepare products for delivery</div>
            </div>

            <div class="btn-group">
              <button class="btn btn-outline-primary" type="button" data-toggle="modal" data-target="#addProductsModal">
                <i class="fa fa-plus mr-2"></i> Add Products
              </button>
              <a class="btn btn-outline-secondary" title="Print packing slip" target="_blank"
                 href="/modules/consignments/stock-transfers/packing-slip.php?transfer_id=<?= (int)$transferData->id; ?>">
                <i class="fa fa-print mr-2"></i> Packing Slip
              </a>
              </div>
            </div>
          </div>

          <div class="d-none d-md-block" id="packSidebarContainer">
            <?php // Render outlet sidebar partial; keep transfer data format compatibility
              $partialTransfer = $transferData;
              include __DIR__ . '/../views/_outlet_sidebar.php';
            ?>
          </div>
            </div>
          </div>

          <div class="card-body cis-container">
            <!-- Notes -->
            <div class="form-group">
              <label for="internalNotes" class="font-weight-bold small text-uppercase text-muted">Internal notes</label>
              <textarea id="internalNotes" class="form-control form-control-sm" rows="2" placeholder="Optional notes..."></textarea>
            </div>

            <!-- Shipping Options -->
            <div class="card mb-3">
              <div class="card-header py-2"><strong>Shipping Options</strong></div>
              <div class="card-body">
                <div class="form-row">
                  <div class="col-md-6 mb-2">
                    <label class="small text-muted">Method</label>
                    <div class="d-flex flex-wrap" style="gap:10px">
                      <div class="custom-control custom-radio">
                        <input type="radio" id="ship_pickup" name="ship_method" class="custom-control-input" value="pickup" checked>
                        <label class="custom-control-label" for="ship_pickup">Courier Pickup</label>
                      </div>
                      <div class="custom-control custom-radio">
                        <input type="radio" id="ship_dropoff" name="ship_method" class="custom-control-input" value="dropoff">
                        <label class="custom-control-label" for="ship_dropoff">Drop-off at Branch</label>
                      </div>
                      <div class="custom-control custom-radio">
                        <input type="radio" id="ship_manual" name="ship_method" class="custom-control-input" value="manual">
                        <label class="custom-control-label" for="ship_manual">Manual (external portal)</label>
                      </div>
                    </div>
                  </div>
                  <div class="col-md-3 mb-2 pickup-only">
                    <label class="small text-muted">Carrier</label>
                    <select id="pickup_carrier" class="form-control form-control-sm">
                      <option value="nzpost">NZ Post</option>
                      <option value="gss">GoSweetSpot</option>
                      <option value="courierpost">CourierPost</option>
                    </select>
                  </div>
                  <div class="col-md-3 mb-2 pickup-only">
                    <label class="small text-muted">Pickup Window</label>
                    <input type="datetime-local" id="pickup_when" class="form-control form-control-sm">
                  </div>
                </div>

                <div class="manual-only mt-2" style="display:none">
                  <label class="small text-muted d-block">Manual Tracking Numbers (one per box)</label>
                  <textarea id="manual_tracking" class="form-control form-control-sm" rows="2" placeholder="Enter tracking numbers, one per line"></textarea>
                </div>

                <div class="dropoff-only mt-2" style="display:none">
                  <label class="small text-muted">Drop-off Location / Notes</label>
                  <input id="dropoff_location" class="form-control form-control-sm" placeholder="e.g. NZ Post New Lynn, after 4pm">
                </div>
              </div>
            </div>

            <!-- Box Configuration & Best Sort -->
            <div class="card mb-3">
              <div class="card-header py-2 d-flex justify-content-between align-items-center">
                <strong>Box Configuration & Best Sort</strong>
                <small class="text-muted">Defaults target 25kg limit</small>
              </div>
              <div class="card-body">
                <div class="form-row">
                  <div class="col-md-2 mb-2">
                    <label class="small text-muted">Box W (mm)</label>
                    <input type="number" id="box_w" class="form-control form-control-sm" value="400" min="50">
                  </div>
                  <div class="col-md-2 mb-2">
                    <label class="small text-muted">Box H (mm)</label>
                    <input type="number" id="box_h" class="form-control form-control-sm" value="300" min="50">
                  </div>
                  <div class="col-md-2 mb-2">
                    <label class="small text-muted">Box D (mm)</label>
                    <input type="number" id="box_d" class="form-control form-control-sm" value="300" min="50">
                  </div>
                  <div class="col-md-2 mb-2">
                    <label class="small text-muted">Max Weight (kg)</label>
                    <input type="number" id="box_wlimit" class="form-control form-control-sm" value="25" step="0.5" min="1">
                  </div>
                  <div class="col-md-2 mb-2">
                    <label class="small text-muted">Satchel (kg)</label>
                    <input type="number" id="satchel_limit" class="form-control form-control-sm" value="1" step="0.1" min="0.1">
                  </div>
                  <div class="col-md-2 mb-2 d-flex align-items-end">
                    <button type="button" class="btn btn-outline-primary btn-block" onclick="bestSortBoxes()">Best Sort</button>
                  </div>
                </div>
                <div class="small text-muted">Tip: E20 satchels fit ~2–3 x 100ml bottles; basic heuristic applies when volumes are low.</div>
                <div class="mt-2" id="box_result" aria-live="polite"></div>
              </div>
            </div>

            <!-- Controls -->
            <div class="form-inline mb-2">
              <div class="custom-control custom-switch">
                <input type="checkbox" class="custom-control-input" id="unlockManual">
                <label class="custom-control-label" for="unlockManual">Unlock manual quantity editing</label>
              </div>
            </div>

            <!-- Transfer table -->
            <div class="table-responsive">
              <table id="transfer-table" class="table table-sm table-hover align-middle"
                     data-transfer-id="<?= (int)$transferData->id; ?>">
                <thead class="thead-light">
                  <tr>
                    <th style="width:42px">#</th>
                    <th>Product</th>
                    <th class="text-right" style="width:90px">Planned</th>
                    <th class="text-right" style="width:100px">Counted</th>
                    <th class="text-right" style="width:110px">Stock</th>
                    <th style="width:120px">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php
                  // Prefer a dedicated helper if present; else derive from $transferData
                  $items = [];
                  if (function_exists('getTransferItems')) {
                      $items = getTransferItems($transferId);
                  } elseif (!empty($transferData->items) && is_array($transferData->items)) {
                      $items = $transferData->items;
                  }
                  $i = 0;
                  foreach ($items as $row):
                      $i++;
                      $pid = (string)($row['product_id'] ?? $row->product_id ?? '');
                      $name = (string)($row['name'] ?? $row->name ?? '');
                      $sku  = (string)($row['sku'] ?? $row->sku ?? '');
                      $planned = (int)($row['qty_requested'] ?? $row->qty_requested ?? 0);
                      $stock   = (int)($row['stock_on_hand'] ?? $row->stock_on_hand ?? 0);
                  ?>
                  <tr>
                    <td class="text-muted"><?= $i ?></td>
                    <td>
                      <div class="font-weight-bold"><?= htmlspecialchars($name) ?></div>
                      <small class="text-muted">SKU: <?= htmlspecialchars($sku) ?></small>
                    </td>
                    <td class="text-right">
                      <span class="badge badge-light"><?= $planned ?></span>
                    </td>
                    <td class="text-right">
       <input type="number" min="0" step="1" readonly
                             class="form-control form-control-sm counted-qty"
                             data-product-id="<?= htmlspecialchars($pid) ?>"
                             data-planned="<?= $planned ?>"
                             placeholder="0">
                    </td>
                    <td class="text-right">
                      <span class="text-muted"><?= $stock ?></span>
                    </td>
                    <td class="d-flex flex-wrap" style="gap:.25rem;">
                      <button type="button" class="btn btn-outline-secondary btn-sm"
                              onclick="window.validateCountedQty && window.validateCountedQty(this.closest('tr').querySelector('.counted-qty'));;">
                        Validate
                      </button>
                      <button type="button" class="btn btn-outline-warning btn-sm"
                              onclick="flagDiscrepancy('<?= htmlspecialchars($pid) ?>', <?= $planned ?>, this.closest('tr').querySelector('.counted-qty')?.value||0)">
                        Flag
                      </button>
                    </td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>

            <!-- Photos / Videos & Sign-off -->
            <div class="card mt-3">
              <div class="card-header py-2"><strong>Evidence & Sign-off</strong></div>
              <div class="card-body">
                <div class="form-row">
                  <div class="col-md-6 mb-2">
                    <label class="small text-muted">Upload Photos/Videos</label>
                    <input id="evidence_files" type="file" class="form-control-file" multiple accept="image/*,video/*">
                    <small class="form-text text-muted">Add damaged products or packaging photos. Videos allowed.</small>
                  </div>
                  <div class="col-md-3 mb-2">
                    <label class="small text-muted">Packed By</label>
                    <input id="packed_by" class="form-control form-control-sm" placeholder="Staff name">
                  </div>
                  <div class="col-md-3 mb-2">
                    <label class="small text-muted">Sign-off</label>
                    <input id="sign_off" class="form-control form-control-sm" placeholder="Initials / Signature ref">
                  </div>
                </div>
                <div class="custom-control custom-checkbox mt-2">
                  <input type="checkbox" class="custom-control-input" id="send_store_summary" checked>
                  <label class="custom-control-label" for="send_store_summary">Email store summary/feedback to source outlet upon dispatch</label>
                </div>
              </div>
            </div>

            <!-- Primary action -->
            <?php if (!$PACKONLY): ?>
              <div class="d-flex flex-wrap align-items-center mt-3" style="gap:.5rem;">
                <button type="button" class="btn btn-primary btn-lg" id="createTransferButton" onclick="submitTransfer();">
                  <i class="fa fa-rocket mr-2"></i>Mark Packed • Create Labels / Attach Tracking
                </button>
                <button type="button" class="btn btn-outline-secondary" onclick="window.autoFillAllQuantities && window.autoFillAllQuantities();">
                  Auto-Fill Planned
                </button>
              </div>
            <?php endif; ?>
          </div><!--/card-body-->
        </div><!--/card-->
  </div><!--/fadeIn-->
</div><!--/pack-page-->

<!-- Upload Progress Modal -->
<div class="modal fade" id="uploadProgressModal" tabindex="-1" role="dialog" aria-labelledby="uploadProgressLabel" aria-hidden="true">
  <div class="modal-dialog modal-md" role="document">
    <div class="modal-content border-0 shadow">
      <div class="modal-header bg-primary text-white py-2 px-3 border-0">
        <h6 class="modal-title mb-0" id="uploadProgressLabel"><i class="fa fa-cloud-upload mr-2"></i>Uploading to Lightspeed…</h6>
        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close" style="font-size:1.2rem;opacity:.8;"><span aria-hidden="true">×</span></button>
      </div>
      <div class="modal-body p-3">
        <div class="mb-2 small text-muted" id="uploadOperation">Connecting…</div>
        <div class="progress mb-2"><div class="progress-bar progress-bar-striped progress-bar-animated" id="uploadProgressBar" style="width:0%"></div></div>
        <ul class="list-unstyled mb-0 small" id="uploadLog" style="max-height:180px;overflow:auto;"></ul>
      </div>
      <div class="modal-footer py-2">
        <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<script>
// Ensure CSRF header for ajax-manager and fetch
window.CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

(function() {
  'use strict';

  // Build payload from the table the way the backend expects
  function buildPayloadFromPage() {
    const items = [];
    document.querySelectorAll('#transfer-table input.counted-qty').forEach((el) => {
      const pid = el.dataset.productId;
      const qty = parseInt(el.value || '0', 10) || 0;
      if (pid) items.push({ product_id: pid, counted_qty: qty });
    });
    const notes = (document.getElementById('internalNotes')?.value || '').trim();
    // Shipping options
    const method = document.querySelector('input[name="ship_method"]:checked')?.value || 'pickup';
    const pickup = {
      carrier: document.getElementById('pickup_carrier')?.value || null,
      when: document.getElementById('pickup_when')?.value || null
    };
    const manualTracking = (document.getElementById('manual_tracking')?.value || '')
      .split(/\r?\n/).map(s => s.trim()).filter(Boolean);
    const dropoff = {
      location: document.getElementById('dropoff_location')?.value || ''
    };
    // Box settings
    const box = {
      w_mm: parseInt(document.getElementById('box_w')?.value || '400', 10),
      h_mm: parseInt(document.getElementById('box_h')?.value || '300', 10),
      d_mm: parseInt(document.getElementById('box_d')?.value || '300', 10),
      max_kg: parseFloat(document.getElementById('box_wlimit')?.value || '25'),
      satchel_limit_kg: parseFloat(document.getElementById('satchel_limit')?.value || '1')
    };
    const evidence = document.getElementById('evidence_files')?.files || [];
    const packedBy = document.getElementById('packed_by')?.value || '';
    const signOff = document.getElementById('sign_off')?.value || '';
    const sendStoreSummary = !!document.getElementById('send_store_summary')?.checked;

    return {
      transfer_id: <?= (int)$transferId ?>,
      items,
      notes,
      ship: { method, pickup, manualTracking, dropoff },
      box,
      evidence_count: evidence.length,
      packed_by: packedBy,
      sign_off: signOff,
      send_store_summary: sendStoreSummary
    };
  }

  // Telemetry helper
  async function tlog(event, payload={}){
    try{
      await fetch('/modules/consignments/api/telemetry.php',{
        method:'POST',headers:{'Content-Type':'application/json'},
        body: JSON.stringify({event, transfer_id: <?= (int)$transferId ?>, payload, ts: new Date().toISOString()})
      });
    }catch(e){/* ignore */}
  }

  // Minimal UI hooks for the modal
  function openProgress() { $('#uploadProgressModal').modal({ backdrop: 'static', keyboard: false }); }
  function setOp(msg) { const el = document.getElementById('uploadOperation'); if (el) el.textContent = msg || ''; }
  function setPct(pct) { const el = document.getElementById('uploadProgressBar'); if (el) { el.style.width = (pct || 0) + '%'; } }
  function logLine(text) {
    const ul = document.getElementById('uploadLog'); if (!ul) return;
    const li = document.createElement('li'); li.textContent = text; ul.prepend(li);
  }

  // SSE renderer used by the pipeline
  function onSSE(evt) {
    try {
      const st = evt.status || '';
      if (evt.current_operation) setOp(evt.current_operation);
      if (typeof evt.progress_percentage === 'number') setPct(evt.progress_percentage);
      if (evt.message) logLine(evt.message);
      if (st === 'completed') { setOp('Completed'); setPct(100); setTimeout(()=>location.reload(), 600); }
      if (st === 'failed') { setOp('Failed'); logLine('One or more products failed to upload.'); }
    } catch (e) {}
  }

  // Route all submissions through the new pipeline
  if (window.TransferPipeline && typeof window.TransferPipeline.run === 'function') {
    window.submitTransfer = function() {
      openProgress();
      tlog('pack_submit_clicked', buildPayloadFromPage());
      return window.TransferPipeline.run(<?= (int)$transferId ?>, buildPayloadFromPage);
    };
  } else {
    // Fallback: call API directly then connect SSE
    window.submitTransfer = async function () {
      openProgress();
      const payload = buildPayloadFromPage();
      payload.action = 'submit_transfer';
      tlog('pack_submit_clicked', payload);
      let endpoint = '/modules/consignments/api/pack_submit.php';
      let res = await fetch(endpoint, {
        method: 'POST',
        headers: {
          'Accept': 'application/json',
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          ...(window.CSRF_TOKEN ? {'X-CSRF-Token': window.CSRF_TOKEN} : {})
        },
        body: JSON.stringify(payload)
      });
      if (res.status === 404) {
        endpoint = '/modules/consignments/api/api.php';
        res = await fetch(endpoint, {
          method: 'POST',
          headers: {
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            ...(window.CSRF_TOKEN ? {'X-CSRF-Token': window.CSRF_TOKEN} : {})
          },
          body: JSON.stringify(payload)
        });
      }
      const data = await res.json();
      if (!data || !data.success) { setOp((data && (data.error || data.message)) || 'Submit failed'); return; }
      tlog('pack_submit_accepted', {session: data.upload_session_id});
      const es = new EventSource(data.progress_url);
      es.addEventListener('progress', (e) => { try { onSSE(JSON.parse(e.data)); } catch(_){} });
      es.addEventListener('connected', (e) => { logLine('Connected to progress stream'); });
      es.addEventListener('finished', (e) => { try { const j=JSON.parse(e.data); if (j.success) { setPct(100); setOp('Completed'); setTimeout(()=>location.reload(), 600); } } catch(_){ } es.close(); });
      es.onerror = () => { logLine('SSE connection error'); };
      // kick upload
      const fd = new FormData();
      fd.append('transfer_id', String(<?= (int)$transferId ?>));
      fd.append('session_id', data.upload_session_id);
      await fetch(data.upload_url, { method: 'POST', body: fd });
      tlog('pack_upload_started', {session: data.upload_session_id});
    };
  }

  // Provide CSRF header to AjaxManager if present
  if (window.ConsignmentsAjax && typeof window.ConsignmentsAjax.setDefaultHeader === 'function' && window.CSRF_TOKEN) {
    window.ConsignmentsAjax.setDefaultHeader('X-CSRF-Token', window.CSRF_TOKEN);
  }

  // Toggle shipping option panels
  document.querySelectorAll('input[name="ship_method"]').forEach(r => {
    r.addEventListener('change', () => {
      const val = document.querySelector('input[name="ship_method"]:checked')?.value;
      document.querySelectorAll('.pickup-only').forEach(el => el.style.display = (val === 'pickup') ? '' : 'none');
      const manual = document.querySelector('.manual-only');
      if (manual) manual.style.display = (val === 'manual') ? '' : 'none';
      const dropoff = document.querySelector('.dropoff-only');
      if (dropoff) dropoff.style.display = (val === 'dropoff') ? '' : 'none';
      tlog('ship_method_changed', {method: val});
    });
  });
  // Initialize state
  (function initShipPanels(){ const evt = new Event('change'); const radio = document.querySelector('input[name="ship_method"]:checked'); if (radio) radio.dispatchEvent(evt); })();

  // Simple discrepancy flag hook → send to flagged-products pipeline (server side endpoint expected)
  window.flagDiscrepancy = async function(productId, planned, counted) {
    try {
      await fetch('/modules/flagged-products/api/flag.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', ...(window.CSRF_TOKEN ? {'X-CSRF-Token': window.CSRF_TOKEN} : {}) },
        body: JSON.stringify({
          source: 'consignments-pack',
          transfer_id: <?= (int)$transferId ?>,
          product_id: productId,
          planned_qty: parseInt(planned,10)||0,
          counted_qty: parseInt(counted,10)||0,
          note: (document.getElementById('internalNotes')?.value || '').trim()
        })
      });
      tlog('discrepancy_flagged', {product_id: productId, planned, counted});
    } catch (e) { console.warn('Flag discrepancy failed', e); }
  }

  // Basic “Best Sort” heuristic (weight-first with satchel preference)
  window.bestSortBoxes = function() {
    // Gather counted items with known per-unit weight if present in DOM dataset (optional extension)
    const rows = document.querySelectorAll('#transfer-table tbody tr');
    const items = [];
    rows.forEach(row => {
      const pid = row.querySelector('.counted-qty')?.dataset.productId;
      const planned = parseInt(row.querySelector('.counted-qty')?.dataset.planned||'0',10)||0;
      const counted = parseInt(row.querySelector('.counted-qty')?.value||'0',10)||0;
      const qty = counted || planned;
      if (!pid || qty <= 0) return;
      const wPer = parseFloat(row.dataset.weight_g || '0') || 0; // could be filled by server enhancements
      items.push({ pid, qty, weight_g: wPer });
    });

    const wLimit = Math.max(1, parseFloat(document.getElementById('box_wlimit')?.value||'25'));
    const satchel = Math.max(0.1, parseFloat(document.getElementById('satchel_limit')?.value||'1'));

    let boxes = [];
    let satchels = 0;

    // Simple rule: if total item weight (approx via weight_g or fallback 100g) <= satchel, put into satchel bucket
    items.forEach(it => {
      const perKg = (it.weight_g>0 ? it.weight_g : 100) / 1000; // fallback 100g per unit
      const totalKg = perKg * it.qty;
      if (totalKg <= satchel) {
        satchels++;
      } else {
        // split across boxes by weight limit
        let remaining = it.qty;
        while (remaining > 0) {
          const allowed = Math.max(1, Math.floor((wLimit) / Math.max(perKg, 0.001)));
          const put = Math.min(remaining, allowed);
          boxes.push({ pid: it.pid, qty: put, est_kg: +(put * perKg).toFixed(2) });
          remaining -= put;
        }
      }
    });

    // Merge into box counts approximation
    const approxBoxes = Math.max(0, Math.ceil(boxes.reduce((sum,b)=> sum + (b.est_kg / wLimit), 0)));
    const totalBoxes = approxBoxes + satchels;

    const el = document.getElementById('box_result');
    if (el) {
      el.innerHTML = `<div class="alert alert-info py-2 mb-2">Estimated: <strong>${totalBoxes}</strong> packages — `+
        `${approxBoxes} cartons (≤ ${wLimit}kg) + ${satchels} satchel(s)`+
        `</div>`;
    }
    tlog('best_sort_computed', {totalBoxes, cartons: approxBoxes, satchels, wLimit, satchel});
  }

  // Session timing
  const __t0 = Date.now();
  window.addEventListener('beforeunload', () => {
    tlog('pack_session_end', {elapsed_ms: Date.now() - __t0});
  });
  // Log count changes for analytics
  document.querySelectorAll('#transfer-table input.counted-qty').forEach((el)=>{
    el.addEventListener('change',()=>{
      tlog('count_changed',{product_id: el.dataset.productId, planned: el.dataset.planned, counted: el.value});
    });
  });
  // Manual unlock toggle
  const unlock = document.getElementById('unlockManual');
  if (unlock) {
    unlock.addEventListener('change', () => {
      document.querySelectorAll('#transfer-table input.counted-qty').forEach(inp => {
        inp.readOnly = !unlock.checked;
      });
    });
  }
})();
</script>
<?php echo $autoCSS; ?>
<meta name="csrf-token" content="<?= $csrf ?>">
<?php echo $autoJS; ?>
<?php
// End page content and render via CIS Template
$template->endContent();
$template->render();
?>
