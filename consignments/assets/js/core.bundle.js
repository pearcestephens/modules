export async function post(url, data, asJson=true) {
  const body = data instanceof FormData ? data : Object.entries(data).reduce((fd,[k,v]) => (fd.append(k, v), fd), new FormData());
  const r = await fetch(url, { method:'POST', body });
  if (!r.ok) throw new Error(`HTTP ${r.status}`);
  return asJson ? r.json() : r.text();
}

;
// Minimal, framework-agnostic UI helpers scoped to .vs-transfer.
export function toast(root, message, kind = 'info') {
  let host = root.querySelector('.vt-toasts');
  if (!host) {
    host = document.createElement('div');
    host.className = 'vt-toasts';
    root.appendChild(host);
  }
  const el = document.createElement('div');
  el.className = `vt-toast vt-toast--${kind}`;
  el.textContent = message;
  host.appendChild(el);
  setTimeout(() => el.classList.add('is-visible'), 10);
  setTimeout(() => {
    el.classList.remove('is-visible');
    setTimeout(() => el.remove(), 200);
  }, 3000);
}

export function showModal(el) {
  if (window.bootstrap?.Modal) {
    const m = new bootstrap.Modal(el); // eslint-disable-line no-undef
    m.show();
    return;
  }
  // Fallback simple modal
  el.classList.add('vt-modal--open');
}

export function hideModal(el) {
  if (window.bootstrap?.Modal) {
    const m = bootstrap.Modal.getInstance(el) || new bootstrap.Modal(el); // eslint-disable-line no-undef
    m.hide();
    return;
  }
  el.classList.remove('vt-modal--open');
}

;
import { post } from './api.js';
import { toast } from './ui.js';

function formSnapshot(form) {
  return Object.fromEntries(new FormData(form));
}
function applySnapshot(form, snap) {
  if (!snap || typeof snap !== 'object') return;
  for (const [k, v] of Object.entries(snap)) {
    const el = form.querySelector(`[name="${CSS.escape(k)}"]`);
    if (!el) continue;
    if (el.type === 'checkbox' || el.type === 'radio') {
      el.checked = (v === 'on' || v === '1' || v === true);
    } else {
      el.value = v;
      el.dispatchEvent(new Event('input', { bubbles: true }));
      el.dispatchEvent(new Event('change', { bubbles: true }));
    }
  }
}

export function mountDraftControls(root, c) {
  const saveBtn = root.querySelector('.js-draft-save');
  const restoreBtn = root.querySelector('.js-draft-restore');

  saveBtn?.addEventListener('click', async () => {
    const form = root.querySelector('.vt-form') || root.querySelector('form');
    const snapshot = JSON.stringify(formSnapshot(form));
    await post(`${c.apiBase}/autosave.php`, { csrf: c.csrf, transfer_id: c.transferId, state_json: snapshot });
    toast(root, 'Draft saved', 'success');
  });

  restoreBtn?.addEventListener('click', async () => {
    const form = root.querySelector('.vt-form') || root.querySelector('form');
    const res = await post(`${c.apiBase}/autosave_load.php`, { csrf: c.csrf, transfer_id: c.transferId });
    try {
      const snap = JSON.parse(res.state || '{}');
      applySnapshot(form, snap);
      toast(root, 'Draft restored', 'success');
    } catch {
      toast(root, 'No draft to restore', 'warning');
    }
  });
}

;
export function sumInputs(rowsSel, inputSel, targetSel) {
  let total = 0;
  document.querySelectorAll(rowsSel).forEach(row => {
    const v = parseInt(row.querySelector(inputSel)?.value || '0', 10);
    total += isNaN(v) ? 0 : v;
    const planned = parseInt(row.querySelector('input[name$="[qty_planned]"]')?.value || '0', 10);
    const rem = Math.max(0, planned - v);
    const tgt = row.querySelector(targetSel);
    if (tgt) tgt.textContent = String(rem);
  });
  return total;
}

;
export function isPositiveInt(val) {
  const n = Number(val);
  return Number.isInteger(n) && n >= 0;
}

export function clampInt(val, min, max) {
  let n = parseInt(val, 10);
  if (isNaN(n)) n = min;
  return Math.max(min, Math.min(max, n));
}

;
export function mountKeyboardScanner({ root, onCode, timeoutMs = 200 }) {
  let buf = '';
  let timer = null;
  const handler = (e) => {
    if (e.key.length === 1) {
      buf += e.key;
      if (timer) clearTimeout(timer);
      timer = setTimeout(() => {
        const code = buf.trim();
        buf = '';
        if (code) onCode(code);
      }, timeoutMs);
    } else if (e.key === 'Enter') {
      const code = buf.trim();
      buf = '';
      if (code) onCode(code);
    }
  };
  root.addEventListener('keydown', handler);
  return () => root.removeEventListener('keydown', handler);
}
