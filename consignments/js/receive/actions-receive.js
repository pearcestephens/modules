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
