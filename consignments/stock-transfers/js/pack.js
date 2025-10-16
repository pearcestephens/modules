/**
 * Pack Transfer JavaScript - Clean Version
 * 
 * Core functionality for packing stock transfers:
 * - Real-time validation with visual feedback
 * - Enterprise AJAX auto-save with state indicators
 * - Product search and multi-select
 * - Transfer completion workflow
 * - Label printing support
 * 
 * Dependencies: jQuery, ConsignmentsAjax (ajax-manager.js)
 */

(function() {
  'use strict';

  // ============================================================================
  // CONFIGURATION & STATE
  // ============================================================================

  const AUTO_SAVE_DEBOUNCE = 5000; // 5 seconds - don't interrupt while typing numbers
  const SAVING_DURATION = 2000; // 2 seconds in SAVING state
  const SAVED_DURATION = 1500; // 1.5 seconds - quick flash then gone

  let autoSaveTimer = null;
  let autoSaveState = 'IDLE'; // IDLE | SAVING | SAVED | LOADING
  let selectedProducts = new Set();
  let inputDebounceTimer = null; // Prevent double input events
  let modalEventSource = null; // SSE for upload progress (single declaration)

  const $table = $('#transfer-table');
  const transferId = $table.data('transfer-id');

  // Log initialization for debugging
    // Initialize pack transfer functionality

  // ============================================================================
  // VALIDATION & AUTO-SAVE
  // ============================================================================

  /**
   * Block non-integer input (prevent decimals, letters, special chars)
   */
  function isNumberKey(evt) {
    const charCode = (evt.which) ? evt.which : evt.keyCode;
    // Allow: backspace, delete, tab, escape, enter
    if (charCode === 8 || charCode === 46 || charCode === 9 || charCode === 27 || charCode === 13) {
      return true;
    }
    // Allow: Ctrl+A, Ctrl+C, Ctrl+V, Ctrl+X
    if (charCode === 65 || charCode === 67 || charCode === 86 || charCode === 88) {
      return true;
    }
    // Block: decimals (46 = period, 44 = comma)
    if (charCode === 46 || charCode === 44) {
      return false;
    }
    // Allow: numbers 0-9 only
    if (charCode < 48 || charCode > 57) {
      return false;
    }
    return true;
  }

  /**
   * Detect unusual/suspicious numbers (fraud patterns)
   * Returns: { isSuspicious: boolean, reason: string }
   */
  function detectUnusualNumber(value, plannedQty, stockQty) {
    const num = parseInt(value) || 0;
    
    // Pattern 1: Repeating digits (111, 222, 333, 777, 999)
    if (num > 0 && /^(\d)\1+$/.test(value)) {
      return { isSuspicious: true, reason: 'Repeating digits (e.g., 111, 222, 999) - Please verify count' };
    }
    
    // Pattern 2: Sequential digits (123, 234, 456, 789)
    if (num >= 123) {
      const digits = value.split('');
      let sequential = true;
      for (let i = 1; i < digits.length; i++) {
        if (parseInt(digits[i]) !== parseInt(digits[i-1]) + 1) {
          sequential = false;
          break;
        }
      }
      if (sequential) {
        return { isSuspicious: true, reason: 'Sequential digits (e.g., 123, 456) - Please verify count' };
      }
    }
    
    // Pattern 3: Round numbers suspiciously above planned (50, 100, 500, 1000)
    const roundNumbers = [50, 100, 200, 500, 1000];
    if (roundNumbers.includes(num) && num > plannedQty * 2) {
      return { isSuspicious: true, reason: 'Suspiciously round number much higher than planned - Please verify' };
    }
    
    // Pattern 4: Exactly 10x or 100x the planned quantity (typing error)
    if (num === plannedQty * 10 || num === plannedQty * 100) {
      return { isSuspicious: true, reason: 'Exactly 10x or 100x planned qty - Possible typing error (extra zero?)' };
    }
    
    // Pattern 5: Very high percentage over planned (>300%)
    if (plannedQty > 0 && num > plannedQty * 3) {
      return { isSuspicious: true, reason: `Over 300% of planned quantity (${plannedQty}) - Please verify count` };
    }
    
    // Pattern 6: Exceeds stock by significant margin
    if (num > stockQty * 1.5) {
      return { isSuspicious: true, reason: `Significantly exceeds available stock (${stockQty}) - Check inventory` };
    }
    
    return { isSuspicious: false, reason: '' };
  }

  /**
   * Validate counted quantity input with visual feedback
   * Colors entire row: Green (perfect match), Yellow (under-count), Red (over-count)
   * Uses Bootstrap color classes: table-success, table-warning, table-danger
   * Also detects unusual/fraudulent number patterns
   * 
   * @param {HTMLElement} input - The input element to validate
   * @param {boolean} triggerAutoSave - Whether to trigger auto-save (default: true)
   */
  function validateCountedQty(input, triggerAutoSave = true) {
    const $input = $(input);
    const $row = $input.closest('tr');
    const $validationMsg = $row.find('.validation-message');
    
    // Force integer only (remove decimals)
    let value = $input.val();
    if (value && value.includes('.')) {
      value = value.split('.')[0];
      $input.val(value);
    }
    
    const plannedQty = parseInt($input.data('planned')) || 0; // Fixed: use 'planned' not 'planned-qty'
    const stockQty = parseInt($input.data('stock')) || 0;
    const countedQty = parseInt(value) || 0;

    // Remove all validation classes from row and input
    $row.removeClass('table-success table-warning table-danger');
    $input.removeClass('is-valid is-warning is-invalid border-success border-warning border-danger')
      .css('border-color', '')
      .css('background-color', '');
    $validationMsg.hide();

    // Check for unusual patterns FIRST (fraud detection)
    if (value && countedQty > 0) {
      const unusualCheck = detectUnusualNumber(value, plannedQty, stockQty);
      if (unusualCheck.isSuspicious) {
        // Show warning message below input
        $validationMsg.html(`<i class="fa fa-exclamation-triangle"></i> ${unusualCheck.reason}`).show();
        $row.addClass('table-warning');
        $input.addClass('border-warning')
          .css('border-color', '#ffc107')
          .css('background-color', '#fff8e1');
        
        // Also show toast notification
        showToast(unusualCheck.reason, 'warning');
        
        // Still schedule auto-save (but with warning logged)
        scheduleAutoSave();
        return;
      }
    }

    // Standard validation (if no unusual patterns)
    if (countedQty === plannedQty) {
      // Perfect match - GREEN (Bootstrap success color)
      $row.addClass('table-success');
      $input.addClass('is-valid border-success');
    } else if (countedQty < plannedQty && countedQty > 0) {
      // Under-count - YELLOW (Bootstrap warning color)
      $row.addClass('table-warning');
      $input.addClass('is-warning border-warning');
      $validationMsg.html(`<i class="fa fa-info-circle"></i> Short ${plannedQty - countedQty} units`).show();
    } else if (countedQty > plannedQty && countedQty <= stockQty) {
      // Over planned but within stock - YELLOW warning
      $row.addClass('table-warning');
      $input.addClass('border-warning');
      $validationMsg.html(`<i class="fa fa-info-circle"></i> ${countedQty - plannedQty} units over planned`).show();
    } else if (countedQty > stockQty) {
      // Over-count exceeds stock - RED (Bootstrap danger color)
      $row.addClass('table-danger');
      $input.addClass('is-invalid border-danger');
      $validationMsg.html(`<i class="fa fa-times-circle"></i> Exceeds stock by ${countedQty - stockQty} units!`).show();
    } else if (countedQty === 0 || !value) {
      // No value or zero - GREY (default, no class)
      $row.removeClass('table-success table-warning table-danger');
    }

    // Schedule auto-save after validation (only if not loading from draft)
    if (triggerAutoSave && autoSaveState !== 'LOADING') {
      scheduleAutoSave();
    }
  }

  /**
   * Update auto-save indicator with state and animation - Enhanced UX with fixed positioning
   * States: IDLE, SAVING, SAVED
   */
  function updateSaveIndicator(state, timestamp = null) {
    const $container = $('.auto-save-container');
    const $indicator = $('#autosave-indicator');
    const $icon = $indicator.find('.save-status-icon');
    const $status = $indicator.find('.save-status');
    const $timestamp = $indicator.find('.save-timestamp');

    // Reset animations
    $indicator.removeClass('saving-pulse saved-glow');

    if (state === 'SAVING') {
      $status.text('SAVING');
      $icon.css('background', '#0066cc');
      $indicator.addClass('saving-pulse').css({
        'background': 'linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%)',
        'border-color': '#2196f3',
        'box-shadow': '0 4px 16px rgba(33,150,243,0.4)',
        'color': '#0066cc'
      });
      $timestamp.text('Saving...').css('color', '#0066cc');
      
      // Show container with animation
      $container.addClass('show');
      
    } else if (state === 'SAVED') {
      $status.text('SAVED');
      $icon.css('background', '#00c851');
      $indicator.addClass('saved-glow').css({
        'background': 'linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%)',
        'border-color': '#4caf50',
        'box-shadow': '0 4px 16px rgba(76,175,80,0.4)',
        'color': '#00c851'
      });
      
      // Format and show timestamp
      if (timestamp) {
        const date = new Date(timestamp);
        const timeString = date.toLocaleTimeString('en-NZ', { 
          hour: '2-digit', 
          minute: '2-digit',
          hour12: true 
        });
        $timestamp.text(`${timeString}`).css('color', '#00c851');
      } else {
        const now = new Date();
        const timeString = now.toLocaleTimeString('en-NZ', { 
          hour: '2-digit', 
          minute: '2-digit',
          hour12: true 
        });
        $timestamp.text(`${timeString}`).css('color', '#00c851');
      }
      
      // Show container and hide after delay
      $container.addClass('show');
      setTimeout(() => {
        $container.removeClass('show');
      }, SAVED_DURATION);
      
    } else {
      // IDLE state - completely hidden, no distraction
      $status.text('IDLE');
      $icon.css('background', '#6c757d');
      $indicator.css({
        'background': 'linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%)',
        'border-color': '#e9ecef',
        'box-shadow': '0 4px 12px rgba(0,0,0,0.08)',
        'color': '#495057'
      });
      $timestamp.text('').css('color', '#868e96');
      
      // Hide container in IDLE - only show when actually saving
      $container.removeClass('show');
    }
  }

  /**
   * Perform auto-save using Enterprise AJAX Manager
   */
  function performAutoSave() {
    if (autoSaveState !== 'IDLE') return; // Don't save if already saving
    
    // Set to SAVING state immediately
    autoSaveState = 'SAVING';
    updateSaveIndicator('SAVING');
    
    // Collect all counted quantities
    const draftData = [];
    $table.find('tbody tr').each(function() {
      const $row = $(this);
      const productId = $row.data('product-id');
      const countedQty = $row.find('.counted-qty').val();
      
      if (productId && countedQty !== undefined && countedQty !== '') {
        draftData.push({
          product_id: productId,
          counted_qty: parseInt(countedQty) || 0
        });
      }
    });
    
    // Simulate realistic save time, then send to server
    setTimeout(() => {
      ConsignmentsAjax.request({
        action: 'autosave_transfer',
        data: {
          pin: '5050',
          transfer_id: transferId,
          draft_data: draftData
        },
        showLoader: false, // Use our custom indicator instead
        showSuccess: false, // Visual feedback via indicator
        showError: true // Show errors as toasts
      })
      .then(function(response) {
        // Move to SAVED state
        autoSaveState = 'SAVED';
        updateSaveIndicator('SAVED', response.data.updated_at);
        
        // After SAVED_DURATION, return to IDLE
        setTimeout(function() {
          autoSaveState = 'IDLE';
          updateSaveIndicator('IDLE', response.data.updated_at);
        }, SAVED_DURATION);
      })
      .catch(function(error) {
        console.error('Auto-save failed:', error);
        
        // Log error to server for monitoring
        ConsignmentsAjax.request({
          action: 'log_error',
          data: {
            level: 'ERROR',
            message: 'Auto-save failed: ' + error.message,
            context: {
              transfer_id: transferId,
              error_type: error.code || 'UNKNOWN',
              draft_data_count: draftData.length
            },
            url: window.location.href
          },
          showLoader: false,
          showSuccess: false,
          showError: false // Already showed error from first request
        });
        
        autoSaveState = 'IDLE';
        updateSaveIndicator('IDLE');
      });
    }, SAVING_DURATION); // Wait 1 second to show SAVING state
  }

  /**
   * Schedule auto-save with improved timing
   * - First keystroke saves immediately if IDLE
   * - During SAVING/SAVED states, queue the next save for 2 seconds after current cycle completes
   * - Don't interrupt animations
   */
  function scheduleAutoSave() {
    // If currently IDLE, save immediately on first keystroke
    if (autoSaveState === 'IDLE') {
      // Clear any pending timer
      if (autoSaveTimer) {
        clearTimeout(autoSaveTimer);
      }
      // Save immediately
      performAutoSave();
    } else {
      // If already saving or recently saved, don't interrupt - queue for later
      if (autoSaveTimer) {
        clearTimeout(autoSaveTimer);
      }
      
      // Calculate when to save next based on current state
      let delayMs = AUTO_SAVE_DEBOUNCE; // Default 1 second
      
      if (autoSaveState === 'SAVING') {
        // Wait for SAVING to complete + 1 second
        delayMs = SAVING_DURATION + AUTO_SAVE_DEBOUNCE;
      } else if (autoSaveState === 'SAVED') {
        // Wait for SAVED state to end + 1 second
        delayMs = SAVED_DURATION + AUTO_SAVE_DEBOUNCE;
      }
      
      // Schedule new save
      autoSaveTimer = setTimeout(performAutoSave, delayMs);
    }
  }

  // ============================================================================
  // TRANSFER SUBMISSION SYSTEM
  // ============================================================================

  /**
   * Comprehensive frontend validation before submission
   */
  function validateTransferForSubmission() {
    const validation = {
      isValid: true,
      errors: [],
      warnings: [],
      summary: {
        totalProducts: 0,
        totalCounted: 0,
        emptyCount: 0,
        overageCount: 0,
        shortageCount: 0
      }
    };

    // Validate each product row
    $table.find('tbody tr').each(function() {
      const $row = $(this);
      const $input = $row.find('.counted-qty');
      const productName = $row.find('.product-name').text().trim();
      const plannedQty = parseInt($input.data('planned')) || 0;
      const stockQty = parseInt($input.data('stock')) || 0;
      const countedQty = parseInt($input.val()) || 0;

      validation.summary.totalProducts++;

      if (countedQty === 0) {
        validation.summary.emptyCount++;
        validation.warnings.push(`${productName}: No quantity counted (planned: ${plannedQty})`);
      } else {
        validation.summary.totalCounted += countedQty;
        
        if (countedQty > stockQty) {
          validation.errors.push(`${productName}: Count (${countedQty}) exceeds available stock (${stockQty})`);
          validation.isValid = false;
        } else if (countedQty > plannedQty) {
          validation.summary.overageCount++;
          validation.warnings.push(`${productName}: Overage of ${countedQty - plannedQty} units`);
        } else if (countedQty < plannedQty) {
          validation.summary.shortageCount++;
          validation.warnings.push(`${productName}: Shortage of ${plannedQty - countedQty} units`);
        }
      }
    });

    // Check if at least one product has been counted
    if (validation.summary.totalCounted === 0) {
      validation.errors.push('No products have been counted. At least one product must have a quantity.');
      validation.isValid = false;
    }

    return validation;
  }

  /**
   * Build comprehensive transfer object for submission
   */
  function buildTransferObject() {
    const transferData = {
      transfer_id: transferId,
      action: 'submit_transfer',  // 🔧 FIX: Use submit_transfer consistently throughout
      metadata: {
        timestamp: new Date().toISOString(),
        user_agent: navigator.userAgent,
        screen_resolution: `${screen.width}x${screen.height}`,
        submission_type: 'pack_and_ready'
      },
      
      // Transfer Details
      transfer: {
        id: transferId,
        status: 'ready_for_delivery',
        packed_at: new Date().toISOString(),
        packed_by: window.currentUser?.name || 'System User'
      },

      // Collected Product Data
      products: [],
      
      // Default Shipping Configuration
      shipping: {
        carrier: 'NZ_POST',
        service_type: 'standard',
        pickup_required: false,
        delivery_method: 'standard_delivery'
      },

      // Default Parcel (single parcel containing all items)
      parcels: [{
        parcel_id: 1,
        weight_kg: 0, // Will be calculated server-side
        dimensions: { length: 0, width: 0, height: 0 }, // Server-side defaults
        items: []
      }],

      // Tracking (to be generated server-side)
      tracking: {
        numbers: [],
        carrier: 'NZ_POST',
        service: 'Standard Post'
      },

      // Notes (empty for now as per requirements)
      notes: {
        internal: '',
        delivery: '',
        special_instructions: ''
      }
    };

    // Collect all counted products
    $table.find('tbody tr').each(function() {
      const $row = $(this);
      const $input = $row.find('.counted-qty');
      const productId = $row.data('product-id');
      const countedQty = parseInt($input.val()) || 0;
      
      if (countedQty > 0) {
        const productItem = {
          product_id: productId,
          sku: $row.find('small:contains("SKU:")').text().replace('SKU: ', '').trim(),
          name: $row.find('.product-name').text().trim(),
          planned_qty: parseInt($input.data('planned')) || 0,
          counted_qty: countedQty,
          stock_level: parseInt($input.data('stock')) || 0,
          variance: countedQty - (parseInt($input.data('planned')) || 0)
        };

        transferData.products.push(productItem);
        
        // Add to default parcel
        transferData.parcels[0].items.push({
          product_id: productId,
          quantity: countedQty,
          weight_per_unit: 0 // Server-side calculation
        });
      }
    });

    return transferData;
  }

  /**
   * Main transfer submission function with enhanced consignment upload
   */
  async function submitTransfer() {
    try {
      // DISABLE BUTTON IMMEDIATELY!
      const $submitBtn = $('.submit-transfer-btn, #submitTransferBtn, button[onclick*="submitTransfer"]');
      $submitBtn.prop('disabled', true).css('opacity', '0.5').css('cursor', 'not-allowed');
      
      // Get transfer ID
      const transferId = $('#transfer-table').data('transfer-id');
      console.log('🔍 Transfer ID:', transferId);
      
      // DON'T open modal yet - wait until we have session ID
      
      // 1. Show overlay and start validation
      // showSubmissionOverlay(); // DISABLED - using simple modal instead
      updateProgressStep('validation', 'active', 'Validating transfer data...');
      addLiveFeedback('Starting comprehensive validation...', 'info');

      // 2. Frontend validation
      await delay(800); // UX timing
      const validation = validateTransferForSubmission();
      
      if (!validation.isValid) {
        addLiveFeedback('Validation failed!', 'error');
        validation.errors.forEach(error => addLiveFeedback(`❌ ${error}`, 'error'));
        
        setTimeout(() => {
          closeSubmissionOverlay();
          showToast('Please fix validation errors before submitting', 'error');
        }, 2000);
        return;
      }

      // Show validation summary
      updateProgressStep('validation', 'complete', `Validated ${validation.summary.totalProducts} products`);
      addLiveFeedback(`✅ Validation passed: ${validation.summary.totalCounted} items ready`, 'success');
      
      if (validation.warnings.length > 0) {
        addLiveFeedback(`⚠️ ${validation.warnings.length} warnings noted`, 'warning');
      }

      // 3. Build transfer object and save locally first
      await delay(500);
      updateProgressStep('consignment', 'active', 'Preparing transfer for consignment...');
      addLiveFeedback('Saving transfer data locally...', 'info');
      
      const transferData = buildTransferObject();
      
      // Save transfer data locally first
      const saveResponse = await ConsignmentsAjax.request({
        action: 'submit_transfer',  // 🔧 FIX: Use submit_transfer instead of save_transfer to avoid API routing issues
        data: transferData,
        showLoader: false,
        showSuccess: false,
        showError: false
      });
      
      // DEBUG: Log the actual response
      console.log('API Response:', saveResponse);
      
      if (!saveResponse.success) {
        const errorMsg = saveResponse.error || saveResponse.message || 'Unknown error';
        console.error('API Error Details:', saveResponse);
        throw new Error('Failed to save transfer data: ' + errorMsg);
      }
      
      addLiveFeedback(`💾 Transfer data saved successfully`, 'success');

      // 4. DUAL MODE: Queue or Direct Upload (based on server config)
      await delay(500);
      updateProgressStep('consignment', 'complete', 'Transfer saved - preparing upload');
      updateProgressStep('products', 'active', 'Starting Lightspeed sync...');
      
      const uploadMode = saveResponse.upload_mode || 'direct';
      
      if (uploadMode === 'queue') {
        // QUEUE MODE: Job created, workers will process
        addLiveFeedback('� Queue mode: Job created for background workers', 'info');
        addLiveFeedback(`📋 Queue Job ID: ${saveResponse.queue_job_id}`, 'info');
        addLiveFeedback('⚠️ Waiting for workers to process...', 'warning');
        
        updateProgressStep('products', 'complete', 'Queue job created');
        updateProgressStep('complete', 'active', 'Waiting for workers...');
        
        await delay(2000);
        updateProgressStep('complete', 'complete', 'Queued successfully');
        
        setTimeout(() => {
          closeSubmissionOverlay();
          showToast('Transfer queued! Workers will process when they wake up 😴', 'success');
          window.location.reload();
        }, 2000);
        
      } else {
        // DIRECT MODE: NOW open modal once we have the session ID
        const sessionId = saveResponse.upload_session_id;
        const uploadUrl = '/modules/consignments/api/simple-upload-direct.php';
        
        if (!sessionId) {
          throw new Error('Server did not provide upload session ID');
        }
        
        // Open modal ONCE with real session ID
        openSimpleProgressModal(transferId, sessionId);
        updateProgressModal('🚀 Connecting to Vend API...');
        
        // Connect SSE for progress
        connectModalSSE(transferId, sessionId);
        
        // Start the upload in background
        const uploadFormData = new FormData();
        uploadFormData.append('transfer_id', transferId);
        uploadFormData.append('session_id', sessionId);
        
        fetch(uploadUrl, {
          method: 'POST',
          body: uploadFormData
        })
        .then(async response => {
          // GRACEFUL ERROR HANDLING - Check content type
          const contentType = response.headers.get('content-type');
          
          if (!contentType || !contentType.includes('application/json')) {
            // Server returned HTML/text - likely PHP error
            const errorText = await response.text();
            console.error('❌ Server returned non-JSON:', errorText);
            
            // Extract meaningful error from PHP error page
            let errorMsg = 'Server error occurred';
            const fatalMatch = errorText.match(/<b>Fatal error<\/b>:\s*(.+?)<br/i);
            const errorMatch = errorText.match(/<b>Error<\/b>:\s*(.+?)<br/i);
            
            if (fatalMatch) {
              errorMsg = fatalMatch[1].replace(/<[^>]+>/g, '').trim();
            } else if (errorMatch) {
              errorMsg = errorMatch[1].replace(/<[^>]+>/g, '').trim();
            } else if (errorText.includes('Cannot redeclare')) {
              errorMsg = 'PHP function conflict - please refresh page and try again';
            }
            
            throw new Error(errorMsg);
          }
          
          return response.json();
        })
        .then(uploadResult => {
          console.log('📥 Upload response:', uploadResult);
          
          if (uploadResult.success) {
            updateProgressModal(`✅ ${uploadResult.message || 'Upload started successfully'}`);
            console.log(`🎯 Vend Consignment: ${uploadResult.consignment_id}`);
            if (uploadResult.vend_url) {
              console.log(`🔗 View in Vend: ${uploadResult.vend_url}`);
            }
          } else {
            const errorMsg = uploadResult.error || 'Unknown error';
            const errorCode = uploadResult.error_code || 'UNKNOWN';
            console.error(`❌ Upload failed [${errorCode}]:`, errorMsg);
            
            // Show detailed error in modal
            if (modalEventSource) {
              modalEventSource.close();
              modalEventSource = null;
            }
            $('#progressStatus').html(`
              <div style="color: #ef4444; font-weight: 700; font-size: 15px; margin-bottom: 8px;">❌ Upload Failed</div>
              <div style="background: #fee2e2; color: #991b1b; padding: 12px; border-radius: 8px; font-size: 12px; line-height: 1.5; border: 1px solid #fecaca;">
                <strong>Error:</strong> ${errorMsg}<br>
                <span style="opacity: 0.7; font-size: 11px;">Code: ${errorCode}</span>
              </div>
            `);
            $('#closeModalBtn').text('Close').show();
          }
        })
        .catch(err => {
          console.error('💥 Upload error:', err);
          
          if (modalEventSource) {
            modalEventSource.close();
            modalEventSource = null;
          }
          
          $('#progressStatus').html(`
            <div style="color: #ef4444; font-weight: 700; font-size: 15px; margin-bottom: 8px;">❌ Connection Error</div>
            <div style="background: #fee2e2; color: #991b1b; padding: 12px; border-radius: 8px; font-size: 12px; line-height: 1.5; border: 1px solid #fecaca;">
              ${err.message}
            </div>
            <div style="margin-top: 12px; padding: 10px; background: #fef3c7; border-radius: 8px; font-size: 11px; color: #92400e; border: 1px solid #fde68a;">
              💡 <strong>Tip:</strong> Refresh the page and try again. If this persists, contact support.
            </div>
          `);
          $('#closeModalBtn').text('Close').show();
        });
      }

    } catch (error) {
      console.error('Transfer submission failed:', error);
      addLiveFeedback(`❌ Submission failed: ${error.message}`, 'error');
      showErrorState(error.message);
      
      setTimeout(() => {
        closeSubmissionOverlay();
        showToast('Submission failed! ' + error.message, 'error');
      }, 2000);
    }
  }

  /**
   * Auto-fill all empty counted quantity fields with their planned quantities
   * Only fills inputs that are currently empty or 0
   */
  function autoFillAllQuantities() {
    let filledCount = 0;
    let skippedCount = 0;

    // Find all counted quantity inputs
    $('#transfer-table tbody input.counted-qty-input').each(function() {
      const $input = $(this);
      const currentValue = parseInt($input.val()) || 0;
      
      // Only fill if current value is 0 or empty
      if (currentValue === 0 || $input.val() === '') {
        // Get the planned quantity from the same row
        const $row = $input.closest('tr');
        const plannedQty = parseInt($row.find('.planned-qty').text()) || 0;
        
        if (plannedQty > 0) {
          // Fill the input with planned quantity
          $input.val(plannedQty);
          
          // Trigger validation and auto-save
          $input.trigger('input');
          
          // Visual feedback - flash green
          $input.addClass('border-success');
          setTimeout(() => {
            $input.removeClass('border-success');
          }, 800);
          
          filledCount++;
        }
      } else {
        skippedCount++;
      }
    });

    // Show feedback message
    if (filledCount > 0) {
      alert(`✅ Auto-filled ${filledCount} quantities!\n${skippedCount > 0 ? `Skipped ${skippedCount} items that already had values.` : ''}`);
    } else {
      alert('ℹ️ No empty quantities to fill. All items already have counted values.');
    }

    console.log(`Auto-fill completed: ${filledCount} filled, ${skippedCount} skipped`);
  }

  /**
   * Generate unique session ID for progress tracking
   */
  function generateSessionId() {
    return 'session_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
  }

  /**
   * Show the full-screen submission overlay
   */
  function showSubmissionOverlay() {
    $('#submission-overlay').fadeIn(300);
    document.body.style.overflow = 'hidden';
  }

  /**
   * Close the submission overlay
   */
  function closeSubmissionOverlay() {
    $('#submission-overlay').fadeOut(300);
    document.body.style.overflow = '';
    
    // Reset overlay state
    $('#error-state').hide();
    $('.progress-step').each(function() {
      $(this).css({
        'background': 'rgba(255,255,255,0.05)',
        'border-left-color': '#6c757d'
      });
      $(this).find('.step-indicator').html('<i class="fa fa-circle" style="font-size: 8px; color: #fff;"></i>').css('background', '#6c757d');
      $(this).find('.step-details').text('Waiting...').css('color', '#666');
    });
    $('#live-feedback').html('<div style="font-size: 14px; color: #28a745; margin-bottom: 5px;"><i class="fa fa-check-circle mr-2"></i>Transfer validation initiated...</div>');
  }

  /**
   * Update progress step visual state
   */
  function updateProgressStep(stepName, state, message = '') {
    const $step = $(`.progress-step[data-step="${stepName}"]`);
    const $indicator = $step.find('.step-indicator');
    const $details = $step.find('.step-details');

    if (state === 'active') {
      $step.css({
        'background': 'rgba(255,193,7,0.1)',
        'border-left-color': '#ffc107'
      });
      $indicator.html('<i class="fa fa-spinner fa-spin" style="font-size: 10px; color: #000;"></i>').css('background', '#ffc107');
      $details.text(message).css('color', '#ffc107');
      
    } else if (state === 'complete') {
      $step.css({
        'background': 'rgba(40,167,69,0.1)',
        'border-left-color': '#28a745'
      });
      $indicator.html('<i class="fa fa-check" style="font-size: 10px; color: #fff;"></i>').css('background', '#28a745');
      $details.text(message).css('color', '#28a745');
    }
  }

  /**
   * Add live feedback message
   */
  function addLiveFeedback(message, type = 'info') {
    const $feedback = $('#live-feedback');
    const colors = {
      'info': '#17a2b8',
      'success': '#28a745', 
      'warning': '#ffc107',
      'error': '#dc3545'
    };
    
    const timestamp = new Date().toLocaleTimeString('en-NZ', { hour12: false });
    const feedbackHtml = `<div style="font-size: 13px; color: ${colors[type]}; margin-bottom: 3px;">
      <span style="color: #666;">[${timestamp}]</span> ${message}
    </div>`;
    
    $feedback.append(feedbackHtml);
    $feedback.scrollTop($feedback[0].scrollHeight);
  }

  /**
   * Show error state in overlay
   */
  function showErrorState(errorMessage) {
    $('.progress-step').each(function() {
      const $step = $(this);
      if ($step.find('.step-indicator .fa-spinner').length > 0) {
        $step.css({
          'background': 'rgba(220,53,69,0.1)',
          'border-left-color': '#dc3545'
        });
        $step.find('.step-indicator').html('<i class="fa fa-times" style="font-size: 10px; color: #fff;"></i>').css('background', '#dc3545');
        $step.find('.step-details').text('Failed').css('color', '#dc3545');
      }
    });
    
    $('#error-state').show();
  }

  /**
   * Start Server-Sent Events for live feedback
   */
  function startServerSideEvents(transferId) {
    // This would connect to an SSE endpoint for real-time updates
    // For now, we'll simulate with the AJAX response handling above
    addLiveFeedback('📡 Connected to server for live updates', 'info');
  }

  /**
   * Utility delay function for UX timing
   */
  function delay(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
  }

  /**
   * Search for products and display results
   */
  async function searchProducts() {
    const query = $('#search-input').val().trim();
    
    if (query.length < 2) {
      $('#search-results').html('<div class="text-muted p-3">Type at least 2 characters to search</div>');
      return;
    }

    try {
      const response = await ConsignmentsAjax.request({
        action: 'search_products',
        data: { query: query, outlet_id: window.transferData?.outletFrom?.id || 0 },
        showLoader: false
      });

      displaySearchResults(response.data.products, query);
    } catch (error) {
      $('#search-results').html('<div class="alert alert-danger">Search failed. Please try again.</div>');
      console.error('Product search failed:', error);
    }
  }

  /**
   * Display search results with multi-select checkboxes
   */
  function displaySearchResults(results, query) {
    const $container = $('#search-results');
    
    if (!results || results.length === 0) {
      $container.html(`<div class="text-muted p-3">No products found matching "${query}"</div>`);
      return;
    }

    let html = '<div class="list-group">';
    results.forEach(product => {
      const isInTransfer = $table.find(`tr[data-product-id="${product.id}"]`).length > 0;
      const disabled = isInTransfer ? 'disabled' : '';
      const badge = isInTransfer ? '<span class="badge badge-secondary ml-2">Already Added</span>' : '';
      
      html += `
        <div class="list-group-item search-result-row" data-product-id="${product.id}" data-supplier="${product.supplier || 'Unknown'}">
          <div class="d-flex align-items-center">
            <input type="checkbox" class="product-select-checkbox mr-2" ${disabled} data-product-id="${product.id}">
            <div class="flex-grow-1">
              <div class="product-name">${product.name}</div>
              <small class="text-muted product-sku">${product.sku} | Stock: ${product.stock || 0}</small>
            </div>
            <button class="btn btn-sm btn-primary" ${disabled} data-product-id="${product.id}" onclick="addProductToTransfer(this)">
              <i class="fa fa-plus"></i> Add
            </button>
            ${badge}
          </div>
        </div>`;
    });
    html += '</div>';
    
    $container.html(html);
    initializeMultiSelect();
  }

  /**
   * Add product to transfer table
   */
  function addProductToTransfer($btn) {
    const productId = $($btn).data('product-id');
    const $row = $(`.search-result-row[data-product-id="${productId}"]`);
    
    // Get product details from row
    const product = {
      id: productId,
      name: $row.find('.product-name').text(),
      sku: $row.find('.product-sku').text().split('|')[0].trim()
    };

    // Add product to table (backend call)
    ConsignmentsAjax.request({
      action: 'add_product_to_transfer',
      data: {
        transfer_id: transferId,
        product_id: productId
      }
    })
    .then(function(response) {
      // Disable button and checkbox
      $($btn).prop('disabled', true).html('<i class="fa fa-check"></i> Added');
      $row.find('.product-select-checkbox').prop('disabled', true).prop('checked', false);
      $row.append('<span class="badge badge-secondary ml-2">Already Added</span>');
      
      // Reload page to show new product in table
      location.reload();
    })
    .catch(function(error) {
      console.error('Failed to add product:', error);
    });
  }

  /**
   * Initialize multi-select functionality
   */
  function initializeMultiSelect() {
    selectedProducts.clear();
    
    // Checkbox change handler
    $(document).off('change.multiselect').on('change.multiselect', '.product-select-checkbox', function(e) {
      e.stopPropagation();
      const productId = $(this).data('product-id');
      const $row = $(this).closest('.search-result-row');
      
      if ($(this).is(':checked')) {
        selectedProducts.add(productId);
        $row.addClass('selected');
      } else {
        selectedProducts.delete(productId);
        $row.removeClass('selected');
      }
      
      updateBulkControls();
    });
    
    // Row click handler (toggle checkbox)
    $(document).off('click.multiselect').on('click.multiselect', '.search-result-row', function(e) {
      if ($(e.target).is('button, input, a')) return;
      
      const $checkbox = $(this).find('.product-select-checkbox');
      if (!$checkbox.prop('disabled')) {
        $checkbox.prop('checked', !$checkbox.prop('checked')).trigger('change');
      }
    });
  }

  /**
   * Update bulk action button states
   */
  function updateBulkControls() {
    const count = selectedProducts.size;
    $('#add-to-current-btn').prop('disabled', count === 0);
    $('#add-to-all-btn').prop('disabled', count === 0);
  }

  /**
   * Select all visible products
   */
  function selectAllVisible() {
    $('.product-select-checkbox:not(:disabled)').prop('checked', true).trigger('change');
  }

  /**
   * Clear all selections
   */
  function clearSelection() {
    $('.product-select-checkbox').prop('checked', false);
    $('.search-result-row').removeClass('selected');
    selectedProducts.clear();
    updateBulkControls();
  }

  /**
   * Add selected products to current transfer
   */
  function addSelectedToCurrentTransfer() {
    if (selectedProducts.size === 0) return;

    let addedCount = 0;
    selectedProducts.forEach(productId => {
      const $row = $(`.search-result-row[data-product-id="${productId}"]`);
      const $btn = $row.find('button[data-product-id]');

      if (!$btn.prop('disabled')) {
        $btn.click();
        addedCount++;
      }
    });

    if (addedCount > 0) {
      showToast(`Added ${addedCount} products to this transfer`, 'success');
      clearSelection();
    } else {
      showToast('No products could be added (already in transfer or out of stock)', 'warning');
    }
  }

  /**
   * Add selected products to all transfers
   */
  async function addSelectedToAllTransfers() {
    if (selectedProducts.size === 0) return;

    // TODO: Implement backend endpoint to add products to all pending transfers
    showToast('Add to All Transfers feature requires backend implementation', 'info');
  }

  /**
   * Add selected products to outlet transfers
   */
  function addSelectedToOutletTransfers() {
    if (selectedProducts.size === 0) {
      showToast('Please select products first', 'warning');
      return;
    }

    const selectedProductsArray = Array.from(selectedProducts).map(id => {
      const $row = $(`.search-result-row[data-product-id="${id}"]`);
      return {
        id: id,
        name: $row.find('.product-name').text(),
        sku: $row.find('.product-sku').text().split('|')[0].trim()
      };
    });

    // TODO: Show modal to select outlet transfers
    console.log('Add to outlet transfers:', selectedProductsArray);
    showToast('Outlet transfers feature requires modal implementation', 'info');
  }

  /**
   * Add selected products to similar transfers
   */
  function addSelectedToSimilarTransfers() {
    if (selectedProducts.size === 0) {
      showToast('Please select products first', 'warning');
      return;
    }

    const selectedProductsArray = Array.from(selectedProducts).map(id => {
      const $row = $(`.search-result-row[data-product-id="${id}"]`);
      return {
        id: id,
        name: $row.find('.product-name').text(),
        sku: $row.find('.product-sku').text().split('|')[0].trim()
      };
    });

    // TODO: Show modal to select similar transfers
    console.log('Add to similar transfers:', selectedProductsArray);
    showToast('Similar transfers feature requires modal implementation', 'info');
  }

  // ============================================================================
  // TRANSFER COMPLETION
  // ============================================================================

  /**
   * Mark transfer as ready for delivery
   */
  async function markReadyForDelivery() {
    // Validate all quantities are entered
    const hasEmptyQty = $table.find('.counted-qty').filter(function() {
      return $(this).val() === '' || $(this).val() === null;
    }).length > 0;

    if (hasEmptyQty) {
      showToast('Please enter counted quantities for all products', 'warning');
      return;
    }

    // Validate no invalid quantities (red)
    const hasInvalid = $table.find('.counted-qty.is-invalid').length > 0;
    if (hasInvalid) {
      showToast('Please fix invalid quantities (over-counts)', 'danger');
      return;
    }

    // Collect products for submission
    const products = [];
    $table.find('tbody tr').each(function() {
      const $row = $(this);
      products.push({
        product_id: $row.data('product-id'),
        counted_qty: $row.find('.counted-qty').val()
      });
    });

    // Collect tracking numbers
    const trackingNumbers = [];
    $('#tracking-numbers-container .tracking-input').each(function() {
      const value = $(this).val().trim();
      if (value) trackingNumbers.push(value);
    });

    try {
      const response = await ConsignmentsAjax.request({
        action: 'mark_transfer_ready',
        data: {
          transfer_id: transferId,
          products: products,
          tracking_numbers: trackingNumbers
        }
      });

      showToast('Transfer marked as ready for delivery!', 'success');
      
      // Redirect after 2 seconds
      setTimeout(function() {
        window.location.href = '/modules/consignments/stock-transfers/';
      }, 2000);
    } catch (error) {
      console.error('Failed to mark transfer ready:', error);
    }
  }

  // ============================================================================
  // LABEL PRINTING
  // ============================================================================

  /**
   * Open label print dialog
   */
  function openLabelPrintDialog() {
    // TODO: Implement label print dialog
    showToast('Label printing dialog coming soon', 'info');
    console.log('Open label print dialog for transfer:', transferId);
  }

  // ============================================================================
  // UTILITY FUNCTIONS
  // ============================================================================

  /**
   * Show toast notification - now uses global CIS.Toast system
   */
  function showToast(message, type = 'info') {
    // Use global CIS.Toast system (template-wide)
    if (window.CIS && window.CIS.Toast) {
      window.CIS.Toast.show(message, type);
    }
    // Fallback to ajax-manager's toast if CIS.Toast not available
    else if (window.ConsignmentsAjax && window.ConsignmentsAjax.showToast) {
      window.ConsignmentsAjax.showToast(message, type);
    }
    // Last resort fallback
    else {
      alert(message);
    }
  }

  /**
   * Load saved draft data on page load (without triggering auto-save)
   */
  async function loadSavedDraft() {
    if (!transferId) return;
    
    try {
      const response = await ConsignmentsAjax.request({
        action: 'get_draft_transfer',
        data: {
          pin: '5050',
          transfer_id: transferId
        },
        showLoader: false,
        showSuccess: false,
        showError: false
      });
      
      if (response.success && response.data.draft_data) {
        const draftData = response.data.draft_data;
        let loadedCount = 0;
        
        // Temporarily disable auto-save while loading
        const originalState = autoSaveState;
        autoSaveState = 'LOADING';
        
        // Load values into inputs
        draftData.forEach(item => {
          const $row = $(`tr[data-product-id="${item.product_id}"]`);
          if ($row.length) {
            const $input = $row.find('.counted-qty');
            if ($input.length && item.counted_qty !== undefined && item.counted_qty !== '') {
              $input.val(item.counted_qty);
              // Validate the loaded value (but don't trigger auto-save)
              validateCountedQty($input[0], false); // false = don't trigger auto-save
              loadedCount++;
            }
          }
        });
        
        // Restore auto-save state
        autoSaveState = originalState;
        
        if (loadedCount > 0) {
          // Show last saved time if available
          if (response.data.draft_updated_at) {
            updateSaveIndicator('IDLE', response.data.draft_updated_at);
          }
          console.log(`Loaded ${loadedCount} saved values from draft`);
        }
      }
    } catch (error) {
      console.log('No saved draft found or error loading:', error.message);
      // This is not critical - continue without saved values
    }
  }

  // ============================================================================
  // EVENT LISTENERS
  // ============================================================================

  $(document).ready(function() {
    // Load saved draft values first (before setting up event listeners)
    loadSavedDraft();
    
    // Validation on input
    $(document).on('input', '.counted-qty', function() {
      validateCountedQty(this);
    });

    // Print mode (hide UI elements when printing)
    window.addEventListener('beforeprint', function() {
      $('.no-print').hide();
      $('.counted-qty').each(function() {
        const $input = $(this);
        const value = $input.val() || '—';
        $input.after(`<span class="print-value">${value}</span>`);
        $input.hide();
      });
    });

    window.addEventListener('afterprint', function() {
      $('.no-print').show();
      $('.print-value').remove();
      $('.counted-qty').show();
    });

    // Search products
    $('#search-input').on('input', searchProducts);
    $('#btn-clear-search').on('click', function() {
      $('#search-input').val('');
      $('#search-results').empty();
      clearSelection();
    });

    // Initialize state indicator
    updateSaveIndicator('IDLE');
    
    console.log('Pack transfer JavaScript initialized');
  });

  // ============================================================================
  // GANGSTA UPLOAD MODAL (Auto-expires to boring mode after 2 weeks)
  // ============================================================================
  
  function openUploadModal(transferId, sessionId, progressUrl) {
    // Calculate days until gangsta mode expires (Oct 30, 2025)
    const expiryDate = new Date('2025-10-30 23:59:59');
    const now = new Date();
    const daysLeft = Math.ceil((expiryDate - now) / (1000 * 60 * 60 * 24));
    
    // Determine personality based on days left
    let personality = 'boring'; // Default to boring
    let title = 'Upload Progress';
    let modalClass = 'corporate-modal';
    
    if (daysLeft >= 14) {
      personality = 'gangsta';
      title = '🔥 YO WE PUSHIN THIS SHIT TO LIGHTSPEED RETAIL FAM 🔥';
      modalClass = 'gangsta-modal';
    } else if (daysLeft >= 7) {
      personality = 'hood';
      title = '😎 Syncing to Lightspeed Retail (Direct Upload)';
      modalClass = 'hood-modal';
    } else if (daysLeft >= 3) {
      personality = 'corporate';
      title = '👔 Lightspeed Retail Synchronization';
      modalClass = 'corporate-modal';
    } else if (daysLeft > 0) {
      personality = 'boring';
      title = 'Lightspeed Retail Upload Progress';
      modalClass = 'corporate-modal';
    }
    
    // 🎵 GANGSTA BEATS: Add background music if in gangsta mode
    let audioHTML = '';
    if (personality === 'gangsta') {
      // Using royalty-free trap beat from YouTube Audio Library (looped)
      audioHTML = `
        <audio id="gangstaBeats" autoplay loop volume="0.3">
          <source src="https://cdn.pixabay.com/audio/2022/03/23/audio_d1718dcf88.mp3" type="audio/mpeg">
          <source src="https://cdn.pixabay.com/audio/2022/05/27/audio_1808fbf07a.mp3" type="audio/mpeg">
        </audio>
      `;
    }
    
    // Create modal HTML
    const modalHTML = `
      <div id="uploadModal" class="upload-modal ${modalClass}" style="
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.85);
        z-index: 99999;
        display: flex;
        align-items: center;
        justify-content: center;
        backdrop-filter: blur(5px);
      ">
        <div class="modal-content" style="
          background: #1a1a1a;
          border-radius: 12px;
          width: 95%;
          max-width: 1400px;
          height: 90vh;
          max-height: 900px;
          box-shadow: 0 10px 50px rgba(0,255,0,0.3);
          border: ${personality === 'gangsta' ? '3px solid #00ff00' : '1px solid #333'};
          display: flex;
          flex-direction: column;
          overflow: hidden;
        ">
          <div class="modal-header" style="
            background: ${personality === 'gangsta' ? 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)' : '#2d2d2d'};
            padding: 10px 15px;
            border-bottom: 2px solid ${personality === 'gangsta' ? '#00ff00' : '#444'};
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-shrink: 0;
          ">
            <h3 style="
              margin: 0;
              color: #fff;
              font-size: ${personality === 'gangsta' ? '18px' : '16px'};
              font-weight: bold;
              text-shadow: ${personality === 'gangsta' ? '2px 2px 4px rgba(0,0,0,0.5)' : 'none'};
            ">${title}</h3>
            <div style="display: flex; gap: 8px; align-items: center;">
              ${personality === 'gangsta' ? `
                <button onclick="toggleBeats()" id="beatsToggle" style="
                  background: #00ff00;
                  border: none;
                  color: #000;
                  padding: 5px 10px;
                  border-radius: 4px;
                  cursor: pointer;
                  font-size: 12px;
                  font-weight: bold;
                ">🎵 BEATS</button>
              ` : ''}
              <button onclick="closeUploadModal()" style="
                background: #d9534f;
                border: none;
                color: white;
                padding: 5px 10px;
                border-radius: 4px;
                cursor: pointer;
                font-size: 14px;
                font-weight: bold;
              ">✖</button>
            </div>
          </div>
          ${audioHTML}
          <div class="modal-body" style="
            flex: 1;
            overflow: hidden;
            background: #0a0a0a;
          ">
            <iframe 
              src="${progressUrl}" 
              style="
                width: 100%;
                height: 100%;
                border: none;
              "
              id="uploadProgressFrame"
            ></iframe>
          </div>
          ${personality === 'gangsta' ? `
          <div class="modal-footer" style="
            background: #1a1a1a;
            padding: 8px 15px;
            border-top: 2px solid #00ff00;
            text-align: center;
            color: #00ff00;
            font-size: 11px;
            flex-shrink: 0;
          ">
            💯 UPLOADING LIVE - NO WAITING 🔥 | Gangsta mode: ${daysLeft} days left
          </div>
          ` : ''}
        </div>
      </div>
    `;
    
    // Add modal to page
    $('body').append(modalHTML);
    
    // Add gangsta styles if in gangsta mode
    if (personality === 'gangsta') {
      $('head').append(`
        <style>
          @keyframes gangsta-glow {
            0%, 100% { box-shadow: 0 0 20px rgba(0,255,0,0.3); }
            50% { box-shadow: 0 0 40px rgba(0,255,0,0.6); }
          }
          .gangsta-modal .modal-content {
            animation: gangsta-glow 2s infinite;
          }
        </style>
      `);
    }
  }
  
  window.closeUploadModal = function() {
    $('#uploadModal').fadeOut(300, function() {
      $(this).remove();
      // Reload page to see updated transfer
      window.location.reload();
    });
  };

  // ============================================================================
  // SIMPLE PROGRESS MODAL (Clean & Professional)
  // ============================================================================
  
function openSimpleProgressModal(transferId, sessionId) {
  $('#simpleProgressModal').remove();

  const modalHTML = `
    <div id="simpleProgressModal" style="
      position: fixed; inset: 0; background: rgba(0,0,0,0.92);
      display: flex; align-items: center; justify-content: center; z-index: 99999;
      backdrop-filter: blur(8px); animation: fadeIn .25s ease;
    ">
      <style>
        @keyframes fadeIn { from { opacity: 0 } to { opacity: 1 } }
        @keyframes shimmer { 0% { left: -100% } 100% { left: 200% } }
        @keyframes glow { 0%,100% { box-shadow: 0 0 20px rgba(16,185,129,.5) } 50% { box-shadow: 0 0 40px rgba(16,185,129,.8) } }
        .product-item { transition: all .15s ease }
        .product-item:hover { transform: translateX(2px); background: rgba(16,185,129,.14) !important }
      </style>

      <div style="
        background: linear-gradient(135deg,#fff 0%,#f0fdf4 100%); padding: 22px; border-radius: 14px;
        max-width: 560px; width: 92%; color: #111827; box-shadow: 0 24px 70px rgba(16,185,129,.28);
        border: 2px solid rgba(16,185,129,.35); animation: fadeIn .25s ease;
      ">
        <div style="text-align:center;margin-bottom:12px">
          <div style="display:inline-block;background:linear-gradient(135deg,#10b981,#059669);padding:11px;border-radius:50%;margin-bottom:8px;box-shadow:0 6px 20px rgba(16,185,129,.45)">
            <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2">
              <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
              <polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline>
              <line x1="12" y1="22.08" x2="12" y2="12"></line>
            </svg>
          </div>
          <h2 style="margin:0 0 4px 0;font-size:21px;font-weight:800;letter-spacing:-.3px;background:linear-gradient(135deg,#059669,#10b981);-webkit-background-clip:text;-webkit-text-fill-color:transparent">
            Uploading Consignment
          </h2>
          <div style="font-size:12px;opacity:.7;font-weight:700;color:#065f46">Transfer #${transferId}</div>
        </div>

        <div id="progressStatus" style="margin-bottom:12px;font-size:13px;opacity:.95;text-align:center;font-weight:700;min-height:16px;color:#065f46">
          🚀 Initializing…
        </div>

        <div style="background:#d1d5db;border-radius:10px;height:28px;overflow:hidden;margin-bottom:12px;box-shadow:inset 0 2px 5px rgba(0,0,0,.15)">
          <div id="progressBar" style="
            background: linear-gradient(90deg,#10b981 0%,#059669 50%,#047857 100%);
            height: 100%; width: 0%; transition: width .5s cubic-bezier(.4,0,.2,1);
            display:flex;align-items:center;justify-content:center;font-weight:800;font-size:12px;animation: glow 2s infinite;color:white;text-shadow: 0 1px 4px rgba(0,0,0,.2)
          ">
            <div style="position:absolute;top:0;left:-100%;width:100%;height:100%;background:linear-gradient(90deg,transparent,rgba(255,255,255,.4),transparent);animation:shimmer 1.5s infinite"></div>
          </div>
        </div>

        <div id="statsBar" style="display:flex;justify-content:space-around;margin-bottom:10px;padding:8px;background:rgba(16,185,129,.09);border-radius:8px;border:1px solid rgba(16,185,129,.28)">
          <div style="text-align:center"><div id="completedCount" style="font-size:18px;font-weight:800;color:#10b981">0</div><div style="font-size:10px;opacity:.7;font-weight:700;color:#065f46">Done</div></div>
          <div style="text-align:center"><div id="totalCount" style="font-size:18px;font-weight:800;color:#3b82f6">-</div><div style="font-size:10px;opacity:.7;font-weight:700;color:#1d4ed8">Total</div></div>
          <div style="text-align:center"><div id="speedIndicator" style="font-size:18px;font-weight:800;color:#10b981">⚡</div><div style="font-size:10px;opacity:.7;font-weight:700;color:#065f46">Status</div></div>
        </div>

        <div id="productList" style="
          background:#f0fdf4;border-radius:10px;padding:10px;max-height:170px;overflow-y:auto;margin-bottom:12px;border:1px solid #d1fae5;display:flex;flex-direction:column-reverse
        ">
          <div style="opacity:.55;text-align:center;font-size:12px;padding:12px;color:#9ca3af">⏳ Waiting…</div>
        </div>

        <button id="closeModalBtn" onclick="closeSimpleProgressModal()" style="
          background:linear-gradient(135deg,#10b981,#047857);color:white;border:none;padding:11px 24px;border-radius:10px;
          font-weight:800;cursor:pointer;font-size:14px;display:none;width:100%;transition:all .2s ease;box-shadow:0 6px 16px rgba(16,185,129,.5)
        ">✓ Done — View Transfer</button>
      </div>
    </div>
  `;
  $('body').append(modalHTML).hide().fadeIn(120);
  connectModalSSE(transferId, sessionId);
}

function connectModalSSE(transferId, sessionId) {
  if (modalEventSource) { try { modalEventSource.close(); } catch(e){} modalEventSource=null; }

  const url = `/modules/consignments/api/consignment-upload-progress.php?transfer_id=${transferId}&session_id=${sessionId}`;
  modalEventSource = new EventSource(url);

  modalEventSource.addEventListener('connected', (ev) => {
    try {
      const d = JSON.parse(ev.data);
      $('#totalCount').text(d.total_items || 0);
      updateProgressModal('📡 Connected — preparing items…');
    } catch(_) {}
  });

  modalEventSource.addEventListener('progress', (ev) => {
    try {
      const d = JSON.parse(ev.data);
      const pct = d.progress_percentage || 0;
      updateProgressBar(pct);
      $('#completedCount').text(d.completed_products || 0);
      $('#totalCount').text(d.total_products || 0);

      // nice, succinct status line
      let line = d.current_operation || 'Working…';
      if (d.status === 'connecting') line = '🔌 Connecting to Lightspeed…';
      if (d.status === 'created')    line = '📦 Consignment created — adding products…';
      if (d.status === 'adding_products') line = `⚙️ Adding products (${d.completed_products}/${d.total_products})…`;
      if (d.status === 'updating_state')  line = '📝 Finalising consignment state…';
      updateProgressModal(line);

      // show a few recent products (without spamming)
      if (Array.isArray(d.recent_products)) {
        d.recent_products.forEach(p => {
          // message shows success/failed icon
          const icon = p.status === 'failed' ? '❌' : '✅';
          addProductToList(`${icon} ${p.name} • ${p.sku || ''}`);
        });
      }
    } catch (e) {
      console.error('SSE progress parse error', e);
    }
  });

  modalEventSource.addEventListener('finished', (ev) => {
    try {
      const d = JSON.parse(ev.data);
      updateProgressBar(100);
      $('#speedIndicator').text(d.success ? '✅' : '⚠️');
      updateProgressModal(d.success ? '🎉 Upload complete!' : '⚠️ Upload finished with errors');
      $('#closeModalBtn').show();
      modalEventSource.close();
      modalEventSource = null;
    } catch (e) {
      console.error('SSE finish parse error', e);
    }
  });

  modalEventSource.addEventListener('error', (ev) => {
    updateProgressModal('❌ Connection error — attempting to recover…');
    try { modalEventSource.close(); } catch(e){}
    modalEventSource = null;
    setTimeout(() => connectModalSSE(transferId, sessionId), 1500);
  });
}

  
function updateProgressBar(percent) {
  const p = Math.max(0, Math.min(100, Math.round(percent)));
  $('#progressBar').css('width', p + '%');
  $('#progressBar').text(p >= 8 ? (p + '%') : '');
}

function updateProgressModal(message) {
  $('#progressStatus').text(message);
}

  
function addProductToList(label) {
  const $pl = $('#productList');
  if ($pl.text().includes('Waiting…')) $pl.empty();
  const item = `
    <div class="product-item" style="
      background:#fff;padding:8px 10px;border-radius:8px;margin-bottom:8px;font-size:12px;
      display:flex;align-items:center;gap:10px;border-left:3px solid #10b981;box-shadow:0 2px 4px rgba(16,185,129,.1);border:1px solid #d1fae5">
      <div style="width:16px;height:16px;background:linear-gradient(135deg,#10b981,#059669);border-radius:50%;
        display:flex;align-items:center;justify-content:center;flex-shrink:0;box-shadow:0 2px 6px rgba(16,185,129,.35)">
        <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="3"><polyline points="20 6 9 17 4 12"></polyline></svg>
      </div>
      <div style="flex:1;font-weight:700;color:#1f2937;line-height:1.35">${label}</div>
    </div>`;
  $pl.prepend(item);
}
  
  window.closeSimpleProgressModal = function() {
    // Redirect to HOME PAGE
    window.location.href = '/';
    
    // Fade out during page transition (smooth visual effect)
    $('#simpleProgressModal').fadeOut(800);
  };

  // ============================================================================
  // GLOBAL EXPORTS (for onclick handlers and inline event handlers)
  // ============================================================================

  window.isNumberKey = isNumberKey;
  window.validateCountedQty = validateCountedQty;
  window.markReadyForDelivery = markReadyForDelivery;
  window.openLabelPrintDialog = openLabelPrintDialog;
  window.selectAllVisible = selectAllVisible;
  window.clearSelection = clearSelection;
  window.addSelectedToCurrentTransfer = addSelectedToCurrentTransfer;
  window.addSelectedToAllTransfers = addSelectedToAllTransfers;
  window.addSelectedToOutletTransfers = addSelectedToOutletTransfers;
  window.addSelectedToSimilarTransfers = addSelectedToSimilarTransfers;
  window.addProductToTransfer = addProductToTransfer;
  window.submitTransfer = submitTransfer; // 🔧 CRITICAL FIX: Export submitTransfer function
  window.autoFillAllQuantities = autoFillAllQuantities; // 🎯 NEW: Auto-fill function
  

})();

