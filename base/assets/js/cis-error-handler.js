/**
 * CIS Error Handler - Professional JavaScript Error Management System
 *
 * Provides beautiful, professional error handling with:
 * - Multiple severity levels (error, warning, info, success)
 * - Copy to clipboard functionality
 * - Stack trace viewing
 * - AJAX/JSON error interception
 * - Global error catching
 * - User-friendly messages
 * - Developer details
 *
 * @package CIS\Base\Assets
 * @version 1.0.0
 * @created 2025-11-04
 */

(function(window, document) {
    'use strict';

    // Error Handler Class
    class CISErrorHandler {
        constructor() {
            this.errors = [];
            this.maxErrors = 50;
            this.isInitialized = false;
            this.config = {
                showStackTrace: true,
                autoShow: true,
                position: 'top-right', // top-right, top-left, bottom-right, bottom-left, center
                sound: false,
                persistErrors: true,
                debugMode: false
            };

            this.init();
        }

        init() {
            if (this.isInitialized) return;

            // Inject CSS
            this.injectStyles();

            // Create error container when DOM is ready
            if (document.body) {
                this.createContainer();
            } else {
                document.addEventListener('DOMContentLoaded', () => this.createContainer());
            }

            // Setup global error handlers
            this.setupGlobalHandlers();

            // Intercept fetch/AJAX
            this.interceptAjax();

            this.isInitialized = true;
            console.log('✅ CIS Error Handler initialized');
        }

        injectStyles() {
            const style = document.createElement('style');
            style.id = 'cis-error-handler-styles';
            style.textContent = `
                /* CIS Error Handler Styles */
                .cis-error-container {
                    position: fixed;
                    z-index: 999999;
                    max-width: 500px;
                    pointer-events: none;
                }

                .cis-error-container.top-right {
                    top: 20px;
                    right: 20px;
                }

                .cis-error-container.top-left {
                    top: 20px;
                    left: 20px;
                }

                .cis-error-container.bottom-right {
                    bottom: 20px;
                    right: 20px;
                }

                .cis-error-container.bottom-left {
                    bottom: 20px;
                    left: 20px;
                }

                .cis-error-container.center {
                    top: 50%;
                    left: 50%;
                    transform: translate(-50%, -50%);
                }

                .cis-error-alert {
                    pointer-events: auto;
                    margin-bottom: 15px;
                    border-radius: 8px;
                    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
                    overflow: hidden;
                    animation: slideInRight 0.3s ease-out;
                    max-width: 500px;
                    background: white;
                    border: 2px solid;
                }

                @keyframes slideInRight {
                    from {
                        transform: translateX(400px);
                        opacity: 0;
                    }
                    to {
                        transform: translateX(0);
                        opacity: 1;
                    }
                }

                .cis-error-alert.closing {
                    animation: slideOutRight 0.3s ease-in forwards;
                }

                @keyframes slideOutRight {
                    to {
                        transform: translateX(400px);
                        opacity: 0;
                    }
                }

                /* Severity Colors */
                .cis-error-alert.error {
                    border-color: #dc3545;
                }

                .cis-error-alert.warning {
                    border-color: #ffc107;
                }

                .cis-error-alert.info {
                    border-color: #17a2b8;
                }

                .cis-error-alert.success {
                    border-color: #28a745;
                }

                /* Header */
                .cis-error-header {
                    padding: 12px 16px;
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    font-weight: 600;
                    color: white;
                }

                .cis-error-alert.error .cis-error-header {
                    background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
                }

                .cis-error-alert.warning .cis-error-header {
                    background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%);
                    color: #212529;
                }

                .cis-error-alert.info .cis-error-header {
                    background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
                }

                .cis-error-alert.success .cis-error-header {
                    background: linear-gradient(135deg, #28a745 0%, #218838 100%);
                }

                .cis-error-header-left {
                    display: flex;
                    align-items: center;
                    gap: 10px;
                }

                .cis-error-icon {
                    font-size: 20px;
                }

                .cis-error-title {
                    font-size: 15px;
                    margin: 0;
                }

                .cis-error-close {
                    background: none;
                    border: none;
                    color: inherit;
                    font-size: 20px;
                    cursor: pointer;
                    padding: 0;
                    width: 24px;
                    height: 24px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    border-radius: 4px;
                    transition: background 0.2s;
                }

                .cis-error-close:hover {
                    background: rgba(0, 0, 0, 0.1);
                }

                /* Body */
                .cis-error-body {
                    padding: 16px;
                    font-size: 14px;
                    line-height: 1.5;
                    color: #212529;
                }

                .cis-error-message {
                    margin-bottom: 12px;
                    font-weight: 500;
                }

                .cis-error-details {
                    background: #f8f9fa;
                    border: 1px solid #dee2e6;
                    border-radius: 4px;
                    padding: 10px;
                    font-family: 'Courier New', monospace;
                    font-size: 12px;
                    color: #495057;
                    overflow-x: auto;
                    white-space: pre-wrap;
                    word-break: break-all;
                }

                .cis-error-stack {
                    margin-top: 12px;
                    max-height: 200px;
                    overflow-y: auto;
                }

                .cis-error-stack-toggle {
                    color: #007bff;
                    cursor: pointer;
                    text-decoration: underline;
                    font-size: 13px;
                    margin-top: 8px;
                    display: inline-block;
                }

                .cis-error-stack-toggle:hover {
                    color: #0056b3;
                }

                /* Footer */
                .cis-error-footer {
                    padding: 10px 16px;
                    background: #f8f9fa;
                    border-top: 1px solid #dee2e6;
                    display: flex;
                    gap: 8px;
                    justify-content: flex-end;
                }

                .cis-error-btn {
                    padding: 6px 12px;
                    border: none;
                    border-radius: 4px;
                    font-size: 13px;
                    font-weight: 500;
                    cursor: pointer;
                    transition: all 0.2s;
                    display: flex;
                    align-items: center;
                    gap: 6px;
                }

                .cis-error-btn-copy {
                    background: #007bff;
                    color: white;
                }

                .cis-error-btn-copy:hover {
                    background: #0056b3;
                }

                .cis-error-btn-copy.copied {
                    background: #28a745;
                }

                .cis-error-btn-dismiss {
                    background: #6c757d;
                    color: white;
                }

                .cis-error-btn-dismiss:hover {
                    background: #5a6268;
                }

                /* Timestamp */
                .cis-error-timestamp {
                    font-size: 11px;
                    color: rgba(255, 255, 255, 0.8);
                    margin-left: 8px;
                }

                .cis-error-alert.warning .cis-error-timestamp {
                    color: rgba(0, 0, 0, 0.5);
                }

                /* Mobile responsive */
                @media (max-width: 576px) {
                    .cis-error-container {
                        left: 10px !important;
                        right: 10px !important;
                        max-width: none;
                        transform: none !important;
                    }

                    .cis-error-alert {
                        max-width: none;
                    }
                }
            `;
            document.head.appendChild(style);
        }

        createContainer() {
            // Ensure we have a body element
            if (!document.body) {
                console.warn('CIS Error Handler: document.body not ready yet');
                return;
            }

            // Don't create container if it already exists
            if (document.getElementById('cis-error-container')) {
                return;
            }

            const container = document.createElement('div');
            container.id = 'cis-error-container';
            container.className = `cis-error-container ${this.config.position}`;
            document.body.appendChild(container);
        }

        setupGlobalHandlers() {
            // Catch unhandled errors
            window.addEventListener('error', (event) => {
                this.handleError({
                    message: event.message,
                    filename: event.filename,
                    lineno: event.lineno,
                    colno: event.colno,
                    error: event.error
                }, 'error');

                // Prevent default browser error console
                if (this.config.debugMode) {
                    event.preventDefault();
                }
            });

            // Catch unhandled promise rejections
            window.addEventListener('unhandledrejection', (event) => {
                this.handleError({
                    message: 'Unhandled Promise Rejection',
                    reason: event.reason
                }, 'error');

                if (this.config.debugMode) {
                    event.preventDefault();
                }
            });
        }

        interceptAjax() {
            // Intercept fetch
            const originalFetch = window.fetch;
            const self = this;

            window.fetch = function(...args) {
                return originalFetch.apply(this, args)
                    .then(response => {
                        // Check for HTTP errors
                        if (!response.ok) {
                            self.handleAjaxError(response, args[0]);
                        }
                        return response;
                    })
                    .catch(error => {
                        self.handleAjaxError(error, args[0]);
                        throw error;
                    });
            };

            // Intercept jQuery AJAX if jQuery is available
            if (window.jQuery) {
                jQuery(document).ajaxError(function(event, jqXHR, settings, thrownError) {
                    self.handleAjaxError({
                        status: jqXHR.status,
                        statusText: jqXHR.statusText,
                        responseText: jqXHR.responseText
                    }, settings.url);
                });
            }
        }

        handleError(error, severity = 'error') {
            const errorData = {
                id: this.generateId(),
                severity: severity,
                message: error.message || 'An unknown error occurred',
                details: this.formatErrorDetails(error),
                stack: error.error?.stack || error.stack || null,
                timestamp: new Date(),
                source: error.filename || 'Unknown'
            };

            this.errors.push(errorData);

            // Limit stored errors
            if (this.errors.length > this.maxErrors) {
                this.errors.shift();
            }

            if (this.config.autoShow) {
                this.showError(errorData);
            }

            // Log to console in debug mode
            if (this.config.debugMode) {
                console.error('[CIS Error Handler]', errorData);
            }
        }

        handleAjaxError(response, url) {
            let message = 'AJAX Request Failed';
            let details = '';

            if (typeof response === 'object') {
                if (response.status) {
                    message = `HTTP ${response.status}: ${response.statusText || 'Request Failed'}`;
                    details = `URL: ${url}\n`;

                    if (response.responseText) {
                        try {
                            const json = JSON.parse(response.responseText);
                            details += `Response: ${JSON.stringify(json, null, 2)}`;
                        } catch (e) {
                            details += `Response: ${response.responseText.substring(0, 500)}`;
                        }
                    }
                } else if (response.message) {
                    message = response.message;
                    details = response.toString();
                }
            }

            this.show({
                message: message,
                details: details,
                severity: 'error'
            });
        }

        formatErrorDetails(error) {
            let details = '';

            if (error.filename) {
                details += `File: ${error.filename}\n`;
            }
            if (error.lineno) {
                details += `Line: ${error.lineno}`;
                if (error.colno) {
                    details += `:${error.colno}`;
                }
                details += '\n';
            }
            if (error.reason) {
                details += `Reason: ${JSON.stringify(error.reason, null, 2)}\n`;
            }

            return details.trim();
        }

        showError(errorData) {
            let container = document.getElementById('cis-error-container');

            // If container doesn't exist yet, create it
            if (!container) {
                this.createContainer();
                container = document.getElementById('cis-error-container');
            }

            // Still no container? Can't show error (probably in tests or no body)
            if (!container) {
                console.error('[CIS Error Handler] Cannot show error - no container', errorData);
                return;
            }

            const alert = this.createAlert(errorData);
            container.appendChild(alert);

            // Auto-dismiss after delay (unless error severity)
            if (errorData.severity !== 'error') {
                const delay = errorData.severity === 'success' ? 3000 : 5000;
                setTimeout(() => this.dismissAlert(alert), delay);
            }
        }

        createAlert(errorData) {
            const alert = document.createElement('div');
            alert.className = `cis-error-alert ${errorData.severity}`;
            alert.setAttribute('data-error-id', errorData.id);

            const icons = {
                error: '⛔',
                warning: '⚠️',
                info: 'ℹ️',
                success: '✅'
            };

            const titles = {
                error: 'Error Occurred',
                warning: 'Warning',
                info: 'Information',
                success: 'Success'
            };

            let html = `
                <div class="cis-error-header">
                    <div class="cis-error-header-left">
                        <span class="cis-error-icon">${icons[errorData.severity]}</span>
                        <div>
                            <div class="cis-error-title">${titles[errorData.severity]}</div>
                            <span class="cis-error-timestamp">${this.formatTime(errorData.timestamp)}</span>
                        </div>
                    </div>
                    <button class="cis-error-close" onclick="window.CIS.ErrorHandler.dismissAlert(this.closest('.cis-error-alert'))">
                        ×
                    </button>
                </div>
                <div class="cis-error-body">
                    <div class="cis-error-message">${this.escapeHtml(errorData.message)}</div>
            `;

            if (errorData.details) {
                html += `<div class="cis-error-details">${this.escapeHtml(errorData.details)}</div>`;
            }

            if (errorData.stack && this.config.showStackTrace) {
                html += `
                    <a class="cis-error-stack-toggle" onclick="event.preventDefault(); this.nextElementSibling.style.display = this.nextElementSibling.style.display === 'none' ? 'block' : 'none'">
                        Toggle Stack Trace
                    </a>
                    <div class="cis-error-details cis-error-stack" style="display: none;">${this.escapeHtml(errorData.stack)}</div>
                `;
            }

            html += `
                </div>
                <div class="cis-error-footer">
                    <button class="cis-error-btn cis-error-btn-copy" onclick="window.CIS.ErrorHandler.copyError('${errorData.id}')">
                        📋 Copy Details
                    </button>
                    <button class="cis-error-btn cis-error-btn-dismiss" onclick="window.CIS.ErrorHandler.dismissAlert(this.closest('.cis-error-alert'))">
                        Dismiss
                    </button>
                </div>
            `;

            alert.innerHTML = html;
            return alert;
        }

        dismissAlert(alert) {
            if (typeof alert === 'string') {
                alert = document.querySelector(`[data-error-id="${alert}"]`);
            }

            if (!alert) return;

            alert.classList.add('closing');
            setTimeout(() => {
                alert.remove();
            }, 300);
        }

        copyError(errorId) {
            const error = this.errors.find(e => e.id === errorId);
            if (!error) return;

            const text = this.formatErrorForClipboard(error);

            if (navigator.clipboard) {
                navigator.clipboard.writeText(text).then(() => {
                    this.showCopySuccess(errorId);
                });
            } else {
                // Fallback for older browsers
                const textarea = document.createElement('textarea');
                textarea.value = text;
                textarea.style.position = 'fixed';
                textarea.style.opacity = '0';
                document.body.appendChild(textarea);
                textarea.select();
                document.execCommand('copy');
                document.body.removeChild(textarea);
                this.showCopySuccess(errorId);
            }
        }

        showCopySuccess(errorId) {
            const btn = document.querySelector(`[data-error-id="${errorId}"] .cis-error-btn-copy`);
            if (btn) {
                const originalText = btn.innerHTML;
                btn.classList.add('copied');
                btn.innerHTML = '✓ Copied!';
                setTimeout(() => {
                    btn.classList.remove('copied');
                    btn.innerHTML = originalText;
                }, 2000);
            }
        }

        formatErrorForClipboard(error) {
            let text = `=== CIS ERROR REPORT ===\n`;
            text += `Severity: ${error.severity.toUpperCase()}\n`;
            text += `Time: ${error.timestamp.toLocaleString()}\n`;
            text += `Message: ${error.message}\n`;

            if (error.details) {
                text += `\n--- Details ---\n${error.details}\n`;
            }

            if (error.stack) {
                text += `\n--- Stack Trace ---\n${error.stack}\n`;
            }

            text += `\n--- Environment ---\n`;
            text += `User Agent: ${navigator.userAgent}\n`;
            text += `URL: ${window.location.href}\n`;
            text += `Viewport: ${window.innerWidth}x${window.innerHeight}\n`;

            return text;
        }

        // Public API
        show(options) {
            const errorData = {
                id: this.generateId(),
                severity: options.severity || 'info',
                message: options.message || 'No message provided',
                details: options.details || '',
                stack: options.stack || null,
                timestamp: new Date(),
                source: options.source || 'Manual'
            };

            this.errors.push(errorData);
            this.showError(errorData);

            return errorData.id;
        }

        error(message, details) {
            return this.show({ message, details, severity: 'error' });
        }

        warning(message, details) {
            return this.show({ message, details, severity: 'warning' });
        }

        info(message, details) {
            return this.show({ message, details, severity: 'info' });
        }

        success(message, details) {
            return this.show({ message, details, severity: 'success' });
        }

        clearAll() {
            const container = document.getElementById('cis-error-container');
            if (container) {
                container.innerHTML = '';
            }
            this.errors = [];
        }

        getErrors() {
            return this.errors;
        }

        configure(options) {
            this.config = Object.assign(this.config, options);
        }

        // Utilities
        generateId() {
            return 'err_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
        }

        formatTime(date) {
            return date.toLocaleTimeString('en-NZ', {
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit'
            });
        }

        escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    }

    // Initialize global instance
    window.CIS = window.CIS || {};
    window.CIS.ErrorHandler = new CISErrorHandler();

    console.log('✅ CIS Error Handler loaded');

})(window, document);
