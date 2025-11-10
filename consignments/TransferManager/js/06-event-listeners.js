/**
 * 06-event-listeners.js
 * Setup all event listeners (extracted from scripts.js)
 */
function setupEventListeners() {
  // Table row clicks - use event delegation on document body for safety
  const tblRows = document.getElementById('tblRows');
  if (tblRows) {
    tblRows.addEventListener('click', (e)=>{
      const row = e.target.closest('tr[data-id]');
      if (!row) return;
      const btn = e.target.closest('.act-open');
      if (btn) openQuick(parseInt(row.dataset.id));
    });
  }

  // Pagination
  const btnRefresh = $q('#btnRefresh');
  const btnHardRefresh = $q('#btnHardRefresh');
  const prevPage = $q('#prevPage');
  const nextPage = $q('#nextPage');

  if (btnRefresh) btnRefresh.addEventListener('click', ()=>{ page=1; refresh(); });
  if (btnHardRefresh) btnHardRefresh.addEventListener('click', ()=>{
    // Hard refresh: bypass cache (Ctrl+Shift+R / Cmd+Shift+R)
    window.location.reload(true);
  });
  if (prevPage) prevPage.addEventListener('click', ()=>{ if (page>1) { page--; refresh(); } });
  if (nextPage) nextPage.addEventListener('click', ()=>{ page++; refresh(); });

  // Filters
  const filterType = $q('#filterType');
  const filterState = $q('#filterState');
  const filterOutlet = $q('#filterOutlet');
  const filterQ = $q('#filterQ');

  if (filterType) filterType.addEventListener('change', ()=>{ page=1; refresh(); });
  if (filterState) filterState.addEventListener('change', ()=>{ page=1; refresh(); });
  if (filterOutlet) filterOutlet.addEventListener('change', ()=>{ page=1; refresh(); });
  if (filterQ) {
    filterQ.addEventListener('input', ()=>{
      clearTimeout(window.__qDeb);
      window.__qDeb = setTimeout(()=>{ page=1; refresh(); }, 350);
    });
  }

  // Keyboard shortcut: / to focus search
  document.addEventListener('keydown', (e)=>{
    if(e.key==='/' && !e.target.matches('input,textarea')){
      e.preventDefault();
  const searchBox = $q('#filterQ');
      if (searchBox) searchBox.focus();
    }
  });

  // New transfer button
  const btnNew = $q('#btnNew');
  if (btnNew) {
  const createModal = new bootstrap.Modal('#modalCreate');
    btnNew.addEventListener('click', ()=>{
  const ctType = $q('#ct_type');
  const ctSupplierWrap = $q('#ct_supplier_wrap');
  const ctSupplierSelect = $q('#ct_supplier_select');
  const ctFromSelect = $q('#ct_from_select');
  const ctToSelect = $q('#ct_to_select');
  const ctAddProducts = $q('#ct_add_products');

      if (ctType) ctType.value='STOCK';
      if (ctSupplierWrap) ctSupplierWrap.style.display='none';
      if (ctSupplierSelect) ctSupplierSelect.value='';
      if (ctFromSelect) ctFromSelect.value='';
      if (ctToSelect) ctToSelect.value='';
      if (ctAddProducts) ctAddProducts.checked=true;
      createModal.show();
    });
  }

  // Transfer type change (show/hide supplier field)
  const ctType = $q('#ct_type');
  const ctSupplierWrap = $q('#ct_supplier_wrap');
  if (ctType && ctSupplierWrap) {
    ctType.addEventListener('change', (e)=>{
      const po = e.target.value==='PURCHASE_ORDER';
      ctSupplierWrap.style.display = po ? '' : 'none';
    });
  }

  // Form create handled in 05-detail-modal or other modules

  // ✅ CRITICAL FIX: Add null check for #syncToggle
  const syncToggle = $q('#syncToggle');
  if (syncToggle) {
    syncToggle.addEventListener('change', async (e)=>{
      const checked = e.target.checked;
      try {
        await api('toggle_sync',{enabled:checked});
        toast(`Lightspeed Sync ${checked?'enabled':'disabled'}`,'success');
      }
      catch(err){
        e.target.checked = !checked;
        toast('Failed to toggle','danger');
      }
    });
  } else {
    console.warn('⚠️ #syncToggle element not found - sync toggle disabled');
  }

  // ✅ NEW: Verify Sync Button - Comprehensive Lightspeed table verification
  const btnVerifySync = $q('#btnVerifySync');
  if (btnVerifySync) {
    btnVerifySync.addEventListener('click', async ()=> {
      const btn = btnVerifySync;
      const originalHTML = btn.innerHTML;
      btn.disabled = true;
      btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Verifying...';

      try {
        const result = await api('verify_sync');
        showVerificationModal(result);
      } catch(err) {
        toast('Sync verification failed: ' + (err.message || 'Unknown error'), 'danger');
      } finally {
        btn.disabled = false;
        btn.innerHTML = originalHTML;
      }
    });
  }

  // ✅ FIX: Add cleanup on page unload
  window.addEventListener('beforeunload', () => {
    clearTimeout(window.__qDeb);
    delete window.__qDeb;
  });

  console.log('✅ Event listeners attached');
}