// === pack-hotfix.js ===
window.PACK = window.PACK || {};

// ---- Optional: a tiny SSE manager to avoid multiple open streams ----
(function(ns){
  ns.sse = ns.sse || { es: null };
  ns.sse.open = function(url, handlers = {}) {
    if (this.es) { try { this.es.close(); } catch(e){} }
    this.es = new EventSource(url);
    if (handlers.onmessage) this.es.onmessage = handlers.onmessage;
    if (handlers.onopen) this.es.onopen = handlers.onopen;
    if (handlers.onerror) this.es.onerror = handlers.onerror;
    return this.es;
  };
  ns.sse.close = function() {
    if (this.es) { try { this.es.close(); } catch(e){}; this.es = null; }
  };
})(window.PACK);

// ---- Global helpers used by inline HTML attributes ----
(function(){
  function isNumberKey(evt) {
    const e = evt || window.event;
    const k = e.key;

    // allow control keys
    if (['Backspace','Delete','ArrowLeft','ArrowRight','Tab','Enter'].includes(k)) return true;

    // one decimal point only
    if (k === '.') {
      const el = e.target;
      if (el && String(el.value).includes('.')) { e.preventDefault(); return false; }
      return true;
    }

    // digits only
    if (!/^[0-9]$/.test(k)) { e.preventDefault(); return false; }
    return true;
  }

  function validateCountedQty(el) {
    if (!el) return;
    let raw = (el.value ?? '').trim();
    if (raw === '') return;

    let n = Number(raw.replace(/[^0-9.]/g, ''));
    if (!Number.isFinite(n)) n = 0;

    const min = Number(el.min ?? 0);
    const maxAttr = el.max ?? el.getAttribute('data-max');
    const max = Number(maxAttr ?? Number.MAX_SAFE_INTEGER);

    if (n < min) n = min;
    if (n > max) n = max;
    el.value = String(n);

    // optional: update a diff cell if present
    const row = el.closest('tr');
    const planned = Number(el.dataset.planned ?? el.getAttribute('data-planned') ?? 0);
    const diffEl = row ? row.querySelector('[data-role="qty-diff"]') : null;
    if (diffEl) {
      const diff = n - planned;
      diffEl.textContent = (Number.isFinite(diff) ? diff.toFixed(2) : '0');
      diffEl.classList.toggle('text-danger', diff < 0);
      diffEl.classList.toggle('text-success', diff >= 0);
    }
  }

  function autoFillAllQuantities() {
    const inputs = document.querySelectorAll('.js-counted-qty, input[name^="counted_qty"]');
    inputs.forEach((el) => {
      const fallback = el.dataset.planned ?? el.dataset.requested ?? el.placeholder ?? '0';
      el.value = String(fallback);
      validateCountedQty(el);
    });
  }

  // expose for inline HTML (onkeypress/oninput/onclick)
  window.isNumberKey = isNumberKey;
  window.validateCountedQty = validateCountedQty;
  window.autoFillAllQuantities = autoFillAllQuantities;

  // progressive enhancement: also wire by class (so you can remove inline attrs later)
  document.addEventListener('keypress', (e) => {
    if (e.target && (e.target.matches('.js-counted-qty') || e.target.name?.startsWith('counted_qty'))) {
      isNumberKey(e);
    }
  });
  document.addEventListener('input', (e) => {
    if (e.target && (e.target.matches('.js-counted-qty') || e.target.name?.startsWith('counted_qty'))) {
      validateCountedQty(e.target);
    }
  });
})();

