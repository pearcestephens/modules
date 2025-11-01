/**
 * 02-ui-components.js
 * UI components like toasts, activity indicators, and basic formatting
 */

/* ===== Toasts + activity ===== */
// ✅ CRITICAL FIX: Add cleanup listener and null check
function toast(msg, type='info', ms=3500){
  const container = $('#toastContainer');
  if (!container) {
    console.warn('⚠️ #toastContainer not found');
    return null;
  }
  
  // Validate toast type
  const validTypes = ['info', 'success', 'warning', 'danger'];
  if (!validTypes.includes(type)) {
    console.warn(`⚠️ Invalid toast type "${type}", using "info"`);
    type = 'info';
  }
  
  const id='t'+Math.random().toString(36).slice(2);
  container.insertAdjacentHTML('beforeend',
    `<div id="${id}" class="toast" role="status" data-bs-delay="${ms}">
      <div class="toast-header"><strong class="me-auto text-${type}">${type.toUpperCase()}</strong><small>now</small>
      <button type="button" class="btn-close" data-bs-dismiss="toast"></button></div>
      <div class="toast-body">${esc(msg)}</div></div>`);
  
  const toastEl = document.getElementById(id);
  if (!toastEl) return null;
  
  try {
    const t = new bootstrap.Toast(toastEl);
    
    // ✅ Remove from DOM after hidden to prevent memory leak
    toastEl.addEventListener('hidden.bs.toast', () => {
      toastEl.remove();
    });
    
    t.show();
    return t;
  } catch (err) {
    console.error('❌ Failed to create toast:', err);
    toastEl.remove(); // Clean up DOM on error
    return null;
  }
}

// ✅ FIX: Add auto-hide timeout failsafe
let activityTimeout = null;

function showActivity(title='Working…', sub='Please wait', maxMs=30000){ 
  const ga = $('#globalActivity');
  if (ga) {
    $('#gaTitle').textContent=title; 
    $('#gaSub').textContent=sub; 
    ga.style.display='grid';
    
    // ✅ Auto-hide after 30 seconds as failsafe
    clearTimeout(activityTimeout);
    activityTimeout = setTimeout(() => {
      console.warn('⚠️ Activity overlay auto-hidden after 30s (failsafe)');
      hideActivity();
    }, maxMs);
  }
}

function hideActivity(){ 
  clearTimeout(activityTimeout);
  const ga = $('#globalActivity');
  if (ga) ga.style.display='none';
}

/* ===== List paging ===== */
// ✅ CRITICAL FIX: Add validation to prevent invalid page values
let page = 1, perPage = 25;
function setPage(val) {
  page = Math.max(1, parseInt(val) || 1);
}
function setPerPage(val) {
  perPage = Math.max(1, Math.min(100, parseInt(val) || 25));
}

// ✅ FIX: Better date formatting with invalid date handling
function dt(s) {
  if (!s) return '—';
  
  // Support multiple date formats
  let dateStr = s;
  if (typeof s === 'string' && s.includes(' ') && !s.includes('T')) {
    dateStr = s.replace(' ', 'T'); // MySQL format: "YYYY-MM-DD HH:MM:SS"
  }
  
  const d = new Date(dateStr);
  
  // Check if date is valid
  if (isNaN(d.getTime())) {
    console.warn('Invalid date format:', s);
    return '—';
  }
  
  return d.toLocaleString();
}

function pill(state){ return `<span class="state-pill state-${esc(state)}">${esc(state)}</span>`; }
function outletLabel(id, apiName, label, supplierName){ return esc(label || apiName || supplierName || OUTLET_MAP[id] || SUPPLIER_MAP[id] || id || ''); }