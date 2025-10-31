/**
 * CIS Global Error Handler - JavaScript Edition
 * 
 * Catches all AJAX/fetch errors and displays them beautifully
 * Works with: fetch(), XMLHttpRequest, jQuery $.ajax()
 * 
 * @package CIS\Shared\JS
 * @version 2.0.0
 */

(function() {
    'use strict';
    
    // Error display state
    let errorModalVisible = false;
    
    /**
     * Initialize global error handlers
     */
    function initErrorHandlers() {
        // Intercept fetch() calls
        interceptFetch();
        
        // Intercept XMLHttpRequest
        interceptXHR();
        
        // Intercept jQuery AJAX (if jQuery is loaded)
        if (window.jQuery) {
            interceptJQuery();
        }
        
        // Handle unhandled promise rejections
        window.addEventListener('unhandledrejection', function(event) {
            console.error('Unhandled promise rejection:', event.reason);
            if (event.reason && event.reason.message) {
                showErrorModal({
                    type: 'Unhandled Promise Rejection',
                    message: event.reason.message,
                    stack: event.reason.stack
                });
            }
        });
        
        console.log('✓ CIS Error Handler initialized');
    }
    
    /**
     * Intercept native fetch() API
     */
    function interceptFetch() {
        const originalFetch = window.fetch;
        
        window.fetch = function(...args) {
            return originalFetch.apply(this, args)
                .then(response => {
                    // Check for HTTP errors
                    if (!response.ok && response.status >= 500) {
                        return response.clone().json()
                            .then(data => {
                                handleAPIError(response, data);
                                return response;
                            })
                            .catch(() => {
                                // Not JSON, try text
                                return response.clone().text()
                                    .then(text => {
                                        handleAPIError(response, { message: text });
                                        return response;
                                    });
                            });
                    }
                    return response;
                })
                .catch(error => {
                    console.error('Fetch error:', error);
                    showErrorModal({
                        type: 'Network Error',
                        message: error.message || 'Failed to connect to server',
                        detail: 'Request: ' + (args[0] || 'unknown')
                    });
                    throw error;
                });
        };
    }
    
    /**
     * Intercept XMLHttpRequest
     */
    function interceptXHR() {
        const originalOpen = XMLHttpRequest.prototype.open;
        const originalSend = XMLHttpRequest.prototype.send;
        
        XMLHttpRequest.prototype.open = function(method, url, ...rest) {
            this._method = method;
            this._url = url;
            return originalOpen.apply(this, [method, url, ...rest]);
        };
        
        XMLHttpRequest.prototype.send = function(...args) {
            const xhr = this;
            
            // Store original handlers
            const originalError = xhr.onerror;
            const originalLoad = xhr.onload;
            
            // Error handler
            xhr.onerror = function(e) {
                showErrorModal({
                    type: 'XHR Error',
                    message: 'Request failed',
                    detail: `${xhr._method || 'GET'} ${xhr._url || 'unknown'}`
                });
                if (originalError) originalError.apply(this, arguments);
            };
            
            // Load handler (check for 500 errors)
            xhr.onload = function(e) {
                if (xhr.status >= 500) {
                    try {
                        const data = JSON.parse(xhr.responseText);
                        handleAPIError({ status: xhr.status, url: xhr._url }, data);
                    } catch (err) {
                        handleAPIError(
                            { status: xhr.status, url: xhr._url },
                            { message: xhr.responseText || 'Server error' }
                        );
                    }
                }
                if (originalLoad) originalLoad.apply(this, arguments);
            };
            
            return originalSend.apply(this, args);
        };
    }
    
    /**
     * Intercept jQuery AJAX
     */
    function interceptJQuery() {
        const $ = window.jQuery;
        
        // Global AJAX error handler
        $(document).ajaxError(function(event, jqXHR, settings, thrownError) {
            if (jqXHR.status >= 500) {
                try {
                    const data = typeof jqXHR.responseJSON === 'object' 
                        ? jqXHR.responseJSON 
                        : JSON.parse(jqXHR.responseText);
                    handleAPIError(jqXHR, data);
                } catch (e) {
                    handleAPIError(jqXHR, {
                        message: jqXHR.responseText || thrownError || 'Server error'
                    });
                }
            }
        });
    }
    
    /**
     * Handle API error responses
     */
    function handleAPIError(response, data) {
        console.error('API Error:', response, data);
        
        // Extract error details
        const errorData = {
            type: data.error?.type || 'Server Error',
            message: data.error?.message || data.message || 'An unexpected error occurred',
            code: response.status || data.error?.code || 500,
            url: response.url || response._url || window.location.href
        };
        
        // Add debug info if available
        if (data.debug) {
            errorData.debug = data.debug;
        }
        
        showErrorModal(errorData);
    }
    
    /**
     * Show beautiful error modal
     */
    function showErrorModal(error) {
        if (errorModalVisible) return; // Don't show multiple modals
        
        errorModalVisible = true;
        
        // Create modal HTML
        const modalHTML = `
            <div id="cis-error-modal" class="cis-error-modal">
                <div class="cis-error-overlay" onclick="closeErrorModal()"></div>
                <div class="cis-error-content">
                    <div class="cis-error-header">
                        <div class="cis-error-icon">🚨</div>
                        <div class="cis-error-title">
                            <strong>${escapeHtml(error.type || 'Error')}</strong>
                            ${error.code ? `<span class="cis-error-code">HTTP ${error.code}</span>` : ''}
                        </div>
                        <button class="cis-error-close" onclick="closeErrorModal()">×</button>
                    </div>
                    
                    <div class="cis-error-body">
                        <div class="cis-error-message">${escapeHtml(error.message)}</div>
                        
                        ${error.detail ? `
                            <div class="cis-error-detail">
                                <strong>Details:</strong> ${escapeHtml(error.detail)}
                            </div>
                        ` : ''}
                        
                        ${error.debug ? `
                            <div class="cis-error-debug">
                                <div class="cis-error-section-title">Debug Information</div>
                                
                                ${error.debug.file ? `
                                    <div class="cis-error-kv">
                                        <div>File:</div>
                                        <div class="cis-error-mono">${escapeHtml(error.debug.file)}:${error.debug.line || '?'}</div>
                                    </div>
                                ` : ''}
                                
                                ${error.debug.memory ? `
                                    <div class="cis-error-kv">
                                        <div>Memory:</div>
                                        <div>${escapeHtml(error.debug.memory.current)} (peak ${escapeHtml(error.debug.memory.peak)})</div>
                                    </div>
                                ` : ''}
                                
                                ${error.debug.trace && error.debug.trace.length ? `
                                    <div class="cis-error-trace-container">
                                        <strong>Stack Trace:</strong>
                                        <div class="cis-error-trace">
                                            ${error.debug.trace.map(t => `
                                                <div class="cis-error-trace-item">
                                                    <div><strong>#${t.index}</strong> <span class="cis-error-trace-file">${escapeHtml(t.file)}:${t.line}</span></div>
                                                    <div class="cis-error-trace-func">${escapeHtml(t.function)}</div>
                                                </div>
                                            `).join('')}
                                        </div>
                                    </div>
                                ` : ''}
                            </div>
                        ` : ''}
                        
                        ${error.stack ? `
                            <div class="cis-error-stack">
                                <strong>Stack Trace:</strong>
                                <pre>${escapeHtml(error.stack)}</pre>
                            </div>
                        ` : ''}
                    </div>
                    
                    <div class="cis-error-footer">
                        <button class="cis-error-btn cis-error-btn-primary" onclick="copyErrorToClipboard()">
                            📋 Copy Error
                        </button>
                        <button class="cis-error-btn cis-error-btn-secondary" onclick="closeErrorModal()">
                            Close
                        </button>
                    </div>
                </div>
            </div>
        `;
        
        // Inject styles if not already present
        if (!document.getElementById('cis-error-styles')) {
            injectStyles();
        }
        
        // Add modal to page
        const modalContainer = document.createElement('div');
        modalContainer.innerHTML = modalHTML;
        document.body.appendChild(modalContainer.firstElementChild);
        
        // Store error data for copying
        window._currentError = error;
        
        // Make close function global
        window.closeErrorModal = closeErrorModal;
        window.copyErrorToClipboard = copyErrorToClipboard;
    }
    
    /**
     * Close error modal
     */
    function closeErrorModal() {
        const modal = document.getElementById('cis-error-modal');
        if (modal) {
            modal.remove();
            errorModalVisible = false;
        }
    }
    
    /**
     * Copy error to clipboard
     */
    function copyErrorToClipboard() {
        const error = window._currentError;
        if (!error) return;
        
        let text = `=== CIS ERROR REPORT ===\n`;
        text += `Type: ${error.type}\n`;
        text += `Message: ${error.message}\n`;
        if (error.code) text += `Code: ${error.code}\n`;
        if (error.url) text += `URL: ${error.url}\n`;
        text += `Timestamp: ${new Date().toISOString()}\n`;
        
        if (error.debug) {
            text += `\n=== DEBUG INFO ===\n`;
            if (error.debug.file) text += `File: ${error.debug.file}:${error.debug.line}\n`;
            if (error.debug.memory) text += `Memory: ${error.debug.memory.current} (peak ${error.debug.memory.peak})\n`;
            
            if (error.debug.trace) {
                text += `\n=== STACK TRACE ===\n`;
                error.debug.trace.forEach(t => {
                    text += `#${t.index} ${t.file}:${t.line}\n`;
                    text += `   ${t.function}\n`;
                });
            }
        }
        
        if (error.stack) {
            text += `\n=== STACK ===\n${error.stack}\n`;
        }
        
        // Copy to clipboard
        const textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.style.position = 'fixed';
        textarea.style.left = '-9999px';
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        document.body.removeChild(textarea);
        
        // Show success message
        showCopySuccess();
    }
    
    /**
     * Show copy success notification
     */
    function showCopySuccess() {
        const notification = document.createElement('div');
        notification.className = 'cis-error-copy-success';
        notification.textContent = '✓ Copied to clipboard!';
        document.body.appendChild(notification);
        
        setTimeout(() => notification.classList.add('show'), 10);
        setTimeout(() => {
            notification.classList.remove('show');
            setTimeout(() => notification.remove(), 300);
        }, 2000);
    }
    
    /**
     * Inject CSS styles
     */
    function injectStyles() {
        const styles = document.createElement('style');
        styles.id = 'cis-error-styles';
        styles.textContent = `
            .cis-error-modal {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                z-index: 999999;
                display: flex;
                align-items: center;
                justify-content: center;
                animation: cis-error-fadein 0.2s;
            }
            
            .cis-error-overlay {
                position: absolute;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.7);
                backdrop-filter: blur(4px);
            }
            
            .cis-error-content {
                position: relative;
                background: #fff;
                border-radius: 12px;
                width: 90%;
                max-width: 800px;
                max-height: 90vh;
                overflow: hidden;
                box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
                display: flex;
                flex-direction: column;
                animation: cis-error-slideup 0.3s;
            }
            
            .cis-error-header {
                background: linear-gradient(135deg, #dc2626, #991b1b);
                color: #fff;
                padding: 20px 24px;
                display: flex;
                align-items: center;
                gap: 12px;
            }
            
            .cis-error-icon {
                font-size: 32px;
            }
            
            .cis-error-title {
                flex: 1;
            }
            
            .cis-error-title strong {
                display: block;
                font-size: 20px;
                margin-bottom: 4px;
            }
            
            .cis-error-code {
                display: inline-block;
                background: rgba(255, 255, 255, 0.2);
                padding: 2px 8px;
                border-radius: 4px;
                font-size: 12px;
                font-weight: 600;
            }
            
            .cis-error-close {
                background: transparent;
                border: none;
                color: #fff;
                font-size: 32px;
                cursor: pointer;
                padding: 0;
                width: 32px;
                height: 32px;
                display: flex;
                align-items: center;
                justify-content: center;
                opacity: 0.8;
                transition: opacity 0.2s;
            }
            
            .cis-error-close:hover {
                opacity: 1;
            }
            
            .cis-error-body {
                padding: 24px;
                overflow-y: auto;
                flex: 1;
            }
            
            .cis-error-message {
                font-size: 15px;
                line-height: 1.6;
                color: #374151;
                background: #fef2f2;
                padding: 16px;
                border-radius: 8px;
                border-left: 4px solid #dc2626;
                font-family: ui-monospace, monospace;
            }
            
            .cis-error-detail {
                margin-top: 16px;
                padding: 12px;
                background: #f3f4f6;
                border-radius: 6px;
                font-size: 13px;
            }
            
            .cis-error-debug {
                margin-top: 16px;
                padding: 16px;
                background: #f9fafb;
                border-radius: 8px;
                border: 1px solid #e5e7eb;
            }
            
            .cis-error-section-title {
                font-weight: 600;
                margin-bottom: 12px;
                color: #374151;
            }
            
            .cis-error-kv {
                display: grid;
                grid-template-columns: 120px 1fr;
                gap: 12px;
                margin-bottom: 8px;
                font-size: 13px;
            }
            
            .cis-error-kv > div:first-child {
                font-weight: 600;
                color: #6b7280;
            }
            
            .cis-error-mono {
                font-family: ui-monospace, monospace;
                background: #fff;
                padding: 6px 10px;
                border-radius: 4px;
                color: #dc2626;
            }
            
            .cis-error-trace-container {
                margin-top: 12px;
            }
            
            .cis-error-trace {
                background: #1f2937;
                color: #e5e7eb;
                padding: 16px;
                border-radius: 6px;
                max-height: 300px;
                overflow-y: auto;
                font-family: ui-monospace, monospace;
                font-size: 12px;
                margin-top: 8px;
            }
            
            .cis-error-trace-item {
                margin-bottom: 12px;
                padding-bottom: 12px;
                border-bottom: 1px solid #374151;
            }
            
            .cis-error-trace-item:last-child {
                border-bottom: none;
                margin-bottom: 0;
            }
            
            .cis-error-trace-file {
                color: #60a5fa;
            }
            
            .cis-error-trace-func {
                color: #fbbf24;
                opacity: 0.9;
                font-size: 11px;
                margin-top: 4px;
            }
            
            .cis-error-stack {
                margin-top: 16px;
            }
            
            .cis-error-stack pre {
                background: #1f2937;
                color: #e5e7eb;
                padding: 16px;
                border-radius: 6px;
                overflow-x: auto;
                font-size: 12px;
                margin-top: 8px;
            }
            
            .cis-error-footer {
                padding: 16px 24px;
                background: #f9fafb;
                border-top: 1px solid #e5e7eb;
                display: flex;
                gap: 12px;
                justify-content: flex-end;
            }
            
            .cis-error-btn {
                padding: 10px 20px;
                border: none;
                border-radius: 6px;
                font-size: 14px;
                font-weight: 500;
                cursor: pointer;
                transition: all 0.2s;
            }
            
            .cis-error-btn-primary {
                background: #3b82f6;
                color: #fff;
            }
            
            .cis-error-btn-primary:hover {
                background: #2563eb;
            }
            
            .cis-error-btn-secondary {
                background: #e5e7eb;
                color: #374151;
            }
            
            .cis-error-btn-secondary:hover {
                background: #d1d5db;
            }
            
            .cis-error-copy-success {
                position: fixed;
                top: 20px;
                right: 20px;
                background: #10b981;
                color: #fff;
                padding: 12px 20px;
                border-radius: 8px;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
                z-index: 9999999;
                opacity: 0;
                transform: translateY(-20px);
                transition: all 0.3s;
            }
            
            .cis-error-copy-success.show {
                opacity: 1;
                transform: translateY(0);
            }
            
            @keyframes cis-error-fadein {
                from { opacity: 0; }
                to { opacity: 1; }
            }
            
            @keyframes cis-error-slideup {
                from {
                    opacity: 0;
                    transform: translateY(40px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }
        `;
        document.head.appendChild(styles);
    }
    
    /**
     * Escape HTML for safe display
     */
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    // Auto-initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initErrorHandlers);
    } else {
        initErrorHandlers();
    }
    
    // Export for manual initialization if needed
    window.CISErrorHandler = {
        init: initErrorHandlers,
        showError: showErrorModal,
        closeError: closeErrorModal
    };
    
})();
