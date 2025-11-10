/**
 * 07-init.js
 * Exposes initApp and any small boot helpers
 */

// ✅ FIX: Prevent duplicate initialization
let appInitialized = false;

function initApp() {
  if (appInitialized) {
    console.warn('⚠️ initApp() already called, skipping...');
    return;
  }
  appInitialized = true;

  console.log('🚀 Transfers Tool - Initializing...');

  // ✅ CRITICAL FIX: Ensure DOM is ready
  if (document.readyState === 'loading') {
    console.warn('⚠️ initApp() called before DOM ready. Waiting...');
    document.addEventListener('DOMContentLoaded', initApp);
    return;
  }

  // ✅ CRITICAL FIX: Validate Bootstrap dependency (support BS4 via jQuery OR BS5 global)
  const hasBS5 = typeof window.bootstrap !== 'undefined' && typeof window.bootstrap.Modal !== 'undefined';
  const hasBS4 = typeof window.jQuery !== 'undefined' && window.jQuery.fn && typeof window.jQuery.fn.modal === 'function';
  if (!(hasBS4 || hasBS5)) {
    console.error('❌ Bootstrap runtime not available (neither BS4 nor BS5 detected)');
    alert('Critical Error: UI runtime missing. Please reload the page.');
    return;
  }

  // ✅ CRITICAL FIX: Validate config variables
  if (typeof CSRF === 'undefined' || typeof LS_CONSIGNMENT_BASE === 'undefined') {
    console.error('❌ Required configuration variables not found!');
    alert('Critical Error: Configuration missing. Please reload the page.');
    return;
  }

  console.log('✅ Configuration loaded:', {
    CSRF: CSRF ? '(set)' : '(missing)',
  Bootstrap: hasBS5 ? 'bs5' : (hasBS4 ? 'bs4' : 'missing'),
    DOMState: document.readyState
  });

  // Wire listeners and load initial data
  setupEventListeners();
  refresh();

  console.log('✅ Transfers Tool initialized successfully');
}

// Export globally
window.initApp = initApp;
