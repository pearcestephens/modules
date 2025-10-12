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
