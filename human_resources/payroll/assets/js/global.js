/**
 * Payroll Module - Global JavaScript
 * Version: 1.0.0
 */

const PayrollGlobal = {
    /**
     * API Base URL
     */
    apiBase: '/modules/human_resources/payroll/api',

    /**
     * Make API request with CSRF token
     */
    async apiRequest(endpoint, options = {}) {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
                       || document.querySelector('input[name="csrf_token"]')?.value;

        const defaultOptions = {
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken || '',
            },
            credentials: 'same-origin',
        };

        const mergedOptions = {
            ...defaultOptions,
            ...options,
            headers: {
                ...defaultOptions.headers,
                ...options.headers,
            },
        };

        try {
            const response = await fetch(`${this.apiBase}${endpoint}`, mergedOptions);

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }

            const data = await response.json();

            if (!data.success) {
                throw new Error(data.message || 'Request failed');
            }

            return data;
        } catch (error) {
            console.error('API Request Error:', error);
            this.showNotification(error.message, 'error');
            throw error;
        }
    },

    /**
     * Show notification message
     */
    showNotification(message, type = 'info', duration = 5000) {
        const container = document.getElementById('notification-container') || this.createNotificationContainer();

        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.innerHTML = `
            <span class="notification-message">${this.escapeHtml(message)}</span>
            <button class="notification-close" onclick="this.parentElement.remove()">×</button>
        `;

        container.appendChild(notification);

        setTimeout(() => {
            notification.classList.add('fade-out');
            setTimeout(() => notification.remove(), 300);
        }, duration);
    },

    /**
     * Create notification container
     */
    createNotificationContainer() {
        const container = document.createElement('div');
        container.id = 'notification-container';
        container.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            display: flex;
            flex-direction: column;
            gap: 10px;
            max-width: 400px;
        `;
        document.body.appendChild(container);

        // Add notification styles
        if (!document.getElementById('notification-styles')) {
            const style = document.createElement('style');
            style.id = 'notification-styles';
            style.textContent = `
                .notification {
                    padding: 12px 16px;
                    border-radius: 6px;
                    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                    display: flex;
                    align-items: center;
                    gap: 12px;
                    animation: slideIn 0.3s ease;
                }
                .notification-info { background: #3b82f6; color: white; }
                .notification-success { background: #10b981; color: white; }
                .notification-warning { background: #f59e0b; color: white; }
                .notification-error { background: #ef4444; color: white; }
                .notification-message { flex: 1; font-size: 14px; }
                .notification-close {
                    background: none;
                    border: none;
                    color: white;
                    font-size: 20px;
                    cursor: pointer;
                    opacity: 0.8;
                    padding: 0;
                    width: 24px;
                    height: 24px;
                }
                .notification-close:hover { opacity: 1; }
                .notification.fade-out { animation: fadeOut 0.3s ease; opacity: 0; }
                @keyframes slideIn {
                    from { transform: translateX(400px); opacity: 0; }
                    to { transform: translateX(0); opacity: 1; }
                }
                @keyframes fadeOut {
                    to { opacity: 0; transform: translateX(400px); }
                }
            `;
            document.head.appendChild(style);
        }

        return container;
    },

    /**
     * Escape HTML to prevent XSS
     */
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    },

    /**
     * Format currency
     */
    formatCurrency(amount, currency = 'NZD') {
        return new Intl.NumberFormat('en-NZ', {
            style: 'currency',
            currency: currency,
        }).format(amount);
    },

    /**
     * Format date
     */
    formatDate(dateString, format = 'medium') {
        const date = new Date(dateString);
        const options = {
            short: { year: 'numeric', month: 'short', day: 'numeric' },
            medium: { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' },
            long: { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit' },
        };
        return date.toLocaleDateString('en-NZ', options[format] || options.medium);
    },

    /**
     * Confirm action with modal
     */
    async confirm(message, title = 'Confirm Action') {
        return new Promise((resolve) => {
            const modal = document.createElement('div');
            modal.className = 'confirm-modal-overlay';
            modal.innerHTML = `
                <div class="confirm-modal">
                    <h3 class="confirm-modal-title">${this.escapeHtml(title)}</h3>
                    <p class="confirm-modal-message">${this.escapeHtml(message)}</p>
                    <div class="confirm-modal-actions">
                        <button class="btn btn-secondary" id="confirm-cancel">Cancel</button>
                        <button class="btn btn-primary" id="confirm-ok">Confirm</button>
                    </div>
                </div>
            `;

            document.body.appendChild(modal);

            // Add modal styles
            if (!document.getElementById('confirm-modal-styles')) {
                const style = document.createElement('style');
                style.id = 'confirm-modal-styles';
                style.textContent = `
                    .confirm-modal-overlay {
                        position: fixed;
                        top: 0;
                        left: 0;
                        right: 0;
                        bottom: 0;
                        background: rgba(0, 0, 0, 0.5);
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        z-index: 10000;
                    }
                    .confirm-modal {
                        background: white;
                        padding: 24px;
                        border-radius: 8px;
                        max-width: 400px;
                        width: 90%;
                        box-shadow: 0 20px 40px rgba(0,0,0,0.3);
                    }
                    .confirm-modal-title {
                        margin: 0 0 12px 0;
                        font-size: 20px;
                        font-weight: 600;
                    }
                    .confirm-modal-message {
                        margin: 0 0 20px 0;
                        color: #6b7280;
                    }
                    .confirm-modal-actions {
                        display: flex;
                        gap: 10px;
                        justify-content: flex-end;
                    }
                `;
                document.head.appendChild(style);
            }

            modal.querySelector('#confirm-ok').onclick = () => {
                modal.remove();
                resolve(true);
            };

            modal.querySelector('#confirm-cancel').onclick = () => {
                modal.remove();
                resolve(false);
            };

            modal.onclick = (e) => {
                if (e.target === modal) {
                    modal.remove();
                    resolve(false);
                }
            };
        });
    },

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
    },

    /**
     * Initialize tab navigation
     */
    initTabs() {
        document.querySelectorAll('.tab-button').forEach(button => {
            button.addEventListener('click', (e) => {
                const targetId = e.target.dataset.tab;

                // Update buttons
                document.querySelectorAll('.tab-button').forEach(b => b.classList.remove('active'));
                e.target.classList.add('active');

                // Update content
                document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
                document.getElementById(targetId)?.classList.add('active');
            });
        });
    },

    /**
     * Initialize on page load
     */
    init() {
        console.log('Payroll Global JS loaded');
        this.initTabs();
    }
};

// Auto-initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => PayrollGlobal.init());
} else {
    PayrollGlobal.init();
}

// Export for use in other scripts
window.PayrollGlobal = PayrollGlobal;
