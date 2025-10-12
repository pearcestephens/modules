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
