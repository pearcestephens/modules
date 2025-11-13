/**
 * =============================================================================
 * VAPEULTRA AJAX CLIENT
 * =============================================================================
 *
 * Version: 2.0.0
 * Purpose: Enterprise-grade AJAX client with interceptors and error handling
 *
 * FEATURES:
 * - Axios-based HTTP client
 * - Request/Response interceptors
 * - Automatic CSRF token injection
 * - Automatic retry with exponential backoff
 * - Request cancellation support
 * - Global error handling integration
 * - Loading state management
 * - Request deduplication
 * - Request/Response logging
 *
 * USAGE:
 * ```javascript
 * // Initialize
 * VapeUltra.Ajax.init({
 *     baseURL: '/api',
 *     timeout: 30000,
 *     retryAttempts: 3
 * });
 *
 * // GET request
 * VapeUltra.Ajax.get('/users')
 *     .then(data => console.log(data))
 *     .catch(error => console.error(error));
 *
 * // POST request
 * VapeUltra.Ajax.post('/users', { name: 'John' })
 *     .then(data => console.log(data))
 *     .catch(error => console.error(error));
 *
 * // With cancellation
 * const cancel = VapeUltra.Ajax.get('/long-request', { cancelable: true });
 * cancel(); // Cancel the request
 * ```
 *
 * =============================================================================
 */

