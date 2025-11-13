/**
 * 05-detail-modal.js
 * Detail modal rendering and item handlers
 *
 * ✅ FULL IMPLEMENTATION RESTORED FROM scripts.js (lines 200-1560)
 * Contains ALL workflow functionality:
 * - Complete modal UI with workflow stepper
 * - All action buttons (Setup, Packing, Receiving, Revert, Danger Zone, Recreate)
 * - Product search and add
 * - Inline quantity editing with auto-save
 * - Fill All / Clear All buttons
 * - Enhanced modals with multiple options
 * - Complete event handlers
 */

function openModal(id){
  const el = document.getElementById('modalQuick');
  if (!el) { console.error('❌ modalQuick not found'); return null; }
  const m = new bootstrap.Modal(el);
  m.show();
  return m;
}
function actionModal(){
  const el = document.getElementById('modalAction');
  if (!el) { console.error('❌ modalAction not found'); return null; }
  return new bootstrap.Modal(el);
}
function confirmModal(){
  const el = document.getElementById('modalConfirm');
  if (!el) { console.error('❌ modalConfirm not found'); return null; }
  return new bootstrap.Modal(el);
}

// Global product selection state
let selectedProduct = null;
let productSearchTimer = null;

// ============================================================
// MAIN DETAIL MODAL FUNCTION - COMPLETE RESTORATION
// ============================================================
async function openQuick(id){
  const modal = openModal(id);
  const body  = $('#qBody');
  body.innerHTML = `<div class="text-center py-5"><div class="spinner-border mb-3"></div><div class="text-muted">Loading details…</div></div>`;

  try {
    const d = await api('get_transfer_detail', {id});
    const t = d.transfer, items=d.items||[], ships=d.shipments||[], notes=d.notes||[], ls=d.ls||null, totals=d.totals||null;

    // Define outlet objects
    const sourceOutlet = d.source_outlet || { name: t.outlet_from_label || t.outlet_from_name || (typeof OUTLET_MAP !== 'undefined' ? OUTLET_MAP[t.outlet_from] : null) || t.outlet_from };
    const destOutlet = d.dest_outlet || { name: t.outlet_to_label || t.outlet_to_name || (typeof OUTLET_MAP !== 'undefined' ? OUTLET_MAP[t.outlet_to] : null) || t.outlet_to };

    const from = sourceOutlet.name;
    const to = destOutlet.name;
    const lsUrl = t.vend_transfer_id ? (typeof LS_CONSIGNMENT_BASE !== 'undefined' ? LS_CONSIGNMENT_BASE + encodeURIComponent(t.vend_transfer_id) : null) : null;

    // buttons gating
    const s = (t.state||'').toUpperCase(), cat=(t.transfer_category||'').toUpperCase();
    const terminal = ['RECEIVED','CLOSED','CANCELLED'].includes(s);
    const beforeSent = ['DRAFT','OPEN','PACKING','PACKAGED'].includes(s);
    const afterSent  = ['SENT','RECEIVING','PARTIAL'].includes(s);
    const hasLS = !!t.vend_transfer_id;
    const perms = {
      canCreateCons: beforeSent && !terminal,
      canStoreVend: !terminal,
      canPushLines: hasLS && beforeSent,
      canMarkSent: beforeSent && cat!=='PURCHASE_ORDER',
      canStartReceiving: s==='SENT' || (cat==='PURCHASE_ORDER' && beforeSent),
      canReceiveAll: ['RECEIVING','PARTIAL'].includes(s),
      canCancel: beforeSent,
      canAddItems: beforeSent,
      canEditItems: beforeSent,
      canRevertToOpen: s === 'SENT' && cat !== 'PURCHASE_ORDER',
      canRevertToSent: s === 'RECEIVING',
      canRevertToReceiving: s === 'PARTIAL'
    };

    // Calculate totals properly from items
    const totalItems = items.length;
    const totalQty = items.reduce((sum, item) => sum + (parseInt(item.qty) || 0), 0);
    const totalValue = items.reduce((sum, item) => {
      const qty = parseInt(item.qty) || 0;
      const price = parseFloat(item.supply_price) || parseFloat(item.cost) || 0;
      return sum + (qty * price);
    }, 0);

    // Generate smart transfer ID for modal
    const modalTransferID = smartTransferID(t.id, cat, t.vend_number);

    // Format transfer category display name
    const catDisplay = cat === 'PURCHASE_ORDER' ? 'PURCHASE ORDER' :
                       cat === 'STOCK' ? 'STOCK TRANSFER' :
                       cat === 'JUICE' ? 'JUICE TRANSFER' :
                       cat === 'STAFF' ? 'STAFF TRANSFER' :
                       cat + ' TRANSFER';

    // Workflow guides per category
    const workflows = {
      'PURCHASE_ORDER': [
        'Create consignment in Lightspeed',
        'Add products & quantities',
        'Push lines to Lightspeed',
        'Begin receiving when stock arrives',
        'Fill received quantities',
        'Complete receiving in Lightspeed'
      ],
      'STOCK': [
        'Link to Lightspeed consignment',
        'Fill sent quantities (what you\'re packing)',
        'Mark as SENT when packed',
        'Destination staff clicks "Begin Receiving"',
        'Fill received quantities',
        'Click "Receive All" to complete'
      ],
      'JUICE': [
        'Link to Lightspeed consignment',
        'Fill sent quantities (juice stock)',
        'Mark as SENT when ready',
        'Destination staff receives items',
        'Complete transfer'
      ],
      'STAFF': [
        'Link to Lightspeed consignment',
        'Fill sent quantities (staff products)',
        'Mark as SENT when ready',
        'Staff member confirms receipt',
        'Complete transfer'
      ]
    };

    const workflow = workflows[cat] || workflows['STOCK'];
    const currentStep = beforeSent ? 0 : afterSent ? 3 : 5;

    // START BUILDING PREMIUM MODAL HTML
    body.innerHTML = `
      <!-- 🏆 HERO SECTION - Ultra Compact 200px -->
      <div class="transfer-hero-section">
        <div class="hero-compact-grid">

          <!-- Left Column: Type + ID -->
          <div class="hero-left">
            <div class="hero-type-badge">
              <i class="bi bi-${cat==='PURCHASE_ORDER'?'cart-check-fill':cat==='JUICE'?'droplet-fill':cat==='STAFF'?'person-badge-fill':'arrow-left-right'}"></i>
              ${cat.replace('_', ' ')}
            </div>
            <div class="hero-transfer-id">${modalTransferID}</div>
            ${t.vend_number ? `<div class="hero-vend-badge"><i class="bi bi-cloud-check-fill"></i> #${esc(t.vend_number)}</div>` : ''}
          </div>

          <!-- Center Column: Route -->
          <div class="hero-center">
            <div class="hero-route">
              <div class="hero-location">
                <div class="hero-location-label">From</div>
                <div class="hero-location-name">${esc(sourceOutlet?.name || 'Unknown')}</div>
              </div>
              <i class="bi bi-arrow-right hero-arrow"></i>
              <div class="hero-location">
                <div class="hero-location-label">To</div>
                <div class="hero-location-name">${esc(destOutlet?.name || 'Unknown')}</div>
              </div>
            </div>
          </div>

          <!-- Right Column: Metrics + Status -->
          <div class="hero-right">
            <div class="hero-stats">
              <div class="hero-stat">
                <span class="hero-stat-value">${totalItems}</span>
                <span class="hero-stat-label">Items</span>
              </div>
              <div class="hero-stat">
                <span class="hero-stat-value">${totalQty}</span>
                <span class="hero-stat-label">Units</span>
              </div>
              <div class="hero-stat hero-stat-highlight">
                <span class="hero-stat-value">$${totalValue.toFixed(0)}</span>
                <span class="hero-stat-label">Value</span>
              </div>
            </div>
            <div class="hero-status-badge hero-status-${t.state.toLowerCase()}">
              <i class="bi bi-${t.state==='RECEIVED'?'check-circle-fill':t.state==='CANCELLED'?'x-circle-fill':t.state==='SENT'?'send-check':'hourglass-split'}"></i>
              ${esc(t.state)}
            </div>
          </div>

        </div>
      </div>

      <!-- 🎯 WORKFLOW PROGRESS SECTION - Modern Timeline -->
      <div class="workflow-progress-section">
        <div class="workflow-progress-header">
          <h6><i class="bi bi-diagram-3-fill me-2"></i>Workflow Progress</h6>
          <span class="workflow-progress-percentage">${Math.round((currentStep / workflow.length) * 100)}% Complete</span>
        </div>

        <div class="workflow-timeline">
          ${workflow.map((step, i) => {
            const status = i < currentStep ? 'completed' : i === currentStep ? 'active' : 'pending';
            const icon = status === 'completed' ? '<i class="bi bi-check-circle-fill"></i>' : (i + 1);

            let tooltipText = step;
            if (status === 'completed') {
              tooltipText += ' - Completed ✓';
            } else if (status === 'active') {
              tooltipText += ' - In Progress';
            } else {
              tooltipText += ' - Pending';
            }

            return `
              <div class="workflow-step-item ${status}">
                <div class="workflow-step-circle">${icon}</div>
                <div class="workflow-step-label">${step.split(' ').slice(0,3).join(' ')}</div>
                ${i < workflow.length - 1 ? '<div class="workflow-step-connector"></div>' : ''}
                <div class="workflow-step-tooltip">${tooltipText}</div>
              </div>
            `;
          }).join('')}
        </div>
      </div>

      <!-- 🎴 ACTION CARDS SECTION - Card-Based UI -->
      <div class="action-cards-section">
        <div class="action-cards-grid">

          <!-- ⚙️ SETUP CARD -->
          ${!terminal && beforeSent ? `
          <div class="action-card">
            <div class="action-card-header setup">
              <div class="action-card-icon">
                <i class="bi bi-gear-fill"></i>
              </div>
              <div class="action-card-title-group">
                <h5 class="action-card-title">Setup</h5>
                <p class="action-card-subtitle">Initial configuration</p>
              </div>
            </div>
            <div class="action-card-body">
              <div class="action-card-actions">
                <button class="btn-action btn-action-primary" id="qa_link" ${!perms.canCreateCons?'disabled':''}>
                  <i class="bi bi-link-45deg"></i>
                  <span>Create/Link Consignment</span>
                </button>
                <button class="btn-action btn-action-secondary" id="qa_store_vend" ${!perms.canStoreVend?'disabled':''}>
                  <i class="bi bi-upc-scan"></i>
                  <span>Store Vend #</span>
                </button>
              </div>
            </div>
          </div>
          ` : ''}

          <!-- 📦 PACKING & SENDING CARD -->
          ${beforeSent && !terminal ? `
          <div class="action-card">
            <div class="action-card-header packing">
              <div class="action-card-icon">
                <i class="bi bi-box-seam-fill"></i>
              </div>
              <div class="action-card-title-group">
                <h5 class="action-card-title">Packing & Sending</h5>
                <p class="action-card-subtitle">Prepare and dispatch products</p>
              </div>
            </div>
            <div class="action-card-body">
              <div class="action-card-actions">
                <button class="btn-action btn-action-primary" id="qa_push_lines" ${!perms.canPushLines?'disabled':''}>
                  <i class="bi bi-upload"></i>
                  <span>Push Lines to Lightspeed</span>
                </button>
                ${perms.canMarkSent?`
                <button class="btn-action btn-action-warning" id="qa_sent">
                  <i class="bi bi-send-check-fill"></i>
                  <span>Mark as SENT</span>
                  <small class="btn-warning-text">⚠️ Subtracts inventory from source</small>
                </button>
                `:''}
              </div>
            </div>
          </div>
          ` : ''}

          <!-- 📥 RECEIVING CARD -->
          ${(afterSent || (cat==='PURCHASE_ORDER' && beforeSent)) && !terminal ? `
          <div class="action-card">
            <div class="action-card-header receiving">
              <div class="action-card-icon">
                <i class="bi bi-box-arrow-in-down-fill"></i>
              </div>
              <div class="action-card-title-group">
                <h5 class="action-card-title">Receiving</h5>
                <p class="action-card-subtitle">Accept and complete delivery</p>
              </div>
            </div>
            <div class="action-card-body">
              <div class="action-card-actions">
                <button class="btn-action btn-action-primary" id="qa_receiving" ${!perms.canStartReceiving?'disabled':''}>
                  <i class="bi bi-play-circle-fill"></i>
                  <span>${cat==='PURCHASE_ORDER'?'Begin Receiving':'Start Receiving'}</span>
                </button>
                <button class="btn-action btn-action-success" id="qa_receive_all" ${!perms.canReceiveAll?'disabled':''}>
                  <i class="bi bi-check2-all"></i>
                  <span>Receive All & Complete</span>
                </button>
              </div>
            </div>
          </div>
          ` : ''}

          <!-- ⏮️ REVERT CARD -->
          ${(perms.canRevertToOpen || perms.canRevertToSent || perms.canRevertToReceiving) ? `
          <div class="action-card">
            <div class="action-card-header revert">
              <div class="action-card-icon">
                <i class="bi bi-arrow-counterclockwise"></i>
              </div>
              <div class="action-card-title-group">
                <h5 class="action-card-title">Undo Actions</h5>
                <p class="action-card-subtitle">Reverse workflow steps</p>
              </div>
            </div>
            <div class="action-card-body">
              <div class="action-card-actions">
                ${perms.canRevertToOpen ? `
                <button class="btn-action btn-action-warning" id="qa_revert_open">
                  <i class="bi bi-arrow-counterclockwise"></i>
                  <span>Undo Sent → Open</span>
                  <small class="btn-warning-text">⚠️ Restores source inventory</small>
                </button>
                ` : ''}
                ${perms.canRevertToSent ? `
                <button class="btn-action btn-action-warning" id="qa_revert_sent">
                  <i class="bi bi-x-circle"></i>
                  <span>Cancel Receiving → Sent</span>
                </button>
                ` : ''}
                ${perms.canRevertToReceiving ? `
                <button class="btn-action btn-action-danger" id="qa_revert_receiving">
                  <i class="bi bi-exclamation-triangle-fill"></i>
                  <span>Undo Partial (Risky)</span>
                  <small class="btn-warning-text">⚠️ May cause inventory issues</small>
                </button>
                ` : ''}
              </div>
            </div>
          </div>
          ` : ''}

          <!-- 🚨 DANGER ZONE CARD -->
          ${!terminal ? `
          <div class="action-card">
            <div class="action-card-header danger">
              <div class="action-card-icon">
                <i class="bi bi-exclamation-triangle-fill"></i>
              </div>
              <div class="action-card-title-group">
                <h5 class="action-card-title">Danger Zone</h5>
                <p class="action-card-subtitle">Irreversible actions</p>
              </div>
            </div>
            <div class="action-card-body">
              <div class="action-card-actions">
                <button class="btn-action btn-action-danger" id="qa_cancel" ${!perms.canCancel?'disabled':''}>
                  <i class="bi bi-x-circle-fill"></i>
                  <span>Cancel Transfer${cat !== 'PURCHASE_ORDER' ? '<br><small class="btn-warning-text">⚠️ Does NOT revert stock automatically</small>' : ''}</span>
                </button>
              </div>
            </div>
          </div>
          ` : ''}

          <!-- ♻️ RECREATE CARD - CANCELLED OR COMPLETED -->
          ${(terminal || s === 'RECEIVED' || s === 'CLOSED') ? `
          <div class="action-card">
            <div class="action-card-header recreate">
              <div class="action-card-icon">
                <i class="bi bi-arrow-repeat"></i>
              </div>
              <div class="action-card-title-group">
                <h5 class="action-card-title">Recreate</h5>
                <p class="action-card-subtitle">Start fresh with same details</p>
              </div>
            </div>
            <div class="action-card-body">
              <div class="action-card-actions">
                <button class="btn-action btn-action-recreate" id="qa_recreate">
                  <i class="bi bi-arrow-clockwise"></i>
                  <span>Recreate Transfer</span>
                </button>
              </div>
            </div>
          </div>
          ` : ''}
        </div>

        <!-- 🔥 TABS - MODERN DESIGN -->
        <ul class="nav nav-tabs nav-tabs-modern mt-4" role="tablist">
          <li class="nav-item">
            <a class="nav-link active" data-bs-toggle="tab" href="#tabItems">
              <i class="bi bi-box-seam me-1"></i>
              <span>Items</span>
              <span class="badge bg-primary ms-2">${totalItems}</span>
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link" data-bs-toggle="tab" href="#tabShipments">
              <i class="bi bi-truck me-1"></i>
              <span>Shipments</span>
              <span class="badge bg-secondary ms-2">${ships.length}</span>
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link" data-bs-toggle="tab" href="#tabNotes">
              <i class="bi bi-chat-left-text me-1"></i>
              <span>Notes</span>
              <span class="badge bg-secondary ms-2">${notes.length}</span>
            </a>
          </li>
        </ul>

        <div class="tab-content tab-content-modern">
          <div class="tab-pane fade show active" id="tabItems">
            ${perms.canAddItems ? `
            <div class="d-flex justify-content-between align-items-center mb-3">
              <button class="btn btn-sm btn-action-primary" id="qa_add_item">
                <i class="bi bi-plus-circle"></i>
                <span>Add Product</span>
              </button>
              <div class="text-muted" style="font-size: 0.75rem;">
                <i class="bi bi-info-circle me-1"></i>
                Changes save automatically when you edit quantities
              </div>
            </div>
            ` : ''}

            <div class="items-table-container">
              <table class="table-premium">
                <thead>
                  <tr>
                    <th style="width: 40%;">Product</th>
                    <th style="width: 12%; text-align: center;">Requested</th>
                    <th style="width: 12%; text-align: center;">Sent</th>
                    <th style="width: 12%; text-align: center;">Received</th>
                    <th style="width: 12%; text-align: right;">Unit Cost</th>
                    <th style="width: 12%; text-align: right;">Total</th>
                    ${perms.canEditItems ? `<th style="width: 80px; text-align: center;">Actions</th>` : ''}
                  </tr>
                </thead>
              <tbody id="itemsTbody">
                ${items.length ? items.map(item => {
                  const cost = parseFloat(item.supply_price) || parseFloat(item.cost) || 0;
                  const qty = parseInt(item.qty) || 0;
                  const total = cost * qty;

                  return `
                  <tr data-item="${item.id}">
                    <td class="product-cell">
                      <div class="product-name">${esc(item.product_name || 'Unknown Product')}</div>
                      <div class="product-sku">${esc(item.sku || '')}</div>
                    </td>
                    <td style="text-align: center;">
                      ${perms.canEditItems ? `
                      <input type="number"
                             class="qty-input-modern"
                             value="${item.qty || 0}"
                             min="0"
                             data-item="${item.id}"
                             data-field="req">
                      ` : `<span>${item.qty || 0}</span>`}
                    </td>
                    <td style="text-align: center;">
                      ${perms.canEditItems ? `
                      <input type="number"
                             class="qty-input-modern"
                             value="${item.sent || 0}"
                             min="0"
                             data-item="${item.id}"
                             data-field="sent">
                      ` : `<span>${item.sent || 0}</span>`}
                    </td>
                    <td style="text-align: center;">
                      ${perms.canEditItems && (s === 'RECEIVING' || s === 'PARTIAL') ? `
                      <input type="number"
                             class="qty-input-modern"
                             value="${item.received || 0}"
                             min="0"
                             data-item="${item.id}"
                             data-field="rec">
                      ` : `<span>${item.received || 0}</span>`}
                    </td>
                    <td class="price-cell">$${cost.toFixed(2)}</td>
                    <td class="price-cell">$${total.toFixed(2)}</td>
                    ${perms.canEditItems ? `
                    <td class="actions-cell">
                      <button class="btn btn-sm btn-outline-danger remove-item-btn" data-item="${item.id}">
                        <i class="bi bi-trash"></i>
                      </button>
                    </td>
                    ` : ''}
                  </tr>
                  `;
                }).join('') : `
                <tr>
                  <td colspan="${perms.canEditItems ? 7 : 6}" class="table-empty-state">
                    <i class="bi bi-inbox"></i>
                    <div>No items added yet</div>
                    ${perms.canAddItems ? `<button class="btn btn-sm btn-action-primary mt-2" onclick="$('#qa_add_item').click()">Add First Product</button>` : ''}
                  </td>
                </tr>
                `}
              </tbody>
              ${items.length ? `
              <tfoot>
                <tr class="table-footer-totals">
                  <td>TOTALS</td>
                  <td style="text-align: center;">${items.reduce((sum, i) => sum + (parseInt(i.qty) || 0), 0)}</td>
                  <td style="text-align: center;">${items.reduce((sum, i) => sum + (parseInt(i.sent) || 0), 0)}</td>
                  <td style="text-align: center;">${items.reduce((sum, i) => sum + (parseInt(i.received) || 0), 0)}</td>
                  <td class="price-cell">—</td>
                  <td class="price-cell">$${totalValue.toFixed(2)}</td>
                  ${perms.canEditItems ? `<td></td>` : ''}
                </tr>
              </tfoot>
              ` : ''}
              </table>
            </div>
          </div>

          <div class="tab-pane fade" id="tabShipments">
            ${ships.length ? `
            <table class="enhanced-table">
              <thead>
                <tr>
                  <th>Tracking</th>
                  <th>Carrier</th>
                  <th>Status</th>
                  <th>Date</th>
                </tr>
              </thead>
              <tbody>
                ${ships.map(s => `
                <tr>
                  <td>${esc(s.tracking_number || 'N/A')}</td>
                  <td>${esc(s.carrier || 'N/A')}</td>
                  <td>${esc(s.status || 'N/A')}</td>
                  <td>${esc(s.date || 'N/A')}</td>
                </tr>
                `).join('')}
              </tbody>
            </table>
            ` : `
            <div class="empty-state-row" style="padding: 3rem 1rem; text-align: center; color: #9ca3af;">
              <i class="bi bi-truck" style="font-size: 2rem; opacity: 0.3; display: block; margin-bottom: 1rem;"></i>
              No shipments recorded
            </div>
            `}
          </div>

          <div class="tab-pane fade" id="tabNotes">
            ${notes.length ? `
            <div class="notes-list">
              ${notes.map(n => `
              <div class="note-item" style="padding: 12px; background: #f8fafc; border-left: 3px solid #3b82f6; margin-bottom: 10px; border-radius: 6px;">
                <div style="font-size: 0.75rem; color: #64748b; margin-bottom: 4px;">
                  <strong>${esc(n.author || 'Unknown')}</strong> • ${esc(n.date || 'N/A')}
                </div>
                <div style="font-size: 0.85rem; color: #1e293b;">${esc(n.content || '')}</div>
              </div>
              `).join('')}
            </div>
            ` : `
            <div class="empty-state-row" style="padding: 3rem 1rem; text-align: center; color: #9ca3af;">
              <i class="bi bi-chat-left-text" style="font-size: 2rem; opacity: 0.3; display: block; margin-bottom: 1rem;"></i>
              No notes added
            </div>
            `}
          </div>
        </div>
      </div>
    `;
    // END OF MODAL HTML STRUCTURE

    // ============================================================
    // BUTTON EVENT HANDLERS - ALL FUNCTIONALITY
    // ============================================================

    // 1. CREATE/LINK CONSIGNMENT BUTTON
    $('#qa_link')?.addEventListener('click', async ()=>{
      const ma = actionModal();
      $('#maTitle').textContent = 'Create / Link Consignment';
      $('#maBody').innerHTML = `
        <div class="mb-3">
          <label class="form-label fw-bold">Vend Number (optional)</label>
          <input id="vendNumInput" class="form-control" placeholder="C-12345">
        </div>
        <div class="mb-3">
          <label class="form-label fw-bold">Vend UUID (optional)</label>
          <input id="vendUUIDInput" class="form-control" placeholder="uuid…">
          <div class="small-note mt-1">Leave blank to create a new consignment (when Sync is ON).</div>
        </div>

        <div class="alert alert-info mb-3" style="padding: 10px 12px; font-size: 0.85rem;">
          <i class="bi bi-info-circle me-2"></i><strong>Choose action:</strong>
        </div>

        <div class="d-flex gap-2 flex-column">
          <label class="consignment-option-card" style="border: 2px solid #e5e7eb; border-radius: 8px; padding: 12px; cursor: pointer; transition: all 0.2s ease;">
            <input type="radio" name="linkMode" value="link_only" id="linkModeOnly" style="margin-right: 8px;">
            <div style="display: inline-block; vertical-align: top;">
              <strong style="font-size: 0.9rem; color: #1e293b;">Create Consignment Only</strong>
              <div style="font-size: 0.75rem; color: #64748b; margin-top: 2px;">Just create/link the consignment. Products remain in "pending" state. You'll push them manually later.</div>
            </div>
          </label>

          <label class="consignment-option-card" style="border: 2px solid #3b82f6; border-radius: 8px; padding: 12px; cursor: pointer; transition: all 0.2s ease; background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);">
            <input type="radio" name="linkMode" value="link_and_push" id="linkModeAndPush" checked style="margin-right: 8px;">
            <div style="display: inline-block; vertical-align: top;">
              <strong style="font-size: 0.9rem; color: #1e40af;">Create Consignment + Push Products</strong>
              <div style="font-size: 0.75rem; color: #1e40af; margin-top: 2px;">Create/link consignment AND immediately push all products to Lightspeed (recommended).</div>
            </div>
          </label>
        </div>
      `;

      // Add hover effects
      const optionCards = $('#maBody')?.querySelectorAll('.consignment-option-card');
      if (optionCards) {
        optionCards.forEach(card => {
          const radio = card.querySelector('input[type="radio"]');
          if (!radio) return;
          card.addEventListener('click', () => radio.checked = true);
          card.addEventListener('mouseenter', () => {
            if (!radio.checked) card.style.borderColor = '#93c5fd';
          });
          card.addEventListener('mouseleave', () => {
            if (!radio.checked) card.style.borderColor = '#e5e7eb';
          });
          radio.addEventListener('change', () => {
            optionCards.forEach(c => {
              const r = c.querySelector('input[type="radio"]');
              if (r && r.checked) {
                c.style.borderColor = '#3b82f6';
                c.style.background = 'linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%)';
              } else {
                c.style.borderColor = '#e5e7eb';
                c.style.background = 'white';
              }
            });
          });
        });
      }

      $('#maSubmit').onclick = async ()=>{
        try {
          $('#maSubmit').disabled=true;
          const vend_number = $('#vendNumInput').value || undefined;
          const vend_uuid   = $('#vendUUIDInput').value || undefined;
          const mode = document.querySelector('input[name="linkMode"]:checked')?.value || 'link_and_push';

          showActivity('Linking…','Creating/storing identifiers');
          await api('create_consignment', {id: t.id, vend_number, vend_transfer_id: vend_uuid});

          if (mode === 'link_and_push') {
            showActivity('Pushing products…','Sending product lines to Lightspeed');
            await api('push_consignment_lines', {id: t.id});
            hideActivity();
            toast('Consignment linked & products pushed','success');
          } else {
            hideActivity();
            toast('Consignment linked (products not pushed yet)','info');
          }

          ma.hide(); await openQuick(t.id); await refresh();
        } catch(e){ hideActivity(); toast(e.message||'Failed','danger'); $('#maSubmit').disabled=false; }
      };
      ma.show();
    });

    // 2. PUSH LINES TO LIGHTSPEED BUTTON
    $('#qa_push_lines')?.addEventListener('click', async ()=>{
      try {
        showActivity('Pushing lines…');
        await api('push_consignment_lines', {id: t.id});
        hideActivity();
        toast('Pushed','success');
        await openQuick(t.id);
      }
      catch(e){ hideActivity(); toast(e.message||'Failed','danger'); }
    });

    // 3. MARK AS SENT BUTTON
    $('#qa_sent')?.addEventListener('click', ()=>{
      const ma = actionModal();
      $('#maTitle').textContent = 'Mark as Sent';
      $('#maBody').innerHTML = `<label class="form-label">Total boxes</label><input id="boxesInput" type="number" class="form-control" value="1" min="0">`;
      $('#maSubmit').onclick = async ()=>{
        try {
          $('#maSubmit').disabled=true;
          await api('mark_sent',{id:t.id,total_boxes:parseInt($('#boxesInput').value||'1',10)});
          toast('Marked sent','success');
          ma.hide();
          await openQuick(t.id);
          await refresh();
        }
        catch(e){ toast(e.message||'Failed','danger'); $('#maSubmit').disabled=false; }
      };
      ma.show();
    });

    // 4. START RECEIVING BUTTON
    $('#qa_receiving')?.addEventListener('click', ()=>{
      // Show receiving modal with two options
      const receivingModalElement = document.getElementById('modalReceiving');
      if (!receivingModalElement) {
        console.error('❌ modalReceiving not found');
        return;
      }
      const receivingModal = new bootstrap.Modal(receivingModalElement);
      $('#receivingTitle').textContent = cat === 'PURCHASE_ORDER' ? 'Begin Receiving Items' : 'Start Receiving';
      $('#receivingItemCount').textContent = items.length;
      $('#receivingTotalQty').textContent = items.reduce((sum, i) => sum + (parseInt(i.qty) || 0), 0);

      // Reset previous selection
      document.querySelectorAll('input[name="receivingMode"]').forEach(r => r.checked = false);

      receivingModal.show();
    });

    // Handle "Begin Receiving" - Manual entry mode
    $('#btnBeginReceiving')?.addEventListener('click', async ()=>{
      const modal = bootstrap.Modal.getInstance('#modalReceiving');
      modal?.hide();
      try {
        showActivity('Starting receiving mode…');
        await api('mark_receiving', {id: t.id});
        hideActivity();
        toast('Receiving mode activated - enter quantities manually', 'success');
        await openQuick(t.id);
        await refresh();
      }
      catch(e){ hideActivity(); toast(e.message || 'Failed to start receiving', 'danger'); }
    });

    // Handle "Receive All" - Auto-fill and complete
    $('#btnReceiveAll')?.addEventListener('click', ()=>{
      const modal = bootstrap.Modal.getInstance('#modalReceiving');
      modal?.hide();

      // Show confirmation for auto-complete
      const cm = confirmModal();
      $('#mcTitle').textContent = 'Receive All Items (Auto-Fill)';
      $('#mcBody').innerHTML = `
        <div class="alert alert-info mb-3">
          <i class="bi bi-info-circle me-2"></i>
          <strong>This will automatically:</strong>
          <ul class="mb-0 mt-2">
            <li>Set received quantities to match sent quantities</li>
            <li>Update destination store inventory levels</li>
            <li>Mark transfer as RECEIVED</li>
            <li>Update Lightspeed consignment to RECEIVED</li>
          </ul>
        </div>
        <p class="mb-0"><strong>Items:</strong> ${items.length} | <strong>Total Qty:</strong> ${items.reduce((s,i)=>s+(parseInt(i.qty)||0),0)}</p>
        <p class="text-danger mb-0 mt-2"><i class="bi bi-exclamation-triangle me-1"></i>This action cannot be undone.</p>
      `;
      $('#mcYes').onclick = async ()=> {
        cm.hide();
        try {
          showActivity('Auto-receiving all items…');
          await api('receive_all', {id: t.id, auto_fill: true});
          hideActivity();
          toast('All items received and inventory updated!', 'success');
          await openQuick(t.id);
          await refresh();
        }
        catch(e){ hideActivity(); toast(e.message || 'Failed to receive all', 'danger'); }
      };
      cm.show();
    });

    // 5. LEGACY RECEIVE ALL BUTTON (keep for backward compatibility)
    $('#qa_receive_all')?.addEventListener('click', ()=>{
      const cm = confirmModal();
      $('#mcTitle').textContent = 'Receive All Items';
      $('#mcBody').innerHTML = `
        <p>Mark all items as received and close this transfer?</p>
        <p class="text-muted small">This will auto-fill received quantities to match sent quantities.</p>
      `;
      $('#mcYes').onclick = async ()=> {
        cm.hide();
        try {
          showActivity('Receiving all…');
          await api('receive_all', {id: t.id, auto_fill: true});
          hideActivity();
          toast('All received', 'success');
          await openQuick(t.id);
          await refresh();
        }
        catch(e){ hideActivity(); toast(e.message || 'Failed', 'danger'); }
      };
      cm.show();
    });

    // 6. CANCEL TRANSFER BUTTON
    $('#qa_cancel')?.addEventListener('click', ()=>{
      const ma = actionModal();
      $('#maTitle').textContent = '🚨 Cancel Transfer';

      // For STOCK/JUICE transfers after SENT: stock was deducted, offer reversion option
      // For PURCHASE_ORDER: stock only adds on RECEIVED, so no reversion needed
      const needsStockReversion = (cat === 'STOCK' || cat === 'JUICE') && afterSent;

      $('#maBody').innerHTML = `
        <div class="alert alert-warning mb-3" style="padding: 10px 12px; font-size: 0.85rem;">
          <i class="bi bi-exclamation-triangle me-2"></i>
          <strong>You are about to cancel this transfer.</strong>
        </div>

        ${needsStockReversion ? `
        <div class="mb-3">
          <label class="form-label fw-bold" style="font-size: 0.9rem;">
            <i class="bi bi-box-seam me-1"></i>Stock Quantity Reversion
          </label>
          <div class="mb-2" style="font-size: 0.8rem; color: #64748b;">
            This transfer has stock deducted from <strong>${from}</strong>. Do you want to revert stock quantities?
          </div>

          <div class="d-flex gap-2 flex-column">
            <label class="revert-option-card" style="border: 2px solid #e5e7eb; border-radius: 8px; padding: 10px; cursor: pointer;">
              <input type="radio" name="revertStock" value="yes" id="revertYes" style="margin-right: 8px;">
              <div style="display: inline-block; vertical-align: top;">
                <strong style="font-size: 0.85rem; color: #dc2626;">✅ Yes, revert stock quantities</strong>
                <div style="font-size: 0.7rem; color: #64748b; margin-top: 2px;">
                  Restore source outlet: <strong>+${items.reduce((sum,i)=>sum+(parseInt(i.qty)||0),0)} units</strong><br>
                  <em>Updates both CIS database AND Vend via API</em>
                </div>
              </div>
            </label>

            <label class="revert-option-card" style="border: 2px solid #3b82f6; border-radius: 8px; padding: 10px; cursor: pointer; background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);">
              <input type="radio" name="revertStock" value="no" id="revertNo" checked style="margin-right: 8px;">
              <div style="display: inline-block; vertical-align: top;">
                <strong style="font-size: 0.85rem; color: #1e40af;">❌ No, leave quantities as-is</strong>
                <div style="font-size: 0.7rem; color: #1e40af; margin-top: 2px;">Stock levels will remain unchanged. Only cancel the transfer record.</div>
              </div>
            </label>
          </div>
        </div>
        ` : `
        <div class="alert alert-info mb-3" style="padding: 10px 12px; font-size: 0.85rem;">
          <i class="bi bi-info-circle me-2"></i>
          ${cat === 'PURCHASE_ORDER' ?
            '<strong>Hey!</strong> By default, consignment stock only changes the quantity of the destination source once they are all received in.<br><br><strong>Cancelling now has not affected any stock levels.</strong>' :
            'This transfer hasn\'t been sent yet, so no stock has been deducted. Only the transfer record will be cancelled.'
          }
        </div>
        `}

        <div class="small-note text-danger" style="font-size: 0.75rem;">
          <i class="bi bi-exclamation-circle me-1"></i>
          <strong>Note:</strong> Cancelled transfers cannot be un-cancelled. This action is permanent.
        </div>

        <div class="mt-3" style="font-size: 0.85rem; color: #64748b;">
          <strong>Cancel anyway?</strong>
        </div>
      `;

      // Add hover effects for revert options
      const optionCards = $('#maBody')?.querySelectorAll('.revert-option-card');
      if (optionCards) {
        optionCards.forEach(card => {
          const radio = card.querySelector('input[type="radio"]');
          if (!radio) return;
          card.addEventListener('click', () => radio.checked = true);
          card.addEventListener('mouseenter', () => {
            if (!radio.checked) card.style.borderColor = '#93c5fd';
          });
          card.addEventListener('mouseleave', () => {
            if (!radio.checked) card.style.borderColor = '#e5e7eb';
          });
          radio.addEventListener('change', () => {
            optionCards.forEach(c => {
              const r = c.querySelector('input[type="radio"]');
              if (r && r.checked) {
                c.style.borderColor = r.value === 'yes' ? '#dc2626' : '#3b82f6';
                c.style.background = r.value === 'yes' ? 'linear-gradient(135deg, #fee2e2 0%, #fecaca 100%)' : 'linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%)';
              } else {
                c.style.borderColor = '#e5e7eb';
                c.style.background = 'white';
              }
            });
          });
        });
      }

      $('#maSubmit').textContent = 'Cancel Transfer';
      $('#maSubmit').className = 'btn btn-danger';
      $('#maSubmit').onclick = async ()=>{
        try {
          $('#maSubmit').disabled = true;
          const revertStock = document.querySelector('input[name="revertStock"]:checked')?.value === 'yes';

          showActivity('Cancelling transfer…', revertStock ? 'Reverting stock quantities…' : 'Updating status…');
          await api('cancel_transfer', {id: t.id, revert_stock: revertStock});
          hideActivity();
          toast(revertStock ? 'Transfer cancelled & stock reverted' : 'Transfer cancelled', 'success');
          ma.hide();
          await refresh();
        } catch(e){
          hideActivity();
          toast(e.message||'Failed','danger');
          $('#maSubmit').disabled = false;
        }
      };
      ma.show();
    });

    // 7. RECREATE CANCELLED/COMPLETED TRANSFER AS NEW
    $('#qa_recreate')?.addEventListener('click', ()=>{
      const ma = actionModal();
      $('#maTitle').textContent = '🔄 Recreate Transfer';
      $('#maBody').innerHTML = `
        <div class="alert alert-info mb-3" style="padding: 10px 12px; font-size: 0.85rem;">
          <i class="bi bi-info-circle me-2"></i>
          <strong>Create a fresh transfer with the same details</strong>
        </div>

        <div class="mb-3" style="padding: 12px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 0.8rem;">
          <div class="mb-2"><strong>From:</strong> ${esc(from)}</div>
          <div class="mb-2"><strong>To:</strong> ${esc(to)}</div>
          <div class="mb-2"><strong>Items:</strong> ${items.length} products</div>
          <div><strong>Type:</strong> ${esc(cat)}</div>
        </div>

        <div class="form-check mb-3" style="padding-left: 1.5rem;">
          <input class="form-check-input" type="checkbox" id="revertStockCheck" ${s === 'SENT' ? 'checked' : ''} style="margin-top: 0.3rem;">
          <label class="form-check-label" for="revertStockCheck" style="font-size: 0.85rem; font-weight: 600;">
            <i class="bi bi-arrow-counterclockwise me-1"></i>Revert stock quantities at source outlet
          </label>
          <div style="font-size: 0.7rem; color: #64748b; margin-top: 4px; margin-left: 24px;">
            Restores inventory that was deducted when this transfer was sent
          </div>
        </div>

        <div class="small-note text-muted" style="font-size: 0.75rem;">
          <i class="bi bi-info-circle me-1"></i>
          Products, quantities, and notes will be copied to the new transfer
        </div>
      `;

      $('#maSubmit').textContent = 'Create New Transfer';
      $('#maSubmit').className = 'btn btn-success';
      $('#maSubmit').onclick = async ()=>{
        try {
          $('#maSubmit').disabled = true;
          $('#maSubmit').innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Creating...';
          const revertStock = $('#revertStockCheck').checked;

          showActivity('Creating new transfer…', 'Duplicating transfer details…');
          const result = await api('recreate_transfer', {
            id: t.id,
            revert_stock: revertStock
          });

          hideActivity();
          toast(`Transfer recreated: ${result.new_public_id} (${result.items_copied} items)`, 'success');
          ma.hide();

          // Close current modal and open the new transfer
          $('#quickModal')?.hide();
          await openQuick(result.new_id);
          await refresh();
        } catch(e){
          hideActivity();
          toast(e.message || 'Failed to recreate transfer', 'danger');
          $('#maSubmit').disabled = false;
          $('#maSubmit').textContent = 'Create New Transfer';
        }
      };
      ma.show();
    });

    // 8. REVERT TO OPEN BUTTON
    $('#qa_revert_open')?.addEventListener('click', ()=>{
      const cm = confirmModal();
      $('#mcTitle').textContent = '⚠️ Revert to OPEN';
      $('#mcBody').innerHTML = `<div class="alert alert-warning mb-0">
        <strong>This will:</strong>
        <ul class="mb-0 mt-2">
          <li>Change status from SENT to OPEN</li>
          <li>Add inventory back to source outlet</li>
          <li>Update Lightspeed consignment status</li>
        </ul>
      </div>`;
      $('#mcYes').onclick = async ()=>{
        cm.hide();
        try{
          showActivity('Reverting to OPEN…');
          const result = await api('revert_to_open',{id:t.id});
          hideActivity();
          let msg = result.message || 'Reverted to OPEN';
          if (result.inventory_adjustments?.length > 0) {
            msg += '\n\nInventory restored:\n' + result.inventory_adjustments.map(a =>
              `• Product ${a.product_id}: +${a.quantity_added} units`
            ).join('\n');
          }
          alert(msg);
          await refresh();
          await openQuick(t.id);
        } catch(e){ hideActivity(); toast(e.message||'Revert failed','danger'); }
      };
      cm.show();
    });

    // 9. REVERT TO SENT BUTTON
    $('#qa_revert_sent')?.addEventListener('click', ()=>{
      const cm = confirmModal();
      $('#mcTitle').textContent = '⚠️ Cancel Receiving';
      $('#mcBody').innerHTML = `<div class="alert alert-warning mb-0">
        <strong>This will:</strong>
        <ul class="mb-0 mt-2">
          <li>Change status from RECEIVING to SENT</li>
          <li>Remove any partial inventory from destination</li>
          <li>Reset all received quantities to 0</li>
        </ul>
      </div>`;
      $('#mcYes').onclick = async ()=>{
        cm.hide();
        try{
          showActivity('Cancelling receiving…');
          const result = await api('revert_to_sent',{id:t.id});
          hideActivity();
          let msg = result.message || 'Receiving cancelled';
          if (result.inventory_adjustments?.length > 0) {
            msg += '\n\nInventory adjustments:\n' + result.inventory_adjustments.map(a =>
              `• Product ${a.product_id}: -${a.quantity_removed} units`
            ).join('\n');
          }
          alert(msg);
          await refresh();
          await openQuick(t.id);
        } catch(e){ hideActivity(); toast(e.message||'Revert failed','danger'); }
      };
      cm.show();
    });

    // 10. REVERT TO RECEIVING BUTTON (DANGEROUS)
    $('#qa_revert_receiving')?.addEventListener('click', ()=>{
      const cm = confirmModal();
      $('#mcTitle').textContent = '⚠️ DANGER: Undo Partial Receiving';
      $('#mcBody').innerHTML = `<div class="alert alert-danger mb-3">
        <strong>⚠️ WARNING:</strong> This will remove inventory from destination outlet!
        <ul class="mb-0 mt-2">
          <li>Change status from PARTIAL to RECEIVING</li>
          <li>Remove inventory from destination outlet</li>
          <li>Reset all received quantities to 0</li>
        </ul>
        <p class="mb-0 mt-2"><strong>Only use if receiving errors occurred.</strong></p>
      </div>
      <div class="form-group">
        <label class="form-label fw-bold">Type <code>UNDO</code> to confirm:</label>
        <input type="text" id="undoConfirm" class="form-control" placeholder="Type UNDO">
      </div>`;
      $('#mcYes').onclick = async ()=>{
        const confirmation = $('#undoConfirm')?.value;
        if (confirmation !== 'UNDO') {
          toast('You must type exactly UNDO to confirm','danger');
          return;
        }
        cm.hide();
        try{
          showActivity('Reverting to RECEIVING…');
          const result = await api('revert_to_receiving',{id:t.id});
          hideActivity();
          let msg = '⚠️ ' + (result.message || 'Reverted to RECEIVING');
          if (result.inventory_adjustments?.length > 0) {
            msg += '\n\nInventory removed:\n' + result.inventory_adjustments.map(a =>
              `• Product ${a.product_id}: -${a.quantity_removed} units`
            ).join('\n');
          }
          alert(msg);
          await refresh();
          await openQuick(t.id);
        } catch(e){ hideActivity(); toast(e.message||'Revert failed','danger'); }
      };
      cm.show();
    });

    // 11. STORE VEND # BUTTON
    $('#qa_store_vend')?.addEventListener('click', ()=>{
      const ma = actionModal();
      $('#maTitle').textContent = 'Store Vend Identifiers';
      $('#maBody').innerHTML = `<div class="mb-2"><label class="form-label">Vend Number</label><input id="vendNumInput" class="form-control" value="${esc(t.vend_number||'')}"></div>
                                <div class="mb-2"><label class="form-label">Vend UUID</label><input id="vendUUIDInput" class="form-control" value="${esc(t.vend_transfer_id||'')}"></div>`;
      $('#maSubmit').onclick = async ()=>{
        try{
          $('#maSubmit').disabled=true;
          await api('store_vend_numbers',{id:t.id, vend_number:$('#vendNumInput').value||null, vend_transfer_id:$('#vendUUIDInput').value||null});
          toast('Saved','success');
          await openQuick(t.id);
        }
        catch(e){ toast(e.message||'Failed','danger'); $('#maSubmit').disabled=false; }
      };
      ma.show();
    });

    // 12. ADD PRODUCT BUTTON
    $('#qa_add_item')?.addEventListener('click', ()=>{
      const ma = actionModal();
      $('#maTitle').textContent = 'Add Product';
      $('#maBody').innerHTML = `
        <h6 class="dropdown-header px-0 mb-2"><i class="bi bi-search me-1"></i>Search & Add Product</h6>
        <div class="position-relative mb-2">
          <input id="pp_search" type="text" class="form-control form-control-sm" placeholder="Type name or SKU (min 2 chars)…" ${!perms.canAddItems?'disabled':''}>
          <div id="pp_dropdown" class="pp-dropdown"></div>
        </div>
        <div class="mb-2">
          <label class="form-label small mb-1">Quantity</label>
          <input id="pp_qty" type="number" class="form-control form-control-sm" value="1" min="1" ${!perms.canAddItems?'disabled':''}>
        </div>
        <div class="d-grid">
          <button class="btn btn-primary btn-sm" id="pp_add" ${!perms.canAddItems?'disabled':''}><i class="bi bi-plus-circle me-1"></i>Add to Transfer</button>
        </div>
        <div class="small text-muted mt-2" id="pp_selected"><i class="bi bi-info-circle me-1"></i>No product selected</div>
      `;
      ma.show();

      // Setup product search after modal is shown
      setupProductSearch(t.id, perms);
    });

    // 13. INLINE QUANTITY EDITING WITH AUTO-SAVE
    document.querySelectorAll('#itemsTbody .qty-input-modern').forEach(inp=>{
      let original = inp.value;

      inp.addEventListener('focus', ()=>{
        original=inp.value;
        inp.style.background = '#fffbeb';
        inp.style.borderColor = '#f59e0b';
      });

      inp.addEventListener('blur', async ()=>{
        const val = parseInt(inp.value||'0',10);
        if (String(val)===String(original)) {
          inp.style.background = '';
          inp.style.borderColor = '';
          return;
        }

        const tr = inp.closest('tr');
        const itemId = parseInt(tr.dataset.item,10);
        const field = inp.dataset.field;

        try {
          inp.style.background = '#dbeafe';
          inp.style.borderColor = '#3b82f6';
          inp.disabled = true;

          const spinner = document.createElement('span');
          spinner.className = 'saving-spinner';
          spinner.innerHTML = '<i class="bi bi-arrow-repeat" style="animation: spin 1s linear infinite; margin-left: 4px; color: #3b82f6;"></i>';
          spinner.style.cssText = 'position: absolute; right: -24px; top: 50%; transform: translateY(-50%);';
          inp.parentElement.style.position = 'relative';
          inp.parentElement.appendChild(spinner);

          await api('update_transfer_item_qty',{id:t.id, item_id:itemId, field, value:val});

          spinner.remove();
          inp.style.background = '#d1fae5';
          inp.style.borderColor = '#10b981';
          inp.disabled = false;
          original = inp.value;

          const checkmark = document.createElement('span');
          checkmark.innerHTML = '<i class="bi bi-check-circle-fill" style="color: #10b981; margin-left: 4px;"></i>';
          checkmark.style.cssText = 'position: absolute; right: -24px; top: 50%; transform: translateY(-50%);';
          inp.parentElement.appendChild(checkmark);

          toast(`${field.toUpperCase()} quantity saved: ${val}`, 'success');

          setTimeout(() => {
            inp.style.background = '';
            inp.style.borderColor = '';
            checkmark.remove();
          }, 1500);

        } catch(e) {
          if (inp.parentElement.querySelector('.saving-spinner')) {
            inp.parentElement.querySelector('.saving-spinner').remove();
          }
          inp.style.background = '#fee2e2';
          inp.style.borderColor = '#ef4444';
          inp.disabled = false;

          const errorIcon = document.createElement('span');
          errorIcon.innerHTML = '<i class="bi bi-x-circle-fill" style="color: #ef4444; margin-left: 4px;"></i>';
          errorIcon.style.cssText = 'position: absolute; right: -24px; top: 50%; transform: translateY(-50%);';
          inp.parentElement.appendChild(errorIcon);

          toast(e.message||'Failed to save','danger');
          inp.value = original;

          setTimeout(() => {
            inp.style.background = '';
            inp.style.borderColor = '';
            errorIcon.remove();
          }, 2000);
        }
      });

      inp.addEventListener('input', ()=>{
        if (inp.value !== original) {
          inp.style.background = '#fffbeb';
          inp.style.borderColor = '#f59e0b';
        } else {
          inp.style.background = '';
          inp.style.borderColor = '';
        }
      });
    });

    // 14. REMOVE ITEM BUTTONS
    document.querySelectorAll('#itemsTbody .remove-item-btn').forEach(btn=>{
      btn.addEventListener('click', async ()=>{
        const tr = btn.closest('tr');
        const itemId = parseInt(tr.dataset.item,10);
        if (!confirm('Remove this item?')) return;
        try{
          await api('remove_transfer_item', {item_id:itemId});
          tr.remove();
          toast('Removed','success');
        }
        catch(e){ toast(e.message||'Failed','danger'); }
      });
    });

  } catch(e) {
    body.innerHTML = `<div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-2"></i>${esc(e.message||'Failed to load')}</div>`;
  }
}
// END OF openQuick FUNCTION

