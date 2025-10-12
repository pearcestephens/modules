<?php
if (!isset($transferId) || (int)$transferId <= 0) {
  echo '<div class="alert alert-danger">Courier Console requires a valid transfer id.</div>';
  return;
}
$transferId = (int)$transferId;
$currentUserId = isset($currentUserId) ? (int)$currentUserId : 0;
?>
<div id="courier-console" class="cxc card shadow-sm mt-4"
     data-transfer-id="<?php echo $transferId; ?>"
     data-user-id="<?php echo $currentUserId; ?>">

       <!-- server-computed weights (grams) -->
  <input type="hidden" id="cx-planned-weight-g" value="<?= (int)$plannedWeightTotalGrams ?>">
  <input type="hidden" id="cx-actual-weight-g"  value="<?= (int)$initialActualWeightGrams ?>">

  <!-- Header (gradient + mode strip) -->
  <div class="card-header cxc-header d-flex align-items-center justify-content-between flex-wrap">
    <div class="h5 mb-0 text-white">Courier Console</div>
    <div class="btn-group btn-group-sm" role="group" aria-label="mode">
      <button class="btn btn-outline-light active" data-mode="auto"   title="Live quote & print">Auto</button>
      <button class="btn btn-outline-light"        data-mode="manual" title="Record manual dispatch">Manual</button>
      <button class="btn btn-outline-light"        data-mode="dropoff" title="Drop at a branch">Drop‑off</button>
      <button class="btn btn-outline-light"        data-mode="pickup"  title="Schedule pickup">Pickup</button>
      <button id="cx-settings" class="btn btn-warning" title="Preferences">
        <i class="fa fa-sliders-h" aria-hidden="true"></i><span class="sr-only">Settings</span>
      </button>
    </div>
  </div>

  <div class="card-body p-0">
    <div class="row no-gutters">
      <!-- LEFT: action zone -->
      <div class="col-lg-4 cxc-left p-3 cxc-col-divider">
        <div class="d-flex align-items-center flex-wrap">
          <strong>Suggested</strong>
          <span id="cx-status" class="badge ml-2">Booting…</span>
          <span id="cx-ttl" class="badge ml-2">TTL —</span>
        </div>
        <div id="cx-suggest" class="small text-muted mt-2">Auto‑pack + live rates coming…</div>
        <div class="small text-muted mt-1">Request ID: <code id="cx-rid">—</code></div>

        <div class="mt-3">
          <button id="cx-primary" class="btn cxc-btn-primary btn-block" disabled>Print &amp; Pack</button>
          <button id="cx-print-only" class="btn cxc-btn-secondary btn-block mt-2" disabled>Print Only</button>
          <div class="d-flex justify-content-between mt-2">
            <button id="cx-reprint" class="btn btn-light btn-sm" disabled>Reprint</button>
            <button id="cx-cancel" class="btn btn-outline-danger btn-sm" disabled>Cancel</button>
          </div>
        </div>

        <!-- State / error banner -->
        <div id="cx-banner" class="cxc-banner mt-3" role="status" aria-live="polite" hidden></div>
      </div>

      <!-- RIGHT: content panels -->
      <div class="col-lg-8 p-3">

        <!-- Parcels (Auto & Manual) -->
        <div class="mb-3 cxc-panel" data-panel="parcels">
          <div class="cxc-panel-head d-flex align-items-center justify-content-between">
            <strong>Parcels</strong>
            <div class="btn-group btn-group-sm">
              <button id="cx-auto-pack"   class="btn btn-outline-primary" title="Compute from item weights">Auto</button>
              <button id="cx-add-satchel" class="btn btn-outline-primary">Satchel</button>
              <button id="cx-add-box"     class="btn btn-outline-primary">Box</button>
            </div>
          </div>
          <div class="cxc-panel-body">
            <div class="table-responsive">
              <table class="table table-sm table-hover mb-0 cxc-table">
                <thead>
                <tr>
                  <th class="cxc-col-idx">#</th>
                  <th class="cxc-col-type">Type</th>
                  <th class="cxc-col-dims">Dims (L×W×H mm)</th>
                  <th class="cxc-col-weight">Weight (g)</th>
                  <th class="cxc-col-assign">Assign</th>
                  <th class="cxc-col-del"></th>
                </tr>
                </thead>
                <tbody id="cx-parcel-rows">
                <tr><td colspan="6" class="text-muted">No parcels</td></tr>
                </tbody>
              </table>
            </div>
            <div class="small text-muted mt-2">Satchel ≤ 2 kg; otherwise box (+ box adder). Staff can adjust.</div>
            <div id="cx-weight-intel" class="small text-muted mt-1"></div>
          </div>
        </div>

        <!-- Rates (Auto only) -->
        <div class="mb-3 cxc-panel" data-panel="rates">
          <div class="cxc-panel-head d-flex align-items-center justify-content-between">
            <strong class="d-inline-flex align-items-center">Rates
              <span id="cx-quote-spin" class="cxc-spin ml-2" hidden></span>
            </strong>
            <div class="d-inline-flex align-items-center">
              <label class="mr-3 small mb-0"><input type="checkbox" id="cx-sig" checked> Signature</label>
              <label class="mr-3 small mb-0"><input type="checkbox" id="cx-sat"> Saturday</label>
              <span class="small text-muted">R18 disabled</span>
            </div>
          </div>
          <div class="cxc-panel-body">
            <div class="table-responsive">
              <table class="table table-sm mb-0 cxc-table">
                <thead>
                <tr>
                  <th style="width:32px;"></th>
                  <th>Carrier</th>
                  <th>Service</th>
                  <th>Container</th>
                  <th class="text-right">Cost</th>
                  <th>Notes</th>
                </tr>
                </thead>
                <tbody id="cx-rate-rows">
                <tr><td colspan="6" class="text-muted cxc-empty">No rates yet.</td></tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <!-- Destination (always visible) -->
        <div class="mb-3 cxc-panel" data-panel="destination">
          <div class="cxc-panel-head d-flex align-items-center justify-content-between">
            <strong>Destination</strong>
            <button id="cx-edit-address" class="btn btn-light btn-sm">Edit / Validate</button>
          </div>
          <div class="cxc-panel-body">
            <div class="small text-muted">Preloaded from outlet destination; change only if the courier rejects.</div>
          </div>
        </div>

        <!-- Manual (Manual mode) -->
        <div class="mb-3 cxc-panel" data-panel="manual">
          <div class="cxc-panel-head"><strong>Manual Dispatch</strong></div>
          <div class="cxc-panel-body">
            <div class="form-row">
              <div class="form-group col-md-4">
                <label>Carrier</label>
                <select id="man_carrier" class="form-control form-control-sm">
                  <option value="INTERNAL">Internal</option>
                  <option value="GSS">NZ Couriers (GSS)</option>
                  <option value="NZ_POST">NZ Post</option>
                  <option value="OTHER">Other</option>
                </select>
              </div>
              <div class="form-group col-md-4">
                <label>Tracking / Ref</label>
                <input id="man_tracking" class="form-control form-control-sm" placeholder="e.g. KCN1234567NZ">
              </div>
              <div class="form-group col-md-4">
                <label>Notes</label>
                <input id="man_notes" class="form-control form-control-sm" placeholder="Optional">
              </div>
            </div>
            <div class="small text-muted">Enter accurate dimensions/weights above in Parcels.</div>
          </div>
        </div>

        <!-- Pickup (Pickup mode) -->
        <div class="mb-3 cxc-panel" data-panel="pickup">
          <div class="cxc-panel-head"><strong>Pickup Details</strong></div>
          <div class="cxc-panel-body">
            <div class="form-row">
              <div class="form-group col-md-4"><label>Ready at</label><input id="pu_ready" type="time" class="form-control form-control-sm"></div>
              <div class="form-group col-md-4"><label>Close by</label><input id="pu_close" type="time" class="form-control form-control-sm"></div>
              <div class="form-group col-md-4"><label>Date</label><input id="pu_date" type="date" class="form-control form-control-sm"></div>
            </div>
            <div class="form-row">
              <div class="form-group col-md-6"><label>Contact name</label><input id="pu_contact" class="form-control form-control-sm"></div>
              <div class="form-group col-md-6"><label>Contact phone</label><input id="pu_phone" class="form-control form-control-sm"></div>
            </div>
          </div>
        </div>

        <!-- Drop‑off (Drop-off mode) -->
        <div class="mb-3 cxc-panel" data-panel="dropoff">
          <div class="cxc-panel-head"><strong>Drop‑off Details</strong></div>
          <div class="cxc-panel-body">
            <div class="form-row">
              <div class="form-group col-md-6"><label>Location / Branch</label><input id="do_location" class="form-control form-control-sm" placeholder="Courier depot / branch"></div>
              <div class="form-group col-md-6"><label>Reference</label><input id="do_ref" class="form-control form-control-sm" placeholder="Manifest / Ref"></div>
            </div>
            <div class="form-row">
              <div class="form-group col-12"><label>Notes</label><input id="do_notes" class="form-control form-control-sm" placeholder="Optional"></div>
            </div>
          </div>
        </div>

      </div>
    </div>
  </div>
