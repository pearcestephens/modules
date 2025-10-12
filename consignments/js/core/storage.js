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
