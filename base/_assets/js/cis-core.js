/**
 * CIS Core JavaScript
 * Core utilities and helpers for CIS applications
 */

(function() {
    'use strict';

    // CIS Namespace
    window.CIS = window.CIS || {};

    /**
     * Show toast notification
     */
    CIS.toast = function(message, type = 'info', duration = 3000) {
        // Check if Bootstrap toast container exists
        let container = document.getElementById('toastContainer');
        if (!container) {
            container = document.createElement('div');
            container.id = 'toastContainer';
            container.className = 'toast-container position-fixed top-0 end-0 p-3';
            document.body.appendChild(container);
        }

        // Create toast element
        const toastId = 'toast-' + Date.now();
        const toastHtml = `
            <div id="${toastId}" class="toast align-items-center text-white bg-${type} border-0" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="d-flex">
                    <div class="toast-body">${message}</div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
            </div>
        `;

        container.insertAdjacentHTML('beforeend', toastHtml);

        const toastElement = document.getElementById(toastId);
        if (typeof bootstrap !== 'undefined' && bootstrap.Toast) {
            const toast = new bootstrap.Toast(toastElement, { delay: duration });
            toast.show();

            // Remove from DOM after hidden
            toastElement.addEventListener('hidden.bs.toast', function() {
                toastElement.remove();
            });
        } else {
            // Fallback if Bootstrap not loaded
            toastElement.style.display = 'block';
            setTimeout(() => toastElement.remove(), duration);
        }
    };

    /**
     * Confirm dialog wrapper
     */
    CIS.confirm = function(message, callback) {
        if (confirm(message)) {
            callback();
        }
    };

    /**
     * Loading overlay
     */
    CIS.showLoading = function(message = 'Loading...') {
        let overlay = document.getElementById('globalActivity');
        if (!overlay) {
            console.warn('Loading overlay #globalActivity not found');
            return;
        }

        const title = overlay.querySelector('#gaTitle');
        const sub = overlay.querySelector('#gaSub');

        if (title) title.textContent = message;
        if (sub) sub.textContent = 'Please wait';

        overlay.style.display = 'flex';
    };

    CIS.hideLoading = function() {
        const overlay = document.getElementById('globalActivity');
        if (overlay) {
            overlay.style.display = 'none';
        }
    };

    /**
     * AJAX helper
     */
    CIS.ajax = function(url, options = {}) {
        const defaults = {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        };

        const config = { ...defaults, ...options };

        return fetch(url, config)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .catch(error => {
                console.error('AJAX Error:', error);
                throw error;
            });
    };

    /**
     * Format date
     */
    CIS.formatDate = function(date, format = 'Y-m-d H:i:s') {
        if (!(date instanceof Date)) {
            date = new Date(date);
        }

        if (isNaN(date.getTime())) {
            return 'Invalid Date';
        }

        const pad = (n) => n.toString().padStart(2, '0');

        const replacements = {
            'Y': date.getFullYear(),
            'm': pad(date.getMonth() + 1),
            'd': pad(date.getDate()),
            'H': pad(date.getHours()),
            'i': pad(date.getMinutes()),
            's': pad(date.getSeconds())
        };

        return format.replace(/Y|m|d|H|i|s/g, match => replacements[match]);
    };

    /**
     * Debounce function
     */
    CIS.debounce = function(func, wait = 300) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    };

    /**
     * Initialize tooltips (Bootstrap)
     */
    CIS.initTooltips = function() {
        if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        }
    };

    /**
     * Initialize popovers (Bootstrap)
     */
    CIS.initPopovers = function() {
        if (typeof bootstrap !== 'undefined' && bootstrap.Popover) {
            const popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
            popoverTriggerList.map(function (popoverTriggerEl) {
                return new bootstrap.Popover(popoverTriggerEl);
            });
        }
    };

    /**
     * Auto-initialize on DOM ready
     */
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            CIS.initTooltips();
            CIS.initPopovers();
        });
    } else {
        CIS.initTooltips();
        CIS.initPopovers();
    }

    // Log CIS core loaded
    console.log('✅ CIS Core loaded');
})();
