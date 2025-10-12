export function mountConfidence(root) {
  const bar = root.querySelector('.js-confidence');
  const digest = root.querySelector('.js-digest');
  const update = () => {
    const planned = parseInt(root.querySelector('.js-stat-planned').textContent || '0', 10);
    const received = parseInt(root.querySelector('.js-stat-received').textContent || '0', 10);
    const pct = planned ? Math.round((received / planned) * 100) : 0;
    bar.style.width = pct + '%';
    digest.textContent = pct >= 100 ? 'All quantities matched.' : `Confidence: ${pct}%`;
  };
  root.addEventListener('input', update);
  update();
}
