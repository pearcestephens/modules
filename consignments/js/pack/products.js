import { post } from '../core/api.js';
import { toast } from '../core/ui.js';

export function bindAddProducts(root, c) {
  const openBtn = root.querySelector('.js-add-product');
  const modal = document.getElementById('addProductsModal');
  const input = modal?.querySelector('.js-ap-search');
  const btnSearch = modal?.querySelector('.js-ap-search-btn');
  const tbody = modal?.querySelector('.js-ap-tbody');
  const bulkBtn = modal?.querySelector('.js-ap-bulk-add');

  const renderRow = (p) => {
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td><code>${p.sku ?? ''}</code></td>
      <td>${p.name ?? p.product_id}</td>
      <td>${parseInt(p.stock ?? 0,10)}</td>
      <td><input type="number" class="form-control form-control-sm js-ap-qty" min="0" value="1" style="width:110px"></td>
      <td><button type="button" class="btn btn-sm btn-primary js-ap-add-one">Add</button></td>
    `;
    tr.dataset.productId = p.product_id;
    tr.dataset.sku = p.sku ?? '';
    tr.dataset.name = p.name ?? p.product_id;
    tr.dataset.stock = String(p.stock ?? 0);
    return tr;
  };

  const doSearch = async () => {
    const q = input.value.trim();
    if (q.length < 2) { tbody.innerHTML = `<tr><td colspan="5" class="text-muted">Enter 2+ chars.</td></tr>`; return; }
    tbody.innerHTML = `<tr><td colspan="5">Searching…</td></tr>`;
    try {
      const res = await post(`${c.apiBase}/search_products.php`, {
        csrf: c.csrf, q, outlet_from: input.dataset.outletFrom || ''
      });
      const list = res?.data || [];
      if (!list.length) { tbody.innerHTML = `<tr><td colspan="5" class="text-muted">No results.</td></tr>`; return; }
      tbody.innerHTML = '';
      list.forEach(p => tbody.appendChild(renderRow(p)));
    } catch (err) {
      tbody.innerHTML = `<tr><td colspan="5" class="text-danger">Error: ${err.message}</td></tr>`;
    }
  };

  const appendRealRow = ({ item_id, product_id, qty_requested, name, sku }) => {
    const table = root.querySelector('.js-pack-table tbody');
    // If a row for this item already exists, bump planned (client-only view); server truth is already updated
    const existing = table.querySelector(`tr[data-item-id="${item_id}"]`);
    if (existing) {
      const plannedEl = existing.querySelector('input[name$="[qty_planned]"]');
      if (plannedEl) plannedEl.value = String(qty_requested);
      existing.querySelector('.rem-cell').textContent = String(qty_requested);
      return;
    }
    const tr = document.createElement('tr');
    tr.dataset.itemId = String(item_id);
    tr.dataset.productId = product_id;
    tr.innerHTML = `
      <td><code>${sku || product_id}</code></td>
      <td>${name || product_id}</td>
      <td><span class="js-planned-text">${qty_requested}</span>
          <input type="hidden" name="lines[${item_id}][qty_planned]" value="${qty_requested}">
          <input type="hidden" name="lines[${item_id}][product_id]" value="${product_id}">
      </td>
      <td>
        <input class="form-control form-control-sm qty-input" type="number" min="0"
               name="lines[${item_id}][qty_packed]" value="0">
      </td>
      <td class="rem-cell">${qty_requested}</td>
      <td style="width:1%;white-space:nowrap;">
        <button type="button" class="btn btn-sm btn-light js-line-minus">–</button>
        <button type="button" class="btn btn-sm btn-outline-danger js-line-remove">Remove</button>
      </td>
    `;
    table.appendChild(tr);
  };

  const addOne = async (tr) => {
    const qty = Math.max(0, parseInt(tr.querySelector('.js-ap-qty')?.value || '0', 10) || 0);
    if (qty <= 0) { toast(root, 'Enter a quantity', 'warning'); return; }
    const productId = tr.dataset.productId;
    const name = tr.dataset.name;
    const sku = tr.dataset.sku;

    const res = await post(`${c.apiBase}/add_line.php`, {
      csrf: c.csrf, transfer_id: c.transferId, product_id: productId, qty_requested: qty, nonce: crypto.randomUUID?.() || String(Date.now())
    });
    if (!res?.ok) throw new Error(res?.error || 'Add failed');

    appendRealRow({ item_id: res.item_id, product_id, qty_requested: res.qty_requested, name, sku });
    toast(root, `Added ${name} × ${qty}`, 'success');
  };

  const bulkAdd = async () => {
    let added = 0;
    for (const tr of tbody.querySelectorAll('tr')) {
      const qty = Math.max(0, parseInt(tr.querySelector('.js-ap-qty')?.value || '0', 10) || 0);
      if (qty > 0) { await addOne(tr); added++; }
    }
    toast(root, `Added ${added} products`, 'success');
  };

  // open/search (Bootstrap 4 modal API)
  openBtn?.addEventListener('click', () => {
    try {
      if (window.jQuery && typeof jQuery.fn.modal === 'function') {
        jQuery(modal).modal('show');
      } else {
        // Fallback if BS4 JS not present
        modal.classList.add('show');
        modal.style.display = 'block';
        modal.setAttribute('aria-modal', 'true');
        modal.removeAttribute('aria-hidden');
        document.body.classList.add('modal-open');
      }
    } catch (e) { console.error('Modal open failed', e); }
    tbody.innerHTML = `<tr class="vt-empty"><td colspan="5" class="text-muted">No results yet.</td></tr>`;
    input.value = ''; input.focus();
  });
  btnSearch?.addEventListener('click', doSearch);
  input?.addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); doSearch(); } });

  tbody?.addEventListener('click', async (e) => {
    if (e.target.closest('.js-ap-add-one')) {
      try { await addOne(e.target.closest('tr')); } catch (err) { toast(root, err.message, 'error'); }
    }
  });

  bulkBtn?.addEventListener('click', async () => {
    try { await bulkAdd(); } catch (err) { toast(root, err.message, 'error'); }
  });

  // Row actions on main table: decrement planned, remove line
  root.querySelector('.js-pack-table tbody')?.addEventListener('click', async (e) => {
    const tr = e.target.closest('tr[data-item-id]');
    if (!tr) return;
    const itemId = parseInt(tr.dataset.itemId, 10);
    if (e.target.closest('.js-line-remove')) {
      try {
        await post(`${c.apiBase}/remove_line.php`, { csrf: c.csrf, transfer_id: c.transferId, item_id: itemId });
        tr.remove();
        toast(root, 'Line removed', 'success');
      } catch (err) { toast(root, err.message, 'error'); }
    }
    if (e.target.closest('.js-line-minus')) {
      const plannedHidden = tr.querySelector('input[name$="[qty_planned]"]');
      const plannedText   = tr.querySelector('.js-planned-text');
      let v = Math.max(0, parseInt(plannedHidden?.value || '0', 10) - 1);
      try {
        const res = await post(`${c.apiBase}/update_line_qty.php`, { csrf: c.csrf, transfer_id: c.transferId, item_id: itemId, qty_requested: v });
        plannedHidden.value = String(res.qty_requested);
        plannedText.textContent = String(res.qty_requested);
        tr.querySelector('.rem-cell').textContent = String(res.qty_requested);
      } catch (err) { toast(root, err.message, 'error'); }
    }
  });
}
