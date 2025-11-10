/**
 * Global AJAX Error Handler
 * 
 * Automatically catches AJAX errors and displays them nicely
 * Uses toasts if available, falls back to custom modal
 */

(function() {
    'use strict';
    
    // Check if we have Bootstrap toast or other toast library
    const hasBootstrapToast = typeof bootstrap !== 'undefined' && bootstrap.Toast;
    const hasToastr = typeof toastr !== 'undefined';
    
    /**
     * Show error notification
     */
    window.showAjaxError = function(title, message, details = null) {
        if (hasToastr) {
            // Use toastr if available
            toastr.error(message, title, {
                timeOut: 10000,
                extendedTimeOut: 5000,
                closeButton: true,
                progressBar: true,
                positionClass: 'toast-top-right'
            });
            
            // If there are details, show copy button
            if (details) {
                setTimeout(() => showErrorDetailsModal(title, message, details), 100);
            }
        } else if (hasBootstrapToast) {
            // Use Bootstrap toast
            showBootstrapToast(title, message, details);
        } else {
            // Fallback to custom modal
            showErrorModal(title, message, details);
        }
    };
    
    /**
     * Bootstrap Toast implementation
     */
    function showBootstrapToast(title, message, details) {
        const toastId = 'ajaxErrorToast_' + Date.now();
        const detailsBtn = details ? `<button class="btn btn-sm btn-light ms-2" onclick="showAjaxErrorDetails('${escapeHtml(title)}', '${escapeHtml(message)}', '${escapeHtml(details)}')">Details</button>` : '';
        
        const toastHtml = `
            <div class="toast align-items-center text-white bg-danger border-0" role="alert" id="${toastId}" data-bs-autohide="true" data-bs-delay="10000">
                <div class="d-flex">
                    <div class="toast-body">
                        <strong>${escapeHtml(title)}</strong><br>
                        ${escapeHtml(message)}
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
                ${detailsBtn ? `<div class="px-3 pb-2">${detailsBtn}</div>` : ''}
            </div>
        `;
        
        // Create or get toast container
        let container = document.getElementById('toastContainer');
        if (!container) {
            container = document.createElement('div');
            container.id = 'toastContainer';
            container.className = 'toast-container position-fixed top-0 end-0 p-3';
            container.style.zIndex = '9999';
            document.body.appendChild(container);
        }
        
        // Add toast
        container.insertAdjacentHTML('beforeend', toastHtml);
        const toastElement = document.getElementById(toastId);
        const toast = new bootstrap.Toast(toastElement);
        toast.show();
        
        // Remove after hiding
        toastElement.addEventListener('hidden.bs.toast', function() {
            toastElement.remove();
        });
    }
    
    /**
     * Fallback error modal
     */
    function showErrorModal(title, message, details) {
        const modalId = 'ajaxErrorModal';
        
        // Remove existing modal if any
        const existing = document.getElementById(modalId);
        if (existing) {
            existing.remove();
        }
        
        const detailsSection = details ? `
            <div class="alert alert-warning mt-3">
                <strong>Error Details:</strong>
                <pre class="mb-0 mt-2" style="max-height: 200px; overflow-y: auto; font-size: 0.85em;">${escapeHtml(details)}</pre>
                <button class="btn btn-sm btn-outline-secondary mt-2" onclick="copyErrorDetails('${escapeHtml(details)}')">
                    📋 Copy Error Details
                </button>
            </div>
        ` : '';
        
        const modalHtml = `
            <div class="modal fade" id="${modalId}" tabindex="-1" style="z-index: 99999;">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header bg-danger text-white">
                            <h5 class="modal-title">
                                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                                ${escapeHtml(title)}
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-dismiss="modal" onclick="closeAjaxErrorModal()"></button>
                        </div>
                        <div class="modal-body">
                            <p class="mb-0">${escapeHtml(message)}</p>
                            ${detailsSection}
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" onclick="closeAjaxErrorModal()">Close</button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        document.body.insertAdjacentHTML('beforeend', modalHtml);
        
        // Show modal
        const modal = document.getElementById(modalId);
        modal.style.display = 'block';
        modal.classList.add('show');
        
        // Add backdrop
        const backdrop = document.createElement('div');
        backdrop.className = 'modal-backdrop fade show';
        backdrop.id = 'ajaxErrorBackdrop';
        document.body.appendChild(backdrop);
        document.body.classList.add('modal-open');
    }
    
    /**
     * Close error modal
     */
    window.closeAjaxErrorModal = function() {
        const modal = document.getElementById('ajaxErrorModal');
        const backdrop = document.getElementById('ajaxErrorBackdrop');
        
        if (modal) {
            modal.classList.remove('show');
            setTimeout(() => modal.remove(), 150);
        }
        if (backdrop) {
            backdrop.classList.remove('show');
            setTimeout(() => backdrop.remove(), 150);
        }
        document.body.classList.remove('modal-open');
    };
    
    /**
     * Show error details modal
     */
    window.showAjaxErrorDetails = function(title, message, details) {
        showErrorModal(title, message, details);
    };
    
    /**
     * Copy error details to clipboard
     */
    window.copyErrorDetails = function(details) {
        if (navigator.clipboard) {
            navigator.clipboard.writeText(details).then(() => {
                alert('Error details copied to clipboard!');
            });
        } else {
            // Fallback for older browsers
            const textarea = document.createElement('textarea');
            textarea.value = details;
            textarea.style.position = 'fixed';
            textarea.style.opacity = '0';
            document.body.appendChild(textarea);
            textarea.select();
            document.execCommand('copy');
            document.body.removeChild(textarea);
            alert('Error details copied to clipboard!');
        }
    };
    
    /**
     * Escape HTML
     */
    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    /**
     * Intercept fetch requests
     */
    if (window.fetch) {
        const originalFetch = window.fetch;
        window.fetch = function(...args) {
            return originalFetch.apply(this, args)
                .then(response => {
                    // Clone response so we can read it
                    const clonedResponse = response.clone();
                    
                    // Check if error response
                    if (!response.ok && response.headers.get('Content-Type')?.includes('application/json')) {
                        return clonedResponse.json().then(data => {
                            if (data.error) {
                                showAjaxError(
                                    data.error_title || 'Request Failed',
                                    data.error,
                                    data.error_details || null
                                );
                            }
                            return response; // Return original response
                        }).catch(() => response);
                    }
                    
                    return response;
                })
                .catch(error => {
                    showAjaxError('Network Error', error.message);
                    throw error;
                });
        };
    }
    
    /**
     * Intercept jQuery AJAX if jQuery is available
     */
    if (typeof jQuery !== 'undefined') {
        jQuery(document).ajaxError(function(event, jqXHR, ajaxSettings, thrownError) {
            // Check if response is JSON with error
            try {
                const response = JSON.parse(jqXHR.responseText);
                if (response.error) {
                    showAjaxError(
                        response.error_title || 'AJAX Error',
                        response.error,
                        response.error_details || jqXHR.responseText
                    );
                }
            } catch (e) {
                // Not JSON, show generic error
                if (jqXHR.status !== 200) {
                    showAjaxError(
                        'AJAX Error',
                        thrownError || 'An error occurred during the request',
                        `Status: ${jqXHR.status}\n${jqXHR.responseText}`
                    );
                }
            }
        });
    }
    
    console.log('✅ AJAX Error Handler initialized');
})();
