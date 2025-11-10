<?php
/**
 * Transfer Manager - Frontend Content (HTML Body Only)
 *
 * This file contains ONLY the HTML body content for the Transfer Manager interface.
 * It's designed to be included in the main index.php and uses variables passed from the parent scope:
 *
 * Expected variables from parent:
 * - $outletMap (array) - Map of outlet IDs to names
 * - $supplierMap (array) - Map of supplier IDs to names
 * - $syncEnabled (bool) - Whether Lightspeed sync is enabled
 * - $lsBase (string) - Lightspeed consignment base URL
 * - $debugInfo (array|null) - Debug information if available
 * - $initError (string|null) - Initialization error if any
 *
 * @package CIS\Consignments\TransferManager
 * @version 2.0.0
 * @created 2025-11-01
 */

// Ensure this file is included, not accessed directly
// Check that required variables are set from parent scope
if (!isset($outletMap) || !isset($supplierMap)) {
    http_response_code(403);
    exit('Direct access forbidden - missing required variables');
}
?>

<!-- Transfer Manager Wrap Container -->
<div class="wrap transfer-manager-wrap">

<!-- Header -->
<div class="d-flex align-items-center justify-content-between mb-3">
  <div>
    <h2 class="mb-0">Transfers Tool <span class="small-note">Ad-hoc</span></h2>
    <div class="small-note">Manage consignments. Press <kbd>/</kbd> to search.</div>
  </div>
  <div class="d-flex align-items-center gap-2">
    <div class="sync-control-group d-flex align-items-center gap-2 me-3" style="padding: 8px 16px; background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%); border-radius: 12px; border: 2px solid #e2e8f0;">
      <div class="form-check form-switch mb-0">
        <input class="form-check-input" type="checkbox" id="syncToggle" <?= $syncEnabled ? 'checked' : '' ?>>
        <label class="form-check-label" for="syncToggle">Lightspeed Sync</label>
      </div>
      <button id="btnVerifySync" class="btn btn-sm btn-outline-success" style="font-size: 0.8rem; padding: 4px 12px;" title="Verify all Lightspeed table data and sync status">
        <i class="bi bi-shield-check me-1"></i> Verify
      </button>
    </div>
    <button id="btnNew" class="btn btn-success btn-elevated"><i class="bi bi-plus-lg me-1"></i> New Transfer</button>
    <button id="btnRefresh" class="btn btn-primary btn-elevated"><i class="bi bi-arrow-repeat me-1"></i> Refresh</button>
    <button id="btnHardRefresh" class="btn btn-secondary btn-elevated" title="Hard refresh (bypass cache)"><i class="bi bi-arrow-clockwise me-1"></i> Hard Refresh</button>
  </div>
</div>

<!-- Filters - 4 columns → 2 columns + enhanced search -->
<div class="card mb-3 filters-stick">
  <div class="card-body">
    <div class="row g-3">
      <!-- Primary Filters Column -->
      <div class="col-lg-5">
        <div class="row g-2">
          <div class="col-6">
            <label class="form-label fw-semibold">
              <i class="bi bi-funnel me-1"></i>Type
            </label>
            <select id="filterType" class="form-select">
              <option value="">All Types</option>
              <option>STOCK</option>
              <option>JUICE</option>
              <option>STAFF</option>
              <option>RETURN</option>
              <option>PURCHASE_ORDER</option>
            </select>
          </div>
          <div class="col-6">
            <label class="form-label fw-semibold">
              <i class="bi bi-flag me-1"></i>State
            </label>
            <select id="filterState" class="form-select">
              <option value="">All States</option>
              <option>DRAFT</option>
              <option>OPEN</option>
              <option>PACKING</option>
              <option>PACKAGED</option>
              <option>SENT</option>
              <option>RECEIVING</option>
              <option>PARTIAL</option>
              <option>RECEIVED</option>
              <option>CANCELLED</option>
              <option>CLOSED</option>
            </select>
          </div>
        </div>
      </div>

      <!-- Outlet Filter Column -->
      <div class="col-lg-3">
        <label class="form-label fw-semibold">
          <i class="bi bi-shop me-1"></i>Outlet
        </label>
        <select id="filterOutlet" class="form-select">
          <option value="">All Outlets</option>
          <?php foreach ($outletMap as $id => $label): ?>
            <option value="<?= htmlspecialchars($id, ENT_QUOTES) ?>"><?= htmlspecialchars($label, ENT_QUOTES) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- Enhanced Search Column -->
      <div class="col-lg-4">
        <label class="form-label fw-semibold">
          <i class="bi bi-search me-1"></i>Smart Search
        </label>
        <div class="input-group">
          <span class="input-group-text bg-white">
            <i class="bi bi-search text-primary"></i>
          </span>
          <input
            id="filterQ"
            type="text"
            class="form-control"
            placeholder="Transfer #, Vend #, outlet, supplier..."
            title="Search across transfers, outlets, and suppliers">
        </div>
        <small class="text-muted d-block mt-1">
          <i class="bi bi-lightbulb text-warning"></i> Pro tip: Press <kbd>/</kbd> to quick search
        </small>
      </div>
    </div>
  </div>
