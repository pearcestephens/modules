/* Freight Status UI - fetches per-outlet courier credential status and renders cards */
(function(){
  const outletId = (window.FREIGHT_BOOT && window.FREIGHT_BOOT.outlet_id) ? window.FREIGHT_BOOT.outlet_id : 1;
  const statusEl = document.getElementById('freight-status');
  const outletBadge = document.getElementById('freight-outlet-id');
  const btn = document.getElementById('btn-refresh-freight');
  if (outletBadge) outletBadge.textContent = String(outletId);

  function renderInitialIfAvailable(){
    const init = window.FREIGHT_BOOT && window.FREIGHT_BOOT.initial;
    if (!init) return false;
    render(init);
    return true;
  }

  async function fetchStatus() {
    if (!statusEl) return;
    statusEl.innerHTML = '<div class="col-12 text-muted">Loading…</div>';
    try {
      const res = await fetch('/modules/consignments/api/freight.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify({ action: 'outlet_status', data: { outlet_id: outletId } })
      });
      const json = await res.json();
      if (!json.ok) throw new Error(json.error || 'Request failed');
      render(json.data);
    } catch (e) {
      statusEl.innerHTML = '<div class="col-12 text-danger">Failed to load freight status</div>';
      // Optionally log to console for admins
      console.warn('Freight status error', e);
    }
  }

  function render(data) {
    const cards = [];
    const carriers = data.carriers || {};
    for (const [name, info] of Object.entries(carriers)) {
      const configured = info.configured ? 'Configured' : 'Missing';
      const badgeClass = info.configured ? 'bg-success' : 'bg-danger';
      const key = info.key_masked ? info.key_masked : '—';
      const sec = info.secret_masked ? info.secret_masked : '—';
      const src = info.source ? info.source.toUpperCase() : '—';
      const updated = info.updated_at ? new Date(info.updated_at).toLocaleString() : '—';
      cards.push(`
        <div class="col-md-4">
          <div class="card h-100 shadow-sm">
            <div class="card-body">
              <div class="d-flex justify-content-between align-items-start">
                <h5 class="card-title text-capitalize"><i class="bi bi-shield-lock me-2"></i>${name}</h5>
                <span class="badge ${badgeClass}">${configured}</span>
              </div>
              <div class="small text-muted">Source: ${src}</div>
              <hr/>
              <div class="small">
                <div class="mb-1">Key: <code>${key}</code></div>
                <div class="mb-1">Secret: <code>${sec}</code></div>
                <div class="text-muted">Updated: ${updated}</div>
              </div>
            </div>
          </div>
        </div>`);
    }
    statusEl.innerHTML = cards.join('');
  }

  if (btn) btn.addEventListener('click', fetchStatus);
  if (!renderInitialIfAvailable()) fetchStatus();
})();