// Manager PIN tools (issue/revoke/status)
window.tmPin = (function(){
  async function call(action, payload={}){
    const body = Object.assign({ action, csrf: (window.APP_CONFIG && APP_CONFIG.CSRF) || '' }, payload);
    const resp = await fetch('/modules/consignments/TransferManager/api.php?api=1', {
      method:'POST', headers:{'Content-Type':'application/json','Accept':'application/json','X-Requested-With':'XMLHttpRequest'}, credentials:'same-origin', body: JSON.stringify(body)
    });
    const data = await resp.json().catch(()=>({}));
    if (!resp.ok || data.success===false) throw new Error((data.error && data.error.message) || ('HTTP '+resp.status));
    return data.data || data;
  }
  return {
    async issue(ttlSec){ return call('pin_issue', { ttl: ttlSec||900 }); },
    async status(){ return call('pin_status'); },
    async revoke(){ return call('pin_revoke'); }
  };
})();

/**
 * Show Vend/Lightspeed Transfer Verification Modal
 * Called when clicking the green checkmark button on each transfer row
 */
window.showVendVerificationModal = async function(transferId, vendTransferId) {
  // Show loading modal first
  const loadingHtml = `
    <div class="modal-header bg-gradient" style="background: linear-gradient(135deg, #86efac 0%, #4ade80 100%);">
      <h5 class="modal-title text-white"><i class="bi bi-shield-check me-2"></i>Verifying Transfer Sync</h5>
    </div>
    <div class="modal-body text-center py-5">
      <div class="spinner-border text-success mb-3" role="status" style="width: 4rem; height: 4rem;">
        <span class="visually-hidden">Loading...</span>
      </div>
      <h5>Fetching live data from Lightspeed...</h5>
      <p class="text-muted">Verifying transfer #${transferId}</p>
    </div>
  `;
  showModal(loadingHtml, 'lg');

  try {
    // Get transfer details from our database
    const localData = await api('get_transfer_detail', { id: transferId });

    // Get live data from Lightspeed (via backend which calls Vend API)
    const vendData = localData.ls || null;
    const totals = localData.totals || null;

    // Compare and build verification report
    const verification = {
      transfer_id: transferId,
      vend_transfer_id: vendTransferId,
      local: {
        items_count: localData.items?.length || 0,
        total_requested: localData.items?.reduce((sum, i) => sum + (parseInt(i.qty_requested) || 0), 0) || 0,
        total_sent: localData.items?.reduce((sum, i) => sum + (parseInt(i.qty_sent_total) || 0), 0) || 0,
        total_received: localData.items?.reduce((sum, i) => sum + (parseInt(i.qty_received_total) || 0), 0) || 0,
        state: localData.transfer?.state,
        outlet_from: localData.transfer?.outlet_from,
        outlet_to: localData.transfer?.outlet_to
      },
      vend: {
        connected: !!vendData && !vendData.error,
        status: vendData?.status || 'UNKNOWN',
        product_count: vendData?.consignment_products?.length || 0,
        error: vendData?.error || null
      },
      sync_status: 'unknown'
    };

    // Determine sync status
    if (!verification.vend.connected) {
      verification.sync_status = 'error';
      verification.sync_message = 'Cannot connect to Lightspeed API';
    } else if (verification.local.items_count === 0) {
      verification.sync_status = 'warning';
      verification.sync_message = 'No items in local database';
    } else if (verification.local.items_count !== verification.vend.product_count) {
      verification.sync_status = 'warning';
      verification.sync_message = `Item count mismatch: Local(${verification.local.items_count}) vs Lightspeed(${verification.vend.product_count})`;
    } else {
      verification.sync_status = 'ok';
      verification.sync_message = 'All data synchronized';
    }

    // Show verification modal
    showVendVerificationResultModal(verification, vendTransferId);

  } catch (err) {
    const errorHtml = `
      <div class="modal-header bg-danger">
        <h5 class="modal-title text-white"><i class="bi bi-exclamation-triangle me-2"></i>Verification Failed</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="alert alert-danger mb-0">
          <strong>Error:</strong> ${esc(err.message || 'Unknown error occurred')}
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    `;
    showModal(errorHtml, 'lg');
  }
};

