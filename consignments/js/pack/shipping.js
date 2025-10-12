export function bindShipping(root) {
  const boxCountInput = root.querySelector('input[name="box_count"]');
  const parcelsWrap = root.querySelector('.js-parcels');
  const rows = Array.from(root.querySelectorAll('.js-pack-table tbody tr'))
    .map(tr => ({
      itemId: tr.getAttribute('data-item-id'),
      planned: parseInt(tr.querySelector('input[name$="[qty_planned]"]').value || '0', 10),
      productId: tr.querySelector('input[name$="[product_id]"]').value
    }));

  const renderAllocTable = (i) => {
    const tbl = document.createElement('table');
    tbl.className = 'table table-sm vt-alloc-table';
    tbl.innerHTML = `
      <thead><tr><th>Item ID</th><th>Product</th><th>Allocate to Box ${i+1}</th></tr></thead>
      <tbody></tbody>`;
    const tb = tbl.querySelector('tbody');
    rows.forEach(r => {
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td>${r.itemId}</td>
        <td><code>${r.productId}</code></td>
        <td>
          <input class="form-control form-control-sm" type="number" min="0"
                 name="parcel_allocations[${i}][${r.itemId}]"
                 placeholder="0">
        </td>`;
      tb.appendChild(tr);
    });
    return tbl;
  };

  const render = () => {
    const n = Math.max(1, parseInt(boxCountInput.value || '1', 10));
    parcelsWrap.innerHTML = '';
    for (let i = 0; i < n; i++) {
      const card = document.createElement('div');
      card.className = 'vt-parcel';
      card.innerHTML = `
        <div class="row g-2 align-items-end">
          <div class="col-md-4">
            <label class="form-label">Tracking # (Box ${i+1})</label>
            <input class="form-control" name="tracking[${i}]" required>
          </div>
          <div class="col-md-2">
            <label class="form-label">Weight (g)</label>
            <input class="form-control" type="number" min="0" name="weight_grams[${i}]">
          </div>
          <div class="col-md-4">
            <label class="form-label">Dims (L×W×H mm)</label>
            <div class="d-flex gap-2">
              <input class="form-control" type="number" min="0" name="dims[${i}][l]" placeholder="L">
              <input class="form-control" type="number" min="0" name="dims[${i}][w]" placeholder="W">
              <input class="form-control" type="number" min="0" name="dims[${i}][h]" placeholder="H">
            </div>
          </div>
          <div class="col-md-2">
            <button type="button" class="btn btn-outline-secondary btn-sm js-toggle-alloc" data-i="${i}">
              Allocate Items
            </button>
          </div>
        </div>
        <div class="vt-alloc-panel collapse" data-box="${i}"></div>
      `;
      parcelsWrap.appendChild(card);

      // Allocation panel
      const panel = card.querySelector('.vt-alloc-panel');
      panel.appendChild(renderAllocTable(i));

      // Toggle panel visibility
      card.querySelector('.js-toggle-alloc')?.addEventListener('click', () => {
        panel.classList.toggle('collapse');
      });
    }
  };

  boxCountInput?.addEventListener('input', render);
  render();
}
