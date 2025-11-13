<?php
/**
 * Enterprise Transfer Packing Flagship Page (LIVE DATA)
 * Focus: High-end professional UX for complex multi-box stock transfer packing.
 * Connected to live CIS data via API.
 */
session_start();
require_once __DIR__ . '/../../base/templates/themes/cis-classic/theme.php';

// Get transfer ID from URL
$transfer_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$transfer_id) {
    die('Transfer ID required. Usage: ?id=12345');
}

$theme = new CISClassicTheme();
$theme->setTitle('Enterprise Packing • Transfer #' . $transfer_id);
$theme->setPageSubtitle('Flagship Intelligence View');
$theme->addBreadcrumb('Consignments', '/modules/consignments/');
$theme->addBreadcrumb('Stock Transfers', '/modules/consignments/stock-transfers/');
$theme->addBreadcrumb('Pack Transfer', null);
$theme->addHeaderButton('Auto Plan', 'btn-sm btn-outline-primary', 'javascript:demoAutoPlan()', 'fa-magic');
$theme->addHeaderButton('Optimize', 'btn-sm btn-info', 'javascript:demoOptimize()', 'fa-robot');
$theme->addHeaderButton('Save Draft', 'btn-sm btn-secondary', 'javascript:demoSave()', 'fa-save');
$theme->addHeaderButton('Finish', 'btn-sm btn-success', 'javascript:demoFinish()', 'fa-check');
$theme->render('html-head');
// External flagship stylesheet
echo '<link rel="stylesheet" href="/assets/css/flagship-transfer.css?v=7">';
$theme->render('header');
$theme->render('sidebar');
$theme->render('main-start');
?>
<?php
  // Expose transfer/consignment id from querystring for live data binding
  $transferId = (int)($_GET['id'] ?? $_GET['transfer_id'] ?? 0);
