/**
 * Pack Page Hotfix - Auto-Fill Counted Quantities
 * Ensures Auto-Fill button works correctly with the current markup
 */

// Override with the selector your markup uses
window.autoFillAllQuantities = function () {
  document.querySelectorAll('#transfer-table input.js-counted-qty').forEach((el) => {
    const planned = Number(el.dataset.planned ?? 0);
    const val = Number(el.value || 0);
    if (!val && planned > 0) {
      el.value = String(planned);
      if (typeof window.validateCountedQty === 'function') {
        window.validateCountedQty(el);
      }
    }
  });
};
