/**
 * VapeUltra Core System
 *
 * Central nervous system for the entire application
 * Handles initialization, state management, events
 */

(function() {
    'use strict';

    window.VapeUltra = window.VapeUltra || {};

    VapeUltra.Core = {
        version: '1.0.0',
        initialized: false,
        modules: {},
        state: {},
        events: {},

        /**
         * Initialize the core system
         */
        init: function() {
            if (this.initialized) return;

            console.log('%c🚀 VapeUltra Core v' + this.version, 'color: #6366f1; font-weight: bold; font-size: 14px;');

            this.setupEventSystem();
            this.loadState();
            this.initializeModules();
            this.setupGlobalHandlers();

            this.initialized = true;
            this.emit('core:ready');

            console.log('✅ Core system initialized');
        },

        /**
         * Event system for pub/sub pattern
         */
        setupEventSystem: function() {
            this.on = function(event, callback) {
                if (!this.events[event]) this.events[event] = [];
                this.events[event].push(callback);
            };

            this.off = function(event, callback) {
                if (!this.events[event]) return;
                this.events[event] = this.events[event].filter(cb => cb !== callback);
            };

            this.emit = function(event, data) {
                if (!this.events[event]) return;
                this.events[event].forEach(callback => callback(data));
            };
        },

        /**
         * State management - simple reactive store
         */
        loadState: function() {
            const stored = localStorage.getItem('vapeultra_state');
            if (stored) {
                try {
                    this.state = JSON.parse(stored);
                } catch(e) {
                    console.warn('Failed to load state:', e);
                }
            }
        },

        saveState: function() {
            try {
                localStorage.setItem('vapeultra_state', JSON.stringify(this.state));
            } catch(e) {
                console.warn('Failed to save state:', e);
            }
        },

        setState: function(key, value) {
            this.state[key] = value;
            this.saveState();
            this.emit('state:change', { key, value });
        },

        getState: function(key, defaultValue) {
            return this.state.hasOwnProperty(key) ? this.state[key] : defaultValue;
        },

        /**
         * Module registry
         */
        registerModule: function(name, module) {
            this.modules[name] = module;
            if (typeof module.init === 'function') {
                module.init();
            }
            this.emit('module:registered', { name, module });
        },

        getModule: function(name) {
            return this.modules[name];
        },

        initializeModules: function() {
            // Auto-initialize registered modules
            Object.keys(this.modules).forEach(name => {
                const module = this.modules[name];
                if (typeof module.init === 'function' && !module.initialized) {
                    module.init();
                    module.initialized = true;
                }
            });
        },

        /**
         * Global event handlers
         */
        setupGlobalHandlers: function() {
            // Handle visibility change
            document.addEventListener('visibilitychange', () => {
                if (document.hidden) {
                    this.emit('app:background');
                } else {
                    this.emit('app:foreground');
                }
            });

            // Handle online/offline
            window.addEventListener('online', () => {
                this.emit('network:online');
                VapeUltra.Notifications.success('Connection restored');
            });

            window.addEventListener('offline', () => {
                this.emit('network:offline');
                VapeUltra.Notifications.warning('Connection lost');
            });

            // Handle errors globally
            window.addEventListener('error', (e) => {
                console.error('Global error:', e);
                this.emit('app:error', e);
            });

            // Handle unhandled promise rejections
            window.addEventListener('unhandledrejection', (e) => {
                console.error('Unhandled promise rejection:', e);
                this.emit('app:error', e);
            });
        },

        /**
         * Utility methods
         */
        debounce: function(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        },

        throttle: function(func, wait) {
            let inThrottle;
            return function(...args) {
                if (!inThrottle) {
                    func.apply(this, args);
                    inThrottle = true;
                    setTimeout(() => inThrottle = false, wait);
                }
            };
        },

        /**
         * Performance monitoring
         */
        measure: function(name, fn) {
            const start = performance.now();
            const result = fn();
            const end = performance.now();
            console.log(`⏱️ ${name}: ${(end - start).toFixed(2)}ms`);
            return result;
        }
    };

})();