?>
<div id="pageData" data-transfer-id="<?= htmlspecialchars((string)$transferId, ENT_QUOTES) ?>" style="display:none"></div>
<div class="flagship-wrapper">
    <!-- TRANSFER SUMMARY HEADER (Live data populated by JS) -->
  <div class="transfer-header" role="region" aria-label="Transfer summary">
    <div class="transfer-row">
      <div class="transfer-hero">
        <div class="transfer-hero-top">
          <div class="transfer-title" id="transferTitle">Transfer #<?php echo $transfer_id; ?></div>
          <span class="badge badge-status" id="transferStatus">LOADING...</span>
          <span class="badge badge-risk" id="riskBadge" aria-live="polite">—</span>
        </div>
        <div class="transfer-hero-sub">
          <span class="hero-outlet"><i class="fa fa-arrow-right"></i> <strong>From:</strong> <span id="outletFromName">Loading...</span></span>
          <span class="hero-outlet"><i class="fa fa-store"></i> <strong>To:</strong> <span id="outletToName">Loading...</span></span>
          <span class="hero-contact"><i class="fa fa-phone"></i> <a href="#" id="outletToPhone">—</a></span>
          <span class="hero-distance"><i class="fa fa-road"></i> <span id="distanceETA">Calculating...</span></span>
          <span class="hero-closing" aria-live="polite"><i class="fa fa-clock"></i> <span id="closingCountdown">—</span></span>
        </div>
      </div>
      <div class="transfer-pacing" role="status" aria-live="polite" aria-label="Pacing metrics">
        <div class="pacing-stat">
          <div class="pacing-label">Items/Hour</div>
          <div class="pacing-value" id="pacingRate">—</div>
        </div>
        <div class="pacing-stat">
          <div class="pacing-label">Projected Finish</div>
          <div class="pacing-value" id="pacingFinish">—</div>
        </div>
        <div class="pacing-stat">
          <div class="pacing-label">Packed</div>
          <div class="pacing-value" id="pacingPacked">—</div>
        </div>
      </div>
    </div>
    <!-- Analytics Ribbon (merged KPIs) -->
    <div class="analytics-ribbon" role="region" aria-label="Analytics overview">
      <div class="ribbon-chip"><i class="fa fa-box"></i> <strong id="ribbonItems">—</strong> items</div>
      <div class="ribbon-chip"><i class="fa fa-cubes"></i> <strong id="ribbonBoxes">—</strong> boxes</div>
      <div class="ribbon-chip"><i class="fa fa-dollar-sign"></i> <strong id="ribbonFreight">—</strong> freight</div>
      <div class="ribbon-chip"><i class="fa fa-leaf"></i> <strong id="ribbonCO2">—</strong> CO₂ saved</div>
      <div class="ribbon-chip ribbon-chip-warn"><i class="fa fa-arrow-up"></i> <strong id="ribbonOver">—</strong> over-picks</div>
    </div>
    <!-- Progress & Pacing Bar -->
    <div class="pacing-bar-container" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="64" aria-label="Packing progress">
      <div class="pacing-bar-fill" id="pacingBarFill"></div>
      <div class="pacing-bar-projected" id="pacingBarProjected"></div>
    </div>
  </div>

  <!-- SEARCH / FILTERS -->
  <div class="search-filters" role="search" aria-label="Search and filters">
    <div class="search-container">
      <input type="text" id="searchInput" placeholder="Search SKU, name or scan barcode... (Press / to focus)" autocomplete="off" />
      <i class="fa fa-search"></i>
    </div>
    <div class="filter-badges" id="filterBadges">
      <div class="badge active" data-filter="all"><i class="fa fa-layer-group"></i>All</div>
      <div class="badge" data-filter="ok"><i class="fa fa-check"></i>OK</div>
      <div class="badge" data-filter="under"><i class="fa fa-arrow-down"></i>Under</div>
      <div class="badge" data-filter="over"><i class="fa fa-arrow-up"></i>Over</div>
      <div class="badge" data-filter="zero"><i class="fa fa-circle"></i>Zero</div>
    </div>
  </div>

  <div class="flagship-grid" role="main">
    <!-- LEFT MAIN CONTENT -->
    <div>
      <!-- Mini sticky header (appears after banner scrolls out) -->
      <div id="miniHeader" class="mini-header">
        <div class="mini-title" id="miniTitle">Loading transfer...</div>
        <div class="mini-chip"><i class="fa fa-truck"></i> <span id="miniRate">—</span></div>
      </div>

      <!-- Bulk toolbar (shows when rows selected) -->
      <div id="bulkToolbar" class="bulk-toolbar">
        <div><strong id="bulkCount">0</strong> selected</div>
        <div class="bulk-actions">
          <button class="btn btn-outline-secondary btn-xxs" onclick="bulkSetPackedToPlan()">Set Packed = Plan</button>
          <button class="btn btn-outline-secondary btn-xxs" onclick="bulkAssignBox('Box 1')">Assign Box 1</button>
          <button class="btn btn-outline-secondary btn-xxs" onclick="bulkAssignBox('Box 2')">Assign Box 2</button>
          <button class="btn btn-outline-secondary btn-xxs" onclick="bulkRemove()">Remove</button>
          <button class="btn btn-primary btn-xxs" onclick="openAddToTransfer()"><i class="fa fa-external-link-alt"></i> Add to Transfer…</button>
        </div>
      </div>

      <div style="display:flex; justify-content:flex-end; margin-bottom:8px;">
        <button class="btn btn-outline-primary btn-sm" onclick="openAddProducts()"><i class="fa fa-plus"></i> Add Products</button>
      </div>
      <div class="product-matrix fade-in">
        <div class="matrix-header">
          <div class="subtle">Items (Demo)</div>
          <div class="legend">
            <span><i class="fa fa-square" style="color:#e6f4ea"></i> OK</span>
            <span><i class="fa fa-square" style="color:#fff8e1"></i> Under</span>
            <span><i class="fa fa-square" style="color:#ffe2e0"></i> Over</span>
            <span><i class="fa fa-square" style="color:#dfe2e5"></i> Zero</span>
          </div>
        </div>
        <table class="product-table" id="productsTable" role="table" aria-describedby="productsCaption">
          <thead>
            <tr>
              <th style="width:22px;"><input type="checkbox" id="selectAll"></th>
              <th style="width:42px;"></th>
              <th>Product</th>
              <th style="width:70px;">Plan</th>
              <th style="width:70px;">Packed</th>
              <th style="width:120px;">Box</th>
              <th style="width:110px;">Diff</th>
            </tr>
          </thead>
          <tbody id="productsBody"></tbody>
        </table>
        <div id="productsCaption" class="subtle" style="padding:6px 10px;">Loading product data...</div>
      </div>

      <div class="card" style="margin-top:16px;">
        <div class="card-header"><i class="fa fa-robot" aria-hidden="true"></i> AI Insights (Demo)</div>
        <div class="card-body" id="aiInsights">
          <div style="font-size:12px; color:#4a5568;">Analyzing packing anomalies...</div>
          <ul id="aiList" style="margin:8px 0 0; padding-left:18px; font-size:12px; color:#243447;"></ul>
          <div aria-live="polite" id="aiAria" class="sr-only" style="position:absolute; left:-9999px; top:auto; width:1px; height:1px; overflow:hidden;">AI suggestions loaded.</div>
        </div>
      </div>

      <div class="card" style="margin-top:16px;">
        <div class="card-header"><i class="fa fa-tools"></i> Tools & Shortcuts</div>
        <div class="card-body">
          <div class="tools-flex">
            <div class="tool-pill" onclick="demoTool('Packing Slip')"><i class="fa fa-file-alt"></i> Packing Slip</div>
            <div class="tool-pill" onclick="demoTool('Photos')"><i class="fa fa-camera"></i> Photos</div>
            <div class="tool-pill" onclick="demoTool('Email Summary')"><i class="fa fa-envelope"></i> Email Summary</div>
            <div class="tool-pill" onclick="demoTool('Auto Assign Boxes')"><i class="fa fa-magic"></i> Auto Assign</div>
            <div class="tool-pill" onclick="demoTool('Export CSV')"><i class="fa fa-download"></i> Export CSV</div>
            <div class="tool-pill" onclick="demoTool('AI Advisor')"><i class="fa fa-robot"></i> AI Advisor</div>
            <div class="tool-pill" onclick="demoTool('Thermal Label Batch')"><i class="fa fa-tag"></i> Thermal Labels</div>
            <div class="tool-pill" onclick="demoTool('Freight Re-Quote')"><i class="fa fa-truck"></i> Re-Quote Freight</div>
          </div>
        </div>
      </div>

      <div class="action-footer fade-in" style="margin-top:16px;" role="contentinfo">
        <div class="progress-mini" aria-label="Packing progress">
          <span><i class="fa fa-box"></i> Progress</span>
          <div class="progress-bar-inline" aria-hidden="true"><span></span></div>
          <span id="progressPct">—</span>
        </div>
      </div>
    </div>

    <!-- RIGHT PANEL -->
    <div class="right-panel">
      <!-- COMMAND CENTER (Actions Panel) -->
      <div class="command-center fade-in widget-card" id="command-center" role="region" aria-label="Command Center">
        <div class="command-hero">
          <h4><i class="fa fa-bolt"></i> Quick Actions</h4>
        </div>
        <div class="command-grid">
          <button id="finish-transfer-btn" class="command-btn command-primary" onclick="demoFinish()" data-shortcut="⌘↵">
            <span class="command-btn-label">Finish & Generate</span>
            <span class="command-btn-shortcut"><kbd>⌘</kbd><kbd>↵</kbd></span>
          </button>
          <button id="auto-plan-btn" class="command-btn command-secondary" onclick="demoAutoPlan()" data-shortcut="⌘E">
            <span class="command-btn-label">Auto Plan</span>
            <span class="command-btn-shortcut"><kbd>⌘</kbd><kbd>E</kbd></span>
          </button>
          <button id="save-draft-btn" class="command-btn" onclick="demoSave()" data-shortcut="⌘S">
            <span class="command-btn-label">Save Draft</span>
            <span class="command-btn-shortcut"><kbd>⌘</kbd><kbd>S</kbd></span>
          </button>
          <button id="generate-labels-btn" class="command-btn" onclick="demoGenerateLabels()" data-shortcut="⌘P">
            <span class="command-btn-label">Print Labels</span>
            <span class="command-btn-shortcut"><kbd>⌘</kbd><kbd>P</kbd></span>
          </button>
        </div>
      </div>
      <!-- FREIGHT CONTROL PANEL (Full Courier Integration) -->
      <div class="freight-hub fade-in widget-card" id="freight-hub" role="region" aria-label="Freight Control Panel">
        <div class="freight-hub-header">
          <h4><i class="fa fa-truck"></i> Freight</h4>
          <button class="btn btn-outline-primary btn-xxs" onclick="openFreightAdvanced()" style="font-size:9px;"><i class="fa fa-cog"></i> Advanced</button>
        </div>

        <!-- Shipment Type Selector -->
        <div class="freight-type-selector">
          <label class="freight-type-option">
            <input type="radio" name="shipmentType" value="delivery" checked onclick="updateShipmentType('delivery')">
            <span class="freight-type-label">
              <i class="fa fa-shipping-fast"></i>
              <span>Delivery</span>
            </span>
          </label>
          <label class="freight-type-option">
            <input type="radio" name="shipmentType" value="pickup" onclick="updateShipmentType('pickup')">
            <span class="freight-type-label">
              <i class="fa fa-hand-holding-box"></i>
              <span>Pickup</span>
            </span>
          </label>
          <label class="freight-type-option">
            <input type="radio" name="shipmentType" value="dropoff" onclick="updateShipmentType('dropoff')">
            <span class="freight-type-label">
              <i class="fa fa-store-alt"></i>
              <span>Drop-off</span>
            </span>
          </label>
        </div>

        <!-- Service Level Quick Select -->
        <div class="freight-service-level">
          <select class="form-select form-select-sm" id="serviceLevel" onchange="updateServiceLevel(this.value)">
            <option value="standard">Standard (2-3 days)</option>
            <option value="express">Express (1-2 days)</option>
            <option value="overnight">Overnight</option>
            <option value="sameday">Same Day</option>
          </select>
        </div>

        <!-- Compact Metrics -->
        <div class="freight-metrics-compact">
          <div class="metric-compact"><span class="metric-label">Boxes</span><strong id="freightBoxes">—</strong></div>
          <div class="metric-compact"><span class="metric-label">Weight</span><strong id="freightWeight">—</strong></div>
          <div class="metric-compact"><span class="metric-label">Est. Cost</span><strong id="freightCost">—</strong></div>
        </div>

        <!-- Quick Options Toggles -->
        <div class="freight-quick-options">
          <label class="freight-checkbox">
            <input type="checkbox" id="signatureRequired" onchange="recalcFreight()">
            <span>Signature Required</span>
          </label>
          <label class="freight-checkbox">
            <input type="checkbox" id="saturdayDelivery" onchange="recalcFreight()">
            <span>Saturday Delivery</span>
          </label>
          <label class="freight-checkbox">
            <input type="checkbox" id="insuranceRequired" onchange="recalcFreight()">
            <span>Insurance ($<span id="insuranceValue">500</span>)</span>
          </label>
          <label class="freight-checkbox">
            <input type="checkbox" id="ruralDelivery" onchange="recalcFreight()">
            <span>Rural Surcharge</span>
          </label>
        </div>

        <!-- Selected Courier Display -->
        <div class="freight-selected-courier" id="selectedCourierDisplay">
          <div class="courier-badge">
            <img src="/assets/images/couriers/nzpost.png" alt="NZ Post" style="height:16px; margin-right:6px;" onerror="this.style.display='none'">
            <span class="courier-name">NZ Post Standard</span>
            <span class="courier-price">$85.82</span>
          </div>
          <button class="btn btn-outline-secondary btn-xxs" onclick="openCourierSelector()" style="font-size:9px;">Change</button>
        </div>

        <!-- Pickup/Dropoff Details (conditional display) -->
        <div id="pickupDetails" class="freight-conditional-panel" style="display:none;">
          <div class="freight-panel-header">Pickup Details</div>
          <div class="freight-input-group">
            <label>Pickup Date</label>
            <input type="date" class="form-control form-control-sm" id="pickupDate" onchange="recalcFreight()">
          </div>
          <div class="freight-input-group">
            <label>Time Window</label>
            <select class="form-select form-select-sm" id="pickupTime" onchange="recalcFreight()">
              <option value="morning">Morning (8-12pm)</option>
              <option value="afternoon">Afternoon (12-5pm)</option>
              <option value="anytime">Anytime</option>
            </select>
          </div>
        </div>

        <div id="dropoffDetails" class="freight-conditional-panel" style="display:none;">
          <div class="freight-panel-header">Drop-off Location</div>
          <div class="freight-input-group">
            <select class="form-select form-select-sm" id="dropoffLocation" onchange="recalcFreight()">
              <option value="">Select depot…</option>
              <option value="auckland">Auckland Depot</option>
              <option value="hamilton">Hamilton Depot</option>
              <option value="tauranga">Tauranga Depot</option>
              <option value="wellington">Wellington Depot</option>
              <option value="christchurch">Christchurch Depot</option>
            </select>
          </div>
          <div class="freight-help-text">Drop at depot for lower rates</div>
        </div>

        <!-- Live Courier Comparison -->
        <div class="freight-courier-compare">
          <div class="freight-compare-header">
            <span style="font-size:9px; font-weight:600; color:#57606a; text-transform:uppercase;">Live Rates</span>
            <span style="font-size:8px; color:#6a737d;">Updated 2 mins ago</span>
          </div>
          <div id="courierComparisonList" class="courier-comparison-list">
            <!-- Populated by JS with real courier products -->
          </div>
        </div>

        <!-- Action Buttons -->
        <div class="freight-actions">
          <button class="btn btn-primary btn-sm" onclick="bookFreight()" style="width:100%;"><i class="fa fa-check"></i> Book Shipment</button>
          <button class="btn btn-outline-secondary btn-sm" onclick="openCourierSelector()" style="width:100%; margin-top:4px; font-size:10px;">View All Options</button>
        </div>
      </div>

      <!-- PACKING SHEET GENERATOR -->
      <div class="card fade-in widget-card" id="packing-sheet-card" role="region" aria-label="Packing Sheet">
        <div class="card-header" style="background:#7c3aed; color:#fff;"><i class="fa fa-print"></i> Packing Sheet</div>
        <div class="card-body" style="padding:8px 10px;">
          <div style="font-size:10px; color:#57606a; margin-bottom:6px;">Generate professional packing document for wholesale verification</div>
          <button class="btn btn-primary btn-sm" onclick="openPackingSheet()" style="width:100%; font-size:11px;"><i class="fa fa-file-invoice"></i> Generate Packing Sheet</button>
          <button class="btn btn-outline-secondary btn-sm" onclick="openPackingSheet(true)" style="width:100%; margin-top:4px; font-size:10px;"><i class="fa fa-print"></i> Print Packing Sheet</button>
        </div>
      </div>

      <!-- DESTINATION (Moved to top) -->
      <div class="card fade-in dest-card" role="region" aria-label="Destination Details">
        <div class="card-header"><i class="fa fa-map-marker-alt" aria-hidden="true"></i> Destination</div>
        <div class="card-body">
          <div class="address-block">
            <div style="font-size:18px; font-weight:700; color:#0969da; margin-bottom:8px;" id="printToName">Loading...</div>
            <div style="font-size:12px; color:#57606a; margin-bottom:2px;" id="printToAddress">—</div>
            <div style="font-size:12px; color:#57606a; margin-bottom:2px;" id="printToContact">—</div>
            <div style="font-size:12px; color:#57606a;">Hours: 09:00–18:00 • Opens in <span id="closesIn" style="font-weight:600; color:#0969da;">40 min</span></div>
          </div>
          <div class="dest-actions" style="margin-top:10px;">
            <button class="btn btn-outline-secondary btn-sm" onclick="alert('Copied address (demo)')"><i class="fa fa-copy"></i> Copy</button>
            <button class="btn btn-outline-primary btn-sm" onclick="alert('Open directions (demo)')"><i class="fa fa-directions"></i> Directions</button>
          </div>
        </div>
      </div>

      <!-- BOX BUILDER (Separate card) -->
      <div class="card fade-in widget-card" id="box-builder-card" role="region" aria-label="Box Builder">
        <div class="card-header" style="display:flex; justify-content:space-between; align-items:center; padding:8px 12px;">
          <div><i class="fa fa-box"></i> Box Builder</div>
          <div style="display:flex; gap:4px;">
            <button class="btn btn-primary btn-xs" onclick="demoGenerateLabels()" title="Generate Shipping Labels" style="font-size:10px; padding:4px 8px;"><i class="fa fa-tag"></i></button>
            <button class="btn btn-outline-secondary btn-xs" onclick="demoPrintInternal()" title="Print Internal Box Labels" style="font-size:10px; padding:4px 8px;"><i class="fa fa-box"></i></button>
            <button class="btn btn-outline-primary btn-xs" onclick="openTrackingManager()" title="Tracking Manager" style="font-size:10px; padding:4px 8px;"><i class="fa fa-barcode"></i></button>
            <button class="btn btn-outline-primary btn-xs" onclick="addBox()" title="Add Box" style="font-size:10px; padding:4px 8px;"><i class="fa fa-plus"></i></button>
          </div>
        </div>
        <div class="card-body">
          <div class="box-builder" aria-label="Box Builder">
            <div class="box-actions">
              <select class="form-select form-select-sm" style="width:100%;" onchange="applyPreset(this.value)">
                <option value="">Preset size…</option>
                <option value="S">Small (30×20×15 cm)</option>
                <option value="M">Medium (40×30×25 cm)</option>
                <option value="L">Large (60×40×40 cm)</option>
              </select>
            </div>
            <div class="box-list" id="boxList" role="list"></div>
            <div class="box-meta">
              <div><strong>Total boxes:</strong> <span id="metaBoxes">3</span></div>
              <div><strong>Chargeable wt:</strong> <span id="metaCw">0.0</span> kg</div>
              <div><strong>Volume:</strong> <span id="metaVol">0.000</span> m³</div>
            </div>
            <div class="rates-updated">Rates last updated: <span id="ratesUpdatedAt">–</span></div>
          </div>
        </div>
      </div>
      <!-- Rate Summary Card -->
      <div class="rate-summary" role="region" aria-label="Rate Summary">
        <div class="rate-chip primary"><i class="fa fa-truck"></i> Recommended: <span id="rateRec">NZ Post</span></div>
        <div class="rate-chip"><i class="fa fa-dollar-sign"></i> Cheapest: <span id="rateCheap">GoSweetSpot</span></div>
        <div class="rate-chip"><i class="fa fa-bolt"></i> Fastest: <span id="rateFast">CourierPost</span></div>
        <div class="rate-chip"><i class="fa fa-percentage"></i> Diff vs Cheapest: <span id="rateDiff">+8.0%</span></div>
      </div>

      <!-- Consignment Notes & History -->
      <div class="card fade-in notes-card widget-card" id="notes-history" role="region" aria-label="Consignment Notes">
        <div class="card-header"><i class="fa fa-sticky-note" aria-hidden="true"></i> Notes & History</div>
        <div class="card-body">
          <form class="notes-form" onsubmit="event.preventDefault(); addNote();">
            <textarea id="noteText" placeholder="Add internal note about this shipment…"></textarea>
            <input type="file" id="noteFile" aria-label="Attach file">
            <button class="btn btn-outline-primary btn-sm" type="submit"><i class="fa fa-plus"></i> Add</button>
          </form>
          <table class="table">
            <thead>
              <tr><th style="width:110px;">When</th><th style="width:120px;">User</th><th>Note</th><th style="width:80px;">Attachment</th></tr>
            </thead>
            <tbody id="notesBody"></tbody>
          </table>
        </div>
      </div>

      <!-- Staff Presence & Activity -->
      <div class="card fade-in widget-card" id="staff-presence" role="region" aria-label="Staff Presence">
        <div class="card-header"><i class="fa fa-users" aria-hidden="true"></i> Staff Presence</div>
        <div class="card-body">
          <div class="staff-grid" id="staffGrid"></div>
        </div>
      </div>

      <!-- Courier Matrix -->
      <div class="card fade-in widget-card" id="courier-matrix-card" role="region" aria-label="Courier Matrix" style="display:none;">
        <div class="card-header"><i class="fa fa-th-large" aria-hidden="true"></i> Courier Matrix</div>
        <div class="card-body">
          <div class="courier-matrix" id="courierMatrix"></div>
        </div>
      </div>

      <!-- Sustainability & CO2 -->
      <div class="card fade-in widget-card" id="sustainability-metrics" role="region" aria-label="Sustainability Metrics">
        <div class="card-header"><i class="fa fa-leaf" aria-hidden="true"></i> Sustainability</div>
        <div class="card-body">
          <div class="co2-grid" id="co2Grid"></div>
        </div>
      </div>

      <!-- Health & Cut-Off -->
      <div class="card fade-in widget-card" id="operational-health" role="region" aria-label="Operational Health">
        <div class="card-header"><i class="fa fa-heartbeat" aria-hidden="true"></i> Operational Health</div>
        <div class="card-body">
          <div class="health-grid" id="healthGrid"></div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Add Products Modal -->