</div>

<!-- Settings Drawer (advanced only) -->
<div class="modal fade" id="cxSettingsModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-dialog-slideout modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Courier Settings</h5><button type="button" class="close" data-dismiss="modal" aria-label="Close"><span>&times;</span></button></div>
      <div class="modal-body">
        <div class="row">
          <div class="col-md-6">
            <h6>Packaging</h6>
            <div class="form-group form-check">
              <input type="checkbox" class="form-check-input" id="prefSatchel" checked>
              <label class="form-check-label" for="prefSatchel">Prefer satchel</label>
            </div>
            <div class="form-group"><label>Box weight adder (g)</label><input type="number" class="form-control form-control-sm" id="boxAdder" value="300"></div>
            <div class="form-group"><label>Volumetric divisor</label><input type="number" class="form-control form-control-sm" id="volDiv" value="5000"></div>
          </div>
          <div class="col-md-6">
            <h6>Rules</h6>
            <div class="form-group form-check">
              <input type="checkbox" class="form-check-input" id="forceSignature" checked>
              <label class="form-check-label" for="forceSignature">Force Signature</label>
            </div>
            <div class="form-group form-check">
              <input type="checkbox" class="form-check-input" id="allowSaturday">
              <label class="form-check-label" for="allowSaturday">Allow Saturday</label>
            </div>
            <div class="form-group"><label>Preferred carrier</label>
              <select id="prefCarrier" class="form-control form-control-sm">
                <option value="auto" selected>Auto</option>
                <option value="GSS">NZ Couriers (GSS)</option>
                <option value="NZ_POST">NZ Post</option>
                <option value="INTERNAL">Internal</option>
              </select>
            </div>
          </div>
        </div>
        <div class="small text-muted">These settings are for experienced users and are saved per user.</div>
      </div>
      <div class="modal-footer"><button class="btn btn-light" data-dismiss="modal">Close</button><button id="saveSettings" class="btn btn-primary">Save</button></div>
    </div>
  </div>