</div>

<!-- Table -->
<div class="card mb-3">
  <div class="card-body">
    <div class="d-flex justify-content-between align-items-center mb-2">
      <div id="resultCount" class="small-note">Loading…</div>
      <div class="d-flex gap-2 align-items-center">
        <div class="small-note">Rows</div>
        <select id="ddlPerPage" class="form-select form-select-sm" style="width:auto;">
          <option>10</option>
          <option selected>25</option>
          <option>50</option>
          <option>100</option>
        </select>
        <button id="prevPage" class="btn btn-ghost btn-sm"><i class="bi bi-chevron-left"></i> Prev</button>
        <button id="nextPage" class="btn btn-ghost btn-sm">Next <i class="bi bi-chevron-right"></i></button>
      </div>
    </div>
    <div class="table-responsive">
      <table class="table table-hover align-middle table-sm">
        <thead class="table-light">
          <tr class="compact-header">
            <th style="width: 180px; padding: 0.4rem 0.5rem;"><i class="bi bi-tag me-1"></i>Type</th>
            <th style="width: 200px; padding: 0.4rem 0.5rem;"><i class="bi bi-building me-1"></i>Supplier</th>
            <th style="width: 160px; padding: 0.4rem 0.5rem;"><i class="bi bi-geo-alt me-1"></i>Destination</th>
            <th style="width: 130px; padding: 0.4rem 0.5rem;"><i class="bi bi-activity me-1"></i>Progress</th>
            <th style="width: 100px; padding: 0.4rem 0.5rem;"><i class="bi bi-flag me-1"></i>State</th>
            <th class="text-center" style="width: 80px; padding: 0.4rem 0.5rem;"><i class="bi bi-box-seam me-1"></i>Boxes</th>
            <th style="width: 150px; padding: 0.4rem 0.5rem;"><i class="bi bi-clock me-1"></i>Updated</th>
            <th class="text-end" style="width: 140px; padding: 0.4rem 0.5rem;">Actions</th>
          </tr>
        </thead>
        <tbody id="tblRows">
          <tr>
            <td colspan="8" class="text-center text-muted py-5">
              <div class="d-flex flex-column align-items-center gap-2">
                <i class="bi bi-inbox fs-1 opacity-50"></i>
                <div>No transfers found</div>
                <small class="text-muted">Create a new transfer to get started</small>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Detail Modal -->
<div class="modal fade" id="modalQuick" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-box-seam me-2"></i>Transfer</h5>
        <button class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div id="qBody" class="d-grid gap-3"></div>
      </div>
      <div class="modal-footer"><button class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button></div>
    </div>
  </div>
</div>

<!-- Create Transfer -->
<div class="modal fade" id="modalCreate" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Create Transfer</h5>
        <button class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form id="formCreate" class="needs-validation" novalidate>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Type</label>
            <select class="form-select" id="ct_type" required>
              <option value="STOCK">STOCK</option>
              <option value="JUICE">JUICE</option>
              <option value="STAFF">STAFF</option>
              <option value="RETURN">RETURN</option>
              <option value="PURCHASE_ORDER">PURCHASE_ORDER</option>
            </select>
          </div>
          <div class="mb-3" id="ct_supplier_wrap" style="display:none">
            <label class="form-label">Supplier</label>
            <select class="form-select" id="ct_supplier_select" required>
              <option value="">Choose supplier</option>
              <?php foreach ($supplierMap as $id => $name): ?>
                <option value="<?= htmlspecialchars($id, ENT_QUOTES) ?>"><?= htmlspecialchars($name, ENT_QUOTES) ?></option>
              <?php endforeach; ?>
            </select>
            <div class="invalid-feedback">Choose a supplier</div>
            <div class="small-note">Required for PURCHASE_ORDER</div>
          </div>
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">From (Outlet)</label>
              <select class="form-select" id="ct_from_select" required>
                <option value="">Choose outlet</option>
                <?php foreach ($outletMap as $id => $label): ?>
                  <option value="<?= htmlspecialchars($id, ENT_QUOTES) ?>"><?= htmlspecialchars($label, ENT_QUOTES) ?></option>
                <?php endforeach; ?>
              </select>
              <div class="invalid-feedback">Choose an outlet</div>
            </div>
            <div class="col-md-6">
              <label class="form-label">To (Outlet)</label>
              <select class="form-select" id="ct_to_select" required>
                <option value="">Choose outlet</option>
                <?php foreach ($outletMap as $id => $label): ?>
                  <option value="<?= htmlspecialchars($id, ENT_QUOTES) ?>"><?= htmlspecialchars($label, ENT_QUOTES) ?></option>
                <?php endforeach; ?>
              </select>
              <div class="invalid-feedback">Choose an outlet</div>
            </div>
          </div>
          <div class="mt-3">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" id="ct_add_products" checked>
              <label class="form-check-label" for="ct_add_products">Add products immediately after creating</label>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button class="btn btn-primary btn-elevated" type="submit">Create</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Action Modal -->