<div class="modal" id="addProductsModal" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="addProductsTitle">
  <div class="modal-dialog" style="max-width:900px;">
    <div class="modal-content">
      <div class="modal-header">
        <h5 id="addProductsTitle" class="modal-title"><i class="fa fa-plus"></i> Add Products to Transfer</h5>
  <button type="button" class="btn-close" onclick="document.getElementById('addProductsModal').classList.remove('show')" aria-label="Close">✕</button>
      </div>
      <div class="modal-body" style="max-height:60vh; overflow:auto;">
        <div style="display:flex; gap:10px; margin-bottom:8px;">
          <input id="addSearch" type="search" class="form-control form-control-sm" placeholder="Search products (SKU, name)…" oninput="if(window.renderAddProducts) renderAddProducts()" style="flex:1;">
          <select id="addCategory" class="form-select form-select-sm" onchange="if(window.renderAddProducts) renderAddProducts()">
            <option value="">All Categories</option>
            <option value="Pods">Pods</option>
            <option value="Juice">E-Liquids</option>
            <option value="Devices">Devices</option>
          </select>
        </div>
        <div id="addProductsTableWrapper">
          <table class="table table-sm" style="font-size:12px;">
            <thead>
              <tr>
                <th style="width:26px;"><input type="checkbox" id="addSelectAll" onclick="if(window.toggleAddSelectAll) toggleAddSelectAll()"></th>
                <th style="width:55px;">Image</th>
                <th>Name</th>
                <th style="width:100px;">SKU</th>
                <th style="width:80px;">Default Qty</th>
                <th style="width:80px;">Category</th>
              </tr>
            </thead>
            <tbody id="addProductsBody"></tbody>
          </table>
        </div>
      </div>
      <div class="modal-footer" style="display:flex; justify-content:space-between; align-items:center;">
        <div class="subtle" id="addSelectedCount">0 selected</div>
        <div style="display:flex; gap:6px;">
          <button class="btn btn-outline-secondary btn-sm" onclick="document.getElementById('addProductsModal').classList.remove('show')">Cancel</button>
          <button class="btn btn-primary btn-sm" onclick="if(window.confirmAddProducts) confirmAddProducts()"><i class="fa fa-check"></i> Add Selected</button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Courier Selector Modal -->
