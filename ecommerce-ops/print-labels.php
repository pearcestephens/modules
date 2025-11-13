<?php
/**
 * Print Shipping Labels
 * Bulk label printing for orders
 */

require_once __DIR__ . '/../base/bootstrap.php';
require_once __DIR__ . '/includes/order-sorting-engine.php';

$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    header('Location: /login.php');
    exit;
}

// Get pre-selected orders from query string
$selected_order_ids = [];
if (!empty($_GET['orders'])) {
    $selected_order_ids = array_map('intval', explode(',', $_GET['orders']));
}

// Get available carriers
$carriers = ['gss' => 'GoSweetSpot', 'nzpost' => 'NZ Post', 'pickup' => 'Store Pickup'];

include("assets/template/html-header.php");
include("assets/template/header.php");
?>

<style>
.print-wizard {
    max-width: 1200px;
    margin: 0 auto;
}

.wizard-steps {
    display: flex;
    justify-content: center;
    gap: 40px;
    margin-bottom: 40px;
}

.wizard-step {
    display: flex;
    align-items: center;
    gap: 12px;
    opacity: 0.4;
    transition: opacity 0.2s;
}

.wizard-step.active {
    opacity: 1;
}

.step-number {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: #e5e7eb;
    color: #6b7280;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 18px;
}

.wizard-step.active .step-number {
    background: #3b82f6;
    color: white;
}

.wizard-step.complete .step-number {
    background: #10b981;
    color: white;
}

.step-label {
    font-size: 14px;
    font-weight: 600;
    color: #374151;
}

.order-selection-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 12px;
    margin: 20px 0;
}

.selectable-order {
    background: white;
    border: 2px solid #e5e7eb;
    border-radius: 8px;
    padding: 12px;
    cursor: pointer;
    transition: all 0.2s;
}

.selectable-order:hover {
    border-color: #3b82f6;
    transform: translateY(-1px);
}

.selectable-order.selected {
    border-color: #3b82f6;
    background: #eff6ff;
}

.selectable-order .order-check {
    width: 20px;
    height: 20px;
    border-radius: 4px;
    border: 2px solid #d1d5db;
    display: inline-block;
    position: relative;
}

.selectable-order.selected .order-check {
    background: #3b82f6;
    border-color: #3b82f6;
}

.selectable-order.selected .order-check::after {
    content: '✓';
    color: white;
    position: absolute;
    left: 3px;
    top: -2px;
}

.carrier-selection {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 16px;
    margin: 20px 0;
}

.carrier-card {
    background: white;
    border: 3px solid #e5e7eb;
    border-radius: 12px;
    padding: 24px;
    cursor: pointer;
    text-align: center;
    transition: all 0.2s;
}

.carrier-card:hover {
    border-color: #3b82f6;
    transform: translateY(-2px);
}

.carrier-card.selected {
    border-color: #3b82f6;
    background: #eff6ff;
}

.carrier-icon {
    font-size: 48px;
    margin-bottom: 12px;
}

.carrier-name {
    font-size: 18px;
    font-weight: 700;
    color: #1f2937;
    margin-bottom: 4px;
}

.carrier-description {
    font-size: 13px;
    color: #6b7280;
}

.batch-summary {
    background: white;
    border-radius: 12px;
    padding: 24px;
    margin: 20px 0;
}

.summary-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.summary-stat {
    text-align: center;
}

.summary-value {
    font-size: 32px;
    font-weight: 700;
    color: #1f2937;
}

.summary-label {
    font-size: 13px;
    color: #6b7280;
    text-transform: uppercase;
    margin-top: 4px;
}

.label-preview {
    border: 2px dashed #d1d5db;
    border-radius: 8px;
    padding: 20px;
    background: #f9fafb;
    text-align: center;
}

