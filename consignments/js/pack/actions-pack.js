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