<div class="modal" id="courierSelectorModal" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="courierSelectorTitle">
  <div class="modal-dialog" style="max-width:820px;">
    <div class="modal-content">
      <div class="modal-header">
        <h5 id="courierSelectorTitle" class="modal-title"><i class="fa fa-truck"></i> Compare Courier Services</h5>
        <button type="button" class="btn-close" onclick="document.getElementById('courierSelectorModal').classList.remove('show')" aria-label="Close">✕</button>
      </div>
      <div class="modal-body" style="max-height:65vh; overflow:auto;">
        <div style="display:flex; gap:8px; margin-bottom:10px;">
          <input type="text" class="form-control form-control-sm" placeholder="Filter by courier or service…" id="courierSearchFilter" oninput="filterCourierOptions()" style="flex:1;">
          <select class="form-select form-select-sm" id="courierSortBy" onchange="sortCourierOptions(this.value)" style="width:140px;">
            <option value="price">Sort by Price</option>
            <option value="speed">Sort by Speed</option>
            <option value="rating">Sort by Rating</option>
          </select>
        </div>
        <div id="courierOptionsGrid" class="courier-options-grid">
          <!-- Populated by JS -->
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-outline-secondary btn-sm" onclick="document.getElementById('courierSelectorModal').classList.remove('show')">Cancel</button>
      </div>
    </div>
  </div>
