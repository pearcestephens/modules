/* expects core.bundle.js loaded first */
;
export function bindReceiveTable(root) {
  const table = root.querySelector('.js-receive-table');
  const stats = {
    items: root.querySelector('.js-stat-items'),
    planned: root.querySelector('.js-stat-planned'),
    received: root.querySelector('.js-stat-received'),
    diff: root.querySelector('.js-stat-diff')
  };
  const recalc = () => {
    let items = 0, planned = 0, received = 0;
    table.querySelectorAll('tbody tr').forEach(tr => {
      items++;
      const sent = parseInt(tr.children[2].textContent || '0', 10);
      const inp = tr.querySelector('.qty-input');
      const val = parseInt(inp?.value || '0', 10);
      planned += (isNaN(sent) ? 0 : sent);
      received += (isNaN(val) ? 0 : val);
    });
    stats.items.textContent = String(items);
    stats.planned.textContent = String(planned);
    stats.received.textContent = String(received);
    stats.diff.textContent = String(planned - received);
  };
  table?.addEventListener('input', recalc);
  recalc();
}

;
export function bindFilters(root) {
  root.querySelectorAll('.js-filter').forEach(btn => {
    btn.addEventListener('click', () => {
      // Placeholder—hook with server filtering if needed (keeping consistent UI).
      btn.classList.toggle('active');
    });
  });
};

export function mountConfidence(root) {
  const bar = root.querySelector('.js-confidence');
  const digest = root.querySelector('.js-digest');
  const update = () => {
    const planned = parseInt(root.querySelector('.js-stat-planned').textContent || '0', 10);
    const received = parseInt(root.querySelector('.js-stat-received').textContent || '0', 10);
    const pct = planned ? Math.round((received / planned) * 100) : 0;
    bar.style.width = pct + '%';
    digest.textContent = pct >= 100 ? 'All quantities matched.' : `Confidence: ${pct}%`;
  };
  root.addEventListener('input', update);
  update();
};


import { post } from '../core/api.js';
import { toast } from '../core/ui.js';

export function bindReceiveActions(root, c) {
  const form = root.querySelector('#receiveForm');
  const setLoading = (btn, on) => {
    if (!btn) return;
    if (on) {
      btn.setAttribute('disabled', 'disabled');
      btn.setAttribute('aria-busy', 'true');
      let sp = btn.querySelector('.vt-btn-spinner');
      if (!sp) {
        sp = document.createElement('span');
        sp.className = 'vt-btn-spinner spinner-border spinner-border-sm me-1';
        sp.setAttribute('role', 'status');
        sp.setAttribute('aria-hidden', 'true');
        btn.prepend(sp);
      }
    } else {
      btn.removeAttribute('disabled');
      btn.setAttribute('aria-busy', 'false');
      btn.querySelector('.vt-btn-spinner')?.remove();
    }
  };

  form?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const fd = new FormData(form);
    // Consistent idempotency key from UI
    fd.append('nonce', (crypto?.randomUUID?.() || String(Date.now())));
    // Prevent double-submit
    if (form.dataset.inFlight === '1') return;
    form.dataset.inFlight = '1';
  const submitBtn = form.querySelector('[type="submit"]');
  setLoading(submitBtn, true);

    try {
      const res   = await post(`${c.apiBase}/receive_submit.php`, fd);
      const flash = res.complete ? 'receive_complete' : 'receive_partial';
      const rid   = res.receipt_id ? `&rid=${encodeURIComponent(res.receipt_id)}` : '';
      // Prefer server-provided redirect_url if present
      const next  = res?.redirect_url || `/modules/consignments/?flash=${flash}&tx=${encodeURIComponent(c.transferId)}${rid}`;
      toast(root, `✅ Receive ${res.complete ? 'complete' : 'partial'} saved. Redirecting…`, 'success');
      window.location.href = next;
    } catch (err) {
      toast(root, `Receive failed: ${err.message}`, 'error');
      setLoading(submitBtn, false);
      delete form.dataset.inFlight;
    }
  });

  // keep existing modal close hook if the DOM has it
  document.querySelectorAll('#receiveSuccess [data-bs-dismiss="modal"], #receiveSuccess [data-dismiss="modal"]').forEach((el) => {
    el.addEventListener('click', () => {
      document.getElementById('receiveSuccess')?.classList.remove('vt-modal--open');
    });
  });
};


import { cfg } from '../core/config.js';
import { mountDraftControls } from '../core/storage.js';
import { bindReceiveTable } from './table-receive.js';
import { bindFilters } from './filters.js';
import { mountConfidence } from './confidence.js';
import { bindReceiveActions } from './actions-receive.js';
import { mountKeyboardScanner } from '../core/scanner.js';

export function initReceive(options) {
  const c = cfg(options);
  const root = document.querySelector('.vs-transfer--receive');
  if (!root) return;

  mountDraftControls(root, c);
  bindFilters(root);
  bindReceiveTable(root);
  mountConfidence(root);
  bindReceiveActions(root, c);

  let unmount = null;
  if (c.enableScanner) {
    unmount = mountKeyboardScanner({
      root,
      onCode: (code) => {
        // Try to match a row by SKU or product_id stored as data attributes.
        const esc = (s) => (window.CSS && CSS.escape) ? CSS.escape(s) : String(s).replace(/[^a-zA-Z0-9_\-]/g, '\\$&');
        const row = root.querySelector(`tr[data-sku="${esc(code)}"], tr[data-product-id="${esc(code)}"]`)
          || root.querySelector('.js-receive-table tbody tr'); // fallback: first row
        const input = row?.querySelector('.qty-input');
        if (!input) return;
        input.value = String((parseInt(input.value || '0', 10) || 0) + 1);
        input.dispatchEvent(new Event('input', { bubbles: true }));
      }
    });
  }

  window.addEventListener('beforeunload', () => { if (unmount) unmount(); });
}
