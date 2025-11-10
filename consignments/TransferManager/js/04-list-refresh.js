/**
 * 04-list-refresh.js
 * List loading and row HTML rendering
 */

// ✅ CRITICAL FIX: Better error handling and loading state management
let isRefreshing = false;

async function refresh() {
  // ✅ Prevent concurrent refreshes
  if (isRefreshing) {
    console.log('Refresh already in progress, skipping...');
    return;
  }

  isRefreshing = true;
  const btnRefresh = $q('#btnRefresh');
  if (btnRefresh) {
    btnRefresh.disabled = true;
    const originalHTML = btnRefresh.innerHTML;
    btnRefresh.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Loading...';
    btnRefresh.dataset.originalHtml = originalHTML;
  }

  try {
  const tblRows = $q('#tblRows');
  const resultCount = $q('#resultCount');
  const prevPage = $q('#prevPage');
  const nextPage = $q('#nextPage');

    if (!tblRows) {
      console.error('❌ #tblRows element not found');
      return;
    }

    tblRows.innerHTML = '<tr><td colspan="8" class="text-center py-4"><span class="spinner-border spinner-border-sm"></span> Loading…</td></tr>';

    const d = await api('list_transfers', {
      page, perPage,
      type: $q('#filterType')?.value || undefined,
      state: $q('#filterState')?.value || undefined,
      outlet: $q('#filterOutlet')?.value || undefined,
      q: $q('#filterQ')?.value || undefined
    });

    if (!d.rows?.length) {
      tblRows.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4">No transfers found</td></tr>';
    } else {
      tblRows.innerHTML = d.rows.map(rowHtml).join('');
    }

    const total=d.total||0, start=total?((page-1)*perPage)+1:0, end=Math.min(page*perPage,total);
    if (resultCount) resultCount.textContent = `${total} result${total===1?'':'s'}${total>perPage?` (showing ${start}-${end})`:''}`;
    if (prevPage) prevPage.disabled = page<=1;
    if (nextPage) nextPage.disabled = end>=total;

  } catch(e){
    console.error('refresh() error:', e);
    const tblRows = $('#tblRows');
    if (tblRows) {
      tblRows.innerHTML = `
        <tr>
          <td colspan="8" class="text-danger text-center py-4">
            <i class="bi bi-exclamation-triangle me-2"></i>
            <strong>Failed to load transfers</strong><br>
            <small class="text-muted">${esc(e.message || 'Unknown error')}</small><br>
            <button class="btn btn-sm btn-primary mt-2" onclick="refresh()">
              <i class="bi bi-arrow-repeat me-1"></i>Try Again
            </button>
          </td>
        </tr>
      `;
    }
    toast(e.message || 'Failed to load transfers', 'danger');
  } finally {
    isRefreshing = false;
    if (btnRefresh) {
      btnRefresh.disabled = false;
      btnRefresh.innerHTML = btnRefresh.dataset.originalHtml || '<i class="bi bi-arrow-repeat me-1"></i> Refresh';
    }
  }
}

// rowHtml and smartTransferID are in 03-transfer-functions.js