</div>

<!-- Advanced Freight Settings Modal -->
<div class="modal" id="freightAdvancedModal" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="freightAdvancedTitle">
  <div class="modal-dialog" style="max-width:680px;">
    <div class="modal-content">
      <div class="modal-header">
        <h5 id="freightAdvancedTitle" class="modal-title"><i class="fa fa-cog"></i> Advanced Freight Settings</h5>
        <button type="button" class="btn-close" onclick="document.getElementById('freightAdvancedModal').classList.remove('show')" aria-label="Close">✕</button>
      </div>
      <div class="modal-body" style="max-height:62vh; overflow:auto;">

        <!-- Insurance Section -->
        <div class="freight-advanced-section">
          <h6><i class="fa fa-shield-alt"></i> Insurance & Liability</h6>
          <div class="freight-input-group">
            <label>Insured Value (NZD)</label>
            <input type="number" class="form-control form-control-sm" id="advInsuranceValue" value="500" min="0" step="100">
          </div>
          <div class="freight-input-group">
            <label>Contents Description</label>
            <input type="text" class="form-control form-control-sm" id="advContentsDesc" placeholder="e.g., Vaping products" value="Electronic vaping products">
          </div>
          <label class="freight-checkbox">
            <input type="checkbox" id="advDangerousGoods">
            <span>Dangerous Goods Declaration Required</span>
          </label>
        </div>

        <!-- Address Validation -->
        <div class="freight-advanced-section">
          <h6><i class="fa fa-map-marked-alt"></i> Address & Delivery Instructions</h6>
          <div class="freight-input-group">
            <label>Special Instructions</label>
            <textarea class="form-control form-control-sm" id="advDeliveryInstructions" rows="2" placeholder="Leave at door, call on arrival, etc."></textarea>
          </div>
          <div class="freight-input-group">
            <label>Authority to Leave (ATL)</label>
            <select class="form-select form-select-sm" id="advATL">
              <option value="yes">Yes - Safe to leave</option>
              <option value="no" selected>No - Signature required</option>
              <option value="neighbour">Yes - Neighbour OK</option>
            </select>
          </div>
          <label class="freight-checkbox">
            <input type="checkbox" id="advResidentialAddress">
            <span>Residential Address (may incur surcharge)</span>
          </label>
        </div>

        <!-- Notifications -->
        <div class="freight-advanced-section">
          <h6><i class="fa fa-bell"></i> Notifications</h6>
          <label class="freight-checkbox">
            <input type="checkbox" id="advNotifyEmail" checked>
            <span>Email tracking updates</span>
          </label>
          <label class="freight-checkbox">
            <input type="checkbox" id="advNotifySMS">
            <span>SMS tracking updates (+$0.50)</span>
          </label>
          <label class="freight-checkbox">
            <input type="checkbox" id="advNotifyRecipient" checked>
            <span>Notify recipient</span>
          </label>
          <div class="freight-input-group">
            <label>Recipient Email</label>
            <input type="email" class="form-control form-control-sm" id="advRecipientEmail" placeholder="customer@example.com">
          </div>
          <div class="freight-input-group">
            <label>Recipient Mobile</label>
            <input type="tel" class="form-control form-control-sm" id="advRecipientMobile" placeholder="021 234 5678">
          </div>
        </div>

        <!-- Packaging Options -->
        <div class="freight-advanced-section">
          <h6><i class="fa fa-box-open"></i> Packaging & Handling</h6>
          <label class="freight-checkbox">
            <input type="checkbox" id="advFragile">
            <span>Fragile - Handle with care</span>
          </label>
          <label class="freight-checkbox">
            <input type="checkbox" id="advThisWayUp">
            <span>This way up</span>
          </label>
          <label class="freight-checkbox">
            <input type="checkbox" id="advStackable">
            <span>Stackable</span>
          </label>
          <div class="freight-input-group">
            <label>Temperature Control</label>
            <select class="form-select form-select-sm" id="advTempControl">
              <option value="none">None</option>
              <option value="cool">Keep cool (2-8°C)</option>
              <option value="frozen">Keep frozen (-18°C)</option>
              <option value="ambient">Ambient only</option>
            </select>
          </div>
        </div>

        <!-- Account & Billing -->
        <div class="freight-advanced-section">
          <h6><i class="fa fa-credit-card"></i> Billing & Account</h6>
          <div class="freight-input-group">
            <label>Courier Account Number</label>
            <input type="text" class="form-control form-control-sm" id="advAccountNumber" placeholder="Optional - use your account">
          </div>
          <div class="freight-input-group">
            <label>Reference Number</label>
            <input type="text" class="form-control form-control-sm" id="advReferenceNumber" value="TRANSFER-12345">
          </div>
          <label class="freight-checkbox">
            <input type="checkbox" id="advChargeback">
            <span>Chargeback freight to destination outlet</span>
          </label>
        </div>

        <!-- Carbon Offset -->
        <div class="freight-advanced-section">
          <h6><i class="fa fa-leaf"></i> Sustainability</h6>
          <label class="freight-checkbox">
            <input type="checkbox" id="advCarbonOffset">
            <span>Carbon offset shipment (+$2.50, ~12kg CO₂)</span>
          </label>
          <label class="freight-checkbox">
            <input type="checkbox" id="advEcoPackaging">
            <span>Request eco-friendly packaging</span>
          </label>
        </div>

      </div>
      <div class="modal-footer" style="display:flex; justify-content:space-between;">
        <button class="btn btn-outline-secondary btn-sm" onclick="resetFreightAdvanced()"><i class="fa fa-undo"></i> Reset to Defaults</button>
        <div style="display:flex; gap:6px;">
          <button class="btn btn-outline-secondary btn-sm" onclick="document.getElementById('freightAdvancedModal').classList.remove('show')">Cancel</button>
          <button class="btn btn-primary btn-sm" onclick="saveFreightAdvanced()"><i class="fa fa-check"></i> Apply Settings</button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Tracking Manager Modal -->
