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
