/**
 * Pack Pro JS v3 - WITH BARCODE SCANNER INTEGRATION
 * Complete integration example showing how to use CISBarcodeScanner
 */

// Include the barcode scanner library
// <script src="js/barcode-scanner.js"></script>

(function () {
  'use strict';

  const BOOT = window.PACKPRO_BOOT || {};
  const TID  = BOOT.transferId;
  const OUTLET_FROM = BOOT.outletFrom;
  const USER_ID = BOOT.userId;
  const CSRF = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

  const $ = (s, r=document)=>r.querySelector(s);
  const $$ = (s, r=document)=>Array.from(r.querySelectorAll(s));

  // ============================================================================
  // BARCODE SCANNER SETUP
  // ============================================================================

  let barcodeScanner = null;

  /**
   * Initialize barcode scanner
   */
  function initBarcodeScanner() {
    barcodeScanner = new CISBarcodeScanner({
      transferId: TID,
      userId: USER_ID,
      outletId: OUTLET_FROM,
      container: '#barcodeCameraPreview', // For camera scanning

      // Called when barcode is scanned
      onScan: async (barcode, method, format) => {
        console.log(`[Pack] Barcode scanned: ${barcode} via ${method}`);

        // Find matching product in table
        const row = findProductByBarcode(barcode);

        if (row) {
          // Product found - increment count
          const input = row.querySelector('.counted');
          const currentQty = parseInt(input.value || '0', 10);
          const plannedQty = parseInt(input.dataset.planned || '0', 10);
          const newQty = currentQty + 1;

          // Check if exceeding planned qty
          if (barcodeScanner.config.block_on_qty_exceed && newQty > plannedQty) {
            showToast('Cannot exceed planned quantity', 'warning');
            return {
              success: false,
              warning: true,
              reason: 'quantity_exceeded',
              element: row
            };
          }

          // Update quantity
          input.value = newQty;
          recalcRow(row);
          recalcAll();
          markDirty();

          // Show success toast
          const productName = row.querySelector('.prod')?.textContent || 'Product';
          showToast(`✓ ${productName} (${newQty}/${plannedQty})`, 'success');

          return {
            success: true,
            productId: row.dataset.productId,
            sku: row.querySelector('.sku-mono')?.textContent,
            productName: productName,
            qty: 1,
            element: row
          };

        } else {
          // Product not found in transfer
          showToast(`Product not found: ${barcode}`, 'error');

          // Optionally, search and add product
          if (confirm(`Product ${barcode} not in transfer. Search and add it?`)) {
            $('#productSearch').value = barcode;
            $('#productSearch').focus();
          }

          return {
            success: false,
            reason: 'not_found'
          };
        }
      },

      // Called on scanner errors
      onError: (error) => {
        console.error('[Pack] Scanner error:', error);
        showToast('Scanner error: ' + error.message, 'error');
      }
    });

    console.log('[Pack] Barcode scanner initialized');
  }

  /**
   * Find product row by barcode or SKU
   */
  function findProductByBarcode(barcode) {
    return $$('#transferTable tbody tr').find(tr => {
      const sku = tr.querySelector('.sku-mono')?.textContent?.trim();
      const productBarcode = tr.dataset.barcode; // If you store barcode in data attribute

      return sku === barcode ||
             productBarcode === barcode ||
             sku?.replace(/[^0-9]/g, '') === barcode.replace(/[^0-9]/g, ''); // Fuzzy match
    });
  }

  /**
   * Show toast notification
   */
  function showToast(message, type = 'info') {
    const colors = {
      success: '#28a745',
      warning: '#ffc107',
      error: '#dc3545',
      info: '#17a2b8'
    };

    const toast = document.createElement('div');
    toast.style.cssText = `
      position: fixed;
      top: 20px;
      right: 20px;
      background: ${colors[type] || colors.info};
      color: white;
      padding: 12px 20px;
      border-radius: 6px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.2);
      z-index: 10000;
      font-size: 14px;
      font-weight: 500;
      animation: slideIn 0.3s ease;
    `;
    toast.textContent = message;
    document.body.appendChild(toast);

    setTimeout(() => {
      toast.style.animation = 'slideOut 0.3s ease';
      setTimeout(() => toast.remove(), 300);
    }, 3000);
  }

  // Add animations
  const style = document.createElement('style');
  style.textContent = `
    @keyframes slideIn {
      from { transform: translateX(400px); opacity: 0; }
      to { transform: translateX(0); opacity: 1; }
    }
    @keyframes slideOut {
      from { transform: translateX(0); opacity: 1; }
      to { transform: translateX(400px); opacity: 0; }
    }
  `;
  document.head.appendChild(style);

  // ============================================================================
  // SCANNER CONTROLS
  // ============================================================================

  /**
   * Toggle camera scanning
   */
  $('#toggleCamera')?.addEventListener('click', () => {
    if (barcodeScanner) {
      barcodeScanner.toggleCamera();
      const btn = $('#toggleCamera');
      btn.textContent = barcodeScanner.cameraActive ? 'Stop Camera' : 'Start Camera';
    }
  });

  /**
   * Manual barcode entry
   */
  $('#manualBarcodeInput')?.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') {
      e.preventDefault();
      const barcode = e.target.value.trim();
      if (barcode && barcodeScanner) {
        barcodeScanner.manualEntry(barcode);
        e.target.value = '';
      }
    }
  });

  /**
   * Show scanner stats
   */
  $('#showScanStats')?.addEventListener('click', () => {
    if (barcodeScanner) {
      const stats = barcodeScanner.getStats();
      alert(`Scanner Stats:\n\nTotal Scans: ${stats.total}\nSuccessful: ${stats.successful}\nFailed: ${stats.failed}\nSuccess Rate: ${stats.successRate}%`);
    }
  });

  // ============================================================================
  // EXISTING PACK.JS CODE (KPIs, Search, Freight, etc.)
  // ============================================================================

  function recalcRow(tr){
    const inp=$('.counted',tr);
    const planned=parseInt(inp?.dataset.planned||'0',10)||0;
    const counted=parseInt(inp?.value||'0',10)||0;
    tr.classList.remove('row-ok','row-under','row-over','row-zero');
    tr.classList.add(counted===0?'row-zero':(counted===planned?'row-ok':(counted<planned?'row-under':'row-over')));
    return {planned,counted};
  }

  function recalcAll(){
    let planned=0, counted=0;
    $$('#transferTable tbody tr').forEach(tr=>{ const s=recalcRow(tr); planned+=s.planned; counted+=s.counted; });
    const pct = Math.min(100, Math.round(((planned>0?counted:0)*100)/(planned||1)));
    $('#kpiPct').textContent = pct;
    const bar=$('.progress-bar'); if(bar){bar.style.width=pct+'%'; bar.setAttribute('aria-valuenow',String(pct));}
  }

  let dirty=false;
  const markDirty=()=>{ dirty=true; };

  document.addEventListener('input',e=>{
    if(e.target.classList?.contains('counted')){
      recalcRow(e.target.closest('tr'));
      recalcAll();
      markDirty();
    }
  });

  // ============================================================================
  // INITIALIZE EVERYTHING
  // ============================================================================

  document.addEventListener('DOMContentLoaded', () => {
    // Initialize barcode scanner
    initBarcodeScanner();

    // Initial calculations
    recalcAll();

    console.log('[Pack] Page initialized with barcode scanner support');
  });

  // Cleanup on page unload
  window.addEventListener('beforeunload', () => {
    if (barcodeScanner) {
      barcodeScanner.destroy();
    }
  });

})();
