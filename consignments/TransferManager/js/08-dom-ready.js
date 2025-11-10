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
      // Attach simple manager PIN controls if manager/admin
      if (window.USER_ROLE === 'manager' || window.USER_ROLE === 'admin') {
        const hdr = document.querySelector('.d-flex.align-items-center.justify-content-between.mb-3, .d-flex.justify-content-between.align-items-center');
        if (hdr) {
          const wrap = document.createElement('div');
          wrap.className = 'ms-3';
          wrap.innerHTML = '<div class="btn-group btn-group-sm" role="group" aria-label="PIN">'
            +'<button id="tmPinIssue" class="btn btn-outline-secondary" title="Issue override PIN">PIN Issue</button>'
            +'<button id="tmPinStatus" class="btn btn-outline-secondary" title="View PIN status">PIN Status</button>'
            +'<button id="tmPinRevoke" class="btn btn-outline-danger" title="Revoke PIN">Revoke</button>'
            +'</div>';
          hdr.appendChild(wrap);
          const bind = (id,fn)=>{ const el=document.getElementById(id); if (el) el.addEventListener('click', fn); };
          bind('tmPinIssue', async ()=>{ try{ const r=await window.tmPin.issue(900); alert('PIN: '+r.pin+'\nExpires: '+new Date(r.expires_at*1000).toLocaleString()); }catch(e){ alert('Issue failed: '+e.message); } });
          bind('tmPinStatus', async ()=>{ try{ const r=await window.tmPin.status(); alert(r.active?('Active, expires: '+new Date(r.expires_at*1000).toLocaleString()):'No active PIN'); }catch(e){ alert('Status failed: '+e.message); } });
          bind('tmPinRevoke', async ()=>{ try{ await window.tmPin.revoke(); alert('PIN revoked'); }catch(e){ alert('Revoke failed: '+e.message); } });
        }
      }
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
