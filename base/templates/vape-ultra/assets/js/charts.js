/**
 * Chart Utilities
 *
 * Helpers for Chart.js
 */

(function() {
    'use strict';

    window.VapeUltra = window.VapeUltra || {};

    VapeUltra.Charts = {

        defaultColors: [
            '#6366f1', '#10b981', '#f59e0b', '#ef4444',
            '#3b82f6', '#8b5cf6', '#ec4899', '#14b8a6'
        ],

        /**
         * Get default chart options
         */
        getDefaultOptions: function() {
            return {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            font: {
                                family: "'Inter', sans-serif",
                                size: 12
                            },
                            padding: 12
                        }
                    }
                }
            };
        },

        /**
         * Create line chart
         */
        createLineChart: function(canvasId, data, options = {}) {
            const ctx = document.getElementById(canvasId);
            if (!ctx) return null;

            return new Chart(ctx, {
                type: 'line',
                data: data,
                options: {
                    ...this.getDefaultOptions(),
                    ...options
                }
            });
        },

        /**
         * Create bar chart
         */
        createBarChart: function(canvasId, data, options = {}) {
            const ctx = document.getElementById(canvasId);
            if (!ctx) return null;

            return new Chart(ctx, {
                type: 'bar',
                data: data,
                options: {
                    ...this.getDefaultOptions(),
                    ...options
                }
            });
        },

        /**
         * Create doughnut chart
         */
        createDoughnutChart: function(canvasId, data, options = {}) {
            const ctx = document.getElementById(canvasId);
            if (!ctx) return null;

            return new Chart(ctx, {
                type: 'doughnut',
                data: data,
                options: {
                    ...this.getDefaultOptions(),
                    ...options
                }
            });
        },

        /**
         * Update chart data
         */
        updateChart: function(chart, newData) {
            if (!chart) return;

            chart.data = newData;
            chart.update();
        },

        /**
         * Destroy chart
         */
        destroyChart: function(chart) {
            if (chart) {
                chart.destroy();
            }
        }
    };

    console.log('✅ Chart utilities initialized');

})();
