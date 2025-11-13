/**
 * =============================================================================
 * VAPEULTRA TOAST NOTIFICATION SYSTEM
 * =============================================================================
 *
 * Version: 2.0.0
 * Purpose: Beautiful toast notifications with queue management
 *
 * FEATURES:
 * - Types: success, error, warning, info, custom
 * - 9 positions (top/center/bottom × left/center/right)
 * - Auto-dismiss with countdown
 * - Progress bar indicator
 * - Action buttons
 * - Closable/non-closable
 * - Queue management (max concurrent toasts)
 * - Stacking & spacing
 * - Icons & custom icons
 * - Rich HTML content support
 *
 * USAGE:
 * ```javascript
 * // Success toast
 * VapeUltra.Toast.success('Changes saved successfully!');
 *
 * // Error toast
 * VapeUltra.Toast.error('An error occurred', { duration: 5000 });
 *
 * // Warning toast
 * VapeUltra.Toast.warning('Please review your input');
 *
 * // Info toast
 * VapeUltra.Toast.info('Welcome to CIS 2.0!');
 *
 * // Custom toast with action
 * VapeUltra.Toast.show({
 *     message: 'Item deleted',
 *     type: 'info',
 *     duration: 5000,
 *     actions: [
 *         {
 *             label: 'Undo',
 *             onClick: () => { console.log('Undo clicked'); }
 *         }
 *     ]
 * });
 *
 * // Toast with custom position
 * VapeUltra.Toast.success('Top center notification', {
 *     position: 'top-center'
 * });
 * ```
 *
 * =============================================================================
 */

