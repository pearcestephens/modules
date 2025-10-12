  <div class="vt-shipping">
    <h2 class="h5">Shipping &amp; Labels</h2>
    <div class="row g-3">
      <div class="col-md-3">
        <label class="form-label">Method</label>
        <select class="form-select" name="delivery_mode" required>
          <option value="manual_courier">Manual Courier</option>
          <option value="pickup">Pickup</option>
          <option value="dropoff">Drop‑off</option>
          <option value="internal_drive">Internal Drive</option>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label">Box count</label>
        <input class="form-control" type="number" min="1" name="box_count" value="1" required>
      </div>
      <div class="col-md-3">
        <label class="form-label">Contains nicotine?</label>
        <select class="form-select" name="nicotine_in_shipment">
          <option value="0" selected>No</option>
          <option value="1">Yes</option>
        </select>
      </div>
    </div>

    <div class="vt-parcels mt-3 js-parcels">
      <!-- JS will render per‑box tracking + weights + dims + allocations -->
    </div>
  </div>
