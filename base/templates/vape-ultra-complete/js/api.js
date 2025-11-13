/**
 * API Client
 *
 * Centralized API communication with axios
 * Handles authentication, retries, error handling
 */

(function() {
    'use strict';

    window.VapeUltra = window.VapeUltra || {};

    VapeUltra.API = {
        baseURL: '/api',
        timeout: 30000,
        retries: 3,

        /**
         * Create configured axios instance
         */
        client: null,

        init: function() {
            this.client = axios.create({
                baseURL: this.baseURL,
                timeout: this.timeout,
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            // Request interceptor
            this.client.interceptors.request.use(
                config => {
                    // Add auth token
                    const token = this.getToken();
                    if (token) {
                        config.headers.Authorization = `Bearer ${token}`;
                    }

                    // Add CSRF token
                    const csrf = this.getCsrfToken();
                    if (csrf) {
                        config.headers['X-CSRF-Token'] = csrf;
                    }

                    // Log request in dev mode
                    if (this.isDebug()) {
                        console.log('🚀 API Request:', config.method.toUpperCase(), config.url, config);
                    }

                    return config;
                },
                error => {
                    return Promise.reject(error);
                }
            );

            // Response interceptor
            this.client.interceptors.response.use(
                response => {
                    if (this.isDebug()) {
                        console.log('✅ API Response:', response.config.url, response);
                    }
                    return response.data;
                },
                error => {
                    return this.handleError(error);
                }
            );

            console.log('✅ API client initialized');
        },

        /**
         * HTTP methods
         */
        get: function(url, params = {}) {
            return this.client.get(url, { params });
        },

        post: function(url, data = {}) {
            return this.client.post(url, data);
        },

        put: function(url, data = {}) {
            return this.client.put(url, data);
        },

        patch: function(url, data = {}) {
            return this.client.patch(url, data);
        },

        delete: function(url) {
            return this.client.delete(url);
        },

        /**
         * Error handling
         */
        handleError: function(error) {
            console.error('❌ API Error:', error);

            if (error.response) {
                // Server responded with error
                const status = error.response.status;
                const message = error.response.data?.message || error.message;

                switch(status) {
                    case 401:
                        VapeUltra.Notifications.error('Authentication required');
                        this.redirectToLogin();
                        break;
                    case 403:
                        VapeUltra.Notifications.error('Access denied');
                        break;
                    case 404:
                        VapeUltra.Notifications.error('Resource not found');
                        break;
                    case 422:
                        VapeUltra.Notifications.error('Validation failed');
                        break;
                    case 429:
                        VapeUltra.Notifications.error('Too many requests. Please slow down.');
                        break;
                    case 500:
                        VapeUltra.Notifications.error('Server error. Please try again.');
                        break;
                    default:
                        VapeUltra.Notifications.error(message);
                }

                return Promise.reject({
                    status,
                    message,
                    data: error.response.data
                });
            } else if (error.request) {
                // Request made but no response
                VapeUltra.Notifications.error('Network error. Please check your connection.');
                return Promise.reject({
                    status: 0,
                    message: 'Network error',
                    data: null
                });
            } else {
                // Something else happened
                VapeUltra.Notifications.error('An unexpected error occurred');
                return Promise.reject({
                    status: -1,
                    message: error.message,
                    data: null
                });
            }
        },

        /**
         * Auth helpers
         */
        getToken: function() {
            return localStorage.getItem('auth_token') || sessionStorage.getItem('auth_token');
        },

        setToken: function(token, remember = false) {
            if (remember) {
                localStorage.setItem('auth_token', token);
            } else {
                sessionStorage.setItem('auth_token', token);
            }
        },

        clearToken: function() {
            localStorage.removeItem('auth_token');
            sessionStorage.removeItem('auth_token');
        },

        getCsrfToken: function() {
            const meta = document.querySelector('meta[name="csrf-token"]');
            return meta ? meta.content : null;
        },

        redirectToLogin: function() {
            setTimeout(() => {
                window.location.href = '/login';
            }, 1500);
        },

        /**
         * Debug mode
         */
        isDebug: function() {
            return VapeUltra.Core.getState('debug', false) ||
                   window.location.hostname === 'localhost';
        },

        /**
         * Retry logic with exponential backoff
         */
        retryRequest: function(requestFn, retries = 3, delay = 1000) {
            return new Promise((resolve, reject) => {
                const attempt = (n) => {
                    requestFn()
                        .then(resolve)
                        .catch(error => {
                            if (n === 1) {
                                reject(error);
                            } else {
                                setTimeout(() => {
                                    console.log(`🔄 Retrying... (${retries - n + 2}/${retries})`);
                                    attempt(n - 1);
                                }, delay * (retries - n + 1));
                            }
                        });
                };
                attempt(retries);
            });
        }
    };

    // Auto-initialize
    VapeUltra.Core.registerModule('API', VapeUltra.API);

})();
