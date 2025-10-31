<?php
declare(strict_types=1);
/**
 * Stock Transfer Packing — PRODUCTION
 * - CIS bootstrap + auto asset loader
 * - Uses new Lightspeed upload pipeline with SSE
 * - Keeps existing UI (pack.js) and safely overrides submitTransfer()
 */

define('PACK_PAGE', true);

// -----------------------------------------------------------------------------
// Param normalization
// -----------------------------------------------------------------------------
$transferId = (int)($_GET['transfer_id'] ?? $_GET['transfer'] ?? $_GET['id'] ?? 0);
if ($transferId <= 0) {
    http_response_code(400);
    echo '<!doctype html><meta charset="utf-8"><title>Bad Request</title><h1>Bad Request</h1><p>Missing or invalid transfer id.</p>';
    exit;
}

// -----------------------------------------------------------------------------
// Load CIS + shared helpers
// -----------------------------------------------------------------------------
require_once __DIR__ . '/../bootstrap.php';

// DEV-ONLY BLOCK (disabled by default):
// if (getenv('PACK_DEV_IP_ONLY') === '1' && ($_SERVER['REMOTE_ADDR'] ?? '') !== '125.236.217.224') {
//     http_response_code(403);
//     header('Content-Type: text/plain; charset=utf-8');
//     exit("Access temporarily limited. Please try again soon.");
// }

// -----------------------------------------------------------------------------
// Pull transfer (canonical helper) and validate state/category
// -----------------------------------------------------------------------------
try {
    $transferData = getUniversalTransfer($transferId); // shared function
} catch (Throwable $e) {
    $transferData = null;
    error_log("Pack page error loading transfer $transferId: " . $e->getMessage());
}
if (!$transferData) {
    showErrorPage("Transfer #$transferId not found or you don't have access to it.", [
        'title' => 'Unable to Load Transfer',
        'backUrl' => 'index.php',
        'backLabel' => 'Back to Transfer List'
    ]);
    exit;
}
if (($transferData->transfer_category ?? 'STOCK') !== 'STOCK') {
    showErrorPage("Transfer #$transferId is a {$transferData->transfer_category} transfer. This page handles STOCK transfers.", [
        'title' => 'Wrong Transfer Type',
        'backUrl' => 'index.php',
        'backLabel' => 'Back to Transfer List'
    ]);
    exit;
}
if (!in_array(($transferData->state ?? ''), ['OPEN','PACKING'], true)) {
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
$body_class = 'app header-fixed sidebar-fixed aside-menu-fixed sidebar-lg-show';
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

// Templates expect these variables:
$page_head_extra = $autoCSS . "\n<meta name='csrf-token' content='{$csrf}'>";
$page_scripts_before_footer = $autoJS;

// -----------------------------------------------------------------------------
// Header
// -----------------------------------------------------------------------------
include(ROOT_PATH . "/assets/template/html-header.php");
include(ROOT_PATH . "/assets/template/header.php");
?>
<style>
  /* A few tiny reinforcements */
  .auto-save-badge{position:fixed;right:14px;bottom:18px;z-index:1030}
  .progress-xs{height:.5rem}
  .modal .progress{height:12px}
</style>

<div class="app-body">
  <?php include(ROOT_PATH . "/assets/template/sidemenu.php") ?>
  <main class="main">
    <ol class="breadcrumb">
      <li class="breadcrumb-item">Home</li>
      <li class="breadcrumb-item"><a href="#">Transfers</a></li>
      <li class="breadcrumb-item active">
        OUTGOING Stock Transfer #<?= (int)$transferData->id; ?>
        To <?= htmlspecialchars($transferData->outlet_to->name ?? ''); ?>
      </li>
    </ol>

    <div class="container-fluid">
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
              <button type="button" class="btn btn-outline-secondary" title="Print picking sheet" onclick="window.print()">
                <i class="fa fa-print mr-2"></i> Print
              </button>
            </div>
          </div>

          <div class="card-body cis-container">
            <!-- Notes -->
            <div class="form-group">
              <label for="internalNotes" class="font-weight-bold small text-uppercase text-muted">Internal notes</label>
              <textarea id="internalNotes" class="form-control form-control-sm" rows="2" placeholder="Optional notes..."></textarea>
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
                      <input type="number" min="0" step="1"
                             class="form-control form-control-sm counted-qty"
                             data-product-id="<?= htmlspecialchars($pid) ?>"
                             data-planned="<?= $planned ?>"
                             placeholder="0">
                    </td>
                    <td class="text-right">
                      <span class="text-muted"><?= $stock ?></span>
                    </td>
                    <td>
                      <button type="button" class="btn btn-outline-secondary btn-sm"
                              onclick="window.validateCountedQty && window.validateCountedQty(this.closest('tr').querySelector('.counted-qty'));">
                        Validate
                      </button>
                    </td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>

            <!-- Primary action -->
            <?php if (!$PACKONLY): ?>
              <div class="d-flex flex-wrap align-items-center mt-3" style="gap:.5rem;">
                <button type="button" class="btn btn-primary btn-lg" id="createTransferButton" onclick="submitTransfer();">
                  <i class="fa fa-rocket mr-2"></i>Create Consignment & Ready For Delivery
                </button>
                <button type="button" class="btn btn-outline-secondary" onclick="window.autoFillAllQuantities && window.autoFillAllQuantities();">
                  Auto-Fill Planned
                </button>
              </div>
            <?php endif; ?>

          </div><!--/card-body-->
        </div><!--/card-->

      </div><!--/fadeIn-->
    </div><!--/container-->
  </main>
</div>

<?php include(ROOT_PATH . "/assets/template/html-footer.php"); ?>
<?php include(ROOT_PATH . "/assets/template/footer.php"); ?>

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
    return { transfer_id: <?= (int)$transferId ?>, items, notes };
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
      return window.TransferPipeline.run(<?= (int)$transferId ?>, buildPayloadFromPage);
    };
  } else {
    // Fallback: call API directly then connect SSE
    window.submitTransfer = async function () {
      openProgress();
      const payload = buildPayloadFromPage();
      payload.action = 'submit_transfer';
      const res = await fetch('/modules/consignments/api/api.php', {
        method: 'POST',
        headers: {
          'Accept': 'application/json',
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          ...(window.CSRF_TOKEN ? {'X-CSRF-Token': window.CSRF_TOKEN} : {})
        },
        body: JSON.stringify(payload)
      });
      const data = await res.json();
      if (!data || !data.success) { setOp((data && (data.error || data.message)) || 'Submit failed'); return; }
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
    };
  }

  // Provide CSRF header to AjaxManager if present
  if (window.ConsignmentsAjax && typeof window.ConsignmentsAjax.setDefaultHeader === 'function' && window.CSRF_TOKEN) {
    window.ConsignmentsAjax.setDefaultHeader('X-CSRF-Token', window.CSRF_TOKEN);
  }
})();
</script>
