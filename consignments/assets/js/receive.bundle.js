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
}

;
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
}

;
import { post } from '../core/api.js';
import { showModal } from '../core/ui.js';

export function bindReceiveActions(root, c) {
  const form = root.querySelector('#receiveForm');
  form?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const fd = new FormData(form);
    try {
      const res = await post(`${c.apiBase}/receive_submit.php`, fd);
      const el = document.getElementById('receiveSuccess');
      const summary = el.querySelector('.js-receive-summary');
      summary.textContent = `Receipt #${res.receipt_id} ${res.complete ? 'complete' : 'partial'}.`;
      showModal(el);
    } catch (err) {
      alert('Receive failed: ' + err.message);
    }
  });

  // Fallback close if Bootstrap not present:
  document.querySelector('#receiveSuccess [data-bs-dismiss="modal"]')
    ?.addEventListener('click', () => {
      document.getElementById('receiveSuccess')?.classList.remove('vt-modal--open');
    });
}

;
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
        const row = root.querySelector(`tr[data-sku="${CSS.escape(code)}"], tr[data-product-id="${CSS.escape(code)}"]`)
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