(function(window) {
    'use strict';

    // Ensure VapeUltra namespace exists
    window.VapeUltra = window.VapeUltra || {};

    /**
     * Toast Class
     */
    class Toast {

        constructor() {
            this.config = {
                position: 'top-right',        // top-left, top-center, top-right, center-left, center-center, center-right, bottom-left, bottom-center, bottom-right
                duration: 4000,               // Auto-dismiss time (ms), 0 = never
                maxToasts: 5,                 // Max concurrent toasts
                spacing: 12,                  // Space between toasts (px)
                closable: true,               // Show close button
                progressBar: true,            // Show progress bar
                pauseOnHover: true,           // Pause auto-dismiss on hover
                animation: 'slide',           // slide, fade, bounce
                animationDuration: 300,       // Animation duration (ms)
                debug: false
            };

            this.toasts = [];                 // Active toasts
            this.toastCounter = 0;            // Unique toast ID
            this.containers = new Map();      // Position containers
        }

        /**
         * Initialize toast system
         * @param {Object} options
         */
        init(options = {}) {
            this.config = { ...this.config, ...options };

            if (this.config.debug) {
                console.log('✅ VapeUltra Toast initialized', this.config);
            }
        }

        /**
         * Show success toast
         * @param {string} message
         * @param {Object} options
         * @return {Object} Toast instance
         */
        success(message, options = {}) {
            return this.show({
                message: message,
                type: 'success',
                ...options
            });
        }

        /**
         * Show error toast
         * @param {string} message
         * @param {Object} options
         * @return {Object} Toast instance
         */
        error(message, options = {}) {
            return this.show({
                message: message,
                type: 'error',
                ...options
            });
        }

        /**
         * Show warning toast
         * @param {string} message
         * @param {Object} options
         * @return {Object} Toast instance
         */
        warning(message, options = {}) {
            return this.show({
                message: message,
                type: 'warning',
                ...options
            });
        }

        /**
         * Show info toast
         * @param {string} message
         * @param {Object} options
         * @return {Object} Toast instance
         */
        info(message, options = {}) {
            return this.show({
                message: message,
                type: 'info',
                ...options
            });
        }

        /**
         * Show custom toast
         * @param {Object} options
         * @return {Object} Toast instance
         */
        show(options = {}) {
            const defaults = {
                message: '',
                type: 'info',                 // success, error, warning, info, custom
                title: null,
                icon: null,                   // Custom icon class
                position: this.config.position,
                duration: this.config.duration,
                closable: this.config.closable,
                progressBar: this.config.progressBar,
                pauseOnHover: this.config.pauseOnHover,
                actions: [],                  // Array of { label, onClick }
                onClick: null,                // Toast click handler
                onClose: null                 // Toast close callback
            };

            const config = { ...defaults, ...options };

            // Generate unique toast ID
            const toastId = `vape-ultra-toast-${++this.toastCounter}`;

            // Check if we've reached max toasts
            const positionToasts = this.toasts.filter(t => t.position === config.position);
            if (positionToasts.length >= this.config.maxToasts) {
                // Remove oldest toast at this position
                this.dismiss(positionToasts[0].id);
            }

            // Get or create container for this position
            const container = this.getOrCreateContainer(config.position);

            // Build toast HTML
            const toastHTML = this.buildToastHTML(toastId, config);

            // Insert into container
            container.insertAdjacentHTML('beforeend', toastHTML);

            // Get toast element
            const toastElement = document.getElementById(toastId);

            // Create toast instance
            const toastInstance = {
                id: toastId,
                element: toastElement,
                config: config,
                position: config.position,
                timer: null,
                progressInterval: null,
                isPaused: false,
                dismiss: () => this.dismiss(toastId)
            };

            // Add to active toasts
            this.toasts.push(toastInstance);

            // Attach event listeners
            this.attachToastListeners(toastInstance);

            // Show toast (with animation)
            setTimeout(() => {
                toastElement.classList.add('show');

                // Start auto-dismiss timer
                if (config.duration > 0) {
                    this.startTimer(toastInstance);
                }
            }, 10);

            return toastInstance;
        }

        /**
         * Dismiss toast
         * @param {string} toastId
         */
        dismiss(toastId) {
            const toastIndex = this.toasts.findIndex(t => t.id === toastId);
            if (toastIndex === -1) return;

            const toast = this.toasts[toastIndex];
            const toastElement = toast.element;

            // Clear timers
            if (toast.timer) clearTimeout(toast.timer);
            if (toast.progressInterval) clearInterval(toast.progressInterval);

            // Remove 'show' class to trigger exit animation
            toastElement.classList.remove('show');

            // Wait for animation, then remove from DOM
            setTimeout(() => {
                toastElement.remove();

                // Remove from active toasts
                this.toasts.splice(toastIndex, 1);

                // Call onClose callback
                if (toast.config.onClose) {
                    toast.config.onClose();
                }

                // Clean up empty containers
                this.cleanupContainers();
            }, this.config.animationDuration);
        }

        /**
         * Dismiss all toasts
         * @param {string} position - Optional position filter
         */
        dismissAll(position = null) {
            const toastsToDismiss = position
                ? this.toasts.filter(t => t.position === position)
                : [...this.toasts];

            toastsToDismiss.forEach(toast => this.dismiss(toast.id));
        }

        /**
         * Build toast HTML
         * @param {string} toastId
         * @param {Object} config
         * @return {string}
         */
        buildToastHTML(toastId, config) {
            const typeClass = `toast-${config.type}`;
            const animationClass = `toast-${this.config.animation}`;
            const icon = config.icon || this.getIconForType(config.type);

            return `
                <div id="${toastId}"
                     class="vape-ultra-toast ${typeClass} ${animationClass}"
                     role="alert"
                     aria-live="polite">

                    ${config.progressBar && config.duration > 0 ? `
                        <div class="toast-progress">
                            <div class="toast-progress-bar"></div>
                        </div>
                    ` : ''}

                    <div class="toast-content">
                        ${icon ? `<i class="toast-icon ${icon}"></i>` : ''}

                        <div class="toast-body">
                            ${config.title ? `<div class="toast-title">${config.title}</div>` : ''}
                            <div class="toast-message">${config.message}</div>

                            ${config.actions.length > 0 ? `
                                <div class="toast-actions">
                                    ${this.buildActionsHTML(config.actions)}
                                </div>
                            ` : ''}
                        </div>

                        ${config.closable ? `
                            <button type="button" class="toast-close" aria-label="Close">
                                <i class="bi bi-x"></i>
                            </button>
                        ` : ''}
                    </div>
                </div>
            `;
        }

        /**
         * Build actions HTML
         * @param {Array} actions
         * @return {string}
         */
        buildActionsHTML(actions) {
            return actions.map((action, index) => `
                <button type="button"
                        class="toast-action-btn"
                        data-action-index="${index}">
                    ${action.label}
                </button>
            `).join('');
        }

        /**
         * Get icon for toast type
         * @param {string} type
         * @return {string}
         */
        getIconForType(type) {
            const icons = {
                success: 'bi bi-check-circle-fill',
                error: 'bi bi-x-circle-fill',
                warning: 'bi bi-exclamation-triangle-fill',
                info: 'bi bi-info-circle-fill'
            };

            return icons[type] || '';
        }

        /**
         * Attach toast event listeners
         * @param {Object} toast
         */
        attachToastListeners(toast) {
            const toastElement = toast.element;

            // Close button
            const closeButton = toastElement.querySelector('.toast-close');
            if (closeButton) {
                closeButton.addEventListener('click', (e) => {
                    e.stopPropagation();
                    this.dismiss(toast.id);
                });
            }

            // Action buttons
            const actionButtons = toastElement.querySelectorAll('.toast-action-btn');
            actionButtons.forEach((button) => {
                button.addEventListener('click', (e) => {
                    e.stopPropagation();
                    const actionIndex = parseInt(button.getAttribute('data-action-index'));
                    const action = toast.config.actions[actionIndex];

                    if (action && action.onClick) {
                        action.onClick(toast);

                        // Auto-dismiss after action (unless duration is 0)
                        if (toast.config.duration !== 0) {
                            this.dismiss(toast.id);
                        }
                    }
                });
            });

            // Toast click
            if (toast.config.onClick) {
                toastElement.addEventListener('click', () => {
                    toast.config.onClick(toast);
                });
            }

            // Pause on hover
            if (toast.config.pauseOnHover && toast.config.duration > 0) {
                toastElement.addEventListener('mouseenter', () => {
                    this.pauseTimer(toast);
                });

                toastElement.addEventListener('mouseleave', () => {
                    this.resumeTimer(toast);
                });
            }
        }

        /**
         * Start auto-dismiss timer
         * @param {Object} toast
         */
        startTimer(toast) {
            const startTime = Date.now();
            const duration = toast.config.duration;

            // Auto-dismiss timer
            toast.timer = setTimeout(() => {
                this.dismiss(toast.id);
            }, duration);

            // Progress bar animation
            if (toast.config.progressBar) {
                const progressBar = toast.element.querySelector('.toast-progress-bar');
                if (progressBar) {
                    progressBar.style.transition = `width ${duration}ms linear`;
                    progressBar.style.width = '0%';
                }
            }
        }

        /**
         * Pause timer
         * @param {Object} toast
         */
        pauseTimer(toast) {
            if (toast.isPaused) return;

            toast.isPaused = true;

            // Pause auto-dismiss
            if (toast.timer) {
                clearTimeout(toast.timer);
                toast.timer = null;
            }

            // Pause progress bar
            const progressBar = toast.element.querySelector('.toast-progress-bar');
            if (progressBar) {
                const currentWidth = progressBar.offsetWidth;
                const containerWidth = progressBar.parentElement.offsetWidth;
                const percentage = (currentWidth / containerWidth) * 100;

                progressBar.style.transition = 'none';
                progressBar.style.width = `${percentage}%`;

                // Store remaining time
                toast.remainingTime = (percentage / 100) * toast.config.duration;
            }
        }

        /**
         * Resume timer
         * @param {Object} toast
         */
        resumeTimer(toast) {
            if (!toast.isPaused) return;

            toast.isPaused = false;

            const remainingTime = toast.remainingTime || toast.config.duration;

            // Resume auto-dismiss
            toast.timer = setTimeout(() => {
                this.dismiss(toast.id);
            }, remainingTime);

            // Resume progress bar
            const progressBar = toast.element.querySelector('.toast-progress-bar');
            if (progressBar) {
                progressBar.style.transition = `width ${remainingTime}ms linear`;
                progressBar.style.width = '0%';
            }
        }

        /**
         * Get or create container for position
         * @param {string} position
         * @return {HTMLElement}
         */
        getOrCreateContainer(position) {
            if (this.containers.has(position)) {
                return this.containers.get(position);
            }

            // Create container
            const container = document.createElement('div');
            container.className = `vape-ultra-toast-container toast-${position}`;
            container.setAttribute('aria-live', 'polite');
            container.setAttribute('aria-atomic', 'true');

            document.body.appendChild(container);

            this.containers.set(position, container);
            return container;
        }

        /**
         * Clean up empty containers
         */
        cleanupContainers() {
            this.containers.forEach((container, position) => {
                if (container.children.length === 0) {
                    container.remove();
                    this.containers.delete(position);
                }
            });
        }

        /**
         * Get active toast count
         * @param {string} position - Optional position filter
         * @return {number}
         */
        getActiveCount(position = null) {
            return position
                ? this.toasts.filter(t => t.position === position).length
                : this.toasts.length;
        }
    }

    // Create global instance
    window.VapeUltra.Toast = new Toast();

    // Auto-initialize
    window.VapeUltra.Toast.init();

})(window);
