/**
 * Website Operations Module - API Client
 *
 * JavaScript wrapper for all API endpoints
 * Handles authentication, error handling, and response formatting
 *
 * @version 1.0.0
 * @author Ecigdis Development Team
 * @date 2025-11-14
 */

class WebOpsAPI {
    constructor(baseUrl = '/modules/website-operations/api/index.php') {
        this.baseUrl = baseUrl;
        this.apiKey = null; // Set via setAPIKey() if needed
    }

    /**
     * Set API key for authenticated requests
     * @param {string} key - API key
     */
    setAPIKey(key) {
        this.apiKey = key;
    }

    /**
     * Make HTTP request to API
     * @param {string} endpoint - API endpoint
     * @param {string} method - HTTP method (GET, POST, PUT, DELETE)
     * @param {object} data - Request payload
     * @returns {Promise} - API response
     */
    async request(endpoint, method = 'GET', data = null) {
        const url = `${this.baseUrl}?endpoint=${encodeURIComponent(endpoint)}`;

        const options = {
            method: method,
            headers: {
                'Content-Type': 'application/json',
            }
        };

        // Add API key if set
        if (this.apiKey) {
            options.headers['X-API-Key'] = this.apiKey;
        }

        // Add body for POST/PUT requests
        if (data && (method === 'POST' || method === 'PUT')) {
            options.body = JSON.stringify(data);
        }

        try {
            const response = await fetch(url, options);
            const result = await response.json();

            if (!response.ok) {
                throw new Error(result.message || `HTTP ${response.status}`);
            }

            return result;
        } catch (error) {
            console.error(`API Error [${method} ${endpoint}]:`, error);
            throw error;
        }
    }

    // =========================================================================
    // ORDER ENDPOINTS
    // =========================================================================

    /**
     * Get all orders with optional filters
     * @param {object} params - Query parameters
     * @returns {Promise<Array>} - List of orders
     */
    async getOrders(params = {}) {
        const queryString = new URLSearchParams(params).toString();
        const endpoint = `orders${queryString ? '?' + queryString : ''}`;
        return this.request(endpoint, 'GET');
    }

    /**
     * Get single order by ID
     * @param {number} orderId - Order ID
     * @returns {Promise<object>} - Order details
     */
    async getOrder(orderId) {
        return this.request(`orders/${orderId}`, 'GET');
    }

    /**
     * Create new order
     * @param {object} orderData - Order data
     * @returns {Promise<object>} - Created order
     */
    async createOrder(orderData) {
        return this.request('orders', 'POST', orderData);
    }

    /**
     * Update existing order
     * @param {number} orderId - Order ID
     * @param {object} orderData - Updated order data
     * @returns {Promise<object>} - Updated order
     */
    async updateOrder(orderId, orderData) {
        return this.request(`orders/${orderId}`, 'PUT', orderData);
    }

    /**
     * Delete order
     * @param {number} orderId - Order ID
     * @returns {Promise<object>} - Deletion confirmation
     */
    async deleteOrder(orderId) {
        return this.request(`orders/${orderId}`, 'DELETE');
    }

    /**
     * Get order statistics
     * @returns {Promise<object>} - Order statistics
     */
    async getOrderStats() {
        return this.request('orders/stats', 'GET');
    }

    // =========================================================================
    // PRODUCT ENDPOINTS
    // =========================================================================

    /**
     * Get all products with optional filters
     * @param {object} params - Query parameters
     * @returns {Promise<Array>} - List of products
     */
    async getProducts(params = {}) {
        const queryString = new URLSearchParams(params).toString();
        const endpoint = `products${queryString ? '?' + queryString : ''}`;
        return this.request(endpoint, 'GET');
    }

    /**
     * Get single product by ID
     * @param {number} productId - Product ID
     * @returns {Promise<object>} - Product details
     */
    async getProduct(productId) {
        return this.request(`products/${productId}`, 'GET');
    }

    /**
     * Create new product
     * @param {object} productData - Product data
     * @returns {Promise<object>} - Created product
     */
    async createProduct(productData) {
        return this.request('products', 'POST', productData);
    }

    /**
     * Update existing product
     * @param {number} productId - Product ID
     * @param {object} productData - Updated product data
     * @returns {Promise<object>} - Updated product
     */
    async updateProduct(productId, productData) {
        return this.request(`products/${productId}`, 'PUT', productData);
    }

    /**
     * Delete product
     * @param {number} productId - Product ID
     * @returns {Promise<object>} - Deletion confirmation
     */
    async deleteProduct(productId) {
        return this.request(`products/${productId}`, 'DELETE');
    }

    /**
     * Get product statistics
     * @returns {Promise<object>} - Product statistics
     */
    async getProductStats() {
        return this.request('products/stats', 'GET');
    }

