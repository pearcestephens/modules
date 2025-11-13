/**
 * =============================================================================
 * VAPEULTRA MODAL SYSTEM
 * =============================================================================
 *
 * Version: 2.0.0
 * Purpose: Enterprise-grade modal dialog system with accessibility
 *
 * FEATURES:
 * - Confirm, Alert, Prompt dialogs
 * - Custom content modals
 * - Promise-based API
 * - Size options (sm, md, lg, xl, fullscreen)
 * - Keyboard navigation (ESC, TAB trap)
 * - Focus management & restoration
 * - Backdrop click handling
 * - Stackable modals (z-index management)
 * - Animation support
 * - Accessibility (ARIA, screen readers)
 *
 * USAGE:
 * ```javascript
 * // Alert
 * VapeUltra.Modal.alert({
 *     title: 'Success',
 *     message: 'Your changes have been saved.',
 *     type: 'success'
 * }).then(() => console.log('Alert closed'));
 *
 * // Confirm
 * VapeUltra.Modal.confirm({
 *     title: 'Delete Item',
 *     message: 'Are you sure you want to delete this item?',
 *     type: 'danger'
 * }).then(result => {
 *     if (result) {
 *         console.log('User confirmed');
 *     }
 * });
 *
 * // Prompt
 * VapeUltra.Modal.prompt({
 *     title: 'Enter Your Name',
 *     message: 'Please enter your full name:',
 *     defaultValue: 'John Doe'
 * }).then(value => {
 *     if (value !== null) {
 *         console.log('User entered:', value);
 *     }
 * });
 *
 * // Custom modal
 * VapeUltra.Modal.open({
 *     title: 'Custom Modal',
 *     content: '<div>Your custom HTML</div>',
 *     size: 'lg',
 *     buttons: [
 *         { label: 'Cancel', variant: 'secondary', onClick: (modal) => modal.close() },
 *         { label: 'Save', variant: 'primary', onClick: (modal) => { /* save */ } }
 *     ]
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
     * Modal Class
     */
    class Modal {

        constructor() {
            this.config = {
                backdropClose: true,     // Close on backdrop click
                keyboardClose: true,     // Close on ESC
                animation: true,         // Enable animations
                animationDuration: 300,  // Animation duration (ms)
                debug: false
            };

            this.activeModals = [];      // Stack of active modals
            this.modalCounter = 0;       // Unique modal ID counter
            this.previousFocus = null;   // Focus restoration
        }

        /**
         * Initialize modal system
         * @param {Object} options
         */
        init(options = {}) {
            this.config = { ...this.config, ...options };

            // Attach global keyboard listener
            document.addEventListener('keydown', (e) => this.handleKeydown(e));

            if (this.config.debug) {
                console.log('✅ VapeUltra Modal initialized', this.config);
            }
        }

        /**
         * Alert dialog
         * @param {Object} options
         * @return {Promise}
         */
        alert(options = {}) {
            const defaults = {
                title: 'Alert',
                message: '',
                type: 'info',
                confirmLabel: 'OK',
                size: 'md'
            };

            const config = { ...defaults, ...options };

            return new Promise((resolve) => {
                this.open({
                    title: config.title,
                    content: this.buildAlertContent(config.message, config.type),
                    size: config.size,
                    buttons: [
                        {
                            label: config.confirmLabel,
                            variant: this.getVariantForType(config.type),
                            onClick: (modal) => {
                                modal.close();
                                resolve();
                            }
                        }
                    ],
                    onClose: () => resolve()
                });
            });
        }

        /**
         * Confirm dialog
         * @param {Object} options
         * @return {Promise<boolean>}
         */
        confirm(options = {}) {
            const defaults = {
                title: 'Confirm',
                message: '',
                type: 'warning',
                confirmLabel: 'Confirm',
                cancelLabel: 'Cancel',
                size: 'md'
            };

            const config = { ...defaults, ...options };

            return new Promise((resolve) => {
                this.open({
                    title: config.title,
                    content: this.buildAlertContent(config.message, config.type),
                    size: config.size,
                    buttons: [
                        {
                            label: config.cancelLabel,
                            variant: 'secondary',
                            onClick: (modal) => {
                                modal.close();
                                resolve(false);
                            }
                        },
                        {
                            label: config.confirmLabel,
                            variant: this.getVariantForType(config.type),
                            onClick: (modal) => {
                                modal.close();
                                resolve(true);
                            }
                        }
                    ],
                    onClose: () => resolve(false)
                });
            });
        }

        /**
         * Prompt dialog
         * @param {Object} options
         * @return {Promise<string|null>}
         */
        prompt(options = {}) {
            const defaults = {
                title: 'Prompt',
                message: '',
                defaultValue: '',
                placeholder: '',
                inputType: 'text',
                confirmLabel: 'OK',
                cancelLabel: 'Cancel',
                size: 'md'
            };

            const config = { ...defaults, ...options };
            const inputId = `modal-prompt-input-${this.modalCounter}`;

            return new Promise((resolve) => {
                this.open({
                    title: config.title,
                    content: this.buildPromptContent(config.message, inputId, config.defaultValue, config.placeholder, config.inputType),
                    size: config.size,
                    buttons: [
                        {
                            label: config.cancelLabel,
                            variant: 'secondary',
                            onClick: (modal) => {
                                modal.close();
                                resolve(null);
                            }
                        },
                        {
                            label: config.confirmLabel,
                            variant: 'primary',
                            onClick: (modal) => {
                                const input = document.getElementById(inputId);
                                const value = input ? input.value : '';
                                modal.close();
                                resolve(value);
                            }
                        }
                    ],
                    onClose: () => resolve(null),
                    onOpen: () => {
                        // Focus input after modal opens
                        setTimeout(() => {
                            const input = document.getElementById(inputId);
                            if (input) {
                                input.focus();
                                input.select();
                            }
                        }, 100);
                    }
                });
            });
        }

        /**
         * Open custom modal
         * @param {Object} options
         * @return {Object} Modal instance
         */
        open(options = {}) {
            const defaults = {
                title: 'Modal',
                content: '',
                size: 'md',               // sm, md, lg, xl, fullscreen
                buttons: [],
                closable: true,           // Show close button
                backdropClose: this.config.backdropClose,
                keyboardClose: this.config.keyboardClose,
                onOpen: null,
                onClose: null
            };

            const config = { ...defaults, ...options };

            // Generate unique modal ID
            const modalId = `vape-ultra-modal-${++this.modalCounter}`;

            // Store previous focus for restoration
            this.previousFocus = document.activeElement;

            // Build modal HTML
            const modalHTML = this.buildModalHTML(modalId, config);

            // Insert into DOM
            document.body.insertAdjacentHTML('beforeend', modalHTML);

            // Get modal element
            const modalElement = document.getElementById(modalId);

            // Create modal instance
            const modalInstance = {
                id: modalId,
                element: modalElement,
                config: config,
                close: () => this.closeModal(modalId)
            };

            // Add to active modals stack
            this.activeModals.push(modalInstance);

            // Update z-index based on stack position
            this.updateZIndex();

            // Attach event listeners
            this.attachModalListeners(modalInstance);

            // Show modal (with animation)
            setTimeout(() => {
                modalElement.classList.add('show');
                document.body.classList.add('modal-open');

                // Focus first focusable element
                this.focusFirstElement(modalElement);

                // Call onOpen callback
                if (config.onOpen) {
                    config.onOpen(modalInstance);
                }
            }, 10);

            return modalInstance;
        }

        /**
         * Close modal
         * @param {string} modalId
         */
        closeModal(modalId) {
            const modalIndex = this.activeModals.findIndex(m => m.id === modalId);
            if (modalIndex === -1) return;

            const modal = this.activeModals[modalIndex];
            const modalElement = modal.element;

            // Remove 'show' class to trigger exit animation
            modalElement.classList.remove('show');

            // Wait for animation, then remove from DOM
            setTimeout(() => {
                // Remove from DOM
                modalElement.remove();

                // Remove from active modals
                this.activeModals.splice(modalIndex, 1);

                // If no more modals, remove body class
                if (this.activeModals.length === 0) {
                    document.body.classList.remove('modal-open');

                    // Restore previous focus
                    if (this.previousFocus && this.previousFocus.focus) {
                        this.previousFocus.focus();
                    }
                }

                // Update z-index for remaining modals
                this.updateZIndex();

                // Call onClose callback
                if (modal.config.onClose) {
                    modal.config.onClose();
                }
            }, this.config.animationDuration);
        }

        /**
         * Close all modals
         */
        closeAll() {
            [...this.activeModals].forEach(modal => {
                this.closeModal(modal.id);
            });
        }

        /**
         * Build modal HTML
         * @param {string} modalId
         * @param {Object} config
         * @return {string}
         */
        buildModalHTML(modalId, config) {
            const sizeClass = `modal-${config.size}`;
            const animationClass = this.config.animation ? 'modal-animated' : '';

            return `
                <div id="${modalId}" class="vape-ultra-modal ${animationClass}" role="dialog" aria-modal="true" aria-labelledby="${modalId}-title">
                    <div class="modal-backdrop"></div>
                    <div class="modal-container">
                        <div class="modal-dialog ${sizeClass}">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h3 id="${modalId}-title" class="modal-title">${config.title}</h3>
                                    ${config.closable ? `<button type="button" class="modal-close" aria-label="Close">&times;</button>` : ''}
                                </div>
                                <div class="modal-body">
                                    ${config.content}
                                </div>
                                ${config.buttons.length > 0 ? `
                                    <div class="modal-footer">
                                        ${this.buildButtonsHTML(config.buttons)}
                                    </div>
                                ` : ''}
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }

        /**
         * Build buttons HTML
         * @param {Array} buttons
         * @return {string}
         */
        buildButtonsHTML(buttons) {
            return buttons.map((button, index) => {
                const variant = button.variant || 'primary';
                return `
                    <button type="button"
                            class="btn btn-${variant}"
                            data-modal-button="${index}">
                        ${button.label}
                    </button>
                `;
            }).join('');
        }

        /**
         * Build alert content
         * @param {string} message
         * @param {string} type
         * @return {string}
         */
        buildAlertContent(message, type) {
            const icons = {
                success: 'bi-check-circle-fill',
                error: 'bi-x-circle-fill',
                warning: 'bi-exclamation-triangle-fill',
                info: 'bi-info-circle-fill'
            };

            const icon = icons[type] || icons.info;

            return `
                <div class="alert-content alert-${type}">
                    <i class="bi ${icon} alert-icon"></i>
                    <div class="alert-message">${message}</div>
                </div>
            `;
        }

        /**
         * Build prompt content
         * @param {string} message
         * @param {string} inputId
         * @param {string} defaultValue
         * @param {string} placeholder
         * @param {string} inputType
         * @return {string}
         */
        buildPromptContent(message, inputId, defaultValue, placeholder, inputType) {
            return `
                <div class="prompt-content">
                    <div class="prompt-message">${message}</div>
                    <input type="${inputType}"
                           id="${inputId}"
                           class="form-control"
                           value="${defaultValue}"
                           placeholder="${placeholder}">
                </div>
            `;
        }

        /**
         * Attach modal event listeners
         * @param {Object} modal
         */
        attachModalListeners(modal) {
            const modalElement = modal.element;

            // Close button
            const closeButton = modalElement.querySelector('.modal-close');
            if (closeButton) {
                closeButton.addEventListener('click', () => this.closeModal(modal.id));
            }

            // Backdrop click
            if (modal.config.backdropClose) {
                const backdrop = modalElement.querySelector('.modal-backdrop');
                backdrop.addEventListener('click', () => this.closeModal(modal.id));
            }

            // Action buttons
            const buttons = modalElement.querySelectorAll('[data-modal-button]');
            buttons.forEach((button) => {
                const buttonIndex = parseInt(button.getAttribute('data-modal-button'));
                const buttonConfig = modal.config.buttons[buttonIndex];

                if (buttonConfig && buttonConfig.onClick) {
                    button.addEventListener('click', () => buttonConfig.onClick(modal));
                }
            });
        }

        /**
         * Handle keyboard events
         * @param {KeyboardEvent} e
         */
        handleKeydown(e) {
            if (this.activeModals.length === 0) return;

            const topModal = this.activeModals[this.activeModals.length - 1];

            // ESC key - close modal
            if (e.key === 'Escape' && topModal.config.keyboardClose) {
                e.preventDefault();
                this.closeModal(topModal.id);
            }

            // TAB key - trap focus within modal
            if (e.key === 'Tab') {
                this.trapFocus(e, topModal.element);
            }
        }

        /**
         * Trap focus within modal
         * @param {KeyboardEvent} e
         * @param {HTMLElement} modalElement
         */
        trapFocus(e, modalElement) {
            const focusableElements = modalElement.querySelectorAll(
                'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
            );

            const firstElement = focusableElements[0];
            const lastElement = focusableElements[focusableElements.length - 1];

            if (e.shiftKey && document.activeElement === firstElement) {
                e.preventDefault();
                lastElement.focus();
            } else if (!e.shiftKey && document.activeElement === lastElement) {
                e.preventDefault();
                firstElement.focus();
            }
        }

        /**
         * Focus first focusable element in modal
         * @param {HTMLElement} modalElement
         */
        focusFirstElement(modalElement) {
            const focusableElements = modalElement.querySelectorAll(
                'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
            );

            if (focusableElements.length > 0) {
                focusableElements[0].focus();
            }
        }

        /**
         * Update z-index for stacked modals
         */
        updateZIndex() {
            const baseZIndex = parseInt(getComputedStyle(document.documentElement).getPropertyValue('--z-modal')) || 1040;

            this.activeModals.forEach((modal, index) => {
                modal.element.style.zIndex = baseZIndex + (index * 10);
            });
        }

        /**
         * Get button variant for alert type
         * @param {string} type
         * @return {string}
         */
        getVariantForType(type) {
            const variants = {
                success: 'success',
                error: 'danger',
                warning: 'warning',
                info: 'primary'
            };

            return variants[type] || 'primary';
        }

        /**
         * Get active modal count
         * @return {number}
         */
        getActiveCount() {
            return this.activeModals.length;
        }
    }

    // Create global instance
    window.VapeUltra.Modal = new Modal();

    // Auto-initialize
    window.VapeUltra.Modal.init();

})(window);
