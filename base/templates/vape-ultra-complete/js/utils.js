/**
 * Utility Functions
 *
 * Common helper functions
 */

(function() {
    'use strict';

    window.VapeUltra = window.VapeUltra || {};

    VapeUltra.Utils = {

        /**
         * Format currency
         */
        formatCurrency: function(amount, currency = 'NZD') {
            return new Intl.NumberFormat('en-NZ', {
                style: 'currency',
                currency: currency
            }).format(amount);
        },

        /**
         * Format number
         */
        formatNumber: function(number, decimals = 0) {
            return new Intl.NumberFormat('en-NZ', {
                minimumFractionDigits: decimals,
                maximumFractionDigits: decimals
            }).format(number);
        },

        /**
         * Format date
         */
        formatDate: function(date, format = 'short') {
            if (typeof date === 'string') date = new Date(date);

            const options = {
                short: { year: 'numeric', month: 'short', day: 'numeric' },
                long: { year: 'numeric', month: 'long', day: 'numeric' },
                time: { hour: '2-digit', minute: '2-digit' },
                datetime: { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' }
            };

            return new Intl.DateTimeFormat('en-NZ', options[format] || options.short).format(date);
        },

        /**
         * Relative time (e.g., "2 hours ago")
         */
        timeAgo: function(date) {
            if (typeof date === 'string') date = new Date(date);

            const seconds = Math.floor((new Date() - date) / 1000);

            const intervals = {
                year: 31536000,
                month: 2592000,
                week: 604800,
                day: 86400,
                hour: 3600,
                minute: 60,
                second: 1
            };

            for (let [unit, secondsInUnit] of Object.entries(intervals)) {
                const interval = Math.floor(seconds / secondsInUnit);
                if (interval >= 1) {
                    return interval + ' ' + unit + (interval === 1 ? '' : 's') + ' ago';
                }
            }

            return 'just now';
        },

        /**
         * Truncate text
         */
        truncate: function(text, length = 50, suffix = '...') {
            if (text.length <= length) return text;
            return text.substring(0, length).trim() + suffix;
        },

        /**
         * Escape HTML
         */
        escapeHtml: function(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        },

        /**
         * Generate random ID
         */
        randomId: function(prefix = 'id') {
            return prefix + '_' + Math.random().toString(36).substr(2, 9);
        },

        /**
         * Deep clone object
         */
        clone: function(obj) {
            return JSON.parse(JSON.stringify(obj));
        },

        /**
         * Check if object is empty
         */
        isEmpty: function(obj) {
            return Object.keys(obj).length === 0;
        },

        /**
         * Get query parameters
         */
        getQueryParams: function() {
            return Object.fromEntries(new URLSearchParams(window.location.search));
        },

        /**
         * Set query parameter
         */
        setQueryParam: function(key, value) {
            const url = new URL(window.location);
            url.searchParams.set(key, value);
            window.history.pushState({}, '', url);
        },

        /**
         * Copy to clipboard
         */
        copyToClipboard: function(text) {
            return navigator.clipboard.writeText(text)
                .then(() => {
                    VapeUltra.Notifications.success('Copied to clipboard');
                })
                .catch(() => {
                    VapeUltra.Notifications.error('Failed to copy');
                });
        },

        /**
         * Download file
         */
        downloadFile: function(filename, content, type = 'text/plain') {
            const blob = new Blob([content], { type });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = filename;
            a.click();
            URL.revokeObjectURL(url);
        }
    };

    console.log('✅ Utilities initialized');

})();
