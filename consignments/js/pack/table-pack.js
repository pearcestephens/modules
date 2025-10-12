import { sumInputs } from '../core/table.js';

export function bindPackTable(root) {
  const table = root.querySelector('.js-pack-table');
  const recalc = () => sumInputs('.js-pack-table tbody tr', '.qty-input', '.rem-cell');
  table?.addEventListener('input', (e) => {
    if (e.target.matches('.qty-input')) recalc();
  });
  recalc();
}
