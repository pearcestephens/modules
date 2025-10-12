/* expects core.bundle.js loaded first */
;
import { sumInputs } from '../core/table.js';

export function bindPackTable(root) {
  const table = root.querySelector('.js-pack-table');
  const recalc = () => sumInputs('.js-pack-table tbody tr', '.qty-input', '.rem-cell');
  table?.addEventListener('input', (e) => {
    if (e.target.matches('.qty-input')) recalc();
  });
  recalc();
}

;
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

;
import { post } from '../core/api.js';
import { toast } from '../core/ui.js';

export function bindPackActions(root, c) {
  const form = root.querySelector('#packForm');

  form?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const fd = new FormData(form);
    try {
      const res = await post(`${c.apiBase}/pack_submit.php`, fd);
      toast(root, `Packed. Queue Log #${res.queue_log_id}`, 'success');
      setTimeout(() => location.reload(), 600);
    } catch (err) {
      toast(root, `Pack failed: ${err.message}`, 'error');
    }
  });

  root.querySelector('.js-print-boxlabels')?.addEventListener('click', () => {
    window.print();
  });
}

;
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

;
import { toast } from '../core/ui.js';

const LS_KEY = 'vs_default_printer';

function mockDetectPrinters() {
  // Replace this with a real detector later if you have an agent; this is UI-complete for now.
  return Promise.resolve([
    { id: 'ZPL-Shipping-01', name: 'Zebra ZPL – Shipping 01' },
    { id: 'Office-Laser-Front', name: 'Office Laser – Front' },
    { id: 'Warehouse-Label-A', name: 'Warehouse Label A' },
  ]);
}

function getDefaultPrinter() {
  try { return localStorage.getItem(LS_KEY) || ''; } catch { return ''; }
}
function setDefaultPrinter(id) {
  try { localStorage.setItem(LS_KEY, id || ''); } catch {}
}

export function bindPrinters(root) {
  const sel = root.querySelector('.js-printers-select');
  const status = root.querySelector('.js-printers-status');
  const btnRefresh = root.querySelector('.js-printers-refresh');
  const btnTest = root.querySelector('.js-printers-test');
  const btnPrintAll = root.querySelector('.js-print-all');
  const btnShowPrinters = root.querySelector('.js-show-printers');

  const printersSection = root.querySelector('.vt-block--printers');

  const render = (list) => {
    const def = getDefaultPrinter();
    sel.innerHTML = '';
    list.forEach(p => {
      const opt = document.createElement('option');
      opt.value = p.id; opt.textContent = p.name;
      if (p.id === def) opt.selected = true;
      sel.appendChild(opt);
    });
    status.textContent = def ? `Default: ${def}` : 'No printer selected.';
  };

  const detect = async () => {
    sel.innerHTML = '<option value="">Detecting…</option>';
    const list = await mockDetectPrinters();
    render(list);
  };

  sel?.addEventListener('change', () => {
    const v = sel.value;
    setDefaultPrinter(v);
    status.textContent = v ? `Default: ${v}` : 'No printer selected.';
    toast(root, 'Default printer saved', 'success');
  });

  btnRefresh?.addEventListener('click', detect);

  btnTest?.addEventListener('click', () => {
    const def = getDefaultPrinter();
    if (!def) { toast(root, 'Select a printer first', 'warning'); return; }
    // For now, a visual confirmation (you can route to your print job API here)
    toast(root, `Sent test label to ${def}`, 'success');
    // Example future hook:
    // post('/print-api.php', { printer: def, payload: <zpl> });
  });

  btnPrintAll?.addEventListener('click', () => {
    const def = getDefaultPrinter();
    if (!def) { toast(root, 'Select a printer first', 'warning'); return; }
    // Collect current box count to “print”
    const n = parseInt(root.querySelector('input[name="box_count"]')?.value || '0', 10);
    if (n <= 0) { toast(root, 'Set a valid box count first', 'warning'); return; }
    toast(root, `Printed ${n} box labels on ${def}`, 'success');
  });

  btnShowPrinters?.addEventListener('click', () => {
    printersSection?.scrollIntoView({ behavior: 'smooth', block: 'start' });
  });

  detect();
}

;
import { cfg } from '../core/config.js';
import { mountDraftControls } from '../core/storage.js';
import { bindPackTable } from './table-pack.js';
import { bindShipping } from './shipping.js';
import { bindPackActions } from './actions-pack.js';
import { bindAddProducts } from './products.js';
import { bindPrinters } from './printers.js';

export function initPack(options) {
  const c = cfg(options);
  const root = document.querySelector('.vs-transfer--pack');
  if (!root) return;

  fetch(`${c.apiBase}/pack_lock.php`, {
    method: 'POST',
    body: new URLSearchParams({ csrf: c.csrf, transfer_id: String(c.transferId), op: 'acquire', ttl_min: '10' })
  });

  mountDraftControls(root, c);
  bindPackTable(root);
  bindShipping(root);
  bindPackActions(root, c);
  bindAddProducts(root, c);
  bindPrinters(root);

  const beat = setInterval(() => {
    fetch(`${c.apiBase}/pack_lock.php`, {
      method: 'POST',
      body: new URLSearchParams({ csrf: c.csrf, transfer_id: String(c.transferId), op: 'heartbeat', ttl_min: '10' })
    });
  }, 60_000);

  window.addEventListener('beforeunload', () => {
    clearInterval(beat);
    navigator.sendBeacon?.(`${c.apiBase}/pack_lock.php`, new URLSearchParams({
      csrf: c.csrf, transfer_id: String(c.transferId), op: 'release'
    }));
  });
}
