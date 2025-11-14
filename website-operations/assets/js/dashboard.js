/**
 * Website Operations Module - Dashboard Logic
 *
 * Handles dashboard interactions, real-time updates, and data visualization
 *
 * @version 1.0.0
 * @author Ecigdis Development Team
 * @date 2025-11-14
 */

// Dashboard Controller
class WebOpsDashboard {
    constructor() {
        this.api = window.webOpsAPI;
        this.refreshInterval = null;
        this.autoRefreshEnabled = true;
        this.refreshRate = 30000; // 30 seconds
        this.init();
    }

    /**
     * Initialize dashboard
     */
    async init() {
        console.log('🚀 Website Operations Dashboard initializing...');

        // Load initial data
        await this.loadDashboardData();

        // Set up auto-refresh
        this.setupAutoRefresh();

        // Set up event listeners
        this.setupEventListeners();

        console.log('✅ Dashboard ready!');
    }

    /**
     * Load all dashboard data
     */
    async loadDashboardData() {
        try {
            this.showLoading();

            // Load main dashboard data
            const data = await this.api.getDashboard();

            // Update UI elements
            this.updateStats(data.stats);
            this.updateRecentOrders(data.recent_orders);
            this.updateRecentActivity(data.recent_activity);

            this.hideLoading();
            this.updateLastRefresh();

        } catch (error) {
            console.error('Dashboard load error:', error);
            this.api.showToast('Failed to load dashboard data', 'danger');
            this.hideLoading();
        }
    }

    /**
     * Update statistics widgets
     * @param {object} stats - Statistics data
     */
    updateStats(stats) {
        if (!stats) return;

        // Update each stat widget
        Object.keys(stats).forEach(key => {
            const element = document.querySelector(`[data-stat="${key}"]`);
            if (element) {
                this.updateStatWidget(element, stats[key]);
            }
        });
    }

    /**
     * Update individual stat widget
     * @param {HTMLElement} element - Stat widget element
     * @param {object} data - Stat data
     */
    updateStatWidget(element, data) {
        const valueEl = element.querySelector('.webops-stat-value');
        const changeEl = element.querySelector('.webops-stat-change');

        if (valueEl) {
            // Animate value change
            this.animateValue(valueEl, data.value);
        }

        if (changeEl && data.change !== undefined) {
            const isPositive = data.change >= 0;
            changeEl.className = `webops-stat-change ${isPositive ? 'positive' : 'negative'}`;
            changeEl.textContent = `${isPositive ? '+' : ''}${data.change}%`;
        }
    }

    /**
     * Animate number value
     * @param {HTMLElement} element - Element to animate
     * @param {number} targetValue - Target value
     */
    animateValue(element, targetValue) {
        const currentValue = parseFloat(element.textContent.replace(/[^0-9.-]/g, '')) || 0;
        const duration = 1000; // 1 second
        const steps = 30;
        const increment = (targetValue - currentValue) / steps;
        let current = currentValue;
        let step = 0;

        const timer = setInterval(() => {
            step++;
            current += increment;

            // Format based on data type
            if (element.dataset.format === 'currency') {
                element.textContent = this.api.formatCurrency(current);
            } else if (element.dataset.format === 'number') {
                element.textContent = Math.round(current).toLocaleString();
            } else {
                element.textContent = Math.round(current);
            }

            if (step >= steps) {
                clearInterval(timer);
                // Set final value
                if (element.dataset.format === 'currency') {
                    element.textContent = this.api.formatCurrency(targetValue);
                } else if (element.dataset.format === 'number') {
                    element.textContent = Math.round(targetValue).toLocaleString();
                } else {
                    element.textContent = Math.round(targetValue);
                }
            }
        }, duration / steps);
    }

    /**
     * Update recent orders list
     * @param {Array} orders - Recent orders
     */
    updateRecentOrders(orders) {
        const container = document.getElementById('recent-orders-list');
        if (!container || !orders) return;

        container.innerHTML = orders.map(order => this.renderOrderCard(order)).join('');
    }