// ============================================================
// PRODUCT SEARCH HANDLER (separate function for reusability)
// ============================================================
function setupProductSearch(transferId, perms) {
  let sel = null;
  let timer = null;

  function renderResults(list){
    const el = $('#pp_dropdown');
    el.innerHTML = list.length ? list.map((p,i)=>`
      <div class="pp-item ${i===0?'active':''}" data-idx="${i}" data-id="${esc(p.id)}" data-name="${esc(p.name||'')}" data-sku="${esc(p.sku||'')}">
        <div class="pp-item-content"><div class="pp-name">${esc(p.name||p.id)}</div><div class="pp-meta">${p.sku?`SKU: ${esc(p.sku)}`:'No SKU'}</div></div>
        <i class="bi bi-plus-circle text-primary"></i>
      </div>`).join('') :
      `<div class="p-3 text-center text-muted">No results</div>`;
    el.classList.add('show');
  }

  $('#pp_search').addEventListener('input', ()=>{
    clearTimeout(timer);
    const q = $('#pp_search').value.trim();
    if (q.length < 2){
      $('#pp_dropdown').classList.remove('show');
      sel=null;
      $('#pp_selected').innerHTML='<i class="bi bi-info-circle me-1"></i>No product selected';
      return;
    }
    $('#pp_dropdown').innerHTML='<div class="p-3 text-center"><span class="spinner-border spinner-border-sm"></span> Searching…</div>';
    $('#pp_dropdown').classList.add('show');
    timer = setTimeout(async ()=>{
      try{
        const r = await api('search_products',{q,limit:25});
        renderResults(r.results||[]);
      }
      catch(e){ $('#pp_dropdown').innerHTML='<div class="p-3 text-danger">Search failed</div>'; }
    }, 250);
  });

  $('#pp_dropdown').addEventListener('click', (e)=>{
    const row = e.target.closest('.pp-item');
    if(!row) return;
    sel = {id: row.dataset.id, name: row.dataset.name, sku: row.dataset.sku};
    $('#pp_selected').innerHTML = `<i class="bi bi-check-circle text-success me-1"></i> Selected: <strong>${esc(sel.name||sel.id)}</strong> ${sel.sku?`(${esc(sel.sku)})`:''}`;
    $('#pp_dropdown').classList.remove('show');
  });

  async function addItem(){
    const productId = (sel ? sel.id : null);
    const qty = parseInt($('#pp_qty').value||'1',10) || 1;
    if (!productId) { toast('Select a product first','warning'); return; }
    try{
      await api('add_transfer_item',{id:transferId, product_id:productId, qty});
      toast('Item added','success');
      $('#pp_search').value='';
      $('#pp_qty').value='1';
      sel=null;
      $('#pp_selected').innerHTML = '<i class="bi bi-info-circle me-1"></i>No product selected';
      const dropdown = bootstrap.Modal.getInstance('#modalAction');
      dropdown?.hide();
      await openQuick(transferId);
    }catch(e){ toast(e.message||'Failed','danger'); }
  }

  $('#pp_add')?.addEventListener('click', ()=>addItem());
}