    /**
     * Bulk update products
     * @param {Array} products - Array of product IDs and data
     * @returns {Promise<object>} - Bulk update results
     */
    async bulkUpdateProducts(products) {
        return this.request('products/bulk', 'PUT', { products });
    }

    // =========================================================================
    // CUSTOMER ENDPOINTS
    // =========================================================================

    /**
     * Get all customers with optional filters
     * @param {object} params - Query parameters
     * @returns {Promise<Array>} - List of customers
     */
    async getCustomers(params = {}) {
        const queryString = new URLSearchParams(params).toString();
        const endpoint = `customers${queryString ? '?' + queryString : ''}`;
        return this.request(endpoint, 'GET');
    }

    /**
     * Get single customer by ID
     * @param {number} customerId - Customer ID
     * @returns {Promise<object>} - Customer details
     */
    async getCustomer(customerId) {
        return this.request(`customers/${customerId}`, 'GET');
    }

    /**
     * Update customer
     * @param {number} customerId - Customer ID
     * @param {object} customerData - Updated customer data
     * @returns {Promise<object>} - Updated customer
     */
    async updateCustomer(customerId, customerData) {
        return this.request(`customers/${customerId}`, 'PUT', customerData);
    }

    /**
     * Get customer statistics
     * @returns {Promise<object>} - Customer statistics
     */
    async getCustomerStats() {
        return this.request('customers/stats', 'GET');
    }

    /**
     * Get customer order history
     * @param {number} customerId - Customer ID
     * @returns {Promise<Array>} - Customer orders
     */
    async getCustomerOrders(customerId) {
        return this.request(`customers/${customerId}/orders`, 'GET');
    }

    // =========================================================================
    // SHIPPING ENDPOINTS
    // =========================================================================

    /**
     * Optimize shipping for order
     * @param {object} shippingData - Order and destination data
     * @returns {Promise<object>} - Optimized shipping options
     */
    async optimizeShipping(shippingData) {
        return this.request('shipping/optimize', 'POST', shippingData);
    }

    /**
     * Get shipping rates
     * @param {object} rateParams - Rate calculation parameters
     * @returns {Promise<Array>} - Available shipping rates
     */
    async getShippingRates(rateParams) {
        return this.request('shipping/rates', 'POST', rateParams);
    }

    /**
     * Get shipping zones
     * @returns {Promise<Array>} - Shipping zones
     */
    async getShippingZones() {
        return this.request('shipping/zones', 'GET');
    }

    /**
     * Get shipping savings report
     * @param {object} params - Report parameters
     * @returns {Promise<object>} - Savings statistics
     */
    async getShippingSavings(params = {}) {
        const queryString = new URLSearchParams(params).toString();
        const endpoint = `shipping/savings${queryString ? '?' + queryString : ''}`;
        return this.request(endpoint, 'GET');
    }

    // =========================================================================
    // WHOLESALE ENDPOINTS
    // =========================================================================

    /**
     * Get wholesale accounts
     * @param {object} params - Query parameters
     * @returns {Promise<Array>} - Wholesale accounts
     */
    async getWholesaleAccounts(params = {}) {
        const queryString = new URLSearchParams(params).toString();
        const endpoint = `wholesale/accounts${queryString ? '?' + queryString : ''}`;
        return this.request(endpoint, 'GET');
    }

    /**
     * Get wholesale account details
     * @param {number} accountId - Account ID
     * @returns {Promise<object>} - Account details
     */
    async getWholesaleAccount(accountId) {
        return this.request(`wholesale/accounts/${accountId}`, 'GET');
    }

    /**
     * Update wholesale account
     * @param {number} accountId - Account ID
     * @param {object} accountData - Updated account data
     * @returns {Promise<object>} - Updated account
     */
    async updateWholesaleAccount(accountId, accountData) {
        return this.request(`wholesale/accounts/${accountId}`, 'PUT', accountData);
    }

    /**
     * Get wholesale orders
     * @param {object} params - Query parameters
     * @returns {Promise<Array>} - Wholesale orders
     */
    async getWholesaleOrders(params = {}) {
        const queryString = new URLSearchParams(params).toString();
        const endpoint = `wholesale/orders${queryString ? '?' + queryString : ''}`;
        return this.request(endpoint, 'GET');
    }

    // =========================================================================
    // ANALYTICS ENDPOINTS
    // =========================================================================

    /**
     * Get performance metrics
     * @param {object} params - Time range and filters
     * @returns {Promise<object>} - Performance data
     */
    async getPerformanceMetrics(params = {}) {
        const queryString = new URLSearchParams(params).toString();
        const endpoint = `analytics/performance${queryString ? '?' + queryString : ''}`;
        return this.request(endpoint, 'GET');
    }

