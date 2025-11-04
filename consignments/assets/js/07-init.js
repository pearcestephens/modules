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
  
  // ✅ CRITICAL FIX: Validate Bootstrap dependency
  if (typeof bootstrap === 'undefined') {
    console.error('❌ Bootstrap library not loaded!');
    alert('Critical Error: Bootstrap library missing. Please reload the page.');
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
    Bootstrap: typeof bootstrap !== 'undefined' ? 'loaded' : 'MISSING',
    DOMState: document.readyState
  });

  // Wire listeners and load initial data
  setupEventListeners();
  refresh();

  console.log('✅ Transfers Tool initialized successfully');
}

// Export globally
window.initApp = initApp;
