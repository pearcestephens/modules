<?php
declare(strict_types=1);
/**
 * Pack Transfer — Pro v2 (Pastel, Insights, Fixed 400px Sidebar)
 * - Banner header (left), Freight console + insights (right ~400px)
 * - Breadcrumb + top actions restored
 * - Created: “X days Y mins ago” (own line), State on its own line
 * - Transfer # and Destination prominent; Source shows name/phone (no full address)
 * - 600px power search, table priority width; SKU under name (mono), planned badge bigger
 * - History capped, shows placeholders if empty
 * - One-button “Packed & Ready to Deliver” → save → PACKAGED → upload (SSE)
 */

define('PACK_PAGE_PRO', true);

$transferId = (int)($_GET['transfer_id'] ?? $_GET['transfer'] ?? $_GET['id'] ?? 0);
if ($transferId <= 0) { http_response_code(400); echo 'Missing transfer id'; exit; }

require_once __DIR__ . '/../bootstrap.php';

function pdo_cis(): \PDO { return cis_resolve_pdo(); }

function human_days_mins_ago($ts): string {
    if (!$ts) return '';
    $dt = new DateTime(is_string($ts) ? $ts : '@'.(int)$ts);
    $now = new DateTime('now');
    $diff = $now->diff($dt);
    $days = (int)$diff->format('%a');
    $mins = (int)$diff->i + ((int)$diff->h * 60);
    return sprintf('%d days %d mins ago', $days, $mins);
}

