/**
 * Stock Transfers - Frontend Controller
 *
 * Handles AJAX data loading, filtering, modal interactions, and live updates
 * Works with stock-transfers API backend
 *
 * @version 4.0.0
 * @created 2025-11-10
 */

(function() {
  'use strict';

  // Configuration
  const CONFIG = {
    apiUrl: '/modules/consignments/stock-transfers/api/stock-transfers-api.php',
    refreshInterval: 30000, // 30 seconds
    debounceDelay: 300
  };

  // State
  let state = {
    transfers: [],
    filters: {
      state: '',
      scope: ''
    },
    counts: {},
    loading: false,
    refreshTimer: null
  };

  // DOM Elements
  const elements = {
    filterPills: null,
    transfersBody: null,
    modal: null,
    modalTitle: null,
    modalBody: null,
    pageData: null
  };

  /**
   * Initialize the application
   */
  function init() {
    // Get DOM elements
    elements.pageData = document.getElementById('pageData');
    elements.filterPills = document.getElementById('filterPills');
    elements.transfersBody = document.getElementById('transfersBody');
    elements.modal = document.getElementById('transferModal');
    elements.modalTitle = document.getElementById('modalTitle');
    elements.modalBody = document.getElementById('modalBody');

    // Load initial state from page data
    if (elements.pageData) {
      state.filters.state = elements.pageData.dataset.state || '';
      state.filters.scope = elements.pageData.dataset.scope || '';
    }

    // Setup event listeners
    setupEventListeners();

    // Load data
    loadCounts();
    loadTransfers();

    // Start auto-refresh
    startAutoRefresh();
  }

  /**
   * Setup event listeners
   */
  function setupEventListeners() {
    // Filter pills click
    if (elements.filterPills) {
      elements.filterPills.addEventListener('click', handleFilterClick);
    }

    // Table row click
    if (elements.transfersBody) {
      elements.transfersBody.addEventListener('click', handleRowClick);
    }

    // Keyboard shortcuts
    document.addEventListener('keydown', handleKeyboard);

    // Visibility change (pause refresh when tab hidden)
    document.addEventListener('visibilitychange', handleVisibilityChange);
  }

  /**
   * Handle filter pill clicks
   */
  function handleFilterClick(e) {
    const pill = e.target.closest('.filter-pill');
    if (!pill) return;

    e.preventDefault();
    const filterState = pill.dataset.state || '';
    const filterScope = pill.dataset.scope || '';

    // Update state
    state.filters.state = filterState;
    state.filters.scope = filterScope;

    // Update URL
    const url = new URL(window.location);
    if (filterState) {
      url.searchParams.set('state', filterState);
    } else {
      url.searchParams.delete('state');
    }
    if (filterScope) {
      url.searchParams.set('scope', filterScope);
    } else {
      url.searchParams.delete('scope');
    }
    window.history.pushState({}, '', url);

    // Reload data
    loadTransfers();

    // Update UI
    renderFilters();
  }

  /**
   * Handle table row clicks
   */
  function handleRowClick(e) {
    const row = e.target.closest('tr');
    if (!row) return;

    const transferId = row.dataset.id;
    if (!transferId) return;

    // Don't trigger if clicking a button
    if (e.target.closest('.st-action-btn')) return;

    // Show modal
    showTransferModal(transferId);
  }

  /**
   * Handle keyboard shortcuts
   */
  function handleKeyboard(e) {
    // Escape - close modal
    if (e.key === 'Escape' && elements.modal) {
      $(elements.modal).modal('hide');
    }

    // R - refresh
    if (e.key === 'r' && !e.ctrlKey && !e.metaKey) {
      if (document.activeElement.tagName !== 'INPUT' &&
          document.activeElement.tagName !== 'TEXTAREA') {
        e.preventDefault();
        loadTransfers();
      }
    }
  }

  /**
   * Handle visibility change
   */
  function handleVisibilityChange() {
    if (document.hidden) {
      stopAutoRefresh();
    } else {
      startAutoRefresh();
      loadTransfers(); // Refresh data when tab becomes visible
    }
  }

  /**
   * Load transfer counts from API
   */
  async function loadCounts() {
    try {
      const response = await fetch(CONFIG.apiUrl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({ action: 'get_counts' })
      });

      const data = await response.json();

      if (data.success) {
        state.counts = data.data;
        renderFilters();
      }
    } catch (error) {
      console.error('Failed to load counts:', error);
    }
  }

  /**
   * Load transfers from API
   */
  async function loadTransfers() {
    if (state.loading) return;

    state.loading = true;
    renderLoading();

    try {
      const response = await fetch(CONFIG.apiUrl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
          action: 'get_transfers',
          filters: state.filters
        })
      });

      const data = await response.json();

      if (data.success) {
        state.transfers = data.data;
        renderTransfers();
      } else {
        renderError(data.error?.message || 'Failed to load transfers');
      }
    } catch (error) {
      console.error('Failed to load transfers:', error);
      renderError('Network error. Please try again.');
    } finally {
      state.loading = false;
    }
  }

  /**
   * Render filter pills
   */
  function renderFilters() {
    if (!elements.filterPills) return;

    const filters = [
      { label: 'All', state: '', scope: '', count: state.counts.TOTAL || 0 },
      { label: 'Open', state: 'OPEN', scope: '', count: state.counts.OPEN || 0, class: 'info' },
      { label: 'Sent', state: 'SENT', scope: '', count: state.counts.SENT || 0, class: 'warning' },
      { label: 'Receiving', state: 'RECEIVING', scope: '', count: state.counts.RECEIVING || 0, class: 'warning' },
      { label: 'Received', state: 'RECEIVED', scope: '', count: state.counts.RECEIVED || 0, class: 'success' },
      { label: 'My Transfers', state: '', scope: 'mine', count: state.counts.MINE || 0, icon: 'fa-user' }
    ];

    elements.filterPills.innerHTML = filters.map(filter => {
      const isActive = state.filters.state === filter.state && state.filters.scope === filter.scope;
      const classes = ['filter-pill'];
      if (filter.class) classes.push(filter.class);
      if (isActive) classes.push('active');

      return `
        <a href="#" class="${classes.join(' ')}" data-state="${filter.state}" data-scope="${filter.scope}">
          ${filter.icon ? `<i class="fas ${filter.icon}"></i>` : ''}
          ${filter.label}
          <span class="filter-badge">${filter.count}</span>
        </a>
      `;
    }).join('');
  }

  /**
   * Render transfers table
   */
  function renderTransfers() {
    if (!elements.transfersBody) return;

    if (state.transfers.length === 0) {
      renderEmpty();
      return;
    }

    elements.transfersBody.innerHTML = state.transfers.map(t => `
      <tr data-id="${escapeHtml(t.id || t.cis_internal_id)}" role="button" tabindex="0">
        <td>
          <div style="font-weight: 600;">${escapeHtml(t.consignment_number || t.name || t.cis_internal_id)}</div>
          <div style="font-size: 0.75rem; color: var(--st-gray-600);">Vend ID: ${escapeHtml(t.id)}</div>
        </td>
        <td>
          <div>${escapeHtml(t.from_outlet_name || '-')}</div>
          <div style="font-size: 0.75rem; color: var(--st-gray-600);">→ ${escapeHtml(t.to_outlet_name || '-')}</div>
        </td>
        <td>
          <span class="st-badge ${getStatusClass(t.status || t.state)}">
            ${escapeHtml((t.status || t.state || 'UNKNOWN').toUpperCase())}
          </span>
        </td>
        <td>
          <strong>${formatNumber(t.item_count_total || 0)}</strong> items
        </td>
        <td>
          <div class="st-progress">
            <span class="st-badge ${getProgressClass(t.items_received, t.item_count_total)}">
              ${getProgressPercent(t.items_received, t.item_count_total)}%
            </span>
            <span class="st-progress__text">
              ${formatNumber(t.items_received || 0)} / ${formatNumber(t.item_count_total || 0)}
            </span>
          </div>
        </td>
        <td>
          <div>${formatDateTime(t.updated_at || t.created_at)}</div>
          ${t.age_hours_nz ? `<div style="font-size: 0.75rem; color: var(--st-gray-600);">~${t.age_hours_nz}h ago</div>` : ''}
        </td>
        <td>
          <a href="/modules/consignments/stock-transfers/pack.php?id=${encodeURIComponent(t.cis_internal_id || t.id)}"
             class="st-action-btn"
             onclick="event.stopPropagation();">
            <i class="fas fa-box"></i> Pack
          </a>
        </td>
      </tr>
    `).join('');
  }

  /**
   * Render loading state
   */
  function renderLoading() {
    if (!elements.transfersBody) return;

    elements.transfersBody.innerHTML = `
      <tr>
        <td colspan="7" class="st-loading">
          <i class="fas fa-spinner fa-spin st-loading__spinner"></i>
          <div>Loading transfers...</div>
        </td>
      </tr>
    `;
  }

  /**
   * Render empty state
   */
  function renderEmpty() {
    if (!elements.transfersBody) return;

    elements.transfersBody.innerHTML = `
      <tr>
        <td colspan="7" class="st-empty">
          <div class="st-empty__icon">
            <i class="fas fa-box-open"></i>
          </div>
          <div class="st-empty__title">No transfers found</div>
          <div class="st-empty__text">
            ${state.filters.state || state.filters.scope ? 'Try adjusting your filters' : 'Create a new transfer to get started'}
          </div>
        </td>
      </tr>
    `;
  }

  /**
   * Render error state
   */
  function renderError(message) {
    if (!elements.transfersBody) return;

    elements.transfersBody.innerHTML = `
      <tr>
        <td colspan="7" class="st-empty">
          <div class="st-empty__icon">
            <i class="fas fa-exclamation-triangle" style="color: var(--st-danger);"></i>
          </div>
          <div class="st-empty__title">Error Loading Transfers</div>
          <div class="st-empty__text">${escapeHtml(message)}</div>
          <button class="st-action-btn" onclick="location.reload();">
            <i class="fas fa-refresh"></i> Retry
          </button>
        </td>
      </tr>
    `;
  }

  /**
   * Show transfer detail modal
   */
  async function showTransferModal(transferId) {
    if (!elements.modal) return;

    // Show modal with loading state
    elements.modalTitle.textContent = `Transfer #${transferId}`;
    elements.modalBody.innerHTML = `
      <div class="text-center py-5">
        <i class="fas fa-spinner fa-spin fa-3x" style="color: var(--st-primary);"></i>
        <p class="mt-3">Loading transfer details...</p>
      </div>
    `;

    $(elements.modal).modal('show');

    // Load transfer details
    try {
      const response = await fetch(CONFIG.apiUrl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
          action: 'get_transfer_detail',
          id: transferId
        })
      });

      const data = await response.json();

      if (data.success) {
        renderTransferDetail(data.data);
      } else {
        elements.modalBody.innerHTML = `
          <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle"></i>
            ${escapeHtml(data.error?.message || 'Failed to load transfer details')}
          </div>
        `;
      }
    } catch (error) {
      console.error('Failed to load transfer details:', error);
      elements.modalBody.innerHTML = `
        <div class="alert alert-danger">
          <i class="fas fa-exclamation-circle"></i>
          Network error. Please try again.
        </div>
      `;
    }
  }

  /**
   * Render transfer detail in modal
   */
  function renderTransferDetail(transfer) {
    elements.modalBody.innerHTML = `
      <div class="transfer-detail">
        <div class="detail-card">
          <div class="detail-label">From Outlet</div>
          <div class="detail-value">${escapeHtml(transfer.from_outlet_name || '-')}</div>
        </div>
        <div class="detail-card">
          <div class="detail-label">To Outlet</div>
          <div class="detail-value">${escapeHtml(transfer.to_outlet_name || '-')}</div>
        </div>
        <div class="detail-card">
          <div class="detail-label">Status</div>
          <div class="detail-value">
            <span class="st-badge ${getStatusClass(transfer.status)}">
              ${escapeHtml((transfer.status || 'UNKNOWN').toUpperCase())}
            </span>
          </div>
        </div>
        <div class="detail-card">
          <div class="detail-label">Total Items</div>
          <div class="detail-value">${formatNumber(transfer.item_count_total || 0)}</div>
        </div>
        <div class="detail-card">
          <div class="detail-label">Items Received</div>
          <div class="detail-value">${formatNumber(transfer.items_received || 0)}</div>
        </div>
        <div class="detail-card">
          <div class="detail-label">Progress</div>
          <div class="detail-value">${getProgressPercent(transfer.items_received, transfer.item_count_total)}%</div>
        </div>
        <div class="detail-card">
          <div class="detail-label">Created</div>
          <div class="detail-value">${formatDateTime(transfer.created_at)}</div>
        </div>
        <div class="detail-card">
          <div class="detail-label">Last Updated</div>
          <div class="detail-value">${formatDateTime(transfer.updated_at)}</div>
        </div>
      </div>
      ${transfer.latest_note ? `
        <div class="mt-4">
          <h6>Latest Note</h6>
          <div class="detail-card">
            ${escapeHtml(transfer.latest_note)}
          </div>
        </div>
      ` : ''}
      <div class="mt-4 text-right">
        <a href="/modules/consignments/stock-transfers/pack.php?id=${encodeURIComponent(transfer.cis_internal_id || transfer.id)}"
           class="st-action-btn success">
          <i class="fas fa-box"></i> Open Packing View
        </a>
      </div>
    `;
  }

  /**
   * Auto-refresh
   */
  function startAutoRefresh() {
    stopAutoRefresh();
    state.refreshTimer = setInterval(() => {
      if (!document.hidden) {
        loadCounts();
        loadTransfers();
      }
    }, CONFIG.refreshInterval);
  }

  function stopAutoRefresh() {
    if (state.refreshTimer) {
      clearInterval(state.refreshTimer);
      state.refreshTimer = null;
    }
  }

  /**
   * Utility functions
   */
  function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text || '';
    return div.innerHTML;
  }

  function formatNumber(num) {
    return (num || 0).toLocaleString();
  }

  function formatDateTime(dt) {
    if (!dt) return '-';
    const date = new Date(dt.replace(' ', 'T'));
    return date.toLocaleString();
  }

  function getStatusClass(status) {
    const s = (status || '').toUpperCase();
    if (s === 'RECEIVED') return 'success';
    if (s === 'SENT' || s === 'RECEIVING') return 'warning';
    if (s === 'OPEN') return 'info';
    return 'secondary';
  }

  function getProgressClass(received, total) {
    const pct = getProgressPercent(received, total);
    if (pct >= 90) return 'success';
    if (pct >= 50) return 'warning';
    if (pct > 0) return 'info';
    return 'secondary';
  }

  function getProgressPercent(received, total) {
    if (!total) return 0;
    return Math.round((received || 0) / total * 100);
  }

  // Initialize when DOM is ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

})();
