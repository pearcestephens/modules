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