    /**
     * Render order card HTML
     * @param {object} order - Order data
     * @returns {string} - HTML string
     */
    renderOrderCard(order) {
        const statusClass = this.getStatusClass(order.status);
        return `
            <div class="webops-card" data-order-id="${order.id}">
                <div class="webops-card-header">
                    <div>
                        <strong>Order #${order.order_number}</strong>
                        <span class="webops-badge webops-badge-${statusClass}">${order.status}</span>
                    </div>
                    <div class="webops-stat-value" data-format="currency">${this.api.formatCurrency(order.total)}</div>
                </div>
                <div class="webops-card-body">
                    <p><strong>Customer:</strong> ${order.customer_name}</p>
                    <p><strong>Items:</strong> ${order.item_count}</p>
                    <p><strong>Date:</strong> ${this.api.formatDate(order.created_at, 'relative')}</p>
                </div>
                <div class="webops-card-footer">
                    <button class="webops-btn webops-btn-sm webops-btn-primary" onclick="dashboard.viewOrder(${order.id})">
                        View Details
                    </button>
                </div>
            </div>
        `;
    }

    /**
     * Update recent activity feed
     * @param {Array} activities - Recent activities
     */
    updateRecentActivity(activities) {
        const container = document.getElementById('recent-activity-list');
        if (!container || !activities) return;

        container.innerHTML = activities.map(activity => this.renderActivity(activity)).join('');
    }

    /**
     * Render activity item
     * @param {object} activity - Activity data
     * @returns {string} - HTML string
     */
    renderActivity(activity) {
        const icon = this.getActivityIcon(activity.type);
        return `
            <div class="webops-activity-item">
                <div class="webops-activity-icon ${activity.type}">
                    ${icon}
                </div>
                <div class="webops-activity-content">
                    <div class="webops-activity-title">${activity.title}</div>
                    <div class="webops-activity-time">${this.api.formatDate(activity.created_at, 'relative')}</div>
                </div>
            </div>
        `;
    }

    /**
     * Get status badge class
     * @param {string} status - Order status
     * @returns {string} - CSS class
     */
    getStatusClass(status) {
        const statusMap = {
            'pending': 'warning',
            'processing': 'info',
            'completed': 'success',
            'cancelled': 'danger',
            'shipped': 'info',
            'delivered': 'success'
        };
        return statusMap[status.toLowerCase()] || 'gray';
    }

    /**
     * Get activity icon
     * @param {string} type - Activity type
     * @returns {string} - Icon HTML
     */
    getActivityIcon(type) {
        const icons = {
            'order': '📦',
            'product': '🏷️',
            'customer': '👤',
            'shipping': '🚚',
            'payment': '💳',
            'system': '⚙️'
        };
        return icons[type] || '•';
    }

    /**
     * Set up auto-refresh
     */
    setupAutoRefresh() {
        if (this.autoRefreshEnabled) {
            this.refreshInterval = setInterval(() => {
                this.loadDashboardData();
            }, this.refreshRate);
        }
    }

    /**
     * Toggle auto-refresh
     */
    toggleAutoRefresh() {
        this.autoRefreshEnabled = !this.autoRefreshEnabled;

        if (this.autoRefreshEnabled) {
            this.setupAutoRefresh();
            this.api.showToast('Auto-refresh enabled', 'success');
        } else {
            if (this.refreshInterval) {
                clearInterval(this.refreshInterval);
                this.refreshInterval = null;
            }
            this.api.showToast('Auto-refresh disabled', 'info');
        }

        // Update button state
        const btn = document.getElementById('toggle-auto-refresh');
        if (btn) {
            btn.textContent = this.autoRefreshEnabled ? '⏸ Pause' : '▶ Resume';
        }
    }

    /**
     * Manual refresh
     */
    async refresh() {
        await this.loadDashboardData();
        this.api.showToast('Dashboard refreshed', 'success');
    }

    /**
     * Set up event listeners
     */
    setupEventListeners() {
        // Refresh button
        const refreshBtn = document.getElementById('refresh-dashboard');
        if (refreshBtn) {
            refreshBtn.addEventListener('click', () => this.refresh());
        }

        // Auto-refresh toggle
        const autoRefreshBtn = document.getElementById('toggle-auto-refresh');
        if (autoRefreshBtn) {
            autoRefreshBtn.addEventListener('click', () => this.toggleAutoRefresh());
        }

        // Search functionality
        const searchInput = document.getElementById('dashboard-search');
        if (searchInput) {
            searchInput.addEventListener('input', (e) => this.handleSearch(e.target.value));
        }

        // Filter dropdowns
        document.querySelectorAll('[data-filter]').forEach(filter => {
            filter.addEventListener('change', (e) => this.handleFilter(e.target.dataset.filter, e.target.value));
        });
    }

