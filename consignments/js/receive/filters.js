export function bindFilters(root) {
  root.querySelectorAll('.js-filter').forEach(btn => {
    btn.addEventListener('click', () => {
      // Placeholder—hook with server filtering if needed (keeping consistent UI).
      btn.classList.toggle('active');
    });
  });
}
