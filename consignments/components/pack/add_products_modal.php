<?php
/** Requires: $transferId, $outletFrom, $outletTo (already provided in pack.php) */
?>
<div class="modal fade" id="addProductsModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content vt-modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Add Products to Transfer #<?= (int)$transferId ?></h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <div class="row g-2">
          <div class="col-md-8">
            <label class="form-label">Search by name / SKU</label>
            <input type="text" class="form-control js-ap-search" placeholder="Type 2+ chars…"
                   data-outlet-from="<?= htmlspecialchars($outletFrom) ?>">
          </div>
          <div class="col-md-4 d-flex align-items-end">
            <button type="button" class="btn btn-primary w-100 js-ap-search-btn">Search</button>
          </div>
        </div>

        <div class="vt-ap-results mt-3">
          <table class="table vt-table vt-ap-table">
            <thead>
              <tr><th>SKU</th><th>Name</th><th>Stock (src)</th><th style="width:120px">Add Qty</th><th></th></tr>
            </thead>
            <tbody class="js-ap-tbody">
              <tr class="vt-empty"><td colspan="5" class="text-muted">No results yet.</td></tr>
            </tbody>
          </table>
        </div>

        <div class="d-flex justify-content-between small text-muted">
          <div>Source Outlet: <code><?= htmlspecialchars($outletFrom) ?></code></div>
          <div>Destination: <code><?= htmlspecialchars($outletTo) ?></code></div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-dismiss="modal">Close</button>
        <button class="btn btn-success js-ap-bulk-add">Add All Entered</button>
      </div>
    </div>
  </div>
</div>