</div>

<!-- Address Modal -->
<div class="modal fade" id="cxAddressModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Edit / Validate Address</h5><button type="button" class="close" data-dismiss="modal" aria-label="Close"><span>&times;</span></button></div>
      <div class="modal-body">
        <div class="form-row">
          <div class="form-group col-md-6"><label>Name</label><input id="addr_name" class="form-control"></div>
          <div class="form-group col-md-6"><label>Company</label><input id="addr_company" class="form-control"></div>
          <div class="form-group col-md-8"><label>Address 1</label><input id="addr_line1" class="form-control"></div>
          <div class="form-group col-md-4"><label>Address 2</label><input id="addr_line2" class="form-control"></div>
          <div class="form-group col-md-4"><label>Suburb</label><input id="addr_suburb" class="form-control"></div>
          <div class="form-group col-md-4"><label>City</label><input id="addr_city" class="form-control"></div>
          <div class="form-group col-md-4"><label>Postcode</label><input id="addr_postcode" class="form-control"></div>
          <div class="form-group col-md-6"><label>Email</label><input id="addr_email" class="form-control"></div>
          <div class="form-group col-md-6"><label>Phone</label><input id="addr_phone" class="form-control"></div>
        </div>
        <div class="d-flex align-items-center">
          <button id="addrValidate" class="btn btn-outline-primary mr-2">Validate</button>
          <div id="addrStatus" class="small text-muted">NZ Post address suggestions handled by backend.</div>
        </div>
      </div>
      <div class="modal-footer"><button class="btn btn-light" data-dismiss="modal">Close</button><button id="addrSave" class="btn btn-primary">Save</button></div>
    </div>
  </div>
</div>

<link rel="stylesheet" href="/modules/transfers/stock/assets/css/courier_console.css?v=cxc4">
<script src="/modules/transfers/stock/assets/js/courier_console.js?v=cxc4" defer></script>
