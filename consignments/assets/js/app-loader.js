/**
 * Transfer Manager - Modern Module Loader
 *
 * Industry-standard async module loading with dependency management
 * Replaces numbered file loading with intelligent auto-loader
 *
 * @version 2.0.0
 * @pattern Module Pattern + Dynamic Imports
 */

(function(window, document) {
  'use strict';

  const TransferApp = {
    version: '2.0.0',
    loaded: {},
    config: null,

    /**
     * Module registry with dependency chain
     * Order matters for initialization
     */
    modules: [
      { name: 'config', file: '00-config-init.js', deps: [] },
      { name: 'core', file: 'modules/core-helpers.js', deps: ['config'] },
      { name: 'ui', file: 'modules/ui-components.js', deps: ['config', 'core'] },
      { name: 'transfers', file: 'modules/transfer-functions.js', deps: ['config', 'core'] },
      { name: 'list', file: 'modules/list-refresh.js', deps: ['config', 'core', 'ui'] },
      { name: 'modal', file: 'modules/detail-modal.js', deps: ['config', 'core', 'ui'] },
      { name: 'events', file: 'modules/event-listeners.js', deps: ['config', 'core', 'ui', 'transfers'] },
      { name: 'init', file: '07-init.js', deps: ['config', 'core', 'ui', 'events', 'list'] },
      { name: 'boot', file: '08-dom-ready.js', deps: ['init'] }
    ],

    /**
     * Base path for module loading
     */
    basePath: '/modules/consignments/assets/js/',

    /**
     * Load a script dynamically
     */
    loadScript: function(src) {
      return new Promise((resolve, reject) => {
        const script = document.createElement('script');
        script.src = this.basePath + src;
        script.onload = () => resolve(src);
        script.onerror = () => reject(new Error(`Failed to load: ${src}`));
        document.head.appendChild(script);
      });
    },

    /**
     * Check if module dependencies are satisfied
     */
    depsReady: function(deps) {
      return deps.every(dep => this.loaded[dep] === true);
    },

    /**
     * Load modules in correct order respecting dependencies
     */
    loadModules: async function() {
      console.log('🚀 TransferApp Module Loader v' + this.version);
      console.log('📦 Loading', this.modules.length, 'modules...');

      const pending = [...this.modules];
      const maxAttempts = pending.length * 2; // Prevent infinite loops
      let attempts = 0;

      while (pending.length > 0 && attempts < maxAttempts) {
        attempts++;

        for (let i = pending.length - 1; i >= 0; i--) {
          const module = pending[i];

          // Check if dependencies are satisfied
          if (this.depsReady(module.deps)) {
            try {
              console.log(`  ⏳ Loading ${module.name}... (${module.file})`);
              await this.loadScript(module.file);
              this.loaded[module.name] = true;
              console.log(`  ✅ ${module.name} loaded`);
              pending.splice(i, 1);
            } catch (error) {
              console.error(`  ❌ Failed to load ${module.name}:`, error);
              throw error;
            }
          }
        }

        // Small delay between rounds to allow scripts to initialize
        if (pending.length > 0) {
          await new Promise(resolve => setTimeout(resolve, 50));
        }
      }

      if (pending.length > 0) {
        console.error('❌ Failed to load modules (dependency deadlock):',
          pending.map(m => m.name).join(', '));
        throw new Error('Module loading failed: unresolved dependencies');
      }

      console.log('✅ All modules loaded successfully!');
      return true;
    },

    /**
     * Initialize the application
     */
    init: async function() {
      try {
        const startTime = performance.now();

        await this.loadModules();

        const loadTime = (performance.now() - startTime).toFixed(2);
        console.log(`🎉 TransferApp ready in ${loadTime}ms`);

        // Trigger custom event for other scripts
        window.dispatchEvent(new CustomEvent('transferapp:ready', {
          detail: { version: this.version, loadTime }
        }));

      } catch (error) {
        console.error('💥 TransferApp initialization failed:', error);
        alert('Failed to initialize Transfer Manager. Please refresh the page.');
      }
    }
  };

  // Expose globally
  window.TransferApp = TransferApp;

  // Auto-start when DOM is ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => TransferApp.init());
  } else {
    TransferApp.init();
  }

})(window, document);
