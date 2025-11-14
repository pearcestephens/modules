<?php
/**
 * ORDER COMMAND CENTER
 * Production-grade order management with real-time updates
 *
 * Features:
 * - SSE real-time order changes
 * - Smart sorting algorithm (auto-optimizes on load)
 * - Courier batch printing
 * - Zero-distraction UI
 */

require_once __DIR__ . '/../base/bootstrap.php';

// Get user session
$user_id = getUserId();
if (!$user_id) {
    redirect('/login.php');
}

// Initialize sorting algorithm for this user (runs once on page load)
require_once __DIR__ . '/includes/order-sorting-engine.php';
calculate_order_priorities($db, $user_id);

// Get filter params
$status_filter = $_GET['status'] ?? 'processing';
$outlet_filter = $_GET['outlet'] ?? null;
$sort_by = $_GET['sort'] ?? 'priority'; // priority, age, value
$view_mode = $_GET['view'] ?? 'cards'; // cards, compact

include("assets/template/html-header.php");
include("assets/template/header.php");
?>

<style>
/* Zero-distraction, high-performance UI */
:root {
    --color-processing: #3b82f6;
    --color-ready: #10b981;
    --color-urgent: #ef4444;
    --color-hold: #f59e0b;
    --shadow-card: 0 1px 3px rgba(0,0,0,0.1);
    --shadow-card-hover: 0 4px 12px rgba(0,0,0,0.15);
}

.order-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 16px;
    margin-top: 20px;
}

.order-card {
    background: white;
    border-radius: 8px;
    padding: 16px;
    box-shadow: var(--shadow-card);
    transition: all 0.2s ease;
    border-left: 4px solid var(--color-processing);
    position: relative;
}

.order-card:hover {
    box-shadow: var(--shadow-card-hover);
    transform: translateY(-2px);
}

.order-card.urgent { border-left-color: var(--color-urgent); }
.order-card.ready { border-left-color: var(--color-ready); }
.order-card.hold { border-left-color: var(--color-hold); }

.order-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
}

.order-id {
    font-size: 16px;
    font-weight: 600;
    color: #1f2937;
}

.priority-badge {
    font-size: 11px;
    padding: 4px 8px;
    border-radius: 12px;
    font-weight: 600;
    text-transform: uppercase;
}