/**
 * Show Vend Verification Result Modal
 */
function showVendVerificationResultModal(verification, vendTransferId) {
  const v = verification;

  // Determine status badge
  let statusBadge = '';
  let statusClass = '';
  if (v.sync_status === 'ok') {
    statusBadge = '<span class="badge bg-success fs-6">✅ Fully Synchronized</span>';
    statusClass = 'success';
  } else if (v.sync_status === 'warning') {
    statusBadge = '<span class="badge bg-warning fs-6">⚠️ Sync Warning</span>';
    statusClass = 'warning';
  } else {
    statusBadge = '<span class="badge bg-danger fs-6">❌ Sync Error</span>';
    statusClass = 'danger';
  }

  const html = `
    <div class="modal-header bg-gradient" style="background: linear-gradient(135deg, #86efac 0%, #4ade80 100%);">
      <h5 class="modal-title text-white"><i class="bi bi-shield-check me-2"></i>Transfer Sync Verification</h5>
      <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
    </div>
    <div class="modal-body">
      <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
          ${statusBadge}
          <div class="text-muted small mt-1">Transfer ID: ${esc(v.transfer_id)} | Vend ID: ${esc(v.vend_transfer_id)}</div>
        </div>
      </div>

      ${v.sync_message ? `
      <div class="alert alert-${statusClass} mb-4">
        <strong>${v.sync_status === 'ok' ? '✅' : v.sync_status === 'warning' ? '⚠️' : '❌'}</strong> ${esc(v.sync_message)}
      </div>
      ` : ''}

      <div class="row g-3 mb-4">
        <div class="col-md-6">
          <div class="card">
            <div class="card-header bg-light">
              <strong>📦 Local Database</strong>
            </div>
            <div class="card-body">
              <table class="table table-sm mb-0">
                <tr>
                  <td><strong>Items:</strong></td>
                  <td class="text-end">${v.local.items_count}</td>
                </tr>
                <tr>
                  <td><strong>Requested:</strong></td>
                  <td class="text-end">${v.local.total_requested}</td>
                </tr>
                <tr>
                  <td><strong>Sent:</strong></td>
                  <td class="text-end">${v.local.total_sent}</td>
                </tr>
                <tr>
                  <td><strong>Received:</strong></td>
                  <td class="text-end">${v.local.total_received}</td>
                </tr>
                <tr>
                  <td><strong>State:</strong></td>
                  <td class="text-end"><span class="badge bg-secondary">${esc(v.local.state)}</span></td>
                </tr>
              </table>
            </div>
          </div>
        </div>

        <div class="col-md-6">
          <div class="card">
            <div class="card-header bg-light">
              <strong>☁️ Lightspeed Live Data</strong>
            </div>
            <div class="card-body">
              ${v.vend.connected ? `
              <table class="table table-sm mb-0">
                <tr>
                  <td><strong>Connection:</strong></td>
                  <td class="text-end"><span class="badge bg-success">✅ Connected</span></td>
                </tr>
                <tr>
                  <td><strong>Products:</strong></td>
                  <td class="text-end">${v.vend.product_count}</td>
                </tr>
                <tr>
                  <td><strong>Status:</strong></td>
                  <td class="text-end"><span class="badge bg-info">${esc(v.vend.status)}</span></td>
                </tr>
              </table>
              ` : `
              <div class="alert alert-danger mb-0">
                <strong>❌ Connection Failed</strong><br>
                ${esc(v.vend.error || 'Cannot reach Lightspeed API')}
              </div>
              `}
            </div>
          </div>
        </div>
      </div>

      <div class="card bg-light">
        <div class="card-body">
          <h6 class="mb-2">🔍 Verification Summary:</h6>
          <ul class="mb-0">
            <li><strong>Local items vs Lightspeed products:</strong> ${v.local.items_count === v.vend.product_count ? '✅ Match' : '⚠️ Mismatch'}</li>
            <li><strong>API connectivity:</strong> ${v.vend.connected ? '✅ Connected' : '❌ Failed'}</li>
            <li><strong>Data freshness:</strong> ✅ Retrieved just now</li>
          </ul>
        </div>
      </div>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      <a href="${LS_CONSIGNMENT_BASE}${encodeURIComponent(vendTransferId)}" target="_blank" class="btn btn-success">
        <i class="bi bi-box-arrow-up-right me-1"></i> Open in Lightspeed
      </a>
    </div>
  `;

  showModal(html, 'lg');
}

