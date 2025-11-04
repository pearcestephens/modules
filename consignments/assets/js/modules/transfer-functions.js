/**
 * 03-transfer-functions.js
 * Functions related to transfer display and formatting
 */

function smartTransferID(transferID, category, vendNumber) {
  if (!transferID) return 'N/A';
  
  let prefix = '';
  switch(category) {
    case 'JUICE':
      prefix = 'JU-';
      break;
    case 'PURCHASE_ORDER':
      prefix = 'PO-';
      break;
    case 'STOCK':
      prefix = 'ST-';
      break;
    case 'STAFF':
      prefix = 'IN-';
      break;
    default:
      prefix = 'ST-';
  }
  
  // Format: PO-123456 or IN-123456-1 (if vend number exists)
  if (category === 'STAFF' && vendNumber) {
    return `${prefix}${transferID}-${vendNumber}`;
  }
  
  return `${prefix}${transferID}`;
}

function rowHtml(r){
  const fromName = outletLabel(r.outlet_from, r.outlet_from_name, r.outlet_from_label, r.supplier_from_name);
  const toName   = outletLabel(r.outlet_to, r.outlet_to_name, r.outlet_to_label);
  
  // Category display with icon
  const catIcon = r.transfer_category === 'PURCHASE_ORDER' ? 'cart-check' : 
                  r.transfer_category === 'STOCK' ? 'arrow-left-right' : 
                  r.transfer_category === 'JUICE' ? 'droplet-fill' : 
                  r.transfer_category === 'STAFF' ? 'person-badge' : 'box';
  const catDisplay = r.transfer_category === 'PURCHASE_ORDER' ? 'Purchase Order' : r.transfer_category;
  
  // Smart Transfer ID
  const displayTransferID = smartTransferID(r.id, r.transfer_category, r.vend_number);
  
  // 🏆 TOURNAMENT CHAMPION: Calculate urgency based on last update
  let urgencyClass = '';
  if (r.updated_at && r.status !== 'received' && r.status !== 'cancelled') {
    const daysSinceUpdate = Math.floor((Date.now() - new Date(r.updated_at.replace(' ', 'T')).getTime()) / (1000 * 60 * 60 * 24));
    if (daysSinceUpdate > 3) {
      urgencyClass = 'urgent';
    } else if (daysSinceUpdate > 1) {
      urgencyClass = 'warning';
    }
  }
  
  // Vend link styling
  const vendLink = r.vend_transfer_id ? 
    `<a target="_blank" rel="noopener" class="btn btn-sm btn-success" href="${LS_CONSIGNMENT_BASE+encodeURIComponent(r.vend_transfer_id)}">
      <i class="bi bi-box-arrow-up-right me-1"></i>View in Vend
    </a>` : 
    '<span class="badge bg-light text-muted border">Not Linked</span>';
  
  // Status with better icons
  const statusConfig = {
    'received': { label: 'Complete', icon: 'check-circle-fill', color: 'success' },
    'sent': { label: 'In Transit', icon: 'truck', color: 'info' },
    'cancelled': { label: 'Cancelled', icon: 'x-circle-fill', color: 'warning' },
    'open': { label: 'In Progress', icon: 'hourglass-split', color: 'danger' }
  };
  const sc = statusConfig[r.status] || statusConfig['open'];
  
  // Create compact Vend button - clickable to show verification modal
  const compactVendBtn = r.vend_transfer_id ? 
    `<button class="btn btn-sm btn-vend-compact vend-active" data-transfer-id="${r.id}" data-vend-id="${esc(r.vend_transfer_id)}" onclick="window.showVendVerificationModal(${r.id}, '${esc(r.vend_transfer_id)}')" title="Verify sync & open in Lightspeed">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
        <circle cx="12" cy="12" r="10" fill="#86efac"/>
        <path d="M7 12L10 15L17 8" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
      </svg>
    </button>` : 
    `<span class="btn btn-sm btn-vend-compact vend-disabled" title="Not linked to Lightspeed">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
        <circle cx="12" cy="12" r="10" fill="#cbd5e1"/>
        <path d="M7 12L10 15L17 8" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" opacity="0.5"/>
      </svg>
    </span>`;
  
  return `<tr data-id="${r.id}" class="cursor">
    <td style="padding: 0.5rem 0.75rem;">
      <div class="d-flex align-items-center gap-2">
        <i class="bi bi-${catIcon} text-primary fs-5"></i>
        <div>
          <div class="fw-bold">${esc(catDisplay)}</div>
          <div class="small text-primary"><i class="bi bi-hash"></i>${esc(displayTransferID)}</div>
        </div>
      </div>
    </td>
    <td style="padding: 0.5rem 0.75rem;">
      <span class="fw-semibold text-dark">${fromName}</span>
    </td>
    <td style="padding: 0.5rem 0.75rem;">
      <span class="fw-semibold text-dark">${toName}</span>
    </td>
    <td style="padding: 0.5rem 0.75rem;">
      <div class="d-flex align-items-center gap-2">
        <i class="bi bi-${sc.icon} text-${sc.color}"></i>
        <span class="fw-semibold">${sc.label}</span>
      </div>
    </td>
    <td style="padding: 0.5rem 0.75rem;">${pill(r.state)}</td>
    <td class="text-center" style="padding: 0.5rem 0.75rem;">
      <span class="badge bg-secondary fs-6">${esc(r.total_boxes ?? 0)}</span>
      <div class="small-note">boxes</div>
    </td>
    <td style="padding: 0.5rem 0.75rem;"><small class="text-muted">${esc(dt(r.updated_at))}</small></td>
    <td class="text-end" style="padding: 0.5rem 0.75rem;">
      <div class="d-flex align-items-center justify-content-end gap-2">
        ${compactVendBtn}
        <button class="btn btn-primary btn-elevated act-open ${urgencyClass}">
          <i class="bi bi-box-arrow-up-right me-1"></i>Open
        </button>
      </div>
    </td>
  </tr>`;
}