.badge-critical { background: #fee2e2; color: #991b1b; }
.badge-high { background: #fef3c7; color: #92400e; }
.badge-medium { background: #dbeafe; color: #1e40af; }
.badge-low { background: #f3f4f6; color: #6b7280; }

.order-customer {
    font-size: 14px;
    color: #374151;
    margin-bottom: 8px;
}

.order-meta {
    display: flex;
    gap: 12px;
    font-size: 12px;
    color: #6b7280;
    margin-bottom: 12px;
}

.order-meta span {
    display: flex;
    align-items: center;
    gap: 4px;
}

.order-actions {
    display: flex;
    gap: 8px;
}

.btn-action {
    flex: 1;
    padding: 8px 12px;
    font-size: 13px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 500;
    transition: all 0.15s;
}

.btn-primary-action {
    background: #3b82f6;
    color: white;
}

.btn-primary-action:hover {
    background: #2563eb;
}

.btn-secondary-action {
    background: #f3f4f6;
    color: #374151;
}

.btn-secondary-action:hover {
    background: #e5e7eb;
}

.filters-bar {
    background: white;
    padding: 16px;
    border-radius: 8px;
    margin-bottom: 20px;
    display: flex;
    gap: 12px;
    align-items: center;
    flex-wrap: wrap;
}

.filter-group {
    display: flex;
    align-items: center;
    gap: 8px;
}

.filter-label {
    font-size: 13px;
    font-weight: 500;
    color: #6b7280;
}

.filter-select {
    padding: 8px 12px;
    border: 1px solid #e5e7eb;
    border-radius: 6px;
    font-size: 13px;
    color: #374151;
    background: white;
    cursor: pointer;
}

.stats-bar {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 16px;
    margin-bottom: 20px;
}

.stat-card {
    background: white;
    padding: 16px;
    border-radius: 8px;
    box-shadow: var(--shadow-card);
}

.stat-value {
    font-size: 28px;
    font-weight: 700;
    color: #1f2937;
    margin-bottom: 4px;
}

.stat-label {
    font-size: 13px;
    color: #6b7280;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.live-indicator {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    background: #ecfdf5;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    color: #059669;
}

.live-dot {
    width: 6px;
    height: 6px;
    background: #10b981;
    border-radius: 50%;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}

.fraud-warning {
    background: #fef2f2;
    border: 1px solid #fecaca;
    border-radius: 6px;
    padding: 8px;
    font-size: 12px;
    color: #991b1b;
    margin-bottom: 8px;
}

.stock-indicator {
    font-size: 12px;
    display: flex;
    align-items: center;
    gap: 4px;
}

.stock-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
}

.stock-available { background: #10b981; }
.stock-partial { background: #f59e0b; }
.stock-none { background: #ef4444; }

.compact-view .order-card {
    padding: 12px;
}

.compact-view .order-grid {
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 12px;
}

/* Batch printing UI */
.batch-toolbar {
    position: fixed;
    bottom: 20px;
    right: 20px;
    background: white;
    padding: 16px 20px;
    border-radius: 12px;
    box-shadow: 0 10px 40px rgba(0,0,0,0.2);
    display: none;
    align-items: center;
    gap: 16px;
    z-index: 1000;
}

.batch-toolbar.active {
    display: flex;
}

.batch-count {
    font-size: 14px;
    font-weight: 600;
    color: #374151;
}

.btn-batch {
    padding: 10px 20px;
    border: none;
    border-radius: 6px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-batch-primary {
    background: #3b82f6;
    color: white;
}

.btn-batch-primary:hover {
    background: #2563eb;
}
</style>

<body class="app header-fixed sidebar-fixed aside-menu-fixed sidebar-lg-show">
  <div class="app-body">
    <?php include("assets/template/sidemenu.php"); ?>
    <main class="main">
      <ol class="breadcrumb">
        <li class="breadcrumb-item">Home</li>
        <li class="breadcrumb-item"><a href="#">Orders</a></li>
        <li class="breadcrumb-item active">Command Center</li>
        <li class="breadcrumb-menu d-md-down-none">
          <span class="live-indicator">
            <span class="live-dot"></span>
            LIVE
          </span>
        </li>
      </ol>

      <div class="container-fluid">
        <div class="animated fadeIn">

          <!-- Stats Bar -->
          <div class="stats-bar">
            <div class="stat-card">
              <div class="stat-value" id="stat-processing">0</div>
              <div class="stat-label">Processing</div>
            </div>
            <div class="stat-card">
              <div class="stat-value" id="stat-ready">0</div>
              <div class="stat-label">Ready to Ship</div>
            </div>
            <div class="stat-card">
              <div class="stat-value" id="stat-urgent">0</div>
              <div class="stat-label">Urgent</div>
            </div>
            <div class="stat-card">
              <div class="stat-value" id="stat-revenue">$0</div>
              <div class="stat-label">Today's Revenue</div>
            </div>
          </div>

          <!-- Filters -->
          <div class="filters-bar">
            <div class="filter-group">
              <span class="filter-label">Status:</span>
              <select class="filter-select" id="filter-status">
                <option value="processing" selected>Processing</option>
                <option value="ready">Ready to Ship</option>
                <option value="all">All Active</option>
                <option value="hold">On Hold</option>
              </select>
            </div>

            <div class="filter-group">
              <span class="filter-label">Store:</span>
              <select class="filter-select" id="filter-outlet">
                <option value="">All Stores</option>
                <?php
                $outlets = $conn->query("SELECT id, name FROM vend_outlets WHERE deleted_at IS NULL ORDER BY name");
                while ($outlet = $outlets->fetch(PDO::FETCH_ASSOC)) {
                    echo "<option value='{$outlet['id']}'>{$outlet['name']}</option>";
                }
                ?>
              </select>
            </div>

            <div class="filter-group">
              <span class="filter-label">Sort:</span>
              <select class="filter-select" id="filter-sort">
                <option value="priority" selected>Priority</option>
                <option value="age">Oldest First</option>
                <option value="value">Highest Value</option>
                <option value="customer">Customer Name</option>
              </select>
            </div>

            <div class="filter-group">
              <span class="filter-label">View:</span>
              <select class="filter-select" id="filter-view">
                <option value="cards" selected>Cards</option>
                <option value="compact">Compact</option>
              </select>
            </div>

            <div style="margin-left: auto;">
              <button class="btn btn-sm btn-success" onclick="window.location.href='/modules/ecommerce-ops/print-labels.php'">
                <i class="fa fa-print"></i> Batch Print
              </button>
            </div>
          </div>

          <!-- Orders Grid -->
          <div class="order-grid" id="orders-container">
            <!-- Orders loaded via AJAX -->
          </div>

        </div>
      </div>
    </main>
  </div>

  <!-- Batch Selection Toolbar -->
  <div class="batch-toolbar" id="batch-toolbar">
    <span class="batch-count"><span id="batch-selected-count">0</span> orders selected</span>
    <button class="btn-batch btn-batch-primary" onclick="batchPrintLabels()">
      <i class="fa fa-print"></i> Print Labels
    </button>
    <button class="btn-batch" style="background: #f3f4f6; color: #374151;" onclick="clearSelection()">
      Clear
    </button>
  </div>

  <script>
  // ===== PRODUCTION-GRADE ORDER MANAGEMENT SYSTEM =====

  const state = {
      selectedOrders: new Set(),
      filters: {
          status: 'processing',
          outlet: null,
          sort: 'priority'
      },
      lastEventId: 0,
      eventSource: null
  };

  // Initialize on page load
  document.addEventListener('DOMContentLoaded', function() {
      loadOrders();
      setupSSE();
      setupFilters();
      updateStats();
  });

  // ===== ORDER LOADING =====
  function loadOrders() {
      const params = new URLSearchParams({
          status: state.filters.status,
          outlet: state.filters.outlet || '',
          sort: state.filters.sort
      });

      fetch(`/modules/ecommerce-ops/api/orders-list.php?${params}`)
          .then(r => r.json())
          .then(data => {
              renderOrders(data.orders);
              updateStats(data.stats);
          })
          .catch(err => console.error('Load error:', err));
  }

  // ===== RENDER ORDERS =====
  function renderOrders(orders) {
      const container = document.getElementById('orders-container');

      if (!orders || orders.length === 0) {
          container.innerHTML = `
              <div style="grid-column: 1/-1; text-align: center; padding: 60px 20px; color: #6b7280;">
                  <i class="fa fa-inbox fa-3x mb-3"></i>
                  <p>No orders found</p>
              </div>
          `;
          return;
      }

      container.innerHTML = orders.map(order => `
          <div class="order-card ${order.urgency_level}" data-order-id="${order.id}">
              ${order.fraud_risk_score > 60 ? `
                  <div class="fraud-warning">
                      <i class="fa fa-exclamation-triangle"></i>
                      Fraud Risk: ${order.fraud_risk_score}/100
                  </div>
              ` : ''}

              <div class="order-header">
                  <span class="order-id">#${order.public_id || order.id}</span>
                  <span class="priority-badge badge-${order.urgency_level}">
                      ${order.urgency_level}
                  </span>
              </div>

              <div class="order-customer">
                  <i class="fa fa-user"></i> ${escapeHtml(order.customer_name || 'Guest')}
              </div>

              <div class="order-meta">
                  <span><i class="fa fa-clock"></i> ${formatTime(order.created_at)}</span>
                  <span><i class="fa fa-dollar-sign"></i> ${formatMoney(order.total)}</span>
                  <span><i class="fa fa-box"></i> ${order.items_count} items</span>
              </div>

              ${order.optimal_outlet_name ? `
                  <div class="stock-indicator">
                      <span class="stock-dot stock-${order.stock_status}"></span>
                      Suggested: ${order.optimal_outlet_name}
                  </div>
              ` : ''}

              <div class="order-actions">
                  <button class="btn-action btn-primary-action" onclick="viewOrder(${order.id})">
                      <i class="fa fa-eye"></i> View
                  </button>
                  <button class="btn-action btn-secondary-action" onclick="toggleBatchSelect(${order.id})">
                      <i class="fa fa-check"></i> Select
                  </button>
              </div>
          </div>
      `).join('');
  }

  // ===== SERVER-SENT EVENTS (REAL-TIME UPDATES) =====
  function setupSSE() {
      if (state.eventSource) {
          state.eventSource.close();
      }

      state.eventSource = new EventSource(`/modules/ecommerce-ops/api/order-stream.php?last_event_id=${state.lastEventId}`);

      state.eventSource.addEventListener('order_change', function(e) {
          const data = JSON.parse(e.data);
          state.lastEventId = data.event_id;

          // Update UI based on change type
          switch(data.change_type) {
              case 'new_order':
                  showNotification('New Order', `Order #${data.order_id} received`);
                  loadOrders(); // Refresh list
                  break;
              case 'status_change':
                  updateOrderCard(data.order_id, data);
                  break;
              case 'assigned':
                  updateOrderCard(data.order_id, data);
                  break;
          }

          updateStats();
      });

      state.eventSource.addEventListener('stats_update', function(e) {
          const stats = JSON.parse(e.data);
          updateStats(stats);
      });

      state.eventSource.onerror = function() {
          console.warn('SSE connection lost, reconnecting...');
          setTimeout(setupSSE, 5000);
      };
  }

  // ===== UPDATE SINGLE ORDER CARD =====
  function updateOrderCard(orderId, data) {
      const card = document.querySelector(`[data-order-id="${orderId}"]`);
      if (card && data.remove) {
          card.style.opacity = '0';
          setTimeout(() => card.remove(), 300);
      } else if (!card && data.add) {
          loadOrders(); // Reload to add new order
      }
  }

  // ===== UPDATE STATS =====
  function updateStats(stats) {
      if (!stats) {
          fetch('/modules/ecommerce-ops/api/order-stats.php')
              .then(r => r.json())
              .then(data => updateStats(data))
              .catch(err => console.error('Stats error:', err));
          return;
      }

      document.getElementById('stat-processing').textContent = stats.processing || 0;
      document.getElementById('stat-ready').textContent = stats.ready || 0;
      document.getElementById('stat-urgent').textContent = stats.urgent || 0;
      document.getElementById('stat-revenue').textContent = formatMoney(stats.revenue || 0);
  }

  // ===== FILTERS =====
  function setupFilters() {
      document.getElementById('filter-status').addEventListener('change', function(e) {
          state.filters.status = e.target.value;
          loadOrders();
      });

      document.getElementById('filter-outlet').addEventListener('change', function(e) {
          state.filters.outlet = e.target.value || null;
          loadOrders();
      });

      document.getElementById('filter-sort').addEventListener('change', function(e) {
          state.filters.sort = e.target.value;
          loadOrders();
      });

      document.getElementById('filter-view').addEventListener('change', function(e) {
          const container = document.querySelector('.order-grid');
          if (e.target.value === 'compact') {
              container.classList.add('compact-view');
          } else {
              container.classList.remove('compact-view');
          }
      });
  }

  // ===== BATCH OPERATIONS =====
  function toggleBatchSelect(orderId) {
      if (state.selectedOrders.has(orderId)) {
          state.selectedOrders.delete(orderId);
      } else {
          state.selectedOrders.add(orderId);
      }

      updateBatchToolbar();
  }

  function updateBatchToolbar() {
      const toolbar = document.getElementById('batch-toolbar');
      const count = state.selectedOrders.size;

      if (count > 0) {
          toolbar.classList.add('active');
          document.getElementById('batch-selected-count').textContent = count;
      } else {
          toolbar.classList.remove('active');
      }
  }

  function clearSelection() {
      state.selectedOrders.clear();
      updateBatchToolbar();
  }

  function batchPrintLabels() {
      if (state.selectedOrders.size === 0) return;

      const orderIds = Array.from(state.selectedOrders);
      window.open(`/modules/ecommerce-ops/print-labels.php?orders=${orderIds.join(',')}`, '_blank');
  }

  // ===== ACTIONS =====
  function viewOrder(orderId) {
      window.location.href = `/modules/ecommerce-ops/view-order.php?id=${orderId}`;
  }

  function showNotification(title, message) {
      if ("Notification" in window && Notification.permission === "granted") {
          new Notification(title, { body: message, icon: '/assets/img/logo.png' });
      }
  }

  // ===== UTILITIES =====
  function escapeHtml(text) {
      const div = document.createElement('div');
      div.textContent = text;
      return div.innerHTML;
  }

  function formatMoney(amount) {
      return '$' + parseFloat(amount || 0).toFixed(2);
  }

  function formatTime(timestamp) {
      const date = new Date(timestamp);
      const now = new Date();
      const diffMs = now - date;
      const diffMins = Math.floor(diffMs / 60000);

      if (diffMins < 60) return `${diffMins}m ago`;
      if (diffMins < 1440) return `${Math.floor(diffMins / 60)}h ago`;
      return date.toLocaleDateString();
  }

  // Request notification permission
  if ("Notification" in window && Notification.permission === "default") {
      Notification.requestPermission();
  }
  </script>

  <?php include("assets/template/html-footer.php"); ?>
  <?php include("assets/template/footer.php"); ?>
</body>
</html>