<div class="modal fade" id="modalAction" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 id="maTitle" class="modal-title">Action</h5><button class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div id="maBody" class="modal-body"></div>
      <div class="modal-footer">
        <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
        <button class="btn btn-primary btn-elevated" id="maSubmit">Save</button>
      </div>
    </div>
  </div>
</div>

<!-- Confirm Modal -->
<div class="modal fade" id="modalConfirm" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 id="mcTitle" class="modal-title">Confirm</h5><button class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div id="mcBody" class="modal-body">Are you sure?</div>
      <div class="modal-footer">
        <button class="btn btn-outline-secondary" data-bs-dismiss="modal">No</button>
        <button class="btn btn-danger btn-elevated" id="mcYes">Yes</button>
      </div>
    </div>
  </div>
</div>

<!-- Receiving Mode Selection Modal -->
<div class="modal fade" id="modalReceiving" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 id="receivingTitle" class="modal-title"><i class="bi bi-box-arrow-in-down me-2"></i>Choose Receiving Method</h5>
        <button class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="alert alert-info mb-4">
          <i class="bi bi-info-circle me-2"></i>
          <strong>Transfer Summary:</strong> <span id="receivingItemCount">0</span> items, <span id="receivingTotalQty">0</span> total units
        </div>

        <div class="row g-4">
          <!-- Option 1: Begin Receiving (Manual) -->
          <div class="col-md-6">
            <div class="card h-100 border-warning shadow-sm receiving-option-card">
              <div class="card-body d-flex flex-column">
                <div class="mb-3">
                  <div class="d-flex align-items-center mb-2">
                    <i class="bi bi-pencil-square fs-2 text-warning me-2"></i>
                    <h5 class="card-title mb-0">Begin Receiving</h5>
                  </div>
                  <p class="text-muted small mb-0">Manual entry mode</p>
                </div>

                <ul class="list-unstyled mb-4 flex-grow-1">
                  <li class="mb-2"><i class="bi bi-check-circle text-success me-2"></i>Enter actual received quantities</li>
                  <li class="mb-2"><i class="bi bi-check-circle text-success me-2"></i>Handle partial shipments</li>
                  <li class="mb-2"><i class="bi bi-check-circle text-success me-2"></i>Verify each item individually</li>
                  <li class="mb-2"><i class="bi bi-check-circle text-success me-2"></i>Complete when ready</li>
                </ul>

                <button class="btn btn-warning btn-lg w-100 btn-elevated" id="btnBeginReceiving">
                  <i class="bi bi-pencil-square me-2"></i>Begin Receiving
                </button>
              </div>
            </div>
          </div>

          <!-- Option 2: Receive All (Auto-Fill) -->
          <div class="col-md-6">
            <div class="card h-100 border-success shadow-sm receiving-option-card">
              <div class="card-body d-flex flex-column">
                <div class="mb-3">
                  <div class="d-flex align-items-center mb-2">
                    <i class="bi bi-lightning-charge-fill fs-2 text-success me-2"></i>
                    <h5 class="card-title mb-0">Receive All</h5>
                  </div>
                  <p class="text-muted small mb-0">Auto-complete instantly</p>
                </div>

                <ul class="list-unstyled mb-4 flex-grow-1">
                  <li class="mb-2"><i class="bi bi-check-circle text-success me-2"></i>Auto-fill all quantities</li>
                  <li class="mb-2"><i class="bi bi-check-circle text-success me-2"></i>Update inventory immediately</li>
                  <li class="mb-2"><i class="bi bi-check-circle text-success me-2"></i>Complete transfer in one click</li>
                  <li class="mb-2"><i class="bi bi-check-circle text-success me-2"></i>Sync to Lightspeed instantly</li>
                </ul>

                <button class="btn btn-success btn-lg w-100 btn-elevated" id="btnReceiveAll">
                  <i class="bi bi-lightning-charge-fill me-2"></i>Receive All Now
                </button>
              </div>
            </div>
          </div>
        </div>

        <div class="alert alert-light border mt-4 mb-0">
          <div class="d-flex align-items-start">
            <i class="bi bi-lightbulb text-primary me-2 mt-1"></i>
            <div class="small">
              <strong>Tip:</strong> Use <strong>"Begin Receiving"</strong> if you need to verify each item or handle partial deliveries.
              Use <strong>"Receive All"</strong> for complete shipments where all items arrived as expected.
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer bg-light">
        <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
      </div>
    </div>
  </div>
</div>

<!-- Activity overlay + toast container -->
<div id="globalActivity" aria-live="polite" aria-atomic="true" style="display:none;">
  <div class="ga-box">
    <div class="spinner-border" role="status" aria-hidden="true"></div>
    <div>
      <div id="gaTitle" class="fw-semibold">Working…</div>
      <div id="gaSub" class="small-note">Please wait</div>
    </div>
  </div>
</div>
<div class="toast-container" id="toastContainer" aria-live="polite" aria-atomic="true"></div>

</div><!-- /.transfer-manager-wrap -->