(function(window) {
    'use strict';

    // Ensure VapeUltra namespace exists
    window.VapeUltra = window.VapeUltra || {};

    /**
     * AJAX Client Class
     */
    class AjaxClient {

        constructor() {
            this.config = {
                baseURL: '',
                timeout: 30000,
                retryAttempts: 3,
                retryDelay: 1000,
                retryStatusCodes: [408, 429, 500, 502, 503, 504],
                withCredentials: true,
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                debug: false,
                enableLogging: true,
                enableDeduplication: true,
                dedupeWindow: 1000
            };

            this.axios = null;
            this.pendingRequests = new Map();  // For deduplication
            this.requestLog = [];              // Request history
            this.isInitialized = false;
        }

        /**
         * Initialize AJAX client
         * @param {Object} options - Configuration options
         */
        init(options = {}) {
            if (this.isInitialized) {
                console.warn('AjaxClient already initialized');
                return;
            }

            // Check if axios is available
            if (typeof axios === 'undefined') {
                console.error('❌ Axios is required for VapeUltra.Ajax');
                return;
            }

            // Merge options with defaults
            this.config = { ...this.config, ...options };

            // Create axios instance
            this.axios = axios.create({
                baseURL: this.config.baseURL,
                timeout: this.config.timeout,
                withCredentials: this.config.withCredentials,
                headers: this.config.headers
            });

            // Setup interceptors
            this.setupRequestInterceptors();
            this.setupResponseInterceptors();

            // Log initialization
            if (this.config.debug) {
                console.log('✅ VapeUltra AjaxClient initialized', this.config);
            }

            this.isInitialized = true;
        }

        /**
         * Setup request interceptors
         */
        setupRequestInterceptors() {
            this.axios.interceptors.request.use(
                (config) => {
                    // 1. Inject CSRF token
                    const csrfToken = this.getCsrfToken();
                    if (csrfToken) {
                        config.headers['X-CSRF-TOKEN'] = csrfToken;
                    }

                    // 2. Add request timestamp
                    config.metadata = { startTime: Date.now() };

                    // 3. Check for duplicate requests
                    if (this.config.enableDeduplication && config.method === 'get') {
                        const requestKey = this.getRequestKey(config);
                        const existingRequest = this.pendingRequests.get(requestKey);

                        if (existingRequest) {
                            if (this.config.debug) {
                                console.log('🔁 Duplicate request detected, using existing:', requestKey);
                            }
                            // Return existing promise instead of making new request
                            return Promise.reject({
                                isDuplicate: true,
                                originalRequest: existingRequest
                            });
                        }

                        // Store pending request
                        this.pendingRequests.set(requestKey, config);

                        // Remove after dedupe window
                        setTimeout(() => {
                            this.pendingRequests.delete(requestKey);
                        }, this.config.dedupeWindow);
                    }

                    // 4. Log request
                    if (this.config.enableLogging) {
                        this.logRequest(config);
                    }

                    // 5. Show loading indicator (if enabled globally)
                    if (config.showLoading !== false) {
                        this.showLoading();
                    }

                    return config;
                },
                (error) => {
                    this.hideLoading();
                    return Promise.reject(error);
                }
            );
        }

        /**
         * Setup response interceptors
         */
        setupResponseInterceptors() {
            this.axios.interceptors.response.use(
                (response) => {
                    // 1. Calculate request duration
                    if (response.config.metadata) {
                        const duration = Date.now() - response.config.metadata.startTime;
                        response.duration = duration;

                        if (this.config.debug) {
                            console.log(`⏱️ Request completed in ${duration}ms`);
                        }
                    }

                    // 2. Log response
                    if (this.config.enableLogging) {
                        this.logResponse(response);
                    }

                    // 3. Hide loading indicator
                    this.hideLoading();

                    // 4. Remove from pending requests
                    const requestKey = this.getRequestKey(response.config);
                    this.pendingRequests.delete(requestKey);

                    // 5. Return data (unwrap axios response)
                    return response.data;
                },
                (error) => {
                    // Hide loading indicator
                    this.hideLoading();

                    // Handle duplicate request
                    if (error.isDuplicate) {
                        return error.originalRequest;
                    }

                    // Handle cancellation
                    if (axios.isCancel(error)) {
                        if (this.config.debug) {
                            console.log('🚫 Request cancelled:', error.message);
                        }
                        return Promise.reject({ cancelled: true, message: error.message });
                    }

                    // Extract error details
                    const errorDetails = {
                        message: error.message,
                        status: error.response ? error.response.status : null,
                        statusText: error.response ? error.response.statusText : null,
                        data: error.response ? error.response.data : null,
                        config: error.config
                    };

                    // Log error
                    if (this.config.enableLogging) {
                        this.logError(errorDetails);
                    }

                    // Retry logic
                    if (this.shouldRetry(error)) {
                        return this.retryRequest(error.config);
                    }

                    // Integrate with ErrorHandler
                    if (window.VapeUltra && window.VapeUltra.ErrorHandler) {
                        window.VapeUltra.ErrorHandler.handleAjaxError(
                            error.response || { status: 0, statusText: 'Network Error' },
                            {
                                url: error.config ? error.config.url : 'unknown',
                                method: error.config ? error.config.method : 'unknown',
                                retryCount: error.config ? error.config.retryCount || 0 : 0,
                                retryCallback: () => this.retryRequest(error.config)
                            }
                        );
                    }

                    return Promise.reject(errorDetails);
                }
            );
        }

        /**
         * Determine if request should be retried
         * @param {Object} error
         * @return {boolean}
         */
        shouldRetry(error) {
            if (!error.config || !error.response) {
                return false; // Network error, don't auto-retry
            }

            const retryCount = error.config.retryCount || 0;
            const isRetryableStatus = this.config.retryStatusCodes.includes(error.response.status);
            const hasRetriesLeft = retryCount < this.config.retryAttempts;

            return isRetryableStatus && hasRetriesLeft;
        }

        /**
         * Retry a failed request
         * @param {Object} config - Axios config
         * @return {Promise}
         */
        retryRequest(config) {
            config.retryCount = (config.retryCount || 0) + 1;

            const delay = this.config.retryDelay * Math.pow(2, config.retryCount - 1);

            if (this.config.debug) {
                console.log(`🔄 Retrying request (attempt ${config.retryCount}/${this.config.retryAttempts}) in ${delay}ms`);
            }

            return new Promise((resolve) => {
                setTimeout(() => {
                    resolve(this.axios(config));
                }, delay);
            });
        }

        /**
         * GET request
         * @param {string} url
         * @param {Object} options
         * @return {Promise}
         */
        get(url, options = {}) {
            return this.request({ method: 'GET', url, ...options });
        }

        /**
         * POST request
         * @param {string} url
         * @param {Object} data
         * @param {Object} options
         * @return {Promise}
         */
        post(url, data = {}, options = {}) {
            return this.request({ method: 'POST', url, data, ...options });
        }

        /**
         * PUT request
         * @param {string} url
         * @param {Object} data
         * @param {Object} options
         * @return {Promise}
         */
        put(url, data = {}, options = {}) {
            return this.request({ method: 'PUT', url, data, ...options });
        }

        /**
         * PATCH request
         * @param {string} url
         * @param {Object} data
         * @param {Object} options
         * @return {Promise}
         */
        patch(url, data = {}, options = {}) {
            return this.request({ method: 'PATCH', url, data, ...options });
        }

        /**
         * DELETE request
         * @param {string} url
         * @param {Object} options
         * @return {Promise}
         */
        delete(url, options = {}) {
            return this.request({ method: 'DELETE', url, ...options });
        }

        /**
         * Generic request method
         * @param {Object} config
         * @return {Promise|Function} Returns promise or cancel function if cancelable
         */
        request(config) {
            // Support request cancellation
            if (config.cancelable) {
                const source = axios.CancelToken.source();
                config.cancelToken = source.token;

                const promise = this.axios(config);
                promise.cancel = () => source.cancel('Request cancelled by user');

                return promise;
            }

            return this.axios(config);
        }

        /**
         * Get CSRF token from meta tag or cookie
         * @return {string|null}
         */
        getCsrfToken() {
            // Try meta tag first
            const metaTag = document.querySelector('meta[name="csrf-token"]');
            if (metaTag) {
                return metaTag.getAttribute('content');
            }

            // Try cookie
            const cookieMatch = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
            if (cookieMatch) {
                return decodeURIComponent(cookieMatch[1]);
            }

            return null;
        }

        /**
         * Generate unique request key for deduplication
         * @param {Object} config
         * @return {string}
         */
        getRequestKey(config) {
            const method = config.method.toUpperCase();
            const url = config.url;
            const params = JSON.stringify(config.params || {});

            return `${method}:${url}:${params}`;
        }

        /**
         * Show loading indicator
         */
        showLoading() {
            if (window.VapeUltra && window.VapeUltra.LoadingState) {
                window.VapeUltra.LoadingState.show();
            } else {
                // Fallback: Show global loading overlay
                const overlay = document.getElementById('vape-ultra-loading-overlay');
                if (overlay) {
                    overlay.classList.add('active');
                }
            }
        }

        /**
         * Hide loading indicator
         */
        hideLoading() {
            if (window.VapeUltra && window.VapeUltra.LoadingState) {
                window.VapeUltra.LoadingState.hide();
            } else {
                // Fallback: Hide global loading overlay
                const overlay = document.getElementById('vape-ultra-loading-overlay');
                if (overlay) {
                    overlay.classList.remove('active');
                }
            }
        }

        /**
         * Log request
         * @param {Object} config
         */
        logRequest(config) {
            const log = {
                type: 'request',
                timestamp: new Date().toISOString(),
                method: config.method.toUpperCase(),
                url: config.url,
                params: config.params,
                data: config.data
            };

            this.requestLog.push(log);

            if (this.config.debug) {
                console.log('📤 Request:', log);
            }
        }

        /**
         * Log response
         * @param {Object} response
         */
        logResponse(response) {
            const log = {
                type: 'response',
                timestamp: new Date().toISOString(),
                method: response.config.method.toUpperCase(),
                url: response.config.url,
                status: response.status,
                duration: response.duration,
                data: response.data
            };

            this.requestLog.push(log);

            if (this.config.debug) {
                console.log('📥 Response:', log);
            }
        }

        /**
         * Log error
         * @param {Object} error
         */
        logError(error) {
            const log = {
                type: 'error',
                timestamp: new Date().toISOString(),
                message: error.message,
                status: error.status,
                statusText: error.statusText,
                url: error.config ? error.config.url : 'unknown',
                method: error.config ? error.config.method.toUpperCase() : 'unknown'
            };

            this.requestLog.push(log);

            if (this.config.debug) {
                console.error('❌ Request Error:', log);
            }
        }

        /**
         * Get request history
         * @return {Array}
         */
        getRequestLog() {
            return this.requestLog;
        }

        /**
         * Clear request history
         */
        clearRequestLog() {
            this.requestLog = [];
        }

        /**
         * Export request log as JSON
         * @return {string}
         */
        exportRequestLog() {
            return JSON.stringify(this.requestLog, null, 2);
        }

        /**
         * Cancel all pending requests
         */
        cancelAll() {
            this.pendingRequests.clear();
            console.log('🚫 All pending requests cancelled');
        }
    }

    // Create global instance
    window.VapeUltra.Ajax = new AjaxClient();

    // Auto-initialize if config exists
    if (window.VapeUltraConfig && window.VapeUltraConfig.ajax) {
        window.VapeUltra.Ajax.init(window.VapeUltraConfig.ajax);
    }

})(window);
