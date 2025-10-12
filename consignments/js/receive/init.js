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
