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

    /**
     * Right Sidebar Toggle Module
     */
    VapeUltra.SidebarToggle = {
        initialized: false,

        init: function() {
            if (this.initialized) return;

            const toggleBtn = document.getElementById('toggle-right-sidebar');
            const sidebar = document.querySelector('.sidebar-right');
            const appGrid = document.querySelector('.app-grid');

            if (!toggleBtn || !sidebar) return;

            // Load saved state
            const isCollapsed = VapeUltra.Core.getState('sidebar_right_collapsed', false);
            if (isCollapsed) {
                this.collapse(sidebar, appGrid, toggleBtn, false);
            }

            // Setup click handler
            toggleBtn.addEventListener('click', () => {
                const collapsed = sidebar.classList.contains('collapsed');
                if (collapsed) {
                    this.expand(sidebar, appGrid, toggleBtn);
                } else {
                    this.collapse(sidebar, appGrid, toggleBtn);
                }
            });

            // Keyboard shortcut: Ctrl/Cmd + ]
            document.addEventListener('keydown', (e) => {
                if ((e.ctrlKey || e.metaKey) && e.key === ']') {
                    e.preventDefault();
                    toggleBtn.click();
                }
            });

            this.initialized = true;
            console.log('✅ Sidebar toggle initialized');
        },

        collapse: function(sidebar, appGrid, toggleBtn, animate = true) {
            if (!animate) {
                sidebar.style.transition = 'none';
                appGrid.style.transition = 'none';
            }

            sidebar.classList.add('collapsed');
            appGrid?.classList.add('sidebar-right-collapsed');
            toggleBtn?.classList.add('active');

            VapeUltra.Core.setState('sidebar_right_collapsed', true);
            VapeUltra.Core.emit('sidebar:collapsed');

            if (!animate) {
                setTimeout(() => {
                    sidebar.style.transition = '';
                    appGrid.style.transition = '';
                }, 50);
            }
        },

        expand: function(sidebar, appGrid, toggleBtn) {
            sidebar.classList.remove('collapsed');
            appGrid?.classList.remove('sidebar-right-collapsed');
            toggleBtn?.classList.remove('active');

            VapeUltra.Core.setState('sidebar_right_collapsed', false);
            VapeUltra.Core.emit('sidebar:expanded');
        },

        toggle: function() {
            document.getElementById('toggle-right-sidebar')?.click();
        }
    };

    /**
     * Right Sidebar Hover-Reveal
     * Hover right edge for 1 second to reveal, 3 second delay to hide
     */
    VapeUltra.RightSidebarHover = {
        trigger: null,
        sidebar: null,
        closeBtn: null,
        showTimeout: null,
        hideTimeout: null,
        
        init: function() {
            // Only activate on messaging page
            if (!document.body.classList.contains('messaging-page')) {
                return;
            }
            
            this.trigger = document.getElementById('sidebar-right-trigger');
            this.sidebar = document.getElementById('app-sidebar-right');
            this.closeBtn = document.getElementById('sidebar-right-close-btn');
            
            if (!this.trigger || !this.sidebar) return;
            
            console.log('👉 Right Sidebar Hover-Reveal initialized (messaging page only)');
            
            // Hover right edge - start 1 second countdown to show
            this.trigger.addEventListener('mouseenter', () => {
                this.startShowCountdown();
            });
            
            this.trigger.addEventListener('mouseleave', () => {
                this.cancelShowCountdown();
            });
            
            // Sidebar mouse enter - keep it open, cancel hide countdown
            this.sidebar.addEventListener('mouseenter', () => {
                this.cancelHideCountdown();
            });
            
            // Sidebar mouse leave - start 3 second countdown to hide
            this.sidebar.addEventListener('mouseleave', () => {
                this.startHideCountdown();
            });
            
            // Close button click - immediate hide
            if (this.closeBtn) {
                this.closeBtn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    this.hideSidebar();
                });
            }
        },
        
        startShowCountdown: function() {
            this.cancelShowCountdown();
            
            this.showTimeout = setTimeout(() => {
                this.showSidebar();
            }, 1000); // 1 second hover required
            
            console.log('⏱️ Right sidebar will show in 1 second...');
        },
        
        cancelShowCountdown: function() {
            if (this.showTimeout) {
                clearTimeout(this.showTimeout);
                this.showTimeout = null;
            }
        },
        
        startHideCountdown: function() {
            this.cancelHideCountdown();
            
            this.hideTimeout = setTimeout(() => {
                this.hideSidebar();
            }, 3000); // 3 second delay
            
            console.log('⏱️ Right sidebar will hide in 3 seconds...');
        },
        
        cancelHideCountdown: function() {
            if (this.hideTimeout) {
                clearTimeout(this.hideTimeout);
                this.hideTimeout = null;
            }
        },
        
        showSidebar: function() {
            if (this.sidebar) {
                this.sidebar.classList.add('sidebar-revealed');
                console.log('👋 Right sidebar shown');
            }
        },
        
        hideSidebar: function() {
            if (this.sidebar) {
                this.sidebar.classList.remove('sidebar-revealed');
                this.cancelShowCountdown();
                this.cancelHideCountdown();
                console.log('👋 Right sidebar hidden');
            }
        }
    };    /**
     * Hover-Reveal Sidebar for Desktop Breakpoint
     * Auto-collapse at 1200px, reveal on hover, 3-second delay to close
     */
    VapeUltra.HoverSidebar = {
        sidebar: null,
        hideTimeout: null,

        init: function() {
            this.sidebar = document.getElementById('app-sidebar');

            if (!this.sidebar) return;

            console.log('🎯 Hover-Reveal Sidebar initialized');

            // Mouse leave - start 3 second countdown
            this.sidebar.addEventListener('mouseleave', () => {
                this.startHideCountdown();
            });

            // Mouse enter - cancel countdown
            this.sidebar.addEventListener('mouseenter', () => {
                this.cancelHideCountdown();
            });
        },        startHideCountdown: function() {
            this.cancelHideCountdown(); // Clear any existing timeout

            this.hideTimeout = setTimeout(() => {
                this.hideSidebar();
            }, 3000); // 3 second delay

            console.log('⏱️ Sidebar will hide in 3 seconds...');
        },

        cancelHideCountdown: function() {
            if (this.hideTimeout) {
                clearTimeout(this.hideTimeout);
                this.hideTimeout = null;
                console.log('⏹️ Sidebar hide cancelled');
            }
        },

        hideSidebar: function() {
            if (this.sidebar) {
                this.sidebar.classList.remove('sidebar-revealed');
                console.log('👋 Sidebar hidden');
            }
        }
    };

    // Auto-initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            VapeUltra.Core.init();
            VapeUltra.SidebarToggle.init();
            VapeUltra.RightSidebarHover.init();
            VapeUltra.HoverSidebar.init();
        });
    } else {
        VapeUltra.Core.init();
        VapeUltra.SidebarToggle.init();
        VapeUltra.RightSidebarHover.init();
        VapeUltra.HoverSidebar.init();
    }

})();
