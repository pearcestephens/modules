/**
 * 01-core-helpers.js
 * Core utility functions and UX helpers
 */

/* ===== UX Helpers ===== */
const $  = s => document.querySelector(s);
const $$ = s => Array.from(document.querySelectorAll(s));
const esc = s => (s ?? '').toString().replace(/[&<>"]/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[m]));

/* ===== API Helper ===== */
function backoffDelay(attempt) { 
  return Math.min(15000, 500 * Math.pow(2, attempt)); 
}

// ✅ CRITICAL FIX: Add max retry limit to prevent infinite loop
async function api(action, payload = {}) {
  const MAX_ATTEMPTS = 5;  // ✅ Prevent infinite retry loop
  const body = JSON.stringify(Object.assign({action, csrf: CSRF, sync: $('#syncToggle')?.checked}, payload));
  let attempt = 0;
  
  for (;;) {
    if (attempt >= MAX_ATTEMPTS) {
      const errorMsg = `Max retry attempts (${MAX_ATTEMPTS}) exceeded. Please try again later.`;
      toast(errorMsg, 'danger');
      throw new Error(errorMsg);
    }
    
    let resp, data;
    try {
      resp = await fetch('backend.php?api=1', {method:'POST', headers:{'Content-Type':'application/json'}, body});
      data = await resp.json().catch(()=> ({}));
    } catch(e) { 
      attempt++;
      if (attempt >= MAX_ATTEMPTS) {
        const errorMsg = 'Network error - unable to connect';
        toast(errorMsg, 'danger');
        throw new Error(errorMsg);
      }
      await new Promise(r=>setTimeout(r, backoffDelay(attempt)));
      continue;
    }
    
    if (resp.status === 429) { 
      attempt++;
      await new Promise(r=>setTimeout(r, backoffDelay(attempt))); 
      continue; 
    }
    
    // ✅ FIX: Handle all CSRF token expiry status codes (403, 419, 422)
    // Check if error message indicates CSRF issue
    const isCsrfError = data.error && (
      data.error.includes('CSRF') || 
      data.error === 'CSRF_INVALID' ||
      resp.status === 419
    );
    
    if ([403, 419, 422].includes(resp.status) && isCsrfError) {
      console.error('❌ CSRF token invalid (status ' + resp.status + '). Forcing page reload...');
      
      // Prevent infinite reload loop - only reload once per session
      if (!sessionStorage.getItem('csrf_reloading')) {
        sessionStorage.setItem('csrf_reloading', '1');
        toast('Session expired. Reloading page...', 'warning');
        setTimeout(() => window.location.reload(), 1500);
      } else {
        console.error('❌ CSRF reload loop detected - stopping');
        throw new Error('CSRF validation failed. Please clear cookies and reload manually.');
      }
      throw new Error('Session expired. Please wait...');
    }
    
    // Show detailed error modal for non-CSRF errors
    if (!resp.ok || !data.ok) {
      const errorMessage = data.error || data.detail || `HTTP ${resp.status}`;
      console.error('❌ API Error:', errorMessage, data);
      
      // Show detailed error modal if we have response data
      if (data.error || data.request || data.system) {
        showDetailedError(new Error(errorMessage), data);
      }
      
      throw new Error(errorMessage);
    }
    
    return data.data;
  }
}

/* ===== Enhanced Error Display with Copy Buttons ===== */
function showDetailedError(error, response) {
  // Create error modal if it doesn't exist
  let errorModal = document.getElementById('errorDetailModal');
  if (!errorModal) {
    errorModal = document.createElement('div');
    errorModal.id = 'errorDetailModal';
    errorModal.className = 'modal fade';
    errorModal.innerHTML = `
      <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
          <div class="modal-header bg-danger text-white">
            <h5 class="modal-title">
              <i class="bi bi-exclamation-triangle-fill me-2"></i>Error Details
            </h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div id="errorDetailsContent"></div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
          </div>
        </div>
      </div>
    `;
    document.body.appendChild(errorModal);
  }
  
  const content = document.getElementById('errorDetailsContent');
  if (!content) return;
  
  // Build error display with copy buttons
  let html = `
    <!-- Error Information -->
    <div class="mb-4">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <h6 class="text-danger mb-0"><i class="bi bi-bug-fill me-2"></i>Error Information</h6>
        <button class="btn btn-sm btn-outline-secondary" onclick="copyToClipboard('errorInfo', this)">
          <i class="bi bi-clipboard"></i> Copy
        </button>
      </div>
      <div class="card">
        <div class="card-body">
          <pre id="errorInfo" class="mb-0 small" style="max-height: 200px; overflow-y: auto;">${esc(JSON.stringify({
            error: response?.error || error.message,
            detail: response?.detail || error.stack?.split('\\n')[0],
            timestamp: response?.ts || new Date().toISOString()
          }, null, 2))}</pre>
        </div>
      </div>
    </div>
    
    <!-- Request Details -->
    <div class="mb-4">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <h6 class="text-primary mb-0"><i class="bi bi-send-fill me-2"></i>Request Details</h6>
        <button class="btn btn-sm btn-outline-secondary" onclick="copyToClipboard('requestInfo', this)">
          <i class="bi bi-clipboard"></i> Copy
        </button>
      </div>
      <div class="card">
        <div class="card-body">
          <pre id="requestInfo" class="mb-0 small" style="max-height: 200px; overflow-y: auto;">${response?.request ? esc(JSON.stringify(response.request, null, 2)) : 'Request details not available'}</pre>
        </div>
      </div>
    </div>
    
    <!-- System Stats -->
    <div class="mb-0">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <h6 class="text-secondary mb-0"><i class="bi bi-cpu-fill me-2"></i>System Stats</h6>
        <button class="btn btn-sm btn-outline-secondary" onclick="copyToClipboard('systemInfo', this)">
          <i class="bi bi-clipboard"></i> Copy
        </button>
      </div>
      <div class="card">
        <div class="card-body">
          <pre id="systemInfo" class="mb-0 small" style="max-height: 150px; overflow-y: auto;">${response?.system ? esc(JSON.stringify(response.system, null, 2)) : 'System stats not available'}</pre>
        </div>
      </div>
    </div>
  `;
  
  content.innerHTML = html;
  
  // Show modal
  const modal = new bootstrap.Modal(errorModal);
  modal.show();
}

// Copy to clipboard helper
window.copyToClipboard = function(elementId, button) {
  const element = document.getElementById(elementId);
  if (!element) return;
  
  const text = element.textContent;
  
  // Try modern clipboard API first
  if (navigator.clipboard && navigator.clipboard.writeText) {
    navigator.clipboard.writeText(text).then(() => {
      showCopyFeedback(button);
    }).catch(err => {
      // Fallback to old method
      fallbackCopy(text, button);
    });
  } else {
    fallbackCopy(text, button);
  }
};

function fallbackCopy(text, button) {
  const textarea = document.createElement('textarea');
  textarea.value = text;
  textarea.style.position = 'fixed';
  textarea.style.opacity = '0';
  document.body.appendChild(textarea);
  textarea.select();
  try {
    document.execCommand('copy');
    showCopyFeedback(button);
  } catch (err) {
    console.error('Failed to copy:', err);
    toast('Copy failed', 'danger');
  }
  document.body.removeChild(textarea);
}

function showCopyFeedback(button) {
  const originalHTML = button.innerHTML;
  button.innerHTML = '<i class="bi bi-check-lg"></i> Copied!';
  button.classList.remove('btn-outline-secondary');
  button.classList.add('btn-success');
  
  setTimeout(() => {
    button.innerHTML = originalHTML;
    button.classList.remove('btn-success');
    button.classList.add('btn-outline-secondary');
  }, 2000);
}