    /**
     * Get sales trends
     * @param {object} params - Time range and grouping
     * @returns {Promise<Array>} - Sales data
     */
    async getSalesTrends(params = {}) {
        const queryString = new URLSearchParams(params).toString();
        const endpoint = `analytics/sales${queryString ? '?' + queryString : ''}`;
        return this.request(endpoint, 'GET');
    }

    /**
     * Get top products
     * @param {number} limit - Number of products
     * @returns {Promise<Array>} - Top products
     */
    async getTopProducts(limit = 10) {
        return this.request(`analytics/top-products?limit=${limit}`, 'GET');
    }

    /**
     * Get top customers
     * @param {number} limit - Number of customers
     * @returns {Promise<Array>} - Top customers
     */
    async getTopCustomers(limit = 10) {
        return this.request(`analytics/top-customers?limit=${limit}`, 'GET');
    }

    // =========================================================================
    // DASHBOARD ENDPOINTS
    // =========================================================================

    /**
     * Get dashboard overview
     * @returns {Promise<object>} - Dashboard data
     */
    async getDashboard() {
        return this.request('dashboard', 'GET');
    }

    /**
     * Get recent activity
     * @param {number} limit - Number of items
     * @returns {Promise<Array>} - Recent activity
     */
    async getRecentActivity(limit = 20) {
        return this.request(`dashboard/activity?limit=${limit}`, 'GET');
    }

    // =========================================================================
    // UTILITY METHODS
    // =========================================================================

    /**
     * Health check
     * @returns {Promise<object>} - API health status
     */
    async health() {
        return this.request('health', 'GET');
    }

    /**
     * Format currency
     * @param {number} amount - Amount to format
     * @param {string} currency - Currency code
     * @returns {string} - Formatted currency
     */
    formatCurrency(amount, currency = 'NZD') {
        return new Intl.NumberFormat('en-NZ', {
            style: 'currency',
            currency: currency
        }).format(amount);
    }

    /**
     * Format date
     * @param {string|Date} date - Date to format
     * @param {string} format - Format type (short, long, relative)
     * @returns {string} - Formatted date
     */
    formatDate(date, format = 'short') {
        const d = new Date(date);

        switch(format) {
            case 'short':
                return d.toLocaleDateString('en-NZ');
            case 'long':
                return d.toLocaleDateString('en-NZ', {
                    weekday: 'long',
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric'
                });
            case 'relative':
                return this.getRelativeTime(d);
            default:
                return d.toISOString();
        }
    }

    /**
     * Get relative time string
     * @param {Date} date - Date to compare
     * @returns {string} - Relative time (e.g., "2 hours ago")
     */
    getRelativeTime(date) {
        const now = new Date();
        const diff = now - date;
        const seconds = Math.floor(diff / 1000);
        const minutes = Math.floor(seconds / 60);
        const hours = Math.floor(minutes / 60);
        const days = Math.floor(hours / 24);

        if (seconds < 60) return 'just now';
        if (minutes < 60) return `${minutes} minute${minutes > 1 ? 's' : ''} ago`;
        if (hours < 24) return `${hours} hour${hours > 1 ? 's' : ''} ago`;
        if (days < 30) return `${days} day${days > 1 ? 's' : ''} ago`;

        return date.toLocaleDateString('en-NZ');
    }

    /**
     * Show toast notification
     * @param {string} message - Notification message
     * @param {string} type - Type (success, error, warning, info)
     */
    showToast(message, type = 'info') {
        // Create toast element
        const toast = document.createElement('div');
        toast.className = `webops-alert webops-alert-${type}`;
        toast.style.position = 'fixed';
        toast.style.top = '20px';
        toast.style.right = '20px';
        toast.style.zIndex = '9999';
        toast.style.minWidth = '300px';
        toast.style.animation = 'slideInRight 0.3s ease-out';
        toast.textContent = message;

        document.body.appendChild(toast);

        // Auto-remove after 5 seconds
        setTimeout(() => {
            toast.style.animation = 'slideOutRight 0.3s ease-out';
            setTimeout(() => toast.remove(), 300);
        }, 5000);
    }
}

// Create global instance
window.webOpsAPI = new WebOpsAPI();

// Add animation keyframes
if (!document.getElementById('webops-toast-animations')) {
    const style = document.createElement('style');
    style.id = 'webops-toast-animations';
    style.textContent = `
        @keyframes slideInRight {
            from {
                transform: translateX(400px);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        @keyframes slideOutRight {
            from {
                transform: translateX(0);
                opacity: 1;
            }
            to {
                transform: translateX(400px);
                opacity: 0;
            }
        }
    `;
    document.head.appendChild(style);
}

// Export for module systems
if (typeof module !== 'undefined' && module.exports) {
    module.exports = WebOpsAPI;
}
