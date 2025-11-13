/**
 * =============================================================================
 * VAPEULTRA GLOBAL ERROR HANDLER
 * =============================================================================
 *
 * Version: 2.0.0
 * Purpose: Enterprise-grade error handling for production applications
 *
 * FEATURES:
 * - Catches all uncaught JavaScript errors
 * - Handles AJAX failures globally
 * - Captures unhandled promise rejections
 * - Logs errors to backend
 * - Shows user-friendly error messages
 * - Developer debug mode
 * - Error grouping & deduplication
 * - Automatic retry for transient failures
 *
 * USAGE:
 * ```javascript
 * // Initialize on page load
 * VapeUltra.ErrorHandler.init({
 *     debug: false,
 *     logToServer: true,
 *     showToUser: true,
 *     endpoint: '/api/log-error'
 * });
 *
 * // Manual error reporting
 * try {
 *     // risky code
 * } catch (error) {
 *     VapeUltra.ErrorHandler.catch(error, { context: 'user action' });
 * }
 * ```
 *
 * =============================================================================
 */

(function(window) {
    'use strict';

    // Ensure VapeUltra namespace exists
    window.VapeUltra = window.VapeUltra || {};

    /**
     * Error Handler Class
     */
    class ErrorHandler {

        constructor() {
            this.config = {
                debug: false,
                logToServer: true,
                showToUser: true,
                endpoint: '/api/log-error',
                maxErrors: 50,              // Max errors to store in memory
                dedupeWindow: 5000,         // Dedupe errors within 5s
                retryAttempts: 3,           // Retry failed log requests
                retryDelay: 1000,           // Delay between retries (ms)
                userAgent: navigator.userAgent,
                url: window.location.href
            };

            this.errorLog = [];              // In-memory error log
            this.errorCounts = new Map();    // Error deduplication
            this.isInitialized = false;
        }

        /**
         * Initialize error handler
         * @param {Object} options - Configuration options
         */
        init(options = {}) {
            if (this.isInitialized) {
                console.warn('ErrorHandler already initialized');
                return;
            }

            // Merge options with defaults
            this.config = { ...this.config, ...options };

            // Attach global error listeners
            this.attachListeners();

            // Log initialization
            if (this.config.debug) {
                console.log('✅ VapeUltra ErrorHandler initialized', this.config);
            }

            this.isInitialized = true;
        }

        /**
         * Attach global error listeners
         */
        attachListeners() {
            // Catch uncaught errors
            window.addEventListener('error', (event) => {
                this.handleError({
                    type: 'javascript',
                    message: event.message,
                    filename: event.filename,
                    lineno: event.lineno,
                    colno: event.colno,
                    error: event.error,
                    stack: event.error ? event.error.stack : null
                });
            });

            // Catch unhandled promise rejections
            window.addEventListener('unhandledrejection', (event) => {
                this.handleError({
                    type: 'promise',
                    message: event.reason ? event.reason.message : 'Unhandled promise rejection',
                    error: event.reason,
                    stack: event.reason ? event.reason.stack : null
                });
            });

            // Intercept console.error
            const originalConsoleError = console.error;
            console.error = (...args) => {
                this.handleError({
                    type: 'console',
                    message: args.join(' '),
                    args: args
                });
                originalConsoleError.apply(console, args);
            };
        }

        /**
         * Handle an error
         * @param {Object} errorData - Error information
         * @param {Object} context - Additional context
         */
        handleError(errorData, context = {}) {
            // Build error object
            const error = {
                id: this.generateErrorId(),
                timestamp: new Date().toISOString(),
                type: errorData.type || 'unknown',
                message: errorData.message || 'Unknown error',
                filename: errorData.filename || context.filename || null,
                lineno: errorData.lineno || context.lineno || null,
                colno: errorData.colno || context.colno || null,
                stack: errorData.stack || null,
                url: this.config.url,
                userAgent: this.config.userAgent,
                context: context,
                severity: this.determineSeverity(errorData)
            };

            // Check if this is a duplicate error
            if (this.isDuplicate(error)) {
                if (this.config.debug) {
                    console.log('🔁 Duplicate error suppressed:', error.message);
                }
                return;
            }

            // Store in memory
            this.storeError(error);

            // Log to console in debug mode
            if (this.config.debug) {
                console.error('🚨 Error caught:', error);
            }

            // Log to server
            if (this.config.logToServer) {
                this.logToServer(error);
            }

            // Show to user
            if (this.config.showToUser && error.severity !== 'low') {
                this.showErrorToUser(error);
            }
        }

        /**
         * Catch and handle errors manually
         * @param {Error|string} error - Error object or message
         * @param {Object} context - Additional context
         */
        catch(error, context = {}) {
            const errorData = {
                type: 'manual',
                message: error.message || error.toString(),
                error: error instanceof Error ? error : null,
                stack: error instanceof Error ? error.stack : null
            };

            this.handleError(errorData, context);
        }

        /**
         * Handle AJAX errors
         * @param {Object} xhr - XMLHttpRequest object
         * @param {Object} context - Request context
         */
        handleAjaxError(xhr, context = {}) {
            const error = {
                type: 'ajax',
                message: `AJAX Error: ${xhr.status} ${xhr.statusText}`,
                status: xhr.status,
                statusText: xhr.statusText,
                responseText: xhr.responseText,
                url: context.url || xhr.responseURL,
                method: context.method || 'GET'
            };

            this.handleError(error, context);

            // Specific handling by status code
            switch (xhr.status) {
                case 401: // Unauthorized
                    this.handle401();
                    break;
                case 403: // Forbidden
                    this.handle403();
                    break;
                case 404: // Not Found
                    this.handle404(context);
                    break;
                case 422: // Validation Error
                    this.handle422(xhr);
                    break;
                case 500: // Server Error
                case 502: // Bad Gateway
                case 503: // Service Unavailable
                    this.handle5xx(xhr, context);
                    break;
            }
        }

        /**
         * Handle 401 Unauthorized
         */
        handle401() {
            if (typeof VapeUltra.Modal !== 'undefined') {
                VapeUltra.Modal.alert({
                    title: 'Session Expired',
                    message: 'Your session has expired. Please log in again.',
                    type: 'warning',
                    onClose: () => {
                        window.location.href = '/login?redirect=' + encodeURIComponent(window.location.pathname);
                    }
                });
            } else {
                // Fallback
                alert('Your session has expired. Redirecting to login...');
                window.location.href = '/login?redirect=' + encodeURIComponent(window.location.pathname);
            }
        }

        /**
         * Handle 403 Forbidden
         */
        handle403() {
            if (typeof VapeUltra.Toast !== 'undefined') {
                VapeUltra.Toast.error('You do not have permission to perform this action.', {
                    duration: 5000
                });
            } else {
                alert('Access denied: You do not have permission to perform this action.');
            }
        }

        /**
         * Handle 404 Not Found
         */
        handle404(context) {
            if (this.config.debug) {
                console.warn('404 Not Found:', context);
            }

            if (typeof VapeUltra.Toast !== 'undefined') {
                VapeUltra.Toast.warning('The requested resource was not found.', {
                    duration: 4000
                });
            }
        }

        /**
         * Handle 422 Validation Error
         */
        handle422(xhr) {
            try {
                const response = JSON.parse(xhr.responseText);
                const errors = response.errors || {};

                // If using FormValidator, pass errors to it
                if (typeof VapeUltra.FormValidator !== 'undefined' && VapeUltra.FormValidator.activeForm) {
                    VapeUltra.FormValidator.activeForm.setErrors(errors);
                } else {
                    // Show generic validation error
                    const errorMessages = Object.values(errors).flat().join('<br>');

                    if (typeof VapeUltra.Toast !== 'undefined') {
                        VapeUltra.Toast.error(errorMessages, {
                            duration: 5000
                        });
                    }
                }
            } catch (e) {
                console.error('Failed to parse validation errors:', e);
            }
        }

        /**
         * Handle 5xx Server Errors
         */
        handle5xx(xhr, context) {
            const shouldRetry = context.retryCount < this.config.retryAttempts;

            if (shouldRetry) {
                // Attempt retry with exponential backoff
                const retryDelay = this.config.retryDelay * Math.pow(2, context.retryCount || 0);

                if (this.config.debug) {
                    console.log(`🔄 Retrying request in ${retryDelay}ms (attempt ${(context.retryCount || 0) + 1}/${this.config.retryAttempts})`);
                }

                setTimeout(() => {
                    if (context.retryCallback) {
                        context.retryCallback();
                    }
                }, retryDelay);
            } else {
                // Max retries reached
                if (typeof VapeUltra.Modal !== 'undefined') {
                    VapeUltra.Modal.alert({
                        title: 'Server Error',
                        message: 'A server error occurred. Please try again later or contact support if the problem persists.',
                        type: 'error'
                    });
                } else {
                    alert('A server error occurred. Please try again later.');
                }
            }
        }

        /**
         * Determine error severity
         * @param {Object} errorData
         * @return {string} Severity level
         */
        determineSeverity(errorData) {
            // Critical errors
            if (errorData.message && errorData.message.toLowerCase().includes('critical')) {
                return 'critical';
            }

            // High severity errors
            if (errorData.type === 'ajax' && errorData.status >= 500) {
                return 'high';
            }

            if (errorData.message && (
                errorData.message.toLowerCase().includes('security') ||
                errorData.message.toLowerCase().includes('authentication') ||
                errorData.message.toLowerCase().includes('unauthorized')
            )) {
                return 'high';
            }

            // Medium severity (default)
            if (errorData.type === 'javascript' || errorData.type === 'promise') {
                return 'medium';
            }

            // Low severity
            return 'low';
        }

        /**
         * Check if error is duplicate
         * @param {Object} error
         * @return {boolean}
         */
        isDuplicate(error) {
            const errorKey = `${error.type}:${error.message}:${error.filename}:${error.lineno}`;
            const now = Date.now();
            const lastOccurrence = this.errorCounts.get(errorKey);

            if (lastOccurrence && (now - lastOccurrence) < this.config.dedupeWindow) {
                return true; // Duplicate within dedupe window
            }

            this.errorCounts.set(errorKey, now);
            return false;
        }

        /**
         * Store error in memory
         * @param {Object} error
         */
        storeError(error) {
            this.errorLog.push(error);

            // Keep only last N errors
            if (this.errorLog.length > this.config.maxErrors) {
                this.errorLog.shift();
            }
        }

        /**
         * Log error to server
         * @param {Object} error
         * @param {number} attempt
         */
        logToServer(error, attempt = 1) {
            // Use Axios if available, otherwise fallback to fetch
            const sendRequest = () => {
                if (typeof axios !== 'undefined') {
                    return axios.post(this.config.endpoint, error, {
                        headers: {
                            'Content-Type': 'application/json',
                            'X-Error-Logger': 'VapeUltra'
                        }
                    });
                } else {
                    return fetch(this.config.endpoint, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-Error-Logger': 'VapeUltra'
                        },
                        body: JSON.stringify(error)
                    });
                }
            };

            sendRequest()
                .then(() => {
                    if (this.config.debug) {
                        console.log('✅ Error logged to server');
                    }
                })
                .catch((err) => {
                    console.error('❌ Failed to log error to server:', err);

                    // Retry if attempts remain
                    if (attempt < this.config.retryAttempts) {
                        setTimeout(() => {
                            this.logToServer(error, attempt + 1);
                        }, this.config.retryDelay * attempt);
                    }
                });
        }

        /**
         * Show error to user
         * @param {Object} error
         */
        showErrorToUser(error) {
            // Use VapeUltra Toast if available
            if (typeof VapeUltra !== 'undefined' && typeof VapeUltra.Toast !== 'undefined') {
                const message = this.getUserFriendlyMessage(error);

                VapeUltra.Toast.error(message, {
                    duration: 5000,
                    closable: true
                });
            } else {
                // Fallback to browser alert (not ideal)
                const message = this.config.debug
                    ? `Error: ${error.message}`
                    : 'An error occurred. Please try again or contact support.';

                console.error(message);
            }
        }

        /**
         * Get user-friendly error message
         * @param {Object} error
         * @return {string}
         */
        getUserFriendlyMessage(error) {
            if (this.config.debug) {
                return error.message;
            }

            // Map technical errors to user-friendly messages
            switch (error.type) {
                case 'ajax':
                    return 'A network error occurred. Please check your connection and try again.';
                case 'promise':
                    return 'An operation failed. Please try again.';
                case 'javascript':
                    return 'An unexpected error occurred. Please refresh the page.';
                default:
                    return 'Something went wrong. Please try again or contact support.';
            }
        }

        /**
         * Generate unique error ID
         * @return {string}
         */
        generateErrorId() {
            return `err_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`;
        }

        /**
         * Get all errors from memory
         * @return {Array}
         */
        getErrors() {
            return this.errorLog;
        }

        /**
         * Clear error log
         */
        clearErrors() {
            this.errorLog = [];
            this.errorCounts.clear();
        }

        /**
         * Export errors as JSON
         * @return {string}
         */
        exportErrors() {
            return JSON.stringify(this.errorLog, null, 2);
        }
    }

    // Create global instance
    window.VapeUltra.ErrorHandler = new ErrorHandler();

    // Auto-initialize if config exists
    if (window.VapeUltraConfig && window.VapeUltraConfig.errorHandler) {
        window.VapeUltra.ErrorHandler.init(window.VapeUltraConfig.errorHandler);
    }

})(window);
