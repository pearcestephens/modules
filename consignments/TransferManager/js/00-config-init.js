/**
 * 00-config-init.js
 * Configuration initialization and validation
 * This file is loaded FIRST before all other modules
 * 
 * Note: window.APP_CONFIG is injected by config.php with PHP-generated values
 */

(function() {
  'use strict';
  
  // ✅ Validate that configuration was injected
  if (typeof window.APP_CONFIG === 'undefined') {
    console.error('❌ CRITICAL: APP_CONFIG not found! Configuration must be loaded before this script.');
    alert('Configuration Error: Application cannot start. Please refresh the page.');
    throw new Error('APP_CONFIG not initialized');
  }
  
  // ✅ Extract config to global constants for backward compatibility
  const config = window.APP_CONFIG;
  
  // Validate required fields
  const required = ['CSRF', 'LS_CONSIGNMENT_BASE', 'OUTLET_MAP', 'SUPPLIER_MAP', 'SYNC_ENABLED'];
  const missing = required.filter(key => !(key in config));
  
  if (missing.length > 0) {
    console.error('❌ Missing required configuration keys:', missing);
    alert('Configuration Error: Missing required settings. Please refresh the page.');
    throw new Error('Incomplete APP_CONFIG: ' + missing.join(', '));
  }
  
  // ✅ Make config globally accessible as constants
  window.CSRF = config.CSRF;
  window.LS_CONSIGNMENT_BASE = config.LS_CONSIGNMENT_BASE;
  window.OUTLET_MAP = config.OUTLET_MAP;
  window.SUPPLIER_MAP = config.SUPPLIER_MAP;
  window.SYNC_ENABLED = config.SYNC_ENABLED;
  
  console.log('✅ Configuration loaded successfully:', {
    CSRF: window.CSRF ? '(present)' : '(missing)',
    LS_BASE: window.LS_CONSIGNMENT_BASE ? '(present)' : '(missing)',
    Outlets: Object.keys(window.OUTLET_MAP || {}).length + ' outlets',
    Suppliers: Object.keys(window.SUPPLIER_MAP || {}).length + ' suppliers',
    SyncEnabled: window.SYNC_ENABLED
  });
  
  // ✅ Freeze config to prevent accidental modification
  Object.freeze(window.APP_CONFIG);
  
})();
