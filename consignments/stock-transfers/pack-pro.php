<div class="container-fluid pack-pro">

  <!-- Top actions: hero search (left) + actions (right) -->
  <div class="pro-actions d-flex align-items-center justify-content-between mb-2">
    <div class="left d-flex align-items-center">
      <div class="hero-search" role="search" aria-label="Add product">
        <i class="fa fa-plus-circle"></i>
        <input id="productSearch" class="form-control form-control-lg"
               type="search" placeholder="Add product by name or SKU… (press / to focus)"
               autocomplete="off">
        <div id="productResults" class="dropdown-menu d-none" aria-live="polite"></div>
      </div>
    </div>

    <div class="right d-flex align-items-center gap-2">
      <a class="btn btn-outline-secondary" target="_blank"
         href="/modules/consignments/stock-transfers/print.php?transfer_id=<?= (int)$T['id'] ?>">
        <i class="fa fa-print mr-1"></i> Print picking sheet
      </a>
      <div class="dropdown">
        <button class="btn btn-outline-secondary dropdown-toggle" type="button" id="moreActions" data-toggle="dropdown">
          More actions
        </button>
        <div class="dropdown-menu dropdown-menu-right">
          <a class="dropdown-item text-danger" href="#" id="actCancel">Cancel transfer</a>
        </div>
      </div>
      <button class="btn btn-gradient-primary" id="btnPackedReady">
        <i class="fa fa-rocket mr-1"></i> Packed & Ready
      </button>
    </div>
  </div>

  <!-- Transfer banner -->
  <div class="pro-banner card mb-3">
    <div class="row no-gutters align-items-center">
      <div class="col">
        <div class="pb-title">Stock Transfer <span class="pb-number">#<?= (int)$T['id'] ?></span></div>
        <div class="pb-dest">Destination: <strong><?= htmlspecialchars($T['outlet_to_name'] ?? '-') ?></strong></div>
        <div class="pb-time">Created: <?= htmlspecialchars($T['created_fmt']) ?> — <?= htmlspecialchars($T['ago_fmt']) ?></div>
        <div class="pb-state">State: <span class="badge badge-state"><?= htmlspecialchars($T['state'] ?? '-') ?></span></div>
      </div>
      <div class="col-auto pl-3">
        <div class="pb-from small text-muted">
          From: <strong><?= htmlspecialchars($T['outlet_from_name'] ?? '-') ?></strong><br>
          <?= htmlspecialchars($T['outlet_from_phone'] ?? '') ?>
        </div>
      </div>
      <div class="col-auto">
        <div class="pb-kpis">
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

  <!-- Two-column layout -->
  <div class="grid">
    <!-- Left: products -->
    <div class="grid-left">
      <div class="card">
        <div class="card-header py-2 d-flex justify-content-between align-items-center">
          <strong>Products to pack</strong>
          <div class="small text-muted">Source inv shown; images 40×40</div>
        </div>
        <div class="card-body p-0">
          <div class="table-wrap">
            <table id="transferTable" class="table table-sm table-hover align-middle mb-0"
                   data-transfer-id="<?= (int)$T['id']; ?>"
                   data-outlet-from="<?= htmlspecialchars((string)$T['outlet_from'], ENT_QUOTES) ?>">
              <thead>
                <tr>
                  <th class="th-line">#</th>
                  <th class="th-img">Img</th>
                  <th class="th-name text-left">Product name <span class="small text-muted">(SKU beneath)</span></th>
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
                  <td class="text-center td-img">
                    <?= $thumb ? '<img class="thumb" src="'.htmlspecialchars($thumb).'" alt="">' : '<span class="noimg">—</span>' ?>
                  </td>
                  <td class="td-name text-left">
                    <div class="prod"><?= htmlspecialchars((string)$r['name'] ?: 'Unnamed') ?></div>
                    <div class="sku-mono"><?= htmlspecialchars((string)$r['sku'] ?: '-') ?></div>
                  </td>
                  <td class="text-center td-qty"><span class="text-muted"><?= (int)$r['stock_at_source'] ?></span></td>
                  <td class="text-center td-qty"><span class="badge badge-plan"><?= $plannedR ?></span></td>
                  <td class="text-center td-qty">
                    <input type="number" class="form-control form-control-sm counted" min="0" step="1"
                           value="<?= $countedR ?>" data-planned="<?= $plannedR ?>" inputmode="numeric">
                  </td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>

          <div class="note-row d-flex justify-content-between align-items-center">
            <div class="small text-muted"><strong>Notes</strong></div>
            <div class="flex-fill mx-2">
              <input type="text" id="internalNotes" class="form-control form-control-sm" placeholder="Optional internal notes">
            </div>
            <button class="btn btn-outline-secondary btn-sm" id="btnSaveDraft">Save draft</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Right: freight + insights + history -->
    <div class="grid-right" style="width:400px">

      <!-- Freight -->
      <div class="card freight-card mb-3" aria-labelledby="freightTitle">
        <div class="card-header freight-head">
          <strong id="freightTitle">Freight</strong>
          <div class="boxes-stepper" aria-label="Boxes stepper">
            <button class="btn btn-step" id="boxMinus" aria-label="decrease boxes">−</button>
            <span class="box-pill" id="boxCount"><?= (int)($T['total_boxes'] ?? 0) ?></span>
            <button class="btn btn-step" id="boxPlus" aria-label="increase boxes">+</button>
          </div>
        </div>
        <div class="card-body">
          <ul class="nav nav-pills mb-2" id="freightTabs" role="tablist">
            <li class="nav-item"><a class="nav-link active" data-toggle="pill" href="#mode_manual" role="tab">Manual</a></li>
            <li class="nav-item"><a class="nav-link" data-toggle="pill" href="#mode_pickup" role="tab">Pick-up</a></li>
            <li class="nav-item"><a class="nav-link" data-toggle="pill" href="#mode_dropoff" role="tab">Drop-off</a></li>
          </ul>

          <div class="tab-content">
            <!-- Manual -->
            <div class="tab-pane fade show active" id="mode_manual" role="tabpanel">
              <div class="form-group mb-2">
                <label class="small text-muted mb-1">Courier (optional)</label>
                <input type="text" class="form-control form-control-sm" id="freight_courier" placeholder="e.g. NZ Post, GoSweetSpot">
              </div>
              <div class="mb-2">
                <label class="small text-muted d-flex justify-content-between align-items-center">
                  <span>Tracking numbers</span>
                  <span class="small text-muted">Boxes follow tracking count</span>
                </label>
                <div class="input-group input-group-sm mb-2">
                  <input type="text" class="form-control" id="tracking_input" placeholder="Enter tracking number…">
                  <div class="input-group-append"><button class="btn btn-outline-primary" id="addTracking">Add</button></div>
                </div>
                <ul class="list-group list-group-sm" id="trackingList"></ul>
              </div>
            </div>

            <!-- Pick-up -->
            <div class="tab-pane fade" id="mode_pickup" role="tabpanel">
              <div class="form-row">
                <div class="form-group col-6"><label class="small text-muted">Pickup window</label><input type="text" class="form-control form-control-sm" id="pickup_window"></div>
                <div class="form-group col-6"><label class="small text-muted">Contact phone</label><input type="text" class="form-control form-control-sm" id="pickup_phone"></div>
              </div>
              <div class="form-group">
                <label class="small text-muted">Boxes</label>
                <input type="number" class="form-control form-control-sm" id="pickup_boxes" min="0" step="1" value="<?= (int)($T['total_boxes'] ?? 0) ?>">
              </div>
            </div>

            <!-- Drop-off -->
            <div class="tab-pane fade" id="mode_dropoff" role="tabpanel">
              <div class="form-group"><label class="small text-muted">Drop-off location</label><input type="text" class="form-control form-control-sm" id="dropoff_location"></div>
              <div class="form-group"><label class="small text-muted">ETA</label><input type="text" class="form-control form-control-sm" id="dropoff_eta"></div>
              <div class="form-group">
                <label class="small text-muted">Boxes</label>
                <input type="number" class="form-control form-control-sm" id="dropoff_boxes" min="0" step="1" value="<?= (int)($T['total_boxes'] ?? 0) ?>">
              </div>
            </div>
          </div>

          <div class="d-flex gap-2 mt-2">
            <button class="btn btn-secondary btn-sm flex-fill" id="btnResetFreight">Reset</button>
            <button class="btn btn-success btn-sm flex-fill" id="btnSaveFreight">Save freight</button>
          </div>
        </div>
      </div>

      <!-- Insights -->
      <div class="card mb-3">
        <div class="card-header py-2"><strong>Freight Insights</strong></div>
        <div class="card-body small" id="freightInsights">
          <div class="text-muted">Computing…</div>
        </div>
      </div>

      <!-- History -->
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
  </div><!--/grid-->

</div>

<!-- SSE Modal (unchanged contracts) -->
<div class="modal fade" id="uploadProgressModal" tabindex="-1" role="dialog" aria-labelledby="uploadProgressLabel" aria-hidden="true">
  <div class="modal-dialog modal-md" role="document">
    <div class="modal-content border-0 shadow">
      <div class="modal-header text-white py-2 px-3 border-0">
        <h6 class="modal-title mb-0" id="uploadProgressLabel"><i class="fa fa-cloud-upload mr-2"></i>Uploading to Lightspeed…</h6>
        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
      </div>
      <div class="modal-body p-3">
        <div class="mb-2 small text-muted" id="uploadOperation">Connecting…</div>
        <div class="progress mb-2"><div class="progress-bar progress-bar-striped progress-bar-animated" id="uploadProgressBar" style="width:0%"></div></div>
        <ul class="list-unstyled mb-0 small" id="uploadLog"></ul>
      </div>
      <div class="modal-footer py-2"><button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Close</button></div>
    </div>
  </div>
</div>