    /**
     * Handle search
     * @param {string} query - Search query
     */
    handleSearch(query) {
        // Debounce search
        clearTimeout(this.searchTimeout);
        this.searchTimeout = setTimeout(async () => {
            if (query.length >= 3) {
                await this.performSearch(query);
            }
        }, 300);
    }

    /**
     * Perform search
     * @param {string} query - Search query
     */
    async performSearch(query) {
        try {
            const results = await this.api.request(`search?q=${encodeURIComponent(query)}`, 'GET');
            this.displaySearchResults(results);
        } catch (error) {
            console.error('Search error:', error);
        }
    }

    /**
     * Display search results
     * @param {object} results - Search results
     */
    displaySearchResults(results) {
        // Implement search results display
        console.log('Search results:', results);
    }

    /**
     * Handle filter change
     * @param {string} filterType - Filter type
     * @param {string} filterValue - Filter value
     */
    handleFilter(filterType, filterValue) {
        console.log(`Filter: ${filterType} = ${filterValue}`);
        this.loadDashboardData();
    }

    /**
     * View order details
     * @param {number} orderId - Order ID
     */
    async viewOrder(orderId) {
        try {
            const order = await this.api.getOrder(orderId);
            this.showOrderModal(order);
        } catch (error) {
            this.api.showToast('Failed to load order', 'danger');
        }
    }

    /**
     * Show order modal
     * @param {object} order - Order data
     */
    showOrderModal(order) {
        // Create and show modal with order details
        console.log('Show order:', order);
        // Implementation would go here
    }

    /**
     * Show loading state
     */
    showLoading() {
        const loader = document.getElementById('dashboard-loader');
        if (loader) {
            loader.classList.remove('webops-hidden');
        }
    }

    /**
     * Hide loading state
     */
    hideLoading() {
        const loader = document.getElementById('dashboard-loader');
        if (loader) {
            loader.classList.add('webops-hidden');
        }
    }

    /**
     * Update last refresh timestamp
     */
    updateLastRefresh() {
        const element = document.getElementById('last-refresh');
        if (element) {
            element.textContent = `Last updated: ${new Date().toLocaleTimeString('en-NZ')}`;
        }
    }

    /**
     * Export data
     * @param {string} format - Export format (csv, json, excel)
     */
    async exportData(format = 'csv') {
        try {
            this.api.showToast('Preparing export...', 'info');

            // Get data to export
            const data = await this.api.getDashboard();

            // Format and download
            switch(format) {
                case 'csv':
                    this.downloadCSV(data);
                    break;
                case 'json':
                    this.downloadJSON(data);
                    break;
                default:
                    throw new Error('Unsupported format');
            }

            this.api.showToast('Export complete', 'success');
        } catch (error) {
            this.api.showToast('Export failed', 'danger');
        }
    }

    /**
     * Download data as CSV
     * @param {object} data - Data to export
     */
    downloadCSV(data) {
        // Simplified CSV export
        const csv = JSON.stringify(data);
        this.downloadFile(csv, 'dashboard-export.csv', 'text/csv');
    }

    /**
     * Download data as JSON
     * @param {object} data - Data to export
     */
    downloadJSON(data) {
        const json = JSON.stringify(data, null, 2);
        this.downloadFile(json, 'dashboard-export.json', 'application/json');
    }

    /**
     * Download file
     * @param {string} content - File content
     * @param {string} filename - Filename
     * @param {string} mimeType - MIME type
     */
    downloadFile(content, filename, mimeType) {
        const blob = new Blob([content], { type: mimeType });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = filename;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(url);
    }
}

// Initialize dashboard when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        window.dashboard = new WebOpsDashboard();
    });
} else {
    window.dashboard = new WebOpsDashboard();
}
