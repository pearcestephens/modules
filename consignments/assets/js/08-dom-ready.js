/**
 * 08-dom-ready.js
 * DOM Ready handler and application bootstrap
 * This file is loaded LAST after all other modules
 */

(function() {
  'use strict';
  
  // ✅ Track if we've already initialized
  let domReadyFired = false;
  
  /**
   * Bootstrap the application
   * Called when DOM is ready
   */
  function bootstrap() {
    // Prevent double initialization
    if (domReadyFired) {
      console.warn('⚠️ DOM ready handler called multiple times, ignoring...');
      return;
    }
    domReadyFired = true;
    
    console.log('🚀 DOM Ready - Starting application...');
    
    // ✅ Validate dependencies
    if (typeof bootstrap === 'undefined') {
      console.error('❌ Bootstrap library not loaded!');
      alert('Critical Error: Bootstrap library missing. Please reload the page.');
      return;
    }
    
    if (typeof initApp !== 'function') {
      console.error('❌ initApp() function not found!');
      alert('Critical Error: Application initialization failed. Check console for details.');
      return;
    }
    
    // ✅ Initialize the application
    try {
      initApp();
      console.log('✅ Application started successfully');
    } catch (error) {
      console.error('❌ Application initialization failed:', error);
      alert('Application Error: ' + error.message + '. Check console for details.');
    }
  }
  
  // ✅ Multiple DOM ready strategies for maximum compatibility
  
  // Strategy 1: If DOM is already loaded
  if (document.readyState === 'complete' || document.readyState === 'interactive') {
    // DOM is already ready, execute immediately
    setTimeout(bootstrap, 1);
  } 
  // Strategy 2: Standard DOMContentLoaded
  else if (document.addEventListener) {
    document.addEventListener('DOMContentLoaded', bootstrap);
  } 
  // Strategy 3: Legacy IE support (just in case)
  else if (document.attachEvent) {
    document.attachEvent('onreadystatechange', function() {
      if (document.readyState === 'complete') {
        bootstrap();
      }
    });
  }
  
  // Strategy 4: Fallback - window.onload (waits for all resources)
  window.addEventListener('load', function() {
    if (!domReadyFired) {
      console.warn('⚠️ DOM ready event missed, using window.onload fallback');
      bootstrap();
    }
  });
  
  console.log('✅ DOM ready handler registered');
  
})();