<div class="modal" id="trackingManagerModal" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="trackingManagerTitle">
  <div class="modal-dialog" style="max-width:780px;">
    <div class="modal-content">
      <div class="modal-header">
        <h5 id="trackingManagerTitle" class="modal-title"><i class="fa fa-barcode"></i> Tracking Manager</h5>
  <button type="button" class="btn-close" onclick="document.getElementById('trackingManagerModal').classList.remove('show')" aria-label="Close">✕</button>
      </div>
      <div class="modal-body" style="max-height:58vh; overflow:auto;">
        <div class="subtle" style="margin-bottom:6px;">Assign, validate, and review tracking numbers per box. Duplicate entries highlighted.</div>
        <table class="table table-sm" style="font-size:12px;">
          <thead>
            <tr>
              <th style="width:90px;">Box</th>
              <th>Tracking #</th>
              <th style="width:130px;">Carrier (opt)</th>
              <th style="width:120px;">Status</th>
              <th style="width:36px;"></th>
            </tr>
          </thead>
          <tbody id="trackingBody"></tbody>
        </table>
        <div style="display:flex; gap:6px; margin-top:6px;">
          <input id="bulkTrackingInput" class="form-control form-control-sm" placeholder="Paste multiple tracking numbers…" style="flex:1;" />
          <button class="btn btn-outline-secondary btn-sm" onclick="if(window.bulkPreviewTracking) bulkPreviewTracking()"><i class="fa fa-eye"></i> Preview Import</button>
          <button class="btn btn-outline-primary btn-sm" onclick="if(window.bulkImportTracking) bulkImportTracking()"><i class="fa fa-upload"></i> Import</button>
        </div>
        <div id="trackingPreview" style="margin-top:8px; font-size:11px;"></div>
      </div>
      <div class="modal-footer" style="display:flex; justify-content:space-between; align-items:center;">
        <div class="subtle" id="trackingStats">0 assigned • 0 duplicates</div>
        <div style="display:flex; gap:6px;">
          <button class="btn btn-outline-secondary btn-sm" onclick="document.getElementById('trackingManagerModal').classList.remove('show')">Close</button>
          <button class="btn btn-primary btn-sm" onclick="document.getElementById('trackingManagerModal').classList.remove('show')"><i class="fa fa-save"></i> Save</button>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
// Global transfer data
window.TRANSFER_ID = <?php echo $transfer_id; ?>;
window.transferData = null;

// Load live transfer data on page load
document.addEventListener('DOMContentLoaded', function() {
    loadTransferData();
    // Refresh every 30 seconds
    setInterval(loadTransferData, 30000);
});