.btn-wizard {
    padding: 12px 32px;
    font-size: 16px;
    font-weight: 600;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-wizard-primary {
    background: #3b82f6;
    color: white;
}

.btn-wizard-primary:hover {
    background: #2563eb;
}

.btn-wizard-secondary {
    background: #f3f4f6;
    color: #374151;
}

.btn-wizard-secondary:hover {
    background: #e5e7eb;
}

.progress-bar-container {
    width: 100%;
    height: 8px;
    background: #e5e7eb;
    border-radius: 4px;
    overflow: hidden;
    margin: 20px 0;
}

.progress-bar {
    height: 100%;
    background: linear-gradient(90deg, #3b82f6, #10b981);
    transition: width 0.3s ease;
}

.thermal-options {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 16px;
    margin: 20px 0;
}

.thermal-option {
    background: white;
    border: 2px solid #e5e7eb;
    border-radius: 8px;
    padding: 16px;
    display: flex;
    align-items: center;
    gap: 12px;
}

.thermal-option input[type="radio"] {
    width: 20px;
    height: 20px;
}
</style>

<body class="app header-fixed sidebar-fixed aside-menu-fixed sidebar-lg-show">
  <div class="app-body">
    <?php include("assets/template/sidemenu.php"); ?>
    <main class="main">
      <ol class="breadcrumb">
        <li class="breadcrumb-item">Home</li>
        <li class="breadcrumb-item"><a href="/modules/ecommerce-ops/order-center.php">Orders</a></li>
        <li class="breadcrumb-item active">Batch Print Labels</li>
      </ol>

      <div class="container-fluid">
        <div class="animated fadeIn">
          <div class="print-wizard">

            <!-- Wizard Steps -->
            <div class="wizard-steps">
              <div class="wizard-step active" data-step="1">
                <div class="step-number">1</div>
                <div class="step-label">Select Orders</div>
              </div>
              <div class="wizard-step" data-step="2">
                <div class="step-number">2</div>
                <div class="step-label">Choose Carrier</div>
              </div>
              <div class="wizard-step" data-step="3">
                <div class="step-number">3</div>
                <div class="step-label">Generate Labels</div>
              </div>
            </div>

            <div class="card">
              <div class="card-body">

                <!-- STEP 1: Select Orders -->
                <div id="step-1" class="wizard-content">
                  <h4 class="mb-3">Select Orders for Batch Printing</h4>
                  <p class="text-muted mb-4">Choose which orders to include in this batch</p>

                  <div class="mb-3">
                    <button class="btn btn-sm btn-outline-primary" onclick="selectAll()">Select All</button>
                    <button class="btn btn-sm btn-outline-secondary ml-2" onclick="clearAll()">Clear All</button>
                    <span class="ml-3 text-muted"><span id="selected-count">0</span> orders selected</span>
                  </div>

                  <div class="order-selection-grid" id="order-grid">
                    <!-- Loaded via JS -->
                  </div>

                  <div class="text-center mt-4">
                    <button class="btn-wizard btn-wizard-primary" onclick="nextStep(2)" id="btn-next-1">
                      Continue to Carrier Selection →
                    </button>
                  </div>
                </div>

                <!-- STEP 2: Choose Carrier -->
                <div id="step-2" class="wizard-content" style="display:none;">
                  <h4 class="mb-3">Choose Courier Service</h4>
                  <p class="text-muted mb-4">Select which carrier to use for this batch</p>

                  <div class="carrier-selection">
                    <div class="carrier-card" data-carrier="gss" onclick="selectCarrier('gss')">
                      <div class="carrier-icon">📦</div>
                      <div class="carrier-name">GoSweetSpot</div>
                      <div class="carrier-description">Fast courier delivery with live tracking</div>
                    </div>

                    <div class="carrier-card" data-carrier="nzpost" onclick="selectCarrier('nzpost')">
                      <div class="carrier-icon">📮</div>
                      <div class="carrier-name">NZ Post</div>
                      <div class="carrier-description">Reliable postal service nationwide</div>
                    </div>

                    <div class="carrier-card" data-carrier="pickup" onclick="selectCarrier('pickup')">
                      <div class="carrier-icon">🏪</div>
                      <div class="carrier-name">Store Pickup</div>
                      <div class="carrier-description">Customer collects from store</div>
                    </div>
                  </div>

                  <div class="thermal-options" id="thermal-options" style="display:none;">
                    <h5 class="col-span-2">Label Format</h5>
                    <div class="thermal-option">
                      <input type="radio" name="label_format" value="thermal" id="format-thermal" checked>
                      <label for="format-thermal">
                        <strong>4x6 Thermal Label</strong><br>
                        <small>Direct to thermal printer (ZPL)</small>
                      </label>
                    </div>
                    <div class="thermal-option">
                      <input type="radio" name="label_format" value="a4" id="format-a4">
                      <label for="format-a4">
                        <strong>A4 Sheet</strong><br>
                        <small>Regular office printer (PDF)</small>
                      </label>
                    </div>
                  </div>

                  <div class="text-center mt-4">
                    <button class="btn-wizard btn-wizard-secondary mr-3" onclick="prevStep(1)">
                      ← Back
                    </button>
                    <button class="btn-wizard btn-wizard-primary" onclick="nextStep(3)" id="btn-next-2" disabled>
                      Continue to Generate →
                    </button>
                  </div>
                </div>

                <!-- STEP 3: Generate Labels -->
                <div id="step-3" class="wizard-content" style="display:none;">
                  <h4 class="mb-3">Generate & Print Labels</h4>

                  <div class="batch-summary">
                    <h5>Batch Summary</h5>
                    <div class="summary-grid">
                      <div class="summary-stat">
                        <div class="summary-value" id="summary-count">0</div>
                        <div class="summary-label">Orders</div>
                      </div>
                      <div class="summary-stat">
                        <div class="summary-value" id="summary-carrier">-</div>
                        <div class="summary-label">Carrier</div>
                      </div>
                      <div class="summary-stat">
                        <div class="summary-value" id="summary-weight">0 kg</div>
                        <div class="summary-label">Total Weight</div>
                      </div>
                      <div class="summary-stat">
                        <div class="summary-value" id="summary-cost">$0.00</div>
                        <div class="summary-label">Est. Cost</div>
                      </div>
                    </div>
                  </div>

                  <div class="progress-bar-container">
                    <div class="progress-bar" id="generation-progress" style="width: 0%"></div>
                  </div>

                  <div id="generation-status" class="text-center mb-4">
                    <p class="text-muted">Click generate to create labels</p>
                  </div>

                  <div class="text-center mt-4">
                    <button class="btn-wizard btn-wizard-secondary mr-3" onclick="prevStep(2)">
                      ← Back
                    </button>
                    <button class="btn-wizard btn-wizard-primary" onclick="generateBatch()" id="btn-generate">
                      🚀 Generate Labels
                    </button>
                  </div>

                  <div id="download-section" style="display:none;" class="mt-4 text-center">
                    <div class="label-preview mb-3">
                      <i class="fa fa-file-pdf fa-3x mb-3" style="color: #ef4444;"></i>
                      <p><strong>Labels Ready!</strong></p>
                      <p class="text-muted">Your batch has been generated successfully</p>
                    </div>
                    <button class="btn btn-success btn-lg" onclick="downloadLabels()">
                      <i class="fa fa-download"></i> Download PDF
                    </button>
                    <button class="btn btn-primary btn-lg ml-2" onclick="printThermal()">
                      <i class="fa fa-print"></i> Print to Thermal
                    </button>
                  </div>
                </div>

              </div>
            </div>

          </div>
        </div>
      </div>
    </main>
  </div>

  <script>
  const state = {
      selectedOrders: new Set(<?php echo json_encode($selected_order_ids); ?>),
      selectedCarrier: null,
      currentStep: 1,
      batchCode: null
  };

  // Initialize
  document.addEventListener('DOMContentLoaded', function() {
      loadOrders();
      updateSelectedCount();
  });

  // Load available orders
  function loadOrders() {
      fetch('/modules/ecommerce-ops/api/orders-list.php?status=ready')
          .then(r => r.json())
          .then(data => {
              renderOrderGrid(data.orders);
          });
  }

  // Render order selection grid
  function renderOrderGrid(orders) {
      const grid = document.getElementById('order-grid');
      grid.innerHTML = orders.map(order => `
          <div class="selectable-order ${state.selectedOrders.has(order.id) ? 'selected' : ''}"
               data-order-id="${order.id}"
               onclick="toggleOrder(${order.id})">
              <div class="d-flex justify-content-between align-items-center mb-2">
                  <strong>#${order.id}</strong>
                  <span class="order-check"></span>
              </div>
              <div style="font-size: 13px; color: #6b7280;">
                  ${order.customer_name}<br>
                  ${order.items_count} items • $${order.total}
              </div>
          </div>
      `).join('');
  }

  // Toggle order selection
  function toggleOrder(orderId) {
      if (state.selectedOrders.has(orderId)) {
          state.selectedOrders.delete(orderId);
      } else {
          state.selectedOrders.add(orderId);
      }

      // Update UI
      const card = document.querySelector(`[data-order-id="${orderId}"]`);
      card.classList.toggle('selected');
      updateSelectedCount();
  }

  // Select/clear all
  function selectAll() {
      document.querySelectorAll('.selectable-order').forEach(card => {
          const orderId = parseInt(card.dataset.orderId);
          state.selectedOrders.add(orderId);
          card.classList.add('selected');
      });
      updateSelectedCount();
  }

  function clearAll() {
      state.selectedOrders.clear();
      document.querySelectorAll('.selectable-order').forEach(card => {
          card.classList.remove('selected');
      });
      updateSelectedCount();
  }

  function updateSelectedCount() {
      document.getElementById('selected-count').textContent = state.selectedOrders.size;
      document.getElementById('btn-next-1').disabled = state.selectedOrders.size === 0;
  }

  // Select carrier
  function selectCarrier(carrier) {
      state.selectedCarrier = carrier;

      document.querySelectorAll('.carrier-card').forEach(card => {
          card.classList.remove('selected');
      });
      document.querySelector(`[data-carrier="${carrier}"]`).classList.add('selected');

      document.getElementById('btn-next-2').disabled = false;

      if (carrier !== 'pickup') {
          document.getElementById('thermal-options').style.display = 'grid';
      } else {
          document.getElementById('thermal-options').style.display = 'none';
      }
  }

  // Wizard navigation
  function nextStep(step) {
      // Hide current step
      document.getElementById(`step-${state.currentStep}`).style.display = 'none';
      document.querySelector(`[data-step="${state.currentStep}"]`).classList.remove('active');
      document.querySelector(`[data-step="${state.currentStep}"]`).classList.add('complete');

      // Show next step
      state.currentStep = step;
      document.getElementById(`step-${step}`).style.display = 'block';
      document.querySelector(`[data-step="${step}"]`).classList.add('active');

      // Update summary if on step 3
      if (step === 3) {
          updateSummary();
      }
  }

  function prevStep(step) {
      document.getElementById(`step-${state.currentStep}`).style.display = 'none';
      document.querySelector(`[data-step="${state.currentStep}"]`).classList.remove('active');

      state.currentStep = step;
      document.getElementById(`step-${step}`).style.display = 'block';
      document.querySelector(`[data-step="${step}"]`).classList.add('active');
  }

  // Update batch summary
  function updateSummary() {
      document.getElementById('summary-count').textContent = state.selectedOrders.size;
      document.getElementById('summary-carrier').textContent = {
          'gss': 'GoSweetSpot',
          'nzpost': 'NZ Post',
          'pickup': 'Store Pickup'
      }[state.selectedCarrier];
  }

  // Generate batch
  async function generateBatch() {
      const btn = document.getElementById('btn-generate');
      btn.disabled = true;
      btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Generating...';

      const orderIds = Array.from(state.selectedOrders);
      const labelFormat = document.querySelector('input[name="label_format"]:checked')?.value || 'thermal';

      try {
          // Simulate progress
          for (let i = 0; i <= 100; i += 10) {
              document.getElementById('generation-progress').style.width = i + '%';
              document.getElementById('generation-status').innerHTML = `<p>Generating labels... ${i}%</p>`;
              await new Promise(r => setTimeout(r, 200));
          }

          // Call API
          const response = await fetch('/modules/ecommerce-ops/api/generate-batch.php', {
              method: 'POST',
              headers: {'Content-Type': 'application/json'},
              body: JSON.stringify({
                  order_ids: orderIds,
                  carrier: state.selectedCarrier,
                  label_format: labelFormat
              })
          });

          const result = await response.json();

          if (result.success) {
              state.batchCode = result.batch_code;
              document.getElementById('generation-status').innerHTML = '<p class="text-success"><strong>✓ Generation Complete!</strong></p>';
              document.getElementById('download-section').style.display = 'block';
              btn.style.display = 'none';
          } else {
              throw new Error(result.error || 'Generation failed');
          }

      } catch (error) {
          document.getElementById('generation-status').innerHTML = `<p class="text-danger">Error: ${error.message}</p>`;
          btn.disabled = false;
          btn.innerHTML = '🚀 Generate Labels';
      }
  }

  // Download/print functions
  function downloadLabels() {
      window.open(`/modules/ecommerce-ops/api/download-batch.php?batch=${state.batchCode}`, '_blank');
  }

  function printThermal() {
      // Send to thermal printer via websocket or direct USB
      alert('Sending to thermal printer...\n\nBatch: ' + state.batchCode);
      // Actual implementation would connect to local printer driver
  }
  </script>

  <?php include("assets/template/html-footer.php"); ?>
  <?php include("assets/template/footer.php"); ?>
</body>
</html>
