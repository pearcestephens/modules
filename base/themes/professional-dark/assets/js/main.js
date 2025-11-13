/**
 * Professional Dark Theme - Main JavaScript
 * CIS Theme System v1.0
 */

(function() {
    'use strict';

    // Initialize theme
    document.addEventListener('DOMContentLoaded', function() {
        initTheme();
        initAnimations();
        initCharts();
        initRealTimeUpdates();
    });

    /**
     * Theme initialization
     */
    function initTheme() {
        console.log('🎨 Professional Dark Theme Loaded');

        // Add smooth scroll behavior
        document.documentElement.style.scrollBehavior = 'smooth';

        // Add active state to navigation
        const currentPath = window.location.pathname;
        document.querySelectorAll('.cis-nav-link').forEach(link => {
            if (link.getAttribute('href') === currentPath) {
                link.classList.add('active');
            }
        });
    }

    /**
     * Animate elements on scroll
     */
    function initAnimations() {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        }, {
            threshold: 0.1
        });

        // Observe cards
        document.querySelectorAll('.cis-card, .cis-product-card, .cis-store-card').forEach(el => {
            el.style.opacity = '0';
            el.style.transform = 'translateY(20px)';
            el.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
            observer.observe(el);
        });
    }

    /**
     * Initialize chart animations
     */
    function initCharts() {
        // Animate chart bars
        document.querySelectorAll('.cis-chart-bar').forEach((bar, index) => {
            const height = bar.style.height || '50%';
            bar.style.height = '0';

            setTimeout(() => {
                bar.style.transition = 'height 0.8s ease';
                bar.style.height = height;
            }, index * 100);
        });

        // Add tooltips to chart bars
        document.querySelectorAll('.cis-chart-bar').forEach(bar => {
            bar.addEventListener('mouseenter', function(e) {
                const value = this.getAttribute('data-value');
                if (value) {
                    showTooltip(e, `$${parseFloat(value).toLocaleString()}`);
                }
            });

            bar.addEventListener('mouseleave', hideTooltip);
        });
    }

    /**
     * Initialize real-time updates
     */
    function initRealTimeUpdates() {
        // Simulate real-time updates every 30 seconds
        setInterval(() => {
            updateLiveStats();
        }, 30000);

        // Add pulse animation to live indicators
        document.querySelectorAll('[data-live]').forEach(el => {
            el.style.animation = 'pulse 2s infinite';
        });
    }

    /**
     * Update live statistics
     */
    function updateLiveStats() {
        const stats = document.querySelectorAll('.cis-stat-value');

        stats.forEach(stat => {
            const currentValue = parseFloat(stat.textContent.replace(/[^0-9.-]+/g, ''));
            const change = (Math.random() * 10 - 5); // Random change ±5
            const newValue = currentValue + change;

            // Animate value change
            animateValue(stat, currentValue, newValue, 500);
        });
    }

    /**
     * Animate number value
     */
    function animateValue(element, start, end, duration) {
        const startTime = performance.now();
        const prefix = element.textContent.match(/^[^0-9]*/)[0];
        const suffix = element.textContent.match(/[^0-9]*$/)[0];

        function update(currentTime) {
            const elapsed = currentTime - startTime;
            const progress = Math.min(elapsed / duration, 1);

            const current = start + (end - start) * progress;
            element.textContent = prefix + current.toFixed(2) + suffix;

            if (progress < 1) {
                requestAnimationFrame(update);
            }
        }

        requestAnimationFrame(update);
    }

    /**
     * Show tooltip
     */
    function showTooltip(event, text) {
        let tooltip = document.getElementById('cis-tooltip');

        if (!tooltip) {
            tooltip = document.createElement('div');
            tooltip.id = 'cis-tooltip';
            tooltip.style.cssText = `
                position: fixed;
                background: #1e293b;
                color: #f1f5f9;
                padding: 8px 12px;
                border-radius: 6px;
                font-size: 13px;
                pointer-events: none;
                z-index: 9999;
                box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.3);
                transition: opacity 0.2s ease;
            `;
            document.body.appendChild(tooltip);
        }

        tooltip.textContent = text;
        tooltip.style.left = event.pageX + 10 + 'px';
        tooltip.style.top = event.pageY + 10 + 'px';
        tooltip.style.opacity = '1';
    }

    /**
     * Hide tooltip
     */
    function hideTooltip() {
        const tooltip = document.getElementById('cis-tooltip');
        if (tooltip) {
            tooltip.style.opacity = '0';
        }
    }

    /**
     * Add click handlers to feed actions
     */
    document.addEventListener('click', function(e) {
        if (e.target.closest('.cis-feed-action')) {
            const action = e.target.closest('.cis-feed-action');
            const actionType = action.textContent.trim().toLowerCase();

            // Animate the action
            action.style.transform = 'scale(0.9)';
            setTimeout(() => {
                action.style.transform = 'scale(1)';
            }, 100);

            console.log('📱 Feed action:', actionType);
        }
    });

    /**
     * Format numbers with commas
     */
    window.formatNumber = function(num) {
        return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
    };

    /**
     * Format currency
     */
    window.formatCurrency = function(amount) {
        return '$' + parseFloat(amount).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ",");
    };

    /**
     * Time ago helper
     */
    window.timeAgo = function(date) {
        const seconds = Math.floor((new Date() - new Date(date)) / 1000);

        let interval = seconds / 31536000;
        if (interval > 1) return Math.floor(interval) + ' years ago';

        interval = seconds / 2592000;
        if (interval > 1) return Math.floor(interval) + ' months ago';

        interval = seconds / 86400;
        if (interval > 1) return Math.floor(interval) + ' days ago';

        interval = seconds / 3600;
        if (interval > 1) return Math.floor(interval) + ' hours ago';

        interval = seconds / 60;
        if (interval > 1) return Math.floor(interval) + ' minutes ago';

        return 'Just now';
    };

})();

// Add CSS animation for pulse
const style = document.createElement('style');
style.textContent = `
    @keyframes pulse {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.7; }
    }
`;
document.head.appendChild(style);
