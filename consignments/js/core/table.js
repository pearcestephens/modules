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
