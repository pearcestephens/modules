/**
 * Staff Performance Module - Main JavaScript
 *
 * Handles AJAX updates, real-time stats, and UI interactions
 *
 * @package CIS\Modules\StaffPerformance
 * @version 1.0.0
 */

(function() {
    'use strict';

    // Configuration
    const CONFIG = {
        refreshInterval: 60000, // 60 seconds
        apiBase: '/modules/staff-performance/api',
        animationDuration: 300
    };

    // State management
    const state = {
        lastUpdate: null,
        isRefreshing: false,
        autoRefresh: true
    };

    /**
     * Initialize the module
     */
    function init() {
        console.log('Staff Performance Module initialized');

        // Start auto-refresh if enabled
        if (state.autoRefresh) {
            startAutoRefresh();
        }

        // Bind event handlers
        bindEvents();

        // Initialize UI components
        initializeComponents();

        // Show welcome notification for first-time users
        checkFirstVisit();
    }

    /**
     * Bind UI event handlers
     */
    function bindEvents() {
        // Refresh button
        document.querySelectorAll('[data-action="refresh"]').forEach(btn => {
            btn.addEventListener('click', () => refreshStats());
        });

        // Toggle auto-refresh
        const autoRefreshToggle = document.getElementById('autoRefreshToggle');
        if (autoRefreshToggle) {
            autoRefreshToggle.addEventListener('change', (e) => {
                state.autoRefresh = e.target.checked;
                if (state.autoRefresh) {
                    startAutoRefresh();
                }
                localStorage.setItem('staffPerf_autoRefresh', state.autoRefresh);
            });
        }

        // Leaderboard filters
        document.querySelectorAll('.leaderboard-filter').forEach(filter => {
            filter.addEventListener('change', updateLeaderboard);
        });

        // Achievement cards - show details on click
        document.querySelectorAll('.achievement-card').forEach(card => {
            card.addEventListener('click', function() {
                const achievementId = this.dataset.achievementId;
                if (achievementId) {
                    showAchievementDetails(achievementId);
                }
            });
        });
    }

    /**
     * Initialize UI components (tooltips, popovers, etc.)
     */
    function initializeComponents() {
        // Initialize Bootstrap tooltips
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });

        // Initialize popovers
        const popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
        popoverTriggerList.map(function (popoverTriggerEl) {
            return new bootstrap.Popover(popoverTriggerEl);
        });

        // Animate numbers on load
        animateNumbers();
    }

    /**
     * Start auto-refresh interval
     */
    function startAutoRefresh() {
        setInterval(() => {
            if (state.autoRefresh && !state.isRefreshing) {
                refreshStats();
            }
        }, CONFIG.refreshInterval);
    }

    /**
     * Refresh personal stats via AJAX
     */
    async function refreshStats() {
        if (state.isRefreshing) return;

        state.isRefreshing = true;
        showRefreshIndicator();

        try {
            const response = await fetch(`${CONFIG.apiBase}/get-stats.php`);
            const data = await response.json();

            if (data.success) {
                updateStatsUI(data.data);
                state.lastUpdate = new Date();
                showNotification('Stats updated successfully', 'success');
            } else {
                throw new Error(data.error || 'Failed to fetch stats');
            }
        } catch (error) {
            console.error('Error refreshing stats:', error);
            showNotification('Failed to update stats', 'error');
        } finally {
            state.isRefreshing = false;
            hideRefreshIndicator();
        }
    }

    /**
     * Update stats UI with new data
     */
    function updateStatsUI(data) {
        // Update this month stats
        updateElement('[data-stat="reviews-month"]', data.this_month.reviews_this_month);
        updateElement('[data-stat="drops-month"]', data.this_month.drops_this_month);
        updateElement('[data-stat="earnings-month"]', '$' + parseFloat(data.this_month.earnings_this_month).toFixed(2));
        updateElement('[data-stat="points-month"]', data.this_month.points_this_month);

        // Update all-time stats
        updateElement('[data-stat="reviews-total"]', data.all_time.total_reviews);
        updateElement('[data-stat="drops-total"]', data.all_time.total_drops);
        updateElement('[data-stat="earnings-total"]', '$' + parseFloat(data.all_time.total_earnings).toFixed(2));

        // Update rank
        updateElement('[data-stat="rank"]', '#' + data.rank);

        // Update last update time
        updateElement('[data-stat="last-update"]', formatTime(data.updated_at));
    }

    /**
     * Update leaderboard via AJAX
     */
    async function updateLeaderboard() {
        const period = document.getElementById('leaderboard-period')?.value || 'current_month';
        const limit = document.getElementById('leaderboard-limit')?.value || 10;

        try {
            const response = await fetch(`${CONFIG.apiBase}/get-leaderboard.php?period=${period}&limit=${limit}`);
            const data = await response.json();

            if (data.success) {
                renderLeaderboard(data.data);
            }
        } catch (error) {
            console.error('Error updating leaderboard:', error);
        }
    }

    /**
     * Render leaderboard data
     */
    function renderLeaderboard(data) {
        const container = document.getElementById('leaderboard-container');
        if (!container) return;

        let html = '';
        data.forEach((row, index) => {
            const medal = ['🥇', '🥈', '🥉'][index] || `#${row.rank}`;
            html += `
                <div class="leaderboard-row ${row.is_current_user ? 'highlight' : ''}" data-rank="${row.rank}">
                    <span class="rank">${medal}</span>
                    <span class="name">${row.full_name}</span>
                    <span class="stats">
                        <span class="reviews">${row.google_reviews} reviews</span>
                        <span class="drops">${row.vape_drops} drops</span>
                    </span>
                    <span class="points">${row.points} pts</span>
                    <span class="earnings">$${parseFloat(row.earnings).toFixed(2)}</span>
                </div>
            `;
        });

        container.innerHTML = html;
        animateLeaderboard();
    }

    /**
     * Show achievement details modal
     */
    function showAchievementDetails(achievementId) {
        // TODO: Implement achievement details modal
        console.log('Show achievement details:', achievementId);
    }

    /**
     * Animate numbers with count-up effect
     */
    function animateNumbers() {
        document.querySelectorAll('[data-animate="number"]').forEach(el => {
            const target = parseInt(el.dataset.value || el.textContent.replace(/[^0-9]/g, ''));
            const duration = 1000;
            const step = Math.ceil(target / (duration / 16));
            let current = 0;

            const timer = setInterval(() => {
                current += step;
                if (current >= target) {
                    current = target;
                    clearInterval(timer);
                }
                el.textContent = current.toLocaleString();
            }, 16);
        });
    }

    /**
     * Animate leaderboard rows
     */
    function animateLeaderboard() {
        const rows = document.querySelectorAll('.leaderboard-row');
        rows.forEach((row, index) => {
            row.style.opacity = '0';
            row.style.transform = 'translateY(20px)';

            setTimeout(() => {
                row.style.transition = 'all 0.3s ease-out';
                row.style.opacity = '1';
                row.style.transform = 'translateY(0)';
            }, index * 50);
        });
    }

    /**
     * Update element content with animation
     */
    function updateElement(selector, value) {
        const elements = document.querySelectorAll(selector);
        elements.forEach(el => {
            if (el.textContent !== value) {
                el.classList.add('updating');
                setTimeout(() => {
                    el.textContent = value;
                    el.classList.remove('updating');
                    el.classList.add('updated');
                    setTimeout(() => el.classList.remove('updated'), 1000);
                }, 150);
            }
        });
    }

    /**
     * Show refresh indicator
     */
    function showRefreshIndicator() {
        document.querySelectorAll('[data-refresh-indicator]').forEach(el => {
            el.classList.add('spinning');
        });
    }

    /**
     * Hide refresh indicator
     */
    function hideRefreshIndicator() {
        document.querySelectorAll('[data-refresh-indicator]').forEach(el => {
            el.classList.remove('spinning');
        });
    }

    /**
     * Show notification toast
     */
    function showNotification(message, type = 'info') {
        const toast = document.createElement('div');
        toast.className = `toast-notification toast-${type}`;
        toast.textContent = message;
        toast.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 20px;
            background: ${type === 'success' ? '#28a745' : type === 'error' ? '#dc3545' : '#17a2b8'};
            color: white;
            border-radius: 5px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
            z-index: 9999;
            animation: slideIn 0.3s ease-out;
        `;

        document.body.appendChild(toast);

        setTimeout(() => {
            toast.style.animation = 'slideOut 0.3s ease-out';
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }

    /**
     * Format time string
     */
    function formatTime(datetime) {
        const date = new Date(datetime);
        const now = new Date();
        const diff = Math.floor((now - date) / 1000);

        if (diff < 60) return 'Just now';
        if (diff < 3600) return `${Math.floor(diff / 60)}m ago`;
        if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`;
        return date.toLocaleDateString();
    }

    /**
     * Check if first visit and show welcome
     */
    function checkFirstVisit() {
        if (!localStorage.getItem('staffPerf_visited')) {
            localStorage.setItem('staffPerf_visited', 'true');
            showNotification('Welcome to Staff Performance! Track your progress and compete with your team.', 'info');
        }
    }

    // Initialize on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // Export public API
    window.StaffPerformance = {
        refreshStats,
        updateLeaderboard,
        showNotification
    };

})();

// Add CSS animations
const style = document.createElement('style');
style.textContent = `
    @keyframes slideIn {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }

    @keyframes slideOut {
        from {
            transform: translateX(0);
            opacity: 1;
        }
        to {
            transform: translateX(100%);
            opacity: 0;
        }
    }

    .updating {
        opacity: 0.5;
        transform: scale(0.95);
        transition: all 0.15s ease-out;
    }

    .updated {
        animation: pulse 0.5s ease-out;
    }

    @keyframes pulse {
        0% { transform: scale(1); }
        50% { transform: scale(1.05); }
        100% { transform: scale(1); }
    }

    .spinning {
        animation: spin 1s linear infinite;
    }

    @keyframes spin {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }
`;
document.head.appendChild(style);