function fetchHeader(int $tid): array {
    $pdo = pdo_cis();
    $tq = $pdo->prepare("SELECT id, public_id, state, outlet_from, outlet_to, created_at, total_boxes, total_weight_g FROM transfers WHERE id=? LIMIT 1");
    $tq->execute([$tid]);
    $t = $tq->fetch(PDO::FETCH_ASSOC) ?: [];

    $o = $pdo->prepare("SELECT name, physical_phone_number AS phone FROM vend_outlets WHERE id=? LIMIT 1");
    foreach (['outlet_from','outlet_to'] as $k) {
        if (!empty($t[$k])) {
            $o->execute([$t[$k]]);
            $row = $o->fetch(PDO::FETCH_ASSOC) ?: [];
            foreach ($row as $rk=>$rv) { $t[$k.'_'.$rk] = $rv; }
        }
    }
    $t['created_fmt'] = $t['created_at'] ? date('Y-m-d H:i', strtotime((string)$t['created_at'])) : '';
    $t['ago_fmt']     = $t['created_at'] ? human_days_mins_ago($t['created_at']) : '';
    return $t;
}
function fetchItems(int $tid, string $from): array {
    $pdo = pdo_cis();
    $q = $pdo->prepare("
        SELECT ti.id AS item_id, ti.product_id,
               ti.qty_requested, COALESCE(ti.qty_sent_total,0) AS qty_counted,
               vp.name, vp.sku, vp.image_thumbnail_url, vp.image_url,
               COALESCE(vi.current_amount, 0) AS stock_at_source
          FROM transfer_items ti
     LEFT JOIN vend_products vp ON vp.id = ti.product_id
     LEFT JOIN vend_inventory vi ON vi.product_id = ti.product_id AND vi.outlet_id = :outlet
         WHERE ti.transfer_id = :tid
      ORDER BY vp.name ASC
         LIMIT 3000
    ");
    $q->execute([':tid'=>$tid, ':outlet'=>$from]);
    return $q->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

$T = fetchHeader($transferId);
if (!$T) { showErrorPage('Transfer not found', ['title'=>'Not found']); exit; }
if (($T['state'] ?? '') === 'CANCELLED') { showErrorPage('Transfer is cancelled'); exit; }

$items = fetchItems($transferId, (string)$T['outlet_from']);
$planned = 0; $counted = 0;
foreach ($items as $r) { $planned += (int)$r['qty_requested']; $counted += (int)$r['qty_counted']; }
$pct = $planned ? min(100, (int)round($counted * 100 / $planned)) : 0;

$page_title = 'Pack Transfer #'.$transferId;
$body_class = 'app header-fixed sidebar-fixed aside-menu-fixed sidebar-lg-show';
$csrf = htmlspecialchars(function_exists('cis_csrf_token') ? (string)cis_csrf_token() : (string)($_SESSION['csrf'] ?? ''), ENT_QUOTES);

require_once __DIR__ . '/../../shared/functions/auto-load-assets.php';
$page_head_extra  = autoLoadModuleCSS(__FILE__, [
  'additional' => ['/modules/consignments/stock-transfers/css/pack-pro.css']
]);
$page_head_extra .= "\n<meta name='csrf-token' content='{$csrf}'>";
$page_scripts_before_footer = autoLoadModuleJS(__FILE__, [
  'additional' => [
    '/modules/consignments/stock-transfers/js/pipeline.js',
    '/modules/consignments/stock-transfers/js/pack-pro.js'
  ],
  'defer' => false
]);

ob_start();
?>
<div class="container-fluid pack-pro pastel">

  <!-- Breadcrumb -->
  <ol class="breadcrumb mb-2">
    <li class="breadcrumb-item"><a href="/">Home</a></li>
    <li class="breadcrumb-item"><a href="#">Transfers</a></li>
    <li class="breadcrumb-item active">Pack #<?= (int)$T['id'] ?></li>
  </ol>

  <!-- Actions bar -->
  <div class="actions-bar d-flex align-items-center justify-content-between mb-2">
    <div class="left d-flex align-items-center gap-2">
      <button class="btn btn-primary" id="btnPackedReady">
        <i class="fa fa-rocket mr-1"></i> Packed & Ready to Deliver
      </button>
      <div class="dropdown">
        <button class="btn btn-outline-secondary dropdown-toggle" type="button" id="moreActions" data-toggle="dropdown">More actions</button>
        <div class="dropdown-menu"><a class="dropdown-item text-danger" href="#" id="actCancel">Cancel transfer</a></div>
      </div>
      <a class="btn btn-outline-secondary" target="_blank" href="/modules/consignments/stock-transfers/print.php?transfer_id=<?= (int)$T['id'] ?>">
        <i class="fa fa-print mr-1"></i> Print picking sheet
      </a>
    </div>
    <div class="right">
      <div class="product-search" style="max-width:600px;">
        <input id="productSearch" class="form-control form-control-sm" type="search" placeholder="Search products (name / SKU)…" autocomplete="off">
        <div id="productResults" class="dropdown-menu show d-none"></div>
      </div>
    </div>
  </div>

  <!-- Two-column grid: left banner + table; right sidebar (freight+insights+history) -->
  <div class="grid">
    <div class="grid-left">

      <!-- Banner -->
      <div class="pro-banner card mb-3">
        <div class="row no-gutters align-items-center">
          <div class="col">
            <div class="pb-title">Stock Transfer <span class="pb-number">#<?= (int)$T['id'] ?></span></div>
            <div class="pb-dest">Destination: <strong><?= htmlspecialchars($T['outlet_to_name'] ?? $T['outlet_to'] ?? '-') ?></strong></div>
            <div class="pb-time">Created: <?= htmlspecialchars($T['created_fmt']) ?> — <?= htmlspecialchars($T['ago_fmt']) ?></div>
            <div class="pb-state">State: <span class="badge badge-state"><?= htmlspecialchars($T['state'] ?? '-') ?></span></div>
          </div>
          <div class="col-auto pl-3">
            <div class="pb-from small text-muted">
              From: <strong><?= htmlspecialchars($T['outlet_from_name'] ?? $T['outlet_from'] ?? '-') ?></strong><br>
              <?= htmlspecialchars($T['outlet_from_phone'] ?? '') ?>
            </div>
          </div>
          <div class="col-auto">
            <div class="pb-kpis d-flex align-items-center">
              <div class="kpi">
                <div class="k">Boxes</div><div class="v" id="kpiBoxes"><?= (int)($T['total_boxes'] ?? 0) ?></div>
              </div>
              <div class="kpi">
                <div class="k">Weight</div><div class="v"><?= (int)($T['total_weight_g'] ?? 0) ?>g</div>
              </div>
              <div class="kpi wide">
                <div class="k">Packed</div>
                <div class="v"><span id="kpiPct"><?= $pct ?></span>%</div>
                <div class="progress progress-xs"><div class="progress-bar" style="width: <?= $pct ?>%"></div></div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Products table -->
      <div class="card">
        <div class="card-header py-2 d-flex justify-content-between align-items-center">
          <strong>Products to Pack</strong>
          <div class="small text-muted">Source inv shown; images 40×40</div>
        </div>
        <div class="card-body p-0">
          <div class="table-wrap">
            <table id="transferTable" class="table table-sm table-hover align-middle mb-0"
                   data-transfer-id="<?= (int)$T['id']; ?>"
                   data-outlet-from="<?= htmlspecialchars((string)$T['outlet_from'], ENT_QUOTES) ?>">
              <thead class="thead-light">
                <tr>
                  <th class="th-line">#</th>
                  <th class="th-img">Img</th>
                  <th class="th-name text-left">Product name &nbsp;<span class="small text-muted">(SKU beneath)</span></th>
                  <th class="th-qty">Source Qty</th>
                  <th class="th-qty">Planned</th>
                  <th class="th-qty">Counted</th>
                </tr>
              </thead>
              <tbody>
              <?php $i=0; foreach ($items as $r): $i++;
                  $plannedR = (int)$r['qty_requested']; $countedR = (int)$r['qty_counted'];
                  $cls = $countedR === 0 ? 'row-zero' : ($countedR === $plannedR ? 'row-ok' : ($countedR < $plannedR ? 'row-under' : 'row-over'));
                  $thumb = (string)($r['image_thumbnail_url'] ?: '');
              ?>
                <tr class="<?= $cls ?>"
                    data-product-id="<?= htmlspecialchars($r['product_id']) ?>"
                    data-thumb="<?= htmlspecialchars($thumb) ?>"
                    data-fullimg="<?= htmlspecialchars((string)($r['image_url'] ?: $thumb)) ?>">
                  <td class="text-center td-line"><?= $i ?></td>
                  <td class="text-center td-img"><?= $thumb? '<img class="thumb" src="'.htmlspecialchars($thumb).'" alt="">' : '<span class="noimg">—</span>' ?></td>
                  <td class="td-name text-left">
                    <div class="prod"><?= htmlspecialchars((string)$r['name'] ?: 'Unnamed') ?></div>
                    <div class="sku-mono"><?= htmlspecialchars((string)$r['sku'] ?: '-') ?></div>
                  </td>
                  <td class="text-center td-qty"><span class="text-muted"><?= (int)$r['stock_at_source'] ?></span></td>
                  <td class="text-center td-qty"><span class="badge badge-plan"><?= $plannedR ?></span></td>
                  <td class="text-center td-qty">
                    <input type="number" class="form-control form-control-sm counted" min="0" step="1"
                           value="<?= $countedR ?>" data-planned="<?= $plannedR ?>">
                  </td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>

          <div class="px-3 py-2 border-top bg-light d-flex justify-content-between align-items-center">
            <div class="small text-muted"><strong>Notes</strong></div>
            <div class="flex-fill mx-2"><input type="text" id="internalNotes" class="form-control form-control-sm" placeholder="Optional internal notes"></div>
            <button class="btn btn-outline-secondary btn-sm" id="btnSaveDraft">Save Draft</button>
          </div>
        </div>
      </div>

    </div><!--/grid-left-->

    <div class="grid-right" style="width:400px">

      <!-- Freight console -->
      <div class="card freight-card mb-3">
        <div class="card-header freight-head">
          <strong>Freight Console</strong>
          <span class="ml-2 badge badge-pill badge-light">Boxes: <span id="boxCount"><?= (int)($T['total_boxes'] ?? 0) ?></span></span>
        </div>
        <div class="card-body">
          <ul class="nav nav-pills mb-2" id="freightTabs" role="tablist">
            <li class="nav-item"><a class="nav-link active" data-toggle="pill" href="#mode_manual" role="tab">Manual</a></li>
            <li class="nav-item"><a class="nav-link" data-toggle="pill" href="#mode_pickup" role="tab">Pick-up</a></li>
            <li class="nav-item"><a class="nav-link" data-toggle="pill" href="#mode_dropoff" role="tab">Drop-off</a></li>
          </ul>
          <div class="tab-content">
            <div class="tab-pane fade show active" id="mode_manual" role="tabpanel">
              <div class="form-group mb-2">
                <label class="small text-muted mb-1">Courier (optional)</label>
                <input type="text" class="form-control form-control-sm" id="freight_courier" placeholder="e.g. NZ Post, GoSweetSpot">
              </div>
              <div class="mb-2">
                <label class="small text-muted d-flex justify-content-between align-items-center">
                  <span>Tracking numbers</span>
                  <span class="small">Boxes: <input type="number" id="boxInput" class="form-control form-control-sm d-inline-block" style="width:80px" min="0" step="1" value="<?= (int)($T['total_boxes'] ?? 0) ?>" readonly></span>
                </label>
                <div class="input-group input-group-sm mb-2">
                  <input type="text" class="form-control" id="tracking_input" placeholder="Enter tracking number…">
                  <div class="input-group-append"><button class="btn btn-outline-primary" id="addTracking">Add</button></div>
                </div>
                <div class="alert alert-warning py-1 px-2 small d-none" id="boxWarn">Reduce tracking lines first before lowering boxes.</div>
                <ul class="list-group list-group-sm" id="trackingList"></ul>
              </div>
            </div>
            <div class="tab-pane fade" id="mode_pickup" role="tabpanel">
              <div class="form-row">
                <div class="form-group col-6"><label class="small text-muted">Pickup window</label><input type="text" class="form-control form-control-sm" id="pickup_window"></div>
                <div class="form-group col-6"><label class="small text-muted">Contact phone</label><input type="text" class="form-control form-control-sm" id="pickup_phone"></div>
              </div>
              <div class="form-group"><label class="small text-muted">Boxes</label><input type="number" class="form-control form-control-sm" id="pickup_boxes" min="0" step="1" value="<?= (int)($T['total_boxes'] ?? 0) ?>"></div>
            </div>
            <div class="tab-pane fade" id="mode_dropoff" role="tabpanel">
              <div class="form-group"><label class="small text-muted">Drop-off location</label><input type="text" class="form-control form-control-sm" id="dropoff_location"></div>
              <div class="form-group"><label class="small text-muted">ETA</label><input type="text" class="form-control form-control-sm" id="dropoff_eta"></div>
              <div class="form-group"><label class="small text-muted">Boxes</label><input type="number" class="form-control form-control-sm" id="dropoff_boxes" min="0" step="1" value="<?= (int)($T['total_boxes'] ?? 0) ?>"></div>
            </div>
          </div>
          <div class="d-flex gap-2 mt-2">
            <button class="btn btn-secondary btn-sm flex-fill" id="btnResetFreight">Reset</button>
            <button class="btn btn-success btn-sm flex-fill" id="btnSaveFreight">Save freight</button>
          </div>
        </div>
      </div>

      <!-- Freight Insights (CisFreight) -->
      <div class="card mb-3">
        <div class="card-header py-2"><strong>Freight Insights</strong></div>
        <div class="card-body small" id="freightInsights">
          <div class="text-muted">Computing…</div>
        </div>
      </div>

      <!-- Order History -->
      <div class="card">
        <div class="card-header py-2"><strong>Order History & Comments</strong></div>
        <div class="card-body d-flex flex-column">
          <div id="historyList" class="history-list flex-fill"></div>
          <div class="d-flex gap-2 pt-2 border-top">
            <input type="text" class="form-control form-control-sm" id="commentInput" placeholder="Add a staff comment…">
            <button class="btn btn-primary btn-sm" id="btnAddComment">Add</button>
          </div>
        </div>
      </div>

    </div><!--/grid-right-->
  </div><!--/grid -->

</div>

<!-- SSE Modal -->
<div class="modal fade" id="uploadProgressModal" tabindex="-1" role="dialog" aria-labelledby="uploadProgressLabel" aria-hidden="true">
  <div class="modal-dialog modal-md" role="document">
    <div class="modal-content border-0 shadow">
      <div class="modal-header bg-primary text-white py-2 px-3 border-0">
        <h6 class="modal-title mb-0" id="uploadProgressLabel"><i class="fa fa-cloud-upload mr-2"></i>Uploading to Lightspeed…</h6>
        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
      </div>
      <div class="modal-body p-3">
        <div class="mb-2 small text-muted" id="uploadOperation">Connecting…</div>
        <div class="progress mb-2"><div class="progress-bar progress-bar-striped progress-bar-animated" id="uploadProgressBar" style="width:0%"></div></div>
        <ul class="list-unstyled mb-0 small" id="uploadLog" style="max-height:180px;overflow:auto;"></ul>
      </div>
      <div class="modal-footer py-2"><button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Close</button></div>
    </div>
  </div>
</div>
<?php
$page_content = ob_get_clean();
$page_modals = '';

$boot = [
  'transferId' => (int)$T['id'],
  'outletFrom' => (string)$T['outlet_from'],
  'state'      => (string)$T['state'],
  'csrf'       => (string)$csrf
];
$page_scripts_before_footer .= "\n<script>window.PACKPRO_BOOT=".json_encode($boot, JSON_UNESCAPED_UNICODE).";</script>\n";

require __DIR__ . '/../shared/templates/base-layout.php';
