/**
 * Enterprise-Level AJAX Communication Manager
 * 
 * Provides consistent, logged, error-handled AJAX communication
 * for all Consignments module operations
 * 
 * @package CIS\Consignments\JS
 * @version 1.0.0
 */

(function(window) {
  'use strict';

  /**
   * AJAX Manager Class
   */
  class AjaxManager {
    constructor() {
      this.pendingRequests = new Map();
      this.requestLog = [];
      this.maxLogEntries = 100;
      this.defaultTimeout = 30000; // 30 seconds
      this.retryAttempts = 0;
      this.maxRetries = 2;
    }

    /**
     * Generate unique request ID
     */
    generateRequestId() {
      return 'req_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
    }

    /**
     * Log request/response
     */
    log(requestId, type, data) {
      const entry = {
        requestId,
        type, // 'request', 'success', 'error', 'retry'
        timestamp: new Date().toISOString(),
        data
      };

      this.requestLog.push(entry);

      // Keep log size manageable
      if (this.requestLog.length > this.maxLogEntries) {
        this.requestLog.shift();
      }

      // Console logging in development
      if (window.location.hostname === 'localhost' || window.location.hostname.includes('dev')) {
        console.log(`[AJAX ${type.toUpperCase()}]`, entry);
      }
    }

    /**
     * Main request method
     * 
     * @param {Object} options - Request configuration
     * @returns {Promise}
     */
    request(options) {
      return new Promise((resolve, reject) => {
        const requestId = this.generateRequestId();
        
        // Default configuration
        const config = {
          url: '/modules/consignments/api/api.php',
          method: 'POST',
          action: null, // Required
          data: {},
          timeout: this.defaultTimeout,
          showLoader: true,
          showSuccess: false,
          showError: true,
          retryOnError: false,
          ...options
        };

        // Validate required fields
        if (!config.action) {
          const error = new Error('Action parameter is required');
          this.log(requestId, 'error', { error: error.message, config });
          reject(error);
          return;
        }

        // Prepare request data
        const requestData = {
          action: config.action,
          ...config.data,
          _request_id: requestId,
          _timestamp: Date.now()
        };

        // Log request
        this.log(requestId, 'request', {
          url: config.url,
          action: config.action,
          method: config.method,
          data: requestData
        });

        // Show loader if requested
        if (config.showLoader) {
          this.showLoader();
        }

        // Store pending request
        const abortController = new AbortController();
        this.pendingRequests.set(requestId, abortController);

        // Make AJAX request
        $.ajax({
          url: config.url,
          method: config.method,
          contentType: 'application/json',
          data: JSON.stringify(requestData),
          timeout: config.timeout,
          beforeSend: (xhr) => {
            xhr.setRequestHeader('X-Request-ID', requestId);
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            // 🔒 CSRF Protection (from risk register)
            if (window.CSRF_TOKEN) {
              xhr.setRequestHeader('X-CSRF-Token', window.CSRF_TOKEN);
            }
          },
          success: (response) => {
            this.pendingRequests.delete(requestId);
            
            if (config.showLoader) {
              this.hideLoader();
            }

            // Validate response structure
            if (typeof response !== 'object') {
              const error = new Error('Invalid response format: expected object');
              this.log(requestId, 'error', { error: error.message, response });
              
              if (config.showError) {
                this.showError('Invalid server response');
              }
              
              reject(error);
              return;
            }

            // Check success flag
            if (response.success === true) {
              this.log(requestId, 'success', response);
              
              if (config.showSuccess && response.message) {
                this.showSuccess(response.message);
              }
              
              resolve(response);
            } else {
              // API returned success=false
              const errorMsg = response.error?.message || 'Request failed';
              const errorCode = response.error?.code || 'UNKNOWN_ERROR';
              
              this.log(requestId, 'error', {
                errorCode,
                errorMessage: errorMsg,
                errorDetails: response.error?.details,
                response
              });
              
              if (config.showError) {
                this.showError(errorMsg);
              }
              
              const error = new Error(errorMsg);
              error.code = errorCode;
              error.response = response;
              reject(error);
            }
          },
          error: (xhr, status, error) => {
            this.pendingRequests.delete(requestId);
            
            if (config.showLoader) {
              this.hideLoader();
            }

            const errorData = {
              status: xhr.status,
              statusText: xhr.statusText,
              error: error,
              errorType: status, // 'timeout', 'error', 'abort', 'parsererror'
              responseText: xhr.responseText
            };

            this.log(requestId, 'error', errorData);

            // Retry logic for network errors
            if (config.retryOnError && this.retryAttempts < this.maxRetries && status === 'timeout') {
              this.retryAttempts++;
              this.log(requestId, 'retry', { attempt: this.retryAttempts });
              
              setTimeout(() => {
                this.request(config).then(resolve).catch(reject);
              }, 1000 * this.retryAttempts);
              return;
            }

            this.retryAttempts = 0;

            // User-friendly error messages
            let userMessage = 'An error occurred';
            
            if (status === 'timeout') {
              userMessage = 'Request timed out. Please try again.';
            } else if (status === 'abort') {
              userMessage = 'Request was cancelled';
            } else if (xhr.status === 0) {
              userMessage = 'Network error. Please check your connection.';
            } else if (xhr.status === 404) {
              userMessage = 'API endpoint not found';
            } else if (xhr.status === 500) {
              userMessage = 'Server error. Please try again later.';
            } else if (xhr.status === 401) {
              userMessage = 'Unauthorized. Please log in again.';
            } else if (xhr.status === 403) {
              userMessage = 'Access forbidden';
            }

            if (config.showError) {
              this.showError(userMessage);
            }

            const err = new Error(userMessage);
            err.xhr = xhr;
            err.status = status;
            reject(err);
          }
        });
      });
    }

    /**
     * Abort a specific request
     */
    abort(requestId) {
      const controller = this.pendingRequests.get(requestId);
      if (controller) {
        controller.abort();
        this.pendingRequests.delete(requestId);
        this.log(requestId, 'abort', {});
      }
    }

    /**
     * Abort all pending requests
     */
    abortAll() {
      this.pendingRequests.forEach((controller, requestId) => {
        controller.abort();
        this.log(requestId, 'abort', { reason: 'abort_all' });
      });
      this.pendingRequests.clear();
    }

    /**
     * Get request log
     */
    getLog() {
      return this.requestLog;
    }

    /**
     * Clear request log
     */
    clearLog() {
      this.requestLog = [];
    }

    /**
     * UI Feedback Methods
     */
    showLoader() {
      if ($('#ajax-loader').length === 0) {
        $('body').append(`
          <div id="ajax-loader" style="position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.3); z-index:9998; display:flex; align-items:center; justify-content:center;">
            <div style="background:white; padding:20px 30px; border-radius:8px; box-shadow:0 4px 20px rgba(0,0,0,0.3);">
              <div class="spinner-border text-primary" role="status" style="width:3rem; height:3rem;">
                <span class="sr-only">Loading...</span>
              </div>
              <div class="mt-2 text-center font-weight-bold">Processing...</div>
            </div>
          </div>
        `);
      } else {
        $('#ajax-loader').show();
      }
    }

    hideLoader() {
      $('#ajax-loader').fadeOut(200);
    }

    showSuccess(message) {
      this.showToast(message, 'success');
    }

    showError(message) {
      this.showToast(message, 'error');
    }

    showWarning(message) {
      this.showToast(message, 'warning');
    }

    showInfo(message) {
      this.showToast(message, 'info');
    }

    showToast(message, type = 'info') {
      const colors = {
        success: 'bg-success',
        error: 'bg-danger',
        warning: 'bg-warning',
        info: 'bg-info'
      };

      const icons = {
        success: 'fa-check-circle',
        error: 'fa-exclamation-triangle',
        warning: 'fa-exclamation-circle',
        info: 'fa-info-circle'
      };

      if ($('#ajax-toast-container').length === 0) {
        $('body').append('<div id="ajax-toast-container" style="position:fixed; top:70px; right:20px; z-index:9999; width:320px;"></div>');
      }

      const toastId = 'toast-' + Date.now();
      const html = `
        <div id="${toastId}" class="alert ${colors[type]} text-white alert-dismissible fade show mb-2" role="alert" style="box-shadow:0 4px 12px rgba(0,0,0,0.2);">
          <i class="fa ${icons[type]} mr-2"></i>
          <strong>${message}</strong>
          <button type="button" class="close text-white" data-dismiss="alert" aria-label="Close" style="opacity:0.8;">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
      `;

      $('#ajax-toast-container').append(html);

      // Auto-remove after 5 seconds
      setTimeout(() => {
        $('#' + toastId).fadeOut(300, function() {
          $(this).remove();
        });
      }, 5000);
    }
  }

  // Create global instance
  window.ConsignmentsAjax = new AjaxManager();

  // Expose jQuery plugin style wrapper for convenience
  $.consignmentsAjax = function(options) {
    return window.ConsignmentsAjax.request(options);
  };

})(window);