/**
 * Show Lightspeed Sync Verification Modal (for global verify button)
 */
function showVerificationModal(data) {
  const { sync_enabled, timestamp, tables, errors, warnings, summary } = data;

  // Determine overall badge
  let statusBadge = '';
  if (summary.overall_status === 'ok') {
    statusBadge = '<span class="badge bg-success fs-6">✅ All Systems OK</span>';
  } else if (summary.overall_status === 'warning') {
    statusBadge = '<span class="badge bg-warning fs-6">⚠️ Warnings Detected</span>';
  } else if (summary.overall_status === 'error') {
    statusBadge = '<span class="badge bg-danger fs-6">❌ Errors Found</span>';
  } else {
    statusBadge = '<span class="badge bg-danger fs-6">🚨 Critical Issues</span>';
  }

  // Build tables HTML
  let tablesHTML = '<div class="table-responsive" style="max-height: 400px; overflow-y: auto;">';
  tablesHTML += '<table class="table table-sm table-hover mb-0">';
  tablesHTML += '<thead class="sticky-top bg-light"><tr>';
  tablesHTML += '<th>Table Name</th><th>Status</th><th>Rows</th><th>Columns</th><th>Issues</th>';
  tablesHTML += '</tr></thead><tbody>';

  tables.forEach(t => {
    let statusIcon = '';
    let rowClass = '';
    if (t.status === 'ok') {
      statusIcon = '<span class="badge bg-success">✅ OK</span>';
    } else if (t.status === 'empty') {
      statusIcon = '<span class="badge bg-warning">⚠️ Empty</span>';
      rowClass = 'table-warning';
    } else if (t.status === 'incomplete') {
      statusIcon = '<span class="badge bg-danger">❌ Incomplete</span>';
      rowClass = 'table-danger';
    } else if (t.status === 'missing') {
      statusIcon = '<span class="badge bg-dark">🚫 Missing</span>';
      rowClass = 'table-danger';
    }

    const criticalBadge = t.critical ? '<span class="badge bg-danger ms-1">CRITICAL</span>' : '';
    const issues = t.missing_columns.length > 0 ? `Missing: ${t.missing_columns.join(', ')}` : '—';

    tablesHTML += `<tr class="${rowClass}">`;
    tablesHTML += `<td><code>${esc(t.name)}</code> ${criticalBadge}</td>`;
    tablesHTML += `<td>${statusIcon}</td>`;
    tablesHTML += `<td>${t.row_count.toLocaleString()}</td>`;
    tablesHTML += `<td>${t.columns.length}</td>`;
    tablesHTML += `<td><small>${esc(issues)}</small></td>`;
    tablesHTML += `</tr>`;
  });

  tablesHTML += '</tbody></table></div>';

  // Build errors/warnings HTML
  let issuesHTML = '';
  if (errors.length > 0) {
    issuesHTML += '<div class="alert alert-danger mb-3"><strong>❌ Errors:</strong><ul class="mb-0 mt-2">';
    errors.forEach(e => issuesHTML += `<li>${esc(e)}</li>`);
    issuesHTML += '</ul></div>';
  }
  if (warnings.length > 0) {
    issuesHTML += '<div class="alert alert-warning mb-3"><strong>⚠️ Warnings:</strong><ul class="mb-0 mt-2">';
    warnings.forEach(w => issuesHTML += `<li>${esc(w)}</li>`);
    issuesHTML += '</ul></div>';
  }
  if (errors.length === 0 && warnings.length === 0) {
    issuesHTML = '<div class="alert alert-success mb-3">✅ No issues detected. All Lightspeed tables are properly configured.</div>';
  }

  const html = `
    <div class="modal-header bg-gradient" style="background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%);">
      <h5 class="modal-title text-white"><i class="bi bi-shield-check me-2"></i>Lightspeed Sync Verification</h5>
      <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
    </div>
    <div class="modal-body">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
          ${statusBadge}
          <div class="text-muted small mt-1">Verified at: ${esc(timestamp)}</div>
        </div>
        <div class="text-end">
          <div><strong>Sync Status:</strong> ${sync_enabled ? '<span class="badge bg-success">✅ ENABLED</span>' : '<span class="badge bg-secondary">❌ DISABLED</span>'}</div>
        </div>
      </div>

      <div class="row mb-3">
        <div class="col-md-3">
          <div class="card text-center">
            <div class="card-body p-2">
              <div class="fs-4 fw-bold text-primary">${summary.tables_exist}</div>
              <div class="small text-muted">Tables Found</div>
            </div>
          </div>
        </div>
        <div class="col-md-3">
          <div class="card text-center">
            <div class="card-body p-2">
              <div class="fs-4 fw-bold text-danger">${summary.tables_missing}</div>
              <div class="small text-muted">Missing</div>
            </div>
          </div>
        </div>
        <div class="col-md-3">
          <div class="card text-center">
            <div class="card-body p-2">
              <div class="fs-4 fw-bold text-success">${summary.total_rows_all_tables.toLocaleString()}</div>
              <div class="small text-muted">Total Rows</div>
            </div>
          </div>
        </div>
        <div class="col-md-3">
          <div class="card text-center">
            <div class="card-body p-2">
              <div class="fs-4 fw-bold ${summary.critical_missing > 0 ? 'text-danger' : 'text-success'}">${summary.critical_missing}</div>
              <div class="small text-muted">Critical Issues</div>
            </div>
          </div>
        </div>
      </div>

      ${issuesHTML}

      <h6 class="mb-2">📊 Detailed Table Status:</h6>
      ${tablesHTML}
    </div>
    <div class="modal-footer">
      <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      ${summary.overall_status !== 'ok' ? '<button type="button" class="btn btn-warning" onclick="window.open(\'https://staff.vapeshed.co.nz/sync-manager\', \'_blank\')">Open Sync Manager</button>' : ''}
    </div>
  `;

  showModal(html, 'xl');
}