function loadTransferData() {
    fetch('/modules/consignments/stock-transfers/api/get-transfer-data.php?transfer_id=' + window.TRANSFER_ID)
        .then(response => {
            if (!response.ok) {
                throw new Error('Transfer not found (HTTP ' + response.status + ')');
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                window.transferData = data;
                renderTransferData(data);
            } else {
                console.error('Failed to load transfer data:', data.error);
                showError(data.error || 'Transfer not found');
                hideAllContent();
            }
        })
        .catch(error => {
            console.error('Error loading transfer data:', error);
            showError(error.message || 'Unable to load transfer. Please check the ID and try again.');
            hideAllContent();
        });
}

function hideAllContent() {
    // Hide all main content when transfer doesn't exist
    const mainContent = document.querySelector('.flagship-grid');
    if (mainContent) {
        mainContent.style.display = 'none';
    }
    const searchFilters = document.querySelector('.search-filters');
    if (searchFilters) {
        searchFilters.style.display = 'none';
    }
    const analyticsRibbon = document.querySelector('.analytics-ribbon');
    if (analyticsRibbon) {
        analyticsRibbon.style.display = 'none';
    }
    const pacingBar = document.querySelector('.pacing-bar-container');
    if (pacingBar) {
        pacingBar.style.display = 'none';
    }
}

function renderTransferData(data) {
    const transfer = data.transfer;
    const metrics = data.metrics;
    const freight = data.freight || {};

    // Check if address validation is required (outlet addresses incomplete)
    if (freight.address_validation_required && freight.errors.length > 0) {
        showFreightWarning(freight.errors);
    }

    // Update mini title in header
    const miniTitle = document.getElementById('miniTitle');
    if (miniTitle) {
        miniTitle.textContent = 'Transfer #' + transfer.public_id + ' • ' + (transfer.outlet_to_name || 'Unknown');
    }

    // Update header
    document.getElementById('transferTitle').textContent = 'Transfer #' + transfer.public_id;
    document.getElementById('transferStatus').textContent = transfer.state || transfer.status;
    document.getElementById('transferStatus').className = 'badge badge-status badge-' + (transfer.state || transfer.status).toLowerCase();

    // Update outlets
    document.getElementById('outletFromName').textContent = transfer.outlet_from_name || 'Unknown';
    document.getElementById('outletToName').textContent = transfer.outlet_to_name || 'Unknown';

    if (transfer.outlet_to_phone) {
        const phoneLink = document.getElementById('outletToPhone');
        phoneLink.href = 'tel:' + transfer.outlet_to_phone;
        phoneLink.textContent = transfer.outlet_to_phone;
    }

    // Update print label destination
    const printToName = document.getElementById('printToName');
    if (printToName && transfer.outlet_to_name) {
        printToName.textContent = transfer.outlet_to_name;
    }
    const printToAddress = document.getElementById('printToAddress');
    if (printToAddress) {
        const address = [
            transfer.outlet_to_address,
            transfer.outlet_to_city,
            transfer.outlet_to_postcode,
            'New Zealand'
        ].filter(Boolean).join(', ');
        printToAddress.textContent = address || '—';
    }
    const printToContact = document.getElementById('printToContact');
    if (printToContact) {
        const contact = [];
        if (transfer.outlet_to_phone) contact.push('Phone: ' + transfer.outlet_to_phone);
        if (transfer.outlet_to_email) contact.push('Email: ' + transfer.outlet_to_email);
        printToContact.textContent = contact.join(' • ') || '—';
    }

    // Update metrics in analytics ribbon
    document.getElementById('ribbonItems').textContent = metrics.total_items;
    document.getElementById('ribbonBoxes').textContent = metrics.total_boxes || 0;
    document.getElementById('ribbonFreight').textContent = '$' + (metrics.total_cost || 0).toFixed(2);
    document.getElementById('ribbonOver').textContent = metrics.over_picks || 0;

    // Update mini rate if freight carrier available
    const miniRate = document.getElementById('miniRate');
    if (miniRate && metrics.freight_carrier) {
        miniRate.textContent = metrics.freight_carrier +
            (metrics.freight_service ? ' ' + metrics.freight_service : '') +
            ' ($' + (metrics.total_cost || 0).toFixed(2) + ')';
    } else if (miniRate && metrics.total_cost > 0) {
        miniRate.textContent = '$' + (metrics.total_cost || 0).toFixed(2);
    }

    // Update progress
    document.getElementById('pacingPacked').textContent = metrics.packing_progress + '%';
    document.getElementById('progressPct').textContent = metrics.packing_progress + '%';
    const progressBar = document.getElementById('pacingBarFill');
    if (progressBar) {
        progressBar.style.width = metrics.packing_progress + '%';
    }

    // Update pacing if available
    if (data.pacing) {
        document.getElementById('pacingRate').textContent = data.pacing.items_per_hour || '—';
        if (data.pacing.projected_finish_hours > 0) {
            document.getElementById('pacingFinish').textContent = data.pacing.projected_finish_hours + 'h';
        }
    }

    // Render products table
    renderProductsTable(data.items);

    // Render boxes
    renderBoxes(data.parcels);

    // Render notes
    renderNotes(data.notes);

    // Render AI insights
    renderAIInsights(data.ai_insights);

    // Update destination card
    renderDestination(transfer);
}

function renderProductsTable(items) {
    const tbody = document.getElementById('productsBody');
    if (!tbody || !items || items.length === 0) return;

    tbody.innerHTML = '';

    items.forEach(item => {
        const diff = item.quantity_sent - item.quantity;
        let statusClass = 'status-ok';
        if (diff > 0) statusClass = 'status-over';
        else if (diff < 0) statusClass = 'status-under';
        else if (item.quantity_sent === 0) statusClass = 'status-zero';

        const row = document.createElement('tr');
        row.className = statusClass;
        row.innerHTML = `
            <td><input type="checkbox" class="product-checkbox" data-id="${item.id}"></td>
            <td><div class="product-thumb" style="background:#dfe2e5;"></div></td>
            <td>
                <div class="product-name">${item.name || item.product_name || 'Unknown'}</div>
                <div class="product-sku">${item.sku || item.product_sku || '—'}</div>
            </td>
            <td class="text-center">${item.quantity}</td>
            <td class="text-center"><strong>${item.quantity_sent}</strong></td>
            <td><span class="box-pill">—</span></td>
            <td class="text-center ${diff !== 0 ? 'text-danger' : ''}">${diff > 0 ? '+' : ''}${diff}</td>
        `;
        tbody.appendChild(row);
    });
}

