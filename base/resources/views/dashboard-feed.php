<?php
/**
 * ============================================================================
 * CIS Dashboard Feed Frontend
 * ============================================================================
 *
 * Purpose:
 *   Main dashboard feed view with real-time AJAX auto-refresh, lazy loading,
 *   and gamification elements. Provides an engaging activity stream for
 *   CIS staff portal users.
 *
 * Features:
 *   - Auto-refresh every 30 seconds
 *   - Manual refresh button
 *   - Lazy loading for images
 *   - Engagement metrics display
 *   - Responsive design (mobile-first)
 *   - Accessibility (WCAG 2.1 AA)
 *   - Performance optimized
 *
 * ============================================================================
 */

require_once __DIR__ . '/../bootstrap.php';

// Require authentication
requireAuth();

// Get current user & outlet info
$userId = $_SESSION['user_id'];
$outletId = $_SESSION['outlet_id'] ?? null;

// Page metadata
$pageTitle = 'Activity Feed';
$pageIcon = 'bi-feed';
?>

<?php render('layout/header', ['title' => $pageTitle, 'icon' => $pageIcon]); ?>

<div class="container-fluid py-4">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col">
            <h1 class="h3 mb-0">
                <i class="bi <?php echo $pageIcon; ?>"></i>
                <?php echo $pageTitle; ?>
            </h1>
            <p class="text-muted small mt-1">Real-time activity stream for your store and company</p>
        </div>
        <div class="col-auto">
            <!-- Refresh Controls -->
            <div class="btn-group" role="group" aria-label="Feed controls">
                <button type="button"
                        id="refreshFeedBtn"
                        class="btn btn-outline-primary"
                        title="Refresh feed immediately"
                        aria-label="Refresh feed">
                    <i class="bi bi-arrow-clockwise"></i>
                    <span class="d-none d-sm-inline ms-2">Refresh</span>
                </button>

                <button type="button"
                        id="toggleAutoRefreshBtn"
                        class="btn btn-outline-secondary active"
                        title="Toggle auto-refresh (every 30 seconds)"
                        aria-label="Toggle auto-refresh"
                        data-auto-refresh="true">
                    <i class="bi bi-clock"></i>
                    <span class="d-none d-sm-inline ms-2">Auto</span>
                </button>

                <div class="btn-group" role="group">
                    <input type="checkbox"
                           id="externalNewsToggle"
                           class="btn-check"
                           checked>
                    <label class="btn btn-outline-info" for="externalNewsToggle" title="Toggle external news">
                        <i class="bi bi-globe"></i>
                        <span class="d-none d-sm-inline ms-2">News</span>
                    </label>
                </div>
            </div>
        </div>
    </div>

    <!-- Status Bar -->
    <div class="row mb-3">
        <div class="col">
            <div class="alert alert-info alert-dismissible fade show d-flex align-items-center" role="alert" id="statusAlert" style="display: none;">
                <i class="bi bi-info-circle me-2"></i>
                <div id="statusMessage">Loading feed...</div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        </div>
    </div>

    <!-- Filters & Stats Row -->
    <div class="row mb-3">
        <div class="col-md-8">
            <div class="input-group">
                <input type="text"
                       id="feedSearch"
                       class="form-control"
                       placeholder="Search activities..."
                       aria-label="Search activities">
                <button class="btn btn-outline-secondary"
                        type="button"
                        id="filterBtn"
                        title="Advanced filters">
                    <i class="bi bi-funnel"></i>
                </button>
            </div>
        </div>
        <div class="col-md-4 text-end">
            <small class="text-muted">
                Last refreshed: <span id="lastRefreshed">just now</span>
                <span class="spinner-border spinner-border-sm ms-2 d-none" id="refreshSpinner" role="status" aria-label="Refreshing...">
                    <span class="visually-hidden">Refreshing...</span>
                </span>
            </small>
        </div>
    </div>

    <!-- Main Feed Container -->
    <div class="row">
        <div class="col-lg-8">
            <!-- Activity Feed -->
            <div id="feedContainer" class="feed-container">
                <!-- Loading skeleton -->
                <div class="placeholder-glow">
                    <?php for ($i = 0; $i < 3; $i++): ?>
                        <div class="card mb-3">
                            <div class="card-body">
                                <div class="placeholder col-6"></div>
                                <div class="placeholder col-8 mt-2"></div>
                                <div class="placeholder col-12 mt-3"></div>
                            </div>
                        </div>
                    <?php endfor; ?>
                </div>
            </div>

            <!-- Load More Button -->
            <div class="text-center mb-4" id="loadMoreContainer" style="display: none;">
                <button type="button"
                        id="loadMoreBtn"
                        class="btn btn-outline-secondary"
                        aria-label="Load more activities">
                    <i class="bi bi-arrow-down-circle"></i>
                    Load More
                </button>
            </div>

            <!-- No Activities Message -->
            <div class="alert alert-info text-center d-none" id="emptyState" role="status">
                <i class="bi bi-inbox" style="font-size: 2rem;"></i>
                <p class="mt-2">No activities to display. Check back later!</p>
            </div>
        </div>

        <!-- Sidebar: Engagement Stats & Trending -->
        <div class="col-lg-4">
            <!-- Engagement Summary Card -->
            <div class="card mb-3">
                <div class="card-header bg-light">
                    <h6 class="card-title mb-0">
                        <i class="bi bi-fire text-danger"></i>
                        Engagement Today
                    </h6>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col">
                            <div class="h3 mb-0" id="totalEngagementCount">-</div>
                            <small class="text-muted">Total Engagement</small>
                        </div>
                        <div class="col">
                            <div class="h3 mb-0" id="hotActivitiesCount">-</div>
                            <small class="text-muted">Hot Activities</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Trending Activities -->
            <div class="card mb-3">
                <div class="card-header bg-light">
                    <h6 class="card-title mb-0">
                        <i class="bi bi-graph-up text-success"></i>
                        Trending
                    </h6>
                </div>
                <div class="list-group list-group-flush" id="trendingList">
                    <div class="list-group-item text-muted text-center py-4">
                        <small>Loading...</small>
                    </div>
                </div>
            </div>

            <!-- Performance Card -->
            <div class="card">
                <div class="card-header bg-light">
                    <h6 class="card-title mb-0">
                        <i class="bi bi-speedometer2"></i>
                        Performance
                    </h6>
                </div>
                <div class="card-body small">
                    <div class="mb-2">
                        <div class="d-flex justify-content-between mb-1">
                            <span>API Response</span>
                            <span id="apiResponseTime">-</span>
                        </div>
                        <div class="progress" style="height: 4px;">
                            <div id="responseTimeBar" class="progress-bar bg-success" role="progressbar" style="width: 0%"></div>
                        </div>
                    </div>
                    <div>
                        <div class="d-flex justify-content-between">
                            <span>Server Cache</span>
                            <span id="cacheStatus">-</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Scripts -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const FeedManager = {
        // Configuration
        config: {
            apiEndpoint: '/modules/base/api/feed_refresh.php',
            autoRefreshInterval: 30000, // 30 seconds
            pageSize: 20,
            debounceDelay: 300
        },

        // State
        state: {
            currentOffset: 0,
            isLoading: false,
            isAutoRefreshing: true,
            autoRefreshTimer: null,
            lastRefreshTime: Date.now()
        },

        // DOM Elements
        elements: {
            feedContainer: document.getElementById('feedContainer'),
            refreshBtn: document.getElementById('refreshFeedBtn'),
            autoRefreshBtn: document.getElementById('toggleAutoRefreshBtn'),
            externalNewsToggle: document.getElementById('externalNewsToggle'),
            searchInput: document.getElementById('feedSearch'),
            lastRefreshedSpan: document.getElementById('lastRefreshed'),
            refreshSpinner: document.getElementById('refreshSpinner'),
            loadMoreBtn: document.getElementById('loadMoreBtn'),
            loadMoreContainer: document.getElementById('loadMoreContainer'),
            emptyState: document.getElementById('emptyState'),
            statusAlert: document.getElementById('statusAlert'),
            statusMessage: document.getElementById('statusMessage')
        },

        /**
         * Initialize the feed manager
         */
        init() {
            this.attachEventListeners();
            this.startAutoRefresh();
            this.loadFeed();
        },

        /**
         * Attach event listeners to UI controls
         */
        attachEventListeners() {
            this.elements.refreshBtn?.addEventListener('click', () => this.refreshFeed());
            this.elements.autoRefreshBtn?.addEventListener('click', () => this.toggleAutoRefresh());
            this.elements.externalNewsToggle?.addEventListener('change', () => this.refreshFeed());
            this.elements.loadMoreBtn?.addEventListener('click', () => this.loadMore());

            // Search with debounce
            let searchTimeout;
            this.elements.searchInput?.addEventListener('input', (e) => {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => this.filterFeed(e.target.value), this.config.debounceDelay);
            });
        },

        /**
         * Fetch feed data from API
         */
        async loadFeed(offset = 0, append = false) {
            if (this.state.isLoading) return;

            this.state.isLoading = true;
            this.elements.refreshSpinner?.classList.remove('d-none');

            try {
                const params = new URLSearchParams({
                    limit: this.config.pageSize,
                    offset: offset,
                    include_external: this.elements.externalNewsToggle?.checked ? 1 : 0,
                    format: 'html'
                });

                const startTime = Date.now();
                const response = await fetch(`${this.config.apiEndpoint}?${params}`);
                const duration = Date.now() - startTime;

                if (!response.ok) {
                    throw new Error(`API Error: ${response.status}`);
                }

                const data = await response.json();

                if (data.ok) {
                    if (append) {
                        this.elements.feedContainer.innerHTML += data.html;
                        this.state.currentOffset += this.config.pageSize;
                    } else {
                        this.elements.feedContainer.innerHTML = data.html;
                        this.state.currentOffset = 0;
                    }

                    // Update UI
                    this.updateLastRefreshed();
                    this.updatePerformanceMetrics(duration, data.cached);
                    this.attachActivityListeners();

                    // Show/hide load more button
                    if (data.has_more) {
                        this.elements.loadMoreContainer?.style.display = 'block';
                    } else {
                        this.elements.loadMoreContainer?.style.display = 'none';
                    }

                    this.showStatus('Feed updated successfully', 'success');
                } else {
                    throw new Error(data.error || 'Unknown error');
                }
            } catch (error) {
                console.error('Feed load error:', error);
                this.showStatus(`Error loading feed: ${error.message}`, 'danger');
            } finally {
                this.state.isLoading = false;
                this.elements.refreshSpinner?.classList.add('d-none');
            }
        },

        /**
         * Refresh the feed (clear and reload)
         */
        async refreshFeed() {
            this.state.currentOffset = 0;
            await this.loadFeed();
        },

        /**
         * Load more activities (pagination)
         */
        async loadMore() {
            await this.loadFeed(this.state.currentOffset + this.config.pageSize, true);
        },

        /**
         * Toggle auto-refresh
         */
        toggleAutoRefresh() {
            this.state.isAutoRefreshing = !this.state.isAutoRefreshing;
            this.elements.autoRefreshBtn?.classList.toggle('active', this.state.isAutoRefreshing);

            if (this.state.isAutoRefreshing) {
                this.startAutoRefresh();
            } else {
                this.stopAutoRefresh();
            }
        },

        /**
         * Start auto-refresh timer
         */
        startAutoRefresh() {
            if (this.state.autoRefreshTimer) clearInterval(this.state.autoRefreshTimer);

            this.state.autoRefreshTimer = setInterval(() => {
                if (this.state.isAutoRefreshing && !this.state.isLoading) {
                    this.refreshFeed();
                }
            }, this.config.autoRefreshInterval);
        },

        /**
         * Stop auto-refresh timer
         */
        stopAutoRefresh() {
            if (this.state.autoRefreshTimer) {
                clearInterval(this.state.autoRefreshTimer);
                this.state.autoRefreshTimer = null;
            }
        },

        /**
         * Filter feed by search term
         */
        filterFeed(searchTerm) {
            const cards = this.elements.feedContainer?.querySelectorAll('.activity-card');
            cards?.forEach(card => {
                const title = card.querySelector('.card-title')?.textContent.toLowerCase() || '';
                const description = card.querySelector('.card-text')?.textContent.toLowerCase() || '';
                const matches = title.includes(searchTerm.toLowerCase()) || description.includes(searchTerm.toLowerCase());
                card.style.display = matches ? 'block' : 'none';
            });
        },

        /**
         * Attach event listeners to activity cards
         */
        attachActivityListeners() {
            // Like buttons
            document.querySelectorAll('.like-btn').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    e.preventDefault();
                    btn.classList.toggle('active');
                    btn.innerHTML = btn.classList.contains('active')
                        ? '<i class="bi bi-heart-fill"></i>'
                        : '<i class="bi bi-heart"></i>';
                });
            });

            // Share buttons
            document.querySelectorAll('.share-btn').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    e.preventDefault();
                    const activityId = btn.dataset.activityId;
                    if (navigator.share) {
                        navigator.share({
                            title: 'Check this out!',
                            text: 'Found an interesting activity in the feed',
                            url: window.location.href
                        });
                    } else {
                        alert('Share functionality not supported in this browser');
                    }
                });
            });
        },

        /**
         * Update last refreshed timestamp
         */
        updateLastRefreshed() {
            const now = new Date();
            this.elements.lastRefreshedSpan.textContent = now.toLocaleTimeString();
            this.state.lastRefreshTime = now.getTime();
        },

        /**
         * Update performance metrics display
         */
        updatePerformanceMetrics(responseTime, fromCache) {
            const responseMs = Math.round(responseTime);
            const responseTimeElement = document.getElementById('apiResponseTime');
            const cacheStatusElement = document.getElementById('cacheStatus');
            const responseTimeBar = document.getElementById('responseTimeBar');

            if (responseTimeElement) {
                responseTimeElement.textContent = `${responseMs}ms`;
            }

            // Set progress bar color based on response time
            let barColor = 'bg-success';
            if (responseMs > 1000) barColor = 'bg-warning';
            if (responseMs > 2000) barColor = 'bg-danger';

            if (responseTimeBar) {
                responseTimeBar.className = `progress-bar ${barColor}`;
                responseTimeBar.style.width = Math.min(100, (responseMs / 3000) * 100) + '%';
            }

            if (cacheStatusElement) {
                cacheStatusElement.innerHTML = fromCache
                    ? '<span class="badge bg-success">Cached</span>'
                    : '<span class="badge bg-info">Fresh</span>';
            }
        },

        /**
         * Show status message
         */
        showStatus(message, type = 'info') {
            const alertClass = `alert-${type}`;
            this.elements.statusAlert?.className.baseVal = `alert alert-dismissible fade show d-flex align-items-center ${alertClass}`;
            if (this.elements.statusMessage) {
                this.elements.statusMessage.textContent = message;
            }
            this.elements.statusAlert?.style.display = 'flex';

            setTimeout(() => {
                this.elements.statusAlert?.style.display = 'none';
            }, 5000);
        }
    };

    // Initialize feed manager
    FeedManager.init();

    // Expose to window for debugging
    window.FeedManager = FeedManager;
});
</script>

<?php render('layout/footer'); ?>
