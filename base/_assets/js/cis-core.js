/**
 * CIS Core Utilities - Global JavaScript Library
 *
 * Provides common utilities that all CIS modules can inherit:
 * - AJAX helper with CSRF protection
 * - Format utilities (currency, date, number)
 * - Toast notifications
 * - Confirmation dialogs
 * - Local storage helpers
 * - Form utilities
 * - Validation helpers
 *
 * @package CIS\Base\Assets
 * @version 1.0.0
 * @created 2025-11-04
 */

(function(window, document) {
    'use strict';

    // CIS Core Class
    class CISCore {
        constructor() {
            this.config = {
                apiBase: '',
                csrfToken: window.CIS_CSRF || null,
                dateFormat: 'en-NZ',
                currency: 'NZD',
                currencySymbol: '$',
                debug: false
            };

            this.init();
        }

        init() {
            // Auto-detect CSRF token from meta tag or global var
            this.detectCSRFToken();

            // Setup AJAX defaults
            this.setupAjaxDefaults();

            console.log('✅ CIS Core Utilities loaded');
        }

        detectCSRFToken() {
            // Try meta tag first
            const metaTag = document.querySelector('meta[name="csrf-token"]');
            if (metaTag) {
                this.config.csrfToken = metaTag.getAttribute('content');
            }

            // Try global variable
            if (!this.config.csrfToken && window.CIS_CSRF) {
                this.config.csrfToken = window.CIS_CSRF;
            }
        }

        setupAjaxDefaults() {
            // Setup jQuery AJAX defaults if jQuery is available
            if (window.jQuery) {
                const self = this;
                jQuery.ajaxSetup({
                    beforeSend: function(xhr) {
                        if (self.config.csrfToken) {
                            xhr.setRequestHeader('X-CSRF-Token', self.config.csrfToken);
                        }
                        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
                    }
                });
            }
        }

        // ============================================================
        // AJAX HELPERS
        // ============================================================

        /**
         * Enhanced AJAX helper with automatic error handling
         *
         * @param {string} url - API endpoint
         * @param {object} options - Fetch options
         * @returns {Promise}
         */
        ajax(url, options = {}) {
            const defaults = {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin'
            };

            // Merge options
            const config = Object.assign({}, defaults, options);

            // Add CSRF token
            if (this.config.csrfToken) {
                config.headers['X-CSRF-Token'] = this.config.csrfToken;
            }

            // Convert body to JSON if it's an object
            if (config.body && typeof config.body === 'object' && !(config.body instanceof FormData)) {
                config.body = JSON.stringify(config.body);
            }

            return fetch(url, config)
                .then(response => {
                    // Check if response is JSON
                    const contentType = response.headers.get('content-type');
                    const isJson = contentType && contentType.includes('application/json');

                    if (!response.ok) {
                        // Try to get error message from response
                        if (isJson) {
                            return response.json().then(data => {
                                throw new Error(data.message || data.error || `HTTP ${response.status}: ${response.statusText}`);
                            });
                        } else {
                            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                        }
                    }

                    // Return JSON or text
                    return isJson ? response.json() : response.text();
                })
                .catch(error => {
                    // Show error using error handler
                    if (window.CIS.ErrorHandler) {
                        window.CIS.ErrorHandler.error(error.message, `URL: ${url}\nMethod: ${config.method}`);
                    }
                    throw error;
                });
        }

        /**
         * GET request
         */
        get(url, params = {}) {
            const query = new URLSearchParams(params).toString();
            const fullUrl = query ? `${url}?${query}` : url;
            return this.ajax(fullUrl, { method: 'GET' });
        }

        /**
         * POST request
         */
        post(url, data = {}) {
            return this.ajax(url, {
                method: 'POST',
                body: data
            });
        }

        /**
         * PUT request
         */
        put(url, data = {}) {
            return this.ajax(url, {
                method: 'PUT',
                body: data
            });
        }

        /**
         * DELETE request
         */
        delete(url) {
            return this.ajax(url, { method: 'DELETE' });
        }

        // ============================================================
        // FORMAT UTILITIES
        // ============================================================

        /**
         * Format currency
         */
        formatCurrency(amount, showSymbol = true) {
            const num = parseFloat(amount);
            if (isNaN(num)) return showSymbol ? `${this.config.currencySymbol}0.00` : '0.00';

            const formatted = num.toFixed(2);
            return showSymbol ? `${this.config.currencySymbol}${formatted}` : formatted;
        }

        /**
         * Format date
         */
        formatDate(dateString, options = {}) {
            const defaults = {
                year: 'numeric',
                month: 'short',
                day: 'numeric'
            };

            const formatOptions = Object.assign({}, defaults, options);
            const date = new Date(dateString);

            if (isNaN(date.getTime())) return 'Invalid Date';

            return date.toLocaleDateString(this.config.dateFormat, formatOptions);
        }

        /**
         * Format datetime
         */
        formatDateTime(dateString, options = {}) {
            const defaults = {
                year: 'numeric',
                month: 'short',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            };

            const formatOptions = Object.assign({}, defaults, options);
            const date = new Date(dateString);

            if (isNaN(date.getTime())) return 'Invalid Date';

            return date.toLocaleString(this.config.dateFormat, formatOptions);
        }

        /**
         * Format number
         */
        formatNumber(number, decimals = 0) {
            const num = parseFloat(number);
            if (isNaN(num)) return '0';

            return num.toLocaleString(this.config.dateFormat, {
                minimumFractionDigits: decimals,
                maximumFractionDigits: decimals
            });
        }

        /**
         * Format file size
         */
        formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';

            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));

            return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
        }

        /**
         * Format phone number (NZ format)
         */
        formatPhone(phone) {
            // Remove all non-numeric characters
            const cleaned = ('' + phone).replace(/\D/g, '');

            // Format based on length
            if (cleaned.length === 10) {
                // Mobile: 021 123 4567
                return cleaned.replace(/(\d{3})(\d{3})(\d{4})/, '$1 $2 $3');
            } else if (cleaned.length === 9) {
                // Landline: 09 123 4567
                return cleaned.replace(/(\d{2})(\d{3})(\d{4})/, '$1 $2 $3');
            }

            return phone;
        }

        // ============================================================
        // USER FEEDBACK
        // ============================================================

        /**
         * Show toast notification (uses Error Handler)
         */
        toast(message, type = 'info', details = '') {
            if (window.CIS.ErrorHandler) {
                switch(type) {
                    case 'success':
                        return window.CIS.ErrorHandler.success(message, details);
                    case 'error':
                        return window.CIS.ErrorHandler.error(message, details);
                    case 'warning':
                        return window.CIS.ErrorHandler.warning(message, details);
                    default:
                        return window.CIS.ErrorHandler.info(message, details);
                }
            } else {
                // Fallback to console
                console.log(`[${type.toUpperCase()}] ${message}`);
            }
        }

        /**
         * Show confirmation dialog
         */
        confirm(message, callback, cancelCallback = null) {
            // Use SweetAlert2 if available
            if (window.Swal) {
                Swal.fire({
                    title: 'Confirm',
                    text: message,
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#007bff',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Yes',
                    cancelButtonText: 'Cancel'
                }).then((result) => {
                    if (result.isConfirmed) {
                        callback();
                    } else if (cancelCallback) {
                        cancelCallback();
                    }
                });
            } else {
                // Fallback to native confirm
                if (confirm(message)) {
                    callback();
                } else if (cancelCallback) {
                    cancelCallback();
                }
            }
        }

        /**
         * Show loading overlay
         */
        showLoading(message = 'Loading...') {
            let overlay = document.getElementById('cis-loading-overlay');

            if (!overlay) {
                overlay = document.createElement('div');
                overlay.id = 'cis-loading-overlay';
                overlay.innerHTML = `
                    <div class="cis-loading-spinner"></div>
                    <div class="cis-loading-text">${message}</div>
                `;
                document.body.appendChild(overlay);

                // Add styles if not already present
                if (!document.getElementById('cis-loading-styles')) {
                    const style = document.createElement('style');
                    style.id = 'cis-loading-styles';
                    style.textContent = `
                        #cis-loading-overlay {
                            position: fixed;
                            top: 0;
                            left: 0;
                            right: 0;
                            bottom: 0;
                            background: rgba(0, 0, 0, 0.7);
                            display: flex;
                            flex-direction: column;
                            align-items: center;
                            justify-content: center;
                            z-index: 999998;
                        }
                        .cis-loading-spinner {
                            border: 4px solid #f3f3f3;
                            border-top: 4px solid #007bff;
                            border-radius: 50%;
                            width: 50px;
                            height: 50px;
                            animation: spin 1s linear infinite;
                        }
                        .cis-loading-text {
                            color: white;
                            margin-top: 20px;
                            font-size: 16px;
                            font-weight: 500;
                        }
                        @keyframes spin {
                            0% { transform: rotate(0deg); }
                            100% { transform: rotate(360deg); }
                        }
                    `;
                    document.head.appendChild(style);
                }
            }

            overlay.style.display = 'flex';
        }

        /**
         * Hide loading overlay
         */
        hideLoading() {
            const overlay = document.getElementById('cis-loading-overlay');
            if (overlay) {
                overlay.style.display = 'none';
            }
        }

        // ============================================================
        // LOCAL STORAGE HELPERS
        // ============================================================

        /**
         * Store data in local storage
         */
        store(key, value, prefix = 'cis_') {
            try {
                const data = JSON.stringify(value);
                localStorage.setItem(prefix + key, data);
                return true;
            } catch (e) {
                console.error('LocalStorage error:', e);
                return false;
            }
        }

        /**
         * Retrieve data from local storage
         */
        retrieve(key, defaultValue = null, prefix = 'cis_') {
            try {
                const data = localStorage.getItem(prefix + key);
                return data ? JSON.parse(data) : defaultValue;
            } catch (e) {
                console.error('LocalStorage error:', e);
                return defaultValue;
            }
        }

        /**
         * Remove data from local storage
         */
        forget(key, prefix = 'cis_') {
            try {
                localStorage.removeItem(prefix + key);
                return true;
            } catch (e) {
                console.error('LocalStorage error:', e);
                return false;
            }
        }

        /**
         * Clear all CIS data from local storage
         */
        clearStorage(prefix = 'cis_') {
            try {
                const keys = Object.keys(localStorage);
                keys.forEach(key => {
                    if (key.startsWith(prefix)) {
                        localStorage.removeItem(key);
                    }
                });
                return true;
            } catch (e) {
                console.error('LocalStorage error:', e);
                return false;
            }
        }

        // ============================================================
        // FORM UTILITIES
        // ============================================================

        /**
         * Serialize form data to object
         */
        serializeForm(form) {
            const formData = new FormData(form);
            const obj = {};

            for (let [key, value] of formData.entries()) {
                // Handle multiple values (checkboxes, etc.)
                if (obj[key]) {
                    if (!Array.isArray(obj[key])) {
                        obj[key] = [obj[key]];
                    }
                    obj[key].push(value);
                } else {
                    obj[key] = value;
                }
            }

            return obj;
        }

        /**
         * Populate form with data
         */
        populateForm(form, data) {
            for (let key in data) {
                const input = form.elements[key];
                if (input) {
                    if (input.type === 'checkbox') {
                        input.checked = !!data[key];
                    } else if (input.type === 'radio') {
                        const radio = form.querySelector(`input[name="${key}"][value="${data[key]}"]`);
                        if (radio) radio.checked = true;
                    } else {
                        input.value = data[key];
                    }
                }
            }
        }

        /**
         * Reset form and clear validation
         */
        resetForm(form) {
            form.reset();

            // Clear validation states (Bootstrap)
            const inputs = form.querySelectorAll('.is-invalid, .is-valid');
            inputs.forEach(input => {
                input.classList.remove('is-invalid', 'is-valid');
            });

            const feedback = form.querySelectorAll('.invalid-feedback, .valid-feedback');
            feedback.forEach(el => el.remove());
        }

        // ============================================================
        // VALIDATION HELPERS
        // ============================================================

        /**
         * Validate email
         */
        isEmail(email) {
            const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return re.test(email);
        }

        /**
         * Validate NZ phone
         */
        isPhone(phone) {
            const cleaned = ('' + phone).replace(/\D/g, '');
            return cleaned.length >= 9 && cleaned.length <= 11;
        }

        /**
         * Validate URL
         */
        isUrl(url) {
            try {
                new URL(url);
                return true;
            } catch (e) {
                return false;
            }
        }

        /**
         * Check if value is empty
         */
        isEmpty(value) {
            return value === null || value === undefined || value === '' ||
                   (Array.isArray(value) && value.length === 0) ||
                   (typeof value === 'object' && Object.keys(value).length === 0);
        }

        // ============================================================
        // UTILITY FUNCTIONS
        // ============================================================

        /**
         * Debounce function
         */
        debounce(func, wait = 300) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }

        /**
         * Throttle function
         */
        throttle(func, limit = 300) {
            let inThrottle;
            return function(...args) {
                if (!inThrottle) {
                    func.apply(this, args);
                    inThrottle = true;
                    setTimeout(() => inThrottle = false, limit);
                }
            };
        }

        /**
         * Generate unique ID
         */
        uniqueId(prefix = 'cis') {
            return `${prefix}_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`;
        }

        /**
         * Copy text to clipboard
         */
        copyToClipboard(text) {
            if (navigator.clipboard) {
                return navigator.clipboard.writeText(text)
                    .then(() => {
                        this.toast('Copied to clipboard!', 'success');
                        return true;
                    })
                    .catch(err => {
                        this.toast('Failed to copy', 'error');
                        return false;
                    });
            } else {
                // Fallback
                const textarea = document.createElement('textarea');
                textarea.value = text;
                textarea.style.position = 'fixed';
                textarea.style.opacity = '0';
                document.body.appendChild(textarea);
                textarea.select();
                const success = document.execCommand('copy');
                document.body.removeChild(textarea);

                if (success) {
                    this.toast('Copied to clipboard!', 'success');
                }
                return success;
            }
        }

        /**
         * Get query parameter from URL
         */
        getParam(name, url = window.location.href) {
            name = name.replace(/[\[\]]/g, '\\$&');
            const regex = new RegExp('[?&]' + name + '(=([^&#]*)|&|#|$)');
            const results = regex.exec(url);
            if (!results) return null;
            if (!results[2]) return '';
            return decodeURIComponent(results[2].replace(/\+/g, ' '));
        }

        /**
         * Check if element is in viewport
         */
        isInViewport(element) {
            const rect = element.getBoundingClientRect();
            return (
                rect.top >= 0 &&
                rect.left >= 0 &&
                rect.bottom <= (window.innerHeight || document.documentElement.clientHeight) &&
                rect.right <= (window.innerWidth || document.documentElement.clientWidth)
            );
        }

        // ============================================================
        // CONFIGURATION
        // ============================================================

        // ============================================================
        // WEBSOCKET MANAGER
        // ============================================================

        /**
         * WebSocket connection manager
         */
        connectWebSocket(url, options = {}) {
            const defaults = {
                reconnect: true,
                reconnectInterval: 5000,
                maxReconnectAttempts: 10,
                onOpen: null,
                onMessage: null,
                onClose: null,
                onError: null
            };

            const config = Object.assign({}, defaults, options);
            let ws = null;
            let reconnectAttempts = 0;
            let reconnectTimer = null;

            const connect = () => {
                try {
                    ws = new WebSocket(url);

                    ws.onopen = (event) => {
                        console.log('✅ WebSocket connected:', url);
                        reconnectAttempts = 0;
                        if (config.onOpen) config.onOpen(event);
                    };

                    ws.onmessage = (event) => {
                        try {
                            const data = JSON.parse(event.data);
                            if (config.onMessage) config.onMessage(data, event);
                        } catch (e) {
                            if (config.onMessage) config.onMessage(event.data, event);
                        }
                    };

                    ws.onerror = (event) => {
                        console.error('❌ WebSocket error:', event);
                        if (config.onError) config.onError(event);
                    };

                    ws.onclose = (event) => {
                        console.log('🔌 WebSocket closed:', event.code, event.reason);
                        if (config.onClose) config.onClose(event);

                        // Attempt reconnection
                        if (config.reconnect && reconnectAttempts < config.maxReconnectAttempts) {
                            reconnectAttempts++;
                            console.log(`🔄 Reconnecting in ${config.reconnectInterval}ms (attempt ${reconnectAttempts}/${config.maxReconnectAttempts})...`);
                            reconnectTimer = setTimeout(connect, config.reconnectInterval);
                        }
                    };

                } catch (e) {
                    console.error('❌ Failed to create WebSocket:', e);
                }
            };

            connect();

            // Return controller
            return {
                send: (data) => {
                    if (ws && ws.readyState === WebSocket.OPEN) {
                        ws.send(typeof data === 'object' ? JSON.stringify(data) : data);
                        return true;
                    }
                    return false;
                },
                close: () => {
                    if (reconnectTimer) clearTimeout(reconnectTimer);
                    if (ws) ws.close();
                },
                reconnect: () => {
                    if (ws) ws.close();
                    connect();
                },
                getState: () => {
                    if (!ws) return 'CLOSED';
                    const states = ['CONNECTING', 'OPEN', 'CLOSING', 'CLOSED'];
                    return states[ws.readyState];
                },
                isConnected: () => ws && ws.readyState === WebSocket.OPEN
            };
        }

        // ============================================================
        // SERVER-SENT EVENTS (SSE)
        // ============================================================

        /**
         * Server-Sent Events manager
         */
        connectSSE(url, options = {}) {
            const defaults = {
                reconnect: true,
                reconnectInterval: 3000,
                onMessage: null,
                onError: null,
                onOpen: null
            };

            const config = Object.assign({}, defaults, options);
            let eventSource = null;
            let reconnectTimer = null;

            const connect = () => {
                try {
                    eventSource = new EventSource(url);

                    eventSource.onopen = (event) => {
                        console.log('✅ SSE connected:', url);
                        if (config.onOpen) config.onOpen(event);
                    };

                    eventSource.onmessage = (event) => {
                        try {
                            const data = JSON.parse(event.data);
                            if (config.onMessage) config.onMessage(data, event);
                        } catch (e) {
                            if (config.onMessage) config.onMessage(event.data, event);
                        }
                    };

                    eventSource.onerror = (event) => {
                        console.error('❌ SSE error:', event);
                        if (config.onError) config.onError(event);

                        // Reconnect logic
                        if (config.reconnect) {
                            eventSource.close();
                            reconnectTimer = setTimeout(connect, config.reconnectInterval);
                        }
                    };

                } catch (e) {
                    console.error('❌ Failed to create SSE connection:', e);
                }
            };

            connect();

            return {
                close: () => {
                    if (reconnectTimer) clearTimeout(reconnectTimer);
                    if (eventSource) eventSource.close();
                },
                reconnect: () => {
                    if (eventSource) eventSource.close();
                    connect();
                }
            };
        }

        // ============================================================
        // ADVANCED LOGGING
        // ============================================================

        /**
         * Advanced logger with levels and remote logging
         */
        createLogger(namespace = 'CIS') {
            const levels = {
                debug: 0,
                info: 1,
                warn: 2,
                error: 3
            };

            let currentLevel = this.config.debug ? 0 : 1;
            let remoteEndpoint = null;

            const formatMessage = (level, ...args) => {
                const timestamp = new Date().toISOString();
                const prefix = `[${timestamp}] [${namespace}] [${level.toUpperCase()}]`;
                return [prefix, ...args];
            };

            const sendToRemote = (level, message) => {
                if (remoteEndpoint) {
                    this.post(remoteEndpoint, {
                        level,
                        namespace,
                        message,
                        timestamp: new Date().toISOString(),
                        url: window.location.href,
                        userAgent: navigator.userAgent
                    }).catch(() => {}); // Silent fail for remote logging
                }
            };

            return {
                debug: (...args) => {
                    if (currentLevel <= levels.debug) {
                        console.debug(...formatMessage('debug', ...args));
                        sendToRemote('debug', args);
                    }
                },
                info: (...args) => {
                    if (currentLevel <= levels.info) {
                        console.info(...formatMessage('info', ...args));
                        sendToRemote('info', args);
                    }
                },
                warn: (...args) => {
                    if (currentLevel <= levels.warn) {
                        console.warn(...formatMessage('warn', ...args));
                        sendToRemote('warn', args);
                    }
                },
                error: (...args) => {
                    if (currentLevel <= levels.error) {
                        console.error(...formatMessage('error', ...args));
                        sendToRemote('error', args);
                    }
                },
                setLevel: (level) => {
                    if (levels[level] !== undefined) {
                        currentLevel = levels[level];
                    }
                },
                setRemoteEndpoint: (url) => {
                    remoteEndpoint = url;
                },
                group: (label) => console.group(`[${namespace}] ${label}`),
                groupEnd: () => console.groupEnd(),
                table: (data) => console.table(data),
                time: (label) => console.time(`[${namespace}] ${label}`),
                timeEnd: (label) => console.timeEnd(`[${namespace}] ${label}`)
            };
        }

        // ============================================================
        // NOTIFICATIONS API (Browser Notifications)
        // ============================================================

        /**
         * Request notification permission
         */
        async requestNotificationPermission() {
            if (!('Notification' in window)) {
                console.warn('Browser does not support notifications');
                return false;
            }

            if (Notification.permission === 'granted') {
                return true;
            }

            if (Notification.permission !== 'denied') {
                const permission = await Notification.requestPermission();
                return permission === 'granted';
            }

            return false;
        }

        /**
         * Show browser notification
         */
        notify(title, options = {}) {
            if (!('Notification' in window)) return null;

            if (Notification.permission === 'granted') {
                const defaults = {
                    icon: '/assets/images/logo.png',
                    badge: '/assets/images/badge.png',
                    vibrate: [200, 100, 200],
                    requireInteraction: false
                };

                const config = Object.assign({}, defaults, options);
                const notification = new Notification(title, config);

                // Auto close after 10 seconds unless requireInteraction
                if (!config.requireInteraction) {
                    setTimeout(() => notification.close(), 10000);
                }

                return notification;
            }

            return null;
        }

        // ============================================================
        // WEB WORKERS
        // ============================================================

        /**
         * Create and manage a Web Worker
         */
        createWorker(workerFunction) {
            const blob = new Blob(['(' + workerFunction.toString() + ')()'], {
                type: 'application/javascript'
            });
            const url = URL.createObjectURL(blob);
            const worker = new Worker(url);

            return {
                postMessage: (data) => worker.postMessage(data),
                onMessage: (callback) => {
                    worker.onmessage = (e) => callback(e.data);
                },
                onError: (callback) => {
                    worker.onerror = (e) => callback(e);
                },
                terminate: () => {
                    worker.terminate();
                    URL.revokeObjectURL(url);
                }
            };
        }

        // ============================================================
        // INDEXED DB HELPER
        // ============================================================

        /**
         * IndexedDB wrapper for client-side database
         */
        async openDB(dbName = 'CIS_DB', version = 1, upgradeCallback = null) {
            return new Promise((resolve, reject) => {
                const request = indexedDB.open(dbName, version);

                request.onerror = () => reject(request.error);
                request.onsuccess = () => resolve(request.result);

                request.onupgradeneeded = (event) => {
                    const db = event.target.result;
                    if (upgradeCallback) {
                        upgradeCallback(db, event.oldVersion, event.newVersion);
                    }
                };
            });
        }

        // ============================================================
        // GEOLOCATION API
        // ============================================================

        /**
         * Get current position
         */
        async getCurrentPosition(options = {}) {
            if (!navigator.geolocation) {
                throw new Error('Geolocation not supported');
            }

            return new Promise((resolve, reject) => {
                navigator.geolocation.getCurrentPosition(
                    (position) => resolve({
                        latitude: position.coords.latitude,
                        longitude: position.coords.longitude,
                        accuracy: position.coords.accuracy,
                        timestamp: position.timestamp
                    }),
                    (error) => reject(error),
                    options
                );
            });
        }

        /**
         * Watch position changes
         */
        watchPosition(callback, errorCallback = null, options = {}) {
            if (!navigator.geolocation) {
                console.warn('Geolocation not supported');
                return null;
            }

            const watchId = navigator.geolocation.watchPosition(
                (position) => callback({
                    latitude: position.coords.latitude,
                    longitude: position.coords.longitude,
                    accuracy: position.coords.accuracy,
                    timestamp: position.timestamp
                }),
                errorCallback,
                options
            );

            return {
                clear: () => navigator.geolocation.clearWatch(watchId)
            };
        }

        // ============================================================
        // CLIPBOARD API (Advanced)
        // ============================================================

        /**
         * Read from clipboard
         */
        async readClipboard() {
            if (navigator.clipboard && navigator.clipboard.readText) {
                try {
                    return await navigator.clipboard.readText();
                } catch (e) {
                    console.error('Failed to read clipboard:', e);
                    return null;
                }
            }
            return null;
        }

        /**
         * Write to clipboard (with image support)
         */
        async writeClipboard(data, type = 'text') {
            if (!navigator.clipboard) return false;

            try {
                if (type === 'text') {
                    await navigator.clipboard.writeText(data);
                } else if (type === 'html') {
                    const blob = new Blob([data], { type: 'text/html' });
                    const clipboardItem = new ClipboardItem({ 'text/html': blob });
                    await navigator.clipboard.write([clipboardItem]);
                } else if (type === 'image') {
                    // data should be a Blob or File
                    const clipboardItem = new ClipboardItem({ [data.type]: data });
                    await navigator.clipboard.write([clipboardItem]);
                }
                return true;
            } catch (e) {
                console.error('Failed to write to clipboard:', e);
                return false;
            }
        }

        // ============================================================
        // WEB SHARE API
        // ============================================================

        /**
         * Share content using Web Share API
         */
        async share(data = {}) {
            if (!navigator.share) {
                console.warn('Web Share API not supported');
                return false;
            }

            try {
                await navigator.share(data);
                return true;
            } catch (e) {
                if (e.name !== 'AbortError') {
                    console.error('Share failed:', e);
                }
                return false;
            }
        }

        // ============================================================
        // VIBRATION API
        // ============================================================

        /**
         * Vibrate device
         */
        vibrate(pattern) {
            if (navigator.vibrate) {
                navigator.vibrate(pattern);
                return true;
            }
            return false;
        }

        // ============================================================
        // BATTERY API
        // ============================================================

        /**
         * Get battery information
         */
        async getBatteryInfo() {
            if (navigator.getBattery) {
                const battery = await navigator.getBattery();
                return {
                    level: battery.level,
                    charging: battery.charging,
                    chargingTime: battery.chargingTime,
                    dischargingTime: battery.dischargingTime
                };
            }
            return null;
        }

        // ============================================================
        // NETWORK INFORMATION API
        // ============================================================

        /**
         * Get network information
         */
        getNetworkInfo() {
            const connection = navigator.connection || navigator.mozConnection || navigator.webkitConnection;

            if (connection) {
                return {
                    effectiveType: connection.effectiveType,
                    downlink: connection.downlink,
                    rtt: connection.rtt,
                    saveData: connection.saveData,
                    online: navigator.onLine
                };
            }

            return {
                online: navigator.onLine
            };
        }

        /**
         * Watch network status changes
         */
        watchNetworkStatus(callback) {
            const updateStatus = () => callback(this.getNetworkInfo());

            window.addEventListener('online', updateStatus);
            window.addEventListener('offline', updateStatus);

            const connection = navigator.connection || navigator.mozConnection || navigator.webkitConnection;
            if (connection) {
                connection.addEventListener('change', updateStatus);
            }

            return {
                stop: () => {
                    window.removeEventListener('online', updateStatus);
                    window.removeEventListener('offline', updateStatus);
                    if (connection) {
                        connection.removeEventListener('change', updateStatus);
                    }
                }
            };
        }

        // ============================================================
        // PERFORMANCE API
        // ============================================================

        /**
         * Measure performance
         */
        measure(name, startMark, endMark) {
            if (window.performance && window.performance.measure) {
                try {
                    performance.measure(name, startMark, endMark);
                    const measures = performance.getEntriesByName(name);
                    return measures[measures.length - 1];
                } catch (e) {
                    console.warn('Performance measurement failed:', e);
                }
            }
            return null;
        }

        /**
         * Mark performance point
         */
        mark(name) {
            if (window.performance && window.performance.mark) {
                performance.mark(name);
            }
        }

        /**
         * Get performance metrics
         */
        getPerformanceMetrics() {
            if (!window.performance) return null;

            const navigation = performance.getEntriesByType('navigation')[0];
            const paint = performance.getEntriesByType('paint');

            return {
                loadTime: navigation ? navigation.loadEventEnd - navigation.fetchStart : 0,
                domContentLoaded: navigation ? navigation.domContentLoadedEventEnd - navigation.fetchStart : 0,
                firstPaint: paint[0] ? paint[0].startTime : 0,
                firstContentfulPaint: paint[1] ? paint[1].startTime : 0,
                memory: performance.memory ? {
                    used: performance.memory.usedJSHeapSize,
                    total: performance.memory.totalJSHeapSize,
                    limit: performance.memory.jsHeapSizeLimit
                } : null
            };
        }

        // ============================================================
        // PAGE VISIBILITY API
        // ============================================================

        /**
         * Watch page visibility changes
         */
        watchPageVisibility(callback) {
            const handleVisibilityChange = () => {
                callback({
                    hidden: document.hidden,
                    visibilityState: document.visibilityState
                });
            };

            document.addEventListener('visibilitychange', handleVisibilityChange);

            return {
                stop: () => document.removeEventListener('visibilitychange', handleVisibilityChange)
            };
        }

        // ============================================================
        // INTERSECTION OBSERVER
        // ============================================================

        /**
         * Observe element intersection (lazy loading, infinite scroll)
         */
        observeIntersection(elements, callback, options = {}) {
            const defaults = {
                root: null,
                rootMargin: '0px',
                threshold: 0.1
            };

            const config = Object.assign({}, defaults, options);

            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        callback(entry.target, entry);
                    }
                });
            }, config);

            // Observe elements
            if (Array.isArray(elements)) {
                elements.forEach(el => observer.observe(el));
            } else if (elements instanceof NodeList) {
                elements.forEach(el => observer.observe(el));
            } else {
                observer.observe(elements);
            }

            return {
                disconnect: () => observer.disconnect(),
                unobserve: (element) => observer.unobserve(element)
            };
        }

        // ============================================================
        // MUTATION OBSERVER
        // ============================================================

        /**
         * Observe DOM mutations
         */
        observeMutations(target, callback, options = {}) {
            const defaults = {
                childList: true,
                attributes: true,
                characterData: true,
                subtree: true
            };

            const config = Object.assign({}, defaults, options);

            const observer = new MutationObserver((mutations) => {
                callback(mutations, observer);
            });

            observer.observe(target, config);

            return {
                disconnect: () => observer.disconnect()
            };
        }

        // ============================================================
        // RESIZE OBSERVER
        // ============================================================

        /**
         * Observe element size changes
         */
        observeResize(elements, callback) {
            if (!window.ResizeObserver) {
                console.warn('ResizeObserver not supported');
                return null;
            }

            const observer = new ResizeObserver((entries) => {
                entries.forEach(entry => {
                    callback(entry.target, entry.contentRect);
                });
            });

            if (Array.isArray(elements)) {
                elements.forEach(el => observer.observe(el));
            } else if (elements instanceof NodeList) {
                elements.forEach(el => observer.observe(el));
            } else {
                observer.observe(elements);
            }

            return {
                disconnect: () => observer.disconnect(),
                unobserve: (element) => observer.unobserve(element)
            };
        }

        // ============================================================
        // SPEECH RECOGNITION API
        // ============================================================

        /**
         * Start voice recognition
         */
        startVoiceRecognition(options = {}) {
            const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;

            if (!SpeechRecognition) {
                console.warn('Speech Recognition not supported');
                return null;
            }

            const recognition = new SpeechRecognition();

            recognition.continuous = options.continuous || false;
            recognition.interimResults = options.interimResults || false;
            recognition.lang = options.lang || 'en-US';

            const callbacks = {
                onResult: options.onResult || null,
                onError: options.onError || null,
                onEnd: options.onEnd || null
            };

            recognition.onresult = (event) => {
                const results = Array.from(event.results).map(result => ({
                    transcript: result[0].transcript,
                    confidence: result[0].confidence,
                    isFinal: result.isFinal
                }));
                if (callbacks.onResult) callbacks.onResult(results);
            };

            recognition.onerror = (event) => {
                if (callbacks.onError) callbacks.onError(event.error);
            };

            recognition.onend = () => {
                if (callbacks.onEnd) callbacks.onEnd();
            };

            recognition.start();

            return {
                stop: () => recognition.stop(),
                abort: () => recognition.abort()
            };
        }

        // ============================================================
        // TEXT-TO-SPEECH API
        // ============================================================

        /**
         * Speak text
         */
        speak(text, options = {}) {
            if (!window.speechSynthesis) {
                console.warn('Speech Synthesis not supported');
                return null;
            }

            const utterance = new SpeechSynthesisUtterance(text);

            utterance.lang = options.lang || 'en-US';
            utterance.rate = options.rate || 1;
            utterance.pitch = options.pitch || 1;
            utterance.volume = options.volume || 1;

            if (options.onEnd) utterance.onend = options.onEnd;
            if (options.onError) utterance.onerror = options.onError;

            speechSynthesis.speak(utterance);

            return {
                cancel: () => speechSynthesis.cancel(),
                pause: () => speechSynthesis.pause(),
                resume: () => speechSynthesis.resume()
            };
        }

        // ============================================================
        // FILE API HELPERS
        // ============================================================

        /**
         * Read file as text
         */
        async readFileAsText(file) {
            return new Promise((resolve, reject) => {
                const reader = new FileReader();
                reader.onload = (e) => resolve(e.target.result);
                reader.onerror = (e) => reject(e);
                reader.readAsText(file);
            });
        }

        /**
         * Read file as data URL
         */
        async readFileAsDataURL(file) {
            return new Promise((resolve, reject) => {
                const reader = new FileReader();
                reader.onload = (e) => resolve(e.target.result);
                reader.onerror = (e) => reject(e);
                reader.readAsDataURL(file);
            });
        }

        /**
         * Download file
         */
        downloadFile(data, filename, mimeType = 'application/octet-stream') {
            const blob = new Blob([data], { type: mimeType });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = filename;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
        }

        // ============================================================
        // CONFIGURATION
        // ============================================================

        /**
         * Configure CIS Core
         */
        configure(options) {
            this.config = Object.assign(this.config, options);
        }

        /**
         * Get configuration value
         */
        getConfig(key, defaultValue = null) {
            return this.config[key] !== undefined ? this.config[key] : defaultValue;
        }
    }

    // Initialize global instance
    window.CIS = window.CIS || {};
    window.CIS.Core = new CISCore();

    // Shorthand alias
    window.CIS.$ = window.CIS.Core;

    // Auto-detect and log capabilities
    if (window.CIS.$.getConfig('debug')) {
        console.group('🚀 CIS Core - Available Features');
        console.log('✓ AJAX/Fetch helpers');
        console.log('✓ WebSocket support:', 'WebSocket' in window);
        console.log('✓ Server-Sent Events:', 'EventSource' in window);
        console.log('✓ Notifications:', 'Notification' in window);
        console.log('✓ Geolocation:', 'geolocation' in navigator);
        console.log('✓ Web Share:', 'share' in navigator);
        console.log('✓ Clipboard:', 'clipboard' in navigator);
        console.log('✓ IndexedDB:', 'indexedDB' in window);
        console.log('✓ Web Workers:', 'Worker' in window);
        console.log('✓ Speech Recognition:', 'SpeechRecognition' in window || 'webkitSpeechRecognition' in window);
        console.log('✓ Speech Synthesis:', 'speechSynthesis' in window);
        console.groupEnd();
    }

})(window, document);