function renderBoxes(parcels) {
    const boxList = document.getElementById('boxList');
    if (!boxList || !parcels || parcels.length === 0) return;

    boxList.innerHTML = '';

    parcels.forEach(parcel => {
        const boxDiv = document.createElement('div');
        boxDiv.className = 'box-row';
        boxDiv.innerHTML = `
            <div class="box-row-header">
                <span class="box-name">Box ${parcel.box_number}</span>
                <span class="box-status badge-${(parcel.status || 'pending').toLowerCase()}">${parcel.status || 'Pending'}</span>
            </div>
            <div class="box-row-body">
                <div class="box-dims">
                    <span class="dim-badge">L</span> ${(parcel.length_mm || 0) / 10} cm
                    <span class="dim-badge">W</span> ${(parcel.width_mm || 0) / 10} cm
                    <span class="dim-badge">H</span> ${(parcel.height_mm || 0) / 10} cm
                    <span class="dim-badge">kg</span> ${parcel.weight_kg || 0} kg
                </div>
                ${parcel.tracking_number ? `<div class="box-tracking">${parcel.tracking_number}</div>` : ''}
            </div>
        `;
        boxList.appendChild(boxDiv);
    });

    document.getElementById('metaBoxes').textContent = parcels.length;
}

function renderNotes(notes) {
    const tbody = document.getElementById('notesBody');
    if (!tbody) return;

    tbody.innerHTML = '';

    if (!notes || notes.length === 0) {
        tbody.innerHTML = '<tr><td colspan="4" class="text-center subtle">No notes yet</td></tr>';
        return;
    }

    notes.forEach(note => {
        const row = document.createElement('tr');
        const date = new Date(note.created_at);
        const userName = (note.firstname || '') + ' ' + (note.surname || '');
        row.innerHTML = `
            <td>${date.toLocaleString()}</td>
            <td>${userName.trim() || 'Unknown'}</td>
            <td>${note.note_text}</td>
            <td>—</td>
        `;
        tbody.appendChild(row);
    });
}

function renderAIInsights(insights) {
    const list = document.getElementById('aiList');
    if (!list) return;

    list.innerHTML = '';

    if (!insights || insights.length === 0) {
        list.innerHTML = '<li>No insights available</li>';
        return;
    }

    insights.forEach(insight => {
        const li = document.createElement('li');
        li.textContent = insight.insight_text;
        li.style.color = insight.priority === 'high' || insight.priority === 'critical' ? '#d73a49' : '#243447';
        list.appendChild(li);
    });
}

function renderDestination(transfer) {
    if (transfer.outlet_to_name) {
        const addressBlock = document.querySelector('.address-block');
        if (addressBlock) {
            addressBlock.querySelector('div:first-child').textContent = transfer.outlet_to_name;
            const address = [
                transfer.outlet_to_address,
                transfer.outlet_to_city,
                transfer.outlet_to_postcode,
                'New Zealand'
            ].filter(Boolean).join(', ');
            addressBlock.querySelectorAll('div')[1].textContent = address;
        }
    }
}

function showError(message) {
    const header = document.querySelector('.transfer-header');
    if (header) {
        // Clear existing content from header
        const title = header.querySelector('#transferTitle');
        if (title) {
            title.textContent = 'Transfer Not Found';
        }
        const status = header.querySelector('#transferStatus');
        if (status) {
            status.textContent = 'ERROR';
            status.className = 'badge badge-status badge-danger';
        }

        // Create error message div
        const errorDiv = document.createElement('div');
        errorDiv.className = 'alert alert-danger';
        errorDiv.style.margin = '20px';
        errorDiv.style.padding = '20px';
        errorDiv.style.fontSize = '16px';
        errorDiv.innerHTML = `
            <h4><i class="fa fa-exclamation-triangle"></i> Transfer Not Found</h4>
            <p><strong>Error:</strong> ${message}</p>
            <p>The transfer ID <strong>#${window.TRANSFER_ID}</strong> could not be found in the system.</p>
            <hr>
            <p><strong>Possible reasons:</strong></p>
            <ul>
                <li>The transfer ID does not exist</li>
                <li>The transfer has been deleted</li>
                <li>You may not have permission to view this transfer</li>
                <li>The ID in the URL is incorrect</li>
            </ul>
            <p style="margin-top: 15px;">
                <a href="/modules/consignments/stock-transfers/test-list-transfers.php" class="btn btn-primary">
                    <i class="fa fa-list"></i> View Available Transfers
                </a>
                <a href="/modules/consignments/" class="btn btn-secondary">
                    <i class="fa fa-home"></i> Return to Consignments
                </a>
            </p>
        `;
        header.parentNode.insertBefore(errorDiv, header.nextSibling);
    }
}

function showFreightWarning(errors) {
    // Show a simple banner warning about freight calculation
    const banner = document.createElement('div');
    banner.className = 'alert alert-warning';
    banner.style.cssText = 'margin: 10px 20px; padding: 15px; border-left: 4px solid #f0ad4e;';
    banner.innerHTML = `
        <h4 style="margin-top: 0;"><i class="fa fa-exclamation-triangle"></i> Freight Calculation Unavailable</h4>
        <p style="margin-bottom: 10px;">Cannot calculate live freight rates due to incomplete outlet addresses:</p>
        <ul style="margin: 10px 0; padding-left: 20px;">
            ${errors.map(err => `<li>${err}</li>`).join('')}
        </ul>
        <p style="margin-top: 10px; margin-bottom: 0;">
            <strong>Action:</strong> Update outlet addresses in <a href="/modules/settings/outlets/" style="color: #0969da;">Outlet Management</a> to enable live freight rates.
            Using database fallback values for now.
        </p>
    `;

    const header = document.querySelector('.transfer-header');
    if (header && header.parentNode) {
        header.parentNode.insertBefore(banner, header.nextSibling);
    }
}
</script>
<script src="/assets/js/flagship-transfer.js?v=7"></script>
<?php $theme->render('main-end'); ?>
<?php $theme->render('footer'); ?>
<?php $theme->render('html-end'); ?>
